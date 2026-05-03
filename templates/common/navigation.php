<?php

/**
 * Common navigation template for the projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

\OCP\Util::addStyle('projectcheck', 'common/feedback-system');
// Centralized locale-aware formatting (number/currency/date). Must load before any
// page module that calls window.ProjectCheckFormat (audit ref. AUDIT-FINDINGS B10/H28).
// Registered first so the script bucket exists; pc-l10n is then prepended so it still loads first.
\OCP\Util::addScript('projectcheck', 'common/format');
// Patch window.t before other app scripts; keep first in the projectcheck bundle (prepend list).
\OCP\Util::addScript('projectcheck', 'pc-l10n', 'core', true);
// Shared modal accessibility helper (focus trap, Escape, backdrop, restore focus).
// Must load before messaging/components so every openModal call gets the trap.
\OCP\Util::addScript('projectcheck', 'common/modal-a11y');
// SVG icon injection: external app script (CSP via Nextcloud app resource pipeline, not inline).
\OCP\Util::addScript('projectcheck', 'navigation-icons');
// Centralised icon catalog and hydration (audit ref. AUDIT-FINDINGS H22/icon-dedup).
// Replaces six duplicated inline svgIcons blocks across page templates.
\OCP\Util::addScript('projectcheck', 'common/icons');

// Get current page to highlight active navigation item
$currentPage = $_SERVER['REQUEST_URI'] ?? '';
$isProjects = strpos($currentPage, '/projects') !== false;
$isCustomers = strpos($currentPage, '/customers') !== false;
$isEmployees = strpos($currentPage, '/employees') !== false;
$isTimeEntries = strpos($currentPage, '/time-entries') !== false;
$isSettings = strpos($currentPage, '/settings') !== false;
$isOrganization = strpos($currentPage, '/organization') !== false;
// Injected by EnrichTemplateNavigationContext (BeforeTemplateRendered); safe fallbacks for edge cases.
$canManageSettings = $canManageSettings ?? ($_['canManageSettings'] ?? $_['canManageOrg'] ?? false);
$canManageOrganization = $canManageOrganization ?? ($_['canManageOrganization'] ?? $_['canManageOrg'] ?? false);
$canAccessSettings = $canManageSettings || $canManageOrganization;
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

// If stats are not available yet, fall back to explicit zeros.
if (!isset($_['stats']) || empty($_['stats'])) {
    $projectCount = 0;
    $customerCount = 0;
    $timeEntryCount = 0;
}

include __DIR__ . '/pc-l10n-bootstrap.php';
?>

<div id="app-navigation" role="navigation" aria-label="<?php p($l->t('ProjectCheck primary navigation')); ?>">
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
    <ul class="nav-menu" aria-label="<?php p($l->t('Main sections')); ?>">
        <li class="<?php echo $isDashboard ? 'active' : ''; ?>">
            <a href="<?php p($_['dashboardUrl'] ?? '/index.php/apps/projectcheck/dashboard'); ?>" <?php echo $isDashboard ? 'aria-current="page"' : ''; ?>>
                <i data-lucide="home" class="lucide-icon" aria-hidden="true"></i>
                <span><?php p($l->t('Dashboard')); ?></span>
            </a>
        </li>
        <li class="<?php echo $isTimeEntries ? 'active' : ''; ?>">
            <a href="<?php p($_['timeEntriesUrl'] ?? '/index.php/apps/projectcheck/time-entries'); ?>" <?php echo $isTimeEntries ? 'aria-current="page"' : ''; ?>>
                <i data-lucide="clock" class="lucide-icon" aria-hidden="true"></i>
                <span><?php p($l->t('Time Entries')); ?></span>
            </a>
        </li>
        <li class="<?php echo $isProjects ? 'active' : ''; ?>">
            <a href="<?php p($_['projectsUrl'] ?? '/index.php/apps/projectcheck/projects'); ?>" <?php echo $isProjects ? 'aria-current="page"' : ''; ?>>
                <i data-lucide="folder" class="lucide-icon" aria-hidden="true"></i>
                <span><?php p($l->t('Projects')); ?></span>
            </a>
        </li>
        <li class="<?php echo $isCustomers ? 'active' : ''; ?>">
            <a href="<?php p($_['customersUrl'] ?? '/index.php/apps/projectcheck/customers'); ?>" <?php echo $isCustomers ? 'aria-current="page"' : ''; ?>>
                <i data-lucide="users" class="lucide-icon" aria-hidden="true"></i>
                <span><?php p($l->t('Customers')); ?></span>
            </a>
        </li>
        <li class="<?php echo $isEmployees ? 'active' : ''; ?>">
            <a href="<?php p($_['employeesUrl'] ?? '/index.php/apps/projectcheck/employees'); ?>" <?php echo $isEmployees ? 'aria-current="page"' : ''; ?>>
                <i data-lucide="user-check" class="lucide-icon" aria-hidden="true"></i>
                <span><?php p($l->t('Employees')); ?></span>
            </a>
        </li>
        <?php if ($canAccessSettings) { ?>
        <li class="<?php echo $isSettings ? 'active' : ''; ?>">
            <a href="<?php p($_['settingsUrl'] ?? $orgAppSettingsUrl); ?>" <?php echo $isSettings ? 'aria-current="page"' : ''; ?>>
                <i data-lucide="settings" class="lucide-icon" aria-hidden="true"></i>
                <span><?php p($l->t('Settings')); ?></span>
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