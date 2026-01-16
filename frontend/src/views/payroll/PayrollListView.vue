<template>
  <v-container fluid class="pa-6">
    <!-- Header -->
    <v-row class="mb-4">
      <v-col cols="12">
        <div class="d-flex justify-space-between align-center">
          <div>
            <h1 class="text-h4 font-weight-bold mb-2">
              <v-icon icon="mdi-cash-multiple" size="large" class="mr-2"></v-icon>
              Payroll Management
            </h1>
            <p class="text-subtitle-1 text-medium-emphasis">
              Create, manage and process payroll for all employees
            </p>
          </div>
          <v-btn
            color="primary"
            size="large"
            prepend-icon="mdi-plus"
            @click="openCreateDialog"
          >
            Create Payroll
          </v-btn>
        </div>
      </v-col>
    </v-row>

    <!-- Stats Cards -->
    <v-row class="mb-4">
      <v-col cols="12" md="3">
        <v-card>
          <v-card-text>
            <div class="text-overline mb-1">Total Payrolls</div>
            <div class="text-h5 font-weight-bold">{{ stats.total }}</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" md="3">
        <v-card>
          <v-card-text>
            <div class="text-overline mb-1">Draft</div>
            <div class="text-h5 font-weight-bold text-warning">{{ stats.draft }}</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" md="3">
        <v-card>
          <v-card-text>
            <div class="text-overline mb-1">Finalized</div>
            <div class="text-h5 font-weight-bold text-info">{{ stats.finalized }}</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" md="3">
        <v-card>
          <v-card-text>
            <div class="text-overline mb-1">Paid</div>
            <div class="text-h5 font-weight-bold text-success">{{ stats.paid }}</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Payroll List -->
    <v-card>
      <v-card-title class="d-flex align-center">
        <v-icon icon="mdi-format-list-bulleted" class="mr-2"></v-icon>
        Payroll Records
        <v-spacer></v-spacer>
        <v-text-field
          v-model="search"
          prepend-inner-icon="mdi-magnify"
          label="Search payroll..."
          single-line
          hide-details
          density="compact"
          class="mr-4"
          style="max-width: 300px"
        ></v-text-field>
      </v-card-title>

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
              {{ formatDate(item.period_start) }} - {{ formatDate(item.period_end) }}
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
            v-if="item.status === 'draft'"
            icon="mdi-delete"
            size="small"
            variant="text"
            color="error"
            @click="confirmDelete(item)"
          >
          </v-btn>
        </template>
      </v-data-table>
    </v-card>

    <!-- Create/Edit Dialog - Modern UI -->
    <v-dialog v-model="dialog" max-width="800" persistent>
      <v-card class="modern-dialog-card" elevation="24">
        <!-- Enhanced Header -->
        <v-card-title class="modern-dialog-header modern-dialog-header-success">
          <div class="d-flex align-center w-100">
            <v-avatar color="white" size="48" class="mr-4">
              <v-icon color="success" size="32">{{ editMode ? 'mdi-pencil' : 'mdi-cash-multiple' }}</v-icon>
            </v-avatar>
            <div>
              <div class="text-h5 font-weight-bold">
                {{ editMode ? 'Edit' : 'Create' }} Payroll
              </div>
              <div class="text-subtitle-2 text-white-70">
                {{ editMode ? 'Update payroll period details' : 'Generate new payroll period' }}
              </div>
            </div>
            <v-spacer></v-spacer>
            <v-btn icon variant="text" color="white" @click="closeDialog" size="small">
              <v-icon>mdi-close</v-icon>
            </v-btn>
          </div>
        </v-card-title>

        <v-card-text class="pa-6">
          <v-form ref="form" v-model="valid">
            <!-- Period Name -->
            <div class="form-field-wrapper">
              <label class="form-label">
                <v-icon size="small" color="primary">mdi-label</v-icon>
                Period Name <span class="text-error">*</span>
              </label>
              <v-text-field
                v-model="formData.period_name"
                placeholder="Enter period name"
                :rules="[v => !!v || 'Period name is required']"
                required
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-label"
                color="primary"
              ></v-text-field>
            </div>

            <v-row>
              <!-- Period Start -->
              <v-col cols="6">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="primary">mdi-calendar-start</v-icon>
                    Period Start <span class="text-error">*</span>
                  </label>
                  <v-text-field
                    v-model="formData.period_start"
                    type="date"
                    placeholder="Select start date"
                    :rules="[v => !!v || 'Start date is required']"
                    required
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-calendar-start"
                    color="primary"
                  ></v-text-field>
                </div>
              </v-col>

              <!-- Period End -->
              <v-col cols="6">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="primary">mdi-calendar-end</v-icon>
                    Period End <span class="text-error">*</span>
                  </label>
                  <v-text-field
                    v-model="formData.period_end"
                    type="date"
                    placeholder="Select end date"
                    :rules="[v => !!v || 'End date is required']"
                    required
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-calendar-end"
                    color="primary"
                  ></v-text-field>
                </div>
              </v-col>
            </v-row>

            <!-- Payment Date -->
            <div class="form-field-wrapper">
              <label class="form-label">
                <v-icon size="small" color="primary">mdi-calendar-check</v-icon>
                Payment Date <span class="text-error">*</span>
              </label>
              <v-text-field
                v-model="formData.payment_date"
                type="date"
                placeholder="Select payment date"
                :rules="[v => !!v || 'Payment date is required']"
                required
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-calendar-check"
                color="primary"
              ></v-text-field>
            </div>

            <v-divider class="my-4"></v-divider>

            <!-- Employee Filter Section -->
            <h3 class="text-subtitle-1 mb-3">
              <v-icon icon="mdi-filter" size="small" class="mr-2"></v-icon>
              Employee Filter
            </h3>

            <v-radio-group
              v-model="formData.filter_type"
              inline
              hide-details
              class="mb-4"
            >
              <v-radio label="All Employees" value="all"></v-radio>
              <v-radio label="By Position/Role" value="position"></v-radio>
              <v-radio label="By Project" value="project"></v-radio>
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
                      color="primary"
                    ></v-icon>
                  </template>
                  <div style="max-width: 300px;">
                    Useful for creating partial payrolls or testing.<br>
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
              :rules="[v => formData.filter_type !== 'position' || (v && v.length > 0) || 'Select at least one position']"
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
              :rules="[v => formData.filter_type !== 'project' || (v && v.length > 0) || 'Select at least one project']"
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
                    {{ item.raw.code }} - {{ item.raw.employees_count || 0 }} employees
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
              :rules="[v => formData.filter_type !== 'department' || (v && v.length > 0) || 'Select at least one department']"
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
              :rules="[v => formData.filter_type !== 'staff_type' || (v && v.length > 0) || 'Select at least one staff type']"
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
              color="primary"
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
                  Only employees with the selected position(s) will be included in this payroll.
                </span>
                <span v-else-if="formData.filter_type === 'project'">
                  Only employees assigned to the selected project(s) will be included in this payroll.
                </span>
                <span v-else-if="formData.filter_type === 'department'">
                  Only employees from the selected department(s) will be included in this payroll.
                </span>
                <span v-else-if="formData.filter_type === 'staff_type'">
                  Only employees with the selected staff type(s) will be included in this payroll.
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
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn text @click="closeDialog">Cancel</v-btn>
          <v-btn
            color="primary"
            :loading="saving"
            @click="savePayroll"
          >
            {{ editMode ? 'Update' : 'Create' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete Confirmation Dialog -->
    <v-dialog v-model="deleteDialog" max-width="400">
      <v-card>
        <v-card-title class="text-h5">Confirm Delete</v-card-title>
        <v-card-text>
          Are you sure you want to delete this payroll? This action cannot be undone.
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn text @click="deleteDialog = false">Cancel</v-btn>
          <v-btn color="error" :loading="deleting" @click="deletePayroll">
            Delete
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import api from '@/services/api';

const router = useRouter();
const toast = useToast();

const loading = ref(false);
const saving = ref(false);
const deleting = ref(false);
const loadingPositions = ref(false);
const loadingProjects = ref(false);
const search = ref('');
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
  period_name: '',
  period_start: '',
  period_end: '',
  payment_date: '',
  notes: '',
  filter_type: 'all',
  position_ids: [],
  project_ids: [],
  departments: [],
  staff_types: [],
  employee_limit: null,
  has_attendance: false,
});

const headers = [
  { title: 'Payroll #', key: 'payroll_number', sortable: true },
  { title: 'Period', key: 'period', sortable: false },
  { title: 'Payment Date', key: 'payment_date', sortable: true },
  { title: 'Employees', key: 'items_count', sortable: true },
  { title: 'Total Net Pay', key: 'total_net', sortable: true, align: 'end' },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, align: 'center' },
];

const stats = computed(() => {
  return {
    total: payrolls.value.length,
    draft: payrolls.value.filter(p => p.status === 'draft').length,
    finalized: payrolls.value.filter(p => p.status === 'finalized').length,
    paid: payrolls.value.filter(p => p.status === 'paid').length,
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
    const response = await api.get('/payrolls');
    payrolls.value = response.data.data || response.data;
  } catch (error) {
    console.error('Error fetching payrolls:', error);
    toast.error('Failed to load payrolls');
  } finally {
    loading.value = false;
  }
}

async function fetchPositions() {
  loadingPositions.value = true;
  try {
    const response = await api.get('/position-rates');
    positions.value = (response.data.data || response.data).filter(p => p.is_active);
  } catch (error) {
    console.error('Error fetching positions:', error);
  } finally {
    loadingPositions.value = false;
  }
}

async function fetchProjects() {
  loadingProjects.value = true;
  try {
    const response = await api.get('/projects');
    projects.value = (response.data.data || response.data).filter(p => p.is_active);
  } catch (error) {
    console.error('Error fetching projects:', error);
  } finally {
    loadingProjects.value = false;
  }
}

async function fetchDepartmentsAndStaffTypes() {
  try {
    // Fetch all employees to get unique departments and staff types
    const response = await api.get('/employees', {
      params: { per_page: 10000 } // Get all employees
    });
    const employees = response.data.data || response.data;
    
    // Extract unique departments (filter out null/empty values)
    const depts = [...new Set(employees
      .map(e => e.department)
      .filter(d => d && d.trim() !== '')
    )].sort();
    departmentOptions.value = depts;
    
    // Extract unique staff types (filter out null/empty values)
    const types = [...new Set(employees
      .map(e => e.staff_type)
      .filter(t => t && t.trim() !== '')
    )].sort();
    staffTypeOptions.value = types;
  } catch (error) {
    console.error('Error fetching departments and staff types:', error);
  }
}

function openCreateDialog() {
  editMode.value = false;
  formData.value = {
    period_name: '',
    period_start: '',
    period_end: '',
    payment_date: '',
    notes: '',
    filter_type: 'all',
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
    notes: item.notes || '',
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
  if (formData.value.filter_type === 'position') {
    payload.position_ids = formData.value.position_ids;
  } else if (formData.value.filter_type === 'project') {
    payload.project_ids = formData.value.project_ids;
  } else if (formData.value.filter_type === 'department') {
    payload.departments = formData.value.departments;
  } else if (formData.value.filter_type === 'staff_type') {
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
      toast.success('Payroll updated successfully');
    } else {
      const response = await api.post('/payrolls', payload);
      const employeeCount = response.data.items_count || response.data.payroll?.items_count || 0;
      const limitText = formData.value.employee_limit ? ` (limited to ${formData.value.employee_limit})` : '';
      toast.success(`Payroll created successfully for ${employeeCount} employee(s)${limitText}`);
    }
    await fetchPayrolls();
    closeDialog();
  } catch (error) {
    console.error('Error saving payroll:', error);
    toast.error(error.response?.data?.message || 'Failed to save payroll');
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
    toast.success('Payroll deleted successfully');
    await fetchPayrolls();
    deleteDialog.value = false;
  } catch (error) {
    console.error('Error deleting payroll:', error);
    toast.error('Failed to delete payroll');
  } finally {
    deleting.value = false;
  }
}

function getStatusColor(status) {
  const colors = {
    draft: 'warning',
    finalized: 'info',
    paid: 'success',
  };
  return colors[status] || 'grey';
}

function formatDate(date) {
  if (!date) return '';
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

function formatCurrency(amount) {
  if (!amount) return '0.00';
  return parseFloat(amount).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}
</script>

<style scoped>
.v-card {
  border-radius: 12px;
}
</style>
