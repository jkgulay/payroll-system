<template>
  <div class="pa-4">
    <!-- Summary Cards -->
    <v-row class="mb-4">
      <v-col cols="12" md="3">
        <v-card variant="outlined" class="pa-3">
          <div class="d-flex align-center">
            <v-avatar color="primary" size="40" class="mr-3">
              <v-icon color="white">mdi-account-group</v-icon>
            </v-avatar>
            <div>
              <div class="text-caption text-medium-emphasis">
                Total Employees
              </div>
              <div class="text-h6 font-weight-bold">
                {{ summary.total_employees || 0 }}
              </div>
            </div>
          </div>
        </v-card>
      </v-col>
      <v-col cols="12" md="3">
        <v-card variant="outlined" class="pa-3">
          <div class="d-flex align-center">
            <v-avatar color="blue" size="40" class="mr-3">
              <v-icon color="white">mdi-shield-account</v-icon>
            </v-avatar>
            <div>
              <div class="text-caption text-medium-emphasis">Total SSS</div>
              <div class="text-h6 font-weight-bold text-blue">
                ₱{{ formatCurrency(summary.total_sss || 0) }}
              </div>
            </div>
          </div>
        </v-card>
      </v-col>
      <v-col cols="12" md="3">
        <v-card variant="outlined" class="pa-3">
          <div class="d-flex align-center">
            <v-avatar color="green" size="40" class="mr-3">
              <v-icon color="white">mdi-hospital-box</v-icon>
            </v-avatar>
            <div>
              <div class="text-caption text-medium-emphasis">
                Total PhilHealth
              </div>
              <div class="text-h6 font-weight-bold text-green">
                ₱{{ formatCurrency(summary.total_philhealth || 0) }}
              </div>
            </div>
          </div>
        </v-card>
      </v-col>
      <v-col cols="12" md="3">
        <v-card variant="outlined" class="pa-3">
          <div class="d-flex align-center">
            <v-avatar color="orange" size="40" class="mr-3">
              <v-icon color="white">mdi-home-city</v-icon>
            </v-avatar>
            <div>
              <div class="text-caption text-medium-emphasis">
                Total Pag-IBIG
              </div>
              <div class="text-h6 font-weight-bold text-orange">
                ₱{{ formatCurrency(summary.total_pagibig || 0) }}
              </div>
            </div>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- Filters -->
    <v-row class="mb-4">
      <v-col cols="12" md="4">
        <v-text-field
          v-model="search"
          prepend-inner-icon="mdi-magnify"
          label="Search employees..."
          variant="outlined"
          density="compact"
          hide-details
          clearable
        ></v-text-field>
      </v-col>
      <v-col cols="12" md="3">
        <v-select
          v-model="selectedProject"
          :items="projects"
          item-title="name"
          item-value="id"
          label="Filter by Department"
          variant="outlined"
          density="compact"
          hide-details
          clearable
        ></v-select>
      </v-col>
      <v-col cols="12" md="3">
        <v-select
          v-model="selectedDepartment"
          :items="departments"
          label="Filter by Department"
          variant="outlined"
          density="compact"
          hide-details
          clearable
        ></v-select>
      </v-col>
      <v-col cols="12" md="2" class="d-flex align-center">
        <v-btn
          color="primary"
          variant="tonal"
          @click="loadData"
          :loading="loading"
          block
        >
          <v-icon class="mr-1">mdi-refresh</v-icon>
          Refresh
        </v-btn>
      </v-col>
    </v-row>

    <!-- Data Table -->
    <v-data-table
      :headers="headers"
      :items="filteredEmployees"
      :loading="loading"
      :search="search"
      class="elevation-1"
      density="comfortable"
      :items-per-page="15"
    >
      <template #item.full_name="{ item }">
        <div>
          <div class="font-weight-medium">{{ item.full_name }}</div>
          <div class="text-caption text-medium-emphasis">
            {{ item.employee_number }}
          </div>
        </div>
      </template>

      <template #item.position="{ item }">
        <div>
          <div>{{ item.position || "N/A" }}</div>
          <div class="text-caption text-medium-emphasis">
            {{ item.department || "N/A" }}
          </div>
        </div>
      </template>

      <template #item.monthly_rate="{ item }">
        <span class="font-weight-medium"
          >₱{{ formatCurrency(item.monthly_rate) }}</span
        >
      </template>

      <template #item.has_sss="{ item }">
        <v-checkbox
          v-model="item.has_sss"
          color="primary"
          hide-details
          density="compact"
          @change="updateEmployee(item)"
        ></v-checkbox>
      </template>

      <template #item.effective_sss="{ item }">
        <div class="d-flex align-center">
          <v-text-field
            v-if="editingId === item.id && editingField === 'sss'"
            v-model.number="editValue"
            type="number"
            variant="outlined"
            density="compact"
            hide-details
            style="width: 100px"
            @blur="saveEdit(item, 'custom_sss')"
            @keyup.enter="saveEdit(item, 'custom_sss')"
            @keyup.escape="cancelEdit"
            autofocus
          ></v-text-field>
          <template v-else>
            <span
              :class="{
                'text-primary font-weight-medium': item.custom_sss !== null,
              }"
            >
              ₱{{ formatCurrency(item.effective_sss) }}
            </span>
            <v-icon
              v-if="item.custom_sss !== null"
              size="14"
              color="primary"
              class="ml-1"
              title="Custom value"
              >mdi-pencil-circle</v-icon
            >
            <v-btn
              icon
              size="x-small"
              variant="text"
              class="ml-1"
              @click="
                startEdit(item, 'sss', item.custom_sss ?? item.computed_sss)
              "
              :disabled="!item.has_sss"
            >
              <v-icon size="14">mdi-pencil</v-icon>
            </v-btn>
          </template>
        </div>
      </template>

      <template #item.has_philhealth="{ item }">
        <v-checkbox
          v-model="item.has_philhealth"
          color="success"
          hide-details
          density="compact"
          @change="updateEmployee(item)"
        ></v-checkbox>
      </template>

      <template #item.effective_philhealth="{ item }">
        <div class="d-flex align-center">
          <v-text-field
            v-if="editingId === item.id && editingField === 'philhealth'"
            v-model.number="editValue"
            type="number"
            variant="outlined"
            density="compact"
            hide-details
            style="width: 100px"
            @blur="saveEdit(item, 'custom_philhealth')"
            @keyup.enter="saveEdit(item, 'custom_philhealth')"
            @keyup.escape="cancelEdit"
            autofocus
          ></v-text-field>
          <template v-else>
            <span
              :class="{
                'text-success font-weight-medium':
                  item.custom_philhealth !== null,
              }"
            >
              ₱{{ formatCurrency(item.effective_philhealth) }}
            </span>
            <v-icon
              v-if="item.custom_philhealth !== null"
              size="14"
              color="success"
              class="ml-1"
              title="Custom value"
              >mdi-pencil-circle</v-icon
            >
            <v-btn
              icon
              size="x-small"
              variant="text"
              class="ml-1"
              @click="
                startEdit(
                  item,
                  'philhealth',
                  item.custom_philhealth ?? item.computed_philhealth,
                )
              "
              :disabled="!item.has_philhealth"
            >
              <v-icon size="14">mdi-pencil</v-icon>
            </v-btn>
          </template>
        </div>
      </template>

      <template #item.has_pagibig="{ item }">
        <v-checkbox
          v-model="item.has_pagibig"
          color="orange"
          hide-details
          density="compact"
          @change="updateEmployee(item)"
        ></v-checkbox>
      </template>

      <template #item.effective_pagibig="{ item }">
        <div class="d-flex align-center">
          <v-text-field
            v-if="editingId === item.id && editingField === 'pagibig'"
            v-model.number="editValue"
            type="number"
            variant="outlined"
            density="compact"
            hide-details
            style="width: 100px"
            @blur="saveEdit(item, 'custom_pagibig')"
            @keyup.enter="saveEdit(item, 'custom_pagibig')"
            @keyup.escape="cancelEdit"
            autofocus
          ></v-text-field>
          <template v-else>
            <span
              :class="{
                'text-orange font-weight-medium': item.custom_pagibig !== null,
              }"
            >
              ₱{{ formatCurrency(item.effective_pagibig) }}
            </span>
            <v-icon
              v-if="item.custom_pagibig !== null"
              size="14"
              color="orange"
              class="ml-1"
              title="Custom value"
              >mdi-pencil-circle</v-icon
            >
            <v-btn
              icon
              size="x-small"
              variant="text"
              class="ml-1"
              @click="
                startEdit(
                  item,
                  'pagibig',
                  item.custom_pagibig ?? item.computed_pagibig,
                )
              "
              :disabled="!item.has_pagibig"
            >
              <v-icon size="14">mdi-pencil</v-icon>
            </v-btn>
          </template>
        </div>
      </template>

      <template #item.total="{ item }">
        <v-chip color="primary" variant="tonal" size="small">
          ₱{{
            formatCurrency(
              item.effective_sss +
                item.effective_philhealth +
                item.effective_pagibig,
            )
          }}
        </v-chip>
      </template>

      <template #item.actions="{ item }">
        <v-btn
          icon
          size="small"
          variant="text"
          color="error"
          @click="resetEmployee(item)"
          title="Reset to default"
          :disabled="
            item.custom_sss === null &&
            item.custom_philhealth === null &&
            item.custom_pagibig === null
          "
        >
          <v-icon size="18">mdi-restore</v-icon>
        </v-btn>
      </template>

      <template #no-data>
        <div class="text-center pa-4">
          <v-icon size="64" color="grey-lighten-1">mdi-account-off</v-icon>
          <p class="text-grey mt-2">No employees found</p>
        </div>
      </template>
    </v-data-table>

    <!-- Legend -->
    <v-card variant="tonal" color="info" class="mt-4 pa-3">
      <div class="d-flex align-center flex-wrap ga-4">
        <div class="d-flex align-center">
          <v-icon size="16" color="primary" class="mr-1"
            >mdi-pencil-circle</v-icon
          >
          <span class="text-caption">Custom value set</span>
        </div>
        <div class="d-flex align-center">
          <v-icon size="16" class="mr-1">mdi-restore</v-icon>
          <span class="text-caption">Reset to computed default</span>
        </div>
        <div class="text-caption text-medium-emphasis">
          Note: Custom contributions are semi-monthly amounts and will be
          deducted as-is per payroll cutoff.
        </div>
      </div>
    </v-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from "vue";
import { useToast } from "vue-toastification";
import api from "@/services/api";
import { devLog } from "@/utils/devLog";
import { formatCurrency } from "@/utils/formatters";
import { useConfirmDialog } from "@/composables/useConfirmDialog";

const toast = useToast();
const { confirm: confirmDialog } = useConfirmDialog();

const loading = ref(false);
const employees = ref([]);
const summary = ref({});
const search = ref("");
const selectedProject = ref(null);
const selectedDepartment = ref(null);
const projects = ref([]);
const departments = ref([]);

// Inline editing state
const editingId = ref(null);
const editingField = ref(null);
const editValue = ref(null);

const headers = [
  { title: "Employee", key: "full_name", sortable: true, width: "180px" },
  { title: "Position/Dept", key: "position", sortable: true, width: "150px" },
  { title: "Monthly Rate", key: "monthly_rate", align: "end", width: "120px" },
  {
    title: "SSS",
    key: "has_sss",
    align: "center",
    width: "60px",
    sortable: false,
  },
  { title: "SSS Amount", key: "effective_sss", align: "end", width: "130px" },
  {
    title: "PH",
    key: "has_philhealth",
    align: "center",
    width: "60px",
    sortable: false,
  },
  {
    title: "PhilHealth",
    key: "effective_philhealth",
    align: "end",
    width: "130px",
  },
  {
    title: "PI",
    key: "has_pagibig",
    align: "center",
    width: "60px",
    sortable: false,
  },
  { title: "Pag-IBIG", key: "effective_pagibig", align: "end", width: "130px" },
  { title: "Total", key: "total", align: "end", width: "100px" },
  { title: "", key: "actions", sortable: false, width: "60px" },
];

const filteredEmployees = computed(() => {
  let result = employees.value;

  if (selectedProject.value) {
    result = result.filter((e) => e.project_id === selectedProject.value);
  }

  if (selectedDepartment.value) {
    result = result.filter((e) => e.department === selectedDepartment.value);
  }

  return result;
});

const loadData = async () => {
  loading.value = true;
  try {
    const [employeesRes, summaryRes, projectsRes] = await Promise.all([
      api.get("/employee-contributions"),
      api.get("/employee-contributions/summary"),
      api.get("/projects"),
    ]);

    employees.value = employeesRes.data.data || [];
    summary.value = summaryRes.data || {};
    projects.value = projectsRes.data.data || projectsRes.data || [];

    // Extract unique departments
    const depts = new Set(
      employees.value.map((e) => e.department).filter(Boolean),
    );
    departments.value = Array.from(depts).sort();
  } catch (error) {
    devLog.error("Failed to load employee contributions:", error);
    toast.error("Failed to load employee contributions");
  } finally {
    loading.value = false;
  }
};

const updateEmployee = async (employee) => {
  try {
    const response = await api.put(`/employee-contributions/${employee.id}`, {
      has_sss: employee.has_sss,
      has_philhealth: employee.has_philhealth,
      has_pagibig: employee.has_pagibig,
      custom_sss: employee.custom_sss,
      custom_philhealth: employee.custom_philhealth,
      custom_pagibig: employee.custom_pagibig,
    });

    // Update local data with response
    const updated = response.data.data;
    const index = employees.value.findIndex((e) => e.id === employee.id);
    if (index !== -1) {
      employees.value[index] = { ...employees.value[index], ...updated };
    }

    // Refresh summary
    const summaryRes = await api.get("/employee-contributions/summary");
    summary.value = summaryRes.data || {};

    toast.success("Contribution updated");
  } catch (error) {
    devLog.error("Failed to update employee:", error);
    toast.error("Failed to update contribution");
    // Reload to reset state
    loadData();
  }
};

const startEdit = (item, field, value) => {
  editingId.value = item.id;
  editingField.value = field;
  editValue.value = value;
};

const saveEdit = async (item, fieldName) => {
  if (editValue.value === null || editValue.value === "") {
    item[fieldName] = null;
  } else {
    item[fieldName] = parseFloat(editValue.value);
  }

  // Recalculate effective value
  if (fieldName === "custom_sss") {
    item.effective_sss = item.has_sss
      ? (item.custom_sss ?? item.computed_sss)
      : 0;
  } else if (fieldName === "custom_philhealth") {
    item.effective_philhealth = item.has_philhealth
      ? (item.custom_philhealth ?? item.computed_philhealth)
      : 0;
  } else if (fieldName === "custom_pagibig") {
    item.effective_pagibig = item.has_pagibig
      ? (item.custom_pagibig ?? item.computed_pagibig)
      : 0;
  }

  await updateEmployee(item);
  cancelEdit();
};

const cancelEdit = () => {
  editingId.value = null;
  editingField.value = null;
  editValue.value = null;
};

const resetEmployee = async (employee) => {
  if (
    !(await confirmDialog(
      `Reset contributions for ${employee.full_name} to default computed values?`,
    ))
  ) {
    return;
  }

  try {
    const response = await api.post(
      `/employee-contributions/${employee.id}/reset`,
    );

    // Update local data
    const updated = response.data.data;
    const index = employees.value.findIndex((e) => e.id === employee.id);
    if (index !== -1) {
      employees.value[index] = {
        ...employees.value[index],
        custom_sss: null,
        custom_philhealth: null,
        custom_pagibig: null,
        effective_sss: employees.value[index].has_sss
          ? updated.computed_sss
          : 0,
        effective_philhealth: employees.value[index].has_philhealth
          ? updated.computed_philhealth
          : 0,
        effective_pagibig: employees.value[index].has_pagibig
          ? updated.computed_pagibig
          : 0,
      };
    }

    // Refresh summary
    const summaryRes = await api.get("/employee-contributions/summary");
    summary.value = summaryRes.data || {};

    toast.success("Contributions reset to default");
  } catch (error) {
    devLog.error("Failed to reset contributions:", error);
    toast.error("Failed to reset contributions");
  }
};

onMounted(() => {
  loadData();
});

// Expose loadData for parent component
defineExpose({ loadData });
</script>

<style scoped>
.v-data-table :deep(th) {
  white-space: nowrap;
}
</style>
