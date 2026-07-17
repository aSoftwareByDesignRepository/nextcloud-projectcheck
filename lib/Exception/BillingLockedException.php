<?php

declare(strict_types=1);

/**
 * Thrown when entry content (hours, project, date, description) may not be
 * changed because the entry is invoiced or paid (feature spec D12).
 * Controllers translate this into HTTP 409.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Exception;

use Exception;

class BillingLockedException extends Exception
{
	public function __construct(
		private readonly string $billingStatus,
		string $message = '',
		int $code = 0,
		?Exception $previous = null,
	) {
		if ($message === '') {
			$message = 'Time entry is locked for editing (status: ' . $billingStatus . ')';
		}
		parent::__construct($message, $code, $previous);
	}

	public function getBillingStatus(): string
	{
		return $this->billingStatus;
	}
}
