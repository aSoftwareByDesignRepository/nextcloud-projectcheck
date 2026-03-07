<?php

declare(strict_types=1);

/**
 * Time entry not found exception for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Exception;

use Exception;

/**
 * Exception thrown when a time entry is not found
 */
class TimeEntryNotFoundException extends Exception
{
	/**
	 * TimeEntryNotFoundException constructor
	 *
	 * @param int $timeEntryId The time entry ID that was not found
	 * @param string $message Custom error message
	 * @param int $code Error code
	 * @param Exception|null $previous Previous exception
	 */
	public function __construct(int $timeEntryId, string $message = '', int $code = 0, Exception $previous = null)
	{
		if (empty($message)) {
			$message = "Time entry with ID {$timeEntryId} not found";
		}
		
		parent::__construct($message, $code, $previous);
	}
}
