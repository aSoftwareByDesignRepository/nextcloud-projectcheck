/**
 * Employees JavaScript for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function () {
	'use strict';

	console.log('🚀 EMPLOYEES JS LOADED');

	// DOM elements
	const elements = {
		searchInput: document.getElementById('employee-search'),
		clearFiltersBtn: document.getElementById('clear-filters'),
		clearSearchInlineBtn: document.getElementById('clear-search-inline'),
		employeesTbody: document.getElementById('employees-tbody')
	};

	/**
	 * Initialize the application
	 */
	function init() {
		console.log('Employees app initializing...');
		bindEvents();
		console.log('Employees app initialized');
	}

	/**
	 * Bind event listeners
	 */
	function bindEvents() {
		console.log('Binding events...');

		// Search input - real-time filtering
		if (elements.searchInput) {
			elements.searchInput.addEventListener('input', function() {
				applySearch();
			});

			// Enter key should also work
			elements.searchInput.addEventListener('keypress', function(e) {
				if (e.key === 'Enter') {
					applySearch();
				}
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
		const searchTerm = elements.searchInput ? elements.searchInput.value.toLowerCase() : '';

		console.log('=== APPLYING SEARCH ===');
		console.log('Search term:', searchTerm);

		const rows = elements.employeesTbody ? elements.employeesTbody.querySelectorAll('tr') : [];
		console.log('Total rows:', rows.length);
		
		let visibleCount = 0;

		rows.forEach(row => {
			// Skip the no-results row
			if (row.id === 'no-results-row') {
				return;
			}

			let showRow = true;

			// Search filter - search in employee name
			if (searchTerm && showRow) {
				const employeeName = row.getAttribute('data-employee-name') || '';
				const employeeId = row.getAttribute('data-employee-id') || '';
				
				if (!employeeName.includes(searchTerm) && !employeeId.toLowerCase().includes(searchTerm)) {
					showRow = false;
				}
			}

			// Show/hide row
			row.style.display = showRow ? '' : 'none';
			
			if (showRow) {
				visibleCount++;
			}
		});

		console.log('Visible rows after search:', visibleCount);
		updateEmptyState();
	}

	/**
	 * Clear search and show all rows
	 */
	function clearSearch() {
		console.log('=== CLEARING SEARCH ===');
		
		if (elements.searchInput) {
			elements.searchInput.value = '';
		}

		// Show all rows except the no-results row
		const rows = elements.employeesTbody ? elements.employeesTbody.querySelectorAll('tr') : [];
		rows.forEach(row => {
			if (row.id !== 'no-results-row') {
				row.style.display = '';
			}
		});

		console.log('Search cleared, showing all rows');
		updateEmptyState();
	}

	/**
	 * Update empty state visibility
	 */
	function updateEmptyState() {
		// Get all rows except the no-results row
		const allRows = elements.employeesTbody ?
			Array.from(elements.employeesTbody.querySelectorAll('tr')).filter(row =>
				row.id !== 'no-results-row'
			) : [];
		
		const visibleRows = allRows.filter(row => row.style.display !== 'none');
		const noResultsRow = document.getElementById('no-results-row');

		console.log('updateEmptyState: Total rows:', allRows.length, 'Visible rows:', visibleRows.length);

		// Show no-results row if there are rows but none are visible after filtering
		if (noResultsRow) {
			if (allRows.length > 0 && visibleRows.length === 0) {
				noResultsRow.style.display = '';
				console.log('Showing no-results row');
			} else {
				noResultsRow.style.display = 'none';
				console.log('Hiding no-results row');
			}
		}
	}

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

})();
