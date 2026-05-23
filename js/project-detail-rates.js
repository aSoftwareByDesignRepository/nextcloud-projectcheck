/**
 * Per-person project rate: inline team list rate append (PUT member).
 */
(function () {
	'use strict';

	const cfg = window.projectDetailRatesConfig || {};
	if (!cfg.enabled) {
		return;
	}

	function notify(message, isError) {
		if (typeof window.ProjectCheckNotify !== 'undefined') {
			window.ProjectCheckNotify.show(message, isError ? 'error' : undefined);
			return;
		}
		if (typeof OC !== 'undefined' && OC.Notification) {
			OC.Notification.showTemporary(message, isError ? { type: 'error' } : undefined);
			return;
		}
		const region = document.getElementById('pc-alert-region');
		if (region) {
			region.textContent = message;
		}
	}

	function toIsoDate(value) {
		const trimmed = (value || '').trim();
		if (/^\d{4}-\d{2}-\d{2}$/.test(trimmed)) {
			return trimmed;
		}
		const eu = trimmed.match(/^(\d{2})\.(\d{2})\.(\d{4})$/);
		if (eu) {
			return `${eu[3]}-${eu[2]}-${eu[1]}`;
		}
		return trimmed;
	}

	document.addEventListener('click', async (event) => {
		const button = event.target.closest('.pc-member-rate-save');
		if (!button || button.disabled) {
			return;
		}

		const row = button.closest('.team-member-item');
		if (!row) {
			return;
		}

		const rateInput = row.querySelector('.pc-member-rate-input');
		const dateInput = row.querySelector('.pc-member-rate-date');
		const errorEl = row.querySelector('.pc-member-rate-error');
		const updateUrl = button.getAttribute('data-update-url');
		if (!rateInput || !updateUrl) {
			return;
		}

		const rate = parseFloat(rateInput.value);
		if (!Number.isFinite(rate) || rate <= 0) {
			if (errorEl) {
				errorEl.textContent = cfg.messages.ratePositive || 'Hourly rate must be a positive number';
			}
			rateInput.setAttribute('aria-invalid', 'true');
			rateInput.focus();
			return;
		}

		const effectiveFrom = dateInput ? toIsoDate(dateInput.value) : '';
		if (!effectiveFrom) {
			if (errorEl) {
				errorEl.textContent = cfg.messages.dateRequired || 'Effective-from date is required';
			}
			if (dateInput) {
				dateInput.setAttribute('aria-invalid', 'true');
				dateInput.focus();
			}
			return;
		}

		if (errorEl) {
			errorEl.textContent = '';
		}
		rateInput.removeAttribute('aria-invalid');
		if (dateInput) {
			dateInput.removeAttribute('aria-invalid');
		}

		button.disabled = true;
		button.setAttribute('aria-busy', 'true');
		const token = document.querySelector('input[name="requesttoken"]')?.value
			|| (typeof OC !== 'undefined' ? OC.requestToken : '');

		try {
			const body = new URLSearchParams();
			body.append('hourly_rate', String(rate));
			body.append('effective_from', effectiveFrom);
			body.append('requesttoken', token);

			const response = await fetch(updateUrl, {
				method: 'PUT',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
					'X-Requested-With': 'XMLHttpRequest',
					requesttoken: token,
				},
				body: body.toString(),
				credentials: 'same-origin',
			});
			const data = await response.json().catch(() => ({}));
			if (!response.ok || !data.success) {
				throw new Error((data && data.error) ? data.error : (cfg.messages.saveFailed || 'Could not save rate'));
			}
			const display = row.querySelector('.pc-member-rate-current');
			if (display) {
				display.textContent = cfg.messages.rateSavedDisplay
					? cfg.messages.rateSavedDisplay.replace('%s', String(rate))
					: String(rate);
			}
			notify(data.message || cfg.messages.rateSaved || 'Rate saved.', false);
			rateInput.value = '';
		} catch (err) {
			if (errorEl) {
				errorEl.textContent = err.message || cfg.messages.saveFailed || 'Could not save rate';
			}
			notify(err.message || cfg.messages.saveFailed || 'Could not save rate', true);
		} finally {
			button.disabled = false;
			button.removeAttribute('aria-busy');
		}
	});
})();
