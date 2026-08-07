<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Integration;

use OCA\ProjectCheck\AppInfo\Application;
use OCA\ProjectCheck\Tests\Unit\Controller\RoutesControllerNamingContractTest;
use Test\TestCase;

/**
 * Boots the real app container and resolves every controller class implied by
 * appinfo/routes.php — the same lookup Nextcloud performs on HTTP dispatch.
 *
 * @group integration
 *
 * @copyright Copyright (c) 2026, Software by Design GbR
 * @license AGPL-3.0-or-later
 */
final class RouteControllerResolutionIntegrationTest extends TestCase
{
	protected function setUp(): void
	{
		if (!class_exists(\OC::class) || !isset(\OC::$server)) {
			$this->markTestSkipped('Nextcloud is not bootstrapped (set NEXTCLOUD_ROOT or run inside Docker).');
		}
	}

	private function appContainer()
	{
		return \OC::$server->getRegisteredAppContainer(Application::APP_ID);
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function routeControllers(): array
	{
		$appRoot = dirname(__DIR__, 2);
		$config = require $appRoot . '/appinfo/routes.php';
		$cases = [];
		foreach ($config['routes'] as $route) {
			$name = (string) ($route['name'] ?? '');
			[$segment] = explode('#', $name, 2);
			$classBase = RoutesControllerNamingContractTest::buildControllerName($segment);
			$fqcn = 'OCA\\ProjectCheck\\Controller\\' . $classBase;
			$cases[$segment] = [$segment, $fqcn];
		}
		ksort($cases);
		return $cases;
	}

	/** @dataProvider routeControllers */
	public function testRouteControllerResolvesFromAppContainer(string $segment, string $fqcn): void
	{
		$controller = $this->appContainer()->query($fqcn);
		$this->assertInstanceOf($fqcn, $controller, "Route segment '{$segment}' must resolve {$fqcn}");
	}

	/**
	 * Fresh-process reproduction of GitHub #8. PHP class names are case-insensitive
	 * *after* a correct load, so this must run in an isolated CLI process that has
	 * never loaded ProjectFileController.
	 */
	public function testCompactProjectfileNameFailsInFreshProcess(): void
	{
		$php = 'require "/var/www/html/lib/base.php";'
			. '$c = \\OC::$server->getRegisteredAppContainer("projectcheck");'
			. 'try {'
			. '  $c->query("OCA\\\\ProjectCheck\\\\Controller\\\\ProjectfileController");'
			. '  fwrite(STDOUT, "UNEXPECTED_OK\\n");'
			. '  exit(0);'
			. '} catch (\\Throwable $e) {'
			. '  fwrite(STDOUT, get_class($e) . "|" . $e->getMessage() . "\\n");'
			. '  exit(str_contains($e->getMessage(), "ProjectfileController") ? 2 : 1);'
			. '}';

		$cmd = 'php -r ' . escapeshellarg($php);
		$out = [];
		$code = 0;
		exec($cmd . ' 2>&1', $out, $code);
		$joined = implode("\n", $out);

		$this->assertSame(2, $code, "Expected QueryNotFoundException for ProjectfileController, got: {$joined}");
		$this->assertStringContainsString('ProjectfileController', $joined);
		$this->assertStringNotContainsString('UNEXPECTED_OK', $joined);
	}

	public function testProjectFileControllerResolvesInFreshProcess(): void
	{
		$php = 'require "/var/www/html/lib/base.php";'
			. '$c = \\OC::$server->getRegisteredAppContainer("projectcheck");'
			. '$ctrl = $c->query("OCA\\\\ProjectCheck\\\\Controller\\\\ProjectFileController");'
			. 'echo get_class($ctrl);';

		$cmd = 'php -r ' . escapeshellarg($php);
		$out = [];
		$code = 0;
		exec($cmd . ' 2>&1', $out, $code);
		$joined = implode("\n", $out);

		$this->assertSame(0, $code, $joined);
		$this->assertSame('OCA\\ProjectCheck\\Controller\\ProjectFileController', trim($joined));
	}
}
