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

// Server-rendered re-draft after a failed non-XHR store() (see
// ProjectController::renderProjectCreateForm): the submitted values are
// repopulated and the server's per-field messages are pinned inline —
// aria-invalid + a linked .form-error element, the same surface contract
// js/common/validation.js and customer-form.js produce client-side
// (WCAG 3.3.1/3.3.3). File inputs (project_files) cannot be repopulated;
// browsers intentionally forbid presetting them.
$draft = (isset($_['draft']) && is_array($_['draft'])) ? $_['draft'] : [];
$fieldErrors = (isset($_['fieldErrors']) && is_array($_['fieldErrors'])) ? $_['fieldErrors'] : [];
$draftOr = static function (string $key, string $fallback = '') use ($draft): string {
	if (!array_key_exists($key, $draft)) {
		return $fallback;
	}
	$v = $draft[$key];
	return is_scalar($v) ? (string) $v : $fallback;
};
$fieldErrorMessage = static function (string $key) use ($fieldErrors): ?string {
	$msg = $fieldErrors[$key] ?? null;
	return (is_string($msg) && trim($msg) !== '') ? $msg : null;
};
// Attribute suffix for a control carrying a server field error:
// aria-invalid plus the error element id merged into aria-describedby.
// Without an error it emits only the given describedby ids (or nothing).
$fieldErrorAttrs = static function (string $key, string $describedBy = '') use ($fieldErrorMessage): string {
	if ($fieldErrorMessage($key) === null) {
		return $describedBy !== '' ? ' aria-describedby="' . $describedBy . '"' : '';
	}
	$ids = trim($describedBy . ' ' . $key . '-error');
	return ' aria-invalid="true" aria-describedby="' . $ids . '"';
};
// Field keys wired with an inline error element below; any other key in the
// server's map still surfaces in the form-level alert so no message is lost.
$inlineErrorFields = [
	'name', 'short_description', 'detailed_description', 'customer_id',
	'start_date', 'end_date', 'status', 'priority', 'project_type',
	'category', 'total_budget', 'hourly_rate',
];
$unmappedFieldErrors = [];
foreach ($fieldErrors as $errorKey => $errorMessage) {
	if (!in_array($errorKey, $inlineErrorFields, true) && is_string($errorMessage) && trim($errorMessage) !== '') {
		$unmappedFieldErrors[] = $errorMessage;
	}
}
// Effective create-mode values: the submitted draft wins over the user's
// configured defaults; without a draft these equal the old defaults.
$effStatus = $draftOr('status', (string)($_['defaultSettings']['status'] ?? ''));
$effPriority = $draftOr('priority', (string)($_['defaultSettings']['priority'] ?? 'Medium'));
$effProjectType = $draftOr('project_type', (string)($_['defaultSettings']['project_type'] ?? 'client'));
$effCustomerId = array_key_exists('customer_id', $draft)
	? $draftOr('customer_id')
	: (isset($selectedCustomerId) ? (string) $selectedCustomerId : null);

$pageTitle = $isEdit ? $l->t('Edit Project') : $l->t('Create New Project');
$formAction = $_['formAction'] ?? ($isEdit ? '/projects/' . $project->getId() : '/projects');
$formMethod = $isEdit ? 'PUT' : 'POST';
$currencyCode = isset($_['orgCurrency']) && is_string($_['orgCurrency']) ? strtoupper(trim($_['orgCurrency'])) : 'EUR';
$costRateModeLocked = !empty($_['costRateModeLocked']);
$selectedCostRateMode = $isEdit
	? $project->getCostRateMode()
	: ($draftOr('cost_rate_mode') !== '' ? $draftOr('cost_rate_mode') : \OCA\ProjectCheck\Util\CostRateMode::DEFAULT);
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
        // Controller-provided alert text (failed store() re-render) takes
        // precedence; the GET banner path stays for redirects that still
        // pass ?message=error&error_text=… (e.g. update() failures).
        if (isset($_['formErrorText']) && is_string($_['formErrorText'])) {
        	$formErrorText = trim($_['formErrorText']);
        } elseif (isset($_GET['message']) && $_GET['message'] === 'error' && isset($_GET['error_text']) && is_string($_GET['error_text'])) {
        	$formErrorText = trim($_GET['error_text']);
        }
        // Cap attacker-crafted query strings (display is escaped via p()).
        if (function_exists('mb_substr') && mb_strlen($formErrorText) > 280) {
        	$formErrorText = mb_substr($formErrorText, 0, 279) . '…';
        } elseif (strlen($formErrorText) > 280) {
        	$formErrorText = substr($formErrorText, 0, 279) . '…';
        }
        ?>
        <?php if ($formErrorText !== '' || $unmappedFieldErrors !== []): ?>
        <div class="pc-form-alert pc-form-alert--error" role="alert" id="pc-project-form-error" tabindex="-1">
            <span class="pc-form-alert__icon" data-lucide="alert-circle" aria-hidden="true"></span>
            <div class="pc-form-alert__body">
                <p class="pc-form-alert__title"><?php p($l->t('Could not save')); ?></p>
                <p class="pc-form-alert__text"><?php p($formErrorText !== '' ? $formErrorText : $l->t('Please check the highlighted fields.')); ?></p>
                <?php if ($unmappedFieldErrors !== []): ?>
                <ul class="pc-form-alert__list">
                    <?php foreach ($unmappedFieldErrors as $unmappedError): ?>
                    <li><?php p($unmappedError); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
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
                        value="<?php p($isEdit ? $project->getName() : $draftOr('name')); ?>"
                        maxlength="100"
                        required<?php echo $fieldErrorAttrs('name'); ?>
                        placeholder="<?php p($l->t('Enter project name')); ?>">
                    <?php if (($fieldError = $fieldErrorMessage('name')) !== null): ?>
                    <p class="form-error" id="name-error" role="alert"><?php p($fieldError); ?></p>
                    <?php endif; ?>
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
                                rows="2"<?php echo $fieldErrorAttrs('short_description', 'short_description-help short_description-count-wrap'); ?>
                                placeholder="<?php p($l->t('Optional — leave blank to use the project name')); ?>"><?php p($isEdit ? $project->getShortDescription() : $draftOr('short_description')); ?></textarea>
                            <?php if (($fieldError = $fieldErrorMessage('short_description')) !== null): ?>
                            <p class="form-error" id="short_description-error" role="alert"><?php p($fieldError); ?></p>
                            <?php endif; ?>
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
                                rows="5"<?php echo $fieldErrorAttrs('detailed_description'); ?>
                                placeholder="<?php p($l->t('Detailed project description (max 2000 characters)')); ?>"><?php p($isEdit ? $project->getDetailedDescription() : $draftOr('detailed_description')); ?></textarea>
                            <?php if (($fieldError = $fieldErrorMessage('detailed_description')) !== null): ?>
                            <p class="form-error" id="detailed_description-error" role="alert"><?php p($fieldError); ?></p>
                            <?php endif; ?>
                            <div class="char-count" aria-live="polite">
                                <span id="detailed_description-count">0</span>/2000
                            </div>
                        </div>
                    </div>
                </details>

                <div class="form-group">
                    <label for="customer_id"><?php p($l->t('Customer')); ?> *</label>
                    <select id="customer_id" name="customer_id" class="form-input form-select" required<?php echo $fieldErrorAttrs('customer_id', 'customer_id-help' . (!empty($_['canCreateCustomer']) ? ' pc-quick-customer-status' : '')); ?>>
                        <option value=""><?php p($l->t('Select a customer')); ?></option>
                        <?php if (isset($customers) && is_array($customers)): ?>
                            <?php foreach ($customers as $customer): ?>
                                <option value="<?php p($customer['id']); ?>"
                                    <?php
                                    $selected = false;
                                    if ($isEdit && $project->getCustomerId() == $customer['id']) {
                                        $selected = true;
                                    } elseif (!$isEdit && $effCustomerId !== null && $effCustomerId !== '' && $effCustomerId == $customer['id']) {
                                        $selected = true;
                                    }
                                    echo $selected ? 'selected' : '';
                                    ?>>
                                    <?php p($customer['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                    <?php if (($fieldError = $fieldErrorMessage('customer_id')) !== null): ?>
                    <p class="form-error" id="customer_id-error" role="alert"><?php p($fieldError); ?></p>
                    <?php endif; ?>
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
                $startDateIso = ($isEdit && $project->getStartDate()) ? $project->getStartDate()->format('Y-m-d') : $draftOr('start_date');
                $endDateIso = ($isEdit && $project->getEndDate()) ? $project->getEndDate()->format('Y-m-d') : $draftOr('end_date');
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
                            autocomplete="off"<?php echo $fieldErrorAttrs('start_date', 'project-dates-hint'); ?>>
                        <?php if (($fieldError = $fieldErrorMessage('start_date')) !== null): ?>
                        <p class="form-error" id="start_date-error" role="alert"><?php p($fieldError); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="end_date"><?php p($l->t('End Date')); ?></label>
                        <input type="date"
                            id="end_date"
                            name="end_date"
                            class="form-input"
                            lang="<?php p($htmlLang); ?>"
                            value="<?php p($endDateIso); ?>"
                            autocomplete="off"<?php echo $fieldErrorAttrs('end_date', 'project-dates-hint'); ?>>
                        <?php if (($fieldError = $fieldErrorMessage('end_date')) !== null): ?>
                        <p class="form-error" id="end_date-error" role="alert"><?php p($fieldError); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <p class="form-hint" id="project-dates-hint"><?php p($l->t('End date must be on or after the start date when both are set.')); ?></p>

                <div class="form-group">
                    <label for="status"><?php p($l->t('Status')); ?> *</label>
                    <select id="status" name="status" class="form-input form-select" required<?php echo $fieldErrorAttrs('status'); ?>>
                        <option value="Active" <?php echo ($isEdit && $project->getStatus() === 'Active') || (!$isEdit && $effStatus === 'Active') ? 'selected' : ''; ?>>
                            <?php p($l->t('Active')); ?>
                        </option>
                        <option value="On Hold" <?php echo ($isEdit && $project->getStatus() === 'On Hold') || (!$isEdit && $effStatus === 'On Hold') ? 'selected' : ''; ?>>
                            <?php p($l->t('On Hold')); ?>
                        </option>
                        <option value="Completed" <?php echo ($isEdit && $project->getStatus() === 'Completed') || (!$isEdit && $effStatus === 'Completed') ? 'selected' : ''; ?>>
                            <?php p($l->t('Completed')); ?>
                        </option>
                        <option value="Cancelled" <?php echo ($isEdit && $project->getStatus() === 'Cancelled') || (!$isEdit && $effStatus === 'Cancelled') ? 'selected' : ''; ?>>
                            <?php p($l->t('Cancelled')); ?>
                        </option>
                        <?php if ($isEdit) { ?>
                        <option value="Archived" <?php echo $project->getStatus() === 'Archived' ? 'selected' : ''; ?>>
                            <?php p($l->t('Archived')); ?>
                        </option>
                        <?php } ?>
                    </select>
                    <?php if (($fieldError = $fieldErrorMessage('status')) !== null): ?>
                    <p class="form-error" id="status-error" role="alert"><?php p($fieldError); ?></p>
                    <?php endif; ?>
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
                        <select id="priority" name="priority" class="form-input form-select" required<?php echo $fieldErrorAttrs('priority'); ?>>
                            <option value="Low" <?php echo ($isEdit && $project->getPriority() === 'Low') || (!$isEdit && $effPriority === 'Low') ? 'selected' : ''; ?>>
                                <?php p($l->t('Low')); ?>
                            </option>
                            <option value="Medium" <?php echo ($isEdit && $project->getPriority() === 'Medium') || (!$isEdit && $effPriority === 'Medium') ? 'selected' : ''; ?>>
                                <?php p($l->t('Medium')); ?>
                            </option>
                            <option value="High" <?php echo ($isEdit && $project->getPriority() === 'High') || (!$isEdit && $effPriority === 'High') ? 'selected' : ''; ?>>
                                <?php p($l->t('High')); ?>
                            </option>
                            <option value="Critical" <?php echo ($isEdit && $project->getPriority() === 'Critical') || (!$isEdit && $effPriority === 'Critical') ? 'selected' : ''; ?>>
                                <?php p($l->t('Critical')); ?>
                            </option>
                        </select>
                        <?php if (($fieldError = $fieldErrorMessage('priority')) !== null): ?>
                        <p class="form-error" id="priority-error" role="alert"><?php p($fieldError); ?></p>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="project_type"><?php p($l->t('Project Type')); ?> *</label>
                        <select id="project_type" name="project_type" class="form-input form-select" required<?php echo $fieldErrorAttrs('project_type'); ?>>
                            <option value="client" <?php echo ($isEdit && $project->getProjectType() === 'client') || (!$isEdit && $effProjectType === 'client') ? 'selected' : ''; ?>>
                                <?php p($l->t('Client Project')); ?>
                            </option>
                            <option value="admin" <?php echo ($isEdit && $project->getProjectType() === 'admin') || (!$isEdit && $effProjectType === 'admin') ? 'selected' : ''; ?>>
                                <?php p($l->t('Administrative')); ?>
                            </option>
                            <option value="sales" <?php echo ($isEdit && $project->getProjectType() === 'sales') || (!$isEdit && $effProjectType === 'sales') ? 'selected' : ''; ?>>
                                <?php p($l->t('Sales & Marketing')); ?>
                            </option>
                            <option value="customer" <?php echo ($isEdit && $project->getProjectType() === 'customer') || (!$isEdit && $effProjectType === 'customer') ? 'selected' : ''; ?>>
                                <?php p($l->t('Customer Support')); ?>
                            </option>
                            <option value="product" <?php echo ($isEdit && $project->getProjectType() === 'product') || (!$isEdit && $effProjectType === 'product') ? 'selected' : ''; ?>>
                                <?php p($l->t('Product Development')); ?>
                            </option>
                            <option value="meeting" <?php echo ($isEdit && $project->getProjectType() === 'meeting') || (!$isEdit && $effProjectType === 'meeting') ? 'selected' : ''; ?>>
                                <?php p($l->t('Meetings & Overhead')); ?>
                            </option>
                            <option value="internal" <?php echo ($isEdit && $project->getProjectType() === 'internal') || (!$isEdit && $effProjectType === 'internal') ? 'selected' : ''; ?>>
                                <?php p($l->t('Internal Project')); ?>
                            </option>
                            <option value="research" <?php echo ($isEdit && $project->getProjectType() === 'research') || (!$isEdit && $effProjectType === 'research') ? 'selected' : ''; ?>>
                                <?php p($l->t('Research & Development')); ?>
                            </option>
                            <option value="training" <?php echo ($isEdit && $project->getProjectType() === 'training') || (!$isEdit && $effProjectType === 'training') ? 'selected' : ''; ?>>
                                <?php p($l->t('Training & Education')); ?>
                            </option>
                            <option value="other" <?php echo ($isEdit && $project->getProjectType() === 'other') || (!$isEdit && $effProjectType === 'other') ? 'selected' : ''; ?>>
                                <?php p($l->t('Other')); ?>
                            </option>
                        </select>
                        <?php if (($fieldError = $fieldErrorMessage('project_type')) !== null): ?>
                        <p class="form-error" id="project_type-error" role="alert"><?php p($fieldError); ?></p>
                        <?php endif; ?>
                        <small class="form-help"><?php p($l->t('Select the type of project to categorize it for productivity analysis')); ?></small>
                    </div>

                    <div class="form-group">
                        <label for="category"><?php p($l->t('Category')); ?></label>
                        <input type="text"
                            id="category"
                            name="category"
                            class="form-input"
                            value="<?php p($isEdit ? $project->getCategory() : $draftOr('category')); ?>"<?php echo $fieldErrorAttrs('category'); ?>
                            placeholder="<?php p($l->t('Project category (optional)')); ?>">
                        <?php if (($fieldError = $fieldErrorMessage('category')) !== null): ?>
                        <p class="form-error" id="category-error" role="alert"><?php p($fieldError); ?></p>
                        <?php endif; ?>
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
                            value="<?php p($isEdit ? $project->getTotalBudget() : $draftOr('total_budget')); ?>"
                            placeholder="0.00"
                            data-initial-value="<?php p($isEdit ? $project->getTotalBudget() : $draftOr('total_budget')); ?>"<?php echo $fieldErrorAttrs('total_budget'); ?>>
                        <?php if (($fieldError = $fieldErrorMessage('total_budget')) !== null): ?>
                        <p class="form-error" id="total_budget-error" role="alert"><?php p($fieldError); ?></p>
                        <?php endif; ?>
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
                            value="<?php p($isEdit ? $project->getHourlyRate() : $draftOr('hourly_rate', (string)($_['defaultSettings']['hourly_rate'] ?? ''))); ?>"
                            placeholder="0.00"
                            data-initial-value="<?php p($isEdit ? $project->getHourlyRate() : $draftOr('hourly_rate', (string)($_['defaultSettings']['hourly_rate'] ?? ''))); ?>"<?php echo $fieldErrorAttrs('hourly_rate', 'pc-capacity-hint pc-pricing-gate'); ?>>
                        <?php if (($fieldError = $fieldErrorMessage('hourly_rate')) !== null): ?>
                        <p class="form-error" id="hourly_rate-error" role="alert"><?php p($fieldError); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-group" id="pc-available-hours-group">
                    <label for="available_hours"><?php p($l->t('Estimated capacity (hours)')); ?></label>
                    <input type="text"
                        id="available_hours"
                        class="form-input pc-capacity-input"
                        inputmode="decimal"
                        value="<?php p($isEdit ? number_format(max(0.0, (float) $project->getAvailableHours()), 2, '.', '') : $draftOr('available_hours', '0')); ?>"
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