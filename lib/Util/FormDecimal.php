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
			$value = trim($value);
		}
		if ($value === '') {
			return 0.0;
		}
		if (!is_numeric($value)) {
			throw new \InvalidArgumentException('Numeric value required');
		}

		return (float) $value;
	}
}
