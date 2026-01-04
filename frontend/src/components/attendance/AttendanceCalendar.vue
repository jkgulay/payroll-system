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
                        <div
                          v-if="day.records.length > 2"
                          class="text-caption text-center"
                        >
                          +{{ day.records.length - 2 }} more
                        </div>
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
  </v-card-text>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import attendanceService from "@/services/attendanceService";
import { useToast } from "vue-toastification";

const emit = defineEmits(["date-click", "record-click"]);
const toast = useToast();

const loading = ref(false);
const selectedDate = ref(new Date());
const attendanceData = ref([]);

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
    });
  }

  // Current month days
  for (let day = 1; day <= totalDays; day++) {
    const dateStr = `${year}-${String(month + 1).padStart(2, "0")}-${String(
      day
    ).padStart(2, "0")}`;

    week.push({
      date: dateStr,
      dayNumber: day,
      isCurrentMonth: true,
      isToday: dateStr === new Date().toISOString().split("T")[0],
      records: attendanceData.value.filter(
        (a) => a.attendance_date === dateStr
      ),
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

    const response = await attendanceService.getAttendance({
      date_from: firstDay,
      date_to: lastDay,
    });
    attendanceData.value = response.data || [];
  } catch (error) {
    toast.error("Failed to load calendar data");
  } finally {
    loading.value = false;
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

onMounted(() => {
  loadMonthData();
});
</script>

<style scoped>
.attendance-calendar :deep(td) {
  border: 1px solid #e0e0e0;
}
</style>
