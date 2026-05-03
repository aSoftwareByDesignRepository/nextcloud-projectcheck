<?php

declare(strict_types=1);

/**
 * Customer not found exception for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Exception;

use Exception;

/**
 * Exception thrown when a customer is not found
 */
class CustomerNotFoundException extends Exception
{
	/**
	 * CustomerNotFoundException constructor
	 *
	 * @param int $customerId The customer ID that was not found
	 * @param string $message Custom error message
	 * @param int $code Error code
	 * @param Exception|null $previous Previous exception
	 */
	public function __construct(int $customerId, string $message = '', int $code = 0, ?Exception $previous = null)
	{
		if (empty($message)) {
			$message = "Customer with ID {$customerId} not found";
		}
		
		parent::__construct($message, $code, $previous);
	}
}
