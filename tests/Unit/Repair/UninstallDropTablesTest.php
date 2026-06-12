<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Repair;

use OCA\ProjectCheck\Repair\UninstallDropTables;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\Migration\IOutput;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UninstallDropTablesTest extends TestCase
{
	private IDBConnection&MockObject $connection;
	private IConfig&MockObject $config;
	private IOutput&MockObject $output;

	protected function setUp(): void
	{
		parent::setUp();
		$this->connection = $this->createMock(IDBConnection::class);
		$this->config = $this->createMock(IConfig::class);
		$this->output = $this->createMock(IOutput::class);
	}

	public function testFirstPassPreservesDataOnDisable(): void
	{
		$this->config->expects(self::once())
			->method('getAppValue')
			->with(UninstallDropTables::APP_ID, UninstallDropTables::REPAIR_PASS_KEY, '0')
			->willReturn('0');
		$this->config->expects(self::once())
			->method('setAppValue')
			->with(UninstallDropTables::APP_ID, UninstallDropTables::REPAIR_PASS_KEY, '1');
		$this->connection->expects(self::never())->method('executeStatement');
		$this->config->expects(self::never())->method('deleteAppValues');

		$step = new UninstallDropTables($this->connection, $this->config);
		$step->run($this->output);
	}

	public function testSecondPassDropsTablesAndClearsMetadata(): void
	{
		$this->config->expects(self::once())
			->method('getAppValue')
			->with(UninstallDropTables::APP_ID, UninstallDropTables::REPAIR_PASS_KEY, '0')
			->willReturn('1');
		$this->config->expects(self::never())->method('setAppValue');

		$this->connection->method('getDatabaseProvider')->willReturn(IDBConnection::PLATFORM_SQLITE);
		$this->connection->method('tableExists')->willReturn(false);

		$qb = $this->createMock(\OCP\DB\QueryBuilder\IQueryBuilder::class);
		$expr = $this->createMock(\OCP\DB\QueryBuilder\IExpressionBuilder::class);
		$qb->method('delete')->willReturnSelf();
		$qb->method('where')->willReturnSelf();
		$qb->method('expr')->willReturn($expr);
		$expr->method('eq')->willReturn('app = :app');
		$qb->method('createNamedParameter')->willReturn('projectcheck');
		$qb->expects(self::once())->method('executeStatement')->willReturn(3);
		$this->connection->method('getQueryBuilder')->willReturn($qb);

		$this->config->expects(self::once())
			->method('deleteAppValues')
			->with(UninstallDropTables::APP_ID);

		$step = new UninstallDropTables($this->connection, $this->config);
		$step->run($this->output);
	}
}
