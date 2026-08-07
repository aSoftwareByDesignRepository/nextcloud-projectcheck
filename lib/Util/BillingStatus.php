<?php

declare(strict_types=1);

/**
 * Billing status vocabulary and transition matrix for time entries.
 *
 * Time entries are the single source of truth for settlement (feature spec
 * `time-entry-billing-status.md`, decisions D1–D4). Projects and customers
 * only ever show *derived* postures — see {@see SettlementPosture}.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Util;

final class BillingStatus
{
	public const OPEN = 'open';
	public const INVOICED = 'invoiced';
	public const PAID = 'paid';
	public const EXCLUDED = 'excluded';

	/** @var list<string> */
	public const ALL = [self::OPEN, self::INVOICED, self::PAID, self::EXCLUDED];

	/**
	 * Statuses that count towards "outstanding" (not yet paid) sums (D9).
	 *
	 * @var list<string>
	 */
	public const OUTSTANDING = [self::OPEN, self::INVOICED];

	/**
	 * Statuses that count as chargeable work (everything except excluded).
	 *
	 * @var list<string>
	 */
	public const CHARGEABLE = [self::OPEN, self::INVOICED, self::PAID];

	/**
	 * Statuses that lock entry content against owner edits / deletes and
	 * AZC upserts (D12).
	 *
	 * @var list<string>
	 */
	public const CONTENT_LOCKING = [self::INVOICED, self::PAID];

	/**
	 * Allowed transitions (spec §6.1). `open → paid` is intentionally absent:
	 * every payment must pass through `invoiced` so the AR trail stays honest
	 * (D4). Same-state re-application is *not* allowed either — callers get a
	 * clear `invalid transition` instead of a silent no-op.
	 *
	 * @var array<string, list<string>>
	 */
	private const TRANSITIONS = [
		self::OPEN => [self::INVOICED, self::EXCLUDED],
		self::INVOICED => [self::PAID, self::OPEN],
		self::PAID => [self::INVOICED],
		self::EXCLUDED => [self::OPEN],
	];

	public static function isValid(string $status): bool
	{
		return in_array($status, self::ALL, true);
	}

	/**
	 * Normalize a raw DB value; legacy rows without a status count as open.
	 */
	public static function normalize(?string $status): string
	{
		$status = strtolower(trim((string) $status));
		return self::isValid($status) ? $status : self::OPEN;
	}

	public static function isTransitionAllowed(string $from, string $to): bool
	{
		return in_array($to, self::TRANSITIONS[$from] ?? [], true);
	}

	/**
	 * @return list<string> statuses reachable from $from
	 */
	public static function allowedTargets(string $from): array
	{
		return self::TRANSITIONS[$from] ?? [];
	}

	public static function isOutstanding(string $status): bool
	{
		return in_array($status, self::OUTSTANDING, true);
	}

	public static function locksContent(string $status): bool
	{
		return in_array($status, self::CONTENT_LOCKING, true);
	}

	/**
	 * Initial status for a newly created entry (D7): overhead projects are
	 * internal work and never billed, so their entries start as excluded.
	 */
	public static function initialForProjectType(string $projectType): string
	{
		$overheadTypes = ['admin', 'meeting', 'internal', 'training'];
		return in_array($projectType, $overheadTypes, true) ? self::EXCLUDED : self::OPEN;
	}
}
