<?php

/**
 * Employee detail template for projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

script('projectcheck', 'employee-detail');
style('projectcheck', 'dashboard');
style('projectcheck', 'projects');
style('projectcheck', 'custom-icons');
style('projectcheck', 'navigation');
style('projectcheck', 'common/progress-bars');
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
?>

<script nonce="<?php p($_['cspNonce']) ?>">
    // Pass PHP variables to JavaScript
    window.projectControlData = {
        requestToken: '<?php p($_['requesttoken']) ?>',
        employeeId: '<?php p($eid); ?>'
    };
</script>

<div id="app-content" role="main">
    <div id="app-content-wrapper">
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb-container">
            <nav class="breadcrumb" aria-label="<?php p($l->t('Breadcrumb')); ?>">
                <ol>
                    <li><a href="<?php p($urlGenerator->linkToRoute('projectcheck.employee.index')); ?>"><?php p($l->t('Employees')); ?></a></li>
                    <li aria-current="page"><?php p($empDisplay); ?></li>
                </ol>
            </nav>
        </div>

        <!-- Page Header -->
        <div class="section page-header-section">
            <div class="header-content">
                <div class="header-text">
                    <div class="header-details">
                        <h2><?php p($empDisplay); ?><?php if ($isFormer): ?>
                            <span class="pc-badge pc-badge--neutral" role="status" aria-label="<?php p($l->t('Former user — account was removed. Statistics are historical data.')); ?>"><?php p($l->t('Former user')); ?></span>
                        <?php endif; ?></h2>
                        <p><?php p($l->t('Employee performance and time tracking statistics')); ?></p>
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
                </div>
                <div class="header-actions">
                    <a href="<?php p($urlGenerator->linkToRoute('projectcheck.employee.index')); ?>"
                        class="button secondary" role="button">
                        <i data-lucide="arrow-left" class="lucide-icon"></i>
                        <?php p($l->t('Back to Employees')); ?>
                    </a>
                </div>
            </div>
        </div>

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
                                            <div class="stat-value">€<?php p(number_format($yearData['total_cost'], 2)); ?></div>
                                            <div class="stat-label"><?php p($l->t('Total Cost')); ?></div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Progress indicators -->
                                <div class="yearly-progress">
                                    <div class="progress-item progress-item--compact">
                                        <div class="progress-label"><?php p($l->t('Hours Share')); ?></div>
                                        <div class="progress-wrapper">
                                            <div class="progress-bar progress-bar--sm">
                                                <div class="progress-fill" style="width: <?php p($totalHours > 0 ? ($yearData['total_hours'] / $totalHours) * 100 : 0); ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="progress-percentage"><?php p($totalHours > 0 ? round(($yearData['total_hours'] / $totalHours) * 100, 1) : 0); ?>%</div>
                                    </div>
                                    <div class="progress-item progress-item--compact">
                                        <div class="progress-label"><?php p($l->t('Cost Share')); ?></div>
                                        <div class="progress-wrapper">
                                            <div class="progress-bar progress-bar--sm">
                                                <div class="progress-fill" style="width: <?php p($totalCost > 0 ? ($yearData['total_cost'] / $totalCost) * 100 : 0); ?>%"></div>
                                            </div>
                                        </div>
                                        <div class="progress-percentage"><?php p($totalCost > 0 ? round(($yearData['total_cost'] / $totalCost) * 100, 1) : 0); ?>%</div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Main Content Grid -->
        <div class="content-grid">
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

        <!-- Employee Productivity Analysis -->
        <?php if (!empty($_['employeeProductivityAnalysis'])): ?>
            <div class="section employee-productivity-analysis-section">
                <div class="section-header">
                    <h3>
                        <i data-lucide="trending-up" class="lucide-icon"></i>
                        <?php p($l->t('Productivity Analysis')); ?>
                        <span class="info-tooltip"
                            title="<?php p($l->t('Billable Work: Client projects, sales, customer support, product development, research & development, and other revenue-generating activities. Overhead Work: Administrative tasks, meetings, internal projects, and training activities that do not directly generate revenue.')); ?>">ℹ️</span>
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
                                                <span class="stat-value">€<?php p(number_format($yearData['billable']['total_cost'], 2)); ?></span>
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
                                                <span class="stat-value">€<?php p(number_format($yearData['overhead']['total_cost'], 2)); ?></span>
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
    </div>
</div>

<script nonce="<?php p($_['cspNonce']) ?>">
    // Local SVG icon library
    const svgIcons = {
        user: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        mail: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>',
        'arrow-left': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>',
        calendar: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
        info: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>',
        plus: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
        clock: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>',
        users: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'icon-time-custom': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>',
        'icon-money-custom': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.25.5-2.5 1.5-3.5Z"/></svg>',
        'pie-chart': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>',
        'dollar-sign': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/></svg>',
        'settings': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1 1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
        'trending-up': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><polyline points="22,7 13.5,15.5 8.5,10.5 2,17"/><polyline points="16,7 22,7 22,13"/></svg>',
        'euro': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.25.5-2.5 1.5-3.5Z"/></svg>'
    };

    // Initialize icons
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-lucide]').forEach(function(el) {
            const iconName = el.getAttribute('data-lucide');
            if (svgIcons[iconName]) {
                el.innerHTML = svgIcons[iconName];
            }
        });

        // Initialize custom icons
        document.querySelectorAll('.icon-time-custom, .icon-money-custom').forEach(function(el) {
            const className = el.className;
            if (svgIcons[className]) {
                el.innerHTML = svgIcons[className];
            }
        });
    });
</script>