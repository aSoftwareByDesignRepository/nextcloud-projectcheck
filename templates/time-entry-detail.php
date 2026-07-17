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
Util::addStyle('projectcheck', 'navigation');
Util::addStyle('projectcheck', 'common/detail-layout');

if (!isset($timeEntry) || !($timeEntry instanceof \OCA\ProjectCheck\Db\TimeEntry)) {
    throw new Exception('Time entry not found');
}

$timeEntryId = $timeEntry->getId();
$totalCost = $timeEntry->getCost() ?? ($timeEntry->getHours() * $timeEntry->getHourlyRate());
$fmt = $_['fmt'] ?? null;
$currencyCode = isset($_['orgCurrency']) && is_string($_['orgCurrency']) ? strtoupper(trim($_['orgCurrency'])) : 'EUR';
if (preg_match('/^[A-Z]{3}$/', $currencyCode) !== 1) {
	$currencyCode = 'EUR';
}

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
$projectLinkable = !empty($_['projectLinkable']);
$projectLinkHref = isset($projectShowUrl) ? (string) $projectShowUrl : (string) $urlGenerator->linkToRoute('projectcheck.project.show', ['id' => $timeEntry->getProjectId()]);
?>

<?php include __DIR__ . '/common/navigation.php'; ?>

<?php
$pageId = 'time-entry-detail';
$pageTitle = $l->t('Time Entry Details');
$pageHelp = $l->t('Time entry information and associated details');
ob_start(); ?>
                        <div class="project-meta">
                            <div class="meta-item">
                                <span data-lucide="calendar" class="lucide-icon" aria-hidden="true"></span>
                                <span><?php p($timeEntry->getDate() ? $timeEntry->getDate()->format('d.m.Y') : $l->t('Unknown')); ?></span>
                            </div>
                            <div class="meta-item">
                                <span data-lucide="clock" class="lucide-icon" aria-hidden="true"></span>
                                <span><?php p($timeEntry->getHours()); ?> <?php p($l->t('hours')); ?></span>
                            </div>
                            <div class="meta-item">
                                <span data-lucide="euro" class="lucide-icon" aria-hidden="true"></span>
                                <span><?php p($fmt ? $fmt->currency((float)$totalCost) : $currencyCode . ' ' . number_format((float)$totalCost, 2)); ?></span>
                            </div>
                            <div class="meta-item">
                                <span data-lucide="user" class="lucide-icon" aria-hidden="true"></span>
                                <span><?php p($timeEntry->isOwnedBy((string)($userId ?? '')) ? $l->t('Your time entry') : $l->t('Time entry by %s', [$timeEntry->getUserId()])); ?></span>
                            </div>
                            <div class="meta-item">
                                <?php
                                $chipKind = 'status';
                                $chipValue = $timeEntry->getBillingStatus();
                                include __DIR__ . '/parts/settlement-chip.php';
                                ?>
                            </div>
                        </div>
<?php
$pageHeaderMetaHtml = ob_get_clean();
ob_start(); ?>
                    <?php $entryBillingLocked = $timeEntry->isBillingLocked(); ?>
                    <?php if ($timeEntry->isOwnedBy((string)($userId ?? '')) && !$entryBillingLocked): ?>
                        <a href="<?php p($urlGenerator->linkToRoute('projectcheck.timeentry.edit', ['id' => $timeEntryId])); ?>" class="button secondary">
                            <span data-lucide="edit" class="lucide-icon" aria-hidden="true"></span>
                            <?php p($l->t('Edit Time Entry')); ?>
                        </a>
                    <?php endif; ?>
                    <a href="<?php p($urlGenerator->linkToRoute('projectcheck.timeentry.index')); ?>" class="button secondary">
                        <span data-lucide="arrow-left" class="lucide-icon" aria-hidden="true"></span>
                        <?php p($l->t('Back to List')); ?>
                    </a>
<?php
$pageHeaderActionsHtml = ob_get_clean();
$pageHeaderActionsLabel = $l->t('Time entry actions');
include __DIR__ . '/common/page-start.php';
?>
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb-container">
            <nav class="breadcrumb" aria-label="<?php p($l->t('Breadcrumb')); ?>">
                <ol>
                    <li><a href="<?php p($urlGenerator->linkToRoute('projectcheck.timeentry.index')); ?>"><?php p($l->t('Time Entries')); ?></a></li>
                    <li aria-current="page"><?php p($l->t('Time Entry Details')); ?></li>
                </ol>
            </nav>
        </div>

        <!-- Time Entry Statistics -->
        <section class="section stats-section pc-stats-panel pc-section" aria-labelledby="time-entry-stats-title">
            <div class="section-header">
                <h3 id="time-entry-stats-title"><i data-lucide="bar-chart-3" class="lucide-icon primary" aria-hidden="true"></i> <?php p($l->t('Summary')); ?></h3>
                <p><?php p($l->t('Hours, rate, and total for this entry.')); ?></p>
            </div>
            <div class="section-content">
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
                        <div class="stat-number"><?php p($fmt ? $fmt->currency((float)$timeEntry->getHourlyRate()) : $currencyCode . ' ' . number_format((float)$timeEntry->getHourlyRate(), 2)); ?></div>
                        <div class="stat-label"><?php p($l->t('HOURLY RATE')); ?></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="icon-money-custom icon-large"></i>
                    </div>
                    <div class="stat-content">
                        <div class="stat-number"><?php p($fmt ? $fmt->currency((float)$totalCost) : $currencyCode . ' ' . number_format((float)$totalCost, 2)); ?></div>
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
        </section>

        <!-- Content Grid -->
        <div class="content-grid">
            <!-- Time Entry Information -->
            <div class="section info-section pc-section" aria-labelledby="pc-te-info-heading">
                <div class="section-header">
                    <h3 id="pc-te-info-heading"><i data-lucide="info" class="lucide-icon primary" aria-hidden="true"></i> <?php p($l->t('Time Entry Information')); ?></h3>
                    <p><?php p($l->t('When, where, and how this entry was logged.')); ?></p>
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
                            <span><?php p($fmt ? $fmt->currency((float)$timeEntry->getHourlyRate()) : $currencyCode . ' ' . number_format((float)$timeEntry->getHourlyRate(), 2)); ?><?php p($l->t('/hour')); ?></span>
                            <p class="form-hint" id="time-entry-rate-frozen-hint"><?php p($l->t('This hourly rate was stored when the entry was saved and is not recalculated.')); ?></p>
                        </div>
                        <?php if (!empty($pricingRateSourceLabel)): ?>
                        <div class="info-item">
                            <label><?php p($l->t('How hours are priced')); ?></label>
                            <span><?php p($pricingRateSourceLabel); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="info-item">
                            <label><?php p($l->t('TOTAL COST')); ?></label>
                            <span class="total-cost"><?php p($fmt ? $fmt->currency((float)$totalCost) : $currencyCode . ' ' . number_format((float)$totalCost, 2)); ?></span>
                        </div>
                        <div class="info-item">
                            <label><?php p($l->t('SETTLEMENT')); ?></label>
                            <span>
                                <?php
                                $chipKind = 'status';
                                $chipValue = $timeEntry->getBillingStatus();
                                include __DIR__ . '/parts/settlement-chip.php';
                                ?>
                            </span>
                            <?php if ($timeEntry->getBilledAt()): ?>
                                <p class="form-hint"><?php p($l->t('Invoiced on %s', [$timeEntry->getBilledAt()->format('d.m.Y H:i')])); ?></p>
                            <?php endif; ?>
                            <?php if ($timeEntry->getPaidAt()): ?>
                                <p class="form-hint"><?php p($l->t('Paid on %s', [$timeEntry->getPaidAt()->format('d.m.Y H:i')])); ?></p>
                            <?php endif; ?>
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
            <div class="section projects-section" aria-labelledby="pc-te-details-heading">
                <div class="section-header">
                    <h3 id="pc-te-details-heading"><i data-lucide="calendar" class="lucide-icon primary" aria-hidden="true"></i> <?php p($l->t('Entry Details')); ?></h3>
                    <p><?php p($l->t('A quick look at this entry and its project.')); ?></p>
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
                                    <span class="stat-value"><?php p($fmt ? $fmt->currency((float)$timeEntry->getHourlyRate()) : $currencyCode . ' ' . number_format((float)$timeEntry->getHourlyRate(), 2)); ?><?php p($l->t('/hour')); ?></span>
                                </div>
                            </div>
                            <div class="overview-stat">
                                <div class="stat-icon">
                                    <i class="icon-money-custom"></i>
                                </div>
                                <div class="stat-content">
                                    <span class="stat-label"><?php p($l->t('Total')); ?></span>
                                    <span class="stat-value"><?php p($fmt ? $fmt->currency((float)$totalCost) : $currencyCode . ' ' . number_format((float)$totalCost, 2)); ?></span>
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
                                <span><?php p($fmt ? $fmt->currency((float)$timeEntry->getHourlyRate()) : $currencyCode . ' ' . number_format((float)$timeEntry->getHourlyRate(), 2)); ?><?php p($l->t('/hour')); ?></span>
                            </div>
                        </div>
                        <div class="timeline-item">
                            <div class="timeline-icon">
                                <i class="icon-money-custom"></i>
                            </div>
                            <div class="timeline-content">
                                <label><?php p($l->t('Total Cost')); ?></label>
                                <span><?php p($fmt ? $fmt->currency((float)$totalCost) : $currencyCode . ' ' . number_format((float)$totalCost, 2)); ?></span>
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

        <?php
        $canSettleEntry = !empty($_['canSettleEntry']);
        $billingEntryUrl = (string)($_['billingEntryUrl'] ?? '');
        $allowedBillingTargets = \OCA\ProjectCheck\Util\BillingStatus::allowedTargets($timeEntry->getBillingStatus());
        $billingTargetButtons = [
            'invoiced' => ['label' => $l->t('Mark invoiced'), 'icon' => 'file-text'],
            'paid' => ['label' => $l->t('Mark paid'), 'icon' => 'circle-check'],
            'open' => ['label' => $l->t('Reopen'), 'icon' => 'rotate-ccw'],
            'excluded' => ['label' => $l->t('Mark not billable'), 'icon' => 'circle-slash'],
        ];
        ?>
        <?php if ($canSettleEntry || $entryBillingLocked): ?>
        <!-- Settlement section (feature spec §12) -->
        <div class="section pc-section" id="pc-entry-settlement" aria-labelledby="pc-te-settlement-heading"
            <?php if ($canSettleEntry): ?>data-billing-entry-url="<?php p($billingEntryUrl); ?>"<?php endif; ?>>
            <div class="section-header">
                <h3 id="pc-te-settlement-heading"><span data-lucide="wallet" class="lucide-icon primary" aria-hidden="true"></span> <?php p($l->t('Settlement')); ?></h3>
                <p><?php p($l->t('Mark this entry as invoiced, paid, or not billable.')); ?></p>
            </div>
            <div class="section-content">
                <p class="pc-entry-settlement__state">
                    <?php
                    $chipKind = 'status';
                    $chipValue = $timeEntry->getBillingStatus();
                    include __DIR__ . '/parts/settlement-chip.php';
                    ?>
                </p>
                <?php if ($entryBillingLocked && !$canSettleEntry): ?>
                    <p class="form-hint">
                        <?php p($l->t('This entry has been invoiced or paid, so its content is locked. Ask a project manager to reopen it if something needs fixing.')); ?>
                    </p>
                <?php endif; ?>
                <?php if ($canSettleEntry && $allowedBillingTargets !== []): ?>
                    <div class="actions-grid pc-entry-settlement__actions" role="group" aria-label="<?php p($l->t('Change settlement status')); ?>">
                        <?php foreach ($allowedBillingTargets as $billingTarget): ?>
                            <?php $targetMeta = $billingTargetButtons[$billingTarget] ?? null; ?>
                            <?php if ($targetMeta === null) continue; ?>
                            <button type="button" class="button secondary pc-entry-billing-action"
                                data-billing-target="<?php p($billingTarget); ?>">
                                <span data-lucide="<?php p($targetMeta['icon']); ?>" class="lucide-icon" aria-hidden="true"></span>
                                <?php p($targetMeta['label']); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                    <p class="form-hint"><?php p($l->t('Payments always go through "Invoiced" first — there is no shortcut from Open to Paid.')); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Actions Section -->
        <div class="section" aria-labelledby="pc-te-actions-heading">
            <div class="section-header">
                <h3 id="pc-te-actions-heading"><i data-lucide="wrench" class="lucide-icon primary" aria-hidden="true"></i> <?php p($l->t('Actions')); ?></h3>
                <p><?php p($l->t('Edit, delete, or open related pages.')); ?></p>
            </div>
            <div class="section-content">
                <div class="actions-grid">
                    <?php if ($timeEntry->isOwnedBy((string)($_['userId'] ?? '')) && !$entryBillingLocked): ?>
                        <a href="<?php p($urlGenerator->linkToRoute('projectcheck.timeentry.edit', ['id' => $timeEntryId])); ?>" class="button primary">
                            <i class="icon-edit-custom"></i>
                            <?php p($l->t('Edit Time Entry')); ?>
                        </a>
                        <button type="button" class="button danger delete-time-entry" id="delete-time-entry-btn"
                            data-id="<?php p((string)$timeEntryId); ?>"
                            data-delete-url="<?php p($urlGenerator->linkToRoute('projectcheck.timeentry.deletePost', ['id' => $timeEntryId])); ?>"
                            data-index-url="<?php p($urlGenerator->linkToRoute('projectcheck.timeentry.index')); ?>"
                            data-confirm="<?php p($l->t('Are you sure you want to delete this time entry? This action cannot be undone.')); ?>"
                            aria-label="<?php p($l->t('Delete time entry')); ?>">
                            <i class="icon-delete-custom" aria-hidden="true"></i>
                            <?php p($l->t('Delete Time Entry')); ?>
                        </button>
                    <?php elseif ($timeEntry->isOwnedBy((string)($_['userId'] ?? '')) && $entryBillingLocked): ?>
                        <p class="form-hint pc-entry-settlement__lock-note">
                            <span data-lucide="lock" class="lucide-icon" aria-hidden="true"></span>
                            <?php p($l->t('Editing and deleting are disabled because this entry has been invoiced or paid.')); ?>
                        </p>
                    <?php endif; ?>
                    <a href="<?php p($urlGenerator->linkToRoute('projectcheck.timeentry.index')); ?>" class="button secondary">
                        <i class="icon-time-custom"></i>
                        <?php p($l->t('View All Time Entries')); ?>
                    </a>
                    <?php if ($projectLinkable): ?>
                        <a href="<?php p($projectLinkHref); ?>" class="button secondary">
                            <i class="icon-user-custom"></i>
                            <?php p($l->t('View Project')); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
<?php include __DIR__ . '/common/page-end.php'; ?>