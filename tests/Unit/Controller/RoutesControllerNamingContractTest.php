<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;

/**
 * Guarantees every route controller segment resolves to an on-disk Controller class
 * using Nextcloud's exact naming rule (underScoreToCamelCase + ucfirst + "Controller").
 *
 * Regression guard for GitHub #8: `projectfile#…` → ProjectfileController (missing file)
 * while the real class is ProjectFileController. Linux PSR-4 is case-sensitive.
 *
 * @copyright Copyright (c) 2026, Software by Design GbR
 * @license AGPL-3.0-or-later
 */
final class RoutesControllerNamingContractTest extends TestCase
{
	private const APP_ID = 'projectcheck';
	private const CONTROLLER_NS = 'OCA\\ProjectCheck\\Controller\\';

	private static function appRoot(): string
	{
		return dirname(__DIR__, 3);
	}

	/**
	 * Mirrors OC\AppFramework\Routing\RouteConfig::underScoreToCamelCase().
	 */
	public static function underScoreToCamelCase(string $str): string
	{
		$pattern = '/_[a-z]?/';
		return (string) preg_replace_callback(
			$pattern,
			static function (array $matches): string {
				return strtoupper(ltrim($matches[0], '_'));
			},
			$str
		);
	}

	/**
	 * Mirrors OC\AppFramework\Routing\RouteConfig::buildControllerName().
	 */
	public static function buildControllerName(string $controller): string
	{
		return self::underScoreToCamelCase(ucfirst($controller)) . 'Controller';
	}

	/**
	 * @return list<array{name: string, url: string, verb: string}>
	 */
	private static function routes(): array
	{
		$config = require self::appRoot() . '/appinfo/routes.php';
		self::assertIsArray($config['routes'] ?? null);
		/** @var list<array{name: string, url: string, verb: string}> $routes */
		$routes = $config['routes'];
		return $routes;
	}

	/**
	 * @return list<string>
	 */
	private static function controllerSegments(): array
	{
		$segments = [];
		foreach (self::routes() as $route) {
			$name = (string) ($route['name'] ?? '');
			self::assertMatchesRegularExpression(
				'/^[a-z][a-z0-9_]*#[a-zA-Z][a-zA-Z0-9]*$/',
				$name,
				"Route name '{$name}' must be controller#action"
			);
			[$controller] = explode('#', $name, 2);
			$segments[$controller] = $controller;
		}
		ksort($segments);
		return array_values($segments);
	}

	public function testEveryRouteControllerClassFileExistsWithExactCase(): void
	{
		$controllerDir = self::appRoot() . '/lib/Controller';
		foreach (self::controllerSegments() as $segment) {
			$classBase = self::buildControllerName($segment);
			$path = $controllerDir . '/' . $classBase . '.php';
			self::assertFileExists(
				$path,
				"Route '{$segment}#…' resolves to {$classBase}, but {$path} is missing. "
				. 'Use underscores in multi-word controller segments (e.g. project_file → ProjectFileController).'
			);

			$source = (string) file_get_contents($path);
			self::assertMatchesRegularExpression(
				'/\bclass\s+' . preg_quote($classBase, '/') . '\b/',
				$source,
				"{$path} must declare class {$classBase}"
			);
		}
	}

	public function testKnownMultiWordControllersUseUnderscoreSegments(): void
	{
		$segments = self::controllerSegments();
		$required = [
			'project_file' => 'ProjectFileController',
			'time_entry' => 'TimeEntryController',
			'project_member' => 'ProjectMemberController',
			'app_config' => 'AppConfigController',
			'service_worker' => 'ServiceWorkerController',
		];
		foreach ($required as $segment => $classBase) {
			self::assertContains(
				$segment,
				$segments,
				"Expected underscored route controller '{$segment}' (→ {$classBase})"
			);
			self::assertSame(
				$classBase,
				self::buildControllerName($segment),
				"Naming algorithm drift for '{$segment}'"
			);
		}

		$forbidden = ['projectfile', 'timeentry', 'projectmember', 'appconfig', 'serviceworker'];
		foreach ($forbidden as $bad) {
			self::assertNotContains(
				$bad,
				$segments,
				"Forbidden compact controller segment '{$bad}' — Nextcloud would look for "
				. self::buildControllerName($bad) . ' which does not exist on Linux'
			);
		}
	}

	public function testLinkToRouteCallSitesMatchRegisteredRouteNames(): void
	{
		$registered = [];
		foreach (self::routes() as $route) {
			[$controller, $action] = explode('#', (string) $route['name'], 2);
			$registered[self::APP_ID . '.' . $controller . '.' . $action] = true;
		}

		$roots = [
			self::appRoot() . '/lib',
			self::appRoot() . '/templates',
		];
		$pattern = "/linkToRoute(?:Absolute)?\\(\\s*'(" . preg_quote(self::APP_ID, '/') . "\\.[a-z0-9_]+\\.[a-zA-Z0-9]+)'/";
		$found = [];
		foreach ($roots as $root) {
			$iterator = new \RecursiveIteratorIterator(
				new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)
			);
			/** @var \SplFileInfo $file */
			foreach ($iterator as $file) {
				if (!$file->isFile() || $file->getExtension() !== 'php') {
					continue;
				}
				$contents = (string) file_get_contents($file->getPathname());
				if (preg_match_all($pattern, $contents, $matches) !== false) {
					foreach ($matches[1] as $routeName) {
						$found[$routeName] = $file->getPathname();
					}
				}
			}
		}

		self::assertNotEmpty($found, 'Expected at least one linkToRoute call site');
		foreach ($found as $routeName => $path) {
			self::assertArrayHasKey(
				$routeName,
				$registered,
				"{$path} links to '{$routeName}' which is not registered in appinfo/routes.php"
			);
		}
	}

	public function testBuildControllerNameMatchesNextcloudForIssue8Repro(): void
	{
		// Document the exact failure mode reported in GitHub #8.
		self::assertSame('ProjectfileController', self::buildControllerName('projectfile'));
		self::assertSame('ProjectFileController', self::buildControllerName('project_file'));
		self::assertSame('TimeentryController', self::buildControllerName('timeentry'));
		self::assertSame('TimeEntryController', self::buildControllerName('time_entry'));
		self::assertSame('ProjectmemberController', self::buildControllerName('projectmember'));
		self::assertSame('ProjectMemberController', self::buildControllerName('project_member'));
		self::assertFalse(
			is_file(self::appRoot() . '/lib/Controller/ProjectfileController.php'),
			'Broken casing must not exist as a real file (would hide the bug on CI)'
		);
		self::assertTrue(
			is_file(self::appRoot() . '/lib/Controller/ProjectFileController.php')
		);
	}

	public function testControllerFqcnIsLoadableViaComposerAutoload(): void
	{
		foreach (self::controllerSegments() as $segment) {
			$fqcn = self::CONTROLLER_NS . self::buildControllerName($segment);
			self::assertTrue(
				class_exists($fqcn),
				"PSR-4 cannot load {$fqcn} — route segment '{$segment}' is misnamed"
			);
		}
	}
}
