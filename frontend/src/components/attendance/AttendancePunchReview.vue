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
      :headers="headers"
      :items="reviewRows"
      :loading="loading"
      :items-per-page="15"
      :items-per-page-options="[
        { value: 15, title: '15' },
        { value: 30, title: '30' },
        { value: 60, title: '60' },
      ]"
      class="elevation-0"
    >
      <template v-slot:item.employee="{ item }">
        <div>
          <div class="font-weight-medium">{{ item.employee?.full_name || "-" }}</div>
          <div class="text-caption text-medium-emphasis">
            {{ item.employee?.employee_number || "" }}
          </div>
        </div>
      </template>

      <template v-slot:item.attendance_date="{ item }">
        {{ formatDate(item.attendance_date) }}
      </template>

      <template v-slot:item.punch_timeline="{ item }">
        <div class="timeline-chips">
          <v-chip size="x-small" color="success" variant="tonal">IN {{ displayTime(item.time_in) }}</v-chip>
          <v-chip size="x-small" color="warning" variant="tonal">BO {{ displayTime(item.break_start) }}</v-chip>
          <v-chip size="x-small" color="info" variant="tonal">BI {{ displayTime(item.break_end) }}</v-chip>
          <v-chip size="x-small" color="error" variant="tonal">OUT {{ displayTime(item.actual_time_out || item.time_out) }}</v-chip>
          <v-chip size="x-small" color="purple" variant="tonal">OT IN {{ displayTime(item.ot_time_in) }}</v-chip>
          <v-chip size="x-small" color="deep-purple" variant="tonal">OT OUT {{ displayTime(item.ot_time_out) }}</v-chip>
        </div>
      </template>

      <template v-slot:item.anomalies="{ item }">
        <div v-if="item.anomalies.length" class="d-flex flex-wrap ga-1">
          <v-chip
            v-for="(flag, idx) in item.anomalies"
            :key="`${item.id}-flag-${idx}`"
            size="x-small"
            color="error"
            variant="tonal"
          >
            {{ flag }}
          </v-chip>
        </div>
        <v-chip v-else size="x-small" color="success" variant="tonal">
          Clean
        </v-chip>
      </template>

      <template v-slot:item.hours="{ item }">
        <span>{{ Number(item.regular_hours || 0).toFixed(2) }}h</span>
        <span v-if="Number(item.overtime_hours || 0) > 0" class="text-warning font-weight-medium">
          + {{ Number(item.overtime_hours).toFixed(2) }}h OT
        </span>
      </template>

      <template v-slot:item.actions="{ item }">
        <v-btn
          size="small"
          variant="text"
          color="primary"
          prepend-icon="mdi-pencil"
          @click="$emit('edit', item)"
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

const headers = [
  { title: "Employee", key: "employee", sortable: false },
  { title: "Date", key: "attendance_date" },
  { title: "Punch Timeline", key: "punch_timeline", sortable: false },
  { title: "Flags", key: "anomalies", sortable: false },
  { title: "Hours", key: "hours", sortable: false },
  { title: "Actions", key: "actions", sortable: false },
];

const displayTime = (value) => value || "--:--";

const analyzeAnomalies = (record) => {
  const flags = [];

  const timeOut = record.actual_time_out || record.time_out;
  if (!record.time_in && timeOut) {
    flags.push("Missing IN with OUT");
  }

  if (record.time_in && !timeOut) {
    flags.push("Missing OUT");
  }

  if (record.break_start && !record.break_end) {
    flags.push("Break start without break end");
  }

  if (!record.break_start && record.break_end) {
    flags.push("Break end without break start");
  }

  if (record.ot_time_in && !record.ot_time_out) {
    flags.push("OT IN without OT OUT");
  }

  if (!record.ot_time_in && record.ot_time_out) {
    flags.push("OT OUT without OT IN");
  }

  return flags;
};

const reviewRows = computed(() => {
  return attendance.value.map((record) => ({
    ...record,
    anomalies: analyzeAnomalies(record),
  }));
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
.timeline-chips {
  display: flex;
  flex-wrap: wrap;
  gap: 4px;
}
</style>
