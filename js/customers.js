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
        settlementFilter: document.getElementById('settlement-filter'),
        clearFiltersBtn: document.getElementById('clear-filters'),
        customersTable: document.querySelector('.customers-table'),
        customersTbody: document.querySelector('.customers-table tbody')
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
                if (deletionModalOpen) {
                    return;
                }
                const button = e.target.closest('.delete-customer-btn');
                if (button.disabled) {
                    return;
                }
                const customerId = button.getAttribute('data-customer-id');
                const customerName = button.getAttribute('data-customer-name');
                const deleteUrl = button.getAttribute('data-delete-url');
                showCustomerDeletionModal(customerId, customerName, deleteUrl, button);
            }
        });
    }

    /**
     * Apply all filters
     */
    function applyFilters() {
        const searchTerm = elements.searchInput ? elements.searchInput.value : '';
        const settlement = elements.settlementFilter ? elements.settlementFilter.value : '';
        const url = new URL(window.location.href);
        searchTerm ? url.searchParams.set('search', searchTerm) : url.searchParams.delete('search');
        if (settlement && settlement !== 'all') {
            url.searchParams.set('settlement', settlement);
        } else {
            url.searchParams.delete('settlement');
        }
        url.searchParams.set('page', '1');
        window.location.href = url.toString();
    }

    /**
     * Clear all filters
     */
    function clearFilters() {
        const url = new URL(window.location.href);
        url.searchParams.delete('search');
        url.searchParams.delete('settlement');
        if (elements.settlementFilter) {
            elements.settlementFilter.value = 'all';
        }
        url.searchParams.set('page', '1');
        window.location.href = url.toString();
    }

    /**
     * Update empty state visibility
     */
    function updateEmptyState() {
        const tbody = elements.customersTbody;
        if (!tbody) {
            return;
        }
        const remaining = tbody.querySelectorAll('tr[data-customer-id]').length;
        if (remaining === 0) {
            window.location.reload();
        }
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

    /** @type {boolean} */
    let deletionModalOpen = false;

    /**
     * Show customer deletion modal
     */
    function showCustomerDeletionModal(customerId, customerName, deleteUrl, triggerButton) {
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

        if (deletionModalOpen) {
            return;
        }
        deletionModalOpen = true;
        if (triggerButton) {
            triggerButton.disabled = true;
        }

        function releaseDeletionTrigger() {
            deletionModalOpen = false;
            if (triggerButton) {
                triggerButton.disabled = false;
            }
        }

        window.projectcheckDeletionModal.show({
            entityType: 'customer',
            entityId: customerId,
            entityName: customerName,
            deleteUrl: deleteUrl,
            onSuccess: function (entity) {
                releaseDeletionTrigger();
                const row = document.querySelector(`tr[data-customer-id="${entity.id}"]`);
                if (row) {
                    row.remove();
                    updateEmptyState();
                }
                showMessage(t('projectcheck', 'Customer deleted successfully'), 'success');
            },
            onCancel: function () {
                releaseDeletionTrigger();
            },
            onRelease: releaseDeletionTrigger
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
        const existingMessages = document.querySelectorAll('.pc-page-notice, .notice');
        existingMessages.forEach(msg => msg.remove());

        const messageDiv = document.createElement('div');
        messageDiv.className = 'notice notice-' + level + ' pc-page-notice';
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

        const main = document.getElementById('pc-main-content') || document.querySelector('.pc-main');
        if (main) {
            main.insertBefore(messageDiv, main.firstChild);
        } else {
            const pageHeader = document.querySelector('.pc-page-header');
            if (pageHeader && pageHeader.parentNode) {
                pageHeader.parentNode.insertBefore(messageDiv, pageHeader.nextSibling);
            } else {
                document.body.insertBefore(messageDiv, document.body.firstChild);
            }
        }

        const liveRegion = document.getElementById('pc-live-region');
        if (liveRegion) {
            liveRegion.textContent = message;
        }

        requestAnimationFrame(function () {
            messageDiv.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        });

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
