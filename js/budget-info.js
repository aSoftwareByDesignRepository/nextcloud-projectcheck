/**
 * Budget information display for time entry forms
 * 
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function () {
    'use strict';
    let activeRequestController = null;
    let latestBudgetRequestId = 0;

    function getBudgetText(key, fallback) {
        const source = document.querySelector(`#pc-budget-l10n [data-budget-tpl="${key}"]`);
        if (source && source.textContent) {
            return source.textContent.trim();
        }
        return fallback;
    }

    /**
     * Initialize budget info functionality
     */
    function initializeBudgetInfo() {
        const projectSelect = document.getElementById('project_id');

        if (projectSelect) {
            projectSelect.addEventListener('change', handleProjectChange);

            // Load budget info for pre-selected project (in edit mode)
            if (projectSelect.value) {
                loadProjectBudgetInfo(projectSelect.value);
            }
        }
    }

    /**
     * Handle project selection change
     */
    function handleProjectChange(event) {
        const projectId = event.target.value;

        if (projectId) {
            loadProjectBudgetInfo(projectId);
        } else {
            hideBudgetInfo();
        }
    }

    /**
     * Load and display project budget information
     */
    function loadProjectBudgetInfo(projectId) {
        if (!projectId) {
            hideBudgetInfo();
            return;
        }
        if (activeRequestController) {
            activeRequestController.abort();
        }
        activeRequestController = new AbortController();
        const requestId = ++latestBudgetRequestId;
        showBudgetInfoLoading();

        fetch(OC.generateUrl(`/apps/projectcheck/api/project/${projectId}/budget`), {
            method: 'GET',
            headers: {
                'requesttoken': OC.requestToken
            },
            signal: activeRequestController.signal
        })
            .then(response => response.json())
            .then(result => {
                if (requestId !== latestBudgetRequestId) {
                    return;
                }
                if (result.success && result.budget) {
                    displayBudgetInfo(result.budget);
                } else {
                    hideBudgetInfo();
                    console.error('Failed to load budget info:', result.error);
                }
            })
            .catch(error => {
                if (error && error.name === 'AbortError') {
                    return;
                }
                console.error('Error loading budget info:', error);
                hideBudgetInfo();
            });
    }

    /**
     * Display budget information
     */
    function displayBudgetInfo(budget) {
        const budgetSection = document.getElementById('budget-info-section');
        const totalBudgetEl = document.getElementById('total-budget');
        const usedBudgetEl = document.getElementById('used-budget');
        const remainingBudgetEl = document.getElementById('remaining-budget');
        const totalHoursEl = document.getElementById('total-hours');
        const percentageEl = document.getElementById('budget-percentage');
        const progressStatusEl = document.getElementById('budget-progress-status');
        const remainingHoursDisplay = document.getElementById('remaining-hours-display');
        const progressFill = document.getElementById('budget-progress-fill');
        const progressBar = document.querySelector('.budget-progress-bar');

        if (!budgetSection || !totalBudgetEl || !usedBudgetEl || !remainingBudgetEl || !totalHoursEl || !percentageEl || !progressStatusEl || !progressFill || !progressBar) return;
        const totalBudget = parseNumber(budget.total_budget);
        if (!isFinite(totalBudget) || totalBudget <= 0) {
            hideBudgetInfo();
            return;
        }
        const usedBudget = Math.max(parseNumber(budget.used_budget), 0);
        const remainingBudget = totalBudget - usedBudget;
        const usedHours = Math.max(parseNumber(budget.used_hours), 0);
        const remainingHours = parseOptionalNumber(budget.remaining_hours);
        const rawPercentage = (usedBudget / totalBudget) * 100;
        const visiblePercentage = clamp(rawPercentage, 0, 100);
        const roundedPercentage = Math.round(Math.max(rawPercentage, 0));
        const warningLevel = resolveWarningLevel(budget.warning_level, rawPercentage, remainingBudget);

        // Update budget values
        totalBudgetEl.textContent = formatCurrency(totalBudget);
        usedBudgetEl.textContent = formatCurrency(usedBudget);
        remainingBudgetEl.textContent = formatCurrency(remainingBudget);
        totalHoursEl.textContent = formatHours(usedHours);
        percentageEl.textContent = `${roundedPercentage}%`;
        progressStatusEl.textContent = rawPercentage > 100
            ? getBudgetText('over-budget', 'Over budget')
            : getBudgetText('used', 'used');

        // Update remaining hours display
        if (remainingHoursDisplay && remainingHours !== null) {
            remainingHoursDisplay.textContent = `${formatHours(Math.max(remainingHours, 0))} ${getBudgetText('remaining-hours', 'remaining')}`;
            remainingHoursDisplay.style.display = 'block';
        } else if (remainingHoursDisplay) {
            remainingHoursDisplay.style.display = 'none';
        }

        // Update progress bar
        progressFill.style.width = `${visiblePercentage}%`;
        progressBar.setAttribute('aria-valuenow', String(Math.round(visiblePercentage)));

        // Apply warning level styling
        progressFill.className = `budget-progress-fill budget-progress-fill--${warningLevel}`;

        // Update remaining budget color based on amount
        remainingBudgetEl.className = 'budget-stat-value';
        if (remainingBudget < 0) {
            remainingBudgetEl.classList.add('budget-stat-value--critical');
        } else if (warningLevel === 'warning') {
            remainingBudgetEl.classList.add('budget-stat-value--warning');
        } else if (warningLevel === 'critical') {
            remainingBudgetEl.classList.add('budget-stat-value--critical');
        } else {
            remainingBudgetEl.classList.add('budget-stat-value--safe');
        }

        // Show the budget section
        budgetSection.style.display = 'block';
    }

    /**
     * Show loading state for budget info
     */
    function showBudgetInfoLoading() {
        const budgetSection = document.getElementById('budget-info-section');

        if (!budgetSection) return;

        // Show section with loading state
        budgetSection.style.display = 'block';
        const loadingLabel = getBudgetText('loading', 'Loading...');

        // Show loading in values
        document.getElementById('total-budget').textContent = loadingLabel;
        document.getElementById('used-budget').textContent = loadingLabel;
        document.getElementById('remaining-budget').textContent = loadingLabel;
        document.getElementById('total-hours').textContent = loadingLabel;
        document.getElementById('budget-percentage').textContent = '...';
        const progressStatus = document.getElementById('budget-progress-status');
        if (progressStatus) {
            progressStatus.textContent = getBudgetText('used', 'used');
        }
        const remainingHoursDisplay = document.getElementById('remaining-hours-display');
        if (remainingHoursDisplay) {
            remainingHoursDisplay.style.display = 'none';
        }

        // Reset progress bar
        const progressFill = document.getElementById('budget-progress-fill');
        progressFill.style.width = '0%';
        progressFill.className = 'budget-progress-fill budget-progress-fill--safe';
        const progressBar = document.querySelector('.budget-progress-bar');
        if (progressBar) {
            progressBar.setAttribute('aria-valuenow', '0');
        }
    }

    /**
     * Hide budget information section
     */
    function hideBudgetInfo() {
        const budgetSection = document.getElementById('budget-info-section');
        const remainingHoursDisplay = document.getElementById('remaining-hours-display');

        if (budgetSection) {
            budgetSection.style.display = 'none';
        }

        if (remainingHoursDisplay) {
            remainingHoursDisplay.style.display = 'none';
        }
    }

    /**
     * Format currency value
     */
    function formatCurrency(amount) {
        const value = parseNumber(amount);
        if (!isFinite(value)) return '\u2014';
        if (window.ProjectCheckFormat) {
            return window.ProjectCheckFormat.currencyFmt(value);
        }
        const code = (window.ProjectCheckConfig && typeof window.ProjectCheckConfig.currency === 'string'
            && /^[A-Z]{3}$/i.test(window.ProjectCheckConfig.currency))
            ? window.ProjectCheckConfig.currency.toUpperCase()
            : 'EUR';
        const abs = Math.abs(value).toFixed(2);
        return value < 0 ? `-${code} ${abs}` : `${code} ${abs}`;
    }

    /**
     * Format hours value
     */
    function formatHours(hours) {
        const value = parseNumber(hours);
        if (!isFinite(value)) return '0.00h';
        return `${value.toFixed(2)}h`;
    }

    function parseNumber(value) {
        if (value === null || value === undefined || value === '') {
            return 0;
        }
        const parsed = Number.parseFloat(value);
        return Number.isFinite(parsed) ? parsed : 0;
    }

    function parseOptionalNumber(value) {
        if (value === null || value === undefined || value === '') {
            return null;
        }
        const parsed = Number.parseFloat(value);
        return Number.isFinite(parsed) ? parsed : null;
    }

    function clamp(value, min, max) {
        if (!Number.isFinite(value)) return min;
        return Math.min(Math.max(value, min), max);
    }

    function resolveWarningLevel(serverLevel, consumptionPercentage, remainingBudget) {
        if (remainingBudget < 0 || consumptionPercentage > 100 || serverLevel === 'critical') {
            return 'critical';
        }
        if (serverLevel === 'warning' || consumptionPercentage >= 80) {
            return 'warning';
        }
        return 'safe';
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeBudgetInfo);
    } else {
        initializeBudgetInfo();
    }

})();
