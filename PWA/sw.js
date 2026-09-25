// Service Worker for Support Services System PWA
const CACHE_NAME = 'sss-pwa-v1';
const OFFLINE_PAGE = '/THESIS/SUPPORTSERVICESYSTEM/PWA/offline.html';

// Static assets to cache during installation
const STATIC_ASSETS = [
  '/THESIS/SUPPORTSERVICESYSTEM/index.php',
  '/THESIS/SUPPORTSERVICESYSTEM/PWA/offline.html',
  '/THESIS/SUPPORTSERVICESYSTEM/assets/css/',
  '/THESIS/SUPPORTSERVICESYSTEM/assets/js/',
  '/THESIS/SUPPORTSERVICESYSTEM/IMG%20ASSETS/passlogo.png',
  '/THESIS/SUPPORTSERVICESYSTEM/IMG%20ASSETS/passlogo1.png'
];

// Installation event - cache static assets
self.addEventListener('install', (event) => {
  console.log('[Service Worker] Installing...');
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => {
        console.log('[Service Worker] Caching static assets');
        // Cache main offline page
        return cache.addAll([OFFLINE_PAGE])
          .catch((error) => {
            console.warn('[Service Worker] Failed to cache offline page:', error);
          });
      })
      .then(() => self.skipWaiting())
      .catch((error) => {
        console.error('[Service Worker] Installation error:', error);
      })
  );
});

// Activation event - clean up old caches
self.addEventListener('activate', (event) => {
  console.log('[Service Worker] Activating...');
  event.waitUntil(
    caches.keys()
      .then((cacheNames) => {
        return Promise.all(
          cacheNames.map((cacheName) => {
            if (cacheName !== CACHE_NAME) {
              console.log('[Service Worker] Deleting old cache:', cacheName);
              return caches.delete(cacheName);
            }
          })
        );
      })
      .then(() => self.clients.claim())
  );
});

// Fetch event - Network First strategy with Cache fallback
self.addEventListener('fetch', (event) => {
  const { request } = event;
  const url = new URL(request.url);

  // Skip cross-origin requests and certain file types
  if (url.origin !== location.origin) {
    return;
  }

  // Skip requests to /admin or sensitive paths
  if (url.pathname.includes('/admin') || url.pathname.includes('/user-data')) {
    return;
  }

  // Network first strategy for HTML/PHP pages
  if (request.method === 'GET') {
    event.respondWith(
      fetch(request)
        .then((response) => {
          // Don't cache error responses
          if (!response || response.status !== 200 || response.type === 'error') {
            return response;
          }

          // Clone the response
          const responseClone = response.clone();

          // Cache successful responses
          caches.open(CACHE_NAME)
            .then((cache) => {
              cache.put(request, responseClone);
            })
            .catch((error) => {
              console.warn('[Service Worker] Cache put error:', error);
            });

          return response;
        })
        .catch((error) => {
          console.log('[Service Worker] Network request failed:', error);

          // Return cached response if available
          return caches.match(request)
            .then((response) => {
              if (response) {
                return response;
              }

              // Return offline page as fallback for navigation requests
              if (request.mode === 'navigate') {
                return caches.match(OFFLINE_PAGE);
              }

              // Return a basic error response
              return new Response('Offline - Resource not available', {
                status: 503,
                statusText: 'Service Unavailable',
                headers: new Headers({
                  'Content-Type': 'text/plain'
                })
              });
            });
        })
    );
  }
});

// Handle messages from clients (for skip waiting and other commands)
self.addEventListener('message', (event) => {
  if (event.data && event.data.type === 'SKIP_WAITING') {
    self.skipWaiting();
  }

  if (event.data && event.data.type === 'GET_VERSION') {
    event.ports[0].postMessage({ version: CACHE_NAME });
  }
});

// Periodic background sync (optional - for notifications)
self.addEventListener('sync', (event) => {
  if (event.tag === 'sync-appointments') {
    event.waitUntil(
      // Sync appointments data when back online
      fetch('/THESIS/SUPPORTSERVICESYSTEM/services/sync-appointments.php')
        .catch((error) => {
          console.log('[Service Worker] Sync failed:', error);
        })
    );
  }
});

console.log('[Service Worker] Script loaded');
