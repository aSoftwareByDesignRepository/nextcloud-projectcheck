<?php

/**
 * Menu Bar Template
 * Vertical menu bar with three main sections: top (logo), middle (navigation), bottom (utilities)
 * This template generates the HTML structure for the menu bar
 */

// Ensure this file is being included within a Nextcloud app context
if (!defined('OC_APP')) {
    die('This file can only be accessed within a Nextcloud app');
}

// Get current user information
$user = \OC::$server->getUserSession()->getUser();
$userDisplayName = $user ? $user->getDisplayName() : '';
$userUID = $user ? $user->getUID() : '';

// Get current page for active menu highlighting
$currentPage = $_GET['page'] ?? 'dashboard';
$currentApp = $_GET['app'] ?? 'projectcheck';

// Get user permissions
$isAdmin = \OC::$server->getGroupManager()->isInGroup($userUID, 'admin');
$canManageProjects = true; // TODO: Implement proper permission check
$canManageCustomers = true; // TODO: Implement proper permission check
$canViewReports = true; // TODO: Implement proper permission check
?>

<!-- Mobile hamburger menu button -->
<button class="menu-bar-toggle" id="menu-bar-toggle" aria-label="<?php p($l->t('Toggle menu')); ?>" aria-expanded="false">
    <span class="hamburger-line"></span>
    <span class="hamburger-line"></span>
    <span class="hamburger-line"></span>
</button>

<!-- Mobile overlay background -->
<div class="menu-bar-overlay" id="menu-bar-overlay"></div>

<!-- Main menu bar container -->
<nav class="menu-bar" id="menu-bar" role="navigation" aria-label="<?php p($l->t('Main navigation')); ?>">

    <!-- Top Section: Logo and Branding -->
    <div class="menu-bar-top">
        <div class="logo">
            <?php if (file_exists(\OC::$SERVERROOT . '/apps/projectcheck/img/logo.png')): ?>
                <img src="<?php p(\OC::$server->getURLGenerator()->linkTo('projectcheck', 'img/logo.png')); ?>"
                    alt="<?php p($l->t('Project Control')); ?>" />
            <?php else: ?>
                <div class="logo-placeholder">
                    <span class="icon icon-folder"></span>
                </div>
            <?php endif; ?>
        </div>
        <h1 class="brand-name"><?php p($l->t('Project Control')); ?></h1>
    </div>

    <!-- Middle Section: Main Navigation -->
    <div class="menu-bar-middle">

        <!-- Dashboard Section -->
        <div class="menu-group">
            <div class="menu-section-header"><?php p($l->t('Overview')); ?></div>

            <a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('projectcheck.page.index')); ?>"
                class="menu-item <?php echo ($currentPage === 'dashboard') ? 'active' : ''; ?>"
                data-icon="dashboard"
                aria-current="<?php echo ($currentPage === 'dashboard') ? 'page' : 'false'; ?>">
                <span class="icon"></span>
                <span class="text"><?php p($l->t('Dashboard')); ?></span>
            </a>
        </div>

        <!-- Projects Section -->
        <div class="menu-group">
            <div class="menu-section-header"><?php p($l->t('Projects')); ?></div>

            <a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('projectcheck.page.projects')); ?>"
                class="menu-item <?php echo ($currentPage === 'projects') ? 'active' : ''; ?>"
                data-icon="projects"
                aria-current="<?php echo ($currentPage === 'projects') ? 'page' : 'false'; ?>">
                <span class="icon"></span>
                <span class="text"><?php p($l->t('All Projects')); ?></span>
            </a>

            <?php if ($canManageProjects): ?>
                <a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('projectcheck.page.project_form')); ?>"
                    class="menu-item <?php echo ($currentPage === 'project-form') ? 'active' : ''; ?>"
                    data-icon="folder"
                    aria-current="<?php echo ($currentPage === 'project-form') ? 'page' : 'false'; ?>">
                    <span class="icon"></span>
                    <span class="text"><?php p($l->t('New Project')); ?></span>
                </a>
            <?php endif; ?>
        </div>

        <!-- Customers Section -->
        <div class="menu-group">
            <div class="menu-section-header"><?php p($l->t('Customers')); ?></div>

            <a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('projectcheck.page.customers')); ?>"
                class="menu-item <?php echo ($currentPage === 'customers') ? 'active' : ''; ?>"
                data-icon="customers"
                aria-current="<?php echo ($currentPage === 'customers') ? 'page' : 'false'; ?>">
                <span class="icon"></span>
                <span class="text"><?php p($l->t('All Customers')); ?></span>
            </a>

            <?php if ($canManageCustomers): ?>
                <a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('projectcheck.page.customer_form')); ?>"
                    class="menu-item <?php echo ($currentPage === 'customer-form') ? 'active' : ''; ?>"
                    data-icon="users"
                    aria-current="<?php echo ($currentPage === 'customer-form') ? 'page' : 'false'; ?>">
                    <span class="icon"></span>
                    <span class="text"><?php p($l->t('New Customer')); ?></span>
                </a>
            <?php endif; ?>
        </div>

        <!-- Time Tracking Section -->
        <div class="menu-group">
            <div class="menu-section-header"><?php p($l->t('Time Tracking')); ?></div>

            <a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('projectcheck.page.time_entries')); ?>"
                class="menu-item <?php echo ($currentPage === 'time-entries') ? 'active' : ''; ?>"
                data-icon="time"
                aria-current="<?php echo ($currentPage === 'time-entries') ? 'page' : 'false'; ?>">
                <span class="icon"></span>
                <span class="text"><?php p($l->t('Time Entries')); ?></span>
            </a>

            <a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('projectcheck.page.time_entry_form')); ?>"
                class="menu-item <?php echo ($currentPage === 'time-entry-form') ? 'active' : ''; ?>"
                data-icon="calendar"
                aria-current="<?php echo ($currentPage === 'time-entry-form') ? 'page' : 'false'; ?>">
                <span class="icon"></span>
                <span class="text"><?php p($l->t('New Time Entry')); ?></span>
            </a>
        </div>

        <!-- Reports Section -->
        <?php if ($canViewReports): ?>
            <div class="menu-group">
                <div class="menu-section-header"><?php p($l->t('Reports')); ?></div>

                <a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('projectcheck.page.reports')); ?>"
                    class="menu-item <?php echo ($currentPage === 'reports') ? 'active' : ''; ?>"
                    data-icon="reports"
                    aria-current="<?php echo ($currentPage === 'reports') ? 'page' : 'false'; ?>">
                    <span class="icon"></span>
                    <span class="text"><?php p($l->t('Reports')); ?></span>
                </a>

                <a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('projectcheck.page.analytics')); ?>"
                    class="menu-item <?php echo ($currentPage === 'analytics') ? 'active' : ''; ?>"
                    data-icon="chart"
                    aria-current="<?php echo ($currentPage === 'analytics') ? 'page' : 'false'; ?>">
                    <span class="icon"></span>
                    <span class="text"><?php p($l->t('Analytics')); ?></span>
                </a>
            </div>
        <?php endif; ?>

    </div>

    <!-- Bottom Section: User Utilities and Settings -->
    <div class="menu-bar-bottom">

        <!-- User Information -->
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($userDisplayName, 0, 1)); ?>
            </div>
            <div class="user-details">
                <div class="user-name"><?php p($userDisplayName); ?></div>
                <div class="user-role"><?php echo $isAdmin ? $l->t('Administrator') : $l->t('User'); ?></div>
            </div>
        </div>

        <!-- Settings and Utilities -->
        <div class="menu-group">
            <a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('projectcheck.settings.index')); ?>"
                class="menu-item <?php echo ($currentPage === 'settings') ? 'active' : ''; ?>"
                data-icon="settings"
                aria-current="<?php echo ($currentPage === 'settings') ? 'page' : 'false'; ?>">
                <span class="icon"></span>
                <span class="text"><?php p($l->t('Settings')); ?></span>
            </a>

            <?php if ($isAdmin): ?>
                <a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('projectcheck.admin.index')); ?>"
                    class="menu-item <?php echo ($currentPage === 'admin-settings') ? 'active' : ''; ?>"
                    data-icon="gear"
                    aria-current="<?php echo ($currentPage === 'admin-settings') ? 'page' : 'false'; ?>">
                    <span class="icon"></span>
                    <span class="text"><?php p($l->t('Admin Settings')); ?></span>
                </a>
            <?php endif; ?>

            <a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('projectcheck.page.help')); ?>"
                class="menu-item <?php echo ($currentPage === 'help') ? 'active' : ''; ?>"
                data-icon="help"
                aria-current="<?php echo ($currentPage === 'help') ? 'page' : 'false'; ?>">
                <span class="icon"></span>
                <span class="text"><?php p($l->t('Help')); ?></span>
            </a>

            <a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('projectcheck.page.about')); ?>"
                class="menu-item <?php echo ($currentPage === 'about') ? 'active' : ''; ?>"
                data-icon="info"
                aria-current="<?php echo ($currentPage === 'about') ? 'page' : 'false'; ?>">
                <span class="icon"></span>
                <span class="text"><?php p($l->t('About')); ?></span>
            </a>
        </div>

        <!-- Logout -->
        <div class="menu-group">
            <a href="<?php p(\OC::$server->getURLGenerator()->linkToRoute('core.login.logout')); ?>"
                class="menu-item"
                data-icon="logout">
                <span class="icon"></span>
                <span class="text"><?php p($l->t('Logout')); ?></span>
            </a>
        </div>

    </div>

</nav>

<!-- Skip navigation link for accessibility -->
<a href="#main-content" class="skip-navigation" id="skip-navigation">
    <?php p($l->t('Skip to main content')); ?>
</a>