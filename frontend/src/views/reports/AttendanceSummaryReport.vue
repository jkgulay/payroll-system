<template>
  <div>
    <v-row class="mb-4">
      <v-col>
        <v-btn icon @click="$router.back()" size="small" class="mr-2">
          <v-icon>mdi-arrow-left</v-icon>
        </v-btn>
        <span class="text-h4 font-weight-bold">Attendance Summary Report</span>
      </v-col>
    </v-row>

    <!-- Filters Card -->
    <v-card class="mb-4">
      <v-card-text>
        <v-row>
          <v-col cols="12" md="4">
            <v-text-field
              v-model="filters.date_from"
              label="Date From"
              type="date"
              variant="outlined"
              density="comfortable"
              hide-details
            />
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field
              v-model="filters.date_to"
              label="Date To"
              type="date"
              variant="outlined"
              density="comfortable"
              hide-details
            />
          </v-col>
          <v-col cols="12" md="4">
            <v-autocomplete
              v-model="filters.employee_id"
              :items="employees"
              item-title="full_name"
              item-value="id"
              label="Employee (Optional)"
              variant="outlined"
              density="comfortable"
              clearable
              hide-details
            />
          </v-col>
        </v-row>
        <v-row class="mt-2">
          <v-col>
            <v-btn
              color="primary"
              prepend-icon="mdi-magnify"
              :loading="loading"
              @click="fetchSummary"
            >
              Generate Report
            </v-btn>
            <v-btn
              color="success"
              prepend-icon="mdi-microsoft-excel"
              class="ml-2"
              :disabled="!summary"
              :loading="exporting"
              @click="exportToExcel"
            >
              Export to Excel
            </v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Summary Cards -->
    <v-row v-if="summary" class="mb-4">
      <v-col cols="12" sm="6" md="3">
        <v-card>
          <v-card-text>
            <div class="text-overline">Total Records</div>
            <div class="text-h4 font-weight-bold primary--text">
              {{ summary.total_records }}
            </div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card>
          <v-card-text>
            <div class="text-overline">Present</div>
            <div class="text-h4 font-weight-bold success--text">
              {{ summary.total_present }}
            </div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card>
          <v-card-text>
            <div class="text-overline">Absent</div>
            <div class="text-h4 font-weight-bold error--text">
              {{ summary.total_absent }}
            </div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="3">
        <v-card>
          <v-card-text>
            <div class="text-overline">Late</div>
            <div class="text-h4 font-weight-bold warning--text">
              {{ summary.total_late }}
            </div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="4">
        <v-card>
          <v-card-text>
            <div class="text-overline">Total Hours Worked</div>
            <div class="text-h4 font-weight-bold">
              {{ formatHoursDisplay(summary.total_hours_worked) }}
            </div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="4">
        <v-card>
          <v-card-text>
            <div class="text-overline">Overtime Hours</div>
            <div class="text-h4 font-weight-bold">
              {{ Math.floor(summary.total_overtime_hours || 0) }}h
            </div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" sm="6" md="4">
        <v-card>
          <v-card-text>
            <div class="text-overline">Pending Approval</div>
            <div class="text-h4 font-weight-bold info--text">
              {{ summary.pending_approval }}
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- By Employee Summary Table -->
    <v-card v-if="summary && summary.by_employee">
      <v-card-title>
        <v-icon class="mr-2">mdi-account-group</v-icon>
        Summary by Employee
      </v-card-title>
      <v-card-text>
        <v-data-table
          :headers="employeeHeaders"
          :items="summary.by_employee"
          :search="search"
          class="elevation-1"
        >
          <template v-slot:top>
            <v-text-field
              v-model="search"
              label="Search employees..."
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="comfortable"
              hide-details
              clearable
              class="mb-4"
            />
          </template>
          <template v-slot:item.employee="{ item }">
            <div>
              <div class="font-weight-medium">{{ item.employee.full_name }}</div>
              <div class="text-caption text-medium-emphasis">
                {{ item.employee.employee_number }}
              </div>
            </div>
          </template>
          <template v-slot:item.total_hours="{ item }">
            {{ item.total_hours?.toFixed(2) || 0 }}
          </template>
          <template v-slot:item.attendance_rate="{ item }">
            <v-chip
              :color="getAttendanceRateColor(item)"
              size="small"
            >
              {{ calculateAttendanceRate(item) }}%
            </v-chip>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <!-- No Data Message -->
    <v-card v-if="!loading && !summary" class="text-center pa-8">
      <v-icon size="64" color="grey-lighten-1">mdi-chart-box-outline</v-icon>
      <p class="text-h6 mt-4">No data available</p>
      <p class="text-body-2 text-medium-emphasis">
        Select a date range and click "Generate Report" to view the attendance summary
      </p>
    </v-card>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useToast } from "vue-toastification";
import api from "@/services/api";
import { devLog } from "@/utils/devLog";

const toast = useToast();

const loading = ref(false);
const exporting = ref(false);
const summary = ref(null);
const employees = ref([]);
const search = ref("");

const filters = ref({
  date_from: getDefaultDateFrom(),
  date_to: getDefaultDateTo(),
  employee_id: null,
});

const employeeHeaders = [
  { title: "Employee", key: "employee", sortable: true },
  { title: "Present", key: "total_present", sortable: true },
  { title: "Absent", key: "total_absent", sortable: true },
  { title: "Total Hours", key: "total_hours", sortable: true },
  { title: "Attendance Rate", key: "attendance_rate", sortable: true },
];

function getDefaultDateFrom() {
  const date = new Date();
  date.setDate(1); // First day of current month
  return date.toISOString().split("T")[0];
}

function getDefaultDateTo() {
  return new Date().toISOString().split("T")[0];
}

function formatHoursDisplay(hours) {
  if (!hours || hours <= 0) return "0h 0m";
  
  const totalMinutes = Math.round(hours * 60);
  const hrs = Math.floor(totalMinutes / 60);
  const mins = totalMinutes % 60;
  
  if (hrs === 0) {
    return `${mins}m`;
  } else if (mins === 0) {
    return `${hrs}h`;
  } else {
    return `${hrs}h ${mins}m`;
  }
}

async function fetchSummary() {
  if (!filters.value.date_from || !filters.value.date_to) {
    toast.error("Please select both date from and date to");
    return;
  }

  loading.value = true;
  try {
    const params = {
      date_from: filters.value.date_from,
      date_to: filters.value.date_to,
    };

    if (filters.value.employee_id) {
      params.employee_id = filters.value.employee_id;
    }

    const response = await api.get("/attendance/summary", { params });
    summary.value = response.data;
    toast.success("Report generated successfully");
  } catch (error) {
    devLog.error("Error fetching attendance summary:", error);
    toast.error("Failed to generate report");
  } finally {
    loading.value = false;
  }
}

async function exportToExcel() {
  exporting.value = true;
  try {
    const params = {
      date_from: filters.value.date_from,
      date_to: filters.value.date_to,
    };

    if (filters.value.employee_id) {
      params.employee_id = filters.value.employee_id;
    }

    const response = await api.get("/attendance/summary/export", {
      params,
      responseType: "blob",
    });

    // Create download link
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute(
      "download",
      `attendance_summary_${filters.value.date_from}_to_${filters.value.date_to}.xlsx`
    );
    document.body.appendChild(link);
    link.click();
    link.remove();

    toast.success("Report exported successfully");
  } catch (error) {
    devLog.error("Error exporting attendance summary:", error);
    toast.error("Failed to export report");
  } finally {
    exporting.value = false;
  }
}

async function fetchEmployees() {
  try {
    const response = await api.get("/employees", {
      params: { per_page: 1000, status: "active" },
    });
    employees.value = response.data.data || response.data;
  } catch (error) {
    devLog.error("Error fetching employees:", error);
  }
}

function calculateAttendanceRate(item) {
  const total = item.total_present + item.total_absent;
  if (total === 0) return 0;
  return ((item.total_present / total) * 100).toFixed(1);
}

function getAttendanceRateColor(item) {
  const rate = calculateAttendanceRate(item);
  if (rate >= 95) return "success";
  if (rate >= 85) return "info";
  if (rate >= 75) return "warning";
  return "error";
}

onMounted(() => {
  fetchEmployees();
});
</script>
