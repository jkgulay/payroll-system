import { defineStore } from "pinia";
import { ref, computed } from "vue";
import api from "@/services/api";

export const useAuthStore = defineStore("auth", () => {
  // State
  const user = ref(null);
  const token = ref(
    localStorage.getItem("token") || sessionStorage.getItem("token") || null
  );
  const loading = ref(false);
  const rememberMe = ref(localStorage.getItem("rememberMe") === "true");
  const initialized = ref(false);

  // Restore user from localStorage on init
  const storedUser = localStorage.getItem("user");
  if (storedUser && token.value) {
    try {
      user.value = JSON.parse(storedUser);
      // Set Authorization header from stored token
      api.defaults.headers.common["Authorization"] = `Bearer ${token.value}`;
      initialized.value = true;
    } catch (e) {
      console.error("Failed to parse stored user:", e);
      localStorage.removeItem("user");
    }
  }

  // Getters
  const isAuthenticated = computed(() => !!token.value);
  const userRole = computed(() => user.value?.role || null);
  const isAdmin = computed(() => userRole.value === "admin");
  const isAccountant = computed(() =>
    ["admin", "accountant"].includes(userRole.value)
  );
  const mustChangePassword = computed(
    () => user.value?.must_change_password || false
  );

  // Actions
  async function login(credentials) {
    loading.value = true;
    try {
      const response = await api.post("/login", credentials);

      // Check if 2FA is required
      if (response.data.requires_2fa) {
        loading.value = false;
        return response.data; // Return to let login component handle 2FA
      }

      token.value = response.data.token;
      user.value = response.data.user;
      rememberMe.value = credentials.remember || false;

      // Store user data in localStorage
      localStorage.setItem("user", JSON.stringify(user.value));

      // Store token based on remember me preference
      if (rememberMe.value) {
        localStorage.setItem("token", token.value);
        localStorage.setItem("rememberMe", "true");
        // Remove from session storage if it exists
        sessionStorage.removeItem("token");
      } else {
        sessionStorage.setItem("token", token.value);
        // Clear from localStorage if it exists
        localStorage.removeItem("token");
        localStorage.removeItem("rememberMe");
      }

      // Set default Authorization header
      api.defaults.headers.common["Authorization"] = `Bearer ${token.value}`;

      return response.data;
    } catch (error) {
      throw error;
    } finally {
      loading.value = false;
    }
  }

  async function logout() {
    loading.value = true;
    try {
      await api.post("/logout");
    } catch (error) {
      console.error("Logout error:", error);
    } finally {
      // Clear state
      user.value = null;
      token.value = null;
      rememberMe.value = false;
      initialized.value = false;

      // Clear both localStorage and sessionStorage
      localStorage.removeItem("token");
      localStorage.removeItem("user");
      localStorage.removeItem("rememberMe");
      sessionStorage.removeItem("token");

      // Remove Authorization header
      delete api.defaults.headers.common["Authorization"];

      loading.value = false;
    }
  }

  async function checkAuth() {
    if (!token.value) return false;

    // If user is already loaded and initialized, don't make API call
    if (user.value && initialized.value) return true;

    loading.value = true;
    try {
      // Set Authorization header
      api.defaults.headers.common["Authorization"] = `Bearer ${token.value}`;

      // Get current user
      const response = await api.get("/user");
      user.value = response.data;

      // Store user data in localStorage
      localStorage.setItem("user", JSON.stringify(user.value));
      initialized.value = true;

      return true;
    } catch (error) {
      // Token invalid, clear auth
      user.value = null;
      token.value = null;
      rememberMe.value = false;
      initialized.value = false;
      localStorage.removeItem("token");
      localStorage.removeItem("user");
      localStorage.removeItem("rememberMe");
      sessionStorage.removeItem("token");
      delete api.defaults.headers.common["Authorization"];

      return false;
    } finally {
      loading.value = false;
    }
  }

  async function fetchUser() {
    loading.value = true;
    try {
      const response = await api.get("/user");
      user.value = response.data;

      // Store user data in localStorage
      localStorage.setItem("user", JSON.stringify(user.value));
    } catch (error) {
      console.error("Fetch user error:", error);
    } finally {
      loading.value = false;
    }
  }

  function updateUserPasswordStatus() {
    if (user.value) {
      user.value.must_change_password = false;
      localStorage.setItem("user", JSON.stringify(user.value));
    }
  }

  return {
    // State
    user,
    token,
    loading,
    rememberMe,
    // Getters
    isAuthenticated,
    userRole,
    isAdmin,
    isAccountant,
    mustChangePassword,
    // Actions
    login,
    logout,
    checkAuth,
    fetchUser,
    updateUserPasswordStatus,
  };
});
