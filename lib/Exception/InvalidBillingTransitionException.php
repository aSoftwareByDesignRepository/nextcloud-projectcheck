<?php

declare(strict_types=1);

/**
 * Thrown for billing status transitions outside the allowed state machine
 * (e.g. open → paid, or re-applying the current status). HTTP 400.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Exception;

use Exception;

class InvalidBillingTransitionException extends Exception
{
	public function __construct(
		private readonly string $fromStatus,
		private readonly string $toStatus,
		string $message = '',
		?Exception $previous = null,
	) {
		if ($message === '') {
			$message = 'Invalid billing transition: ' . $fromStatus . ' → ' . $toStatus;
		}
		parent::__construct($message, 0, $previous);
	}

	public function getFromStatus(): string
	{
		return $this->fromStatus;
	}

	public function getToStatus(): string
	{
		return $this->toStatus;
	}
}
