<template>
  <div class="audit-trail-page">
    <!-- Modern Page Header -->
    <div class="page-header">
      <div class="page-title-section">
        <div class="page-icon-badge">
          <v-icon size="24">mdi-shield-search</v-icon>
        </div>
        <div>
          <h1 class="page-title">Audit Trail</h1>
          <p class="page-subtitle">
            Complete activity log of all system operations
          </p>
        </div>
      </div>
      <button class="export-btn" @click="exportLogs" :disabled="exporting">
        <v-icon size="18">mdi-download</v-icon>
        <span>{{ exporting ? "Exporting..." : "Export Logs" }}</span>
      </button>
    </div>

    <!-- Modern Statistics Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-primary">
          <v-icon size="24">mdi-file-document-multiple</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ statistics.total }}</div>
          <div class="stat-label">Total Logs</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-success">
          <v-icon size="24">mdi-calendar-today</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ statistics.today }}</div>
          <div class="stat-label">Today's Activities</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-info">
          <v-icon size="24">mdi-account-multiple</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ statistics.activeUsers }}</div>
          <div class="stat-label">Active Users</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-warning">
          <v-icon size="24">mdi-folder-multiple</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ statistics.modules }}</div>
          <div class="stat-label">Active Modules</div>
        </div>
      </div>
    </div>

    <!-- Modern Filters Section -->
    <div class="filters-section">
      <div class="filters-header">
        <div class="filters-icon-badge">
          <v-icon size="18">mdi-filter</v-icon>
        </div>
        <h2 class="filters-title">Filters</h2>
      </div>
      <div class="filters-content">
        <div class="filters-grid">
          <v-select
            v-model="filters.module"
            label="Module"
            :items="modules"
            item-title="text"
            item-value="value"
            density="compact"
            variant="outlined"
            clearable
            prepend-inner-icon="mdi-folder"
          >
            <template v-slot:item="{ props, item }">
              <v-list-item v-bind="props">
                <template v-slot:prepend>
                  <v-icon
                    :icon="item.raw.icon"
                    :color="item.raw.color"
                  ></v-icon>
                </template>
              </v-list-item>
            </template>
          </v-select>
          <v-select
            v-model="filters.action"
            label="Action"
            :items="actions"
            item-title="text"
            item-value="value"
            density="compact"
            variant="outlined"
            clearable
            prepend-inner-icon="mdi-lightning-bolt"
          ></v-select>
          <v-text-field
            v-model="filters.date_from"
            label="Date From"
            type="date"
            density="compact"
            variant="outlined"
            clearable
            prepend-inner-icon="mdi-calendar"
          ></v-text-field>
          <v-text-field
            v-model="filters.date_to"
            label="Date To"
            type="date"
            density="compact"
            variant="outlined"
            clearable
            prepend-inner-icon="mdi-calendar"
          ></v-text-field>
          <v-text-field
            v-model="filters.search"
            label="Search description..."
            density="compact"
            variant="outlined"
            clearable
            prepend-inner-icon="mdi-magnify"
            class="search-full-width"
          ></v-text-field>
          <v-autocomplete
            v-model="filters.user_id"
            label="User"
            :items="users"
            item-title="name"
            item-value="id"
            density="compact"
            variant="outlined"
            clearable
            prepend-inner-icon="mdi-account"
            :loading="loadingUsers"
          ></v-autocomplete>
          <button
            class="apply-filters-btn"
            @click="fetchAuditLogs"
            :disabled="loading"
          >
            <v-icon size="18">mdi-check</v-icon>
            <span>{{ loading ? "Loading..." : "Apply Filters" }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Modern Audit Logs Table -->
    <div class="table-section">
      <div class="table-header">
        <div class="table-title-wrapper">
          <div class="table-icon-badge">
            <v-icon size="18">mdi-format-list-bulleted</v-icon>
          </div>
          <h2 class="table-title">Activity Log</h2>
        </div>
        <div class="records-badge">{{ auditLogs.length }} records</div>
      </div>

      <v-data-table
        :headers="headers"
        :items="filteredLogs"
        :loading="loading"
        :items-per-page="25"
        class="elevation-0"
      >
        <!-- Date/Time Column -->
        <template v-slot:item.created_at="{ item }">
          <div class="d-flex flex-column py-2">
            <span class="font-weight-medium">{{
              formatDate(item.created_at)
            }}</span>
            <span class="text-caption text-medium-emphasis">
              {{ formatTime(item.created_at) }}
            </span>
          </div>
        </template>

        <!-- User Column -->
        <template v-slot:item.user="{ item }">
          <div class="d-flex align-center">
            <v-avatar size="32" color="primary" class="mr-2">
              <v-icon size="small">mdi-account</v-icon>
            </v-avatar>
            <div>
              <div class="font-weight-medium">
                {{ item.user?.full_name || "System" }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ item.user?.email || "N/A" }}
              </div>
            </div>
          </div>
        </template>

        <!-- Module Column -->
        <template v-slot:item.module="{ item }">
          <v-chip
            :prepend-icon="getModuleIcon(item.module)"
            size="small"
            variant="flat"
            color="grey-lighten-2"
          >
            {{ formatModule(item.module) }}
          </v-chip>
        </template>

        <!-- Action Column -->
        <template v-slot:item.action="{ item }">
          <v-chip
            size="small"
            :color="getActionColor(item.action)"
            variant="flat"
          >
            {{ formatAction(item.action) }}
          </v-chip>
        </template>

        <!-- Description Column -->
        <template v-slot:item.description="{ item }">
          <div class="text-body-2" style="max-width: 400px">
            {{ item.description || "No description" }}
          </div>
        </template>

        <!-- Actions Column -->
        <template v-slot:item.actions="{ item }">
          <v-btn icon size="small" variant="text" @click="viewDetails(item)">
            <v-icon>mdi-eye</v-icon>
            <v-tooltip activator="parent" location="top"
              >View Details</v-tooltip
            >
          </v-btn>
        </template>

        <!-- Loading -->
        <template v-slot:loading>
          <v-skeleton-loader type="table-row@10"></v-skeleton-loader>
        </template>

        <!-- No Data -->
        <template v-slot:no-data>
          <div class="text-center pa-8">
            <v-icon size="64" color="grey">mdi-database-off</v-icon>
            <div class="text-h6 mt-4">No audit logs found</div>
            <div class="text-body-2 text-medium-emphasis">
              Try adjusting your filters
            </div>
          </div>
        </template>
      </v-data-table>
    </div>

    <!-- Modern Details Dialog -->
    <v-dialog v-model="showDetailsDialog" max-width="800px" scrollable>
      <v-card class="modern-dialog">
        <div class="dialog-header">
          <div class="dialog-icon-wrapper">
            <v-icon size="20">mdi-information</v-icon>
          </div>
          <div>
            <div class="dialog-title">Audit Log Details</div>
            <div class="dialog-subtitle">
              Complete information about this activity
            </div>
          </div>
        </div>

        <v-card-text class="pa-6" v-if="selectedLog">
          <v-row>
            <!-- Basic Information -->
            <v-col cols="12">
              <div class="text-h6 mb-4">Basic Information</div>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                label="User"
                :model-value="selectedLog.user?.full_name || 'System'"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                label="Date & Time"
                :model-value="formatDateTime(selectedLog.created_at)"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                label="Module"
                :model-value="formatModule(selectedLog.module)"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                label="Action"
                :model-value="formatAction(selectedLog.action)"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12">
              <v-textarea
                label="Description"
                :model-value="selectedLog.description"
                readonly
                variant="outlined"
                rows="3"
              ></v-textarea>
            </v-col>

            <!-- Technical Information -->
            <v-col cols="12">
              <div class="text-h6 mb-4 mt-2">Technical Information</div>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                label="IP Address"
                :model-value="selectedLog.ip_address || 'N/A'"
                readonly
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-ip"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                label="Log ID"
                :model-value="selectedLog.id"
                readonly
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-key"
              ></v-text-field>
            </v-col>

            <v-col cols="12">
              <v-text-field
                label="User Agent"
                :model-value="selectedLog.user_agent || 'N/A'"
                readonly
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-devices"
              ></v-text-field>
            </v-col>

            <!-- Changes (Old vs New Values) -->
            <v-col
              cols="12"
              v-if="selectedLog.old_values || selectedLog.new_values"
            >
              <div class="text-h6 mb-4 mt-2">Data Changes</div>
            </v-col>

            <v-col cols="12" md="6" v-if="selectedLog.old_values">
              <v-card variant="outlined">
                <v-card-title class="bg-error text-white text-subtitle-1">
                  <v-icon start size="small">mdi-minus-circle</v-icon>
                  Old Values
                </v-card-title>
                <v-card-text>
                  <pre class="text-caption">{{
                    formatJSON(selectedLog.old_values)
                  }}</pre>
                </v-card-text>
              </v-card>
            </v-col>

            <v-col cols="12" md="6" v-if="selectedLog.new_values">
              <v-card variant="outlined">
                <v-card-title class="bg-success text-white text-subtitle-1">
                  <v-icon start size="small">mdi-plus-circle</v-icon>
                  New Values
                </v-card-title>
                <v-card-text>
                  <pre class="text-caption">{{
                    formatJSON(selectedLog.new_values)
                  }}</pre>
                </v-card-text>
              </v-card>
            </v-col>
          </v-row>
        </v-card-text>

        <div class="dialog-divider"></div>
        <div class="dialog-actions">
          <button class="dialog-close-btn" @click="showDetailsDialog = false">
            Close
          </button>
        </div>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import auditLogService from "@/services/auditLogService";
import { useToast } from "vue-toastification";

const toast = useToast();

// Data
const loading = ref(false);
const loadingUsers = ref(false);
const exporting = ref(false);
const auditLogs = ref([]);
const users = ref([]);
const showDetailsDialog = ref(false);
const selectedLog = ref(null);

// Filters
const filters = ref({
  module: null,
  action: null,
  date_from: null,
  date_to: null,
  user_id: null,
  search: "",
});

// Statistics
const statistics = ref({
  total: 0,
  today: 0,
  activeUsers: 0,
  modules: 0,
});

// Available modules and actions
const modules = auditLogService.getAvailableModules();
const actions = auditLogService.getAvailableActions();

// Table headers
const headers = [
  { title: "Date & Time", key: "created_at", sortable: true, width: "150px" },
  { title: "User", key: "user", sortable: false, width: "200px" },
  { title: "Module", key: "module", sortable: true, width: "150px" },
  { title: "Action", key: "action", sortable: true, width: "150px" },
  { title: "Description", key: "description", sortable: false },
  {
    title: "Actions",
    key: "actions",
    sortable: false,
    width: "80px",
    align: "center",
  },
];

// Computed
const filteredLogs = computed(() => {
  if (!filters.value.search) return auditLogs.value;

  const search = filters.value.search.toLowerCase();
  return auditLogs.value.filter(
    (log) =>
      log.description?.toLowerCase().includes(search) ||
      log.user?.name?.toLowerCase().includes(search) ||
      log.module?.toLowerCase().includes(search) ||
      log.action?.toLowerCase().includes(search),
  );
});

// Methods
async function fetchAuditLogs() {
  loading.value = true;
  try {
    const params = {};
    if (filters.value.module) params.module = filters.value.module;
    if (filters.value.action) params.action = filters.value.action;
    if (filters.value.date_from) params.date_from = filters.value.date_from;
    if (filters.value.date_to) params.date_to = filters.value.date_to;
    if (filters.value.user_id) params.user_id = filters.value.user_id;

    const response = await auditLogService.getAll(params);
    auditLogs.value = response.data || [];

    // Update statistics
    updateStatistics();
  } catch (error) {
    console.error("Error fetching audit logs:", error);
    toast.error("Failed to load audit logs");
  } finally {
    loading.value = false;
  }
}

async function fetchUsers() {
  loadingUsers.value = true;
  try {
    // Assuming you have a user service
    const response = await fetch("/api/users", {
      headers: {
        Authorization: `Bearer ${localStorage.getItem("token")}`,
      },
    });
    const data = await response.json();
    users.value = data.data || data;
  } catch (error) {
    console.error("Error fetching users:", error);
  } finally {
    loadingUsers.value = false;
  }
}

function updateStatistics() {
  statistics.value.total = auditLogs.value.length;

  // Count today's activities
  const today = new Date().toISOString().split("T")[0];
  statistics.value.today = auditLogs.value.filter((log) => {
    const logDate = new Date(log.created_at).toISOString().split("T")[0];
    return logDate === today;
  }).length;

  // Count unique users
  const uniqueUsers = new Set(
    auditLogs.value.map((log) => log.user_id).filter(Boolean),
  );
  statistics.value.activeUsers = uniqueUsers.size;

  // Count unique modules
  const uniqueModules = new Set(
    auditLogs.value.map((log) => log.module).filter(Boolean),
  );
  statistics.value.modules = uniqueModules.size;
}

function viewDetails(log) {
  selectedLog.value = log;
  showDetailsDialog.value = true;
}

async function exportLogs() {
  exporting.value = true;
  try {
    const params = { ...filters.value };
    const blob = await auditLogService.exportLogs(params);

    // Create download link
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `audit-logs-${new Date().toISOString().split("T")[0]}.xlsx`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);

    toast.success("Audit logs exported successfully");
  } catch (error) {
    console.error("Error exporting logs:", error);
    toast.error("Failed to export audit logs");
  } finally {
    exporting.value = false;
  }
}

function formatDate(date) {
  return new Date(date).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

function formatTime(date) {
  return new Date(date).toLocaleTimeString("en-US", {
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
  });
}

function formatDateTime(date) {
  return new Date(date).toLocaleString("en-US", {
    year: "numeric",
    month: "long",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
  });
}

function formatModule(module) {
  return module
    ? module.replace(/_/g, " ").replace(/\b\w/g, (l) => l.toUpperCase())
    : "N/A";
}

function formatAction(action) {
  return auditLogService.formatAction(action);
}

function getActionColor(action) {
  return auditLogService.getActionColor(action);
}

function getModuleIcon(module) {
  return auditLogService.getModuleIcon(module);
}

function formatJSON(obj) {
  return JSON.stringify(obj, null, 2);
}

// Lifecycle
onMounted(() => {
  fetchAuditLogs();
  fetchUsers();
});
</script>

<style scoped lang="scss">
.audit-trail-page {
  max-width: 1600px;
  margin: 0 auto;
}

// Page Header
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 32px;
  gap: 24px;
  flex-wrap: wrap;
}

.page-title-section {
  display: flex;
  align-items: center;
  gap: 16px;
}

.page-icon-badge {
  width: 56px;
  height: 56px;
  border-radius: 14px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);
  flex-shrink: 0;

  .v-icon {
    color: #ffffff !important;
  }
}

.page-title {
  font-size: 28px;
  font-weight: 700;
  color: #001f3d;
  margin: 0 0 4px 0;
  letter-spacing: -0.5px;
}

.page-subtitle {
  font-size: 14px;
  color: rgba(0, 31, 61, 0.6);
  margin: 0;
}

.export-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  border: none;
  border-radius: 10px;
  color: #ffffff;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);

  .v-icon {
    color: #ffffff !important;
  }

  &:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }

  &:not(:disabled):hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(237, 152, 95, 0.4);
  }
}

// Statistics Cards
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-bottom: 32px;
}

.stat-card {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  padding: 24px;
  display: flex;
  align-items: center;
  gap: 16px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  transition: all 0.3s ease;

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
  }
}

.stat-icon-wrapper {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;

  &.stat-icon-primary {
    background: linear-gradient(
      135deg,
      rgba(237, 152, 95, 0.12) 0%,
      rgba(247, 185, 128, 0.08) 100%
    );
    .v-icon {
      color: #ed985f !important;
    }
  }

  &.stat-icon-success {
    background: linear-gradient(
      135deg,
      rgba(76, 175, 80, 0.12) 0%,
      rgba(76, 175, 80, 0.08) 100%
    );
    .v-icon {
      color: #4caf50 !important;
    }
  }

  &.stat-icon-info {
    background: linear-gradient(
      135deg,
      rgba(33, 150, 243, 0.12) 0%,
      rgba(33, 150, 243, 0.08) 100%
    );
    .v-icon {
      color: #2196f3 !important;
    }
  }

  &.stat-icon-warning {
    background: linear-gradient(
      135deg,
      rgba(255, 152, 0, 0.12) 0%,
      rgba(255, 152, 0, 0.08) 100%
    );
    .v-icon {
      color: #ff9800 !important;
    }
  }
}

.stat-content {
  flex: 1;
}

.stat-value {
  font-size: 28px;
  font-weight: 700;
  color: #001f3d;
  line-height: 1;
  margin-bottom: 6px;
}

.stat-label {
  font-size: 13px;
  font-weight: 600;
  color: rgba(0, 31, 61, 0.6);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

// Filters Section
.filters-section {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  margin-bottom: 32px;
  overflow: hidden;
}

.filters-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 20px 24px;
  background: linear-gradient(
    135deg,
    rgba(0, 31, 61, 0.02) 0%,
    rgba(237, 152, 95, 0.02) 100%
  );
  border-bottom: 1px solid rgba(0, 31, 61, 0.08);
}

.filters-icon-badge {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.25);

  .v-icon {
    color: #ffffff !important;
  }
}

.filters-title {
  font-size: 16px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
  letter-spacing: -0.3px;
}

.filters-content {
  padding: 24px;
}

.filters-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
  gap: 16px;
  align-items: end;

  .search-full-width {
    grid-column: span 2;

    @media (max-width: 768px) {
      grid-column: span 1;
    }
  }
}

.apply-filters-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 11px 24px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  border: none;
  border-radius: 10px;
  color: #ffffff;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);
  width: 100%;

  .v-icon {
    color: #ffffff !important;
  }

  &:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }

  &:not(:disabled):hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(237, 152, 95, 0.4);
  }
}

// Table Section
.table-section {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  overflow: hidden;
}

.table-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  background: linear-gradient(
    135deg,
    rgba(0, 31, 61, 0.02) 0%,
    rgba(237, 152, 95, 0.02) 100%
  );
  border-bottom: 1px solid rgba(0, 31, 61, 0.08);
}

.table-title-wrapper {
  display: flex;
  align-items: center;
  gap: 12px;
}

.table-icon-badge {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.25);

  .v-icon {
    color: #ffffff !important;
  }
}

.table-title {
  font-size: 16px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
  letter-spacing: -0.3px;
}

.records-badge {
  padding: 6px 14px;
  background: rgba(237, 152, 95, 0.1);
  border: 1px solid rgba(237, 152, 95, 0.2);
  border-radius: 8px;
  color: #ed985f;
  font-size: 13px;
  font-weight: 600;
}

// Dialog
.modern-dialog {
  border-radius: 16px !important;
}

.dialog-header {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 24px;
  background: linear-gradient(
    135deg,
    rgba(0, 31, 61, 0.02) 0%,
    rgba(237, 152, 95, 0.02) 100%
  );
  border-bottom: 1px solid rgba(0, 31, 61, 0.08);
}

.dialog-icon-wrapper {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.25);

  .v-icon {
    color: #ffffff !important;
  }
}

.dialog-title {
  font-size: 20px;
  font-weight: 700;
  color: #001f3d;
  margin-bottom: 4px;
}

.dialog-subtitle {
  font-size: 13px;
  color: rgba(0, 31, 61, 0.6);
}

.dialog-divider {
  height: 1px;
  background: rgba(0, 31, 61, 0.08);
}

.dialog-actions {
  padding: 16px 24px;
  background: rgba(0, 31, 61, 0.02);
  border-top: 1px solid rgba(0, 31, 61, 0.08);
  display: flex;
  justify-content: flex-end;
}

.dialog-close-btn {
  padding: 10px 24px;
  background: rgba(0, 31, 61, 0.06);
  border: 1px solid rgba(0, 31, 61, 0.1);
  border-radius: 8px;
  color: rgba(0, 31, 61, 0.8);
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;

  &:hover {
    background: rgba(0, 31, 61, 0.1);
    border-color: rgba(0, 31, 61, 0.15);
  }
}

pre {
  background: rgba(0, 31, 61, 0.03);
  border: 1px solid rgba(0, 31, 61, 0.08);
  padding: 12px;
  border-radius: 8px;
  overflow-x: auto;
  white-space: pre-wrap;
  word-wrap: break-word;
  color: #001f3d;
  font-size: 12px;
}

.v-data-table {
  font-size: 0.875rem;
}
</style>
