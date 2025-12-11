/**
 * Budget warnings JavaScript for time entry forms
 * 
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

(function () {
    'use strict';

    /**
     * Initialize budget warning functionality
     */
    function initializeBudgetWarnings() {
        const projectSelect = document.getElementById('project_id');
        const hoursInput = document.getElementById('hours');
        const rateInput = document.getElementById('hourly_rate');

        if (projectSelect && hoursInput && rateInput) {
            // Add event listeners for real-time budget checking
            [projectSelect, hoursInput, rateInput].forEach(element => {
                element.addEventListener('change', checkBudgetImpact);
                element.addEventListener('input', debounce(checkBudgetImpact, 500));
            });
        }
    }

    /**
     * Check budget impact when form values change
     */
    function checkBudgetImpact() {
        const projectId = document.getElementById('project_id').value;
        const hours = parseFloat(document.getElementById('hours').value) || 0;
        const rate = parseFloat(document.getElementById('hourly_rate').value) || 0;

        if (!projectId || hours <= 0 || rate <= 0) {
            hideBudgetWarning();
            return;
        }

        // Make API call to check budget impact
        fetch(OC.generateUrl('/apps/projectcheck/api/budget/impact'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'requesttoken': OC.requestToken
            },
            body: JSON.stringify({
                project_id: parseInt(projectId),
                additional_hours: hours,
                additional_rate: rate
            })
        })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    displayBudgetImpact(result.impact);
                } else {
                    hideBudgetWarning();
                }
            })
            .catch(error => {
                console.error('Error checking budget impact:', error);
                hideBudgetWarning();
            });
    }

    /**
     * Display budget impact information
     */
    function displayBudgetImpact(impact) {
        let warningContainer = document.getElementById('budget-warning-container');

        if (!warningContainer) {
            warningContainer = document.createElement('div');
            warningContainer.id = 'budget-warning-container';

            // Insert before the hours, hourly rate, and total cost form row
            const hoursField = document.getElementById('hours');
            if (hoursField) {
                const formRow = hoursField.closest('.form-row');
                if (formRow) {
                    formRow.parentNode.insertBefore(warningContainer, formRow);
                }
            }
        }

        const warningLevel = impact.warning_level_after || 'none';
        const wouldExceed = impact.would_exceed_budget;

        let message = '';
        let alertClass = 'budget-impact-info';

        if (wouldExceed) {
            message = t('projectcheck', '⚠️ This entry would exceed the project budget by €{amount}', {
                amount: (impact.additional_cost - impact.remaining_budget_after).toFixed(2),
            });
            alertClass += ' critical';
        } else if (warningLevel === 'critical') {
            message = t('projectcheck', '⚠️ This entry would bring the project to {percent}% of budget (€{cost} cost)', {
                percent: impact.new_consumption.toFixed(1),
                cost: impact.additional_cost.toFixed(2),
            });
            alertClass += ' critical';
        } else if (warningLevel === 'warning') {
            message = t('projectcheck', '⚡ This entry would bring the project to {percent}% of budget (€{cost} cost)', {
                percent: impact.new_consumption.toFixed(1),
                cost: impact.additional_cost.toFixed(2),
            });
            alertClass += ' warning';
        } else {
            // Show positive feedback for entries within budget
            message = t('projectcheck', '✅ Entry cost: €{cost} - Budget remaining: €{remaining}', {
                cost: impact.additional_cost.toFixed(2),
                remaining: impact.remaining_budget_after.toFixed(2),
            });
        }

        warningContainer.innerHTML = `
			<div class="${alertClass}">
				${message}
			</div>
		`;

        warningContainer.style.display = 'block';
    }

    /**
     * Hide budget warning
     */
    function hideBudgetWarning() {
        const warningContainer = document.getElementById('budget-warning-container');
        if (warningContainer) {
            warningContainer.style.display = 'none';
        }
    }

    /**
     * Debounce function to limit API calls
     */
    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    /**
     * Show notification
     */
    function showNotification(message, type = 'info') {
        if (window.OC && window.OC.Notification) {
            if (type === 'error') {
                OC.Notification.showTemporary(message);
            } else {
                OC.Notification.showTemporary(message);
            }
        } else {
            // Fallback notification
            console.log(type.toUpperCase() + ': ' + message);
        }
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initializeBudgetWarnings);
    } else {
        initializeBudgetWarnings();
    }

})();
