<?php

declare(strict_types=1);

/**
 * Maintains the materialized settlement counters on `pc_projects`.
 *
 * Counters are pure derivatives of `pc_time_entries` (single source of
 * truth). Every write here MUST happen inside the same DB transaction as the
 * entry write that caused it (feature spec D10) — callers own the
 * transaction; this service only issues arithmetic UPDATEs.
 *
 * Deadlock discipline (spec §10.2/§10.7): callers update entry rows first
 * (ascending id), then project counters (ascending project id). Use
 * {@see applyDeltas} for multi-project changes — it sorts by project id.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

use OCA\ProjectCheck\Migration\SettlementRecomputer;
use OCA\ProjectCheck\Util\BillingStatus;
use OCA\ProjectCheck\Util\Money;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

class ProjectSettlementCounterService
{
	private const HOUR_COLUMNS = [
		BillingStatus::OPEN => 'stl_open_hours',
		BillingStatus::INVOICED => 'stl_invoiced_hours',
		BillingStatus::PAID => 'stl_paid_hours',
		BillingStatus::EXCLUDED => 'stl_excluded_hours',
	];

	private const AMOUNT_COLUMNS = [
		BillingStatus::OPEN => 'stl_open_amount',
		BillingStatus::INVOICED => 'stl_invoiced_amount',
		BillingStatus::PAID => 'stl_paid_amount',
		BillingStatus::EXCLUDED => 'stl_excluded_amount',
	];

	public function __construct(
		private IDBConnection $db,
	) {
	}

	/**
	 * Per-entry deltas for the bucket of $status: entry creation adds, entry
	 * deletion subtracts (pass $sign = -1). Hours and amounts are computed
	 * with Money fixed-point math; the amount is the entry cost rounded to 2
	 * decimals, matching {@see \OCA\ProjectCheck\Db\TimeEntry::getCost()}
	 * and the SQL recompute.
	 */
	public function applyEntryDelta(int $projectId, string $status, float $hours, float $hourlyRate, int $sign): void
	{
		$status = BillingStatus::normalize($status);
		$hoursDelta = Money::normalize($hours, Money::HOUR_SCALE);
		$amountDelta = Money::mul($hours, $hourlyRate, Money::MONEY_SCALE);
		if ($sign < 0) {
			$hoursDelta = Money::sub('0', $hoursDelta, Money::HOUR_SCALE);
			$amountDelta = Money::sub('0', $amountDelta, Money::MONEY_SCALE);
		}
		$this->applyRawDelta($projectId, $status, $hoursDelta, $amountDelta);
	}

	/**
	 * Counter effect of an entry content change (hours/rate and/or project
	 * move) while the entry stays in the same status bucket: −old +new,
	 * possibly across two projects. Projects are updated in ascending id
	 * order (spec §10.7) with one UPDATE per project.
	 */
	public function applyContentChangeDelta(
		int $oldProjectId,
		int $newProjectId,
		string $status,
		float $oldHours,
		float $oldHourlyRate,
		float $newHours,
		float $newHourlyRate,
	): void {
		$status = BillingStatus::normalize($status);

		$deltas = [];
		$old = &$deltas[$oldProjectId][$status];
		$old = $old ?? ['hours' => '0', 'amount' => '0'];
		$old['hours'] = Money::sub($old['hours'], Money::normalize($oldHours, Money::HOUR_SCALE), Money::HOUR_SCALE);
		$old['amount'] = Money::sub($old['amount'], Money::mul($oldHours, $oldHourlyRate, Money::MONEY_SCALE), Money::MONEY_SCALE);
		unset($old);

		$new = &$deltas[$newProjectId][$status];
		$new = $new ?? ['hours' => '0', 'amount' => '0'];
		$new['hours'] = Money::add($new['hours'], Money::normalize($newHours, Money::HOUR_SCALE), Money::HOUR_SCALE);
		$new['amount'] = Money::add($new['amount'], Money::mul($newHours, $newHourlyRate, Money::MONEY_SCALE), Money::MONEY_SCALE);
		unset($new);

		$this->applyDeltas($deltas);
	}

	/**
	 * Move an entry's hours/amount between two status buckets on the same
	 * project (settlement transition) in one UPDATE — the row lock is taken
	 * once and both buckets change atomically.
	 */
	public function applyTransitionDelta(int $projectId, string $fromStatus, string $toStatus, float $hours, float $hourlyRate): void
	{
		$fromStatus = BillingStatus::normalize($fromStatus);
		$toStatus = BillingStatus::normalize($toStatus);
		if ($fromStatus === $toStatus) {
			return;
		}

		$hoursDelta = Money::normalize($hours, Money::HOUR_SCALE);
		$amountDelta = Money::mul($hours, $hourlyRate, Money::MONEY_SCALE);
		$negHours = Money::sub('0', $hoursDelta, Money::HOUR_SCALE);
		$negAmount = Money::sub('0', $amountDelta, Money::MONEY_SCALE);

		$qb = $this->db->getQueryBuilder();
		$qb->update('pc_projects');
		$this->addBucketSet($qb, $fromStatus, $negHours, $negAmount);
		$this->addBucketSet($qb, $toStatus, $hoursDelta, $amountDelta);
		$qb->set('stl_updated_at', $qb->createNamedParameter((new \DateTime())->format('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	/**
	 * Apply aggregated deltas for several projects, ordered by ascending
	 * project id (global lock order — prevents deadlocks between concurrent
	 * bulk settlers, spec §10.2).
	 *
	 * @param array<int, array<string, array{hours: string, amount: string}>> $deltasByProject
	 *        projectId => status => signed decimal-string deltas
	 */
	public function applyDeltas(array $deltasByProject): void
	{
		ksort($deltasByProject);
		foreach ($deltasByProject as $projectId => $statusDeltas) {
			$qb = $this->db->getQueryBuilder();
			$qb->update('pc_projects');
			$any = false;
			foreach ($statusDeltas as $status => $delta) {
				$status = BillingStatus::normalize((string) $status);
				$hours = Money::normalize($delta['hours'] ?? 0, Money::HOUR_SCALE);
				$amount = Money::normalize($delta['amount'] ?? 0, Money::MONEY_SCALE);
				if (Money::isZero($hours) && Money::isZero($amount)) {
					continue;
				}
				$this->addBucketSet($qb, $status, $hours, $amount);
				$any = true;
			}
			if (!$any) {
				continue;
			}
			$qb->set('stl_updated_at', $qb->createNamedParameter((new \DateTime())->format('Y-m-d H:i:s')))
				->where($qb->expr()->eq('id', $qb->createNamedParameter((int) $projectId, IQueryBuilder::PARAM_INT)));
			$qb->executeStatement();
		}
	}

	/**
	 * Rebuild one project's counters from its entries (drift repair). Runs in
	 * its own transaction — do not call inside another transaction.
	 */
	public function recomputeProject(int $projectId): void
	{
		(new SettlementRecomputer($this->db))->recomputeProject($projectId);
	}

	/**
	 * Rebuild all projects' counters. Returns the number of projects.
	 */
	public function recomputeAll(): int
	{
		return (new SettlementRecomputer($this->db))->recomputeAll();
	}

	/**
	 * Aggregate open/invoiced counters across projects for the dashboard AR
	 * widget. null $projectIds = all projects (global settler); empty list =
	 * zero totals (Manager with nothing to settle).
	 *
	 * @param list<int>|null $projectIds
	 * @return array{
	 *   outstanding_hours: float,
	 *   outstanding_amount: float,
	 *   open_hours: float,
	 *   invoiced_hours: float,
	 *   project_count: int
	 * }
	 */
	public function sumOutstandingCounters(?array $projectIds): array
	{
		$empty = [
			'outstanding_hours' => 0.0,
			'outstanding_amount' => 0.0,
			'open_hours' => 0.0,
			'invoiced_hours' => 0.0,
			'project_count' => 0,
		];
		if ($projectIds !== null && $projectIds === []) {
			return $empty;
		}

		$openHours = '0';
		$invoicedHours = '0';
		$openAmount = '0';
		$invoicedAmount = '0';
		$withOutstanding = 0;

		$chunks = $projectIds === null ? [null] : array_chunk(array_values(array_unique(array_map('intval', $projectIds))), 500);
		foreach ($chunks as $chunk) {
			$qb = $this->db->getQueryBuilder();
			$qb->select(
				'stl_open_hours',
				'stl_invoiced_hours',
				'stl_open_amount',
				'stl_invoiced_amount'
			)->from('pc_projects');
			if ($chunk !== null) {
				$qb->where($qb->expr()->in('id', $qb->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			}
			$rs = $qb->executeQuery();
			while ($row = $rs->fetch()) {
				$pOpen = Money::normalize($row['stl_open_hours'] ?? 0, Money::HOUR_SCALE);
				$pInvoiced = Money::normalize($row['stl_invoiced_hours'] ?? 0, Money::HOUR_SCALE);
				$openHours = Money::add($openHours, $pOpen, Money::HOUR_SCALE);
				$invoicedHours = Money::add($invoicedHours, $pInvoiced, Money::HOUR_SCALE);
				$openAmount = Money::add($openAmount, Money::normalize($row['stl_open_amount'] ?? 0, Money::MONEY_SCALE), Money::MONEY_SCALE);
				$invoicedAmount = Money::add($invoicedAmount, Money::normalize($row['stl_invoiced_amount'] ?? 0, Money::MONEY_SCALE), Money::MONEY_SCALE);
				if (!Money::isZero(Money::add($pOpen, $pInvoiced, Money::HOUR_SCALE))) {
					$withOutstanding++;
				}
			}
			$rs->closeCursor();
		}

		return [
			'outstanding_hours' => Money::asFloat(Money::add($openHours, $invoicedHours, Money::HOUR_SCALE), Money::HOUR_SCALE),
			'outstanding_amount' => Money::asFloat(Money::add($openAmount, $invoicedAmount, Money::MONEY_SCALE), Money::MONEY_SCALE),
			'open_hours' => Money::asFloat($openHours, Money::HOUR_SCALE),
			'invoiced_hours' => Money::asFloat($invoicedHours, Money::HOUR_SCALE),
			'project_count' => $withOutstanding,
		];
	}

	private function applyRawDelta(int $projectId, string $status, string $hoursDelta, string $amountDelta): void
	{
		if (Money::isZero($hoursDelta) && Money::isZero($amountDelta)) {
			return;
		}
		$qb = $this->db->getQueryBuilder();
		$qb->update('pc_projects');
		$this->addBucketSet($qb, $status, $hoursDelta, $amountDelta);
		$qb->set('stl_updated_at', $qb->createNamedParameter((new \DateTime())->format('Y-m-d H:i:s')))
			->where($qb->expr()->eq('id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)));
		$qb->executeStatement();
	}

	private function addBucketSet(IQueryBuilder $qb, string $status, string $hoursDelta, string $amountDelta): void
	{
		$hourColumn = self::HOUR_COLUMNS[$status] ?? self::HOUR_COLUMNS[BillingStatus::OPEN];
		$amountColumn = self::AMOUNT_COLUMNS[$status] ?? self::AMOUNT_COLUMNS[BillingStatus::OPEN];
		$qb->set($hourColumn, $qb->createFunction(
			$hourColumn . ' + ' . $qb->createNamedParameter($hoursDelta)
		));
		$qb->set($amountColumn, $qb->createFunction(
			$amountColumn . ' + ' . $qb->createNamedParameter($amountDelta)
		));
	}
}
