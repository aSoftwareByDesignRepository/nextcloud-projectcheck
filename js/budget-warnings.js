/**
 * Budget warnings JavaScript for time entry forms
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

/* global t */
(function () {
	'use strict';

	/**
	 * Format a value as locale-aware currency. Audit ref. AUDIT-FINDINGS B10:
	 * delegate to {@link window.ProjectCheckFormat} so the org-configured
	 * currency code drives the display. The previous hard-coded EUR fallback
	 * is retained only when the formatter has not loaded yet (e.g. during a
	 * very early script evaluation race) so the call site never throws.
	 *
	 * @param {number|string} value
	 * @returns {string}
	 */
	function formatEur(value) {
		if (window.ProjectCheckFormat) {
			return window.ProjectCheckFormat.currencyFmt(value);
		}
		const n = parseFloat(String(value));
		if (isNaN(n) || !isFinite(n)) {
			return '—';
		}
		var loc = (typeof OC !== 'undefined' && typeof OC.getLanguage === 'function')
			? String(OC.getLanguage()).replace(/_/g, '-')
			: (document.documentElement.getAttribute('lang') || 'en').replace(/_/g, '-');
		try {
			return new Intl.NumberFormat(loc, { style: 'currency', currency: 'EUR' }).format(n);
		} catch (e) {
			return 'EUR ' + n.toFixed(2);
		}
	}

	/**
	 * Replace {placeholders} in case core t() does not apply the third-argument object (defensive).
	 * @param {string} s
	 * @param {Record<string, string>} params
	 * @returns {string}
	 */
	function applyNamedPlaceholders(s, params) {
		if (!params || typeof s !== 'string' || s.indexOf('{') === -1) {
			return s;
		}
		return s.replace(/\{([a-zA-Z0-9_]+)\}/g, function (match, name) {
			if (!Object.prototype.hasOwnProperty.call(params, name) || params[name] === undefined) {
				return match;
			}
			return String(params[name]);
		});
	}

	/** @type {Record<string, 'exceed'|'threshold'|'ok'|'error'>} */
	var BUDGET_T_TO_SERVER = {
		'This entry would exceed the project budget by {amount}.': 'exceed',
		'After this entry, the project would be at {usage} of the budget. Additional cost: {cost}.': 'threshold',
		'Entry cost: {entryCost}. Remaining budget: {remaining}.': 'ok',
		'Could not check budget. Try again.': 'error',
	};

	/**
	 * Prefer server-injected IL10N (TimeEntryController): browser `t('projectcheck',…)` does not
	 * resolve app l10n JSON, so the English msgid is returned with only numbers localized.
	 *
	 * @param {string} key
	 * @param {Record<string, string>} [params]
	 * @returns {string}
	 */
	function tPl(key, params) {
		var fromServer = getBudgetL10nFromPage(key);
		if (fromServer) {
			return applyNamedPlaceholders(fromServer, params || {});
		}
		var s = params ? t('projectcheck', key, params) : t('projectcheck', key);
		s = typeof s === 'string' ? s : String(s);
		if (s.indexOf('{') === -1) {
			return s;
		}
		if (params) {
			return applyNamedPlaceholders(s, params);
		}
		return s;
	}

	/**
	 * Translated templates from the page (time-entry-form.php + IL10N). This is the only reliable
	 * source: inline JSON and OCA can run in the wrong order relative to this script; DOM is always ready.
	 *
	 * @param {string} englishMsgId
	 * @returns {string}
	 */
	function getBudgetL10nFromPage(englishMsgId) {
		var fromDom = getBudgetL10nFromDom(englishMsgId);
		if (fromDom) {
			return fromDom;
		}
		if (typeof OCA === 'undefined' || !OCA.ProjectCheck || !OCA.ProjectCheck.budgetImpactL10n) {
			return '';
		}
		var k = BUDGET_T_TO_SERVER[englishMsgId];
		if (!k) {
			return '';
		}
		var tpl = OCA.ProjectCheck.budgetImpactL10n[k];
		return (typeof tpl === 'string' && tpl.length) ? tpl : '';
	}

	/**
	 * @param {string} englishMsgId
	 * @returns {string}
	 */
	function getBudgetL10nFromDom(englishMsgId) {
		var k = BUDGET_T_TO_SERVER[englishMsgId];
		if (!k) {
			return '';
		}
		var root = document.getElementById('pc-budget-l10n');
		if (!root) {
			return '';
		}
		var el = root.querySelector('[data-budget-tpl="' + k + '"]');
		if (!el) {
			return '';
		}
		return (el.textContent || '').replace(/\s+/g, ' ').trim();
	}

	/**
	 * Format a percentage in the user's locale. Audit ref. AUDIT-FINDINGS B10:
	 * delegate to the central formatter; the inlined `Intl.NumberFormat` block
	 * remains only as a defensive fallback for the boot-order edge case.
	 *
	 * @param {number|string} value
	 * @returns {string}
	 */
	function formatPercent(value) {
		if (window.ProjectCheckFormat) {
			return window.ProjectCheckFormat.percent(value, 1);
		}
		const n = parseFloat(String(value));
		if (isNaN(n) || !isFinite(n)) {
			return '—';
		}
		var loc = (typeof OC !== 'undefined' && typeof OC.getLanguage === 'function')
			? String(OC.getLanguage()).replace(/_/g, '-')
			: (document.documentElement.getAttribute('lang') || 'en').replace(/_/g, '-');
		try {
			return new Intl.NumberFormat(loc, { maximumFractionDigits: 1, minimumFractionDigits: 0 }).format(n) + '\u00A0%';
		} catch (e) {
			return n.toFixed(1) + '%';
		}
	}


	/**
	 * Initialize budget warning functionality
	 */
	function initializeBudgetWarnings() {
		const projectSelect = document.getElementById('project_id');
		const hoursInput = document.getElementById('hours');
		const rateInput = document.getElementById('hourly_rate');

		if (projectSelect && hoursInput && rateInput) {
			[projectSelect, hoursInput, rateInput].forEach((element) => {
				element.addEventListener('change', checkBudgetImpact);
				element.addEventListener('input', debounce(checkBudgetImpact, 500));
			});
		}
	}

	/**
	 * Check budget impact when form values change
	 */
	function checkBudgetImpact() {
		const projectSelect = document.getElementById('project_id');
		const hoursField = document.getElementById('hours');
		const rateField = document.getElementById('hourly_rate');
		if (!projectSelect || !hoursField || !rateField) {
			return;
		}

		const projectId = projectSelect.value;
		const hours = parseFloat(hoursField.value) || 0;
		const rate = parseFloat(rateField.value) || 0;

		if (!projectId || hours <= 0 || rate <= 0) {
			hideBudgetWarning();
			return;
		}

		fetch(OC.generateUrl('/apps/projectcheck/api/budget/impact'), {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				requesttoken: OC.requestToken
			},
			body: JSON.stringify({
				project_id: parseInt(projectId, 10),
				additional_hours: hours,
				additional_rate: rate
			})
		})
			.then((response) => response.json())
			.then((result) => {
				if (result.success) {
					displayBudgetImpact(result.impact);
				} else {
					hideBudgetWarning();
				}
			})
			.catch(function (err) {
				if (typeof console !== 'undefined' && console.error) {
					console.error('Error checking budget impact:', err);
				}
				hideBudgetWarning();
				showNotification(tPl('Could not check budget. Try again.'));
			});
	}

	/**
	 * @param {object} impact
	 */
	function displayBudgetImpact(impact) {
		if (!impact || impact.has_budget === false) {
			hideBudgetWarning();
			return;
		}

		let container = document.getElementById('budget-warning-container');

		if (!container) {
			container = document.createElement('div');
			container.id = 'budget-warning-container';
			container.className = 'pc-budget-warning-surface';

			const hoursField = document.getElementById('hours');
			if (hoursField) {
				const formRow = hoursField.closest('.form-row');
				if (formRow && formRow.parentNode) {
					formRow.parentNode.insertBefore(container, formRow);
				}
			} else {
				return;
			}
		}

		var warningLevel = impact.warning_level_after || 'none';
		var wouldExceed = impact.would_exceed_budget;
		/** @type {string} */
		var message = '';
		/** @type {string} */
		var modClass = 'ok';

		if (wouldExceed) {
			var overRaw = (impact.additional_cost - impact.remaining_budget_after);
			var over = overRaw > 0 ? overRaw : 0;
			message = tPl('This entry would exceed the project budget by {amount}.', {
				amount: formatEur(over)
			});
			modClass = 'over-budget';
		} else if (warningLevel === 'critical' || warningLevel === 'warning') {
			message = tPl('After this entry, the project would be at {usage} of the budget. Additional cost: {cost}.', {
				usage: formatPercent(impact.new_consumption),
				cost: formatEur(impact.additional_cost)
			});
			modClass = (warningLevel === 'critical' ? 'critical' : 'warning');
		} else {
			message = tPl('Entry cost: {entryCost}. Remaining budget: {remaining}.', {
				entryCost: formatEur(impact.additional_cost),
				remaining: formatEur(impact.remaining_budget_after)
			});
		}

		// a11y: over-budget / high severity → alert; other updates → status
		var isAlert = wouldExceed || warningLevel === 'critical';
		container.setAttribute('role', isAlert ? 'alert' : 'status');
		container.setAttribute('aria-live', isAlert ? 'assertive' : 'polite');
		if (isAlert) {
			container.setAttribute('aria-atomic', 'true');
		} else {
			container.removeAttribute('aria-atomic');
		}

		// Rebuild without innerHTML (XSS hardening, predictable DOM)
		while (container.firstChild) {
			container.removeChild(container.firstChild);
		}
		var box = document.createElement('div');
		box.className = 'pc-budget-impact budget-impact-info' + (modClass === 'ok' ? ' ok' : ' ' + modClass);
		var h = document.createElement('h3');
		h.className = 'pc-budget-impact__title';
		var titleText = (typeof t === 'function') ? t('projectcheck', 'Live budget check') : 'Live budget check';
		h.textContent = titleText;
		var p = document.createElement('p');
		p.className = 'pc-budget-impact__text';
		p.textContent = message;
		box.appendChild(h);
		box.appendChild(p);
		container.appendChild(box);

		container.style.display = 'block';
	}

	/**
	 * Hide budget warning
	 */
	function hideBudgetWarning() {
		const c = document.getElementById('budget-warning-container');
		if (c) {
			c.style.display = 'none';
		}
	}

	function debounce(func, wait) {
		var timeout;
		return function executedFunction() {
			var context = this;
			var args = arguments;
			var later = function () {
				clearTimeout(timeout);
				func.apply(context, args);
			};
			clearTimeout(timeout);
			timeout = setTimeout(later, wait);
		};
	}

	/**
	 * @param {string} message
	 */
	function showNotification(message) {
		if (window.OC && window.OC.Notification) {
			OC.Notification.showTemporary(message);
		} else {
			var el = document.getElementById('projectcheck-budget-a11y-live');
			if (!el) {
				el = document.createElement('div');
				el.id = 'projectcheck-budget-a11y-live';
				el.setAttribute('role', 'status');
				el.setAttribute('aria-live', 'polite');
				el.style.cssText = 'position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0;';
				document.body.appendChild(el);
			}
			el.textContent = message;
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initializeBudgetWarnings);
	} else {
		initializeBudgetWarnings();
	}
})();
