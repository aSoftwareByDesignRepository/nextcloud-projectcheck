<?php

/**
 * Project creation/editing form template
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

use OCP\Util;

Util::addScript('projectcheck', 'common/datepicker');
Util::addScript('projectcheck', 'projects');
Util::addScript('projectcheck', 'project-form');
Util::addStyle('projectcheck', 'projects');
Util::addStyle('projectcheck', 'common/datepicker');
Util::addStyle('projectcheck', 'navigation');

$isEdit = isset($project) && $project instanceof \OCA\ProjectCheck\Db\Project;
$pageTitle = $isEdit ? $l->t('Edit Project') : $l->t('Create New Project');
$formAction = $_['formAction'] ?? ($isEdit ? '/projects/' . $project->getId() : '/projects');
$formMethod = $isEdit ? 'PUT' : 'POST';
$currencyCode = isset($_['orgCurrency']) && is_string($_['orgCurrency']) ? strtoupper(trim($_['orgCurrency'])) : 'EUR';
if (preg_match('/^[A-Z]{3}$/', $currencyCode) !== 1) {
	$currencyCode = 'EUR';
}
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<div id="app-content" role="main">
    <div id="app-content-wrapper">
        <!-- Page Header -->
        <div class="section">
            <h2><?php p($pageTitle); ?></h2>
            <p><?php p($isEdit ? $l->t('Update project information') : $l->t('Create a new project')); ?></p>

            <div class="actions">
                <a href="<?php p($_['indexUrl'] ?? '/projects'); ?>" class="button">
                    ← <?php p($l->t('Back to Projects')); ?>
                </a>
            </div>
        </div>

        <!-- Project Form -->
        <div class="section">
            <form id="project-form" action="<?php p($formAction); ?>" method="POST">
                <input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']) ?>">

                <!-- Basic Information -->
                <div class="form-group">
                    <label for="name"><?php p($l->t('Project Name')); ?> *</label>
                    <input type="text"
                        id="name"
                        name="name"
                        class="form-input"
                        value="<?php p($isEdit ? $project->getName() : ''); ?>"
                        maxlength="100"
                        required
                        placeholder="<?php p($l->t('Enter project name')); ?>">
                </div>

                <div class="form-group">
                    <label for="short_description"><?php p($l->t('Short Description')); ?> *</label>
                    <textarea id="short_description"
                        name="short_description"
                        class="form-input form-textarea"
                        maxlength="500"
                        required
                        rows="3"
                        placeholder="<?php p($l->t('Brief description of the project (max 500 characters)')); ?>"><?php p($isEdit ? $project->getShortDescription() : ''); ?></textarea>
                    <div class="char-count" aria-live="polite">
                        <span id="short_description-count">0</span>/500
                    </div>
                </div>

                <div class="form-group">
                    <label for="detailed_description"><?php p($l->t('Detailed Description')); ?></label>
                    <textarea id="detailed_description"
                        name="detailed_description"
                        class="form-input form-textarea"
                        maxlength="2000"
                        rows="5"
                        placeholder="<?php p($l->t('Detailed project description (max 2000 characters)')); ?>"><?php p($isEdit ? $project->getDetailedDescription() : ''); ?></textarea>
                    <div class="char-count" aria-live="polite">
                        <span id="detailed_description-count">0</span>/2000
                    </div>
                </div>

                <div class="form-group">
                    <label for="customer_id"><?php p($l->t('Customer')); ?> *</label>
                    <select id="customer_id" name="customer_id" class="form-input form-select" required>
                        <option value=""><?php p($l->t('Select a customer')); ?></option>
                        <?php if (isset($customers) && is_array($customers)): ?>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php p($customer['id']); ?>"
                                    <?php
                                    $selected = false;
                                    if ($isEdit && $project->getCustomerId() == $customer['id']) {
                                        $selected = true;
                                    } elseif (!$isEdit && isset($selectedCustomerId) && $selectedCustomerId == $customer['id']) {
                                        $selected = true;
                                    }
                                    echo $selected ? 'selected' : '';
                                    ?>>
                                    <?php p($customer['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <!-- Project Details -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="start_date"><?php p($l->t('Start Date')); ?></label>
                        <input type="text"
                            id="start_date"
                            name="start_date"
                            class="form-input datepicker-only"
                            placeholder="dd.mm.yyyy"
                            value="<?php p($isEdit && $project->getStartDate() ? $project->getStartDate()->format('d.m.Y') : ''); ?>"
                            pattern="\d{2}\.\d{2}\.\d{4}"
                            title="<?php p($l->t('Please enter date in format dd.mm.yyyy')); ?>"
                            readonly="readonly" autocomplete="off">
                    </div>

                    <div class="form-group">
                        <label for="end_date"><?php p($l->t('End Date')); ?></label>
                        <input type="text"
                            id="end_date"
                            name="end_date"
                            class="form-input datepicker-only"
                            placeholder="dd.mm.yyyy"
                            value="<?php p($isEdit && $project->getEndDate() ? $project->getEndDate()->format('d.m.Y') : ''); ?>"
                            pattern="\d{2}\.\d{2}\.\d{4}"
                            title="<?php p($l->t('Please enter date in format dd.mm.yyyy')); ?>"
                            readonly="readonly" autocomplete="off">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="status"><?php p($l->t('Status')); ?> *</label>
                        <select id="status" name="status" class="form-input form-select" required>
                            <option value="Active" <?php echo ($isEdit && $project->getStatus() === 'Active') || (!$isEdit && isset($_['defaultSettings']['status']) && $_['defaultSettings']['status'] === 'Active') ? 'selected' : ''; ?>>
                                <?php p($l->t('Active')); ?>
                            </option>
                            <option value="On Hold" <?php echo ($isEdit && $project->getStatus() === 'On Hold') || (!$isEdit && isset($_['defaultSettings']['status']) && $_['defaultSettings']['status'] === 'On Hold') ? 'selected' : ''; ?>>
                                <?php p($l->t('On Hold')); ?>
                            </option>
                            <option value="Completed" <?php echo ($isEdit && $project->getStatus() === 'Completed') || (!$isEdit && isset($_['defaultSettings']['status']) && $_['defaultSettings']['status'] === 'Completed') ? 'selected' : ''; ?>>
                                <?php p($l->t('Completed')); ?>
                            </option>
                            <option value="Cancelled" <?php echo ($isEdit && $project->getStatus() === 'Cancelled') || (!$isEdit && isset($_['defaultSettings']['status']) && $_['defaultSettings']['status'] === 'Cancelled') ? 'selected' : ''; ?>>
                                <?php p($l->t('Cancelled')); ?>
                            </option>
                            <?php if ($isEdit) { ?>
                            <option value="Archived" <?php echo $project->getStatus() === 'Archived' ? 'selected' : ''; ?>>
                                <?php p($l->t('Archived')); ?>
                            </option>
                            <?php } ?>
                        </select>
                        <?php if ($isEdit) { ?>
                        <p class="form-hint" id="status-hint"><?php p($l->t('To avoid mistakes, use “Change status” on the project page: transitions are validated there. Archiving removes the project from the default list and stops new time entries until you reactivate.')); ?></p>
                        <?php } ?>
                    </div>

                    <div class="form-group">
                        <label for="priority"><?php p($l->t('Priority')); ?> *</label>
                        <select id="priority" name="priority" class="form-input form-select" required>
                            <option value="Low" <?php echo ($isEdit && $project->getPriority() === 'Low') || (!$isEdit && isset($_['defaultSettings']['priority']) && $_['defaultSettings']['priority'] === 'Low') ? 'selected' : ''; ?>>
                                <?php p($l->t('Low')); ?>
                            </option>
                            <option value="Medium" <?php echo ($isEdit && $project->getPriority() === 'Medium') || (!$isEdit && isset($_['defaultSettings']['priority']) && $_['defaultSettings']['priority'] === 'Medium') ? 'selected' : ''; ?>>
                                <?php p($l->t('Medium')); ?>
                            </option>
                            <option value="High" <?php echo ($isEdit && $project->getPriority() === 'High') || (!$isEdit && isset($_['defaultSettings']['priority']) && $_['defaultSettings']['priority'] === 'High') ? 'selected' : ''; ?>>
                                <?php p($l->t('High')); ?>
                            </option>
                            <option value="Critical" <?php echo ($isEdit && $project->getPriority() === 'Critical') || (!$isEdit && isset($_['defaultSettings']['priority']) && $_['defaultSettings']['priority'] === 'Critical') ? 'selected' : ''; ?>>
                                <?php p($l->t('Critical')); ?>
                            </option>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="project_type"><?php p($l->t('Project Type')); ?> *</label>
                        <select id="project_type" name="project_type" class="form-input form-select" required>
                            <option value="client" <?php echo ($isEdit && $project->getProjectType() === 'client') || (!$isEdit && (!isset($_['defaultSettings']['project_type']) || $_['defaultSettings']['project_type'] === 'client')) ? 'selected' : ''; ?>>
                                <?php p($l->t('Client Project')); ?>
                            </option>
                            <option value="admin" <?php echo ($isEdit && $project->getProjectType() === 'admin') || (!$isEdit && isset($_['defaultSettings']['project_type']) && $_['defaultSettings']['project_type'] === 'admin') ? 'selected' : ''; ?>>
                                <?php p($l->t('Administrative')); ?>
                            </option>
                            <option value="sales" <?php echo ($isEdit && $project->getProjectType() === 'sales') || (!$isEdit && isset($_['defaultSettings']['project_type']) && $_['defaultSettings']['project_type'] === 'sales') ? 'selected' : ''; ?>>
                                <?php p($l->t('Sales & Marketing')); ?>
                            </option>
                            <option value="customer" <?php echo ($isEdit && $project->getProjectType() === 'customer') || (!$isEdit && isset($_['defaultSettings']['project_type']) && $_['defaultSettings']['project_type'] === 'customer') ? 'selected' : ''; ?>>
                                <?php p($l->t('Customer Support')); ?>
                            </option>
                            <option value="product" <?php echo ($isEdit && $project->getProjectType() === 'product') || (!$isEdit && isset($_['defaultSettings']['project_type']) && $_['defaultSettings']['project_type'] === 'product') ? 'selected' : ''; ?>>
                                <?php p($l->t('Product Development')); ?>
                            </option>
                            <option value="meeting" <?php echo ($isEdit && $project->getProjectType() === 'meeting') || (!$isEdit && isset($_['defaultSettings']['project_type']) && $_['defaultSettings']['project_type'] === 'meeting') ? 'selected' : ''; ?>>
                                <?php p($l->t('Meetings & Overhead')); ?>
                            </option>
                            <option value="internal" <?php echo ($isEdit && $project->getProjectType() === 'internal') || (!$isEdit && isset($_['defaultSettings']['project_type']) && $_['defaultSettings']['project_type'] === 'internal') ? 'selected' : ''; ?>>
                                <?php p($l->t('Internal Project')); ?>
                            </option>
                            <option value="research" <?php echo ($isEdit && $project->getProjectType() === 'research') || (!$isEdit && isset($_['defaultSettings']['project_type']) && $_['defaultSettings']['project_type'] === 'research') ? 'selected' : ''; ?>>
                                <?php p($l->t('Research & Development')); ?>
                            </option>
                            <option value="training" <?php echo ($isEdit && $project->getProjectType() === 'training') || (!$isEdit && isset($_['defaultSettings']['project_type']) && $_['defaultSettings']['project_type'] === 'training') ? 'selected' : ''; ?>>
                                <?php p($l->t('Training & Education')); ?>
                            </option>
                            <option value="other" <?php echo ($isEdit && $project->getProjectType() === 'other') || (!$isEdit && isset($_['defaultSettings']['project_type']) && $_['defaultSettings']['project_type'] === 'other') ? 'selected' : ''; ?>>
                                <?php p($l->t('Other')); ?>
                            </option>
                        </select>
                        <small class="form-help"><?php p($l->t('Select the type of project to categorize it for productivity analysis')); ?></small>
                    </div>

                    <div class="form-group">
                        <label for="category"><?php p($l->t('Category')); ?></label>
                        <input type="text"
                            id="category"
                            name="category"
                            class="form-input"
                            value="<?php p($isEdit ? $project->getCategory() : ''); ?>"
                            placeholder="<?php p($l->t('Project category (optional)')); ?>">
                    </div>
                </div>

                <!-- Budget Information -->
                <div class="form-row">
                    <div class="form-group">
                        <label for="total_budget"><?php p($l->t('Total Budget (%s)', [$currencyCode])); ?></label>
                        <input type="number"
                            id="total_budget"
                            name="total_budget"
                            class="form-input"
                            step="0.01"
                            min="0"
                            value="<?php p($isEdit ? $project->getTotalBudget() : ''); ?>"
                            placeholder="0.00">
                    </div>

                    <div class="form-group">
                        <label for="hourly_rate"><?php p($l->t('Hourly Rate (%s)', [$currencyCode])); ?></label>
                        <input type="number"
                            id="hourly_rate"
                            name="hourly_rate"
                            class="form-input"
                            step="0.01"
                            min="0"
                            value="<?php p($isEdit ? $project->getHourlyRate() : ($_['defaultSettings']['hourly_rate'] ?? '')); ?>"
                            placeholder="0.00">
                    </div>
                </div>

                <div class="form-group">
                    <label for="available_hours"><?php p($l->t('Available Hours')); ?></label>
                    <input type="number"
                        id="available_hours"
                        name="available_hours"
                        class="form-input"
                        step="0.01"
                        min="0"
                        value="<?php p($isEdit ? $project->getAvailableHours() : ''); ?>"
                        placeholder="0.00"
                        readonly>
                    <small class="form-help"><?php p($l->t('Calculated automatically from budget and hourly rate')); ?></small>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="button primary">
                        <?php p($isEdit ? $l->t('Update Project') : $l->t('Create Project')); ?>
                    </button>
                    <a href="<?php p($_['indexUrl'] ?? '/projects'); ?>" class="button">
                        <?php p($l->t('Cancel')); ?>
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<script nonce="<?php p($_['cspNonce']) ?>">
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-calculate available hours when budget or hourly rate changes
        const totalBudgetInput = document.getElementById('total_budget');
        const hourlyRateInput = document.getElementById('hourly_rate');
        const availableHoursInput = document.getElementById('available_hours');

        function calculateAvailableHours() {
            const budget = parseFloat(totalBudgetInput.value) || 0;
            const rate = parseFloat(hourlyRateInput.value) || 0;

            if (rate > 0) {
                const hours = budget / rate;
                availableHoursInput.value = hours.toFixed(2);
            } else {
                availableHoursInput.value = '0.00';
            }
        }

        if (totalBudgetInput && hourlyRateInput && availableHoursInput) {
            totalBudgetInput.addEventListener('input', calculateAvailableHours);
            hourlyRateInput.addEventListener('input', calculateAvailableHours);

            // Calculate on page load
            calculateAvailableHours();
        }

        // Character count for textareas
        const shortDescriptionTextarea = document.getElementById('short_description');
        const detailedDescriptionTextarea = document.getElementById('detailed_description');
        const shortDescriptionCount = document.getElementById('short_description-count');
        const detailedDescriptionCount = document.getElementById('detailed_description-count');

        function updateCharCount(textarea, countElement, maxLength) {
            if (textarea && countElement) {
                const currentLength = textarea.value.length;
                countElement.textContent = currentLength;
                const container = countElement.closest('.char-count');
                if (container) {
                    container.classList.remove('char-count--warning', 'char-count--critical');
                    if (currentLength > maxLength * 0.9) {
                        container.classList.add('char-count--critical');
                    } else if (currentLength > maxLength * 0.8) {
                        container.classList.add('char-count--warning');
                    }
                }
            }
        }

        if (shortDescriptionTextarea && shortDescriptionCount) {
            shortDescriptionTextarea.addEventListener('input', function() {
                updateCharCount(this, shortDescriptionCount, 500);
            });
            updateCharCount(shortDescriptionTextarea, shortDescriptionCount, 500);
        }

        if (detailedDescriptionTextarea && detailedDescriptionCount) {
            detailedDescriptionTextarea.addEventListener('input', function() {
                updateCharCount(this, detailedDescriptionCount, 2000);
            });
            updateCharCount(detailedDescriptionTextarea, detailedDescriptionCount, 2000);
        }

    });
</script>