<template>
  <div>
    <v-row class="mb-4">
      <v-col cols="12" md="6">
        <h1 class="text-h4 font-weight-bold">Position Rates Management</h1>
        <p class="text-body-2 text-medium-emphasis">
          Manage pay rates for different positions
        </p>
      </v-col>
      <v-col cols="12" md="6" class="text-right">
        <v-btn color="primary" prepend-icon="mdi-plus" @click="openAddDialog">
          Add Position Rate
        </v-btn>
      </v-col>
    </v-row>

    <v-card>
      <v-card-text>
        <v-row class="mb-4" align="center">
          <v-col cols="12" md="4">
            <v-text-field
              v-model="search"
              prepend-inner-icon="mdi-magnify"
              label="Search positions..."
              clearable
              variant="outlined"
              density="comfortable"
              hide-details
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filterCategory"
              :items="categoryOptions"
              label="Filter by Category"
              clearable
              variant="outlined"
              density="comfortable"
              hide-details
            ></v-select>
          </v-col>
          <v-col cols="12" md="2">
            <v-switch
              v-model="showActiveOnly"
              label="Active Only"
              color="success"
              hide-details
            ></v-switch>
          </v-col>
          <v-col cols="auto">
            <v-btn
              color="primary"
              variant="tonal"
              icon="mdi-refresh"
              @click="loadPositionRates"
              :loading="loading"
              title="Refresh"
            ></v-btn>
          </v-col>
        </v-row>

        <v-data-table
          :headers="headers"
          :items="filteredPositions"
          :loading="loading"
          :search="search"
          class="elevation-1"
        >
          <template v-slot:item.position_name="{ item }">
            <div class="font-weight-medium">{{ item.position_name }}</div>
            <div v-if="item.code" class="text-caption text-medium-emphasis">
              Code: {{ item.code }}
            </div>
          </template>

          <template v-slot:item.daily_rate="{ item }">
            <div class="text-right font-weight-bold">
              {{ formatCurrency(item.daily_rate) }}
            </div>
          </template>

          <template v-slot:item.category="{ item }">
            <v-chip
              v-if="item.category"
              size="small"
              :color="getCategoryColor(item.category)"
            >
              {{ formatCategory(item.category) }}
            </v-chip>
            <span v-else class="text-medium-emphasis">—</span>
          </template>

          <template v-slot:item.employee_count="{ item }">
            <v-chip size="small" color="info">
              {{ item.employee_count || 0 }} employees
            </v-chip>
          </template>

          <template v-slot:item.is_active="{ item }">
            <v-chip size="small" :color="item.is_active ? 'success' : 'grey'">
              {{ item.is_active ? "Active" : "Inactive" }}
            </v-chip>
          </template>

          <template v-slot:item.actions="{ item }">
            <v-btn
              icon="mdi-pencil"
              size="small"
              variant="text"
              color="primary"
              @click="openEditDialog(item)"
              title="Edit"
            ></v-btn>
            <v-btn
              icon="mdi-delete"
              size="small"
              variant="text"
              color="error"
              @click="deletePosition(item)"
              title="Delete"
              :disabled="item.employee_count > 0"
            ></v-btn>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <!-- Add/Edit Position Rate Dialog -->
    <v-dialog v-model="showDialog" max-width="600px" persistent>
      <v-card>
        <v-card-title
          class="text-h5 py-4"
          :class="isEditing ? 'bg-primary' : 'bg-success'"
        >
          <v-icon start>{{ isEditing ? "mdi-pencil" : "mdi-plus" }}</v-icon>
          {{ isEditing ? "Edit Position Rate" : "Add New Position Rate" }}
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-6">
          <v-form ref="form" v-model="formValid">
            <v-text-field
              v-model="formData.position_name"
              label="Position Name *"
              prepend-inner-icon="mdi-badge-account"
              variant="outlined"
              :rules="[
                (v) => !!v || 'Position name is required',
                (v) => (v && v.length <= 100) || 'Maximum 100 characters',
              ]"
              counter="100"
              class="mb-4"
            ></v-text-field>

            <v-text-field
              v-model="formData.code"
              label="Position Code"
              prepend-inner-icon="mdi-barcode"
              variant="outlined"
              hint="Optional short code for the position"
              persistent-hint
              :rules="[(v) => !v || v.length <= 20 || 'Maximum 20 characters']"
              counter="20"
              class="mb-4"
            ></v-text-field>

            <v-text-field
              v-model.number="formData.daily_rate"
              label="Daily Rate (₱) *"
              type="number"
              prepend-inner-icon="mdi-cash"
              variant="outlined"
              prefix="₱"
              :rules="[
                (v) =>
                  (v !== null && v !== undefined && v !== '') ||
                  'Daily rate is required',
                (v) => v >= 0 || 'Rate must be positive',
                (v) => v <= 999999.99 || 'Rate is too large',
              ]"
              class="mb-2"
            ></v-text-field>

            <!-- Rate Change Comparison (only when editing) -->
            <v-alert
              v-if="isEditing && rateComparison.hasChange"
              :type="rateComparison.type"
              variant="tonal"
              density="compact"
              class="mb-4"
            >
              <div class="d-flex align-center justify-space-between">
                <div>
                  <div class="text-subtitle-2 mb-1">Rate Change</div>
                  <div class="text-caption">
                    Previous:
                    <strong>{{
                      formatCurrency(rateComparison.oldRate)
                    }}</strong>
                    → New:
                    <strong>{{
                      formatCurrency(rateComparison.newRate)
                    }}</strong>
                  </div>
                </div>
                <v-chip :color="rateComparison.chipColor" size="small">
                  <v-icon start size="small">{{ rateComparison.icon }}</v-icon>
                  {{ rateComparison.changeText }}
                </v-chip>
              </div>
            </v-alert>

            <v-select
              v-model="formData.category"
              :items="categoryOptions"
              label="Category"
              prepend-inner-icon="mdi-tag"
              variant="outlined"
              hint="Optional classification for the position"
              persistent-hint
              clearable
              class="mb-4"
            ></v-select>

            <v-textarea
              v-model="formData.description"
              label="Description"
              prepend-inner-icon="mdi-text"
              variant="outlined"
              rows="3"
              hint="Optional description of the position"
              persistent-hint
              counter="500"
              :rules="[
                (v) => !v || v.length <= 500 || 'Maximum 500 characters',
              ]"
              class="mb-4"
            ></v-textarea>

            <v-switch
              v-model="formData.is_active"
              label="Active"
              color="success"
              hint="Inactive positions won't be selectable for new employees"
              persistent-hint
            ></v-switch>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="closeDialog">Cancel</v-btn>
          <v-btn
            :color="isEditing ? 'primary' : 'success'"
            @click="savePosition"
            :loading="saving"
            :disabled="!formValid"
          >
            {{ isEditing ? "Update" : "Create" }}
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

const toast = useToast();

const loading = ref(false);
const positions = ref([]);
const search = ref("");
const filterCategory = ref(null);
const showActiveOnly = ref(true);

const showDialog = ref(false);
const isEditing = ref(false);
const formValid = ref(false);
const saving = ref(false);
const form = ref(null);

const formData = ref({
  position_name: "",
  code: "",
  daily_rate: null,
  category: null,
  description: "",
  is_active: true,
});

const selectedPosition = ref(null);

const categoryOptions = [
  { title: "Skilled", value: "skilled" },
  { title: "Semi-Skilled", value: "semi-skilled" },
  { title: "Technical", value: "technical" },
  { title: "Support", value: "support" },
];

const headers = [
  { title: "Position Name", key: "position_name", sortable: true },
  { title: "Daily Rate", key: "daily_rate", sortable: true, align: "end" },
  { title: "Category", key: "category", sortable: true },
  { title: "Employees", key: "employee_count", sortable: true },
  { title: "Status", key: "is_active", sortable: true },
  { title: "Actions", key: "actions", sortable: false, align: "center" },
];

const filteredPositions = computed(() => {
  let filtered = positions.value;

  if (showActiveOnly.value) {
    filtered = filtered.filter((p) => p.is_active);
  }

  if (filterCategory.value) {
    filtered = filtered.filter((p) => p.category === filterCategory.value);
  }

  return filtered;
});

// Rate change comparison for editing
const rateComparison = computed(() => {
  if (
    !isEditing.value ||
    !selectedPosition.value ||
    formData.value.daily_rate === null
  ) {
    return { hasChange: false };
  }

  const oldRate = parseFloat(selectedPosition.value.daily_rate);
  const newRate = parseFloat(formData.value.daily_rate);

  if (oldRate === newRate) {
    return { hasChange: false };
  }

  const difference = newRate - oldRate;
  const percentChange = ((difference / oldRate) * 100).toFixed(2);
  const isIncrease = difference > 0;

  return {
    hasChange: true,
    oldRate,
    newRate,
    difference,
    percentChange: Math.abs(percentChange),
    isIncrease,
    type: isIncrease ? "success" : "warning",
    chipColor: isIncrease ? "success" : "error",
    icon: isIncrease ? "mdi-trending-up" : "mdi-trending-down",
    changeText: `${isIncrease ? "+" : ""}${percentChange}%`,
  };
});

onMounted(() => {
  loadPositionRates();
});

async function loadPositionRates() {
  loading.value = true;
  try {
    const response = await api.get("/position-rates");
    positions.value = response.data || [];
  } catch (error) {
    console.error("Error loading position rates:", error);
    toast.error("Failed to load position rates");
  } finally {
    loading.value = false;
  }
}

function openAddDialog() {
  isEditing.value = false;
  formData.value = {
    position_name: "",
    code: "",
    daily_rate: null,
    category: null,
    description: "",
    is_active: true,
  };
  showDialog.value = true;
}

function openEditDialog(position) {
  isEditing.value = true;
  selectedPosition.value = position;
  formData.value = {
    position_name: position.position_name,
    code: position.code || "",
    daily_rate: position.daily_rate,
    category: position.category,
    description: position.description || "",
    is_active: position.is_active,
  };
  showDialog.value = true;
}

function closeDialog() {
  showDialog.value = false;
  isEditing.value = false;
  selectedPosition.value = null;
  formData.value = {
    position_name: "",
    code: "",
    daily_rate: null,
    category: null,
    description: "",
    is_active: true,
  };
}

async function savePosition() {
  if (!formValid.value) return;

  saving.value = true;
  try {
    if (isEditing.value) {
      // Update existing position
      await api.put(
        `/position-rates/${selectedPosition.value.id}`,
        formData.value,
      );
      toast.success("Position rate updated successfully!");
    } else {
      // Create new position
      await api.post("/position-rates", formData.value);
      toast.success("Position rate created successfully!");
    }

    await loadPositionRates();
    closeDialog();
  } catch (error) {
    console.error("Error saving position rate:", error);
    const message =
      error.response?.data?.message ||
      error.response?.data?.error ||
      `Failed to ${isEditing.value ? "update" : "create"} position rate`;
    toast.error(message);
  } finally {
    saving.value = false;
  }
}

async function deletePosition(position) {
  if (position.employee_count > 0) {
    toast.error(
      `Cannot delete position. ${position.employee_count} employee(s) are assigned to this position.`,
    );
    return;
  }

  if (
    !confirm(
      `Are you sure you want to delete the position "${position.position_name}"?`,
    )
  ) {
    return;
  }

  try {
    await api.delete(`/position-rates/${position.id}`);
    toast.success("Position rate deleted successfully!");
    await loadPositionRates();
  } catch (error) {
    console.error("Error deleting position rate:", error);
    const message =
      error.response?.data?.message ||
      error.response?.data?.error ||
      "Failed to delete position rate";
    toast.error(message);
  }
}

function formatCurrency(value) {
  if (!value && value !== 0) return "₱0.00";
  return (
    "₱" +
    Number(value).toLocaleString("en-US", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })
  );
}

function getCategoryColor(category) {
  const colors = {
    skilled: "success",
    "semi-skilled": "info",
    technical: "primary",
    support: "warning",
  };
  return colors[category] || "grey";
}

function formatCategory(category) {
  if (!category) return "";
  return category
    .split("-")
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join("-");
}
</script>
