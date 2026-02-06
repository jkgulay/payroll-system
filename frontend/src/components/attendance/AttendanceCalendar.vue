<template>
  <v-card-text>
    <v-row>
      <v-col cols="12" md="3">
        <v-card>
          <v-card-title class="text-h6">
            <v-icon class="mr-2">mdi-filter</v-icon>
            Filter by Employee
          </v-card-title>
          <v-card-text>
            <v-autocomplete
              v-model="selectedEmployeeId"
              :items="employees"
              item-title="full_name"
              item-value="id"
              label="Search employee..."
              variant="outlined"
              density="comfortable"
              clearable
              :loading="loadingEmployees"
              @update:model-value="loadMonthData"
              prepend-inner-icon="mdi-account-search"
              hide-details
            >
              <template v-slot:item="{ item }">
                <v-list-item
                  :title="item.raw.full_name"
                  :subtitle="item.raw.employee_number"
                  :value="item.value"
                >
                  <template v-slot:prepend>
                    <v-avatar size="32" color="primary" class="text-white">
                      {{ item.raw.full_name?.charAt(0) }}
                    </v-avatar>
                  </template>
                </v-list-item>
              </template>
            </v-autocomplete>

            <v-divider class="my-4"></v-divider>

            <div class="text-center">
              <div class="text-h4 font-weight-bold text-primary">
                {{ totalEmployees }}
              </div>
              <div class="text-caption text-medium-emphasis">
                Total Employees
              </div>
            </div>

            <v-divider class="my-4"></v-divider>

            <div class="text-center">
              <div class="text-h4 font-weight-bold">
                {{ attendanceData.length }}
              </div>
              <div class="text-caption text-medium-emphasis">
                Records This Month
              </div>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="9">
        <v-card>
          <v-card-title>
            <v-row align="center">
              <v-col>
                <span class="text-h6">
                  {{ currentMonthYear }}
                </span>
              </v-col>
              <v-col cols="auto">
                <v-btn
                  icon="mdi-chevron-left"
                  @click="previousMonth"
                  variant="text"
                  :loading="loading"
                ></v-btn>
                <v-btn
                  icon="mdi-calendar-today"
                  @click="goToToday"
                  variant="text"
                  :loading="loading"
                ></v-btn>
                <v-btn
                  icon="mdi-chevron-right"
                  @click="nextMonth"
                  variant="text"
                  :loading="loading"
                ></v-btn>
              </v-col>
            </v-row>
          </v-card-title>

          <!-- Loading Progress Bar -->
          <v-progress-linear
            v-if="loading"
            indeterminate
            color="primary"
            height="3"
          ></v-progress-linear>

          <v-card-text :class="{ 'calendar-loading': loading }">
            <!-- Loading Overlay -->
            <div v-if="loading" class="calendar-loading-overlay">
              <v-progress-circular
                indeterminate
                color="primary"
                size="64"
              ></v-progress-circular>
              <p class="mt-4 text-subtitle-1">Loading attendance data...</p>
            </div>

            <v-data-table
              :headers="calendarHeaders"
              :items="calendarDays"
              :loading="loading"
              hide-default-footer
              :items-per-page="-1"
              class="attendance-calendar"
            >
              <template v-slot:item="{ item }">
                <tr>
                  <td
                    v-for="day in item"
                    :key="day.date"
                    :class="getDayClass(day)"
                    @click="handleDayClick(day)"
                    style="cursor: pointer; height: 80px; vertical-align: top"
                  >
                    <div class="pa-2">
                      <div class="text-caption font-weight-bold">
                        {{ day.dayNumber }}
                      </div>

                      <!-- Daily Summary -->
                      <div
                        v-if="day.records.length > 0"
                        class="summary-box mb-1"
                      >
                        <div class="summary-line">
                          <strong
                            >{{ day.summary.present }}/{{
                              day.summary.total
                            }}</strong
                          >
                          Present
                        </div>
                        <div
                          v-if="day.summary.absent > 0"
                          class="summary-line text-error"
                        >
                          <strong>{{ day.summary.absent }}</strong> Absent
                        </div>
                        <div
                          v-if="day.summary.late > 0"
                          class="summary-line text-warning"
                        >
                          <strong>{{ day.summary.late }}</strong> Late
                        </div>
                      </div>

                      <div v-if="day.records.length > 0" class="mt-1">
                        <v-chip
                          v-for="record in day.records.slice(0, 2)"
                          :key="record.id"
                          :color="getStatusColor(record.status)"
                          size="x-small"
                          class="mb-1"
                          @click.stop="$emit('record-click', record)"
                        >
                          {{ record.employee.full_name.split(" ")[0] }}
                        </v-chip>
                        <v-chip
                          v-if="day.records.length > 2"
                          size="x-small"
                          color="grey"
                          class="mb-1"
                          @click.stop="showDayDetails(day)"
                        >
                          +{{ day.records.length - 2 }} more
                        </v-chip>
                      </div>
                    </div>
                  </td>
                </tr>
              </template>
            </v-data-table>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Day Details Dialog -->
    <v-dialog v-model="dayDetailsDialog" max-width="800">
      <v-card>
        <v-card-title>
          Attendance for {{ formatDate(selectedDayDate) }}
        </v-card-title>
        <v-divider></v-divider>
        <v-card-text>
          <v-data-table
            :headers="dayDetailsHeaders"
            :items="selectedDayRecords"
            :items-per-page="10"
            density="compact"
          >
            <template v-slot:item.employee="{ item }">
              <div>
                <div class="font-weight-medium">
                  {{ item.employee.full_name }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ item.employee.employee_number }}
                </div>
              </div>
            </template>

            <template v-slot:item.time_in="{ item }">
              {{ item.time_in || "--:--" }}
            </template>

            <template v-slot:item.time_out="{ item }">
              {{ item.actual_time_out || "--:--" }}
            </template>

            <template v-slot:item.hours="{ item }">
              {{ item.regular_hours || 0 }}h
              <span v-if="item.overtime_hours >= 1" class="text-warning">
                +{{ Math.floor(item.overtime_hours) }}h
              </span>
            </template>

            <template v-slot:item.status="{ item }">
              <v-chip :color="getStatusColor(item.status)" size="small">
                {{ item.status }}
              </v-chip>
            </template>

            <template v-slot:item.actions="{ item }">
              <v-btn
                icon="mdi-pencil"
                size="small"
                variant="text"
                @click="$emit('record-click', item)"
              ></v-btn>
            </template>
          </v-data-table>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn @click="dayDetailsDialog = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-card-text>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import api from "@/services/api";
import attendanceService from "@/services/attendanceService";
import { useToast } from "vue-toastification";
import { onAttendanceUpdate } from "@/stores/attendance";

const emit = defineEmits(["date-click", "record-click"]);
const toast = useToast();

const loading = ref(false);
const selectedDate = ref(new Date());
const attendanceData = ref([]);
const selectedEmployeeId = ref(null);
const employees = ref([]);
const loadingEmployees = ref(false);
const totalEmployees = ref(0);
const dayDetailsDialog = ref(false);
const selectedDayDate = ref(null);
const selectedDayRecords = ref([]);

const dayDetailsHeaders = [
  { title: "Employee", key: "employee", sortable: false },
  { title: "Time In", key: "time_in" },
  { title: "Time Out", key: "time_out" },
  { title: "Hours", key: "hours", sortable: false },
  { title: "Status", key: "status" },
  { title: "Actions", key: "actions", sortable: false },
];

const calendarHeaders = [
  { title: "Sun", value: "sun", sortable: false },
  { title: "Mon", value: "mon", sortable: false },
  { title: "Tue", value: "tue", sortable: false },
  { title: "Wed", value: "wed", sortable: false },
  { title: "Thu", value: "thu", sortable: false },
  { title: "Fri", value: "fri", sortable: false },
  { title: "Sat", value: "sat", sortable: false },
];

const currentMonthYear = computed(() => {
  return selectedDate.value.toLocaleDateString("en-US", {
    month: "long",
    year: "numeric",
  });
});

const calendarDays = computed(() => {
  const year = selectedDate.value.getFullYear();
  const month = selectedDate.value.getMonth();

  const firstDay = new Date(year, month, 1);
  const lastDay = new Date(year, month + 1, 0);
  const startDay = firstDay.getDay();
  const totalDays = lastDay.getDate();

  const days = [];
  let week = [];

  // Previous month padding
  for (let i = 0; i < startDay; i++) {
    week.push({
      date: null,
      dayNumber: "",
      isCurrentMonth: false,
      records: [],
      summary: { total: 0, present: 0, absent: 0, late: 0, onLeave: 0 },
    });
  }

  // Current month days
  for (let day = 1; day <= totalDays; day++) {
    const dateStr = `${year}-${String(month + 1).padStart(2, "0")}-${String(
      day,
    ).padStart(2, "0")}`;

    // Filter records for this day - handle both date formats (YYYY-MM-DD and timestamp)
    const dayRecords = attendanceData.value.filter((a) => {
      const recordDate = a.attendance_date.split("T")[0]; // Extract date part from timestamp
      return recordDate === dateStr;
    });

    const summary = {
      total: selectedEmployeeId.value
        ? dayRecords.length
        : totalEmployees.value, // If filtering by employee, show their records; otherwise show total employees
      present: dayRecords.filter((r) => r.status === "present").length,
      absent: dayRecords.filter((r) => r.status === "absent").length,
      late: dayRecords.filter((r) => r.status === "late").length,
      onLeave: dayRecords.filter((r) => r.status === "on_leave").length,
    };

    // Debug logging for first few days
    if (day <= 3 && dayRecords.length > 0) {
      console.log(`Day ${day} (${dateStr}):`, {
        totalRecords: dayRecords.length,
        statuses: {
          present: summary.present,
          absent: summary.absent,
          late: summary.late,
          onLeave: summary.onLeave,
        },
        sampleRecords: dayRecords.slice(0, 2).map((r) => ({
          id: r.id,
          employee: r.employee?.full_name,
          status: r.status,
        })),
      });
    }

    week.push({
      date: dateStr,
      dayNumber: day,
      isCurrentMonth: true,
      isToday: dateStr === new Date().toISOString().split("T")[0],
      records: dayRecords,
      summary: summary,
    });

    if (week.length === 7) {
      days.push([...week]);
      week = [];
    }
  }

  // Next month padding
  if (week.length > 0) {
    while (week.length < 7) {
      week.push({
        date: null,
        dayNumber: "",
        isCurrentMonth: false,
        records: [],
        summary: { total: 0, present: 0, absent: 0, late: 0, onLeave: 0 },
      });
    }
    days.push(week);
  }

  return days;
});

const loadMonthData = async () => {
  loading.value = true;
  try {
    const year = selectedDate.value.getFullYear();
    const month = selectedDate.value.getMonth();
    const firstDay = new Date(year, month, 1).toISOString().split("T")[0];
    const lastDay = new Date(year, month + 1, 0).toISOString().split("T")[0];

    const params = {
      date_from: firstDay,
      date_to: lastDay,
      per_page: 10000, // Request all attendance records for the month
    };

    if (selectedEmployeeId.value) {
      params.employee_id = selectedEmployeeId.value;
    }

    const response = await attendanceService.getAttendance(params);
    // Handle both paginated (response.data.data) and non-paginated (response.data) responses
    attendanceData.value = response.data?.data || response.data || [];

    // Debug: Log what we received
    console.log("Calendar loaded attendance records:", {
      count: attendanceData.value.length,
      sample: attendanceData.value.slice(0, 3),
      params,
    });
  } catch (error) {
    console.error("Calendar load error:", error);
    toast.error("Failed to load calendar data");
  } finally {
    loading.value = false;
  }
};

const loadEmployees = async () => {
  loadingEmployees.value = true;
  try {
    // Fetch all employees without pagination by requesting a large per_page value
    const response = await api.get("/employees", {
      params: {
        per_page: 10000, // Request all employees (no activity_status filter to include all)
      },
    });
    const employeeList = response.data.data || response.data || [];
    employees.value = employeeList;
    totalEmployees.value = employeeList.length; // Store total count for calendar summary
  } catch (error) {
    toast.error("Failed to load employees");
  } finally {
    loadingEmployees.value = false;
  }
};

const getDayClass = (day) => {
  const classes = ["text-center"];
  if (!day.isCurrentMonth) classes.push("text-disabled");
  if (day.isToday) classes.push("bg-blue-lighten-5");
  return classes.join(" ");
};

const getStatusColor = (status) => {
  const colors = {
    present: "success",
    absent: "error",
    late: "warning",
    half_day: "info",
    on_leave: "purple",
  };
  return colors[status] || "grey";
};

const handleDayClick = (day) => {
  if (day.date && day.records.length > 0) {
    // If day has records, show the details dialog
    showDayDetails(day);
  } else if (day.date) {
    // If day has no records, emit date-click to create new attendance
    emit("date-click", day.date);
  }
};

const onDatePickerChange = (date) => {
  // No longer needed - removed date picker
  loadMonthData();
};

const previousMonth = () => {
  const newDate = new Date(selectedDate.value);
  newDate.setMonth(newDate.getMonth() - 1);
  selectedDate.value = newDate;
  loadMonthData();
};

const nextMonth = () => {
  const newDate = new Date(selectedDate.value);
  newDate.setMonth(newDate.getMonth() + 1);
  selectedDate.value = newDate;
  loadMonthData();
};

const goToToday = () => {
  selectedDate.value = new Date();
  loadMonthData();
};

const showDayDetails = (day) => {
  selectedDayDate.value = day.date;
  selectedDayRecords.value = day.records;
  dayDetailsDialog.value = true;
};

const formatDate = (date) => {
  if (!date) return "";
  return new Date(date).toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
};

let unsubscribeAttendance = null;

onMounted(() => {
  loadMonthData();
  loadEmployees();

  // Listen for attendance updates
  unsubscribeAttendance = onAttendanceUpdate(() => {
    loadMonthData();
  });
});

onUnmounted(() => {
  if (unsubscribeAttendance) {
    unsubscribeAttendance();
  }
});
</script>

<style scoped>
.attendance-calendar :deep(td) {
  border: 1px solid #e0e0e0;
}

.attendance-calendar :deep(td:hover) {
  background-color: #f5f5f5;
}

.summary-box {
  background-color: rgba(0, 0, 0, 0.05);
  padding: 4px;
  border-radius: 4px;
  font-size: 11px;
  line-height: 1.3;
}

.summary-line {
  margin: 1px 0;
}

.text-error {
  color: #d32f2f !important;
  font-weight: 500;
}

.text-warning {
  color: #f57c00 !important;
  font-weight: 500;
}

/* Loading State */
.calendar-loading {
  position: relative;
  min-height: 400px;
}

.calendar-loading-overlay {
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(255, 255, 255, 0.9);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  z-index: 10;
  backdrop-filter: blur(2px);
}

.calendar-loading .attendance-calendar {
  opacity: 0.3;
  pointer-events: none;
}
</style>
