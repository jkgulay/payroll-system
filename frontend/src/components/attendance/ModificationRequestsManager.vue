<template>
  <v-card-text>
    <v-data-table
      :headers="headers"
      :items="requests"
      :loading="loading"
      :items-per-page="10"
      class="elevation-0"
    >
      <template v-slot:top>
        <v-toolbar flat>
          <v-toolbar-title>Attendance Modification Requests</v-toolbar-title>
          <v-spacer></v-spacer>
          <v-select
            v-model="statusFilter"
            :items="statusOptions"
            label="Status"
            variant="outlined"
            density="compact"
            hide-details
            style="max-width: 180px"
            class="mr-2"
          ></v-select>
          <v-btn
            color="primary"
            @click="loadRequests"
            icon="mdi-refresh"
          ></v-btn>
        </v-toolbar>
      </template>

      <template v-slot:item.requester="{ item }">
        <div>
          <div class="font-weight-medium">{{ item.requester?.name || 'Unknown' }}</div>
          <div class="text-caption text-medium-emphasis">
            {{ item.requester?.role || '' }}
          </div>
        </div>
      </template>

      <template v-slot:item.date="{ item }">
        {{ formatDate(item.date) }}
      </template>

      <template v-slot:item.status="{ item }">
        <v-chip :color="getStatusColor(item.status)" size="small" variant="flat">
          {{ item.status }}
        </v-chip>
      </template>

      <template v-slot:item.created_at="{ item }">
        {{ formatDateTime(item.created_at) }}
      </template>

      <template v-slot:item.reviewer="{ item }">
        <span v-if="item.reviewer">{{ item.reviewer.name }}</span>
        <span v-else class="text-medium-emphasis">—</span>
      </template>

      <template v-slot:item.actions="{ item }">
        <div v-if="item.status === 'pending'" class="d-flex gap-1">
          <v-btn
            icon="mdi-check"
            size="small"
            variant="text"
            color="success"
            @click="approveRequest(item)"
          ></v-btn>
          <v-btn
            icon="mdi-close"
            size="small"
            variant="text"
            color="error"
            @click="openRejectDialog(item)"
          ></v-btn>
        </div>
        <span v-else class="text-caption text-medium-emphasis">
          {{ item.reviewed_at ? formatDateTime(item.reviewed_at) : '' }}
        </span>
      </template>

      <template v-slot:no-data>
        <div class="text-center pa-6">
          <v-icon size="64" color="success">mdi-check-all</v-icon>
          <p class="text-h6 mt-4">No modification requests</p>
          <p class="text-body-2 text-medium-emphasis">
            There are no {{ statusFilter === 'all' ? '' : statusFilter }} requests at this time.
          </p>
        </div>
      </template>
    </v-data-table>

    <!-- Reject Dialog -->
    <v-dialog v-model="rejectDialog" max-width="500" persistent>
      <v-card rounded="lg">
        <v-card-title class="d-flex align-center pa-4">
          <v-icon color="error" class="mr-2">mdi-close-circle</v-icon>
          Reject Modification Request
        </v-card-title>
        <v-divider></v-divider>
        <v-card-text class="pa-4">
          <p class="text-body-2 mb-4">
            Rejecting request from <strong>{{ selectedRequest?.requester?.name }}</strong>
            for date <strong>{{ formatDate(selectedRequest?.date) }}</strong>.
          </p>
          <v-textarea
            v-model="rejectNotes"
            label="Rejection Reason"
            variant="outlined"
            rows="3"
            :rules="[v => !!v || 'Reason is required']"
            placeholder="Provide a reason for rejecting this request"
          ></v-textarea>
        </v-card-text>
        <v-divider></v-divider>
        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="rejectDialog = false; rejectNotes = ''">
            Cancel
          </v-btn>
          <v-btn
            color="error"
            variant="flat"
            :loading="processing"
            :disabled="!rejectNotes"
            prepend-icon="mdi-close"
            @click="rejectRequest"
          >
            Reject
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-card-text>
</template>

<script setup>
import { ref, watch, onMounted } from "vue";
import attendanceService from "@/services/attendanceService";
import { useToast } from "vue-toastification";

const emit = defineEmits(["update-count"]);
const toast = useToast();

const loading = ref(false);
const processing = ref(false);
const requests = ref([]);
const statusFilter = ref("pending");
const rejectDialog = ref(false);
const selectedRequest = ref(null);
const rejectNotes = ref("");

const statusOptions = [
  { title: "All", value: "all" },
  { title: "Pending", value: "pending" },
  { title: "Approved", value: "approved" },
  { title: "Rejected", value: "rejected" },
];

const headers = [
  { title: "Requested By", key: "requester", sortable: true },
  { title: "Date", key: "date", sortable: true },
  { title: "Reason", key: "reason", sortable: false },
  { title: "Status", key: "status", sortable: true },
  { title: "Submitted", key: "created_at", sortable: true },
  { title: "Reviewed By", key: "reviewer", sortable: false },
  { title: "Actions", key: "actions", sortable: false, align: "center" },
];

function formatDate(dateStr) {
  if (!dateStr) return "";
  const d = new Date(dateStr + "T00:00:00");
  return d.toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

function formatDateTime(dtStr) {
  if (!dtStr) return "";
  const d = new Date(dtStr);
  return d.toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function getStatusColor(status) {
  const colors = { pending: "warning", approved: "success", rejected: "error" };
  return colors[status] || "grey";
}

const loadRequests = async () => {
  loading.value = true;
  try {
    const params = {};
    if (statusFilter.value !== "all") {
      params.status = statusFilter.value;
    }
    const response = await attendanceService.getModificationRequests(params);
    requests.value = response.data || [];

    // Emit pending count
    const pendingCount = requests.value.filter((r) => r.status === "pending").length;
    emit("update-count", pendingCount);
  } catch {
    toast.error("Failed to load modification requests");
    requests.value = [];
  } finally {
    loading.value = false;
  }
};

const approveRequest = async (request) => {
  processing.value = true;
  try {
    await attendanceService.approveModificationRequest(request.id);
    toast.success("Request approved successfully");
    await loadRequests();
  } catch (error) {
    const msg = error.response?.data?.message || "Failed to approve request";
    toast.error(msg);
  } finally {
    processing.value = false;
  }
};

const openRejectDialog = (request) => {
  selectedRequest.value = request;
  rejectNotes.value = "";
  rejectDialog.value = true;
};

const rejectRequest = async () => {
  if (!rejectNotes.value || !selectedRequest.value) return;

  processing.value = true;
  try {
    await attendanceService.rejectModificationRequest(
      selectedRequest.value.id,
      rejectNotes.value
    );
    toast.success("Request rejected");
    rejectDialog.value = false;
    selectedRequest.value = null;
    rejectNotes.value = "";
    await loadRequests();
  } catch (error) {
    const msg = error.response?.data?.message || "Failed to reject request";
    toast.error(msg);
  } finally {
    processing.value = false;
  }
};

watch(statusFilter, () => {
  loadRequests();
});

onMounted(() => {
  loadRequests();
});
</script>
