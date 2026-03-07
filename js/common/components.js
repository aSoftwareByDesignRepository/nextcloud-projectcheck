/**
 * Reusable JavaScript Components for ProjectControl App
 * Provides modal, dropdown, and other interactive component functionality
 */

const ProjectControlComponents = {
  escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
  },

  /**
   * Initialize all components
   */
  init() {
    this.initModals();
    this.initDropdowns();
    this.initTooltips();
    this.initAlerts();
    this.initTabs();
    this.initAccordions();
    this.initCarousels();
  },

  // ===== MODAL COMPONENTS =====

  /**
   * Initialize modal functionality
   */
  initModals() {
    const modalTriggers = document.querySelectorAll('[data-modal]');
    
    modalTriggers.forEach(trigger => {
      trigger.addEventListener('click', (e) => {
        e.preventDefault();
        const modalId = trigger.dataset.modal;
        this.openModal(modalId);
      });
    });

    // Close modals on backdrop click
    document.addEventListener('click', (e) => {
      if (e.target.classList.contains('modal-backdrop')) {
        this.closeModal(e.target.querySelector('.modal'));
      }
    });

    // Close modals on escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        const openModal = document.querySelector('.modal-backdrop');
        if (openModal) {
          this.closeModal(openModal.querySelector('.modal'));
        }
      }
    });
  },

  /**
   * Open modal by ID
   */
  openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (!modal) return;

    // Create backdrop
    const backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop';
    backdrop.setAttribute('aria-hidden', 'true');

    // Move modal to backdrop
    backdrop.appendChild(modal);
    document.body.appendChild(backdrop);

    // Show modal
    backdrop.style.display = 'flex';
    modal.style.display = 'flex';
    modal.setAttribute('aria-hidden', 'false');

    // Lock body scroll
    if (window.ProjectControlLayout) {
      window.ProjectControlLayout.lockBodyScroll();
    }

    // Focus first focusable element
    this.focusFirstElement(modal);

    // Dispatch event
    window.dispatchEvent(new CustomEvent('modal-open', {
      detail: { modalId, modal }
    }));
  },

  /**
   * Close modal
   */
  closeModal(modal) {
    if (!modal) return;

    const backdrop = modal.closest('.modal-backdrop');
    if (!backdrop) return;

    // Hide modal
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
    backdrop.style.display = 'none';

    // Move modal back to original position
    const originalContainer = document.querySelector(`[data-modal-container="${modal.id}"]`) || document.body;
    originalContainer.appendChild(modal);

    // Remove backdrop
    backdrop.remove();

    // Unlock body scroll
    if (window.ProjectControlLayout) {
      window.ProjectControlLayout.unlockBodyScroll();
    }

    // Dispatch event
    window.dispatchEvent(new CustomEvent('modal-close', {
      detail: { modalId: modal.id, modal }
    }));
  },

  /**
   * Create modal dynamically
   */
  createModal(options = {}) {
    const {
      id = `modal-${Date.now()}`,
      title = '',
      content = '',
      size = 'md',
      closable = true,
      onOpen = null,
      onClose = null
    } = options;

    const modal = document.createElement('div');
    modal.className = `modal modal--${size}`;
    modal.id = id;
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.setAttribute('aria-hidden', 'true');

    modal.innerHTML = `
      <div class="modal-header">
        <h2 class="modal-title">${this.escapeHtml(title)}</h2>
        ${closable ? `<button type="button" class="modal-close" aria-label="${t('projectcheck', 'Close modal')}">&times;</button>` : ''}
      </div>
      <div class="modal-body">
        ${content}
      </div>
    `;

    // Add event listeners
    if (closable) {
      const closeBtn = modal.querySelector('.modal-close');
      closeBtn.addEventListener('click', () => {
        this.closeModal(modal);
        if (onClose) onClose();
      });
    }

    // Store callbacks
    modal.dataset.onOpen = onOpen ? onOpen.toString() : '';
    modal.dataset.onClose = onClose ? onClose.toString() : '';

    return modal;
  },

  // ===== DROPDOWN COMPONENTS =====

  /**
   * Initialize dropdown functionality
   */
  initDropdowns() {
    const dropdowns = document.querySelectorAll('[data-dropdown]');
    
    dropdowns.forEach(dropdown => {
      const toggle = dropdown.querySelector('[data-dropdown-toggle]');
      const menu = dropdown.querySelector('[data-dropdown-menu]');
      
      if (toggle && menu) {
        toggle.addEventListener('click', (e) => {
          e.preventDefault();
          this.toggleDropdown(dropdown);
        });
      }
    });

    // Close dropdowns when clicking outside
    document.addEventListener('click', (e) => {
      const openDropdowns = document.querySelectorAll('[data-dropdown].dropdown--open');
      
      openDropdowns.forEach(dropdown => {
        if (!dropdown.contains(e.target)) {
          this.closeDropdown(dropdown);
        }
      });
    });

    // Close dropdowns on escape key
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        const openDropdowns = document.querySelectorAll('[data-dropdown].dropdown--open');
        openDropdowns.forEach(dropdown => this.closeDropdown(dropdown));
      }
    });
  },

  /**
   * Toggle dropdown
   */
  toggleDropdown(dropdown) {
    if (dropdown.classList.contains('dropdown--open')) {
      this.closeDropdown(dropdown);
    } else {
      this.openDropdown(dropdown);
    }
  },

  /**
   * Open dropdown
   */
  openDropdown(dropdown) {
    // Close other dropdowns
    const openDropdowns = document.querySelectorAll('[data-dropdown].dropdown--open');
    openDropdowns.forEach(d => this.closeDropdown(d));

    // Open this dropdown
    dropdown.classList.add('dropdown--open');
    const toggle = dropdown.querySelector('[data-dropdown-toggle]');
    if (toggle) {
      toggle.setAttribute('aria-expanded', 'true');
    }

    // Focus first menu item
    const firstItem = dropdown.querySelector('[data-dropdown-menu] a, [data-dropdown-menu] button');
    if (firstItem) {
      firstItem.focus();
    }
  },

  /**
   * Close dropdown
   */
  closeDropdown(dropdown) {
    dropdown.classList.remove('dropdown--open');
    const toggle = dropdown.querySelector('[data-dropdown-toggle]');
    if (toggle) {
      toggle.setAttribute('aria-expanded', 'false');
      toggle.focus();
    }
  },

  // ===== TOOLTIP COMPONENTS =====

  /**
   * Initialize tooltip functionality
   */
  initTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    
    tooltips.forEach(element => {
      element.addEventListener('mouseenter', () => {
        this.showTooltip(element);
      });
      
      element.addEventListener('mouseleave', () => {
        this.hideTooltip(element);
      });
      
      element.addEventListener('focus', () => {
        this.showTooltip(element);
      });
      
      element.addEventListener('blur', () => {
        this.hideTooltip(element);
      });
    });
  },

  /**
   * Show tooltip
   */
  showTooltip(element) {
    const text = element.dataset.tooltip;
    if (!text) return;

    // Remove existing tooltip
    this.hideTooltip(element);

    // Create tooltip
    const tooltip = document.createElement('div');
    tooltip.className = 'tooltip-popup';
    tooltip.textContent = text;
    tooltip.setAttribute('role', 'tooltip');

    // Position tooltip
    const rect = element.getBoundingClientRect();
    tooltip.style.position = 'absolute';
    tooltip.style.top = `${rect.top - 40}px`;
    tooltip.style.left = `${rect.left + rect.width / 2}px`;
    tooltip.style.transform = 'translateX(-50%)';

    // Add to DOM
    document.body.appendChild(tooltip);
    element.dataset.tooltipElement = tooltip.id = `tooltip-${Date.now()}`;
  },

  /**
   * Hide tooltip
   */
  hideTooltip(element) {
    const tooltipId = element.dataset.tooltipElement;
    if (tooltipId) {
      const tooltip = document.getElementById(tooltipId);
      if (tooltip) {
        tooltip.remove();
      }
      delete element.dataset.tooltipElement;
    }
  },

  // ===== ALERT COMPONENTS =====

  /**
   * Initialize alert functionality
   */
  initAlerts() {
    const alerts = document.querySelectorAll('.alert');
    
    alerts.forEach(alert => {
      // Setup dismissible alerts
      const closeBtn = alert.querySelector('.alert-close');
      if (closeBtn) {
        closeBtn.addEventListener('click', () => {
          this.dismissAlert(alert);
        });
      }

      // Auto-dismiss alerts
      const autoDismiss = alert.dataset.autoDismiss;
      if (autoDismiss) {
        setTimeout(() => {
          this.dismissAlert(alert);
        }, parseInt(autoDismiss));
      }
    });
  },

  /**
   * Dismiss alert
   */
  dismissAlert(alert) {
    alert.style.opacity = '0';
    alert.style.transform = 'translateY(-10px)';
    
    setTimeout(() => {
      alert.remove();
    }, 300);
  },

  /**
   * Show alert
   */
  showAlert(options = {}) {
    const {
      type = 'info',
      title = '',
      message = '',
      dismissible = true,
      autoDismiss = null,
      container = document.body
    } = options;

    const alert = document.createElement('div');
    alert.className = `alert alert--${type}`;
    alert.setAttribute('role', 'alert');

    alert.innerHTML = `
      <div class="alert-icon">${this.getAlertIcon(type)}</div>
      <div class="alert-content">
        ${title ? `<div class="alert-title">${this.escapeHtml(title)}</div>` : ''}
        <div class="alert-message">${this.escapeHtml(message)}</div>
      </div>
      ${dismissible ? `<button type="button" class="alert-close" aria-label="${t('projectcheck', 'Dismiss alert')}">&times;</button>` : ''}
    `;

    if (autoDismiss) {
      alert.dataset.autoDismiss = autoDismiss;
    }

    container.appendChild(alert);

    // Setup close button
    if (dismissible) {
      const closeBtn = alert.querySelector('.alert-close');
      closeBtn.addEventListener('click', () => {
        this.dismissAlert(alert);
      });
    }

    return alert;
  },

  /**
   * Get alert icon
   */
  getAlertIcon(type) {
    const icons = {
      success: '✓',
      error: '✗',
      warning: '⚠',
      info: 'ℹ'
    };
    return icons[type] || icons.info;
  },

  // ===== TAB COMPONENTS =====

  /**
   * Initialize tab functionality
   */
  initTabs() {
    const tabContainers = document.querySelectorAll('[data-tabs]');
    
    tabContainers.forEach(container => {
      const tabs = container.querySelectorAll('[data-tab]');
      const panels = container.querySelectorAll('[data-tab-panel]');
      
      tabs.forEach(tab => {
        tab.addEventListener('click', (e) => {
          e.preventDefault();
          this.activateTab(container, tab);
        });
      });

      // Activate first tab by default
      if (tabs.length > 0) {
        this.activateTab(container, tabs[0]);
      }
    });
  },

  /**
   * Activate tab
   */
  activateTab(container, tab) {
    const tabId = tab.dataset.tab;
    const panel = container.querySelector(`[data-tab-panel="${tabId}"]`);
    
    if (!panel) return;

    // Deactivate all tabs
    const allTabs = container.querySelectorAll('[data-tab]');
    const allPanels = container.querySelectorAll('[data-tab-panel]');
    
    allTabs.forEach(t => {
      t.classList.remove('tab--active');
      t.setAttribute('aria-selected', 'false');
    });
    
    allPanels.forEach(p => {
      p.classList.remove('tab-panel--active');
      p.setAttribute('aria-hidden', 'true');
    });

    // Activate selected tab
    tab.classList.add('tab--active');
    tab.setAttribute('aria-selected', 'true');
    panel.classList.add('tab-panel--active');
    panel.setAttribute('aria-hidden', 'false');
  },

  // ===== ACCORDION COMPONENTS =====

  /**
   * Initialize accordion functionality
   */
  initAccordions() {
    const accordions = document.querySelectorAll('[data-accordion]');
    
    accordions.forEach(accordion => {
      const items = accordion.querySelectorAll('[data-accordion-item]');
      
      items.forEach(item => {
        const trigger = item.querySelector('[data-accordion-trigger]');
        const content = item.querySelector('[data-accordion-content]');
        
        if (trigger && content) {
          trigger.addEventListener('click', () => {
            this.toggleAccordionItem(accordion, item);
          });
        }
      });
    });
  },

  /**
   * Toggle accordion item
   */
  toggleAccordionItem(accordion, item) {
    const isOpen = item.classList.contains('accordion-item--open');
    const isSingle = accordion.dataset.accordion === 'single';
    
    if (isSingle && !isOpen) {
      // Close other items in single accordion
      const otherItems = accordion.querySelectorAll('[data-accordion-item].accordion-item--open');
      otherItems.forEach(otherItem => {
        this.closeAccordionItem(otherItem);
      });
    }
    
    if (isOpen) {
      this.closeAccordionItem(item);
    } else {
      this.openAccordionItem(item);
    }
  },

  /**
   * Open accordion item
   */
  openAccordionItem(item) {
    const trigger = item.querySelector('[data-accordion-trigger]');
    const content = item.querySelector('[data-accordion-content]');
    
    item.classList.add('accordion-item--open');
    trigger.setAttribute('aria-expanded', 'true');
    content.setAttribute('aria-hidden', 'false');
  },

  /**
   * Close accordion item
   */
  closeAccordionItem(item) {
    const trigger = item.querySelector('[data-accordion-trigger]');
    const content = item.querySelector('[data-accordion-content]');
    
    item.classList.remove('accordion-item--open');
    trigger.setAttribute('aria-expanded', 'false');
    content.setAttribute('aria-hidden', 'true');
  },

  // ===== CAROUSEL COMPONENTS =====

  /**
   * Initialize carousel functionality
   */
  initCarousels() {
    const carousels = document.querySelectorAll('[data-carousel]');
    
    carousels.forEach(carousel => {
      const slides = carousel.querySelectorAll('[data-carousel-slide]');
      const prevBtn = carousel.querySelector('[data-carousel-prev]');
      const nextBtn = carousel.querySelector('[data-carousel-next]');
      const indicators = carousel.querySelectorAll('[data-carousel-indicator]');
      
      let currentSlide = 0;
      
      // Setup navigation buttons
      if (prevBtn) {
        prevBtn.addEventListener('click', () => {
          this.showCarouselSlide(carousel, currentSlide - 1);
        });
      }
      
      if (nextBtn) {
        nextBtn.addEventListener('click', () => {
          this.showCarouselSlide(carousel, currentSlide + 1);
        });
      }
      
      // Setup indicators
      indicators.forEach((indicator, index) => {
        indicator.addEventListener('click', () => {
          this.showCarouselSlide(carousel, index);
        });
      });
      
      // Auto-play
      const autoPlay = carousel.dataset.carouselAutoPlay;
      if (autoPlay) {
        setInterval(() => {
          this.showCarouselSlide(carousel, currentSlide + 1);
        }, parseInt(autoPlay));
      }
    });
  },

  /**
   * Show carousel slide
   */
  showCarouselSlide(carousel, index) {
    const slides = carousel.querySelectorAll('[data-carousel-slide]');
    const indicators = carousel.querySelectorAll('[data-carousel-indicator]');
    
    if (slides.length === 0) return;
    
    // Handle wrap-around
    if (index < 0) index = slides.length - 1;
    if (index >= slides.length) index = 0;
    
    // Hide all slides
    slides.forEach(slide => {
      slide.classList.remove('carousel-slide--active');
      slide.setAttribute('aria-hidden', 'true');
    });
    
    // Deactivate all indicators
    indicators.forEach(indicator => {
      indicator.classList.remove('carousel-indicator--active');
      indicator.setAttribute('aria-selected', 'false');
    });
    
    // Show current slide
    slides[index].classList.add('carousel-slide--active');
    slides[index].setAttribute('aria-hidden', 'false');
    
    // Activate current indicator
    if (indicators[index]) {
      indicators[index].classList.add('carousel-indicator--active');
      indicators[index].setAttribute('aria-selected', 'true');
    }
    
    // Update carousel state
    carousel.dataset.currentSlide = index;
  },

  // ===== UTILITY FUNCTIONS =====

  /**
   * Focus first focusable element
   */
  focusFirstElement(container) {
    const focusableElements = container.querySelectorAll(
      'button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'
    );
    
    if (focusableElements.length > 0) {
      focusableElements[0].focus();
    }
  },

  /**
   * Create element with attributes
   */
  createElement(tag, attributes = {}, content = '') {
    const element = document.createElement(tag);
    
    Object.entries(attributes).forEach(([key, value]) => {
      if (key === 'className') {
        element.className = value;
      } else if (key === 'textContent') {
        element.textContent = value;
      } else if (key === 'innerHTML') {
        element.innerHTML = value;
      } else {
        element.setAttribute(key, value);
      }
    });
    
    if (content) {
      element.textContent = content;
    }
    
    return element;
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
  module.exports = ProjectControlComponents;
} else if (typeof window !== 'undefined') {
  window.ProjectControlComponents = ProjectControlComponents;
}
