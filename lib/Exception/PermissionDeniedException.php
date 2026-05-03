<?php

declare(strict_types=1);

/**
 * Permission denied exception for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Exception;

use Exception;

/**
 * Exception thrown when permission is denied
 */
class PermissionDeniedException extends Exception
{
	/**
	 * PermissionDeniedException constructor
	 *
	 * @param string $action The action that was denied
	 * @param string $resource The resource that was accessed
	 * @param string $message Custom error message
	 * @param int $code Error code
	 * @param Exception|null $previous Previous exception
	 */
	public function __construct(string $action, string $resource, string $message = '', int $code = 0, ?Exception $previous = null)
	{
		if (empty($message)) {
			$message = "Permission denied: Cannot {$action} on {$resource}";
		}
		
		parent::__construct($message, $code, $previous);
	}
}
