<template>
  <div class="attendance-settings-page">
    <v-overlay :model-value="loading" class="align-center justify-center">
      <v-progress-circular
        indeterminate
        color="#ED985F"
        :size="70"
        :width="7"
      ></v-progress-circular>
    </v-overlay>

    <!-- Modern Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="back-button-wrapper">
          <button class="back-button" @click="$router.push('/settings')">
            <v-icon size="20">mdi-arrow-left</v-icon>
            <span>Back to Settings</span>
          </button>
        </div>

        <div class="header-main">
          <div class="page-title-section">
            <div class="page-icon-badge">
              <v-icon size="22">mdi-clock-check</v-icon>
            </div>
            <div>
              <h1 class="page-title">Attendance Settings</h1>
              <p class="page-subtitle">
                Configure per-department schedules for accurate attendance and
                payroll
              </p>
            </div>
          </div>
          <div class="action-buttons">
            <button
              class="action-btn action-btn-primary"
              @click="openBulkDialog"
            >
              <v-icon size="20">mdi-calendar-multiple</v-icon>
              <span>Set All Schedules</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon total">
          <v-icon size="20">mdi-folder-multiple</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Total Departments</div>
          <div class="stat-value">{{ stats.total }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon active">
          <v-icon size="20">mdi-check-circle</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Active Departments</div>
          <div class="stat-value success">{{ stats.active }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon schedule">
          <v-icon size="20">mdi-timer-outline</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Custom Schedules</div>
          <div class="stat-value">{{ stats.customSchedules }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon default">
          <v-icon size="20">mdi-clock-outline</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Using Default</div>
          <div class="stat-value">{{ stats.defaultSchedules }}</div>
        </div>
      </div>
    </div>

    <!-- Filters and Table -->
    <div class="content-card">
      <div class="table-controls">
        <v-text-field
          v-model="search"
          prepend-inner-icon="mdi-magnify"
          label="Search departments..."
          variant="outlined"
          density="comfortable"
          hide-details
          clearable
          class="search-field"
        ></v-text-field>
      </div>

      <v-data-table
        :headers="headers"
        :items="filteredProjects"
        class="modern-table"
        :items-per-page="15"
        :loading="loading"
      >
        <template v-slot:item.department="{ item }">
          <div class="department-cell">
            <div class="department-name">{{ item.name }}</div>
            <div class="department-code">{{ item.code }}</div>
          </div>
        </template>

        <template v-slot:item.designation="{ item }">
          <div class="designation-cell">
            {{ item.description || "-" }}
          </div>
        </template>

        <template v-slot:item.time_in="{ item }">
          <div class="schedule-cell">
            {{ formatScheduleTime(item.time_in) || DEFAULT_TIME_IN }}
          </div>
        </template>

        <template v-slot:item.time_out="{ item }">
          <div class="schedule-cell">
            {{ formatScheduleTime(item.time_out) || DEFAULT_TIME_OUT }}
          </div>
        </template>

        <template v-slot:item.grace_period_minutes="{ item }">
          <div class="schedule-cell">
            {{
              item.grace_period_minutes !== null &&
              item.grace_period_minutes !== undefined
                ? `${item.grace_period_minutes} mins`
                : `${DEFAULT_GRACE} mins`
            }}
          </div>
        </template>

        <template v-slot:item.status="{ item }">
          <v-chip
            :color="item.is_active ? 'success' : 'grey'"
            size="small"
            class="status-chip"
          >
            <v-icon size="14" class="mr-1">
              {{ item.is_active ? "mdi-check-circle" : "mdi-close-circle" }}
            </v-icon>
            {{ item.is_active ? "Active" : "Inactive" }}
          </v-chip>
        </template>

        <template v-slot:item.actions="{ item }">
          <div class="action-buttons-cell">
            <v-tooltip text="Edit schedule" location="top">
              <template v-slot:activator="{ props }">
                <v-btn
                  v-bind="props"
                  icon
                  size="small"
                  variant="text"
                  color="warning"
                  @click="openEditDialog(item)"
                >
                  <v-icon size="18">mdi-pencil</v-icon>
                </v-btn>
              </template>
            </v-tooltip>
          </div>
        </template>
      </v-data-table>
    </div>

    <!-- Edit Schedule Dialog -->
    <v-dialog v-model="dialog" max-width="640px" persistent>
      <v-card class="modern-dialog-card" elevation="24">
        <v-card-title class="modern-dialog-header">
          <div class="d-flex align-center w-100">
            <div class="dialog-icon-badge">
              <v-icon size="24">mdi-clock-edit</v-icon>
            </div>
            <div class="flex-grow-1">
              <div class="text-h5 font-weight-bold">Edit Schedule</div>
              <div class="text-subtitle-2 text-white-70">
                {{ selectedProject?.name }}
              </div>
            </div>
            <v-btn
              icon
              variant="text"
              color="white"
              @click="closeDialog"
              size="small"
              class="ml-2"
            >
              <v-icon>mdi-close</v-icon>
            </v-btn>
          </div>
        </v-card-title>

        <v-card-text class="pa-6">
          <v-form ref="formRef" @submit.prevent="saveSchedule">
            <v-row>
              <v-col cols="12" md="4">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="primary">mdi-clock-in</v-icon>
                    Time In
                  </label>
                  <v-text-field
                    v-model="formData.time_in"
                    type="time"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-clock-in"
                    color="primary"
                    clearable
                  ></v-text-field>
                </div>
              </v-col>
              <v-col cols="12" md="4">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="primary">mdi-clock-out</v-icon>
                    Time Out
                  </label>
                  <v-text-field
                    v-model="formData.time_out"
                    type="time"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-clock-out"
                    color="primary"
                    clearable
                  ></v-text-field>
                </div>
              </v-col>
              <v-col cols="12" md="4">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="primary"
                      >mdi-timer-outline</v-icon
                    >
                    Grace Period (mins)
                  </label>
                  <v-text-field
                    v-model.number="formData.grace_period_minutes"
                    type="number"
                    min="0"
                    max="180"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-timer-outline"
                    color="primary"
                    clearable
                  ></v-text-field>
                </div>
              </v-col>
            </v-row>

            <div class="info-note">
              Leave fields empty to use the default schedule (07:30 - 17:00).
            </div>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-btn
            variant="text"
            color="grey-darken-1"
            size="large"
            @click="resetToDefault"
            prepend-icon="mdi-refresh"
          >
            Use Default Schedule
          </v-btn>
          <v-spacer></v-spacer>
          <v-btn
            variant="text"
            color="grey-darken-1"
            size="large"
            @click="closeDialog"
            prepend-icon="mdi-close"
          >
            Cancel
          </v-btn>
          <v-btn
            color="primary"
            size="large"
            :loading="saving"
            @click="saveSchedule"
            prepend-icon="mdi-check"
            class="px-6"
            elevation="2"
          >
            <span class="font-weight-bold">Save</span>
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snackbar" :color="snackbarColor" :timeout="3000">
      {{ snackbarText }}
    </v-snackbar>

    <!-- Bulk Schedule Dialog -->
    <v-dialog v-model="bulkDialog" max-width="720px" persistent>
      <v-card class="modern-dialog-card" elevation="24">
        <v-card-title class="modern-dialog-header">
          <div class="d-flex align-center w-100">
            <div class="dialog-icon-badge">
              <v-icon size="24">mdi-calendar-multiple</v-icon>
            </div>
            <div class="flex-grow-1">
              <div class="text-h5 font-weight-bold">Set Schedule for All</div>
              <div class="text-subtitle-2 text-white-70">
                Apply to all departments except selected ones
              </div>
            </div>
            <v-btn
              icon
              variant="text"
              color="white"
              @click="closeBulkDialog"
              size="small"
              class="ml-2"
            >
              <v-icon>mdi-close</v-icon>
            </v-btn>
          </div>
        </v-card-title>

        <v-card-text class="pa-6">
          <v-form ref="bulkFormRef" @submit.prevent="saveBulkSchedule">
            <v-row>
              <v-col cols="12" md="4">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="primary">mdi-clock-in</v-icon>
                    Time In
                  </label>
                  <v-text-field
                    v-model="bulkFormData.time_in"
                    type="time"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-clock-in"
                    color="primary"
                    clearable
                  ></v-text-field>
                </div>
              </v-col>
              <v-col cols="12" md="4">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="primary">mdi-clock-out</v-icon>
                    Time Out
                  </label>
                  <v-text-field
                    v-model="bulkFormData.time_out"
                    type="time"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-clock-out"
                    color="primary"
                    clearable
                  ></v-text-field>
                </div>
              </v-col>
              <v-col cols="12" md="4">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="primary"
                      >mdi-timer-outline</v-icon
                    >
                    Grace Period (mins)
                  </label>
                  <v-text-field
                    v-model.number="bulkFormData.grace_period_minutes"
                    type="number"
                    min="0"
                    max="180"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-timer-outline"
                    color="primary"
                    clearable
                  ></v-text-field>
                </div>
              </v-col>
            </v-row>

            <v-row>
              <v-col cols="12">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="primary"
                      >mdi-minus-circle</v-icon
                    >
                    Exclude Departments
                  </label>
                  <v-autocomplete
                    v-model="bulkFormData.exclude_project_ids"
                    :items="projects"
                    item-title="name"
                    item-value="id"
                    multiple
                    chips
                    closable-chips
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-filter-variant"
                    placeholder="Select departments to exclude"
                  ></v-autocomplete>
                </div>
              </v-col>
            </v-row>

            <div class="info-note">
              Leave fields empty to use the default schedule (07:30 - 17:00).
            </div>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-btn
            variant="text"
            color="grey-darken-1"
            size="large"
            @click="setBulkDefaults"
            prepend-icon="mdi-refresh"
          >
            Use Default Schedule
          </v-btn>
          <v-spacer></v-spacer>
          <v-btn
            variant="text"
            color="grey-darken-1"
            size="large"
            @click="closeBulkDialog"
            prepend-icon="mdi-close"
          >
            Cancel
          </v-btn>
          <v-btn
            color="primary"
            size="large"
            :loading="bulkSaving"
            @click="saveBulkSchedule"
            prepend-icon="mdi-check"
            class="px-6"
            elevation="2"
          >
            <span class="font-weight-bold">Apply</span>
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import api from "@/services/api";

const DEFAULT_TIME_IN = "07:30";
const DEFAULT_TIME_OUT = "17:00";
const DEFAULT_GRACE = 1;

const loading = ref(false);
const saving = ref(false);
const bulkSaving = ref(false);
const dialog = ref(false);
const bulkDialog = ref(false);
const projects = ref([]);
const search = ref("");
const snackbar = ref(false);
const snackbarText = ref("");
const snackbarColor = ref("success");
const selectedProject = ref(null);
const formRef = ref(null);
const bulkFormRef = ref(null);

const formData = ref({
  time_in: null,
  time_out: null,
  grace_period_minutes: null,
});

const bulkFormData = ref({
  time_in: DEFAULT_TIME_IN,
  time_out: DEFAULT_TIME_OUT,
  grace_period_minutes: DEFAULT_GRACE,
  exclude_project_ids: [],
});

const headers = [
  { title: "Department", key: "department", sortable: true },
  { title: "Designation", key: "designation", sortable: false },
  { title: "Time In", key: "time_in", sortable: false },
  { title: "Time Out", key: "time_out", sortable: false },
  { title: "Grace", key: "grace_period_minutes", sortable: false },
  { title: "Status", key: "status", sortable: true },
  { title: "Actions", key: "actions", sortable: false },
];

const filteredProjects = computed(() => {
  if (!search.value) return projects.value;
  const searchLower = search.value.toLowerCase();
  return projects.value.filter(
    (p) =>
      p.name.toLowerCase().includes(searchLower) ||
      p.code.toLowerCase().includes(searchLower) ||
      (p.description && p.description.toLowerCase().includes(searchLower)),
  );
});

const stats = computed(() => {
  const total = projects.value.length;
  const active = projects.value.filter((p) => p.is_active).length;
  const customSchedules = projects.value.filter(
    (p) => p.time_in || p.time_out || p.grace_period_minutes !== null,
  ).length;
  return {
    total,
    active,
    customSchedules,
    defaultSchedules: total - customSchedules,
  };
});

const fetchProjects = async () => {
  loading.value = true;
  try {
    const response = await api.get("/projects");
    projects.value = response.data;
  } catch (error) {
    showSnackbar("Failed to load departments", "error");
    console.error("Error fetching projects:", error);
  } finally {
    loading.value = false;
  }
};

const formatScheduleTime = (value) => {
  if (!value) return null;
  const [hours, minutes] = value.split(":");
  if (!hours || !minutes) return value;
  return `${hours.padStart(2, "0")}:${minutes.padStart(2, "0")}`;
};

const openEditDialog = (project) => {
  selectedProject.value = project;
  formData.value = {
    time_in: formatScheduleTime(project.time_in) || DEFAULT_TIME_IN,
    time_out: formatScheduleTime(project.time_out) || DEFAULT_TIME_OUT,
    grace_period_minutes:
      project.grace_period_minutes !== undefined &&
      project.grace_period_minutes !== null
        ? project.grace_period_minutes
        : DEFAULT_GRACE,
  };
  dialog.value = true;
};

const openBulkDialog = () => {
  bulkFormData.value = {
    time_in: DEFAULT_TIME_IN,
    time_out: DEFAULT_TIME_OUT,
    grace_period_minutes: DEFAULT_GRACE,
    exclude_project_ids: [],
  };
  bulkDialog.value = true;
};

const resetToDefault = () => {
  formData.value = {
    time_in: DEFAULT_TIME_IN,
    time_out: DEFAULT_TIME_OUT,
    grace_period_minutes: DEFAULT_GRACE,
  };
};

const setBulkDefaults = () => {
  bulkFormData.value = {
    ...bulkFormData.value,
    time_in: DEFAULT_TIME_IN,
    time_out: DEFAULT_TIME_OUT,
    grace_period_minutes: DEFAULT_GRACE,
  };
};

const saveSchedule = async () => {
  if (!selectedProject.value) return;
  if (!formRef.value) return;

  const valid = await formRef.value.validate();
  if (!valid.valid) return;

  saving.value = true;
  try {
    const payload = normalizeSchedulePayload({
      ...selectedProject.value,
      ...formData.value,
    });
    await api.put(`/projects/${selectedProject.value.id}`, payload);
    showSnackbar("Schedule updated successfully", "success");
    await fetchProjects();
    closeDialog();
  } catch (error) {
    const message =
      error.response?.data?.message || "Failed to update schedule";
    showSnackbar(message, "error");
    console.error("Error saving schedule:", error);
  } finally {
    saving.value = false;
  }
};

const saveBulkSchedule = async () => {
  if (!bulkFormRef.value) return;

  const valid = await bulkFormRef.value.validate();
  if (!valid.valid) return;

  bulkSaving.value = true;
  try {
    const payload = normalizeBulkPayload(bulkFormData.value);
    await api.post("/projects/bulk-schedule", payload);
    showSnackbar("Schedules updated successfully", "success");
    await fetchProjects();
    closeBulkDialog();
  } catch (error) {
    const message =
      error.response?.data?.message || "Failed to update schedules";
    showSnackbar(message, "error");
    console.error("Error saving bulk schedules:", error);
  } finally {
    bulkSaving.value = false;
  }
};

const normalizeSchedulePayload = (payload) => ({
  code: payload.code,
  name: payload.name,
  description: payload.description,
  head_employee_id: payload.head_employee_id,
  is_active: payload.is_active,
  time_in: payload.time_in || null,
  time_out: payload.time_out || null,
  grace_period_minutes:
    payload.grace_period_minutes === "" ||
    payload.grace_period_minutes === undefined
      ? null
      : payload.grace_period_minutes,
});

const normalizeBulkPayload = (payload) => ({
  time_in: payload.time_in || null,
  time_out: payload.time_out || null,
  grace_period_minutes:
    payload.grace_period_minutes === "" ||
    payload.grace_period_minutes === undefined
      ? null
      : payload.grace_period_minutes,
  exclude_project_ids: payload.exclude_project_ids || [],
});

const closeDialog = () => {
  dialog.value = false;
  selectedProject.value = null;
  formData.value = {
    time_in: null,
    time_out: null,
    grace_period_minutes: null,
  };
};

const closeBulkDialog = () => {
  bulkDialog.value = false;
  bulkFormData.value = {
    time_in: DEFAULT_TIME_IN,
    time_out: DEFAULT_TIME_OUT,
    grace_period_minutes: DEFAULT_GRACE,
    exclude_project_ids: [],
  };
};

const showSnackbar = (text, color = "success") => {
  snackbarText.value = text;
  snackbarColor.value = color;
  snackbar.value = true;
};

onMounted(() => {
  fetchProjects();
});
</script>

<style scoped lang="scss">
.attendance-settings-page {
  max-width: 1400px;
  margin: 0 auto;
}

.page-header {
  margin-bottom: 32px;
}

.header-content {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.back-button-wrapper {
  margin-bottom: 8px;
}

.back-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: none;
  background: transparent;
  color: #001f3d;
  font-weight: 600;
  cursor: pointer;
  padding: 6px 8px;
  border-radius: 8px;
  transition: all 0.2s ease;

  &:hover {
    background: rgba(0, 31, 61, 0.08);
  }
}

.header-main {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.action-buttons {
  display: flex;
  gap: 12px;
}

.action-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: none;
  border-radius: 10px;
  padding: 10px 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.action-btn-primary {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: #ffffff;
  box-shadow: 0 6px 16px rgba(237, 152, 95, 0.35);
}

.action-btn-primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 8px 20px rgba(237, 152, 95, 0.4);
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
  margin: 0 0 4px 0;
}

.page-subtitle {
  font-size: 14px;
  color: rgba(0, 31, 61, 0.6);
  margin: 0;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.stat-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  background: #ffffff;
  border-radius: 16px;
  box-shadow: 0 6px 20px rgba(0, 0, 0, 0.06);
}

.stat-icon {
  width: 40px;
  height: 40px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;

  &.total {
    background: rgba(237, 152, 95, 0.15);
    color: #ed985f;
  }

  &.active {
    background: rgba(76, 175, 80, 0.15);
    color: #4caf50;
  }

  &.schedule {
    background: rgba(33, 150, 243, 0.15);
    color: #2196f3;
  }

  &.default {
    background: rgba(156, 39, 176, 0.15);
    color: #9c27b0;
  }
}

.stat-content {
  .stat-label {
    font-size: 12px;
    color: rgba(0, 31, 61, 0.6);
  }

  .stat-value {
    font-size: 20px;
    font-weight: 700;
    color: #001f3d;

    &.success {
      color: #4caf50;
    }
  }
}

.content-card {
  background: #ffffff;
  border-radius: 20px;
  padding: 20px;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.08);
}

.table-controls {
  display: flex;
  gap: 12px;
  margin-bottom: 16px;
  flex-wrap: wrap;
}

.search-field {
  max-width: 320px;
}

.modern-table {
  border-radius: 12px;
}

.department-cell {
  display: flex;
  flex-direction: column;
}

.department-name {
  font-weight: 600;
  color: #001f3d;
}

.department-code {
  font-size: 12px;
  color: rgba(0, 31, 61, 0.6);
}

.designation-cell {
  color: rgba(0, 31, 61, 0.8);
}

.schedule-cell {
  font-weight: 600;
  color: #001f3d;
}

.action-buttons-cell {
  display: flex;
  align-items: center;
  gap: 6px;
}

.modern-dialog-card {
  border-radius: 20px;
  overflow: hidden;
}

.modern-dialog-header {
  background: linear-gradient(135deg, #001f3d 0%, #1a4a7c 100%);
  color: white;
}

.dialog-icon-badge {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: rgba(255, 255, 255, 0.15);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 12px;
}

.form-field-wrapper {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.form-label {
  font-weight: 600;
  color: #001f3d;
  display: flex;
  align-items: center;
  gap: 6px;
}

.info-note {
  margin-top: 12px;
  font-size: 12px;
  color: rgba(0, 31, 61, 0.6);
}
</style>
