/**
 * Canonical HTML escaping for ProjectCheck (audit: single source, no duplicated helpers).
 *
 * @copyright Copyright (c) 2026, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */
(function () {
	'use strict';

	/**
	 * @param {unknown} text
	 * @returns {string}
	 */
	function html(text) {
		const div = document.createElement('div');
		div.textContent = text == null ? '' : String(text);
		return div.innerHTML;
	}

	/**
	 * @param {unknown} text
	 * @returns {string}
	 */
	function attr(text) {
		return html(text).replace(/"/g, '&quot;');
	}

	window.ProjectCheckEscape = {
		html: html,
		attr: attr,
	};
})();
