/**
 * Admin settings JavaScript for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function () {
	'use strict';

	OCA.ProjectControl = OCA.ProjectControl || {};

	OCA.ProjectControl.AdminSettings = {
		init: function () {
			this.bindEvents();
			this.initializeForm();
		},

		bindEvents: function () {
			// Form submission
			$('#admin-settings-form').on('submit', this.handleFormSubmit.bind(this));

			// Real-time validation
			$('#default-hourly-rate').on('input', this.validateHourlyRate.bind(this));
			$('#max-projects-per-user').on('input', this.validateMaxProjects.bind(this));
			$('#max-team-members-per-project').on('input', this.validateMaxTeamMembers.bind(this));
		},

		initializeForm: function () {
			// Set up any initial form state
			this.updateValidation();
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

			// Validate max projects
			if (!this.validateMaxProjects()) {
				isValid = false;
			}

			// Validate max team members
			if (!this.validateMaxTeamMembers()) {
				isValid = false;
			}

			return isValid;
		},

		validateHourlyRate: function () {
			const rate = parseFloat($('#default-hourly-rate').val());
			const field = $('#default-hourly-rate');

			if (isNaN(rate) || rate < 0) {
				this.showError(field, t('projectcheck', 'Please enter a valid hourly rate'));
				return false;
			}

			this.clearError(field);
			return true;
		},

		validateMaxProjects: function () {
			const maxProjects = parseInt($('#max-projects-per-user').val());
			const field = $('#max-projects-per-user');

			if (isNaN(maxProjects) || maxProjects < 1) {
				this.showError(field, t('projectcheck', 'Maximum projects must be at least 1'));
				return false;
			}

			this.clearError(field);
			return true;
		},

		validateMaxTeamMembers: function () {
			const maxTeamMembers = parseInt($('#max-team-members-per-project').val());
			const field = $('#max-team-members-per-project');

			if (isNaN(maxTeamMembers) || maxTeamMembers < 1) {
				this.showError(field, t('projectcheck', 'Maximum team members must be at least 1'));
				return false;
			}

			this.clearError(field);
			return true;
		},

		updateValidation: function () {
			// Trigger validation when fields change
			$('#default-hourly-rate, #max-projects-per-user, #max-team-members-per-project').trigger('input');
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
			const formData = new FormData($('#admin-settings-form')[0]);

			$.ajax({
				url: $('#admin-settings-form').attr('action'),
				method: 'POST',
				data: formData,
				processData: false,
				contentType: false,
				success: function (response) {
					if (response.success) {
						OC.Notification.showTemporary(t('projectcheck', 'Admin settings saved successfully'));
					} else {
						OC.Notification.showTemporary(t('projectcheck', 'Error saving admin settings'));
					}
				},
				error: function () {
					OC.Notification.showTemporary(t('projectcheck', 'Error saving admin settings'));
				}
			});
		}
	};

	$(document).ready(function () {
		OCA.ProjectControl.AdminSettings.init();
	});

})();
