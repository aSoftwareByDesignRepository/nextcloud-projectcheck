<?php

/**
 * Common navigation template for the projectcheck app
 *
 * @copyright Copyright (c) 2024, Nextcloud GmbH
 * @license AGPL-3.0-or-later
 */

// Get current page to highlight active navigation item
$currentPage = $_SERVER['REQUEST_URI'] ?? '';
$isProjects = strpos($currentPage, '/projects') !== false;
$isCustomers = strpos($currentPage, '/customers') !== false;
$isEmployees = strpos($currentPage, '/employees') !== false;
$isTimeEntries = strpos($currentPage, '/time-entries') !== false;
$isSettings = strpos($currentPage, '/settings') !== false;
// Dashboard is active if URL contains /dashboard OR if it's the base app URL without any specific section
$isDashboard = strpos($currentPage, '/dashboard') !== false || 
               (!$isProjects && !$isCustomers && !$isEmployees && !$isTimeEntries && !$isSettings && 
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

<!-- Initialize Lucide Icons for Navigation -->
<script nonce="<?php p($_['cspNonce'] ?? ''); ?>">
    // Local SVG icon library for navigation
    const navSvgIcons = {
        folder: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M4 20h16a2 2 0 0 0 2-2V8a2 2 0 0 0-2-2h-7.93a2 2 0 0 1-1.66-.9l-.82-1.2A2 2 0 0 0 7.93 3H4a2 2 0 0 0-2 2v13c0 1.1.9 2 2 2Z"/></svg>',
        home: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg>',
        users: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
        'user-check': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/><path d="M16 11l2 2 4-4"/></svg>',
        clock: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>',
        settings: '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="lucide-icon"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.38a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.39a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>'
    };

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('[data-lucide]').forEach(function(el) {
            const iconName = el.getAttribute('data-lucide');
            if (navSvgIcons[iconName]) {
                el.innerHTML = navSvgIcons[iconName];
            }
        });
    });
</script>