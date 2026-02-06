/**
 * Time Entries Management JavaScript for the projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function () {
	'use strict';
	
	// Inline datepicker implementation (to avoid dependency on external script loading)
	// This ensures the datepicker works even if Nextcloud Core Scripts have CSP issues
	
	/**
	 * Convert European date format (dd.mm.yyyy) to ISO format (yyyy-mm-dd)
	 */
	function convertEuropeanToISO(dateString) {
		if (!dateString) return '';
		if (/^\d{2}\.\d{2}\.\d{4}$/.test(dateString)) {
			const parts = dateString.split('.');
			return `${parts[2]}-${parts[1]}-${parts[0]}`;
		}
		if (/^\d{4}-\d{2}-\d{2}$/.test(dateString)) {
			return dateString;
		}
		return dateString;
	}
	
	/**
	 * Initialize simple inline datepicker (CSP-compliant, no eval)
	 */
	function initializeInlineDatepicker(element, options = {}) {
		if (!element) return null;
		
		const monthNames = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 
			'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
		const dayNames = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];
		
		let selectedDate = null;
		let currentDate = new Date();
		let calendarElement = null;
		
		if (element.value && /^\d{2}\.\d{2}\.\d{4}$/.test(element.value)) {
			const parts = element.value.split('.');
			selectedDate = new Date(parseInt(parts[2], 10), parseInt(parts[1], 10) - 1, parseInt(parts[0], 10));
			currentDate = new Date(selectedDate);
		}
		currentDate.setHours(0, 0, 0, 0);
		
		function createCalendar() {
			const container = document.createElement('div');
			container.className = 'projectcheck-datepicker';
			container.style.cssText = 'position: absolute; z-index: 10000; background: var(--color-main-background); border: 1px solid var(--color-border); border-radius: 8px; padding: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 280px;';
			
			const header = document.createElement('div');
			header.style.cssText = 'display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;';
			
			const prevBtn = document.createElement('button');
			prevBtn.innerHTML = '‹';
			prevBtn.style.cssText = 'background: none; border: none; font-size: 20px; cursor: pointer; padding: 4px 8px; color: var(--color-main-text);';
			prevBtn.addEventListener('click', () => {
				currentDate.setMonth(currentDate.getMonth() - 1);
				renderCalendar();
			});
			
			const nextBtn = document.createElement('button');
			nextBtn.innerHTML = '›';
			nextBtn.style.cssText = 'background: none; border: none; font-size: 20px; cursor: pointer; padding: 4px 8px; color: var(--color-main-text);';
			nextBtn.addEventListener('click', () => {
				currentDate.setMonth(currentDate.getMonth() + 1);
				renderCalendar();
			});
			
			const monthYear = document.createElement('div');
			monthYear.style.cssText = 'font-weight: 600; color: var(--color-main-text);';
			
			header.appendChild(prevBtn);
			header.appendChild(monthYear);
			header.appendChild(nextBtn);
			
			const calendar = document.createElement('div');
			calendar.className = 'projectcheck-datepicker-calendar';
			
			container.appendChild(header);
			container.appendChild(calendar);
			
			function renderCalendar() {
				monthYear.textContent = monthNames[currentDate.getMonth()] + ' ' + currentDate.getFullYear();
				calendar.innerHTML = '';
				
				const dayHeader = document.createElement('div');
				dayHeader.style.cssText = 'display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; margin-bottom: 8px;';
				dayNames.forEach(day => {
					const dayCell = document.createElement('div');
					dayCell.textContent = day;
					dayCell.style.cssText = 'text-align: center; font-weight: 600; font-size: 12px; color: var(--color-text-maxcontrast); padding: 4px;';
					dayHeader.appendChild(dayCell);
				});
				calendar.appendChild(dayHeader);
				
				const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
				const startDate = new Date(firstDay);
				startDate.setDate(startDate.getDate() - (firstDay.getDay() || 7) + 1);
				
				const daysGrid = document.createElement('div');
				daysGrid.style.cssText = 'display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px;';
				
				const today = new Date();
				today.setHours(0, 0, 0, 0);
				
				for (let i = 0; i < 42; i++) {
					const date = new Date(startDate);
					date.setDate(startDate.getDate() + i);
					
					const dayCell = document.createElement('button');
					dayCell.textContent = date.getDate();
					dayCell.type = 'button';
					dayCell.style.cssText = 'padding: 8px; border: none; background: transparent; cursor: pointer; border-radius: 4px; color: var(--color-main-text);';
					
					if (date.getMonth() !== currentDate.getMonth()) {
						dayCell.style.opacity = '0.3';
					}
					
					if (date.getTime() === today.getTime()) {
						dayCell.style.fontWeight = 'bold';
						dayCell.style.background = 'var(--color-primary-element-light)';
					}
					
					if (selectedDate && date.getTime() === selectedDate.getTime()) {
						dayCell.style.background = 'var(--color-primary-element)';
						dayCell.style.color = 'var(--color-primary-element-text)';
					}
					
					if (options.maxDate && date > options.maxDate) {
						dayCell.style.opacity = '0.3';
						dayCell.style.cursor = 'not-allowed';
					} else {
						dayCell.addEventListener('click', () => {
							selectedDate = new Date(date);
							const day = String(selectedDate.getDate()).padStart(2, '0');
							const month = String(selectedDate.getMonth() + 1).padStart(2, '0');
							const year = selectedDate.getFullYear();
							element.value = `${day}.${month}.${year}`;
							element.dispatchEvent(new Event('change', { bubbles: true }));
							closeCalendar();
						});
						
						dayCell.addEventListener('mouseenter', () => {
							if (dayCell.style.background !== 'var(--color-primary-element)') {
								dayCell.style.background = 'var(--color-background-hover)';
							}
						});
						
						dayCell.addEventListener('mouseleave', () => {
							if (dayCell.style.background !== 'var(--color-primary-element)') {
								dayCell.style.background = 'transparent';
							}
						});
					}
					
					daysGrid.appendChild(dayCell);
				}
				
				calendar.appendChild(daysGrid);
			}
			
			function closeCalendar() {
				if (calendarElement && calendarElement.parentNode) {
					calendarElement.parentNode.removeChild(calendarElement);
					calendarElement = null;
				}
			}
			
			function openCalendar() {
				// If already open, do nothing (avoids focus+click both calling open and second call closing it)
				if (calendarElement && calendarElement.parentNode) {
					return;
				}
				
				calendarElement = container.cloneNode(true);
				document.body.appendChild(calendarElement);
				
				const rect = element.getBoundingClientRect();
				calendarElement.style.top = (rect.bottom + window.scrollY + 4) + 'px';
				calendarElement.style.left = (rect.left + window.scrollX) + 'px';
				
				const prevBtnClone = calendarElement.querySelector('button:first-child');
				const nextBtnClone = calendarElement.querySelector('button:last-child');
				
				prevBtnClone.addEventListener('click', () => {
					currentDate.setMonth(currentDate.getMonth() - 1);
					renderCalendarForElement(calendarElement);
				});
				
				nextBtnClone.addEventListener('click', () => {
					currentDate.setMonth(currentDate.getMonth() + 1);
					renderCalendarForElement(calendarElement);
				});
				
				function renderCalendarForElement(calEl) {
					const monthYearEl = calEl.querySelector('div:nth-child(2)');
					const calGrid = calEl.querySelector('.projectcheck-datepicker-calendar');
					monthYearEl.textContent = monthNames[currentDate.getMonth()] + ' ' + currentDate.getFullYear();
					calGrid.innerHTML = '';
					
					const dayHeader = document.createElement('div');
					dayHeader.style.cssText = 'display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; margin-bottom: 8px;';
					dayNames.forEach(day => {
						const dayCell = document.createElement('div');
						dayCell.textContent = day;
						dayCell.style.cssText = 'text-align: center; font-weight: 600; font-size: 12px; color: var(--color-text-maxcontrast); padding: 4px;';
						dayHeader.appendChild(dayCell);
					});
					calGrid.appendChild(dayHeader);
					
					const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
					const startDate = new Date(firstDay);
					startDate.setDate(startDate.getDate() - (firstDay.getDay() || 7) + 1);
					
					const daysGrid = document.createElement('div');
					daysGrid.style.cssText = 'display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px;';
					
					const today = new Date();
					today.setHours(0, 0, 0, 0);
					
					for (let i = 0; i < 42; i++) {
						const date = new Date(startDate);
						date.setDate(startDate.getDate() + i);
						
						const dayCell = document.createElement('button');
						dayCell.textContent = date.getDate();
						dayCell.type = 'button';
						dayCell.style.cssText = 'padding: 8px; border: none; background: transparent; cursor: pointer; border-radius: 4px; color: var(--color-main-text);';
						
						if (date.getMonth() !== currentDate.getMonth()) {
							dayCell.style.opacity = '0.3';
						}
						
						if (date.getTime() === today.getTime()) {
							dayCell.style.fontWeight = 'bold';
							dayCell.style.background = 'var(--color-primary-element-light)';
						}
						
						if (selectedDate && date.getTime() === selectedDate.getTime()) {
							dayCell.style.background = 'var(--color-primary-element)';
							dayCell.style.color = 'var(--color-primary-element-text)';
						}
						
						if (options.maxDate && date > options.maxDate) {
							dayCell.style.opacity = '0.3';
							dayCell.style.cursor = 'not-allowed';
						} else {
							dayCell.addEventListener('click', () => {
								selectedDate = new Date(date);
								const day = String(selectedDate.getDate()).padStart(2, '0');
								const month = String(selectedDate.getMonth() + 1).padStart(2, '0');
								const year = selectedDate.getFullYear();
								element.value = `${day}.${month}.${year}`;
								element.dispatchEvent(new Event('change', { bubbles: true }));
								closeCalendar();
							});
							
							dayCell.addEventListener('mouseenter', () => {
								if (dayCell.style.background !== 'var(--color-primary-element)') {
									dayCell.style.background = 'var(--color-background-hover)';
								}
							});
							
							dayCell.addEventListener('mouseleave', () => {
								if (dayCell.style.background !== 'var(--color-primary-element)') {
									dayCell.style.background = 'transparent';
								}
							});
						}
						
						daysGrid.appendChild(dayCell);
					}
					
					calGrid.appendChild(daysGrid);
				}
				
				renderCalendarForElement(calendarElement);
				
				setTimeout(() => {
					function closeOnOutside(e) {
						if (!calendarElement || !calendarElement.parentNode) {
							document.removeEventListener('click', closeOnOutside);
							return;
						}
						const triggerArea = element.parentNode;
						const clickedInsideCalendar = calendarElement.contains(e.target);
						const clickedInsideTrigger = (triggerArea && triggerArea.contains(e.target)) || e.target === element;
						if (!clickedInsideCalendar && !clickedInsideTrigger) {
							closeCalendar();
							document.removeEventListener('click', closeOnOutside);
						}
					}
					document.addEventListener('click', closeOnOutside);
				}, 100);
			}
			
			renderCalendar();
			
			return {
				open: openCalendar,
				close: closeCalendar
			};
		}
		
		const datepicker = createCalendar();

		// Datepicker-only: no manual input
		element.setAttribute('readonly', 'readonly');
		element.setAttribute('autocomplete', 'off');
		element.readOnly = true;
		element.addEventListener('keydown', function(e) {
			if (e.key !== 'Tab' && e.key !== 'Escape' && e.key !== 'Enter') {
				e.preventDefault();
				datepicker.open();
			}
		});
		element.addEventListener('focus', function(e) { e.preventDefault(); datepicker.open(); });
		element.addEventListener('click', function(e) { e.preventDefault(); datepicker.open(); });
		element.addEventListener('paste', function(e) { e.preventDefault(); });

		const wrapper = document.createElement('div');
		wrapper.style.cssText = 'position: relative; display: inline-block; width: 100%;';
		element.parentNode.insertBefore(wrapper, element);
		wrapper.appendChild(element);
		
		const toggleBtn = document.createElement('button');
		toggleBtn.type = 'button';
		toggleBtn.innerHTML = '📅';
		toggleBtn.style.cssText = 'position: absolute; right: 8px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; font-size: 18px; padding: 4px 8px;';
		toggleBtn.addEventListener('click', (e) => {
			e.preventDefault();
			e.stopPropagation();
			datepicker.open();
		});
		wrapper.appendChild(toggleBtn);
		
		element.addEventListener('blur', function() {
			if (this.value && this.value.length === 10) {
				const dateRegex = /^(\d{2})\.(\d{2})\.(\d{4})$/;
				const match = this.value.match(dateRegex);
				if (match) {
					const day = parseInt(match[1], 10);
					const month = parseInt(match[2], 10);
					const year = parseInt(match[3], 10);
					const date = new Date(year, month - 1, day);
					
					if (date.getDate() !== day || date.getMonth() !== (month - 1) || date.getFullYear() !== year) {
						this.setCustomValidity('Invalid date');
					} else if (options.maxDate && date > options.maxDate) {
						this.setCustomValidity('Date cannot be in the future');
					} else {
						this.setCustomValidity('');
					}
				} else {
					this.setCustomValidity('Please enter date in format dd.mm.yyyy');
				}
			}
		});
		
		return datepicker;
	}
	
	// Get datepicker functions - use inline implementation or global if available
	function getDatepickerFunctions() {
		// Try global first (if datepicker.js loaded successfully)
		if (window.ProjectCheckDatepicker && typeof window.ProjectCheckDatepicker.initializeDatepicker === 'function') {
			return {
				initializeDatepicker: window.ProjectCheckDatepicker.initializeDatepicker,
				convertEuropeanToISO: window.ProjectCheckDatepicker.convertEuropeanToISO
			};
		}
		// Use inline implementation as fallback
		return {
			initializeDatepicker: initializeInlineDatepicker,
			convertEuropeanToISO: convertEuropeanToISO
		};
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
		
		// Initialize datepicker immediately (using inline implementation if needed)
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
			const datepickerFuncs = getDatepickerFunctions();
			dateFrom = datepickerFuncs.convertEuropeanToISO(dateFrom);
		}
		if (dateTo) {
			const datepickerFuncs = getDatepickerFunctions();
			dateTo = datepickerFuncs.convertEuropeanToISO(dateTo);
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
	 * Initialize date input with datepicker (European format dd.mm.yyyy)
	 */
	function initializeDateInput(input) {
		if (!input) return;
		
		try {
			const datepickerFuncs = getDatepickerFunctions();
			if (datepickerFuncs.initializeDatepicker && typeof datepickerFuncs.initializeDatepicker === 'function') {
				datepickerFuncs.initializeDatepicker(input);
				console.log('Datepicker initialized for', input.id || input.name);
			} else {
				console.warn('Datepicker not available, using fallback');
			}
		} catch (error) {
			console.error('Error initializing datepicker:', error);
		}
	}

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

		// Convert European format (dd.mm.yyyy) to ISO format (yyyy-mm-dd) for backend
		const datepickerFuncs = getDatepickerFunctions();
		if (dateFrom) {
			dateFrom = datepickerFuncs.convertEuropeanToISO(dateFrom);
		}
		if (dateTo) {
			dateTo = datepickerFuncs.convertEuropeanToISO(dateTo);
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
			exportBtn.textContent = t('projectcheck', 'Exporting...');
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
					throw new Error(data.error || 'Export failed');
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
