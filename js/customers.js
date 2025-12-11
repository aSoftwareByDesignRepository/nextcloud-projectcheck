/**
 * Customers Management JavaScript for the projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

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
        console.log('Customers app initializing...');
        bindEvents();
        initMessageAutoHide();
        console.log('Customers app initialized');
    }

    /**
     * Bind event listeners
     */
    function bindEvents() {
        console.log('Binding events...');

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
                console.log('Delete button clicked for customer:', customerId, customerName);
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
     * Show customer deletion modal
     */
    function showCustomerDeletionModal(customerId, customerName, deleteUrl) {
        if (typeof window.projectcheckDeletionModal === 'undefined') {
            console.error('Deletion modal not loaded');
            // Fallback to old method
            confirmDeleteCustomer(customerId, customerName);
            return;
        }

        // Set up success callback
        window.projectcheckDeletionModal.onSuccess = function (entity) {
            // Remove the row from the table
            const row = document.querySelector(`tr[data-customer-id="${entity.id}"]`);
            if (row) {
                row.remove();
                updateEmptyState();
            }
        };

        // Show the modal
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
                console.log('Customer deletion cancelled');
            }
        });
    }

    /**
     * Fallback: Confirm delete customer (old method)
     */
    function confirmDeleteCustomer(customerId, customerName) {
        const name = customerName || 'this customer';
        const message = `Are you sure you want to delete ${name}?`;

        if (!confirm(message)) {
            return;
        }

        deleteCustomer(customerId, { strategy: 'restrict' });
    }

    /**
     * Delete customer via AJAX
     */
    function deleteCustomer(customerId, options = {}) {
        console.log('deleteCustomer called with customerId:', customerId);

        // Try to get the delete URL from a data attribute or generate it
        let url;
        const deleteButton = document.querySelector(`button[data-customer-id="${customerId}"]`);
        console.log('Delete button found:', deleteButton);
        console.log('Delete button dataset:', deleteButton ? deleteButton.dataset : 'No button found');

        if (deleteButton && deleteButton.dataset.deleteUrl) {
            url = deleteButton.dataset.deleteUrl.replace('CUSTOMER_ID', customerId);
            console.log('Using URL from data attribute:', url);
        } else {
            // Use Nextcloud's URL generation if available, otherwise fallback to hardcoded URL
            url = typeof OC !== 'undefined' && OC.generateUrl ?
                OC.generateUrl(`/apps/projectcheck/customers/${customerId}/delete`) :
                `/index.php/apps/projectcheck/customers/${customerId}/delete`;
            console.log('Using generated URL:', url);
        }

        console.log('Final Delete URL:', url);

        const token = document.querySelector('input[name="requesttoken"]')?.value ||
            (typeof OC !== 'undefined' ? OC.requestToken : '');

        console.log('Request token:', token);

        const bodyParams = new URLSearchParams();
        bodyParams.append('_method', 'DELETE');
        if (options.strategy) {
            bodyParams.append('strategy', options.strategy);
        }
        if (options.strategy === 'reassign' && options.reassignCustomerId) {
            bodyParams.append('reassign_customer_id', options.reassignCustomerId);
        }

        fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest',
                'requesttoken': token
            },
            body: bodyParams.toString()
        })
            .then(response => {
                console.log('Response status:', response.status);
                console.log('Response headers:', response.headers);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    // Remove the row from the table
                    const row = document.querySelector(`tr[data-customer-id="${customerId}"]`);
                    if (row) {
                        row.remove();
                        updateEmptyState();
                    }

                    // Show success message
                    showMessage('Customer deleted successfully!', 'success');
                } else {
                    showMessage(data.error || data.message || t('projectcheck', 'Failed to delete customer'), 'error');
                }
            })
            .catch(error => {
                console.error('Error deleting customer:', error);
                showMessage('An error occurred while deleting the customer', 'error');
            });
    }

    /**
     * Show message
     */
    function showMessage(message, type) {
        // Remove existing messages
        const existingMessages = document.querySelectorAll('.notice');
        existingMessages.forEach(msg => msg.remove());

        // Create new message
        const messageDiv = document.createElement('div');
        messageDiv.className = `notice notice-${type}`;
        messageDiv.innerHTML = `
			<i class="icon icon-${type === 'success' ? 'checkmark' : 'error'}"></i>
			<span>${message}</span>
		`;

        // Insert after header
        const header = document.querySelector('.header-content');
        if (header && header.parentNode) {
            header.parentNode.insertBefore(messageDiv, header.nextSibling);
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
