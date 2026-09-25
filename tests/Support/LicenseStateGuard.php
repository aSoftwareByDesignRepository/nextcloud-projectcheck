<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Support;

use OCP\IDBConnection;

/**
 * Guards the shared license/seat tables across mobile HTTP integration tests.
 *
 * The previous wipeLicense() ran unscoped deleteAll() on pc_license_state and
 * pc_mobile_seats in setUp AND tearDown — on any instance with a real license
 * installed, the suite destroyed it permanently with no restore. The guard
 * captures the full row sets (raw, preserving ids) before wiping, and in
 * tearDown wipes the test-created state then re-inserts the captured rows.
 */
final class LicenseStateGuard
{
	private const TABLES = ['pc_license_state', 'pc_mobile_seats'];

	private IDBConnection $db;

	/** @var array<string, list<array<string, mixed>>> */
	private array $captured = [];

	public function setUp(): void
	{
		$this->db = \OC::$server->get(IDBConnection::class);
		foreach (self::TABLES as $table) {
			$this->captured[$table] = $this->readRows($table);
			$this->deleteRows($table);
		}
	}

	public function tearDown(): void
	{
		foreach (self::TABLES as $table) {
			$this->deleteRows($table);
			$this->insertRows($table, $this->captured[$table] ?? []);
		}
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	private function readRows(string $table): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')->from($table);
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();

		return $rows;
	}

	private function deleteRows(string $table): void
	{
		$qb = $this->db->getQueryBuilder();
		$qb->delete($table)->executeStatement();
	}

	/**
	 * @param list<array<string, mixed>> $rows
	 */
	private function insertRows(string $table, array $rows): void
	{
		foreach ($rows as $row) {
			$qb = $this->db->getQueryBuilder();
			$qb->insert($table);
			foreach ($row as $column => $value) {
				$qb->setValue((string)$column, $qb->createNamedParameter($value));
			}
			$qb->executeStatement();
		}
	}
}
