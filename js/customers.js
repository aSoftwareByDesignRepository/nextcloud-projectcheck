/**
 * Customers Management JavaScript for the projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function () {
    'use strict';

    // Global variables
    let searchTimeout = null;

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

        // Search functionality
        if (elements.searchInput) {
            elements.searchInput.addEventListener('input', handleSearch);
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
     * Handle search input
     */
    function handleSearch() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            applyFilters();
        }, 300);
    }

    /**
     * Apply all filters
     */
    function applyFilters() {
        const searchTerm = elements.searchInput ? elements.searchInput.value.toLowerCase() : '';

        const rows = elements.customersTbody ? elements.customersTbody.querySelectorAll('tr') : [];

        rows.forEach(row => {
            let showRow = true;

            // Search filter
            if (searchTerm) {
                const name = row.querySelector('td:nth-child(1)')?.textContent.toLowerCase() || '';
                const email = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '';
                const phone = row.querySelector('td:nth-child(3)')?.textContent.toLowerCase() || '';
                const contactPerson = row.querySelector('td:nth-child(4)')?.textContent.toLowerCase() || '';

                if (!name.includes(searchTerm) &&
                    !email.includes(searchTerm) &&
                    !phone.includes(searchTerm) &&
                    !contactPerson.includes(searchTerm)) {
                    showRow = false;
                }
            }

            // Show/hide row
            row.style.display = showRow ? '' : 'none';
        });

        updateEmptyState();
    }

    /**
     * Clear all filters
     */
    function clearFilters() {
        if (elements.searchInput) {
            elements.searchInput.value = '';
        }

        // Show all rows
        const rows = elements.customersTbody ? elements.customersTbody.querySelectorAll('tr') : [];
        rows.forEach(row => {
            row.style.display = '';
        });

        updateEmptyState();
    }

    /**
     * Update empty state visibility
     */
    function updateEmptyState() {
        const visibleRows = elements.customersTbody ?
            Array.from(elements.customersTbody.querySelectorAll('tr')).filter(row =>
                row.style.display !== 'none'
            ) : [];

        const emptyState = document.querySelector('.emptycontent');
        if (emptyState) {
            if (visibleRows.length === 0) {
                emptyState.style.display = 'block';
                emptyState.querySelector('h2').textContent = 'No customers match your filters';
                emptyState.querySelector('p').textContent = 'Try adjusting your search criteria or clear the filters.';
            } else {
                emptyState.style.display = 'none';
            }
        }
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
