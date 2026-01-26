<template>
  <div class="leave-approval-page">
    <!-- Modern Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="page-title-section">
          <div class="page-icon-badge">
            <v-icon size="22">mdi-calendar-check</v-icon>
          </div>
          <div>
            <h1 class="page-title">Leave Approval Management</h1>
            <p class="page-subtitle">
              Review and approve employee leave requests
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Modern Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon pending">
          <v-icon size="20">mdi-clock-outline</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Pending</div>
          <div class="stat-value">{{ pendingCount }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon approved">
          <v-icon size="20">mdi-check-circle</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Approved</div>
          <div class="stat-value">{{ approvedCount }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon rejected">
          <v-icon size="20">mdi-close-circle</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Rejected</div>
          <div class="stat-value">{{ rejectedCount }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon total">
          <v-icon size="20">mdi-file-document-multiple</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Total Requests</div>
          <div class="stat-value">{{ totalCount }}</div>
        </div>
      </div>
    </div>

    <!-- Leave Requests List -->
    <div class="modern-card">
      <div class="filters-section">
        <v-row align="center" class="mb-0">
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.status"
              :items="statusOptions"
              label="Status"
              variant="outlined"
              density="comfortable"
              hide-details
              clearable
              @update:model-value="loadLeaves"
            ></v-select>
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.leave_type_id"
              :items="leaveTypes"
              item-title="name"
              item-value="id"
              label="Leave Type"
              variant="outlined"
              density="comfortable"
              hide-details
              clearable
              @update:model-value="loadLeaves"
            ></v-select>
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field
              v-model="search"
              label="Search by employee"
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="comfortable"
              hide-details
              clearable
            ></v-text-field>
          </v-col>
          <v-spacer></v-spacer>
          <v-col cols="auto">
            <v-btn
              color="#ED985F"
              variant="tonal"
              icon="mdi-refresh"
              @click="loadLeaves"
              :loading="loading"
              title="Refresh"
            ></v-btn>
          </v-col>
        </v-row>
      </div>

      <div class="table-section">
        <v-data-table
          :headers="headers"
          :items="leaves"
          :loading="loading"
          :search="search"
          item-value="id"
          class="elevation-1"
        >
          <template #item.employee="{ item }">
            <div class="d-flex align-center py-2">
              <div>
                <div class="font-weight-bold">
                  {{ item.employee?.first_name }} {{ item.employee?.last_name }}
                </div>
                <div class="text-caption text-grey">
                  {{ item.employee?.employee_number }}
                </div>
              </div>
            </div>
          </template>

          <template #item.leave_type="{ item }">
            <v-chip size="small" color="primary">
              {{ item.leave_type?.name }}
            </v-chip>
          </template>

          <template #item.leave_date_from="{ item }">
            {{ formatDate(item.leave_date_from) }}
          </template>

          <template #item.leave_date_to="{ item }">
            {{ formatDate(item.leave_date_to) }}
          </template>

          <template #item.number_of_days="{ item }">
            <v-chip size="small"> {{ item.number_of_days }} day(s) </v-chip>
          </template>

          <template #item.status="{ item }">
            <v-chip size="small" :color="getStatusColor(item.status)">
              {{ item.status.toUpperCase() }}
            </v-chip>
          </template>

          <template #item.actions="{ item }">
            <v-btn
              icon="mdi-eye"
              size="small"
              variant="text"
              @click="viewLeave(item)"
            ></v-btn>
            <v-btn
              v-if="item.status === 'pending'"
              icon="mdi-check"
              size="small"
              variant="text"
              color="success"
              @click="openApproveDialog(item)"
            ></v-btn>
            <v-btn
              v-if="item.status === 'pending'"
              icon="mdi-close"
              size="small"
              variant="text"
              color="error"
              @click="openRejectDialog(item)"
            ></v-btn>
          </template>
        </v-data-table>
      </div>
    </div>

    <!-- View Leave Dialog -->
    <v-dialog v-model="showViewDialog" max-width="700px">
      <v-card v-if="selectedLeave" class="modern-dialog">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper info">
            <v-icon size="24">mdi-calendar-clock</v-icon>
          </div>
          <div>
            <div class="dialog-title">Leave Request Details</div>
            <div class="dialog-subtitle">Review employee leave request</div>
          </div>
        </v-card-title>

        <v-card-text class="dialog-content">
          <div class="detail-row">
            <div class="detail-label">Status</div>
            <div class="detail-value">
              <v-chip
                :color="getStatusColor(selectedLeave.status)"
                variant="flat"
                size="small"
              >
                {{ selectedLeave.status.toUpperCase() }}
              </v-chip>
            </div>
          </div>

          <div class="detail-row">
            <div class="detail-label">Employee</div>
            <div class="detail-value">
              <div class="employee-info">
                <div class="employee-name">
                  {{ selectedLeave.employee?.first_name }}
                  {{ selectedLeave.employee?.last_name }}
                </div>
                <div class="employee-number">
                  {{ selectedLeave.employee?.employee_number }}
                </div>
              </div>
            </div>
          </div>

          <div class="detail-row">
            <div class="detail-label">Position</div>
            <div class="detail-value">
              {{ selectedLeave.employee?.position || "N/A" }}
            </div>
          </div>

          <div class="detail-row">
            <div class="detail-label">Leave Type</div>
            <div class="detail-value">{{ selectedLeave.leave_type?.name }}</div>
          </div>

          <div class="detail-row">
            <div class="detail-label">Number of Days</div>
            <div class="detail-value">
              {{ selectedLeave.number_of_days }} day{{
                selectedLeave.number_of_days > 1 ? "s" : ""
              }}
            </div>
          </div>

          <div class="detail-row">
            <div class="detail-label">From Date</div>
            <div class="detail-value">
              {{ formatDate(selectedLeave.leave_date_from) }}
            </div>
          </div>

          <div class="detail-row">
            <div class="detail-label">To Date</div>
            <div class="detail-value">
              {{ formatDate(selectedLeave.leave_date_to) }}
            </div>
          </div>

          <div class="detail-row full-width">
            <div class="detail-label">Reason</div>
            <div class="detail-value">{{ selectedLeave.reason }}</div>
          </div>

          <div v-if="selectedLeave.approved_by" class="detail-row full-width">
            <div class="detail-label">
              {{
                selectedLeave.status === "approved" ? "Approved" : "Rejected"
              }}
              By
            </div>
            <div class="detail-value">
              {{ selectedLeave.approved_by?.name || "N/A" }} on
              {{ formatDateTime(selectedLeave.approved_at) }}
            </div>
          </div>

          <v-alert
            v-if="
              selectedLeave.rejection_reason &&
              selectedLeave.status === 'rejected'
            "
            type="error"
            variant="tonal"
            density="compact"
            class="mt-3"
          >
            <div class="alert-title">Rejection Reason</div>
            <div>{{ selectedLeave.rejection_reason }}</div>
          </v-alert>
        </v-card-text>

        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <button
            v-if="selectedLeave.status === 'pending'"
            class="dialog-btn dialog-btn-success"
            @click="openApproveDialog(selectedLeave)"
          >
            <v-icon size="18">mdi-check</v-icon>
            Approve
          </button>
          <button
            v-if="selectedLeave.status === 'pending'"
            class="dialog-btn dialog-btn-danger"
            @click="openRejectDialog(selectedLeave)"
          >
            <v-icon size="18">mdi-close</v-icon>
            Reject
          </button>
          <button
            class="dialog-btn dialog-btn-cancel"
            @click="showViewDialog = false"
          >
            Close
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Approve Dialog -->
    <v-dialog v-model="showApproveDialog" max-width="550px" persistent>
      <v-card class="modern-dialog">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper success">
            <v-icon size="24">mdi-check-circle</v-icon>
          </div>
          <div>
            <div class="dialog-title">Approve Leave Request</div>
            <div class="dialog-subtitle">Confirm leave approval</div>
          </div>
          <v-spacer></v-spacer>
          <button class="close-btn" @click="closeApproveDialog">
            <v-icon size="20">mdi-close</v-icon>
          </button>
        </v-card-title>

        <v-card-text class="dialog-content">
          <div class="confirmation-message">
            Are you sure you want to approve this leave request for
            <strong class="employee-highlight">
              {{ selectedLeave?.employee?.first_name }}
              {{ selectedLeave?.employee?.last_name }} </strong
            >?
          </div>

          <div class="info-box success-box">
            <div class="info-row">
              <v-icon size="16" color="#10b981">mdi-calendar-text</v-icon>
              <span class="info-label">Leave Type:</span>
              <span class="info-value">{{
                selectedLeave?.leave_type?.name
              }}</span>
            </div>
            <div class="info-row">
              <v-icon size="16" color="#10b981">mdi-calendar-range</v-icon>
              <span class="info-label">Duration:</span>
              <span class="info-value"
                >{{ selectedLeave?.number_of_days }} day{{
                  selectedLeave?.number_of_days > 1 ? "s" : ""
                }}</span
              >
            </div>
            <div class="info-row">
              <v-icon size="16" color="#10b981">mdi-calendar-clock</v-icon>
              <span class="info-label">Dates:</span>
              <span class="info-value">
                {{ formatDate(selectedLeave?.leave_date_from) }} to
                {{ formatDate(selectedLeave?.leave_date_to) }}
              </span>
            </div>
          </div>

          <div class="form-field-label">
            <v-icon size="16" color="#10b981">mdi-note-text</v-icon>
            <span>Remarks (Optional)</span>
          </div>
          <v-textarea
            v-model="approveData.remarks"
            placeholder="Add any remarks or notes (optional)"
            rows="3"
            variant="outlined"
            density="comfortable"
            color="#10b981"
            hide-details
          ></v-textarea>
        </v-card-text>

        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <button
            class="dialog-btn dialog-btn-cancel"
            @click="closeApproveDialog"
          >
            <v-icon size="18">mdi-close</v-icon>
            Cancel
          </button>
          <button
            class="dialog-btn dialog-btn-success"
            :disabled="approving"
            @click="confirmApprove"
          >
            <v-progress-circular
              v-if="approving"
              size="18"
              width="2"
              indeterminate
              color="white"
            ></v-progress-circular>
            <v-icon v-else size="18">mdi-check</v-icon>
            Approve
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Reject Dialog -->
    <v-dialog v-model="showRejectDialog" max-width="550px" persistent>
      <v-card class="modern-dialog">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper danger">
            <v-icon size="24">mdi-close-circle</v-icon>
          </div>
          <div>
            <div class="dialog-title">Reject Leave Request</div>
            <div class="dialog-subtitle">Provide rejection reason</div>
          </div>
          <v-spacer></v-spacer>
          <button class="close-btn" @click="closeRejectDialog">
            <v-icon size="20">mdi-close</v-icon>
          </button>
        </v-card-title>

        <v-card-text class="dialog-content">
          <div class="confirmation-message">
            Are you sure you want to reject this leave request for
            <strong class="employee-highlight">
              {{ selectedLeave?.employee?.first_name }}
              {{ selectedLeave?.employee?.last_name }} </strong
            >?
          </div>

          <v-alert
            type="warning"
            variant="tonal"
            density="compact"
            class="rejection-warning"
          >
            <div class="d-flex align-center">
              <v-icon size="18" class="mr-2">mdi-alert</v-icon>
              <span>Please provide a clear reason for rejection</span>
            </div>
          </v-alert>

          <div class="form-field-label">
            <v-icon size="16" color="#ef4444">mdi-text-box</v-icon>
            <span>Rejection Reason <span class="required">*</span></span>
          </div>
          <v-textarea
            v-model="rejectData.rejection_reason"
            placeholder="Explain why this leave request is being rejected"
            rows="3"
            variant="outlined"
            density="comfortable"
            color="#ef4444"
            hide-details
          ></v-textarea>
        </v-card-text>

        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <button
            class="dialog-btn dialog-btn-cancel"
            @click="closeRejectDialog"
          >
            <v-icon size="18">mdi-close</v-icon>
            Cancel
          </button>
          <button
            class="dialog-btn dialog-btn-danger"
            :disabled="rejecting || !rejectData.rejection_reason"
            @click="confirmReject"
          >
            <v-progress-circular
              v-if="rejecting"
              size="18"
              width="2"
              indeterminate
              color="white"
            ></v-progress-circular>
            <v-icon v-else size="18">mdi-close-circle</v-icon>
            Reject
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Snackbar for notifications -->
    <v-snackbar v-model="snackbar.show" :color="snackbar.color" :timeout="3000">
      {{ snackbar.message }}
    </v-snackbar>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { format, parseISO } from "date-fns";
import leaveService from "@/services/leaveService";

// State
const loading = ref(false);
const approving = ref(false);
const rejecting = ref(false);
const leaves = ref([]);
const leaveTypes = ref([]);
const search = ref("");
const showViewDialog = ref(false);
const showApproveDialog = ref(false);
const showRejectDialog = ref(false);
const selectedLeave = ref(null);

const filters = ref({
  status: null,
  leave_type_id: null,
});

const approveData = ref({
  remarks: "",
});

const rejectData = ref({
  rejection_reason: "",
});

const snackbar = ref({
  show: false,
  message: "",
  color: "success",
});

// Computed
const pendingCount = computed(() => {
  return leaves.value.filter((l) => l.status === "pending").length;
});

const approvedCount = computed(() => {
  return leaves.value.filter((l) => l.status === "approved").length;
});

const rejectedCount = computed(() => {
  return leaves.value.filter((l) => l.status === "rejected").length;
});

const totalCount = computed(() => {
  return leaves.value.length;
});

// Table headers
const headers = [
  { title: "Employee", key: "employee", sortable: true },
  { title: "Leave Type", key: "leave_type", sortable: true },
  { title: "From Date", key: "leave_date_from", sortable: true },
  { title: "To Date", key: "leave_date_to", sortable: true },
  { title: "Days", key: "number_of_days", sortable: true, align: "center" },
  { title: "Status", key: "status", sortable: true },
  { title: "Actions", key: "actions", sortable: false, align: "center" },
];

const statusOptions = [
  { title: "All", value: null },
  { title: "Pending", value: "pending" },
  { title: "Approved", value: "approved" },
  { title: "Rejected", value: "rejected" },
];

// Validation rules
const rules = {
  required: (v) => !!v || "This field is required",
};

// Methods
const loadLeaves = async () => {
  try {
    loading.value = true;
    const params = {};
    if (filters.value.status) {
      params.status = filters.value.status;
    }
    if (filters.value.leave_type_id) {
      params.leave_type_id = filters.value.leave_type_id;
    }
    const response = await leaveService.getLeaves(params);
    leaves.value = response.data || response;
  } catch (error) {
    showSnackbar("Failed to load leave requests", "error");
  } finally {
    loading.value = false;
  }
};

const loadLeaveTypes = async () => {
  try {
    const response = await leaveService.getLeaveTypes();
    leaveTypes.value = response.data || response;
  } catch (error) {
    showSnackbar("Failed to load leave types", "error");
  }
};

const viewLeave = (leave) => {
  selectedLeave.value = leave;
  showViewDialog.value = true;
};

const openApproveDialog = (leave) => {
  selectedLeave.value = leave;
  approveData.value = { remarks: "" };
  showViewDialog.value = false;
  showApproveDialog.value = true;
};

const closeApproveDialog = () => {
  showApproveDialog.value = false;
  approveData.value = { remarks: "" };
};

const confirmApprove = async () => {
  try {
    approving.value = true;
    await leaveService.approveLeave(selectedLeave.value.id, approveData.value);
    showSnackbar("Leave request approved successfully", "success");
    closeApproveDialog();
    loadLeaves();
  } catch (error) {
    showSnackbar(
      error.response?.data?.message || "Failed to approve leave request",
      "error",
    );
  } finally {
    approving.value = false;
  }
};

const openRejectDialog = (leave) => {
  selectedLeave.value = leave;
  rejectData.value = { rejection_reason: "" };
  showViewDialog.value = false;
  showRejectDialog.value = true;
};

const closeRejectDialog = () => {
  showRejectDialog.value = false;
  rejectData.value = { rejection_reason: "" };
};

const confirmReject = async () => {
  if (!rejectData.value.rejection_reason) {
    showSnackbar("Please provide a rejection reason", "error");
    return;
  }

  try {
    rejecting.value = true;
    await leaveService.rejectLeave(selectedLeave.value.id, rejectData.value);
    showSnackbar("Leave request rejected", "success");
    closeRejectDialog();
    loadLeaves();
  } catch (error) {
    showSnackbar(
      error.response?.data?.message || "Failed to reject leave request",
      "error",
    );
  } finally {
    rejecting.value = false;
  }
};

const formatDate = (date) => {
  if (!date) return "N/A";
  try {
    return format(parseISO(date), "MMM dd, yyyy");
  } catch {
    return date;
  }
};

const formatDateTime = (date) => {
  if (!date) return "N/A";
  try {
    return format(parseISO(date), "MMM dd, yyyy hh:mm a");
  } catch {
    return date;
  }
};

const getStatusColor = (status) => {
  const colors = {
    pending: "warning",
    approved: "success",
    rejected: "error",
    cancelled: "grey",
  };
  return colors[status] || "grey";
};

const showSnackbar = (message, color = "success") => {
  snackbar.value = {
    show: true,
    message,
    color,
  };
};

// Lifecycle
onMounted(() => {
  loadLeaves();
  loadLeaveTypes();
});
</script>

<style scoped lang="scss">
.leave-approval-page {
  background-color: #f8f9fa;
  min-height: 100vh;
}

.page-header {
  margin-bottom: 24px;
}

.header-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.page-title-section {
  display: flex;
  align-items: center;
  gap: 16px;
}

.page-icon-badge {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.25);
}

.page-title {
  font-size: 28px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
  line-height: 1.2;
}

.page-subtitle {
  font-size: 14px;
  color: rgba(0, 31, 61, 0.6);
  margin: 4px 0 0 0;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
  margin-bottom: 20px;
}

.stat-card {
  background: white;
  border-radius: 12px;
  padding: 14px 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  display: flex;
  align-items: center;
  gap: 12px;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  overflow: hidden;

  &::before {
    content: "";
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: linear-gradient(180deg, #ed985f 0%, #f7b980 100%);
    transform: scaleY(0);
    transition: transform 0.3s ease;
  }
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(237, 152, 95, 0.2);
  border-color: rgba(237, 152, 95, 0.3);

  &::before {
    transform: scaleY(1);
  }
}

.stat-icon {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.stat-icon.pending {
  background: rgba(237, 152, 95, 0.1);

  .v-icon {
    color: #ed985f;
  }
}

.stat-icon.approved {
  background: rgba(16, 185, 129, 0.1);

  .v-icon {
    color: #10b981;
  }
}

.stat-icon.rejected {
  background: rgba(239, 68, 68, 0.1);

  .v-icon {
    color: #ef4444;
  }
}

.stat-icon.total {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);

  .v-icon {
    color: white;
  }
}

.stat-content {
  flex: 1;
  min-width: 0;
}

.stat-label {
  font-size: 11px;
  color: rgba(0, 31, 61, 0.6);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
}

.stat-value {
  font-size: 24px;
  font-weight: 700;
  color: #001f3d;
  line-height: 1;
}

.modern-card {
  background: white;
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid rgba(0, 31, 61, 0.08);
  padding: 24px;
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
    position: relative;
  }

  .dialog-icon-wrapper {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;

    &.info {
      background: linear-gradient(
        135deg,
        rgba(33, 150, 243, 0.15) 0%,
        rgba(33, 150, 243, 0.1) 100%
      );

      .v-icon {
        color: #2196f3 !important;
      }
    }

    &.success {
      background: linear-gradient(
        135deg,
        rgba(16, 185, 129, 0.15) 0%,
        rgba(16, 185, 129, 0.1) 100%
      );

      .v-icon {
        color: #10b981 !important;
      }
    }

    &.danger {
      background: linear-gradient(
        135deg,
        rgba(239, 68, 68, 0.15) 0%,
        rgba(239, 68, 68, 0.1) 100%
      );

      .v-icon {
        color: #ef4444 !important;
      }
    }
  }

  .close-btn {
    width: 36px;
    height: 36px;
    border-radius: 8px;
    background: rgba(0, 31, 61, 0.06);
    border: none;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    transition: all 0.2s ease;

    .v-icon {
      color: rgba(0, 31, 61, 0.7) !important;
    }

    &:hover {
      background: rgba(0, 31, 61, 0.1);
      transform: scale(1.05);
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
  }

  .dialog-actions {
    padding: 16px 24px !important;
    background: rgba(0, 31, 61, 0.02);
    border-top: 1px solid rgba(0, 31, 61, 0.08);
    gap: 10px;
  }
}

// Detail Row (View Dialog)
.detail-row {
  display: grid;
  grid-template-columns: 140px 1fr;
  gap: 12px;
  padding: 12px 0;
  border-bottom: 1px solid rgba(0, 31, 61, 0.06);

  &:last-child {
    border-bottom: none;
  }

  &.full-width {
    grid-template-columns: 1fr;
  }

  .detail-label {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: rgba(0, 31, 61, 0.6);
  }

  .detail-value {
    font-size: 14px;
    font-weight: 500;
    color: #001f3d;
  }
}

.employee-info {
  .employee-name {
    font-weight: 600;
    color: #001f3d;
    margin-bottom: 2px;
  }

  .employee-number {
    font-size: 12px;
    color: rgba(0, 31, 61, 0.6);
  }
}

.alert-title {
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 6px;
}

// Dialog Buttons
.dialog-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;

  .v-icon {
    flex-shrink: 0;
    color: inherit !important;
  }

  &:hover:not(:disabled) {
    transform: translateY(-1px);
  }

  &:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }

  &.dialog-btn-cancel {
    background: rgba(0, 31, 61, 0.06);
    color: rgba(0, 31, 61, 0.8);

    &:hover:not(:disabled) {
      background: rgba(0, 31, 61, 0.1);
    }
  }

  &.dialog-btn-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);

    .v-icon {
      color: #ffffff !important;
    }

    &:hover:not(:disabled) {
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    }
  }

  &.dialog-btn-danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);

    .v-icon {
      color: #ffffff !important;
    }

    &:hover:not(:disabled) {
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
    }
  }
}

// Confirmation Message
.confirmation-message {
  font-size: 15px;
  color: #001f3d;
  margin-bottom: 20px;
  line-height: 1.6;

  .employee-highlight {
    color: #ed985f;
    font-weight: 600;
  }
}

// Info Box
.info-box {
  background: rgba(0, 31, 61, 0.02);
  border-radius: 10px;
  padding: 16px;
  margin-bottom: 20px;
  border: 1px solid rgba(0, 31, 61, 0.08);

  &.success-box {
    background: rgba(16, 185, 129, 0.04);
    border-color: rgba(16, 185, 129, 0.15);
  }
}

.info-row {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 0;

  &:not(:last-child) {
    border-bottom: 1px solid rgba(0, 31, 61, 0.06);
  }

  .info-label {
    font-size: 13px;
    font-weight: 600;
    color: rgba(0, 31, 61, 0.7);
    min-width: 90px;
  }

  .info-value {
    font-size: 13px;
    color: #001f3d;
    flex: 1;
  }
}

// Form Field Label
.form-field-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 13px;
  font-weight: 600;
  color: #001f3d;
  margin-bottom: 8px;

  .required {
    color: #ef4444;
  }
}

// Rejection Warning
.rejection-warning {
  margin-bottom: 20px;
  border-radius: 8px !important;
  font-size: 13px !important;
}
</style>
