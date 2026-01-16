<template>
  <v-dialog v-model="showDialog" persistent max-width="600" :scrim="true">
    <v-card class="modern-dialog-card" elevation="24">
      <!-- Enhanced Warning Header -->
      <v-card-title class="modern-dialog-header modern-dialog-header-warning">
        <div class="d-flex align-center w-100">
          <v-avatar color="white" size="48" class="mr-4">
            <v-icon color="warning" size="32">mdi-lock-alert</v-icon>
          </v-avatar>
          <div>
            <div class="text-h5 font-weight-bold">
              Password Change Required
            </div>
            <div class="text-subtitle-2 text-white-70">
              Secure your account with a new password
            </div>
          </div>
        </div>
      </v-card-title>

      <v-card-text class="pa-6">
        <v-alert type="warning" variant="tonal" density="comfortable" class="mb-4" icon="mdi-shield-alert">
          <div class="text-subtitle-2 font-weight-bold mb-1">
            First-Time Login Detected
          </div>
          <div class="text-body-2">
            For security reasons, you must change your temporary password before
            continuing.
          </div>
        </v-alert>

        <v-form
          ref="passwordFormRef"
          @submit.prevent="handlePasswordChange"
          validate-on="submit lazy"
        >
          <!-- Current Password -->
          <div class="form-field-wrapper">
            <label class="form-label">
              <v-icon size="small" color="primary">mdi-lock-outline</v-icon>
              Current Password <span class="text-error">*</span>
            </label>
            <v-text-field
              v-model="passwordForm.current_password"
              placeholder="Enter your current password"
              :type="showCurrentPassword ? 'text' : 'password'"
              :rules="[rules.required]"
              variant="outlined"
              density="comfortable"
              color="primary"
              :disabled="loading"
            >
              <template v-slot:prepend-inner>
                <v-icon size="20">mdi-lock-outline</v-icon>
              </template>
              <template v-slot:append-inner>
                <v-icon
                  @click="showCurrentPassword = !showCurrentPassword"
                  size="20"
                  style="cursor: pointer"
                >
                  {{ showCurrentPassword ? "mdi-eye-off" : "mdi-eye" }}
                </v-icon>
              </template>
            </v-text-field>
          </div>

          <!-- New Password -->
          <div class="form-field-wrapper">
            <label class="form-label">
              <v-icon size="small" color="primary">mdi-lock-plus-outline</v-icon>
              New Password <span class="text-error">*</span>
            </label>
            <v-text-field
              v-model="passwordForm.new_password"
              placeholder="Enter your new password"
              :type="showNewPassword ? 'text' : 'password'"
              :rules="[rules.required, rules.minLength, rules.passwordStrength]"
              variant="outlined"
              density="comfortable"
              color="primary"
              :disabled="loading"
            >
              <template v-slot:prepend-inner>
                <v-icon size="20">mdi-lock-plus-outline</v-icon>
              </template>
              <template v-slot:append-inner>
                <v-icon
                  @click="showNewPassword = !showNewPassword"
                  size="20"
                  style="cursor: pointer"
                >
                  {{ showNewPassword ? "mdi-eye-off" : "mdi-eye" }}
                </v-icon>
              </template>
            </v-text-field>
          </div>

          <!-- Confirm Password -->
          <div class="form-field-wrapper">
            <label class="form-label">
              <v-icon size="small" color="primary">mdi-lock-check-outline</v-icon>
              Confirm New Password <span class="text-error">*</span>
            </label>
            <v-text-field
              v-model="passwordForm.new_password_confirmation"
              placeholder="Confirm your new password"
              :type="showConfirmPassword ? 'text' : 'password'"
              :rules="[rules.required, rules.passwordMatch]"
              variant="outlined"
              density="comfortable"
              color="primary"
              :disabled="loading"
            >
              <template v-slot:prepend-inner>
                <v-icon size="20">mdi-lock-check-outline</v-icon>
              </template>
              <template v-slot:append-inner>
                <v-icon
                  @click="showConfirmPassword = !showConfirmPassword"
                  size="20"
                  style="cursor: pointer"
                >
                  {{ showConfirmPassword ? "mdi-eye-off" : "mdi-eye" }}
                </v-icon>
              </template>
            </v-text-field>
          </div>

          <!-- Password Requirements -->
          <v-card variant="tonal" color="info" class="mt-3" style="border-radius: 12px;">
            <v-card-text class="pa-3">
              <div class="text-caption font-weight-bold mb-2 d-flex align-center">
                <v-icon size="small" class="mr-2">mdi-shield-check</v-icon>
                Password Requirements:
              </div>
              <ul class="text-caption pl-4">
                <li>At least 8 characters long</li>
                <li>Contains uppercase and lowercase letters</li>
                <li>Contains at least one number</li>
                <li>Different from your current password</li>
              </ul>
            </v-card-text>
          </v-card>

          <!-- Error Message -->
          <v-alert
            v-if="errorMessage"
            type="error"
            variant="tonal"
            density="compact"
            class="mt-3"
            closable
            @click:close="errorMessage = ''"
          >
            {{ errorMessage }}
          </v-alert>
        </v-form>
      </v-card-text>

      <v-divider></v-divider>

      <!-- Enhanced Actions -->
      <v-card-actions class="pa-4">
        <v-spacer></v-spacer>
        <v-btn
          color="primary"
          size="large"
          :loading="loading"
          @click="handlePasswordChange"
          prepend-icon="mdi-check-circle"
          class="px-6"
          elevation="2"
        >
          <span class="font-weight-bold">Change Password</span>
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, reactive, watch } from "vue";
import { useToast } from "vue-toastification";
import api from "@/services/api";

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
});

const emit = defineEmits(["update:modelValue", "passwordChanged"]);

const toast = useToast();

const showDialog = ref(props.modelValue);
const loading = ref(false);
const errorMessage = ref("");

const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);

const passwordFormRef = ref(null);

const passwordForm = reactive({
  current_password: "",
  new_password: "",
  new_password_confirmation: "",
});

// Validation rules
const rules = {
  required: (value) => !!value || "This field is required",
  minLength: (value) =>
    (value && value.length >= 8) || "Password must be at least 8 characters",
  passwordStrength: (value) => {
    if (!value) return true;
    const hasUpper = /[A-Z]/.test(value);
    const hasLower = /[a-z]/.test(value);
    const hasNumber = /[0-9]/.test(value);
    return (
      (hasUpper && hasLower && hasNumber) ||
      "Password must contain uppercase, lowercase, and numbers"
    );
  },
  passwordMatch: (value) =>
    value === passwordForm.new_password || "Passwords do not match",
};

// Watch for prop changes
watch(
  () => props.modelValue,
  (newValue) => {
    showDialog.value = newValue;
  }
);

// Watch for dialog changes
watch(showDialog, (newValue) => {
  emit("update:modelValue", newValue);
});

async function handlePasswordChange() {
  errorMessage.value = "";

  // Validate form
  const { valid } = await passwordFormRef.value.validate();
  if (!valid) return;

  loading.value = true;

  try {
    await api.post("/profile/change-password", passwordForm);

    toast.success("Password changed successfully!");

    // Reset form
    passwordForm.current_password = "";
    passwordForm.new_password = "";
    passwordForm.new_password_confirmation = "";
    passwordFormRef.value.reset();

    // Emit success event
    emit("passwordChanged");

    // Close dialog
    showDialog.value = false;
  } catch (error) {
    console.error("Password change error:", error);

    if (error.response?.status === 422) {
      // Validation errors
      if (error.response?.data?.errors) {
        const errors = error.response.data.errors;
        const errorMessages = Object.values(errors).flat();
        errorMessage.value = errorMessages.join(". ");
      } else {
        errorMessage.value =
          error.response?.data?.message || "Validation error occurred";
      }
    } else if (error.response?.status === 401) {
      errorMessage.value = "Current password is incorrect";
    } else {
      errorMessage.value =
        error.response?.data?.message ||
        "Failed to change password. Please try again.";
    }
  } finally {
    loading.value = false;
  }
}
</script>

<style scoped>
.bg-warning {
  background: linear-gradient(135deg, #ff6f00 0%, #ff9800 100%) !important;
}

:deep(.v-field) {
  border-radius: 8px;
}

:deep(.v-card) {
  border-radius: 12px;
}
</style>
