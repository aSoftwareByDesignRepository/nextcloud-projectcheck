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
Util::addStyle('projectcheck', 'time-entries');
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<div id="app-content" role="main" class="employees-page">
    <div id="app-content-wrapper">
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb-container">
            <nav class="breadcrumb" aria-label="<?php p($l->t('Breadcrumb')); ?>">
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

        <!-- Team Overview Stats -->
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

        <!-- Search and Filters -->
        <div class="section">
            <div class="filters-container">
                <div class="search-input-wrapper">
					<label class="u-visually-hidden" for="employee-search"><?php p($l->t('Search employees...')); ?></label>
                    <input type="search" id="employee-search" class="search-input" autocomplete="off"
                        placeholder="<?php p($l->t('Search employees...')); ?>"
                        value="<?php p($_['filters']['search'] ?? ''); ?>"
                        aria-describedby="employee-search-hint">
					<p class="u-visually-hidden" id="employee-search-hint"><?php p($l->t('Filters the table below. Use Apply to search on the server.')); ?></p>
                </div>

                <!-- Action Buttons -->
                <div class="filter-actions">
                    <button id="apply-filters" class="btn btn-primary" type="button">
                        <span class="btn-icon">🔍</span>
                        <?php p($l->t('Apply Filters')); ?>
                    </button>
                    <button id="clear-filters" class="btn btn-secondary" type="button">
                        <span class="btn-icon">🔄</span>
                        <?php p($l->t('Reset Search')); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Employee Table -->
        <div class="section">
            <?php if (empty($_['employeeComparisonStats'])): ?>
                <div class="emptycontent">
                    <div class="icon-time"></div>
                    <h2><?php p($l->t('No employees found')); ?></h2>
                    <p><?php p($l->t('No employees have logged time entries yet.')); ?></p>
                </div>
            <?php else: ?>
                <div class="grid-container employees-table-wrap">
					<table class="employees-table" role="table" aria-label="<?php p($l->t('Employees')); ?>">
                        <thead>
                            <tr>
                                <th><?php p($l->t('Rank')); ?></th>
                                <th><?php p($l->t('Employee')); ?></th>
                                <th><?php p($l->t('Total Hours')); ?></th>
                                <th><?php p($l->t('Total Revenue')); ?></th>
                                <th><?php p($l->t('Avg. Hourly Rate')); ?></th>
                                <th><?php p($l->t('Actions')); ?></th>
                            </tr>
                        </thead>
                        <tbody id="employees-tbody">
                            <!-- No Results Message (Hidden by default) -->
                            <tr id="no-results-row" style="display: none;">
                                <td colspan="6">
                                    <div class="no-results-message">
                                        <div class="no-results-icon">🔍</div>
                                        <h3><?php p($l->t('No employees match your search')); ?></h3>
                                        <p><?php p($l->t('Try adjusting your search criteria.')); ?></p>
                                        <button id="clear-search-inline" class="btn btn-secondary">
                                            <span class="btn-icon">🔄</span>
                                            <?php p($l->t('Reset Search')); ?>
                                        </button>
                                    </div>
                                </td>
                            </tr>

                            <?php foreach ($_['employeeComparisonStats'] as $index => $employee): ?>
                                <tr data-employee-name="<?php p(strtolower($employee['user_display_name'])); ?>"
                                    data-employee-id="<?php p($employee['user_id']); ?>">
                                    <td>
                                        <strong>#<?php p($index + 1); ?></strong>
                                    </td>
                                    <td class="employee-name-cell">
                                        <a href="<?php p($urlGenerator->linkToRoute('projectcheck.employee.show', ['userId' => $employee['user_id']])); ?>">
                                            <?php p($employee['user_display_name']); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <i data-lucide="clock" class="lucide-icon"></i>
                                        <?php p(number_format($employee['total_hours'], 1)); ?>h
                                    </td>
                                    <td>
                                        <i data-lucide="euro" class="lucide-icon"></i>
                                        €<?php p(number_format($employee['total_cost'], 2)); ?>
                                    </td>
                                    <td>
                                        <i data-lucide="trending-up" class="lucide-icon"></i>
                                        €<?php p(number_format($employee['avg_hourly_rate'], 2)); ?>/h
                                    </td>
                                    <td>
                                        <a href="<?php p($urlGenerator->linkToRoute('projectcheck.employee.show', ['userId' => $employee['user_id']])); ?>"
                                           class="button primary" title="<?php p($l->t('View Details')); ?>">
                                            <i data-lucide="eye" class="lucide-icon"></i>
                                            <?php p($l->t('View Details')); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php
                    $pagination = $_['pagination'] ?? ['page' => 1, 'totalPages' => 1, 'totalEntries' => count($_['employeeComparisonStats'] ?? []), 'perPage' => count($_['employeeComparisonStats'] ?? [])];
                    $currentPage = max(1, (int)($pagination['page'] ?? 1));
                    $totalPages = max(1, (int)($pagination['totalPages'] ?? 1));
                    $totalEntries = (int)($pagination['totalEntries'] ?? 0);
                    $perPage = (int)($pagination['perPage'] ?? 0);
                    $baseUrl = $urlGenerator->linkToRoute('projectcheck.employee.index');
                    $baseQuery = $_['filters'] ?? [];
                ?>
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
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
                            <a class="btn btn-secondary <?php echo $currentPage <= 1 ? 'disabled' : ''; ?>"
                               href="<?php p($currentPage <= 1 ? '#' : $baseUrl . '?' . http_build_query($prevQuery)); ?>"
                               aria-disabled="<?php echo $currentPage <= 1 ? 'true' : 'false'; ?>">
                                ‹ <?php p($l->t('Previous')); ?>
                            </a>
                            <a class="btn btn-secondary <?php echo $currentPage >= $totalPages ? 'disabled' : ''; ?>"
                               href="<?php p($currentPage >= $totalPages ? '#' : $baseUrl . '?' . http_build_query($nextQuery)); ?>"
                               aria-disabled="<?php echo $currentPage >= $totalPages ? 'true' : 'false'; ?>">
                                <?php p($l->t('Next')); ?> ›
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
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
