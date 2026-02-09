/**
 * Shared formatting utilities used across multiple views/components.
 *
 * Centralizes duplicated formatCurrency / formatDate / formatDateTime helpers
 * so every view produces consistent output.
 */

/**
 * Format a numeric value as a currency string (no symbol).
 * Uses en-US locale with 2 decimal places.
 *
 * @param {number|string} amount
 * @returns {string} e.g. "1,234.56"
 */
export function formatCurrency(amount) {
  if (!amount && amount !== 0) return "0.00";
  return parseFloat(amount).toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

/**
 * Format an ISO date string as a short, human-readable date.
 * e.g. "Jan 15, 2025"
 *
 * @param {string|null} date  ISO date string
 * @param {string}      fallback  Value returned when date is falsy (default "")
 * @returns {string}
 */
export function formatDate(date, fallback = "") {
  if (!date) return fallback;
  return new Date(date).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

/**
 * Format an ISO datetime string with time included.
 * e.g. "Jan 15, 2025, 02:30 PM"
 *
 * @param {string|null} date  ISO datetime string
 * @param {string}      fallback  Value returned when date is falsy (default "")
 * @returns {string}
 */
export function formatDateTime(date, fallback = "") {
  if (!date) return fallback;
  return new Date(date).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

/**
 * Format a numeric value with commas and 2 decimal places (no symbol).
 * Alias for formatCurrency — used in views that prefer the "formatNumber" name.
 *
 * @param {number|string} value
 * @returns {string} e.g. "1,234.56"
 */
export function formatNumber(value) {
  if (!value && value !== 0) return "0.00";
  return parseFloat(value).toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

/**
 * Format an ISO datetime string as a time-only string.
 * e.g. "02:30:45 PM"
 *
 * @param {string|null} date  ISO datetime string
 * @param {string}      fallback  Value returned when date is falsy (default "")
 * @returns {string}
 */
export function formatTime(date, fallback = "") {
  if (!date) return fallback;
  return new Date(date).toLocaleTimeString("en-US", {
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
  });
}
