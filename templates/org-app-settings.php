<?php
/**
 * In-app settings (delegated app admins and system admins)
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
 */
use OCP\Util;
Util::addScript('projectcheck', 'admin-settings');
Util::addScript('projectcheck', 'org-policy-pickers');
Util::addStyle('projectcheck', 'admin-settings');
Util::addStyle('projectcheck', 'dashboard');
Util::addStyle('projectcheck', 'projects');
Util::addStyle('projectcheck', 'navigation');
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
];
$_ = array_merge(is_array($_) ? $_ : [], $nav);
include __DIR__ . '/common/navigation.php';

$pageId = 'org-settings';
$pageKicker = $l->t('Settings');
$pageTitle = $l->t('ProjectCheck — settings');
$pageTitleId = 'page-title-projectcheck-org';
$pageHelp = $l->t('Set who may use the app, who can manage these settings, and default values for projects and budgets. Changes apply to all users in this Nextcloud. Server administrators can also edit this under Administration.');
$mainContentId = 'projectcheck-org-main';
$mainContentClass = 'projectcheck-org__main';
$wrapperClass = 'projectcheck-org pc-shell';
include __DIR__ . '/common/page-start.php';
?>

		<section class="projectcheck-org__notice pc-section" aria-labelledby="projectcheck-org-trust-h">
			<h2 class="pc-section-title" id="projectcheck-org-trust-h"><?php p($l->t('Before you change anything')); ?></h2>
			<p class="pc-section-intro projectcheck-org__notice-text"><?php p($l->t('These settings are security-relevant. Wrong allowlists or administrators can lock people out of the app or grant access too widely. If you are unsure, make a small change and test with a second account before you rely on the result.')); ?></p>
		</section>

		<?php
		$showSectionNav = true;
		include __DIR__ . '/parts/org-settings-form.php';
		?>

<?php include __DIR__ . '/common/page-end.php'; ?>
