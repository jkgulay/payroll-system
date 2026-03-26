import axios from "axios";
import { useToast } from "vue-toastification";

const toast = useToast();

// Create axios instance
const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || "http://localhost:8000/api",
  timeout: 60000, // 60 seconds for large imports
  headers: {
    "Content-Type": "application/json",
    Accept: "application/json",
  },
});

const inFlightGetRequests = new Map();
const responseCache = new Map();

const CACHE_TTL_BY_PREFIX = [
  { prefix: "/payrolls", ttl: 15000 },
  { prefix: "/projects", ttl: 120000 },
  { prefix: "/position-rates", ttl: 120000 },
  { prefix: "/employees/departments", ttl: 300000 },
  { prefix: "/company-info", ttl: 120000 },
  { prefix: "/locations", ttl: 300000 },
];

function stableStringify(value) {
  if (value === null || value === undefined) return "";

  if (Array.isArray(value)) {
    return `[${value.map(stableStringify).join(",")}]`;
  }

  if (typeof value === "object") {
    return `{${Object.keys(value)
      .sort()
      .map((key) => `${key}:${stableStringify(value[key])}`)
      .join(",")}}`;
  }

  return String(value);
}

function createGetRequestKey(url, config = {}) {
  const paramsString = stableStringify(config.params || {});
  return `${url}?${paramsString}`;
}

function getCacheTTL(url, config = {}) {
  if (config.skipCache) return 0;
  if (typeof config.cacheTTL === "number") return config.cacheTTL;

  const matched = CACHE_TTL_BY_PREFIX.find(({ prefix }) =>
    url.startsWith(prefix),
  );
  return matched ? matched.ttl : 0;
}

function deepCloneData(data) {
  if (data === null || data === undefined) return data;
  return JSON.parse(JSON.stringify(data));
}

function buildCachedAxiosResponse(cached, config) {
  return {
    data: deepCloneData(cached.data),
    status: cached.status,
    statusText: cached.statusText,
    headers: cached.headers,
    config,
    request: null,
  };
}

function clearApiResponseCache() {
  responseCache.clear();
}

const originalGet = api.get.bind(api);

api.get = function getWithDedupeAndCache(url, config = {}) {
  const cacheTTL = getCacheTTL(url, config);
  const key = createGetRequestKey(url, config);

  if (cacheTTL > 0) {
    const cached = responseCache.get(key);
    if (cached && cached.expiresAt > Date.now()) {
      return Promise.resolve(buildCachedAxiosResponse(cached, config));
    }
    if (cached && cached.expiresAt <= Date.now()) {
      responseCache.delete(key);
    }
  }

  const existingRequest = inFlightGetRequests.get(key);
  if (existingRequest) {
    return existingRequest;
  }

  const requestPromise = originalGet(url, config)
    .then((response) => {
      if (cacheTTL > 0) {
        responseCache.set(key, {
          data: deepCloneData(response.data),
          status: response.status,
          statusText: response.statusText,
          headers: response.headers,
          expiresAt: Date.now() + cacheTTL,
        });
      }

      return response;
    })
    .finally(() => {
      inFlightGetRequests.delete(key);
    });

  inFlightGetRequests.set(key, requestPromise);

  return requestPromise;
};

// Request interceptor
api.interceptors.request.use(
  (config) => {
    // Add token from localStorage or sessionStorage
    const token = localStorage.getItem("token") || sessionStorage.getItem("token");
    if (token) {
      config.headers.Authorization = `Bearer ${token}`;
    }

    return config;
  },
  (error) => {
    return Promise.reject(error);
  },
);

// Response interceptor
api.interceptors.response.use(
  (response) => {
    const method = response.config?.method?.toLowerCase();
    if (["post", "put", "patch", "delete"].includes(method)) {
      clearApiResponseCache();
    }

    return response;
  },
  (error) => {
    // Skip toast if explicitly requested
    if (error.config?.skipToast) {
      return Promise.reject(error);
    }

    if (error.response) {
      // Server responded with error
      const { status, data } = error.response;

      switch (status) {
        case 401:
          // Unauthorized - clear auth from both storages and redirect to login
          localStorage.removeItem("token");
          sessionStorage.removeItem("token");
          delete api.defaults.headers.common["Authorization"];
          window.location.href = "/login";
          toast.error("Session expired. Please login again.");
          break;

        case 403:
          toast.error("You do not have permission to perform this action.");
          break;

        case 404:
          toast.error("Resource not found.");
          break;

        case 422:
          // Validation errors
          if (data.errors) {
            Object.values(data.errors)
              .flat()
              .forEach((msg) => {
                toast.error(msg);
              });
          } else {
            toast.error(data.message || "Validation error.");
          }
          break;

        case 500:
          toast.error("Server error. Please try again later.");
          break;

        default:
          toast.error(data.message || "An error occurred.");
      }
    } else if (error.request) {
      // Request made but no response
      toast.error("Network error. Please check your connection.");
    } else {
      // Something else happened
      toast.error("An unexpected error occurred.");
    }

    return Promise.reject(error);
  },
);

export default api;
