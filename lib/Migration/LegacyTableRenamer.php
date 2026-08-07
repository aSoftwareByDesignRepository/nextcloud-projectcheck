<?php

declare(strict_types=1);

/**
 * Renames legacy generic ProjectCheck tables to the `pc_*` namespace.
 *
 * Shared by {@see Version2006Date20260505224500} and the repair migration
 * {@see Version2010Date20260601120000}.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Migration;

use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use RuntimeException;

final class LegacyTableRenamer
{
	/** @var array<string, string> */
	public const RENAMES = [
		'customers' => 'pc_customers',
		'projects' => 'pc_projects',
		'project_members' => 'pc_project_members',
		'time_entries' => 'pc_time_entries',
		'project_files' => 'pc_project_files',
	];

	/** @var list<string> */
	private const KNOWN_STALE_TARGETS = [
		'pc_customers',
		'pc_projects',
		'pc_project_members',
		'pc_time_entries',
		'pc_project_files',
		'pc_members',
	];

	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
	) {
	}

	public function run(IOutput $output): void
	{
		$this->dropStaleEmptyTargets($output);
		$this->renameLegacyTables($output);

		if ($this->db->getDatabaseProvider() === IDBConnection::PLATFORM_POSTGRES) {
			$this->renamePostgresSequences($output);
		}
	}

	private function tablePrefix(): string
	{
		return (string)$this->config->getSystemValue('dbtableprefix', 'oc_');
	}

	private function dropStaleEmptyTargets(IOutput $output): void
	{
		$legacyStillPresent = array_filter(
			array_keys(self::RENAMES),
			fn (string $legacy): bool => $this->db->tableExists($legacy)
		);

		if ($legacyStillPresent === []) {
			return;
		}

		foreach (self::KNOWN_STALE_TARGETS as $stale) {
			if (!$this->db->tableExists($stale)) {
				continue;
			}
			if (!$this->isTableEmpty($stale)) {
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
			return;
		}

		if (!$oldExists && !$newExists) {
			return;
		}

		if ($oldExists && $newExists) {
			$this->assertSafeToRename($oldName, $newName);
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
			$this->db->executeStatement(sprintf(
				'ALTER TABLE "%s" RENAME TO "%s"',
				$oldTable,
				$newTable
			));
		}

		$output->info(sprintf('ProjectCheck: renamed table %s to %s.', $oldName, $newName));
	}

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
			return false;
		}
	}

	private function dropTableRaw(string $prefixedTable): void
	{
		$this->assertSafeIdentifier($prefixedTable);

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

	private function assertSafeIdentifier(string $name): void
	{
		if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
			throw new \InvalidArgumentException('Invalid SQL identifier: ' . $name);
		}
	}
}
