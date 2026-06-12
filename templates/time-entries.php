<?php

/**
 * Time entries list template for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

use OCP\Util;

Util::addScript('projectcheck', 'time-entries');
Util::addStyle('projectcheck', 'time-entries');
Util::addStyle('projectcheck', 'navigation');
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<?php
$pageId = 'time-entries';
$pageTitle = $l->t('Time Entries');
$pageHelp = $l->t('Track and manage your time entries');
include __DIR__ . '/common/page-start.php';
?>
        <?php
        // Lead text already rendered under the h1 by page-start.php — the bar only carries actions.
        ob_start(); ?>
                    <a href="<?php p($_['createUrl'] ?? '/index.php/apps/projectcheck/time-entries/create'); ?>" class="button primary">
                        <?php p($l->t('Add Time Entry')); ?>
                    </a>
        <?php
        $headerActionsHtml = ob_get_clean();
        include __DIR__ . '/common/page-header-section.php';
        ?>

        <!-- Success/Error Messages -->
        <?php if (isset($_GET['message']) && $_GET['message'] === 'success'): ?>
            <div class="notice notice-success">
                <i class="icon icon-checkmark"></i>
                <span>
                    <?php if (isset($_GET['time_entry_id'])): ?>
                        <?php p($l->t('Time entry was created successfully!')); ?>
                    <?php elseif (isset($_GET['updated'])): ?>
                        <?php p($l->t('Time entry was updated successfully!')); ?>
                    <?php elseif (isset($_GET['deleted'])): ?>
                        <?php p($l->t('Time entry was deleted successfully!')); ?>
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


        <!-- Search and Filter Section -->
        <div class="section">
            <div class="filters-container">
                <!-- Search Input -->
                <div class="search-input-wrapper">
                    <input type="text" id="time-entry-search" class="search-input"
                        placeholder="<?php p($l->t('Search descriptions, projects, or customers...')); ?>"
                        value="<?php p($filters['search'] ?? ''); ?>"
                        aria-label="<?php p($l->t('Search descriptions, projects, or customers')); ?>"
                        autocomplete="off">
                    <span class="search-icon" aria-hidden="true"><i data-lucide="search" class="lucide-icon"></i></span>
                </div>

                <!-- Filter Controls -->
                <div class="filter-controls">
                    <div class="filter-group">
                        <label for="project-filter" class="filter-label"><?php p($l->t('Project')); ?></label>
                        <select id="project-filter" class="filter-select">
                            <option value=""><?php p($l->t('All Projects')); ?></option>
                            <?php if (isset($projects) && is_array($projects)): ?>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?php p($project->getId()); ?>" <?php if (isset($filters['project_id']) && $filters['project_id'] == $project->getId()) echo 'selected'; ?>>
                                        <?php p($project->getName()); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <?php if (!empty($_['canViewAllEntries'])): ?>
                    <div class="filter-group">
                        <label for="user-filter" class="filter-label"><?php p($l->t('User')); ?></label>
                        <select id="user-filter" class="filter-select">
                            <option value=""><?php p($l->t('All Users')); ?></option>
                            <?php if (isset($users) && is_array($users)): ?>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php p($user['user_id']); ?>" <?php if (isset($filters['user_id']) && $filters['user_id'] == $user['user_id']) echo 'selected'; ?>>
                                        <?php p($user['displayname']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="filter-group">
                        <label for="time-entry-project-type-filter" class="filter-label"><?php p($l->t('Project Type')); ?></label>
                        <select id="time-entry-project-type-filter" class="filter-select">
                            <option value=""><?php p($l->t('All Types')); ?></option>
                            <option value="client" <?php if (isset($filters['project_type']) && $filters['project_type'] == 'client') echo 'selected'; ?>><?php p($l->t('Client Project')); ?></option>
                            <option value="admin" <?php if (isset($filters['project_type']) && $filters['project_type'] == 'admin') echo 'selected'; ?>><?php p($l->t('Administrative')); ?></option>
                            <option value="sales" <?php if (isset($filters['project_type']) && $filters['project_type'] == 'sales') echo 'selected'; ?>><?php p($l->t('Sales & Marketing')); ?></option>
                            <option value="customer" <?php if (isset($filters['project_type']) && $filters['project_type'] == 'customer') echo 'selected'; ?>><?php p($l->t('Customer Support')); ?></option>
                            <option value="product" <?php if (isset($filters['project_type']) && $filters['project_type'] == 'product') echo 'selected'; ?>><?php p($l->t('Product Development')); ?></option>
                            <option value="meeting" <?php if (isset($filters['project_type']) && $filters['project_type'] == 'meeting') echo 'selected'; ?>><?php p($l->t('Meetings & Overhead')); ?></option>
                            <option value="internal" <?php if (isset($filters['project_type']) && $filters['project_type'] == 'internal') echo 'selected'; ?>><?php p($l->t('Internal Project')); ?></option>
                            <option value="research" <?php if (isset($filters['project_type']) && $filters['project_type'] == 'research') echo 'selected'; ?>><?php p($l->t('Research & Development')); ?></option>
                            <option value="training" <?php if (isset($filters['project_type']) && $filters['project_type'] == 'training') echo 'selected'; ?>><?php p($l->t('Training & Education')); ?></option>
                            <option value="other" <?php if (isset($filters['project_type']) && $filters['project_type'] == 'other') echo 'selected'; ?>><?php p($l->t('Other')); ?></option>
                        </select>
                    </div>

                    <?php
                    $htmlLang = isset($_['htmlLang']) && is_string($_['htmlLang']) ? $_['htmlLang'] : 'en';
                    $filterDateFrom = '';
                    if (!empty($filters['date_from'])) {
                        $dateObj = \DateTime::createFromFormat('Y-m-d', (string)$filters['date_from']);
                        $filterDateFrom = $dateObj ? $dateObj->format('Y-m-d') : '';
                    }
                    $filterDateTo = '';
                    if (!empty($filters['date_to'])) {
                        $dateObj = \DateTime::createFromFormat('Y-m-d', (string)$filters['date_to']);
                        $filterDateTo = $dateObj ? $dateObj->format('Y-m-d') : '';
                    }
                    ?>
                    <div class="filter-group">
                        <label for="date-from-filter" class="filter-label"><?php p($l->t('From')); ?></label>
                        <input type="date" id="date-from-filter" name="date_from" class="filter-date form-input"
                            lang="<?php p($htmlLang); ?>"
                            value="<?php p($filterDateFrom); ?>"
                            autocomplete="off">
                    </div>

                    <div class="filter-group">
                        <label for="date-to-filter" class="filter-label"><?php p($l->t('To')); ?></label>
                        <input type="date" id="date-to-filter" name="date_to" class="filter-date form-input"
                            lang="<?php p($htmlLang); ?>"
                            value="<?php p($filterDateTo); ?>"
                            autocomplete="off">
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="filter-actions">
                    <button id="apply-filters" class="btn btn-primary">
                        <span class="btn-icon" aria-hidden="true">🔍</span>
                        <?php p($l->t('Apply Filters')); ?>
                    </button>
                    <button id="clear-filters" class="btn btn-secondary">
                        <span class="btn-icon" aria-hidden="true">🔄</span>
                        <?php p($l->t('Reset Filters')); ?>
                    </button>
                    <button id="export-csv" class="btn btn-primary">
                        <span class="btn-icon" aria-hidden="true">📊</span>
                        <?php p($l->t('Export')); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Time Entries Table -->
        <div class="section">
            <?php if (empty($timeEntries)): ?>
                <div class="time-entries-empty">
                    <div class="time-entries-empty__icon" aria-hidden="true">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-clock-icon lucide-clock"><circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/></svg>
                    </div>
                    <h2><?php p($l->t('No time entries found')); ?></h2>
                    <p><?php p($l->t('Add your first time entry to get started!')); ?></p>
                    <div class="time-entries-empty__actions">
                        <a href="<?php p($_['createUrl'] ?? '/index.php/apps/projectcheck/time-entries/create'); ?>" class="button primary">
                            <?php p($l->t('Add Time Entry')); ?>
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php
                $fmt = $_['fmt'] ?? null;
                $selectionSummary = is_array($_['selectionSummary'] ?? null) ? $_['selectionSummary'] : [];
                $selectionHoursTotal = (float)($selectionSummary['hoursTotal'] ?? 0);
                $selectionEntryCount = (int)($selectionSummary['entryCount'] ?? count($timeEntries));
                $pageHoursTotal = (float)($selectionSummary['pageHoursTotal'] ?? $selectionHoursTotal);
                $pageEntryCount = (int)($selectionSummary['pageEntryCount'] ?? count($timeEntries));
                $summaryPage = max(1, (int)($selectionSummary['page'] ?? ($pagination['page'] ?? 1)));
                $summaryTotalPages = max(1, (int)($selectionSummary['totalPages'] ?? ($pagination['totalPages'] ?? 1)));
                $showPageHoursSubtotal = $summaryTotalPages > 1;
                $colHours = $l->t('Hours');
                ?>
                <div id="time-entries-summary-live" class="pc-sr-only" aria-live="polite" aria-atomic="true"></div>
                <div class="grid-container">
                    <table class="grid time-entries-table" id="time-entries-table"
                        data-selection-hours="<?php p(number_format($selectionHoursTotal, 4, '.', '')); ?>"
                        data-selection-count="<?php p((string)$selectionEntryCount); ?>"
                        data-page-hours="<?php p(number_format($pageHoursTotal, 4, '.', '')); ?>"
                        data-page-count="<?php p((string)$pageEntryCount); ?>"
                        data-show-page-subtotal="<?php p($showPageHoursSubtotal ? '1' : '0'); ?>">
                        <colgroup>
                            <col class="col-date">
                            <col class="col-project">
                            <col class="col-type">
                            <col class="col-customer">
                            <col class="col-user">
                            <col class="col-hours">
                            <col class="col-description">
                            <col class="col-actions">
                        </colgroup>
                        <thead>
                            <tr>
                                <th><?php p($l->t('Date')); ?></th>
                                <th><?php p($l->t('Project')); ?></th>
                                <th><?php p($l->t('Type')); ?></th>
                                <th><?php p($l->t('Customer')); ?></th>
                                <th><?php p($l->t('User')); ?></th>
                                <th><?php p($l->t('Hours')); ?></th>
                                <th><?php p($l->t('Description')); ?></th>
                                <th><?php p($l->t('Actions')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // null = viewer may open every project; otherwise a lookup set of
                            // accessible ids (own historical entries can sit on projects the
                            // user has left — those render as text, not as a dead link).
                            $accessibleProjectIdSet = null;
                            if (isset($_['accessibleProjectIds']) && is_array($_['accessibleProjectIds'])) {
                                $accessibleProjectIdSet = [];
                                foreach ($_['accessibleProjectIds'] as $accessibleId) {
                                    $accessibleProjectIdSet[(int) $accessibleId] = true;
                                }
                            }
                            ?>
                            <!-- No Results Message (Hidden by default) -->
                            <tr id="no-results-row" style="display: none;">
                                <td colspan="8">
                                    <div class="no-results-message">
                                        <div class="no-results-icon" aria-hidden="true">🔍</div>
                                        <h3><?php p($l->t('No time entries match your filters')); ?></h3>
                                        <p><?php p($l->t('Try adjusting your search criteria or clear the filters to see all entries.')); ?></p>
                                        <button id="clear-filters-inline" class="btn btn-secondary">
                                            <span class="btn-icon" aria-hidden="true">🔄</span>
                                            <?php p($l->t('Reset Filters')); ?>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php foreach ($timeEntries as $entry): ?>
                                <?php
                                $timeEntry = $entry['timeEntry'];
                                if (!$timeEntry || !is_object($timeEntry)) {
                                    continue;
                                }
                                ?>
                                <?php $entryHours = (float)($timeEntry->getHours() ?? 0); ?>
                                <tr data-entry-id="<?php p($timeEntry->getId()); ?>"
                                    data-project-id="<?php p($timeEntry->getProjectId()); ?>"
                                    data-user-id="<?php p($timeEntry->getUserId()); ?>"
                                    data-project-type="<?php p($entry['project_type'] ?? 'client'); ?>"
                                    data-date-iso="<?php p($timeEntry->getDate() ? $timeEntry->getDate()->format('Y-m-d') : ''); ?>"
                                    data-entry-hours="<?php p(number_format($entryHours, 4, '.', '')); ?>">
                                    <td><?php p($timeEntry->getDate() ? $timeEntry->getDate()->format('d.m.Y') : ''); ?></td>
                                    <td>
                                        <?php
                                        $rowProjectLinkable = $accessibleProjectIdSet === null
                                            || isset($accessibleProjectIdSet[(int) $timeEntry->getProjectId()]);
                                        ?>
                                        <?php if ($rowProjectLinkable): ?>
                                            <a href="<?php p(str_replace('PROJECT_ID', $timeEntry->getProjectId(), $_['projectShowUrl'] ?? '/index.php/apps/projectcheck/projects/')); ?>">
                                                <?php p($entry['projectName'] ?? $l->t('Unknown Project')); ?>
                                            </a>
                                        <?php else: ?>
                                            <span><?php p($entry['projectName'] ?? $l->t('Unknown Project')); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php
                                        $projectType = $entry['project_type'] ?? 'client';
                                        $displayName = $entry['project_type_display_name'] ?? 'Client Project';

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

                                        $icon = $iconMapping[$projectType] ?? '📋';
                                        ?>
                                        <span class="project-type-icon"
                                            data-project-type="<?php p($projectType); ?>"
                                            title="<?php p($displayName); ?>">
                                            <?php p($icon); ?>
                                        </span>
                                    </td>
                                    <td><?php p($entry['customerName'] ?? ''); ?></td>
                                    <td><?php p($entry['userDisplayName'] ?? $timeEntry->getUserId() ?? ''); ?></td>
                                    <td class="col-hours" data-label="<?php p($colHours); ?>">
                                        <span class="time-entries-hours-value"><?php p($fmt ? $fmt->hours($entryHours) : number_format($entryHours, 2) . 'h'); ?></span>
                                    </td>
                                    <td class="description-cell"><span class="description-cell__text"><?php p($timeEntry->getDescription() ?? ''); ?></span></td>
                                    <td>
                                        <div class="action-items">
                                            <a href="<?php p(str_replace('ENTRY_ID', $timeEntry->getId(), $_['showUrl'] ?? '/index.php/apps/projectcheck/time-entries/')); ?>"
                                                class="action-item" title="<?php p($l->t('View Details')); ?>"
                                                aria-label="<?php p($l->t('View time entry details')); ?>">
                                                <span class="icon icon-details" aria-hidden="true"></span>
                                            </a>
                                            <?php if ($timeEntry->getUserId() === $_['userId']): ?>
                                                <a href="<?php p(str_replace('ENTRY_ID', $timeEntry->getId(), $_['editUrl'] ?? '/index.php/apps/projectcheck/time-entries/edit/')); ?>"
                                                    class="action-item" title="<?php p($l->t('Edit Time Entry')); ?>" aria-label="<?php p($l->t('Edit time entry')); ?>">
                                                    <span class="icon icon-rename"></span>
                                                </a>
                                                <button type="button" class="action-item delete-entry-btn"
                                                    data-entry-id="<?php p($timeEntry->getId()); ?>"
                                                    data-entry-description="<?php p($timeEntry->getDescription() ?? ''); ?>"
                                                    title="<?php p($l->t('Delete Time Entry')); ?>"
                                                    aria-label="<?php p($l->t('Delete time entry')); ?>">
                                                    <span class="icon icon-delete"></span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="time-entries-summary">
                                <th scope="row" colspan="5" class="time-entries-summary__lead" id="time-entries-summary-label">
                                    <span class="time-entries-summary__title"><?php p($l->t('Total hours (matching filters)')); ?></span>
                                    <span class="time-entries-summary__meta" id="time-entries-selection-meta">
                                        <?php p($l->n('%n matching entry', '%n matching entries', $selectionEntryCount)); ?>
                                        <?php if ($showPageHoursSubtotal): ?>
                                            <span class="time-entries-summary__meta-sep" aria-hidden="true"> · </span>
                                            <span class="time-entries-summary__meta-page">
                                                <?php p($l->t('Page %1$s of %2$s', [(string)$summaryPage, (string)$summaryTotalPages])); ?>
                                            </span>
                                        <?php endif; ?>
                                    </span>
                                </th>
                                <td colspan="3" class="time-entries-summary__figures" headers="time-entries-summary-label">
                                    <?php if ($showPageHoursSubtotal): ?>
                                        <div class="time-entries-summary__stats time-entries-summary__stats--split" id="time-entries-page-hours-wrap">
                                            <span class="time-entries-summary__stat-label"><?php p($l->t('All matching')); ?></span>
                                            <span class="time-entries-summary__stat-label"><?php p($l->t('This page')); ?></span>
                                            <span class="time-entries-summary__stat-value" id="time-entries-selection-hours">
                                                <?php p($fmt ? $fmt->hours($selectionHoursTotal) : number_format($selectionHoursTotal, 2) . 'h'); ?>
                                            </span>
                                            <span class="time-entries-summary__stat-value time-entries-summary__stat-value--muted" id="time-entries-page-hours">
                                                <?php p($fmt ? $fmt->hours($pageHoursTotal) : number_format($pageHoursTotal, 2) . 'h'); ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <div class="time-entries-summary__stats">
                                            <span class="time-entries-summary__stat-label pc-sr-only"><?php p($l->t('Total hours (matching filters)')); ?></span>
                                            <span class="time-entries-summary__stat-value" id="time-entries-selection-hours">
                                                <?php p($fmt ? $fmt->hours($selectionHoursTotal) : number_format($selectionHoursTotal, 2) . 'h'); ?>
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <?php
                    $pagination = $_['pagination'] ?? ['page' => 1, 'totalPages' => 1, 'totalEntries' => count($timeEntries), 'perPage' => count($timeEntries)];
                    $currentPage = max(1, (int)($pagination['page'] ?? 1));
                    $totalPages = max(1, (int)($pagination['totalPages'] ?? 1));
                    $totalEntries = (int)($pagination['totalEntries'] ?? 0);
                    $perPage = (int)($pagination['perPage'] ?? 0);

                    // Build helper for pagination links with current filters
                    $baseUrl = $_['indexUrl'] ?? '/index.php/apps/projectcheck/time-entries';
                    $baseQuery = $filters ?? [];
                    unset($baseQuery['limit'], $baseQuery['offset']);
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
                            <?php if ($currentPage > 1): ?>
                            <a class="btn btn-secondary" href="<?php p($baseUrl . '?' . http_build_query($prevQuery)); ?>">
                                ‹ <?php p($l->t('Previous')); ?>
                            </a>
                            <?php else: ?>
                            <span class="btn btn-secondary disabled" aria-disabled="true"><?php p($l->t('Previous')); ?></span>
                            <?php endif; ?>
                            <?php if ($currentPage < $totalPages): ?>
                            <a class="btn btn-secondary" href="<?php p($baseUrl . '?' . http_build_query($nextQuery)); ?>">
                                <?php p($l->t('Next')); ?> ›
                            </a>
                            <?php else: ?>
                            <span class="btn btn-secondary disabled" aria-disabled="true"><?php p($l->t('Next')); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
<?php include __DIR__ . '/common/page-end.php'; ?>

