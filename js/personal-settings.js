/**
 * Personal settings JavaScript for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function () {
	'use strict';

	OCA.ProjectControl = OCA.ProjectControl || {};

	OCA.ProjectControl.PersonalSettings = {
		init: function () {
			this.bindEvents();
			this.initializeForm();
		},

		bindEvents: function () {
			// Form submission
			$('#personal-settings-form').on('submit', this.handleFormSubmit.bind(this));

			// Real-time validation
			$('#default-hourly-rate').on('input', this.validateHourlyRate.bind(this));
			$('#budget-warning-threshold').on('input', this.validateThreshold.bind(this));
			$('#budget-critical-threshold').on('input', this.validateThreshold.bind(this));
		},

		initializeForm: function () {
			// Set up any initial form state
			this.updateThresholdValidation();
		},

		handleFormSubmit: function (e) {
			e.preventDefault();

			if (this.validateForm()) {
				this.submitForm();
			}
		},

		validateForm: function () {
			let isValid = true;

			// Clear previous errors
			$('.error-message').remove();
			$('.form-group').removeClass('has-error');

			// Validate hourly rate
			if (!this.validateHourlyRate()) {
				isValid = false;
			}

			// Validate thresholds
			if (!this.validateThreshold()) {
				isValid = false;
			}

			return isValid;
		},

		validateHourlyRate: function () {
			const rate = parseFloat($('#default-hourly-rate').val());
			const field = $('#default-hourly-rate');

			if (isNaN(rate) || rate < 0) {
				this.showError(field, 'Please enter a valid hourly rate');
				return false;
			}

			this.clearError(field);
			return true;
		},

		validateThreshold: function () {
			const warning = parseInt($('#budget-warning-threshold').val());
			const critical = parseInt($('#budget-critical-threshold').val());
			const warningField = $('#budget-warning-threshold');
			const criticalField = $('#budget-critical-threshold');

			if (isNaN(warning) || warning < 0 || warning > 100) {
				this.showError(warningField, 'Warning threshold must be between 0 and 100');
				return false;
			}

			if (isNaN(critical) || critical < 0 || critical > 100) {
				this.showError(criticalField, 'Critical threshold must be between 0 and 100');
				return false;
			}

			if (warning >= critical) {
				this.showError(warningField, 'Warning threshold must be less than critical threshold');
				this.showError(criticalField, 'Critical threshold must be greater than warning threshold');
				return false;
			}

			this.clearError(warningField);
			this.clearError(criticalField);
			return true;
		},

		updateThresholdValidation: function () {
			// Trigger validation when thresholds change
			$('#budget-warning-threshold, #budget-critical-threshold').trigger('input');
		},

		showError: function (field, message) {
			field.closest('.form-group').addClass('has-error');
			field.after('<div class="error-message">' + message + '</div>');
		},

		clearError: function (field) {
			field.closest('.form-group').removeClass('has-error');
			field.siblings('.error-message').remove();
		},

		submitForm: function () {
			const formData = new FormData($('#personal-settings-form')[0]);

			$.ajax({
				url: $('#personal-settings-form').attr('action'),
				method: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function (response) {
					if (response.success) {
						OC.Notification.showTemporary(t('projectcheck', 'Settings saved successfully'));
					} else {
						OC.Notification.showTemporary(t('projectcheck', 'Error saving settings'));
					}
				},
				error: function () {
					OC.Notification.showTemporary(t('projectcheck', 'Error saving settings'));
				}
			});
		}
	};

	$(document).ready(function () {
		OCA.ProjectControl.PersonalSettings.init();
	});

})();
