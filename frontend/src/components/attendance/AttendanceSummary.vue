<template>
  <v-card-text>
    <v-row>
      <v-col cols="12" md="6">
        <v-card>
          <v-card-title class="text-h6">Date Range</v-card-title>
          <v-card-text>
            <v-row>
              <v-col cols="6">
                <v-text-field
                  v-model="filters.date_from"
                  label="From"
                  type="date"
                  variant="outlined"
                  density="compact"
                ></v-text-field>
              </v-col>
              <v-col cols="6">
                <v-text-field
                  v-model="filters.date_to"
                  label="To"
                  type="date"
                  variant="outlined"
                  density="compact"
                ></v-text-field>
              </v-col>
              <v-col cols="12">
                <v-autocomplete
                  v-model="filters.employee_id"
                  :items="employees"
                  item-title="full_name"
                  item-value="id"
                  label="Filter by Employee (Optional)"
                  variant="outlined"
                  density="compact"
                  clearable
                  :loading="loadingEmployees"
                ></v-autocomplete>
              </v-col>
              <v-col cols="12">
                <v-btn
                  color="primary"
                  block
                  @click="loadSummary"
                  :loading="loading"
                >
                  Generate Summary
                </v-btn>
              </v-col>
              <v-col cols="12" v-if="summary">
                <v-btn
                  color="success"
                  block
                  @click="exportToExcel"
                  :loading="exporting"
                  prepend-icon="mdi-file-excel"
                >
                  Export to Excel
                </v-btn>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="6">
        <v-card>
          <v-card-title class="text-h6">Quick Filters</v-card-title>
          <v-card-text>
            <v-btn block class="mb-2" @click="setToday">Today</v-btn>
            <v-btn block class="mb-2" @click="setThisWeek">This Week</v-btn>
            <v-btn block class="mb-2" @click="setThisMonth">This Month</v-btn>
            <v-btn block @click="setLastMonth">Last Month</v-btn>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <v-row v-if="summary" class="mt-4">
      <v-col cols="12">
        <v-card>
          <v-card-title class="text-h6">
            Summary: {{ formatDate(summary.date_from) }} -
            {{ formatDate(summary.date_to) }}
          </v-card-title>
          <v-divider></v-divider>

          <v-card-text>
            <v-row>
              <v-col cols="6" md="3">
                <v-card color="info" variant="tonal">
                  <v-card-text class="text-center">
                    <div class="text-h4">{{ summary.total_records }}</div>
                    <div class="text-caption">Total Records</div>
                  </v-card-text>
                </v-card>
              </v-col>

              <v-col cols="6" md="3">
                <v-card color="success" variant="tonal">
                  <v-card-text class="text-center">
                    <div class="text-h4">{{ summary.total_present }}</div>
                    <div class="text-caption">Present</div>
                  </v-card-text>
                </v-card>
              </v-col>

              <v-col cols="6" md="3">
                <v-card color="error" variant="tonal">
                  <v-card-text class="text-center">
                    <div class="text-h4">{{ summary.total_absent }}</div>
                    <div class="text-caption">Absent</div>
                  </v-card-text>
                </v-card>
              </v-col>

              <v-col cols="6" md="3">
                <v-card color="warning" variant="tonal">
                  <v-card-text class="text-center">
                    <div class="text-h4">{{ summary.total_late }}</div>
                    <div class="text-caption">Late</div>
                  </v-card-text>
                </v-card>
              </v-col>

              <v-col cols="6" md="3">
                <v-card color="purple" variant="tonal">
                  <v-card-text class="text-center">
                    <div class="text-h4">{{ summary.total_on_leave }}</div>
                    <div class="text-caption">On Leave</div>
                  </v-card-text>
                </v-card>
              </v-col>

              <v-col cols="6" md="3">
                <v-card color="blue" variant="tonal">
                  <v-card-text class="text-center">
                    <div class="text-h4">
                      {{ summary.total_hours_worked?.toFixed(1) }}
                    </div>
                    <div class="text-caption">Total Hours</div>
                  </v-card-text>
                </v-card>
              </v-col>

              <v-col cols="6" md="3">
                <v-card color="teal" variant="tonal">
                  <v-card-text class="text-center">
                    <div class="text-h4">
                      {{ summary.total_overtime_hours?.toFixed(1) }}
                    </div>
                    <div class="text-caption">Overtime Hours</div>
                  </v-card-text>
                </v-card>
              </v-col>

              <v-col cols="6" md="3">
                <v-card color="orange" variant="tonal">
                  <v-card-text class="text-center">
                    <div class="text-h4">
                      {{ summary.pending_approval || 0 }}
                    </div>
                    <div class="text-caption">Pending Approval</div>
                  </v-card-text>
                </v-card>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- By Employee Table -->
      <v-col
        cols="12"
        v-if="summary.by_employee && summary.by_employee.length > 0"
      >
        <v-card>
          <v-card-title class="text-h6">By Employee</v-card-title>
          <v-divider></v-divider>
          <v-data-table
            :headers="employeeHeaders"
            :items="summary.by_employee"
            :items-per-page="10"
            class="elevation-0"
          >
            <template v-slot:item.employee="{ item }">
              {{ item.employee.full_name }}
            </template>
            <template v-slot:item.total_hours="{ item }">
              {{ item.total_hours?.toFixed(1) }}h
            </template>
          </v-data-table>
        </v-card>
      </v-col>
    </v-row>

    <v-row v-else class="mt-4">
      <v-col cols="12">
        <v-card>
          <v-card-text class="text-center pa-12">
            <v-icon size="64" color="info">mdi-chart-bar</v-icon>
            <p class="text-h6 mt-4">
              Select date range and click "Generate Summary"
            </p>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </v-card-text>
</template>

<script setup>
import { ref, reactive, onMounted, onUnmounted } from "vue";
import attendanceService from "@/services/attendanceService";
import api from "@/services/api";
import { useToast } from "vue-toastification";
import { onAttendanceUpdate } from "@/stores/attendance";

const toast = useToast();

const loading = ref(false);
const loadingEmployees = ref(false);
const exporting = ref(false);
const summary = ref(null);
const employees = ref([]);

const filters = reactive({
  date_from: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000)
    .toISOString()
    .split("T")[0],
  date_to: new Date().toISOString().split("T")[0],
  employee_id: null,
});

const employeeHeaders = [
  { title: "Employee", key: "employee", sortable: false },
  { title: "Present", key: "total_present" },
  { title: "Absent", key: "total_absent" },
  { title: "Total Hours", key: "total_hours" },
];

const loadSummary = async () => {
  loading.value = true;
  try {
    summary.value = await attendanceService.getSummary(filters);
  } catch (error) {
    toast.error("Failed to load summary");
  } finally {
    loading.value = false;
  }
};

const loadEmployees = async () => {
  loadingEmployees.value = true;
  try {
    const response = await api.get("/employees");
    employees.value = response.data.data || response.data || [];
  } catch (error) {
    toast.error("Failed to load employees");
  } finally {
    loadingEmployees.value = false;
  }
};

const formatDate = (date) => {
  return new Date(date).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

const setToday = () => {
  const today = new Date().toISOString().split("T")[0];
  filters.date_from = today;
  filters.date_to = today;
};

const setThisWeek = () => {
  const today = new Date();
  const firstDay = new Date(today.setDate(today.getDate() - today.getDay()));
  const lastDay = new Date(today.setDate(today.getDate() - today.getDay() + 6));
  filters.date_from = firstDay.toISOString().split("T")[0];
  filters.date_to = lastDay.toISOString().split("T")[0];
};

const setThisMonth = () => {
  const today = new Date();
  const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
  const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);
  filters.date_from = firstDay.toISOString().split("T")[0];
  filters.date_to = lastDay.toISOString().split("T")[0];
};

const setLastMonth = () => {
  const today = new Date();
  const firstDay = new Date(today.getFullYear(), today.getMonth() - 1, 1);
  const lastDay = new Date(today.getFullYear(), today.getMonth(), 0);
  filters.date_from = firstDay.toISOString().split("T")[0];
  filters.date_to = lastDay.toISOString().split("T")[0];
};
const exportToExcel = async () => {
  exporting.value = true;
  try {
    const response = await api.get("/attendance/summary/export", {
      params: filters,
      responseType: "blob",
    });

    const blob = new Blob([response.data], {
      type: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `attendance_summary_${filters.date_from}_to_${filters.date_to}.xlsx`;
    link.click();
    window.URL.revokeObjectURL(url);

    toast.success("Attendance summary exported successfully");
  } catch (error) {
    console.error("Export error:", error);
    toast.error("Failed to export attendance summary");
  } finally {
    exporting.value = false;
  }
};

let unsubscribeAttendance = null;

onMounted(() => {
  loadEmployees();

  // Listen for attendance updates to refresh summary if it's loaded
  unsubscribeAttendance = onAttendanceUpdate(() => {
    if (summary.value) {
      loadSummary();
    }
  });
});

onUnmounted(() => {
  if (unsubscribeAttendance) {
    unsubscribeAttendance();
  }
});
</script>
