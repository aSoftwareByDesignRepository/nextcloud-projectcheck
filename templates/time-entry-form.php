<?php

/**
 * Time entry form template for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

use OCP\Util;

Util::addScript('projectcheck', 'time-entry-form');
Util::addScript('projectcheck', 'budget-warnings');
Util::addScript('projectcheck', 'budget-info');
Util::addStyle('projectcheck', 'time-entry-form');
Util::addStyle('projectcheck', 'budget-alerts');
Util::addStyle('projectcheck', 'navigation');
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<div id="app-content">
	<div id="app-content-wrapper">
		<div class="time-entry-form-container">
			<div class="time-entry-form-header">
				<h2><?php p($isEdit ? $l->t('Edit Time Entry') : $l->t('Add Time Entry')); ?></h2>
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
										<?php if ($isEdit && $timeEntry->getProjectId() == $project->getId()) echo 'selected'; ?>
										data-hourly-rate="<?php p($project->getHourlyRate()); ?>">
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

				<!-- Project Budget Information -->
				<div id="budget-info-section" class="form-row" style="display: none;">
					<div class="form-group budget-overview">
						<label><?php p($l->t('Project Budget Overview')); ?></label>
						<div class="budget-stats-grid">
							<div class="budget-stat-item">
								<span class="budget-stat-label"><?php p($l->t('Total Budget')); ?></span>
								<span class="budget-stat-value" id="total-budget">€0.00</span>
							</div>
							<div class="budget-stat-item">
								<span class="budget-stat-label"><?php p($l->t('Used Budget')); ?></span>
								<span class="budget-stat-value" id="used-budget">€0.00</span>
							</div>
							<div class="budget-stat-item">
								<span class="budget-stat-label"><?php p($l->t('Remaining Budget')); ?></span>
								<span class="budget-stat-value" id="remaining-budget">€0.00</span>
							</div>
							<div class="budget-stat-item">
								<span class="budget-stat-label"><?php p($l->t('Total Hours')); ?></span>
								<span class="budget-stat-value" id="total-hours">0.00h</span>
								<span class="budget-stat-sub" id="remaining-hours-display" style="display: none;"></span>
							</div>
						</div>
						<div class="budget-progress-container">
							<div class="budget-progress-bar">
								<div class="budget-progress-fill" id="budget-progress-fill" style="width: 0%"></div>
							</div>
							<div class="budget-progress-text">
								<span id="budget-percentage">0%</span> <?php p($l->t('used')); ?>
							</div>
						</div>
					</div>
				</div>

				<div class="form-row">
					<div class="form-group">
						<label for="date" class="required"><?php p($l->t('Date')); ?></label>
						<input type="date" name="date" id="date" class="form-input" required
							value="<?php p($isEdit ? $timeEntry->getDate()->format('Y-m-d') : date('Y-m-d')); ?>"
							max="<?php p(date('Y-m-d')); ?>"
							title="<?php p($l->t('Select date')); ?>">
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
						<label for="hourly_rate" class="required"><?php p($l->t('Hourly Rate (€)')); ?></label>
						<input type="number" name="hourly_rate" id="hourly_rate" class="form-input" step="0.01" min="0" required
							value="<?php p($isEdit ? $timeEntry->getHourlyRate() : ''); ?>"
							placeholder="0.00">
						<div class="error-message" id="hourly_rate-error"></div>
					</div>

					<div class="form-group">
						<label for="total_cost"><?php p($l->t('Total Cost')); ?></label>
						<input type="text" id="total_cost" class="form-input" readonly value="€0.00">
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
			</form>
		</div>
	</div>
</div>

<script nonce="<?php p($_['cspNonce']) ?>">
	// Time entry form JavaScript
	document.addEventListener('DOMContentLoaded', function() {
		const form = document.getElementById('time-entry-form');
		const projectSelect = document.getElementById('project_id');
		const hoursInput = document.getElementById('hours');
		const hourlyRateInput = document.getElementById('hourly_rate');
		const totalCostInput = document.getElementById('total_cost');
		const descriptionTextarea = document.getElementById('description');
		const charCount = document.getElementById('char-count');

		// Calculate total cost
		function calculateTotalCost() {
			const hours = parseFloat(hoursInput.value) || 0;
			const hourlyRate = parseFloat(hourlyRateInput.value) || 0;
			const totalCost = hours * hourlyRate;
			totalCostInput.value = '€' + totalCost.toFixed(2);
		}

		// Update hourly rate when project is selected
		projectSelect.addEventListener('change', function() {
			const selectedOption = this.options[this.selectedIndex];
			const hourlyRate = selectedOption.getAttribute('data-hourly-rate');
			if (hourlyRate) {
				hourlyRateInput.value = hourlyRate;
				calculateTotalCost();
			}
		});

		// Recalculate total cost when hours or hourly rate changes
		hoursInput.addEventListener('input', calculateTotalCost);
		hourlyRateInput.addEventListener('input', calculateTotalCost);

		// Character count for description
		descriptionTextarea.addEventListener('input', function() {
			const count = this.value.length;
			charCount.textContent = count;
		});

		// Form validation
		form.addEventListener('submit', function(e) {
			let isValid = true;

			// Clear previous error messages
			document.querySelectorAll('.error-message').forEach(el => el.textContent = '');

			// Validate required fields
			if (!projectSelect.value) {
				document.getElementById('project_id-error').textContent = '<?php p($l->t('Please select a project')); ?>';
				isValid = false;
			}

			if (!hoursInput.value || parseFloat(hoursInput.value) <= 0) {
				document.getElementById('hours-error').textContent = '<?php p($l->t('Please enter valid hours')); ?>';
				isValid = false;
			}

			if (!hourlyRateInput.value || parseFloat(hourlyRateInput.value) < 0) {
				document.getElementById('hourly_rate-error').textContent = '<?php p($l->t('Please enter a valid hourly rate')); ?>';
				isValid = false;
			}

			if (!isValid) {
				e.preventDefault();
			}
		});

		// Initialize character count
		charCount.textContent = descriptionTextarea.value.length;
	});
</script>