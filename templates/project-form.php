<?php

/**
 * Project creation/editing form template.
 *
 * Nextcloud renders this through TemplateResponse, which extract()-s the
 * payload array into local scope before invoking the template. The
 * documented variables below are guaranteed to exist (or to be null/absent
 * in $_ which we handle defensively). Declared for static analysis so that
 * intelephense and PHPStan stop flagging template-injected variables.
 *
 * @var \OCP\IL10N $l
 * @var array<string,mixed> $_
 * @var \OCP\IURLGenerator $urlGenerator
 * @var \OCA\ProjectCheck\Db\Project|null $project
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

use OCP\Util;

Util::addScript('projectcheck', 'projects');
Util::addScript('projectcheck', 'project-form');
Util::addScript('projectcheck', 'project-form-cost-rates');
Util::addStyle('projectcheck', 'projects');
Util::addStyle('projectcheck', 'common/accessibility');
Util::addStyle('projectcheck', 'navigation');

$isEdit = isset($project) && $project instanceof \OCA\ProjectCheck\Db\Project;
$pageTitle = $isEdit ? $l->t('Edit Project') : $l->t('Create New Project');
$formAction = $_['formAction'] ?? ($isEdit ? '/projects/' . $project->getId() : '/projects');
$formMethod = $isEdit ? 'PUT' : 'POST';
$currencyCode = isset($_['orgCurrency']) && is_string($_['orgCurrency']) ? strtoupper(trim($_['orgCurrency'])) : 'EUR';
$costRateModeLocked = !empty($_['costRateModeLocked']);
$selectedCostRateMode = $isEdit ? $project->getCostRateMode() : \OCA\ProjectCheck\Util\CostRateMode::DEFAULT;
$employeesIndexUrl = $_['employeesIndexUrl'] ?? '';
if (preg_match('/^[A-Z]{3}$/', $currencyCode) !== 1) {
	$currencyCode = 'EUR';
}

// Team management deep-link. Edit-only; the variables stay empty in create
// mode because there is no project yet to deep-link to. The fragment must
// match the id of the team section rendered in project-detail.php.
$teamSectionUrl = ($isEdit && isset($_['teamSectionUrl']) && is_string($_['teamSectionUrl'])) ? $_['teamSectionUrl'] : '';
$projectShowUrl = ($isEdit && isset($_['projectShowUrl']) && is_string($_['projectShowUrl'])) ? $_['projectShowUrl'] : '';
$teamMembersActiveCount = isset($_['teamMembersActiveCount']) ? max(0, (int)$_['teamMembersActiveCount']) : 0;
$teamMembersFormerCount = isset($_['teamMembersFormerCount']) ? max(0, (int)$_['teamMembersFormerCount']) : 0;
$canManageMembers = !empty($_['canManageMembers']);
$teamTotalCount = $teamMembersActiveCount + $teamMembersFormerCount;

// Resolve callout copy once so the template body stays branch-free. Wording
// adapts to manager vs viewer perms so we never advertise an action the
// user cannot perform on the destination page.
if ($canManageMembers) {
	$teamCalloutTitle = $l->t('Looking to add or remove team members?');
	$teamCalloutText = $l->t('Team members are managed on the project page, not in this edit form. Open it to add people, set their hourly rates, and review who can log time.');
	$teamCalloutCta = $l->t('Manage team');
} else {
	$teamCalloutTitle = $l->t('Looking for the team list?');
	$teamCalloutText = $l->t('The team list lives on the project page, not in this edit form. Open it to see who can log time and their hours.');
	$teamCalloutCta = $l->t('View team');
}
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<?php
$pageId = $isEdit ? 'project-edit' : 'project-create';
$pageTitle = $isEdit ? $l->t('Edit Project') : $l->t('Create New Project');
$pageHelp = $isEdit ? $l->t('Update project information') : $l->t('Create a new project');
include __DIR__ . '/common/page-start.php';
?>
        <?php if (!$isEdit): ?>
        <nav class="pc-create-workflow pc-section" aria-label="<?php p($l->t('Steps to create a project')); ?>">
            <h2 class="pc-create-workflow__title"><?php p($l->t('How to set up a new project')); ?></h2>
            <ol class="pc-create-workflow__list">
                <li class="pc-create-workflow__step">
                    <span class="pc-create-workflow__num" aria-hidden="true">1</span>
                    <span><?php p($l->t('Enter name, customer, and description')); ?></span>
                </li>
                <li class="pc-create-workflow__step">
                    <span class="pc-create-workflow__num" aria-hidden="true">2</span>
                    <span><?php p($l->t('Set schedule and status')); ?></span>
                </li>
                <li class="pc-create-workflow__step">
                    <span class="pc-create-workflow__num" aria-hidden="true">3</span>
                    <span><?php p($l->t('Choose how hours are priced')); ?></span>
                </li>
                <li class="pc-create-workflow__step">
                    <span class="pc-create-workflow__num" aria-hidden="true">4</span>
                    <span><?php p($l->t('After saving, add your team so people can log time')); ?></span>
                </li>
            </ol>
        </nav>
        <?php endif; ?>

        <?php if ($isEdit && $teamSectionUrl !== ''): ?>
        <aside class="pc-form-callout" role="note" aria-labelledby="pc-team-callout-title" aria-describedby="pc-team-callout-desc">
            <div class="pc-form-callout__icon" aria-hidden="true">
                <span data-lucide="users" class="lucide-icon"></span>
            </div>
            <div class="pc-form-callout__body">
                <h2 id="pc-team-callout-title" class="pc-form-callout__title">
                    <?php p($teamCalloutTitle); ?>
                    <?php if ($teamTotalCount > 0): ?>
                        <span class="pc-form-callout__count" aria-label="<?php p($l->n('%n team member', '%n team members', $teamTotalCount)); ?>">
                            <?php p($teamTotalCount); ?>
                        </span>
                    <?php endif; ?>
                </h2>
                <p id="pc-team-callout-desc" class="pc-form-callout__text">
                    <?php p($teamCalloutText); ?>
                </p>
                <p class="pc-form-callout__hint">
                    <?php p($l->t('Tip: save this form first if you have unsaved changes — leaving the page will discard them.')); ?>
                </p>
            </div>
            <div class="pc-form-callout__actions">
                <a class="button primary pc-form-callout__cta" href="<?php p($teamSectionUrl); ?>" rel="noopener">
                    <span data-lucide="users" class="lucide-icon" aria-hidden="true"></span>
                    <span><?php p($teamCalloutCta); ?></span>
                </a>
            </div>
        </aside>
        <?php endif; ?>

        <div class="section pc-section">
            <div class="actions">
                <a href="<?php p($_['indexUrl'] ?? '/projects'); ?>" class="button">
                    ← <?php p($l->t('Back to Projects')); ?>
                </a>
                <?php if ($isEdit && $projectShowUrl !== ''): ?>
                    <a href="<?php p($projectShowUrl); ?>" class="button">
                        <?php p($l->t('View project')); ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Project Form -->
        <div class="section">
            <form id="project-form" action="<?php p($formAction); ?>" method="POST">
                <input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']) ?>">

                <section class="pc-section" aria-labelledby="pc-project-basics-heading">
                    <h3 id="pc-project-basics-heading" class="pc-section-title"><?php p($l->t('Basics')); ?></h3>
                    <p class="pc-section-intro"><?php p($l->t('Name, customer, and what this project is about.')); ?></p>
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
                </section>

                <?php
                $htmlLang = isset($_['htmlLang']) && is_string($_['htmlLang']) ? $_['htmlLang'] : 'en';
                $startDateIso = ($isEdit && $project->getStartDate()) ? $project->getStartDate()->format('Y-m-d') : '';
                $endDateIso = ($isEdit && $project->getEndDate()) ? $project->getEndDate()->format('Y-m-d') : '';
                ?>
                <section class="pc-section" aria-labelledby="pc-project-schedule-heading">
                    <h3 id="pc-project-schedule-heading" class="pc-section-title"><?php p($l->t('Schedule & status')); ?></h3>
                    <p class="pc-section-intro"><?php p($l->t('When the project runs and whether work can be logged now.')); ?></p>
                <div class="form-row">
                    <div class="form-group">
                        <label for="start_date"><?php p($l->t('Start Date')); ?></label>
                        <input type="date"
                            id="start_date"
                            name="start_date"
                            class="form-input"
                            lang="<?php p($htmlLang); ?>"
                            value="<?php p($startDateIso); ?>"
                            autocomplete="off"
                            aria-describedby="project-dates-hint">
                    </div>

                    <div class="form-group">
                        <label for="end_date"><?php p($l->t('End Date')); ?></label>
                        <input type="date"
                            id="end_date"
                            name="end_date"
                            class="form-input"
                            lang="<?php p($htmlLang); ?>"
                            value="<?php p($endDateIso); ?>"
                            autocomplete="off"
                            aria-describedby="project-dates-hint">
                    </div>
                </div>
                <p class="form-hint" id="project-dates-hint"><?php p($l->t('End date must be on or after the start date when both are set.')); ?></p>

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
                </section>

                <section class="pc-section" aria-labelledby="pc-project-classification-heading">
                    <h3 id="pc-project-classification-heading" class="pc-section-title"><?php p($l->t('Classification')); ?></h3>
                    <p class="pc-section-intro"><?php p($l->t('For reports only — this does not change how hours are priced.')); ?></p>
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
                </section>

                <section class="pc-section pc-section--pricing" aria-labelledby="pc-pricing-heading">
                    <h3 id="pc-pricing-heading" class="pc-section-title"><?php p($l->t('Pricing')); ?></h3>
                    <?php include __DIR__ . '/parts/pricing-mode-cards.php'; ?>
                </section>

                <!-- Budget & capacity -->
                <section class="pc-section" aria-labelledby="pc-budget-heading">
                    <h3 id="pc-budget-heading" class="pc-section-title"><?php p($l->t('Budget & capacity')); ?></h3>
                    <p class="pc-section-intro" id="pc-capacity-hint" data-hint-project="<?php p($l->t('Available hours are calculated from budget ÷ project hourly rate.')); ?>" data-hint-planning="<?php p($l->t('Planning rate is for capacity estimates only — billed cost uses the pricing method above.')); ?>"></p>
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

                    <div class="form-group" id="pc-hourly-rate-group">
                        <label for="hourly_rate" id="pc-hourly-rate-label"
                            data-label-project="<?php p($l->t('Project hourly rate (%s)', [$currencyCode])); ?>"
                            data-label-planning="<?php p($l->t('Planning hourly rate (%s) — optional', [$currencyCode])); ?>">
                            <?php p($l->t('Hourly Rate (%s)', [$currencyCode])); ?>
                        </label>
                        <input type="number"
                            id="hourly_rate"
                            name="hourly_rate"
                            class="form-input"
                            step="0.01"
                            min="0"
                            value="<?php p($isEdit ? $project->getHourlyRate() : ($_['defaultSettings']['hourly_rate'] ?? '')); ?>"
                            placeholder="0.00"
                            aria-describedby="pc-capacity-hint">
                    </div>
                </div>

                <div class="form-group" id="pc-available-hours-group">
                    <label for="available_hours"><?php p($l->t('Estimated capacity (hours)')); ?></label>
                    <input type="text"
                        id="available_hours"
                        name="available_hours"
                        class="form-input pc-capacity-input"
                        inputmode="decimal"
                        value="<?php p($isEdit ? ($project->getAvailableHours() > 0 ? number_format($project->getAvailableHours(), 2, '.', '') : '') : ''); ?>"
                        placeholder="—"
                        readonly
                        aria-readonly="true"
                        aria-describedby="pc-available-hours-help">
                    <small id="pc-available-hours-help" class="form-help"
                        data-help-project="<?php p($l->t('Calculated from budget ÷ project hourly rate.')); ?>"
                        data-help-planning="<?php p($l->t('Calculated from budget ÷ planning rate (optional). Actual cost uses each person’s billing rate.')); ?>"
                        data-help-unavailable="<?php p($l->t('Add an optional planning hourly rate above to estimate how many hours fit the budget.')); ?>"
                        data-help-empty="<?php p($l->t('Enter a budget and hourly rate to estimate capacity.')); ?>">
                        <?php p($l->t('Calculated automatically from budget and hourly rate')); ?>
                    </small>
                </div>
                </section>

                <!-- Form Actions -->
                <div class="form-actions">
                    <button type="submit" class="button primary">
                        <?php p($isEdit ? $l->t('Update Project') : $l->t('Create Project')); ?>
                    </button>
                    <a href="<?php p($_['indexUrl'] ?? '/projects'); ?>" class="button">
                        <?php p($l->t('Cancel')); ?>
                    </a>
                    <?php if ($isEdit && $teamSectionUrl !== ''): ?>
                        <a href="<?php p($teamSectionUrl); ?>" class="button pc-form-actions__secondary-link" rel="noopener">
                            <span data-lucide="users" class="lucide-icon" aria-hidden="true"></span>
                            <span><?php p($canManageMembers ? $l->t('Manage team') : $l->t('View team')); ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
<?php include __DIR__ . '/common/page-end.php'; ?>