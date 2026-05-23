<?php

/**
 * Time entry form template for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

use OCP\Util;

Util::addScript('projectcheck', 'common/api');
Util::addScript('projectcheck', 'time-entry-form');
Util::addScript('projectcheck', 'budget-warnings');
Util::addScript('projectcheck', 'budget-info');
Util::addStyle('projectcheck', 'time-entry-form');
Util::addStyle('projectcheck', 'budget-alerts');
Util::addStyle('projectcheck', 'navigation');
$currencyCode = isset($_['orgCurrency']) && is_string($_['orgCurrency']) ? strtoupper(trim($_['orgCurrency'])) : 'EUR';
if (preg_match('/^[A-Z]{3}$/', $currencyCode) !== 1) {
	$currencyCode = 'EUR';
}
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<?php
$isEdit = isset($isEdit) ? $isEdit : (isset($timeEntry) && $timeEntry instanceof \OCA\ProjectCheck\Db\TimeEntry);
$pageId = $isEdit ? 'time-entry-edit' : 'time-entry-create';
$pageTitle = $isEdit ? $l->t('Edit Time Entry') : $l->t('Add Time Entry');
$pageHelp = $l->t('You can log time only for projects with status Active or On Hold that you can access (creator, admin, or active team member).');
include __DIR__ . '/common/page-start.php';
?>
		<?php
		// Server IL10N for budget-warnings.js: browser t() does not resolve app l10n; embed text in DOM (not script order sensitive).
		?>
		<div id="pc-budget-l10n" class="pc-budget-l10n-source" hidden aria-hidden="true">
			<span data-budget-tpl="exceed"><?php p($l->t('This entry would exceed the project budget by {amount}.')); ?></span>
			<span data-budget-tpl="threshold"><?php p($l->t('After this entry, the project would be at {usage} of the budget. Additional cost: {cost}.')); ?></span>
			<span data-budget-tpl="ok"><?php p($l->t('Entry cost: {entryCost}. Remaining budget: {remaining}.')); ?></span>
			<span data-budget-tpl="error"><?php p($l->t('Could not check budget. Try again.')); ?></span>
			<span data-budget-tpl="loading"><?php p($l->t('Loading…')); ?></span>
			<span data-budget-tpl="remaining-hours"><?php p($l->t('remaining')); ?></span>
			<span data-budget-tpl="remaining-hours-estimate"><?php p($l->t('remaining (estimate)')); ?></span>
			<span data-budget-tpl="no-hour-estimate"><?php p($l->t('Hour estimate unavailable')); ?></span>
			<span data-budget-tpl="used"><?php p($l->t('used')); ?></span>
			<span data-budget-tpl="over-budget"><?php p($l->t('Over budget')); ?></span>
			<span data-budget-tpl="with-entry"><?php p($l->t('incl. this entry')); ?></span>
		</div>
		<div class="time-entry-form-container pc-section">
			<div class="time-entry-form-header">
				<div class="form-actions">
					<a href="<?php p($_['indexUrl']); ?>" class="btn btn-secondary">
						<?php p($l->t('Cancel')); ?>
					</a>
				</div>
			</div>

			<form id="time-entry-form" class="time-entry-form" method="POST" action="<?php p($isEdit ? $_['updateUrl'] : $_['storeUrl']); ?>">
				<?php if ($isEdit && isset($timeEntry)): ?>
					<input type="hidden" name="_method" value="PUT">
					<script nonce="<?php p($_['cspNonce']) ?>">
						// Let JS know this is an edit and which entry to update
						window.timeEntryFormData = {
							isEdit: true,
							timeEntryId: <?php p($timeEntry->getId()); ?>
						};
					</script>
				<?php endif; ?>
				<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']) ?>">
				<div class="form-row">
					<div class="form-group">
						<label for="project_id" class="required"><?php p($l->t('Project')); ?></label>
						<select name="project_id" id="project_id" class="form-input form-select" required>
							<option value=""><?php p($l->t('Select a project')); ?></option>
							<?php if (!empty($projects)): ?>
								<?php foreach ($projects as $project): ?>
									<option value="<?php p($project->getId()); ?>"
										data-cost-rate-mode="<?php p($project->getCostRateMode()); ?>"
										<?php if ($isEdit && $timeEntry->getProjectId() == $project->getId()) echo 'selected'; ?>>
										<?php p($project->getName()); ?> (<?php p($project->getStatus()); ?>)
									</option>
								<?php endforeach; ?>
							<?php else: ?>
								<option value="" disabled><?php p($l->t('No projects available')); ?></option>
							<?php endif; ?>
						</select>
						<div class="error-message" id="project_id-error"></div>
					</div>
				</div>
				<?php if (empty($projects)): ?>
					<div class="time-entry-form-note" role="status" aria-live="polite">
						<?php p($l->t('No selectable project found. Check project status and team assignment.')); ?>
					</div>
				<?php endif; ?>

				<!-- Project Budget Information -->
				<div id="budget-info-section" class="form-row" style="display: none;">
					<div class="form-group budget-overview">
						<label id="budget-overview-heading"><?php p($l->t('Project Budget Overview')); ?></label>
						<div class="budget-stats-grid">
							<div class="budget-stat-item">
								<span class="budget-stat-label"><?php p($l->t('Total Budget')); ?></span>
								<span class="budget-stat-value" id="total-budget"><?php p($currencyCode); ?> 0.00</span>
							</div>
							<div class="budget-stat-item">
								<span class="budget-stat-label"><?php p($l->t('Used Budget')); ?></span>
								<span class="budget-stat-value" id="used-budget"><?php p($currencyCode); ?> 0.00</span>
							</div>
							<div class="budget-stat-item">
								<span class="budget-stat-label"><?php p($l->t('Remaining Budget')); ?></span>
								<span class="budget-stat-value" id="remaining-budget"><?php p($currencyCode); ?> 0.00</span>
							</div>
							<div class="budget-stat-item">
								<span class="budget-stat-label"><?php p($l->t('Total Hours')); ?></span>
								<span class="budget-stat-value" id="total-hours">0.00h</span>
								<span class="budget-stat-sub" id="remaining-hours-display" style="display: none;"></span>
							</div>
						</div>
						<div class="budget-progress-container">
							<div
								class="budget-progress-bar"
								role="progressbar"
								aria-labelledby="budget-overview-heading"
								aria-valuemin="0"
								aria-valuemax="100"
								aria-valuenow="0">
								<div class="budget-progress-fill" id="budget-progress-fill" style="width: 0%"></div>
							</div>
							<div class="budget-progress-text">
								<span id="budget-percentage">0%</span>
								<span id="budget-progress-status"><?php p($l->t('used')); ?></span>
							</div>
						</div>
					</div>
				</div>

				<?php
				$htmlLang = isset($_['htmlLang']) && is_string($_['htmlLang']) ? $_['htmlLang'] : 'en';
				$dateValue = '';
				if ($isEdit && isset($timeEntry)) {
					$dateValue = $timeEntry->getDate()->format('Y-m-d');
				}
				$todayIso = (new \DateTime('today'))->format('Y-m-d');
				?>
				<div class="form-row">
					<div class="form-group">
						<label for="date" class="required"><?php p($l->t('Date')); ?></label>
						<input type="date" name="date" id="date" class="form-input" required
							lang="<?php p($htmlLang); ?>"
							value="<?php p($dateValue); ?>"
							max="<?php p($todayIso); ?>"
							autocomplete="off"
							aria-describedby="date-hint date-error">
						<p class="form-hint" id="date-hint"><?php p($l->t('Use the calendar or type the work date. Future dates are not allowed.')); ?></p>
						<div class="error-message" id="date-error"></div>
					</div>
				</div>

				<div class="form-row">
					<div class="form-group">
						<label for="hours" class="required"><?php p($l->t('Hours')); ?></label>
						<input type="number" name="hours" id="hours" class="form-input" step="0.25" min="0.25" max="24" required
							value="<?php p($isEdit ? $timeEntry->getHours() : ''); ?>"
							placeholder="0.00">
						<div class="error-message" id="hours-error"></div>
					</div>

					<div class="form-group">
						<label for="hourly_rate"><?php p($l->t('Hourly rate (%s)', [$currencyCode])); ?></label>
						<input type="number" name="hourly_rate" id="hourly_rate" class="form-input" step="0.01" min="0.01" readonly
							value="<?php p($isEdit ? $timeEntry->getHourlyRate() : ''); ?>"
							placeholder="0.00"
							aria-required="true"
							aria-describedby="hourly_rate-hint">
						<p class="form-hint" id="hourly_rate-hint" role="status" aria-live="polite">
							<?php p($l->t('Rate is set by the server from the project and work date. It cannot be edited.')); ?>
						</p>
						<div class="error-message" id="hourly_rate-error"></div>
					</div>

					<div class="form-group">
						<label for="total_cost"><?php p($l->t('Total Cost')); ?></label>
						<input type="text" id="total_cost" class="form-input" readonly value="<?php p($currencyCode); ?> 0.00">
					</div>
				</div>

				<div class="form-group">
					<label for="description"><?php p($l->t('Description')); ?></label>
					<textarea name="description" id="description" class="form-input form-textarea" rows="4" maxlength="1000"
						placeholder="<?php p($l->t('Describe the work performed...')); ?>"><?php p($isEdit ? $timeEntry->getDescription() : ''); ?></textarea>
					<div class="char-count">
						<span id="char-count">0</span>/1000
					</div>
					<div class="error-message" id="description-error"></div>
				</div>

				<div class="form-actions">
					<button type="submit" class="btn btn-primary" id="submit-btn">
						<?php if ($isEdit): ?>
							<?php p($l->t('Update Time Entry')); ?>
						<?php else: ?>
							<?php p($l->t('Create Time Entry')); ?>
						<?php endif; ?>
					</button>
					<a href="<?php p($_['indexUrl']); ?>" class="btn btn-secondary">
						<?php p($l->t('Cancel')); ?>
					</a>
				</div>
				<div id="time-entry-form-errors" class="time-entry-form-errors" role="alert" aria-live="assertive"></div>
			</form>
		</div>
<?php include __DIR__ . '/common/page-end.php'; ?>