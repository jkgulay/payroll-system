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
                Configure project schedules and employee schedule overrides for
                accurate attendance and payroll
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
            <button
              class="action-btn action-btn-secondary"
              @click="openEmployeeScheduleDialog"
            >
              <v-icon size="20">mdi-account-clock</v-icon>
              <span>Set Employee Schedule</span>
            </button>
            <button
              class="action-btn action-btn-tertiary"
              @click="openRecalculateDialog"
              v-if="isAdmin"
            >
              <v-icon size="20">mdi-calendar-refresh</v-icon>
              <span>Recalculate Range</span>
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
          <div class="stat-label">Total Projects</div>
          <div class="stat-value">{{ stats.total }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon active">
          <v-icon size="20">mdi-check-circle</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Active Projects</div>
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
          label="Search projects..."
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
                    Exclude Projects
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
                    placeholder="Select projects to exclude"
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

    <!-- Employee Schedule Dialog -->
    <v-dialog v-model="employeeDialog" max-width="760px" persistent>
      <v-card class="modern-dialog-card" elevation="24">
        <v-card-title class="modern-dialog-header">
          <div class="d-flex align-center w-100">
            <div class="dialog-icon-badge">
              <v-icon size="24">mdi-account-clock</v-icon>
            </div>
            <div class="flex-grow-1">
              <div class="text-h5 font-weight-bold">
                Employee Schedule Override
              </div>
              <div class="text-subtitle-2 text-white-70">
                Applies to selected employee(s) and takes priority over project
                schedule
              </div>
            </div>
            <v-btn
              icon
              variant="text"
              color="white"
              @click="closeEmployeeScheduleDialog"
              size="small"
              class="ml-2"
            >
              <v-icon>mdi-close</v-icon>
            </v-btn>
          </div>
        </v-card-title>

        <v-card-text class="pa-6">
          <v-form ref="employeeFormRef" @submit.prevent="saveEmployeeSchedule">
            <v-row>
              <v-col cols="12">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="primary"
                      >mdi-account-multiple</v-icon
                    >
                    Employees
                  </label>
                  <v-autocomplete
                    v-model="employeeScheduleForm.employee_ids"
                    :items="employeeScheduleOptions"
                    item-title="label"
                    item-value="id"
                    multiple
                    chips
                    closable-chips
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-account-search"
                    placeholder="Select one or multiple employees"
                  ></v-autocomplete>
                </div>
              </v-col>
            </v-row>

            <v-row>
              <v-col cols="12" md="4">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="primary">mdi-clock-in</v-icon>
                    Time In
                  </label>
                  <v-text-field
                    v-model="employeeScheduleForm.attendance_time_in"
                    type="time"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-clock-in"
                    color="primary"
                    :disabled="employeeScheduleForm.clear_override"
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
                    v-model="employeeScheduleForm.attendance_time_out"
                    type="time"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-clock-out"
                    color="primary"
                    :disabled="employeeScheduleForm.clear_override"
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
                    v-model.number="
                      employeeScheduleForm.attendance_grace_period_minutes
                    "
                    type="number"
                    min="0"
                    max="180"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-timer-outline"
                    color="primary"
                    :disabled="employeeScheduleForm.clear_override"
                    clearable
                  ></v-text-field>
                </div>
              </v-col>
            </v-row>

            <v-row>
              <v-col cols="12">
                <v-checkbox
                  v-model="employeeScheduleForm.clear_override"
                  label="Clear employee override and use project/default schedule"
                  color="primary"
                  hide-details
                ></v-checkbox>
              </v-col>
            </v-row>

            <div class="info-note">
              Employee schedule override is prioritized during attendance
              calculation and punch import.
            </div>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn
            variant="text"
            color="grey-darken-1"
            size="large"
            @click="closeEmployeeScheduleDialog"
            prepend-icon="mdi-close"
          >
            Cancel
          </v-btn>
          <v-btn
            color="primary"
            size="large"
            :loading="employeeScheduleSaving"
            @click="saveEmployeeSchedule"
            prepend-icon="mdi-check"
            class="px-6"
            elevation="2"
          >
            <span class="font-weight-bold">Apply</span>
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Recalculate Attendance Dialog -->
    <v-dialog v-model="recalcDialog" max-width="760px" persistent>
      <v-card class="modern-dialog-card" elevation="24">
        <v-card-title class="modern-dialog-header">
          <div class="d-flex align-center w-100">
            <div class="dialog-icon-badge">
              <v-icon size="24">mdi-calendar-refresh</v-icon>
            </div>
            <div class="flex-grow-1">
              <div class="text-h5 font-weight-bold">Recalculate Attendance</div>
              <div class="text-subtitle-2 text-white-70">
                Recompute attendance hours for selected employees and date range
              </div>
            </div>
            <v-btn
              icon
              variant="text"
              color="white"
              @click="closeRecalculateDialog"
              size="small"
              class="ml-2"
            >
              <v-icon>mdi-close</v-icon>
            </v-btn>
          </div>
        </v-card-title>

        <v-card-text class="pa-6">
          <v-form
            ref="recalcFormRef"
            @submit.prevent="runRecalculateAttendance"
          >
            <v-row>
              <v-col cols="12">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="primary"
                      >mdi-account-multiple</v-icon
                    >
                    Employees
                  </label>
                  <v-autocomplete
                    v-model="recalcForm.employee_ids"
                    :items="employeeScheduleOptions"
                    item-title="label"
                    item-value="id"
                    multiple
                    chips
                    closable-chips
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-account-search"
                    placeholder="Select employee(s) for attendance recalculation"
                  ></v-autocomplete>
                </div>
              </v-col>
            </v-row>

            <v-row>
              <v-col cols="12" md="6">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="primary"
                      >mdi-calendar-start</v-icon
                    >
                    Date From
                  </label>
                  <v-text-field
                    v-model="recalcForm.date_from"
                    type="date"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-calendar-start"
                    color="primary"
                  ></v-text-field>
                </div>
              </v-col>
              <v-col cols="12" md="6">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="primary"
                      >mdi-calendar-end</v-icon
                    >
                    Date To
                  </label>
                  <v-text-field
                    v-model="recalcForm.date_to"
                    type="date"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-calendar-end"
                    color="primary"
                  ></v-text-field>
                </div>
              </v-col>
            </v-row>

            <div class="info-note">
              Notes: This recalculates only records with both time in and time
              out. Draft payrolls that overlap the range can be reprocessed
              after review.
            </div>
          </v-form>

          <div v-if="recalcResult" class="recalc-result mt-5">
            <v-alert type="info" variant="tonal" class="mb-3">
              {{ recalcResult.message || "Attendance recalculation completed" }}
            </v-alert>

            <div class="recalc-summary-grid">
              <div class="recalc-summary-item">
                <span class="recalc-label">Records Found</span>
                <strong>{{
                  recalcResult.summary?.total_records_found ?? 0
                }}</strong>
              </div>
              <div class="recalc-summary-item">
                <span class="recalc-label">Recalculated</span>
                <strong>{{ recalcResult.summary?.recalculated ?? 0 }}</strong>
              </div>
              <div class="recalc-summary-item">
                <span class="recalc-label">Skipped Incomplete</span>
                <strong>{{
                  recalcResult.summary?.skipped_incomplete ?? 0
                }}</strong>
              </div>
              <div class="recalc-summary-item">
                <span class="recalc-label">Failed</span>
                <strong>{{ recalcResult.summary?.failed ?? 0 }}</strong>
              </div>
            </div>

            <div class="mt-4">
              <div class="recalc-list-title">Impacted Draft Payrolls</div>
              <div
                v-if="(recalcResult.impacted_payrolls?.draft || []).length"
                class="recalc-chip-wrap"
              >
                <v-chip
                  v-for="payroll in recalcResult.impacted_payrolls.draft"
                  :key="`draft-${payroll.id}`"
                  color="warning"
                  size="small"
                  variant="outlined"
                >
                  {{ payroll.payroll_number || `#${payroll.id}` }}
                </v-chip>
              </div>
              <div v-else class="recalc-empty">
                No draft payroll overlaps found.
              </div>
            </div>

            <div class="mt-4">
              <div class="recalc-list-title">Impacted Locked Payrolls</div>
              <div
                v-if="(recalcResult.impacted_payrolls?.locked || []).length"
                class="recalc-chip-wrap"
              >
                <v-chip
                  v-for="payroll in recalcResult.impacted_payrolls.locked"
                  :key="`locked-${payroll.id}`"
                  color="error"
                  size="small"
                  variant="outlined"
                >
                  {{ payroll.payroll_number || `#${payroll.id}` }}
                </v-chip>
              </div>
              <div v-else class="recalc-empty">
                No locked payroll overlaps found.
              </div>
            </div>
          </div>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn
            variant="text"
            color="grey-darken-1"
            size="large"
            @click="closeRecalculateDialog"
            prepend-icon="mdi-close"
          >
            Close
          </v-btn>
          <v-btn
            color="primary"
            size="large"
            :loading="recalcSaving"
            @click="runRecalculateAttendance"
            prepend-icon="mdi-refresh"
            class="px-6"
            elevation="2"
          >
            <span class="font-weight-bold">Run Recalculation</span>
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import api from "@/services/api";
import moduleAccessService from "@/services/moduleAccessService";
import { useAuthStore } from "@/stores/auth";
import { devLog } from "@/utils/devLog";

const DEFAULT_TIME_IN = "07:30";
const DEFAULT_TIME_OUT = "17:00";
const DEFAULT_GRACE = 3;

const authStore = useAuthStore();
const isAdmin = computed(() => authStore.userRole === "admin");
const isPayrollist = computed(() => authStore.userRole === "payrollist");

const loading = ref(false);
const saving = ref(false);
const bulkSaving = ref(false);
const employeeScheduleSaving = ref(false);
const recalcSaving = ref(false);
const dialog = ref(false);
const bulkDialog = ref(false);
const employeeDialog = ref(false);
const recalcDialog = ref(false);
const projects = ref([]);
const employeeScheduleOptions = ref([]);
const search = ref("");
const snackbar = ref(false);
const snackbarText = ref("");
const snackbarColor = ref("success");
const selectedProject = ref(null);
const formRef = ref(null);
const bulkFormRef = ref(null);
const employeeFormRef = ref(null);
const recalcFormRef = ref(null);
const recalcResult = ref(null);

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

const employeeScheduleForm = ref({
  employee_ids: [],
  attendance_time_in: DEFAULT_TIME_IN,
  attendance_time_out: DEFAULT_TIME_OUT,
  attendance_grace_period_minutes: DEFAULT_GRACE,
  clear_override: false,
});

const recalcForm = ref({
  employee_ids: [],
  date_from: null,
  date_to: null,
});

const headers = [
  { title: "Project", key: "department", sortable: true },
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
      (p.name && p.name.toLowerCase().includes(searchLower)) ||
      (p.code && p.code.toLowerCase().includes(searchLower)) ||
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
    showSnackbar("Failed to load projects", "error");
    devLog.error("Error fetching projects:", error);
  } finally {
    loading.value = false;
  }
};

const fetchEmployeeScheduleOptions = async () => {
  try {
    const response = await api.get("/employees/schedules", {
      params: { activity_status: "active" },
    });

    employeeScheduleOptions.value = (response.data || []).map((employee) => {
      const projectName = employee.project?.name || "No Project";
      const positionName = employee.position || "N/A";
      const sourceLabel =
        employee.schedule_source === "employee"
          ? "Employee Override"
          : "Project Schedule";

      return {
        id: employee.id,
        label: `${employee.employee_number} - ${employee.full_name} | ${positionName} | ${projectName} [${sourceLabel}]`,
      };
    });
  } catch (error) {
    showSnackbar("Failed to load employee list", "error");
    devLog.error("Error fetching employee schedule options:", error);
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

const openEmployeeScheduleDialog = async () => {
  if (employeeScheduleOptions.value.length === 0) {
    await fetchEmployeeScheduleOptions();
  }

  employeeScheduleForm.value = {
    employee_ids: [],
    attendance_time_in: DEFAULT_TIME_IN,
    attendance_time_out: DEFAULT_TIME_OUT,
    attendance_grace_period_minutes: DEFAULT_GRACE,
    clear_override: false,
  };

  employeeDialog.value = true;
};

const toDateInputValue = (dateValue) => {
  const year = dateValue.getFullYear();
  const month = String(dateValue.getMonth() + 1).padStart(2, "0");
  const day = String(dateValue.getDate()).padStart(2, "0");
  return `${year}-${month}-${day}`;
};

const openRecalculateDialog = async () => {
  if (!isAdmin.value) {
    showSnackbar("Only admin can run attendance recalculation", "warning");
    return;
  }

  if (employeeScheduleOptions.value.length === 0) {
    await fetchEmployeeScheduleOptions();
  }

  const today = new Date();
  const firstDayOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);

  recalcForm.value = {
    employee_ids: [],
    date_from: toDateInputValue(firstDayOfMonth),
    date_to: toDateInputValue(today),
  };
  recalcResult.value = null;
  recalcDialog.value = true;
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
    const schedulePayload = normalizeSchedulePayload(formData.value);

    if (isPayrollist.value) {
      await submitAttendanceSettingsRequest(
        "project_schedule_update",
        {
          project_id: selectedProject.value.id,
          project_name: selectedProject.value.name,
          schedule: schedulePayload,
        },
        `Schedule change request submitted for ${selectedProject.value.name}`,
      );
    } else {
      await api.put(
        `/projects/${selectedProject.value.id}/schedule`,
        schedulePayload,
      );
      showSnackbar("Schedule updated successfully", "success");
      await fetchProjects();
    }

    closeDialog();
  } catch (error) {
    const message =
      error.response?.data?.message || "Failed to update schedule";
    showSnackbar(message, "error");
    devLog.error("Error saving schedule:", error);
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

    if (isPayrollist.value) {
      const excludeIds = payload.exclude_project_ids || [];
      const targetProjectIds = projects.value
        .filter((project) => !excludeIds.includes(project.id))
        .map((project) => project.id);

      if (!targetProjectIds.length) {
        showSnackbar("No target projects available for request", "warning");
        return;
      }

      await submitAttendanceSettingsRequest(
        "project_bulk_schedule_update",
        {
          project_ids: targetProjectIds,
          excluded_project_ids: excludeIds,
          schedule: {
            time_in: payload.time_in,
            time_out: payload.time_out,
            grace_period_minutes: payload.grace_period_minutes,
          },
        },
        `Bulk schedule change request submitted for ${targetProjectIds.length} project(s)`,
      );
    } else {
      await api.post("/projects/bulk-schedule", payload);
      showSnackbar("Schedules updated successfully", "success");
      await fetchProjects();
    }

    closeBulkDialog();
  } catch (error) {
    const message =
      error.response?.data?.message || "Failed to update schedules";
    showSnackbar(message, "error");
    devLog.error("Error saving bulk schedules:", error);
  } finally {
    bulkSaving.value = false;
  }
};

const saveEmployeeSchedule = async () => {
  if (!employeeFormRef.value) return;

  const valid = await employeeFormRef.value.validate();
  if (!valid.valid) return;

  if (!employeeScheduleForm.value.employee_ids.length) {
    showSnackbar("Please select at least one employee", "warning");
    return;
  }

  employeeScheduleSaving.value = true;
  try {
    const payload = {
      employee_ids: employeeScheduleForm.value.employee_ids,
      attendance_time_in:
        employeeScheduleForm.value.clear_override === true
          ? null
          : employeeScheduleForm.value.attendance_time_in || null,
      attendance_time_out:
        employeeScheduleForm.value.clear_override === true
          ? null
          : employeeScheduleForm.value.attendance_time_out || null,
      attendance_grace_period_minutes:
        employeeScheduleForm.value.clear_override === true
          ? null
          : employeeScheduleForm.value.attendance_grace_period_minutes === "" ||
              employeeScheduleForm.value.attendance_grace_period_minutes ===
                undefined
            ? null
            : employeeScheduleForm.value.attendance_grace_period_minutes,
      clear_override: employeeScheduleForm.value.clear_override,
    };

    if (isPayrollist.value) {
      await submitAttendanceSettingsRequest(
        "employee_schedule_update",
        payload,
        `Employee schedule override request submitted for ${payload.employee_ids.length} employee(s)`,
      );
    } else {
      if (payload.employee_ids.length === 1) {
        await api.put(
          `/employees/${payload.employee_ids[0]}/schedule`,
          payload,
        );
      } else {
        await api.post("/employees/bulk-schedule", payload);
      }

      showSnackbar("Employee schedule updated successfully", "success");
      await fetchEmployeeScheduleOptions();
    }

    closeEmployeeScheduleDialog();
  } catch (error) {
    const message =
      error.response?.data?.message || "Failed to update employee schedule";
    showSnackbar(message, "error");
    devLog.error("Error saving employee schedule:", error);
  } finally {
    employeeScheduleSaving.value = false;
  }
};

const runRecalculateAttendance = async () => {
  if (!isAdmin.value) {
    showSnackbar("Only admin can run attendance recalculation", "warning");
    return;
  }

  if (!recalcForm.value.employee_ids.length) {
    showSnackbar("Please select at least one employee", "warning");
    return;
  }

  if (!recalcForm.value.date_from || !recalcForm.value.date_to) {
    showSnackbar("Please select both date range values", "warning");
    return;
  }

  if (recalcForm.value.date_to < recalcForm.value.date_from) {
    showSnackbar("Date To must be on or after Date From", "warning");
    return;
  }

  recalcSaving.value = true;
  try {
    const payload = {
      employee_ids: recalcForm.value.employee_ids,
      date_from: recalcForm.value.date_from,
      date_to: recalcForm.value.date_to,
    };

    const response = await api.post("/attendance/recalculate-range", payload);
    recalcResult.value = response.data;
    showSnackbar(
      response.data?.message || "Attendance recalculation completed",
      "success",
    );
  } catch (error) {
    const message =
      error.response?.data?.message || "Failed to recalculate attendance";
    showSnackbar(message, "error");
    devLog.error("Error recalculating attendance:", error);
  } finally {
    recalcSaving.value = false;
  }
};

const normalizeSchedulePayload = (payload) => ({
  time_in: payload.time_in || null,
  time_out: payload.time_out || null,
  grace_period_minutes:
    payload.grace_period_minutes === "" ||
    payload.grace_period_minutes === undefined
      ? null
      : payload.grace_period_minutes,
});

const submitAttendanceSettingsRequest = async (
  type,
  payload,
  successMessage,
) => {
  const typeLabel = {
    project_schedule_update: "project schedule update",
    project_bulk_schedule_update: "bulk project schedule update",
    employee_schedule_update: "employee schedule override update",
  };

  const reason = `Payrollist requested ${typeLabel[type] || "attendance settings change"}`;

  await moduleAccessService.submitRequest("attendance-settings", {
    reason,
    payload: {
      type,
      ...payload,
    },
  });

  showSnackbar(
    successMessage || "Request submitted. Waiting for admin approval.",
    "success",
  );
};

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

const closeEmployeeScheduleDialog = () => {
  employeeDialog.value = false;
  employeeScheduleForm.value = {
    employee_ids: [],
    attendance_time_in: DEFAULT_TIME_IN,
    attendance_time_out: DEFAULT_TIME_OUT,
    attendance_grace_period_minutes: DEFAULT_GRACE,
    clear_override: false,
  };
};

const closeRecalculateDialog = () => {
  recalcDialog.value = false;
  recalcForm.value = {
    employee_ids: [],
    date_from: null,
    date_to: null,
  };
  recalcResult.value = null;
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
  align-items: stretch;
  justify-content: flex-start;
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

.action-btn-secondary {
  background: #ffffff;
  color: #001f3d;
  border: 1px solid rgba(0, 31, 61, 0.2);
}

.action-btn-secondary:hover {
  background: rgba(0, 31, 61, 0.06);
  transform: translateY(-1px);
}

.action-btn-tertiary {
  background: #001f3d;
  color: #ffffff;
}

.action-btn-tertiary:hover {
  background: #0e365f;
  transform: translateY(-1px);
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

.recalc-result {
  background: rgba(0, 31, 61, 0.03);
  border: 1px solid rgba(0, 31, 61, 0.1);
  border-radius: 12px;
  padding: 14px;
}

.recalc-summary-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
  gap: 10px;
}

.recalc-summary-item {
  background: #ffffff;
  border: 1px solid rgba(0, 31, 61, 0.12);
  border-radius: 10px;
  padding: 10px 12px;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.recalc-label {
  font-size: 12px;
  color: rgba(0, 31, 61, 0.65);
}

.recalc-list-title {
  font-size: 13px;
  font-weight: 700;
  color: #001f3d;
  margin-bottom: 8px;
}

.recalc-chip-wrap {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.recalc-empty {
  font-size: 12px;
  color: rgba(0, 31, 61, 0.6);
}
</style>
