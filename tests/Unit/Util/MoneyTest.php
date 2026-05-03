<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Util;

use OCA\ProjectCheck\Util\Money;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for {@see Money}.
 *
 * Audit reference: AUDIT-FINDINGS A5 - float arithmetic on money produced
 * rounding drift in totals/thresholds. Each test is a precision vector that
 * has historically caused IEEE-754 issues on naive `float * float` paths
 * (`0.1+0.2`, banker's-vs-arithmetic rounding, percentage near 100, etc.).
 */
class MoneyTest extends TestCase {
	public function testNormalizeAcceptsDifferentInputShapes(): void {
		$this->assertSame('0.0000', Money::normalize(null));
		$this->assertSame('0.0000', Money::normalize(''));
		$this->assertSame('0.0000', Money::normalize('not-a-number'));
		$this->assertSame('1.5000', Money::normalize('1.5'));
		$this->assertSame('1.5000', Money::normalize(1.5));
		$this->assertSame('1.5000', Money::normalize('1,5')); // German decimal
		$this->assertSame('1234.5600', Money::normalize('1234,56'));
	}

	public function testMulHandlesClassicFloatDriftCases(): void {
		// 0.1 + 0.2 famously becomes 0.30000000000000004 in IEEE-754.
		$this->assertSame('0.30', Money::add('0.1', '0.2', Money::MONEY_SCALE));
		// 0.1 * 3 likewise drifts.
		$this->assertSame('0.30', Money::mul('0.1', '3', Money::MONEY_SCALE));
		// 1.005 historically rounds to 1.00 with PHP's round() in some flag
		// combinations because the float 1.005 is actually 1.00499999...
		// Using normalize() with scale 2 exercises the same rounding path.
		$this->assertSame('1.01', Money::normalize('1.005', 2));
	}

	public function testHourlyRateMultiplicationIsExact(): void {
		// 7.5 hours * €52.34/h = 392.55 (and not 392.5499999...)
		$this->assertSame('392.55', Money::mul('7.5', '52.34'));
		// Larger contract: 1000 hours * €127.83 = 127830.00
		$this->assertSame('127830.00', Money::mul('1000', '127.83'));
	}

	public function testPercentageRoundsHalfAwayFromZero(): void {
		// Default percentage uses half-away-from-zero rounding at 2dp:
		// 999.99 / 1000 * 100 = 99.999% -> rounds up to 100.00%.
		$this->assertSame('100.00', Money::percentage('999.99', '1000.00'));
		$this->assertSame('100.00', Money::percentage('1000', '1000'));
		$this->assertSame('110.00', Money::percentage('1100', '1000'));
	}

	public function testPercentageBoundedNeverCrosses100ByRounding(): void {
		// Below 100: must NEVER display 100.00 just because of rounding.
		$this->assertSame('99.99', Money::percentageBounded('999.99', '1000.00'));
		// Exactly 100%: stays 100.00.
		$this->assertSame('100.00', Money::percentageBounded('1000', '1000'));
		// Over budget: stays > 100.
		$this->assertSame('110.00', Money::percentageBounded('1100', '1000'));
		// Slightly over (100.001%) must NEVER round down to 100.00.
		$this->assertSame('100.01', Money::percentageBounded('100001', '100000'));
	}

	public function testDivideByZeroIsSafe(): void {
		$this->assertSame('0.00', Money::div('100', '0'));
		$this->assertSame('0.00', Money::percentage('100', '0'));
	}

	public function testCompareEdges(): void {
		$this->assertSame(1, Money::compare('100.01', '100.00'));
		$this->assertSame(-1, Money::compare('99.99', '100.00'));
		$this->assertSame(0, Money::compare('100.0000', '100.00'));
	}

	public function testAsFloatRoundsDeterministically(): void {
		// Half-away-from-zero
		$this->assertSame(2.51, Money::asFloat('2.505'));
		$this->assertSame(-2.51, Money::asFloat('-2.505'));
		$this->assertSame(0.10, Money::asFloat('0.099501'));
	}

	public function testSubKeepsPrecisionOnLargeAmounts(): void {
		// 1,000,000.00 - 0.01 must remain exact (no scientific notation drift).
		$this->assertSame('999999.99', Money::sub('1000000', '0.01'));
	}

	public function testNormalizeRejectsScientificNotationGracefully(): void {
		// Arbitrary input from JSON could be "1e2" - we must accept it.
		$this->assertSame('100.0000', Money::normalize('1e2'));
	}
}
