<template>
  <v-card>
    <v-card-title class="d-flex align-center bg-primary pa-4">
      <v-icon icon="mdi-shield-lock" size="28" class="mr-3"></v-icon>
      <span class="text-h6">Two-Factor Authentication</span>
    </v-card-title>

    <v-card-text class="pa-6">
      <!-- 2FA Status -->
      <v-alert
        v-if="twoFactorEnabled"
        type="success"
        variant="tonal"
        class="mb-4"
        icon="mdi-shield-check"
      >
        Two-factor authentication is currently <strong>enabled</strong>
      </v-alert>
      <v-alert
        v-else
        type="warning"
        variant="tonal"
        class="mb-4"
        icon="mdi-shield-alert"
      >
        Two-factor authentication is currently <strong>disabled</strong>
      </v-alert>

      <div v-if="!twoFactorEnabled">
        <p class="text-body-1 mb-4">
          Add an extra layer of security to your account by enabling two-factor authentication.
          You'll need to enter a code from your authenticator app in addition to your password when logging in.
        </p>

        <v-list lines="two" class="mb-4">
          <v-list-item>
            <template v-slot:prepend>
              <v-avatar color="primary" size="40">
                <v-icon icon="mdi-numeric-1"></v-icon>
              </v-avatar>
            </template>
            <v-list-item-title>Install an authenticator app</v-list-item-title>
            <v-list-item-subtitle>
              Download Google Authenticator, Authy, or similar app on your mobile device
            </v-list-item-subtitle>
          </v-list-item>

          <v-list-item>
            <template v-slot:prepend>
              <v-avatar color="primary" size="40">
                <v-icon icon="mdi-numeric-2"></v-icon>
              </v-avatar>
            </template>
            <v-list-item-title>Scan the QR code</v-list-item-title>
            <v-list-item-subtitle>
              Use your authenticator app to scan the QR code we'll provide
            </v-list-item-subtitle>
          </v-list-item>

          <v-list-item>
            <template v-slot:prepend>
              <v-avatar color="primary" size="40">
                <v-icon icon="mdi-numeric-3"></v-icon>
              </v-avatar>
            </template>
            <v-list-item-title>Enter the verification code</v-list-item-title>
            <v-list-item-subtitle>
              Enter the 6-digit code from your app to confirm setup
            </v-list-item-subtitle>
          </v-list-item>
        </v-list>

        <v-btn
          color="primary"
          size="large"
          prepend-icon="mdi-shield-lock"
          @click="showPasswordDialog = true"
          block
        >
          Enable Two-Factor Authentication
        </v-btn>
      </div>

      <div v-else>
        <p class="text-body-1 mb-4">
          Your account is protected with two-factor authentication. You can disable it or regenerate your recovery codes below.
        </p>

        <div class="d-flex gap-3">
          <v-btn
            color="warning"
            variant="tonal"
            prepend-icon="mdi-key-variant"
            @click="showRegenerateDialog = true"
          >
            Regenerate Recovery Codes
          </v-btn>
          <v-btn
            color="error"
            variant="tonal"
            prepend-icon="mdi-shield-off"
            @click="showDisableDialog = true"
          >
            Disable 2FA
          </v-btn>
        </div>
      </div>
    </v-card-text>

    <!-- Password Confirmation Dialog for Enabling -->
    <v-dialog v-model="showPasswordDialog" max-width="500" persistent>
      <v-card>
        <v-card-title class="bg-primary">Confirm Your Password</v-card-title>
        <v-card-text class="pa-6">
          <p class="mb-4">Please enter your password to enable two-factor authentication.</p>
          <v-text-field
            v-model="password"
            label="Password"
            type="password"
            variant="outlined"
            :error-messages="passwordError"
            @keyup.enter="enableTwoFactor"
          ></v-text-field>
        </v-card-text>
        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="closePasswordDialog">Cancel</v-btn>
          <v-btn color="primary" variant="elevated" @click="enableTwoFactor" :loading="loading">
            Continue
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- QR Code Dialog -->
    <v-dialog v-model="showQrDialog" max-width="550" persistent>
      <v-card>
        <v-card-title class="bg-primary">Scan QR Code</v-card-title>
        <v-card-text class="pa-6">
          <p class="mb-4">
            Scan this QR code with your authenticator app (Google Authenticator, Authy, etc.)
          </p>
          
          <div class="qr-code-container mb-4" v-html="qrCode"></div>

          <v-alert type="info" variant="tonal" class="mb-4">
            <strong>Manual Entry:</strong> {{ secret }}
          </v-alert>

          <p class="mb-2"><strong>Enter the 6-digit code from your app:</strong></p>
          <v-text-field
            v-model="verificationCode"
            label="Verification Code"
            variant="outlined"
            maxlength="6"
            :error-messages="verificationError"
            @keyup.enter="confirmTwoFactor"
          ></v-text-field>
        </v-card-text>
        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="closeQrDialog">Cancel</v-btn>
          <v-btn color="primary" variant="elevated" @click="confirmTwoFactor" :loading="loading">
            Verify & Enable
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Recovery Codes Dialog -->
    <v-dialog v-model="showRecoveryDialog" max-width="600" persistent>
      <v-card>
        <v-card-title class="bg-success">Recovery Codes Generated</v-card-title>
        <v-card-text class="pa-6">
          <v-alert type="warning" variant="tonal" class="mb-4">
            <strong>Important!</strong> Save these recovery codes in a safe place. 
            Each code can only be used once if you lose access to your authenticator app.
          </v-alert>

          <v-card variant="outlined" class="pa-4 mb-4">
            <div class="recovery-codes">
              <div v-for="(code, index) in recoveryCodes" :key="index" class="code-item">
                <v-chip color="primary" variant="outlined" size="small">
                  {{ code }}
                </v-chip>
              </div>
            </div>
          </v-card>

          <v-btn
            color="primary"
            variant="tonal"
            prepend-icon="mdi-content-copy"
            @click="copyRecoveryCodes"
            block
            class="mb-2"
          >
            Copy All Codes
          </v-btn>
        </v-card-text>
        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn color="success" variant="elevated" @click="closeRecoveryDialog">
            I've Saved My Codes
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Regenerate Recovery Codes Dialog -->
    <v-dialog v-model="showRegenerateDialog" max-width="500" persistent>
      <v-card>
        <v-card-title class="bg-warning">Regenerate Recovery Codes</v-card-title>
        <v-card-text class="pa-6">
          <p class="mb-4">Enter your password to regenerate your recovery codes. Your old codes will no longer work.</p>
          <v-text-field
            v-model="password"
            label="Password"
            type="password"
            variant="outlined"
            :error-messages="passwordError"
            @keyup.enter="regenerateRecoveryCodes"
          ></v-text-field>
        </v-card-text>
        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="showRegenerateDialog = false">Cancel</v-btn>
          <v-btn color="warning" variant="elevated" @click="regenerateRecoveryCodes" :loading="loading">
            Regenerate
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Disable 2FA Dialog -->
    <v-dialog v-model="showDisableDialog" max-width="500" persistent>
      <v-card>
        <v-card-title class="bg-error">Disable Two-Factor Authentication</v-card-title>
        <v-card-text class="pa-6">
          <v-alert type="error" variant="tonal" class="mb-4">
            Warning! This will make your account less secure.
          </v-alert>
          <p class="mb-4">Enter your password to disable two-factor authentication.</p>
          <v-text-field
            v-model="password"
            label="Password"
            type="password"
            variant="outlined"
            :error-messages="passwordError"
            @keyup.enter="disableTwoFactor"
          ></v-text-field>
        </v-card-text>
        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="showDisableDialog = false">Cancel</v-btn>
          <v-btn color="error" variant="elevated" @click="disableTwoFactor" :loading="loading">
            Disable 2FA
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-card>
</template>

<script setup>
import { ref, onMounted } from "vue";
import api from "@/services/api";
import { useToast } from "vue-toastification";

const toast = useToast();

const twoFactorEnabled = ref(false);
const loading = ref(false);

// Dialogs
const showPasswordDialog = ref(false);
const showQrDialog = ref(false);
const showRecoveryDialog = ref(false);
const showRegenerateDialog = ref(false);
const showDisableDialog = ref(false);

// Form data
const password = ref("");
const passwordError = ref("");
const verificationCode = ref("");
const verificationError = ref("");
const qrCode = ref("");
const secret = ref("");
const recoveryCodes = ref([]);

onMounted(async () => {
  await checkTwoFactorStatus();
});

async function checkTwoFactorStatus() {
  try {
    const response = await api.get("/two-factor/status");
    twoFactorEnabled.value = response.data.enabled;
  } catch (error) {
    console.error("Error checking 2FA status:", error);
  }
}

async function enableTwoFactor() {
  if (!password.value) {
    passwordError.value = "Password is required";
    return;
  }

  loading.value = true;
  passwordError.value = "";

  try {
    const response = await api.post("/two-factor/enable", {
      password: password.value,
    });

    qrCode.value = response.data.qr_code;
    secret.value = response.data.secret;
    
    showPasswordDialog.value = false;
    showQrDialog.value = true;
    password.value = "";
  } catch (error) {
    if (error.response?.status === 401) {
      passwordError.value = "Invalid password";
    } else {
      toast.error("Failed to enable 2FA");
    }
  } finally {
    loading.value = false;
  }
}

async function confirmTwoFactor() {
  if (!verificationCode.value || verificationCode.value.length !== 6) {
    verificationError.value = "Please enter a valid 6-digit code";
    return;
  }

  loading.value = true;
  verificationError.value = "";

  try {
    const response = await api.post("/two-factor/confirm", {
      code: verificationCode.value,
    });

    recoveryCodes.value = response.data.recovery_codes;
    
    showQrDialog.value = false;
    showRecoveryDialog.value = true;
    verificationCode.value = "";
    
    twoFactorEnabled.value = true;
    toast.success("Two-factor authentication enabled successfully!");
  } catch (error) {
    if (error.response?.status === 401) {
      verificationError.value = "Invalid verification code";
    } else {
      toast.error("Failed to confirm 2FA");
    }
  } finally {
    loading.value = false;
  }
}

async function disableTwoFactor() {
  if (!password.value) {
    passwordError.value = "Password is required";
    return;
  }

  loading.value = true;
  passwordError.value = "";

  try {
    await api.delete("/two-factor/disable", {
      data: { password: password.value },
    });

    twoFactorEnabled.value = false;
    showDisableDialog.value = false;
    password.value = "";
    toast.success("Two-factor authentication disabled");
  } catch (error) {
    if (error.response?.status === 401) {
      passwordError.value = "Invalid password";
    } else {
      toast.error("Failed to disable 2FA");
    }
  } finally {
    loading.value = false;
  }
}

async function regenerateRecoveryCodes() {
  if (!password.value) {
    passwordError.value = "Password is required";
    return;
  }

  loading.value = true;
  passwordError.value = "";

  try {
    const response = await api.post("/two-factor/recovery-codes", {
      password: password.value,
    });

    recoveryCodes.value = response.data.recovery_codes;
    
    showRegenerateDialog.value = false;
    showRecoveryDialog.value = true;
    password.value = "";
    toast.success("Recovery codes regenerated");
  } catch (error) {
    if (error.response?.status === 401) {
      passwordError.value = "Invalid password";
    } else {
      toast.error("Failed to regenerate codes");
    }
  } finally {
    loading.value = false;
  }
}

function copyRecoveryCodes() {
  const codesText = recoveryCodes.value.join("\n");
  navigator.clipboard.writeText(codesText);
  toast.success("Recovery codes copied to clipboard");
}

function closePasswordDialog() {
  showPasswordDialog.value = false;
  password.value = "";
  passwordError.value = "";
}

function closeQrDialog() {
  showQrDialog.value = false;
  verificationCode.value = "";
  verificationError.value = "";
  qrCode.value = "";
  secret.value = "";
}

function closeRecoveryDialog() {
  showRecoveryDialog.value = false;
  recoveryCodes.value = [];
}
</script>

<style scoped>
.qr-code-container {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 20px;
  background: white;
  border-radius: 8px;
  border: 2px solid #e0e0e0;
}

.qr-code-container :deep(svg) {
  max-width: 250px;
  height: auto;
}

.recovery-codes {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.code-item {
  font-family: 'Courier New', monospace;
  font-size: 14px;
}
</style>
