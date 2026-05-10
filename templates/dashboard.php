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
Util::addStyle('projectcheck', 'common/accessibility');
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<?php
/**
 * Audit ref. AUDIT-FINDINGS C14: previously the dashboard had two competing
 * "New Time Entry / New Project" CTAs (one in the page header and one in the
 * quick-actions toolbar). We keep a single primary group in the header for
 * mutating actions, and demote navigation shortcuts ("View …") into the
 * quick-actions toolbar so only one element of any given role appears at a
 * time.
 */
$fmt = $_['fmt'] ?? null;
$currencyCode = isset($_['orgCurrency']) && is_string($_['orgCurrency']) ? strtoupper(trim($_['orgCurrency'])) : 'EUR';
if (preg_match('/^[A-Z]{3}$/', $currencyCode) !== 1) {
	$currencyCode = 'EUR';
}
?>
<div id="app-content" role="main">
    <div id="app-content-wrapper">
        <?php $isGlobalViewer = !empty($_['isGlobalViewer']); ?>
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb-container">
            <nav class="breadcrumb" aria-label="<?php p($l->t('Breadcrumb')); ?>">
                <ol>
                    <li aria-current="page"><?php p($l->t('Dashboard')); ?></li>
                </ol>
            </nav>
        </div>

        <!-- Page Header -->
        <header class="section page-header-section" aria-labelledby="dash-title">
            <div class="header-content">
                <div class="header-text">
                    <div class="header-details">
                        <h2 id="dash-title"><?php p($l->t('Dashboard')); ?></h2>
                        <p><?php p($isGlobalViewer ? $l->t('Overview of your projects and activities') : $l->t('Overview of the projects you can access and your own logged work')); ?></p>
                        <div class="project-meta" aria-label="<?php p($l->t('Dashboard summary')); ?>">
                            <div class="meta-item">
                                <i data-lucide="calendar" class="lucide-icon primary" aria-hidden="true"></i>
                                <span><?php p($fmt ? $fmt->date(new \DateTime()) : date('d.m.Y')); ?></span>
                            </div>
                            <div class="meta-item">
                                <i data-lucide="folder" class="lucide-icon primary" aria-hidden="true"></i>
                                <span><?php p(($_['stats']['totalProjects'] ?? 0) . ' ' . $l->t('Projects')); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="header-actions" role="group" aria-label="<?php p($l->t('Primary actions')); ?>">
                    <a href="<?php p($urlGenerator->linkToRoute('projectcheck.timeentry.create')); ?>" class="button primary">
                        <i data-lucide="clock" class="lucide-icon" aria-hidden="true"></i>
                        <?php p($l->t('New Time Entry')); ?>
                    </a>
                    <?php if (!empty($_['canCreateProject'])): ?>
                    <a href="<?php p($urlGenerator->linkToRoute('projectcheck.project.create')); ?>" class="button secondary">
                        <i data-lucide="plus" class="lucide-icon" aria-hidden="true"></i>
                        <?php p($l->t('New Project')); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </header>

        <?php if (!$isGlobalViewer): ?>
            <div class="section pc-section--ghost" aria-labelledby="dash-scope-title">
                <div class="section-content">
                    <div class="pc-scope-banner" role="status" aria-live="polite">
                        <div class="pc-scope-banner__icon">
                            <i data-lucide="info" class="lucide-icon primary" aria-hidden="true"></i>
                        </div>
                        <div class="pc-scope-banner__content">
                            <h3 id="dash-scope-title"><?php p($l->t('Personal dashboard')); ?></h3>
                            <p><?php p($l->t('Dashboard analytics only include your own time entries. Project cards still reflect the projects you are allowed to access.')); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Quick Navigation Toolbar (jump-to surfaces only; primary CTAs live in the header) -->
        <nav class="quick-actions-toolbar" aria-label="<?php p($l->t('Quick navigation')); ?>">
            <a href="<?php p($urlGenerator->linkToRoute('projectcheck.project.index')); ?>" class="toolbar-action">
                <i data-lucide="folder" class="lucide-icon" aria-hidden="true"></i>
                <span><?php p($l->t('View Projects')); ?></span>
            </a>
            <a href="<?php p($urlGenerator->linkToRoute('projectcheck.timeentry.index')); ?>" class="toolbar-action">
                <i data-lucide="bar-chart-3" class="lucide-icon" aria-hidden="true"></i>
                <span><?php p($l->t('View Time Entries')); ?></span>
            </a>
            <a href="<?php p($urlGenerator->linkToRoute('projectcheck.customer.index')); ?>" class="toolbar-action">
                <i data-lucide="users" class="lucide-icon" aria-hidden="true"></i>
                <span><?php p($l->t('View Customers')); ?></span>
            </a>
        </nav>

        <!-- Budget Alerts -->
        <?php if (isset($budgetAlerts) && !empty($budgetAlerts)): ?>
            <div class="section budget-alerts-section pc-section" aria-labelledby="dash-budget-alerts-title">
                <div class="section-header">
                    <h3 class="pc-section__title" id="dash-budget-alerts-title"><?php p($l->t('Budget Alerts')); ?></h3>
                    <p class="pc-section__intro"><?php p($l->t('Projects requiring attention')); ?></p>
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
        <section class="section stats-overview-section pc-section" aria-labelledby="dash-overview-stats-title">
            <div class="section-header">
                <h3 class="pc-section__title" id="dash-overview-stats-title"><?php p($l->t('Overview Statistics')); ?></h3>
                <p class="pc-section__intro"><?php p($l->t('Key metrics and project insights')); ?></p>
            </div>

            <ul class="overview-stats-compact" role="list">
                <li class="overview-stat-compact">
                    <i data-lucide="folder" class="lucide-icon" aria-hidden="true"></i>
                    <div class="stat-content">
                        <div class="stat-number" aria-describedby="dash-stat-projects-label"><?php p($fmt ? $fmt->number((int)($_['stats']['totalProjects'] ?? 0)) : (int)($_['stats']['totalProjects'] ?? 0)); ?></div>
                        <div class="stat-label" id="dash-stat-projects-label"><?php p($l->t('Projects')); ?></div>
                        <div class="stat-detail">
                            <span><?php p(($fmt ? $fmt->number((int)($_['stats']['activeProjects'] ?? 0)) : (int)($_['stats']['activeProjects'] ?? 0)) . ' ' . $l->t('active')); ?></span>
                        </div>
                    </div>
                </li>

                <li class="overview-stat-compact">
                    <i data-lucide="euro" class="lucide-icon" aria-hidden="true"></i>
                    <div class="stat-content">
                        <div class="stat-number" aria-describedby="dash-stat-budget-label"><?php p($fmt ? $fmt->currency((float)($_['stats']['totalBudget'] ?? 0)) : $currencyCode . ' ' . number_format((float)($_['stats']['totalBudget'] ?? 0), 0)); ?></div>
                        <div class="stat-label" id="dash-stat-budget-label"><?php p($l->t('Budget')); ?></div>
                        <div class="stat-detail">
                            <span><?php p(($fmt ? $fmt->percent((float)($_['stats']['consumptionPercentage'] ?? 0), 0) : ((int)($_['stats']['consumptionPercentage'] ?? 0)) . '%') . ' ' . $l->t('used')); ?></span>
                        </div>
                    </div>
                </li>

                <li class="overview-stat-compact">
                    <i data-lucide="clock" class="lucide-icon" aria-hidden="true"></i>
                    <div class="stat-content">
                        <div class="stat-number" aria-describedby="dash-stat-hours-label"><?php p($fmt ? $fmt->hours((float)($_['stats']['totalHours'] ?? 0)) : ((float)($_['stats']['totalHours'] ?? 0)) . 'h'); ?></div>
                        <div class="stat-label" id="dash-stat-hours-label"><?php p($l->t('Hours')); ?></div>
                        <div class="stat-detail">
                            <span><?php p($l->t('total')); ?></span>
                        </div>
                    </div>
                </li>

                <li class="overview-stat-compact">
                    <i data-lucide="users" class="lucide-icon" aria-hidden="true"></i>
                    <div class="stat-content">
                        <div class="stat-number" aria-describedby="dash-stat-customers-label"><?php p($fmt ? $fmt->number((int)($_['stats']['totalCustomers'] ?? 0)) : (int)($_['stats']['totalCustomers'] ?? 0)); ?></div>
                        <div class="stat-label" id="dash-stat-customers-label"><?php p($l->t('Customers')); ?></div>
                        <div class="stat-detail">
                            <span><?php p($l->t('active')); ?></span>
                        </div>
                    </div>
                </li>
            </ul>
        </section>

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
                                            <span class="summary-value"><?php p($fmt ? $fmt->hours($yearTotalHours) : number_format($yearTotalHours, 1) . 'h'); ?></span>
                                        </div>
                                        <div class="year-summary-item">
                                            <span class="summary-label"><?php p($l->t('Total Cost')); ?>:</span>
                                            <span class="summary-value"><?php p($fmt ? $fmt->currency($yearTotalCost) : $currencyCode . ' ' . number_format($yearTotalCost, 2)); ?></span>
                                        </div>
                                        <div class="year-summary-item">
                                            <span class="summary-label"><?php p($l->t('Entries')); ?>:</span>
                                            <span class="summary-value"><?php p($fmt ? $fmt->number($yearTotalEntries) : $yearTotalEntries); ?></span>
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
                                                        <span class="summary-value"><?php p($fmt ? $fmt->hours($customerData['total_hours']) : number_format($customerData['total_hours'], 1) . 'h'); ?></span>
                                                    </div>
                                                    <div class="customer-summary-item">
                                                        <span class="summary-label"><?php p($l->t('Cost')); ?>:</span>
                                                        <span class="summary-value"><?php p($fmt ? $fmt->currency($customerData['total_cost']) : $currencyCode . ' ' . number_format($customerData['total_cost'], 2)); ?></span>
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
                                                                        <i data-lucide="clock" class="lucide-icon" aria-hidden="true"></i>
                                                                    </div>
                                                                    <div class="stat-details">
                                                                        <div class="stat-value"><?php p($fmt ? $fmt->hours($projectData['total_hours']) : number_format($projectData['total_hours'], 1) . 'h'); ?></div>
                                                                        <div class="stat-label"><?php p($l->t('Hours')); ?></div>
                                                                    </div>
                                                                </div>
                                                                <div class="project-stat">
                                                                    <div class="stat-icon">
                                                                        <i data-lucide="euro" class="lucide-icon" aria-hidden="true"></i>
                                                                    </div>
                                                                    <div class="stat-details">
                                                                        <div class="stat-value"><?php p($fmt ? $fmt->currency($projectData['total_cost']) : $currencyCode . ' ' . number_format($projectData['total_cost'], 2)); ?></div>
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
                                            <i data-lucide="clock" class="lucide-icon" aria-hidden="true"></i>
                                            <?php p($fmt ? $fmt->hours($yearTotalHours) : number_format($yearTotalHours, 1) . 'h'); ?>
                                        </span>
                                        <span class="summary-item">
                                            <i data-lucide="euro" class="lucide-icon" aria-hidden="true"></i>
                                            <?php p($fmt ? $fmt->currency($yearTotalCost) : $currencyCode . ' ' . number_format($yearTotalCost, 2)); ?>
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
                                                        title="<?php p($displayName); ?>"
                                                        tabindex="0"
                                                        role="img"
                                                        aria-label="<?php p($displayName); ?>">
                                                        <?php p($icon); ?>
                                                    </span>
                                                    <span class="project-type-label"><?php p($displayName); ?></span>
                                                </h5>
                                                <div class="type-stats">
                                                    <div class="stat-item">
                                                        <span class="stat-value"><?php p($fmt ? $fmt->hours($typeData['total_hours']) : number_format($typeData['total_hours'], 1) . 'h'); ?></span>
                                                        <span class="stat-label"><?php p($l->t('Hours')); ?></span>
                                                    </div>
                                                    <div class="stat-item">
                                                        <span class="stat-value"><?php p($fmt ? $fmt->currency($typeData['total_cost']) : $currencyCode . ' ' . number_format($typeData['total_cost'], 2)); ?></span>
                                                        <span class="stat-label"><?php p($l->t('Cost')); ?></span>
                                                    </div>
                                                    <div class="stat-item">
                                                        <span class="stat-value"><?php p($fmt ? $fmt->number((int)$typeData['entry_count']) : $typeData['entry_count']); ?></span>
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
                        <button class="info-popup-trigger" data-action="show-productivity-info" title="<?php p($l->t('Click for detailed explanation')); ?>" aria-label="<?php p($l->t('Show productivity analysis explanation')); ?>">?</button>
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
                                                <i data-lucide="dollar-sign" class="lucide-icon" aria-hidden="true"></i>
                                            </div>
                                        </div>
                                        <div class="card-content">
                                            <div class="stat-item">
                                                <span class="stat-value"><?php p($fmt ? $fmt->hours($yearData['billable']['total_hours']) : number_format($yearData['billable']['total_hours'], 1) . 'h'); ?></span>
                                                <span class="stat-label"><?php p($l->t('Hours')); ?></span>
                                            </div>
                                            <div class="stat-item">
                                                <span class="stat-value"><?php p($fmt ? $fmt->currency($yearData['billable']['total_cost']) : $currencyCode . ' ' . number_format($yearData['billable']['total_cost'], 2)); ?></span>
                                                <span class="stat-label"><?php p($l->t('Revenue')); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="productivity-card overhead">
                                        <div class="card-header">
                                            <h5><?php p($l->t('Overhead Work')); ?></h5>
                                            <div class="card-icon">
                                                <i data-lucide="settings" class="lucide-icon" aria-hidden="true"></i>
                                            </div>
                                        </div>
                                        <div class="card-content">
                                            <div class="stat-item">
                                                <span class="stat-value"><?php p($fmt ? $fmt->hours($yearData['overhead']['total_hours']) : number_format($yearData['overhead']['total_hours'], 1) . 'h'); ?></span>
                                                <span class="stat-label"><?php p($l->t('Hours')); ?></span>
                                            </div>
                                            <div class="stat-item">
                                                <span class="stat-value"><?php p($fmt ? $fmt->currency($yearData['overhead']['total_cost']) : $currencyCode . ' ' . number_format($yearData['overhead']['total_cost'], 2)); ?></span>
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
                                    <div class="ratio-bar" role="img" aria-label="<?php p($l->t('Billable %1$s, overhead %2$s', [($fmt ? $fmt->percent($billablePercentage, 1) : round($billablePercentage, 1) . '%'), ($fmt ? $fmt->percent($overheadPercentage, 1) : round($overheadPercentage, 1) . '%')])); ?>">
                                        <div class="ratio-fill billable" style="width: <?php p($billablePercentage); ?>%"></div>
                                        <div class="ratio-fill overhead" style="width: <?php p($overheadPercentage); ?>%"></div>
                                    </div>
                                    <div class="ratio-labels">
                                        <span class="ratio-label billable"><?php p(($fmt ? $fmt->percent($billablePercentage, 1) : round($billablePercentage, 1) . '%') . ' ' . $l->t('Billable')); ?></span>
                                        <span class="ratio-label overhead"><?php p(($fmt ? $fmt->percent($overheadPercentage, 1) : round($overheadPercentage, 1) . '%') . ' ' . $l->t('Overhead')); ?></span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recent Projects and Time Entries (Side by Side) -->
        <div class="two-column-layout">
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
                    <?php if (!empty($_['canCreateProject'])): ?>
                    <a href="<?php p($urlGenerator->linkToRoute('projectcheck.project.create')); ?>" class="button primary">
                        <i data-lucide="plus" class="lucide-icon"></i>
                        <?php p($l->t('Create Project')); ?>
                    </a>
                    <?php endif; ?>
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
                                            title="<?php p($displayName); ?>"
                                            tabindex="0"
                                            role="img"
                                            aria-label="<?php p($displayName); ?>">
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
                                                    <?php p($fmt ? $fmt->currency($budgetInfo['remaining_budget']) : $currencyCode . ' ' . number_format($budgetInfo['remaining_budget'], 2)); ?>
                                                </span>
                                                <span class="budget-separator"><?php p($l->t('remaining of')); ?></span>
                                                <span class="budget-total"><?php p($fmt ? $fmt->currency($budgetInfo['total_budget']) : $currencyCode . ' ' . number_format($budgetInfo['total_budget'], 2)); ?></span>
                                            </span>
                                        </div>
                                        <div class="detail-row">
                                            <span class="detail-label"><?php p($l->t('Progress:')); ?></span>
                                            <span class="detail-value progress-info">
                                                <span class="usage-stats">
                                                    <?php p(($fmt ? $fmt->percent($budgetInfo['consumption_percentage'], 0) : round($budgetInfo['consumption_percentage']) . '%') . ' ' . $l->t('used')); ?>
                                                    • <?php p($fmt ? $fmt->hours($budgetInfo['used_hours']) : number_format($budgetInfo['used_hours'], 1) . 'h'); ?>
                                                    <?php p($l->t('logged')); ?>
                                                </span>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <div class="detail-row">
                                            <span class="detail-label"><?php p($l->t('Budget:')); ?></span>
                                            <span class="detail-value"><?php p($fmt ? $fmt->currency($project->getTotalBudget() ?? 0) : $currencyCode . ' ' . number_format($project->getTotalBudget() ?? 0, 2)); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if ($budgetInfo): ?>
                                    <div class="card-progress-section">
                                        <div class="budget-progress-bar dashboard" role="progressbar"
                                             aria-valuemin="0" aria-valuemax="100"
                                             aria-valuenow="<?php p((int)round($budgetInfo['consumption_percentage'])); ?>"
                                             aria-label="<?php p($l->t('Budget consumption: %s', [($fmt ? $fmt->percent($budgetInfo['consumption_percentage'], 0) : round($budgetInfo['consumption_percentage']) . '%')])); ?>">
                                            <div class="budget-progress-fill <?php p($budgetInfo['warning_level']); ?>"
                                                style="width: <?php p(min(100, $budgetInfo['consumption_percentage'])); ?>%"></div>
                                        </div>
                                        <div class="progress-labels">
                                            <span class="progress-label-left"><?php p($fmt ? $fmt->currency(0) : $currencyCode . ' 0'); ?></span>
                                            <span class="progress-label-center <?php p($budgetInfo['warning_level']); ?>">
                                                <?php p($fmt ? $fmt->percent($budgetInfo['consumption_percentage'], 0) : round($budgetInfo['consumption_percentage']) . '%'); ?>
                                            </span>
                                            <span class="progress-label-right"><?php p($fmt ? $fmt->currency($budgetInfo['total_budget']) : $currencyCode . ' ' . number_format($budgetInfo['total_budget'], 0)); ?></span>
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
                                    <span class="duration"><?php p($fmt ? $fmt->hours($entry->getHours()) : ($entry->getHours() . 'h')); ?></span>
                                </div>
                            </div>
                            <div class="card-content">
                                <p class="card-description"><?php p($entry->getDescription() ?: $l->t('No description')); ?></p>
                                <div class="card-meta">
                                    <div class="meta-item">
                                        <i data-lucide="calendar" class="lucide-icon primary" aria-hidden="true"></i>
                                        <span><?php p($entry->getDate() ? ($fmt ? $fmt->date($entry->getDate()) : $entry->getDate()->format('d.m.Y')) : ''); ?></span>
                                    </div>
                                    <div class="meta-item">
                                        <i data-lucide="user" class="lucide-icon primary" aria-hidden="true"></i>
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
        </div>
        <!-- End Two Column Layout -->
    </div>
</div>

<?php /* Icons hydrated by js/common/icons.js (audit ref. AUDIT-FINDINGS H22).
       Productivity info popup behavior lives in js/dashboard.js so every
       modal in the app shares one accessibility primitive
       (audit ref. C13/D17 - ProjectCheckModalA11y). */ ?>

<!-- Productivity Info Popup Modal -->
<div id="productivity-info-popup" class="productivity-info-popup" style="display: none;" hidden
     role="dialog" aria-modal="true" aria-labelledby="productivity-info-popup-title" tabindex="-1">
    <div class="popup-content">
        <div class="popup-header">
            <h3 id="productivity-info-popup-title"><?php p($l->t('Productivity Analysis Explanation')); ?></h3>
            <button type="button" class="popup-close" data-action="hide-productivity-info" aria-label="<?php p($l->t('Close')); ?>">
                <span aria-hidden="true">×</span>
            </button>
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