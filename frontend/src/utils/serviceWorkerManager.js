import { devLog } from "@/utils/devLog";
// Service Worker Registration and Management
export function registerServiceWorker() {
  if ('serviceWorker' in navigator && import.meta.env.PROD) {
    window.addEventListener('load', async () => {
      try {
        const registration = await navigator.serviceWorker.register(
          '/service-worker.js',
          { scope: '/' }
        );

        devLog.log('Service Worker registered:', registration.scope);

        // Check for updates periodically
        setInterval(() => {
          registration.update();
        }, 60 * 60 * 1000); // Check every hour

        // Handle updates
        registration.addEventListener('updatefound', () => {
          const newWorker = registration.installing;
          
          if (newWorker) {
            newWorker.addEventListener('statechange', () => {
              if (
                newWorker.state === 'installed' &&
                navigator.serviceWorker.controller
              ) {
                // New service worker available, prompt user to reload
                if (confirm('New version available! Reload to update?')) {
                  window.location.reload();
                }
              }
            });
          }
        });
      } catch (error) {
        devLog.error('Service Worker registration failed:', error);
      }
    });
  }
}

// Unregister service worker (for development/debugging)
export async function unregisterServiceWorker() {
  if ('serviceWorker' in navigator) {
    const registrations = await navigator.serviceWorker.getRegistrations();
    for (const registration of registrations) {
      await registration.unregister();
    }
    devLog.log('Service Workers unregistered');
  }
}

// Check if app is running in standalone mode (installed PWA)
export function isStandalone() {
  return (
    window.matchMedia('(display-mode: standalone)').matches ||
    window.navigator.standalone === true
  );
}

// Request notification permission
export async function requestNotificationPermission() {
  if ('Notification' in window && Notification.permission === 'default') {
    const permission = await Notification.requestPermission();
    return permission === 'granted';
  }
  return Notification.permission === 'granted';
}

// Show notification
export function showNotification(title, options = {}) {
  if ('Notification' in window && Notification.permission === 'granted') {
    if (navigator.serviceWorker && navigator.serviceWorker.controller) {
      navigator.serviceWorker.controller.postMessage({
        type: 'SHOW_NOTIFICATION',
        payload: { title, options },
      });
    } else {
      new Notification(title, options);
    }
  }
}

// Sync data when back online
export function registerBackgroundSync(tag = 'sync-data') {
  if ('serviceWorker' in navigator && 'SyncManager' in window) {
    navigator.serviceWorker.ready.then((registration) => {
      return registration.sync.register(tag);
    });
  }
}

// Check online/offline status
export function setupOnlineListener(onOnline, onOffline) {
  window.addEventListener('online', () => {
    devLog.log('App is online');
    if (onOnline) onOnline();
    registerBackgroundSync();
  });

  window.addEventListener('offline', () => {
    devLog.log('App is offline');
    if (onOffline) onOffline();
  });

  // Initial check
  if (!navigator.onLine && onOffline) {
    onOffline();
  }
}
