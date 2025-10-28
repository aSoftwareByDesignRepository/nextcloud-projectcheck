/**
 * Customer form JavaScript for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function () {
	'use strict';

	// Initialize customer form when DOM is ready
	document.addEventListener('DOMContentLoaded', function () {
		// Initialize Lucide icons if available
		if (window.LucideIcons && window.LucideIcons.initialize) {
			window.LucideIcons.initialize();
		}
		initializeCustomerForm();
	});

	/**
	 * Initialize customer form functionality
	 */
	function initializeCustomerForm() {
		// Add form submission handler
		addFormHandlers();

		// Add validation
		addValidation();

		// Add real-time validation
		addRealTimeValidation();

		// Add accessibility features
		addAccessibilityFeatures();

		// Add form field enhancements
		addFormEnhancements();
	}

	/**
	 * Add form submission handlers
	 */
	function addFormHandlers() {
		const form = document.getElementById('customer-form');
		const messageDiv = document.getElementById('form-message');

		if (form) {
			form.addEventListener('submit', function (e) {
				e.preventDefault();

				// Clear previous messages
				clearMessages();

				// Show loading state
				showLoadingState(true);

				// Collect form data
				const formData = collectFormData();

				// Validate form data
				if (!validateFormData(formData)) {
					showLoadingState(false);
					return;
				}

				// Submit customer data
				submitCustomerData(formData);
			});
		}
	}

	/**
	 * Add accessibility features
	 */
	function addAccessibilityFeatures() {
		const form = document.getElementById('customer-form');
		if (!form) return;

		// Add keyboard navigation
		const inputs = form.querySelectorAll('input, textarea, select');
		inputs.forEach(function (input, index) {
			input.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' && e.target.type !== 'textarea') {
					e.preventDefault();
					const nextInput = inputs[index + 1];
					if (nextInput) {
						nextInput.focus();
					} else {
						form.querySelector('button[type="submit"]').focus();
					}
				}
			});
		});

		// Add focus management
		inputs.forEach(function (input) {
			input.addEventListener('focus', function () {
				this.closest('.form-group').classList.add('focused');
			});

			input.addEventListener('blur', function () {
				this.closest('.form-group').classList.remove('focused');
			});
		});
	}

	/**
	 * Add form enhancements
	 */
	function addFormEnhancements() {
		const form = document.getElementById('customer-form');
		if (!form) return;

		// Add character counters
		const textInputs = form.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], textarea');
		textInputs.forEach(function (input) {
			if (input.maxLength) {
				addCharacterCounter(input);
			}
		});

		// Add auto-save functionality (optional)
		let autoSaveTimeout;
		textInputs.forEach(function (input) {
			input.addEventListener('input', function () {
				clearTimeout(autoSaveTimeout);
				autoSaveTimeout = setTimeout(function () {
					// Could implement auto-save here
					console.log('Auto-save triggered');
				}, 2000);
			});
		});
	}

	/**
	 * Add character counter to input field
	 */
	function addCharacterCounter(input) {
		const formGroup = input.closest('.form-group');
		const counter = document.createElement('div');
		counter.className = 'character-counter';
		counter.textContent = `${input.value.length}/${input.maxLength}`;
		formGroup.appendChild(counter);

		input.addEventListener('input', function () {
			counter.textContent = `${this.value.length}/${this.maxLength}`;
			if (this.value.length > this.maxLength * 0.9) {
				counter.classList.add('warning');
			} else {
				counter.classList.remove('warning');
			}
		});
	}

	/**
	 * Collect form data
	 */
	function collectFormData() {
		const form = document.getElementById('customer-form');
		const formData = new FormData(form);
		const data = {};

		// Convert FormData to object
		for (let [key, value] of formData.entries()) {
			data[key] = value.trim();
		}

		return data;
	}

	/**
	 * Validate form data
	 */
	function validateFormData(data) {
		const errors = {};
		let hasErrors = false;

		// Clear previous errors
		clearFieldErrors();

		// Validate name
		if (!data.name || data.name.trim() === '') {
			errors.name = 'Customer name is required';
			hasErrors = true;
		} else if (data.name.length > 100) {
			errors.name = 'Customer name must be 100 characters or less';
			hasErrors = true;
		} else if (data.name.length < 2) {
			errors.name = 'Customer name must be at least 2 characters';
			hasErrors = true;
		}

		// Validate email if provided
		if (data.email && data.email.trim() !== '') {
			if (!isValidEmail(data.email)) {
				errors.email = 'Please enter a valid email address';
				hasErrors = true;
			}
		}

		// Validate phone if provided
		if (data.phone && data.phone.trim() !== '') {
			if (data.phone.length > 50) {
				errors.phone = 'Phone number must be 50 characters or less';
				hasErrors = true;
			}
		}

		// Validate contact person if provided
		if (data.contact_person && data.contact_person.trim() !== '') {
			if (data.contact_person.length > 100) {
				errors.contact_person = 'Contact person name must be 100 characters or less';
				hasErrors = true;
			}
		}

		// Validate address if provided
		if (data.address && data.address.trim() !== '') {
			if (data.address.length > 500) {
				errors.address = 'Address must be 500 characters or less';
				hasErrors = true;
			}
		}

		// Show field-specific errors
		if (hasErrors) {
			showFieldErrors(errors);
			return false;
		}

		return true;
	}

	/**
	 * Clear all field errors
	 */
	function clearFieldErrors() {
		const errorElements = document.querySelectorAll('.error-message');
		errorElements.forEach(element => {
			element.textContent = '';
			element.style.display = 'none';
		});

		const formGroups = document.querySelectorAll('.form-group');
		formGroups.forEach(group => {
			group.classList.remove('error');
		});
	}

	/**
	 * Show field-specific errors
	 */
	function showFieldErrors(errors) {
		Object.keys(errors).forEach(fieldName => {
			const errorElement = document.getElementById(fieldName + '-error');
			const formGroup = errorElement ? errorElement.closest('.form-group') : null;

			if (errorElement && formGroup) {
				errorElement.textContent = errors[fieldName];
				errorElement.style.display = 'block';
				formGroup.classList.add('error');
			}
		});
	}

	/**
	 * Submit customer data to server
	 */
	function submitCustomerData(data) {
		const form = document.getElementById('customer-form');
		const isEdit = form.action.includes('/edit');

		fetch(form.action, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'requesttoken': OC.requestToken
			},
			body: JSON.stringify(data)
		})
			.then(function (response) {
				if (!response.ok) {
					throw new Error('Network response was not ok');
				}
				return response.json();
			})
			.then(function (result) {
				showLoadingState(false);

				if (result.success) {
					showMessage(result.message, 'success');

					// Redirect to customer list after successful creation
					if (!isEdit) {
						setTimeout(function () {
							window.location.href = OC.generateUrl('/apps/projectcheck/customers');
						}, 1500);
					}
				} else {
					showMessage(result.error || t('projectcheck', 'Failed to save customer'), 'error');
				}
			})
			.catch(function (error) {
				showLoadingState(false);
				showMessage(t('projectcheck', 'Failed to save customer') + ': ' + error.message, 'error');
			});
	}

	/**
	 * Add validation to form fields
	 */
	function addValidation() {
		const form = document.getElementById('customer-form');

		if (!form) return;

		// Add validation to text fields
		const textFields = form.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], textarea');
		textFields.forEach(function (field) {
			field.addEventListener('blur', function () {
				validateField(this);
			});

			field.addEventListener('input', function () {
				// Clear error state on input
				const formGroup = this.closest('.form-group');
				formGroup.classList.remove('error');
				const errorMessage = formGroup.querySelector('.error-message');
				if (errorMessage) {
					errorMessage.remove();
				}
			});
		});
	}

	/**
	 * Add real-time validation
	 */
	function addRealTimeValidation() {
		const form = document.getElementById('customer-form');

		if (!form) return;

		// Real-time validation for email field
		const emailField = form.querySelector('#email');
		if (emailField) {
			emailField.addEventListener('input', function () {
				if (this.value && !isValidEmail(this.value)) {
					this.setCustomValidity('Please enter a valid email address');
					showFieldError(this, 'Please enter a valid email address');
				} else {
					this.setCustomValidity('');
					clearFieldError(this);
				}
			});
		}

		// Real-time validation for name field
		const nameField = form.querySelector('#name');
		if (nameField) {
			nameField.addEventListener('input', function () {
				if (this.value.length > 100) {
					this.setCustomValidity('Name must be 100 characters or less');
					showFieldError(this, 'Name must be 100 characters or less');
				} else {
					this.setCustomValidity('');
					clearFieldError(this);
				}
			});
		}
	}

	/**
	 * Validate individual field
	 */
	function validateField(field) {
		const formGroup = field.closest('.form-group');
		clearFieldError(field);

		// Validate based on field type
		let isValid = true;
		let message = '';

		if (field.type === 'email' && field.value) {
			if (!isValidEmail(field.value)) {
				isValid = false;
				message = 'Please enter a valid email address';
			}
		} else if (field.type === 'text' || field.type === 'tel') {
			const maxLength = field.name === 'name' ? 100 :
				field.name === 'contact_person' ? 100 :
					field.name === 'phone' ? 50 : 255;

			if (field.value.length > maxLength) {
				isValid = false;
				message = `Field must be ${maxLength} characters or less`;
			}
		}

		// Show error if invalid
		if (!isValid) {
			showFieldError(field, message);
		}
	}

	/**
	 * Show field error
	 */
	function showFieldError(field, message) {
		const formGroup = field.closest('.form-group');
		formGroup.classList.add('error');

		const errorDiv = document.createElement('div');
		errorDiv.className = 'error-message';
		errorDiv.textContent = message;
		formGroup.appendChild(errorDiv);

		// Add ARIA attributes
		field.setAttribute('aria-invalid', 'true');
		field.setAttribute('aria-describedby', 'error-' + field.id);
		errorDiv.id = 'error-' + field.id;
	}

	/**
	 * Clear field error
	 */
	function clearFieldError(field) {
		const formGroup = field.closest('.form-group');
		formGroup.classList.remove('error');

		const errorMessage = formGroup.querySelector('.error-message');
		if (errorMessage) {
			errorMessage.remove();
		}

		// Remove ARIA attributes
		field.removeAttribute('aria-invalid');
		field.removeAttribute('aria-describedby');
	}

	/**
	 * Validate email format
	 */
	function isValidEmail(email) {
		const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
		return emailRegex.test(email);
	}

	/**
	 * Show loading state
	 */
	function showLoadingState(loading) {
		const form = document.getElementById('customer-form');
		const submitButton = form.querySelector('button[type="submit"]');

		if (loading) {
			form.classList.add('loading');
			submitButton.disabled = true;
			submitButton.textContent = 'Saving...';
			submitButton.setAttribute('aria-busy', 'true');
		} else {
			form.classList.remove('loading');
			submitButton.disabled = false;

			// Restore the correct button text based on whether this is an edit form
			const isEdit = form.action.includes('/update');
			submitButton.textContent = isEdit ? 'Update Customer' : 'Create Customer';

			submitButton.removeAttribute('aria-busy');
		}
	}

	/**
	 * Clear all messages
	 */
	function clearMessages() {
		const messageDiv = document.getElementById('form-message');
		if (messageDiv) {
			messageDiv.style.display = 'none';
			messageDiv.innerHTML = '';
		}
	}

	/**
	 * Show message
	 */
	function showMessage(message, type = 'info') {
		const messageDiv = document.getElementById('form-message');

		if (messageDiv) {
			messageDiv.innerHTML = message;
			messageDiv.className = `form-message ${type}`;
			messageDiv.style.display = 'block';

			// Scroll to message
			messageDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });

			// Auto-hide success messages after 3 seconds
			if (type === 'success') {
				setTimeout(function () {
					messageDiv.style.display = 'none';
				}, 3000);
			}
		}
	}

	/**
	 * Validate individual field
	 */
	function validateField(field) {
		const value = field.value.trim();
		const fieldName = field.name;
		const errorElement = document.getElementById(fieldName + '-error');
		const formGroup = field.closest('.form-group');

		// Clear previous error
		if (errorElement) {
			errorElement.style.display = 'none';
		}
		if (formGroup) {
			formGroup.classList.remove('error');
		}

		// Validate based on field type
		let errorMessage = '';

		switch (fieldName) {
			case 'name':
				if (!value) {
					errorMessage = 'Customer name is required';
				} else if (value.length < 2) {
					errorMessage = 'Customer name must be at least 2 characters';
				} else if (value.length > 100) {
					errorMessage = 'Customer name must be 100 characters or less';
				}
				break;

			case 'email':
				if (value && !isValidEmail(value)) {
					errorMessage = 'Please enter a valid email address';
				}
				break;

			case 'phone':
				if (value && value.length > 50) {
					errorMessage = 'Phone number must be 50 characters or less';
				}
				break;

			case 'contact_person':
				if (value && value.length > 100) {
					errorMessage = 'Contact person name must be 100 characters or less';
				}
				break;

			case 'address':
				if (value && value.length > 500) {
					errorMessage = 'Address must be 500 characters or less';
				}
				break;
		}

		// Show error if any
		if (errorMessage && errorElement && formGroup) {
			errorElement.textContent = errorMessage;
			errorElement.style.display = 'block';
			formGroup.classList.add('error');
		}
	}

	// Export functions for global access if needed
	window.ProjectControlCustomerForm = {
		validateFormData: validateFormData,
		submitCustomerData: submitCustomerData,
		showMessage: showMessage,
		validateField: validateField
	};

})();
