/**
 * Dashboard JavaScript for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function () {
	'use strict';

	// Initialize dashboard when DOM is ready
	document.addEventListener('DOMContentLoaded', function () {
		// Initialize Lucide icons if available
		if (window.LucideIcons && window.LucideIcons.initialize) {
			window.LucideIcons.initialize();
		}
		initializeDashboard();
	});

	/**
	 * Initialize dashboard functionality
	 */
	function initializeDashboard() {
		// Add event listeners for interactive elements
		addEventListeners();

		// Initialize progress bars with animation
		initializeProgressBars();

		// Set up auto-refresh for stats (every 5 minutes)
		setupAutoRefresh();
	}

	/**
	 * Add event listeners to dashboard elements
	 */
	function addEventListeners() {
		// Add click handlers for project items
		const projectItems = document.querySelectorAll('.project-item');
		projectItems.forEach(function (item) {
			item.addEventListener('click', function (e) {
				// Don't trigger if clicking on action buttons
				if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON') {
					return;
				}

				// Find the view link and navigate to it
				const viewLink = item.querySelector('.project-actions a');
				if (viewLink) {
					window.location.href = viewLink.href;
				}
			});
		});

		// Add hover effects for stat cards
		const statCards = document.querySelectorAll('.stat-card');
		statCards.forEach(function (card) {
			card.addEventListener('mouseenter', function () {
				this.style.transform = 'translateY(-2px)';
			});

			card.addEventListener('mouseleave', function () {
				this.style.transform = 'translateY(0)';
			});
		});

		// Add click handlers for quick action buttons
		const actionButtons = document.querySelectorAll('.action-buttons .button');
		actionButtons.forEach(function (button) {
			button.addEventListener('click', function (e) {
				// Add loading state
				const originalText = this.textContent;
				this.textContent = 'Loading...';
				this.disabled = true;

				// Re-enable after navigation
				setTimeout(function () {
					button.textContent = originalText;
					button.disabled = false;
				}, 1000);
			});
		});
	}

	/**
	 * Initialize progress bars with animation
	 */
	function initializeProgressBars() {
		const progressBars = document.querySelectorAll('.progress-fill');

		progressBars.forEach(function (bar) {
			const width = bar.style.width;
			const percentage = parseFloat(width);

			// Reset width to 0 for animation
			bar.style.width = '0%';

			// Animate to target width
			setTimeout(function () {
				bar.style.width = width;

				// Add warning/critical classes based on percentage
				if (percentage >= 90) {
					bar.classList.add('critical');
				} else if (percentage >= 80) {
					bar.classList.add('warning');
				}
			}, 100);
		});
	}

	/**
	 * Set up auto-refresh for dashboard stats
	 */
	function setupAutoRefresh() {
		// Refresh stats every 5 minutes
		setInterval(function () {
			refreshStats();
		}, 5 * 60 * 1000);
	}

	/**
	 * Refresh dashboard statistics via AJAX
	 */
	function refreshStats() {
		fetch(OC.generateUrl('/apps/projectcheck/api/dashboard/stats'), {
			method: 'GET',
			headers: {
				'Content-Type': 'application/json',
				'requesttoken': OC.requestToken
			}
		})
			.then(function (response) {
				if (!response.ok) {
					throw new Error('Network response was not ok');
				}
				return response.json();
			})
			.then(function (data) {
				updateStatsDisplay(data);
			})
			.catch(function (error) {
				console.error('Error refreshing stats:', error);
			});
	}

	/**
	 * Update the stats display with new data
	 */
	function updateStatsDisplay(stats) {
		// Update stat numbers with animation
		updateStatNumber('totalProjects', stats.totalProjects);
		updateStatNumber('activeProjects', stats.activeProjects);
		updateStatNumber('completedProjects', stats.completedProjects);
		updateStatNumber('totalBudget', stats.totalBudget, true);

		// Update progress bar
		updateProgressBar(stats.consumptionPercentage);

		// Update recent projects if available
		if (stats.recentProjects) {
			updateRecentProjects(stats.recentProjects);
		}
	}

	/**
	 * Update a stat number with animation
	 */
	function updateStatNumber(statType, newValue, isCurrency = false) {
		const statElements = document.querySelectorAll('.stat-number');

		statElements.forEach(function (element) {
			const parentCard = element.closest('.stat-card');
			const statLabel = parentCard.querySelector('.stat-label');

			if (statLabel.textContent.toLowerCase().includes(statType.toLowerCase())) {
				const currentValue = parseFloat(element.textContent.replace(/[^0-9.-]+/g, ''));
				const targetValue = parseFloat(newValue);

				if (currentValue !== targetValue) {
					animateNumber(element, currentValue, targetValue, isCurrency);
				}
			}
		});
	}

	/**
	 * Animate number change
	 */
	function animateNumber(element, start, end, isCurrency = false) {
		const duration = 1000; // 1 second
		const startTime = performance.now();

		function updateNumber(currentTime) {
			const elapsed = currentTime - startTime;
			const progress = Math.min(elapsed / duration, 1);

			// Easing function for smooth animation
			const easeOutQuart = 1 - Math.pow(1 - progress, 4);
			const currentValue = start + (end - start) * easeOutQuart;

			// Format the number
			let formattedValue;
			if (isCurrency) {
				formattedValue = '€' + currentValue.toFixed(2);
			} else {
				formattedValue = Math.round(currentValue).toString();
			}

			element.textContent = formattedValue;

			if (progress < 1) {
				requestAnimationFrame(updateNumber);
			}
		}

		requestAnimationFrame(updateNumber);
	}

	/**
	 * Update progress bar with new percentage
	 */
	function updateProgressBar(percentage) {
		const progressFill = document.querySelector('.progress-fill');
		const progressText = document.querySelector('.progress-text');

		if (progressFill && progressText) {
			// Animate to new width
			progressFill.style.width = percentage + '%';

			// Update text
			progressText.textContent = percentage + '% consumed';

			// Update classes based on percentage
			progressFill.classList.remove('warning', 'critical');
			if (percentage >= 90) {
				progressFill.classList.add('critical');
			} else if (percentage >= 80) {
				progressFill.classList.add('warning');
			}
		}
	}

	/**
	 * Update recent projects list
	 */
	function updateRecentProjects(projects) {
		const projectList = document.querySelector('.project-list');

		if (!projectList || !projects.length) {
			return;
		}

		// Clear existing projects
		projectList.innerHTML = '';

		// Add new projects
		projects.forEach(function (project) {
			const projectItem = createProjectItem(project);
			projectList.appendChild(projectItem);
		});

		// Re-add event listeners
		addEventListeners();
	}

	/**
	 * Create a project item element
	 */
	function createProjectItem(project) {
		const item = document.createElement('div');
		item.className = 'project-item';

		item.innerHTML = `
			<div class="project-info">
				<div class="project-name">${escapeHtml(project.name)}</div>
				<div class="project-status status-${project.status.toLowerCase()}">
					${escapeHtml(project.status)}
				</div>
			</div>
			<div class="project-budget">
				€${parseFloat(project.totalBudget).toFixed(2)}
			</div>
			<div class="project-actions">
				<a href="${OC.generateUrl('/apps/projectcheck/projects/' + project.id)}" class="button">View</a>
			</div>
		`;

		return item;
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
	 * Show notification message
	 */
	function showNotification(message, type = 'info') {
		const notification = document.createElement('div');
		notification.className = `notification notification-${type}`;
		notification.textContent = message;

		// Add to page
		document.body.appendChild(notification);

		// Remove after 3 seconds
		setTimeout(function () {
			notification.remove();
		}, 3000);
	}

	/**
	 * Show productivity info popup
	 */
	function showProductivityInfoPopup() {
		const popup = document.getElementById('productivity-info-popup');
		if (popup) {
			popup.style.display = 'flex';
			document.body.style.overflow = 'hidden';
		}
	}

	/**
	 * Hide productivity info popup
	 */
	function hideProductivityInfoPopup() {
		const popup = document.getElementById('productivity-info-popup');
		if (popup) {
			popup.style.display = 'none';
			document.body.style.overflow = 'auto';
		}
	}

	// Make functions globally available
	window.showProductivityInfoPopup = showProductivityInfoPopup;
	window.hideProductivityInfoPopup = hideProductivityInfoPopup;

	// Add event listeners for popup
	document.addEventListener('click', function (event) {
		const popup = document.getElementById('productivity-info-popup');
		if (popup && event.target === popup) {
			hideProductivityInfoPopup();
		}
	});

	// Close popup with Escape key
	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape') {
			hideProductivityInfoPopup();
		}
	});

	// Export functions for global access if needed
	window.ProjectControlDashboard = {
		refreshStats: refreshStats,
		showNotification: showNotification,
		showProductivityInfoPopup: showProductivityInfoPopup,
		hideProductivityInfoPopup: hideProductivityInfoPopup
	};

})();
