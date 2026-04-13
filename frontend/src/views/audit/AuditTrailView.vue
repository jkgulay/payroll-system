<template>
  <div class="audit-trail-page">
    <div class="page-header">
      <div class="header-content">
        <div class="page-title-section">
          <div class="page-icon-badge">
            <v-icon size="22">mdi-shield-search</v-icon>
          </div>
          <div>
            <h1 class="page-title">Audit Trail</h1>
            <p class="page-subtitle">
              Clear, searchable activity history for administrative review
            </p>
          </div>
        </div>

        <div class="action-buttons">
          <button
            class="action-btn action-btn-secondary"
            @click="fetchAuditLogs"
            :disabled="loading"
          >
            <v-icon size="20">mdi-refresh</v-icon>
            <span>{{ loading ? "Refreshing..." : "Refresh" }}</span>
          </button>
          <button
            class="action-btn action-btn-primary"
            @click="exportLogs"
            :disabled="exporting || loading"
          >
            <v-icon size="20">mdi-download</v-icon>
            <span>{{ exporting ? "Exporting..." : "Export Logs" }}</span>
          </button>
        </div>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-primary">
          <v-icon size="22">mdi-file-document-multiple</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ statistics.total }}</div>
          <div class="stat-label">Matching Logs</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-success">
          <v-icon size="22">mdi-calendar-today</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ statistics.today }}</div>
          <div class="stat-label">Today</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-info">
          <v-icon size="22">mdi-account-multiple</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ statistics.activeUsers }}</div>
          <div class="stat-label">Users</div>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon-wrapper stat-icon-warning">
          <v-icon size="22">mdi-folder-multiple</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-value">{{ statistics.modules }}</div>
          <div class="stat-label">Modules</div>
        </div>
      </div>
    </div>

    <div class="modern-card">
      <div class="filters-section">
        <v-row class="mb-0" align="center" dense>
          <v-col cols="12" md="3">
            <v-text-field
              v-model="filters.search"
              prepend-inner-icon="mdi-magnify"
              label="Search activity logs..."
              clearable
              variant="outlined"
              density="comfortable"
              hide-details
              @update:model-value="debouncedSearch"
            ></v-text-field>
          </v-col>

          <v-col cols="12" md="2">
            <v-select
              v-model="filters.module"
              :items="modules"
              item-title="text"
              item-value="value"
              label="Module"
              clearable
              variant="outlined"
              density="comfortable"
              hide-details
              @update:model-value="applyFilters"
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
          </v-col>

          <v-col cols="12" md="2">
            <v-select
              v-model="filters.action"
              :items="actions"
              item-title="text"
              item-value="value"
              label="Activity"
              clearable
              variant="outlined"
              density="comfortable"
              hide-details
              @update:model-value="applyFilters"
            ></v-select>
          </v-col>

          <v-col cols="12" md="2">
            <v-autocomplete
              v-model="filters.user_id"
              :items="users"
              item-title="display_name"
              item-value="id"
              label="User"
              clearable
              variant="outlined"
              density="comfortable"
              hide-details
              :loading="loadingUsers"
              @update:model-value="applyFilters"
            ></v-autocomplete>
          </v-col>

          <v-col cols="12" md="2">
            <v-text-field
              v-model="filters.date"
              label="Date"
              type="date"
              clearable
              variant="outlined"
              density="comfortable"
              hide-details
              @update:model-value="applyFilters"
            ></v-text-field>
          </v-col>

          <v-col cols="auto" class="d-flex align-center">
            <v-btn
              color="#ED985F"
              variant="tonal"
              icon="mdi-refresh"
              @click="fetchAuditLogs"
              :loading="loading"
              title="Refresh"
            ></v-btn>
          </v-col>

          <v-col cols="auto" class="d-flex align-center">
            <v-btn
              color="error"
              variant="tonal"
              icon="mdi-filter-remove"
              @click="clearFilters"
              :disabled="!hasActiveFilters"
              title="Clear Filters"
            ></v-btn>
          </v-col>

          <v-col
            cols="auto"
            class="d-flex align-center"
            v-if="hasActiveFilters"
          >
            <v-chip size="small" color="info" variant="tonal">
              {{ activeFilterCount }} active filter{{
                activeFilterCount > 1 ? "s" : ""
              }}
            </v-chip>
          </v-col>
        </v-row>
      </div>

      <div class="table-section">
        <v-data-table-server
          :headers="headers"
          :items="auditLogs"
          :loading="loading"
          :items-per-page="pagination.perPage"
          :page="pagination.page"
          :items-length="pagination.total"
          :items-per-page-options="pageSizeOptions"
          @update:page="onPageChange"
          @update:items-per-page="onPerPageChange"
          hover
          class="elevation-0"
        >
          <template v-slot:item.created_at="{ item }">
            <div class="audit-date-cell">
              <span class="audit-date-primary">{{
                formatDate(item.created_at)
              }}</span>
              <span class="audit-date-secondary">{{
                formatTime(item.created_at)
              }}</span>
            </div>
          </template>

          <template v-slot:item.user="{ item }">
            <div class="audit-user-cell">
              <v-avatar size="32" color="primary" class="mr-2">
                <v-img
                  v-if="getLogActorAvatar(item)"
                  :src="getLogActorAvatar(item)"
                  :alt="`${getLogActorName(item)} profile picture`"
                  cover
                ></v-img>
                <v-icon v-else size="small">mdi-account</v-icon>
              </v-avatar>
              <div>
                <div class="audit-user-name">{{ getLogActorName(item) }}</div>
                <div class="audit-user-meta">
                  {{ getLogActorMeta(item) }}
                </div>
              </div>
            </div>
          </template>

          <template v-slot:item.module="{ item }">
            <v-chip
              :prepend-icon="getModuleIcon(item.module)"
              size="small"
              variant="tonal"
              color="grey-darken-1"
            >
              {{ formatModule(item.module) }}
            </v-chip>
          </template>

          <template v-slot:item.action="{ item }">
            <v-chip
              size="small"
              :color="getActionColor(item.action)"
              variant="tonal"
            >
              {{ formatAction(item.action) }}
            </v-chip>
          </template>

          <template v-slot:item.description="{ item }">
            <div class="audit-description-cell">
              <div class="text-body-2 audit-description-text">
                {{ getReadableDescription(item) }}
              </div>
              <div class="audit-description-meta">
                <v-chip
                  v-for="(highlight, index) in getActivityHighlights(item)"
                  :key="`${item.id}-highlight-${index}`"
                  size="x-small"
                  color="grey"
                  variant="tonal"
                >
                  {{ highlight }}
                </v-chip>
                <div v-if="shouldShowReason(item)" class="audit-reason-inline">
                  <v-icon size="14" class="audit-reason-icon"
                    >mdi-comment-alert-outline</v-icon
                  >
                  <span class="audit-reason-text"
                    >Reason: {{ getLogReason(item) }}</span
                  >
                </div>
                <v-chip
                  v-if="getLogAccessRequestId(item)"
                  size="x-small"
                  color="info"
                  variant="tonal"
                >
                  Access Request #{{ getLogAccessRequestId(item) }}
                </v-chip>
              </div>
            </div>
          </template>

          <template v-slot:item.actions="{ item }">
            <v-btn
              icon="mdi-dots-vertical"
              size="small"
              variant="text"
              title="View details"
              @click="viewDetails(item)"
            ></v-btn>
          </template>

          <template v-slot:loading>
            <v-skeleton-loader type="table-row@10"></v-skeleton-loader>
          </template>

          <template v-slot:no-data>
            <div class="text-center pa-8">
              <v-icon size="64" color="grey">mdi-database-off</v-icon>
              <div class="text-h6 mt-4">No audit logs found</div>
              <div class="text-body-2 text-medium-emphasis">
                Try adjusting your filters or date.
              </div>
              <v-btn
                class="mt-3"
                variant="outlined"
                color="primary"
                @click="clearFilters"
                :disabled="!hasActiveFilters"
              >
                Clear filters
              </v-btn>
            </div>
          </template>
        </v-data-table-server>
      </div>
    </div>

    <v-dialog v-model="showDetailsDialog" max-width="980px" scrollable>
      <v-card>
        <div class="dialog-header">
          <div class="header-icon-badge">
            <v-icon size="20">mdi-text-box-search-outline</v-icon>
          </div>
          <div>
            <h2 class="header-title">Audit Log Details</h2>
            <p class="header-subtitle">
              Human-readable summary plus field-level change history
            </p>
          </div>
        </div>

        <v-card-text class="pa-6" v-if="selectedLog">
          <v-row dense class="detail-grid">
            <v-col cols="12" md="6">
              <div class="detail-row">
                <div class="detail-label">When</div>
                <div class="detail-value">
                  {{ formatDateTime(selectedLog.created_at) }}
                </div>
              </div>
            </v-col>

            <v-col cols="12" md="6">
              <div class="detail-row">
                <div class="detail-label">Who</div>
                <div class="detail-value detail-value-column">
                  <span>{{ getLogActorName(selectedLog) }}</span>
                  <span class="detail-meta-value">{{
                    getLogActorMeta(selectedLog)
                  }}</span>
                </div>
              </div>
            </v-col>

            <v-col cols="12" md="6">
              <div class="detail-row">
                <div class="detail-label">Module</div>
                <div class="detail-value">
                  <v-chip
                    :prepend-icon="getModuleIcon(selectedLog.module)"
                    size="small"
                    variant="tonal"
                    color="grey-darken-1"
                  >
                    {{ formatModule(selectedLog.module) }}
                  </v-chip>
                </div>
              </div>
            </v-col>

            <v-col cols="12" md="6">
              <div class="detail-row">
                <div class="detail-label">Activity</div>
                <div class="detail-value">
                  <v-chip
                    size="small"
                    :color="getActionColor(selectedLog.action)"
                    variant="tonal"
                  >
                    {{ formatAction(selectedLog.action) }}
                  </v-chip>
                </div>
              </div>
            </v-col>
          </v-row>

          <v-alert
            type="info"
            variant="tonal"
            density="comfortable"
            class="mt-4 audit-summary-alert"
          >
            {{ getReadableDescription(selectedLog) }}
          </v-alert>

          <v-alert
            v-if="
              shouldShowReason(selectedLog) ||
              getLogAccessRequestId(selectedLog)
            "
            type="warning"
            variant="tonal"
            density="comfortable"
            class="mt-3 audit-context-alert"
          >
            <div v-if="shouldShowReason(selectedLog)">
              <span class="audit-context-label">Reason:</span>
              <span class="audit-context-value">{{
                getLogReason(selectedLog)
              }}</span>
            </div>
            <div v-if="getLogAccessRequestId(selectedLog)" class="mt-1">
              <span class="audit-context-label">Access Request ID:</span>
              <span class="audit-context-value"
                >#{{ getLogAccessRequestId(selectedLog) }}</span
              >
            </div>
          </v-alert>

          <div class="text-h6 mt-5 mb-3">What Changed</div>
          <v-table
            v-if="detailChangeRows.length"
            density="comfortable"
            class="detail-changes-table"
          >
            <thead>
              <tr>
                <th>Field</th>
                <th>Before</th>
                <th>After</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in detailChangeRows" :key="row.key">
                <td class="field-name-cell">{{ row.label }}</td>
                <td>{{ row.before }}</td>
                <td>{{ row.after }}</td>
              </tr>
            </tbody>
          </v-table>
          <v-alert v-else type="info" variant="tonal" density="comfortable">
            No field-level difference was recorded for this activity.
          </v-alert>

          <v-expansion-panels variant="accordion" class="mt-5">
            <v-expansion-panel>
              <v-expansion-panel-title>
                Technical Details (for IT)
              </v-expansion-panel-title>
              <v-expansion-panel-text>
                <v-row>
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
                  <v-col cols="12" md="6">
                    <v-card variant="outlined">
                      <v-card-title class="text-subtitle-1"
                        >Old Values</v-card-title
                      >
                      <v-card-text>
                        <pre class="text-caption">{{
                          formatJSON(selectedLog.old_values)
                        }}</pre>
                      </v-card-text>
                    </v-card>
                  </v-col>
                  <v-col cols="12" md="6">
                    <v-card variant="outlined">
                      <v-card-title class="text-subtitle-1"
                        >New Values</v-card-title
                      >
                      <v-card-text>
                        <pre class="text-caption">{{
                          formatJSON(selectedLog.new_values)
                        }}</pre>
                      </v-card-text>
                    </v-card>
                  </v-col>
                </v-row>
              </v-expansion-panel-text>
            </v-expansion-panel>
          </v-expansion-panels>
        </v-card-text>

        <v-card-actions class="justify-end pa-4">
          <v-btn
            variant="outlined"
            class="secondary-action-btn"
            @click="showDetailsDialog = false"
          >
            Close
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import api from "@/services/api";
import auditLogService from "@/services/auditLogService";
import { useToast } from "vue-toastification";
import {
  formatDate,
  formatTime,
  formatDateTime,
  formatCurrency,
} from "@/utils/formatters";
import { devLog } from "@/utils/devLog";

const toast = useToast();

const loading = ref(false);
const loadingUsers = ref(false);
const exporting = ref(false);
const auditLogs = ref([]);
const users = ref([]);
const showDetailsDialog = ref(false);
const selectedLog = ref(null);

const filters = ref({
  module: null,
  action: null,
  date: null,
  user_id: null,
  search: "",
});

const statistics = ref({
  total: 0,
  today: 0,
  activeUsers: 0,
  modules: 0,
});

const modules = ref(auditLogService.getAvailableModules());
const actions = ref(auditLogService.getAvailableActions());

const pagination = ref({
  page: 1,
  perPage: 10,
  total: 0,
  lastPage: 1,
});

const pageSizeOptions = [10, 25, 50, 100];

let searchDebounceTimeout = null;

const headers = [
  { title: "Date & Time", key: "created_at", sortable: false },
  { title: "User", key: "user", sortable: false },
  { title: "Module", key: "module", sortable: false },
  { title: "Activity", key: "action", sortable: false },
  { title: "Summary", key: "description", sortable: false },
  {
    title: "Actions",
    key: "actions",
    sortable: false,
    align: "center",
  },
];

const hasActiveFilters = computed(() => {
  return (
    !!filters.value.module ||
    !!filters.value.action ||
    !!filters.value.date ||
    !!filters.value.user_id ||
    !!String(filters.value.search || "").trim()
  );
});

const activeFilterCount = computed(() => {
  return [
    filters.value.module,
    filters.value.action,
    filters.value.date,
    filters.value.user_id,
    String(filters.value.search || "").trim(),
  ].filter(Boolean).length;
});

const pageStart = computed(() => {
  if (!pagination.value.total) return 0;
  return (pagination.value.page - 1) * pagination.value.perPage + 1;
});

const pageEnd = computed(() => {
  if (!pagination.value.total) return 0;
  return Math.min(
    pagination.value.page * pagination.value.perPage,
    pagination.value.total,
  );
});

const detailChangeRows = computed(() => buildChangeRows(selectedLog.value));

const changeIgnoredKeys = new Set([
  "edit_reason",
  "access_request_id",
  "edited_by_role",
  "result_count",
]);

const currencyKeys = new Set([
  "rate",
  "basic_pay",
  "regular_ot_pay",
  "special_ot_pay",
  "sunday_pay",
  "holiday_pay",
  "salary_adjustment",
  "other_allowances",
  "gross_pay",
  "sss",
  "philhealth",
  "pagibig",
  "withholding_tax",
  "employee_savings",
  "cash_advance",
  "loans",
  "employee_deductions",
  "other_deductions",
  "undertime_deduction",
  "total_deductions",
  "net_pay",
]);

function parseLogValues(values) {
  if (values && typeof values === "object" && !Array.isArray(values)) {
    return values;
  }

  if (typeof values === "string") {
    try {
      const parsed = JSON.parse(values);
      if (parsed && typeof parsed === "object" && !Array.isArray(parsed)) {
        return parsed;
      }
    } catch {
      return {};
    }
  }

  return {};
}

function parseLogNewValues(log) {
  const values = log?.new_values;

  if (values && typeof values === "object" && !Array.isArray(values)) {
    return values;
  }

  if (typeof values === "string") {
    try {
      const parsed = JSON.parse(values);
      if (parsed && typeof parsed === "object" && !Array.isArray(parsed)) {
        return parsed;
      }
    } catch {
      return {};
    }
  }

  return {};
}

function getLogActorName(log) {
  const user = log?.user;
  if (!user) {
    return "System";
  }

  if (user.full_name) {
    return user.full_name;
  }

  if (user.employee) {
    const employeeName = [
      user.employee.first_name,
      user.employee.middle_name,
      user.employee.last_name,
      user.employee.suffix,
    ]
      .filter(Boolean)
      .join(" ")
      .trim();

    if (employeeName) {
      return employeeName;
    }
  }

  return user.name || user.username || user.email || `User #${user.id}`;
}

function getLogActorMeta(log) {
  const user = log?.user;
  if (!user) return "Automated/System Action";

  const role = user.role ? String(user.role).toUpperCase() : null;
  const username = user.username ? `@${user.username}` : null;
  const email = user.email || null;

  return [role, username, email].filter(Boolean).join(" · ") || "User account";
}

function resolveAvatarUrl(avatar) {
  if (!avatar || typeof avatar !== "string") {
    return null;
  }

  if (avatar.startsWith("http")) {
    return avatar;
  }

  const apiUrl = (
    import.meta.env.VITE_API_URL || "http://localhost:8000/api"
  ).replace(/\/api\/?$/, "");
  const normalizedAvatar = avatar.startsWith("/") ? avatar.slice(1) : avatar;

  if (normalizedAvatar.startsWith("storage/")) {
    return `${apiUrl}/${normalizedAvatar}`;
  }

  return `${apiUrl}/storage/${normalizedAvatar}`;
}

function getLogActorAvatar(log) {
  const user = log?.user;
  if (!user) {
    return null;
  }

  return resolveAvatarUrl(
    user.avatar ||
      user.avatar_url ||
      user.profile_photo_url ||
      user.profile_photo,
  );
}

function getLogReason(log) {
  const value = parseLogNewValues(log).edit_reason;
  return typeof value === "string" ? value.trim() : "";
}

function getLogAccessRequestId(log) {
  const value = parseLogNewValues(log).access_request_id;
  if (value === null || value === undefined || value === "") {
    return "";
  }
  return String(value);
}

function getReadableDescription(log) {
  return auditLogService.summarizeActivity(log);
}

function summaryIncludesReason(log) {
  const reason = getLogReason(log);
  if (!reason) {
    return false;
  }

  const summary = String(getReadableDescription(log) || "").toLowerCase();
  const normalizedReason = reason.toLowerCase();

  return (
    summary.includes(`reason: ${normalizedReason}`) ||
    summary.includes(`(reason: ${normalizedReason})`)
  );
}

function shouldShowReason(log) {
  const reason = getLogReason(log);
  if (!reason) {
    return false;
  }

  return !summaryIncludesReason(log);
}

function getActivityHighlights(log) {
  const values = parseLogValues(log?.new_values);
  const highlights = [];

  if (values.employee_number) {
    highlights.push(`Employee ${values.employee_number}`);
  }
  if (values.period_name) {
    highlights.push(`Payroll ${values.period_name}`);
  }
  if (values.payroll_id) {
    highlights.push(`Payroll ID #${values.payroll_id}`);
  }
  if (values.included_position) {
    highlights.push(`Position: ${values.included_position}`);
  }
  if (values.employee_id || values.included_employee_id) {
    highlights.push(
      `Employee ID #${values.employee_id || values.included_employee_id}`,
    );
  }
  if (typeof values.overtime_employee_count === "number") {
    highlights.push(`Overtime employees: ${values.overtime_employee_count}`);
  }

  return highlights.slice(0, 3);
}

function humanizeFieldName(key) {
  return String(key || "")
    .replace(/_/g, " ")
    .replace(/\b\w/g, (char) => char.toUpperCase());
}

function valuesAreEqual(valueA, valueB) {
  return JSON.stringify(valueA) === JSON.stringify(valueB);
}

function formatChangeValue(key, value) {
  if (value === null || value === undefined || value === "") {
    return "-";
  }

  if (typeof value === "boolean") {
    return value ? "Yes" : "No";
  }

  if (typeof value === "number") {
    if (currencyKeys.has(key)) {
      return `PHP ${formatCurrency(value)}`;
    }
    return Number.isInteger(value) ? String(value) : value.toFixed(2);
  }

  if (Array.isArray(value)) {
    return value.length ? value.join(", ") : "-";
  }

  if (typeof value === "object") {
    return JSON.stringify(value);
  }

  return String(value);
}

function buildChangeRows(log) {
  if (!log) {
    return [];
  }

  const oldValues = parseLogValues(log.old_values);
  const newValues = parseLogValues(log.new_values);
  const keys = new Set([...Object.keys(oldValues), ...Object.keys(newValues)]);

  const rows = [];

  for (const key of keys) {
    if (changeIgnoredKeys.has(key)) {
      continue;
    }

    const before = oldValues[key];
    const after = newValues[key];
    if (valuesAreEqual(before, after)) {
      continue;
    }

    rows.push({
      key,
      label: humanizeFieldName(key),
      before: formatChangeValue(key, before),
      after: formatChangeValue(key, after),
    });
  }

  return rows;
}

function getFilterParams(includePagination = true) {
  const params = {};

  if (includePagination) {
    params.page = pagination.value.page;
    params.per_page = pagination.value.perPage;
  }

  if (filters.value.module) params.module = filters.value.module;
  if (filters.value.action) params.action = filters.value.action;
  if (filters.value.date) {
    params.date_from = filters.value.date;
    params.date_to = filters.value.date;
  }
  if (filters.value.user_id) params.user_id = filters.value.user_id;

  const search = String(filters.value.search || "").trim();
  if (search) params.search = search;

  return params;
}

function normalizeFilterOptions(response) {
  const dynamicModules = Array.isArray(response?.available_modules)
    ? response.available_modules
    : [];
  const dynamicActions = Array.isArray(response?.available_actions)
    ? response.available_actions
    : [];

  modules.value = auditLogService.getAvailableModules(dynamicModules);
  actions.value = auditLogService.getAvailableActions(dynamicActions);
}

function normalizePagination(response) {
  const meta = response?.meta || {};

  const total = Number(response?.total ?? meta?.total ?? 0);
  const currentPage = Number(
    response?.current_page ?? meta?.current_page ?? pagination.value.page ?? 1,
  );
  const perPage = Number(
    response?.per_page ?? meta?.per_page ?? pagination.value.perPage ?? 10,
  );
  const lastPage = Number(response?.last_page ?? meta?.last_page ?? 1);

  pagination.value.total = Number.isFinite(total) && total >= 0 ? total : 0;
  pagination.value.page =
    Number.isFinite(currentPage) && currentPage > 0 ? currentPage : 1;
  pagination.value.perPage =
    Number.isFinite(perPage) && perPage > 0 ? perPage : 10;
  pagination.value.lastPage =
    Number.isFinite(lastPage) && lastPage > 0 ? lastPage : 1;
}

function getFallbackStatistics(logs) {
  const total = logs.length;
  const todayDate = new Date().toISOString().split("T")[0];
  const today = logs.filter((log) => {
    const createdAt = log?.created_at;
    if (!createdAt) return false;
    return new Date(createdAt).toISOString().split("T")[0] === todayDate;
  }).length;

  const activeUsers = new Set(logs.map((log) => log.user_id).filter(Boolean))
    .size;
  const modulesCount = new Set(logs.map((log) => log.module).filter(Boolean))
    .size;

  return {
    total,
    today,
    activeUsers,
    modules: modulesCount,
  };
}

function applyFilters() {
  pagination.value.page = 1;
  fetchAuditLogs();
}

function debouncedSearch() {
  if (searchDebounceTimeout) {
    clearTimeout(searchDebounceTimeout);
  }

  searchDebounceTimeout = setTimeout(() => {
    applyFilters();
  }, 350);
}

function clearFilters() {
  filters.value = {
    module: null,
    action: null,
    date: null,
    user_id: null,
    search: "",
  };

  pagination.value.page = 1;
  fetchAuditLogs();
}

async function fetchAuditLogs() {
  loading.value = true;
  try {
    const response = await auditLogService.getAll(getFilterParams(), {
      skipCache: true,
    });

    auditLogs.value = response.data || [];
    normalizePagination(response);
    statistics.value =
      response.statistics || getFallbackStatistics(auditLogs.value);

    normalizeFilterOptions(response);

    if (
      pagination.value.page > pagination.value.lastPage &&
      pagination.value.lastPage > 0
    ) {
      pagination.value.page = pagination.value.lastPage;
      await fetchAuditLogs();
      return;
    }
  } catch (error) {
    devLog.error("Error fetching audit logs:", error);
    toast.error("Failed to load audit logs");
  } finally {
    loading.value = false;
  }
}

async function fetchUsers() {
  loadingUsers.value = true;
  try {
    const response = await api.get("/users", {
      params: {
        per_page: 200,
        sort_by: "name",
        sort_order: "asc",
      },
      skipCache: true,
      skipToast: true,
    });

    const payload = response.data;
    const rows = Array.isArray(payload?.data)
      ? payload.data
      : Array.isArray(payload)
        ? payload
        : [];

    users.value = rows.map((user) => ({
      id: user.id,
      display_name: getLogActorName({ user }),
    }));
  } catch (error) {
    devLog.error("Error fetching users:", error);
  } finally {
    loadingUsers.value = false;
  }
}

function onPageChange(page) {
  const normalizedPage = Number(page) || 1;
  if (normalizedPage === pagination.value.page || loading.value) {
    return;
  }

  pagination.value.page = normalizedPage;
  fetchAuditLogs();
}

function onPerPageChange(perPage) {
  const normalizedPerPage = Number(perPage) || 10;
  if (normalizedPerPage === pagination.value.perPage || loading.value) {
    return;
  }

  pagination.value.perPage = normalizedPerPage;
  pagination.value.page = 1;
  fetchAuditLogs();
}

function viewDetails(log) {
  selectedLog.value = log;
  showDetailsDialog.value = true;
}

async function exportLogs() {
  exporting.value = true;
  try {
    const params = getFilterParams(false);
    const blob = await auditLogService.exportLogs(params);

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
    devLog.error("Error exporting logs:", error);
    toast.error("Failed to export audit logs");
  } finally {
    exporting.value = false;
  }
}

function formatModule(module) {
  return auditLogService.formatModule(module);
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
  return JSON.stringify(parseLogValues(obj), null, 2);
}

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

.page-header {
  margin-bottom: 24px;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 24px;

  @media (max-width: 960px) {
    flex-direction: column;
    align-items: flex-start;
  }
}

.page-title-section {
  display: flex;
  align-items: center;
  gap: 16px;
  flex: 1;
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

.action-buttons {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;

  @media (max-width: 960px) {
    width: 100%;
  }
}

.action-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 20px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
  white-space: nowrap;

  .v-icon {
    flex-shrink: 0;
  }

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(237, 152, 95, 0.25);
  }

  &.action-btn-primary {
    background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);

    .v-icon {
      color: #ffffff !important;
    }
  }

  &.action-btn-secondary {
    background: rgba(237, 152, 95, 0.1);
    color: #ed985f;
    border: 1px solid rgba(237, 152, 95, 0.2);

    .v-icon {
      color: #ed985f !important;
    }

    &:hover {
      background: rgba(237, 152, 95, 0.15);
      border-color: rgba(237, 152, 95, 0.3);
    }
  }
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 12px;
  margin-bottom: 24px;
}

.stat-card {
  background: #ffffff;
  border-radius: 12px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  padding: 14px 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 78px;
}

.stat-icon-wrapper {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;

  &.stat-icon-primary {
    background: rgba(237, 152, 95, 0.12);

    .v-icon {
      color: #ed985f !important;
    }
  }

  &.stat-icon-success {
    background: rgba(76, 175, 80, 0.12);

    .v-icon {
      color: #4caf50 !important;
    }
  }

  &.stat-icon-info {
    background: rgba(33, 150, 243, 0.12);

    .v-icon {
      color: #2196f3 !important;
    }
  }

  &.stat-icon-warning {
    background: rgba(255, 152, 0, 0.12);

    .v-icon {
      color: #ff9800 !important;
    }
  }
}

.stat-content {
  min-width: 0;
}

.stat-value {
  font-size: 20px;
  font-weight: 700;
  color: #001f3d;
  line-height: 1;
  margin-bottom: 5px;
}

.stat-label {
  font-size: 12px;
  color: rgba(0, 31, 61, 0.62);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.4px;
}

.modern-card {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  overflow: hidden;
  margin-bottom: 24px;
  padding: 24px;
}

.filters-section {
  background: rgba(0, 31, 61, 0.01);
  margin-bottom: 24px;
}

.table-section {
  background: #ffffff;
}

.audit-date-cell {
  display: flex;
  flex-direction: column;
  gap: 2px;
  line-height: 1.35;
}

.audit-date-primary {
  color: #001f3d;
  font-size: 13px;
  font-weight: 600;
}

.audit-date-secondary {
  color: rgba(0, 31, 61, 0.74);
  font-size: 12px;
}

.audit-user-cell {
  display: flex;
  align-items: center;
  min-width: 0;
}

.audit-user-name {
  color: #001f3d;
  font-weight: 600;
  line-height: 1.35;
}

.audit-user-meta {
  color: rgba(0, 31, 61, 0.74);
  font-size: 12px;
  line-height: 1.35;
}

.audit-description-cell {
  display: flex;
  flex-direction: column;
  gap: 6px;
  min-width: 0;
  max-width: 100%;
}

.audit-description-text {
  color: #0f3554;
  font-weight: 500;
  line-height: 1.4;
  display: -webkit-box;
  line-clamp: 2;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
}

.audit-description-meta {
  display: flex;
  flex-wrap: wrap;
  gap: 6px;
  align-items: center;
}

.audit-reason-inline {
  display: inline-flex;
  align-items: flex-start;
  gap: 6px;
  max-width: min(460px, 100%);
  padding: 4px 10px;
  border-radius: 999px;
  background: rgba(255, 193, 7, 0.25);
  color: #5c3c00;
}

.audit-reason-icon {
  color: #8f5d00;
  margin-top: 1px;
}

.audit-reason-text {
  font-size: 12px;
  font-weight: 600;
  line-height: 1.35;
  display: -webkit-box;
  line-clamp: 2;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
  text-overflow: ellipsis;
}

.dialog-header {
  padding: 24px;
  display: flex;
  align-items: center;
  gap: 16px;
  background: linear-gradient(135deg, #001f3d 0%, #003d5c 100%);
}

.header-icon-badge {
  width: 48px;
  height: 48px;
  border-radius: 12px;
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

.header-title {
  font-size: 20px;
  font-weight: 700;
  color: white;
  margin: 0;
  line-height: 1.2;
}

.header-subtitle {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.7);
  margin: 4px 0 0 0;
  line-height: 1.3;
}

.secondary-action-btn {
  border-radius: 10px;
  padding: 0 24px;
  font-weight: 600;
  text-transform: none;
  letter-spacing: 0.3px;
  transition: all 0.2s ease;
  border: 1.5px solid rgba(0, 31, 61, 0.15);
  color: #001f3d;
}

.secondary-action-btn:hover {
  background: rgba(0, 31, 61, 0.04);
  border-color: rgba(0, 31, 61, 0.25);
}

.detail-grid {
  margin: 0 -6px;
}

.detail-row {
  margin-bottom: 12px;
  padding: 12px 14px;
  border: 1px solid rgba(0, 31, 61, 0.1);
  border-radius: 10px;
  background: rgba(0, 31, 61, 0.02);
  min-height: 96px;
  display: flex;
  flex-direction: column;
  justify-content: center;
}

.detail-label {
  display: flex;
  align-items: center;
  font-size: 12px;
  font-weight: 600;
  color: rgba(0, 31, 61, 0.72);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 6px;
}

.detail-value {
  font-size: 15px;
  font-weight: 600;
  color: #001f3d;
  padding: 4px 0 0;
  min-height: 28px;
  display: flex;
  align-items: center;
  line-height: 1.45;
}

.detail-value-column {
  align-items: flex-start;
  flex-direction: column;
  gap: 4px;
}

.detail-meta-value {
  font-size: 13px;
  color: rgba(0, 31, 61, 0.78);
  line-height: 1.4;
}

.audit-summary-alert {
  :deep(.v-alert__content) {
    color: #0f3554;
    font-size: 14px;
    font-weight: 500;
    line-height: 1.55;
  }
}

.audit-context-alert {
  :deep(.v-alert__content) {
    color: #5a420b;
    font-size: 14px;
    line-height: 1.5;
  }
}

.audit-context-label {
  font-weight: 700;
  color: #7a5600;
  margin-right: 4px;
}

.audit-context-value {
  color: #4a3300;
  font-weight: 600;
}

.detail-changes-table {
  border: 1px solid rgba(0, 31, 61, 0.08);
  border-radius: 10px;
  overflow: hidden;

  thead th {
    font-weight: 700;
    color: #001f3d;
    background: rgba(0, 31, 61, 0.04);
  }
}

.field-name-cell {
  font-weight: 600;
}

.v-data-table {
  :deep(.v-data-table__wrapper) {
    border-radius: 0;
  }

  :deep(.v-data-table-header) {
    background-color: rgba(0, 31, 61, 0.02);
  }

  :deep(.v-data-table__th) {
    font-weight: 600;
    color: #001f3d;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  :deep(tbody tr) {
    transition: background-color 0.2s ease;
  }

  :deep(tbody tr:hover) {
    background-color: rgba(237, 152, 95, 0.04) !important;
  }

  :deep(.v-data-table__td) {
    border-bottom: 1px solid rgba(0, 31, 61, 0.06);
  }
}

@media (max-width: 960px) {
  .page-title {
    font-size: 24px;
  }

  .stats-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }

  .action-buttons {
    width: 100%;
  }

  .action-btn {
    flex: 1;
    justify-content: center;
  }
}

@media (max-width: 640px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }
}
</style>
