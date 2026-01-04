<template>
  <v-card>
    <!-- Coming Soon Notice -->
    <v-alert type="info" variant="tonal" prominent border="start" class="ma-4">
      <v-row align="center">
        <v-col class="grow">
          <div class="text-h6 mb-2">
            <v-icon left>mdi-clock-outline</v-icon>
            Coming Soon
          </div>
          <div>
            Yunatt Cloud Biometric Integration is currently under development
            and will be available in a future update.
          </div>
        </v-col>
      </v-row>
    </v-alert>

    <v-card-title class="d-flex align-center" style="opacity: 0.5">
      <v-icon left color="grey">mdi-cloud-sync</v-icon>
      Yunatt Cloud Integration (Disabled)
    </v-card-title>
    <v-divider></v-divider>

    <v-card-text style="opacity: 0.5; pointer-events: none">
      <!-- Connection Status -->
      <v-alert
        v-if="connectionStatus"
        :type="connectionStatus.status === 'connected' ? 'success' : 'error'"
        class="mb-4"
        variant="tonal"
      >
        <v-row align="center">
          <v-col cols="auto">
            <v-icon>{{
              connectionStatus.status === "connected"
                ? "mdi-check-circle"
                : "mdi-alert-circle"
            }}</v-icon>
          </v-col>
          <v-col>
            <div class="font-weight-bold">{{ connectionStatus.message }}</div>
            <div class="text-caption" v-if="lastSync">
              Last sync: {{ formatDate(lastSync) }}
            </div>
          </v-col>
        </v-row>
      </v-alert>

      <!-- Test Connection Button -->
      <v-row class="mb-4">
        <v-col cols="12" md="6">
          <v-btn
            color="primary"
            variant="tonal"
            block
            @click="testConnection"
            :loading="testingConnection"
          >
            <v-icon left>mdi-connection</v-icon>
            Test Connection
          </v-btn>
        </v-col>
        <v-col cols="12" md="6">
          <v-btn
            color="success"
            block
            @click="showSyncDialog = true"
            :disabled="!isConnected"
          >
            <v-icon left>mdi-cloud-download</v-icon>
            Sync Attendance
          </v-btn>
        </v-col>
      </v-row>

      <!-- Info Cards -->
      <v-row>
        <v-col cols="12" md="4">
          <v-card variant="tonal" color="primary">
            <v-card-text class="text-center">
              <v-icon size="48" class="mb-2">mdi-cloud-check</v-icon>
              <div class="text-h5">{{ stats.totalImported }}</div>
              <div class="text-caption">Total Records Imported</div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="4">
          <v-card variant="tonal" color="success">
            <v-card-text class="text-center">
              <v-icon size="48" class="mb-2">mdi-account-check</v-icon>
              <div class="text-h5">{{ stats.employeesLinked }}</div>
              <div class="text-caption">Employees Linked</div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="4">
          <v-card variant="tonal" color="info">
            <v-card-text class="text-center">
              <v-icon size="48" class="mb-2">mdi-calendar-sync</v-icon>
              <div class="text-h5">{{ stats.lastSyncDays }}</div>
              <div class="text-caption">Days Since Last Sync</div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Instructions -->
      <v-expansion-panels class="mt-4">
        <v-expansion-panel>
          <v-expansion-panel-title>
            <v-icon left>mdi-information</v-icon>
            How to Use Yunatt Integration
          </v-expansion-panel-title>
          <v-expansion-panel-text>
            <v-list density="compact">
              <v-list-item>
                <template v-slot:prepend>
                  <v-icon color="primary">mdi-numeric-1-circle</v-icon>
                </template>
                <v-list-item-title
                  >Configure API credentials in .env file</v-list-item-title
                >
                <v-list-item-subtitle
                  >Add YUNATT_API_KEY and
                  YUNATT_COMPANY_ID</v-list-item-subtitle
                >
              </v-list-item>
              <v-list-item>
                <template v-slot:prepend>
                  <v-icon color="primary">mdi-numeric-2-circle</v-icon>
                </template>
                <v-list-item-title>Test connection</v-list-item-title>
                <v-list-item-subtitle
                  >Click "Test Connection" to verify setup</v-list-item-subtitle
                >
              </v-list-item>
              <v-list-item>
                <template v-slot:prepend>
                  <v-icon color="primary">mdi-numeric-3-circle</v-icon>
                </template>
                <v-list-item-title>Sync attendance data</v-list-item-title>
                <v-list-item-subtitle
                  >Select date range and click "Sync
                  Attendance"</v-list-item-subtitle
                >
              </v-list-item>
              <v-list-item>
                <template v-slot:prepend>
                  <v-icon color="primary">mdi-numeric-4-circle</v-icon>
                </template>
                <v-list-item-title>Review imported records</v-list-item-title>
                <v-list-item-subtitle
                  >Check attendance list for newly imported
                  data</v-list-item-subtitle
                >
              </v-list-item>
            </v-list>
          </v-expansion-panel-text>
        </v-expansion-panel>
      </v-expansion-panels>
    </v-card-text>

    <!-- Sync Dialog -->
    <v-dialog v-model="showSyncDialog" max-width="600">
      <v-card>
        <v-card-title>
          <v-icon left color="primary">mdi-cloud-sync</v-icon>
          Sync Attendance from Yunatt
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text>
          <v-form ref="syncForm" v-model="formValid">
            <v-row>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="syncData.dateFrom"
                  label="From Date"
                  type="date"
                  :rules="[(v) => !!v || 'Required']"
                  variant="outlined"
                  density="comfortable"
                ></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="syncData.dateTo"
                  label="To Date"
                  type="date"
                  :rules="[
                    (v) => !!v || 'Required',
                    (v) =>
                      !syncData.dateFrom ||
                      v >= syncData.dateFrom ||
                      'Must be after From Date',
                  ]"
                  variant="outlined"
                  density="comfortable"
                ></v-text-field>
              </v-col>
            </v-row>

            <v-alert type="info" variant="tonal" class="mb-4">
              This will fetch attendance records from Yunatt's cloud platform
              for the selected date range.
            </v-alert>

            <v-alert
              v-if="syncResult"
              :type="syncResult.success ? 'success' : 'error'"
              class="mb-0"
            >
              <div class="font-weight-bold">{{ syncResult.message }}</div>
              <div
                v-if="syncResult.imported !== undefined"
                class="text-caption mt-2"
              >
                Imported: {{ syncResult.imported }} | Failed:
                {{ syncResult.failed }}
              </div>
            </v-alert>
          </v-form>
        </v-card-text>

        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn
            variant="text"
            @click="showSyncDialog = false"
            :disabled="syncing"
          >
            Cancel
          </v-btn>
          <v-btn
            color="primary"
            @click="syncFromYunatt"
            :loading="syncing"
            :disabled="!formValid"
          >
            <v-icon left>mdi-download</v-icon>
            Sync Now
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-card>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useSnackbar } from "@/composables/useSnackbar";
import attendanceService from "@/services/attendanceService";
import { format, parseISO, differenceInDays } from "date-fns";

const { showSnackbar } = useSnackbar();

// State
const testingConnection = ref(false);
const connectionStatus = ref(null);
const isConnected = ref(false);
const showSyncDialog = ref(false);
const syncing = ref(false);
const formValid = ref(false);
const lastSync = ref(null);

const stats = ref({
  totalImported: 0,
  employeesLinked: 0,
  lastSyncDays: 0,
});

const syncData = ref({
  dateFrom: format(new Date(), "yyyy-MM-dd"),
  dateTo: format(new Date(), "yyyy-MM-dd"),
});

const syncResult = ref(null);

// Methods
const testConnection = async () => {
  testingConnection.value = true;
  connectionStatus.value = null;

  try {
    const response = await attendanceService.testYuttanConnection();
    connectionStatus.value = response;
    isConnected.value = response.status === "connected";

    if (isConnected.value) {
      showSnackbar("Successfully connected to Yunatt API", "success");
    } else {
      showSnackbar("Failed to connect to Yunatt API", "error");
    }
  } catch (error) {
    connectionStatus.value = {
      status: "error",
      message: error.response?.data?.message || "Connection test failed",
    };
    isConnected.value = false;
    showSnackbar("Connection test failed", "error");
  } finally {
    testingConnection.value = false;
  }
};

const syncFromYunatt = async () => {
  if (!formValid.value) return;

  syncing.value = true;
  syncResult.value = null;

  try {
    const response = await attendanceService.fetchFromYunatt({
      date_from: syncData.value.dateFrom,
      date_to: syncData.value.dateTo,
    });

    syncResult.value = {
      success: true,
      message: response.message,
      imported: response.imported,
      failed: response.failed,
    };

    lastSync.value = new Date().toISOString();
    stats.value.totalImported += response.imported;
    stats.value.lastSyncDays = 0;

    showSnackbar(
      `Successfully imported ${response.imported} records`,
      "success"
    );

    // Close dialog after 2 seconds
    setTimeout(() => {
      showSyncDialog.value = false;
      syncResult.value = null;
    }, 2000);
  } catch (error) {
    syncResult.value = {
      success: false,
      message: error.response?.data?.message || "Sync failed",
    };
    showSnackbar("Failed to sync from Yunatt", "error");
  } finally {
    syncing.value = false;
  }
};

const formatDate = (dateString) => {
  try {
    return format(parseISO(dateString), "MMM dd, yyyy hh:mm a");
  } catch {
    return dateString;
  }
};

// Load stats on mount
onMounted(() => {
  testConnection();

  // You can load actual stats from API if needed
  stats.value = {
    totalImported: 0,
    employeesLinked: 0,
    lastSyncDays: 0,
  };
});
</script>

<style scoped>
.v-expansion-panel-text {
  padding: 16px;
}
</style>
