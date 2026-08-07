<?php
/**
 * Shared org / server admin form for ProjectCheck access and defaults.
 *
 * Approach A: `$settingsSectionsToShow` selects which panels render.
 * In-app multipage pages pass a single section; NC admin passes all policy
 * sections plus license/support. Scoped saves use `$settingsSectionScope`
 * (`access` | `admins` | `defaults` | `all`).
 *
 * @var \OCP\IL10N $l
 * @var array $formUiStrings from SavePolicyUiStrings::forForm (PHP l10n for the save script; never from JS t()).
 * @var string $saveUrl
 * @var string $allowedUserLines
 * @var string $allowedGroupLines
 * @var string $appAdminLines
 * @var string $default_hourly_rate
 * @var string $default_project_status
 * @var string $default_project_priority
 * @var string $budget_warning_threshold
 * @var string $budget_critical_threshold
 * @var string $items_per_page
 * @var string $max_projects_per_user
 * @var string $enable_time_tracking
 * @var string $enable_customer_management
 * @var string $enable_budget_tracking
 * @var bool $restrictOn
 * @var string $formId HTML id for the form element
 * @var string $orgSearchUsersUrl optional, for user/group search (delegated org admins)
 * @var string $orgSearchGroupsUrl
 * @var list<string>|null $settingsSectionsToShow
 * @var string|null $settingsSectionScope
 * @var string|null $saveButtonLabel
 */
$orgSearchUsersUrl = $orgSearchUsersUrl ?? ($_['orgSearchUsersUrl'] ?? '');
$orgSearchGroupsUrl = $orgSearchGroupsUrl ?? ($_['orgSearchGroupsUrl'] ?? '');
$showSectionNav = false;
$settingsSectionsToShow = $settingsSectionsToShow
	?? $_['settingsSectionsToShow']
	?? ['access', 'admins', 'defaults', 'license', 'support'];
if (!is_array($settingsSectionsToShow)) {
	$settingsSectionsToShow = ['access', 'admins', 'defaults', 'license', 'support'];
}
$settingsSectionScope = (string) ($settingsSectionScope ?? $_['settingsSectionScope'] ?? 'all');
if (!in_array($settingsSectionScope, ['access', 'admins', 'defaults', 'all'], true)) {
	$settingsSectionScope = 'all';
}
$showAccess = in_array('access', $settingsSectionsToShow, true);
$showAdmins = in_array('admins', $settingsSectionsToShow, true);
$showDefaults = in_array('defaults', $settingsSectionsToShow, true);
$showLicense = in_array('license', $settingsSectionsToShow, true);
$showSupport = in_array('support', $settingsSectionsToShow, true);
$includePolicyForm = $showAccess || $showAdmins || $showDefaults;

if (!isset($restrictOn)) {
	$restrictOn = !empty($policy['restrictionEnabled']);
}
$formId = $formId ?? 'projectcheck-admin-form';
$statusId = $formId . '-status';
$saveId = $formId . '-save';
if (!isset($formUiStrings) && isset($l)) {
	$formUiStrings = \OCA\ProjectCheck\Service\SavePolicyUiStrings::forForm($l);
}
if (!is_array($formUiStrings)) {
	$formUiStrings = [];
}
try {
	$formUiJson = (string) json_encode($formUiStrings, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
} catch (\JsonException) {
	$formUiJson = '{}';
}

if (!isset($saveButtonLabel) || $saveButtonLabel === '') {
	$saveButtonLabel = match ($settingsSectionScope) {
		'access' => $l->t('Save access settings'),
		'admins' => $l->t('Save administrators'),
		'defaults' => $l->t('Save defaults'),
		default => $l->t('Save settings'),
	};
}
?>
<?php if ($includePolicyForm) { ?>
	<form
		class="projectcheck-admin-form"
		id="<?php p($formId); ?>"
		data-pc-form-strings="<?php p($formUiJson); ?>"
		data-pc-settings-section="<?php p($settingsSectionScope); ?>"
		<?php if ($orgSearchUsersUrl !== '') { ?>
		data-pc-search-users-url="<?php p($orgSearchUsersUrl); ?>"
		<?php } ?>
		<?php if ($orgSearchGroupsUrl !== '') { ?>
		data-pc-search-groups-url="<?php p($orgSearchGroupsUrl); ?>"
		<?php } ?>
		method="post"
		action="<?php p($saveUrl); ?>"
		novalidate
		aria-label="<?php p($l->t('ProjectCheck access and defaults')); ?>"
	>
		<input type="hidden" name="settings_section" value="<?php p($settingsSectionScope); ?>">
		<?php if ($showAccess) {
			include __DIR__ . '/settings/_access-fields.php';
		} ?>
		<?php if ($showAdmins) {
			include __DIR__ . '/settings/_admins-fields.php';
		} ?>
		<?php if ($showDefaults) {
			include __DIR__ . '/settings/_defaults-fields.php';
		} ?>

		<div class="projectcheck-form-actions">
			<button type="submit" class="button primary projectcheck-save-button" id="<?php p($saveId); ?>"><?php p($saveButtonLabel); ?></button>
		</div>
		<p class="projectcheck-form-status" id="<?php p($statusId); ?>" role="status" aria-live="polite" tabindex="-1" hidden></p>
	</form>
<?php } ?>
<?php
if ($showLicense) {
	include __DIR__ . '/license-panel.php';
}
if ($showSupport) {
	$supportUsLanguageCode = method_exists($l, 'getLanguageCode') ? (string)$l->getLanguageCode() : 'en';
	$supportUsCssPrefix = 'projectcheck';
	$supportUsBtnPrimaryClass = 'button primary';
	$supportUsBtnSecondaryClass = 'button';
	$supportUsLicenseUrl = $_['supportUsLicenseUrl']
		?? (isset($urlGenerator) ? $urlGenerator->linkToRoute('projectcheck.app_config.settingsSection', ['section' => 'license']) . '#projectcheck-license' : '/index.php/apps/projectcheck/settings/license#projectcheck-license');
	$supportUsLinks = new \OCA\ProjectCheck\Support\SupportUsLinks('ProjectCheck', true, $supportUsLicenseUrl);
	include __DIR__ . '/support-us-section.php';
}
?>
