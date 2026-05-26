<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Migration;

use OCA\ProjectCheck\Migration\RateHistoryTables;
use PHPUnit\Framework\TestCase;

class RateHistoryTablesTest extends TestCase
{
	public function testTableNamesFitNextcloudOracleBudget(): void
	{
		$prefixLength = 3;

		foreach ([RateHistoryTables::EMPLOYEE, RateHistoryTables::PROJECT_MEMBER] as $logical) {
			$prefixedLen = $prefixLength + strlen($logical);
			self::assertLessThanOrEqual(30, $prefixedLen, 'prefixed table name must be <= 30 chars: ' . $logical);

			$withoutDefaultPk = $prefixedLen - $prefixLength;
			self::assertLessThan(23, $withoutDefaultPk, 'logical table must allow default PK fallback: ' . $logical);
		}
	}

	public function testLegacyRenamesTargetCurrentTables(): void
	{
		foreach (RateHistoryTables::LEGACY_RENAMES as $newName) {
			self::assertContains($newName, [RateHistoryTables::EMPLOYEE, RateHistoryTables::PROJECT_MEMBER]);
		}
	}
}
