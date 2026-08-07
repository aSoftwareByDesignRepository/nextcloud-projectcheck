<?php

declare(strict_types=1);

/**
 * Renames legacy overlong rate-history table names to {@see RateHistoryTables}.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Migration;

use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use RuntimeException;

final class RateTableRenamer
{
	public function __construct(
		private IDBConnection $db,
		private IConfig $config,
	) {
	}

	public function run(IOutput $output): void
	{
		foreach (RateHistoryTables::LEGACY_RENAMES as $oldName => $newName) {
			$this->renameIfPresent($oldName, $newName, $output);
		}
	}

	private function renameIfPresent(string $oldName, string $newName, IOutput $output): void
	{
		$oldExists = $this->db->tableExists($oldName);
		$newExists = $this->db->tableExists($newName);

		if (!$oldExists) {
			return;
		}

		if ($newExists) {
			throw new RuntimeException(sprintf(
				'ProjectCheck: cannot rename %s to %s — target already exists. Merge or drop one table manually.',
				$oldName,
				$newName,
			));
		}

		$prefix = (string)$this->config->getSystemValue('dbtableprefix', 'oc_');
		$oldTable = $prefix . $oldName;
		$newTable = $prefix . $newName;
		$this->assertSafeIdentifier($oldTable);
		$this->assertSafeIdentifier($newTable);
		$provider = $this->db->getDatabaseProvider();

		if ($provider === IDBConnection::PLATFORM_MYSQL) {
			$this->db->executeStatement(sprintf(
				'RENAME TABLE `%s` TO `%s`',
				$oldTable,
				$newTable,
			));
		} else {
			$this->db->executeStatement(sprintf(
				'ALTER TABLE "%s" RENAME TO "%s"',
				$oldTable,
				$newTable,
			));
		}

		$output->info(sprintf('ProjectCheck: renamed rate table %s to %s.', $oldName, $newName));
	}

	private function assertSafeIdentifier(string $identifier): void
	{
		if (!preg_match('/^[a-zA-Z0-9_]+$/', $identifier)) {
			throw new RuntimeException('ProjectCheck: invalid database identifier.');
		}
	}
}
