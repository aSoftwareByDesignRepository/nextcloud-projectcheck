/**
 * Datepicker utility for projectcheck app
 * CSP-compliant simple datepicker with European date format (dd.mm.yyyy)
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

/**
 * Convert European date format (dd.mm.yyyy) to ISO format (yyyy-mm-dd)
 * 
 * @param {string} dateString - Date in dd.mm.yyyy format
 * @returns {string} Date in yyyy-mm-dd format
 */
function convertEuropeanToISO(dateString) {
	if (!dateString) {
		return '';
	}
	
	if (/^\d{2}\.\d{2}\.\d{4}$/.test(dateString)) {
		const parts = dateString.split('.');
		const day = parts[0];
		const month = parts[1];
		const year = parts[2];
		return `${year}-${month}-${day}`;
	}
	
	// If already in ISO format, return as-is
	if (/^\d{4}-\d{2}-\d{2}$/.test(dateString)) {
		return dateString;
	}
	
	return dateString;
}

/**
 * Convert ISO date format (yyyy-mm-dd) to European format (dd.mm.yyyy)
 * 
 * @param {string} dateString - Date in yyyy-mm-dd format
 * @returns {string} Date in dd.mm.yyyy format
 */
function convertISOToEuropean(dateString) {
	if (!dateString) {
		return '';
	}
	
	if (/^\d{4}-\d{2}-\d{2}$/.test(dateString)) {
		const parts = dateString.split('-');
		const year = parts[0];
		const month = parts[1];
		const day = parts[2];
		return `${day}.${month}.${year}`;
	}
	
	// If already in European format, return as-is
	if (/^\d{2}\.\d{2}\.\d{4}$/.test(dateString)) {
		return dateString;
	}
	
	return dateString;
}

/**
 * Initialize a simple datepicker on the given input element
 * CSP-compliant implementation without eval()
 * 
 * @param {HTMLElement|string} input - Input element or selector
 * @param {Object} options - Additional options (maxDate, minDate, etc.)
 * @returns {Object} Datepicker instance
 */
function initializeDatepicker(input, options = {}) {
	const element = typeof input === 'string' ? document.querySelector(input) : input;
	if (!element) {
		console.error('Datepicker: Input element not found');
		return null;
	}

	// German month names
	const monthNames = ['Januar', 'Februar', 'März', 'April', 'Mai', 'Juni', 
		'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'];
	const dayNames = ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'];

	let currentDate = null;
	let selectedDate = null;
	let calendarOpen = false;
	let calendarElement = null;

	// Parse current value if exists
	if (element.value && /^\d{2}\.\d{2}\.\d{4}$/.test(element.value)) {
		const parts = element.value.split('.');
		selectedDate = new Date(parseInt(parts[2], 10), parseInt(parts[1], 10) - 1, parseInt(parts[0], 10));
	}

	currentDate = selectedDate || new Date();
	currentDate.setHours(0, 0, 0, 0);

	// Create calendar container
	function createCalendar() {
		const container = document.createElement('div');
		container.className = 'projectcheck-datepicker';
		container.style.cssText = 'position: absolute; z-index: 10000; background: var(--color-main-background); border: 1px solid var(--color-border); border-radius: 8px; padding: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); min-width: 280px;';

		// Header
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

		// Calendar grid
		const calendar = document.createElement('div');
		calendar.className = 'projectcheck-datepicker-calendar';

		container.appendChild(header);
		container.appendChild(calendar);

		function renderCalendar() {
			// Update month/year display
			monthYear.textContent = monthNames[currentDate.getMonth()] + ' ' + currentDate.getFullYear();

			// Clear calendar
			calendar.innerHTML = '';

			// Day headers
			const dayHeader = document.createElement('div');
			dayHeader.style.cssText = 'display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; margin-bottom: 8px;';
			dayNames.forEach(day => {
				const dayCell = document.createElement('div');
				dayCell.textContent = day;
				dayCell.style.cssText = 'text-align: center; font-weight: 600; font-size: 12px; color: var(--color-text-maxcontrast); padding: 4px;';
				dayHeader.appendChild(dayCell);
			});
			calendar.appendChild(dayHeader);

			// Get first day of month and days in month
			const firstDay = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1);
			const lastDay = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0);
			const startDate = new Date(firstDay);
			startDate.setDate(startDate.getDate() - (firstDay.getDay() || 7) + 1); // Monday = 0

			// Days grid
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

				// Check if date is in current month
				if (date.getMonth() !== currentDate.getMonth()) {
					dayCell.style.opacity = '0.3';
				}

				// Check if date is today
				if (date.getTime() === today.getTime()) {
					dayCell.style.fontWeight = 'bold';
					dayCell.style.background = 'var(--color-primary-element-light)';
				}

				// Check if date is selected
				if (selectedDate && date.getTime() === selectedDate.getTime()) {
					dayCell.style.background = 'var(--color-primary-element)';
					dayCell.style.color = 'var(--color-primary-element-text)';
				}

				// Check if date is disabled (future dates if maxDate is set)
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
				calendarOpen = false;
				calendarElement = null;
			}
		}

		function openCalendar() {
			if (calendarOpen) {
				closeCalendar();
				return;
			}

			calendarElement = container.cloneNode(true);
			document.body.appendChild(calendarElement);

			// Position calendar
			const rect = element.getBoundingClientRect();
			calendarElement.style.top = (rect.bottom + window.scrollY + 4) + 'px';
			calendarElement.style.left = (rect.left + window.scrollX) + 'px';

			// Re-initialize event listeners
			const prevBtn = calendarElement.querySelector('button:first-child');
			const nextBtn = calendarElement.querySelector('button:last-child');
			const calendar = calendarElement.querySelector('.projectcheck-datepicker-calendar');

			prevBtn.addEventListener('click', () => {
				currentDate.setMonth(currentDate.getMonth() - 1);
				renderCalendarForElement(calendarElement);
			});

			nextBtn.addEventListener('click', () => {
				currentDate.setMonth(currentDate.getMonth() + 1);
				renderCalendarForElement(calendarElement);
			});

			function renderCalendarForElement(calEl) {
				const monthYear = calEl.querySelector('div:nth-child(2)');
				const calGrid = calEl.querySelector('.projectcheck-datepicker-calendar');
				monthYear.textContent = monthNames[currentDate.getMonth()] + ' ' + currentDate.getFullYear();
				calGrid.innerHTML = '';

				// Day headers
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
			calendarOpen = true;

			// Close on outside click
			setTimeout(() => {
				document.addEventListener('click', function closeOnOutside(e) {
					if (!calendarElement.contains(e.target) && e.target !== element) {
						closeCalendar();
						document.removeEventListener('click', closeOnOutside);
					}
				});
			}, 100);
		}

		renderCalendar();

		return {
			open: openCalendar,
			close: closeCalendar
		};
	}

	const datepicker = createCalendar();

	// Auto-format input as user types
	element.addEventListener('input', function() {
		let value = this.value.replace(/\D/g, '');
		if (value.length >= 2) {
			value = value.substring(0, 2) + '.' + value.substring(2);
		}
		if (value.length >= 5) {
			value = value.substring(0, 5) + '.' + value.substring(5, 9);
		}
		this.value = value;
	});

	// Add calendar icon button
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

	// Validate on blur
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

// Make available globally immediately (wrapped in IIFE to ensure execution)
(function() {
	'use strict';
	if (typeof window !== 'undefined') {
		window.ProjectCheckDatepicker = {
			initializeDatepicker: initializeDatepicker,
			convertEuropeanToISO: convertEuropeanToISO,
			convertISOToEuropean: convertISOToEuropean
		};
		console.log('[ProjectCheck] Datepicker initialized and available globally');
	}
})();

// Export for ES6 modules (if module system is available, but don't rely on it)
if (typeof module !== 'undefined' && typeof module.exports !== 'undefined') {
	module.exports = { initializeDatepicker, convertEuropeanToISO, convertISOToEuropean };
}
