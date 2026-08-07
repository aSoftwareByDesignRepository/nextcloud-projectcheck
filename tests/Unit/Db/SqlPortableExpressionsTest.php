<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Tests\Unit\Db;

use OCA\ProjectCheck\Db\SqlPortableExpressions;
use OCP\IDBConnection;
use PHPUnit\Framework\TestCase;

class SqlPortableExpressionsTest extends TestCase
{
	public function testYearFromColumnUsesExtractOnPostgres(): void
	{
		$db = $this->createMock(IDBConnection::class);
		$db->method('getDatabaseProvider')->willReturn(IDBConnection::PLATFORM_POSTGRES);
		self::assertSame('EXTRACT(YEAR FROM t.date)', SqlPortableExpressions::yearFromColumn($db, 't.date'));
	}

	public function testYearFromColumnUsesExtractOnMysql(): void
	{
		$db = $this->createMock(IDBConnection::class);
		$db->method('getDatabaseProvider')->willReturn(IDBConnection::PLATFORM_MYSQL);
		self::assertSame('EXTRACT(YEAR FROM t.date)', SqlPortableExpressions::yearFromColumn($db, 't.date'));
	}

	public function testYearFromColumnUsesStrftimeOnSqlite(): void
	{
		$db = $this->createMock(IDBConnection::class);
		$db->method('getDatabaseProvider')->willReturn(IDBConnection::PLATFORM_SQLITE);
		self::assertSame("CAST(strftime('%Y', t.date) AS INTEGER)", SqlPortableExpressions::yearFromColumn($db, 't.date'));
	}

	public function testCoalesceUserDisplayNameHasNoBackticks(): void
	{
		$s = SqlPortableExpressions::coalesceUserDisplayName();
		self::assertStringNotContainsString('`', $s);
		self::assertStringContainsString('COALESCE', $s);
	}
}
