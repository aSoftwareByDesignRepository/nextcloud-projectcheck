<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Db;

use PHPUnit\Framework\TestCase;

/**
 * A5 contract: analytics must not use unrounded SQL float products.
 * Line totals use ROUND(..., 2) (matches Money::mul) or PHP Money::mul.
 */
final class TimeEntryMapperMoneySafeContractTest extends TestCase
{
	public function testMapperHasNoUnroundedHoursTimesRateSqlProduct(): void
	{
		$path = dirname(__DIR__, 3) . '/lib/Db/TimeEntryMapper.php';
		self::assertFileExists($path);
		$source = (string) file_get_contents($path);

		self::assertDoesNotMatchRegularExpression(
			'/SUM\(\s*(?:t\.)?hours\s*\*\s*(?:t\.)?hourly_rate\s*\)/',
			$source,
			'A5: unrounded SUM(hours * hourly_rate) reintroduces float drift'
		);
		self::assertStringContainsString(
			'SUM(ROUND(t.hours * t.hourly_rate, 2))',
			$source,
			'A5: yearly stats must round each line to 2 decimals before summing'
		);
		self::assertStringContainsString(
			'Money::mul($row[\'hours\'] ?? 0, $row[\'hourly_rate\'] ?? 0)',
			$source,
			'A5: project/user totals must use Money::mul'
		);
	}
}
