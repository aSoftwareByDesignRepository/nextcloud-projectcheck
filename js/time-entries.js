/**
 * Time Entries Management JavaScript for the projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function () {
	'use strict';

	// DOM elements - NO AUTOMATIC EVENT LISTENERS
	const elements = {
		searchInput: document.getElementById('time-entry-search'),
		projectFilter: document.getElementById('project-filter'),
		userFilter: document.getElementById('user-filter'),
		projectTypeFilter: document.getElementById('time-entry-project-type-filter'),
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
				console.log('Delete button clicked for time entry:', entryId, entryDescription);
				showTimeEntryDeletionModal(entryId, entryDescription);
			}
		});
	}


	/**
	 * Convert European date format (dd.mm.yyyy) to ISO format (yyyy-mm-dd) for backend
	 */
	function convertEuropeanDateToISO(dateString) {
		if (!dateString || !/^\d{2}\.\d{2}\.\d{4}$/.test(dateString)) {
			return dateString; // Return as-is if not European format
		}
		const parts = dateString.split('.');
		return `${parts[2]}-${parts[1]}-${parts[0]}`;
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

		// Convert European format (dd.mm.yyyy) to ISO format (yyyy-mm-dd) for backend
		if (dateFrom) {
			dateFrom = convertEuropeanDateToISO(dateFrom);
		}
		if (dateTo) {
			dateTo = convertEuropeanDateToISO(dateTo);
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
		console.log('=== CLEARING ALL FILTERS ===');
		
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
		// For server-side paging the no-results row is controlled on render; nothing to do.
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

	/**
	 * Initialize date input formatting for European format (dd.mm.yyyy)
	 */
	function initCalendarIcons() {
		const dateFromInput = document.getElementById('date-from-filter');
		const dateToInput = document.getElementById('date-to-filter');

		if (dateFromInput) {
			initializeDateInput(dateFromInput);
		}
		if (dateToInput) {
			initializeDateInput(dateToInput);
		}
	}

	/**
	 * Initialize date input with European format (dd.mm.yyyy)
	 */
	function initializeDateInput(input) {
		// Auto-format as user types
		input.addEventListener('input', function() {
			let value = this.value.replace(/\D/g, ''); // Remove non-digits

			if (value.length >= 2) {
				value = value.substring(0, 2) + '.' + value.substring(2);
			}
			if (value.length >= 5) {
				value = value.substring(0, 5) + '.' + value.substring(5, 9);
			}

			this.value = value;
		});

		// Validate on blur
		input.addEventListener('blur', function() {
			if (this.value && this.value.length === 10) {
				const dateRegex = /^(\d{2})\.(\d{2})\.(\d{4})$/;
				const match = this.value.match(dateRegex);

				if (match) {
					const day = parseInt(match[1], 10);
					const month = parseInt(match[2], 10);
					const year = parseInt(match[3], 10);

					// Basic validation
					if (day < 1 || day > 31 || month < 1 || month > 12 || year < 1900 || year > 2100) {
						this.setCustomValidity(t('projectcheck', 'Please enter a valid date'));
					} else {
						const date = new Date(year, month - 1, day);
						if (isNaN(date.getTime()) || date.getDate() !== day || date.getMonth() !== (month - 1) || date.getFullYear() !== year) {
							this.setCustomValidity(t('projectcheck', 'Invalid date'));
						} else {
							this.setCustomValidity('');
						}
					}
				} else {
					this.setCustomValidity(t('projectcheck', 'Please enter date in format dd.mm.yyyy'));
				}
			} else if (this.value) {
				this.setCustomValidity(t('projectcheck', 'Please enter date in format dd.mm.yyyy'));
			} else {
				this.setCustomValidity('');
			}
		});
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
