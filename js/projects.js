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

	// DOM elements
	const elements = {
		searchInput: document.getElementById('project-search'),
		statusFilter: document.getElementById('status-filter'),
		priorityFilter: document.getElementById('priority-filter'),
		projectTypeFilter: document.getElementById('project-type-filter'),
		customerFilter: document.getElementById('customer-filter'),
		applyFiltersBtn: document.getElementById('apply-filters'),
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
		// Only initialize if we're on the projects page
		if (!document.getElementById('projects-tbody') && !document.getElementById('project-search') && !document.querySelector('.projects-table')) {
			return;
		}
		bindEvents();
		updateProgressBars();
		initMessageAutoHide();
	}

	/**
	 * Bind event listeners
	 */
	function bindEvents() {
		// Search functionality (apply on Enter)
		if (elements.searchInput) {
			elements.searchInput.addEventListener('keydown', function (e) {
				if (e.key === 'Enter') {
					e.preventDefault();
					applyFilters();
				}
			});
		}

		// Apply filters button
		if (elements.applyFiltersBtn) {
			elements.applyFiltersBtn.addEventListener('click', function (e) {
				e.preventDefault();
				applyFilters();
			});
		}

		// Clear filters
		if (elements.clearFiltersBtn) {
			elements.clearFiltersBtn.addEventListener('click', clearFilters);
		}

		// Table sorting (click + keyboard for accessibility)
		if (elements.projectsTable) {
			const sortableHeaders = elements.projectsTable.querySelectorAll('th.sortable');
			sortableHeaders.forEach(header => {
				header.addEventListener('click', handleSort);
				header.addEventListener('keydown', function (e) {
					if (e.key === 'Enter' || e.key === ' ') {
						e.preventDefault();
						handleSort.call(this, e);
					}
				});
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
			if (e.target.closest('.delete-project-btn')) {
				if (deletionModalOpen) {
					return;
				}
				const button = e.target.closest('.delete-project-btn');
				if (button.disabled) {
					return;
				}
				const projectId = button.getAttribute('data-project-id');
				const projectName = button.getAttribute('data-project-name');
				showProjectDeletionModal(projectId, projectName, button);
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
	 * Apply all filters and search
	 */
	function applyFilters() {
		const searchTerm = elements.searchInput ? elements.searchInput.value : '';
		const status = elements.statusFilter ? elements.statusFilter.value : '';
		const priority = elements.priorityFilter ? elements.priorityFilter.value : '';
		const projectType = elements.projectTypeFilter ? elements.projectTypeFilter.value : '';
		const customerId = elements.customerFilter ? elements.customerFilter.value : '';

		// Build URL with filters
		const url = new URL(window.location);
		if (searchTerm) url.searchParams.set('search', searchTerm); else url.searchParams.delete('search');

		if (status && status !== 'all') {
			url.searchParams.set('status', status);
		} else if (status === 'all') {
			url.searchParams.set('status', 'all');
		} else {
			url.searchParams.delete('status');
		}

		if (priority) url.searchParams.set('priority', priority); else url.searchParams.delete('priority');

		if (projectType) url.searchParams.set('project_type', projectType); else url.searchParams.delete('project_type');

		if (customerId) url.searchParams.set('customer_id', customerId); else url.searchParams.delete('customer_id');

		url.searchParams.set('page', '1'); // reset to first page on filter apply

		// Preserve sort and direction
		const currentSort = url.searchParams.get('sort');
		const currentDirection = url.searchParams.get('direction');
		if (currentSort) url.searchParams.set('sort', currentSort);
		if (currentDirection) url.searchParams.set('direction', currentDirection);

		// Navigate to filtered URL
		window.location.href = url.toString();
	}

	/**
	 * Clear all filters
	 */
	function clearFilters() {
		if (elements.searchInput) elements.searchInput.value = '';
		if (elements.statusFilter) elements.statusFilter.value = 'Active';
		if (elements.priorityFilter) elements.priorityFilter.value = '';
		if (elements.projectTypeFilter) elements.projectTypeFilter.value = '';
		if (elements.customerFilter) elements.customerFilter.value = '';

		// Navigate to default state (Active status, default sort)
		const url = new URL(window.location);
		url.searchParams.set('status', 'Active');
		url.searchParams.delete('page');
		url.searchParams.delete('priority');
		url.searchParams.delete('project_type');
		url.searchParams.delete('customer_id');
		url.searchParams.delete('search');
		url.searchParams.delete('sort');
		url.searchParams.delete('direction');
		window.location.href = url.toString();
	}

	/**
	 * Handle table sorting
	 */
	function handleSort(e) {
		const header = e.currentTarget;
		const sortField = header.dataset.sort;
		if (!sortField) return;
		// When column is active: toggle direction. When inactive: first click = ascending
		const currentDirection = header.dataset.direction || 'desc';
		const newDirection = currentDirection === 'asc' ? 'desc' : 'asc';

		// Update URL with sort parameters
		const url = new URL(window.location);
		url.searchParams.set('sort', sortField);
		url.searchParams.set('direction', newDirection);
		url.searchParams.set('page', '1'); // reset to first page on sort change

		window.location.href = url.toString();
	}

	/**
	 * Show team members modal
	 */
	function showTeamMembers(projectId) {
		currentProjectId = projectId;

		setTeamListMessage('div', 'loading', t('projectcheck', 'Loading team members…'));
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
					renderTeamMembers(data.teamMembers, data.teamMembersFormer);
				} else {
					setTeamListMessage('p', 'error', t('projectcheck', 'Error loading team members'));
				}
			})
			.catch(error => {
				console.error('Error fetching team members:', error);
				setTeamListMessage('p', 'error', t('projectcheck', 'Error loading team members'));
			});
	}

	/**
	 * Render team members in modal (active = manageable; former = read-only)
	 */
	function setTeamListMessage(tagName, className, text) {
		const list = elements.teamMembersList;
		if (!list) {
			return;
		}
		list.replaceChildren();
		const el = document.createElement(tagName);
		el.className = className;
		el.textContent = text;
		list.appendChild(el);
	}

	function appendTeamMemberRow(parent, member, isFormer) {
		const row = document.createElement('div');
		row.className = 'team-member' + (isFormer ? ' team-member--former' : '');

		const info = document.createElement('div');
		info.className = 'team-member-info';

		const nameEl = document.createElement('div');
		nameEl.className = 'team-member-name';
		nameEl.appendChild(document.createTextNode(member.name || member.user_id || ''));
		if (isFormer) {
			const badge = document.createElement('span');
			badge.className = 'pc-badge pc-badge--neutral';
			badge.appendChild(document.createTextNode(' ' + t('projectcheck', 'Former')));
			nameEl.appendChild(badge);
		}

		const roleEl = document.createElement('div');
		roleEl.className = 'team-member-role';
		roleEl.appendChild(document.createTextNode(member.role || ''));

		info.appendChild(nameEl);
		info.appendChild(roleEl);

		if (member.hourly_rate !== null && member.hourly_rate !== undefined && member.hourly_rate !== '') {
			const rateEl = document.createElement('div');
			rateEl.className = 'team-member-rate';
			rateEl.appendChild(document.createTextNode(formatCurrency(member.hourly_rate) + '/h'));
			info.appendChild(rateEl);
		}

		row.appendChild(info);

		if (!isFormer) {
			const actions = document.createElement('div');
			actions.className = 'team-member-actions';
			const removeBtn = document.createElement('button');
			removeBtn.type = 'button';
			removeBtn.className = 'button secondary small remove-member-btn';
			removeBtn.dataset.userId = String(member.user_id || '');
			removeBtn.setAttribute('aria-label', t('projectcheck', 'Remove from project'));
			removeBtn.appendChild(document.createTextNode(t('projectcheck', 'Remove')));
			removeBtn.addEventListener('click', function () {
				removeTeamMember(currentProjectId, member);
			});
			actions.appendChild(removeBtn);
			row.appendChild(actions);
		}

		parent.appendChild(row);
	}

	function renderTeamMembers(teamMembers, teamMembersFormer) {
		const former = teamMembersFormer || [];
		const list = elements.teamMembersList;
		if (!list) {
			return;
		}
		list.replaceChildren();

		if (teamMembers.length === 0 && former.length === 0) {
			setTeamListMessage('p', '', t('projectcheck', 'No team members assigned to this project.'));
			return;
		}

		if (teamMembers.length > 0) {
			const activeGroup = document.createElement('div');
			activeGroup.setAttribute('role', 'group');
			activeGroup.setAttribute('aria-label', t('projectcheck', 'Current team'));
			teamMembers.forEach(function (member) {
				appendTeamMemberRow(activeGroup, member, false);
			});
			list.appendChild(activeGroup);
		}
		if (former.length > 0) {
			const intro = document.createElement('p');
			intro.className = 'team-modal-former-intro';
			intro.appendChild(document.createTextNode(t('projectcheck', 'Former team members (account removed)')));
			list.appendChild(intro);
			const formerGroup = document.createElement('div');
			formerGroup.setAttribute('role', 'group');
			formerGroup.setAttribute('aria-label', t('projectcheck', 'Former team members'));
			former.forEach(function (member) {
				appendTeamMemberRow(formerGroup, member, true);
			});
			list.appendChild(formerGroup);
		}
	}

	/**
	 * Remove team member
	 */
	function removeTeamMember(projectId, member) {
		if (typeof window.projectcheckDeletionModal === 'undefined') {
			showNotification(t('projectcheck', 'Could not open the confirmation dialog. Reload the page and try again.'), 'error');
			return;
		}

		const memberId = member && member.id != null ? member.id : null;
		const userId = member && member.user_id ? String(member.user_id) : '';
		const memberName = member && (member.name || member.user_id)
			? String(member.name || member.user_id)
			: t('projectcheck', 'Team member');

		const deleteUrl = OC.generateUrl('/apps/projectcheck/projects/{id}/members/{userId}/remove', {
			id: projectId,
			userId: userId
		});

		const modalOptions = {
			entityType: 'member',
			entityId: memberId != null ? memberId : userId,
			entityName: memberName,
			deleteUrl: deleteUrl,
			onSuccess: function () {
				showTeamMembers(projectId);
			},
			onCancel: function () {}
		};

		if (memberId != null) {
			modalOptions.impactUrl = OC.generateUrl('/apps/projectcheck/api/project-members/{id}/deletion-impact', {
				id: memberId
			});
		} else {
			modalOptions.simpleConfirm = true;
			modalOptions.confirmMessage = t('projectcheck', 'Are you sure you want to remove this team member?');
		}

		window.projectcheckDeletionModal.show(modalOptions);
	}

	/** @type {boolean} */
	let deletionModalOpen = false;

	/**
	 * Show project deletion modal
	 */
	function showProjectDeletionModal(projectId, projectName, triggerButton) {
		if (typeof window.projectcheckDeletionModal === 'undefined') {
			showNotification(t('projectcheck', 'Could not open the confirmation dialog. Reload the page and try again.'), 'error');
			return;
		}

		if (deletionModalOpen) {
			return;
		}
		deletionModalOpen = true;
		if (triggerButton) {
			triggerButton.disabled = true;
		}

		const deleteUrl = OC.generateUrl('/apps/projectcheck/projects/{id}/delete', { id: projectId });

		function releaseDeletionTrigger() {
			deletionModalOpen = false;
			if (triggerButton) {
				triggerButton.disabled = false;
			}
		}

		window.projectcheckDeletionModal.show({
			entityType: 'project',
			entityId: projectId,
			entityName: projectName,
			deleteUrl: deleteUrl,
			onSuccess: function () {
				releaseDeletionTrigger();
				const url = new URL(window.location.href);
				url.searchParams.set('message', 'success');
				url.searchParams.set('deleted', '1');
				window.location.href = url.toString();
			},
			onCancel: function () {
				releaseDeletionTrigger();
			},
			onRelease: releaseDeletionTrigger
		});
	}

	/**
	 * Delete project (opens accessible confirmation modal).
	 */
	window.confirmDeleteProject = function (projectId, projectName) {
		showProjectDeletionModal(projectId, projectName);
	};

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
		const text = String(message || '').trim();
		if (text === '') {
			return;
		}
		if (window.ProjectCheckNotify) {
			if (type === 'error') {
				window.ProjectCheckNotify.error(text);
			} else {
				window.ProjectCheckNotify.show(text, type);
			}
			return;
		}
		if (typeof OC !== 'undefined' && OC.Notification && typeof OC.Notification.showTemporary === 'function') {
			OC.Notification.showTemporary(text, type === 'error' ? { type: 'error' } : undefined);
			return;
		}
		const region = document.getElementById('pc-alert-region');
		if (region) {
			region.textContent = text;
		}
	}

	function formatCurrency(amount) {
		if (window.ProjectCheckFormat) {
			return window.ProjectCheckFormat.currencyFmt(amount);
		}
		const value = Number.parseFloat(amount);
		if (!Number.isFinite(value)) {
			return '\u2014';
		}
		const code = (window.ProjectCheckConfig && typeof window.ProjectCheckConfig.currency === 'string'
			&& /^[A-Z]{3}$/i.test(window.ProjectCheckConfig.currency))
			? window.ProjectCheckConfig.currency.toUpperCase()
			: 'EUR';
		return code + ' ' + value.toFixed(2);
	}

	/**
	 * Real-time budget calculation for forms
	 */
	function initBudgetCalculator() {
		const budgetInput = document.getElementById('total_budget');
		const rateInput = document.getElementById('hourly_rate');
		const hoursOutput = document.getElementById('available_hours');

		// Project create/edit uses project-form-cost-rates.js (mode-aware capacity).
		if (!budgetInput || !rateInput || !hoursOutput || hoursOutput.classList.contains('pc-capacity-input')) {
			return;
		}

		if (budgetInput && rateInput && hoursOutput) {
			function calculateHours() {
				const budget = parseFloat(budgetInput.value) || 0;
				const rate = parseFloat(rateInput.value) || 0;

				if (rate > 0) {
					const hours = budget / rate;
					if ('value' in hoursOutput) {
						hoursOutput.value = hours.toFixed(2);
					} else {
						hoursOutput.textContent = hours.toFixed(2);
					}
					hoursOutput.classList.remove('error', 'pc-capacity-input--unavailable');
				} else {
					if ('value' in hoursOutput) {
						hoursOutput.value = '';
						hoursOutput.placeholder = '—';
					} else {
						hoursOutput.textContent = '—';
					}
					hoursOutput.classList.add('pc-capacity-input--unavailable');
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
			return t('projectcheck', 'This field is required');
		}

		if (field.type === 'email') {
			return t('projectcheck', 'Please enter a valid email address');
		}

		if (field.type === 'number') {
			return t('projectcheck', 'Please enter a valid number');
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
