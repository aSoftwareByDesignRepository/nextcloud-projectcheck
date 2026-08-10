<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

/**
 * Locked pricing must be a readable summary — never a faded disabled radio grid.
 */
final class PricingModeCardsContractTest extends TestCase
{
	private static function read(): string
	{
		return (string) file_get_contents(
			dirname(__DIR__, 3) . '/templates/parts/pricing-mode-cards.php'
		);
	}

	public function testLockedBranchUsesSummaryNotDisabledRadios(): void
	{
		$tpl = self::read();
		self::assertStringContainsString('pc-pricing-locked', $tpl);
		self::assertStringContainsString('pc-pricing-summary', $tpl);
		self::assertStringContainsString('Current method', $tpl);
		self::assertStringContainsString('data-testid="pc-pricing-locked"', $tpl);
		self::assertStringContainsString('type="hidden" name="cost_rate_mode"', $tpl);
		// No disabled radio ternary — locked path must not paint disabled radios.
		self::assertStringNotContainsString("\$costRateModeLocked ? 'disabled'", $tpl);
	}

	public function testUnlockedBranchKeepsThreeModeRadios(): void
	{
		$tpl = self::read();
		self::assertStringContainsString('pc-pricing-cards', $tpl);
		self::assertSame(3, substr_count($tpl, 'type="radio"'));
		self::assertStringContainsString('value="<?php p(CostRateMode::PROJECT); ?>"', $tpl);
		self::assertStringContainsString('value="<?php p(CostRateMode::EMPLOYEE); ?>"', $tpl);
		self::assertStringContainsString('value="<?php p(CostRateMode::PROJECT_MEMBER); ?>"', $tpl);
	}
}
