<?php

/**
 * Employee detail template for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

script('projectcheck', 'common/api');
script('projectcheck', 'employee-detail');
script('projectcheck', 'common/deletion-modal');
style('projectcheck', 'projects');
style('projectcheck', 'navigation');
style('projectcheck', 'common/progress-bars');
style('projectcheck', 'common/accessibility');
style('projectcheck', 'employee-detail');
?>

<?php include __DIR__ . '/common/navigation.php'; ?>
<?php
$emp = $_['employee'] ?? null;
$eid = (string) ($_['employeeId'] ?? '');
$isFormer = !empty($_['isFormerAccount']);
$empDisplay = $isFormer
	? (string) ($_['formerAccountDisplayName'] ?? $eid)
	: ($emp ? $emp->getDisplayName() : $eid);
$empEmail = $emp && !$isFormer ? $emp->getEMailAddress() : '';
$fmt = $_['fmt'] ?? null;
$currencyCode = isset($_['orgCurrency']) && is_string($_['orgCurrency']) ? strtoupper(trim($_['orgCurrency'])) : 'EUR';
if (preg_match('/^[A-Z]{3}$/', $currencyCode) !== 1) {
	$currencyCode = 'EUR';
}
?>

<script nonce="<?php p($_['cspNonce']) ?>">
    // Pass PHP variables to JavaScript
    window.projectControlData = {
        requestToken: '<?php p($_['requesttoken']) ?>',
        employeeId: '<?php p($eid); ?>',
        assignProjectUrl: '<?php p($_['assignProjectUrl'] ?? ''); ?>',
        addEmployeeRateUrl: '<?php p($_['addEmployeeRateUrl'] ?? ''); ?>',
        unassignProjectUrlTemplate: '<?php p($urlGenerator->linkToRoute('projectcheck.employee.unassignProjectPost', ['userId' => 'USER_ID', 'projectId' => 'PROJECT_ID'])); ?>'
    };
</script>

<?php
$isGlobalViewer = !empty($_['isGlobalViewer']);
$pageId = 'employee-detail';
$pageTitle = $empDisplay;
$pageHelp = $l->t('Employee performance and time tracking statistics');
include __DIR__ . '/common/page-start.php';
?>
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb-container">
            <nav class="breadcrumb" aria-label="<?php p($l->t('Breadcrumb')); ?>">
                <ol>
                    <li><a href="<?php p($urlGenerator->linkToRoute('projectcheck.employee.index')); ?>"><?php p($l->t('Employees')); ?></a></li>
                    <li aria-current="page"><?php p($empDisplay); ?></li>
                </ol>
            </nav>
        </div>

        <?php if (!$isGlobalViewer): ?>
            <div class="section">
                <div class="section-content">
                    <div class="pc-scope-banner" role="status" aria-live="polite">
                        <div class="pc-scope-banner__icon">
                            <i data-lucide="info" class="lucide-icon primary" aria-hidden="true"></i>
                        </div>
                        <div class="pc-scope-banner__content">
                            <h3><?php p($l->t('Private employee view')); ?></h3>
                            <p><?php p($l->t('You can only open your own employee profile unless you are an administrator.')); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <section class="pc-employee-profile" aria-label="<?php p($l->t('Employee profile')); ?>">
            <div class="pc-employee-profile__meta">
                <?php if ($isFormer): ?>
                    <span class="pc-badge pc-badge--neutral" role="status"><?php p($l->t('Former user — account was removed. Statistics are historical data.')); ?></span>
                <?php endif; ?>
                <div class="employee-meta">
                    <span class="meta-item">
                        <i data-lucide="user" class="lucide-icon primary" aria-hidden="true"></i>
                        <span><?php p($eid); ?></span>
                    </span>
                    <?php if (!$isFormer): ?>
                    <span class="meta-item">
                        <i data-lucide="mail" class="lucide-icon primary" aria-hidden="true"></i>
                        <span><?php p($empEmail !== '' && $empEmail !== null ? $empEmail : $l->t('No email')); ?></span>
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="header-actions">
                <a href="<?php p($urlGenerator->linkToRoute('projectcheck.employee.index')); ?>"
                    class="button secondary">
                    <i data-lucide="arrow-left" class="lucide-icon" aria-hidden="true"></i>
                    <?php p($l->t('Back to Employees')); ?>
                </a>
            </div>
        </section>

        <!-- Yearly Performance Dashboard -->
        <?php if (!empty($yearlyStats)): ?>
            <div class="section yearly-stats-section">
                <div class="section-header">
                    <h3><i data-lucide="calendar" class="lucide-icon primary"></i> <?php p($l->t('Yearly Performance Dashboard')); ?></h3>
                    <p><?php p($l->t('Track hours and costs across all projects for this employee')); ?></p>
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

        <?php if (!empty($_['canManageEmployeeRates'])): ?>
        <section class="pc-section" id="employee-rate-history" aria-labelledby="employee-rates-heading">
            <h3 id="employee-rates-heading" class="pc-section-title"><?php p($l->t('Hourly rate history')); ?></h3>
            <p class="pc-section-intro"><?php p($l->t('Append-only rates used when projects price hours by employee. Time entries keep the rate that was effective on the work date.')); ?></p>
            <?php $rates = $_['employeeHourlyRates'] ?? []; ?>
            <?php if ($rates === []): ?>
                <p class="form-hint"><?php p($l->t('No rates yet. Add the first rate with an effective-from date on or before the earliest work date you plan to log.')); ?></p>
            <?php else: ?>
                <table class="grid employee-rates-table">
                    <thead>
                        <tr>
                            <th scope="col"><?php p($l->t('Effective from')); ?></th>
                            <th scope="col"><?php p($l->t('Hourly rate (%s)', [$currencyCode])); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rates as $rateRow): ?>
                            <tr>
                                <td><?php p($rateRow->getEffectiveFrom()->format('d.m.Y')); ?></td>
                                <td><?php p(number_format((float) $rateRow->getHourlyRate(), 2, '.', '')); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <form id="add-employee-rate-form" class="employee-rate-add-form">
                <div class="form-row">
                    <div class="form-group">
                        <label for="employeeRateAmount"><?php p($l->t('New hourly rate (%s)', [$currencyCode])); ?></label>
                        <input type="number" id="employeeRateAmount" name="hourly_rate" class="form-input" min="0.01" step="0.01" required>
                    </div>
                    <div class="form-group">
                        <label for="employeeRateEffective"><?php p($l->t('Effective from')); ?></label>
                        <input type="date" id="employeeRateEffective" name="effective_from" class="form-input" required lang="<?php p(str_replace('_', '-', \OCP\Util::getLocale())); ?>">
                    </div>
                </div>
                <p id="employee-rate-error" class="form-error-text" role="alert" aria-live="assertive"></p>
                <button type="submit" class="button primary"><?php p($l->t('Add rate')); ?></button>
            </form>
        </section>
        <?php endif; ?>

        <!-- Main Content Grid -->
        <div class="content-grid">
            <?php
            $assignedProjects = $_['employeeAssignedProjects'] ?? [];
            $manageableProjects = $_['manageableProjects'] ?? [];
            $canManageAssignments = !empty($_['canManageAssignments']);
            ?>
            <div class="section" id="employee-project-assignments">
                <div class="section-header employee-assignments-header">
                    <h3><i data-lucide="users" class="lucide-icon primary"></i> <?php p($l->t('Project assignments')); ?></h3>
                </div>
                <div class="section-content">
                    <div class="employee-assignments-grid">
                        <div class="employee-assignments-panel employee-assignments-panel--form">
                            <h4 class="employee-assignments-panel__title"><?php p($l->t('Add to project')); ?></h4>
                            <?php if ($canManageAssignments): ?>
                                <?php if ($manageableProjects === [] && !empty($assignedProjects)): ?>
                            <p class="empty-state-hint"><?php p($l->t('This person is already on all projects you can manage.')); ?></p>
                                <?php elseif ($manageableProjects === []): ?>
                            <p class="empty-state-hint"><?php p($l->t('No projects available to assign. You need permission to manage a project team.')); ?></p>
                                <?php else: ?>
                            <form id="assign-project-form" class="employee-assignments-form" method="post">
                                <div class="form-group">
                                    <label for="assignProjectId"><?php p($l->t('Project')); ?></label>
                                    <select id="assignProjectId" name="project_id" class="form-input form-select" required aria-describedby="assign-project-help assign-project-error">
                                        <option value=""><?php p($l->t('Select project')); ?></option>
                                        <?php foreach ($manageableProjects as $project): ?>
                                            <option
                                                value="<?php p((int)$project->getId()); ?>"
                                                data-cost-rate-mode="<?php p($project->getCostRateMode()); ?>"
                                                data-project-url="<?php p($urlGenerator->linkToRoute('projectcheck.project.show', ['id' => (int)$project->getId()]) . '#team-section'); ?>">
                                                <?php p($project->getName()); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <p id="assign-project-help" class="form-help-text"><?php p($l->t('Only projects you can manage are shown. Already assigned projects are hidden.')); ?></p>
                                <p id="assign-project-member-hint" class="form-help-text pc-assign-member-hint" hidden></p>
                                <p id="assign-project-error" class="form-error-text" role="alert" aria-live="assertive"></p>
                                <button type="submit" class="button primary" id="assign-project-submit"><?php p($l->t('Add to project')); ?></button>
                            </form>
                                <?php endif; ?>
                            <?php else: ?>
                            <p class="empty-state-hint"><?php p($l->t('Project assignments can only be managed for active user accounts.')); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="employee-assignments-panel employee-assignments-panel--list">
                            <h4 class="employee-assignments-panel__title"><?php p($l->t('Assigned projects')); ?></h4>
                            <div class="team-members-list" role="list" aria-label="<?php p($l->t('Assigned projects')); ?>">
                                <?php if (!empty($assignedProjects)): ?>
                                    <?php foreach ($assignedProjects as $project): ?>
                                    <div class="team-member-item" role="listitem">
                                        <div class="member-info">
                                            <span class="member-name"><?php p($project->getName()); ?></span>
                                            <span class="member-role"><?php p($l->t('Status')); ?>: <?php p($l->t((string)$project->getStatus())); ?></span>
                                        </div>
                                        <div class="member-actions">
                                            <?php if ($canManageAssignments && $project->isEditableState()): ?>
                                            <button type="button"
                                                    class="action-btn employee-unassign-project-btn"
                                                    data-project-id="<?php p((int)$project->getId()); ?>"
                                                    data-project-name="<?php p($project->getName()); ?>"
                                                    aria-label="<?php p($l->t('Remove from project')); ?>">
                                                <i class="icon-delete-custom" aria-hidden="true"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="empty-state">
                                        <i class="icon-user-custom icon-large" aria-hidden="true"></i>
                                        <h3><?php p($l->t('No projects assigned yet')); ?></h3>
                                        <p><?php p($l->t('Use the form above to assign this person to a project.')); ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Employee Information -->
            <div class="section info-section">
                <div class="section-header">
                    <h3><i data-lucide="info" class="lucide-icon primary"></i> <?php p($l->t('Employee Information')); ?></h3>
                </div>
                <div class="section-content">
                    <div class="info-grid">
                        <div class="info-item">
                            <label><?php p($l->t('Display Name')); ?></label>
                            <span><?php p($empDisplay); ?></span>
                        </div>

                        <div class="info-item">
                            <label><?php p($l->t('User ID')); ?></label>
                            <span><?php p($eid); ?></span>
                        </div>

                        <?php if (!$isFormer && $emp && $emp->getEMailAddress()): ?>
                            <div class="info-item">
                                <label><?php p($l->t('Email')); ?></label>
                                <span><a href="mailto:<?php p($emp->getEMailAddress()); ?>"><?php p($emp->getEMailAddress()); ?></a></span>
                            </div>
                        <?php endif; ?>

                        <div class="info-item">
                            <label><?php p($l->t('Last Login')); ?></label>
                            <span><?php
								if ($isFormer || !$emp) {
									p($l->t('Not available (account removed)'));
								} else {
                                    $lastLogin = $emp->getLastLogin();
                                    if ($lastLogin && $lastLogin > 0) {
                                        if (is_int($lastLogin) || is_numeric($lastLogin)) {
                                            echo date('d.m.Y H:i', (int)$lastLogin);
                                        } elseif (is_object($lastLogin) && method_exists($lastLogin, 'format')) {
                                            echo $lastLogin->format('d.m.Y H:i');
                                        } else {
                                            p($l->t('Unknown'));
                                        }
                                    } else {
                                        p($l->t('Never'));
                                    }
								}
                                    ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="section">
                <div class="section-header">
                    <h3><?php p($l->t('Quick Actions')); ?></h3>
                    <p><?php p($l->t('Common tasks and shortcuts')); ?></p>
                </div>

                <div class="actions-grid">
                    <a href="<?php p($urlGenerator->linkToRoute('projectcheck.timeentry.create')); ?>" class="action-card">
                        <div class="action-icon">
                            <i data-lucide="plus" class="lucide-icon primary"></i>
                        </div>
                        <div class="action-content">
                            <h4><?php p($l->t('New Time Entry')); ?></h4>
                            <p><?php p($l->t('Log time for this employee')); ?></p>
                        </div>
                    </a>

                    <a href="<?php p($urlGenerator->linkToRoute('projectcheck.timeentry.index')); ?>" class="action-card">
                        <div class="action-icon">
                            <i data-lucide="clock" class="lucide-icon primary"></i>
                        </div>
                        <div class="action-content">
                            <h4><?php p($l->t('View Time Entries')); ?></h4>
                            <p><?php p($l->t('See all time entries')); ?></p>
                        </div>
                    </a>

                    <a href="<?php p($urlGenerator->linkToRoute('projectcheck.employee.index')); ?>" class="action-card">
                        <div class="action-icon">
                            <i data-lucide="users" class="lucide-icon primary"></i>
                        </div>
                        <div class="action-content">
                            <h4><?php p($l->t('All Employees')); ?></h4>
                            <p><?php p($l->t('View all employees')); ?></p>
                        </div>
                    </a>
                </div>
            </div>
        </div>

        <!-- Employee Project Type Analysis -->
        <?php if (!empty($_['employeeProjectTypeStats'])): ?>
            <div class="section employee-project-type-stats-section compact">
                <div class="section-header">
                    <h3><i data-lucide="pie-chart" class="lucide-icon"></i> <?php p($l->t('Project Type Analysis')); ?></h3>
                </div>
                <div class="section-content">
                    <div class="employee-project-type-stats-compact">
                        <?php foreach ($_['employeeProjectTypeStats'] as $year => $yearData): ?>
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
                                            <?php p($fmt ? $fmt->currency((float)$yearTotalCost) : $currencyCode . ' ' . number_format((float)$yearTotalCost, 2)); ?>
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
                                                            'other' => $l->t('Other'),
                                                        ];
                                                        $typeIconNames = [
                                                            'client' => 'users',
                                                            'admin' => 'settings',
                                                            'sales' => 'bar-chart-3',
                                                            'customer' => 'users',
                                                            'product' => 'layout-grid',
                                                            'meeting' => 'users',
                                                            'internal' => 'folder',
                                                            'research' => 'search',
                                                            'training' => 'file-text',
                                                            'other' => 'tag',
                                                        ];
                                                        $displayName = $displayNames[$projectType] ?? ucfirst($projectType);
                                                        $typeIcon = $typeIconNames[$projectType] ?? 'tag';
                                                        ?>
                                                        <div class="project-type-display">
                                                            <i data-lucide="<?php p($typeIcon); ?>" class="lucide-icon" aria-hidden="true"></i>
                                                            <span class="project-type-text"><?php p($displayName); ?></span>
                                                        </div>
                                                    </td>
                                                    <td class="stat-cell"><?php p(number_format($typeData['total_hours'], 1)); ?>h</td>
                                                    <td class="stat-cell"><?php p($fmt ? $fmt->currency((float)$typeData['total_cost']) : $currencyCode . ' ' . number_format((float)$typeData['total_cost'], 2)); ?></td>
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

        <!-- Employee Productivity Analysis -->
        <?php if (!empty($_['employeeProductivityAnalysis'])): ?>
            <div class="section employee-productivity-analysis-section">
                <div class="section-header">
                    <h3>
                        <i data-lucide="trending-up" class="lucide-icon" aria-hidden="true"></i>
                        <?php p($l->t('Productivity Analysis')); ?>
                        <button type="button"
                            class="pc-help-trigger"
                            aria-label="<?php p($l->t('Billable Work: Client projects, sales, customer support, product development, research & development, and other revenue-generating activities. Overhead Work: Administrative tasks, meetings, internal projects, and training activities that do not directly generate revenue.')); ?>">
                            <i data-lucide="info" class="lucide-icon" aria-hidden="true"></i>
                        </button>
                    </h3>
                    <p><?php p($l->t('Compare billable vs overhead work to measure this employee\'s productivity')); ?></p>
                </div>
                <div class="section-content">
                    <div class="productivity-stats-container">
                        <?php foreach ($_['employeeProductivityAnalysis'] as $year => $yearData): ?>
                            <div class="productivity-year-section">
                                <div class="year-header">
                                    <h4><?php p($year); ?></h4>
                                </div>
                                <div class="productivity-comparison">
                                    <div class="productivity-card billable">
                                        <div class="card-header">
                                            <h5><?php p($l->t('Billable Work')); ?></h5>
                                            <div class="card-icon">
                                                <i data-lucide="dollar-sign" class="lucide-icon"></i>
                                            </div>
                                        </div>
                                        <div class="card-content">
                                            <div class="stat-item">
                                                <span class="stat-value"><?php p(number_format($yearData['billable']['total_hours'], 1)); ?>h</span>
                                                <span class="stat-label"><?php p($l->t('Hours')); ?></span>
                                            </div>
                                            <div class="stat-item">
                                                <span class="stat-value"><?php p($fmt ? $fmt->currency((float)$yearData['billable']['total_cost']) : $currencyCode . ' ' . number_format((float)$yearData['billable']['total_cost'], 2)); ?></span>
                                                <span class="stat-label"><?php p($l->t('Revenue')); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="productivity-card overhead">
                                        <div class="card-header">
                                            <h5><?php p($l->t('Overhead Work')); ?></h5>
                                            <div class="card-icon">
                                                <i data-lucide="settings" class="lucide-icon"></i>
                                            </div>
                                        </div>
                                        <div class="card-content">
                                            <div class="stat-item">
                                                <span class="stat-value"><?php p(number_format($yearData['overhead']['total_hours'], 1)); ?>h</span>
                                                <span class="stat-label"><?php p($l->t('Hours')); ?></span>
                                            </div>
                                            <div class="stat-item">
                                                <span class="stat-value"><?php p($fmt ? $fmt->currency((float)$yearData['overhead']['total_cost']) : $currencyCode . ' ' . number_format((float)$yearData['overhead']['total_cost'], 2)); ?></span>
                                                <span class="stat-label"><?php p($l->t('Cost')); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="productivity-ratio">
                                    <div class="ratio-bar">
                                        <div class="ratio-fill billable" style="width: <?php
                                                                                        $totalHours = $yearData['billable']['total_hours'] + $yearData['overhead']['total_hours'];
                                                                                        p($totalHours > 0 ? ($yearData['billable']['total_hours'] / $totalHours) * 100 : 0);
                                                                                        ?>%"></div>
                                        <div class="ratio-fill overhead" style="width: <?php
                                                                                        p($totalHours > 0 ? ($yearData['overhead']['total_hours'] / $totalHours) * 100 : 0);
                                                                                        ?>%"></div>
                                    </div>
                                    <div class="ratio-labels">
                                        <span class="ratio-label billable"><?php p($l->t('Billable')); ?>: <?php p($totalHours > 0 ? round(($yearData['billable']['total_hours'] / $totalHours) * 100, 1) : 0); ?>%</span>
                                        <span class="ratio-label overhead"><?php p($l->t('Overhead')); ?>: <?php p($totalHours > 0 ? round(($yearData['overhead']['total_hours'] / $totalHours) * 100, 1) : 0); ?>%</span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
<?php include __DIR__ . '/common/page-end.php'; ?>
