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

// Add performance monitoring
if (typeof window !== 'undefined') {
    // Monitor Core Web Vitals
    if ('PerformanceObserver' in window) {
        // Monitor Largest Contentful Paint (LCP)
        const lcpObserver = new PerformanceObserver((list) => {
            const entries = list.getEntries();
            const lastEntry = entries[entries.length - 1];
            console.log('LCP:', lastEntry.startTime);
        });
        lcpObserver.observe({ entryTypes: ['largest-contentful-paint'] });
        
        // Monitor First Input Delay (FID)
        const fidObserver = new PerformanceObserver((list) => {
            const entries = list.getEntries();
            entries.forEach((entry) => {
                console.log('FID:', entry.processingStart - entry.startTime);
            });
        });
        fidObserver.observe({ entryTypes: ['first-input'] });
        
        // Monitor Cumulative Layout Shift (CLS)
        const clsObserver = new PerformanceObserver((list) => {
            let clsValue = 0;
            const entries = list.getEntries();
            entries.forEach((entry) => {
                if (!entry.hadRecentInput) {
                    clsValue += entry.value;
                }
            });
            console.log('CLS:', clsValue);
        });
        clsObserver.observe({ entryTypes: ['layout-shift'] });
    }
    
    // Monitor resource loading performance
    window.addEventListener('load', () => {
        if ('performance' in window) {
            const navigation = performance.getEntriesByType('navigation')[0];
            if (navigation) {
                console.log('Page Load Time:', navigation.loadEventEnd - navigation.loadEventStart);
                console.log('DOM Content Loaded:', navigation.domContentLoadedEventEnd - navigation.domContentLoadedEventStart);
            }
        }
    });
}
