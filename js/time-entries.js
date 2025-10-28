/**
 * Time Entries Management JavaScript for the projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function () {
	'use strict';

	// Global variables
	let searchTimeout = null;

	// DOM elements
	const elements = {
		searchInput: document.getElementById('time-entry-search'),
		projectFilter: document.getElementById('project-filter'),
		userFilter: document.getElementById('user-filter'),
		projectTypeFilter: document.getElementById('project-type-filter'),
		applyFiltersBtn: document.getElementById('apply-filters'),
		clearFiltersBtn: document.getElementById('clear-filters'),
		exportCsvBtn: document.getElementById('export-csv'),
		timeEntriesTable: document.querySelector('.grid'),
		timeEntriesTbody: document.querySelector('.grid tbody')
	};

	/**
	 * Initialize the application
	 */
	function init() {
		console.log('TimeEntries app initializing...');
		// Run immediate truncation first to prevent flash
		forceImmediateTruncation();
		bindEvents();
		initCalendarIcons();
		initMessageAutoHide();
		initMutationObserver();
		console.log('TimeEntries app initialized');
	}

	/**
	 * Initialize mutation observer to handle dynamic content
	 */
	function initMutationObserver() {
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
		console.log('Binding events...');

		// Apply filters button (manual filtering)
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
				console.log('Delete button clicked for time entry:', entryId, entryDescription);
				showTimeEntryDeletionModal(entryId, entryDescription);
			}
		});
	}


	/**
	 * Apply all filters - SIMPLIFIED with data attributes
	 */
	function applyFilters() {
		const searchTerm = elements.searchInput ? elements.searchInput.value.toLowerCase() : '';
		const projectFilter = elements.projectFilter ? elements.projectFilter.value : '';
		const userFilter = elements.userFilter ? elements.userFilter.value : '';
		const projectTypeFilter = elements.projectTypeFilter ? elements.projectTypeFilter.value : '';
		const dateFromInput = document.getElementById('date-from-filter');
		const dateToInput = document.getElementById('date-to-filter');
		const dateFrom = dateFromInput ? dateFromInput.value : '';
		const dateTo = dateToInput ? dateToInput.value : '';

		console.log('=== APPLYING FILTERS ===');
		console.log('Filters:', { searchTerm, projectFilter, userFilter, projectTypeFilter, dateFrom, dateTo });

		const rows = elements.timeEntriesTbody ? elements.timeEntriesTbody.querySelectorAll('tr') : [];
		console.log('Total rows:', rows.length);
		
		let visibleCount = 0;

		rows.forEach(row => {
			let showRow = true;

			// Project filter - use data attribute
			if (projectFilter && showRow) {
				const rowProjectId = row.getAttribute('data-project-id');
				if (rowProjectId !== projectFilter) {
					showRow = false;
				}
			}

			// User filter - use data attribute
			if (userFilter && showRow) {
				const rowUserId = row.getAttribute('data-user-id');
				if (rowUserId !== userFilter) {
					showRow = false;
				}
			}

			// Project type filter - use data attribute
			if (projectTypeFilter && showRow) {
				const rowProjectType = row.getAttribute('data-project-type');
				if (rowProjectType !== projectTypeFilter) {
					showRow = false;
				}
			}

			// Date from filter - use data attribute
			if (dateFrom && showRow) {
				const rowDateISO = row.getAttribute('data-date-iso');
				if (rowDateISO && rowDateISO < dateFrom) {
					showRow = false;
				}
			}

			// Date to filter - use data attribute
			if (dateTo && showRow) {
				const rowDateISO = row.getAttribute('data-date-iso');
				if (rowDateISO && rowDateISO > dateTo) {
					showRow = false;
				}
			}

			// Search filter - search in visible text
			if (searchTerm && showRow) {
				const description = row.querySelector('td:nth-child(7)')?.textContent.toLowerCase() || '';
				const project = row.querySelector('td:nth-child(2)')?.textContent.toLowerCase() || '';
				const customer = row.querySelector('td:nth-child(4)')?.textContent.toLowerCase() || '';

				if (!description.includes(searchTerm) &&
					!project.includes(searchTerm) &&
					!customer.includes(searchTerm)) {
					showRow = false;
				}
			}

			// Show/hide row
			row.style.display = showRow ? '' : 'none';
			
			if (showRow) {
				visibleCount++;
			}
		});

		console.log('Visible rows after filtering:', visibleCount);
		updateEmptyState();
	}


	/**
	 * Clear all filters and show all rows
	 */
	function clearFilters() {
		console.log('=== CLEARING ALL FILTERS ===');
		
		if (elements.searchInput) {
			elements.searchInput.value = '';
		}
		if (elements.projectFilter) {
			elements.projectFilter.value = '';
		}
		if (elements.userFilter) {
			elements.userFilter.value = '';
		}
		if (elements.projectTypeFilter) {
			elements.projectTypeFilter.value = '';
		}
		const dateFromInput = document.getElementById('date-from-filter');
		const dateToInput = document.getElementById('date-to-filter');
		if (dateFromInput) {
			dateFromInput.value = '';
		}
		if (dateToInput) {
			dateToInput.value = '';
		}

		// Show all rows
		const rows = elements.timeEntriesTbody ? elements.timeEntriesTbody.querySelectorAll('tr') : [];
		rows.forEach(row => {
			row.style.display = '';
		});

		console.log('All filters cleared, showing all', rows.length, 'rows');
		updateEmptyState();
	}

	/**
	 * Update empty state visibility
	 */
	function updateEmptyState() {
		const visibleRows = elements.timeEntriesTbody ?
			Array.from(elements.timeEntriesTbody.querySelectorAll('tr')).filter(row =>
				row.style.display !== 'none'
			) : [];

		const emptyState = document.querySelector('.emptycontent');
		if (emptyState) {
			if (visibleRows.length === 0) {
				emptyState.style.display = 'block';
				emptyState.querySelector('h2').textContent = 'No time entries match your filters';
				emptyState.querySelector('p').textContent = 'Try adjusting your search criteria or clear the filters.';
			} else {
				emptyState.style.display = 'none';
			}
		}
	}

	/**
	 * Show time entry deletion modal
	 */
	function showTimeEntryDeletionModal(entryId, entryDescription) {
		if (typeof window.projectcheckDeletionModal === 'undefined') {
			console.error('Deletion modal not loaded');
			// Fallback to old method
			confirmDeleteTimeEntry(entryId, entryDescription);
			return;
		}

		const deleteUrl = `/index.php/apps/projectcheck/time-entries/${entryId}`;

		// Show the modal
		window.projectcheckDeletionModal.show({
			entityType: 'time_entry',
			entityId: entryId,
			entityName: entryDescription || 'Time Entry',
			deleteUrl: deleteUrl,
			onSuccess: function (entity) {
				// Remove the row from the table
				const row = document.querySelector(`tr[data-entry-id="${entity.id}"]`);
				if (row) {
					row.remove();
					updateEmptyState();
				}

				// Show success message
				showMessage('Time entry deleted successfully!', 'success');
			},
			onCancel: function () {
				console.log('Time entry deletion cancelled');
			}
		});
	}

	/**
	 * Confirm delete time entry - fallback method
	 */
	function confirmDeleteTimeEntry(entryId, entryDescription) {
		const description = entryDescription || 'this time entry';
		const message = `Are you sure you want to delete ${description}? This action cannot be undone.`;

		if (confirm(message)) {
			deleteTimeEntry(entryId);
		}
	}

	/**
	 * Delete time entry via AJAX
	 */
	function deleteTimeEntry(entryId) {
		const url = `/index.php/apps/projectcheck/time-entries/${entryId}`;
		const token = document.querySelector('input[name="requesttoken"]')?.value ||
			document.querySelector('meta[name="requesttoken"]')?.content ||
			(window.OC && window.OC.requestToken);

		fetch(url, {
			method: 'DELETE',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'requesttoken': token || '',
				'X-Requested-With': 'XMLHttpRequest'
			}
		})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					// Remove the row from the table
					const row = document.querySelector(`tr[data-entry-id="${entryId}"]`);
					if (row) {
						row.remove();
						updateEmptyState();
					}

					// Show success message
					showMessage('Time entry deleted successfully!', 'success');
				} else {
					showMessage(data.message || t('projectcheck', 'Failed to delete time entry'), 'error');
				}
			})
			.catch(error => {
				console.error('Error deleting time entry:', error);
				showMessage('An error occurred while deleting the time entry', 'error');
			});
	}

	/**
	 * Show message
	 */
	function showMessage(message, type) {
		// Remove existing messages
		const existingMessages = document.querySelectorAll('.notice');
		existingMessages.forEach(msg => msg.remove());

		// Create new message
		const messageDiv = document.createElement('div');
		messageDiv.className = `notice notice-${type}`;

		// Choose appropriate icon based on message type
		let iconClass = 'icon-info';
		if (type === 'success') {
			iconClass = 'icon-checkmark';
		} else if (type === 'error') {
			iconClass = 'icon-error';
		}

		// Create icon element
		const icon = document.createElement('i');
		icon.className = 'icon ' + iconClass;
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
		const hideDelay = type === 'info' ? 3000 : 5000;
		setTimeout(() => {
			if (messageDiv.parentNode) {
				messageDiv.remove();
			}
		}, hideDelay);
	}

	// HTML5 date inputs work natively - no custom date picker needed

	// Date conversion functions no longer needed with HTML5 date inputs

	// All date conversion functions removed - HTML5 date inputs handle this automatically

	// Date input formatting no longer needed with HTML5 date inputs

	// All date formatting and validation functions removed - HTML5 date inputs handle this automatically

	// No longer needed with HTML5 date inputs

	// Initialize date picker functionality
	function initCalendarIcons() {
		// Date pickers are ready, no automatic filtering
		// User must click "Apply Filters" button
		console.log('Date picker functionality initialized');
	}

	/**
	 * Export time entries to CSV - ONLY visible (filtered) rows
	 */
	function exportToCsv() {
		const rows = elements.timeEntriesTbody ? elements.timeEntriesTbody.querySelectorAll('tr') : [];
		const visibleRows = Array.from(rows).filter(row => row.style.display !== 'none');
		
		if (visibleRows.length === 0) {
			showMessage(t('projectcheck', 'No time entries to export'), 'error');
			return;
		}

		console.log('Exporting', visibleRows.length, 'visible entries');

		// Build CSV manually from visible rows
		const csvRows = [];
		
		// CSV Headers
		csvRows.push([
			t('projectcheck', 'Date'),
			t('projectcheck', 'Project'),
			t('projectcheck', 'Type'),
			t('projectcheck', 'Customer'),
			t('projectcheck', 'User'),
			t('projectcheck', 'Hours'),
			t('projectcheck', 'Description')
		]);

		// Extract data from visible rows
		visibleRows.forEach(row => {
			const cells = row.querySelectorAll('td');
			if (cells.length >= 7) {
				const rowData = [
					cells[0].textContent.trim(), // Date
					cells[1].textContent.trim(), // Project
					cells[2].querySelector('.project-type-icon')?.getAttribute('title') || cells[2].textContent.trim(), // Type
					cells[3].textContent.trim(), // Customer
					cells[4].textContent.trim(), // User
					cells[5].textContent.trim(), // Hours
					cells[6].getAttribute('data-original-text') || cells[6].textContent.trim() // Description (full text)
				];
				csvRows.push(rowData);
			}
		});

		// Convert to CSV format
		const csvContent = csvRows.map(row => 
			row.map(cell => {
				// Escape quotes and wrap in quotes if contains comma, quote, or newline
				const cellStr = String(cell).replace(/"/g, '""');
				return /[,"\n]/.test(cellStr) ? `"${cellStr}"` : cellStr;
			}).join(',')
		).join('\n');

		// Add BOM for proper UTF-8 encoding in Excel
		const bom = '\uFEFF';
		const blob = new Blob([bom + csvContent], { type: 'text/csv;charset=utf-8;' });
		
		// Create download link
		const link = document.createElement('a');
		const url = URL.createObjectURL(blob);
		const filename = 'time_entries_filtered_' + new Date().toISOString().slice(0, 10) + '.csv';
		
		link.setAttribute('href', url);
		link.setAttribute('download', filename);
		link.style.visibility = 'hidden';
		document.body.appendChild(link);
		link.click();
		document.body.removeChild(link);
		
		// Clean up
		URL.revokeObjectURL(url);
		
		showMessage(t('projectcheck', 'Exported') + ' ' + visibleRows.length + ' ' + t('projectcheck', 'entries'), 'success');
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
	function initDescriptionTruncation() {
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
	function forceImmediateTruncation() {
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
	function checkAndTruncateDescriptions() {
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
		title.textContent = 'Full Description';
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
