<?php

declare(strict_types=1);

/**
 * SQL predicates for the project settlement filter (feature spec §13).
 *
 * Mirrors {@see \OCA\ProjectCheck\Util\SettlementPosture::fromCounters()} as
 * pure counter predicates on `pc_projects.stl_*_hours`, so list filtering and
 * counting never need per-entry scans (spec: "counter predicates, not N+1").
 * Any change to the posture derivation must be reflected here — the unit
 * tests cover both against the same fixtures.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Db;

use OCA\ProjectCheck\Util\SettlementPosture;
use OCP\DB\QueryBuilder\IQueryBuilder;

final class ProjectSettlementFilter
{
	/** @var list<string> */
	public const CODES = ['outstanding', SettlementPosture::OPEN, SettlementPosture::PARTIAL, SettlementPosture::AWAITING_PAYMENT, SettlementPosture::PAID, SettlementPosture::NA];

	/**
	 * Sentinel for unknown URL/API values. Must never be treated as "no filter"
	 * — finance filters fail closed (parity with {@see \OCA\ProjectCheck\Service\CustomerSettlementService::matchesSettlementFilter}).
	 */
	public const INVALID = '__invalid__';

	/**
	 * Normalize a raw request value to a known filter code.
	 * '' = no filter (all); {@see INVALID} = unknown input (match nothing).
	 */
	public static function normalize(string $value): string
	{
		$value = strtolower(trim($value));
		if ($value === '' || $value === 'all') {
			return '';
		}
		return in_array($value, self::CODES, true) ? $value : self::INVALID;
	}

	/**
	 * Add the WHERE predicates for one settlement filter code to a query on
	 * `pc_projects` aliased as $alias. Empty = no-op; unknown = match nothing.
	 */
	public static function apply(IQueryBuilder $qb, string $settlement, string $alias = 'p'): void
	{
		$settlement = self::normalize($settlement);
		if ($settlement === '') {
			return;
		}
		if ($settlement === self::INVALID) {
			// Always-false predicate: typos must not silently show every project.
			$qb->andWhere($qb->expr()->eq(
				$qb->createNamedParameter(1, IQueryBuilder::PARAM_INT),
				$qb->createNamedParameter(0, IQueryBuilder::PARAM_INT)
			));
			return;
		}

		$zero = static fn (): string => $qb->createNamedParameter('0.00');
		$open = $alias . '.stl_open_hours';
		$invoiced = $alias . '.stl_invoiced_hours';
		$paid = $alias . '.stl_paid_hours';

		switch ($settlement) {
			case 'outstanding':
				$qb->andWhere($qb->expr()->orX(
					$qb->expr()->gt($open, $zero()),
					$qb->expr()->gt($invoiced, $zero())
				));
				break;
			case SettlementPosture::OPEN:
				$qb->andWhere($qb->expr()->gt($open, $zero()))
					->andWhere($qb->expr()->lte($invoiced, $zero()))
					->andWhere($qb->expr()->lte($paid, $zero()));
				break;
			case SettlementPosture::PARTIAL:
				$qb->andWhere($qb->expr()->gt($open, $zero()))
					->andWhere($qb->expr()->orX(
						$qb->expr()->gt($invoiced, $zero()),
						$qb->expr()->gt($paid, $zero())
					));
				break;
			case SettlementPosture::AWAITING_PAYMENT:
				$qb->andWhere($qb->expr()->lte($open, $zero()))
					->andWhere($qb->expr()->gt($invoiced, $zero()));
				break;
			case SettlementPosture::PAID:
				$qb->andWhere($qb->expr()->lte($open, $zero()))
					->andWhere($qb->expr()->lte($invoiced, $zero()))
					->andWhere($qb->expr()->gt($paid, $zero()));
				break;
			case SettlementPosture::NA:
				$qb->andWhere($qb->expr()->lte($open, $zero()))
					->andWhere($qb->expr()->lte($invoiced, $zero()))
					->andWhere($qb->expr()->lte($paid, $zero()));
				break;
		}
	}
}
