<template>
  <div>
    <h1 class="text-h4 font-weight-bold mb-6">Settings</h1>

    <v-row>
      <v-col cols="12" md="6">
        <v-card>
          <v-card-title>
            <v-icon start>mdi-office-building</v-icon>
            Company Information
          </v-card-title>
          <v-card-text>
            <p class="text-body-2 text-medium-emphasis">
              Configure company details, logo, and contact information
            </p>
            <v-btn color="primary" variant="tonal" class="mt-4"
              >Edit Company Info</v-btn
            >
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="6">
        <v-card>
          <v-card-title>
            <v-icon start>mdi-cash-settings</v-icon>
            Payroll Settings
          </v-card-title>
          <v-card-text>
            <p class="text-body-2 text-medium-emphasis">
              Configure payroll cutoffs, payment schedules, and rules
            </p>
            <v-btn color="primary" variant="tonal" class="mt-4"
              >Edit Payroll Settings</v-btn
            >
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="6">
        <v-card>
          <v-card-title>
            <v-icon start>mdi-bank</v-icon>
            Government Rates
          </v-card-title>
          <v-card-text>
            <p class="text-body-2 text-medium-emphasis">
              Update SSS, PhilHealth, Pag-IBIG, and tax tables
            </p>
            <v-btn
              color="primary"
              variant="tonal"
              class="mt-4"
              @click="openRatesDialog"
              >Manage Rates</v-btn
            >
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="6">
        <v-card>
          <v-card-title>
            <v-icon start>mdi-account-multiple</v-icon>
            User Management
          </v-card-title>
          <v-card-text>
            <p class="text-body-2 text-medium-emphasis">
              Manage system users and access permissions
            </p>
            <v-btn color="primary" variant="tonal" class="mt-4"
              >Manage Users</v-btn
            >
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Government Rates Dialog -->
    <GovernmentRatesDialog v-model="showRatesDialog" />
  </div>
</template>

<script setup>
import { ref } from "vue";
import GovernmentRatesDialog from "@/components/settings/GovernmentRatesDialog.vue";

const showRatesDialog = ref(false);

const openRatesDialog = () => {
  showRatesDialog.value = true;
};
</script>
