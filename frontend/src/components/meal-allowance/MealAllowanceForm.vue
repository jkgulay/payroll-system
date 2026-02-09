<template>
  <v-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    max-width="800px"
    persistent
    scrollable
  >
    <v-card class="modern-dialog">
      <v-card-title class="dialog-header">
        <div class="dialog-icon-wrapper primary">
          <v-icon size="24">mdi-food</v-icon>
        </div>
        <div>
          <div class="dialog-title">
            {{ isEdit ? "Edit Allowance" : "Create Allowance" }}
          </div>
          <div class="dialog-subtitle">
            {{
              isEdit
                ? "Update allowance details"
                : "Add allowance distribution for employees"
            }}
          </div>
        </div>
      </v-card-title>
      <v-divider></v-divider>

      <v-card-text class="dialog-content" style="max-height: 70vh">
        <v-form ref="formRef" @submit.prevent="save">
          <v-row>
            <!-- Section 1: Basic Information -->
            <v-col cols="12">
              <div class="section-header">
                <div class="section-icon">
                  <v-icon size="18">mdi-information</v-icon>
                </div>
                <h3 class="section-title">Basic Information</h3>
              </div>
            </v-col>

            <!-- Allowance Type -->
            <v-col cols="12" md="6">
              <v-select
                v-model="form.allowance_type"
                :items="allowanceTypes"
                label="Allowance Type *"
                placeholder="Select allowance type"
                prepend-inner-icon="mdi-tag"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
              ></v-select>
            </v-col>

            <v-col cols="12" md="6" v-if="form.allowance_type === 'custom'">
              <v-text-field
                v-model="form.custom_allowance_type"
                label="Custom Allowance Type *"
                placeholder="e.g., Equipment Rental"
                prepend-inner-icon="mdi-tag-plus"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <!-- Allowance Name -->
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.title"
                label="Allowance Name"
                placeholder="Auto-generated from type if left blank"
                prepend-inner-icon="mdi-format-title"
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <!-- Location -->
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.location"
                label="Location"
                placeholder="Enter location"
                prepend-inner-icon="mdi-map-marker"
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <!-- Period Start -->
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.period_start"
                label="Period Start"
                type="date"
                placeholder="Select start date"
                prepend-inner-icon="mdi-calendar-start"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <!-- Period End -->
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.period_end"
                label="Period End"
                type="date"
                placeholder="Select end date"
                prepend-inner-icon="mdi-calendar-end"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <!-- Notes -->
            <v-col cols="12">
              <v-textarea
                v-model="form.notes"
                label="Notes"
                placeholder="Enter notes"
                prepend-inner-icon="mdi-note-text"
                variant="outlined"
                density="comfortable"
                rows="2"
              ></v-textarea>
            </v-col>

            <!-- Section 2: Employee Allowances -->
            <v-col cols="12" class="mt-4">
              <div class="section-header">
                <div class="section-icon">
                  <v-icon size="18">mdi-account-group</v-icon>
                </div>
                <h3 class="section-title">Employee Allowances</h3>
                <v-spacer></v-spacer>

                <v-chip v-if="form.items.length > 0" color="info" class="mr-2">
                  {{ form.items.length }} employee(s)
                </v-chip>

                <!-- Search Other Employees Button -->
                <v-btn
                  color="secondary"
                  size="small"
                  prepend-icon="mdi-magnify"
                  class="mr-2"
                  @click="showSearchDialog = true"
                >
                  Search Other Employees
                </v-btn>

                <!-- Bulk Assignment Dialog -->
                <v-dialog v-model="showBulkDialog" max-width="500px">
                  <template v-slot:activator="{ props }">
                    <v-btn
                      v-if="availableEmployees.length > 0"
                      v-bind="props"
                      color="primary"
                      size="small"
                      prepend-icon="mdi-account-multiple-plus"
                    >
                      Bulk Add by Department
                    </v-btn>
                  </template>

                  <v-card>
                    <v-card-title class="bg-primary">
                      <v-icon left>mdi-account-multiple-plus</v-icon>
                      Bulk Add Field Employees
                    </v-card-title>

                    <v-card-text class="pt-4">
                      <v-alert type="info" variant="tonal" class="mb-4">
                        This will add all {{ availableEmployees.length }} active
                        employee(s) from the selected department to the
                        allowance.
                      </v-alert>

                      <v-text-field
                        v-model.number="bulkForm.no_of_days"
                        label="Number of Days *"
                        type="number"
                        min="1"
                        max="31"
                        :rules="[rules.required]"
                        outlined
                        dense
                        hint="Default days for all employees"
                      ></v-text-field>

                      <v-text-field
                        v-model.number="bulkForm.amount_per_day"
                        label="Amount per Day *"
                        type="number"
                        min="0"
                        step="0.01"
                        :rules="[rules.required]"
                        outlined
                        dense
                        prefix="₱"
                        hint="Default amount per day for all employees"
                        class="mt-3"
                      ></v-text-field>

                      <v-alert type="warning" variant="tonal" class="mt-4">
                        <strong>Note:</strong> This will replace any existing
                        employees in the list.
                      </v-alert>
                    </v-card-text>

                    <v-card-actions>
                      <v-spacer></v-spacer>
                      <v-btn @click="showBulkDialog = false" variant="outlined">
                        Cancel
                      </v-btn>
                      <v-btn
                        color="primary"
                        @click="bulkAddEmployees"
                        :loading="bulkLoading"
                      >
                        <v-icon left>mdi-check</v-icon>
                        Add All Employees
                      </v-btn>
                    </v-card-actions>
                  </v-card>
                </v-dialog>

                <!-- Employee Search Dialog -->
                <v-dialog v-model="showSearchDialog" max-width="600px">
                  <v-card>
                    <v-card-title class="bg-secondary">
                      <v-icon left>mdi-magnify</v-icon>
                      Search Employees from Other Departments
                    </v-card-title>

                    <v-card-text class="pt-4">
                      <v-alert type="info" variant="tonal" class="mb-4">
                        Search for employees by name or employee number.
                      </v-alert>

                      <v-text-field
                        v-model="searchTerm"
                        label="Search Employee"
                        placeholder="Enter name or employee number"
                        prepend-inner-icon="mdi-magnify"
                        clearable
                        variant="outlined"
                        density="comfortable"
                        @input="debouncedSearch"
                        :loading="searchLoading"
                      ></v-text-field>

                      <v-list v-if="searchResults.length > 0" class="mt-3">
                        <v-list-item
                          v-for="employee in searchResults"
                          :key="employee.id"
                          @click="addSearchedEmployee(employee)"
                        >
                          <template #prepend>
                            <v-avatar color="primary" size="40">
                              <v-icon>mdi-account</v-icon>
                            </v-avatar>
                          </template>
                          <v-list-item-title>
                            {{ employee.name }}
                          </v-list-item-title>
                          <v-list-item-subtitle>
                            {{ employee.employee_number }} -
                            {{ employee.department }}
                          </v-list-item-subtitle>
                          <template #append>
                            <v-btn
                              icon="mdi-plus"
                              size="small"
                              color="primary"
                            ></v-btn>
                          </template>
                        </v-list-item>
                      </v-list>

                      <v-alert
                        v-else-if="searchTerm && !searchLoading"
                        type="warning"
                        variant="tonal"
                      >
                        No employees found. Try a different search term.
                      </v-alert>
                    </v-card-text>

                    <v-card-actions>
                      <v-spacer></v-spacer>
                      <v-btn
                        @click="showSearchDialog = false"
                        variant="outlined"
                      >
                        Close
                      </v-btn>
                    </v-card-actions>
                  </v-card>
                </v-dialog>
              </div>

              <!-- Selection Mode -->
              <v-col cols="12">
                <v-btn-toggle
                  v-model="selectionMode"
                  variant="outlined"
                  color="primary"
                  density="comfortable"
                  class="mb-3"
                  mandatory
                >
                  <v-btn value="individual">Individual</v-btn>
                  <v-btn value="multiple">Multiple</v-btn>
                  <v-btn value="department">Department</v-btn>
                  <v-btn value="position">Position</v-btn>
                </v-btn-toggle>
              </v-col>

              <v-col cols="12" md="6" v-if="selectionMode === 'individual'">
                <v-select
                  v-model="form.employee_id"
                  :items="availableEmployees"
                  item-title="name"
                  item-value="id"
                  label="Select Employee"
                  variant="outlined"
                  density="comfortable"
                  clearable
                ></v-select>
              </v-col>

              <v-col cols="12" md="6" v-if="selectionMode === 'multiple'">
                <v-select
                  v-model="form.employee_ids"
                  :items="availableEmployees"
                  item-title="name"
                  item-value="id"
                  label="Select Employees"
                  variant="outlined"
                  density="comfortable"
                  multiple
                  chips
                  clearable
                ></v-select>
              </v-col>

              <v-col cols="12" md="6" v-if="selectionMode === 'department'">
                <v-select
                  v-model="form.department"
                  :items="departments"
                  item-title="title"
                  item-value="value"
                  label="Select Department"
                  variant="outlined"
                  density="comfortable"
                  clearable
                ></v-select>
              </v-col>

              <v-col cols="12" md="6" v-if="selectionMode === 'position'">
                <v-select
                  v-model="form.position_id"
                  :items="positions"
                  item-title="position_name"
                  item-value="id"
                  label="Select Position"
                  variant="outlined"
                  density="comfortable"
                  clearable
                ></v-select>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model.number="bulkForm.no_of_days"
                  label="Number of Days *"
                  type="number"
                  min="1"
                  max="31"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model.number="bulkForm.amount_per_day"
                  label="Amount per Day *"
                  type="number"
                  min="0"
                  step="0.01"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                  prefix="₱"
                ></v-text-field>
              </v-col>

              <v-col cols="12">
                <v-btn color="primary" @click="applySelection">
                  <v-icon start>mdi-account-multiple-check</v-icon>
                  Apply Selection
                </v-btn>
              </v-col>

              <!-- Employee Table -->
              <v-data-table
                :headers="itemHeaders"
                :items="form.items"
                :loading="loadingEmployees"
                density="compact"
                class="mt-3"
              >
                <template #[`item.employee_id`]="{ item, index }">
                  <div class="d-flex align-center">
                    <span v-if="item.employee_name" class="text-body-2">
                      {{ item.employee_name }}
                      <span
                        v-if="item.employee_number"
                        class="text-caption text-grey ml-1"
                      >
                        ({{ item.employee_number }})
                      </span>
                    </span>
                    <v-select
                      v-else
                      v-model="item.employee_id"
                      :items="availableEmployees"
                      item-title="name"
                      item-value="id"
                      density="compact"
                      variant="outlined"
                      hide-details
                      placeholder="Select employee"
                      @update:model-value="onEmployeeSelect(index, $event)"
                    ></v-select>
                  </div>
                </template>

                <template #[`item.no_of_days`]="{ item }">
                  <v-text-field
                    v-model.number="item.no_of_days"
                    type="number"
                    min="1"
                    max="31"
                    density="compact"
                    variant="outlined"
                    hide-details
                    @input="calculateTotal(item)"
                  ></v-text-field>
                </template>

                <template #[`item.amount_per_day`]="{ item }">
                  <v-text-field
                    v-model.number="item.amount_per_day"
                    type="number"
                    min="0"
                    step="0.01"
                    density="compact"
                    variant="outlined"
                    hide-details
                    prefix="₱"
                    @input="calculateTotal(item)"
                  ></v-text-field>
                </template>

                <template #[`item.total`]="{ item }">
                  ₱{{ formatNumber(item.no_of_days * item.amount_per_day) }}
                </template>

                <template #[`item.actions`]="{ index }">
                  <v-btn
                    icon="mdi-delete"
                    size="small"
                    variant="text"
                    color="error"
                    @click="removeItem(index)"
                  ></v-btn>
                </template>

                <template #bottom>
                  <v-row class="ma-2">
                    <v-col>
                      <v-btn color="primary" size="small" @click="addItem">
                        <v-icon left>mdi-plus</v-icon>
                        Add Row
                      </v-btn>
                    </v-col>
                    <v-col class="text-right">
                      <strong
                        >Grand Total: ₱{{ formatNumber(grandTotal) }}</strong
                      >
                    </v-col>
                  </v-row>
                </template>
              </v-data-table>
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>

      <v-divider></v-divider>

      <v-card-actions class="dialog-actions">
        <v-spacer></v-spacer>
        <button
          class="dialog-btn dialog-btn-cancel"
          @click="close"
          :disabled="saving"
        >
          Cancel
        </button>
        <button
          v-if="!isEdit"
          class="dialog-btn dialog-btn-secondary"
          @click="saveAndSubmit"
          :disabled="saving"
        >
          <v-progress-circular
            v-if="saving"
            indeterminate
            size="16"
            width="2"
            class="mr-2"
          ></v-progress-circular>
          <v-icon v-else size="18" class="mr-1">mdi-send</v-icon>
          {{ saving ? "Submitting..." : "Create & Submit" }}
        </button>
        <button
          class="dialog-btn dialog-btn-primary"
          @click="save"
          :disabled="saving"
        >
          <v-progress-circular
            v-if="saving"
            indeterminate
            size="16"
            width="2"
            class="mr-2"
          ></v-progress-circular>
          <v-icon v-else size="18" class="mr-1">mdi-content-save</v-icon>
          {{
            saving
              ? isEdit
                ? "Updating..."
                : "Saving..."
              : isEdit
                ? "Update"
                : "Save as Draft"
          }}
        </button>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import mealAllowanceService from "@/services/mealAllowanceService";
import api from "@/services/api";

const props = defineProps({
  modelValue: Boolean,
  mealAllowance: Object,
  positions: Array,
});

const emit = defineEmits(["update:modelValue", "saved"]);

const formRef = ref(null);
const saving = ref(false);
const loadingEmployees = ref(false);
const bulkLoading = ref(false);
const showBulkDialog = ref(false);
const availableEmployees = ref([]);
const selectionMode = ref("individual");
const departments = ref([]);
const searchLoading = ref(false);
const searchResults = ref([]);
const searchTerm = ref("");
const showSearchDialog = ref(false);
const selectedDepartment = ref("all");

const form = ref({
  title: "",
  allowance_type: null,
  custom_allowance_type: "",
  location: "",
  period_start: "",
  period_end: "",
  position_id: null,
  department: null,
  employee_id: null,
  employee_ids: [],
  notes: "",
  items: [],
});

const bulkForm = ref({
  no_of_days: 15,
  amount_per_day: 120.0,
});

const itemHeaders = [
  { title: "Employee", key: "employee_id", width: "250px" },
  { title: "No. of Days", key: "no_of_days", width: "120px" },
  { title: "Amount/Day", key: "amount_per_day", width: "120px" },
  { title: "Total", key: "total", width: "120px" },
  { title: "Actions", key: "actions", width: "80px", align: "center" },
];

const allowanceTypes = [
  { title: "Meal", value: "meal" },
  { title: "Transportation", value: "transportation" },
  { title: "Communication", value: "communication" },
  { title: "Housing", value: "housing" },
  { title: "Clothing", value: "clothing" },
  { title: "Medical", value: "medical" },
  { title: "Education", value: "education" },
  { title: "Performance", value: "performance" },
  { title: "Hazard", value: "hazard" },
  { title: "Incentive", value: "incentive" },
  { title: "COLA", value: "cola" },
  { title: "PPE", value: "ppe" },
  { title: "Other", value: "other" },
  { title: "Custom", value: "custom" },
];

const rules = {
  required: (v) => !!v || "Required",
};

const isEdit = computed(() => !!props.mealAllowance);

const grandTotal = computed(() => {
  return form.value.items.reduce((sum, item) => {
    return sum + item.no_of_days * item.amount_per_day;
  }, 0);
});

watch(
  () => props.mealAllowance,
  (newVal) => {
    if (newVal) {
      const inferred = inferAllowanceTypeFromTitle(newVal.title);
      form.value = {
        title: newVal.title,
        allowance_type: newVal.allowance_type || inferred.type,
        custom_allowance_type: newVal.allowance_type ? "" : inferred.custom,
        location: newVal.location || "",
        period_start: newVal.period_start,
        period_end: newVal.period_end,
        position_id: newVal.position_id,
        department: newVal.department || null,
        employee_id: null,
        employee_ids: [],
        notes: newVal.notes || "",
        items: newVal.items
          ? newVal.items.map((item) => ({
              employee_id: item.employee_id,
              no_of_days: item.no_of_days,
              amount_per_day: parseFloat(item.amount_per_day),
            }))
          : [],
      };
      if (newVal.position_id) {
        loadEmployees();
      }
    } else {
      resetForm();
    }
  },
  { immediate: true },
);

// Load employees on mount
loadEmployees();
loadDepartments();

async function loadDepartments() {
  try {
    const response = await api.get("/projects", {
      params: { is_active: true },
    });
    const projects = response.data.data || response.data;
    departments.value = projects.map((p) => ({
      title: p.name,
      value: p.id,
    }));
  } catch (error) {
    // Silent fail
  }
}

async function loadEmployees() {
  loadingEmployees.value = true;
  try {
    availableEmployees.value =
      await mealAllowanceService.getEmployeesByPosition(null, null, "all");
  } catch (error) {
    // Silent fail
  } finally {
    loadingEmployees.value = false;
  }
}

// Debounced search for employees
let searchTimeout = null;
function debouncedSearch() {
  clearTimeout(searchTimeout);
  if (!searchTerm.value || searchTerm.value.length < 2) {
    searchResults.value = [];
    return;
  }
  searchTimeout = setTimeout(async () => {
    await searchEmployees();
  }, 500);
}

async function searchEmployees() {
  if (!searchTerm.value || searchTerm.value.length < 2) return;

  searchLoading.value = true;
  try {
    searchResults.value = await mealAllowanceService.searchEmployees(
      searchTerm.value,
      null,
    );
  } catch (error) {
    console.error("Error searching employees:", error);
    searchResults.value = [];
  } finally {
    searchLoading.value = false;
  }
}

function addSearchedEmployee(employee) {
  // Check if employee already exists in items
  const exists = form.value.items.some(
    (item) => item.employee_id === employee.id,
  );

  if (exists) {
    alert("Employee already added to the list");
    return;
  }

  // Add employee to items
  form.value.items.push({
    employee_id: employee.id,
    employee_name: employee.name,
    employee_number: employee.employee_number,
    position_code: employee.position_code,
    department: employee.department,
    no_of_days: 15,
    amount_per_day: employee.basic_salary || 120.0,
    total_amount: 15 * (employee.basic_salary || 120.0),
  });

  // Clear search and close dialog
  searchTerm.value = "";
  searchResults.value = [];
  showSearchDialog.value = false;

  alert(`${employee.name} added successfully!`);
}

function addItem() {
  form.value.items.push({
    employee_id: null,
    no_of_days: 15,
    amount_per_day: 120.0,
  });
}

function addAllEmployees() {
  // Clear existing items and add all employees from selected position
  form.value.items = [];

  availableEmployees.value.forEach((emp) => {
    form.value.items.push({
      employee_id: emp.id,
      employee_name: emp.name,
      employee_number: emp.employee_number,
      position_code: emp.position_code,
      no_of_days: 15,
      amount_per_day: 120.0,
      total_amount: 15 * 120.0,
    });
  });
}

async function bulkAddEmployees() {
  if (!bulkForm.value.no_of_days || !bulkForm.value.amount_per_day) {
    alert("Please fill in all fields");
    return;
  }

  if (
    !confirm(
      `Add all ${availableEmployees.value.length} employee(s) to this allowance? This will replace existing entries.`,
    )
  ) {
    return;
  }

  bulkLoading.value = true;
  try {
    const response = await mealAllowanceService.bulkAssignByPosition({
      position_id: null,
      project_id: form.value.project_id || null,
      department: selectedDepartment.value,
      no_of_days: bulkForm.value.no_of_days,
      amount_per_day: bulkForm.value.amount_per_day,
    });

    // Replace current items with bulk assigned items
    form.value.items = response.items || response.data.items;

    showBulkDialog.value = false;
    alert(
      response.message ||
        `Successfully added ${form.value.items.length} employees`,
    );
  } catch (error) {
    console.error("Error bulk assigning employees:", error);
    alert(
      "Failed to bulk add employees: " +
        (error.response?.data?.message || error.message),
    );
  } finally {
    bulkLoading.value = false;
  }
}

function removeItem(index) {
  form.value.items.splice(index, 1);
}

function calculateTotal(item) {
  item.total_amount = (item.no_of_days || 0) * (item.amount_per_day || 0);
}

function onEmployeeSelect(index, employeeId) {
  const employee = availableEmployees.value.find((e) => e.id === employeeId);
  if (employee) {
    // Store employee details
    form.value.items[index].employee_name = employee.name;
    form.value.items[index].employee_number = employee.employee_number;
    form.value.items[index].position_code = employee.position_code;
    // Set default amount based on employee's daily rate
    form.value.items[index].amount_per_day = employee.basic_salary || 120.0;
    calculateTotal(form.value.items[index]);
  }
}

async function save() {
  const { valid } = await formRef.value.validate();
  if (!valid) return;

  if (form.value.items.length === 0) {
    alert("Please add at least one employee");
    return;
  }

  if (!form.value.title) {
    const typeLabel = getAllowanceTypeLabel(
      form.value.allowance_type,
      form.value.custom_allowance_type,
    );
    form.value.title = `${typeLabel} Allowance`;
  }

  saving.value = true;
  try {
    if (isEdit.value) {
      await mealAllowanceService.update(props.mealAllowance.id, form.value);
      alert("Allowance updated successfully");
    } else {
      await mealAllowanceService.create(form.value);
      alert("Allowance saved as draft");
    }
    emit("saved");
    close();
  } catch (error) {
    console.error("Error saving allowance:", error);
    alert(
      "Failed to save allowance: " +
        (error.response?.data?.message || error.message),
    );
  } finally {
    saving.value = false;
  }
}

async function saveAndSubmit() {
  const { valid } = await formRef.value.validate();
  if (!valid) return;

  if (form.value.items.length === 0) {
    alert("Please add at least one employee");
    return;
  }

  if (!form.value.title) {
    const typeLabel = getAllowanceTypeLabel(
      form.value.allowance_type,
      form.value.custom_allowance_type,
    );
    form.value.title = `${typeLabel} Allowance`;
  }

  if (!confirm("Create and submit this allowance for admin approval?")) return;

  saving.value = true;
  try {
    // First create the allowance
    const response = await mealAllowanceService.create(form.value);
    const mealAllowanceId = response.data.id;

    // Then submit it for approval
    await mealAllowanceService.submit(mealAllowanceId);

    alert("Allowance created and submitted for approval successfully!");
    emit("saved");
    close();
  } catch (error) {
    console.error("Error creating and submitting allowance:", error);
    alert(
      "Failed to create and submit: " +
        (error.response?.data?.message || error.message),
    );
  } finally {
    saving.value = false;
  }
}

function close() {
  emit("update:modelValue", false);
  resetForm();
}

function resetForm() {
  form.value = {
    title: "",
    allowance_type: null,
    custom_allowance_type: "",
    location: "",
    period_start: "",
    period_end: "",
    position_id: null,
    department: null,
    employee_id: null,
    employee_ids: [],
    notes: "",
    items: [],
  };
  availableEmployees.value = [];
}

function getAllowanceTypeLabel(type, customType) {
  if (type === "custom") return customType || "Custom";
  const match = allowanceTypes.find((t) => t.value === type);
  return match ? match.title : "Allowance";
}

function inferAllowanceTypeFromTitle(title) {
  const normalizedTitle = (title || "").toLowerCase();
  const baseTypes = allowanceTypes
    .filter((type) => type.value !== "custom")
    .map((type) => ({
      value: type.value,
      title: type.title.toLowerCase(),
    }));

  const match = baseTypes.find((type) => normalizedTitle.includes(type.title));

  if (match) {
    return { type: match.value, custom: "" };
  }

  return { type: "custom", custom: title || "" };
}

async function applySelection() {
  if (!bulkForm.value.no_of_days || !bulkForm.value.amount_per_day) {
    alert("Please fill in Number of Days and Amount per Day");
    return;
  }

  let employees = [];

  if (selectionMode.value === "individual") {
    if (!form.value.employee_id) {
      alert("Please select an employee");
      return;
    }
    employees = availableEmployees.value.filter(
      (emp) => emp.id === form.value.employee_id,
    );
  }

  if (selectionMode.value === "multiple") {
    if (!form.value.employee_ids || form.value.employee_ids.length === 0) {
      alert("Please select employees");
      return;
    }
    employees = availableEmployees.value.filter((emp) =>
      form.value.employee_ids.includes(emp.id),
    );
  }

  if (selectionMode.value === "department") {
    if (!form.value.department) {
      alert("Please select a department");
      return;
    }
    employees = await mealAllowanceService.getEmployeesByPosition(
      null,
      form.value.department,
      "all",
    );
  }

  if (selectionMode.value === "position") {
    if (!form.value.position_id) {
      alert("Please select a position");
      return;
    }
    employees = await mealAllowanceService.getEmployeesByPosition(
      form.value.position_id,
      null,
      null,
    );
  }

  form.value.items = employees.map((emp, index) => ({
    employee_id: emp.id,
    employee_name: emp.name,
    employee_number: emp.employee_number,
    position_code: emp.position_code,
    department: emp.department,
    no_of_days: bulkForm.value.no_of_days,
    amount_per_day: bulkForm.value.amount_per_day,
    total_amount: bulkForm.value.no_of_days * bulkForm.value.amount_per_day,
    sort_order: index,
  }));
}

function formatNumber(value) {
  return new Intl.NumberFormat("en-PH", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value);
}
</script>

<style scoped lang="scss">
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
  margin-bottom: 0;
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

.dialog-btn-secondary {
  background: rgba(237, 152, 95, 0.1);
  color: #ed985f;
  border: 1px solid rgba(237, 152, 95, 0.2);
  margin-left: 12px;

  &:hover:not(:disabled) {
    background: rgba(237, 152, 95, 0.15);
    border-color: rgba(237, 152, 95, 0.3);
  }
}

.dialog-btn-primary {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: white;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);
  margin-left: 12px;

  &:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(237, 152, 95, 0.4);
  }
}
</style>
