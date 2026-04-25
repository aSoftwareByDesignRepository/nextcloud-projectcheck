/**
 * Datepicker utility for projectcheck app
 * CSP-compliant simple datepicker with European date format (dd.mm.yyyy)
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

/* global t */
function projectcheckT(key) {
	return (typeof t === 'function') ? t('projectcheck', key) : key;
}

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
		return null;
	}

	const loc = (typeof OC !== 'undefined' && typeof OC.getLocale === 'function')
		? OC.getLocale().replace(/_/g, '-')
		: ((typeof navigator !== 'undefined' && navigator.language) ? navigator.language : 'en');
	const monthYearFor = function (d) {
		return new Intl.DateTimeFormat(loc, { month: 'long', year: 'numeric' }).format(d);
	};
	const dayNames = [];
	for (let wi = 0; wi < 7; wi++) {
		dayNames.push(
			new Intl.DateTimeFormat(loc, { weekday: 'short' }).format(new Date(2024, 0, 1 + wi))
		);
	}

	let currentDate = null;
	let selectedDate = null;
	let calendarOpen = false;
	let calendarElement = null;
	/** @type {null|((e: KeyboardEvent) => void)} */
	let onDocEscape = null;

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

		// Header
		const header = document.createElement('div');
		header.style.cssText = 'display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;';

		const prevBtn = document.createElement('button');
		prevBtn.type = 'button';
		prevBtn.setAttribute('aria-label', projectcheckT('Previous month'));
		prevBtn.textContent = '‹';
		prevBtn.style.cssText = 'background: none; border: none; font-size: 20px; cursor: pointer; padding: 4px 8px; color: var(--color-main-text);';
		prevBtn.addEventListener('click', () => {
			currentDate.setMonth(currentDate.getMonth() - 1);
			renderCalendar();
		});

		const nextBtn = document.createElement('button');
		nextBtn.type = 'button';
		nextBtn.setAttribute('aria-label', projectcheckT('Next month'));
		nextBtn.textContent = '›';
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
			monthYear.textContent = monthYearFor(currentDate);

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
			if (onDocEscape) {
				document.removeEventListener('keydown', onDocEscape, true);
				onDocEscape = null;
			}
			if (calendarElement && calendarElement.parentNode) {
				calendarElement.parentNode.removeChild(calendarElement);
				calendarOpen = false;
				calendarElement = null;
			}
		}

		function openCalendar() {
			// If already open, do nothing (avoids focus+click both calling open and second call closing it)
			if (calendarOpen) {
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
				monthYear.textContent = monthYearFor(currentDate);
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
			onDocEscape = function (e) {
				if (e.key === 'Escape') {
					e.preventDefault();
					closeCalendar();
				}
			};
			document.addEventListener('keydown', onDocEscape, true);

			// Close on outside click
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

	// Datepicker-only: no manual input. Values are set only by the calendar.
	element.setAttribute('readonly', 'readonly');
	element.setAttribute('autocomplete', 'off');
	element.readOnly = true;

	// Allow Tab, Escape, Enter; other keys open the calendar; Escape also closes
	element.addEventListener('keydown', function(e) {
		if (e.key === 'Escape') {
			e.preventDefault();
			datepicker.close();
			return;
		}
		const allow = e.key === 'Tab' || e.key === 'Enter';
		if (!allow) {
			e.preventDefault();
			datepicker.open();
		}
	});

	// Open calendar on focus (primary way for keyboard/screen reader)
	element.addEventListener('focus', function(e) {
		e.preventDefault();
		datepicker.open();
	});

	// Open calendar on click (in case focus didn't open it)
	element.addEventListener('click', function(e) {
		e.preventDefault();
		datepicker.open();
	});

	// Block paste so only datepicker can set the value
	element.addEventListener('paste', function(e) {
		e.preventDefault();
	});

	// Add calendar icon button
	const wrapper = document.createElement('div');
	wrapper.className = 'projectcheck-datepicker__wrap';
	wrapper.style.cssText = 'position: relative; display: inline-block; width: 100%;';
	element.parentNode.insertBefore(wrapper, element);
	wrapper.appendChild(element);

	const toggleBtn = document.createElement('button');
	toggleBtn.type = 'button';
	toggleBtn.setAttribute('aria-label', projectcheckT('Open calendar'));
	toggleBtn.setAttribute('aria-haspopup', 'dialog');
	toggleBtn.innerHTML = '<span aria-hidden="true" class="projectcheck-datepicker__icon">&#128198;</span>';
	toggleBtn.className = 'projectcheck-datepicker__toggle';
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
					this.setCustomValidity(projectcheckT('Invalid date'));
				} else if (options.maxDate && date > options.maxDate) {
					this.setCustomValidity(projectcheckT('Date cannot be in the future'));
				} else {
					this.setCustomValidity('');
				}
			} else {
				this.setCustomValidity(projectcheckT('Please enter the date in the format dd.mm.yyyy.'));
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
	}
})();

// Export for ES6 modules (if module system is available, but don't rely on it)
if (typeof module !== 'undefined' && typeof module.exports !== 'undefined') {
	module.exports = { initializeDatepicker, convertEuropeanToISO, convertISOToEuropean };
}
