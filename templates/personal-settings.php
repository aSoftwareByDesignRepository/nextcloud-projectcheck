<?php

/**
 * Personal settings template for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

use OCP\Util;

Util::addScript('projectcheck', 'personal-settings');
Util::addStyle('projectcheck', 'personal-settings');
Util::addStyle('projectcheck', 'navigation');
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<div id="app-content" role="main">
    <div id="app-content-wrapper">
        <div class="section">
            <h2><?php p($l->t('Project Control Preferences')); ?></h2>

            <form id="projectcheck-personal-settings" class="projectcheck-personal-form">
                <div class="form-group">
                    <label for="default_hourly_rate"><?php p($l->t('Default Hourly Rate (€)')); ?></label>
                    <input type="number" id="default_hourly_rate" name="default_hourly_rate"
                        value="<?php p($default_hourly_rate); ?>" step="0.01" min="0">
                    <p class="setting-hint"><?php p($l->t('Your default hourly rate for time entries')); ?></p>
                </div>

                <div class="form-group">
                    <label for="dashboard_refresh_interval"><?php p($l->t('Dashboard Refresh Interval (seconds)')); ?></label>
                    <select id="dashboard_refresh_interval" name="dashboard_refresh_interval">
                        <option value="15" <?php if ($dashboard_refresh_interval === '15') echo 'selected'; ?>>15</option>
                        <option value="30" <?php if ($dashboard_refresh_interval === '30') echo 'selected'; ?>>30</option>
                        <option value="60" <?php if ($dashboard_refresh_interval === '60') echo 'selected'; ?>>60</option>
                        <option value="300" <?php if ($dashboard_refresh_interval === '300') echo 'selected'; ?>>300</option>
                        <option value="0" <?php if ($dashboard_refresh_interval === '0') echo 'selected'; ?>><?php p($l->t('Disabled')); ?></option>
                    </select>
                    <p class="setting-hint"><?php p($l->t('How often to refresh dashboard statistics')); ?></p>
                </div>

                <div class="form-group">
                    <label for="show_completed_projects"><?php p($l->t('Show Completed Projects')); ?></label>
                    <select id="show_completed_projects" name="show_completed_projects">
                        <option value="yes" <?php if ($show_completed_projects === 'yes') echo 'selected'; ?>><?php p($l->t('Yes')); ?></option>
                        <option value="no" <?php if ($show_completed_projects === 'no') echo 'selected'; ?>><?php p($l->t('No')); ?></option>
                    </select>
                    <p class="setting-hint"><?php p($l->t('Show completed projects in project lists')); ?></p>
                </div>

                <div class="form-group">
                    <label for="time_entry_reminder"><?php p($l->t('Time Entry Reminder')); ?></label>
                    <select id="time_entry_reminder" name="time_entry_reminder">
                        <option value="yes" <?php if ($time_entry_reminder === 'yes') echo 'selected'; ?>><?php p($l->t('Yes')); ?></option>
                        <option value="no" <?php if ($time_entry_reminder === 'no') echo 'selected'; ?>><?php p($l->t('No')); ?></option>
                    </select>
                    <p class="setting-hint"><?php p($l->t('Show reminder to log time entries')); ?></p>
                </div>

                <div class="form-group">
                    <label for="email_notifications"><?php p($l->t('Email Notifications')); ?></label>
                    <select id="email_notifications" name="email_notifications">
                        <option value="yes" <?php if ($email_notifications === 'yes') echo 'selected'; ?>><?php p($l->t('Yes')); ?></option>
                        <option value="no" <?php if ($email_notifications === 'no') echo 'selected'; ?>><?php p($l->t('No')); ?></option>
                    </select>
                    <p class="setting-hint"><?php p($l->t('Receive email notifications for project events')); ?></p>
                </div>

                <div class="form-group">
                    <label for="default_time_entry_duration"><?php p($l->t('Default Time Entry Duration (hours)')); ?></label>
                    <input type="number" id="default_time_entry_duration" name="default_time_entry_duration"
                        value="<?php p($default_time_entry_duration); ?>" step="0.25" min="0.25" max="24">
                    <p class="setting-hint"><?php p($l->t('Default duration when creating new time entries')); ?></p>
                </div>

                <div class="form-group">
                    <label for="budget_warning_threshold"><?php p($l->t('Budget Warning Threshold (Percent)')); ?></label>
                    <input type="number" id="budget_warning_threshold" name="budget_warning_threshold"
                        value="<?php p($budget_warning_threshold ?? '80'); ?>" min="0" max="100">
                    <p class="setting-hint"><?php p($l->t('Highlight projects when budget consumption reaches this percentage')); ?></p>
                </div>

                <div class="form-group">
                    <label for="budget_critical_threshold"><?php p($l->t('Budget Critical Threshold (Percent)')); ?></label>
                    <input type="number" id="budget_critical_threshold" name="budget_critical_threshold"
                        value="<?php p($budget_critical_threshold ?? '90'); ?>" min="0" max="100">
                    <p class="setting-hint"><?php p($l->t('Highlight projects as critical when budget consumption reaches this percentage')); ?></p>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?php p($l->t('Save Preferences')); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>