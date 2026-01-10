<template>
  <v-app>
    <v-main class="login-main">
      <!-- Animated Background -->
      <div class="background-overlay"></div>
      <div class="background-pattern"></div>

      <v-container class="fill-height" fluid>
        <v-row align="center" justify="center" class="position-relative">
          <!-- Left Side - Branding (Hidden on mobile) -->
          <v-col
            cols="12"
            md="6"
            lg="7"
            class="d-none d-md-flex align-center justify-center"
          >
            <div class="branding-section">
              <div class="brand-icon-wrapper mb-6">
                <v-icon
                  icon="mdi-hard-hat"
                  size="120"
                  class="brand-icon"
                ></v-icon>
              </div>
              <h1
                class="display-1 font-weight-bold text-white mb-4 text-shadow"
              >
                Payroll Management System
              </h1>
              <p class="text-h6 text-white-80 mb-6 text-shadow">
                A comprehensive Philippine payroll management system designed
                specifically for construction companies. Built with modern
                technologies to handle complex payroll computations, employee
                management, attendance tracking, and government compliance
                requirements.
              </p>
            </div>
          </v-col>

          <!-- Right Side - Login Form -->
          <v-col cols="12" sm="8" md="5" lg="4" xl="3">
            <v-card elevation="0" class="login-card">
              <!-- Modern Header -->
              <div class="login-header-modern">
                <div class="header-content">
                  <div class="icon-circle">
                    <v-icon
                      icon="mdi-hard-hat"
                      size="40"
                      color="white"
                    ></v-icon>
                  </div>
                  <h1 class="header-title">Sign In</h1>
                  <p class="header-subtitle">Access your payroll dashboard</p>
                </div>
              </div>

              <v-card-text class="form-section">
                <v-form @submit.prevent="handleLogin" ref="loginForm">
                  <!-- Username Field -->
                  <div class="field-wrapper">
                    <div class="field-label">
                      <v-icon size="18" color="#546e7a"
                        >mdi-account-outline</v-icon
                      >
                      <span>Username or Email</span>
                    </div>
                    <v-text-field
                      v-model="form.email"
                      placeholder="Enter your username or email"
                      variant="solo"
                      flat
                      color="#ff6f00"
                      bg-color="#f8f9fa"
                      :rules="[rules.required]"
                      :error-messages="errors.email"
                      @input="errors.email = ''"
                      density="comfortable"
                      class="modern-field"
                      hide-details="auto"
                    >
                      <template v-slot:prepend-inner>
                        <v-icon color="#546e7a" size="20"
                          >mdi-account-circle</v-icon
                        >
                      </template>
                    </v-text-field>
                  </div>

                  <!-- Password Field -->
                  <div class="field-wrapper">
                    <div class="field-label">
                      <v-icon size="18" color="#546e7a"
                        >mdi-lock-outline</v-icon
                      >
                      <span>Password</span>
                    </div>
                    <v-text-field
                      v-model="form.password"
                      placeholder="Enter your password"
                      :type="showPassword ? 'text' : 'password'"
                      variant="solo"
                      flat
                      color="#ff6f00"
                      bg-color="#f8f9fa"
                      :rules="[rules.required]"
                      :error-messages="errors.password"
                      @input="errors.password = ''"
                      density="comfortable"
                      class="modern-field"
                      hide-details="auto"
                    >
                      <template v-slot:prepend-inner>
                        <v-icon color="#546e7a" size="20">mdi-lock</v-icon>
                      </template>
                      <template v-slot:append-inner>
                        <v-icon
                          @click="showPassword = !showPassword"
                          color="#546e7a"
                          size="20"
                          style="cursor: pointer"
                        >
                          {{ showPassword ? "mdi-eye-off" : "mdi-eye" }}
                        </v-icon>
                      </template>
                    </v-text-field>
                  </div>

                  <!-- Role Select -->
                  <div class="field-wrapper">
                    <div class="field-label">
                      <v-icon size="18" color="#546e7a"
                        >mdi-shield-account-outline</v-icon
                      >
                      <span>Select Role</span>
                    </div>
                    <v-select
                      v-model="form.role"
                      placeholder="Choose your role"
                      :items="roles"
                      variant="solo"
                      flat
                      color="#ff6f00"
                      bg-color="#f8f9fa"
                      :rules="[rules.required]"
                      :error-messages="errors.role"
                      @update:modelValue="errors.role = ''"
                      density="comfortable"
                      class="modern-field"
                      hide-details="auto"
                    >
                      <template v-slot:prepend-inner>
                        <v-icon color="#546e7a" size="20"
                          >mdi-shield-account</v-icon
                        >
                      </template>
                      <template v-slot:item="{ props, item }">
                        <v-list-item v-bind="props" class="role-list-item">
                          <template v-slot:prepend>
                            <v-icon
                              :color="
                                item.value === 'admin'
                                  ? '#ff6f00'
                                  : item.value === 'accountant'
                                  ? '#ff9800'
                                  : '#546e7a'
                              "
                              size="20"
                            >
                              {{
                                item.value === "admin"
                                  ? "mdi-shield-crown"
                                  : item.value === "accountant"
                                  ? "mdi-calculator"
                                  : "mdi-account-hard-hat"
                              }}
                            </v-icon>
                          </template>
                        </v-list-item>
                      </template>
                    </v-select>
                  </div>

                  <!-- Remember Me -->
                  <div class="remember-section">
                    <v-checkbox
                      v-model="form.remember"
                      color="#ff6f00"
                      hide-details
                      density="compact"
                    >
                      <template v-slot:label>
                        <span class="remember-text">Keep me signed in</span>
                      </template>
                    </v-checkbox>
                  </div>

                  <!-- Sign In Button -->
                  <v-btn
                    type="submit"
                    color="#ff6f00"
                    size="x-large"
                    :loading="authStore.loading"
                    class="signin-btn"
                    elevation="0"
                  >
                    Sign In
                    <v-icon end size="small">mdi-arrow-right</v-icon>
                  </v-btn>

                  <!-- Security Info -->
                  <div class="security-info">
                    <v-icon size="16" color="#546e7a">mdi-shield-check</v-icon>
                    <span>Protected by 2FA & SSL Encryption</span>
                  </div>

                  <v-alert
                    v-if="errorMessage"
                    type="error"
                    variant="tonal"
                    density="compact"
                    class="mt-4"
                    closable
                  >
                    {{ errorMessage }}
                  </v-alert>
                </v-form>
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </v-container>

      <!-- Two-Factor Authentication Dialog -->
      <TwoFactorVerify
        v-model="showTwoFactorDialog"
        :user-id="twoFactorUserId"
        @verified="handleTwoFactorVerified"
        @cancel="handleTwoFactorCancel"
        ref="twoFactorVerifyRef"
      />
    </v-main>
  </v-app>
</template>

<script setup>
import { ref, reactive } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import { useToast } from "vue-toastification";
import TwoFactorVerify from "@/components/TwoFactorVerify.vue";
import api from "@/services/api";

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const toast = useToast();

const loginForm = ref(null);
const showPassword = ref(false);
const errorMessage = ref("");
const showTwoFactorDialog = ref(false);
const twoFactorUserId = ref(null);
const twoFactorVerifyRef = ref(null);

const form = reactive({
  email: "",
  password: "",
  remember: false,
  role: "admin",
});

const errors = reactive({
  email: "",
  password: "",
  role: "",
});

const roles = [
  { title: "Admin", value: "admin" },
  { title: "Accountant", value: "accountant" },
  { title: "Employee", value: "employee" },
];

const rules = {
  required: (value) => !!value || "This field is required",
};

async function handleLogin() {
  errorMessage.value = "";

  // Validate form
  const { valid } = await loginForm.value.validate();
  if (!valid) return;

  try {
    const response = await authStore.login({
      email: form.email,
      password: form.password,
      remember: form.remember,
      role: form.role,
    });

    // Check if 2FA is required
    if (response && response.requires_2fa) {
      twoFactorUserId.value = response.user_id;
      showTwoFactorDialog.value = true;
      return;
    }

    toast.success("Login successful");

    // Redirect to intended page or dashboard
    const redirect = route.query.redirect || "/";
    router.push(redirect);
  } catch (error) {
    console.error("Login error:", error);
    console.error("Login error response:", error.response?.data);
    console.error("Login validation errors:", error.response?.data?.errors);
    console.error("Login credentials sent:", {
      email: form.email,
      role: form.role,
    });

    if (error.response?.status === 401 || error.response?.status === 422) {
      // Show validation errors if available
      if (error.response?.data?.errors) {
        const errors = error.response.data.errors;
        const errorMessages = Object.values(errors).flat();
        errorMessage.value = errorMessages.join(". ");
      } else {
        errorMessage.value =
          error.response?.data?.message || "Invalid credentials";
      }
    } else if (error.response?.data?.message) {
      errorMessage.value = error.response.data.message;
    } else {
      errorMessage.value = "An error occurred. Please try again.";
    }
  }
}

async function handleTwoFactorVerified({ userId, code }) {
  twoFactorVerifyRef.value.setLoading(true);

  try {
    const response = await api.post("/two-factor/verify", {
      user_id: userId,
      code: code,
    });

    if (response.data.valid) {
      // Properly set authentication state
      authStore.token = response.data.token;
      authStore.user = response.data.user;
      authStore.rememberMe = form.remember;

      // Store token based on remember me preference
      if (form.remember) {
        localStorage.setItem("token", response.data.token);
        localStorage.setItem("rememberMe", "true");
        sessionStorage.removeItem("token");
      } else {
        sessionStorage.setItem("token", response.data.token);
        localStorage.removeItem("token");
        localStorage.removeItem("rememberMe");
      }

      // Set default Authorization header for future requests
      api.defaults.headers.common[
        "Authorization"
      ] = `Bearer ${response.data.token}`;

      showTwoFactorDialog.value = false;
      toast.success("Login successful");

      // Redirect to appropriate dashboard based on role
      let redirectPath = route.query.redirect;
      if (!redirectPath) {
        const role = response.data.user.role;
        if (role === "employee") {
          redirectPath = "/employee-dashboard";
        } else if (role === "accountant") {
          redirectPath = "/accountant-dashboard";
        } else {
          redirectPath = "/admin-dashboard";
        }
      }

      await router.push(redirectPath);
    }
  } catch (error) {
    console.error("2FA verification error:", error);

    if (error.response?.status === 401) {
      twoFactorVerifyRef.value.setError(
        error.response?.data?.message || "Invalid verification code"
      );
    } else {
      twoFactorVerifyRef.value.setError(
        "Verification failed. Please try again."
      );
    }
  }
}

function handleTwoFactorCancel() {
  showTwoFactorDialog.value = false;
  twoFactorUserId.value = null;
  // Clear any error messages
  errorMessage.value = "";
  toast.info("Login cancelled");
}
</script>

<style scoped lang="scss">
// Modern Professional Login Page with Construction Background

.login-main {
  background: linear-gradient(rgba(30, 30, 30, 0.7), rgba(30, 30, 30, 0.7)),
    url("@/images/construction-bg.jpg");
  background-size: cover;
  background-position: center;
  background-attachment: fixed;
  position: relative;
  overflow: hidden;
  min-height: 100vh;
}

// Animated Background
.background-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(
    135deg,
    rgba(55, 71, 79, 0.4) 0%,
    rgba(255, 111, 0, 0.25) 50%,
    rgba(69, 90, 100, 0.4) 100%
  );
  animation: pulse-overlay 8s ease-in-out infinite;
}

@keyframes pulse-overlay {
  0%,
  100% {
    opacity: 0.8;
  }
  50% {
    opacity: 0.5;
  }
}

.background-pattern {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background-image: radial-gradient(
      circle at 20% 50%,
      rgba(255, 152, 0, 0.08) 0%,
      transparent 50%
    ),
    radial-gradient(
      circle at 80% 80%,
      rgba(84, 110, 122, 0.1) 0%,
      transparent 50%
    ),
    radial-gradient(
      circle at 40% 20%,
      rgba(255, 111, 0, 0.06) 0%,
      transparent 50%
    );
  animation: float 30s ease-in-out infinite;
}

@keyframes float {
  0%,
  100% {
    transform: translateX(0);
  }
  50% {
    transform: translateX(20px);
  }
}

.position-relative {
  position: relative;
  z-index: 1;
}

// Branding Section
.branding-section {
  padding: 3rem;
  text-align: center;
  animation: fadeInLeft 1s ease-out;
}

.brand-icon-wrapper {
  display: inline-block;
  background: rgba(255, 152, 0, 0.15);
  padding: 2rem;
  border-radius: 50%;
  backdrop-filter: blur(10px);
  box-shadow: 0 8px 32px rgba(216, 67, 21, 0.3);
  border: 2px solid rgba(255, 152, 0, 0.3);
}

.brand-icon {
  color: #ff9800;
  filter: drop-shadow(0 4px 12px rgba(216, 67, 21, 0.4));
  animation: pulse 3s ease-in-out infinite;
}

@keyframes pulse {
  0%,
  100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.05);
  }
}

.text-shadow {
  text-shadow: 0 2px 16px rgba(0, 0, 0, 0.8);
}

.text-white-80 {
  color: rgba(255, 255, 255, 0.95);
}

.features-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
  margin-top: 2rem;
}

.feature-item {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  color: white;
  font-size: 1rem;
  padding: 0.75rem 1.5rem;
  background: rgba(216, 67, 21, 0.2);
  border-radius: 12px;
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255, 152, 0, 0.3);
  transition: all 0.3s ease;

  &:hover {
    background: rgba(216, 67, 21, 0.3);
    border-color: rgba(255, 152, 0, 0.5);
    transform: translateX(5px);
  }
}

@keyframes fadeInLeft {
  from {
    opacity: 0;
    transform: translateX(-30px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

// Login Card
.login-card {
  background: rgba(255, 255, 255, 0.98) !important;
  border-radius: 20px !important;
  overflow: hidden;
  box-shadow: 0 40px 80px -20px rgba(0, 0, 0, 0.4),
    0 20px 40px -20px rgba(255, 111, 0, 0.15),
    0 0 0 1px rgba(255, 152, 0, 0.1) inset !important;
  border: none !important;
  backdrop-filter: blur(20px);
  animation: fadeInUp 1s ease-out;

  :deep(.v-card__underlay) {
    display: none !important;
  }
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(30px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

// Modern Header
.login-header-modern {
  padding: 2rem 1.5rem 1.5rem;
  background: linear-gradient(135deg, #37474f 0%, #ff6f00 100%);
  position: relative;
  text-align: center;

  &::after {
    content: "";
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, transparent, #ff9800, transparent);
  }
}

.header-content {
  position: relative;
  z-index: 1;
}

.icon-circle {
  width: 64px;
  height: 64px;
  margin: 0 auto 1rem;
  background: rgba(255, 255, 255, 0.15);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(10px);
  border: 2px solid rgba(255, 255, 255, 0.3);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
}

.header-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: white;
  margin-bottom: 0.35rem;
  text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
  letter-spacing: -0.5px;
}

.header-subtitle {
  font-size: 0.875rem;
  color: rgba(255, 255, 255, 0.9);
  margin: 0;
  font-weight: 400;
  text-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

// Form Section
.form-section {
  padding: 2rem 1.5rem 1.75rem;
}

// Field Wrapper
.field-wrapper {
  margin-bottom: 1.25rem;
  max-width: 480px;
  margin-left: auto;
  margin-right: auto;
}

.field-label {
  display: flex;
  align-items: center;
  gap: 0.4rem;
  margin-bottom: 0.5rem;
  font-size: 0.8125rem;
  font-weight: 600;
  color: #37474f;
  letter-spacing: 0.3px;
}

// Modern Field Styling
.modern-field {
  :deep(.v-field) {
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    border: 1px solid rgba(0, 0, 0, 0.08);
    transition: all 0.3s ease;

    &:hover {
      border-color: rgba(255, 152, 0, 0.3);
      box-shadow: 0 4px 12px rgba(255, 152, 0, 0.1);
      transform: translateY(-1px);
    }
  }

  :deep(.v-field--focused) {
    border-color: #ff6f00 !important;
    box-shadow: 0 4px 16px rgba(255, 111, 0, 0.2) !important;
  }

  :deep(.v-field__prepend-inner) {
    padding-right: 8px;
  }

  :deep(.v-field__input) {
    padding: 10px 14px;
    font-size: 0.9rem;
  }
}

// Role List Item
.role-list-item {
  transition: all 0.2s ease;

  &:hover {
    background: rgba(255, 152, 0, 0.08);
  }
}

// Remember Section
.remember-section {
  margin: 1.25rem 0 1.5rem;
  max-width: 280px;
  margin-left: auto;
  margin-right: auto;

  .remember-text {
    font-size: 0.875rem;
    color: #546e7a;
    font-weight: 500;
  }
}

// Sign In Button
.signin-btn {
  height: 50px !important;
  border-radius: 12px !important;
  font-size: 0.95rem !important;
  font-weight: 600 !important;
  letter-spacing: 0.3px;
  text-transform: none !important;
  margin-bottom: 1.25rem;
  width: 150px !important;
  display: block !important;
  margin-left: auto !important;
  margin-right: auto !important;
  background: linear-gradient(135deg, #ff6f00 0%, #ff9800 100%) !important;
  box-shadow: 0 8px 20px rgba(255, 111, 0, 0.3),
    0 4px 10px rgba(255, 111, 0, 0.2) !important;
  transition: all 0.3s ease !important;

  &:hover {
    box-shadow: 0 12px 28px rgba(255, 111, 0, 0.4),
      0 6px 14px rgba(255, 111, 0, 0.3) !important;
    transform: translateY(-2px);
  }

  &:active {
    transform: translateY(0);
  }
}

// Security Info
.security-info {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.75rem;
  background: rgba(84, 110, 122, 0.06);
  border-radius: 10px;
  font-size: 0.75rem;
  color: #546e7a;
  font-weight: 500;
  border: 1px solid rgba(84, 110, 122, 0.1);
  max-width: 280px;
  margin-left: auto;
  margin-right: auto;
}

// Compact Header (old - to be removed)
.login-header {
  background: linear-gradient(135deg, #37474f 0%, #ff6f00 50%, #546e7a 100%);
  position: relative;
  box-shadow: 0 4px 12px rgba(255, 111, 0, 0.2);
  border-radius: 24px 24px 0 0;

  &::after {
    content: "";
    position: absolute;
    bottom: 0;
    left: 5%;
    right: 5%;
    height: 1px;
    background: linear-gradient(
      90deg,
      transparent 0%,
      rgba(255, 152, 0, 0.6) 50%,
      transparent 100%
    );
  }

  .v-icon {
    filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.3));
  }
}

.input-group {
  position: relative;
}

.input-label {
  display: flex;
  align-items: center;
  font-size: 0.875rem;
  font-weight: 600;
  color: #37474f;
  margin-bottom: 0.5rem;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.modern-input {
  :deep(.v-field) {
    border-radius: 14px;
    background: #ffffff;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    border: 2px solid rgba(120, 120, 120, 0.12);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.04), 0 1px 2px rgba(0, 0, 0, 0.02),
      0 0 0 1px rgba(255, 255, 255, 0.9) inset;

    &:hover {
      background: #ffffff;
      border-color: rgba(255, 152, 0, 0.3);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(255, 152, 0, 0.08),
        0 2px 4px rgba(0, 0, 0, 0.04), 0 0 0 1px rgba(255, 255, 255, 1) inset;
    }
  }

  :deep(.v-field--focused) {
    background: #ffffff;
    box-shadow: 0 6px 20px rgba(255, 111, 0, 0.2),
      0 3px 8px rgba(255, 111, 0, 0.15), 0 0 0 3px rgba(255, 152, 0, 0.15);
    transform: translateY(-1px);
    border-color: rgba(255, 111, 0, 0.5);
  }

  :deep(.v-field__outline) {
    display: none;
  }

  :deep(.v-field__prepend-inner) {
    .v-icon {
      color: #546e7a;
      opacity: 0.6;
      transition: all 0.3s ease;
    }
  }

  :deep(.v-field--focused .v-field__prepend-inner .v-icon) {
    opacity: 1;
    transform: scale(1.15);
    color: #ff6f00;
  }
}

// Role Select Item Styling
.role-item {
  transition: all 0.2s ease;

  &:hover {
    background: rgba(255, 152, 0, 0.08);
  }
}

// Remember Checkbox
.remember-checkbox {
  :deep(.v-label) {
    font-size: 0.875rem;
    color: #546e7a;
    font-weight: 500;
  }
}

.remember-label {
  font-size: 0.875rem;
  color: #546e7a;
  font-weight: 500;
}

// Security Badge
.security-badge {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.875rem 1rem;
  background: linear-gradient(
    135deg,
    rgba(255, 152, 0, 0.06),
    rgba(84, 110, 122, 0.08)
  );
  border-radius: 12px;
  border: 1px solid rgba(255, 152, 0, 0.15);
  font-size: 0.75rem;
  color: #546e7a;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  box-shadow: 0 2px 4px rgba(255, 152, 0, 0.06);

  .v-icon {
    color: #ff9800 !important;
    filter: drop-shadow(0 1px 2px rgba(255, 152, 0, 0.3));
  }
}

// Modern Button with Enhanced Effects
.modern-btn {
  border-radius: 14px;
  height: 56px !important;
  font-size: 1rem;
  letter-spacing: 0.5px;
  width: 280px !important;
  display: block !important;
  margin: 0 auto !important;
  background: linear-gradient(
    135deg,
    #37474f 0%,
    #ff6f00 50%,
    #546e7a 100%
  ) !important;
  box-shadow: 0 10px 30px rgba(255, 111, 0, 0.3),
    0 5px 15px rgba(55, 71, 79, 0.3), 0 0 0 1px rgba(255, 255, 255, 0.1) inset !important;
  transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  overflow: hidden;
  transform: translateZ(0);

  &::before {
    content: "";
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(
      90deg,
      transparent,
      rgba(255, 255, 255, 0.25),
      transparent
    );
    transition: left 0.6s ease;
  }

  &:hover {
    box-shadow: 0 15px 45px rgba(255, 111, 0, 0.4),
      0 8px 20px rgba(55, 71, 79, 0.4), 0 0 0 1px rgba(255, 255, 255, 0.2) inset !important;
    transform: translateY(-2px) translateZ(0);

    &::before {
      left: 100%;
    }
  }

  &:active {
    transform: translateY(0) translateZ(0);
  }

  :deep(.v-icon) {
    transition: transform 0.3s ease;
    filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.3));
  }

  &:hover :deep(.v-icon) {
    transform: scale(1.15);
  }
}

// Responsive Utilities
.fill-height {
  min-height: 100vh;
}

// Mobile Adjustments
@media (max-width: 960px) {
  .login-card {
    margin: 1rem;
  }

  .branding-section {
    display: none;
  }
}

// Smooth Transitions
* {
  transition: background-color 0.3s ease, border-color 0.3s ease;
}
</style>
