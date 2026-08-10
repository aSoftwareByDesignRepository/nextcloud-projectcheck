<?php

declare(strict_types=1);

/**
 * Stage HTML/API project create|update payloads before merge/validate/write.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Util;

/**
 * Full HTML forms post every field (including empty strings and CSRF tokens).
 * Writing "" into DECIMAL columns aborts the entire UPDATE. This helper:
 * - allowlists writable fields
 * - for full forms: coerces empty decimals to 0.0
 * - for partial API updates: omits empty values so merge keeps stored numbers
 * - never forwards internal request keys
 */
final class ProjectFormPayload
{
	/** @var list<string> */
	public const DECIMAL_FIELDS = ['hourly_rate', 'total_budget', 'available_hours'];

	/** @var list<string> */
	public const ALLOWED_FIELDS = [
		'name',
		'short_description',
		'detailed_description',
		'customer_id',
		'hourly_rate',
		'total_budget',
		'available_hours',
		'category',
		'priority',
		'status',
		'start_date',
		'end_date',
		'tags',
		'project_type',
		'cost_rate_mode',
	];

	/**
	 * @param array<string, mixed> $raw
	 * @param bool $fullForm True for HTML create/update forms; false for partial API payloads
	 * @return array<string, mixed>
	 */
	public static function stage(array $raw, bool $fullForm = true): array
	{
		$staged = [];
		foreach (self::ALLOWED_FIELDS as $field) {
			if (!array_key_exists($field, $raw)) {
				continue;
			}
			$value = $raw[$field];

			if (in_array($field, self::DECIMAL_FIELDS, true)) {
				if (!$fullForm && self::isBlank($value)) {
					// Partial API: omit so merge keeps the stored DECIMAL.
					continue;
				}
				try {
					$staged[$field] = FormDecimal::coerce($value);
				} catch (\InvalidArgumentException $e) {
					throw new \InvalidArgumentException(
						ucfirst(str_replace('_', ' ', $field)) . ' must be a non-negative number',
						0,
						$e
					);
				}
				continue;
			}

			if ($field === 'status') {
				if (is_string($value) && $value !== '') {
					$staged[$field] = $value;
				}
				continue;
			}

			if (!$fullForm && self::isBlank($value)) {
				continue;
			}

			$staged[$field] = $value;
		}

		return $staged;
	}

	private static function isBlank(mixed $value): bool
	{
		if ($value === null) {
			return true;
		}
		if (is_string($value) && trim($value) === '') {
			return true;
		}

		return false;
	}
}
