<template>
  <div>
    <v-row class="mb-4" align="center">
      <v-col>
        <h1 class="text-h4 font-weight-bold">Attendance Management</h1>
      </v-col>
      <v-col cols="auto">
        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          @click="openManualEntryDialog"
          v-if="canManualEntry"
        >
          Manual Entry
        </v-btn>
        <v-btn
          color="success"
          prepend-icon="mdi-file-document"
          @click="openDTRDialog"
          class="ml-2"
        >
          Generate DTR
        </v-btn>
        <v-btn
          color="info"
          prepend-icon="mdi-upload"
          @click="openImportDialog"
          class="ml-2"
          v-if="canManualEntry"
        >
          Import Biometric
        </v-btn>
        <v-btn
          color="warning"
          prepend-icon="mdi-account-alert"
          @click="openMarkAbsentDialog"
          class="ml-2"
          v-if="canManualEntry"
        >
          Mark Absent
        </v-btn>
      </v-col>
    </v-row>

    <v-card>
      <v-tabs v-model="tab" bg-color="primary">
        <v-tab value="list">
          <v-icon start>mdi-view-list</v-icon>
          Attendance List
        </v-tab>
        <v-tab value="calendar">
          <v-icon start>mdi-calendar</v-icon>
          Calendar View
        </v-tab>
        <v-tab value="approvals" v-if="canApprove">
          <v-icon start>mdi-check-circle</v-icon>
          Pending Approvals
          <v-chip
            v-if="pendingCount > 0"
            size="small"
            color="error"
            class="ml-2"
          >
            {{ pendingCount }}
          </v-chip>
        </v-tab>
        <v-tab value="summary">
          <v-icon start>mdi-chart-bar</v-icon>
          Summary
        </v-tab>
        <v-tab value="device" v-if="canManualEntry">
          <v-icon start>mdi-fingerprint</v-icon>
          Device
        </v-tab>
      </v-tabs>

      <v-window v-model="tab">
        <!-- List View -->
        <v-window-item value="list">
          <AttendanceList
            ref="listView"
            @edit="openEditDialog"
            @delete="deleteAttendance"
            @approve="approveAttendance"
            @reject="openRejectDialog"
          />
        </v-window-item>

        <!-- Calendar View -->
        <v-window-item value="calendar">
          <AttendanceCalendar
            @date-click="handleDateClick"
            @record-click="openEditDialog"
          />
        </v-window-item>

        <!-- Pending Approvals -->
        <v-window-item value="approvals" v-if="canApprove">
          <PendingApprovals
            @approve="approveAttendance"
            @reject="openRejectDialog"
            @update-count="updatePendingCount"
          />
        </v-window-item>

        <!-- Summary -->
        <v-window-item value="summary">
          <AttendanceSummary />
        </v-window-item>

        <!-- Device Management -->
        <v-window-item value="device" v-if="canManualEntry">
          <DeviceManagement />
        </v-window-item>
      </v-window>
    </v-card>

    <!-- Manual Entry Dialog -->
    <ManualEntryDialog
      v-model="manualEntryDialog"
      :attendance="selectedAttendance"
      :prefilledDate="prefilledDate"
      @saved="handleSaved"
    />

    <!-- Import Dialog -->
    <ImportBiometricDialog v-model="importDialog" @imported="handleImported" />

    <!-- Mark Absent Dialog -->
    <MarkAbsentDialog v-model="markAbsentDialog" @marked="handleMarkedAbsent" />

    <!-- DTR Generation Dialog -->
    <GenerateDTRDialog v-model="dtrDialog" />

    <!-- Reject Dialog -->
    <RejectDialog
      v-model="rejectDialog"
      :attendance="selectedAttendance"
      @rejected="handleRejected"
    />

    <!-- Delete Confirmation -->
    <v-dialog v-model="deleteDialog" max-width="500">
      <v-card>
        <v-card-title class="text-h5">Confirm Delete</v-card-title>
        <v-card-text>
          Are you sure you want to delete this attendance record?
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn text @click="deleteDialog = false">Cancel</v-btn>
          <v-btn color="error" @click="confirmDelete">Delete</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useToast } from "vue-toastification";
import { useAuthStore } from "@/stores/auth";
import attendanceService from "@/services/attendanceService";
import AttendanceList from "@/components/attendance/AttendanceList.vue";
import AttendanceCalendar from "@/components/attendance/AttendanceCalendar.vue";
import PendingApprovals from "@/components/attendance/PendingApprovals.vue";
import AttendanceSummary from "@/components/attendance/AttendanceSummary.vue";
import DeviceManagement from "@/components/attendance/DeviceManagement.vue";
import ManualEntryDialog from "@/components/attendance/ManualEntryDialog.vue";
import ImportBiometricDialog from "@/components/attendance/ImportBiometricDialog.vue";
import MarkAbsentDialog from "@/components/attendance/MarkAbsentDialog.vue";
import GenerateDTRDialog from "@/components/attendance/GenerateDTRDialog.vue";
import RejectDialog from "@/components/attendance/RejectDialog.vue";

const toast = useToast();
const authStore = useAuthStore();
const tab = ref("list");
const listView = ref(null);

// User permissions
const canManualEntry = computed(() =>
  ["admin", "accountant"].includes(authStore.userRole)
);
const canApprove = computed(() =>
  ["admin", "accountant", "manager"].includes(authStore.userRole)
);

// Dialogs
const manualEntryDialog = ref(false);
const importDialog = ref(false);
const markAbsentDialog = ref(false);
const dtrDialog = ref(false);
const rejectDialog = ref(false);
const deleteDialog = ref(false);

// State
const selectedAttendance = ref(null);
const attendanceToDelete = ref(null);
const pendingCount = ref(0);
const prefilledDate = ref(null);

// Dialog handlers
const openManualEntryDialog = () => {
  selectedAttendance.value = null;
  prefilledDate.value = null;
  manualEntryDialog.value = true;
};

const openEditDialog = (attendance) => {
  selectedAttendance.value = attendance;
  prefilledDate.value = null;
  manualEntryDialog.value = true;
};

const openImportDialog = () => {
  importDialog.value = true;
};

const openMarkAbsentDialog = () => {
  markAbsentDialog.value = true;
};

const openDTRDialog = () => {
  dtrDialog.value = true;
};

const openRejectDialog = (attendance) => {
  selectedAttendance.value = attendance;
  rejectDialog.value = true;
};

const handleDateClick = (date) => {
  prefilledDate.value = date;
  selectedAttendance.value = null;
  manualEntryDialog.value = true;
};

// CRUD operations
const handleSaved = () => {
  toast.success("Attendance saved successfully");
  manualEntryDialog.value = false;
  selectedAttendance.value = null;
  refreshData();
};

const handleImported = (result) => {
  toast.success(
    `Imported ${result.imported} records. Failed: ${result.failed}`
  );
  importDialog.value = false;
  refreshData();
};

const handleMarkedAbsent = (result) => {
  toast.success(`Marked ${result.marked} employees as absent`);
  markAbsentDialog.value = false;
  refreshData();
};

const approveAttendance = async (attendance) => {
  try {
    await attendanceService.approve(attendance.id);
    toast.success("Attendance approved");
    refreshData();
  } catch (error) {
    toast.error("Failed to approve attendance");
  }
};

const handleRejected = () => {
  toast.success("Attendance rejected");
  rejectDialog.value = false;
  selectedAttendance.value = null;
  refreshData();
};

const deleteAttendance = (attendance) => {
  attendanceToDelete.value = attendance;
  deleteDialog.value = true;
};

const confirmDelete = async () => {
  try {
    await attendanceService.deleteAttendance(attendanceToDelete.value.id);
    toast.success("Attendance deleted");
    deleteDialog.value = false;
    attendanceToDelete.value = null;
    refreshData();
  } catch (error) {
    toast.error("Failed to delete attendance");
  }
};

const updatePendingCount = (count) => {
  pendingCount.value = count;
};

const refreshData = () => {
  // Refresh all tabs to ensure consistency
  if (listView.value) {
    listView.value.loadAttendance();
  }
  // Trigger refresh for calendar and other components
  window.dispatchEvent(new CustomEvent("attendance-data-changed"));
};

// Load pending count on mount
onMounted(async () => {
  if (canApprove.value) {
    try {
      const response = await attendanceService.getPendingApprovals();
      pendingCount.value = response.total || 0;
    } catch (error) {
      console.error("Failed to load pending count:", error);
    }
  }
});
</script>

<style scoped>
.v-window {
  min-height: 600px;
}
</style>
