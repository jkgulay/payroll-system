<template>
  <v-container fluid>
    <v-row>
      <v-col cols="12">
        <h1 class="text-h4 font-weight-bold mb-6">
          <v-icon>mdi-account-cog</v-icon>
          My Profile
        </h1>
      </v-col>
    </v-row>

    <v-row>
      <!-- Profile Information -->
      <v-col cols="12" md="6">
        <v-card>
          <v-card-title>
            <v-icon class="mr-2">mdi-account</v-icon>
            Profile Information
          </v-card-title>
          <v-card-text>
            <v-list>
              <v-list-item>
                <template v-slot:prepend>
                  <v-icon>mdi-account-circle</v-icon>
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
      </v-col>

      <!-- Change Password -->
      <v-col cols="12" md="6">
        <v-card>
          <v-card-title>
            <v-icon class="mr-2">mdi-lock-reset</v-icon>
            Change Password
          </v-card-title>
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
    <v-row>
      <v-col cols="12">
        <v-card>
          <v-card-title>
            <v-icon class="mr-2">mdi-shield-check</v-icon>
            Account Security
          </v-card-title>
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
    }
  } catch (error) {
    toast.error('Failed to load profile');
    console.error(error);
  }
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
