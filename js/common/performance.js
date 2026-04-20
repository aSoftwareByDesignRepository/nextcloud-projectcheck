/**
 * Performance optimization utilities
 * Handles lazy loading, resource optimization, and performance monitoring
 */

class PerformanceOptimizer {
    constructor() {
        this.observers = new Map();
        this.lazyElements = new Set();
        this.init();
    }

    init() {
        this.setupLazyLoading();
        this.setupIntersectionObserver();
        this.setupPerformanceMonitoring();
        this.optimizeImages();
        this.setupResourceHints();
    }

    /**
     * Setup lazy loading for images and components
     */
    setupLazyLoading() {
        // Lazy load images
        const lazyImages = document.querySelectorAll('img[data-src]');
        lazyImages.forEach(img => {
            this.lazyElements.add(img);
            img.classList.add('lazy');
        });

        // Lazy load background images
        const lazyBackgrounds = document.querySelectorAll('[data-background]');
        lazyBackgrounds.forEach(element => {
            this.lazyElements.add(element);
            element.classList.add('lazy-bg');
        });

        // Lazy load components
        const lazyComponents = document.querySelectorAll('[data-lazy-component]');
        lazyComponents.forEach(component => {
            this.lazyElements.add(component);
            component.classList.add('lazy-component');
        });
    }

    /**
     * Setup intersection observer for lazy loading
     */
    setupIntersectionObserver() {
        if (!('IntersectionObserver' in window)) {
            // Fallback for older browsers
            this.loadAllLazyElements();
            return;
        }

        const observerOptions = {
            root: null,
            rootMargin: '50px',
            threshold: 0.1
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    this.loadElement(entry.target);
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        this.lazyElements.forEach(element => {
            observer.observe(element);
        });

        this.observers.set('lazy', observer);
    }

    /**
     * Load a lazy element
     */
    loadElement(element) {
        if (element.classList.contains('lazy')) {
            // Load image
            const src = element.getAttribute('data-src');
            if (src) {
                element.src = src;
                element.removeAttribute('data-src');
                element.classList.remove('lazy');
            }
        } else if (element.classList.contains('lazy-bg')) {
            // Load background image
            const background = element.getAttribute('data-background');
            if (background) {
                element.style.backgroundImage = `url(${background})`;
                element.removeAttribute('data-background');
                element.classList.remove('lazy-bg');
            }
        } else if (element.classList.contains('lazy-component')) {
            // Load component
            const componentType = element.getAttribute('data-lazy-component');
            this.loadComponent(element, componentType);
        }
    }

    /**
     * Load a lazy component
     */
    loadComponent(element, componentType) {
        // Load component based on type
        switch (componentType) {
            case 'chart':
                this.loadChartComponent(element);
                break;
            case 'table':
                this.loadTableComponent(element);
                break;
            case 'form':
                this.loadFormComponent(element);
                break;
            default:
                console.warn(`Unknown lazy component type: ${componentType}`);
        }
    }

    /**
     * Load chart component
     */
    loadChartComponent(element) {
        // Dynamically import Chart.js if needed
        if (typeof Chart === 'undefined') {
            import('chart.js').then(({ Chart }) => {
                this.initializeChart(element);
            });
        } else {
            this.initializeChart(element);
        }
    }

    /**
     * Initialize chart
     */
    initializeChart(element) {
        const ctx = element.getContext('2d');
        const data = JSON.parse(element.getAttribute('data-chart-data') || '{}');
        const options = JSON.parse(element.getAttribute('data-chart-options') || '{}');
        
        new Chart(ctx, {
            type: element.getAttribute('data-chart-type') || 'line',
            data: data,
            options: options
        });
    }

    /**
     * Load table component
     */
    loadTableComponent(element) {
        const data = JSON.parse(element.getAttribute('data-table-data') || '[]');
        const columns = JSON.parse(element.getAttribute('data-table-columns') || '[]');
        
        // Render table
        element.innerHTML = this.renderTable(data, columns);
        element.classList.remove('lazy-component');
    }

    /**
     * Render table HTML
     */
    renderTable(data, columns) {
        let html = '<table class="table">';
        
        // Header
        html += '<thead><tr>';
        columns.forEach(column => {
            html += `<th>${column.label}</th>`;
        });
        html += '</tr></thead>';
        
        // Body
        html += '<tbody>';
        data.forEach(row => {
            html += '<tr>';
            columns.forEach(column => {
                html += `<td>${row[column.key] || ''}</td>`;
            });
            html += '</tr>';
        });
        html += '</tbody></table>';
        
        return html;
    }

    /**
     * Load form component
     */
    loadFormComponent(element) {
        const formType = element.getAttribute('data-form-type');
        const formData = JSON.parse(element.getAttribute('data-form-data') || '{}');
        
        // Render form
        element.innerHTML = this.renderForm(formType, formData);
        element.classList.remove('lazy-component');
    }

    /**
     * Render form HTML
     */
    renderForm(type, data) {
        // Basic form rendering - can be extended
        return `<form class="form" data-form-type="${type}">
            <div class="form-group">
                <label class="form-label">Form loaded dynamically</label>
                <input type="text" class="form-control" placeholder="Dynamic form field">
            </div>
        </form>`;
    }

    /**
     * Load all lazy elements (fallback)
     */
    loadAllLazyElements() {
        this.lazyElements.forEach(element => {
            this.loadElement(element);
        });
    }

    /**
     * Optimize images
     */
    optimizeImages() {
        const images = document.querySelectorAll('img');
        images.forEach(img => {
            // Add loading="lazy" for native lazy loading
            if (!img.hasAttribute('loading')) {
                img.setAttribute('loading', 'lazy');
            }
            
            // Add decoding="async" for better performance
            if (!img.hasAttribute('decoding')) {
                img.setAttribute('decoding', 'async');
            }
            
            // Add error handling
            img.addEventListener('error', () => {
                img.src = '/img/placeholder.png';
                img.alt = 'Image not available';
            });
        });
    }

    /**
     * Setup resource hints
     */
    setupResourceHints() {
        // Add preload hints for critical resources
        const criticalResources = [
            '/css/common/critical.css',
            '/js/common/index.js'
        ];

        criticalResources.forEach(resource => {
            const link = document.createElement('link');
            link.rel = 'preload';
            link.href = resource;
            link.as = resource.endsWith('.css') ? 'style' : 'script';
            document.head.appendChild(link);
        });

        // Add prefetch hints for likely next pages
        const likelyPages = [
            '/projects',
            '/customers',
            '/time-entries'
        ];

        likelyPages.forEach(page => {
            const link = document.createElement('link');
            link.rel = 'prefetch';
            link.href = page;
            document.head.appendChild(link);
        });
    }

    /**
     * Setup performance monitoring
     */
    setupPerformanceMonitoring() {
        // Monitor Core Web Vitals
        if ('PerformanceObserver' in window) {
            // Monitor Largest Contentful Paint (LCP)
            const lcpObserver = new PerformanceObserver((list) => {
                const entries = list.getEntries();
                const lastEntry = entries[entries.length - 1];
                this.reportMetric('LCP', lastEntry.startTime);
            });
            lcpObserver.observe({ entryTypes: ['largest-contentful-paint'] });

            // Monitor First Input Delay (FID)
            const fidObserver = new PerformanceObserver((list) => {
                const entries = list.getEntries();
                entries.forEach((entry) => {
                    const fid = entry.processingStart - entry.startTime;
                    this.reportMetric('FID', fid);
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
                this.reportMetric('CLS', clsValue);
            });
            clsObserver.observe({ entryTypes: ['layout-shift'] });
        }

        // Monitor resource loading
        window.addEventListener('load', () => {
            if ('performance' in window) {
                const navigation = performance.getEntriesByType('navigation')[0];
                if (navigation) {
                    this.reportMetric('PageLoadTime', navigation.loadEventEnd - navigation.loadEventStart);
                    this.reportMetric('DOMContentLoaded', navigation.domContentLoadedEventEnd - navigation.domContentLoadedEventStart);
                }
            }
        });
    }

    /**
     * Report performance metric
     */
    reportMetric(name, value) {
        // Send to analytics or log
        console.log(`Performance Metric - ${name}:`, value);
        
        // You can send to your analytics service here
        // analytics.track('performance_metric', { name, value });
    }

    /**
     * Cleanup observers
     */
    destroy() {
        this.observers.forEach(observer => {
            observer.disconnect();
        });
        this.observers.clear();
        this.lazyElements.clear();
    }
}

// Initialize performance optimizer
const performanceOptimizer = new PerformanceOptimizer();

// Export for use in other modules
export default PerformanceOptimizer;

// Make available globally
if (typeof window !== 'undefined') {
    window.PerformanceOptimizer = PerformanceOptimizer;
    window.performanceOptimizer = performanceOptimizer;
}
