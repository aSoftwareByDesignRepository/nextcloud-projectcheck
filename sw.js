// Service Worker for the ProjectCheck app
// Handles caching, offline functionality, and performance optimization

const CACHE_NAME = 'projectcheck-v1';
const STATIC_CACHE = 'projectcheck-static-v1';
const DYNAMIC_CACHE = 'projectcheck-dynamic-v1';

// Files to cache immediately
const STATIC_FILES = [
    '/css/common/critical.css',
    '/css/common/base.css',
    '/css/common/colors.css',
    '/css/common/typography.css',
    '/js/common/index.js',
    '/js/common/utils.js',
    '/js/common/layout.js',
    '/js/common/components.js',
    '/js/common/theme.js',
    '/js/common/validation.js',
    '/js/common/messaging.js',
    '/js/common/performance.js',
    '/js/common/cache.js',
    '/img/placeholder.png',
    '/img/logo.png',
    '/img/icons/',
    '/templates/common/layout.php',
    '/templates/common/header.php',
    '/templates/common/footer.php',
    '/templates/common/navigation.php'
];

// API endpoints to cache
const API_CACHE_PATTERNS = [
    '/api/projects',
    '/api/customers',
    '/api/time-entries',
    '/api/dashboard',
    '/api/settings'
];

// Install event - cache static files
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(STATIC_CACHE)
            .then(cache => {
                return cache.addAll(STATIC_FILES);
            })
            .then(() => {
                return self.skipWaiting();
            })
            .catch(error => {
                console.error('ProjectCheck SW: failed to cache static files', error);
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
    return request.headers.get('accept').includes('text/html');
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
        
        // Return offline page for HTML requests
        if (isHTMLRequest(request)) {
            return caches.match('/offline.html');
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
        
        // Return offline page for HTML requests
        if (isHTMLRequest(request)) {
            return caches.match('/offline.html');
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
        
        // Return offline page for HTML requests
        if (isHTMLRequest(request)) {
            return caches.match('/offline.html');
        }
        
        throw error;
    }
}

// Background sync for offline actions
self.addEventListener('sync', event => {
    if (event.tag === 'background-sync') {
        event.waitUntil(performBackgroundSync());
    }
});

// Perform background sync
async function performBackgroundSync() {
    try {
        // Get pending offline actions from IndexedDB
        const pendingActions = await getPendingActions();
        
        for (const action of pendingActions) {
            try {
                await performAction(action);
                await removePendingAction(action.id);
            } catch (error) {
                console.error('Failed to perform background action:', error);
            }
        }
    } catch (error) {
        console.error('Background sync failed:', error);
    }
}

// Get pending actions from IndexedDB
async function getPendingActions() {
    // This would typically use IndexedDB to store pending actions
    // For now, return empty array
    return [];
}

// Perform a pending action
async function performAction(action) {
    const response = await fetch(action.url, {
        method: action.method,
        headers: action.headers,
        body: action.body
    });
    
    if (!response.ok) {
        throw new Error(`Action failed: ${response.status}`);
    }
    
    return response;
}

// Remove pending action from IndexedDB
async function removePendingAction(actionId) {
    // Placeholder for IndexedDB removal when offline queue is implemented
    void actionId;
}

// Push notification handling
self.addEventListener('push', event => {
    const options = {
        body: event.data ? event.data.text() : 'New notification',
        icon: '/img/notification-icon.png',
        badge: '/img/badge-icon.png',
        vibrate: [100, 50, 100],
        data: {
            dateOfArrival: Date.now(),
            primaryKey: 1
        },
        actions: [
            {
                action: 'explore',
                title: 'View',
                icon: '/img/checkmark.png'
            },
            {
                action: 'close',
                title: 'Close',
                icon: '/img/xmark.png'
            }
        ]
    };
    
    event.waitUntil(
        self.registration.showNotification('ProjectCheck', options)
    );
});

// Notification click handling
self.addEventListener('notificationclick', event => {
    event.notification.close();
    
    if (event.action === 'explore') {
        event.waitUntil(
            clients.openWindow('/')
        );
    }
});

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
