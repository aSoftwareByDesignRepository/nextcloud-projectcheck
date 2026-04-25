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
    // Initialize theme management
    if (window.ThemeManager) {
        window.ThemeManager.init();
    }
    
    // Initialize layout management
    if (window.LayoutManager) {
        window.LayoutManager.init();
    }
    
    // Initialize component management
    if (window.ComponentManager) {
        window.ComponentManager.init();
    }
    
    // Initialize validation management
    if (window.ValidationManager) {
        window.ValidationManager.init();
    }
    
    // Initialize message management
    if (window.MessageManager) {
        window.MessageManager.init();
    }
    
    // Initialize performance optimization
    if (window.PerformanceOptimizer) {
        window.PerformanceOptimizer.init();
    }
    
    // Initialize cache management
    if (window.CacheManager) {
        window.CacheManager.init();
    }
});
