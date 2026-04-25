<?php
/**
 * In-app organization settings (delegated app admins)
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
/* Same app shell as dashboard / projects: #app-navigation + #app-content under #content (row layout, scroll, heights). */
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
?>
<div id="app-content" role="main" class="projectcheck-app-content">
	<div id="app-content-wrapper" class="projectcheck-org">
		<a href="#projectcheck-org-main" class="projectcheck-skip-link"><?php p($l->t('Skip to main content')); ?></a>
		<div id="projectcheck-org-main" class="projectcheck-org__main" role="main" aria-labelledby="page-title-projectcheck-org" tabindex="-1">
		<header class="projectcheck-org__header">
			<p class="projectcheck-org__kicker" id="projectcheck-org-kicker"><?php p($l->t('Organization')); ?></p>
			<h1 class="projectcheck-org__title" id="page-title-projectcheck-org"><?php p($l->t('ProjectCheck — organization')); ?></h1>
			<p class="projectcheck-org__lede"><?php p($l->t('Set who may use the app, who can manage these settings, and default values. Changes apply to all users in this Nextcloud. Server administrators can also edit this under Administration.')); ?></p>
		</header>

		<section class="projectcheck-org__notice" role="region" aria-labelledby="projectcheck-org-trust-h">
			<h2 class="projectcheck-org__notice-title" id="projectcheck-org-trust-h"><?php p($l->t('Before you change anything')); ?></h2>
			<p class="projectcheck-org__notice-text"><?php p($l->t('These settings are security-relevant. Wrong allowlists or administrators can lock people out of the app or grant access too widely. If you are unsure, make a small change and test with a second account before you rely on the result.')); ?></p>
		</section>

		<?php
		include __DIR__ . '/parts/org-settings-form.php';
		?>
		</div>
	</div>
</div>
