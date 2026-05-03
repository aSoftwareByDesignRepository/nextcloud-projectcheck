/**
 * Centralized locale-aware formatting helpers for the projectcheck frontend.
 *
 * Audit reference: AUDIT-FINDINGS.md B10 (hard-coded `en-US`/`USD`) and H28
 * (locale/currency drift across modules). Every page must funnel its number,
 * currency and date formatting through this single module so:
 *   - the user's Nextcloud language drives all `Intl.*` formatters,
 *   - the displayed currency comes from the server-injected app config,
 *   - failures (Intl missing, exotic locales) fall back deterministically.
 *
 * Public API: `window.ProjectCheckFormat`.
 */

/* global OC, OCA */
(function (root) {
	'use strict';

	const FALLBACK_LOCALE = 'en';
	const FALLBACK_CURRENCY = 'EUR';

	function readLocale() {
		try {
			if (typeof OC !== 'undefined' && typeof OC.getLanguage === 'function') {
				const lang = OC.getLanguage();
				if (typeof lang === 'string' && lang.length) {
					return lang.replace(/_/g, '-');
				}
			}
		} catch (e) { /* ignore */ }
		const htmlLang = document.documentElement.getAttribute('lang');
		if (htmlLang) {
			return htmlLang.replace(/_/g, '-');
		}
		return FALLBACK_LOCALE;
	}

	function readCurrency() {
		// 1. Page-level override (server template can set this once).
		if (root.ProjectCheckConfig && typeof root.ProjectCheckConfig.currency === 'string') {
			const c = root.ProjectCheckConfig.currency.trim().toUpperCase();
			if (/^[A-Z]{3}$/.test(c)) {
				return c;
			}
		}
		// 2. OCA namespace (set by JsL10nCatalogBuilder if extended later).
		if (typeof OCA !== 'undefined' && OCA.ProjectCheck && typeof OCA.ProjectCheck.currency === 'string') {
			const c = OCA.ProjectCheck.currency.trim().toUpperCase();
			if (/^[A-Z]{3}$/.test(c)) {
				return c;
			}
		}
		return FALLBACK_CURRENCY;
	}

	function toFiniteNumber(value) {
		if (value === null || value === undefined || value === '') {
			return null;
		}
		const n = typeof value === 'number' ? value : parseFloat(String(value));
		if (!Number.isFinite(n)) {
			return null;
		}
		return n;
	}

	const ProjectCheckFormat = {
		/** @returns {string} BCP 47 tag, e.g. "de-DE" */
		locale: readLocale,
		/** @returns {string} ISO-4217 code, e.g. "EUR" */
		currency: readCurrency,

		/**
		 * Locale-aware number with min/max fraction digits.
		 * Returns the dash-em fallback for non-numeric input.
		 *
		 * @param {*} value
		 * @param {{minimumFractionDigits?:number,maximumFractionDigits?:number}} [opts]
		 */
		number(value, opts) {
			const n = toFiniteNumber(value);
			if (n === null) {
				return '\u2014';
			}
			const o = Object.assign({
				minimumFractionDigits: 0,
				maximumFractionDigits: 0,
			}, opts || {});
			try {
				return new Intl.NumberFormat(readLocale(), o).format(n);
			} catch (e) {
				return n.toFixed(o.maximumFractionDigits || 0);
			}
		},

		/**
		 * Locale-aware currency. Currency code defaults to the configured
		 * app currency (EUR) but can be overridden per call.
		 */
		currencyFmt(value, currencyCode) {
			const n = toFiniteNumber(value);
			if (n === null) {
				return '\u2014';
			}
			const code = (typeof currencyCode === 'string' && /^[A-Z]{3}$/i.test(currencyCode))
				? currencyCode.toUpperCase()
				: readCurrency();
			try {
				return new Intl.NumberFormat(readLocale(), {
					style: 'currency',
					currency: code,
				}).format(n);
			} catch (e) {
				return code + ' ' + n.toFixed(2);
			}
		},

		/**
		 * Percent value where the input is the *displayable* percentage
		 * (e.g. 12.5 -> "12,5 %"). Use `ratio()` for 0-1 inputs.
		 */
		percent(value, fractionDigits) {
			const n = toFiniteNumber(value);
			if (n === null) {
				return '\u2014';
			}
			const digits = typeof fractionDigits === 'number' ? fractionDigits : 1;
			try {
				return new Intl.NumberFormat(readLocale(), {
					minimumFractionDigits: 0,
					maximumFractionDigits: digits,
				}).format(n) + '\u00A0%';
			} catch (e) {
				return n.toFixed(digits) + '%';
			}
		},

		/** Formats a 0-1 ratio as a percentage. */
		ratio(value, fractionDigits) {
			const n = toFiniteNumber(value);
			if (n === null) {
				return '\u2014';
			}
			return this.percent(n * 100, fractionDigits);
		},

		/**
		 * Locale-aware short date.
		 *
		 * @param {string|Date|number|null} value
		 */
		date(value) {
			if (!value && value !== 0) {
				return '\u2014';
			}
			const d = value instanceof Date ? value : new Date(value);
			if (Number.isNaN(d.getTime())) {
				return '\u2014';
			}
			try {
				return new Intl.DateTimeFormat(readLocale(), {
					year: 'numeric', month: '2-digit', day: '2-digit',
				}).format(d);
			} catch (e) {
				return d.toISOString().substring(0, 10);
			}
		},

		dateTime(value) {
			if (!value && value !== 0) {
				return '\u2014';
			}
			const d = value instanceof Date ? value : new Date(value);
			if (Number.isNaN(d.getTime())) {
				return '\u2014';
			}
			try {
				return new Intl.DateTimeFormat(readLocale(), {
					year: 'numeric', month: '2-digit', day: '2-digit',
					hour: '2-digit', minute: '2-digit',
				}).format(d);
			} catch (e) {
				return d.toISOString().replace('T', ' ').substring(0, 16);
			}
		},

		/** Locale-aware hour value (e.g. "1.5 h"). */
		hours(value) {
			const n = toFiniteNumber(value);
			if (n === null) {
				return '\u2014';
			}
			return this.number(n, { minimumFractionDigits: 0, maximumFractionDigits: 2 }) + '\u00A0h';
		},
	};

	root.ProjectCheckFormat = ProjectCheckFormat;
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = ProjectCheckFormat;
	}
})(typeof window !== 'undefined' ? window : globalThis);
