<?php

/**
 * ProjectService for the projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Db\ProjectMember;
use OCA\ProjectCheck\Util\ProjectCalculator;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\IConfig;

/**
 * Class ProjectService
 *
 * @package OCA\ProjectControl\Service
 */
class ProjectService
{

	/** @var IDBConnection */
	private $db;

	/** @var IUserSession */
	private $userSession;



	/** @var ProjectCalculator */
	private $calculator;

	/** @var IUserManager */
	private $userManager;

	/** @var IConfig */
	private $config;

	/**
	 * ProjectService constructor
	 *
	 * @param IDBConnection $db
	 * @param IUserSession $userSession
	 * @param IUserManager $userManager
	 * @param IConfig $config
	 */
	public function __construct(IDBConnection $db, IUserSession $userSession, IUserManager $userManager, IConfig $config)
	{
		$this->db = $db;
		$this->userSession = $userSession;
		$this->userManager = $userManager;
		$this->config = $config;
		$this->calculator = new ProjectCalculator();
	}

	/**
	 * Create a new project
	 *
	 * @param array $data
	 * @return Project
	 * @throws \Exception
	 */
	public function createProject(array $data): Project
	{
		$this->validateProjectData($data);

		$user = $this->userSession->getUser();
		if (!$user) {
			throw new \Exception('User not authenticated');
		}

		$userId = $user->getUID();

		// Get user's default settings
		$defaultHourlyRate = $this->config->getUserValue($userId, 'projectcheck', 'default_hourly_rate', '50.00');
		$defaultStatus = $this->config->getUserValue($userId, 'projectcheck', 'default_project_status', 'Active');
		$defaultPriority = $this->config->getUserValue($userId, 'projectcheck', 'default_project_priority', 'Medium');

		// Calculate available hours from budget and rate (if provided)
		$hourlyRate = $data['hourly_rate'] ?? floatval($defaultHourlyRate);
		$totalBudget = $data['total_budget'] ?? 0;

		if ($hourlyRate > 0 && $totalBudget > 0) {
			$availableHours = $this->calculator->calculateAvailableHours($totalBudget, $hourlyRate);
		} else {
			$availableHours = $data['available_hours'] ?? 0;
		}

		$project = new Project();
		$project->setName($data['name']);
		$project->setShortDescription($data['short_description']);
		$project->setDetailedDescription($data['detailed_description'] ?? '');
		$project->setCustomerId($data['customer_id']);
		$project->setHourlyRate($hourlyRate);
		$project->setTotalBudget($totalBudget);
		$project->setAvailableHours($availableHours);
		$project->setPriority($data['priority'] ?? $defaultPriority);
		$project->setStatus($data['status'] ?? $defaultStatus);
		$project->setStartDate($this->parseEuropeanDate($data['start_date'] ?? null));
		$project->setEndDate($this->parseEuropeanDate($data['end_date'] ?? null));
		$project->setTags($data['tags'] ?? '');
		$project->setProjectType($data['project_type'] ?? 'client');
		$project->setCreatedBy($user->getUID());
		$project->setCreatedAt(new \DateTime());
		$project->setUpdatedAt(new \DateTime());

		$qb = $this->db->getQueryBuilder();
		$values = [
			'name' => $qb->createNamedParameter($project->getName()),
			'short_description' => $qb->createNamedParameter($project->getShortDescription()),
			'detailed_description' => $qb->createNamedParameter($project->getDetailedDescription()),
			'customer_id' => $qb->createNamedParameter($project->getCustomerId(), IQueryBuilder::PARAM_INT),
			'hourly_rate' => $qb->createNamedParameter($project->getHourlyRate()),
			'total_budget' => $qb->createNamedParameter($project->getTotalBudget()),
			'available_hours' => $qb->createNamedParameter($project->getAvailableHours()),
			'priority' => $qb->createNamedParameter($project->getPriority()),
			'status' => $qb->createNamedParameter($project->getStatus()),
			'start_date' => $project->getStartDate() ? $qb->createNamedParameter($project->getStartDate()->format('Y-m-d H:i:s')) : $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL),
			'end_date' => $project->getEndDate() ? $qb->createNamedParameter($project->getEndDate()->format('Y-m-d H:i:s')) : $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL),
			'tags' => $qb->createNamedParameter($project->getTags()),
			'created_by' => $qb->createNamedParameter($project->getCreatedBy()),
			'created_at' => $qb->createNamedParameter($project->getCreatedAt()->format('Y-m-d H:i:s')),
			'updated_at' => $qb->createNamedParameter($project->getUpdatedAt()->format('Y-m-d H:i:s')),
		];

		// Add project_type column if it exists
		if ($this->columnExists('projects', 'project_type')) {
			$values['project_type'] = $qb->createNamedParameter($project->getProjectType());
		}

		$qb->insert('projects')->values($values);

		try {
			$qb->execute();
		} catch (\Exception $e) {
			// If the error is about project_type column not existing, try again without it
			if (strpos($e->getMessage(), 'project_type') !== false || strpos($e->getMessage(), 'Unknown column') !== false) {
				// Remove project_type from the insert and try again
				unset($values['project_type']);
				$qb = $this->db->getQueryBuilder();
				$qb->insert('projects')->values($values);
				$qb->execute();
			} else {
				// Re-throw if it's a different error
				throw $e;
			}
		}
		$project->setId($this->db->lastInsertId('projects'));

		// Removed logger call

		return $project;
	}

	/**
	 * Get project by ID
	 *
	 * @param int $id
	 * @return Project|null
	 */
	public function getProject(int $id): ?Project
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('p.*', 'c.name as customer_name')
			->from('projects', 'p')
			->leftJoin('p', 'customers', 'c', $qb->expr()->eq('p.customer_id', 'c.id'))
			->where($qb->expr()->eq('p.id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		$result = $qb->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if (!$row) {
			return null;
		}

		return $this->mapRowToProject($row);
	}

	/**
	 * Get filtered list of projects
	 *
	 * @param array $filters
	 * @return array
	 */
	public function getProjects(array $filters = []): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('p.*', 'c.name as customer_name')
			->from('projects', 'p')
			->leftJoin('p', 'customers', 'c', $qb->expr()->eq('p.customer_id', 'c.id'));

		// Apply filters
		if (!empty($filters['status'])) {
			if (is_array($filters['status'])) {
				// Handle multiple statuses
				$statusParams = [];
				foreach ($filters['status'] as $status) {
					$statusParams[] = $qb->createNamedParameter($status);
				}
				$qb->andWhere($qb->expr()->in('status', $statusParams));
			} else {
				// Handle single status
				$qb->andWhere($qb->expr()->eq('status', $qb->createNamedParameter($filters['status'])));
			}
		}

		if (!empty($filters['customer_id'])) {
			$qb->andWhere($qb->expr()->eq('customer_id', $qb->createNamedParameter($filters['customer_id'], IQueryBuilder::PARAM_INT)));
		}


		if (!empty($filters['priority'])) {
			$qb->andWhere($qb->expr()->eq('priority', $qb->createNamedParameter($filters['priority'])));
		}

		if (!empty($filters['project_type']) && $this->columnExists('projects', 'project_type')) {
			$qb->andWhere($qb->expr()->eq('project_type', $qb->createNamedParameter($filters['project_type'])));
		}

		if (!empty($filters['search'])) {
			$qb->andWhere($qb->expr()->orX(
				$qb->expr()->like('name', $qb->createNamedParameter('%' . $filters['search'] . '%')),
				$qb->expr()->like('short_description', $qb->createNamedParameter('%' . $filters['search'] . '%'))
			));
		}

		// Apply pagination
		if (isset($filters['limit'])) {
			$qb->setMaxResults($filters['limit']);
		}

		if (isset($filters['offset'])) {
			$qb->setFirstResult($filters['offset']);
		}

		// Apply sorting
		$sortField = $filters['sort'] ?? 'created_at';
		$sortDirection = $filters['direction'] ?? 'DESC';
		$qb->orderBy($sortField, $sortDirection);

		$result = $qb->execute();
		$projects = [];

		while ($row = $result->fetch()) {
			$projects[] = $this->mapRowToProject($row);
		}

		$result->closeCursor();

		return $projects;
	}

	/**
	 * Update existing project
	 *
	 * @param int $id
	 * @param array $data
	 * @return Project
	 * @throws \Exception
	 */
	public function updateProject(int $id, array $data): Project
	{
		$project = $this->getProject($id);
		if (!$project) {
			throw new \Exception('Project not found');
		}

		if ($project->isCompleted() || $project->isCancelled()) {
			throw new \Exception('Cannot edit completed or cancelled projects');
		}

		$this->validateProjectData($data, $id);

		// Recalculate available hours if budget or rate changed
		if (isset($data['total_budget']) || isset($data['hourly_rate'])) {
			$budget = $data['total_budget'] ?? $project->getTotalBudget();
			$rate = $data['hourly_rate'] ?? $project->getHourlyRate();

			if ($budget > 0 && $rate > 0) {
				$data['available_hours'] = $this->calculator->calculateAvailableHours($budget, $rate);
			}
		}

		$qb = $this->db->getQueryBuilder();
		$qb->update('projects')
			->set('updated_at', $qb->createNamedParameter((new \DateTime())->format('Y-m-d H:i:s')));

		// Map form field names to database column names
		$dbFieldMapping = [
			'name' => 'name',
			'short_description' => 'short_description',
			'detailed_description' => 'detailed_description',
			'customer_id' => 'customer_id',
			'hourly_rate' => 'hourly_rate',
			'total_budget' => 'total_budget',
			'available_hours' => 'available_hours',
			'start_date' => 'start_date',
			'end_date' => 'end_date',
			'status' => 'status',
			'priority' => 'priority',
			'tags' => 'tags',
			'project_type' => 'project_type'
		];

		foreach ($data as $field => $value) {
			// Only process fields that exist in our mapping
			if (isset($dbFieldMapping[$field])) {
				$dbField = $dbFieldMapping[$field];

				// Handle date fields
				if (in_array($field, ['start_date', 'end_date'])) {
					if (!empty($value)) {
						$dateObj = $this->parseEuropeanDate($value);
						$value = $dateObj ? $dateObj->format('Y-m-d H:i:s') : null;
					} else {
						$value = null; // Set to NULL if empty
					}
				}

				// Handle numeric fields
				if (in_array($field, ['customer_id']) && !empty($value)) {
					$value = (int)$value;
				}

				if (in_array($field, ['hourly_rate', 'total_budget', 'available_hours']) && !empty($value)) {
					$value = (float)$value;
				}

				if ($value === null) {
					$qb->set($dbField, $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL));
				} else {
					$qb->set($dbField, $qb->createNamedParameter($value));
				}
			}
		}

		$qb->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));

		try {
			$qb->execute();
		} catch (\Exception $e) {
			// If the error is about project_type column not existing, try again without it
			if (strpos($e->getMessage(), 'project_type') !== false || strpos($e->getMessage(), 'Unknown column') !== false) {
				// Remove project_type from the update and try again
				$qb = $this->db->getQueryBuilder();
				$qb->update('projects')
					->set('updated_at', $qb->createNamedParameter((new \DateTime())->format('Y-m-d H:i:s')));

				// Rebuild the query without project_type
				$dbFieldMappingWithoutProjectType = [
					'name' => 'name',
					'short_description' => 'short_description',
					'detailed_description' => 'detailed_description',
					'customer_id' => 'customer_id',
					'hourly_rate' => 'hourly_rate',
					'total_budget' => 'total_budget',
					'available_hours' => 'available_hours',
					'start_date' => 'start_date',
					'end_date' => 'end_date',
					'status' => 'status',
					'priority' => 'priority',
					'tags' => 'tags'
				];

				foreach ($data as $field => $value) {
					if (isset($dbFieldMappingWithoutProjectType[$field])) {
						$dbField = $dbFieldMappingWithoutProjectType[$field];

						// Handle date fields
						if (in_array($field, ['start_date', 'end_date'])) {
							if (!empty($value)) {
								$dateObj = $this->parseEuropeanDate($value);
								$value = $dateObj ? $dateObj->format('Y-m-d H:i:s') : null;
							} else {
								$value = null;
							}
						}

						// Handle numeric fields
						if (in_array($field, ['customer_id']) && !empty($value)) {
							$value = (int)$value;
						}

						if (in_array($field, ['hourly_rate', 'total_budget', 'available_hours']) && !empty($value)) {
							$value = (float)$value;
						}

						if ($value === null) {
							$qb->set($dbField, $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL));
						} else {
							$qb->set($dbField, $qb->createNamedParameter($value));
						}
					}
				}

				$qb->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
				$qb->execute();
			} else {
				// Re-throw if it's a different error
				throw $e;
			}
		}

		// Removed logger call

		return $this->getProject($id);
	}

	/**
	 * Delete/cancel project
	 *
	 * @param int $id
	 * @return bool
	 * @throws \Exception
	 */
	public function deleteProject(int $id): bool
	{
		$project = $this->getProject($id);
		if (!$project) {
			throw new \Exception('Project not found');
		}

		// Check if project has active team members
		$teamMembers = $this->getProjectTeam($id);
		if (!empty($teamMembers) && $project->isActive()) {
			throw new \Exception('Cannot delete project with active team members');
		}

		// Start transaction for data integrity
		$this->db->beginTransaction();

		try {
			// Delete related time entries first
			$qb = $this->db->getQueryBuilder();
			$qb->delete('time_entries')
				->where($qb->expr()->eq('project_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
			$qb->execute();

			// Delete project team members
			$qb = $this->db->getQueryBuilder();
			$qb->delete('project_members')
				->where($qb->expr()->eq('project_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
			$qb->execute();

			// Delete the project itself
			$qb = $this->db->getQueryBuilder();
			$qb->delete('projects')
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
			$qb->execute();

			// Commit transaction
			$this->db->commit();

			return true;
		} catch (\Exception $e) {
			// Rollback transaction on error
			$this->db->rollBack();
			throw $e;
		}
	}

	/**
	 * Search projects by name or description
	 *
	 * @param string $query
	 * @return array
	 */
	public function searchProjects(string $query): array
	{
		return $this->getProjects(['search' => $query]);
	}

	/**
	 * Check if user can delete a project
	 *
	 * @param string $userId
	 * @param int $projectId
	 * @return bool
	 */
	public function canUserDeleteProject(string $userId, int $projectId): bool
	{
		$project = $this->getProject($projectId);
		if (!$project) {
			return false;
		}

		// Project creator can delete
		if ($project->getCreatedBy() === $userId) {
			return true;
		}

		// Check if user is a Project Manager
		$teamMembers = $this->getProjectTeam($projectId);
		foreach ($teamMembers as $member) {
			if ($member->getUserId() === $userId && $member->isProjectManager()) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if user can manage project members
	 *
	 * @param string $userId
	 * @param int $projectId
	 * @return bool
	 */
	public function canUserManageMembers(string $userId, int $projectId): bool
	{
		return $this->canUserDeleteProject($userId, $projectId);
	}

	/**
	 * Filter projects by various criteria
	 *
	 * @param array $filters
	 * @return array
	 */
	public function filterProjects(array $filters): array
	{
		return $this->getProjects($filters);
	}

	/**
	 * Get projects for specific customer
	 *
	 * @param int $customerId
	 * @return array
	 */
	public function getProjectsByCustomer(int $customerId): array
	{
		return $this->getProjects(['customer_id' => $customerId]);
	}

	/**
	 * Get projects by status
	 *
	 * @param string $status
	 * @return array
	 */
	public function getProjectsByStatus(string $status): array
	{
		return $this->getProjects(['status' => $status]);
	}

	/**
	 * Check if user can access project
	 *
	 * @param string $userId
	 * @param int $projectId
	 * @return bool
	 */
	public function canUserAccessProject(string $userId, int $projectId): bool
	{
		$project = $this->getProject($projectId);
		if (!$project) {
			return false;
		}

		// Admin can access all projects
		if ($this->isUserAdmin($userId)) {
			return true;
		}

		// Project creator can access
		if ($project->getCreatedBy() === $userId) {
			return true;
		}

		// Team members can access
		$member = $this->getProjectMember($projectId, $userId);
		return $member !== null;
	}

	/**
	 * Check if user can edit project
	 *
	 * @param string $userId
	 * @param int $projectId
	 * @return bool
	 */
	public function canUserEditProject(string $userId, int $projectId): bool
	{
		$project = $this->getProject($projectId);
		if (!$project) {
			return false;
		}

		// Cannot edit completed or cancelled projects
		if ($project->isCompleted() || $project->isCancelled()) {
			return false;
		}

		// Admin can edit all projects
		if ($this->isUserAdmin($userId)) {
			return true;
		}

		// Project creator can edit
		if ($project->getCreatedBy() === $userId) {
			return true;
		}

		// Project managers can edit
		$member = $this->getProjectMember($projectId, $userId);
		return $member && $member->getRole() === 'Project Manager';
	}


	/**
	 * Check if user is admin
	 *
	 * @param string $userId
	 * @return bool
	 */
	private function isUserAdmin(string $userId): bool
	{
		$user = $this->userManager->get($userId);
		if (!$user) {
			return false;
		}

		// Treat default admin users and root as admins
		$uid = $user->getUID();
		if (in_array($uid, ['admin', 'administrator', 'root'], true)) {
			return true;
		}

		return false;
	}

	/**
	 * Get projects for specific user
	 *
	 * @param string $userId
	 * @return array
	 */
	public function getProjectsByUser(string $userId): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('p.*')
			->from('projects', 'p')
			->innerJoin('p', 'project_members', 'pm', $qb->expr()->eq('p.id', 'pm.project_id'))
			->where($qb->expr()->eq('pm.user_id', $qb->createNamedParameter($userId)))
			->orderBy('p.created_at', 'DESC');

		$result = $qb->execute();
		$projects = [];

		while ($row = $result->fetch()) {
			$projects[] = $this->mapRowToProject($row);
		}

		$result->closeCursor();

		return $projects;
	}

	/**
	 * Get projects created by specific user
	 *
	 * @param string $userId
	 * @return array
	 */
	public function getProjectsCreatedByUser(string $userId): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('p.*')
			->from('projects', 'p')
			->where($qb->expr()->eq('p.created_by', $qb->createNamedParameter($userId)))
			->orderBy('p.created_at', 'DESC');

		$result = $qb->execute();
		$projects = [];

		while ($row = $result->fetch()) {
			$projects[] = $this->mapRowToProject($row);
		}

		$result->closeCursor();

		return $projects;
	}

	/**
	 * Get all projects in the system
	 *
	 * @return array
	 */
	public function getAllProjects(): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('p.*', 'c.name as customer_name')
			->from('projects', 'p')
			->leftJoin('p', 'customers', 'c', $qb->expr()->eq('p.customer_id', 'c.id'))
			->orderBy('p.created_at', 'DESC');

		$result = $qb->execute();
		$projects = [];

		while ($row = $result->fetch()) {
			$projects[] = $this->mapRowToProject($row);
		}

		$result->closeCursor();

		return $projects;
	}

	/**
	 * Add team member to project
	 *
	 * @param int $projectId
	 * @param string $userId
	 * @param string $role
	 * @param float|null $hourlyRate
	 * @return ProjectMember
	 * @throws \Exception
	 */
	public function addTeamMember(int $projectId, string $userId, string $role, ?float $hourlyRate = null): ProjectMember
	{
		$project = $this->getProject($projectId);
		if (!$project) {
			throw new \Exception('Project not found');
		}

		// Validate that user exists
		$user = $this->userManager->get($userId);
		if (!$user) {
			throw new \Exception('User not found');
		}

		// Validate role
		$validRoles = ['Project Manager', 'Developer', 'Tester', 'Consultant'];
		if (!in_array($role, $validRoles)) {
			throw new \Exception('Invalid role value');
		}

		// Check if user is already assigned
		$existingMember = $this->getProjectMember($projectId, $userId);
		if ($existingMember) {
			throw new \Exception('User is already assigned to this project');
		}

		$user = $this->userSession->getUser();
		if (!$user) {
			throw new \Exception('User not authenticated');
		}

		$member = new ProjectMember();
		$member->setProjectId($projectId);
		$member->setUserId($userId);
		$member->setRole($role);
		$member->setHourlyRate($hourlyRate);
		$member->setAssignedAt(new \DateTime());
		$member->setAssignedBy($user->getUID());

		$qb = $this->db->getQueryBuilder();
		$qb->insert('project_members')
			->values([
				'project_id' => $qb->createNamedParameter($member->getProjectId(), IQueryBuilder::PARAM_INT),
				'user_id' => $qb->createNamedParameter($member->getUserId()),
				'role' => $qb->createNamedParameter($member->getRole()),
				'hourly_rate' => $qb->createNamedParameter($member->getHourlyRate()),
				'assigned_at' => $qb->createNamedParameter($member->getAssignedAt()),
				'assigned_by' => $qb->createNamedParameter($member->getAssignedBy()),
			]);

		$qb->execute();
		$member->setId($this->db->lastInsertId('project_members'));

		return $member;
	}

	/**
	 * Remove team member from project
	 *
	 * @param int $projectId
	 * @param string $userId
	 * @return bool
	 * @throws \Exception
	 */
	public function removeTeamMember(int $projectId, string $userId): bool
	{
		$member = $this->getProjectMember($projectId, $userId);
		if (!$member) {
			throw new \Exception('Team member not found');
		}

		$qb = $this->db->getQueryBuilder();
		$qb->delete('project_members')
			->where($qb->expr()->andX(
				$qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)),
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId))
			));

		$qb->execute();

		return true;
	}

	/**
	 * Get project team members
	 *
	 * @param int $projectId
	 * @return array
	 */
	public function getProjectTeam(int $projectId): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('project_members')
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)))
			->orderBy('role', 'ASC');

		$result = $qb->execute();
		$members = [];

		while ($row = $result->fetch()) {
			$members[] = $this->mapRowToProjectMember($row);
		}

		$result->closeCursor();

		return $members;
	}

	/**
	 * Get specific project member
	 *
	 * @param int $projectId
	 * @param string $userId
	 * @return ProjectMember|null
	 */
	private function getProjectMember(int $projectId, string $userId): ?ProjectMember
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('project_members')
			->where($qb->expr()->andX(
				$qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)),
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId))
			));

		$result = $qb->execute();
		$row = $result->fetch();
		$result->closeCursor();

		if (!$row) {
			return null;
		}

		return $this->mapRowToProjectMember($row);
	}

	/**
	 * Change project status with validation
	 *
	 * @param int $projectId
	 * @param string $newStatus
	 * @return Project
	 * @throws \Exception
	 */
	public function changeProjectStatus(int $projectId, string $newStatus): Project
	{
		$project = $this->getProject($projectId);
		if (!$project) {
			throw new \Exception('Project not found');
		}

		// Validate status transition
		$currentStatus = $project->getStatus();
		$validTransitions = [
			'Active' => ['On Hold', 'Completed', 'Cancelled'],
			'On Hold' => ['Active', 'Completed', 'Cancelled'],
			'Completed' => [], // No transitions allowed from completed
			'Cancelled' => []  // No transitions allowed from cancelled
		];

		if (!isset($validTransitions[$currentStatus]) || !in_array($newStatus, $validTransitions[$currentStatus])) {
			throw new \Exception("Invalid status transition from '{$currentStatus}' to '{$newStatus}'");
		}

		// Validate new status value
		$validStatuses = ['Active', 'On Hold', 'Completed', 'Cancelled'];
		if (!in_array($newStatus, $validStatuses)) {
			throw new \Exception('Invalid status value');
		}

		$user = $this->userSession->getUser();
		if (!$user) {
			throw new \Exception('User not authenticated');
		}

		$qb = $this->db->getQueryBuilder();
		$qb->update('projects')
			->set('status', $qb->createNamedParameter($newStatus))
			->set('updated_at', $qb->createNamedParameter((new \DateTime())->format('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)));

		$qb->execute();

		$project->setStatus($newStatus);
		$project->setUpdatedAt(new \DateTime());



		return $project;
	}

	/**
	 * Update team member role
	 *
	 * @param int $projectId
	 * @param string $userId
	 * @param string $newRole
	 * @return ProjectMember
	 * @throws \Exception
	 */
	public function updateTeamMemberRole(int $projectId, string $userId, string $newRole): ProjectMember
	{
		$member = $this->getProjectMember($projectId, $userId);
		if (!$member) {
			throw new \Exception('Team member not found');
		}

		// Validate role
		$validRoles = ['Project Manager', 'Developer', 'Tester', 'Consultant'];
		if (!in_array($newRole, $validRoles)) {
			throw new \Exception('Invalid role value');
		}

		$qb = $this->db->getQueryBuilder();
		$qb->update('project_members')
			->set('role', $qb->createNamedParameter($newRole))
			->where($qb->expr()->andX(
				$qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)),
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId))
			));

		$qb->execute();

		$member->setRole($newRole);



		return $member;
	}

	/**
	 * Get all projects a user is assigned to
	 *
	 * @param string $userId
	 * @return array
	 */
	public function getUserProjects(string $userId): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('p.*')
			->from('projects', 'p')
			->innerJoin('p', 'project_members', 'pm', $qb->expr()->eq('p.id', 'pm.project_id'))
			->where($qb->expr()->eq('pm.user_id', $qb->createNamedParameter($userId)))
			->orderBy('p.created_at', 'DESC');

		$result = $qb->execute();
		$projects = [];

		while ($row = $result->fetch()) {
			$projects[] = $this->mapRowToProject($row);
		}

		$result->closeCursor();

		return $projects;
	}

	/**
	 * Get all team members for a manager across all projects they manage
	 *
	 * @param string $managerId
	 * @return array
	 */
	public function getTeamMembers(string $managerId): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('pm.user_id', 'pm.role', 'pm.hourly_rate', 'p.name as project_name')
			->from('project_members', 'pm')
			->innerJoin('pm', 'projects', 'p', $qb->expr()->eq('pm.project_id', 'p.id'))
			->where($qb->expr()->eq('pm.user_id', $qb->createNamedParameter($managerId)))
			->andWhere($qb->expr()->eq('pm.role', $qb->createNamedParameter('Project Manager')));

		$result = $qb->execute();
		$managedProjects = [];

		while ($row = $result->fetch()) {
			$managedProjects[] = $row['project_id'];
		}

		$result->closeCursor();

		if (empty($managedProjects)) {
			return [];
		}

		// Get all team members from projects managed by this manager
		$qb = $this->db->getQueryBuilder();
		$qb->select('pm.user_id', 'pm.role', 'pm.hourly_rate', 'p.name as project_name')
			->from('project_members', 'pm')
			->innerJoin('pm', 'projects', 'p', $qb->expr()->eq('pm.project_id', 'p.id'))
			->where($qb->expr()->in('pm.project_id', $qb->createNamedParameter($managedProjects, IQueryBuilder::PARAM_INT_ARRAY)))
			->andWhere($qb->expr()->neq('pm.user_id', $qb->createNamedParameter($managerId)))
			->orderBy('pm.user_id', 'ASC');

		$result = $qb->execute();
		$teamMembers = [];

		while ($row = $result->fetch()) {
			$userId = $row['user_id'];
			if (!isset($teamMembers[$userId])) {
				$teamMembers[$userId] = [
					'user_id' => $userId,
					'user_name' => $userId, // In a real implementation, you'd get the display name
					'role' => $row['role'],
					'hourly_rate' => $row['hourly_rate'],
					'projects' => []
				];
			}
			$teamMembers[$userId]['projects'][] = $row['project_name'];
		}

		$result->closeCursor();

		return array_values($teamMembers);
	}

	/**
	 * Validate project data
	 *
	 * @param array $data
	 * @param int|null $projectId
	 * @throws \Exception
	 */
	private function validateProjectData(array $data, ?int $projectId = null): void
	{
		$requiredFields = ['name', 'short_description', 'customer_id'];

		foreach ($requiredFields as $field) {
			if (!isset($data[$field]) || empty($data[$field])) {
				throw new \Exception("Field '{$field}' is required");
			}
		}

		if (strlen($data['name']) > 100) {
			throw new \Exception('Project name must be 100 characters or less');
		}

		if (strlen($data['short_description']) > 500) {
			throw new \Exception('Short description must be 500 characters or less');
		}

		if (isset($data['detailed_description']) && strlen($data['detailed_description']) > 2000) {
			throw new \Exception('Detailed description must be 2000 characters or less');
		}

		// Validate budget and rate fields (optional but if provided, must be valid)
		if (isset($data['hourly_rate']) && !empty($data['hourly_rate'])) {
			if (!is_numeric($data['hourly_rate']) || $data['hourly_rate'] < 0) {
				throw new \Exception('Hourly rate must be a non-negative number');
			}
		}

		if (isset($data['total_budget']) && !empty($data['total_budget'])) {
			if (!is_numeric($data['total_budget']) || $data['total_budget'] < 0) {
				throw new \Exception('Total budget must be a non-negative number');
			}
		}

		// If both budget and rate are provided, validate the combination
		if (
			isset($data['hourly_rate']) && !empty($data['hourly_rate']) &&
			isset($data['total_budget']) && !empty($data['total_budget'])
		) {
			$availableHours = $this->calculator->calculateAvailableHours($data['total_budget'], $data['hourly_rate']);
			if ($availableHours < 0.5) {
				throw new \Exception('Budget too low for the specified hourly rate');
			}
		}

		// Validate date range if both dates are provided
		if (
			isset($data['start_date']) && !empty($data['start_date']) &&
			isset($data['end_date']) && !empty($data['end_date'])
		) {
			$startDate = new \DateTime($data['start_date']);
			$endDate = new \DateTime($data['end_date']);
			if ($endDate <= $startDate) {
				throw new \Exception('End date must be after start date');
			}
		}

		$validStatuses = ['Active', 'On Hold', 'Completed', 'Cancelled'];
		if (isset($data['status']) && !in_array($data['status'], $validStatuses)) {
			throw new \Exception('Invalid status value');
		}

		$validPriorities = ['Low', 'Medium', 'High', 'Critical'];
		if (isset($data['priority']) && !in_array($data['priority'], $validPriorities)) {
			throw new \Exception('Invalid priority value');
		}

		$validProjectTypes = ['client', 'admin', 'sales', 'customer', 'product', 'meeting', 'internal', 'research', 'training', 'other'];
		if (isset($data['project_type']) && !in_array($data['project_type'], $validProjectTypes)) {
			throw new \Exception('Invalid project type value');
		}
	}

	/**
	 * Map database row to Project entity
	 *
	 * @param array $row
	 * @return Project
	 */
	private function mapRowToProject(array $row): Project
	{
		$project = new Project();
		$project->setId($row['id']);
		$project->setName($row['name']);
		$project->setShortDescription($row['short_description']);
		$project->setDetailedDescription($row['detailed_description']);
		$project->setCustomerId($row['customer_id']);
		$project->setCustomerName($row['customer_name'] ?? '');
		$project->setHourlyRate($row['hourly_rate']);
		$project->setTotalBudget($row['total_budget']);
		$project->setAvailableHours($row['available_hours']);
		$project->setCategory($row['category']);
		$project->setPriority($row['priority']);
		$project->setStatus($row['status']);
		$project->setStartDate($row['start_date'] ? new \DateTime($row['start_date']) : null);
		$project->setEndDate($row['end_date'] ? new \DateTime($row['end_date']) : null);
		$project->setTags($row['tags']);
		$project->setProjectType($row['project_type'] ?? 'client');
		$project->setCreatedBy($row['created_by']);
		$project->setCreatedAt(new \DateTime($row['created_at']));
		$project->setUpdatedAt(new \DateTime($row['updated_at']));

		return $project;
	}

	/**
	 * Check if a column exists in a table
	 *
	 * @param string $table
	 * @param string $column
	 * @return bool
	 */
	private function columnExists(string $table, string $column): bool
	{
		try {
			// Use PRAGMA table_info for SQLite to check column existence
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from('sqlite_master')
				->where($qb->expr()->eq('type', $qb->createNamedParameter('table')))
				->andWhere($qb->expr()->eq('name', $qb->createNamedParameter($table)));

			$result = $qb->execute();
			$row = $result->fetch();
			$result->closeCursor();

			if (!$row) {
				return false;
			}

			// Get table schema
			$schema = $row['sql'];

			// Check if column exists in schema
			return strpos($schema, "`{$column}`") !== false || strpos($schema, "'{$column}'") !== false || strpos($schema, "{$column}") !== false;
		} catch (\Exception $e) {
			// Fallback: try to select the column directly
			try {
				$qb = $this->db->getQueryBuilder();
				$qb->select($column)
					->from($table)
					->setMaxResults(1);
				$result = $qb->execute();
				$result->closeCursor();
				return true;
			} catch (\Exception $e2) {
				return false;
			}
		}
	}

	/**
	 * Map database row to ProjectMember entity
	 *
	 * @param array $row
	 * @return ProjectMember
	 */
	private function mapRowToProjectMember(array $row): ProjectMember
	{
		$member = new ProjectMember();
		$member->setId($row['id']);
		$member->setProjectId($row['project_id']);
		$member->setUserId($row['user_id']);
		$member->setRole($row['role']);
		$member->setHourlyRate($row['hourly_rate']);
		$member->setAssignedAt(new \DateTime($row['assigned_at']));
		$member->setAssignedBy($row['assigned_by']);

		return $member;
	}

	/**
	 * Get total project count
	 *
	 * @return int
	 */
	public function getTotalProjectCount(): int
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*)'))
			->from('projects');

		$result = $qb->execute();
		$count = $result->fetchOne();
		$result->closeCursor();

		return (int) $count;
	}

	/**
	 * Parse European date format (dd.mm.yyyy) to DateTime object
	 *
	 * @param string|null $dateString
	 * @return \DateTime|null
	 */
	private function parseEuropeanDate(?string $dateString): ?\DateTime
	{
		if (empty($dateString)) {
			return null;
		}

		// Handle both European format (dd.mm.yyyy) and ISO format (yyyy-mm-dd)
		if (preg_match('/^(\d{2})\.(\d{2})\.(\d{4})$/', $dateString, $matches)) {
			// European format: dd.mm.yyyy
			$day = $matches[1];
			$month = $matches[2];
			$year = $matches[3];
			$isoDate = $year . '-' . $month . '-' . $day;
		} elseif (preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $dateString, $matches)) {
			// ISO format: yyyy-mm-dd (already in correct format)
			$isoDate = $dateString;
		} else {
			// Invalid format, return null
			return null;
		}

		try {
			return new \DateTime($isoDate);
		} catch (\Exception $e) {
			return null;
		}
	}
}
