<?php

/**
 * Time entries list template for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

use OCP\Util;

Util::addScript('projectcheck', 'time-entries');
Util::addStyle('projectcheck', 'time-entries');
Util::addStyle('projectcheck', 'navigation');
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<style nonce="<?php p($_['cspNonce'] ?? '') ?>">
    /* Filter Container - Horizontal Layout */
    .filters-container {
        background: var(--color-main-background);
        border: 1px solid var(--color-border);
        border-radius: 8px;
        padding: 16px;
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 16px;
    }

    /* Search Input */
    .search-input-wrapper {
        position: relative;
        flex: 1;
        min-width: 300px;
    }

    .search-input {
        width: 100%;
        padding: 8px 12px 8px 36px;
        border: 1px solid var(--color-border);
        border-radius: 4px;
        font-size: 14px;
        background: var(--color-main-background);
        color: var(--color-text);
        transition: all 0.2s ease;
    }

    .search-input:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 0 2px var(--color-primary-alpha-20);
    }

    .search-icon {
        position: absolute;
        left: 10px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 14px;
        color: var(--color-text-maxcontrast);
        pointer-events: none;
    }

    /* Filter Controls - Horizontal */
    .filter-controls {
        display: flex;
        flex-wrap: wrap;
        align-items: center;
        gap: 12px;
    }

    .filter-group {
        display: flex;
        align-items: center;
        gap: 6px;
    }

    .filter-label {
        font-size: 13px;
        font-weight: 500;
        color: var(--color-text);
        white-space: nowrap;
    }

    .filter-select,
    .filter-date {
        padding: 6px 8px;
        border: 1px solid var(--color-border);
        border-radius: 4px;
        font-size: 13px;
        background: var(--color-main-background);
        color: var(--color-text);
        transition: all 0.2s ease;
        min-width: 120px;
    }

    .filter-select:focus,
    .filter-date:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 0 2px var(--color-primary-alpha-20);
    }

    /* Action Buttons */
    .filter-actions {
        display: flex;
        gap: 8px;
        margin-left: auto;
    }

    .btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 12px;
        border: none;
        border-radius: 4px;
        font-size: 13px;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
    }

    .btn-primary {
        background: var(--color-primary);
        color: var(--color-primary-text);
    }

    .btn-primary:hover {
        background: var(--color-primary-hover);
    }

    .btn-secondary {
        background: var(--color-background-hover);
        color: var(--color-text);
        border: 1px solid var(--color-border);
    }

    .btn-secondary:hover {
        background: var(--color-background-dark);
    }

    .btn-icon {
        font-size: 14px;
    }

    /* Responsive Design */
    @media (max-width: 1024px) {
        .filters-container {
            flex-direction: column;
            align-items: stretch;
        }

        .search-input-wrapper {
            min-width: auto;
        }

        .filter-controls {
            justify-content: center;
        }

        .filter-actions {
            margin-left: 0;
            justify-content: center;
        }
    }

    @media (max-width: 768px) {
        .filter-controls {
            flex-direction: column;
            align-items: stretch;
            gap: 8px;
        }

        .filter-group {
            flex-direction: column;
            align-items: stretch;
            gap: 4px;
        }

        .filter-select,
        .filter-date {
            min-width: auto;
        }
    }

    /* Notice styles for info messages */
    .notice-info {
        background: var(--color-primary-element-light);
        border: 1px solid var(--color-primary-element);
        color: var(--color-primary-text);
    }
</style>

<div id="app-content" data-date-format="<?php p($_['dateFormat']); ?>">
    <div id="app-content-wrapper">
        <!-- Page Header -->
        <div class="section">
            <div class="header-content">
                <div class="header-text">
                    <h2><?php p($l->t('Time Entries')); ?></h2>
                    <p><?php p($l->t('Track and manage your time entries')); ?></p>
                </div>
                <div class="header-actions">
                    <a href="<?php p($_['createUrl'] ?? '/index.php/apps/projectcheck/time-entries/create'); ?>" class="button primary">
                        <?php p($l->t('Add Time Entry')); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_GET['message']) && $_GET['message'] === 'success'): ?>
            <div class="notice notice-success">
                <i class="icon icon-checkmark"></i>
                <span>
                    <?php if (isset($_GET['time_entry_id'])): ?>
                        <?php p($l->t('Time entry was created successfully!')); ?>
                    <?php elseif (isset($_GET['updated'])): ?>
                        <?php p($l->t('Time entry was updated successfully!')); ?>
                    <?php elseif (isset($_GET['deleted'])): ?>
                        <?php p($l->t('Time entry was deleted successfully!')); ?>
                    <?php else: ?>
                        <?php p($l->t('Operation completed successfully!')); ?>
                    <?php endif; ?>
                </span>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['message']) && $_GET['message'] === 'error' && isset($_GET['error_text'])): ?>
            <div class="notice notice-error">
                <i class="icon icon-error"></i>
                <span><?php p($l->t('Error: %s', [$_GET['error_text']])); ?></span>
            </div>
        <?php endif; ?>


        <!-- Search and Filter Section -->
        <div class="section">
            <div class="filters-container">
                <!-- Search Input -->
                <div class="search-input-wrapper">
                    <input type="text" id="time-entry-search" class="search-input"
                        placeholder="<?php p($l->t('Search descriptions, projects, or customers...')); ?>"
                        value="<?php p($filters['search'] ?? ''); ?>">
                    <span class="search-icon">🔍</span>
                </div>

                <!-- Filter Controls -->
                <div class="filter-controls">
                    <div class="filter-group">
                        <label for="project-filter" class="filter-label"><?php p($l->t('Project')); ?></label>
                        <select id="project-filter" class="filter-select">
                            <option value=""><?php p($l->t('All Projects')); ?></option>
                            <?php if (isset($projects) && is_array($projects)): ?>
                                <?php foreach ($projects as $project): ?>
                                    <option value="<?php p($project->getId()); ?>" <?php if (isset($filters['project_id']) && $filters['project_id'] == $project->getId()) echo 'selected'; ?>>
                                        <?php p($project->getName()); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="user-filter" class="filter-label"><?php p($l->t('User')); ?></label>
                        <select id="user-filter" class="filter-select">
                            <option value=""><?php p($l->t('All Users')); ?></option>
                            <?php if (isset($users) && is_array($users)): ?>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php p($user['user_id']); ?>" <?php if (isset($filters['user_id']) && $filters['user_id'] == $user['user_id']) echo 'selected'; ?>>
                                        <?php p($user['displayname']); ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="project-type-filter" class="filter-label"><?php p($l->t('Project Type')); ?></label>
                        <select id="project-type-filter" class="filter-select">
                            <option value=""><?php p($l->t('All Types')); ?></option>
                            <option value="client" <?php if (isset($filters['project_type']) && $filters['project_type'] == 'client') echo 'selected'; ?>>👥 <?php p($l->t('Client Project')); ?></option>
                            <option value="admin" <?php if (isset($filters['project_type']) && $filters['project_type'] == 'admin') echo 'selected'; ?>>⚙️ <?php p($l->t('Administrative')); ?></option>
                            <option value="sales" <?php if (isset($filters['project_type']) && $filters['project_type'] == 'sales') echo 'selected'; ?>>📈 <?php p($l->t('Sales & Marketing')); ?></option>
                            <option value="customer" <?php if (isset($filters['project_type']) && $filters['project_type'] == 'customer') echo 'selected'; ?>>🎧 <?php p($l->t('Customer Support')); ?></option>
                            <option value="product" <?php if (isset($filters['project_type']) && $filters['project_type'] == 'product') echo 'selected'; ?>>💻 <?php p($l->t('Product Development')); ?></option>
                            <option value="meeting" <?php if (isset($filters['project_type']) && $filters['project_type'] == 'meeting') echo 'selected'; ?>>🤝 <?php p($l->t('Meetings & Overhead')); ?></option>
                            <option value="internal" <?php if (isset($filters['project_type']) && $filters['project_type'] == 'internal') echo 'selected'; ?>>🏢 <?php p($l->t('Internal Project')); ?></option>
                            <option value="research" <?php if (isset($filters['project_type']) && $filters['project_type'] == 'research') echo 'selected'; ?>>🔬 <?php p($l->t('Research & Development')); ?></option>
                            <option value="training" <?php if (isset($filters['project_type']) && $filters['project_type'] == 'training') echo 'selected'; ?>>🎓 <?php p($l->t('Training & Education')); ?></option>
                            <option value="other" <?php if (isset($filters['project_type']) && $filters['project_type'] == 'other') echo 'selected'; ?>>📋 <?php p($l->t('Other')); ?></option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label for="date-from-filter" class="filter-label"><?php p($l->t('From')); ?></label>
                        <input type="date" id="date-from-filter" name="date_from" class="filter-date"
                            value="<?php
                                    if (!empty($filters['date_from'])) {
                                        $parsedDate = $_['dateFormatService']->parseDate($filters['date_from'], $_['userId']);
                                        echo $parsedDate ? $parsedDate->format('Y-m-d') : '';
                                    }
                                    ?>"
                            title="<?php p($l->t('Select start date')); ?>">
                    </div>

                    <div class="filter-group">
                        <label for="date-to-filter" class="filter-label"><?php p($l->t('To')); ?></label>
                        <input type="date" id="date-to-filter" name="date_to" class="filter-date"
                            value="<?php
                                    if (!empty($filters['date_to'])) {
                                        $parsedDate = $_['dateFormatService']->parseDate($filters['date_to'], $_['userId']);
                                        echo $parsedDate ? $parsedDate->format('Y-m-d') : '';
                                    }
                                    ?>"
                            title="<?php p($l->t('Select end date')); ?>">
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="filter-actions">
                    <button id="clear-filters" class="btn btn-secondary">
                        <span class="btn-icon">🗑️</span>
                        <?php p($l->t('Clear')); ?>
                    </button>
                    <button id="export-csv" class="btn btn-primary">
                        <span class="btn-icon">📊</span>
                        <?php p($l->t('Export')); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Time Entries Table -->
        <div class="section">
            <?php if (empty($timeEntries)): ?>
                <div class="emptycontent">
                    <div class="icon-time"></div>
                    <h2><?php p($l->t('No time entries found')); ?></h2>
                    <p><?php p($l->t('Add your first time entry to get started!')); ?></p>
                </div>
            <?php else: ?>
                <div class="grid-container">
                    <table class="grid">
                        <thead>
                            <tr>
                                <th><?php p($l->t('Date')); ?></th>
                                <th><?php p($l->t('Project')); ?></th>
                                <th><?php p($l->t('Type')); ?></th>
                                <th><?php p($l->t('Customer')); ?></th>
                                <th><?php p($l->t('User')); ?></th>
                                <th><?php p($l->t('Hours')); ?></th>
                                <th><?php p($l->t('Description')); ?></th>
                                <th><?php p($l->t('Actions')); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($timeEntries as $entry): ?>
                                <?php
                                $timeEntry = $entry['timeEntry'];
                                if (!$timeEntry || !is_object($timeEntry)) {
                                    error_log('TimeEntry template error: timeEntry is not an object. Entry: ' . print_r($entry, true));
                                    continue;
                                }
                                ?>
                                <tr data-entry-id="<?php p($timeEntry->getId()); ?>">
                                    <td><?php p($timeEntry->getDate() ? $_['dateFormatService']->formatDate($timeEntry->getDate(), $_['userId']) : ''); ?></td>
                                    <td>
                                        <a href="<?php p(str_replace('PROJECT_ID', $timeEntry->getProjectId(), $_['projectShowUrl'] ?? '/index.php/apps/projectcheck/projects/')); ?>">
                                            <?php p($entry['projectName'] ?? $l->t('Unknown Project')); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <?php
                                        $projectType = $entry['project_type'] ?? 'client';
                                        $displayName = $entry['project_type_display_name'] ?? 'Client Project';

                                        // Icon mapping for project types
                                        $iconMapping = [
                                            'client' => '👥',
                                            'admin' => '⚙️',
                                            'sales' => '📈',
                                            'customer' => '🎧',
                                            'product' => '💻',
                                            'meeting' => '🤝',
                                            'internal' => '🏢',
                                            'research' => '🔬',
                                            'training' => '🎓',
                                            'other' => '📋'
                                        ];

                                        $icon = $iconMapping[$projectType] ?? '📋';
                                        ?>
                                        <span class="project-type-icon"
                                            data-project-type="<?php p($projectType); ?>"
                                            title="<?php p($displayName); ?>">
                                            <?php p($icon); ?>
                                        </span>
                                    </td>
                                    <td><?php p($entry['customerName'] ?? ''); ?></td>
                                    <td><?php p($entry['userDisplayName'] ?? $timeEntry->getUserId() ?? ''); ?></td>
                                    <td><?php p($timeEntry->getHours() ?? 0); ?></td>
                                    <td class="description-cell"><?php p($timeEntry->getDescription() ?? ''); ?></td>
                                    <td>
                                        <div class="action-items">
                                            <a href="<?php p(str_replace('ENTRY_ID', $timeEntry->getId(), $_['showUrl'] ?? '/index.php/apps/projectcheck/time-entries/')); ?>"
                                                class="action-item" title="<?php p($l->t('View Details')); ?>">
                                                <span class="icon icon-details"></span>
                                            </a>
                                            <?php if ($timeEntry->getUserId() === $_['userId']): ?>
                                                <a href="<?php p(str_replace('ENTRY_ID', $timeEntry->getId(), $_['editUrl'] ?? '/index.php/apps/projectcheck/time-entries/edit/')); ?>"
                                                    class="action-item" title="<?php p($l->t('Edit Time Entry')); ?>">
                                                    <span class="icon icon-rename"></span>
                                                </a>
                                                <button type="button" class="action-item delete-entry-btn"
                                                    data-entry-id="<?php p($timeEntry->getId()); ?>"
                                                    data-entry-description="<?php p($timeEntry->getDescription() ?? ''); ?>"
                                                    title="<?php p($l->t('Delete Time Entry')); ?>">
                                                    <span class="icon icon-delete"></span>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script nonce="<?php p($_['cspNonce']) ?>">
    // Local SVG icon library
    const svgIcons = {
        'pie-chart': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>',
        clock: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>',
        euro: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.25.5-2.5 1.5-3.5Z"/></svg>'
    };

    // Initialize icons
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-lucide]').forEach(function(el) {
            const iconName = el.getAttribute('data-lucide');
            if (svgIcons[iconName]) {
                el.innerHTML = svgIcons[iconName];
            }
        });
    });
</script>        <!-- Project Type Statistics -->
        <?php if (!empty($_['projectTypeStats'])): ?>
            <div class="section project-type-stats-section compact">
                <div class="section-header">
                    <h3><i data-lucide="pie-chart" class="lucide-icon"></i> <?php p($l->t('Project Type Analysis')); ?></h3>
                </div>
                <div class="section-content">
                    <div class="project-type-stats-compact">
                        <?php foreach ($_['projectTypeStats'] as $year => $yearData): ?>
                            <div class="year-section-compact">
                                <div class="year-header-compact">
                                    <h4><?php p($year); ?></h4>
                                    <?php
                                    $yearTotalHours = array_sum(array_column($yearData, 'total_hours'));
                                    $yearTotalCost = array_sum(array_column($yearData, 'total_cost'));
                                    ?>
                                    <div class="year-summary-compact">
                                        <span class="summary-item-compact">
                                            <i data-lucide="clock" class="lucide-icon"></i>
                                            <?php p(number_format($yearTotalHours, 1)); ?>h
                                        </span>
                                        <span class="summary-item-compact">
                                            <i data-lucide="euro" class="lucide-icon"></i>
                                            €<?php p(number_format($yearTotalCost, 2)); ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="project-types-table">
                                    <table class="compact-table">
                                        <thead>
                                            <tr>
                                                <th><?php p($l->t('Type')); ?></th>
                                                <th><?php p($l->t('Hours')); ?></th>
                                                <th><?php p($l->t('Cost')); ?></th>
                                                <th><?php p($l->t('Entries')); ?></th>
                                                <th><?php p($l->t('Hours Share')); ?></th>
                                                <th><?php p($l->t('Cost Share')); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($yearData as $projectType => $typeData): ?>
                                                <tr>
                                                    <td class="type-cell">
                                                        <?php
                                                        $displayNames = [
                                                            'client' => $l->t('Client Project'),
                                                            'admin' => $l->t('Administrative'),
                                                            'sales' => $l->t('Sales & Marketing'),
                                                            'customer' => $l->t('Customer Support'),
                                                            'product' => $l->t('Product Development'),
                                                            'meeting' => $l->t('Meetings & Overhead'),
                                                            'internal' => $l->t('Internal Project'),
                                                            'research' => $l->t('Research & Development'),
                                                            'training' => $l->t('Training & Education'),
                                                            'other' => $l->t('Other')
                                                        ];

                                                        // Icon mapping for project types (same as in main table)
                                                        $iconMapping = [
                                                            'client' => '👥',
                                                            'admin' => '⚙️',
                                                            'sales' => '📈',
                                                            'customer' => '🎧',
                                                            'product' => '💻',
                                                            'meeting' => '🤝',
                                                            'internal' => '🏢',
                                                            'research' => '🔬',
                                                            'training' => '🎓',
                                                            'other' => '📋'
                                                        ];

                                                        $displayName = $displayNames[$projectType] ?? ucfirst($projectType);
                                                        $icon = $iconMapping[$projectType] ?? '📋';
                                                        ?>
                                                        <div class="project-type-display">
                                                            <span class="project-type-icon"
                                                                data-project-type="<?php p($projectType); ?>"
                                                                title="<?php p($displayName); ?>">
                                                                <?php p($icon); ?>
                                                            </span>
                                                            <span class="project-type-text"><?php p($displayName); ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="stat-cell"><?php p(number_format($typeData['total_hours'], 1)); ?>h</td>
                                                    <td class="stat-cell">€<?php p(number_format($typeData['total_cost'], 2)); ?></td>
                                                    <td class="stat-cell"><?php p($typeData['entry_count']); ?></td>
                                                    <td class="percentage-cell">
                                                        <div class="percentage-bar">
                                                            <div class="percentage-fill" style="width: <?php p($yearTotalHours > 0 ? ($typeData['total_hours'] / $yearTotalHours) * 100 : 0); ?>%"></div>
                                                            <span class="percentage-text"><?php p($yearTotalHours > 0 ? round(($typeData['total_hours'] / $yearTotalHours) * 100, 1) : 0); ?>%</span>
                                                        </div>
                                                    </td>
                                                    <td class="percentage-cell">
                                                        <div class="percentage-bar">
                                                            <div class="percentage-fill" style="width: <?php p($yearTotalCost > 0 ? ($typeData['total_cost'] / $yearTotalCost) * 100 : 0); ?>%"></div>
                                                            <span class="percentage-text"><?php p($yearTotalCost > 0 ? round(($typeData['total_cost'] / $yearTotalCost) * 100, 1) : 0); ?>%</span>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

