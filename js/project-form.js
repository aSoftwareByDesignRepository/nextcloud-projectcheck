/**
 * Project form JavaScript for projectcheck app
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
	
	// Import inline datepicker implementation from a shared source
	// For now, we'll use the same implementation as time-entry-form.js
	// (In a real refactoring, this would be extracted to a shared module)
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
				if (calendarElement && calendarElement.parentNode) {
					closeCalendar();
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

	/**
	 * Initialize project form functionality
	 */
	function initializeProjectForm() {
		const startDateInput = document.getElementById('start_date');
		const endDateInput = document.getElementById('end_date');

		// Initialize datepicker immediately (using inline implementation if needed)
		const datepickerFuncs = getDatepickerFunctions();
		try {
			if (startDateInput && datepickerFuncs.initializeDatepicker) {
				datepickerFuncs.initializeDatepicker(startDateInput);
				console.log('[ProjectForm] Start date datepicker initialized');
			}
			if (endDateInput && datepickerFuncs.initializeDatepicker) {
				datepickerFuncs.initializeDatepicker(endDateInput);
				console.log('[ProjectForm] End date datepicker initialized');
			}
		} catch (error) {
			console.error('[ProjectForm] Error initializing datepicker:', error);
		}

		// Convert European date format to ISO format before form submission
		const form = document.getElementById('project-form');
		if (form) {
			form.addEventListener('submit', function(e) {
				const datepickerFuncs = getDatepickerFunctions();
				if (startDateInput && startDateInput.value) {
					startDateInput.value = datepickerFuncs.convertEuropeanToISO(startDateInput.value);
				}
				if (endDateInput && endDateInput.value) {
					endDateInput.value = datepickerFuncs.convertEuropeanToISO(endDateInput.value);
				}
			});
		}
	}

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initializeProjectForm);
	} else {
		initializeProjectForm();
	}
})();

