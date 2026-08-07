<?php

declare(strict_types=1);

/**
 * DTO for an open (billable) time entry exposed to trusted invoicing apps.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Public;

/**
 * @psalm-immutable
 */
final class OutstandingEntry
{
	public function __construct(
		public readonly int $id,
		public readonly int $projectId,
		public readonly int $customerId,
		public readonly string $userId,
		public readonly string $date,
		public readonly string $hours,
		public readonly string $rate,
		public readonly string $amount,
		public readonly string $description,
		public readonly string $updatedAt,
		public readonly string $billingStatus,
	) {
	}

	/**
	 * @return array{
	 *   id: int,
	 *   projectId: int,
	 *   customerId: int,
	 *   userId: string,
	 *   date: string,
	 *   hours: string,
	 *   rate: string,
	 *   amount: string,
	 *   description: string,
	 *   updatedAt: string,
	 *   billingStatus: string
	 * }
	 */
	public function toArray(): array
	{
		return [
			'id' => $this->id,
			'projectId' => $this->projectId,
			'customerId' => $this->customerId,
			'userId' => $this->userId,
			'date' => $this->date,
			'hours' => $this->hours,
			'rate' => $this->rate,
			'amount' => $this->amount,
			'description' => $this->description,
			'updatedAt' => $this->updatedAt,
			'billingStatus' => $this->billingStatus,
		];
	}
}
