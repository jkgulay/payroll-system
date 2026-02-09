<template>
  <div class="attendance-page">
    <!-- Modern Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="page-title-section">
          <div class="page-icon-badge">
            <v-icon size="20">mdi-clock-check-outline</v-icon>
          </div>
          <div>
            <h1 class="page-title">Attendance Management</h1>
            <p class="page-subtitle">
              Track and manage employee attendance records
            </p>
          </div>
        </div>
        <div class="action-buttons">
          <button
            v-if="canManualEntry"
            class="action-btn action-btn-primary"
            @click="openManualEntryDialog"
          >
            <v-icon size="20">mdi-plus</v-icon>
            <span>Manual Entry</span>
          </button>
          <button
            class="action-btn action-btn-secondary"
            @click="openDTRDialog"
          >
            <v-icon size="20">mdi-file-document</v-icon>
            <span>Generate DTR</span>
          </button>
          <button
            v-if="canManualEntry"
            class="action-btn action-btn-secondary"
            @click="openImportDialog"
          >
            <v-icon size="20">mdi-upload</v-icon>
            <span>Import</span>
          </button>
          <button
            v-if="canManualEntry"
            class="action-btn action-btn-secondary"
            @click="openMarkAbsentDialog"
          >
            <v-icon size="20">mdi-account-alert</v-icon>
            <span>Mark Absent</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Modern Tab Navigation -->
    <div class="modern-card tab-container">
      <div class="modern-tabs">
        <button
          class="modern-tab"
          :class="{ active: tab === 'list' }"
          @click="tab = 'list'"
        >
          <v-icon size="20">mdi-view-list</v-icon>
          <span>Attendance List</span>
        </button>

        <button
          class="modern-tab"
          :class="{ active: tab === 'missing' }"
          @click="tab = 'missing'"
        >
          <v-icon size="20">mdi-alert-circle-outline</v-icon>
          <span>Missing Attendance</span>
        </button>
        <button
          v-if="canApprove"
          class="modern-tab"
          :class="{ active: tab === 'approvals' }"
          @click="tab = 'approvals'"
        >
          <v-icon size="20">mdi-check-circle</v-icon>
          <span>Pending Approvals</span>
          <div v-if="pendingCount > 0" class="tab-badge">
            {{ pendingCount }}
          </div>
        </button>
        <button
          class="modern-tab"
          :class="{ active: tab === 'summary' }"
          @click="tab = 'summary'"
        >
          <v-icon size="20">mdi-chart-bar</v-icon>
          <span>Summary</span>
        </button>

        <button
          v-if="canManualEntry"
          class="modern-tab"
          :class="{ active: tab === 'device' }"
          @click="tab = 'device'"
        >
          <v-icon size="20">mdi-fingerprint</v-icon>
          <span>Device</span>
        </button>
      </div>

      <v-window v-model="tab">
        <!-- List View -->
        <v-window-item value="list">
          <div class="tab-content">
            <AttendanceList
              ref="listView"
              @edit="openEditDialog"
              @delete="deleteAttendance"
              @approve="approveAttendance"
              @reject="openRejectDialog"
            />
          </div>
        </v-window-item>

        <!-- Pending Approvals -->
        <v-window-item value="approvals" v-if="canApprove">
          <div class="tab-content">
            <PendingApprovals
              @approve="approveAttendance"
              @reject="openRejectDialog"
              @update-count="updatePendingCount"
            />
          </div>
        </v-window-item>

        <!-- Summary -->
        <v-window-item value="summary">
          <div class="tab-content">
            <AttendanceSummary />
          </div>
        </v-window-item>

        <!-- Missing Attendance -->
        <v-window-item value="missing">
          <div class="tab-content">
            <MissingAttendance @edit-attendance="openEditDialog" />
          </div>
        </v-window-item>

        <!-- Device Management -->
        <v-window-item value="device" v-if="canManualEntry">
          <div class="tab-content">
            <DeviceManagement />
          </div>
        </v-window-item>
      </v-window>
    </div>

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

    <!-- Delete Confirmation with Modern Styling -->
    <v-dialog v-model="deleteDialog" max-width="500">
      <v-card class="modern-dialog">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper danger">
            <v-icon size="24">mdi-alert-circle</v-icon>
          </div>
          <div>
            <div class="dialog-title">Confirm Delete</div>
            <div class="dialog-subtitle">This action cannot be undone</div>
          </div>
        </v-card-title>
        <v-card-text class="dialog-content">
          Are you sure you want to delete this attendance record?
        </v-card-text>
        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <button
            class="dialog-btn dialog-btn-cancel"
            @click="deleteDialog = false"
          >
            Cancel
          </button>
          <button class="dialog-btn dialog-btn-danger" @click="confirmDelete">
            <v-icon size="18">mdi-delete</v-icon>
            Delete
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRoute } from "vue-router";
import { useToast } from "vue-toastification";
import { useAuthStore } from "@/stores/auth";
import attendanceService from "@/services/attendanceService";
import AttendanceList from "@/components/attendance/AttendanceList.vue";
import PendingApprovals from "@/components/attendance/PendingApprovals.vue";
import AttendanceSummary from "@/components/attendance/AttendanceSummary.vue";
import MissingAttendance from "@/components/attendance/MissingAttendance.vue";
import DeviceManagement from "@/components/attendance/DeviceManagement.vue";
import ManualEntryDialog from "@/components/attendance/ManualEntryDialog.vue";
import ImportBiometricDialog from "@/components/attendance/ImportBiometricDialog.vue";
import MarkAbsentDialog from "@/components/attendance/MarkAbsentDialog.vue";
import GenerateDTRDialog from "@/components/attendance/GenerateDTRDialog.vue";
import RejectDialog from "@/components/attendance/RejectDialog.vue";

const toast = useToast();
const authStore = useAuthStore();
const route = useRoute();
const tab = ref("list");
const listView = ref(null);

// User permissions
const canManualEntry = computed(() =>
  ["admin", "hr"].includes(authStore.userRole),
);
const canApprove = computed(() =>
  ["admin", "hr", "manager"].includes(authStore.userRole),
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

const openEditDialog = (data) => {
  // Handle both old format (just attendance object) and new format (object with attendance and date)
  if (data && data.attendance) {
    // From Missing Attendance tab - has both attendance and selected date
    selectedAttendance.value = data.attendance;
    prefilledDate.value = data.date;
  } else {
    // From other tabs - just attendance object
    selectedAttendance.value = data;
    prefilledDate.value = data?.attendance_date || null;
  }
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

// CRUD operations
const handleSaved = () => {
  toast.success("Attendance saved successfully");
  manualEntryDialog.value = false;
  selectedAttendance.value = null;
  refreshData();
};

const handleImported = (result) => {
  toast.success(
    `Imported ${result.imported} records. Failed: ${result.failed}`,
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
    const message =
      error.response?.data?.message || "Failed to approve attendance";
    toast.error(message);
    console.error("Approval error:", error.response?.data);
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
  // Check for query parameters from dashboard
  if (route.query.tab) {
    tab.value = route.query.tab;
  }

  // If date filters are provided, pass them to the list view
  if (route.query.date_from && route.query.date_to && listView.value) {
    setTimeout(() => {
      if (listView.value) {
        listView.value.loadAttendance();
      }
    }, 100);
  }

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

<style scoped lang="scss">
.attendance-page {
  max-width: 1600px;
  margin: 0 auto;
}

// Modern Page Header
.page-header {
  margin-bottom: 28px;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 24px;
  flex-wrap: wrap;

  @media (max-width: 960px) {
    flex-direction: column;
    align-items: flex-start;
  }
}

.page-title-section {
  display: flex;
  align-items: center;
  gap: 16px;
  flex: 1;
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

// Action Buttons
.action-buttons {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;

  @media (max-width: 960px) {
    width: 100%;
  }
}

.action-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 20px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
  white-space: nowrap;

  .v-icon {
    flex-shrink: 0;
  }

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(237, 152, 95, 0.25);
  }

  &.action-btn-primary {
    background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);

    .v-icon {
      color: #ffffff !important;
    }
  }

  &.action-btn-secondary {
    background: rgba(237, 152, 95, 0.1);
    color: #ed985f;
    border: 1px solid rgba(237, 152, 95, 0.2);

    .v-icon {
      color: #ed985f !important;
    }

    &:hover {
      background: rgba(237, 152, 95, 0.15);
      border-color: rgba(237, 152, 95, 0.3);
    }
  }
}

// Modern Card & Tabs
.modern-card {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  overflow: hidden;
}

.tab-container {
  margin-bottom: 24px;
}

.modern-tabs {
  display: flex;
  gap: 4px;
  padding: 8px;
  background: rgba(0, 31, 61, 0.02);
  border-bottom: 1px solid rgba(0, 31, 61, 0.08);
  overflow-x: auto;

  &::-webkit-scrollbar {
    height: 4px;
  }

  &::-webkit-scrollbar-track {
    background: rgba(0, 31, 61, 0.04);
  }

  &::-webkit-scrollbar-thumb {
    background: rgba(237, 152, 95, 0.3);
    border-radius: 2px;
  }
}

.modern-tab {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 20px;
  background: transparent;
  border: none;
  border-radius: 10px;
  color: rgba(0, 31, 61, 0.7);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  white-space: nowrap;
  position: relative;

  .v-icon {
    color: rgba(0, 31, 61, 0.5) !important;
    transition: color 0.3s ease;
  }

  &:hover {
    background: rgba(237, 152, 95, 0.08);
    color: #001f3d;

    .v-icon {
      color: #ed985f !important;
    }
  }

  &.active {
    background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);

    .v-icon {
      color: #ffffff !important;
    }

    .tab-badge {
      background: #ffffff;
      color: #ed985f;
    }
  }
}

.tab-badge {
  min-width: 22px;
  height: 22px;
  padding: 0 6px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: #ffffff;
  border-radius: 11px;
  font-size: 11px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 6px rgba(237, 152, 95, 0.3);
}

// Tab Content
.tab-content {
  padding: 24px;
  min-height: 500px;
}

// Modern Dialog Styling
.modern-dialog {
  border-radius: 16px !important;
  overflow: hidden;

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
    flex-shrink: 0;

    &.danger {
      background: linear-gradient(
        135deg,
        rgba(244, 67, 54, 0.15) 0%,
        rgba(244, 67, 54, 0.1) 100%
      );

      .v-icon {
        color: #f44336 !important;
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
    font-size: 15px;
    color: rgba(0, 31, 61, 0.8);
    line-height: 1.6;
  }

  .dialog-actions {
    padding: 16px 24px !important;
    background: rgba(0, 31, 61, 0.02);
    border-top: 1px solid rgba(0, 31, 61, 0.08);
    gap: 10px;
  }
}

.dialog-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;

  .v-icon {
    flex-shrink: 0;
  }

  &:hover {
    transform: translateY(-1px);
  }

  &.dialog-btn-cancel {
    background: rgba(0, 31, 61, 0.06);
    color: rgba(0, 31, 61, 0.8);

    &:hover {
      background: rgba(0, 31, 61, 0.1);
    }
  }

  &.dialog-btn-danger {
    background: linear-gradient(135deg, #f44336 0%, #e53935 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(244, 67, 54, 0.3);

    .v-icon {
      color: #ffffff !important;
    }

    &:hover {
      box-shadow: 0 4px 12px rgba(244, 67, 54, 0.4);
    }
  }
}

// Override Vuetify window
:deep(.v-window) {
  background: transparent !important;
}

:deep(.v-window-item) {
  background: transparent !important;
}
</style>
