<template>
  <div class="position-rates-page">
    <!-- Modern Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="page-title-section">
          <div class="page-icon-badge">
            <v-icon size="22">mdi-badge-account</v-icon>
          </div>
          <div>
            <h1 class="page-title">Position Rates Management</h1>
            <p class="page-subtitle">
              Manage pay rates for different positions
            </p>
          </div>
        </div>
        <div class="action-buttons">
          <button class="action-btn action-btn-primary" @click="openAddDialog">
            <v-icon size="20">mdi-plus</v-icon>
            <span>Add Position Rate</span>
          </button>
        </div>
      </div>
    </div>

    <div class="modern-card">
      <div class="filters-section">
        <v-row class="mb-0" align="center">
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
          <v-spacer></v-spacer>
          <v-col cols="auto">
            <v-btn
              color="#ED985F"
              variant="tonal"
              icon="mdi-refresh"
              @click="loadPositionRates"
              :loading="loading"
              title="Refresh"
            ></v-btn>
          </v-col>
        </v-row>
      </div>

      <div class="table-section">
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
      </div>
    </div>

    <!-- Add/Edit Position Rate Dialog -->
    <v-dialog v-model="showDialog" max-width="800px" persistent scrollable>
      <v-card class="modern-dialog">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper primary">
            <v-icon size="24">{{
              isEditing ? "mdi-pencil" : "mdi-plus"
            }}</v-icon>
          </div>
          <div>
            <div class="dialog-title">
              {{ isEditing ? "Edit Position Rate" : "Add New Position Rate" }}
            </div>
            <div class="dialog-subtitle">
              {{
                isEditing
                  ? "Update position rate details"
                  : "Create new position rate"
              }}
            </div>
          </div>
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="dialog-content" style="max-height: 70vh">
          <v-form ref="form" v-model="formValid">
            <!-- Section: Basic Information -->
            <v-col cols="12" class="px-0">
              <div class="section-header">
                <div class="section-icon">
                  <v-icon size="18">mdi-information</v-icon>
                </div>
                <h3 class="section-title">Basic Information</h3>
              </div>
            </v-col>

            <div class="form-field-wrapper mt-3">
              <label class="form-label">
                <v-icon size="small" color="#ED985F">mdi-badge-account</v-icon>
                Position Name <span class="text-error">*</span>
              </label>
              <v-text-field
                v-model="formData.position_name"
                placeholder="Enter position name"
                prepend-inner-icon="mdi-badge-account"
                variant="outlined"
                density="comfortable"
                color="primary"
                :rules="[
                  (v) => !!v || 'Position name is required',
                  (v) => (v && v.length <= 100) || 'Maximum 100 characters',
                ]"
                counter="100"
              ></v-text-field>
            </div>

            <div class="form-field-wrapper">
              <label class="form-label">
                <v-icon size="small" color="#ED985F">mdi-barcode</v-icon>
                Position Code
              </label>
              <v-text-field
                v-model="formData.code"
                placeholder="Optional short code"
                prepend-inner-icon="mdi-barcode"
                variant="outlined"
                density="comfortable"
                color="primary"
                hint="Optional short code for the position"
                persistent-hint
                :rules="[
                  (v) => !v || v.length <= 20 || 'Maximum 20 characters',
                ]"
                counter="20"
              ></v-text-field>
            </div>

            <!-- Section: Rate Details -->
            <v-col cols="12" class="px-0 mt-4">
              <div class="section-header">
                <div class="section-icon">
                  <v-icon size="18">mdi-cash</v-icon>
                </div>
                <h3 class="section-title">Rate Details</h3>
              </div>
            </v-col>

            <div class="form-field-wrapper mt-3">
              <label class="form-label">
                <v-icon size="small" color="#ED985F">mdi-cash</v-icon>
                Daily Rate <span class="text-error">*</span>
              </label>
              <v-text-field
                v-model.number="formData.daily_rate"
                type="number"
                placeholder="0.00"
                prepend-inner-icon="mdi-cash"
                variant="outlined"
                density="comfortable"
                color="primary"
                prefix="₱"
                :rules="[
                  (v) =>
                    (v !== null && v !== undefined && v !== '') ||
                    'Daily rate is required',
                  (v) => v >= 0 || 'Rate must be positive',
                  (v) => v <= 999999.99 || 'Rate is too large',
                ]"
              ></v-text-field>
            </div>

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

            <div class="form-field-wrapper">
              <label class="form-label">
                <v-icon size="small" color="#ED985F">mdi-tag</v-icon>
                Category
              </label>
              <v-select
                v-model="formData.category"
                :items="categoryOptions"
                placeholder="Select category"
                prepend-inner-icon="mdi-tag"
                variant="outlined"
                density="comfortable"
                color="primary"
                hint="Optional classification for the position"
                persistent-hint
                clearable
              ></v-select>
            </div>

            <div class="form-field-wrapper">
              <label class="form-label">
                <v-icon size="small" color="#ED985F">mdi-text</v-icon>
                Description
              </label>
              <v-textarea
                v-model="formData.description"
                placeholder="Enter description"
                prepend-inner-icon="mdi-text"
                variant="outlined"
                density="comfortable"
                color="primary"
                rows="3"
                hint="Optional description of the position"
                persistent-hint
                counter="500"
                :rules="[
                  (v) => !v || v.length <= 500 || 'Maximum 500 characters',
                ]"
              ></v-textarea>
            </div>

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

        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <button
            class="dialog-btn dialog-btn-cancel"
            @click="closeDialog"
            :disabled="saving"
          >
            Cancel
          </button>
          <button
            class="dialog-btn dialog-btn-primary"
            @click="savePosition"
            :disabled="!formValid || saving"
          >
            <v-progress-circular
              v-if="saving"
              indeterminate
              size="16"
              width="2"
              class="mr-2"
            ></v-progress-circular>
            <v-icon v-else size="18" class="mr-1">{{
              isEditing ? "mdi-pencil" : "mdi-check"
            }}</v-icon>
            {{
              saving
                ? isEditing
                  ? "Updating..."
                  : "Creating..."
                : isEditing
                  ? "Update"
                  : "Create"
            }}
          </button>
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

<style scoped lang="scss">
.position-rates-page {
  max-width: 1600px;
  margin: 0 auto;
}

.page-header {
  margin-bottom: 24px;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
}

.page-title-section {
  display: flex;
  align-items: center;
  gap: 16px;
}

.page-icon-badge {
  width: 56px;
  height: 56px;
  border-radius: 14px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);
  flex-shrink: 0;

  .v-icon {
    color: #ffffff !important;
  }
}

.page-title {
  font-size: 28px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
  letter-spacing: -0.5px;
}

.page-subtitle {
  font-size: 14px;
  color: #64748b;
  margin: 4px 0 0 0;
}

.action-buttons {
  display: flex;
  gap: 12px;
}

.action-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 20px;
  border-radius: 10px;
  border: none;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  white-space: nowrap;

  .v-icon {
    flex-shrink: 0;
  }

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(237, 152, 95, 0.25);
  }

  &.action-btn-primary {
    background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);

    .v-icon {
      color: #ffffff !important;
    }
  }
}

.modern-card {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  overflow: hidden;
  padding: 24px;
}

.filters-section {
  background: rgba(0, 31, 61, 0.01);
}

.table-section {
  background: #ffffff;
}

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
  margin-bottom: 16px;
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

.form-field-wrapper {
  margin-bottom: 16px;
}

.form-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 600;
  color: #001f3d;
  margin-bottom: 8px;
}
</style>
