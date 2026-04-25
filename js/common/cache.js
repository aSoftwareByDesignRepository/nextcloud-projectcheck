/**
 * Caching utilities for better performance
 * Implements various caching strategies including service worker caching
 */

class CacheManager {
    constructor() {
        this.cacheName = 'projectcheck-v1';
        this.cacheStrategies = {
            cacheFirst: 'cache-first',
            networkFirst: 'network-first',
            staleWhileRevalidate: 'stale-while-revalidate',
            networkOnly: 'network-only',
            cacheOnly: 'cache-only'
        };
        this.init();
    }

    init() {
        this.setupServiceWorker();
        this.setupLocalStorage();
        this.setupSessionStorage();
        this.setupMemoryCache();
    }

    /**
     * Service worker: intentionally not registered. The previous code called
     * `navigator.serviceWorker.register('/sw.js')` (Nextcloud web root), not this app, which
     * was wrong, caused failed registrations, and could affect unrelated pages. A future SW
     * for ProjectCheck must be served under the app and reviewed for caching and CSP.
     */
    setupServiceWorker() {
    }

    /**
     * Setup local storage caching
     */
    setupLocalStorage() {
        this.localStorage = {
            set: (key, value, ttl = 3600000) => { // Default 1 hour TTL
                const item = {
                    value: value,
                    timestamp: Date.now(),
                    ttl: ttl
                };
                localStorage.setItem(key, JSON.stringify(item));
            },
            get: (key) => {
                const item = localStorage.getItem(key);
                if (!item) return null;
                
                const parsed = JSON.parse(item);
                const now = Date.now();
                
                if (now - parsed.timestamp > parsed.ttl) {
                    localStorage.removeItem(key);
                    return null;
                }
                
                return parsed.value;
            },
            remove: (key) => {
                localStorage.removeItem(key);
            },
            clear: () => {
                localStorage.clear();
            }
        };
    }

    /**
     * Setup session storage caching
     */
    setupSessionStorage() {
        this.sessionStorage = {
            set: (key, value) => {
                sessionStorage.setItem(key, JSON.stringify(value));
            },
            get: (key) => {
                const item = sessionStorage.getItem(key);
                return item ? JSON.parse(item) : null;
            },
            remove: (key) => {
                sessionStorage.removeItem(key);
            },
            clear: () => {
                sessionStorage.clear();
            }
        };
    }

    /**
     * Setup memory cache
     */
    setupMemoryCache() {
        this.memoryCache = new Map();
        this.memoryCacheTTL = new Map();
        
        // Clean up expired cache entries every 5 minutes
        setInterval(() => {
            this.cleanupMemoryCache();
        }, 300000);
    }

    /**
     * Clean up expired memory cache entries
     */
    cleanupMemoryCache() {
        const now = Date.now();
        for (const [key, ttl] of this.memoryCacheTTL) {
            if (now > ttl) {
                this.memoryCache.delete(key);
                this.memoryCacheTTL.delete(key);
            }
        }
    }

    /**
     * Set memory cache entry
     */
    setMemoryCache(key, value, ttl = 300000) { // Default 5 minutes
        this.memoryCache.set(key, value);
        this.memoryCacheTTL.set(key, Date.now() + ttl);
    }

    /**
     * Get memory cache entry
     */
    getMemoryCache(key) {
        const ttl = this.memoryCacheTTL.get(key);
        if (ttl && Date.now() > ttl) {
            this.memoryCache.delete(key);
            this.memoryCacheTTL.delete(key);
            return null;
        }
        return this.memoryCache.get(key);
    }

    /**
     * Cache API data with different strategies
     */
    async cacheAPI(url, options = {}) {
        const strategy = options.strategy || this.cacheStrategies.networkFirst;
        const ttl = options.ttl || 300000; // 5 minutes default
        const cacheKey = `api:${url}`;

        switch (strategy) {
            case this.cacheStrategies.cacheFirst:
                return this.cacheFirst(url, cacheKey, ttl);
            case this.cacheStrategies.networkFirst:
                return this.networkFirst(url, cacheKey, ttl);
            case this.cacheStrategies.staleWhileRevalidate:
                return this.staleWhileRevalidate(url, cacheKey, ttl);
            case this.cacheStrategies.networkOnly:
                return this.networkOnly(url);
            case this.cacheStrategies.cacheOnly:
                return this.cacheOnly(cacheKey);
            default:
                return this.networkFirst(url, cacheKey, ttl);
        }
    }

    /**
     * Cache-first strategy
     */
    async cacheFirst(url, cacheKey, ttl) {
        // Try cache first
        const cached = this.getMemoryCache(cacheKey);
        if (cached) {
            return cached;
        }

        // If not in cache, fetch from network
        try {
            const response = await fetch(url);
            const data = await response.json();
            
            // Cache the result
            this.setMemoryCache(cacheKey, data, ttl);
            
            return data;
        } catch (error) {
            console.error('Cache-first strategy failed:', error);
            throw error;
        }
    }

    /**
     * Network-first strategy
     */
    async networkFirst(url, cacheKey, ttl) {
        try {
            // Try network first
            const response = await fetch(url);
            const data = await response.json();
            
            // Cache the result
            this.setMemoryCache(cacheKey, data, ttl);
            
            return data;
        } catch (error) {
            // If network fails, try cache
            const cached = this.getMemoryCache(cacheKey);
            if (cached) {
                return cached;
            }
            
            console.error('Network-first strategy failed:', error);
            throw error;
        }
    }

    /**
     * Stale-while-revalidate strategy
     */
    async staleWhileRevalidate(url, cacheKey, ttl) {
        // Return cached data immediately if available
        const cached = this.getMemoryCache(cacheKey);
        
        // Fetch fresh data in background
        fetch(url)
            .then(response => response.json())
            .then(data => {
                this.setMemoryCache(cacheKey, data, ttl);
            })
            .catch(error => {
                console.error('Background fetch failed:', error);
            });
        
        if (cached) {
            return cached;
        }
        
        // If no cached data, wait for network response
        try {
            const response = await fetch(url);
            const data = await response.json();
            
            this.setMemoryCache(cacheKey, data, ttl);
            return data;
        } catch (error) {
            console.error('Stale-while-revalidate strategy failed:', error);
            throw error;
        }
    }

    /**
     * Network-only strategy
     */
    async networkOnly(url) {
        const response = await fetch(url);
        return response.json();
    }

    /**
     * Cache-only strategy
     */
    async cacheOnly(cacheKey) {
        const cached = this.getMemoryCache(cacheKey);
        if (!cached) {
            throw new Error('Data not found in cache');
        }
        return cached;
    }

    /**
     * Cache form data
     */
    cacheFormData(formId, data) {
        const key = `form:${formId}`;
        this.localStorage.set(key, data, 1800000); // 30 minutes TTL
    }

    /**
     * Get cached form data
     */
    getCachedFormData(formId) {
        const key = `form:${formId}`;
        return this.localStorage.get(key);
    }

    /**
     * Clear cached form data
     */
    clearCachedFormData(formId) {
        const key = `form:${formId}`;
        this.localStorage.remove(key);
    }

    /**
     * Cache user preferences
     */
    cacheUserPreferences(preferences) {
        this.localStorage.set('user-preferences', preferences, 86400000); // 24 hours TTL
    }

    /**
     * Get cached user preferences
     */
    getCachedUserPreferences() {
        return this.localStorage.get('user-preferences') || {};
    }

    /**
     * Cache page data
     */
    cachePageData(pageId, data) {
        const key = `page:${pageId}`;
        this.sessionStorage.set(key, data);
    }

    /**
     * Get cached page data
     */
    getCachedPageData(pageId) {
        const key = `page:${pageId}`;
        return this.sessionStorage.get(key);
    }

    /**
     * Clear all caches
     */
    clearAllCaches() {
        this.memoryCache.clear();
        this.memoryCacheTTL.clear();
        this.localStorage.clear();
        this.sessionStorage.clear();
        
        // Clear service worker cache
        if ('caches' in window) {
            caches.keys().then(cacheNames => {
                return Promise.all(
                    cacheNames.map(cacheName => {
                        if (cacheName.startsWith('projectcheck-')) {
                            return caches.delete(cacheName);
                        }
                    })
                );
            });
        }
    }

    /**
     * Get cache statistics
     */
    getCacheStats() {
        return {
            memoryCacheSize: this.memoryCache.size,
            localStorageSize: localStorage.length,
            sessionStorageSize: sessionStorage.length,
            memoryCacheKeys: Array.from(this.memoryCache.keys()),
            localStorageKeys: Object.keys(localStorage),
            sessionStorageKeys: Object.keys(sessionStorage)
        };
    }

    /**
     * Preload critical resources
     */
    async preloadResources(resources) {
        const promises = resources.map(resource => {
            if (resource.type === 'api') {
                return this.cacheAPI(resource.url, { strategy: this.cacheStrategies.cacheFirst });
            } else if (resource.type === 'image') {
                return this.preloadImage(resource.url);
            } else if (resource.type === 'script') {
                return this.preloadScript(resource.url);
            } else if (resource.type === 'style') {
                return this.preloadStyle(resource.url);
            }
        });
        
        return Promise.all(promises);
    }

    /**
     * Preload image
     */
    preloadImage(url) {
        return new Promise((resolve, reject) => {
            const img = new Image();
            img.onload = () => resolve(url);
            img.onerror = () => reject(new Error(`Failed to preload image: ${url}`));
            img.src = url;
        });
    }

    /**
     * Preload script
     */
    preloadScript(url) {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');
            script.onload = () => resolve(url);
            script.onerror = () => reject(new Error(`Failed to preload script: ${url}`));
            script.src = url;
            document.head.appendChild(script);
        });
    }

    /**
     * Preload style
     */
    preloadStyle(url) {
        return new Promise((resolve, reject) => {
            const link = document.createElement('link');
            link.rel = 'stylesheet';
            link.onload = () => resolve(url);
            link.onerror = () => reject(new Error(`Failed to preload style: ${url}`));
            link.href = url;
            document.head.appendChild(link);
        });
    }
}

// Initialize cache manager
const cacheManager = new CacheManager();

// Export for use in other modules
export default CacheManager;

// Make available globally
if (typeof window !== 'undefined') {
    window.CacheManager = CacheManager;
    window.cacheManager = cacheManager;
}
