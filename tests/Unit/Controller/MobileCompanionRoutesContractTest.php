<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;

/**
 * Cross-repo contract: official ProjectCheck companion (`mobile/projectcheck`)
 * calls only `/mobile/v1/*` (+ `/health`). Those URL paths must stay stable and
 * must resolve to MobileController — never to web TimeEntryController /
 * ProjectFileController (GitHub #8 underscored renames are web-only).
 *
 * Golden surface mirrors `mobile/projectcheck/src/api/client.ts` + health probe.
 *
 * @copyright Copyright (c) 2026, Software by Design GbR
 * @license AGPL-3.0-or-later
 */
final class MobileCompanionRoutesContractTest extends TestCase
{
	private static function appRoot(): string
	{
		return dirname(__DIR__, 3);
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
	 * Companion HTTP surface used by client.ts (path patterns as registered).
	 *
	 * @return list<array{verb: string, url: string, name: string}>
	 */
	public static function companionSurface(): array
	{
		return [
			['verb' => 'GET', 'url' => '/health', 'name' => 'health#check'],
			['verb' => 'GET', 'url' => '/mobile/v1/bootstrap', 'name' => 'mobile#bootstrap'],
			['verb' => 'GET', 'url' => '/mobile/v1/projects', 'name' => 'mobile#projects'],
			['verb' => 'GET', 'url' => '/mobile/v1/projects/{id}/resolve-hourly-rate', 'name' => 'mobile#resolveHourlyRate'],
			['verb' => 'GET', 'url' => '/mobile/v1/time-entries', 'name' => 'mobile#timeEntries'],
			['verb' => 'POST', 'url' => '/mobile/v1/time-entries', 'name' => 'mobile#createTimeEntry'],
			['verb' => 'PUT', 'url' => '/mobile/v1/time-entries/{id}', 'name' => 'mobile#updateTimeEntry'],
			['verb' => 'DELETE', 'url' => '/mobile/v1/time-entries/{id}', 'name' => 'mobile#deleteTimeEntry'],
			['verb' => 'GET', 'url' => '/mobile/v1/settlement/entries', 'name' => 'mobile#settlementEntries'],
			['verb' => 'POST', 'url' => '/mobile/v1/time-entries/{id}/billing', 'name' => 'mobile#changeEntryBilling'],
			['verb' => 'POST', 'url' => '/mobile/v1/projects/{id}/settlement/preview', 'name' => 'mobile#projectSettlementPreview'],
			['verb' => 'POST', 'url' => '/mobile/v1/projects/{id}/settlement/apply', 'name' => 'mobile#projectSettlementApply'],
		];
	}

	public function testCompanionSurfaceIsRegisteredExactly(): void
	{
		$byKey = [];
		foreach (self::routes() as $route) {
			$key = ($route['verb'] ?? '') . ' ' . ($route['url'] ?? '');
			$byKey[$key] = $route;
		}

		foreach (self::companionSurface() as $expected) {
			$key = $expected['verb'] . ' ' . $expected['url'];
			self::assertArrayHasKey(
				$key,
				$byKey,
				"Companion endpoint missing from routes.php: {$key}"
			);
			self::assertSame(
				$expected['name'],
				$byKey[$key]['name'],
				"Companion route name drift for {$key}"
			);
		}
	}

	public function testCompanionApiRoutesUseMobileControllerOnly(): void
	{
		foreach (self::routes() as $route) {
			$url = (string) ($route['url'] ?? '');
			if (!str_starts_with($url, '/mobile/v1')) {
				continue;
			}
			$name = (string) ($route['name'] ?? '');
			[$segment] = explode('#', $name, 2);
			self::assertSame(
				'mobile',
				$segment,
				"Companion URL {$url} must use mobile#… (got '{$name}'). "
				. 'Web time_entry# / project_file# must never own /mobile/v1.'
			);
			self::assertSame(
				'MobileController',
				RoutesControllerNamingContractTest::buildControllerName($segment)
			);
		}
	}

	public function testWebTimeEntryAndFileRoutesDoNotStealCompanionPaths(): void
	{
		$companionUrls = [];
		foreach (self::companionSurface() as $row) {
			if (str_starts_with($row['url'], '/mobile/v1')) {
				$companionUrls[$row['url']] = true;
			}
		}

		foreach (self::routes() as $route) {
			$name = (string) ($route['name'] ?? '');
			$url = (string) ($route['url'] ?? '');
			[$segment] = explode('#', $name, 2);
			if (in_array($segment, ['time_entry', 'project_file', 'project_member'], true)) {
				self::assertArrayNotHasKey(
					$url,
					$companionUrls,
					"Web controller '{$segment}' must not register companion URL {$url}"
				);
				self::assertStringStartsNotWith(
					'/mobile/v1',
					$url,
					"Web controller '{$segment}' leaked onto mobile API path {$url}"
				);
			}
		}
	}

	public function testMobileControllerClassIsLoadable(): void
	{
		self::assertTrue(
			class_exists(\OCA\ProjectCheck\Controller\MobileController::class),
			'MobileController must autoload for companion dispatch'
		);
		self::assertFileExists(self::appRoot() . '/lib/Controller/MobileController.php');
	}

	public function testCompanionClientBaseFixtureLocksMobileV1Prefix(): void
	{
		$fixturePath = self::appRoot() . '/tests/fixtures/companion-api-client-base.json';
		self::assertFileExists($fixturePath);
		$fixture = json_decode((string) file_get_contents($fixturePath), true, 512, JSON_THROW_ON_ERROR);
		self::assertSame('/mobile/v1', $fixture['base'] ?? null);
		self::assertSame('/health', $fixture['healthPath'] ?? null);
		self::assertIsArray($fixture['forbiddenBases'] ?? null);
		self::assertContains('/apps/projectcheck/mobile/v1', $fixture['forbiddenBases']);

		$urls = array_column(self::companionSurface(), 'url');
		self::assertContains($fixture['healthPath'], $urls);
		foreach ($fixture['relativePaths'] as $rel) {
			self::assertIsString($rel);
			self::assertContains(
				$fixture['base'] . $rel,
				$urls,
				"Fixture relative path {$rel} must appear on companionSurface() as {$fixture['base']}{$rel}"
			);
		}

		// Host monorepo only: when mobile/ is visible, enforce live client.ts BASE.
		$clientPath = dirname(self::appRoot(), 3) . '/mobile/projectcheck/src/api/client.ts';
		if (!is_file($clientPath)) {
			return;
		}
		$source = (string) file_get_contents($clientPath);
		self::assertStringContainsString("const BASE = '" . $fixture['base'] . "';", $source);
		foreach ($fixture['forbiddenBases'] as $forbidden) {
			self::assertStringNotContainsString("const BASE = '" . $forbidden . "';", $source);
		}
		self::assertStringNotContainsString('projectcheck.time_entry', $source);
		self::assertStringNotContainsString('projectcheck.project_file', $source);
		self::assertStringNotContainsString('projectcheck.timeentry', $source);
		self::assertStringNotContainsString('projectcheck.projectfile', $source);
	}

	public function testHealthProbeAdvertisesMobileApiForCompanionLogin(): void
	{
		$health = (string) file_get_contents(self::appRoot() . '/lib/Controller/HealthController.php');
		self::assertStringContainsString("'mobileApi' => true", $health);
		$found = false;
		foreach (self::routes() as $route) {
			if (($route['name'] ?? '') === 'health#check' && ($route['url'] ?? '') === '/health') {
				$found = true;
				self::assertSame('GET', $route['verb'] ?? null);
			}
		}
		self::assertTrue($found, 'health#check /health must remain for companion reachability');
	}
}
