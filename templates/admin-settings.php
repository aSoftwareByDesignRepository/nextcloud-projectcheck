<?php
/**
 * Server administration — ProjectCheck (no in-app navigation chrome)
 *
 * Mega-form for backward compatibility: all policy fieldsets + license + support
 * on one page. Prefer in-app Settings (`/apps/projectcheck/settings/{section}`)
 * for per-topic pages. Scoped save uses settings_section=all.
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 *
 * @var \OCP\IL10N $l
 * @var array $policy
 * @var string $allowedUserLines
 * @var string $allowedGroupLines
 * @var string $appAdminLines
 * @var string $saveUrl
 * @var string $default_hourly_rate
 * @var string $budget_warning_threshold
 * @var string $max_projects_per_user
 * @var string $enable_time_tracking
 * @var string $enable_customer_management
 * @var string $enable_budget_tracking
 */
use OCP\Util;
Util::addStyle('projectcheck', 'common/feedback-system');
// Init script list before prepend: OCP\Util::addScript(..., true) requires a non-null bucket (PHP 8+).
Util::addScript('projectcheck', 'admin-settings');
Util::addScript('projectcheck', 'pc-l10n', 'core', true);
Util::addScript('projectcheck', 'org-policy-pickers');
Util::addScript('projectcheck', 'license-settings');
Util::addStyle('projectcheck', 'admin-settings');
Util::addStyle('projectcheck', 'license-settings');
$restrictOn = !empty($policy['restrictionEnabled']);
$formId = 'projectcheck-admin-form';
$settingsSectionsToShow = ['access', 'admins', 'defaults', 'license', 'support'];
$settingsSectionScope = 'all';
$showSectionNav = false;

include __DIR__ . '/common/pc-l10n-bootstrap.php';
?>
<div class="section projectcheck-admin" id="projectcheck-admin-root">
	<h2 class="projectcheck-admin__title"><?php p($l->t('ProjectCheck')); ?></h2>
	<p class="projectcheck-admin__intro"><?php p($l->t('Control who can see and use ProjectCheck, set delegated app administrators, and adjust app defaults. Nextcloud system administrators always have full access.')); ?></p>
	<p class="projectcheck-admin__intro"><?php p($l->t('Prefer in-app Settings for per-topic pages.')); ?></p>
<?php
include __DIR__ . '/parts/org-settings-form.php';
?>
</div>
