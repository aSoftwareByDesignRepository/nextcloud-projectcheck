<?php

declare(strict_types=1);

/**
 * Derived settlement progress percentages for projects and customers.
 *
 * Pure function over chargeable hour counters (open + invoiced + paid).
 * Excluded hours are never part of the denominator. Percentages are null when
 * there is nothing chargeable (posture n_a) — callers must not treat null as 0%.
 *
 * Stacked-bar segment widths use the largest-remainder method so the integer
 * parts always sum to 100 when chargeable &gt; 0 (no 99%/101% bar bugs).
 * `billed_percent` is defined as paid + invoiced bar shares so headlines and
 * the bar stay consistent.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Util;

final class SettlementProgress
{
	/**
	 * @param array{
	 *   open_hours?: mixed,
	 *   invoiced_hours?: mixed,
	 *   paid_hours?: mixed
	 * } $counters
	 * @return array{
	 *   has_chargeable: bool,
	 *   paid_percent: int|null,
	 *   invoiced_percent: int|null,
	 *   billed_percent: int|null,
	 *   open_percent: int|null,
	 *   bar: array{paid: int, invoiced: int, open: int}
	 * }
	 */
	public static function fromCounters(array $counters): array
	{
		$open = Money::normalize($counters['open_hours'] ?? 0, Money::HOUR_SCALE);
		$invoiced = Money::normalize($counters['invoiced_hours'] ?? 0, Money::HOUR_SCALE);
		$paid = Money::normalize($counters['paid_hours'] ?? 0, Money::HOUR_SCALE);

		$chargeable = Money::add(Money::add($open, $invoiced, Money::HOUR_SCALE), $paid, Money::HOUR_SCALE);
		if (Money::isZero($chargeable)) {
			return self::empty();
		}

		$bar = self::largestRemainderPercents([
			'paid' => $paid,
			'invoiced' => $invoiced,
			'open' => $open,
		], $chargeable);

		return [
			'has_chargeable' => true,
			'paid_percent' => $bar['paid'],
			'invoiced_percent' => $bar['invoiced'],
			'billed_percent' => $bar['paid'] + $bar['invoiced'],
			'open_percent' => $bar['open'],
			'bar' => $bar,
		];
	}

	/**
	 * @return array{
	 *   has_chargeable: bool,
	 *   paid_percent: null,
	 *   invoiced_percent: null,
	 *   billed_percent: null,
	 *   open_percent: null,
	 *   bar: array{paid: 0, invoiced: 0, open: 0}
	 * }
	 */
	public static function empty(): array
	{
		return [
			'has_chargeable' => false,
			'paid_percent' => null,
			'invoiced_percent' => null,
			'billed_percent' => null,
			'open_percent' => null,
			'bar' => ['paid' => 0, 'invoiced' => 0, 'open' => 0],
		];
	}

	/**
	 * Largest-remainder apportionment so segment integers always sum to 100.
	 *
	 * @param array{paid: string, invoiced: string, open: string} $parts
	 * @return array{paid: int, invoiced: int, open: int}
	 */
	private static function largestRemainderPercents(array $parts, string $total): array
	{
		$keys = ['paid', 'invoiced', 'open'];
		/** @var array<string, array{floor: int, frac: string}> $meta */
		$meta = [];
		$floorSum = 0;

		foreach ($keys as $key) {
			$ratio = Money::div($parts[$key], $total, Money::INTERNAL_SCALE);
			$exact = Money::mul($ratio, '100', Money::INTERNAL_SCALE);
			$floor = self::floorNonNegative($exact);
			$frac = Money::sub($exact, (string) $floor, Money::INTERNAL_SCALE);
			if (Money::compare($frac, '0', Money::INTERNAL_SCALE) < 0) {
				$frac = Money::normalize('0', Money::INTERNAL_SCALE);
			}
			$meta[$key] = ['floor' => $floor, 'frac' => $frac];
			$floorSum += $floor;
		}

		$remainder = 100 - $floorSum;
		while ($remainder < 0) {
			$maxKey = null;
			$maxFloor = -1;
			foreach ($keys as $key) {
				if ($meta[$key]['floor'] > $maxFloor) {
					$maxFloor = $meta[$key]['floor'];
					$maxKey = $key;
				}
			}
			if ($maxKey === null || $maxFloor <= 0) {
				break;
			}
			$meta[$maxKey]['floor']--;
			$remainder++;
		}

		if ($remainder > 0) {
			uasort(
				$meta,
				static function (array $a, array $b): int {
					return Money::compare($b['frac'], $a['frac'], Money::INTERNAL_SCALE);
				}
			);
			foreach (array_keys($meta) as $key) {
				if ($remainder <= 0) {
					break;
				}
				$meta[$key]['floor']++;
				$remainder--;
			}
		}

		return [
			'paid' => $meta['paid']['floor'],
			'invoiced' => $meta['invoiced']['floor'],
			'open' => $meta['open']['floor'],
		];
	}

	/**
	 * Truncate a non-negative decimal string toward zero (true floor).
	 * Do not use Money::normalize(..., 0) — that rounds half-away-from-zero.
	 */
	private static function floorNonNegative(string $value): int
	{
		$normalized = Money::normalize($value, Money::INTERNAL_SCALE);
		if (Money::compare($normalized, '0', Money::INTERNAL_SCALE) <= 0) {
			return 0;
		}
		$dot = strpos($normalized, '.');
		$intPart = $dot === false ? $normalized : substr($normalized, 0, $dot);
		return max(0, (int) $intPart);
	}
}
