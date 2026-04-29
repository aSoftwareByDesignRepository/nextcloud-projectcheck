<?php

declare(strict_types=1);

/**
 * Project mapper for projectcontrol app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Db;

use OCA\ProjectCheck\Util\SafeDateTime;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Project mapper for database operations
 */
class ProjectMapper extends QBMapper
{
	/**
	 * ProjectMapper constructor
	 *
	 * @param IDBConnection $db
	 */
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'projects', Project::class);
	}

	/**
	 * Find project by ID
	 *
	 * @param int $id Project ID
	 * @return Project|null
	 */
	public function find($id)
	{
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
			   ->from($this->getTableName())
			   ->where($qb->expr()->eq('id', $qb->createNamedParameter($id)));
			
			return $this->findEntity($qb);
		} catch (\Exception $e) {
			return null;
		}
	}

	/**
	 * Find all projects
	 *
	 * @return Project[]
	 */
	public function findAll()
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
		   ->from($this->getTableName())
		   ->orderBy('created_at', 'DESC');

		return $this->findEntities($qb);
	}

	/**
	 * Find projects by user
	 *
	 * @param string $userId User ID
	 * @param int $limit Limit number of results
	 * @return Project[]
	 */
	public function findByUser($userId, $limit = null)
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('p.*')
		   ->from($this->getTableName(), 'p')
		   ->leftJoin('p', 'project_members', 'pm', $qb->expr()->andX(
			   $qb->expr()->eq('p.id', 'pm.project_id'),
			   $qb->expr()->eq('pm.user_id', $qb->createNamedParameter($userId)),
			   $qb->expr()->eq('pm.member_state', $qb->createNamedParameter(ProjectMember::STATE_ACTIVE))
		   ))
		   ->where($qb->expr()->orX(
			   $qb->expr()->eq('p.created_by', $qb->createNamedParameter($userId)),
			   $qb->expr()->isNotNull('pm.id')
		   ))
		   ->orderBy('p.created_at', 'DESC');

		if ($limit) {
			$qb->setMaxResults($limit);
		}

		return $this->findEntities($qb);
	}

	/**
	 * Find projects by customer
	 *
	 * @param int $customerId Customer ID
	 * @return Project[]
	 */
	public function findByCustomer($customerId)
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
		   ->from($this->getTableName())
		   ->where($qb->expr()->eq('customer_id', $qb->createNamedParameter($customerId)))
		   ->orderBy('created_at', 'DESC');

		return $this->findEntities($qb);
	}

	/**
	 * Search projects
	 *
	 * @param string $query Search query
	 * @param string $userId User ID
	 * @return Project[]
	 */
	public function search($query, $userId)
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('p.*')
		   ->from($this->getTableName(), 'p')
		   ->leftJoin('p', 'project_members', 'pm', $qb->expr()->andX(
			   $qb->expr()->eq('p.id', 'pm.project_id'),
			   $qb->expr()->eq('pm.user_id', $qb->createNamedParameter($userId)),
			   $qb->expr()->eq('pm.member_state', $qb->createNamedParameter(ProjectMember::STATE_ACTIVE))
		   ))
		   ->where($qb->expr()->orX(
			   $qb->expr()->eq('p.created_by', $qb->createNamedParameter($userId)),
			   $qb->expr()->isNotNull('pm.id')
		   ))
		   ->andWhere($qb->expr()->orX(
			   $qb->expr()->like('p.name', $qb->createNamedParameter('%' . $query . '%')),
			   $qb->expr()->like('p.short_description', $qb->createNamedParameter('%' . $query . '%')),
			   $qb->expr()->like('p.detailed_description', $qb->createNamedParameter('%' . $query . '%'))
		   ))
		   ->orderBy('p.created_at', 'DESC');

		return $this->findEntities($qb);
	}

	/**
	 * Count projects by user
	 *
	 * @param string $userId User ID
	 * @return int
	 */
	public function countByUser($userId)
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('p.id'))
		   ->from($this->getTableName(), 'p')
		   ->leftJoin('p', 'project_members', 'pm', $qb->expr()->andX(
			   $qb->expr()->eq('p.id', 'pm.project_id'),
			   $qb->expr()->eq('pm.user_id', $qb->createNamedParameter($userId)),
			   $qb->expr()->eq('pm.member_state', $qb->createNamedParameter(ProjectMember::STATE_ACTIVE))
		   ))
		   ->where($qb->expr()->orX(
			   $qb->expr()->eq('p.created_by', $qb->createNamedParameter($userId)),
			   $qb->expr()->isNotNull('pm.id')
		   ));

		$result = $qb->executeQuery();
		$count = $result->fetchColumn();
		$result->closeCursor();

		return (int) $count;
	}

	/**
	 * Count projects by status
	 *
	 * @param string $status Project status
	 * @param string $userId User ID
	 * @return int
	 */
	public function countByStatus($status, $userId)
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('p.id'))
		   ->from($this->getTableName(), 'p')
		   ->leftJoin('p', 'project_members', 'pm', $qb->expr()->andX(
			   $qb->expr()->eq('p.id', 'pm.project_id'),
			   $qb->expr()->eq('pm.user_id', $qb->createNamedParameter($userId)),
			   $qb->expr()->eq('pm.member_state', $qb->createNamedParameter(ProjectMember::STATE_ACTIVE))
		   ))
		   ->where($qb->expr()->orX(
			   $qb->expr()->eq('p.created_by', $qb->createNamedParameter($userId)),
			   $qb->expr()->isNotNull('pm.id')
		   ))
		   ->andWhere($qb->expr()->eq('p.status', $qb->createNamedParameter($status)));

		$result = $qb->executeQuery();
		$count = $result->fetchColumn();
		$result->closeCursor();

		return (int) $count;
	}

	/**
	 * Count projects with filters (status, customer, priority, project_type, search)
	 *
	 * @param array $filters
	 * @return int
	 */
	public function countWithFilters(array $filters = []): int
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->func()->count('p.id'))
			->from($this->getTableName(), 'p')
			->leftJoin('p', 'customers', 'c', 'p.customer_id = c.id');

		if (!empty($filters['status'])) {
			if (is_array($filters['status'])) {
				$statusParams = [];
				foreach ($filters['status'] as $status) {
					$statusParams[] = $qb->createNamedParameter($status);
				}
				$qb->andWhere($qb->expr()->in('p.status', $statusParams));
			} else {
				$qb->andWhere($qb->expr()->eq('p.status', $qb->createNamedParameter($filters['status'])));
			}
		}

		if (!empty($filters['customer_id'])) {
			$qb->andWhere($qb->expr()->eq('p.customer_id', $qb->createNamedParameter($filters['customer_id'])));
		}

		if (!empty($filters['priority'])) {
			$qb->andWhere($qb->expr()->eq('p.priority', $qb->createNamedParameter($filters['priority'])));
		}

		if (!empty($filters['project_type'])) {
			$qb->andWhere($qb->expr()->eq('p.project_type', $qb->createNamedParameter($filters['project_type'])));
		}

		if (!empty($filters['search'])) {
			$qb->andWhere($qb->expr()->orX(
				$qb->expr()->like('p.name', $qb->createNamedParameter('%' . $filters['search'] . '%')),
				$qb->expr()->like('p.short_description', $qb->createNamedParameter('%' . $filters['search'] . '%'))
			));
		}

		// Optional hard project-id scope (used for per-user visibility scoping).
		if (array_key_exists('id_in', $filters) && is_array($filters['id_in'])) {
			if ($filters['id_in'] === []) {
				$qb->andWhere('1 = 0');
			} else {
				$qb->andWhere(
					$qb->expr()->in('p.id', $qb->createNamedParameter($filters['id_in'], IQueryBuilder::PARAM_INT_ARRAY))
				);
			}
		}

		$result = $qb->executeQuery();
		$count = $result->fetchColumn();
		$result->closeCursor();

		return (int)$count;
	}

	/**
	 * Get projects with budget consumption
	 *
	 * @param string $userId User ID
	 * @return array
	 */
	public function getProjectsWithBudgetConsumption($userId)
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('p.*', 'c.name as customer_name')
		   ->from($this->getTableName(), 'p')
		   ->leftJoin('p', 'customers', 'c', 'p.customer_id = c.id')
		   ->leftJoin('p', 'project_members', 'pm', $qb->expr()->andX(
			   $qb->expr()->eq('p.id', 'pm.project_id'),
			   $qb->expr()->eq('pm.user_id', $qb->createNamedParameter($userId)),
			   $qb->expr()->eq('pm.member_state', $qb->createNamedParameter(ProjectMember::STATE_ACTIVE))
		   ))
		   ->where($qb->expr()->orX(
			   $qb->expr()->eq('p.created_by', $qb->createNamedParameter($userId)),
			   $qb->expr()->isNotNull('pm.id')
		   ))
		   ->orderBy('p.created_at', 'DESC');

		$result = $qb->executeQuery();
		$projects = [];
		
		while ($row = $result->fetch()) {
			$project = $this->mapRowToEntity($row);
			$projects[] = $project;
		}
		
		$result->closeCursor();
		
		return $projects;
	}

	/**
	 * Map database row to entity
	 *
	 * @param array $row Database row
	 * @return Project
	 */
	protected function mapRowToEntity(array $row): Project
	{
		$project = new Project();
		$project->setId($row['id']);
		$project->setName($row['name']);
		$project->setShortDescription($row['short_description']);
		$project->setDetailedDescription($row['detailed_description']);
		$project->setCustomerId((int) $row['customer_id']);
		$project->setHourlyRate((float) $row['hourly_rate']);
		$project->setTotalBudget((float) $row['total_budget']);
		$project->setAvailableHours((float) $row['available_hours']);
		$project->setCategory($row['category']);
		$project->setPriority($row['priority']);
		$project->setStatus($row['status']);
		$project->setStartDate(SafeDateTime::fromOptional($row['start_date'] ?? null));
		$project->setEndDate(SafeDateTime::fromOptional($row['end_date'] ?? null));
		$project->setTags($row['tags']);
		$project->setCreatedBy($row['created_by']);
		$project->setCreatedAt(SafeDateTime::fromRequired($row['created_at'] ?? null, 'projects.created_at'));
		$project->setUpdatedAt(SafeDateTime::fromRequired($row['updated_at'] ?? null, 'projects.updated_at'));
		
		return $project;
	}
}
