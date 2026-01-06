// Composable for network status monitoring
import { ref, onMounted, onUnmounted } from 'vue';

export function useNetworkStatus() {
  const isOnline = ref(navigator.onLine);
  const isSlowConnection = ref(false);

  function updateOnlineStatus() {
    isOnline.value = navigator.onLine;
  }

  function checkConnectionSpeed() {
    if ('connection' in navigator) {
      const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
      
      if (connection) {
        // Check for slow connections (2G, slow-2g)
        isSlowConnection.value = connection.effectiveType === 'slow-2g' || connection.effectiveType === '2g';
        
        // Return connection info
        return {
          type: connection.effectiveType,
          downlink: connection.downlink, // Mbps
          rtt: connection.rtt, // Round trip time in ms
          saveData: connection.saveData, // Data saver mode
        };
      }
    }
    return null;
  }

  onMounted(() => {
    window.addEventListener('online', updateOnlineStatus);
    window.addEventListener('offline', updateOnlineStatus);

    // Listen for connection changes
    if ('connection' in navigator) {
      const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
      if (connection) {
        connection.addEventListener('change', checkConnectionSpeed);
      }
    }

    // Initial check
    checkConnectionSpeed();
  });

  onUnmounted(() => {
    window.removeEventListener('online', updateOnlineStatus);
    window.removeEventListener('offline', updateOnlineStatus);

    if ('connection' in navigator) {
      const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
      if (connection) {
        connection.removeEventListener('change', checkConnectionSpeed);
      }
    }
  });

  return {
    isOnline,
    isSlowConnection,
    checkConnectionSpeed,
  };
}
