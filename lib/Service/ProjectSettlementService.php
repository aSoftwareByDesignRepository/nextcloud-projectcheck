<?php

declare(strict_types=1);

/**
 * Read-model + full-project settle wrappers for the settlement feature.
 *
 * Presents the materialized project counters as UI-ready settlement info
 * (posture, outstanding, per-bucket sums) and wraps the generic filter-mode
 * bulk engine in {@see TimeEntryBillingService} for the two project-level
 * actions ("Invoice all open", "Mark all invoiced as paid" — spec §6.6).
 * No settlement state is written here; the billing service owns all writes.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

use OCA\ProjectCheck\Db\Project;
use OCA\ProjectCheck\Exception\PermissionDeniedException;
use OCA\ProjectCheck\Exception\ValidationException;
use OCA\ProjectCheck\Util\BillingStatus;
use OCA\ProjectCheck\Util\Money;
use OCA\ProjectCheck\Util\SettlementPosture;
use OCA\ProjectCheck\Util\SettlementProgress;
use OCP\IL10N;

class ProjectSettlementService
{
	/** Project action: open → invoiced. */
	public const ACTION_INVOICE_OPEN = 'invoice_open';
	/** Project action: invoiced → paid. */
	public const ACTION_MARK_PAID = 'mark_paid';

	private const ACTIONS = [
		self::ACTION_INVOICE_OPEN => [BillingStatus::OPEN, BillingStatus::INVOICED],
		self::ACTION_MARK_PAID => [BillingStatus::INVOICED, BillingStatus::PAID],
	];

	public function __construct(
		private ProjectService $projectService,
		private TimeEntryBillingService $billingService,
		private ProjectSettlementCounterService $counterService,
		private IL10N $l,
	) {
	}

	/**
	 * UI-ready settlement info for one project (chips, summary strip,
	 * Invoicing section). Read audience = project accessors (spec D6).
	 *
	 * @return array{
	 *   posture: string,
	 *   counters: array<string, float>,
	 *   outstanding_hours: float,
	 *   outstanding_amount: float,
	 *   chargeable_hours: float,
	 *   progress: array{
	 *     has_chargeable: bool,
	 *     paid_percent: int|null,
	 *     invoiced_percent: int|null,
	 *     billed_percent: int|null,
	 *     open_percent: int|null,
	 *     bar: array{paid: int, invoiced: int, open: int}
	 *   },
	 *   can_settle: bool
	 * }
	 */
	public function getSettlementInfo(Project $project, string $userId): array
	{
		$info = $this->getSettlementInfoWithoutAcl($project);
		$info['can_settle'] = $this->projectService->canUserSettleProject($userId, (int) $project->getId());
		return $info;
	}

	/**
	 * Settlement info for a list of projects, keyed by project id. Uses only
	 * data already loaded on the entities — no extra queries, safe for lists.
	 *
	 * @param list<Project> $projects
	 * @return array<int, array<string, mixed>>
	 */
	public function enrichProjectsWithSettlementInfo(array $projects, string $userId): array
	{
		$canSettleAnywhere = $this->projectService->canUserSettleAnywhere($userId);
		$settleableIds = $canSettleAnywhere
			? null
			: ($this->projectService->getSettleableProjectIdListForUser($userId) ?? []);

		$result = [];
		foreach ($projects as $project) {
			$projectId = (int) $project->getId();
			$info = $this->getSettlementInfoWithoutAcl($project);
			$info['can_settle'] = $canSettleAnywhere || in_array($projectId, $settleableIds ?? [], true);
			$result[$projectId] = $info;
		}

		return $result;
	}

	/**
	 * Preview a project-level settle action: count/sum + confirmation token
	 * (spec §6.6 — same preview token + 500 cap as entry bulk).
	 *
	 * @param string $action one of the ACTION_* constants
	 * @param array{date_from?: string, date_to?: string} $dates optional narrowing
	 * @return array{count: int, hours: float, amount: float, token: string|null, cap: int, capExceeded: bool, target: string}
	 * @throws PermissionDeniedException
	 * @throws ValidationException
	 */
	public function previewProjectSettle(int $projectId, string $action, array $dates, string $actorUid): array
	{
		[$source, $target] = $this->resolveAction($action);
		$this->assertProjectSettleable($projectId, $actorUid);

		$preview = $this->billingService->previewByFilters(
			$this->buildFilters($projectId, $source, $dates),
			$target,
			$actorUid
		);
		$preview['target'] = $target;

		return $preview;
	}

	/**
	 * Apply a previously previewed project-level settle action.
	 *
	 * @param array{date_from?: string, date_to?: string} $dates must match the preview
	 * @return array{applied: int, failed: list<array{id: int, reason: string}>}
	 * @throws PermissionDeniedException
	 * @throws ValidationException
	 * @throws \OCA\ProjectCheck\Exception\SettlementConflictException
	 */
	public function applyProjectSettle(int $projectId, string $action, array $dates, string $actorUid, string $token): array
	{
		[$source, $target] = $this->resolveAction($action);
		$this->assertProjectSettleable($projectId, $actorUid);

		return $this->billingService->applyByFilters(
			$this->buildFilters($projectId, $source, $dates),
			$target,
			$actorUid,
			$token
		);
	}

	/**
	 * Outstanding AR summary for the dashboard widget (spec §11.4 / M6).
	 *
	 * Returns null when the actor may not settle anything (ordinary Members
	 * must not see an org/manager AR widget — E30). Global settlers get
	 * org-wide totals; Managers/creators get settleable-project totals only.
	 *
	 * @return array{
	 *   outstanding_hours: float,
	 *   outstanding_amount: float,
	 *   open_hours: float,
	 *   invoiced_hours: float,
	 *   project_count: int,
	 *   scope: 'global'|'managed'
	 * }|null
	 */
	public function getOutstandingSummaryForSettler(string $userId): ?array
	{
		if (!$this->projectService->canUserSettleAnything($userId)) {
			return null;
		}

		$settleableIds = $this->projectService->getSettleableProjectIdListForUser($userId);
		$scope = $settleableIds === null ? 'global' : 'managed';
		$totals = $this->counterService->sumOutstandingCounters($settleableIds);

		return $totals + ['scope' => $scope];
	}

	/**
	 * @return array{0: string, 1: string} [source status, target status]
	 * @throws ValidationException
	 */
	private function resolveAction(string $action): array
	{
		if (!isset(self::ACTIONS[$action])) {
			throw new ValidationException([], $this->l->t('Unknown settlement action'));
		}
		return self::ACTIONS[$action];
	}

	/**
	 * @throws PermissionDeniedException
	 * @throws ValidationException
	 */
	private function assertProjectSettleable(int $projectId, string $actorUid): void
	{
		$project = $this->projectService->getProject($projectId);
		if (!$project) {
			throw new ValidationException([], $this->l->t('Project not found'));
		}
		if (!$this->projectService->canUserSettleProject($actorUid, $projectId)) {
			throw new PermissionDeniedException('settle', 'project', $this->l->t('Access denied'));
		}
	}

	/**
	 * @param array{date_from?: string, date_to?: string} $dates
	 * @return array<string, string|int>
	 */
	private function buildFilters(int $projectId, string $source, array $dates): array
	{
		$filters = [
			'project_id' => $projectId,
			'billing_status' => $source,
		];
		foreach (['date_from', 'date_to'] as $key) {
			$value = trim((string) ($dates[$key] ?? ''));
			if ($value !== '') {
				$filters[$key] = $value;
			}
		}
		return $filters;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function getSettlementInfoWithoutAcl(Project $project): array
	{
		$counters = $project->getSettlementCounters();

		$outstandingHours = Money::add(
			Money::normalize($counters['open_hours'], Money::HOUR_SCALE),
			Money::normalize($counters['invoiced_hours'], Money::HOUR_SCALE),
			Money::HOUR_SCALE
		);
		$outstandingAmount = Money::add(
			Money::normalize($counters['open_amount'], Money::MONEY_SCALE),
			Money::normalize($counters['invoiced_amount'], Money::MONEY_SCALE),
			Money::MONEY_SCALE
		);
		$chargeableHours = Money::add(
			$outstandingHours,
			Money::normalize($counters['paid_hours'], Money::HOUR_SCALE),
			Money::HOUR_SCALE
		);

		return [
			'posture' => SettlementPosture::fromCounters($counters),
			'counters' => $counters,
			'outstanding_hours' => Money::asFloat($outstandingHours, Money::HOUR_SCALE),
			'outstanding_amount' => Money::asFloat($outstandingAmount, Money::MONEY_SCALE),
			'chargeable_hours' => Money::asFloat($chargeableHours, Money::HOUR_SCALE),
			'progress' => SettlementProgress::fromCounters($counters),
		];
	}
}
