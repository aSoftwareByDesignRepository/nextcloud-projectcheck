/**
 * Settings JavaScript for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function () {
	'use strict';

	// Initialize settings when DOM is ready
	document.addEventListener('DOMContentLoaded', function () {
		initializeSettings();
	});

	/**
	 * Initialize settings functionality
	 */
	function initializeSettings() {
		// Add form submission handler
		addFormHandlers();

		// Add validation
		addValidation();

		// Add reset functionality
		addResetHandler();

		// Add real-time validation
		addRealTimeValidation();
	}

	/**
	 * Add form submission handlers
	 */
	function addFormHandlers() {
		const form = document.getElementById('settings-form');
		const messageDiv = document.getElementById('settings-message');

		if (form) {
			form.addEventListener('submit', function (e) {
				e.preventDefault();

				// Show loading state
				showLoadingState(true);

				// Collect form data
				const formData = collectFormData();

				// Validate form data
				if (!validateFormData(formData)) {
					showLoadingState(false);
					return;
				}

				// Submit settings
				submitSettings(formData);
			});
		}
	}

	/**
	 * Collect form data
	 */
	function collectFormData() {
		const form = document.getElementById('settings-form');
		const formData = new FormData(form);
		const data = {};

		// Convert FormData to object
		for (let [key, value] of formData.entries()) {
			data[key] = value;
		}

		// Handle checkboxes
		const checkboxes = form.querySelectorAll('input[type="checkbox"]');
		checkboxes.forEach(function (checkbox) {
			data[checkbox.name] = checkbox.checked ? 'true' : 'false';
		});

		return data;
	}

	/**
	 * Validate form data
	 */
	function validateFormData(data) {
		const errors = [];

		// Validate hourly rate
		if (data.defaultHourlyRate && parseFloat(data.defaultHourlyRate) <= 0) {
			errors.push('Default hourly rate must be greater than 0');
		}

		// Validate budget thresholds
		if (data.budgetWarningThreshold) {
			const warning = parseInt(data.budgetWarningThreshold);
			if (warning < 0 || warning > 100) {
				errors.push('Budget warning threshold must be between 0 and 100');
			}
		}

		if (data.budgetCriticalThreshold) {
			const critical = parseInt(data.budgetCriticalThreshold);
			if (critical < 0 || critical > 100) {
				errors.push('Budget critical threshold must be between 0 and 100');
			}
		}

		// Validate warning threshold is less than critical threshold
		if (data.budgetWarningThreshold && data.budgetCriticalThreshold) {
			const warning = parseInt(data.budgetWarningThreshold);
			const critical = parseInt(data.budgetCriticalThreshold);
			if (warning >= critical) {
				errors.push('Warning threshold must be less than critical threshold');
			}
		}

		// Validate items per page
		if (data.itemsPerPage) {
			const items = parseInt(data.itemsPerPage);
			if (items < 5 || items > 100) {
				errors.push('Items per page must be between 5 and 100');
			}
		}

		// Show errors if any
		if (errors.length > 0) {
			showMessage(errors.join('<br>'), 'error');
			return false;
		}

		return true;
	}

	/**
	 * Submit settings to server
	 */
	function submitSettings(data) {
		fetch(OC.generateUrl('/apps/projectcheck/settings'), {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'requesttoken': OC.requestToken
			},
			body: JSON.stringify(data)
		})
			.then(function (response) {
				if (!response.ok) {
					throw new Error('Network response was not ok');
				}
				return response.json();
			})
			.then(function (result) {
				showLoadingState(false);

				if (result.success) {
					showMessage(result.message, 'success');
					updateFormWithNewValues(result.settings);
				} else {
					showMessage(result.error || 'Failed to save settings', 'error');
				}
			})
			.catch(function (error) {
				showLoadingState(false);
				showMessage('Failed to save settings: ' + error.message, 'error');
			});
	}

	/**
	 * Add validation to form fields
	 */
	function addValidation() {
		const form = document.getElementById('settings-form');

		if (!form) return;

		// Add validation to numeric fields
		const numericFields = form.querySelectorAll('input[type="number"]');
		numericFields.forEach(function (field) {
			field.addEventListener('blur', function () {
				validateField(this);
			});
		});

		// Add validation to text fields
		const textFields = form.querySelectorAll('input[type="text"]');
		textFields.forEach(function (field) {
			field.addEventListener('blur', function () {
				validateField(this);
			});
		});
	}

	/**
	 * Add real-time validation
	 */
	function addRealTimeValidation() {
		const form = document.getElementById('settings-form');

		if (!form) return;

		// Real-time validation for budget thresholds
		const warningThreshold = form.querySelector('#budgetWarningThreshold');
		const criticalThreshold = form.querySelector('#budgetCriticalThreshold');

		if (warningThreshold && criticalThreshold) {
			function validateThresholds() {
				const warning = parseInt(warningThreshold.value) || 0;
				const critical = parseInt(criticalThreshold.value) || 0;

				if (warning >= critical && critical > 0) {
					criticalThreshold.setCustomValidity('Critical threshold must be greater than warning threshold');
				} else {
					criticalThreshold.setCustomValidity('');
				}
			}

			warningThreshold.addEventListener('input', validateThresholds);
			criticalThreshold.addEventListener('input', validateThresholds);
		}
	}

	/**
	 * Validate individual field
	 */
	function validateField(field) {
		const formGroup = field.closest('.form-group');
		const errorMessage = formGroup.querySelector('.error-message');

		// Remove existing error state
		formGroup.classList.remove('error');
		if (errorMessage) {
			errorMessage.remove();
		}

		// Validate based on field type and attributes
		let isValid = true;
		let message = '';

		if (field.type === 'number') {
			const value = parseFloat(field.value);
			const min = parseFloat(field.min);
			const max = parseFloat(field.max);

			if (field.value && !isNaN(value)) {
				if (min !== undefined && value < min) {
					isValid = false;
					message = `Value must be at least ${min}`;
				} else if (max !== undefined && value > max) {
					isValid = false;
					message = `Value must be at most ${max}`;
				}
			}
		} else if (field.type === 'text') {
			const pattern = field.pattern;
			if (pattern && field.value) {
				const regex = new RegExp(pattern);
				if (!regex.test(field.value)) {
					isValid = false;
					message = field.title || 'Invalid format';
				}
			}
		}

		// Show error if invalid
		if (!isValid) {
			formGroup.classList.add('error');
			const errorDiv = document.createElement('div');
			errorDiv.className = 'error-message';
			errorDiv.textContent = message;
			formGroup.appendChild(errorDiv);
		}
	}

	/**
	 * Add reset handler
	 */
	function addResetHandler() {
		const resetButton = document.getElementById('reset-settings');

		if (resetButton) {
			resetButton.addEventListener('click', function () {
				if (confirm('Are you sure you want to reset all settings to their default values?')) {
					resetSettings();
				}
			});
		}
	}

	/**
	 * Reset settings to defaults
	 */
	function resetSettings() {
		showLoadingState(true);

		const defaultSettings = {
			defaultHourlyRate: '50.00',
			budgetWarningThreshold: '80',
			budgetCriticalThreshold: '90',
			emailNotifications: 'true',
			budgetAlerts: 'true',
			projectUpdates: 'true',
			defaultProjectStatus: 'Active',
			defaultProjectPriority: 'Medium',
			itemsPerPage: '20',
			showCompletedProjects: 'true',
			autoCalculateHours: 'true',
			dateFormat: 'Y-m-d',
			timeFormat: 'H:i',
			currency: 'EUR',
			language: 'en'
		};

		// Update form with default values
		updateFormWithNewValues(defaultSettings);

		// Submit default settings
		submitSettings(defaultSettings);
	}

	/**
	 * Update form with new values
	 */
	function updateFormWithNewValues(settings) {
		const form = document.getElementById('settings-form');

		if (!form) return;

		Object.keys(settings).forEach(function (key) {
			const field = form.querySelector(`[name="${key}"]`);
			if (field) {
				if (field.type === 'checkbox') {
					field.checked = settings[key] === 'true';
				} else {
					field.value = settings[key];
				}
			}
		});
	}

	/**
	 * Show loading state
	 */
	function showLoadingState(loading) {
		const form = document.getElementById('settings-form');
		const submitButton = form.querySelector('button[type="submit"]');

		if (loading) {
			form.classList.add('loading');
			submitButton.disabled = true;
			submitButton.textContent = 'Saving...';
		} else {
			form.classList.remove('loading');
			submitButton.disabled = false;
			submitButton.textContent = 'Save Settings';
		}
	}

	/**
	 * Show message
	 */
	function showMessage(message, type = 'info') {
		const messageDiv = document.getElementById('settings-message');

		if (messageDiv) {
			messageDiv.innerHTML = message;
			messageDiv.className = `settings-message ${type}`;
			messageDiv.style.display = 'block';

			// Auto-hide success messages after 3 seconds
			if (type === 'success') {
				setTimeout(function () {
					messageDiv.style.display = 'none';
				}, 3000);
			}
		}
	}

	/**
	 * Format currency value
	 */
	function formatCurrency(value) {
		return parseFloat(value).toFixed(2);
	}

	/**
	 * Format percentage value
	 */
	function formatPercentage(value) {
		return parseInt(value);
	}

	// Export functions for global access if needed
	window.ProjectControlSettings = {
		submitSettings: submitSettings,
		resetSettings: resetSettings,
		showMessage: showMessage
	};

})();
