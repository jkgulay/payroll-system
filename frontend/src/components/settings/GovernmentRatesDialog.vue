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
      <v-card-title class="dialog-header">
        <div class="header-content">
          <div class="icon-wrapper">
            <v-icon size="28" color="white">mdi-cash-multiple</v-icon>
          </div>
          <div>
            <h2 class="dialog-title">Manage Government Rates</h2>
            <p class="dialog-subtitle">
              Configure SSS, PhilHealth, Pag-IBIG, and withholding tax tables
            </p>
          </div>
        </div>
        <v-btn icon variant="text" @click="handleClose" class="close-btn">
          <v-icon>mdi-close</v-icon>
        </v-btn>
      </v-card-title>

      <v-divider />

      <!-- Clear All Button -->
      <div v-if="activeTab !== 'employees'" class="action-bar">
        <v-btn
          color="error"
          variant="outlined"
          @click="clearAllRates"
          size="small"
          :disabled="loading"
        >
          <v-icon class="mr-1">mdi-delete-sweep</v-icon>
          Clear All
        </v-btn>
      </div>

      <v-card-text class="dialog-content">
        <v-progress-linear
          v-if="loading"
          indeterminate
          color="primary"
        ></v-progress-linear>
        <v-tabs v-model="activeTab" class="config-tabs" show-arrows>
          <v-tab value="employees">
            <v-icon class="mr-2">mdi-account-group</v-icon>
            Employee Contributions
          </v-tab>
          <v-tab value="sss">
            <v-icon class="mr-2">mdi-shield-account</v-icon>
            SSS
          </v-tab>
          <v-tab value="philhealth">
            <v-icon class="mr-2">mdi-hospital-box</v-icon>
            PhilHealth
          </v-tab>
          <v-tab value="pagibig">
            <v-icon class="mr-2">mdi-home-city</v-icon>
            Pag-IBIG
          </v-tab>
          <v-tab value="tax">
            <v-icon class="mr-2">mdi-file-document</v-icon>
            Withholding Tax
          </v-tab>
        </v-tabs>

        <v-window v-model="activeTab" class="config-window">
          <!-- Employee Contributions Tab -->
          <v-window-item value="employees">
            <employee-contributions-tab ref="employeeContributionsRef" />
          </v-window-item>

          <!-- SSS Tab -->
          <v-window-item value="sss">
            <rate-table-panel
              :rates="rates.sss"
              type="sss"
              title="SSS Contribution Rates"
              @add="openAddDialog"
              @edit="openEditDialog"
              @delete="deleteRate"
              @toggle-active="toggleActive"
            />
          </v-window-item>

          <!-- PhilHealth Tab -->
          <v-window-item value="philhealth">
            <rate-table-panel
              :rates="rates.philhealth"
              type="philhealth"
              title="PhilHealth Contribution Rates"
              @add="openAddDialog"
              @edit="openEditDialog"
              @delete="deleteRate"
              @toggle-active="toggleActive"
            />
          </v-window-item>

          <!-- Pag-IBIG Tab -->
          <v-window-item value="pagibig">
            <rate-table-panel
              :rates="rates.pagibig"
              type="pagibig"
              title="Pag-IBIG Contribution Rates"
              @add="openAddDialog"
              @edit="openEditDialog"
              @delete="deleteRate"
              @toggle-active="toggleActive"
            />
          </v-window-item>

          <!-- Tax Tab -->
          <v-window-item value="tax">
            <rate-table-panel
              :rates="rates.tax"
              type="tax"
              title="Withholding Tax Rates"
              @add="openAddDialog"
              @edit="openEditDialog"
              @delete="deleteRate"
              @toggle-active="toggleActive"
            />
          </v-window-item>
        </v-window>
      </v-card-text>

      <v-divider></v-divider>

      <v-card-actions class="dialog-actions">
        <v-spacer></v-spacer>
        <v-btn variant="text" @click="handleClose" :disabled="loading">
          Close
        </v-btn>
      </v-card-actions>
    </v-card>

    <!-- Add/Edit Rate Dialog -->
    <rate-form-dialog
      v-model="formDialog"
      :rate="editingRate"
      :type="currentType"
      @save="saveRate"
      @cancel="formDialog = false"
    />
  </v-dialog>
</template>

<script setup>
import { ref, watch } from "vue";
import { useToast } from "vue-toastification";
import api from "@/services/api";
import RateTablePanel from "./RateTablePanel.vue";
import RateFormDialog from "./RateFormDialog.vue";
import EmployeeContributionsTab from "./EmployeeContributionsTab.vue";

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["update:modelValue"]);
const toast = useToast();

const activeTab = ref("employees");
const formDialog = ref(false);
const editingRate = ref(null);
const currentType = ref("sss");
const loading = ref(false);
const employeeContributionsRef = ref(null);

const rates = ref({
  sss: [],
  philhealth: [],
  pagibig: [],
  tax: [],
});

// Watch for dialog opening to load rates
watch(
  () => props.modelValue,
  (newVal) => {
    if (newVal) {
      loadRates();
      // Refresh employee contributions when dialog opens
      if (employeeContributionsRef.value) {
        employeeContributionsRef.value.loadData();
      }
    } else {
      // Reset state when closing
      activeTab.value = "employees";
    }
  },
);

const handleClose = () => {
  emit("update:modelValue", false);
};

const loadRates = async () => {
  loading.value = true;
  try {
    const response = await api.get("/government-rates");
    const allRates = response.data.data || response.data || [];

    // Group rates by type
    rates.value = {
      sss: allRates.filter((r) => r.type === "sss"),
      philhealth: allRates.filter((r) => r.type === "philhealth"),
      pagibig: allRates.filter((r) => r.type === "pagibig"),
      tax: allRates.filter((r) => r.type === "tax"),
    };
  } catch (error) {
    console.error("Failed to load rates:", error);
    toast.error("Failed to load government rates");
  } finally {
    loading.value = false;
  }
};

const openAddDialog = (type) => {
  currentType.value = type;
  editingRate.value = null;
  formDialog.value = true;
};

const openEditDialog = (rate) => {
  currentType.value = rate.type;
  editingRate.value = { ...rate };
  formDialog.value = true;
};

const saveRate = async (rateData) => {
  try {
    if (rateData.id) {
      await api.put(`/government-rates/${rateData.id}`, rateData);
      toast.success("Rate updated successfully");
    } else {
      await api.post("/government-rates", rateData);
      toast.success("Rate added successfully");
    }

    formDialog.value = false;
    await loadRates();
  } catch (error) {
    console.error("Failed to save rate:", error);
    const message = error.response?.data?.message || "Failed to save rate";
    const errors = error.response?.data?.errors;
    if (errors) {
      const errorMessages = Object.values(errors).flat().join(", ");
      toast.error(`${message}: ${errorMessages}`);
    } else {
      toast.error(message);
    }
  }
};

const deleteRate = async (rate) => {
  if (
    !confirm(
      `Are you sure you want to delete this rate? This action cannot be undone.`,
    )
  ) {
    return;
  }

  try {
    await api.delete(`/government-rates/${rate.id}`);
    toast.success("Rate deleted successfully");
    loadRates();
  } catch (error) {
    console.error("Failed to delete rate:", error);
    toast.error("Failed to delete rate");
  }
};

const toggleActive = async (rate) => {
  try {
    await api.put(`/government-rates/${rate.id}`, {
      ...rate,
      is_active: !rate.is_active,
    });
    toast.success(`Rate ${rate.is_active ? "deactivated" : "activated"}`);
    await loadRates();
  } catch (error) {
    console.error("Failed to toggle rate:", error);
    toast.error("Failed to update rate status");
  }
};

const clearAllRates = async () => {
  const totalRates = Object.values(rates.value).flat().length;

  if (totalRates === 0) {
    toast.info("No rates to delete");
    return;
  }

  if (
    !confirm(
      `Are you sure you want to delete ALL ${totalRates} government rates? This action cannot be undone.`,
    )
  ) {
    return;
  }

  try {
    const allRates = Object.values(rates.value).flat();
    const allIds = allRates.map((r) => r.id);

    await api.post("/government-rates/bulk-delete", { ids: allIds });
    toast.success(`Successfully deleted ${allIds.length} rate(s)`);
    await loadRates();
  } catch (error) {
    console.error("Failed to clear rates:", error);
    const message = error.response?.data?.message || "Failed to clear rates";
    toast.error(message);
  }
};
</script>

<style scoped lang="scss">
.government-rates-dialog {
  border-radius: 16px;
}

.dialog-header {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: white;
  padding: 24px 28px;
  position: relative;
}

.header-content {
  display: flex;
  align-items: center;
  gap: 16px;
  width: 100%;
}

.icon-wrapper {
  width: 56px;
  height: 56px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.dialog-title {
  font-size: 24px;
  font-weight: 700;
  margin: 0;
  color: white;
}

.dialog-subtitle {
  font-size: 14px;
  margin: 4px 0 0 0;
  opacity: 0.9;
}

.close-btn {
  position: absolute;
  top: 16px;
  right: 16px;

  :deep(.v-icon) {
    color: white !important;
  }
}

.action-bar {
  padding: 12px 24px;
  background: rgba(0, 0, 0, 0.02);
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
  display: flex;
  justify-content: flex-end;
}

.dialog-content {
  padding: 0;
  max-height: 600px;
}

.config-tabs {
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
  padding: 0 24px;

  :deep(.v-tab) {
    text-transform: none;
    font-weight: 600;
    letter-spacing: 0;
  }
}

.dialog-actions {
  padding: 20px 28px;
  background: rgba(0, 0, 0, 0.02);
}
</style>
