<?php

declare(strict_types=1);

/**
 * Normalize legacy PK / FK names to portable, app-prefixed identifiers.
 *
 * Why this matters
 * ----------------
 *  - Generic constraint names like `projects_pk` and `fk_members_project` can
 *    collide with other apps on PostgreSQL because constraint names are global
 *    within a Postgres schema.
 *  - Nextcloud's `MigrationService::ensureUniqueNamesConstraints` enforces this
 *    uniqueness on install (and warns on update).
 *  - We therefore rename them to the `pc_*` namespace before renaming the
 *    underlying tables in the next step (Version2006).
 *
 * Robustness guarantees
 * ---------------------
 *  - Idempotent: re-running the migration is a no-op once the new names exist.
 *  - Defensive against missing constraints: in some historical installs the
 *    initial FK migration (Version1003) silently failed - we add the FKs only
 *    when they are missing AND the data is integrity-clean (orphan rows are
 *    pruned in `preSchemaChange`).
 *  - Postgres PK constraint names are renamed via `ALTER ... RENAME CONSTRAINT`
 *    in `postSchemaChange`. The PK can NOT be dropped while child FKs exist,
 *    so we never use the drop+recreate dance for it.
 *  - On MySQL/MariaDB and SQLite the primary key constraint is not renameable
 *    (it is always `PRIMARY` on InnoDB and SQLite has no named PKs in the same
 *    sense), which is fine because uniqueness is per-table on those engines.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;

class Version2005Date20260505213000 extends SimpleMigrationStep
{
	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
	) {
	}

	/**
	 * Reads the table prefix from `IConfig` because the public
	 * `IDBConnection` interface does not expose `getPrefix()` and we must
	 * stay compatible with the `ConnectionAdapter` injection wrapper.
	 */
	private function tablePrefix(): string
	{
		return (string)$this->config->getSystemValue('dbtableprefix', 'oc_');
	}

	/**
	 * Prune orphan child rows so re-adding FK constraints can never fail on
	 * partially inconsistent data. We only delete rows whose parent identifier
	 * does not exist; data with a valid parent is never touched.
	 *
	 * Important: this runs BEFORE `changeSchema`, so the connection still
	 * speaks the legacy table names (`projects`, `customers`, `time_entries`,
	 * `project_members`).
	 */
	public function preSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
	{
		$this->pruneOrphans(
			$output,
			'project_members',
			'project_id',
			'projects',
			'id'
		);
		$this->pruneOrphans(
			$output,
			'time_entries',
			'project_id',
			'projects',
			'id'
		);
	}

	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		/** @var ISchemaWrapper $schema */
		$schema = $schemaClosure();

		$this->renameForeignKey(
			$schema,
			'projects',
			'fk_projects_customer',
			'pc_fk_proj_customer',
			'customers',
			['customer_id'],
			['id'],
			['onDelete' => 'RESTRICT']
		);
		$this->renameForeignKey(
			$schema,
			'project_members',
			'fk_members_project',
			'pc_fk_member_project',
			'projects',
			['project_id'],
			['id'],
			['onDelete' => 'CASCADE']
		);
		$this->renameForeignKey(
			$schema,
			'time_entries',
			'fk_time_entries_project',
			'pc_fk_te_project',
			'projects',
			['project_id'],
			['id'],
			['onDelete' => 'CASCADE']
		);

		return $schema;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
	{
		// PK constraint names are only globally namespaced on PostgreSQL.
		// MySQL/MariaDB always call the PK "PRIMARY" and SQLite stores it
		// without a global name - both are fine without intervention.
		if ($this->db->getDatabaseProvider() !== IDBConnection::PLATFORM_POSTGRES) {
			return;
		}

		$prefix = $this->tablePrefix();

		foreach ([
			['projects', 'pc_projects_pk'],
			['project_members', 'pc_members_pk'],
			['customers', 'pc_customers_pk'],
			['time_entries', 'pc_time_entries_pk'],
		] as [$logical, $newName]) {
			$table = $prefix . $logical;
			$this->postgresRenamePrimaryKeyConstraint($output, $table, $newName);
		}
	}

	/**
	 * Idempotent FK rename via Doctrine: we only DROP the legacy FK if it is
	 * present and only ADD the new FK if it is missing. Both branches are safe
	 * to run together in any combination, which is why this works for new
	 * installs (FK was created with the new name later in the chain), upgrades
	 * (FK exists with legacy name) and recovery (FK never existed at all).
	 *
	 * @param array<string, string> $options
	 * @param list<string> $localColumns
	 * @param list<string> $foreignColumns
	 */
	private function renameForeignKey(
		ISchemaWrapper $schema,
		string $tableName,
		string $oldName,
		string $newName,
		string $foreignTableName,
		array $localColumns,
		array $foreignColumns,
		array $options,
	): void {
		if (!$schema->hasTable($tableName) || !$schema->hasTable($foreignTableName)) {
			return;
		}

		$table = $schema->getTable($tableName);
		$foreignTable = $schema->getTable($foreignTableName);

		if ($table->hasForeignKey($oldName)) {
			$table->removeForeignKey($oldName);
		}

		if (!$table->hasForeignKey($newName)) {
			$table->addForeignKeyConstraint(
				$foreignTable,
				$localColumns,
				$foreignColumns,
				$options,
				$newName
			);
		}
	}

	/**
	 * Remove rows from `$childTable` whose foreign-key column points at a
	 * parent row that no longer exists. We do this with a small portable
	 * 2-step approach (collect IDs, then DELETE in batches) so we never depend
	 * on `DELETE ... FROM ... JOIN`, which is not portable.
	 */
	private function pruneOrphans(
		IOutput $output,
		string $childTable,
		string $childColumn,
		string $parentTable,
		string $parentColumn,
	): void {
		if (!$this->db->tableExists($childTable) || !$this->db->tableExists($parentTable)) {
			return;
		}

		$select = $this->db->getQueryBuilder();
		$select->select('c.id')
			->from($childTable, 'c')
			->leftJoin('c', $parentTable, 'p', $select->expr()->eq('c.' . $childColumn, 'p.' . $parentColumn))
			->where($select->expr()->isNull('p.' . $parentColumn));

		$rs = $select->executeQuery();
		$orphanIds = [];
		while ($row = $rs->fetch()) {
			if ($row['id'] !== null && $row['id'] !== '') {
				$orphanIds[] = (int)$row['id'];
			}
		}
		$rs->closeCursor();

		if ($orphanIds === []) {
			return;
		}

		$output->info(sprintf(
			'ProjectCheck: pruning %d orphan row(s) from %s before FK normalization.',
			count($orphanIds),
			$childTable
		));

		// Chunk to keep the IN-list well within all DB engines' parameter limits
		// (Oracle: 1000 expressions, others have higher limits).
		foreach (array_chunk($orphanIds, 500) as $chunk) {
			$delete = $this->db->getQueryBuilder();
			$delete->delete($childTable)
				->where($delete->expr()->in(
					'id',
					$delete->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)
				));
			$delete->executeStatement();
		}
	}

	private function postgresRenamePrimaryKeyConstraint(IOutput $output, string $prefixedTable, string $newName): void
	{
		$current = $this->postgresPrimaryConstraintName($prefixedTable);
		if ($current === null || $current === '') {
			return;
		}
		if (strtolower($current) === strtolower($newName)) {
			return;
		}

		try {
			$this->db->executeStatement(sprintf(
				'ALTER TABLE "%s" RENAME CONSTRAINT "%s" TO "%s"',
				$this->sanitizePgIdent($prefixedTable),
				$this->sanitizePgIdent($current),
				$this->sanitizePgIdent($newName)
			));
			$output->info('ProjectCheck (PG): renamed primary constraint ' . $current . ' to ' . $newName . ' on ' . $prefixedTable);
		} catch (\Throwable $e) {
			$output->warning('ProjectCheck (PG): could not rename PK on ' . $prefixedTable . ': ' . $e->getMessage());
		}
	}

	private function postgresPrimaryConstraintName(string $prefixedTable): ?string
	{
		$sql = <<<'SQL'
SELECT c.conname
FROM pg_constraint c
INNER JOIN pg_class r ON r.oid = c.conrelid
WHERE r.relname = ?
  AND c.contype = 'p'
LIMIT 1
SQL;

		try {
			$result = $this->db->executeQuery($sql, [$prefixedTable]);
			$name = $result->fetchOne();
			$result->closeCursor();

			return $name === false || $name === null ? null : (string)$name;
		} catch (\Throwable $e) {
			return null;
		}
	}

	private function sanitizePgIdent(string $name): string
	{
		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
			throw new \InvalidArgumentException('Invalid PostgreSQL identifier');
		}
		return $name;
	}
}
