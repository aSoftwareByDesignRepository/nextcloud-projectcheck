<?php

/**
 * Admin settings template for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

use OCP\Util;

Util::addScript('projectcheck', 'admin-settings');
Util::addStyle('projectcheck', 'admin-settings');
Util::addStyle('projectcheck', 'navigation');
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<div id="app-content">
    <div id="app-content-wrapper">
        <div class="section">
            <h2><?php p($l->t('Project Control Settings')); ?></h2>

            <form id="projectcheck-admin-settings" class="projectcheck-admin-form">
                <div class="form-group">
                    <label for="default_hourly_rate"><?php p($l->t('Default Hourly Rate (€)')); ?></label>
                    <input type="number" id="default_hourly_rate" name="default_hourly_rate"
                        value="<?php p($default_hourly_rate); ?>" step="0.01" min="0">
                    <p class="setting-hint"><?php p($l->t('Default hourly rate for new projects')); ?></p>
                </div>

                <div class="form-group">
                    <label for="budget_warning_threshold"><?php p($l->t('Budget Warning Threshold (Percent)')); ?></label>
                    <input type="number" id="budget_warning_threshold" name="budget_warning_threshold"
                        value="<?php p($budget_warning_threshold); ?>" min="0" max="100">
                    <p class="setting-hint"><?php p($l->t('Percentage at which budget warnings are shown')); ?></p>
                </div>

                <div class="form-group">
                    <label for="max_projects_per_user"><?php p($l->t('Maximum Projects per User')); ?></label>
                    <input type="number" id="max_projects_per_user" name="max_projects_per_user"
                        value="<?php p($max_projects_per_user); ?>" min="1">
                    <p class="setting-hint"><?php p($l->t('Maximum number of projects a user can create')); ?></p>
                </div>

                <div class="form-group">
                    <label for="enable_time_tracking"><?php p($l->t('Enable Time Tracking')); ?></label>
                    <select id="enable_time_tracking" name="enable_time_tracking">
                        <option value="yes" <?php if ($enable_time_tracking === 'yes') echo 'selected'; ?>><?php p($l->t('Yes')); ?></option>
                        <option value="no" <?php if ($enable_time_tracking === 'no') echo 'selected'; ?>><?php p($l->t('No')); ?></option>
                    </select>
                    <p class="setting-hint"><?php p($l->t('Enable time tracking functionality')); ?></p>
                </div>

                <div class="form-group">
                    <label for="enable_customer_management"><?php p($l->t('Enable Customer Management')); ?></label>
                    <select id="enable_customer_management" name="enable_customer_management">
                        <option value="yes" <?php if ($enable_customer_management === 'yes') echo 'selected'; ?>><?php p($l->t('Yes')); ?></option>
                        <option value="no" <?php if ($enable_customer_management === 'no') echo 'selected'; ?>><?php p($l->t('No')); ?></option>
                    </select>
                    <p class="setting-hint"><?php p($l->t('Enable customer management functionality')); ?></p>
                </div>

                <div class="form-group">
                    <label for="enable_budget_tracking"><?php p($l->t('Enable Budget Tracking')); ?></label>
                    <select id="enable_budget_tracking" name="enable_budget_tracking">
                        <option value="yes" <?php if ($enable_budget_tracking === 'yes') echo 'selected'; ?>><?php p($l->t('Yes')); ?></option>
                        <option value="no" <?php if ($enable_budget_tracking === 'no') echo 'selected'; ?>><?php p($l->t('No')); ?></option>
                    </select>
                    <p class="setting-hint"><?php p($l->t('Enable budget tracking and consumption calculation')); ?></p>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary"><?php p($l->t('Save Settings')); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>