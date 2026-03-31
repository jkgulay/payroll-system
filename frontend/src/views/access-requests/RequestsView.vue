<template>
  <div class="requests-page">
    <div class="modern-card">
      <div class="page-header">
        <div class="page-icon-badge">
          <v-icon
            icon="mdi-clipboard-list-outline"
            size="24"
            color="white"
          ></v-icon>
        </div>
        <div class="page-header-content">
          <h1 class="page-title">Access Requests</h1>
          <p class="page-subtitle">
            Review and manage all access requests from payrollists
          </p>
        </div>
        <div class="d-flex gap-2">
          <v-btn
            icon="mdi-refresh"
            variant="text"
            color="primary"
            @click="loadRequests"
            title="Refresh"
          ></v-btn>
        </div>
      </div>

      <v-tabs v-model="activeTab" color="primary" class="px-4">
        <v-tab value="all">
          All Requests
          <v-badge
            v-if="pendingCounts.total > 0"
            :content="pendingCounts.total"
            color="error"
            class="ml-2"
            inline
          ></v-badge>
        </v-tab>
        <v-tab value="attendance">
          Attendance
          <v-badge
            v-if="pendingCounts.attendance > 0"
            :content="pendingCounts.attendance"
            color="error"
            class="ml-2"
            inline
          ></v-badge>
        </v-tab>
        <v-tab value="attendance-settings">
          Attendance Settings
          <v-badge
            v-if="pendingCounts['attendance-settings'] > 0"
            :content="pendingCounts['attendance-settings']"
            color="error"
            class="ml-2"
            inline
          ></v-badge>
        </v-tab>
        <v-tab value="government-rates">
          Gov. Rates
          <v-badge
            v-if="pendingCounts['government-rates'] > 0"
            :content="pendingCounts['government-rates']"
            color="error"
            class="ml-2"
            inline
          ></v-badge>
        </v-tab>
        <v-tab value="deductions">
          Deductions
          <v-badge
            v-if="pendingCounts.deductions > 0"
            :content="pendingCounts.deductions"
            color="error"
            class="ml-2"
            inline
          ></v-badge>
        </v-tab>
        <v-tab value="allowances">
          Allowances
          <v-badge
            v-if="pendingCounts.allowances > 0"
            :content="pendingCounts.allowances"
            color="error"
            class="ml-2"
            inline
          ></v-badge>
        </v-tab>
        <v-tab value="thirteenth-month-pay">
          13th Month
          <v-badge
            v-if="pendingCounts['thirteenth-month-pay'] > 0"
            :content="pendingCounts['thirteenth-month-pay']"
            color="error"
            class="ml-2"
            inline
          ></v-badge>
        </v-tab>
        <v-tab value="loans">
          Loans
          <v-badge
            v-if="pendingCounts.loans > 0"
            :content="pendingCounts.loans"
            color="error"
            class="ml-2"
            inline
          ></v-badge>
        </v-tab>
        <v-tab value="cash-bonds">
          Cash Bonds
          <v-badge
            v-if="pendingCounts['cash-bonds'] > 0"
            :content="pendingCounts['cash-bonds']"
            color="error"
            class="ml-2"
            inline
          ></v-badge>
        </v-tab>
        <v-tab value="salary-adjustments">
          Salary Adj.
          <v-badge
            v-if="pendingCounts['salary-adjustments'] > 0"
            :content="pendingCounts['salary-adjustments']"
            color="error"
            class="ml-2"
            inline
          ></v-badge>
        </v-tab>
      </v-tabs>

      <v-divider></v-divider>

      <v-card-text>
        <v-toolbar flat>
          <v-toolbar-title>{{ tabTitle }} Access Requests</v-toolbar-title>
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
            variant="tonal"
            color="grey"
            prepend-icon="mdi-filter-remove"
            class="mr-2"
            @click="clearTableFilters"
            :disabled="!hasActiveFilters"
          >
            Clear
          </v-btn>
          <v-chip
            v-if="hasActiveFilters"
            size="small"
            color="info"
            variant="tonal"
          >
            {{ activeFilterCount }} active filter
          </v-chip>
        </v-toolbar>

        <v-data-table
          :headers="headers"
          :items="filteredRequests"
          :loading="loading"
          :items-per-page="10"
          class="elevation-0"
        >
          <template v-slot:item.requester="{ item }">
            <div>
              <div class="font-weight-medium">
                {{ item.requester?.name || "Unknown" }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ item.requester?.role || "" }}
              </div>
            </div>
          </template>

          <template v-slot:item.module="{ item }">
            <v-chip
              :color="getModuleColor(item.module)"
              size="small"
              variant="flat"
            >
              {{ getModuleLabel(item.module) }}
            </v-chip>
          </template>

          <template v-slot:item.date="{ item }">
            {{ formatDate(item.date) }}
          </template>

          <template v-slot:item.status="{ item }">
            <v-chip
              :color="getStatusColor(item.status)"
              size="small"
              variant="flat"
            >
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
              {{ item.reviewed_at ? formatDateTime(item.reviewed_at) : "" }}
            </span>
          </template>

          <template v-slot:no-data>
            <div class="text-center pa-6">
              <v-icon size="64" color="success">mdi-check-all</v-icon>
              <p class="text-h6 mt-4">No access requests</p>
              <p class="text-body-2 text-medium-emphasis">
                There are no
                {{ statusFilter === "all" ? "" : statusFilter }} requests at
                this time.
              </p>
              <v-btn
                class="mt-3"
                variant="outlined"
                color="primary"
                @click="clearTableFilters"
                :disabled="!hasActiveFilters"
              >
                Clear filters
              </v-btn>
            </div>
          </template>
        </v-data-table>
      </v-card-text>

      <v-dialog v-model="rejectDialog" max-width="500" persistent>
        <v-card rounded="lg">
          <v-card-title class="d-flex align-center pa-4">
            <v-icon color="error" class="mr-2">mdi-close-circle</v-icon>
            Reject Access Request
          </v-card-title>
          <v-divider></v-divider>
          <v-card-text class="pa-4">
            <p class="text-body-2 mb-4">
              Rejecting
              <strong>{{ getModuleLabel(selectedRequest?.module) }}</strong>
              request from
              <strong>{{ selectedRequest?.requester?.name }}</strong>
              <span v-if="selectedRequest?.date"
                >for date
                <strong>{{ formatDate(selectedRequest?.date) }}</strong></span
              >.
            </p>
            <v-textarea
              v-model="rejectNotes"
              label="Rejection Reason"
              variant="outlined"
              rows="3"
              :rules="[(v) => !!v || 'Reason is required']"
              placeholder="Provide a reason for rejecting this request"
            ></v-textarea>
          </v-card-text>
          <v-divider></v-divider>
          <v-card-actions class="pa-4">
            <v-spacer></v-spacer>
            <v-btn
              variant="text"
              @click="
                rejectDialog = false;
                rejectNotes = '';
              "
            >
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
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from "vue";
import { useRoute } from "vue-router";
import moduleAccessService from "@/services/moduleAccessService";
import { useToast } from "vue-toastification";

const route = useRoute();
const toast = useToast();

const ALL_MODULES = [
  "attendance",
  "attendance-settings",
  "government-rates",
  "deductions",
  "allowances",
  "thirteenth-month-pay",
  "loans",
  "cash-bonds",
  "salary-adjustments",
];

const moduleLabels = {
  attendance: "Attendance",
  "attendance-settings": "Attendance Settings",
  "government-rates": "Government Rates",
  deductions: "Deductions",
  allowances: "Allowances",
  "thirteenth-month-pay": "13th Month Pay",
  loans: "Loans",
  "cash-bonds": "Cash Bonds",
  "salary-adjustments": "Salary Adjustments",
};

const moduleColors = {
  attendance: "green",
  "attendance-settings": "deep-orange",
  "government-rates": "brown",
  deductions: "orange",
  allowances: "blue",
  "thirteenth-month-pay": "purple",
  loans: "teal",
  "cash-bonds": "indigo",
  "salary-adjustments": "cyan",
};

const activeTab = ref(route.query.tab || "all");
const loading = ref(false);
const processing = ref(false);
const requests = ref([]);
const statusFilter = ref("pending");
const rejectDialog = ref(false);
const selectedRequest = ref(null);
const rejectNotes = ref("");

const pendingCounts = ref({
  total: 0,
  attendance: 0,
  "attendance-settings": 0,
  "government-rates": 0,
  deductions: 0,
  allowances: 0,
  "thirteenth-month-pay": 0,
  loans: 0,
  "cash-bonds": 0,
  "salary-adjustments": 0,
});

const statusOptions = [
  { title: "All", value: "all" },
  { title: "Pending", value: "pending" },
  { title: "Approved", value: "approved" },
  { title: "Rejected", value: "rejected" },
];

const baseHeaders = [
  { title: "Requested By", key: "requester", sortable: true },
  { title: "Date", key: "date", sortable: true },
  { title: "Reason", key: "reason", sortable: false },
  { title: "Status", key: "status", sortable: true },
  { title: "Submitted", key: "created_at", sortable: true },
  { title: "Reviewed By", key: "reviewer", sortable: false },
  { title: "Actions", key: "actions", sortable: false, align: "center" },
];

const headers = computed(() => {
  if (activeTab.value === "all") {
    return [
      { title: "Requested By", key: "requester", sortable: true },
      { title: "Request Type", key: "module", sortable: true },
      ...baseHeaders.slice(1),
    ];
  }
  return baseHeaders;
});

const tabTitle = computed(() => {
  if (activeTab.value === "all") return "All";
  return moduleLabels[activeTab.value] || activeTab.value;
});

const filteredRequests = computed(() => {
  let filtered = requests.value;

  if (activeTab.value !== "all") {
    filtered = filtered.filter((r) => r.module === activeTab.value);
  }

  if (statusFilter.value !== "all") {
    filtered = filtered.filter((r) => r.status === statusFilter.value);
  }

  return filtered;
});

const hasActiveFilters = computed(() => statusFilter.value !== "all");
const activeFilterCount = computed(() =>
  statusFilter.value !== "all" ? 1 : 0,
);

const clearTableFilters = () => {
  statusFilter.value = "all";
};

function getModuleLabel(module) {
  return moduleLabels[module] || module;
}

function getModuleColor(module) {
  return moduleColors[module] || "grey";
}

function formatDate(dateStr) {
  if (!dateStr) return "—";
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
    const response = await moduleAccessService.getRequestsForModules(
      ALL_MODULES,
      {},
    );
    requests.value = response.data || [];

    const counts = {
      total: 0,
      attendance: 0,
      "attendance-settings": 0,
      "government-rates": 0,
      deductions: 0,
      allowances: 0,
      "thirteenth-month-pay": 0,
      loans: 0,
      "cash-bonds": 0,
      "salary-adjustments": 0,
    };

    requests.value.forEach((r) => {
      if (r.status === "pending" && counts[r.module] !== undefined) {
        counts[r.module]++;
        counts.total++;
      }
    });

    pendingCounts.value = counts;
  } catch {
    toast.error("Failed to load access requests");
    requests.value = [];
  } finally {
    loading.value = false;
  }
};

const approveRequest = async (request) => {
  processing.value = true;
  try {
    await moduleAccessService.approveRequest(request.id);
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
    await moduleAccessService.rejectRequest(
      selectedRequest.value.id,
      rejectNotes.value,
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

watch(
  () => route.query.tab,
  (newTab) => {
    if (newTab && ALL_MODULES.includes(newTab)) {
      activeTab.value = newTab;
    }
  },
);

onMounted(() => {
  if (route.query.tab && ALL_MODULES.includes(route.query.tab)) {
    activeTab.value = route.query.tab;
  }
  loadRequests();
});
</script>

<style scoped>
.requests-page {
  padding: 16px;
}

.modern-card {
  background: white;
  border-radius: 16px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.08);
  overflow: hidden;
}

.page-header {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 24px;
  background: linear-gradient(135deg, #f8f9fa 0%, #fff 100%);
  border-bottom: 1px solid #f0f0f0;
}

.page-icon-badge {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: linear-gradient(135deg, #d97706 0%, #f59e0b 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(217, 119, 6, 0.3);
}

.page-header-content {
  flex: 1;
}

.page-title {
  font-size: 1.5rem;
  font-weight: 600;
  color: #1a1a1a;
  margin: 0;
}

.page-subtitle {
  font-size: 0.875rem;
  color: #666;
  margin: 4px 0 0 0;
}
</style>
