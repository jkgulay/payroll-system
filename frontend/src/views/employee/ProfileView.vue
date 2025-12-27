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
      <!-- Profile Picture Section -->
      <v-col cols="12" md="4">
        <v-card class="industrial-card">
          <v-card-title class="construction-header">
            <v-icon class="mr-2">mdi-camera-account</v-icon>
            Profile Picture
          </v-card-title>
          <v-divider class="steel-divider"></v-divider>
          <v-card-text class="text-center pa-6">
            <v-avatar size="200" class="mb-4" color="steel">
              <v-img
                v-if="avatarPreview || user?.avatar"
                :src="avatarPreview || getAvatarUrl(user?.avatar)"
                cover
              ></v-img>
              <v-icon v-else size="100" color="white">mdi-account</v-icon>
            </v-avatar>

            <div class="mb-4">
              <v-file-input
                v-model="avatarFile"
                accept="image/*"
                label="Choose profile picture"
                prepend-icon="mdi-camera"
                variant="outlined"
                density="comfortable"
                show-size
                @change="handleAvatarChange"
                :disabled="uploadingAvatar"
              ></v-file-input>
            </div>

            <v-btn
              color="primary"
              block
              :loading="uploadingAvatar"
              :disabled="!avatarFile || avatarFile.length === 0"
              @click="uploadAvatar"
              class="construction-btn"
            >
              <v-icon left>mdi-upload</v-icon>
              Upload Picture
            </v-btn>

            <v-btn
              v-if="user?.avatar"
              color="error"
              variant="tonal"
              block
              class="mt-2"
              @click="removeAvatar"
              :disabled="uploadingAvatar"
            >
              <v-icon left>mdi-delete</v-icon>
              Remove Picture
            </v-btn>

            <div class="text-caption text-medium-emphasis mt-3">
              Recommended: Square image, at least 200x200px
              <br>Max size: 2MB
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Profile Information -->
      <v-col cols="12" md="8">
        <v-card class="industrial-card mb-4">
          <v-card-title class="construction-header">
            <v-icon class="mr-2">mdi-account</v-icon>
            Profile Information
          </v-card-title>
          <v-divider class="steel-divider"></v-divider>
          <v-card-text>
            <v-list>
              <v-list-item>
                <template v-slot:prepend>
                  <v-icon>mdi-account-circle</v-icon>
                </template>
                <v-list-item-title>Full Name</v-list-item-title>
                <v-list-item-subtitle>{{ user?.name || 'N/A' }}</v-list-item-subtitle>
              </v-list-item>
              <v-list-item>
                <template v-slot:prepend>
                  <v-icon>mdi-account-badge</v-icon>
                </template>
                <v-list-item-title>Username</v-list-item-title>
                <v-list-item-subtitle>{{ user?.username }}</v-list-item-subtitle>
              </v-list-item>
              <v-list-item>
                <template v-slot:prepend>
                  <v-icon>mdi-email</v-icon>
                </template>
                <v-list-item-title>Email</v-list-item-title>
                <v-list-item-subtitle>{{ user?.email }}</v-list-item-subtitle>
              </v-list-item>
              <v-list-item>
                <template v-slot:prepend>
                  <v-icon>mdi-shield-account</v-icon>
                </template>
                <v-list-item-title>Role</v-list-item-title>
                <v-list-item-subtitle class="text-capitalize">{{ user?.role }}</v-list-item-subtitle>
              </v-list-item>
            </v-list>
          </v-card-text>
        </v-card>

        <!-- Change Password -->
        <v-card class="industrial-card">
          <v-card-title class="construction-header">
            <v-icon class="mr-2">mdi-lock-reset</v-icon>
            Change Password
          </v-card-title>
          <v-divider class="steel-divider"></v-divider>
          <v-card-text>
            <v-form @submit.prevent="changePassword" ref="passwordFormRef" validate-on="submit lazy">
              <v-text-field
                v-model="passwordForm.current_password"
                label="Current Password"
                :type="showCurrentPassword ? 'text' : 'password'"
                :append-inner-icon="showCurrentPassword ? 'mdi-eye' : 'mdi-eye-off'"
                @click:append-inner="showCurrentPassword = !showCurrentPassword"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
              ></v-text-field>

              <v-text-field
                v-model="passwordForm.new_password"
                label="New Password"
                :type="showNewPassword ? 'text' : 'password'"
                :append-inner-icon="showNewPassword ? 'mdi-eye' : 'mdi-eye-off'"
                @click:append-inner="showNewPassword = !showNewPassword"
                :rules="[rules.required, rules.minLength]"
                variant="outlined"
                density="comfortable"
                hint="At least 8 characters"
                persistent-hint
              ></v-text-field>

              <v-text-field
                v-model="passwordForm.new_password_confirmation"
                label="Confirm New Password"
                :type="showConfirmPassword ? 'text' : 'password'"
                :append-inner-icon="showConfirmPassword ? 'mdi-eye' : 'mdi-eye-off'"
                @click:append-inner="showConfirmPassword = !showConfirmPassword"
                :rules="[rules.required, rules.passwordMatch]"
                variant="outlined"
                density="comfortable"
              ></v-text-field>

              <v-alert v-if="passwordError" type="error" class="mb-3" dismissible>
                {{ passwordError }}
              </v-alert>

              <v-alert v-if="passwordSuccess" type="success" class="mb-3" dismissible>
                {{ passwordSuccess }}
              </v-alert>

              <v-btn
                type="submit"
                color="primary"
                :loading="changingPassword"
                block
                class="construction-btn"
              >
                <v-icon left>mdi-lock-check</v-icon>
                Change Password
              </v-btn>
            </v-form>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Account Security -->
    <v-row class="mt-4">
      <v-col cols="12">
        <v-card class="industrial-card">
          <v-card-title class="construction-header">
            <v-icon class="mr-2">mdi-shield-check</v-icon>
            Account Security
          </v-card-title>
          <v-divider class="steel-divider"></v-divider>
          <v-card-text>
            <v-list>
              <v-list-item>
                <template v-slot:prepend>
                  <v-icon color="success">mdi-check-circle</v-icon>
                </template>
                <v-list-item-title>Password Protection</v-list-item-title>
                <v-list-item-subtitle>Your account is protected with a password</v-list-item-subtitle>
              </v-list-item>
              <v-list-item v-if="user?.last_login_at">
                <template v-slot:prepend>
                  <v-icon>mdi-clock</v-icon>
                </template>
                <v-list-item-title>Last Login</v-list-item-title>
                <v-list-item-subtitle>{{ formatDate(user.last_login_at) }}</v-list-item-subtitle>
              </v-list-item>
            </v-list>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import { ref, reactive, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import { useAuthStore } from '@/stores/auth';
import api from '@/services/api';
import { format } from 'date-fns';

const toast = useToast();
const authStore = useAuthStore();

const user = ref(null);
const avatarFile = ref(null);
const avatarPreview = ref(null);
const uploadingAvatar = ref(false);

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
const passwordFormRef = ref(null);

const rules = {
  required: (v) => !!v || 'This field is required',
  minLength: (v) => (v && v.length >= 8) || 'Must be at least 8 characters',
  passwordMatch: (v) => v === passwordForm.new_password || 'Passwords must match',
};

onMounted(() => {
  fetchProfile();
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
