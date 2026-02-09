<template>
  <div class="holiday-page">
    <!-- Modern Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="page-title-section">
          <div class="page-icon-badge">
            <v-icon size="22">mdi-calendar-star</v-icon>
          </div>
          <div>
            <h1 class="page-title">Holiday Management</h1>
            <p class="page-subtitle">
              Manage company holidays and holiday pay rates
            </p>
          </div>
        </div>
        <div class="action-buttons">
          <button class="action-btn action-btn-primary" @click="openAddDialog">
            <v-icon size="20">mdi-plus</v-icon>
            <span>Add Holiday</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Modern Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon total">
          <v-icon size="20">mdi-calendar-multiple</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Total Holidays</div>
          <div class="stat-value">{{ totalHolidays }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon regular">
          <v-icon size="20">mdi-calendar-check</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Regular</div>
          <div class="stat-value info">{{ regularCount }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon special">
          <v-icon size="20">mdi-calendar-star</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Special</div>
          <div class="stat-value primary">{{ specialCount }}</div>
        </div>
      </div>
    </div>

    <div class="modern-card">
      <div class="filters-section">
        <v-row align="center" class="mb-0">
          <v-col cols="12" md="3">
            <v-select
              v-model="selectedYear"
              :items="years"
              label="Year"
              variant="outlined"
              density="comfortable"
              hide-details
              @update:model-value="loadHolidays"
            ></v-select>
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filterType"
              :items="typeFilterOptions"
              item-title="text"
              item-value="value"
              label="Type"
              clearable
              variant="outlined"
              density="comfortable"
              hide-details
            ></v-select>
          </v-col>
          <v-col cols="auto">
            <v-btn
              color="#ED985F"
              variant="tonal"
              icon="mdi-refresh"
              @click="loadHolidays"
              :loading="loading"
              title="Refresh"
            ></v-btn>
          </v-col>
        </v-row>
      </div>

      <div class="table-section">
        <v-data-table
          :headers="headers"
          :items="filteredHolidays"
          :loading="loading"
          :items-per-page="15"
          hover
          class="elevation-0"
        >
          <template v-slot:item.date="{ item }">
            <div class="date-display">
              <div class="date-main">
                <v-icon size="18" color="#ED985F" class="mr-2"
                  >mdi-calendar</v-icon
                >
                <strong>{{ formatDateFull(item.date) }}</strong>
              </div>
              <div class="date-weekday">{{ formatWeekday(item.date) }}</div>
            </div>
          </template>

          <template v-slot:item.name="{ item }">
            <div class="font-weight-medium">{{ item.name }}</div>
            <div
              class="text-caption text-medium-emphasis"
              v-if="item.description"
            >
              {{ item.description }}
            </div>
          </template>

          <template v-slot:item.type="{ item }">
            <v-chip
              size="small"
              :color="item.type === 'regular' ? 'info' : 'primary'"
              variant="tonal"
            >
              <v-icon start size="16">
                {{
                  item.type === "regular"
                    ? "mdi-calendar-check"
                    : "mdi-calendar-star"
                }}
              </v-icon>
              {{ item.type === "regular" ? "Regular" : "Special" }}
            </v-chip>
          </template>

          <template v-slot:item.pay_rate="{ item }">
            <v-chip size="small" color="success" variant="tonal">
              <v-icon start size="14">mdi-cash-multiple</v-icon>
              {{ getPayRateText(item) }}
            </v-chip>
          </template>

          <template v-slot:item.is_recurring="{ item }">
            <v-chip
              v-if="item.is_recurring"
              size="small"
              color="secondary"
              variant="tonal"
            >
              <v-icon start size="14">mdi-sync</v-icon>
              Recurring
            </v-chip>
            <span v-else class="text-medium-emphasis">-</span>
          </template>

          <template v-slot:item.is_active="{ item }">
            <v-chip
              size="small"
              :color="item.is_active ? 'success' : 'error'"
              variant="tonal"
            >
              {{ item.is_active ? "Active" : "Inactive" }}
            </v-chip>
          </template>

          <template v-slot:item.actions="{ item }">
            <v-btn
              icon="mdi-pencil"
              size="small"
              variant="text"
              color="#ED985F"
              @click="editHoliday(item)"
              title="Edit"
            ></v-btn>
            <v-btn
              icon="mdi-delete"
              size="small"
              variant="text"
              color="error"
              @click="confirmDelete(item)"
              title="Delete"
            ></v-btn>
          </template>
        </v-data-table>
      </div>
    </div>

    <!-- Add/Edit Holiday Dialog -->
    <v-dialog v-model="showDialog" max-width="800px" persistent scrollable>
      <v-card class="modern-dialog">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper primary">
            <v-icon size="24">{{
              showEditModal ? "mdi-pencil" : "mdi-plus"
            }}</v-icon>
          </div>
          <div>
            <div class="dialog-title">
              {{ showEditModal ? "Edit Holiday" : "Add New Holiday" }}
            </div>
            <div class="dialog-subtitle">
              {{
                showEditModal
                  ? "Update holiday information"
                  : "Create a new company holiday"
              }}
            </div>
          </div>
        </v-card-title>

        <v-card-text class="dialog-content" style="max-height: 70vh">
          <v-form ref="formRef" v-model="formValid">
            <!-- Section: Holiday Information -->
            <v-col cols="12" class="px-0">
              <div class="section-header">
                <div class="section-icon">
                  <v-icon size="18">mdi-information</v-icon>
                </div>
                <h3 class="section-title">Holiday Information</h3>
              </div>
            </v-col>

            <div class="form-field-wrapper mt-3">
              <label class="form-label">
                <v-icon size="small" color="#ED985F">mdi-format-title</v-icon>
                Holiday Name <span class="text-error">*</span>
              </label>
              <v-text-field
                v-model="form.name"
                placeholder="e.g., New Year's Day"
                variant="outlined"
                density="comfortable"
                color="primary"
                :rules="[(v) => !!v || 'Holiday name is required']"
              ></v-text-field>
            </div>

            <v-row>
              <v-col cols="12" md="6">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="#ED985F">mdi-calendar</v-icon>
                    Date <span class="text-error">*</span>
                  </label>
                  <v-text-field
                    v-model="form.date"
                    type="date"
                    variant="outlined"
                    density="comfortable"
                    color="primary"
                    :rules="[(v) => !!v || 'Date is required']"
                  ></v-text-field>
                </div>
              </v-col>
              <v-col cols="12" md="6">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="#ED985F">mdi-tag</v-icon>
                    Type <span class="text-error">*</span>
                  </label>
                  <v-select
                    v-model="form.type"
                    :items="typeOptions"
                    item-title="text"
                    item-value="value"
                    variant="outlined"
                    density="comfortable"
                    color="primary"
                    :rules="[(v) => !!v || 'Type is required']"
                  ></v-select>
                </div>
              </v-col>
            </v-row>

            <div class="form-field-wrapper">
              <label class="form-label">
                <v-icon size="small" color="#ED985F">mdi-text</v-icon>
                Description
              </label>
              <v-textarea
                v-model="form.description"
                placeholder="Optional description"
                variant="outlined"
                density="comfortable"
                color="primary"
                rows="3"
              ></v-textarea>
            </div>

            <!-- Pay Rate Preview -->
            <v-alert type="info" variant="tonal" class="mb-4">
              <div class="d-flex align-center">
                <v-icon start>mdi-calculator</v-icon>
                <div>
                  <strong>Pay Rate:</strong>
                  <span v-if="form.type === 'regular'">
                    2.0x on regular days, 2.6x on Sundays
                  </span>
                  <span v-else> 2.6x pay for 8 hours work </span>
                </div>
              </div>
            </v-alert>

            <!-- Section: Settings -->
            <v-col cols="12" class="px-0 mt-4">
              <div class="section-header">
                <div class="section-icon">
                  <v-icon size="18">mdi-cog</v-icon>
                </div>
                <h3 class="section-title">Settings</h3>
              </div>
            </v-col>

            <v-row class="mt-3">
              <v-col cols="12" md="6">
                <v-switch
                  v-model="form.is_recurring"
                  label="Recurring annually"
                  color="#ED985F"
                  hide-details
                ></v-switch>
              </v-col>
              <v-col cols="12" md="6">
                <v-switch
                  v-model="form.is_active"
                  label="Active"
                  color="success"
                  hide-details
                ></v-switch>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <button
            class="dialog-btn dialog-btn-cancel"
            @click="closeModal"
            :disabled="saving"
          >
            Cancel
          </button>
          <button
            class="dialog-btn dialog-btn-primary"
            @click="submitForm"
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
              showEditModal ? "mdi-check" : "mdi-plus"
            }}</v-icon>
            {{ saving ? "Saving..." : showEditModal ? "Update" : "Create" }}
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete Confirmation Dialog -->
    <v-dialog v-model="showDeleteModal" max-width="500px" persistent>
      <v-card class="modern-dialog">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper danger">
            <v-icon size="24">mdi-alert-circle</v-icon>
          </div>
          <div>
            <div class="dialog-title">Delete Holiday</div>
            <div class="dialog-subtitle">This action cannot be undone</div>
          </div>
        </v-card-title>

        <v-card-text class="dialog-content py-6">
          <div class="mb-4">
            <p class="text-body-1 mb-2">
              Are you sure you want to delete this holiday?
            </p>
            <v-alert variant="tonal" color="error" class="mt-4">
              <div class="d-flex align-center">
                <v-icon start>mdi-calendar-remove</v-icon>
                <div>
                  <strong>{{ holidayToDelete?.name }}</strong>
                  <div class="text-caption">
                    {{ formatDateFull(holidayToDelete?.date) }}
                  </div>
                </div>
              </div>
            </v-alert>
          </div>
          <v-alert type="warning" variant="tonal" density="compact">
            <v-icon start size="small">mdi-information</v-icon>
            This will permanently remove the holiday from the system.
          </v-alert>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <button
            class="dialog-btn dialog-btn-cancel"
            @click="showDeleteModal = false"
            :disabled="deleting"
          >
            Cancel
          </button>
          <button
            class="dialog-btn dialog-btn-danger"
            @click="deleteHoliday"
            :disabled="deleting"
          >
            <v-progress-circular
              v-if="deleting"
              indeterminate
              size="16"
              width="2"
              class="mr-2"
            ></v-progress-circular>
            <v-icon v-else size="18" class="mr-1">mdi-delete</v-icon>
            {{ deleting ? "Deleting..." : "Delete Holiday" }}
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import api from "@/services/api";
import { useToast } from "vue-toastification";
import { devLog } from "@/utils/devLog";

const toast = useToast();

// State
const selectedYear = ref(new Date().getFullYear());
const holidays = ref([]);
const filterType = ref(null);
const showDialog = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const holidayToDelete = ref(null);
const loading = ref(false);
const saving = ref(false);
const deleting = ref(false);
const formValid = ref(false);
const formRef = ref(null);

const form = ref({
  name: "",
  date: "",
  type: "regular",
  description: "",
  is_recurring: false,
  is_active: true,
});

const typeFilterOptions = [
  { text: "All Types", value: null },
  { text: "Regular Holiday", value: "regular" },
  { text: "Special Holiday", value: "special" },
];

const typeOptions = [
  { text: "Regular Holiday", value: "regular" },
  { text: "Special Holiday", value: "special" },
];

const headers = [
  { title: "Date", key: "date", sortable: true },
  { title: "Holiday Name", key: "name", sortable: true },
  { title: "Type", key: "type", sortable: true },
  { title: "Pay Rate", key: "pay_rate", sortable: false },
  { title: "Recurring", key: "is_recurring", sortable: true },
  { title: "Status", key: "is_active", sortable: true },
  { title: "Actions", key: "actions", sortable: false, align: "center" },
];

// Computed
const years = computed(() => {
  const currentYear = new Date().getFullYear();
  return [currentYear - 1, currentYear, currentYear + 1, currentYear + 2];
});

const filteredHolidays = computed(() => {
  if (filterType.value === null) return holidays.value;
  return holidays.value.filter((h) => h.type === filterType.value);
});

const totalHolidays = computed(() => holidays.value.length);
const regularCount = computed(
  () => holidays.value.filter((h) => h.type === "regular").length,
);
const specialCount = computed(
  () => holidays.value.filter((h) => h.type === "special").length,
);

// Methods
const loadHolidays = async () => {
  loading.value = true;
  try {
    const response = await api.get(`/holidays/year/${selectedYear.value}`);
    holidays.value = response.data.data.holidays;
  } catch (error) {
    devLog.error("Failed to load holidays:", error);
    toast.error("Failed to load holidays");
  } finally {
    loading.value = false;
  }
};

const formatDateFull = (date) => {
  return new Date(date).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

const formatWeekday = (date) => {
  return new Date(date).toLocaleDateString("en-US", { weekday: "long" });
};

const getPayRateText = (holiday) => {
  const date = new Date(holiday.date);
  const isSunday = date.getDay() === 0;

  if (holiday.type === "regular") {
    return isSunday ? "2.6x" : "2.0x";
  } else {
    return "2.6x";
  }
};

const openAddDialog = () => {
  form.value = {
    name: "",
    date: "",
    type: "regular",
    description: "",
    is_recurring: false,
    is_active: true,
  };
  showEditModal.value = false;
  showDialog.value = true;
};

const editHoliday = (holiday) => {
  form.value = {
    id: holiday.id,
    name: holiday.name,
    date: holiday.date,
    type: holiday.type,
    description: holiday.description || "",
    is_recurring: holiday.is_recurring,
    is_active: holiday.is_active,
  };
  showEditModal.value = true;
  showDialog.value = true;
};

const confirmDelete = (holiday) => {
  holidayToDelete.value = holiday;
  showDeleteModal.value = true;
};

const deleteHoliday = async () => {
  deleting.value = true;
  try {
    await api.delete(`/holidays/${holidayToDelete.value.id}`);
    toast.success("Holiday deleted successfully");
    showDeleteModal.value = false;
    holidayToDelete.value = null;
    await loadHolidays();
  } catch (error) {
    devLog.error("Failed to delete holiday:", error);
    toast.error("Failed to delete holiday");
  } finally {
    deleting.value = false;
  }
};

const submitForm = async () => {
  if (!formValid.value) return;

  saving.value = true;
  try {
    if (showEditModal.value) {
      await api.put(`/holidays/${form.value.id}`, form.value);
      toast.success("Holiday updated successfully");
    } else {
      await api.post("/holidays", form.value);
      toast.success("Holiday created successfully");
    }

    closeModal();
    await loadHolidays();
  } catch (error) {
    devLog.error("Failed to save holiday:", error);
    toast.error(error.response?.data?.message || "Failed to save holiday");
  } finally {
    saving.value = false;
  }
};

const closeModal = () => {
  showDialog.value = false;
  showEditModal.value = false;
  form.value = {
    name: "",
    date: "",
    type: "regular",
    description: "",
    is_recurring: false,
    is_active: true,
  };
};

onMounted(() => {
  loadHolidays();
});
</script>

<style scoped lang="scss">
.holiday-page {
  max-width: 1600px;
  margin: 0 auto;
}

/* Page Header */
.page-header {
  margin-bottom: 28px;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 24px;
  flex-wrap: wrap;

  @media (max-width: 960px) {
    flex-direction: column;
    align-items: flex-start;
  }
}

.page-title-section {
  display: flex;
  align-items: center;
  gap: 16px;
  flex: 1;
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
  margin: 0 0 4px 0;
  letter-spacing: -0.5px;
}

.page-subtitle {
  font-size: 14px;
  color: rgba(0, 31, 61, 0.6);
  margin: 0;
}

.action-buttons {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;

  @media (max-width: 960px) {
    width: 100%;
  }
}

.action-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 20px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
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

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.stat-card {
  background: white;
  border-radius: 12px;
  padding: 14px 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  border: 1px solid rgba(0, 31, 61, 0.06);
  position: relative;
  overflow: hidden;

  &::before {
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
}

.stat-card:hover {
  box-shadow: 0 8px 24px rgba(237, 152, 95, 0.2);
  transform: translateY(-4px);
  border-color: rgba(237, 152, 95, 0.3);

  &::before {
    transform: scaleY(1);
  }
}

.stat-icon {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  flex-shrink: 0;
}

.stat-icon.total {
  background: linear-gradient(135deg, #001f3d 0%, #0f3557 100%);
}

.stat-icon.regular {
  background: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%);
}

.stat-icon.special {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
}

.stat-content {
  flex: 1;
}

.stat-label {
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
}

.stat-value {
  font-size: 24px;
  font-weight: 700;
  color: #001f3d;
  letter-spacing: -0.5px;
}

.stat-value.info {
  color: #3b82f6;
}

.stat-value.primary {
  color: #ed985f;
}

/* Modern Card */
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
  margin-bottom: 24px;
}

.table-section {
  background: transparent;
}

.date-display {
  display: flex;
  flex-direction: column;
}

.date-main {
  display: flex;
  align-items: center;
  margin-bottom: 2px;
}

.date-weekday {
  font-size: 11px;
  color: #64748b;
  padding-left: 26px;
}

/* Dialog Styles */
.modern-dialog .dialog-header {
  padding: 24px;
  background: white;
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
  flex-shrink: 0;
}

.dialog-icon-wrapper.primary {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);
}

.dialog-icon-wrapper.danger {
  background: linear-gradient(135deg, #ef4444 0%, #f87171 100%);
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
}

.dialog-title {
  font-size: 20px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
  line-height: 1.2;
}

.dialog-subtitle {
  font-size: 13px;
  color: #64748b;
  margin: 4px 0 0 0;
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
}

.dialog-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.dialog-btn-cancel {
  background: transparent;
  color: #64748b;
}

.dialog-btn-cancel:hover:not(:disabled) {
  background: rgba(0, 31, 61, 0.04);
}

.dialog-btn-primary {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: white;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);
  margin-left: 12px;
}

.dialog-btn-primary:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.4);
}

.dialog-btn-danger {
  background: linear-gradient(135deg, #ef4444 0%, #f87171 100%);
  color: white;
  box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
  margin-left: 12px;
}

.dialog-btn-danger:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
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

/* Responsive */
@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }

  .action-btn {
    flex: 1;
    justify-content: center;
  }
}
</style>
