<?php

declare(strict_types=1);

/**
 * Final step of the legacy → app-prefixed table normalization.
 *
 * Background
 * ----------
 *  Historical ProjectCheck installs created tables with generic names
 *  (`projects`, `customers`, `time_entries`, `project_members`, `project_files`)
 *  which are not Nextcloud-best-practice and can collide with other apps that
 *  reasonably want to use the same names. This migration renames each of those
 *  tables to its `pc_` app-prefixed equivalent and brings the live database in
 *  line with the QBMappers / Entities (which already reference `pc_*`).
 *
 * Engine semantics during a rename
 * --------------------------------
 *  - MariaDB/InnoDB  : `RENAME TABLE` updates internal FK references.
 *  - PostgreSQL      : FKs reference table OIDs, so renames follow.
 *                      *Sequences* however do NOT follow - we explicitly
 *                      `ALTER SEQUENCE ... RENAME TO ...` so the implicit
 *                      identity sequence keeps the table-prefixed name.
 *  - SQLite (>=3.25) : updates references inside FK definitions.
 *
 * Idempotency / recovery
 * ----------------------
 *  - Re-running is a no-op once the new table exists with data.
 *  - If a previous failed attempt left an EMPTY target table behind (visible
 *    in dev environments that have iterated on this migration), we drop it
 *    before performing the rename.
 *  - If a target table exists AND already holds rows AND a legacy table also
 *    still exists, we abort with a clear error: this means data could be lost
 *    by an automated decision, and the operator must reconcile manually.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Migration;

use Closure;
use OCP\DB\ISchemaWrapper;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use OCP\Migration\SimpleMigrationStep;
use RuntimeException;

class Version2006Date20260505224500 extends SimpleMigrationStep
{
	/**
	 * Mapping of legacy table names to their app-prefixed targets.
	 * Renames happen in dependency order: parents (referenced) before
	 * children (referencing) - that keeps ON RENAME constraint behaviour
	 * robust on every engine and is also the order in which the test suite
	 * inserts data, so accidental cycles surface immediately.
	 *
	 * @var array<string, string>
	 */
	private const RENAMES = [
		'customers' => 'pc_customers',
		'projects' => 'pc_projects',
		'project_members' => 'pc_project_members',
		'time_entries' => 'pc_time_entries',
		'project_files' => 'pc_project_files',
	];

	/**
	 * Stale target names that historic, since-removed migration drafts may
	 * have created. They are dropped only when they are empty - never when
	 * they hold data, so we can never cause data loss here.
	 *
	 * @var list<string>
	 */
	private const KNOWN_STALE_TARGETS = [
		'pc_customers',
		'pc_projects',
		'pc_project_members',
		'pc_time_entries',
		'pc_project_files',
		// Earlier draft used the name `pc_members` (without the `project_`
		// segment) for `project_members`. Drop it if found and empty.
		'pc_members',
	];

	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
	) {
	}

	/**
	 * Resolve the configured database table prefix. We prefer reading it from
	 * `IConfig` because the public `IDBConnection` interface intentionally
	 * does not expose `getPrefix()` (only the internal `OC\DB\Connection`
	 * does), and we must remain compatible with `ConnectionAdapter` wrappers.
	 */
	private function tablePrefix(): string
	{
		return (string)$this->config->getSystemValue('dbtableprefix', 'oc_');
	}

	/**
	 * `changeSchema` is intentionally a no-op: every operation here is a raw
	 * DDL rename or DROP that bypasses Doctrine's schema diff. Returning the
	 * live schema would only invite the diff engine to reverse our renames.
	 */
	public function changeSchema(IOutput $output, Closure $schemaClosure, array $options): ?ISchemaWrapper
	{
		return null;
	}

	public function postSchemaChange(IOutput $output, Closure $schemaClosure, array $options): void
	{
		$this->dropStaleEmptyTargets($output);
		$this->renameLegacyTables($output);

		// On PostgreSQL identity sequences keep the OLD table name after
		// `ALTER TABLE ... RENAME TO ...`. Bring them in line so future schema
		// diffs do not flag the mismatch.
		if ($this->db->getDatabaseProvider() === IDBConnection::PLATFORM_POSTGRES) {
			$this->renamePostgresSequences($output);
		}
	}

	/**
	 * Drop any of the well-known stale target tables that were left behind
	 * by an earlier attempt of this rename and contain no rows. Data is never
	 * deleted by this method.
	 */
	private function dropStaleEmptyTargets(IOutput $output): void
	{
		$legacyStillPresent = array_filter(
			array_keys(self::RENAMES),
			fn (string $legacy): bool => $this->db->tableExists($legacy)
		);

		// If no legacy tables remain, the rename has already happened (or the
		// install is already on the new layout). In that case we must not
		// drop any `pc_*` tables - they are the live ones.
		if ($legacyStillPresent === []) {
			return;
		}

		foreach (self::KNOWN_STALE_TARGETS as $stale) {
			if (!$this->db->tableExists($stale)) {
				continue;
			}
			if (!$this->isTableEmpty($stale)) {
				// Will be reported by `assertSafeToRename` below if it
				// blocks a rename, with a clearer error message.
				continue;
			}

			$prefixedStale = $this->tablePrefix() . $stale;
			$this->dropTableRaw($prefixedStale);
			$output->info(sprintf(
				'ProjectCheck: dropped stale empty table %s left over from a prior migration attempt.',
				$stale
			));
		}
	}

	private function renameLegacyTables(IOutput $output): void
	{
		foreach (self::RENAMES as $oldName => $newName) {
			$this->renameTableIfPresent($oldName, $newName, $output);
		}
	}

	private function renameTableIfPresent(string $oldName, string $newName, IOutput $output): void
	{
		$oldExists = $this->db->tableExists($oldName);
		$newExists = $this->db->tableExists($newName);

		if (!$oldExists && $newExists) {
			// Already renamed (steady state). Nothing to do.
			return;
		}

		if (!$oldExists && !$newExists) {
			// Neither legacy nor target exists. This can happen for
			// `project_files` on installs older than v2.0.27 if the table
			// was never created. Skip silently - subsequent migrations
			// will create the new-name table when needed.
			return;
		}

		if ($oldExists && $newExists) {
			$this->assertSafeToRename($oldName, $newName);
			// `assertSafeToRename` only returns when the target is empty.
			$prefixedTarget = $this->tablePrefix() . $newName;
			$this->dropTableRaw($prefixedTarget);
			$output->info(sprintf(
				'ProjectCheck: dropped empty %s before renaming %s into place.',
				$newName,
				$oldName
			));
		}

		$this->doRenameTable($oldName, $newName, $output);
	}

	private function assertSafeToRename(string $oldName, string $newName): void
	{
		if ($this->isTableEmpty($newName)) {
			return;
		}
		throw new RuntimeException(sprintf(
			'ProjectCheck: refusing to rename %s -> %s because the target '
			. 'already exists and is non-empty. Manual reconciliation is '
			. 'required (most likely the data should be merged into %s).',
			$oldName,
			$newName,
			$newName
		));
	}

	private function doRenameTable(string $oldName, string $newName, IOutput $output): void
	{
		$prefix = $this->tablePrefix();
		$oldTable = $prefix . $oldName;
		$newTable = $prefix . $newName;
		$provider = $this->db->getDatabaseProvider();

		$this->assertSafeIdentifier($oldTable);
		$this->assertSafeIdentifier($newTable);

		if ($provider === IDBConnection::PLATFORM_MYSQL) {
			$this->db->executeStatement(sprintf(
				'RENAME TABLE `%s` TO `%s`',
				$oldTable,
				$newTable
			));
		} else {
			// PostgreSQL, SQLite and Oracle all accept the standard form.
			$this->db->executeStatement(sprintf(
				'ALTER TABLE "%s" RENAME TO "%s"',
				$oldTable,
				$newTable
			));
		}

		$output->info(sprintf('ProjectCheck: renamed table %s to %s.', $oldName, $newName));
	}

	/**
	 * Rename the implicit identity sequences that PostgreSQL creates for
	 * `bigint NOT NULL AUTO_INCREMENT` columns. After `ALTER TABLE ... RENAME
	 * TO ...` Postgres keeps the sequence under its old name (e.g.
	 * `oc_projects_id_seq`) which is misleading and makes future schema
	 * diffs noisy. Renaming is purely cosmetic but very important for the
	 * auditor and for clean Doctrine reflection.
	 */
	private function renamePostgresSequences(IOutput $output): void
	{
		$prefix = $this->tablePrefix();

		foreach (self::RENAMES as $oldName => $newName) {
			$oldSeq = $prefix . $oldName . '_id_seq';
			$newSeq = $prefix . $newName . '_id_seq';

			if (!$this->postgresSequenceExists($oldSeq)) {
				continue;
			}
			if ($this->postgresSequenceExists($newSeq)) {
				// Already renamed.
				continue;
			}

			$this->assertSafeIdentifier($oldSeq);
			$this->assertSafeIdentifier($newSeq);

			try {
				$this->db->executeStatement(sprintf(
					'ALTER SEQUENCE "%s" RENAME TO "%s"',
					$oldSeq,
					$newSeq
				));
				$output->info(sprintf(
					'ProjectCheck (PG): renamed sequence %s to %s.',
					$oldSeq,
					$newSeq
				));
			} catch (\Throwable $e) {
				// The sequence may not exist (e.g. if PG used identity
				// columns differently) - we log and continue, never block.
				$output->warning(sprintf(
					'ProjectCheck (PG): could not rename sequence %s: %s',
					$oldSeq,
					$e->getMessage()
				));
			}
		}
	}

	private function postgresSequenceExists(string $sequenceName): bool
	{
		try {
			$rs = $this->db->executeQuery(
				'SELECT 1 FROM pg_class WHERE relkind = \'S\' AND relname = ?',
				[$sequenceName]
			);
			$found = $rs->fetchOne();
			$rs->closeCursor();
			return $found !== false;
		} catch (\Throwable $e) {
			return false;
		}
	}

	private function isTableEmpty(string $logicalTable): bool
	{
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select($qb->func()->count('*', 'c'))
				->from($logicalTable);
			$rs = $qb->executeQuery();
			$row = $rs->fetch();
			$rs->closeCursor();
			return ((int)($row['c'] ?? 0)) === 0;
		} catch (\Throwable $e) {
			// If we cannot count, treat as non-empty for safety.
			return false;
		}
	}

	private function dropTableRaw(string $prefixedTable): void
	{
		$this->assertSafeIdentifier($prefixedTable);

		// `DROP TABLE IF EXISTS` is portable, but the quoting rules are not:
		// MySQL/MariaDB use backticks while PostgreSQL and SQLite use ANSI
		// double quotes. We dispatch on the database provider so the
		// statement parses cleanly on every supported engine.
		if ($this->db->getDatabaseProvider() === IDBConnection::PLATFORM_MYSQL) {
			$this->db->executeStatement(sprintf(
				'DROP TABLE IF EXISTS `%s`',
				$prefixedTable
			));
			return;
		}

		$this->db->executeStatement(sprintf(
			'DROP TABLE IF EXISTS "%s"',
			$prefixedTable
		));
	}

	/**
	 * Hard guard against ever interpolating user-influenceable strings into
	 * raw DDL. Identifiers are constructed from constants here, but the guard
	 * stays in place to make the audit trail trivially correct.
	 */
	private function assertSafeIdentifier(string $name): void
	{
		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
			throw new \InvalidArgumentException('Invalid SQL identifier: ' . $name);
		}
	}
}
