<?php

/**
 * Validation exception for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Exception;

use Exception;

/**
 * Exception thrown when validation fails
 */
class ValidationException extends Exception
{
	/** @var array */
	private $errors;

	/**
	 * ValidationException constructor
	 *
	 * @param array $errors Array of validation errors
	 * @param string $message Custom error message
	 * @param int $code Error code
	 * @param Exception|null $previous Previous exception
	 */
	public function __construct(array $errors, string $message = '', int $code = 0, Exception $previous = null)
	{
		if (empty($message)) {
			$message = 'Validation failed';
		}
		
		$this->errors = $errors;
		parent::__construct($message, $code, $previous);
	}

	/**
	 * Get validation errors
	 *
	 * @return array
	 */
	public function getErrors(): array
	{
		return $this->errors;
	}
}
