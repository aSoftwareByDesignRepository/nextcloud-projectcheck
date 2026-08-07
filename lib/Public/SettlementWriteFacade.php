<?php

declare(strict_types=1);

/**
 * Settlement write API for trusted sibling apps (InvoiceCheck).
 *
 * All mutations go through {@see TimeEntryBillingService} — never raw SQL.
 * Optimistic `expectedUpdatedAt` checks fail fast before any write so a
 * stale invoice draft cannot silently overwrite concurrent settlement.
 *
 * `$trustedSiblingApp` skips ProjectCheck settle ACL for server-side callers
 * that already authorized the actor as an InvoiceCheck global invoicer.
 * Never expose that flag on HTTP surfaces.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Public;

use OCA\ProjectCheck\Db\TimeEntryMapper;
use OCA\ProjectCheck\Service\TimeEntryBillingService;
use OCA\ProjectCheck\Util\BillingStatus;
use OCA\ProjectCheck\Util\Money;

/**
 * Server-side only. Not an HTTP surface.
 */
class SettlementWriteFacade
{
	public function __construct(
		private TimeEntryBillingService $billingService,
		private TimeEntryMapper $timeEntryMapper,
	) {
	}

	/**
	 * @param list<int> $entryIds
	 * @param array<int, string> $expectedUpdatedAt map entryId => 'Y-m-d H:i:s'
	 * @param array{invoiceId?: int, invoiceNumber?: string} $meta
	 */
	public function markEntriesInvoiced(
		string $actorUid,
		array $entryIds,
		array $expectedUpdatedAt = [],
		array $meta = [],
		bool $requireFullSuccess = true,
		bool $trustedSiblingApp = false,
	): SettlementResult {
		unset($meta);
		return $this->transition(
			$actorUid,
			$entryIds,
			$expectedUpdatedAt,
			BillingStatus::INVOICED,
			BillingStatus::OPEN,
			$requireFullSuccess,
			true,
			$trustedSiblingApp,
		);
	}

	/**
	 * @param list<int> $entryIds
	 * @param array<int, string> $expectedUpdatedAt
	 * @param array{invoiceId?: int, paymentId?: int} $meta
	 */
	public function markEntriesPaid(
		string $actorUid,
		array $entryIds,
		array $expectedUpdatedAt = [],
		array $meta = [],
		bool $requireFullSuccess = true,
		bool $trustedSiblingApp = false,
	): SettlementResult {
		unset($meta);
		return $this->transition(
			$actorUid,
			$entryIds,
			$expectedUpdatedAt,
			BillingStatus::PAID,
			BillingStatus::INVOICED,
			$requireFullSuccess,
			true,
			$trustedSiblingApp,
		);
	}

	/**
	 * @param list<int> $entryIds
	 * @param array<int, string> $expectedUpdatedAt
	 * @param array{invoiceId?: int} $meta
	 */
	public function reopenEntries(
		string $actorUid,
		array $entryIds,
		array $expectedUpdatedAt = [],
		array $meta = [],
		bool $requireFullSuccess = true,
		bool $trustedSiblingApp = false,
	): SettlementResult {
		unset($meta);
		return $this->transition(
			$actorUid,
			$entryIds,
			$expectedUpdatedAt,
			BillingStatus::OPEN,
			BillingStatus::INVOICED,
			$requireFullSuccess,
			false,
			$trustedSiblingApp,
		);
	}

	/**
	 * @param list<int> $entryIds
	 * @param array<int, string> $expectedUpdatedAt
	 */
	private function transition(
		string $actorUid,
		array $entryIds,
		array $expectedUpdatedAt,
		string $target,
		string $expectedSource,
		bool $requireFullSuccess,
		bool $alreadyAtTargetIsOk,
		bool $trustedSiblingApp,
	): SettlementResult {
		$entryIds = array_values(array_unique(array_filter(array_map('intval', $entryIds), static fn (int $id): bool => $id > 0)));
		if ($entryIds === []) {
			return new SettlementResult(0, []);
		}
		sort($entryIds);

		$preFailed = [];
		$toApply = [];
		$entries = $this->timeEntryMapper->findByIds($entryIds);
		$byId = [];
		foreach ($entries as $entry) {
			$byId[(int) $entry->getId()] = $entry;
		}

		foreach ($entryIds as $id) {
			$entry = $byId[$id] ?? null;
			if ($entry === null) {
				$preFailed[] = ['id' => $id, 'reason' => 'not_found'];
				continue;
			}
			$status = $entry->getBillingStatus();
			if ($status === $target && $alreadyAtTargetIsOk) {
				continue;
			}
			if ($status !== $expectedSource) {
				$preFailed[] = ['id' => $id, 'reason' => 'invalid_transition'];
				continue;
			}
			if (isset($expectedUpdatedAt[$id])) {
				$expected = (string) $expectedUpdatedAt[$id];
				$actual = $entry->getUpdatedAt()->format('Y-m-d H:i:s');
				if ($expected !== '' && $actual !== $expected) {
					$preFailed[] = ['id' => $id, 'reason' => 'conflict_updated_at'];
					continue;
				}
			}
			if (Money::compare($entry->getHours(), '0', Money::HOUR_SCALE) <= 0) {
				$preFailed[] = ['id' => $id, 'reason' => 'invalid_hours'];
				continue;
			}
			$toApply[] = $id;
		}

		if ($requireFullSuccess && $preFailed !== []) {
			return new SettlementResult(0, $preFailed);
		}

		$applied = 0;
		$failed = $preFailed;
		if ($toApply !== []) {
			$result = $this->billingService->bulkChangeStatusByIds($toApply, $target, $actorUid, $trustedSiblingApp);
			$applied = (int) $result['applied'];
			foreach ($result['failed'] as $row) {
				$failed[] = $row;
			}
		}

		if ($requireFullSuccess && $failed !== []) {
			return new SettlementResult($applied, $failed);
		}

		return new SettlementResult($applied, $failed);
	}
}
