<?php

/**
 * Settings template for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

script('projectcheck', 'settings');
style('projectcheck', 'settings');
style('projectcheck', 'navigation');
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<div id="app-content" role="main">
	<div id="app-content-wrapper">
		<div class="settings-container">
			<div class="settings-header">
				<h1><?php p($l->t('Project Control Settings')); ?></h1>
				<p><?php p($l->t('Configure your project management preferences')); ?></p>
			</div>

			<div id="settings-message" class="settings-message" style="display: none;" role="status" aria-live="polite" aria-atomic="true" hidden></div>

			<form id="settings-form" class="settings-form">
				<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']) ?>">
				<div class="settings-section">
					<h3><?php p($l->t('Default Values')); ?></h3>

					<div class="form-group">
						<label for="defaultHourlyRate"><?php p($l->t('Default Hourly Rate (€)')); ?></label>
						<input type="number" id="defaultHourlyRate" name="defaultHourlyRate"
							value="<?php p($settings['defaultHourlyRate']); ?>"
							step="0.01" min="0" class="form-control">
						<small><?php p($l->t('Default hourly rate for new projects')); ?></small>
					</div>

					<div class="form-group">
						<label for="defaultProjectStatus"><?php p($l->t('Default Project Status')); ?></label>
						<select id="defaultProjectStatus" name="defaultProjectStatus" class="form-control">
							<option value="Active" <?php if ($settings['defaultProjectStatus'] === 'Active') p('selected'); ?>>
								<?php p($l->t('Active')); ?>
							</option>
							<option value="On Hold" <?php if ($settings['defaultProjectStatus'] === 'On Hold') p('selected'); ?>>
								<?php p($l->t('On Hold')); ?>
							</option>
							<option value="Completed" <?php if ($settings['defaultProjectStatus'] === 'Completed') p('selected'); ?>>
								<?php p($l->t('Completed')); ?>
							</option>
							<option value="Cancelled" <?php if ($settings['defaultProjectStatus'] === 'Cancelled') p('selected'); ?>>
								<?php p($l->t('Cancelled')); ?>
							</option>
						</select>
					</div>

					<div class="form-group">
						<label for="defaultProjectPriority"><?php p($l->t('Default Project Priority')); ?></label>
						<select id="defaultProjectPriority" name="defaultProjectPriority" class="form-control">
							<option value="Low" <?php if ($settings['defaultProjectPriority'] === 'Low') p('selected'); ?>>
								<?php p($l->t('Low')); ?>
							</option>
							<option value="Medium" <?php if ($settings['defaultProjectPriority'] === 'Medium') p('selected'); ?>>
								<?php p($l->t('Medium')); ?>
							</option>
							<option value="High" <?php if ($settings['defaultProjectPriority'] === 'High') p('selected'); ?>>
								<?php p($l->t('High')); ?>
							</option>
							<option value="Critical" <?php if ($settings['defaultProjectPriority'] === 'Critical') p('selected'); ?>>
								<?php p($l->t('Critical')); ?>
							</option>
						</select>
					</div>
				</div>

				<div class="settings-section">
					<h3><?php p($l->t('Budget Alerts')); ?></h3>

					<div class="form-group">
						<label for="budgetWarningThreshold"><?php p($l->t('Budget Warning Threshold (Percent)')); ?></label>
						<input type="number" id="budgetWarningThreshold" name="budgetWarningThreshold"
							value="<?php p($settings['budgetWarningThreshold']); ?>"
							min="0" max="100" class="form-control">
						<small><?php p($l->t('Show warning when budget consumption reaches this percentage')); ?></small>
					</div>

					<div class="form-group">
						<label for="budgetCriticalThreshold"><?php p($l->t('Budget Critical Threshold (Percent)')); ?></label>
						<input type="number" id="budgetCriticalThreshold" name="budgetCriticalThreshold"
							value="<?php p($settings['budgetCriticalThreshold']); ?>"
							min="0" max="100" class="form-control">
						<small><?php p($l->t('Show critical alert when budget consumption reaches this percentage')); ?></small>
					</div>
				</div>

				<div class="settings-section">
					<h3><?php p($l->t('Display Options')); ?></h3>

					<div class="form-group">
						<label for="itemsPerPage"><?php p($l->t('Items per Page')); ?></label>
						<input type="number" id="itemsPerPage" name="itemsPerPage"
							value="<?php p($settings['itemsPerPage']); ?>"
							min="5" max="100" class="form-control">
						<small><?php p($l->t('Number of projects to show per page')); ?></small>
					</div>

					<div class="form-group">
						<label><?php p($l->t('Date Format')); ?></label>
						<input type="text" class="form-control" value="dd.mm.yyyy" readonly>
						<small><?php p($l->t('Date format is fixed to DD.MM.YYYY for all users')); ?></small>
					</div>
				</div>

				<div class="settings-section">
					<h3><?php p($l->t('Notifications')); ?></h3>

					<div class="form-group">
						<label class="checkbox-label">
							<input type="checkbox" id="emailNotifications" name="emailNotifications"
								<?php if ($settings['emailNotifications']) p('checked'); ?>>
							<?php p($l->t('Enable email notifications')); ?>
						</label>
					</div>

					<div class="form-group">
						<label class="checkbox-label">
							<input type="checkbox" id="budgetAlerts" name="budgetAlerts"
								<?php if ($settings['budgetAlerts']) p('checked'); ?>>
							<?php p($l->t('Enable budget alerts')); ?>
						</label>
					</div>

					<div class="form-group">
						<label class="checkbox-label">
							<input type="checkbox" id="projectUpdates" name="projectUpdates"
								<?php if ($settings['projectUpdates']) p('checked'); ?>>
							<?php p($l->t('Notify on project updates')); ?>
						</label>
					</div>
				</div>

				<div class="settings-actions">
					<button type="submit" class="button primary">
						<?php p($l->t('Save Settings')); ?>
					</button>
					<button type="button" id="reset-settings" class="button">
						<?php p($l->t('Reset to Defaults')); ?>
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script nonce="<?php p($_['cspNonce']) ?>">
	// Local SVG icon library for settings
	const settingsSvgIcons = {
		save: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17,21 17,13 7,13 7,21"/><polyline points="7,3 7,8 15,8"/></svg>',
		'rotate-ccw': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/></svg>',
		settings: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.39a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>'
	};

	// Initialize icons
	document.addEventListener('DOMContentLoaded', function() {
		document.querySelectorAll('[data-lucide]').forEach(function(el) {
			const iconName = el.getAttribute('data-lucide');
			if (settingsSvgIcons[iconName]) {
				el.innerHTML = settingsSvgIcons[iconName];
			}
		});
	});
</script>