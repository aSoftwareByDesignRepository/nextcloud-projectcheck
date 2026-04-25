<?php
/**
 * Server administration — ProjectCheck (no in-app navigation chrome)
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
Util::addScript('projectcheck', 'pc-l10n', 'core', true);
Util::addScript('projectcheck', 'admin-settings');
Util::addScript('projectcheck', 'org-policy-pickers');
Util::addStyle('projectcheck', 'admin-settings');
$restrictOn = !empty($policy['restrictionEnabled']);
$formId = 'projectcheck-admin-form';

include __DIR__ . '/common/pc-l10n-bootstrap.php';
?>
<div class="section projectcheck-admin" id="projectcheck-admin-root">
	<h2 class="projectcheck-admin__title"><?php p($l->t('ProjectCheck')); ?></h2>
	<p class="projectcheck-admin__intro"><?php p($l->t('Control who can see and use ProjectCheck, set delegated app administrators, and adjust app defaults. Nextcloud system administrators always have full access.')); ?></p>
<?php
$showSectionNav = true;
include __DIR__ . '/parts/org-settings-form.php';
?>
</div>
