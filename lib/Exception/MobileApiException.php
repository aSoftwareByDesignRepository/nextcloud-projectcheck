<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Exception;

/**
 * Structured mobile JSON API error (validation / conflict / forbidden / not found).
 *
 * @phpstan-type ErrorDetails array<string, mixed>
 */
class MobileApiException extends \Exception
{
	/**
	 * @param ErrorDetails $details
	 */
	public function __construct(
		private readonly string $errorCode,
		string $message,
		private readonly int $httpStatus,
		private readonly array $details = [],
	) {
		parent::__construct($message, $httpStatus);
	}

	public function getErrorCode(): string
	{
		return $this->errorCode;
	}

	public function getHttpStatus(): int
	{
		return $this->httpStatus;
	}

	/**
	 * @return ErrorDetails
	 */
	public function getDetails(): array
	{
		return $this->details;
	}
}
