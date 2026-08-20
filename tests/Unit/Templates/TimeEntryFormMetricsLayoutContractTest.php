<?php

declare(strict_types=1);

/**
 * Contract: time-entry metrics strip is Date | Hours | Rate | Total — aligned, no dead grid track.
 *
 * @copyright Copyright (c) 2026, Software by Design
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Tests\Unit\Templates;

use PHPUnit\Framework\TestCase;

class TimeEntryFormMetricsLayoutContractTest extends TestCase
{
	private static function read(string $relative): string
	{
		$path = dirname(__DIR__, 3) . '/' . $relative;
		self::assertFileExists($path);
		$content = file_get_contents($path);
		self::assertIsString($content);
		return $content;
	}

	public function testMetricsRowUsesFourFormGroupsNotCollapsedDetails(): void
	{
		$tpl = self::read('templates/time-entry-form.php');

		self::assertStringContainsString('form-row--metrics', $tpl);
		self::assertStringNotContainsString('form-row--split-4', $tpl);
		self::assertStringNotContainsString('id="pc-te-pricing-summary"', $tpl);

		self::assertSame(1, preg_match_all('/form-group--date/', $tpl));
		self::assertSame(1, preg_match_all('/form-group--hours/', $tpl));
		self::assertSame(1, preg_match_all('/form-group--rate/', $tpl));
		self::assertSame(1, preg_match_all('/form-group--total/', $tpl));

		self::assertStringContainsString('id="date"', $tpl);
		self::assertStringContainsString('id="hours"', $tpl);
		self::assertStringContainsString('id="hourly_rate"', $tpl);
		self::assertStringContainsString('id="total_cost"', $tpl);
		self::assertStringContainsString('id="date-hint"', $tpl);
		self::assertStringContainsString('form-row--metrics__date-hint', $tpl);
	}

	public function testHourlyRateRemainsServerAuthoritativeReadonly(): void
	{
		$tpl = self::read('templates/time-entry-form.php');

		self::assertMatchesRegularExpression(
			'/<input[^>]*id="hourly_rate"[^>]*\breadonly\b/s',
			$tpl
		);
		self::assertMatchesRegularExpression(
			'/<input[^>]*name="hourly_rate"[^>]*\breadonly\b/s',
			$tpl
		);
		self::assertStringContainsString('id="hourly_rate-hint"', $tpl);
		self::assertStringContainsString(
			'Rate is set by the server from the project and work date. It cannot be edited.',
			$tpl
		);
		// Preview total must not post as a client-trusted amount
		self::assertDoesNotMatchRegularExpression(
			'/<input[^>]*id="total_cost"[^>]*\bname=/',
			$tpl
		);
	}

	public function testCssMetricsGridMatchesFourChildren(): void
	{
		$css = self::read('css/time-entry-form.css');

		self::assertStringContainsString('.form-row--metrics', $css);
		self::assertStringNotContainsString('form-row--split-4', $css);
		self::assertStringContainsString('minmax(0, 1.2fr) minmax(0, 0.7fr) minmax(0, 1fr) minmax(0, 1fr)', $css);
		self::assertStringContainsString('form-row--metrics__date-hint', $css);
		self::assertStringContainsString('min-height: var(--pc-touch-min, 44px)', $css);
	}

	public function testJsStillResolvesRateFromServer(): void
	{
		$js = self::read('js/time-entry-form.js');

		self::assertStringContainsString('resolveRateFromServer', $js);
		self::assertStringContainsString('resolve-hourly-rate', $js);
		self::assertStringContainsString("getElementById('hourly_rate')", $js);
		self::assertStringContainsString('rateInput.readOnly = true', $js);
		self::assertStringContainsString('calculateTotalCost', $js);
	}
}
