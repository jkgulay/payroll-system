<template>
  <div class="profile-page">
    <!-- Modern Page Header -->
    <div class="page-header">
      <div class="page-title-section">
        <div class="page-icon-badge">
          <v-icon size="20">mdi-account-cog</v-icon>
        </div>
        <div>
          <h1 class="page-title">My Profile</h1>
          <p class="page-subtitle">
            Manage your personal information and account settings
          </p>
        </div>
      </div>
    </div>

    <v-row>
      <!-- Left Column: Profile Picture -->
      <v-col cols="12" md="4">
        <div class="profile-picture-card">
          <div class="card-header">
            <div class="card-header-left">
              <div class="card-header-icon">
                <v-icon size="18">mdi-camera-account</v-icon>
              </div>
              <h3 class="card-header-title">Profile Picture</h3>
            </div>
          </div>

          <div class="profile-avatar-section">
            <div class="avatar-wrapper">
              <v-avatar size="160" class="profile-avatar">
                <v-img
                  v-if="avatarPreview || user?.avatar"
                  :src="avatarPreview || getAvatarUrl(user?.avatar)"
                  cover
                ></v-img>
                <div v-else class="avatar-placeholder">
                  <v-icon size="80" color="white">mdi-account</v-icon>
                </div>
              </v-avatar>
            </div>

            <div class="image-guidelines">
              <div class="guidelines-title">
                <v-icon size="16">mdi-information</v-icon>
                <span>Image Guidelines</span>
              </div>
              <ul class="guidelines-list">
                <li>Maximum size: 2MB</li>
                <li>Format: JPG, PNG, GIF</li>
                <li>Recommended: Square image</li>
              </ul>
            </div>

            <v-file-input
              v-model="avatarFile"
              accept="image/*"
              label="Choose picture"
              prepend-icon="mdi-camera"
              variant="outlined"
              density="compact"
              hide-details
              @change="handleAvatarChange"
              :disabled="uploadingAvatar"
              class="mb-3"
            ></v-file-input>

            <div class="avatar-actions">
              <button
                class="avatar-btn avatar-btn-primary"
                :disabled="
                  !avatarFile || avatarFile.length === 0 || uploadingAvatar
                "
                @click="uploadAvatar"
              >
                <v-icon v-if="!uploadingAvatar" size="18">mdi-upload</v-icon>
                <v-progress-circular
                  v-else
                  size="18"
                  width="2"
                  indeterminate
                  color="white"
                ></v-progress-circular>
                <span>{{
                  uploadingAvatar ? "Uploading..." : "Upload Picture"
                }}</span>
              </button>

              <button
                v-if="user?.avatar"
                class="avatar-btn avatar-btn-danger"
                @click="removeAvatar"
                :disabled="uploadingAvatar"
              >
                <v-icon size="18">mdi-delete</v-icon>
                <span>Remove</span>
              </button>
            </div>
          </div>
        </div>
      </v-col>

      <!-- Right Column: Profile Info & Security -->
      <v-col cols="12" md="8">
        <!-- Profile Information Card -->
        <div class="profile-info-card mb-4">
          <div class="card-header">
            <div class="card-header-left">
              <div class="card-header-icon">
                <v-icon size="18">mdi-account</v-icon>
              </div>
              <h3 class="card-header-title">Profile Information</h3>
            </div>
            <button
              v-if="!editingProfile"
              class="edit-btn"
              @click="startEditProfile"
            >
              <v-icon size="18">mdi-pencil</v-icon>
            </button>
          </div>

          <!-- Edit Mode -->
          <div v-if="editingProfile" class="profile-edit-form">
            <v-form
              ref="profileFormRef"
              @submit.prevent="updateProfileInfo"
              validate-on="submit lazy"
            >
              <v-row dense>
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="profileForm.name"
                    label="Full Name"
                    prepend-inner-icon="mdi-account-circle"
                    :rules="[rules.required]"
                    variant="outlined"
                    density="compact"
                    hide-details="auto"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="profileForm.username"
                    label="Username"
                    prepend-inner-icon="mdi-account-badge"
                    :rules="[rules.required]"
                    variant="outlined"
                    density="compact"
                    hide-details="auto"
                  ></v-text-field>
                </v-col>
                <v-col cols="12">
                  <v-text-field
                    v-model="profileForm.email"
                    label="Email"
                    prepend-inner-icon="mdi-email"
                    :rules="[rules.required, rules.email]"
                    variant="outlined"
                    density="compact"
                    hide-details="auto"
                  ></v-text-field>
                </v-col>
              </v-row>

              <v-alert
                v-if="profileError"
                type="error"
                density="compact"
                class="mt-3 mb-2"
                dismissible
                @click:close="profileError = ''"
              >
                {{ profileError }}
              </v-alert>

              <v-alert
                v-if="profileSuccess"
                type="success"
                density="compact"
                class="mt-3 mb-2"
                dismissible
                @click:close="profileSuccess = ''"
              >
                {{ profileSuccess }}
              </v-alert>

              <div class="form-actions">
                <button
                  type="submit"
                  class="form-btn form-btn-primary"
                  :disabled="updatingProfile"
                >
                  <v-icon v-if="!updatingProfile" size="18">mdi-check</v-icon>
                  <v-progress-circular
                    v-else
                    size="18"
                    width="2"
                    indeterminate
                    color="white"
                  ></v-progress-circular>
                  <span>{{
                    updatingProfile ? "Saving..." : "Save Changes"
                  }}</span>
                </button>
                <button
                  type="button"
                  class="form-btn form-btn-secondary"
                  @click="cancelEditProfile"
                  :disabled="updatingProfile"
                >
                  <v-icon size="18">mdi-close</v-icon>
                  <span>Cancel</span>
                </button>
              </div>
            </v-form>
          </div>

          <!-- View Mode -->
          <div v-else class="profile-info-view">
            <div class="info-item">
              <div class="info-icon">
                <v-icon size="20">mdi-account-circle</v-icon>
              </div>
              <div class="info-content">
                <div class="info-label">Full Name</div>
                <div class="info-value">{{ user?.name || "N/A" }}</div>
              </div>
            </div>
            <div class="info-item">
              <div class="info-icon">
                <v-icon size="20">mdi-account-badge</v-icon>
              </div>
              <div class="info-content">
                <div class="info-label">Username</div>
                <div class="info-value">{{ user?.username }}</div>
              </div>
            </div>
            <div class="info-item">
              <div class="info-icon">
                <v-icon size="20">mdi-email</v-icon>
              </div>
              <div class="info-content">
                <div class="info-label">Email</div>
                <div class="info-value">{{ user?.email }}</div>
              </div>
            </div>
            <div class="info-item">
              <div class="info-icon">
                <v-icon size="20">mdi-shield-account</v-icon>
              </div>
              <div class="info-content">
                <div class="info-label">Role</div>
                <div class="info-value text-capitalize">{{ user?.role }}</div>
              </div>
            </div>
            <div v-if="user?.last_login_at" class="info-item">
              <div class="info-icon">
                <v-icon size="20">mdi-clock</v-icon>
              </div>
              <div class="info-content">
                <div class="info-label">Last Login</div>
                <div class="info-value">
                  {{ formatDate(user.last_login_at) }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Change Password Card -->
        <div class="profile-password-card mb-4">
          <div class="card-header">
            <div class="card-header-left">
              <div class="card-header-icon">
                <v-icon size="18">mdi-lock-reset</v-icon>
              </div>
              <h3 class="card-header-title">Change Password</h3>
            </div>
          </div>

          <div class="password-form-wrapper">
            <v-form
              @submit.prevent="changePassword"
              ref="passwordFormRef"
              validate-on="submit lazy"
            >
              <v-row dense>
                <v-col cols="12" md="4">
                  <v-text-field
                    v-model="passwordForm.current_password"
                    label="Current Password"
                    :type="showCurrentPassword ? 'text' : 'password'"
                    :append-inner-icon="
                      showCurrentPassword ? 'mdi-eye' : 'mdi-eye-off'
                    "
                    @click:append-inner="
                      showCurrentPassword = !showCurrentPassword
                    "
                    :rules="[rules.required]"
                    variant="outlined"
                    density="compact"
                    hide-details="auto"
                  ></v-text-field>
                </v-col>

                <v-col cols="12" md="4">
                  <v-text-field
                    v-model="passwordForm.new_password"
                    label="New Password"
                    :type="showNewPassword ? 'text' : 'password'"
                    :append-inner-icon="
                      showNewPassword ? 'mdi-eye' : 'mdi-eye-off'
                    "
                    @click:append-inner="showNewPassword = !showNewPassword"
                    :rules="[rules.required, rules.minLength]"
                    variant="outlined"
                    density="compact"
                    hint="At least 8 characters"
                    persistent-hint
                  ></v-text-field>
                </v-col>

                <v-col cols="12" md="4">
                  <v-text-field
                    v-model="passwordForm.new_password_confirmation"
                    label="Confirm Password"
                    :type="showConfirmPassword ? 'text' : 'password'"
                    :append-inner-icon="
                      showConfirmPassword ? 'mdi-eye' : 'mdi-eye-off'
                    "
                    @click:append-inner="
                      showConfirmPassword = !showConfirmPassword
                    "
                    :rules="[rules.required, rules.passwordMatch]"
                    variant="outlined"
                    density="compact"
                    hide-details="auto"
                  ></v-text-field>
                </v-col>
              </v-row>

              <v-alert
                v-if="passwordError"
                type="error"
                density="compact"
                class="mt-3 mb-2"
                dismissible
              >
                {{ passwordError }}
              </v-alert>

              <v-alert
                v-if="passwordSuccess"
                type="success"
                density="compact"
                class="mt-3 mb-2"
                dismissible
              >
                {{ passwordSuccess }}
              </v-alert>

              <button
                type="submit"
                class="password-btn"
                :disabled="changingPassword"
              >
                <v-icon v-if="!changingPassword" size="18"
                  >mdi-lock-check</v-icon
                >
                <v-progress-circular
                  v-else
                  size="18"
                  width="2"
                  indeterminate
                  color="white"
                ></v-progress-circular>
                <span>{{
                  changingPassword ? "Updating..." : "Update Password"
                }}</span>
              </button>
            </v-form>
          </div>
        </div>

        <!-- Two-Factor Authentication Card -->
        <TwoFactorSetup />
      </v-col>
    </v-row>

    <!-- Recovery Codes Dialog -->
    <v-dialog v-model="showRecoveryDialog" max-width="500">
      <v-card class="modern-dialog">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper warning">
            <v-icon size="24">mdi-key-variant</v-icon>
          </div>
          <div>
            <div class="dialog-title">Recovery Codes</div>
            <div class="dialog-subtitle">Save these codes securely</div>
          </div>
        </v-card-title>
        <v-card-text class="dialog-content">
          <v-alert type="info" density="compact" variant="tonal" class="mb-3">
            <div class="text-caption">
              <strong>Important:</strong> Save these codes securely. Use them to
              access your account if you lose your authenticator.
            </div>
          </v-alert>

          <button
            class="dialog-action-btn"
            @click="regenerateRecoveryCodes"
            :disabled="regeneratingCodes"
          >
            <v-icon v-if="!regeneratingCodes" size="18">mdi-refresh</v-icon>
            <v-progress-circular
              v-else
              size="18"
              width="2"
              indeterminate
            ></v-progress-circular>
            <span>{{
              regeneratingCodes ? "Generating..." : "Generate New Codes"
            }}</span>
          </button>

          <div v-if="recoveryCodes.length > 0" class="recovery-codes-list">
            <v-list density="compact" class="codes-list">
              <v-list-item
                v-for="(code, index) in recoveryCodes"
                :key="index"
                class="code-item"
              >
                <template v-slot:prepend>
                  <v-icon size="x-small">mdi-key</v-icon>
                </template>
                <v-list-item-title class="code-text">{{
                  code
                }}</v-list-item-title>
              </v-list-item>
            </v-list>

            <button class="download-codes-btn" @click="downloadRecoveryCodes">
              <v-icon size="18">mdi-download</v-icon>
              <span>Download Codes</span>
            </button>
          </div>
        </v-card-text>
        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <button
            class="dialog-btn dialog-btn-close"
            @click="showRecoveryDialog = false"
          >
            Close
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useToast } from "vue-toastification";
import { formatDateTime as formatDate } from "@/utils/formatters";
import TwoFactorSetup from "@/components/TwoFactorSetup.vue";
import api from "@/services/api";
import { devLog } from "@/utils/devLog";
import { useConfirmDialog } from "@/composables/useConfirmDialog";

const { confirm: confirmDialog } = useConfirmDialog();
const authStore = useAuthStore();
const toast = useToast();

const user = computed(() => authStore.user);
const avatarFile = ref([]);
const avatarPreview = ref(null);
const uploadingAvatar = ref(false);

// Profile edit form
const editingProfile = ref(false);
const profileFormRef = ref(null);
const profileForm = reactive({
  name: "",
  username: "",
  email: "",
});
const updatingProfile = ref(false);
const profileError = ref("");
const profileSuccess = ref("");

const passwordFormRef = ref(null);
const passwordForm = reactive({
  current_password: "",
  new_password: "",
  new_password_confirmation: "",
});

const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);
const changingPassword = ref(false);
const passwordError = ref("");
const passwordSuccess = ref("");

// 2FA variables
const loading2FA = ref(false);
const twoFactorEnabled = ref(false);
const disabling2FA = ref(false);
const show2FASetup = ref(false);
const showRecoveryDialog = ref(false);
const recoveryCodes = ref([]);
const regeneratingCodes = ref(false);

const rules = {
  required: (v) => !!v || "This field is required",
  minLength: (v) => (v && v.length >= 8) || "Must be at least 8 characters",
  passwordMatch: (v) =>
    v === passwordForm.new_password || "Passwords must match",
  email: (v) => !v || /.+@.+\..+/.test(v) || "Email must be valid",
};

onMounted(() => {
  fetchProfile();
  check2FAStatus();
});

async function fetchProfile() {
  try {
    const response = await api.get("/profile");
    if (response.data.success) {
      user.value = response.data.data;
      // Update auth store with latest user data
      authStore.user = response.data.data;
    }
  } catch (error) {
    toast.error("Failed to load profile");
    devLog.error(error);
  }
}

// Profile editing functions
function startEditProfile() {
  editingProfile.value = true;
  profileForm.name = user.value?.name || "";
  profileForm.username = user.value?.username || "";
  profileForm.email = user.value?.email || "";
  profileError.value = "";
  profileSuccess.value = "";
}

function cancelEditProfile() {
  editingProfile.value = false;
  profileError.value = "";
  profileSuccess.value = "";
  if (profileFormRef.value) {
    profileFormRef.value.resetValidation();
  }
}

async function updateProfileInfo() {
  profileError.value = "";
  profileSuccess.value = "";

  // Validate form
  const { valid } = await profileFormRef.value.validate();
  if (!valid) {
    toast.error("Please fill in all fields correctly");
    return;
  }

  updatingProfile.value = true;

  try {
    const response = await api.put("/profile", profileForm);

    if (response.data.success) {
      profileSuccess.value = response.data.message;
      toast.success(response.data.message);

      // Update user data locally
      user.value = response.data.data;
      authStore.user = response.data.data;

      // Exit edit mode after a short delay
      setTimeout(() => {
        editingProfile.value = false;
        profileSuccess.value = "";
      }, 2000);
    }
  } catch (error) {
    const errorMsg =
      error.response?.data?.message || "Failed to update profile";
    profileError.value = errorMsg;
    toast.error(errorMsg);

    // Handle validation errors
    if (error.response?.data?.errors) {
      const errors = error.response.data.errors;
      const errorMessages = Object.values(errors).flat().join(", ");
      profileError.value = errorMessages;
    }
    devLog.error(error);
  } finally {
    updatingProfile.value = false;
  }
}

// 2FA Functions
async function check2FAStatus() {
  loading2FA.value = true;
  try {
    const response = await api.get("/two-factor/status");
    twoFactorEnabled.value = response.data.enabled;
  } catch (error) {
    devLog.error("Failed to check 2FA status:", error);
  } finally {
    loading2FA.value = false;
  }
}

async function disable2FA() {
  if (
    !(await confirmDialog(
      "Are you sure you want to disable two-factor authentication? This will make your account less secure.",
    ))
  ) {
    return;
  }

  disabling2FA.value = true;
  try {
    await api.delete("/two-factor/disable");
    twoFactorEnabled.value = false;
    toast.success("Two-factor authentication disabled");
  } catch (error) {
    toast.error("Failed to disable 2FA");
    devLog.error(error);
  } finally {
    disabling2FA.value = false;
  }
}

function handle2FAEnabled() {
  twoFactorEnabled.value = true;
  show2FASetup.value = false;
  toast.success("Two-factor authentication enabled successfully");
}

async function regenerateRecoveryCodes() {
  regeneratingCodes.value = true;
  try {
    const response = await api.post("/two-factor/recovery-codes");
    recoveryCodes.value = response.data.recovery_codes || [];
    toast.success("New recovery codes generated");
  } catch (error) {
    toast.error("Failed to generate recovery codes");
    devLog.error(error);
  } finally {
    regeneratingCodes.value = false;
  }
}

function downloadRecoveryCodes() {
  const text = recoveryCodes.value.join("\n");
  const blob = new Blob([text], { type: "text/plain" });
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = "2fa-recovery-codes.txt";
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  window.URL.revokeObjectURL(url);
  toast.success("Recovery codes downloaded");
}

function handleAvatarChange() {
  // v-file-input with v-model returns array
  const file = avatarFile.value?.[0];

  if (!file) {
    avatarPreview.value = null;
    return;
  }

  // Validate file size (max 2MB)
  if (file.size > 2 * 1024 * 1024) {
    toast.error("Image size must be less than 2MB");
    avatarFile.value = [];
    avatarPreview.value = null;
    return;
  }

  // Validate file type
  if (!file.type.startsWith("image/")) {
    toast.error("Please select an image file");
    avatarFile.value = [];
    avatarPreview.value = null;
    return;
  }

  // Create preview
  const reader = new FileReader();
  reader.onload = (e) => {
    avatarPreview.value = e.target.result;
  };
  reader.readAsDataURL(file);
}

async function uploadAvatar() {
  // Handle both array and direct file access
  let file = null;
  if (Array.isArray(avatarFile.value) && avatarFile.value.length > 0) {
    file = avatarFile.value[0];
  } else if (avatarFile.value && avatarFile.value instanceof File) {
    file = avatarFile.value;
  }

  if (!file) {
    toast.error("Please select an image first");
    return;
  }

  uploadingAvatar.value = true;

  try {
    const formData = new FormData();
    formData.append("avatar", file);

    const response = await api.post("/profile/upload-avatar", formData, {
      headers: {
        "Content-Type": "multipart/form-data",
      },
    });

    if (response.data.success) {
      toast.success("Profile picture updated successfully");

      // Update user data locally
      user.value = response.data.data;
      authStore.user = response.data.data;

      // Clear the file input and preview
      avatarFile.value = [];
      avatarPreview.value = null;

      // Force a refresh to ensure avatar displays
      await fetchProfile();
    }
  } catch (error) {
    toast.error(
      error.response?.data?.message || "Failed to upload profile picture",
    );
    devLog.error(error);
  } finally {
    uploadingAvatar.value = false;
  }
}

async function removeAvatar() {
  if (
    !(await confirmDialog(
      "Are you sure you want to remove your profile picture?",
    ))
  ) {
    return;
  }

  uploadingAvatar.value = true;

  try {
    const response = await api.delete("/profile/remove-avatar");

    if (response.data.success) {
      toast.success("Profile picture removed successfully");
      user.value = response.data.data;
      // Update auth store
      authStore.user = response.data.data;
      avatarPreview.value = null;
    }
  } catch (error) {
    toast.error(
      error.response?.data?.message || "Failed to remove profile picture",
    );
    devLog.error(error);
  } finally {
    uploadingAvatar.value = false;
  }
}

function getAvatarUrl(avatar) {
  if (!avatar) return null;
  // If avatar is already a full URL, return it
  if (avatar.startsWith("http")) return avatar;
  // Otherwise, prepend the base URL (remove /api from VITE_API_URL)
  const apiUrl = (
    import.meta.env.VITE_API_URL || "http://localhost:8000/api"
  ).replace("/api", "");
  return `${apiUrl}/storage/${avatar}`;
}

async function changePassword() {
  passwordError.value = "";
  passwordSuccess.value = "";

  // Validate form
  const { valid } = await passwordFormRef.value.validate();
  if (!valid) {
    toast.error("Please fill in all fields correctly");
    return;
  }

  changingPassword.value = true;

  try {
    const response = await api.post("/profile/change-password", passwordForm);

    if (response.data.success) {
      passwordSuccess.value = response.data.message;
      toast.success(response.data.message);

      // Reset form
      passwordForm.current_password = "";
      passwordForm.new_password = "";
      passwordForm.new_password_confirmation = "";
      passwordFormRef.value.reset();
      passwordFormRef.value.resetValidation();

      // Clear success message after 5 seconds
      setTimeout(() => {
        passwordSuccess.value = "";
      }, 5000);
    }
  } catch (error) {
    passwordError.value =
      error.response?.data?.message || "Failed to change password";
    toast.error(passwordError.value);
    devLog.error(error);
  } finally {
    changingPassword.value = false;
  }
}
</script>

<style scoped lang="scss">
.profile-page {
  max-width: 1400px;
  margin: 0 auto;
}

// Page Header
.page-header {
  margin-bottom: 32px;
}

.page-title-section {
  display: flex;
  align-items: center;
  gap: 16px;
}

.page-icon-badge {
  width: 56px;
  height: 56px;
  border-radius: 14px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);
  flex-shrink: 0;

  .v-icon {
    color: #ffffff !important;
  }
}

.page-title {
  font-size: 28px;
  font-weight: 700;
  color: #001f3d;
  margin: 0 0 4px 0;
  letter-spacing: -0.5px;
}

.page-subtitle {
  font-size: 14px;
  color: rgba(0, 31, 61, 0.6);
  margin: 0;
}

// Profile Picture Card
.profile-picture-card {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  overflow: hidden;
}

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

.edit-btn {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: rgba(237, 152, 95, 0.1);
  border: 1px solid rgba(237, 152, 95, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s ease;

  .v-icon {
    color: #ed985f !important;
  }

  &:hover {
    background: rgba(237, 152, 95, 0.15);
    transform: scale(1.05);
  }
}

.profile-avatar-section {
  padding: 32px 24px;
}

.avatar-wrapper {
  display: flex;
  justify-content: center;
  margin-bottom: 24px;
}

.profile-avatar {
  border: 4px solid rgba(237, 152, 95, 0.2);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
}

.avatar-placeholder {
  width: 100%;
  height: 100%;
  background: linear-gradient(
    135deg,
    rgba(237, 152, 95, 0.2) 0%,
    rgba(247, 185, 128, 0.15) 100%
  );
  display: flex;
  align-items: center;
  justify-content: center;

  .v-icon {
    color: rgba(237, 152, 95, 0.4) !important;
  }
}

.image-guidelines {
  background: rgba(0, 31, 61, 0.03);
  border: 1px solid rgba(0, 31, 61, 0.08);
  border-radius: 10px;
  padding: 14px;
  margin-bottom: 20px;
}

.guidelines-title {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
  color: #001f3d;
  margin-bottom: 10px;

  .v-icon {
    color: #ed985f !important;
  }
}

.guidelines-list {
  list-style: none;
  padding: 0;
  margin: 0;

  li {
    font-size: 13px;
    color: rgba(0, 31, 61, 0.7);
    padding-left: 16px;
    position: relative;
    margin-bottom: 4px;

    &:before {
      content: "•";
      position: absolute;
      left: 6px;
      color: #ed985f;
    }

    &:last-child {
      margin-bottom: 0;
    }
  }
}

.avatar-actions {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.avatar-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 20px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
  width: 100%;

  &:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }

  &.avatar-btn-primary {
    background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);

    .v-icon {
      color: #ffffff !important;
    }

    &:not(:disabled):hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(237, 152, 95, 0.4);
    }
  }

  &.avatar-btn-danger {
    background: rgba(244, 67, 54, 0.1);
    color: #f44336;
    border: 1px solid rgba(244, 67, 54, 0.2);

    .v-icon {
      color: #f44336 !important;
    }

    &:not(:disabled):hover {
      background: rgba(244, 67, 54, 0.15);
      border-color: rgba(244, 67, 54, 0.3);
    }
  }
}

// Profile Info Card
.profile-info-card,
.profile-password-card {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  overflow: hidden;
}

.profile-info-view {
  display: flex;
  flex-direction: column;
  gap: 16px;
  padding: 24px;
}

.info-item {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 14px;
  background: rgba(0, 31, 61, 0.02);
  border-radius: 10px;
  transition: all 0.3s ease;

  &:hover {
    background: rgba(237, 152, 95, 0.04);
  }
}

.info-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: linear-gradient(
    135deg,
    rgba(237, 152, 95, 0.12) 0%,
    rgba(247, 185, 128, 0.08) 100%
  );
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;

  .v-icon {
    color: #ed985f !important;
  }
}

.info-content {
  flex: 1;
  min-width: 0;
}

.info-label {
  font-size: 12px;
  font-weight: 600;
  color: rgba(0, 31, 61, 0.6);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
}

.info-value {
  font-size: 15px;
  font-weight: 600;
  color: #001f3d;
}

// Forms
.profile-edit-form,
.password-form-wrapper {
  padding: 24px;
}

.form-actions {
  display: flex;
  gap: 10px;
  margin-top: 16px;
}

.form-btn,
.password-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;

  &:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }

  &.form-btn-primary {
    background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);

    .v-icon {
      color: #ffffff !important;
    }

    &:not(:disabled):hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(237, 152, 95, 0.4);
    }
  }

  &.form-btn-secondary {
    background: rgba(0, 31, 61, 0.06);
    color: rgba(0, 31, 61, 0.8);

    .v-icon {
      color: rgba(0, 31, 61, 0.8) !important;
    }

    &:not(:disabled):hover {
      background: rgba(0, 31, 61, 0.1);
    }
  }
}

.password-btn {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: #ffffff;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);
  margin-top: 16px;

  .v-icon {
    color: #ffffff !important;
  }

  &:not(:disabled):hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(237, 152, 95, 0.4);
  }
}

// Dialog Styles
.modern-dialog {
  border-radius: 16px !important;

  .dialog-header {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 24px !important;
    background: linear-gradient(
      135deg,
      rgba(0, 31, 61, 0.02) 0%,
      rgba(237, 152, 95, 0.02) 100%
    );
    border-bottom: 1px solid rgba(0, 31, 61, 0.08);
  }

  .dialog-icon-wrapper {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;

    &.warning {
      background: linear-gradient(
        135deg,
        rgba(247, 185, 128, 0.2) 0%,
        rgba(237, 152, 95, 0.15) 100%
      );

      .v-icon {
        color: #ed985f !important;
      }
    }
  }

  .dialog-title {
    font-size: 20px;
    font-weight: 700;
    color: #001f3d;
    margin-bottom: 4px;
  }

  .dialog-subtitle {
    font-size: 13px;
    color: rgba(0, 31, 61, 0.6);
  }

  .dialog-content {
    padding: 24px !important;
  }

  .dialog-actions {
    padding: 16px 24px !important;
    background: rgba(0, 31, 61, 0.02);
    border-top: 1px solid rgba(0, 31, 61, 0.08);
  }
}

.dialog-action-btn,
.download-codes-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 20px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  border: none;
  border-radius: 10px;
  color: #ffffff;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  width: 100%;
  margin-bottom: 16px;

  .v-icon {
    color: #ffffff !important;
  }

  &:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }

  &:not(:disabled):hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(237, 152, 95, 0.4);
  }
}

.download-codes-btn {
  background: rgba(237, 152, 95, 0.1);
  color: #ed985f;
  margin-bottom: 0;

  .v-icon {
    color: #ed985f !important;
  }

  &:hover {
    background: rgba(237, 152, 95, 0.15);
  }
}

.recovery-codes-list {
  margin-top: 16px;
}

.codes-list {
  background: rgba(0, 31, 61, 0.03);
  border: 1px solid rgba(0, 31, 61, 0.08);
  border-radius: 10px;
  padding: 12px;
  margin-bottom: 12px;
  max-height: 300px;
  overflow-y: auto;
}

.code-item {
  min-height: 32px !important;
  padding: 6px 8px !important;
  margin-bottom: 4px;

  &:last-child {
    margin-bottom: 0;
  }
}

.code-text {
  font-family: "Courier New", monospace;
  font-size: 13px !important;
  color: #001f3d;
  font-weight: 600;
}

.dialog-btn {
  padding: 8px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;

  &.dialog-btn-close {
    background: rgba(0, 31, 61, 0.06);
    color: rgba(0, 31, 61, 0.8);

    &:hover {
      background: rgba(0, 31, 61, 0.1);
    }
  }
}
</style>
