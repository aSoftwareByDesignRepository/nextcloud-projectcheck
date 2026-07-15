<?php

declare(strict_types=1);

/**
 * TimeEntry service for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

use OCA\ProjectCheck\Db\TimeEntry;
use OCA\ProjectCheck\Db\TimeEntryMapper;
use OCA\ProjectCheck\Db\ProjectMapper;
use OCA\ProjectCheck\Exception\PermissionDeniedException;
use OCA\ProjectCheck\Exception\RateResolutionException;
use OCA\ProjectCheck\Exception\TimeEntryNotFoundException;
use OCA\ProjectCheck\Exception\ValidationException;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IL10N;

/**
 * TimeEntry service for time tracking business logic
 */
class TimeEntryService
{
	/** @var TimeEntryMapper */
	private $timeEntryMapper;

	/** @var ProjectMapper */
	private $projectMapper;

	/** @var ProjectService */
	private $projectService;

	/** @var HourlyRateService */
	private $hourlyRateService;

	/** @var IL10N */
	private $l;

	public function __construct(
		TimeEntryMapper $timeEntryMapper,
		ProjectMapper $projectMapper,
		ProjectService $projectService,
		HourlyRateService $hourlyRateService,
		IL10N $l
	) {
		$this->timeEntryMapper = $timeEntryMapper;
		$this->projectMapper = $projectMapper;
		$this->projectService = $projectService;
		$this->hourlyRateService = $hourlyRateService;
		$this->l = $l;
	}

	/**
	 * Create a new time entry
	 *
	 * @param array $data Time entry data
	 * @param string $userId User ID
	 * @return TimeEntry
	 * @throws \Exception
	 */
	public function createTimeEntry($data, $userId)
	{
		$pid = (int) ($data['project_id'] ?? 0);
		$project = $this->projectMapper->find($pid);
		if (!$project) {
			throw new ValidationException([], $this->l->t('Project not found'));
		}
		if (!$project->allowsTimeTracking()) {
			throw new ValidationException([], $this->l->t('Time cannot be logged on this project. Only Active and On Hold projects accept new entries; reactivate an archived project if needed.'));
		}
		if (!$this->projectService->canUserAccessProject($userId, $pid)) {
			throw new PermissionDeniedException('create', 'time entry', $this->l->t('Access denied'));
		}

		$parsedDate = $this->parseTimeEntryDateString($data['date'] ?? null);
		if ($parsedDate === null) {
			throw new ValidationException([], $this->l->t('Date is required'));
		}

		$resolvedRate = $this->resolveAndAssertClientRate(
			$pid,
			$userId,
			$parsedDate,
			$data['hourly_rate'] ?? null
		);

		$timeEntry = new TimeEntry();
		$timeEntry->setProjectId($pid);
		$timeEntry->setUserId($userId);
		$timeEntry->setDate($parsedDate);
		$timeEntry->setHours((float)$data['hours']);
		$timeEntry->setDescription($data['description'] ?? '');
		$timeEntry->setHourlyRate($resolvedRate);
		$timeEntry->setCreatedAt(new \DateTime());
		$timeEntry->setUpdatedAt(new \DateTime());

		return $this->timeEntryMapper->insert($timeEntry);
	}

	/**
	 * Get a time entry by ID
	 *
	 * @param int $id Time entry ID
	 * @return TimeEntry|null
	 */
	public function getTimeEntry($id)
	{
		// Defensive cast: route parameters may arrive as numeric strings and the
		// mapper signature is strictly typed (strict_types=1 at this call site).
		if (!is_numeric($id)) {
			return null;
		}
		try {
			return $this->timeEntryMapper->find((int) $id);
		} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
			return null;
		}
	}

	/**
	 * Get all time entries for a user
	 *
	 * @param string $userId User ID
	 * @return TimeEntry[]
	 */
	public function getTimeEntriesByUser($userId)
	{
		return $this->timeEntryMapper->findByUser($userId);
	}

	/**
	 * Get all time entries in the system
	 *
	 * @return TimeEntry[]
	 */
	public function getAllTimeEntries()
	{
		return $this->timeEntryMapper->findAll();
	}

	/**
	 * Get time entries for a project
	 *
	 * @param int $projectId Project ID
	 * @return TimeEntry[]
	 */
	public function getTimeEntriesByProject($projectId)
	{
		return $this->timeEntryMapper->findByProject($projectId);
	}

	/**
	 * Get time entries with project information
	 *
	 * @param array $filters Filters to apply
	 * @return array
	 */
	public function getTimeEntriesWithProjectInfo($filters = [])
	{
		return $this->timeEntryMapper->findWithProjectInfo($filters);
	}

	/**
	 * Count time entries with optional filters (same filter keys as findWithProjectInfo)
	 *
	 * @param array $filters
	 * @return int
	 */
	public function countTimeEntries(array $filters = []): int
	{
		$countFilters = $filters;
		unset($countFilters['limit'], $countFilters['offset']);
		return $this->timeEntryMapper->count($countFilters);
	}

	/**
	 * Sum hours for entries matching list filters (ignores pagination).
	 */
	public function sumTimeEntriesHours(array $filters = []): float
	{
		$sumFilters = $filters;
		unset($sumFilters['limit'], $sumFilters['offset']);
		return $this->timeEntryMapper->sumHours($sumFilters);
	}

	/**
	 * Update a time entry
	 *
	 * Permission model (kept consistent with {@see deleteTimeEntry}):
	 *  - Only the entry owner may update it.
	 *  - Editing an entry *in place* (project unchanged) only requires ownership.
	 *    Project status does not gate corrections to historical entries, so owners
	 *    can still fix hours/description after a project was Completed/Archived or
	 *    after their team membership ended — the same situations in which they may
	 *    already delete the entry outright.
	 *  - *Moving* an entry to a different project is treated like logging new time
	 *    there: the target must be Active/On Hold and the user must be allowed to
	 *    log time on it ({@see ProjectService::canUserAddTimeEntryForProject}).
	 *
	 * Rate model ("frozen at save"): the stored hourly rate is kept unless a
	 * rate-relevant field (project or work date) changes, in which case the rate is
	 * re-resolved server-side. A client-supplied rate is never trusted — it is only
	 * verified against the authoritative value to detect tampering.
	 *
	 * @param int $id Time entry ID
	 * @param array $data Update data
	 * @param string $userId User ID
	 * @return TimeEntry
	 * @throws TimeEntryNotFoundException
	 * @throws PermissionDeniedException
	 * @throws \Exception
	 */
	public function updateTimeEntry($id, $data, $userId)
	{
		$timeEntry = $this->getTimeEntry($id);
		if (!$timeEntry) {
			throw new TimeEntryNotFoundException((int) $id, $this->l->t('Time entry not found'));
		}

		if (!$timeEntry->isOwnedBy($userId)) {
			throw new PermissionDeniedException('update', 'time entry', $this->l->t('Access denied'));
		}

		$originalProjectId = (int) $timeEntry->getProjectId();
		$originalDateKey = $timeEntry->getDate() instanceof \DateTimeInterface
			? $timeEntry->getDate()->format('Y-m-d')
			: null;

		if (isset($data['project_id'])) {
			$timeEntry->setProjectId((int)$data['project_id']);
		}
		if (isset($data['date'])) {
			if ($data['date'] instanceof \DateTimeInterface) {
				$timeEntry->setDate(\DateTime::createFromInterface($data['date']));
			} else {
				$parsed = $this->parseTimeEntryDateString($data['date']);
				if ($parsed !== null) {
					$timeEntry->setDate($parsed);
				}
			}
		}
		if (isset($data['hours'])) {
			$timeEntry->setHours((float)$data['hours']);
		}
		if (isset($data['description'])) {
			$timeEntry->setDescription((string)$data['description']);
		}

		$targetProjectId = (int) $timeEntry->getProjectId();
		$projectChanged = $targetProjectId !== $originalProjectId;

		$targetProject = $this->projectMapper->find($targetProjectId);
		if (!$targetProject) {
			throw new ValidationException([], $this->l->t('Project not found'));
		}

		if ($projectChanged) {
			if (!$targetProject->allowsTimeTracking()) {
				throw new ValidationException([], $this->l->t('Time entries cannot be moved to a project that is not Active or On Hold.'));
			}
			if (!$this->projectService->canUserAddTimeEntryForProject($userId, $targetProjectId)) {
				throw new PermissionDeniedException('move', 'time entry', $this->l->t('Access denied'));
			}
		}

		$entryDate = $timeEntry->getDate();
		if (!$entryDate instanceof \DateTimeInterface) {
			throw new ValidationException([], $this->l->t('Date is required'));
		}
		$dateChanged = $originalDateKey !== $entryDate->format('Y-m-d');

		$clientRate = $data['hourly_rate'] ?? null;
		if ($projectChanged || $dateChanged) {
			// Rate depends on project and work date — re-resolve authoritatively.
			$resolvedRate = $this->resolveAndAssertClientRate(
				$targetProjectId,
				$userId,
				$entryDate,
				$clientRate
			);
			$timeEntry->setHourlyRate($resolvedRate);
		} elseif ($clientRate !== null && $clientRate !== '') {
			// Nothing rate-relevant changed: keep the frozen stored rate, but still
			// reject a tampered client rate that contradicts it.
			if (!is_numeric($clientRate)) {
				throw new ValidationException([], $this->l->t('Invalid hourly rate'));
			}
			try {
				$this->hourlyRateService->assertClientRateMatchesResolved(
					(float) $clientRate,
					(float) $timeEntry->getHourlyRate()
				);
			} catch (RateResolutionException $e) {
				throw new ValidationException([], $e->getMessage());
			}
		}

		$timeEntry->setUpdatedAt(new \DateTime());

		return $this->timeEntryMapper->update($timeEntry);
	}

	/**
	 * @throws ValidationException
	 */
	private function resolveAndAssertClientRate(
		int $projectId,
		string $userId,
		\DateTimeInterface $entryDate,
		mixed $clientRate,
	): float {
		try {
			$resolved = $this->hourlyRateService->resolveForTimeEntry($projectId, $userId, $entryDate);
		} catch (RateResolutionException $e) {
			throw new ValidationException([], $e->getMessage());
		}

		if ($clientRate !== null && $clientRate !== '') {
			if (!is_numeric($clientRate)) {
				throw new ValidationException([], $this->l->t('Invalid hourly rate'));
			}
			try {
				$this->hourlyRateService->assertClientRateMatchesResolved((float) $clientRate, $resolved);
			} catch (RateResolutionException $e) {
				throw new ValidationException([], $e->getMessage());
			}
		}

		return $resolved;
	}

	/**
	 * Delete a time entry (owner only)
	 *
	 * @param int $id Time entry ID
	 * @param string $userId User ID
	 * @throws TimeEntryNotFoundException
	 * @throws PermissionDeniedException
	 */
	public function deleteTimeEntry($id, $userId)
	{
		$timeEntry = $this->getTimeEntry($id);
		if (!$timeEntry) {
			throw new TimeEntryNotFoundException((int) $id, $this->l->t('Time entry not found'));
		}

		if (!$timeEntry->isOwnedBy($userId)) {
			throw new PermissionDeniedException('delete', 'time entry', $this->l->t('Access denied'));
		}

		$this->timeEntryMapper->delete($timeEntry);
	}

	/**
	 * Remove a time entry as part of system maintenance (cron/CLI). No per-user ownership check.
	 * Do not call from user HTTP controllers.
	 */
	public function deleteTimeEntryForMaintenance(int $id): void
	{
		$timeEntry = $this->getTimeEntry($id);
		if (!$timeEntry) {
			return;
		}
		$this->timeEntryMapper->delete($timeEntry);
	}

	/**
	 * Get time entry deletion impact
	 *
	 * @param int $id
	 * @return array
	 */
	public function getTimeEntryDeletionImpact(int $id): array
	{
		$timeEntry = $this->getTimeEntry($id);
		if (!$timeEntry) {
			return ['time_entry' => null];
		}

		return [
			'time_entry' => [
				'id' => $timeEntry->getId(),
				'project_id' => $timeEntry->getProjectId(),
				'user_id' => $timeEntry->getUserId(),
				'date' => $timeEntry->getFormattedDate(),
				'hours' => $timeEntry->getHours(),
				'description' => $timeEntry->getDescription(),
				'cost' => $timeEntry->getCost()
			]
		];
	}

	/**
	 * Search time entries
	 *
	 * @param string $query Search query
	 * @param string $userId User ID
	 * @return TimeEntry[]
	 */
	public function searchTimeEntries($query, $userId)
	{
		return $this->timeEntryMapper->search($query, $userId);
	}

	/**
	 * Distinct ids of projects on which the user owns at least one time entry.
	 *
	 * @return list<int>
	 */
	public function getProjectIdsWithEntriesForUser(string $userId): array
	{
		return $this->timeEntryMapper->findDistinctProjectIdsByUser($userId);
	}

	/**
	 * Get total hours for a project
	 *
	 * @param int $projectId Project ID
	 * @return float
	 */
	public function getTotalHoursForProject($projectId)
	{
		return $this->timeEntryMapper->getTotalHoursForProject($projectId);
	}

	/**
	 * Get total cost for a project
	 *
	 * @param int $projectId Project ID
	 * @return float
	 */
	public function getTotalCostForProject($projectId)
	{
		return $this->timeEntryMapper->getTotalCostForProject($projectId);
	}

	/**
	 * @return float Total cost for one user on one project
	 */
	public function getTotalCostForProjectAndUser(int $projectId, string $userId): float
	{
		$sum = '0';
		foreach ($this->timeEntryMapper->findByProjectAndUser($projectId, $userId) as $entry) {
			$sum = \OCA\ProjectCheck\Util\Money::add($sum, $entry->getCost());
		}
		return \OCA\ProjectCheck\Util\Money::asFloat($sum);
	}

	/**
	 * Get total hours for a user
	 *
	 * @param string $userId User ID
	 * @return float
	 */
	public function getTotalHoursForUser($userId)
	{
		return $this->timeEntryMapper->getTotalHoursForUser($userId);
	}

	/**
	 * Total billed cost for all of a user's time entries (every project).
	 */
	public function getTotalCostForUser(string $userId): float
	{
		return $this->timeEntryMapper->getTotalCostForUser($userId);
	}

	/**
	 * Get time entry statistics for a user
	 *
	 * @param string $userId User ID
	 * @return array
	 */
	public function getTimeEntryStats($userId)
	{
		$totalHours = $this->getTotalHoursForUser($userId);
		$totalEntries = $this->timeEntryMapper->countByUser($userId);

		$recentEntries = $this->timeEntryMapper->findByUser($userId);

		return [
			'total_hours' => $totalHours,
			'total_entries' => $totalEntries,
			'recent_entries' => $recentEntries,
			'average_hours_per_entry' => $totalEntries > 0 ? $totalHours / $totalEntries : 0
		];
	}

	/**
	 * Get yearly statistics for a project
	 *
	 * @param int $projectId Project ID
	 * @return array
	 */
	public function getYearlyStatsForProject($projectId)
	{
		return $this->timeEntryMapper->getYearlyStatsForProject($projectId);
	}

	/**
	 * Get yearly statistics for all projects
	 *
	 * @return array
	 */
	public function getYearlyStatsForAllProjects(?array $projectIds = null, ?array $userIds = null)
	{
		if ($projectIds === null && $userIds === null) {
			return $this->timeEntryMapper->getYearlyStatsForAllProjects();
		}
		return $this->timeEntryMapper->getYearlyStatsForScope($projectIds, $userIds);
	}

	/**
	 * Get yearly statistics for a customer
	 *
	 * @param int $customerId Customer ID
	 * @return array
	 */
	public function getYearlyStatsForCustomer($customerId, ?array $projectIds = null, ?array $userIds = null)
	{
		return $this->timeEntryMapper->getYearlyStatsForCustomer((int) $customerId, $projectIds, $userIds);
	}

	/**
	 * Get detailed yearly statistics grouped by customer and project
	 *
	 * @return array
	 */
	public function getDetailedYearlyStats(?array $projectIds = null, ?array $userIds = null)
	{
		return $this->timeEntryMapper->getDetailedYearlyStats($projectIds, $userIds);
	}

	/**
	 * Get yearly statistics for an employee
	 *
	 * @param string $userId User ID
	 * @return array
	 */
	public function getYearlyStatsForEmployee($userId)
	{
		return $this->timeEntryMapper->getYearlyStatsForEmployee($userId);
	}

	/**
	 * Get detailed yearly statistics grouped by employee
	 *
	 * @return array
	 */
	public function getEmployeeYearlyStats(?array $projectIds = null, ?array $userIds = null)
	{
		return $this->timeEntryMapper->getEmployeeYearlyStats($projectIds, $userIds);
	}

	/**
	 * Get employee comparison statistics
	 *
	 * @return array
	 */
	public function getEmployeeComparisonStats(?array $projectIds = null, ?array $userIds = null)
	{
		return $this->timeEntryMapper->getEmployeeComparisonStats($projectIds, $userIds);
	}

	/**
	 * Get yearly statistics grouped by project type
	 *
	 * @return array
	 */
	public function getYearlyStatsByProjectType(?array $projectIds = null, ?array $userIds = null)
	{
		return $this->timeEntryMapper->getYearlyStatsByProjectType($projectIds, $userIds);
	}

	/**
	 * Get detailed yearly statistics grouped by project type and customer
	 *
	 * @return array
	 */
	public function getDetailedYearlyStatsByProjectType(?array $projectIds = null, ?array $userIds = null)
	{
		return $this->timeEntryMapper->getDetailedYearlyStatsByProjectType($projectIds, $userIds);
	}

	/**
	 * Get productivity analysis (billable vs overhead)
	 *
	 * @return array
	 */
	public function getProductivityAnalysis(?array $projectIds = null, ?array $userIds = null)
	{
		return $this->timeEntryMapper->getProductivityAnalysis($projectIds, $userIds);
	}

	/**
	 * Parse time entry date from request (dd.mm.yyyy or yyyy-mm-dd). Null if empty/invalid.
	 *
	 * @param mixed $value
	 */
	private function parseTimeEntryDateString($value): ?\DateTime
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
		$s = trim((string)$value);
		if ($s === '') {
			return null;
		}
		if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $s, $m)) {
			$dt = \DateTime::createFromFormat('Y-m-d', $m[3] . '-' . $m[2] . '-' . $m[1]);
			return $dt !== false ? $dt : null;
		}
		if (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $s)) {
			$dt = \DateTime::createFromFormat('Y-m-d', $s);
			return $dt !== false ? $dt : null;
		}

		return null;
	}

	/**
	 * Validate time entry data (returns localized messages for API and forms)
	 *
	 * @param array $data Time entry data
	 * @return array<string, string> field => translated message
	 */
	public function validateTimeEntryData($data)
	{
		return $this->validateTimeEntryDataDetailed($data)['errors'];
	}

	/**
	 * Validate time entry data with both localized messages and stable error codes
	 *
	 * @param array $data Time entry data
	 * @return array{errors: array<string, string>, errorCodes: array<string, string>}
	 */
	public function validateTimeEntryDataDetailed(array $data): array
	{
		$raw = $this->collectTimeEntryDataValidationCodes($data);
		$errors = [];
		foreach ($raw as $field => $code) {
			$errors[$field] = $this->translateTimeEntryDataValidationCode($field, (string) $code);
		}
		return ['errors' => $errors, 'errorCodes' => $raw];
	}

	/**
	 * @return array<string, string> field => machine code
	 */
	private function collectTimeEntryDataValidationCodes(array $data): array
	{
		$errors = [];

		if (!isset($data['project_id']) || $data['project_id'] === '' || $data['project_id'] === null) {
			$errors['project_id'] = 'required';
		} else {
			if (!is_numeric($data['project_id']) || $data['project_id'] <= 0) {
				$errors['project_id'] = 'invalid';
			}
		}

		if (!isset($data['date']) || $data['date'] === '' || $data['date'] === null) {
			$errors['date'] = 'required';
		} else {
			$date = $this->parseTimeEntryDateString($data['date']);

			if (!$date) {
				$errors['date'] = 'invalid_format';
			} else {
				$today = new \DateTime();
				$today->setTime(0, 0, 0);
				$inputDate = clone $date;
				$inputDate->setTime(0, 0, 0);
				if ($inputDate > $today) {
					$errors['date'] = 'in_future';
				}
			}
		}

		if (!isset($data['hours']) || $data['hours'] === '' || $data['hours'] === null) {
			$errors['hours'] = 'required';
		} else {
			if (!is_numeric($data['hours']) || $data['hours'] <= 0) {
				$errors['hours'] = 'not_positive';
			} elseif ((float) $data['hours'] > 24) {
				$errors['hours'] = 'exceeds_24';
			}
		}

		if (isset($data['hourly_rate']) && $data['hourly_rate'] !== '' && $data['hourly_rate'] !== null) {
			if (!is_numeric($data['hourly_rate']) || (float) $data['hourly_rate'] < 0) {
				$errors['hourly_rate'] = 'invalid';
			}
		}

		if (!empty($data['description']) && strlen((string) $data['description']) > 1000) {
			$errors['description'] = 'too_long';
		}

		return $errors;
	}

	private function translateTimeEntryDataValidationCode(string $field, string $code): string
	{
		return match ($field) {
			'project_id' => match ($code) {
				'required' => $this->l->t('Project is required'),
				'invalid' => $this->l->t('Invalid project ID'),
				default => $this->l->t('Invalid parameters'),
			},
			'date' => match ($code) {
				'required' => $this->l->t('Date is required'),
				'invalid_format' => $this->l->t('Invalid date format'),
				'in_future' => $this->l->t('Date cannot be in the future'),
				default => $this->l->t('Invalid parameters'),
			},
			'hours' => match ($code) {
				'required' => $this->l->t('Hours are required'),
				'not_positive' => $this->l->t('Hours must be a positive number'),
				'exceeds_24' => $this->l->t('Hours cannot exceed 24'),
				default => $this->l->t('Invalid parameters'),
			},
			'hourly_rate' => match ($code) {
				'invalid' => $this->l->t('Hourly rate must be a non-negative number'),
				default => $this->l->t('Invalid parameters'),
			},
			'description' => match ($code) {
				'too_long' => $this->l->t('Description cannot exceed 1000 characters'),
				default => $this->l->t('Invalid parameters'),
			},
			default => $this->l->t('Invalid parameters'),
		};
	}

	/**
	 * Get time entries by date range
	 *
	 * @param string $dateFrom Start date (Y-m-d)
	 * @param string $dateTo End date (Y-m-d)
	 * @param string $userId User ID
	 * @return TimeEntry[]
	 */
	public function getTimeEntriesByDateRange($dateFrom, $dateTo, $userId)
	{
		$filters = [];
		if ($dateFrom !== null && $dateFrom !== '') {
			$filters['date_from'] = $dateFrom;
		}
		if ($dateTo !== null && $dateTo !== '') {
			$filters['date_to'] = $dateTo;
		}
		if ($userId !== null && $userId !== '' && $userId !== 'system') {
			$filters['user_id'] = $userId;
		}
		return $this->timeEntryMapper->findAll($filters);
	}

	/**
	 * Get time entries by project and user
	 *
	 * @param int $projectId Project ID
	 * @param string $userId User ID
	 * @return TimeEntry[]
	 */
	public function getTimeEntriesByProjectAndUser($projectId, $userId)
	{
		return $this->timeEntryMapper->findByProjectAndUser($projectId, $userId);
	}

	/**
	 * @return float Total hours for one user on one project
	 */
	public function getTotalHoursForProjectAndUser(int $projectId, string $userId): float
	{
		$sum = 0.0;
		foreach ($this->timeEntryMapper->findByProjectAndUser($projectId, $userId) as $entry) {
			$sum += $entry->getHours();
		}
		return $sum;
	}

	/**
	 * Get all users who have time entries
	 *
	 * @return array
	 */
	public function getUsersWithTimeEntries(?array $projectIds = null): array
	{
		return $this->timeEntryMapper->findUsersWithTimeEntries($projectIds);
	}

	/**
	 * Get yearly statistics by project type for a specific employee
	 *
	 * @param string $userId
	 * @return array
	 */
	public function getYearlyStatsByProjectTypeForEmployee(string $userId, ?array $projectIds = null): array
	{
		return $this->timeEntryMapper->getYearlyStatsByProjectTypeForEmployee($userId, $projectIds);
	}

	/**
	 * Get detailed yearly statistics by project type for all employees
	 *
	 * @return array
	 */
	public function getDetailedYearlyStatsByProjectTypeForEmployees(?array $projectIds = null, ?array $userIds = null): array
	{
		return $this->timeEntryMapper->getDetailedYearlyStatsByProjectTypeForEmployees($projectIds, $userIds);
	}

	/**
	 * Get productivity analysis for a specific employee
	 *
	 * @param string $userId
	 * @return array
	 */
	public function getProductivityAnalysisForEmployee(string $userId, ?array $projectIds = null): array
	{
		return $this->timeEntryMapper->getProductivityAnalysisForEmployee($userId, $projectIds);
	}

	/**
	 * Create or update a billing time row from ArbeitszeitCheck (same-server integration).
	 *
	 * @param int|null $existingPcEntryId Existing pc_time_entries.id linked from at_entries, or null to insert
	 * @return int pc_time_entries.id
	 * @throws \Exception
	 */
	public function upsertFromArbeitszeitCheckBilling(
		string $actorUserId,
		string $billableUserId,
		?int $existingPcEntryId,
		int $projectId,
		\DateTimeInterface $dateOnly,
		float $hours,
		string $description,
	): int {
		if (!$this->projectService->mayBillArbeitszeitCheckTimeForUser($actorUserId, $billableUserId, $projectId)) {
			throw new \Exception($this->l->t('Access denied'));
		}

		$parsedDate = \DateTime::createFromInterface($dateOnly);
		$parsedDate->setTime(0, 0, 0);

		$resolvedRate = $this->resolveAndAssertClientRate($projectId, $billableUserId, $parsedDate, null);

		$hours = round(max(0.0, $hours), 2);
		if ($hours <= 0.0) {
			throw new \Exception($this->l->t('Hours must be greater than zero'));
		}
		if ($hours > 999.99) {
			$hours = 999.99;
		}

		$desc = $description;
		if (mb_strlen($desc) > 65000) {
			$desc = mb_substr($desc, 0, 65000);
		}

		if ($existingPcEntryId !== null && $existingPcEntryId > 0) {
			$existing = $this->getTimeEntry($existingPcEntryId);
			if ($existing === null || $existing->getUserId() !== $billableUserId) {
				throw new \Exception($this->l->t('Time entry not found'));
			}

			$existing->setProjectId($projectId);
			$existing->setDate($parsedDate);
			$existing->setHours($hours);
			$existing->setDescription($desc);
			$existing->setHourlyRate($resolvedRate);
			$existing->setUpdatedAt(new \DateTime());

			$updated = $this->timeEntryMapper->update($existing);
			return (int)$updated->getId();
		}

		$timeEntry = new TimeEntry();
		$timeEntry->setProjectId($projectId);
		$timeEntry->setUserId($billableUserId);
		$timeEntry->setDate($parsedDate);
		$timeEntry->setHours($hours);
		$timeEntry->setDescription($desc);
		$timeEntry->setHourlyRate($resolvedRate);
		$timeEntry->setCreatedAt(new \DateTime());
		$timeEntry->setUpdatedAt(new \DateTime());

		$inserted = $this->timeEntryMapper->insert($timeEntry);
		return (int)$inserted->getId();
	}

	/**
	 * Delete a billing row linked from ArbeitszeitCheck (integration path).
	 *
	 * @throws \Exception
	 */
	public function deleteFromArbeitszeitCheckBilling(string $actorUserId, string $billableUserId, int $pcEntryId): void
	{
		$row = $this->getTimeEntry($pcEntryId);
		if ($row === null || $row->getUserId() !== $billableUserId) {
			throw new \Exception($this->l->t('Time entry not found'));
		}

		$pid = (int)$row->getProjectId();
		if (!$this->projectService->mayBillArbeitszeitCheckTimeForUser($actorUserId, $billableUserId, $pid)) {
			throw new \Exception($this->l->t('Access denied'));
		}

		$this->timeEntryMapper->delete($row);
	}
}
