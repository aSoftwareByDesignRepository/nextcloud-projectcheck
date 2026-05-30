/**
 * Employees page progressive enhancement for the projectcheck app.
 *
 * The employee search is a plain GET <form> and works fully without
 * JavaScript. This module only refines the experience:
 *   - trims the query so stray whitespace never triggers a "no results" view;
 *   - removes the empty `search` param so the URL stays clean;
 *   - always resets to page 1 on a new search.
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function () {
	'use strict';

	function init() {
		const form = document.querySelector('.employees-search-form');
		const input = document.getElementById('employee-search');
		if (!form || !input) {
			return;
		}

		form.addEventListener('submit', function (event) {
			const term = (input.value || '').trim();
			input.value = term;

			// Build a clean target URL so an empty search doesn't leave a
			// dangling `?search=` and a new search always starts on page 1.
			const url = new URL(form.action || window.location.href, window.location.origin);
			if (term === '') {
				url.searchParams.delete('search');
			} else {
				url.searchParams.set('search', term);
			}
			url.searchParams.delete('page');

			event.preventDefault();
			window.location.href = url.toString();
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
