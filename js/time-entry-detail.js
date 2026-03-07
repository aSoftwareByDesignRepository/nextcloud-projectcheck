/**
 * Time entry detail JavaScript for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function () {
    'use strict';

    // Initialize time entry detail when DOM is ready
    document.addEventListener('DOMContentLoaded', function () {
        initializeTimeEntryDetail();
    });

    /**
     * Initialize time entry detail functionality
     */
    function initializeTimeEntryDetail() {
        // Add event listeners
        addEventListeners();

        // Add accessibility features
        addAccessibilityFeatures();
    }

    /**
     * Add event listeners
     */
    function addEventListeners() {
        // Add delete button handler
        const deleteButton = document.querySelector('.delete-time-entry');
        if (deleteButton) {
            deleteButton.addEventListener('click', function () {
                handleDeleteTimeEntry(this);
            });
        }

        // Add keyboard navigation
        addKeyboardNavigation();
    }

    /**
     * Handle time entry deletion
     */
    function handleDeleteTimeEntry(button) {
        const timeEntryId = button.getAttribute('data-id');
        const confirmMessage = button.getAttribute('data-confirm');

        if (!timeEntryId) {
            console.error('Time entry ID not found');
            return;
        }

        // Show confirmation dialog
        if (confirm(confirmMessage || 'Are you sure you want to delete this time entry?')) {
            // Show loading state
            button.disabled = true;
            button.textContent = 'Deleting...';

            // Make delete request
            fetch(`/apps/projectcheck/time-entries/${timeEntryId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'requesttoken': getRequestToken()
                }
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(t('projectcheck', 'Failed to delete time entry'));
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Redirect to time entries list
                        window.location.href = '/apps/projectcheck/time-entries';
                    } else {
                        throw new Error(data.error || t('projectcheck', 'Failed to delete time entry'));
                    }
                })
                .catch(error => {
                    console.error('Error deleting time entry:', error);
                    showError(t('projectcheck', 'Failed to delete time entry') + ': ' + error.message);

                    // Reset button state
                    button.disabled = false;
                    button.textContent = 'Delete Time Entry';
                });
        }
    }

    /**
     * Add accessibility features
     */
    function addAccessibilityFeatures() {
        // Add ARIA labels and roles
        const deleteButton = document.querySelector('.delete-time-entry');
        if (deleteButton) {
            deleteButton.setAttribute('aria-label', t('projectcheck', 'Delete time entry'));
        }

        // Add focus management
        const actionButtons = document.querySelectorAll('.action-buttons .button');
        actionButtons.forEach(function (button, index) {
            button.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    button.click();
                }
            });
        });
    }

    /**
     * Add keyboard navigation
     */
    function addKeyboardNavigation() {
        // Add keyboard shortcuts
        document.addEventListener('keydown', function (e) {
            // Ctrl/Cmd + E to edit
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                const editButton = document.querySelector('a[href*="/edit"]');
                if (editButton) {
                    editButton.click();
                }
            }

            // Ctrl/Cmd + Backspace to go back
            if ((e.ctrlKey || e.metaKey) && e.key === 'Backspace') {
                e.preventDefault();
                const backButton = document.querySelector('a[href*="/time-entries"]');
                if (backButton) {
                    backButton.click();
                }
            }

            // Escape to go back
            if (e.key === 'Escape') {
                const backButton = document.querySelector('a[href*="/time-entries"]');
                if (backButton) {
                    backButton.click();
                }
            }
        });
    }

    /**
     * Show error message
     */
    function showError(message) {
        // Create error notification
        const notification = document.createElement('div');
        notification.className = 'notification notification-error';
        notification.setAttribute('role', 'alert');
        notification.innerHTML = `
			<div class="notification-content">
				<span class="notification-message">${escapeHtml(message)}</span>
				<button type="button" class="notification-close" aria-label="${t('projectcheck', 'Close notification')}">
					<span class="icon-close"></span>
				</button>
			</div>
		`;

        // Add to page
        document.body.appendChild(notification);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 5000);

        // Add close functionality
        const closeButton = notification.querySelector('.notification-close');
        if (closeButton) {
            closeButton.addEventListener('click', function () {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            });
        }
    }

    /**
     * Get request token for CSRF protection
     */
    function getRequestToken() {
        const tokenElement = document.querySelector('input[name="requesttoken"]');
        return tokenElement ? tokenElement.value : '';
    }

    /**
     * Utility function to escape HTML
     */
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

})();
