<?php

/**
 * Projects template for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

use OCP\Util;

Util::addScript('projectcheck', 'common/export-menu');
Util::addScript('projectcheck', 'projects');
Util::addStyle('projectcheck', 'projects');
Util::addStyle('projectcheck', 'navigation');
Util::addStyle('projectcheck', 'common/list-table');
// Last: shared index section chrome (matches detail Key figures headers).
Util::addStyle('projectcheck', 'common/list-layout');
$fmt = $_['fmt'] ?? null;
$currencyCode = isset($_['orgCurrency']) && is_string($_['orgCurrency']) ? strtoupper(trim($_['orgCurrency'])) : 'EUR';
if (preg_match('/^[A-Z]{3}$/', $currencyCode) !== 1) {
	$currencyCode = 'EUR';
}
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<?php
$pageId = 'projects';
$pageTitle = $l->t('Projects');
$pageHelp = $l->t('Manage your projects and track their progress');
ob_start(); ?>
                    <?php if (!empty($_['canCreateProject'])): ?>
                    <a href="<?php p($_['createUrl']); ?>" class="button primary">
                        <span data-lucide="plus" class="lucide-icon" aria-hidden="true"></span>
                        <?php p($l->t('Create New Project')); ?>
                    </a>
                    <?php endif; ?>
<?php
$pageHeaderActionsHtml = ob_get_clean();
$pageHeaderActionsLabel = $l->t('Page actions');
include __DIR__ . '/common/page-start.php';
?>

        <!-- Success/Error Messages -->
        <?php if (isset($_GET['message']) && $_GET['message'] === 'success'): ?>
            <div class="notice notice-success">
                <i class="icon icon-checkmark"></i>
                <span>
                    <?php if (isset($_GET['project_name'])): ?>
                        <?php p($l->t('Project "%s" was created successfully!', [$_GET['project_name']])); ?>
                    <?php elseif (isset($_GET['deleted'])): ?>
                        <?php p($l->t('Project was deleted successfully!')); ?>
                    <?php elseif (isset($_GET['status_updated'])): ?>
                        <?php p($l->t('Project status was updated successfully!')); ?>
                    <?php else: ?>
                        <?php p($l->t('Operation completed successfully!')); ?>
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['message']) && $_GET['message'] === 'error' && isset($_GET['error_text'])): ?>
            <div class="notice notice-error">
                <i class="icon icon-error"></i>
                <span><?php p($l->t('Error: %s', [$_GET['error_text']])); ?></span>
            </div>
        <?php endif; ?>

        <!-- Project Statistics Overview -->
        <section class="section pc-stats-panel pc-section stats-overview-section" aria-labelledby="projects-stats-title">
            <div class="section-header">
                <h3 id="projects-stats-title"><i data-lucide="bar-chart-3" class="lucide-icon primary" aria-hidden="true"></i> <?php p($l->t('Project statistics')); ?></h3>
                <p><?php p($l->t('Overview of your project portfolio and performance')); ?></p>
            </div>
            <div class="section-content">
            <ul class="pc-stats-grid" role="list">
                <li class="pc-stat-card">
                    <span class="pc-stat-card__icon" aria-hidden="true"><i data-lucide="folder" class="lucide-icon"></i></span>
                    <div class="pc-stat-card__body">
                        <div class="pc-stat-card__value"><?php p($_['stats']['totalProjects'] ?? 0); ?></div>
                        <div class="pc-stat-card__label"><?php p($l->t('Projects')); ?></div>
                        <div class="pc-stat-card__detail">
                            <span><?php p($_['stats']['activeProjects'] ?? 0); ?> <?php p($l->t('active')); ?></span>
                        </div>
                    </div>
                </li>

                <li class="pc-stat-card">
                    <span class="pc-stat-card__icon" aria-hidden="true"><i data-lucide="users" class="lucide-icon"></i></span>
                    <div class="pc-stat-card__body">
                        <div class="pc-stat-card__value"><?php p($_['stats']['totalCustomers'] ?? 0); ?></div>
                        <div class="pc-stat-card__label"><?php p($l->t('Customers')); ?></div>
                        <div class="pc-stat-card__detail">
                            <span><?php p($l->t('active')); ?></span>
                        </div>
                    </div>
                </li>

                <li class="pc-stat-card">
                    <span class="pc-stat-card__icon" aria-hidden="true"><i data-lucide="clock" class="lucide-icon"></i></span>
                    <div class="pc-stat-card__body">
                        <div class="pc-stat-card__value"><?php p($_['stats']['totalHours'] ?? 0); ?>h</div>
                        <div class="pc-stat-card__label"><?php p($l->t('Hours')); ?></div>
                        <div class="pc-stat-card__detail">
                            <span><?php p($l->t('total')); ?></span>
                        </div>
                    </div>
                </li>

                <li class="pc-stat-card">
                    <span class="pc-stat-card__icon" aria-hidden="true"><i data-lucide="euro" class="lucide-icon"></i></span>
                    <div class="pc-stat-card__body">
                        <div class="pc-stat-card__value"><?php p($fmt ? $fmt->currency((float)($_['stats']['totalBudget'] ?? 0)) : $currencyCode . ' ' . number_format((float)($_['stats']['totalBudget'] ?? 0), 0)); ?></div>
                        <div class="pc-stat-card__label"><?php p($l->t('Budget')); ?></div>
                        <div class="pc-stat-card__detail">
                            <span><?php p($_['stats']['consumptionPercentage'] ?? 0); ?>% <?php p($l->t('used')); ?></span>
                        </div>
                    </div>
                </li>
            </ul>
            </div>
        </section>

        <!-- Search, filter, and project list (one panel) -->
        <?php
        $colName = $l->t('Name');
        $colCustomer = $l->t('Customer');
        $colType = $l->t('Type');
        $colStatus = $l->t('Status');
        $colBudget = $l->t('Budget');
        $colProgress = $l->t('Progress');
        $colInvoicing = $l->t('Invoicing');
        $colActions = $l->t('Actions');
        $settlementInfoByProject = $_['settlementInfoByProject'] ?? [];
        ?>
        <div class="section pc-list-panel pc-section" aria-labelledby="pc-projects-list-heading">
            <div class="section-header">
                <h3 id="pc-projects-list-heading"><i data-lucide="folder" class="lucide-icon primary" aria-hidden="true"></i> <?php p($l->t('Project list')); ?></h3>
                <p><?php p($l->t('Search and filter')); ?></p>
            </div>
            <div class="pc-list-panel__toolbar">
            <div class="filters-container">
                <div class="search-input-wrapper">
                    <span class="pc-list-search-icon" aria-hidden="true"><i data-lucide="search" class="lucide-icon"></i></span>
                    <input type="search" id="project-search" class="search-input"
                        placeholder="<?php p($l->t('Search projects…')); ?>"
                        value="<?php p($_['filters']['search'] ?? ''); ?>"
                        aria-label="<?php p($l->t('Search projects')); ?>"
                        autocomplete="off">
                </div>

                <div class="filters-row">
                    <select id="status-filter" aria-label="<?php p($l->t('Filter by status')); ?>">
                        <option value="all" <?php if (($_['filters']['status'] ?? '') === 'all') echo 'selected'; ?>><?php p($l->t('All statuses')); ?></option>
                        <option value="Active" <?php if (($_['filters']['status'] ?? 'Active') === 'Active') echo 'selected'; ?>><?php p($l->t('Active')); ?></option>
                        <option value="On Hold" <?php if (($_['filters']['status'] ?? '') === 'On Hold') echo 'selected'; ?>><?php p($l->t('On Hold')); ?></option>
                        <option value="Completed" <?php if (($_['filters']['status'] ?? '') === 'Completed') echo 'selected'; ?>><?php p($l->t('Completed')); ?></option>
                        <option value="Cancelled" <?php if (($_['filters']['status'] ?? '') === 'Cancelled') echo 'selected'; ?>><?php p($l->t('Cancelled')); ?></option>
                        <option value="Archived" <?php if (($_['filters']['status'] ?? '') === 'Archived') echo 'selected'; ?>><?php p($l->t('Archived')); ?></option>
                    </select>

                    <select id="priority-filter" aria-label="<?php p($l->t('Filter by priority')); ?>">
                        <option value=""><?php p($l->t('All Priorities')); ?></option>
                        <option value="Low" <?php if (($_['filters']['priority'] ?? '') === 'Low') echo 'selected'; ?>><?php p($l->t('Low')); ?></option>
                        <option value="Medium" <?php if (($_['filters']['priority'] ?? '') === 'Medium') echo 'selected'; ?>><?php p($l->t('Medium')); ?></option>
                        <option value="High" <?php if (($_['filters']['priority'] ?? '') === 'High') echo 'selected'; ?>><?php p($l->t('High')); ?></option>
                        <option value="Critical" <?php if (($_['filters']['priority'] ?? '') === 'Critical') echo 'selected'; ?>><?php p($l->t('Critical')); ?></option>
                    </select>

                    <select id="project-type-filter" aria-label="<?php p($l->t('Filter by project type')); ?>">
                        <option value=""><?php p($l->t('All Project Types')); ?></option>
                        <option value="client" <?php if (($_['filters']['project_type'] ?? '') === 'client') echo 'selected'; ?>><?php p($l->t('Client Project')); ?></option>
                        <option value="admin" <?php if (($_['filters']['project_type'] ?? '') === 'admin') echo 'selected'; ?>><?php p($l->t('Administrative')); ?></option>
                        <option value="sales" <?php if (($_['filters']['project_type'] ?? '') === 'sales') echo 'selected'; ?>><?php p($l->t('Sales & Marketing')); ?></option>
                        <option value="customer" <?php if (($_['filters']['project_type'] ?? '') === 'customer') echo 'selected'; ?>><?php p($l->t('Customer Support')); ?></option>
                        <option value="product" <?php if (($_['filters']['project_type'] ?? '') === 'product') echo 'selected'; ?>><?php p($l->t('Product Development')); ?></option>
                        <option value="meeting" <?php if (($_['filters']['project_type'] ?? '') === 'meeting') echo 'selected'; ?>><?php p($l->t('Meetings & Overhead')); ?></option>
                        <option value="internal" <?php if (($_['filters']['project_type'] ?? '') === 'internal') echo 'selected'; ?>><?php p($l->t('Internal Project')); ?></option>
                        <option value="research" <?php if (($_['filters']['project_type'] ?? '') === 'research') echo 'selected'; ?>><?php p($l->t('Research & Development')); ?></option>
                        <option value="training" <?php if (($_['filters']['project_type'] ?? '') === 'training') echo 'selected'; ?>><?php p($l->t('Training & Education')); ?></option>
                        <option value="other" <?php if (($_['filters']['project_type'] ?? '') === 'other') echo 'selected'; ?>><?php p($l->t('Other')); ?></option>
                    </select>

                    <select id="customer-filter" aria-label="<?php p($l->t('Filter by customer')); ?>">
                        <option value=""><?php p($l->t('All Customers')); ?></option>
                        <?php if (!empty($_['customers'])): ?>
                            <?php foreach ($_['customers'] as $customer): ?>
                                <option value="<?php p($customer['id']); ?>" <?php if (($_['filters']['customer_id'] ?? '') == $customer['id']) echo 'selected'; ?>>
                                    <?php p($customer['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>

                    <?php $settlementFilterValue = (string)($_['filters']['settlement'] ?? ''); ?>
                    <select id="settlement-filter" aria-label="<?php p($l->t('Filter by settlement')); ?>">
                        <option value="all" <?php if ($settlementFilterValue === '' || $settlementFilterValue === 'all') echo 'selected'; ?>><?php p($l->t('Settlement: all')); ?></option>
                        <option value="outstanding" <?php if ($settlementFilterValue === 'outstanding') echo 'selected'; ?>><?php p($l->t('Not yet paid')); ?></option>
                        <option value="open" <?php if ($settlementFilterValue === 'open') echo 'selected'; ?>><?php p($l->t('Open')); ?></option>
                        <option value="partial" <?php if ($settlementFilterValue === 'partial') echo 'selected'; ?>><?php p($l->t('Partially settled')); ?></option>
                        <option value="awaiting_payment" <?php if ($settlementFilterValue === 'awaiting_payment') echo 'selected'; ?>><?php p($l->t('Awaiting payment')); ?></option>
                        <option value="paid" <?php if ($settlementFilterValue === 'paid') echo 'selected'; ?>><?php p($l->t('Paid')); ?></option>
                        <option value="n_a" <?php if ($settlementFilterValue === 'n_a') echo 'selected'; ?>><?php p($l->t('Nothing to invoice')); ?></option>
                    </select>

                    <button id="apply-filters" class="button primary" type="button">
                        <span data-lucide="search" class="lucide-icon" aria-hidden="true"></span>
                        <?php p($l->t('Apply Filters')); ?>
                    </button>
                    <button id="clear-filters" class="button secondary" type="button">
                        <span data-lucide="rotate-ccw" class="lucide-icon" aria-hidden="true"></span>
                        <?php p($l->t('Clear Filters')); ?>
                    </button>
                    <?php
                    $exportUrl = (string)($_['exportUrl'] ?? '');
                    if ($exportUrl === '' && isset($_['urlGenerator']) && is_object($_['urlGenerator'])) {
                    	$exportUrl = (string)$_['urlGenerator']->linkToRoute('projectcheck.project.export');
                    }
                    $exportEntityLabel = 'projects';
                    $exportFilterKeys = 'search,status,priority,project_type,customer_id,settlement';
                    $exportSuccessMsg = 'Exported {count} projects';
                    $exportIncludeSort = true;
                    $exportMenuId = 'pc-export-menu-projects';
                    include __DIR__ . '/parts/export-menu.php';
                    ?>
                </div>
            </div>
            </div>

            <?php if (empty($_['projects'])): ?>
                <div class="emptycontent">
                    <div class="icon-folder"></div>
                    <h2><?php p($l->t('No projects found')); ?></h2>
                    <p><?php p($l->t('Create your first project to get started!')); ?></p>
                </div>
            <?php else: ?>
            <div class="pc-list-table-wrap" tabindex="0" role="region" aria-label="<?php p($l->t('Projects')); ?>">
                <table class="grid projects-table pc-data-table">
                    <thead>
                        <tr>
                            <?php
                            $currentSort = $_['sort'] ?? 'remaining_budget';
                            $currentDirection = $_['direction'] ?? 'asc';
                            $sortableHeaders = [
                                'name' => $l->t('Name'),
                                'customer' => $l->t('Customer'),
                                'type' => $l->t('Type'),
                                'status' => $l->t('Status'),
                                'remaining_budget' => $l->t('Budget'),
                                'progress' => $l->t('Progress'),
                            ];
                            foreach ($sortableHeaders as $sortKey => $label):
                                $isActive = ($currentSort === $sortKey);
                                $dir = $isActive ? $currentDirection : 'desc';
                            ?>
                            <th class="sortable" data-sort="<?php p($sortKey); ?>" data-direction="<?php p($dir); ?>" role="columnheader" scope="col" tabindex="0" title="<?php p($l->t('Click to sort')); ?>" aria-sort="<?php echo $isActive ? ($currentDirection === 'asc' ? 'ascending' : 'descending') : 'none'; ?>">
                                <?php p($label); ?>
                                <?php if ($isActive): ?>
                                    <span class="sort-indicator" aria-hidden="true"><?php echo $dir === 'asc' ? '▲' : '▼'; ?></span>
                                <?php endif; ?>
                            </th>
                            <?php endforeach; ?>
                            <th scope="col" class="col-invoicing"><?php p($colInvoicing); ?></th>
                            <th scope="col" class="col-actions"><?php p($colActions); ?></th>
                        </tr>
                    </thead>
                    <tbody id="projects-tbody">
                        <?php foreach ($_['projects'] as $projectData): ?>
                            <?php
                            $project = $projectData['project'] ?? $projectData;
                            $budgetInfo = $projectData['budgetInfo'] ?? null;
                            $canEditRow = (bool)($projectData['canEdit'] ?? true);
                            ?>
                            <tr class="project-row <?php if ($budgetInfo): ?>budget-status-<?php p($budgetInfo['warning_level']); ?><?php endif; ?>"
                                data-project-id="<?php p($project->getId()); ?>">
                                <td class="project-name-cell" data-label="<?php p($colName); ?>">
                                    <div class="project-name-content">
                                        <a href="<?php p(str_replace('PROJECT_ID', $project->getId(), $_['showUrl'])); ?>" class="project-title">
                                            <?php p($project->getName()); ?>
                                        </a>
                                        <div class="project-badges">
                                            <span class="priority-badge priority-<?php echo strtolower($project->getPriority()); ?>">
                                                <?php p($l->t($project->getPriority())); ?>
                                            </span>
                                            <?php if ($budgetInfo): ?>
                                                <?php if ($budgetInfo['consumption_percentage'] >= 100): ?>
                                                    <span class="budget-warning-badge critical" title="<?php p($l->t('Budget Exceeded')); ?>">
                                                        ⚠️ <?php p($l->t('Over Budget')); ?>
                                                    </span>
                                                <?php elseif ($budgetInfo['warning_level'] === 'critical'): ?>
                                                    <span class="budget-warning-badge critical" title="<?php p($l->t('Budget Critical')); ?>">
                                                        ⚠️ <?php p($l->t('Critical')); ?>
                                                    </span>
                                                <?php elseif ($budgetInfo['warning_level'] === 'warning'): ?>
                                                    <span class="budget-warning-badge warning" title="<?php p($l->t('Budget Warning')); ?>">
                                                        ⚠️ <?php p($l->t('Warning')); ?>
                                                    </span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td data-label="<?php p($colCustomer); ?>"><?php p($project->getCustomerName() ?? $l->t('N/A')); ?></td>
                                <td data-label="<?php p($colType); ?>">
                                    <?php
                                    // Icon mapping for project types
                                    $iconMapping = [
                                        'client' => '👥',
                                        'admin' => '⚙️',
                                        'sales' => '📈',
                                        'customer' => '🎧',
                                        'product' => '💻',
                                        'meeting' => '🤝',
                                        'internal' => '🏢',
                                        'research' => '🔬',
                                        'training' => '🎓',
                                        'other' => '📋'
                                    ];
                                    $projectType = strtolower($project->getProjectType());
                                    $icon = $iconMapping[$projectType] ?? '📋';
                                    $displayName = $project->getProjectTypeDisplayName();
                                    ?>
                                    <span class="project-type-icon"
                                        data-project-type="<?php p($projectType); ?>"
                                        title="<?php p($displayName); ?>">
                                        <?php p($icon); ?>
                                    </span>
                                </td>
                                <td data-label="<?php p($colStatus); ?>">
                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $project->getStatus())); ?>">
                                        <?php p($l->t($project->getStatus())); ?>
                                    </span>
                                </td>
                                <td class="budget-cell" data-label="<?php p($colBudget); ?>">
                                    <?php if ($budgetInfo): ?>
                                        <div class="budget-info-compact">
                                            <div class="budget-main">
                                                <div class="budget-line">
                                                    <span class="budget-label"><?php p($l->t('Total Budget:')); ?></span>
                                                    <span class="budget-total"><?php p($fmt ? $fmt->currency((float)$budgetInfo['total_budget']) : $currencyCode . ' ' . number_format((float)$budgetInfo['total_budget'], 2)); ?></span>
                                                </div>
                                                <div class="budget-line">
                                                    <span class="budget-label"><?php p($l->t('Used:')); ?></span>
                                                    <span class="budget-used"><?php p($fmt ? $fmt->currency((float)$budgetInfo['used_budget']) : $currencyCode . ' ' . number_format((float)$budgetInfo['used_budget'], 2)); ?></span>
                                                </div>
                                                <div class="budget-line">
                                                    <span class="budget-label"><?php p($l->t('Remaining:')); ?></span>
                                                    <span class="budget-remaining <?php p($budgetInfo['warning_level']); ?>">
                                                        <?php p($fmt ? $fmt->currency((float)$budgetInfo['remaining_budget']) : $currencyCode . ' ' . number_format((float)$budgetInfo['remaining_budget'], 2)); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="budget-secondary">
                                                <span class="budget-percentage <?php p($budgetInfo['warning_level']); ?>">
                                                    <?php p(round($budgetInfo['consumption_percentage'])); ?>% <?php p($l->t('used')); ?>
                                                </span>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <div class="budget-info-compact">
                                            <div class="budget-main">
                                                <div class="budget-line">
                                                    <span class="budget-label"><?php p($l->t('Total Budget:')); ?></span>
                                                    <span class="budget-total"><?php p($fmt ? $fmt->currency((float)($project->getTotalBudget() ?? 0)) : $currencyCode . ' ' . number_format((float)($project->getTotalBudget() ?? 0), 2)); ?></span>
                                                </div>
                                                <div class="budget-line">
                                                    <span class="budget-label"><?php p($l->t('Used:')); ?></span>
                                                    <span class="budget-used"><?php p($fmt ? $fmt->currency(0) : $currencyCode . ' 0.00'); ?></span>
                                                </div>
                                                <div class="budget-line">
                                                    <span class="budget-label"><?php p($l->t('Remaining:')); ?></span>
                                                    <span class="budget-remaining"><?php p($fmt ? $fmt->currency((float)($project->getTotalBudget() ?? 0)) : $currencyCode . ' ' . number_format((float)($project->getTotalBudget() ?? 0), 2)); ?></span>
                                                </div>
                                            </div>
                                            <div class="budget-secondary">
                                                <span class="budget-percentage">0% <?php p($l->t('used')); ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="progress-cell" data-label="<?php p($colProgress); ?>">
                                    <?php if ($budgetInfo): ?>
                                        <div class="progress-info">
                                            <div class="budget-progress-bar compact">
                                                <div class="budget-progress-fill <?php p($budgetInfo['warning_level']); ?>"
                                                    style="width: <?php p(min(100, $budgetInfo['consumption_percentage'])); ?>%"></div>
                                            </div>
                                            <span class="hours-logged">
                                                <?php p(number_format($budgetInfo['used_hours'], 1)); ?>h <?php p($l->t('logged')); ?>
                                                <?php if (!empty($budgetInfo['hours_estimated']) && ($budgetInfo['available_hours'] ?? 0) > 0): ?>
                                                    <span class="hours-capacity-estimate" title="<?php p($l->t('Estimated capacity based on planning or project rate')); ?>">
                                                        · <?php p($l->t('%sh remaining (estimate)', [number_format((float) $budgetInfo['remaining_hours'], 1, '.', '')])); ?>
                                                    </span>
                                                <?php endif; ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <div class="progress-info">
                                            <div class="budget-progress-bar compact">
                                                <div class="budget-progress-fill" style="width: 0%"></div>
                                            </div>
                                            <span class="hours-logged">0h <?php p($l->t('logged')); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="col-invoicing" data-label="<?php p($colInvoicing); ?>">
                                    <?php $rowSettlement = $settlementInfoByProject[(int)$project->getId()] ?? null; ?>
                                    <?php if ($rowSettlement !== null): ?>
                                        <div class="pc-invoicing-cell">
                                            <?php
                                            $chipKind = 'posture';
                                            $chipValue = (string)($rowSettlement['posture'] ?? 'n_a');
                                            include __DIR__ . '/parts/settlement-chip.php';
                                            ?>
                                            <?php
                                            $progress = is_array($rowSettlement['progress'] ?? null) ? $rowSettlement['progress'] : [];
                                            $progressVariant = 'compact';
                                            $progressId = 'pc-proj-stl-' . (int)$project->getId();
                                            include __DIR__ . '/parts/settlement-progress.php';
                                            ?>
                                            <?php if ((float)($rowSettlement['outstanding_hours'] ?? 0) > 0): ?>
                                                <span class="pc-invoicing-cell__outstanding">
                                                    <?php p($l->t('Not yet paid: %1$s h · %2$s', [
                                                        number_format((float)$rowSettlement['outstanding_hours'], 2),
                                                        $fmt ? $fmt->currency((float)$rowSettlement['outstanding_amount']) : $currencyCode . ' ' . number_format((float)$rowSettlement['outstanding_amount'], 2),
                                                    ])); ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="col-actions" data-label="<?php p($colActions); ?>">
                                    <div class="action-items" role="group" aria-label="<?php p($l->t('Project actions')); ?>">
                                        <a href="<?php p(str_replace('PROJECT_ID', $project->getId(), $_['showUrl'])); ?>"
                                            class="action-item action-item--view"
                                            title="<?php p($l->t('View project')); ?>"
                                            aria-label="<?php p($l->t('View project %s', [$project->getName()])); ?>">
                                            <span data-lucide="eye" class="lucide-icon" aria-hidden="true"></span>
                                        </a>
                                        <?php if ($canEditRow) { ?>
                                        <a href="<?php p(str_replace('PROJECT_ID', $project->getId(), $_['editUrl'])); ?>"
                                            class="action-item action-item--edit"
                                            title="<?php p($l->t('Edit project')); ?>"
                                            aria-label="<?php p($l->t('Edit project %s', [$project->getName()])); ?>">
                                            <span data-lucide="edit" class="lucide-icon" aria-hidden="true"></span>
                                        </a>
                                        <?php } ?>
                                        <?php if (!empty($projectData['canEdit'])): ?>
                                        <button type="button" class="action-item action-item--danger delete-project-btn"
                                            data-project-id="<?php p($project->getId()); ?>"
                                            data-project-name="<?php p($project->getName()); ?>"
                                            title="<?php p($l->t('Delete Project')); ?>"
                                            aria-label="<?php p($l->t('Delete project %s', [$project->getName()])); ?>">
                                            <span data-lucide="trash-2" class="lucide-icon" aria-hidden="true"></span>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
                <?php
                    $pagination = $_['pagination'] ?? ['page' => 1, 'totalPages' => 1, 'totalEntries' => count($_['projects'] ?? []), 'perPage' => count($_['projects'] ?? [])];
                    $currentPage = max(1, (int)($pagination['page'] ?? 1));
                    $totalPages = max(1, (int)($pagination['totalPages'] ?? 1));
                    $totalEntries = (int)($pagination['totalEntries'] ?? 0);
                    $perPage = (int)($pagination['perPage'] ?? 0);
                    $baseUrl = $_['projectsUrl'] ?? '/index.php/apps/projectcheck/projects';
                    $baseQuery = $_['filters'] ?? [];
                    unset($baseQuery['limit'], $baseQuery['offset']);
                ?>
                <?php if ($totalPages > 1): ?>
                    <div class="pc-list-panel__footer pagination">
                        <div class="pagination-info">
                            <span><?php p($l->t('Page')); ?> <?php p($currentPage); ?> / <?php p($totalPages); ?></span>
                            <span>•</span>
                            <span><?php p($l->t('Total')); ?> <?php p($totalEntries); ?></span>
                        </div>
                        <div class="pagination-actions">
                            <?php
                                $prevQuery = array_merge($baseQuery, ['page' => max(1, $currentPage - 1)]);
                                $nextQuery = array_merge($baseQuery, ['page' => min($totalPages, $currentPage + 1)]);
                            ?>
                            <?php if ($currentPage > 1): ?>
                            <a class="button secondary" href="<?php p($baseUrl . '?' . http_build_query($prevQuery)); ?>">
                                ‹ <?php p($l->t('Previous')); ?>
                            </a>
                            <?php else: ?>
                            <span class="button secondary disabled" aria-disabled="true"><?php p($l->t('Previous')); ?></span>
                            <?php endif; ?>
                            <?php if ($currentPage < $totalPages): ?>
                            <a class="button secondary" href="<?php p($baseUrl . '?' . http_build_query($nextQuery)); ?>">
                                <?php p($l->t('Next')); ?> ›
                            </a>
                            <?php else: ?>
                            <span class="button secondary disabled" aria-disabled="true"><?php p($l->t('Next')); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
<?php include __DIR__ . '/common/page-end.php'; ?>