/**
 * Layout Management and Responsive Utilities for ProjectControl App
 * Handles responsive behavior, layout adjustments, and viewport management
 */

const ProjectControlLayout = {
  /**
   * Initialize the layout system
   */
  init() {
    this.setupResponsiveHandlers();
    this.setupScrollHandlers();
    this.setupResizeHandlers();
    this.setupViewportHandlers();
    this.setupStickyHeaders();
    this.setupSidebarToggles();
    this.setupMobileNavigation();
    this.setupBackToTop();
  },

  /**
   * Setup responsive breakpoint handlers
   */
  setupResponsiveHandlers() {
    const breakpoints = {
      sm: 640,
      md: 768,
      lg: 1024,
      xl: 1280,
      '2xl': 1536
    };

    // Track current breakpoint
    this.currentBreakpoint = this.getCurrentBreakpoint(breakpoints);
    
    // Update breakpoint on resize
    let resizeTimeout;
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimeout);
      resizeTimeout = setTimeout(() => {
        const newBreakpoint = this.getCurrentBreakpoint(breakpoints);
        if (newBreakpoint !== this.currentBreakpoint) {
          this.currentBreakpoint = newBreakpoint;
          this.handleBreakpointChange(newBreakpoint);
        }
      }, 100);
    });

    // Dispatch initial breakpoint event
    this.handleBreakpointChange(this.currentBreakpoint);
  },

  /**
   * Get current breakpoint based on window width
   */
  getCurrentBreakpoint(breakpoints) {
    const width = window.innerWidth;
    let current = 'xs';
    
    for (const [name, minWidth] of Object.entries(breakpoints)) {
      if (width >= minWidth) {
        current = name;
      }
    }
    
    return current;
  },

  /**
   * Handle breakpoint changes
   */
  handleBreakpointChange(breakpoint) {
    // Update body class
    document.body.className = document.body.className.replace(/breakpoint-\w+/g, '');
    document.body.classList.add(`breakpoint-${breakpoint}`);
    
    // Dispatch app-scoped custom event
    window.dispatchEvent(new CustomEvent('projectcheck:breakpoint-change', {
      detail: { breakpoint }
    }));
    
    // Handle specific breakpoint logic
    if (breakpoint === 'lg' || breakpoint === 'xl' || breakpoint === '2xl') {
      this.showSidebar();
    } else {
      this.hideSidebar();
    }
  },

  /**
   * Setup scroll event handlers
   */
  setupScrollHandlers() {
    let scrollTimeout;
    
    window.addEventListener('scroll', () => {
      clearTimeout(scrollTimeout);
      
      // Dispatch app-scoped custom event (avoid re-firing native "scroll")
      window.dispatchEvent(new CustomEvent('projectcheck:scroll', {
        detail: { 
          scrollY: window.scrollY,
          scrollX: window.scrollX 
        }
      }));
      
      // Handle scroll-based UI updates
      this.updateScrollBasedUI();
      
      // Debounced scroll end event
      scrollTimeout = setTimeout(() => {
        window.dispatchEvent(new CustomEvent('projectcheck:scroll-end'));
      }, 150);
    });
  },

  /**
   * Update UI elements based on scroll position
   */
  updateScrollBasedUI() {
    const scrollY = window.scrollY;
    const header = document.querySelector('.header');
    const backToTop = document.querySelector('.footer__back-to-top');
    
    // Header scroll behavior
    if (header) {
      if (scrollY > 100) {
        header.classList.add('header--scrolled');
      } else {
        header.classList.remove('header--scrolled');
      }
    }
    
    // Back to top button
    if (backToTop) {
      if (scrollY > 300) {
        backToTop.classList.add('footer__back-to-top--visible');
      } else {
        backToTop.classList.remove('footer__back-to-top--visible');
      }
    }
  },

  /**
   * Setup resize event handlers
   */
  setupResizeHandlers() {
    let resizeTimeout;
    
    window.addEventListener('resize', () => {
      clearTimeout(resizeTimeout);
      
      resizeTimeout = setTimeout(() => {
        this.handleResize();
      }, 100);
    });
  },

  /**
   * Handle window resize
   */
  handleResize() {
    const width = window.innerWidth;
    const height = window.innerHeight;
    
    // Update CSS custom properties
    document.documentElement.style.setProperty('--viewport-width', `${width}px`);
    document.documentElement.style.setProperty('--viewport-height', `${height}px`);
    
    // Dispatch app-scoped custom event (avoid re-firing native "resize")
    window.dispatchEvent(new CustomEvent('projectcheck:resize', {
      detail: { width, height }
    }));
    
    // Handle mobile navigation
    if (width >= 768) {
      this.closeMobileNavigation();
    }
  },

  /**
   * Setup viewport handlers
   */
  setupViewportHandlers() {
    // Handle viewport orientation changes
    window.addEventListener('orientationchange', () => {
      setTimeout(() => {
        this.handleResize();
      }, 100);
    });
    
    // Handle fullscreen changes
    document.addEventListener('fullscreenchange', () => {
      this.handleFullscreenChange();
    });
  },

  /**
   * Handle fullscreen changes
   */
  handleFullscreenChange() {
    const isFullscreen = !!document.fullscreenElement;
    
    if (isFullscreen) {
      document.body.classList.add('fullscreen');
    } else {
      document.body.classList.remove('fullscreen');
    }
    
    // Dispatch fullscreen event
    window.dispatchEvent(new CustomEvent('fullscreen-change', {
      detail: { isFullscreen }
    }));
  },

  /**
   * Setup sticky headers
   */
  setupStickyHeaders() {
    const stickyHeaders = document.querySelectorAll('[data-sticky]');
    
    stickyHeaders.forEach(header => {
      const offset = header.dataset.stickyOffset || 0;
      const scrollContainer = header.dataset.stickyContainer ? 
        document.querySelector(header.dataset.stickyContainer) : window;
      
      const handleScroll = () => {
        const scrollTop = scrollContainer === window ? 
          window.scrollY : scrollContainer.scrollTop;
        
        if (scrollTop > offset) {
          header.classList.add('sticky--active');
        } else {
          header.classList.remove('sticky--active');
        }
      };
      
      if (scrollContainer === window) {
        window.addEventListener('scroll', handleScroll);
      } else {
        scrollContainer.addEventListener('scroll', handleScroll);
      }
    });
  },

  /**
   * Setup sidebar toggles
   */
  setupSidebarToggles() {
    const sidebarToggles = document.querySelectorAll('[data-sidebar-toggle]');
    
    sidebarToggles.forEach(toggle => {
      toggle.addEventListener('click', (e) => {
        e.preventDefault();
        this.toggleSidebar();
      });
    });
    
    // Close sidebar when clicking outside
    document.addEventListener('click', (e) => {
      const sidebar = document.querySelector('.content-sidebar');
      const isClickInsideSidebar = sidebar && sidebar.contains(e.target);
      const isClickOnToggle = Array.from(sidebarToggles).some(toggle => 
        toggle.contains(e.target)
      );
      
      if (!isClickInsideSidebar && !isClickOnToggle && this.isSidebarOpen()) {
        this.hideSidebar();
      }
    });
  },

  /**
   * Toggle sidebar visibility
   */
  toggleSidebar() {
    const sidebar = document.querySelector('.content-sidebar');
    if (!sidebar) return;
    
    if (this.isSidebarOpen()) {
      this.hideSidebar();
    } else {
      this.showSidebar();
    }
  },

  /**
   * Show sidebar
   */
  showSidebar() {
    const sidebar = document.querySelector('.content-sidebar');
    if (!sidebar) return;
    
    sidebar.classList.add('content-sidebar--open');
    document.body.classList.add('sidebar-open');
    
    // Dispatch event
    window.dispatchEvent(new CustomEvent('sidebar-show'));
  },

  /**
   * Hide sidebar
   */
  hideSidebar() {
    const sidebar = document.querySelector('.content-sidebar');
    if (!sidebar) return;
    
    sidebar.classList.remove('content-sidebar--open');
    document.body.classList.remove('sidebar-open');
    
    // Dispatch event
    window.dispatchEvent(new CustomEvent('sidebar-hide'));
  },

  /**
   * Check if sidebar is open
   */
  isSidebarOpen() {
    const sidebar = document.querySelector('.content-sidebar');
    return sidebar && sidebar.classList.contains('content-sidebar--open');
  },

  /**
   * Setup mobile navigation
   */
  setupMobileNavigation() {
    const mobileToggle = document.querySelector('.header__mobile-toggle');
    const mobileNav = document.querySelector('.header__mobile-nav');
    
    if (mobileToggle && mobileNav) {
      mobileToggle.addEventListener('click', () => {
        this.toggleMobileNavigation();
      });
      
      // Close mobile nav when clicking on a link
      const mobileLinks = mobileNav.querySelectorAll('a');
      mobileLinks.forEach(link => {
        link.addEventListener('click', () => {
          this.closeMobileNavigation();
        });
      });
      
      // Close mobile nav when clicking outside
      document.addEventListener('click', (e) => {
        const isClickInsideNav = mobileNav.contains(e.target);
        const isClickOnToggle = mobileToggle.contains(e.target);
        
        if (!isClickInsideNav && !isClickOnToggle && this.isMobileNavigationOpen()) {
          this.closeMobileNavigation();
        }
      });
    }
  },

  /**
   * Toggle mobile navigation
   */
  toggleMobileNavigation() {
    const mobileToggle = document.querySelector('.header__mobile-toggle');
    const mobileNav = document.querySelector('.header__mobile-nav');
    
    if (!mobileToggle || !mobileNav) return;
    
    const isOpen = this.isMobileNavigationOpen();
    
    if (isOpen) {
      this.closeMobileNavigation();
    } else {
      this.openMobileNavigation();
    }
  },

  /**
   * Open mobile navigation
   */
  openMobileNavigation() {
    const mobileToggle = document.querySelector('.header__mobile-toggle');
    const mobileNav = document.querySelector('.header__mobile-nav');
    
    if (!mobileToggle || !mobileNav) return;
    
    mobileToggle.setAttribute('aria-expanded', 'true');
    mobileNav.style.display = 'block';
    document.body.classList.add('mobile-nav-open');
    
    // Dispatch event
    window.dispatchEvent(new CustomEvent('mobile-nav-open'));
  },

  /**
   * Close mobile navigation
   */
  closeMobileNavigation() {
    const mobileToggle = document.querySelector('.header__mobile-toggle');
    const mobileNav = document.querySelector('.header__mobile-nav');
    
    if (!mobileToggle || !mobileNav) return;
    
    mobileToggle.setAttribute('aria-expanded', 'false');
    mobileNav.style.display = 'none';
    document.body.classList.remove('mobile-nav-open');
    
    // Dispatch event
    window.dispatchEvent(new CustomEvent('mobile-nav-close'));
  },

  /**
   * Check if mobile navigation is open
   */
  isMobileNavigationOpen() {
    const mobileToggle = document.querySelector('.header__mobile-toggle');
    return mobileToggle && mobileToggle.getAttribute('aria-expanded') === 'true';
  },

  /**
   * Setup back to top functionality
   */
  setupBackToTop() {
    const backToTop = document.querySelector('.footer__back-to-top');
    
    if (backToTop) {
      backToTop.addEventListener('click', () => {
        this.scrollToTop();
      });
    }
  },

  /**
   * Smooth scroll to top
   */
  scrollToTop() {
    window.scrollTo({
      top: 0,
      behavior: 'smooth'
    });
  },

  /**
   * Get current viewport dimensions
   */
  getViewportDimensions() {
    return {
      width: window.innerWidth,
      height: window.innerHeight
    };
  },

  /**
   * Check if element is in viewport
   */
  isInViewport(element) {
    if (!element) return false;
    
    const rect = element.getBoundingClientRect();
    const viewport = this.getViewportDimensions();
    
    return (
      rect.top >= 0 &&
      rect.left >= 0 &&
      rect.bottom <= viewport.height &&
      rect.right <= viewport.width
    );
  },

  /**
   * Scroll element into view
   */
  scrollIntoView(element, options = {}) {
    if (!element) return;
    
    const defaultOptions = {
      behavior: 'smooth',
      block: 'start',
      inline: 'nearest'
    };
    
    element.scrollIntoView({ ...defaultOptions, ...options });
  },

  /**
   * Get scroll position
   */
  getScrollPosition() {
    return {
      x: window.scrollX,
      y: window.scrollY
    };
  },

  /**
   * Set scroll position
   */
  setScrollPosition(x, y) {
    window.scrollTo(x, y);
  },

  /**
   * Lock body scroll (for modals)
   */
  lockBodyScroll() {
    const scrollY = window.scrollY;
    document.body.style.position = 'fixed';
    document.body.style.top = `-${scrollY}px`;
    document.body.style.width = '100%';
    document.body.dataset.scrollY = scrollY;
  },

  /**
   * Unlock body scroll
   */
  unlockBodyScroll() {
    const scrollY = document.body.dataset.scrollY;
    document.body.style.position = '';
    document.body.style.top = '';
    document.body.style.width = '';
    delete document.body.dataset.scrollY;
    
    if (scrollY) {
      window.scrollTo(0, parseInt(scrollY));
    }
  },

  /**
   * Get element dimensions
   */
  getElementDimensions(element) {
    if (!element) return null;
    
    const rect = element.getBoundingClientRect();
    return {
      width: rect.width,
      height: rect.height,
      top: rect.top,
      left: rect.left,
      bottom: rect.bottom,
      right: rect.right
    };
  },

  /**
   * Debounce function
   */
  debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  },

  /**
   * Throttle function
   */
  throttle(func, limit) {
    let inThrottle;
    return function() {
      const args = arguments;
      const context = this;
      if (!inThrottle) {
        func.apply(context, args);
        inThrottle = true;
        setTimeout(() => inThrottle = false, limit);
      }
    };
  }
};

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
  module.exports = ProjectControlLayout;
} else if (typeof window !== 'undefined') {
  window.ProjectCheckLayout = ProjectControlLayout;
  window.ProjectControlLayout = ProjectControlLayout;
}
