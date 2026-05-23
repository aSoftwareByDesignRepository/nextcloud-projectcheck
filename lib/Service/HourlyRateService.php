<?php

declare(strict_types=1);

/**
 * Server-authoritative hourly rate resolution for time entries.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Db\ProjectMapper;
use OCA\ProjectCheck\Exception\RateResolutionException;
use OCA\ProjectCheck\Util\CostRateMode;
use OCA\ProjectCheck\Util\Money;
use OCP\IL10N;

class HourlyRateService
{
	/** Maximum allowed delta between client preview and server rate. */
	public const CLIENT_TOLERANCE = 0.009;

	public function __construct(
		private ProjectMapper $projectMapper,
		private ProjectService $projectService,
		private EmployeeHourlyRateService $employeeHourlyRateService,
		private ProjectMemberHourlyRateService $projectMemberHourlyRateService,
		private IL10N $l,
	) {
	}

	/**
	 * Resolve the billing hourly rate for a time entry (frozen at save).
	 *
	 * @throws RateResolutionException
	 */
	public function resolveForTimeEntry(int $projectId, string $userId, \DateTimeInterface $entryDate): float
	{
		$userId = trim($userId);
		if ($userId === '') {
			throw new RateResolutionException($this->l->t('User is required'), 'user_required');
		}

		$project = $this->projectMapper->find($projectId);
		if (!$project instanceof Project) {
			throw new RateResolutionException($this->l->t('Project not found'), 'project_not_found');
		}

		if (!$project->allowsTimeTracking()) {
			throw new RateResolutionException(
				$this->l->t('Time cannot be logged on this project. Only Active and On Hold projects accept new entries.'),
				'project_closed'
			);
		}

		if (!$this->projectService->isActiveTeamMember($projectId, $userId)) {
			throw new RateResolutionException(
				$this->l->t('You must be on the project team to log time. Ask a project manager to add you under Team on the project page.'),
				'not_on_team'
			);
		}

		$entryYmd = $entryDate->format('Y-m-d');
		$mode = CostRateMode::normalize($project->getCostRateMode());

		$rate = match ($mode) {
			CostRateMode::PROJECT => $this->resolveProjectModeRate($project),
			CostRateMode::EMPLOYEE => $this->employeeHourlyRateService->resolveRateForDate($userId, $entryYmd),
			CostRateMode::PROJECT_MEMBER => $this->projectMemberHourlyRateService->resolveRateForProjectMember($projectId, $userId, $entryYmd),
			default => $this->resolveProjectModeRate($project),
		};

		if ($rate <= 0) {
			throw new RateResolutionException(
				$this->messageForUnresolvedMode($mode),
				'rate_unresolved'
			);
		}

		return Money::asFloat(Money::normalize($rate, Money::MONEY_SCALE));
	}

	/**
	 * Preview payload for forms and APIs.
	 *
	 * @return array{hourly_rate: float, cost_rate_mode: string, source: string}
	 * @throws RateResolutionException
	 */
	public function resolvePreview(int $projectId, string $userId, \DateTimeInterface $entryDate): array
	{
		$project = $this->projectMapper->find($projectId);
		if (!$project instanceof Project) {
			throw new RateResolutionException($this->l->t('Project not found'), 'project_not_found');
		}
		$mode = CostRateMode::normalize($project->getCostRateMode());
		$rate = $this->resolveForTimeEntry($projectId, $userId, $entryDate);
		return [
			'hourly_rate' => $rate,
			'cost_rate_mode' => $mode,
			'source' => $this->sourceLabel($mode),
		];
	}

	/**
	 * Reject tampered client rates (preview must match within tolerance).
	 *
	 * @throws RateResolutionException
	 */
	public function assertClientRateMatchesResolved(float $clientRate, float $resolvedRate): void
	{
		if (!is_finite($clientRate) || $clientRate < 0) {
			throw new RateResolutionException(
				$this->l->t('Invalid hourly rate. Refresh the page and try again.'),
				'rate_tamper'
			);
		}
		$delta = abs($clientRate - $resolvedRate);
		if ($delta > self::CLIENT_TOLERANCE) {
			throw new RateResolutionException(
				$this->l->t('The hourly rate does not match the server. Refresh the page and try again.'),
				'rate_tamper'
			);
		}
	}

	private function resolveProjectModeRate(Project $project): float
	{
		return (float) $project->getHourlyRate();
	}

	private function messageForUnresolvedMode(string $mode): string
	{
		return match ($mode) {
			CostRateMode::EMPLOYEE => $this->l->t('No employee hourly rate is effective on this date. Add a rate under Employees with an effective-from date on or before the work date.'),
			CostRateMode::PROJECT_MEMBER => $this->l->t('No project rate is effective for this person on this date. Add a rate on the project team with an effective-from date on or before the work date.'),
			default => $this->l->t('Set a project hourly rate on the project before logging time.'),
		};
	}

	private function sourceLabel(string $mode): string
	{
		return match ($mode) {
			CostRateMode::EMPLOYEE => 'employee_history',
			CostRateMode::PROJECT_MEMBER => 'project_member_history',
			default => 'project_rate',
		};
	}
}
