<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Util;

use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;
use OCP\IDBConnection;
use Psr\Log\LoggerInterface;
use OCP\IUserManager;
use OCP\IUserSession;

/**
 * Server-side validation helper for ProjectControl app
 * Provides comprehensive validation for forms and business logic
 */
class ValidationHelper
{
	private IDBConnection $db;
	private LoggerInterface $logger;
	private IUserManager $userManager;
	private IUserSession $userSession;

	public function __construct(
		IDBConnection $db,
		LoggerInterface $logger,
		IUserManager $userManager,
		IUserSession $userSession
	) {
		$this->db = $db;
		$this->logger = $logger;
		$this->userManager = $userManager;
		$this->userSession = $userSession;
	}

	/**
	 * Validate time entry data
	 */
	public function validateTimeEntry(array $data): array
	{
		$errors = [];

		// Required fields
		if (empty($data['project_id'])) {
			$errors['project_id'] = 'Project is required';
		}

		if (empty($data['date'])) {
			$errors['date'] = 'Date is required';
		} elseif (!$this->isValidDate($data['date'])) {
			$errors['date'] = 'Invalid date format';
		}

		if (empty($data['hours'])) {
			$errors['hours'] = 'Hours are required';
		} elseif (!$this->isValidHours($data['hours'])) {
			$errors['hours'] = 'Hours must be a positive number between 0.25 and 24';
		}

		if (empty($data['hourly_rate'])) {
			$errors['hourly_rate'] = 'Hourly rate is required';
		} elseif (!$this->isValidRate($data['hourly_rate'])) {
			$errors['hourly_rate'] = 'Hourly rate must be a non-negative number';
		}

		// Description validation
		if (!empty($data['description']) && strlen($data['description']) > 1000) {
			$errors['description'] = 'Description cannot exceed 1000 characters';
		}

		// Business logic validation
		if (empty($errors)) {
			$businessErrors = $this->validateTimeEntryBusinessLogic($data);
			$errors = array_merge($errors, $businessErrors);
		}

		return $errors;
	}

	/**
	 * Validate project data
	 */
	public function validateProject(array $data): array
	{
		$errors = [];

		// Required fields
		if (empty($data['name'])) {
			$errors['name'] = 'Project name is required';
		} elseif (!$this->isValidProjectName($data['name'])) {
			$errors['name'] = 'Project name must be 3-50 characters and contain only letters, numbers, spaces, hyphens, and underscores';
		}

		if (empty($data['customer_id'])) {
			$errors['customer_id'] = 'Customer is required';
		}

		if (empty($data['start_date'])) {
			$errors['start_date'] = 'Start date is required';
		} elseif (!$this->isValidDate($data['start_date'])) {
			$errors['start_date'] = 'Invalid start date format';
		}

		if (!empty($data['end_date']) && !$this->isValidDate($data['end_date'])) {
			$errors['end_date'] = 'Invalid end date format';
		}

		// Date range validation
		if (empty($errors['start_date']) && empty($errors['end_date']) && !empty($data['end_date'])) {
			if (strtotime($data['end_date']) <= strtotime($data['start_date'])) {
				$errors['end_date'] = 'End date must be after start date';
			}
		}

		// Budget validation
		if (!empty($data['budget']) && !$this->isValidBudget($data['budget'])) {
			$errors['budget'] = 'Budget must be a positive number';
		}

		// Business logic validation
		if (empty($errors)) {
			$businessErrors = $this->validateProjectBusinessLogic($data);
			$errors = array_merge($errors, $businessErrors);
		}

		return $errors;
	}

	/**
	 * Validate customer data
	 */
	public function validateCustomer(array $data): array
	{
		$errors = [];

		// Required fields
		if (empty($data['name'])) {
			$errors['name'] = 'Customer name is required';
		} elseif (!$this->isValidCustomerName($data['name'])) {
			$errors['name'] = 'Customer name must be 2-50 characters and contain only letters and spaces';
		}

		if (empty($data['email'])) {
			$errors['email'] = 'Email is required';
		} elseif (!$this->isValidEmail($data['email'])) {
			$errors['email'] = 'Please enter a valid email address';
		}

		// Optional fields
		if (!empty($data['phone']) && !$this->isValidPhone($data['phone'])) {
			$errors['phone'] = 'Please enter a valid phone number';
		}

		if (!empty($data['website']) && !$this->isValidUrl($data['website'])) {
			$errors['website'] = 'Please enter a valid URL';
		}

		// Business logic validation
		if (empty($errors)) {
			$businessErrors = $this->validateCustomerBusinessLogic($data);
			$errors = array_merge($errors, $businessErrors);
		}

		return $errors;
	}

	/**
	 * Validate project assignment
	 */
	public function validateProjectAssignment(array $data): array
	{
		$errors = [];

		// Required fields
		if (empty($data['project_id'])) {
			$errors['project_id'] = 'Project is required';
		}

		if (empty($data['user_id'])) {
			$errors['user_id'] = 'User is required';
		}

		if (empty($data['role'])) {
			$errors['role'] = 'Role is required';
		}

		// Business logic validation
		if (empty($errors)) {
			$businessErrors = $this->validateAssignmentBusinessLogic($data);
			$errors = array_merge($errors, $businessErrors);
		}

		return $errors;
	}

	/**
	 * Check for time entry overlaps
	 */
	public function checkTimeEntryOverlap(array $data, ?int $excludeId = null): bool
	{
		$sql = 'SELECT COUNT(*) FROM `*PREFIX*projectcontrol_time_entries` 
				WHERE `user_id` = ? AND `date` = ? AND `project_id` = ?';
		$params = [
			$data['user_id'] ?? $this->userSession->getUser()->getUID(),
			$data['date'],
			$data['project_id']
		];

		if ($excludeId) {
			$sql .= ' AND `id` != ?';
			$params[] = $excludeId;
		}

		// Check for time overlaps
		$sql .= ' AND (
			(`start_time` <= ? AND `end_time` > ?) OR
			(`start_time` < ? AND `end_time` >= ?) OR
			(`start_time` >= ? AND `end_time` <= ?)
		)';
		$params = array_merge($params, [
			$data['start_time'],
			$data['start_time'],
			$data['end_time'],
			$data['end_time'],
			$data['start_time'],
			$data['end_time']
		]);

		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		$count = (int) $stmt->fetchColumn();

		return $count > 0;
	}

	/**
	 * Check project budget constraints
	 */
	public function checkProjectBudget(int $projectId, float $hours, float $hourlyRate): array
	{
		$sql = 'SELECT `budget`, 
				COALESCE(SUM(`hours` * `hourly_rate`), 0) as spent_budget
				FROM `*PREFIX*projectcontrol_projects` p
				LEFT JOIN `*PREFIX*projectcontrol_time_entries` t ON p.id = t.project_id
				WHERE p.id = ?
				GROUP BY p.id, p.budget';

		$stmt = $this->db->prepare($sql);
		$stmt->execute([$projectId]);
		$result = $stmt->fetch();

		if (!$result) {
			return ['withinBudget' => true, 'remainingBudget' => 0];
		}

		$totalBudget = (float) $result['budget'];
		$spentBudget = (float) $result['spent_budget'];
		$newEntryCost = $hours * $hourlyRate;
		$remainingBudget = $totalBudget - $spentBudget;

		return [
			'withinBudget' => $newEntryCost <= $remainingBudget,
			'remainingBudget' => $remainingBudget,
			'totalBudget' => $totalBudget,
			'spentBudget' => $spentBudget,
			'newEntryCost' => $newEntryCost
		];
	}

	/**
	 * Check user availability for project assignment
	 */
	public function checkUserAvailability(string $userId, int $projectId, ?string $startDate = null, ?string $endDate = null): bool
	{
		// Check if user is already assigned to this project
		$sql = 'SELECT COUNT(*) FROM `*PREFIX*projectcontrol_project_assignments` 
				WHERE `user_id` = ? AND `project_id` = ? AND `status` = "active"';

		$stmt = $this->db->prepare($sql);
		$stmt->execute([$userId, $projectId]);
		$count = (int) $stmt->fetchColumn();

		if ($count > 0) {
			return false; // User is already assigned
		}

		// Check for overlapping project assignments
		if ($startDate && $endDate) {
			$sql = 'SELECT COUNT(*) FROM `*PREFIX*projectcontrol_project_assignments` pa
					JOIN `*PREFIX*projectcontrol_projects` p ON pa.project_id = p.id
					WHERE pa.user_id = ? AND pa.status = "active"
					AND (
						(p.start_date <= ? AND p.end_date >= ?) OR
						(p.start_date <= ? AND p.end_date >= ?) OR
						(p.start_date >= ? AND p.end_date <= ?)
					)';

			$stmt = $this->db->prepare($sql);
			$stmt->execute([$userId, $endDate, $startDate, $startDate, $endDate, $startDate, $endDate]);
			$count = (int) $stmt->fetchColumn();

			if ($count > 0) {
				return false; // User has overlapping project assignments
			}
		}

		return true;
	}

	/**
	 * Check customer project limit
	 */
	public function checkCustomerProjectLimit(int $customerId): bool
	{
		$sql = 'SELECT COUNT(*) FROM `*PREFIX*projectcontrol_projects` 
				WHERE `customer_id` = ? AND `status` IN ("active", "in_progress")';

		$stmt = $this->db->prepare($sql);
		$stmt->execute([$customerId]);
		$count = (int) $stmt->fetchColumn();

		// Assume maximum 10 active projects per customer
		return $count < 10;
	}

	// ===== PRIVATE VALIDATION METHODS =====

	/**
	 * Validate time entry business logic
	 */
	private function validateTimeEntryBusinessLogic(array $data): array
	{
		$errors = [];

		// Check for time overlaps
		if ($this->checkTimeEntryOverlap($data, $data['id'] ?? null)) {
			$errors['time_overlap'] = 'This time entry overlaps with an existing entry';
		}

		// Check project budget
		$budgetCheck = $this->checkProjectBudget(
			(int) $data['project_id'],
			(float) $data['hours'],
			(float) $data['hourly_rate']
		);

		if (!$budgetCheck['withinBudget']) {
			$errors['budget_exceeded'] = $this->l10n->t('This time entry would exceed the project budget');
		}

		// Check if project is active
		$sql = 'SELECT `status` FROM `*PREFIX*projectcontrol_projects` WHERE `id` = ?';
		$stmt = $this->db->prepare($sql);
		$stmt->execute([$data['project_id']]);
		$project = $stmt->fetch();

		if (!$project || !in_array($project['status'], ['active', 'in_progress'])) {
			$errors['project_inactive'] = 'Cannot add time entries to inactive projects';
		}

		return $errors;
	}

	/**
	 * Validate project business logic
	 */
	private function validateProjectBusinessLogic(array $data): array
	{
		$errors = [];

		// Check for duplicate project names
		$sql = 'SELECT COUNT(*) FROM `*PREFIX*projectcontrol_projects` 
				WHERE `name` = ? AND `customer_id` = ?';
		$params = [$data['name'], $data['customer_id']];

		if (!empty($data['id'])) {
			$sql .= ' AND `id` != ?';
			$params[] = $data['id'];
		}

		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		$count = (int) $stmt->fetchColumn();

		if ($count > 0) {
			$errors['duplicate_name'] = 'A project with this name already exists for this customer';
		}

		// Check customer project limit
		if (!$this->checkCustomerProjectLimit((int) $data['customer_id'])) {
			$errors['customer_limit'] = 'This customer has reached their maximum number of active projects';
		}

		return $errors;
	}

	/**
	 * Validate customer business logic
	 */
	private function validateCustomerBusinessLogic(array $data): array
	{
		$errors = [];

		// Check for duplicate email
		$sql = 'SELECT COUNT(*) FROM `*PREFIX*projectcontrol_customers` WHERE `email` = ?';
		$params = [$data['email']];

		if (!empty($data['id'])) {
			$sql .= ' AND `id` != ?';
			$params[] = $data['id'];
		}

		$stmt = $this->db->prepare($sql);
		$stmt->execute($params);
		$count = (int) $stmt->fetchColumn();

		if ($count > 0) {
			$errors['duplicate_email'] = 'A customer with this email already exists';
		}

		return $errors;
	}

	/**
	 * Validate assignment business logic
	 */
	private function validateAssignmentBusinessLogic(array $data): array
	{
		$errors = [];

		// Check if user exists
		if (!$this->userManager->get($data['user_id'])) {
			$errors['user_not_found'] = 'User does not exist';
		}

		// Check if project exists and is active
		$sql = 'SELECT `status` FROM `*PREFIX*projectcontrol_projects` WHERE `id` = ?';
		$stmt = $this->db->prepare($sql);
		$stmt->execute([$data['project_id']]);
		$project = $stmt->fetch();

		if (!$project) {
			$errors['project_not_found'] = 'Project does not exist';
		} elseif (!in_array($project['status'], ['active', 'in_progress'])) {
			$errors['project_inactive'] = 'Cannot assign users to inactive projects';
		}

		// Check user availability
		if (empty($errors) && !$this->checkUserAvailability($data['user_id'], (int) $data['project_id'])) {
			$errors['user_unavailable'] = 'User is not available for this project assignment';
		}

		return $errors;
	}

	// ===== UTILITY VALIDATION METHODS =====

	private function isValidDate(string $date): bool
	{
		return (bool) strtotime($date);
	}

	private function isValidHours(string $hours): bool
	{
		$hours = (float) $hours;
		return $hours >= 0.25 && $hours <= 24;
	}

	private function isValidRate(string $rate): bool
	{
		$rate = (float) $rate;
		return $rate >= 0;
	}

	private function isValidProjectName(string $name): bool
	{
		return preg_match('/^[a-zA-Z0-9\s\-_]{3,50}$/', $name);
	}

	private function isValidCustomerName(string $name): bool
	{
		return preg_match('/^[a-zA-Z\s]{2,50}$/', $name);
	}

	private function isValidEmail(string $email): bool
	{
		return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
	}

	private function isValidPhone(string $phone): bool
	{
		return preg_match('/^[\+]?[0-9\s\-\(\)]{10,20}$/', $phone);
	}

	private function isValidUrl(string $url): bool
	{
		return filter_var($url, FILTER_VALIDATE_URL) !== false;
	}

	private function isValidBudget(string $budget): bool
	{
		$budget = (float) $budget;
		return $budget > 0;
	}

	/**
	 * Format validation errors for API response
	 */
	public function formatValidationErrors(array $errors): array
	{
		$formatted = [];
		foreach ($errors as $field => $message) {
			$formatted[] = [
				'field' => $field,
				'message' => $message
			];
		}
		return $formatted;
	}

	/**
	 * Get validation statistics
	 */
	public function getValidationStats(): array
	{
		$sql = 'SELECT 
				COUNT(*) as total_entries,
				COUNT(CASE WHEN `status` = "valid" THEN 1 END) as valid_entries,
				COUNT(CASE WHEN `status` = "invalid" THEN 1 END) as invalid_entries
				FROM `*PREFIX*projectcontrol_validation_log`';

		$stmt = $this->db->prepare($sql);
		$stmt->execute();
		return $stmt->fetch();
	}
}
