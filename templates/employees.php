<?php

/**
 * Employees template for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

use OCP\Util;

Util::addScript('projectcheck', 'employees');
Util::addStyle('projectcheck', 'dashboard');
Util::addStyle('projectcheck', 'projects');
Util::addStyle('projectcheck', 'custom-icons');
Util::addStyle('projectcheck', 'navigation');
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<div id="app-content">
    <div id="app-content-wrapper">
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb-container">
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <ol>
                    <li aria-current="page"><?php p($l->t('Employees')); ?></li>
                </ol>
            </nav>
        </div>

        <!-- Page Header -->
        <div class="section page-header-section">
            <div class="header-content">
                <div class="header-text">
                    <div class="header-details">
                        <h2><?php p($l->t('Employee Overview')); ?></h2>
                        <p><?php p($l->t('Track employee performance and time tracking statistics')); ?></p>
                        <div class="employee-meta">
                            <div class="meta-item">
                                <i data-lucide="users" class="lucide-icon primary"></i>
                                <span><?php p(count($_['usersWithTimeEntries'])); ?> <?php p($l->t('Active Employees')); ?></span>
                            </div>
                            <div class="meta-item">
                                <i data-lucide="calendar" class="lucide-icon primary"></i>
                                <span><?php p(date('Y')); ?> <?php p($l->t('Performance')); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="header-actions">
                    <a href="<?php p($urlGenerator->linkToRoute('projectcheck.dashboard.index')); ?>" class="button secondary">
                        <i data-lucide="arrow-left" class="lucide-icon"></i>
                        <?php p($l->t('Back to Dashboard')); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Team Overview -->
        <div class="section team-overview">
            <div class="section-header">
                <h3><i data-lucide="users" class="lucide-icon"></i> <?php p($l->t('Team Overview')); ?></h3>
            </div>
            <div class="section-content">
                <?php
                // Calculate team totals
                $teamTotalHours = 0;
                $teamTotalCost = 0;
                $teamTotalEmployees = 0;

                if (!empty($_['employeeComparisonStats'])) {
                    foreach ($_['employeeComparisonStats'] as $employee) {
                        $teamTotalHours += $employee['total_hours'];
                        $teamTotalCost += $employee['total_cost'];
                        $teamTotalEmployees++;
                    }
                }
                ?>

                <div class="overview-cards">
                    <div class="overview-card">
                        <div class="card-icon">
                            <i data-lucide="users" class="lucide-icon"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-value"><?php p($teamTotalEmployees); ?></div>
                            <div class="card-label"><?php p($l->t('Employees')); ?></div>
                        </div>
                    </div>

                    <div class="overview-card">
                        <div class="card-icon">
                            <i data-lucide="clock" class="lucide-icon"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-value"><?php p(number_format($teamTotalHours, 1)); ?>h</div>
                            <div class="card-label"><?php p($l->t('Total Hours')); ?></div>
                        </div>
                    </div>

                    <div class="overview-card">
                        <div class="card-icon">
                            <i data-lucide="euro" class="lucide-icon"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-value">€<?php p(number_format($teamTotalCost, 2)); ?></div>
                            <div class="card-label"><?php p($l->t('Total Revenue')); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Employee List -->
        <?php if (!empty($_['employeeComparisonStats'])): ?>
            <div class="section employee-list-section">
                <div class="section-header">
                    <h3><i data-lucide="list" class="lucide-icon"></i> <?php p($l->t('Employees')); ?></h3>
                </div>
                <div class="section-content">
                    <div class="employee-grid">
                        <?php foreach ($_['employeeComparisonStats'] as $index => $employee): ?>
                            <div class="employee-card">
                                <div class="employee-header">
                                    <div class="employee-rank">#<?php p($index + 1); ?></div>
                                    <h4 class="employee-name">
                                        <a href="<?php p($urlGenerator->linkToRoute('projectcheck.employee.show', ['userId' => $employee['user_id']])); ?>">
                                            <?php p($employee['user_display_name']); ?>
                                        </a>
                                    </h4>
                                </div>
                                <div class="employee-stats">
                                    <div class="stat">
                                        <i data-lucide="clock" class="lucide-icon"></i>
                                        <span class="stat-value"><?php p(number_format($employee['total_hours'], 1)); ?>h</span>
                                    </div>
                                    <div class="stat">
                                        <i data-lucide="euro" class="lucide-icon"></i>
                                        <span class="stat-value">€<?php p(number_format($employee['total_cost'], 2)); ?></span>
                                    </div>
                                    <div class="stat">
                                        <i data-lucide="trending-up" class="lucide-icon"></i>
                                        <span class="stat-value">€<?php p(number_format($employee['avg_hourly_rate'], 2)); ?>/h</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Detailed Employee Breakdown -->
        <?php if (!empty($_['detailedEmployeeProjectTypeStats'])): ?>
            <div class="section detailed-breakdown-section">
                <div class="section-header">
                    <h3><i data-lucide="pie-chart" class="lucide-icon"></i> <?php p($l->t('Employee Work Breakdown')); ?></h3>
                    <p><?php p($l->t('See how each employee worked on different project types over the years')); ?></p>
                </div>
                <div class="section-content">
                    <div class="breakdown-container">
                        <?php foreach ($_['detailedEmployeeProjectTypeStats'] as $year => $yearData): ?>
                            <div class="year-breakdown">
                                <div class="year-header">
                                    <h4><?php p($year); ?></h4>
                                    <?php
                                    $yearTotalHours = 0;
                                    $yearTotalCost = 0;
                                    foreach ($yearData as $employeeData) {
                                        foreach ($employeeData['by_type'] as $typeData) {
                                            $yearTotalHours += $typeData['total_hours'];
                                            $yearTotalCost += $typeData['total_cost'];
                                        }
                                    }
                                    ?>
                                    <div class="year-summary">
                                        <span class="summary-item">
                                            <i data-lucide="clock" class="lucide-icon"></i>
                                            <?php p(number_format($yearTotalHours, 1)); ?>h total
                                        </span>
                                        <span class="summary-item">
                                            <i data-lucide="euro" class="lucide-icon"></i>
                                            €<?php p(number_format($yearTotalCost, 2)); ?> total
                                        </span>
                                    </div>
                                </div>

                                <div class="employees-breakdown">
                                    <?php foreach ($yearData as $userId => $employeeData): ?>
                                        <div class="employee-breakdown-card">
                                            <div class="employee-breakdown-header">
                                                <h5 class="employee-name">
                                                    <a href="<?php p($urlGenerator->linkToRoute('projectcheck.employee.show', ['userId' => $userId])); ?>">
                                                        <i data-lucide="user" class="lucide-icon"></i>
                                                        <?php p($employeeData['user_display_name']); ?>
                                                    </a>
                                                </h5>
                                                <?php
                                                $employeeTotalHours = array_sum(array_column($employeeData['by_type'], 'total_hours'));
                                                $employeeTotalCost = array_sum(array_column($employeeData['by_type'], 'total_cost'));
                                                ?>
                                                <div class="employee-totals">
                                                    <span class="total-item">
                                                        <i data-lucide="clock" class="lucide-icon"></i>
                                                        <?php p(number_format($employeeTotalHours, 1)); ?>h
                                                    </span>
                                                    <span class="total-item">
                                                        <i data-lucide="euro" class="lucide-icon"></i>
                                                        €<?php p(number_format($employeeTotalCost, 2)); ?>
                                                    </span>
                                                </div>
                                            </div>

                                            <div class="project-types-breakdown">
                                                <?php foreach ($employeeData['by_type'] as $projectType => $typeData): ?>
                                                    <div class="project-type-row">
                                                        <div class="project-type-info">
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

                                                            $displayName = $displayNames[$projectType] ?? ucfirst($projectType);
                                                            $icon = $iconMapping[$projectType] ?? '📋';
                                                            ?>
                                                            <div class="project-type-display">
                                                                <span class="project-type-icon"
                                                                    data-project-type="<?php p($projectType); ?>"
                                                                    title="<?php p($displayName); ?>">
                                                                    <?php p($icon); ?>
                                                                </span>
                                                                <span class="project-type-text"><?php p($displayName); ?></span>
                                                            </div>
                                                        </div>
                                                        <div class="project-type-stats">
                                                            <span class="stat">
                                                                <i data-lucide="clock" class="lucide-icon"></i>
                                                                <?php p(number_format($typeData['total_hours'], 1)); ?>h
                                                            </span>
                                                            <span class="stat">
                                                                <i data-lucide="euro" class="lucide-icon"></i>
                                                                €<?php p(number_format($typeData['total_cost'], 2)); ?>
                                                            </span>
                                                            <span class="percentage">
                                                                <?php p($employeeTotalHours > 0 ? round(($typeData['total_hours'] / $employeeTotalHours) * 100, 1) : 0); ?>%
                                                            </span>
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
    </div>
</div>

<script nonce="<?php p($_['cspNonce']) ?>">
    // Local SVG icon library
    const svgIcons = {
        users: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        user: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        calendar: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
        'trending-up': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><polyline points="22,7 13.5,15.5 8.5,10.5 2,17"/><polyline points="16,7 22,7 22,13"/></svg>',
        'bar-chart-3': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>',
        'arrow-left': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>',
        clock: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>',
        euro: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.25.5-2.5 1.5-3.5Z"/></svg>',
        eye: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>',
        'pie-chart': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>',
        list: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>'
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
</script>