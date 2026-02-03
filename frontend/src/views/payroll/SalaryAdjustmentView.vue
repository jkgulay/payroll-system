<template>
  <v-container fluid class="salary-adjustments-view pa-6">
    <!-- Header -->
    <div class="d-flex justify-space-between align-center mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold text-primary">Salary Adjustments</h1>
        <p class="text-body-2 text-medium-emphasis mt-1">
          Manage salary adjustments (deductions/additions) for previous payroll periods
        </p>
      </div>
      <v-btn color="primary" @click="openAddDialog" prepend-icon="mdi-plus">
        Add Adjustment
      </v-btn>
    </div>

    <!-- Filters -->
    <v-card class="mb-6" elevation="0" border>
      <v-card-text>
        <v-row>
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
              v-model="statusFilter"
              :items="statusOptions"
              label="Status"
              hide-details
              variant="outlined"
              density="compact"
            />
          </v-col>
          <v-col cols="12" md="3">
            <v-btn variant="outlined" @click="refreshData" :loading="loading">
              <v-icon start>mdi-refresh</v-icon>
              Refresh
            </v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Adjustments Table -->
    <v-card elevation="0" border>
      <v-data-table
        :headers="headers"
        :items="adjustments"
        :loading="loading"
        :search="search"
        class="elevation-0"
        :items-per-page="15"
      >
        <template v-slot:item.employee="{ item }">
          <div>
            <div class="font-weight-medium">{{ item.employee?.full_name }}</div>
            <div class="text-caption text-medium-emphasis">
              {{ item.employee?.department || 'N/A' }}
            </div>
          </div>
        </template>

        <template v-slot:item.amount="{ item }">
          <v-chip
            :color="item.adjustment_type === 'deduction' ? 'error' : 'success'"
            size="small"
            variant="tonal"
          >
            {{ item.adjustment_type === 'deduction' ? '-' : '+' }}₱{{ formatCurrency(item.amount) }}
          </v-chip>
        </template>

        <template v-slot:item.adjustment_type="{ item }">
          <v-chip
            :color="item.adjustment_type === 'deduction' ? 'warning' : 'info'"
            size="small"
            variant="flat"
          >
            {{ item.adjustment_type === 'deduction' ? 'Deduction' : 'Addition' }}
          </v-chip>
        </template>

        <template v-slot:item.status="{ item }">
          <v-chip
            :color="getStatusColor(item.status)"
            size="small"
            variant="tonal"
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
          />
          <v-btn
            v-if="item.status === 'pending'"
            icon="mdi-delete"
            size="small"
            variant="text"
            color="error"
            @click="confirmDelete(item)"
          />
          <v-btn
            v-if="item.status === 'applied'"
            icon="mdi-eye"
            size="small"
            variant="text"
            @click="viewAdjustment(item)"
          />
        </template>
      </v-data-table>
    </v-card>

    <!-- Add/Edit Dialog -->
    <v-dialog v-model="dialog" max-width="600" persistent>
      <v-card>
        <v-card-title class="d-flex justify-space-between align-center pa-4">
          <span>{{ isEditing ? 'Edit Adjustment' : 'Add Salary Adjustment' }}</span>
          <v-btn icon="mdi-close" variant="text" @click="closeDialog" />
        </v-card-title>
        
        <v-divider />
        
        <v-card-text class="pa-4">
          <v-form ref="formRef" v-model="formValid">
            <v-row>
              <v-col cols="12">
                <v-autocomplete
                  v-model="form.employee_id"
                  :items="employees"
                  item-title="full_name"
                  item-value="id"
                  label="Select Employee *"
                  :rules="[v => !!v || 'Employee is required']"
                  variant="outlined"
                  :disabled="isEditing"
                  :loading="loadingEmployees"
                >
                  <template v-slot:item="{ item, props }">
                    <v-list-item v-bind="props">
                      <template v-slot:subtitle>
                        {{ item.raw.department }} | ₱{{ formatCurrency(item.raw.basic_salary) }}/day
                        <span v-if="item.raw.pending_adjustments != 0" class="ml-2 text-warning">
                          (Pending: ₱{{ formatCurrency(Math.abs(item.raw.pending_adjustments)) }})
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
                  :rules="[v => !!v || 'Type is required']"
                  variant="outlined"
                />
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model.number="form.amount"
                  label="Amount *"
                  type="number"
                  prefix="₱"
                  :rules="[
                    v => !!v || 'Amount is required',
                    v => v > 0 || 'Amount must be greater than 0'
                  ]"
                  variant="outlined"
                />
              </v-col>

              <v-col cols="12">
                <v-text-field
                  v-model="form.reason"
                  label="Reason"
                  placeholder="e.g., Previous salary underpayment, correction for Jan 2026"
                  variant="outlined"
                />
              </v-col>

              <v-col cols="12">
                <v-text-field
                  v-model="form.reference_period"
                  label="Reference Period"
                  placeholder="e.g., January 2026 - Cutoff 1"
                  variant="outlined"
                />
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-divider />

        <v-card-actions class="pa-4">
          <v-spacer />
          <v-btn variant="text" @click="closeDialog">Cancel</v-btn>
          <v-btn
            color="primary"
            :loading="saving"
            :disabled="!formValid"
            @click="saveAdjustment"
          >
            {{ isEditing ? 'Update' : 'Create' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete Confirmation Dialog -->
    <v-dialog v-model="deleteDialog" max-width="400">
      <v-card>
        <v-card-title>Confirm Delete</v-card-title>
        <v-card-text>
          Are you sure you want to delete this salary adjustment for
          <strong>{{ selectedAdjustment?.employee?.full_name }}</strong>?
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="deleteDialog = false">Cancel</v-btn>
          <v-btn color="error" :loading="deleting" @click="deleteAdjustment">
            Delete
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- View Dialog -->
    <v-dialog v-model="viewDialog" max-width="500">
      <v-card v-if="selectedAdjustment">
        <v-card-title class="d-flex justify-space-between align-center">
          <span>Adjustment Details</span>
          <v-btn icon="mdi-close" variant="text" @click="viewDialog = false" />
        </v-card-title>
        <v-divider />
        <v-card-text>
          <v-list>
            <v-list-item>
              <v-list-item-title class="text-caption">Employee</v-list-item-title>
              <v-list-item-subtitle class="text-body-1">
                {{ selectedAdjustment.employee?.full_name }}
              </v-list-item-subtitle>
            </v-list-item>
            <v-list-item>
              <v-list-item-title class="text-caption">Amount</v-list-item-title>
              <v-list-item-subtitle>
                <v-chip
                  :color="selectedAdjustment.adjustment_type === 'deduction' ? 'error' : 'success'"
                  size="small"
                >
                  {{ selectedAdjustment.adjustment_type === 'deduction' ? '-' : '+' }}₱{{ formatCurrency(selectedAdjustment.amount) }}
                </v-chip>
              </v-list-item-subtitle>
            </v-list-item>
            <v-list-item v-if="selectedAdjustment.reason">
              <v-list-item-title class="text-caption">Reason</v-list-item-title>
              <v-list-item-subtitle>{{ selectedAdjustment.reason }}</v-list-item-subtitle>
            </v-list-item>
            <v-list-item v-if="selectedAdjustment.description">
              <v-list-item-title class="text-caption">Reference Period</v-list-item-title>
              <v-list-item-subtitle>{{ selectedAdjustment.description }}</v-list-item-subtitle>
            </v-list-item>
            <v-list-item v-if="selectedAdjustment.applied_payroll">
              <v-list-item-title class="text-caption">Applied to Payroll</v-list-item-title>
              <v-list-item-subtitle>
                {{ selectedAdjustment.applied_payroll.period_name }}
              </v-list-item-subtitle>
            </v-list-item>
            <v-list-item>
              <v-list-item-title class="text-caption">Created By</v-list-item-title>
              <v-list-item-subtitle>
                {{ selectedAdjustment.created_by?.name || 'System' }}
              </v-list-item-subtitle>
            </v-list-item>
          </v-list>
        </v-card-text>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import { ref, reactive, onMounted, computed } from 'vue';
import { useToast } from 'vue-toastification';
import api from '@/services/api';

const toast = useToast();

// State
const loading = ref(false);
const loadingEmployees = ref(false);
const saving = ref(false);
const deleting = ref(false);
const adjustments = ref([]);
const employees = ref([]);
const search = ref('');
const statusFilter = ref('all');
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
  type: 'deduction',
  reason: '',
  reference_period: '',
});

const headers = [
  { title: 'Employee', key: 'employee', sortable: true },
  { title: 'Amount', key: 'amount', sortable: true },
  { title: 'Type', key: 'adjustment_type', sortable: true },
  { title: 'Reason', key: 'reason', sortable: false },
  { title: 'Reference Period', key: 'description', sortable: false },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Created', key: 'created_at', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, width: '120px' },
];

const statusOptions = [
  { title: 'All', value: 'all' },
  { title: 'Pending', value: 'pending' },
  { title: 'Applied', value: 'applied' },
  { title: 'Cancelled', value: 'cancelled' },
];

const typeOptions = [
  { title: 'Deduction (subtract from salary)', value: 'deduction' },
  { title: 'Addition (add to salary)', value: 'addition' },
];

// Methods
const formatCurrency = (value) => {
  return new Intl.NumberFormat('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value || 0);
};

const formatDate = (date) => {
  if (!date) return '';
  return new Date(date).toLocaleDateString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
};

const capitalizeFirst = (str) => {
  if (!str) return '';
  return str.charAt(0).toUpperCase() + str.slice(1);
};

const getStatusColor = (status) => {
  switch (status) {
    case 'pending': return 'warning';
    case 'applied': return 'success';
    case 'cancelled': return 'grey';
    default: return 'grey';
  }
};

const fetchAdjustments = async () => {
  loading.value = true;
  try {
    const params = {};
    if (statusFilter.value !== 'all') {
      params.status = statusFilter.value;
    }
    const response = await api.get('/salary-adjustments', { params });
    adjustments.value = response.data.data || response.data;
  } catch (error) {
    console.error('Error fetching adjustments:', error);
    toast.error('Failed to load salary adjustments');
  } finally {
    loading.value = false;
  }
};

const fetchEmployees = async () => {
  loadingEmployees.value = true;
  try {
    const response = await api.get('/salary-adjustments/employees');
    employees.value = response.data;
  } catch (error) {
    console.error('Error fetching employees:', error);
    toast.error('Failed to load employees');
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
  form.reason = item.reason || '';
  form.reference_period = item.description || '';
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
  form.type = 'deduction';
  form.reason = '';
  form.reference_period = '';
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
      toast.success('Salary adjustment updated successfully');
    } else {
      await api.post('/salary-adjustments', form);
      toast.success('Salary adjustment created successfully');
    }
    closeDialog();
    fetchAdjustments();
  } catch (error) {
    console.error('Error saving adjustment:', error);
    toast.error(error.response?.data?.message || 'Failed to save adjustment');
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
    toast.success('Salary adjustment deleted successfully');
    deleteDialog.value = false;
    fetchAdjustments();
  } catch (error) {
    console.error('Error deleting adjustment:', error);
    toast.error(error.response?.data?.message || 'Failed to delete adjustment');
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

<style scoped>
.salary-adjustments-view {
  max-width: 1400px;
  margin: 0 auto;
}
</style>
