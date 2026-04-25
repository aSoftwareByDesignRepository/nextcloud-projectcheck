<?php

declare(strict_types=1);

/**
 * Customer mapper for projectcontrol app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Db;

use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

/**
 * Customer mapper for database operations
 */
class CustomerMapper extends QBMapper
{
	/**
	 * CustomerMapper constructor
	 *
	 * @param IDBConnection $db Database connection
	 */
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'customers', Customer::class);
	}



	/**
	 * Find customer by ID
	 *
	 * @param int $id Customer ID
	 * @return Customer|null
	 */
	public function find(int $id): ?Customer
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
	 * Find customer by name
	 *
	 * @param string $name Customer name
	 * @return Customer|null
	 */
	public function findByName(string $name): ?Customer
	{
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from($this->getTableName())
				->where($qb->expr()->eq('name', $qb->createNamedParameter($name)));

			return $this->findEntity($qb);
		} catch (\Exception $e) {
			return null;
		}
	}

	/**
	 * Find all customers with optional filters.
	 * Always ordered alphabetically by name (ASC) for consistent list and dropdown UX.
	 *
	 * @param array $filters Optional filters
	 * @return Customer[]
	 */
	public function findAll(array $filters = []): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->orderBy('name', 'ASC');

		// Apply filters
		if (!empty($filters['search'])) {
			$qb->andWhere($qb->expr()->orX(
				$qb->expr()->like('name', $qb->createNamedParameter('%' . $filters['search'] . '%')),
				$qb->expr()->like('email', $qb->createNamedParameter('%' . $filters['search'] . '%')),
				$qb->expr()->like('contact_person', $qb->createNamedParameter('%' . $filters['search'] . '%'))
			));
		}

		if (!empty($filters['created_by'])) {
			$qb->andWhere($qb->expr()->eq('created_by', $qb->createNamedParameter($filters['created_by'])));
		}

		/** @var list<int> $idList */
		if (isset($filters['id_in']) && is_array($filters['id_in']) && $filters['id_in'] === []) {
			$qb->andWhere('1=0');
		} elseif (!empty($filters['id_in']) && is_array($filters['id_in'])) {
			$ids = array_map('intval', $filters['id_in']);
			$ids = array_values(array_filter($ids, static fn (int $x): bool => $x > 0));
			if ($ids === []) {
				$qb->andWhere('1=0');
			} else {
				$qb->andWhere($qb->expr()->in('id', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));
			}
		}

		// Pagination
		if (!empty($filters['limit'])) {
			$qb->setMaxResults((int)$filters['limit']);
		}
		if (!empty($filters['offset'])) {
			$qb->setFirstResult((int)$filters['offset']);
		}

		return $this->findEntities($qb);
	}

	/**
	 * Search customers
	 *
	 * @param string $query Search query
	 * @return Customer[]
	 */
	public function search(string $query): array
	{
		return $this->findAll(['search' => $query]);
	}

	/**
	 * Find customers by creator
	 *
	 * @param string $userId User ID
	 * @return Customer[]
	 */
	public function findByCreator(string $userId): array
	{
		return $this->findAll(['created_by' => $userId]);
	}

	/**
	 * Count total customers
	 *
	 * @return int
	 */
	public function countWithFilters(array $filters = []): int
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*)'))
			->from($this->getTableName());

		if (!empty($filters['search'])) {
			$qb->andWhere($qb->expr()->orX(
				$qb->expr()->like('name', $qb->createNamedParameter('%' . $filters['search'] . '%')),
				$qb->expr()->like('email', $qb->createNamedParameter('%' . $filters['search'] . '%')),
				$qb->expr()->like('contact_person', $qb->createNamedParameter('%' . $filters['search'] . '%'))
			));
		}

		if (!empty($filters['created_by'])) {
			$qb->andWhere($qb->expr()->eq('created_by', $qb->createNamedParameter($filters['created_by'])));
		}

		if (isset($filters['id_in']) && is_array($filters['id_in']) && $filters['id_in'] === []) {
			$qb->andWhere('1=0');
		} elseif (!empty($filters['id_in']) && is_array($filters['id_in'])) {
			$ids = array_map('intval', $filters['id_in']);
			$ids = array_values(array_filter($ids, static fn (int $x): bool => $x > 0));
			if ($ids === []) {
				$qb->andWhere('1=0');
			} else {
				$qb->andWhere($qb->expr()->in('id', $qb->createNamedParameter($ids, IQueryBuilder::PARAM_INT_ARRAY)));
			}
		}

		$result = $qb->executeQuery();
		$count = $result->fetchColumn();
		$result->closeCursor();

		return (int) $count;
	}

	/**
	 * Count customers with projects
	 *
	 * @return int
	 */
	public function countWithProjects(): int
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(DISTINCT c.id)'))
			->from($this->getTableName(), 'c')
			->innerJoin('c', 'projects', 'p', $qb->expr()->eq('c.id', 'p.customer_id'));

		$result = $qb->executeQuery();
		$count = $result->fetchColumn();
		$result->closeCursor();

		return (int) $count;
	}

	/**
	 * Count customers with complete information
	 *
	 * @return int
	 */
	public function countWithCompleteInfo(): int
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*)'))
			->from($this->getTableName())
			->where($qb->expr()->andX(
				$qb->expr()->isNotNull('email'),
				$qb->expr()->neq('email', $qb->createNamedParameter('')),
				$qb->expr()->isNotNull('phone'),
				$qb->expr()->neq('phone', $qb->createNamedParameter('')),
				$qb->expr()->isNotNull('contact_person'),
				$qb->expr()->neq('contact_person', $qb->createNamedParameter(''))
			));

		$result = $qb->executeQuery();
		$count = $result->fetchColumn();
		$result->closeCursor();

		return (int) $count;
	}

	/**
	 * Get project count for a customer
	 *
	 * @param int $customerId Customer ID
	 * @return int
	 */
	public function getProjectCount(int $customerId): int
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*)'))
			->from('projects')
			->where($qb->expr()->eq('customer_id', $qb->createNamedParameter($customerId)));

		$result = $qb->executeQuery();
		$count = $result->fetchColumn();
		$result->closeCursor();

		return (int) $count;
	}

	/**
	 * Find customers with project count
	 *
	 * @return array
	 */
	public function findWithProjectCount(): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('c.*', $qb->createFunction('COUNT(p.id) as project_count'))
			->from($this->getTableName(), 'c')
			->leftJoin('c', 'projects', 'p', $qb->expr()->eq('c.id', 'p.customer_id'))
			->groupBy('c.id')
			->orderBy('c.name', 'ASC');

		$result = $qb->executeQuery();
		$customers = [];
		while ($row = $result->fetch()) {
			$customer = $this->mapRowToEntity($row);
			$customers[] = [
				'customer' => $customer,
				'projectCount' => (int) $row['project_count']
			];
		}
		$result->closeCursor();

		return $customers;
	}

	/**
	 * Customer IDs a non–system-admin user may list: own customers, or any customer with a project
	 * the user created or is a team member of.
	 *
	 * @return list<int>
	 */
	public function findAccessibleCustomerIdsForUser(string $userId): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('c.id')
			->from($this->getTableName(), 'c')
			->leftJoin('c', 'projects', 'p', $qb->expr()->eq('c.id', 'p.customer_id'))
			->leftJoin('p', 'project_members', 'pm', $qb->expr()->andX(
				$qb->expr()->eq('p.id', 'pm.project_id'),
				$qb->expr()->eq('pm.user_id', $qb->createNamedParameter($userId)),
				$qb->expr()->eq('pm.member_state', $qb->createNamedParameter(ProjectMember::STATE_ACTIVE))
			))
			->where($qb->expr()->orX(
				$qb->expr()->eq('c.created_by', $qb->createNamedParameter($userId)),
				$qb->expr()->eq('p.created_by', $qb->createNamedParameter($userId)),
				$qb->expr()->isNotNull('pm.id')
			));

		$result = $qb->executeQuery();
		$out = [];
		while ($row = $result->fetch()) {
			$out[] = (int) $row['id'];
		}
		$result->closeCursor();

		return $out;
	}
}
