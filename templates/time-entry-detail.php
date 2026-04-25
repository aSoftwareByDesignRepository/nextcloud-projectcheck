<?php

/**
 * Time entry detail template for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

use OCP\Util;

Util::addScript('projectcheck', 'time-entry-detail');
Util::addStyle('projectcheck', 'time-entries');
Util::addStyle('projectcheck', 'projects');
Util::addStyle('projectcheck', 'custom-icons');
Util::addStyle('projectcheck', 'navigation');

if (!isset($timeEntry) || !($timeEntry instanceof \OCA\ProjectCheck\Db\TimeEntry)) {
    throw new Exception('Time entry not found');
}

$timeEntryId = $timeEntry->getId();
$totalCost = $timeEntry->getCost() ?? ($timeEntry->getHours() * $timeEntry->getHourlyRate());

$project = $project ?? null;
$projectStatus = ($project && $project instanceof \OCA\ProjectCheck\Db\Project) ? (string) $project->getStatus() : '';
$statusClassByProject = [
	'Active' => 'status-active',
	'On Hold' => 'status-on-hold',
	'Completed' => 'status-completed',
	'Cancelled' => 'status-cancelled',
	'Archived' => 'status-archived',
];
$projectStatusClass = $statusClassByProject[$projectStatus] ?? 'status-on-hold';
$projectStatusLabel = $projectStatus !== ''
	? $l->t($projectStatus)
	: $l->t('Unknown');
$projectLinkHref = isset($projectShowUrl) ? (string) $projectShowUrl : (string) $urlGenerator->linkToRoute('projectcheck.project.show', ['id' => $timeEntry->getProjectId()]);
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<div id="app-content" role="main">
    <div id="app-content-wrapper">
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb-container">
            <nav class="breadcrumb" aria-label="<?php p($l->t('Breadcrumb')); ?>">
                <ol>
                    <li><a href="<?php p($urlGenerator->linkToRoute('projectcheck.timeentry.index')); ?>"><?php p($l->t('Time Entries')); ?></a></li>
                    <li aria-current="page"><?php p($l->t('Time Entry Details')); ?></li>
                </ol>
            </nav>
        </div>

        <!-- Page Header -->
        <div class="section page-header-section">
            <div class="header-content">
                <div class="header-text">
                    <div class="header-details">
                        <h2><?php p($l->t('Time Entry Details')); ?></h2>
                        <p class="header-subtitle"><?php p($l->t('Time entry information and associated details')); ?></p>
                        <div class="project-meta">
                            <div class="meta-item">
                                <i class="icon-calendar-custom"></i>
                                <span><?php p($timeEntry->getDate() ? $timeEntry->getDate()->format('d.m.Y') : 'Unknown'); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="icon-time-custom"></i>
                                <span><?php p($timeEntry->getHours()); ?> <?php p($l->t('hours')); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="icon-money-custom"></i>
                                <span>€<?php p(number_format($totalCost, 2)); ?></span>
                            </div>
                            <div class="meta-item">
                                <i class="icon-user-custom"></i>
                                <span><?php p($timeEntry->getUserId() === $userId ? $l->t('Your time entry') : $l->t('Time entry by %s', [$timeEntry->getUserId()])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="header-actions">
                    <?php if ($timeEntry->getUserId() === $userId): ?>
                        <a href="<?php p($urlGenerator->linkToRoute('projectcheck.timeentry.edit', ['id' => $timeEntryId])); ?>" class="button secondary">
                            <i class="icon-edit-custom"></i>
                            <?php p($l->t('Edit Time Entry')); ?>
                        </a>
                    <?php endif; ?>
                    <a href="<?php p($urlGenerator->linkToRoute('projectcheck.timeentry.index')); ?>" class="button secondary">
                        <i class="icon-time-custom"></i>
                        <?php p($l->t('Back to List')); ?>
                    </a>
                </div>
            </div>
        </div>

        <!-- Time Entry Statistics -->
        <div class="section stats-section">
            <div class="stats-container">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="icon-time-custom icon-large"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php p($timeEntry->getHours()); ?>h</div>
                        <div class="stat-label"><?php p($l->t('HOURS')); ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="icon-money-custom icon-large"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number">€<?php p(number_format($timeEntry->getHourlyRate(), 2)); ?></div>
                        <div class="stat-label"><?php p($l->t('HOURLY RATE')); ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="icon-money-custom icon-large"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number">€<?php p(number_format($totalCost, 2)); ?></div>
                        <div class="stat-label"><?php p($l->t('TOTAL COST')); ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="icon-calendar-custom icon-large"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php p($timeEntry->getDate() ? $timeEntry->getDate()->format('d.m.Y') : 'N/A'); ?></div>
                        <div class="stat-label"><?php p($l->t('DATE')); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Time Entry Information -->
            <div class="info-section">
                <div class="section-header">
                    <h3><i class="icon-info-custom"></i> <?php p($l->t('Time Entry Information')); ?></h3>
                </div>
                <div class="section-content">
                    <div class="info-grid">
                        <div class="info-item">
                            <label><?php p($l->t('PROJECT')); ?></label>
                            <span>
                                <a href="<?php p($projectLinkHref); ?>" class="project-link">
                                    <?php p($projectName); ?>
                                </a>
                            </span>
                        </div>
                        <div class="info-item">
                            <label><?php p($l->t('DATE')); ?></label>
                            <span><?php p($timeEntry->getDate() ? $timeEntry->getDate()->format('d.m.Y') : 'Not set'); ?></span>
                        </div>
                        <div class="info-item">
                            <label><?php p($l->t('HOURS')); ?></label>
                            <span><?php p($timeEntry->getHours()); ?> <?php p($l->t('hours')); ?></span>
                        </div>
                        <div class="info-item">
                            <label><?php p($l->t('HOURLY RATE')); ?></label>
                            <span>€<?php p(number_format($timeEntry->getHourlyRate(), 2)); ?><?php p($l->t('/hour')); ?></span>
                        </div>
                        <div class="info-item">
                            <label><?php p($l->t('TOTAL COST')); ?></label>
                            <span class="total-cost">€<?php p(number_format($totalCost, 2)); ?></span>
                        </div>
                        <div class="info-item">
                            <label><?php p($l->t('CREATED BY')); ?></label>
                            <span><?php p($timeEntry->getUserId()); ?></span>
                        </div>
                        <div class="info-item">
                            <label><?php p($l->t('CREATED')); ?></label>
                            <span><?php p($timeEntry->getCreatedAt() ? $timeEntry->getCreatedAt()->format('d.m.Y H:i') : 'Unknown'); ?></span>
                        </div>
                        <div class="info-item">
                            <label><?php p($l->t('LAST UPDATED')); ?></label>
                            <span><?php p($timeEntry->getUpdatedAt() ? $timeEntry->getUpdatedAt()->format('d.m.Y H:i') : 'Unknown'); ?></span>
                        </div>
                        <?php if ($timeEntry->getDescription()): ?>
                            <div class="info-item full-width">
                                <label><?php p($l->t('DESCRIPTION')); ?></label>
                                <span><?php p($timeEntry->getDescription()); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Time Entry Details -->
            <div class="projects-section">
                <div class="section-header">
                    <h3><i class="icon-calendar-custom"></i> <?php p($l->t('Entry Details')); ?></h3>
                </div>
                <div class="section-content">
                    <!-- Time Entry Overview Card -->
                    <div class="project-overview-card">
                        <div class="overview-header">
                            <div class="overview-title">
                                <h4><?php p($projectName); ?></h4>
                                <p class="overview-description"><?php p($timeEntry->getDescription() ?: $l->t('No description provided')); ?></p>
                            </div>
                            <div class="overview-status">
                                <span class="status-badge <?php p($projectStatusClass); ?>"><?php p($projectStatusLabel); ?></span>
                            </div>
                        </div>

                        <div class="overview-stats">
                            <div class="overview-stat">
                                <div class="stat-icon">
                                    <i class="icon-calendar-custom"></i>
                                </div>
                                <div class="stat-content">
                                    <span class="stat-label"><?php p($l->t('Date')); ?></span>
                                    <span class="stat-value"><?php p($timeEntry->getDate() ? $timeEntry->getDate()->format('d.m.Y') : 'Not set'); ?></span>
                                </div>
                            </div>
                            <div class="overview-stat">
                                <div class="stat-icon">
                                    <i class="icon-time-custom"></i>
                                </div>
                                <div class="stat-content">
                                    <span class="stat-label"><?php p($l->t('Hours')); ?></span>
                                    <span class="stat-value"><?php p($timeEntry->getHours()); ?> <?php p($l->t('hours')); ?></span>
                                </div>
                            </div>
                            <div class="overview-stat">
                                <div class="stat-icon">
                                    <i class="icon-money-custom"></i>
                                </div>
                                <div class="stat-content">
                                    <span class="stat-label"><?php p($l->t('Rate')); ?></span>
                                    <span class="stat-value">€<?php p(number_format($timeEntry->getHourlyRate(), 2)); ?><?php p($l->t('/hour')); ?></span>
                                </div>
                            </div>
                            <div class="overview-stat">
                                <div class="stat-icon">
                                    <i class="icon-money-custom"></i>
                                </div>
                                <div class="stat-content">
                                    <span class="stat-label"><?php p($l->t('Total')); ?></span>
                                    <span class="stat-value">€<?php p(number_format($totalCost, 2)); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Timeline Grid -->
                    <div class="timeline-grid">
                        <div class="timeline-item">
                            <div class="timeline-icon">
                                <i class="icon-calendar-custom"></i>
                            </div>
                            <div class="timeline-content">
                                <label><?php p($l->t('Entry Date')); ?></label>
                                <span><?php p($timeEntry->getDate() ? $timeEntry->getDate()->format('d.m.Y') : 'Not set'); ?></span>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-icon">
                                <i class="icon-time-custom"></i>
                            </div>
                            <div class="timeline-content">
                                <label><?php p($l->t('Hours Worked')); ?></label>
                                <span><?php p($timeEntry->getHours()); ?> <?php p($l->t('hours')); ?></span>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-icon">
                                <i class="icon-money-custom"></i>
                            </div>
                            <div class="timeline-content">
                                <label><?php p($l->t('Hourly Rate')); ?></label>
                                <span>€<?php p(number_format($timeEntry->getHourlyRate(), 2)); ?><?php p($l->t('/hour')); ?></span>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-icon">
                                <i class="icon-money-custom"></i>
                            </div>
                            <div class="timeline-content">
                                <label><?php p($l->t('Total Cost')); ?></label>
                                <span>€<?php p(number_format($totalCost, 2)); ?></span>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-icon">
                                <i class="icon-user-custom"></i>
                            </div>
                            <div class="timeline-content">
                                <label><?php p($l->t('Created By')); ?></label>
                                <span><?php p($timeEntry->getUserId()); ?></span>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-icon">
                                <i class="icon-calendar-custom"></i>
                            </div>
                            <div class="timeline-content">
                                <label><?php p($l->t('Created At')); ?></label>
                                <span><?php p($timeEntry->getCreatedAt() ? $timeEntry->getCreatedAt()->format('d.m.Y H:i') : 'Unknown'); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Actions Section -->
        <div class="section">
            <div class="section-header">
                <h3><i class="icon-time-custom"></i> <?php p($l->t('Actions')); ?></h3>
            </div>
            <div class="section-content">
                <div class="actions-grid">
                    <?php if ($timeEntry->getUserId() === $_['userId']): ?>
                        <a href="<?php p($urlGenerator->linkToRoute('projectcheck.timeentry.edit', ['id' => $timeEntryId])); ?>" class="button primary">
                            <i class="icon-edit-custom"></i>
                            <?php p($l->t('Edit Time Entry')); ?>
                        </a>
                        <button type="button" class="button danger delete-time-entry" id="delete-time-entry-btn"
                            aria-label="<?php p($l->t('Delete time entry')); ?>">
                            <i class="icon-delete-custom"></i>
                            <?php p($l->t('Delete Time Entry')); ?>
                        </button>
                    <?php endif; ?>
                    <a href="<?php p($urlGenerator->linkToRoute('projectcheck.timeentry.index')); ?>" class="button secondary">
                        <i class="icon-time-custom"></i>
                        <?php p($l->t('View All Time Entries')); ?>
                    </a>
                    <a href="/index.php/apps/projectcheck/projects/<?php p($timeEntry->getProjectId()); ?>" class="button secondary">
                        <i class="icon-user-custom"></i>
                        <?php p($l->t('View Project')); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script nonce="<?php p($_['cspNonce']) ?>">
    document.addEventListener('DOMContentLoaded', function() {
        const deleteBtn = document.getElementById('delete-time-entry-btn');
        if (deleteBtn) {
            deleteBtn.addEventListener('click', function() {
                if (confirm('<?php p($l->t('Are you sure you want to delete this time entry? This action cannot be undone.')); ?>')) {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '<?php p($urlGenerator->linkToRoute('projectcheck.timeentry.delete', ['id' => $timeEntryId])); ?>';

                    const tokenInput = document.createElement('input');
                    tokenInput.type = 'hidden';
                    tokenInput.name = 'requesttoken';
                    tokenInput.value = '<?php p($_['requesttoken']); ?>';

                    form.appendChild(tokenInput);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
    });
</script>