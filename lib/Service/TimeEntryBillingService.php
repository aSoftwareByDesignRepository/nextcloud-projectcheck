<?php

declare(strict_types=1);

/**
 * Settlement state machine for time entries: single transitions, bulk
 * transitions, and stateless preview tokens for filter-mode bulk apply.
 *
 * This is the ONLY code path that mutates `pc_time_entries.billing_status`
 * (feature spec §4, option D mitigation). Every write:
 *  - checks the actor's scope per entry ({@see ProjectService::canUserSettleProject}),
 *  - validates the transition matrix ({@see BillingStatus}),
 *  - uses an optimistic predicate (status + updated_at) so races surface as
 *    conflicts instead of double-applied counter deltas,
 *  - and updates the project counters in the same transaction (D10).
 *
 * Deadlock discipline: entry rows are written in ascending id order, project
 * counter rows in ascending project id order — one global lock order for all
 * concurrent settlers (spec §10.2).
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Service;

use OCA\ProjectCheck\Db\TimeEntry;
use OCA\ProjectCheck\Db\TimeEntryMapper;
use OCA\ProjectCheck\Exception\InvalidBillingTransitionException;
use OCA\ProjectCheck\Exception\PermissionDeniedException;
use OCA\ProjectCheck\Exception\SettlementConflictException;
use OCA\ProjectCheck\Exception\TimeEntryNotFoundException;
use OCA\ProjectCheck\Exception\ValidationException;
use OCA\ProjectCheck\Util\BillingStatus;
use OCA\ProjectCheck\Util\Money;
use OCP\ICacheFactory;
use OCP\IConfig;
use OCP\IDBConnection;
use OCP\IL10N;
use Psr\Log\LoggerInterface;

class TimeEntryBillingService
{
	/** Hard cap for one bulk apply (feature spec D11). */
	public const MAX_BULK_ROWS = 500;

	/** Entries per transaction chunk during bulk apply. */
	public const CHUNK_SIZE = 50;

	/** Preview token lifetime in seconds. */
	public const TOKEN_TTL = 600;

	private const TOKEN_CACHE_PREFIX = 'pc-settle-token-';

	/** @var array<int, bool> per-request cache: project id => actor may settle */
	private array $settleScopeCache = [];

	private ?string $settleScopeCacheActor = null;

	public function __construct(
		private TimeEntryMapper $timeEntryMapper,
		private ProjectService $projectService,
		private ProjectSettlementCounterService $counterService,
		private IDBConnection $db,
		private ActivityService $activityService,
		private IConfig $config,
		private ICacheFactory $cacheFactory,
		private IL10N $l,
		private LoggerInterface $logger,
	) {
	}

	/**
	 * Transition a single entry. Throws typed exceptions the controller maps
	 * to 404 / 403 / 400 / 409.
	 *
	 * @throws TimeEntryNotFoundException
	 * @throws PermissionDeniedException
	 * @throws InvalidBillingTransitionException
	 * @throws SettlementConflictException
	 */
	public function changeStatus(int $entryId, string $target, string $actorUid): TimeEntry
	{
		if (!BillingStatus::isValid($target)) {
			throw new ValidationException([], $this->l->t('Invalid settlement status'));
		}

		$entry = $this->findEntry($entryId);
		if ($entry === null) {
			throw new TimeEntryNotFoundException($entryId, $this->l->t('Time entry not found'));
		}

		if (!$this->actorMaySettle($actorUid, (int) $entry->getProjectId())) {
			throw new PermissionDeniedException('settle', 'time entry', $this->l->t('Access denied'));
		}

		$from = $entry->getBillingStatus();
		if (!BillingStatus::isTransitionAllowed($from, $target)) {
			throw new InvalidBillingTransitionException($from, $target);
		}

		$this->db->beginTransaction();
		try {
			$affected = $this->applyGuardedTransition($entry, $target, $actorUid);
			if ($affected === 0) {
				$this->db->rollBack();
				throw new SettlementConflictException(
					SettlementConflictException::CODE_UPDATED_AT,
					$this->l->t('This entry was changed by someone else in the meantime. Reload the page and try again.')
				);
			}
			$this->counterService->applyTransitionDelta(
				(int) $entry->getProjectId(),
				$from,
				$target,
				(float) $entry->getHours(),
				(float) $entry->getHourlyRate()
			);
			$this->db->commit();
		} catch (SettlementConflictException $e) {
			throw $e;
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}

		$this->activityService->logBillingStatusChanged($actorUid, $entry, $from, $target);

		$updated = $this->findEntry($entryId);
		return $updated ?? $entry;
	}

	/**
	 * Bulk transition an explicit id selection. Per-entry failures never abort
	 * the batch; they are reported with machine-readable reasons.
	 *
	 * @param list<int> $entryIds
	 * @return array{applied: int, failed: list<array{id: int, reason: string}>}
	 * @throws ValidationException on invalid target / oversize selection
	 */
	public function bulkChangeStatusByIds(array $entryIds, string $target, string $actorUid): array
	{
		if (!BillingStatus::isValid($target)) {
			throw new ValidationException([], $this->l->t('Invalid settlement status'));
		}

		$entryIds = array_values(array_unique(array_filter(array_map('intval', $entryIds), static fn (int $id): bool => $id > 0)));
		if ($entryIds === []) {
			throw new ValidationException([], $this->l->t('No entries selected'));
		}
		if (count($entryIds) > self::MAX_BULK_ROWS) {
			throw new ValidationException([], $this->l->t(
				'Too many entries in one operation (limit %s). Narrow the date range and run it in slices.',
				[(string) self::MAX_BULK_ROWS]
			));
		}
		sort($entryIds);

		$applied = 0;
		$failed = [];

		foreach (array_chunk($entryIds, self::CHUNK_SIZE) as $chunk) {
			$result = $this->applyChunk($chunk, $target, $actorUid);
			$applied += $result['applied'];
			foreach ($result['failed'] as $failure) {
				$failed[] = $failure;
			}
		}

		if ($applied > 0) {
			$this->activityService->logBillingBulkChanged($actorUid, $target, $applied, count($failed));
		}

		return ['applied' => $applied, 'failed' => $failed];
	}

	/**
	 * Filter-mode preview: count and sum the candidate set, and mint a token
	 * that binds actor + normalized filters + target + count. Apply refuses
	 * the token when anything drifted (spec §10.4).
	 *
	 * @param array<string,mixed> $filters raw list filters (see normalizeFilters)
	 * @return array{count: int, hours: float, amount: float, token: string|null, cap: int, capExceeded: bool}
	 * @throws ValidationException
	 * @throws PermissionDeniedException
	 */
	public function previewByFilters(array $filters, string $target, string $actorUid): array
	{
		[$normalized, $source] = $this->normalizeFilters($filters, $target, $actorUid);

		$buckets = $this->timeEntryMapper->sumBillingBuckets($normalized);
		$bucket = $buckets[$source];

		$capExceeded = $bucket['count'] > self::MAX_BULK_ROWS;
		$token = null;
		if ($bucket['count'] > 0 && !$capExceeded) {
			$token = $this->mintToken($normalized, $target, $actorUid, $bucket['count']);
		}

		return [
			'count' => $bucket['count'],
			'hours' => $bucket['hours'],
			'amount' => $bucket['amount'],
			'token' => $token,
			'cap' => self::MAX_BULK_ROWS,
			'capExceeded' => $capExceeded,
		];
	}

	/**
	 * Filter-mode apply. Validates the preview token (signature, expiry,
	 * actor/filter binding, single-use) and aborts with `stale_preview` when
	 * the live candidate set no longer matches the previewed count.
	 *
	 * @param array<string,mixed> $filters
	 * @return array{applied: int, failed: list<array{id: int, reason: string}>}
	 * @throws ValidationException
	 * @throws PermissionDeniedException
	 * @throws SettlementConflictException
	 */
	public function applyByFilters(array $filters, string $target, string $actorUid, string $token): array
	{
		[$normalized] = $this->normalizeFilters($filters, $target, $actorUid);

		$payload = $this->validateToken($token, $normalized, $target, $actorUid);

		// Recount against live data; any drift aborts the whole operation —
		// finance actions must apply to exactly what was previewed.
		$ids = $this->timeEntryMapper->findIdsByFilters($normalized, self::MAX_BULK_ROWS);
		if (count($ids) !== $payload['count'] || count($ids) > self::MAX_BULK_ROWS) {
			throw new SettlementConflictException(
				SettlementConflictException::CODE_STALE_PREVIEW,
				$this->l->t('The entries changed since the preview. Review the numbers and confirm again.')
			);
		}

		// Claim the nonce only after the recount succeeds so a stale preview
		// does not burn the token — but claim MUST be atomic before apply so
		// two concurrent confirms cannot both proceed (spec §10.5 / E22).
		$this->claimToken($payload['nonce']);

		$result = $this->bulkChangeStatusByIds($ids, $target, $actorUid);

		// Post-condition: silent partial application would also mean drift.
		if ($result['failed'] !== []) {
			$this->logger->info('ProjectCheck settlement: filter-mode apply had per-entry conflicts', [
				'target' => $target,
				'failed' => count($result['failed']),
			]);
		}

		return $result;
	}

	/**
	 * Per-billing-status sums for the summary strip, already scope-checked by
	 * the caller (controller passes the same filters as the visible list).
	 *
	 * @param array<string,mixed> $filters
	 * @return array<string, array{hours: float, amount: float, count: int}>
	 */
	public function getBillingBuckets(array $filters): array
	{
		return $this->timeEntryMapper->sumBillingBuckets($filters);
	}

	// ---------------------------------------------------------------
	// internals
	// ---------------------------------------------------------------

	/**
	 * One transaction: guarded entry updates (ascending id) + aggregated
	 * counter deltas (ascending project id).
	 *
	 * @param list<int> $chunk ascending entry ids
	 * @return array{applied: int, failed: list<array{id: int, reason: string}>}
	 */
	private function applyChunk(array $chunk, string $target, string $actorUid): array
	{
		$applied = 0;
		$failed = [];

		$this->db->beginTransaction();
		try {
			$entries = $this->timeEntryMapper->findByIds($chunk);
			$entriesById = [];
			foreach ($entries as $entry) {
				$entriesById[(int) $entry->getId()] = $entry;
			}

			/** @var array<int, array<string, array{hours: string, amount: string}>> $deltas */
			$deltas = [];

			foreach ($chunk as $entryId) {
				$entry = $entriesById[$entryId] ?? null;
				if ($entry === null) {
					$failed[] = ['id' => $entryId, 'reason' => 'not_found'];
					continue;
				}
				if (!$this->actorMaySettle($actorUid, (int) $entry->getProjectId())) {
					$failed[] = ['id' => $entryId, 'reason' => 'forbidden'];
					continue;
				}
				$from = $entry->getBillingStatus();
				if (!BillingStatus::isTransitionAllowed($from, $target)) {
					$failed[] = ['id' => $entryId, 'reason' => 'invalid_transition'];
					continue;
				}

				$affected = $this->applyGuardedTransition($entry, $target, $actorUid);
				if ($affected === 0) {
					$failed[] = ['id' => $entryId, 'reason' => 'conflict_updated_at'];
					continue;
				}

				$projectId = (int) $entry->getProjectId();
				$hours = Money::normalize($entry->getHours(), Money::HOUR_SCALE);
				$amount = Money::mul($entry->getHours(), $entry->getHourlyRate(), Money::MONEY_SCALE);

				$deltas[$projectId][$from]['hours'] = Money::sub($deltas[$projectId][$from]['hours'] ?? '0', $hours, Money::HOUR_SCALE);
				$deltas[$projectId][$from]['amount'] = Money::sub($deltas[$projectId][$from]['amount'] ?? '0', $amount, Money::MONEY_SCALE);
				$deltas[$projectId][$target]['hours'] = Money::add($deltas[$projectId][$target]['hours'] ?? '0', $hours, Money::HOUR_SCALE);
				$deltas[$projectId][$target]['amount'] = Money::add($deltas[$projectId][$target]['amount'] ?? '0', $amount, Money::MONEY_SCALE);
				$applied++;
			}

			$this->counterService->applyDeltas($deltas);
			$this->db->commit();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}

		return ['applied' => $applied, 'failed' => $failed];
	}

	/**
	 * Guarded UPDATE for one entry (must run inside the caller's transaction).
	 * Timestamp semantics per spec §6.1:
	 *  - → invoiced: set billed_at when empty; clear paid_at (paid → invoiced re-open)
	 *  - → paid: set paid_at when empty
	 *  - → open: clear billed_at + paid_at
	 *  - → excluded: timestamps untouched (only reachable from open, both empty)
	 */
	private function applyGuardedTransition(TimeEntry $entry, string $target, string $actorUid): int
	{
		$now = new \DateTime();
		$set = [
			'billing_status' => $target,
			'billing_changed_by' => $actorUid,
			'billing_changed_at' => $now,
			'updated_at' => $now,
		];

		switch ($target) {
			case BillingStatus::INVOICED:
				if ($entry->getBilledAt() === null) {
					$set['billed_at'] = $now;
				}
				if ($entry->getPaidAt() !== null) {
					$set['paid_at'] = null;
				}
				break;
			case BillingStatus::PAID:
				if ($entry->getPaidAt() === null) {
					$set['paid_at'] = $now;
				}
				break;
			case BillingStatus::OPEN:
				$set['billed_at'] = null;
				$set['paid_at'] = null;
				break;
		}

		return $this->timeEntryMapper->updateBillingGuarded(
			(int) $entry->getId(),
			$entry->getBillingStatus(),
			$entry->getUpdatedAt()->format('Y-m-d H:i:s'),
			$set
		);
	}

	/**
	 * Normalize + authorize filter-mode filters.
	 *
	 * Enforces (spec §8.1): the source billing status must be a single exact
	 * status with an allowed transition to $target, and non-global actors are
	 * hard-scoped to their settleable projects — IDOR by filter is impossible
	 * because the scope is ANDed server-side, never taken from the client.
	 *
	 * @param array<string,mixed> $filters
	 * @return array{0: array<string,mixed>, 1: string} [normalized filters, source status]
	 * @throws ValidationException
	 * @throws PermissionDeniedException
	 */
	private function normalizeFilters(array $filters, string $target, string $actorUid): array
	{
		if (!BillingStatus::isValid($target)) {
			throw new ValidationException([], $this->l->t('Invalid settlement status'));
		}

		$source = (string) ($filters['billing_status'] ?? '');
		if (!BillingStatus::isValid($source)) {
			throw new ValidationException([], $this->l->t('Select which settlement status to change (for example: all open entries).'));
		}
		if (!BillingStatus::isTransitionAllowed($source, $target)) {
			throw new InvalidBillingTransitionException($source, $target);
		}

		$normalized = ['billing_status' => $source];

		$projectId = (int) ($filters['project_id'] ?? 0);
		if ($projectId > 0) {
			$normalized['project_id'] = $projectId;
		}
		$userId = trim((string) ($filters['user_id'] ?? ''));
		if ($userId !== '') {
			$normalized['user_id'] = $userId;
		}
		$projectType = trim((string) ($filters['project_type'] ?? ''));
		if ($projectType !== '') {
			$normalized['project_type'] = $projectType;
		}
		foreach (['date_from', 'date_to'] as $dateKey) {
			$value = trim((string) ($filters[$dateKey] ?? ''));
			if ($value === '') {
				continue;
			}
			if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
				throw new ValidationException([], $this->l->t('Invalid date format'));
			}
			$normalized[$dateKey] = $value;
		}
		$search = trim((string) ($filters['search'] ?? ''));
		if ($search !== '') {
			$normalized['search'] = $search;
		}

		$settleableIds = $this->projectService->getSettleableProjectIdListForUser($actorUid);
		if ($settleableIds !== null) {
			if ($settleableIds === []) {
				throw new PermissionDeniedException('settle', 'time entries', $this->l->t('Access denied'));
			}
			if ($projectId > 0 && !in_array($projectId, $settleableIds, true)) {
				throw new PermissionDeniedException('settle', 'time entries', $this->l->t('Access denied'));
			}
			sort($settleableIds);
			$normalized['project_ids'] = $settleableIds;
		}

		return [$normalized, $source];
	}

	/**
	 * @param array<string,mixed> $normalized
	 */
	private function filterHash(array $normalized, string $target, string $actorUid): string
	{
		ksort($normalized);
		return hash('sha256', json_encode([
			'v' => 1,
			'actor' => $actorUid,
			'target' => $target,
			'filters' => $normalized,
		], JSON_THROW_ON_ERROR));
	}

	/**
	 * Stateless HMAC token: `base64(json payload) . "." . hmac`. The payload
	 * binds filter hash + count + expiry + nonce; the HMAC uses the instance
	 * secret so tokens cannot be forged or replayed across instances.
	 *
	 * @param array<string,mixed> $normalized
	 */
	private function mintToken(array $normalized, string $target, string $actorUid, int $count): string
	{
		$payload = json_encode([
			'h' => $this->filterHash($normalized, $target, $actorUid),
			'c' => $count,
			'e' => time() + self::TOKEN_TTL,
			'n' => bin2hex(random_bytes(16)),
		], JSON_THROW_ON_ERROR);
		$encoded = base64_encode($payload);
		$sig = hash_hmac('sha256', $encoded, $this->tokenSecret());

		return $encoded . '.' . $sig;
	}

	/**
	 * @param array<string,mixed> $normalized
	 * @return array{count: int, nonce: string}
	 * @throws SettlementConflictException
	 * @throws ValidationException
	 */
	private function validateToken(string $token, array $normalized, string $target, string $actorUid): array
	{
		$parts = explode('.', $token, 2);
		if (count($parts) !== 2) {
			throw new ValidationException([], $this->l->t('Invalid or expired preview. Run the preview again.'));
		}
		[$encoded, $sig] = $parts;
		$expected = hash_hmac('sha256', $encoded, $this->tokenSecret());
		if (!hash_equals($expected, $sig)) {
			throw new ValidationException([], $this->l->t('Invalid or expired preview. Run the preview again.'));
		}

		$payload = json_decode(base64_decode($encoded, true) ?: '', true);
		if (!is_array($payload)) {
			throw new ValidationException([], $this->l->t('Invalid or expired preview. Run the preview again.'));
		}

		$expiry = (int) ($payload['e'] ?? 0);
		$nonce = (string) ($payload['n'] ?? '');
		$count = (int) ($payload['c'] ?? -1);
		$hash = (string) ($payload['h'] ?? '');

		if ($expiry < time() || $nonce === '' || $count < 0) {
			throw new ValidationException([], $this->l->t('Invalid or expired preview. Run the preview again.'));
		}
		if (!hash_equals($this->filterHash($normalized, $target, $actorUid), $hash)) {
			throw new ValidationException([], $this->l->t('Invalid or expired preview. Run the preview again.'));
		}

		return ['count' => $count, 'nonce' => $nonce];
	}

	/**
	 * Atomically mark a preview nonce as consumed. Prefer IMemcache::add
	 * (set-if-absent) so two concurrent applies cannot both succeed; fall
	 * back to check-then-set on backends without add().
	 *
	 * @throws SettlementConflictException
	 */
	private function claimToken(string $nonce): void
	{
		$cache = $this->cacheFactory->createDistributed('projectcheck-settlement');
		$key = self::TOKEN_CACHE_PREFIX . $nonce;

		if ($cache instanceof \OCP\IMemcache) {
			if (!$cache->add($key, 1, self::TOKEN_TTL)) {
				throw new SettlementConflictException(
					SettlementConflictException::CODE_TOKEN_USED,
					$this->l->t('This confirmation was already used. Run the preview again.')
				);
			}
			return;
		}

		if ($cache->get($key) !== null) {
			throw new SettlementConflictException(
				SettlementConflictException::CODE_TOKEN_USED,
				$this->l->t('This confirmation was already used. Run the preview again.')
			);
		}
		$cache->set($key, 1, self::TOKEN_TTL);
	}

	private function tokenSecret(): string
	{
		$secret = (string) $this->config->getSystemValue('secret', '');
		if ($secret === '') {
			// Instance without a secret (should not happen on a real install);
			// fall back to instance id so tokens still cannot cross instances.
			$secret = (string) $this->config->getSystemValue('instanceid', 'projectcheck');
		}
		return $secret . '|projectcheck-settlement-v1';
	}

	private function actorMaySettle(string $actorUid, int $projectId): bool
	{
		if ($this->settleScopeCacheActor !== $actorUid) {
			$this->settleScopeCache = [];
			$this->settleScopeCacheActor = $actorUid;
		}
		if (!array_key_exists($projectId, $this->settleScopeCache)) {
			$this->settleScopeCache[$projectId] = $this->projectService->canUserSettleProject($actorUid, $projectId);
		}
		return $this->settleScopeCache[$projectId];
	}

	private function findEntry(int $id): ?TimeEntry
	{
		try {
			return $this->timeEntryMapper->find($id);
		} catch (\Throwable $e) {
			return null;
		}
	}
}
