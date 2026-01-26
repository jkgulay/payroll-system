<template>
  <div class="payroll-page">
    <!-- Modern Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="page-title-section">
          <div class="page-icon-badge">
            <v-icon size="22">mdi-cash-multiple</v-icon>
          </div>
          <div>
            <h1 class="page-title">Payroll Management</h1>
            <p class="page-subtitle">
              Create, manage and process payroll for all employees
            </p>
          </div>
        </div>
        <div class="action-buttons">
          <button
            class="action-btn action-btn-primary"
            @click="openCreateDialog"
          >
            <v-icon size="20">mdi-plus</v-icon>
            <span>Create Payroll</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Modern Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon total">
          <v-icon size="20">mdi-cash-multiple</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Total Payrolls</div>
          <div class="stat-value">{{ stats.total }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon draft">
          <v-icon size="20">mdi-file-edit-outline</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Draft</div>
          <div class="stat-value warning">{{ stats.draft }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon finalized">
          <v-icon size="20">mdi-file-check-outline</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Finalized</div>
          <div class="stat-value info">{{ stats.finalized }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon paid">
          <v-icon size="20">mdi-check-circle</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Paid</div>
          <div class="stat-value success">{{ stats.paid }}</div>
        </div>
      </div>
    </div>

    <!-- Payroll List -->
    <div class="modern-card">
      <div class="filters-section">
        <v-row align="center" class="mb-0">
          <v-col cols="12" md="4">
            <v-text-field
              v-model="search"
              prepend-inner-icon="mdi-magnify"
              label="Search payroll..."
              clearable
              variant="outlined"
              density="comfortable"
              hide-details
            ></v-text-field>
          </v-col>
          <v-col cols="auto">
            <v-btn
              color="#ED985F"
              variant="tonal"
              icon="mdi-refresh"
              @click="loadPayrolls"
              :loading="loading"
              title="Refresh"
            ></v-btn>
          </v-col>
        </v-row>
      </div>

      <div class="table-section">
        <v-data-table
          :headers="headers"
          :items="payrolls"
          :search="search"
          :loading="loading"
          :items-per-page="15"
          class="elevation-1"
        >
          <!-- Status -->
          <template v-slot:item.status="{ item }">
            <v-chip
              :color="getStatusColor(item.status)"
              size="small"
              variant="flat"
            >
              {{ item.status.toUpperCase() }}
            </v-chip>
          </template>

          <!-- Period -->
          <template v-slot:item.period="{ item }">
            <div>
              <div class="font-weight-medium">{{ item.period_name }}</div>
              <div class="text-caption text-medium-emphasis">
                {{ formatDate(item.period_start) }} -
                {{ formatDate(item.period_end) }}
              </div>
            </div>
          </template>

          <!-- Payment Date -->
          <template v-slot:item.payment_date="{ item }">
            {{ formatDate(item.payment_date) }}
          </template>

          <!-- Employees Count -->
          <template v-slot:item.items_count="{ item }">
            <v-chip size="small" variant="outlined">
              {{ item.items_count }} employees
            </v-chip>
          </template>

          <!-- Total Net -->
          <template v-slot:item.total_net="{ item }">
            <div class="text-right font-weight-bold">
              ₱{{ formatCurrency(item.total_net) }}
            </div>
          </template>

          <!-- Actions -->
          <template v-slot:item.actions="{ item }">
            <v-btn
              icon="mdi-eye"
              size="small"
              variant="text"
              color="primary"
              @click="viewPayroll(item)"
            >
            </v-btn>
            <v-btn
              v-if="item.status === 'draft'"
              icon="mdi-pencil"
              size="small"
              variant="text"
              color="warning"
              @click="editPayroll(item)"
            >
            </v-btn>
            <v-btn
              icon="mdi-delete"
              size="small"
              variant="text"
              color="error"
              @click="confirmDelete(item)"
            >
            </v-btn>
          </template>
        </v-data-table>
      </div>
    </div>

    <!-- Create/Edit Dialog - Modern UI -->
    <v-dialog v-model="dialog" max-width="800px" persistent scrollable>
      <v-card class="modern-dialog">
        <!-- Enhanced Header -->
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper primary">
            <v-icon size="24">{{
              editMode ? "mdi-pencil" : "mdi-cash-multiple"
            }}</v-icon>
          </div>
          <div>
            <div class="dialog-title">
              {{ editMode ? "Edit Payroll" : "Create Payroll" }}
            </div>
            <div class="dialog-subtitle">
              {{
                editMode
                  ? "Update payroll period details"
                  : "Generate new payroll period"
              }}
            </div>
          </div>
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="dialog-content" style="max-height: 70vh">
          <v-form ref="form" v-model="valid">
            <!-- Section 1: Basic Information -->
            <v-col cols="12" class="px-0">
              <div class="section-header">
                <div class="section-icon">
                  <v-icon size="18">mdi-information</v-icon>
                </div>
                <h3 class="section-title">Basic Information</h3>
              </div>
            </v-col>

            <!-- Period Name -->
            <div class="form-field-wrapper mt-3">
              <label class="form-label">
                <v-icon size="small" color="#ed985f">mdi-label</v-icon>
                Period Name <span class="text-error">*</span>
              </label>
              <v-text-field
                v-model="formData.period_name"
                placeholder="Enter period name"
                :rules="[(v) => !!v || 'Period name is required']"
                required
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-label"
                color="#ed985f"
              ></v-text-field>
            </div>

            <v-row>
              <!-- Period Start -->
              <v-col cols="6">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="#ed985f"
                      >mdi-calendar-start</v-icon
                    >
                    Period Start <span class="text-error">*</span>
                  </label>
                  <v-text-field
                    v-model="formData.period_start"
                    type="date"
                    placeholder="Select start date"
                    :rules="[(v) => !!v || 'Start date is required']"
                    required
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-calendar-start"
                    color="#ed985f"
                  ></v-text-field>
                </div>
              </v-col>

              <!-- Period End -->
              <v-col cols="6">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="#ed985f"
                      >mdi-calendar-end</v-icon
                    >
                    Period End <span class="text-error">*</span>
                  </label>
                  <v-text-field
                    v-model="formData.period_end"
                    type="date"
                    placeholder="Select end date"
                    :rules="[(v) => !!v || 'End date is required']"
                    required
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-calendar-end"
                    color="#ed985f"
                  ></v-text-field>
                </div>
              </v-col>
            </v-row>

            <!-- Payment Date -->
            <div class="form-field-wrapper">
              <label class="form-label">
                <v-icon size="small" color="#ed985f">mdi-calendar-check</v-icon>
                Payment Date <span class="text-error">*</span>
              </label>
              <v-text-field
                v-model="formData.payment_date"
                type="date"
                placeholder="Select payment date"
                :rules="[(v) => !!v || 'Payment date is required']"
                required
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-calendar-check"
                color="#ed985f"
              ></v-text-field>
            </div>

            <!-- Section 2: Employee Filter -->
            <v-col cols="12" class="px-0 mt-5">
              <div class="section-header">
                <div class="section-icon">
                  <v-icon size="18">mdi-filter</v-icon>
                </div>
                <h3 class="section-title">Employee Filter</h3>
              </div>
            </v-col>

            <v-radio-group
              v-model="formData.filter_type"
              inline
              hide-details
              class="mb-4"
            >
              <v-radio label="All Employees" value="all"></v-radio>
              <v-radio label="By Department" value="department"></v-radio>
              <v-radio label="By Staff Type" value="staff_type"></v-radio>
            </v-radio-group>

            <!-- Employee Limit -->
            <v-text-field
              v-model.number="formData.employee_limit"
              label="Limit Number of Employees (Optional)"
              type="number"
              min="1"
              max="1000"
              prepend-icon="mdi-account-multiple-check"
              hint="Leave empty to include all matching employees, or specify a number to limit"
              persistent-hint
              clearable
              class="mb-4"
            >
              <template v-slot:append-inner>
                <v-tooltip location="top">
                  <template v-slot:activator="{ props }">
                    <v-icon
                      v-bind="props"
                      icon="mdi-information"
                      size="small"
                      color="#ed985f"
                    ></v-icon>
                  </template>
                  <div style="max-width: 300px">
                    Useful for creating partial payrolls or testing.<br />
                    Employees are selected in order by employee number.
                  </div>
                </v-tooltip>
              </template>
            </v-text-field>

            <!-- Position Filter -->
            <v-autocomplete
              v-if="formData.filter_type === 'position'"
              v-model="formData.position_ids"
              :items="positions"
              :loading="loadingPositions"
              item-title="position_name"
              item-value="id"
              label="Select Positions/Roles"
              prepend-icon="mdi-account-hard-hat"
              multiple
              chips
              closable-chips
              :rules="[
                (v) =>
                  formData.filter_type !== 'position' ||
                  (v && v.length > 0) ||
                  'Select at least one position',
              ]"
              hint="Select one or more positions to include in payroll"
              persistent-hint
            >
              <template v-slot:chip="{ props, item }">
                <v-chip
                  v-bind="props"
                  :text="item.raw.position_name"
                  size="small"
                ></v-chip>
              </template>
              <template v-slot:item="{ props, item }">
                <v-list-item v-bind="props">
                  <template v-slot:title>
                    {{ item.raw.position_name }}
                  </template>
                  <template v-slot:subtitle>
                    {{ item.raw.code }} - ₱{{ item.raw.daily_rate }}/day
                  </template>
                </v-list-item>
              </template>
            </v-autocomplete>

            <!-- Project Filter -->
            <v-autocomplete
              v-if="formData.filter_type === 'project'"
              v-model="formData.project_ids"
              :items="projects"
              :loading="loadingProjects"
              item-title="name"
              item-value="id"
              label="Select Projects"
              prepend-icon="mdi-briefcase"
              multiple
              chips
              closable-chips
              :rules="[
                (v) =>
                  formData.filter_type !== 'project' ||
                  (v && v.length > 0) ||
                  'Select at least one project',
              ]"
              hint="Select one or more projects to include in payroll"
              persistent-hint
            >
              <template v-slot:chip="{ props, item }">
                <v-chip
                  v-bind="props"
                  :text="item.raw.name"
                  size="small"
                ></v-chip>
              </template>
              <template v-slot:item="{ props, item }">
                <v-list-item v-bind="props">
                  <template v-slot:title>
                    {{ item.raw.name }}
                  </template>
                  <template v-slot:subtitle>
                    {{ item.raw.code }} -
                    {{ item.raw.employees_count || 0 }} employees
                  </template>
                </v-list-item>
              </template>
            </v-autocomplete>

            <!-- Department Filter -->
            <v-autocomplete
              v-if="formData.filter_type === 'department'"
              v-model="formData.departments"
              :items="departmentOptions"
              label="Select Departments"
              prepend-icon="mdi-office-building"
              multiple
              chips
              closable-chips
              :rules="[
                (v) =>
                  formData.filter_type !== 'department' ||
                  (v && v.length > 0) ||
                  'Select at least one department',
              ]"
              hint="Select one or more departments to include in payroll"
              persistent-hint
            ></v-autocomplete>

            <!-- Staff Type Filter -->
            <v-autocomplete
              v-if="formData.filter_type === 'staff_type'"
              v-model="formData.staff_types"
              :items="staffTypeOptions"
              label="Select Staff Types"
              prepend-icon="mdi-account-group"
              multiple
              chips
              closable-chips
              :rules="[
                (v) =>
                  formData.filter_type !== 'staff_type' ||
                  (v && v.length > 0) ||
                  'Select at least one staff type',
              ]"
              hint="Select one or more staff types to include in payroll"
              persistent-hint
            ></v-autocomplete>

            <v-divider class="my-4"></v-divider>

            <!-- Additional Filters -->
            <v-checkbox
              v-model="formData.has_attendance"
              label="Only include employees with attendance"
              prepend-icon="mdi-calendar-check"
              hint="Exclude employees who have no attendance records in this payroll period"
              persistent-hint
              color="#ed985f"
            ></v-checkbox>

            <!-- Info Alert -->
            <v-alert
              v-if="formData.filter_type !== 'all'"
              type="info"
              variant="tonal"
              density="compact"
              class="mb-4"
            >
              <template v-slot:prepend>
                <v-icon icon="mdi-information"></v-icon>
              </template>
              <div class="text-caption">
                <strong>Note:</strong>
                <span v-if="formData.filter_type === 'position'">
                  Only employees with the selected position(s) will be included
                  in this payroll.
                </span>
                <span v-else-if="formData.filter_type === 'project'">
                  Only employees assigned to the selected project(s) will be
                  included in this payroll.
                </span>
                <span v-else-if="formData.filter_type === 'department'">
                  Only employees from the selected department(s) will be
                  included in this payroll.
                </span>
                <span v-else-if="formData.filter_type === 'staff_type'">
                  Only employees with the selected staff type(s) will be
                  included in this payroll.
                </span>
              </div>
            </v-alert>

            <v-textarea
              v-model="formData.notes"
              label="Notes (Optional)"
              rows="3"
              prepend-icon="mdi-note-text"
            ></v-textarea>
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
            @click="savePayroll"
            :disabled="saving"
          >
            <v-progress-circular
              v-if="saving"
              indeterminate
              size="16"
              width="2"
              class="mr-2"
            ></v-progress-circular>
            <v-icon v-else size="18" class="mr-1">{{
              editMode ? "mdi-pencil" : "mdi-content-save"
            }}</v-icon>
            {{
              saving
                ? editMode
                  ? "Updating..."
                  : "Creating..."
                : editMode
                  ? "Update"
                  : "Create"
            }}
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete Confirmation Dialog - Modern Design -->
    <v-dialog v-model="deleteDialog" max-width="550">
      <v-card class="delete-dialog-modern">
        <!-- Modern Header with Icon -->
        <div class="delete-dialog-header">
          <div class="delete-icon-wrapper">
            <v-icon size="40" color="white">mdi-alert-circle-outline</v-icon>
          </div>
          <h2 class="delete-title">Confirm Deletion</h2>
        </div>

        <v-card-text class="delete-dialog-content">
    

          <!-- Payroll Details Card -->
          <div v-if="selectedPayroll" class="details-card">
            <div class="details-header">
              <v-icon size="16" color="#ed985f">mdi-information-outline</v-icon>
              <span>Payroll Information</span>
            </div>
            <div class="details-grid">
              <div class="detail-item">
                <div class="detail-icon">
                  <v-icon size="16">mdi-label</v-icon>
                </div>
                <div class="detail-content">
                  <div class="detail-label">Period</div>
                  <div class="detail-value">{{ selectedPayroll.period_name }}</div>
                </div>
              </div>
              <div class="detail-item">
                <div class="detail-icon">
                  <v-icon size="16">mdi-account-group</v-icon>
                </div>
                <div class="detail-content">
                  <div class="detail-label">Employees</div>
                  <div class="detail-value">{{ selectedPayroll.items_count }} affected</div>
                </div>
              </div>
              <div class="detail-item">
                <div class="detail-icon">
                  <v-icon size="16">mdi-cash</v-icon>
                </div>
                <div class="detail-content">
                  <div class="detail-label">Total Amount</div>
                  <div class="detail-value">₱{{ formatCurrency(selectedPayroll.total_net) }}</div>
                </div>
              </div>
              <div class="detail-item">
                <div class="detail-icon">
                  <v-icon size="16">mdi-flag</v-icon>
                </div>
                <div class="detail-content">
                  <div class="detail-label">Status</div>
                  <div class="detail-value">
                    <v-chip
                      :color="getStatusColor(selectedPayroll.status)"
                      size="small"
                      variant="flat"
                    >
                      {{ selectedPayroll.status.toUpperCase() }}
                    </v-chip>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Consequences List -->
          <div class="consequences-section">
            <div class="consequences-title">
              <v-icon size="16" color="#64748b">mdi-alert-circle-outline</v-icon>
              <span>What will be deleted:</span>
            </div>
            <ul class="consequences-list">
              <li>All employee payroll items and calculations</li>
              <li>Associated deduction records and installments</li>
              <li>Payment history and transaction logs</li>
            </ul>
          </div>

          <!-- Final Confirmation -->
          <div class="final-confirmation">
            <v-icon size="20" color="#ef4444">mdi-alert</v-icon>
            <span>Are you absolutely sure you want to delete this payroll?</span>
          </div>
        </v-card-text>

        <!-- Modern Actions -->
        <v-card-actions class="delete-dialog-actions">
          <v-btn
            variant="outlined"
            color="#64748b"
            @click="deleteDialog = false"
            class="cancel-btn"
          >
            <v-icon size="18" class="mr-1">mdi-close</v-icon>
            Cancel
          </v-btn>
          <v-spacer></v-spacer>
          <v-btn
            color="#ef4444"
            variant="flat"
            :loading="deleting"
            @click="deletePayroll"
            class="delete-btn"
          >
            <v-icon size="18" class="mr-1">mdi-delete-forever</v-icon>
            Delete Permanently
          </v-btn>
        </v-card-actions>

      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import { useRouter } from "vue-router";
import { useToast } from "vue-toastification";
import api from "@/services/api";

const router = useRouter();
const toast = useToast();

const loading = ref(false);
const saving = ref(false);
const deleting = ref(false);
const loadingPositions = ref(false);
const loadingProjects = ref(false);
const search = ref("");
const dialog = ref(false);
const deleteDialog = ref(false);
const editMode = ref(false);
const valid = ref(false);
const form = ref(null);

const payrolls = ref([]);
const selectedPayroll = ref(null);
const positions = ref([]);
const projects = ref([]);
const departmentOptions = ref([]);
const staffTypeOptions = ref([]);

const formData = ref({
  period_name: "",
  period_start: "",
  period_end: "",
  payment_date: "",
  notes: "",
  filter_type: "all",
  position_ids: [],
  project_ids: [],
  departments: [],
  staff_types: [],
  employee_limit: null,
  has_attendance: false,
});

const headers = [
  { title: "Payroll #", key: "payroll_number", sortable: true },
  { title: "Period", key: "period", sortable: false },
  { title: "Payment Date", key: "payment_date", sortable: true },
  { title: "Employees", key: "items_count", sortable: true },
  { title: "Total Net Pay", key: "total_net", sortable: true, align: "end" },
  { title: "Status", key: "status", sortable: true },
  { title: "Actions", key: "actions", sortable: false, align: "center" },
];

const stats = computed(() => {
  return {
    total: payrolls.value.length,
    draft: payrolls.value.filter((p) => p.status === "draft").length,
    finalized: payrolls.value.filter((p) => p.status === "finalized").length,
    paid: payrolls.value.filter((p) => p.status === "paid").length,
  };
});

onMounted(() => {
  fetchPayrolls();
  fetchPositions();
  fetchProjects();
  fetchDepartmentsAndStaffTypes();
});

async function fetchPayrolls() {
  loading.value = true;
  try {
    const response = await api.get("/payrolls");
    payrolls.value = response.data.data || response.data;
  } catch (error) {
    console.error("Error fetching payrolls:", error);
    toast.error("Failed to load payrolls");
  } finally {
    loading.value = false;
  }
}

async function fetchPositions() {
  loadingPositions.value = true;
  try {
    const response = await api.get("/position-rates");
    positions.value = (response.data.data || response.data).filter(
      (p) => p.is_active,
    );
  } catch (error) {
    console.error("Error fetching positions:", error);
  } finally {
    loadingPositions.value = false;
  }
}

async function fetchProjects() {
  loadingProjects.value = true;
  try {
    const response = await api.get("/projects");
    projects.value = (response.data.data || response.data).filter(
      (p) => p.is_active,
    );
  } catch (error) {
    console.error("Error fetching projects:", error);
  } finally {
    loadingProjects.value = false;
  }
}

async function fetchDepartmentsAndStaffTypes() {
  try {
    // Fetch all employees to get unique departments
    const response = await api.get("/employees", {
      params: { per_page: 10000 }, // Get all employees
    });
    const employees = response.data.data || response.data;

    // Extract unique departments (filter out null/empty values)
    const depts = [
      ...new Set(
        employees.map((e) => e.department).filter((d) => d && d.trim() !== ""),
      ),
    ].sort();
    departmentOptions.value = depts;

    // Get staff types from position_rates table (active positions)
    const positionResponse = await api.get("/position-rates");
    const activePositions = (
      positionResponse.data.data || positionResponse.data
    ).filter((p) => p.is_active);
    staffTypeOptions.value = activePositions.map((p) => p.position_name).sort();
  } catch (error) {
    console.error("Error fetching departments and staff types:", error);
  }
}

function openCreateDialog() {
  editMode.value = false;
  formData.value = {
    period_name: "",
    period_start: "",
    period_end: "",
    payment_date: "",
    notes: "",
    filter_type: "all",
    position_ids: [],
    project_ids: [],
    departments: [],
    staff_types: [],
    employee_limit: null,
    has_attendance: false,
  };
  dialog.value = true;
}

function editPayroll(item) {
  editMode.value = true;
  selectedPayroll.value = item;
  formData.value = {
    period_name: item.period_name,
    period_start: item.period_start,
    period_end: item.period_end,
    payment_date: item.payment_date,
    notes: item.notes || "",
  };
  dialog.value = true;
}

function closeDialog() {
  dialog.value = false;
  selectedPayroll.value = null;
}

async function savePayroll() {
  const { valid } = await form.value.validate();
  if (!valid) return;

  // Prepare payload
  const payload = {
    period_name: formData.value.period_name,
    period_start: formData.value.period_start,
    period_end: formData.value.period_end,
    payment_date: formData.value.payment_date,
    notes: formData.value.notes,
    filter_type: formData.value.filter_type,
  };

  // Add filter-specific data
  if (formData.value.filter_type === "position") {
    payload.position_ids = formData.value.position_ids;
  } else if (formData.value.filter_type === "project") {
    payload.project_ids = formData.value.project_ids;
  } else if (formData.value.filter_type === "department") {
    payload.departments = formData.value.departments;
  } else if (formData.value.filter_type === "staff_type") {
    payload.staff_types = formData.value.staff_types;
  }

  // Add optional filters
  if (formData.value.employee_limit) {
    payload.employee_limit = formData.value.employee_limit;
  }
  if (formData.value.has_attendance) {
    payload.has_attendance = formData.value.has_attendance;
  }

  // Add employee limit if specified
  if (formData.value.employee_limit && formData.value.employee_limit > 0) {
    payload.employee_limit = formData.value.employee_limit;
  }

  // Add attendance filter if enabled
  if (formData.value.has_attendance) {
    payload.has_attendance = formData.value.has_attendance;
  }

  saving.value = true;
  try {
    if (editMode.value) {
      await api.put(`/payrolls/${selectedPayroll.value.id}`, payload);
      toast.success("Payroll updated successfully");
    } else {
      const response = await api.post("/payrolls", payload);
      const employeeCount =
        response.data.items_count || response.data.payroll?.items_count || 0;
      const limitText = formData.value.employee_limit
        ? ` (limited to ${formData.value.employee_limit})`
        : "";
      toast.success(
        `Payroll created successfully for ${employeeCount} employee(s)${limitText}`,
      );
    }
    await fetchPayrolls();
    closeDialog();
  } catch (error) {
    console.error("Error saving payroll:", error);

    // Extract detailed error message from response
    let errorMessage = "Failed to save payroll";

    if (error.response?.data) {
      const data = error.response.data;
      // Check for detailed error field first, then fall back to message
      if (data.error) {
        errorMessage = data.error;
      } else if (data.message && data.message !== "Validation failed") {
        errorMessage = data.message;
      }

      // Add employee count info if available
      if (data.employee_count) {
        console.log("Employee count breakdown:", data.employee_count);
      }
    }

    toast.error(errorMessage, {
      duration: 8000, // Show longer for detailed messages
    });
  } finally {
    saving.value = false;
  }
}

function viewPayroll(item) {
  router.push(`/payroll/${item.id}`);
}

function confirmDelete(item) {
  selectedPayroll.value = item;
  deleteDialog.value = true;
}

async function deletePayroll() {
  deleting.value = true;
  try {
    await api.delete(`/payrolls/${selectedPayroll.value.id}`);
    toast.success("Payroll deleted successfully");
    await fetchPayrolls();
    deleteDialog.value = false;
  } catch (error) {
    console.error("Error deleting payroll:", error);
    toast.error("Failed to delete payroll");
  } finally {
    deleting.value = false;
  }
}

function getStatusColor(status) {
  const colors = {
    draft: "warning",
    finalized: "info",
    paid: "success",
  };
  return colors[status] || "grey";
}

function formatDate(date) {
  if (!date) return "";
  return new Date(date).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

function formatCurrency(amount) {
  if (!amount) return "0.00";
  return parseFloat(amount).toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}
</script>

<style scoped lang="scss">
.payroll-page {
  max-width: 1600px;
  margin: 0 auto;
}

.page-header {
  margin-bottom: 24px;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 24px;

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

.stat-icon.total {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);

  .v-icon {
    color: white;
  }
}

.stat-icon.draft {
  background: rgba(237, 152, 95, 0.1);

  .v-icon {
    color: #ed985f;
  }
}

.stat-icon.finalized {
  background: rgba(0, 31, 61, 0.1);

  .v-icon {
    color: #001f3d;
  }
}

.stat-icon.paid {
  background: rgba(16, 185, 129, 0.1);

  .v-icon {
    color: #10b981;
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

.stat-value.warning {
  color: #f59e0b;
}

.stat-value.info {
  color: #3b82f6;
}

.stat-value.success {
  color: #10b981;
}

.modern-card {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  overflow: hidden;
  padding: 24px;
}

.filters-section {
  background: rgba(0, 31, 61, 0.01);
}

.table-section {
  background: #ffffff;
}

.modern-dialog {
  border-radius: 16px;
  overflow: hidden;
}

.dialog-header {
  background: white;
  padding: 24px;
  display: flex;
  align-items: center;
  gap: 16px;
}

.dialog-icon-wrapper {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;

  &.primary {
    background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
    box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);
  }
}

.dialog-title {
  font-size: 20px;
  font-weight: 700;
  color: #001f3d;
  line-height: 1.2;
}

.dialog-subtitle {
  font-size: 13px;
  color: #64748b;
  margin-top: 2px;
}

.dialog-content {
  padding: 24px;
}

.section-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 20px;
  background: linear-gradient(
    135deg,
    rgba(0, 31, 61, 0.02) 0%,
    rgba(237, 152, 95, 0.02) 100%
  );
  border-radius: 12px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  margin-bottom: 16px;
}

.section-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.25);
}

.section-title {
  font-size: 16px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
  letter-spacing: -0.3px;
}

.dialog-actions {
  padding: 16px 24px;
  background: rgba(0, 31, 61, 0.02);
}

.dialog-btn {
  padding: 10px 24px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;

  &:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
}

.dialog-btn-cancel {
  background: transparent;
  color: #64748b;

  &:hover:not(:disabled) {
    background: rgba(0, 31, 61, 0.04);
  }
}

.dialog-btn-primary {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: white;

  &:hover:not(:disabled) {
    box-shadow: 0 4px 12px rgba(237, 152, 95, 0.4);
    transform: translateY(-1px);
  }
}

// Delete Dialog - Modern Design
.delete-dialog-modern {
  border-radius: 16px !important;
  overflow: hidden;
}

.delete-dialog-header {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  padding: 32px 24px;
  text-align: center;
  position: relative;
  overflow: hidden;

  &::before {
    content: "";
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(
      circle,
      rgba(255, 255, 255, 0.1) 0%,
      transparent 70%
    );
    animation: pulse 3s ease-in-out infinite;
  }
}

@keyframes pulse {
  0%,
  100% {
    transform: scale(1);
    opacity: 0.5;
  }
  50% {
    transform: scale(1.1);
    opacity: 0.8;
  }
}

.delete-icon-wrapper {
  width: 80px;
  height: 80px;
  margin: 0 auto 16px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(10px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
  position: relative;
  z-index: 1;
}

.delete-title {
  font-size: 24px;
  font-weight: 700;
  color: white;
  margin: 0 0 8px 0;
  position: relative;
  z-index: 1;
}

.delete-subtitle {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.9);
  margin: 0;
  position: relative;
  z-index: 1;
}

.delete-dialog-content {
  padding: 24px !important;
}

.warning-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 8px;
  margin-bottom: 20px;

  span {
    font-size: 13px;
    font-weight: 600;
    color: #ef4444;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
}

.details-card {
  background: linear-gradient(
    135deg,
    rgba(237, 152, 95, 0.04) 0%,
    rgba(247, 185, 128, 0.02) 100%
  );
  border: 1px solid rgba(237, 152, 95, 0.15);
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 20px;
}

.details-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
  padding-bottom: 10px;
  border-bottom: 1px solid rgba(0, 31, 61, 0.08);

  span {
    font-size: 13px;
    font-weight: 700;
    color: #001f3d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
}

.details-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.detail-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px;
  background: white;
  border-radius: 8px;
  border: 1px solid rgba(0, 31, 61, 0.08);
}

.detail-icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;

  .v-icon {
    color: white !important;
  }
}

.detail-content {
  flex: 1;
  min-width: 0;
}

.detail-label {
  font-size: 11px;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 2px;
}

.detail-value {
  font-size: 13px;
  font-weight: 600;
  color: #001f3d;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.consequences-section {
  background: rgba(100, 116, 139, 0.04);
  border: 1px solid rgba(100, 116, 139, 0.15);
  border-radius: 10px;
  padding: 14px 16px;
  margin-bottom: 20px;
}

.consequences-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 10px;

  span {
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
}

.consequences-list {
  margin: 0;
  padding-left: 20px;

  li {
    font-size: 13px;
    color: #64748b;
    line-height: 1.6;
    margin-bottom: 6px;

    &:last-child {
      margin-bottom: 0;
    }
  }
}

.final-confirmation {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 16px;
  background: rgba(239, 68, 68, 0.06);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 10px;
  border-left: 4px solid #ef4444;

  span {
    font-size: 14px;
    font-weight: 600;
    color: #001f3d;
    line-height: 1.5;
  }
}

.delete-dialog-actions {
  padding: 20px 24px !important;
  background: rgba(0, 31, 61, 0.02);
  border-top: 1px solid rgba(0, 31, 61, 0.08);
}

.cancel-btn {
  text-transform: none !important;
  font-weight: 600 !important;
  border-radius: 10px !important;
  padding: 10px 20px !important;
  letter-spacing: 0 !important;

  &:hover {
    background: rgba(100, 116, 139, 0.08) !important;
  }
}

.delete-btn {
  text-transform: none !important;
  font-weight: 600 !important;
  border-radius: 10px !important;
  padding: 10px 20px !important;
  letter-spacing: 0 !important;
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3) !important;

  &:hover:not(:disabled) {
    box-shadow: 0 6px 16px rgba(239, 68, 68, 0.4) !important;
    transform: translateY(-1px);
  }
}

.form-field-wrapper {
  margin-bottom: 16px;
}

.form-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 600;
  color: #001f3d;
  margin-bottom: 8px;
}
</style>
