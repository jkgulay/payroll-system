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

        <v-btn
          color="#ED985F"
          variant="flat"
          prepend-icon="mdi-calendar-plus"
          @click="openSetLeaveDialog"
        >
          Set Leave
        </v-btn>
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
          <v-col cols="12" md="2">
            <v-select
              v-model="filters.status"
              :items="statusOptions"
              label="Status"
              variant="outlined"
              density="comfortable"
              hide-details
              clearable
              @update:model-value="onFilterChange"
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
              @update:model-value="onFilterChange"
            ></v-select>
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.is_with_pay"
              :items="compensationFilterOptions"
              label="Compensation"
              variant="outlined"
              density="comfortable"
              hide-details
              clearable
              @update:model-value="onFilterChange"
            ></v-select>
          </v-col>
          <v-col cols="12" md="3">
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
          <v-col cols="12" md="1" class="d-flex justify-end">
            <v-btn
              color="#ED985F"
              variant="tonal"
              icon="mdi-refresh"
              @click="loadLeaves(currentPage)"
              :loading="loading"
              title="Refresh"
            ></v-btn>
          </v-col>
        </v-row>
      </div>

      <div class="table-section">
        <v-data-table-server
          :headers="headers"
          :items="leaves"
          :loading="loading"
          :items-length="totalItems"
          :items-per-page="itemsPerPage"
          :page="currentPage"
          :items-per-page-options="pageSizeOptions"
          item-value="id"
          class="elevation-1"
          @update:page="onPageChange"
          @update:items-per-page="onItemsPerPageChange"
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

          <template #item.is_with_pay="{ item }">
            <v-chip size="small" :color="getLeaveCompensationColor(item)">
              {{ getLeaveCompensationLabel(item) }}
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
            <v-menu location="bottom end">
              <template #activator="{ props }">
                <v-btn
                  v-bind="props"
                  icon="mdi-dots-vertical"
                  size="small"
                  variant="text"
                  title="Actions"
                ></v-btn>
              </template>
              <v-list density="compact">
                <v-list-item @click="viewLeave(item)">
                  <template #prepend>
                    <v-icon size="18">mdi-eye</v-icon>
                  </template>
                  <v-list-item-title>View</v-list-item-title>
                </v-list-item>
                <v-list-item
                  v-if="item.status === 'pending'"
                  @click="openApproveDialog(item)"
                >
                  <template #prepend>
                    <v-icon size="18" color="success">mdi-check</v-icon>
                  </template>
                  <v-list-item-title>Approve</v-list-item-title>
                </v-list-item>
                <v-list-item
                  v-if="item.status === 'pending'"
                  @click="openRejectDialog(item)"
                >
                  <template #prepend>
                    <v-icon size="18" color="error">mdi-close</v-icon>
                  </template>
                  <v-list-item-title>Reject</v-list-item-title>
                </v-list-item>
              </v-list>
            </v-menu>
          </template>
        </v-data-table-server>
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
            <div class="detail-label">Compensation</div>
            <div class="detail-value">
              {{ getLeaveCompensationLabel(selectedLeave) }}
            </div>
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
              selectedLeave.approval_remarks &&
              selectedLeave.status === 'approved'
            "
            type="success"
            variant="tonal"
            density="compact"
            class="mt-3"
          >
            <div class="alert-title">Approval Remarks</div>
            <div>{{ selectedLeave.approval_remarks }}</div>
          </v-alert>

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

        <v-card-actions class="dialog-actions leave-dialog-actions">
          <v-spacer></v-spacer>
          <v-btn
            v-if="selectedLeave.status === 'pending'"
            color="success"
            variant="flat"
            @click="openApproveDialog(selectedLeave)"
          >
            <v-icon size="18">mdi-check</v-icon>
            Approve
          </v-btn>
          <v-btn
            v-if="selectedLeave.status === 'pending'"
            color="error"
            variant="flat"
            @click="openRejectDialog(selectedLeave)"
          >
            <v-icon size="18">mdi-close</v-icon>
            Reject
          </v-btn>
          <v-btn
            variant="outlined"
            color="grey"
            @click="showViewDialog = false"
          >
            Close
          </v-btn>
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
              <v-icon size="16" color="#10b981">mdi-cash-check</v-icon>
              <span class="info-label">Compensation:</span>
              <span class="info-value">
                {{ getLeaveCompensationLabel(selectedLeave) }}
              </span>
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

          <div class="form-field-label mt-3">
            <v-icon size="16" color="#10b981">mdi-cash-check</v-icon>
            <span>Set Compensation <span class="required">*</span></span>
          </div>
          <v-select
            v-model="approveData.is_with_pay"
            :items="compensationOptions"
            item-title="title"
            item-value="value"
            placeholder="Select compensation"
            variant="outlined"
            density="comfortable"
            color="#10b981"
            hide-details
          ></v-select>

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

        <v-card-actions class="dialog-actions leave-dialog-actions">
          <v-spacer></v-spacer>
          <v-btn variant="outlined" color="grey" @click="closeApproveDialog">
            <v-icon size="18">mdi-close</v-icon>
            Cancel
          </v-btn>
          <v-btn
            color="success"
            variant="flat"
            :disabled="approving || !hasApproveCompensation"
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
          </v-btn>
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

        <v-card-actions class="dialog-actions leave-dialog-actions">
          <v-spacer></v-spacer>
          <v-btn variant="outlined" color="grey" @click="closeRejectDialog">
            <v-icon size="18">mdi-close</v-icon>
            Cancel
          </v-btn>
          <v-btn
            color="error"
            variant="flat"
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
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Snackbar for notifications -->
    <v-snackbar v-model="snackbar.show" :color="snackbar.color" :timeout="3000">
      {{ snackbar.message }}
    </v-snackbar>

    <!-- Set Leave Dialog -->
    <v-dialog v-model="showSetLeaveDialog" max-width="700px" persistent>
      <v-card class="modern-dialog">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper info">
            <v-icon size="24">mdi-calendar-plus</v-icon>
          </div>
          <div>
            <div class="dialog-title">Set Leave</div>
            <div class="dialog-subtitle">
              Manually set and approve leave (same fields as My Leaves)
            </div>
          </div>
          <v-spacer></v-spacer>
          <button class="close-btn" @click="closeSetLeaveDialog">
            <v-icon size="20">mdi-close</v-icon>
          </button>
        </v-card-title>

        <v-card-text class="dialog-content">
          <v-form ref="setLeaveFormRef" v-model="setLeaveFormValid">
            <v-row>
              <v-col cols="12">
                <div class="form-field-label">
                  <v-icon size="16" color="#ed985f">mdi-account</v-icon>
                  <span>Employee <span class="required">*</span></span>
                </div>

                <v-autocomplete
                  v-model="setLeaveForm.employee_id"
                  :items="employeeOptions"
                  item-title="title"
                  item-value="value"
                  placeholder="Select employee"
                  :rules="[setLeaveRules.required]"
                  :loading="loadingEmployees"
                  no-data-text="No employees found"
                  variant="outlined"
                  density="comfortable"
                  color="#ed985f"
                  hide-details="auto"
                ></v-autocomplete>
              </v-col>

              <v-col cols="12" md="6">
                <div class="form-field-label">
                  <v-icon size="16" color="#ed985f">mdi-calendar-text</v-icon>
                  <span>Leave Type <span class="required">*</span></span>
                </div>
                <v-select
                  v-model="setLeaveForm.leave_type_id"
                  :items="leaveTypes"
                  item-title="name"
                  item-value="id"
                  placeholder="Select leave type"
                  :rules="[setLeaveRules.required]"
                  variant="outlined"
                  density="comfortable"
                  color="#ed985f"
                  hide-details="auto"
                  @update:model-value="onSetLeaveTypeChange"
                ></v-select>
              </v-col>

              <v-col cols="12" md="6">
                <div class="form-field-label">
                  <v-icon size="16" color="#ed985f">mdi-cash-check</v-icon>
                  <span>Compensation <span class="required">*</span></span>
                </div>
                <v-select
                  v-model="setLeaveForm.is_with_pay"
                  :items="compensationOptions"
                  item-title="title"
                  item-value="value"
                  placeholder="Select compensation"
                  :rules="[setLeaveRules.requiredBoolean]"
                  variant="outlined"
                  density="comfortable"
                  color="#ed985f"
                  hide-details="auto"
                ></v-select>
              </v-col>

              <v-col cols="12" md="6">
                <div class="form-field-label">
                  <v-icon size="16" color="#ed985f">mdi-calendar-start</v-icon>
                  <span>From Date <span class="required">*</span></span>
                </div>
                <v-text-field
                  v-model="setLeaveForm.leave_date_from"
                  type="date"
                  placeholder="Select start date"
                  :rules="[setLeaveRules.required]"
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
                  v-model="setLeaveForm.leave_date_to"
                  type="date"
                  placeholder="Select end date"
                  :rules="[
                    setLeaveRules.required,
                    setLeaveRules.endDateAfterStart,
                  ]"
                  :min="setLeaveForm.leave_date_from || minDate"
                  variant="outlined"
                  density="comfortable"
                  color="#ed985f"
                  hide-details="auto"
                ></v-text-field>
              </v-col>

              <v-col cols="12" v-if="setLeaveNumberOfDays > 0">
                <v-alert
                  type="info"
                  variant="tonal"
                  density="compact"
                  class="days-alert"
                >
                  <div class="d-flex align-center">
                    <v-icon size="18" class="mr-2">mdi-calendar-range</v-icon>
                    <span>
                      Total Days:
                      <strong>{{ setLeaveNumberOfDays }}</strong> day{{
                        setLeaveNumberOfDays > 1 ? "s" : ""
                      }}
                    </span>
                  </div>
                </v-alert>
              </v-col>

              <v-col cols="12">
                <div class="form-field-label">
                  <v-icon size="16" color="#ed985f">mdi-text-box</v-icon>
                  <span>Reason <span class="required">*</span></span>
                </div>
                <v-textarea
                  v-model="setLeaveForm.reason"
                  placeholder="Please provide a reason for this leave"
                  :rules="[setLeaveRules.required]"
                  rows="3"
                  variant="outlined"
                  density="comfortable"
                  color="#ed985f"
                  hide-details="auto"
                ></v-textarea>
              </v-col>

              <v-col cols="12">
                <div class="form-field-label">
                  <v-icon size="16" color="#10b981">mdi-note-text</v-icon>
                  <span>Approval Remarks (Optional)</span>
                </div>
                <v-textarea
                  v-model="setLeaveForm.approval_remarks"
                  placeholder="Add optional remarks"
                  rows="2"
                  variant="outlined"
                  density="comfortable"
                  color="#10b981"
                  hide-details="auto"
                ></v-textarea>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-card-actions class="dialog-actions leave-dialog-actions">
          <v-spacer></v-spacer>
          <v-btn variant="outlined" color="grey" @click="closeSetLeaveDialog">
            <v-icon size="18">mdi-close</v-icon>
            Cancel
          </v-btn>
          <v-btn
            color="success"
            variant="flat"
            :disabled="savingSetLeave"
            @click="submitSetLeave"
          >
            <v-progress-circular
              v-if="savingSetLeave"
              size="18"
              width="2"
              indeterminate
              color="white"
            ></v-progress-circular>
            <v-icon v-else size="18">mdi-check</v-icon>
            Set Leave
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import { differenceInDays } from "date-fns";
import { formatDate, formatDateTime } from "@/utils/formatters";
import leaveService from "@/services/leaveService";

// State
const loading = ref(false);
const loadingEmployees = ref(false);
const approving = ref(false);
const rejecting = ref(false);
const savingSetLeave = ref(false);
const leaves = ref([]);
const leaveTypes = ref([]);
const employees = ref([]);
const leaveStats = ref({
  pending: 0,
  approved: 0,
  rejected: 0,
  total: 0,
});
const currentPage = ref(1);
const itemsPerPage = ref(15);
const totalItems = ref(0);
const pageSizeOptions = [
  { value: 10, title: "10" },
  { value: 15, title: "15" },
  { value: 25, title: "25" },
  { value: 50, title: "50" },
];
const search = ref("");
const showViewDialog = ref(false);
const showApproveDialog = ref(false);
const showRejectDialog = ref(false);
const showSetLeaveDialog = ref(false);
const selectedLeave = ref(null);
const setLeaveFormRef = ref(null);
const setLeaveFormValid = ref(false);
let searchDebounceTimeout = null;

const filters = ref({
  status: null,
  leave_type_id: null,
  is_with_pay: null,
});

const approveData = ref({
  remarks: "",
  is_with_pay: null,
});

const rejectData = ref({
  rejection_reason: "",
});

const setLeaveForm = ref({
  employee_id: null,
  leave_type_id: null,
  is_with_pay: null,
  leave_date_from: "",
  leave_date_to: "",
  reason: "",
  approval_remarks: "",
});

const snackbar = ref({
  show: false,
  message: "",
  color: "success",
});

// Computed
const pendingCount = computed(() => {
  return leaveStats.value.pending;
});

const approvedCount = computed(() => {
  return leaveStats.value.approved;
});

const rejectedCount = computed(() => {
  return leaveStats.value.rejected;
});

const totalCount = computed(() => {
  return leaveStats.value.total;
});

const minDate = computed(() => {
  return new Date().toISOString().split("T")[0];
});

const setLeaveNumberOfDays = computed(() => {
  if (
    !setLeaveForm.value.leave_date_from ||
    !setLeaveForm.value.leave_date_to
  ) {
    return 0;
  }

  const from = new Date(setLeaveForm.value.leave_date_from);
  const to = new Date(setLeaveForm.value.leave_date_to);
  return differenceInDays(to, from) + 1;
});

const employeeOptions = computed(() => {
  const mapped = employees.value.map((employee) => {
    const fullName =
      `${employee.first_name || ""} ${employee.last_name || ""}`.trim();
    const employeeNumber = employee.employee_number
      ? ` (${employee.employee_number})`
      : "";
    const projectName = employee.project?.name || "No Project";
    const positionName =
      employee.positionRate?.position_name ||
      employee.position ||
      "No Position";

    return {
      value: employee.id,
      fullName,
      title: `${fullName}${employeeNumber} - ${projectName} / ${positionName}`,
    };
  });

  mapped.sort((a, b) => a.fullName.localeCompare(b.fullName));

  return mapped.map((employee) => ({
    value: employee.value,
    title: employee.title,
  }));
});

const setLeaveRules = {
  required: (v) => !!v || "This field is required",
  requiredBoolean: (v) => v === true || v === false || "This field is required",
  endDateAfterStart: (v) => {
    if (!setLeaveForm.value.leave_date_from || !v) return true;
    return (
      v >= setLeaveForm.value.leave_date_from ||
      "End date must be after start date"
    );
  },
};

// Table headers
const headers = [
  { title: "Employee", key: "employee", sortable: false },
  { title: "Leave Type", key: "leave_type", sortable: false },
  { title: "Compensation", key: "is_with_pay", sortable: false },
  { title: "From Date", key: "leave_date_from", sortable: false },
  { title: "To Date", key: "leave_date_to", sortable: false },
  { title: "Days", key: "number_of_days", sortable: false, align: "center" },
  { title: "Status", key: "status", sortable: false },
  { title: "Actions", key: "actions", sortable: false, align: "center" },
];

const compensationOptions = [
  { title: "With Pay", value: true },
  { title: "Without Pay", value: false },
];

const getLeaveTypeDefaultCompensation = (leaveTypeId) => {
  const leaveType = leaveTypes.value.find(
    (item) => Number(item.id) === Number(leaveTypeId),
  );

  if (!leaveType || typeof leaveType.is_paid !== "boolean") {
    return null;
  }

  return leaveType.is_paid;
};

const onSetLeaveTypeChange = (leaveTypeId) => {
  const isWithPayDefault = getLeaveTypeDefaultCompensation(leaveTypeId);
  if (isWithPayDefault !== null) {
    setLeaveForm.value.is_with_pay = isWithPayDefault;
  }
};

const resolveLeaveWithPay = (leave) => {
  if (!leave) {
    return null;
  }

  if (leave.status !== "approved") {
    return null;
  }

  if (leave?.is_with_pay === true || leave?.is_with_pay === false) {
    return leave.is_with_pay;
  }

  if (leave?.leave_type && typeof leave.leave_type.is_paid === "boolean") {
    return leave.leave_type.is_paid;
  }

  return null;
};

const getLeaveCompensationLabel = (leave) => {
  const compensationState = resolveLeaveWithPay(leave);

  if (compensationState === true) {
    return "With Pay";
  }

  if (compensationState === false) {
    return "Without Pay";
  }

  if (leave?.status === "pending") {
    return "Pending Decision";
  }

  return "N/A";
};

const getLeaveCompensationColor = (leave) => {
  const compensationState = resolveLeaveWithPay(leave);

  if (compensationState === true) {
    return "success";
  }

  if (compensationState === false) {
    return "warning";
  }

  return "grey";
};

const hasApproveCompensation = computed(() => {
  return (
    approveData.value.is_with_pay === true ||
    approveData.value.is_with_pay === false
  );
});

const statusOptions = [
  { title: "All", value: null },
  { title: "Pending", value: "pending" },
  { title: "Approved", value: "approved" },
  { title: "Rejected", value: "rejected" },
];

const compensationFilterOptions = [
  { title: "With Pay", value: true },
  { title: "Without Pay", value: false },
];

// Methods
const loadLeaves = async (page = currentPage.value) => {
  try {
    loading.value = true;
    const normalizedPage = Number(page) > 0 ? Number(page) : 1;
    currentPage.value = normalizedPage;

    const searchTerm = String(search.value || "").trim();

    const listParams = {
      page: normalizedPage,
      per_page: itemsPerPage.value,
    };
    if (filters.value.status) {
      listParams.status = filters.value.status;
    }
    if (filters.value.leave_type_id) {
      listParams.leave_type_id = filters.value.leave_type_id;
    }
    if (
      filters.value.is_with_pay === true ||
      filters.value.is_with_pay === false
    ) {
      listParams.is_with_pay = filters.value.is_with_pay;
    }
    if (searchTerm) {
      listParams.search = searchTerm;
    }

    const statsParams = {};
    if (filters.value.leave_type_id) {
      statsParams.leave_type_id = filters.value.leave_type_id;
    }
    if (
      filters.value.is_with_pay === true ||
      filters.value.is_with_pay === false
    ) {
      statsParams.is_with_pay = filters.value.is_with_pay;
    }
    if (searchTerm) {
      statsParams.search = searchTerm;
    }

    const [listResponse, statsResponse] = await Promise.all([
      leaveService.getLeaves(listParams),
      leaveService.getLeaveStats(statsParams),
    ]);

    leaves.value = Array.isArray(listResponse?.data) ? listResponse.data : [];
    currentPage.value = Number(listResponse?.current_page || normalizedPage);
    totalItems.value = Number(listResponse?.total || leaves.value.length || 0);
    itemsPerPage.value = Number(listResponse?.per_page || itemsPerPage.value);

    leaveStats.value = {
      pending: Number(statsResponse?.pending || 0),
      approved: Number(statsResponse?.approved || 0),
      rejected: Number(statsResponse?.rejected || 0),
      total: Number(statsResponse?.total || 0),
    };
  } catch (error) {
    showSnackbar("Failed to load leave requests", "error");
    leaves.value = [];
    totalItems.value = 0;
  } finally {
    loading.value = false;
  }
};

const onFilterChange = () => {
  currentPage.value = 1;
  loadLeaves(1);
};

const onPageChange = (page) => {
  const nextPage = Number(page) > 0 ? Number(page) : 1;
  if (nextPage === currentPage.value) {
    return;
  }

  loadLeaves(nextPage);
};

const onItemsPerPageChange = (perPage) => {
  const normalizedPerPage = Number(perPage) > 0 ? Number(perPage) : 15;
  if (normalizedPerPage === itemsPerPage.value) {
    return;
  }

  itemsPerPage.value = normalizedPerPage;
  currentPage.value = 1;
  loadLeaves(1);
};

const loadLeaveTypes = async () => {
  try {
    const response = await leaveService.getLeaveTypes();
    leaveTypes.value = response.data || response;
  } catch (error) {
    showSnackbar("Failed to load leave types", "error");
  }
};

const loadEmployees = async () => {
  try {
    loadingEmployees.value = true;

    const allEmployees = [];
    let page = 1;
    let lastPage = 1;

    do {
      const response = await leaveService.getEmployees({ page, per_page: 200 });
      const rows = Array.isArray(response?.data) ? response.data : [];
      allEmployees.push(...rows);
      lastPage = Number(response?.last_page || 1);
      page += 1;
    } while (page <= lastPage);

    employees.value = allEmployees;
  } catch (error) {
    showSnackbar("Failed to load employees", "error");
    employees.value = [];
  } finally {
    loadingEmployees.value = false;
  }
};

const resetSetLeaveForm = () => {
  setLeaveForm.value = {
    employee_id: null,
    leave_type_id: null,
    is_with_pay: null,
    leave_date_from: "",
    leave_date_to: "",
    reason: "",
    approval_remarks: "",
  };
};

const openSetLeaveDialog = async () => {
  resetSetLeaveForm();
  showSetLeaveDialog.value = true;

  if (!employees.value.length) {
    await loadEmployees();
  }
};

const closeSetLeaveDialog = () => {
  showSetLeaveDialog.value = false;
  resetSetLeaveForm();
};

const submitSetLeave = async () => {
  if (!setLeaveFormRef.value) return;

  const { valid } = await setLeaveFormRef.value.validate();
  if (!valid) {
    showSnackbar("Please fill in all required fields correctly", "error");
    return;
  }

  try {
    savingSetLeave.value = true;

    await leaveService.setLeave({
      employee_id: setLeaveForm.value.employee_id,
      leave_type_id: setLeaveForm.value.leave_type_id,
      is_with_pay: setLeaveForm.value.is_with_pay,
      leave_date_from: setLeaveForm.value.leave_date_from,
      leave_date_to: setLeaveForm.value.leave_date_to,
      reason: setLeaveForm.value.reason,
      approval_remarks: setLeaveForm.value.approval_remarks || null,
    });

    showSnackbar("Leave was set and approved successfully", "success");
    closeSetLeaveDialog();
    await loadLeaves(currentPage.value);
  } catch (error) {
    showSnackbar(
      error.response?.data?.message || "Failed to set leave",
      "error",
    );
  } finally {
    savingSetLeave.value = false;
  }
};

const viewLeave = (leave) => {
  selectedLeave.value = leave;
  showViewDialog.value = true;
};

const openApproveDialog = (leave) => {
  selectedLeave.value = leave;
  approveData.value = {
    remarks: "",
    is_with_pay: null,
  };
  showViewDialog.value = false;
  showApproveDialog.value = true;
};

const closeApproveDialog = () => {
  showApproveDialog.value = false;
  approveData.value = {
    remarks: "",
    is_with_pay: null,
  };
};

const confirmApprove = async () => {
  if (!hasApproveCompensation.value) {
    showSnackbar("Please set compensation before approving", "error");
    return;
  }

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
  loadLeaves(1);
  loadLeaveTypes();
  loadEmployees();
});

watch(search, () => {
  if (searchDebounceTimeout) {
    clearTimeout(searchDebounceTimeout);
  }

  searchDebounceTimeout = setTimeout(() => {
    currentPage.value = 1;
    loadLeaves(1);
  }, 350);
});

onUnmounted(() => {
  if (searchDebounceTimeout) {
    clearTimeout(searchDebounceTimeout);
    searchDebounceTimeout = null;
  }
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

.leave-dialog-actions {
  position: sticky;
  bottom: 0;
  z-index: 2;
  background: #ffffff !important;
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
