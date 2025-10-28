/**
 * Deletion Modal JavaScript
 * CSP-compliant modal component for deletion confirmations with dependency analysis
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function () {
    'use strict';

    let currentModal = null;
    let currentEntity = null;
    let currentDependencies = null;

    /**
     * Show deletion modal for an entity
     * @param {Object} options - Configuration options
     * @param {string} options.entityType - Type of entity (project, customer, time_entry, member)
     * @param {string|number} options.entityId - ID of the entity
     * @param {string} options.entityName - Display name of the entity
     * @param {string} options.deleteUrl - URL to call for deletion
     * @param {Function} options.onSuccess - Callback for successful deletion
     * @param {Function} options.onCancel - Callback for cancellation
     */
    function showDeletionModal(options) {
        if (currentModal) {
            closeDeletionModal();
        }

        currentEntity = {
            type: options.entityType,
            id: options.entityId,
            name: options.entityName,
            deleteUrl: options.deleteUrl
        };

        // Create modal HTML
        const modalHtml = createModalHTML();
        document.body.insertAdjacentHTML('beforeend', modalHtml);

        currentModal = document.getElementById('projectcheck-deletion-modal');
        if (!currentModal) {
            console.error('Failed to create deletion modal');
            return;
        }

        // Add event listeners
        addModalEventListeners(options);

        // Show modal
        currentModal.style.display = 'flex';
        document.body.style.overflow = 'hidden';

        // Focus management
        const firstFocusable = currentModal.querySelector('.projectcheck-deletion-modal__close');
        if (firstFocusable) {
            firstFocusable.focus();
        }

        // Load dependencies
        loadDependencies();
    }

    /**
     * Create modal HTML structure
     */
    function createModalHTML() {
        return `
            <div id="projectcheck-deletion-modal" class="projectcheck-deletion-modal" style="display: none;">
                <div class="projectcheck-deletion-modal__backdrop"></div>
                <div class="projectcheck-deletion-modal__container" role="dialog" aria-labelledby="deletion-modal-title" aria-modal="true">
                    <div class="projectcheck-deletion-modal__header">
                        <h2 id="deletion-modal-title" class="projectcheck-deletion-modal__title">Confirm Deletion</h2>
                        <button class="projectcheck-deletion-modal__close" aria-label="Close modal" type="button">
                            <svg width="20" height="20" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M15.898,4.045c-0.271-0.272-0.713-0.272-0.986,0l-4.71,4.711L5.493,4.045c-0.272-0.272-0.714-0.272-0.986,0s-0.272,0.714,0,0.986l4.709,4.711l-4.71,4.711c-0.272,0.271-0.272,0.713,0,0.986c0.136,0.136,0.314,0.203,0.492,0.203c0.179,0,0.357-0.067,0.493-0.203l4.711-4.711l4.71,4.711c0.136,0.136,0.314,0.203,0.494,0.203c0.18,0,0.357-0.067,0.493-0.203c0.272-0.273,0.272-0.715,0-0.986L10.187,9.742l4.711-4.711C16.17,4.759,16.17,4.317,15.898,4.045z"/>
                            </svg>
                        </button>
                    </div>
                    <div class="projectcheck-deletion-modal__body">
                        <div class="projectcheck-deletion-modal__loading">
                            <div class="projectcheck-deletion-modal__spinner"></div>
                            <span>Analyzing dependencies...</span>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Add event listeners to modal
     */
    function addModalEventListeners(options) {
        if (!currentModal) return;

        // Close button
        const closeBtn = currentModal.querySelector('.projectcheck-deletion-modal__close');
        if (closeBtn) {
            closeBtn.addEventListener('click', () => {
                closeDeletionModal();
                if (options.onCancel) options.onCancel();
            });
        }

        // Backdrop click
        const backdrop = currentModal.querySelector('.projectcheck-deletion-modal__backdrop');
        if (backdrop) {
            backdrop.addEventListener('click', () => {
                closeDeletionModal();
                if (options.onCancel) options.onCancel();
            });
        }

        // Escape key
        document.addEventListener('keydown', handleEscapeKey);

        // Focus trap
        setupFocusTrap();
    }

    /**
     * Handle escape key press
     */
    function handleEscapeKey(event) {
        if (event.key === 'Escape' && currentModal) {
            closeDeletionModal();
        }
    }

    /**
     * Setup focus trap for accessibility
     */
    function setupFocusTrap() {
        if (!currentModal) return;

        const focusableElements = currentModal.querySelectorAll(
            'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
        );
        const firstElement = focusableElements[0];
        const lastElement = focusableElements[focusableElements.length - 1];

        currentModal.addEventListener('keydown', (event) => {
            if (event.key === 'Tab') {
                if (event.shiftKey) {
                    if (document.activeElement === firstElement) {
                        event.preventDefault();
                        lastElement.focus();
                    }
                } else {
                    if (document.activeElement === lastElement) {
                        event.preventDefault();
                        firstElement.focus();
                    }
                }
            }
        });
    }

    /**
     * Load dependency information
     */
    function loadDependencies() {
        if (!currentEntity) return;

        const impactUrl = getImpactUrl(currentEntity.type, currentEntity.id);

        fetch(impactUrl, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'requesttoken': getRequestToken()
            }
        })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    currentDependencies = data.impact;
                    displayDependencyInfo(data.impact);
                } else {
                    displayError(data.error || 'Failed to load dependency information');
                }
            })
            .catch(error => {
                console.error('Error loading dependencies:', error);
                displayError('Failed to load dependency information');
            });
    }

    /**
     * Get impact URL based on entity type
     */
    function getImpactUrl(entityType, entityId) {
        switch (entityType) {
            case 'project':
                return OC.generateUrl(`/apps/projectcheck/api/projects/${entityId}/deletion-impact`);
            case 'customer':
                return OC.generateUrl(`/apps/projectcheck/customers/${entityId}/deletion-impact`);
            case 'time_entry':
                return OC.generateUrl(`/apps/projectcheck/api/time-entries/${entityId}/deletion-impact`);
            case 'member':
                return OC.generateUrl(`/apps/projectcheck/api/project-members/${entityId}/deletion-impact`);
            default:
                throw new Error('Unknown entity type: ' + entityType);
        }
    }

    /**
     * Display dependency information
     */
    function displayDependencyInfo(impact) {
        if (!currentModal || !currentEntity) return;

        const body = currentModal.querySelector('.projectcheck-deletion-modal__body');
        if (!body) return;

        // Clear loading state
        body.innerHTML = '';

        // Create content based on entity type
        let content = createDependencyContent(impact);
        body.innerHTML = content;

        // Add event listeners for new buttons
        addActionEventListeners();
    }

    /**
     * Create dependency content based on entity type and impact
     */
    function createDependencyContent(impact) {
        const entityName = currentEntity.name || 'this item';
        let content = `
            <div class="projectcheck-deletion-modal__warning">
                <h3 class="projectcheck-deletion-modal__warning-title">Warning</h3>
                <p class="projectcheck-deletion-modal__warning-message">
                    Are you sure you want to delete <strong>${entityName}</strong>? This action cannot be undone.
                </p>
            </div>
        `;

        // Add impact information
        if (hasDependencies(impact)) {
            content += `
                <div class="projectcheck-deletion-modal__impact">
                    <h3 class="projectcheck-deletion-modal__impact-title">Impact Analysis</h3>
                    <div class="projectcheck-deletion-modal__impact-list">
                        ${createImpactList(impact)}
                    </div>
                    <p class="projectcheck-deletion-modal__impact-summary">
                        Total items affected: <span class="projectcheck-deletion-modal__impact-total">${getTotalImpact(impact)}</span>
                    </p>
                </div>
            `;
        }

        // Add strategy selection for customers with dependencies
        if (currentEntity.type === 'customer' && hasDependencies(impact)) {
            content += createStrategySelection();
        }

        // Add action buttons
        content += `
            <div class="projectcheck-deletion-modal__actions">
                <button type="button" class="projectcheck-deletion-modal__btn projectcheck-deletion-modal__btn--cancel">
                    Cancel
                </button>
                <button type="button" class="projectcheck-deletion-modal__btn projectcheck-deletion-modal__btn--delete">
                    Delete
                </button>
            </div>
        `;

        return content;
    }

    /**
     * Check if there are dependencies
     */
    function hasDependencies(impact) {
        if (!impact) return false;

        switch (currentEntity.type) {
            case 'project':
                return (impact.time_entries > 0) || (impact.project_members > 0);
            case 'customer':
                return (impact.projects > 0) || (impact.time_entries > 0) || (impact.project_members > 0);
            case 'time_entry':
                return false; // Time entries have no dependencies
            case 'member':
                return impact.time_entries > 0;
            default:
                return false;
        }
    }

    /**
     * Create impact list HTML
     */
    function createImpactList(impact) {
        let list = '';

        switch (currentEntity.type) {
            case 'project':
                if (impact.time_entries > 0) {
                    list += `<li>${impact.time_entries} time entries will be deleted</li>`;
                }
                if (impact.project_members > 0) {
                    list += `<li>${impact.project_members} team members will be removed</li>`;
                }
                break;
            case 'customer':
                if (impact.projects > 0) {
                    list += `<li>${impact.projects} projects are associated</li>`;
                }
                if (impact.time_entries > 0) {
                    list += `<li>${impact.time_entries} time entries across all projects</li>`;
                }
                if (impact.project_members > 0) {
                    list += `<li>${impact.project_members} team members across all projects</li>`;
                }
                break;
            case 'member':
                if (impact.time_entries > 0) {
                    list += `<li>${impact.time_entries} time entries will remain (unchanged)</li>`;
                }
                break;
        }

        return list ? `<ul>${list}</ul>` : '<p>No dependencies found.</p>';
    }

    /**
     * Get total impact count
     */
    function getTotalImpact(impact) {
        if (!impact) return 0;

        switch (currentEntity.type) {
            case 'project':
                return (impact.time_entries || 0) + (impact.project_members || 0);
            case 'customer':
                return (impact.projects || 0) + (impact.time_entries || 0) + (impact.project_members || 0);
            case 'member':
                return 0; // Member removal doesn't delete other items
            default:
                return 0;
        }
    }

    /**
     * Create strategy selection for customer deletion
     */
    function createStrategySelection() {
        return `
            <div class="projectcheck-deletion-modal__strategy">
                <h3 class="projectcheck-deletion-modal__strategy-title">Deletion Strategy</h3>
                <div class="projectcheck-deletion-modal__strategy-options">
                    <label class="projectcheck-deletion-modal__strategy-option">
                        <input type="radio" name="deletion-strategy" value="restrict" checked>
                        <span class="projectcheck-deletion-modal__strategy-label">
                            <strong>Restrict</strong> - Only delete if no projects exist
                        </span>
                    </label>
                    <label class="projectcheck-deletion-modal__strategy-option">
                        <input type="radio" name="deletion-strategy" value="cascade">
                        <span class="projectcheck-deletion-modal__strategy-label">
                            <strong>Cascade</strong> - Delete customer and all associated projects
                        </span>
                    </label>
                    <label class="projectcheck-deletion-modal__strategy-option">
                        <input type="radio" name="deletion-strategy" value="reassign">
                        <span class="projectcheck-deletion-modal__strategy-label">
                            <strong>Reassign</strong> - Move projects to another customer
                        </span>
                    </label>
                </div>
                <div class="projectcheck-deletion-modal__reassign-options" style="display: none;">
                    <label for="reassign-customer">Reassign to customer:</label>
                    <select id="reassign-customer" name="reassign-customer-id">
                        <option value="">Select customer...</option>
                    </select>
                </div>
            </div>
        `;
    }

    /**
     * Add event listeners for action buttons
     */
    function addActionEventListeners() {
        if (!currentModal) return;

        // Cancel button
        const cancelBtn = currentModal.querySelector('.projectcheck-deletion-modal__btn--cancel');
        if (cancelBtn) {
            cancelBtn.addEventListener('click', closeDeletionModal);
        }

        // Delete button
        const deleteBtn = currentModal.querySelector('.projectcheck-deletion-modal__btn--delete');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', performDeletion);
        }

        // Strategy selection for customers
        if (currentEntity.type === 'customer') {
            setupStrategySelection();
        }
    }

    /**
     * Setup strategy selection for customer deletion
     */
    function setupStrategySelection() {
        const strategyOptions = currentModal.querySelectorAll('input[name="deletion-strategy"]');
        const reassignOptions = currentModal.querySelector('.projectcheck-deletion-modal__reassign-options');

        strategyOptions.forEach(option => {
            option.addEventListener('change', () => {
                if (reassignOptions) {
                    reassignOptions.style.display = option.value === 'reassign' ? 'block' : 'none';
                }
            });
        });

        // Load customer options for reassignment
        loadCustomerOptions();
    }

    /**
     * Load customer options for reassignment
     */
    function loadCustomerOptions() {
        const select = currentModal.querySelector('#reassign-customer');
        if (!select) return;

        // This would typically load from an API endpoint
        // For now, we'll use a placeholder
        select.innerHTML = '<option value="">Select customer...</option>';
    }

    /**
     * Perform the deletion
     */
    function performDeletion() {
        if (!currentEntity || !currentDependencies) return;

        const deleteBtn = currentModal.querySelector('.projectcheck-deletion-modal__btn--delete');
        if (deleteBtn) {
            deleteBtn.disabled = true;
            deleteBtn.textContent = 'Deleting...';
        }

        // Customer deletions use POST with form data, others use DELETE
        const isCustomer = currentEntity.type === 'customer';
        const requestToken = getRequestToken();
        let deleteUrl = currentEntity.deleteUrl;
        let fetchOptions = {
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'requesttoken': requestToken
            }
        };

        if (isCustomer) {
            // Customers use POST method with form data
            const formData = new FormData();
            const strategy = currentModal.querySelector('input[name="deletion-strategy"]:checked');
            if (strategy) {
                formData.append('strategy', strategy.value);

                if (strategy.value === 'reassign') {
                    const reassignCustomer = currentModal.querySelector('#reassign-customer');
                    if (reassignCustomer && reassignCustomer.value) {
                        formData.append('reassign_customer_id', reassignCustomer.value);
                    }
                }
            }
            fetchOptions.method = 'POST';
            fetchOptions.body = formData;
        } else {
            // Projects, time entries, and members use DELETE method
            // For DELETE requests, also add token as query parameter for better CSRF compatibility
            fetchOptions.method = 'DELETE';
            const separator = deleteUrl.includes('?') ? '&' : '?';
            deleteUrl = deleteUrl + separator + 'requesttoken=' + encodeURIComponent(requestToken);
        }

        fetch(deleteUrl, fetchOptions)
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showSuccessMessage(data.message || 'Item deleted successfully');
                    closeDeletionModal();

                    // Trigger success callback
                    if (window.projectcheckDeletionModal && window.projectcheckDeletionModal.onSuccess) {
                        window.projectcheckDeletionModal.onSuccess(currentEntity);
                    }
                } else {
                    showErrorMessage(data.error || 'Failed to delete item');
                    resetDeleteButton();
                }
            })
            .catch(error => {
                console.error('Deletion error:', error);
                showErrorMessage('An error occurred while deleting the item');
                resetDeleteButton();
            });
    }

    /**
     * Reset delete button state
     */
    function resetDeleteButton() {
        const deleteBtn = currentModal.querySelector('.projectcheck-deletion-modal__btn--delete');
        if (deleteBtn) {
            deleteBtn.disabled = false;
            deleteBtn.textContent = 'Delete';
        }
    }

    /**
     * Display error message
     */
    function displayError(message) {
        if (!currentModal) return;

        const body = currentModal.querySelector('.projectcheck-deletion-modal__body');
        if (body) {
            body.innerHTML = `
                <div class="projectcheck-deletion-modal__error">
                    <h3>Error</h3>
                    <p>${message}</p>
                    <div class="projectcheck-deletion-modal__actions">
                        <button type="button" class="projectcheck-deletion-modal__btn projectcheck-deletion-modal__btn--cancel">
                            Close
                        </button>
                    </div>
                </div>
            `;

            // Add event listener for close button
            const closeBtn = body.querySelector('.projectcheck-deletion-modal__btn--cancel');
            if (closeBtn) {
                closeBtn.addEventListener('click', closeDeletionModal);
            }
        }
    }

    /**
     * Show success message
     */
    function showSuccessMessage(message) {
        if (typeof OC !== 'undefined' && OC.Notification) {
            OC.Notification.showTemporary(message);
        } else {
            alert(message);
        }
    }

    /**
     * Show error message
     */
    function showErrorMessage(message) {
        if (typeof OC !== 'undefined' && OC.Notification) {
            OC.Notification.showTemporary(message, { type: 'error' });
        } else {
            alert('Error: ' + message);
        }
    }

    /**
     * Close the deletion modal
     */
    function closeDeletionModal() {
        if (!currentModal) return;

        // Remove event listeners
        document.removeEventListener('keydown', handleEscapeKey);

        // Hide modal
        currentModal.style.display = 'none';
        document.body.style.overflow = '';

        // Remove from DOM
        currentModal.remove();

        // Reset state
        currentModal = null;
        currentEntity = null;
        currentDependencies = null;
    }

    /**
     * Get request token from OC global object or fallback to input field
     */
    function getRequestToken() {
        // Use OC.requestToken if available (standard Nextcloud way)
        if (typeof OC !== 'undefined' && OC.requestToken) {
            return OC.requestToken;
        }
        // Fallback to input field
        const tokenInput = document.querySelector('input[name="requesttoken"]');
        return tokenInput ? tokenInput.value : '';
    }

    // Export the modal functions
    window.projectcheckDeletionModal = {
        show: showDeletionModal,
        close: closeDeletionModal,
        onSuccess: null,
        onCancel: null
    };

})();
