<template>
  <v-container fluid class="pa-6">
    <v-row>
      <v-col cols="12">
        <div class="d-flex align-center mb-6">
          <v-icon icon="mdi-shield-lock" size="32" color="primary" class="mr-3" />
          <div>
            <h1 class="text-h4 font-weight-bold">Security Settings</h1>
            <p class="text-body-2 text-medium-emphasis">
              Manage your account security and two-factor authentication
            </p>
          </div>
        </div>
      </v-col>
    </v-row>

    <v-row>
      <v-col cols="12" md="8" lg="6">
        <TwoFactorSetup />
      </v-col>

      <v-col cols="12" md="4" lg="6">
        <v-card>
          <v-card-title class="bg-info">
            <v-icon icon="mdi-information" class="mr-2" />
            Security Tips
          </v-card-title>
          <v-card-text class="pa-4">
            <v-list density="compact">
              <v-list-item>
                <template v-slot:prepend>
                  <v-icon icon="mdi-check-circle" color="success" />
                </template>
                <v-list-item-title>Use a strong, unique password</v-list-item-title>
              </v-list-item>

              <v-list-item>
                <template v-slot:prepend>
                  <v-icon icon="mdi-check-circle" color="success" />
                </template>
                <v-list-item-title>Enable two-factor authentication</v-list-item-title>
              </v-list-item>

              <v-list-item>
                <template v-slot:prepend>
                  <v-icon icon="mdi-check-circle" color="success" />
                </template>
                <v-list-item-title>Keep your recovery codes safe</v-list-item-title>
              </v-list-item>

              <v-list-item>
                <template v-slot:prepend>
                  <v-icon icon="mdi-check-circle" color="success" />
                </template>
                <v-list-item-title>Logout when not in use</v-list-item-title>
              </v-list-item>

              <v-list-item>
                <template v-slot:prepend>
                  <v-icon icon="mdi-check-circle" color="success" />
                </template>
                <v-list-item-title>Don't share your credentials</v-list-item-title>
              </v-list-item>
            </v-list>
          </v-card-text>
        </v-card>

        <v-card class="mt-4">
          <v-card-title class="bg-warning">
            <v-icon icon="mdi-shield-alert" class="mr-2" />
            Recommended Apps
          </v-card-title>
          <v-card-text class="pa-4">
            <p class="text-body-2 mb-3">
              Download one of these authenticator apps to use 2FA:
            </p>
            <v-list density="compact">
              <v-list-item>
                <template v-slot:prepend>
                  <v-icon icon="mdi-google" color="primary" />
                </template>
                <v-list-item-title>Google Authenticator</v-list-item-title>
                <v-list-item-subtitle>iOS & Android</v-list-item-subtitle>
              </v-list-item>

              <v-list-item>
                <template v-slot:prepend>
                  <v-icon icon="mdi-shield-check" color="success" />
                </template>
                <v-list-item-title>Authy</v-list-item-title>
                <v-list-item-subtitle>iOS, Android, Desktop</v-list-item-subtitle>
              </v-list-item>

              <v-list-item>
                <template v-slot:prepend>
                  <v-icon icon="mdi-microsoft" color="info" />
                </template>
                <v-list-item-title>Microsoft Authenticator</v-list-item-title>
                <v-list-item-subtitle>iOS & Android</v-list-item-subtitle>
              </v-list-item>
            </v-list>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup>
import TwoFactorSetup from "@/components/TwoFactorSetup.vue";
</script>

<style scoped>
/* Add any custom styles if needed */
</style>
