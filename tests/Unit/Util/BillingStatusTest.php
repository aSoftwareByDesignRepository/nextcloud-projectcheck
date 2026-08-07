<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Util;

use OCA\ProjectCheck\Util\BillingStatus;
use PHPUnit\Framework\TestCase;

class BillingStatusTest extends TestCase
{
	public function testValidStatuses(): void
	{
		foreach (BillingStatus::ALL as $status) {
			$this->assertTrue(BillingStatus::isValid($status));
		}
		$this->assertFalse(BillingStatus::isValid('paid_out'));
		$this->assertFalse(BillingStatus::isValid(''));
	}

	public function testNormalizeFallsBackToOpen(): void
	{
		$this->assertSame(BillingStatus::OPEN, BillingStatus::normalize(null));
		$this->assertSame(BillingStatus::OPEN, BillingStatus::normalize(''));
		$this->assertSame(BillingStatus::OPEN, BillingStatus::normalize('bogus'));
		$this->assertSame(BillingStatus::INVOICED, BillingStatus::normalize(' Invoiced '));
	}

	/**
	 * Spec §6.1 transition matrix — including the intentional absence of open→paid.
	 *
	 * @dataProvider transitionProvider
	 */
	public function testTransitionMatrix(string $from, string $to, bool $allowed): void
	{
		$this->assertSame($allowed, BillingStatus::isTransitionAllowed($from, $to));
	}

	/**
	 * @return list<array{0: string, 1: string, 2: bool}>
	 */
	public static function transitionProvider(): array
	{
		return [
			['open', 'invoiced', true],
			['open', 'excluded', true],
			['open', 'paid', false],
			['open', 'open', false],
			['invoiced', 'paid', true],
			['invoiced', 'open', true],
			['invoiced', 'excluded', false],
			['paid', 'invoiced', true],
			['paid', 'open', false],
			['paid', 'excluded', false],
			['excluded', 'open', true],
			['excluded', 'invoiced', false],
			['excluded', 'paid', false],
		];
	}

	public function testContentLocking(): void
	{
		$this->assertTrue(BillingStatus::locksContent(BillingStatus::INVOICED));
		$this->assertTrue(BillingStatus::locksContent(BillingStatus::PAID));
		$this->assertFalse(BillingStatus::locksContent(BillingStatus::OPEN));
		$this->assertFalse(BillingStatus::locksContent(BillingStatus::EXCLUDED));
	}

	public function testOutstanding(): void
	{
		$this->assertTrue(BillingStatus::isOutstanding(BillingStatus::OPEN));
		$this->assertTrue(BillingStatus::isOutstanding(BillingStatus::INVOICED));
		$this->assertFalse(BillingStatus::isOutstanding(BillingStatus::PAID));
		$this->assertFalse(BillingStatus::isOutstanding(BillingStatus::EXCLUDED));
	}

	public function testInitialForProjectType(): void
	{
		$this->assertSame(BillingStatus::OPEN, BillingStatus::initialForProjectType('client'));
		$this->assertSame(BillingStatus::OPEN, BillingStatus::initialForProjectType('sales'));
		$this->assertSame(BillingStatus::EXCLUDED, BillingStatus::initialForProjectType('admin'));
		$this->assertSame(BillingStatus::EXCLUDED, BillingStatus::initialForProjectType('meeting'));
		$this->assertSame(BillingStatus::EXCLUDED, BillingStatus::initialForProjectType('internal'));
		$this->assertSame(BillingStatus::EXCLUDED, BillingStatus::initialForProjectType('training'));
	}
}
