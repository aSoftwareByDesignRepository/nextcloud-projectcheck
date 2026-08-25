<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

/**
 * Compact settlement progress (list cells) must not use overflowing stat cards.
 */
final class SettlementProgressContractTest extends TestCase
{
	private static function read(): string
	{
		return (string) file_get_contents(
			dirname(__DIR__, 3) . '/templates/parts/settlement-progress.php'
		);
	}

	public function testCompactUsesInlineLineNotStatCards(): void
	{
		$tpl = self::read();
		self::assertStringContainsString('pc-stl-progress__compact-line', $tpl);
		self::assertStringContainsString("\$progressVariant === 'compact'", $tpl);
		// Compact branch precedes stats; stats only in else (full).
		$compactPos = strpos($tpl, 'pc-stl-progress__compact-line');
		$statsPos = strpos($tpl, 'pc-stl-progress__stats');
		self::assertNotFalse($compactPos);
		self::assertNotFalse($statsPos);
		self::assertLessThan($statsPos, $compactPos);
		self::assertStringContainsString("%s%% paid", $tpl);
		self::assertStringContainsString("%s%% invoiced or paid", $tpl);
	}
}
