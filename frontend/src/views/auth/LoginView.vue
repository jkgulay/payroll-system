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
                Giovanni Construction Management System
              </h1>
              <p class="text-h6 text-white-80 mb-6 text-shadow">
                At Giovanni Construction, we specialize in building not just
                structures but also efficient solutions for managing your
                workforce. Our system is designed to tackle the unique demands
                of the construction industry, from payroll and compliance to
                attendance tracking, so you can focus on building the future.
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
                      <v-icon size="18" color="#001f3d"
                        >mdi-account-outline</v-icon
                      >
                      <span>Username or Email</span>
                    </div>
                    <v-text-field
                      v-model="form.email"
                      placeholder="Enter your username or email"
                      variant="solo"
                      flat
                      color="#ed985f"
                      bg-color="#f8f9fa"
                      :rules="[rules.required]"
                      :error-messages="errors.email"
                      @input="errors.email = ''"
                      density="comfortable"
                      class="modern-field"
                      hide-details="auto"
                    >
                      <template v-slot:prepend-inner>
                        <v-icon color="#001f3d" size="20"
                          >mdi-account-circle</v-icon
                        >
                      </template>
                    </v-text-field>
                  </div>

                  <!-- Password Field -->
                  <div class="field-wrapper">
                    <div class="field-label">
                      <v-icon size="18" color="#001f3d"
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
                      color="#ed985f"
                      bg-color="#f8f9fa"
                      :rules="[rules.required, rules.minLength(8)]"
                      :error-messages="errors.password"
                      @input="errors.password = ''"
                      density="comfortable"
                      class="modern-field"
                      hide-details="auto"
                    >
                      <template v-slot:prepend-inner>
                        <v-icon color="#001f3d" size="20">mdi-lock</v-icon>
                      </template>
                      <template v-slot:append-inner>
                        <v-icon
                          @click="showPassword = !showPassword"
                          color="#001f3d"
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
                      <v-icon size="18" color="#001f3d"
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
                      color="#ed985f"
                      bg-color="#f8f9fa"
                      :rules="[rules.required]"
                      :error-messages="errors.role"
                      @update:modelValue="errors.role = ''"
                      density="comfortable"
                      class="modern-field"
                      hide-details="auto"
                    >
                      <template v-slot:prepend-inner>
                        <v-icon color="#001f3d" size="20"
                          >mdi-shield-account</v-icon
                        >
                      </template>
                      <template v-slot:item="{ props, item }">
                        <v-list-item v-bind="props" class="role-list-item">
                          <template v-slot:prepend>
                            <v-icon
                              :color="
                                item.value === 'admin'
                                  ? '#ed985f'
                                  : item.value === 'accountant'
                                    ? '#f7b980'
                                    : item.value === 'payrollist'
                                      ? '#4CAF50'
                                      : '#001f3d'
                              "
                              size="20"
                            >
                              {{
                                item.value === "admin"
                                  ? "mdi-shield-crown"
                                  : item.value === "accountant"
                                    ? "mdi-calculator"
                                    : item.value === "payrollist"
                                      ? "mdi-cash-register"
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
                      color="#ed985f"
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
                    color="#ed985f"
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
                    <v-icon size="16" color="#001f3d">mdi-shield-check</v-icon>
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

      <!-- Force Password Change Dialog -->
      <ChangePasswordDialog
        v-model="showPasswordChangeDialog"
        @passwordChanged="handlePasswordChanged"
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
import ChangePasswordDialog from "@/components/ChangePasswordDialog.vue";
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
const showPasswordChangeDialog = ref(false);
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
  { title: "Payrollist", value: "payrollist" },
  { title: "Employee", value: "employee" },
];

const rules = {
  required: (value) => !!value || "This field is required",
  minLength: (min) => (value) =>
    (value && value.length >= min) || `Must be at least ${min} characters`,
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

    // Check if user must change password
    if (response.user && response.user.must_change_password) {
      showPasswordChangeDialog.value = true;
      return; // Don't redirect yet
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
      api.defaults.headers.common["Authorization"] =
        `Bearer ${response.data.token}`;

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
        error.response?.data?.message || "Invalid verification code",
      );
    } else {
      twoFactorVerifyRef.value.setError(
        "Verification failed. Please try again.",
      );
    }
  }
}

async function handlePasswordChanged() {
  // Update user status in store (local state)
  authStore.updateUserPasswordStatus();

  // Fetch updated user data from backend to sync must_change_password flag
  await authStore.fetchUser();

  toast.success("Password changed successfully! Redirecting...");

  // Small delay for user to see the success message
  setTimeout(() => {
    // Redirect to appropriate dashboard based on role
    const redirect = route.query.redirect;
    if (redirect) {
      router.push(redirect);
    } else {
      const role = authStore.userRole;
      if (role === "employee") {
        router.push("/employee-dashboard");
      } else if (role === "accountant") {
        router.push("/accountant-dashboard");
      } else {
        router.push("/admin-dashboard");
      }
    }
  }, 1000);
}

function handleTwoFactorCancel() {
  showTwoFactorDialog.value = false;
  twoFactorUserId.value = null;
  // Clear any error messages
  errorMessage.value = "";
  toast.info("Login cancelled");
}
</script>

<style scoped lang="scss" src="@/styles/login-view.scss"></style>
