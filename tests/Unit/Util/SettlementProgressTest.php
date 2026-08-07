<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Util;

use OCA\ProjectCheck\Util\SettlementProgress;
use PHPUnit\Framework\TestCase;

class SettlementProgressTest extends TestCase
{
	public function testEmptyWhenNoChargeableHours(): void
	{
		$progress = SettlementProgress::fromCounters([
			'open_hours' => 0,
			'invoiced_hours' => 0,
			'paid_hours' => 0,
			'excluded_hours' => 40,
		]);

		$this->assertFalse($progress['has_chargeable']);
		$this->assertNull($progress['paid_percent']);
		$this->assertNull($progress['billed_percent']);
		$this->assertSame(['paid' => 0, 'invoiced' => 0, 'open' => 0], $progress['bar']);
	}

	public function testFullyPaidIsOneHundred(): void
	{
		$progress = SettlementProgress::fromCounters([
			'paid_hours' => 8,
		]);

		$this->assertTrue($progress['has_chargeable']);
		$this->assertSame(100, $progress['paid_percent']);
		$this->assertSame(100, $progress['billed_percent']);
		$this->assertSame(0, $progress['open_percent']);
		$this->assertSame(100, array_sum($progress['bar']));
	}

	public function testBarSegmentsAlwaysSumToOneHundred(): void
	{
		$progress = SettlementProgress::fromCounters([
			'open_hours' => 1,
			'invoiced_hours' => 1,
			'paid_hours' => 1,
		]);

		$this->assertSame(100, array_sum($progress['bar']));
		$this->assertSame(
			$progress['paid_percent'] + $progress['invoiced_percent'],
			$progress['billed_percent']
		);
		// Equal thirds → 34+33+33 or similar via largest remainder
		$this->assertSame(100, $progress['paid_percent'] + $progress['invoiced_percent'] + $progress['open_percent']);
	}

	public function testPartialMixMatchesKnownShares(): void
	{
		// 10 open + 5 invoiced + 2 paid = 17 chargeable
		// paid ≈ 11.76% → 12 after remainder; invoiced ≈ 29.41%; open ≈ 58.82%
		$progress = SettlementProgress::fromCounters([
			'open_hours' => 10,
			'invoiced_hours' => 5,
			'paid_hours' => 2,
		]);

		$this->assertTrue($progress['has_chargeable']);
		$this->assertSame(100, array_sum($progress['bar']));
		$this->assertSame($progress['bar']['paid'], $progress['paid_percent']);
		$this->assertSame($progress['bar']['invoiced'], $progress['invoiced_percent']);
		$this->assertSame($progress['bar']['open'], $progress['open_percent']);
		$this->assertGreaterThan(0, $progress['paid_percent']);
		$this->assertGreaterThan($progress['paid_percent'], $progress['billed_percent']);
		$this->assertLessThan(100, $progress['billed_percent']);
	}

	public function testExcludedHoursDoNotAffectDenominator(): void
	{
		$withExcluded = SettlementProgress::fromCounters([
			'paid_hours' => 4,
			'excluded_hours' => 100,
		]);
		$without = SettlementProgress::fromCounters([
			'paid_hours' => 4,
		]);

		$this->assertSame($without, $withExcluded);
	}

	public function testStringDecimalsFromDbAreAccepted(): void
	{
		$progress = SettlementProgress::fromCounters([
			'open_hours' => '2.50',
			'invoiced_hours' => '2.50',
			'paid_hours' => '5.00',
		]);

		$this->assertSame(100, array_sum($progress['bar']));
		$this->assertSame(50, $progress['paid_percent']);
		$this->assertSame(75, $progress['billed_percent']);
	}
}
