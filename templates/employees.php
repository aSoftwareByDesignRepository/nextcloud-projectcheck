<?php

/**
 * Employees template for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

use OCP\Util;

Util::addScript('projectcheck', 'common/export-menu');
Util::addScript('projectcheck', 'employees');
Util::addScript('projectcheck', 'common/icons');
Util::addStyle('projectcheck', 'projects');
Util::addStyle('projectcheck', 'navigation');
Util::addStyle('projectcheck', 'common/stats-panel');
Util::addStyle('projectcheck', 'common/list-table');
Util::addStyle('projectcheck', 'common/list-layout');
$fmt = $_['fmt'] ?? null;
$currencyCode = isset($_['orgCurrency']) && is_string($_['orgCurrency']) ? strtoupper(trim($_['orgCurrency'])) : 'EUR';
if (preg_match('/^[A-Z]{3}$/', $currencyCode) !== 1) {
	$currencyCode = 'EUR';
}
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<?php
$isGlobalViewer = !empty($_['isGlobalViewer']);
$pageId = 'employees';
$pageTitle = $isGlobalViewer ? $l->t('Employees') : $l->t('Your work overview');
$pageHelp = $isGlobalViewer
	? $l->t('Track employee performance and time tracking statistics')
	: $l->t('Review your own time tracking and yearly performance.');
ob_start(); ?>
            <a href="<?php p($urlGenerator->linkToRoute('projectcheck.dashboard.index')); ?>" class="button secondary">
                <span data-lucide="arrow-left" class="lucide-icon" aria-hidden="true"></span>
                <?php p($l->t('Back to Dashboard')); ?>
            </a>
<?php
$pageHeaderActionsHtml = ob_get_clean();
$pageHeaderActionsLabel = $l->t('Page actions');
include __DIR__ . '/common/page-start.php';
?>
<?php
$baseUrl = $urlGenerator->linkToRoute('projectcheck.employee.index');
$hasSearch = ($_['filters']['search'] ?? '') !== '';
$searchValue = (string)($_['filters']['search'] ?? '');

// Column labels reused for both <th> headers and per-cell data-labels
// (the responsive stacked layout reads data-label to caption each value).
$colRank = $l->t('Rank');
$colEmployee = $l->t('Employee');
$colHours = $l->t('Total Hours');
$colRevenue = $l->t('Total Revenue');
$colRate = $l->t('Avg. Hourly Rate');
$colActions = $l->t('Actions');
?>
        <nav class="breadcrumb breadcrumb--inline" aria-label="<?php p($l->t('Breadcrumb')); ?>">
            <ol>
                <li><a href="<?php p($urlGenerator->linkToRoute('projectcheck.dashboard.index')); ?>"><?php p($l->t('Dashboard')); ?></a></li>
                <li aria-current="page"><?php p($l->t('Employees')); ?></li>
            </ol>
        </nav>

        <?php if (!$isGlobalViewer): ?>
            <div class="section pc-section" aria-labelledby="pc-employees-scope-heading">
                <div class="section-header">
                    <h3 id="pc-employees-scope-heading"><i data-lucide="info" class="lucide-icon primary" aria-hidden="true"></i> <?php p($l->t('Only your data is shown')); ?></h3>
                    <p><?php p($l->t('Employee analytics are restricted to your own time entries unless you are an administrator.')); ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Team Overview Stats -->
        <section class="section pc-stats-panel pc-section team-overview" aria-labelledby="employees-stats-title">
            <div class="section-header">
                <h3 id="employees-stats-title">
                    <i data-lucide="users" class="lucide-icon primary" aria-hidden="true"></i>
                    <?php p($isGlobalViewer ? $l->t('Team Overview') : $l->t('Personal Overview')); ?>
                </h3>
                <p><?php p($l->t('Hours and costs for people you can see.')); ?></p>
            </div>
            <div class="section-content">
                <?php
                // Team totals are computed server-side across the full (search-filtered)
                // result set so the cards stay correct across pagination.
                $teamTotals = $_['teamTotals'] ?? null;
                $teamTotalEmployees = (int)($teamTotals['employees'] ?? count($_['employeeComparisonStats'] ?? []));
                $teamTotalHours = (float)($teamTotals['hours'] ?? 0);
                $teamTotalCost = (float)($teamTotals['cost'] ?? 0);
                ?>

                <ul class="pc-stats-grid" role="list">
                    <li class="pc-stat-card">
                        <span class="pc-stat-card__icon" aria-hidden="true"><i data-lucide="users" class="lucide-icon"></i></span>
                        <div class="pc-stat-card__body">
                            <div class="pc-stat-card__value"><?php p($teamTotalEmployees); ?></div>
                            <div class="pc-stat-card__label"><?php p($l->t('Employees')); ?></div>
                        </div>
                    </li>

                    <li class="pc-stat-card">
                        <span class="pc-stat-card__icon" aria-hidden="true"><i data-lucide="clock" class="lucide-icon"></i></span>
                        <div class="pc-stat-card__body">
                            <div class="pc-stat-card__value"><?php p(number_format($teamTotalHours, 1)); ?>h</div>
                            <div class="pc-stat-card__label"><?php p($l->t('Total Hours')); ?></div>
                        </div>
                    </li>

                    <li class="pc-stat-card">
                        <span class="pc-stat-card__icon" aria-hidden="true"><i data-lucide="euro" class="lucide-icon"></i></span>
                        <div class="pc-stat-card__body">
                            <div class="pc-stat-card__value"><?php p($fmt ? $fmt->currency((float)$teamTotalCost) : $currencyCode . ' ' . number_format((float)$teamTotalCost, 2)); ?></div>
                            <div class="pc-stat-card__label"><?php p($l->t('Total Revenue')); ?></div>
                        </div>
                    </li>
                </ul>
            </div>
        </section>

        <!-- Filters + employees table (same list-panel pattern as customers / projects) -->
        <div class="section pc-list-panel pc-section" aria-labelledby="pc-employees-list-heading">
            <div class="section-header">
                <h3 id="pc-employees-list-heading"><i data-lucide="users" class="lucide-icon primary" aria-hidden="true"></i> <?php p($l->t('Employees')); ?></h3>
                <p><?php p($l->t('Search and filter')); ?></p>
            </div>
            <div class="pc-list-panel__toolbar">
                <form class="filters-container employees-search-form" method="get" action="<?php p($baseUrl); ?>" role="search" aria-label="<?php p($l->t('Search employees')); ?>">
                    <div class="search-input-wrapper">
                        <span class="pc-list-search-icon" aria-hidden="true"><i data-lucide="search" class="lucide-icon"></i></span>
                        <label class="pc-sr-only" for="employee-search"><?php p($l->t('Search employees')); ?></label>
                        <input type="search" id="employee-search" name="search" class="search-input"
                            placeholder="<?php p($l->t('Search employees...')); ?>"
                            value="<?php p($searchValue); ?>"
                            aria-label="<?php p($l->t('Search employees')); ?>"
                            aria-describedby="employee-search-hint"
                            autocomplete="off">
                    </div>
                    <p class="pc-sr-only" id="employee-search-hint"><?php p($l->t('Type a name and press Enter or Apply to filter the list.')); ?></p>

                    <div class="filters-row">
                        <button type="submit" id="apply-filters" class="button primary">
                            <span data-lucide="search" class="lucide-icon" aria-hidden="true"></span>
                            <?php p($l->t('Apply Filters')); ?>
                        </button>
                        <a class="button secondary" id="clear-filters" href="<?php p($baseUrl); ?>">
                            <span data-lucide="rotate-ccw" class="lucide-icon" aria-hidden="true"></span>
                            <?php p($l->t('Clear Filters')); ?>
                        </a>
                        <?php
                        $exportUrl = (string)($_['exportUrl'] ?? '');
                        if ($exportUrl === '' && isset($urlGenerator) && is_object($urlGenerator)) {
                        	$exportUrl = (string)$urlGenerator->linkToRoute('projectcheck.employee.export');
                        }
                        $exportEntityLabel = 'employees';
                        $exportFilterKeys = 'search';
                        $exportSuccessMsg = 'Exported {count} employees';
                        $exportIncludeSort = false;
                        $exportMenuId = 'pc-export-menu-employees';
                        include __DIR__ . '/parts/export-menu.php';
                        ?>
                    </div>
                </form>
            </div>

            <?php if (empty($_['employeeComparisonStats'])): ?>
                <div class="emptycontent" role="status">
                    <?php if ($hasSearch): ?>
                        <span class="emptycontent__icon" aria-hidden="true"><i data-lucide="search-x" class="lucide-icon"></i></span>
                        <h2><?php p($l->t('No employees match your search')); ?></h2>
                        <p><?php p($l->t('Try adjusting your search criteria.')); ?></p>
                        <a class="button secondary" href="<?php p($baseUrl); ?>">
                            <span data-lucide="rotate-ccw" class="lucide-icon" aria-hidden="true"></span>
                            <span><?php p($l->t('Clear Filters')); ?></span>
                        </a>
                    <?php else: ?>
                        <span class="emptycontent__icon" aria-hidden="true"><i data-lucide="clock" class="lucide-icon"></i></span>
                        <h2><?php p($l->t('No employees found')); ?></h2>
                        <p><?php p($l->t('No employees have logged time entries yet.')); ?></p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php
                    $pagination = $_['pagination'] ?? [];
                    $currentPage = max(1, (int)($pagination['page'] ?? 1));
                    $totalPages = max(1, (int)($pagination['totalPages'] ?? 1));
                    $totalEntries = (int)($pagination['totalEntries'] ?? count($_['employeeComparisonStats']));
                    $perPage = (int)($pagination['perPage'] ?? count($_['employeeComparisonStats']));
                    $baseQuery = $_['filters'] ?? [];
                    unset($baseQuery['limit'], $baseQuery['offset']);
                    // Continuous rank across pages (rows are ordered by revenue).
                    $rankBase = ($currentPage - 1) * $perPage;
                ?>
                <div class="pc-list-table-wrap" tabindex="0" role="region" aria-label="<?php p($l->t('Employees')); ?>">
                    <table class="grid employees-table pc-data-table">
                        <caption class="pc-sr-only"><?php p($l->t('Employees ranked by total revenue')); ?></caption>
                        <thead>
                            <tr>
                                <th scope="col" class="col-rank"><?php p($colRank); ?></th>
                                <th scope="col"><?php p($colEmployee); ?></th>
                                <th scope="col" class="col-num"><?php p($colHours); ?></th>
                                <th scope="col" class="col-num"><?php p($colRevenue); ?></th>
                                <th scope="col" class="col-num"><?php p($colRate); ?></th>
                                <th scope="col" class="col-actions"><?php p($colActions); ?></th>
                            </tr>
                        </thead>
                        <tbody id="employees-tbody">
                            <?php foreach ($_['employeeComparisonStats'] as $index => $employee): ?>
                                <?php
                                    $employeeId = (string)($employee['user_id'] ?? '');
                                    $displayName = (string)($employee['user_display_name'] ?? $employeeId);
                                    $showUrl = $urlGenerator->linkToRoute('projectcheck.employee.show', ['userId' => $employeeId]);
                                ?>
                                <tr>
                                    <td class="col-rank" data-label="<?php p($colRank); ?>">
                                        <span class="rank-badge">#<?php p($rankBase + $index + 1); ?></span>
                                    </td>
                                    <th scope="row" class="employee-name-cell" data-label="<?php p($colEmployee); ?>">
                                        <a href="<?php p($showUrl); ?>"><?php p($displayName); ?></a>
                                    </th>
                                    <td class="col-num" data-label="<?php p($colHours); ?>">
                                        <?php p(number_format((float)($employee['total_hours'] ?? 0), 1)); ?>h
                                    </td>
                                    <td class="col-num" data-label="<?php p($colRevenue); ?>">
                                        <?php p($fmt ? $fmt->currency((float)($employee['total_cost'] ?? 0)) : $currencyCode . ' ' . number_format((float)($employee['total_cost'] ?? 0), 2)); ?>
                                    </td>
                                    <td class="col-num" data-label="<?php p($colRate); ?>">
                                        <?php p($fmt ? $fmt->currency((float)($employee['avg_hourly_rate'] ?? 0)) : $currencyCode . ' ' . number_format((float)($employee['avg_hourly_rate'] ?? 0), 2)); ?>/h
                                    </td>
                                    <td class="col-actions" data-label="<?php p($colActions); ?>">
                                        <div class="action-items" role="group" aria-label="<?php p($l->t('Employee actions')); ?>">
                                            <a href="<?php p($showUrl); ?>"
                                                class="action-item action-item--view"
                                                title="<?php p($l->t('View Details')); ?>"
                                                aria-label="<?php p($l->t('View details for employee %s', [$displayName])); ?>">
                                                <span data-lucide="eye" class="lucide-icon" aria-hidden="true"></span>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if ($totalPages > 1): ?>
                    <?php
                        $prevQuery = array_merge($baseQuery, ['page' => max(1, $currentPage - 1)]);
                        $nextQuery = array_merge($baseQuery, ['page' => min($totalPages, $currentPage + 1)]);
                    ?>
                    <div class="pc-list-panel__footer pagination">
                        <div class="pagination-info">
                            <span><?php p($l->t('Page')); ?> <?php p($currentPage); ?> / <?php p($totalPages); ?></span>
                            <span aria-hidden="true">•</span>
                            <span><?php p($l->t('Total')); ?> <?php p($totalEntries); ?></span>
                        </div>
                        <div class="pagination-actions">
                            <?php if ($currentPage > 1): ?>
                                <a class="button secondary" rel="prev" href="<?php p($baseUrl . '?' . http_build_query($prevQuery)); ?>">‹ <?php p($l->t('Previous')); ?></a>
                            <?php else: ?>
                                <span class="button secondary disabled" aria-disabled="true">‹ <?php p($l->t('Previous')); ?></span>
                            <?php endif; ?>
                            <?php if ($currentPage < $totalPages): ?>
                                <a class="button secondary" rel="next" href="<?php p($baseUrl . '?' . http_build_query($nextQuery)); ?>"><?php p($l->t('Next')); ?> ›</a>
                            <?php else: ?>
                                <span class="button secondary disabled" aria-disabled="true"><?php p($l->t('Next')); ?> ›</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

<?php include __DIR__ . '/common/page-end.php'; ?>
