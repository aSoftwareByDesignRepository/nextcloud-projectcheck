<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Util;

use OCA\ProjectCheck\Util\SettlementPosture;
use PHPUnit\Framework\TestCase;

class SettlementPostureTest extends TestCase
{
	/**
	 * @dataProvider fromCountersProvider
	 * @param array{open_hours: mixed, invoiced_hours: mixed, paid_hours: mixed} $counters
	 */
	public function testFromCounters(array $counters, string $expected): void
	{
		$this->assertSame($expected, SettlementPosture::fromCounters($counters));
	}

	/**
	 * @return list<array{0: array<string, mixed>, 1: string}>
	 */
	public static function fromCountersProvider(): array
	{
		return [
			[['open_hours' => 0, 'invoiced_hours' => 0, 'paid_hours' => 0], SettlementPosture::NA],
			[['open_hours' => 0, 'invoiced_hours' => 0, 'paid_hours' => 0, 'excluded_hours' => 10], SettlementPosture::NA],
			[['open_hours' => 5, 'invoiced_hours' => 0, 'paid_hours' => 0], SettlementPosture::OPEN],
			[['open_hours' => 0, 'invoiced_hours' => 3, 'paid_hours' => 0], SettlementPosture::AWAITING_PAYMENT],
			[['open_hours' => 0, 'invoiced_hours' => 2, 'paid_hours' => 8], SettlementPosture::AWAITING_PAYMENT],
			[['open_hours' => 0, 'invoiced_hours' => 0, 'paid_hours' => 12], SettlementPosture::PAID],
			[['open_hours' => 2, 'invoiced_hours' => 1, 'paid_hours' => 0], SettlementPosture::PARTIAL],
			[['open_hours' => 2, 'invoiced_hours' => 0, 'paid_hours' => 4], SettlementPosture::PARTIAL],
			[['open_hours' => 1, 'invoiced_hours' => 1, 'paid_hours' => 1], SettlementPosture::PARTIAL],
			// Decimal-string safety (Money path)
			[['open_hours' => '0.00', 'invoiced_hours' => '0.00', 'paid_hours' => '4.50'], SettlementPosture::PAID],
		];
	}

	/**
	 * @dataProvider combineProvider
	 * @param list<string> $postures
	 */
	public function testCombine(array $postures, string $expected): void
	{
		$this->assertSame($expected, SettlementPosture::combine($postures));
	}

	/**
	 * @return list<array{0: list<string>, 1: string}>
	 */
	public static function combineProvider(): array
	{
		return [
			[[], SettlementPosture::NA],
			[[SettlementPosture::NA, SettlementPosture::NA], SettlementPosture::NA],
			[[SettlementPosture::PAID, SettlementPosture::PAID], SettlementPosture::PAID],
			[[SettlementPosture::OPEN, SettlementPosture::OPEN], SettlementPosture::OPEN],
			[[SettlementPosture::AWAITING_PAYMENT, SettlementPosture::PAID], SettlementPosture::AWAITING_PAYMENT],
			[[SettlementPosture::OPEN, SettlementPosture::PAID], SettlementPosture::PARTIAL],
			[[SettlementPosture::PARTIAL, SettlementPosture::OPEN], SettlementPosture::PARTIAL],
			[[SettlementPosture::NA, SettlementPosture::PAID], SettlementPosture::PAID],
			[[SettlementPosture::NA, SettlementPosture::OPEN, SettlementPosture::AWAITING_PAYMENT], SettlementPosture::PARTIAL],
		];
	}

	public function testCombineIgnoresNaWhenOthersExist(): void
	{
		$this->assertSame(
			SettlementPosture::OPEN,
			SettlementPosture::combine([SettlementPosture::NA, SettlementPosture::OPEN, SettlementPosture::OPEN])
		);
	}
}
