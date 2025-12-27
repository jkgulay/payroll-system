<template>
  <v-app>
    <v-main class="login-main">
      <v-container class="fill-height" fluid>
        <v-row align="center" justify="center">
          <v-col cols="12" sm="8" md="5" lg="4">
            <!-- Construction-themed Login Card -->
            <v-card elevation="12" rounded="xl" class="login-card">
              <!-- Header Section with Construction Elements -->
              <div class="login-header pa-8">
                <div class="hardhat-icon-container mb-4">
                  <v-icon
                    icon="mdi-hard-hat"
                    size="80"
                    color="white"
                    class="hardhat-icon"
                  ></v-icon>
                </div>
                <h1 class="text-h3 font-weight-bold text-white mb-2">
                  Payroll System
                </h1>
                <div class="system-subtitle">
                  <v-icon size="small" class="mr-1">mdi-domain</v-icon>
                  Construction Company Management
                </div>
              </div>

              <v-card-text class="pa-8">
                <v-form @submit.prevent="handleLogin" ref="loginForm">
                  <v-text-field
                    v-model="form.email"
                    label="Username or Email"
                    prepend-inner-icon="mdi-account-circle"
                    variant="outlined"
                    color="primary"
                    :rules="[rules.required]"
                    :error-messages="errors.email"
                    @input="errors.email = ''"
                    class="mb-2"
                  ></v-text-field>

                  <v-text-field
                    v-model="form.password"
                    label="Password"
                    :type="showPassword ? 'text' : 'password'"
                    prepend-inner-icon="mdi-lock"
                    :append-inner-icon="
                      showPassword ? 'mdi-eye' : 'mdi-eye-off'
                    "
                    @click:append-inner="showPassword = !showPassword"
                    variant="outlined"
                    color="primary"
                    :rules="[rules.required]"
                    :error-messages="errors.password"
                    @input="errors.password = ''"
                    class="mb-2"
                  ></v-text-field>

                  <v-select
                    v-model="form.role"
                    label="Login As"
                    prepend-inner-icon="mdi-shield-account"
                    :items="roles"
                    variant="outlined"
                    color="primary"
                    :rules="[rules.required]"
                    :error-messages="errors.role"
                    @update:modelValue="errors.role = ''"
                    class="mb-2"
                  ></v-select>

                  <v-checkbox
                    v-model="form.remember"
                    label="Remember me"
                    color="primary"
                    hide-details
                    class="mb-4"
                  ></v-checkbox>

                  <v-btn
                    type="submit"
                    color="primary"
                    size="x-large"
                    block
                    :loading="authStore.loading"
                    class="construction-login-btn mb-4"
                    prepend-icon="mdi-login"
                  >
                    Sign In
                  </v-btn>

                  <v-alert
                    v-if="errorMessage"
                    type="error"
                    variant="tonal"
                    density="compact"
                    class="mt-4"
                  >
                    {{ errorMessage }}
                  </v-alert>
                </v-form>
              </v-card-text>

              <!-- Footer with Construction Pattern -->
              <div class="login-footer pa-4 text-center">
                <div class="steel-divider mb-3"></div>
                <div class="text-caption font-weight-medium">
                  <v-icon size="small" class="mr-1">mdi-shield-check</v-icon>
                  Construction Payroll Management System v1.0
                </div>
              </div>
            </v-card>
          </v-col>
        </v-row>
      </v-container>
    </v-main>
  </v-app>
</template>

<script setup>
import { ref, reactive } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import { useToast } from "vue-toastification";

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const toast = useToast();

const loginForm = ref(null);
const showPassword = ref(false);
const errorMessage = ref("");

const form = reactive({
  email: "",
  password: "",
  remember: false,
  role: "",
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
    await authStore.login({
      email: form.email,
      password: form.password,
      remember: form.remember,
      role: form.role,
    });

    toast.success("Login successful");

    // Redirect to intended page or dashboard
    const redirect = route.query.redirect || "/";
    router.push(redirect);
  } catch (error) {
    console.error("Login error:", error);

    if (error.response?.status === 401 || error.response?.status === 422) {
      errorMessage.value =
        error.response?.data?.message || "Invalid credentials";
    } else if (error.response?.data?.message) {
      errorMessage.value = error.response.data.message;
    } else {
      errorMessage.value = "An error occurred. Please try again.";
    }
  }
}
</script>

<style scoped lang="scss">
// Construction-themed Login Page

.login-main {
  background: #FFFFFF;
  position: relative;
  overflow: hidden;
}

.login-card {
  overflow: hidden;
  border: 3px solid rgba(216, 67, 21, 0.3);
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5) !important;
}

// Header with Construction Orange Gradient
.login-header {
  background: linear-gradient(135deg, #D84315 0%, #F4511E 50%, #FF6E40 100%);
  text-align: center;
  position: relative;
  
  &::after {
    content: "";
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, 
      transparent 0%,
      rgba(255, 255, 255, 0.5) 50%,
      transparent 100%);
  }
}

.hardhat-icon-container {
  display: inline-block;
  position: relative;
  
  .hardhat-icon {
    filter: drop-shadow(0 4px 8px rgba(0, 0, 0, 0.3));
    animation: float 3s ease-in-out infinite;
  }
}

@keyframes float {
  0%, 100% {
    transform: translateY(0px);
  }
  50% {
    transform: translateY(-10px);
  }
}

.system-subtitle {
  color: rgba(255, 255, 255, 0.95);
  font-size: 14px;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 1px;
  display: flex;
  align-items: center;
  justify-content: center;
}

// Login Button
.construction-login-btn {
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  box-shadow: 0 4px 12px rgba(216, 67, 21, 0.4);
  transition: all 0.3s ease;
  
  &:hover {
    box-shadow: 0 6px 16px rgba(216, 67, 21, 0.6);
    transform: translateY(-2px);
  }
}

// Footer
.login-footer {
  background: linear-gradient(135deg, #FAFAFA 0%, #ECEFF1 100%);
  color: #37474F;
  border-top: 3px solid #D84315;
}

// Form fields enhancement
:deep(.v-text-field),
:deep(.v-select) {
  .v-field {
    border: 2px solid rgba(216, 67, 21, 0.2);
    transition: all 0.3s ease;
    
    &:hover {
      border-color: rgba(216, 67, 21, 0.4);
    }
  }
  
  .v-field--focused {
    border-color: #D84315;
    box-shadow: 0 0 0 3px rgba(216, 67, 21, 0.1);
  }
}

.fill-height {
  min-height: 100vh;
}
</style>
