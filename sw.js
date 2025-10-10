// EMA Camp Service Worker - Cross Platform Support
const CACHE_NAME = 'ema-camp-v3.0';
const OFFLINE_URL = './modules/dashboard/';

// Get dynamic base path
const getBasePath = () => {
    const currentScript = self.location.pathname;
    return currentScript.replace('/sw.js', '');
};

const basePath = getBasePath();

// URLs to cache
const urlsToCache = [
    basePath + '/',
    basePath + '/index.php',
    basePath + '/modules/dashboard/',
    basePath + '/modules/dashboard/index.php',
    basePath + '/modules/schedule/',
    basePath + '/modules/members/',
    basePath + '/modules/finance/',
    basePath + '/modules/notifications/',
    basePath + '/assets/css/style.css',
    basePath + '/assets/js/app.js',
    basePath + '/assets/images/ema-logo.png',
    basePath + '/assets/images/ema-logo2.png',
    'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css',
    'https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.2/css/bootstrap.min.css'
];

// Install Service Worker
self.addEventListener('install', function(event) {
    console.log('EMA Camp SW installing with base path:', basePath);
    
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(function(cache) {
                console.log('EMA Camp: Caching app shell');
                return cache.addAll(urlsToCache.filter(url => 
                    url.startsWith('http') || url.startsWith(basePath)
                ));
            })
            .then(() => {
                console.log('EMA Camp: Install complete');
                return self.skipWaiting();
            })
            .catch(function(error) {
                console.error('EMA Camp: Install failed', error);
            })
    );
});

// Activate Service Worker
self.addEventListener('activate', function(event) {
    console.log('EMA Camp SW activating');
    
    event.waitUntil(
        caches.keys()
            .then(function(cacheNames) {
                return Promise.all(
                    cacheNames.map(function(cacheName) {
                        if (cacheName !== CACHE_NAME) {
                            console.log('EMA Camp: Deleting old cache:', cacheName);
                            return caches.delete(cacheName);
                        }
                    })
                );
            })
            .then(() => {
                console.log('EMA Camp: Activation complete');
                return self.clients.claim();
            })
    );
});

// Fetch Strategy
self.addEventListener('fetch', function(event) {
    // Skip non-GET requests
    if (event.request.method !== 'GET') {
        return;
    }
    
    // Skip cross-origin requests
    if (!event.request.url.startsWith(self.location.origin)) {
        return;
    }
    
    event.respondWith(
        caches.match(event.request)
            .then(function(response) {
                // Return cached version if available
                if (response) {
                    console.log('Serving from cache:', event.request.url);
                    return response;
                }
                
                // Network first for API calls and dynamic content
                return fetch(event.request.clone())
                    .then(function(response) {
                        // Check if valid response
                        if (!response || response.status !== 200 || response.type !== 'basic') {
                            return response;
                        }
                        
                        // Clone and cache the response
                        const responseToCache = response.clone();
                        caches.open(CACHE_NAME)
                            .then(function(cache) {
                                cache.put(event.request, responseToCache);
                            });
                        
                        return response;
                    })
                    .catch(function(error) {
                        console.log('Network request failed:', error);
                        
                        // Return offline page for navigation requests
                        if (event.request.destination === 'document') {
                            return caches.match(OFFLINE_URL);
                        }
                        
                        // Return cached version if available
                        return caches.match(event.request);
                    });
            })
    );
});

// Background Sync
self.addEventListener('sync', function(event) {
    console.log('Background sync:', event.tag);
    
    if (event.tag === 'background-sync') {
        event.waitUntil(doBackgroundSync());
    }
});

function doBackgroundSync() {
    return new Promise(function(resolve) {
        console.log('EMA Camp: Performing background sync');
        // Add your background sync logic here
        resolve();
    });
}

// Push Notifications
self.addEventListener('push', function(event) {
    console.log('Push notification received');
    
    let notificationData = {
        title: 'EMA Camp Notification',
        body: 'Anda memiliki update baru',
        icon: basePath + '/assets/images/ema-logo.png',
        badge: basePath + '/assets/images/ema-logo.png'
    };
    
    if (event.data) {
        try {
            notificationData = { ...notificationData, ...event.data.json() };
        } catch (e) {
            notificationData.body = event.data.text();
        }
    }
    
    const options = {
        body: notificationData.body,
        icon: notificationData.icon,
        badge: notificationData.badge,
        vibrate: [100, 50, 100],
        data: {
            dateOfArrival: Date.now(),
            primaryKey: notificationData.primaryKey || 1,
            url: notificationData.url || basePath + '/modules/dashboard/'
        },
        actions: [
            {
                action: 'explore',
                title: 'Open App',
                icon: basePath + '/assets/images/ema-logo.png'
            },
            {
                action: 'close',
                title: 'Close'
            }
        ],
        tag: 'ema-camp-notification',
        requireInteraction: false,
        silent: false
    };
    
    event.waitUntil(
        self.registration.showNotification(notificationData.title || 'EMA Camp', options)
    );
});

// Notification Click Handler
self.addEventListener('notificationclick', function(event) {
    event.notification.close();
    
    const targetUrl = event.notification.data.url || basePath + '/modules/dashboard/';
    
    if (event.action === 'explore') {
        event.waitUntil(
            clients.matchAll({ type: 'window', includeUncontrolled: true })
                .then(function(clientList) {
                    // Check if there's already a window/tab open with the target URL
                    for (let client of clientList) {
                        if (client.url.includes(basePath) && 'focus' in client) {
                            return client.focus();
                        }
                    }
                    // If no existing window found, open new one
                    if (clients.openWindow) {
                        return clients.openWindow(targetUrl);
                    }
                })
        );
    } else if (event.action === 'close') {
        // Just close the notification
        return;
    } else {
        // Default action (click on notification body)
        event.waitUntil(
            clients.openWindow(targetUrl)
        );
    }
});

// Message Handler (for communication with main app)
self.addEventListener('message', function(event) {
    console.log('SW received message:', event.data);
    
    if (event.data && event.data.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    
    if (event.data && event.data.type === 'GET_VERSION') {
        event.ports[0].postMessage({ version: CACHE_NAME });
    }
});

// Periodic Background Sync (if supported)
self.addEventListener('periodicsync', function(event) {
    console.log('Periodic background sync:', event.tag);
    
    if (event.tag === 'ema-camp-sync') {
        event.waitUntil(doPeriodicSync());
    }
});

function doPeriodicSync() {
    return new Promise(function(resolve) {
        console.log('EMA Camp: Performing periodic sync');
        // Add periodic sync logic here (e.g., sync notifications, update cache)
        resolve();
    });
}

// Error Handler
self.addEventListener('error', function(event) {
    console.error('Service Worker error:', event.error);
});

// Unhandled Rejection Handler
self.addEventListener('unhandledrejection', function(event) {
    console.error('Service Worker unhandled rejection:', event.reason);
});

console.log('EMA Camp Service Worker loaded successfully');