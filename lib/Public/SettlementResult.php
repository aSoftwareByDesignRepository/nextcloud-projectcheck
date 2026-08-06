<?php

declare(strict_types=1);

/**
 * Result of a trusted-app settlement write (InvoiceCheck handshake).
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Public;

/**
 * @psalm-immutable
 */
final class SettlementResult
{
	/**
	 * @param list<array{id: int, reason: string}> $failed
	 */
	public function __construct(
		public readonly int $applied,
		public readonly array $failed,
	) {
	}

	public function isFullSuccess(): bool
	{
		return $this->failed === [] && $this->applied > 0;
	}

	public function isEmptySuccess(): bool
	{
		return $this->failed === [] && $this->applied === 0;
	}

	/**
	 * @return list<int>
	 */
	public function failedIds(): array
	{
		$ids = [];
		foreach ($this->failed as $row) {
			$ids[] = (int) $row['id'];
		}
		return $ids;
	}
}
