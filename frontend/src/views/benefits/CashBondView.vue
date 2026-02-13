<template>
  <div class="cash-bond-page">
    <div class="modern-card">
      <!-- Modern Page Header -->
      <div class="page-header">
        <div class="page-icon-badge">
          <v-icon icon="mdi-cash-lock" size="24" color="white"></v-icon>
        </div>
        <div class="page-header-content">
          <h1 class="page-title">Cash Bond Management</h1>
          <p class="page-subtitle">
            Track and manage employee cash bonds and refunds
          </p>
        </div>
        <button
          v-if="userRole !== 'employee'"
          class="action-btn action-btn-primary"
          @click="openAddDialog"
        >
          <v-icon size="20">mdi-cash-plus</v-icon>
          <span>Add Cash Bond</span>
        </button>
      </div>

      <!-- Summary Cards -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon primary">
            <v-icon size="20">mdi-lock-check</v-icon>
          </div>
          <div class="stat-content">
            <div class="stat-label">Active Bonds</div>
            <div class="stat-value">{{ summary.active_count || 0 }}</div>
            <div class="stat-sublabel">
              ₱{{ formatNumber(summary.active_total || 0) }}
            </div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon warning">
            <v-icon size="20">mdi-alert-circle</v-icon>
          </div>
          <div class="stat-content">
            <div class="stat-label">Outstanding Balance</div>
            <div class="stat-value warning-text">
              ₱{{ formatNumber(summary.outstanding_balance || 0) }}
            </div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon success">
            <v-icon size="20">mdi-check-circle</v-icon>
          </div>
          <div class="stat-content">
            <div class="stat-label">Completed Bonds</div>
            <div class="stat-value">{{ summary.completed_count || 0 }}</div>
            <div class="stat-sublabel">
              ₱{{ formatNumber(summary.completed_total || 0) }}
            </div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon info">
            <v-icon size="20">mdi-cash-check</v-icon>
          </div>
          <div class="stat-content">
            <div class="stat-label">Total Collected</div>
            <div class="stat-value info-text">
              ₱{{ formatNumber(summary.total_collected || 0) }}
            </div>
          </div>
        </div>
      </div>

      <!-- Filters -->
      <div class="filters-section">
        <v-row>
          <v-col cols="12" md="4" v-if="userRole !== 'employee'">
            <v-autocomplete
              v-model="filters.employee_id"
              :items="employees"
              item-title="full_name"
              item-value="id"
              label="Filter by Employee"
              variant="outlined"
              density="comfortable"
              clearable
              hide-details
              @update:model-value="fetchCashBonds"
            ></v-autocomplete>
          </v-col>
          <v-col cols="12" :md="userRole === 'employee' ? 6 : 4">
            <v-select
              v-model="filters.status"
              :items="statusOptions"
              label="Filter by Status"
              variant="outlined"
              density="comfortable"
              clearable
              hide-details
              @update:model-value="fetchCashBonds"
            ></v-select>
          </v-col>
          <v-col cols="auto" class="d-flex align-center">
            <v-btn
              color="error"
              variant="tonal"
              icon="mdi-filter-remove"
              @click="clearFilters"
              title="Clear Filters"
            ></v-btn>
          </v-col>
        </v-row>
      </div>

      <!-- Cash Bonds Table -->
      <v-data-table
        :headers="headers"
        :items="cashBonds"
        :loading="loading"
        :items-per-page="15"
        class="modern-table"
      >
        <template v-slot:item.employee="{ item }">
          <div>
            <div class="font-weight-medium">{{ item.employee?.full_name }}</div>
            <div class="text-caption text-medium-emphasis">
              {{ item.employee?.employee_number }}
            </div>
          </div>
        </template>

        <template v-slot:item.total_amount="{ item }">
          <span class="font-weight-bold text-primary"
            >₱{{ formatNumber(item.total_amount) }}</span
          >
        </template>

        <template v-slot:item.amount_per_cutoff="{ item }">
          <span class="font-weight-medium"
            >₱{{ formatNumber(item.amount_per_cutoff) }}</span
          >
        </template>

        <template v-slot:item.balance="{ item }">
          <div>
            <span
              :class="
                item.balance > 0
                  ? 'text-warning font-weight-bold'
                  : 'text-success font-weight-bold'
              "
            >
              ₱{{ formatNumber(item.balance) }}
            </span>
            <div class="text-caption text-medium-emphasis">
              Paid: ₱{{ formatNumber(item.total_amount - item.balance) }}
            </div>
          </div>
        </template>

        <template v-slot:item.progress="{ item }">
          <div class="d-flex align-center">
            <v-progress-linear
              :model-value="getProgress(item)"
              :color="item.status === 'completed' ? 'success' : 'primary'"
              height="10"
              rounded
              class="mr-2"
              style="min-width: 100px"
            ></v-progress-linear>
            <span class="text-caption font-weight-medium"
              >{{ item.installments_paid }}/{{ item.installments }}</span
            >
          </div>
        </template>

        <template v-slot:item.dates="{ item }">
          <div>
            <div class="text-caption">
              <strong>Start:</strong> {{ formatDate(item.start_date) }}
            </div>
            <div class="text-caption">
              <strong>End:</strong> {{ formatDate(item.end_date) }}
            </div>
          </div>
        </template>

        <template v-slot:item.status="{ item }">
          <v-chip
            :color="getStatusColor(item.status)"
            size="small"
            variant="flat"
          >
            {{ formatStatus(item.status) }}
          </v-chip>
        </template>

        <template v-slot:item.actions="{ item }">
          <v-menu>
            <template v-slot:activator="{ props }">
              <v-btn
                icon="mdi-dots-vertical"
                variant="text"
                size="small"
                v-bind="props"
              ></v-btn>
            </template>
            <v-list>
              <v-list-item @click="viewDetails(item)">
                <v-list-item-title>
                  <v-icon size="small" class="mr-2">mdi-eye</v-icon>
                  View Details
                </v-list-item-title>
              </v-list-item>
              <v-list-item
                v-if="item.status === 'active' && userRole !== 'employee'"
                @click="openRefundDialog(item)"
              >
                <v-list-item-title>
                  <v-icon size="small" class="mr-2">mdi-cash-refund</v-icon>
                  Refund/Return
                </v-list-item-title>
              </v-list-item>
              <v-list-item
                v-if="item.status === 'active' && userRole !== 'employee'"
                @click="openEditDialog(item)"
              >
                <v-list-item-title>
                  <v-icon size="small" class="mr-2">mdi-pencil</v-icon>
                  Edit
                </v-list-item-title>
              </v-list-item>
              <v-divider v-if="userRole !== 'employee'"></v-divider>
              <v-list-item
                v-if="
                  userRole !== 'employee' &&
                  item.status !== 'completed' &&
                  item.installments_paid === 0
                "
                @click="confirmDelete(item)"
                class="text-error"
              >
                <v-list-item-title>
                  <v-icon size="small" class="mr-2">mdi-delete</v-icon>
                  Delete
                </v-list-item-title>
              </v-list-item>
            </v-list>
          </v-menu>
        </template>
      </v-data-table>
    </div>

    <!-- Add/Edit Cash Bond Dialog -->
    <v-dialog v-model="dialog" max-width="900px" persistent scrollable>
      <v-card class="modern-dialog">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper primary">
            <v-icon size="24">{{
              editMode ? "mdi-pencil" : "mdi-cash-lock"
            }}</v-icon>
          </div>
          <div>
            <div class="dialog-title">
              {{ editMode ? "Edit Cash Bond" : "Add Cash Bond" }}
            </div>
            <div class="dialog-subtitle">
              {{
                editMode
                  ? "Update cash bond details"
                  : "Create new cash bond for employee"
              }}
            </div>
          </div>
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="dialog-content" style="max-height: 70vh">
          <v-form ref="bondFormRef" v-model="formValid">
            <v-row>
              <v-col cols="12" class="pb-0">
                <div class="section-header">
                  <div class="section-icon">
                    <v-icon size="18">mdi-account-cash</v-icon>
                  </div>
                  <h3 class="section-title">Bond Information</h3>
                </div>
              </v-col>

              <!-- Employee Selection -->
              <v-col cols="12">
                <v-autocomplete
                  v-model="form.employee_id"
                  :items="employees"
                  item-title="full_name"
                  item-value="id"
                  label="Search and Select Employee *"
                  placeholder="Search by name, employee number, or position"
                  variant="outlined"
                  density="comfortable"
                  :rules="[rules.required]"
                  :disabled="editMode"
                  clearable
                  prepend-inner-icon="mdi-account-search"
                  hint="Search by name, employee number, or position"
                  persistent-hint
                >
                  <template v-slot:item="{ props, item }">
                    <v-list-item v-bind="props">
                      <template v-slot:prepend>
                        <v-avatar color="primary" size="40">
                          <span class="text-white text-subtitle-2">
                            {{ getInitials(item.raw.full_name) }}
                          </span>
                        </v-avatar>
                      </template>
                      <template v-slot:title>
                        <span class="font-weight-medium">{{
                          item.raw.full_name
                        }}</span>
                      </template>
                      <template v-slot:subtitle>
                        <v-chip
                          size="x-small"
                          class="mr-1"
                          color="primary"
                          variant="outlined"
                        >
                          {{ item.raw.employee_number }}
                        </v-chip>
                        <span class="text-caption">{{
                          item.raw.position || "N/A"
                        }}</span>
                      </template>
                    </v-list-item>
                  </template>
                </v-autocomplete>
              </v-col>

              <!-- Total Amount -->
              <v-col cols="12" md="6">
                <v-text-field
                  v-model.number="form.total_amount"
                  type="number"
                  label="Total Cash Bond Amount *"
                  prefix="₱"
                  placeholder="0.00"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-currency-php"
                  :rules="[rules.required, rules.positiveNumber]"
                  @input="calculateInstallments"
                ></v-text-field>
              </v-col>

              <!-- Amount per Cutoff -->
              <v-col cols="12" md="6">
                <v-text-field
                  v-model.number="form.amount_per_cutoff"
                  type="number"
                  label="Amount per Cutoff *"
                  prefix="₱"
                  placeholder="0.00"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-cash"
                  :rules="[rules.required, rules.positiveNumber]"
                  @input="calculateInstallments"
                  hint="Semi-monthly deduction amount"
                  persistent-hint
                ></v-text-field>
              </v-col>

              <!-- Number of Installments -->
              <v-col cols="12" md="6">
                <v-text-field
                  v-model.number="form.installments"
                  label="Number of Installments"
                  type="number"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-numeric"
                  readonly
                  hint="Auto-calculated from amount fields"
                  persistent-hint
                >
                  <template #append-inner>
                    <v-chip size="x-small" color="info">Auto-calculated</v-chip>
                  </template>
                </v-text-field>
              </v-col>

              <!-- Reference Number -->
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="form.reference_number"
                  label="Reference Number"
                  placeholder="Auto-generated if left blank"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-identifier"
                  hint="Leave blank to auto-generate"
                  persistent-hint
                ></v-text-field>
              </v-col>

              <!-- Section: Payment Schedule -->
              <v-col cols="12" class="pb-0 pt-2">
                <div class="section-header">
                  <div class="section-icon">
                    <v-icon size="18">mdi-calendar-clock</v-icon>
                  </div>
                  <h3 class="section-title">Payment Schedule</h3>
                </div>
              </v-col>

              <!-- Start Date -->
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="form.start_date"
                  label="Start Date *"
                  type="date"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-calendar-start"
                  :rules="[rules.required]"
                ></v-text-field>
              </v-col>

              <!-- Description -->
              <v-col cols="12">
                <v-textarea
                  v-model="form.description"
                  label="Description"
                  placeholder="Reason for cash bond"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-text-box"
                  rows="2"
                ></v-textarea>
              </v-col>

              <!-- Additional Notes -->
              <v-col cols="12">
                <v-textarea
                  v-model="form.notes"
                  label="Additional Notes (Optional)"
                  placeholder="Any additional information"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-note-text"
                  rows="2"
                ></v-textarea>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <button
            class="dialog-btn dialog-btn-cancel"
            @click="closeDialog"
            :disabled="saving"
          >
            Cancel
          </button>
          <button
            class="dialog-btn dialog-btn-primary"
            @click="saveCashBond"
            :disabled="!formValid || saving"
          >
            <v-progress-circular
              v-if="saving"
              indeterminate
              size="16"
              width="2"
              class="mr-2"
            ></v-progress-circular>
            <v-icon v-else size="18" class="mr-1">{{
              editMode ? "mdi-check" : "mdi-content-save"
            }}</v-icon>
            {{
              saving
                ? editMode
                  ? "Updating..."
                  : "Creating..."
                : editMode
                  ? "Update Cash Bond"
                  : "Create Cash Bond"
            }}
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Refund Dialog -->
    <v-dialog v-model="refundDialog" max-width="500px" persistent>
      <v-card>
        <v-card-title class="bg-success">
          <span class="text-h6">Refund/Return Cash Bond</span>
        </v-card-title>
        <v-card-text class="pt-4">
          <v-alert type="info" variant="tonal" class="mb-4">
            <div class="text-caption">
              Employee: <strong>{{ refundForm.employee_name }}</strong>
            </div>
            <div class="text-caption">
              Total Bond:
              <strong>₱{{ formatNumber(refundForm.total_amount) }}</strong>
            </div>
            <div class="text-caption">
              Already Deducted:
              <strong>₱{{ formatNumber(refundForm.amount_paid) }}</strong>
            </div>
            <div class="text-caption">
              Current Balance:
              <strong>₱{{ formatNumber(refundForm.current_balance) }}</strong>
            </div>
          </v-alert>

          <v-form ref="refundFormRef" v-model="refundFormValid">
            <v-text-field
              v-model="refundForm.refund_amount"
              label="Refund Amount *"
              variant="outlined"
              type="number"
              prefix="₱"
              :rules="[rules.required, rules.positiveNumber, rules.maxRefund]"
              class="mb-2"
              hint="Amount to return to employee (usually the remaining balance)"
              persistent-hint
            ></v-text-field>

            <v-text-field
              v-model="refundForm.refund_date"
              label="Refund Date *"
              variant="outlined"
              type="date"
              :rules="[rules.required]"
              class="mb-2"
            ></v-text-field>

            <v-textarea
              v-model="refundForm.refund_reason"
              label="Refund Reason"
              variant="outlined"
              rows="2"
              placeholder="e.g., Employee resignation, Contract completion"
              class="mb-2"
            ></v-textarea>
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="closeRefundDialog">Cancel</v-btn>
          <v-btn
            color="success"
            :loading="refunding"
            :disabled="!refundFormValid"
            @click="processRefund"
          >
            Process Refund
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Details Dialog -->
    <v-dialog v-model="detailsDialog" max-width="700px">
      <v-card v-if="selectedBond">
        <v-card-title class="bg-primary">
          <span class="text-h6">Cash Bond Details</span>
        </v-card-title>
        <v-card-text class="pt-4">
          <v-row>
            <v-col cols="12" md="6">
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">Employee</div>
                <div class="font-weight-medium">
                  {{ selectedBond.employee?.full_name }}
                </div>
                <div class="text-caption">
                  {{ selectedBond.employee?.employee_number }}
                </div>
              </div>
            </v-col>
            <v-col cols="12" md="6">
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">Status</div>
                <v-chip
                  :color="getStatusColor(selectedBond.status)"
                  size="small"
                  class="mt-1"
                >
                  {{ formatStatus(selectedBond.status) }}
                </v-chip>
              </div>
            </v-col>
            <v-col cols="12" md="6">
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">
                  Total Amount
                </div>
                <div class="text-h6 text-primary">
                  ₱{{ formatNumber(selectedBond.total_amount) }}
                </div>
              </div>
            </v-col>
            <v-col cols="12" md="6">
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">
                  Amount per Cutoff
                </div>
                <div class="text-h6">
                  ₱{{ formatNumber(selectedBond.amount_per_cutoff) }}
                </div>
              </div>
            </v-col>
            <v-col cols="12" md="6">
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">Balance</div>
                <div
                  class="text-h6"
                  :class="
                    selectedBond.balance > 0 ? 'text-warning' : 'text-success'
                  "
                >
                  ₱{{ formatNumber(selectedBond.balance) }}
                </div>
              </div>
            </v-col>
            <v-col cols="12" md="6">
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">Progress</div>
                <div>
                  <v-progress-linear
                    :model-value="getProgress(selectedBond)"
                    :color="
                      selectedBond.status === 'completed'
                        ? 'success'
                        : 'primary'
                    "
                    height="15"
                    rounded
                    class="mb-1"
                  >
                    <template v-slot:default>
                      <span class="text-caption"
                        >{{ Math.round(getProgress(selectedBond)) }}%</span
                      >
                    </template>
                  </v-progress-linear>
                  <div class="text-caption">
                    {{ selectedBond.installments_paid }} of
                    {{ selectedBond.installments }} installments paid
                  </div>
                </div>
              </div>
            </v-col>
            <v-col cols="12" md="6">
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">Start Date</div>
                <div>{{ formatDate(selectedBond.start_date) }}</div>
              </div>
            </v-col>
            <v-col cols="12" md="6">
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">End Date</div>
                <div>{{ formatDate(selectedBond.end_date) }}</div>
              </div>
            </v-col>
            <v-col cols="12" v-if="selectedBond.reference_number">
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">
                  Reference Number
                </div>
                <div>{{ selectedBond.reference_number }}</div>
              </div>
            </v-col>
            <v-col cols="12" v-if="selectedBond.description">
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">Description</div>
                <div>{{ selectedBond.description }}</div>
              </div>
            </v-col>
            <v-col cols="12" v-if="selectedBond.notes">
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">Notes</div>
                <div class="text-caption" style="white-space: pre-wrap">
                  {{ selectedBond.notes }}
                </div>
              </div>
            </v-col>
          </v-row>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="detailsDialog = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Snackbar for notifications -->
    <v-snackbar v-model="snackbar" :color="snackbarColor" :timeout="3000">
      {{ snackbarMessage }}
    </v-snackbar>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useAuthStore } from "@/stores/auth";
import api from "@/services/api";
import { formatDate, formatNumber } from "@/utils/formatters";
import { devLog } from "@/utils/devLog";
import { useConfirmDialog } from "@/composables/useConfirmDialog";

const authStore = useAuthStore();
const { confirm: confirmDialog } = useConfirmDialog();
const userRole = computed(() => authStore.user?.role);

// Data
const cashBonds = ref([]);
const employees = ref([]);
const loading = ref(false);
const dialog = ref(false);
const refundDialog = ref(false);
const detailsDialog = ref(false);
const editMode = ref(false);
const bondFormRef = ref(null);
const formValid = ref(false);
const refundFormValid = ref(false);
const saving = ref(false);
const refunding = ref(false);
const selectedBond = ref(null);

const snackbar = ref(false);
const snackbarMessage = ref("");
const snackbarColor = ref("success");

// Summary data
const summary = ref({
  active_count: 0,
  active_total: 0,
  outstanding_balance: 0,
  completed_count: 0,
  completed_total: 0,
  total_collected: 0,
});

// Filters
const filters = ref({
  employee_id: null,
  status: null,
});

const statusOptions = [
  { title: "Active", value: "active" },
  { title: "Completed", value: "completed" },
  { title: "Cancelled", value: "cancelled" },
];

// Form
const form = ref({
  id: null,
  employee_id: null,
  total_amount: null,
  amount_per_cutoff: null,
  installments: null,
  start_date: new Date().toISOString().substr(0, 10),
  reference_number: "",
  description: "",
  notes: "",
});

const refundForm = ref({
  deduction_id: null,
  employee_name: "",
  total_amount: 0,
  amount_paid: 0,
  current_balance: 0,
  refund_amount: 0,
  refund_date: new Date().toISOString().substr(0, 10),
  refund_reason: "",
});

// Validation rules
const rules = {
  required: (value) => !!value || "Required",
  positiveNumber: (value) => value > 0 || "Must be greater than 0",
  maxRefund: (value) =>
    value <= refundForm.value.current_balance ||
    "Cannot exceed current balance",
};

// Table headers
const headers = computed(() => {
  const baseHeaders = [
    { title: "Employee", key: "employee", sortable: true },
    { title: "Total Amount", key: "total_amount", sortable: true },
    { title: "Per Cutoff", key: "amount_per_cutoff", sortable: true },
    { title: "Balance", key: "balance", sortable: true },
    { title: "Progress", key: "progress", sortable: false },
    { title: "Dates", key: "dates", sortable: false },
    { title: "Status", key: "status", sortable: true },
    { title: "Actions", key: "actions", sortable: false, align: "center" },
  ];

  if (userRole.value === "employee") {
    return baseHeaders.filter((h) => h.key !== "employee");
  }

  return baseHeaders;
});

// Methods
const fetchCashBonds = async () => {
  loading.value = true;
  try {
    const params = {};
    if (filters.value.employee_id)
      params.employee_id = filters.value.employee_id;
    if (filters.value.status) params.status = filters.value.status;

    const response = await api.get("/cash-bonds", { params });
    cashBonds.value = response.data.data;
    calculateSummary();
  } catch (error) {
    showSnackbar("Failed to fetch cash bonds: " + error.message, "error");
  } finally {
    loading.value = false;
  }
};

const fetchEmployees = async () => {
  try {
    const response = await api.get("/employees", {
      params: { per_page: 1000 },
    });
    employees.value = response.data.data;
  } catch (error) {
    devLog.error("Failed to fetch employees:", error);
  }
};

const calculateSummary = () => {
  const active = cashBonds.value.filter((b) => b.status === "active");
  const completed = cashBonds.value.filter((b) => b.status === "completed");

  summary.value = {
    active_count: active.length,
    active_total: active.reduce(
      (sum, b) => sum + parseFloat(b.total_amount),
      0,
    ),
    outstanding_balance: active.reduce(
      (sum, b) => sum + parseFloat(b.balance),
      0,
    ),
    completed_count: completed.length,
    completed_total: completed.reduce(
      (sum, b) => sum + parseFloat(b.total_amount),
      0,
    ),
    total_collected: cashBonds.value.reduce(
      (sum, b) => sum + (parseFloat(b.total_amount) - parseFloat(b.balance)),
      0,
    ),
  };
};

const calculateInstallments = () => {
  if (form.value.total_amount && form.value.amount_per_cutoff) {
    form.value.installments = Math.ceil(
      form.value.total_amount / form.value.amount_per_cutoff,
    );
  }
};

const openAddDialog = () => {
  editMode.value = false;
  resetForm();
  dialog.value = true;
  // Reset form validation after dialog opens
  setTimeout(() => {
    bondFormRef.value?.resetValidation();
  }, 100);
};

const openEditDialog = (bond) => {
  editMode.value = true;
  Object.assign(form.value, {
    id: bond.id,
    employee_id: bond.employee_id,
    total_amount: bond.total_amount,
    amount_per_cutoff: bond.amount_per_cutoff,
    installments: bond.installments,
    start_date: bond.start_date,
    reference_number: bond.reference_number || "",
    description: bond.description || "",
    notes: bond.notes || "",
  });
  dialog.value = true;
  // Reset form validation after dialog opens
  setTimeout(() => {
    bondFormRef.value?.resetValidation();
  }, 100);
};

const openRefundDialog = (bond) => {
  Object.assign(refundForm.value, {
    deduction_id: bond.id,
    employee_name: bond.employee.full_name,
    total_amount: bond.total_amount,
    amount_paid: bond.total_amount - bond.balance,
    current_balance: bond.balance,
    refund_amount: bond.balance,
    refund_date: new Date().toISOString().substr(0, 10),
    refund_reason: "",
  });
  refundDialog.value = true;
};

const closeDialog = () => {
  dialog.value = false;
  bondFormRef.value?.reset();
  resetForm();
};

const closeRefundDialog = () => {
  refundDialog.value = false;
  Object.assign(refundForm.value, {
    deduction_id: null,
    employee_name: "",
    total_amount: 0,
    amount_paid: 0,
    current_balance: 0,
    refund_amount: 0,
    refund_date: new Date().toISOString().substr(0, 10),
    refund_reason: "",
  });
};

const resetForm = () => {
  Object.assign(form.value, {
    id: null,
    employee_id: null,
    total_amount: null,
    amount_per_cutoff: null,
    installments: null,
    start_date: new Date().toISOString().substr(0, 10),
    reference_number: "",
    description: "",
    notes: "",
  });
};

const saveCashBond = async () => {
  // Validate form before submission
  const { valid } = await bondFormRef.value?.validate();
  if (!valid) {
    showSnackbar("Please fill in all required fields correctly", "error");
    return;
  }

  saving.value = true;
  try {
    // Convert reactive form to plain object to avoid circular structure error
    const payload = {
      employee_id: form.value.employee_id,
      total_amount: form.value.total_amount,
      amount_per_cutoff: form.value.amount_per_cutoff,
      installments: form.value.installments,
      start_date: form.value.start_date,
      reference_number: form.value.reference_number,
      description: form.value.description,
      notes: form.value.notes,
    };

    if (editMode.value) {
      await api.put(`/deductions/${form.value.id}`, payload);
      showSnackbar("Cash bond updated successfully", "success");
    } else {
      await api.post("/cash-bonds", payload);
      showSnackbar("Cash bond created successfully", "success");
    }
    closeDialog();
    fetchCashBonds();
  } catch (error) {
    showSnackbar(
      "Failed to save cash bond: " +
        (error.response?.data?.message || error.message),
      "error",
    );
  } finally {
    saving.value = false;
  }
};

const processRefund = async () => {
  refunding.value = true;
  try {
    await api.post(
      `/deductions/${refundForm.value.deduction_id}/refund-cash-bond`,
      {
        refund_amount: refundForm.value.refund_amount,
        refund_date: refundForm.value.refund_date,
        refund_reason: refundForm.value.refund_reason,
      },
    );
    showSnackbar("Cash bond refunded successfully", "success");
    closeRefundDialog();
    fetchCashBonds();
  } catch (error) {
    showSnackbar(
      "Failed to refund cash bond: " +
        (error.response?.data?.message || error.message),
      "error",
    );
  } finally {
    refunding.value = false;
  }
};

const viewDetails = (bond) => {
  selectedBond.value = bond;
  detailsDialog.value = true;
};

const confirmDelete = async (bond) => {
  if (
    await confirmDialog(
      `Are you sure you want to delete this cash bond for ${bond.employee.full_name}?`,
    )
  ) {
    try {
      await api.delete(`/deductions/${bond.id}`);
      showSnackbar("Cash bond deleted successfully", "success");
      fetchCashBonds();
    } catch (error) {
      showSnackbar(
        "Failed to delete cash bond: " +
          (error.response?.data?.message || error.message),
        "error",
      );
    }
  }
};

const clearFilters = () => {
  filters.value = {
    employee_id: null,
    status: null,
  };
  fetchCashBonds();
};

const getProgress = (bond) => {
  if (bond.installments === 0) return 0;
  return (bond.installments_paid / bond.installments) * 100;
};

const getStatusColor = (status) => {
  const colors = {
    active: "primary",
    completed: "success",
    cancelled: "error",
  };
  return colors[status] || "grey";
};

const formatStatus = (status) => {
  return status.charAt(0).toUpperCase() + status.slice(1);
};

const getInitials = (name) => {
  if (!name) return "??";
  const parts = name.trim().split(" ");
  if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
};

const showSnackbar = (message, color = "success") => {
  snackbarMessage.value = message;
  snackbarColor.value = color;
  snackbar.value = true;
};

// Lifecycle
onMounted(() => {
  fetchCashBonds();
  if (userRole.value !== "employee") {
    fetchEmployees();
  }
});
</script>

<style lang="scss" scoped>
.cash-bond-page {
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

.action-button {
  text-transform: none;
  font-weight: 600;
  letter-spacing: 0;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.2);
  transition: all 0.2s ease;

  &:hover {
    box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);
    transform: translateY(-1px);
  }
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

  &:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(237, 152, 95, 0.2);
    border-color: rgba(237, 152, 95, 0.3);

    &::before {
      transform: scaleY(1);
    }
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

  &.primary {
    background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);

    :deep(.v-icon) {
      color: white;
    }
  }

  &.warning {
    background: rgba(245, 158, 11, 0.1);

    :deep(.v-icon) {
      color: #f59e0b;
    }
  }

  &.success {
    background: rgba(16, 185, 129, 0.1);

    :deep(.v-icon) {
      color: #10b981;
    }
  }

  &.info {
    background: rgba(59, 130, 246, 0.1);

    :deep(.v-icon) {
      color: #3b82f6;
    }
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
  margin-bottom: 2px;

  &.warning-text {
    color: #f59e0b;
  }

  &.info-text {
    color: #3b82f6;
  }
}

.stat-sublabel {
  font-size: 12px;
  color: rgba(0, 31, 61, 0.5);
  font-weight: 500;
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

/* Modern Dialog Styles */
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

.section-header {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  background: linear-gradient(
    135deg,
    rgba(0, 31, 61, 0.02) 0%,
    rgba(237, 152, 95, 0.02) 100%
  );
  border-radius: 8px;
  margin-bottom: 12px;
}

.section-icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.25);
  flex-shrink: 0;

  .v-icon {
    color: white;
  }
}

.section-title {
  font-size: 15px;
  font-weight: 600;
  color: #001f3d;
  margin: 0;
}

.dialog-actions {
  padding: 14px 20px;
  background: rgba(0, 31, 61, 0.02);
  gap: 10px;
}

.dialog-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px 24px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  border: none;
  white-space: nowrap;
  min-width: 120px;

  &:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }

  &.dialog-btn-cancel {
    background: white;
    color: #64748b;
    border: 1px solid #e2e8f0;

    &:hover:not(:disabled) {
      background: #f8f9fa;
      border-color: #cbd5e1;
    }
  }

  &.dialog-btn-primary {
    background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
    color: white;
    box-shadow: 0 2px 8px rgba(237, 152, 95, 0.25);

    &:hover:not(:disabled) {
      box-shadow: 0 4px 12px rgba(237, 152, 95, 0.35);
      transform: translateY(-1px);
    }

    .v-icon {
      color: white;
    }
  }
}
</style>
