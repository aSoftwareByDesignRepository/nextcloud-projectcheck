/**
 * Personal settings panel for ProjectCheck.
 *
 * The form is rendered inside Nextcloud's Personal Settings UI; this script
 * progressively enhances the form with client-side validation and an
 * asynchronous save flow. Without JavaScript the form still posts to the
 * configured save URL — Nextcloud's CSRF middleware rejects requests without
 * a valid request token, so the failure mode is "save rejected" rather than
 * "settings silently broken".
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function () {
	'use strict';

	const FORM_ID = 'projectcheck-personal-settings-form';
	const STATUS_TIMEOUT_MS = 6000;

	function $(selector, root) {
		return (root || document).querySelector(selector);
	}

	function getRequestToken(form) {
		const fromForm = form ? form.querySelector('input[name="requesttoken"]') : null;
		if (fromForm && fromForm.value) {
			return fromForm.value;
		}
		if (typeof OC !== 'undefined' && OC && typeof OC.requestToken === 'string') {
			return OC.requestToken;
		}
		const meta = document.head.querySelector('meta[name="requesttoken"]');
		if (meta && meta.content) {
			return meta.content;
		}
		const bodyToken = document.body ? document.body.getAttribute('data-requesttoken') : null;
		return bodyToken || '';
	}

	function tString(key, fallback, params) {
		if (typeof t === 'function') {
			try {
				return t('projectcheck', fallback, params || undefined);
			} catch (e) {
				// Fall through to the literal fallback when the i18n bundle
				// has not loaded yet (e.g. when this script runs before
				// OC.L10N is initialised).
			}
		}
		return fallback;
	}

	function clearFieldError(field, errorEl) {
		if (errorEl) {
			errorEl.hidden = true;
			errorEl.textContent = '';
		}
		if (field) {
			field.removeAttribute('aria-invalid');
			field.classList.remove('has-error');
		}
	}

	function setFieldError(field, errorEl, message) {
		if (errorEl) {
			errorEl.hidden = false;
			errorEl.textContent = message;
		}
		if (field) {
			field.setAttribute('aria-invalid', 'true');
			field.classList.add('has-error');
		}
	}

	function parseInteger(raw) {
		if (typeof raw !== 'string') {
			return null;
		}
		const trimmed = raw.trim();
		if (trimmed === '' || !/^-?\d+$/.test(trimmed)) {
			return null;
		}
		const value = parseInt(trimmed, 10);
		return Number.isFinite(value) ? value : null;
	}

	function validateForm(form) {
		const warningField = $('#pc-personal-warning', form);
		const criticalField = $('#pc-personal-critical', form);
		const warningError = $('#pc-personal-warning-error', form);
		const criticalError = $('#pc-personal-critical-error', form);

		clearFieldError(warningField, warningError);
		clearFieldError(criticalField, criticalError);

		let valid = true;
		const warning = parseInteger(warningField ? warningField.value : '');
		const critical = parseInteger(criticalField ? criticalField.value : '');

		const rangeMessage = tString(
			'budget_threshold_range',
			'Enter a whole number between 0 and 100.'
		);
		if (warning === null || warning < 0 || warning > 100) {
			setFieldError(warningField, warningError, rangeMessage);
			valid = false;
		}
		if (critical === null || critical < 0 || critical > 100) {
			setFieldError(criticalField, criticalError, rangeMessage);
			valid = false;
		}

		// Cross-field check only if both fields parse as valid integers.
		if (valid && warning !== null && critical !== null && warning >= critical) {
			setFieldError(
				warningField,
				warningError,
				tString('budget_warning_lower', 'The warning threshold must be lower than the critical threshold.')
			);
			setFieldError(
				criticalField,
				criticalError,
				tString('budget_critical_higher', 'The critical threshold must be higher than the warning threshold.')
			);
			valid = false;
		}

		return valid
			? { ok: true, payload: { budget_warning_threshold: warning, budget_critical_threshold: critical } }
			: { ok: false };
	}

	function setStatus(form, message, kind) {
		const status = form.querySelector('[data-pc-status]');
		if (!status) {
			return;
		}
		status.textContent = message || '';
		status.classList.remove('is-success', 'is-error');
		if (kind) {
			status.classList.add(kind === 'error' ? 'is-error' : 'is-success');
		}
		if (status._pcTimeout) {
			window.clearTimeout(status._pcTimeout);
		}
		if (message && kind) {
			status._pcTimeout = window.setTimeout(() => {
				status.textContent = '';
				status.classList.remove('is-success', 'is-error');
			}, STATUS_TIMEOUT_MS);
		}
	}

	function setSubmitting(form, submitting) {
		const button = form.querySelector('[data-pc-save]');
		const spinner = form.querySelector('.projectcheck-personal__save-spinner');
		if (!button) {
			return;
		}
		button.disabled = !!submitting;
		button.setAttribute('aria-busy', submitting ? 'true' : 'false');
		if (spinner) {
			spinner.hidden = !submitting;
		}
	}

	function applyServerFieldErrors(form, fieldErrors) {
		if (!fieldErrors || typeof fieldErrors !== 'object') {
			return;
		}
		const map = {
			budget_warning_threshold: {
				field: $('#pc-personal-warning', form),
				error: $('#pc-personal-warning-error', form),
			},
			budget_critical_threshold: {
				field: $('#pc-personal-critical', form),
				error: $('#pc-personal-critical-error', form),
			},
		};
		Object.keys(fieldErrors).forEach((key) => {
			const target = map[key];
			if (!target) {
				return;
			}
			setFieldError(target.field, target.error, String(fieldErrors[key]));
		});
		// Move focus to the first invalid field for accessibility.
		const firstKey = Object.keys(fieldErrors).find((k) => map[k] && map[k].field);
		if (firstKey) {
			map[firstKey].field.focus();
		}
	}

	async function submitForm(form) {
		const validation = validateForm(form);
		if (!validation.ok) {
			setStatus(form, tString('check_fields', 'Please check the highlighted fields.'), 'error');
			const firstInvalid = form.querySelector('[aria-invalid="true"]');
			if (firstInvalid) {
				firstInvalid.focus();
			}
			return;
		}

		const url = form.dataset.saveUrl || form.getAttribute('action') || '';
		if (!url) {
			setStatus(form, tString('save_error', 'Could not save your settings. Please try again.'), 'error');
			return;
		}

		setSubmitting(form, true);
		setStatus(form, '', null);

		try {
			const response = await fetch(url, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Accept': 'application/json',
					'Content-Type': 'application/json',
					'requesttoken': getRequestToken(form),
					'X-Requested-With': 'XMLHttpRequest',
				},
				body: JSON.stringify(validation.payload),
			});

			let body = null;
			const contentType = response.headers.get('content-type') || '';
			if (contentType.indexOf('application/json') !== -1) {
				try {
					body = await response.json();
				} catch (e) {
					body = null;
				}
			}

			if (response.ok && body && body.success === true) {
				setStatus(form, body.message || tString('save_ok', 'Your preferences were saved.'), 'success');
				return;
			}

			if (body && body.fieldErrors) {
				applyServerFieldErrors(form, body.fieldErrors);
			}
			setStatus(
				form,
				(body && body.message) || tString('save_error', 'Could not save your settings. Please try again.'),
				'error'
			);
		} catch (e) {
			setStatus(form, tString('save_error', 'Could not save your settings. Please try again.'), 'error');
		} finally {
			setSubmitting(form, false);
		}
	}

	function bindLiveValidation(form) {
		['#pc-personal-warning', '#pc-personal-critical'].forEach((sel) => {
			const el = form.querySelector(sel);
			if (!el) {
				return;
			}
			el.addEventListener('input', () => {
				// Only clear stale errors; full re-validation happens at submit time
				// to avoid distracting the user with intermediate states while typing.
				const errorEl = form.querySelector(sel + '-error');
				if (errorEl && !errorEl.hidden) {
					clearFieldError(el, errorEl);
				}
			});
		});
	}

	function init() {
		const form = document.getElementById(FORM_ID);
		if (!form) {
			return;
		}
		form.addEventListener('submit', (event) => {
			event.preventDefault();
			submitForm(form);
		});
		bindLiveValidation(form);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init, { once: true });
	} else {
		init();
	}
})();
