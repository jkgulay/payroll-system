<template>
  <div class="salary-adjustments-page">
    <div class="modern-card">
      <!-- Page Header -->
      <div class="page-header">
        <div class="header-content">
          <div class="page-title-section">
            <div class="page-icon-badge">
              <v-icon size="28">mdi-cash-plus</v-icon>
            </div>
            <div>
              <h1 class="page-title">Salary Adjustments</h1>
              <p class="page-subtitle">
                Manage salary adjustments for previous payroll periods
              </p>
            </div>
          </div>
          <div class="action-buttons">
            <v-btn
              variant="text"
              @click="refreshData"
              :loading="loading"
              icon="mdi-refresh"
              size="small"
            ></v-btn>
            <button
              class="action-btn action-btn-primary"
              @click="openAddDialog"
            >
              <v-icon size="18">mdi-plus</v-icon>
              Add Adjustment
            </button>
          </div>
        </div>
      </div>

      <!-- Filters -->
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

      <!-- Adjustments Table -->
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
            <div class="font-weight-medium">{{ item.employee?.full_name }}</div>
            <div class="text-caption text-medium-emphasis">
              {{ item.employee?.employee_number }}
            </div>
          </div>
        </template>

        <template v-slot:item.amount="{ item }">
          <span class="font-weight-medium">
            <span
              :class="
                item.adjustment_type === 'deduction'
                  ? 'text-error'
                  : 'text-success'
              "
            >
              {{ item.adjustment_type === "deduction" ? "-" : "+" }}₱{{
                formatCurrency(item.amount)
              }}
            </span>
          </span>
        </template>

        <template v-slot:item.adjustment_type="{ item }">
          <v-chip
            :color="item.adjustment_type === 'deduction' ? 'warning' : 'info'"
            size="small"
            variant="tonal"
          >
            {{
              item.adjustment_type === "deduction" ? "Deduction" : "Addition"
            }}
          </v-chip>
        </template>

        <template v-slot:item.status="{ item }">
          <v-chip
            :color="getStatusColor(item.status)"
            size="small"
            variant="flat"
          >
            {{ capitalizeFirst(item.status) }}
          </v-chip>
        </template>

        <template v-slot:item.created_at="{ item }">
          {{ formatDate(item.created_at) }}
        </template>

        <template v-slot:item.actions="{ item }">
          <v-btn
            v-if="item.status === 'pending'"
            icon="mdi-pencil"
            size="small"
            variant="text"
            @click="openEditDialog(item)"
          ></v-btn>
          <v-btn
            v-if="item.status === 'pending'"
            icon="mdi-delete"
            size="small"
            variant="text"
            color="error"
            @click="confirmDelete(item)"
          ></v-btn>
          <v-btn
            icon="mdi-eye"
            size="small"
            variant="text"
            color="info"
            @click="viewAdjustment(item)"
          ></v-btn>
        </template>

        <template v-slot:no-data>
          <div class="text-center py-8">
            <v-icon size="64" color="grey">mdi-cash-plus</v-icon>
            <p class="text-h6 mt-4">No adjustments found</p>
            <p class="text-body-2 text-medium-emphasis">
              No salary adjustments match your current filters
            </p>
          </div>
        </template>
      </v-data-table>
    </div>

    <!-- Add/Edit Dialog -->
    <v-dialog v-model="dialog" max-width="650" persistent scrollable>
      <v-card class="modern-dialog">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper primary">
            <v-icon size="24">{{
              isEditing ? "mdi-pencil" : "mdi-cash-plus"
            }}</v-icon>
          </div>
          <div>
            <div class="dialog-title">
              {{ isEditing ? "Edit Adjustment" : "Add Salary Adjustment" }}
            </div>
            <div class="dialog-subtitle">
              {{
                isEditing
                  ? "Update adjustment details"
                  : "Create a new salary adjustment for an employee"
              }}
            </div>
          </div>
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="dialog-content" style="max-height: 70vh">
          <v-form ref="formRef" v-model="formValid">
            <v-row>
              <v-col cols="12">
                <div class="section-header">
                  <div class="section-icon">
                    <v-icon size="18">mdi-account-cash</v-icon>
                  </div>
                  <h3 class="section-title">Adjustment Information</h3>
                </div>
              </v-col>

              <v-col cols="12">
                <v-autocomplete
                  v-model="form.employee_id"
                  :items="employees"
                  item-title="full_name"
                  item-value="id"
                  label="Select Employee *"
                  placeholder="Search by name or employee number"
                  :rules="[(v) => !!v || 'Employee is required']"
                  variant="outlined"
                  density="comfortable"
                  :disabled="isEditing"
                  :loading="loadingEmployees"
                  clearable
                  prepend-inner-icon="mdi-account-search"
                  no-data-text="No employees found"
                >
                  <template v-slot:item="{ item, props }">
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
                        <span class="text-caption">{{
                          item.raw.department || "N/A"
                        }}</span>
                        <span class="mx-1">|</span>
                        <span class="text-caption"
                          >₱{{
                            formatCurrency(item.raw.basic_salary)
                          }}/day</span
                        >
                        <span
                          v-if="item.raw.pending_adjustments != 0"
                          class="ml-2 text-warning"
                        >
                          (Pending: ₱{{
                            formatCurrency(
                              Math.abs(item.raw.pending_adjustments),
                            )
                          }})
                        </span>
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
                  prepend-inner-icon="mdi-swap-vertical"
                />
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model.number="form.amount"
                  label="Amount *"
                  type="number"
                  prefix="₱"
                  :rules="[
                    (v) => !!v || 'Amount is required',
                    (v) => v > 0 || 'Amount must be greater than 0',
                  ]"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-currency-php"
                />
              </v-col>

              <v-col cols="12">
                <v-text-field
                  v-model="form.reason"
                  label="Reason"
                  placeholder="e.g., Previous salary underpayment, correction for Jan 2026"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-text-box-outline"
                />
              </v-col>

              <v-col cols="12">
                <v-text-field
                  v-model="form.reference_period"
                  label="Reference Period"
                  placeholder="e.g., January 2026 - Cutoff 1"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-calendar-range"
                />
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <button class="dialog-btn dialog-btn-cancel" @click="closeDialog">
            Cancel
          </button>
          <button
            class="dialog-btn dialog-btn-primary"
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
            <v-icon v-else size="18">{{
              isEditing ? "mdi-check" : "mdi-plus"
            }}</v-icon>
            {{ isEditing ? "Update" : "Create" }}
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete Confirmation Dialog -->
    <v-dialog v-model="deleteDialog" max-width="450">
      <v-card class="modern-dialog">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper danger">
            <v-icon size="24" color="white">mdi-delete-alert</v-icon>
          </div>
          <div>
            <div class="dialog-title">Confirm Delete</div>
            <div class="dialog-subtitle">This action cannot be undone</div>
          </div>
        </v-card-title>
        <v-divider></v-divider>
        <v-card-text class="dialog-content">
          <p>
            Are you sure you want to delete this salary adjustment for
            <strong>{{ selectedAdjustment?.employee?.full_name }}</strong
            >?
          </p>
        </v-card-text>
        <v-divider></v-divider>
        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <button
            class="dialog-btn dialog-btn-cancel"
            @click="deleteDialog = false"
          >
            Cancel
          </button>
          <button
            class="dialog-btn dialog-btn-danger"
            :disabled="deleting"
            @click="deleteAdjustment"
          >
            <v-progress-circular
              v-if="deleting"
              indeterminate
              size="16"
              width="2"
              color="white"
            ></v-progress-circular>
            <v-icon v-else size="18" color="white">mdi-delete</v-icon>
            Delete
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- View Dialog -->
    <v-dialog v-model="viewDialog" max-width="550">
      <v-card class="modern-dialog" v-if="selectedAdjustment">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper primary">
            <v-icon size="24">mdi-eye</v-icon>
          </div>
          <div>
            <div class="dialog-title">Adjustment Details</div>
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
              <span class="detail-label">Type</span>
              <span class="detail-value">
                <v-chip
                  :color="
                    selectedAdjustment.adjustment_type === 'deduction'
                      ? 'warning'
                      : 'info'
                  "
                  size="small"
                  variant="tonal"
                >
                  {{
                    selectedAdjustment.adjustment_type === "deduction"
                      ? "Deduction"
                      : "Addition"
                  }}
                </v-chip>
              </span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Amount</span>
              <span
                class="detail-value font-weight-bold"
                :class="
                  selectedAdjustment.adjustment_type === 'deduction'
                    ? 'text-error'
                    : 'text-success'
                "
              >
                {{
                  selectedAdjustment.adjustment_type === "deduction"
                    ? "-"
                    : "+"
                }}₱{{ formatCurrency(selectedAdjustment.amount) }}
              </span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Status</span>
              <span class="detail-value">
                <v-chip
                  :color="getStatusColor(selectedAdjustment.status)"
                  size="small"
                  variant="flat"
                >
                  {{ capitalizeFirst(selectedAdjustment.status) }}
                </v-chip>
              </span>
            </div>
            <div class="detail-item" v-if="selectedAdjustment.reason">
              <span class="detail-label">Reason</span>
              <span class="detail-value">{{ selectedAdjustment.reason }}</span>
            </div>
            <div class="detail-item" v-if="selectedAdjustment.description">
              <span class="detail-label">Reference Period</span>
              <span class="detail-value">{{
                selectedAdjustment.description
              }}</span>
            </div>
            <div class="detail-item" v-if="selectedAdjustment.applied_payroll">
              <span class="detail-label">Applied to Payroll</span>
              <span class="detail-value">{{
                selectedAdjustment.applied_payroll.period_name
              }}</span>
            </div>
            <div class="detail-item">
              <span class="detail-label">Created By</span>
              <span class="detail-value">{{
                selectedAdjustment.created_by?.name || "System"
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
          <button
            class="dialog-btn dialog-btn-cancel"
            @click="viewDialog = false"
          >
            Close
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from "vue";
import { useToast } from "vue-toastification";
import api from "@/services/api";
import { formatCurrency, formatDate } from "@/utils/formatters";

const toast = useToast();

// State
const loading = ref(false);
const loadingEmployees = ref(false);
const saving = ref(false);
const deleting = ref(false);
const adjustments = ref([]);
const employees = ref([]);
const search = ref("");
const statusFilter = ref("all");
const typeFilter = ref("all");
const dialog = ref(false);
const deleteDialog = ref(false);
const viewDialog = ref(false);
const formRef = ref(null);
const formValid = ref(false);
const isEditing = ref(false);
const selectedAdjustment = ref(null);

const form = reactive({
  id: null,
  employee_id: null,
  amount: null,
  type: "deduction",
  reason: "",
  reference_period: "",
});

const headers = [
  { title: "Employee", key: "employee", sortable: true },
  { title: "Amount", key: "amount", sortable: true },
  { title: "Type", key: "adjustment_type", sortable: true },
  { title: "Reason", key: "reason", sortable: false },
  { title: "Reference Period", key: "description", sortable: false },
  { title: "Status", key: "status", sortable: true },
  { title: "Created", key: "created_at", sortable: true },
  { title: "Actions", key: "actions", sortable: false, width: "130px" },
];

const statusOptions = [
  { title: "All Statuses", value: "all" },
  { title: "Pending", value: "pending" },
  { title: "Applied", value: "applied" },
  { title: "Cancelled", value: "cancelled" },
];

const typeFilterOptions = [
  { title: "All Types", value: "all" },
  { title: "Deduction", value: "deduction" },
  { title: "Addition", value: "addition" },
];

const typeOptions = [
  { title: "Deduction (subtract from salary)", value: "deduction" },
  { title: "Addition (add to salary)", value: "addition" },
];

// Computed
const hasActiveFilters = computed(() => {
  return (
    statusFilter.value !== "all" || typeFilter.value !== "all" || !!search.value
  );
});

const filteredAdjustments = computed(() => {
  let result = adjustments.value;
  if (statusFilter.value !== "all") {
    result = result.filter((a) => a.status === statusFilter.value);
  }
  if (typeFilter.value !== "all") {
    result = result.filter((a) => a.adjustment_type === typeFilter.value);
  }
  return result;
});

// Methods
const capitalizeFirst = (str) => {
  if (!str) return "";
  return str.charAt(0).toUpperCase() + str.slice(1);
};

const getStatusColor = (status) => {
  switch (status) {
    case "pending":
      return "warning";
    case "applied":
      return "success";
    case "cancelled":
      return "grey";
    default:
      return "grey";
  }
};

const getInitials = (name) => {
  if (!name) return "?";
  return name
    .split(" ")
    .map((n) => n[0])
    .join("")
    .substring(0, 2)
    .toUpperCase();
};

const clearFilters = () => {
  search.value = "";
  statusFilter.value = "all";
  typeFilter.value = "all";
};

const fetchAdjustments = async () => {
  loading.value = true;
  try {
    const response = await api.get("/salary-adjustments");
    adjustments.value = response.data.data || response.data;
  } catch (error) {
    toast.error("Failed to load salary adjustments");
  } finally {
    loading.value = false;
  }
};

const fetchEmployees = async () => {
  loadingEmployees.value = true;
  try {
    const response = await api.get("/salary-adjustments/employees");
    employees.value = response.data;
  } catch (error) {
    toast.error("Failed to load employees");
  } finally {
    loadingEmployees.value = false;
  }
};

const refreshData = () => {
  fetchAdjustments();
  fetchEmployees();
};

const openAddDialog = () => {
  isEditing.value = false;
  resetForm();
  dialog.value = true;
};

const openEditDialog = (item) => {
  isEditing.value = true;
  form.id = item.id;
  form.employee_id = item.employee_id;
  form.amount = item.amount;
  form.type = item.adjustment_type;
  form.reason = item.reason || "";
  form.reference_period = item.description || "";
  dialog.value = true;
};

const closeDialog = () => {
  dialog.value = false;
  resetForm();
};

const resetForm = () => {
  form.id = null;
  form.employee_id = null;
  form.amount = null;
  form.type = "deduction";
  form.reason = "";
  form.reference_period = "";
  if (formRef.value) {
    formRef.value.resetValidation();
  }
};

const saveAdjustment = async () => {
  if (!formRef.value?.validate()) return;

  saving.value = true;
  try {
    if (isEditing.value) {
      await api.put(`/salary-adjustments/${form.id}`, form);
      toast.success("Salary adjustment updated successfully");
    } else {
      await api.post("/salary-adjustments", form);
      toast.success("Salary adjustment created successfully");
    }
    closeDialog();
    fetchAdjustments();
  } catch (error) {
    toast.error(error.response?.data?.message || "Failed to save adjustment");
  } finally {
    saving.value = false;
  }
};

const confirmDelete = (item) => {
  selectedAdjustment.value = item;
  deleteDialog.value = true;
};

const deleteAdjustment = async () => {
  if (!selectedAdjustment.value) return;

  deleting.value = true;
  try {
    await api.delete(`/salary-adjustments/${selectedAdjustment.value.id}`);
    toast.success("Salary adjustment deleted successfully");
    deleteDialog.value = false;
    fetchAdjustments();
  } catch (error) {
    toast.error(error.response?.data?.message || "Failed to delete adjustment");
  } finally {
    deleting.value = false;
  }
};

const viewAdjustment = (item) => {
  selectedAdjustment.value = item;
  viewDialog.value = true;
};

// Lifecycle
onMounted(() => {
  fetchAdjustments();
  fetchEmployees();
});
</script>

<style lang="scss" scoped>
@import "@/styles/_shared-layout.scss";

.salary-adjustments-page {
  max-width: 1400px;
  margin: 0 auto;
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

// View Dialog Details
.detail-grid {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.detail-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 10px 16px;
  background: white;
  border-radius: 8px;
  border: 1px solid rgba(0, 31, 61, 0.06);
}

.detail-label {
  font-size: 13px;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.detail-value {
  font-size: 14px;
  color: #001f3d;
}

// Delete dialog button
.dialog-btn-danger {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white;
  display: inline-flex;
  align-items: center;
  gap: 8px;

  &:hover:not(:disabled) {
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.35);
    transform: translateY(-1px);
  }
}
</style>
