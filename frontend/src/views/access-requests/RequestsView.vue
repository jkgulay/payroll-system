<template>
  <div class="requests-page">
    <div class="page-header">
      <div class="header-content">
        <div class="page-title-section">
          <div class="page-icon-badge">
            <v-icon size="20">mdi-clipboard-list-outline</v-icon>
          </div>
          <div>
            <h1 class="page-title">Access Requests</h1>
            <p class="page-subtitle">
              Review and manage all access requests from payrollists
            </p>
          </div>
        </div>
        <div class="action-buttons">
          <button class="action-btn action-btn-primary" @click="loadRequests">
            <v-icon size="20">mdi-refresh</v-icon>
            <span>Refresh</span>
          </button>
        </div>
      </div>
    </div>

    <div class="modern-card tab-container">
      <div class="modern-tabs">
        <button
          v-for="tab in primaryTabs"
          :key="tab.key"
          class="modern-tab"
          :class="{ active: activeTab === tab.key }"
          @click="activeTab = tab.key"
        >
          <v-icon size="20">{{ tab.icon }}</v-icon>
          <span>{{ tab.label }}</span>
          <div v-if="getPendingCount(tab.key) > 0" class="tab-badge">
            {{ getPendingCount(tab.key) }}
          </div>
        </button>

        <v-menu v-if="overflowTabs.length" location="bottom end">
          <template v-slot:activator="{ props }">
            <button
              v-bind="props"
              class="modern-tab more-tab"
              :class="{ active: overflowTabActive }"
            >
              <v-icon size="20">mdi-dots-horizontal-circle-outline</v-icon>
              <span>More</span>
              <div v-if="overflowPendingTotal > 0" class="tab-badge">
                {{ overflowPendingTotal }}
              </div>
            </button>
          </template>

          <v-list class="more-tabs-menu" density="compact">
            <v-list-item
              v-for="tab in overflowTabs"
              :key="tab.key"
              @click="activeTab = tab.key"
              :active="activeTab === tab.key"
            >
              <template v-slot:prepend>
                <v-icon size="18">{{ tab.icon }}</v-icon>
              </template>
              <v-list-item-title>{{ tab.label }}</v-list-item-title>
              <template v-slot:append>
                <v-chip
                  v-if="getPendingCount(tab.key) > 0"
                  size="x-small"
                  color="warning"
                >
                  {{ getPendingCount(tab.key) }}
                </v-chip>
              </template>
            </v-list-item>
          </v-list>
        </v-menu>
      </div>

      <div class="tab-content">
        <v-card-text class="requests-filter-panel">
          <v-row>
            <v-col cols="12" md="3">
              <v-text-field
                v-model="filters.date"
                label="Date"
                type="date"
                density="compact"
                variant="outlined"
                hide-details
              ></v-text-field>
            </v-col>
            <v-col cols="12" md="3">
              <v-select
                v-model="filters.status"
                label="Approval"
                :items="statusOptions"
                density="compact"
                variant="outlined"
                clearable
                hide-details
              ></v-select>
            </v-col>
            <v-col cols="12" md="3">
              <v-select
                v-model="filters.module"
                label="Request Type"
                :items="requestTypeOptions"
                density="compact"
                variant="outlined"
                clearable
                hide-details
                :disabled="activeTab !== 'all'"
              ></v-select>
            </v-col>
            <v-col cols="12" md="2">
              <v-text-field
                v-model="filters.search"
                label="Search Employee"
                density="compact"
                variant="outlined"
                prepend-inner-icon="mdi-magnify"
                clearable
                hide-details
                placeholder="Name or role..."
              ></v-text-field>
            </v-col>
            <v-col cols="12" md="1" class="d-flex align-center justify-end">
              <v-btn
                variant="tonal"
                color="grey"
                icon="mdi-filter-remove"
                @click="clearFilters"
                :disabled="!hasActiveFilters"
                title="Clear filters"
              ></v-btn>
            </v-col>
          </v-row>
        </v-card-text>

        <v-data-table
          :headers="headers"
          :items="filteredRequests"
          :loading="loading"
          :items-per-page="10"
          :items-per-page-options="[
            { value: 10, title: '10' },
            { value: 25, title: '25' },
            { value: 50, title: '50' },
            { value: 100, title: '100' },
          ]"
          class="requests-data-table elevation-0"
        >
          <template v-slot:item.requester="{ item }">
            <div>
              <div class="requester-name">
                {{ item.requester?.name || "Unknown" }}
              </div>
              <div class="requester-meta">{{ item.requester?.role || "" }}</div>
            </div>
          </template>

          <template v-slot:item.module="{ item }">
            <v-chip
              :color="getModuleColor(item.module)"
              size="small"
              variant="tonal"
            >
              {{ getModuleLabel(item.module) }}
            </v-chip>
          </template>

          <template v-slot:item.date="{ item }">
            {{ formatDate(item.date) }}
          </template>

          <template v-slot:item.reason="{ item }">
            <div class="reason-text" :title="item.reason || '-'">
              {{ item.reason || "-" }}
            </div>
          </template>

          <template v-slot:item.status="{ item }">
            <v-chip :color="getStatusColor(item.status)" size="small">
              {{ formatStatusLabel(item.status) }}
            </v-chip>
          </template>

          <template v-slot:item.created_at="{ item }">
            {{ formatDateTime(item.created_at) }}
          </template>

          <template v-slot:item.reviewer="{ item }">
            <span v-if="item.reviewer">{{ item.reviewer.name }}</span>
            <span v-else class="text-medium-emphasis">--</span>
          </template>

          <template v-slot:item.actions="{ item }">
            <div v-if="item.status === 'pending'" class="d-flex gap-1">
              <v-btn
                icon="mdi-check"
                size="small"
                variant="text"
                color="success"
                :disabled="processing"
                @click="openApproveDialog(item)"
              ></v-btn>
              <v-btn
                icon="mdi-close"
                size="small"
                variant="text"
                color="error"
                :disabled="processing"
                @click="openRejectDialog(item)"
              ></v-btn>
            </div>
            <span v-else class="text-caption text-medium-emphasis">
              {{ item.reviewed_at ? formatDateTime(item.reviewed_at) : "" }}
            </span>
          </template>

          <template v-slot:no-data>
            <div class="text-center py-8">
              <v-icon size="52" color="grey"
                >mdi-clipboard-text-search-outline</v-icon
              >
              <p class="text-h6 mt-3 mb-1">No access requests found</p>
              <p class="text-body-2 text-medium-emphasis mb-4">
                Adjust the filters or clear them to view available requests.
              </p>
              <v-btn
                variant="outlined"
                color="primary"
                @click="clearFilters"
                :disabled="!hasActiveFilters"
              >
                Clear filters
              </v-btn>
            </div>
          </template>
        </v-data-table>
      </div>
    </div>

    <v-dialog v-model="approveDialog" max-width="500">
      <v-card class="modern-dialog">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper primary">
            <v-icon size="24">mdi-check-circle</v-icon>
          </div>
          <div>
            <div class="dialog-title">Approve Access Request</div>
            <div class="dialog-subtitle">
              This action will grant the requested access
            </div>
          </div>
        </v-card-title>
        <v-card-text class="dialog-content">
          <p>
            Approving
            <strong>{{ getModuleLabel(selectedRequest?.module) }}</strong>
            request from
            <strong>{{ selectedRequest?.requester?.name }}</strong>
            <span v-if="selectedRequest?.date">
              for
              <strong>{{ formatDate(selectedRequest?.date) }}</strong> </span
            >.
          </p>
          <p class="mt-3 text-caption text-medium-emphasis">
            This will notify the requester and update their access permissions.
          </p>
        </v-card-text>
        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <button
            class="dialog-btn dialog-btn-cancel"
            @click="approveDialog = false"
          >
            Cancel
          </button>
          <button
            class="dialog-btn dialog-btn-primary"
            :disabled="processing"
            @click="confirmApprove"
          >
            <v-icon size="18">mdi-check</v-icon>
            Approve
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="rejectDialog" max-width="500" persistent>
      <v-card class="modern-dialog">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper danger">
            <v-icon size="24">mdi-close-circle</v-icon>
          </div>
          <div>
            <div class="dialog-title">Reject Access Request</div>
            <div class="dialog-subtitle">
              Please provide a reason for rejection
            </div>
          </div>
        </v-card-title>
        <v-card-text class="dialog-content">
          <p>
            Rejecting
            <strong>{{ getModuleLabel(selectedRequest?.module) }}</strong>
            request from
            <strong>{{ selectedRequest?.requester?.name }}</strong>
            <span v-if="selectedRequest?.date">
              for
              <strong>{{ formatDate(selectedRequest?.date) }}</strong> </span
            >.
          </p>
          <v-textarea
            v-model="rejectNotes"
            label="Rejection Reason"
            variant="outlined"
            rows="3"
            class="mt-4"
            :rules="[(v) => !!v || 'Reason is required']"
            placeholder="Provide a reason for rejecting this request"
          ></v-textarea>
        </v-card-text>
        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <button
            class="dialog-btn dialog-btn-cancel"
            @click="
              rejectDialog = false;
              rejectNotes = '';
            "
          >
            Cancel
          </button>
          <button
            class="dialog-btn dialog-btn-danger"
            :disabled="!rejectNotes || processing"
            @click="rejectRequest"
          >
            <v-icon size="18">mdi-close</v-icon>
            Reject
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, reactive, computed, watch, onMounted } from "vue";
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
  "employee-savings",
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
  "employee-savings": "Employee Savings",
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
  "employee-savings": "pink",
  "salary-adjustments": "cyan",
};

const tabItems = [
  {
    key: "all",
    label: "All Requests",
    icon: "mdi-view-list",
    countKey: "total",
  },
  { key: "attendance", label: "Attendance", icon: "mdi-clock-check-outline" },
  {
    key: "attendance-settings",
    label: "Attendance Settings",
    icon: "mdi-cog-outline",
  },
  { key: "government-rates", label: "Gov. Rates", icon: "mdi-bank-outline" },
  { key: "deductions", label: "Deductions", icon: "mdi-minus-circle-outline" },
  { key: "allowances", label: "Allowances", icon: "mdi-plus-circle-outline" },
  {
    key: "thirteenth-month-pay",
    label: "13th Month",
    icon: "mdi-gift-outline",
  },
  { key: "loans", label: "Loans", icon: "mdi-cash-multiple" },
  { key: "cash-bonds", label: "Cash Bonds", icon: "mdi-certificate-outline" },
  {
    key: "employee-savings",
    label: "Employee Savings",
    icon: "mdi-piggy-bank-outline",
  },
  { key: "salary-adjustments", label: "Salary Adj.", icon: "mdi-cash-edit" },
];

const primaryTabKeys = [
  "all",
  "attendance",
  "attendance-settings",
  "government-rates",
  "deductions",
];

const activeTab = ref(route.query.tab || "all");
const loading = ref(false);
const processing = ref(false);
const requests = ref([]);
const approveDialog = ref(false);
const rejectDialog = ref(false);
const selectedRequest = ref(null);
const rejectNotes = ref("");

const filters = reactive({
  date: "",
  status: null,
  module: null,
  search: "",
});

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
  "employee-savings": 0,
  "salary-adjustments": 0,
});

const statusOptions = [
  { title: "Pending", value: "pending" },
  { title: "Approved", value: "approved" },
  { title: "Rejected", value: "rejected" },
];

const requestTypeOptions = ALL_MODULES.map((module) => ({
  title: moduleLabels[module] || module,
  value: module,
}));

const primaryTabs = computed(() =>
  tabItems.filter((tab) => primaryTabKeys.includes(tab.key)),
);

const overflowTabs = computed(() =>
  tabItems.filter((tab) => !primaryTabKeys.includes(tab.key)),
);

const overflowTabActive = computed(() =>
  overflowTabs.value.some((tab) => tab.key === activeTab.value),
);

const overflowPendingTotal = computed(() =>
  overflowTabs.value.reduce(
    (total, tab) => total + getPendingCount(tab.key),
    0,
  ),
);

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

const filteredRequests = computed(() => {
  let filtered = requests.value;

  if (activeTab.value !== "all") {
    filtered = filtered.filter((r) => r.module === activeTab.value);
  } else if (filters.module) {
    filtered = filtered.filter((r) => r.module === filters.module);
  }

  if (filters.date) {
    filtered = filtered.filter((r) => {
      const requestDate = (r.date || "").slice(0, 10);
      return requestDate === filters.date;
    });
  }

  if (filters.status) {
    filtered = filtered.filter((r) => r.status === filters.status);
  }

  if (filters.search) {
    const query = filters.search.toLowerCase();
    filtered = filtered.filter((r) => {
      const name = (r.requester?.name || "").toLowerCase();
      const role = (r.requester?.role || "").toLowerCase();
      return name.includes(query) || role.includes(query);
    });
  }

  return filtered;
});

const hasActiveFilters = computed(
  () =>
    !!filters.date || !!filters.status || !!filters.module || !!filters.search,
);

const clearFilters = () => {
  filters.date = "";
  filters.status = null;
  filters.module = null;
  filters.search = "";
};

function getPendingCount(tabKey) {
  if (tabKey === "all") {
    return pendingCounts.value.total || 0;
  }
  return pendingCounts.value[tabKey] || 0;
}

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
  const colors = {
    pending: "warning",
    approved: "success",
    rejected: "error",
  };
  return colors[status] || "grey";
}

function formatStatusLabel(status) {
  if (!status) return "Unknown";
  return status.charAt(0).toUpperCase() + status.slice(1);
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
      "employee-savings": 0,
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

const openApproveDialog = (request) => {
  selectedRequest.value = request;
  approveDialog.value = true;
};

const confirmApprove = async () => {
  if (!selectedRequest.value) return;
  await approveRequest(selectedRequest.value);
  approveDialog.value = false;
  selectedRequest.value = null;
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

watch(
  () => activeTab.value,
  (newTab) => {
    if (newTab !== "all") {
      filters.module = null;
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

<style scoped lang="scss">
.requests-page {
  max-width: 1600px;
  margin: 0 auto;
}

.modern-card {
  padding: 0;
}

.tab-container {
  margin-bottom: 24px;
  overflow: hidden;
}

.modern-tabs {
  display: flex;
  gap: 4px;
  padding: 8px;
  background: rgba(0, 31, 61, 0.02);
  border-bottom: 1px solid rgba(0, 31, 61, 0.08);
  overflow-x: auto;

  &::-webkit-scrollbar {
    height: 4px;
  }

  &::-webkit-scrollbar-track {
    background: rgba(0, 31, 61, 0.04);
  }

  &::-webkit-scrollbar-thumb {
    background: rgba(237, 152, 95, 0.3);
    border-radius: 2px;
  }
}

.modern-tab {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 20px;
  border: none;
  border-radius: 10px;
  background: transparent;
  color: rgba(0, 31, 61, 0.7);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  white-space: nowrap;

  .v-icon {
    color: rgba(0, 31, 61, 0.5) !important;
    transition: color 0.3s ease;
  }

  &:hover {
    background: rgba(237, 152, 95, 0.08);
    color: #001f3d;

    .v-icon {
      color: #ed985f !important;
    }
  }

  &.active {
    background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);

    .v-icon {
      color: #ffffff !important;
    }

    .tab-badge {
      background: #ffffff;
      color: #ed985f;
    }
  }
}

.more-tab {
  margin-left: auto;
}

.tab-badge {
  min-width: 22px;
  height: 22px;
  padding: 0 6px;
  border-radius: 11px;
  color: #ffffff;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  font-size: 11px;
  font-weight: 700;
  display: flex;
  align-items: center;
  justify-content: center;
}

.tab-content {
  padding: 24px;
}

.requests-filter-panel {
  padding: 0 0 14px;
}

.requests-data-table {
  border: 1px solid rgba(237, 152, 95, 0.4);
  border-radius: 14px;
  overflow: hidden;
}

.requester-name {
  color: #001f3d;
  font-size: 14px;
  font-weight: 600;
}

.requester-meta {
  color: rgba(0, 31, 61, 0.58);
  font-size: 12px;
}

.reason-text {
  max-width: 250px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

:deep(.requests-data-table thead th) {
  color: #001f3d;
  font-size: 14px;
  font-weight: 700;
  border-bottom: 1px solid rgba(0, 31, 61, 0.12) !important;
}

:deep(.requests-data-table tbody td) {
  border-bottom: 1px solid rgba(0, 31, 61, 0.08) !important;
}

:deep(.requests-data-table tbody tr:hover) {
  background: rgba(237, 152, 95, 0.04);
}

:deep(.requests-data-table .v-data-table-footer) {
  border-top: 1px solid rgba(0, 31, 61, 0.08);
}

:deep(.more-tabs-menu) {
  min-width: 230px;
}

:deep(.more-tabs-menu .v-list-item--active) {
  background: rgba(237, 152, 95, 0.12);
}

.dialog-btn-danger {
  background: linear-gradient(135deg, #f44336 0%, #e53935 100%);
  color: #ffffff;
}

@media (max-width: 1024px) {
  .header-content {
    flex-direction: column;
    align-items: flex-start;
  }

  .reason-text {
    max-width: 180px;
  }
}

@media (max-width: 768px) {
  .tab-content {
    padding: 16px;
  }

  .requests-filter-panel {
    padding-bottom: 10px;
  }
}
</style>
