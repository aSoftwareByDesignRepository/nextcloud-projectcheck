<?php

declare(strict_types=1);

/**
 * ProjectService for the projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Db\ProjectMember;
use OCA\ProjectCheck\Db\ProjectMapper;
use OCA\ProjectCheck\Util\ProjectCalculator;
use OCA\ProjectCheck\Util\SafeDateTime;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;
use OCP\IUserSession;
use OCP\IUserManager;
use OCP\IConfig;
use OCP\IGroupManager;

/**
 * Class ProjectService
 *
 * @package OCA\ProjectControl\Service
 */
class ProjectService
{
	/** @var list<string> All stored project status values */
	public const PROJECT_STATUSES = ['Active', 'On Hold', 'Completed', 'Cancelled', 'Archived'];
	public const DEFAULT_MEMBER_ROLE = 'Member';

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

	/** @var IGroupManager */
	private $groupManager;

	/** @var ProjectMapper|null */
	private $projectMapper;

	/** @var BudgetService|null */
	private $budgetService;

	/** @var AccessControlService|null */
	private $accessControl;

	/**
	 * ProjectService constructor
	 *
	 * @param IDBConnection $db
	 * @param IUserSession $userSession
	 * @param IUserManager $userManager
	 * @param IConfig $config
	 * @param IGroupManager $groupManager
	 * @param ProjectMapper|null $projectMapper
	 * @param BudgetService|null $budgetService
	 * @param AccessControlService|null $accessControl
	 */
	public function __construct(IDBConnection $db, IUserSession $userSession, IUserManager $userManager, IConfig $config, IGroupManager $groupManager, ?ProjectMapper $projectMapper = null, ?BudgetService $budgetService = null, ?AccessControlService $accessControl = null)
	{
		$this->db = $db;
		$this->userSession = $userSession;
		$this->userManager = $userManager;
		$this->config = $config;
		$this->groupManager = $groupManager;
		$this->projectMapper = $projectMapper ?? new ProjectMapper($db);
		$this->budgetService = $budgetService;
		$this->accessControl = $accessControl;
		$this->calculator = new ProjectCalculator();
	}

	/**
	 * null = all projects (system or app org admin / NC group admin)
	 * otherwise: project IDs the user may use for scoped stats
	 *
	 * @return list<int>|null
	 */
	public function getAccessibleProjectIdListForUser(string $userId): ?array
	{
		if ($this->hasGlobalProjectAccess($userId)) {
			return null;
		}
		$ids = [];
		$qb = $this->db->getQueryBuilder();
		$qb->select('p.id')
			->from('projects', 'p')
			->where($qb->expr()->eq('p.created_by', $qb->createNamedParameter($userId)));
		$rs = $qb->executeQuery();
		while ($r = $rs->fetch()) {
			$ids[] = (int) $r['id'];
		}
		$rs->closeCursor();
		$qb = $this->db->getQueryBuilder();
		$qb->select('p2.id')
			->from('project_members', 'm')
			->innerJoin('m', 'projects', 'p2', $qb->expr()->eq('m.project_id', 'p2.id'))
			->where($qb->expr()->eq('m.user_id', $qb->createNamedParameter($userId)))
			->andWhere($this->buildActiveMemberExpression($qb, 'm'));
		$rs = $qb->executeQuery();
		while ($r = $rs->fetch()) {
			$ids[] = (int) $r['id'];
		}
		$rs->closeCursor();
		$ids = array_values(array_unique($ids));
		return $ids;
	}

	/**
	 * @param list<int> $ids
	 * @return list<Project>
	 */
	public function getProjectsByIdList(array $ids): array
	{
		if ($ids === []) {
			return [];
		}
		$out = [];
		foreach ($ids as $id) {
			$p = $this->getProject((int) $id);
			if ($p !== null) {
				$out[] = $p;
			}
		}
		return $out;
	}

	/**
	 * getProjects() result restricted to projects the user may access (time entry pickers, etc.)
	 *
	 * @param array<string, mixed> $filters
	 * @return list<Project>
	 */
	public function getProjectsForUserTimeEntry(string $userId, array $filters = []): array
	{
		$all = $this->getProjects($filters);
		$out = [];
		foreach ($all as $p) {
			if ($this->canUserAccessProject($userId, (int) $p->getId())) {
				$out[] = $p;
			}
		}
		return $out;
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

		$requestedStatus = $data['status'] ?? $defaultStatus;
		if ($requestedStatus === 'Archived') {
			throw new \Exception('New projects cannot be created as archived. Create an active project and use “Archive” from the project view.');
		}

		// Calculate available hours from budget and rate (if provided)
		$hourlyRate = isset($data['hourly_rate']) && $data['hourly_rate'] !== '' ? (float)$data['hourly_rate'] : (float)$defaultHourlyRate;
		$totalBudget = isset($data['total_budget']) && $data['total_budget'] !== '' ? (float)$data['total_budget'] : 0.0;

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
		$project->setCategory($data['category'] ?? '');
		$project->setPriority($data['priority'] ?? $defaultPriority);
		$project->setStatus($requestedStatus);
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
			'category' => $qb->createNamedParameter($project->getCategory()),
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
			$qb->executeStatement();
		} catch (\Exception $e) {
			// If the error is about project_type column not existing, try again without it
			if (strpos($e->getMessage(), 'project_type') !== false || strpos($e->getMessage(), 'Unknown column') !== false) {
				// Remove project_type from the insert and try again
				unset($values['project_type']);
				$qb = $this->db->getQueryBuilder();
				$qb->insert('projects')->values($values);
				$qb->executeStatement();
			} else {
				// Re-throw if it's a different error
				throw $e;
			}
		}
		$project->setId($this->db->lastInsertId('projects'));

		// Ensure the creator is also stored as an active project member so that
		// membership-based queries (team lists, scoped stats, etc.) treat them as
		// part of the project, not just as the creator record.
		try {
			// Use the public API so all invariants (editable state, account checks)
			// are applied consistently. Swallow failures so a project can still be
			// created even if the membership insert hits a legacy edge case.
			$this->addTeamMember((int) $project->getId(), $userId, self::DEFAULT_MEMBER_ROLE, null);
		} catch (\Throwable $e) {
			// Intentionally ignore; access control still treats the creator as
			// having project access via created_by even if the membership row is
			// missing.
		}

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

		$result = $qb->executeQuery();
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
		if (isset($filters['status']) && $filters['status'] === 'all') {
			// Explicit "all" means no status filter
			$filters['status'] = '';
		}

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

		// Optional hard project-id scope (used for per-user visibility scoping).
		if (array_key_exists('id_in', $filters) && is_array($filters['id_in'])) {
			if ($filters['id_in'] === []) {
				// Explicitly empty scope: no projects are visible.
				$qb->andWhere('1 = 0');
			} else {
				$qb->andWhere(
					$qb->expr()->in('p.id', $qb->createNamedParameter($filters['id_in'], IQueryBuilder::PARAM_INT_ARRAY))
				);
			}
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

		// Apply sorting (allowlisted for security - no SQL injection)
		$sortColumnMap = [
			'name' => 'p.name',
			'customer_name' => 'c.name',
			'project_type' => 'p.project_type',
			'status' => 'p.status',
			'created_at' => 'p.created_at',
		];
		$requestedSort = $filters['sort'] ?? 'created_at';
		$sortField = $sortColumnMap[$requestedSort] ?? 'p.created_at';
		// project_type column may not exist on older installations
		if ($requestedSort === 'project_type' && !$this->columnExists('projects', 'project_type')) {
			$sortField = 'p.created_at';
		}
		$requestedDirection = strtoupper((string)($filters['direction'] ?? 'DESC'));
		$sortDirection = ($requestedDirection === 'ASC') ? 'ASC' : 'DESC';
		$qb->orderBy($sortField, $sortDirection);

		$result = $qb->executeQuery();
		$projects = [];

		while ($row = $result->fetch()) {
			$projects[] = $this->mapRowToProject($row);
		}

		$result->closeCursor();

		return $projects;
	}

	/**
	 * Get projects for list view: filtered, enriched with budget info, and sorted.
	 * Handles both DB-sortable columns and computed columns (remaining_budget, progress).
	 *
	 * @param array $filters search, status, priority, project_type, customer_id, limit, offset
	 * @param string $sort User-facing: name, customer, type, status, remaining_budget, progress
	 * @param string $direction asc|desc
	 * @param string $userId
	 * @return array<int, array{project: Project, budgetInfo: array}>
	 */
	public function getProjectsForListView(array $filters, string $sort, string $direction, string $userId): array
	{
		if ($this->budgetService === null) {
			throw new \RuntimeException('BudgetService is required for getProjectsForListView');
		}

		$sortToDbColumn = [
			'name' => 'name',
			'customer' => 'customer_name',
			'type' => 'project_type',
			'status' => 'status',
		];
		$dbSortable = isset($sortToDbColumn[$sort]);
		$dbSort = $dbSortable ? $sortToDbColumn[$sort] : 'created_at';
		$dbDirection = ($direction === 'desc') ? 'DESC' : 'ASC';

		$dbFilters = array_merge($filters, [
			'sort' => $dbSort,
			'direction' => $dbDirection,
		]);

		// Restrict the project set to what the user may actually access, unless
		// they have global project access (system admin or app org admin).
		$accessibleIds = $this->getAccessibleProjectIdListForUser($userId);
		if ($accessibleIds !== null) {
			$dbFilters['id_in'] = $accessibleIds;
		}

		// For computed columns, use created_at as placeholder for initial DB sort
		if ($sort === 'remaining_budget' || $sort === 'progress') {
			$dbFilters['sort'] = 'created_at';
			$dbFilters['direction'] = 'DESC';
		}

		$projects = $this->getProjects($dbFilters);
		$enriched = $this->enrichProjectsWithBudgetInfo($projects, $userId);

		// For computed columns, sort in PHP after enrichment
		if ($sort === 'remaining_budget' || $sort === 'progress') {
			$computeRemaining = static function (array $item): float {
				if (isset($item['budgetInfo']['remaining_budget'])) {
					return (float)$item['budgetInfo']['remaining_budget'];
				}
				if (isset($item['project']) && method_exists($item['project'], 'getTotalBudget')) {
					$total = $item['project']->getTotalBudget();
					return $total !== null ? (float)$total : PHP_FLOAT_MAX;
				}
				return PHP_FLOAT_MAX;
			};
			$computeProgress = static function (array $item): float {
				return isset($item['budgetInfo']['consumption_percentage'])
					? (float)$item['budgetInfo']['consumption_percentage']
					: 0.0;
			};
			usort($enriched, static function (array $a, array $b) use ($sort, $direction, $computeRemaining, $computeProgress): int {
				$valA = $sort === 'remaining_budget' ? $computeRemaining($a) : $computeProgress($a);
				$valB = $sort === 'remaining_budget' ? $computeRemaining($b) : $computeProgress($b);
				if ($valA === $valB) {
					return 0;
				}
				$cmp = $valA < $valB ? -1 : 1;
				return $direction === 'desc' ? -$cmp : $cmp;
			});
		}

		return $enriched;
	}

	/**
	 * Enrich projects with budget information.
	 *
	 * @param array<Project> $projects
	 * @param string $userId
	 * @return array<int, array{project: Project, budgetInfo: array}>
	 */
	public function enrichProjectsWithBudgetInfo(array $projects, string $userId): array
	{
		if ($this->budgetService === null) {
			throw new \RuntimeException('BudgetService is required for enrichProjectsWithBudgetInfo');
		}

		$enriched = [];
		foreach ($projects as $project) {
			try {
				$budgetInfo = $this->budgetService->getProjectBudgetInfo($project, $userId);
				$enriched[] = [
					'project' => $project,
					'budgetInfo' => $budgetInfo,
					'canEdit' => $this->canUserEditProject($userId, $project->getId()),
				];
			} catch (\Exception $e) {
				$enriched[] = [
					'project' => $project,
					'budgetInfo' => [
						'total_budget' => $project->getTotalBudget() ?? 0,
						'used_budget' => 0,
						'remaining_budget' => $project->getTotalBudget() ?? 0,
						'consumption_percentage' => 0,
						'warning_level' => 'safe',
						'used_hours' => 0,
					],
					'canEdit' => $this->canUserEditProject($userId, $project->getId()),
				];
			}
		}
		return $enriched;
	}

	/**
	 * Count projects with filters (status, customer, priority, project_type, search)
	 *
	 * @param array $filters
	 * @return int
	 */
	public function countProjects(array $filters = []): int
	{
		$countFilters = $filters;
		unset($countFilters['limit'], $countFilters['offset'], $countFilters['sort'], $countFilters['direction']);
		// Fallback if mapper was not injected (legacy DI)
		if (!$this->projectMapper) {
			$this->projectMapper = new \OCA\ProjectCheck\Db\ProjectMapper($this->db);
		}

		return $this->projectMapper->countWithFilters($countFilters);
	}

	/**
	 * Count projects visible to a specific user (creator, member, or global admin/app-admin).
	 *
	 * @param array $filters
	 */
	public function countProjectsForUser(array $filters, string $userId): int
	{
		$countFilters = $filters;
		unset($countFilters['limit'], $countFilters['offset'], $countFilters['sort'], $countFilters['direction']);

		$ids = $this->getAccessibleProjectIdListForUser($userId);
		if ($ids !== null) {
			$countFilters['id_in'] = $ids;
		}

		if (!$this->projectMapper) {
			$this->projectMapper = new \OCA\ProjectCheck\Db\ProjectMapper($this->db);
		}

		return $this->projectMapper->countWithFilters($countFilters);
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

		if ($project->isCompleted() || $project->isCancelled() || $project->isArchived()) {
			throw new \Exception('Cannot edit completed, cancelled, or archived projects. To work on an archived project again, reactivate it from the project page.');
		}

		$previousStatus = $project->getStatus();
		// Merge incoming data with existing project to allow partial updates (e.g. status-only or rate-only changes)
		$data = $this->mergeWithExistingProjectData($project, $data);

		$this->validateProjectData($data, $id);

		if (isset($data['status']) && (string)$data['status'] !== (string)$previousStatus) {
			$this->assertStatusTransitionAllowed((string)$previousStatus, (string)$data['status']);
		}

		// Recalculate available hours if budget or rate changed
		if (isset($data['total_budget']) || isset($data['hourly_rate'])) {
			$budget = isset($data['total_budget']) && $data['total_budget'] !== '' ? (float)$data['total_budget'] : (float)$project->getTotalBudget();
			$rate = isset($data['hourly_rate']) && $data['hourly_rate'] !== '' ? (float)$data['hourly_rate'] : (float)$project->getHourlyRate();

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
			'category' => 'category',
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
			$qb->executeStatement();
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
					'category' => 'category',
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
				$qb->executeStatement();
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
	/**
	 * Maintenance only: update projects.updated_at (does not run user-edit or workflow rules).
	 * Used by cron/CLI; never expose as a user-facing HTTP action.
	 */
	public function touchProjectRowTimestampForMaintenance(int $id): void
	{
		$qb = $this->db->getQueryBuilder();
		$qb->update('projects')
			->set('updated_at', $qb->createNamedParameter((new \DateTime())->format('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * System event: force project to Cancelled (e.g. when owner account is removed).
	 * Bypasses normal status transitions. Does not apply to already Completed/Cancelled.
	 */
	public function cancelProjectForUserAccountRemoval(int $id): void
	{
		$project = $this->getProject($id);
		if (!$project) {
			return;
		}
		if ($project->isCompleted() || $project->isCancelled()) {
			return;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->update('projects')
			->set('status', $qb->createNamedParameter('Cancelled'))
			->set('updated_at', $qb->createNamedParameter((new \DateTime())->format('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	public function deleteProject(int $id): bool
	{
		$project = $this->getProject($id);
		if (!$project) {
			throw new \Exception('Project not found');
		}

		// Check if project has active team members (former/archived do not block deletion)
		$activeTeam = $this->getProjectTeamGrouped($id)['active'] ?? [];
		if ($activeTeam !== [] && $project->isActive()) {
			throw new \Exception('Cannot delete project with active team members');
		}

		// Start transaction for data integrity
		$this->db->beginTransaction();

		try {
			// Delete related time entries first
			$qb = $this->db->getQueryBuilder();
			$qb->delete('time_entries')
				->where($qb->expr()->eq('project_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
			$qb->executeStatement();

			// Delete project team members
			$qb = $this->db->getQueryBuilder();
			$qb->delete('project_members')
				->where($qb->expr()->eq('project_id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
			$qb->executeStatement();

			// Delete the project itself
			$qb = $this->db->getQueryBuilder();
			$qb->delete('projects')
				->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)));
			$qb->executeStatement();

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

		// System and app-level admins can delete all projects.
		if ($this->hasGlobalProjectAccess($userId)) {
			return true;
		}

		// Project creator can delete
		if ($project->getCreatedBy() === $userId) {
			return true;
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
	 * Convenience helper: return projects visible to a given user, with arbitrary filters.
	 *
	 * @param array<string, mixed> $filters
	 * @return list<Project>
	 */
	public function getUserScopedProjects(string $userId, array $filters = []): array
	{
		$ids = $this->getAccessibleProjectIdListForUser($userId);
		if ($ids !== null) {
			$filters['id_in'] = $ids;
		}
		return $this->getProjects($filters);
	}

	/**
	 * Project IDs for a customer, intersected with what the user may access.
	 *
	 * @return list<int>|null null means "all projects for this customer" for global viewers
	 */
	public function getUserScopedProjectIdsForCustomer(string $userId, int $customerId): ?array
	{
		$accessibleIds = $this->getAccessibleProjectIdListForUser($userId);
		if ($accessibleIds === null) {
			return null;
		}

		$customerProjectIds = [];
		foreach ($this->getProjects(['customer_id' => $customerId]) as $project) {
			$customerProjectIds[] = (int) $project->getId();
		}

		if ($customerProjectIds === []) {
			return [];
		}

		$allowedMap = array_fill_keys($accessibleIds, true);
		$scoped = [];
		foreach ($customerProjectIds as $projectId) {
			if (isset($allowedMap[$projectId])) {
				$scoped[] = $projectId;
			}
		}

		return $scoped;
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

		// System and app-level admins can access all projects.
		if ($this->hasGlobalProjectAccess($userId)) {
			return true;
		}

		// Project creator can access
		if ($project->getCreatedBy() === $userId) {
			return true;
		}

		// Team members can access
		$member = $this->getProjectMemberActive($projectId, $userId);
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

		// Cannot edit completed, cancelled, or archived project metadata (reactivation is a separate action)
		if ($project->isCompleted() || $project->isCancelled() || $project->isArchived()) {
			return false;
		}

		// System and app-level admins can edit all projects
		if ($this->hasGlobalProjectAccess($userId)) {
			return true;
		}

		// Project creator can edit
		if ($project->getCreatedBy() === $userId) {
			return true;
		}

		return false;
	}

	/**
	 * Unified global-access check: Nextcloud system admins and delegated app administrators.
	 */
	private function hasGlobalProjectAccess(string $userId): bool
	{
		if ($this->isUserAdmin($userId)) {
			return true;
		}
		if ($this->accessControl === null) {
			return false;
		}
		return $this->accessControl->isSystemAdministrator($userId)
			|| $this->accessControl->canManageAppConfiguration($userId);
	}

	/**
	 * Map of allowed status transitions (single source of truth for workflow)
	 *
	 * @return array<string, list<string>>
	 */
	private function getStatusTransitionMap(): array
	{
		return [
			'Active' => ['On Hold', 'Completed', 'Cancelled', 'Archived'],
			'On Hold' => ['Active', 'Completed', 'Cancelled', 'Archived'],
			'Archived' => ['Active', 'On Hold'],
			'Completed' => [],
			'Cancelled' => [],
		];
	}

	/**
	 * @param string $from
	 * @param string $to
	 * @return bool
	 */
	public function isStatusTransitionAllowed(string $from, string $to): bool
	{
		$map = $this->getStatusTransitionMap();
		if (!isset($map[$from])) {
			return false;
		}
		return in_array($to, $map[$from], true);
	}

	/**
	 * @param string $from
	 * @param string $to
	 * @throws \Exception
	 */
	private function assertStatusTransitionAllowed(string $from, string $to): void
	{
		if (!$this->isStatusTransitionAllowed($from, $to)) {
			throw new \Exception("Invalid status transition from '{$from}' to '{$to}'");
		}
	}

	/**
	 * Status values a project may change to from its current state
	 *
	 * @param string $currentStatus
	 * @return list<string>
	 */
	public function getAllowedStatusTargets(string $currentStatus): array
	{
		$map = $this->getStatusTransitionMap();

		return $map[$currentStatus] ?? [];
	}

	/**
	 * Whether the user may change this project's status (including reactivating from Archived)
	 *
	 * @param string $userId
	 * @param int $projectId
	 * @return bool
	 */
	public function canUserChangeProjectStatus(string $userId, int $projectId): bool
	{
		$project = $this->getProject($projectId);
		if (!$project) {
			return false;
		}

		// No transitions out of final states
		if ($project->isCompleted() || $project->isCancelled()) {
			return false;
		}

		// Archiving and other transitions use the same authority as project edit (creator / admin / project manager)
		// Reactivating an archived project uses the same rules
		if ($project->isArchived()) {
			// Same privilege level as deleting or fully managing the project
			return $this->canUserDeleteProject($userId, $projectId);
		}

		return $this->canUserEditProject($userId, $projectId);
	}

	/**
	 * Check if user is admin
	 *
	 * @param string $userId
	 * @return bool
	 */
	private function isUserAdmin(string $userId): bool
	{
		// Use Nextcloud's group-based admin check (users in the admin group)
		return $this->groupManager->isAdmin($userId);
	}

	/**
	 * Nextcloud "admin" group (not ProjectCheck org admin)
	 */
	public function isUserGroupAdmin(string $userId): bool
	{
		return $this->isUserAdmin($userId);
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
			->andWhere($this->buildActiveMemberExpression($qb, 'pm'))
			->orderBy('p.created_at', 'DESC');

		$result = $qb->executeQuery();
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

		$result = $qb->executeQuery();
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

		$result = $qb->executeQuery();
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
	public function addTeamMember(int $projectId, string $userId, string $role = self::DEFAULT_MEMBER_ROLE, ?float $hourlyRate = null): ProjectMember
	{
		$userId = trim($userId);
		$role = self::DEFAULT_MEMBER_ROLE;
		if ($userId === '') {
			throw new \Exception('User ID is required');
		}
		if ($hourlyRate !== null && $hourlyRate < 0) {
			throw new \Exception('Hourly rate must be a non-negative number');
		}

		$project = $this->getProject($projectId);
		if (!$project) {
			throw new \Exception('Project not found');
		}

		if (!$project->isEditableState()) {
			throw new \Exception('Cannot change the team for a completed, cancelled, or archived project');
		}

		// Validate that user exists
		$targetUser = $this->userManager->get($userId);
		if (!$targetUser) {
			throw new \Exception('User not found');
		}

		$sessionUser = $this->userSession->getUser();
		if (!$sessionUser) {
			throw new \Exception('User not authenticated');
		}

		$existingMember = $this->getProjectMemberAnyState($projectId, $userId);
		if ($existingMember !== null) {
			if ($existingMember->isActiveMember()) {
				throw new \Exception('User is already assigned to this project');
			}
			$now = new \DateTime();
			$qb = $this->db->getQueryBuilder();
			$qb->update('project_members')
				->set('member_state', $qb->createNamedParameter(ProjectMember::STATE_ACTIVE))
				->set('archived_at', $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL))
				->set('role', $qb->createNamedParameter(self::DEFAULT_MEMBER_ROLE))
				->set('hourly_rate', $qb->createNamedParameter($hourlyRate))
				->set('assigned_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_DATETIME_MUTABLE))
				->set('assigned_by', $qb->createNamedParameter($sessionUser->getUID()))
				->where($qb->expr()->eq('id', $qb->createNamedParameter($existingMember->getId(), IQueryBuilder::PARAM_INT)));
			$qb->executeStatement();
			$ref = $this->getProjectMemberAnyState($projectId, $userId);
			if ($ref !== null) {
				return $ref;
			}
			return $existingMember;
		}

		$member = new ProjectMember();
		$member->setProjectId($projectId);
		$member->setUserId($userId);
		$member->setRole(self::DEFAULT_MEMBER_ROLE);
		$member->setHourlyRate($hourlyRate);
		$member->setAssignedAt(new \DateTime());
		$member->setAssignedBy($sessionUser->getUID());
		$member->setMemberState(ProjectMember::STATE_ACTIVE);
		$member->setArchivedAt(null);

		$qb = $this->db->getQueryBuilder();
		$ins = [
			'project_id' => $qb->createNamedParameter($member->getProjectId(), IQueryBuilder::PARAM_INT),
			'user_id' => $qb->createNamedParameter($member->getUserId()),
			'role' => $qb->createNamedParameter($member->getRole()),
			'hourly_rate' => $qb->createNamedParameter($member->getHourlyRate()),
			'assigned_at' => $qb->createNamedParameter($member->getAssignedAt(), IQueryBuilder::PARAM_DATETIME_MUTABLE),
			'assigned_by' => $qb->createNamedParameter($member->getAssignedBy()),
			'member_state' => $qb->createNamedParameter(ProjectMember::STATE_ACTIVE),
			'archived_at' => $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL),
		];
		$qb->insert('project_members')
			->values($ins);

		$qb->executeStatement();
		$member->setId((int) $this->db->lastInsertId('project_members'));

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
		$project = $this->getProject($projectId);
		if (!$project) {
			throw new \Exception('Project not found');
		}
		if (!$project->isEditableState()) {
			throw new \Exception('Cannot change the team for a completed, cancelled, or archived project');
		}

		$member = $this->getProjectMemberActive($projectId, $userId);
		if (!$member) {
			throw new \Exception('Team member not found');
		}

		$qb = $this->db->getQueryBuilder();
		$qb->delete('project_members')
			->where($qb->expr()->andX(
				$qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)),
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId))
			));

		$qb->executeStatement();

		return true;
	}

	/**
	 * Mark all active project team rows for a user as former (account removed). Preserves which projects they were on.
	 */
	public function archiveProjectMembershipsForDeletedUser(string $userId, \DateTimeInterface $at): int
	{
		if ($userId === '') {
			return 0;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->update('project_members')
			->set('member_state', $qb->createNamedParameter(ProjectMember::STATE_FORMER))
			->set('archived_at', $qb->createNamedParameter($at, IQueryBuilder::PARAM_DATETIME_MUTABLE))
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->andWhere($qb->expr()->eq('member_state', $qb->createNamedParameter(ProjectMember::STATE_ACTIVE)));
		$result = $qb->executeStatement();
		return is_int($result) ? $result : 0;
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
			->orderBy('user_id', 'ASC');

		$result = $qb->executeQuery();
		$members = [];

		while ($row = $result->fetch()) {
			$members[] = $this->mapRowToProjectMember($row);
		}

		$result->closeCursor();

		usort($members, static function (ProjectMember $a, ProjectMember $b): int {
			$fa = $a->isFormerMember() ? 1 : 0;
			$fb = $b->isFormerMember() ? 1 : 0;
			if ($fa !== $fb) {
				return $fa <=> $fb;
			}
			return strcasecmp((string)$a->getUserId(), (string)$b->getUserId());
		});

		return $members;
	}

	/**
	 * Active vs former team members (e.g. project detail UI).
	 *
	 * @return array{active: list<ProjectMember>, former: list<ProjectMember>}
	 */
	public function getProjectTeamGrouped(int $projectId): array
	{
		$all = $this->getProjectTeam($projectId);
		$active = [];
		$former = [];
		foreach ($all as $m) {
			if ($m->isFormerMember()) {
				$former[] = $m;
			} else {
				$active[] = $m;
			}
		}
		return ['active' => $active, 'former' => $former];
	}

	/**
	 * @return string a valid `users.uid` in the instance, or `system` as last resort
	 */
	public function getSuccessorUserIdForReassignment(string $excludedUserId): string
	{
		if ($this->accessControl !== null) {
			foreach ($this->accessControl->getAppAdminUserIds() as $id) {
				if ($id === $excludedUserId) {
					continue;
				}
				if ($this->userManager->get($id) !== null) {
					return $id;
				}
			}
		}
		$g = $this->groupManager->get('admin');
		if ($g !== null) {
			foreach ($g->getUsers() as $u) {
				$id = $u->getUID();
				if ($id === $excludedUserId) {
					continue;
				}
				return $id;
			}
		}
		return 'system';
	}

	/**
	 * Reassign `created_by` for projects and customers that pointed at a deleted account.
	 */
	public function reassignCreatorshipFromDeletedUser(string $deletedId, string $newOwnerId): void
	{
		$now = (new \DateTime())->format('Y-m-d H:i:s');
		$qb = $this->db->getQueryBuilder();
		$qb->update('projects')
			->set('created_by', $qb->createNamedParameter($newOwnerId))
			->set('updated_at', $qb->createNamedParameter($now, IQueryBuilder::PARAM_STR))
			->where($qb->expr()->eq('created_by', $qb->createNamedParameter($deletedId)));
		$qb->executeStatement();

		$qb2 = $this->db->getQueryBuilder();
		$qb2->update('customers')
			->set('created_by', $qb2->createNamedParameter($newOwnerId))
			->set('updated_at', $qb2->createNamedParameter($now, IQueryBuilder::PARAM_STR))
			->where($qb2->expr()->eq('created_by', $qb2->createNamedParameter($deletedId)));
		$qb2->executeStatement();
	}

	/**
	 * Get specific project member
	 *
	 * @param int $projectId
	 * @param string $userId
	 * @return ProjectMember|null
	 */
	private function getProjectMemberActive(int $projectId, string $userId): ?ProjectMember
	{
		return $this->getProjectMemberByState($projectId, $userId, ProjectMember::STATE_ACTIVE);
	}

	private function getProjectMemberAnyState(int $projectId, string $userId): ?ProjectMember
	{
		return $this->getProjectMemberByState($projectId, $userId, null);
	}

	private function getProjectMemberByState(int $projectId, string $userId, ?string $memberState): ?ProjectMember
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from('project_members')
			->where($qb->expr()->andX(
				$qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)),
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId))
			));
		if ($memberState !== null) {
			if ($memberState === ProjectMember::STATE_ACTIVE) {
				$qb->andWhere($this->buildActiveMemberExpression($qb));
			} else {
				$qb->andWhere($qb->expr()->eq('member_state', $qb->createNamedParameter($memberState)));
			}
		}

		$result = $qb->executeQuery();
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

		$newStatus = trim($newStatus);
		$currentStatus = (string)$project->getStatus();

		if (!in_array($newStatus, self::PROJECT_STATUSES, true)) {
			throw new \Exception('Invalid status value');
		}

		$this->assertStatusTransitionAllowed($currentStatus, $newStatus);

		$user = $this->userSession->getUser();
		if (!$user) {
			throw new \Exception('User not authenticated');
		}

		$qb = $this->db->getQueryBuilder();
		$qb->update('projects')
			->set('status', $qb->createNamedParameter($newStatus))
			->set('updated_at', $qb->createNamedParameter((new \DateTime())->format('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)));

		$qb->executeStatement();

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
		$member = $this->getProjectMemberActive($projectId, $userId);
		if (!$member) {
			throw new \Exception('Team member not found');
		}
		if ($member->isFormerMember()) {
			throw new \Exception('Cannot change the role of a former team member');
		}

		$newRole = self::DEFAULT_MEMBER_ROLE;

		$qb = $this->db->getQueryBuilder();
		$qb->update('project_members')
			->set('role', $qb->createNamedParameter($newRole))
			->where($qb->expr()->andX(
				$qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)),
				$qb->expr()->eq('user_id', $qb->createNamedParameter($userId))
			));

		$qb->executeStatement();

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
			->andWhere($this->buildActiveMemberExpression($qb, 'pm'))
			->orderBy('p.created_at', 'DESC');

		$result = $qb->executeQuery();
		$projects = [];

		while ($row = $result->fetch()) {
			$projects[] = $this->mapRowToProject($row);
		}

		$result->closeCursor();

		return $projects;
	}

	/**
	 * Backward-compatible active-member check.
	 * Legacy rows from pre-member_state rollout can have NULL/empty state and must count as active.
	 */
	private function buildActiveMemberExpression(IQueryBuilder $qb, string $alias = ''): \OCP\DB\QueryBuilder\ICompositeExpression
	{
		$field = $alias !== '' ? $alias . '.member_state' : 'member_state';
		return $qb->expr()->orX(
			$qb->expr()->eq($field, $qb->createNamedParameter(ProjectMember::STATE_ACTIVE)),
			$qb->expr()->isNull($field),
			$qb->expr()->eq($field, $qb->createNamedParameter(''))
		);
	}

	/**
	 * Get all team members for projects created by the given user.
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
			->where($qb->expr()->eq('p.created_by', $qb->createNamedParameter($managerId)))
			->andWhere($qb->expr()->neq('pm.user_id', $qb->createNamedParameter($managerId)))
			->orderBy('pm.user_id', 'ASC');

		$result = $qb->executeQuery();
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
			$availableHours = $this->calculator->calculateAvailableHours((float)$data['total_budget'], (float)$data['hourly_rate']);
			if ($availableHours < 0.5) {
				throw new \Exception('Budget too low for the specified hourly rate');
			}
		}

		// Validate date range if both dates are provided
		if (
			isset($data['start_date']) && !empty($data['start_date']) &&
			isset($data['end_date']) && !empty($data['end_date'])
		) {
			$startDate = SafeDateTime::fromOptional($data['start_date']);
			$endDate = SafeDateTime::fromOptional($data['end_date']);
			if ($startDate === null || $endDate === null) {
				throw new \Exception('Invalid start or end date');
			}
			if ($endDate <= $startDate) {
				throw new \Exception('End date must be after start date');
			}
		}

		if (isset($data['status']) && !in_array($data['status'], self::PROJECT_STATUSES, true)) {
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
		$project->setStartDate(SafeDateTime::fromOptional($row['start_date'] ?? null));
		$project->setEndDate(SafeDateTime::fromOptional($row['end_date'] ?? null));
		$project->setTags($row['tags']);
		$project->setProjectType($row['project_type'] ?? 'client');
		$project->setCreatedBy($row['created_by']);
		$project->setCreatedAt(SafeDateTime::fromRequired($row['created_at'] ?? null, 'projects.created_at'));
		$project->setUpdatedAt(SafeDateTime::fromRequired($row['updated_at'] ?? null, 'projects.updated_at'));

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

			$result = $qb->executeQuery();
			$row = $result->fetch();
			$result->closeCursor();

			if (!$row) {
				return false;
			}

			// Get table schema
			$schema = $row['sql'];

			// Check if column exists in schema
			return strpos($schema, "`{$column}`") !== false || strpos($schema, "'{$column}'") !== false || strpos($schema, "{$column}") !== false;
		} catch (\Throwable $e) {
			// Fallback: try to select the column directly
			try {
				$qb = $this->db->getQueryBuilder();
				$qb->select($column)
					->from($table)
					->setMaxResults(1);
				$result = $qb->executeQuery();
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
		$member->setAssignedAt(SafeDateTime::fromRequired($row['assigned_at'] ?? null, 'project_members.assigned_at'));
		$member->setAssignedBy($row['assigned_by']);
		$member->setMemberState((string)($row['member_state'] ?? ProjectMember::STATE_ACTIVE));
		$member->setArchivedAt(SafeDateTime::fromOptional($row['archived_at'] ?? null));

		return $member;
	}

	/**
	 * Merge provided update data with the current project to support partial updates.
	 *
	 * @param Project $project
	 * @param array $data
	 * @return array
	 */
	private function mergeWithExistingProjectData(Project $project, array $data): array
	{
		$currentData = [
			'name' => $project->getName(),
			'short_description' => $project->getShortDescription(),
			'detailed_description' => $project->getDetailedDescription(),
			'customer_id' => $project->getCustomerId(),
			'hourly_rate' => $project->getHourlyRate(),
			'total_budget' => $project->getTotalBudget(),
			'available_hours' => $project->getAvailableHours(),
			'category' => $project->getCategory(),
			'priority' => $project->getPriority(),
			'status' => $project->getStatus(),
			'start_date' => $project->getStartDate() ? $project->getStartDate()->format('Y-m-d') : null,
			'end_date' => $project->getEndDate() ? $project->getEndDate()->format('Y-m-d') : null,
			'tags' => $project->getTags(),
			'project_type' => $project->getProjectType(),
		];

		$mergedData = array_merge($currentData, $data);

		// Normalize DateTime objects back to strings for validation/DB writes
		foreach (['start_date', 'end_date'] as $dateField) {
			if ($mergedData[$dateField] instanceof \DateTimeInterface) {
				$mergedData[$dateField] = $mergedData[$dateField]->format('Y-m-d');
			}
		}

		return $mergedData;
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

		$result = $qb->executeQuery();
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
		} catch (\Throwable $e) {
			return null;
		}
	}
}
