<?php

declare(strict_types=1);

/**
 * Raised when an hourly rate cannot be resolved for a time entry.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Exception;

use Exception;

class RateResolutionException extends Exception
{
	public function __construct(
		string $message,
		private readonly string $codeKey = 'rate_unresolved',
		int $httpCode = 0,
		?Exception $previous = null,
	) {
		parent::__construct($message, $httpCode, $previous);
	}

	public function getCodeKey(): string
	{
		return $this->codeKey;
	}
}
