<?php

declare(strict_types=1);

namespace OCA\ProjectCheck\Util;

use OCP\IUser;
use OCP\IGroupManager;
use OCP\IURLGenerator;
use OCP\IL10N;
use OCP\IAppManager;
use Psr\Log\LoggerInterface;

/**
 * MenuBarHelper Class
 * Generates menu content dynamically based on current page and user permissions
 */
class MenuBarHelper
{

    /** @var IUser */
    private $user;

    /** @var IGroupManager */
    private $groupManager;

    /** @var IURLGenerator */
    private $urlGenerator;

    /** @var IL10N */
    private $l10n;

    /** @var string */
    private $currentPage;

    /** @var string */
    private $currentApp;

    /** @var LoggerInterface */
    private $logger;

    /** @var IAppManager */
    private $appManager;

    /**
     * Constructor
     *
     * @param IUser $user
     * @param IGroupManager $groupManager
     * @param IURLGenerator $urlGenerator
     * @param IL10N $l10n
     * @param LoggerInterface $logger
     * @param IAppManager $appManager
     */
    public function __construct(
        IUser $user,
        IGroupManager $groupManager,
        IURLGenerator $urlGenerator,
        IL10N $l10n,
        LoggerInterface $logger,
        IAppManager $appManager
    ) {
        $this->user = $user;
        $this->groupManager = $groupManager;
        $this->urlGenerator = $urlGenerator;
        $this->l10n = $l10n;
        $this->logger = $logger;
        $this->appManager = $appManager;
        $this->currentPage = $this->getCurrentPage();
        $this->currentApp = $this->getCurrentApp();
    }

    /**
     * Generate the complete menu bar HTML
     *
     * @return string
     */
    public function generateMenuBar(): string
    {
        try {
            $html = '<nav class="menu-bar" id="menu-bar" role="navigation" aria-label="' . $this->l10n->t('Main navigation') . '">';

            $html .= $this->generateTopSection();
            $html .= $this->generateMiddleSection();
            $html .= $this->generateBottomSection();

            $html .= '</nav>';

            return $html;
        } catch (\Exception $e) {
            $this->logger->error('Error generating menu bar: ' . $e->getMessage(), [ 'app' => 'projectcheck' ]);
            return $this->generateFallbackMenu();
        }
    }

    /**
     * Generate top section (logo and branding)
     *
     * @return string
     */
    public function generateTopSection(): string
    {
        $logoPath = $this->appManager->getAppPath('projectcheck') . '/img/logo.svg';
        $logoUrl = $this->urlGenerator->linkTo('projectcheck', 'img/logo.svg');

        $html = '<div class="menu-bar-top">';
        $html .= '<div class="logo">';

        if (file_exists($logoPath)) {
            $html .= '<img src="' . $logoUrl . '" alt="' . $this->l10n->t('Project Control') . '" />';
        } else {
            $html .= '<div class="logo-placeholder">';
            $html .= '<span class="icon icon-folder"></span>';
            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '<h1 class="brand-name">' . $this->l10n->t('Project Control') . '</h1>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Generate middle section (main navigation)
     *
     * @return string
     */
    public function generateMiddleSection(): string
    {
        $html = '<div class="menu-bar-middle">';

        // Dashboard Section
        $html .= $this->generateDashboardSection();

        // Projects Section
        $html .= $this->generateProjectsSection();

        // Customers Section
        $html .= $this->generateCustomersSection();

        // Time Tracking Section
        $html .= $this->generateTimeTrackingSection();

        // Reports Section (if user has permission)
        if ($this->hasPermission('view_reports')) {
            $html .= $this->generateReportsSection();
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Generate bottom section (user utilities and settings)
     *
     * @return string
     */
    public function generateBottomSection(): string
    {
        $html = '<div class="menu-bar-bottom">';

        // User Information
        $html .= $this->generateUserInfo();

        // Settings and Utilities
        $html .= $this->generateSettingsSection();

        // Logout
        $html .= $this->generateLogoutSection();

        $html .= '</div>';

        return $html;
    }

    /**
     * Generate dashboard section
     *
     * @return string
     */
    private function generateDashboardSection(): string
    {
        $isActive = ($this->currentPage === 'dashboard');

        $html = '<div class="menu-group">';
        $html .= '<div class="menu-section-header">' . $this->l10n->t('Overview') . '</div>';

        $html .= '<a href="' . $this->urlGenerator->linkToRoute('projectcheck.page.index') . '" ';
        $html .= 'class="menu-item' . ($isActive ? ' active' : '') . '" ';
        $html .= 'data-icon="dashboard" ';
        $html .= 'aria-current="' . ($isActive ? 'page' : 'false') . '">';
        $html .= '<span class="icon"></span>';
        $html .= '<span class="text">' . $this->l10n->t('Dashboard') . '</span>';
        $html .= '</a>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Generate projects section
     *
     * @return string
     */
    private function generateProjectsSection(): string
    {
        $isActive = ($this->currentPage === 'projects');
        $isFormActive = ($this->currentPage === 'project-form');

        $html = '<div class="menu-group">';
        $html .= '<div class="menu-section-header">' . $this->l10n->t('Projects') . '</div>';

        // All Projects
        $html .= '<a href="' . $this->urlGenerator->linkToRoute('projectcheck.page.projects') . '" ';
        $html .= 'class="menu-item' . ($isActive ? ' active' : '') . '" ';
        $html .= 'data-icon="projects" ';
        $html .= 'aria-current="' . ($isActive ? 'page' : 'false') . '">';
        $html .= '<span class="icon"></span>';
        $html .= '<span class="text">' . $this->l10n->t('All Projects') . '</span>';
        $html .= '</a>';

        // New Project (if user has permission)
        if ($this->hasPermission('manage_projects')) {
            $html .= '<a href="' . $this->urlGenerator->linkToRoute('projectcheck.page.project_form') . '" ';
            $html .= 'class="menu-item' . ($isFormActive ? ' active' : '') . '" ';
            $html .= 'data-icon="folder" ';
            $html .= 'aria-current="' . ($isFormActive ? 'page' : 'false') . '">';
            $html .= '<span class="icon"></span>';
            $html .= '<span class="text">' . $this->l10n->t('New Project') . '</span>';
            $html .= '</a>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Generate customers section
     *
     * @return string
     */
    private function generateCustomersSection(): string
    {
        $isActive = ($this->currentPage === 'customers');
        $isFormActive = ($this->currentPage === 'customer-form');

        $html = '<div class="menu-group">';
        $html .= '<div class="menu-section-header">' . $this->l10n->t('Customers') . '</div>';

        // All Customers
        $html .= '<a href="' . $this->urlGenerator->linkToRoute('projectcheck.page.customers') . '" ';
        $html .= 'class="menu-item' . ($isActive ? ' active' : '') . '" ';
        $html .= 'data-icon="customers" ';
        $html .= 'aria-current="' . ($isActive ? 'page' : 'false') . '">';
        $html .= '<span class="icon"></span>';
        $html .= '<span class="text">' . $this->l10n->t('All Customers') . '</span>';
        $html .= '</a>';

        // New Customer (if user has permission)
        if ($this->hasPermission('manage_customers')) {
            $html .= '<a href="' . $this->urlGenerator->linkToRoute('projectcheck.page.customer_form') . '" ';
            $html .= 'class="menu-item' . ($isFormActive ? ' active' : '') . '" ';
            $html .= 'data-icon="users" ';
            $html .= 'aria-current="' . ($isFormActive ? 'page' : 'false') . '">';
            $html .= '<span class="icon"></span>';
            $html .= '<span class="text">' . $this->l10n->t('New Customer') . '</span>';
            $html .= '</a>';
        }

        $html .= '</div>';

        return $html;
    }

    /**
     * Generate time tracking section
     *
     * @return string
     */
    private function generateTimeTrackingSection(): string
    {
        $isActive = ($this->currentPage === 'time-entries');
        $isFormActive = ($this->currentPage === 'time-entry-form');

        $html = '<div class="menu-group">';
        $html .= '<div class="menu-section-header">' . $this->l10n->t('Time Tracking') . '</div>';

        // Time Entries
        $html .= '<a href="' . $this->urlGenerator->linkToRoute('projectcheck.page.time_entries') . '" ';
        $html .= 'class="menu-item' . ($isActive ? ' active' : '') . '" ';
        $html .= 'data-icon="time" ';
        $html .= 'aria-current="' . ($isActive ? 'page' : 'false') . '">';
        $html .= '<span class="icon"></span>';
        $html .= '<span class="text">' . $this->l10n->t('Time Entries') . '</span>';
        $html .= '</a>';

        // New Time Entry
        $html .= '<a href="' . $this->urlGenerator->linkToRoute('projectcheck.page.time_entry_form') . '" ';
        $html .= 'class="menu-item' . ($isFormActive ? ' active' : '') . '" ';
        $html .= 'data-icon="calendar" ';
        $html .= 'aria-current="' . ($isFormActive ? 'page' : 'false') . '">';
        $html .= '<span class="icon"></span>';
        $html .= '<span class="text">' . $this->l10n->t('New Time Entry') . '</span>';
        $html .= '</a>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Generate reports section
     *
     * @return string
     */
    private function generateReportsSection(): string
    {
        $isActive = ($this->currentPage === 'reports');
        $isAnalyticsActive = ($this->currentPage === 'analytics');

        $html = '<div class="menu-group">';
        $html .= '<div class="menu-section-header">' . $this->l10n->t('Reports') . '</div>';

        // Reports
        $html .= '<a href="' . $this->urlGenerator->linkToRoute('projectcheck.page.reports') . '" ';
        $html .= 'class="menu-item' . ($isActive ? ' active' : '') . '" ';
        $html .= 'data-icon="reports" ';
        $html .= 'aria-current="' . ($isActive ? 'page' : 'false') . '">';
        $html .= '<span class="icon"></span>';
        $html .= '<span class="text">' . $this->l10n->t('Reports') . '</span>';
        $html .= '</a>';

        // Analytics
        $html .= '<a href="' . $this->urlGenerator->linkToRoute('projectcheck.page.analytics') . '" ';
        $html .= 'class="menu-item' . ($isAnalyticsActive ? ' active' : '') . '" ';
        $html .= 'data-icon="chart" ';
        $html .= 'aria-current="' . ($isAnalyticsActive ? 'page' : 'false') . '">';
        $html .= '<span class="icon"></span>';
        $html .= '<span class="text">' . $this->l10n->t('Analytics') . '</span>';
        $html .= '</a>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Generate user information
     *
     * @return string
     */
    private function generateUserInfo(): string
    {
        $displayName = $this->user->getDisplayName();
        $firstLetter = strtoupper(substr($displayName, 0, 1));
        $isAdmin = $this->groupManager->isInGroup($this->user->getUID(), 'admin');

        $html = '<div class="user-info">';
        $html .= '<div class="user-avatar">' . $firstLetter . '</div>';
        $html .= '<div class="user-details">';
        $html .= '<div class="user-name">' . htmlspecialchars($displayName) . '</div>';
        $html .= '<div class="user-role">' . ($isAdmin ? $this->l10n->t('Administrator') : $this->l10n->t('User')) . '</div>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Generate settings section
     *
     * @return string
     */
    private function generateSettingsSection(): string
    {
        $isSettingsActive = ($this->currentPage === 'settings');
        $isAdminSettingsActive = ($this->currentPage === 'admin-settings');
        $isHelpActive = ($this->currentPage === 'help');
        $isAboutActive = ($this->currentPage === 'about');
        $isAdmin = $this->groupManager->isInGroup($this->user->getUID(), 'admin');

        $html = '<div class="menu-group">';

        // Settings
        $html .= '<a href="' . $this->urlGenerator->linkToRoute('projectcheck.page.settings') . '" ';
        $html .= 'class="menu-item' . ($isSettingsActive ? ' active' : '') . '" ';
        $html .= 'data-icon="settings" ';
        $html .= 'aria-current="' . ($isSettingsActive ? 'page' : 'false') . '">';
        $html .= '<span class="icon"></span>';
        $html .= '<span class="text">' . $this->l10n->t('Settings') . '</span>';
        $html .= '</a>';

        // Admin Settings (if user is admin)
        if ($isAdmin) {
            $html .= '<a href="' . $this->urlGenerator->linkToRoute('projectcheck.page.admin_settings') . '" ';
            $html .= 'class="menu-item' . ($isAdminSettingsActive ? ' active' : '') . '" ';
            $html .= 'data-icon="gear" ';
            $html .= 'aria-current="' . ($isAdminSettingsActive ? 'page' : 'false') . '">';
            $html .= '<span class="icon"></span>';
            $html .= '<span class="text">' . $this->l10n->t('Admin Settings') . '</span>';
            $html .= '</a>';
        }

        // Help
        $html .= '<a href="' . $this->urlGenerator->linkToRoute('projectcheck.page.help') . '" ';
        $html .= 'class="menu-item' . ($isHelpActive ? ' active' : '') . '" ';
        $html .= 'data-icon="help" ';
        $html .= 'aria-current="' . ($isHelpActive ? 'page' : 'false') . '">';
        $html .= '<span class="icon"></span>';
        $html .= '<span class="text">' . $this->l10n->t('Help') . '</span>';
        $html .= '</a>';

        // About
        $html .= '<a href="' . $this->urlGenerator->linkToRoute('projectcheck.page.about') . '" ';
        $html .= 'class="menu-item' . ($isAboutActive ? ' active' : '') . '" ';
        $html .= 'data-icon="info" ';
        $html .= 'aria-current="' . ($isAboutActive ? 'page' : 'false') . '">';
        $html .= '<span class="icon"></span>';
        $html .= '<span class="text">' . $this->l10n->t('About') . '</span>';
        $html .= '</a>';

        $html .= '</div>';

        return $html;
    }

    /**
     * Generate logout section
     *
     * @return string
     */
    private function generateLogoutSection(): string
    {
        $html = '<div class="menu-group">';
        $html .= '<a href="' . $this->urlGenerator->linkToRoute('core.login.logout') . '" ';
        $html .= 'class="menu-item" data-icon="logout">';
        $html .= '<span class="icon"></span>';
        $html .= '<span class="text">' . $this->l10n->t('Logout') . '</span>';
        $html .= '</a>';
        $html .= '</div>';

        return $html;
    }

    /**
     * Get current page identifier
     *
     * @return string
     */
    public function getCurrentPage(): string
    {
        $page = $_GET['page'] ?? 'dashboard';
        return $page;
    }

    /**
     * Get current app identifier
     *
     * @return string
     */
    public function getCurrentApp(): string
    {
        $app = $_GET['app'] ?? 'projectcheck';
        return $app;
    }

    /**
     * Check if user has specific permission
     *
     * @param string $permission
     * @return bool
     */
    public function hasPermission(string $permission): bool
    {
        $uid = $this->user->getUID();

        switch ($permission) {
            case 'manage_projects':
                return $this->groupManager->isInGroup($uid, 'admin') ||
                    $this->groupManager->isInGroup($uid, 'project_manager');

            case 'manage_customers':
                return $this->groupManager->isInGroup($uid, 'admin') ||
                    $this->groupManager->isInGroup($uid, 'customer_manager');

            case 'view_reports':
                return $this->groupManager->isInGroup($uid, 'admin') ||
                    $this->groupManager->isInGroup($uid, 'reports_viewer');

            case 'admin':
                return $this->groupManager->isInGroup($uid, 'admin');

            default:
                return true; // Default to allowing access
        }
    }

    /**
     * Generate fallback menu when errors occur
     *
     * @return string
     */
    private function generateFallbackMenu(): string
    {
        $html = '<nav class="menu-bar" id="menu-bar" role="navigation">';
        $html .= '<div class="menu-bar-top">';
        $html .= '<h1 class="brand-name">' . $this->l10n->t('Project Control') . '</h1>';
        $html .= '</div>';
        $html .= '<div class="menu-bar-middle">';
        $html .= '<div class="menu-group">';
        $html .= '<a href="' . $this->urlGenerator->linkToRoute('projectcheck.page.index') . '" class="menu-item">';
        $html .= '<span class="icon"></span>';
        $html .= '<span class="text">' . $this->l10n->t('Dashboard') . '</span>';
        $html .= '</a>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '<div class="menu-bar-bottom">';
        $html .= '<div class="menu-group">';
        $html .= '<a href="' . $this->urlGenerator->linkToRoute('core.login.logout') . '" class="menu-item">';
        $html .= '<span class="icon"></span>';
        $html .= '<span class="text">' . $this->l10n->t('Logout') . '</span>';
        $html .= '</a>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</nav>';

        return $html;
    }
}

