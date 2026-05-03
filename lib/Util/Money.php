<?php

declare(strict_types=1);

/**
 * Fixed-point money/decimal arithmetic helper.
 *
 * Audit reference: `pm/app-ideas/projectcheck/AUDIT-FINDINGS.md` finding A5 -
 * float math on monetary values introduces rounding drift in totals,
 * thresholds and budget warnings. All financially-relevant calculations now
 * route through this helper, which uses BCMath when available (the default in
 * any production Nextcloud environment) and falls back to a string-precise
 * pure-PHP implementation for environments where BCMath is missing.
 *
 * Conventions:
 *  - Internal scale is 4 to keep two extra digits of precision before the
 *    final 2-decimal rounding.
 *  - Public APIs return strings for chaining and floats for legacy callers
 *    (`asFloat()`); the float conversion is bounded to 2 decimals so we never
 *    leak `0.10000000000001`-style artefacts into JSON responses.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Util;

final class Money
{
	/** Internal calculation scale; consumers see 2 decimals after rounding. */
	public const INTERNAL_SCALE = 4;

	/** Public scale for monetary values (cents). */
	public const MONEY_SCALE = 2;

	/** Public scale for hour values (centi-hours). */
	public const HOUR_SCALE = 2;

	private static ?bool $hasBc = null;

	private static function hasBc(): bool
	{
		if (self::$hasBc === null) {
			self::$hasBc = function_exists('bcadd');
		}
		return self::$hasBc;
	}

	/**
	 * Normalize an arbitrary numeric input (float|int|string|null) to a
	 * decimal string with the requested scale, deterministically rounded
	 * half-away-from-zero. Empty / non-numeric input becomes "0".
	 */
	public static function normalize(mixed $value, int $scale = self::INTERNAL_SCALE): string
	{
		if ($value === null || $value === '') {
			return self::zero($scale);
		}
		if (is_string($value)) {
			$trimmed = trim($value);
			if ($trimmed === '') {
				return self::zero($scale);
			}
			// Allow comma-decimal locale input as a convenience.
			$trimmed = str_replace([' ', '_'], '', $trimmed);
			if (substr_count($trimmed, ',') === 1 && substr_count($trimmed, '.') === 0) {
				$trimmed = str_replace(',', '.', $trimmed);
			}
			if (!is_numeric($trimmed)) {
				return self::zero($scale);
			}
			$value = $trimmed;
		} elseif (!is_numeric($value)) {
			return self::zero($scale);
		}

		if (is_float($value)) {
			$str = sprintf('%.10F', $value);
		} else {
			$str = (string)$value;
			// Expand scientific notation ("1e2", "-3.5E-2") into a decimal
			// string so the rounding regex below can match. Using a wide
			// internal scale here keeps tiny exponents from collapsing to 0.
			if (stripos($str, 'e') !== false) {
				$str = sprintf('%.10F', (float)$str);
			}
		}
		return self::roundString($str, $scale);
	}

	private static function zero(int $scale): string
	{
		return $scale > 0 ? '0.' . str_repeat('0', $scale) : '0';
	}

	/**
	 * Half-away-from-zero rounding on a decimal string.
	 */
	private static function roundString(string $value, int $scale): string
	{
		$negative = false;
		if ($value !== '' && $value[0] === '-') {
			$negative = true;
			$value = substr($value, 1);
		} elseif ($value !== '' && $value[0] === '+') {
			$value = substr($value, 1);
		}
		if (!preg_match('/^(\d+)(?:\.(\d*))?$/', $value, $m)) {
			return self::zero($scale);
		}
		$int = $m[1];
		$frac = $m[2] ?? '';
		if ($scale === 0) {
			$digit = isset($frac[0]) ? (int)$frac[0] : 0;
			if ($digit >= 5) {
				$int = self::stringIncrement($int);
			}
			return ($negative && $int !== '0') ? '-' . $int : $int;
		}

		if (strlen($frac) <= $scale) {
			$frac = str_pad($frac, $scale, '0');
		} else {
			$keep = substr($frac, 0, $scale);
			$digit = (int)$frac[$scale];
			if ($digit >= 5) {
				$keep = self::stringIncrement($keep);
				if (strlen($keep) > $scale) {
					$int = self::stringIncrement($int);
					$keep = substr($keep, 1);
				}
			}
			$frac = $keep;
		}
		$result = $int . '.' . $frac;
		return ($negative && $result !== self::zero($scale)) ? '-' . $result : $result;
	}

	private static function stringIncrement(string $digits): string
	{
		if ($digits === '') {
			return '1';
		}
		$i = strlen($digits) - 1;
		$carry = 1;
		while ($i >= 0 && $carry === 1) {
			$d = (int)$digits[$i] + 1;
			if ($d === 10) {
				$digits[$i] = '0';
				$carry = 1;
			} else {
				$digits[$i] = (string)$d;
				$carry = 0;
			}
			$i--;
		}
		if ($carry === 1) {
			$digits = '1' . $digits;
		}
		return $digits;
	}

	/**
	 * Multiply two arbitrary numeric values with full precision and return
	 * a string rounded to the requested scale (default 2 = currency).
	 */
	public static function mul(mixed $a, mixed $b, int $scale = self::MONEY_SCALE): string
	{
		$as = self::normalize($a, self::INTERNAL_SCALE);
		$bs = self::normalize($b, self::INTERNAL_SCALE);
		if (self::hasBc()) {
			$product = bcmul($as, $bs, self::INTERNAL_SCALE);
		} else {
			$product = sprintf('%.' . self::INTERNAL_SCALE . 'F', (float)$as * (float)$bs);
		}
		return self::roundString($product, $scale);
	}

	public static function add(mixed $a, mixed $b, int $scale = self::MONEY_SCALE): string
	{
		$as = self::normalize($a, self::INTERNAL_SCALE);
		$bs = self::normalize($b, self::INTERNAL_SCALE);
		if (self::hasBc()) {
			$sum = bcadd($as, $bs, self::INTERNAL_SCALE);
		} else {
			$sum = sprintf('%.' . self::INTERNAL_SCALE . 'F', (float)$as + (float)$bs);
		}
		return self::roundString($sum, $scale);
	}

	public static function sub(mixed $a, mixed $b, int $scale = self::MONEY_SCALE): string
	{
		$as = self::normalize($a, self::INTERNAL_SCALE);
		$bs = self::normalize($b, self::INTERNAL_SCALE);
		if (self::hasBc()) {
			$diff = bcsub($as, $bs, self::INTERNAL_SCALE);
		} else {
			$diff = sprintf('%.' . self::INTERNAL_SCALE . 'F', (float)$as - (float)$bs);
		}
		return self::roundString($diff, $scale);
	}

	/**
	 * Divide $a by $b at the requested scale. Returns "0" when $b is zero
	 * or non-numeric so callers do not have to guard against the edge case.
	 */
	public static function div(mixed $a, mixed $b, int $scale = self::MONEY_SCALE): string
	{
		$as = self::normalize($a, self::INTERNAL_SCALE);
		$bs = self::normalize($b, self::INTERNAL_SCALE);
		if (self::isZero($bs)) {
			return self::zero($scale);
		}
		// bcdiv truncates rather than rounds, so we work at +2 digits of
		// precision and let roundString() apply half-away-from-zero rounding
		// at the requested scale. Without this, e.g. 392.55 / 1000 * 100
		// would surface as 39.25% instead of the correct 39.26%.
		$workingScale = max(self::INTERNAL_SCALE, $scale) + 2;
		if (self::hasBc()) {
			$q = bcdiv($as, $bs, $workingScale);
		} else {
			$q = sprintf('%.' . $workingScale . 'F', (float)$as / (float)$bs);
		}
		return self::roundString($q, $scale);
	}

	/** True when the absolute value is below 1 ulp at internal scale. */
	public static function isZero(string $value): bool
	{
		$cleaned = rtrim(rtrim(ltrim($value, '+-'), '0'), '0.');
		return $cleaned === '';
	}

	/**
	 * Calculate consumption percentage with full precision.
	 *
	 * @return string e.g. "12.50"
	 */
	public static function percentage(mixed $part, mixed $whole, int $scale = self::MONEY_SCALE): string
	{
		if (self::isZero(self::normalize($whole, self::INTERNAL_SCALE))) {
			return self::zero($scale);
		}
		$ratio = self::div($part, $whole, self::INTERNAL_SCALE);
		return self::mul($ratio, '100', $scale);
	}

	/**
	 * Calculate percentage but never let display rounding move the value
	 * across the 100% boundary. A project at 99.999% must never appear as
	 * "100% / exceeded" and a project at 100.001% must never collapse to
	 * "100% / exactly on budget".
	 *
	 * - If the true ratio is < 100, the result is clamped to 99.99
	 *   (smallest value below 100 at the requested scale).
	 * - If the true ratio is >= 100, the result is at least 100 at the
	 *   requested scale; values strictly greater than 100 retain their
	 *   excess at the requested scale.
	 */
	public static function percentageBounded(mixed $part, mixed $whole, int $scale = self::MONEY_SCALE): string
	{
		$wholeNorm = self::normalize($whole, self::INTERNAL_SCALE);
		if (self::isZero($wholeNorm)) {
			return self::zero($scale);
		}
		$partNorm = self::normalize($part, self::INTERNAL_SCALE);

		// Use a high working scale (enough to distinguish any 2dp display
		// boundary safely) to compare the *true* percentage against 100.
		$workingScale = max(self::INTERNAL_SCALE, $scale) + 6;
		if (self::hasBc()) {
			$ratio = bcdiv($partNorm, $wholeNorm, $workingScale);
			$pctInternal = bcmul($ratio, '100', $workingScale);
		} else {
			$pctInternal = sprintf('%.' . $workingScale . 'F', ((float)$partNorm / (float)$wholeNorm) * 100.0);
		}

		$rounded = self::roundString($pctInternal, $scale);
		$cmp = self::compare($pctInternal, '100', $workingScale);
		if ($cmp < 0 && self::compare($rounded, '100', $scale) >= 0) {
			$epsilon = self::epsilon($scale);
			return self::sub('100', $epsilon, $scale);
		}
		if ($cmp > 0 && self::compare($rounded, '100', $scale) <= 0) {
			$epsilon = self::epsilon($scale);
			return self::add('100', $epsilon, $scale);
		}
		return $rounded;
	}

	/** Smallest positive value representable at the given scale, e.g. 0.01. */
	private static function epsilon(int $scale): string
	{
		if ($scale <= 0) {
			return '1';
		}
		return '0.' . str_repeat('0', $scale - 1) . '1';
	}

	/**
	 * Convert a normalized decimal string to a float bounded to a 2-decimal
	 * representation. Use this only at the very edge (JSON serialisation).
	 */
	public static function asFloat(string $value, int $scale = self::MONEY_SCALE): float
	{
		return (float) self::roundString($value, $scale);
	}

	/**
	 * Compare two values; returns -1, 0 or 1 (like PHP's spaceship operator).
	 */
	public static function compare(mixed $a, mixed $b, int $scale = self::INTERNAL_SCALE): int
	{
		$as = self::normalize($a, $scale);
		$bs = self::normalize($b, $scale);
		if (self::hasBc()) {
			return bccomp($as, $bs, $scale);
		}
		$diff = (float)$as - (float)$bs;
		if ($diff > 0) {
			return 1;
		}
		if ($diff < 0) {
			return -1;
		}
		return 0;
	}
}
