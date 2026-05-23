<?php

declare(strict_types=1);

/**
 * Capacity (available hours) estimates for projects.
 *
 * Billing always uses frozen time-entry rates ({@see HourlyRateService}).
 * Capacity hours are planning aids only: budget ÷ rate, where the rate depends
 * on pricing mode.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Util;

use OCA\ProjectCheck\Db\Project;

final class ProjectCapacity
{
	public const BASIS_PROJECT_RATE = 'project_rate';
	public const BASIS_PLANNING_RATE = 'planning_rate';
	public const BASIS_UNAVAILABLE = 'unavailable';

	/**
	 * Capacity snapshot for display and APIs.
	 *
	 * @return array{
	 *   basis: string,
	 *   rate: float,
	 *   hours_estimated: bool,
	 *   available_hours: float,
	 *   remaining_hours: float,
	 * }
	 */
	public static function forProject(Project $project, float $usedHours = 0.0): array
	{
		$mode = CostRateMode::normalize($project->getCostRateMode());
		$budget = (float) ($project->getTotalBudget() ?? 0);
		$rate = (float) ($project->getHourlyRate() ?? 0);

		$basis = match ($mode) {
			CostRateMode::PROJECT => self::BASIS_PROJECT_RATE,
			default => $rate > 0 ? self::BASIS_PLANNING_RATE : self::BASIS_UNAVAILABLE,
		};

		if ($budget <= 0 || $rate <= 0) {
			return [
				'basis' => $basis,
				'rate' => $rate,
				'hours_estimated' => false,
				'available_hours' => 0.0,
				'remaining_hours' => 0.0,
			];
		}

		$available = Money::asFloat(Money::div($budget, $rate, Money::HOUR_SCALE), Money::HOUR_SCALE);
		$remaining = Money::asFloat(Money::sub($available, $usedHours, Money::HOUR_SCALE), Money::HOUR_SCALE);
		if ($remaining < 0) {
			$remaining = 0.0;
		}

		return [
			'basis' => $basis,
			'rate' => $rate,
			'hours_estimated' => true,
			'available_hours' => $available,
			'remaining_hours' => $remaining,
		];
	}

	/**
	 * Value persisted on pc_projects.available_hours when budget/rate change.
	 */
	public static function storedAvailableHours(float $budget, float $rate, ?string $costRateMode = null): float
	{
		if ($budget <= 0 || $rate <= 0) {
			return 0.0;
		}

		return Money::asFloat(Money::div($budget, $rate, Money::HOUR_SCALE), Money::HOUR_SCALE);
	}

	/**
	 * Progress percent: hours-based when estimated, otherwise budget consumption.
	 */
	public static function progressPercent(array $budgetInfo, float $usedHours): float
	{
		if (!empty($budgetInfo['hours_estimated']) && ($budgetInfo['available_hours'] ?? 0) > 0) {
			$pct = ($usedHours / (float) $budgetInfo['available_hours']) * 100;

			return min(100.0, max(0.0, round($pct, 1)));
		}

		$consumption = (float) ($budgetInfo['consumption_percentage'] ?? 0);

		return min(100.0, max(0.0, round($consumption, 1)));
	}
}
