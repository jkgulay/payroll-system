<template>
  <v-app>
    <v-main>
      <v-container class="fill-height" fluid>
        <v-row align="center" justify="center">
          <v-col cols="12" sm="8" md="5" lg="4">
            <v-card elevation="8" rounded="lg">
              <v-card-text class="text-center pa-8">
                <v-icon
                  icon="mdi-hard-hat"
                  size="64"
                  color="primary"
                  class="mb-4"
                ></v-icon>
                <h1 class="text-h4 font-weight-bold mb-2">Payroll System</h1>
                <p class="text-body-1 text-medium-emphasis mb-8">
                  Construction Company Management
                </p>

                <v-form @submit.prevent="handleLogin" ref="loginForm">
                  <v-text-field
                    v-model="form.email"
                    label="Username or Email"
                    prepend-inner-icon="mdi-account"
                    :rules="[rules.required]"
                    :error-messages="errors.email"
                    @input="errors.email = ''"
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
                    :rules="[rules.required]"
                    :error-messages="errors.password"
                    @input="errors.password = ''"
                  ></v-text-field>

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
                    size="large"
                    block
                    :loading="authStore.loading"
                    class="mb-4"
                  >
                    Login
                  </v-btn>

                  <v-alert
                    v-if="errorMessage"
                    type="error"
                    variant="tonal"
                    density="compact"
                  >
                    {{ errorMessage }}
                  </v-alert>
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
});

const errors = reactive({
  email: "",
  password: "",
});

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
    });

    toast.success("Login successful");

    // Redirect to intended page or dashboard
    const redirect = route.query.redirect || "/";
    router.push(redirect);
  } catch (error) {
    console.error("Login error:", error);

    if (error.response?.status === 401 || error.response?.status === 422) {
      errorMessage.value = error.response?.data?.message || "Invalid credentials";
    } else if (error.response?.data?.message) {
      errorMessage.value = error.response.data.message;
    } else {
      errorMessage.value = "An error occurred. Please try again.";
    }
  }
}
</script>

<style scoped>
.fill-height {
  min-height: 100vh;
}
</style>
