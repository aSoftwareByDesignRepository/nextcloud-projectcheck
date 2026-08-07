<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Repair;

use OCA\ProjectCheck\Repair\EnsureProjectCheckSchema;
use OCA\ProjectCheck\Repair\UninstallDropTables;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class EnsureProjectCheckSchemaTest extends TestCase
{
	/**
	 * Repair always clears the uninstall pass and then runs ensure().
	 * A mock connection cannot build SchemaWrapper — that is covered by the
	 * Docker integration test; here we only assert the uninstall-pass clear
	 * happens before ensure attempts schema work.
	 */
	public function testClearsLegacyUninstallPassBeforeEnsure(): void
	{
		$connection = $this->createMock(IDBConnection::class);
		$connection->method('tableExists')->willReturn(true);

		$config = $this->createMock(IConfig::class);
		$config->expects(self::once())
			->method('deleteAppValue')
			->with(UninstallDropTables::APP_ID, UninstallDropTables::REPAIR_PASS_KEY);
		$config->method('getAppValue')->willReturn('1');

		$output = $this->createMock(IOutput::class);

		$step = new EnsureProjectCheckSchema($connection, $config);

		$this->expectException(RuntimeException::class);
		$step->run($output);
	}
}
