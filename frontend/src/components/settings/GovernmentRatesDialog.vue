<template>
  <v-dialog
    :model-value="modelValue"
    @update:model-value="handleClose"
    max-width="1200"
    persistent
    scrollable
  >
    <v-card>
      <v-card-title class="text-h5 d-flex align-center">
        <v-icon class="mr-2">mdi-cash-multiple</v-icon>
        Manage Government Rates
        <v-spacer></v-spacer>
        <v-btn
          color="error"
          variant="outlined"
          @click="clearAllRates"
          class="mr-2"
          size="small"
          :disabled="loading"
        >
          <v-icon class="mr-1">mdi-delete-sweep</v-icon>
          Clear All
        </v-btn>
        <v-btn icon @click="handleClose" variant="text">
          <v-icon>mdi-close</v-icon>
        </v-btn>
      </v-card-title>

      <v-divider></v-divider>

      <v-card-text class="pa-0" style="height: 600px">
        <v-progress-linear
          v-if="loading"
          indeterminate
          color="primary"
        ></v-progress-linear>
        <v-tabs v-model="activeTab" bg-color="primary" show-arrows>
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

        <v-tabs-window v-model="activeTab">
          <!-- SSS Tab -->
          <v-tabs-window-item value="sss">
            <rate-table-panel
              :rates="rates.sss"
              type="sss"
              title="SSS Contribution Rates"
              @add="openAddDialog"
              @edit="openEditDialog"
              @delete="deleteRate"
              @toggle-active="toggleActive"
            />
          </v-tabs-window-item>

          <!-- PhilHealth Tab -->
          <v-tabs-window-item value="philhealth">
            <rate-table-panel
              :rates="rates.philhealth"
              type="philhealth"
              title="PhilHealth Contribution Rates"
              @add="openAddDialog"
              @edit="openEditDialog"
              @delete="deleteRate"
              @toggle-active="toggleActive"
            />
          </v-tabs-window-item>

          <!-- Pag-IBIG Tab -->
          <v-tabs-window-item value="pagibig">
            <rate-table-panel
              :rates="rates.pagibig"
              type="pagibig"
              title="Pag-IBIG Contribution Rates"
              @add="openAddDialog"
              @edit="openEditDialog"
              @delete="deleteRate"
              @toggle-active="toggleActive"
            />
          </v-tabs-window-item>

          <!-- Tax Tab -->
          <v-tabs-window-item value="tax">
            <rate-table-panel
              :rates="rates.tax"
              type="tax"
              title="Withholding Tax Rates"
              @add="openAddDialog"
              @edit="openEditDialog"
              @delete="deleteRate"
              @toggle-active="toggleActive"
            />
          </v-tabs-window-item>
        </v-tabs-window>
      </v-card-text>

      <v-divider></v-divider>

      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn
          color="primary"
          variant="text"
          @click="handleClose"
          :disabled="loading"
        >
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

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
});

const emit = defineEmits(["update:modelValue"]);
const toast = useToast();

const activeTab = ref("sss");
const formDialog = ref(false);
const editingRate = ref(null);
const currentType = ref("sss");
const loading = ref(false);

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
    } else {
      // Reset state when closing
      activeTab.value = "sss";
    }
  }
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
      `Are you sure you want to delete this rate? This action cannot be undone.`
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
      `Are you sure you want to delete ALL ${totalRates} government rates? This action cannot be undone.`
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

<style scoped>
.v-tabs {
  position: sticky;
  top: 0;
  z-index: 1;
}
</style>
