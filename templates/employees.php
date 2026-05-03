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
        <?php $isGlobalViewer = !empty($_['isGlobalViewer']); ?>
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
                        <h2><?php p($isGlobalViewer ? $l->t('Employee Overview') : $l->t('Your work overview')); ?></h2>
                        <p><?php p($isGlobalViewer ? $l->t('Track employee performance and time tracking statistics') : $l->t('Review your own time tracking and yearly performance.')); ?></p>
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

        <?php if (!$isGlobalViewer): ?>
            <div class="section">
                <div class="section-content">
                    <div class="pc-scope-banner" role="status" aria-live="polite">
                        <div class="pc-scope-banner__icon">
                            <i data-lucide="info" class="lucide-icon primary" aria-hidden="true"></i>
                        </div>
                        <div class="pc-scope-banner__content">
                            <h3><?php p($l->t('Only your data is shown')); ?></h3>
                            <p><?php p($l->t('Employee analytics are restricted to your own time entries unless you are an administrator.')); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Team Overview Stats -->
        <div class="section team-overview">
            <div class="section-header">
                    <h3><i data-lucide="users" class="lucide-icon"></i> <?php p($isGlobalViewer ? $l->t('Team Overview') : $l->t('Personal Overview')); ?></h3>
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

<?php /* Icons hydrated by js/common/icons.js (audit ref. AUDIT-FINDINGS H22). */ ?>
