<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-6">
      <h1 class="text-h4 font-weight-bold">Employee Allowances</h1>
      <v-btn color="primary" prepend-icon="mdi-plus" @click="openAddDialog">
        Add Allowance
      </v-btn>
    </div>

    <!-- Filters -->
    <v-card class="mb-4">
      <v-card-text>
        <v-row>
          <v-col cols="12" md="4">
            <v-autocomplete
              v-model="filters.employee_id"
              :items="employees"
              item-title="full_name"
              item-value="id"
              label="Filter by Employee"
              variant="outlined"
              density="compact"
              clearable
              @update:model-value="fetchAllowances"
            ></v-autocomplete>
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.allowance_type"
              :items="allowanceTypes"
              label="Filter by Type"
              variant="outlined"
              density="compact"
              clearable
              @update:model-value="fetchAllowances"
            ></v-select>
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.status"
              :items="statusOptions"
              label="Filter by Status"
              variant="outlined"
              density="compact"
              clearable
              @update:model-value="fetchAllowances"
            ></v-select>
          </v-col>
          <v-col cols="12" md="2">
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

    <!-- Allowances Table -->
    <v-card>
      <v-data-table
        :headers="headers"
        :items="allowances"
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

        <template v-slot:item.allowance_type="{ item }">
          <v-chip
            :color="getTypeColor(item.allowance_type)"
            size="small"
            variant="tonal"
          >
            {{ formatAllowanceType(item.allowance_type) }}
          </v-chip>
        </template>

        <template v-slot:item.amount="{ item }">
          <span class="font-weight-medium"
            >₱{{ formatNumber(item.amount) }}</span
          >
        </template>

        <template v-slot:item.frequency="{ item }">
          {{ formatFrequency(item.frequency) }}
        </template>

        <template v-slot:item.effective_date="{ item }">
          {{ formatDate(item.effective_date) }}
        </template>

        <template v-slot:item.end_date="{ item }">
          {{ item.end_date ? formatDate(item.end_date) : "Ongoing" }}
        </template>

        <template v-slot:item.is_taxable="{ item }">
          <v-icon :color="item.is_taxable ? 'warning' : 'success'" size="small">
            {{ item.is_taxable ? "mdi-check-circle" : "mdi-close-circle" }}
          </v-icon>
        </template>

        <template v-slot:item.is_active="{ item }">
          <v-chip
            :color="item.is_active ? 'success' : 'error'"
            size="small"
            variant="flat"
          >
            {{ item.is_active ? "Active" : "Inactive" }}
          </v-chip>
        </template>

        <template v-slot:item.actions="{ item }">
          <v-btn
            icon="mdi-pencil"
            size="small"
            variant="text"
            @click="openEditDialog(item)"
          ></v-btn>
          <v-btn
            icon="mdi-delete"
            size="small"
            variant="text"
            color="error"
            @click="confirmDelete(item)"
          ></v-btn>
        </template>

        <template v-slot:no-data>
          <div class="text-center py-8">
            <v-icon size="64" color="grey">mdi-wallet-plus-outline</v-icon>
            <p class="text-h6 mt-4">No allowances found</p>
            <p class="text-body-2 text-medium-emphasis">
              Add an allowance to get started
            </p>
          </div>
        </template>
      </v-data-table>
    </v-card>

    <!-- Add/Edit Dialog -->
    <v-dialog v-model="dialog" max-width="600px" persistent>
      <v-card>
        <v-card-title class="text-h5">
          {{ editMode ? "Edit Allowance" : "Add Allowance" }}
        </v-card-title>

        <v-card-text>
          <v-form ref="form" v-model="formValid">
            <v-row>
              <v-col cols="12">
                <v-autocomplete
                  v-model="formData.employee_id"
                  :items="employees"
                  item-title="full_name"
                  item-value="id"
                  label="Employee *"
                  variant="outlined"
                  :rules="[rules.required]"
                  :disabled="editMode"
                >
                  <template v-slot:item="{ props, item }">
                    <v-list-item v-bind="props">
                      <template v-slot:title>
                        {{ item.raw.full_name }}
                      </template>
                      <template v-slot:subtitle>
                        {{ item.raw.employee_number }} - {{ item.raw.position }}
                      </template>
                    </v-list-item>
                  </template>
                </v-autocomplete>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="formData.allowance_type"
                  :items="allowanceTypes"
                  label="Allowance Type *"
                  variant="outlined"
                  :rules="[rules.required]"
                ></v-select>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="formData.allowance_name"
                  label="Custom Name (Optional)"
                  variant="outlined"
                  hint="Leave blank to use type as name"
                  persistent-hint
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model.number="formData.amount"
                  label="Amount *"
                  type="number"
                  prefix="₱"
                  variant="outlined"
                  :rules="[rules.required, rules.positive]"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="formData.frequency"
                  :items="frequencyOptions"
                  label="Frequency *"
                  variant="outlined"
                  :rules="[rules.required]"
                ></v-select>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="formData.effective_date"
                  label="Effective Date *"
                  type="date"
                  variant="outlined"
                  :rules="[rules.required]"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="formData.end_date"
                  label="End Date (Optional)"
                  type="date"
                  variant="outlined"
                  hint="Leave blank for ongoing"
                  persistent-hint
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-switch
                  v-model="formData.is_taxable"
                  label="Taxable"
                  color="warning"
                  hint="Subject to withholding tax"
                  persistent-hint
                ></v-switch>
              </v-col>

              <v-col cols="12" md="6">
                <v-switch
                  v-model="formData.is_active"
                  label="Active"
                  color="success"
                  hint="Include in payroll calculation"
                  persistent-hint
                ></v-switch>
              </v-col>

              <v-col cols="12">
                <v-textarea
                  v-model="formData.notes"
                  label="Notes (Optional)"
                  variant="outlined"
                  rows="3"
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
            @click="saveAllowance"
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
          Are you sure you want to delete this allowance?
          <div class="mt-4 pa-3 bg-grey-lighten-4 rounded">
            <div>
              <strong>Employee:</strong> {{ deleteItem?.employee?.full_name }}
            </div>
            <div>
              <strong>Type:</strong>
              {{ formatAllowanceType(deleteItem?.allowance_type) }}
            </div>
            <div>
              <strong>Amount:</strong> ₱{{ formatNumber(deleteItem?.amount) }}
            </div>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="deleteDialog = false">Cancel</v-btn>
          <v-btn
            color="error"
            variant="flat"
            :loading="deleting"
            @click="deleteAllowance"
          >
            Delete
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Snackbar -->
    <v-snackbar v-model="snackbar" :color="snackbarColor" :timeout="3000">
      {{ snackbarText }}
    </v-snackbar>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from "vue";
import { useRoute } from "vue-router";
import api from "@/services/api";

const route = useRoute();
const loading = ref(false);
const saving = ref(false);
const deleting = ref(false);
const dialog = ref(false);
const deleteDialog = ref(false);
const editMode = ref(false);
const formValid = ref(false);
const form = ref(null);

const allowances = ref([]);
const employees = ref([]);
const deleteItem = ref(null);

const filters = ref({
  employee_id: null,
  allowance_type: null,
  status: null,
});

const formData = ref({
  employee_id: null,
  allowance_type: null,
  allowance_name: null,
  amount: 0,
  frequency: "semi_monthly",
  effective_date: null,
  end_date: null,
  is_taxable: false,
  is_active: true,
  notes: null,
});

const snackbar = ref(false);
const snackbarText = ref("");
const snackbarColor = ref("success");

const headers = [
  { title: "Employee", key: "employee", sortable: true },
  { title: "Type", key: "allowance_type", sortable: true },
  { title: "Amount", key: "amount", sortable: true },
  { title: "Frequency", key: "frequency", sortable: true },
  { title: "Start Date", key: "effective_date", sortable: true },
  { title: "End Date", key: "end_date", sortable: true },
  { title: "Taxable", key: "is_taxable", sortable: true },
  { title: "Status", key: "is_active", sortable: true },
  { title: "Actions", key: "actions", sortable: false, align: "center" },
];

const allowanceTypes = [
  { title: "Water Allowance", value: "water" },
  { title: "COLA (Cost of Living)", value: "cola" },
  { title: "Incentive", value: "incentive" },
  { title: "PPE (Personal Protective Equipment)", value: "ppe" },
  { title: "Transportation", value: "transportation" },
  { title: "Meal Allowance", value: "meal" },
  { title: "Communication", value: "communication" },
  { title: "Housing", value: "housing" },
  { title: "Clothing", value: "clothing" },
  { title: "Other", value: "other" },
];

const frequencyOptions = [
  { title: "Daily", value: "daily" },
  { title: "Semi-Monthly", value: "semi_monthly" },
  { title: "Monthly", value: "monthly" },
];

const statusOptions = [
  { title: "Active", value: "active" },
  { title: "Inactive", value: "inactive" },
];

const rules = {
  required: (value) => !!value || "Required",
  positive: (value) => value > 0 || "Must be greater than 0",
};

onMounted(() => {
  // Check if employee_id is passed via URL query parameter
  if (route.query.employee_id) {
    filters.value.employee_id = parseInt(route.query.employee_id);
  }
  fetchEmployees();
  fetchAllowances();
});

// Watch for route query changes
watch(
  () => route.query.employee_id,
  (newEmployeeId) => {
    if (newEmployeeId) {
      filters.value.employee_id = parseInt(newEmployeeId);
      fetchAllowances();
    }
  }
);

async function fetchEmployees() {
  try {
    const response = await api.get("/employees?per_page=1000");
    employees.value = response.data.data;
  } catch (error) {
    showSnackbar("Failed to load employees", "error");
  }
}

async function fetchAllowances() {
  loading.value = true;
  try {
    const params = new URLSearchParams();
    if (filters.value.employee_id)
      params.append("employee_id", filters.value.employee_id);
    if (filters.value.allowance_type)
      params.append("allowance_type", filters.value.allowance_type);

    const response = await api.get(`/allowances?${params.toString()}`);
    let data = response.data.data || response.data;

    // Filter by status if selected
    if (filters.value.status) {
      const isActive = filters.value.status === "active";
      data = data.filter((item) => item.is_active === isActive);
    }

    allowances.value = data;
  } catch (error) {
    showSnackbar("Failed to load allowances", "error");
  } finally {
    loading.value = false;
  }
}

function clearFilters() {
  filters.value = {
    employee_id: null,
    allowance_type: null,
    status: null,
  };
  fetchAllowances();
}

function openAddDialog() {
  editMode.value = false;
  resetForm();
  dialog.value = true;
}

function openEditDialog(item) {
  editMode.value = true;
  formData.value = {
    id: item.id,
    employee_id: item.employee_id,
    allowance_type: item.allowance_type,
    allowance_name: item.allowance_name,
    amount: item.amount,
    frequency: item.frequency,
    effective_date: item.effective_date,
    end_date: item.end_date,
    is_taxable: item.is_taxable,
    is_active: item.is_active,
    notes: item.notes,
  };
  dialog.value = true;
}

function closeDialog() {
  dialog.value = false;
  resetForm();
}

function resetForm() {
  formData.value = {
    employee_id: null,
    allowance_type: null,
    allowance_name: null,
    amount: 0,
    frequency: "semi_monthly",
    effective_date: null,
    end_date: null,
    is_taxable: false,
    is_active: true,
    notes: null,
  };
  form.value?.resetValidation();
}

async function saveAllowance() {
  if (!formValid.value) return;

  saving.value = true;
  try {
    if (editMode.value) {
      await api.put(`/allowances/${formData.value.id}`, formData.value);
      showSnackbar("Allowance updated successfully", "success");
    } else {
      await api.post("/allowances", formData.value);
      showSnackbar("Allowance added successfully", "success");
    }
    closeDialog();
    fetchAllowances();
  } catch (error) {
    showSnackbar(
      error.response?.data?.message || "Failed to save allowance",
      "error"
    );
  } finally {
    saving.value = false;
  }
}

function confirmDelete(item) {
  deleteItem.value = item;
  deleteDialog.value = true;
}

async function deleteAllowance() {
  deleting.value = true;
  try {
    await api.delete(`/allowances/${deleteItem.value.id}`);
    showSnackbar("Allowance deleted successfully", "success");
    deleteDialog.value = false;
    fetchAllowances();
  } catch (error) {
    showSnackbar("Failed to delete allowance", "error");
  } finally {
    deleting.value = false;
  }
}

function showSnackbar(text, color = "success") {
  snackbarText.value = text;
  snackbarColor.value = color;
  snackbar.value = true;
}

function formatAllowanceType(type) {
  const found = allowanceTypes.find((item) => item.value === type);
  return found ? found.title : type;
}

function formatFrequency(freq) {
  const found = frequencyOptions.find((item) => item.value === freq);
  return found ? found.title : freq;
}

function formatDate(date) {
  if (!date) return "";
  return new Date(date).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

function formatNumber(num) {
  return Number(num).toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}

function getTypeColor(type) {
  const colors = {
    water: "blue",
    cola: "green",
    incentive: "purple",
    ppe: "orange",
    transportation: "cyan",
    meal: "pink",
    communication: "indigo",
    housing: "teal",
    clothing: "amber",
    other: "grey",
  };
  return colors[type] || "grey";
}
</script>
