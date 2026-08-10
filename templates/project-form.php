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
Util::addStyle('projectcheck', 'common/filters');
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
$teamCalloutCta = $canManageMembers ? $l->t('Manage team') : $l->t('View team');
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<?php
$pageId = $isEdit ? 'project-edit' : 'project-create';
$pageTitle = $isEdit ? $l->t('Edit Project') : $l->t('Create New Project');
$pageHelp = $isEdit ? $l->t('Update project information') : $l->t('Create a new project');
ob_start(); ?>
                <a href="<?php p($_['indexUrl'] ?? '/projects'); ?>" class="button secondary">
                    <span data-lucide="arrow-left" class="lucide-icon" aria-hidden="true"></span>
                    <?php p($l->t('Back to Projects')); ?>
                </a>
                <?php if ($isEdit && $projectShowUrl !== ''): ?>
                <a href="<?php p($projectShowUrl); ?>" class="button secondary">
                    <span data-lucide="eye" class="lucide-icon" aria-hidden="true"></span>
                    <?php p($l->t('View project')); ?>
                </a>
                <?php endif; ?>
<?php
$pageHeaderActionsHtml = ob_get_clean();
$pageHeaderActionsLabel = $l->t('Page actions');
include __DIR__ . '/common/page-start.php';
?>
        <?php
        $formErrorText = '';
        if (isset($_GET['message']) && $_GET['message'] === 'error' && isset($_GET['error_text']) && is_string($_GET['error_text'])) {
        	$formErrorText = trim($_GET['error_text']);
        	// Cap attacker-crafted query strings (display is escaped via p()).
        	if (function_exists('mb_substr') && mb_strlen($formErrorText) > 280) {
        		$formErrorText = mb_substr($formErrorText, 0, 279) . '…';
        	} elseif (strlen($formErrorText) > 280) {
        		$formErrorText = substr($formErrorText, 0, 279) . '…';
        	}
        }
        ?>
        <?php if ($formErrorText !== ''): ?>
        <div class="pc-form-alert pc-form-alert--error" role="alert" id="pc-project-form-error" tabindex="-1">
            <span class="pc-form-alert__icon" data-lucide="alert-circle" aria-hidden="true"></span>
            <div class="pc-form-alert__body">
                <p class="pc-form-alert__title"><?php p($l->t('Could not save')); ?></p>
                <p class="pc-form-alert__text"><?php p($formErrorText); ?></p>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$isEdit): ?>
        <p class="pc-form-tip pc-section" role="note" id="pc-form-tip">
            <?php p($l->t('Name, customer, then Save. Everything else stays closed until you need it.')); ?>
        </p>
        <?php endif; ?>

        <?php if ($isEdit && $teamSectionUrl !== ''): ?>
        <p class="pc-form-team-link pc-section" role="note">
            <a class="pc-form-callout__text-link" href="<?php p($teamSectionUrl); ?>" rel="noopener">
                <?php p($teamCalloutCta); ?><?php if ($teamTotalCount > 0): ?> (<?php p($teamTotalCount); ?>)<?php endif; ?>
            </a>
            <span class="pc-form-team-link__hint">
                — <?php p($l->t('Save this form first if you changed anything.')); ?>
            </span>
        </p>
        <?php endif; ?>

        <!-- Project Form -->
        <div class="section">
            <form id="project-form" action="<?php p($formAction); ?>" method="POST">
                <input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']) ?>">
                <?php if (!$isEdit && !empty($_['createIdempotencyNonce'])): ?>
                    <input type="hidden" name="pc_form_nonce" value="<?php p($_['createIdempotencyNonce']); ?>">
                <?php endif; ?>

                <section class="pc-section" aria-labelledby="pc-project-basics-heading">
                    <h3 id="pc-project-basics-heading" class="pc-section-title"><?php p($l->t('Basics')); ?></h3>
                    <p class="pc-section-intro"><?php p($l->t('A name and a customer are enough to save.')); ?></p>
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

                <details class="pc-advanced-details" id="pc-more-about-project"<?php if ($isEdit) {
                	echo ' open';
                } ?>>
                    <summary class="pc-advanced-details__summary"><?php p($l->t('More about this project')); ?></summary>
                    <div class="pc-advanced-details__body">
                        <div class="form-group">
                            <label for="short_description"><?php p($l->t('Short Description')); ?></label>
                            <textarea id="short_description"
                                name="short_description"
                                class="form-input form-textarea"
                                maxlength="500"
                                rows="2"
                                aria-describedby="short_description-help short_description-count-wrap"
                                placeholder="<?php p($l->t('Optional — leave blank to use the project name')); ?>"><?php p($isEdit ? $project->getShortDescription() : ''); ?></textarea>
                            <p class="form-hint" id="short_description-help"><?php p($l->t('Leave blank to use the project name.')); ?></p>
                            <div class="char-count" id="short_description-count-wrap" aria-live="polite">
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
                    </div>
                </details>

                <div class="form-group">
                    <label for="customer_id"><?php p($l->t('Customer')); ?> *</label>
                    <select id="customer_id" name="customer_id" class="form-input form-select" required
                        aria-describedby="customer_id-help<?php p(!empty($_['canCreateCustomer']) ? ' pc-quick-customer-status' : ''); ?>">
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
                    <?php
                    $canCreateCustomer = !empty($_['canCreateCustomer']);
                    $customerStoreUrl = (string)($_['customerStoreUrl'] ?? '');
                    $customerCreateUrl = (string)($_['customerCreateUrl'] ?? '');
                    if ($customerCreateUrl === '' && isset($_['urlGenerator']) && is_object($_['urlGenerator'])) {
                    	$customerCreateUrl = (string)$_['urlGenerator']->linkToRoute('projectcheck.customer.create');
                    }
                    if ($customerStoreUrl === '' && isset($_['urlGenerator']) && is_object($_['urlGenerator'])) {
                    	$customerStoreUrl = (string)$_['urlGenerator']->linkToRoute('projectcheck.customer.store');
                    }
                    ?>
                    <p class="form-hint" id="customer_id-help">
                        <?php if ($canCreateCustomer): ?>
                            <?php p($l->t('Not in the list? Type a name below, then save the project.')); ?>
                        <?php else: ?>
                            <?php p($l->t('Ask an administrator if you need a new customer.')); ?>
                        <?php endif; ?>
                    </p>
                    <?php if ($canCreateCustomer && $customerStoreUrl !== ''): ?>
                    <div class="pc-quick-customer"
                        data-store-url="<?php p($customerStoreUrl); ?>"
                        data-create-url="<?php p($customerCreateUrl); ?>">
                        <label class="pc-quick-customer__label" for="pc-quick-customer-name"><?php p($l->t('New customer name')); ?></label>
                        <div class="pc-quick-customer__row">
                            <input type="text"
                                id="pc-quick-customer-name"
                                class="form-input pc-quick-customer__input"
                                maxlength="255"
                                autocomplete="organization"
                                placeholder="<?php p($l->t('e.g. Acme GmbH')); ?>"
                                aria-describedby="pc-quick-customer-status">
                            <button type="button" id="pc-quick-customer-create" class="button pc-quick-customer__btn">
                                <span data-lucide="plus" class="lucide-icon" aria-hidden="true"></span>
                                <?php p($l->t('Add to list')); ?>
                            </button>
                        </div>
                        <p class="pc-quick-customer__status" id="pc-quick-customer-status" role="status" aria-live="polite"></p>
                        <div class="pc-quick-customer__next" id="pc-quick-customer-next" hidden>
                            <p class="pc-quick-customer__next-text" id="pc-quick-customer-next-text">
                                <?php p($l->t('Customer is selected. Press Save at the bottom when you are done.')); ?>
                            </p>
                            <button type="button"
                                class="button pc-quick-customer__goto-save"
                                id="pc-quick-customer-goto-save"
                                aria-describedby="pc-quick-customer-next-text">
                                <span data-lucide="arrow-down" class="lucide-icon" aria-hidden="true"></span>
                                <?php p($l->t('Go to Save')); ?>
                            </button>
                        </div>
                        <?php if ($customerCreateUrl !== ''): ?>
                        <p class="pc-quick-customer__more">
                            <a href="<?php p($customerCreateUrl); ?>"><?php p($l->t('Need email or address? Open full customer form')); ?></a>
                        </p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                </section>

                <?php
                $htmlLang = isset($_['htmlLang']) && is_string($_['htmlLang']) ? $_['htmlLang'] : 'en';
                $startDateIso = ($isEdit && $project->getStartDate()) ? $project->getStartDate()->format('Y-m-d') : '';
                $endDateIso = ($isEdit && $project->getEndDate()) ? $project->getEndDate()->format('Y-m-d') : '';
                ?>
                <details class="pc-advanced-details pc-section" id="pc-advanced-schedule"<?php if ($isEdit) {
                	echo ' open';
                } ?>>
                    <summary class="pc-advanced-details__summary" id="pc-project-schedule-heading"><?php p($l->t('Schedule & status')); ?></summary>
                    <div class="pc-advanced-details__body">
                    <p class="pc-section-intro"><?php p($l->t('When the project runs and whether work can be logged now. New projects start as Active.')); ?></p>
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
                    <p class="form-hint" id="status-hint"><?php p($l->t('Saved together with the rest of this form.')); ?></p>
                    <?php } ?>
                </div>
                    </div>
                </details>

                <details class="pc-advanced-details pc-section" id="pc-advanced-classification"<?php if ($isEdit) {
                	echo ' open';
                } ?>>
                    <summary class="pc-advanced-details__summary"><?php p($l->t('Classification & priority')); ?></summary>
                    <div class="pc-advanced-details__body">
                    <p class="pc-section-intro"><?php p($l->t('For reports only — this does not change how hours are priced. Defaults are fine for most projects.')); ?></p>
                <div class="form-row">
                    <div class="form-group">
                        <label for="priority"><?php p($l->t('Priority')); ?> *</label>
                        <select id="priority" name="priority" class="form-input form-select" required>
                            <option value="Low" <?php echo ($isEdit && $project->getPriority() === 'Low') || (!$isEdit && isset($_['defaultSettings']['priority']) && $_['defaultSettings']['priority'] === 'Low') ? 'selected' : ''; ?>>
                                <?php p($l->t('Low')); ?>
                            </option>
                            <option value="Medium" <?php echo ($isEdit && $project->getPriority() === 'Medium') || (!$isEdit && (!isset($_['defaultSettings']['priority']) || $_['defaultSettings']['priority'] === 'Medium')) ? 'selected' : ''; ?>>
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
                    </div>
                </details>

                <details class="pc-advanced-details pc-section pc-section--pricing" id="pc-advanced-pricing"<?php if ($isEdit) {
                	echo ' open';
                } ?>>
                    <summary class="pc-advanced-details__summary" id="pc-pricing-heading"><?php p($l->t('Pricing')); ?></summary>
                    <div class="pc-advanced-details__body">
                    <?php
                    $selectedMode = $selectedCostRateMode;
                    include __DIR__ . '/parts/pricing-mode-cards.php';
                    ?>
                    </div>
                </details>

                <!-- Budget & capacity -->
                <details class="pc-advanced-details pc-section" id="pc-advanced-budget"<?php if ($isEdit) {
                	echo ' open';
                } ?>>
                    <summary class="pc-advanced-details__summary"><?php p($l->t('Budget & capacity')); ?></summary>
                    <div class="pc-advanced-details__body">
                    <p class="pc-section-intro" id="pc-capacity-hint" data-hint-project="<?php p($l->t('Available hours are calculated from budget ÷ project hourly rate.')); ?>" data-hint-planning="<?php p($l->t('Planning rate is for capacity estimates only — billed cost uses the pricing method above.')); ?>"></p>
                    <div class="pc-pricing-gate" id="pc-pricing-gate" hidden role="status">
                        <p class="pc-pricing-gate__title" id="pc-pricing-gate-title"><?php p($l->t('Hourly rate needed for this budget')); ?></p>
                        <p class="pc-pricing-gate__text" id="pc-pricing-gate-text"><?php p($l->t('Enter a project hourly rate greater than 0 to save budget changes. You can still save name, description, dates, status, and other fields.')); ?></p>
                    </div>
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
                            placeholder="0.00"
                            data-initial-value="<?php p($isEdit ? $project->getTotalBudget() : ''); ?>">
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
                            data-initial-value="<?php p($isEdit ? $project->getHourlyRate() : ($_['defaultSettings']['hourly_rate'] ?? '')); ?>"
                            aria-describedby="pc-capacity-hint pc-pricing-gate">
                    </div>
                </div>

                <div class="form-group" id="pc-available-hours-group">
                    <label for="available_hours"><?php p($l->t('Estimated capacity (hours)')); ?></label>
                    <input type="text"
                        id="available_hours"
                        class="form-input pc-capacity-input"
                        inputmode="decimal"
                        value="<?php p($isEdit ? number_format(max(0.0, (float) $project->getAvailableHours()), 2, '.', '') : '0'); ?>"
                        placeholder="0"
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
                    </div>
                </details>

                <!-- Form Actions: exactly one primary Save -->
                <div class="form-actions pc-form-actions--sticky" id="pc-project-form-actions">
                    <p class="pc-form-actions__hint" id="pc-project-save-hint">
                        <?php p($l->t('One button saves everything on this page.')); ?>
                    </p>
                    <div class="pc-form-actions__row">
                        <button type="submit"
                            class="button primary pc-form-actions__save"
                            id="pc-project-save"
                            aria-describedby="pc-project-save-hint">
                            <span data-lucide="check" class="lucide-icon" aria-hidden="true"></span>
                            <?php p($l->t('Save project')); ?>
                        </button>
                        <a href="<?php p($_['indexUrl'] ?? '/projects'); ?>" class="button pc-form-actions__cancel">
                            <?php p($l->t('Cancel')); ?>
                        </a>
                    </div>
                </div>
            </form>
        </div>
<?php include __DIR__ . '/common/page-end.php'; ?>