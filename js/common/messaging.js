/**
 * Success/Error Message System for ProjectControl App
 * Provides toast notifications and message display functionality
 */

const ProjectControlMessaging = {
  tr(key, ...params) {
    const translated = t('projectcheck', key);
    if (!params || params.length === 0) {
      return translated;
    }

    let paramIndex = 0;
    return String(translated).replace(/%[sd]/g, () => {
      if (paramIndex >= params.length) {
        return '';
      }
      const value = params[paramIndex++];
      return value === null || value === undefined ? '' : String(value);
    });
  },

  escapeHtml(text) {
    if (text === null || text === undefined) return '';
    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
  },

  /**
   * Initialize messaging system
   */
  init() {
    this.setupToastContainer();
    this.setupMessageHandlers();
    this.checkPersistentNotifications();
  },

  // ===== TOAST NOTIFICATIONS =====

  /**
   * Setup toast container
   */
  setupToastContainer() {
    // Create toast container if it doesn't exist
    let container = document.getElementById('toast-container');
    if (!container) {
      container = document.createElement('div');
      container.id = 'toast-container';
      container.className = 'toast-container';
      document.body.appendChild(container);
    }
    
    this.toastContainer = container;
  },

  /**
   * Show toast notification
   */
  show(type, message, options = {}) {
    const {
      title = '',
      duration = 5000,
      dismissible = true,
      actions = []
    } = options;

    const toast = this.createToast(type, title, message, dismissible, actions);
    
    // Add to container
    this.toastContainer.appendChild(toast);
    
    // Show toast with animation
    requestAnimationFrame(() => {
      toast.classList.add('toast--visible');
    });
    
    // Auto-dismiss
    if (duration > 0) {
      setTimeout(() => {
        this.dismissToast(toast);
      }, duration);
    }
    
    // Dispatch event
    window.dispatchEvent(new CustomEvent('toast-show', {
      detail: { type, message, toast }
    }));
    
    return toast;
  },

  /**
   * Create toast element
   */
  createToast(type, title, message, dismissible, actions) {
    const toast = document.createElement('div');
    toast.className = `toast toast--${type}`;
    toast.setAttribute('role', type === 'error' || type === 'warning' ? 'alert' : 'status');
    const live = (type === 'error' || type === 'warning') ? 'assertive' : 'polite';
    toast.setAttribute('aria-live', live);
    toast.setAttribute('aria-atomic', 'true');
    toast.setAttribute('tabindex', '-1');
    
    const icon = this.getToastIcon(type);
    const hasTitle = Boolean(title);
    
    toast.innerHTML = `
      <span class="toast-icon" aria-hidden="true">${icon}</span>
      <div class="toast-content">
        ${hasTitle ? `<div class="toast-title">${this.escapeHtml(title)}</div>` : ''}
        <div class="toast-message">${this.escapeHtml(message)}</div>
        ${actions.length > 0 ? this.createToastActions(actions) : ''}
      </div>
      ${dismissible ? `<button type="button" class="toast-close" aria-label="${this.tr('Close notification')}"><span aria-hidden="true">&times;</span></button>` : ''}
    `;
    
    // Add event listeners
    if (dismissible) {
      const closeBtn = toast.querySelector('.toast-close');
      closeBtn.addEventListener('click', () => {
        this.dismissToast(toast);
      });
    }
    
    // Add action event listeners
    const actionButtons = toast.querySelectorAll('.toast-action');
    actionButtons.forEach(button => {
      button.addEventListener('click', (e) => {
        e.preventDefault();
        const action = button.dataset.action;
        if (action) {
          this.handleToastAction(toast, action);
        }
      });
    });
    
    return toast;
  },

  /**
   * Create toast actions
   */
  createToastActions(actions) {
    const actionsHtml = actions.map(action => `
      <button type="button" 
              class="toast-action" 
              data-action="${this.escapeHtml(action.name)}"
              ${action.primary ? 'data-primary="true"' : ''}>
        ${this.escapeHtml(action.label)}
      </button>
    `).join('');
    
    return `<div class="toast-actions">${actionsHtml}</div>`;
  },

  /**
   * Handle toast action
   */
  handleToastAction(toast, actionName) {
    // Dispatch action event
    window.dispatchEvent(new CustomEvent('toast-action', {
      detail: { action: actionName, toast }
    }));
    
    // Dismiss toast after action
    this.dismissToast(toast);
  },

  /**
   * Dismiss toast
   */
  dismissToast(toast) {
    toast.classList.add('toast--removing');
    
    setTimeout(() => {
      if (toast.parentNode) {
        toast.parentNode.removeChild(toast);
      }
    }, 300);
    
    // Dispatch event
    window.dispatchEvent(new CustomEvent('toast-dismiss', {
      detail: { toast }
    }));
  },

  /**
   * Get toast icon
   */
  getToastIcon(type) {
    const icons = {
      success: '✓',
      error: '✗',
      warning: '⚠',
      info: 'ℹ'
    };
    return icons[type] || icons.info;
  },

  // ===== CONVENIENCE METHODS =====

  /**
   * Show success message
   */
  success(message, options = {}) {
    return this.show('success', message, options);
  },

  /**
   * Show error message
   */
  error(message, options = {}) {
    return this.show('error', message, { duration: 8000, ...options });
  },

  /**
   * Show warning message
   */
  warning(message, options = {}) {
    return this.show('warning', message, { duration: 6000, ...options });
  },

  /**
   * Show info message
   */
  info(message, options = {}) {
    return this.show('info', message, options);
  },

  // ===== ALERT MESSAGES =====

  /**
   * Show alert message
   */
  showAlert(type, message, options = {}) {
    const {
      title = '',
      dismissible = true,
      autoDismiss = null,
      container = document.body
    } = options;

    const alert = document.createElement('div');
    alert.className = `alert alert--${type}`;
    alert.setAttribute('role', type === 'error' || type === 'warning' ? 'alert' : 'status');
    alert.setAttribute('aria-live', type === 'error' || type === 'warning' ? 'assertive' : 'polite');
    alert.setAttribute('aria-atomic', 'true');
    
    if (autoDismiss) {
      alert.dataset.autoDismiss = autoDismiss;
    }

    const icon = this.getAlertIcon(type);
    
    alert.innerHTML = `
      <div class="alert-icon" aria-hidden="true">${icon}</div>
      <div class="alert-content">
        ${title ? `<div class="alert-title">${this.escapeHtml(title)}</div>` : ''}
        <div class="alert-message">${this.escapeHtml(message)}</div>
      </div>
      ${dismissible ? `<button type="button" class="alert-close" aria-label="${this.tr('Dismiss alert')}"><span aria-hidden="true">&times;</span></button>` : ''}
    `;

    container.appendChild(alert);

    // Setup close button
    if (dismissible) {
      const closeBtn = alert.querySelector('.alert-close');
      closeBtn.addEventListener('click', () => {
        this.dismissAlert(alert);
      });
    }

    // Auto-dismiss
    if (autoDismiss) {
      setTimeout(() => {
        this.dismissAlert(alert);
      }, autoDismiss);
    }

    return alert;
  },

  /**
   * Dismiss alert
   */
  dismissAlert(alert) {
    alert.style.opacity = '0';
    alert.style.transform = 'translateY(-10px)';
    
    setTimeout(() => {
      if (alert.parentNode) {
        alert.parentNode.removeChild(alert);
      }
    }, 300);
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

  // ===== CONFIRMATION DIALOGS =====

  /**
   * Show confirmation dialog
   */
  confirm(message, options = {}) {
    const {
      title = this.tr('Confirm Action'),
      confirmText = this.tr('Confirm'),
      cancelText = this.tr('Cancel'),
      type = 'warning'
    } = options;

    return new Promise((resolve) => {
      const modal = this.createConfirmModal(title, message, confirmText, cancelText, type);
      
      const confirmBtn = modal.querySelector('.modal-confirm');
      const cancelBtn = modal.querySelector('.modal-cancel');
      
      confirmBtn.addEventListener('click', () => {
        this.closeModal(modal);
        resolve(true);
      });
      
      cancelBtn.addEventListener('click', () => {
        this.closeModal(modal);
        resolve(false);
      });
      
      this.openModal(modal);
    });
  },

  /**
   * Create confirmation modal
   */
  createConfirmModal(title, message, confirmText, cancelText, type) {
    const modal = document.createElement('div');
    modal.className = `modal modal--sm modal--${type}`;
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    
    modal.innerHTML = `
      <div class="modal-header">
        <h2 class="modal-title">${this.escapeHtml(title)}</h2>
      </div>
      <div class="modal-body">
        <p>${this.escapeHtml(message)}</p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn--secondary modal-cancel">${this.escapeHtml(cancelText)}</button>
        <button type="button" class="btn btn--${type} modal-confirm">${this.escapeHtml(confirmText)}</button>
      </div>
    `;
    
    return modal;
  },

  /**
   * Open modal
   */
  openModal(modal) {
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
    if (window.ProjectCheckLayout || window.ProjectControlLayout) {
      (window.ProjectCheckLayout || window.ProjectControlLayout).lockBodyScroll();
    }

    // Focus first focusable element
    this.focusFirstElement(modal);
  },

  /**
   * Close modal
   */
  closeModal(modal) {
    const backdrop = modal.closest('.modal-backdrop');
    if (!backdrop) return;

    // Hide modal
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
    backdrop.style.display = 'none';

    // Remove backdrop
    backdrop.remove();

    // Unlock body scroll
    if (window.ProjectCheckLayout || window.ProjectControlLayout) {
      (window.ProjectCheckLayout || window.ProjectControlLayout).unlockBodyScroll();
    }
  },

  // ===== MESSAGE HANDLERS =====

  /**
   * Setup message handlers
   */
  setupMessageHandlers() {
    // Listen for custom message events
    window.addEventListener('show-message', (e) => {
      const { type, message, options } = e.detail;
      this.show(type, message, options);
    });

    window.addEventListener('show-alert', (e) => {
      const { type, message, options } = e.detail;
      this.showAlert(type, message, options);
    });

    window.addEventListener('show-confirmation', (e) => {
      const { message, options, callback } = e.detail;
      this.confirm(message, options).then(callback);
    });

    // Form-specific message handlers
    this.setupFormSpecificHandlers();
  },

  /**
   * Setup form-specific message handlers
   */
  setupFormSpecificHandlers() {
    // Customer form messages
    window.addEventListener('customer-form-success', (e) => {
      const { action, data } = e.detail;
      this.showCustomerFormMessage(action, data);
    });

    // Project form messages
    window.addEventListener('project-form-success', (e) => {
      const { action, data } = e.detail;
      this.showProjectFormMessage(action, data);
    });

    // Time entry form messages
    window.addEventListener('time-entry-form-success', (e) => {
      const { action, data } = e.detail;
      this.showTimeEntryFormMessage(action, data);
    });

    // Settings form messages
    window.addEventListener('settings-form-success', (e) => {
      const { action, data } = e.detail;
      this.showSettingsFormMessage(action, data);
    });

    // Bulk operation messages
    window.addEventListener('bulk-operation-complete', (e) => {
      const { action, data } = e.detail;
      this.showBulkOperationMessage(action, data);
    });
  },

  /**
   * Show customer form success/error messages
   */
  showCustomerFormMessage(action, data) {
    const messages = {
      'created': {
        type: 'success',
        title: this.tr('Customer Created'),
        message: this.tr('Customer "%s" has been created successfully.', [data.name]),
        actions: [
          { name: 'view', label: this.tr('View Customer'), primary: true },
          { name: 'create_project', label: this.tr('Create Project') }
        ]
      },
      'updated': {
        type: 'success',
        title: this.tr('Customer Updated'),
        message: this.tr('Customer "%s" has been updated successfully.', [data.name]),
        actions: [
          { name: 'view', label: this.tr('View Customer'), primary: true }
        ]
      },
      'deleted': {
        type: 'info',
        title: this.tr('Customer Deleted'),
        message: this.tr('Customer "%s" has been deleted.', [data.name]),
        actions: [
          { name: 'undo', label: this.tr('Undo'), primary: true }
        ]
      }
    };

    const message = messages[action];
    if (message) {
      this.show(message.type, message.message, {
        title: message.title,
        actions: message.actions
      });
    }
  },

  /**
   * Show project form success/error messages
   */
  showProjectFormMessage(action, data) {
    const messages = {
      'created': {
        type: 'success',
        title: this.tr('Project Created'),
        message: this.tr('Project "%s" has been created successfully.', [data.name]),
        actions: [
          { name: 'view', label: this.tr('View Project'), primary: true },
          { name: 'add_time_entry', label: this.tr('Add Time Entry') }
        ]
      },
      'updated': {
        type: 'success',
        title: this.tr('Project Updated'),
        message: this.tr('Project "%s" has been updated successfully.', [data.name]),
        actions: [
          { name: 'view', label: this.tr('View Project'), primary: true }
        ]
      },
      'deleted': {
        type: 'info',
        title: this.tr('Project Deleted'),
        message: this.tr('Project "%s" has been deleted.', [data.name]),
        actions: [
          { name: 'undo', label: this.tr('Undo'), primary: true }
        ]
      },
      'status_changed': {
        type: 'info',
        title: this.tr('Project Status Changed'),
        message: this.tr('Project "%s" status changed to "%s".', [data.name, data.status]),
        actions: [
          { name: 'view', label: this.tr('View Project'), primary: true }
        ]
      }
    };

    const message = messages[action];
    if (message) {
      this.show(message.type, message.message, {
        title: message.title,
        actions: message.actions
      });
    }
  },

  /**
   * Show time entry form success/error messages
   */
  showTimeEntryFormMessage(action, data) {
    const messages = {
      'created': {
        type: 'success',
        title: this.tr('Time Entry Created'),
        message: this.tr('Time entry for %s hours has been created successfully.', [data.hours]),
        actions: [
          { name: 'view', label: this.tr('View Entry'), primary: true },
          { name: 'add_another', label: this.tr('Add Another') }
        ]
      },
      'updated': {
        type: 'success',
        title: this.tr('Time Entry Updated'),
        message: this.tr('Time entry has been updated successfully.'),
        actions: [
          { name: 'view', label: this.tr('View Entry'), primary: true }
        ]
      },
      'deleted': {
        type: 'info',
        title: this.tr('Time Entry Deleted'),
        message: this.tr('Time entry has been deleted.'),
        actions: [
          { name: 'undo', label: this.tr('Undo'), primary: true }
        ]
      },
      'overlap_warning': {
        type: 'warning',
        title: this.tr('Time Entry Overlap'),
        message: this.tr('This time entry overlaps with an existing entry. Please adjust the time range.'),
        actions: [
          { name: 'adjust', label: this.tr('Adjust Time'), primary: true },
          { name: 'view_conflicts', label: this.tr('View Conflicts') }
        ]
      }
    };

    const message = messages[action];
    if (message) {
      this.show(message.type, message.message, {
        title: message.title,
        actions: message.actions
      });
    }
  },

  /**
   * Show settings form success/error messages
   */
  showSettingsFormMessage(action, data) {
    const messages = {
      'updated': {
        type: 'success',
        title: this.tr('Settings Updated'),
        message: this.tr('Settings have been updated successfully.'),
        actions: []
      },
      'permissions_changed': {
        type: 'info',
        title: this.tr('Permissions Updated'),
        message: this.tr('User permissions have been updated.'),
        actions: [
          { name: 'view_users', label: this.tr('View Users'), primary: true }
        ]
      }
    };

    const message = messages[action];
    if (message) {
      this.show(message.type, message.message, {
        title: message.title,
        actions: message.actions
      });
    }
  },

  /**
   * Show bulk operation messages
   */
  showBulkOperationMessage(action, data) {
    const messages = {
      'success': {
        type: 'success',
        title: this.tr('Bulk Operation Completed'),
        message: this.tr('%d items have been processed successfully.', [data.count]),
        actions: [
          { name: 'view_results', label: this.tr('View Results'), primary: true }
        ]
      },
      'partial': {
        type: 'warning',
        title: this.tr('Bulk Operation Partially Completed'),
        message: this.tr('%d items processed successfully, %d failed.', [data.success_count, data.error_count]),
        actions: [
          { name: 'view_errors', label: this.tr('View Errors'), primary: true },
          { name: 'retry_failed', label: this.tr('Retry Failed') }
        ]
      }
    };

    const message = messages[action];
    if (message) {
      this.show(message.type, message.message, {
        title: message.title,
        actions: message.actions
      });
    }
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
   * Clear all toasts
   */
  clearAll() {
    const toasts = this.toastContainer.querySelectorAll('.toast');
    toasts.forEach(toast => {
      this.dismissToast(toast);
    });
  },

  /**
   * Get toast count
   */
  getToastCount() {
    return this.toastContainer.querySelectorAll('.toast').length;
  },

  /**
   * Check if toasts are visible
   */
  hasToasts() {
    return this.getToastCount() > 0;
  },

  // ===== MESSAGE PERSISTENCE AND HISTORY =====

  /**
   * Save message to persistent storage
   */
  saveMessageToHistory(messageData) {
    const history = this.getMessageHistory();
    const message = {
      ...messageData,
      id: this.generateMessageId(),
      timestamp: new Date().toISOString(),
      acknowledged: false
    };

    history.push(message);

    // Keep only last 100 messages
    if (history.length > 100) {
      history.splice(0, history.length - 100);
    }

    localStorage.setItem('projectcheck_message_history', JSON.stringify(history));
    
    // Dispatch event for other components
    window.dispatchEvent(new CustomEvent('message-saved', {
      detail: { message }
    }));
  },

  /**
   * Get message history from storage
   */
  getMessageHistory() {
    try {
      const history = localStorage.getItem('projectcheck_message_history');
      return history ? JSON.parse(history) : [];
    } catch (error) {
      console.error('Error loading message history:', error);
      return [];
    }
  },

  /**
   * Mark message as acknowledged
   */
  acknowledgeMessage(messageId) {
    const history = this.getMessageHistory();
    const messageIndex = history.findIndex(msg => msg.id === messageId);
    
    if (messageIndex !== -1) {
      history[messageIndex].acknowledged = true;
      history[messageIndex].acknowledgedAt = new Date().toISOString();
      localStorage.setItem('projectcheck_message_history', JSON.stringify(history));
      
      // Dispatch event
      window.dispatchEvent(new CustomEvent('message-acknowledged', {
        detail: { messageId, message: history[messageIndex] }
      }));
    }
  },

  /**
   * Get unacknowledged messages
   */
  getUnacknowledgedMessages() {
    const history = this.getMessageHistory();
    return history.filter(msg => !msg.acknowledged);
  },

  /**
   * Show persistent notification
   */
  showPersistentNotification(messageData) {
    const notification = this.createPersistentNotification(messageData);
    document.body.appendChild(notification);
    
    // Save to history
    this.saveMessageToHistory(messageData);
    
    return notification;
  },

  /**
   * Create persistent notification element
   */
  createPersistentNotification(messageData) {
    const notification = document.createElement('div');
    const nType = messageData.type || 'info';
    notification.className = `persistent-notification persistent-notification--${nType}`;
    notification.setAttribute('data-message-id', messageData.id);
    notification.setAttribute('role', nType === 'error' || nType === 'warning' ? 'alert' : 'status');
    notification.setAttribute('aria-live', nType === 'error' || nType === 'warning' ? 'assertive' : 'polite');
    notification.setAttribute('aria-atomic', 'true');
    
    notification.innerHTML = `
      <div class="persistent-notification__icon" aria-hidden="true">
        ${this.getToastIcon(nType)}
      </div>
      <div class="persistent-notification__content">
        <div class="persistent-notification__title">${this.escapeHtml(messageData.title || '')}</div>
        <div class="persistent-notification__message">${this.escapeHtml(messageData.message)}</div>
      </div>
      <div class="persistent-notification__actions">
        <button type="button" class="persistent-notification__acknowledge" aria-label="${this.tr('Acknowledge')}">
          <span aria-hidden="true">✓</span>
        </button>
        <button type="button" class="persistent-notification__dismiss" aria-label="${this.tr('Dismiss')}">
          <span aria-hidden="true">×</span>
        </button>
      </div>
    `;
    
    // Add event listeners
    const acknowledgeBtn = notification.querySelector('.persistent-notification__acknowledge');
    const dismissBtn = notification.querySelector('.persistent-notification__dismiss');
    
    acknowledgeBtn.addEventListener('click', () => {
      this.acknowledgeMessage(messageData.id);
      notification.remove();
    });
    
    dismissBtn.addEventListener('click', () => {
      notification.remove();
    });
    
    return notification;
  },

  /**
   * Show message history panel
   */
  showMessageHistory() {
    const history = this.getMessageHistory();
    const modal = this.createHistoryModal(history);
    this.openModal(modal);
  },

  /**
   * Create history modal
   */
  createHistoryModal(history) {
    const modal = document.createElement('div');
    modal.className = 'modal modal--lg';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    
    const historyHtml = history.length > 0 
      ? history.map(msg => `
          <div class="history-item history-item--${msg.type} ${msg.acknowledged ? 'history-item--acknowledged' : ''}">
            <div class="history-item__icon">${this.getToastIcon(msg.type)}</div>
            <div class="history-item__content">
              <div class="history-item__title">${this.escapeHtml(msg.title || '')}</div>
              <div class="history-item__message">${this.escapeHtml(msg.message)}</div>
              <div class="history-item__timestamp">${this.escapeHtml(new Date(msg.timestamp).toLocaleString())}</div>
            </div>
            <div class="history-item__status">
              ${msg.acknowledged ? `✓ ${this.escapeHtml(this.tr('Acknowledged'))}` : `⚠ ${this.escapeHtml(this.tr('Pending'))}`}
            </div>
          </div>
        `).join('')
      : `<div class="history-empty">${this.escapeHtml(this.tr('No message history found.'))}</div>`;
    
    modal.innerHTML = `
      <div class="modal-header">
        <h2 class="modal-title">${this.escapeHtml(this.tr('Message History'))}</h2>
      </div>
      <div class="modal-body">
        <div class="history-list">
          ${historyHtml}
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn--secondary modal-clear-history">${this.escapeHtml(this.tr('Clear History'))}</button>
        <button type="button" class="btn btn--primary modal-export-history">${this.escapeHtml(this.tr('Export'))}</button>
        <button type="button" class="btn btn--secondary modal-cancel">${this.escapeHtml(this.tr('Close'))}</button>
      </div>
    `;
    
    // Add event listeners
    const clearBtn = modal.querySelector('.modal-clear-history');
    const exportBtn = modal.querySelector('.modal-export-history');
    const closeBtn = modal.querySelector('.modal-cancel');
    
    clearBtn.addEventListener('click', () => {
      this.clearMessageHistory();
      this.closeModal(modal);
      this.show('info', this.tr('Message history has been cleared.'));
    });
    
    exportBtn.addEventListener('click', () => {
      this.exportMessageHistory();
    });
    
    closeBtn.addEventListener('click', () => {
      this.closeModal(modal);
    });
    
    return modal;
  },

  /**
   * Clear message history
   */
  clearMessageHistory() {
    localStorage.removeItem('projectcheck_message_history');
    window.dispatchEvent(new CustomEvent('message-history-cleared'));
  },

  /**
   * Export message history
   */
  exportMessageHistory() {
    const history = this.getMessageHistory();
    const exportData = {
      exportDate: new Date().toISOString(),
      totalMessages: history.length,
      messages: history
    };
    
    const blob = new Blob([JSON.stringify(exportData, null, 2)], {
      type: 'application/json'
    });
    
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `projectcheck-messages-${new Date().toISOString().split('T')[0]}.json`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
  },

  /**
   * Generate unique message ID
   */
  generateMessageId() {
    return 'msg_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
  },

  /**
   * Check for persistent notifications on page load
   */
  checkPersistentNotifications() {
    const unacknowledged = this.getUnacknowledgedMessages();
    unacknowledged.forEach(message => {
      this.showPersistentNotification(message);
    });
  }
};

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
  module.exports = ProjectControlMessaging;
} else if (typeof window !== 'undefined') {
  window.ProjectCheckMessaging = ProjectControlMessaging;
  window.ProjectControlMessaging = ProjectControlMessaging;
}
