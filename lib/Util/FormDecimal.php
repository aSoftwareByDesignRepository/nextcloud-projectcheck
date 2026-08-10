<?php

declare(strict_types=1);

/**
 * Coerce HTML form decimals for MariaDB/MySQL DECIMAL columns.
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Util;

/**
 * Native number inputs and readonly capacity fields often submit "" when blank.
 * Writing that empty string into a DECIMAL column raises SQLSTATE[22007]
 * (Incorrect decimal value: '') and aborts the whole project update — after
 * side effects like inline customer create have already succeeded.
 *
 * Also accepts common locale decimal separators (comma) so pasted values
 * like "1,50" do not reject an otherwise valid save.
 */
final class FormDecimal
{
	/**
	 * @param mixed $value Raw request value (string|int|float|null)
	 */
	public static function coerce(mixed $value): float
	{
		if ($value === null) {
			return 0.0;
		}
		if (is_string($value)) {
			$value = self::normalizeNumericString($value);
		}
		if ($value === '') {
			return 0.0;
		}
		if (!is_numeric($value)) {
			throw new \InvalidArgumentException('Numeric value required');
		}

		return (float) $value;
	}

	/**
	 * Normalize locale-ish number strings before is_numeric().
	 *
	 * Examples: " 1,50 " → "1.50"; "1.234,56" → "1234.56"; "1,234.56" → "1234.56"
	 */
	public static function normalizeNumericString(string $value): string
	{
		$value = trim($value);
		if ($value === '') {
			return '';
		}
		// Strip spaces / thin spaces used as thousand separators
		$value = str_replace(["\u{00A0}", ' '], '', $value);

		$hasComma = str_contains($value, ',');
		$hasDot = str_contains($value, '.');
		if ($hasComma && $hasDot) {
			// Last separator is the decimal mark; the other is thousands.
			if (strrpos($value, ',') > strrpos($value, '.')) {
				$value = str_replace('.', '', $value);
				$value = str_replace(',', '.', $value);
			} else {
				$value = str_replace(',', '', $value);
			}
		} elseif ($hasComma) {
			$value = str_replace(',', '.', $value);
		}

		return $value;
	}
}
