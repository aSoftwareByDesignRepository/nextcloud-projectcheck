<?php

declare(strict_types=1);

/**
 * Add referential integrity for `project_files`.
 *
 * Audit reference: `pm/app-ideas/projectcheck/AUDIT-FINDINGS.md` finding A4 -
 * orphan rows were possible because `project_files.project_id` had no FK and
 * relied on application-side cleanup only.
 *
 * Steps:
 *  1. preSchemaChange : drop orphan rows that reference a missing project,
 *     and best-effort delete the corresponding files from app storage so the
 *     CASCADE FK below cannot leave behind unreferenced blobs.
 *  2. changeSchema    : align the column type with `projects.id` (some DB
 *     engines refuse FKs across mismatched signedness) and add the FK with
 *     `ON DELETE CASCADE` so deleting a project also removes its file rows.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\Files\AppData\IAppDataFactory;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use Throwable;

class Version2003Date20260430120000 extends SimpleMigrationStep
{
	public function __construct(
		private IDBConnection $db,
		private IAppDataFactory $appDataFactory,
	) {
	}

	/**
	 * Drop orphan `project_files` rows before we apply the FK so that
	 * `ON DELETE CASCADE` cannot fail on inconsistent legacy data.
	 *
	 * Best-effort blob cleanup is logged but never blocks the migration.
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
	{
		if (!$this->db->tableExists('project_files') || !$this->db->tableExists('projects')) {
			return;
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select('f.id', 'f.storage_path', 'f.project_id')
			->from('project_files', 'f')
			->leftJoin('f', 'projects', 'p', $qb->expr()->eq('f.project_id', 'p.id'))
			->where($qb->expr()->isNull('p.id'));

		$rs = $qb->executeQuery();
		$orphans = [];
		while ($row = $rs->fetch()) {
			$orphans[] = [
				'id' => (int)$row['id'],
				'storage_path' => (string)($row['storage_path'] ?? ''),
				'project_id' => (string)($row['project_id'] ?? ''),
			];
		}
		$rs->closeCursor();

		if ($orphans === []) {
			$output->info('ProjectCheck: no orphan project_files rows detected.');
			return;
		}

		$output->info('ProjectCheck: removing ' . count($orphans) . ' orphan project_files row(s).');

		// Best-effort blob cleanup.
		try {
			$appData = $this->appDataFactory->get('projectcheck');
		} catch (Throwable $e) {
			$appData = null;
		}

		foreach ($orphans as $orphan) {
			if ($appData !== null && $orphan['storage_path'] !== '') {
				$parts = explode('/', $orphan['storage_path']);
				if (count($parts) === 3 && $parts[0] === 'project_files') {
					try {
						$root = $appData->getFolder($parts[0]);
						$folder = $root->getFolder($parts[1]);
						$folder->getFile($parts[2])->delete();
					} catch (Throwable $e) {
						// non-fatal: just log and continue
						$output->info('ProjectCheck: could not remove orphan blob ' . $orphan['storage_path'] . ' (' . $e->getMessage() . ')');
					}
				}
			}

			$del = $this->db->getQueryBuilder();
			$del->delete('project_files')
				->where($del->expr()->eq('id', $del->createNamedParameter($orphan['id'], \OCP\DB\QueryBuilder\IQueryBuilder::PARAM_INT)));
			$del->executeStatement();
		}
	}

	/**
	 * @param IOutput $output
	 * @param Closure $schemaClosure The `\Closure` returns a `ISchemaWrapper`
	 * @param array $options
	 * @return null|ISchemaWrapper
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options)
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		if (!$schema->hasTable('project_files') || !$schema->hasTable('projects')) {
			return null;
		}

		$projectFiles = $schema->getTable('project_files');
		$projects = $schema->getTable('projects');

		// Align the column type with `projects.id`. Some DB engines refuse FKs
		// when one side is signed and the other unsigned.
		if ($projectFiles->hasColumn('project_id')) {
			$column = $projectFiles->getColumn('project_id');
			$column->setUnsigned(false);
			$column->setNotnull(true);
		}

		if (!$projectFiles->hasForeignKey('fk_pc_files_project')) {
			$projectFiles->addForeignKeyConstraint(
				$projects,
				['project_id'],
				['id'],
				['onDelete' => 'CASCADE'],
				'fk_pc_files_project'
			);
		}

		return $schema;
	}
}
