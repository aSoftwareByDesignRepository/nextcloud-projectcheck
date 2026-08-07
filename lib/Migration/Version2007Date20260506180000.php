<?php

declare(strict_types=1);

/**
 * Ensure `pc_projects.project_type` exists on every install.
 *
 * Why this migration is necessary
 * -------------------------------
 *  The `project_type` column is referenced unconditionally by the `Project`
 *  entity (`OCA\ProjectCheck\Db\Project`), the projects controller, time
 *  entries reporting, dashboard analytics, the JS bundle and several
 *  templates. Despite that, no earlier migration in the projectcheck schema
 *  chain creates the column - the historical projectcontrol-era migration
 *  that introduced it has since been removed from the tree.
 *
 *  The result was that every fresh install AND every install that arrived at
 *  projectcheck via a partial projectcontrol migration ran headless without
 *  `project_type`. Earlier runtime code used fragile `columnExists()` shims;
 *  one of them silently produced invalid SQL (named parameter wrapped in
 *  backticks as if it were a column identifier) and crashed the time-entries
 *  page with a 500 error. Current application code assumes this migration
 *  has run and no longer performs column introspection for `project_type`.
 *
 *  This migration removes the entire class of failure at the schema level:
 *    - the column always exists with a sensible default (`'client'`),
 *    - it is `NOT NULL` so reads can never come back as `NULL`,
 *    - an index is added so the existing `WHERE project_type = …` filters
 *      run on an index instead of a full scan.
 *
 *  Idempotency
 *  -----------
 *  - Re-running is a no-op once both column and index exist.
 *  - Existing installs with the column already in place skip both the
 *    `addColumn` and the `addIndex` branches.
 *  - The `postSchemaChange` backfill only normalizes legacy `NULL` rows;
 *    on a freshly-created column the backfill is a no-op because the DDL
 *    `DEFAULT 'client'` already populated every row.
 *
 *  Sequencing note
 *  ---------------
 *  This step deliberately runs after `Version2006Date20260505224500`, which
 *  performs the legacy table rename (`projects` -> `pc_projects`). Running
 *  earlier would either fail to find the table on legacy installs or have
 *  to operate on the obsolete name.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Types;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Throwable;

class Version2007Date20260506180000 extends SimpleMigrationStep
{
	/**
	 * Default value for new rows AND for the legacy NULL backfill.
	 * Mirrors `OCA\ProjectCheck\Db\Project::getProjectType()`'s fallback so
	 * runtime behaviour is unchanged when the column is freshly added.
	 */
	private const DEFAULT_PROJECT_TYPE = 'client';

	/**
	 * Length matches the longest valid project type identifier
	 * (`'research'` = 8 chars) with comfortable headroom for future
	 * additions while staying inside MySQL's index-key-length limits with
	 * any character set.
	 */
	private const COLUMN_LENGTH = 32;

	public function __construct(
		private IDBConnection $db,
	) {
	}

	/**
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array<string, mixed> $options
	 * @return ISchemaWrapper|null
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		// `pc_projects` is created in `Version1000` (as legacy `projects`) and
		// renamed in `Version2006`. If it is absent here something earlier in
		// the chain failed; we silently no-op and let the operator fix that.
		if (!$schema->hasTable('pc_projects')) {
			return null;
		}

		$table = $schema->getTable('pc_projects');
		$changed = false;

		if (!$table->hasColumn('project_type')) {
			$table->addColumn('project_type', Types::STRING, [
				'notnull' => true,
				'length' => self::COLUMN_LENGTH,
				'default' => self::DEFAULT_PROJECT_TYPE,
			]);
			$changed = true;
		}

		// Same name shape used by `Version2004` for renaming the legacy
		// `projects_project_type_idx`. Adding it here when missing means
		// fresh installs (which never had the legacy index) end up at the
		// same steady-state schema as upgraded installs.
		if (!$table->hasIndex('pc_proj_type_idx')) {
			$table->addIndex(['project_type'], 'pc_proj_type_idx');
			$changed = true;
		}

		return $changed ? $schema : null;
	}

	/**
	 * Defensive backfill for legacy installs whose `project_type` column was
	 * created as nullable by an old projectcontrol-era draft. Setting any
	 * remaining `NULL` rows to the documented default lets the runtime treat
	 * the column as `NOT NULL` going forward without surprising the operator.
	 *
	 * On installs where the column was just created by `changeSchema` above,
	 * MySQL/MariaDB and PostgreSQL both populate every existing row with the
	 * DDL `DEFAULT 'client'`, so this step finds zero rows to update.
	 *
	 * @param IOutput $output
	 * @param Closure(): ISchemaWrapper $schemaClosure
	 * @param array<string, mixed> $options
	 */
	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
	{
		if (!$this->db->tableExists('pc_projects')) {
			return;
		}

		try {
			$qb = $this->db->getQueryBuilder();
			$qb->update('pc_projects')
				->set('project_type', $qb->createNamedParameter(self::DEFAULT_PROJECT_TYPE))
				->where($qb->expr()->isNull('project_type'));
			$affected = $qb->executeStatement();
			if ($affected > 0) {
				$output->info(sprintf(
					'ProjectCheck: backfilled %d row(s) of pc_projects.project_type from NULL to %s.',
					$affected,
					self::DEFAULT_PROJECT_TYPE,
				));
			}
		} catch (Throwable $e) {
			// Never block the migration on the backfill: if the UPDATE fails
			// (column was just created and the operator's DB does not allow
			// touching it in the same transaction, exotic engine, …) the
			// schema-level NOT NULL DEFAULT already keeps reads correct.
			$output->warning('ProjectCheck: project_type backfill skipped: ' . $e->getMessage());
		}
	}
}
