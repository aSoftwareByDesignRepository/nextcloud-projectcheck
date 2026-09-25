<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Integration;

use OCA\ProjectCheck\Exception\UpgradeBackupException;
use OCA\ProjectCheck\Service\UpgradeBackupService;
use OCA\ProjectCheck\Tests\Support\UpgradeBackupStateGuard;
use OCP\IDBConnection;
use Test\TestCase;

final class UpgradeBackupIntegrationTest extends TestCase
{
	private UpgradeBackupService $backupService;
	private IDBConnection $db;
	private UpgradeBackupStateGuard $backupStateGuard;

	protected function setUp(): void
	{
		parent::setUp();
		$this->backupService = \OC::$server->get(UpgradeBackupService::class);
		$this->db = \OC::$server->get(IDBConnection::class);
		$this->backupStateGuard = new UpgradeBackupStateGuard();
		$this->backupStateGuard->setUp();
	}

	protected function tearDown(): void
	{
		$this->backupStateGuard->tearDown();
		parent::tearDown();
	}

	/**
	 * Snapshot → wipe → restore round-trip.
	 *
	 * Single-writer assumption: the snapshot→restore window truncates every
	 * backup table and rewrites it from the snapshot — that IS the feature
	 * under test. On a shared instance a concurrent writer's rows inserted
	 * inside the window are destroyed; the suite assumes no concurrent
	 * writer for the duration (documented hazard, bounded to one test).
	 * The assertion is on row IDENTITY (id set), not just the count — a
	 * count match with different rows would hide a partial restore.
	 */
	public function testCreateListAndRestoreRoundTrip(): void
	{
		if (!$this->db->tableExists('pc_projects')) {
			self::markTestSkipped('ProjectCheck tables not present in this instance.');
		}

		$beforeIds = $this->rowIds('pc_projects');

		$result = $this->backupService->createSnapshot('integration-test');
		$snapshotId = $result['id'];
		self::assertNotSame('', $snapshotId);
		self::assertTrue($result['manifest']['complete'] ?? false);
		self::assertNotEmpty($result['manifest']['tables'] ?? [], 'Snapshot must include table metadata when tables exist.');

		$snapshots = $this->backupService->listSnapshots();
		$ids = array_map(static fn (array $snapshot): string => (string)($snapshot['id'] ?? ''), $snapshots);
		self::assertContains($snapshotId, $ids, 'listSnapshots must find the snapshot just created');

		$this->db->getQueryBuilder()
			->delete('pc_projects')
			->executeStatement();
		self::assertSame([], $this->rowIds('pc_projects'));

		$this->backupService->restoreSnapshot($snapshotId, false);
		self::assertSame(
			$beforeIds,
			$this->rowIds('pc_projects'),
			'restore must bring back the identical pre-existing row set, not just the same count',
		);
	}

	public function testRestoreRejectsInvalidSnapshotId(): void
	{
		$this->expectException(UpgradeBackupException::class);
		$this->backupService->restoreSnapshot('../evil', false);
	}

	/**
	 * @return list<int>
	 */
	private function rowIds(string $table): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')->from($table)->orderBy('id', 'ASC');
		$result = $qb->executeQuery();
		$ids = array_map('intval', $result->fetchAll(\PDO::FETCH_COLUMN));
		$result->closeCursor();

		return $ids;
	}
}
