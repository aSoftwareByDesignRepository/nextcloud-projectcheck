/**
 * Employee Detail JavaScript for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

document.addEventListener('DOMContentLoaded', function () {
    // Initialize employee detail functionality
    initializeEmployeeDetail();
});

function initializeEmployeeDetail() {
    const data = window.projectControlData || {};
    const assignForm = document.getElementById('assign-project-form');
    const assignSubmit = document.getElementById('assign-project-submit');
    const assignProjectSelect = document.getElementById('assignProjectId');
    const assignError = document.getElementById('assign-project-error');

    function setAssignError(message) {
        if (!assignError) {
            return;
        }
        assignError.textContent = message || '';
        const hasError = Boolean(message);
        if (assignProjectSelect) {
            assignProjectSelect.setAttribute('aria-invalid', hasError ? 'true' : 'false');
        }
    }

    function notify(message, type) {
        if (typeof OC !== 'undefined' && OC.Notification) {
            if (type === 'error') {
                OC.Notification.showTemporary(message, { type: 'error' });
                return;
            }
            OC.Notification.showTemporary(message);
            return;
        }
        window.alert(message);
    }

    if (assignForm && data.assignProjectUrl) {
        if (assignProjectSelect) {
            assignProjectSelect.addEventListener('change', function () {
                setAssignError('');
            });
        }
        assignForm.addEventListener('submit', function (event) {
            event.preventDefault();
            const formData = new FormData(assignForm);
            const projectId = String(formData.get('project_id') || '').trim();
            setAssignError('');
            if (!projectId) {
                const error = t('projectcheck', 'Select project');
                setAssignError(error);
                return;
            }
            if (assignSubmit) {
                assignSubmit.disabled = true;
            }
            fetch(data.assignProjectUrl, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'requesttoken': data.requestToken || ''
                },
                body: formData
            })
                .then((response) => response.json())
                .then((payload) => {
                    if (payload && payload.success) {
                        window.location.reload();
                        return;
                    }
                    const error = (payload && payload.error) ? payload.error : t('projectcheck', 'Could not add team member');
                    setAssignError(error);
                })
                .catch(() => {
                    const error = t('projectcheck', 'Something went wrong. Please try again.');
                    setAssignError(error);
                })
                .finally(() => {
                    if (assignSubmit) {
                        assignSubmit.disabled = false;
                    }
                });
        });
    }

    document.querySelectorAll('.employee-unassign-project-btn').forEach((button) => {
        button.addEventListener('click', function () {
            const projectId = button.getAttribute('data-project-id');
            const projectName = button.getAttribute('data-project-name') || t('projectcheck', 'Project');
            if (!projectId || !data.unassignProjectUrlTemplate) {
                return;
            }

            const url = String(data.unassignProjectUrlTemplate)
                .replace('USER_ID', encodeURIComponent(String(data.employeeId || '')))
                .replace('PROJECT_ID', encodeURIComponent(String(projectId)));

            const confirmMessage = t('projectcheck', 'Do you want to remove this person from the selected project?') +
                ' ' + t('projectcheck', 'Project') + ': ' + projectName;

            if (typeof window.projectcheckDeletionModal !== 'undefined') {
                window.projectcheckDeletionModal.show({
                    entityType: 'member',
                    entityId: projectId,
                    entityName: projectName,
                    deleteUrl: url,
                    simpleConfirm: true,
                    confirmMessage: confirmMessage,
                    onSuccess: function () {
                        window.location.reload();
                    },
                    onCancel: function () {}
                });
                return;
            }

            const confirmed = window.confirm(confirmMessage);
            if (!confirmed) {
                return;
            }

            fetch(url, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'requesttoken': data.requestToken || ''
                }
            })
                .then((response) => response.json())
                .then((payload) => {
                    if (payload && payload.success) {
                        window.location.reload();
                        return;
                    }
                    notify((payload && payload.error) ? payload.error : t('projectcheck', 'Failed to remove team member'), 'error');
                })
                .catch(() => notify(t('projectcheck', 'Error removing team member. Please try again.'), 'error'));
        });
    });
}
