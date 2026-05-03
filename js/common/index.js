// Common JavaScript entry point for shared utilities and components
// This file is used by webpack to create a common bundle for shared code

// Import all common utilities
import './utils.js';
import './layout.js';
import './components.js';
import './theme.js';
import './validation.js';
import './messaging.js';
import './performance.js';
import './cache.js';

// Export common utilities for use in other modules
export { default as LayoutManager } from './layout.js';
export { default as ComponentManager } from './components.js';
export { default as ThemeManager } from './theme.js';
export { default as ValidationManager } from './validation.js';
export { default as MessageManager } from './messaging.js';
export { default as PerformanceOptimizer } from './performance.js';
export { default as CacheManager } from './cache.js';

// Initialize common functionality
document.addEventListener('DOMContentLoaded', () => {
    const themeManager = window.ThemeManager || window.ProjectCheckTheme || window.ProjectControlTheme;
    const layoutManager = window.LayoutManager || window.ProjectCheckLayout || window.ProjectControlLayout;
    const componentManager = window.ComponentManager || window.ProjectCheckComponents || window.ProjectControlComponents;
    const validationManager = window.ValidationManager || window.ProjectCheckValidation || window.ProjectControlValidation;
    const messageManager = window.MessageManager || window.ProjectCheckMessaging || window.ProjectControlMessaging;
    const performanceManager = window.PerformanceOptimizer || window.ProjectCheckPerformance || window.ProjectControlPerformance;
    const cacheManager = window.CacheManager || window.ProjectCheckCache || window.ProjectControlCache;

    // Initialize theme management
    if (themeManager && typeof themeManager.init === 'function') {
        themeManager.init();
    }
    
    // Initialize layout management
    if (layoutManager && typeof layoutManager.init === 'function') {
        layoutManager.init();
    }
    
    // Initialize component management
    if (componentManager && typeof componentManager.init === 'function') {
        componentManager.init();
    }
    
    // Initialize validation management
    if (validationManager && typeof validationManager.init === 'function') {
        validationManager.init();
    }
    
    // Initialize message management
    if (messageManager && typeof messageManager.init === 'function') {
        messageManager.init();
    }
    
    // Initialize performance optimization
    if (performanceManager && typeof performanceManager.init === 'function') {
        performanceManager.init();
    }
    
    // Initialize cache management
    if (cacheManager && typeof cacheManager.init === 'function') {
        cacheManager.init();
    }
});
