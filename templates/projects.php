<?php

/**
 * Projects template for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

use OCP\Util;

Util::addScript('projectcheck', 'projects');
Util::addStyle('projectcheck', 'projects');
Util::addStyle('projectcheck', 'navigation');
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<div id="app-content">
    <div id="app-content-wrapper">
        <!-- Page Header -->
        <div class="section header-section">
            <div class="header-content">
                <div class="header-text">
                    <h2><?php p($l->t('Projects')); ?></h2>
                    <p><?php p($l->t('Manage your projects and track their progress')); ?></p>
                </div>
                <div class="header-actions">
                    <a href="<?php p($_['createUrl']); ?>" class="button primary">
                        <?php p($l->t('Create New Project')); ?>
                    </a>
                </div>
            </div>
        </div>

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
        <div class="section">
            <div class="section-header">
                <h3><?php p($l->t('Project Statistics')); ?></h3>
                <p><?php p($l->t('Overview of your project portfolio and performance')); ?></p>
            </div>

            <div class="overview-stats">
                <div class="overview-stat">
                    <div class="stat-icon">
                        <i data-lucide="folder" class="lucide-icon white"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php p($_['stats']['totalProjects'] ?? 0); ?></div>
                        <div class="stat-label"><?php p($l->t('Total Projects')); ?></div>
                        <div class="stat-detail">
                            <span class="stat-sub"><?php p($_['stats']['activeProjects'] ?? 0); ?> <?php p($l->t('active')); ?></span>
                            <span class="stat-sub"><?php p($_['stats']['completedProjects'] ?? 0); ?> <?php p($l->t('completed')); ?></span>
                        </div>
                    </div>
                </div>

                <div class="overview-stat">
                    <div class="stat-icon">
                        <i data-lucide="users" class="lucide-icon white"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php p($_['stats']['totalCustomers'] ?? 0); ?></div>
                        <div class="stat-label"><?php p($l->t('Total Customers')); ?></div>
                        <div class="stat-detail">
                            <span class="stat-sub"><?php p($l->t('Active clients')); ?></span>
                        </div>
                    </div>
                </div>

                <div class="overview-stat">
                    <div class="stat-icon">
                        <i data-lucide="clock" class="lucide-icon white"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php p($_['stats']['totalHours'] ?? 0); ?></div>
                        <div class="stat-label"><?php p($l->t('Total Hours')); ?></div>
                        <div class="stat-detail">
                            <span class="stat-sub"><?php p($l->t('Across all projects')); ?></span>
                        </div>
                    </div>
                </div>

                <div class="overview-stat">
                    <div class="stat-icon">
                        <i data-lucide="euro" class="lucide-icon white"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number">€<?php p(number_format($_['stats']['totalBudget'] ?? 0, 2)); ?></div>
                        <div class="stat-label"><?php p($l->t('Total Budget')); ?></div>
                        <div class="stat-detail">
                            <span class="stat-sub">€<?php p(number_format($_['stats']['totalConsumption'] ?? 0, 2)); ?> <?php p($l->t('used')); ?></span>
                            <span class="stat-sub"><?php p($_['stats']['consumptionPercentage'] ?? 0); ?>% <?php p($l->t('consumed')); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter Section -->
        <div class="section">
            <div class="filters-container">
                <div class="searchbox">
                    <input type="text" id="project-search"
                        placeholder="<?php p($l->t('Search projects...')); ?>"
                        value="<?php p($_['filters']['search'] ?? ''); ?>">
                </div>

                <div class="filters-row">
                    <select id="status-filter">
                        <option value=""><?php p($l->t('All Statuses')); ?></option>
                        <option value="Active" <?php if (($_['filters']['status'] ?? '') === 'Active') echo 'selected'; ?>><?php p($l->t('Active')); ?></option>
                        <option value="On Hold" <?php if (($_['filters']['status'] ?? '') === 'On Hold') echo 'selected'; ?>><?php p($l->t('On Hold')); ?></option>
                        <option value="Completed" <?php if (($_['filters']['status'] ?? '') === 'Completed') echo 'selected'; ?>><?php p($l->t('Completed')); ?></option>
                        <option value="Cancelled" <?php if (($_['filters']['status'] ?? '') === 'Cancelled') echo 'selected'; ?>><?php p($l->t('Cancelled')); ?></option>
                    </select>

                    <select id="priority-filter">
                        <option value=""><?php p($l->t('All Priorities')); ?></option>
                        <option value="Low" <?php if (($_['filters']['priority'] ?? '') === 'Low') echo 'selected'; ?>><?php p($l->t('Low')); ?></option>
                        <option value="Medium" <?php if (($_['filters']['priority'] ?? '') === 'Medium') echo 'selected'; ?>><?php p($l->t('Medium')); ?></option>
                        <option value="High" <?php if (($_['filters']['priority'] ?? '') === 'High') echo 'selected'; ?>><?php p($l->t('High')); ?></option>
                        <option value="Critical" <?php if (($_['filters']['priority'] ?? '') === 'Critical') echo 'selected'; ?>><?php p($l->t('Critical')); ?></option>
                    </select>

                    <select id="project-type-filter">
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

                    <button id="clear-filters" class="button">
                        <?php p($l->t('Clear Filters')); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Projects Table -->
        <div class="section">
            <?php if (empty($_['projects'])): ?>
                <div class="emptycontent">
                    <div class="icon-folder"></div>
                    <h2><?php p($l->t('No projects found')); ?></h2>
                    <p><?php p($l->t('Create your first project to get started!')); ?></p>
                </div>
            <?php else: ?>
                <table class="grid">
                    <thead>
                        <tr>
                            <th><?php p($l->t('Name')); ?></th>
                            <th><?php p($l->t('Customer')); ?></th>
                            <th><?php p($l->t('Type')); ?></th>
                            <th><?php p($l->t('Status')); ?></th>
                            <th><?php p($l->t('Budget')); ?></th>
                            <th><?php p($l->t('Progress')); ?></th>
                            <th><?php p($l->t('Actions')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($_['projects'] as $projectData): ?>
                            <?php
                            $project = $projectData['project'] ?? $projectData;
                            $budgetInfo = $projectData['budgetInfo'] ?? null;
                            ?>
                            <tr class="project-row <?php if ($budgetInfo): ?>budget-status-<?php p($budgetInfo['warning_level']); ?><?php endif; ?>">
                                <td class="project-name-cell">
                                    <div class="project-name-content">
                                        <a href="<?php p(str_replace('PROJECT_ID', $project->getId(), $_['showUrl'])); ?>" class="project-title">
                                            <?php p($project->getName()); ?>
                                        </a>
                                        <div class="project-badges">
                                            <span class="priority-badge priority-<?php echo strtolower($project->getPriority()); ?>">
                                                <?php p($project->getPriority()); ?>
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
                                <td><?php p($project->getCustomerName() ?? $l->t('N/A')); ?></td>
                                <td>
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
                                <td>
                                    <span class="status-badge status-<?php echo strtolower(str_replace(' ', '-', $project->getStatus())); ?>">
                                        <?php p($project->getStatus()); ?>
                                    </span>
                                </td>
                                <td class="budget-cell">
                                    <?php if ($budgetInfo): ?>
                                        <div class="budget-info-compact">
                                            <div class="budget-main">
                                                <div class="budget-line">
                                                    <span class="budget-label"><?php p($l->t('Total Budget:')); ?></span>
                                                    <span class="budget-total">€<?php p(number_format($budgetInfo['total_budget'], 2)); ?></span>
                                                </div>
                                                <div class="budget-line">
                                                    <span class="budget-label"><?php p($l->t('Used:')); ?></span>
                                                    <span class="budget-used">€<?php p(number_format($budgetInfo['used_budget'], 2)); ?></span>
                                                </div>
                                                <div class="budget-line">
                                                    <span class="budget-label"><?php p($l->t('Remaining:')); ?></span>
                                                    <span class="budget-remaining <?php p($budgetInfo['warning_level']); ?>">
                                                        €<?php p(number_format($budgetInfo['remaining_budget'], 2)); ?>
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
                                                    <span class="budget-total">€<?php p(number_format($project->getTotalBudget() ?? 0, 2)); ?></span>
                                                </div>
                                                <div class="budget-line">
                                                    <span class="budget-label"><?php p($l->t('Used:')); ?></span>
                                                    <span class="budget-used">€0.00</span>
                                                </div>
                                                <div class="budget-line">
                                                    <span class="budget-label"><?php p($l->t('Remaining:')); ?></span>
                                                    <span class="budget-remaining">€<?php p(number_format($project->getTotalBudget() ?? 0, 2)); ?></span>
                                                </div>
                                            </div>
                                            <div class="budget-secondary">
                                                <span class="budget-percentage">0% <?php p($l->t('used')); ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="progress-cell">
                                    <?php if ($budgetInfo): ?>
                                        <div class="progress-info">
                                            <div class="budget-progress-bar compact">
                                                <div class="budget-progress-fill <?php p($budgetInfo['warning_level']); ?>"
                                                    style="width: <?php p(min(100, $budgetInfo['consumption_percentage'])); ?>%"></div>
                                            </div>
                                            <span class="hours-logged">
                                                <?php p(number_format($budgetInfo['used_hours'], 1)); ?>h <?php p($l->t('logged')); ?>
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
                                <td>
                                    <div class="action-items">
                                        <a href="<?php p(str_replace('PROJECT_ID', $project->getId(), $_['showUrl'])); ?>"
                                            class="action-item" title="<?php p($l->t('View Project')); ?>">
                                            <span class="icon icon-details"></span>
                                        </a>
                                        <a href="<?php p(str_replace('PROJECT_ID', $project->getId(), $_['editUrl'])); ?>"
                                            class="action-item" title="<?php p($l->t('Edit Project')); ?>">
                                            <span class="icon icon-rename"></span>
                                        </a>
                                        <button type="button" class="action-item delete-project-btn"
                                            data-project-id="<?php p($project->getId()); ?>"
                                            data-project-name="<?php p($project->getName()); ?>"
                                            title="<?php p($l->t('Delete Project')); ?>"
                                            onclick="console.log('Delete button clicked directly, project ID: <?php p($project->getId()); ?>');">
                                            <span class="icon icon-delete"></span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>