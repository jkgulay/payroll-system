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

// Request interceptor
api.interceptors.request.use(
  (config) => {
    // Add token from localStorage
    const token = localStorage.getItem("token");
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
          // Unauthorized - clear auth and redirect to login
          localStorage.removeItem("token");
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
