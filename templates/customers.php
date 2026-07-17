<?php

/**
 * Customers template for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

use OCP\Util;

Util::addScript('projectcheck', 'common/export-menu');
Util::addScript('projectcheck', 'customers');
Util::addStyle('projectcheck', 'customers');
Util::addStyle('projectcheck', 'navigation');
Util::addStyle('projectcheck', 'common/list-table');
Util::addStyle('projectcheck', 'common/list-layout');
Util::addStyle('projectcheck', 'common/filters');
$fmt = $_['fmt'] ?? null;
$currencyCode = isset($_['orgCurrency']) && is_string($_['orgCurrency']) ? strtoupper(trim($_['orgCurrency'])) : 'EUR';
if (preg_match('/^[A-Z]{3}$/', $currencyCode) !== 1) {
	$currencyCode = 'EUR';
}
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<?php
$pageId = 'customers';
$pageTitle = $l->t('Customers');
$pageHelp = $l->t('Manage your customer relationships');
ob_start(); ?>
                    <?php if (!empty($_['canCreateCustomer'])): ?>
                    <a href="<?php p($_['urlGenerator']->linkToRoute('projectcheck.customer.create')); ?>" class="button primary">
                        <span data-lucide="plus" class="lucide-icon" aria-hidden="true"></span>
                        <?php p($l->t('Add New Customer')); ?>
                    </a>
                    <?php endif; ?>
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
                    <?php if (isset($_GET['customer_name'])): ?>
                        <?php p($l->t('Customer "%s" was created successfully!', [$_GET['customer_name']])); ?>
                    <?php elseif (isset($_GET['deleted'])): ?>
                        <?php p($l->t('Customer was deleted successfully!')); ?>
                    <?php elseif (isset($_GET['updated'])): ?>
                        <?php p($l->t('Customer was updated successfully!')); ?>
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

        <!-- Customer Statistics Overview -->
        <section class="section pc-stats-panel pc-section" aria-labelledby="customers-stats-title">
            <div class="section-header">
                <h3 id="customers-stats-title"><i data-lucide="bar-chart-3" class="lucide-icon primary" aria-hidden="true"></i> <?php p($l->t('Customer Statistics')); ?></h3>
                <p><?php p($l->t('Overview of your customer relationships and project data')); ?></p>
            </div>
            <div class="section-content">
            <ul class="pc-stats-grid" role="list">
                <li class="pc-stat-card">
                    <span class="pc-stat-card__icon" aria-hidden="true"><i data-lucide="users" class="lucide-icon"></i></span>
                    <div class="pc-stat-card__body">
                        <div class="pc-stat-card__value"><?php p($_['stats']['totalCustomers'] ?? 0); ?></div>
                        <div class="pc-stat-card__label"><?php p($l->t('Total Customers')); ?></div>
                        <div class="pc-stat-card__detail">
                            <span><?php p($l->t('Active clients')); ?></span>
                        </div>
                    </div>
                </li>

                <li class="pc-stat-card">
                    <span class="pc-stat-card__icon" aria-hidden="true"><i data-lucide="folder" class="lucide-icon"></i></span>
                    <div class="pc-stat-card__body">
                        <div class="pc-stat-card__value"><?php p($_['stats']['totalProjects'] ?? 0); ?></div>
                        <div class="pc-stat-card__label"><?php p($l->t('Total Projects')); ?></div>
                        <div class="pc-stat-card__detail">
                            <span><?php p($_['stats']['activeProjects'] ?? 0); ?> <?php p($l->t('active')); ?></span>
                            <span><?php p($_['stats']['completedProjects'] ?? 0); ?> <?php p($l->t('completed')); ?></span>
                        </div>
                    </div>
                </li>

                <li class="pc-stat-card">
                    <span class="pc-stat-card__icon" aria-hidden="true"><i data-lucide="clock" class="lucide-icon"></i></span>
                    <div class="pc-stat-card__body">
                        <div class="pc-stat-card__value"><?php p($_['stats']['totalHours'] ?? 0); ?></div>
                        <div class="pc-stat-card__label"><?php p($l->t('Total Hours')); ?></div>
                        <div class="pc-stat-card__detail">
                            <span><?php p($l->t('Across all projects')); ?></span>
                        </div>
                    </div>
                </li>

                <li class="pc-stat-card">
                    <span class="pc-stat-card__icon" aria-hidden="true"><i data-lucide="euro" class="lucide-icon"></i></span>
                    <div class="pc-stat-card__body">
                        <div class="pc-stat-card__value"><?php p($fmt ? $fmt->currency((float)($_['stats']['totalRevenue'] ?? 0)) : $currencyCode . ' ' . number_format((float)($_['stats']['totalRevenue'] ?? 0), 2)); ?></div>
                        <div class="pc-stat-card__label"><?php p($l->t('Total Revenue')); ?></div>
                        <div class="pc-stat-card__detail">
                            <span><?php p($l->t('From all customers')); ?></span>
                        </div>
                    </div>
                </li>
            </ul>
            </div>
        </section>

        <?php
        $colName = $l->t('Name');
        $colEmail = $l->t('Email');
        $colPhone = $l->t('Phone');
        $colContact = $l->t('Contact Person');
        $colProjects = $l->t('Projects');
        $colOutstanding = $l->t('Not yet paid');
        $colInvoicing = $l->t('Invoicing');
        $colActions = $l->t('Actions');
        $settlementByCustomer = $_['settlementByCustomer'] ?? [];
        ?>

        <!-- Filters + customers table (one panel) -->
        <div class="section pc-list-panel pc-section" aria-labelledby="pc-customers-list-heading">
            <div class="section-header">
                <h3 id="pc-customers-list-heading"><i data-lucide="users" class="lucide-icon primary" aria-hidden="true"></i> <?php p($l->t('All customers')); ?></h3>
                <p><?php p($l->t('Search and filter')); ?></p>
            </div>
            <div class="pc-list-panel__toolbar">
            <div class="filters-container pc-filters" role="search" aria-label="<?php p($l->t('Search and filter customers')); ?>">
                <div class="pc-filters__grid">
                    <div class="pc-filters__field pc-filters__field--search">
                        <label for="customer-search" class="pc-filters__label"><?php p($l->t('Search')); ?></label>
                        <div class="pc-filters__search search-input-wrapper">
                            <span class="pc-list-search-icon" aria-hidden="true"><i data-lucide="search" class="lucide-icon"></i></span>
                            <input type="search" id="customer-search" class="search-input"
                                placeholder="<?php p($l->t('Search customers...')); ?>"
                                value="<?php p($_['filters']['search'] ?? ''); ?>"
                                autocomplete="off">
                        </div>
                    </div>

                    <?php $settlementFilterValue = (string)($_['filters']['settlement'] ?? ''); ?>
                    <div class="pc-filters__field">
                        <label for="settlement-filter" class="pc-filters__label"><?php p($l->t('Settlement')); ?></label>
                        <select id="settlement-filter" class="filter-select">
                            <option value="all"<?php if ($settlementFilterValue === '' || $settlementFilterValue === 'all') echo ' selected'; ?>><?php p($l->t('Settlement: all')); ?></option>
                            <option value="outstanding"<?php if ($settlementFilterValue === 'outstanding') echo ' selected'; ?>><?php p($l->t('Not yet paid')); ?></option>
                            <option value="open"<?php if ($settlementFilterValue === 'open') echo ' selected'; ?>><?php p($l->t('Open')); ?></option>
                            <option value="partial"<?php if ($settlementFilterValue === 'partial') echo ' selected'; ?>><?php p($l->t('Partially settled')); ?></option>
                            <option value="awaiting_payment"<?php if ($settlementFilterValue === 'awaiting_payment') echo ' selected'; ?>><?php p($l->t('Awaiting payment')); ?></option>
                            <option value="paid"<?php if ($settlementFilterValue === 'paid') echo ' selected'; ?>><?php p($l->t('Paid')); ?></option>
                            <option value="n_a"<?php if ($settlementFilterValue === 'n_a') echo ' selected'; ?>><?php p($l->t('Nothing to invoice')); ?></option>
                        </select>
                    </div>
                </div>

                <div class="pc-filters__actions">
                    <button id="apply-filters" class="button primary" type="button">
                        <span data-lucide="search" class="lucide-icon" aria-hidden="true"></span>
                        <?php p($l->t('Apply Filters')); ?>
                    </button>
                    <button id="clear-filters" class="button secondary" type="button">
                        <span data-lucide="rotate-ccw" class="lucide-icon" aria-hidden="true"></span>
                        <?php p($l->t('Clear Filters')); ?>
                    </button>
                    <?php
                    $exportUrl = (string)($_['exportUrl'] ?? '');
                    if ($exportUrl === '' && isset($_['urlGenerator']) && is_object($_['urlGenerator'])) {
                    	$exportUrl = (string)$_['urlGenerator']->linkToRoute('projectcheck.customer.export');
                    }
                    $exportEntityLabel = 'customers';
                    $exportFilterKeys = 'search,settlement';
                    $exportSuccessMsg = 'Exported {count} customers';
                    $exportIncludeSort = false;
                    $exportMenuId = 'pc-export-menu-customers';
                    include __DIR__ . '/parts/export-menu.php';
                    ?>
                </div>
            </div>
            </div>

            <?php if (empty($_['customers'])): ?>
                <div class="emptycontent">
                    <div class="emptycontent__icon" aria-hidden="true"><span data-lucide="user" class="lucide-icon"></span></div>
                    <h2><?php p($l->t('No customers found')); ?></h2>
                    <p><?php p($l->t('Add your first customer to get started!')); ?></p>
                </div>
            <?php else: ?>
                <div class="pc-list-table-wrap" tabindex="0" role="region" aria-label="<?php p($l->t('Customers')); ?>">
                <table class="grid customers-table pc-data-table">
                    <thead>
                        <tr>
                            <th scope="col"><?php p($colName); ?></th>
                            <th scope="col"><?php p($colEmail); ?></th>
                            <th scope="col"><?php p($colPhone); ?></th>
                            <th scope="col"><?php p($colContact); ?></th>
                            <th scope="col"><?php p($colProjects); ?></th>
                            <th scope="col" class="col-outstanding"><?php p($colOutstanding); ?></th>
                            <th scope="col" class="col-invoicing"><?php p($colInvoicing); ?></th>
                            <th scope="col" class="col-actions"><?php p($colActions); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($_['customers'] as $customer): ?>
                            <?php
                            $cid = (int)$customer->getId();
                            $rowSettlement = $settlementByCustomer[$cid] ?? null;
                            ?>
                            <tr data-customer-id="<?php p($customer->getId()); ?>">
                                <td data-label="<?php p($colName); ?>">
                                    <a href="<?php p(str_replace('CUSTOMER_ID', $customer->getId(), $_['showUrl'] ?? '')); ?>">
                                        <?php p($customer->getName()); ?>
                                    </a>
                                </td>
                                <td data-label="<?php p($colEmail); ?>"><?php p($customer->getEmail() ?? ''); ?></td>
                                <td data-label="<?php p($colPhone); ?>"><?php p($customer->getPhone() ?? ''); ?></td>
                                <td data-label="<?php p($colContact); ?>"><?php p($customer->getContactPerson() ?? ''); ?></td>
                                <td data-label="<?php p($colProjects); ?>"><?php p($customer->getProjectCount() ?? 0); ?></td>
                                <td class="col-outstanding" data-label="<?php p($colOutstanding); ?>">
                                    <?php if ($rowSettlement && (float)($rowSettlement['outstanding_hours'] ?? 0) > 0): ?>
                                        <span class="pc-invoicing-cell__outstanding">
                                            <?php p($l->t('%1$s h · %2$s', [
                                                number_format((float)$rowSettlement['outstanding_hours'], 2),
                                                $fmt ? $fmt->currency((float)$rowSettlement['outstanding_amount']) : $currencyCode . ' ' . number_format((float)$rowSettlement['outstanding_amount'], 2),
                                            ])); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="pc-muted"><?php p($l->t('—')); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="col-invoicing" data-label="<?php p($colInvoicing); ?>">
                                    <?php if ($rowSettlement): ?>
                                        <div class="pc-invoicing-cell">
                                            <?php
                                            $chipKind = 'posture';
                                            $chipValue = (string)($rowSettlement['posture'] ?? 'n_a');
                                            include __DIR__ . '/parts/settlement-chip.php';
                                            ?>
                                            <?php
                                            $progress = is_array($rowSettlement['progress'] ?? null) ? $rowSettlement['progress'] : [];
                                            $progressVariant = 'compact';
                                            $progressId = 'pc-cust-stl-' . (int)$customer->getId();
                                            include __DIR__ . '/parts/settlement-progress.php';
                                            ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="pc-muted"><?php p($l->t('—')); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="col-actions" data-label="<?php p($colActions); ?>">
                                    <div class="action-items" role="group" aria-label="<?php p($l->t('Customer actions')); ?>">
                                        <a href="<?php p(str_replace('CUSTOMER_ID', $customer->getId(), $_['showUrl'] ?? '')); ?>"
                                            class="action-item action-item--view" title="<?php p($l->t('View Details')); ?>"
                                            aria-label="<?php p($l->t('View details for customer %s', [$customer->getName() ?? ''])); ?>">
                                            <span data-lucide="eye" class="lucide-icon" aria-hidden="true"></span>
                                        </a>
                                        <?php if (!empty($_['editableCustomerIds'][(int)$customer->getId()])): ?>
                                        <a href="<?php p(str_replace('CUSTOMER_ID', $customer->getId(), $_['editUrl'] ?? '')); ?>"
                                            class="action-item action-item--edit" title="<?php p($l->t('Edit Customer')); ?>"
                                            aria-label="<?php p($l->t('Edit customer %s', [$customer->getName() ?? ''])); ?>">
                                            <span data-lucide="edit" class="lucide-icon" aria-hidden="true"></span>
                                        </a>
                                        <?php endif; ?>
                                        <?php if (!empty($_['editableCustomerIds'][(int)$customer->getId()])): ?>
                                            <?php
                                            $projectCount = (int)($customer->getProjectCount() ?? 0);
                                            $hasProjects = $projectCount > 0;
                                            $deleteTitle = $hasProjects
                                                ? $l->t('Delete customer (choose how to handle projects)')
                                                : $l->t('Delete Customer');
                                            $deleteAria = $hasProjects
                                                ? $l->t('Delete customer %s (choose how to handle associated projects)', [$customer->getName() ?? ''])
                                                : $l->t('Delete customer %s', [$customer->getName() ?? '']);
                                            ?>
                                            <button type="button" class="action-item action-item--danger delete-customer-btn"
                                                data-customer-id="<?php p($customer->getId()); ?>"
                                                data-customer-name="<?php p($customer->getName()); ?>"
                                                data-delete-url="<?php p(str_replace('CUSTOMER_ID', (string)$customer->getId(), $_['deleteUrl'] ?? '')); ?>"
                                                title="<?php p($deleteTitle); ?>"
                                                aria-label="<?php p($deleteAria); ?>">
                                                <span data-lucide="trash-2" class="lucide-icon" aria-hidden="true"></span>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
                <?php
                    $pagination = $_['pagination'] ?? ['page' => 1, 'totalPages' => 1, 'totalEntries' => count($_['customers'] ?? []), 'perPage' => count($_['customers'] ?? [])];
                    $currentPage = max(1, (int)($pagination['page'] ?? 1));
                    $totalPages = max(1, (int)($pagination['totalPages'] ?? 1));
                    $totalEntries = (int)($pagination['totalEntries'] ?? 0);
                    $perPage = (int)($pagination['perPage'] ?? 0);
                    $baseUrl = $_['urlGenerator']->linkToRoute('projectcheck.customer.index');
                    $baseQuery = $_['filters'] ?? [];
                    unset($baseQuery['limit'], $baseQuery['offset']);
                ?>
                <?php if ($totalPages > 1): ?>
                    <div class="pc-list-panel__footer pagination">
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