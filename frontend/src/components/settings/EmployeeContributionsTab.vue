<template>
  <div class="contributions-tab">
    <!-- Summary Cards -->
    <div class="summary-grid">
      <div class="summary-card summary-employees">
        <div class="summary-icon-ring">
          <v-icon size="22">mdi-account-group</v-icon>
        </div>
        <div class="summary-info">
          <span class="summary-value">{{ summary.total_employees || 0 }}</span>
          <span class="summary-label">Total Employees</span>
        </div>
      </div>
      <div class="summary-card summary-sss">
        <div class="summary-icon-ring">
          <v-icon size="22">mdi-shield-account</v-icon>
        </div>
        <div class="summary-info">
          <span class="summary-value"
            >₱{{ formatCurrency(summary.total_sss || 0) }}</span
          >
          <span class="summary-label"
            >SSS
            <span class="summary-count"
              >({{ summary.employees_with_sss || 0 }})</span
            ></span
          >
        </div>
      </div>
      <div class="summary-card summary-ph">
        <div class="summary-icon-ring">
          <v-icon size="22">mdi-hospital-box</v-icon>
        </div>
        <div class="summary-info">
          <span class="summary-value"
            >₱{{ formatCurrency(summary.total_philhealth || 0) }}</span
          >
          <span class="summary-label"
            >PhilHealth
            <span class="summary-count"
              >({{ summary.employees_with_philhealth || 0 }})</span
            ></span
          >
        </div>
      </div>
      <div class="summary-card summary-pi">
        <div class="summary-icon-ring">
          <v-icon size="22">mdi-home-city</v-icon>
        </div>
        <div class="summary-info">
          <span class="summary-value"
            >₱{{ formatCurrency(summary.total_pagibig || 0) }}</span
          >
          <span class="summary-label"
            >Pag-IBIG
            <span class="summary-count"
              >({{ summary.employees_with_pagibig || 0 }})</span
            ></span
          >
        </div>
      </div>
      <div class="summary-card summary-total">
        <div class="summary-icon-ring">
          <v-icon size="22">mdi-sigma</v-icon>
        </div>
        <div class="summary-info">
          <span class="summary-value"
            >₱{{ formatCurrency(summary.total_contributions || 0) }}</span
          >
          <span class="summary-label"
            >Total Per Cutoff
            <span class="summary-count"
              >({{ summary.employees_with_custom || 0 }} custom)</span
            ></span
          >
        </div>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="toolbar-row">
      <v-text-field
        v-model="search"
        prepend-inner-icon="mdi-magnify"
        placeholder="Search employee name or number..."
        variant="outlined"
        density="compact"
        hide-details
        clearable
        class="toolbar-search"
      />
      <v-select
        v-model="selectedProject"
        :items="projects"
        item-title="name"
        item-value="id"
        label="Project"
        variant="outlined"
        density="compact"
        hide-details
        clearable
        class="toolbar-filter"
      />
      <v-select
        v-model="statusFilter"
        :items="statusOptions"
        label="Status"
        variant="outlined"
        density="compact"
        hide-details
        clearable
        class="toolbar-filter-sm"
      />
      <v-btn
        color="primary"
        variant="tonal"
        density="comfortable"
        @click="loadData"
        :loading="loading"
        prepend-icon="mdi-refresh"
      >
        Refresh
      </v-btn>
    </div>

    <!-- Data Table -->
    <v-card variant="flat" class="table-card">
      <v-data-table
        :headers="headers"
        :items="filteredEmployees"
        :loading="loading"
        :search="search"
        density="comfortable"
        :items-per-page="15"
        class="contributions-table"
        hover
        @click:row="(_, { item }) => openEditDialog(item)"
      >
        <!-- Employee Info -->
        <template #item.full_name="{ item }">
          <div class="employee-cell">
            <v-avatar
              :color="getAvatarColor(item.full_name)"
              size="32"
              class="mr-2"
            >
              <span class="text-white text-caption font-weight-bold">{{
                getInitials(item.full_name)
              }}</span>
            </v-avatar>
            <div>
              <div class="font-weight-medium text-body-2">
                {{ item.full_name }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ item.employee_number }}
                <span v-if="item.project"> · {{ item.project }}</span>
              </div>
            </div>
          </div>
        </template>

        <!-- Monthly Rate -->
        <template #item.monthly_rate="{ item }">
          <span class="amount-cell"
            >₱{{ formatCurrency(item.monthly_rate) }}</span
          >
        </template>

        <!-- SSS -->
        <template #item.effective_sss="{ item }">
          <div class="contribution-cell" @click.stop="openEditDialog(item)">
            <v-chip
              v-if="item.has_sss"
              :color="item.custom_sss !== null ? 'blue' : 'blue-grey'"
              :variant="item.custom_sss !== null ? 'flat' : 'tonal'"
              size="small"
              class="contribution-chip"
            >
              <span class="font-weight-medium"
                >₱{{ formatCurrency(item.effective_sss) }}</span
              >
              <v-icon v-if="item.custom_sss !== null" size="12" class="ml-1"
                >mdi-pencil</v-icon
              >
            </v-chip>
            <v-chip v-else size="small" variant="outlined" color="grey">
              OFF
            </v-chip>
          </div>
        </template>

        <!-- PhilHealth -->
        <template #item.effective_philhealth="{ item }">
          <div class="contribution-cell" @click.stop="openEditDialog(item)">
            <v-chip
              v-if="item.has_philhealth"
              :color="item.custom_philhealth !== null ? 'green' : 'blue-grey'"
              :variant="item.custom_philhealth !== null ? 'flat' : 'tonal'"
              size="small"
              class="contribution-chip"
            >
              <span class="font-weight-medium"
                >₱{{ formatCurrency(item.effective_philhealth) }}</span
              >
              <v-icon
                v-if="item.custom_philhealth !== null"
                size="12"
                class="ml-1"
                >mdi-pencil</v-icon
              >
            </v-chip>
            <v-chip v-else size="small" variant="outlined" color="grey">
              OFF
            </v-chip>
          </div>
        </template>

        <!-- Pag-IBIG -->
        <template #item.effective_pagibig="{ item }">
          <div class="contribution-cell" @click.stop="openEditDialog(item)">
            <v-chip
              v-if="item.has_pagibig"
              :color="item.custom_pagibig !== null ? 'orange' : 'blue-grey'"
              :variant="item.custom_pagibig !== null ? 'flat' : 'tonal'"
              size="small"
              class="contribution-chip"
            >
              <span class="font-weight-medium"
                >₱{{ formatCurrency(item.effective_pagibig) }}</span
              >
              <v-icon v-if="item.custom_pagibig !== null" size="12" class="ml-1"
                >mdi-pencil</v-icon
              >
            </v-chip>
            <v-chip v-else size="small" variant="outlined" color="grey">
              OFF
            </v-chip>
          </div>
        </template>

        <!-- Total -->
        <template #item.total="{ item }">
          <span class="total-cell font-weight-bold">
            ₱{{
              formatCurrency(
                (item.has_sss ? parseFloat(item.effective_sss) || 0 : 0) +
                  (item.has_philhealth
                    ? parseFloat(item.effective_philhealth) || 0
                    : 0) +
                  (item.has_pagibig
                    ? parseFloat(item.effective_pagibig) || 0
                    : 0),
              )
            }}
          </span>
        </template>

        <!-- Actions -->
        <template #item.actions="{ item }">
          <div class="actions-cell" @click.stop>
            <v-btn
              icon
              size="x-small"
              variant="text"
              color="primary"
              @click="openEditDialog(item)"
              title="Edit contributions"
            >
              <v-icon size="18">mdi-pencil</v-icon>
            </v-btn>
            <v-btn
              icon
              size="x-small"
              variant="text"
              color="error"
              @click="resetEmployee(item)"
              title="Reset to computed defaults"
              :disabled="
                item.custom_sss === null &&
                item.custom_philhealth === null &&
                item.custom_pagibig === null
              "
            >
              <v-icon size="18">mdi-restore</v-icon>
            </v-btn>
          </div>
        </template>

        <!-- Empty State -->
        <template #no-data>
          <div class="empty-state">
            <v-icon size="56" color="grey-lighten-2">mdi-account-search</v-icon>
            <p class="text-body-1 text-medium-emphasis mt-3">
              No employees found
            </p>
          </div>
        </template>
      </v-data-table>
    </v-card>

    <!-- Legend -->
    <div class="legend-row">
      <div class="legend-item">
        <v-chip size="x-small" color="blue" variant="flat">₱0.00</v-chip>
        <span>Custom override set</span>
      </div>
      <div class="legend-item">
        <v-chip size="x-small" color="blue-grey" variant="tonal">₱0.00</v-chip>
        <span>Auto-computed from salary</span>
      </div>
      <div class="legend-item">
        <v-chip size="x-small" variant="outlined" color="grey">OFF</v-chip>
        <span>Contribution disabled</span>
      </div>
      <div class="legend-note">
        <v-icon size="14" class="mr-1">mdi-information-outline</v-icon>
        All amounts are <strong>semi-monthly (per cutoff)</strong> and deducted
        as-is from each payroll period. Click any row to edit.
      </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════ -->
    <!-- Edit Contribution Dialog                                    -->
    <!-- ═══════════════════════════════════════════════════════════ -->
    <v-dialog v-model="editDialog" max-width="560" persistent>
      <v-card v-if="editEmployee" class="edit-dialog-card">
        <!-- Header -->
        <div class="edit-header">
          <v-avatar
            :color="getAvatarColor(editEmployee.full_name)"
            size="44"
            class="mr-3"
          >
            <span class="text-white font-weight-bold">{{
              getInitials(editEmployee.full_name)
            }}</span>
          </v-avatar>
          <div class="flex-grow-1">
            <div class="text-h6 font-weight-bold">
              {{ editEmployee.full_name }}
            </div>
            <div class="text-caption text-medium-emphasis">
              {{ editEmployee.employee_number }}
              <span v-if="editEmployee.project">
                · {{ editEmployee.project }}</span
              >
              · Monthly: ₱{{ formatCurrency(editEmployee.monthly_rate) }}
            </div>
          </div>
          <v-btn
            icon
            variant="text"
            size="small"
            @click="closeEditDialog"
            :disabled="editSaving"
          >
            <v-icon>mdi-close</v-icon>
          </v-btn>
        </div>

        <v-divider />

        <!-- Contribution Sections -->
        <v-card-text class="edit-body">
          <!-- SSS -->
          <div class="contrib-section">
            <div class="contrib-header">
              <div class="contrib-label">
                <v-icon size="20" color="blue" class="mr-2"
                  >mdi-shield-account</v-icon
                >
                <span class="font-weight-bold">SSS</span>
              </div>
              <v-switch
                v-model="editForm.has_sss"
                color="blue"
                density="compact"
                hide-details
                inset
              />
            </div>
            <div v-if="editForm.has_sss" class="contrib-fields">
              <div class="computed-row">
                <span class="text-caption text-medium-emphasis"
                  >Computed from salary:</span
                >
                <span class="text-caption font-weight-medium"
                  >₱{{ formatCurrency(editEmployee.computed_sss) }}</span
                >
              </div>
              <v-text-field
                v-model.number="editForm.custom_sss"
                label="Custom SSS Amount (per cutoff)"
                type="number"
                step="0.01"
                prefix="₱"
                variant="outlined"
                density="compact"
                hide-details="auto"
                hint="Leave blank to use computed value"
                persistent-hint
                clearable
                @click:clear="editForm.custom_sss = null"
              />
            </div>
            <div v-else class="contrib-disabled">
              SSS deduction is disabled for this employee
            </div>
          </div>

          <!-- PhilHealth -->
          <div class="contrib-section">
            <div class="contrib-header">
              <div class="contrib-label">
                <v-icon size="20" color="green" class="mr-2"
                  >mdi-hospital-box</v-icon
                >
                <span class="font-weight-bold">PhilHealth</span>
              </div>
              <v-switch
                v-model="editForm.has_philhealth"
                color="green"
                density="compact"
                hide-details
                inset
              />
            </div>
            <div v-if="editForm.has_philhealth" class="contrib-fields">
              <div class="computed-row">
                <span class="text-caption text-medium-emphasis"
                  >Computed from salary:</span
                >
                <span class="text-caption font-weight-medium"
                  >₱{{ formatCurrency(editEmployee.computed_philhealth) }}</span
                >
              </div>
              <v-text-field
                v-model.number="editForm.custom_philhealth"
                label="Custom PhilHealth Amount (per cutoff)"
                type="number"
                step="0.01"
                prefix="₱"
                variant="outlined"
                density="compact"
                hide-details="auto"
                hint="Leave blank to use computed value"
                persistent-hint
                clearable
                @click:clear="editForm.custom_philhealth = null"
              />
            </div>
            <div v-else class="contrib-disabled">
              PhilHealth deduction is disabled for this employee
            </div>
          </div>

          <!-- Pag-IBIG -->
          <div class="contrib-section">
            <div class="contrib-header">
              <div class="contrib-label">
                <v-icon size="20" color="orange" class="mr-2"
                  >mdi-home-city</v-icon
                >
                <span class="font-weight-bold">Pag-IBIG</span>
              </div>
              <v-switch
                v-model="editForm.has_pagibig"
                color="orange"
                density="compact"
                hide-details
                inset
              />
            </div>
            <div v-if="editForm.has_pagibig" class="contrib-fields">
              <div class="computed-row">
                <span class="text-caption text-medium-emphasis"
                  >Computed from salary:</span
                >
                <span class="text-caption font-weight-medium"
                  >₱{{ formatCurrency(editEmployee.computed_pagibig) }}</span
                >
              </div>
              <v-text-field
                v-model.number="editForm.custom_pagibig"
                label="Custom Pag-IBIG Amount (per cutoff)"
                type="number"
                step="0.01"
                prefix="₱"
                variant="outlined"
                density="compact"
                hide-details="auto"
                hint="Leave blank to use computed value"
                persistent-hint
                clearable
                @click:clear="editForm.custom_pagibig = null"
              />
            </div>
            <div v-else class="contrib-disabled">
              Pag-IBIG deduction is disabled for this employee
            </div>
          </div>

          <!-- Preview -->
          <div class="preview-section">
            <div class="preview-title">
              <v-icon size="16" class="mr-1">mdi-calculator</v-icon>
              Payroll Preview (per cutoff)
            </div>
            <div class="preview-grid">
              <div class="preview-item">
                <span>SSS</span>
                <span>₱{{ formatCurrency(previewSSS) }}</span>
              </div>
              <div class="preview-item">
                <span>PhilHealth</span>
                <span>₱{{ formatCurrency(previewPhilHealth) }}</span>
              </div>
              <div class="preview-item">
                <span>Pag-IBIG</span>
                <span>₱{{ formatCurrency(previewPagIBIG) }}</span>
              </div>
              <v-divider class="my-1" />
              <div class="preview-item preview-total">
                <span>Total Deduction</span>
                <span
                  >₱{{
                    formatCurrency(
                      previewSSS + previewPhilHealth + previewPagIBIG,
                    )
                  }}</span
                >
              </div>
            </div>
          </div>
        </v-card-text>

        <v-divider />

        <!-- Actions -->
        <v-card-actions class="edit-actions">
          <v-btn
            v-if="hasCustomValues"
            variant="text"
            color="error"
            size="small"
            prepend-icon="mdi-restore"
            @click="resetInDialog"
            :disabled="editSaving"
          >
            Reset All
          </v-btn>
          <v-spacer />
          <v-btn variant="text" @click="closeEditDialog" :disabled="editSaving">
            Cancel
          </v-btn>
          <v-btn
            color="primary"
            variant="elevated"
            @click="saveEditDialog"
            :loading="editSaving"
            prepend-icon="mdi-content-save"
          >
            Save Changes
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useToast } from "vue-toastification";
import api from "@/services/api";
import { devLog } from "@/utils/devLog";
import { formatCurrency } from "@/utils/formatters";
import { useConfirmDialog } from "@/composables/useConfirmDialog";

const toast = useToast();
const { confirm: confirmDialog } = useConfirmDialog();

// ─── State ────────────────────────────────────────────────────────────────
const loading = ref(false);
const employees = ref([]);
const summary = ref({});
const search = ref("");
const selectedProject = ref(null);
const statusFilter = ref(null);
const projects = ref([]);

// Edit dialog
const editDialog = ref(false);
const editEmployee = ref(null);
const editSaving = ref(false);
const editForm = ref({
  has_sss: true,
  has_philhealth: true,
  has_pagibig: true,
  custom_sss: null,
  custom_philhealth: null,
  custom_pagibig: null,
});

const statusOptions = [
  { title: "All", value: null },
  { title: "Has Custom Override", value: "custom" },
  { title: "All Computed", value: "computed" },
  { title: "Has Disabled", value: "disabled" },
];

const headers = [
  { title: "Employee", key: "full_name", sortable: true, minWidth: "220px" },
  {
    title: "Monthly Rate",
    key: "monthly_rate",
    align: "end",
    width: "130px",
    sortable: true,
  },
  {
    title: "SSS",
    key: "effective_sss",
    align: "center",
    width: "130px",
    sortable: true,
  },
  {
    title: "PhilHealth",
    key: "effective_philhealth",
    align: "center",
    width: "130px",
    sortable: true,
  },
  {
    title: "Pag-IBIG",
    key: "effective_pagibig",
    align: "center",
    width: "130px",
    sortable: true,
  },
  {
    title: "Total",
    key: "total",
    align: "end",
    width: "120px",
    sortable: true,
  },
  {
    title: "",
    key: "actions",
    sortable: false,
    width: "80px",
    align: "center",
  },
];

// ─── Computed ─────────────────────────────────────────────────────────────
const filteredEmployees = computed(() => {
  let result = employees.value;

  if (selectedProject.value) {
    result = result.filter((e) => e.project_id === selectedProject.value);
  }

  if (statusFilter.value === "custom") {
    result = result.filter(
      (e) =>
        e.custom_sss !== null ||
        e.custom_philhealth !== null ||
        e.custom_pagibig !== null,
    );
  } else if (statusFilter.value === "computed") {
    result = result.filter(
      (e) =>
        e.custom_sss === null &&
        e.custom_philhealth === null &&
        e.custom_pagibig === null,
    );
  } else if (statusFilter.value === "disabled") {
    result = result.filter(
      (e) => !e.has_sss || !e.has_philhealth || !e.has_pagibig,
    );
  }

  return result;
});

const previewSSS = computed(() => {
  if (!editForm.value.has_sss) return 0;
  return (
    parseFloat(
      editForm.value.custom_sss ?? editEmployee.value?.computed_sss ?? 0,
    ) || 0
  );
});

const previewPhilHealth = computed(() => {
  if (!editForm.value.has_philhealth) return 0;
  return (
    parseFloat(
      editForm.value.custom_philhealth ??
        editEmployee.value?.computed_philhealth ??
        0,
    ) || 0
  );
});

const previewPagIBIG = computed(() => {
  if (!editForm.value.has_pagibig) return 0;
  return (
    parseFloat(
      editForm.value.custom_pagibig ??
        editEmployee.value?.computed_pagibig ??
        0,
    ) || 0
  );
});

const hasCustomValues = computed(() => {
  return (
    editForm.value.custom_sss !== null ||
    editForm.value.custom_philhealth !== null ||
    editForm.value.custom_pagibig !== null
  );
});

// ─── Methods ──────────────────────────────────────────────────────────────
const getInitials = (name) => {
  if (!name) return "?";
  const parts = name.trim().split(/\s+/);
  return parts.length >= 2
    ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
    : parts[0][0].toUpperCase();
};

const getAvatarColor = (name) => {
  const colors = [
    "#ed985f",
    "#4CAF50",
    "#2196F3",
    "#9C27B0",
    "#FF5722",
    "#009688",
    "#795548",
    "#607D8B",
  ];
  let hash = 0;
  for (let i = 0; i < (name || "").length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash);
  }
  return colors[Math.abs(hash) % colors.length];
};

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
  } catch (error) {
    devLog.error("Failed to load employee contributions:", error);
    toast.error("Failed to load employee contributions");
  } finally {
    loading.value = false;
  }
};

const openEditDialog = (employee) => {
  editEmployee.value = { ...employee };
  editForm.value = {
    has_sss: employee.has_sss,
    has_philhealth: employee.has_philhealth,
    has_pagibig: employee.has_pagibig,
    custom_sss: employee.custom_sss,
    custom_philhealth: employee.custom_philhealth,
    custom_pagibig: employee.custom_pagibig,
  };
  editDialog.value = true;
};

const closeEditDialog = () => {
  editDialog.value = false;
  editEmployee.value = null;
};

const saveEditDialog = async () => {
  if (!editEmployee.value) return;
  editSaving.value = true;
  try {
    const payload = {
      has_sss: editForm.value.has_sss,
      has_philhealth: editForm.value.has_philhealth,
      has_pagibig: editForm.value.has_pagibig,
      custom_sss: editForm.value.custom_sss || null,
      custom_philhealth: editForm.value.custom_philhealth || null,
      custom_pagibig: editForm.value.custom_pagibig || null,
    };

    const response = await api.put(
      `/employee-contributions/${editEmployee.value.id}`,
      payload,
    );

    // Update local data
    const updated = response.data.data;
    const index = employees.value.findIndex(
      (e) => e.id === editEmployee.value.id,
    );
    if (index !== -1) {
      employees.value[index] = { ...employees.value[index], ...updated };
    }

    // Refresh summary
    const summaryRes = await api.get("/employee-contributions/summary");
    summary.value = summaryRes.data || {};

    toast.success(`Updated contributions for ${editEmployee.value.full_name}`);
    closeEditDialog();
  } catch (error) {
    devLog.error("Failed to update contributions:", error);
    toast.error("Failed to update contribution");
  } finally {
    editSaving.value = false;
  }
};

const resetInDialog = async () => {
  if (!editEmployee.value) return;
  if (
    !(await confirmDialog(
      `Reset all custom contributions for ${editEmployee.value.full_name} to computed defaults?`,
    ))
  )
    return;

  editForm.value.custom_sss = null;
  editForm.value.custom_philhealth = null;
  editForm.value.custom_pagibig = null;
};

const resetEmployee = async (employee) => {
  if (
    !(await confirmDialog(
      `Reset contributions for ${employee.full_name} to default?`,
    ))
  )
    return;

  try {
    const response = await api.post(
      `/employee-contributions/${employee.id}/reset`,
    );
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

    const summaryRes = await api.get("/employee-contributions/summary");
    summary.value = summaryRes.data || {};
    toast.success("Contributions reset to default");
  } catch (error) {
    devLog.error("Failed to reset:", error);
    toast.error("Failed to reset contributions");
  }
};

onMounted(() => {
  loadData();
});

defineExpose({ loadData });
</script>

<style scoped>
.contributions-tab {
  padding: 20px;
}

/* Summary Cards */
.summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 14px;
  margin-bottom: 20px;
}

.summary-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 18px;
  border-radius: 14px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  background: #fff;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  overflow: hidden;
}

.summary-card::before {
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

.summary-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08);
  border-color: rgba(237, 152, 95, 0.25);
}

.summary-card:hover::before {
  transform: scaleY(1);
}

.summary-icon-ring {
  width: 42px;
  height: 42px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.summary-employees .summary-icon-ring {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
}

.summary-employees .summary-icon-ring .v-icon {
  color: white !important;
}

.summary-sss .summary-icon-ring {
  background: linear-gradient(135deg, #2196f3 0%, #42a5f5 100%);
}

.summary-sss .summary-icon-ring .v-icon {
  color: white !important;
}

.summary-ph .summary-icon-ring {
  background: linear-gradient(135deg, #4caf50 0%, #66bb6a 100%);
}

.summary-ph .summary-icon-ring .v-icon {
  color: white !important;
}

.summary-pi .summary-icon-ring {
  background: linear-gradient(135deg, #ff9800 0%, #ffa726 100%);
}

.summary-pi .summary-icon-ring .v-icon {
  color: white !important;
}

.summary-total .summary-icon-ring {
  background: linear-gradient(135deg, #9c27b0 0%, #ba68c8 100%);
}

.summary-total .summary-icon-ring .v-icon {
  color: white !important;
}

.summary-info {
  display: flex;
  flex-direction: column;
  min-width: 0;
}

.summary-value {
  font-size: 18px;
  font-weight: 700;
  color: #1a1a1a;
  line-height: 1.2;
}

.summary-label {
  font-size: 11px;
  color: #94a3b8;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.summary-count {
  font-weight: 400;
  opacity: 0.7;
}

/* Toolbar */
.toolbar-row {
  display: flex;
  gap: 14px;
  align-items: center;
  margin-bottom: 20px;
  flex-wrap: wrap;
  padding: 16px 20px;
  background: white;
  border-radius: 14px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.toolbar-search {
  flex: 1;
  min-width: 200px;
  max-width: 340px;
}

.toolbar-filter {
  width: 180px;
  flex-shrink: 0;
}

.toolbar-filter-sm {
  width: 160px;
  flex-shrink: 0;
}

/* Table */
.table-card {
  border: 1px solid rgba(0, 31, 61, 0.08);
  border-radius: 14px;
  overflow: hidden;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
}

.contributions-table :deep(thead tr th) {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  color: #64748b;
  background: #f8fafc;
}

.contributions-table :deep(tbody tr) {
  cursor: pointer;
  transition: background 0.15s;
}

.contributions-table :deep(tbody tr:hover) {
  background: rgba(237, 152, 95, 0.04) !important;
}

.contributions-table :deep(tbody tr td) {
  border-bottom: 1px solid rgba(0, 0, 0, 0.04);
}

.employee-cell {
  display: flex;
  align-items: center;
  padding: 4px 0;
}

.amount-cell {
  font-weight: 600;
  font-size: 13px;
  color: #334155;
  font-variant-numeric: tabular-nums;
}

.contribution-cell {
  display: flex;
  justify-content: center;
}

.contribution-chip {
  min-width: 90px;
  justify-content: center;
  cursor: pointer;
}

.total-cell {
  font-size: 13px;
  color: #1e293b;
  font-variant-numeric: tabular-nums;
}

.actions-cell {
  display: flex;
  gap: 2px;
  justify-content: center;
}

/* Legend */
.legend-row {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 16px;
  margin-top: 14px;
  padding: 10px 14px;
  background: #f8fafc;
  border-radius: 10px;
  border: 1px solid rgba(0, 0, 0, 0.04);
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #64748b;
}

.legend-note {
  font-size: 12px;
  color: #94a3b8;
  display: flex;
  align-items: center;
  margin-left: auto;
}

/* Edit Dialog */
.edit-dialog-card {
  border-radius: 16px !important;
  overflow: hidden;
}

.edit-header {
  display: flex;
  align-items: center;
  padding: 16px 20px;
  background: #f8fafc;
}

.edit-body {
  padding: 16px 20px !important;
}

.contrib-section {
  margin-bottom: 16px;
  border: 1px solid rgba(0, 0, 0, 0.06);
  border-radius: 12px;
  overflow: hidden;
}

.contrib-section:last-of-type {
  margin-bottom: 16px;
}

.contrib-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 14px;
  background: #fafbfc;
  border-bottom: 1px solid rgba(0, 0, 0, 0.04);
}

.contrib-label {
  display: flex;
  align-items: center;
  font-size: 14px;
}

.contrib-fields {
  padding: 12px 14px;
}

.computed-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 6px 0;
  margin-bottom: 8px;
  border-bottom: 1px dashed rgba(0, 0, 0, 0.06);
}

.contrib-disabled {
  padding: 10px 14px;
  font-size: 13px;
  color: #94a3b8;
  font-style: italic;
}

/* Preview */
.preview-section {
  background: linear-gradient(135deg, #f1f5f9 0%, #f8fafc 100%);
  border-radius: 12px;
  padding: 14px;
  border: 1px solid rgba(0, 0, 0, 0.06);
}

.preview-title {
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.4px;
  color: #64748b;
  margin-bottom: 10px;
  display: flex;
  align-items: center;
}

.preview-grid {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.preview-item {
  display: flex;
  justify-content: space-between;
  font-size: 13px;
  color: #475569;
  padding: 3px 0;
}

.preview-item.preview-total {
  font-weight: 700;
  font-size: 14px;
  color: #1e293b;
  padding-top: 6px;
}

.edit-actions {
  padding: 12px 20px;
  background: #fafbfc;
}

/* Empty State */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 48px 24px;
}
</style>
