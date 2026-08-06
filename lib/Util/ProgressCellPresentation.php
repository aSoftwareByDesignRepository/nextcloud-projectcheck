<?php

declare(strict_types=1);

/**
 * Presentation rules for project list progress cells.
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Util;

/**
 * Empty 0% progress tracks look like broken UI; only show a bar when there
 * is measurable consumption. Hours text remains independently.
 */
final class ProgressCellPresentation
{
	/**
	 * Whether to render the compact budget progress track.
	 *
	 * @param float|int|string|null $consumptionPercentage 0–100+ budget used
	 * @param float|int|string|null $usedHours hours logged (informational only)
	 */
	public static function shouldShowProgressBar($consumptionPercentage, $usedHours = 0): bool
	{
		$pct = is_numeric($consumptionPercentage) ? (float)$consumptionPercentage : 0.0;
		if (!is_finite($pct) || $pct <= 0.0) {
			return false;
		}
		return true;
	}

	/**
	 * Clamp fill width for inline style / aria (never negative; cap at 100 for bar).
	 *
	 * @param float|int|string|null $consumptionPercentage
	 */
	public static function barWidthPercent($consumptionPercentage): float
	{
		$pct = is_numeric($consumptionPercentage) ? (float)$consumptionPercentage : 0.0;
		if (!is_finite($pct) || $pct <= 0.0) {
			return 0.0;
		}
		return min(100.0, $pct);
	}

	/**
	 * Hours for display; invalid → 0.
	 *
	 * @param float|int|string|null $usedHours
	 */
	public static function normalizedHours($usedHours): float
	{
		$h = is_numeric($usedHours) ? (float)$usedHours : 0.0;
		if (!is_finite($h) || $h < 0.0) {
			return 0.0;
		}
		return $h;
	}
}
