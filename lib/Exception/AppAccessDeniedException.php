<?php

declare(strict_types=1);

/**
 * Typed exception for app-level access denial in ProjectCheck.
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Exception;

class AppAccessDeniedException extends \RuntimeException
{
	public function __construct(string $message = 'Access denied', int $code = 0, ?\Throwable $previous = null)
	{
		parent::__construct($message, $code, $previous);
	}
}
