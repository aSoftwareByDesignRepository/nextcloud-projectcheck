<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Migration;

use OCA\ProjectCheck\Migration\PcCoreSchemaBootstrap;
use OCP\DB\ISchemaWrapper;
use OCP\DB\Schema\IColumn;
use OCP\DB\Schema\ITable;
use PHPUnit\Framework\TestCase;

/**
 * Ensure repair creates license tables when migrations were marked complete without effect.
 */
final class PcCoreSchemaBootstrapLicenseTest extends TestCase
{
	private function tableMock(): ITable
	{
		$table = $this->createMock(ITable::class);
		// Nextcloud 35: addColumn() returns IColumn (not the table).
		$table->method('addColumn')->willReturn($this->createMock(IColumn::class));
		$table->method('setPrimaryKey')->willReturnSelf();
		$table->method('addUniqueIndex')->willReturnSelf();
		$table->method('addIndex')->willReturnSelf();
		return $table;
	}

	public function testEnsureLicenseTablesCreatesBothWhenMissing(): void
	{
		$created = [];
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->willReturnCallback(static function (string $name) use (&$created): bool {
			return isset($created[$name]);
		});
		$schema->method('createTable')->willReturnCallback(function (string $name) use (&$created): ITable {
			$created[$name] = true;
			return $this->tableMock();
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
		$schema->method('createTable')->willReturnCallback(function (string $name) use (&$created): ITable {
			$created[$name] = true;
			return $this->tableMock();
		});
		// Second call: table exists and already has every column → no change.
		$schema->method('getTable')->willReturnCallback(function (): ITable {
			$t = $this->tableMock();
			$t->method('hasColumn')->willReturn(true);
			return $t;
		});

		self::assertTrue(PcCoreSchemaBootstrap::ensureMobileIdempotencyTable($schema));
		self::assertArrayHasKey('pc_mob_idem', $created);
		self::assertFalse(PcCoreSchemaBootstrap::ensureMobileIdempotencyTable($schema));
	}

	public function testEnsureMobileIdempotencyTableAddsPayloadHashToLegacyTable(): void
	{
		$schema = $this->createMock(ISchemaWrapper::class);
		$schema->method('hasTable')->with('pc_mob_idem')->willReturn(true);
		$schema->expects(self::never())->method('createTable');

		$existing = $this->createMock(ITable::class);
		$existing->method('hasColumn')->willReturnCallback(
			static fn (string $c): bool => $c !== 'payload_hash'
		);
		$existing->expects(self::once())
			->method('addColumn')
			->with('payload_hash', self::anything(), self::anything())
			->willReturn($this->createMock(IColumn::class));
		$schema->method('getTable')->with('pc_mob_idem')->willReturn($existing);

		self::assertTrue(PcCoreSchemaBootstrap::ensureMobileIdempotencyTable($schema));
	}
}
