<template>
  <div>
    <!-- Filters -->
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
        <v-col cols="12" md="3">
          <v-select
            v-model="filters.status"
            label="Status"
            :items="statusOptions"
            density="compact"
            variant="outlined"
            clearable
            hide-details
          ></v-select>
        </v-col>
        <v-col cols="12" md="3">
          <v-btn color="#ED985F" block @click="loadAttendance">
            <v-icon start>mdi-magnify</v-icon>
            Search
          </v-btn>
        </v-col>
      </v-row>
    </v-card-text>

    <!-- Data Table -->
    <v-data-table
      :headers="headers"
      :items="attendance"
      :loading="loading"
      :items-per-page="10"
      :items-per-page-options="[
        { value: 10, title: '10' },
        { value: 25, title: '25' },
        { value: 50, title: '50' },
        { value: 100, title: '100' },
        { value: -1, title: 'All' },
      ]"
      class="elevation-0"
    >
      <template v-slot:item.employee="{ item }">
        <div>
          <div class="font-weight-medium">{{ item.employee.full_name }}</div>
          <div class="text-caption text-medium-emphasis">
            {{ item.employee.employee_number }}
          </div>
        </div>
      </template>

      <template v-slot:item.employee.employee_number="{ item }">
        <v-chip size="small" variant="tonal">
          {{ item.employee.employee_number }}
        </v-chip>
      </template>

      <template v-slot:item.attendance_date="{ item }">
        {{ formatDate(item.attendance_date) }}
      </template>

      <template v-slot:item.time_in="{ item }">
        <v-chip
          v-if="item.time_in"
          size="small"
          color="success"
          variant="tonal"
          prepend-icon="mdi-login"
        >
          {{ item.time_in }}
        </v-chip>
        <span v-else class="text-medium-emphasis">--:--</span>
      </template>

      <template v-slot:item.break_start="{ item }">
        <v-chip
          v-if="item.break_start"
          size="small"
          color="warning"
          variant="tonal"
          prepend-icon="mdi-coffee"
        >
          {{ item.break_start }}
        </v-chip>
        <span v-else class="text-medium-emphasis">--:--</span>
      </template>

      <template v-slot:item.break_end="{ item }">
        <v-chip
          v-if="item.break_end"
          size="small"
          color="info"
          variant="tonal"
          prepend-icon="mdi-coffee-outline"
        >
          {{ item.break_end }}
        </v-chip>
        <span v-else class="text-medium-emphasis">--:--</span>
      </template>

      <template v-slot:item.time_out="{ item }">
        <v-chip
          v-if="item.actual_time_out"
          size="small"
          color="error"
          variant="tonal"
          prepend-icon="mdi-logout"
        >
          {{ item.actual_time_out }}
        </v-chip>
        <span v-else class="text-medium-emphasis">--:--</span>
      </template>

      <template v-slot:item.hours_worked="{ item }">
        <span>{{ formatHoursWorked(item.regular_hours) }}</span>
        <span v-if="item.overtime_hours >= 1" class="text-warning">
          +{{ Math.floor(item.overtime_hours) }}h OT
        </span>
      </template>

      <template v-slot:item.status="{ item }">
        <v-chip :color="getStatusColor(item.status)" size="small">
          {{ item.status }}
        </v-chip>
      </template>

      <template v-slot:item.approval="{ item }">
        <v-chip
          v-if="item.approval_status === 'approved'"
          color="success"
          size="small"
          prepend-icon="mdi-check"
        >
          Approved
        </v-chip>
        <v-chip
          v-else-if="item.approval_status === 'rejected'"
          color="error"
          size="small"
          prepend-icon="mdi-close"
        >
          Rejected
        </v-chip>
        <v-chip v-else color="warning" size="small" prepend-icon="mdi-clock">
          Pending
        </v-chip>
      </template>

      <template v-slot:item.is_manual_entry="{ item }">
        <v-icon v-if="item.is_manual_entry" color="info">mdi-hand</v-icon>
        <v-icon v-else color="success">mdi-fingerprint</v-icon>
      </template>

      <template v-slot:item.actions="{ item }">
        <v-menu location="bottom end">
          <template v-slot:activator="{ props }">
            <v-btn
              v-bind="props"
              icon="mdi-dots-vertical"
              size="small"
              variant="text"
            ></v-btn>
          </template>
          <v-list density="compact">
            <v-list-item v-if="canEdit(item)" @click="$emit('edit', item)">
              <template v-slot:prepend>
                <v-icon size="18">mdi-pencil</v-icon>
              </template>
              <v-list-item-title>Edit</v-list-item-title>
            </v-list-item>
            <v-list-item
              v-if="canApprove(item)"
              @click="$emit('approve', item)"
            >
              <template v-slot:prepend>
                <v-icon size="18" color="success">mdi-check</v-icon>
              </template>
              <v-list-item-title>Approve</v-list-item-title>
            </v-list-item>
            <v-list-item v-if="canApprove(item)" @click="$emit('reject', item)">
              <template v-slot:prepend>
                <v-icon size="18" color="error">mdi-close</v-icon>
              </template>
              <v-list-item-title>Reject</v-list-item-title>
            </v-list-item>
            <v-list-item v-if="canDelete(item)" @click="$emit('delete', item)">
              <template v-slot:prepend>
                <v-icon size="18" color="error">mdi-delete</v-icon>
              </template>
              <v-list-item-title>Delete</v-list-item-title>
            </v-list-item>
          </v-list>
        </v-menu>
      </template>
    </v-data-table>
  </div>
</template>

<script setup>
import { ref, reactive, onMounted, computed, onUnmounted } from "vue";
import { useRoute } from "vue-router";
import attendanceService from "@/services/attendanceService";
import { useToast } from "vue-toastification";
import { onAttendanceUpdate } from "@/stores/attendance";
import { formatDate } from "@/utils/formatters";

const toast = useToast();
const route = useRoute();
const emit = defineEmits(["edit", "delete", "approve", "reject"]);

const loading = ref(false);
const attendance = ref([]);

const user = JSON.parse(localStorage.getItem("user") || "{}");
const canEditRole = computed(() =>
  ["admin", "hr", "payrollist"].includes(user.role),
);
const canApproveRole = computed(() =>
  ["admin", "hr", "manager"].includes(user.role),
);

const filters = reactive({
  date_from: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000)
    .toISOString()
    .split("T")[0],
  date_to: new Date().toISOString().split("T")[0],
  status: null,
});

const headers = [
  { title: "Employee", key: "employee", sortable: false },
  { title: "Staff Code", key: "employee.employee_number", sortable: false },
  { title: "Date", key: "attendance_date" },
  { title: "Time In", key: "time_in" },
  { title: "Break Out", key: "break_start" },
  { title: "Break In", key: "break_end" },
  { title: "Time Out", key: "time_out" },
  { title: "Hours", key: "hours_worked", sortable: false },
  { title: "Status", key: "status" },
  { title: "Approval", key: "approval", sortable: false },
  { title: "Type", key: "is_manual_entry", sortable: false },
  { title: "Actions", key: "actions", sortable: false },
];

const statusOptions = [
  { title: "Present", value: "present" },
  { title: "Absent", value: "absent" },
  { title: "Late", value: "late" },
  { title: "Half Day", value: "half_day" },
  { title: "On Leave", value: "on_leave" },
];

const loadAttendance = async () => {
  loading.value = true;
  try {
    const response = await attendanceService.getAttendance({
      ...filters,
      per_page: 10000, // Fetch all records
    });
    attendance.value = response.data || [];
  } catch (error) {
    toast.error("Failed to load attendance records");
  } finally {
    loading.value = false;
  }
};

// formatDate imported from @/utils/formatters

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

const formatHoursWorked = (hours) => {
  if (!hours || hours <= 0) return "0.00h";

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
};

const canEdit = (item) => {
  return (
    canEditRole.value &&
    (item.approval_status !== "approved" || user.role === "admin")
  );
};

const canApprove = (item) => {
  return canApproveRole.value && item.approval_status === "pending";
};

const canDelete = (item) => {
  return canEditRole.value && item.approval_status !== "approved";
};

let unsubscribeAttendance = null;

onMounted(() => {
  // Apply query parameters if present (from dashboard)
  if (route.query.date_from) {
    filters.date_from = route.query.date_from;
  }
  if (route.query.date_to) {
    filters.date_to = route.query.date_to;
  }

  loadAttendance();

  // Listen for attendance updates
  unsubscribeAttendance = onAttendanceUpdate(() => {
    loadAttendance();
  });
});

onUnmounted(() => {
  if (unsubscribeAttendance) {
    unsubscribeAttendance();
  }
});

defineExpose({ loadAttendance });
</script>
