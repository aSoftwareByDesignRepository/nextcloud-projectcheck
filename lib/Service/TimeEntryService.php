<?php

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
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

/**
 * TimeEntry service for time tracking business logic
 */
class TimeEntryService
{
	/** @var TimeEntryMapper */
	private $timeEntryMapper;

	/** @var ProjectMapper */
	private $projectMapper;

	/**
	 * TimeEntryService constructor
	 *
	 * @param TimeEntryMapper $timeEntryMapper
	 * @param ProjectMapper $projectMapper
	 */
	public function __construct(
		TimeEntryMapper $timeEntryMapper,
		ProjectMapper $projectMapper
	) {
		$this->timeEntryMapper = $timeEntryMapper;
		$this->projectMapper = $projectMapper;
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
		// Validate project exists
		$project = $this->projectMapper->find($data['project_id']);
		if (!$project) {
			throw new \Exception('Project not found');
		}

		$timeEntry = new TimeEntry();
		$timeEntry->setProjectId((int)$data['project_id']);
		$timeEntry->setUserId($userId);
		$timeEntry->setDate(new \DateTime($data['date']));
		$timeEntry->setHours((float)$data['hours']);
		$timeEntry->setDescription($data['description'] ?? '');
		$timeEntry->setHourlyRate((float)$data['hourly_rate']);
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
		try {
			return $this->timeEntryMapper->find($id);
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
	 * Update a time entry
	 *
	 * @param int $id Time entry ID
	 * @param array $data Update data
	 * @param string $userId User ID
	 * @return TimeEntry
	 * @throws \Exception
	 */
	public function updateTimeEntry($id, $data, $userId)
	{
		$timeEntry = $this->getTimeEntry($id);
		if (!$timeEntry) {
			throw new \Exception('Time entry not found');
		}

		// Check if user owns this time entry
		if ($timeEntry->getUserId() !== $userId) {
			throw new \Exception('Access denied');
		}

		// Update fields with proper types
		if (isset($data['project_id'])) {
			$timeEntry->setProjectId((int)$data['project_id']);
		}
		if (isset($data['date'])) {
			// Accept either DateTime, ISO yyyy-mm-dd, or dd.mm.yyyy
			if ($data['date'] instanceof \DateTimeInterface) {
				$timeEntry->setDate($data['date']);
			} else {
				$dateString = (string)$data['date'];
				$parsed = null;
				if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $dateString, $m)) {
					$parsed = \DateTime::createFromFormat('Y-m-d', $m[3] . '-' . $m[2] . '-' . $m[1]);
				} elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateString)) {
					$parsed = \DateTime::createFromFormat('Y-m-d', $dateString);
				}
				if ($parsed) {
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
		if (isset($data['hourly_rate'])) {
			$timeEntry->setHourlyRate((float)$data['hourly_rate']);
		}

		$timeEntry->setUpdatedAt(new \DateTime());

		return $this->timeEntryMapper->update($timeEntry);
	}

	/**
	 * Delete a time entry
	 *
	 * @param int $id Time entry ID
	 * @param string $userId User ID
	 * @throws \Exception
	 */
	public function deleteTimeEntry($id, $userId)
	{
		$timeEntry = $this->getTimeEntry($id);
		if (!$timeEntry) {
			throw new \Exception('Time entry not found');
		}

		// Check if user owns this time entry
		if ($timeEntry->getUserId() !== $userId) {
			throw new \Exception('Access denied');
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
	 * Get time entry statistics for a user
	 *
	 * @param string $userId User ID
	 * @return array
	 */
	public function getTimeEntryStats($userId)
	{
		$totalHours = $this->getTotalHoursForUser($userId);
		$totalEntries = $this->timeEntryMapper->countByUser($userId);

		// Get recent entries
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
	public function getYearlyStatsForAllProjects()
	{
		return $this->timeEntryMapper->getYearlyStatsForAllProjects();
	}

	/**
	 * Get yearly statistics for a customer
	 *
	 * @param int $customerId Customer ID
	 * @return array
	 */
	public function getYearlyStatsForCustomer($customerId)
	{
		return $this->timeEntryMapper->getYearlyStatsForCustomer($customerId);
	}

	/**
	 * Get detailed yearly statistics grouped by customer and project
	 *
	 * @return array
	 */
	public function getDetailedYearlyStats()
	{
		return $this->timeEntryMapper->getDetailedYearlyStats();
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
	public function getEmployeeYearlyStats()
	{
		return $this->timeEntryMapper->getEmployeeYearlyStats();
	}

	/**
	 * Get employee comparison statistics
	 *
	 * @return array
	 */
	public function getEmployeeComparisonStats()
	{
		return $this->timeEntryMapper->getEmployeeComparisonStats();
	}

	/**
	 * Get yearly statistics grouped by project type
	 *
	 * @return array
	 */
	public function getYearlyStatsByProjectType()
	{
		return $this->timeEntryMapper->getYearlyStatsByProjectType();
	}

	/**
	 * Get detailed yearly statistics grouped by project type and customer
	 *
	 * @return array
	 */
	public function getDetailedYearlyStatsByProjectType()
	{
		return $this->timeEntryMapper->getDetailedYearlyStatsByProjectType();
	}

	/**
	 * Get productivity analysis (billable vs overhead)
	 *
	 * @return array
	 */
	public function getProductivityAnalysis()
	{
		return $this->timeEntryMapper->getProductivityAnalysis();
	}

	/**
	 * Validate time entry data
	 *
	 * @param array $data Time entry data
	 * @return array Array of validation errors
	 */
	public function validateTimeEntryData($data)
	{
		$errors = [];

		// Required fields
		if (!isset($data['project_id']) || $data['project_id'] === '' || $data['project_id'] === null) {
			$errors['project_id'] = 'Project is required';
		} else {
			// Validate project_id is numeric
			if (!is_numeric($data['project_id']) || $data['project_id'] <= 0) {
				$errors['project_id'] = 'Invalid project ID';
			}
		}

		if (!isset($data['date']) || $data['date'] === '' || $data['date'] === null) {
			$errors['date'] = 'Date is required';
		} else {
			// Validate date format - support both European (dd.mm.yyyy) and ISO (yyyy-mm-dd) formats
			$date = null;
			if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $data['date'], $matches)) {
				// European format: dd.mm.yyyy
				$day = $matches[1];
				$month = $matches[2];
				$year = $matches[3];
				$isoDate = $year . '-' . $month . '-' . $day;
				$date = \DateTime::createFromFormat('Y-m-d', $isoDate);
			} elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $data['date'], $matches)) {
				// ISO format: yyyy-mm-dd (from HTML5 date inputs)
				$date = \DateTime::createFromFormat('Y-m-d', $data['date']);
			}

			if (!$date) {
				$errors['date'] = 'Invalid date format (dd.mm.yyyy)';
			} else {
				// Check if date is not in the future (allow today)
				$today = new \DateTime();
				$today->setTime(0, 0, 0);
				$inputDate = clone $date;
				$inputDate->setTime(0, 0, 0);
				if ($inputDate > $today) {
					$errors['date'] = 'Date cannot be in the future';
				}
			}
		}

		if (!isset($data['hours']) || $data['hours'] === '' || $data['hours'] === null) {
			$errors['hours'] = 'Hours are required';
		} else {
			// Validate hours
			if (!is_numeric($data['hours']) || $data['hours'] <= 0) {
				$errors['hours'] = 'Hours must be a positive number';
			}
			if ($data['hours'] > 24) {
				$errors['hours'] = 'Hours cannot exceed 24';
			}
		}

		if (!isset($data['hourly_rate']) || $data['hourly_rate'] === '' || $data['hourly_rate'] === null) {
			$errors['hourly_rate'] = 'Hourly rate is required';
		} else {
			// Validate hourly rate
			if (!is_numeric($data['hourly_rate']) || $data['hourly_rate'] < 0) {
				$errors['hourly_rate'] = 'Hourly rate must be a non-negative number';
			}
		}

		// Validate description length
		if (!empty($data['description']) && strlen($data['description']) > 1000) {
			$errors['description'] = 'Description cannot exceed 1000 characters';
		}

		return $errors;
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
		return $this->timeEntryMapper->findByDateRange($dateFrom, $dateTo, $userId);
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
	 * Get all users who have time entries
	 *
	 * @return array
	 */
	public function getUsersWithTimeEntries(): array
	{
		return $this->timeEntryMapper->findUsersWithTimeEntries();
	}

	/**
	 * Get yearly statistics by project type for a specific employee
	 *
	 * @param string $userId
	 * @return array
	 */
	public function getYearlyStatsByProjectTypeForEmployee(string $userId): array
	{
		return $this->timeEntryMapper->getYearlyStatsByProjectTypeForEmployee($userId);
	}

	/**
	 * Get detailed yearly statistics by project type for all employees
	 *
	 * @return array
	 */
	public function getDetailedYearlyStatsByProjectTypeForEmployees(): array
	{
		return $this->timeEntryMapper->getDetailedYearlyStatsByProjectTypeForEmployees();
	}

	/**
	 * Get productivity analysis for a specific employee
	 *
	 * @param string $userId
	 * @return array
	 */
	public function getProductivityAnalysisForEmployee(string $userId): array
	{
		return $this->timeEntryMapper->getProductivityAnalysisForEmployee($userId);
	}
}
