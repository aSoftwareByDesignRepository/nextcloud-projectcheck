/**
 * Employee Detail JavaScript for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

document.addEventListener('DOMContentLoaded', function () {
	initializeEmployeeDetail();
});

function initializeEmployeeDetail() {
	const data = window.projectControlData || {};
	const assignForm = document.getElementById('assign-project-form');
	const assignSubmit = document.getElementById('assign-project-submit');
	const assignProjectSelect = document.getElementById('assignProjectId');
	const assignError = document.getElementById('assign-project-error');
	const assignMemberHint = document.getElementById('assign-project-member-hint');

	function clearAssignMemberHint() {
		if (!assignMemberHint) {
			return;
		}
		assignMemberHint.hidden = true;
		assignMemberHint.replaceChildren();
	}

	/**
	 * @param {string} url
	 */
	function appendProjectTeamLink(container, url) {
		const link = document.createElement('a');
		link.href = url;
		link.className = 'pc-inline-action-link';
		link.textContent = t('projectcheck', 'Open project team page');
		container.appendChild(link);
	}

	function showAssignMemberHint(url) {
		if (!assignMemberHint || !url) {
			return;
		}
		assignMemberHint.replaceChildren();
		assignMemberHint.appendChild(
			document.createTextNode(
				t('projectcheck', 'This project uses per-person rates. Add this person on the project team page with their rate.') + ' ',
			),
		);
		appendProjectTeamLink(assignMemberHint, url);
		assignMemberHint.hidden = false;
	}

	/**
	 * @param {string} message
	 * @param {string} [linkUrl]
	 */
	function setAssignError(message, linkUrl) {
		if (!assignError) {
			return;
		}
		assignError.replaceChildren();
		const hasError = Boolean(message || linkUrl);
		if (message) {
			assignError.appendChild(document.createTextNode(message));
		}
		if (linkUrl) {
			if (message) {
				assignError.appendChild(document.createTextNode(' '));
			}
			appendProjectTeamLink(assignError, linkUrl);
		}
		if (assignProjectSelect) {
			assignProjectSelect.setAttribute('aria-invalid', hasError ? 'true' : 'false');
		}
	}

	function updateAssignMemberHintFromSelect() {
		clearAssignMemberHint();
		if (!assignProjectSelect) {
			return;
		}
		const opt = assignProjectSelect.selectedOptions[0];
		if (!opt || !opt.value) {
			return;
		}
		if (opt.dataset.costRateMode === 'project_member' && opt.dataset.projectUrl) {
			showAssignMemberHint(opt.dataset.projectUrl);
		}
	}

    function notify(message, type) {
        if (typeof window.ProjectCheckNotify !== 'undefined') {
            window.ProjectCheckNotify.show(message, type);
            return;
        }
        if (typeof OC !== 'undefined' && OC.Notification) {
            if (type === 'error') {
                OC.Notification.showTemporary(message, { type: 'error' });
                return;
            }
            OC.Notification.showTemporary(message);
            return;
        }
        const region = document.getElementById('pc-alert-region');
        if (region) {
            region.textContent = message;
        }
    }

	if (assignForm && data.assignProjectUrl) {
		if (assignProjectSelect) {
			assignProjectSelect.addEventListener('change', function () {
				setAssignError('');
				updateAssignMemberHintFromSelect();
			});
		}
		assignForm.addEventListener('submit', function (event) {
			event.preventDefault();
			const formData = new FormData(assignForm);
			const projectId = String(formData.get('project_id') || '').trim();
			setAssignError('');
			if (!projectId) {
				setAssignError(t('projectcheck', 'Select project'));
				return;
			}
			if (assignSubmit) {
				assignSubmit.disabled = true;
			}
			fetch(data.assignProjectUrl, {
				method: 'POST',
				headers: {
					'X-Requested-With': 'XMLHttpRequest',
					requesttoken: data.requestToken || '',
				},
				body: formData,
			})
				.then((response) => response.json())
				.then((payload) => {
					if (payload && payload.success) {
						window.location.reload();
						return;
					}
					if (payload && payload.code === 'project_member_mode' && payload.project_url) {
						setAssignError(payload.error || '', payload.project_url);
						notify(payload.error || t('projectcheck', 'This project uses per-person rates.'), 'error');
						return;
					}
					const error = (payload && payload.error)
						? payload.error
						: t('projectcheck', 'Could not add team member');
					setAssignError(error);
				})
				.catch(() => {
					setAssignError(t('projectcheck', 'Something went wrong. Please try again.'));
				})
				.finally(() => {
					if (assignSubmit) {
						assignSubmit.disabled = false;
					}
				});
		});
	}

	const rateForm = document.getElementById('add-employee-rate-form');
	const rateError = document.getElementById('employee-rate-error');
	const rateSubmit = document.getElementById('employee-rate-submit');
	if (rateForm && data.addEmployeeRateUrl && window.ProjectCheckApi) {
		rateForm.addEventListener('submit', async function (event) {
			event.preventDefault();
			if (rateError) {
				rateError.textContent = '';
			}
			const amount = document.getElementById('employeeRateAmount');
			const effective = document.getElementById('employeeRateEffective');
			const rateValue = amount ? parseFloat(String(amount.value).replace(',', '.')) : NaN;
			const effectiveValue = effective ? String(effective.value).trim() : '';
			const today = effective && effective.max ? effective.max : '';
			if (!Number.isFinite(rateValue) || rateValue <= 0) {
				const message = t('projectcheck', 'Hourly rate must be a positive number');
				if (rateError) {
					rateError.textContent = message;
				}
				if (amount) {
					amount.setAttribute('aria-invalid', 'true');
					amount.focus();
				}
				return;
			}
			if (amount) {
				amount.setAttribute('aria-invalid', 'false');
			}
			if (!effectiveValue) {
				const message = t('projectcheck', 'Effective-from date is required');
				if (rateError) {
					rateError.textContent = message;
				}
				if (effective) {
					effective.setAttribute('aria-invalid', 'true');
					effective.focus();
				}
				return;
			}
			if (today && effectiveValue > today) {
				const message = t('projectcheck', 'Effective-from date cannot be in the future.');
				if (rateError) {
					rateError.textContent = message;
				}
				if (effective) {
					effective.setAttribute('aria-invalid', 'true');
					effective.focus();
				}
				return;
			}
			if (effective) {
				effective.setAttribute('aria-invalid', 'false');
			}
			if (rateSubmit) {
				rateSubmit.disabled = true;
			}
			try {
				await window.ProjectCheckApi.post(data.addEmployeeRateUrl, {
					hourly_rate: amount ? amount.value : '',
					effective_from: effectiveValue,
				});
				window.location.reload();
			} catch (err) {
				const message = err.message || t('projectcheck', 'Could not save rate.');
				if (rateError) {
					rateError.textContent = message;
				} else {
					notify(message, 'error');
				}
			} finally {
				if (rateSubmit) {
					rateSubmit.disabled = false;
				}
			}
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
					entityType: 'employee_unassign',
					entityId: projectId,
					entityName: projectName,
					deleteUrl: url,
					simpleConfirm: true,
					confirmMessage: confirmMessage,
					onSuccess: function () {
						window.location.reload();
					},
					onCancel: function () {},
				});
				return;
			}

			notify(t('projectcheck', 'Could not open the confirmation dialog. Reload the page and try again.'), 'error');
		});
	});
}
