<template>
  <v-dialog v-model="show" max-width="500" persistent>
    <v-card>
      <v-card-title class="d-flex align-center bg-primary pa-4">
        <v-icon icon="mdi-shield-lock-outline" size="28" class="mr-3"></v-icon>
        <span class="text-h6">Two-Factor Authentication</span>
      </v-card-title>

      <v-card-text class="pa-6">
        <p class="text-body-1 mb-4">
          Enter the 6-digit code from your authenticator app to complete login.
        </p>

        <v-text-field
          v-model="code"
          label="Verification Code"
          variant="outlined"
          maxlength="6"
          :error-messages="errorMessage"
          @keyup.enter="verify"
          autofocus
          :disabled="loading"
        >
          <template v-slot:prepend-inner>
            <v-icon icon="mdi-numeric"></v-icon>
          </template>
        </v-text-field>

        <v-divider class="my-4"></v-divider>

        <div class="text-center">
          <p class="text-body-2 text-medium-emphasis mb-2">
            Lost access to your authenticator app?
          </p>
          <v-btn
            variant="text"
            color="primary"
            size="small"
            @click="showRecoveryInput = !showRecoveryInput"
          >
            Use a recovery code
          </v-btn>
        </div>

        <v-expand-transition>
          <div v-if="showRecoveryInput" class="mt-4">
            <v-text-field
              v-model="recoveryCode"
              label="Recovery Code"
              variant="outlined"
              placeholder="xxxxxxxxxx-xxxxxxxxxx"
              :error-messages="errorMessage"
              @keyup.enter="verify"
              :disabled="loading"
            >
              <template v-slot:prepend-inner>
                <v-icon icon="mdi-key-variant"></v-icon>
              </template>
            </v-text-field>
          </div>
        </v-expand-transition>
      </v-card-text>

      <v-card-actions class="pa-4">
        <v-btn variant="text" @click="cancel" :disabled="loading">
          Cancel
        </v-btn>
        <v-spacer></v-spacer>
        <v-btn
          color="primary"
          variant="elevated"
          @click="verify"
          :loading="loading"
          prepend-icon="mdi-check"
        >
          Verify
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, watch } from "vue";

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  userId: {
    type: Number,
    default: null,
  },
});

const emit = defineEmits(["update:modelValue", "verified", "cancel"]);

const show = ref(props.modelValue);
const code = ref("");
const recoveryCode = ref("");
const showRecoveryInput = ref(false);
const errorMessage = ref("");
const loading = ref(false);

watch(
  () => props.modelValue,
  (newValue) => {
    show.value = newValue;
    if (newValue) {
      // Reset form when dialog opens
      code.value = "";
      recoveryCode.value = "";
      showRecoveryInput.value = false;
      errorMessage.value = "";
    }
  }
);

watch(show, (newValue) => {
  emit("update:modelValue", newValue);
});

async function verify() {
  const verificationCode = showRecoveryInput.value ? recoveryCode.value : code.value;

  if (!verificationCode) {
    errorMessage.value = "Please enter a code";
    return;
  }

  if (!showRecoveryInput.value && verificationCode.length !== 6) {
    errorMessage.value = "Please enter a valid 6-digit code";
    return;
  }

  if (!props.userId) {
    errorMessage.value = "Invalid session. Please try logging in again.";
    return;
  }

  loading.value = true;
  errorMessage.value = "";

  emit("verified", {
    userId: props.userId,
    code: verificationCode,
  });

  // Loading state will be managed by parent component
  // Reset will happen when dialog closes
}

function cancel() {
  show.value = false;
  emit("cancel");
}

// Expose method to handle verification errors from parent
function setError(message) {
  errorMessage.value = message;
  loading.value = false;
}

function setLoading(value) {
  loading.value = value;
}

defineExpose({
  setError,
  setLoading,
});
</script>

<style scoped>
/* Add any additional styling if needed */
</style>
