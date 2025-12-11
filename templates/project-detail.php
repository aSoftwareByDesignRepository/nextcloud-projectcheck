<?php

/**
 * Project detail view template
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

use OCP\Util;

Util::addScript('projectcheck', 'projects');
Util::addStyle('projectcheck', 'projects');
Util::addStyle('projectcheck', 'budget-alerts');
Util::addStyle('projectcheck', 'custom-icons');
Util::addStyle('projectcheck', 'navigation');

if (!isset($project) || !($project instanceof \OCA\ProjectCheck\Db\Project)) {
    throw new Exception('Project not found');
}

$projectId = $project->getId();
$statusClass = 'status-' . strtolower(str_replace(' ', '-', $project->getStatus()));
$priorityClass = 'priority-' . strtolower($project->getPriority());
$budgetConsumption = isset($budgetConsumption) ? $budgetConsumption : 0;
$warningLevel = isset($warningLevel) ? $warningLevel : 'none';
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<div id="app-content">
    <div id="app-content-wrapper">
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb-container">
            <nav class="breadcrumb" aria-label="Breadcrumb">
                <ol>
                    <li><a href="<?php p($urlGenerator->linkToRoute('projectcheck.project.index')); ?>"><?php p($l->t('Projects')); ?></a></li>
                    <li aria-current="page"><?php p($project->getName()); ?></li>
                </ol>
            </nav>
        </div>

        <!-- Page Header -->
        <div class="section page-header-section">
            <div class="header-content">
                <div class="header-text">

                    <div class="header-details">
                        <h2><?php p($project->getName()); ?></h2>
                        <p><?php p($l->t('Project details and associated information')); ?></p>
                        <div class="project-meta">
                            <div class="meta-item">
                                <i class="icon-user-custom"></i>
                                <?php if ($customerName && $project->getCustomerId()): ?>
                                    <a href="<?php p($urlGenerator->linkToRoute('projectcheck.customer.show', ['id' => $project->getCustomerId()])); ?>" class="customer-link">
                                        <?php p($customerName); ?>
                                    </a>
                                <?php else: ?>
                                    <span><?php p($l->t('Customer #%s', [$project->getCustomerId()])); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="meta-item">
                                <i class="icon-calendar-custom"></i>
                                <span><?php p($project->getCreatedAt() ? $project->getCreatedAt()->format('d.m.Y H:i') : $l->t('Unknown')); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="header-actions">
                    <?php if ($canEdit): ?>
                        <a href="<?php p($urlGenerator->linkToRoute('projectcheck.project.edit', ['id' => $projectId])); ?>" class="button secondary">
                            <i class="icon-edit-custom"></i>
                            <?php p($l->t('Edit Project')); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Budget Alerts -->
        <?php if (isset($budgetInfo['alerts']) && !empty($budgetInfo['alerts'])): ?>
            <div class="section budget-alerts-section">
                <?php foreach ($budgetInfo['alerts'] as $alert): ?>
                    <div class="alert alert-<?php p($alert['level']); ?>">
                        <div class="alert-icon">
                            <?php if ($alert['level'] === 'critical'): ?>
                                <i class="icon-error icon-white"></i>
                            <?php else: ?>
                                <i class="icon-info icon-white"></i>
                            <?php endif; ?>
                        </div>
                        <div class="alert-content">
                            <div class="alert-title"><?php p($alert['title']); ?></div>
                            <div class="alert-message"><?php p($alert['message']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Project Statistics -->
        <div class="section stats-section">
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="icon-time-custom icon-large"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php p($totalHours ?? 0); ?>h</div>
                        <div class="stat-label"><?php p($l->t('TOTAL HOURS')); ?></div>
                        <?php if (isset($budgetInfo['available_hours']) && $budgetInfo['available_hours'] > 0): ?>
                            <div class="stat-sub">
                                <?php p(number_format($budgetInfo['remaining_hours'], 1)); ?>h remaining
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="icon-money-custom icon-large"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number">€<?php p(number_format($budgetInfo['used_budget'] ?? $budgetConsumption, 2)); ?></div>
                        <div class="stat-label"><?php p($l->t('BUDGET USED')); ?></div>
                        <?php if (isset($budgetInfo['total_budget']) && $budgetInfo['total_budget'] > 0): ?>
                            <div class="stat-sub">
                                €<?php p(number_format($budgetInfo['remaining_budget'], 2)); ?> remaining
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="icon-calendar-custom icon-large"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php p($timeEntriesCount ?? 0); ?></div>
                        <div class="stat-label"><?php p($l->t('TIME ENTRIES')); ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="icon-user-custom icon-large"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php p($teamMembersCount ?? 1); ?></div>
                        <div class="stat-label"><?php p($l->t('TEAM MEMBERS')); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Yearly Statistics -->
        <?php if (!empty($yearlyStats)): ?>
            <div class="section yearly-stats-section">
                <div class="section-header">
                    <h3><i class="icon-chart-custom"></i> <?php p($l->t('Yearly Performance Dashboard')); ?></h3>
                    <p><?php p($l->t('Comprehensive analysis of hours and costs by year')); ?></p>
                </div>
                <div class="section-content">
                    <div class="yearly-stats-container">
                        <?php
                        // Calculate totals for progress bars
                        $totalHours = array_sum(array_column($yearlyStats, 'total_hours'));
                        $totalCost = array_sum(array_column($yearlyStats, 'total_cost'));
                        ?>
                        <?php foreach ($yearlyStats as $index => $yearData): ?>
                            <div class="yearly-stat-card">
                                <div class="yearly-stat-header">
                                    <h4><?php p($yearData['year']); ?></h4>
                                    <div class="yearly-stat-badge">
                                        <?php p($yearData['entry_count']); ?> <?php p($l->t('entries')); ?>
                                    </div>
                                </div>
                                <div class="yearly-stat-content">
                                    <div class="yearly-stat-item">
                                        <div class="stat-icon">
                                            <i class="icon-time-custom"></i>
                                        </div>
                                        <div class="stat-details">
                                            <div class="stat-value"><?php p(number_format($yearData['total_hours'], 1)); ?>h</div>
                                            <div class="stat-label"><?php p($l->t('Total Hours')); ?></div>
                                        </div>
                                    </div>
                                    <div class="yearly-stat-item">
                                        <div class="stat-icon">
                                            <i class="icon-money-custom"></i>
                                        </div>
                                        <div class="stat-details">
                                            <div class="stat-value">€<?php p(number_format($yearData['total_cost'], 2)); ?></div>
                                            <div class="stat-label"><?php p($l->t('Total Cost')); ?></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Progress indicators -->
                                <div class="yearly-progress">
                                    <div class="yearly-progress-item">
                                        <div class="yearly-progress-label"><?php p($l->t('Hours Share')); ?></div>
                                        <div class="yearly-progress-bar">
                                            <div class="yearly-progress-fill" data-width="<?php p($totalHours > 0 ? ($yearData['total_hours'] / $totalHours) * 100 : 0); ?>"></div>
                                        </div>
                                        <div class="yearly-progress-percentage"><?php p($totalHours > 0 ? round(($yearData['total_hours'] / $totalHours) * 100, 1) : 0); ?>%</div>
                                    </div>
                                    <div class="yearly-progress-item">
                                        <div class="yearly-progress-label"><?php p($l->t('Cost Share')); ?></div>
                                        <div class="yearly-progress-bar">
                                            <div class="yearly-progress-fill" data-width="<?php p($totalCost > 0 ? ($yearData['total_cost'] / $totalCost) * 100 : 0); ?>"></div>
                                        </div>
                                        <div class="yearly-progress-percentage"><?php p($totalCost > 0 ? round(($yearData['total_cost'] / $totalCost) * 100, 1) : 0); ?>%</div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Project Information -->
            <div class="info-section">
                <div class="section-header">
                    <h3><i class="icon-info-custom"></i> <?php p($l->t('Project Information')); ?></h3>
                </div>
                <div class="section-content">
                    <div class="info-grid">
                        <div class="info-item">
                            <label><?php p($l->t('PROJECT NAME')); ?></label>
                            <span><?php p($project->getName()); ?></span>
                        </div>
                        <div class="info-item">
                            <label><?php p($l->t('STATUS')); ?></label>
                            <span class="status-badge <?php p($statusClass); ?>"><?php p($project->getStatus()); ?></span>
                        </div>
                        <div class="info-item">
                            <label><?php p($l->t('PRIORITY')); ?></label>
                            <span class="priority-badge <?php p($priorityClass); ?>"><?php p($project->getPriority()); ?></span>
                        </div>
                        <div class="info-item">
                            <label><?php p($l->t('PROJECT TYPE')); ?></label>
                            <?php
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
                            $projectType = strtolower($project->getProjectType());
                            $icon = $iconMapping[$projectType] ?? '📋';
                            $displayName = $project->getProjectTypeDisplayName();
                            ?>
                            <div class="project-type-display">
                                <span class="project-type-icon"
                                    data-project-type="<?php p($projectType); ?>"
                                    title="<?php p($displayName); ?>">
                                    <?php p($icon); ?>
                                </span>
                                <span class="project-type-label"><?php p($displayName); ?></span>
                            </div>
                        </div>
                        <div class="info-item">
                            <label><?php p($l->t('CUSTOMER')); ?></label>
                            <?php if ($customerName && $project->getCustomerId()): ?>
                                <a href="<?php p($urlGenerator->linkToRoute('projectcheck.customer.show', ['id' => $project->getCustomerId()])); ?>" class="customer-link">
                                    <?php p($customerName); ?>
                                </a>
                            <?php else: ?>
                                <span><?php p($l->t('Customer #%s', [$project->getCustomerId()])); ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="info-item">
                            <label><?php p($l->t('CREATED BY')); ?></label>
                            <span><?php p($createdBy ?? $l->t('Unknown')); ?></span>
                        </div>
                        <div class="info-item">
                            <label><?php p($l->t('CREATED')); ?></label>
                            <span><?php p($project->getCreatedAt() ? $project->getCreatedAt()->format('d.m.Y H:i') : $l->t('Unknown')); ?></span>
                        </div>
                        <div class="info-item">
                            <label><?php p($l->t('LAST UPDATED')); ?></label>
                            <span><?php p($project->getUpdatedAt() ? $project->getUpdatedAt()->format('d.m.Y H:i') : $l->t('Unknown')); ?></span>
                        </div>
                        <div class="info-item full-width">
                            <label><?php p($l->t('SHORT DESCRIPTION')); ?></label>
                            <span><?php p($project->getShortDescription() ?: 'No description provided'); ?></span>
                        </div>
                        <?php if ($project->getDetailedDescription()): ?>
                            <div class="info-item full-width">
                                <label><?php p($l->t('DETAILED DESCRIPTION')); ?></label>
                                <span><?php p($project->getDetailedDescription()); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Project Timeline & Budget -->
            <div class="projects-section">
                <div class="section-header">
                    <h3><i class="icon-calendar-custom"></i> <?php p($l->t('Timeline & Budget')); ?></h3>
                </div>
                <div class="section-content">
                    <!-- Project Overview Card -->
                    <div class="project-overview-card">
                        <div class="overview-header">
                            <div class="overview-title">
                                <h4><?php p($project->getName()); ?></h4>
                                <p class="overview-description"><?php p($project->getShortDescription() ?: 'No description provided'); ?></p>
                            </div>
                            <div class="overview-status">
                                <span class="status-badge <?php p($statusClass); ?>"><?php p($project->getStatus()); ?></span>
                            </div>
                        </div>

                        <div class="overview-stats">
                            <div class="overview-stat">
                                <div class="stat-icon">
                                    <i class="icon-calendar-custom"></i>
                                </div>
                                <div class="stat-content">
                                    <span class="stat-label"><?php p($l->t('Timeline')); ?></span>
                                    <span class="stat-value">
                                        <?php if ($project->getStartDate() && $project->getEndDate()): ?>
                                            <?php p($project->getStartDate()->format('d.m.Y')); ?> - <?php p($project->getEndDate()->format('d.m.Y')); ?>
                                        <?php else: ?>
                                            <?php p($l->t('Not set')); ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                            <div class="overview-stat">
                                <div class="stat-icon">
                                    <i class="icon-money-custom"></i>
                                </div>
                                <div class="stat-content">
                                    <span class="stat-label"><?php p($l->t('Budget')); ?></span>
                                    <span class="stat-value">€<?php p(number_format($project->getTotalBudget() ?? 0, 2)); ?></span>
                                </div>
                            </div>
                            <div class="overview-stat">
                                <div class="stat-icon">
                                    <i class="icon-time-custom"></i>
                                </div>
                                <div class="stat-content">
                                    <span class="stat-label"><?php p($l->t('Progress')); ?></span>
                                    <span class="stat-value"><?php p($projectProgress ?? 0); ?>%</span>
                                </div>
                            </div>
                            <div class="overview-stat">
                                <div class="stat-icon">
                                    <i class="icon-money-custom"></i>
                                </div>
                                <div class="stat-content">
                                    <span class="stat-label"><?php p($l->t('Consumed')); ?></span>
                                    <span class="stat-value">€<?php p(number_format($budgetConsumption, 2)); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Budget Progress Section -->
                    <div class="budget-progress-section">
                        <div class="progress-header">
                            <h4><?php p($l->t('Budget Usage')); ?></h4>
                            <div class="progress-stats">
                                <?php if (isset($budgetInfo['consumption_percentage'])): ?>
                                    <span class="budget-percentage budget-<?php p($budgetInfo['warning_level']); ?>">
                                        <?php p($budgetInfo['consumption_percentage']); ?>% used
                                    </span>
                                    <span class="budget-remaining">€<?php p(number_format($budgetInfo['remaining_budget'], 2)); ?> remaining</span>
                                <?php else: ?>
                                    <span class="budget-percentage"><?php p(round(($budgetConsumption / max(1, $project->getTotalBudget() ?? 1)) * 100, 1)); ?>% used</span>
                                    <span class="budget-remaining">€<?php p(number_format(($project->getTotalBudget() ?? 0) - $budgetConsumption, 2)); ?> remaining</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="budget-progress">
                            <?php if (isset($budgetInfo['consumption_percentage'])): ?>
                                <div class="budget-progress-fill budget-<?php p($budgetInfo['warning_level']); ?>"
                                    data-width="<?php p(min(100, $budgetInfo['consumption_percentage'])); ?>"></div>
                            <?php else: ?>
                                <div class="budget-progress-fill <?php echo $warningLevel; ?>"
                                    data-width="<?php p(min(100, ($budgetConsumption / max(1, $project->getTotalBudget() ?? 1)) * 100)); ?>"></div>
                            <?php endif; ?>
                        </div>
                        <div class="budget-breakdown">
                            <div class="breakdown-item">
                                <span class="breakdown-label"><?php p($l->t('Total Budget')); ?></span>
                                <span class="breakdown-value">€<?php p(number_format($budgetInfo['total_budget'] ?? $project->getTotalBudget() ?? 0, 2)); ?></span>
                            </div>
                            <div class="breakdown-item">
                                <span class="breakdown-label"><?php p($l->t('Used')); ?></span>
                                <span class="breakdown-value consumed">€<?php p(number_format($budgetInfo['used_budget'] ?? $budgetConsumption, 2)); ?></span>
                            </div>
                            <div class="breakdown-item">
                                <span class="breakdown-label"><?php p($l->t('Remaining')); ?></span>
                                <span class="breakdown-value remaining">€<?php p(number_format($budgetInfo['remaining_budget'] ?? (($project->getTotalBudget() ?? 0) - $budgetConsumption), 2)); ?></span>
                            </div>
                            <?php if (isset($budgetInfo['is_over_budget']) && $budgetInfo['is_over_budget']): ?>
                                <div class="breakdown-item over-budget">
                                    <span class="breakdown-label"><?php p($l->t('Over Budget')); ?></span>
                                    <span class="breakdown-value over-budget">€<?php p(number_format($budgetInfo['used_budget'] - $budgetInfo['total_budget'], 2)); ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Project Timeline Details -->
                    <div class="timeline-details">
                        <h4><?php p($l->t('Project Timeline')); ?></h4>
                        <div class="timeline-grid">
                            <div class="timeline-item">
                                <div class="timeline-icon">
                                    <i class="icon-calendar-custom"></i>
                                </div>
                                <div class="timeline-content">
                                    <label><?php p($l->t('Start Date')); ?></label>
                                    <span><?php p($project->getStartDate() ? $project->getStartDate()->format('d.m.Y') : 'Not set'); ?></span>
                                </div>
                            </div>
                            <div class="timeline-item">
                                <div class="timeline-icon">
                                    <i class="icon-calendar-custom"></i>
                                </div>
                                <div class="timeline-content">
                                    <label><?php p($l->t('End Date')); ?></label>
                                    <span><?php p($project->getEndDate() ? $project->getEndDate()->format('d.m.Y') : 'Not set'); ?></span>
                                </div>
                            </div>
                            <div class="timeline-item">
                                <div class="timeline-icon">
                                    <i class="icon-time-custom"></i>
                                </div>
                                <div class="timeline-content">
                                    <label><?php p($l->t('Duration')); ?></label>
                                    <span><?php
                                            if ($project->getStartDate() && $project->getEndDate()) {
                                                $start = $project->getStartDate();
                                                $end = $project->getEndDate();
                                                $diff = $start->diff($end);
                                                echo $diff->days . ' days';
                                            } else {
                                                echo 'Not set';
                                            }
                                            ?></span>
                                </div>
                            </div>
                            <div class="timeline-item">
                                <div class="timeline-icon">
                                    <i class="icon-chart-custom"></i>
                                </div>
                                <div class="timeline-content">
                                    <label><?php p($l->t('Progress')); ?></label>
                                    <span><?php p($projectProgress ?? 0); ?>%</span>
                                </div>
                            </div>
                            <div class="timeline-item">
                                <div class="timeline-icon">
                                    <i class="icon-time-custom"></i>
                                </div>
                                <div class="timeline-content">
                                    <label><?php p($l->t('Hours Used')); ?></label>
                                    <span><?php p($totalHours); ?> / <?php p($project->getAvailableHours() ?? 0); ?> <?php p($l->t('hours')); ?></span>
                                </div>
                            </div>
                            <div class="timeline-item">
                                <div class="timeline-icon">
                                    <i class="icon-money-custom"></i>
                                </div>
                                <div class="timeline-content">
                                    <label><?php p($l->t('Hourly Rate')); ?></label>
                                    <span>€<?php p(number_format($project->getHourlyRate() ?? 0, 2)); ?><?php p($l->t('/hour')); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Project Files -->
        <div class="section">
            <div class="section-header section-header--files">
                <h3><i class="icon-project-files"></i> <?php p($l->t('Project Files')); ?></h3>
				<?php if ($canManageFiles): ?>
					<form id="project-file-upload-form"
						class="inline-form file-upload-bar"
						action="<?php p($urlGenerator->linkToRoute('projectcheck.projectfile.upload', ['projectId' => $projectId])); ?>"
						method="POST"
						enctype="multipart/form-data">
						<input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']); ?>">
						<label class="button secondary ghost" for="project_files_upload">
							<i class="icon-upload"></i>
							<?php p($l->t('Select files')); ?>
						</label>
						<input type="file"
							id="project_files_upload"
							name="project_files[]"
							aria-label="<?php p($l->t('Upload project files')); ?>"
							class="file-input-hidden"
							multiple
							required>
					</form>
				<?php endif; ?>
            </div>
            <div class="section-content">
                <?php if (!empty($projectFiles)): ?>
                    <ul class="project-files-list">
                        <?php foreach ($projectFiles as $file): ?>
                            <li class="project-file-row" data-file-id="<?php p($file->getId()); ?>">
                                <div class="file-info">
                                    <i class="icon-file-custom"></i>
                                    <div class="file-meta">
                                        <a class="file-name"
                                            href="<?php p($urlGenerator->linkToRoute('projectcheck.projectfile.download', ['projectId' => $projectId, 'fileId' => $file->getId()])); ?>"
                                            target="_blank" rel="noreferrer noopener">
                                            <?php p($file->getDisplayName()); ?>
                                        </a>
                                        <div class="file-details">
                                            <span><?php p($l->t('Uploaded by %s', [$file->getUploadedBy()])); ?></span>
                                            <span>•</span>
                                            <span><?php p($file->getCreatedAt() ? $file->getCreatedAt()->format('d.m.Y H:i') : ''); ?></span>
                                            <span>•</span>
                                            <span><?php p(Util::humanFileSize($file->getSize())); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="file-actions">
                                    <a class="button secondary"
                                        href="<?php p($urlGenerator->linkToRoute('projectcheck.projectfile.download', ['projectId' => $projectId, 'fileId' => $file->getId()])); ?>"
                                        target="_blank" rel="noreferrer noopener">
                                        <i class="icon icon-download"></i>
                                        <?php p($l->t('Download')); ?>
                                    </a>
                                    <?php if ($canManageFiles): ?>
                                        <button type="button"
                                            class="button danger ghost delete-file-btn"
                                            data-delete-url="<?php p($urlGenerator->linkToRoute('projectcheck.projectfile.delete', ['projectId' => $projectId, 'fileId' => $file->getId()])); ?>"
                                            data-file-name="<?php p($file->getDisplayName()); ?>">
                                            <i class="icon icon-delete"></i>
                                            <?php p($l->t('Delete')); ?>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="icon-project-files icon-large"></i>
                        <h3><?php p($l->t('No files uploaded yet')); ?></h3>
                        <p><?php p($l->t('Add contracts, checklists, or other documents to keep everything in one place.')); ?></p>
                        <?php if ($canManageFiles): ?>
                            <label class="button primary" for="project_files_upload">
                                <i class="icon-add-custom"></i>
                                <?php p($l->t('Upload files')); ?>
                            </label>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Time Entries -->
        <?php if (!empty($timeEntries)): ?>
            <div class="section">
                <div class="section-header">
                    <h3><i class="icon-time-custom"></i> <?php p($l->t('Recent Time Entries')); ?></h3>
                    <div class="section-header-actions">
                        <a href="<?php p($urlGenerator->linkToRoute('projectcheck.timeentry.create', ['project_id' => $projectId])); ?>" class="button primary">
                            <i class="icon-add-custom"></i>
                            <?php p($l->t('Add Time Entry')); ?>
                        </a>
                    </div>
                </div>
                <div class="section-content">
                    <div class="time-entries-list">
                        <?php foreach ($timeEntries as $entry): ?>
                            <div class="time-entry-item">
                                <div class="entry-header">
                                    <div class="entry-date-info">
                                        <span class="entry-date"><?php p($entry->getDate() ? $entry->getDate()->format('d.m.Y') : ''); ?></span>
                                        <span class="entry-user"><?php p($entry->getUserId()); ?></span>
                                    </div>
                                    <div class="entry-stats">
                                        <span class="entry-hours"><?php p($entry->getHours()); ?> <?php p($l->t('hours')); ?></span>
                                        <span class="entry-cost">€<?php p(number_format($entry->getHours() * $entry->getHourlyRate(), 2)); ?></span>
                                    </div>
                                </div>
                                <?php if ($entry->getDescription()): ?>
                                    <div class="entry-description">
                                        <?php p($entry->getDescription()); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if (count($timeEntries) > 5): ?>
                        <div class="time-entries-footer">
                            <a href="<?php p($urlGenerator->linkToRoute('projectcheck.timeentry.index', ['project_id' => $projectId])); ?>" class="button secondary">
                                <?php p($l->t('View All Time Entries')); ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Empty Time Entries State -->
            <div class="section">
                <div class="section-header">
                    <h3><i class="icon-time-custom"></i> <?php p($l->t('Time Entries')); ?></h3>
                    <div class="section-header-actions">
                        <a href="<?php p($urlGenerator->linkToRoute('projectcheck.timeentry.create', ['project_id' => $projectId])); ?>" class="button primary">
                            <i class="icon-add-custom"></i>
                            <?php p($l->t('Add Time Entry')); ?>
                        </a>
                    </div>
                </div>
                <div class="section-content">
                    <div class="empty-state">
                        <i class="icon-time-custom icon-large"></i>
                        <h3><?php p($l->t('No time entries yet')); ?></h3>
                        <p><?php p($l->t('Start tracking time for this project by adding your first time entry.')); ?></p>
                        <a href="<?php p($urlGenerator->linkToRoute('projectcheck.timeentry.create', ['project_id' => $projectId])); ?>" class="button primary">
                            <i class="icon-add-custom"></i>
                            <?php p($l->t('Add First Time Entry')); ?>
                        </a>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Team Members -->
        <?php if (!empty($teamMembers)): ?>
            <div class="section">
                <div class="section-header">
                    <h3><i class="icon-user-custom"></i> <?php p($l->t('Team Members')); ?></h3>
                    <div class="section-header-actions">
                        <button type="button" class="button primary" id="add-team-member-btn">
                            <i class="icon-add-custom"></i>
                            <?php p($l->t('Add Team Member')); ?>
                        </button>
                    </div>
                </div>
                <div class="section-content">
                    <div class="team-members-list">
                        <?php foreach ($teamMembers as $member): ?>
                            <div class="team-member-item">
                                <div class="member-avatar">
                                    <div class="avatar-initial"><?php p(strtoupper(substr($member['name'] ?? 'U', 0, 1))); ?></div>
                                </div>
                                <div class="member-info">
                                    <span class="member-name"><?php p($member['name'] ?? $l->t('Unknown')); ?></span>
                                    <span class="member-role"><?php p($member['role'] ?? 'Team Member'); ?></span>
                                </div>
                                <div class="member-hours">
                                    <span class="hours-label"><?php p($l->t('Hours:')); ?></span>
                                    <span class="hours-value"><?php p($member['hours'] ?? 0); ?></span>
                                </div>
                                <div class="member-actions">
                                    <?php if ($canManageMembers): ?>
                                        <button type="button" class="action-btn remove-member-btn"
                                            data-member-id="<?php p($member['id'] ?? ''); ?>"
                                            data-member-name="<?php p($member['name'] ?? $l->t('Unknown')); ?>"
                                            title="<?php p($l->t('Remove from project')); ?>">
                                            <i class="icon-delete-custom"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Status Change Modal -->
<div id="statusChangeModal" class="modal" style="display: none;">
    <div class="modal-content">
        <div class="modal-header">
            <h3><?php p($l->t('Change Project Status')); ?></h3>
            <span class="close" id="close-status-modal">&times;</span>
        </div>
        <div class="modal-body">
            <form id="statusChangeForm">
                <div class="form-group">
                    <label for="newStatus"><?php p($l->t('New Status')); ?></label>
                    <select id="newStatus" name="status" required>
                        <option value="Active"><?php p($l->t('Active')); ?></option>
                        <option value="On Hold"><?php p($l->t('On Hold')); ?></option>
                        <option value="Completed"><?php p($l->t('Completed')); ?></option>
                        <option value="Cancelled"><?php p($l->t('Cancelled')); ?></option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="statusReason"><?php p($l->t('Reason (optional)')); ?></label>
                    <textarea id="statusReason" name="reason" rows="3"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="button secondary" id="cancel-status-change"><?php p($l->t('Cancel')); ?></button>
            <button type="button" class="button primary" id="submit-status-change"><?php p($l->t('Update Status')); ?></button>
        </div>
    </div>
</div>

<script nonce="<?php p($_['cspNonce']) ?>">
    // Event listeners for buttons
    document.addEventListener('DOMContentLoaded', function() {
        // Set widths for progress bars using data attributes
        document.querySelectorAll('[data-width]').forEach(function(el) {
            el.style.width = el.getAttribute('data-width') + '%';
        });

        const addTeamMemberBtn = document.getElementById('add-team-member-btn');
        if (addTeamMemberBtn) {
            addTeamMemberBtn.addEventListener('click', showAddTeamMemberModal);
        }

        const closeStatusModal = document.getElementById('close-status-modal');
        if (closeStatusModal) {
            closeStatusModal.addEventListener('click', closeStatusChangeModal);
        }

        const cancelStatusChange = document.getElementById('cancel-status-change');
        if (cancelStatusChange) {
            cancelStatusChange.addEventListener('click', closeStatusChangeModal);
        }

        const submitStatusChange = document.getElementById('submit-status-change');
        if (submitStatusChange) {
            submitStatusChange.addEventListener('click', submitStatusChangeFunc);
        }
    });

    function showStatusChangeModal() {
        const modal = document.getElementById('statusChangeModal');
        if (modal) {
            modal.style.display = 'block';
        }
    }

    function closeStatusChangeModal() {
        const modal = document.getElementById('statusChangeModal');
        if (modal) {
            modal.style.display = 'none';
        }
    }

    function submitStatusChangeFunc() {
        const form = document.getElementById('statusChangeForm');
        const formData = new FormData(form);

        fetch('<?php p($urlGenerator->linkToRoute('projectcheck.project.changeStatus', ['id' => $projectId])); ?>', {
                method: 'PUT',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'requesttoken': '<?php p($_['requesttoken']) ?>'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('<?php p($l->t('Error updating status')); ?>: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('<?php p($l->t('Error updating status')); ?>');
            });
    }

    function confirmDelete() {
        if (confirm('<?php p($l->t('Are you sure you want to delete this project? This action cannot be undone.')); ?>')) {
            window.location.href = '<?php p($urlGenerator->linkToRoute('projectcheck.project.delete', ['id' => $projectId])); ?>';
        }
    }

    function showAddTeamMemberModal() {
        // Implement team member modal functionality
        alert('<?php p($l->t('Add team member functionality will be implemented here')); ?>');
    }

    // Member removal functionality
    document.addEventListener('click', function(e) {
        if (e.target.closest('.remove-member-btn')) {
            const button = e.target.closest('.remove-member-btn');
            const memberId = button.getAttribute('data-member-id');
            const memberName = button.getAttribute('data-member-name');
            showMemberRemovalModal(memberId, memberName);
        }
    });

    function showMemberRemovalModal(memberId, memberName) {
        if (typeof window.projectcheckDeletionModal === 'undefined') {
            console.error('Deletion modal not loaded');
            // Fallback to confirm dialog
            if (confirm(`Are you sure you want to remove ${memberName} from this project?`)) {
                removeMember(memberId);
            }
            return;
        }

        const deleteUrl = `/index.php/apps/projectcheck/api/project-members/${memberId}/remove`;

        // Show the modal
        window.projectcheckDeletionModal.show({
            entityType: 'member',
            entityId: memberId,
            entityName: memberName,
            deleteUrl: deleteUrl,
            onSuccess: function(entity) {
                // Remove the member item from the DOM
                const memberItem = document.querySelector(`.remove-member-btn[data-member-id="${entity.id}"]`)?.closest('.team-member-item');
                if (memberItem) {
                    memberItem.remove();
                }

                // Show success message
                if (typeof OC !== 'undefined' && OC.Notification) {
                    OC.Notification.showTemporary('<?php p($l->t('Team member removed successfully')); ?>');
                } else {
                    alert('<?php p($l->t('Team member removed successfully')); ?>');
                }
            },
            onCancel: function() {
                console.log('Member removal cancelled');
            }
        });
    }

    function removeMember(memberId) {
        const deleteUrl = `/index.php/apps/projectcheck/api/project-members/${memberId}/remove`;
        const token = document.querySelector('input[name="requesttoken"]')?.value ||
            (typeof OC !== 'undefined' ? OC.requestToken : '');

        fetch(deleteUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest',
                    'requesttoken': token
                },
                body: '_method=DELETE'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the member item from the DOM
                    const memberItem = document.querySelector(`.remove-member-btn[data-member-id="${memberId}"]`)?.closest('.team-member-item');
                    if (memberItem) {
                        memberItem.remove();
                    }

                    // Show success message
                    if (typeof OC !== 'undefined' && OC.Notification) {
                        OC.Notification.showTemporary('<?php p($l->t('Team member removed successfully')); ?>');
                    } else {
                        alert('<?php p($l->t('Team member removed successfully')); ?>');
                    }
                } else {
                    alert('<?php p($l->t('Error')); ?>: ' + (data.error || '<?php p($l->t('Failed to remove team member')); ?>'));
                }
            })
            .catch(error => {
                console.error('Error removing member:', error);
                alert('<?php p($l->t('Error removing team member. Please try again.')); ?>');
            });
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('statusChangeModal');
        if (event.target === modal) {
            closeStatusChangeModal();
        }
    }
</script>

<script nonce="<?php p($_['cspNonce'] ?? '') ?>">
    document.addEventListener('DOMContentLoaded', () => {
        const filesList = document.querySelector('.project-files-list');
        const requestTokenInput = document.querySelector('input[name="requesttoken"]');
        const requestToken = requestTokenInput ? requestTokenInput.value : (typeof OC !== 'undefined' ? OC.requestToken : '');
        const fileInput = document.getElementById('project_files_upload');
        const uploadForm = document.getElementById('project-file-upload-form');

        if (filesList) {
            filesList.addEventListener('click', async (event) => {
                const button = event.target.closest('.delete-file-btn');
                if (!button) {
                    return;
                }

                const fileName = button.dataset.fileName || '';
                if (!confirm('<?php p($l->t('Delete this file?')); ?>' + (fileName ? ' ' + fileName : ''))) {
                    return;
                }

                const deleteUrl = button.dataset.deleteUrl;
                const url = new URL(deleteUrl, window.location.origin);
                if (requestToken) {
                    url.searchParams.set('requesttoken', requestToken);
                }
                try {
                    const response = await fetch(url.toString(), {
                        method: 'DELETE',
                        headers: {
                            'requesttoken': requestToken,
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        credentials: 'same-origin'
                    });

                    if (!response.ok) {
                        const data = await response.json().catch(() => ({}));
                        const msg = data.error || 'Failed to delete';
                        throw new Error(msg);
                    }

                    const row = button.closest('.project-file-row');
                    if (row) {
                        row.remove();
                    }
                } catch (error) {
                    console.error(error);
                    alert(error?.message || '<?php p($l->t('Could not delete the file. Please try again.')); ?>');
                }
            });
        }

        if (fileInput && uploadForm) {
            fileInput.addEventListener('change', () => {
                if (fileInput.files && fileInput.files.length > 0) {
                    uploadForm.submit();
                }
            });
        }
    });
</script>

<style nonce="<?php p($_['cspNonce'] ?? '') ?>">
    .team-member-item {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1rem;
        background: var(--color-main-background);
        border: 1px solid var(--color-border);
        border-radius: 8px;
        margin-bottom: 0.5rem;
        position: relative;
    }

    .member-actions {
        margin-left: auto;
        display: flex;
        gap: 0.5rem;
    }

    .action-btn {
        background: none;
        border: none;
        padding: 0.5rem;
        border-radius: 4px;
        cursor: pointer;
        color: var(--color-text-maxcontrast);
        transition: all 0.2s ease;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .action-btn:hover {
        background: var(--color-background-hover);
        color: var(--color-error);
    }

    .remove-member-btn:hover {
        background: var(--color-error-background);
        color: var(--color-error);
    }

    .member-avatar {
        flex-shrink: 0;
    }

    .member-info {
        flex: 1;
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
    }

    .member-hours {
        display: flex;
        flex-direction: column;
        gap: 0.25rem;
        text-align: right;
    }
</style>