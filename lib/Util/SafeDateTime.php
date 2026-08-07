<?php

declare(strict_types=1);

/**
 * Safe DateTime construction for DB rows and untyped input (PHP 8+).
 *
 * @copyright Copyright (c) 2025, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Util;

final class SafeDateTime
{
	/**
	 * Nullable column or optional value: returns null if empty or unparseable.
	 *
	 * @param mixed $value
	 */
	public static function fromOptional($value): ?\DateTime
	{
		if ($value === null || $value === '') {
			return null;
		}
		if ($value instanceof \DateTimeInterface) {
			return \DateTime::createFromInterface($value);
		}
		if (\is_bool($value)) {
			return null;
		}
		$s = trim((string) $value);
		if ($s === '') {
			return null;
		}
		try {
			return new \DateTime($s);
		} catch (\Throwable $e) {
			return null;
		}
	}

	/**
	 * Required NOT NULL column: throws if empty or unparseable.
	 *
	 * @param mixed $value
	 */
	public static function fromRequired($value, string $context = 'date'): \DateTime
	{
		$dt = self::fromOptional($value);
		if ($dt === null) {
			throw new \InvalidArgumentException($context . ' is missing or invalid');
		}

		return $dt;
	}
}
