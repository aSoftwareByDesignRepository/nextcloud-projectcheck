<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Dashboard;

use PHPUnit\Framework\TestCase;

final class DeskletStylesContractTest extends TestCase
{
	private string $root;

	protected function setUp(): void
	{
		parent::setUp();
		$this->root = dirname(__DIR__, 3);
	}

	public function testDeskletCssExistsWithAaTouchAndFocus(): void
	{
		$css = (string)file_get_contents($this->root . '/css/desklet-nextcloud.css');
		self::assertStringContainsString('#app-dashboard .panel:has(.panel--header img[src*="/projectcheck/"]', $css);
		self::assertStringContainsString('min-height: 44px', $css);
		self::assertStringContainsString(':focus-visible', $css);
		self::assertStringContainsString('app-dashboard.svg', $css);
		self::assertStringContainsString('background-invert-if-dark', $css);
		self::assertStringContainsString('prefers-reduced-motion', $css);
		self::assertStringContainsString('a.more', $css);
		self::assertStringContainsString('-webkit-line-clamp: 3', $css);
		self::assertStringContainsString('forced-colors: active', $css);
	}

	public function testProjectWidgetRegistersDeskletStylesAndV2Api(): void
	{
		$trait = (string)file_get_contents($this->root . '/lib/Dashboard/RegistersDeskletStylesTrait.php');
		self::assertStringContainsString("Util::addStyle(Application::APP_ID, 'desklet-nextcloud')", $trait);

		$src = (string)file_get_contents($this->root . '/lib/Dashboard/ProjectWidget.php');
		self::assertStringContainsString('RegistersDeskletStylesTrait', $src);
		self::assertMatchesRegularExpression(
			'/function load\(\): void\s*\{\s*\$this->registerDeskletStylesForWidget\(\);/s',
			$src,
		);
		self::assertStringContainsString('IAPIWidgetV2', $src);
		self::assertStringContainsString('IConditionalWidget', $src);
		self::assertStringContainsString('linkToRouteAbsolute', $src);
		self::assertStringContainsString('getProjectsByUser($userId, $limit)', $src);
		self::assertStringNotContainsString('icon-play', $src);
		self::assertDoesNotMatchRegularExpression(
			'/new WidgetButton\(\s*WidgetButton::TYPE_MORE,\s*\$this->l10n->t\(/s',
			$src,
			'WidgetButton args must be type, link, text — not type, text, link',
		);
	}
}
