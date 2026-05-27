/**
 * Time entry form JavaScript for projectcheck app
 */

(function () {
	'use strict';

	/**
	 * Normalize native date (YYYY-MM-DD) or legacy EU text to ISO for the server.
	 */
	function normalizeDateToIso(dateString) {
		if (!dateString) {
			return '';
		}
		const s = String(dateString).trim();
		if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
			return s;
		}
		if (/^\d{2}\.\d{2}\.\d{4}$/.test(s)) {
			const parts = s.split('.');
			return `${parts[2]}-${parts[1]}-${parts[0]}`;
		}
		return '';
	}

	/**
	 * Parse ISO date in local timezone (avoids UTC midnight drift).
	 */
	function parseIsoDateLocal(iso) {
		const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso);
		if (!m) {
			return null;
		}
		const year = parseInt(m[1], 10);
		const month = parseInt(m[2], 10);
		const day = parseInt(m[3], 10);
		const date = new Date(year, month - 1, day);
		if (
			isNaN(date.getTime())
			|| date.getDate() !== day
			|| date.getMonth() !== month - 1
			|| date.getFullYear() !== year
		) {
			return null;
		}
		return date;
	}

	/**
	 * Initialize time entry form functionality
	 */
	function initializeTimeEntryForm() {
		addFormHandlers();
		addValidation();
		addRealTimeValidation();
		initializeCharacterCount();
		initializeProjectRateSync();
		updateAdminOverrideBanner();
		calculateTotalCost();

		const isEdit = window.timeEntryFormData && window.timeEntryFormData.isEdit;
		const dateInput = document.getElementById('date');
		if (dateInput && !isEdit && !dateInput.value) {
			const today = new Date();
			const y = today.getFullYear();
			const mo = String(today.getMonth() + 1).padStart(2, '0');
			const d = String(today.getDate()).padStart(2, '0');
			dateInput.value = `${y}-${mo}-${d}`;
		}
	}

	/**
	 * Add validation functions
	 */
	function addValidation() {
		// This function is called but not needed for this form
		// Validation is handled in validateFormData function
	}

	/**
	 * Add form event handlers
	 */
	function addFormHandlers() {
		const form = document.getElementById('time-entry-form');
		if (form) {
			form.addEventListener('submit', handleFormSubmit);
		}

		// Hours and rate change handlers for total cost calculation
		const hoursInput = document.getElementById('hours');
		const rateInput = document.getElementById('hourly_rate');

		if (hoursInput) {
			hoursInput.addEventListener('input', calculateTotalCost);
		}

		if (rateInput) {
			rateInput.addEventListener('input', calculateTotalCost);
		}

		const projectSelect = document.getElementById('project_id');
		if (projectSelect) {
			projectSelect.addEventListener('change', () => {
				updateAdminOverrideBanner();
				resolveRateFromServer();
			});
		}
		const dateInput = document.getElementById('date');
		if (dateInput) {
			dateInput.addEventListener('change', () => resolveRateFromServer());
			dateInput.addEventListener('blur', () => resolveRateFromServer());
		}

		// Description character count
		const descriptionTextarea = document.getElementById('description');
		if (descriptionTextarea) {
			descriptionTextarea.addEventListener('input', updateCharacterCount);
		}

	}

	/**
	 * Handle form submission
	 */
	function handleFormSubmit(event) {
		event.preventDefault();

		const form = event.currentTarget;
		const submitButton = form.querySelector('#submit-btn');

		// Prevent double submission
		if (submitButton.disabled || form.classList.contains('submitting')) {
			return;
		}

		const formData = collectFormData(form);
		const errors = validateFormData(formData);

		if (Object.keys(errors).length > 0) {
			displayErrors(errors);
			return;
		}

		// Remove internal _raw field before sending to server
		delete formData._raw;
		
		submitTimeEntryData(formData, form);
	}

	/**
	 * Collect form data
	 */
	function collectFormData(form) {
		const formData = new FormData(form);
		const data = {};
		const rawData = {}; // Store raw values for validation

		for (let [key, value] of formData.entries()) {
			const trimmedValue = value.trim();
			rawData[key] = trimmedValue; // Store raw value

			// Convert numeric fields to numbers
			if (key === 'project_id' || key === 'hours' || key === 'hourly_rate') {
				data[key] = trimmedValue === '' ? null : parseFloat(trimmedValue);
			} else if (key === 'date' && trimmedValue) {
				data[key] = normalizeDateToIso(trimmedValue);
			} else {
				data[key] = trimmedValue;
			}
		}

		// Store raw data for validation
		data._raw = rawData;

		return data;
	}


	/**
	 * Validate form data
	 */
	function validateFormData(data) {
		const errors = {};

		// Required fields
		if (!data.project_id) {
			errors.project_id = t('projectcheck', 'Project is required');
		}

		const rawDate = data._raw && data._raw.date ? data._raw.date : '';
		const isoDate = normalizeDateToIso(rawDate);

		if (!rawDate) {
			errors.date = t('projectcheck', 'Date is required');
		} else if (!isoDate) {
			errors.date = t('projectcheck', 'Invalid date');
		} else {
			const date = parseIsoDateLocal(isoDate);
			if (!date) {
				errors.date = t('projectcheck', 'Invalid date (e.g., 31.02.2024 is not valid)');
			} else {
				const today = new Date();
				today.setHours(0, 0, 0, 0);
				if (date > today) {
					errors.date = t('projectcheck', 'Date cannot be in the future');
				}
			}
		}

		if (!data.hours) {
			errors.hours = t('projectcheck', 'Hours are required');
		} else {
			const hours = parseFloat(data.hours);
			if (isNaN(hours) || hours <= 0) {
				errors.hours = t('projectcheck', 'Hours must be a positive number');
			} else if (hours > 24) {
				errors.hours = t('projectcheck', 'Hours cannot exceed 24');
			}
		}

		if (!data.hourly_rate) {
			errors.hourly_rate = t('projectcheck', 'Hourly rate is required');
		} else {
			const rate = parseFloat(data.hourly_rate);
			if (isNaN(rate) || rate < 0) {
				errors.hourly_rate = t('projectcheck', 'Hourly rate must be a non-negative number');
			}
		}

		// Validate description length
		if (data.description && data.description.length > 1000) {
			errors.description = t('projectcheck', 'Description cannot exceed 1000 characters');
		}

		return errors;
	}

	/**
	 * Display validation errors
	 */
	function displayErrors(errors) {
		// Clear previous errors
		clearErrors();
		const errorSummary = document.getElementById('time-entry-form-errors');
		const messages = [];

		// Display new errors
		Object.keys(errors).forEach(fieldName => {
			const field = document.getElementById(fieldName);
			const errorElement = document.getElementById(fieldName + '-error');
			const formGroup = field ? field.closest('.form-group') : null;

			if (field && errorElement) {
				field.classList.add('has-error');
				if (formGroup) {
					formGroup.classList.add('has-error');
				}
				errorElement.textContent = errors[fieldName];
				messages.push(errors[fieldName]);
			}
		});
		if (errorSummary) {
			errorSummary.textContent = messages.join(' ');
		}

		// Show error notification
		const firstError = Object.values(errors)[0];
		if (firstError) {
			showNotification(firstError, 'error');
		}
	}

	/**
	 * Clear all error messages
	 */
	function clearErrors() {
		const errorSummary = document.getElementById('time-entry-form-errors');
		document.querySelectorAll('.error-message').forEach(element => {
			element.textContent = '';
		});
		if (errorSummary) {
			errorSummary.textContent = '';
		}

		document.querySelectorAll('.form-group').forEach(group => {
			group.classList.remove('has-error');
		});
		document.querySelectorAll('.has-error').forEach(element => {
			if (element.classList.contains('form-group')) {
				return;
			}
			element.classList.remove('has-error');
		});
	}

	/**
	 * Submit time entry data
	 */
	function submitTimeEntryData(data, form) {
		const isEdit = window.timeEntryFormData && window.timeEntryFormData.isEdit;
		const url = isEdit
			? OC.generateUrl('/apps/projectcheck/time-entries/' + window.timeEntryFormData.timeEntryId)
			: OC.generateUrl('/apps/projectcheck/time-entries');

		const method = isEdit ? 'PUT' : 'POST';

		// Mark form as submitting and show loading state
		form.classList.add('submitting');
		showLoadingState(form, true);

		fetch(url, {
			method: method,
			headers: {
				'Content-Type': 'application/json',
				'requesttoken': OC.requestToken
			},
			body: JSON.stringify(data)
		})
			.then(response => response.json())
			.then(result => {
				if (result.success) {
					showNotification(result.message || t('projectcheck', 'Time entry saved successfully'), 'success');

					// Redirect to time entries list after a short delay
					setTimeout(() => {
						window.location.href = OC.generateUrl('/apps/projectcheck/time-entries');
					}, 1500);
				} else {
					if (result.errors) {
						displayErrors(result.errors);
					} else {
						showNotification(result.error || t('projectcheck', 'Failed to save time entry'), 'error');
					}
					// Re-enable form only on error
					form.classList.remove('submitting');
					showLoadingState(form, false);
				}
			})
			.catch(error => {
				showNotification(t('projectcheck', 'An error occurred while saving the time entry'), 'error');
				// Re-enable form on error
				form.classList.remove('submitting');
				showLoadingState(form, false);
			});
	}

	/**
	 * Show/hide loading state
	 */
	function showLoadingState(form, loading) {
		const submitButton = form.querySelector('#submit-btn');

		if (loading) {
			form.classList.add('loading');
			if (submitButton) {
				submitButton.disabled = true;
				submitButton.innerHTML = '<span class="icon icon-loading-small"></span> ' +
					(window.timeEntryFormData && window.timeEntryFormData.isEdit
						? t('projectcheck', 'Updating…') : t('projectcheck', 'Creating…'));
			}
		} else {
			form.classList.remove('loading');
			if (submitButton) {
				submitButton.disabled = false;
				submitButton.innerHTML = window.timeEntryFormData && window.timeEntryFormData.isEdit
					? t('projectcheck', 'Update Time Entry')
					: t('projectcheck', 'Create Time Entry');
			}
		}
	}

	/**
	 * Add real-time validation
	 */
	function addRealTimeValidation() {
		const fields = ['project_id', 'date', 'hours', 'hourly_rate', 'description'];

		fields.forEach(fieldName => {
			const field = document.getElementById(fieldName);
			if (field) {
				field.addEventListener('blur', () => {
					validateField(fieldName, field.value);
				});

				field.addEventListener('input', () => {
					// Clear error on input
					const errorElement = document.getElementById(fieldName + '-error');
					if (errorElement && errorElement.textContent) {
						errorElement.textContent = '';
						field.classList.remove('has-error');
					}
				});
			}
		});
	}

	/**
	 * Validate individual field
	 */
	function validateField(fieldName, value) {
		const field = document.getElementById(fieldName);
		const errorElement = document.getElementById(fieldName + '-error');

		if (!field || !errorElement) return;

		let error = '';

		switch (fieldName) {
			case 'project_id':
				if (!value) { error = t('projectcheck', 'Project is required'); }
				break;
			case 'date': {
				if (!value) {
					error = t('projectcheck', 'Date is required');
					break;
				}
				const iso = normalizeDateToIso(value);
				if (!iso) {
					error = t('projectcheck', 'Invalid date');
					break;
				}
				const date = parseIsoDateLocal(iso);
				if (!date) {
					error = t('projectcheck', 'Invalid date (e.g., 31.02.2024 is not valid)');
					break;
				}
				const today = new Date();
				today.setHours(0, 0, 0, 0);
				if (date > today) {
					error = t('projectcheck', 'Date cannot be in the future');
				}
				break;
			}
			case 'hours':
				if (!value) {
					error = t('projectcheck', 'Hours are required');
				} else {
					const hours = parseFloat(value);
					if (isNaN(hours) || hours <= 0) {
						error = t('projectcheck', 'Hours must be a positive number');
					} else if (hours > 24) {
						error = t('projectcheck', 'Hours cannot exceed 24');
					}
				}
				break;
			case 'hourly_rate':
				if (!value) {
					error = t('projectcheck', 'Hourly rate is required');
				} else {
					const rate = parseFloat(value);
					if (isNaN(rate) || rate < 0) {
						error = t('projectcheck', 'Hourly rate must be a non-negative number');
					}
				}
				break;
			case 'description':
				if (value && value.length > 1000) {
					error = t('projectcheck', 'Description cannot exceed 1000 characters');
				}
				break;
		}

		if (error) {
			field.classList.add('has-error');
			const formGroup = field.closest('.form-group');
			if (formGroup) {
				formGroup.classList.add('has-error');
			}
			errorElement.textContent = error;
		} else {
			field.classList.remove('has-error');
			const formGroup = field.closest('.form-group');
			if (formGroup) {
				formGroup.classList.remove('has-error');
			}
			errorElement.textContent = '';
		}
	}

	/**
	 * Initialize character count
	 */
	function initializeCharacterCount() {
		const descriptionTextarea = document.getElementById('description');
		if (descriptionTextarea) {
			updateCharacterCount();
		}
	}

	/**
	 * Update character count
	 */
	function updateCharacterCount() {
		const descriptionTextarea = document.getElementById('description');
		const charCountElement = document.getElementById('char-count');

		if (descriptionTextarea && charCountElement) {
			const count = descriptionTextarea.value.length;
			charCountElement.textContent = count;

			const charCountContainer = charCountElement.closest('.char-count');
			if (charCountContainer) {
				charCountContainer.classList.remove('warning', 'error');

				if (count > 900) {
					charCountContainer.classList.add('error');
				} else if (count > 800) {
					charCountContainer.classList.add('warning');
				}
			}
		}
	}

	function getIsoDateFromInput() {
		const dateInput = document.getElementById('date');
		if (!dateInput || !dateInput.value) {
			return '';
		}
		return normalizeDateToIso(dateInput.value.trim());
	}

	async function resolveRateFromServer() {
		const projectSelect = document.getElementById('project_id');
		const rateInput = document.getElementById('hourly_rate');
		const hint = document.getElementById('hourly_rate-hint');
		if (!projectSelect || !rateInput || !window.ProjectCheckApi) {
			return;
		}
		const projectId = parseInt(projectSelect.value, 10);
		const isoDate = getIsoDateFromInput();
		if (!projectId || !isoDate) {
			return;
		}
		rateInput.setAttribute('aria-busy', 'true');
		try {
			const path = '/apps/projectcheck/api/projects/' + projectId + '/resolve-hourly-rate';
			const payload = await window.ProjectCheckApi.get(path, { date: isoDate });
			if (payload && payload.success && payload.data) {
				rateInput.value = String(payload.data.hourly_rate);
				rateInput.readOnly = true;
				rateInput.classList.add('readonly');
				if (hint) {
					hint.textContent = t('projectcheck', 'Rate resolved from server for this project and work date.');
				}
				calculateTotalCost();
			}
		} catch (err) {
			rateInput.value = '';
			if (hint) {
				hint.textContent = err.message || t('projectcheck', 'Could not resolve hourly rate.');
			}
		} finally {
			rateInput.removeAttribute('aria-busy');
		}
	}

	function initializeProjectRateSync() {
		resolveRateFromServer();
	}

	/**
	 * Show / hide the "logging as administrator" banner based on the selected
	 * project's data-admin-override attribute (server-authoritative). The body
	 * text is tailored to the project's cost-rate mode so admins know exactly
	 * which rate will be applied.
	 */
	function updateAdminOverrideBanner() {
		const banner = document.getElementById('pc-admin-override-banner');
		if (!banner) {
			return;
		}
		const projectSelect = document.getElementById('project_id');
		const hintEl = document.getElementById('pc-admin-override-hint');
		const hideBanner = () => {
			banner.classList.add('pc-form-callout--hidden');
			if (hintEl) {
				hintEl.textContent = '';
				hintEl.classList.add('pc-form-callout__hint--empty');
			}
		};
		if (!projectSelect) {
			hideBanner();
			return;
		}
		const opt = projectSelect.options[projectSelect.selectedIndex];
		const adminOverride = !!opt && opt.getAttribute('data-admin-override') === '1';
		if (!adminOverride) {
			hideBanner();
			return;
		}

		const mode = (opt.getAttribute('data-cost-rate-mode') || '').toLowerCase();
		if (hintEl) {
			let modeText = '';
			if (mode === 'project') {
				modeText = banner.getAttribute('data-pc-mode-project-label') || '';
			} else if (mode === 'employee') {
				modeText = banner.getAttribute('data-pc-mode-employee-label') || '';
			}
			hintEl.textContent = modeText;
			if (modeText) {
				hintEl.classList.remove('pc-form-callout__hint--empty');
			} else {
				hintEl.classList.add('pc-form-callout__hint--empty');
			}
		}
		banner.classList.remove('pc-form-callout--hidden');
		// Icon may have been skipped while the callout was hidden (catalog hydrates on show).
		const iconEl = banner.querySelector('[data-lucide]');
		if (iconEl) {
			iconEl.removeAttribute('data-lucide-hydrated');
		}
		if (window.ProjectCheckIcons && typeof window.ProjectCheckIcons.hydrate === 'function') {
			window.ProjectCheckIcons.hydrate(banner);
		}
	}

	/**
	 * Calculate total cost
	 */
	function calculateTotalCost() {
		const hoursInput = document.getElementById('hours');
		const rateInput = document.getElementById('hourly_rate');
		const totalCostInput = document.getElementById('total_cost');

		if (hoursInput && rateInput && totalCostInput) {
			const hours = parseFloat(hoursInput.value) || 0;
			const rate = parseFloat(rateInput.value) || 0;
			const total = hours * rate;

			totalCostInput.value = formatCurrency(total);
		}
	}

	/**
	 * Format currency
	 */
	function formatCurrency(amount) {
		if (window.ProjectCheckFormat) {
			return window.ProjectCheckFormat.currencyFmt(amount);
		}
		const value = Number.parseFloat(amount);
		if (!Number.isFinite(value)) {
			return '\u2014';
		}
		const code = (window.ProjectCheckConfig && typeof window.ProjectCheckConfig.currency === 'string'
			&& /^[A-Z]{3}$/i.test(window.ProjectCheckConfig.currency))
			? window.ProjectCheckConfig.currency.toUpperCase()
			: 'EUR';
		return code + ' ' + value.toFixed(2);
	}

	/**
	 * Show notification
	 */
	function showNotification(message, type = 'info') {
		OC.Notification.show(message, {
			type: type,
			timeout: 5
		});
	}

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initializeTimeEntryForm);
	} else {
		initializeTimeEntryForm();
	}

	// Export functions for global access if needed
	window.TimeEntryForm = {
		initialize: initializeTimeEntryForm,
		validateField: validateField,
		calculateTotalCost: calculateTotalCost,
		showNotification: showNotification
	};

})();

