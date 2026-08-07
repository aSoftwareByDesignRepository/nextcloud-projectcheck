/**
 * General Utility Functions for ProjectControl App
 * Provides common utility functions used throughout the application
 */

const ProjectControlUtils = {
  // ===== DOM UTILITIES =====

  /**
   * Get element by selector
   */
  $(selector, context = document) {
    return context.querySelector(selector);
  },

  /**
   * Get all elements by selector
   */
  $$(selector, context = document) {
    return context.querySelectorAll(selector);
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
   * Add event listener with options
   */
  on(element, event, handler, options = {}) {
    if (typeof element === 'string') {
      element = this.$(element);
    }
    
    if (element) {
      element.addEventListener(event, handler, options);
    }
  },

  /**
   * Remove event listener
   */
  off(element, event, handler, options = {}) {
    if (typeof element === 'string') {
      element = this.$(element);
    }
    
    if (element) {
      element.removeEventListener(event, handler, options);
    }
  },

  /**
   * Toggle element visibility
   */
  toggle(element, show = null) {
    if (typeof element === 'string') {
      element = this.$(element);
    }
    
    if (!element) return;
    
    if (show === null) {
      show = element.style.display === 'none';
    }
    
    element.style.display = show ? '' : 'none';
  },

  /**
   * Show element
   */
  show(element) {
    this.toggle(element, true);
  },

  /**
   * Hide element
   */
  hide(element) {
    this.toggle(element, false);
  },

  /**
   * Add class to element
   */
  addClass(element, className) {
    if (typeof element === 'string') {
      element = this.$(element);
    }
    
    if (element) {
      element.classList.add(className);
    }
  },

  /**
   * Remove class from element
   */
  removeClass(element, className) {
    if (typeof element === 'string') {
      element = this.$(element);
    }
    
    if (element) {
      element.classList.remove(className);
    }
  },

  /**
   * Toggle class on element
   */
  toggleClass(element, className, force = null) {
    if (typeof element === 'string') {
      element = this.$(element);
    }
    
    if (element) {
      element.classList.toggle(className, force);
    }
  },

  /**
   * Check if element has class
   */
  hasClass(element, className) {
    if (typeof element === 'string') {
      element = this.$(element);
    }
    
    return element ? element.classList.contains(className) : false;
  },

  // ===== STRING UTILITIES =====

  /**
   * Capitalize first letter
   */
  capitalize(str) {
    return str.charAt(0).toUpperCase() + str.slice(1);
  },

  /**
   * Convert to camelCase
   */
  camelCase(str) {
    return str.replace(/-([a-z])/g, (g) => g[1].toUpperCase());
  },

  /**
   * Convert to kebab-case
   */
  kebabCase(str) {
    return str.replace(/([a-z])([A-Z])/g, '$1-$2').toLowerCase();
  },

  /**
   * Convert to snake_case
   */
  snakeCase(str) {
    return str.replace(/([a-z])([A-Z])/g, '$1_$2').toLowerCase();
  },

  /**
   * Truncate string
   */
  truncate(str, length = 50, suffix = '...') {
    if (str.length <= length) return str;
    return str.substring(0, length) + suffix;
  },

  /**
   * Generate random string
   */
  randomString(length = 8) {
    const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let result = '';
    for (let i = 0; i < length; i++) {
      result += chars.charAt(Math.floor(Math.random() * chars.length));
    }
    return result;
  },

  /**
   * Generate UUID
   */
  uuid() {
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
      const r = Math.random() * 16 | 0;
      const v = c == 'x' ? r : (r & 0x3 | 0x8);
      return v.toString(16);
    });
  },

  // ===== NUMBER UTILITIES =====

  /**
   * Format number with locale-aware thousands separators.
   *
   * Delegates to {@link window.ProjectCheckFormat} so the user's Nextcloud
   * locale drives the output. The previous hard-coded `en-US` was an audit
   * finding (B10) and produced wrong number grouping for non-English users.
   */
  formatNumber(num, decimals = 0) {
    if (typeof window !== 'undefined' && window.ProjectCheckFormat) {
      return window.ProjectCheckFormat.number(num, {
        minimumFractionDigits: decimals,
        maximumFractionDigits: decimals,
      });
    }
    return Number(num).toLocaleString(undefined, {
      minimumFractionDigits: decimals,
      maximumFractionDigits: decimals,
    });
  },

  /**
   * Format currency. Currency defaults to the org-configured currency
   * (resolved by ProjectCheckFormat) so the displayed unit matches the
   * server-side budget data.
   */
  formatCurrency(amount, currency) {
    if (typeof window !== 'undefined' && window.ProjectCheckFormat) {
      return window.ProjectCheckFormat.currencyFmt(amount, currency);
    }
    const code = typeof currency === 'string' ? currency.toUpperCase() : 'EUR';
    try {
      return new Intl.NumberFormat(undefined, { style: 'currency', currency: code }).format(amount);
    } catch (e) {
      return code + ' ' + Number(amount).toFixed(2);
    }
  },

  /**
   * Format percentage. Input is a 0-1 ratio (e.g. `0.42` -> "42 %").
   */
  formatPercentage(value, decimals = 1) {
    if (typeof window !== 'undefined' && window.ProjectCheckFormat) {
      return window.ProjectCheckFormat.ratio(value, decimals);
    }
    return `${(Number(value) * 100).toFixed(decimals)}%`;
  },

  /**
   * Clamp number between min and max
   */
  clamp(value, min, max) {
    return Math.min(Math.max(value, min), max);
  },

  /**
   * Round to nearest multiple
   */
  roundTo(value, multiple) {
    return Math.round(value / multiple) * multiple;
  },

  // ===== DATE UTILITIES =====

  /**
   * Format date
   * Default format is DD.MM.YYYY for European users
   */
  formatDate(date, format = 'DD.MM.YYYY') {
    const d = new Date(date);
    
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    const hours = String(d.getHours()).padStart(2, '0');
    const minutes = String(d.getMinutes()).padStart(2, '0');
    const seconds = String(d.getSeconds()).padStart(2, '0');
    
    return format
      .replace('YYYY', year)
      .replace('MM', month)
      .replace('DD', day)
      .replace('HH', hours)
      .replace('mm', minutes)
      .replace('ss', seconds);
  },

  /**
   * Get relative time (e.g., "2 hours ago")
   */
  relativeTime(date) {
    const now = new Date();
    const diff = now - new Date(date);
    const seconds = Math.floor(diff / 1000);
    const minutes = Math.floor(seconds / 60);
    const hours = Math.floor(minutes / 60);
    const days = Math.floor(hours / 24);
    const months = Math.floor(days / 30);
    const years = Math.floor(months / 12);
    
    if (years > 0) return `${years} year${years > 1 ? 's' : ''} ago`;
    if (months > 0) return `${months} month${months > 1 ? 's' : ''} ago`;
    if (days > 0) return `${days} day${days > 1 ? 's' : ''} ago`;
    if (hours > 0) return `${hours} hour${hours > 1 ? 's' : ''} ago`;
    if (minutes > 0) return `${minutes} minute${minutes > 1 ? 's' : ''} ago`;
    return 'Just now';
  },

  /**
   * Check if date is today
   */
  isToday(date) {
    const today = new Date();
    const d = new Date(date);
    return d.toDateString() === today.toDateString();
  },

  /**
   * Check if date is yesterday
   */
  isYesterday(date) {
    const yesterday = new Date();
    yesterday.setDate(yesterday.getDate() - 1);
    const d = new Date(date);
    return d.toDateString() === yesterday.toDateString();
  },

  // ===== ARRAY UTILITIES =====

  /**
   * Remove duplicates from array
   */
  unique(array) {
    return [...new Set(array)];
  },

  /**
   * Group array by key
   */
  groupBy(array, key) {
    return array.reduce((groups, item) => {
      const group = item[key];
      groups[group] = groups[group] || [];
      groups[group].push(item);
      return groups;
    }, {});
  },

  /**
   * Sort array by key
   */
  sortBy(array, key, direction = 'asc') {
    return [...array].sort((a, b) => {
      let aVal = a[key];
      let bVal = b[key];
      
      if (typeof aVal === 'string') aVal = aVal.toLowerCase();
      if (typeof bVal === 'string') bVal = bVal.toLowerCase();
      
      if (aVal < bVal) return direction === 'asc' ? -1 : 1;
      if (aVal > bVal) return direction === 'asc' ? 1 : -1;
      return 0;
    });
  },

  /**
   * Filter array by multiple conditions
   */
  filterBy(array, filters) {
    return array.filter(item => {
      return Object.entries(filters).every(([key, value]) => {
        if (typeof value === 'function') {
          return value(item[key], item);
        }
        if (Array.isArray(value)) {
          return value.includes(item[key]);
        }
        return item[key] === value;
      });
    });
  },

  /**
   * Chunk array into smaller arrays
   */
  chunk(array, size) {
    const chunks = [];
    for (let i = 0; i < array.length; i += size) {
      chunks.push(array.slice(i, i + size));
    }
    return chunks;
  },

  // ===== OBJECT UTILITIES =====

  /**
   * Deep clone object
   */
  clone(obj) {
    if (obj === null || typeof obj !== 'object') return obj;
    if (obj instanceof Date) return new Date(obj.getTime());
    if (obj instanceof Array) return obj.map(item => this.clone(item));
    if (typeof obj === 'object') {
      const clonedObj = {};
      for (const key in obj) {
        if (obj.hasOwnProperty(key)) {
          clonedObj[key] = this.clone(obj[key]);
        }
      }
      return clonedObj;
    }
  },

  /**
   * Merge objects
   */
  merge(target, ...sources) {
    sources.forEach(source => {
      for (const key in source) {
        if (source.hasOwnProperty(key)) {
          if (typeof source[key] === 'object' && source[key] !== null && !Array.isArray(source[key])) {
            target[key] = target[key] || {};
            this.merge(target[key], source[key]);
          } else {
            target[key] = source[key];
          }
        }
      }
    });
    return target;
  },

  /**
   * Pick properties from object
   */
  pick(obj, keys) {
    const result = {};
    keys.forEach(key => {
      if (obj.hasOwnProperty(key)) {
        result[key] = obj[key];
      }
    });
    return result;
  },

  /**
   * Omit properties from object
   */
  omit(obj, keys) {
    const result = {};
    for (const key in obj) {
      if (obj.hasOwnProperty(key) && !keys.includes(key)) {
        result[key] = obj[key];
      }
    }
    return result;
  },

  // ===== FUNCTION UTILITIES =====

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
  },

  /**
   * Once function (execute only once)
   */
  once(func) {
    let executed = false;
    return function(...args) {
      if (!executed) {
        executed = true;
        return func.apply(this, args);
      }
    };
  },

  /**
   * Memoize function
   */
  memoize(func) {
    const cache = new Map();
    return function(...args) {
      const key = JSON.stringify(args);
      if (cache.has(key)) {
        return cache.get(key);
      }
      const result = func.apply(this, args);
      cache.set(key, result);
      return result;
    };
  },

  // ===== VALIDATION UTILITIES =====

  /**
   * Check if value is empty
   */
  isEmpty(value) {
    if (value === null || value === undefined) return true;
    if (typeof value === 'string') return value.trim() === '';
    if (Array.isArray(value)) return value.length === 0;
    if (typeof value === 'object') return Object.keys(value).length === 0;
    return false;
  },

  /**
   * Check if value is email
   */
  isEmail(value) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(value);
  },

  /**
   * Check if value is URL
   */
  isUrl(value) {
    try {
      new URL(value);
      return true;
    } catch {
      return false;
    }
  },

  /**
   * Check if value is phone number
   */
  isPhone(value) {
    const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
    return phoneRegex.test(value.replace(/[\s\-\(\)]/g, ''));
  },

  /**
   * Check if value is numeric
   */
  isNumeric(value) {
    return !isNaN(parseFloat(value)) && isFinite(value);
  },

  // ===== STORAGE UTILITIES =====

  /**
   * Set localStorage item
   */
  setStorage(key, value) {
    try {
      localStorage.setItem(key, JSON.stringify(value));
      return true;
    } catch (error) {
      console.error('Error setting localStorage:', error);
      return false;
    }
  },

  /**
   * Get localStorage item
   */
  getStorage(key, defaultValue = null) {
    try {
      const item = localStorage.getItem(key);
      return item ? JSON.parse(item) : defaultValue;
    } catch (error) {
      console.error('Error getting localStorage:', error);
      return defaultValue;
    }
  },

  /**
   * Remove localStorage item
   */
  removeStorage(key) {
    try {
      localStorage.removeItem(key);
      return true;
    } catch (error) {
      console.error('Error removing localStorage:', error);
      return false;
    }
  },

  /**
   * Clear all localStorage
   */
  clearStorage() {
    try {
      localStorage.clear();
      return true;
    } catch (error) {
      console.error('Error clearing localStorage:', error);
      return false;
    }
  },

  // ===== URL UTILITIES =====

  /**
   * Get URL parameters
   */
  getUrlParams() {
    const params = new URLSearchParams(window.location.search);
    const result = {};
    for (const [key, value] of params) {
      result[key] = value;
    }
    return result;
  },

  /**
   * Set URL parameter
   */
  setUrlParam(key, value) {
    const url = new URL(window.location);
    url.searchParams.set(key, value);
    window.history.replaceState({}, '', url);
  },

  /**
   * Remove URL parameter
   */
  removeUrlParam(key) {
    const url = new URL(window.location);
    url.searchParams.delete(key);
    window.history.replaceState({}, '', url);
  },

  // ===== BROWSER UTILITIES =====

  /**
   * Check if browser supports feature
   */
  supports(feature) {
    const features = {
      localStorage: () => {
        try {
          localStorage.setItem('test', 'test');
          localStorage.removeItem('test');
          return true;
        } catch {
          return false;
        }
      },
      sessionStorage: () => {
        try {
          sessionStorage.setItem('test', 'test');
          sessionStorage.removeItem('test');
          return true;
        } catch {
          return false;
        }
      },
      webp: () => {
        const canvas = document.createElement('canvas');
        canvas.width = 1;
        canvas.height = 1;
        return canvas.toDataURL('image/webp').indexOf('data:image/webp') === 0;
      },
      webgl: () => {
        const canvas = document.createElement('canvas');
        return !!(canvas.getContext('webgl') || canvas.getContext('experimental-webgl'));
      }
    };
    
    return features[feature] ? features[feature]() : false;
  },

  /**
   * Get browser info
   */
  getBrowserInfo() {
    const userAgent = navigator.userAgent;
    let browser = 'Unknown';
    let version = 'Unknown';
    
    if (userAgent.includes('Chrome')) {
      browser = 'Chrome';
      version = userAgent.match(/Chrome\/(\d+)/)?.[1] || 'Unknown';
    } else if (userAgent.includes('Firefox')) {
      browser = 'Firefox';
      version = userAgent.match(/Firefox\/(\d+)/)?.[1] || 'Unknown';
    } else if (userAgent.includes('Safari')) {
      browser = 'Safari';
      version = userAgent.match(/Version\/(\d+)/)?.[1] || 'Unknown';
    } else if (userAgent.includes('Edge')) {
      browser = 'Edge';
      version = userAgent.match(/Edge\/(\d+)/)?.[1] || 'Unknown';
    }
    
    return { browser, version, userAgent };
  },

  /**
   * Get device info
   */
  getDeviceInfo() {
    return {
      isMobile: /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent),
      isTablet: /iPad|Android(?=.*\bMobile\b)(?=.*\bSafari\b)/i.test(navigator.userAgent),
      isDesktop: !(/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)),
      screenWidth: window.screen.width,
      screenHeight: window.screen.height,
      viewportWidth: window.innerWidth,
      viewportHeight: window.innerHeight
    };
  }
};

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
  module.exports = ProjectControlUtils;
} else if (typeof window !== 'undefined') {
  window.ProjectCheckUtils = ProjectControlUtils;
  window.ProjectControlUtils = ProjectControlUtils;
}
