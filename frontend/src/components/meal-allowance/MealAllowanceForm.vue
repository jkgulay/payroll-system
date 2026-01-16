<template>
  <v-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    max-width="1200px"
    persistent
  >
    <v-card class="modern-dialog-card" elevation="24">
      <!-- Enhanced Header -->
      <v-card-title class="modern-dialog-header modern-dialog-header-warning">
        <div class="d-flex align-center w-100">
          <v-avatar color="white" size="48" class="mr-4">
            <v-icon color="warning" size="32">mdi-food</v-icon>
          </v-avatar>
          <div>
            <div class="text-h5 font-weight-bold">
              {{ isEdit ? "Edit Meal Allowance" : "Create Meal Allowance" }}
            </div>
            <div class="text-subtitle-2 text-white-70">
              {{ isEdit ? 'Update meal allowance details' : 'Add meal allowance for employees' }}
            </div>
          </div>
          <v-spacer></v-spacer>
          <v-btn icon variant="text" color="white" @click="close" size="small">
            <v-icon>mdi-close</v-icon>
          </v-btn>
        </div>
      </v-card-title>

      <v-card-text class="pa-6">
        <v-form ref="formRef" @submit.prevent="save">
          <v-row>
            <!-- Title -->
            <v-col cols="12" md="6">
              <div class="form-field-wrapper">
                <label class="form-label">
                  <v-icon size="small" color="primary">mdi-format-title</v-icon>
                  Title <span class="text-error">*</span>
                </label>
                <v-text-field
                  v-model="form.title"
                  placeholder="Enter title"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-format-title"
                  color="primary"
                ></v-text-field>
              </div>
            </v-col>

            <!-- Location -->
            <v-col cols="12" md="6">
              <div class="form-field-wrapper">
                <label class="form-label">
                  <v-icon size="small" color="primary">mdi-map-marker</v-icon>
                  Location
                </label>
                <v-text-field
                  v-model="form.location"
                  placeholder="Enter location"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-map-marker"
                  color="primary"
                ></v-text-field>
              </div>
            </v-col>

            <!-- Period Start -->
            <v-col cols="12" md="6">
              <div class="form-field-wrapper">
                <label class="form-label">
                  <v-icon size="small" color="primary">mdi-calendar-start</v-icon>
                  Period Start <span class="text-error">*</span>
                </label>
                <v-text-field
                  v-model="form.period_start"
                  type="date"
                  placeholder="Select start date"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-calendar-start"
                  color="primary"
                ></v-text-field>
              </div>
            </v-col>

            <!-- Period End -->
            <v-col cols="12" md="6">
              <div class="form-field-wrapper">
                <label class="form-label">
                  <v-icon size="small" color="primary">mdi-calendar-end</v-icon>
                  Period End <span class="text-error">*</span>
                </label>
                <v-text-field
                  v-model="form.period_end"
                  type="date"
                  placeholder="Select end date"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-calendar-end"
                  color="primary"
                ></v-text-field>
              </div>
            </v-col>

            <!-- Position/Role -->
            <v-col cols="12" md="6">
              <div class="form-field-wrapper">
                <label class="form-label">
                  <v-icon size="small" color="primary">mdi-account-hard-hat</v-icon>
                  Position/Role <span class="text-error">*</span>
                </label>
                <v-select
                  v-model="form.position_id"
                  :items="positions"
                  item-title="position_name"
                  item-value="id"
                  placeholder="Select position/role"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-account-hard-hat"
                  color="primary"
                  @update:model-value="loadEmployees"
                ></v-select>
              </div>
            </v-col>

            <!-- Notes -->
            <v-col cols="12">
              <div class="form-field-wrapper">
                <label class="form-label">
                  <v-icon size="small" color="primary">mdi-note-text</v-icon>
                  Notes
                </label>
                <v-textarea
                  v-model="form.notes"
                  placeholder="Enter notes"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-note-text"
                  color="primary"
                  rows="2"
                ></v-textarea>
              </div>
            </v-col>
          </v-row>

          <v-divider class="my-4"></v-divider>

          <v-row>
            <v-col cols="12" class="d-flex justify-space-between align-center">
              <h3>Employees</h3>
              <div class="d-flex gap-2 align-center">
                <v-chip v-if="form.items.length > 0" color="info" class="mr-2">
                  {{ form.items.length }} employee(s)
                </v-chip>

                <!-- Bulk Assignment Dialog -->
                <v-dialog v-model="showBulkDialog" max-width="500px">
                  <template v-slot:activator="{ props }">
                    <v-btn
                      v-if="form.position_id && availableEmployees.length > 0"
                      v-bind="props"
                      color="primary"
                      size="small"
                      prepend-icon="mdi-account-multiple-plus"
                    >
                      Bulk Add by Position
                    </v-btn>
                  </template>

                  <v-card>
                    <v-card-title class="bg-primary">
                      <v-icon left>mdi-account-multiple-plus</v-icon>
                      Bulk Add Employees by Position
                    </v-card-title>

                    <v-card-text class="pt-4">
                      <v-alert type="info" variant="tonal" class="mb-4">
                        This will add all {{ availableEmployees.length }} active
                        employee(s) with the selected position to the meal
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
              </div>
            </v-col>
          </v-row>

          <v-data-table
            :headers="itemHeaders"
            :items="form.items"
            :loading="loadingEmployees"
            density="compact"
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
                  <strong>Grand Total: ₱{{ formatNumber(grandTotal) }}</strong>
                </v-col>
              </v-row>
            </template>
          </v-data-table>
        </v-form>
      </v-card-text>

      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn @click="close" variant="outlined">Cancel</v-btn>
        <v-btn
          color="success"
          @click="saveAndSubmit"
          :loading="saving"
          v-if="!isEdit"
        >
          <v-icon left>mdi-send</v-icon>
          Create & Submit for Approval
        </v-btn>
        <v-btn color="primary" @click="save" :loading="saving">
          {{ isEdit ? "Update" : "Save as Draft" }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import mealAllowanceService from "@/services/mealAllowanceService";

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

const form = ref({
  title: "",
  location: "",
  period_start: "",
  period_end: "",
  position_id: null,
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
      form.value = {
        title: newVal.title,
        location: newVal.location || "",
        period_start: newVal.period_start,
        period_end: newVal.period_end,
        position_id: newVal.position_id,
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
  { immediate: true }
);

async function loadEmployees() {
  if (!form.value.position_id) return;

  loadingEmployees.value = true;
  try {
    availableEmployees.value =
      await mealAllowanceService.getEmployeesByPosition(form.value.position_id);
  } catch (error) {
    console.error("Error loading employees:", error);
  } finally {
    loadingEmployees.value = false;
  }
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
  if (!form.value.position_id) {
    alert("Please select a position first");
    return;
  }

  if (!bulkForm.value.no_of_days || !bulkForm.value.amount_per_day) {
    alert("Please fill in all fields");
    return;
  }

  if (
    !confirm(
      `Add all ${availableEmployees.value.length} employee(s) to this meal allowance? This will replace existing entries.`
    )
  ) {
    return;
  }

  bulkLoading.value = true;
  try {
    const response = await mealAllowanceService.bulkAssignByPosition({
      position_id: form.value.position_id,
      project_id: form.value.project_id || null,
      no_of_days: bulkForm.value.no_of_days,
      amount_per_day: bulkForm.value.amount_per_day,
    });

    // Replace current items with bulk assigned items
    form.value.items = response.items || response.data.items;

    showBulkDialog.value = false;
    alert(
      response.message ||
        `Successfully added ${form.value.items.length} employees`
    );
  } catch (error) {
    console.error("Error bulk assigning employees:", error);
    alert(
      "Failed to bulk add employees: " +
        (error.response?.data?.message || error.message)
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

  saving.value = true;
  try {
    if (isEdit.value) {
      await mealAllowanceService.update(props.mealAllowance.id, form.value);
      alert("Meal allowance updated successfully");
    } else {
      await mealAllowanceService.create(form.value);
      alert("Meal allowance saved as draft");
    }
    emit("saved");
    close();
  } catch (error) {
    console.error("Error saving meal allowance:", error);
    alert(
      "Failed to save meal allowance: " +
        (error.response?.data?.message || error.message)
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

  if (!confirm("Create and submit this meal allowance for admin approval?"))
    return;

  saving.value = true;
  try {
    // First create the meal allowance
    const response = await mealAllowanceService.create(form.value);
    const mealAllowanceId = response.data.id;

    // Then submit it for approval
    await mealAllowanceService.submit(mealAllowanceId);

    alert("Meal allowance created and submitted for approval successfully!");
    emit("saved");
    close();
  } catch (error) {
    console.error("Error creating and submitting meal allowance:", error);
    alert(
      "Failed to create and submit: " +
        (error.response?.data?.message || error.message)
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
    location: "",
    period_start: "",
    period_end: "",
    position_id: null,
    notes: "",
    items: [],
  };
  availableEmployees.value = [];
}

function formatNumber(value) {
  return new Intl.NumberFormat("en-PH", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value);
}
</script>
