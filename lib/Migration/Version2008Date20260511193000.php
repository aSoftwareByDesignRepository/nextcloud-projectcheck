<?php

declare(strict_types=1);

/**
 * Normalize whitespace on `pc_projects.name`.
 *
 * Why this migration is necessary
 * -------------------------------
 *  Project names are surfaced in alphabetically-sorted dropdowns (time-entry
 *  form, time-entries filter, …). Any stray leading whitespace on a stored
 *  name therefore sorted that project *before* every other row, even though
 *  the visible label looked identical.
 *
 *  Going forward `ProjectService::validateProjectData` trims `name` before
 *  both inserts and updates, so freshly created/edited rows can no longer
 *  reach the database with surrounding whitespace. This migration handles
 *  the historical rows that already have it.
 *
 *  Idempotency
 *  -----------
 *  - The UPDATE only targets rows where `TRIM(name) <> name`, so re-running
 *    after a successful pass is a no-op.
 *  - Schema is untouched (`changeSchema` returns null).
 *
 *  Sequencing
 *  ----------
 *  Runs after Version2006/Version2007 which finalize the `pc_projects`
 *  table name and `project_type` column. The cleanup only touches `name`,
 *  so there is no dependency on the `project_type` work beyond the table
 *  existing under its current name.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Throwable;

class Version2008Date20260511193000 extends SimpleMigrationStep
{
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
		return null;
	}

	/**
	 * Trim leading/trailing whitespace on existing project names.
	 *
	 * We run this in `postSchemaChange` rather than in a hand-written raw
	 * SQL block so that the doctrine driver picks the right TRIM() flavour
	 * for the connected DB (MariaDB/MySQL and PostgreSQL both support
	 * standard `TRIM(name)`). Failures are warned, not thrown: a stray
	 * space in the data is undesirable but not a blocker for upgrade.
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
			// ANSI TRIM() and `<>` work on MySQL/MariaDB, PostgreSQL, and SQLite.
			// Never use MySQL identifier backticks here — PostgreSQL rejects them.
			$affected = $this->db->executeStatement(
				'UPDATE *PREFIX*pc_projects SET name = TRIM(name) WHERE TRIM(name) <> name'
			);
			if ($affected > 0) {
				$output->info(sprintf(
					'ProjectCheck: trimmed whitespace on %d pc_projects.name row(s).',
					$affected,
				));
			}
		} catch (Throwable $e) {
			$output->warning('ProjectCheck: pc_projects.name whitespace cleanup skipped: ' . $e->getMessage());
		}
	}
}
