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

<style nonce="<?php p($_['cspNonce'] ?? '') ?>">
    /* Search and Filter Styles */
    .filters-container {
        background: linear-gradient(135deg, var(--color-main-background) 0%, var(--color-background-hover) 100%);
        border: 2px solid var(--color-border);
        border-radius: 12px;
        padding: 24px;
        margin-bottom: 24px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    }

    .search-input-wrapper {
        position: relative;
        flex: 1;
        min-width: 320px;
    }

    .search-input {
        width: 100%;
        padding: 12px 16px 12px 44px;
        border: 2px solid var(--color-border);
        border-radius: 8px;
        font-size: 15px;
        background: var(--color-main-background);
        color: var(--color-text);
        transition: all 0.3s ease;
    }

    .search-input:focus {
        outline: none;
        border-color: var(--color-primary-element);
        box-shadow: 0 0 0 3px rgba(0, 130, 201, 0.1);
    }

    .search-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 18px;
        opacity: 0.6;
    }

    .filter-actions {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    /* Table Styles */
    .employees-table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
        background: var(--color-main-background);
        border-radius: 8px;
        overflow: hidden;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    }

    .employees-table thead {
        background: var(--color-background-hover);
    }

    .employees-table th {
        padding: 16px;
        text-align: left;
        font-weight: 600;
        color: var(--color-main-text);
        border-bottom: 2px solid var(--color-border);
    }

    .employees-table tbody tr {
        border-bottom: 1px solid var(--color-border);
        transition: background-color 0.2s ease;
    }

    .employees-table tbody tr:hover {
        background: var(--color-background-hover);
    }

    .employees-table td {
        padding: 16px;
        vertical-align: middle;
    }

    /* Icon alignment in table cells */
    .employees-table td .lucide-icon {
        width: 16px;
        height: 16px;
        display: inline-block;
        vertical-align: middle;
        margin-right: 4px;
        position: relative;
        top: -1px;
    }

    .employees-table .button .lucide-icon {
        width: 16px;
        height: 16px;
        margin-right: 6px;
        vertical-align: middle;
        position: relative;
        top: -1px;
    }

    .employee-name-cell {
        font-weight: 500;
    }

    .employee-name-cell a {
        color: var(--color-primary-element);
        text-decoration: none;
    }

    .employee-name-cell a:hover {
        text-decoration: underline;
    }

    /* Overview cards icon alignment */
    .overview-card .lucide-icon {
        width: 24px;
        height: 24px;
        display: inline-block;
    }

    /* Header actions icon alignment */
    .header-actions .button .lucide-icon {
        width: 18px;
        height: 18px;
        vertical-align: middle;
        position: relative;
        top: -1px;
        margin-right: 6px;
    }

    /* No results message */
    #no-results-row {
        background: var(--color-background-hover);
    }

    .no-results-message {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 40px 20px;
    }

    .no-results-icon {
        font-size: 48px;
        margin-bottom: 16px;
        opacity: 0.5;
    }

    .no-results-message h3 {
        margin: 0 0 8px 0;
        color: var(--color-main-text);
        font-size: 18px;
        font-weight: 600;
    }

    .no-results-message p {
        margin: 0 0 16px 0;
        color: var(--color-text-lighter);
        font-size: 14px;
    }
</style>

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
                <!-- Search Input -->
                <div class="search-input-wrapper">
                    <input type="text" id="employee-search" class="search-input"
                        placeholder="<?php p($l->t('Search employees...')); ?>">
                    <span class="search-icon">🔍</span>
                </div>

                <!-- Action Buttons -->
                <div class="filter-actions">
                    <button id="clear-filters" class="btn btn-secondary">
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
                <div class="grid-container">
                    <table class="employees-table">
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
