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
Util::addScript('projectcheck', 'common/icons');
Util::addStyle('projectcheck', 'navigation');
Util::addStyle('projectcheck', 'time-entries');
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
        <!-- Breadcrumb + page actions -->
        <div class="employees-pagebar">
            <nav class="breadcrumb" aria-label="<?php p($l->t('Breadcrumb')); ?>">
                <ol>
                    <li><a href="<?php p($urlGenerator->linkToRoute('projectcheck.dashboard.index')); ?>"><?php p($l->t('Dashboard')); ?></a></li>
                    <li aria-current="page"><?php p($l->t('Employees')); ?></li>
                </ol>
            </nav>
            <a href="<?php p($urlGenerator->linkToRoute('projectcheck.dashboard.index')); ?>" class="button secondary">
                <i data-lucide="arrow-left" class="lucide-icon" aria-hidden="true"></i>
                <?php p($l->t('Back to Dashboard')); ?>
            </a>
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
                    <h3><i data-lucide="users" class="lucide-icon" aria-hidden="true"></i> <?php p($isGlobalViewer ? $l->t('Team Overview') : $l->t('Personal Overview')); ?></h3>
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

                <div class="overview-cards">
                    <div class="overview-card">
                        <div class="card-icon">
                            <i data-lucide="users" class="lucide-icon" aria-hidden="true"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-value"><?php p($teamTotalEmployees); ?></div>
                            <div class="card-label"><?php p($l->t('Employees')); ?></div>
                        </div>
                    </div>

                    <div class="overview-card">
                        <div class="card-icon">
                            <i data-lucide="clock" class="lucide-icon" aria-hidden="true"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-value"><?php p(number_format($teamTotalHours, 1)); ?>h</div>
                            <div class="card-label"><?php p($l->t('Total Hours')); ?></div>
                        </div>
                    </div>

                    <div class="overview-card">
                        <div class="card-icon">
                            <i data-lucide="euro" class="lucide-icon" aria-hidden="true"></i>
                        </div>
                        <div class="card-content">
                            <div class="card-value"><?php p($fmt ? $fmt->currency((float)$teamTotalCost) : $currencyCode . ' ' . number_format((float)$teamTotalCost, 2)); ?></div>
                            <div class="card-label"><?php p($l->t('Total Revenue')); ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Employees: one panel holds the search toolbar, the table and pagination -->
        <?php if (empty($_['employeeComparisonStats']) && !$hasSearch): ?>
            <!-- Nothing recorded yet and nothing searched: a single clean empty card (no search box) -->
            <div class="section employees-panel">
                <div class="emptycontent" role="status">
                    <span class="emptycontent__icon"><i data-lucide="clock" class="lucide-icon" aria-hidden="true"></i></span>
                    <h2><?php p($l->t('No employees found')); ?></h2>
                    <p><?php p($l->t('No employees have logged time entries yet.')); ?></p>
                </div>
            </div>
        <?php else: ?>
            <section class="section employees-panel" aria-label="<?php p($l->t('Employees')); ?>">
                <!-- Toolbar header: native GET form, fully functional without JS -->
                <div class="employees-panel__toolbar">
                    <form class="employees-search-form" method="get" action="<?php p($baseUrl); ?>" role="search" aria-label="<?php p($l->t('Search employees...')); ?>">
                        <div class="employees-search">
                            <span class="employees-search__icon" data-lucide="search" aria-hidden="true"></span>
                            <label class="u-visually-hidden" for="employee-search"><?php p($l->t('Search employees...')); ?></label>
                            <input type="search" id="employee-search" name="search" class="employees-search__input" autocomplete="off"
                                placeholder="<?php p($l->t('Search employees...')); ?>"
                                value="<?php p($searchValue); ?>"
                                aria-describedby="employee-search-hint">
                        </div>
                        <p class="u-visually-hidden" id="employee-search-hint"><?php p($l->t('Type a name and press Enter or Search to filter the list.')); ?></p>

                        <div class="employees-search__actions">
                            <button type="submit" class="button primary">
                                <i data-lucide="search" class="lucide-icon" aria-hidden="true"></i>
                                <span><?php p($l->t('Search')); ?></span>
                            </button>
                            <?php if ($hasSearch): ?>
                                <a class="button secondary" href="<?php p($baseUrl); ?>">
                                    <i data-lucide="rotate-ccw" class="lucide-icon" aria-hidden="true"></i>
                                    <span><?php p($l->t('Reset Search')); ?></span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>

                <?php if (empty($_['employeeComparisonStats'])): ?>
                    <!-- Search returned nothing: contextual empty state inside the same panel -->
                    <div class="emptycontent" role="status">
                        <span class="emptycontent__icon"><i data-lucide="search-x" class="lucide-icon" aria-hidden="true"></i></span>
                        <h2><?php p($l->t('No employees match your search')); ?></h2>
                        <p><?php p($l->t('Try adjusting your search criteria.')); ?></p>
                        <a class="button secondary" href="<?php p($baseUrl); ?>">
                            <i data-lucide="rotate-ccw" class="lucide-icon" aria-hidden="true"></i>
                            <span><?php p($l->t('Reset Search')); ?></span>
                        </a>
                    </div>
                <?php else: ?>
                    <?php
                        $pagination = $_['pagination'] ?? [];
                        $currentPage = max(1, (int)($pagination['page'] ?? 1));
                        $totalPages = max(1, (int)($pagination['totalPages'] ?? 1));
                        $totalEntries = (int)($pagination['totalEntries'] ?? count($_['employeeComparisonStats']));
                        $perPage = (int)($pagination['perPage'] ?? count($_['employeeComparisonStats']));
                        $baseQuery = $_['filters'] ?? [];
                        // Continuous rank across pages (rows are ordered by revenue).
                        $rankBase = ($currentPage - 1) * $perPage;
                    ?>
                    <div class="grid-container employees-table-wrap" tabindex="0" role="region" aria-label="<?php p($l->t('Employees')); ?>">
                    <table class="employees-table">
                        <caption class="u-visually-hidden"><?php p($l->t('Employees ranked by total revenue')); ?></caption>
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
                                        <span class="employees-metric">
                                            <i data-lucide="clock" class="lucide-icon" aria-hidden="true"></i>
                                            <span class="employees-metric__value"><?php p(number_format((float)($employee['total_hours'] ?? 0), 1)); ?>h</span>
                                        </span>
                                    </td>
                                    <td class="col-num" data-label="<?php p($colRevenue); ?>">
                                        <span class="employees-metric">
                                            <i data-lucide="euro" class="lucide-icon" aria-hidden="true"></i>
                                            <span class="employees-metric__value"><?php p($fmt ? $fmt->currency((float)($employee['total_cost'] ?? 0)) : $currencyCode . ' ' . number_format((float)($employee['total_cost'] ?? 0), 2)); ?></span>
                                        </span>
                                    </td>
                                    <td class="col-num" data-label="<?php p($colRate); ?>">
                                        <span class="employees-metric">
                                            <i data-lucide="trending-up" class="lucide-icon" aria-hidden="true"></i>
                                            <span class="employees-metric__value"><?php p($fmt ? $fmt->currency((float)($employee['avg_hourly_rate'] ?? 0)) : $currencyCode . ' ' . number_format((float)($employee['avg_hourly_rate'] ?? 0), 2)); ?>/h</span>
                                        </span>
                                    </td>
                                    <td class="col-actions" data-label="<?php p($colActions); ?>">
                                        <a href="<?php p($showUrl); ?>" class="button primary"
                                           aria-label="<?php p($l->t('View details for employee %s', [$displayName])); ?>">
                                            <i data-lucide="eye" class="lucide-icon" aria-hidden="true"></i>
                                            <span><?php p($l->t('View Details')); ?></span>
                                        </a>
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
                        $isFirst = $currentPage <= 1;
                        $isLast = $currentPage >= $totalPages;
                    ?>
                    <nav class="pagination employees-panel__footer" aria-label="<?php p($l->t('Pagination')); ?>">
                        <div class="pagination-info">
                            <span><?php p($l->t('Page')); ?> <?php p($currentPage); ?> / <?php p($totalPages); ?></span>
                            <span aria-hidden="true">•</span>
                            <span><?php p($l->t('Total')); ?> <?php p($totalEntries); ?></span>
                        </div>
                        <div class="pagination-actions">
                            <?php if ($isFirst): ?>
                                <span class="button secondary disabled" aria-disabled="true">‹ <?php p($l->t('Previous')); ?></span>
                            <?php else: ?>
                                <a class="button secondary" rel="prev" href="<?php p($baseUrl . '?' . http_build_query($prevQuery)); ?>">‹ <?php p($l->t('Previous')); ?></a>
                            <?php endif; ?>
                            <?php if ($isLast): ?>
                                <span class="button secondary disabled" aria-disabled="true"><?php p($l->t('Next')); ?> ›</span>
                            <?php else: ?>
                                <a class="button secondary" rel="next" href="<?php p($baseUrl . '?' . http_build_query($nextQuery)); ?>"><?php p($l->t('Next')); ?> ›</a>
                            <?php endif; ?>
                        </div>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
            </section>
        <?php endif; ?>

<?php include __DIR__ . '/common/page-end.php'; ?>
