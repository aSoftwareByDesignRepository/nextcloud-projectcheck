<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Controller;

use OCA\ProjectCheck\Service\SettingsSectionCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Cross-artifact drift protection for the split settings sub-pages.
 */
final class SettingsPagesContractTest extends TestCase
{
	private static function appRoot(): string
	{
		return dirname(__DIR__, 3);
	}

	private static function read(string $relative): string
	{
		$path = self::appRoot() . '/' . $relative;
		self::assertFileExists($path);
		return (string) file_get_contents($path);
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

	private static function routeByName(string $name): array
	{
		foreach (self::routes() as $route) {
			if (($route['name'] ?? '') === $name) {
				return $route;
			}
		}
		self::fail("Route '{$name}' is not registered");
	}

	public function testLegacySettingsRouteIsPreserved(): void
	{
		$route = self::routeByName('app_config#settingsIndex');
		self::assertSame('/settings', $route['url']);
		self::assertSame('GET', $route['verb']);
	}

	public function testSectionRouteRequirementMatchesCatalog(): void
	{
		$route = self::routeByName('app_config#settingsSection');
		self::assertSame('/settings/{section}', $route['url']);
		self::assertSame('GET', $route['verb']);
		self::assertSame(
			SettingsSectionCatalog::routeRequirement(),
			$route['requirements']['section'] ?? null,
			'Route allowlist drifted from SettingsSectionCatalog::routeRequirement()',
		);
	}

	public function testDispatcherMapCoversExactlyTheCatalogInOrder(): void
	{
		$dispatcher = self::read('templates/org-app-settings.php');
		self::assertSame(
			1,
			preg_match('/\$pcSettingsSectionFiles\s*=\s*\[(.*?)\];/s', $dispatcher, $m),
			'Dispatcher must declare the literal slug → file map',
		);
		preg_match_all("/'([a-z-]+)'\s*=>\s*'([a-z.-]+)'/", $m[1], $pairs, PREG_SET_ORDER);
		$map = [];
		foreach ($pairs as $pair) {
			$map[$pair[1]] = $pair[2];
		}
		self::assertSame(SettingsSectionCatalog::SECTIONS, array_keys($map), 'Dispatcher slugs drifted from the catalog');
		foreach ($map as $slug => $file) {
			self::assertSame($slug . '.php', $file, 'Dispatcher file names must mirror slugs (auditability)');
			self::assertFileExists(
				self::appRoot() . '/templates/parts/settings/' . $file,
				"Partial for section '{$slug}' is missing",
			);
		}
	}

	public function testDispatcherNormalizesUnknownSectionsAndNeverBuildsPathsFromInput(): void
	{
		$dispatcher = self::read('templates/org-app-settings.php');
		self::assertStringContainsString(
			"if (!isset(\$pcSettingsSectionFiles[\$pcRequestedSection])) {",
			$dispatcher,
			'Unknown sections must be rewritten before include',
		);
		self::assertStringContainsString(
			"\$pcRequestedSection = '" . SettingsSectionCatalog::DEFAULT_SECTION . "';",
			$dispatcher,
			'Unknown sections must fall back to the default section',
		);
		self::assertStringContainsString(
			". \$pcSettingsSectionFiles[\$pcRequestedSection];",
			$dispatcher,
			'Include must use the literal map after normalization',
		);
		self::assertStringNotContainsString(
			'$pcRequestedSection . ',
			$dispatcher,
			'The request value must never be concatenated into an include path',
		);
		self::assertStringNotContainsString(
			' . $pcRequestedSection',
			str_replace(
				['$pcSettingsSectionFiles[$pcRequestedSection]', 'isset($pcSettingsSectionFiles[$pcRequestedSection])'],
				['', ''],
				$dispatcher,
			),
			'The request value must never be concatenated into an include path',
		);
	}

	public function testDispatcherHasNoSoftDenialCard(): void
	{
		$dispatcher = self::read('templates/org-app-settings.php');
		self::assertStringContainsString('hard-denied', $dispatcher);
		self::assertStringNotContainsString('Only app administrators may change these settings.', $dispatcher);
		self::assertStringNotContainsString('if (!$canAdminApp)', $dispatcher);
	}

	/**
	 * @return array<string, string>
	 */
	private static function jsAnchorSections(): array
	{
		$js = self::read('js/settings-legacy-redirect.js');
		self::assertSame(
			1,
			preg_match('/ANCHOR_SECTIONS\s*=\s*Object\.freeze\(\{(.*?)\}\)/s', $js, $m),
			'settings-legacy-redirect.js must declare a frozen ANCHOR_SECTIONS map',
		);
		preg_match_all("/'([a-z0-9_-]+)'\s*:\s*'([a-z-]+)'/", $m[1], $pairs, PREG_SET_ORDER);
		$map = [];
		foreach ($pairs as $pair) {
			self::assertArrayNotHasKey($pair[1], $map, "Duplicate anchor '{$pair[1]}' in JS map");
			$map[$pair[1]] = $pair[2];
		}
		return $map;
	}

	public function testJsAnchorMapMirrorsCatalogLegacyAnchorsExactly(): void
	{
		self::assertSame(
			SettingsSectionCatalog::LEGACY_ANCHORS,
			self::jsAnchorSections(),
			'js/settings-legacy-redirect.js drifted from SettingsSectionCatalog::LEGACY_ANCHORS',
		);
	}

	public function testLegacyRedirectScriptSelfBootsReplace(): void
	{
		$js = self::read('js/settings-legacy-redirect.js');
		self::assertStringContainsString('ProjectCheckSettingsLegacyRedirect', $js);
		self::assertStringContainsString('window.location.replace', $js);
		self::assertStringContainsString('data-pc-settings-section', $js);
		self::assertStringContainsString('data-pc-urls', $js);
	}

	public function testOrgAppSettingsRegistersLegacyRedirectBeforeAdminSettings(): void
	{
		$tpl = self::read('templates/org-app-settings.php');
		self::assertStringContainsString("Util::addScript('projectcheck', 'settings-legacy-redirect')", $tpl);
		$legacyPos = strpos($tpl, "'settings-legacy-redirect'");
		$adminPos = strpos($tpl, "'admin-settings'");
		self::assertNotFalse($legacyPos);
		self::assertNotFalse($adminPos);
		self::assertLessThan($adminPos, $legacyPos);
	}

	public function testEveryLegacyAnchorTargetStillExistsInItsOwningPartial(): void
	{
		$sharedPartialsBySection = [
			'access' => [
				'templates/parts/settings/_access-fields.php',
				'templates/org-app-settings.php',
			],
			'admins' => ['templates/parts/settings/_admins-fields.php'],
			'defaults' => ['templates/parts/settings/_defaults-fields.php'],
			'license' => ['templates/parts/license-panel.php'],
			'support' => ['templates/parts/support-us-section.php'],
		];
		foreach (SettingsSectionCatalog::LEGACY_ANCHORS as $anchor => $section) {
			$haystack = self::read('templates/parts/settings/' . $section . '.php');
			foreach ($sharedPartialsBySection[$section] ?? [] as $extra) {
				$haystack .= self::read($extra);
			}
			if (str_starts_with($anchor, 'projectcheck-support-us')) {
				self::assertStringContainsString(
					"'-support-us",
					$haystack,
					"Anchor #{$anchor} generator disappeared from support partial",
				);
				continue;
			}
			self::assertMatchesRegularExpression(
				'/\sid=["\']' . preg_quote($anchor, '/') . '["\']/',
				$haystack,
				"Anchor #{$anchor} must exist on the '{$section}' sub-page so the forwarded fragment still scrolls",
			);
		}
	}

	public function testNavigationBuildsSubListFromControllerData(): void
	{
		$nav = self::read('templates/common/navigation.php');
		self::assertStringContainsString('projectcheck-nav__sublist', $nav);
		self::assertStringContainsString('projectcheck-nav__sublink', $nav);
		self::assertStringContainsString('$parentAriaCurrent = $isOnSettings && $settingsChildren === [];', $nav);
		self::assertMatchesRegularExpression(
			'/if \(\$childActive\): \?>aria-current="page"/',
			$nav,
			'Active settings sub-page must carry aria-current="page"',
		);

		$enricher = self::read('lib/Listener/EnrichTemplateNavigationContext.php');
		self::assertStringContainsString('SettingsSectionCatalog::SECTIONS', $enricher);
		self::assertStringContainsString('settingsSections', $enricher);
	}

	public function testInPageSettingsNavIsIncludedBeforeSectionDispatch(): void
	{
		$dispatcher = self::read('templates/org-app-settings.php');
		$navInclude = strpos($dispatcher, "include __DIR__ . '/parts/settings-nav.php'");
		$sectionInclude = strpos($dispatcher, "include __DIR__ . '/parts/settings/'");
		self::assertNotFalse($navInclude, 'org-app-settings.php must include the in-page chip bar');
		self::assertNotFalse($sectionInclude);
		self::assertGreaterThan($navInclude, $sectionInclude, 'Chip bar must render above the section body');

		$nav = self::read('templates/parts/settings-nav.php');
		self::assertStringContainsString('pc-settings-nav', $nav);
		self::assertStringContainsString('pc-settings-nav__link', $nav);
		self::assertStringContainsString('id="pc-settings-pages"', $nav);
		self::assertStringContainsString("\$_['settingsSectionLabels']", $nav);
		self::assertStringContainsString("settingsSections", $nav);
		self::assertMatchesRegularExpression(
			'/if \(\$active\): \?>aria-current="page"/',
			$nav,
			'Active chip must carry aria-current="page"',
		);
	}

	public function testAppConfigControllerFeedsNavLabelsNotPageTitles(): void
	{
		$controller = self::read('lib/Controller/AppConfigController.php');
		self::assertStringContainsString(
			'$this->settingsSections->navLabel($l, $sectionId)',
			$controller,
			'Sidebar/chip labels must use navLabel() (short DeskCheck-style names)',
		);
		self::assertStringContainsString(
			'$this->settingsSections->label($l, $section)',
			$controller,
			'Page H1 must keep the longer label()',
		);
		self::assertStringContainsString('RedirectResponse', $controller);
		self::assertStringContainsString('settingsSection', $controller);
	}

	public function testPageChromeExposesTheCurrentSectionToClientScripts(): void
	{
		$pageStart = self::read('templates/common/page-start.php');
		self::assertStringContainsString('data-pc-settings-section="<?php p($settingsSection); ?>"', $pageStart);
		self::assertStringContainsString('data-pc-urls=', $pageStart);
		self::assertStringContainsString("\$_['settingsSection'] ?? ''", $pageStart);
	}

	public function testOrgAppSettingsRendersParentBreadcrumbForSubPages(): void
	{
		$tpl = self::read('templates/org-app-settings.php');
		self::assertStringContainsString('breadcrumbParent', $tpl);
		self::assertStringContainsString('breadcrumb--inline', $tpl);
	}

	public function testPostApiRoutesRemainUnchanged(): void
	{
		$names = array_column(self::routes(), 'name');
		foreach ([
			'app_config#savePolicy',
			'app_config#savePersonalPreferences',
			'app_config#searchUsers',
			'app_config#searchGroups',
			'license#apply',
		] as $name) {
			self::assertContains($name, $names, "POST/API route {$name} must remain registered");
		}
	}
}
