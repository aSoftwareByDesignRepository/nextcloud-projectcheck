<?php

declare(strict_types=1);

/**
 * TimeEntry mapper for projectcontrol app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Db;

use OCA\ProjectCheck\Util\SafeDateTime;
use OCP\AppFramework\Db\QBMapper;
use OCP\IDBConnection;

/**
 * TimeEntry mapper for database operations
 */
class TimeEntryMapper extends QBMapper
{
	/**
	 * TimeEntryMapper constructor
	 *
	 * @param IDBConnection $db Database connection
	 */
	public function __construct(IDBConnection $db)
	{
		parent::__construct($db, 'time_entries', TimeEntry::class);
	}

	/**
	 * Find all time entries with optional filters
	 *
	 * @param array $filters Optional filters
	 * @return TimeEntry[]
	 */
	public function findAll(array $filters = []): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('*')
			->from($this->getTableName())
			->orderBy('date', 'DESC')
			->addOrderBy('created_at', 'DESC');

		// Apply filters
		if (!empty($filters['project_id'])) {
			$qb->andWhere($qb->expr()->eq('project_id', $qb->createNamedParameter($filters['project_id'])));
		}

		if (!empty($filters['user_id'])) {
			$qb->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($filters['user_id'])));
		}

		if (!empty($filters['date_from'])) {
			$qb->andWhere($qb->expr()->gte('date', $qb->createNamedParameter($filters['date_from'])));
		}

		if (!empty($filters['date_to'])) {
			$qb->andWhere($qb->expr()->lte('date', $qb->createNamedParameter($filters['date_to'])));
		}

		if (!empty($filters['search'])) {
			$qb->andWhere($qb->expr()->like('description', $qb->createNamedParameter('%' . $filters['search'] . '%')));
		}

		// Apply pagination
		if (!empty($filters['limit'])) {
			$qb->setMaxResults($filters['limit']);
		}

		if (!empty($filters['offset'])) {
			$qb->setFirstResult($filters['offset']);
		}

		$result = $qb->executeQuery();
		$timeEntries = [];
		while ($row = $result->fetch()) {
			$timeEntries[] = $this->mapRowToEntity($row);
		}
		$result->closeCursor();

		return $timeEntries;
	}

	/**
	 * Find time entry by ID
	 *
	 * @param int $id Time entry ID
	 * @return TimeEntry|null
	 */
	public function find(int $id): ?TimeEntry
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
	 * Find time entries by project
	 *
	 * @param int $projectId Project ID
	 * @return TimeEntry[]
	 */
	public function findByProject(int $projectId): array
	{
		return $this->findAll(['project_id' => $projectId]);
	}

	/**
	 * Find time entries by user
	 *
	 * @param string $userId User ID
	 * @param int $limit Optional limit
	 * @return TimeEntry[]
	 */
	public function findByUser(string $userId, ?int $limit = null): array
	{
		$filters = ['user_id' => $userId];
		if ($limit) {
			$filters['limit'] = $limit;
		}
		return $this->findAll($filters);
	}

	/**
	 * Find time entries by project and user
	 *
	 * @param int $projectId Project ID
	 * @param string $userId User ID
	 * @return TimeEntry[]
	 */
	public function findByProjectAndUser(int $projectId, string $userId): array
	{
		return $this->findAll(['project_id' => $projectId, 'user_id' => $userId]);
	}

	/**
	 * Find time entries by date range
	 *
	 * @param string $dateFrom Start date (Y-m-d)
	 * @param string $dateTo End date (Y-m-d)
	 * @return TimeEntry[]
	 */
	public function findByDateRange(string $dateFrom, string $dateTo): array
	{
		return $this->findAll(['date_from' => $dateFrom, 'date_to' => $dateTo]);
	}

	/**
	 * Search time entries
	 *
	 * @param string $query Search query
	 * @param string $userId User ID
	 * @return TimeEntry[]
	 */
	public function search(string $query, string $userId): array
	{
		return $this->findAll(['search' => $query, 'user_id' => $userId]);
	}

	/**
	 * Count total time entries
	 *
	 * @param array $filters Optional filters
	 * @return int
	 */
	public function count(array $filters = []): int
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('COUNT(*)'))
			->from($this->getTableName());

		// Apply filters
		if (!empty($filters['project_id'])) {
			$qb->andWhere($qb->expr()->eq('project_id', $qb->createNamedParameter($filters['project_id'])));
		}

		if (!empty($filters['user_id'])) {
			$qb->andWhere($qb->expr()->eq('user_id', $qb->createNamedParameter($filters['user_id'])));
		}

		if (!empty($filters['date_from'])) {
			$qb->andWhere($qb->expr()->gte('date', $qb->createNamedParameter($filters['date_from'])));
		}

		if (!empty($filters['date_to'])) {
			$qb->andWhere($qb->expr()->lte('date', $qb->createNamedParameter($filters['date_to'])));
		}

		$result = $qb->executeQuery();
		$count = $result->fetchColumn();
		$result->closeCursor();

		return (int) $count;
	}

	/**
	 * Count time entries by user
	 *
	 * @param string $userId User ID
	 * @return int
	 */
	public function countByUser(string $userId): int
	{
		return $this->count(['user_id' => $userId]);
	}

	/**
	 * Get total hours for a project
	 *
	 * @param int $projectId Project ID
	 * @return float
	 */
	public function getTotalHoursForProject(int $projectId): float
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('SUM(hours)'))
			->from($this->getTableName())
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId)));

		$result = $qb->executeQuery();
		$total = $result->fetchColumn();
		$result->closeCursor();

		return (float) $total;
	}

	/**
	 * Get total cost for a project
	 *
	 * @param int $projectId Project ID
	 * @return float
	 */
	public function getTotalCostForProject(int $projectId): float
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('SUM(hours * hourly_rate)'))
			->from($this->getTableName())
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId)));

		$result = $qb->executeQuery();
		$total = $result->fetchColumn();
		$result->closeCursor();

		return (float) $total;
	}

	/**
	 * Get total hours for a user
	 *
	 * @param string $userId User ID
	 * @param array $filters Optional filters
	 * @return float
	 */
	public function getTotalHoursForUser(string $userId, array $filters = []): float
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('SUM(hours)'))
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

		// Apply additional filters
		if (!empty($filters['date_from'])) {
			$qb->andWhere($qb->expr()->gte('date', $qb->createNamedParameter($filters['date_from'])));
		}

		if (!empty($filters['date_to'])) {
			$qb->andWhere($qb->expr()->lte('date', $qb->createNamedParameter($filters['date_to'])));
		}

		$result = $qb->executeQuery();
		$total = $result->fetchColumn();
		$result->closeCursor();

		return (float) $total;
	}

	/**
	 * Get time entries with project information
	 *
	 * @param array $filters Optional filters
	 * @return array
	 */
	public function findWithProjectInfo(array $filters = []): array
	{
		$qb = $this->db->getQueryBuilder();

		// Base select fields
		$selectFields = [
			't.*',
			'p.name as project_name',
			'c.name as customer_name',
			$qb->createFunction('COALESCE(s.display_name, u.displayname, t.user_id) as user_display_name'),
		];

		// Add project_type fields if column exists
		if ($this->columnExists('projects', 'project_type')) {
			$selectFields[] = 'p.project_type';
			$selectFields[] = 'p.project_type as project_type_display_name';
		} else {
			// Provide default values if column doesn't exist - will be handled in post-processing
			$selectFields[] = $qb->createNamedParameter('client') . ' as project_type';
			$selectFields[] = $qb->createNamedParameter('Client Project') . ' as project_type_display_name';
		}

		$qb->select(...$selectFields)
			->from($this->getTableName(), 't')
			->innerJoin('t', 'projects', 'p', $qb->expr()->eq('t.project_id', 'p.id'))
			->innerJoin('p', 'customers', 'c', $qb->expr()->eq('p.customer_id', 'c.id'))
			->leftJoin('t', 'pc_user_account_snapshots', 's', $qb->expr()->eq('t.user_id', 's.user_id'))
			->leftJoin('t', 'users', 'u', $qb->expr()->eq('t.user_id', 'u.uid'))
			->orderBy('t.date', 'DESC')
			->addOrderBy('t.created_at', 'DESC');

		// Apply filters
		if (!empty($filters['project_id'])) {
			$qb->andWhere($qb->expr()->eq('t.project_id', $qb->createNamedParameter($filters['project_id'])));
		}

		if (!empty($filters['user_id'])) {
			$qb->andWhere($qb->expr()->eq('t.user_id', $qb->createNamedParameter($filters['user_id'])));
		}

		if (!empty($filters['project_type'])) {
			if ($this->columnExists('projects', 'project_type')) {
				$qb->andWhere($qb->expr()->eq('p.project_type', $qb->createNamedParameter($filters['project_type'])));
			}
		}

		if (!empty($filters['date_from'])) {
			$qb->andWhere($qb->expr()->gte('t.date', $qb->createNamedParameter($filters['date_from'])));
		}

		if (!empty($filters['date_to'])) {
			$qb->andWhere($qb->expr()->lte('t.date', $qb->createNamedParameter($filters['date_to'])));
		}

		// Search filter - search in description, project name, customer name
		if (!empty($filters['search'])) {
			$searchTerm = '%' . $this->db->escapeLikeParameter($filters['search']) . '%';
			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->iLike('t.description', $qb->createNamedParameter($searchTerm)),
					$qb->expr()->iLike('p.name', $qb->createNamedParameter($searchTerm)),
					$qb->expr()->iLike('c.name', $qb->createNamedParameter($searchTerm))
				)
			);
		}

		// Apply pagination
		if (!empty($filters['limit'])) {
			$qb->setMaxResults($filters['limit']);
		}

		if (!empty($filters['offset'])) {
			$qb->setFirstResult($filters['offset']);
		}

		$result = $qb->executeQuery();
		$timeEntries = [];
		while ($row = $result->fetch()) {
			$timeEntry = $this->mapRowToEntity($row);

			// Get project type and display name - handle both cases (column exists or not)
			$projectType = $row['project_type'] ?? 'client';
			$projectTypeDisplayName = $this->getProjectTypeDisplayName($projectType);

			$timeEntries[] = [
				'timeEntry' => $timeEntry,
				'projectName' => $row['project_name'],
				'customerName' => $row['customer_name'],
				'userDisplayName' => $row['user_display_name'] ?? $timeEntry->getUserId(),
				'project_type' => $projectType,
				'project_type_display_name' => $projectTypeDisplayName
			];
		}
		$result->closeCursor();

		return $timeEntries;
	}

	/**
	 * Find all users who have time entries
	 *
	 * @return array
	 */
	public function findUsersWithTimeEntries(): array
	{
		$qb = $this->db->getQueryBuilder();
		$coalesce = "COALESCE(MAX(s.display_name), MAX(u.displayname), t.user_id)";
		$qb->select('t.user_id', $qb->createFunction($coalesce . ' as displayname'))
			->from($this->getTableName(), 't')
			->leftJoin('t', 'pc_user_account_snapshots', 's', $qb->expr()->eq('t.user_id', 's.user_id'))
			->leftJoin('t', 'users', 'u', $qb->expr()->eq('t.user_id', 'u.uid'))
			->groupBy('t.user_id')
			->orderBy($qb->createFunction($coalesce), 'ASC');

		$result = $qb->executeQuery();
		$users = [];
		while ($row = $result->fetch()) {
			$users[] = [
				'user_id' => $row['user_id'],
				'displayname' => $row['displayname'] ?: $row['user_id']
			];
		}
		$result->closeCursor();

		return $users;
	}

	/**
	 * Get yearly statistics for a project
	 *
	 * @param int $projectId Project ID
	 * @return array
	 */
	public function getYearlyStatsForProject(int $projectId): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			$qb->createFunction('YEAR(date) as year'),
			$qb->createFunction('SUM(hours) as total_hours'),
			$qb->createFunction('SUM(hours * hourly_rate) as total_cost'),
			$qb->createFunction('COUNT(*) as entry_count')
		)
			->from($this->getTableName())
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId)))
			->groupBy($qb->createFunction('YEAR(date)'))
			->orderBy('year', 'DESC');

		$result = $qb->executeQuery();
		$yearlyStats = [];
		while ($row = $result->fetch()) {
			$yearlyStats[] = [
				'year' => (int) $row['year'],
				'total_hours' => (float) $row['total_hours'],
				'total_cost' => (float) $row['total_cost'],
				'entry_count' => (int) $row['entry_count']
			];
		}
		$result->closeCursor();

		return $yearlyStats;
	}

	/**
	 * Get yearly statistics for all projects
	 *
	 * @return array
	 */
	public function getYearlyStatsForAllProjects(): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			$qb->createFunction('YEAR(date) as year'),
			$qb->createFunction('SUM(hours) as total_hours'),
			$qb->createFunction('SUM(hours * hourly_rate) as total_cost'),
			$qb->createFunction('COUNT(*) as entry_count')
		)
			->from($this->getTableName())
			->groupBy($qb->createFunction('YEAR(date)'))
			->orderBy('year', 'DESC');

		$result = $qb->executeQuery();
		$yearlyStats = [];
		while ($row = $result->fetch()) {
			$yearlyStats[] = [
				'year' => (int) $row['year'],
				'total_hours' => (float) $row['total_hours'],
				'total_cost' => (float) $row['total_cost'],
				'entry_count' => (int) $row['entry_count']
			];
		}
		$result->closeCursor();

		return $yearlyStats;
	}

	/**
	 * Get yearly statistics for a customer
	 *
	 * @param int $customerId Customer ID
	 * @return array
	 */
	public function getYearlyStatsForCustomer(int $customerId): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			$qb->createFunction('YEAR(t.date) as year'),
			$qb->createFunction('SUM(t.hours) as total_hours'),
			$qb->createFunction('SUM(t.hours * t.hourly_rate) as total_cost'),
			$qb->createFunction('COUNT(*) as entry_count')
		)
			->from($this->getTableName(), 't')
			->innerJoin('t', 'projects', 'p', $qb->expr()->eq('t.project_id', 'p.id'))
			->where($qb->expr()->eq('p.customer_id', $qb->createNamedParameter($customerId)))
			->groupBy($qb->createFunction('YEAR(t.date)'))
			->orderBy('year', 'DESC');

		$result = $qb->executeQuery();
		$yearlyStats = [];
		while ($row = $result->fetch()) {
			$yearlyStats[] = [
				'year' => (int) $row['year'],
				'total_hours' => (float) $row['total_hours'],
				'total_cost' => (float) $row['total_cost'],
				'entry_count' => (int) $row['entry_count']
			];
		}
		$result->closeCursor();

		return $yearlyStats;
	}

	/**
	 * Get detailed yearly statistics grouped by customer and project
	 *
	 * @return array
	 */
	public function getDetailedYearlyStats(): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			$qb->createFunction('YEAR(t.date) as year'),
			'c.id as customer_id',
			'c.name as customer_name',
			'p.id as project_id',
			'p.name as project_name',
			$qb->createFunction('SUM(t.hours) as total_hours'),
			$qb->createFunction('SUM(t.hours * t.hourly_rate) as total_cost'),
			$qb->createFunction('COUNT(*) as entry_count')
		)
			->from($this->getTableName(), 't')
			->innerJoin('t', 'projects', 'p', $qb->expr()->eq('t.project_id', 'p.id'))
			->innerJoin('p', 'customers', 'c', $qb->expr()->eq('p.customer_id', 'c.id'))
			->groupBy($qb->createFunction('YEAR(t.date)'), 'c.id', 'p.id')
			->orderBy('year', 'DESC')
			->addOrderBy('c.name', 'ASC')
			->addOrderBy('p.name', 'ASC');

		$result = $qb->executeQuery();
		$detailedStats = [];
		while ($row = $result->fetch()) {
			$year = (int) $row['year'];
			$customerId = (int) $row['customer_id'];
			$projectId = (int) $row['project_id'];

			if (!isset($detailedStats[$year])) {
				$detailedStats[$year] = [];
			}
			if (!isset($detailedStats[$year][$customerId])) {
				$detailedStats[$year][$customerId] = [
					'customer_id' => $customerId,
					'customer_name' => $row['customer_name'],
					'total_hours' => 0,
					'total_cost' => 0,
					'total_entries' => 0,
					'projects' => []
				];
			}

			$projectData = [
				'project_id' => $projectId,
				'project_name' => $row['project_name'],
				'total_hours' => (float) $row['total_hours'],
				'total_cost' => (float) $row['total_cost'],
				'entry_count' => (int) $row['entry_count']
			];

			$detailedStats[$year][$customerId]['projects'][$projectId] = $projectData;
			$detailedStats[$year][$customerId]['total_hours'] += $projectData['total_hours'];
			$detailedStats[$year][$customerId]['total_cost'] += $projectData['total_cost'];
			$detailedStats[$year][$customerId]['total_entries'] += $projectData['entry_count'];
		}
		$result->closeCursor();

		return $detailedStats;
	}

	/**
	 * Get yearly statistics for an employee
	 *
	 * @param string $userId User ID
	 * @return array
	 */
	public function getYearlyStatsForEmployee(string $userId): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			$qb->createFunction('YEAR(date) as year'),
			$qb->createFunction('SUM(hours) as total_hours'),
			$qb->createFunction('SUM(hours * hourly_rate) as total_cost'),
			$qb->createFunction('COUNT(*) as entry_count')
		)
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)))
			->groupBy($qb->createFunction('YEAR(date)'))
			->orderBy('year', 'DESC');

		$result = $qb->executeQuery();
		$yearlyStats = [];
		while ($row = $result->fetch()) {
			$yearlyStats[] = [
				'year' => (int) $row['year'],
				'total_hours' => (float) $row['total_hours'],
				'total_cost' => (float) $row['total_cost'],
				'entry_count' => (int) $row['entry_count']
			];
		}
		$result->closeCursor();

		return $yearlyStats;
	}

	/**
	 * Get detailed yearly statistics grouped by employee
	 *
	 * @return array
	 */
	public function getEmployeeYearlyStats(): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			$qb->createFunction('YEAR(t.date) as year'),
			't.user_id',
			$qb->createFunction('MAX(COALESCE(s.display_name, u.displayname, t.user_id)) as user_display_name'),
			$qb->createFunction('SUM(t.hours) as total_hours'),
			$qb->createFunction('SUM(t.hours * t.hourly_rate) as total_cost'),
			$qb->createFunction('COUNT(*) as entry_count')
		)
			->from($this->getTableName(), 't')
			->leftJoin('t', 'pc_user_account_snapshots', 's', $qb->expr()->eq('t.user_id', 's.user_id'))
			->leftJoin('t', 'users', 'u', $qb->expr()->eq('t.user_id', 'u.uid'))
			->groupBy($qb->createFunction('YEAR(t.date)'), 't.user_id')
			->orderBy('year', 'DESC')
			->addOrderBy($qb->createFunction('MAX(COALESCE(s.display_name, u.displayname, t.user_id))'), 'ASC');

		$result = $qb->executeQuery();
		$employeeStats = [];
		while ($row = $result->fetch()) {
			$year = (int) $row['year'];
			$userId = $row['user_id'];

			if (!isset($employeeStats[$year])) {
				$employeeStats[$year] = [];
			}

			$employeeStats[$year][$userId] = [
				'user_id' => $userId,
				'user_display_name' => $row['user_display_name'] ?: $userId,
				'total_hours' => (float) $row['total_hours'],
				'total_cost' => (float) $row['total_cost'],
				'entry_count' => (int) $row['entry_count']
			];
		}
		$result->closeCursor();

		return $employeeStats;
	}

	/**
	 * Get employee comparison statistics
	 *
	 * @return array
	 */
	public function getEmployeeComparisonStats(): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select(
			't.user_id',
			$qb->createFunction('MAX(COALESCE(s.display_name, u.displayname, t.user_id)) as user_display_name'),
			$qb->createFunction('SUM(t.hours) as total_hours'),
			$qb->createFunction('SUM(t.hours * t.hourly_rate) as total_cost'),
			$qb->createFunction('COUNT(*) as entry_count'),
			$qb->createFunction('AVG(t.hourly_rate) as avg_hourly_rate'),
			$qb->createFunction('MIN(t.date) as first_entry'),
			$qb->createFunction('MAX(t.date) as last_entry')
		)
			->from($this->getTableName(), 't')
			->leftJoin('t', 'pc_user_account_snapshots', 's', $qb->expr()->eq('t.user_id', 's.user_id'))
			->leftJoin('t', 'users', 'u', $qb->expr()->eq('t.user_id', 'u.uid'))
			->groupBy('t.user_id')
			->orderBy('total_hours', 'DESC');

		$result = $qb->executeQuery();
		$comparisonStats = [];
		while ($row = $result->fetch()) {
			$comparisonStats[] = [
				'user_id' => $row['user_id'],
				'user_display_name' => $row['user_display_name'] ?: $row['user_id'],
				'total_hours' => (float) $row['total_hours'],
				'total_cost' => (float) $row['total_cost'],
				'entry_count' => (int) $row['entry_count'],
				'avg_hourly_rate' => (float) $row['avg_hourly_rate'],
				'first_entry' => $row['first_entry'],
				'last_entry' => $row['last_entry']
			];
		}
		$result->closeCursor();

		return $comparisonStats;
	}

	/**
	 * Get yearly statistics grouped by project type
	 *
	 * @return array
	 */
	public function getYearlyStatsByProjectType(): array
	{
		// Return empty array if project_type column doesn't exist
		if (!$this->columnExists('projects', 'project_type')) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select(
			$qb->createFunction('YEAR(t.date) as year'),
			'p.project_type',
			$qb->createFunction('SUM(t.hours) as total_hours'),
			$qb->createFunction('SUM(t.hours * t.hourly_rate) as total_cost'),
			$qb->createFunction('COUNT(*) as entry_count')
		)
			->from($this->getTableName(), 't')
			->innerJoin('t', 'projects', 'p', $qb->expr()->eq('t.project_id', 'p.id'))
			->groupBy($qb->createFunction('YEAR(t.date)'), 'p.project_type')
			->orderBy('year', 'DESC')
			->addOrderBy('p.project_type', 'ASC');

		$result = $qb->executeQuery();
		$projectTypeStats = [];
		while ($row = $result->fetch()) {
			$year = (int) $row['year'];
			$projectType = $row['project_type'] ?: 'client';

			if (!isset($projectTypeStats[$year])) {
				$projectTypeStats[$year] = [];
			}

			$projectTypeStats[$year][$projectType] = [
				'project_type' => $projectType,
				'total_hours' => (float) $row['total_hours'],
				'total_cost' => (float) $row['total_cost'],
				'entry_count' => (int) $row['entry_count']
			];
		}
		$result->closeCursor();

		return $projectTypeStats;
	}

	/**
	 * Get detailed yearly statistics grouped by project type and customer
	 *
	 * @return array
	 */
	public function getDetailedYearlyStatsByProjectType(): array
	{
		// Return empty array if project_type column doesn't exist
		if (!$this->columnExists('projects', 'project_type')) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select(
			$qb->createFunction('YEAR(t.date) as year'),
			'p.project_type',
			'c.id as customer_id',
			'c.name as customer_name',
			$qb->createFunction('SUM(t.hours) as total_hours'),
			$qb->createFunction('SUM(t.hours * t.hourly_rate) as total_cost'),
			$qb->createFunction('COUNT(*) as entry_count')
		)
			->from($this->getTableName(), 't')
			->innerJoin('t', 'projects', 'p', $qb->expr()->eq('t.project_id', 'p.id'))
			->innerJoin('p', 'customers', 'c', $qb->expr()->eq('p.customer_id', 'c.id'))
			->groupBy($qb->createFunction('YEAR(t.date)'), 'p.project_type', 'c.id')
			->orderBy('year', 'DESC')
			->addOrderBy('p.project_type', 'ASC')
			->addOrderBy('c.name', 'ASC');

		$result = $qb->executeQuery();
		$detailedStats = [];
		while ($row = $result->fetch()) {
			$year = (int) $row['year'];
			$projectType = $row['project_type'] ?: 'client';
			$customerId = (int) $row['customer_id'];

			if (!isset($detailedStats[$year])) {
				$detailedStats[$year] = [];
			}
			if (!isset($detailedStats[$year][$projectType])) {
				$detailedStats[$year][$projectType] = [
					'project_type' => $projectType,
					'total_hours' => 0,
					'total_cost' => 0,
					'total_entries' => 0,
					'customers' => []
				];
			}
			if (!isset($detailedStats[$year][$projectType]['customers'][$customerId])) {
				$detailedStats[$year][$projectType]['customers'][$customerId] = [
					'customer_id' => $customerId,
					'customer_name' => $row['customer_name'],
					'total_hours' => 0,
					'total_cost' => 0,
					'entry_count' => 0
				];
			}

			$customerData = [
				'customer_id' => $customerId,
				'customer_name' => $row['customer_name'],
				'total_hours' => (float) $row['total_hours'],
				'total_cost' => (float) $row['total_cost'],
				'entry_count' => (int) $row['entry_count']
			];

			$detailedStats[$year][$projectType]['customers'][$customerId] = $customerData;
			$detailedStats[$year][$projectType]['total_hours'] += $customerData['total_hours'];
			$detailedStats[$year][$projectType]['total_cost'] += $customerData['total_cost'];
			$detailedStats[$year][$projectType]['total_entries'] += $customerData['entry_count'];
		}
		$result->closeCursor();

		return $detailedStats;
	}

	/**
	 * Get project type display name
	 *
	 * @param string $projectType
	 * @return string
	 */
	private function getProjectTypeDisplayName(string $projectType): string
	{
		$types = [
			'client' => 'Client Project',
			'admin' => 'Administrative',
			'sales' => 'Sales & Marketing',
			'customer' => 'Customer Support',
			'product' => 'Product Development',
			'meeting' => 'Meetings & Overhead',
			'internal' => 'Internal Project',
			'research' => 'Research & Development',
			'training' => 'Training & Education',
			'other' => 'Other'
		];

		return $types[$projectType] ?? 'Client Project';
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
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from($table)
				->setMaxResults(1);
			$result = $qb->executeQuery();
			$row = $result->fetch();
			$result->closeCursor();

			return isset($row[$column]);
		} catch (\Exception $e) {
			return false;
		}
	}

	/**
	 * Get productivity analysis (billable vs overhead)
	 *
	 * @return array
	 */
	public function getProductivityAnalysis(): array
	{
		// Return empty array if project_type column doesn't exist
		if (!$this->columnExists('projects', 'project_type')) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select(
			$qb->createFunction('YEAR(t.date) as year'),
			'p.project_type',
			$qb->createFunction('SUM(t.hours) as total_hours'),
			$qb->createFunction('SUM(t.hours * t.hourly_rate) as total_cost'),
			$qb->createFunction('COUNT(*) as entry_count')
		)
			->from($this->getTableName(), 't')
			->innerJoin('t', 'projects', 'p', $qb->expr()->eq('t.project_id', 'p.id'))
			->groupBy($qb->createFunction('YEAR(t.date)'), 'p.project_type')
			->orderBy('year', 'DESC')
			->addOrderBy('p.project_type', 'ASC');

		$result = $qb->executeQuery();
		$productivityStats = [];
		while ($row = $result->fetch()) {
			$year = (int) $row['year'];
			$projectType = $row['project_type'] ?: 'client';

			if (!isset($productivityStats[$year])) {
				$productivityStats[$year] = [
					'year' => $year,
					'billable' => [
						'total_hours' => 0,
						'total_cost' => 0,
						'entry_count' => 0
					],
					'overhead' => [
						'total_hours' => 0,
						'total_cost' => 0,
						'entry_count' => 0
					],
					'by_type' => []
				];
			}

			$hours = (float) $row['total_hours'];
			$cost = (float) $row['total_cost'];
			$entries = (int) $row['entry_count'];

			// Categorize as billable or overhead
			$overheadTypes = ['admin', 'meeting', 'internal', 'training'];
			if (in_array($projectType, $overheadTypes)) {
				$productivityStats[$year]['overhead']['total_hours'] += $hours;
				$productivityStats[$year]['overhead']['total_cost'] += $cost;
				$productivityStats[$year]['overhead']['entry_count'] += $entries;
			} else {
				$productivityStats[$year]['billable']['total_hours'] += $hours;
				$productivityStats[$year]['billable']['total_cost'] += $cost;
				$productivityStats[$year]['billable']['entry_count'] += $entries;
			}

			$productivityStats[$year]['by_type'][$projectType] = [
				'project_type' => $projectType,
				'total_hours' => $hours,
				'total_cost' => $cost,
				'entry_count' => $entries
			];
		}
		$result->closeCursor();

		return $productivityStats;
	}

	/**
	 * Map database row to entity
	 *
	 * @param array $row Database row
	 * @return TimeEntry
	 */
	protected function mapRowToEntity(array $row): TimeEntry
	{
		$timeEntry = new TimeEntry();
		$timeEntry->setId((int) $row['id']);
		$timeEntry->setProjectId((int) $row['project_id']);
		$timeEntry->setUserId($row['user_id']);
		$timeEntry->setDate(SafeDateTime::fromRequired($row['date'] ?? null, 'time_entries.date'));
		$timeEntry->setHours((float) $row['hours']);
		$timeEntry->setDescription($row['description']);
		$timeEntry->setHourlyRate((float) $row['hourly_rate']);
		$timeEntry->setCreatedAt(SafeDateTime::fromRequired($row['created_at'] ?? null, 'time_entries.created_at'));
		$timeEntry->setUpdatedAt(SafeDateTime::fromRequired($row['updated_at'] ?? null, 'time_entries.updated_at'));

		return $timeEntry;
	}

	/**
	 * Get last activity date for a project
	 *
	 * @param int $projectId Project ID
	 * @return \DateTime|null
	 */
	public function getLastActivityDateForProject(int $projectId): ?\DateTime
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('date')
			->from($this->getTableName())
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId)))
			->orderBy('date', 'DESC')
			->setMaxResults(1);

		$result = $qb->executeQuery();
		$row = $result->fetch();
		$result->closeCursor();

		if ($row && !empty($row['date'])) {
			return SafeDateTime::fromOptional($row['date']);
		}

		return null;
	}

	/**
	 * Get yearly statistics by project type for a specific employee
	 *
	 * @param string $userId
	 * @return array
	 */
	public function getYearlyStatsByProjectTypeForEmployee(string $userId): array
	{
		// Return empty array if project_type column doesn't exist
		if (!$this->columnExists('projects', 'project_type')) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select(
			$qb->createFunction('YEAR(t.date) as year'),
			'p.project_type',
			$qb->createFunction('SUM(t.hours) as total_hours'),
			$qb->createFunction('SUM(t.hours * t.hourly_rate) as total_cost'),
			$qb->createFunction('COUNT(*) as entry_count')
		)
			->from($this->getTableName(), 't')
			->innerJoin('t', 'projects', 'p', $qb->expr()->eq('t.project_id', 'p.id'))
			->where($qb->expr()->eq('t.user_id', $qb->createNamedParameter($userId)))
			->groupBy($qb->createFunction('YEAR(t.date)'), 'p.project_type')
			->orderBy('year', 'DESC')
			->addOrderBy('p.project_type', 'ASC');

		$result = $qb->executeQuery();
		$stats = [];

		while ($row = $result->fetch()) {
			$year = $row['year'];
			$projectType = $row['project_type'];

			if (!isset($stats[$year])) {
				$stats[$year] = [];
			}

			$stats[$year][$projectType] = [
				'project_type' => $projectType,
				'total_hours' => (float) $row['total_hours'],
				'total_cost' => (float) $row['total_cost'],
				'entry_count' => (int) $row['entry_count']
			];
		}
		$result->closeCursor();

		return $stats;
	}

	/**
	 * Get detailed yearly statistics by project type for all employees
	 *
	 * @return array
	 */
	public function getDetailedYearlyStatsByProjectTypeForEmployees(): array
	{
		// Return empty array if project_type column doesn't exist
		if (!$this->columnExists('projects', 'project_type')) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select(
			$qb->createFunction('YEAR(t.date) as year'),
			't.user_id',
			$qb->createFunction('MAX(COALESCE(s.display_name, u.displayname, t.user_id)) as user_display_name'),
			'p.project_type',
			$qb->createFunction('SUM(t.hours) as total_hours'),
			$qb->createFunction('SUM(t.hours * t.hourly_rate) as total_cost'),
			$qb->createFunction('COUNT(*) as entry_count')
		)
			->from($this->getTableName(), 't')
			->innerJoin('t', 'projects', 'p', $qb->expr()->eq('t.project_id', 'p.id'))
			->leftJoin('t', 'pc_user_account_snapshots', 's', $qb->expr()->eq('t.user_id', 's.user_id'))
			->leftJoin('t', 'users', 'u', $qb->expr()->eq('t.user_id', 'u.uid'))
			->groupBy($qb->createFunction('YEAR(t.date)'), 't.user_id', 'p.project_type')
			->orderBy('year', 'DESC')
			->addOrderBy($qb->createFunction('MAX(COALESCE(s.display_name, u.displayname, t.user_id))'), 'ASC')
			->addOrderBy('p.project_type', 'ASC');

		$result = $qb->executeQuery();
		$stats = [];

		while ($row = $result->fetch()) {
			$year = $row['year'];
			$userId = $row['user_id'];
			$projectType = $row['project_type'];

			if (!isset($stats[$year])) {
				$stats[$year] = [];
			}
			if (!isset($stats[$year][$userId])) {
				$stats[$year][$userId] = [
					'user_id' => $userId,
					'user_display_name' => $row['user_display_name'] ?: $userId,
					'by_type' => []
				];
			}

			$stats[$year][$userId]['by_type'][$projectType] = [
				'project_type' => $projectType,
				'total_hours' => (float) $row['total_hours'],
				'total_cost' => (float) $row['total_cost'],
				'entry_count' => (int) $row['entry_count']
			];
		}
		$result->closeCursor();

		return $stats;
	}

	/**
	 * Get productivity analysis for a specific employee
	 *
	 * @param string $userId
	 * @return array
	 */
	public function getProductivityAnalysisForEmployee(string $userId): array
	{
		// Return empty array if project_type column doesn't exist
		if (!$this->columnExists('projects', 'project_type')) {
			return [];
		}

		$qb = $this->db->getQueryBuilder();
		$qb->select(
			$qb->createFunction('YEAR(t.date) as year'),
			'p.project_type',
			$qb->createFunction('SUM(t.hours) as total_hours'),
			$qb->createFunction('SUM(t.hours * t.hourly_rate) as total_cost'),
			$qb->createFunction('COUNT(*) as entry_count')
		)
			->from($this->getTableName(), 't')
			->innerJoin('t', 'projects', 'p', $qb->expr()->eq('t.project_id', 'p.id'))
			->where($qb->expr()->eq('t.user_id', $qb->createNamedParameter($userId)))
			->groupBy($qb->createFunction('YEAR(t.date)'), 'p.project_type')
			->orderBy('year', 'DESC')
			->addOrderBy('p.project_type', 'ASC');

		$result = $qb->executeQuery();
		$productivityStats = [];

		while ($row = $result->fetch()) {
			$year = $row['year'];
			$projectType = $row['project_type'];

			if (!isset($productivityStats[$year])) {
				$productivityStats[$year] = [
					'billable' => ['total_hours' => 0, 'total_cost' => 0, 'entry_count' => 0],
					'overhead' => ['total_hours' => 0, 'total_cost' => 0, 'entry_count' => 0],
					'by_type' => []
				];
			}

			$hours = (float) $row['total_hours'];
			$cost = (float) $row['total_cost'];
			$entries = (int) $row['entry_count'];

			// Categorize as billable or overhead
			$overheadTypes = ['admin', 'meeting', 'internal', 'training'];
			if (in_array($projectType, $overheadTypes)) {
				$productivityStats[$year]['overhead']['total_hours'] += $hours;
				$productivityStats[$year]['overhead']['total_cost'] += $cost;
				$productivityStats[$year]['overhead']['entry_count'] += $entries;
			} else {
				$productivityStats[$year]['billable']['total_hours'] += $hours;
				$productivityStats[$year]['billable']['total_cost'] += $cost;
				$productivityStats[$year]['billable']['entry_count'] += $entries;
			}

			$productivityStats[$year]['by_type'][$projectType] = [
				'project_type' => $projectType,
				'total_hours' => $hours,
				'total_cost' => $cost,
				'entry_count' => $entries
			];
		}
		$result->closeCursor();

		return $productivityStats;
	}
}
