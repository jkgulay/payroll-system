<template>
  <v-dialog v-model="showDialog" persistent max-width="500" :scrim="true">
    <v-card>
      <v-card-title class="bg-warning text-white d-flex align-center pa-4">
        <v-icon icon="mdi-lock-alert" class="mr-2" size="28"></v-icon>
        <span class="text-h6">Password Change Required</span>
      </v-card-title>

      <v-card-text class="pa-6">
        <v-alert type="warning" variant="tonal" density="compact" class="mb-4">
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
          <v-text-field
            v-model="passwordForm.current_password"
            label="Current Password"
            :type="showCurrentPassword ? 'text' : 'password'"
            :rules="[rules.required]"
            variant="outlined"
            density="comfortable"
            color="primary"
            class="mb-2"
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

          <!-- New Password -->
          <v-text-field
            v-model="passwordForm.new_password"
            label="New Password"
            :type="showNewPassword ? 'text' : 'password'"
            :rules="[rules.required, rules.minLength, rules.passwordStrength]"
            variant="outlined"
            density="comfortable"
            color="primary"
            class="mb-2"
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

          <!-- Confirm Password -->
          <v-text-field
            v-model="passwordForm.new_password_confirmation"
            label="Confirm New Password"
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

          <!-- Password Requirements -->
          <v-card variant="tonal" color="info" class="mt-3">
            <v-card-text class="pa-3">
              <div class="text-caption font-weight-bold mb-2">
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

      <v-card-actions class="px-6 pb-4">
        <v-spacer></v-spacer>
        <v-btn
          color="primary"
          variant="elevated"
          size="large"
          :loading="loading"
          @click="handlePasswordChange"
          prepend-icon="mdi-check"
        >
          Change Password
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
