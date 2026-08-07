<?php
/**
 * In-app settings shell (multipage dispatcher).
 *
 * Split into one sub-page per section (DeskCheck pattern): the controller
 * validates `settingsSection` against {@see \OCA\ProjectCheck\Service\SettingsSectionCatalog}
 * and this template dispatches through a literal slug → file map, so no
 * request value is ever used to build an include path.
 *
 * Permission is hard-denied in AppConfigController (error template) — there is
 * no soft denial card in this dispatcher.
 *
 * @var \OCP\IL10N $l
 * @var array $policy
 * @var \OCP\IURLGenerator $urlGenerator
 * @var string $saveUrl
 * @var string $allowedUserLines
 * @var string $allowedGroupLines
 * @var string $appAdminLines
 * @var string $default_hourly_rate
 * @var string $budget_warning_threshold
 * @var string $max_projects_per_user
 * @var string $enable_time_tracking
 * @var string $enable_customer_management
 * @var string $enable_budget_tracking
 * @var array $stats
 * @var string $dashboardUrl
 * @var string $projectsUrl
 * @var string $customersUrl
 * @var string $timeEntriesUrl
 * @var string $employeesUrl
 * @var string $settingsUrl
 * @var string $orgAppSettingsUrl
 * @var string $settingsSection
 * @var array $settingsSectionLabels
 * @var array|null $breadcrumbParent
 * @var string|null $pageTitle
 * @var string|null $pageHelp
 * @var string|null $pageKicker
 */
use OCP\Util;
// Legacy hash forward must load before page scripts so resolve() can replace location first.
Util::addScript('projectcheck', 'settings-legacy-redirect');
Util::addStyle('projectcheck', 'admin-settings');
Util::addStyle('projectcheck', 'dashboard');
Util::addStyle('projectcheck', 'projects');
Util::addStyle('projectcheck', 'navigation');

$pcSettingsSectionFiles = [
	'access' => 'access.php',
	'admins' => 'admins.php',
	'defaults' => 'defaults.php',
	'license' => 'license.php',
	'support' => 'support.php',
];
$pcRequestedSection = (string) ($settingsSection ?? ($_['settingsSection'] ?? 'access'));
if (!isset($pcSettingsSectionFiles[$pcRequestedSection])) {
	$pcRequestedSection = 'access';
}

$policySections = ['access', 'admins', 'defaults'];
if (in_array($pcRequestedSection, $policySections, true)) {
	Util::addScript('projectcheck', 'admin-settings');
	Util::addScript('projectcheck', 'org-policy-pickers');
}
if ($pcRequestedSection === 'license') {
	Util::addScript('projectcheck', 'license-settings');
	Util::addStyle('projectcheck', 'license-settings');
}
$formId = 'projectcheck-org-form';
$nav = [
	'stats' => $stats,
	'dashboardUrl' => $dashboardUrl,
	'projectsUrl' => $projectsUrl,
	'customersUrl' => $customersUrl,
	'timeEntriesUrl' => $timeEntriesUrl,
	'employeesUrl' => $employeesUrl,
	'settingsUrl' => $settingsUrl,
	'orgAppSettingsUrl' => $orgAppSettingsUrl,
	'settingsSection' => $settingsSection ?? ($_['settingsSection'] ?? ''),
	'settingsSectionLabels' => $settingsSectionLabels ?? ($_['settingsSectionLabels'] ?? []),
	'settingsSections' => $_['settingsSections'] ?? ($_['urls']['settingsSections'] ?? []),
	'urls' => $_['urls'] ?? ['settingsSections' => $_['settingsSections'] ?? []],
];
$_ = array_merge(is_array($_) ? $_ : [], $nav);
include __DIR__ . '/common/navigation.php';

$pageId = 'org-settings';
$pageKicker = (string) ($pageKicker ?? $_['pageKicker'] ?? $l->t('Settings'));
$pageTitle = (string) ($pageTitle ?? $_['pageTitle'] ?? $l->t('ProjectCheck — settings'));
$pageTitleId = 'page-title-projectcheck-org';
$pageHelp = (string) ($pageHelp ?? $_['pageHelp'] ?? '');
$mainContentId = 'projectcheck-org-main';
$mainContentClass = 'projectcheck-org__main';
$wrapperClass = 'projectcheck-org pc-shell';
$breadcrumbParent = $breadcrumbParent ?? ($_['breadcrumbParent'] ?? null);
include __DIR__ . '/common/page-start.php';
?>

		<?php if (is_array($breadcrumbParent) && ($breadcrumbParent['label'] ?? '') !== ''): ?>
		<nav class="breadcrumb breadcrumb--inline" aria-label="<?php p($l->t('Breadcrumb')); ?>">
			<ol>
				<li><a href="<?php p((string)($breadcrumbParent['url'] ?? $settingsUrl)); ?>"><?php p((string)$breadcrumbParent['label']); ?></a></li>
				<li aria-current="page"><?php p($pageTitle); ?></li>
			</ol>
		</nav>
		<?php endif; ?>

		<?php
		include __DIR__ . '/parts/settings-nav.php';
		?>

		<?php if ($pcRequestedSection === 'access'): ?>
		<section class="projectcheck-org__notice pc-section" aria-labelledby="projectcheck-org-trust-h">
			<h2 class="pc-section-title" id="projectcheck-org-trust-h"><?php p($l->t('Before you change anything')); ?></h2>
			<p class="pc-section-intro projectcheck-org__notice-text"><?php p($l->t('These settings are security-relevant. Wrong allowlists or administrators can lock people out of the app or grant access too widely. If you are unsure, make a small change and test with a second account before you rely on the result.')); ?></p>
		</section>
		<?php endif; ?>

		<div id="pc-settings-page" class="pc-settings">
<?php
include __DIR__ . '/parts/settings/'
	. $pcSettingsSectionFiles[$pcRequestedSection];
?>
		</div>

<?php include __DIR__ . '/common/page-end.php'; ?>
