<?php

declare(strict_types=1);

/**
 * Derived settlement posture for projects and customers.
 *
 * A posture is never stored or hand-edited (feature spec decision D2). It is
 * computed from the materialized project counters ({@see fromCounters}) or
 * combined across multiple projects for the customer chip ({@see combine}).
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Util;

final class SettlementPosture
{
	/** No chargeable hours at all (none, or only excluded). */
	public const NA = 'n_a';
	/** All chargeable hours are still open. */
	public const OPEN = 'open';
	/** Mix of open / invoiced / paid. */
	public const PARTIAL = 'partial';
	/** No open hours; at least one invoiced; rest paid. */
	public const AWAITING_PAYMENT = 'awaiting_payment';
	/** All chargeable hours are paid. */
	public const PAID = 'paid';

	/** @var list<string> */
	public const ALL = [self::NA, self::OPEN, self::PARTIAL, self::AWAITING_PAYMENT, self::PAID];

	public static function isValid(string $posture): bool
	{
		return in_array($posture, self::ALL, true);
	}

	/**
	 * Posture from project counters (spec §6.3). Hours are compared via
	 * {@see Money::compare} so DECIMAL string values from the DB never hit
	 * float equality traps.
	 *
	 * @param array{open_hours: mixed, invoiced_hours: mixed, paid_hours: mixed} $counters
	 */
	public static function fromCounters(array $counters): string
	{
		$open = Money::normalize($counters['open_hours'] ?? 0, Money::HOUR_SCALE);
		$invoiced = Money::normalize($counters['invoiced_hours'] ?? 0, Money::HOUR_SCALE);
		$paid = Money::normalize($counters['paid_hours'] ?? 0, Money::HOUR_SCALE);

		$chargeable = Money::add(Money::add($open, $invoiced, Money::HOUR_SCALE), $paid, Money::HOUR_SCALE);
		if (Money::isZero($chargeable)) {
			return self::NA;
		}

		$outstanding = Money::add($open, $invoiced, Money::HOUR_SCALE);
		if (Money::isZero($outstanding)) {
			return self::PAID;
		}

		if (Money::isZero($open)) {
			// Only invoiced (and possibly paid) remains — money is on its way.
			return self::AWAITING_PAYMENT;
		}

		if (Money::isZero($paid) && Money::isZero($invoiced)) {
			return self::OPEN;
		}

		return self::PARTIAL;
	}

	/**
	 * Combine several project postures into one customer-level chip
	 * (spec §6.5). Postures without chargeable hours (`n_a`) do not dilute
	 * the result; they only matter when *every* project is `n_a`.
	 *
	 * Rules (documented by unit tests as the executable spec):
	 *  - no chargeable anywhere              → n_a
	 *  - all chargeable projects paid        → paid
	 *  - all chargeable projects open        → open
	 *  - no project has open hours, at least
	 *    one is awaiting payment            → awaiting_payment
	 *  - anything else (mixed progress)      → partial
	 *
	 * @param list<string> $postures
	 */
	public static function combine(array $postures): string
	{
		$relevant = array_values(array_filter(
			$postures,
			static fn (string $p): bool => $p !== self::NA && self::isValid($p)
		));

		if ($relevant === []) {
			return self::NA;
		}

		$counts = array_fill_keys(self::ALL, 0);
		foreach ($relevant as $posture) {
			$counts[$posture]++;
		}

		$total = count($relevant);
		if ($counts[self::PAID] === $total) {
			return self::PAID;
		}
		if ($counts[self::OPEN] === $total) {
			return self::OPEN;
		}
		// "No open hours anywhere" means every project is paid or awaiting;
		// with at least one awaiting the customer is awaiting payment.
		if ($counts[self::OPEN] === 0 && $counts[self::PARTIAL] === 0) {
			return self::AWAITING_PAYMENT;
		}

		return self::PARTIAL;
	}
}
