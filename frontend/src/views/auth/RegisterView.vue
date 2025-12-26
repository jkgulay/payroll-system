<template>
  <v-app>
    <v-main>
      <v-container class="fill-height" fluid>
        <v-row align="center" justify="center">
          <v-col cols="12" sm="8" md="6" lg="5">
            <v-card elevation="8" rounded="lg">
              <v-card-text class="text-center pa-8">
                <v-icon
                  icon="mdi-account-plus"
                  size="64"
                  color="primary"
                  class="mb-4"
                ></v-icon>
                <h1 class="text-h4 font-weight-bold mb-2">Create Account</h1>
                <p class="text-body-1 text-medium-emphasis mb-8">
                  Register for Payroll System
                </p>

                <v-form @submit.prevent="handleRegister" ref="registerForm">
                  <v-text-field
                    v-model="form.username"
                    label="Username"
                    prepend-inner-icon="mdi-account"
                    :rules="[rules.required, rules.username]"
                    :error-messages="errors.username"
                    @input="errors.username = ''"
                  ></v-text-field>

                  <v-text-field
                    v-model="form.email"
                    label="Email"
                    type="email"
                    prepend-inner-icon="mdi-email"
                    :rules="[rules.required, rules.email]"
                    :error-messages="errors.email"
                    @input="errors.email = ''"
                  ></v-text-field>

                  <v-select
                    v-model="form.role"
                    label="Role"
                    prepend-inner-icon="mdi-shield-account"
                    :items="roles"
                    :rules="[rules.required]"
                    :error-messages="errors.role"
                    @update:modelValue="errors.role = ''"
                  ></v-select>

                  <v-text-field
                    v-model="form.password"
                    label="Password"
                    :type="showPassword ? 'text' : 'password'"
                    prepend-inner-icon="mdi-lock"
                    :append-inner-icon="
                      showPassword ? 'mdi-eye' : 'mdi-eye-off'
                    "
                    @click:append-inner="showPassword = !showPassword"
                    :rules="[rules.required, rules.minLength]"
                    :error-messages="errors.password"
                    @input="errors.password = ''"
                  ></v-text-field>

                  <v-text-field
                    v-model="form.password_confirmation"
                    label="Confirm Password"
                    :type="showPasswordConfirm ? 'text' : 'password'"
                    prepend-inner-icon="mdi-lock-check"
                    :append-inner-icon="
                      showPasswordConfirm ? 'mdi-eye' : 'mdi-eye-off'
                    "
                    @click:append-inner="showPasswordConfirm = !showPasswordConfirm"
                    :rules="[rules.required, rules.passwordMatch]"
                    :error-messages="errors.password_confirmation"
                    @input="errors.password_confirmation = ''"
                  ></v-text-field>

                  <v-btn
                    type="submit"
                    color="primary"
                    size="large"
                    block
                    :loading="loading"
                    class="mb-4"
                  >
                    Register
                  </v-btn>

                  <v-alert
                    v-if="errorMessage"
                    type="error"
                    variant="tonal"
                    density="compact"
                    class="mb-4"
                  >
                    {{ errorMessage }}
                  </v-alert>

                  <v-alert
                    v-if="successMessage"
                    type="success"
                    variant="tonal"
                    density="compact"
                    class="mb-4"
                  >
                    {{ successMessage }}
                  </v-alert>

                  <v-divider class="my-4"></v-divider>

                  <div class="text-center">
                    <span class="text-body-2 text-medium-emphasis">
                      Already have an account?
                    </span>
                    <v-btn
                      variant="text"
                      color="primary"
                      @click="$router.push({ name: 'login' })"
                    >
                      Login
                    </v-btn>
                  </div>
                </v-form>
              </v-card-text>

              <v-divider></v-divider>

              <v-card-text
                class="text-center text-caption text-medium-emphasis"
              >
                Construction Payroll Management System v1.0
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </v-container>
    </v-main>
  </v-app>
</template>

<script setup>
import { ref, reactive } from "vue";
import { useRouter } from "vue-router";
import { useToast } from "vue-toastification";
import api from "@/services/api";

const router = useRouter();
const toast = useToast();

const registerForm = ref(null);
const showPassword = ref(false);
const showPasswordConfirm = ref(false);
const loading = ref(false);
const errorMessage = ref("");
const successMessage = ref("");

const form = reactive({
  username: "",
  email: "",
  password: "",
  password_confirmation: "",
  role: "",
});

const roles = [
  { title: "Accountant", value: "accountant" },
  { title: "Employee", value: "employee" },
];

const errors = reactive({
  username: "",
  email: "",
  password: "",
  password_confirmation: "",
  role: "",
});

const rules = {
  required: (value) => !!value || "This field is required",
  email: (value) => /.+@.+\..+/.test(value) || "Email must be valid",
  username: (value) =>
    (value && value.length >= 3) || "Username must be at least 3 characters",
  minLength: (value) =>
    (value && value.length >= 8) || "Password must be at least 8 characters",
  passwordMatch: (value) =>
    value === form.password || "Passwords do not match",
};

async function handleRegister() {
  errorMessage.value = "";
  successMessage.value = "";

  // Validate form
  const { valid } = await registerForm.value.validate();
  if (!valid) return;

  loading.value = true;

  try {
    const response = await api.post("/register", {
      username: form.username,
      email: form.email,
      password: form.password,
      password_confirmation: form.password_confirmation,
      role: form.role,
    });

    successMessage.value = response.data.message || "Registration successful";
    toast.success("Account created successfully! Redirecting to login...");

    // Clear form
    form.username = "";
    form.email = "";
    form.password = "";
    form.password_confirmation = "";
    form.role = "";

    // Redirect to login after 2 seconds
    setTimeout(() => {
      router.push({ name: "login" });
    }, 2000);
  } catch (error) {
    console.error("Registration error:", error);

    if (error.response?.status === 422) {
      // Validation errors
      const validationErrors = error.response.data.errors;
      if (validationErrors) {
        Object.keys(validationErrors).forEach((key) => {
          if (errors[key] !== undefined) {
            errors[key] = validationErrors[key][0];
          }
        });
      }
      errorMessage.value =
        error.response.data.message || "Please check the form for errors";
    } else if (error.response?.data?.message) {
      errorMessage.value = error.response.data.message;
    } else {
      errorMessage.value = "An error occurred. Please try again.";
    }
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
.fill-height {
  min-height: 100vh;
}
</style>
