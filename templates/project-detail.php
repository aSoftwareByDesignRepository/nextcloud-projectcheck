<?php

/**
 * Project detail view template
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

use OCP\Util;

Util::addScript('projectcheck', 'common/api');
Util::addScript('projectcheck', 'common/entity-picker');
Util::addScript('projectcheck', 'project-detail');
Util::addScript('projectcheck', 'project-detail-files');
if (($costRateMode ?? '') === 'project_member') {
	Util::addScript('projectcheck', 'project-detail-rates');
}
Util::addScript('projectcheck', 'projects');
Util::addStyle('projectcheck', 'projects');
Util::addStyle('projectcheck', 'budget-alerts');
Util::addStyle('projectcheck', 'navigation');
Util::addStyle('projectcheck', 'common/progress-bars');
// Required so empty-state and pc-section surface treatments are fully styled
// on this page (icon halo, lead text, CTA, focus rings). Other pages already
// pull this in; project-detail historically did not, which left empty states
// visually flat.
Util::addStyle('projectcheck', 'common/accessibility');

if (!isset($project) || !($project instanceof \OCA\ProjectCheck\Db\Project)) {
    throw new Exception('Project not found');
}

$projectId = $project->getId();
$costRateMode = $_['costRateMode'] ?? $project->getCostRateMode();
$statusClass = 'status-' . strtolower(str_replace(' ', '-', $project->getStatus()));
$priorityClass = 'priority-' . strtolower($project->getPriority());
$budgetConsumption = isset($budgetConsumption) ? $budgetConsumption : 0;
$warningLevel = isset($warningLevel) ? $warningLevel : 'none';
$allowedStatusTargets = $_['allowedStatusTargets'] ?? [];
$canAddTimeEntry = $_['canAddTimeEntry'] ?? $project->allowsTimeTracking();
$usingAdminTimeEntryOverride = !empty($_['usingAdminTimeEntryOverride']);
$addTeamMemberUrl = $_['addTeamMemberUrl'] ?? null;
$bulkAddSuccess = isset($_GET['bulk_add_success']) && (string)$_GET['bulk_add_success'] === '1';
$bulkAddAddedCount = isset($_GET['added_count']) ? max(0, (int)$_GET['added_count']) : 0;
$uploadSuccessCount = isset($_GET['uploaded']) ? max(0, (int)$_GET['uploaded']) : 0;
$uploadSuccess = $uploadSuccessCount > 0;
$fmt = $_['fmt'] ?? null;
$currencyCode = isset($_['orgCurrency']) && is_string($_['orgCurrency']) ? strtoupper(trim($_['orgCurrency'])) : 'EUR';
if (preg_match('/^[A-Z]{3}$/', $currencyCode) !== 1) {
	$currencyCode = 'EUR';
}
$htmlLang = isset($_['htmlLang']) && is_string($_['htmlLang']) ? $_['htmlLang'] : 'en';
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<?php
$pageId = 'project-detail';
$pageTitle = $project->getName();
$pageHelp = $l->t('Status, customer, and time tracking in one place.');
$includeScopeStrip = true;
include __DIR__ . '/common/page-start.php';
?>
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb-container">
            <nav class="breadcrumb" aria-label="<?php p($l->t('Breadcrumb')); ?>">
                <ol>
                    <li><a href="<?php p($urlGenerator->linkToRoute('projectcheck.project.index')); ?>"><?php p($l->t('Projects')); ?></a></li>
                    <li aria-current="page"><?php p($project->getName()); ?></li>
                </ol>
            </nav>
        </div>

        <?php if ($project->isArchived()): ?>
            <div class="project-detail__notice project-detail__notice--archived" role="status" aria-live="polite">
                <i class="icon-pause" aria-hidden="true"></i>
                <p><?php p($l->t('This project is archived. It is read-only. To log time or edit details, reactivate it to Active or On Hold using "Change status".')); ?></p>
            </div>
        <?php endif; ?>

        <?php if (!empty($showCreatedBanner)): ?>
            <div class="notice notice-success pc-created-banner" role="status" aria-live="polite">
                <p><?php p($l->t('Project created. Add your team next so people can log time.')); ?></p>
                <a class="button secondary" href="#team-section"><?php p($l->t('Go to team')); ?></a>
            </div>
        <?php endif; ?>

        <?php if ($bulkAddSuccess): ?>
            <div class="notice notice-success" role="status" aria-live="polite" aria-atomic="true">
                <i class="icon icon-checkmark" aria-hidden="true"></i>
                <span><?php p($l->t('Added %d users to the project', [$bulkAddAddedCount])); ?></span>
            </div>
        <?php endif; ?>

        <?php if ($uploadSuccess): ?>
            <div class="notice notice-success" role="status" aria-live="polite" aria-atomic="true">
                <i class="icon icon-checkmark" aria-hidden="true"></i>
                <span><?php p($l->n('Uploaded %n file.', 'Uploaded %n files.', $uploadSuccessCount)); ?></span>
            </div>
        <?php endif; ?>

        <!-- Page Header: title + key actions (single focal area) -->
        <?php
        ob_start(); ?>
                        <?php if (!empty($pricingModeLabel)): ?>
                            <p class="pc-scope-strip__badge" role="status">
                                <span class="pc-pricing-badge-label"><?php p($l->t('How hours are priced:')); ?></span>
                                <strong><?php p($pricingModeLabel); ?></strong>
                            </p>
                        <?php endif; ?>
                        <div class="project-meta">
                            <div class="meta-item">
                                <i class="icon-user-custom" aria-hidden="true"></i>
                                <?php if ($customerName && $project->getCustomerId()): ?>
                                    <a href="<?php p($urlGenerator->linkToRoute('projectcheck.customer.show', ['id' => $project->getCustomerId()])); ?>" class="customer-link">
                                        <?php p($customerName); ?>
                                    </a>
                                <?php else: ?>
                                    <span><?php p($l->t('Customer #%s', [$project->getCustomerId()])); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="meta-item">
                                <i class="icon-calendar-custom" aria-hidden="true"></i>
                                <span><?php p($project->getCreatedAt() ? $project->getCreatedAt()->format('d.m.Y H:i') : $l->t('Unknown')); ?></span>
                            </div>
                        </div>
        <?php
        $headerMetaHtml = ob_get_clean();
        ob_start(); ?>
                    <?php if (!empty($canChangeStatus) && $canChangeStatus && $allowedStatusTargets !== []): ?>
                        <button type="button" class="button" id="open-status-modal-btn">
                            <?php p($l->t('Change status')); ?>
                        </button>
                    <?php endif; ?>
                    <?php if (!empty($canEdit) && $canEdit): ?>
                        <a href="<?php p($urlGenerator->linkToRoute('projectcheck.project.edit', ['id' => $projectId])); ?>" class="button primary">
                            <i class="icon-edit-custom" aria-hidden="true"></i>
                            <?php p($l->t('Edit project')); ?>
                        </a>
                    <?php endif; ?>
        <?php
        $headerActionsHtml = ob_get_clean();
        $headerActionsLabel = $l->t('Project actions');
        include __DIR__ . '/common/page-header-section.php';
        ?>

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
        <div class="section stats-section" aria-labelledby="pc-project-key-figures">
            <h3 id="pc-project-key-figures" class="stats-section__title"><?php p($l->t('Key figures')); ?></h3>
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="icon-time-custom icon-large"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php p($totalHours ?? 0); ?>h</div>
                        <div class="stat-label"><?php p($l->t('TOTAL HOURS')); ?></div>
                        <div class="stat-sub stat-sub--capacity">
                            <?php $compact = true; $compactSilent = true; include __DIR__ . '/parts/capacity-hours-display.php'; unset($compact, $compactSilent); ?>
                        </div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="icon-money-custom icon-large"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php p($fmt ? $fmt->currency((float)($budgetInfo['used_budget'] ?? $budgetConsumption)) : $currencyCode . ' ' . number_format((float)($budgetInfo['used_budget'] ?? $budgetConsumption), 2)); ?></div>
                        <div class="stat-label"><?php p($l->t('BUDGET USED')); ?></div>
                        <?php if (isset($budgetInfo['total_budget']) && $budgetInfo['total_budget'] > 0): ?>
                            <div class="stat-sub">
                                <?php include __DIR__ . '/parts/budget-remaining-line.php'; ?>
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
                                            <div class="stat-value"><?php p($fmt ? $fmt->currency((float)$yearData['total_cost']) : $currencyCode . ' ' . number_format((float)$yearData['total_cost'], 2)); ?></div>
                                            <div class="stat-label"><?php p($l->t('Total Cost')); ?></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Progress indicators -->
                                <?php
                                $hoursSharePct = $totalHours > 0 ? ($yearData['total_hours'] / $totalHours) * 100 : 0;
                                $costSharePct = $totalCost > 0 ? ($yearData['total_cost'] / $totalCost) * 100 : 0;
                                ?>
                                <div class="yearly-progress">
                                    <div class="yearly-progress-item">
                                        <div class="yearly-progress-label"><?php p($l->t('Hours Share')); ?></div>
                                        <div class="yearly-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php p(round($hoursSharePct, 1)); ?>" aria-label="<?php p($l->t('Hours Share')); ?>">
                                            <div class="yearly-progress-fill" style="width: <?php p($hoursSharePct); ?>%"></div>
                                        </div>
                                        <div class="yearly-progress-percentage"><?php p(round($hoursSharePct, 1)); ?>%</div>
                                    </div>
                                    <div class="yearly-progress-item">
                                        <div class="yearly-progress-label"><?php p($l->t('Cost Share')); ?></div>
                                        <div class="yearly-progress-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php p(round($costSharePct, 1)); ?>" aria-label="<?php p($l->t('Cost Share')); ?>">
                                            <div class="yearly-progress-fill" style="width: <?php p($costSharePct); ?>%"></div>
                                        </div>
                                        <div class="yearly-progress-percentage"><?php p(round($costSharePct, 1)); ?>%</div>
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
                            <span class="status-badge <?php p($statusClass); ?>"><?php p($l->t((string)$project->getStatus())); ?></span>
                        </div>
                        <div class="info-item">
                            <label><?php p($l->t('PRIORITY')); ?></label>
                            <span class="priority-badge <?php p($priorityClass); ?>"><?php p($l->t((string)$project->getPriority())); ?></span>
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
                                    title="<?php p($l->t((string)$displayName)); ?>">
                                    <?php p($icon); ?>
                                </span>
                                <span class="project-type-label"><?php p($l->t((string)$displayName)); ?></span>
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
                            <span><?php p($project->getShortDescription() ?: $l->t('No description provided')); ?></span>
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
                                <p class="overview-description"><?php p($project->getShortDescription() ?: $l->t('No description provided')); ?></p>
                            </div>
                            <div class="overview-status">
                                <span class="status-badge <?php p($statusClass); ?>"><?php p($l->t((string)$project->getStatus())); ?></span>
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
                                    <span class="stat-value"><?php p($fmt ? $fmt->currency((float)($project->getTotalBudget() ?? 0)) : $currencyCode . ' ' . number_format((float)($project->getTotalBudget() ?? 0), 2)); ?></span>
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
                                    <span class="stat-value"><?php p($fmt ? $fmt->currency((float)$budgetConsumption) : $currencyCode . ' ' . number_format((float)$budgetConsumption, 2)); ?></span>
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
                                        <?php p($l->t('%s%% used', [number_format((float)$budgetInfo['consumption_percentage'], 1, '.', '')])); ?>
                                    </span>
                                    <span class="budget-remaining"><?php include __DIR__ . '/parts/budget-remaining-line.php'; ?></span>
                                <?php else: ?>
                                    <span class="budget-percentage"><?php p($l->t('%s%% used', [number_format((float)round(($budgetConsumption / max(1, $project->getTotalBudget() ?? 1)) * 100, 1), 1, '.', '')])); ?></span>
                                    <span class="budget-remaining"><?php p($l->t('%s remaining', [($fmt ? $fmt->currency((float)(($project->getTotalBudget() ?? 0) - $budgetConsumption)) : $currencyCode . ' ' . number_format((float)(($project->getTotalBudget() ?? 0) - $budgetConsumption), 2))])); ?></span>
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
                                <span class="breakdown-value"><?php p($fmt ? $fmt->currency((float)($budgetInfo['total_budget'] ?? $project->getTotalBudget() ?? 0)) : $currencyCode . ' ' . number_format((float)($budgetInfo['total_budget'] ?? $project->getTotalBudget() ?? 0), 2)); ?></span>
                            </div>
                            <div class="breakdown-item">
                                <span class="breakdown-label"><?php p($l->t('Used')); ?></span>
                                <span class="breakdown-value consumed"><?php p($fmt ? $fmt->currency((float)($budgetInfo['used_budget'] ?? $budgetConsumption)) : $currencyCode . ' ' . number_format((float)($budgetInfo['used_budget'] ?? $budgetConsumption), 2)); ?></span>
                            </div>
                            <div class="breakdown-item">
                                <span class="breakdown-label"><?php p(!empty($budgetInfo['is_over_budget']) ? $l->t('Over Budget') : $l->t('Remaining')); ?></span>
                                <span class="breakdown-value remaining<?php echo !empty($budgetInfo['is_over_budget']) ? ' over-budget' : ''; ?>">
                                    <?php
                                    if (!empty($budgetInfo['is_over_budget']) && !empty($budgetInfo['over_budget_amount'])) {
                                        p($fmt ? $fmt->currency((float)$budgetInfo['over_budget_amount']) : $currencyCode . ' ' . number_format((float)$budgetInfo['over_budget_amount'], 2));
                                    } else {
                                        p($fmt ? $fmt->currency((float)($budgetInfo['remaining_budget'] ?? (($project->getTotalBudget() ?? 0) - $budgetConsumption))) : $currencyCode . ' ' . number_format((float)($budgetInfo['remaining_budget'] ?? (($project->getTotalBudget() ?? 0) - $budgetConsumption)), 2));
                                    }
                                    ?>
                                </span>
                            </div>
                            <div class="breakdown-item breakdown-item--capacity">
                                <span class="breakdown-label"><?php p($l->t('Hours (estimate)')); ?></span>
                                <span class="breakdown-value breakdown-value--block">
                                    <?php include __DIR__ . '/parts/capacity-hours-display.php'; ?>
                                </span>
                            </div>
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
                                    <span><?php p($project->getStartDate() ? $project->getStartDate()->format('d.m.Y') : $l->t('Not set')); ?></span>
                                </div>
                            </div>
                            <div class="timeline-item">
                                <div class="timeline-icon">
                                    <i class="icon-calendar-custom"></i>
                                </div>
                                <div class="timeline-content">
                                    <label><?php p($l->t('End Date')); ?></label>
                                    <span><?php p($project->getEndDate() ? $project->getEndDate()->format('d.m.Y') : $l->t('Not set')); ?></span>
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
                                                p($l->t('%d days', [$diff->days]));
                                            } else {
                                                p($l->t('Not set'));
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
                                <div class="timeline-content timeline-content--capacity">
                                    <label><?php p($l->t('Hours')); ?></label>
                                    <?php include __DIR__ . '/parts/capacity-hours-display.php'; ?>
                                </div>
                            </div>
                            <?php
                            $capacityRate = (float) ($project->getHourlyRate() ?? 0);
                            $showRateRow = $capacityRate > 0 || ($costRateMode ?? '') === \OCA\ProjectCheck\Util\CostRateMode::PROJECT;
                            ?>
                            <?php if ($showRateRow): ?>
                            <div class="timeline-item">
                                <div class="timeline-icon">
                                    <i class="icon-money-custom"></i>
                                </div>
                                <div class="timeline-content">
                                    <label>
                                        <?php
                                        if (($costRateMode ?? '') === \OCA\ProjectCheck\Util\CostRateMode::PROJECT) {
                                            p($l->t('Project hourly rate'));
                                        } else {
                                            p($l->t('Planning hourly rate (estimate)'));
                                        }
                                        ?>
                                    </label>
                                    <span><?php p($fmt ? $fmt->currency($capacityRate) : $currencyCode . ' ' . number_format($capacityRate, 2)); ?><?php p($l->t('/hour')); ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Project Files -->
        <?php
        $hasProjectFiles = !empty($projectFiles);
        $fileLimitBytes = \OCA\ProjectCheck\Service\ProjectFileService::MAX_FILE_SIZE_BYTES;
        $fileLimitLabel = Util::humanFileSize($fileLimitBytes);
        $maxFiles = \OCA\ProjectCheck\Service\ProjectFileService::MAX_FILES_PER_UPLOAD;
        $filesHeadingId = 'pc-files-heading';
        ?>
        <div class="section pc-section pc-files-section" id="files-section" aria-labelledby="<?php p($filesHeadingId); ?>">
            <div class="section-header pc-section__header">
                <div class="pc-section__title-wrap">
                    <h3 id="<?php p($filesHeadingId); ?>" class="pc-section-title">
                        <i class="icon-project-files" aria-hidden="true"></i>
                        <?php p($l->t('Project Files')); ?>
                    </h3>
                    <p class="pc-section-intro">
                        <?php p($l->t('Keep contracts, briefs, and other documents together with the project.')); ?>
                    </p>
                </div>
                <?php if ($canManageFiles && $hasProjectFiles): ?>
                    <div class="section-header-actions">
                        <label class="button primary pc-section__primary-action" for="project_files_upload">
                            <span data-lucide="upload-cloud" class="lucide-icon" aria-hidden="true"></span>
                            <span class="pc-section__primary-action-label"><?php p($l->t('Add files')); ?></span>
                        </label>
                    </div>
                <?php endif; ?>
            </div>
            <div class="section-content pc-files-section__content">
                <?php if ($canManageFiles): ?>
                    <form id="project-file-upload-form"
                        class="pc-file-upload-form"
                        action="<?php p($urlGenerator->linkToRoute('projectcheck.projectfile.upload', ['projectId' => $projectId])); ?>"
                        method="POST"
                        enctype="multipart/form-data">
                        <input type="hidden" name="requesttoken" value="<?php p($_['requesttoken']); ?>">
                        <label class="pc-sr-only" for="project_files_upload"><?php p($l->t('Choose project files to upload')); ?></label>
                        <input type="file"
                            id="project_files_upload"
                            name="project_files[]"
                            aria-describedby="pc-files-upload-hint"
                            class="pc-file-input"
                            multiple
                            required>
                    </form>
                <?php endif; ?>

                <?php if ($hasProjectFiles): ?>
                    <?php if ($canManageFiles): ?>
                        <div class="pc-file-dropzone pc-file-dropzone--compact"
                            id="project-files-dropzone"
                            tabindex="0"
                            role="button"
                            aria-controls="project_files_upload"
                            aria-describedby="pc-files-upload-hint"
                            aria-label="<?php p($l->t('Add more files: drag and drop here, or press Enter to choose files.')); ?>">
                            <span data-lucide="upload-cloud" class="lucide-icon pc-file-dropzone__icon" aria-hidden="true"></span>
                            <span class="pc-file-dropzone__text">
                                <strong class="pc-file-dropzone__title"><?php p($l->t('Drop files to upload')); ?></strong>
                                <span class="pc-file-dropzone__hint" id="pc-files-upload-hint">
                                    <?php p($l->t('Up to %1$d files, %2$s each.', [$maxFiles, $fileLimitLabel])); ?>
                                </span>
                            </span>
                        </div>
                    <?php endif; ?>
                    <ul class="project-files-list" aria-label="<?php p($l->t('Project files')); ?>">
                        <?php foreach ($projectFiles as $file): ?>
                            <li class="project-file-row" data-file-id="<?php p($file->getId()); ?>">
                                <div class="file-info">
                                    <i class="icon-file-custom" aria-hidden="true"></i>
                                    <div class="file-meta">
                                        <a class="file-name"
                                            href="<?php p($urlGenerator->linkToRoute('projectcheck.projectfile.download', ['projectId' => $projectId, 'fileId' => $file->getId()])); ?>"
                                            target="_blank" rel="noreferrer noopener">
                                            <?php p($file->getDisplayName()); ?>
                                        </a>
                                        <div class="file-details">
                                            <span><?php p($l->t('Uploaded by %s', [$file->getUploadedBy()])); ?></span>
                                            <span aria-hidden="true">•</span>
                                            <span><?php p($file->getCreatedAt() ? $file->getCreatedAt()->format('d.m.Y H:i') : ''); ?></span>
                                            <span aria-hidden="true">•</span>
                                            <span><?php p(Util::humanFileSize($file->getSize())); ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div class="file-actions">
                                    <a class="button secondary"
                                        href="<?php p($urlGenerator->linkToRoute('projectcheck.projectfile.download', ['projectId' => $projectId, 'fileId' => $file->getId()])); ?>"
                                        target="_blank" rel="noreferrer noopener"
                                        aria-label="<?php p($l->t('Download %s', [$file->getDisplayName()])); ?>">
                                        <i class="icon icon-download" aria-hidden="true"></i>
                                        <span><?php p($l->t('Download')); ?></span>
                                    </a>
                                    <?php if ($canManageFiles): ?>
                                        <button type="button"
                                            class="button danger ghost delete-file-btn"
                                            data-delete-url="<?php p($urlGenerator->linkToRoute('projectcheck.projectfile.deletePost', ['projectId' => $projectId, 'fileId' => $file->getId()])); ?>"
                                            data-file-name="<?php p($file->getDisplayName()); ?>"
                                            aria-label="<?php p($l->t('Delete %s', [$file->getDisplayName()])); ?>">
                                            <i class="icon icon-delete" aria-hidden="true"></i>
                                            <span><?php p($l->t('Delete')); ?></span>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php elseif ($canManageFiles): ?>
                    <div class="pc-file-dropzone pc-file-dropzone--empty"
                        id="project-files-dropzone"
                        tabindex="0"
                        role="button"
                        aria-controls="project_files_upload"
                        aria-describedby="pc-files-upload-hint"
                        aria-label="<?php p($l->t('Upload project files: drag and drop here, or press Enter to choose files.')); ?>">
                        <div class="pc-file-dropzone__icon-wrap" aria-hidden="true">
                            <span data-lucide="upload-cloud" class="lucide-icon pc-file-dropzone__icon"></span>
                        </div>
                        <p class="pc-file-dropzone__title"><?php p($l->t('Drop files here to upload')); ?></p>
                        <p class="pc-file-dropzone__lead">
                            <?php p($l->t('Add contracts, briefs, or other documents — drag and drop, or press Enter to browse.')); ?>
                        </p>
                        <p class="pc-file-dropzone__hint" id="pc-files-upload-hint">
                            <?php p($l->t('Up to %1$d files per upload, %2$s each. Executable file types are blocked.', [$maxFiles, $fileLimitLabel])); ?>
                        </p>
                    </div>
                <?php else: ?>
                    <?php
                    $iconLucide = 'folder';
                    $title = $l->t('No files uploaded yet');
                    $description = $l->t('Once a project manager adds files, they will appear here.');
                    include __DIR__ . '/parts/pc-empty-state.php';
                    unset($iconLucide, $title, $description);
                    ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Time Entries -->
        <?php if (!empty($timeEntries)): ?>
            <div class="section" id="time-entries-section">
                <div class="section-header">
                    <h3><i class="icon-time-custom" aria-hidden="true"></i> <?php p($l->t('Recent Time Entries')); ?></h3>
                    <div class="section-header-actions">
                        <?php if ($canAddTimeEntry): ?>
                            <?php if ($usingAdminTimeEntryOverride): ?>
                                <span class="pc-admin-override-badge" title="<?php p($l->t('You can log time on this project because of your administrator role, not because you are on the team.')); ?>">
                                    <span data-lucide="shield-check" class="lucide-icon" aria-hidden="true"></span>
                                    <span class="pc-admin-override-badge__text"><?php p($l->t('Admin override')); ?></span>
                                </span>
                            <?php endif; ?>
                            <a href="<?php p($urlGenerator->linkToRoute('projectcheck.timeentry.create', ['project_id' => $projectId])); ?>" class="button primary">
                                <span data-lucide="plus" class="lucide-icon" aria-hidden="true"></span>
                                <?php p($l->t('Add time entry')); ?>
                            </a>
                        <?php endif; ?>
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
                                        <span class="entry-cost"><?php p($fmt ? $fmt->currency((float)($entry->getHours() * $entry->getHourlyRate())) : $currencyCode . ' ' . number_format((float)($entry->getHours() * $entry->getHourlyRate()), 2)); ?></span>
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
            <div class="section" id="time-entries-section">
                <div class="section-header">
                    <h3><i class="icon-time-custom" aria-hidden="true"></i> <?php p($l->t('Time Entries')); ?></h3>
                </div>
                <div class="section-content">
                    <?php
                    $iconLucide = 'clock';
                    $title = $l->t('No time entries yet');
                    $description = $l->t('Start tracking time for this project by adding your first time entry.');
                    if ($canAddTimeEntry) {
                        $ctaHref = $urlGenerator->linkToRoute('projectcheck.timeentry.create', ['project_id' => $projectId]);
                        $ctaLabel = $l->t('Add first time entry');
                        if ($usingAdminTimeEntryOverride) {
                            $description = $l->t('Start tracking time. You can log your own time here because of your administrator role, even though you are not on the team.');
                        }
                    } else {
                        $hint = $l->t('Time tracking is not available for this project status. Change status to Active or On Hold to add entries.');
                    }
                    include __DIR__ . '/parts/pc-empty-state.php';
                    unset($iconLucide, $title, $description, $ctaHref, $ctaLabel, $hint, $ctaTag, $ctaFor, $ctaIconLucide);
                    ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Team Members -->
        <?php
        $teamMembersActive = $_['teamMembersActive'] ?? [];
        $teamMembersFormer = $_['teamMembersFormer'] ?? [];
        $hasAnyTeam = !empty($teamMembersActive) || !empty($teamMembersFormer);
        $showTeamSection = $hasAnyTeam || (!empty($canAddTeamMember) && $canAddTeamMember);
        ?>
        <?php if ($showTeamSection): ?>
            <div class="section pc-section" id="team-section">
                <div class="section-header pc-section__header">
                    <h3 class="pc-section-title"><i class="icon-user-custom" aria-hidden="true"></i> <?php p($l->t('Team Members')); ?></h3>
                    <p class="pc-section-intro"><?php p($l->t('People who can log time on this project. Rates depend on the pricing method above.')); ?></p>
                    <div class="section-header-actions">
                        <?php if (!empty($canAddTeamMember) && $canAddTeamMember): ?>
                            <button type="button" class="button primary" id="add-team-member-btn" aria-haspopup="dialog" aria-controls="addTeamMemberModal" aria-expanded="false">
                                <span data-lucide="plus" class="lucide-icon" aria-hidden="true"></span>
                                <?php p($l->t('Add team member')); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="section-content">
                    <?php if (!empty($teamMembersActive)): ?>
                    <div class="team-members-list" role="list" aria-label="<?php p($l->t('Current team')); ?>">
                        <?php foreach ($teamMembersActive as $member): ?>
                            <div class="team-member-item" role="listitem">
                                <div class="member-avatar">
                                    <div class="avatar-initial" aria-hidden="true"><?php p(strtoupper(substr($member['name'] ?? 'U', 0, 1))); ?></div>
                                </div>
                                <div class="member-info">
                                    <span class="member-name"><?php p($member['name'] ?? $l->t('Unknown')); ?></span>
                                </div>
                                <div class="member-hours">
                                    <span class="hours-label"><?php p($l->t('Hours:')); ?></span>
                                    <span class="hours-value"><?php p($member['hours'] ?? 0); ?></span>
                                </div>
                                <?php if (($costRateMode ?? '') === 'project_member' && !empty($canManageMembers)): ?>
                                <div class="member-rate pc-member-rate" role="group" aria-labelledby="member-rate-label-<?php p((string)($member['user_id'] ?? '')); ?>">
                                    <span class="pc-member-rate__label" id="member-rate-label-<?php p((string)($member['user_id'] ?? '')); ?>"><?php p($l->t('Hourly rate')); ?></span>
                                    <?php if (isset($member['current_rate']) && $member['current_rate'] !== null): ?>
                                        <span class="pc-member-rate-current"><?php p($fmt ? $fmt->currency((float)$member['current_rate']) : $currencyCode . ' ' . number_format((float)$member['current_rate'], 2)); ?> <?php p($l->t('(today)')); ?></span>
                                    <?php else: ?>
                                        <span class="pc-member-rate-current pc-member-rate-current--missing"><?php p($l->t('No rate yet')); ?></span>
                                    <?php endif; ?>
                                    <div class="pc-member-rate__form">
                                        <label class="pc-sr-only" for="member-rate-<?php p((string)($member['user_id'] ?? '')); ?>"><?php p($l->t('New hourly rate')); ?></label>
                                        <input type="number" class="form-input pc-member-rate-input" id="member-rate-<?php p((string)($member['user_id'] ?? '')); ?>" min="0.01" step="0.01" inputmode="decimal" placeholder="<?php p($l->t('New rate')); ?>" aria-describedby="member-rate-hint-<?php p((string)($member['user_id'] ?? '')); ?>">
                                        <label class="pc-sr-only" for="member-rate-date-<?php p((string)($member['user_id'] ?? '')); ?>"><?php p($l->t('Effective from')); ?></label>
                                        <input type="date" class="form-input pc-member-rate-date" id="member-rate-date-<?php p((string)($member['user_id'] ?? '')); ?>" lang="<?php p($htmlLang); ?>" max="<?php p(gmdate('Y-m-d')); ?>" value="<?php p(gmdate('Y-m-d')); ?>" aria-describedby="member-rate-hint-<?php p((string)($member['user_id'] ?? '')); ?>">
                                        <button type="button" class="button secondary pc-member-rate-save" data-update-url="<?php p((string)($member['update_rate_url'] ?? '')); ?>"><?php p($l->t('Save rate')); ?></button>
                                    </div>
                                    <p class="form-hint" id="member-rate-hint-<?php p((string)($member['user_id'] ?? '')); ?>"><?php p($l->t('Past time entries keep their previous rate.')); ?></p>
                                    <p class="pc-member-rate-error" role="status" aria-live="polite"></p>
                                </div>
                                <?php endif; ?>
                                <div class="member-actions">
                                    <?php if (!empty($canViewMemberProfiles) && !empty($member['profile_url'])): ?>
                                        <a class="action-btn member-profile-btn"
                                            href="<?php p((string)$member['profile_url']); ?>"
                                            title="<?php p($l->t('Open profile of %s', [$member['name'] ?? $l->t('Unknown')])); ?>"
                                            aria-label="<?php p($l->t('Open profile of %s', [$member['name'] ?? $l->t('Unknown')])); ?>">
                                            <i class="icon-user-custom" aria-hidden="true"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($canViewMemberTimeEntries) && !empty($member['user_id'])): ?>
                                        <a class="action-btn member-timeentries-btn"
                                            href="<?php p($urlGenerator->linkToRoute('projectcheck.timeentry.index', ['project_id' => $projectId, 'user_id' => (string)($member['user_id'] ?? '')])); ?>"
                                            title="<?php p($l->t('View time entries for %s', [$member['name'] ?? $l->t('Unknown')])); ?>"
                                            aria-label="<?php p($l->t('View time entries for %s', [$member['name'] ?? $l->t('Unknown')])); ?>">
                                            <i class="icon-time-custom" aria-hidden="true"></i>
                                        </a>
                                    <?php endif; ?>
                                    <?php if (!empty($canManageMembers) && $canManageMembers && empty($member['is_former'])): ?>
                                        <button type="button" class="action-btn remove-member-btn"
                                            data-member-id="<?php p($member['id'] ?? ''); ?>"
                                            data-user-id="<?php p($member['user_id'] ?? ''); ?>"
                                            data-delete-url="<?php p($urlGenerator->linkToRoute('projectcheck.project.removeTeamMemberPost', ['id' => $projectId, 'userId' => (string)($member['user_id'] ?? '')])); ?>"
                                            data-impact-url="<?php p($urlGenerator->linkToRoute('projectcheck.projectmember.getDeletionImpact', ['id' => (int)($member['id'] ?? 0)])); ?>"
                                            data-member-name="<?php p($member['name'] ?? $l->t('Unknown')); ?>"
                                            title="<?php p($l->t('Remove from project')); ?>"
                                            aria-label="<?php p($l->t('Remove %s from project', [$member['name'] ?? $l->t('Unknown')])); ?>">
                                            <i class="icon-delete-custom" aria-hidden="true"></i>
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($teamMembersFormer)): ?>
                    <div class="team-former-section" role="region" aria-labelledby="team-former-heading">
                        <h4 class="team-former-heading" id="team-former-heading"><?php p($l->t('Former team members (account removed)')); ?></h4>
                        <p class="team-former-help" id="team-former-desc"><?php p($l->t('Historical list — time entries and roles are kept. These accounts no longer sign in to Nextcloud.')); ?></p>
                    <div class="team-members-list team-members-list--former" role="list" aria-describedby="team-former-desc" aria-label="<?php p($l->t('Former team members')); ?>">
                        <?php foreach ($teamMembersFormer as $member): ?>
                            <div class="team-member-item team-member-item--former" role="listitem">
                                <div class="member-avatar">
                                    <div class="avatar-initial" aria-hidden="true"><?php p(strtoupper(substr($member['name'] ?? 'U', 0, 1))); ?></div>
                                </div>
                                <div class="member-info">
                                    <span class="member-name"><?php p($member['name'] ?? $l->t('Unknown')); ?>
                                        <span class="pc-badge pc-badge--neutral"><?php p($l->t('Former')); ?></span>
                                    </span>
                                </div>
                                <div class="member-hours">
                                    <span class="hours-label"><?php p($l->t('Hours:')); ?></span>
                                    <span class="hours-value"><?php p($member['hours'] ?? 0); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    </div>
                    <?php endif; ?>
                    <?php if (empty($teamMembersActive) && empty($teamMembersFormer)): ?>
                    <?php
                    $iconLucide = 'users';
                    $title = $l->t('No team members yet');
                    $description = $l->t('Add the first person to this project to start collaboration and time tracking.');
                    $ariaLive = 'polite';
                    if (!empty($canAddTeamMember) && $canAddTeamMember) {
                        $ctaTag = 'button';
                        $ctaLabel = $l->t('Add first team member');
                        $ctaIconLucide = 'plus';
                        $ctaAriaControls = 'addTeamMemberModal';
                        $ctaAriaHasPopup = 'dialog';
                        $ctaData = ['action' => 'open-add-team-member'];
                    }
                    include __DIR__ . '/parts/pc-empty-state.php';
                    unset($iconLucide, $title, $description, $ariaLive, $role, $ctaHref, $ctaLabel, $hint, $ctaTag, $ctaFor, $ctaIconLucide, $ctaAriaControls, $ctaAriaHasPopup, $ctaData);
                    ?>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
<?php include __DIR__ . '/common/page-end.php'; ?>

<?php
    $hasStatusModal = !empty($canChangeStatus) && $canChangeStatus && $allowedStatusTargets !== [];
?>
<?php if ($hasStatusModal): ?>
<div id="statusChangeModal" class="modal projectcheck-dialog" style="display: none;" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="statusChangeModalTitle">
    <div class="modal-content projectcheck-dialog__panel" onclick="event.stopPropagation();">
        <div class="modal-header projectcheck-dialog__header">
            <h3 id="statusChangeModalTitle"><?php p($l->t('Change project status')); ?></h3>
            <button type="button" class="close" id="close-status-modal" aria-label="<?php p($l->t('Close')); ?>"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body projectcheck-dialog__body">
            <p class="projectcheck-dialog__help" id="statusChangeHelp"><?php p($l->t('Choose the next state. Archived projects are hidden from your default list and do not accept new time until reactivated.')); ?></p>
            <form id="statusChangeForm">
                <div class="form-group">
                    <label for="newStatus"><?php p($l->t('New status')); ?></label>
                    <select id="newStatus" name="status" required aria-describedby="statusChangeHelp">
                        <?php foreach ($allowedStatusTargets as $target): ?>
                            <option value="<?php p($target); ?>"><?php p($l->t($target)); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="statusReason"><?php p($l->t('Note (optional)')); ?></label>
                    <textarea id="statusReason" name="reason" rows="3" maxlength="2000" autocomplete="off"></textarea>
                </div>
            </form>
        </div>
        <div class="modal-footer projectcheck-dialog__footer">
            <button type="button" class="button secondary" id="cancel-status-change"><?php p($l->t('Cancel')); ?></button>
            <button type="button" class="button primary" id="submit-status-change" data-default-label="<?php p($l->t('Update status')); ?>" data-busy-label="<?php p($l->t('Updating status…')); ?>"><?php p($l->t('Update status')); ?></button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
    $addTeamUrl = $addTeamMemberUrl !== null
        ? $addTeamMemberUrl
        : $urlGenerator->linkToRoute('projectcheck.project.addTeamMember', ['id' => $projectId]);
    $addAllTeamUrl = $urlGenerator->linkToRoute('projectcheck.project.addAllTeamMembers', ['id' => $projectId]);
    $requiresMemberRate = ($costRateMode ?? '') === \OCA\ProjectCheck\Util\CostRateMode::PROJECT_MEMBER;
?>
<?php if (!empty($canAddTeamMember) && $canAddTeamMember): ?>
<div id="addTeamMemberModal" class="modal projectcheck-dialog<?php echo $requiresMemberRate ? ' projectcheck-dialog--member-rate' : ''; ?>" style="display: none;" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="addTeamMemberTitle" aria-describedby="addMemberHelp addMemberSteps<?php echo $requiresMemberRate ? ' teamMemberRateHint' : ''; ?>">
    <div class="modal-content projectcheck-dialog__panel projectcheck-dialog__panel--member">
        <div class="modal-header projectcheck-dialog__header">
            <div class="projectcheck-dialog__title-group">
                <span class="projectcheck-dialog__eyebrow"><?php p($l->t('Project team')); ?></span>
                <h3 id="addTeamMemberTitle"><?php p($l->t('Add team member')); ?></h3>
            </div>
            <button type="button" class="close" id="close-add-member-modal" aria-label="<?php p($l->t('Close')); ?>"><span aria-hidden="true">&times;</span></button>
        </div>
        <div class="modal-body projectcheck-dialog__body">
            <div class="projectcheck-dialog__intro">
                <p class="projectcheck-dialog__help" id="addMemberHelp"><?php
                    if ($requiresMemberRate) {
                        p($l->t('This project uses a separate hourly rate for each team member. Search for a person, then enter their rate before adding them.'));
                    } else {
                        p($l->t('Search the Nextcloud user directory and choose exactly one active account. Existing project members are hidden.'));
                    }
                ?></p>
                <ol class="projectcheck-dialog__steps" id="addMemberSteps">
                    <li><?php p($l->t('Search')); ?></li>
                    <li><?php p($l->t('Select')); ?></li>
                    <?php if ($requiresMemberRate) { ?>
                    <li><?php p($l->t('Set rate')); ?></li>
                    <?php } ?>
                    <li><?php p($l->t('Add')); ?></li>
                </ol>
            </div>
            <form id="addTeamMemberForm" method="post" class="team-user-picker" autocomplete="off" novalidate>
                <div class="form-group team-user-picker__search-card">
                    <label for="teamMemberSearch" class="team-user-picker__label"><?php p($l->t('Find a person')); ?> <span class="required-star" aria-hidden="true">*</span></label>
                    <input type="text" id="teamMemberSearch" required autocomplete="off" inputmode="search" aria-describedby="addMemberHelp teamMemberHint add-team-member-error" aria-errormessage="add-team-member-error" class="form-input team-user-picker__input" placeholder="<?php p($l->t('Type at least 2 characters…')); ?>" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="teamMemberSearchResults" />
                    <input type="hidden" id="teamMemberUserId" name="user_id" />
                    <p class="form-hint" id="teamMemberHint"><?php p($l->t('Start typing, then select one person from the list.')); ?></p>
                    <div id="teamMemberSearchResults" class="team-user-search-results projectcheck-entity-picker__suggest" aria-label="<?php p($l->t('Matching users')); ?>" aria-live="polite" hidden></div>
                    <div id="teamMemberSelected" class="team-user-selected" hidden>
                        <span class="team-user-selected__label"><?php p($l->t('Selected:')); ?></span>
                        <span id="teamMemberSelectedText" class="team-user-selected__text"></span>
                        <button type="button" id="teamMemberSelectedClear" class="team-user-selected__clear"><?php p($l->t('Change')); ?></button>
                    </div>
                </div>
                <?php if ($requiresMemberRate) { ?>
                <fieldset class="team-member-rate-card" id="teamMemberRateGroup">
                    <legend class="team-member-rate-card__legend"><?php p($l->t('Hourly rate for this person')); ?></legend>
                    <p class="form-hint team-member-rate-card__intro" id="teamMemberRateHint">
                        <?php p($l->t('This rate applies to all time they log on this project from today. You can set a new rate with an effective date later.')); ?>
                    </p>
                    <div class="form-group team-member-rate-card__field">
                        <label for="teamMemberHourlyRate">
                            <?php p($l->t('Hourly rate (%s)', [$currencyCode])); ?>
                            <span class="required-star" aria-hidden="true">*</span>
                        </label>
                        <div class="team-member-rate-card__input-row">
                            <input type="number" id="teamMemberHourlyRate" name="hourly_rate" class="form-input team-member-rate-card__input"
                                min="0.01" step="0.01" inputmode="decimal" required
                                autocomplete="off"
                                aria-describedby="teamMemberRateHint add-team-member-error"
                                aria-errormessage="add-team-member-error"
                                placeholder="0.00">
                            <span class="team-member-rate-card__suffix" aria-hidden="true"><?php p($l->t('/hour')); ?></span>
                        </div>
                    </div>
                </fieldset>
                <?php } ?>
                <p id="add-team-member-error" class="projectcheck-dialog__error" role="status" aria-live="polite"></p>
            </form>
        </div>
        <div class="modal-footer projectcheck-dialog__footer">
            <button type="button" class="button secondary" id="cancel-add-member"><?php p($l->t('Cancel')); ?></button>
            <?php if (empty($hideAddAllTeam)): ?>
            <button type="button" class="button secondary" id="submit-add-all-team-members" data-default-label="<?php p($l->t('Add all users')); ?>" data-busy-label="<?php p($l->t('Adding all users…')); ?>"><?php p($l->t('Add all users')); ?></button>
            <?php endif; ?>
            <button type="button" class="button primary" id="submit-add-team-member" disabled title="<?php p($l->t('Select one user first')); ?>" data-default-label="<?php p($l->t('Add to project')); ?>" data-busy-label="<?php p($l->t('Adding…')); ?>"><?php p($l->t('Add to project')); ?></button>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
    if (!isset($addTeamUrl)) {
        $addTeamUrl = $addTeamMemberUrl !== null
            ? $addTeamMemberUrl
            : $urlGenerator->linkToRoute('projectcheck.project.addTeamMember', ['id' => $projectId]);
        $addAllTeamUrl = $urlGenerator->linkToRoute('projectcheck.project.addAllTeamMembers', ['id' => $projectId]);
    }
    $projectDetailConfig = [
        'bulkAddSuccess' => $bulkAddSuccess,
        'requiresMemberRate' => $requiresMemberRate,
        'requestToken' => $_['requesttoken'] ?? '',
        'urls' => [
            'changeStatusPost' => $urlGenerator->linkToRoute('projectcheck.project.changeStatusPost', ['id' => $projectId]),
            'addTeam' => $addTeamUrl,
            'addAllTeam' => $addAllTeamUrl,
            'searchUsers' => $urlGenerator->linkToRoute('projectcheck.project.searchAssignableUsers', ['id' => $projectId]),
        ],
        'messages' => [
            'errorStatus' => $l->t('Error updating status'),
            'selectStatus' => $l->t('Please select a status.'),
            'errorGeneric' => $l->t('Something went wrong. Please try again.'),
            'addMemberError' => $l->t('Could not add team member'),
            'noUsersFound' => $l->t('No matching users found'),
            'chooseUser' => $l->t('Choose a user from the list'),
            'loadingUsers' => $l->t('Searching users…'),
            'enterMoreChars' => $l->t('Type at least 2 characters to search.'),
            'searchUsersError' => $l->t('User search failed. Check your connection and try again.'),
            'addAllMembers' => $l->t('Could not add all users to the project'),
            'memberRateRequired' => $l->t('Enter an hourly rate for this person before adding them to the project.'),
            'removeConfirm' => $l->t('Are you sure you want to remove this team member?'),
            'removeSuccess' => $l->t('Team member removed successfully'),
            'removeFailed' => $l->t('Failed to remove team member'),
            'removeError' => $l->t('Error removing team member. Please try again.'),
        ],
    ];
    $projectDetailFilesConfig = [
        'limits' => [
            'maxFiles' => \OCA\ProjectCheck\Service\ProjectFileService::MAX_FILES_PER_UPLOAD,
            'maxBytes' => \OCA\ProjectCheck\Service\ProjectFileService::MAX_FILE_SIZE_BYTES,
        ],
        'messages' => [
            'deleteConfirm' => $l->t('Delete this file?'),
            'deleteFailed' => $l->t('Could not delete the file. Please try again.'),
            'tooManyFiles' => $l->t('You can upload up to %d files at once.', [\OCA\ProjectCheck\Service\ProjectFileService::MAX_FILES_PER_UPLOAD]),
            'fileTooLarge' => $l->t('One or more files exceed the %s per-file limit.', [Util::humanFileSize(\OCA\ProjectCheck\Service\ProjectFileService::MAX_FILE_SIZE_BYTES)]),
            'noFiles' => $l->t('No files selected.'),
            'uploadFailed' => $l->t('Upload failed. Please try again.'),
            'uploading' => $l->t('Uploading files…'),
            'uploadStart' => $l->t('Upload started. Please wait…'),
            'inProgress' => $l->t('An upload is already in progress.'),
        ],
    ];
    $projectDetailRatesConfig = [
        'enabled' => $costRateMode === 'project_member',
        'messages' => [
            'ratePositive' => $l->t('Hourly rate must be a positive number'),
            'dateRequired' => $l->t('Effective-from date is required'),
            'saveFailed' => $l->t('Could not save rate'),
            'rateSaved' => $l->t('Rate saved. Past time entries keep their previous rate.'),
        ],
    ];
?>
<script nonce="<?php p($_['cspNonce'] ?? ''); ?>">
window.projectDetailConfig = <?php echo json_encode($projectDetailConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR); ?>;
window.projectDetailFilesConfig = <?php echo json_encode($projectDetailFilesConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR); ?>;
window.projectDetailRatesConfig = <?php echo json_encode($projectDetailRatesConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR); ?>;
</script>

