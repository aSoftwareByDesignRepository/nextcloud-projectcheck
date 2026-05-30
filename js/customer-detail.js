/**
 * Customer detail JavaScript for projectcheck app
 *
 * Legacy AJAX helper for optional #projects-list containers. The primary
 * customer detail page is server-rendered; this module stays DOM-safe and
 * webroot-aware when used.
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		if (window.LucideIcons && window.LucideIcons.initialize) {
			window.LucideIcons.initialize();
		}
		if (!document.getElementById('projects-list')) {
			return;
		}
		initializeCustomerDetail();
	});

	function initializeCustomerDetail() {
		const customerId = getCustomerIdFromUrl();
		if (!customerId) {
			showError(t('projectcheck', 'Customer ID not found'));
			return;
		}
		loadCustomerProjects(customerId);
		loadCustomerStats(customerId);
	}

	function getCustomerIdFromUrl() {
		const pathParts = window.location.pathname.split('/');
		const customerIndex = pathParts.indexOf('customers');
		if (customerIndex !== -1 && pathParts[customerIndex + 1]) {
			return parseInt(pathParts[customerIndex + 1], 10);
		}
		return window.projectControlData && window.projectControlData.customerId
			? window.projectControlData.customerId
			: null;
	}

	function requestHeaders() {
		const token = (typeof OC !== 'undefined' && OC.requestToken)
			|| (window.projectControlData && window.projectControlData.requestToken)
			|| '';
		return {
			requesttoken: token,
			'X-Requested-With': 'XMLHttpRequest'
		};
	}

	function loadCustomerProjects(customerId) {
		const projectsList = document.getElementById('projects-list');
		if (!projectsList) {
			return;
		}
		setListState(projectsList, 'div', 'loading', t('projectcheck', 'Loading projects...'));

		const url = typeof OC !== 'undefined' && OC.generateUrl
			? OC.generateUrl('/apps/projectcheck/api/projects/by-customer/{id}', { id: customerId })
			: '/apps/projectcheck/api/projects/by-customer/' + customerId;

		fetch(url, { headers: requestHeaders() })
			.then(function (response) {
				if (!response.ok) {
					throw new Error(t('projectcheck', 'Failed to load projects'));
				}
				return response.json();
			})
			.then(function (data) {
				if (data.success && data.projects) {
					displayProjects(data.projects);
				} else {
					showNoProjects();
				}
			})
			.catch(function (error) {
				console.error('Error loading projects:', error);
				showError(t('projectcheck', 'Failed to load projects'));
			});
	}

	function setListState(container, tagName, className, text) {
		container.replaceChildren();
		const el = document.createElement(tagName);
		if (className) {
			el.className = className;
		}
		el.textContent = text;
		container.appendChild(el);
	}

	function displayProjects(projects) {
		const projectsList = document.getElementById('projects-list');
		if (!projectsList) {
			return;
		}
		if (!projects.length) {
			showNoProjects();
			return;
		}

		projectsList.replaceChildren();
		projects.forEach(function (project) {
			projectsList.appendChild(buildProjectItem(project));
		});
	}

	function buildProjectItem(project) {
		const item = document.createElement('div');
		item.className = 'project-item';

		const header = document.createElement('div');
		header.className = 'project-header';

		const title = document.createElement('h4');
		const link = document.createElement('a');
		const projectId = Number(project.id);
		link.href = typeof OC !== 'undefined' && OC.generateUrl
			? OC.generateUrl('/apps/projectcheck/projects/{id}', { id: projectId })
			: '/apps/projectcheck/projects/' + projectId;
		link.appendChild(document.createTextNode(String(project.name ?? '')));
		title.appendChild(link);

		const status = document.createElement('span');
		status.className = 'project-status status-' + String((project.status ?? '').toLowerCase());
		status.appendChild(document.createTextNode(String(project.status ?? '')));

		header.appendChild(title);
		header.appendChild(status);

		const details = document.createElement('div');
		details.className = 'project-details';

		const info = document.createElement('div');
		info.className = 'project-info';
		info.appendChild(makeLabelSpan(t('projectcheck', 'Budget'), formatCurrency(project.budget)));
		const progressNum = Number(project.progress);
		const progressTxt = Number.isFinite(progressNum)
			? (window.ProjectCheckFormat
				? window.ProjectCheckFormat.percent(progressNum, 0)
				: progressNum.toFixed(0) + '%')
			: '\u2014';
		info.appendChild(makeLabelSpan(t('projectcheck', 'Progress'), progressTxt));

		const dates = document.createElement('div');
		dates.className = 'project-dates';
		dates.appendChild(makeLabelSpan(t('projectcheck', 'Start'), formatDate(project.start_date)));
		dates.appendChild(makeLabelSpan(t('projectcheck', 'End'), formatDate(project.end_date)));

		details.appendChild(info);
		details.appendChild(dates);
		item.appendChild(header);
		item.appendChild(details);
		return item;
	}

	function makeLabelSpan(label, value) {
		const span = document.createElement('span');
		span.appendChild(document.createTextNode(label + ': ' + value));
		return span;
	}

	function showNoProjects() {
		const projectsList = document.getElementById('projects-list');
		if (!projectsList) {
			return;
		}
		const customerId = getCustomerIdFromUrl();
		projectsList.replaceChildren();

		const wrap = document.createElement('div');
		wrap.className = 'no-projects';

		const message = document.createElement('p');
		message.appendChild(document.createTextNode(t('projectcheck', 'No projects found for this customer.')));
		wrap.appendChild(message);

		const link = document.createElement('a');
		link.className = 'button primary';
		link.href = typeof OC !== 'undefined' && OC.generateUrl
			? OC.generateUrl('/apps/projectcheck/projects/create', { customer_id: customerId })
			: '/apps/projectcheck/projects/create?customer_id=' + encodeURIComponent(String(customerId || ''));
		link.appendChild(document.createTextNode(t('projectcheck', 'Create First Project')));
		wrap.appendChild(link);
		projectsList.appendChild(wrap);
	}

	function loadCustomerStats(customerId) {
		const url = typeof OC !== 'undefined' && OC.generateUrl
			? OC.generateUrl('/apps/projectcheck/api/customers/stats', { customer_id: customerId })
			: '/apps/projectcheck/api/customers/stats?customer_id=' + customerId;

		fetch(url, { headers: requestHeaders() })
			.then(function (response) {
				if (!response.ok) {
					throw new Error('Failed to load statistics');
				}
				return response.json();
			})
			.then(function (data) {
				if (data.success && data.stats) {
					displayStats(data.stats);
				}
			})
			.catch(function (error) {
				console.error('Error loading statistics:', error);
			});
	}

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

	function showError(message) {
		const projectsList = document.getElementById('projects-list');
		if (!projectsList) {
			return;
		}
		const err = document.createElement('div');
		err.className = 'error';
		err.textContent = message;
		projectsList.replaceChildren(err);
	}

	function formatCurrency(amount) {
		if (window.ProjectCheckFormat) {
			return window.ProjectCheckFormat.currencyFmt(amount);
		}
		const n = Number(amount);
		if (!Number.isFinite(n)) {
			return '\u2014';
		}
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
