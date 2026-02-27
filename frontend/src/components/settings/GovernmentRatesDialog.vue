<template>
  <v-dialog
    :model-value="modelValue"
    @update:model-value="handleClose"
    max-width="1200"
    persistent
    scrollable
  >
    <v-card class="government-rates-dialog">
      <!-- Header -->
      <div class="dialog-header">
        <div class="header-content">
          <div class="icon-wrapper">
            <v-icon size="26" color="white">mdi-cash-multiple</v-icon>
          </div>
          <div>
            <h2 class="dialog-title">Manage Government Rates</h2>
            <p class="dialog-subtitle">
              Configure SSS, PhilHealth, Pag-IBIG, and withholding tax
              contribution tables
            </p>
          </div>
        </div>
        <v-btn
          icon
          variant="text"
          @click="handleClose"
          class="close-btn"
          :disabled="saving"
        >
          <v-icon color="white">mdi-close</v-icon>
        </v-btn>
      </div>

      <!-- Loading bar -->
      <v-progress-linear
        v-if="loading"
        indeterminate
        color="primary"
        height="3"
      />

      <!-- Tabs -->
      <div class="tabs-wrapper">
        <v-tabs
          v-model="activeTab"
          @update:model-value="cancelForm"
          color="primary"
          show-arrows
        >
          <v-tab value="employees">
            <v-icon size="18" class="mr-2">mdi-account-group</v-icon>
            Employee Contributions
          </v-tab>
          <v-tab value="sss">
            <v-icon size="18" class="mr-2">mdi-shield-account</v-icon>
            SSS
            <v-chip
              v-if="rates.sss.length"
              size="x-small"
              class="ml-2"
              color="primary"
              variant="tonal"
              >{{ rates.sss.length }}</v-chip
            >
          </v-tab>
          <v-tab value="philhealth">
            <v-icon size="18" class="mr-2">mdi-hospital-box</v-icon>
            PhilHealth
            <v-chip
              v-if="rates.philhealth.length"
              size="x-small"
              class="ml-2"
              color="success"
              variant="tonal"
              >{{ rates.philhealth.length }}</v-chip
            >
          </v-tab>
          <v-tab value="pagibig">
            <v-icon size="18" class="mr-2">mdi-home-city</v-icon>
            Pag-IBIG
            <v-chip
              v-if="rates.pagibig.length"
              size="x-small"
              class="ml-2"
              color="orange"
              variant="tonal"
              >{{ rates.pagibig.length }}</v-chip
            >
          </v-tab>
          <v-tab value="tax">
            <v-icon size="18" class="mr-2">mdi-file-document</v-icon>
            Tax
            <v-chip
              v-if="rates.tax.length"
              size="x-small"
              class="ml-2"
              color="purple"
              variant="tonal"
              >{{ rates.tax.length }}</v-chip
            >
          </v-tab>
        </v-tabs>
      </div>

      <v-card-text class="dialog-content pa-0">
        <v-window v-model="activeTab">
          <!-- Employee Contributions Tab -->
          <v-window-item value="employees">
            <employee-contributions-tab ref="employeeContributionsRef" />
          </v-window-item>

          <!-- Rate Tabs -->
          <v-window-item
            v-for="rateTab in rateTabs"
            :key="rateTab.value"
            :value="rateTab.value"
          >
            <div class="rate-tab-content">
              <!-- Toolbar -->
              <div class="rate-toolbar">
                <div class="toolbar-left">
                  <span class="rate-count"
                    >{{ currentRates(rateTab.value).length }} rate{{
                      currentRates(rateTab.value).length !== 1 ? "s" : ""
                    }}
                    configured</span
                  >
                </div>
                <div class="toolbar-right">
                  <v-btn
                    v-if="!showForm || editingForTab !== rateTab.value"
                    color="primary"
                    variant="tonal"
                    size="small"
                    prepend-icon="mdi-plus"
                    @click="startAdd(rateTab.value)"
                  >
                    Add Rate
                  </v-btn>
                  <v-btn
                    v-if="currentRates(rateTab.value).length > 0"
                    color="error"
                    variant="text"
                    size="small"
                    prepend-icon="mdi-delete-sweep"
                    class="ml-2"
                    @click="clearTabRates(rateTab.value)"
                    :disabled="loading"
                  >
                    Clear All
                  </v-btn>
                </div>
              </div>

              <!-- Inline Add/Edit Form -->
              <v-expand-transition>
                <div
                  v-if="showForm && editingForTab === rateTab.value"
                  class="form-panel"
                >
                  <div class="form-panel-header">
                    <div class="form-panel-title">
                      <v-icon size="18" class="mr-2" :color="rateTab.color">{{
                        editingRate ? "mdi-pencil" : "mdi-plus-circle"
                      }}</v-icon>
                      {{ editingRate ? "Edit" : "New" }}
                      {{ rateTab.label }} Rate
                    </div>
                    <v-btn
                      icon="mdi-close"
                      size="small"
                      variant="text"
                      @click="cancelForm"
                    />
                  </div>

                  <v-form ref="formRef" v-model="formValid" class="pa-4">
                    <v-row dense>
                      <!-- Rate Name -->
                      <v-col cols="12" md="6">
                        <v-text-field
                          v-model="form.name"
                          label="Rate Name *"
                          :rules="[rules.required]"
                          variant="outlined"
                          density="comfortable"
                          placeholder="e.g., SSS Bracket 1"
                          hide-details="auto"
                        />
                      </v-col>

                      <!-- Effective Date -->
                      <v-col cols="12" md="3">
                        <v-text-field
                          v-model="form.effective_date"
                          label="Effective Date *"
                          type="date"
                          :rules="[rules.required]"
                          variant="outlined"
                          density="comfortable"
                          hide-details="auto"
                        />
                      </v-col>

                      <!-- End Date -->
                      <v-col cols="12" md="3">
                        <v-text-field
                          v-model="form.end_date"
                          label="End Date"
                          type="date"
                          variant="outlined"
                          density="comfortable"
                          hint="Leave empty if currently active"
                          hide-details="auto"
                        />
                      </v-col>

                      <!-- Salary Range -->
                      <v-col cols="12" md="3">
                        <v-text-field
                          v-model.number="form.min_salary"
                          label="Min Salary *"
                          type="number"
                          prefix="₱"
                          :rules="[rules.requiredNumeric]"
                          variant="outlined"
                          density="comfortable"
                          hide-details="auto"
                        />
                      </v-col>
                      <v-col cols="12" md="3">
                        <v-text-field
                          v-model.number="form.max_salary"
                          label="Max Salary"
                          type="number"
                          prefix="₱"
                          variant="outlined"
                          density="comfortable"
                          hint="Leave empty = no limit"
                          hide-details="auto"
                        />
                      </v-col>

                      <!-- Employee Contribution -->
                      <v-col cols="12" md="3">
                        <v-text-field
                          v-model.number="form.employee_rate"
                          label="Employee Rate (%)"
                          type="number"
                          suffix="%"
                          :rules="[rules.rateRange]"
                          variant="outlined"
                          density="comfortable"
                          :disabled="!!form.employee_fixed"
                          hide-details="auto"
                        />
                      </v-col>
                      <v-col cols="12" md="3">
                        <v-text-field
                          v-model.number="form.employee_fixed"
                          label="Employee Fixed (₱)"
                          type="number"
                          prefix="₱"
                          variant="outlined"
                          density="comfortable"
                          :disabled="!!form.employee_rate"
                          hide-details="auto"
                        />
                      </v-col>

                      <!-- Employer Contribution -->
                      <v-col cols="12" md="3">
                        <v-text-field
                          v-model.number="form.employer_rate"
                          label="Employer Rate (%)"
                          type="number"
                          suffix="%"
                          :rules="[rules.rateRange]"
                          variant="outlined"
                          density="comfortable"
                          :disabled="!!form.employer_fixed"
                          hide-details="auto"
                        />
                      </v-col>
                      <v-col cols="12" md="3">
                        <v-text-field
                          v-model.number="form.employer_fixed"
                          label="Employer Fixed (₱)"
                          type="number"
                          prefix="₱"
                          variant="outlined"
                          density="comfortable"
                          :disabled="!!form.employer_rate"
                          hide-details="auto"
                        />
                      </v-col>

                      <!-- Total Contribution -->
                      <v-col cols="12" md="3">
                        <v-text-field
                          v-model.number="form.total_contribution"
                          label="Total Contribution (₱)"
                          type="number"
                          prefix="₱"
                          variant="outlined"
                          density="comfortable"
                          hint="Overrides calculated total"
                          hide-details="auto"
                        />
                      </v-col>

                      <!-- Notes -->
                      <v-col cols="12" md="6">
                        <v-text-field
                          v-model="form.notes"
                          label="Notes"
                          variant="outlined"
                          density="comfortable"
                          hide-details="auto"
                        />
                      </v-col>

                      <!-- Active toggle -->
                      <v-col cols="12" md="3" class="d-flex align-center">
                        <v-switch
                          v-model="form.is_active"
                          label="Active"
                          color="primary"
                          density="comfortable"
                          hide-details
                        />
                      </v-col>
                    </v-row>

                    <!-- Form Actions -->
                    <div class="form-actions mt-3">
                      <v-btn
                        variant="text"
                        @click="cancelForm"
                        :disabled="saving"
                        >Cancel</v-btn
                      >
                      <v-btn
                        color="primary"
                        variant="elevated"
                        :loading="saving"
                        @click="saveRate(rateTab.value)"
                        prepend-icon="mdi-content-save"
                      >
                        {{ editingRate ? "Update Rate" : "Save Rate" }}
                      </v-btn>
                    </div>
                  </v-form>
                </div>
              </v-expand-transition>

              <!-- Rates Table -->
              <div
                v-if="currentRates(rateTab.value).length > 0"
                class="rates-table-wrapper"
              >
                <v-table density="compact" class="rates-table">
                  <thead>
                    <tr>
                      <th>Name</th>
                      <th class="text-right">Min Salary</th>
                      <th class="text-right">Max Salary</th>
                      <th class="text-right">Employee</th>
                      <th class="text-right">Employer</th>
                      <th class="text-right">Total</th>
                      <th>Effective</th>
                      <th>Until</th>
                      <th class="text-center">Status</th>
                      <th class="text-center">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr
                      v-for="rate in currentRates(rateTab.value)"
                      :key="rate.id"
                      :class="{ 'row-inactive': !rate.is_active }"
                    >
                      <td class="font-weight-medium">{{ rate.name }}</td>
                      <td class="text-right">
                        ₱{{ formatCurrency(rate.min_salary) }}
                      </td>
                      <td class="text-right">
                        <span v-if="rate.max_salary"
                          >₱{{ formatCurrency(rate.max_salary) }}</span
                        >
                        <span v-else class="text-medium-emphasis text-caption"
                          >No limit</span
                        >
                      </td>
                      <td class="text-right">
                        <span v-if="rate.employee_rate" class="text-blue"
                          >{{ rate.employee_rate }}%</span
                        >
                        <span v-else-if="rate.employee_fixed" class="text-blue"
                          >₱{{ formatCurrency(rate.employee_fixed) }}</span
                        >
                        <span v-else class="text-medium-emphasis">—</span>
                      </td>
                      <td class="text-right">
                        <span v-if="rate.employer_rate" class="text-green"
                          >{{ rate.employer_rate }}%</span
                        >
                        <span v-else-if="rate.employer_fixed" class="text-green"
                          >₱{{ formatCurrency(rate.employer_fixed) }}</span
                        >
                        <span v-else class="text-medium-emphasis">—</span>
                      </td>
                      <td class="text-right">
                        <span
                          v-if="rate.total_contribution"
                          class="font-weight-medium"
                          >₱{{ formatCurrency(rate.total_contribution) }}</span
                        >
                        <span v-else class="text-caption text-medium-emphasis"
                          >Computed</span
                        >
                      </td>
                      <td class="text-caption">
                        {{ formatDate(rate.effective_date) }}
                      </td>
                      <td class="text-caption">
                        <span v-if="rate.end_date">{{
                          formatDate(rate.end_date)
                        }}</span>
                        <span v-else class="text-medium-emphasis">Current</span>
                      </td>
                      <td class="text-center">
                        <v-chip
                          :color="rate.is_active ? 'success' : 'default'"
                          size="x-small"
                          variant="tonal"
                          style="cursor: pointer"
                          @click="toggleActive(rate)"
                        >
                          {{ rate.is_active ? "Active" : "Inactive" }}
                        </v-chip>
                      </td>
                      <td class="text-center">
                        <v-btn
                          icon="mdi-pencil"
                          size="x-small"
                          variant="text"
                          color="primary"
                          @click="startEdit(rate)"
                          title="Edit"
                        />
                        <v-btn
                          icon="mdi-delete"
                          size="x-small"
                          variant="text"
                          color="error"
                          @click="deleteRate(rate)"
                          title="Delete"
                        />
                      </td>
                    </tr>
                  </tbody>
                </v-table>
              </div>

              <!-- Empty state -->
              <div
                v-else-if="!showForm || editingForTab !== rateTab.value"
                class="empty-state"
              >
                <v-icon size="56" color="grey-lighten-2"
                  >mdi-database-off</v-icon
                >
                <p class="text-body-1 mt-3 font-weight-medium">
                  No {{ rateTab.label }} rates configured
                </p>
                <p class="text-caption text-medium-emphasis">
                  Click "Add Rate" to create the contribution brackets
                </p>
              </div>
            </div>
          </v-window-item>
        </v-window>
      </v-card-text>

      <v-divider />
      <v-card-actions class="dialog-foot">
        <span class="text-caption text-medium-emphasis"
          >Changes are saved immediately per action</span
        >
        <v-spacer />
        <v-btn variant="tonal" @click="handleClose" :disabled="saving">
          Close
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, watch, computed } from "vue";
import { useToast } from "vue-toastification";
import api from "@/services/api";
import EmployeeContributionsTab from "./EmployeeContributionsTab.vue";
import { devLog } from "@/utils/devLog";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import { formatCurrency, formatDate } from "@/utils/formatters";

const props = defineProps({
  modelValue: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);
const toast = useToast();
const { confirm: confirmDialog } = useConfirmDialog();

// ─── State ────────────────────────────────────────────────────────────────
const activeTab = ref("employees");
const loading = ref(false);
const saving = ref(false);
const employeeContributionsRef = ref(null);
const formRef = ref(null);
const formValid = ref(false);

// Inline form state
const showForm = ref(false);
const editingForTab = ref(null); // which tab's form is open
const editingRate = ref(null); // null = adding, object = editing

const form = ref(defaultForm());

function defaultForm() {
  return {
    name: "",
    min_salary: null,
    max_salary: null,
    employee_rate: null,
    employee_fixed: null,
    employer_rate: null,
    employer_fixed: null,
    total_contribution: null,
    effective_date: new Date().toISOString().split("T")[0],
    end_date: null,
    is_active: true,
    notes: "",
  };
}

const rates = ref({ sss: [], philhealth: [], pagibig: [], tax: [] });

const rateTabs = [
  { value: "sss", label: "SSS", color: "blue" },
  { value: "philhealth", label: "PhilHealth", color: "green" },
  { value: "pagibig", label: "Pag-IBIG", color: "orange" },
  { value: "tax", label: "Tax", color: "purple" },
];

const rules = {
  required: (v) => (v !== null && v !== undefined && v !== "") || "Required",
  requiredNumeric: (v) =>
    (v !== null && v !== undefined && v !== "") || "Required",
  rateRange: (v) => !v || (v >= 0 && v <= 100) || "Must be 0–100",
};

// ─── Computed ─────────────────────────────────────────────────────────────
const currentRates = (type) => rates.value[type] ?? [];

// ─── Watchers ─────────────────────────────────────────────────────────────
watch(
  () => props.modelValue,
  (newVal) => {
    if (newVal) {
      loadRates();
      if (employeeContributionsRef.value) {
        employeeContributionsRef.value.loadData();
      }
    } else {
      activeTab.value = "employees";
      cancelForm();
    }
  },
);

// ─── Methods ──────────────────────────────────────────────────────────────
const handleClose = () => emit("update:modelValue", false);

const loadRates = async () => {
  loading.value = true;
  try {
    const response = await api.get("/government-rates");
    const allRates = response.data.data || response.data || [];
    rates.value = {
      sss: allRates.filter((r) => r.type === "sss"),
      philhealth: allRates.filter((r) => r.type === "philhealth"),
      pagibig: allRates.filter((r) => r.type === "pagibig"),
      tax: allRates.filter((r) => r.type === "tax"),
    };
  } catch (error) {
    devLog.error("Failed to load rates:", error);
    toast.error("Failed to load government rates");
  } finally {
    loading.value = false;
  }
};

const startAdd = (tabType) => {
  editingRate.value = null;
  editingForTab.value = tabType;
  form.value = defaultForm();
  showForm.value = true;
};

const startEdit = (rate) => {
  editingRate.value = rate;
  editingForTab.value = rate.type;
  activeTab.value = rate.type;
  form.value = {
    ...rate,
    effective_date:
      rate.effective_date?.split("T")[0] ??
      new Date().toISOString().split("T")[0],
    end_date: rate.end_date?.split("T")[0] ?? null,
  };
  showForm.value = true;
};

const cancelForm = () => {
  showForm.value = false;
  editingForTab.value = null;
  editingRate.value = null;
  form.value = defaultForm();
  const ref = Array.isArray(formRef.value) ? formRef.value[0] : formRef.value;
  ref?.resetValidation();
};

const saveRate = async (tabType) => {
  const ref = Array.isArray(formRef.value) ? formRef.value[0] : formRef.value;
  const { valid } = await ref.validate();
  if (!valid) return;

  saving.value = true;
  try {
    const payload = { ...form.value, type: tabType };

    if (editingRate.value?.id) {
      await api.put(`/government-rates/${editingRate.value.id}`, payload);
      toast.success("Rate updated successfully");
    } else {
      await api.post("/government-rates", payload);
      toast.success("Rate added successfully");
    }

    cancelForm();
    await loadRates();
  } catch (error) {
    devLog.error("Failed to save rate:", error);
    const message = error.response?.data?.message || "Failed to save rate";
    const errors = error.response?.data?.errors;
    if (errors) {
      toast.error(`${message}: ${Object.values(errors).flat().join(", ")}`);
    } else {
      toast.error(message);
    }
  } finally {
    saving.value = false;
  }
};

const deleteRate = async (rate) => {
  if (!(await confirmDialog("Delete this rate? This cannot be undone.")))
    return;
  try {
    await api.delete(`/government-rates/${rate.id}`);
    toast.success("Rate deleted");
    if (editingRate.value?.id === rate.id) cancelForm();
    await loadRates();
  } catch {
    toast.error("Failed to delete rate");
  }
};

const toggleActive = async (rate) => {
  try {
    await api.put(`/government-rates/${rate.id}`, {
      is_active: !rate.is_active,
    });
    toast.success(`Rate ${rate.is_active ? "deactivated" : "activated"}`);
    await loadRates();
  } catch {
    toast.error("Failed to update rate status");
  }
};

const clearTabRates = async (tabType) => {
  const tabRates = currentRates(tabType);
  if (tabRates.length === 0) {
    toast.info("No rates to delete");
    return;
  }
  if (
    !(await confirmDialog(
      `Delete all ${tabRates.length} ${tabType.toUpperCase()} rates? This cannot be undone.`,
    ))
  )
    return;
  try {
    await api.post("/government-rates/bulk-delete", {
      ids: tabRates.map((r) => r.id),
    });
    toast.success(`Deleted all ${tabRates.length} rates`);
    cancelForm();
    await loadRates();
  } catch {
    toast.error("Failed to clear rates");
  }
};
</script>

<style scoped lang="scss">
.government-rates-dialog {
  border-radius: 16px;
  overflow: hidden;
}

.dialog-header {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  padding: 20px 24px;
  display: flex;
  align-items: center;
  gap: 16px;
  position: relative;
}

.header-content {
  display: flex;
  align-items: center;
  gap: 16px;
  flex: 1;
}

.icon-wrapper {
  width: 48px;
  height: 48px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.dialog-title {
  font-size: 20px;
  font-weight: 700;
  margin: 0;
  color: white;
}

.dialog-subtitle {
  font-size: 13px;
  margin: 2px 0 0;
  opacity: 0.88;
  color: white;
}

.close-btn {
  position: absolute;
  top: 12px;
  right: 12px;
}

.tabs-wrapper {
  background: white;
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);

  :deep(.v-tab) {
    text-transform: none;
    font-weight: 600;
    font-size: 13px;
    letter-spacing: 0;
  }
}

.dialog-content {
  max-height: 62vh;
  overflow-y: auto;
}

// Rate tab layout
.rate-tab-content {
  padding: 0;
}

.rate-toolbar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 20px;
  background: rgba(0, 0, 0, 0.01);
  border-bottom: 1px solid rgba(0, 0, 0, 0.06);
}

.rate-count {
  font-size: 13px;
  color: #64748b;
  font-weight: 500;
}

// Inline form panel
.form-panel {
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
  background: #f8fafc;

  .form-panel-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 10px 16px 0;
  }

  .form-panel-title {
    font-size: 14px;
    font-weight: 600;
    color: #334155;
    display: flex;
    align-items: center;
  }

  .form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding-top: 4px;
  }
}

// Rates table
.rates-table-wrapper {
  overflow-x: auto;
}

.rates-table {
  :deep(thead tr th) {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #64748b;
    background: #f8fafc;
    white-space: nowrap;
    padding: 8px 12px;
  }

  :deep(tbody tr td) {
    font-size: 13px;
    padding: 8px 12px;
    border-bottom: 1px solid rgba(0, 0, 0, 0.04);
  }

  :deep(tbody tr.row-inactive td) {
    opacity: 0.45;
  }

  :deep(tbody tr:hover td) {
    background: rgba(237, 152, 95, 0.04);
  }
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 48px 24px;
  color: #94a3b8;
}

.dialog-foot {
  padding: 12px 20px;
  background: #f8fafc;
}
</style>
