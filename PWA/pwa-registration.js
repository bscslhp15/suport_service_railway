/**
 * PWA Registration Script
 * Registers the service worker and manages installation prompts
 */

(function () {
  // Check if Service Workers are supported
  if (!('serviceWorker' in navigator)) {
    console.warn('[PWA] Service Workers are not supported in this browser');
    return;
  }

  // Register Service Worker
  window.addEventListener('load', () => {
    const swPath = '/THESIS/SUPPORTSERVICESYSTEM/sw.js';

    navigator.serviceWorker
      .register(swPath, { scope: '/THESIS/SUPPORTSERVICESYSTEM/' })
      .then((registration) => {
        console.log('[PWA] Service Worker registered successfully:', registration);

        // Check for updates periodically
        setInterval(() => {
          registration.update();
        }, 60000); // Check every 60 seconds

        // Handle new service worker available
        registration.addEventListener('updatefound', () => {
          const newWorker = registration.installing;
          newWorker.addEventListener('statechange', () => {
            if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
              // New service worker available - notify user
              console.log('[PWA] New version available');
              window.dispatchEvent(new CustomEvent('pwa-update-available'));
            }
          });
        });
      })
      .catch((error) => {
        console.error('[PWA] Service Worker registration failed:', error);
      });
  });

  // Handle Service Worker controller changes
  navigator.serviceWorker.addEventListener('controllerchange', () => {
    console.log('[PWA] Service Worker controller changed');
  });

  // Detect if running in standalone mode (installed as app)
  const isStandalone =
    window.matchMedia('(display-mode: standalone)').matches ||
    navigator.standalone === true ||
    document.referrer.includes('android-app://');

  if (isStandalone) {
    console.log('[PWA] Running in standalone mode');
    document.documentElement.classList.add('pwa-standalone');
  }

  // Listen for app installation events
  window.addEventListener('beforeinstallprompt', (event) => {
    console.log('[PWA] beforeinstallprompt fired');
    // Store the event for later use
    window.deferredPrompt = event;
    // Prevent the mini-infobar from appearing on mobile
    event.preventDefault();
    // Enable custom install button
    window.dispatchEvent(new CustomEvent('pwa-install-available'));
  });

  // Handle app installed event
  window.addEventListener('appinstalled', () => {
    console.log('[PWA] App installed successfully');
    window.deferredPrompt = null;
    window.dispatchEvent(new CustomEvent('pwa-app-installed'));
  });

  // Check if app is already installed
  window.checkPWAInstallation = async function () {
    if ('getInstalledRelatedApps' in navigator) {
      try {
        const relatedApps = await navigator.getInstalledRelatedApps();
        return relatedApps.length > 0;
      } catch (error) {
        console.warn('[PWA] Error checking installed apps:', error);
        return false;
      }
    }
    return isStandalone;
  };

  // Export PWA utilities globally
  window.PWAUtils = {
    isStandalone,
    registerServiceWorker: () => {
      if ('serviceWorker' in navigator) {
        return navigator.serviceWorker.register(swPath);
      }
      return Promise.reject(new Error('Service Workers not supported'));
    },
    skipServiceWorkerWaiting: () => {
      if (navigator.serviceWorker.controller) {
        navigator.serviceWorker.controller.postMessage({
          type: 'SKIP_WAITING'
        });
      }
    },
    getServiceWorkerVersion: () => {
      return new Promise((resolve) => {
        if (navigator.serviceWorker.controller) {
          const channel = new MessageChannel();
          channel.port1.onmessage = (event) => {
            resolve(event.data.version);
          };
          navigator.serviceWorker.controller.postMessage(
            { type: 'GET_VERSION' },
            [channel.port2]
          );
        } else {
          resolve(null);
        }
      });
    }
  };

  console.log('[PWA] Registration script loaded');
})();
