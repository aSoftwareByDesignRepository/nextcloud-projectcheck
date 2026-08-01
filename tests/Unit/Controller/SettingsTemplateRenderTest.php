<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Tests\Unit\Controller;

use OCA\ProjectCheck\Service\SettingsSectionCatalog;
use PHPUnit\Framework\TestCase;

/**
 * Renders every settings sub-page partial through real PHP includes (no
 * Nextcloud kernel) and asserts each fragment is self-contained.
 */
final class SettingsTemplateRenderTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		require_once dirname(__DIR__, 2) . '/Unit/Support/template_stubs.php';
	}

	private function l10n(): object
	{
		return new class {
			public function getLanguageCode(): string
			{
				return 'en';
			}

			/** @param array<int|string, mixed> $parameters */
			public function t(string $text, array $parameters = []): string
			{
				return $parameters === [] ? $text : vsprintf($text, $parameters);
			}
		};
	}

	/**
	 * @param array<string, mixed> $vars template payload ($_)
	 */
	private function renderPartial(string $section, array $vars = [], ?object $l10n = null): string
	{
		$file = dirname(__DIR__, 3) . '/templates/parts/settings/' . $section . '.php';
		self::assertFileExists($file, "Partial for '{$section}' must exist");
		$_ = $vars;
		$l = $l10n ?? $this->l10n();
		$policy = $vars['policy'] ?? ['restrictionEnabled' => false];
		$restrictOn = !empty($policy['restrictionEnabled']);
		$allowedUserLines = (string)($vars['allowedUserLines'] ?? '');
		$allowedGroupLines = (string)($vars['allowedGroupLines'] ?? '');
		$appAdminLines = (string)($vars['appAdminLines'] ?? '');
		$saveUrl = (string)($vars['saveUrl'] ?? '/api/config/save');
		$orgSearchUsersUrl = (string)($vars['orgSearchUsersUrl'] ?? '/search/users');
		$orgSearchGroupsUrl = (string)($vars['orgSearchGroupsUrl'] ?? '/search/groups');
		$default_hourly_rate = (string)($vars['default_hourly_rate'] ?? '50.00');
		$default_project_status = (string)($vars['default_project_status'] ?? 'Active');
		$default_project_priority = (string)($vars['default_project_priority'] ?? 'Medium');
		$budget_warning_threshold = (string)($vars['budget_warning_threshold'] ?? '80');
		$budget_critical_threshold = (string)($vars['budget_critical_threshold'] ?? '90');
		$items_per_page = (string)($vars['items_per_page'] ?? '20');
		$max_projects_per_user = (string)($vars['max_projects_per_user'] ?? '100');
		$enable_time_tracking = (string)($vars['enable_time_tracking'] ?? 'yes');
		$enable_customer_management = (string)($vars['enable_customer_management'] ?? 'yes');
		$enable_budget_tracking = (string)($vars['enable_budget_tracking'] ?? 'yes');
		$formId = 'projectcheck-org-form';
		$formUiStrings = is_array($vars['formUiStrings'] ?? null) ? $vars['formUiStrings'] : ['errors' => []];
		$urlGenerator = new class {
			public function linkToRoute(string $route, array $params = []): string
			{
				if ($route === 'projectcheck.app_config.settingsSection') {
					return '/apps/projectcheck/settings/' . ($params['section'] ?? '');
				}
				return '/apps/projectcheck/' . $route;
			}
		};
		ob_start();
		try {
			include $file;
		} finally {
			$html = (string) ob_get_clean();
		}
		return $html;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function licenseVars(): array
	{
		return [
			'licenseI18n' => [
				'badgeNotConfigured' => 'Not configured',
				'seatsMeterLabel' => 'Seats',
				'seatsHeading' => 'Seats',
				'keyHint' => 'Paste key',
				'saveButton' => 'Save',
				'removeButton' => 'Remove',
				'confirmRemoveTitle' => 'Remove?',
				'confirmRemoveBody' => 'Sure',
				'confirmRemoveCancel' => 'Cancel',
				'confirmRemoveConfirm' => 'Remove',
				'expirySoonTitle' => 'Expiring',
			],
			'licenseStatus' => null,
			'licenseSeatsList' => ['data' => [], 'total' => 0, 'limit' => 200, 'offset' => 0],
			'licenseApiUrl' => 'https://cloud.example/apps/projectcheck/api/license',
			'licenseClearUrl' => 'https://cloud.example/apps/projectcheck/api/license',
			'licenseSeatsUrl' => 'https://cloud.example/apps/projectcheck/api/license/seats',
			'licenseAssignSeatUrl' => 'https://cloud.example/apps/projectcheck/api/license/seats',
			'licenseRemoveSeatBase' => 'https://cloud.example/apps/projectcheck/api/license/seats/',
			'licenseSearchUsersUrl' => 'https://cloud.example/apps/projectcheck/api/config/search/users',
			'requesttoken' => 'test-token',
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function policyVars(): array
	{
		return [
			'policy' => ['restrictionEnabled' => false],
			'allowedUserLines' => '',
			'allowedGroupLines' => '',
			'appAdminLines' => '',
			'saveUrl' => '/apps/projectcheck/api/config/save',
			'orgSearchUsersUrl' => '/apps/projectcheck/api/config/search/users',
			'orgSearchGroupsUrl' => '/apps/projectcheck/api/config/search/groups',
			'orgCurrency' => 'EUR',
			'default_hourly_rate' => '50.00',
			'default_project_status' => 'Active',
			'default_project_priority' => 'Medium',
			'budget_warning_threshold' => '80',
			'budget_critical_threshold' => '90',
			'items_per_page' => '20',
			'max_projects_per_user' => '100',
			'enable_time_tracking' => 'yes',
			'enable_customer_management' => 'yes',
			'enable_budget_tracking' => 'yes',
			'formUiStrings' => ['errors' => []],
		];
	}

	public function testEverySectionPartialRendersAsAccessibleFragment(): void
	{
		foreach (SettingsSectionCatalog::SECTIONS as $section) {
			if ($section === 'support') {
				continue;
			}
			$vars = match ($section) {
				'license' => $this->licenseVars(),
				'access', 'admins', 'defaults' => $this->policyVars(),
				default => [],
			};
			$vars['formUiStrings'] = $vars['formUiStrings'] ?? ['errors' => [], 'saving' => '…', 'settingsSaved' => 'ok'];
			$html = $this->renderPartial($section, $vars);
			self::assertStringContainsString('<section', $html, "'{$section}' must render at least one section landmark");
			self::assertDoesNotMatchRegularExpression('/<h1[\s>]/', $html, "'{$section}' must not render an h1 (duplicate page title)");
			self::assertSame(
				substr_count($html, '<section'),
				substr_count($html, '</section>'),
				"'{$section}' has unbalanced <section> tags",
			);
		}
	}

	public function testPolicyPartialsScopeHiddenInputAndSaveLabel(): void
	{
		foreach (['access' => 'Save access settings', 'admins' => 'Save administrators', 'defaults' => 'Save defaults'] as $section => $label) {
			$vars = $this->policyVars();
			$vars['formUiStrings'] = ['errors' => []];
			$html = $this->renderPartial($section, $vars);
			self::assertStringContainsString('name="settings_section"', $html);
			self::assertStringContainsString('value="' . $section . '"', $html);
			self::assertStringContainsString('data-pc-settings-section="' . $section . '"', $html);
			self::assertStringContainsString($label, $html);
		}
	}

	public function testLicensePartialKeepsStableIds(): void
	{
		$html = $this->renderPartial('license', $this->licenseVars());
		self::assertStringContainsString('id="projectcheck-license"', $html);
		self::assertStringContainsString('id="pc-license-panel"', $html);
	}

	public function testTranslatorOutputIsEscapedInAccessPartial(): void
	{
		$evil = $this->l10n();
		$evil = new class {
			public function getLanguageCode(): string
			{
				return 'en';
			}

			public function t(string $text, array $parameters = []): string
			{
				return '<script>alert(1)</script>' . $text;
			}
		};
		$html = $this->renderPartial('access', array_merge($this->policyVars(), ['formUiStrings' => ['errors' => []]]), $evil);
		self::assertStringNotContainsString('<script>alert(1)</script>', $html);
		self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
	}
}
