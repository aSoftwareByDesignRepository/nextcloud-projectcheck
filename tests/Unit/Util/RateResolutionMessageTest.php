<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Util;

use OCA\ProjectCheck\Exception\RateResolutionException;
use OCA\ProjectCheck\Util\RateResolutionMessage;
use OCP\IL10N;
use PHPUnit\Framework\TestCase;

class RateResolutionMessageTest extends TestCase
{
	public function testMapsKnownCodeKeys(): void
	{
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(static fn (string $msg): string => '[' . $msg . ']');

		$e = new RateResolutionException('internal debug string', 'employee_rate_missing');
		$msg = RateResolutionMessage::forException($e, $l);

		$this->assertStringNotContainsString('internal debug', $msg);
		$this->assertStringContainsString('employee hourly rate', $msg);
	}

	public function testUnknownCodeUsesGenericMessage(): void
	{
		$l = $this->createMock(IL10N::class);
		$l->method('t')->willReturnCallback(static fn (string $msg): string => $msg);

		$e = new RateResolutionException('leaked', 'unknown_future_code');
		$msg = RateResolutionMessage::forException($e, $l);

		$this->assertSame('Could not resolve hourly rate.', $msg);
	}
}
