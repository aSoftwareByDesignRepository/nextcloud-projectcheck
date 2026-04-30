/**
 * Budget information display for time entry forms
 * 
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function () {
    'use strict';

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
        showBudgetInfoLoading();

        fetch(OC.generateUrl(`/apps/projectcheck/api/project/${projectId}/budget`), {
            method: 'GET',
            headers: {
                'requesttoken': OC.requestToken
            }
        })
            .then(response => response.json())
            .then(result => {
                if (result.success && result.budget) {
                    displayBudgetInfo(result.budget);
                } else {
                    hideBudgetInfo();
                    console.error('Failed to load budget info:', result.error);
                }
            })
            .catch(error => {
                console.error('Error loading budget info:', error);
                hideBudgetInfo();
            });
    }

    /**
     * Display budget information
     */
    function displayBudgetInfo(budget) {
        const budgetSection = document.getElementById('budget-info-section');

        if (!budgetSection) return;
        const totalBudget = parseFloat(budget.total_budget);
        if (!isFinite(totalBudget) || totalBudget <= 0) {
            hideBudgetInfo();
            return;
        }

        // Update budget values
        document.getElementById('total-budget').textContent = formatCurrency(budget.total_budget);
        document.getElementById('used-budget').textContent = formatCurrency(budget.used_budget);
        document.getElementById('remaining-budget').textContent = formatCurrency(budget.remaining_budget);
        document.getElementById('total-hours').textContent = formatHours(budget.used_hours);
        document.getElementById('budget-percentage').textContent = `${Math.round(budget.consumption_percentage)}%`;

        // Update remaining hours display
        const remainingHoursDisplay = document.getElementById('remaining-hours-display');
        if (remainingHoursDisplay && budget.remaining_hours !== undefined) {
            remainingHoursDisplay.textContent = `(${formatHours(budget.remaining_hours)} remaining)`;
            remainingHoursDisplay.style.display = 'block';
        }

        // Update progress bar
        const progressFill = document.getElementById('budget-progress-fill');
        const percentage = Math.min(budget.consumption_percentage, 100);

        progressFill.style.width = `${percentage}%`;

        // Apply warning level styling
        progressFill.className = 'budget-progress-fill';
        if (budget.warning_level === 'warning') {
            progressFill.classList.add('warning');
        } else if (budget.warning_level === 'critical' || budget.consumption_percentage > 100) {
            progressFill.classList.add('critical');
        }

        // Update remaining budget color based on amount
        const remainingElement = document.getElementById('remaining-budget');
        remainingElement.className = 'budget-stat-value';
        if (budget.remaining_budget < 0) {
            remainingElement.classList.add('critical');
        } else if (budget.warning_level === 'warning') {
            remainingElement.classList.add('warning');
        } else if (budget.warning_level === 'critical') {
            remainingElement.classList.add('critical');
        } else {
            remainingElement.classList.add('safe');
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

        // Show loading in values
        document.getElementById('total-budget').textContent = 'Loading...';
        document.getElementById('used-budget').textContent = 'Loading...';
        document.getElementById('remaining-budget').textContent = 'Loading...';
        document.getElementById('total-hours').textContent = 'Loading...';
        document.getElementById('budget-percentage').textContent = '...';

        // Reset progress bar
        const progressFill = document.getElementById('budget-progress-fill');
        progressFill.style.width = '0%';
        progressFill.className = 'budget-progress-fill';
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
        if (amount === null || amount === undefined) return '€0.00';
        return `€${parseFloat(amount).toFixed(2)}`;
    }

    /**
     * Format hours value
     */
    function formatHours(hours) {
        if (hours === null || hours === undefined) return '0.00h';
        return `${parseFloat(hours).toFixed(2)}h`;
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeBudgetInfo);
    } else {
        initializeBudgetInfo();
    }

})();
