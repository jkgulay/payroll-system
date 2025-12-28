<template>
  <v-container fluid>
    <v-row>
      <v-col cols="12">
        <h1 class="construction-header text-h4 mb-6">
          <v-icon>mdi-account-cog</v-icon>
          My Profile
        </h1>
      </v-col>
    </v-row>

    <v-row>
      <!-- Left Column: Profile Picture & Info -->
      <v-col cols="12" md="4">
        <!-- Profile Picture -->
        <v-card class="industrial-card mb-4">
          <v-card-title class="construction-header pa-3">
            <v-icon class="mr-2" size="20">mdi-camera-account</v-icon>
            <span class="text-subtitle-1">Profile Picture</span>
          </v-card-title>
          <v-divider class="steel-divider"></v-divider>
          <v-card-text class="text-center pa-4">
            <v-avatar size="150" class="mb-3" color="steel">
              <v-img
                v-if="avatarPreview || user?.avatar"
                :src="avatarPreview || getAvatarUrl(user?.avatar)"
                cover
              ></v-img>
              <v-icon v-else size="80" color="white">mdi-account</v-icon>
            </v-avatar>

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
              class="mb-2"
            ></v-file-input>

            <v-btn
              color="primary"
              size="small"
              block
              :loading="uploadingAvatar"
              :disabled="!avatarFile || avatarFile.length === 0"
              @click="uploadAvatar"
              class="mb-1"
            >
              <v-icon size="small" left>mdi-upload</v-icon>
              Upload
            </v-btn>

            <v-btn
              v-if="user?.avatar"
              color="error"
              variant="tonal"
              size="small"
              block
              @click="removeAvatar"
              :disabled="uploadingAvatar"
            >
              <v-icon size="small" left>mdi-delete</v-icon>
              Remove
            </v-btn>
          </v-card-text>
        </v-card>

        <!-- Profile Information -->
        <v-card class="industrial-card">
          <v-card-title class="construction-header pa-3">
            <v-icon class="mr-2" size="20">mdi-account</v-icon>
            <span class="text-subtitle-1">Profile Info</span>
          </v-card-title>
          <v-divider class="steel-divider"></v-divider>
          <v-card-text class="pa-3">
            <v-list density="compact" class="pa-0">
              <v-list-item class="px-0">
                <template v-slot:prepend>
                  <v-icon size="small">mdi-account-circle</v-icon>
                </template>
                <v-list-item-title class="text-caption">Full Name</v-list-item-title>
                <v-list-item-subtitle class="text-body-2">{{ user?.name || 'N/A' }}</v-list-item-subtitle>
              </v-list-item>
              <v-list-item class="px-0">
                <template v-slot:prepend>
                  <v-icon size="small">mdi-account-badge</v-icon>
                </template>
                <v-list-item-title class="text-caption">Username</v-list-item-title>
                <v-list-item-subtitle class="text-body-2">{{ user?.username }}</v-list-item-subtitle>
              </v-list-item>
              <v-list-item class="px-0">
                <template v-slot:prepend>
                  <v-icon size="small">mdi-email</v-icon>
                </template>
                <v-list-item-title class="text-caption">Email</v-list-item-title>
                <v-list-item-subtitle class="text-body-2">{{ user?.email }}</v-list-item-subtitle>
              </v-list-item>
              <v-list-item class="px-0">
                <template v-slot:prepend>
                  <v-icon size="small">mdi-shield-account</v-icon>
                </template>
                <v-list-item-title class="text-caption">Role</v-list-item-title>
                <v-list-item-subtitle class="text-body-2 text-capitalize">{{ user?.role }}</v-list-item-subtitle>
              </v-list-item>
              <v-list-item v-if="user?.last_login_at" class="px-0">
                <template v-slot:prepend>
                  <v-icon size="small">mdi-clock</v-icon>
                </template>
                <v-list-item-title class="text-caption">Last Login</v-list-item-title>
                <v-list-item-subtitle class="text-body-2">{{ formatDate(user.last_login_at) }}</v-list-item-subtitle>
              </v-list-item>
            </v-list>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Right Column: Security Settings -->
      <v-col cols="12" md="8">
        <!-- Change Password -->
        <v-card class="industrial-card mb-4">
          <v-card-title class="construction-header pa-3">
            <v-icon class="mr-2" size="20">mdi-lock-reset</v-icon>
            <span class="text-subtitle-1">Change Password</span>
          </v-card-title>
          <v-divider class="steel-divider"></v-divider>
          <v-card-text class="pa-3">
            <v-form @submit.prevent="changePassword" ref="passwordFormRef" validate-on="submit lazy">
              <v-row dense>
                <v-col cols="12" md="4">
                  <v-text-field
                    v-model="passwordForm.current_password"
                    label="Current Password"
                    :type="showCurrentPassword ? 'text' : 'password'"
                    :append-inner-icon="showCurrentPassword ? 'mdi-eye' : 'mdi-eye-off'"
                    @click:append-inner="showCurrentPassword = !showCurrentPassword"
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
                    :append-inner-icon="showNewPassword ? 'mdi-eye' : 'mdi-eye-off'"
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
                    :append-inner-icon="showConfirmPassword ? 'mdi-eye' : 'mdi-eye-off'"
                    @click:append-inner="showConfirmPassword = !showConfirmPassword"
                    :rules="[rules.required, rules.passwordMatch]"
                    variant="outlined"
                    density="compact"
                    hide-details="auto"
                  ></v-text-field>
                </v-col>
              </v-row>

              <v-alert v-if="passwordError" type="error" density="compact" class="mt-3 mb-2" dismissible>
                {{ passwordError }}
              </v-alert>

              <v-alert v-if="passwordSuccess" type="success" density="compact" class="mt-3 mb-2" dismissible>
                {{ passwordSuccess }}
              </v-alert>

              <v-btn
                type="submit"
                color="primary"
                size="small"
                :loading="changingPassword"
                class="mt-3"
              >
                <v-icon size="small" left>mdi-lock-check</v-icon>
                Update Password
              </v-btn>
            </v-form>
          </v-card-text>
        </v-card>

        <!-- Two-Factor Authentication -->
        <v-card class="industrial-card">
          <v-card-title class="construction-header pa-3">
            <v-icon class="mr-2" size="20">mdi-two-factor-authentication</v-icon>
            <span class="text-subtitle-1">Two-Factor Authentication</span>
          </v-card-title>
          <v-divider class="steel-divider"></v-divider>
          <v-card-text class="pa-3">
            <div v-if="loading2FA" class="text-center pa-4">
              <v-progress-circular indeterminate color="primary" size="32"></v-progress-circular>
            </div>
            <div v-else>
              <!-- 2FA Enabled -->
              <div v-if="twoFactorEnabled">
                <v-alert type="success" density="compact" variant="tonal" class="mb-3">
                  <div class="d-flex align-center">
                    <v-icon start size="small">mdi-shield-check</v-icon>
                    <span class="text-body-2">2FA is enabled and protecting your account</span>
                  </div>
                </v-alert>

                <div class="d-flex gap-2">
                  <v-btn
                    color="error"
                    variant="outlined"
                    size="small"
                    @click="disable2FA"
                    :loading="disabling2FA"
                  >
                    <v-icon size="small" left>mdi-shield-off</v-icon>
                    Disable
                  </v-btn>

                  <v-btn
                    color="warning"
                    variant="tonal"
                    size="small"
                    @click="showRecoveryDialog = true"
                    :disabled="disabling2FA"
                  >
                    <v-icon size="small" left>mdi-key-variant</v-icon>
                    Recovery Codes
                  </v-btn>
                </div>
              </div>

              <!-- 2FA Disabled -->
              <div v-else>
                <v-alert type="warning" density="compact" variant="tonal" class="mb-3">
                  <div class="d-flex align-center">
                    <v-icon start size="small">mdi-shield-alert</v-icon>
                    <span class="text-body-2">2FA is not enabled</span>
                  </div>
                </v-alert>

                <p class="text-caption mb-3">
                  Secure your account with two-factor authentication using an authenticator app.
                </p>

                <v-btn
                  color="primary"
                  size="small"
                  @click="show2FASetup = true"
                >
                  <v-icon size="small" left>mdi-shield-plus</v-icon>
                  Enable 2FA
                </v-btn>
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Recovery Codes Dialog -->
    <v-dialog v-model="showRecoveryDialog" max-width="500">
      <v-card>
        <v-card-title class="bg-warning pa-3">
          <v-icon class="mr-2" size="20">mdi-key-variant</v-icon>
          <span class="text-subtitle-1">Recovery Codes</span>
        </v-card-title>
        <v-card-text class="pa-3">
          <v-alert type="info" density="compact" variant="tonal" class="mb-3">
            <div class="text-caption">
              <strong>Important:</strong> Save these codes securely. Use them to access your account if you lose your authenticator.
            </div>
          </v-alert>

          <v-btn
            color="primary"
            size="small"
            block
            class="mb-2"
            @click="regenerateRecoveryCodes"
            :loading="regeneratingCodes"
          >
            <v-icon size="small" left>mdi-refresh</v-icon>
            Generate New Codes
          </v-btn>

          <div v-if="recoveryCodes.length > 0">
            <v-list density="compact" class="bg-grey-lighten-4 rounded pa-2 mb-2">
              <v-list-item v-for="(code, index) in recoveryCodes" :key="index" class="px-2 py-1">
                <template v-slot:prepend>
                  <v-icon size="x-small">mdi-key</v-icon>
                </template>
                <v-list-item-title class="text-caption font-mono">{{ code }}</v-list-item-title>
              </v-list-item>
            </v-list>

            <v-btn
              color="success"
              variant="tonal"
              size="small"
              block
              @click="downloadRecoveryCodes"
            >
              <v-icon size="small" left>mdi-download</v-icon>
              Download
            </v-btn>
          </div>
        </v-card-text>
        <v-card-actions class="pa-3">
          <v-spacer></v-spacer>
          <v-btn size="small" @click="showRecoveryDialog = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- 2FA Setup Dialog -->
    <TwoFactorSetup v-model="show2FASetup" @enabled="handle2FAEnabled" />
  </v-container>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue';
import { useAuthStore } from '@/stores/auth';
import { useToast } from 'vue-toastification';
import { format } from 'date-fns';
import TwoFactorSetup from '@/components/TwoFactorSetup.vue';
import api from '@/services/api';

const authStore = useAuthStore();
const toast = useToast();

const user = computed(() => authStore.user);
const avatarFile = ref([]);
const avatarPreview = ref(null);
const uploadingAvatar = ref(false);

const passwordFormRef = ref(null);
const passwordForm = reactive({
  current_password: '',
  new_password: '',
  new_password_confirmation: '',
});

const showCurrentPassword = ref(false);
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);
const changingPassword = ref(false);
const passwordError = ref('');
const passwordSuccess = ref('');

// 2FA variables
const loading2FA = ref(false);
const twoFactorEnabled = ref(false);
const disabling2FA = ref(false);
const show2FASetup = ref(false);
const showRecoveryDialog = ref(false);
const recoveryCodes = ref([]);
const regeneratingCodes = ref(false);

const rules = {
  required: (v) => !!v || 'This field is required',
  minLength: (v) => (v && v.length >= 8) || 'Must be at least 8 characters',
  passwordMatch: (v) => v === passwordForm.new_password || 'Passwords must match',
};

onMounted(() => {
  fetchProfile();
  check2FAStatus();
});

async function fetchProfile() {
  try {
    const response = await api.get('/profile');
    if (response.data.success) {
      user.value = response.data.data;
      // Update auth store with latest user data
      authStore.user = response.data.data;
    }
  } catch (error) {
    toast.error('Failed to load profile');
    console.error(error);
  }
}

// 2FA Functions
async function check2FAStatus() {
  loading2FA.value = true;
  try {
    const response = await api.get('/two-factor/status');
    twoFactorEnabled.value = response.data.enabled;
  } catch (error) {
    console.error('Failed to check 2FA status:', error);
  } finally {
    loading2FA.value = false;
  }
}

async function disable2FA() {
  if (!confirm('Are you sure you want to disable two-factor authentication? This will make your account less secure.')) {
    return;
  }

  disabling2FA.value = true;
  try {
    await api.delete('/two-factor/disable');
    twoFactorEnabled.value = false;
    toast.success('Two-factor authentication disabled');
  } catch (error) {
    toast.error('Failed to disable 2FA');
    console.error(error);
  } finally {
    disabling2FA.value = false;
  }
}

function handle2FAEnabled() {
  twoFactorEnabled.value = true;
  show2FASetup.value = false;
  toast.success('Two-factor authentication enabled successfully');
}

async function regenerateRecoveryCodes() {
  regeneratingCodes.value = true;
  try {
    const response = await api.post('/two-factor/recovery-codes');
    recoveryCodes.value = response.data.recovery_codes || [];
    toast.success('New recovery codes generated');
  } catch (error) {
    toast.error('Failed to generate recovery codes');
    console.error(error);
  } finally {
    regeneratingCodes.value = false;
  }
}

function downloadRecoveryCodes() {
  const text = recoveryCodes.value.join('\n');
  const blob = new Blob([text], { type: 'text/plain' });
  const url = window.URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = '2fa-recovery-codes.txt';
  document.body.appendChild(a);
  a.click();
  document.body.removeChild(a);
  window.URL.revokeObjectURL(url);
  toast.success('Recovery codes downloaded');
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
    toast.error('Image size must be less than 2MB');
    avatarFile.value = [];
    avatarPreview.value = null;
    return;
  }

  // Validate file type
  if (!file.type.startsWith('image/')) {
    toast.error('Please select an image file');
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
    toast.error('Please select an image first');
    return;
  }

  uploadingAvatar.value = true;

  try {
    const formData = new FormData();
    formData.append('avatar', file);

    const response = await api.post('/profile/upload-avatar', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });

    if (response.data.success) {
      toast.success('Profile picture updated successfully');
      
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
    toast.error(error.response?.data?.message || 'Failed to upload profile picture');
    console.error(error);
  } finally {
    uploadingAvatar.value = false;
  }
}

async function removeAvatar() {
  if (!confirm('Are you sure you want to remove your profile picture?')) {
    return;
  }

  uploadingAvatar.value = true;

  try {
    const response = await api.delete('/profile/remove-avatar');

    if (response.data.success) {
      toast.success('Profile picture removed successfully');
      user.value = response.data.data;
      // Update auth store
      authStore.user = response.data.data;
      avatarPreview.value = null;
    }
  } catch (error) {
    toast.error(error.response?.data?.message || 'Failed to remove profile picture');
    console.error(error);
  } finally {
    uploadingAvatar.value = false;
  }
}

function getAvatarUrl(avatar) {
  if (!avatar) return null;
  // If avatar is already a full URL, return it
  if (avatar.startsWith('http')) return avatar;
  // Otherwise, prepend the base URL (remove /api from VITE_API_URL)
  const apiUrl = (import.meta.env.VITE_API_URL || 'http://localhost:8000/api').replace('/api', '');
  return `${apiUrl}/storage/${avatar}`;
}

async function changePassword() {
  passwordError.value = '';
  passwordSuccess.value = '';

  // Validate form
  const { valid } = await passwordFormRef.value.validate();
  if (!valid) {
    toast.error('Please fill in all fields correctly');
    return;
  }

  changingPassword.value = true;

  try {
    const response = await api.post('/profile/change-password', passwordForm);

    if (response.data.success) {
      passwordSuccess.value = response.data.message;
      toast.success(response.data.message);

      // Reset form
      passwordForm.current_password = '';
      passwordForm.new_password = '';
      passwordForm.new_password_confirmation = '';
      passwordFormRef.value.reset();
      passwordFormRef.value.resetValidation();

      // Clear success message after 5 seconds
      setTimeout(() => {
        passwordSuccess.value = '';
      }, 5000);
    }
  } catch (error) {
    passwordError.value = error.response?.data?.message || 'Failed to change password';
    toast.error(passwordError.value);
    console.error(error);
  } finally {
    changingPassword.value = false;
  }
}

function formatDate(dateString) {
  if (!dateString) return 'N/A';
  return format(new Date(dateString), 'MMM dd, yyyy hh:mm a');
}
</script>
