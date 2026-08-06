<?php

declare(strict_types=1);

/**
 * Read-only settlement API for trusted sibling apps (InvoiceCheck).
 *
 * Never SELECTs from outside ProjectCheck — callers must use this facade
 * instead of querying `pc_*` tables directly.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Public;

use OCA\ProjectCheck\Db\TimeEntry;
use OCA\ProjectCheck\Db\TimeEntryMapper;
use OCA\ProjectCheck\Service\CustomerService;
use OCA\ProjectCheck\Service\LocaleFormatService;
use OCA\ProjectCheck\Service\ProjectService;
use OCA\ProjectCheck\Service\ProjectSettlementService;
use OCA\ProjectCheck\Service\TimeEntryBillingService;
use OCA\ProjectCheck\Util\BillingStatus;
use OCA\ProjectCheck\Util\Money;
use OCP\App\IAppManager;

/**
 * Server-side only. Not an HTTP surface.
 */
class SettlementReadFacade
{
	public const MAX_ENTRIES = TimeEntryBillingService::MAX_BULK_ROWS;

	public function __construct(
		private TimeEntryMapper $timeEntryMapper,
		private ProjectService $projectService,
		private CustomerService $customerService,
		private ProjectSettlementService $projectSettlementService,
		private LocaleFormatService $localeFormat,
		private IAppManager $appManager,
	) {
	}

	public function isAvailable(): bool
	{
		return $this->appManager->isEnabledForUser('projectcheck');
	}

	public function getOrgCurrency(): string
	{
		return $this->localeFormat->getCurrency();
	}

	/**
	 * @param array{
	 *   customerId?: int,
	 *   projectId?: int,
	 *   dateFrom?: string,
	 *   dateTo?: string,
	 *   limit?: int
	 * } $filters
	 * @return list<OutstandingEntry>
	 */
	public function getOutstandingEntries(string $actorUid, array $filters = []): array
	{
		$normalized = $this->buildOpenFilters($actorUid, $filters);
		$limit = min(self::MAX_ENTRIES, max(1, (int) ($filters['limit'] ?? self::MAX_ENTRIES)));
		$ids = $this->timeEntryMapper->findIdsByFilters($normalized, $limit);
		if (count($ids) > $limit) {
			$ids = array_slice($ids, 0, $limit);
		}
		if ($ids === []) {
			return [];
		}

		$entries = $this->timeEntryMapper->findByIds($ids);
		$projectCache = [];
		$out = [];
		foreach ($entries as $entry) {
			$dto = $this->mapEntry($entry, $projectCache);
			if ($dto !== null) {
				$out[] = $dto;
			}
		}
		return $out;
	}

	/**
	 * Load specific entries (any status) for finalize validation / refresh.
	 *
	 * @param list<int> $entryIds
	 * @return list<OutstandingEntry>
	 */
	public function getEntriesByIds(string $actorUid, array $entryIds): array
	{
		return $this->loadEntriesByIds($entryIds, $actorUid);
	}

	/**
	 * Trusted sibling-app read without settle ACL (void paid-guard for IC admins).
	 * Callers must still enforce their own authorization.
	 *
	 * @param list<int> $entryIds
	 * @return list<OutstandingEntry>
	 */
	public function getEntriesByIdsTrusted(array $entryIds): array
	{
		return $this->loadEntriesByIds($entryIds, null);
	}

	/**
	 * @param list<int> $entryIds
	 * @return list<OutstandingEntry>
	 */
	private function loadEntriesByIds(array $entryIds, ?string $actorUid): array
	{
		$entryIds = array_values(array_unique(array_filter(array_map('intval', $entryIds), static fn (int $id): bool => $id > 0)));
		if ($entryIds === []) {
			return [];
		}
		sort($entryIds);
		$entries = $this->timeEntryMapper->findByIds($entryIds);
		$projectCache = [];
		$out = [];
		foreach ($entries as $entry) {
			$projectId = (int) $entry->getProjectId();
			if ($actorUid !== null && !$this->projectService->canUserSettleProject($actorUid, $projectId)) {
				continue;
			}
			$dto = $this->mapEntry($entry, $projectCache);
			if ($dto !== null) {
				$out[] = $dto;
			}
		}
		return $out;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function getProjectPosture(string $actorUid, int $projectId): array
	{
		if (!$this->projectService->canUserAccessProject($actorUid, $projectId)) {
			return [];
		}
		$project = $this->projectService->getProject($projectId);
		if ($project === null) {
			return [];
		}
		return $this->projectSettlementService->getSettlementInfo($project, $actorUid);
	}

	/**
	 * @return array{id: int, name: string, address: string|null, email: string|null}|null
	 */
	public function getCustomerSnapshot(int $customerId): ?array
	{
		$customer = $this->customerService->getCustomer($customerId);
		if ($customer === null) {
			return null;
		}
		return [
			'id' => (int) $customer->getId(),
			'name' => (string) $customer->getName(),
			'address' => $customer->getAddress(),
			'email' => $customer->getEmail(),
		];
	}

	/**
	 * Customers the actor may invoice (scoped to settleable projects).
	 *
	 * @return list<array{id: int, name: string, email: string|null}>
	 */
	public function listCustomersForInvoicing(string $actorUid): array
	{
		$projectIds = $this->projectService->getSettleableProjectIdListForUser($actorUid);
		if ($projectIds === null) {
			$options = $this->customerService->getCustomersForSelectForUser($actorUid);
			$out = [];
			foreach ($options as $row) {
				$out[] = [
					'id' => (int) $row['id'],
					'name' => (string) $row['name'],
					'email' => $row['email'] !== null ? (string) $row['email'] : null,
				];
			}
			usort($out, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));
			return $out;
		}

		$customers = [];
		foreach ($projectIds as $projectId) {
			$project = $this->projectService->getProject((int) $projectId);
			if ($project === null) {
				continue;
			}
			$customerId = (int) $project->getCustomerId();
			if ($customerId <= 0 || isset($customers[$customerId])) {
				continue;
			}
			$snap = $this->getCustomerSnapshot($customerId);
			if ($snap === null) {
				continue;
			}
			$customers[$customerId] = [
				'id' => $customerId,
				'name' => $snap['name'],
				'email' => $snap['email'],
			];
		}
		$out = array_values($customers);
		usort($out, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));
		return $out;
	}

	public function canUserSettleProject(string $actorUid, int $projectId): bool
	{
		return $this->projectService->canUserSettleProject($actorUid, $projectId);
	}

	/**
	 * @return list<int>|null null = all projects
	 */
	public function getSettleableProjectIds(string $actorUid): ?array
	{
		return $this->projectService->getSettleableProjectIdListForUser($actorUid);
	}

	/**
	 * @param array<string, mixed> $filters
	 * @return array<string, mixed>
	 */
	private function buildOpenFilters(string $actorUid, array $filters): array
	{
		$normalized = [
			'billing_status' => BillingStatus::OPEN,
		];

		$projectId = (int) ($filters['projectId'] ?? $filters['project_id'] ?? 0);
		$customerId = (int) ($filters['customerId'] ?? $filters['customer_id'] ?? 0);

		if ($projectId > 0) {
			if (!$this->projectService->canUserSettleProject($actorUid, $projectId)) {
				$normalized['project_ids'] = [];
				return $normalized;
			}
			if ($customerId > 0) {
				$project = $this->projectService->getProject($projectId);
				if ($project === null || (int) $project->getCustomerId() !== $customerId) {
					$normalized['project_ids'] = [];
					return $normalized;
				}
			}
			$normalized['project_id'] = $projectId;
		} elseif ($customerId > 0) {
			$projects = $this->projectService->getProjectsByCustomer($customerId);
			$ids = [];
			foreach ($projects as $project) {
				$pid = (int) $project->getId();
				if ($this->projectService->canUserSettleProject($actorUid, $pid)) {
					$ids[] = $pid;
				}
			}
			$normalized['project_ids'] = $ids;
		} else {
			$scope = $this->projectService->getSettleableProjectIdListForUser($actorUid);
			if ($scope !== null) {
				$normalized['project_ids'] = $scope;
			}
		}

		$dateFrom = trim((string) ($filters['dateFrom'] ?? $filters['date_from'] ?? ''));
		$dateTo = trim((string) ($filters['dateTo'] ?? $filters['date_to'] ?? ''));
		if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom) === 1) {
			$normalized['date_from'] = $dateFrom;
		}
		if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo) === 1) {
			$normalized['date_to'] = $dateTo;
		}

		return $normalized;
	}

	/**
	 * @param array<int, array{customerId: int}> $projectCache
	 */
	private function mapEntry(TimeEntry $entry, array &$projectCache): ?OutstandingEntry
	{
		$projectId = (int) $entry->getProjectId();
		if (!isset($projectCache[$projectId])) {
			$project = $this->projectService->getProject($projectId);
			if ($project === null) {
				return null;
			}
			$projectCache[$projectId] = ['customerId' => (int) $project->getCustomerId()];
		}

		$hours = Money::normalize($entry->getHours(), Money::HOUR_SCALE);
		$rate = Money::normalize($entry->getHourlyRate(), Money::MONEY_SCALE);
		$amount = Money::mul($hours, $rate, Money::MONEY_SCALE);
		$date = $entry->getDate();
		$updated = $entry->getUpdatedAt();

		return new OutstandingEntry(
			(int) $entry->getId(),
			$projectId,
			$projectCache[$projectId]['customerId'],
			(string) $entry->getUserId(),
			$date instanceof \DateTimeInterface ? $date->format('Y-m-d') : (string) $date,
			$hours,
			$rate,
			$amount,
			(string) ($entry->getDescription() ?? ''),
			$updated instanceof \DateTimeInterface ? $updated->format('Y-m-d H:i:s') : (string) $updated,
			$entry->getBillingStatus(),
		);
	}
}
