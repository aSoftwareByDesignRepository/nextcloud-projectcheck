/**
 * Time entry detail JavaScript for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        initializeTimeEntryDetail();
    });

    function initializeTimeEntryDetail() {
        addEventListeners();
        addAccessibilityFeatures();
    }

    function addEventListeners() {
        const deleteButton = document.querySelector('.delete-time-entry');
        if (deleteButton) {
            deleteButton.addEventListener('click', function () {
                handleDeleteTimeEntry(this);
            });
        }
        addKeyboardNavigation();
    }

    function handleDeleteTimeEntry(button) {
        const timeEntryId = button.getAttribute('data-id');
        const deleteUrl = button.getAttribute('data-delete-url');
        const confirmMessage = button.getAttribute('data-confirm')
            || t('projectcheck', 'Are you sure you want to delete this time entry? This action cannot be undone.');

        if (!timeEntryId || !deleteUrl) {
            return;
        }

        if (typeof window.projectcheckDeletionModal === 'undefined') {
            showError(t('projectcheck', 'Could not open the confirmation dialog. Reload the page and try again.'));
            return;
        }

        window.projectcheckDeletionModal.show({
            entityType: 'time_entry',
            entityId: timeEntryId,
            entityName: t('projectcheck', 'Time entry'),
            deleteUrl: deleteUrl,
            simpleConfirm: true,
            confirmMessage: confirmMessage,
            onSuccess: function () {
                const indexUrl = button.getAttribute('data-index-url');
                if (indexUrl) {
                    window.location.href = indexUrl;
                    return;
                }
                if (typeof OC !== 'undefined' && typeof OC.generateUrl === 'function') {
                    window.location.href = OC.generateUrl('/apps/projectcheck/time-entries');
                    return;
                }
                window.location.reload();
            },
            onCancel: function () {}
        });
    }

    function addAccessibilityFeatures() {
        const deleteButton = document.querySelector('.delete-time-entry');
        if (deleteButton && !deleteButton.getAttribute('aria-label')) {
            deleteButton.setAttribute('aria-label', t('projectcheck', 'Delete time entry'));
        }

        const actionButtons = document.querySelectorAll('.actions-grid .button');
        actionButtons.forEach(function (button) {
            button.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    button.click();
                }
            });
        });
    }

    function addKeyboardNavigation() {
        document.addEventListener('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                const editButton = document.querySelector('a[href*="/edit"]');
                if (editButton) {
                    editButton.click();
                }
            }

            if ((e.ctrlKey || e.metaKey) && e.key === 'Backspace') {
                e.preventDefault();
                const backButton = document.querySelector('a[href*="/time-entries"]');
                if (backButton) {
                    backButton.click();
                }
            }

            if (e.key === 'Escape') {
                const backButton = document.querySelector('a[href*="/time-entries"]');
                if (backButton && !document.querySelector('.projectcheck-deletion-modal[style*="flex"]')) {
                    backButton.click();
                }
            }
        });
    }

    function showError(message) {
        if (typeof OC !== 'undefined' && OC.Notification) {
            OC.Notification.showTemporary(message, { type: 'error' });
            return;
        }
        const live = document.getElementById('pc-alert-region');
        if (live) {
            live.textContent = message;
        }
    }
})();
