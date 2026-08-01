<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Migration;

use Doctrine\DBAL\Schema\Table;
use OCA\ProjectCheck\Migration\PcCoreSchemaBootstrap;
use OCP\DB\ISchemaWrapper;
use PHPUnit\Framework\TestCase;

/**
 * Ensure repair creates license tables when migrations were marked complete without effect.
 */
final class PcCoreSchemaBootstrapLicenseTest extends TestCase
{
	public function testEnsureLicenseTablesCreatesBothWhenMissing(): void
	{
		$created = [];
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->willReturnCallback(static function (string $name) use (&$created): bool {
			return isset($created[$name]);
		});
		$schema->method('createTable')->willReturnCallback(function (string $name) use (&$created): Table {
			$created[$name] = true;
			$table = $this->getMockBuilder(Table::class)
				->disableOriginalConstructor()
				->onlyMethods(['addColumn', 'setPrimaryKey', 'addUniqueIndex', 'addIndex'])
				->getMock();
			$table->method('addColumn')->willReturnSelf();
			$table->method('setPrimaryKey')->willReturnSelf();
			$table->method('addUniqueIndex')->willReturnSelf();
			$table->method('addIndex')->willReturnSelf();
			return $table;
		});

		self::assertTrue(PcCoreSchemaBootstrap::ensureLicenseTables($schema));
		self::assertArrayHasKey('pc_license_state', $created);
		self::assertArrayHasKey('pc_mobile_seats', $created);
		self::assertFalse(PcCoreSchemaBootstrap::ensureLicenseTables($schema));
	}

	public function testEnsureMobileIdempotencyTableCreatesWhenMissing(): void
	{
		$created = [];
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->willReturnCallback(static function (string $name) use (&$created): bool {
			return isset($created[$name]);
		});
		$schema->method('createTable')->willReturnCallback(function (string $name) use (&$created): Table {
			$created[$name] = true;
			$table = $this->getMockBuilder(Table::class)
				->disableOriginalConstructor()
				->onlyMethods(['addColumn', 'setPrimaryKey', 'addUniqueIndex', 'addIndex'])
				->getMock();
			$table->method('addColumn')->willReturnSelf();
			$table->method('setPrimaryKey')->willReturnSelf();
			$table->method('addUniqueIndex')->willReturnSelf();
			$table->method('addIndex')->willReturnSelf();
			return $table;
		});

		self::assertTrue(PcCoreSchemaBootstrap::ensureMobileIdempotencyTable($schema));
		self::assertArrayHasKey('pc_mob_idem', $created);
		self::assertFalse(PcCoreSchemaBootstrap::ensureMobileIdempotencyTable($schema));
	}
}
