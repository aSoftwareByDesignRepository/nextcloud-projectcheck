<?php

declare(strict_types=1);

/**
 * TimeEntry mapper for projectcontrol app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Db;

use OCA\ProjectCheck\Util\BillingStatus;
use OCA\ProjectCheck\Util\Money;
use OCA\ProjectCheck\Util\SafeDateTime;
use OCP\AppFramework\Db\QBMapper;
use OCP\DB\QueryBuilder\IQueryBuilder;
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
		parent::__construct($db, 'pc_time_entries', TimeEntry::class);
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
		if (array_key_exists('project_ids', $filters) && is_array($filters['project_ids'])) {
			if ($filters['project_ids'] === []) {
				$qb->andWhere('1 = 0');
			} else {
				$qb->andWhere(
					$qb->expr()->in('project_id', $qb->createNamedParameter($filters['project_ids'], IQueryBuilder::PARAM_INT_ARRAY))
				);
			}
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
			$searchTerm = '%' . $this->db->escapeLikeParameter($filters['search']) . '%';
			$qb->andWhere($qb->expr()->iLike('description', $qb->createNamedParameter($searchTerm)));
		}

		$this->applyBillingStatusFilter($qb, '', $filters);
		$this->applyVisibilityScope($qb, '', $filters);

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
	 * Distinct ids of projects on which the user owns at least one time entry.
	 *
	 * Used so a user's own historical entries stay reachable (list filter,
	 * visibility) even after their project membership has ended.
	 *
	 * @return list<int>
	 */
	public function findDistinctProjectIdsByUser(string $userId): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->selectDistinct('project_id')
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

		$result = $qb->executeQuery();
		$ids = [];
		while ($row = $result->fetch()) {
			$ids[] = (int) $row['project_id'];
		}
		$result->closeCursor();

		return $ids;
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
		$qb->select($qb->createFunction('COUNT(*)'));
		$tablePrefix = $this->configureFilteredListFrom($qb, $filters);
		$this->applyFilteredListConstraints($qb, $filters, $tablePrefix);

		$result = $qb->executeQuery();
		$count = $result->fetchColumn();
		$result->closeCursor();

		return (int) $count;
	}

	/**
	 * Sum hours for entries matching list filters (same keys as count / export).
	 */
	public function sumHours(array $filters = []): float
	{
		$qb = $this->db->getQueryBuilder();
		$tablePrefix = $this->configureFilteredListFrom($qb, $filters);
		$hoursColumn = $tablePrefix . 'hours';
		$qb->select($qb->createFunction('COALESCE(SUM(' . $hoursColumn . '), 0)'));
		$this->applyFilteredListConstraints($qb, $filters, $tablePrefix);

		$result = $qb->executeQuery();
		$total = $result->fetchColumn();
		$result->closeCursor();

		return Money::asFloat(Money::normalize($total ?? 0, Money::HOUR_SCALE));
	}

	/**
	 * Per-billing-status hours and amounts for entries matching list filters.
	 *
	 * Powers the settlement summary strip and bulk previews. Amounts round
	 * each entry to 2 decimals before summing (matches {@see Money::mul} and
	 * the counter recompute).
	 *
	 * @param array<string,mixed> $filters same keys as count()/sumHours()
	 * @return array<string, array{hours: float, amount: float, count: int}> keyed by billing status
	 */
	public function sumBillingBuckets(array $filters = []): array
	{
		unset($filters['limit'], $filters['offset']);
		$qb = $this->db->getQueryBuilder();
		$tablePrefix = $this->configureFilteredListFrom($qb, $filters);
		$statusColumn = $tablePrefix . 'billing_status';
		$hoursColumn = $tablePrefix . 'hours';
		$rateColumn = $tablePrefix . 'hourly_rate';

		$qb->select($statusColumn)
			->selectAlias($qb->createFunction('COALESCE(SUM(' . $hoursColumn . '), 0)'), 'sum_hours')
			->selectAlias($qb->createFunction('COALESCE(SUM(ROUND(' . $hoursColumn . ' * ' . $rateColumn . ', 2)), 0)'), 'sum_amount')
			->selectAlias($qb->createFunction('COUNT(*)'), 'entry_count')
			->groupBy($statusColumn);
		$this->applyFilteredListConstraints($qb, $filters, $tablePrefix);

		$buckets = [];
		foreach (BillingStatus::ALL as $status) {
			$buckets[$status] = ['hours' => 0.0, 'amount' => 0.0, 'count' => 0];
		}

		$result = $qb->executeQuery();
		while ($row = $result->fetch()) {
			// Normalize unknown/legacy status labels into open. Accumulate so two
			// raw groups that normalize to the same status cannot overwrite each other.
			$status = BillingStatus::normalize($row['billing_status'] ?? null);
			$hours = Money::normalize($row['sum_hours'] ?? 0, Money::HOUR_SCALE);
			$amount = Money::normalize($row['sum_amount'] ?? 0, Money::MONEY_SCALE);
			$count = (int) ($row['entry_count'] ?? 0);
			$buckets[$status] = [
				'hours' => Money::asFloat(Money::add($buckets[$status]['hours'], $hours, Money::HOUR_SCALE), Money::HOUR_SCALE),
				'amount' => Money::asFloat(Money::add($buckets[$status]['amount'], $amount, Money::MONEY_SCALE)),
				'count' => (int) $buckets[$status]['count'] + $count,
			];
		}
		$result->closeCursor();

		return $buckets;
	}

	/**
	 * Entry ids matching list filters, ascending (stable lock order for bulk
	 * settlement, spec §10.2). Fetches limit+1 rows so callers can detect
	 * an over-cap result set without a second COUNT round-trip.
	 *
	 * @param array<string,mixed> $filters
	 * @return list<int>
	 */
	public function findIdsByFilters(array $filters, int $limit): array
	{
		unset($filters['limit'], $filters['offset']);
		$qb = $this->db->getQueryBuilder();
		$tablePrefix = $this->configureFilteredListFrom($qb, $filters);
		$qb->select($tablePrefix . 'id')
			->orderBy($tablePrefix . 'id', 'ASC')
			->setMaxResults(max(1, $limit) + 1);
		$this->applyFilteredListConstraints($qb, $filters, $tablePrefix);

		$ids = [];
		$result = $qb->executeQuery();
		while ($row = $result->fetch()) {
			$ids[] = (int) $row['id'];
		}
		$result->closeCursor();

		return $ids;
	}

	/**
	 * Load entries by id, ascending. Missing ids are silently absent.
	 *
	 * @param list<int> $ids
	 * @return list<TimeEntry>
	 */
	public function findByIds(array $ids): array
	{
		$ids = array_values(array_unique(array_map('intval', $ids)));
		if ($ids === []) {
			return [];
		}

		$entries = [];
		foreach (array_chunk($ids, 500) as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select('*')
				->from($this->getTableName())
				->where($qb->expr()->in('id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)))
				->orderBy('id', 'ASC');

			$result = $qb->executeQuery();
			while ($row = $result->fetch()) {
				$entries[] = $this->mapRowToEntity($row);
			}
			$result->closeCursor();
		}

		return $entries;
	}

	/**
	 * Guarded billing-state write (optimistic lock, spec §10.1).
	 *
	 * The UPDATE only applies while the row still has the expected billing
	 * status and updated_at — a concurrent settler or owner edit makes the
	 * predicate fail (0 rows) and the caller reports a conflict instead of
	 * silently double-applying counter deltas.
	 *
	 * @param array<string, string|\DateTimeInterface|null> $set billing columns to write
	 * @return int affected rows (0 = conflict)
	 */
	public function updateBillingGuarded(
		int $id,
		string $expectedStatus,
		string $expectedUpdatedAt,
		array $set,
	): int {
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName());

		$allowed = ['billing_status', 'billed_at', 'paid_at', 'billing_changed_by', 'billing_changed_at', 'updated_at'];
		foreach ($set as $column => $value) {
			if (!in_array($column, $allowed, true)) {
				throw new \InvalidArgumentException('Column not allowed in billing update: ' . $column);
			}
			if ($value === null) {
				$qb->set($column, $qb->createNamedParameter(null, IQueryBuilder::PARAM_NULL));
			} elseif ($value instanceof \DateTimeInterface) {
				$qb->set($column, $qb->createNamedParameter($value->format('Y-m-d H:i:s')));
			} else {
				$qb->set($column, $qb->createNamedParameter((string) $value));
			}
		}

		$qb->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('billing_status', $qb->createNamedParameter($expectedStatus)))
			->andWhere($qb->expr()->eq('updated_at', $qb->createNamedParameter($expectedUpdatedAt)));

		return (int) $qb->executeStatement();
	}

	/**
	 * Guarded owner/AZC content write: only applies while the row is still
	 * unlocked (open / excluded) and unchanged since it was read. Returns
	 * affected rows; 0 means a concurrent settlement or edit intervened
	 * (caller decides between 409-locked and generic conflict).
	 */
	public function updateContentGuarded(TimeEntry $entry, string $expectedStatus, string $expectedUpdatedAt): int
	{
		if (!in_array($expectedStatus, [BillingStatus::OPEN, BillingStatus::EXCLUDED], true)) {
			return 0;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->update($this->getTableName())
			->set('project_id', $qb->createNamedParameter((int) $entry->getProjectId(), IQueryBuilder::PARAM_INT))
			->set('date', $qb->createNamedParameter($entry->getDate()->format('Y-m-d')))
			->set('hours', $qb->createNamedParameter((string) $entry->getHours()))
			->set('description', $qb->createNamedParameter($entry->getDescription()))
			->set('hourly_rate', $qb->createNamedParameter((string) $entry->getHourlyRate()))
			->set('updated_at', $qb->createNamedParameter($entry->getUpdatedAt()->format('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter((int) $entry->getId(), IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('billing_status', $qb->createNamedParameter($expectedStatus)))
			->andWhere($qb->expr()->eq('updated_at', $qb->createNamedParameter($expectedUpdatedAt)));

		return (int) $qb->executeStatement();
	}

	/**
	 * Guarded delete: only removes the row while it is still exactly in the
	 * unlocked state the caller read (status + updated_at). Guarding on the
	 * precise status keeps the caller's counter decrement pointed at the right
	 * bucket even if the row hopped open<->excluded meanwhile.
	 * Returns affected rows (0 = changed meanwhile or gone).
	 */
	public function deleteGuardedUnlocked(int $id, string $expectedStatus, string $expectedUpdatedAt): int
	{
		if (!in_array($expectedStatus, [BillingStatus::OPEN, BillingStatus::EXCLUDED], true)) {
			return 0;
		}
		return $this->deleteGuardedExact($id, $expectedStatus, $expectedUpdatedAt);
	}

	/**
	 * Guarded delete without a status whitelist (maintenance paths that may
	 * remove settled rows). Still guards on the exact read state so the
	 * caller's counter decrement targets the right bucket.
	 */
	public function deleteGuardedExact(int $id, string $expectedStatus, string $expectedUpdatedAt): int
	{
		$qb = $this->db->getQueryBuilder();
		$qb->delete($this->getTableName())
			->where($qb->expr()->eq('id', $qb->createNamedParameter($id, IQueryBuilder::PARAM_INT)))
			->andWhere($qb->expr()->eq('billing_status', $qb->createNamedParameter($expectedStatus)))
			->andWhere($qb->expr()->eq('updated_at', $qb->createNamedParameter($expectedUpdatedAt)));

		return (int) $qb->executeStatement();
	}

	/**
	 * @return string Table alias prefix for time-entry columns (`t.` or empty).
	 */
	private function configureFilteredListFrom(IQueryBuilder $qb, array $filters): string
	{
		$needsProjectJoin = !empty($filters['project_type']) || !empty($filters['search']);
		if ($needsProjectJoin) {
			$qb->from($this->getTableName(), 't')
				->innerJoin('t', 'pc_projects', 'p', $qb->expr()->eq('t.project_id', 'p.id'))
				->innerJoin('p', 'pc_customers', 'c', $qb->expr()->eq('p.customer_id', 'c.id'));
			return 't.';
		}

		$qb->from($this->getTableName());
		return '';
	}

	/**
	 * @param array<string,mixed> $filters
	 */
	private function applyFilteredListConstraints(IQueryBuilder $qb, array $filters, string $tablePrefix): void
	{
		if (!empty($filters['project_id'])) {
			$qb->andWhere($qb->expr()->eq($tablePrefix . 'project_id', $qb->createNamedParameter($filters['project_id'])));
		}
		if (array_key_exists('project_ids', $filters) && is_array($filters['project_ids'])) {
			if ($filters['project_ids'] === []) {
				$qb->andWhere('1 = 0');
			} else {
				$qb->andWhere(
					$qb->expr()->in($tablePrefix . 'project_id', $qb->createNamedParameter($filters['project_ids'], IQueryBuilder::PARAM_INT_ARRAY))
				);
			}
		}

		if (!empty($filters['user_id'])) {
			$qb->andWhere($qb->expr()->eq($tablePrefix . 'user_id', $qb->createNamedParameter($filters['user_id'])));
		}

		if (!empty($filters['project_type'])) {
			$qb->andWhere($qb->expr()->eq('p.project_type', $qb->createNamedParameter($filters['project_type'])));
		}

		if (!empty($filters['date_from'])) {
			$qb->andWhere($qb->expr()->gte($tablePrefix . 'date', $qb->createNamedParameter($filters['date_from'])));
		}

		if (!empty($filters['date_to'])) {
			$qb->andWhere($qb->expr()->lte($tablePrefix . 'date', $qb->createNamedParameter($filters['date_to'])));
		}

		if (!empty($filters['search'])) {
			$searchTerm = '%' . $this->db->escapeLikeParameter($filters['search']) . '%';
			$qb->andWhere(
				$qb->expr()->orX(
					$qb->expr()->iLike($tablePrefix . 'description', $qb->createNamedParameter($searchTerm)),
					$qb->expr()->iLike('p.name', $qb->createNamedParameter($searchTerm)),
					$qb->expr()->iLike('c.name', $qb->createNamedParameter($searchTerm))
				)
			);
		}

		$this->applyBillingStatusFilter($qb, $tablePrefix, $filters);
		$this->applyVisibilityScope($qb, $tablePrefix, $filters);
	}

	/**
	 * Settlement status filter. Accepts an exact status, the virtual
	 * `outstanding` value (= open + invoiced, spec D9), or a list of statuses.
	 *
	 * @param array<string,mixed> $filters
	 */
	private function applyBillingStatusFilter(IQueryBuilder $qb, string $tablePrefix, array $filters): void
	{
		$value = $filters['billing_status'] ?? null;
		if ($value === null || $value === '' || $value === 'all') {
			return;
		}
		$field = $tablePrefix . 'billing_status';

		if (is_array($value)) {
			$statuses = array_values(array_filter($value, [BillingStatus::class, 'isValid']));
			if ($statuses === []) {
				$qb->andWhere('1 = 0');
				return;
			}
			$qb->andWhere(
				$qb->expr()->in($field, $qb->createNamedParameter($statuses, IQueryBuilder::PARAM_STR_ARRAY))
			);
			return;
		}

		$value = (string) $value;
		if ($value === 'outstanding') {
			$qb->andWhere(
				$qb->expr()->in($field, $qb->createNamedParameter(BillingStatus::OUTSTANDING, IQueryBuilder::PARAM_STR_ARRAY))
			);
			return;
		}

		if (BillingStatus::isValid($value)) {
			$qb->andWhere($qb->expr()->eq($field, $qb->createNamedParameter($value)));
			return;
		}

		// Unknown value: match nothing rather than silently everything.
		$qb->andWhere('1 = 0');
	}

	/**
	 * OR-combined visibility scope for non-global settlers (spec §8.1):
	 * a Manager sees their own entries everywhere plus *all* entries on the
	 * projects they can settle. `visible_to` = ['user_id' => uid,
	 * 'project_ids' => list<int>].
	 *
	 * @param array<string,mixed> $filters
	 */
	private function applyVisibilityScope(IQueryBuilder $qb, string $tablePrefix, array $filters): void
	{
		$scope = $filters['visible_to'] ?? null;
		if (!is_array($scope)) {
			return;
		}
		$uid = (string) ($scope['user_id'] ?? '');
		$projectIds = $scope['project_ids'] ?? [];
		$projectIds = is_array($projectIds) ? array_values(array_map('intval', $projectIds)) : [];

		$conditions = [];
		if ($uid !== '') {
			$conditions[] = $qb->expr()->eq($tablePrefix . 'user_id', $qb->createNamedParameter($uid));
		}
		if ($projectIds !== []) {
			$conditions[] = $qb->expr()->in(
				$tablePrefix . 'project_id',
				$qb->createNamedParameter($projectIds, IQueryBuilder::PARAM_INT_ARRAY)
			);
		}

		if ($conditions === []) {
			$qb->andWhere('1 = 0');
			return;
		}
		$qb->andWhere(count($conditions) === 1 ? $conditions[0] : $qb->expr()->orX(...$conditions));
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
		$qb->select('hours', 'hourly_rate')
			->from($this->getTableName())
			->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId)));

		$result = $qb->executeQuery();
		$total = '0';
		while ($row = $result->fetch()) {
			$line = Money::mul($row['hours'] ?? 0, $row['hourly_rate'] ?? 0);
			$total = Money::add($total, $line);
		}
		$result->closeCursor();

		return Money::asFloat($total);
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
	 * Total billed cost for all of a user's time entries (every project).
	 */
	public function getTotalCostForUser(string $userId): float
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select($qb->createFunction('SUM(hours * hourly_rate)'))
			->from($this->getTableName())
			->where($qb->expr()->eq('user_id', $qb->createNamedParameter($userId)));

		$result = $qb->executeQuery();
		$total = $result->fetchColumn();
		$result->closeCursor();

		return (float) ($total ?? 0);
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

		// Base columns. `user_display_name` uses selectAlias + createFunction so
		// identifiers are quoted per NC rules (avoids fragile `… as …` handling).
		// `project_type_display_name` stays PHP-derived in the row loop below.
		$qb->from($this->getTableName(), 't')
			->innerJoin('t', 'pc_projects', 'p', $qb->expr()->eq('t.project_id', 'p.id'))
			->innerJoin('p', 'pc_customers', 'c', $qb->expr()->eq('p.customer_id', 'c.id'))
			->leftJoin('t', 'pc_user_account_snapshots', 's', $qb->expr()->eq('t.user_id', 's.user_id'))
			->leftJoin('t', 'users', 'u', $qb->expr()->eq('t.user_id', 'u.uid'))
			->select(
				't.*',
				'p.name as project_name',
				'p.project_type',
				'c.name as customer_name',
			)
			->selectAlias(
				$qb->createFunction(SqlPortableExpressions::coalesceUserDisplayName()),
				'user_display_name'
			)
			->orderBy('t.date', 'DESC')
			->addOrderBy('t.created_at', 'DESC');

		// Apply filters
		if (!empty($filters['project_id'])) {
			$qb->andWhere($qb->expr()->eq('t.project_id', $qb->createNamedParameter($filters['project_id'])));
		}
		if (array_key_exists('project_ids', $filters) && is_array($filters['project_ids'])) {
			if ($filters['project_ids'] === []) {
				$qb->andWhere('1 = 0');
			} else {
				$qb->andWhere(
					$qb->expr()->in('t.project_id', $qb->createNamedParameter($filters['project_ids'], IQueryBuilder::PARAM_INT_ARRAY))
				);
			}
		}

		if (!empty($filters['user_id'])) {
			$qb->andWhere($qb->expr()->eq('t.user_id', $qb->createNamedParameter($filters['user_id'])));
		}

		if (!empty($filters['project_type'])) {
			$qb->andWhere($qb->expr()->eq('p.project_type', $qb->createNamedParameter($filters['project_type'])));
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

		$this->applyBillingStatusFilter($qb, 't.', $filters);
		$this->applyVisibilityScope($qb, 't.', $filters);

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
	public function findUsersWithTimeEntries(?array $projectIds = null): array
	{
		$qb = $this->db->getQueryBuilder();
		$coalesce = SqlPortableExpressions::coalesceUserDisplayNameAggregated();
		$qb->from($this->getTableName(), 't')
			->leftJoin('t', 'pc_user_account_snapshots', 's', $qb->expr()->eq('t.user_id', 's.user_id'))
			->leftJoin('t', 'users', 'u', $qb->expr()->eq('t.user_id', 'u.uid'))
			->select('t.user_id')
			->selectAlias($qb->createFunction($coalesce), 'displayname')
			->groupBy('t.user_id')
			->orderBy($qb->createFunction($coalesce), 'ASC');
		if ($projectIds !== null) {
			if ($projectIds === []) {
				return [];
			}
			$qb->andWhere(
				$qb->expr()->in('t.project_id', $qb->createNamedParameter($projectIds, IQueryBuilder::PARAM_INT_ARRAY))
			);
		}

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
		$yearExpr = SqlPortableExpressions::yearFromColumn($this->db, 't.date');
		$qb->select(
			$qb->createFunction($yearExpr . ' as year'),
			$qb->createFunction('SUM(t.hours) as total_hours'),
			$qb->createFunction('SUM(t.hours * t.hourly_rate) as total_cost'),
			$qb->createFunction('COUNT(*) as entry_count')
		)
			->from($this->getTableName(), 't')
			->where($qb->expr()->eq('t.project_id', $qb->createNamedParameter($projectId)))
			->groupBy($qb->createFunction($yearExpr))
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
		$yearExpr = SqlPortableExpressions::yearFromColumn($this->db, 't.date');
		$qb->select(
			$qb->createFunction($yearExpr . ' as year'),
			$qb->createFunction('SUM(t.hours) as total_hours'),
			$qb->createFunction('SUM(t.hours * t.hourly_rate) as total_cost'),
			$qb->createFunction('COUNT(*) as entry_count')
		)
			->from($this->getTableName(), 't')
			->groupBy($qb->createFunction($yearExpr))
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
	 * Get yearly statistics for all projects with optional project/user scope.
	 *
	 * @param list<int>|null $projectIds
	 * @param list<string>|null $userIds
	 * @return array<int, array<string, int|float>>
	 */
	public function getYearlyStatsForScope(?array $projectIds = null, ?array $userIds = null): array
	{
		$qb = $this->db->getQueryBuilder();
		$yearExpr = SqlPortableExpressions::yearFromColumn($this->db, 't.date');
		$qb->select(
			$qb->createFunction($yearExpr . ' as year'),
			$qb->createFunction('SUM(t.hours) as total_hours'),
			$qb->createFunction('SUM(t.hours * t.hourly_rate) as total_cost'),
			$qb->createFunction('COUNT(*) as entry_count')
		)
			->from($this->getTableName(), 't');
		$this->applyProjectScope($qb, 't.project_id', $projectIds);
		$this->applyUserScope($qb, 't.user_id', $userIds);
		$qb->groupBy($qb->createFunction($yearExpr))
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
	public function getYearlyStatsForCustomer(int $customerId, ?array $projectIds = null, ?array $userIds = null): array
	{
		$qb = $this->db->getQueryBuilder();
		$yearExpr = SqlPortableExpressions::yearFromColumn($this->db, 't.date');
		$qb->select(
			$qb->createFunction($yearExpr . ' as year'),
			$qb->createFunction('SUM(t.hours) as total_hours'),
			$qb->createFunction('SUM(t.hours * t.hourly_rate) as total_cost'),
			$qb->createFunction('COUNT(*) as entry_count')
		)
			->from($this->getTableName(), 't')
			->innerJoin('t', 'pc_projects', 'p', $qb->expr()->eq('t.project_id', 'p.id'))
			->where($qb->expr()->eq('p.customer_id', $qb->createNamedParameter($customerId)));
		$this->applyProjectScope($qb, 't.project_id', $projectIds);
		$this->applyUserScope($qb, 't.user_id', $userIds);
		$qb->groupBy($qb->createFunction($yearExpr))
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
	public function getDetailedYearlyStats(?array $projectIds = null, ?array $userIds = null): array
	{
		$qb = $this->db->getQueryBuilder();
		$yearExpr = SqlPortableExpressions::yearFromColumn($this->db, 't.date');
		$qb->select(
			$qb->createFunction($yearExpr . ' as year'),
			'c.id as customer_id',
			'c.name as customer_name',
			'p.id as project_id',
			'p.name as project_name',
			$qb->createFunction('SUM(t.hours) as total_hours'),
			$qb->createFunction('SUM(t.hours * t.hourly_rate) as total_cost'),
			$qb->createFunction('COUNT(*) as entry_count')
		)
			->from($this->getTableName(), 't')
			->innerJoin('t', 'pc_projects', 'p', $qb->expr()->eq('t.project_id', 'p.id'))
			->innerJoin('p', 'pc_customers', 'c', $qb->expr()->eq('p.customer_id', 'c.id'));
		$this->applyProjectScope($qb, 't.project_id', $projectIds);
		$this->applyUserScope($qb, 't.user_id', $userIds);
		$qb->groupBy($qb->createFunction($yearExpr), 'c.id', 'p.id')
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
		$yearExpr = SqlPortableExpressions::yearFromColumn($this->db, 't.date');
		$qb->select(
			$qb->createFunction($yearExpr . ' as year'),
			$qb->createFunction('SUM(t.hours) as total_hours'),
			$qb->createFunction('SUM(t.hours * t.hourly_rate) as total_cost'),
			$qb->createFunction('COUNT(*) as entry_count')
		)
			->from($this->getTableName(), 't')
			->where($qb->expr()->eq('t.user_id', $qb->createNamedParameter($userId)))
			->groupBy($qb->createFunction($yearExpr))
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
	public function getEmployeeYearlyStats(?array $projectIds = null, ?array $userIds = null): array
	{
		$qb = $this->db->getQueryBuilder();
		$yearExpr = SqlPortableExpressions::yearFromColumn($this->db, 't.date');
		$maxDisplay = SqlPortableExpressions::maxCoalesceUserDisplayName();
		$qb->select(
			$qb->createFunction($yearExpr . ' as year'),
			't.user_id',
			$qb->createFunction($maxDisplay . ' as user_display_name'),
			$qb->createFunction('SUM(t.hours) as total_hours'),
			$qb->createFunction('SUM(t.hours * t.hourly_rate) as total_cost'),
			$qb->createFunction('COUNT(*) as entry_count')
		)
			->from($this->getTableName(), 't')
			->leftJoin('t', 'pc_user_account_snapshots', 's', $qb->expr()->eq('t.user_id', 's.user_id'))
			->leftJoin('t', 'users', 'u', $qb->expr()->eq('t.user_id', 'u.uid'));
		$this->applyProjectScope($qb, 't.project_id', $projectIds);
		$this->applyUserScope($qb, 't.user_id', $userIds);
		$qb->groupBy($qb->createFunction($yearExpr), 't.user_id')
			->orderBy('year', 'DESC')
			->addOrderBy($qb->createFunction($maxDisplay), 'ASC');

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
	public function getEmployeeComparisonStats(?array $projectIds = null, ?array $userIds = null): array
	{
		$qb = $this->db->getQueryBuilder();
		$maxDisplay = SqlPortableExpressions::maxCoalesceUserDisplayName();
		$qb->select(
			't.user_id',
			$qb->createFunction($maxDisplay . ' as user_display_name'),
			$qb->createFunction('SUM(t.hours) as total_hours'),
			$qb->createFunction('SUM(t.hours * t.hourly_rate) as total_cost'),
			$qb->createFunction('COUNT(*) as entry_count'),
			$qb->createFunction('AVG(t.hourly_rate) as avg_hourly_rate'),
			$qb->createFunction('MIN(t.date) as first_entry'),
			$qb->createFunction('MAX(t.date) as last_entry')
		)
			->from($this->getTableName(), 't')
			->leftJoin('t', 'pc_user_account_snapshots', 's', $qb->expr()->eq('t.user_id', 's.user_id'))
			->leftJoin('t', 'users', 'u', $qb->expr()->eq('t.user_id', 'u.uid'));
		$this->applyProjectScope($qb, 't.project_id', $projectIds);
		$this->applyUserScope($qb, 't.user_id', $userIds);
		$qb->groupBy('t.user_id')
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
	public function getYearlyStatsByProjectType(?array $projectIds = null, ?array $userIds = null): array
	{
		$qb = $this->db->getQueryBuilder();
		$yearExpr = SqlPortableExpressions::yearFromColumn($this->db, 't.date');
		$qb->select(
			$qb->createFunction($yearExpr . ' as year'),
			'p.project_type',
			$qb->createFunction('SUM(t.hours) as total_hours'),
			$qb->createFunction('SUM(t.hours * t.hourly_rate) as total_cost'),
			$qb->createFunction('COUNT(*) as entry_count')
		)
			->from($this->getTableName(), 't')
			->innerJoin('t', 'pc_projects', 'p', $qb->expr()->eq('t.project_id', 'p.id'));
		$this->applyProjectScope($qb, 't.project_id', $projectIds);
		$this->applyUserScope($qb, 't.user_id', $userIds);
		$qb->groupBy($qb->createFunction($yearExpr), 'p.project_type')
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
	public function getDetailedYearlyStatsByProjectType(?array $projectIds = null, ?array $userIds = null): array
	{
		$qb = $this->db->getQueryBuilder();
		$yearExpr = SqlPortableExpressions::yearFromColumn($this->db, 't.date');
		$qb->select(
			$qb->createFunction($yearExpr . ' as year'),
			'p.project_type',
			'c.id as customer_id',
			'c.name as customer_name',
			$qb->createFunction('SUM(t.hours) as total_hours'),
			$qb->createFunction('SUM(t.hours * t.hourly_rate) as total_cost'),
			$qb->createFunction('COUNT(*) as entry_count')
		)
			->from($this->getTableName(), 't')
			->innerJoin('t', 'pc_projects', 'p', $qb->expr()->eq('t.project_id', 'p.id'))
			->innerJoin('p', 'pc_customers', 'c', $qb->expr()->eq('p.customer_id', 'c.id'));
		$this->applyProjectScope($qb, 't.project_id', $projectIds);
		$this->applyUserScope($qb, 't.user_id', $userIds);
		$qb->groupBy($qb->createFunction($yearExpr), 'p.project_type', 'c.id')
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
	 * Get productivity analysis (billable vs overhead)
	 *
	 * @return array
	 */
	public function getProductivityAnalysis(?array $projectIds = null, ?array $userIds = null): array
	{
		$qb = $this->db->getQueryBuilder();
		$yearExpr = SqlPortableExpressions::yearFromColumn($this->db, 't.date');
		$qb->select(
			$qb->createFunction($yearExpr . ' as year'),
			'p.project_type',
			$qb->createFunction('SUM(t.hours) as total_hours'),
			$qb->createFunction('SUM(t.hours * t.hourly_rate) as total_cost'),
			$qb->createFunction('COUNT(*) as entry_count')
		)
			->from($this->getTableName(), 't')
			->innerJoin('t', 'pc_projects', 'p', $qb->expr()->eq('t.project_id', 'p.id'));
		$this->applyProjectScope($qb, 't.project_id', $projectIds);
		$this->applyUserScope($qb, 't.user_id', $userIds);
		$qb->groupBy($qb->createFunction($yearExpr), 'p.project_type')
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
		$timeEntry->setBillingStatus(BillingStatus::normalize($row['billing_status'] ?? null));
		$timeEntry->setBilledAt(SafeDateTime::fromOptional($row['billed_at'] ?? null));
		$timeEntry->setPaidAt(SafeDateTime::fromOptional($row['paid_at'] ?? null));
		$timeEntry->setBillingChangedBy($row['billing_changed_by'] ?? null);
		$timeEntry->setBillingChangedAt(SafeDateTime::fromOptional($row['billing_changed_at'] ?? null));

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
	public function getYearlyStatsByProjectTypeForEmployee(string $userId, ?array $projectIds = null): array
	{
		$qb = $this->db->getQueryBuilder();
		$yearExpr = SqlPortableExpressions::yearFromColumn($this->db, 't.date');
		$qb->select(
			$qb->createFunction($yearExpr . ' as year'),
			'p.project_type',
			$qb->createFunction('SUM(t.hours) as total_hours'),
			$qb->createFunction('SUM(t.hours * t.hourly_rate) as total_cost'),
			$qb->createFunction('COUNT(*) as entry_count')
		)
			->from($this->getTableName(), 't')
			->innerJoin('t', 'pc_projects', 'p', $qb->expr()->eq('t.project_id', 'p.id'))
			->where($qb->expr()->eq('t.user_id', $qb->createNamedParameter($userId)));
		$this->applyProjectScope($qb, 't.project_id', $projectIds);
		$qb->groupBy($qb->createFunction($yearExpr), 'p.project_type')
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
	public function getDetailedYearlyStatsByProjectTypeForEmployees(?array $projectIds = null, ?array $userIds = null): array
	{
		$qb = $this->db->getQueryBuilder();
		$yearExpr = SqlPortableExpressions::yearFromColumn($this->db, 't.date');
		$maxDisplay = SqlPortableExpressions::maxCoalesceUserDisplayName();
		$qb->select(
			$qb->createFunction($yearExpr . ' as year'),
			't.user_id',
			$qb->createFunction($maxDisplay . ' as user_display_name'),
			'p.project_type',
			$qb->createFunction('SUM(t.hours) as total_hours'),
			$qb->createFunction('SUM(t.hours * t.hourly_rate) as total_cost'),
			$qb->createFunction('COUNT(*) as entry_count')
		)
			->from($this->getTableName(), 't')
			->innerJoin('t', 'pc_projects', 'p', $qb->expr()->eq('t.project_id', 'p.id'))
			->leftJoin('t', 'pc_user_account_snapshots', 's', $qb->expr()->eq('t.user_id', 's.user_id'))
			->leftJoin('t', 'users', 'u', $qb->expr()->eq('t.user_id', 'u.uid'));
		$this->applyProjectScope($qb, 't.project_id', $projectIds);
		$this->applyUserScope($qb, 't.user_id', $userIds);
		$qb->groupBy($qb->createFunction($yearExpr), 't.user_id', 'p.project_type')
			->orderBy('year', 'DESC')
			->addOrderBy($qb->createFunction($maxDisplay), 'ASC')
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
	public function getProductivityAnalysisForEmployee(string $userId, ?array $projectIds = null): array
	{
		$qb = $this->db->getQueryBuilder();
		$yearExpr = SqlPortableExpressions::yearFromColumn($this->db, 't.date');
		$qb->select(
			$qb->createFunction($yearExpr . ' as year'),
			'p.project_type',
			$qb->createFunction('SUM(t.hours) as total_hours'),
			$qb->createFunction('SUM(t.hours * t.hourly_rate) as total_cost'),
			$qb->createFunction('COUNT(*) as entry_count')
		)
			->from($this->getTableName(), 't')
			->innerJoin('t', 'pc_projects', 'p', $qb->expr()->eq('t.project_id', 'p.id'))
			->where($qb->expr()->eq('t.user_id', $qb->createNamedParameter($userId)));
		$this->applyProjectScope($qb, 't.project_id', $projectIds);
		$qb->groupBy($qb->createFunction($yearExpr), 'p.project_type')
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

	/**
	 * @param list<int>|null $projectIds
	 */
	private function applyProjectScope(IQueryBuilder $qb, string $field, ?array $projectIds): void
	{
		if ($projectIds === null) {
			return;
		}
		if ($projectIds === []) {
			$qb->andWhere('1 = 0');
			return;
		}
		$qb->andWhere(
			$qb->expr()->in($field, $qb->createNamedParameter($projectIds, IQueryBuilder::PARAM_INT_ARRAY))
		);
	}

	/**
	 * @param list<string>|null $userIds
	 */
	private function applyUserScope(IQueryBuilder $qb, string $field, ?array $userIds): void
	{
		if ($userIds === null) {
			return;
		}
		if ($userIds === []) {
			$qb->andWhere('1 = 0');
			return;
		}
		$qb->andWhere(
			$qb->expr()->in($field, $qb->createNamedParameter($userIds, IQueryBuilder::PARAM_STR_ARRAY))
		);
	}
}
