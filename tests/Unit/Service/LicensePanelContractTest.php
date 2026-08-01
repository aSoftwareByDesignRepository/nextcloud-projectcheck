<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;

/**
 * File-content contracts for the polished license admin UI panel (PC2 mobile seats).
 *
 * ProjectCheck is seats-only: unlike DeskCheck/MobilityCheck there is no terminal/kiosk
 * product, so these contracts also guard against terminal markup or wording leaking in
 * from a copy-paste of another Check app's license page.
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

	public function testPanelHasAnchorIdAndDataWiring(): void
	{
		$tpl = $this->read('templates/parts/license-panel.php');
		self::assertStringContainsString('id="projectcheck-license"', $tpl);
		self::assertStringContainsString('id="pc-license-panel"', $tpl);
		self::assertStringContainsString('data-api-license="', $tpl);
		self::assertStringContainsString('data-api-clear-license="', $tpl);
		self::assertStringContainsString('data-api-seats="', $tpl);
		self::assertStringContainsString('data-api-assign-seat="', $tpl);
		self::assertStringContainsString('data-api-remove-seat-base="', $tpl);
		self::assertStringContainsString('data-api-search-users="', $tpl);
		self::assertStringContainsString('data-requesttoken="', $tpl);
		self::assertStringContainsString('data-i18n="', $tpl);
	}

	public function testPanelHasLiveRegionsAndFeedbackBanner(): void
	{
		$tpl = $this->read('templates/parts/license-panel.php');
		self::assertStringContainsString('id="pc-license-live"', $tpl);
		self::assertStringContainsString('id="pc-license-alert"', $tpl);
		self::assertStringContainsString('aria-live="polite"', $tpl);
		self::assertStringContainsString('aria-live="assertive"', $tpl);
		self::assertStringContainsString('id="pc-license-feedback"', $tpl);
		self::assertStringContainsString('role="alert"', $tpl);
	}

	public function testPanelHasAccessibleWidgets(): void
	{
		$tpl = $this->read('templates/parts/license-panel.php');
		self::assertStringContainsString('role="meter"', $tpl);
		self::assertStringContainsString('aria-valuemin="0"', $tpl);
		self::assertStringContainsString('aria-valuemax=', $tpl);
		self::assertStringContainsString('role="dialog"', $tpl);
		self::assertStringContainsString('aria-modal="true"', $tpl);
		self::assertStringContainsString('role="combobox"', $tpl);
		self::assertStringContainsString('aria-haspopup="listbox"', $tpl);
	}

	public function testPanelExplainsSeatsOnlyPricingAndPurchaseCta(): void
	{
		$tpl = $this->read('templates/parts/license-panel.php');
		self::assertStringContainsString('PC2', $tpl);
		self::assertStringContainsString('PC2.', $tpl);
		self::assertStringContainsString('mailto:info@software-by-design.de', $tpl);
		self::assertStringContainsString('https://nextcloud.software-by-design.de/', $tpl);
		self::assertStringContainsString('noopener', $tpl);
		self::assertStringContainsString('mobileAppAvailable', $tpl);
		self::assertStringNotContainsString('mobileAppComingSoon', $tpl);
		self::assertStringNotContainsString('coming soon', strtolower($tpl));
	}

	public function testPanelHasNoTerminalOrKioskMarkup(): void
	{
		$tpl = strtolower($this->read('templates/parts/license-panel.php'));
		self::assertStringNotContainsString('terminal', $tpl);
		self::assertStringNotContainsString('kiosk', $tpl);
		self::assertStringNotContainsString('room display', $tpl);
	}

	public function testLicenseSettingsJsHasNoTerminalOrMessagingDeps(): void
	{
		$js = strtolower($this->read('js/license-settings.js'));
		self::assertStringNotContainsString('terminal', $js);
		self::assertStringNotContainsString('kiosk', $js);
		self::assertStringNotContainsString('messaging', $js);
	}

	public function testLicenseSettingsJsHandlesSeatConflictsAndAvoidsInnerHtmlForNames(): void
	{
		$js = $this->read('js/license-settings.js');
		self::assertStringContainsString('409', $js);
		self::assertStringContainsString('seat_busy', $js);
		self::assertStringContainsString('seatAssignBusy', $js);
		self::assertStringContainsString('displayName', $js);
		self::assertStringContainsString('textContent', $js);
		// Never build user/display-name markup via innerHTML (stored-XSS guard).
		self::assertStringNotContainsString('.innerHTML =', $js);
		// assignedAt is documented as UNIX seconds, not ISO, and multiplied accordingly.
		self::assertStringContainsString('UNIX seconds', $js);
		self::assertStringContainsString('* 1000', $js);
	}

	public function testSeatAssignLockBusyUsesDistinctErrorCode(): void
	{
		$src = $this->read('lib/Service/LicenseService.php');
		self::assertStringContainsString("'seat_busy'", $src);
		self::assertStringContainsString('Another seat update is in progress', $src);
		// Lock contention must not reuse the capacity-exhausted code (auditor trap).
		self::assertMatchesRegularExpression(
			"/withExclusiveLock\\(\\s*self::SEAT_LOCK,\\s*'seat_busy'/s",
			$src
		);
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

	public function testOrgAppSettingsAndAdminSettingsWireScriptAndStyle(): void
	{
		$admin = $this->read('templates/admin-settings.php');
		self::assertStringContainsString("Util::addScript('projectcheck', 'license-settings')", $admin, 'admin-settings.php must load license-settings.js');
		self::assertStringContainsString("Util::addStyle('projectcheck', 'license-settings')", $admin, 'admin-settings.php must load license-settings.css');

		$org = $this->read('templates/org-app-settings.php');
		self::assertStringContainsString("Util::addScript('projectcheck', 'settings-legacy-redirect')", $org);
		self::assertStringContainsString("if (\$pcRequestedSection === 'license')", $org, 'In-app settings must gate license assets by section');
		self::assertMatchesRegularExpression(
			"/if \(\\\$pcRequestedSection === 'license'\) \{\s*Util::addScript\('projectcheck', 'license-settings'\);/s",
			$org,
		);
		self::assertMatchesRegularExpression(
			"/if \(in_array\(\\\$pcRequestedSection, \\\$policySections, true\)\) \{\s*Util::addScript\('projectcheck', 'admin-settings'\);/s",
			$org,
			'Policy JS must load only on access/admins/defaults',
		);
		$legacyPos = strpos($org, "'settings-legacy-redirect'");
		$licenseGatePos = strpos($org, "if (\$pcRequestedSection === 'license')");
		self::assertNotFalse($legacyPos);
		self::assertNotFalse($licenseGatePos);
		self::assertLessThan($licenseGatePos, $legacyPos, 'Legacy redirect must register before section-gated page scripts');
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
		self::assertStringContainsString("'licenseRemoveSeatBase' =>", $controller);
		self::assertStringContainsString("'licenseSearchUsersUrl' =>", $controller);
		self::assertStringContainsString("'licenseStatus' =>", $controller);
		self::assertStringContainsString("'licenseI18n' =>", $controller);
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
