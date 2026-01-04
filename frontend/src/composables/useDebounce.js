import { ref, watch, unref } from "vue";

/**
 * Debounce composable for Vue 3
 * Delays execution of a function until after a specified delay
 * Useful for search inputs, form validations, and API calls
 * 
 * @param {Ref|any} value - The value to debounce (can be a ref or regular value)
 * @param {number} delay - Delay in milliseconds (default: 300ms)
 * @returns {Ref} - Debounced value
 * 
 * @example
 * const searchQuery = ref('');
 * const debouncedSearch = useDebounce(searchQuery, 500);
 * 
 * watch(debouncedSearch, (newValue) => {
 *   // This will only execute 500ms after user stops typing
 *   performSearch(newValue);
 * });
 */
export function useDebounce(value, delay = 300) {
  const debouncedValue = ref(unref(value));
  let timeout = null;

  watch(
    () => unref(value),
    (newValue) => {
      if (timeout) {
        clearTimeout(timeout);
      }

      timeout = setTimeout(() => {
        debouncedValue.value = newValue;
      }, delay);
    },
    { immediate: true }
  );

  return debouncedValue;
}

/**
 * Debounced function composable
 * Creates a debounced version of any function
 * 
 * @param {Function} fn - The function to debounce
 * @param {number} delay - Delay in milliseconds (default: 300ms)
 * @returns {Function} - Debounced function
 * 
 * @example
 * const handleSearch = useDebouncedFn((query) => {
 *   console.log('Searching for:', query);
 *   api.search(query);
 * }, 500);
 * 
 * // Call it normally, but execution is debounced
 * handleSearch('test');
 */
export function useDebouncedFn(fn, delay = 300) {
  let timeout = null;

  const debouncedFn = (...args) => {
    if (timeout) {
      clearTimeout(timeout);
    }

    timeout = setTimeout(() => {
      fn(...args);
    }, delay);
  };

  // Cancel method to clear pending execution
  debouncedFn.cancel = () => {
    if (timeout) {
      clearTimeout(timeout);
      timeout = null;
    }
  };

  return debouncedFn;
}

/**
 * Throttle composable for Vue 3
 * Limits the rate at which a function can fire
 * Useful for scroll events, resize events, and continuous actions
 * 
 * @param {Function} fn - The function to throttle
 * @param {number} delay - Delay in milliseconds (default: 300ms)
 * @returns {Function} - Throttled function
 * 
 * @example
 * const handleScroll = useThrottle(() => {
 *   console.log('Scroll event');
 * }, 200);
 * 
 * window.addEventListener('scroll', handleScroll);
 */
export function useThrottle(fn, delay = 300) {
  let lastRun = 0;
  let timeout = null;

  return (...args) => {
    const now = Date.now();
    const timeSinceLastRun = now - lastRun;

    if (timeSinceLastRun >= delay) {
      fn(...args);
      lastRun = now;
    } else {
      if (timeout) {
        clearTimeout(timeout);
      }

      timeout = setTimeout(() => {
        fn(...args);
        lastRun = Date.now();
      }, delay - timeSinceLastRun);
    }
  };
}
