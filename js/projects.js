/**
 * Project Management JavaScript for the projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function () {
	'use strict';

	// Global variables
	let currentProjectId = null;
	let searchTimeout = null;

	// DOM elements
	const elements = {
		searchInput: document.getElementById('project-search'),
		statusFilter: document.getElementById('status-filter'),
		priorityFilter: document.getElementById('priority-filter'),
		projectTypeFilter: document.getElementById('project-type-filter'),
		clearFiltersBtn: document.getElementById('clear-filters'),
		projectsTable: document.querySelector('.projects-table'),
		projectsTbody: document.getElementById('projects-tbody'),
		teamModal: document.getElementById('team-modal'),
		deleteModal: document.getElementById('delete-modal'),
		confirmDeleteBtn: document.getElementById('confirm-delete'),
		teamMembersList: document.getElementById('team-members-list'),
		projectNameToDelete: document.querySelector('.project-name-to-delete')
	};

	/**
	 * Initialize the application
	 */
	function init() {
		console.log('ProjectControl app initializing...');
		bindEvents();
		updateProgressBars();
		initMessageAutoHide();
		console.log('ProjectControl app initialized');
	}

	/**
	 * Bind event listeners
	 */
	function bindEvents() {
		console.log('Binding events...');
		// Search functionality
		if (elements.searchInput) {
			elements.searchInput.addEventListener('input', handleSearch);
		}

		// Filter functionality
		if (elements.statusFilter) {
			elements.statusFilter.addEventListener('change', handleFilter);
		}

		if (elements.priorityFilter) {
			elements.priorityFilter.addEventListener('change', handleFilter);
		}

		if (elements.projectTypeFilter) {
			elements.projectTypeFilter.addEventListener('change', handleFilter);
		}

		// Clear filters
		if (elements.clearFiltersBtn) {
			elements.clearFiltersBtn.addEventListener('click', clearFilters);
		}

		// Table sorting
		if (elements.projectsTable) {
			const sortableHeaders = elements.projectsTable.querySelectorAll('th.sortable');
			sortableHeaders.forEach(header => {
				header.addEventListener('click', handleSort);
			});
		}

		// Team member buttons
		document.addEventListener('click', function (e) {
			if (e.target.classList.contains('show-team-btn')) {
				showTeamMembers(e.target.dataset.projectId);
			}
		});

		// Delete project buttons
		document.addEventListener('click', function (e) {
			console.log('Click event detected on:', e.target);
			if (e.target.closest('.delete-project-btn')) {
				const button = e.target.closest('.delete-project-btn');
				const projectId = button.getAttribute('data-project-id');
				const projectName = button.getAttribute('data-project-name');
				console.log('Delete button clicked for project:', projectId, projectName);
				showProjectDeletionModal(projectId, projectName);
			}
		});

		// Modal close buttons
		document.addEventListener('click', function (e) {
			if (e.target.classList.contains('modal-close')) {
				closeModal(e.target.closest('.modal'));
			}
		});

		// Confirm delete
		if (elements.confirmDeleteBtn) {
			elements.confirmDeleteBtn.addEventListener('click', confirmDelete);
		}

		// Close modal on outside click
		document.addEventListener('click', function (e) {
			if (e.target.classList.contains('modal')) {
				closeModal(e.target);
			}
		});

		// Close modal on escape key
		document.addEventListener('keydown', function (e) {
			if (e.key === 'Escape') {
				const openModal = document.querySelector('.modal.show');
				if (openModal) {
					closeModal(openModal);
				}
			}
		});
	}

	/**
	 * Handle search input
	 */
	function handleSearch() {
		clearTimeout(searchTimeout);
		searchTimeout = setTimeout(() => {
			applyFilters();
		}, 300);
	}

	/**
	 * Handle filter changes
	 */
	function handleFilter() {
		applyFilters();
	}

	/**
	 * Apply all filters and search
	 */
	function applyFilters() {
		const searchTerm = elements.searchInput ? elements.searchInput.value : '';
		const status = elements.statusFilter ? elements.statusFilter.value : '';
		const priority = elements.priorityFilter ? elements.priorityFilter.value : '';
		const projectType = elements.projectTypeFilter ? elements.projectTypeFilter.value : '';

		// Build URL with filters
		const url = new URL(window.location);
		if (searchTerm) url.searchParams.set('search', searchTerm);
		else url.searchParams.delete('search');

		if (status) url.searchParams.set('status', status);
		else url.searchParams.delete('status');

		if (priority) url.searchParams.set('priority', priority);
		else url.searchParams.delete('priority');

		if (projectType) url.searchParams.set('project_type', projectType);
		else url.searchParams.delete('project_type');

		// Navigate to filtered URL
		window.location.href = url.toString();
	}

	/**
	 * Clear all filters
	 */
	function clearFilters() {
		if (elements.searchInput) elements.searchInput.value = '';
		if (elements.statusFilter) elements.statusFilter.value = '';
		if (elements.priorityFilter) elements.priorityFilter.value = '';
		if (elements.projectTypeFilter) elements.projectTypeFilter.value = '';

		// Navigate to base URL
		const url = new URL(window.location);
		url.search = '';
		window.location.href = url.toString();
	}

	/**
	 * Handle table sorting
	 */
	function handleSort(e) {
		const header = e.currentTarget;
		const sortField = header.dataset.sort;
		const currentDirection = header.dataset.direction || 'asc';
		const newDirection = currentDirection === 'asc' ? 'desc' : 'asc';

		// Update URL with sort parameters
		const url = new URL(window.location);
		url.searchParams.set('sort', sortField);
		url.searchParams.set('direction', newDirection);

		window.location.href = url.toString();
	}

	/**
	 * Show team members modal
	 */
	function showTeamMembers(projectId) {
		currentProjectId = projectId;

		// Show loading state
		elements.teamMembersList.innerHTML = '<div class="loading">Loading team members...</div>';
		elements.teamModal.classList.add('show');

		// Fetch team members
		fetch(OC.generateUrl('/apps/projectcheck/projects/' + projectId + '/members'), {
			headers: {
				'X-Requested-With': 'XMLHttpRequest',
				'requesttoken': OC.requestToken
			}
		})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					renderTeamMembers(data.teamMembers);
				} else {
					elements.teamMembersList.innerHTML = '<p class="error">Error loading team members</p>';
				}
			})
			.catch(error => {
				console.error('Error fetching team members:', error);
				elements.teamMembersList.innerHTML = '<p class="error">Error loading team members</p>';
			});
	}

	/**
	 * Render team members in modal
	 */
	function renderTeamMembers(teamMembers) {
		if (teamMembers.length === 0) {
			elements.teamMembersList.innerHTML = '<p>No team members assigned to this project.</p>';
			return;
		}

		const html = teamMembers.map(member => `
			<div class="team-member">
				<div class="team-member-info">
					<div class="team-member-name">${escapeHtml(member.user_id)}</div>
					<div class="team-member-role">${escapeHtml(member.role)}</div>
					${member.hourly_rate ? `<div class="team-member-rate">${member.hourly_rate} €/h</div>` : ''}
				</div>
				<div class="team-member-actions">
					<button class="button secondary small remove-member-btn" data-user-id="${member.user_id}">
						Remove
					</button>
				</div>
			</div>
		`).join('');

		elements.teamMembersList.innerHTML = html;

		// Bind remove member events
		elements.teamMembersList.querySelectorAll('.remove-member-btn').forEach(btn => {
			btn.addEventListener('click', function () {
				removeTeamMember(currentProjectId, this.dataset.userId);
			});
		});
	}

	/**
	 * Remove team member
	 */
	function removeTeamMember(projectId, userId) {
		if (!confirm('Are you sure you want to remove this team member?')) {
			return;
		}

		fetch(OC.generateUrl('/apps/projectcheck/projects/' + projectId + '/members/' + userId), {
			method: 'DELETE',
			headers: {
				'Content-Type': 'application/json',
				'X-Requested-With': 'XMLHttpRequest',
				'requesttoken': OC.requestToken
			}
		})
			.then(response => response.json())
			.then(data => {
				if (data.success) {
					// Refresh team members list
					showTeamMembers(projectId);
				} else {
					alert(t('projectcheck', 'Error removing team member') + ': ' + data.error);
				}
			})
			.catch(error => {
				console.error('Error removing team member:', error);
				alert(t('projectcheck', 'Error removing team member. Please try again.'));
			});
	}

	/**
	 * Show delete confirmation modal
	 */
	function showDeleteConfirmation(projectId, projectName) {
		currentProjectId = projectId;
		elements.projectNameToDelete.textContent = projectName;
		elements.deleteModal.classList.add('show');
	}

	/**
	 * Global function for delete confirmation
	 */
	/**
	 * Show project deletion modal
	 */
	function showProjectDeletionModal(projectId, projectName) {
		if (typeof window.projectcheckDeletionModal === 'undefined') {
			console.error('Deletion modal not loaded');
			// Fallback to old method
			confirmDeleteProject(projectId, projectName);
			return;
		}

		const deleteUrl = OC.generateUrl('/apps/projectcheck/projects/' + projectId);

		// Show the modal
		window.projectcheckDeletionModal.show({
			entityType: 'project',
			entityId: projectId,
			entityName: projectName,
			deleteUrl: deleteUrl,
			onSuccess: function (entity) {
				// Remove the row from the table
				const row = document.querySelector(`tr[data-project-id="${entity.id}"]`);
				if (row) {
					row.remove();
				}

				// Show success message and reload after delay
				showNotification('Project deleted successfully', 'success');
				setTimeout(() => {
					window.location.reload();
				}, 1000);
			},
			onCancel: function () {
				console.log('Project deletion cancelled');
			}
		});
	}

	/**
	 * Confirm delete project function (global) - fallback method
	 */
	window.confirmDeleteProject = function (projectId, projectName = 'this project') {
		if (confirm(`Are you sure you want to delete "${projectName}"? This action cannot be undone.`)) {
			deleteProject(projectId);
		}
	};

	/**
	 * Delete project function
	 */
	function deleteProject(projectId) {
		console.log('deleteProject called with projectId:', projectId);
		// Show loading state
		const deleteBtn = document.querySelector(`button[data-project-id="${projectId}"]`);
		console.log('Found delete button:', deleteBtn);
		if (deleteBtn) {
			const originalContent = deleteBtn.innerHTML;
			deleteBtn.innerHTML = '<span class="icon icon-loading"></span>';
			deleteBtn.disabled = true;

			// Send delete request
			const deleteUrl = OC.generateUrl('/apps/projectcheck/projects/' + projectId);
			console.log('Sending DELETE request to:', deleteUrl);
			console.log('OC.requestToken:', OC.requestToken);
			fetch(deleteUrl, {
				method: 'DELETE',
				headers: {
					'X-Requested-With': 'XMLHttpRequest',
					'requesttoken': OC.requestToken
				}
			})
				.then(response => {
					if (!response.ok) {
						throw new Error(`HTTP error! status: ${response.status}`);
					}
					return response.json();
				})
				.then(data => {
					if (data.success) {
						// Remove project row from table
						const projectRow = deleteBtn.closest('tr');
						if (projectRow) {
							projectRow.remove();
						}

					// Show success message
					showNotification(t('projectcheck', 'Project deleted successfully'), 'success');

					// Reload page to update stats
					setTimeout(() => {
						window.location.reload();
					}, 1000);
				} else {
					alert(t('projectcheck', 'Error deleting project') + ': ' + (data.error || t('projectcheck', 'Unknown error')));
				}
			})
			.catch(error => {
				console.error('Error deleting project:', error);
				alert(t('projectcheck', 'Error deleting project. Please try again.'));
			})
				.finally(() => {
					// Reset button state
					if (deleteBtn) {
						deleteBtn.innerHTML = originalContent;
						deleteBtn.disabled = false;
					}
				});
		}
	}

	/**
	 * Close modal
	 */
	function closeModal(modal) {
		modal.classList.remove('show');
		currentProjectId = null;
	}

	/**
	 * Update progress bars with warning colors
	 */
	function updateProgressBars() {
		const progressBars = document.querySelectorAll('.progress-fill');
		progressBars.forEach(bar => {
			const width = parseFloat(bar.style.width);
			bar.classList.remove('warning', 'critical');

			if (width >= 100) {
				bar.classList.add('critical');
			} else if (width >= 90) {
				bar.classList.add('warning');
			}
		});
	}

	/**
	 * Show notification
	 */
	function showNotification(message, type = 'info') {
		// Use Nextcloud's notification system if available
		if (typeof OC !== 'undefined' && OC.Notification) {
			OC.Notification.show(message, { type: type });
		} else {
			// Fallback to alert
			alert(message);
		}
	}

	/**
	 * Escape HTML to prevent XSS
	 */
	function escapeHtml(text) {
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	/**
	 * Real-time budget calculation for forms
	 */
	function initBudgetCalculator() {
		const budgetInput = document.getElementById('total_budget');
		const rateInput = document.getElementById('hourly_rate');
		const hoursOutput = document.getElementById('available_hours');

		if (budgetInput && rateInput && hoursOutput) {
			function calculateHours() {
				const budget = parseFloat(budgetInput.value) || 0;
				const rate = parseFloat(rateInput.value) || 0;

				if (rate > 0) {
					const hours = budget / rate;
					hoursOutput.textContent = hours.toFixed(2);
					hoursOutput.classList.remove('error');
				} else {
					hoursOutput.textContent = '0.00';
					hoursOutput.classList.add('error');
				}
			}

			budgetInput.addEventListener('input', calculateHours);
			rateInput.addEventListener('input', calculateHours);

			// Calculate on page load
			calculateHours();
		}
	}

	/**
	 * Form validation
	 */
	function initFormValidation() {
		const form = document.querySelector('form[data-validate]');
		if (!form) return;

		const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');

		inputs.forEach(input => {
			input.addEventListener('blur', validateField);
			input.addEventListener('input', clearFieldError);
		});

		form.addEventListener('submit', validateForm);
	}

	/**
	 * Validate individual field
	 */
	function validateField(e) {
		const field = e.target;
		const value = field.value.trim();
		const isValid = validateFieldValue(field, value);

		if (!isValid) {
			showFieldError(field, getFieldErrorMessage(field));
		} else {
			clearFieldError(field);
		}
	}

	/**
	 * Validate field value
	 */
	function validateFieldValue(field, value) {
		if (field.hasAttribute('required') && !value) {
			return false;
		}

		if (field.type === 'email' && value) {
			const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			return emailRegex.test(value);
		}

		if (field.type === 'number' && value) {
			const num = parseFloat(value);
			const min = field.getAttribute('min');
			const max = field.getAttribute('max');

			if (isNaN(num)) return false;
			if (min && num < parseFloat(min)) return false;
			if (max && num > parseFloat(max)) return false;
		}

		if (field.hasAttribute('maxlength') && value.length > parseInt(field.getAttribute('maxlength'))) {
			return false;
		}

		return true;
	}

	/**
	 * Get field error message
	 */
	function getFieldErrorMessage(field) {
		if (field.hasAttribute('data-error-message')) {
			return field.getAttribute('data-error-message');
		}

		if (field.hasAttribute('required') && !field.value.trim()) {
			return 'This field is required';
		}

		if (field.type === 'email') {
			return 'Please enter a valid email address';
		}

		if (field.type === 'number') {
			return 'Please enter a valid number';
		}

		return t('projectcheck', 'Invalid value');
	}

	/**
	 * Show field error
	 */
	function showFieldError(field, message) {
		clearFieldError(field);

		field.classList.add('error');

		const errorDiv = document.createElement('div');
		errorDiv.className = 'field-error';
		errorDiv.textContent = message;
		errorDiv.style.color = 'var(--color-error)';
		errorDiv.style.fontSize = '12px';
		errorDiv.style.marginTop = '5px';

		field.parentNode.appendChild(errorDiv);
	}

	/**
	 * Clear field error
	 */
	function clearFieldError(field) {
		field.classList.remove('error');
		const errorDiv = field.parentNode.querySelector('.field-error');
		if (errorDiv) {
			errorDiv.remove();
		}
	}

	/**
	 * Validate entire form
	 */
	function validateForm(e) {
		const form = e.target;
		const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
		let isValid = true;

		inputs.forEach(input => {
			const value = input.value.trim();
			if (!validateFieldValue(input, value)) {
				showFieldError(input, getFieldErrorMessage(input));
				isValid = false;
			}
		});

		if (!isValid) {
			e.preventDefault();
			return false;
		}

		return true;
	}

	// Initialize when DOM is ready
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}

	// Check if OC object is available
	console.log('OC object available:', typeof OC !== 'undefined');
	if (typeof OC !== 'undefined') {
		console.log('OC.requestToken available:', typeof OC.requestToken !== 'undefined');
		console.log('OC.generateUrl available:', typeof OC.generateUrl !== 'undefined');
	}

	/**
	 * Initialize auto-hide for success/error messages
	 */
	function initMessageAutoHide() {
		const messages = document.querySelectorAll('.message');
		messages.forEach(message => {
			// Auto-hide after 5 seconds
			setTimeout(() => {
				message.style.opacity = '0';
				message.style.transform = 'translateY(-10px)';
				setTimeout(() => {
					message.remove();
				}, 300);
			}, 5000);

			// Add click to dismiss functionality
			message.addEventListener('click', () => {
				message.style.opacity = '0';
				message.style.transform = 'translateY(-10px)';
				setTimeout(() => {
					message.remove();
				}, 300);
			});

			// Add cursor pointer to indicate clickable
			message.style.cursor = 'pointer';
		});
	}

	// Initialize form-specific features
	document.addEventListener('DOMContentLoaded', function () {
		initBudgetCalculator();
		initFormValidation();
	});

})();
