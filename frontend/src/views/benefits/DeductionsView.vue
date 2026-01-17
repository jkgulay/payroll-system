<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-6">
      <h1 class="text-h4 font-weight-bold">Employee Deductions</h1>
      <v-btn
        color="primary"
        prepend-icon="mdi-plus"
        @click="openAddDialog"
        v-if="userRole !== 'employee'"
      >
        Add Deduction
      </v-btn>
    </div>

    <!-- Category Tabs -->
    <v-tabs v-model="activeTab" class="mb-4" color="primary">
      <v-tab value="all">All Deductions</v-tab>
      <v-tab value="government">Government Deductions</v-tab>
      <v-tab value="company">Company Deductions</v-tab>
    </v-tabs>

    <!-- Filters -->
    <v-card class="mb-4">
      <v-card-text>
        <v-row>
          <v-col cols="12" md="3">
            <v-text-field
              v-model="filters.search"
              prepend-inner-icon="mdi-magnify"
              label="Search deductions..."
              variant="outlined"
              density="compact"
              clearable
              hint="Search by name, employee, or reference number"
              persistent-hint
              @update:model-value="debouncedSearch"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="3" v-if="userRole !== 'employee'">
            <v-autocomplete
              v-model="filters.employee_id"
              :items="employees"
              item-title="full_name"
              item-value="id"
              label="Filter by Employee"
              variant="outlined"
              density="compact"
              clearable
              @update:model-value="fetchDeductions"
            ></v-autocomplete>
          </v-col>
          <v-col cols="12" :md="userRole === 'employee' ? 3 : 2">
            <v-select
              v-model="filters.deduction_type"
              :items="deductionTypes"
              label="Filter by Type"
              variant="outlined"
              density="compact"
              clearable
              @update:model-value="fetchDeductions"
            ></v-select>
          </v-col>
          <v-col cols="12" :md="userRole === 'employee' ? 3 : 2">
            <v-select
              v-model="filters.status"
              :items="statusOptions"
              label="Filter by Status"
              variant="outlined"
              density="compact"
              clearable
              @update:model-value="fetchDeductions"
            ></v-select>
          </v-col>
          <v-col cols="12" :md="userRole === 'employee' ? 3 : 2">
            <v-btn
              color="secondary"
              variant="outlined"
              block
              @click="clearFilters"
            >
              Clear Filters
            </v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Deductions Table -->
    <v-card>
      <v-data-table
        :headers="headers"
        :items="filteredDeductions"
        :loading="loading"
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

        <template v-slot:item.deduction_type="{ item }">
          <v-chip
            :color="getDeductionTypeColor(item.deduction_type)"
            size="small"
            variant="tonal"
          >
            {{ formatDeductionType(item.deduction_type) }}
          </v-chip>
        </template>

        <template v-slot:item.total_amount="{ item }">
          <span class="font-weight-medium"
            >₱{{ formatNumber(item.total_amount) }}</span
          >
        </template>

        <template v-slot:item.amount_per_cutoff="{ item }">
          <span class="font-weight-medium"
            >₱{{ formatNumber(item.amount_per_cutoff) }}</span
          >
        </template>

        <template v-slot:item.balance="{ item }">
          <span
            :class="
              item.balance > 0 ? 'text-error font-weight-bold' : 'text-success'
            "
          >
            ₱{{ formatNumber(item.balance) }}
          </span>
        </template>

        <template v-slot:item.progress="{ item }">
          <div class="d-flex align-center">
            <v-progress-linear
              :model-value="getProgress(item)"
              :color="item.status === 'completed' ? 'success' : 'primary'"
              height="8"
              rounded
              class="mr-2"
              style="min-width: 80px"
            ></v-progress-linear>
            <span class="text-caption"
              >{{ item.installments_paid }}/{{ item.installments }}</span
            >
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
          <v-btn
            v-if="userRole !== 'employee' && item.status === 'active'"
            icon="mdi-pencil"
            size="small"
            variant="text"
            @click="openEditDialog(item)"
          ></v-btn>
          <v-btn
            v-if="
              userRole !== 'employee' &&
              (item.installments_paid === 0 || item.status === 'cancelled')
            "
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
            @click="viewDetails(item)"
          ></v-btn>
        </template>

        <template v-slot:no-data>
          <div class="text-center py-8">
            <v-icon size="64" color="grey">mdi-wallet-minus-outline</v-icon>
            <p class="text-h6 mt-4">No deductions found</p>
            <p class="text-body-2 text-medium-emphasis">
              {{
                activeTab === "government"
                  ? "No government deductions"
                  : activeTab === "company"
                  ? "No company deductions"
                  : "No deductions available"
              }}
            </p>
          </div>
        </template>
      </v-data-table>
    </v-card>

    <!-- Add/Edit Dialog - Modern UI -->
    <v-dialog v-model="dialog" max-width="800px" persistent>
      <v-card class="modern-dialog-card" elevation="24">
        <!-- Enhanced Header -->
        <v-card-title class="modern-dialog-header modern-dialog-header-error">
          <div class="d-flex align-center w-100">
            <v-avatar color="white" size="48" class="mr-4">
              <v-icon color="error" size="32">{{ editMode ? 'mdi-pencil' : 'mdi-cash-minus' }}</v-icon>
            </v-avatar>
            <div>
              <div class="text-h5 font-weight-bold">
                {{ editMode ? "Edit Deduction" : "Add Deduction" }}
              </div>
              <div class="text-subtitle-2 text-white-70">
                {{ editMode ? 'Update deduction details' : 'Create new deduction for employee' }}
              </div>
            </div>
            <v-spacer></v-spacer>
            <v-btn icon variant="text" color="white" @click="closeDialog" size="small">
              <v-icon>mdi-close</v-icon>
            </v-btn>
          </div>
        </v-card-title>

        <v-card-text class="pa-6">
          <v-form ref="form" v-model="formValid">
            <v-row>
              <!-- Employee Selection -->
              <v-col cols="12">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="primary">mdi-account-search</v-icon>
                    Search and Select Employee <span class="text-error">*</span>
                  </label>
                  <v-autocomplete
                    v-model="formData.employee_id"
                    :items="employees"
                    item-title="full_name"
                    item-value="id"
                    placeholder="Search by name, employee number, or position"
                    variant="outlined"
                    density="comfortable"
                    :rules="[rules.required]"
                    :disabled="editMode"
                    clearable
                    prepend-inner-icon="mdi-account-search"
                    color="primary"
                    hint="Search by name, employee number, or position"
                    persistent-hint
                    no-data-text="No employees found matching your search"
                    :custom-filter="customEmployeeFilter"
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
                          <span class="font-weight-medium">{{ item.raw.full_name }}</span>
                        </template>
                        <template v-slot:subtitle>
                          <v-chip size="x-small" class="mr-1" color="primary" variant="outlined">
                            {{ item.raw.employee_number }}
                          </v-chip>
                          <span class="text-caption">{{ item.raw.position || 'N/A' }}</span>
                        </template>
                      </v-list-item>
                    </template>
                    <template v-slot:selection="{ item }">
                      <v-chip size="small" color="primary" variant="flat">
                        <v-avatar start>
                          <span class="text-white text-caption">
                            {{ getInitials(item.raw.full_name) }}
                          </span>
                        </v-avatar>
                        {{ item.raw.full_name }} - {{ item.raw.employee_number }}
                      </v-chip>
                    </template>
                  </v-autocomplete>
                </div>
              </v-col>

              <!-- Deduction Type -->
              <v-col cols="12" md="6">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="primary">mdi-tag</v-icon>
                    Deduction Type <span class="text-error">*</span>
                  </label>
                  <v-select
                    v-model="formData.deduction_type"
                    :items="deductionTypes"
                    placeholder="Select deduction type"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-tag"
                    color="primary"
                    :rules="[rules.required]"
                  ></v-select>
                </div>
              </v-col>

              <!-- Deduction Name -->
              <v-col cols="12" md="6">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="primary">mdi-text</v-icon>
                    Deduction Name
                    <v-chip size="x-small" color="info" class="ml-2">Auto-generated</v-chip>
                  </label>
                  <v-text-field
                    v-model="formData.deduction_name"
                    placeholder="Auto-generated from type if left blank"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-text"
                    color="primary"
                    hint="Auto-generated from type if left blank"
                    persistent-hint
                  ></v-text-field>
                </div>
              </v-col>

              <!-- Total Amount -->
              <v-col cols="12" md="6">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="primary">mdi-currency-php</v-icon>
                    Total Amount <span class="text-error">*</span>
                  </label>
                  <v-text-field
                    v-model.number="formData.total_amount"
                    type="number"
                    prefix="₱"
                    placeholder="0.00"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-currency-php"
                    color="primary"
                    :rules="[rules.required, rules.positive]"
                  ></v-text-field>
                </div>
              </v-col>

              <!-- Amount per Cutoff -->
              <v-col cols="12" md="6">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="primary">mdi-cash</v-icon>
                    Amount per Cutoff <span class="text-error">*</span>
                  </label>
                  <v-text-field
                    v-model.number="formData.amount_per_cutoff"
                    type="number"
                    prefix="₱"
                    placeholder="0.00"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-cash"
                    color="primary"
                    :rules="[rules.required, rules.positive]"
                    hint="Semi-monthly deduction amount"
                    persistent-hint
                  ></v-text-field>
                </div>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model.number="formData.installments"
                  label="Number of Installments"
                  type="number"
                  variant="outlined"
                  hint="Calculated from dates if left blank"
                  persistent-hint
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="formData.start_date"
                  label="Start Date *"
                  type="date"
                  variant="outlined"
                  :rules="[rules.required]"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="formData.end_date"
                  label="End Date"
                  type="date"
                  variant="outlined"
                  hint="Calculated from installments if left blank"
                  persistent-hint
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="formData.reference_number"
                  label="Reference Number"
                  variant="outlined"
                ></v-text-field>
              </v-col>

              <v-col cols="12">
                <v-textarea
                  v-model="formData.description"
                  label="Description"
                  variant="outlined"
                  rows="2"
                ></v-textarea>
              </v-col>

              <v-col cols="12">
                <v-textarea
                  v-model="formData.notes"
                  label="Notes (Optional)"
                  variant="outlined"
                  rows="2"
                ></v-textarea>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="closeDialog">Cancel</v-btn>
          <v-btn
            color="primary"
            variant="flat"
            :loading="saving"
            :disabled="!formValid"
            @click="saveDeduction"
          >
            {{ editMode ? "Update" : "Add" }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete Confirmation Dialog -->
    <v-dialog v-model="deleteDialog" max-width="400px">
      <v-card>
        <v-card-title class="text-h5">Confirm Delete</v-card-title>
        <v-card-text>
          Are you sure you want to delete this deduction? This action cannot be
          undone.
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="deleteDialog = false">Cancel</v-btn>
          <v-btn
            color="error"
            variant="flat"
            :loading="deleting"
            @click="deleteDeduction"
          >
            Delete
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Details Dialog -->
    <v-dialog v-model="detailsDialog" max-width="600px">
      <v-card v-if="selectedDeduction">
        <v-card-title class="text-h5 d-flex align-center">
          <v-icon class="mr-2">mdi-wallet-minus</v-icon>
          Deduction Details
        </v-card-title>

        <v-divider></v-divider>

        <v-card-text class="pt-4">
          <v-row>
            <v-col cols="12">
              <div class="text-subtitle-1 font-weight-bold mb-2">
                Deduction Information
              </div>
            </v-col>
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">Employee</div>
              <div class="font-weight-medium">
                {{ selectedDeduction.employee?.full_name }}
              </div>
            </v-col>
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">
                Deduction Type
              </div>
              <v-chip
                :color="getDeductionTypeColor(selectedDeduction.deduction_type)"
                size="small"
                variant="tonal"
              >
                {{ formatDeductionType(selectedDeduction.deduction_type) }}
              </v-chip>
            </v-col>
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">
                Deduction Name
              </div>
              <div class="font-weight-medium">
                {{ selectedDeduction.deduction_name }}
              </div>
            </v-col>
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">Status</div>
              <v-chip
                :color="getStatusColor(selectedDeduction.status)"
                size="small"
                variant="flat"
              >
                {{ formatStatus(selectedDeduction.status) }}
              </v-chip>
            </v-col>

            <v-col cols="12">
              <v-divider class="my-2"></v-divider>
              <div class="text-subtitle-1 font-weight-bold mb-2">
                Financial Details
              </div>
            </v-col>
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">Total Amount</div>
              <div class="font-weight-bold text-primary">
                ₱{{ formatNumber(selectedDeduction.total_amount) }}
              </div>
            </v-col>
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">Per Cutoff</div>
              <div class="font-weight-medium">
                ₱{{ formatNumber(selectedDeduction.amount_per_cutoff) }}
              </div>
            </v-col>
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">Balance</div>
              <div
                :class="
                  selectedDeduction.balance > 0
                    ? 'font-weight-bold text-error'
                    : 'font-weight-bold text-success'
                "
              >
                ₱{{ formatNumber(selectedDeduction.balance) }}
              </div>
            </v-col>
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">Progress</div>
              <div class="font-weight-medium">
                {{ selectedDeduction.installments_paid }}/{{
                  selectedDeduction.installments
                }}
                payments
              </div>
            </v-col>

            <v-col cols="12">
              <v-progress-linear
                :model-value="getProgress(selectedDeduction)"
                :color="
                  selectedDeduction.status === 'completed'
                    ? 'success'
                    : 'primary'
                "
                height="10"
                rounded
              ></v-progress-linear>
            </v-col>

            <v-col cols="12">
              <v-divider class="my-2"></v-divider>
              <div class="text-subtitle-1 font-weight-bold mb-2">Schedule</div>
            </v-col>
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">Start Date</div>
              <div class="font-weight-medium">
                {{ formatDate(selectedDeduction.start_date) }}
              </div>
            </v-col>
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">End Date</div>
              <div class="font-weight-medium">
                {{ formatDate(selectedDeduction.end_date) }}
              </div>
            </v-col>

            <v-col cols="12" v-if="selectedDeduction.reference_number">
              <v-divider class="my-2"></v-divider>
              <div class="text-subtitle-1 font-weight-bold mb-2">
                Reference Number
              </div>
              <div>{{ selectedDeduction.reference_number }}</div>
            </v-col>

            <v-col cols="12" v-if="selectedDeduction.description">
              <v-divider class="my-2"></v-divider>
              <div class="text-subtitle-1 font-weight-bold mb-2">
                Description
              </div>
              <div>{{ selectedDeduction.description }}</div>
            </v-col>
          </v-row>
        </v-card-text>

        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="detailsDialog = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useToast } from "vue-toastification";
import deductionService from "@/services/deductionService";
import api from "@/services/api";
import { useAuthStore } from "@/stores/auth";

const toast = useToast();
const authStore = useAuthStore();

// User role
const userRole = computed(() => authStore.user?.role);

// Data
const deductions = ref([]);
const employees = ref([]);
const loading = ref(false);
const saving = ref(false);
const deleting = ref(false);
const dialog = ref(false);
const deleteDialog = ref(false);
const detailsDialog = ref(false);
const editMode = ref(false);
const formValid = ref(false);
const form = ref(null);
const selectedDeduction = ref(null);
const activeTab = ref("all");

// Filters
const filters = ref({
  search: "",
  employee_id: null,
  deduction_type: null,
  status: null,
  category: null,
});

// Form data
const defaultFormData = {
  employee_id: null,
  deduction_type: null,
  deduction_name: "",
  total_amount: null,
  amount_per_cutoff: null,
  installments: null,
  start_date: new Date().toISOString().split("T")[0],
  end_date: null,
  description: "",
  reference_number: "",
  notes: "",
};

const formData = ref({ ...defaultFormData });

// Computed - Filter deductions by category tab
const filteredDeductions = computed(() => {
  if (activeTab.value === "all") return deductions.value;

  const governmentTypes = ["sss", "philhealth", "pagibig", "tax"];
  const companyTypes = ["ppe", "tools", "uniform", "absence", "loan", "other"];

  if (activeTab.value === "government") {
    return deductions.value.filter((d) =>
      governmentTypes.includes(d.deduction_type)
    );
  } else if (activeTab.value === "company") {
    return deductions.value.filter((d) =>
      companyTypes.includes(d.deduction_type)
    );
  }

  return deductions.value;
});

// Custom filter for employee autocomplete
const customEmployeeFilter = (itemTitle, queryText, item) => {
  if (!queryText) return true;
  
  const search = queryText.toLowerCase();
  const fullName = item.raw.full_name?.toLowerCase() || '';
  const employeeNumber = item.raw.employee_number?.toLowerCase() || '';
  const position = item.raw.position?.toLowerCase() || '';
  
  return fullName.includes(search) || 
         employeeNumber.includes(search) || 
         position.includes(search);
};

// Headers
const headers = computed(() => {
  const baseHeaders = [
    { title: "Type", key: "deduction_type", sortable: true },
    { title: "Name", key: "deduction_name", sortable: true },
    { title: "Total", key: "total_amount", sortable: true },
    { title: "Per Cutoff", key: "amount_per_cutoff", sortable: true },
    { title: "Balance", key: "balance", sortable: true },
    { title: "Progress", key: "progress", sortable: false },
    { title: "Status", key: "status", sortable: true },
    { title: "Actions", key: "actions", sortable: false, align: "center" },
  ];

  if (userRole.value !== "employee") {
    baseHeaders.unshift({
      title: "Employee",
      key: "employee",
      sortable: false,
    });
  }

  return baseHeaders;
});

// Options
const deductionTypes = [
  { title: "PPE (Personal Protective Equipment)", value: "ppe" },
  { title: "Tools", value: "tools" },
  { title: "Uniform", value: "uniform" },
  { title: "Absence", value: "absence" },
  { title: "Cash Advance", value: "cash_advance" },
  { title: "Cash Bond", value: "cash_bond" },
  { title: "Insurance", value: "insurance" },
  { title: "Cooperative", value: "cooperative" },
  { title: "SSS Contribution", value: "sss" },
  { title: "PhilHealth Contribution", value: "philhealth" },
  { title: "Pag-IBIG Contribution", value: "pagibig" },
  { title: "Withholding Tax", value: "tax" },
  { title: "Loan Repayment", value: "loan" },
  { title: "Other", value: "other" },
];

const statusOptions = [
  { title: "Active", value: "active" },
  { title: "Completed", value: "completed" },
  { title: "Cancelled", value: "cancelled" },
];

// Validation rules
const rules = {
  required: (value) => !!value || "This field is required",
  positive: (value) => value > 0 || "Must be greater than 0",
};

// Watch active tab to update category filter
watch(activeTab, (newTab) => {
  filters.value.category = newTab === "all" ? null : newTab;
  fetchDeductions();
});

// Fetch deductions
const fetchDeductions = async () => {
  loading.value = true;
  try {
    const params = {};
    if (filters.value.search) params.search = filters.value.search;
    if (filters.value.employee_id)
      params.employee_id = filters.value.employee_id;
    if (filters.value.deduction_type)
      params.deduction_type = filters.value.deduction_type;
    if (filters.value.status) params.status = filters.value.status;
    if (filters.value.category && filters.value.category !== "all") {
      params.category = filters.value.category;
    }

    const response = await deductionService.getDeductions(params);
    deductions.value = response.data.data || response.data;
  } catch (error) {
    toast.error("Failed to load deductions");
    console.error(error);
  } finally {
    loading.value = false;
  }
};

// Debounce search to avoid too many API calls
let searchTimeout = null;
const debouncedSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    fetchDeductions();
  }, 500);
};

// Fetch employees
const fetchEmployees = async () => {
  try {
    const response = await api.get("/employees", {
      params: {
        per_page: 10000, // Large number to get all employees
        paginate: false
      }
    });
    
    // Handle both paginated and non-paginated responses
    if (response.data.data) {
      employees.value = response.data.data;
    } else if (Array.isArray(response.data)) {
      employees.value = response.data;
    } else {
      employees.value = [];
    }
    
    console.log('Loaded employees:', employees.value.length);
  } catch (error) {
    console.error("Failed to load employees:", error);
    toast.error("Failed to load employees");
  }
};

// Open dialogs
const openAddDialog = () => {
  editMode.value = false;
  formData.value = { ...defaultFormData };
  dialog.value = true;
};

const openEditDialog = (deduction) => {
  editMode.value = true;
  selectedDeduction.value = deduction;
  formData.value = {
    employee_id: deduction.employee_id,
    deduction_type: deduction.deduction_type,
    deduction_name: deduction.deduction_name,
    total_amount: deduction.total_amount,
    amount_per_cutoff: deduction.amount_per_cutoff,
    installments: deduction.installments,
    start_date: deduction.start_date,
    end_date: deduction.end_date,
    description: deduction.description,
    reference_number: deduction.reference_number,
    notes: deduction.notes,
  };
  dialog.value = true;
};

const closeDialog = () => {
  dialog.value = false;
  editMode.value = false;
  formData.value = { ...defaultFormData };
  if (form.value) form.value.resetValidation();
};

// Save deduction
const saveDeduction = async () => {
  if (!form.value) return;

  const { valid } = await form.value.validate();
  if (!valid) return;

  saving.value = true;
  try {
    if (editMode.value) {
      await deductionService.updateDeduction(
        selectedDeduction.value.id,
        formData.value
      );
      toast.success("Deduction updated successfully");
    } else {
      await deductionService.createDeduction(formData.value);
      toast.success("Deduction created successfully");
    }
    closeDialog();
    fetchDeductions();
  } catch (error) {
    toast.error(error.response?.data?.message || "Failed to save deduction");
    console.error(error);
  } finally {
    saving.value = false;
  }
};

// Delete deduction
const confirmDelete = (deduction) => {
  selectedDeduction.value = deduction;
  deleteDialog.value = true;
};

const deleteDeduction = async () => {
  deleting.value = true;
  try {
    await deductionService.deleteDeduction(selectedDeduction.value.id);
    toast.success("Deduction deleted successfully");
    deleteDialog.value = false;
    fetchDeductions();
  } catch (error) {
    toast.error(error.response?.data?.message || "Failed to delete deduction");
    console.error(error);
  } finally {
    deleting.value = false;
  }
};

// View details
const viewDetails = (deduction) => {
  selectedDeduction.value = deduction;
  detailsDialog.value = true;
};

// Clear filters
const clearFilters = () => {
  filters.value = {
    search: "",
    employee_id: null,
    deduction_type: null,
    status: null,
    category: activeTab.value === "all" ? null : activeTab.value,
  };
  fetchDeductions();
};

// Formatters
const formatNumber = (value) => {
  if (!value) return "0.00";
  return Number(value).toLocaleString("en-PH", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

const formatDeductionType = (type) => {
  const types = {
    ppe: "PPE",
    tools: "Tools",
    uniform: "Uniform",
    absence: "Absence",
    sss: "SSS",
    philhealth: "PhilHealth",
    pagibig: "Pag-IBIG",
    tax: "Tax",
    loan: "Loan",
    other: "Other",
  };
  return types[type] || type;
};

const formatStatus = (status) => {
  return status.charAt(0).toUpperCase() + status.slice(1);
};

const formatDate = (date) => {
  if (!date) return "N/A";
  return new Date(date).toLocaleDateString("en-PH", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

const getInitials = (name) => {
  if (!name) return "??";
  const parts = name.trim().split(" ");
  if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
};

const getDeductionTypeColor = (type) => {
  const governmentTypes = ["sss", "philhealth", "pagibig", "tax"];
  if (governmentTypes.includes(type)) return "blue";

  const colors = {
    ppe: "orange",
    tools: "purple",
    uniform: "green",
    absence: "red",
    loan: "cyan",
    other: "grey",
  };
  return colors[type] || "grey";
};

const getStatusColor = (status) => {
  const colors = {
    active: "success",
    completed: "info",
    cancelled: "error",
  };
  return colors[status] || "grey";
};

const getProgress = (deduction) => {
  if (deduction.installments === 0) return 0;
  return (deduction.installments_paid / deduction.installments) * 100;
};

// Lifecycle
onMounted(() => {
  fetchDeductions();
  if (userRole.value !== "employee") {
    fetchEmployees();
  }
});
</script>
