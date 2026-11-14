<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;

/**
 * File-content contracts for the unified license admin page (PC2 mobile seats).
 *
 * The license UX is the canonical shared Check-family license page synced from
 * _shared/license-page. These contracts guard the wiring: the shared assets are
 * present and registered, the host wrapper feeds the canonical params, and the
 * license API routes exist. ProjectCheck is seats-only — no terminal markup may
 * be enabled in the host wrapper.
 */
final class LicensePanelContractTest extends TestCase
{
	private string $root;

	protected function setUp(): void
	{
		parent::setUp();
		$this->root = dirname(__DIR__, 3);
	}

	private function read(string $relativePath): string
	{
		$path = $this->root . '/' . ltrim($relativePath, '/');
		self::assertFileExists($path, "Expected file to exist: {$relativePath}");
		return (string)file_get_contents($path);
	}

	public function testSharedLicensePageAssetsExist(): void
	{
		$php = $this->read('templates/common/license-page.php');
		self::assertStringContainsString('data-license-root="1"', $php);
		self::assertStringContainsString('data-prefix="pc"', $php);
		self::assertStringContainsString('id="pc-license-page"', $php);
		$this->read('js/common/license-page.js');
		$this->read('css/common/license-page.css');
	}

	public function testHostWrapperFeedsCanonicalParams(): void
	{
		$tpl = $this->read('templates/parts/license-panel.php');
		self::assertStringContainsString("include __DIR__ . '/../common/license-page.php'", $tpl);
		foreach ([
			'productCode', 'companionText', 'seatAppName',
			'mobileSeatsUsed', 'mobileSeatsLimit', 'mobileSeats', 'showMobileSeats',
			'apiLicenseUrl', 'apiClearLicenseUrl', 'apiSeatsUrl',
			'apiRemoveSeatUrl', 'apiSearchUsersUrl', 'requesttoken',
		] as $param) {
			self::assertStringContainsString('$_[\'' . $param . '\']', $tpl, 'wrapper must set $_[' . $param . ']');
		}
		self::assertStringContainsString("'PC2'", $tpl);
		self::assertStringContainsString('removeSeatPost', $tpl);
		// Seats-only app: never enable the device card.
		self::assertStringNotContainsString("'showTerminal'] = true", $tpl);
		self::assertStringNotContainsString('apiRegisterDeviceUrl', $tpl);
	}

	public function testSharedPageHasAccessibleWidgets(): void
	{
		$tpl = $this->read('templates/common/license-page.php');
		self::assertStringContainsString('role="status" aria-live="polite"', $tpl);
		self::assertStringContainsString('role="alert" aria-live="assertive"', $tpl);
		self::assertStringContainsString('role="meter"', $tpl);
		self::assertStringContainsString('<dialog id="pc-license-', $tpl);
		self::assertStringContainsString('role="combobox"', $tpl);
	}

	public function testOrgSettingsFormIncludesPanelAfterFormCloses(): void
	{
		$form = $this->read('templates/parts/org-settings-form.php');

		$formCloseAt = strpos($form, '</form>');
		$panelIncludeAt = strpos($form, "include __DIR__ . '/license-panel.php';");
		$supportUsIncludeAt = strpos($form, "include __DIR__ . '/support-us-section.php';");

		self::assertIsInt($formCloseAt, 'Settings form must close with </form>');
		self::assertIsInt($panelIncludeAt, 'org-settings-form.php must include license-panel.php');
		self::assertIsInt($supportUsIncludeAt, 'org-settings-form.php must include support-us-section.php');
		self::assertGreaterThan($formCloseAt, $panelIncludeAt, 'License panel must render after </form> so Save settings never wraps it.');
		self::assertLessThan($supportUsIncludeAt, $panelIncludeAt, 'License panel must render before Support & us.');
		self::assertStringContainsString("settings_section", $form);
	}

	public function testOrgAppSettingsAndAdminSettingsWireSharedAssets(): void
	{
		$admin = $this->read('templates/admin-settings.php');
		self::assertStringContainsString("Util::addScript('projectcheck', 'common/license-page')", $admin, 'admin-settings.php must load common/license-page.js');
		self::assertStringContainsString("Util::addStyle('projectcheck', 'common/license-page')", $admin, 'admin-settings.php must load common/license-page.css');
		self::assertStringNotContainsString("license-settings'", $admin, 'old per-app license assets must be gone');

		$org = $this->read('templates/org-app-settings.php');
		self::assertStringContainsString("Util::addScript('projectcheck', 'settings-legacy-redirect')", $org);
		self::assertStringContainsString("if (\$pcRequestedSection === 'license')", $org, 'In-app settings must gate license assets by section');
		self::assertMatchesRegularExpression(
			"/if \(\\\$pcRequestedSection === 'license'\) \{\s*Util::addScript\('projectcheck', 'common\/license-page'\);/s",
			$org,
		);
	}

	public function testAdminSettingsSupportUsLicenseUrlTargetsLicenseSection(): void
	{
		$admin = $this->read('lib/Settings/AdminSettings.php');
		self::assertStringContainsString("settingsSection", $admin);
		self::assertStringContainsString("['section' => 'license']", $admin);
		self::assertStringContainsString("'supportUsLicenseUrl' => \$licenseSectionUrl . '#projectcheck-license'", $admin);
		self::assertStringNotContainsString("settingsIndexUrl . '#projectcheck-license'", $admin);
	}

	public function testOrgAppSettingsDoesNotDoubleIncludeLicensePanel(): void
	{
		$tpl = $this->read('templates/org-app-settings.php');
		self::assertStringNotContainsString("include __DIR__ . '/parts/license-panel.php';", $tpl);
	}

	public function testAppConfigControllerInjectsLicenseServiceAndBuildsLicenseUrls(): void
	{
		$controller = $this->read('lib/Controller/AppConfigController.php');
		self::assertStringContainsString('use OCA\ProjectCheck\Service\LicenseService;', $controller);
		self::assertStringContainsString('private LicenseService $licenseService', $controller);
		self::assertStringContainsString("'licenseApiUrl' =>", $controller);
		self::assertStringContainsString("'licenseClearUrl' =>", $controller);
		self::assertStringContainsString("'licenseSeatsUrl' =>", $controller);
		self::assertStringContainsString("'licenseAssignSeatUrl' =>", $controller);
		self::assertStringContainsString("'licenseSearchUsersUrl' =>", $controller);
		self::assertStringContainsString("'licenseStatus' =>", $controller);
		self::assertStringContainsString("'supportUsLicenseUrl' =>", $controller);
		self::assertStringContainsString('#projectcheck-license', $controller);
		self::assertStringContainsString("settingsSection", $controller);
		// SSR must be resilient to a missing/mid-migration license schema.
		self::assertMatchesRegularExpression('/try\s*\{[^}]*licenseService->status\(\)/s', $controller);
		self::assertStringContainsString('catch (\Throwable $e)', $controller);
	}

	public function testApplicationRegistersAppConfigControllerWithLicenseService(): void
	{
		$app = $this->read('lib/AppInfo/Application.php');
		self::assertMatchesRegularExpression(
			'/AppConfigController::class,\s*function[^}]*LicenseService::class\)/s',
			$app
		);
	}

	public function testRoutesStillExposeLicenseApis(): void
	{
		$routes = $this->read('appinfo/routes.php');
		self::assertStringContainsString("'name' => 'license#show'", $routes);
		self::assertStringContainsString("'name' => 'license#apply'", $routes);
		self::assertStringContainsString("'name' => 'license#remove'", $routes);
		self::assertStringContainsString("'name' => 'license#seats'", $routes);
		self::assertStringContainsString("'name' => 'license#assignSeat'", $routes);
		self::assertStringContainsString("'name' => 'license#removeSeat'", $routes);
		self::assertStringContainsString("'name' => 'license#removeSeatPost'", $routes);
		self::assertStringContainsString('/api/license', $routes);
	}

	public function testVersionWasBumped(): void
	{
		$version = trim($this->read('appinfo/version'));
		self::assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version);
		$info = $this->read('appinfo/info.xml');
		self::assertStringContainsString('<version>' . $version . '</version>', $info);
		// Mobile companion track is 2.0.86+ (settlement + idempotency repair).
		self::assertTrue(version_compare($version, '2.0.86', '>='), 'expected appinfo/version >= 2.0.86, got ' . $version);
	}
}
