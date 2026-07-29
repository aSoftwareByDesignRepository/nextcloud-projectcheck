<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Exception;

/**
 * HTTP 402 — commercial mobile companion access required / seat missing / expired.
 */
class PaymentRequiredException extends \Exception
{
	public function __construct(
		private readonly string $errorCode,
		string $message = '',
	) {
		parent::__construct($message !== '' ? $message : $errorCode, 402);
	}

	public function getErrorCode(): string
	{
		return $this->errorCode;
	}
}
