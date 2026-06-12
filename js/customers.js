/**
 * Customers Management JavaScript for the projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

/* global t */
(function () {
    'use strict';

    // DOM elements
    const elements = {
        searchInput: document.getElementById('customer-search'),
        clearFiltersBtn: document.getElementById('clear-filters'),
        customersTable: document.querySelector('.grid'),
        customersTbody: document.querySelector('.grid tbody')
    };

    /**
     * Initialize the application
     */
    function init() {
        bindEvents();
        initMessageAutoHide();
    }

    /**
     * Bind event listeners
     */
    function bindEvents() {
        // Apply search on Enter
        if (elements.searchInput) {
            elements.searchInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    applyFilters();
                }
            });
        }

        // Apply button (if present)
        const applyBtn = document.getElementById('apply-filters');
        if (applyBtn) {
            applyBtn.addEventListener('click', function (e) {
                e.preventDefault();
                applyFilters();
            });
        }

        // Clear filters
        if (elements.clearFiltersBtn) {
            elements.clearFiltersBtn.addEventListener('click', clearFilters);
        }

        // Delete customer buttons
        document.addEventListener('click', function (e) {
            if (e.target.closest('.delete-customer-btn')) {
                const button = e.target.closest('.delete-customer-btn');
                const customerId = button.getAttribute('data-customer-id');
                const customerName = button.getAttribute('data-customer-name');
                const deleteUrl = button.getAttribute('data-delete-url');
                showCustomerDeletionModal(customerId, customerName, deleteUrl);
            }
        });
    }

    /**
     * Apply all filters
     */
    function applyFilters() {
        const searchTerm = elements.searchInput ? elements.searchInput.value : '';
        const url = new URL(window.location.href);
        searchTerm ? url.searchParams.set('search', searchTerm) : url.searchParams.delete('search');
        url.searchParams.set('page', '1');
        window.location.href = url.toString();
    }

    /**
     * Clear all filters
     */
    function clearFilters() {
        const url = new URL(window.location.href);
        url.searchParams.delete('search');
        url.searchParams.set('page', '1');
        window.location.href = url.toString();
    }

    /**
     * Update empty state visibility
     */
    function updateEmptyState() {
        // For server-side paging, the empty state is handled on render.
    }

    /**
     * @param {string} customerId
     * @param {string} deleteUrl From data-delete-url (may still contain CUSTOMER_ID placeholder)
     * @returns {string}
     */
    function resolveCustomerDeleteUrl(customerId, deleteUrl) {
        const id = String(customerId || '').trim();
        if (!id) {
            return deleteUrl || '';
        }
        if (deleteUrl && deleteUrl.indexOf('CUSTOMER_ID') !== -1) {
            return deleteUrl.split('CUSTOMER_ID').join(encodeURIComponent(id));
        }
        if (deleteUrl) {
            return deleteUrl;
        }
        if (typeof OC !== 'undefined' && OC.generateUrl) {
            return OC.generateUrl('/apps/projectcheck/customers/{id}/delete', { id: id });
        }
        return '/index.php/apps/projectcheck/customers/' + encodeURIComponent(id) + '/delete';
    }

    /**
     * Show customer deletion modal
     */
    function showCustomerDeletionModal(customerId, customerName, deleteUrl) {
        deleteUrl = resolveCustomerDeleteUrl(customerId, deleteUrl);
        if (typeof window.projectcheckDeletionModal === 'undefined') {
            if (typeof OC !== 'undefined' && OC.Notification) {
                OC.Notification.showTemporary(
                    t('projectcheck', 'Could not open the confirmation dialog. Reload the page and try again.'),
                    { type: 'error' }
                );
            }
            return;
        }

        // Set up success callback via show() options only (legacy onSuccess cleared on close).
        window.projectcheckDeletionModal.show({
            entityType: 'customer',
            entityId: customerId,
            entityName: customerName,
            deleteUrl: deleteUrl,
            onSuccess: function (entity) {
                // Remove the row from the table
                const row = document.querySelector(`tr[data-customer-id="${entity.id}"]`);
                if (row) {
                    row.remove();
                    updateEmptyState();
                }
            },
            onCancel: function () {
            }
        });
    }

    /**
     * Delete customer via AJAX
     */
    function deleteCustomer(customerId, options = {}) {
        // Try to get the delete URL from a data attribute or generate it
        let url;
        const deleteButton = document.querySelector(`button[data-customer-id="${customerId}"]`);

        if (deleteButton && deleteButton.dataset.deleteUrl) {
            url = resolveCustomerDeleteUrl(customerId, deleteButton.dataset.deleteUrl);
        } else {
            // Use Nextcloud's URL generation if available, otherwise fallback to hardcoded URL
            url = typeof OC !== 'undefined' && OC.generateUrl ?
                OC.generateUrl(`/apps/projectcheck/customers/${customerId}/delete`) :
                `/index.php/apps/projectcheck/customers/${customerId}/delete`;
        }

        const token = document.querySelector('input[name="requesttoken"]')?.value ||
            (typeof OC !== 'undefined' ? OC.requestToken : '');

        const formData = new FormData();
        formData.append('requesttoken', token);
        if (options.strategy) {
            formData.append('strategy', options.strategy);
        }
        if (options.strategy === 'reassign' && options.reassignCustomerId) {
            formData.append('reassign_customer_id', options.reassignCustomerId);
        }

        fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'requesttoken': token
            },
            body: formData,
            credentials: 'same-origin'
        })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Remove the row from the table
                    const row = document.querySelector(`tr[data-customer-id="${customerId}"]`);
                    if (row) {
                        row.remove();
                        updateEmptyState();
                    }

                    // Show success message
                    showMessage(t('projectcheck', 'Customer deleted successfully'), 'success');
                } else {
                    showMessage(data.error || data.message || t('projectcheck', 'Failed to delete customer'), 'error');
                }
            })
            .catch(function () {
                showMessage(t('projectcheck', 'An error occurred while deleting the customer'), 'error');
            });
    }

    /**
     * Show message
     */
    function showMessage(message, type) {
        const level = type || 'info';
        // Remove existing messages
        const existingMessages = document.querySelectorAll('.notice');
        existingMessages.forEach(msg => msg.remove());

        // Create new message
        const messageDiv = document.createElement('div');
        messageDiv.className = 'notice notice-' + level;
        messageDiv.setAttribute('role', level === 'error' ? 'alert' : 'status');
        messageDiv.setAttribute('aria-live', level === 'error' ? 'assertive' : 'polite');
        messageDiv.setAttribute('aria-atomic', 'true');
        const icon = document.createElement('i');
        let iconName = 'info';
        if (level === 'success') {
            iconName = 'checkmark';
        } else if (level === 'error') {
            iconName = 'error';
        }
        icon.className = 'icon icon-' + iconName;
        icon.setAttribute('aria-hidden', 'true');
        const span = document.createElement('span');
        span.textContent = message;
        messageDiv.appendChild(icon);
        messageDiv.appendChild(span);

        // Insert after the page header bar; fall back to the top of the main
        // content area when the bar is not rendered (e.g. read-only users).
        const header = document.querySelector('.header-content');
        if (header && header.parentNode) {
            header.parentNode.insertBefore(messageDiv, header.nextSibling);
        } else {
            const main = document.getElementById('pc-main-content') || document.querySelector('main') || document.body;
            main.insertBefore(messageDiv, main.firstChild);
        }

        // Auto-hide after 5 seconds
        setTimeout(() => {
            if (messageDiv.parentNode) {
                messageDiv.remove();
            }
        }, 5000);
    }

    /**
     * Initialize message auto-hide
     */
    function initMessageAutoHide() {
        const messages = document.querySelectorAll('.notice');
        messages.forEach(message => {
            setTimeout(() => {
                if (message.parentNode) {
                    message.remove();
                }
            }, 5000);
        });
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
