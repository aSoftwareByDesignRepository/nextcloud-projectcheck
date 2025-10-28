<?php

/**
 * Project mapper for projectcontrol app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Db;

use OCP\AppFramework\Db\QBMapper;
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
		   ->leftJoin('p', 'project_members', 'pm', 'p.id = pm.project_id')
		   ->where($qb->expr()->orX(
			   $qb->expr()->eq('p.created_by', $qb->createNamedParameter($userId)),
			   $qb->expr()->eq('pm.user_id', $qb->createNamedParameter($userId))
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
		   ->leftJoin('p', 'project_members', 'pm', 'p.id = pm.project_id')
		   ->where($qb->expr()->orX(
			   $qb->expr()->eq('p.created_by', $qb->createNamedParameter($userId)),
			   $qb->expr()->eq('pm.user_id', $qb->createNamedParameter($userId))
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
		   ->leftJoin('p', 'project_members', 'pm', 'p.id = pm.project_id')
		   ->where($qb->expr()->orX(
			   $qb->expr()->eq('p.created_by', $qb->createNamedParameter($userId)),
			   $qb->expr()->eq('pm.user_id', $qb->createNamedParameter($userId))
		   ));

		$result = $qb->execute();
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
		   ->leftJoin('p', 'project_members', 'pm', 'p.id = pm.project_id')
		   ->where($qb->expr()->orX(
			   $qb->expr()->eq('p.created_by', $qb->createNamedParameter($userId)),
			   $qb->expr()->eq('pm.user_id', $qb->createNamedParameter($userId))
		   ))
		   ->andWhere($qb->expr()->eq('p.status', $qb->createNamedParameter($status)));

		$result = $qb->execute();
		$count = $result->fetchColumn();
		$result->closeCursor();

		return (int) $count;
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
		   ->leftJoin('p', 'project_members', 'pm', 'p.id = pm.project_id')
		   ->where($qb->expr()->orX(
			   $qb->expr()->eq('p.created_by', $qb->createNamedParameter($userId)),
			   $qb->expr()->eq('pm.user_id', $qb->createNamedParameter($userId))
		   ))
		   ->orderBy('p.created_at', 'DESC');

		$result = $qb->execute();
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
		$project->setStartDate(new \DateTime($row['start_date']));
		$project->setEndDate(new \DateTime($row['end_date']));
		$project->setTags($row['tags']);
		$project->setCreatedBy($row['created_by']);
		$project->setCreatedAt(new \DateTime($row['created_at']));
		$project->setUpdatedAt(new \DateTime($row['updated_at']));
		
		return $project;
	}
}
