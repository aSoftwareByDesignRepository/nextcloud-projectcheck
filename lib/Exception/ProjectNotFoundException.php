<?php

declare(strict_types=1);

/**
 * Project not found exception for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Exception;

use Exception;

/**
 * Exception thrown when a project is not found
 */
class ProjectNotFoundException extends Exception
{
	/**
	 * ProjectNotFoundException constructor
	 *
	 * @param int $projectId The project ID that was not found
	 * @param string $message Custom error message
	 * @param int $code Error code
	 * @param Exception|null $previous Previous exception
	 */
	public function __construct(int $projectId, string $message = '', int $code = 0, ?Exception $previous = null)
	{
		if (empty($message)) {
			$message = "Project with ID {$projectId} not found";
		}
		
		parent::__construct($message, $code, $previous);
	}
}
