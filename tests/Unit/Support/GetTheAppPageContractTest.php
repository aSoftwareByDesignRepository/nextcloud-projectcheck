<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Support;

use OCA\ProjectCheck\Support\MobileAppLinks;
use PHPUnit\Framework\TestCase;

final class GetTheAppPageContractTest extends TestCase
{
	private string $root;

	protected function setUp(): void
	{
		parent::setUp();
		$this->root = dirname(__DIR__, 3);
	}

	public function testRouteAndControllerExist(): void
	{
		$routes = (string) file_get_contents($this->root . '/appinfo/routes.php');
		self::assertStringContainsString("page#getTheApp", $routes);
		self::assertStringContainsString("'/get-the-app'", $routes);

		$php = (string) file_get_contents($this->root . '/lib/Controller/PageController.php');
		self::assertStringContainsString('function getTheApp(', $php);
		self::assertStringContainsString("'get-the-app'", $php);
		self::assertStringContainsString('Get the App', $php);
		self::assertStringContainsString('projectcheck.page.getTheApp', $php);
		self::assertStringContainsString('MobileAppLinks', $php);
		self::assertStringContainsString("'playStore'", $php);
	}

	public function testNavIncludesGetTheAppForEveryone(): void
	{
		$nav = (string) file_get_contents($this->root . '/templates/common/navigation.php');
		self::assertStringContainsString("'id' => 'get-the-app'", $nav);
		self::assertStringContainsString("'icon' => 'smartphone'", $nav);
		self::assertStringContainsString("\$groups[] = [", $nav);
		$settingsPos = strpos($nav, "'id' => 'settings'");
		$getAppPos = strpos($nav, "'id' => 'get-the-app'");
		self::assertNotFalse($settingsPos);
		self::assertNotFalse($getAppPos);
		self::assertGreaterThan($settingsPos, $getAppPos);
	}

	public function testTemplateWiresPlayStoreSafely(): void
	{
		$tpl = (string) file_get_contents($this->root . '/templates/get-the-app.php');
		self::assertStringContainsString('pc-get-app__hero', $tpl);
		self::assertStringContainsString('pc-get-app__features', $tpl);
		self::assertStringContainsString('pc-get-app__actions', $tpl);
		self::assertStringContainsString('pc-get-app__play', $tpl);
		self::assertStringContainsString('pc-btn pc-btn--primary pc-get-app__play', $tpl);
		self::assertStringContainsString('rel="noopener noreferrer"', $tpl);
		self::assertStringContainsString('target="_blank"', $tpl);
		self::assertStringContainsString('MobileAppLinks::PLAY_STORE_URL', $tpl);
		self::assertStringContainsString("str_starts_with(\$playStore, 'https://play.google.com/')", $tpl);
	}

	public function testCssSeparatesStaticFeaturesFromActionButtons(): void
	{
		$css = (string) file_get_contents($this->root . '/css/get-the-app.css');
		self::assertMatchesRegularExpression(
			'/\.pc-get-app__hero[^{]*\{[^}]*linear-gradient/s',
			$css,
		);
		self::assertMatchesRegularExpression(
			'/\.pc-get-app__feature[^{]*\{[^}]*cursor:\s*default/s',
			$css,
		);
		self::assertMatchesRegularExpression(
			'/#app-content\.pc-app--get-the-app a\.pc-get-app__play[^{]*\{[^}]*background-color:\s*var\(--color-primary-element\)\s*!important/s',
			$css,
		);
		self::assertMatchesRegularExpression(
			'/\.pc-get-app__action[^{]*\{[^}]*cursor:\s*pointer/s',
			$css,
		);
		self::assertSame(MobileAppLinks::PLAY_STORE_PACKAGE_ID, 'de.softwarebydesign.projectcheck');
		self::assertTrue(MobileAppLinks::PLAY_LISTED);
	}
}
