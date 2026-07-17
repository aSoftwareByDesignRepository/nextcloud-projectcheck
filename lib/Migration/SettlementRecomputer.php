<?php

declare(strict_types=1);

/**
 * Rebuilds the materialized settlement counters on `pc_projects` from the
 * time entries (single source of truth) and backfills the creator → Manager
 * role for legacy projects.
 *
 * Used by:
 *  - {@see \OCA\ProjectCheck\Repair\EnsureProjectCheckSchema} via
 *    {@see ProjectCheckSchemaEnsurer::ensure()} (post-migration, once),
 *  - `occ projectcheck:settlement-recompute` (operator-triggered),
 *  - {@see \OCA\ProjectCheck\Service\ProjectSettlementCounterService} for
 *    per-project drift repair.
 *
 * Each project is recomputed in its own short transaction (spec §10.6) so a
 * live instance never holds long locks; concurrent settlement writes on other
 * projects proceed unblocked.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Migration;

use OCA\ProjectCheck\Util\BillingStatus;
use OCP\DB\QueryBuilder\IQueryBuilder;
use OCP\IDBConnection;

final class SettlementRecomputer
{
	public function __construct(
		private IDBConnection $db,
	) {
	}

	/**
	 * Recompute counters for every project. Returns the number of projects
	 * processed. Idempotent — safe to run repeatedly.
	 */
	public function recomputeAll(): int
	{
		$count = 0;
		foreach ($this->allProjectIds() as $projectId) {
			$this->recomputeProject($projectId);
			$count++;
		}
		return $count;
	}

	/**
	 * Recompute counters for one project inside its own transaction.
	 *
	 * Per-entry amounts are rounded to 2 decimals *before* summing
	 * (SUM(ROUND(hours * rate, 2))) so the SQL aggregate matches the
	 * PHP-side {@see \OCA\ProjectCheck\Util\Money::mul} per-entry cost and
	 * incremental counter deltas never drift from a recompute.
	 */
	public function recomputeProject(int $projectId): void
	{
		$this->db->beginTransaction();
		try {
			$qb = $this->db->getQueryBuilder();
			$qb->select('billing_status')
				->selectAlias($qb->createFunction('COALESCE(SUM(hours), 0)'), 'sum_hours')
				->selectAlias($qb->createFunction('COALESCE(SUM(ROUND(hours * hourly_rate, 2)), 0)'), 'sum_amount')
				->from('pc_time_entries')
				->where($qb->expr()->eq('project_id', $qb->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)))
				->groupBy('billing_status');

			$buckets = [
				BillingStatus::OPEN => ['hours' => '0', 'amount' => '0'],
				BillingStatus::INVOICED => ['hours' => '0', 'amount' => '0'],
				BillingStatus::PAID => ['hours' => '0', 'amount' => '0'],
				BillingStatus::EXCLUDED => ['hours' => '0', 'amount' => '0'],
			];

			$result = $qb->executeQuery();
			while ($row = $result->fetch()) {
				$status = BillingStatus::normalize($row['billing_status'] ?? null);
				$buckets[$status] = [
					'hours' => (string) ($row['sum_hours'] ?? '0'),
					'amount' => (string) ($row['sum_amount'] ?? '0'),
				];
			}
			$result->closeCursor();

			$update = $this->db->getQueryBuilder();
			$update->update('pc_projects')
				->set('stl_open_hours', $update->createNamedParameter($buckets[BillingStatus::OPEN]['hours']))
				->set('stl_invoiced_hours', $update->createNamedParameter($buckets[BillingStatus::INVOICED]['hours']))
				->set('stl_paid_hours', $update->createNamedParameter($buckets[BillingStatus::PAID]['hours']))
				->set('stl_excluded_hours', $update->createNamedParameter($buckets[BillingStatus::EXCLUDED]['hours']))
				->set('stl_open_amount', $update->createNamedParameter($buckets[BillingStatus::OPEN]['amount']))
				->set('stl_invoiced_amount', $update->createNamedParameter($buckets[BillingStatus::INVOICED]['amount']))
				->set('stl_paid_amount', $update->createNamedParameter($buckets[BillingStatus::PAID]['amount']))
				->set('stl_excluded_amount', $update->createNamedParameter($buckets[BillingStatus::EXCLUDED]['amount']))
				->set('stl_updated_at', $update->createNamedParameter(
					(new \DateTime())->format('Y-m-d H:i:s')
				))
				->where($update->expr()->eq('id', $update->createNamedParameter($projectId, IQueryBuilder::PARAM_INT)));
			$update->executeStatement();

			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	/**
	 * Optional overhead backfill (spec §7.3 step 4 / E25): open entries on
	 * overhead projects that were never billed or paid become `excluded`.
	 * Idempotent — already-excluded / invoiced / paid rows are left alone.
	 *
	 * @return int number of entries updated
	 */
	public function backfillOverheadExcluded(): int
	{
		$overheadTypes = ['admin', 'meeting', 'internal', 'training'];
		$qb = $this->db->getQueryBuilder();
		$qb->select('te.id')
			->from('pc_time_entries', 'te')
			->innerJoin('te', 'pc_projects', 'p', $qb->expr()->eq('te.project_id', 'p.id'))
			->where($qb->expr()->eq('te.billing_status', $qb->createNamedParameter(BillingStatus::OPEN)))
			->andWhere($qb->expr()->in('p.project_type', $qb->createNamedParameter($overheadTypes, IQueryBuilder::PARAM_STR_ARRAY)))
			->andWhere($qb->expr()->isNull('te.billed_at'))
			->andWhere($qb->expr()->isNull('te.paid_at'));

		$ids = [];
		$result = $qb->executeQuery();
		while ($row = $result->fetch()) {
			$ids[] = (int) $row['id'];
		}
		$result->closeCursor();

		if ($ids === []) {
			return 0;
		}

		$updated = 0;
		$now = (new \DateTime())->format('Y-m-d H:i:s');
		foreach (array_chunk($ids, 500) as $chunk) {
			$update = $this->db->getQueryBuilder();
			$update->update('pc_time_entries')
				->set('billing_status', $update->createNamedParameter(BillingStatus::EXCLUDED))
				->set('billing_changed_by', $update->createNamedParameter('settlement-backfill'))
				->set('billing_changed_at', $update->createNamedParameter($now))
				->set('updated_at', $update->createNamedParameter($now))
				->where($update->expr()->in('id', $update->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)))
				->andWhere($update->expr()->eq('billing_status', $update->createNamedParameter(BillingStatus::OPEN)));
			$updated += (int) $update->executeStatement();
		}

		return $updated;
	}

	/**
	 * Legacy backfill (spec §7.4): the creator of each project becomes a
	 * Manager on their own active membership row. Creators without a
	 * membership row still settle via the `created_by` check — no row is
	 * force-created. Idempotent.
	 *
	 * @return int number of membership rows promoted
	 */
	public function backfillCreatorManagerRoles(): int
	{
		// Collect (project_id, created_by) pairs, then promote matching active
		// membership rows. Two-step to stay portable (no UPDATE ... JOIN).
		$qb = $this->db->getQueryBuilder();
		$qb->select('m.id')
			->from('pc_project_members', 'm')
			->innerJoin('m', 'pc_projects', 'p', $qb->expr()->eq('m.project_id', 'p.id'))
			->where($qb->expr()->eq('m.user_id', 'p.created_by'))
			->andWhere($qb->expr()->neq('m.role', $qb->createNamedParameter(\OCA\ProjectCheck\Util\ProjectMemberRole::MANAGER)))
			->andWhere($qb->expr()->orX(
				$qb->expr()->eq('m.member_state', $qb->createNamedParameter('active')),
				$qb->expr()->isNull('m.member_state'),
				$qb->expr()->eq('m.member_state', $qb->createNamedParameter(''))
			));

		$ids = [];
		$result = $qb->executeQuery();
		while ($row = $result->fetch()) {
			$ids[] = (int) $row['id'];
		}
		$result->closeCursor();

		if ($ids === []) {
			return 0;
		}

		$promoted = 0;
		foreach (array_chunk($ids, 500) as $chunk) {
			$update = $this->db->getQueryBuilder();
			$update->update('pc_project_members')
				->set('role', $update->createNamedParameter(\OCA\ProjectCheck\Util\ProjectMemberRole::MANAGER))
				->where($update->expr()->in('id', $update->createNamedParameter($chunk, IQueryBuilder::PARAM_INT_ARRAY)));
			$promoted += (int) $update->executeStatement();
		}

		return $promoted;
	}

	/**
	 * @return list<int>
	 */
	private function allProjectIds(): array
	{
		$qb = $this->db->getQueryBuilder();
		$qb->select('id')->from('pc_projects')->orderBy('id', 'ASC');

		$ids = [];
		$result = $qb->executeQuery();
		while ($row = $result->fetch()) {
			$ids[] = (int) $row['id'];
		}
		$result->closeCursor();

		return $ids;
	}
}
