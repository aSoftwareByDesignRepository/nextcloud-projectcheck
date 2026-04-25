/**
 * Client-Side Validation Framework for ProjectControl App
 * Provides comprehensive form validation with real-time feedback
 */
/* global t */

function escapeHtml(text) {
	if (text == null) return '';
	const div = document.createElement('div');
	div.textContent = String(text);
	return div.innerHTML;
}

const ProjectControlValidation = {
  /**
   * Initialize validation system
   */
  init() {
    this.setupFormValidation();
    this.setupRealTimeValidation();
    this.setupCustomValidators();
  },

  // ===== VALIDATION RULES =====

  /**
   * Built-in validation rules
   */
  rules: {
    required: {
      test: (value) => !ProjectControlUtils.isEmpty(value),
      message: 'This field is required'
    },
    
    email: {
      test: (value) => ProjectControlUtils.isEmail(value),
      message: 'Please enter a valid email address'
    },
    
    url: {
      test: (value) => ProjectControlUtils.isUrl(value),
      message: 'Please enter a valid URL'
    },
    
    phone: {
      test: (value) => ProjectControlUtils.isPhone(value),
      message: 'Please enter a valid phone number'
    },
    
    numeric: {
      test: (value) => ProjectControlUtils.isNumeric(value),
      message: 'Please enter a valid number'
    },
    
    minLength: {
      test: (value, min) => value.length >= min,
      message: (min) => `Must be at least ${min} characters long`
    },
    
    maxLength: {
      test: (value, max) => value.length <= max,
      message: (max) => `Must be no more than ${max} characters long`
    },
    
    min: {
      test: (value, min) => parseFloat(value) >= parseFloat(min),
      message: (min) => `Must be at least ${min}`
    },
    
    max: {
      test: (value, max) => parseFloat(value) <= parseFloat(max),
      message: (max) => `Must be no more than ${max}`
    },
    
    pattern: {
      test: (value, pattern) => new RegExp(pattern).test(value),
      message: 'Please enter a valid value'
    },
    
    date: {
      test: (value) => !isNaN(Date.parse(value)),
      message: 'Please enter a valid date'
    },
    
    futureDate: {
      test: (value) => new Date(value) > new Date(),
      message: 'Date must be in the future'
    },
    
    pastDate: {
      test: (value) => new Date(value) < new Date(),
      message: 'Date must be in the past'
    },
    
    timeFormat: {
      test: (value) => /^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/.test(value),
      message: 'Please enter a valid time (HH:MM)'
    },
    
    timeRange: {
      test: (value, min, max) => {
        const time = value.split(':').map(Number);
        const minutes = time[0] * 60 + time[1];
        const minMinutes = min.split(':').map(Number).reduce((a, b) => a * 60 + b);
        const maxMinutes = max.split(':').map(Number).reduce((a, b) => a * 60 + b);
        return minutes >= minMinutes && minutes <= maxMinutes;
      },
      message: (min, max) => `Time must be between ${min} and ${max}`
    },
    
    projectName: {
      test: (value) => /^[a-zA-Z0-9\s\-_]{3,50}$/.test(value),
      message: 'Project name must be 3-50 characters and contain only letters, numbers, spaces, hyphens, and underscores'
    },
    
    customerName: {
      test: (value) => /^[a-zA-Z\s]{2,50}$/.test(value),
      message: 'Customer name must be 2-50 characters and contain only letters and spaces'
    },
    
    timeEntry: {
      test: (value) => /^([0-9]|[0-1][0-9]|2[0-3]):[0-5][0-9]$/.test(value),
      message: 'Please enter a valid time entry (HH:MM)'
    },
    
    timeEntryDuration: {
      test: (value, minMinutes = 15) => {
        const time = value.split(':').map(Number);
        const minutes = time[0] * 60 + time[1];
        return minutes >= minMinutes;
      },
      message: (minMinutes) => `Time entry must be at least ${minMinutes} minutes`
    },
    
    noOverlap: {
      test: async function(value, field, form) {
        // Enhanced time entry overlap validation
        const startTimeField = form.querySelector('[name="start_time"]');
        const endTimeField = form.querySelector('[name="end_time"]');
        const dateField = form.querySelector('[name="date"]');
        const projectField = form.querySelector('[name="project_id"]');
        
        if (!startTimeField || !endTimeField || !dateField || !projectField) {
          return true; // Can't validate without required fields
        }
        
        const startTime = startTimeField.value;
        const endTime = endTimeField.value;
        const date = dateField.value;
        const projectId = projectField.value;
        
        if (!startTime || !endTime || !date || !projectId) {
          return true; // Skip validation if fields are empty
        }
        
        // Check if end time is after start time
        const startMinutes = this.timeToMinutes(startTime);
        const endMinutes = this.timeToMinutes(endTime);
        
        if (endMinutes <= startMinutes) {
          return false; // End time must be after start time
        }
        
        // Check for overlaps with existing entries
        try {
          const response = await fetch(OC.generateUrl('/apps/projectcheck/time-entries/check-overlap'), {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'requesttoken': OC.requestToken
            },
            body: JSON.stringify({
              start_time: startTime,
              end_time: endTime,
              date: date,
              project_id: projectId,
              exclude_id: window.timeEntryFormData?.timeEntryId || null
            })
          });
          
          const result = await response.json();
          return !result.hasOverlap;
        } catch (error) {
          console.error('Error checking time overlap:', error);
          return true; // Allow submission if overlap check fails
        }
      },
      message: 'This time entry overlaps with an existing entry'
    },
    
    projectAssignment: {
      test: async function(value, field, form) {
        // Validate project assignment constraints
        const userId = field.getAttribute('data-user-id') || OC.getCurrentUser().uid;
        const projectId = value;
        
        if (!projectId) {
          return true; // Skip validation if no project selected
        }
        
        try {
          const response = await fetch(OC.generateUrl('/apps/projectcheck/projects/' + projectId + '/check-assignment'), {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'requesttoken': OC.requestToken
            },
            body: JSON.stringify({
              user_id: userId
            })
          });
          
          const result = await response.json();
          return result.canAssign;
        } catch (error) {
          console.error('Error checking project assignment:', error);
          return true; // Allow assignment if check fails
        }
      },
      message: 'User cannot be assigned to this project'
    },
    
    timeEntryRange: {
      test: function(value, minHours = 0.25, maxHours = 24) {
        // Validate time entry duration range
        const hours = parseFloat(value);
        if (isNaN(hours)) {
          return false;
        }
        return hours >= minHours && hours <= maxHours;
      },
      message: (minHours, maxHours) => `Time entry must be between ${minHours} and ${maxHours} hours`
    },
    
    workingHours: {
      test: function(value, field, form) {
        // Validate against working hours (e.g., 8 AM to 6 PM)
        const time = value;
        if (!time) return true;
        
        const [hours, minutes] = time.split(':').map(Number);
        const totalMinutes = hours * 60 + minutes;
        
        // Default working hours: 8 AM to 6 PM
        const workStart = 8 * 60; // 8 AM
        const workEnd = 18 * 60;  // 6 PM
        
        return totalMinutes >= workStart && totalMinutes <= workEnd;
      },
      message: 'Time must be within working hours (8 AM - 6 PM)'
    },
    
    projectBudget: {
      test: async function(value, field, form) {
        // Validate against project budget constraints
        const hours = parseFloat(value);
        const projectId = form.querySelector('[name="project_id"]')?.value;
        
        if (!projectId || isNaN(hours)) {
          return true;
        }
        
        try {
          const response = await fetch(OC.generateUrl('/apps/projectcheck/projects/' + projectId + '/budget-check'), {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'requesttoken': OC.requestToken
            },
            body: JSON.stringify({
              hours: hours,
              hourly_rate: form.querySelector('[name="hourly_rate"]')?.value || 0
            })
          });
          
          const result = await response.json();
          return result.withinBudget;
        } catch (error) {
          console.error('Error checking project budget:', error);
          return true; // Allow if budget check fails
        }
      },
      message: t('projectcheck', 'This time entry would exceed the project budget')
    },
    
    customerActive: {
      test: async function(value, field, form) {
        // Validate that customer is active
        const customerId = value;
        
        if (!customerId) {
          return true;
        }
        
        try {
          const response = await fetch(OC.generateUrl('/apps/projectcheck/customers/' + customerId + '/status'), {
            headers: {
              'requesttoken': OC.requestToken
            }
          });
          
          const result = await response.json();
          return result.isActive;
        } catch (error) {
          console.error('Error checking customer status:', error);
          return true; // Allow if status check fails
        }
      },
      message: 'This customer is inactive and cannot be assigned to new projects'
    }
  },

  // ===== CUSTOM VALIDATORS =====

  /**
   * Add custom validation rule
   */
  addRule(name, rule) {
    this.rules[name] = rule;
  },

  /**
   * Remove custom validation rule
   */
  removeRule(name) {
    delete this.rules[name];
  },

  /**
   * Setup custom validators
   */
  setupCustomValidators() {
    this.addRule('validCustomerEmail', {
      test: (value) => {
        return ProjectControlUtils.isEmail(value);
      },
      message: t('projectcheck', 'Please enter a valid customer email address')
    });
  },

  // ===== FORM VALIDATION =====

  /**
   * Setup form validation
   */
  setupFormValidation() {
    const forms = document.querySelectorAll('form[data-validate]');
    
    forms.forEach(form => {
      this.setupForm(form);
    });
  },

  /**
   * Setup form submission handling
   */
  setupFormSubmission(form) {
    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      
      // Show loading state
      this.showFormLoadingState(form, true);
      
      // Validate form before submission
      const isValid = await this.validateForm(form);
      
      if (!isValid) {
        this.showFormLoadingState(form, false);
        this.scrollToFirstError(form);
        return false;
      }
      
      // Submit form data
      try {
        await this.submitFormData(form);
      } catch (error) {
        console.error('Form submission error:', error);
        this.handleSubmissionError(form, error);
      } finally {
        this.showFormLoadingState(form, false);
      }
    });
  },

  /**
   * Submit form data with proper error handling
   */
  async submitFormData(form) {
    const formData = this.getFormData(form);
    const submitUrl = form.action || form.dataset.submitUrl;
    const method = form.method || 'POST';
    
    if (!submitUrl) {
      throw new Error('No submission URL specified');
    }
    
    const response = await fetch(submitUrl, {
      method: method,
      headers: {
        'Content-Type': 'application/json',
        'requesttoken': OC.requestToken
      },
      body: JSON.stringify(formData)
    });
    
    if (!response.ok) {
      const errorData = await response.json().catch(() => ({}));
      throw new Error(errorData.error || `HTTP ${response.status}: ${response.statusText}`);
    }
    
    const result = await response.json();
    
    if (result.success) {
      this.handleSubmissionSuccess(form, result);
    } else {
      this.handleSubmissionError(form, new Error(result.error || 'Form submission failed'));
    }
  },

  /**
   * Handle successful form submission
   */
  handleSubmissionSuccess(form, result) {
    // Show success message
    this.showFormSuccess(form);
    
    // Dispatch success event
    form.dispatchEvent(new CustomEvent('form-submission-success', {
      detail: { result, form }
    }));
    
    // Handle redirect if specified
    if (result.redirect) {
      setTimeout(() => {
        window.location.href = result.redirect;
      }, 1500);
    }
    
    // Reset form if specified
    if (result.resetForm !== false) {
      setTimeout(() => {
        this.resetForm(form);
      }, 2000);
    }
  },

  /**
   * Handle form submission error
   */
  handleSubmissionError(form, error) {
    // Show error message
    this.showFormError(form, error.message);
    
    // Dispatch error event
    form.dispatchEvent(new CustomEvent('form-submission-error', {
      detail: { error, form }
    }));
    
    // Preserve form data for retry
    this.preserveFormData(form);
  },

  /**
   * Show form loading state
   */
  showFormLoadingState(form, isLoading) {
    const submitButton = form.querySelector('[type="submit"]');
    const originalText = submitButton?.dataset.originalText || submitButton?.textContent;
    
    if (isLoading) {
      // Store original text
      if (submitButton && !submitButton.dataset.originalText) {
        submitButton.dataset.originalText = submitButton.textContent;
      }
      
      // Show loading state
      if (submitButton) {
        submitButton.disabled = true;
        submitButton.textContent = 'Submitting...';
        submitButton.classList.add('btn--loading');
      }
      
      form.classList.add('form--submitting');
    } else {
      // Restore original state
      if (submitButton) {
        submitButton.disabled = false;
        submitButton.textContent = originalText;
        submitButton.classList.remove('btn--loading');
      }
      
      form.classList.remove('form--submitting');
    }
  },

  /**
   * Show form error message
   */
  showFormError(form, message) {
    let errorContainer = form.querySelector('.form-submission-error');
    if (!errorContainer) {
      errorContainer = document.createElement('div');
      errorContainer.className = 'form-submission-error';
      form.insertBefore(errorContainer, form.firstChild);
    }
    
    errorContainer.innerHTML = `
      <div class="alert alert--error">
        <strong>Submission Error:</strong> ${escapeHtml(message)}
        <button type="button" class="alert__dismiss" onclick="this.parentElement.parentElement.remove()">×</button>
      </div>
    `;
    errorContainer.style.display = 'block';
  },

  /**
   * Show form info message
   */
  showFormInfo(form, message) {
    let infoContainer = form.querySelector('.form-submission-info');
    if (!infoContainer) {
      infoContainer = document.createElement('div');
      infoContainer.className = 'form-submission-info';
      form.insertBefore(infoContainer, form.firstChild);
    }
    
    infoContainer.innerHTML = `
      <div class="alert alert--info">
        <strong>Info:</strong> ${escapeHtml(message)}
        <button type="button" class="alert__dismiss" onclick="this.parentElement.parentElement.remove()">×</button>
      </div>
    `;
    infoContainer.style.display = 'block';
  },

  /**
   * Preserve form data for retry
   */
  preserveFormData(form) {
    const formData = this.getFormData(form);
    sessionStorage.setItem(`form_data_${form.id || 'default'}`, JSON.stringify({
      data: formData,
      timestamp: Date.now()
    }));
  },

  /**
   * Restore preserved form data
   */
  restoreFormData(form) {
    const stored = sessionStorage.getItem(`form_data_${form.id || 'default'}`);
    if (stored) {
      try {
        const { data, timestamp } = JSON.parse(stored);
        // Only restore if data is less than 1 hour old
        if (Date.now() - timestamp < 3600000) {
          this.setFormData(form, data);
          return true;
        }
      } catch (error) {
        console.error('Error restoring form data:', error);
      }
    }
    return false;
  },

  /**
   * Clear preserved form data
   */
  clearPreservedFormData(form) {
    sessionStorage.removeItem(`form_data_${form.id || 'default'}`);
  },

  /**
   * Scroll to first error in form
   */
  scrollToFirstError(form) {
    const firstError = form.querySelector('.form-input--error');
    if (firstError) {
      firstError.scrollIntoView({ 
        behavior: 'smooth', 
        block: 'center' 
      });
      firstError.focus();
    }
  },

  /**
   * Reset form and clear validation
   */
  resetForm(form) {
    form.reset();
    this.resetFormValidation(form);
    this.clearPreservedFormData(form);
    
    // Clear form-level messages
    const containers = form.querySelectorAll('.form-errors, .form-warnings, .form-success, .form-submission-error');
    containers.forEach(container => container.remove());
    
    // Reset form state
    form.classList.remove('form--valid', 'form--invalid', 'form--submitting');
  },

  /**
   * Setup individual form
   */
  setupForm(form) {
    // Setup form submission handling
    this.setupFormSubmission(form);

    // Setup field validation
    const fields = form.querySelectorAll('[data-validate]');
    fields.forEach(field => {
      this.setupField(field);
    });

    // Check for preserved form data on page load
    if (this.restoreFormData(form)) {
      this.showFormInfo(form, 'Previous form data has been restored. Please review and submit again.');
    }
  },

  /**
   * Setup individual field
   */
  setupField(field) {
    const rules = this.parseValidationRules(field.dataset.validate);
    
    // Store rules on field
    field.dataset.validationRules = JSON.stringify(rules);
    
    // Add validation event listeners
    field.addEventListener('blur', () => {
      this.validateField(field);
    });
    
    field.addEventListener('input', this.debounce(() => {
      this.validateField(field);
    }, 300));
  },

  /**
   * Parse validation rules from data attribute
   */
  parseValidationRules(rulesString) {
    const rules = [];
    const ruleStrings = rulesString.split('|');
    
    ruleStrings.forEach(ruleString => {
      const [ruleName, ...params] = ruleString.split(':');
      rules.push({
        name: ruleName,
        params: params
      });
    });
    
    return rules;
  },

  // ===== REAL-TIME VALIDATION =====

  /**
   * Setup real-time validation
   */
  setupRealTimeValidation() {
    // Global event listener for dynamic content
    document.addEventListener('input', (e) => {
      if (e.target.hasAttribute('data-validate')) {
        this.validateField(e.target);
      }
    });
  },

  /**
   * Validate single field
   */
  async validateField(field) {
    const rules = JSON.parse(field.dataset.validationRules || '[]');
    const value = field.value;
    const errors = [];
    const warnings = [];
    
    // Clear previous validation state
    this.clearFieldValidation(field);
    
    // Set validation state to pending for async validations
    this.setFieldValidationState(field, 'pending');
    
    // Validate against each rule
    for (const rule of rules) {
      const validator = this.rules[rule.name];
      if (!validator) continue;
      
      try {
        const result = await this.runValidator(validator, value, rule.params, field);
        
        if (result === false) {
          // Validation failed
          const message = typeof validator.message === 'function' 
            ? validator.message(...rule.params)
            : validator.message;
          errors.push(message);
        } else if (result === 'warning') {
          // Validation warning
          const message = typeof validator.message === 'function' 
            ? validator.message(...rule.params)
            : validator.message;
          warnings.push(message);
        }
      } catch (error) {
        console.error(`Validation error for rule ${rule.name}:`, error);
        errors.push('Validation error occurred');
      }
    }
    
    // Apply validation result
    if (errors.length > 0) {
      this.showFieldErrors(field, errors);
      this.setFieldValidationState(field, 'error');
    } else if (warnings.length > 0) {
      this.showFieldWarnings(field, warnings);
      this.setFieldValidationState(field, 'warning');
    } else {
      this.showFieldSuccess(field);
      this.setFieldValidationState(field, 'success');
    }
    
    // Track validation statistics
    this.trackValidationResult(field, errors.length === 0, errors, warnings);
    
    return errors.length === 0;
  },

  /**
   * Run validator function
   */
  async runValidator(validator, value, params, field) {
    if (typeof validator.test === 'function') {
      const result = validator.test(value, ...params, field, field.form);
      return result instanceof Promise ? await result : result;
    }
    return true;
  },

  /**
   * Validate entire form
   */
  async validateForm(form) {
    const fields = form.querySelectorAll('[data-validate]');
    const results = [];
    const allErrors = [];
    const allWarnings = [];
    
    // Show form-level validation state
    this.setFormValidationState(form, 'validating');
    
    for (const field of fields) {
      const isValid = await this.validateField(field);
      results.push(isValid);
      
      // Collect all errors and warnings
      const errorContainer = field.parentNode.querySelector('.form-error');
      const warningContainer = field.parentNode.querySelector('.form-warning');
      
      if (errorContainer) {
        allErrors.push({
          field: field.name,
          message: errorContainer.textContent
        });
      }
      
      if (warningContainer) {
        allWarnings.push({
          field: field.name,
          message: warningContainer.textContent
        });
      }
    }
    
    const formIsValid = results.every(result => result);
    
    if (formIsValid) {
      this.setFormValidationState(form, 'valid');
      this.showFormSuccess(form);
    } else {
      this.setFormValidationState(form, 'invalid');
      this.showFormErrors(form, allErrors);
    }
    
    if (allWarnings.length > 0) {
      this.showFormWarnings(form, allWarnings);
    }
    
    // Track form validation
    this.trackFormValidation(form, formIsValid, allErrors, allWarnings);
    
    return formIsValid;
  },

  /**
   * Set form validation state
   */
  setFormValidationState(form, state) {
    form.setAttribute('data-validation-state', state);
    form.classList.remove('form--validating', 'form--valid', 'form--invalid');
    form.classList.add(`form--${state}`);
  },

  /**
   * Show form success
   */
  showFormSuccess(form) {
    let successContainer = form.querySelector('.form-success');
    if (!successContainer) {
      successContainer = document.createElement('div');
      successContainer.className = 'form-success';
      form.insertBefore(successContainer, form.firstChild);
    }
    
    successContainer.textContent = 'Form is valid and ready to submit';
    successContainer.style.display = 'block';
    
    // Hide error and warning containers
    const errorContainer = form.querySelector('.form-errors');
    const warningContainer = form.querySelector('.form-warnings');
    
    if (errorContainer) {
      errorContainer.style.display = 'none';
    }
    if (warningContainer) {
      warningContainer.style.display = 'none';
    }
  },

  /**
   * Show form errors
   */
  showFormErrors(form, errors) {
    let errorContainer = form.querySelector('.form-errors');
    if (!errorContainer) {
      errorContainer = document.createElement('div');
      errorContainer.className = 'form-errors';
      form.insertBefore(errorContainer, form.firstChild);
    }
    
    errorContainer.innerHTML = `
      <h4>Please fix the following errors:</h4>
      <ul>
        ${errors.map(error => `<li><strong>${escapeHtml(error.field)}:</strong> ${escapeHtml(error.message)}</li>`).join('')}
      </ul>
    `;
    errorContainer.style.display = 'block';
    
    // Hide success container
    const successContainer = form.querySelector('.form-success');
    if (successContainer) {
      successContainer.style.display = 'none';
    }
  },

  /**
   * Show form warnings
   */
  showFormWarnings(form, warnings) {
    let warningContainer = form.querySelector('.form-warnings');
    if (!warningContainer) {
      warningContainer = document.createElement('div');
      warningContainer.className = 'form-warnings';
      form.insertBefore(warningContainer, form.firstChild);
    }
    
    warningContainer.innerHTML = `
      <h4>Please review the following warnings:</h4>
      <ul>
        ${warnings.map(warning => `<li><strong>${escapeHtml(warning.field)}:</strong> ${escapeHtml(warning.message)}</li>`).join('')}
      </ul>
    `;
    warningContainer.style.display = 'block';
  },

  /**
   * Track form validation for analytics
   */
  trackFormValidation(form, isValid, errors, warnings) {
    const formData = {
      formId: form.id || 'unknown',
      isValid: isValid,
      errorCount: errors.length,
      warningCount: warnings.length,
      fieldCount: form.querySelectorAll('[data-validate]').length,
      timestamp: new Date().toISOString()
    };
    
    // Store form validation data
    if (!window.formValidationAnalytics) {
      window.formValidationAnalytics = [];
    }
    window.formValidationAnalytics.push(formData);
    
    // Limit stored data
    if (window.formValidationAnalytics.length > 100) {
      window.formValidationAnalytics = window.formValidationAnalytics.slice(-50);
    }
  },

  // ===== UI FEEDBACK =====

  /**
   * Show field errors
   */
  showFieldErrors(field, errors) {
    // Add error class
    field.classList.add('form-input--error');
    field.classList.remove('form-input--success');
    
    // Create or update error message
    let errorContainer = field.parentNode.querySelector('.form-error');
    if (!errorContainer) {
      errorContainer = document.createElement('div');
      errorContainer.className = 'form-error';
      field.parentNode.appendChild(errorContainer);
    }
    
    errorContainer.textContent = errors[0]; // Show first error
    
    // Set aria attributes
    field.setAttribute('aria-invalid', 'true');
    field.setAttribute('aria-describedby', errorContainer.id || 'error-' + Date.now());
    
    // Dispatch validation event
    field.dispatchEvent(new CustomEvent('validation-error', {
      detail: { errors, field }
    }));
  },

  /**
   * Show field success
   */
  showFieldSuccess(field) {
    // Add success class
    field.classList.add('form-input--success');
    field.classList.remove('form-input--error');
    
    // Remove error message
    const errorContainer = field.parentNode.querySelector('.form-error');
    if (errorContainer) {
      errorContainer.remove();
    }
    
    // Clear aria attributes
    field.removeAttribute('aria-invalid');
    field.removeAttribute('aria-describedby');
    
    // Dispatch validation event
    field.dispatchEvent(new CustomEvent('validation-success', {
      detail: { field }
    }));
  },

  /**
   * Clear field validation
   */
  clearFieldValidation(field) {
    field.classList.remove('form-input--error', 'form-input--success', 'form-input--warning', 'form-input--pending');
    
    const errorContainer = field.parentNode.querySelector('.form-error');
    const warningContainer = field.parentNode.querySelector('.form-warning');
    const pendingContainer = field.parentNode.querySelector('.form-pending');
    
    if (errorContainer) {
      errorContainer.remove();
    }
    if (warningContainer) {
      warningContainer.remove();
    }
    if (pendingContainer) {
      pendingContainer.remove();
    }
    
    field.removeAttribute('aria-invalid');
    field.removeAttribute('aria-describedby');
    field.removeAttribute('data-validation-state');
  },

  /**
   * Set field validation state
   */
  setFieldValidationState(field, state) {
    field.setAttribute('data-validation-state', state);
    field.classList.remove('form-input--error', 'form-input--success', 'form-input--warning', 'form-input--pending');
    field.classList.add(`form-input--${state}`);
  },

  /**
   * Show field warnings
   */
  showFieldWarnings(field, warnings) {
    // Add warning class
    field.classList.add('form-input--warning');
    field.classList.remove('form-input--error', 'form-input--success', 'form-input--pending');
    
    // Create or update warning message
    let warningContainer = field.parentNode.querySelector('.form-warning');
    if (!warningContainer) {
      warningContainer = document.createElement('div');
      warningContainer.className = 'form-warning';
      field.parentNode.appendChild(warningContainer);
    }
    
    warningContainer.textContent = warnings[0]; // Show first warning
    
    // Set aria attributes
    field.setAttribute('aria-describedby', warningContainer.id || 'warning-' + Date.now());
    
    // Dispatch validation event
    field.dispatchEvent(new CustomEvent('validation-warning', {
      detail: { warnings, field }
    }));
  },

  /**
   * Track validation result for analytics
   */
  trackValidationResult(field, isValid, errors, warnings) {
    const validationData = {
      fieldName: field.name,
      fieldType: field.type,
      isValid: isValid,
      errorCount: errors.length,
      warningCount: warnings.length,
      timestamp: new Date().toISOString(),
      formId: field.form?.id || 'unknown'
    };
    
    // Store validation data for analytics
    if (!window.validationAnalytics) {
      window.validationAnalytics = [];
    }
    window.validationAnalytics.push(validationData);
    
    // Limit stored data to prevent memory issues
    if (window.validationAnalytics.length > 1000) {
      window.validationAnalytics = window.validationAnalytics.slice(-500);
    }
  },

  // ===== VALIDATION HELPERS =====

  /**
   * Get field value
   */
  getFieldValue(field) {
    if (field.type === 'checkbox') {
      return field.checked;
    } else if (field.type === 'radio') {
      const checkedRadio = field.form.querySelector(`input[name="${field.name}"]:checked`);
      return checkedRadio ? checkedRadio.value : '';
    } else if (field.tagName === 'SELECT') {
      return field.value;
    } else {
      return field.value;
    }
  },

  /**
   * Set field value
   */
  setFieldValue(field, value) {
    if (field.type === 'checkbox') {
      field.checked = value;
    } else if (field.type === 'radio') {
      const radio = field.form.querySelector(`input[name="${field.name}"][value="${value}"]`);
      if (radio) {
        radio.checked = true;
      }
    } else {
      field.value = value;
    }
  },

  /**
   * Get form data as object
   */
  getFormData(form) {
    const formData = new FormData(form);
    const data = {};
    
    for (const [key, value] of formData.entries()) {
      if (data[key]) {
        if (Array.isArray(data[key])) {
          data[key].push(value);
        } else {
          data[key] = [data[key], value];
        }
      } else {
        data[key] = value;
      }
    }
    
    return data;
  },

  /**
   * Set form data from object
   */
  setFormData(form, data) {
    Object.entries(data).forEach(([key, value]) => {
      const field = form.querySelector(`[name="${key}"]`);
      if (field) {
        this.setFieldValue(field, value);
      }
    });
  },

  /**
   * Reset form validation
   */
  resetFormValidation(form) {
    const fields = form.querySelectorAll('[data-validate]');
    fields.forEach(field => {
      this.clearFieldValidation(field);
    });
  },

  // ===== UTILITY FUNCTIONS =====

  /**
   * Debounce function
   */
  debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  },

  /**
   * Validate specific rule
   */
  validateRule(ruleName, value, params = []) {
    const rule = this.rules[ruleName];
    if (!rule) {
      throw new Error(`Unknown validation rule: ${ruleName}`);
    }
    
    return rule.test(value, ...params);
  },

  /**
   * Get validation message
   */
  getValidationMessage(ruleName, params = []) {
    const rule = this.rules[ruleName];
    if (!rule) {
      return 'Unknown validation rule';
    }
    
    return typeof rule.message === 'function' 
      ? rule.message(...params)
      : rule.message;
  },

  /**
   * Check if field is valid
   */
  isFieldValid(field) {
    return !field.classList.contains('form-input--error');
  },

  /**
   * Check if form is valid
   */
  isFormValid(form) {
    const fields = form.querySelectorAll('[data-validate]');
    return Array.from(fields).every(field => this.isFieldValid(field));
  },

  /**
   * Convert time string (HH:MM) to total minutes
   */
  timeToMinutes(time) {
    const [hours, minutes] = time.split(':').map(Number);
    return hours * 60 + minutes;
  },

  /**
   * Convert minutes to time string (HH:MM)
   */
  minutesToTime(minutes) {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${hours.toString().padStart(2, '0')}:${mins.toString().padStart(2, '0')}`;
  },

  /**
   * Calculate duration between two time strings
   */
  calculateDuration(startTime, endTime) {
    const startMinutes = this.timeToMinutes(startTime);
    const endMinutes = this.timeToMinutes(endTime);
    return endMinutes - startMinutes;
  },

  /**
   * Format duration in hours and minutes
   */
  formatDuration(minutes) {
    const hours = Math.floor(minutes / 60);
    const mins = minutes % 60;
    return `${hours}h ${mins}m`;
  }
};

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
  module.exports = ProjectControlValidation;
} else if (typeof window !== 'undefined') {
  window.ProjectCheckValidation = ProjectControlValidation;
  window.ProjectControlValidation = ProjectControlValidation;
}
