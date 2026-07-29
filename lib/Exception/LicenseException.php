<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Exception;

/**
 * License-domain validation / conflict (HTTP 422 / 409).
 * Separate from the legacy ValidationException(array $errors) used elsewhere.
 */
final class LicenseException extends \Exception
{
	public function __construct(
		private readonly string $errorCode,
		string $message = '',
		private readonly int $httpStatus = 422,
	) {
		parent::__construct($message !== '' ? $message : $errorCode);
	}

	public function getErrorCode(): string
	{
		return $this->errorCode;
	}

	public function getHttpStatus(): int
	{
		return $this->httpStatus;
	}
}
