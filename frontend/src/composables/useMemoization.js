import { computed, ref, watch } from "vue";

/**
 * Memoization composable for expensive computed properties
 * Caches results based on input parameters to avoid recalculations
 * 
 * @param {Function} fn - Function to memoize
 * @param {Function} keyFn - Function to generate cache key from arguments
 * @param {number} maxSize - Maximum cache size (default: 100)
 * @returns {Function} - Memoized function
 * 
 * @example
 * const expensiveCalculation = useMemoize(
 *   (data) => {
 *     // Expensive operation
 *     return data.reduce((sum, item) => sum + item.value, 0);
 *   },
 *   (data) => data.length // Cache key based on data length
 * );
 */
export function useMemoize(fn, keyFn = JSON.stringify, maxSize = 100) {
  const cache = new Map();

  return (...args) => {
    const key = keyFn(...args);

    if (cache.has(key)) {
      return cache.get(key);
    }

    const result = fn(...args);

    // Limit cache size (LRU-like behavior)
    if (cache.size >= maxSize) {
      const firstKey = cache.keys().next().value;
      cache.delete(firstKey);
    }

    cache.set(key, result);
    return result;
  };
}

/**
 * Computed with cache TTL (Time To Live)
 * Creates a computed property that caches value for specified duration
 * 
 * @param {Function} fn - Function to compute value
 * @param {number} ttl - Cache duration in milliseconds (default: 5000ms)
 * @returns {Ref} - Computed ref with TTL
 * 
 * @example
 * const expensiveData = useComputedWithTTL(() => {
 *   return calculateExpensiveValue();
 * }, 10000); // Cache for 10 seconds
 */
export function useComputedWithTTL(fn, ttl = 5000) {
  const cachedValue = ref(null);
  const lastUpdate = ref(0);

  return computed(() => {
    const now = Date.now();
    
    if (!cachedValue.value || now - lastUpdate.value > ttl) {
      cachedValue.value = fn();
      lastUpdate.value = now;
    }

    return cachedValue.value;
  });
}

/**
 * Async data fetcher with caching
 * Fetches data and caches it with TTL
 * 
 * @param {Function} fetchFn - Async function to fetch data
 * @param {Object} options - Options for caching
 * @returns {Object} - { data, loading, error, refetch, clearCache }
 * 
 * @example
 * const { data, loading, error, refetch } = useAsyncCache(
 *   async () => {
 *     const response = await api.get('/employees');
 *     return response.data;
 *   },
 *   { ttl: 60000, key: 'employees' }
 * );
 */
export function useAsyncCache(fetchFn, options = {}) {
  const {
    ttl = 60000, // 1 minute default
    key = "default",
    immediate = true,
  } = options;

  const data = ref(null);
  const loading = ref(false);
  const error = ref(null);
  const lastFetch = ref(0);

  // Simple in-memory cache
  const cacheStore = new Map();

  const fetch = async (force = false) => {
    const now = Date.now();
    const cached = cacheStore.get(key);

    // Return cached data if still valid and not forced
    if (!force && cached && now - cached.timestamp < ttl) {
      data.value = cached.data;
      loading.value = false;
      return cached.data;
    }

    loading.value = true;
    error.value = null;

    try {
      const result = await fetchFn();
      data.value = result;
      lastFetch.value = now;

      // Update cache
      cacheStore.set(key, {
        data: result,
        timestamp: now,
      });

      return result;
    } catch (e) {
      error.value = e;
      throw e;
    } finally {
      loading.value = false;
    }
  };

  const clearCache = () => {
    cacheStore.delete(key);
  };

  const refetch = () => fetch(true);

  if (immediate) {
    fetch();
  }

  return {
    data,
    loading,
    error,
    fetch,
    refetch,
    clearCache,
  };
}

/**
 * Lazy computed property
 * Only computes value when accessed, not on every dependency change
 * 
 * @param {Function} fn - Function to compute value
 * @returns {Function} - Getter function
 * 
 * @example
 * const heavyCalculation = useLazyComputed(() => {
 *   return performExpensiveOperation();
 * });
 * 
 * // Only computed when accessed
 * const result = heavyCalculation();
 */
export function useLazyComputed(fn) {
  let cachedValue;
  let isComputed = false;
  let deps = [];

  return () => {
    if (!isComputed) {
      cachedValue = fn();
      isComputed = true;
    }
    return cachedValue;
  };
}

/**
 * Batch updates to prevent multiple renders
 * Collects multiple updates and executes them together
 * 
 * @returns {Object} - { addUpdate, executeBatch }
 * 
 * @example
 * const { addUpdate, executeBatch } = useBatchUpdate();
 * 
 * addUpdate(() => { data1.value = newValue1; });
 * addUpdate(() => { data2.value = newValue2; });
 * addUpdate(() => { data3.value = newValue3; });
 * 
 * // All updates execute together, causing single render
 * executeBatch();
 */
export function useBatchUpdate() {
  const updates = ref([]);

  const addUpdate = (updateFn) => {
    updates.value.push(updateFn);
  };

  const executeBatch = () => {
    updates.value.forEach((fn) => fn());
    updates.value = [];
  };

  return {
    addUpdate,
    executeBatch,
  };
}
