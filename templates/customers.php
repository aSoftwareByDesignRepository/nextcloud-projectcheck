<?php

/**
 * Customers template for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

use OCP\Util;

Util::addScript('projectcheck', 'customers');
Util::addStyle('projectcheck', 'customers');
Util::addStyle('projectcheck', 'navigation');
Util::addStyle('projectcheck', 'customer-statistics');
$fmt = $_['fmt'] ?? null;
$currencyCode = isset($_['orgCurrency']) && is_string($_['orgCurrency']) ? strtoupper(trim($_['orgCurrency'])) : 'EUR';
if (preg_match('/^[A-Z]{3}$/', $currencyCode) !== 1) {
	$currencyCode = 'EUR';
}
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<div id="app-content" role="main">
    <div id="app-content-wrapper">
        <!-- Page Header -->
        <div class="section">
            <div class="header-content">
                <div class="header-text">
                    <h2><?php p($l->t('Customers')); ?></h2>
                    <p><?php p($l->t('Manage your customer relationships')); ?></p>
                </div>
                <div class="header-actions">
                    <?php if (!empty($_['canCreateCustomer'])): ?>
                    <a href="<?php p($_['urlGenerator']->linkToRoute('projectcheck.customer.create')); ?>" class="button primary">
                        <?php p($l->t('Add New Customer')); ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_GET['message']) && $_GET['message'] === 'success'): ?>
            <div class="notice notice-success">
                <i class="icon icon-checkmark"></i>
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
                <i class="icon icon-error"></i>
                <span><?php p($l->t('Error: %s', [$_GET['error_text']])); ?></span>
            </div>
        <?php endif; ?>

        <!-- Customer Statistics Overview -->
        <div class="section">
            <div class="section-header">
                <h3><?php p($l->t('Customer Statistics')); ?></h3>
                <p><?php p($l->t('Overview of your customer relationships and project data')); ?></p>
            </div>

            <div class="overview-stats">
                <div class="overview-stat">
                    <div class="stat-icon">
                        <i data-lucide="users" class="lucide-icon white"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php p($_['stats']['totalCustomers'] ?? 0); ?></div>
                        <div class="stat-label"><?php p($l->t('Total Customers')); ?></div>
                        <div class="stat-detail">
                            <span class="stat-sub"><?php p($l->t('Active clients')); ?></span>
                        </div>
                    </div>
                </div>

                <div class="overview-stat">
                    <div class="stat-icon">
                        <i data-lucide="folder" class="lucide-icon white"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php p($_['stats']['totalProjects'] ?? 0); ?></div>
                        <div class="stat-label"><?php p($l->t('Total Projects')); ?></div>
                        <div class="stat-detail">
                            <span class="stat-sub"><?php p($_['stats']['activeProjects'] ?? 0); ?> <?php p($l->t('active')); ?></span>
                            <span class="stat-sub"><?php p($_['stats']['completedProjects'] ?? 0); ?> <?php p($l->t('completed')); ?></span>
                        </div>
                    </div>
                </div>

                <div class="overview-stat">
                    <div class="stat-icon">
                        <i data-lucide="clock" class="lucide-icon white"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php p($_['stats']['totalHours'] ?? 0); ?></div>
                        <div class="stat-label"><?php p($l->t('Total Hours')); ?></div>
                        <div class="stat-detail">
                            <span class="stat-sub"><?php p($l->t('Across all projects')); ?></span>
                        </div>
                    </div>
                </div>

                <div class="overview-stat">
                    <div class="stat-icon">
                        <i data-lucide="euro" class="lucide-icon white"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php p($fmt ? $fmt->currency((float)($_['stats']['totalRevenue'] ?? 0)) : $currencyCode . ' ' . number_format((float)($_['stats']['totalRevenue'] ?? 0), 2)); ?></div>
                        <div class="stat-label"><?php p($l->t('Total Revenue')); ?></div>
                        <div class="stat-detail">
                            <span class="stat-sub"><?php p($l->t('From all customers')); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search and Filter Section -->
        <div class="section">
            <div class="filters-container">
                <div class="searchbox">
                    <input type="search" id="customer-search"
                        placeholder="<?php p($l->t('Search customers...')); ?>"
                        value="<?php p($_['filters']['search'] ?? ''); ?>"
                        aria-label="<?php p($l->t('Search customers')); ?>"
                        autocomplete="off">
                </div>

                <div class="filters-row">
                    <button id="apply-filters" class="button primary" type="button">
                        <?php p($l->t('Apply Filters')); ?>
                    </button>
                    <button id="clear-filters" class="button" type="button">
                        <?php p($l->t('Clear Filters')); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Customers Table -->
        <div class="section">
            <?php if (empty($_['customers'])): ?>
                <div class="emptycontent">
                    <div class="icon-user"></div>
                    <h2><?php p($l->t('No customers found')); ?></h2>
                    <p><?php p($l->t('Add your first customer to get started!')); ?></p>
                </div>
            <?php else: ?>
                <table class="grid">
                    <thead>
                        <tr>
                            <th><?php p($l->t('Name')); ?></th>
                            <th><?php p($l->t('Email')); ?></th>
                            <th><?php p($l->t('Phone')); ?></th>
                            <th><?php p($l->t('Contact Person')); ?></th>
                            <th><?php p($l->t('Projects')); ?></th>
                            <th><?php p($l->t('Actions')); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($_['customers'] as $customer): ?>
                            <tr data-customer-id="<?php p($customer->getId()); ?>">
                                <td>
                                    <a href="<?php p(str_replace('CUSTOMER_ID', $customer->getId(), $_['showUrl'] ?? '')); ?>">
                                        <?php p($customer->getName()); ?>
                                    </a>
                                </td>
                                <td><?php p($customer->getEmail() ?? ''); ?></td>
                                <td><?php p($customer->getPhone() ?? ''); ?></td>
                                <td><?php p($customer->getContactPerson() ?? ''); ?></td>
                                <td><?php p($customer->getProjectCount() ?? 0); ?></td>
                                <td>
                                    <div class="action-items">
                                        <a href="<?php p(str_replace('CUSTOMER_ID', $customer->getId(), $_['showUrl'] ?? '')); ?>"
                                            class="action-item" title="<?php p($l->t('View Details')); ?>"
                                            aria-label="<?php p($l->t('View details for customer %s', [$customer->getName() ?? ''])); ?>">
                                            <span class="icon icon-details" aria-hidden="true"></span>
                                        </a>
                                        <?php if (!empty($_['editableCustomerIds'][(int)$customer->getId()])): ?>
                                        <a href="<?php p(str_replace('CUSTOMER_ID', $customer->getId(), $_['editUrl'] ?? '')); ?>"
                                            class="action-item" title="<?php p($l->t('Edit Customer')); ?>"
                                            aria-label="<?php p($l->t('Edit customer %s', [$customer->getName() ?? ''])); ?>">
                                            <span class="icon icon-rename" aria-hidden="true"></span>
                                        </a>
                                        <?php endif; ?>
                                        <?php if ($customer->getCanDelete()): ?>
                                            <button type="button" class="action-item delete-customer-btn"
                                                data-customer-id="<?php p($customer->getId()); ?>"
                                                data-customer-name="<?php p($customer->getName()); ?>"
                                                data-delete-url="<?php p($_['deleteUrl'] ?? ''); ?>"
                                                title="<?php p($l->t('Delete Customer')); ?>"
                                                aria-label="<?php p($l->t('Delete customer %s', [$customer->getName() ?? ''])); ?>">
                                                <span class="icon icon-delete" aria-hidden="true"></span>
                                            </button>
                                        <?php else: ?>
                                            <span class="action-item disabled"
                                                role="img"
                                                aria-label="<?php p($l->t('Cannot delete customer %s with associated projects', [$customer->getName() ?? ''])); ?>"
                                                title="<?php p($l->t('Cannot delete customer with associated projects')); ?>">
                                                <span class="icon icon-delete" style="opacity: 0.3;" aria-hidden="true"></span>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
    </div>
</div>

<?php /* Icons are hydrated by the centralised js/common/icons.js module loaded via templates/common/navigation.php (audit ref. AUDIT-FINDINGS H22). */ ?>