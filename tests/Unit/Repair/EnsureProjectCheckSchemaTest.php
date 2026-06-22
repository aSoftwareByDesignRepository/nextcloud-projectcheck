<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Repair;

use OCA\ProjectCheck\Migration\LegacyTableRenamer;
use OCA\ProjectCheck\Migration\ProjectCheckTableCatalog;
use OCA\ProjectCheck\Repair\EnsureProjectCheckSchema;
use OCA\ProjectCheck\Repair\UninstallDropTables;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;

final class EnsureProjectCheckSchemaTest extends TestCase
{
	public function testClearsLegacyUninstallPassAndSucceedsWhenSchemaReady(): void
	{
		$legacyTables = array_keys(LegacyTableRenamer::RENAMES);

		$connection = $this->createMock(IDBConnection::class);
		$connection->method('tableExists')->willReturnCallback(
			static function (string $table) use ($legacyTables): bool {
				if (in_array($table, $legacyTables, true)) {
					return false;
				}

				return in_array($table, ProjectCheckTableCatalog::REQUIRED_TABLES, true);
			},
		);

		$config = $this->createMock(IConfig::class);
		$config->expects(self::once())
			->method('deleteAppValue')
			->with(UninstallDropTables::APP_ID, UninstallDropTables::REPAIR_PASS_KEY);

		$output = $this->createMock(IOutput::class);
		$output->expects(self::never())->method('info');

		$step = new EnsureProjectCheckSchema($connection, $config);
		$step->run($output);
	}
}
