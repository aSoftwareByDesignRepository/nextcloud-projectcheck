/**
 * Time entry form JavaScript for projectcheck app
 */

(function () {
	'use strict';

	/**
	 * Initialize time entry form functionality
	 */
	function initializeTimeEntryForm() {
		addFormHandlers();
		addValidation();
		addRealTimeValidation();
		initializeCharacterCount();
		initializeProjectRateSync();
		calculateTotalCost();
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

		// Project selection handler
		const projectSelect = document.getElementById('project_id');
		if (projectSelect) {
			projectSelect.addEventListener('change', handleProjectChange);
		}

		// Description character count
		const descriptionTextarea = document.getElementById('description');
		if (descriptionTextarea) {
			descriptionTextarea.addEventListener('input', updateCharacterCount);
		}

		// Date input formatting for dd.mm.yyyy
		const dateInput = document.getElementById('date');
		if (dateInput) {
			initializeDateInput(dateInput);
		}
	}

	/**
	 * Initialize date input with European format (dd.mm.yyyy)
	 */
	function initializeDateInput(input) {
		// Auto-format as user types
		input.addEventListener('input', function() {
			let value = this.value.replace(/\D/g, ''); // Remove non-digits

			if (value.length >= 2) {
				value = value.substring(0, 2) + '.' + value.substring(2);
			}
			if (value.length >= 5) {
				value = value.substring(0, 5) + '.' + value.substring(5, 9);
			}

			this.value = value;
		});

		// Validate on blur
		input.addEventListener('blur', function() {
			if (this.value && this.value.length === 10) {
				const dateRegex = /^(\d{2})\.(\d{2})\.(\d{4})$/;
				const match = this.value.match(dateRegex);

				if (match) {
					const day = parseInt(match[1], 10);
					const month = parseInt(match[2], 10);
					const year = parseInt(match[3], 10);

					// Basic validation
					if (day < 1 || day > 31 || month < 1 || month > 12 || year < 1900 || year > 2100) {
						this.setCustomValidity(t('projectcheck', 'Please enter a valid date'));
					} else {
						// Check if date is not in the future
						const date = new Date(year, month - 1, day);
						const today = new Date();
						today.setHours(0, 0, 0, 0);
						
						if (date > today) {
							this.setCustomValidity(t('projectcheck', 'Date cannot be in the future'));
						} else {
							this.setCustomValidity('');
						}
					}
				} else {
					this.setCustomValidity(t('projectcheck', 'Please enter date in format dd.mm.yyyy'));
				}
			} else if (this.value) {
				this.setCustomValidity(t('projectcheck', 'Please enter date in format dd.mm.yyyy'));
			} else {
				this.setCustomValidity('');
			}
		});
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

		submitTimeEntryData(formData, form);
	}

	/**
	 * Collect form data
	 */
	function collectFormData(form) {
		const formData = new FormData(form);
		const data = {};

		for (let [key, value] of formData.entries()) {
			const trimmedValue = value.trim();

			// Convert numeric fields to numbers
			if (key === 'project_id' || key === 'hours' || key === 'hourly_rate') {
				data[key] = trimmedValue === '' ? null : parseFloat(trimmedValue);
			} else if (key === 'date' && trimmedValue) {
				// Convert European date format (dd.mm.yyyy) to ISO format (yyyy-mm-dd) for server
				data[key] = convertEuropeanDateToISO(trimmedValue);
			} else {
				data[key] = trimmedValue;
			}
		}

		return data;
	}

	/**
	 * Convert European date format (dd.mm.yyyy) to ISO format (yyyy-mm-dd)
	 */
	function convertEuropeanDateToISO(dateString) {
		if (/^\d{2}\.\d{2}\.\d{4}$/.test(dateString)) {
			const parts = dateString.split('.');
			const day = parts[0];
			const month = parts[1];
			const year = parts[2];
			return `${year}-${month}-${day}`;
		}
		return dateString; // Return as-is if not European format
	}

	/**
	 * Validate form data
	 */
	function validateFormData(data) {
		const errors = {};

		// Required fields
		if (!data.project_id) {
			errors.project_id = 'Project is required';
		}

		if (!data.date) {
			errors.date = 'Date is required';
		} else {
			// Validate date format - expect European format (dd.mm.yyyy)
			let date = null;
			if (/^\d{2}\.\d{2}\.\d{4}$/.test(data.date)) {
				// European format: dd.mm.yyyy
				const parts = data.date.split('.');
				const day = parseInt(parts[0], 10);
				const month = parseInt(parts[1], 10);
				const year = parseInt(parts[2], 10);
				
				// Validate ranges
				if (day < 1 || day > 31 || month < 1 || month > 12 || year < 1900 || year > 2100) {
					errors.date = t('projectcheck', 'Invalid date format (dd.mm.yyyy)');
				} else {
					date = new Date(year, month - 1, day);
					
					// Check if date is valid
					if (isNaN(date.getTime()) || date.getDate() !== day || date.getMonth() !== (month - 1) || date.getFullYear() !== year) {
						errors.date = t('projectcheck', 'Invalid date (e.g., 31.02.2024 is not valid)');
					} else {
						// Check if date is not in the future
						const today = new Date();
						today.setHours(0, 0, 0, 0);
						if (date > today) {
							errors.date = t('projectcheck', 'Date cannot be in the future');
						}
					}
				}
			} else {
				errors.date = t('projectcheck', 'Invalid date format (dd.mm.yyyy)');
			}
		}

		if (!data.hours) {
			errors.hours = 'Hours are required';
		} else {
			const hours = parseFloat(data.hours);
			if (isNaN(hours) || hours <= 0) {
				errors.hours = 'Hours must be a positive number';
			} else if (hours > 24) {
				errors.hours = 'Hours cannot exceed 24';
			}
		}

		if (!data.hourly_rate) {
			errors.hourly_rate = 'Hourly rate is required';
		} else {
			const rate = parseFloat(data.hourly_rate);
			if (isNaN(rate) || rate < 0) {
				errors.hourly_rate = 'Hourly rate must be a non-negative number';
			}
		}

		// Validate description length
		if (data.description && data.description.length > 1000) {
			errors.description = 'Description cannot exceed 1000 characters';
		}

		return errors;
	}

	/**
	 * Display validation errors
	 */
	function displayErrors(errors) {
		// Clear previous errors
		clearErrors();

		// Display new errors
		Object.keys(errors).forEach(fieldName => {
			const field = document.getElementById(fieldName);
			const errorElement = document.getElementById(fieldName + '-error');

			if (field && errorElement) {
				field.classList.add('has-error');
				errorElement.textContent = errors[fieldName];
			}
		});

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
		document.querySelectorAll('.error-message').forEach(element => {
			element.textContent = '';
		});

		document.querySelectorAll('.form-group').forEach(group => {
			group.classList.remove('has-error');
		});
	}

	/**
	 * Submit time entry data
	 */
	function submitTimeEntryData(data, form) {
		const submitButton = form.querySelector('#submit-btn');
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
					showNotification(result.message || 'Time entry saved successfully', 'success');

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
				console.error('Error submitting time entry:', error);
				showNotification('An error occurred while saving the time entry', 'error');
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
					(window.timeEntryFormData && window.timeEntryFormData.isEdit ? 'Updating...' : 'Creating...');
			}
		} else {
			form.classList.remove('loading');
			if (submitButton) {
				submitButton.disabled = false;
				submitButton.innerHTML = window.timeEntryFormData && window.timeEntryFormData.isEdit ?
					'Update Time Entry' : 'Create Time Entry';
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
				if (!value) error = 'Project is required';
				break;
			case 'date':
				if (!value) {
					error = 'Date is required';
				} else if (!/^\d{2}\.\d{2}\.\d{4}$/.test(value)) {
					error = t('projectcheck', 'Invalid date format (dd.mm.yyyy)');
				} else {
					const parts = value.split('.');
					const day = parseInt(parts[0], 10);
					const month = parseInt(parts[1], 10);
					const year = parseInt(parts[2], 10);
					const date = new Date(year, month - 1, day);
					
					if (isNaN(date.getTime()) || date.getDate() !== day || date.getMonth() !== (month - 1) || date.getFullYear() !== year) {
						error = t('projectcheck', 'Invalid date (e.g., 31.02.2024 is not valid)');
					} else {
						const today = new Date();
						today.setHours(0, 0, 0, 0);
						if (date > today) {
							error = t('projectcheck', 'Date cannot be in the future');
						}
					}
				}
				break;
			case 'hours':
				if (!value) {
					error = 'Hours are required';
				} else {
					const hours = parseFloat(value);
					if (isNaN(hours) || hours <= 0) {
						error = 'Hours must be a positive number';
					} else if (hours > 24) {
						error = 'Hours cannot exceed 24';
					}
				}
				break;
			case 'hourly_rate':
				if (!value) {
					error = 'Hourly rate is required';
				} else {
					const rate = parseFloat(value);
					if (isNaN(rate) || rate < 0) {
						error = 'Hourly rate must be a non-negative number';
					}
				}
				break;
			case 'description':
				if (value && value.length > 1000) {
					error = 'Description cannot exceed 1000 characters';
				}
				break;
		}

		if (error) {
			field.classList.add('has-error');
			errorElement.textContent = error;
		} else {
			field.classList.remove('has-error');
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

	/**
	 * Initialize project rate synchronization
	 */
	function initializeProjectRateSync() {
		const projectSelect = document.getElementById('project_id');
		if (projectSelect) {
			// Set initial rate if project is selected
			const selectedOption = projectSelect.options[projectSelect.selectedIndex];
			if (selectedOption && selectedOption.dataset.hourlyRate) {
				const rateInput = document.getElementById('hourly_rate');
				const projectHourlyRate = parseFloat(selectedOption.dataset.hourlyRate);

				if (rateInput) {
					// If project has hourly rate set and different from 0, make field readonly
					if (projectHourlyRate > 0) {
						rateInput.value = projectHourlyRate;
						rateInput.readOnly = true;
						rateInput.classList.add('readonly');
						rateInput.title = 'Hourly rate is set by the project and cannot be changed';
					} else if (!rateInput.value) {
						// Project has no hourly rate or it's 0, and field is empty
						rateInput.value = projectHourlyRate;
					}

					calculateTotalCost();
				}
			}
		}
	}

	/**
	 * Handle project selection change
	 */
	function handleProjectChange(event) {
		const select = event.currentTarget;
		const selectedOption = select.options[select.selectedIndex];
		const rateInput = document.getElementById('hourly_rate');

		if (selectedOption && selectedOption.dataset.hourlyRate && rateInput) {
			const projectHourlyRate = parseFloat(selectedOption.dataset.hourlyRate);

			// If project has hourly rate set and different from 0, make field readonly
			if (projectHourlyRate > 0) {
				rateInput.value = projectHourlyRate;
				rateInput.readOnly = true;
				rateInput.classList.add('readonly');
				rateInput.title = 'Hourly rate is set by the project and cannot be changed';
			} else {
				// Project has no hourly rate or it's 0, allow editing
				rateInput.readOnly = false;
				rateInput.classList.remove('readonly');
				rateInput.title = '';

				// Only update rate if it's empty or if user hasn't manually changed it
				if (!rateInput.value || rateInput.dataset.autoSet === 'true') {
					rateInput.value = projectHourlyRate;
					rateInput.dataset.autoSet = 'true';
				}
			}

			calculateTotalCost();
		} else {
			// No project selected or no hourly rate, allow editing
			rateInput.readOnly = false;
			rateInput.classList.remove('readonly');
			rateInput.title = '';
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
		return '€' + parseFloat(amount).toFixed(2);
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
