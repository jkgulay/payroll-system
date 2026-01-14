<template>
  <v-card-text>
    <v-row>
      <v-col cols="12" md="3">
        <v-card>
          <v-card-title class="text-h6">Date Navigation</v-card-title>
          <v-card-text>
            <v-date-picker
              v-model="selectedDate"
              @update:model-value="loadMonthData"
              color="primary"
              full-width
              show-adjacent-months
            ></v-date-picker>
          </v-card-text>
        </v-card>

        <v-card class="mt-4">
          <v-card-title class="text-h6">Legend</v-card-title>
          <v-card-text>
            <div class="d-flex align-center mb-2">
              <v-icon color="success" class="mr-2">mdi-circle</v-icon>
              <span>Present</span>
            </div>
            <div class="d-flex align-center mb-2">
              <v-icon color="error" class="mr-2">mdi-circle</v-icon>
              <span>Absent</span>
            </div>
            <div class="d-flex align-center mb-2">
              <v-icon color="warning" class="mr-2">mdi-circle</v-icon>
              <span>Late</span>
            </div>
            <div class="d-flex align-center mb-2">
              <v-icon color="info" class="mr-2">mdi-circle</v-icon>
              <span>Half Day</span>
            </div>
            <div class="d-flex align-center mb-2">
              <v-icon color="purple" class="mr-2">mdi-circle</v-icon>
              <span>On Leave</span>
            </div>
          </v-card-text>
        </v-card>

        <v-card class="mt-4">
          <v-card-title class="text-h6">Filter</v-card-title>
          <v-card-text>
            <v-autocomplete
              v-model="selectedEmployeeId"
              :items="employees"
              item-title="full_name"
              item-value="id"
              label="Filter by Employee"
              variant="outlined"
              density="compact"
              clearable
              :loading="loadingEmployees"
              @update:model-value="loadMonthData"
            ></v-autocomplete>
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
                ></v-btn>
                <v-btn
                  icon="mdi-calendar-today"
                  @click="goToToday"
                  variant="text"
                ></v-btn>
                <v-btn
                  icon="mdi-chevron-right"
                  @click="nextMonth"
                  variant="text"
                ></v-btn>
              </v-col>
            </v-row>
          </v-card-title>

          <v-card-text>
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
              {{ item.time_out || "--:--" }}
            </template>

            <template v-slot:item.hours="{ item }">
              {{ item.regular_hours || 0 }}h
              <span v-if="item.overtime_hours > 0" class="text-warning">
                +{{ item.overtime_hours }}h
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
      day
    ).padStart(2, "0")}`;

    const dayRecords = attendanceData.value.filter(
      (a) => a.attendance_date === dateStr
    );

    const summary = {
      total: dayRecords.length,
      present: dayRecords.filter((r) => r.status === "present").length,
      absent: dayRecords.filter((r) => r.status === "absent").length,
      late: dayRecords.filter((r) => r.status === "late").length,
      onLeave: dayRecords.filter((r) => r.status === "on_leave").length,
    };

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
    };

    if (selectedEmployeeId.value) {
      params.employee_id = selectedEmployeeId.value;
    }

    const response = await attendanceService.getAttendance(params);
    attendanceData.value = response.data || [];
  } catch (error) {
    toast.error("Failed to load calendar data");
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
  if (day.date) {
    emit("date-click", day.date);
  }
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
</style>
