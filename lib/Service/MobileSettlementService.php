<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Service;

use OCA\ProjectCheck\Exception\InvalidBillingTransitionException;
use OCA\ProjectCheck\Exception\MobileApiException;
use OCA\ProjectCheck\Exception\PermissionDeniedException;
use OCA\ProjectCheck\Exception\SettlementConflictException;
use OCA\ProjectCheck\Exception\TimeEntryNotFoundException;
use OCA\ProjectCheck\Exception\ValidationException;
use OCA\ProjectCheck\Util\BillingStatus;
use OCP\IL10N;

/**
 * Thin mobile settlement façade — reuses TimeEntryBillingService / ProjectSettlementService.
 * Seat gate is enforced by MobileController; ACL stays canUserSettleProject.
 */
class MobileSettlementService
{
	private const MAX_ENTRIES = 200;

	public function __construct(
		private readonly ProjectService $projects,
		private readonly TimeEntryService $timeEntries,
		private readonly TimeEntryBillingService $billing,
		private readonly ProjectSettlementService $projectSettlement,
		private readonly IL10N $l,
	) {
	}

	public function actorCanSettleAnything(string $uid): bool
	{
		$list = $this->projects->getSettleableProjectIdListForUser($uid);
		if ($list === null) {
			return true;
		}
		return $list !== [];
	}

	/**
	 * Outstanding (or filtered) entries on projects the actor may settle.
	 *
	 * @return array{entries: list<array<string, mixed>>, hasMore: bool, limit: int}
	 */
	public function listSettleableEntries(
		string $uid,
		?string $from,
		?string $to,
		?string $billingStatus,
	): array {
		if (!$this->actorCanSettleAnything($uid)) {
			throw new MobileApiException('forbidden', $this->l->t('You cannot settle projects.'), 403);
		}

		$status = $billingStatus === null || $billingStatus === ''
			? 'outstanding'
			: BillingStatus::normalize($billingStatus);
		if ($status !== 'all' && $status !== 'outstanding' && !in_array($status, BillingStatus::ALL, true)) {
			throw new MobileApiException('validation', $this->l->t('Invalid billing status filter.'), 422, [
				'fields' => ['billingStatus' => 'invalid'],
			]);
		}

		$filters = [
			// Fetch one extra so we can set hasMore without a second count query.
			'limit' => self::MAX_ENTRIES + 1,
			'offset' => 0,
		];
		$scope = $this->projects->getSettleableProjectIdListForUser($uid);
		if ($scope !== null) {
			if ($scope === []) {
				return ['entries' => [], 'hasMore' => false, 'limit' => self::MAX_ENTRIES];
			}
			$filters['project_ids'] = $scope;
		}
		if ($from !== null && $from !== '') {
			$this->assertYmd($from, 'from');
			$filters['date_from'] = $from;
		}
		if ($to !== null && $to !== '') {
			$this->assertYmd($to, 'to');
			$filters['date_to'] = $to;
		}
		if ($status !== 'all') {
			$filters['billing_status'] = $status;
		}

		$raw = $this->timeEntries->getTimeEntriesWithProjectInfo($filters);
		$entries = [];
		$hasMore = false;
		foreach ($raw as $row) {
			$projectId = (int)($row['project_id'] ?? $row['projectId'] ?? 0);
			if (isset($row['timeEntry']) && is_object($row['timeEntry']) && method_exists($row['timeEntry'], 'getProjectId')) {
				$projectId = (int)$row['timeEntry']->getProjectId();
			}
			// Defence in depth: never leak entries outside settle ACL.
			if (!$this->projects->canUserSettleProject($uid, $projectId)) {
				continue;
			}
			if (count($entries) >= self::MAX_ENTRIES) {
				$hasMore = true;
				break;
			}
			$entries[] = $this->entryRow($row);
		}

		return [
			'entries' => $entries,
			'hasMore' => $hasMore,
			'limit' => self::MAX_ENTRIES,
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	public function changeEntryStatus(string $uid, int $entryId, string $target): array
	{
		$target = strtolower(trim($target));
		if (!BillingStatus::isValid($target)) {
			throw new MobileApiException('validation', $this->l->t('Invalid settlement status.'), 422, [
				'fields' => ['target' => 'invalid'],
			]);
		}

		try {
			$entry = $this->billing->changeStatus($entryId, $target, $uid);
		} catch (TimeEntryNotFoundException) {
			throw new MobileApiException('not_found', $this->l->t('Time entry not found.'), 404);
		} catch (PermissionDeniedException) {
			throw new MobileApiException('forbidden', $this->l->t('You cannot settle this entry.'), 403);
		} catch (InvalidBillingTransitionException $e) {
			throw new MobileApiException('validation', $e->getMessage() !== '' ? $e->getMessage() : $this->l->t('That status change is not allowed.'), 422, [
				'fields' => ['target' => 'invalid_transition'],
			]);
		} catch (SettlementConflictException $e) {
			throw new MobileApiException('conflict', $e->getMessage() !== '' ? $e->getMessage() : $this->l->t('This entry changed. Refresh and try again.'), 409);
		} catch (ValidationException $e) {
			throw new MobileApiException('validation', $e->getMessage() !== '' ? $e->getMessage() : $this->l->t('Validation failed.'), 422);
		}

		$status = $entry->getBillingStatus();
		return [
			'id' => (int)$entry->getId(),
			'billingStatus' => $status,
			'allowedTargets' => BillingStatus::allowedTargets($status),
			'billingLocked' => $entry->isBillingLocked(),
		];
	}

	/**
	 * @param array<string, mixed> $body
	 * @return array<string, mixed>
	 */
	public function previewProjectSettle(string $uid, int $projectId, array $body): array
	{
		if (!$this->projects->canUserSettleProject($uid, $projectId)) {
			throw new MobileApiException('forbidden', $this->l->t('You cannot settle this project.'), 403);
		}

		$action = (string)($body['action'] ?? '');
		try {
			$result = $this->projectSettlement->previewProjectSettle(
				$projectId,
				$action,
				[
					'date_from' => (string)($body['date_from'] ?? $body['dateFrom'] ?? ''),
					'date_to' => (string)($body['date_to'] ?? $body['dateTo'] ?? ''),
				],
				$uid
			);
		} catch (PermissionDeniedException) {
			throw new MobileApiException('forbidden', $this->l->t('You cannot settle this project.'), 403);
		} catch (ValidationException $e) {
			throw new MobileApiException('validation', $e->getMessage() !== '' ? $e->getMessage() : $this->l->t('Validation failed.'), 422);
		}

		return $this->normalizePreviewPayload($action, $result);
	}

	/**
	 * @param array<string, mixed> $body
	 * @return array<string, mixed>
	 */
	public function applyProjectSettle(string $uid, int $projectId, array $body): array
	{
		if (!$this->projects->canUserSettleProject($uid, $projectId)) {
			throw new MobileApiException('forbidden', $this->l->t('You cannot settle this project.'), 403);
		}

		$token = (string)($body['token'] ?? '');
		if ($token === '') {
			throw new MobileApiException('validation', $this->l->t('Preview token is required.'), 422, [
				'fields' => ['token' => 'required'],
			]);
		}

		$action = (string)($body['action'] ?? '');
		try {
			$result = $this->projectSettlement->applyProjectSettle(
				$projectId,
				$action,
				[
					'date_from' => (string)($body['date_from'] ?? $body['dateFrom'] ?? ''),
					'date_to' => (string)($body['date_to'] ?? $body['dateTo'] ?? ''),
				],
				$uid,
				$token
			);
		} catch (PermissionDeniedException) {
			throw new MobileApiException('forbidden', $this->l->t('You cannot settle this project.'), 403);
		} catch (SettlementConflictException $e) {
			throw new MobileApiException('conflict', $e->getMessage() !== '' ? $e->getMessage() : $this->l->t('Settlement changed. Preview again.'), 409);
		} catch (ValidationException $e) {
			throw new MobileApiException('validation', $e->getMessage() !== '' ? $e->getMessage() : $this->l->t('Validation failed.'), 422);
		}

		$applied = (int)($result['applied'] ?? 0);
		$failed = is_array($result['failed'] ?? null) ? $result['failed'] : [];

		// Apply only returns counts — never invent hours/amount (preview already showed money).
		return [
			'action' => $action,
			'appliedCount' => $applied,
			'applied' => $applied,
			'failed' => $failed,
			'success' => true,
		];
	}

	/**
	 * Mobile JSON contract uses camelCase entryCount/totalHours/totalAmount.
	 *
	 * @param array<string, mixed> $preview
	 * @return array<string, mixed>
	 */
	private function normalizePreviewPayload(string $action, array $preview): array
	{
		$count = (int)($preview['count'] ?? $preview['entryCount'] ?? 0);
		$hours = (float)($preview['hours'] ?? $preview['totalHours'] ?? 0);
		$amount = (float)($preview['amount'] ?? $preview['totalAmount'] ?? 0);
		$token = $preview['token'] ?? null;

		return [
			'action' => $action,
			'entryCount' => $count,
			'totalHours' => $hours,
			'totalAmount' => $amount,
			// Also expose snake keys for defensive clients / web parity.
			'count' => $count,
			'hours' => $hours,
			'amount' => $amount,
			'token' => is_string($token) ? $token : null,
			'cap' => (int)($preview['cap'] ?? 500),
			'capExceeded' => (bool)($preview['capExceeded'] ?? false),
			'target' => (string)($preview['target'] ?? ''),
			'success' => true,
		];
	}

	private function assertYmd(string $value, string $field): void
	{
		$dt = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
		$errors = \DateTimeImmutable::getLastErrors();
		if ($dt === false || ($errors['warning_count'] ?? 0) > 0 || ($errors['error_count'] ?? 0) > 0) {
			throw new MobileApiException('validation', $this->l->t('%s must be YYYY-MM-DD.', [$field]), 422, [
				'fields' => [$field => 'invalid_format'],
			]);
		}
	}

	/**
	 * @param array{timeEntry?: mixed}|array<string, mixed> $row
	 * @return array<string, mixed>
	 */
	private function entryRow(array $row): array
	{
		if (isset($row['timeEntry']) && is_object($row['timeEntry'])) {
			$te = $row['timeEntry'];
			$hours = (float)$te->getHours();
			$status = $te->getBillingStatus();
			return [
				'id' => (int)$te->getId(),
				'projectId' => (int)$te->getProjectId(),
				'projectName' => (string)($row['projectName'] ?? ''),
				'customerName' => (string)($row['customerName'] ?? ''),
				'userId' => (string)$te->getUserId(),
				'date' => $te->getFormattedDate(),
				'durationMinutes' => (int)round($hours * 60),
				'hours' => $hours,
				'description' => (string)($te->getDescription() ?? ''),
				'hourlyRate' => (float)$te->getHourlyRate(),
				'billingStatus' => $status,
				'billingLocked' => $te->isBillingLocked(),
				'allowedTargets' => BillingStatus::allowedTargets($status),
				'editable' => false,
			];
		}

		$hours = (float)($row['hours'] ?? 0);
		$status = BillingStatus::normalize((string)($row['billing_status'] ?? $row['billingStatus'] ?? BillingStatus::OPEN));
		$locked = in_array($status, [BillingStatus::INVOICED, BillingStatus::PAID], true);
		return [
			'id' => (int)($row['id'] ?? 0),
			'projectId' => (int)($row['project_id'] ?? $row['projectId'] ?? 0),
			'projectName' => (string)($row['project_name'] ?? $row['projectName'] ?? ''),
			'customerName' => (string)($row['customer_name'] ?? $row['customerName'] ?? ''),
			'userId' => (string)($row['user_id'] ?? $row['userId'] ?? ''),
			'date' => (string)($row['date'] ?? ''),
			'durationMinutes' => (int)round($hours * 60),
			'hours' => $hours,
			'description' => (string)($row['description'] ?? ''),
			'hourlyRate' => (float)($row['hourly_rate'] ?? $row['hourlyRate'] ?? 0),
			'billingStatus' => $status,
			'billingLocked' => $locked,
			'allowedTargets' => BillingStatus::allowedTargets($status),
			'editable' => false,
		];
	}
}
