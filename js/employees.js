/**
 * Employees JavaScript for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function () {
	'use strict';

	// DOM elements
	const elements = {
		searchInput: document.getElementById('employee-search'),
		clearFiltersBtn: document.getElementById('clear-filters'),
		clearSearchInlineBtn: document.getElementById('clear-search-inline'),
		applyFiltersBtn: document.getElementById('apply-filters'),
		employeesTbody: document.getElementById('employees-tbody')
	};

	/**
	 * Initialize the application
	 */
	function init() {
		bindEvents();
	}

	/**
	 * Bind event listeners
	 */
	function bindEvents() {
		// Search input - apply on Enter
		if (elements.searchInput) {
			elements.searchInput.addEventListener('keydown', function(e) {
				if (e.key === 'Enter') {
					applySearch();
				}
			});
		}

		// Apply button
		if (elements.applyFiltersBtn) {
			elements.applyFiltersBtn.addEventListener('click', function(e) {
				e.preventDefault();
				applySearch();
			});
		}

		// Clear search button
		if (elements.clearFiltersBtn) {
			elements.clearFiltersBtn.addEventListener('click', clearSearch);
		}

		// Clear search inline button (in no-results message)
		if (elements.clearSearchInlineBtn) {
			elements.clearSearchInlineBtn.addEventListener('click', clearSearch);
		}
	}

	/**
	 * Apply search filter
	 */
	function applySearch() {
		const searchTerm = elements.searchInput ? elements.searchInput.value : '';
		const url = new URL(window.location.href);
		searchTerm ? url.searchParams.set('search', searchTerm) : url.searchParams.delete('search');
		url.searchParams.set('page', '1');
		window.location.href = url.toString();
	}

	/**
	 * Clear search and show all rows
	 */
	function clearSearch() {
		if (elements.searchInput) {
			elements.searchInput.value = '';
		}

		const url = new URL(window.location.href);
		url.searchParams.delete('search');
		url.searchParams.set('page', '1');
		window.location.href = url.toString();
	}

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

})();
