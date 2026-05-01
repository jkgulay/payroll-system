<template>
  <div class="two-factor-card">
    <!-- Modern Card Header -->
    <div class="card-header">
      <div class="card-header-left">
        <div class="card-header-icon">
          <v-icon icon="mdi-shield-lock" size="18"></v-icon>
        </div>
        <h3 class="card-header-title">Two-Factor Authentication</h3>
      </div>
    </div>

    <div class="card-content">
      <!-- 2FA Status -->
      <v-alert
        v-if="twoFactorEnabled"
        variant="tonal"
        class="status-alert status-enabled mb-4"
      >
        <div class="d-flex align-center">
          <v-icon
            icon="mdi-shield-check"
            size="20"
            class="mr-2"
            color="#4CAF50"
          ></v-icon>
          <div>
            <div class="font-weight-medium text-body-2">
              Two-factor authentication is
              <span class="text-success">enabled</span>
            </div>
            <div class="text-caption mt-1" style="color: rgba(0, 31, 61, 0.6)">
              Your account has enhanced security protection
            </div>
          </div>
        </div>
      </v-alert>
      <v-alert v-else variant="tonal" class="status-alert status-disabled mb-4">
        <div class="d-flex align-center">
          <v-icon
            icon="mdi-shield-alert"
            size="20"
            class="mr-2"
            color="#FF9800"
          ></v-icon>
          <div>
            <div class="font-weight-medium text-body-2">
              Two-factor authentication is
              <span class="text-warning">disabled</span>
            </div>
            <div class="text-caption mt-1" style="color: rgba(0, 31, 61, 0.6)">
              Enable 2FA to better protect your account
            </div>
          </div>
        </div>
      </v-alert>

      <div v-if="!twoFactorEnabled">
        <p class="description-text mb-4">
          Add an extra layer of security to your account by enabling two-factor
          authentication. You'll need to enter a code from your authenticator
          app in addition to your password when logging in.
        </p>

        <div class="setup-steps mb-4">
          <div class="step-item">
            <div class="step-badge">
              <v-icon icon="mdi-numeric-1" size="16" color="white"></v-icon>
            </div>
            <div class="step-content">
              <div class="step-title">Install an authenticator app</div>
              <div class="step-subtitle">
                Download Google Authenticator, Authy, or similar app on your
                mobile device
              </div>
            </div>
          </div>

          <div class="step-item">
            <div class="step-badge">
              <v-icon icon="mdi-numeric-2" size="16" color="white"></v-icon>
            </div>
            <div class="step-content">
              <div class="step-title">Scan the QR code</div>
              <div class="step-subtitle">
                Use your authenticator app to scan the QR code we'll provide
              </div>
            </div>
          </div>

          <div class="step-item">
            <div class="step-badge">
              <v-icon icon="mdi-numeric-3" size="16" color="white"></v-icon>
            </div>
            <div class="step-content">
              <div class="step-title">Enter the verification code</div>
              <div class="step-subtitle">
                Enter the 6-digit code from your app to confirm setup
              </div>
            </div>
          </div>
        </div>

        <button class="enable-btn" @click="showPasswordDialog = true">
          <v-icon icon="mdi-shield-lock" size="18"></v-icon>
          <span>Enable Two-Factor Authentication</span>
        </button>
      </div>

      <div v-else>
        <p class="description-text mb-4">
          Your account is protected with two-factor authentication. You can
          disable it or regenerate your recovery codes below.
        </p>

        <div class="action-buttons">
          <button
            class="action-btn regenerate-btn"
            @click="showRegenerateDialog = true"
          >
            <v-icon icon="mdi-key-variant" size="18"></v-icon>
            <span>Regenerate Recovery Codes</span>
          </button>
          <button
            class="action-btn disable-btn"
            @click="showDisableDialog = true"
          >
            <v-icon icon="mdi-shield-off" size="18"></v-icon>
            <span>Disable 2FA</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Password Confirmation Dialog for Enabling -->
    <v-dialog v-model="showPasswordDialog" max-width="550" persistent>
      <v-card class="dialog-card">
        <div class="dialog-header">
          <div class="header-badge-dialog">
            <v-icon icon="mdi-lock-check" size="24" color="white"></v-icon>
          </div>
          <div class="dialog-header-text">
            <h3 class="dialog-title">Confirm Your Password</h3>
            <p class="dialog-subtitle">Verify your identity to enable 2FA</p>
          </div>
        </div>
        <v-card-text class="pa-6">
          <p class="mb-5" style="color: #001f3d; line-height: 1.6">
            Please enter your password to enable two-factor authentication.
          </p>
          <v-text-field
            v-model="password"
            label="Password"
            type="password"
            variant="outlined"
            color="#001F3D"
            :error-messages="passwordError"
            @keyup.enter="enableTwoFactor"
          ></v-text-field>
        </v-card-text>
        <v-card-actions class="pa-6 pt-0">
          <v-spacer></v-spacer>
          <v-btn class="cancel-btn" variant="text" @click="closePasswordDialog">
            Cancel
          </v-btn>
          <v-btn
            class="confirm-btn"
            @click="enableTwoFactor"
            :loading="loading"
          >
            Continue
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- QR Code Dialog -->
    <v-dialog v-model="showQrDialog" max-width="600" persistent>
      <v-card class="dialog-card">
        <div class="dialog-header">
          <div class="header-badge-dialog">
            <v-icon icon="mdi-qrcode-scan" size="24" color="white"></v-icon>
          </div>
          <div class="dialog-header-text">
            <h3 class="dialog-title">Scan QR Code</h3>
            <p class="dialog-subtitle">Set up your authenticator app</p>
          </div>
        </div>
        <v-card-text class="pa-6">
          <p class="mb-5" style="color: #001f3d; line-height: 1.6">
            Scan this QR code with your authenticator app (Google Authenticator,
            Authy, etc.)
          </p>

          <div class="qr-code-container mb-5" v-html="qrCode"></div>

          <v-alert
            variant="tonal"
            class="manual-entry-alert mb-5"
            icon="mdi-information"
          >
            <div>
              <div class="font-weight-bold mb-1">Manual Entry:</div>
              <div class="secret-code">{{ secret }}</div>
            </div>
          </v-alert>

          <p class="mb-3 font-weight-bold" style="color: #001f3d">
            Enter the 6-digit code from your app:
          </p>
          <v-text-field
            v-model="verificationCode"
            label="Verification Code"
            variant="outlined"
            color="#001F3D"
            maxlength="6"
            :error-messages="verificationError"
            @keyup.enter="confirmTwoFactor"
          ></v-text-field>
        </v-card-text>
        <v-card-actions class="pa-6 pt-0">
          <v-spacer></v-spacer>
          <v-btn class="cancel-btn" variant="text" @click="closeQrDialog">
            Cancel
          </v-btn>
          <v-btn
            class="confirm-btn"
            @click="confirmTwoFactor"
            :loading="loading"
          >
            Verify & Enable
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Recovery Codes Dialog -->
    <v-dialog v-model="showRecoveryDialog" max-width="650" persistent>
      <v-card class="dialog-card">
        <div class="dialog-header success-header">
          <div class="header-badge-dialog success-badge">
            <v-icon icon="mdi-check-circle" size="24" color="white"></v-icon>
          </div>
          <div class="dialog-header-text">
            <h3 class="dialog-title">Recovery Codes Generated</h3>
            <p class="dialog-subtitle">Save these codes in a secure location</p>
          </div>
        </div>
        <v-card-text class="pa-6">
          <v-alert variant="tonal" class="warning-alert mb-5" icon="mdi-alert">
            <div>
              <div class="font-weight-bold mb-1">Important!</div>
              <div class="text-body-2">
                Save these recovery codes in a safe place. Each code can only be
                used once if you lose access to your authenticator app.
              </div>
            </div>
          </v-alert>

          <v-card class="recovery-codes-card mb-5" elevation="0">
            <div class="recovery-codes">
              <div
                v-for="(code, index) in recoveryCodes"
                :key="index"
                class="code-item"
              >
                <div class="code-chip">
                  {{ code }}
                </div>
              </div>
            </div>
          </v-card>

          <v-btn
            class="copy-btn"
            prepend-icon="mdi-content-copy"
            @click="copyRecoveryCodes"
            block
            size="large"
          >
            <v-icon icon="mdi-content-copy" class="mr-2"></v-icon>
            Copy All Codes
          </v-btn>
        </v-card-text>
        <v-card-actions class="pa-6 pt-0">
          <v-spacer></v-spacer>
          <v-btn class="success-btn" @click="closeRecoveryDialog" size="large">
            <v-icon icon="mdi-check" class="mr-2"></v-icon>
            I've Saved My Codes
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Regenerate Recovery Codes Dialog -->
    <v-dialog v-model="showRegenerateDialog" max-width="550" persistent>
      <v-card class="dialog-card">
        <div class="dialog-header warning-header">
          <div class="header-badge-dialog warning-badge">
            <v-icon icon="mdi-key-variant" size="24" color="white"></v-icon>
          </div>
          <div class="dialog-header-text">
            <h3 class="dialog-title">Regenerate Recovery Codes</h3>
            <p class="dialog-subtitle">Get new backup codes for your account</p>
          </div>
        </div>
        <v-card-text class="pa-6">
          <p class="mb-5" style="color: #001f3d; line-height: 1.6">
            Enter your password to regenerate your recovery codes. Your old
            codes will no longer work.
          </p>
          <v-text-field
            v-model="password"
            label="Password"
            type="password"
            variant="outlined"
            color="#001F3D"
            :error-messages="passwordError"
            @keyup.enter="regenerateRecoveryCodes"
          ></v-text-field>
        </v-card-text>
        <v-card-actions class="pa-6 pt-0">
          <v-spacer></v-spacer>
          <v-btn
            class="cancel-btn"
            variant="text"
            @click="showRegenerateDialog = false"
          >
            Cancel
          </v-btn>
          <v-btn
            class="warning-action-btn"
            @click="regenerateRecoveryCodes"
            :loading="loading"
          >
            <v-icon icon="mdi-refresh" class="mr-2"></v-icon>
            Regenerate
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Disable 2FA Dialog -->
    <v-dialog v-model="showDisableDialog" max-width="550" persistent>
      <v-card class="dialog-card">
        <div class="dialog-header danger-header">
          <div class="header-badge-dialog danger-badge">
            <v-icon icon="mdi-shield-off" size="24" color="white"></v-icon>
          </div>
          <div class="dialog-header-text">
            <h3 class="dialog-title">Disable Two-Factor Authentication</h3>
            <p class="dialog-subtitle">
              This will reduce your account security
            </p>
          </div>
        </div>
        <v-card-text class="pa-6">
          <v-alert
            variant="tonal"
            class="danger-alert mb-5"
            icon="mdi-alert-octagon"
          >
            <div>
              <div class="font-weight-bold mb-1">Warning!</div>
              <div class="text-body-2">
                This will make your account less secure.
              </div>
            </div>
          </v-alert>
          <p class="mb-5" style="color: #001f3d; line-height: 1.6">
            Enter your password to disable two-factor authentication.
          </p>
          <v-text-field
            v-model="password"
            label="Password"
            type="password"
            variant="outlined"
            color="#001F3D"
            :error-messages="passwordError"
            @keyup.enter="disableTwoFactor"
          ></v-text-field>
        </v-card-text>
        <v-card-actions class="pa-6 pt-0">
          <v-spacer></v-spacer>
          <v-btn
            class="cancel-btn"
            variant="text"
            @click="showDisableDialog = false"
          >
            Cancel
          </v-btn>
          <v-btn
            class="danger-action-btn"
            @click="disableTwoFactor"
            :loading="loading"
          >
            <v-icon icon="mdi-shield-off" class="mr-2"></v-icon>
            Disable 2FA
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import api from "@/services/api";
import { useToast } from "vue-toastification";
import { devLog } from "@/utils/devLog";

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
    devLog.error("Error checking 2FA status:", error);
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
    devLog.error("2FA Enable Error:", error);
    if (error.response?.status === 401) {
      passwordError.value = "Invalid password";
    } else if (error.response?.status === 500) {
      const errorMsg =
        error.response?.data?.error ||
        error.response?.data?.message ||
        "Server error occurred";
      passwordError.value = errorMsg;
      toast.error(errorMsg);
    } else {
      passwordError.value = "Failed to enable 2FA. Please try again.";
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
.two-factor-card {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  overflow: hidden;
}

/* Card Header - Match ProfileView cards */
.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
  padding: 20px 24px;
  background: linear-gradient(
    135deg,
    rgba(0, 31, 61, 0.02) 0%,
    rgba(237, 152, 95, 0.02) 100%
  );
  border-bottom: 1px solid rgba(0, 31, 61, 0.08);
}

.card-header-left {
  display: flex;
  align-items: center;
  gap: 12px;
  flex: 1;
}

.card-header-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.25);

  .v-icon {
    color: #ffffff !important;
  }
}

.card-header-title {
  font-size: 16px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
  letter-spacing: -0.3px;
}

/* Card Content */
.card-content {
  padding: 24px;
}

/* Description Text */
.description-text {
  color: rgba(0, 31, 61, 0.8);
  font-size: 14px;
  line-height: 1.6;
  margin: 0;
}

/* Status Alerts */
.status-alert {
  border-radius: 8px;
  border: none;
  padding: 12px 14px;
}

.status-enabled {
  background: rgba(76, 175, 80, 0.06) !important;
  border: 1px solid rgba(76, 175, 80, 0.2) !important;
}

.status-disabled {
  background: rgba(255, 152, 0, 0.06) !important;
  border: 1px solid rgba(255, 152, 0, 0.2) !important;
}

/* Setup Steps */
.setup-steps {
  background: #f8f9fa;
  border-radius: 10px;
  border: 1px solid #e8e8e8;
  padding: 4px;
}

.step-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px;
  border-radius: 6px;
  transition: background 0.2s ease;
}

.step-item:hover {
  background: rgba(237, 152, 95, 0.04);
}

.step-badge {
  width: 32px;
  height: 32px;
  border-radius: 6px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.step-content {
  flex: 1;
}

.step-title {
  color: #001f3d;
  font-size: 14px;
  font-weight: 600;
  margin-bottom: 4px;
  line-height: 1.3;
}

.step-subtitle {
  color: rgba(0, 31, 61, 0.65);
  font-size: 13px;
  line-height: 1.4;
}

/* Enable Button */
.enable-btn {
  max-width: 400px;
  margin: 0 auto;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: #ffffff;
  font-weight: 600;
  font-size: 14px;
  border-radius: 10px;
  padding: 12px 20px;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  text-transform: none;
  letter-spacing: 0.2px;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);
  transition: all 0.3s ease;
}

.enable-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(237, 152, 95, 0.4);
}

.enable-btn:active {
  transform: translateY(0);
}

/* Action Buttons */
.action-buttons {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.action-btn {
  flex: 1;
  min-width: 180px;
  font-weight: 600;
  font-size: 14px;
  border-radius: 8px;
  padding: 11px 18px;
  border: 2px solid;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  text-transform: none;
  transition: all 0.3s ease;
}

.regenerate-btn {
  background: white;
  color: #001f3d;
  border-color: #ed985f;
}

.regenerate-btn:hover {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  transform: translateY(-2px);
  box-shadow: 0 4px 10px rgba(237, 152, 95, 0.3);
}

.disable-btn {
  background: #fff5f5;
  color: #dc2626;
  border-color: #dc2626;
}

.disable-btn:hover {
  background: #dc2626;
  color: white;
  transform: translateY(-2px);
  box-shadow: 0 4px 10px rgba(220, 38, 38, 0.3);
}

/* Dialog Styles */
.dialog-card {
  border-radius: 16px;
  overflow: hidden;
}

.dialog-header {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  padding: 24px;
  display: flex;
  align-items: center;
  gap: 16px;
}

.dialog-header.success-header {
  background: linear-gradient(135deg, #4caf50 0%, #66bb6a 100%);
}

.dialog-header.warning-header {
  background: linear-gradient(135deg, #ff9800 0%, #ffb74d 100%);
}

.dialog-header.danger-header {
  background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
}

.header-badge-dialog {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: rgba(0, 31, 61, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.header-badge-dialog.success-badge,
.header-badge-dialog.warning-badge,
.header-badge-dialog.danger-badge {
  background: rgba(255, 255, 255, 0.2);
}

.dialog-header-text {
  flex: 1;
}

.dialog-title {
  color: #001f3d;
  font-size: 22px;
  font-weight: 600;
  margin: 0;
  line-height: 1.2;
}

.dialog-subtitle {
  color: rgba(0, 31, 61, 0.7);
  font-size: 14px;
  margin: 6px 0 0 0;
  line-height: 1.4;
}

/* QR Code Container */
.qr-code-container {
  display: flex;
  justify-content: center;
  align-items: center;
  padding: 24px;
  background: white;
  border-radius: 12px;
  border: 2px solid #e6e6e6;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

.qr-code-container :deep(svg) {
  max-width: 250px;
  height: auto;
}

/* Manual Entry Alert */
.manual-entry-alert {
  background: rgba(237, 152, 95, 0.08) !important;
  border-radius: 10px;
  border: 1px solid #ed985f !important;
}

.secret-code {
  font-family: "Courier New", monospace;
  font-size: 15px;
  color: #001f3d;
  background: rgba(0, 31, 61, 0.05);
  padding: 8px 12px;
  border-radius: 6px;
  margin-top: 4px;
  letter-spacing: 1px;
}

/* Warning Alert */
.warning-alert {
  background: rgba(255, 152, 0, 0.08) !important;
  border-radius: 10px;
  border: 1px solid #ff9800 !important;
}

/* Danger Alert */
.danger-alert {
  background: rgba(220, 38, 38, 0.08) !important;
  border-radius: 10px;
  border: 1px solid #dc2626 !important;
}

/* Recovery Codes Card */
.recovery-codes-card {
  background: #f8f9fa;
  border: 1px solid #e6e6e6;
  border-radius: 12px;
  padding: 20px;
}

.recovery-codes {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
}

.code-chip {
  font-family: "Courier New", monospace;
  font-size: 14px;
  color: #001f3d;
  background: white;
  padding: 12px 16px;
  border-radius: 8px;
  border: 1px solid #e6e6e6;
  text-align: center;
  font-weight: 600;
  letter-spacing: 1px;
  transition: all 0.2s ease;
}

.code-chip:hover {
  border-color: #ed985f;
  background: rgba(237, 152, 95, 0.05);
  transform: translateY(-1px);
}

/* Dialog Buttons */
.cancel-btn {
  color: rgba(0, 31, 61, 0.7);
  font-weight: 600;
  text-transform: none;
  border-radius: 8px;
  padding: 8px 20px;
  background: transparent;
  border: none;
  cursor: pointer;
  transition: all 0.2s ease;
}

.cancel-btn:hover {
  color: #001f3d;
  background: rgba(0, 31, 61, 0.05);
}

.confirm-btn,
.success-btn,
.warning-action-btn,
.danger-action-btn,
.copy-btn {
  font-weight: 600;
  border-radius: 10px;
  text-transform: none;
  padding: 10px 24px;
  border: none;
  cursor: pointer;
  display: flex;
  align-items: center;
  gap: 8px;
  transition: all 0.3s ease;
}

.confirm-btn {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: #001f3d;
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);
}

.confirm-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(237, 152, 95, 0.4);
}

.success-btn {
  background: linear-gradient(135deg, #4caf50 0%, #66bb6a 100%);
  color: white;
  box-shadow: 0 4px 12px rgba(76, 175, 80, 0.3);
}

.success-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(76, 175, 80, 0.4);
}

.warning-action-btn {
  background: linear-gradient(135deg, #ff9800 0%, #ffb74d 100%);
  color: white;
  box-shadow: 0 4px 12px rgba(255, 152, 0, 0.3);
}

.warning-action-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(255, 152, 0, 0.4);
}

.danger-action-btn {
  background: linear-gradient(135deg, #dc2626 0%, #ef4444 100%);
  color: white;
  box-shadow: 0 4px 12px rgba(220, 38, 38, 0.3);
}

.danger-action-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(220, 38, 38, 0.4);
}

.copy-btn {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: #001f3d;
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);
}

.copy-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 16px rgba(237, 152, 95, 0.4);
}
</style>
