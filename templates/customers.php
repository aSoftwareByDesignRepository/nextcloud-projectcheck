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
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<div id="app-content">
    <div id="app-content-wrapper">
        <!-- Page Header -->
        <div class="section">
            <div class="header-content">
                <div class="header-text">
                    <h2><?php p($l->t('Customers')); ?></h2>
                    <p><?php p($l->t('Manage your customer relationships')); ?></p>
                </div>
                <div class="header-actions">
                    <a href="<?php p($_['urlGenerator']->linkToRoute('projectcheck.customer.create')); ?>" class="button primary">
                        <?php p($l->t('Add New Customer')); ?>
                    </a>
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
                        <div class="stat-number">€<?php p(number_format($_['stats']['totalRevenue'] ?? 0, 2)); ?></div>
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
                    <input type="text" id="customer-search"
                        placeholder="<?php p($l->t('Search customers...')); ?>"
                        value="<?php p($_['filters']['search'] ?? ''); ?>">
                </div>

                <div class="filters-row">
                    <button id="clear-filters" class="button">
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
                                            class="action-item" title="<?php p($l->t('View Details')); ?>">
                                            <span class="icon icon-details"></span>
                                        </a>
                                        <a href="<?php p(str_replace('CUSTOMER_ID', $customer->getId(), $_['editUrl'] ?? '')); ?>"
                                            class="action-item" title="<?php p($l->t('Edit Customer')); ?>">
                                            <span class="icon icon-rename"></span>
                                        </a>
                                        <?php if ($customer->getCanDelete()): ?>
                                            <button type="button" class="action-item delete-customer-btn"
                                                data-customer-id="<?php p($customer->getId()); ?>"
                                                data-customer-name="<?php p($customer->getName()); ?>"
                                                data-delete-url="<?php p($_['deleteUrl'] ?? ''); ?>"
                                                title="<?php p($l->t('Delete Customer')); ?>">
                                                <span class="icon icon-delete"></span>
                                            </button>
                                        <?php else: ?>
                                            <span class="action-item disabled"
                                                title="<?php p($l->t('Cannot delete customer with associated projects')); ?>">
                                                <span class="icon icon-delete" style="opacity: 0.3;"></span>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<script nonce="<?php p($_['cspNonce']) ?>">
    // Local SVG icon library
    const svgIcons = {
        users: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        folder: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>',
        play: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><polygon points="5,3 19,12 5,21"/></svg>',
        euro: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.25.5-2.5 1.5-3.5Z"/></svg>',
        clock: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>',
        'check-circle': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22,4 12,14.01 9,11.01"/></svg>',
        'trending-up': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><polyline points="22,7 13.5,15.5 8.5,10.5 2,17"/><polyline points="16,7 22,7 22,13"/></svg>',
        wallet: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M19 7H5a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2Z"/><path d="M16 14a2 2 0 1 0 0-4 2 2 0 0 0 0 4Z"/></svg>',
        percent: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>',
        'bar-chart-3': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M3 3v18h18"/><path d="M18 17V9"/><path d="M13 17V5"/><path d="M8 17v-3"/></svg>',
        'dollar-sign': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
        target: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>',
        trophy: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 1 0 5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21l-1.5.5A2 2 0 0 1 5 17v-2.34"/><path d="M14 14.66V17c0 .55.47.98.97 1.21l1.5.5A2 2 0 0 0 19 17v-2.34"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>',
        activity: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><polyline points="22,12 18,12 15,21 9,3 6,12 2,12"/></svg>'
    };

    // Initialize icons
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-lucide]').forEach(function(el) {
            const iconName = el.getAttribute('data-lucide');
            if (svgIcons[iconName]) {
                el.innerHTML = svgIcons[iconName];
            }
        });

        // Initialize any customer-specific functionality
        console.log('Customer page loaded');
    });
</script>