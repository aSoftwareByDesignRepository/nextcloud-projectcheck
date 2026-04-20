<?php

/**
 * Common Header Template for ProjectCheck App
 * 
 * This template provides the header section that integrates with Nextcloud's
 * header system and provides navigation for the ProjectCheck app.
 */

// Ensure this file is being included within Nextcloud
if (!defined('OCP\AppFramework\App::class')) {
    die('Direct access not allowed');
}

// Get the current user and app context
$user = \OC::$server->getUserSession()->getUser();
$appName = 'projectcheck';
$currentPage = isset($currentPage) ? $currentPage : 'dashboard';

// Get the current URL for navigation highlighting
$currentUrl = $_SERVER['REQUEST_URI'];
?>
<header class="header">
    <div class="header__content">
        <!-- App Logo and Title -->
        <div class="header__logo">
            <a href="<?php print_unescaped(link_to($appName, 'index.php')); ?>" class="header__logo-link">
                <img src="<?php print_unescaped(image_path($appName, 'logo.svg')); ?>"
                    alt="<?php p($l->t('ProjectCheck')); ?>"
                    class="header__logo-image">
                <span class="header__logo-text">ProjectCheck</span>
            </a>
        </div>

        <!-- Main Navigation -->
        <nav class="header__navigation" role="navigation" aria-label="<?php p($l->t('Main navigation')); ?>">
            <ul class="header__nav-list">
                <li class="header__nav-item">
                    <a href="<?php print_unescaped(link_to($appName, 'index.php')); ?>"
                        class="header__nav-link <?php echo ($currentPage === 'dashboard') ? 'header__nav-link--active' : ''; ?>"
                        title="<?php p($l->t('Dashboard')); ?>">
                        <span class="header__nav-icon">📊</span>
                        <span class="header__nav-text"><?php p($l->t('Dashboard')); ?></span>
                    </a>
                </li>

                <li class="header__nav-item">
                    <a href="<?php print_unescaped(link_to($appName, 'projects.php')); ?>"
                        class="header__nav-link <?php echo ($currentPage === 'projects') ? 'header__nav-link--active' : ''; ?>"
                        title="<?php p($l->t('Projects')); ?>">
                        <span class="header__nav-icon">📁</span>
                        <span class="header__nav-text"><?php p($l->t('Projects')); ?></span>
                    </a>
                </li>

                <li class="header__nav-item">
                    <a href="<?php print_unescaped(link_to($appName, 'customers.php')); ?>"
                        class="header__nav-link <?php echo ($currentPage === 'customers') ? 'header__nav-link--active' : ''; ?>"
                        title="<?php p($l->t('Customers')); ?>">
                        <span class="header__nav-icon">👥</span>
                        <span class="header__nav-text"><?php p($l->t('Customers')); ?></span>
                    </a>
                </li>

                <li class="header__nav-item">
                    <a href="<?php print_unescaped(link_to($appName, 'time-entries.php')); ?>"
                        class="header__nav-link <?php echo ($currentPage === 'time-entries') ? 'header__nav-link--active' : ''; ?>"
                        title="<?php p($l->t('Time Entries')); ?>">
                        <span class="header__nav-icon">⏱️</span>
                        <span class="header__nav-text"><?php p($l->t('Time Entries')); ?></span>
                    </a>
                </li>

                <li class="header__nav-item">
                    <a href="<?php print_unescaped(link_to($appName, 'settings.php')); ?>"
                        class="header__nav-link <?php echo ($currentPage === 'settings') ? 'header__nav-link--active' : ''; ?>"
                        title="<?php p($l->t('Settings')); ?>">
                        <span class="header__nav-icon">⚙️</span>
                        <span class="header__nav-text"><?php p($l->t('Settings')); ?></span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Header Actions -->
        <div class="header__actions">
            <!-- Quick Actions -->
            <div class="header__quick-actions">
                <a href="<?php print_unescaped(link_to($appName, 'time-entry-form.php')); ?>"
                    class="header__action-btn header__action-btn--primary"
                    title="<?php p($l->t('Start Time Entry')); ?>">
                    <span class="header__action-icon">▶️</span>
                    <span class="header__action-text"><?php p($l->t('Start Timer')); ?></span>
                </a>

                <a href="<?php print_unescaped(link_to($appName, 'project-form.php')); ?>"
                    class="header__action-btn"
                    title="<?php p($l->t('New Project')); ?>">
                    <span class="header__action-icon">➕</span>
                    <span class="header__action-text"><?php p($l->t('New Project')); ?></span>
                </a>
            </div>

            <!-- User Menu -->
            <div class="header__user-menu">
                <button type="button"
                    class="header__user-btn"
                    aria-expanded="false"
                    aria-haspopup="true"
                    aria-label="<?php p($l->t('User menu')); ?>"
                    title="<?php p($l->t('User menu')); ?>">
                    <span class="header__user-avatar">
                        <?php if ($user): ?>
                            <img src="<?php print_unescaped(\OC::$server->getURLGenerator()->linkToRoute('core.avatar.getAvatar', ['userId' => $user->getUID(), 'size' => 32])); ?>"
                                alt="<?php p($user->getDisplayName()); ?>"
                                class="header__user-avatar-image">
                        <?php else: ?>
                            <span class="header__user-avatar-placeholder">👤</span>
                        <?php endif; ?>
                    </span>
                    <span class="header__user-name">
                        <?php p($user ? $user->getDisplayName() : $l->t('Guest')); ?>
                    </span>
                    <span class="header__user-arrow">▼</span>
                </button>

                <div class="header__user-dropdown" style="display: none;">
                    <ul class="header__user-dropdown-list">
                        <?php if ($user): ?>
                            <li class="header__user-dropdown-item">
                                <a href="<?php print_unescaped(link_to($appName, 'personal-settings.php')); ?>"
                                    class="header__user-dropdown-link">
                                    <span class="header__user-dropdown-icon">👤</span>
                                    <span class="header__user-dropdown-text"><?php p($l->t('Profile')); ?></span>
                                </a>
                            </li>

                            <li class="header__user-dropdown-item">
                                <a href="<?php print_unescaped(link_to($appName, 'settings.php')); ?>"
                                    class="header__user-dropdown-link">
                                    <span class="header__user-dropdown-icon">⚙️</span>
                                    <span class="header__user-dropdown-text"><?php p($l->t('Settings')); ?></span>
                                </a>
                            </li>

                            <li class="header__user-dropdown-divider"></li>

                            <li class="header__user-dropdown-item">
                                <a href="<?php print_unescaped(\OC::$server->getURLGenerator()->linkToRoute('core.logout')); ?>"
                                    class="header__user-dropdown-link header__user-dropdown-link--logout">
                                    <span class="header__user-dropdown-icon">🚪</span>
                                    <span class="header__user-dropdown-text"><?php p($l->t('Logout')); ?></span>
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="header__user-dropdown-item">
                                <a href="<?php print_unescaped(\OC::$server->getURLGenerator()->linkToRoute('core.login.showLoginForm')); ?>"
                                    class="header__user-dropdown-link">
                                    <span class="header__user-dropdown-icon">🔑</span>
                                    <span class="header__user-dropdown-text"><?php p($l->t('Login')); ?></span>
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Theme Toggle -->
            <button type="button"
                class="header__theme-toggle"
                aria-label="<?php p($l->t('Toggle theme')); ?>"
                title="<?php p($l->t('Toggle dark/light theme')); ?>">
                <span class="header__theme-icon header__theme-icon--light">☀️</span>
                <span class="header__theme-icon header__theme-icon--dark">🌙</span>
            </button>
        </div>

        <!-- Mobile Menu Toggle -->
        <button type="button"
            class="header__mobile-toggle"
            aria-label="<?php p($l->t('Toggle mobile menu')); ?>"
            aria-expanded="false">
            <span class="header__mobile-toggle-line"></span>
            <span class="header__mobile-toggle-line"></span>
            <span class="header__mobile-toggle-line"></span>
        </button>
    </div>

    <!-- Mobile Navigation -->
    <div class="header__mobile-nav" style="display: none;">
        <nav class="header__mobile-navigation" role="navigation" aria-label="<?php p($l->t('Mobile navigation')); ?>">
            <ul class="header__mobile-nav-list">
                <li class="header__mobile-nav-item">
                    <a href="<?php print_unescaped(link_to($appName, 'index.php')); ?>"
                        class="header__mobile-nav-link <?php echo ($currentPage === 'dashboard') ? 'header__mobile-nav-link--active' : ''; ?>">
                        <span class="header__mobile-nav-icon">📊</span>
                        <span class="header__mobile-nav-text"><?php p($l->t('Dashboard')); ?></span>
                    </a>
                </li>

                <li class="header__mobile-nav-item">
                    <a href="<?php print_unescaped(link_to($appName, 'projects.php')); ?>"
                        class="header__mobile-nav-link <?php echo ($currentPage === 'projects') ? 'header__mobile-nav-link--active' : ''; ?>">
                        <span class="header__mobile-nav-icon">📁</span>
                        <span class="header__mobile-nav-text"><?php p($l->t('Projects')); ?></span>
                    </a>
                </li>

                <li class="header__mobile-nav-item">
                    <a href="<?php print_unescaped(link_to($appName, 'customers.php')); ?>"
                        class="header__mobile-nav-link <?php echo ($currentPage === 'customers') ? 'header__mobile-nav-link--active' : ''; ?>">
                        <span class="header__mobile-nav-icon">👥</span>
                        <span class="header__mobile-nav-text"><?php p($l->t('Customers')); ?></span>
                    </a>
                </li>

                <li class="header__mobile-nav-item">
                    <a href="<?php print_unescaped(link_to($appName, 'time-entries.php')); ?>"
                        class="header__mobile-nav-link <?php echo ($currentPage === 'time-entries') ? 'header__mobile-nav-link--active' : ''; ?>">
                        <span class="header__mobile-nav-icon">⏱️</span>
                        <span class="header__mobile-nav-text"><?php p($l->t('Time Entries')); ?></span>
                    </a>
                </li>

                <li class="header__mobile-nav-item">
                    <a href="<?php print_unescaped(link_to($appName, 'settings.php')); ?>"
                        class="header__mobile-nav-link <?php echo ($currentPage === 'settings') ? 'header__mobile-nav-link--active' : ''; ?>">
                        <span class="header__mobile-nav-icon">⚙️</span>
                        <span class="header__mobile-nav-text"><?php p($l->t('Settings')); ?></span>
                    </a>
                </li>
            </ul>
        </nav>

        <!-- Mobile Quick Actions -->
        <div class="header__mobile-actions">
            <a href="<?php print_unescaped(link_to($appName, 'time-entry-form.php')); ?>"
                class="header__mobile-action-btn header__mobile-action-btn--primary">
                <span class="header__mobile-action-icon">▶️</span>
                <span class="header__mobile-action-text"><?php p($l->t('Start Timer')); ?></span>
            </a>

            <a href="<?php print_unescaped(link_to($appName, 'project-form.php')); ?>"
                class="header__mobile-action-btn">
                <span class="header__mobile-action-icon">➕</span>
                <span class="header__mobile-action-text"><?php p($l->t('New Project')); ?></span>
            </a>
        </div>
    </div>
</header>

<script nonce="<?php p($_['cspNonce'] ?? '') ?>">
    // Header functionality
    document.addEventListener('DOMContentLoaded', function() {
        const header = document.querySelector('.header');
        const mobileToggle = header.querySelector('.header__mobile-toggle');
        const mobileNav = header.querySelector('.header__mobile-nav');
        const userBtn = header.querySelector('.header__user-btn');
        const userDropdown = header.querySelector('.header__user-dropdown');
        const themeToggle = header.querySelector('.header__theme-toggle');

        // Mobile menu toggle
        mobileToggle.addEventListener('click', function() {
            const isExpanded = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', !isExpanded);
            mobileNav.style.display = isExpanded ? 'none' : 'block';
        });

        // User dropdown toggle
        userBtn.addEventListener('click', function() {
            const isExpanded = this.getAttribute('aria-expanded') === 'true';
            this.setAttribute('aria-expanded', !isExpanded);
            userDropdown.style.display = isExpanded ? 'none' : 'block';
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            if (!header.contains(event.target)) {
                userDropdown.style.display = 'none';
                userBtn.setAttribute('aria-expanded', 'false');
            }
        });

        // Theme toggle
        themeToggle.addEventListener('click', function() {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';

            document.documentElement.setAttribute('data-theme', newTheme);
            localStorage.setItem('projectcheck-theme', newTheme);

            // Dispatch theme change event
            window.dispatchEvent(new CustomEvent('theme-changed', {
                detail: {
                    theme: newTheme
                }
            }));
        });

        // Initialize theme from localStorage
        const savedTheme = localStorage.getItem('projectcheck-theme');
        if (savedTheme) {
            document.documentElement.setAttribute('data-theme', savedTheme);
        }
    });
</script>