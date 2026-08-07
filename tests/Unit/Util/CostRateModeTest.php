<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Util;

use OCA\ProjectCheck\Util\CostRateMode;
use PHPUnit\Framework\TestCase;

class CostRateModeTest extends TestCase
{
	public function testInvalidModeFallsBackToProject(): void
	{
		$this->assertSame(CostRateMode::PROJECT, CostRateMode::normalize('bogus'));
		$this->assertSame(CostRateMode::PROJECT, CostRateMode::normalize(null));
	}

	public function testValidModes(): void
	{
		$this->assertTrue(CostRateMode::isValid(CostRateMode::EMPLOYEE));
		$this->assertTrue(CostRateMode::isValid(CostRateMode::PROJECT_MEMBER));
		$this->assertFalse(CostRateMode::isValid('payroll'));
	}
}
