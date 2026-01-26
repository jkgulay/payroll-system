<template>
  <v-dialog v-model="showDialog" persistent max-width="450" :scrim="true">
    <v-card elevation="0" class="password-change-card">
      <!-- Modern Header matching Sign In -->
      <div class="password-header-modern">
        <div class="header-content">
          <div class="icon-circle">
            <v-icon icon="mdi-lock-alert" size="40" color="white"></v-icon>
          </div>
          <h1 class="header-title">Change Password</h1>
          <p class="header-subtitle">Secure your account with a new password</p>
        </div>
      </div>

      <v-card-text class="form-section">
        <v-form
          ref="passwordFormRef"
          @submit.prevent="handlePasswordChange"
          validate-on="submit lazy"
        >
          <!-- Current Password -->
          <div class="field-wrapper">
            <div class="field-label">
              <span>Current Password</span>
            </div>
            <v-text-field
              v-model="passwordForm.current_password"
              placeholder="Enter your current password"
              :type="showCurrentPassword ? 'text' : 'password'"
              variant="solo"
              flat
              color="#ed985f"
              bg-color="#f8f9fa"
              :rules="[rules.required]"
              density="comfortable"
              class="modern-field"
              hide-details="auto"
              :disabled="loading"
            >
              <template v-slot:prepend-inner>
                <v-icon color="#001f3d" size="20">mdi-lock-outline</v-icon>
              </template>
              <template v-slot:append-inner>
                <v-icon
                  @click="showCurrentPassword = !showCurrentPassword"
                  color="#001f3d"
                  size="20"
                  style="cursor: pointer"
                >
                  {{ showCurrentPassword ? "mdi-eye-off" : "mdi-eye" }}
                </v-icon>
              </template>
            </v-text-field>
          </div>

          <!-- New Password -->
          <div class="field-wrapper">
            <div class="field-label">
              <span>New Password</span>
            </div>
            <v-text-field
              v-model="passwordForm.new_password"
              placeholder="Enter your new password"
              :type="showNewPassword ? 'text' : 'password'"
              variant="solo"
              flat
              color="#ed985f"
              bg-color="#f8f9fa"
              :rules="[rules.required, rules.minLength, rules.passwordStrength]"
              density="comfortable"
              class="modern-field"
              hide-details="auto"
              :disabled="loading"
            >
              <template v-slot:prepend-inner>
                <v-icon color="#001f3d" size="20">mdi-lock-plus-outline</v-icon>
              </template>
              <template v-slot:append-inner>
                <v-icon
                  @click="showNewPassword = !showNewPassword"
                  color="#001f3d"
                  size="20"
                  style="cursor: pointer"
                >
                  {{ showNewPassword ? "mdi-eye-off" : "mdi-eye" }}
                </v-icon>
              </template>
            </v-text-field>
          </div>

          <!-- Confirm Password -->
          <div class="field-wrapper">
            <div class="field-label">
              <span>Confirm New Password</span>
            </div>
            <v-text-field
              v-model="passwordForm.new_password_confirmation"
              placeholder="Confirm your new password"
              :type="showConfirmPassword ? 'text' : 'password'"
              variant="solo"
              flat
              color="#ed985f"
              bg-color="#f8f9fa"
              :rules="[rules.required, rules.passwordMatch]"
              density="comfortable"
              class="modern-field"
              hide-details="auto"
              :disabled="loading"
            >
              <template v-slot:prepend-inner>
                <v-icon color="#001f3d" size="20"
                  >mdi-lock-check-outline</v-icon
                >
              </template>
              <template v-slot:append-inner>
                <v-icon
                  @click="showConfirmPassword = !showConfirmPassword"
                  color="#001f3d"
                  size="20"
                  style="cursor: pointer"
                >
                  {{ showConfirmPassword ? "mdi-eye-off" : "mdi-eye" }}
                </v-icon>
              </template>
            </v-text-field>
          </div>

          <!-- Password Requirements -->
          <div class="requirements-box">
            <div class="requirements-header">
              <span>Password Requirements</span>
            </div>
            <ul class="requirements-list">
              <li>At least 8 characters long</li>
              <li>Contains uppercase and lowercase letters</li>
              <li>Contains at least one number</li>
              <li>Different from your current password</li>
            </ul>
          </div>

          <!-- Error Message -->
          <v-alert
            v-if="errorMessage"
            type="error"
            variant="tonal"
            density="compact"
            class="mt-3"
            closable
            @click:close="errorMessage = ''"
            style="border-radius: 12px"
          >
            {{ errorMessage }}
          </v-alert>

          <!-- Change Password Button -->
          <v-btn
            type="submit"
            color="#ed985f"
            size="x-large"
            :loading="loading"
            class="change-password-btn"
            elevation="0"
          >
            Change Password
            <v-icon end size="small">mdi-arrow-right</v-icon>
          </v-btn>

          <!-- Security Info -->
          <div class="security-info">
            <v-icon size="16" color="#001f3d">mdi-shield-lock</v-icon>
            <span>Your password is encrypted and secure</span>
          </div>
        </v-form>
      </v-card-text>
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
  },
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

<style scoped lang="scss">
// Match Login View Design
.password-change-card {
  background: #ffffff;
  border-radius: 24px !important;
  overflow: hidden;
  box-shadow: 0 20px 60px rgba(0, 31, 61, 0.15) !important;
}

.password-header-modern {
  padding: 2rem 1.5rem 1.5rem;
  background: linear-gradient(135deg, #001f3d 0%, #ed985f 100%);
  position: relative;
  text-align: center;

  &::after {
    content: "";
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, transparent, #f7b980, transparent);
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
  -webkit-backdrop-filter: blur(10px);
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

.form-section {
  padding: 2rem 1.5rem 1.75rem;
}

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
  color: #001f3d;
  letter-spacing: 0.3px;
}

:deep(.modern-field) {
  .v-field {
    border-radius: 12px !important;
    box-shadow: 0 2px 8px rgba(0, 31, 61, 0.04) !important;
    border: 1px solid rgba(0, 31, 61, 0.08) !important;
    transition: all 0.3s ease !important;

    &:hover {
      box-shadow: 0 4px 12px rgba(0, 31, 61, 0.08) !important;
      border-color: rgba(237, 152, 95, 0.3) !important;
    }

    &--focused {
      box-shadow: 0 4px 16px rgba(237, 152, 95, 0.15) !important;
      border-color: #ed985f !important;
    }
  }

  .v-field__input {
    padding: 14px 16px;
    font-size: 14px;
    color: #001f3d;
    min-height: 48px;

    &::placeholder {
      color: rgba(0, 31, 61, 0.4);
      opacity: 1;
    }
  }

  .v-field__prepend-inner {
    padding-right: 8px;
  }

  .v-field__append-inner {
    padding-left: 8px;
  }
}

.requirements-box {
  background: linear-gradient(
    135deg,
    rgba(0, 31, 61, 0.02) 0%,
    rgba(237, 152, 95, 0.02) 100%
  );
  border: 1px solid rgba(0, 31, 61, 0.08);
  border-radius: 12px;
  padding: 16px 20px;
  margin-bottom: 20px;
}

.requirements-header {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 700;
  color: #001f3d;
  margin-bottom: 12px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.requirements-list {
  margin: 0;
  padding-left: 20px;
  list-style: none;

  li {
    font-size: 13px;
    color: rgba(0, 31, 61, 0.7);
    line-height: 1.8;
    position: relative;
    padding-left: 8px;

    &::before {
      content: "•";
      position: absolute;
      left: -8px;
      color: #ed985f;
      font-weight: bold;
    }
  }
}

.change-password-btn {
  height: 50px !important;
  border-radius: 12px !important;
  font-size: 0.95rem !important;
  font-weight: 600 !important;
  letter-spacing: 0.3px;
  text-transform: none !important;
  margin-bottom: 1.25rem;
  margin-top: 1.5rem;
  width: 200px !important;
  display: block !important;
  margin-left: auto !important;
  margin-right: auto !important;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%) !important;
  box-shadow:
    0 8px 20px rgba(237, 152, 95, 0.3),
    0 4px 10px rgba(237, 152, 95, 0.2) !important;
  transition: all 0.3s ease !important;

  &:hover {
    box-shadow:
      0 12px 28px rgba(237, 152, 95, 0.4),
      0 6px 14px rgba(237, 152, 95, 0.3) !important;
    transform: translateY(-2px);
  }

  &:active {
    transform: translateY(0);
  }
}

.security-info {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
  padding: 0.75rem;
  background: rgba(0, 31, 61, 0.06);
  border-radius: 10px;
  font-size: 0.75rem;
  color: #001f3d;
  font-weight: 500;
  border: 1px solid rgba(0, 31, 61, 0.1);
  max-width: 280px;
  margin-left: auto;
  margin-right: auto;
}

// Alert styling
:deep(.v-alert) {
  border-radius: 12px !important;

  .v-alert__prepend {
    margin-right: 12px;
  }
}
</style>
