<template>
  <div class="salary-adjustments-page">
    <div class="modern-card">
      <div class="page-header">
        <div class="page-icon-badge">
          <v-icon size="28">mdi-cash-clock</v-icon>
        </div>

        <div class="page-header-content">
          <h1 class="page-title">Salary Exception Records</h1>
          <p class="page-subtitle">
            ADJ. PREV. SAL is sourced from approved one-time exception records
            only. Use this page for controlled prior-period corrections.
          </p>
        </div>

        <div class="action-buttons" v-if="hasAccess">
          <v-btn
            variant="text"
            @click="refreshData"
            :loading="loading"
            icon="mdi-refresh"
            size="small"
          ></v-btn>
          <button class="action-btn action-btn-primary" @click="openAddDialog">
            <v-icon size="18">mdi-plus</v-icon>
            Add Exception
          </button>
        </div>
      </div>

      <template v-if="!isAdminOrHr && !hasAccess">
        <v-alert
          v-if="accessStatus === 'none'"
          type="info"
          variant="tonal"
          prominent
          class="ma-4"
          icon="mdi-lock-outline"
        >
          <v-alert-title>Access Required</v-alert-title>
          <p class="mt-1">
            You need approved module access before you can manage salary
            adjustment exceptions.
          </p>
          <v-btn
            color="primary"
            variant="flat"
            class="mt-3"
            prepend-icon="mdi-send"
            @click="accessRequestDialog = true"
          >
            Request Access
          </v-btn>
        </v-alert>

        <v-alert
          v-else-if="accessStatus === 'pending'"
          type="warning"
          variant="tonal"
          prominent
          class="ma-4"
          icon="mdi-clock-outline"
        >
          <v-alert-title>Pending Approval</v-alert-title>
          <p class="mt-1">
            Your access request is pending. You can manage exceptions once
            approved.
          </p>
        </v-alert>

        <v-alert
          v-else-if="accessStatus === 'rejected'"
          type="error"
          variant="tonal"
          prominent
          class="ma-4"
          icon="mdi-close-circle-outline"
        >
          <v-alert-title>Request Rejected</v-alert-title>
          <p class="mt-1">{{ accessMessage }}</p>
          <v-btn
            color="primary"
            variant="flat"
            class="mt-3"
            prepend-icon="mdi-send"
            @click="accessRequestDialog = true"
          >
            Submit New Request
          </v-btn>
        </v-alert>

        <v-expansion-panels v-model="accessRequestsPanel" class="mx-4 mb-4">
          <v-expansion-panel>
            <v-expansion-panel-title>
              <v-icon class="mr-2">mdi-history</v-icon>
              My Access Requests
            </v-expansion-panel-title>
            <v-expansion-panel-text>
              <v-list v-if="myAccessRequests.length > 0" density="compact">
                <v-list-item
                  v-for="req in myAccessRequests"
                  :key="req.id"
                  :subtitle="req.reason"
                >
                  <template v-slot:prepend>
                    <v-icon :color="getAccessRequestStatusColor(req.status)">
                      {{
                        req.status === "pending"
                          ? "mdi-clock-outline"
                          : req.status === "approved"
                            ? "mdi-check-circle"
                            : "mdi-close-circle"
                      }}
                    </v-icon>
                  </template>
                  <template v-slot:append>
                    <v-chip
                      :color="getAccessRequestStatusColor(req.status)"
                      size="x-small"
                      variant="flat"
                    >
                      {{ req.status }}
                    </v-chip>
                  </template>
                </v-list-item>
              </v-list>
              <p v-else class="text-center text-medium-emphasis py-4">
                No requests yet.
              </p>
            </v-expansion-panel-text>
          </v-expansion-panel>
        </v-expansion-panels>

        <v-dialog v-model="accessRequestDialog" max-width="500" persistent>
          <v-card rounded="lg">
            <v-card-title class="d-flex align-center pa-4">
              <v-icon color="primary" class="mr-2"
                >mdi-lock-open-variant</v-icon
              >
              Request Salary Exception Records Access
            </v-card-title>
            <v-divider></v-divider>
            <v-card-text class="pa-4">
              <p class="text-body-2 mb-4">
                Provide a reason for needing access to this module.
              </p>
              <v-textarea
                v-model="accessRequestReason"
                label="Reason"
                variant="outlined"
                rows="3"
                :rules="[(v) => !!v || 'Reason is required']"
                placeholder="Explain why you need access"
              ></v-textarea>
            </v-card-text>
            <v-divider></v-divider>
            <v-card-actions class="pa-4">
              <v-spacer></v-spacer>
              <v-btn variant="text" @click="closeAccessRequestDialog">
                Cancel
              </v-btn>
              <v-btn
                color="primary"
                variant="flat"
                :loading="submittingAccessRequest"
                :disabled="!accessRequestReason"
                prepend-icon="mdi-send"
                @click="submitAccessRequest"
              >
                Submit Request
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-dialog>
      </template>

      <template v-if="hasAccess">
        <div class="filters-section mb-4">
          <v-row dense>
            <v-col cols="12" md="4">
              <v-text-field
                v-model="search"
                prepend-inner-icon="mdi-magnify"
                label="Search employees..."
                hide-details
                variant="outlined"
                density="compact"
                clearable
              />
            </v-col>
            <v-col cols="12" md="3">
              <v-select
                v-model="typeFilter"
                :items="typeFilterOptions"
                label="Type"
                hide-details
                variant="outlined"
                density="compact"
              />
            </v-col>
            <v-col cols="12" md="3">
              <v-select
                v-model="statusFilter"
                :items="statusOptions"
                label="Status"
                hide-details
                variant="outlined"
                density="compact"
              />
            </v-col>
            <v-col cols="12" md="2" class="d-flex align-center">
              <v-btn
                variant="text"
                size="small"
                @click="clearFilters"
                :disabled="!hasActiveFilters"
              >
                Clear Filters
              </v-btn>
            </v-col>
          </v-row>
        </div>

        <v-data-table
          :headers="headers"
          :items="filteredAdjustments"
          :loading="loading"
          :search="search"
          class="modern-table"
          :items-per-page="15"
        >
          <template v-slot:item.employee="{ item }">
            <div>
              <div class="font-weight-medium">
                {{ item.employee?.full_name }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ item.employee?.employee_number }}
              </div>
            </div>
          </template>

          <template v-slot:item.amount="{ item }">
            <span
              class="font-weight-medium"
              :class="item.type === 'deduction' ? 'text-error' : 'text-success'"
            >
              {{ item.type === "deduction" ? "-" : "+" }}PHP
              {{ formatCurrency(getDisplayAmount(item)) }}
            </span>
          </template>

          <template v-slot:item.type="{ item }">
            <v-chip
              :color="item.type === 'deduction' ? 'warning' : 'info'"
              size="small"
              variant="tonal"
            >
              {{ item.type === "deduction" ? "Deduction" : "Addition" }}
            </v-chip>
          </template>

          <template v-slot:item.reference_period="{ item }">
            <div class="notes-cell">{{ getReferenceDisplay(item) }}</div>
          </template>

          <template v-slot:item.status="{ item }">
            <v-chip
              :color="getStatusColor(item.status)"
              size="small"
              variant="flat"
            >
              {{ getStatusLabel(item.status) }}
            </v-chip>
          </template>

          <template v-slot:item.effective_date="{ item }">
            {{ item.effective_date ? formatDate(item.effective_date) : "-" }}
          </template>

          <template v-slot:item.created_at="{ item }">
            {{ formatDate(item.created_at) }}
          </template>

          <template v-slot:item.notes="{ item }">
            <div class="notes-cell">{{ getNotesDisplay(item) }}</div>
          </template>

          <template v-slot:item.actions="{ item }">
            <v-menu>
              <template v-slot:activator="{ props }">
                <v-btn
                  icon="mdi-dots-vertical"
                  size="small"
                  variant="text"
                  v-bind="props"
                ></v-btn>
              </template>
              <v-list>
                <v-list-item @click="viewAdjustment(item)">
                  <template v-slot:prepend>
                    <v-icon size="small" color="info">mdi-eye</v-icon>
                  </template>
                  <v-list-item-title>View Details</v-list-item-title>
                </v-list-item>

                <v-list-item
                  v-if="item.status === 'pending' && canApproveAdjustment(item)"
                  @click="approveAdjustment(item)"
                >
                  <template v-slot:prepend>
                    <v-icon size="small" color="success"
                      >mdi-check-circle</v-icon
                    >
                  </template>
                  <v-list-item-title>Approve</v-list-item-title>
                </v-list-item>

                <v-list-item
                  v-if="item.status === 'pending' && canApproveAdjustment(item)"
                  @click="openRejectDialog(item)"
                >
                  <template v-slot:prepend>
                    <v-icon size="small" color="error">mdi-close-circle</v-icon>
                  </template>
                  <v-list-item-title class="text-error"
                    >Reject</v-list-item-title
                  >
                </v-list-item>
              </v-list>
            </v-menu>
          </template>

          <template v-slot:no-data>
            <div class="text-center py-8">
              <v-icon size="64" color="grey">mdi-cash-clock</v-icon>
              <p class="text-h6 mt-4">No exceptions found</p>
              <p class="text-body-2 text-medium-emphasis">
                No salary adjustment exception records match the current
                filters.
              </p>
            </div>
          </template>
        </v-data-table>
      </template>
    </div>

    <v-dialog v-model="dialog" max-width="700" persistent>
      <v-card class="modern-dialog adjustment-dialog">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper primary">
            <v-icon size="24">mdi-cash-plus</v-icon>
          </div>
          <div>
            <div class="dialog-title">Add Exception Record</div>
            <div class="dialog-subtitle">
              Admin-created records are auto-approved. Other roles require
              approval before payroll consumption.
            </div>
          </div>
        </v-card-title>

        <v-divider></v-divider>

        <v-card-text
          class="dialog-content salary-adjustment-dialog-content"
          style="max-height: 70vh"
        >
          <v-alert type="info" variant="tonal" density="compact" class="mb-4">
            This is a one-time prior-period correction. Reason and reference
            period are required. Admin-created records are auto-approved;
            otherwise approval is required. Applied records are consumed once in
            payroll.
          </v-alert>

          <v-alert
            v-if="overlapWarnings.length > 0"
            type="warning"
            variant="tonal"
            density="comfortable"
            class="mb-4"
          >
            <v-alert-title>Potential overlap detected</v-alert-title>
            <ul class="pl-4">
              <li v-for="warning in overlapWarnings" :key="warning">
                {{ warning }}
              </li>
            </ul>
          </v-alert>

          <v-form ref="formRef" v-model="formValid">
            <v-row>
              <v-col cols="12">
                <v-autocomplete
                  v-model="form.employee_id"
                  :items="employees"
                  item-title="full_name"
                  item-value="id"
                  label="Employee *"
                  :rules="[(v) => !!v || 'Employee is required']"
                  variant="outlined"
                  density="comfortable"
                  :loading="loadingEmployees"
                  :custom-filter="customEmployeeFilter"
                >
                  <template v-slot:item="{ item, props }">
                    <v-list-item v-bind="props">
                      <template v-slot:title>
                        <span class="font-weight-medium">{{
                          item.raw.full_name
                        }}</span>
                      </template>
                      <template v-slot:subtitle>
                        {{ item.raw.employee_number }} |
                        {{ item.raw.department }}
                      </template>
                    </v-list-item>
                  </template>
                </v-autocomplete>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="form.type"
                  :items="typeOptions"
                  label="Type *"
                  :rules="[(v) => !!v || 'Type is required']"
                  variant="outlined"
                  density="comfortable"
                />
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model.number="form.amount"
                  label="Amount *"
                  type="number"
                  :rules="[
                    (v) => !!v || 'Amount is required',
                    (v) => Number(v) > 0 || 'Amount must be greater than 0',
                  ]"
                  variant="outlined"
                  density="comfortable"
                  prefix="PHP"
                />
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="form.reference_period"
                  label="Reference Period *"
                  :rules="[
                    (v) =>
                      !!String(v || '').trim() ||
                      'Reference period is required',
                  ]"
                  variant="outlined"
                  density="comfortable"
                  placeholder="e.g., March 2026 - Cutoff 2"
                />
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="form.effective_date"
                  label="Effective Date"
                  type="date"
                  variant="outlined"
                  density="comfortable"
                />
              </v-col>

              <v-col cols="12">
                <v-text-field
                  v-model="form.reason"
                  label="Reason *"
                  :rules="[
                    (v) => !!String(v || '').trim() || 'Reason is required',
                  ]"
                  variant="outlined"
                  density="comfortable"
                />
              </v-col>

              <v-col cols="12">
                <v-textarea
                  v-model="form.notes"
                  label="Notes"
                  variant="outlined"
                  density="comfortable"
                  rows="3"
                />
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="dialog-actions salary-adjustment-dialog-actions">
          <v-spacer></v-spacer>
          <v-btn variant="outlined" color="grey" @click="closeDialog"
            >Cancel</v-btn
          >
          <v-btn
            color="#ED985F"
            variant="flat"
            :disabled="!formValid || saving"
            @click="saveAdjustment"
          >
            <v-progress-circular
              v-if="saving"
              indeterminate
              size="16"
              width="2"
              color="white"
            ></v-progress-circular>
            <v-icon v-else size="18">mdi-check</v-icon>
            {{ isAdmin ? "Create Exception" : "Submit For Approval" }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="rejectDialog" max-width="520" persistent>
      <v-card class="modern-dialog">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper danger">
            <v-icon size="24" color="white">mdi-close-circle</v-icon>
          </div>
          <div>
            <div class="dialog-title">Reject Exception</div>
            <div class="dialog-subtitle">Provide reason for rejection</div>
          </div>
        </v-card-title>
        <v-divider></v-divider>
        <v-card-text class="dialog-content">
          <v-textarea
            v-model="rejectReason"
            label="Rejection reason *"
            variant="outlined"
            rows="3"
            :rules="[(v) => !!v || 'Reason is required']"
          ></v-textarea>
        </v-card-text>
        <v-divider></v-divider>
        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <v-btn variant="outlined" color="grey" @click="closeRejectDialog">
            Cancel
          </v-btn>
          <v-btn
            color="error"
            variant="flat"
            :disabled="!rejectReason || processingApproval"
            @click="rejectAdjustment"
          >
            <v-progress-circular
              v-if="processingApproval"
              indeterminate
              size="16"
              width="2"
              color="white"
            ></v-progress-circular>
            <v-icon v-else size="18" color="white">mdi-close</v-icon>
            Reject
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="viewDialog" max-width="650">
      <v-card class="modern-dialog" v-if="selectedAdjustment">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper primary">
            <v-icon size="24">mdi-eye</v-icon>
          </div>
          <div>
            <div class="dialog-title">Exception Details</div>
            <div class="dialog-subtitle">
              {{ selectedAdjustment.employee?.full_name }}
            </div>
          </div>
        </v-card-title>
        <v-divider></v-divider>
        <v-card-text class="dialog-content">
          <div class="detail-grid">
            <div class="detail-item">
              <span class="detail-label">Employee</span>
              <span class="detail-value">{{
                selectedAdjustment.employee?.full_name
              }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Record</span>
              <span class="detail-value">{{
                getAdjustmentCategoryLabel(selectedAdjustment)
              }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Type</span>
              <span class="detail-value">
                {{
                  selectedAdjustment.type === "deduction"
                    ? "Deduction"
                    : "Addition"
                }}
              </span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Amount</span>
              <span class="detail-value">
                {{ selectedAdjustment.type === "deduction" ? "-" : "+" }}PHP
                {{ formatCurrency(getDisplayAmount(selectedAdjustment)) }}
              </span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Status</span>
              <span class="detail-value">{{
                getStatusLabel(selectedAdjustment.status)
              }}</span>
            </div>
            <div class="detail-item" v-if="selectedAdjustmentRateMeta">
              <span class="detail-label">Request Summary</span>
              <span class="detail-value">{{
                getRateRequestSummary(selectedAdjustment)
              }}</span>
            </div>
            <div
              class="detail-item"
              v-if="selectedAdjustmentRateMeta?.position_name"
            >
              <span class="detail-label">Position</span>
              <span class="detail-value">{{
                selectedAdjustmentRateMeta.position_name
              }}</span>
            </div>
            <div
              class="detail-item"
              v-if="selectedAdjustmentRateMeta?.requested_reason"
            >
              <span class="detail-label">Request Reason</span>
              <span class="detail-value pre-wrap">{{
                selectedAdjustmentRateMeta.requested_reason
              }}</span>
            </div>
            <div class="detail-item" v-if="selectedAdjustmentRateMeta">
              <span class="detail-label">Requested By</span>
              <span class="detail-value">{{
                formatRequestedBy(selectedAdjustmentRateMeta)
              }}</span>
            </div>
            <div
              class="detail-item"
              v-if="selectedAdjustmentRateMeta?.requested_at"
            >
              <span class="detail-label">Requested On</span>
              <span class="detail-value">{{
                formatDateTime(selectedAdjustmentRateMeta.requested_at)
              }}</span>
            </div>
            <div
              class="detail-item"
              v-if="selectedAdjustment.reason && !selectedAdjustmentRateMeta"
            >
              <span class="detail-label">Reason</span>
              <span class="detail-value">{{ selectedAdjustment.reason }}</span>
            </div>
            <div class="detail-item" v-if="selectedAdjustment.reference_period">
              <span class="detail-label">Reference</span>
              <span class="detail-value">{{
                getReferenceDisplay(selectedAdjustment)
              }}</span>
            </div>
            <div class="detail-item" v-if="selectedAdjustment.effective_date">
              <span class="detail-label">Effective Date</span>
              <span class="detail-value">{{
                formatDate(selectedAdjustment.effective_date)
              }}</span>
            </div>
            <div class="detail-item" v-if="getDetailNotes(selectedAdjustment)">
              <span class="detail-label">
                {{ selectedAdjustmentRateMeta ? "Additional Notes" : "Notes" }}
              </span>
              <span class="detail-value pre-wrap">{{
                getDetailNotes(selectedAdjustment)
              }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Created</span>
              <span class="detail-value">{{
                formatDate(selectedAdjustment.created_at)
              }}</span>
            </div>
          </div>
        </v-card-text>
        <v-divider></v-divider>
        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <v-btn variant="outlined" color="grey" @click="viewDialog = false">
            Close
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed, watch } from "vue";
import { useToast } from "vue-toastification";
import { useAuthStore } from "@/stores/auth";
import api from "@/services/api";
import { formatCurrency, formatDate } from "@/utils/formatters";
import moduleAccessService from "@/services/moduleAccessService";

const RATE_REQUEST_META_PREFIX = "[RATE_REQUEST_META]";
const RATE_REQUEST_TYPE_LABELS = {
  employee_custom_pay_rate_update: "Employee custom pay-rate update request",
  employee_custom_pay_rate_clear: "Employee custom pay-rate clear request",
  position_rate_update: "Position rate update request",
  position_rate_bulk_update: "Position bulk rate update request",
};

const toast = useToast();
const authStore = useAuthStore();
const isAdmin = computed(() => authStore.user?.role === "admin");
const isHr = computed(() => authStore.user?.role === "hr");
const isAdminOrHr = computed(() =>
  ["admin", "hr"].includes(authStore.user?.role),
);

const parseRateRequestMeta = (notes) => {
  if (typeof notes !== "string" || !notes.trim()) return null;

  const firstLine = notes.split(/\r?\n/)[0]?.trim() || "";
  if (!firstLine.startsWith(RATE_REQUEST_META_PREFIX)) {
    return null;
  }

  const rawMeta = firstLine.slice(RATE_REQUEST_META_PREFIX.length).trim();
  if (!rawMeta) {
    return null;
  }

  try {
    const parsed = JSON.parse(rawMeta);
    return parsed && typeof parsed === "object" ? parsed : null;
  } catch {
    return null;
  }
};

const getRateRequestMeta = (item) => parseRateRequestMeta(item?.notes);

const isRateRequestAdjustment = (item) => {
  return getRateRequestMeta(item) !== null;
};

const selectedAdjustmentRateMeta = computed(() =>
  parseRateRequestMeta(selectedAdjustment.value?.notes),
);

const formatCurrencyValue = (value) => {
  const parsed = Number(value);
  if (!Number.isFinite(parsed)) {
    return "0.00";
  }
  return formatCurrency(parsed);
};

const getRateRequestTypeLabel = (requestType) =>
  RATE_REQUEST_TYPE_LABELS[requestType] || "Rate change request";

const getAdjustmentCategoryLabel = (item) => {
  const meta = getRateRequestMeta(item);
  if (!meta) {
    return "Manual exception record";
  }

  return getRateRequestTypeLabel(meta.request_type);
};

const getRateRequestSummary = (item) => {
  const meta = getRateRequestMeta(item);
  if (!meta) {
    return item?.reason || "Manual exception record";
  }

  if (
    meta.request_type === "employee_custom_pay_rate_update" ||
    meta.request_type === "employee_custom_pay_rate_clear"
  ) {
    return `Employee rate: PHP ${formatCurrencyValue(
      meta.old_effective_rate,
    )} -> PHP ${formatCurrencyValue(meta.new_effective_rate)}`;
  }

  if (
    meta.request_type === "position_rate_update" ||
    meta.request_type === "position_rate_bulk_update"
  ) {
    return `Position daily rate: PHP ${formatCurrencyValue(
      meta.old_daily_rate,
    )} -> PHP ${formatCurrencyValue(meta.new_daily_rate)}`;
  }

  return getRateRequestTypeLabel(meta.request_type);
};

const getRateRequestDeltaAmount = (meta) => {
  if (!meta || typeof meta !== "object") {
    return null;
  }

  if (
    meta.request_type === "employee_custom_pay_rate_update" ||
    meta.request_type === "employee_custom_pay_rate_clear"
  ) {
    const oldRate = Number(meta.old_effective_rate);
    const newRate = Number(meta.new_effective_rate);
    if (Number.isFinite(oldRate) && Number.isFinite(newRate)) {
      return Math.round(Math.abs(newRate - oldRate) * 100) / 100;
    }
  }

  if (
    meta.request_type === "position_rate_update" ||
    meta.request_type === "position_rate_bulk_update"
  ) {
    const oldRate = Number(meta.old_daily_rate);
    const newRate = Number(meta.new_daily_rate);
    if (Number.isFinite(oldRate) && Number.isFinite(newRate)) {
      return Math.round(Math.abs(newRate - oldRate) * 100) / 100;
    }
  }

  return null;
};

const getDisplayAmount = (item) => {
  const metaAmount = getRateRequestDeltaAmount(getRateRequestMeta(item));
  if (metaAmount !== null) {
    return metaAmount;
  }

  const amount = Number(item?.amount);
  if (!Number.isFinite(amount)) {
    return 0;
  }

  return Math.abs(amount);
};

const formatRoleLabel = (role) => {
  if (!role) return "Unknown role";
  return String(role)
    .split("_")
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(" ");
};

const formatRequestedBy = (meta) => {
  if (!meta) return "Unknown";
  const roleLabel = formatRoleLabel(meta.requested_by_role);
  if (meta.requested_by) {
    return `User #${meta.requested_by} (${roleLabel})`;
  }
  return roleLabel;
};

const formatDateTime = (value) => {
  if (!value) return "-";

  const parsedDate = new Date(value);
  if (Number.isNaN(parsedDate.getTime())) {
    return value;
  }

  return parsedDate.toLocaleString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "numeric",
    minute: "2-digit",
  });
};

const getReferenceDisplay = (item) => {
  if (isRateRequestAdjustment(item)) {
    return "Approval workflow request";
  }

  return item?.reference_period || "-";
};

const extractRateRequestNotes = (notes) => {
  if (typeof notes !== "string" || !notes.trim()) {
    return "";
  }

  return notes
    .split(/\r?\n/)
    .slice(1)
    .map((line) => line.trim())
    .filter((line) => {
      const lowerLine = line.toLowerCase();
      return (
        !!line &&
        !lowerLine.startsWith("requested by user #") &&
        !lowerLine.startsWith("request type:") &&
        !lowerLine.startsWith("effective rate change:") &&
        !lowerLine.startsWith("daily rate change:") &&
        !lowerLine.startsWith("position:")
      );
    })
    .join("\n");
};

const getNotesDisplay = (item) => {
  if (!isRateRequestAdjustment(item)) {
    return item?.notes || "-";
  }

  const summary = getRateRequestSummary(item);
  const meta = getRateRequestMeta(item);
  if (meta?.requested_reason) {
    return `${summary}. Reason: ${meta.requested_reason}`;
  }

  return summary;
};

const getDetailNotes = (item) => {
  if (!item) return "";

  if (!isRateRequestAdjustment(item)) {
    return item.notes || "";
  }

  return extractRateRequestNotes(item.notes);
};

const canApproveAdjustment = (item) =>
  isAdmin.value || (isHr.value && !isRateRequestAdjustment(item));

const accessStatus = ref("none");
const accessMessage = ref("");
const accessRequestDialog = ref(false);
const accessRequestReason = ref("");
const submittingAccessRequest = ref(false);
const myAccessRequests = ref([]);
const accessRequestsPanel = ref(null);
const hasAccess = computed(
  () =>
    isAdminOrHr.value ||
    accessStatus.value === "approved" ||
    accessStatus.value === "admin",
);

const loading = ref(false);
const loadingEmployees = ref(false);
const saving = ref(false);
const processingApproval = ref(false);

const adjustments = ref([]);
const employees = ref([]);

const search = ref("");
const statusFilter = ref("all");
const typeFilter = ref("all");

const dialog = ref(false);
const rejectDialog = ref(false);
const viewDialog = ref(false);

const formRef = ref(null);
const formValid = ref(false);

const selectedAdjustment = ref(null);
const rejectReason = ref("");
const overlapWarnings = ref([]);
const checkingOverlap = ref(false);

const form = reactive({
  employee_id: null,
  amount: null,
  type: "deduction",
  reason: "",
  reference_period: "",
  effective_date: "",
  notes: "",
});

const headers = [
  { title: "Employee", key: "employee", sortable: true },
  { title: "Amount", key: "amount", sortable: true },
  { title: "Type", key: "type", sortable: true },
  { title: "Reference", key: "reference_period", sortable: false },
  { title: "Effective", key: "effective_date", sortable: true },
  { title: "Status", key: "status", sortable: true },
  { title: "Created", key: "created_at", sortable: true },
  { title: "Notes", key: "notes", sortable: false },
  { title: "Actions", key: "actions", sortable: false, width: "120px" },
];

const statusOptions = [
  { title: "All Statuses", value: "all" },
  { title: "Pending Approval", value: "pending" },
  { title: "Approved", value: "applied" },
  { title: "Rejected/Cancelled", value: "cancelled" },
];

const typeFilterOptions = [
  { title: "All Types", value: "all" },
  { title: "Deduction", value: "deduction" },
  { title: "Addition", value: "addition" },
];

const typeOptions = [
  { title: "Deduction", value: "deduction" },
  { title: "Addition", value: "addition" },
];

const hasActiveFilters = computed(
  () =>
    statusFilter.value !== "all" ||
    typeFilter.value !== "all" ||
    !!search.value,
);

const filteredAdjustments = computed(() => {
  let result = adjustments.value;
  if (statusFilter.value !== "all") {
    result = result.filter((a) => a.status === statusFilter.value);
  }
  if (typeFilter.value !== "all") {
    result = result.filter((a) => a.type === typeFilter.value);
  }
  return result;
});

const getAccessRequestStatusColor = (status) =>
  ({ pending: "warning", approved: "success", rejected: "error" })[status] ||
  "grey";

const getStatusLabel = (status) => {
  if (status === "pending") return "Pending Approval";
  if (status === "applied") return "Approved";
  if (status === "cancelled") return "Rejected/Cancelled";
  return status || "Unknown";
};

const getStatusColor = (status) => {
  if (status === "pending") return "warning";
  if (status === "applied") return "success";
  if (status === "cancelled") return "error";
  return "grey";
};

const customEmployeeFilter = (itemTitle, queryText, item) => {
  if (!queryText) return true;
  const keyword = queryText.toLowerCase();
  const fullName = item.raw.full_name?.toLowerCase() || "";
  const employeeNumber = item.raw.employee_number?.toLowerCase() || "";
  const department = item.raw.department?.toLowerCase() || "";
  const position = item.raw.position?.toLowerCase() || "";
  return (
    fullName.includes(keyword) ||
    employeeNumber.includes(keyword) ||
    department.includes(keyword) ||
    position.includes(keyword)
  );
};

const checkModuleAccess = async () => {
  if (isAdminOrHr.value) return;
  try {
    const response =
      await moduleAccessService.checkAccess("salary-adjustments");
    accessStatus.value = response.status;
    accessMessage.value = response.message || "";
  } catch {
    accessStatus.value = "none";
  }
};

const loadMyAccessRequests = async () => {
  if (isAdminOrHr.value) return;
  try {
    const response =
      await moduleAccessService.getRequests("salary-adjustments");
    myAccessRequests.value = response.data || [];
  } catch {
    myAccessRequests.value = [];
  }
};

const closeAccessRequestDialog = () => {
  accessRequestDialog.value = false;
  accessRequestReason.value = "";
};

const submitAccessRequest = async () => {
  if (!accessRequestReason.value) return;
  submittingAccessRequest.value = true;
  try {
    await moduleAccessService.submitRequest("salary-adjustments", {
      reason: accessRequestReason.value,
    });
    toast.success("Access request submitted successfully");
    closeAccessRequestDialog();
    accessStatus.value = "pending";
    await loadMyAccessRequests();
  } catch (error) {
    toast.error(error.response?.data?.message || "Failed to submit request");
  } finally {
    submittingAccessRequest.value = false;
  }
};

const fetchAdjustments = async ({ skipCache = false } = {}) => {
  loading.value = true;
  try {
    const response = await api.get("/salary-adjustments", {
      skipCache,
      cacheTTL: 15000,
    });
    adjustments.value = response.data.data || response.data || [];
  } catch {
    toast.error("Failed to load salary adjustment exceptions");
  } finally {
    loading.value = false;
  }
};

const fetchEmployees = async ({ force = false, skipCache = false } = {}) => {
  if (!force && employees.value.length > 0) return;
  if (loadingEmployees.value) return;

  loadingEmployees.value = true;
  try {
    const response = await api.get("/salary-adjustments/employees", {
      skipCache,
      cacheTTL: 120000,
    });
    employees.value = response.data || [];
  } catch {
    toast.error("Failed to load employees");
  } finally {
    loadingEmployees.value = false;
  }
};

const refreshData = async () => {
  await Promise.all([
    fetchAdjustments({ skipCache: true }),
    fetchEmployees({ force: true, skipCache: true }),
  ]);
};

const clearFilters = () => {
  search.value = "";
  statusFilter.value = "all";
  typeFilter.value = "all";
};

const resetForm = () => {
  form.employee_id = null;
  form.amount = null;
  form.type = "deduction";
  form.reason = "";
  form.reference_period = "";
  form.effective_date = "";
  form.notes = "";
  overlapWarnings.value = [];
  if (formRef.value) {
    formRef.value.resetValidation();
  }
};

const openAddDialog = async () => {
  await fetchEmployees();
  resetForm();
  dialog.value = true;
};

const closeDialog = () => {
  dialog.value = false;
  resetForm();
};

const checkOverlapWarning = async () => {
  overlapWarnings.value = [];
  if (!form.employee_id) return;

  checkingOverlap.value = true;
  try {
    const response = await api.get("/salary-adjustments/overlap-check", {
      params: {
        employee_id: form.employee_id,
        effective_date: form.effective_date || undefined,
      },
    });
    overlapWarnings.value = response.data?.warnings || [];
  } catch {
    overlapWarnings.value = [];
  } finally {
    checkingOverlap.value = false;
  }
};

const saveAdjustment = async () => {
  const { valid } = await formRef.value.validate();
  if (!valid) return;

  saving.value = true;
  try {
    const payload = {
      ...form,
      reason: String(form.reason || "").trim(),
      reference_period: String(form.reference_period || "").trim(),
    };

    const response = await api.post("/salary-adjustments", payload);
    toast.success(response.data?.message || "Exception submitted for approval");
    closeDialog();
    await fetchAdjustments({ skipCache: true });
  } catch (error) {
    const message =
      error.response?.data?.message || "Failed to submit exception";
    toast.error(message);
  } finally {
    saving.value = false;
  }
};

const viewAdjustment = (item) => {
  selectedAdjustment.value = item;
  viewDialog.value = true;
};

const approveAdjustment = async (item) => {
  processingApproval.value = true;
  try {
    const response = await api.post(`/salary-adjustments/${item.id}/approve`);
    toast.success(response.data?.message || "Exception approved");
    await fetchAdjustments({ skipCache: true });
  } catch (error) {
    toast.error(error.response?.data?.message || "Failed to approve exception");
  } finally {
    processingApproval.value = false;
  }
};

const openRejectDialog = (item) => {
  selectedAdjustment.value = item;
  rejectReason.value = "";
  rejectDialog.value = true;
};

const closeRejectDialog = () => {
  rejectDialog.value = false;
  rejectReason.value = "";
};

const rejectAdjustment = async () => {
  if (!selectedAdjustment.value || !rejectReason.value) return;

  processingApproval.value = true;
  try {
    const response = await api.post(
      `/salary-adjustments/${selectedAdjustment.value.id}/reject`,
      { reason: rejectReason.value },
    );
    toast.success(response.data?.message || "Exception rejected");
    closeRejectDialog();
    await fetchAdjustments({ skipCache: true });
  } catch (error) {
    toast.error(error.response?.data?.message || "Failed to reject exception");
  } finally {
    processingApproval.value = false;
  }
};

watch(
  () => [form.employee_id, form.effective_date],
  () => {
    checkOverlapWarning();
  },
);

onMounted(async () => {
  await checkModuleAccess();
  await loadMyAccessRequests();

  if (hasAccess.value) {
    await fetchAdjustments();
    fetchEmployees();
  }
});
</script>

<style scoped lang="scss">
.salary-adjustments-page {
  background-color: #f8f9fa;
  min-height: 100vh;
}

.modern-card {
  padding: 24px;
  background: white;
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid rgba(0, 31, 61, 0.08);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.page-header {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 32px;
  padding-bottom: 24px;
  border-bottom: 1px solid rgba(0, 31, 61, 0.08);
}

.page-icon-badge {
  width: 48px;
  height: 48px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);
  flex-shrink: 0;

  :deep(.v-icon) {
    color: white !important;
  }
}

.page-header-content {
  flex: 1;
  min-width: 0;
}

.page-title {
  font-size: 24px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
  line-height: 1.2;
}

.page-subtitle {
  font-size: 14px;
  color: #64748b;
  margin: 4px 0 0 0;
}

.action-buttons {
  display: flex;
  align-items: center;
  gap: 8px;
}

.filters-section {
  margin-bottom: 24px;
}

.modern-table {
  border-radius: 12px;
  overflow: hidden;

  :deep(th) {
    background-color: #f8f9fa !important;
    color: #001f3d !important;
    font-weight: 600 !important;
    text-transform: uppercase;
    font-size: 12px;
    letter-spacing: 0.5px;
  }

  :deep(.v-data-table__tr:hover) {
    background-color: rgba(237, 152, 95, 0.04) !important;
  }
}

.notes-cell {
  max-width: 280px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
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

.modern-dialog {
  border-radius: 16px !important;
  overflow: hidden;
}

.dialog-header {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px;
  background: white;
  border-bottom: 1px solid rgba(0, 0, 0, 0.05);
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
    background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
    box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);

    .v-icon {
      color: white;
    }
  }

  &.danger {
    background: linear-gradient(135deg, #ef4444 0%, #f87171 100%);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.25);

    .v-icon {
      color: white;
    }
  }

  &.info {
    background: linear-gradient(135deg, #0ea5e9 0%, #38bdf8 100%);
    box-shadow: 0 4px 12px rgba(14, 165, 233, 0.25);

    .v-icon {
      color: white;
    }
  }
}

.dialog-title {
  font-size: 20px;
  font-weight: 700;
  color: #001f3d;
  line-height: 1.2;
}

.dialog-subtitle {
  font-size: 14px;
  color: #64748b;
  margin-top: 4px;
}

.dialog-content {
  padding: 20px;
  background: #fafafa;
}

.salary-adjustment-dialog-content {
  padding-bottom: 10px;
}

.salary-adjustment-dialog-actions {
  position: sticky;
  bottom: 0;
  z-index: 2;
  background: #ffffff;
  border-top: 1px solid rgba(0, 31, 61, 0.08);
}

.dialog-actions {
  padding: 14px 20px;
  background: rgba(0, 31, 61, 0.02);
  gap: 10px;
}

.detail-grid {
  display: flex;
  flex-direction: column;
  gap: 14px;
}

.detail-item {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 16px;
  padding: 10px 14px;
  background: #ffffff;
  border-radius: 8px;
  border: 1px solid rgba(0, 31, 61, 0.08);
}

.detail-label {
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
}

.detail-value {
  font-size: 14px;
  color: #001f3d;
  text-align: right;
}

.pre-wrap {
  white-space: pre-wrap;
}

@media (max-width: 960px) {
  .page-header {
    flex-wrap: wrap;
    align-items: flex-start;
    gap: 14px;
  }

  .action-buttons {
    width: 100%;
    justify-content: flex-end;
  }

  .action-btn {
    width: 100%;
    justify-content: center;
  }
}
</style>
