/**
 * Time Entries Management JavaScript for the projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function () {
	'use strict';
	
	function normalizeDateToIso(dateString) {
		if (!dateString) {
			return '';
		}
		const s = String(dateString).trim();
		if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
			return s;
		}
		if (/^\d{2}\.\d{2}\.\d{4}$/.test(s)) {
			const parts = s.split('.');
			return `${parts[2]}-${parts[1]}-${parts[0]}`;
		}
		return '';
	}

	// DOM elements - NO AUTOMATIC EVENT LISTENERS
	const elements = {
		searchInput: document.getElementById('time-entry-search'),
		projectFilter: document.getElementById('project-filter'),
		userFilter: document.getElementById('user-filter'),
		projectTypeFilter: document.getElementById('time-entry-project-type-filter'),
		applyFiltersBtn: document.getElementById('apply-filters'),
		clearFiltersBtn: document.getElementById('clear-filters'),
		exportCsvBtn: document.getElementById('export-csv'),
		timeEntriesTable: document.getElementById('time-entries-table'),
		timeEntriesTbody: document.querySelector('#time-entries-table tbody')
	};

	const HOUR_SCALE = 10000;

	function parseHoursValue(value) {
		const n = parseFloat(value);
		return Number.isFinite(n) ? n : 0;
	}

	function roundHours(value) {
		return Math.round(value * HOUR_SCALE) / HOUR_SCALE;
	}

	function subtractHours(a, b) {
		return roundHours(Math.max(0, parseHoursValue(a) - parseHoursValue(b)));
	}

	function formatHoursDisplay(hours) {
		if (typeof window.ProjectCheckFormat !== 'undefined' && typeof window.ProjectCheckFormat.hours === 'function') {
			return window.ProjectCheckFormat.hours(hours);
		}
		return parseHoursValue(hours).toFixed(2) + '\u00A0h';
	}

	function formatMatchingEntryCount(count) {
		const safeCount = Math.max(0, parseInt(String(count), 10) || 0);
		return n('projectcheck', '%n matching entry', '%n matching entries', safeCount);
	}

	/**
	 * Initialize the application
	 */
	function init() {
		bindEvents();
		
		initMessageAutoHide();
	}

	/**
	 * Initialize mutation observer to handle dynamic content
	 */
	function _initMutationObserver() {
		const observer = new MutationObserver(function (mutations) {
			mutations.forEach(function (mutation) {
				if (mutation.type === 'childList') {
					// Check for new description cells
					mutation.addedNodes.forEach(function (node) {
						if (node.nodeType === 1) { // Element node
							const descriptionCells = node.querySelectorAll ?
								node.querySelectorAll('.grid .description-cell, .grid td:nth-child(6)') : [];
							descriptionCells.forEach(cell => {
								if (!cell.hasAttribute('data-processed')) {
									const text = cell.textContent.trim();
									if (text.length > 20) {
										cell.setAttribute('data-original-text', text);
										cell.setAttribute('title', text);
										cell.style.cursor = 'help';
										cell.textContent = text.substring(0, 20) + '...';
										cell.setAttribute('data-processed', 'true');

										cell.addEventListener('click', function (e) {
											e.preventDefault();
											showFullDescription(text, cell);
										});
									}
								}
							});
						}
					});
				}
			});
		});

		// Start observing
		const targetNode = document.querySelector('.grid-container') || document.body;
		observer.observe(targetNode, {
			childList: true,
			subtree: true
		});
	}

	/**
	 * Bind event listeners
	 */
	function bindEvents() {
		// Apply filters button (navigate with query params)
		if (elements.applyFiltersBtn) {
			elements.applyFiltersBtn.addEventListener('click', applyFilters);
		}

		// Enter key in search field should also apply filters
		if (elements.searchInput) {
			elements.searchInput.addEventListener('keypress', function(e) {
				if (e.key === 'Enter') {
					applyFilters();
				}
			});
		}

		// Clear filters
		if (elements.clearFiltersBtn) {
			elements.clearFiltersBtn.addEventListener('click', clearFilters);
		}

		// Clear filters inline button (in no-results message)
		const clearFiltersInlineBtn = document.getElementById('clear-filters-inline');
		if (clearFiltersInlineBtn) {
			clearFiltersInlineBtn.addEventListener('click', clearFilters);
		}

		// Export CSV button
		if (elements.exportCsvBtn) {
			elements.exportCsvBtn.addEventListener('click', exportToCsv);
		}

		// Delete time entry buttons
		document.addEventListener('click', function (e) {
			if (e.target.closest('.delete-entry-btn')) {
				const button = e.target.closest('.delete-entry-btn');
				const entryId = button.getAttribute('data-entry-id');
				const entryDescription = button.getAttribute('data-entry-description');
				showTimeEntryDeletionModal(entryId, entryDescription);
			}
		});
	}



	/**
	 * Apply filters by navigating with query params (server-side paging)
	 */
	function applyFilters() {
		const searchTerm = elements.searchInput ? elements.searchInput.value : '';
		const projectFilter = elements.projectFilter ? elements.projectFilter.value : '';
		const userFilter = elements.userFilter ? elements.userFilter.value : '';
		const projectTypeFilter = elements.projectTypeFilter ? elements.projectTypeFilter.value : '';
		const dateFromInput = document.getElementById('date-from-filter');
		const dateToInput = document.getElementById('date-to-filter');
		let dateFrom = dateFromInput ? dateFromInput.value.trim() : '';
		let dateTo = dateToInput ? dateToInput.value.trim() : '';

		if (dateFrom) {
			dateFrom = normalizeDateToIso(dateFrom);
		}
		if (dateTo) {
			dateTo = normalizeDateToIso(dateTo);
		}

		const url = new URL(window.location.href);
		searchTerm ? url.searchParams.set('search', searchTerm) : url.searchParams.delete('search');
		projectFilter ? url.searchParams.set('project_id', projectFilter) : url.searchParams.delete('project_id');
		userFilter ? url.searchParams.set('user_id', userFilter) : url.searchParams.delete('user_id');
		projectTypeFilter ? url.searchParams.set('project_type', projectTypeFilter) : url.searchParams.delete('project_type');
		dateFrom ? url.searchParams.set('date_from', dateFrom) : url.searchParams.delete('date_from');
		dateTo ? url.searchParams.set('date_to', dateTo) : url.searchParams.delete('date_to');
		url.searchParams.set('page', '1'); // reset to first page on new filter set
		window.location.href = url.toString();
	}


	/**
	 * Clear all filters and reload
	 */
	function clearFilters() {
		const url = new URL(window.location.href);
		url.searchParams.delete('search');
		url.searchParams.delete('project_id');
		url.searchParams.delete('user_id');
		url.searchParams.delete('project_type');
		url.searchParams.delete('date_from');
		url.searchParams.delete('date_to');
		url.searchParams.set('page', '1');
		window.location.href = url.toString();
	}

	/**
	 * Update empty state visibility
	 */
	function updateEmptyState() {
		const table = elements.timeEntriesTable;
		if (!table) {
			return;
		}
		const remaining = table.querySelectorAll('tbody tr[data-entry-id]').length;
		if (remaining === 0) {
			window.location.reload();
		}
	}

	/**
	 * Adjust footer totals after a row is removed (same precision as server data attributes).
	 */
	function updateHoursSummaryAfterDelete(deletedHours) {
		const table = elements.timeEntriesTable;
		if (!table || deletedHours <= 0) {
			return;
		}

		const selectionHours = subtractHours(
			table.getAttribute('data-selection-hours'),
			deletedHours
		);
		const pageHours = subtractHours(
			table.getAttribute('data-page-hours'),
			deletedHours
		);
		let selectionCount = Math.max(0, (parseInt(table.getAttribute('data-selection-count'), 10) || 0) - 1);
		let pageCount = Math.max(0, (parseInt(table.getAttribute('data-page-count'), 10) || 0) - 1);

		table.setAttribute('data-selection-hours', String(selectionHours));
		table.setAttribute('data-page-hours', String(pageHours));
		table.setAttribute('data-selection-count', String(selectionCount));
		table.setAttribute('data-page-count', String(pageCount));

		const selectionHoursEl = document.getElementById('time-entries-selection-hours');
		const pageHoursEl = document.getElementById('time-entries-page-hours');
		const metaEl = document.getElementById('time-entries-selection-meta');
		const liveEl = document.getElementById('time-entries-summary-live');

		if (selectionHoursEl) {
			selectionHoursEl.textContent = formatHoursDisplay(selectionHours);
		}
		if (pageHoursEl) {
			pageHoursEl.textContent = formatHoursDisplay(pageHours);
		}
		if (metaEl) {
			const metaPage = metaEl.querySelector('.time-entries-summary__meta-page');
			let metaHtml = formatMatchingEntryCount(selectionCount);
			if (metaPage) {
				metaHtml += '<span class="time-entries-summary__meta-sep" aria-hidden="true"> · </span>';
				metaHtml += metaPage.outerHTML;
			}
			metaEl.innerHTML = metaHtml;
		}
		if (liveEl) {
			liveEl.textContent = t('projectcheck', 'Total hours (matching filters)') + ': ' + formatHoursDisplay(selectionHours);
		}
	}

	/**
	 * Show time entry deletion modal
	 */
	function showTimeEntryDeletionModal(entryId, entryDescription) {
		if (typeof window.projectcheckDeletionModal === 'undefined') {
			showMessage(t('projectcheck', 'Could not open the confirmation dialog. Reload the page and try again.'), 'error');
			return;
		}

		const deleteUrl = OC.generateUrl('/apps/projectcheck/time-entries/{id}/delete', { id: entryId });

		// Show the modal
		window.projectcheckDeletionModal.show({
			entityType: 'time_entry',
			entityId: entryId,
			entityName: entryDescription || t('projectcheck', 'Time entry'),
			deleteUrl: deleteUrl,
			onSuccess: function (entity) {
				const row = document.querySelector(`tr[data-entry-id="${entity.id}"]`);
				if (row) {
					const deletedHours = parseHoursValue(row.getAttribute('data-entry-hours'));
					row.remove();
					updateHoursSummaryAfterDelete(deletedHours);
					updateEmptyState();
				}

				showMessage(t('projectcheck', 'Time entry was deleted successfully!'), 'success');
			},
			onCancel: function () {
			}
		});
	}

	/**
	 * Show message
	 */
	function showMessage(message, type) {
		const level = type || 'info';
		// Remove existing messages
		const existingMessages = document.querySelectorAll('.notice');
		existingMessages.forEach(msg => msg.remove());

		// Create new message
		const messageDiv = document.createElement('div');
		messageDiv.className = 'notice notice-' + level;
		messageDiv.setAttribute('role', level === 'error' ? 'alert' : 'status');
		messageDiv.setAttribute('aria-live', level === 'error' ? 'assertive' : 'polite');
		messageDiv.setAttribute('aria-atomic', 'true');

		// Choose appropriate icon based on message type
		let iconClass = 'icon-info';
		if (level === 'success') {
			iconClass = 'icon-checkmark';
		} else if (level === 'error') {
			iconClass = 'icon-error';
		}

		// Create icon element
		const icon = document.createElement('i');
		icon.className = 'icon ' + iconClass;
		icon.setAttribute('aria-hidden', 'true');
		messageDiv.appendChild(icon);

		// Create message span
		const messageSpan = document.createElement('span');
		messageSpan.textContent = message;
		messageDiv.appendChild(messageSpan);

		// Insert after header
		const header = document.querySelector('.header-content');
		if (header && header.parentNode) {
			header.parentNode.insertBefore(messageDiv, header.nextSibling);
		}

		// Auto-hide after 3 seconds for info messages, 5 seconds for others
		const hideDelay = level === 'info' ? 3000 : 5000;
		setTimeout(() => {
			if (messageDiv.parentNode) {
				messageDiv.remove();
			}
		}, hideDelay);
	}

	/**
	 * Initialize date input formatting for European format (dd.mm.yyyy)
	 */
	/**
	 * Export ALL filtered time entries to CSV via backend API
	 */
	function exportToCsv() {
		// Get current filter values from the form
		const searchTerm = elements.searchInput ? elements.searchInput.value.trim() : '';
		const projectFilter = elements.projectFilter ? elements.projectFilter.value : '';
		const userFilter = elements.userFilter ? elements.userFilter.value : '';
		const projectTypeFilter = elements.projectTypeFilter ? elements.projectTypeFilter.value : '';
		const dateFromInput = document.getElementById('date-from-filter');
		const dateToInput = document.getElementById('date-to-filter');
		let dateFrom = dateFromInput ? dateFromInput.value.trim() : '';
		let dateTo = dateToInput ? dateToInput.value.trim() : '';

		if (dateFrom) {
			dateFrom = normalizeDateToIso(dateFrom);
		}
		if (dateTo) {
			dateTo = normalizeDateToIso(dateTo);
		}

		// Build export URL with current filters
		const exportUrl = OC.generateUrl('/apps/projectcheck/time-entries/export');
		const url = new URL(exportUrl, window.location.origin);
		
		// Add filter parameters
		if (searchTerm) url.searchParams.set('search', searchTerm);
		if (projectFilter) url.searchParams.set('project_id', projectFilter);
		if (userFilter) url.searchParams.set('user_id', userFilter);
		if (projectTypeFilter) url.searchParams.set('project_type', projectTypeFilter);
		if (dateFrom) url.searchParams.set('date_from', dateFrom);
		if (dateTo) url.searchParams.set('date_to', dateTo);

		// Show loading state
		const exportBtn = elements.exportCsvBtn;
		const originalText = exportBtn ? exportBtn.textContent : '';
		if (exportBtn) {
			exportBtn.disabled = true;
			exportBtn.textContent = t('projectcheck', 'Exporting…');
		}

		// Fetch CSV data from backend
		fetch(url.toString(), {
			method: 'GET',
			headers: {
				'requesttoken': OC.requestToken
			}
		})
		.then(response => {
			if (!response.ok) {
				return response.json().then(data => {
					throw new Error(data.error != null && data.error !== ''
						? data.error
						: t('projectcheck', 'Export failed'));
				});
			}
			return response.json();
		})
		.then(data => {
			if (data.error) {
				throw new Error(data.error);
			}

			// Create blob from CSV data
			const bom = '\uFEFF'; // BOM for UTF-8
			const blob = new Blob([bom + data.csv_data], { type: 'text/csv;charset=utf-8;' });
			
			// Create download link
			const link = document.createElement('a');
			const downloadUrl = URL.createObjectURL(blob);
			const filename = data.filename || 'time_entries_' + new Date().toISOString().slice(0, 10) + '.csv';
			
			link.setAttribute('href', downloadUrl);
			link.setAttribute('download', filename);
			link.style.visibility = 'hidden';
			document.body.appendChild(link);
			link.click();
			document.body.removeChild(link);
			
			// Clean up
			URL.revokeObjectURL(downloadUrl);
			
			// Count exported entries (approximate from CSV lines - 1 for header)
			const entryCount = (data.csv_data.match(/\n/g) || []).length;
			showMessage(t('projectcheck', 'Exported') + ' ' + entryCount + ' ' + t('projectcheck', 'entries'), 'success');
		})
		.catch(error => {
			console.error('Export error:', error);
			showMessage(t('projectcheck', 'Export failed:') + ' ' + error.message, 'error');
		})
		.finally(() => {
			// Restore button state
			if (exportBtn) {
				exportBtn.disabled = false;
				exportBtn.textContent = originalText;
			}
		});
	}

	/**
	 * Initialize message auto-hide
	 */
	function initMessageAutoHide() {
		const messages = document.querySelectorAll('.notice');
		messages.forEach(message => {
			setTimeout(() => {
				if (message.parentNode) {
					message.remove();
				}
			}, 5000);
		});
	}

	/**
	 * Initialize description truncation for table cells
	 */
	function _initDescriptionTruncation() {
		// Run immediately to prevent flash of full text
		const descriptionCells = document.querySelectorAll('.grid .description-cell, .grid td:nth-child(6)');
		const maxLength = 20; // Maximum characters before truncation

		descriptionCells.forEach(cell => {
			// Skip if already processed
			if (cell.hasAttribute('data-processed')) {
				return;
			}

			const originalText = cell.textContent.trim();

			if (originalText.length > maxLength) {
				// Store original text in data attribute BEFORE truncating
				cell.setAttribute('data-original-text', originalText);

				// Truncate the text immediately
				const truncatedText = originalText.substring(0, maxLength) + '...';
				cell.textContent = truncatedText;

				// Add tooltip with full text
				cell.setAttribute('title', originalText);
				cell.style.cursor = 'help';

				// Mark as processed
				cell.setAttribute('data-processed', 'true');

				// Add click handler to show full text
				cell.addEventListener('click', function (e) {
					e.preventDefault();
					showFullDescription(originalText, cell); // Use original full text
				});
			}
		});
	}

	/**
	 * Force immediate truncation on page load
	 */
	function _forceImmediateTruncation() {
		// Run as soon as possible
		const descriptionCells = document.querySelectorAll('.grid .description-cell, .grid td:nth-child(6)');
		descriptionCells.forEach(cell => {
			// Skip if already processed
			if (cell.hasAttribute('data-processed')) {
				return;
			}

			const text = cell.textContent.trim();
			if (text.length > 20) {
				// Store original text BEFORE truncating
				cell.setAttribute('data-original-text', text);
				cell.setAttribute('title', text);
				cell.style.cursor = 'help';

				// Truncate for display
				cell.textContent = text.substring(0, 20) + '...';

				// Mark as processed to prevent duplicate event listeners
				cell.setAttribute('data-processed', 'true');

				// Add click handler to show full text
				cell.addEventListener('click', function (e) {
					e.preventDefault();
					showFullDescription(text, cell); // Use original full text
				});
			}
		});
	}

	/**
	 * Additional truncation check for dynamic content
	 */
	function _checkAndTruncateDescriptions() {
		const descriptionCells = document.querySelectorAll('.grid .description-cell, .grid td:nth-child(6)');
		descriptionCells.forEach(cell => {
			if (!cell.hasAttribute('data-processed')) {
				const text = cell.textContent.trim();
				if (text.length > 20) {
					cell.setAttribute('data-original-text', text);
					cell.setAttribute('title', text);
					cell.style.cursor = 'help';
					cell.textContent = text.substring(0, 20) + '...';
					cell.setAttribute('data-processed', 'true');

					cell.addEventListener('click', function (e) {
						e.preventDefault();
						showFullDescription(text, cell);
					});
				}
			}
		});
	}

	/**
	 * Show full description in a modal or popup
	 */
	function showFullDescription(text, cell) {
		// Create a simple popup
		const popup = document.createElement('div');
		popup.style.cssText = `
			position: fixed;
			top: 50%;
			left: 50%;
			transform: translate(-50%, -50%);
			background: #ffffff;
			background-color: var(--color-background, #ffffff) !important;
			border: 1px solid var(--color-border, #cccccc);
			border-radius: 8px;
			padding: 1rem;
			box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
			z-index: 1000;
			max-width: 500px;
			max-height: 300px;
			overflow-y: auto;
			word-wrap: break-word;
			opacity: 1;
			/* Ensure content is not truncated */
			text-overflow: unset;
			white-space: normal;
		`;

	// Create close button
	const closeButton = document.createElement('button');
	closeButton.textContent = '×';
	closeButton.style.cssText = `
		background: none; 
		border: none; 
		font-size: 1.5rem; 
		cursor: pointer; 
		color: var(--color-text, #000000);
		padding: 0;
		margin: 0;
		line-height: 1;
	`;

		// Create header
		const header = document.createElement('div');
		header.style.cssText = `
			display: flex; 
			justify-content: space-between; 
			align-items: center; 
			margin-bottom: 0.5rem; 
			background: transparent;
		`;

		const title = document.createElement('h3');
		title.textContent = t('projectcheck', 'Full Description');
		title.style.cssText = `
			margin: 0; 
			color: var(--color-text, #000000); 
			background: transparent;
		`;

		header.appendChild(title);
		header.appendChild(closeButton);

		// Create content
		const content = document.createElement('p');
		content.textContent = text;
		content.style.cssText = `
			margin: 0; 
			color: var(--color-text, #000000); 
			white-space: pre-wrap; 
			background: transparent;
			word-wrap: break-word;
			word-break: break-word;
			overflow-wrap: break-word;
			/* Ensure full text is displayed without truncation */
			text-overflow: unset;
			overflow: visible;
			max-width: none;
		`;

		// Assemble popup
		popup.appendChild(header);
		popup.appendChild(content);

		// Add backdrop
		const backdrop = document.createElement('div');
		backdrop.style.cssText = `
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: rgba(0, 0, 0, 0.5);
			z-index: 999;
		`;

		// Close function
		function closePopup() {
			popup.remove();
			backdrop.remove();
		}

		// Add event listeners
		closeButton.addEventListener('click', closePopup);
		backdrop.addEventListener('click', closePopup);

		// Add keyboard support
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				closePopup();
			}
		});

		document.body.appendChild(backdrop);
		document.body.appendChild(popup);
	}


	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

})();
