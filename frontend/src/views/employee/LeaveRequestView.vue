<template>
  <div class="leave-page">
    <!-- Modern Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="page-title-section">
          <div class="page-icon-badge">
            <v-icon size="20">mdi-calendar-clock</v-icon>
          </div>
          <div>
            <h1 class="page-title">My Leave Requests</h1>
            <p class="page-subtitle">
              Manage your leave applications and track balances
            </p>
          </div>
        </div>
        <div class="action-buttons">
          <button
            class="action-btn action-btn-primary"
            @click="openNewLeaveDialog"
          >
            <v-icon size="20">mdi-plus</v-icon>
            <span>File New Leave</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Leave Credits Summary Bar -->
    <div v-if="leaveCredits.length > 0" class="credits-bar">
      <div class="credits-bar-header">
        <v-icon size="16" color="#ed985f">mdi-information-outline</v-icon>
        <span class="credits-bar-title">Leave Balance:</span>
      </div>
      <div class="credits-items">
        <div
          v-for="credit in leaveCredits"
          :key="credit.leave_type.id"
          class="credit-item"
          :class="{ 'low-balance': credit.available_credits === 0 }"
        >
          <div class="credit-type">{{ credit.leave_type.name }}</div>
          <div class="credit-balance">
            <span class="available">{{ credit.available_credits }}</span>
            <span class="divider">/</span>
            <span class="total">{{ credit.total_credits }}</span>
            <span class="unit">days</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content Card -->
    <div class="modern-card">
      <!-- Filters Section -->
      <div class="filters-section">
        <div class="filter-group">
          <v-select
            v-model="filters.status"
            :items="statusOptions"
            label="Filter by Status"
            density="comfortable"
            variant="outlined"
            clearable
            @update:model-value="loadLeaves"
            hide-details
            style="max-width: 250px"
          >
            <template v-slot:prepend-inner>
              <v-icon size="20">mdi-filter</v-icon>
            </template>
          </v-select>
        </div>
        <div class="search-group">
          <v-text-field
            v-model="search"
            label="Search..."
            prepend-inner-icon="mdi-magnify"
            variant="outlined"
            density="comfortable"
            clearable
            hide-details
            style="max-width: 280px"
          ></v-text-field>
        </div>
      </div>

      <!-- Leave Requests Table -->
      <div class="table-section">
        <v-data-table
          :headers="headers"
          :items="filteredLeaves"
          :loading="loading"
          item-value="id"
          class="elevation-1"
        >
          <template v-slot:item.leave_type="{ item }">
            <v-chip size="small" color="primary" dark>
              {{ item.leave_type?.name }}
            </v-chip>
          </template>

          <template v-slot:item.leave_date_from="{ item }">
            {{ formatDate(item.leave_date_from) }}
          </template>

          <template v-slot:item.leave_date_to="{ item }">
            {{ formatDate(item.leave_date_to) }}
          </template>

          <template v-slot:item.number_of_days="{ item }">
            {{ item.number_of_days }} day{{
              item.number_of_days > 1 ? "s" : ""
            }}
          </template>

          <template v-slot:item.status="{ item }">
            <v-chip size="small" :color="getStatusColor(item.status)" dark>
              {{ item.status.toUpperCase() }}
            </v-chip>
          </template>

          <template v-slot:item.actions="{ item }">
            <v-menu>
              <template v-slot:activator="{ props }">
                <v-btn icon size="small" v-bind="props">
                  <v-icon>mdi-dots-vertical</v-icon>
                </v-btn>
              </template>
              <v-list density="compact">
                <v-list-item @click="viewLeave(item)">
                  <template v-slot:prepend>
                    <v-icon size="small">mdi-eye</v-icon>
                  </template>
                  <v-list-item-title>View Details</v-list-item-title>
                </v-list-item>
                <v-list-item
                  v-if="item.status === 'pending'"
                  @click="editLeave(item)"
                >
                  <template v-slot:prepend>
                    <v-icon size="small" color="primary">mdi-pencil</v-icon>
                  </template>
                  <v-list-item-title>Edit</v-list-item-title>
                </v-list-item>
                <v-list-item
                  v-if="item.status === 'pending'"
                  @click="deleteLeave(item)"
                >
                  <template v-slot:prepend>
                    <v-icon size="small" color="error">mdi-delete</v-icon>
                  </template>
                  <v-list-item-title>Delete</v-list-item-title>
                </v-list-item>
              </v-list>
            </v-menu>
          </template>
        </v-data-table>
      </div>
    </div>

    <!-- New/Edit Leave Dialog -->
    <v-dialog v-model="showLeaveDialog" max-width="700px" persistent>
      <v-card class="modern-dialog">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper primary">
            <v-icon size="24">mdi-calendar-plus</v-icon>
          </div>
          <div>
            <div class="dialog-title">
              {{ editMode ? "Edit Leave Request" : "File New Leave Request" }}
            </div>
            <div class="dialog-subtitle">
              {{
                editMode
                  ? "Update your leave request details"
                  : "Submit your leave request for approval"
              }}
            </div>
          </div>
          <v-spacer></v-spacer>
          <button class="close-btn" @click="closeLeaveDialog">
            <v-icon size="20">mdi-close</v-icon>
          </button>
        </v-card-title>

        <v-card-text class="dialog-content">
          <v-form ref="leaveFormRef" v-model="formValid">
            <v-row>
              <!-- Leave Type -->
              <v-col cols="12">
                <div class="form-field-label">
                  <v-icon size="16" color="#ed985f">mdi-calendar-text</v-icon>
                  <span>Leave Type <span class="required">*</span></span>
                </div>
                <v-select
                  v-model="formData.leave_type_id"
                  :items="leaveTypes"
                  item-title="name"
                  item-value="id"
                  placeholder="Select leave type"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                  color="#ed985f"
                  hide-details="auto"
                ></v-select>
              </v-col>

              <!-- Date Range -->
              <v-col cols="12" md="6">
                <div class="form-field-label">
                  <v-icon size="16" color="#ed985f">mdi-calendar-start</v-icon>
                  <span>From Date <span class="required">*</span></span>
                </div>
                <v-text-field
                  v-model="formData.leave_date_from"
                  type="date"
                  placeholder="Select start date"
                  :rules="[rules.required]"
                  :min="minDate"
                  variant="outlined"
                  density="comfortable"
                  color="#ed985f"
                  hide-details="auto"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <div class="form-field-label">
                  <v-icon size="16" color="#ed985f">mdi-calendar-end</v-icon>
                  <span>To Date <span class="required">*</span></span>
                </div>
                <v-text-field
                  v-model="formData.leave_date_to"
                  type="date"
                  placeholder="Select end date"
                  :rules="[rules.required, rules.endDateAfterStart]"
                  :min="formData.leave_date_from || minDate"
                  variant="outlined"
                  density="comfortable"
                  color="#ed985f"
                  hide-details="auto"
                ></v-text-field>
              </v-col>

              <v-col cols="12" v-if="numberOfDays > 0">
                <v-alert
                  type="info"
                  variant="tonal"
                  density="compact"
                  class="days-alert"
                >
                  <div class="d-flex align-center">
                    <v-icon size="18" class="mr-2">mdi-calendar-range</v-icon>
                    <span
                      >Total Days: <strong>{{ numberOfDays }}</strong> day{{
                        numberOfDays > 1 ? "s" : ""
                      }}</span
                    >
                  </div>
                </v-alert>
              </v-col>

              <!-- Reason -->
              <v-col cols="12">
                <div class="form-field-label">
                  <v-icon size="16" color="#ed985f">mdi-text-box</v-icon>
                  <span>Reason <span class="required">*</span></span>
                </div>
                <v-textarea
                  v-model="formData.reason"
                  placeholder="Please provide a reason for your leave request"
                  :rules="[rules.required]"
                  rows="3"
                  variant="outlined"
                  density="comfortable"
                  color="#ed985f"
                  hide-details="auto"
                ></v-textarea>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <button
            class="dialog-btn dialog-btn-cancel"
            @click="closeLeaveDialog"
          >
            <v-icon size="18">mdi-close</v-icon>
            Cancel
          </button>
          <button
            class="dialog-btn dialog-btn-primary"
            :disabled="saving"
            @click="saveLeave"
          >
            <v-progress-circular
              v-if="saving"
              size="18"
              width="2"
              indeterminate
              color="white"
            ></v-progress-circular>
            <v-icon v-else size="18">mdi-check</v-icon>
            {{ editMode ? "Update" : "Submit" }}
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- View Leave Dialog -->
    <v-dialog v-model="showViewDialog" max-width="600px">
      <v-card v-if="selectedLeave" class="modern-dialog">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper info">
            <v-icon size="24">mdi-calendar-clock</v-icon>
          </div>
          <div>
            <div class="dialog-title">Leave Request Details</div>
            <div class="dialog-subtitle">View complete information</div>
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
            <div class="detail-label">Approved/Rejected By</div>
            <div class="detail-value">
              {{ selectedLeave.approved_by?.name || "N/A" }} on
              {{ formatDateTime(selectedLeave.approved_at) }}
            </div>
          </div>

          <v-alert
            v-if="selectedLeave.rejection_reason"
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
            class="dialog-btn dialog-btn-cancel"
            @click="showViewDialog = false"
          >
            Close
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete Confirmation Dialog -->
    <v-dialog v-model="showDeleteDialog" max-width="500">
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
          Are you sure you want to delete this leave request?
        </v-card-text>

        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <button
            class="dialog-btn dialog-btn-cancel"
            @click="showDeleteDialog = false"
          >
            Cancel
          </button>
          <button
            class="dialog-btn dialog-btn-danger"
            :disabled="deleting"
            @click="confirmDelete"
          >
            <v-progress-circular
              v-if="deleting"
              size="18"
              width="2"
              indeterminate
              color="white"
            ></v-progress-circular>
            <v-icon v-else size="18">mdi-delete</v-icon>
            Delete
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { format, parseISO, differenceInDays } from "date-fns";
import { useToast } from "vue-toastification";
import leaveService from "@/services/leaveService";
import { formatDate, formatDateTime } from "@/utils/formatters";
import { devLog } from "@/utils/devLog";

const toast = useToast();

// State
const loading = ref(false);
const saving = ref(false);
const deleting = ref(false);
const leaves = ref([]);
const leaveTypes = ref([]);
const leaveCredits = ref([]);
const search = ref("");
const formValid = ref(false);
const editMode = ref(false);
const showLeaveDialog = ref(false);
const showViewDialog = ref(false);
const showDeleteDialog = ref(false);
const selectedLeave = ref(null);
const leaveToDelete = ref(null);
const leaveFormRef = ref(null);

const filters = ref({
  status: null,
});

const formData = ref({
  leave_type_id: null,
  leave_date_from: "",
  leave_date_to: "",
  reason: "",
});

// Computed
const minDate = computed(() => {
  return format(new Date(), "yyyy-MM-dd");
});

const numberOfDays = computed(() => {
  if (formData.value.leave_date_from && formData.value.leave_date_to) {
    const from = new Date(formData.value.leave_date_from);
    const to = new Date(formData.value.leave_date_to);
    return differenceInDays(to, from) + 1;
  }
  return 0;
});

const filteredLeaves = computed(() => {
  let filtered = leaves.value;

  // Apply search filter
  if (search.value) {
    const searchLower = search.value.toLowerCase();
    filtered = filtered.filter((leave) => {
      const leaveType = leave.leave_type?.name?.toLowerCase() || "";
      const reason = leave.reason?.toLowerCase() || "";
      const status = leave.status?.toLowerCase() || "";
      const dateFrom = formatDate(leave.leave_date_from).toLowerCase();
      const dateTo = formatDate(leave.leave_date_to).toLowerCase();

      return (
        leaveType.includes(searchLower) ||
        reason.includes(searchLower) ||
        status.includes(searchLower) ||
        dateFrom.includes(searchLower) ||
        dateTo.includes(searchLower)
      );
    });
  }

  return filtered;
});
// Table headers
const headers = [
  { title: "Leave Type", key: "leave_type", sortable: true },
  { title: "From Date", key: "leave_date_from", sortable: true },
  { title: "To Date", key: "leave_date_to", sortable: true },
  { title: "Days", key: "number_of_days", sortable: true, align: "center" },
  { title: "Status", key: "status", sortable: true },
  {
    title: "Actions",
    key: "actions",
    sortable: false,
    align: "center",
    width: "120px",
  },
];

const statusOptions = [
  { title: "All Statuses", value: null },
  { title: "Pending", value: "pending" },
  { title: "Approved", value: "approved" },
  { title: "Rejected", value: "rejected" },
];

// Validation rules
const rules = {
  required: (v) => !!v || "This field is required",
  endDateAfterStart: (v) => {
    if (!formData.value.leave_date_from || !v) return true;
    return (
      v >= formData.value.leave_date_from || "End date must be after start date"
    );
  },
};

// Methods
const loadLeaves = async () => {
  try {
    loading.value = true;
    const params = {};
    if (filters.value.status) {
      params.status = filters.value.status;
    }
    const response = await leaveService.getMyLeaves(params);
    leaves.value = response.data || response;
  } catch (error) {
    toast.error("Failed to load leave requests");
    devLog.error(error);
  } finally {
    loading.value = false;
  }
};

const loadLeaveTypes = async () => {
  try {
    const response = await leaveService.getLeaveTypes();
    leaveTypes.value = response.data || response;
  } catch (error) {
    toast.error("Failed to load leave types");
    devLog.error(error);
  }
};

const loadLeaveCredits = async () => {
  try {
    const response = await leaveService.getMyCredits();
    leaveCredits.value = response.leave_credits || [];
  } catch (error) {
    devLog.error("Failed to load leave credits:", error);
  }
};

const openNewLeaveDialog = () => {
  editMode.value = false;
  formData.value = {
    leave_type_id: null,
    leave_date_from: "",
    leave_date_to: "",
    reason: "",
  };
  showLeaveDialog.value = true;
};

const closeLeaveDialog = () => {
  showLeaveDialog.value = false;
  formData.value = {
    leave_type_id: null,
    leave_date_from: "",
    leave_date_to: "",
    reason: "",
  };
};

const saveLeave = async () => {
  if (!leaveFormRef.value) return;

  const { valid } = await leaveFormRef.value.validate();
  if (!valid) {
    toast.error("Please fill in all required fields correctly");
    return;
  }

  try {
    saving.value = true;
    if (editMode.value && selectedLeave.value) {
      await leaveService.updateLeave(selectedLeave.value.id, formData.value);
      toast.success("Leave request updated successfully");
    } else {
      await leaveService.createLeave(formData.value);
      toast.success("Leave request submitted successfully");
    }
    closeLeaveDialog();
    loadLeaves();
    loadLeaveCredits();
  } catch (error) {
    toast.error(
      error.response?.data?.message || "Failed to save leave request",
    );
    devLog.error(error);
  } finally {
    saving.value = false;
  }
};

const viewLeave = (leave) => {
  selectedLeave.value = leave;
  showViewDialog.value = true;
};

const editLeave = (leave) => {
  selectedLeave.value = leave;
  editMode.value = true;
  formData.value = {
    leave_type_id: leave.leave_type_id,
    leave_date_from: format(parseISO(leave.leave_date_from), "yyyy-MM-dd"),
    leave_date_to: format(parseISO(leave.leave_date_to), "yyyy-MM-dd"),
    reason: leave.reason,
  };
  showLeaveDialog.value = true;
};

const deleteLeave = (leave) => {
  leaveToDelete.value = leave;
  showDeleteDialog.value = true;
};

const confirmDelete = async () => {
  try {
    deleting.value = true;
    await leaveService.deleteLeave(leaveToDelete.value.id);
    toast.success("Leave request deleted successfully");
    showDeleteDialog.value = false;
    loadLeaves();
    loadLeaveCredits();
  } catch (error) {
    toast.error("Failed to delete leave request");
    devLog.error(error);
  } finally {
    deleting.value = false;
  }
};

const getStatusColor = (status) => {
  const colors = {
    pending: "#FF9800",
    approved: "#4CAF50",
    rejected: "#F44336",
    cancelled: "#9E9E9E",
  };
  return colors[status] || "#9E9E9E";
};

// Lifecycle
onMounted(() => {
  loadLeaves();
  loadLeaveTypes();
  loadLeaveCredits();
});
</script>

<style scoped lang="scss">
.leave-page {
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
}

// Leave Credits Bar (Compact)
.credits-bar {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 12px 20px;
  margin-bottom: 20px;
  background: linear-gradient(
    135deg,
    rgba(237, 152, 95, 0.06) 0%,
    rgba(247, 185, 128, 0.03) 100%
  );
  border: 1px solid rgba(237, 152, 95, 0.15);
  border-radius: 10px;
  flex-wrap: wrap;
}

.credits-bar-header {
  display: flex;
  align-items: center;
  gap: 6px;
  flex-shrink: 0;
}

.credits-bar-title {
  font-size: 13px;
  font-weight: 600;
  color: #001f3d;
}

.credits-items {
  display: flex;
  gap: 20px;
  flex-wrap: wrap;
  flex: 1;
}

.credit-item {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 6px 14px;
  background: #ffffff;
  border-radius: 8px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  transition: all 0.2s ease;

  &:hover {
    transform: translateY(-1px);
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.06);
  }

  &.low-balance {
    background: rgba(244, 67, 54, 0.04);
    border-color: rgba(244, 67, 54, 0.2);

    .available {
      color: #f44336 !important;
    }
  }
}

.credit-type {
  font-size: 12px;
  font-weight: 600;
  color: rgba(0, 31, 61, 0.7);
}

.credit-balance {
  display: flex;
  align-items: baseline;
  gap: 3px;
  font-size: 15px;
  font-weight: 700;

  .available {
    color: #ed985f;
  }

  .divider {
    color: rgba(0, 31, 61, 0.3);
    font-size: 13px;
  }

  .total {
    color: rgba(0, 31, 61, 0.4);
    font-size: 13px;
  }

  .unit {
    font-size: 11px;
    font-weight: 500;
    color: rgba(0, 31, 61, 0.5);
    margin-left: 2px;
  }
}

// Main Content Card
.modern-card {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  overflow: hidden;
  padding: 24px;
}

// Filters Section
.filters-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  padding-bottom: 20px;
  margin-bottom: 20px;
  border-bottom: 1px solid rgba(0, 31, 61, 0.08);
  flex-wrap: wrap;

  @media (max-width: 768px) {
    flex-direction: column;
    align-items: stretch;

    .filter-group,
    .search-group {
      max-width: 100% !important;
      min-width: 100% !important;
    }
  }
}

.filter-group {
  display: flex;
  gap: 10px;
  align-items: center;
}

.search-group {
  flex: 1;
  display: flex;
  justify-content: flex-end;
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

    &.primary {
      background: linear-gradient(
        135deg,
        rgba(237, 152, 95, 0.15) 0%,
        rgba(247, 185, 128, 0.1) 100%
      );

      .v-icon {
        color: #ed985f !important;
      }
    }

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
    color: #f44336;
  }
}

.days-alert {
  border-radius: 8px !important;
  font-size: 14px !important;
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

  &.dialog-btn-primary {
    background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);

    .v-icon {
      color: #ffffff !important;
    }

    &:hover:not(:disabled) {
      box-shadow: 0 4px 12px rgba(237, 152, 95, 0.4);
    }
  }

  &.dialog-btn-danger {
    background: linear-gradient(135deg, #f44336 0%, #e53935 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(244, 67, 54, 0.3);

    .v-icon {
      color: #ffffff !important;
    }

    &:hover:not(:disabled) {
      box-shadow: 0 4px 12px rgba(244, 67, 54, 0.4);
    }
  }
}
</style>
