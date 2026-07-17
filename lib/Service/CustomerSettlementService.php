<?php

declare(strict_types=1);

/**
 * Customer-level settlement rollup (spec §6.5, D13).
 *
 * Customers store NO settlement state. Everything here is a SUM over the
 * materialized project counters, scoped so Managers cannot enumerate
 * org-wide AR via projects they only Member on (spec §8.2 / §12.6):
 *
 *  - global settler → all projects for the customer
 *  - Manager / creator → settleable projects only
 *  - pure Member → accessible projects (read-only chips; D6 / E27)
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

use OCA\ProjectCheck\Util\Money;
use OCA\ProjectCheck\Util\SettlementPosture;
use OCA\ProjectCheck\Util\SettlementProgress;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class CustomerSettlementService
{
	public function __construct(
		private IDBConnection $db,
		private ProjectService $projectService,
	) {
	}

	/**
	 * Rollup for a single customer, scoped per viewer role (see class doc).
	 *
	 * @return array{
	 *   posture: string,
	 *   outstanding_hours: float,
	 *   outstanding_amount: float,
	 *   open_hours: float, invoiced_hours: float, paid_hours: float, excluded_hours: float,
	 *   open_amount: float, invoiced_amount: float, paid_amount: float, excluded_amount: float,
	 *   project_count: int
	 * }
	 */
	public function getSettlementForCustomer(int $customerId, string $userId): array
	{
		$map = $this->getSettlementForCustomers([$customerId], $userId);
		return $map[$customerId] ?? self::emptyRollup();
	}

	/**
	 * Rollup for many customers in one query (customer list page / exports).
	 * Missing customers (no in-scope projects) map to the empty rollup.
	 *
	 * @param list<int> $customerIds
	 * @return array<int, array<string, mixed>> customerId => rollup
	 */
	public function getSettlementForCustomers(array $customerIds, string $userId): array
	{
		$customerIds = array_values(array_unique(array_filter(array_map('intval', $customerIds), static fn (int $id): bool => $id > 0)));
		if ($customerIds === []) {
			return [];
		}

		$scopeIds = $this->resolveRollupProjectScope($userId);
		if ($scopeIds !== null && $scopeIds === []) {
			return array_fill_keys($customerIds, self::emptyRollup());
		}

		$rows = $this->fetchProjectCounterRows($customerIds, $scopeIds);

		/** @var array<int, array{sums: array<string, string>, postures: list<string>, count: int}> $perCustomer */
		$perCustomer = [];
		foreach ($rows as $row) {
			$cid = (int) $row['customer_id'];
			if (!isset($perCustomer[$cid])) {
				$perCustomer[$cid] = ['sums' => self::zeroSums(), 'postures' => [], 'count' => 0];
			}

			$counters = [
				'open_hours' => $row['stl_open_hours'],
				'invoiced_hours' => $row['stl_invoiced_hours'],
				'paid_hours' => $row['stl_paid_hours'],
				'excluded_hours' => $row['stl_excluded_hours'],
				'open_amount' => $row['stl_open_amount'],
				'invoiced_amount' => $row['stl_invoiced_amount'],
				'paid_amount' => $row['stl_paid_amount'],
				'excluded_amount' => $row['stl_excluded_amount'],
			];

			foreach (['open_hours', 'invoiced_hours', 'paid_hours', 'excluded_hours'] as $key) {
				$perCustomer[$cid]['sums'][$key] = Money::add($perCustomer[$cid]['sums'][$key], Money::normalize($counters[$key], Money::HOUR_SCALE), Money::HOUR_SCALE);
			}
			foreach (['open_amount', 'invoiced_amount', 'paid_amount', 'excluded_amount'] as $key) {
				$perCustomer[$cid]['sums'][$key] = Money::add($perCustomer[$cid]['sums'][$key], Money::normalize($counters[$key], Money::MONEY_SCALE), Money::MONEY_SCALE);
			}
			$perCustomer[$cid]['postures'][] = SettlementPosture::fromCounters($counters);
			$perCustomer[$cid]['count']++;
		}

		$result = [];
		foreach ($customerIds as $cid) {
			if (!isset($perCustomer[$cid])) {
				$result[$cid] = self::emptyRollup();
				continue;
			}
			$sums = $perCustomer[$cid]['sums'];
			$outstandingHours = Money::add($sums['open_hours'], $sums['invoiced_hours'], Money::HOUR_SCALE);
			$outstandingAmount = Money::add($sums['open_amount'], $sums['invoiced_amount'], Money::MONEY_SCALE);

			$result[$cid] = [
				'posture' => SettlementPosture::combine($perCustomer[$cid]['postures']),
				'outstanding_hours' => Money::asFloat($outstandingHours, Money::HOUR_SCALE),
				'outstanding_amount' => Money::asFloat($outstandingAmount, Money::MONEY_SCALE),
				'open_hours' => Money::asFloat($sums['open_hours'], Money::HOUR_SCALE),
				'invoiced_hours' => Money::asFloat($sums['invoiced_hours'], Money::HOUR_SCALE),
				'paid_hours' => Money::asFloat($sums['paid_hours'], Money::HOUR_SCALE),
				'excluded_hours' => Money::asFloat($sums['excluded_hours'], Money::HOUR_SCALE),
				'open_amount' => Money::asFloat($sums['open_amount'], Money::MONEY_SCALE),
				'invoiced_amount' => Money::asFloat($sums['invoiced_amount'], Money::MONEY_SCALE),
				'paid_amount' => Money::asFloat($sums['paid_amount'], Money::MONEY_SCALE),
				'excluded_amount' => Money::asFloat($sums['excluded_amount'], Money::MONEY_SCALE),
				'project_count' => $perCustomer[$cid]['count'],
				'progress' => SettlementProgress::fromCounters([
					'open_hours' => $sums['open_hours'],
					'invoiced_hours' => $sums['invoiced_hours'],
					'paid_hours' => $sums['paid_hours'],
				]),
			];
		}

		return $result;
	}

	/**
	 * Whether a customer rollup matches a settlement filter code
	 * (`outstanding` matches any posture with outstanding hours > 0;
	 * posture codes match exactly; `all`/empty matches everything).
	 *
	 * @param array<string, mixed> $rollup value from getSettlementForCustomer(s)
	 */
	public function matchesSettlementFilter(array $rollup, string $filter): bool
	{
		$filter = trim($filter);
		if ($filter === '' || $filter === 'all') {
			return true;
		}
		if ($filter === 'outstanding') {
			return ((float) ($rollup['outstanding_hours'] ?? 0)) > 0.0;
		}
		if (!SettlementPosture::isValid($filter)) {
			// Unknown codes must not silently match everything — prefer empty result.
			return false;
		}
		return ($rollup['posture'] ?? SettlementPosture::NA) === $filter;
	}

	/**
	 * Project id scope for customer AR rollups.
	 *
	 * @return list<int>|null null = all projects (global settler)
	 */
	private function resolveRollupProjectScope(string $userId): ?array
	{
		$settleableIds = $this->projectService->getSettleableProjectIdListForUser($userId);
		if ($settleableIds === null) {
			return null;
		}
		if ($settleableIds !== []) {
			// Manager / creator: AR totals only over projects they may settle (§12.6).
			return $settleableIds;
		}
		// Pure Member: still see outstanding on projects they can access (D6 / E27).
		return $this->projectService->getAccessibleProjectIdListForUser($userId) ?? [];
	}

	/**
	 * @param list<int> $customerIds
	 * @param list<int>|null $scopedProjectIds null = all projects
	 * @return list<array<string, mixed>>
	 */
	private function fetchProjectCounterRows(array $customerIds, ?array $scopedProjectIds): array
	{
		$scopedSet = $scopedProjectIds !== null ? array_fill_keys($scopedProjectIds, true) : null;
		$rows = [];
		// Chunk the IN() list defensively (DB parameter limits).
		foreach (array_chunk($customerIds, 500) as $customerChunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select(
				'id',
				'customer_id',
				'stl_open_hours',
				'stl_invoiced_hours',
				'stl_paid_hours',
				'stl_excluded_hours',
				'stl_open_amount',
				'stl_invoiced_amount',
				'stl_paid_amount',
				'stl_excluded_amount'
			)
				->from('pc_projects')
				->where($qb->expr()->in('customer_id', $qb->createNamedParameter($customerChunk, IQueryBuilder::PARAM_INT_ARRAY)));

			$rs = $qb->executeQuery();
			while ($row = $rs->fetch()) {
				if ($scopedSet !== null && !isset($scopedSet[(int) $row['id']])) {
					continue;
				}
				$rows[] = $row;
			}
			$rs->closeCursor();
		}
		return $rows;
	}

	/**
	 * @return array<string, string>
	 */
	private static function zeroSums(): array
	{
		return [
			'open_hours' => '0',
			'invoiced_hours' => '0',
			'paid_hours' => '0',
			'excluded_hours' => '0',
			'open_amount' => '0',
			'invoiced_amount' => '0',
			'paid_amount' => '0',
			'excluded_amount' => '0',
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function emptyRollup(): array
	{
		return [
			'posture' => SettlementPosture::NA,
			'outstanding_hours' => 0.0,
			'outstanding_amount' => 0.0,
			'open_hours' => 0.0,
			'invoiced_hours' => 0.0,
			'paid_hours' => 0.0,
			'excluded_hours' => 0.0,
			'open_amount' => 0.0,
			'invoiced_amount' => 0.0,
			'paid_amount' => 0.0,
			'excluded_amount' => 0.0,
			'project_count' => 0,
			'progress' => SettlementProgress::empty(),
		];
	}
}
