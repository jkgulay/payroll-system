<template>
  <v-card-text>
    <v-row>
      <!-- Device Info -->
      <v-col cols="12" md="6">
        <v-card>
          <v-card-title class="text-h6">Device Information</v-card-title>
          <v-divider></v-divider>
          <v-card-text>
            <v-btn
              color="primary"
              block
              @click="loadDeviceInfo"
              :loading="loadingInfo"
              class="mb-4"
            >
              <v-icon left>mdi-refresh</v-icon>
              Refresh Device Info
            </v-btn>

            <v-alert v-if="deviceError" type="error" class="mb-4">
              {{ deviceError }}
            </v-alert>

            <v-list v-if="deviceInfo" density="compact">
              <v-list-item>
                <v-list-item-title>Platform</v-list-item-title>
                <v-list-item-subtitle>{{
                  deviceInfo.platform || "N/A"
                }}</v-list-item-subtitle>
              </v-list-item>
              <v-list-item>
                <v-list-item-title>Firmware Version</v-list-item-title>
                <v-list-item-subtitle>{{
                  deviceInfo.firmware_version || "N/A"
                }}</v-list-item-subtitle>
              </v-list-item>
              <v-list-item>
                <v-list-item-title>Serial Number</v-list-item-title>
                <v-list-item-subtitle>{{
                  deviceInfo.serial_number || "N/A"
                }}</v-list-item-subtitle>
              </v-list-item>
              <v-list-item>
                <v-list-item-title>Device Name</v-list-item-title>
                <v-list-item-subtitle>{{
                  deviceInfo.device_name || "N/A"
                }}</v-list-item-subtitle>
              </v-list-item>
              <v-list-item>
                <v-list-item-title>Users Count</v-list-item-title>
                <v-list-item-subtitle>{{
                  deviceInfo.user_count || 0
                }}</v-list-item-subtitle>
              </v-list-item>
              <v-list-item>
                <v-list-item-title>Attendance Records</v-list-item-title>
                <v-list-item-subtitle>{{
                  deviceInfo.attendance_count || 0
                }}</v-list-item-subtitle>
              </v-list-item>
            </v-list>

            <v-alert v-else type="info" variant="tonal">
              Click "Refresh Device Info" to check device status
            </v-alert>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Device Actions -->
      <v-col cols="12" md="6">
        <v-card>
          <v-card-title class="text-h6">Device Actions</v-card-title>
          <v-divider></v-divider>
          <v-card-text>
            <!-- Sync Employees -->
            <v-card class="mb-4" variant="tonal">
              <v-card-title class="text-subtitle-1"
                >Sync Employees to Device</v-card-title
              >
              <v-card-text>
                <p class="text-caption mb-3">
                  Upload all employee biometric IDs to the device
                </p>
                <v-btn
                  color="primary"
                  block
                  @click="confirmSyncEmployees"
                  :loading="syncingEmployees"
                >
                  <v-icon left>mdi-sync</v-icon>
                  Sync Employees
                </v-btn>
              </v-card-text>
            </v-card>

            <!-- Fetch from Device -->
            <v-card class="mb-4" variant="tonal">
              <v-card-title class="text-subtitle-1"
                >Fetch Attendance from Device</v-card-title
              >
              <v-card-text>
                <v-row>
                  <v-col cols="6">
                    <v-text-field
                      v-model="fetchForm.date_from"
                      label="From"
                      type="date"
                      variant="outlined"
                      density="compact"
                    ></v-text-field>
                  </v-col>
                  <v-col cols="6">
                    <v-text-field
                      v-model="fetchForm.date_to"
                      label="To"
                      type="date"
                      variant="outlined"
                      density="compact"
                    ></v-text-field>
                  </v-col>
                  <v-col cols="12">
                    <v-btn
                      color="success"
                      block
                      @click="fetchFromDevice"
                      :loading="fetching"
                    >
                      <v-icon left>mdi-download</v-icon>
                      Fetch Attendance
                    </v-btn>
                  </v-col>
                </v-row>
              </v-card-text>
            </v-card>

            <!-- Clear Device Logs -->
            <v-card variant="tonal" color="error">
              <v-card-title class="text-subtitle-1"
                >Clear Device Logs</v-card-title
              >
              <v-card-text>
                <p class="text-caption mb-3">
                  ⚠️ This will permanently delete all attendance records from
                  the device
                </p>
                <v-btn
                  color="error"
                  block
                  @click="confirmClearLogs"
                  :loading="clearingLogs"
                >
                  <v-icon left>mdi-delete-sweep</v-icon>
                  Clear Device Logs
                </v-btn>
              </v-card-text>
            </v-card>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Last Sync Info -->
      <v-col cols="12" v-if="lastSync">
        <v-alert type="success" variant="tonal">
          <v-alert-title>Last Sync</v-alert-title>
          {{ lastSync }}
        </v-alert>
      </v-col>
    </v-row>

    <!-- Confirmation Dialogs -->
    <v-dialog v-model="syncDialog" max-width="400">
      <v-card>
        <v-card-title>Confirm Sync</v-card-title>
        <v-card-text>
          Are you sure you want to sync all employees to the device? This may
          take several minutes.
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn @click="syncDialog = false">Cancel</v-btn>
          <v-btn color="primary" @click="syncEmployees">Confirm</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="clearDialog" max-width="400">
      <v-card>
        <v-card-title class="text-error">⚠️ Confirm Clear</v-card-title>
        <v-card-text>
          This will permanently delete all attendance records from the device.
          This action cannot be undone. Are you sure?
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn @click="clearDialog = false">Cancel</v-btn>
          <v-btn color="error" @click="clearLogs">Yes, Clear All</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-card-text>
</template>

<script setup>
import { ref, reactive, onMounted } from "vue";
import attendanceService from "@/services/attendanceService";
import { useToast } from "vue-toastification";

const toast = useToast();

const loadingInfo = ref(false);
const syncingEmployees = ref(false);
const fetching = ref(false);
const clearingLogs = ref(false);
const deviceInfo = ref(null);
const deviceError = ref(null);
const lastSync = ref(null);
const syncDialog = ref(false);
const clearDialog = ref(false);

const fetchForm = reactive({
  date_from: new Date(Date.now() - 7 * 24 * 60 * 60 * 1000)
    .toISOString()
    .split("T")[0],
  date_to: new Date().toISOString().split("T")[0],
});

const loadDeviceInfo = async () => {
  loadingInfo.value = true;
  deviceError.value = null;
  try {
    deviceInfo.value = await attendanceService.getDeviceInfo();
  } catch (error) {
    deviceError.value =
      error.response?.data?.message ||
      error.message ||
      "Failed to get device info. Make sure the device is connected and the SDK is installed.";
    deviceInfo.value = null;
  } finally {
    loadingInfo.value = false;
  }
};

const confirmSyncEmployees = () => {
  syncDialog.value = true;
};

const syncEmployees = async () => {
  syncDialog.value = false;
  syncingEmployees.value = true;
  try {
    const result = await attendanceService.syncEmployees();
    toast.success(`Synced ${result.synced} employees to device`);
    lastSync.value = `Synced ${
      result.synced
    } employees at ${new Date().toLocaleString()}`;
    await loadDeviceInfo();
  } catch (error) {
    toast.error(error.response?.data?.message || "Failed to sync employees");
  } finally {
    syncingEmployees.value = false;
  }
};

const fetchFromDevice = async () => {
  fetching.value = true;
  try {
    const result = await attendanceService.fetchFromDevice(fetchForm);
    toast.success(
      `Fetched ${result.imported} records. Failed: ${result.failed || 0}`
    );
    lastSync.value = `Fetched ${
      result.imported
    } records at ${new Date().toLocaleString()}`;
  } catch (error) {
    toast.error(error.response?.data?.message || "Failed to fetch from device");
  } finally {
    fetching.value = false;
  }
};

const confirmClearLogs = () => {
  clearDialog.value = true;
};

const clearLogs = async () => {
  clearDialog.value = false;
  clearingLogs.value = true;
  try {
    await attendanceService.clearDeviceLogs();
    toast.success("Device logs cleared successfully");
    lastSync.value = `Cleared device logs at ${new Date().toLocaleString()}`;
    await loadDeviceInfo();
  } catch (error) {
    toast.error(error.response?.data?.message || "Failed to clear device logs");
  } finally {
    clearingLogs.value = false;
  }
};

onMounted(() => {
  loadDeviceInfo();
});
</script>
