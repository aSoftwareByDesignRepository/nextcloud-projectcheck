<?php

declare(strict_types=1);

/**
 * Shared CSV building helpers for the export endpoints.
 *
 * All ProjectCheck exports use the same dialect: every cell quoted, embedded
 * quotes doubled, cells joined with ";" (Excel-friendly for European locales
 * that use the comma as decimal separator), lines terminated with "\n".
 * The UTF-8 BOM is added exactly once by {@see \OCA\ProjectCheck\Service\ListExportService::toDownloadResponse()}
 * when serving CSV as a DataDownloadResponse — never by callers of this helper.
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Util;

final class Csv
{
	private function __construct()
	{
	}

	/**
	 * Neutralize spreadsheet formulas in exported text fields.
	 *
	 * Without this, values starting with =,+,-,@ (or a leading tab/CR that
	 * some spreadsheet parsers skip before formula detection) can execute
	 * formulas when opened in spreadsheet tools (CSV injection).
	 */
	public static function sanitizeField(string $value): string
	{
		if ($value === '') {
			return $value;
		}
		$first = $value[0];
		if ($first === '=' || $first === '+' || $first === '-' || $first === '@' || $first === "\t" || $first === "\r") {
			return "'" . $value;
		}
		return $value;
	}

	/**
	 * Build one CSV line from raw cell values (sanitized, quoted, escaped).
	 *
	 * @param array<int, string|int|float|null> $fields
	 */
	public static function line(array $fields): string
	{
		$cells = [];
		foreach ($fields as $field) {
			$value = self::sanitizeField((string)($field ?? ''));
			$cells[] = '"' . str_replace('"', '""', $value) . '"';
		}
		return implode(';', $cells) . "\n";
	}
}
