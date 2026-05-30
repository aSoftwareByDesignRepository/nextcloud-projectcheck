// Service Worker for the ProjectCheck app
// Handles runtime caching and offline fallback. We deliberately avoid
// precaching by URL because Nextcloud serves all assets through its asset
// pipeline (with a webroot prefix and content-hashed bundle names) so the
// effective URLs are not knowable at SW install time. Caching is therefore
// driven by the network (cache-first for static files, network-first for
// dynamic content) which mirrors how Nextcloud's own apps behave.

const STATIC_CACHE = 'projectcheck-static-v2';
const DYNAMIC_CACHE = 'projectcheck-dynamic-v2';

// Only precache the offline fallback page; everything else is cached at
// fetch time. The SW is scoped to /apps/projectcheck/ so this resolves to
// /apps/projectcheck/offline.html which is shipped with the app.
const PRECACHE_FALLBACKS = [
    'offline.html'
];

// API endpoints to cache (paths are matched by suffix regardless of webroot)
const API_CACHE_PATTERNS = [
    '/apps/projectcheck/api/',
];

// Install event - prime the offline fallback only.
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => cache.addAll(PRECACHE_FALLBACKS))
            .then(() => self.skipWaiting())
            .catch(error => {
                console.error('ProjectCheck SW: failed to prime fallback cache', error);
            })
    );
});

// Activate event: drop superseded app caches (previous names or old versions)
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
            .then(cacheNames => {
                const stale = cacheNames.filter((name) => {
                    if (name === STATIC_CACHE || name === DYNAMIC_CACHE) {
                        return false;
                    }
                    return name.startsWith('projectcheck-') || name.startsWith('projectcontrol-');
                });
                return Promise.all(stale.map((name) => caches.delete(name)));
            })
            .then(() => {
                return self.clients.claim();
            })
    );
});

// Fetch event - handle requests
self.addEventListener('fetch', event => {
    const { request } = event;
    const url = new URL(request.url);
    
    // Skip non-GET requests
    if (request.method !== 'GET') {
        return;
    }
    
    // Handle different types of requests
    if (isStaticFile(url.pathname)) {
        event.respondWith(cacheFirst(request, STATIC_CACHE));
    } else if (isAPIRequest(url.pathname)) {
        event.respondWith(networkFirst(request, DYNAMIC_CACHE));
    } else if (isHTMLRequest(request)) {
        event.respondWith(networkFirst(request, DYNAMIC_CACHE));
    } else {
        event.respondWith(networkOnly(request));
    }
});

// Check if request is for a static file
function isStaticFile(pathname) {
    return pathname.match(/\.(css|js|png|jpg|jpeg|gif|svg|woff|woff2|eot|ttf|otf|ico)$/);
}

// Check if request is for an API endpoint
function isAPIRequest(pathname) {
    return API_CACHE_PATTERNS.some(pattern => pathname.startsWith(pattern));
}

// Check if request is for HTML content
function isHTMLRequest(request) {
    const accept = request.headers.get('accept') || '';
    return accept.includes('text/html');
}

// Cache-first strategy for static files
async function cacheFirst(request, cacheName) {
    try {
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }
        
        const networkResponse = await fetch(request);
        if (networkResponse.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.error('Cache-first strategy failed:', error);

        if (isHTMLRequest(request)) {
            const fallback = await caches.match('offline.html');
            if (fallback) {
                return fallback;
            }
        }

        throw error;
    }
}

// Network-first strategy for dynamic content
async function networkFirst(request, cacheName) {
    try {
        const networkResponse = await fetch(request);
        
        if (networkResponse.ok) {
            const cache = await caches.open(cacheName);
            cache.put(request, networkResponse.clone());
        }
        
        return networkResponse;
    } catch (error) {
        console.error('Network-first strategy failed:', error);
        
        const cachedResponse = await caches.match(request);
        if (cachedResponse) {
            return cachedResponse;
        }

        if (isHTMLRequest(request)) {
            const fallback = await caches.match('offline.html');
            if (fallback) {
                return fallback;
            }
        }

        throw error;
    }
}

// Network-only strategy
async function networkOnly(request) {
    try {
        return await fetch(request);
    } catch (error) {
        console.error('Network-only strategy failed:', error);

        if (isHTMLRequest(request)) {
            const fallback = await caches.match('offline.html');
            if (fallback) {
                return fallback;
            }
        }

        throw error;
    }
}

// Message handling from main thread
self.addEventListener('message', event => {
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'CACHE_URLS') {
        event.waitUntil(
            caches.open(DYNAMIC_CACHE)
                .then(cache => {
                    return cache.addAll(event.data.urls);
                })
        );
    }
    
    if (event.data && event.data.type === 'DELETE_CACHE') {
        event.waitUntil(
            caches.delete(event.data.cacheName)
        );
    }
});

// Error handling
self.addEventListener('error', event => {
    console.error('Service Worker error:', event.error);
});

self.addEventListener('unhandledrejection', event => {
    console.error('Service Worker unhandled rejection:', event.reason);
});
