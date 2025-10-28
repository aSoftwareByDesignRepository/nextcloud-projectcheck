<?php

/**
 * Dashboard template for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

use OCP\Util;

Util::addScript('projectcheck', 'dashboard');
Util::addStyle('projectcheck', 'dashboard');
Util::addStyle('projectcheck', 'projects');
Util::addStyle('projectcheck', 'budget-alerts');
Util::addStyle('projectcheck', 'custom-icons');
Util::addStyle('projectcheck', 'navigation');
Util::addStyle('projectcheck', 'common/progress-bars');
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<div id="app-content">
    <div id="app-content-wrapper">
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb-container">
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <ol>
                    <li aria-current="page"><?php p($l->t('Dashboard')); ?></li>
                </ol>
            </nav>
        </div>



        <!-- Page Header -->
        <div class="section page-header-section">
            <div class="header-content">
                <div class="header-text">
                    <div class="header-details">
                        <h2><?php p($l->t('Dashboard')); ?></h2>
                        <p><?php p($l->t('Overview of your projects and activities')); ?></p>
                        <div class="project-meta">
                            <div class="meta-item">
                                <i data-lucide="calendar" class="lucide-icon primary"></i>
                                <span><?php p(date('d.m.Y')); ?></span>
                            </div>
                            <div class="meta-item">
                                <i data-lucide="folder" class="lucide-icon primary"></i>
                                <span><?php p($_['stats']['totalProjects'] ?? 0); ?> <?php p($l->t('Projects')); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="<?php p($urlGenerator->linkToRoute('projectcheck.project.create')); ?>" class="button primary">
                        <i data-lucide="plus" class="lucide-icon"></i>
                        <?php p($l->t('New Project')); ?>
                    </a>
                    <a href="<?php p($urlGenerator->linkToRoute('projectcheck.timeentry.create')); ?>" class="button secondary">
                        <i data-lucide="clock" class="lucide-icon"></i>
                        <?php p($l->t('New Time Entry')); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Budget Alerts -->
        <?php if (isset($budgetAlerts) && !empty($budgetAlerts)): ?>
            <div class="section budget-alerts-section">
                <div class="section-header">
                    <h3><?php p($l->t('Budget Alerts')); ?></h3>
                    <p><?php p($l->t('Projects requiring attention')); ?></p>
                </div>

                <?php foreach ($budgetAlerts as $alert): ?>
                    <div class="alert alert-<?php p($alert['level']); ?>">
                        <div class="alert-icon">
                            <?php if ($alert['level'] === 'critical'): ?>
                                <i data-lucide="alert-triangle" class="lucide-icon"></i>
                            <?php else: ?>
                                <i data-lucide="info" class="lucide-icon"></i>
                            <?php endif; ?>
                        </div>
                        <div class="alert-content">
                            <div class="alert-title"><?php p($alert['title']); ?></div>
                            <div class="alert-message"><?php p($alert['message']); ?></div>
                        </div>
                        <div class="alert-actions">
                            <a href="<?php p($urlGenerator->linkToRoute('projectcheck.project.show', ['id' => $alert['project_id']])); ?>"
                                class="button small">
                                <?php p($l->t('View Project')); ?>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Statistics Overview -->
        <div class="section">
            <div class="section-header">
                <h3><?php p($l->t('Overview Statistics')); ?></h3>
                <p><?php p($l->t('Key metrics and project insights')); ?></p>
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
                            <span class="stat-sub"><?php p($_['stats']['activeProjects'] ?? 0); ?> active</span>
                            <span class="stat-sub"><?php p($_['stats']['completedProjects'] ?? 0); ?> completed</span>
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
                            <span class="stat-sub">€<?php p(number_format($_['stats']['totalConsumption'] ?? 0, 2)); ?> used</span>
                            <span class="stat-sub"><?php p($_['stats']['consumptionPercentage'] ?? 0); ?>% consumed</span>
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
                            <span class="stat-sub"><?php p($l->t('This month')); ?></span>
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
            </div>
        </div>

        <!-- Detailed Yearly Statistics -->
        <?php if (!empty($_['stats']['detailedYearlyStats'])): ?>
            <div class="section detailed-yearly-stats-section">
                <div class="section-header">
                    <h3><i data-lucide="trending-up" class="lucide-icon"></i> <?php p($l->t('Detailed Yearly Performance Dashboard')); ?></h3>
                    <p><?php p($l->t('Comprehensive analysis of hours and costs by year, customer, and project')); ?></p>
                </div>
                <div class="section-content">
                    <div class="detailed-yearly-stats-container">
                        <?php foreach ($_['stats']['detailedYearlyStats'] as $year => $yearData): ?>
                            <div class="year-section">
                                <div class="year-header">
                                    <h4 class="year-title"><?php p($year); ?></h4>
                                    <div class="year-summary">
                                        <?php
                                        $yearTotalHours = array_sum(array_column($yearData, 'total_hours'));
                                        $yearTotalCost = array_sum(array_column($yearData, 'total_cost'));
                                        $yearTotalEntries = array_sum(array_column($yearData, 'total_entries'));
                                        ?>
                                        <div class="year-summary-item">
                                            <span class="summary-label"><?php p($l->t('Total Hours')); ?>:</span>
                                            <span class="summary-value"><?php p(number_format($yearTotalHours, 1)); ?>h</span>
                                        </div>
                                        <div class="year-summary-item">
                                            <span class="summary-label"><?php p($l->t('Total Cost')); ?>:</span>
                                            <span class="summary-value">€<?php p(number_format($yearTotalCost, 2)); ?></span>
                                        </div>
                                        <div class="year-summary-item">
                                            <span class="summary-label"><?php p($l->t('Entries')); ?>:</span>
                                            <span class="summary-value"><?php p($yearTotalEntries); ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="customers-container">
                                    <?php foreach ($yearData as $customerId => $customerData): ?>
                                        <div class="customer-section">
                                            <div class="customer-header">
                                                <h5 class="customer-title">
                                                    <i data-lucide="users" class="lucide-icon"></i>
                                                    <?php p($customerData['customer_name']); ?>
                                                </h5>
                                                <div class="customer-summary">
                                                    <div class="customer-summary-item">
                                                        <span class="summary-label"><?php p($l->t('Hours')); ?>:</span>
                                                        <span class="summary-value"><?php p(number_format($customerData['total_hours'], 1)); ?>h</span>
                                                    </div>
                                                    <div class="customer-summary-item">
                                                        <span class="summary-label"><?php p($l->t('Cost')); ?>:</span>
                                                        <span class="summary-value">€<?php p(number_format($customerData['total_cost'], 2)); ?></span>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="projects-container">
                                                <?php foreach ($customerData['projects'] as $projectId => $projectData): ?>
                                                    <div class="project-card detailed-project-card">
                                                        <div class="project-header">
                                                            <h6 class="project-title">
                                                                <i data-lucide="folder" class="lucide-icon"></i>
                                                                <a href="<?php p($urlGenerator->linkToRoute('projectcheck.project.show', ['id' => $projectId])); ?>">
                                                                    <?php p($projectData['project_name']); ?>
                                                                </a>
                                                            </h6>
                                                            <div class="project-badge">
                                                                <?php p($projectData['entry_count']); ?> <?php p($l->t('entries')); ?>
                                                            </div>
                                                        </div>
                                                        <div class="project-content">
                                                            <div class="project-stats">
                                                                <div class="project-stat">
                                                                    <div class="stat-icon">
                                                                        <i data-lucide="clock" class="lucide-icon"></i>
                                                                    </div>
                                                                    <div class="stat-details">
                                                                        <div class="stat-value"><?php p(number_format($projectData['total_hours'], 1)); ?>h</div>
                                                                        <div class="stat-label"><?php p($l->t('Hours')); ?></div>
                                                                    </div>
                                                                </div>
                                                                <div class="project-stat">
                                                                    <div class="stat-icon">
                                                                        <i data-lucide="euro" class="lucide-icon"></i>
                                                                    </div>
                                                                    <div class="stat-details">
                                                                        <div class="stat-value">€<?php p(number_format($projectData['total_cost'], 2)); ?></div>
                                                                        <div class="stat-label"><?php p($l->t('Cost')); ?></div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <!-- Progress indicators -->
                                                            <div class="project-progress">
                                                                <div class="progress-item">
                                                                    <div class="progress-label"><?php p($l->t('Hours Share')); ?></div>
                                                                    <div class="progress-bar">
                                                                        <div class="progress-fill" style="width: <?php p($yearTotalHours > 0 ? ($projectData['total_hours'] / $yearTotalHours) * 100 : 0); ?>%"></div>
                                                                    </div>
                                                                    <div class="progress-percentage"><?php p($yearTotalHours > 0 ? round(($projectData['total_hours'] / $yearTotalHours) * 100, 1) : 0); ?>%</div>
                                                                </div>
                                                                <div class="progress-item">
                                                                    <div class="progress-label"><?php p($l->t('Cost Share')); ?></div>
                                                                    <div class="progress-bar">
                                                                        <div class="progress-fill" style="width: <?php p($yearTotalCost > 0 ? ($projectData['total_cost'] / $yearTotalCost) * 100 : 0); ?>%"></div>
                                                                    </div>
                                                                    <div class="progress-percentage"><?php p($yearTotalCost > 0 ? round(($projectData['total_cost'] / $yearTotalCost) * 100, 1) : 0); ?>%</div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Project Type Statistics -->
        <?php if (!empty($_['stats']['projectTypeStats'])): ?>
            <div class="section project-type-stats-section">
                <div class="section-header">
                    <h3><i data-lucide="pie-chart" class="lucide-icon"></i> <?php p($l->t('Project Type Analysis Dashboard')); ?></h3>
                    <p><?php p($l->t('Analyze productivity by project type to identify billable vs overhead work')); ?></p>
                </div>
                <div class="section-content">
                    <div class="project-type-stats-container">
                        <?php foreach ($_['stats']['projectTypeStats'] as $year => $yearData): ?>
                            <div class="year-section">
                                <div class="year-header">
                                    <h4><?php p($year); ?></h4>
                                    <?php
                                    $yearTotalHours = array_sum(array_column($yearData, 'total_hours'));
                                    $yearTotalCost = array_sum(array_column($yearData, 'total_cost'));
                                    ?>
                                    <div class="year-summary">
                                        <span class="summary-item">
                                            <i data-lucide="clock" class="lucide-icon"></i>
                                            <?php p(number_format($yearTotalHours, 1)); ?>h
                                        </span>
                                        <span class="summary-item">
                                            <i data-lucide="euro" class="lucide-icon"></i>
                                            €<?php p(number_format($yearTotalCost, 2)); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="project-types-container">
                                    <?php foreach ($yearData as $projectType => $typeData): ?>
                                        <div class="project-type-card">
                                            <div class="type-header">
                                                <h5 class="type-name">
                                                    <?php
                                                    $displayNames = [
                                                        'client' => $l->t('Client Project'),
                                                        'admin' => $l->t('Administrative'),
                                                        'sales' => $l->t('Sales & Marketing'),
                                                        'customer' => $l->t('Customer Support'),
                                                        'product' => $l->t('Product Development'),
                                                        'meeting' => $l->t('Meetings & Overhead'),
                                                        'internal' => $l->t('Internal Project'),
                                                        'research' => $l->t('Research & Development'),
                                                        'training' => $l->t('Training & Education'),
                                                        'other' => $l->t('Other')
                                                    ];

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

                                                    $projectType = $typeData['project_type'];
                                                    $displayName = $displayNames[$projectType] ?? ucfirst($projectType);
                                                    $icon = $iconMapping[$projectType] ?? '📋';
                                                    ?>
                                                    <span class="project-type-icon"
                                                        data-project-type="<?php p($projectType); ?>"
                                                        title="<?php p($displayName); ?>">
                                                        <?php p($icon); ?>
                                                    </span>
                                                    <span class="project-type-label"><?php p($displayName); ?></span>
                                                </h5>
                                                <div class="type-stats">
                                                    <div class="stat-item">
                                                        <span class="stat-value"><?php p(number_format($typeData['total_hours'], 1)); ?>h</span>
                                                        <span class="stat-label"><?php p($l->t('Hours')); ?></span>
                                                    </div>
                                                    <div class="stat-item">
                                                        <span class="stat-value">€<?php p(number_format($typeData['total_cost'], 2)); ?></span>
                                                        <span class="stat-label"><?php p($l->t('Cost')); ?></span>
                                                    </div>
                                                    <div class="stat-item">
                                                        <span class="stat-value"><?php p($typeData['entry_count']); ?></span>
                                                        <span class="stat-label"><?php p($l->t('Entries')); ?></span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="type-progress">
                                                <div class="progress-item">
                                                    <div class="progress-label"><?php p($l->t('Hours Share')); ?></div>
                                                    <div class="progress-bar">
                                                        <div class="progress-fill" style="width: <?php p($yearTotalHours > 0 ? ($typeData['total_hours'] / $yearTotalHours) * 100 : 0); ?>%"></div>
                                                    </div>
                                                    <div class="progress-percentage"><?php p($yearTotalHours > 0 ? round(($typeData['total_hours'] / $yearTotalHours) * 100, 1) : 0); ?>%</div>
                                                </div>
                                                <div class="progress-item">
                                                    <div class="progress-label"><?php p($l->t('Cost Share')); ?></div>
                                                    <div class="progress-bar">
                                                        <div class="progress-fill" style="width: <?php p($yearTotalCost > 0 ? ($typeData['total_cost'] / $yearTotalCost) * 100 : 0); ?>%"></div>
                                                    </div>
                                                    <div class="progress-percentage"><?php p($yearTotalCost > 0 ? round(($typeData['total_cost'] / $yearTotalCost) * 100, 1) : 0); ?>%</div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Productivity Analysis -->
        <?php if (!empty($_['stats']['productivityAnalysis'])): ?>
            <div class="section productivity-analysis-section">
                <div class="section-header">
                    <h3>
                        <i data-lucide="trending-up" class="lucide-icon"></i>
                        <?php p($l->t('Productivity Analysis Dashboard')); ?>
                        <button class="info-popup-trigger" data-action="show-productivity-info" title="<?php p($l->t('Click for detailed explanation')); ?>">?</button>
                    </h3>
                    <p><?php p($l->t('Compare billable vs overhead work to measure productivity')); ?></p>
                </div>
                <div class="section-content">
                    <div class="productivity-stats-container">
                        <?php foreach ($_['stats']['productivityAnalysis'] as $year => $yearData): ?>
                            <div class="productivity-year-section">
                                <div class="year-header">
                                    <h4><?php p($year); ?></h4>
                                </div>
                                <div class="productivity-comparison">
                                    <div class="productivity-card billable">
                                        <div class="card-header">
                                            <h5><?php p($l->t('Billable Work')); ?></h5>
                                            <div class="card-icon">
                                                <i data-lucide="dollar-sign" class="lucide-icon"></i>
                                            </div>
                                        </div>
                                        <div class="card-content">
                                            <div class="stat-item">
                                                <span class="stat-value"><?php p(number_format($yearData['billable']['total_hours'], 1)); ?>h</span>
                                                <span class="stat-label"><?php p($l->t('Hours')); ?></span>
                                            </div>
                                            <div class="stat-item">
                                                <span class="stat-value">€<?php p(number_format($yearData['billable']['total_cost'], 2)); ?></span>
                                                <span class="stat-label"><?php p($l->t('Revenue')); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="productivity-card overhead">
                                        <div class="card-header">
                                            <h5><?php p($l->t('Overhead Work')); ?></h5>
                                            <div class="card-icon">
                                                <i data-lucide="settings" class="lucide-icon"></i>
                                            </div>
                                        </div>
                                        <div class="card-content">
                                            <div class="stat-item">
                                                <span class="stat-value"><?php p(number_format($yearData['overhead']['total_hours'], 1)); ?>h</span>
                                                <span class="stat-label"><?php p($l->t('Hours')); ?></span>
                                            </div>
                                            <div class="stat-item">
                                                <span class="stat-value">€<?php p(number_format($yearData['overhead']['total_cost'], 2)); ?></span>
                                                <span class="stat-label"><?php p($l->t('Cost')); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="productivity-ratio">
                                    <?php
                                    $totalHours = $yearData['billable']['total_hours'] + $yearData['overhead']['total_hours'];
                                    $billablePercentage = $totalHours > 0 ? ($yearData['billable']['total_hours'] / $totalHours) * 100 : 0;
                                    $overheadPercentage = $totalHours > 0 ? ($yearData['overhead']['total_hours'] / $totalHours) * 100 : 0;
                                    ?>
                                    <div class="ratio-bar">
                                        <div class="ratio-fill billable" style="width: <?php p($billablePercentage); ?>%"></div>
                                        <div class="ratio-fill overhead" style="width: <?php p($overheadPercentage); ?>%"></div>
                                    </div>
                                    <div class="ratio-labels">
                                        <span class="ratio-label billable"><?php p(round($billablePercentage, 1)); ?>% <?php p($l->t('Billable')); ?></span>
                                        <span class="ratio-label overhead"><?php p(round($overheadPercentage, 1)); ?>% <?php p($l->t('Overhead')); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recent Projects -->
        <div class="section">
            <div class="section-header">
                <h3><?php p($l->t('Recent Projects')); ?></h3>
                <p><?php p($l->t('Your latest project activities')); ?></p>
            </div>

            <?php if (empty($_['stats']['recentProjects'])): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i data-lucide="folder" class="lucide-icon"></i>
                    </div>
                    <h4><?php p($l->t('No recent projects')); ?></h4>
                    <p><?php p($l->t('Create your first project to get started!')); ?></p>
                    <a href="<?php p($urlGenerator->linkToRoute('projectcheck.project.create')); ?>" class="button primary">
                        <i data-lucide="plus" class="lucide-icon"></i>
                        <?php p($l->t('Create Project')); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="projects-grid">
                    <?php foreach ($_['stats']['recentProjects'] as $projectData): ?>
                        <?php
                        $project = $projectData['project'] ?? $projectData;
                        $budgetInfo = $projectData['budgetInfo'] ?? null;
                        ?>
                        <div class="project-card dashboard-card <?php if ($budgetInfo): ?>budget-status-<?php p($budgetInfo['warning_level']); ?><?php endif; ?>">
                            <div class="card-header">
                                <div class="card-title-section">
                                    <h4 class="project-name">
                                        <a href="<?php p($urlGenerator->linkToRoute('projectcheck.project.show', ['id' => $project->getId()])); ?>">
                                            <?php p($project->getName()); ?>
                                        </a>
                                    </h4>
                                    <div class="project-status-badges">
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
                                        <span class="status-badge status-<?php p(strtolower(str_replace(' ', '-', $project->getStatus()))); ?>">
                                            <?php p($project->getStatus()); ?>
                                        </span>
                                        <?php if ($budgetInfo): ?>
                                            <?php if ($budgetInfo['consumption_percentage'] >= 100): ?>
                                                <span class="budget-status-badge critical">⚠️ <?php p($l->t('Over Budget')); ?></span>
                                            <?php elseif ($budgetInfo['warning_level'] === 'critical'): ?>
                                                <span class="budget-status-badge critical">⚠️ <?php p($l->t('Critical')); ?></span>
                                            <?php elseif ($budgetInfo['warning_level'] === 'warning'): ?>
                                                <span class="budget-status-badge warning">⚠️ <?php p($l->t('Warning')); ?></span>
                                            <?php else: ?>
                                                <span class="budget-status-badge safe">✅ <?php p($l->t('On Track')); ?></span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="budget-status-badge safe">✅ <?php p($l->t('On Track')); ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="card-content">
                                <div class="project-details">
                                    <div class="detail-row">
                                        <span class="detail-label"><?php p($l->t('Customer:')); ?></span>
                                        <span class="detail-value"><?php p($project->getCustomerName() ?? $l->t('No customer')); ?></span>
                                    </div>
                                    <?php if ($budgetInfo): ?>
                                        <div class="detail-row budget-detail">
                                            <span class="detail-label"><?php p($l->t('Budget:')); ?></span>
                                            <span class="detail-value budget-info">
                                                <span class="budget-remaining <?php p($budgetInfo['warning_level']); ?>">
                                                    €<?php p(number_format($budgetInfo['remaining_budget'], 2)); ?>
                                                </span>
                                                <span class="budget-separator"><?php p($l->t('remaining of')); ?></span>
                                                <span class="budget-total">€<?php p(number_format($budgetInfo['total_budget'], 2)); ?></span>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label"><?php p($l->t('Progress:')); ?></span>
                                            <span class="detail-value progress-info">
                                                <span class="usage-stats">
                                                    <?php p(round($budgetInfo['consumption_percentage'])); ?>% used
                                                    • <?php p(number_format($budgetInfo['used_hours'], 1)); ?>h logged
                                                </span>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <div class="detail-row">
                                            <span class="detail-label"><?php p($l->t('Budget:')); ?></span>
                                            <span class="detail-value">€<?php p(number_format($project->getTotalBudget() ?? 0, 2)); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if ($budgetInfo): ?>
                                    <div class="card-progress-section">
                                        <div class="budget-progress-bar dashboard">
                                            <div class="budget-progress-fill <?php p($budgetInfo['warning_level']); ?>"
                                                style="width: <?php p(min(100, $budgetInfo['consumption_percentage'])); ?>%"></div>
                                        </div>
                                        <div class="progress-labels">
                                            <span class="progress-label-left">€0</span>
                                            <span class="progress-label-center <?php p($budgetInfo['warning_level']); ?>">
                                                <?php p(round($budgetInfo['consumption_percentage'])); ?>%
                                            </span>
                                            <span class="progress-label-right">€<?php p(number_format($budgetInfo['total_budget'], 0)); ?></span>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Recent Time Entries -->
        <div class="section">
            <div class="section-header">
                <h3><?php p($l->t('Recent Time Entries')); ?></h3>
                <p><?php p($l->t('Your latest time tracking activities')); ?></p>
            </div>

            <?php if (empty($_['stats']['recentTimeEntries'])): ?>
                <div class="empty-state">
                    <div class="empty-icon">
                        <i data-lucide="clock" class="lucide-icon"></i>
                    </div>
                    <h4><?php p($l->t('No recent time entries')); ?></h4>
                    <p><?php p($l->t('Start tracking your time to see recent entries!')); ?></p>
                    <a href="<?php p($urlGenerator->linkToRoute('projectcheck.timeentry.create')); ?>" class="button primary">
                        <i data-lucide="plus" class="lucide-icon"></i>
                        <?php p($l->t('Create Time Entry')); ?>
                    </a>
                </div>
            <?php else: ?>
                <div class="time-entries-grid">
                    <?php foreach ($_['stats']['recentTimeEntries'] as $entryData): ?>
                        <?php $entry = $entryData['timeEntry']; ?>
                        <div class="time-entry-card">
                            <div class="card-header">
                                <div class="card-title">
                                    <h4><?php p($entryData['projectName'] ?? $l->t('Unknown Project')); ?></h4>
                                    <span class="duration"><?php p($entry->getHours() . 'h'); ?></span>
                                </div>
                            </div>
                            <div class="card-content">
                                <p class="card-description"><?php p($entry->getDescription() ?: $l->t('No description')); ?></p>
                                <div class="card-meta">
                                    <div class="meta-item">
                                        <i data-lucide="calendar" class="lucide-icon primary"></i>
                                        <span><?php p($entry->getDate() ? $entry->getDate()->format('d.m.Y') : ''); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i data-lucide="user" class="lucide-icon primary"></i>
                                        <span><?php p($entryData['userDisplayName'] ?? $entry->getUserId() ?? ''); ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-actions">
                                <a href="<?php p($urlGenerator->linkToRoute('projectcheck.timeentry.show', ['id' => $entry->getId()])); ?>" class="button small">
                                    <i data-lucide="eye" class="lucide-icon"></i>
                                    <?php p($l->t('View')); ?>
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="section">
            <div class="section-header">
                <h3><?php p($l->t('Quick Actions')); ?></h3>
                <p><?php p($l->t('Common tasks and shortcuts')); ?></p>
            </div>

            <div class="actions-grid">
                <a href="<?php p($urlGenerator->linkToRoute('projectcheck.project.create')); ?>" class="action-card">
                    <div class="action-icon">
                        <i data-lucide="plus" class="lucide-icon primary"></i>
                    </div>
                    <div class="action-content">
                        <h4><?php p($l->t('New Project')); ?></h4>
                        <p><?php p($l->t('Create a new project')); ?></p>
                    </div>
                </a>

                <a href="<?php p($urlGenerator->linkToRoute('projectcheck.timeentry.create')); ?>" class="action-card">
                    <div class="action-icon">
                        <i data-lucide="clock" class="lucide-icon primary"></i>
                    </div>
                    <div class="action-content">
                        <h4><?php p($l->t('New Time Entry')); ?></h4>
                        <p><?php p($l->t('Log your time')); ?></p>
                    </div>
                </a>

                <a href="<?php p($urlGenerator->linkToRoute('projectcheck.project.index')); ?>" class="action-card">
                    <div class="action-icon">
                        <i data-lucide="folder" class="lucide-icon primary"></i>
                    </div>
                    <div class="action-content">
                        <h4><?php p($l->t('View Projects')); ?></h4>
                        <p><?php p($l->t('Browse all projects')); ?></p>
                    </div>
                </a>

                <a href="<?php p($urlGenerator->linkToRoute('projectcheck.timeentry.index')); ?>" class="action-card">
                    <div class="action-icon">
                        <i data-lucide="bar-chart-3" class="lucide-icon primary"></i>
                    </div>
                    <div class="action-content">
                        <h4><?php p($l->t('View Time Entries')); ?></h4>
                        <p><?php p($l->t('See all time entries')); ?></p>
                    </div>
                </a>
            </div>
        </div>
    </div>
</div>

<script nonce="<?php p($_['cspNonce']) ?>">
    // Local SVG icon library
    const svgIcons = {
        calendar: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
        folder: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>',
        plus: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
        clock: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>',
        euro: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.25.5-2.5 1.5-3.5Z"/></svg>',
        users: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        user: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        eye: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
        'bar-chart-3': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>',
        'pie-chart': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>',
        'dollar-sign': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
        'settings': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1 1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>'
    };

    // Initialize icons
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-lucide]').forEach(function(el) {
            const iconName = el.getAttribute('data-lucide');
            if (svgIcons[iconName]) {
                el.innerHTML = svgIcons[iconName];
            }
        });
    });

    // Temporary popup functions (will be moved to webpack build)
    function showProductivityInfoPopup() {
        console.log('showProductivityInfoPopup called');
        const popup = document.getElementById('productivity-info-popup');
        if (popup) {
            popup.style.display = 'flex';
            document.body.style.overflow = 'hidden';
            console.log('Popup should be visible now');
        } else {
            console.error('Popup element not found!');
        }
    }

    function hideProductivityInfoPopup() {
        console.log('hideProductivityInfoPopup called');
        const popup = document.getElementById('productivity-info-popup');
        if (popup) {
            popup.style.display = 'none';
            document.body.style.overflow = 'auto';
        }
    }

    // Make functions globally available
    window.showProductivityInfoPopup = showProductivityInfoPopup;
    window.hideProductivityInfoPopup = hideProductivityInfoPopup;

    // Add event listeners
    document.addEventListener('click', function(event) {
        console.log('Dashboard click handler - target:', event.target);

        // Handle productivity info popup trigger
        if (event.target.matches('.info-popup-trigger') ||
            event.target.matches('[data-action="show-productivity-info"]')) {
            console.log('Productivity info button clicked!');
            event.preventDefault();
            event.stopPropagation();
            showProductivityInfoPopup();
            return;
        }

        // Handle popup close button
        if (event.target.matches('.popup-close') ||
            event.target.matches('[data-action="hide-productivity-info"]')) {
            console.log('Popup close button clicked!');
            event.preventDefault();
            event.stopPropagation();
            hideProductivityInfoPopup();
            return;
        }

        // Handle popup close when clicking background
        const popup = document.getElementById('productivity-info-popup');
        if (popup && event.target === popup) {
            hideProductivityInfoPopup();
        }
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            hideProductivityInfoPopup();
        }
    });
</script>

<!-- Productivity Info Popup Modal -->
<div id="productivity-info-popup" class="productivity-info-popup" style="display: none;">
    <div class="popup-content">
        <div class="popup-header">
            <h3><?php p($l->t('Productivity Analysis Explanation')); ?></h3>
            <button class="popup-close" data-action="hide-productivity-info">×</button>
        </div>
        <div class="popup-body">
            <div class="info-section">
                <h4><?php p($l->t('💰 Billable Work')); ?></h4>
                <p><?php p($l->t('Revenue-generating activities that can be directly billed to clients:')); ?></p>
                <ul>
                    <li><?php p($l->t('Client Projects - Direct client work')); ?></li>
                    <li><?php p($l->t('Sales & Marketing - Business development')); ?></li>
                    <li><?php p($l->t('Customer Support - Client assistance')); ?></li>
                    <li><?php p($l->t('Product Development - Product creation')); ?></li>
                    <li><?php p($l->t('Research & Development - Innovation work')); ?></li>
                    <li><?php p($l->t('Other - Miscellaneous billable activities')); ?></li>
                </ul>
            </div>

            <div class="info-section">
                <h4><?php p($l->t('🏢 Overhead Work')); ?></h4>
                <p><?php p($l->t('Internal activities necessary for business operations but not directly billable:')); ?></p>
                <ul>
                    <li><?php p($l->t('Administrative - Office management, paperwork')); ?></li>
                    <li><?php p($l->t('Meetings & Overhead - Internal meetings, planning')); ?></li>
                    <li><?php p($l->t('Internal Projects - Company infrastructure')); ?></li>
                    <li><?php p($l->t('Training & Education - Skills development')); ?></li>
                </ul>
            </div>

            <div class="info-section">
                <h4><?php p($l->t('📊 Why This Matters')); ?></h4>
                <p><?php p($l->t('Understanding your billable vs overhead ratio helps you:')); ?></p>
                <ul>
                    <li><?php p($l->t('Optimize resource allocation')); ?></li>
                    <li><?php p($l->t('Improve profitability')); ?></li>
                    <li><?php p($l->t('Make data-driven business decisions')); ?></li>
                    <li><?php p($l->t('Track productivity trends over time')); ?></li>
                </ul>
            </div>
        </div>
    </div>
</div>