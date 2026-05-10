/**
 * Customer detail JavaScript for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function () {
	'use strict';

	// Initialize customer detail when DOM is ready
	document.addEventListener('DOMContentLoaded', function () {
		// Initialize Lucide icons if available
		if (window.LucideIcons && window.LucideIcons.initialize) {
			window.LucideIcons.initialize();
		}
		initializeCustomerDetail();
	});

	/**
	 * Initialize customer detail functionality
	 */
	function initializeCustomerDetail() {
		// Get customer ID from URL
		const customerId = getCustomerIdFromUrl();
		if (!customerId) {
			showError(t('projectcheck', 'Customer ID not found'));
			return;
		}

		// Load customer projects
		loadCustomerProjects(customerId);

		// Load customer statistics
		loadCustomerStats(customerId);

		// Add event listeners
		addEventListeners();
	}

	/**
	 * Get customer ID from URL
	 */
	function getCustomerIdFromUrl() {
		const pathParts = window.location.pathname.split('/');
		const customerIndex = pathParts.indexOf('customers');
		if (customerIndex !== -1 && pathParts[customerIndex + 1]) {
			return parseInt(pathParts[customerIndex + 1]);
		}
		return null;
	}

	/**
	 * Load customer projects
	 */
	function loadCustomerProjects(customerId) {
		const projectsList = document.getElementById('projects-list');
		if (!projectsList) return;

		// Show loading state
		projectsList.innerHTML = '<div class="loading">' + t('projectcheck', 'Loading projects...') + '</div>';

		// Make AJAX request to get projects for this customer
		fetch(`/apps/projectcheck/api/projects/by-customer/${customerId}`, {
			headers: {
				'requesttoken': window.projectControlData?.requestToken || ''
			}
		})
			.then(response => {
				if (!response.ok) {
					throw new Error(t('projectcheck', 'Failed to load projects'));
				}
				return response.json();
			})
			.then(data => {
				if (data.success && data.projects) {
					displayProjects(data.projects);
				} else {
					showNoProjects();
				}
			})
			.catch(error => {
				console.error('Error loading projects:', error);
				showError(t('projectcheck', 'Failed to load projects'));
			});
	}

	/**
	 * Display projects in the list
	 */
	function displayProjects(projects) {
		const projectsList = document.getElementById('projects-list');
		if (!projectsList) return;

		if (projects.length === 0) {
			showNoProjects();
			return;
		}

		const lblBudget = escapeHtml(t('projectcheck', 'Budget'));
		const lblProgress = escapeHtml(t('projectcheck', 'Progress'));
		const lblStart = escapeHtml(t('projectcheck', 'Start'));
		const lblEnd = escapeHtml(t('projectcheck', 'End'));

		const projectsHtml = projects.map(project => {
			const name = escapeHtml(String(project.name ?? ''));
			const status = escapeHtml(String(project.status ?? ''));
			const budget = escapeHtml(formatCurrency(project.budget));
			const progressNum = Number(project.progress);
			const progressTxt = Number.isFinite(progressNum)
				? escapeHtml(window.ProjectCheckFormat
					? window.ProjectCheckFormat.percent(progressNum, 0)
					: progressNum.toFixed(0) + '%')
				: '\u2014';
			const startDate = escapeHtml(formatDate(project.start_date));
			const endDate = escapeHtml(formatDate(project.end_date));
			const id = Number(project.id);
			const statusClass = escapeHtml(String((project.status ?? '').toLowerCase()));
			return `<div class="project-item">
				<div class="project-header">
					<h4><a href="/apps/projectcheck/projects/${id}">${name}</a></h4>
					<span class="project-status status-${statusClass}">${status}</span>
				</div>
				<div class="project-details">
					<div class="project-info">
						<span class="project-budget">${lblBudget}: ${budget}</span>
						<span class="project-progress">${lblProgress}: ${progressTxt}</span>
					</div>
					<div class="project-dates">
						<span class="project-start">${lblStart}: ${startDate}</span>
						<span class="project-end">${lblEnd}: ${endDate}</span>
					</div>
				</div>
			</div>`;
		}).join('');

		projectsList.innerHTML = projectsHtml;
	}

	/**
	 * Show no projects message
	 */
	function showNoProjects() {
		const projectsList = document.getElementById('projects-list');
		if (!projectsList) return;

		const customerId = getCustomerIdFromUrl();
		projectsList.innerHTML = `
			<div class="no-projects">
				<p>${t('projectcheck', 'No projects found for this customer.')}</p>
				<a href="/apps/projectcheck/projects/create?customer_id=${encodeURIComponent(String(customerId || ''))}" class="button primary">
					${t('projectcheck', 'Create First Project')}
				</a>
			</div>
		`;
	}

	/**
	 * Load customer statistics
	 */
	function loadCustomerStats(customerId) {
		// Make AJAX request to get customer statistics
		fetch(`/apps/projectcheck/api/customers/stats?customer_id=${customerId}`, {
			headers: {
				'requesttoken': window.projectControlData?.requestToken || ''
			}
		})
			.then(response => {
				if (!response.ok) {
					throw new Error('Failed to load statistics');
				}
				return response.json();
			})
			.then(data => {
				if (data.success && data.stats) {
					displayStats(data.stats);
				}
			})
			.catch(error => {
				console.error('Error loading statistics:', error);
				// Don't show error for stats, just leave them as "-"
			});
	}

	/**
	 * Display customer statistics
	 */
	function displayStats(stats) {
		const totalProjects = document.getElementById('total-projects');
		const activeProjects = document.getElementById('active-projects');
		const totalHours = document.getElementById('total-hours');
		const totalRevenue = document.getElementById('total-revenue');

		if (totalProjects) totalProjects.textContent = stats.total_projects || 0;
		if (activeProjects) activeProjects.textContent = stats.active_projects || 0;
		if (totalHours) totalHours.textContent = formatHours(stats.used_hours || 0);
		if (totalRevenue) totalRevenue.textContent = formatCurrency(stats.total_revenue || 0);
	}

	/**
	 * Add event listeners
	 */
	function addEventListeners() {
		// Add any additional event listeners here
	}

	/**
	 * Show error message
	 */
	function showError(message) {
		const projectsList = document.getElementById('projects-list');
		if (projectsList) {
			projectsList.innerHTML = `<div class="error">${escapeHtml(message)}</div>`;
		}
	}

	/**
	 * Utility function to escape HTML
	 */
	function escapeHtml(text) {
		const div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	/**
	 * Format currency in the user's locale and the org-configured currency.
	 * Delegates to {@link window.ProjectCheckFormat} so we never hard-code
	 * a locale or currency code (audit ref. B10).
	 */
	function formatCurrency(amount) {
		if (window.ProjectCheckFormat) {
			return window.ProjectCheckFormat.currencyFmt(amount);
		}
		const n = Number(amount);
		if (!Number.isFinite(n)) {
			return '\u2014';
		}
		// Boot-order fallback only; primary path is ProjectCheckFormat above.
		const code = (window.ProjectCheckConfig && typeof window.ProjectCheckConfig.currency === 'string'
			&& /^[A-Z]{3}$/i.test(window.ProjectCheckConfig.currency))
			? window.ProjectCheckConfig.currency.toUpperCase()
			: 'EUR';
		try {
			return new Intl.NumberFormat(undefined, { style: 'currency', currency: code }).format(n);
		} catch (e) {
			return code + ' ' + n.toFixed(2);
		}
	}

	function formatDate(dateString) {
		if (!dateString) return '\u2014';
		if (window.ProjectCheckFormat) {
			return window.ProjectCheckFormat.date(dateString);
		}
		const date = new Date(dateString);
		if (Number.isNaN(date.getTime())) {
			return '\u2014';
		}
		return date.toISOString().substring(0, 10);
	}

	function formatHours(hours) {
		const n = Number(hours);
		if (!Number.isFinite(n)) return '\u2014';
		if (window.ProjectCheckFormat) {
			return window.ProjectCheckFormat.hours(n);
		}
		return n.toFixed(1) + '\u00A0h';
	}

})();
