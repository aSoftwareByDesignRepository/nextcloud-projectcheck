/**
 * Project form: pricing mode progressive disclosure, capacity estimates, char counts.
 *
 * UX contract:
 * - Never write "" into available_hours (DECIMAL abort risk if ever submitted).
 * - Hard-block submit only when the user *changes* budget/rate into an invalid
 *   project-mode state (budget>0, rate≤0). Legacy invalid rows can still save
 *   name/status/description — matching the server soft-validation rule.
 */
(function () {
	'use strict';

	const MODES = {
		project: 'project',
		employee: 'employee',
		project_member: 'project_member',
	};

	function tPc(msg) {
		return typeof t === 'function' ? t('projectcheck', msg) : msg;
	}

	function selectedMode() {
		const checked = document.querySelector('input[name="cost_rate_mode"]:checked');
		const hidden = document.querySelector('input[type="hidden"][name="cost_rate_mode"]');
		if (checked) {
			return checked.value;
		}
		if (hidden) {
			return hidden.value;
		}
		return MODES.project;
	}

	function parseAmount(input) {
		if (!input) {
			return 0;
		}
		const raw = typeof input === 'string' ? input : input.value;
		if (raw === null || raw === undefined) {
			return 0;
		}
		const normalized = String(raw).trim().replace(/\s/g, '').replace(',', '.');
		const parsed = Number.parseFloat(normalized);
		return Number.isFinite(parsed) ? parsed : 0;
	}

	function initialAmount(input) {
		if (!input) {
			return 0;
		}
		const initial = input.getAttribute('data-initial-value');
		return parseAmount(initial === null ? input.value : initial);
	}

	function pricingDirty(budgetInput, rateInput) {
		return parseAmount(budgetInput) !== initialAmount(budgetInput)
			|| parseAmount(rateInput) !== initialAmount(rateInput);
	}

	function updatePricingGate(needsRate, dirty) {
		const gate = document.getElementById('pc-pricing-gate');
		if (!gate) {
			return;
		}
		const show = needsRate;
		gate.hidden = !show;
		gate.classList.toggle('pc-pricing-gate--blocking', show && dirty);
		gate.classList.toggle('pc-pricing-gate--info', show && !dirty);
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

		// Always a numeric string — never "" (DECIMAL / form-submit footgun).
		availableHoursInput.value = '0';
		if (mode !== MODES.project && budget > 0 && rate <= 0) {
			availableHoursInput.placeholder = '—';
			availableHoursInput.classList.add('pc-capacity-input--unavailable');
			return;
		}

		availableHoursInput.placeholder = '0';
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
		const budgetInput = document.getElementById('total_budget');

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

		const budget = budgetInput ? parseAmount(budgetInput) : 0;
		const needsProjectRate = mode === MODES.project && budget > 0;
		const dirty = pricingDirty(budgetInput, rateInput);

		if (mode === MODES.project) {
			rateGroup.hidden = false;
			rateLabel.textContent = rateLabel.dataset.labelProject || rateLabel.textContent;
			// Only hard-require when the user is changing pricing into an invalid state.
			const mustBlock = needsProjectRate && parseAmount(rateInput) <= 0 && dirty;
			rateInput.required = mustBlock;
			rateInput.setAttribute('aria-required', mustBlock ? 'true' : 'false');
			if (mustBlock) {
				rateInput.setCustomValidity(tPc('Hourly rate is required'));
			} else {
				rateInput.setCustomValidity('');
			}
			if (capacityHint) {
				capacityHint.textContent = capacityHint.dataset.hintProject || '';
			}
			updatePricingGate(needsProjectRate && parseAmount(rateInput) <= 0, dirty);
		} else {
			rateGroup.hidden = false;
			rateLabel.textContent = rateLabel.dataset.labelPlanning || rateLabel.textContent;
			rateInput.required = false;
			rateInput.removeAttribute('aria-required');
			rateInput.setCustomValidity('');
			if (capacityHint) {
				capacityHint.textContent = capacityHint.dataset.hintPlanning || '';
			}
			updatePricingGate(false, false);
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
		const form = document.getElementById('project-form');
		if (budgetInput) {
			budgetInput.addEventListener('input', applyMode);
			budgetInput.addEventListener('change', applyMode);
		}
		if (rateInput) {
			rateInput.addEventListener('input', applyMode);
			rateInput.addEventListener('change', applyMode);
		}
		if (form) {
			form.addEventListener('submit', function (e) {
				applyMode();
				if (rateInput && rateInput.validationMessage) {
					e.preventDefault();
					rateInput.reportValidity();
					const gate = document.getElementById('pc-pricing-gate');
					if (gate && !gate.hidden) {
						try {
							gate.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
						} catch (err) {
							gate.scrollIntoView(true);
						}
					}
				}
			});
		}
		applyMode();
		bindCharCounts();

		const formError = document.getElementById('pc-project-form-error');
		if (formError) {
			try {
				formError.focus({ preventScroll: true });
				formError.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
			} catch (err) {
				formError.focus();
			}
		}
	}

	document.addEventListener('DOMContentLoaded', bind);
})();
