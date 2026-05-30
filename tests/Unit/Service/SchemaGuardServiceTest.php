<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use OCA\ProjectCheck\Exception\SchemaRepairFailedException;
use OCA\ProjectCheck\Migration\LegacyTableRenamer;
use OCA\ProjectCheck\Migration\ProjectCheckSchemaEnsurer;
use OCA\ProjectCheck\Migration\ProjectCheckTableCatalog;
use OCA\ProjectCheck\Service\SchemaGuardService;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Lock\ILockingProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SchemaGuardServiceTest extends TestCase
{
	protected function tearDown(): void
	{
		$ref = new \ReflectionClass(SchemaGuardService::class);
		$prop = $ref->getProperty('requestState');
		$prop->setAccessible(true);
		$prop->setValue(null, null);
		parent::tearDown();
	}

	public function testEnsureReadySkipsWhenSchemaComplete(): void
	{
		$db = $this->createMock(IDBConnection::class);
		$db->method('tableExists')->willReturnCallback(
			static function (string $table): bool {
				return in_array($table, ProjectCheckTableCatalog::REQUIRED_TABLES, true);
			}
		);

		$config = $this->createMock(IConfig::class);
		$logger = $this->createMock(LoggerInterface::class);
		$logger->expects(self::never())->method('warning');

		$guard = new SchemaGuardService($db, $config, $logger, $this->lockingMock());
		$guard->ensureReady();
		$guard->ensureReady();
	}

	private function lockingMock(): ILockingProvider
	{
		$locking = $this->createMock(ILockingProvider::class);
		$locking->method('acquireLock');
		$locking->method('releaseLock');
		return $locking;
	}

	public function testEnsureReadyThrowsWhenRepairFails(): void
	{
		$db = $this->createMock(IDBConnection::class);
		$db->method('tableExists')->willReturnCallback(
			static function (string $table): bool {
				if (in_array($table, ProjectCheckTableCatalog::REQUIRED_TABLES, true)) {
					return $table === 'pc_projects';
				}
				return !isset(LegacyTableRenamer::RENAMES[$table]);
			}
		);

		$config = $this->createMock(IConfig::class);
		$logger = $this->createMock(LoggerInterface::class);

		$guard = new SchemaGuardService($db, $config, $logger, $this->lockingMock());

		$this->expectException(SchemaRepairFailedException::class);
		$guard->ensureReady();
	}

	public function testRequiredTablesIncludeTimeEntries(): void
	{
		self::assertContains('pc_time_entries', ProjectCheckSchemaEnsurer::REQUIRED_TABLES);
		self::assertContains('pc_project_files', ProjectCheckSchemaEnsurer::REQUIRED_TABLES);
	}
}
