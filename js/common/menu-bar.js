/**
 * Menu Bar JavaScript
 * Handles menu interactions, mobile toggle, and accessibility features
 */

(function () {
    'use strict';

    // Menu bar elements
    let menuBar;
    let menuBarToggle;
    let menuBarOverlay;
    let skipNavigationLink;
    let isMobileMenuOpen = false;

    // Initialize menu bar functionality
    function init() {
        try {
            // Get DOM elements
            menuBar = document.getElementById('menu-bar');
            menuBarToggle = document.getElementById('menu-bar-toggle');
            menuBarOverlay = document.getElementById('menu-bar-overlay');
            skipNavigationLink = document.getElementById('skip-navigation');

            // Check if elements exist
            if (!menuBar || !menuBarToggle) {
                return;
            }

            // Initialize event listeners
            initEventListeners();

            // Set initial state
            setInitialState();

            // Initialize accessibility features
            initAccessibility();
        } catch (error) {
            if (typeof console !== 'undefined' && console.error) {
                console.error('Error initializing menu bar:', error);
            }
        }
    }

    /**
     * Initialize all event listeners
     */
    function initEventListeners() {
        // Mobile menu toggle
        if (menuBarToggle) {
            menuBarToggle.addEventListener('click', toggleMobileMenu);
            menuBarToggle.addEventListener('keydown', handleToggleKeydown);
        }

        // Overlay click to close mobile menu
        if (menuBarOverlay) {
            menuBarOverlay.addEventListener('click', closeMobileMenu);
        }

        // Menu item interactions
        const menuItems = menuBar.querySelectorAll('.menu-item');
        menuItems.forEach(item => {
            item.addEventListener('click', handleMenuItemClick);
            item.addEventListener('keydown', handleMenuItemKeydown);
            item.addEventListener('focus', handleMenuItemFocus);
            item.addEventListener('blur', handleMenuItemBlur);
        });

        // Window resize handling
        window.addEventListener('resize', handleWindowResize);

        // Escape key to close mobile menu
        document.addEventListener('keydown', handleDocumentKeydown);

        // Skip navigation link
        if (skipNavigationLink) {
            skipNavigationLink.addEventListener('click', handleSkipNavigation);
        }
    }

    /**
     * Set initial state based on screen size
     */
    function setInitialState() {
        const isMobile = window.innerWidth <= 768;

        if (isMobile) {
            menuBar.classList.remove('mobile-open');
            if (menuBarOverlay) {
                menuBarOverlay.classList.remove('mobile-open');
            }
            if (menuBarToggle) {
                menuBarToggle.classList.remove('mobile-open');
                menuBarToggle.setAttribute('aria-expanded', 'false');
            }
        }
    }

    /**
     * Initialize accessibility features
     */
    function initAccessibility() {
        // Set proper ARIA attributes
        if (menuBar) {
            menuBar.setAttribute('role', 'navigation');
            menuBar.setAttribute('aria-label', t('projectcheck', 'Main navigation'));
        }

        // Set focus management
        const firstMenuItem = menuBar.querySelector('.menu-item');
        if (firstMenuItem) {
            firstMenuItem.setAttribute('tabindex', '0');
        }

        // Add keyboard navigation support
        setupKeyboardNavigation();
    }

    /**
     * Toggle mobile menu
     */
    function toggleMobileMenu() {
        try {
            isMobileMenuOpen = !isMobileMenuOpen;

            if (isMobileMenuOpen) {
                openMobileMenu();
            } else {
                closeMobileMenu();
            }
        } catch (error) {
            console.error('Error toggling mobile menu:', error);
        }
    }

    /**
     * Open mobile menu
     */
    function openMobileMenu() {
        if (menuBar) {
            menuBar.classList.add('mobile-open');
        }

        if (menuBarOverlay) {
            menuBarOverlay.classList.add('mobile-open');
        }

        if (menuBarToggle) {
            menuBarToggle.classList.add('mobile-open');
            menuBarToggle.setAttribute('aria-expanded', 'true');
        }

        // Focus first menu item
        const firstMenuItem = menuBar.querySelector('.menu-item');
        if (firstMenuItem) {
            firstMenuItem.focus();
        }

        // Prevent body scroll
        document.body.style.overflow = 'hidden';
    }

    /**
     * Close mobile menu
     */
    function closeMobileMenu() {
        if (menuBar) {
            menuBar.classList.remove('mobile-open');
        }

        if (menuBarOverlay) {
            menuBarOverlay.classList.remove('mobile-open');
        }

        if (menuBarToggle) {
            menuBarToggle.classList.remove('mobile-open');
            menuBarToggle.setAttribute('aria-expanded', 'false');
        }

        // Restore body scroll
        document.body.style.overflow = '';

        // Focus hamburger button
        if (menuBarToggle) {
            menuBarToggle.focus();
        }
    }

    /**
     * Handle menu item click
     */
    function handleMenuItemClick(event) {
        const menuItem = event.currentTarget;

        // Add loading state if needed
        if (menuItem.dataset.loading === 'true') {
            menuItem.classList.add('loading');
            return;
        }

        // Set active state
        setActiveMenuItem(menuItem);

        // Close mobile menu if open
        if (isMobileMenuOpen) {
            closeMobileMenu();
        }
    }

    /**
     * Handle menu item keyboard events
     */
    function handleMenuItemKeydown(event) {
        const menuItem = event.currentTarget;

        switch (event.key) {
            case 'Enter':
            case ' ':
                event.preventDefault();
                menuItem.click();
                break;

            case 'ArrowDown':
                event.preventDefault();
                focusNextMenuItem(menuItem);
                break;

            case 'ArrowUp':
                event.preventDefault();
                focusPreviousMenuItem(menuItem);
                break;

            case 'Home':
                event.preventDefault();
                focusFirstMenuItem();
                break;

            case 'End':
                event.preventDefault();
                focusLastMenuItem();
                break;
        }
    }

    /**
     * Handle menu item focus
     */
    function handleMenuItemFocus(event) {
        const menuItem = event.currentTarget;
        menuItem.classList.add('focused');
    }

    /**
     * Handle menu item blur
     */
    function handleMenuItemBlur(event) {
        const menuItem = event.currentTarget;
        menuItem.classList.remove('focused');
    }

    /**
     * Handle toggle button keyboard events
     */
    function handleToggleKeydown(event) {
        if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            toggleMobileMenu();
        }
    }

    /**
     * Handle document keyboard events
     */
    function handleDocumentKeydown(event) {
        if (event.key === 'Escape' && isMobileMenuOpen) {
            closeMobileMenu();
        }
    }

    /**
     * Handle window resize
     */
    function handleWindowResize() {
        const isMobile = window.innerWidth <= 768;

        // Close mobile menu if switching to desktop
        if (!isMobile && isMobileMenuOpen) {
            closeMobileMenu();
        }
    }

    /**
     * Handle skip navigation
     */
    function handleSkipNavigation(event) {
        const mainContent = document.getElementById('main-content');
        if (mainContent) {
            event.preventDefault();
            mainContent.focus();
            mainContent.scrollIntoView({ behavior: 'smooth' });
        }
    }

    /**
     * Set active menu item
     */
    function setActiveMenuItem(activeItem) {
        // Remove active class from all menu items
        const menuItems = menuBar.querySelectorAll('.menu-item');
        menuItems.forEach(item => {
            item.classList.remove('active');
            item.setAttribute('aria-current', 'false');
        });

        // Add active class to clicked item
        if (activeItem) {
            activeItem.classList.add('active');
            activeItem.setAttribute('aria-current', 'page');
        }
    }

    /**
     * Setup keyboard navigation
     */
    function setupKeyboardNavigation() {
        const menuItems = Array.from(menuBar.querySelectorAll('.menu-item'));

        menuItems.forEach((item, index) => {
            item.setAttribute('tabindex', index === 0 ? '0' : '-1');
        });
    }

    /**
     * Focus next menu item
     */
    function focusNextMenuItem(currentItem) {
        const menuItems = Array.from(menuBar.querySelectorAll('.menu-item'));
        const currentIndex = menuItems.indexOf(currentItem);
        const nextIndex = (currentIndex + 1) % menuItems.length;

        menuItems[nextIndex].focus();
    }

    /**
     * Focus previous menu item
     */
    function focusPreviousMenuItem(currentItem) {
        const menuItems = Array.from(menuBar.querySelectorAll('.menu-item'));
        const currentIndex = menuItems.indexOf(currentItem);
        const previousIndex = currentIndex === 0 ? menuItems.length - 1 : currentIndex - 1;

        menuItems[previousIndex].focus();
    }

    /**
     * Focus first menu item
     */
    function focusFirstMenuItem() {
        const firstMenuItem = menuBar.querySelector('.menu-item');
        if (firstMenuItem) {
            firstMenuItem.focus();
        }
    }

    /**
     * Focus last menu item
     */
    function focusLastMenuItem() {
        const menuItems = menuBar.querySelectorAll('.menu-item');
        const lastMenuItem = menuItems[menuItems.length - 1];
        if (lastMenuItem) {
            lastMenuItem.focus();
        }
    }

    /**
     * Update menu item badge count
     */
    function updateMenuItemBadge(menuItemSelector, count) {
        const menuItem = menuBar.querySelector(menuItemSelector);
        if (menuItem) {
            let badge = menuItem.querySelector('.badge');

            if (count > 0) {
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'badge';
                    menuItem.appendChild(badge);
                }
                badge.textContent = count;
            } else if (badge) {
                badge.remove();
            }
        }
    }

    /**
     * Add loading state to menu item
     */
    function setMenuItemLoading(menuItemSelector, loading) {
        const menuItem = menuBar.querySelector(menuItemSelector);
        if (menuItem) {
            if (loading) {
                menuItem.classList.add('loading');
                menuItem.dataset.loading = 'true';
            } else {
                menuItem.classList.remove('loading');
                menuItem.dataset.loading = 'false';
            }
        }
    }

    /**
     * Enable/disable menu item
     */
    function setMenuItemEnabled(menuItemSelector, enabled) {
        const menuItem = menuBar.querySelector(menuItemSelector);
        if (menuItem) {
            if (enabled) {
                menuItem.classList.remove('disabled');
                menuItem.removeAttribute('disabled');
            } else {
                menuItem.classList.add('disabled');
                menuItem.setAttribute('disabled', 'disabled');
            }
        }
    }

    /**
     * Get current active menu item
     */
    function getActiveMenuItem() {
        return menuBar.querySelector('.menu-item.active');
    }

    /**
     * @returns {boolean} whether the mobile menu is open
     */
    function getIsMobileMenuOpen() {
        return isMobileMenuOpen;
    }

    // Public API
    window.MenuBar = {
        init: init,
        toggleMobileMenu: toggleMobileMenu,
        openMobileMenu: openMobileMenu,
        closeMobileMenu: closeMobileMenu,
        setActiveMenuItem: setActiveMenuItem,
        updateMenuItemBadge: updateMenuItemBadge,
        setMenuItemLoading: setMenuItemLoading,
        setMenuItemEnabled: setMenuItemEnabled,
        getActiveMenuItem: getActiveMenuItem,
        isMobileMenuOpen: getIsMobileMenuOpen
    };

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();

