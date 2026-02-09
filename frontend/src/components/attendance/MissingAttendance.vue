<template>
  <div>
    <!-- Filters -->
    <v-card-text>
      <v-row>
        <v-col cols="12" md="3">
          <v-text-field
            v-model="selectedDate"
            label="Select Date"
            type="date"
            variant="outlined"
            density="compact"
            prepend-inner-icon="mdi-calendar"
            hide-details
          ></v-text-field>
        </v-col>
        <v-col cols="12" md="3">
          <v-select
            v-model="filterType"
            :items="filterOptions"
            label="Filter By Issue Type"
            variant="outlined"
            density="compact"
            hide-details
          ></v-select>
        </v-col>
        <v-col cols="12" md="3">
          <v-text-field
            v-model="searchEmployee"
            label="Search Employee"
            variant="outlined"
            density="compact"
            prepend-inner-icon="mdi-magnify"
            clearable
            hide-details
          ></v-text-field>
        </v-col>
        <v-col cols="12" md="3">
          <v-btn
            color="#ED985F"
            block
            @click="loadMissingAttendance"
            :loading="loading"
          >
            <v-icon start>mdi-magnify</v-icon>
            Search
          </v-btn>
        </v-col>
      </v-row>

      <!-- Summary Cards -->
      <v-row v-if="summary" class="mt-4">
        <v-col cols="12" md="4">
          <v-card flat class="text-center pa-2" color="blue-lighten-5">
            <div class="text-h5 font-weight-bold text-blue">
              {{ summary.total_attendance_records }}
            </div>
            <div class="text-caption text-medium-emphasis">
              Total Attendance Records
            </div>
          </v-card>
        </v-col>
        <v-col cols="12" md="4">
          <v-card flat class="text-center pa-2" color="red-lighten-5">
            <div class="text-h5 font-weight-bold text-red">
              {{ summary.employees_with_issues }}
            </div>
            <div class="text-caption text-medium-emphasis">
              Records With Missing Data
            </div>
          </v-card>
        </v-col>
        <v-col cols="12" md="4">
          <v-card flat class="text-center pa-2" color="green-lighten-5">
            <div class="text-h5 font-weight-bold text-green">
              {{
                summary.total_attendance_records - summary.employees_with_issues
              }}
            </div>
            <div class="text-caption text-medium-emphasis">
              Complete Records
            </div>
          </v-card>
        </v-col>
      </v-row>
    </v-card-text>

    <!-- Data Table -->
    <v-data-table
      :headers="headers"
      :items="filteredRecords"
      :loading="loading"
      :items-per-page="15"
      :items-per-page-options="[
        { value: 10, title: '10' },
        { value: 15, title: '15' },
        { value: 25, title: '25' },
        { value: 50, title: '50' },
        { value: -1, title: 'All' },
      ]"
      class="elevation-0"
    >
      <template v-slot:item.employee_number="{ item }">
        <div>
          <div class="font-weight-medium">{{ item.full_name }}</div>
          <div class="text-caption text-medium-emphasis">
            <v-chip size="x-small" variant="tonal">
              {{ item.employee_number }}
            </v-chip>
          </div>
        </div>
      </template>

      <template v-slot:item.position="{ item }">
        <div class="text-truncate" style="max-width: 120px">
          {{ item.position }}
        </div>
      </template>

      <template v-slot:item.department="{ item }">
        <div class="text-truncate" style="max-width: 130px">
          {{ item.department }}
        </div>
      </template>

      <template v-slot:item.issues="{ item }">
        <div class="d-flex flex-wrap gap-1">
          <v-chip
            v-for="(issue, idx) in item.issues"
            :key="idx"
            :color="getIssueColor(issue)"
            size="small"
            :prepend-icon="getIssueIcon(issue)"
          >
            {{ issue }}
          </v-chip>
        </div>
      </template>

      <template v-slot:item.time_in="{ item }">
        <v-chip
          v-if="item.attendance?.time_in"
          size="small"
          color="success"
          variant="tonal"
          prepend-icon="mdi-login"
        >
          {{ item.attendance.time_in }}
        </v-chip>
        <span v-else class="text-medium-emphasis">--:--</span>
      </template>

      <template v-slot:item.time_out="{ item }">
        <v-chip
          v-if="item.attendance?.time_out"
          size="small"
          color="error"
          variant="tonal"
          prepend-icon="mdi-logout"
        >
          {{ item.attendance.time_out }}
        </v-chip>
        <span v-else class="text-medium-emphasis text-red">Missing</span>
      </template>

      <template v-slot:item.break_start="{ item }">
        <v-chip
          v-if="item.attendance?.break_start"
          size="small"
          color="warning"
          variant="tonal"
          prepend-icon="mdi-coffee"
        >
          {{ item.attendance.break_start }}
        </v-chip>
        <span v-else class="text-medium-emphasis">--:--</span>
      </template>

      <template v-slot:item.break_end="{ item }">
        <v-chip
          v-if="item.attendance?.break_end"
          size="small"
          color="info"
          variant="tonal"
          prepend-icon="mdi-coffee-outline"
        >
          {{ item.attendance.break_end }}
        </v-chip>
        <span
          v-else-if="item.attendance?.break_start"
          class="text-medium-emphasis text-orange"
          >Missing</span
        >
        <span v-else class="text-medium-emphasis">--:--</span>
      </template>

      <template v-slot:item.ot_time_in="{ item }">
        <v-chip
          v-if="item.attendance?.ot_time_in"
          size="small"
          color="purple"
          variant="tonal"
          prepend-icon="mdi-clock-plus"
        >
          {{ item.attendance.ot_time_in }}
        </v-chip>
        <span v-else class="text-medium-emphasis">--:--</span>
      </template>

      <template v-slot:item.ot_time_out="{ item }">
        <v-chip
          v-if="item.attendance?.ot_time_out"
          size="small"
          color="deep-purple"
          variant="tonal"
          prepend-icon="mdi-clock-minus"
        >
          {{ item.attendance.ot_time_out }}
        </v-chip>
        <span
          v-else-if="item.attendance?.ot_time_in"
          class="text-medium-emphasis text-orange"
          >Missing</span
        >
        <span v-else class="text-medium-emphasis">--:--</span>
      </template>

      <template v-slot:item.actions="{ item }">
        <v-btn
          icon="mdi-pencil"
          size="small"
          variant="text"
          color="primary"
          @click="$emit('edit-attendance', item.attendance)"
        ></v-btn>
      </template>

      <template v-slot:no-data>
        <div class="text-center pa-6">
          <v-icon size="64" :color="selectedDate ? 'success' : 'grey'">
            {{ selectedDate ? "mdi-check-all" : "mdi-calendar-search" }}
          </v-icon>
          <p class="text-h6 mt-4">
            {{
              selectedDate
                ? "No missing attendance found!"
                : "Please select a date"
            }}
          </p>
          <p v-if="selectedDate" class="text-body-2 text-medium-emphasis">
            All attendance records have complete punch data for this date.
          </p>
        </div>
      </template>
    </v-data-table>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import attendanceService from "@/services/attendanceService";
import { useToast } from "vue-toastification";

const emit = defineEmits(["edit-attendance"]);
const toast = useToast();

const loading = ref(false);
const selectedDate = ref(getTodayDate());
const filterType = ref("all");
const searchEmployee = ref("");
const missingRecords = ref([]);
const summary = ref(null);

const filterOptions = [
  { title: "All Issues", value: "all" },
  { title: "Missing Time Out", value: "missing_timeout" },
  { title: "Missing Break Out", value: "missing_breakout" },
  { title: "Missing OT Time Out", value: "missing_ot_timeout" },
];

const headers = [
  { title: "Employee", key: "employee_number", sortable: true },
  { title: "Position", key: "position", sortable: true },
  { title: "Department", key: "department", sortable: true },
  { title: "Issues", key: "issues", sortable: false },
  { title: "Time In", key: "time_in", sortable: false },
  { title: "Time Out", key: "time_out", sortable: false },
  { title: "Break Start", key: "break_start", sortable: false },
  { title: "Break End", key: "break_end", sortable: false },
  { title: "OT In", key: "ot_time_in", sortable: false },
  { title: "OT Out", key: "ot_time_out", sortable: false },
  { title: "Actions", key: "actions", sortable: false, align: "center" },
];

function getTodayDate() {
  const today = new Date();
  return today.toISOString().split("T")[0];
}

// Computed: Filtered Records based on search
const filteredRecords = computed(() => {
  if (!searchEmployee.value) return missingRecords.value;

  const search = searchEmployee.value.toLowerCase();
  return missingRecords.value.filter((record) => {
    return (
      record.full_name?.toLowerCase().includes(search) ||
      record.employee_number?.toLowerCase().includes(search) ||
      record.position?.toLowerCase().includes(search) ||
      record.department?.toLowerCase().includes(search)
    );
  });
});

const loadMissingAttendance = async () => {
  if (!selectedDate.value) {
    toast.warning("Please select a date");
    return;
  }

  loading.value = true;
  try {
    const response = await attendanceService.getMissingAttendance({
      date: selectedDate.value,
      type: filterType.value,
    });

    missingRecords.value = response.missing_records || [];
    summary.value = {
      total_attendance_records: response.total_attendance_records,
      employees_with_issues: response.employees_with_issues,
    };
  } catch (error) {
    console.error("Failed to load missing attendance:", error);
    toast.error("Failed to load missing attendance data");
    missingRecords.value = [];
    summary.value = null;
  } finally {
    loading.value = false;
  }
};

const getIssueColor = (issue) => {
  if (issue.includes("Missing time out")) return "error";
  if (issue.includes("Missing break out")) return "warning";
  if (issue.includes("Missing OT")) return "orange";
  return "grey";
};

const getIssueIcon = (issue) => {
  if (issue.includes("Missing time out")) return "mdi-logout-variant";
  if (issue.includes("Missing break out")) return "mdi-coffee-off";
  if (issue.includes("Missing OT")) return "mdi-clock-alert";
  return "mdi-alert-circle";
};

onMounted(() => {
  loadMissingAttendance();
});
</script>

<style scoped>
.summary-card {
  text-align: center;
  min-height: 100px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.gap-1 {
  gap: 4px;
}
</style>
