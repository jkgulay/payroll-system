<template>
  <v-container fluid class="pa-6">
    <!-- Header -->
    <v-row class="mb-4">
      <v-col>
        <div class="d-flex align-center mb-2">
          <v-icon icon="mdi-shield-search" size="32" color="primary" class="mr-3" />
          <div>
            <h1 class="text-h4 font-weight-bold">Audit Trail</h1>
            <p class="text-body-2 text-medium-emphasis">
              Complete activity log of all system operations
            </p>
          </div>
        </div>
      </v-col>
      <v-col cols="auto">
        <v-btn
          color="success"
          prepend-icon="mdi-download"
          @click="exportLogs"
          :loading="exporting"
        >
          Export Logs
        </v-btn>
      </v-col>
    </v-row>

    <!-- Statistics Cards -->
    <v-row class="mb-4">
      <v-col cols="12" md="3">
        <v-card class="pa-4" elevation="2">
          <div class="d-flex align-center">
            <v-avatar color="primary" size="48" class="mr-3">
              <v-icon>mdi-file-document-multiple</v-icon>
            </v-avatar>
            <div>
              <div class="text-h5 font-weight-bold">{{ statistics.total }}</div>
              <div class="text-caption text-medium-emphasis">Total Logs</div>
            </div>
          </div>
        </v-card>
      </v-col>
      <v-col cols="12" md="3">
        <v-card class="pa-4" elevation="2">
          <div class="d-flex align-center">
            <v-avatar color="success" size="48" class="mr-3">
              <v-icon>mdi-calendar-today</v-icon>
            </v-avatar>
            <div>
              <div class="text-h5 font-weight-bold">{{ statistics.today }}</div>
              <div class="text-caption text-medium-emphasis">Today's Activities</div>
            </div>
          </div>
        </v-card>
      </v-col>
      <v-col cols="12" md="3">
        <v-card class="pa-4" elevation="2">
          <div class="d-flex align-center">
            <v-avatar color="info" size="48" class="mr-3">
              <v-icon>mdi-account-multiple</v-icon>
            </v-avatar>
            <div>
              <div class="text-h5 font-weight-bold">{{ statistics.activeUsers }}</div>
              <div class="text-caption text-medium-emphasis">Active Users</div>
            </div>
          </div>
        </v-card>
      </v-col>
      <v-col cols="12" md="3">
        <v-card class="pa-4" elevation="2">
          <div class="d-flex align-center">
            <v-avatar color="warning" size="48" class="mr-3">
              <v-icon>mdi-folder-multiple</v-icon>
            </v-avatar>
            <div>
              <div class="text-h5 font-weight-bold">{{ statistics.modules }}</div>
              <div class="text-caption text-medium-emphasis">Active Modules</div>
            </div>
          </div>
        </v-card>
      </v-col>
    </v-row>

    <!-- Filters Card -->
    <v-card class="mb-4" elevation="2">
      <v-card-title class="bg-grey-lighten-4">
        <v-icon start>mdi-filter</v-icon>
        Filters
      </v-card-title>
      <v-card-text class="pt-4">
        <v-row>
          <v-col cols="12" md="3">
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
                    <v-icon :icon="item.raw.icon" :color="item.raw.color"></v-icon>
                  </template>
                </v-list-item>
              </template>
            </v-select>
          </v-col>
          <v-col cols="12" md="3">
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
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field
              v-model="filters.date_from"
              label="Date From"
              type="date"
              density="compact"
              variant="outlined"
              clearable
              prepend-inner-icon="mdi-calendar"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="3">
            <v-text-field
              v-model="filters.date_to"
              label="Date To"
              type="date"
              density="compact"
              variant="outlined"
              clearable
              prepend-inner-icon="mdi-calendar"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="6">
            <v-text-field
              v-model="filters.search"
              label="Search description..."
              density="compact"
              variant="outlined"
              clearable
              prepend-inner-icon="mdi-magnify"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="3">
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
          </v-col>
          <v-col cols="12" md="3" class="d-flex align-center">
            <v-btn
              color="primary"
              block
              @click="fetchAuditLogs"
              :loading="loading"
            >
              Apply Filters
            </v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Audit Logs Table -->
    <v-card elevation="2">
      <v-card-title class="bg-primary">
        <v-icon start>mdi-format-list-bulleted</v-icon>
        Activity Log
        <v-spacer></v-spacer>
        <v-chip variant="flat" color="white">
          {{ auditLogs.length }} records
        </v-chip>
      </v-card-title>

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
            <span class="font-weight-medium">{{ formatDate(item.created_at) }}</span>
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
              <div class="font-weight-medium">{{ item.user?.name || 'System' }}</div>
              <div class="text-caption text-medium-emphasis">
                {{ item.user?.email || 'N/A' }}
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
          <div class="text-body-2" style="max-width: 400px;">
            {{ item.description || 'No description' }}
          </div>
        </template>

        <!-- Actions Column -->
        <template v-slot:item.actions="{ item }">
          <v-btn
            icon
            size="small"
            variant="text"
            @click="viewDetails(item)"
          >
            <v-icon>mdi-eye</v-icon>
            <v-tooltip activator="parent" location="top">View Details</v-tooltip>
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
    </v-card>

    <!-- Details Dialog -->
    <v-dialog v-model="showDetailsDialog" max-width="800px" scrollable>
      <v-card>
        <v-card-title class="bg-primary text-white">
          <v-icon start>mdi-information</v-icon>
          Audit Log Details
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pa-6" v-if="selectedLog">
          <v-row>
            <!-- Basic Information -->
            <v-col cols="12">
              <div class="text-h6 mb-4">Basic Information</div>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                label="User"
                :model-value="selectedLog.user?.name || 'System'"
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
            <v-col cols="12" v-if="selectedLog.old_values || selectedLog.new_values">
              <div class="text-h6 mb-4 mt-2">Data Changes</div>
            </v-col>

            <v-col cols="12" md="6" v-if="selectedLog.old_values">
              <v-card variant="outlined">
                <v-card-title class="bg-error text-white text-subtitle-1">
                  <v-icon start size="small">mdi-minus-circle</v-icon>
                  Old Values
                </v-card-title>
                <v-card-text>
                  <pre class="text-caption">{{ formatJSON(selectedLog.old_values) }}</pre>
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
                  <pre class="text-caption">{{ formatJSON(selectedLog.new_values) }}</pre>
                </v-card-text>
              </v-card>
            </v-col>
          </v-row>
        </v-card-text>

        <v-divider></v-divider>
        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="showDetailsDialog = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import auditLogService from '@/services/auditLogService';
import { useToast } from 'vue-toastification';

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
  search: '',
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
  { title: 'Date & Time', key: 'created_at', sortable: true, width: '150px' },
  { title: 'User', key: 'user', sortable: false, width: '200px' },
  { title: 'Module', key: 'module', sortable: true, width: '150px' },
  { title: 'Action', key: 'action', sortable: true, width: '150px' },
  { title: 'Description', key: 'description', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false, width: '80px', align: 'center' },
];

// Computed
const filteredLogs = computed(() => {
  if (!filters.value.search) return auditLogs.value;
  
  const search = filters.value.search.toLowerCase();
  return auditLogs.value.filter(log => 
    log.description?.toLowerCase().includes(search) ||
    log.user?.name?.toLowerCase().includes(search) ||
    log.module?.toLowerCase().includes(search) ||
    log.action?.toLowerCase().includes(search)
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
    console.error('Error fetching audit logs:', error);
    toast.error('Failed to load audit logs');
  } finally {
    loading.value = false;
  }
}

async function fetchUsers() {
  loadingUsers.value = true;
  try {
    // Assuming you have a user service
    const response = await fetch('/api/users', {
      headers: {
        'Authorization': `Bearer ${localStorage.getItem('token')}`,
      },
    });
    const data = await response.json();
    users.value = data.data || data;
  } catch (error) {
    console.error('Error fetching users:', error);
  } finally {
    loadingUsers.value = false;
  }
}

function updateStatistics() {
  statistics.value.total = auditLogs.value.length;
  
  // Count today's activities
  const today = new Date().toISOString().split('T')[0];
  statistics.value.today = auditLogs.value.filter(log => {
    const logDate = new Date(log.created_at).toISOString().split('T')[0];
    return logDate === today;
  }).length;

  // Count unique users
  const uniqueUsers = new Set(auditLogs.value.map(log => log.user_id).filter(Boolean));
  statistics.value.activeUsers = uniqueUsers.size;

  // Count unique modules
  const uniqueModules = new Set(auditLogs.value.map(log => log.module).filter(Boolean));
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
    const link = document.createElement('a');
    link.href = url;
    link.download = `audit-logs-${new Date().toISOString().split('T')[0]}.xlsx`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
    
    toast.success('Audit logs exported successfully');
  } catch (error) {
    console.error('Error exporting logs:', error);
    toast.error('Failed to export audit logs');
  } finally {
    exporting.value = false;
  }
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

function formatTime(date) {
  return new Date(date).toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
}

function formatDateTime(date) {
  return new Date(date).toLocaleString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
  });
}

function formatModule(module) {
  return module ? module.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) : 'N/A';
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

<style scoped>
pre {
  background: #f5f5f5;
  padding: 12px;
  border-radius: 4px;
  overflow-x: auto;
  white-space: pre-wrap;
  word-wrap: break-word;
}

.v-data-table {
  font-size: 0.875rem;
}
</style>
