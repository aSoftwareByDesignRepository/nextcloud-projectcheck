/**
 * Time entry form JavaScript for projectcheck app
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
	 * Initialize time entry form functionality
	 */
	function initializeTimeEntryForm() {
		addFormHandlers();
		addValidation();
		addRealTimeValidation();
		initializeCharacterCount();
		initializeProjectRateSync();
		calculateTotalCost();
		
		// Initialize datepicker immediately (using inline implementation if needed)
		const dateInput = document.getElementById('date');
		if (dateInput) {
			try {
				const datepickerFuncs = getDatepickerFunctions();
				console.log('[TimeEntryForm] Initializing datepicker...');
				const datepicker = datepickerFuncs.initializeDatepicker(dateInput, {
					maxDate: new Date() // No future dates
				});
				if (datepicker) {
					console.log('[TimeEntryForm] Datepicker successfully initialized');
				}
			} catch (error) {
				console.error('[TimeEntryForm] Error initializing datepicker:', error);
			}
		}
	}

	/**
	 * Add validation functions
	 */
	function addValidation() {
		// This function is called but not needed for this form
		// Validation is handled in validateFormData function
	}

	/**
	 * Add form event handlers
	 */
	function addFormHandlers() {
		const form = document.getElementById('time-entry-form');
		if (form) {
			form.addEventListener('submit', handleFormSubmit);
		}

		// Hours and rate change handlers for total cost calculation
		const hoursInput = document.getElementById('hours');
		const rateInput = document.getElementById('hourly_rate');

		if (hoursInput) {
			hoursInput.addEventListener('input', calculateTotalCost);
		}

		if (rateInput) {
			rateInput.addEventListener('input', calculateTotalCost);
		}

		// Project selection handler
		const projectSelect = document.getElementById('project_id');
		if (projectSelect) {
			projectSelect.addEventListener('change', handleProjectChange);
		}

		// Description character count
		const descriptionTextarea = document.getElementById('description');
		if (descriptionTextarea) {
			descriptionTextarea.addEventListener('input', updateCharacterCount);
		}

		// Datepicker initialization is handled in initializeTimeEntryForm() with delay
	}

	/**
	 * Handle form submission
	 */
	function handleFormSubmit(event) {
		event.preventDefault();

		const form = event.currentTarget;
		const submitButton = form.querySelector('#submit-btn');

		// Prevent double submission
		if (submitButton.disabled || form.classList.contains('submitting')) {
			return;
		}

		const formData = collectFormData(form);
		const errors = validateFormData(formData);

		if (Object.keys(errors).length > 0) {
			displayErrors(errors);
			return;
		}

		// Remove internal _raw field before sending to server
		delete formData._raw;
		
		submitTimeEntryData(formData, form);
	}

	/**
	 * Collect form data
	 */
	function collectFormData(form) {
		const formData = new FormData(form);
		const data = {};
		const rawData = {}; // Store raw values for validation

		for (let [key, value] of formData.entries()) {
			const trimmedValue = value.trim();
			rawData[key] = trimmedValue; // Store raw value

			// Convert numeric fields to numbers
			if (key === 'project_id' || key === 'hours' || key === 'hourly_rate') {
				data[key] = trimmedValue === '' ? null : parseFloat(trimmedValue);
			} else if (key === 'date' && trimmedValue) {
				// Convert European date format (dd.mm.yyyy) to ISO format (yyyy-mm-dd) for server
				const datepickerFuncs = getDatepickerFunctions();
				data[key] = datepickerFuncs.convertEuropeanToISO(trimmedValue);
			} else {
				data[key] = trimmedValue;
			}
		}

		// Store raw data for validation
		data._raw = rawData;

		return data;
	}


	/**
	 * Validate form data
	 */
	function validateFormData(data) {
		const errors = {};

		// Required fields
		if (!data.project_id) {
			errors.project_id = 'Project is required';
		}

		// Use raw date value for validation (before conversion to ISO)
		const rawDate = data._raw && data._raw.date ? data._raw.date : '';
		
		if (!rawDate) {
			errors.date = 'Date is required';
		} else {
			// Validate date format - expect European format (dd.mm.yyyy)
			let date = null;
			if (/^\d{2}\.\d{2}\.\d{4}$/.test(rawDate)) {
				// European format: dd.mm.yyyy
				const parts = rawDate.split('.');
				const day = parseInt(parts[0], 10);
				const month = parseInt(parts[1], 10);
				const year = parseInt(parts[2], 10);
				
				// Validate ranges
				if (day < 1 || day > 31 || month < 1 || month > 12 || year < 1900 || year > 2100) {
					errors.date = t('projectcheck', 'Invalid date format (dd.mm.yyyy)');
				} else {
					date = new Date(year, month - 1, day);
					
					// Check if date is valid
					if (isNaN(date.getTime()) || date.getDate() !== day || date.getMonth() !== (month - 1) || date.getFullYear() !== year) {
						errors.date = t('projectcheck', 'Invalid date (e.g., 31.02.2024 is not valid)');
					} else {
						// Check if date is not in the future
						const today = new Date();
						today.setHours(0, 0, 0, 0);
						if (date > today) {
							errors.date = t('projectcheck', 'Date cannot be in the future');
						}
					}
				}
			} else {
				errors.date = t('projectcheck', 'Invalid date format (dd.mm.yyyy)');
			}
		}

		if (!data.hours) {
			errors.hours = 'Hours are required';
		} else {
			const hours = parseFloat(data.hours);
			if (isNaN(hours) || hours <= 0) {
				errors.hours = 'Hours must be a positive number';
			} else if (hours > 24) {
				errors.hours = 'Hours cannot exceed 24';
			}
		}

		if (!data.hourly_rate) {
			errors.hourly_rate = 'Hourly rate is required';
		} else {
			const rate = parseFloat(data.hourly_rate);
			if (isNaN(rate) || rate < 0) {
				errors.hourly_rate = 'Hourly rate must be a non-negative number';
			}
		}

		// Validate description length
		if (data.description && data.description.length > 1000) {
			errors.description = 'Description cannot exceed 1000 characters';
		}

		return errors;
	}

	/**
	 * Display validation errors
	 */
	function displayErrors(errors) {
		// Clear previous errors
		clearErrors();

		// Display new errors
		Object.keys(errors).forEach(fieldName => {
			const field = document.getElementById(fieldName);
			const errorElement = document.getElementById(fieldName + '-error');

			if (field && errorElement) {
				field.classList.add('has-error');
				errorElement.textContent = errors[fieldName];
			}
		});

		// Show error notification
		const firstError = Object.values(errors)[0];
		if (firstError) {
			showNotification(firstError, 'error');
		}
	}

	/**
	 * Clear all error messages
	 */
	function clearErrors() {
		document.querySelectorAll('.error-message').forEach(element => {
			element.textContent = '';
		});

		document.querySelectorAll('.form-group').forEach(group => {
			group.classList.remove('has-error');
		});
	}

	/**
	 * Submit time entry data
	 */
	function submitTimeEntryData(data, form) {
		const submitButton = form.querySelector('#submit-btn');
		const isEdit = window.timeEntryFormData && window.timeEntryFormData.isEdit;
		const url = isEdit
			? OC.generateUrl('/apps/projectcheck/time-entries/' + window.timeEntryFormData.timeEntryId)
			: OC.generateUrl('/apps/projectcheck/time-entries');

		const method = isEdit ? 'PUT' : 'POST';

		// Mark form as submitting and show loading state
		form.classList.add('submitting');
		showLoadingState(form, true);

		fetch(url, {
			method: method,
			headers: {
				'Content-Type': 'application/json',
				'requesttoken': OC.requestToken
			},
			body: JSON.stringify(data)
		})
			.then(response => response.json())
			.then(result => {
				if (result.success) {
					showNotification(result.message || 'Time entry saved successfully', 'success');

					// Redirect to time entries list after a short delay
					setTimeout(() => {
						window.location.href = OC.generateUrl('/apps/projectcheck/time-entries');
					}, 1500);
				} else {
					if (result.errors) {
						displayErrors(result.errors);
					} else {
						showNotification(result.error || t('projectcheck', 'Failed to save time entry'), 'error');
					}
					// Re-enable form only on error
					form.classList.remove('submitting');
					showLoadingState(form, false);
				}
			})
			.catch(error => {
				console.error('Error submitting time entry:', error);
				showNotification('An error occurred while saving the time entry', 'error');
				// Re-enable form on error
				form.classList.remove('submitting');
				showLoadingState(form, false);
			});
	}

	/**
	 * Show/hide loading state
	 */
	function showLoadingState(form, loading) {
		const submitButton = form.querySelector('#submit-btn');

		if (loading) {
			form.classList.add('loading');
			if (submitButton) {
				submitButton.disabled = true;
				submitButton.innerHTML = '<span class="icon icon-loading-small"></span> ' +
					(window.timeEntryFormData && window.timeEntryFormData.isEdit ? 'Updating...' : 'Creating...');
			}
		} else {
			form.classList.remove('loading');
			if (submitButton) {
				submitButton.disabled = false;
				submitButton.innerHTML = window.timeEntryFormData && window.timeEntryFormData.isEdit ?
					'Update Time Entry' : 'Create Time Entry';
			}
		}
	}

	/**
	 * Add real-time validation
	 */
	function addRealTimeValidation() {
		const fields = ['project_id', 'date', 'hours', 'hourly_rate', 'description'];

		fields.forEach(fieldName => {
			const field = document.getElementById(fieldName);
			if (field) {
				field.addEventListener('blur', () => {
					validateField(fieldName, field.value);
				});

				field.addEventListener('input', () => {
					// Clear error on input
					const errorElement = document.getElementById(fieldName + '-error');
					if (errorElement && errorElement.textContent) {
						errorElement.textContent = '';
						field.classList.remove('has-error');
					}
				});
			}
		});
	}

	/**
	 * Validate individual field
	 */
	function validateField(fieldName, value) {
		const field = document.getElementById(fieldName);
		const errorElement = document.getElementById(fieldName + '-error');

		if (!field || !errorElement) return;

		let error = '';

		switch (fieldName) {
			case 'project_id':
				if (!value) error = 'Project is required';
				break;
			case 'date':
				if (!value) {
					error = 'Date is required';
				} else if (!/^\d{2}\.\d{2}\.\d{4}$/.test(value)) {
					error = t('projectcheck', 'Invalid date format (dd.mm.yyyy)');
				} else {
					const parts = value.split('.');
					const day = parseInt(parts[0], 10);
					const month = parseInt(parts[1], 10);
					const year = parseInt(parts[2], 10);
					const date = new Date(year, month - 1, day);
					
					if (isNaN(date.getTime()) || date.getDate() !== day || date.getMonth() !== (month - 1) || date.getFullYear() !== year) {
						error = t('projectcheck', 'Invalid date (e.g., 31.02.2024 is not valid)');
					} else {
						const today = new Date();
						today.setHours(0, 0, 0, 0);
						if (date > today) {
							error = t('projectcheck', 'Date cannot be in the future');
						}
					}
				}
				break;
			case 'hours':
				if (!value) {
					error = 'Hours are required';
				} else {
					const hours = parseFloat(value);
					if (isNaN(hours) || hours <= 0) {
						error = 'Hours must be a positive number';
					} else if (hours > 24) {
						error = 'Hours cannot exceed 24';
					}
				}
				break;
			case 'hourly_rate':
				if (!value) {
					error = 'Hourly rate is required';
				} else {
					const rate = parseFloat(value);
					if (isNaN(rate) || rate < 0) {
						error = 'Hourly rate must be a non-negative number';
					}
				}
				break;
			case 'description':
				if (value && value.length > 1000) {
					error = 'Description cannot exceed 1000 characters';
				}
				break;
		}

		if (error) {
			field.classList.add('has-error');
			errorElement.textContent = error;
		} else {
			field.classList.remove('has-error');
			errorElement.textContent = '';
		}
	}

	/**
	 * Initialize character count
	 */
	function initializeCharacterCount() {
		const descriptionTextarea = document.getElementById('description');
		if (descriptionTextarea) {
			updateCharacterCount();
		}
	}

	/**
	 * Update character count
	 */
	function updateCharacterCount() {
		const descriptionTextarea = document.getElementById('description');
		const charCountElement = document.getElementById('char-count');

		if (descriptionTextarea && charCountElement) {
			const count = descriptionTextarea.value.length;
			charCountElement.textContent = count;

			const charCountContainer = charCountElement.closest('.char-count');
			if (charCountContainer) {
				charCountContainer.classList.remove('warning', 'error');

				if (count > 900) {
					charCountContainer.classList.add('error');
				} else if (count > 800) {
					charCountContainer.classList.add('warning');
				}
			}
		}
	}

	/**
	 * Initialize project rate synchronization
	 */
	function initializeProjectRateSync() {
		const projectSelect = document.getElementById('project_id');
		if (projectSelect) {
			// Set initial rate if project is selected
			const selectedOption = projectSelect.options[projectSelect.selectedIndex];
			if (selectedOption && selectedOption.dataset.hourlyRate) {
				const rateInput = document.getElementById('hourly_rate');
				const projectHourlyRate = parseFloat(selectedOption.dataset.hourlyRate);

				if (rateInput) {
					// If project has hourly rate set and different from 0, make field readonly
					if (projectHourlyRate > 0) {
						rateInput.value = projectHourlyRate;
						rateInput.readOnly = true;
						rateInput.classList.add('readonly');
						rateInput.title = 'Hourly rate is set by the project and cannot be changed';
					} else if (!rateInput.value) {
						// Project has no hourly rate or it's 0, and field is empty
						rateInput.value = projectHourlyRate;
					}

					calculateTotalCost();
				}
			}
		}
	}

	/**
	 * Handle project selection change
	 */
	function handleProjectChange(event) {
		const select = event.currentTarget;
		const selectedOption = select.options[select.selectedIndex];
		const rateInput = document.getElementById('hourly_rate');

		if (selectedOption && selectedOption.dataset.hourlyRate && rateInput) {
			const projectHourlyRate = parseFloat(selectedOption.dataset.hourlyRate);

			// If project has hourly rate set and different from 0, make field readonly
			if (projectHourlyRate > 0) {
				rateInput.value = projectHourlyRate;
				rateInput.readOnly = true;
				rateInput.classList.add('readonly');
				rateInput.title = 'Hourly rate is set by the project and cannot be changed';
			} else {
				// Project has no hourly rate or it's 0, allow editing
				rateInput.readOnly = false;
				rateInput.classList.remove('readonly');
				rateInput.title = '';

				// Only update rate if it's empty or if user hasn't manually changed it
				if (!rateInput.value || rateInput.dataset.autoSet === 'true') {
					rateInput.value = projectHourlyRate;
					rateInput.dataset.autoSet = 'true';
				}
			}

			calculateTotalCost();
		} else {
			// No project selected or no hourly rate, allow editing
			rateInput.readOnly = false;
			rateInput.classList.remove('readonly');
			rateInput.title = '';
		}
	}

	/**
	 * Calculate total cost
	 */
	function calculateTotalCost() {
		const hoursInput = document.getElementById('hours');
		const rateInput = document.getElementById('hourly_rate');
		const totalCostInput = document.getElementById('total_cost');

		if (hoursInput && rateInput && totalCostInput) {
			const hours = parseFloat(hoursInput.value) || 0;
			const rate = parseFloat(rateInput.value) || 0;
			const total = hours * rate;

			totalCostInput.value = formatCurrency(total);
		}
	}

	/**
	 * Format currency
	 */
	function formatCurrency(amount) {
		return '€' + parseFloat(amount).toFixed(2);
	}

	/**
	 * Show notification
	 */
	function showNotification(message, type = 'info') {
		OC.Notification.show(message, {
			type: type,
			timeout: 5
		});
	}

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initializeTimeEntryForm);
	} else {
		initializeTimeEntryForm();
	}

	// Export functions for global access if needed
	window.TimeEntryForm = {
		initialize: initializeTimeEntryForm,
		validateField: validateField,
		calculateTotalCost: calculateTotalCost,
		showNotification: showNotification
	};

})();

