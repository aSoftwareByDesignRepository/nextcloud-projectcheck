/**
 * Project form: pricing mode progressive disclosure, capacity estimates, char counts.
 */
(function () {
	'use strict';

	const MODES = {
		project: 'project',
		employee: 'employee',
		project_member: 'project_member',
	};

	function selectedMode() {
		const checked = document.querySelector('input[name="cost_rate_mode"]:checked');
		return checked ? checked.value : MODES.project;
	}

	function parseAmount(input) {
		if (!input) {
			return 0;
		}
		const parsed = Number.parseFloat(input.value);
		return Number.isFinite(parsed) ? parsed : 0;
	}

	function updateAvailableHours() {
		const totalBudgetInput = document.getElementById('total_budget');
		const hourlyRateInput = document.getElementById('hourly_rate');
		const availableHoursInput = document.getElementById('available_hours');
		const helpEl = document.getElementById('pc-available-hours-help');
		if (!availableHoursInput) {
			return;
		}

		const mode = selectedMode();
		const budget = parseAmount(totalBudgetInput);
		const rate = parseAmount(hourlyRateInput);

		if (helpEl) {
			if (mode === MODES.project) {
				helpEl.textContent = helpEl.dataset.helpProject || helpEl.textContent;
			} else if (budget > 0 && rate <= 0) {
				helpEl.textContent = helpEl.dataset.helpUnavailable || helpEl.textContent;
			} else {
				helpEl.textContent = helpEl.dataset.helpPlanning || helpEl.textContent;
			}
		}

		availableHoursInput.classList.remove('pc-capacity-input--unavailable');

		if (budget > 0 && rate > 0) {
			const hours = budget / rate;
			availableHoursInput.value = hours.toFixed(2);
			availableHoursInput.removeAttribute('placeholder');
			return;
		}

		if (mode !== MODES.project && budget > 0 && rate <= 0) {
			availableHoursInput.value = '';
			availableHoursInput.placeholder = '—';
			availableHoursInput.classList.add('pc-capacity-input--unavailable');
			return;
		}

		availableHoursInput.value = '';
		availableHoursInput.placeholder = '—';
		if (helpEl && budget <= 0 && rate <= 0) {
			helpEl.textContent = helpEl.dataset.helpEmpty || helpEl.textContent;
		}
	}

	function applyMode() {
		const mode = selectedMode();
		const rateGroup = document.getElementById('pc-hourly-rate-group');
		const rateLabel = document.getElementById('pc-hourly-rate-label');
		const rateInput = document.getElementById('hourly_rate');
		const capacityHint = document.getElementById('pc-capacity-hint');
		const employeeHint = document.getElementById('pc-pricing-employee-hint');
		const memberHint = document.getElementById('pc-pricing-member-hint');

		if (employeeHint) {
			employeeHint.hidden = mode !== MODES.employee;
		}
		if (memberHint) {
			memberHint.hidden = mode !== MODES.project_member;
		}

		if (!rateGroup || !rateLabel || !rateInput) {
			updateAvailableHours();
			return;
		}

		const budgetInput = document.getElementById('total_budget');
		const budget = budgetInput ? parseAmount(budgetInput) : 0;
		const needsProjectRate = mode === MODES.project && budget > 0;

		if (mode === MODES.project) {
			rateGroup.hidden = false;
			rateLabel.textContent = rateLabel.dataset.labelProject || rateLabel.textContent;
			rateInput.required = needsProjectRate;
			rateInput.setAttribute('aria-required', needsProjectRate ? 'true' : 'false');
			// `required` alone does not catch a pre-filled "0.00" rate (the
			// field is non-empty), but the server rejects budget > 0 with
			// rate <= 0 in this mode — surface that before submitting.
			if (needsProjectRate && parseAmount(rateInput) <= 0) {
				rateInput.setCustomValidity(
					typeof t === 'function'
						? t('projectcheck', 'Hourly rate is required')
						: 'Hourly rate is required'
				);
			} else {
				rateInput.setCustomValidity('');
			}
			if (capacityHint) {
				capacityHint.textContent = capacityHint.dataset.hintProject || '';
			}
		} else {
			rateGroup.hidden = false;
			rateLabel.textContent = rateLabel.dataset.labelPlanning || rateLabel.textContent;
			rateInput.required = false;
			rateInput.removeAttribute('aria-required');
			rateInput.setCustomValidity('');
			if (capacityHint) {
				capacityHint.textContent = capacityHint.dataset.hintPlanning || '';
			}
		}

		updateAvailableHours();
	}

	function updateCharCount(textarea, countElement, maxLength) {
		if (!textarea || !countElement) {
			return;
		}
		const currentLength = textarea.value.length;
		countElement.textContent = String(currentLength);
		const container = countElement.closest('.char-count');
		if (container) {
			container.classList.remove('char-count--warning', 'char-count--critical');
			if (currentLength > maxLength * 0.9) {
				container.classList.add('char-count--critical');
			} else if (currentLength > maxLength * 0.8) {
				container.classList.add('char-count--warning');
			}
		}
	}

	function bindCharCounts() {
		const shortDescriptionTextarea = document.getElementById('short_description');
		const detailedDescriptionTextarea = document.getElementById('detailed_description');
		const shortDescriptionCount = document.getElementById('short_description-count');
		const detailedDescriptionCount = document.getElementById('detailed_description-count');

		if (shortDescriptionTextarea && shortDescriptionCount) {
			shortDescriptionTextarea.addEventListener('input', function () {
				updateCharCount(shortDescriptionTextarea, shortDescriptionCount, 500);
			});
			updateCharCount(shortDescriptionTextarea, shortDescriptionCount, 500);
		}

		if (detailedDescriptionTextarea && detailedDescriptionCount) {
			detailedDescriptionTextarea.addEventListener('input', function () {
				updateCharCount(detailedDescriptionTextarea, detailedDescriptionCount, 2000);
			});
			updateCharCount(detailedDescriptionTextarea, detailedDescriptionCount, 2000);
		}
	}

	function bind() {
		document.querySelectorAll('input[name="cost_rate_mode"]').forEach((el) => {
			el.addEventListener('change', applyMode);
		});
		const budgetInput = document.getElementById('total_budget');
		const rateInput = document.getElementById('hourly_rate');
		if (budgetInput) {
			budgetInput.addEventListener('input', applyMode);
			budgetInput.addEventListener('change', applyMode);
		}
		if (rateInput) {
			rateInput.addEventListener('input', applyMode);
			rateInput.addEventListener('change', applyMode);
		}
		applyMode();
		bindCharCounts();
	}

	document.addEventListener('DOMContentLoaded', bind);
})();
