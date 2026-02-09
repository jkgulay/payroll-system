<template>
  <div class="maintenance-page">
    <!-- Modern Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="back-button-wrapper">
          <button class="back-button" @click="$router.push('/settings')">
            <v-icon size="20">mdi-arrow-left</v-icon>
            <span>Back to Settings</span>
          </button>
        </div>

        <div class="header-main">
          <div class="page-title-section">
            <div class="page-icon-badge">
              <v-icon size="22">mdi-database-cog</v-icon>
            </div>
            <div>
              <h1 class="page-title">System Maintenance</h1>
              <p class="page-subtitle">
                Monitor system health and perform maintenance tasks
              </p>
            </div>
          </div>
          <button
            class="action-btn action-btn-primary"
            @click="checkHealth"
            :disabled="checking"
          >
            <v-icon size="20">mdi-refresh</v-icon>
            <span>{{ checking ? "Checking..." : "Refresh Status" }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="checking" class="loading-state">
      <v-progress-circular indeterminate color="primary" size="48" />
      <p>Analyzing system health...</p>
    </div>

    <!-- Health Status Cards -->
    <div v-else-if="healthData" class="maintenance-content">
      <!-- Overall System Health Banner -->
      <div
        class="health-status-banner"
        :class="`status-${healthData.overall?.status || 'unknown'}`"
      >
        <div class="status-icon">
          <v-icon size="40">{{
            healthData.overall?.status === "healthy"
              ? "mdi-check-circle"
              : healthData.overall?.status === "warning"
                ? "mdi-alert"
                : "mdi-alert-circle"
          }}</v-icon>
        </div>
        <div class="status-content">
          <h2 class="status-title">
            {{
              healthData.overall?.status === "healthy"
                ? "System is Healthy"
                : healthData.overall?.status === "warning"
                  ? "Minor Issues Detected"
                  : "Critical Issues Detected"
            }}
          </h2>
          <p class="status-description">
            {{
              healthData.overall?.critical_issues > 0
                ? `${healthData.overall.critical_issues} critical issue(s) require immediate attention`
                : healthData.overall?.warnings > 0
                  ? `${healthData.overall.warnings} warning(s) need review`
                  : "All systems operating normally"
            }}
          </p>
        </div>
        <div class="status-stats">
          <div class="stat-item">
            <span class="stat-value">{{
              healthData.database?.size_formatted
            }}</span>
            <span class="stat-label">Database Size</span>
          </div>
          <div class="stat-item">
            <span class="stat-value">{{ healthData.employees?.active }}</span>
            <span class="stat-label">Active Employees</span>
          </div>
        </div>
      </div>

      <!-- Diagnostic Cards Grid -->
      <div class="diagnostics-grid">
        <!-- Payroll System Card -->
        <div
          class="diagnostic-card"
          :class="{ 'has-issues': healthData.payrolls?.has_issues }"
        >
          <div class="card-icon-wrapper payroll">
            <v-icon size="32">mdi-cash-multiple</v-icon>
          </div>
          <div class="card-content">
            <h3 class="card-title">Payroll System</h3>
            <span
              class="card-status-badge"
              :class="{
                success: !healthData.payrolls?.has_issues,
                error: healthData.payrolls?.has_issues,
              }"
            >
              {{
                healthData.payrolls?.has_issues
                  ? "Issues Found"
                  : "Operating Normally"
              }}
            </span>
            <div class="card-stats">
              <div class="stat-row">
                <span>Total Payrolls</span>
                <strong>{{ healthData.payrolls?.total }}</strong>
              </div>
              <div class="stat-row">
                <span>Draft</span>
                <strong>{{ healthData.payrolls?.draft }}</strong>
              </div>
              <div class="stat-row">
                <span>Finalized</span>
                <strong>{{ healthData.payrolls?.finalized }}</strong>
              </div>
              <div
                class="stat-row"
                v-if="healthData.payrolls?.sequence_value !== null"
              >
                <span>Sequence Status</span>
                <strong
                  :class="{
                    success: healthData.payrolls?.sequence_ok,
                    error: !healthData.payrolls?.sequence_ok,
                  }"
                >
                  {{ healthData.payrolls?.sequence_value }}
                  <v-icon size="14">{{
                    healthData.payrolls?.sequence_ok
                      ? "mdi-check-circle"
                      : "mdi-alert-circle"
                  }}</v-icon>
                </strong>
              </div>
              <div
                class="stat-row alert"
                v-if="healthData.payrolls?.orphaned_items > 0"
              >
                <span>Orphaned Items</span>
                <strong class="error">{{
                  healthData.payrolls?.orphaned_items
                }}</strong>
              </div>
            </div>
            <div v-if="healthData.payrolls?.has_issues" class="card-actions">
              <button
                class="action-button primary"
                @click="fixPayrollSequence"
                :disabled="fixing"
              >
                <v-icon size="16">mdi-wrench</v-icon>
                <span>{{ fixing ? "Fixing..." : "Fix Sequence" }}</span>
              </button>
              <button
                v-if="healthData.payrolls?.orphaned_items > 0"
                class="action-button secondary"
                @click="cleanDatabase"
                :disabled="cleaning"
              >
                <v-icon size="16">mdi-broom</v-icon>
                <span>{{ cleaning ? "Cleaning..." : "Clean Data" }}</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Employee Records Card -->
        <div
          class="diagnostic-card"
          :class="{ 'has-issues': healthData.employees?.has_issues }"
        >
          <div class="card-icon-wrapper employee">
            <v-icon size="32">mdi-account-group</v-icon>
          </div>
          <div class="card-content">
            <h3 class="card-title">Employee Records</h3>
            <span
              class="card-status-badge"
              :class="{
                success: !healthData.employees?.has_issues,
                warning: healthData.employees?.has_issues,
              }"
            >
              {{
                healthData.employees?.has_issues
                  ? "Attention Needed"
                  : "All Good"
              }}
            </span>
            <div class="card-stats">
              <div class="stat-row">
                <span>Total Employees</span>
                <strong>{{ healthData.employees?.total }}</strong>
              </div>
              <div class="stat-row">
                <span>Active</span>
                <strong :class="{ error: healthData.employees?.active === 0 }">
                  {{ healthData.employees?.active }}
                </strong>
              </div>
              <div class="stat-row">
                <span>Inactive</span>
                <strong>{{ healthData.employees?.inactive }}</strong>
              </div>
              <div
                class="stat-row alert"
                v-if="healthData.employees?.without_position > 0"
              >
                <span>Without Position</span>
                <strong class="warning">{{
                  healthData.employees?.without_position
                }}</strong>
              </div>
              <div
                class="stat-row alert"
                v-if="healthData.employees?.without_project > 0"
              >
                <span>Without Department</span>
                <strong class="warning">{{
                  healthData.employees?.without_project
                }}</strong>
              </div>
            </div>
            <p
              v-if="healthData.employees?.has_issues"
              class="card-note warning"
            >
              <v-icon size="14">mdi-information</v-icon>
              {{
                healthData.employees?.active === 0
                  ? "No active employees. Add employees to create payrolls."
                  : `${healthData.employees.without_position} employee(s) need position assignment.`
              }}
            </p>
          </div>
        </div>

        <!-- Attendance System Card -->
        <div
          class="diagnostic-card"
          :class="{ 'has-issues': healthData.attendance?.has_issues }"
        >
          <div class="card-icon-wrapper attendance">
            <v-icon size="32">mdi-calendar-check</v-icon>
          </div>
          <div class="card-content">
            <h3 class="card-title">Attendance System</h3>
            <span
              class="card-status-badge"
              :class="{
                success: !healthData.attendance?.has_issues,
                warning: healthData.attendance?.has_issues,
              }"
            >
              {{
                healthData.attendance?.has_issues
                  ? "Pending Review"
                  : "Up to Date"
              }}
            </span>
            <div class="card-stats">
              <div class="stat-row">
                <span>Total Records</span>
                <strong>{{ healthData.attendance?.total_records }}</strong>
              </div>
              <div class="stat-row">
                <span>This Month</span>
                <strong>{{ healthData.attendance?.this_month }}</strong>
              </div>
              <div
                class="stat-row alert"
                v-if="healthData.attendance?.pending_corrections > 0"
              >
                <span>Pending Corrections</span>
                <strong
                  :class="{
                    warning: healthData.attendance?.pending_corrections > 10,
                  }"
                >
                  {{ healthData.attendance?.pending_corrections }}
                </strong>
              </div>
            </div>
            <p
              v-if="healthData.attendance?.pending_corrections > 10"
              class="card-note warning"
            >
              <v-icon size="14">mdi-information</v-icon>
              High number of pending corrections. Review attendance records.
            </p>
          </div>
        </div>

        <!-- User Accounts Card -->
        <div
          class="diagnostic-card"
          :class="{ 'has-issues': healthData.users?.has_issues }"
        >
          <div class="card-icon-wrapper users">
            <v-icon size="32">mdi-account-lock</v-icon>
          </div>
          <div class="card-content">
            <h3 class="card-title">User Accounts</h3>
            <span
              class="card-status-badge"
              :class="{
                success: !healthData.users?.has_issues,
                error: healthData.users?.has_issues,
              }"
            >
              {{ healthData.users?.has_issues ? "Critical Issue" : "Secure" }}
            </span>
            <div class="card-stats">
              <div class="stat-row">
                <span>Total Users</span>
                <strong>{{ healthData.users?.total }}</strong>
              </div>
              <div class="stat-row">
                <span>Admin Users</span>
                <strong :class="{ error: healthData.users?.admins === 0 }">
                  {{ healthData.users?.admins }}
                </strong>
              </div>
              <div class="stat-row" v-if="healthData.users?.inactive > 0">
                <span>Inactive</span>
                <strong>{{ healthData.users?.inactive }}</strong>
              </div>
            </div>
            <p v-if="healthData.users?.admins === 0" class="card-note error">
              <v-icon size="14">mdi-alert-circle</v-icon>
              CRITICAL: No admin users found. System access may be limited.
            </p>
          </div>
        </div>

        <!-- Pending Approvals Card (Full Width) -->
        <div class="diagnostic-card full-width">
          <div class="card-icon-wrapper pending">
            <v-icon size="32">mdi-clock-alert</v-icon>
          </div>
          <div class="card-content">
            <h3 class="card-title">Pending Approvals</h3>
            <span class="card-status-badge info">
              {{ healthData.pending_approvals?.total }} items waiting
            </span>
            <div class="pending-grid">
              <div class="pending-item">
                <div class="pending-icon leaves">
                  <v-icon size="20">mdi-calendar-remove</v-icon>
                </div>
                <div class="pending-content">
                  <span class="pending-value">{{
                    healthData.pending_approvals?.leaves || 0
                  }}</span>
                  <span class="pending-label">Leave Requests</span>
                </div>
              </div>
              <div class="pending-item">
                <div class="pending-icon loans">
                  <v-icon size="20">mdi-cash</v-icon>
                </div>
                <div class="pending-content">
                  <span class="pending-value">{{
                    healthData.pending_approvals?.loans || 0
                  }}</span>
                  <span class="pending-label">Loan Applications</span>
                </div>
              </div>
              <div class="pending-item">
                <div class="pending-icon resignations">
                  <v-icon size="20">mdi-exit-run</v-icon>
                </div>
                <div class="pending-content">
                  <span class="pending-value">{{
                    healthData.pending_approvals?.resignations || 0
                  }}</span>
                  <span class="pending-label">Resignations</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Result Message -->
      <div
        v-if="result"
        class="result-message"
        :class="`result-${result.type}`"
      >
        <v-icon size="20">{{
          result.type === "success" ? "mdi-check-circle" : "mdi-alert-circle"
        }}</v-icon>
        <span>{{ result.message }}</span>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import api from "@/services/api";
import { useToast } from "vue-toastification";
import { devLog } from "@/utils/devLog";
import { useConfirmDialog } from "@/composables/useConfirmDialog";

const toast = useToast();
const { confirm: confirmDialog } = useConfirmDialog();

const healthData = ref(null);
const result = ref(null);
const checking = ref(false);
const fixing = ref(false);
const cleaning = ref(false);

onMounted(() => {
  checkHealth();
});

async function checkHealth() {
  checking.value = true;
  result.value = null;
  try {
    const response = await api.get("/maintenance/database-health");
    healthData.value = response.data;
  } catch (error) {
    devLog.error("Error checking health:", error);
    toast.error("Failed to check database health");
  } finally {
    checking.value = false;
  }
}

async function fixPayrollSequence() {
  if (
    !(await confirmDialog(
      "Fix the payroll sequence? This will reset it to the correct value.",
    ))
  ) {
    return;
  }

  fixing.value = true;
  result.value = null;
  try {
    const response = await api.post("/maintenance/fix-payroll-sequence");
    result.value = {
      type: "success",
      message: response.data.message,
    };
    toast.success("Sequence fixed successfully!");
    await checkHealth();
  } catch (error) {
    devLog.error("Error fixing sequence:", error);
    result.value = {
      type: "error",
      message:
        error.response?.data?.message || "Failed to fix payroll sequence",
    };
    toast.error("Failed to fix sequence");
  } finally {
    fixing.value = false;
  }
}

async function cleanDatabase() {
  if (
    !(await confirmDialog(
      "⚠️ WARNING: This will delete orphaned payroll items and reset sequences. Continue?",
    ))
  ) {
    return;
  }

  if (
    !(await confirmDialog(
      "This action cannot be undone. Are you absolutely sure?",
    ))
  ) {
    return;
  }

  cleaning.value = true;
  result.value = null;
  try {
    const response = await api.post("/maintenance/clean-database", {
      confirm: "yes-delete-orphaned-data",
    });
    result.value = {
      type: "success",
      message: response.data.message,
    };
    toast.success("Database cleaned successfully!");
    await checkHealth();
  } catch (error) {
    devLog.error("Error cleaning database:", error);
    result.value = {
      type: "error",
      message: error.response?.data?.message || "Failed to clean database",
    };
    toast.error("Failed to clean database");
  } finally {
    cleaning.value = false;
  }
}
</script>

<style scoped lang="scss">
.maintenance-page {
  max-width: 1400px;
  margin: 0 auto;
}

// Modern Page Header (matching AttendanceSettingsView)
.page-header {
  margin-bottom: 32px;
}

.header-content {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  justify-content: flex-start;
  gap: 16px;
}

.back-button-wrapper {
  margin-bottom: 8px;
}

.back-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: none;
  background: transparent;
  color: #001f3d;
  font-weight: 600;
  cursor: pointer;
  padding: 6px 8px;
  border-radius: 8px;
  transition: all 0.2s ease;

  &:hover {
    background: rgba(0, 31, 61, 0.08);
  }
}

.header-main {
  display: flex;
  align-items: center;
  justify-content: space-between;
}

.action-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  border: none;
  border-radius: 10px;
  padding: 10px 16px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;

  &:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
}

.action-btn-primary {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: #ffffff;
  box-shadow: 0 6px 16px rgba(237, 152, 95, 0.35);

  &:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 8px 20px rgba(237, 152, 95, 0.4);
  }

  .v-icon {
    color: #ffffff !important;
  }
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

// Loading State
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 20px;
  gap: 20px;

  p {
    color: #64748b;
    font-size: 14px;
    margin: 0;
  }
}

// Health Status Banner
.health-status-banner {
  display: flex;
  align-items: center;
  gap: 20px;
  padding: 24px 28px;
  background: #ffffff;
  border-radius: 16px;
  border: 2px solid;
  margin-bottom: 32px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  transition: all 0.3s ease;

  &.status-healthy {
    border-color: rgba(34, 197, 94, 0.3);
    background: linear-gradient(
      135deg,
      rgba(34, 197, 94, 0.05) 0%,
      rgba(22, 163, 74, 0.02) 100%
    );

    .status-icon {
      background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
    }
  }

  &.status-warning {
    border-color: rgba(251, 191, 36, 0.3);
    background: linear-gradient(
      135deg,
      rgba(251, 191, 36, 0.05) 0%,
      rgba(245, 158, 11, 0.02) 100%
    );

    .status-icon {
      background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    }
  }

  &.status-critical {
    border-color: rgba(239, 68, 68, 0.3);
    background: linear-gradient(
      135deg,
      rgba(239, 68, 68, 0.05) 0%,
      rgba(220, 38, 38, 0.02) 100%
    );

    .status-icon {
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    }
  }
}

.status-icon {
  width: 70px;
  height: 70px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);

  .v-icon {
    color: #ffffff !important;
  }
}

.status-content {
  flex: 1;
}

.status-title {
  font-size: 20px;
  font-weight: 700;
  color: #001f3d;
  margin: 0 0 6px 0;
  letter-spacing: -0.3px;
}

.status-description {
  font-size: 14px;
  color: rgba(0, 31, 61, 0.6);
  margin: 0;
  line-height: 1.5;
}

.status-stats {
  display: flex;
  gap: 32px;
}

.stat-item {
  display: flex;
  flex-direction: column;
  align-items: flex-end;
  gap: 4px;
}

.stat-value {
  font-size: 22px;
  font-weight: 700;
  color: #001f3d;
  line-height: 1;
}

.stat-label {
  font-size: 11px;
  color: rgba(0, 31, 61, 0.6);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

// Diagnostics Grid (matching SettingsView)
.diagnostics-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 2fr));
  gap: 24px;

  @media (max-width: 960px) {
    grid-template-columns: 1fr;
  }
}

// Diagnostic Card (matching SettingsView style)
.diagnostic-card {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  padding: 28px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  display: flex;
  flex-direction: column;
  gap: 20px;

  &:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(237, 152, 95, 0.15);
    border-color: rgba(237, 152, 95, 0.3);

    .card-icon-wrapper {
      transform: scale(1.05);
    }
  }

  &.has-issues {
    border-color: rgba(251, 191, 36, 0.4);
    background: linear-gradient(
      135deg,
      rgba(251, 191, 36, 0.03) 0%,
      rgba(255, 255, 255, 1) 100%
    );
  }

  &.full-width {
    grid-column: 1 / -1;
  }
}

.card-icon-wrapper {
  width: 70px;
  height: 70px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;

  &.payroll {
    background: linear-gradient(
      135deg,
      rgba(237, 152, 95, 0.15) 0%,
      rgba(247, 185, 128, 0.1) 100%
    );

    .v-icon {
      color: #ed985f !important;
    }
  }

  &.employee {
    background: linear-gradient(
      135deg,
      rgba(59, 130, 246, 0.15) 0%,
      rgba(37, 99, 235, 0.1) 100%
    );

    .v-icon {
      color: #3b82f6 !important;
    }
  }

  &.attendance {
    background: linear-gradient(
      135deg,
      rgba(139, 92, 246, 0.15) 0%,
      rgba(124, 58, 237, 0.1) 100%
    );

    .v-icon {
      color: #8b5cf6 !important;
    }
  }

  &.users {
    background: linear-gradient(
      135deg,
      rgba(236, 72, 153, 0.15) 0%,
      rgba(219, 39, 119, 0.1) 100%
    );

    .v-icon {
      color: #ec4899 !important;
    }
  }

  &.pending {
    background: linear-gradient(
      135deg,
      rgba(245, 158, 11, 0.15) 0%,
      rgba(217, 119, 6, 0.1) 100%
    );

    .v-icon {
      color: #f59e0b !important;
    }
  }
}

.card-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.card-title {
  font-size: 20px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
  letter-spacing: -0.3px;
}

.card-status-badge {
  display: inline-block;
  padding: 4px 12px;
  border-radius: 8px;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  width: fit-content;

  &.success {
    background: rgba(34, 197, 94, 0.12);
    color: #16a34a;
  }

  &.warning {
    background: rgba(251, 191, 36, 0.12);
    color: #d97706;
  }

  &.error {
    background: rgba(239, 68, 68, 0.12);
    color: #dc2626;
  }

  &.info {
    background: rgba(59, 130, 246, 0.12);
    color: #2563eb;
  }
}

.card-stats {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.stat-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 8px 12px;
  background: rgba(0, 31, 61, 0.03);
  border-radius: 6px;
  font-size: 12px;
  transition: all 0.2s ease;

  &:hover {
    background: rgba(0, 31, 61, 0.05);
  }

  &.alert {
    background: rgba(251, 191, 36, 0.08);
    border: 1px solid rgba(251, 191, 36, 0.2);
  }

  span {
    color: rgba(0, 31, 61, 0.7);
  }

  strong {
    color: #001f3d;
    font-weight: 600;
    font-size: 13px;
    display: flex;
    align-items: center;
    gap: 4px;

    &.success {
      color: #16a34a;
    }

    &.warning {
      color: #d97706;
    }

    &.error {
      color: #dc2626;
    }
  }
}

.card-actions {
  display: flex;
  gap: 10px;
  padding-top: 8px;
  border-top: 1px solid rgba(0, 31, 61, 0.08);
}

.action-button {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  padding: 10px 16px;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;

  &.primary {
    background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(237, 152, 95, 0.25);

    &:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(237, 152, 95, 0.35);
    }

    .v-icon {
      color: #ffffff !important;
    }
  }

  &.secondary {
    background: rgba(239, 68, 68, 0.1);
    color: #dc2626;
    border: 1px solid rgba(239, 68, 68, 0.2);

    &:hover:not(:disabled) {
      background: rgba(239, 68, 68, 0.15);
      border-color: rgba(239, 68, 68, 0.3);
    }

    .v-icon {
      color: #dc2626 !important;
    }
  }

  &:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
}

.card-note {
  display: flex;
  align-items: flex-start;
  gap: 8px;
  padding: 12px 14px;
  border-radius: 8px;
  font-size: 12px;
  line-height: 1.5;
  margin-top: 4px;

  &.warning {
    background: rgba(251, 191, 36, 0.1);
    color: #92400e;
    border-left: 3px solid #fbbf24;
  }

  &.error {
    background: rgba(239, 68, 68, 0.1);
    color: #991b1b;
    border-left: 3px solid #ef4444;
  }

  .v-icon {
    flex-shrink: 0;
    margin-top: 2px;
  }
}

// Pending Approvals Grid
.pending-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 16px;
}

.pending-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  background: rgba(0, 31, 61, 0.03);
  border-radius: 12px;
  transition: all 0.2s ease;

  &:hover {
    background: rgba(0, 31, 61, 0.05);
    transform: translateY(-2px);
  }
}

.pending-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;

  .v-icon {
    color: #ffffff !important;
  }

  &.leaves {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
  }

  &.loans {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
  }

  &.resignations {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
  }
}

.pending-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.pending-value {
  font-size: 24px;
  font-weight: 700;
  color: #001f3d;
  line-height: 1;
}

.pending-label {
  font-size: 11px;
  color: rgba(0, 31, 61, 0.6);
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

// Result Message
.result-message {
  margin-top: 24px;
  padding: 16px 20px;
  border-radius: 12px;
  border: 1px solid;
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 14px;
  font-weight: 600;

  &.result-success {
    background: rgba(34, 197, 94, 0.08);
    border-color: rgba(34, 197, 94, 0.3);
    color: #16a34a;

    .v-icon {
      color: #16a34a !important;
    }
  }

  &.result-error {
    background: rgba(239, 68, 68, 0.08);
    border-color: rgba(239, 68, 68, 0.3);
    color: #dc2626;

    .v-icon {
      color: #dc2626 !important;
    }
  }
}

// Responsive Design
@media (max-width: 960px) {
  .page-header {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }

  .health-status-banner {
    flex-direction: column;
    align-items: flex-start;
    text-align: left;
    gap: 16px;
  }

  .status-stats {
    flex-direction: row;
    gap: 24px;
    width: 100%;

    .stat-item {
      align-items: flex-start;
      flex: 1;
    }
  }

  .diagnostics-grid {
    grid-template-columns: 1fr;
  }

  .pending-grid {
    grid-template-columns: 1fr;
  }
}
</style>
