/**
 * Project form: native date inputs (ISO wire format, WCAG §10).
 */
(function () {
	'use strict';

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

	function validateDateRange(startInput, endInput) {
		if (!startInput || !endInput) {
			return true;
		}
		const startIso = normalizeDateToIso(startInput.value);
		const endIso = normalizeDateToIso(endInput.value);
		if (!startIso || !endIso) {
			endInput.setCustomValidity('');
			return true;
		}
		const start = parseIsoDateLocal(startIso);
		const end = parseIsoDateLocal(endIso);
		if (!start || !end) {
			endInput.setCustomValidity('');
			return true;
		}
		if (end < start) {
			endInput.setCustomValidity(
				typeof t === 'function'
					? t('projectcheck', 'End date must be on or after the start date.')
					: 'End date must be on or after the start date.'
			);
			return false;
		}
		endInput.setCustomValidity('');
		return true;
	}

	function initializeProjectForm() {
		const startDateInput = document.getElementById('start_date');
		const endDateInput = document.getElementById('end_date');
		const form = document.getElementById('project-form');

		function onDateChange() {
			validateDateRange(startDateInput, endDateInput);
		}
		if (startDateInput) {
			startDateInput.addEventListener('change', onDateChange);
		}
		if (endDateInput) {
			endDateInput.addEventListener('change', onDateChange);
		}

		if (form) {
			form.addEventListener('submit', function () {
				if (startDateInput && startDateInput.value) {
					startDateInput.value = normalizeDateToIso(startDateInput.value);
				}
				if (endDateInput && endDateInput.value) {
					endDateInput.value = normalizeDateToIso(endDateInput.value);
				}
			});
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initializeProjectForm);
	} else {
		initializeProjectForm();
	}
})();
