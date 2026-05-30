/**
 * Dashboard JavaScript for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function () {
	'use strict';

	const REFRESH_INTERVAL_MS = 5 * 60 * 1000;

	document.addEventListener('DOMContentLoaded', function () {
		if (window.LucideIcons && window.LucideIcons.initialize) {
			window.LucideIcons.initialize();
		}
		initializeDashboard();
	});

	function initializeDashboard() {
		enhanceOverviewStats();
		enhanceProjectCards();
		animateProgressBars();
		setupAutoRefresh();
	}

	/**
	 * Subtle hover feedback on overview stat tiles (matches .overview-stat-compact).
	 */
	function enhanceOverviewStats() {
		document.querySelectorAll('.overview-stat-compact').forEach(function (card) {
			card.addEventListener('mouseenter', function () {
				this.style.transform = 'translateY(-2px)';
			});
			card.addEventListener('mouseleave', function () {
				this.style.transform = '';
			});
		});
	}

	/**
	 * Entire recent-project card navigates via its primary title link (keyboard friendly).
	 */
	function enhanceProjectCards() {
		document.querySelectorAll('.project-card.dashboard-card').forEach(function (card) {
			const primaryLink = card.querySelector('.project-name a');
			if (!primaryLink) {
				return;
			}
			card.setAttribute('tabindex', '0');
			card.setAttribute('role', 'link');
			card.setAttribute('aria-label', primaryLink.textContent.trim());

			function goToProject(event) {
				if (event.target.closest('a, button')) {
					return;
				}
				primaryLink.click();
			}

			card.addEventListener('click', goToProject);
			card.addEventListener('keydown', function (event) {
				if (event.key === 'Enter' || event.key === ' ') {
					event.preventDefault();
					primaryLink.click();
				}
			});
		});
	}

	/**
	 * Animate progress bars rendered server-side (.progress-fill, .budget-progress-fill).
	 */
	function animateProgressBars() {
		document.querySelectorAll('.progress-fill, .budget-progress-fill').forEach(function (bar) {
			const width = bar.style.width;
			if (!width || width === '0%') {
				return;
			}
			const percentage = parseFloat(width);
			const warnThreshold = parseFloat(bar.dataset.warningThreshold || bar.closest('[data-warning-threshold]')?.dataset.warningThreshold || '80');
			const critThreshold = parseFloat(bar.dataset.criticalThreshold || bar.closest('[data-critical-threshold]')?.dataset.criticalThreshold || '90');

			bar.style.width = '0%';
			window.requestAnimationFrame(function () {
				setTimeout(function () {
					bar.style.width = width;
					bar.classList.remove('warning', 'critical');
					if (percentage >= critThreshold) {
						bar.classList.add('critical');
					} else if (percentage >= warnThreshold) {
						bar.classList.add('warning');
					}
				}, 80);
			});
		});
	}

	function setupAutoRefresh() {
		const section = document.querySelector('.stats-overview-section');
		if (!section) {
			return;
		}
		window.setInterval(refreshStats, REFRESH_INTERVAL_MS);
	}

	function refreshStats() {
		fetch(OC.generateUrl('/apps/projectcheck/api/dashboard/stats'), {
			method: 'GET',
			headers: {
				'Accept': 'application/json',
				'requesttoken': OC.requestToken,
			},
			credentials: 'same-origin',
		})
			.then(function (response) {
				if (!response.ok) {
					throw new Error('stats_request_failed');
				}
				return response.json();
			})
			.then(function (data) {
				if (data.error === 'stats_unavailable') {
					return;
				}
				updateOverviewStats(data);
			})
			.catch(function () {
				// Silent fail — server-rendered values remain valid.
			});
	}

	/**
	 * Updates overview tiles using data-dashboard-stat attributes from the template.
	 */
	function updateOverviewStats(stats) {
		document.querySelectorAll('[data-dashboard-stat]').forEach(function (tile) {
			const key = tile.getAttribute('data-dashboard-stat');
			const valueNode = tile.querySelector('[data-dashboard-value]');
			const detailNode = tile.querySelector('[data-dashboard-detail]');
			if (!valueNode) {
				return;
			}

			switch (key) {
				case 'projects':
					valueNode.textContent = formatNumber(stats.totalProjects);
					if (detailNode) {
						detailNode.textContent = formatNumber(stats.activeProjects) + ' ' + t('projectcheck', 'active');
					}
					break;
				case 'budget':
					valueNode.textContent = formatCurrency(stats.totalBudget);
					if (detailNode) {
						detailNode.textContent = formatPercent(stats.consumptionPercentage) + ' ' + t('projectcheck', 'used');
					}
					break;
				case 'hours':
					valueNode.textContent = formatHours(stats.totalHours);
					break;
				case 'customers':
					valueNode.textContent = formatNumber(stats.totalCustomers);
					break;
				default:
					break;
			}
		});
	}

	function formatNumber(value) {
		const n = Number(value) || 0;
		return window.ProjectCheckFormat
			? window.ProjectCheckFormat.number(Math.round(n))
			: String(Math.round(n));
	}

	function formatCurrency(value) {
		const n = Number(value) || 0;
		return window.ProjectCheckFormat
			? window.ProjectCheckFormat.currencyFmt(n)
			: n.toFixed(2);
	}

	function formatPercent(value) {
		const n = Number(value) || 0;
		return window.ProjectCheckFormat
			? window.ProjectCheckFormat.percent(n, 0)
			: String(Math.round(n)) + '%';
	}

	function formatHours(value) {
		const n = Number(value) || 0;
		return window.ProjectCheckFormat
			? window.ProjectCheckFormat.hours(n)
			: n + 'h';
	}

	/* Productivity modal — shared a11y primitive (AUDIT-FINDINGS C13/D17). */
	let productivityRestoreScroll = '';

	function showProductivityInfoPopup() {
		const popup = document.getElementById('productivity-info-popup');
		if (!popup) {
			return;
		}
		productivityRestoreScroll = document.body.style.overflow || '';
		popup.removeAttribute('hidden');
		popup.style.display = 'flex';
		document.body.style.overflow = 'hidden';

		if (window.ProjectCheckModalA11y) {
			window.ProjectCheckModalA11y.attach(popup, {
				dismissOnEscape: true,
				dismissOnBackdrop: false,
				restoreFocus: true,
				initialFocus: '.popup-close',
				onDismiss: function () {
					hideProductivityInfoPopup({ skipDetach: true });
				},
			});
		}
	}

	function hideProductivityInfoPopup(options) {
		const popup = document.getElementById('productivity-info-popup');
		if (!popup) {
			return;
		}
		const opts = options || {};
		if (!opts.skipDetach && window.ProjectCheckModalA11y) {
			window.ProjectCheckModalA11y.detach(popup, { reason: 'programmatic' });
		}
		popup.style.display = 'none';
		popup.setAttribute('hidden', '');
		document.body.style.overflow = productivityRestoreScroll;
		productivityRestoreScroll = '';
	}

	window.showProductivityInfoPopup = showProductivityInfoPopup;
	window.hideProductivityInfoPopup = hideProductivityInfoPopup;

	document.addEventListener('click', function (event) {
		const target = event.target;
		if (!(target instanceof Element)) {
			return;
		}
		if (target.matches('[data-action="show-productivity-info"], .info-popup-trigger')) {
			event.preventDefault();
			event.stopPropagation();
			showProductivityInfoPopup();
			return;
		}
		if (target.closest('[data-action="hide-productivity-info"], .popup-close')) {
			const popup = document.getElementById('productivity-info-popup');
			if (popup && popup.contains(target)) {
				event.preventDefault();
				event.stopPropagation();
				hideProductivityInfoPopup();
				return;
			}
		}
		const popup = document.getElementById('productivity-info-popup');
		if (popup && event.target === popup) {
			hideProductivityInfoPopup();
		}
	});

	const dashboardApi = {
		refreshStats: refreshStats,
		showProductivityInfoPopup: showProductivityInfoPopup,
		hideProductivityInfoPopup: hideProductivityInfoPopup,
	};
	window.ProjectCheckDashboard = dashboardApi;
	window.ProjectControlDashboard = dashboardApi;
})();
