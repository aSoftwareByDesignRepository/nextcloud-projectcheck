<?php

declare(strict_types=1);

/**
 * Static contract: theme-first CSS — no shell width locks, no wrong NC dark selectors.
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

namespace OCA\ProjectCheck\Tests\Unit\Css;

use PHPUnit\Framework\TestCase;

class ThemeComplianceContractTest extends TestCase
{
	private static function appRoot(): string
	{
		return dirname(__DIR__, 3);
	}

	private static function read(string $relative): string
	{
		$path = self::appRoot() . '/' . $relative;
		self::assertFileExists($path);
		$css = file_get_contents($path);
		self::assertIsString($css);
		return $css;
	}

	public function testShellDoesNotLockMaxWidthTo1400(): void
	{
		$css = self::read('css/projects.css');
		self::assertDoesNotMatchRegularExpression(
			'/max-width\s*:\s*min\(\s*1400px/i',
			$css,
			'Shell must not lock max-width to 1400px (design-system + e2e contract)',
		);
		self::assertDoesNotMatchRegularExpression(
			'/max-width\s*:\s*1400px\s*!important/i',
			$css,
			'Shell must not use max-width: 1400px !important',
		);
	}

	public function testNoLegacyDataThemeDarkAttributeSelectors(): void
	{
		$files = [
			'css/common/components.css',
			'css/common/accessibility.css',
			'css/common/typography.css',
			'css/common/base.css',
		];
		foreach ($files as $file) {
			$css = self::read($file);
			self::assertStringNotContainsString(
				'[data-theme="dark"]',
				$css,
				"{$file} must use Nextcloud [data-theme-dark] / body[data-theme-dark], not [data-theme=\"dark\"]",
			);
		}
	}

	public function testBaseCssDoesNotInventDarkHexPalette(): void
	{
		$css = self::read('css/common/base.css');
		self::assertStringNotContainsString(
			'--color-main-background: #1a1a1a',
			$css,
			'base.css must not invent dark hex palette under prefers-color-scheme',
		);
		self::assertDoesNotMatchRegularExpression(
			'/@media\s*\(\s*prefers-contrast:\s*high\s*\)\s*\{\s*\*\s*\{[^}]*border-color:\s*currentColor\s*!important/s',
			$css,
			'base.css must not blanket border-color: currentColor !important under prefers-contrast',
		);
	}

	public function testTokensMapToNextcloudColorVariables(): void
	{
		$css = self::read('css/common/tokens.css');
		self::assertStringContainsString('--pc-text: var(--color-main-text)', $css);
		self::assertStringContainsString('--pc-muted: var(--color-text-maxcontrast)', $css);
		self::assertStringContainsString('--pc-tint-info: color-mix(in srgb, var(--color-primary-element)', $css);
		self::assertStringContainsString('var(--color-main-background)', $css);
		self::assertStringContainsString('--pc-touch-min: 44px', $css);
	}

	public function testLegacyNavActiveSolidPrimaryPoisonRemoved(): void
	{
		$css = self::read('css/projects.css');
		self::assertDoesNotMatchRegularExpression(
			'/#app-navigation\s+ul:not\(\s*\.nav-menu\s*\)\s+li\.active\s*\{/s',
			$css,
			'Legacy solid-primary active rules must not target pc-nav lists (white-on-tint WCAG failure)',
		);
		self::assertDoesNotMatchRegularExpression(
			'/#app-navigation\s+ul:not\(\s*\.nav-menu\s*\)[^{]*li\.active\s+a\s*\{[^}]*color:\s*var\(--color-primary-text\)/s',
			$css,
			'Must not force --color-primary-text onto #app-navigation non-nav-menu links',
		);
	}

	public function testPcNavActiveUsesMainTextOnTint(): void
	{
		$nav = self::read('css/navigation.css');
		self::assertStringContainsString('background: var(--pc-tint-info)', $nav);
		self::assertMatchesRegularExpression(
			'/\.pc-nav__sublink\[aria-current=[\'"]page[\'"]\][^}]*color:\s*var\(--color-main-text\)/s',
			$nav,
			'Active settings sublink must use main-text on tint (not primary-text)',
		);
		self::assertMatchesRegularExpression(
			'/#app-navigation\.pc-nav\s+\.pc-nav__item\.is-active\s*>\s*\.pc-nav__link[^}]*color:\s*var\(--color-main-text\)/s',
			$nav,
			'Active parent nav link must use main-text on tint',
		);
	}
}
