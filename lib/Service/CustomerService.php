<?php

/**
 * Customer service for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

use OCA\ProjectCheck\Db\Customer;
use OCA\ProjectCheck\Db\CustomerMapper;
use OCA\ProjectCheck\Db\TimeEntryMapper;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\AppFramework\Db\MultipleObjectsReturnedException;

/**
 * Customer service for business logic
 */
class CustomerService
{
	/** @var CustomerMapper */
	private $customerMapper;

	/** @var ProjectService */
	private $projectService;

	/** @var TimeEntryMapper */
	private $timeEntryMapper;

	/**
	 * CustomerService constructor
	 *
	 * @param CustomerMapper $customerMapper
	 * @param ProjectService $projectService
	 * @param TimeEntryMapper $timeEntryMapper
	 */
	public function __construct(CustomerMapper $customerMapper, ProjectService $projectService, TimeEntryMapper $timeEntryMapper)
	{
		$this->customerMapper = $customerMapper;
		$this->projectService = $projectService;
		$this->timeEntryMapper = $timeEntryMapper;
	}

	/**
	 * Create a new customer
	 *
	 * @param array $data Customer data
	 * @param string $userId User creating the customer
	 * @return Customer
	 * @throws \Exception
	 */
	public function createCustomer(array $data, string $userId): Customer
	{
		// Validate required fields
		if (empty($data['name'])) {
			throw new \Exception('Customer name is required');
		}

		// Create customer entity
		$customer = new Customer();
		$customer->setName($data['name']);
		$customer->setEmail($data['email'] ?? null);
		$customer->setPhone($data['phone'] ?? null);
		$customer->setAddress($data['address'] ?? null);
		$customer->setContactPerson($data['contact_person'] ?? null);
		$customer->setCreatedBy($userId);
		$customer->setCreatedAt(new \DateTime());
		$customer->setUpdatedAt(new \DateTime());

		// Validate customer data
		$errors = $customer->validate();
		if (!empty($errors)) {
			throw new \Exception('Validation failed: ' . implode(', ', $errors));
		}

		// Check if customer with same name already exists
		$existingCustomer = $this->customerMapper->findByName($data['name']);
		if ($existingCustomer) {
			throw new \Exception('A customer with this name already exists');
		}

		// Save customer
		return $this->customerMapper->insert($customer);
	}

	/**
	 * Get customer by ID
	 *
	 * @param int $id Customer ID
	 * @return Customer|null
	 */
	public function getCustomer(int $id): ?Customer
	{
		try {
			return $this->customerMapper->find($id);
		} catch (DoesNotExistException | MultipleObjectsReturnedException $e) {
			return null;
		}
	}

	/**
	 * Get all customers
	 *
	 * @param array $filters Optional filters
	 * @return Customer[]
	 */
	public function getCustomers(array $filters = []): array
	{
		$customers = $this->customerMapper->findAll($filters);

		// Populate project count for each customer
		foreach ($customers as $customer) {
			$projectCount = $this->customerMapper->getProjectCount($customer->getId());
			$customer->setProjectCount($projectCount);
		}

		return $customers;
	}

	/**
	 * Count customers with optional filters
	 *
	 * @param array $filters
	 * @return int
	 */
	public function countCustomers(array $filters = []): int
	{
		$countFilters = $filters;
		unset($countFilters['limit'], $countFilters['offset']);
		return $this->customerMapper->countWithFilters($countFilters);
	}

	/**
	 * Update customer
	 *
	 * @param int $id Customer ID
	 * @param array $data Update data
	 * @return Customer
	 * @throws \Exception
	 */
	public function updateCustomer(int $id, array $data): Customer
	{
		$customer = $this->getCustomer($id);
		if (!$customer) {
			throw new \Exception('Customer not found');
		}

		// Update fields if provided
		if (isset($data['name'])) {
			$customer->setName($data['name']);
		}
		if (isset($data['email'])) {
			$customer->setEmail($data['email']);
		}
		if (isset($data['phone'])) {
			$customer->setPhone($data['phone']);
		}
		if (isset($data['address'])) {
			$customer->setAddress($data['address']);
		}
		if (isset($data['contact_person'])) {
			$customer->setContactPerson($data['contact_person']);
		}

		$customer->setUpdatedAt(new \DateTime());

		// Validate customer data
		$errors = $customer->validate();
		if (!empty($errors)) {
			throw new \Exception('Validation failed: ' . implode(', ', $errors));
		}

		// Check if name change conflicts with existing customer
		if (isset($data['name']) && $data['name'] !== $customer->getName()) {
			$existingCustomer = $this->customerMapper->findByName($data['name']);
			if ($existingCustomer && $existingCustomer->getId() !== $id) {
				throw new \Exception('A customer with this name already exists');
			}
		}

		// Save updated customer
		return $this->customerMapper->update($customer);
	}

	/**
	 * Delete customer
	 *
	 * @param int $id Customer ID
	 * @return bool
	 * @throws \Exception
	 */
	public function deleteCustomer(int $id): bool
	{
		$customer = $this->getCustomer($id);
		if (!$customer) {
			throw new \Exception('Customer not found');
		}

		// Check if customer has associated projects
		$projectCount = $this->customerMapper->getProjectCount($id);
		if ($projectCount > 0) {
			throw new \Exception("Cannot delete customer: {$projectCount} project(s) are associated with this customer");
		}

		// Delete customer
		$this->customerMapper->delete($customer);
		return true;
	}

	/**
	 * Delete customer with strategy options
	 *
	 * @param int $id
	 * @param array $options ['strategy' => 'restrict'|'cascade'|'reassign', 'reassignCustomerId' => int]
	 * @return bool
	 * @throws \Exception
	 */

	/**
	 * Search customers
	 *
	 * @param string $query Search query
	 * @return Customer[]
	 */
	public function searchCustomers(string $query): array
	{
		return $this->customerMapper->search($query);
	}

	/**
	 * Get customers for dropdown/select
	 *
	 * @return array
	 */
	public function getCustomersForSelect(): array
	{
		$customers = $this->getCustomers();
		$options = [];

		foreach ($customers as $customer) {
			$options[] = [
				'id' => $customer->getId(),
				'name' => $customer->getName(),
				'email' => $customer->getEmail(),
				'contactPerson' => $customer->getContactPerson()
			];
		}

		return $options;
	}

	/**
	 * Check if user can delete a customer
	 *
	 * @param string $userId
	 * @param int $customerId
	 * @return bool
	 */
	public function canUserDeleteCustomer(string $userId, int $customerId): bool
	{
		$customer = $this->getCustomer($customerId);
		if (!$customer) {
			return false;
		}

		// Only customer creator can delete
		return $customer->getCreatedBy() === $userId;
	}

	/**
	 * Get customer statistics
	 *
	 * @return array
	 */
	public function getCustomerStats(): array
	{
		$totalCustomers = $this->customerMapper->count();
		$customersWithProjects = $this->customerMapper->countWithProjects();
		$customersWithCompleteInfo = $this->customerMapper->countWithCompleteInfo();

		return [
			'totalCustomers' => $totalCustomers,
			'customersWithProjects' => $customersWithProjects,
			'customersWithCompleteInfo' => $customersWithCompleteInfo,
			'customersWithoutProjects' => $totalCustomers - $customersWithProjects,
			'customersWithIncompleteInfo' => $totalCustomers - $customersWithCompleteInfo
		];
	}

	/**
	 * Get customer-specific statistics
	 *
	 * @param int $customerId Customer ID
	 * @return array
	 */
	public function getCustomerSpecificStats(int $customerId): array
	{
		$customer = $this->getCustomer($customerId);
		if (!$customer) {
			return [
				'total_projects' => 0,
				'active_projects' => 0,
				'used_hours' => 0,
				'total_revenue' => 0,
				'total_budget' => 0,
				'budget_earned' => 0,
				'budget_remaining' => 0,
				'completed_projects' => 0,
				'average_hours_per_project' => 0,
				'average_revenue_per_project' => 0,
				'total_time_entries' => 0,
				'last_activity_date' => null,
				'budget_utilization_percentage' => 0,
				'project_completion_rate' => 0
			];
		}

		$totalProjects = $this->customerMapper->getProjectCount($customerId);

		// Get all projects for this customer
		$projects = $this->projectService->getProjectsByCustomer($customerId);

		// Initialize counters
		$activeProjects = 0;
		$completedProjects = 0;
		$totalHours = 0;
		$totalRevenue = 0;
		$totalBudget = 0;
		$totalTimeEntries = 0;
		$lastActivityDate = null;

		foreach ($projects as $project) {
			if ($project->getStatus() === 'Active') {
				$activeProjects++;
			} elseif ($project->getStatus() === 'Completed') {
				$completedProjects++;
			}

			// Get project budget
			$projectBudget = $project->getTotalBudget() ?? 0;
			$totalBudget += $projectBudget;

			// Get total hours for this project
			$projectHours = $this->timeEntryMapper->getTotalHoursForProject($project->getId());
			$totalHours += $projectHours;

			// Calculate revenue (used budget)
			$projectCost = $this->timeEntryMapper->getTotalCostForProject($project->getId());
			$totalRevenue += $projectCost;

			// Count time entries for this project
			$projectTimeEntries = $this->timeEntryMapper->count(['project_id' => $project->getId()]);
			$totalTimeEntries += $projectTimeEntries;

			// Get last activity date for this project
			$projectLastActivity = $this->timeEntryMapper->getLastActivityDateForProject($project->getId());
			if ($projectLastActivity && (!$lastActivityDate || $projectLastActivity > $lastActivityDate)) {
				$lastActivityDate = $projectLastActivity;
			}
		}

		// Calculate derived statistics
		$budgetEarned = $totalRevenue; // Revenue generated from time entries
		$budgetRemaining = max(0, $totalBudget - $totalRevenue);
		$averageHoursPerProject = $totalProjects > 0 ? $totalHours / $totalProjects : 0;
		$averageRevenuePerProject = $totalProjects > 0 ? $totalRevenue / $totalProjects : 0;
		$budgetUtilizationPercentage = $totalBudget > 0 ? ($totalRevenue / $totalBudget) * 100 : 0;
		$projectCompletionRate = $totalProjects > 0 ? ($completedProjects / $totalProjects) * 100 : 0;

		return [
			'total_projects' => $totalProjects,
			'active_projects' => $activeProjects,
			'completed_projects' => $completedProjects,
			'used_hours' => $totalHours,
			'total_revenue' => $totalRevenue,
			'total_budget' => $totalBudget,
			'budget_earned' => $budgetEarned,
			'budget_remaining' => $budgetRemaining,
			'average_hours_per_project' => $averageHoursPerProject,
			'average_revenue_per_project' => $averageRevenuePerProject,
			'total_time_entries' => $totalTimeEntries,
			'last_activity_date' => $lastActivityDate,
			'budget_utilization_percentage' => $budgetUtilizationPercentage,
			'project_completion_rate' => $projectCompletionRate
		];
	}

	/**
	 * Get customers by creator
	 *
	 * @param string $userId User ID
	 * @return Customer[]
	 */
	public function getCustomersByUser(string $userId): array
	{
		return $this->customerMapper->findByCreator($userId);
	}

	/**
	 * Get all customers in the system
	 *
	 * @return Customer[]
	 */
	public function getAllCustomers(): array
	{
		return $this->customerMapper->findAll();
	}

	/**
	 * Get customers with project count
	 *
	 * @return array
	 */
	public function getCustomersWithProjectCount(): array
	{
		return $this->customerMapper->findWithProjectCount();
	}

	/**
	 * Validate customer data
	 *
	 * @param array $data Customer data
	 * @return array Validation errors
	 */
	public function validateCustomerData(array $data): array
	{
		$errors = [];

		// Validate name
		if (empty($data['name'])) {
			$errors['name'] = 'Customer name is required';
		} elseif (strlen($data['name']) > 100) {
			$errors['name'] = 'Customer name must be 100 characters or less';
		}

		// Validate email if provided
		if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
			$errors['email'] = 'Invalid email format';
		}

		// Validate phone if provided
		if (!empty($data['phone']) && strlen($data['phone']) > 50) {
			$errors['phone'] = 'Phone number must be 50 characters or less';
		}

		// Validate contact person if provided
		if (!empty($data['contact_person']) && strlen($data['contact_person']) > 100) {
			$errors['contact_person'] = 'Contact person name must be 100 characters or less';
		}

		// Validate address if provided
		if (!empty($data['address']) && strlen($data['address']) > 500) {
			$errors['address'] = 'Address must be 500 characters or less';
		}

		// Validate email length if provided
		if (!empty($data['email']) && strlen($data['email']) > 254) {
			$errors['email'] = 'Email address must be 254 characters or less';
		}

		return $errors;
	}

	/**
	 * Get total customer count
	 *
	 * @return int
	 */
	public function getTotalCustomerCount(): int
	{
		return $this->customerMapper->countWithFilters([]);
	}

	/**
	 * Check if a customer can be deleted (no associated projects)
	 *
	 * @param int $id Customer ID
	 * @return bool
	 */
	public function canDeleteCustomer(int $id): bool
	{
		$customer = $this->getCustomer($id);
		if (!$customer) {
			return false;
		}

		// Check if customer has associated projects
		$projectCount = $this->customerMapper->getProjectCount($id);
		return $projectCount === 0;
	}
}
