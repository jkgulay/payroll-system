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
      <!-- Left Column: Profile Picture -->
      <v-col cols="12" md="4">
        <!-- Profile Picture -->
        <v-card class="industrial-card">
          <v-card-title class="construction-header pa-3">
            <v-icon class="mr-2" size="20">mdi-camera-account</v-icon>
            <span class="text-subtitle-1">Profile Picture</span>
          </v-card-title>
          <v-divider class="steel-divider"></v-divider>
          <v-card-text class="text-center pa-6">
            <v-avatar size="180" class="mb-4" color="steel">
              <v-img
                v-if="avatarPreview || user?.avatar"
                :src="avatarPreview || getAvatarUrl(user?.avatar)"
                cover
              ></v-img>
              <v-icon v-else size="90" color="white">mdi-account</v-icon>
            </v-avatar>

            <v-alert
              density="compact"
              variant="tonal"
              type="info"
              class="mb-3 text-caption"
            >
              <div class="text-left">
                <strong>Image Guidelines:</strong>
                <ul class="mt-1 pl-4">
                  <li>Maximum size: 2MB</li>
                  <li>Format: JPG, PNG, GIF</li>
                  <li>Recommended: Square image</li>
                </ul>
              </div>
            </v-alert>

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

            <v-btn
              color="primary"
              size="small"
              block
              :loading="uploadingAvatar"
              :disabled="!avatarFile || avatarFile.length === 0"
              @click="uploadAvatar"
              class="mb-2"
            >
              <v-icon size="small" left>mdi-upload</v-icon>
              Upload Picture
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
              Remove Picture
            </v-btn>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Right Column: Profile Info & Security -->
      <v-col cols="12" md="8">
        <!-- Profile Information & Password -->
        <v-card class="industrial-card">
          <v-card-title class="construction-header pa-3">
            <v-icon class="mr-2" size="20">mdi-account-cog</v-icon>
            <span class="text-subtitle-1">Profile Information & Security</span>
            <v-spacer></v-spacer>
            <v-btn
              v-if="!editingProfile"
              icon
              size="x-small"
              variant="text"
              @click="startEditProfile"
            >
              <v-icon size="small">mdi-pencil</v-icon>
            </v-btn>
          </v-card-title>
          <v-divider class="steel-divider"></v-divider>
          <v-card-text class="pa-3">
            <!-- Profile Info Section -->
            <div class="mb-4">
              <div class="text-subtitle-2 mb-2 d-flex align-center">
                <v-icon size="small" class="mr-2">mdi-account</v-icon>
                Profile Information
              </div>
              
              <!-- Edit Mode -->
              <v-form v-if="editingProfile" ref="profileFormRef" @submit.prevent="updateProfileInfo" validate-on="submit lazy">
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

                <v-alert v-if="profileError" type="error" density="compact" class="mt-3 mb-2" dismissible @click:close="profileError = ''">
                  {{ profileError }}
                </v-alert>

                <v-alert v-if="profileSuccess" type="success" density="compact" class="mt-3 mb-2" dismissible @click:close="profileSuccess = ''">
                  {{ profileSuccess }}
                </v-alert>

                <div class="mt-3 d-flex gap-2">
                  <v-btn
                    type="submit"
                    color="primary"
                    size="small"
                    :loading="updatingProfile"
                  >
                    <v-icon size="small" left>mdi-check</v-icon>
                    Save Changes
                  </v-btn>
                  <v-btn
                    size="small"
                    variant="outlined"
                    @click="cancelEditProfile"
                    :disabled="updatingProfile"
                  >
                    <v-icon size="small" left>mdi-close</v-icon>
                    Cancel
                  </v-btn>
                </div>
              </v-form>

              <!-- View Mode -->
              <v-list v-else density="compact" class="pa-0">
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
            </div>

            <v-divider class="my-4"></v-divider>

            <!-- Change Password Section -->
            <div>
              <div class="text-subtitle-2 mb-3 d-flex align-center">
                <v-icon size="small" class="mr-2">mdi-lock-reset</v-icon>
                Change Password
              </div>

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

// Profile edit form
const editingProfile = ref(false);
const profileFormRef = ref(null);
const profileForm = reactive({
  name: '',
  username: '',
  email: '',
});
const updatingProfile = ref(false);
const profileError = ref('');
const profileSuccess = ref('');

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
  email: (v) => !v || /.+@.+\..+/.test(v) || 'Email must be valid',
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

// Profile editing functions
function startEditProfile() {
  editingProfile.value = true;
  profileForm.name = user.value?.name || '';
  profileForm.username = user.value?.username || '';
  profileForm.email = user.value?.email || '';
  profileError.value = '';
  profileSuccess.value = '';
}

function cancelEditProfile() {
  editingProfile.value = false;
  profileError.value = '';
  profileSuccess.value = '';
  if (profileFormRef.value) {
    profileFormRef.value.resetValidation();
  }
}

async function updateProfileInfo() {
  profileError.value = '';
  profileSuccess.value = '';

  // Validate form
  const { valid } = await profileFormRef.value.validate();
  if (!valid) {
    toast.error('Please fill in all fields correctly');
    return;
  }

  updatingProfile.value = true;

  try {
    const response = await api.put('/profile', profileForm);

    if (response.data.success) {
      profileSuccess.value = response.data.message;
      toast.success(response.data.message);

      // Update user data locally
      user.value = response.data.data;
      authStore.user = response.data.data;

      // Exit edit mode after a short delay
      setTimeout(() => {
        editingProfile.value = false;
        profileSuccess.value = '';
      }, 2000);
    }
  } catch (error) {
    const errorMsg = error.response?.data?.message || 'Failed to update profile';
    profileError.value = errorMsg;
    toast.error(errorMsg);
    
    // Handle validation errors
    if (error.response?.data?.errors) {
      const errors = error.response.data.errors;
      const errorMessages = Object.values(errors).flat().join(', ');
      profileError.value = errorMessages;
    }
    console.error(error);
  } finally {
    updatingProfile.value = false;
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
