<?php

declare(strict_types=1);

/**
 * Thrown when an optimistic settlement write loses a race (the entry changed
 * between read and write, or a preview no longer matches the live data).
 * Controllers translate this into HTTP 409 with a machine-readable code.
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Exception;

use Exception;

class SettlementConflictException extends Exception
{
	public const CODE_UPDATED_AT = 'conflict_updated_at';
	public const CODE_STALE_PREVIEW = 'stale_preview';
	public const CODE_TOKEN_USED = 'token_used';

	public function __construct(
		private readonly string $conflictCode,
		string $message = '',
		?Exception $previous = null,
	) {
		if ($message === '') {
			$message = 'Settlement conflict: ' . $conflictCode;
		}
		parent::__construct($message, 0, $previous);
	}

	public function getConflictCode(): string
	{
		return $this->conflictCode;
	}
}
