/**
 * Theme Management for ProjectControl App
 * Inherits Nextcloud theme; no app-level toggles or persistence
 */

const ProjectControlTheme = {
  /**
   * Initialize the theme system
   */
  init() {
    // Read theme from document attribute set by server
    this.currentTheme = this.readThemeFromDocument();
    this.applyTheme(this.currentTheme);
  },

  /**
   * Setup theme detection from localStorage and system preferences
   */
  setupThemeDetection() {
    this.currentTheme = this.readThemeFromDocument();
  },

  /**
   * Setup theme toggle functionality
   */
  setupThemeToggle() { },

  /**
   * Setup system theme change listener
   */
  setupSystemThemeListener() { },

  /**
   * Get system theme preference
   */
  getSystemTheme() {
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
      return 'dark';
    }
    return 'light';
  },

  /**
   * Get current theme
   */
  getCurrentTheme() {
    return this.readThemeFromDocument() || this.currentTheme || 'light';
  },

  /**
   * Set theme
   */
  setTheme() { },

  /**
   * Toggle between light and dark themes
   */
  toggleTheme() { },

  /**
   * Apply theme to the document
   */
  applyTheme(theme) {
    const root = document.documentElement;

    // Remove existing theme classes
    root.classList.remove('theme-light', 'theme-dark');

    // Add new theme class
    root.classList.add(`theme-${theme}`);

    // Set data-theme attribute
    root.setAttribute('data-theme', theme);

    // Update theme toggle buttons
    this.updateThemeToggles(theme);

    // Update meta theme-color
    this.updateThemeColor(theme);
  },

  /**
   * Update theme toggle buttons
   */
  updateThemeToggles() { },

  /**
   * Update meta theme-color
   */
  updateThemeColor(theme) {
    let metaThemeColor = document.querySelector('meta[name="theme-color"]');

    if (!metaThemeColor) {
      metaThemeColor = document.createElement('meta');
      metaThemeColor.name = 'theme-color';
      document.head.appendChild(metaThemeColor);
    }

    const colors = {
      light: '#0082c9',
      dark: '#1a1a1a'
    };

    metaThemeColor.content = colors[theme] || colors.light;
  },

  /**
   * Check if dark theme is active
   */
  isDarkTheme() {
    return this.getCurrentTheme() === 'dark';
  },

  /**
   * Check if light theme is active
   */
  isLightTheme() {
    return this.getCurrentTheme() === 'light';
  },

  /**
   * Get theme-specific CSS variables
   */
  getThemeVariables(theme) {
    const variables = {
      light: {
        '--color-background': '#ffffff',
        '--color-background-secondary': '#fafafa',
        '--color-background-tertiary': '#f5f5f5',
        '--color-text': '#212121',
        '--color-text-secondary': '#616161',
        '--color-text-tertiary': '#9e9e9e',
        '--color-border': '#e0e0e0',
        '--color-border-secondary': '#f5f5f5',
        '--color-shadow-light': 'rgba(0, 0, 0, 0.1)',
        '--color-shadow-medium': 'rgba(0, 0, 0, 0.15)',
        '--color-shadow-heavy': 'rgba(0, 0, 0, 0.25)'
      },
      dark: {
        '--color-background': '#1a1a1a',
        '--color-background-secondary': '#2a2a2a',
        '--color-background-tertiary': '#3a3a3a',
        '--color-text': '#e0e0e0',
        '--color-text-secondary': '#b0b0b0',
        '--color-text-tertiary': '#8a8a8a',
        '--color-border': '#3a3a3a',
        '--color-border-secondary': '#2a2a2a',
        '--color-shadow-light': 'rgba(0, 0, 0, 0.3)',
        '--color-shadow-medium': 'rgba(0, 0, 0, 0.4)',
        '--color-shadow-heavy': 'rgba(0, 0, 0, 0.6)'
      }
    };

    return variables[theme] || variables.light;
  },

  /**
   * Apply theme variables to CSS custom properties
   */
  applyThemeVariables(theme) {
    const variables = this.getThemeVariables(theme);
    const root = document.documentElement;

    Object.entries(variables).forEach(([property, value]) => {
      root.style.setProperty(property, value);
    });
  },

  /**
   * Create theme toggle button
   */
  createThemeToggle(options = {}) {
    const {
      container = document.body,
      className = 'theme-toggle',
      showText = false,
      size = 'md'
    } = options;

    const button = document.createElement('button');
    button.type = 'button';
    button.className = `btn btn--secondary ${className} ${className}--${size}`;
    button.setAttribute('data-theme-toggle', '');
    button.setAttribute('aria-label', 'Toggle theme');

    const icon = document.createElement('span');
    icon.setAttribute('data-theme-icon', '');
    icon.textContent = this.getCurrentTheme() === 'light' ? '🌙' : '☀️';

    button.appendChild(icon);

    if (showText) {
      const text = document.createElement('span');
      text.textContent = this.getCurrentTheme() === 'light' ? 'Dark' : 'Light';
      text.className = `${className}__text`;
      button.appendChild(text);
    }

    if (container) {
      container.appendChild(button);
    }

    return button;
  },

  /**
   * Get theme-aware color
   */
  getThemeAwareColor(colorName) {
    const theme = this.getCurrentTheme();
    const colorMap = {
      primary: {
        light: '#0082c9',
        dark: '#4a9eff'
      },
      background: {
        light: '#ffffff',
        dark: '#1a1a1a'
      },
      text: {
        light: '#212121',
        dark: '#e0e0e0'
      }
    };

    return colorMap[colorName]?.[theme] || colorMap[colorName]?.light || '#000000';
  },

  /**
   * Check if system supports dark mode
   */
  supportsDarkMode() {
    return window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
  },

  /**
   * Reset theme to system preference
   */
  resetToSystemTheme() { },

  /**
   * Get theme transition duration
   */
  getTransitionDuration() {
    return getComputedStyle(document.documentElement)
      .getPropertyValue('--theme-transition-duration') || '0.3s';
  },

  /**
   * Enable smooth theme transitions
   */
  enableSmoothTransitions() {
    const root = document.documentElement;
    root.style.setProperty('--theme-transition-duration', '0.3s');

    // Add transition class to body
    document.body.classList.add('theme-transitioning');

    // Remove transition class after transition completes
    setTimeout(() => {
      document.body.classList.remove('theme-transitioning');
    }, 300);
  },

  /**
   * Disable smooth theme transitions
   */
  disableSmoothTransitions() {
    const root = document.documentElement;
    root.style.setProperty('--theme-transition-duration', '0s');
    document.body.classList.remove('theme-transitioning');
  },

  /**
   * Check if user prefers reduced motion
   */
  prefersReducedMotion() {
    return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  },

  /**
   * Setup theme with motion preferences
   */
  setupWithMotionPreferences() {
    if (this.prefersReducedMotion()) {
      this.disableSmoothTransitions();
    } else {
      this.enableSmoothTransitions();
    }
  },

  /**
   * Get theme statistics
   */
  getThemeStats() {
    const savedTheme = localStorage.getItem('projectcheck-theme');
    const systemTheme = this.getSystemTheme();
    const currentTheme = this.getCurrentTheme();

    return {
      savedTheme,
      systemTheme,
      currentTheme,
      isManualOverride: savedTheme !== null,
      isSystemTheme: !savedTheme || savedTheme === systemTheme
    };
  },

  /**
   * Export theme configuration
   */
  exportThemeConfig() {
    return {
      currentTheme: this.getCurrentTheme(),
      prefersReducedMotion: this.prefersReducedMotion(),
      variables: this.getThemeVariables(this.getCurrentTheme())
    };
  },

  /**
   * Import theme configuration
   */
  importThemeConfig() { },

  /**
   * Setup theme with custom configuration
   */
  setupWithConfig() { },

  readThemeFromDocument() {
    const attr = document.documentElement.getAttribute('data-theme');
    if (attr === 'dark' || attr === 'light') return attr;
    return 'light';
  }
};

// Export for use in other modules
if (typeof module !== 'undefined' && module.exports) {
  module.exports = ProjectControlTheme;
} else if (typeof window !== 'undefined') {
  window.ProjectControlTheme = ProjectControlTheme;
}
