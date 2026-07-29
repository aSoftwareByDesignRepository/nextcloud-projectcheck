<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Service;

/**
 * SPEC §8.4 seat rank rule — deterministic downgrade behaviour.
 *
 * A seat is within limit iff its rank ordered by (assigned_at ASC, id ASC)
 * is ≤ mobile_seats of the currently stored key. Pure and unit-tested.
 */
class SeatRank
{
	/**
	 * @param list<array{id: int, assignedAt: int}> $seats unordered seat rows
	 * @return array<int, int> seat id → 1-based rank
	 */
	public static function ranks(array $seats): array
	{
		usort($seats, static function (array $a, array $b): int {
			if ($a['assignedAt'] !== $b['assignedAt']) {
				return $a['assignedAt'] <=> $b['assignedAt'];
			}
			return $a['id'] <=> $b['id'];
		});
		$ranks = [];
		$rank = 1;
		foreach ($seats as $seat) {
			$ranks[$seat['id']] = $rank;
			$rank++;
		}
		return $ranks;
	}

	/**
	 * @param list<array{id: int, assignedAt: int}> $seats
	 */
	public static function isWithinLimit(array $seats, int $seatId, int $limit): bool
	{
		if ($limit <= 0) {
			return false;
		}
		$ranks = self::ranks($seats);
		if (!isset($ranks[$seatId])) {
			return false;
		}
		return $ranks[$seatId] <= $limit;
	}
}
