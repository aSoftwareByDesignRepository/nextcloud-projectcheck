<?php

declare(strict_types=1);

/**
 * ProjectCalculator utility for the projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Util;

use OCA\ProjectCheck\Util\Money;

/**
 * Class ProjectCalculator
 *
 * @package OCA\ProjectControl\Util
 */
class ProjectCalculator
{

	/**
	 * Calculate available hours based on budget and hourly rate.
	 *
	 * Uses fixed-point math via {@see Money} to eliminate float drift on
	 * large budgets (audit reference A5).
	 *
	 * @param float $budget
	 * @param float $hourlyRate
	 * @return float
	 */
	public function calculateAvailableHours(float $budget, float $hourlyRate): float
	{
		if ($hourlyRate <= 0) {
			return 0;
		}

		return Money::asFloat(Money::div($budget, $hourlyRate, Money::HOUR_SCALE), Money::HOUR_SCALE);
	}

	/**
	 * Calculate budget consumption percentage with fixed-point precision.
	 *
	 * @param float $usedHours
	 * @param float $totalBudget
	 * @param float $hourlyRate
	 * @return float
	 */
	public function calculateBudgetConsumption(float $usedHours, float $totalBudget, float $hourlyRate): float
	{
		if ($totalBudget <= 0) {
			return 0;
		}

		$usedBudget = Money::mul($usedHours, $hourlyRate, Money::INTERNAL_SCALE);
		return Money::asFloat(Money::percentage($usedBudget, $totalBudget, Money::MONEY_SCALE), Money::MONEY_SCALE);
	}

	/**
	 * Calculate budget consumption using actual time entries
	 *
	 * @param int $projectId
	 * @param float $totalBudget
	 * @param \OCA\ProjectControl\Db\TimeEntryMapper $timeEntryMapper
	 * @return float
	 */
	public function calculateBudgetConsumptionFromTimeEntries(int $projectId, float $totalBudget, \OCA\ProjectControl\Db\TimeEntryMapper $timeEntryMapper): float
	{
		if ($totalBudget <= 0) {
			return 0;
		}

		$totalCost = $timeEntryMapper->getTotalCostForProject($projectId);
		return round(($totalCost / $totalBudget) * 100, 2);
	}

	/**
	 * Get budget warning level based on consumption percentage
	 *
	 * @param float $consumptionPercentage
	 * @return string
	 */
	public function getBudgetWarningLevel(float $consumptionPercentage): string
	{
		if ($consumptionPercentage >= 100) {
			return 'critical';
		} elseif ($consumptionPercentage >= 90) {
			return 'warning';
		} elseif ($consumptionPercentage >= 80) {
			return 'notice';
		}

		return 'none';
	}

	/**
	 * Calculate project cost for hours worked.
	 *
	 * Uses fixed-point math; equivalent to `round($hours * $rate, 2)` but
	 * without the IEEE-754 drift that produces "0.30000000000000004" cents
	 * on user-visible totals.
	 *
	 * @param float $hours
	 * @param float $hourlyRate
	 * @return float
	 */
	public function calculateProjectCost(float $hours, float $hourlyRate): float
	{
		return Money::asFloat(Money::mul($hours, $hourlyRate, Money::MONEY_SCALE), Money::MONEY_SCALE);
	}

	/**
	 * Check if project is over budget
	 *
	 * @param float $consumptionPercentage
	 * @return bool
	 */
	public function isOverBudget(float $consumptionPercentage): bool
	{
		return $consumptionPercentage > 100;
	}

	/**
	 * Calculate remaining budget
	 *
	 * @param float $totalBudget
	 * @param float $usedHours
	 * @param float $hourlyRate
	 * @return float
	 */
	public function calculateRemainingBudget(float $totalBudget, float $usedHours, float $hourlyRate): float
	{
		$usedBudget = $this->calculateProjectCost($usedHours, $hourlyRate);
		$remaining = Money::asFloat(Money::sub($totalBudget, $usedBudget, Money::MONEY_SCALE), Money::MONEY_SCALE);
		return max(0.0, $remaining);
	}

	/**
	 * Calculate remaining hours
	 *
	 * @param float $totalHours
	 * @param float $usedHours
	 * @return float
	 */
	public function calculateRemainingHours(float $totalHours, float $usedHours): float
	{
		return max(0, $totalHours - $usedHours);
	}

	/**
	 * Calculate project efficiency (planned vs actual hours)
	 *
	 * @param float $plannedHours
	 * @param float $actualHours
	 * @return float
	 */
	public function calculateEfficiency(float $plannedHours, float $actualHours): float
	{
		if ($plannedHours <= 0) {
			return 0;
		}

		return round(($plannedHours / $actualHours) * 100, 2);
	}

	/**
	 * Calculate average hourly rate for team
	 *
	 * @param array $hourlyRates
	 * @return float
	 */
	public function calculateAverageHourlyRate(array $hourlyRates): float
	{
		if (empty($hourlyRates)) {
			return 0;
		}

		$total = array_sum($hourlyRates);
		return round($total / count($hourlyRates), 2);
	}

	/**
	 * Calculate total project cost with team members
	 *
	 * @param array $memberHours Array of [userId => hours]
	 * @param array $memberRates Array of [userId => hourlyRate]
	 * @return float
	 */
	public function calculateTotalProjectCost(array $memberHours, array $memberRates): float
	{
		$totalCost = 0;

		foreach ($memberHours as $userId => $hours) {
			$rate = $memberRates[$userId] ?? 0;
			$totalCost += $this->calculateProjectCost($hours, $rate);
		}

		return $totalCost;
	}

	/**
	 * Calculate project profitability
	 *
	 * @param float $totalBudget
	 * @param float $totalCost
	 * @return float
	 */
	public function calculateProfitability(float $totalBudget, float $totalCost): float
	{
		if ($totalBudget <= 0) {
			return 0;
		}

		$profit = $totalBudget - $totalCost;
		return round(($profit / $totalBudget) * 100, 2);
	}

	/**
	 * Calculate estimated completion date based on current progress
	 *
	 * @param \DateTime $startDate
	 * @param float $plannedHours
	 * @param float $completedHours
	 * @param float $averageHoursPerDay
	 * @return \DateTime|null
	 */
	public function calculateEstimatedCompletionDate(
		\DateTime $startDate,
		float $plannedHours,
		float $completedHours,
		float $averageHoursPerDay = 8
	): ?\DateTime {
		if ($averageHoursPerDay <= 0 || $plannedHours <= 0) {
			return null;
		}

		$remainingHours = $plannedHours - $completedHours;
		if ($remainingHours <= 0) {
			return $startDate;
		}

		$remainingDays = ceil($remainingHours / $averageHoursPerDay);
		$completionDate = clone $startDate;
		$completionDate->add(new \DateInterval("P{$remainingDays}D"));

		return $completionDate;
	}

	/**
	 * Calculate project burn rate (hours per day)
	 *
	 * @param float $totalHours
	 * @param \DateTime $startDate
	 * @param \DateTime|null $endDate
	 * @return float
	 */
	public function calculateBurnRate(float $totalHours, \DateTime $startDate, ?\DateTime $endDate = null): float
	{
		$endDate = $endDate ?? new \DateTime();
		$days = max(1, $startDate->diff($endDate)->days);

		return round($totalHours / $days, 2);
	}

	/**
	 * Validate budget and rate combination
	 *
	 * @param float $budget
	 * @param float $hourlyRate
	 * @return array Array with 'valid' boolean and 'message' string
	 */
	public function validateBudgetAndRate(float $budget, float $hourlyRate): array
	{
		if ($budget <= 0) {
			return ['valid' => false, 'message' => 'Budget must be greater than zero'];
		}

		if ($hourlyRate <= 0) {
			return ['valid' => false, 'message' => 'Hourly rate must be greater than zero'];
		}

		if ($hourlyRate > 1000) {
			return ['valid' => false, 'message' => 'Hourly rate seems unusually high'];
		}

		$availableHours = $this->calculateAvailableHours($budget, $hourlyRate);
		if ($availableHours < 0.5) {
			return ['valid' => false, 'message' => 'Budget too low for the specified hourly rate'];
		}

		return ['valid' => true, 'message' => 'Valid budget and rate combination'];
	}

	/**
	 * Get total hours for a project from time entries
	 *
	 * @param int $projectId
	 * @param \OCA\ProjectControl\Db\TimeEntryMapper $timeEntryMapper
	 * @return float
	 */
	public function getTotalHoursForProject(int $projectId, \OCA\ProjectControl\Db\TimeEntryMapper $timeEntryMapper): float {
		return $timeEntryMapper->getTotalHoursForProject($projectId);
	}

	/**
	 * Get total cost for a project from time entries
	 *
	 * @param int $projectId
	 * @param \OCA\ProjectControl\Db\TimeEntryMapper $timeEntryMapper
	 * @return float
	 */
	public function getTotalCostForProject(int $projectId, \OCA\ProjectControl\Db\TimeEntryMapper $timeEntryMapper): float {
		return $timeEntryMapper->getTotalCostForProject($projectId);
	}

	/**
	 * Calculate remaining hours for a project
	 *
	 * @param int $projectId
	 * @param float $totalBudget
	 * @param float $hourlyRate
	 * @param \OCA\ProjectControl\Db\TimeEntryMapper $timeEntryMapper
	 * @return float
	 */
	public function calculateRemainingHoursForProject(int $projectId, float $totalBudget, float $hourlyRate, \OCA\ProjectControl\Db\TimeEntryMapper $timeEntryMapper): float {
		$totalHours = $this->calculateAvailableHours($totalBudget, $hourlyRate);
		$usedHours = $this->getTotalHoursForProject($projectId, $timeEntryMapper);
		return $this->calculateRemainingHours($totalHours, $usedHours);
	}

	/**
	 * Calculate remaining budget for a project
	 *
	 * @param int $projectId
	 * @param float $totalBudget
	 * @param \OCA\ProjectControl\Db\TimeEntryMapper $timeEntryMapper
	 * @return float
	 */
	public function calculateRemainingBudgetForProject(int $projectId, float $totalBudget, \OCA\ProjectControl\Db\TimeEntryMapper $timeEntryMapper): float {
		$usedCost = $this->getTotalCostForProject($projectId, $timeEntryMapper);
		return max(0, $totalBudget - $usedCost);
	}

	/**
	 * Get time entry summary for a project
	 *
	 * @param int $projectId
	 * @param \OCA\ProjectControl\Db\TimeEntryMapper $timeEntryMapper
	 * @return array
	 */
	public function getTimeEntrySummaryForProject(int $projectId, \OCA\ProjectControl\Db\TimeEntryMapper $timeEntryMapper): array {
		$totalHours = $this->getTotalHoursForProject($projectId, $timeEntryMapper);
		$totalCost = $this->getTotalCostForProject($projectId, $timeEntryMapper);
		$timeEntries = $timeEntryMapper->findByProject($projectId);
		
		return [
			'total_hours' => $totalHours,
			'total_cost' => $totalCost,
			'entry_count' => count($timeEntries),
			'average_hours_per_entry' => count($timeEntries) > 0 ? $totalHours / count($timeEntries) : 0,
			'last_entry_date' => count($timeEntries) > 0 ? end($timeEntries)->getDate() : null
		];
	}
}
