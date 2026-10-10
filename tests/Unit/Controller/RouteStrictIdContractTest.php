<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;

/**
 * Strict-id contract: every route placeholder that names a DB integer id must
 * carry a `\d+` requirement in appinfo/routes.php.
 *
 * Nextcloud's dispatcher hands untyped route values to the controller where
 * is_numeric()+(int) silently truncates "2e3", "2.9", " 5", "0x1c"-style junk
 * into live row ids — a mutation oracle (budgetcheck live 4181.0 incident).
 * String params (userId, uid, section, ...) intentionally keep the default
 * [^/]+ match — NC uids/gids are arbitrary strings and must never be pinned.
 *
 * Two complementary checks, matching farm gate check-route-id-strictness.py:
 *  1. name-based — every id-shaped placeholder ({id}, {projectId}, {fileId},
 *     {customerId}, ...) is pinned, regardless of the declared PHP type
 *     (covers untyped `$id` signatures like CustomerController::show).
 *  2. type-based — every controller method parameter declared `int` that is
 *     bound to a route placeholder is pinned.
 *
 * @copyright Copyright (c) 2026, Software by Design GbR
 * @license AGPL-3.0-or-later
 */
final class RouteStrictIdContractTest extends TestCase
{
	private const ID_PARAM = '/^id$|^ids$|Id$|Ids$|_id$|_ids$/';
	private const STRING_ID = '/uid$|gid$|uuid$|token$|key$|userid$|groupid$/i';
	private const STRICT = '/^\^?\\\\d\+\$?$/';

	private static function appRoot(): string
	{
		return dirname(__DIR__, 3);
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	private static function routes(): array
	{
		$config = require self::appRoot() . '/appinfo/routes.php';
		self::assertIsArray($config['routes'] ?? null);
		return $config['routes'];
	}

	/**
	 * Map a route name prefix to its controller file using Nextcloud's exact
	 * naming rule: underScoreToCamelCase(ucfirst($prefix)) . 'Controller'.
	 * ('project_file#list' → lib/Controller/ProjectFileController.php)
	 */
	private static function controllerSource(string $prefix): string
	{
		$camel = (string) preg_replace_callback(
			'/_[a-z]?/',
			static fn (array $m): string => strtoupper(ltrim($m[0], '_')),
			ucfirst($prefix)
		);
		$file = self::appRoot() . '/lib/Controller/' . $camel . 'Controller.php';
		self::assertFileExists($file, 'route prefix ' . $prefix . ' must resolve to a controller file');
		return (string) file_get_contents($file);
	}

	/**
	 * @return array<string, string> param name → declared scalar type
	 */
	private static function paramTypesOfMethod(string $source, string $method): array
	{
		if (!preg_match('/function\s+' . preg_quote($method, '/') . '\s*\(([^)]*)\)/s', $source, $m)) {
			self::fail("controller method {$method} not found");
		}
		$types = [];
		foreach (explode(',', $m[1]) as $param) {
			if (preg_match('/\b(int|string|float|bool)\s+\$(\w+)/', $param, $pm)) {
				$types[$pm[2]] = $pm[1];
			}
		}
		return $types;
	}

	private static function requirement(array $route, string $param): ?string
	{
		$req = $route['requirements'][$param] ?? null;
		return is_string($req) ? $req : null;
	}

	public function testEveryIdNamedRouteParamHasDigitRequirement(): void
	{
		$violations = [];
		$checked = 0;
		foreach (self::routes() as $route) {
			$name = (string) ($route['name'] ?? '');
			$url = (string) ($route['url'] ?? '');
			if (!str_contains($name, '#') || !preg_match_all('/\{(\w+)\}/', $url, $pm) || $pm[1] === []) {
				continue;
			}
			foreach ($pm[1] as $param) {
				if (!preg_match(self::ID_PARAM, $param) || preg_match(self::STRING_ID, $param)) {
					continue;
				}
				$checked++;
				$req = self::requirement($route, $param);
				if ($req === null || !preg_match(self::STRICT, $req)) {
					$violations[] = "{$name} {$url}: id param {{$param}} lacks \\d+ requirement (got "
						. var_export($req, true) . ')';
				}
			}
		}
		self::assertGreaterThan(40, $checked, 'route inventory must actually cover id params');
		self::assertSame([], $violations, implode("\n", $violations));
	}

	public function testEveryIntTypedControllerParamHasDigitRequirement(): void
	{
		$violations = [];
		$checked = 0;
		$controllerCache = [];
		foreach (self::routes() as $route) {
			$name = (string) ($route['name'] ?? '');
			$url = (string) ($route['url'] ?? '');
			if (!str_contains($name, '#') || !preg_match_all('/\{(\w+)\}/', $url, $pm) || $pm[1] === []) {
				continue;
			}
			[$prefix, $method] = explode('#', $name, 2);
			$controllerCache[$prefix] ??= self::controllerSource($prefix);
			$paramTypes = self::paramTypesOfMethod($controllerCache[$prefix], $method);
			foreach ($pm[1] as $param) {
				if (($paramTypes[$param] ?? null) !== 'int') {
					continue;
				}
				$checked++;
				$req = self::requirement($route, $param);
				if ($req === null || !preg_match(self::STRICT, $req)) {
					$violations[] = "{$name} {$url}: int \${$param} lacks \\d+ requirement (got "
						. var_export($req, true) . ')';
				}
			}
		}
		self::assertGreaterThan(30, $checked, 'route inventory must actually cover int params');
		self::assertSame([], $violations, implode("\n", $violations));
	}

	public function testStringIdentityParamsAreNotDigitPinned(): void
	{
		// Guard the other direction: pinning \d+ on a uid/userId param would
		// break the route — NC identities are arbitrary strings.
		$violations = [];
		foreach (self::routes() as $route) {
			$name = (string) ($route['name'] ?? '');
			$url = (string) ($route['url'] ?? '');
			if (!preg_match_all('/\{(\w+)\}/', $url, $pm) || $pm[1] === []) {
				continue;
			}
			foreach ($pm[1] as $param) {
				if (!preg_match(self::STRING_ID, $param)) {
					continue;
				}
				$req = self::requirement($route, $param);
				if ($req !== null && preg_match(self::STRICT, $req)) {
					$violations[] = "{$name} {$url}: string identity param {{$param}} wrongly pinned \\d+";
				}
			}
		}
		self::assertSame([], $violations, implode("\n", $violations));
	}
}
