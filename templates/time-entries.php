<?php

/**
 * Time entries list template for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

use OCP\Util;

Util::addScript('projectcheck', 'common/export-menu');
Util::addScript('projectcheck', 'time-entries');
Util::addStyle('projectcheck', 'time-entries');
Util::addStyle('projectcheck', 'navigation');
Util::addStyle('projectcheck', 'common/list-table');
Util::addStyle('projectcheck', 'common/list-layout');
Util::addStyle('projectcheck', 'common/filters');
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<?php
$pageId = 'time-entries';
$pageTitle = $l->t('Time Entries');
$pageHelp = $l->t('Track and manage your time entries');
ob_start(); ?>
                    <a href="<?php p($_['createUrl'] ?? '/index.php/apps/projectcheck/time-entries/create'); ?>" class="button primary">
                        <span data-lucide="plus" class="lucide-icon" aria-hidden="true"></span>
                        <?php p($l->t('Add Time Entry')); ?>
                    </a>
<?php
$pageHeaderActionsHtml = ob_get_clean();
$pageHeaderActionsLabel = $l->t('Page actions');
include __DIR__ . '/common/page-start.php';
?>

        <!-- Success/Error Messages -->
        <?php if (isset($_GET['message']) && $_GET['message'] === 'success'): ?>
            <div class="notice notice-success">
                <span data-lucide="circle-check" class="lucide-icon" aria-hidden="true"></span>
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
                <span data-lucide="alert-circle" class="lucide-icon" aria-hidden="true"></span>
                <span><?php p($l->t('Error: %s', [$_GET['error_text']])); ?></span>
            </div>
        <?php endif; ?>


        <?php
        $colDate = $l->t('Date');
        $colProject = $l->t('Project');
        $colType = $l->t('Type');
        $colCustomer = $l->t('Customer');
        $colUser = $l->t('User');
        $colHours = $l->t('Hours');
        $colDescription = $l->t('Description');
        $colSettlement = $l->t('Settlement');
        $colActions = $l->t('Actions');

        // Settlement scope: null = user can settle every project; otherwise a
        // lookup set of settleable project ids (empty set = no settle rights).
        $canSettleAnything = !empty($_['canSettleAnything']);
        $settleableProjectIdSet = null;
        if (isset($_['settleableProjectIds']) && is_array($_['settleableProjectIds'])) {
            $settleableProjectIdSet = [];
            foreach ($_['settleableProjectIds'] as $settleableId) {
                $settleableProjectIdSet[(int) $settleableId] = true;
            }
        }
        $billingBuckets = is_array($_['billingBuckets'] ?? null) ? $_['billingBuckets'] : [];
        $billingStatusFilter = (string)($filters['billing_status'] ?? '');
        ?>

        <!-- Filters + table: one panel (no nested boxes) -->
        <div class="section time-entries-panel pc-list-panel pc-section" aria-labelledby="pc-time-entries-list-heading">
            <div class="section-header">
                <h3 id="pc-time-entries-list-heading"><i data-lucide="clock" class="lucide-icon primary" aria-hidden="true"></i> <?php p($l->t('Time Entries')); ?></h3>
                <p><?php p($l->t('Search and filter')); ?></p>
            </div>
            <div class="time-entries-panel__toolbar pc-list-panel__toolbar">
            <div class="filters-container pc-filters" role="search" aria-label="<?php p($l->t('Search and filter time entries')); ?>">
                <div class="pc-filters__grid">
                    <div class="pc-filters__field pc-filters__field--search filter-group">
                        <label for="time-entry-search" class="pc-filters__label filter-label"><?php p($l->t('Search')); ?></label>
                        <div class="pc-filters__search search-input-wrapper">
                            <span class="pc-list-search-icon" aria-hidden="true"><i data-lucide="search" class="lucide-icon"></i></span>
                            <input type="search" id="time-entry-search" class="search-input"
                                placeholder="<?php p($l->t('Search descriptions, projects, or customers...')); ?>"
                                value="<?php p($filters['search'] ?? ''); ?>"
                                autocomplete="off">
                        </div>
                    </div>

                    <div class="pc-filters__field filter-group">
                        <label for="project-filter" class="pc-filters__label filter-label"><?php p($l->t('Project')); ?></label>
                        <select id="project-filter" class="filter-select">
                            <option value=""><?php p($l->t('All Projects')); ?></option>
                            <?php if (isset($projects) && is_array($projects)): ?>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?php p($project->getId()); ?>"<?php if (isset($filters['project_id']) && (string)$filters['project_id'] === (string)$project->getId()) echo ' selected'; ?>><?php p($project->getName()); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <?php if (!empty($_['canViewAllEntries'])): ?>
                    <div class="pc-filters__field filter-group">
                        <label for="user-filter" class="pc-filters__label filter-label"><?php p($l->t('User')); ?></label>
                        <select id="user-filter" class="filter-select">
                            <option value=""><?php p($l->t('All Users')); ?></option>
                            <?php if (isset($users) && is_array($users)): ?>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php p($user['user_id']); ?>"<?php if (isset($filters['user_id']) && (string)$filters['user_id'] === (string)$user['user_id']) echo ' selected'; ?>><?php p($user['displayname']); ?></option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="pc-filters__field filter-group">
                        <label for="time-entry-project-type-filter" class="pc-filters__label filter-label"><?php p($l->t('Project Type')); ?></label>
                        <select id="time-entry-project-type-filter" class="filter-select">
                            <option value=""><?php p($l->t('All Types')); ?></option>
                            <option value="client"<?php if (isset($filters['project_type']) && $filters['project_type'] == 'client') echo ' selected'; ?>><?php p($l->t('Client Project')); ?></option>
                            <option value="admin"<?php if (isset($filters['project_type']) && $filters['project_type'] == 'admin') echo ' selected'; ?>><?php p($l->t('Administrative')); ?></option>
                            <option value="sales"<?php if (isset($filters['project_type']) && $filters['project_type'] == 'sales') echo ' selected'; ?>><?php p($l->t('Sales & Marketing')); ?></option>
                            <option value="customer"<?php if (isset($filters['project_type']) && $filters['project_type'] == 'customer') echo ' selected'; ?>><?php p($l->t('Customer Support')); ?></option>
                            <option value="product"<?php if (isset($filters['project_type']) && $filters['project_type'] == 'product') echo ' selected'; ?>><?php p($l->t('Product Development')); ?></option>
                            <option value="meeting"<?php if (isset($filters['project_type']) && $filters['project_type'] == 'meeting') echo ' selected'; ?>><?php p($l->t('Meetings & Overhead')); ?></option>
                            <option value="internal"<?php if (isset($filters['project_type']) && $filters['project_type'] == 'internal') echo ' selected'; ?>><?php p($l->t('Internal Project')); ?></option>
                            <option value="research"<?php if (isset($filters['project_type']) && $filters['project_type'] == 'research') echo ' selected'; ?>><?php p($l->t('Research & Development')); ?></option>
                            <option value="training"<?php if (isset($filters['project_type']) && $filters['project_type'] == 'training') echo ' selected'; ?>><?php p($l->t('Training & Education')); ?></option>
                            <option value="other"<?php if (isset($filters['project_type']) && $filters['project_type'] == 'other') echo ' selected'; ?>><?php p($l->t('Other')); ?></option>
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
                    <div class="pc-filters__field filter-group">
                        <label for="billing-status-filter" class="pc-filters__label filter-label"><?php p($l->t('Settlement')); ?></label>
                        <select id="billing-status-filter" class="filter-select">
                            <option value=""><?php p($l->t('All statuses')); ?></option>
                            <option value="outstanding"<?php if ($billingStatusFilter === 'outstanding') echo ' selected'; ?>><?php p($l->t('Outstanding (open + invoiced)')); ?></option>
                            <option value="open"<?php if ($billingStatusFilter === 'open') echo ' selected'; ?>><?php p($l->t('Open')); ?></option>
                            <option value="invoiced"<?php if ($billingStatusFilter === 'invoiced') echo ' selected'; ?>><?php p($l->t('Invoiced')); ?></option>
                            <option value="paid"<?php if ($billingStatusFilter === 'paid') echo ' selected'; ?>><?php p($l->t('Paid')); ?></option>
                            <option value="excluded"<?php if ($billingStatusFilter === 'excluded') echo ' selected'; ?>><?php p($l->t('Not billable')); ?></option>
                        </select>
                    </div>

                    <div class="pc-filters__field filter-group">
                        <label for="date-from-filter" class="pc-filters__label filter-label"><?php p($l->t('From')); ?></label>
                        <input type="date" id="date-from-filter" name="date_from" class="filter-date form-input"
                            lang="<?php p($htmlLang); ?>"
                            value="<?php p($filterDateFrom); ?>"
                            autocomplete="off">
                    </div>

                    <div class="pc-filters__field filter-group">
                        <label for="date-to-filter" class="pc-filters__label filter-label"><?php p($l->t('To')); ?></label>
                        <input type="date" id="date-to-filter" name="date_to" class="filter-date form-input"
                            lang="<?php p($htmlLang); ?>"
                            value="<?php p($filterDateTo); ?>"
                            autocomplete="off">
                    </div>
                </div>

                <div class="pc-filters__actions filter-actions">
                    <button type="button" id="apply-filters" class="button primary">
                        <span data-lucide="search" class="lucide-icon" aria-hidden="true"></span>
                        <?php p($l->t('Apply Filters')); ?>
                    </button>
                    <button type="button" id="clear-filters" class="button secondary">
                        <span data-lucide="rotate-ccw" class="lucide-icon" aria-hidden="true"></span>
                        <?php p($l->t('Reset Filters')); ?>
                    </button>
                    <?php
                    $exportUrl = (string)($_['exportUrl'] ?? '');
                    if ($exportUrl === '' && isset($_['urlGenerator']) && is_object($_['urlGenerator'])) {
                    	$exportUrl = (string)$_['urlGenerator']->linkToRoute('projectcheck.timeentry.export');
                    }
                    $exportEntityLabel = 'time_entries';
                    $exportFilterKeys = 'search,project_id,user_id,project_type,date_from,date_to,billing_status';
                    $exportSuccessMsg = 'Exported {count} entries';
                    $exportIncludeSort = false;
                    $exportMenuId = 'pc-export-menu-time-entries';
                    include __DIR__ . '/parts/export-menu.php';
                    ?>
                </div>
            </div>
            </div>

            <?php
            // Settlement summary strip (spec §12.2): one line per bucket over the
            // currently visible result set (own + managed scope, other filters
            // applied). Text + icon chips, no colour-only signals.
            $stripBuckets = [
                'open' => ['label' => $l->t('Open'), 'icon' => 'clock'],
                'invoiced' => ['label' => $l->t('Invoiced'), 'icon' => 'file-text'],
                'paid' => ['label' => $l->t('Paid'), 'icon' => 'circle-check'],
                'excluded' => ['label' => $l->t('Not billable'), 'icon' => 'circle-slash'],
            ];
            $stripOutstandingHours = (float)($billingBuckets['open']['hours'] ?? 0) + (float)($billingBuckets['invoiced']['hours'] ?? 0);
            $stripOutstandingAmount = (float)($billingBuckets['open']['amount'] ?? 0) + (float)($billingBuckets['invoiced']['amount'] ?? 0);
            $stripFmt = $_['fmt'] ?? null;
            ?>
            <?php if (!empty($billingBuckets)): ?>
            <div class="pc-settle-strip" role="region" aria-live="polite" aria-atomic="true" aria-label="<?php p($l->t('Settlement overview for the filtered entries')); ?>">
                <ul class="pc-settle-strip__list">
                    <?php foreach ($stripBuckets as $bucketKey => $bucketMeta): ?>
                        <?php
                        $bucket = $billingBuckets[$bucketKey] ?? ['hours' => 0.0, 'amount' => 0.0, 'count' => 0];
                        $bucketHours = (float)($bucket['hours'] ?? 0);
                        $bucketAmount = (float)($bucket['amount'] ?? 0);
                        ?>
                        <li class="pc-settle-strip__item pc-settle-strip__item--<?php p($bucketKey); ?>">
                            <span class="pc-settle-strip__chip">
                                <span data-lucide="<?php p($bucketMeta['icon']); ?>" class="lucide-icon" aria-hidden="true"></span>
                                <span class="pc-settle-strip__label"><?php p($bucketMeta['label']); ?></span>
                            </span>
                            <span class="pc-settle-strip__hours"><?php p($stripFmt ? $stripFmt->hours($bucketHours) : number_format($bucketHours, 2) . ' h'); ?></span>
                            <span class="pc-settle-strip__amount"><?php p($stripFmt ? $stripFmt->currency($bucketAmount) : number_format($bucketAmount, 2)); ?></span>
                        </li>
                    <?php endforeach; ?>
                    <li class="pc-settle-strip__item pc-settle-strip__item--outstanding">
                        <span class="pc-settle-strip__chip">
                            <span data-lucide="wallet" class="lucide-icon" aria-hidden="true"></span>
                            <span class="pc-settle-strip__label"><?php p($l->t('Not yet paid')); ?></span>
                        </span>
                        <span class="pc-settle-strip__hours"><?php p($stripFmt ? $stripFmt->hours($stripOutstandingHours) : number_format($stripOutstandingHours, 2) . ' h'); ?></span>
                        <span class="pc-settle-strip__amount"><?php p($stripFmt ? $stripFmt->currency($stripOutstandingAmount) : number_format($stripOutstandingAmount, 2)); ?></span>
                    </li>
                </ul>
            </div>
            <?php endif; ?>

            <?php if ($canSettleAnything && !empty($timeEntries)): ?>
            <!-- Settlement bulk bar (spec §12.2): selection mode always; filter-mode
                 "settle all matching" when Settlement is an exact status (not outstanding). -->
            <?php
            $filterModeSource = in_array($billingStatusFilter, ['open', 'invoiced', 'paid', 'excluded'], true)
                ? $billingStatusFilter
                : '';
            $filterModeTargets = match ($filterModeSource) {
                'open' => ['invoiced', 'excluded'],
                'invoiced' => ['paid', 'open'],
                'paid' => ['invoiced'],
                'excluded' => ['open'],
                default => [],
            };
            $filterModeTargetLabels = [
                'open' => $l->t('Reopen all matching'),
                'invoiced' => $l->t('Invoice all matching'),
                'paid' => $l->t('Mark all matching paid'),
                'excluded' => $l->t('Mark all matching not billable'),
            ];
            ?>
            <div class="pc-billing-bar" id="pc-billing-bar" role="region" aria-label="<?php p($l->t('Settlement actions for selected entries')); ?>"
                data-filter-billing-status="<?php p($filterModeSource); ?>"
                data-billing-preview-url="<?php p((string)($_['billingPreviewUrl'] ?? '')); ?>"
                data-billing-bulk-url="<?php p((string)($_['billingBulkUrl'] ?? '')); ?>">
                <span class="pc-billing-bar__count" id="pc-billing-bar-count" aria-live="polite">
                    <?php p($l->t('No entries selected')); ?>
                </span>
                <div class="pc-billing-bar__actions" role="group" aria-label="<?php p($l->t('Change settlement status')); ?>">
                    <button type="button" class="button secondary pc-billing-action" data-billing-target="invoiced" disabled>
                        <span data-lucide="file-text" class="lucide-icon" aria-hidden="true"></span>
                        <?php p($l->t('Mark invoiced')); ?>
                    </button>
                    <button type="button" class="button secondary pc-billing-action" data-billing-target="paid" disabled>
                        <span data-lucide="circle-check" class="lucide-icon" aria-hidden="true"></span>
                        <?php p($l->t('Mark paid')); ?>
                    </button>
                    <button type="button" class="button secondary pc-billing-action" data-billing-target="open" disabled>
                        <span data-lucide="rotate-ccw" class="lucide-icon" aria-hidden="true"></span>
                        <?php p($l->t('Reopen')); ?>
                    </button>
                    <button type="button" class="button secondary pc-billing-action" data-billing-target="excluded" disabled>
                        <span data-lucide="circle-slash" class="lucide-icon" aria-hidden="true"></span>
                        <?php p($l->t('Mark not billable')); ?>
                    </button>
                </div>
                <?php if ($filterModeSource !== '' && $filterModeTargets !== []): ?>
                <p class="pc-billing-bar__hint"><?php p($l->t('Select entries below for this page, or settle every matching entry across all pages.')); ?></p>
                <div class="pc-billing-bar__filter-mode" role="group" aria-label="<?php p($l->t('Settle every entry matching the current filters')); ?>">
                    <p class="pc-billing-bar__filter-mode-label">
                        <?php p($l->t('Or settle every matching entry (all pages, up to 500):')); ?>
                    </p>
                    <div class="pc-billing-bar__actions">
                        <?php foreach ($filterModeTargets as $filterTarget): ?>
                            <button type="button" class="button primary pc-billing-filter-action"
                                data-billing-target="<?php p($filterTarget); ?>"
                                data-billing-source="<?php p($filterModeSource); ?>">
                                <span data-lucide="<?php p($filterTarget === 'paid' ? 'circle-check' : ($filterTarget === 'invoiced' ? 'file-text' : ($filterTarget === 'excluded' ? 'circle-slash' : 'rotate-ccw'))); ?>" class="lucide-icon" aria-hidden="true"></span>
                                <?php p($filterModeTargetLabels[$filterTarget] ?? $filterTarget); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php else: ?>
                <p class="pc-billing-bar__hint"><?php p($l->t('Select entries below, then choose what happens to them. Payments always go through "Invoiced" first. To settle every matching entry at once, filter Settlement to Open, Invoiced, Paid, or Not billable.')); ?></p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

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
                ?>
                <div id="time-entries-summary-live" class="pc-sr-only" aria-live="polite" aria-atomic="true"></div>
                <div class="pc-list-table-wrap time-entries-table-wrap"
                    tabindex="0"
                    role="region"
                    aria-label="<?php p($l->t('Time entries')); ?>">
                    <table class="grid time-entries-table pc-data-table" id="time-entries-table"
                        data-selection-hours="<?php p(number_format($selectionHoursTotal, 4, '.', '')); ?>"
                        data-selection-count="<?php p((string)$selectionEntryCount); ?>"
                        data-page-hours="<?php p(number_format($pageHoursTotal, 4, '.', '')); ?>"
                        data-page-count="<?php p((string)$pageEntryCount); ?>"
                        data-show-page-subtotal="<?php p($showPageHoursSubtotal ? '1' : '0'); ?>"
                        data-billing-bulk-url="<?php p((string)($_['billingBulkUrl'] ?? '')); ?>"
                        data-billing-preview-url="<?php p((string)($_['billingPreviewUrl'] ?? '')); ?>"
                        data-billing-entry-url="<?php p((string)($_['billingEntryUrl'] ?? '')); ?>">
                        <colgroup>
                            <?php if ($canSettleAnything): ?><col class="col-select"><?php endif; ?>
                            <col class="col-date">
                            <col class="col-project">
                            <col class="col-type">
                            <col class="col-customer">
                            <col class="col-user">
                            <col class="col-hours">
                            <col class="col-settlement">
                            <col class="col-description">
                            <col class="col-actions">
                        </colgroup>
                        <thead>
                            <tr>
                                <?php if ($canSettleAnything): ?>
                                <th scope="col" class="col-select">
                                    <label class="pc-billing-select-hit" for="pc-billing-select-all">
                                        <input type="checkbox" id="pc-billing-select-all"
                                            aria-label="<?php p($l->t('Select all settleable entries on this page')); ?>">
                                    </label>
                                </th>
                                <?php endif; ?>
                                <th scope="col"><?php p($colDate); ?></th>
                                <th scope="col"><?php p($colProject); ?></th>
                                <th scope="col" class="col-type"><?php p($colType); ?></th>
                                <th scope="col"><?php p($colCustomer); ?></th>
                                <th scope="col"><?php p($colUser); ?></th>
                                <th scope="col" class="col-hours"><?php p($colHours); ?></th>
                                <th scope="col" class="col-settlement"><?php p($colSettlement); ?></th>
                                <th scope="col"><?php p($colDescription); ?></th>
                                <th scope="col" class="col-actions"><?php p($colActions); ?></th>
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
                            <?php foreach ($timeEntries as $entry): ?>
                                <?php
                                $timeEntry = $entry['timeEntry'];
                                if (!$timeEntry || !is_object($timeEntry)) {
                                    continue;
                                }
                                ?>
                                <?php
                                $entryHours = (float)($timeEntry->getHours() ?? 0);
                                $entryBillingStatus = method_exists($timeEntry, 'getBillingStatus') ? $timeEntry->getBillingStatus() : 'open';
                                $rowCanSettle = $canSettleAnything && (
                                    $settleableProjectIdSet === null
                                    || isset($settleableProjectIdSet[(int) $timeEntry->getProjectId()])
                                );
                                ?>
                                <tr data-entry-id="<?php p($timeEntry->getId()); ?>"
                                    data-project-id="<?php p($timeEntry->getProjectId()); ?>"
                                    data-user-id="<?php p($timeEntry->getUserId()); ?>"
                                    data-project-type="<?php p($entry['project_type'] ?? 'client'); ?>"
                                    data-date-iso="<?php p($timeEntry->getDate() ? $timeEntry->getDate()->format('Y-m-d') : ''); ?>"
                                    data-entry-hours="<?php p(number_format($entryHours, 4, '.', '')); ?>"
                                    data-billing-status="<?php p($entryBillingStatus); ?>"
                                    data-can-settle="<?php p($rowCanSettle ? '1' : '0'); ?>">
                                    <?php if ($canSettleAnything): ?>
                                    <td class="col-select" data-label="<?php p($l->t('Select')); ?>">
                                        <?php if ($rowCanSettle): ?>
                                            <label class="pc-billing-select-hit">
                                                <input type="checkbox" class="pc-billing-select"
                                                    value="<?php p((string)$timeEntry->getId()); ?>"
                                                    aria-label="<?php p($l->t('Select entry from %1$s, %2$s hours', [$timeEntry->getDate() ? $timeEntry->getDate()->format('d.m.Y') : '', number_format($entryHours, 2)])); ?>">
                                            </label>
                                        <?php endif; ?>
                                    </td>
                                    <?php endif; ?>
                                    <td data-label="<?php p($colDate); ?>"><?php p($timeEntry->getDate() ? $timeEntry->getDate()->format('d.m.Y') : ''); ?></td>
                                    <td data-label="<?php p($colProject); ?>">
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
                                    <td data-label="<?php p($colType); ?>">
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
                                    <td data-label="<?php p($colCustomer); ?>"><?php p($entry['customerName'] ?? ''); ?></td>
                                    <td data-label="<?php p($colUser); ?>"><?php p($entry['userDisplayName'] ?? $timeEntry->getUserId() ?? ''); ?></td>
                                    <td class="col-hours" data-label="<?php p($colHours); ?>">
                                        <span class="time-entries-hours-value"><?php p($fmt ? $fmt->hours($entryHours) : number_format($entryHours, 2) . 'h'); ?></span>
                                    </td>
                                    <td class="col-settlement" data-label="<?php p($colSettlement); ?>">
                                        <?php
                                        $chipKind = 'status';
                                        $chipValue = $entryBillingStatus;
                                        include __DIR__ . '/parts/settlement-chip.php';
                                        ?>
                                    </td>
                                    <?php $entryDescription = (string)($timeEntry->getDescription() ?? ''); ?>
                                    <td class="description-cell" data-label="<?php p($colDescription); ?>"<?php if ($entryDescription !== ''): ?> title="<?php p($entryDescription); ?>"<?php endif; ?>>
                                        <span class="description-cell__text"><?php p($entryDescription); ?></span>
                                    </td>
                                    <td class="col-actions" data-label="<?php p($colActions); ?>">
                                        <div class="action-items" role="group" aria-label="<?php p($l->t('Time entry actions')); ?>">
                                            <a href="<?php p(str_replace('ENTRY_ID', $timeEntry->getId(), $_['showUrl'] ?? '/index.php/apps/projectcheck/time-entries/')); ?>"
                                                class="action-item action-item--view" title="<?php p($l->t('View Details')); ?>"
                                                aria-label="<?php p($l->t('View time entry details')); ?>">
                                                <span data-lucide="eye" class="lucide-icon" aria-hidden="true"></span>
                                            </a>
                                            <?php $entryLocked = method_exists($timeEntry, 'isBillingLocked') && $timeEntry->isBillingLocked(); ?>
                                            <?php if ($timeEntry->isOwnedBy((string)($_['userId'] ?? '')) && !$entryLocked): ?>
                                                <a href="<?php p(str_replace('ENTRY_ID', (string)$timeEntry->getId(), $_['editUrl'] ?? '/index.php/apps/projectcheck/time-entries/edit/')); ?>"
                                                    class="action-item action-item--edit" title="<?php p($l->t('Edit Time Entry')); ?>" aria-label="<?php p($l->t('Edit time entry')); ?>">
                                                    <span data-lucide="edit" class="lucide-icon" aria-hidden="true"></span>
                                                    <span class="action-item__label pc-sr-only"><?php p($l->t('Edit')); ?></span>
                                                </a>
                                                <button type="button" class="action-item action-item--danger delete-entry-btn"
                                                    data-entry-id="<?php p((string)$timeEntry->getId()); ?>"
                                                    data-entry-description="<?php p($timeEntry->getDescription() ?? ''); ?>"
                                                    data-delete-url="<?php p(str_replace('ENTRY_ID', (string)$timeEntry->getId(), $_['deleteUrl'] ?? '')); ?>"
                                                    title="<?php p($l->t('Delete Time Entry')); ?>"
                                                    aria-label="<?php p($l->t('Delete time entry')); ?>">
                                                    <span data-lucide="trash-2" class="lucide-icon" aria-hidden="true"></span>
                                                    <span class="action-item__label pc-sr-only"><?php p($l->t('Delete')); ?></span>
                                                </button>
                                            <?php elseif ($timeEntry->isOwnedBy((string)($_['userId'] ?? '')) && $entryLocked): ?>
                                                <span class="action-item action-item--locked"
                                                    title="<?php p($l->t('Invoiced or paid entries are locked. Ask a project manager to reopen this entry to change it.')); ?>">
                                                    <span data-lucide="lock" class="lucide-icon" aria-hidden="true"></span>
                                                    <span class="pc-sr-only"><?php p($l->t('Locked — invoiced or paid entries cannot be edited or deleted.')); ?></span>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr class="time-entries-summary">
                                <th scope="row" colspan="<?php p($canSettleAnything ? '7' : '6'); ?>" class="time-entries-summary__lead" id="time-entries-summary-label">
                                    <span class="time-entries-summary__title"><?php p($l->t('Total hours (matching filters)')); ?></span>
                                    <span class="time-entries-summary__meta" id="time-entries-selection-meta">
                                        <span id="time-entries-selection-count"><?php p($l->n('%n matching entry', '%n matching entries', $selectionEntryCount)); ?></span>
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
                    <div class="time-entries-panel__footer pagination">
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
                            <a class="button secondary" href="<?php p($baseUrl . '?' . http_build_query($prevQuery)); ?>">
                                ‹ <?php p($l->t('Previous')); ?>
                            </a>
                            <?php else: ?>
                            <span class="button secondary disabled" aria-disabled="true"><?php p($l->t('Previous')); ?></span>
                            <?php endif; ?>
                            <?php if ($currentPage < $totalPages): ?>
                            <a class="button secondary" href="<?php p($baseUrl . '?' . http_build_query($nextQuery)); ?>">
                                <?php p($l->t('Next')); ?> ›
                            </a>
                            <?php else: ?>
                            <span class="button secondary disabled" aria-disabled="true"><?php p($l->t('Next')); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
<?php include __DIR__ . '/common/page-end.php'; ?>

