<?php

/**
 * Common navigation template for the projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

\OCP\Util::addStyle('projectcheck', 'common/feedback-system');
// Patch window.t before other app scripts; keep first in the projectcheck bundle (prepend list).
\OCP\Util::addScript('projectcheck', 'pc-l10n', 'core', true);
// SVG icon injection: external app script (CSP via Nextcloud app resource pipeline, not inline).
\OCP\Util::addScript('projectcheck', 'navigation-icons');

// Get current page to highlight active navigation item
$currentPage = $_SERVER['REQUEST_URI'] ?? '';
$isProjects = strpos($currentPage, '/projects') !== false;
$isCustomers = strpos($currentPage, '/customers') !== false;
$isEmployees = strpos($currentPage, '/employees') !== false;
$isTimeEntries = strpos($currentPage, '/time-entries') !== false;
$isSettings = strpos($currentPage, '/settings') !== false;
$isOrganization = strpos($currentPage, '/organization') !== false;
// Injected by EnrichTemplateNavigationContext (BeforeTemplateRendered); safe fallbacks for edge cases.
$canManageOrg = $canManageOrg ?? ($_['canManageOrg'] ?? false);
$orgAppSettingsUrl = $orgAppSettingsUrl ?? ($_['orgAppSettingsUrl'] ?? '/index.php/apps/projectcheck/organization');
// Dashboard is active if URL contains /dashboard OR if it's the base app URL without any specific section
$isDashboard = strpos($currentPage, '/dashboard') !== false || 
               (!$isProjects && !$isCustomers && !$isEmployees && !$isTimeEntries && !$isSettings && !$isOrganization && 
                strpos($currentPage, '/apps/projectcheck') !== false);

// Get stats for the footer (if available)
$projectCount = $_['stats']['total_projects'] ?? $_['stats']['totalProjects'] ?? 0;
$customerCount = $_['stats']['total_customers'] ?? $_['stats']['totalCustomers'] ?? 0;
$timeEntryCount = $_['stats']['total_time_entries'] ?? $_['stats']['totalTimeEntries'] ?? 0;

// Ensure we have valid numbers and provide fallbacks
$projectCount = is_numeric($projectCount) ? (int)$projectCount : 0;
$customerCount = is_numeric($customerCount) ? (int)$customerCount : 0;
$timeEntryCount = is_numeric($timeEntryCount) ? (int)$timeEntryCount : 0;

// If stats are not available, show a loading indicator or default values
if (!isset($_['stats']) || empty($_['stats'])) {
    $projectCount = '...';
    $customerCount = '...';
    $timeEntryCount = '...';
}

include __DIR__ . '/pc-l10n-bootstrap.php';
?>

<div id="app-navigation" role="navigation">
    <!-- Sidebar Header -->
    <div class="sidebar-header">
        <div class="app-brand">
            <div class="app-icon">
                <i data-lucide="folder" class="lucide-icon"></i>
            </div>
            <div class="app-info">
                <h3><?php p($l->t('ProjectCheck')); ?></h3>
                <p><?php p($l->t('Manage your projects')); ?></p>
            </div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <ul class="nav-menu">
        <li class="<?php echo $isDashboard ? 'active' : ''; ?>" <?php echo $isDashboard ? 'aria-current="page"' : ''; ?>>
            <a href="<?php p($_['dashboardUrl'] ?? '/index.php/apps/projectcheck/dashboard'); ?>">
                <i data-lucide="home" class="lucide-icon"></i>
                <span><?php p($l->t('Dashboard')); ?></span>
            </a>
        </li>
        <li class="<?php echo $isTimeEntries ? 'active' : ''; ?>" <?php echo $isTimeEntries ? 'aria-current="page"' : ''; ?>>
            <a href="<?php p($_['timeEntriesUrl'] ?? '/index.php/apps/projectcheck/time-entries'); ?>">
                <i data-lucide="clock" class="lucide-icon"></i>
                <span><?php p($l->t('Time Entries')); ?></span>
            </a>
        </li>
        <li class="<?php echo $isProjects ? 'active' : ''; ?>" <?php echo $isProjects ? 'aria-current="page"' : ''; ?>>
            <a href="<?php p($_['projectsUrl'] ?? '/index.php/apps/projectcheck/projects'); ?>">
                <i data-lucide="folder" class="lucide-icon"></i>
                <span><?php p($l->t('Projects')); ?></span>
            </a>
        </li>
        <li class="<?php echo $isCustomers ? 'active' : ''; ?>" <?php echo $isCustomers ? 'aria-current="page"' : ''; ?>>
            <a href="<?php p($_['customersUrl'] ?? '/index.php/apps/projectcheck/customers'); ?>">
                <i data-lucide="users" class="lucide-icon"></i>
                <span><?php p($l->t('Customers')); ?></span>
            </a>
        </li>
        <li class="<?php echo $isEmployees ? 'active' : ''; ?>" <?php echo $isEmployees ? 'aria-current="page"' : ''; ?>>
            <a href="<?php p($_['employeesUrl'] ?? '/index.php/apps/projectcheck/employees'); ?>">
                <i data-lucide="user-check" class="lucide-icon"></i>
                <span><?php p($l->t('Employees')); ?></span>
            </a>
        </li>
        <li class="<?php echo $isSettings ? 'active' : ''; ?>" <?php echo $isSettings ? 'aria-current="page"' : ''; ?>>
            <a href="<?php p($_['settingsUrl'] ?? '/index.php/apps/projectcheck/settings'); ?>">
                <i data-lucide="settings" class="lucide-icon"></i>
                <span><?php p($l->t('Settings')); ?></span>
            </a>
        </li>
        <?php if ($canManageOrg) { ?>
        <li class="<?php echo $isOrganization ? 'active' : ''; ?>" <?php echo $isOrganization ? 'aria-current="page"' : ''; ?>>
            <a href="<?php p($orgAppSettingsUrl); ?>">
                <i data-lucide="shield" class="lucide-icon"></i>
                <span><?php p($l->t('Organization')); ?></span>
            </a>
        </li>
        <?php } ?>
    </ul>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <div class="quick-stats">
            <div class="stat-item">
                <span class="stat-number"><?php p($projectCount); ?></span>
                <span class="stat-label"><?php p($l->t('Projects')); ?></span>
            </div>
            <div class="stat-item">
                <span class="stat-number"><?php p($customerCount); ?></span>
                <span class="stat-label"><?php p($l->t('Customers')); ?></span>
            </div>
        </div>
    </div>
</div>