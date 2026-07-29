<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Exception;

/**
 * Mobile gate ladder failure — HTTP 402 with one of:
 * `license_missing`, `license_expired`, `seat_required`, `seat_limit_exceeded`.
 */
class MobileGateException extends \Exception
{
	public function __construct(
		private readonly string $errorCode,
	) {
		parent::__construct($errorCode, 402);
	}

	public function getErrorCode(): string
	{
		return $this->errorCode;
	}
}
