/**
 * Patches window.t for the projectcheck app: uses server IL10N from #pc-js-l10n-raw
 * (EnrichTemplateNavigationContext + common/pc-l10n-bootstrap.php).
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

/* global window */
(function () {
	'use strict';
	var PCID = 'projectcheck';

	if (typeof window.t !== 'function') {
		return;
	}
	var el = document.getElementById('pc-js-l10n-raw');
	if (!el) {
		return;
	}
	var raw = (el.value || el.textContent || '').trim();
	if (!raw) {
		return;
	}
	/** @type {Record<string, string>|null} */
	var store;
	try {
		store = JSON.parse(raw);
	} catch (e) {
		return;
	}
	if (!store || typeof store !== 'object') {
		return;
	}

	/**
	 * @param {string} s
	 * @param {Record<string, string|number|boolean|undefined|null>} p
	 * @returns {string}
	 */
	function applyNamedPlaceholders(s, p) {
		if (!p || typeof s !== 'string' || s.indexOf('{') === -1) {
			return s;
		}
		return s.replace(/\{([a-zA-Z0-9_]+)\}/g, function (match, name) {
			if (!Object.prototype.hasOwnProperty.call(p, name) || p[name] === undefined) {
				return match;
			}
			return String(p[name]);
		});
	}

	/**
	 * @param {string} s
	 * @param {string|number|boolean|Array<string|number|boolean|undefined|null>|null|undefined} args
	 * @returns {string}
	 */
	function applySimplePercent(s, args) {
		if (s.indexOf('%') === -1) {
			return s;
		}
		var list = Array.isArray(args) ? args : [args];
		var i = 0;
		return s.replace(/%%|%\.\d+f|%s|%d/g, function (m) {
			if (m === '%%') {
				return '%';
			}
			if (i >= list.length) {
				return m;
			}
			var v = list[i++];
			if (v === undefined || v === null) {
				return '';
			}
			if (m === '%d') {
				return String(parseInt(String(v), 10) || 0);
			}
			if (m[0] === '%' && m.indexOf('f', m.length - 1) === m.length - 1 && m.indexOf('.') === 1) {
				var precMatch = m.match(/^%\.(\d+)f$/);
				var prec = precMatch ? parseInt(precMatch[1], 10) : 2;
				if (!isFinite(prec) || prec < 0) {
					prec = 2;
				}
				var f = parseFloat(String(v));
				if (!isFinite(f)) {
					return String(v);
				}
				return f.toFixed(prec);
			}
			return String(v);
		});
	}

	var origT = window.t;
	window.t = function (app, msg, args) {
		if (app !== PCID) {
			return origT.apply(this, arguments);
		}
		if (msg == null) {
			return origT.apply(this, arguments);
		}
		var k = String(msg);
		if (!Object.prototype.hasOwnProperty.call(store, k)) {
			return origT.apply(this, arguments);
		}
		var tr = store[k];
		if (tr === undefined) {
			return origT.apply(this, arguments);
		}
		if (args === undefined || args === null) {
			return tr;
		}
		if (typeof args === 'string' || typeof args === 'number' || typeof args === 'boolean' || Array.isArray(args)) {
			return applySimplePercent(String(tr), args);
		}
		if (typeof args === 'object') {
			// named {x} in translated strings; budget-warnings, ticket-style maps
			return applyNamedPlaceholders(String(tr), /** @type {any} */(args));
		}
		return origT.apply(this, arguments);
	};
})();
