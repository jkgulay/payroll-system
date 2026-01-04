<template>
  <v-card-text>
    <v-data-table
      :headers="headers"
      :items="pendingList"
      :loading="loading"
      :items-per-page="10"
      class="elevation-0"
    >
      <template v-slot:top>
        <v-toolbar flat>
          <v-toolbar-title>Pending Approvals</v-toolbar-title>
          <v-spacer></v-spacer>
          <v-btn
            color="primary"
            @click="loadPending"
            icon="mdi-refresh"
          ></v-btn>
        </v-toolbar>
      </template>

      <template v-slot:item.employee="{ item }">
        <div>
          <div class="font-weight-medium">{{ item.employee.full_name }}</div>
          <div class="text-caption text-medium-emphasis">
            {{ item.employee.employee_number }}
          </div>
        </div>
      </template>

      <template v-slot:item.attendance_date="{ item }">
        {{ formatDate(item.attendance_date) }}
      </template>

      <template v-slot:item.time_in="{ item }">
        {{ item.time_in || "--:--" }}
      </template>

      <template v-slot:item.time_out="{ item }">
        {{ item.time_out || "--:--" }}
      </template>

      <template v-slot:item.status="{ item }">
        <v-chip :color="getStatusColor(item.status)" size="small">
          {{ item.status }}
        </v-chip>
      </template>

      <template v-slot:item.created_by="{ item }">
        {{ item.created_by?.name || "System" }}
      </template>

      <template v-slot:item.manual_reason="{ item }">
        <v-tooltip
          :text="item.manual_reason || 'No reason provided'"
          location="top"
        >
          <template v-slot:activator="{ props }">
            <div v-bind="props" class="text-truncate" style="max-width: 200px">
              {{ item.manual_reason || "No reason" }}
            </div>
          </template>
        </v-tooltip>
      </template>

      <template v-slot:item.actions="{ item }">
        <v-btn
          icon="mdi-check"
          size="small"
          variant="text"
          color="success"
          @click="$emit('approve', item)"
        ></v-btn>
        <v-btn
          icon="mdi-close"
          size="small"
          variant="text"
          color="error"
          @click="$emit('reject', item)"
        ></v-btn>
      </template>

      <template v-slot:no-data>
        <div class="text-center pa-6">
          <v-icon size="64" color="success">mdi-check-all</v-icon>
          <p class="text-h6 mt-4">No pending approvals</p>
        </div>
      </template>
    </v-data-table>
  </v-card-text>
</template>

<script setup>
import { ref, onMounted, watch } from "vue";
import attendanceService from "@/services/attendanceService";
import { useToast } from "vue-toastification";

const emit = defineEmits(["approve", "reject", "update-count"]);
const toast = useToast();

const loading = ref(false);
const pendingList = ref([]);

const headers = [
  { title: "Employee", key: "employee", sortable: false },
  { title: "Date", key: "attendance_date" },
  { title: "Time In", key: "time_in" },
  { title: "Time Out", key: "time_out" },
  { title: "Status", key: "status" },
  { title: "Reason", key: "manual_reason", sortable: false },
  { title: "Created By", key: "created_by", sortable: false },
  { title: "Actions", key: "actions", sortable: false },
];

const loadPending = async () => {
  loading.value = true;
  try {
    const response = await attendanceService.getPendingApprovals();
    pendingList.value = response.data || [];
    emit("update-count", pendingList.value.length);
  } catch (error) {
    toast.error("Failed to load pending approvals");
  } finally {
    loading.value = false;
  }
};

const formatDate = (date) => {
  return new Date(date).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
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

onMounted(() => {
  loadPending();
});

// Watch for external updates
watch(
  () => pendingList.value,
  (newVal) => {
    emit("update-count", newVal.length);
  }
);

defineExpose({ loadPending });
</script>
