/**
 * Project form JavaScript for projectcheck app
 */

(function () {
	'use strict';
	
	function fallbackConvertEuropeanToISO(dateString) {
		if (!dateString) {
			return '';
		}
		if (/^\d{2}\.\d{2}\.\d{4}$/.test(dateString)) {
			const parts = dateString.split('.');
			return `${parts[2]}-${parts[1]}-${parts[0]}`;
		}
		return dateString;
	}

	function enableManualDateInput(element) {
		if (!element) {
			return;
		}
		element.readOnly = false;
		element.removeAttribute('readonly');
		element.setAttribute('inputmode', 'numeric');
	}

	function getDatepickerFunctions() {
		if (window.ProjectCheckDatepicker && typeof window.ProjectCheckDatepicker.initializeDatepicker === 'function') {
			return {
				initializeDatepicker: window.ProjectCheckDatepicker.initializeDatepicker,
				convertEuropeanToISO: window.ProjectCheckDatepicker.convertEuropeanToISO
			};
		}
		console.error('[ProjectForm] Shared datepicker script missing. Falling back to manual date input.');
		return {
			initializeDatepicker: null,
			convertEuropeanToISO: fallbackConvertEuropeanToISO
		};
	}

	/**
	 * Initialize project form functionality
	 */
	function initializeProjectForm() {
		const startDateInput = document.getElementById('start_date');
		const endDateInput = document.getElementById('end_date');

		// Initialize shared datepicker from common/datepicker.js
		const datepickerFuncs = getDatepickerFunctions();
		try {
			if (startDateInput && typeof datepickerFuncs.initializeDatepicker === 'function') {
				datepickerFuncs.initializeDatepicker(startDateInput);
			} else if (startDateInput) {
				enableManualDateInput(startDateInput);
			}
			if (endDateInput && typeof datepickerFuncs.initializeDatepicker === 'function') {
				datepickerFuncs.initializeDatepicker(endDateInput);
			} else if (endDateInput) {
				enableManualDateInput(endDateInput);
			}
		} catch (error) {
			console.error('[ProjectForm] Error initializing datepicker:', error);
			enableManualDateInput(startDateInput);
			enableManualDateInput(endDateInput);
		}

		// Convert European date format to ISO format before form submission
		const form = document.getElementById('project-form');
		if (form) {
			form.addEventListener('submit', function(e) {
				const datepickerFuncs = getDatepickerFunctions();
				if (startDateInput && startDateInput.value) {
					startDateInput.value = datepickerFuncs.convertEuropeanToISO(startDateInput.value);
				}
				if (endDateInput && endDateInput.value) {
					endDateInput.value = datepickerFuncs.convertEuropeanToISO(endDateInput.value);
				}
			});
		}
	}

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initializeProjectForm);
	} else {
		initializeProjectForm();
	}
})();

