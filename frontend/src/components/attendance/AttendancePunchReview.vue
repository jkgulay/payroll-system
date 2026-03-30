<template>
  <div class="punch-review">
    <v-card-text>
      <v-row>
        <v-col cols="12" md="3">
          <v-text-field
            v-model="filters.date_from"
            label="Date From"
            type="date"
            density="compact"
            variant="outlined"
            hide-details
          ></v-text-field>
        </v-col>
        <v-col cols="12" md="3">
          <v-text-field
            v-model="filters.date_to"
            label="Date To"
            type="date"
            density="compact"
            variant="outlined"
            hide-details
          ></v-text-field>
        </v-col>
        <v-col cols="12" md="4">
          <v-autocomplete
            v-model="filters.employee_id"
            :items="employees"
            item-title="full_name"
            item-value="id"
            label="Employee"
            density="compact"
            variant="outlined"
            clearable
            hide-details
            :loading="loadingEmployees"
          >
            <template v-slot:item="{ props, item }">
              <v-list-item v-bind="props">
                <template v-slot:subtitle>
                  {{ item.raw.employee_number || "No ID" }}
                </template>
              </v-list-item>
            </template>
          </v-autocomplete>
        </v-col>
        <v-col cols="12" md="2" class="d-flex align-center justify-end ga-2">
          <v-btn
            variant="outlined"
            color="grey"
            prepend-icon="mdi-filter-remove"
            @click="clearFilters"
          >
            Clear
          </v-btn>
          <v-btn
            color="primary"
            prepend-icon="mdi-refresh"
            @click="loadAttendance"
            :loading="loading"
          >
            Refresh
          </v-btn>
        </v-col>
      </v-row>
    </v-card-text>

    <v-data-table
      :headers="dayHeaders"
      :items="dayViewRows"
      :loading="loading"
      :items-per-page="15"
      :items-per-page-options="[
        { value: 15, title: '15' },
        { value: 30, title: '30' },
        { value: 60, title: '60' },
      ]"
      class="elevation-0"
    >
      <template v-slot:item.staff_code="{ item }">
        <span class="font-weight-medium">{{ item.staff_code }}</span>
      </template>

      <template v-slot:item.date="{ item }">
        {{ formatDate(item.date) }}
      </template>

      <template v-for="timeKey in timeKeys" v-slot:[`item.${timeKey}`]="{ item }" :key="timeKey">
        <v-chip
          v-if="item[timeKey]"
          size="x-small"
          color="success"
          variant="flat"
          class="time-chip"
        >
          {{ item[timeKey] }}
        </v-chip>
        <span v-else class="text-medium-emphasis">-</span>
      </template>

      <template v-slot:item.actions="{ item }">
        <v-btn
          size="small"
          variant="text"
          color="primary"
          prepend-icon="mdi-pencil"
          @click="$emit('edit', item.source)"
        >
          Edit
        </v-btn>
      </template>

      <template v-slot:no-data>
        <div class="text-center py-8">
          <v-icon size="52" color="grey">mdi-clipboard-search-outline</v-icon>
          <p class="text-h6 mt-3 mb-1">No records in selected range</p>
          <p class="text-body-2 text-medium-emphasis mb-0">
            Adjust date range or employee filter.
          </p>
        </div>
      </template>
    </v-data-table>
  </div>
</template>

<script setup>
import { computed, defineExpose, onMounted, reactive, ref, watch } from "vue";
import api from "@/services/api";
import attendanceService from "@/services/attendanceService";
import { useToast } from "vue-toastification";
import { formatDate } from "@/utils/formatters";

const toast = useToast();
const loading = ref(false);
const loadingEmployees = ref(false);
const attendance = ref([]);
const employees = ref([]);

const today = new Date();
const priorDate = new Date();
priorDate.setDate(today.getDate() - 14);

const toDateValue = (value) => value.toISOString().split("T")[0];

const filters = reactive({
  date_from: toDateValue(priorDate),
  date_to: toDateValue(today),
  employee_id: null,
});

const timeKeys = Array.from({ length: 10 }, (_, idx) => `time${idx + 1}`);

const dayHeaders = [
  { title: "Staff Code", key: "staff_code" },
  { title: "Name", key: "name" },
  { title: "Date", key: "date" },
  { title: "Week", key: "week" },
  { title: "Device Name", key: "device_name" },
  ...timeKeys.map((timeKey, idx) => ({ title: `Time${idx + 1}`, key: timeKey, sortable: false })),
  { title: "Actions", key: "actions", sortable: false },
];

const punchFieldMap = [
  { field: "time_in", type: "Time In" },
  { field: "break_start", type: "Break Out" },
  { field: "break_end", type: "Break In" },
  { field: "time_out", type: "Time Out" },
  { field: "ot_time_in", type: "OT In" },
  { field: "ot_time_out", type: "OT Out" },
  { field: "ot_time_in_2", type: "OT2 In" },
  { field: "ot_time_out_2", type: "OT2 Out" },
];

const getWeekDay = (dateValue) => {
  if (!dateValue) {
    return "-";
  }

  const safeDate = `${String(dateValue).slice(0, 10)}T00:00:00`;
  return new Date(safeDate).toLocaleDateString("en-US", {
    weekday: "long",
  });
};

const normalizeTime = (timeValue) => {
  if (!timeValue) {
    return null;
  }

  return String(timeValue).slice(0, 5);
};

const getRecordPunches = (record) => {
  const punches = punchFieldMap
    .map(({ field, type }) => ({
      time: normalizeTime(record[field]),
      type,
    }))
    .filter((entry) => entry.time);

  punches.sort((a, b) => a.time.localeCompare(b.time));
  return punches;
};

const dayViewRows = computed(() => {
  return attendance.value.map((record) => {
    const row = {
      source: record,
      staff_code: record.employee?.employee_number || "-",
      name: record.employee?.full_name || "-",
      date: record.attendance_date,
      week: getWeekDay(record.attendance_date),
      device_name: record.device_name || "-",
    };

    const punchTimes = getRecordPunches(record)
      .map((entry) => entry.time)
      .slice(0, 10);

    timeKeys.forEach((timeKey, idx) => {
      row[timeKey] = punchTimes[idx] || null;
    });

    return row;
  });
});

const loadEmployees = async () => {
  loadingEmployees.value = true;
  try {
    const perPage = 200;
    let page = 1;
    let list = [];
    let lastPage = 1;

    do {
      const response = await api.get("/employees", {
        params: { per_page: perPage, page },
      });
      const data = response.data?.data || [];
      list = list.concat(data);
      lastPage = response.data?.last_page || 1;
      page += 1;
    } while (page <= lastPage);

    employees.value = list;
  } catch (error) {
    toast.error("Failed to load employees");
  } finally {
    loadingEmployees.value = false;
  }
};

const loadAttendance = async () => {
  if (!filters.date_from || !filters.date_to) {
    return;
  }

  loading.value = true;
  try {
    const response = await attendanceService.getAttendance({
      date_from: filters.date_from,
      date_to: filters.date_to,
      employee_id: filters.employee_id || undefined,
      per_page: 10000,
    });

    attendance.value = response.data || [];
  } catch (error) {
    toast.error("Failed to load attendance records");
  } finally {
    loading.value = false;
  }
};

const clearFilters = () => {
  filters.date_from = toDateValue(priorDate);
  filters.date_to = toDateValue(today);
  filters.employee_id = null;
  loadAttendance();
};

watch(
  () => [filters.date_from, filters.date_to, filters.employee_id],
  () => {
    loadAttendance();
  },
);

onMounted(async () => {
  await Promise.all([loadEmployees(), loadAttendance()]);
});

defineExpose({ loadAttendance });
</script>

<style scoped>
.time-chip {
  min-width: 50px;
  justify-content: center;
}
</style>
