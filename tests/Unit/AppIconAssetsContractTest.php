<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class AppIconAssetsContractTest extends TestCase
{
	private string $imgDir;
	private string $root;

	protected function setUp(): void
	{
		parent::setUp();
		$this->root = dirname(__DIR__, 2);
		$this->imgDir = $this->root . '/img';
	}

	public function testRequiredIconFilesExist(): void
	{
		foreach (['app.svg', 'app-dark.svg', 'app-dashboard.svg'] as $file) {
			$path = $this->imgDir . '/' . $file;
			$this->assertFileExists($path, $file);
			$this->assertGreaterThan(200, (int)filesize($path), $file . ' must not be a stub');
		}
	}

	public function testHeaderIconIsWhiteForNcInvertFilter(): void
	{
		$svg = (string)file_get_contents($this->imgDir . '/app.svg');
		$this->assertStringContainsString('fill="#ffffff"', $svg);
		$this->assertStringNotContainsString('currentColor', $svg);
	}

	public function testDarkAndDashboardIconsHaveNoWhiteFill(): void
	{
		foreach (['app-dark.svg', 'app-dashboard.svg'] as $file) {
			$svg = (string)file_get_contents($this->imgDir . '/' . $file);
			$this->assertStringNotContainsString('fill="#ffffff"', $svg, $file);
			$this->assertStringContainsString('fill="#000000"', $svg, $file);
			$this->assertStringContainsString('ProjectCheck', $svg);
		}
	}

	public function testAppIconServiceWiredForDashboardAndNav(): void
	{
		$widget = (string)file_get_contents($this->root . '/lib/Dashboard/ProjectWidget.php');
		$this->assertStringContainsString('AppIconService', $widget);
		$this->assertStringContainsString('absoluteSurfaceIconUrl', $widget);

		$app = (string)file_get_contents($this->root . '/lib/AppInfo/Application.php');
		$this->assertStringContainsString('AppIconService', $app);
		$this->assertStringContainsString('headerIconPath()', $app);
		$this->assertStringNotContainsString(
			"imagePath(self::APP_ID, 'app.svg')",
			$app,
			'Navigation must use cache-busted header icon',
		);
	}

	public function testInfoXmlVersionMatchesAppinfoVersionFile(): void
	{
		$info = (string)file_get_contents($this->root . '/appinfo/info.xml');
		$ver = trim((string)file_get_contents($this->root . '/appinfo/version'));
		$this->assertSame('2.0.99', $ver);
		$this->assertMatchesRegularExpression('/<version>\s*' . preg_quote($ver, '/') . '\s*<\/version>/', $info);
	}
}
