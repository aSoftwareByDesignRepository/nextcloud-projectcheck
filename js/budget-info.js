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
    let activePreviewController = null;
    let latestPreviewRequestId = 0;
    let previewDebounceTimer = null;

    function getEntryDateValue() {
        const dateInput = document.getElementById('date');
        return dateInput && dateInput.value ? dateInput.value : '';
    }

    function debouncePreview(fn, delayMs) {
        return function debounced() {
            if (previewDebounceTimer) {
                clearTimeout(previewDebounceTimer);
            }
            previewDebounceTimer = setTimeout(fn, delayMs);
        };
    }

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
    let lastLoadedBudget = null;

    function initializeBudgetInfo() {
        const projectSelect = document.getElementById('project_id');
        const hoursInput = document.getElementById('hours');
        const rateInput = document.getElementById('hourly_rate');

        if (projectSelect) {
            projectSelect.addEventListener('change', handleProjectChange);

            // Load budget info for pre-selected project (in edit mode)
            if (projectSelect.value) {
                loadProjectBudgetInfo(projectSelect.value);
            }
        }

        if (hoursInput) {
            hoursInput.addEventListener('input', debouncePreview(applyEntryBudgetPreview, 400));
        }
        const dateInput = document.getElementById('date');
        if (dateInput) {
            dateInput.addEventListener('change', debouncePreview(applyEntryBudgetPreview, 400));
        }
        if (rateInput) {
            rateInput.addEventListener('input', debouncePreview(applyEntryBudgetPreview, 400));
            rateInput.addEventListener('change', debouncePreview(applyEntryBudgetPreview, 400));
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
                    lastLoadedBudget = result.budget;
                    displayBudgetInfo(result.budget);
                    applyEntryBudgetPreview();
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
        const warningLevel = warningCssLevel(
            budget.warning_level,
            rawPercentage,
            remainingBudget,
            {
                warning: parseNumber(budget.warning_threshold) || 80,
                critical: parseNumber(budget.critical_threshold) || 90,
            }
        );

        // Update budget values
        totalBudgetEl.textContent = formatCurrency(totalBudget);
        usedBudgetEl.textContent = formatCurrency(usedBudget);
        remainingBudgetEl.textContent = formatCurrency(remainingBudget);
        totalHoursEl.textContent = formatHours(usedHours);
        percentageEl.textContent = `${roundedPercentage}%`;
        progressStatusEl.textContent = rawPercentage > 100
            ? getBudgetText('over-budget', 'Over budget')
            : getBudgetText('used', 'used');

        // Remaining hours only when capacity is estimated (planning or project rate)
        const hoursEstimated = budget.hours_estimated === true || budget.hours_estimated === 1;
        if (remainingHoursDisplay) {
            if (hoursEstimated && remainingHours !== null && remainingHours >= 0) {
                remainingHoursDisplay.textContent = `${formatHours(Math.max(remainingHours, 0))} ${getBudgetText('remaining-hours-estimate', 'remaining (estimate)')}`;
                remainingHoursDisplay.style.display = 'block';
            } else {
                remainingHoursDisplay.textContent = getBudgetText('no-hour-estimate', 'Hour estimate unavailable');
                remainingHoursDisplay.style.display = hoursEstimated ? 'none' : 'block';
                remainingHoursDisplay.classList.toggle('budget-stat-note--muted', !hoursEstimated);
            }
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
     * Preview budget impact via server (Money-safe rate resolution).
     */
    function applyEntryBudgetPreview() {
        if (!lastLoadedBudget) {
            return;
        }
        const projectSelect = document.getElementById('project_id');
        const hoursInput = document.getElementById('hours');
        if (!projectSelect || !hoursInput) {
            return;
        }

        const projectId = projectSelect.value;
        const hours = Math.max(parseNumber(hoursInput.value), 0);
        if (!projectId || hours <= 0) {
            displayBudgetInfo(lastLoadedBudget);
            return;
        }

        if (activePreviewController) {
            activePreviewController.abort();
        }
        activePreviewController = new AbortController();
        const requestId = ++latestPreviewRequestId;

        fetch(OC.generateUrl('/apps/projectcheck/api/budget/impact'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                requesttoken: OC.requestToken,
            },
            signal: activePreviewController.signal,
            body: JSON.stringify({
                project_id: parseInt(projectId, 10),
                additional_hours: hours,
                entry_date: getEntryDateValue(),
            }),
        })
            .then((response) => response.json())
            .then((result) => {
                if (requestId !== latestPreviewRequestId) {
                    return;
                }
                if (!result.success || !result.impact || result.impact.has_budget === false) {
                    displayBudgetInfo(lastLoadedBudget);
                    return;
                }
                displayBudgetPreview(lastLoadedBudget, result.impact);
            })
            .catch((error) => {
                if (error && error.name === 'AbortError') {
                    return;
                }
                displayBudgetInfo(lastLoadedBudget);
            });
    }

    function displayBudgetPreview(baseBudget, impact) {
        const usedBudgetEl = document.getElementById('used-budget');
        const remainingBudgetEl = document.getElementById('remaining-budget');
        const percentageEl = document.getElementById('budget-percentage');
        const progressFill = document.getElementById('budget-progress-fill');
        const progressBar = document.querySelector('.budget-progress-bar');
        const progressStatusEl = document.getElementById('budget-progress-status');
        if (!usedBudgetEl || !remainingBudgetEl) {
            return;
        }

        const totalBudget = parseNumber(baseBudget.total_budget);
        const previewUsed = parseNumber(baseBudget.used_budget) + parseNumber(impact.additional_cost);
        const previewRemaining = parseNumber(impact.remaining_budget_after);
        const rawPercentage = parseNumber(impact.new_consumption);
        const visiblePercentage = clamp(rawPercentage, 0, 100);
        const roundedPercentage = Math.round(Math.max(rawPercentage, 0));
        const warningLevel = warningCssLevel(
            impact.warning_level_after,
            rawPercentage,
            previewRemaining,
            {
                warning: parseNumber(baseBudget.warning_threshold) || 80,
                critical: parseNumber(baseBudget.critical_threshold) || 90,
            }
        );

        usedBudgetEl.textContent = formatCurrency(previewUsed);
        remainingBudgetEl.textContent = formatCurrency(previewRemaining);
        if (percentageEl) {
            percentageEl.textContent = `${roundedPercentage}%`;
        }
        if (progressStatusEl) {
            progressStatusEl.textContent = rawPercentage > 100
                ? getBudgetText('over-budget', 'Over budget')
                : getBudgetText('with-entry', 'incl. this entry');
        }
        if (progressFill) {
            progressFill.style.width = `${visiblePercentage}%`;
            progressFill.className = `budget-progress-fill budget-progress-fill--${warningLevel}`;
        }
        if (progressBar) {
            progressBar.setAttribute('aria-valuenow', String(Math.round(visiblePercentage)));
        }
        remainingBudgetEl.className = 'budget-stat-value';
        if (previewRemaining < 0) {
            remainingBudgetEl.classList.add('budget-stat-value--critical');
        } else if (warningLevel === 'warning') {
            remainingBudgetEl.classList.add('budget-stat-value--warning');
        } else if (warningLevel === 'critical') {
            remainingBudgetEl.classList.add('budget-stat-value--critical');
        } else {
            remainingBudgetEl.classList.add('budget-stat-value--safe');
        }
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

    function warningCssLevel(serverLevel, consumptionPercentage, remainingBudget, thresholds) {
        if (remainingBudget < 0 || consumptionPercentage > 100) {
            return 'critical';
        }
        const level = serverLevel || 'none';
        if (level === 'critical') {
            return 'critical';
        }
        if (level === 'warning') {
            return 'warning';
        }
        const critical = thresholds && Number.isFinite(thresholds.critical) ? thresholds.critical : 90;
        const warning = thresholds && Number.isFinite(thresholds.warning) ? thresholds.warning : 80;
        if (consumptionPercentage >= critical) {
            return 'critical';
        }
        if (consumptionPercentage >= warning) {
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
