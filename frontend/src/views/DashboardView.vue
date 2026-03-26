<template>
  <div class="modern-dashboard">
    <!-- Compact Header with Actions -->
    <div class="dashboard-header-compact">
      <div class="header-left">
        <h1 class="dashboard-title">Welcome back, {{ fullName }}</h1>
        <p class="dashboard-subtitle">{{ currentDateRange }}</p>
      </div>
      <div class="header-actions">
        <v-btn
          variant="text"
          prepend-icon="mdi-refresh"
          @click="refreshData"
          :loading="refreshing"
          class="refresh-btn"
        >
          Refresh
        </v-btn>
      </div>
    </div>

    <!-- Redesigned Statistics Cards with Icons on Left -->
    <v-row class="stats-row">
      <v-col cols="12" sm="6" lg="3">
        <div
          class="stat-card-new stat-card-employees"
          @click="$router.push('/employees')"
        >
          <div class="stat-icon-wrapper">
            <div class="stat-icon-circle">
              <v-icon size="28">mdi-account-group</v-icon>
            </div>
          </div>
          <div class="stat-content">
            <div class="stat-label">Active Employees</div>
            <div class="stat-value">{{ stats.activeEmployees }}</div>
            <div class="stat-meta">of {{ stats.totalEmployees }} total</div>
          </div>
          <div class="stat-arrow">
            <v-icon size="20">mdi-chevron-right</v-icon>
          </div>
        </div>
      </v-col>

      <v-col cols="12" sm="6" lg="3">
        <div
          class="stat-card-new stat-card-attendance"
          @click="goToAttendanceToday"
        >
          <div class="stat-icon-wrapper">
            <div class="stat-icon-circle">
              <v-icon size="28">mdi-clock-check</v-icon>
            </div>
          </div>
          <div class="stat-content">
            <div class="stat-label">Present Today</div>
            <div class="stat-value">{{ stats.presentToday }}</div>
            <div class="stat-meta">Click to view details</div>
          </div>
          <div class="stat-arrow">
            <v-icon size="20">mdi-chevron-right</v-icon>
          </div>
        </div>
      </v-col>

      <v-col cols="12" sm="6" lg="3">
        <div
          class="stat-card-new stat-card-payroll"
          @click="$router.push('/payroll')"
        >
          <div class="stat-icon-wrapper">
            <div class="stat-icon-circle">
              <v-icon size="28">mdi-currency-usd</v-icon>
            </div>
          </div>
          <div class="stat-content">
            <div class="stat-label">Period Payroll</div>
            <div class="stat-value">
              ₱{{ formatNumber(stats.periodPayroll) }}
            </div>
            <div class="stat-meta">Current month</div>
          </div>
          <div class="stat-arrow">
            <v-icon size="20">mdi-chevron-right</v-icon>
          </div>
        </div>
      </v-col>

      <v-col cols="12" sm="6" lg="3">
        <div
          class="stat-card-new stat-card-pending"
          @click="goToPendingActions"
        >
          <div class="stat-icon-wrapper">
            <div class="stat-icon-circle stat-icon-pulse">
              <v-icon size="28">mdi-alert-circle</v-icon>
            </div>
          </div>
          <div class="stat-content">
            <div class="stat-label">Pending Actions</div>
            <div class="stat-value">{{ totalPendingActions }}</div>
            <div class="stat-meta">Requires attention</div>
          </div>
          <div class="stat-arrow">
            <v-icon size="20">mdi-chevron-right</v-icon>
          </div>
        </div>
      </v-col>
    </v-row>

    <!-- Main Content Section with New Layout -->
    <v-row class="content-row">
      <!-- Left Column - 2/3 Width -->
      <v-col cols="12" lg="8">
        <!-- Pending Actions with Modern Design -->
        <div class="action-section mb-6">
          <div class="section-header">
            <div class="section-title-wrapper">
              <div class="section-icon-badge">
                <v-icon size="18">mdi-bell-ring</v-icon>
              </div>
              <h2 class="section-title">Action Items</h2>
              <v-chip size="small" class="ml-2" v-if="totalPendingActions > 0">
                {{ totalPendingActions }}
              </v-chip>
            </div>
          </div>

          <!-- Action Cards in Grid -->
          <div class="action-grid" v-if="totalPendingActions > 0">
            <!-- Pending Applications -->
            <div
              v-if="stats.pendingApplications > 0"
              class="action-item"
              @click="$router.push('/resume-review')"
            >
              <div class="action-item-icon action-icon-warning">
                <v-icon size="24">mdi-account-clock</v-icon>
              </div>
              <div class="action-item-content">
                <div class="action-item-title">Job Applications</div>
                <div class="action-item-desc">
                  {{ stats.pendingApplications }} awaiting review
                </div>
              </div>
              <div class="action-item-badge">
                {{ stats.pendingApplications }}
              </div>
            </div>

            <!-- Pending Leaves -->
            <div
              v-if="stats.pendingLeaves > 0"
              class="action-item"
              @click="$router.push('/leave-approval')"
            >
              <div class="action-item-icon action-icon-info">
                <v-icon size="24">mdi-calendar-clock</v-icon>
              </div>
              <div class="action-item-content">
                <div class="action-item-title">Leave Requests</div>
                <div class="action-item-desc">
                  {{ stats.pendingLeaves }} need approval
                </div>
              </div>
              <div class="action-item-badge">
                {{ stats.pendingLeaves }}
              </div>
            </div>

            <!-- Pending Attendance Corrections -->
            <div
              v-if="stats.pendingAttendanceCorrections > 0"
              class="action-item"
              @click="
                $router.push({
                  path: '/attendance',
                  query: { tab: 'approvals' },
                })
              "
            >
              <div class="action-item-icon action-icon-primary">
                <v-icon size="24">mdi-clock-alert</v-icon>
              </div>
              <div class="action-item-content">
                <div class="action-item-title">Attendance Corrections</div>
                <div class="action-item-desc">
                  {{ stats.pendingAttendanceCorrections }} pending
                </div>
              </div>
              <div class="action-item-badge">
                {{ stats.pendingAttendanceCorrections }}
              </div>
            </div>

            <!-- Pending Modification Requests -->
            <div
              v-if="stats.pendingModificationRequests > 0"
              class="action-item"
              @click="
                $router.push({
                  path: '/attendance',
                  query: { tab: 'mod-requests' },
                })
              "
            >
              <div class="action-item-icon action-icon-warning">
                <v-icon size="24">mdi-file-document-edit-outline</v-icon>
              </div>
              <div class="action-item-content">
                <div class="action-item-title">Attendance Modification Requests</div>
                <div class="action-item-desc">
                  {{ stats.pendingModificationRequests }} awaiting approval
                </div>
              </div>
              <div class="action-item-badge">
                {{ stats.pendingModificationRequests }}
              </div>
            </div>

            <!-- Pending Deduction Access Requests -->
            <div
              v-if="stats.pendingDeductionRequests > 0"
              class="action-item"
              @click="
                $router.push({
                  path: '/deductions',
                  query: { tab: 'access-requests' },
                })
              "
            >
              <div class="action-item-icon action-icon-warning">
                <v-icon size="24">mdi-cash-lock</v-icon>
              </div>
              <div class="action-item-content">
                <div class="action-item-title">Deduction Access Requests</div>
                <div class="action-item-desc">
                  {{ stats.pendingDeductionRequests }} awaiting approval
                </div>
              </div>
              <div class="action-item-badge">
                {{ stats.pendingDeductionRequests }}
              </div>
            </div>

            <!-- Draft Payrolls -->
            <div
              v-if="stats.draftPayrolls > 0"
              class="action-item"
              @click="$router.push('/payroll')"
            >
              <div class="action-item-icon action-icon-success">
                <v-icon size="24">mdi-file-document-edit</v-icon>
              </div>
              <div class="action-item-content">
                <div class="action-item-title">Draft Payrolls</div>
                <div class="action-item-desc">
                  {{ stats.draftPayrolls }} ready to finalize
                </div>
              </div>
              <div class="action-item-badge">
                {{ stats.draftPayrolls }}
              </div>
            </div>

            <!-- Pending Resignations -->
            <div
              v-if="stats.pendingResignations > 0"
              class="action-item"
              @click="$router.push('/resignations')"
            >
              <div class="action-item-icon action-icon-danger">
                <v-icon size="24">mdi-briefcase-remove</v-icon>
              </div>
              <div class="action-item-content">
                <div class="action-item-title">Resignations</div>
                <div class="action-item-desc">
                  {{ stats.pendingResignations }} pending review
                </div>
              </div>
              <div class="action-item-badge">
                {{ stats.pendingResignations }}
              </div>
            </div>

            <!-- Pending Allowances -->
            <div
              v-if="stats.pendingMealAllowances > 0"
              class="action-item"
              @click="$router.push('/allowances')"
            >
              <div class="action-item-icon action-icon-warning">
                <v-icon size="24">mdi-food</v-icon>
              </div>
              <div class="action-item-content">
                <div class="action-item-title">Allowances</div>
                <div class="action-item-desc">
                  {{ stats.pendingMealAllowances }} pending approval
                </div>
              </div>
              <div class="action-item-badge">
                {{ stats.pendingMealAllowances }}
              </div>
            </div>

            <!-- Pending 13th Month Pay -->
            <div
              v-if="stats.pending13thMonthPay > 0"
              class="action-item"
              @click="$router.push('/thirteenth-month-pay')"
            >
              <div class="action-item-icon action-icon-success">
                <v-icon size="24">mdi-gift</v-icon>
              </div>
              <div class="action-item-content">
                <div class="action-item-title">13th Month Pay</div>
                <div class="action-item-desc">
                  {{ stats.pending13thMonthPay }} pending finalization
                </div>
              </div>
              <div class="action-item-badge">
                {{ stats.pending13thMonthPay }}
              </div>
            </div>

            <!-- Pending Employee Loans -->
            <div
              v-if="stats.pendingEmployeeLoans > 0"
              class="action-item"
              @click="$router.push('/loans')"
            >
              <div class="action-item-icon action-icon-info">
                <v-icon size="24">mdi-cash-multiple</v-icon>
              </div>
              <div class="action-item-content">
                <div class="action-item-title">Employee Loans</div>
                <div class="action-item-desc">
                  {{ stats.pendingEmployeeLoans }} awaiting approval
                </div>
              </div>
              <div class="action-item-badge">
                {{ stats.pendingEmployeeLoans }}
              </div>
            </div>
          </div>

          <!-- No Pending Actions -->
          <div v-else class="no-actions-state">
            <div class="no-actions-icon">
              <v-icon size="64" color="success"
                >mdi-check-circle-outline</v-icon
              >
            </div>
            <div class="no-actions-title">All Caught Up!</div>
            <div class="no-actions-desc">
              No pending actions require your attention
            </div>
          </div>
        </div>

        <!-- Recent Activity Widget -->
        <RecentActivityWidget :limit="10" class="mb-6" />
      </v-col>

      <!-- Right Column - 1/3 Width -->
      <v-col cols="12" lg="4">
        <!-- Calendar Widget -->
        <DashboardCalendar />
      </v-col>
    </v-row>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, onUnmounted } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import api from "@/services/api";
import { useToast } from "vue-toastification";
import { onAttendanceUpdate } from "@/stores/attendance";
import DashboardCalendar from "@/components/DashboardCalendar.vue";
import RecentActivityWidget from "@/components/audit/RecentActivityWidget.vue";
import { devLog } from "@/utils/devLog";
import { formatNumber } from "@/utils/formatters";

const toast = useToast();
const router = useRouter();
const authStore = useAuthStore();

const stats = ref({
  totalEmployees: 0,
  activeEmployees: 0,
  periodPayroll: 0,
  presentToday: 0,
  pendingApprovals: 0,
  pendingApplications: 0,
  pendingLeaves: 0,
  pendingAttendanceCorrections: 0,
  draftPayrolls: 0,
  pendingResignations: 0,
  pendingMealAllowances: 0,
  pending13thMonthPay: 0,
  pendingEmployeeLoans: 0,
  employeesCompleteData: 0,
  monthlyAttendanceRate: 0,
  lastBiometricImportDate: null,
});
const DASHBOARD_STATS_CACHE_KEY = "dashboard:stats:v1";
const isFetchingDashboard = ref(false);

const refreshing = ref(false);

const currentDateRange = computed(() => {
  const today = new Date();
  const start = new Date(today.getFullYear(), today.getMonth(), 1);
  const end = new Date(today.getFullYear(), today.getMonth() + 1, 0);
  return `${start.toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
  })} - ${end.toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  })}`;
});

const fullName = computed(() => {
  return authStore.user?.name || authStore.user?.username || "Admin";
});

const totalPendingActions = computed(() => {
  return (
    stats.value.pendingApplications +
    stats.value.pendingLeaves +
    stats.value.pendingAttendanceCorrections +
    (stats.value.pendingModificationRequests || 0) +
    (stats.value.pendingDeductionRequests || 0) +
    stats.value.draftPayrolls +
    (stats.value.pendingResignations || 0) +
    (stats.value.pendingMealAllowances || 0) +
    (stats.value.pending13thMonthPay || 0) +
    (stats.value.pendingEmployeeLoans || 0)
  );
});

let unsubscribeAttendance = null;

onMounted(async () => {
  const cachedStats = sessionStorage.getItem(DASHBOARD_STATS_CACHE_KEY);
  if (cachedStats) {
    try {
      stats.value = { ...stats.value, ...JSON.parse(cachedStats) };
    } catch {
      sessionStorage.removeItem(DASHBOARD_STATS_CACHE_KEY);
    }
  }

  fetchDashboardData();

  // Listen for attendance updates
  unsubscribeAttendance = onAttendanceUpdate((detail) => {
    // Refresh dashboard stats when attendance is created/updated/deleted
    fetchDashboardData();
  });
});

onUnmounted(() => {
  // Cleanup listener
  if (unsubscribeAttendance) {
    unsubscribeAttendance();
  }
});

async function fetchDashboardData() {
  if (isFetchingDashboard.value) return;

  isFetchingDashboard.value = true;
  try {
    const response = await api.get("/dashboard", { cacheTTL: 20000 });
    stats.value = response.data.stats;
    sessionStorage.setItem(
      DASHBOARD_STATS_CACHE_KEY,
      JSON.stringify(response.data.stats),
    );
  } catch (error) {
    devLog.error("Error fetching dashboard data:", error);
  } finally {
    isFetchingDashboard.value = false;
  }
}

function goToAttendanceToday() {
  const today = new Date().toISOString().split("T")[0];
  router.push({
    path: "/attendance",
    query: {
      date_from: today,
      date_to: today,
      tab: "list",
    },
  });
}

function goToPendingActions() {
  // Intelligently route to the page with pending actions
  // Priority order: Applications > Leaves > Attendance Corrections > Payrolls
  if (stats.value.pendingApplications > 0) {
    router.push("/resume-review");
  } else if (stats.value.pendingLeaves > 0) {
    router.push("/leave-approval");
  } else if (stats.value.pendingAttendanceCorrections > 0) {
    router.push({ path: "/attendance", query: { tab: "approvals" } });
  } else if (stats.value.pendingModificationRequests > 0) {
    router.push({ path: "/attendance", query: { tab: "mod-requests" } });
  } else if (stats.value.pendingDeductionRequests > 0) {
    router.push({ path: "/deductions", query: { tab: "access-requests" } });
  } else if (stats.value.draftPayrolls > 0) {
    router.push("/payroll");
  } else {
    // Fallback to resume-review if no specific pending actions
    router.push("/resume-review");
  }
}

async function refreshData() {
  refreshing.value = true;
  try {
    await fetchDashboardData();
    toast.success("Dashboard refreshed successfully!");
  } catch (error) {
    devLog.error("Error refreshing dashboard:", error);
    toast.error("Failed to refresh dashboard");
  } finally {
    refreshing.value = false;
  }
}

</script>

<style scoped lang="scss">
.modern-dashboard {
  max-width: 1600px;
  margin: 0 auto;
  padding: 0 8px;
}

// Compact Modern Header
.dashboard-header-compact {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 32px;
  padding: 0 8px;

  @media (max-width: 960px) {
    flex-direction: column;
    gap: 16px;
  }
}

.header-left {
  flex: 1;
}

.welcome-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  border-radius: 20px;
  color: #ffffff;
  font-size: 12px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 12px;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);

  .welcome-icon {
    color: #ffffff !important;
  }
}

.dashboard-title {
  font-size: 32px;
  font-weight: 700;
  color: #001f3d;
  margin: 0 0 8px 0;
  letter-spacing: -1px;
}

.dashboard-subtitle {
  font-size: 15px;
  color: rgba(0, 31, 61, 0.6);
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 12px;
  align-items: center;
}

.refresh-btn {
  text-transform: none;
  font-weight: 600;
  color: #ed985f !important;

  :deep(.v-icon) {
    color: #ed985f !important;
  }
}

// Modern Stat Cards with Icon on Left
.stats-row {
  margin-bottom: 32px;
}

.stat-card-new {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 24px;
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  overflow: hidden;
  height: 100%;

  &::before {
    content: "";
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: linear-gradient(180deg, #ed985f 0%, #f7b980 100%);
    transform: scaleY(0);
    transition: transform 0.3s ease;
  }

  &:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(237, 152, 95, 0.2);
    border-color: rgba(237, 152, 95, 0.3);

    &::before {
      transform: scaleY(1);
    }

    .stat-arrow {
      transform: translateX(4px);
      opacity: 1;
    }
  }
}

.stat-icon-wrapper {
  flex-shrink: 0;
}

.stat-icon-circle {
  width: 60px;
  height: 60px;
  border-radius: 14px;
  background: linear-gradient(
    135deg,
    rgba(237, 152, 95, 0.12) 0%,
    rgba(247, 185, 128, 0.08) 100%
  );
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;

  .v-icon {
    color: #ed985f !important;
  }
}

.stat-icon-pulse {
  animation: pulse-glow 2s infinite;
}

@keyframes pulse-glow {
  0%,
  100% {
    box-shadow: 0 0 0 0 rgba(237, 152, 95, 0.4);
  }
  50% {
    box-shadow: 0 0 0 8px rgba(237, 152, 95, 0);
  }
}

.stat-content {
  flex: 1;
  min-width: 0;
}

.stat-label {
  font-size: 13px;
  font-weight: 600;
  color: rgba(0, 31, 61, 0.6);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 8px;
}

.stat-value {
  font-size: 32px;
  font-weight: 700;
  color: #001f3d;
  line-height: 1;
  margin-bottom: 6px;
  letter-spacing: -1px;
}

.stat-meta {
  font-size: 13px;
  color: rgba(0, 31, 61, 0.5);
}

.stat-arrow {
  flex-shrink: 0;
  opacity: 0.5;
  transition: all 0.3s ease;

  .v-icon {
    color: #ed985f !important;
  }
}

// Action Section
.action-section {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  padding: 24px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.section-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  margin: -24px -24px 20px -24px;
  background: linear-gradient(
    135deg,
    rgba(0, 31, 61, 0.02) 0%,
    rgba(237, 152, 95, 0.02) 100%
  );
  border-bottom: 1px solid rgba(0, 31, 61, 0.08);
}

.section-title-wrapper {
  display: flex;
  align-items: center;
  gap: 12px;
}

.section-icon-badge {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.25);

  &.success {
    background: linear-gradient(135deg, #f7b980 0%, #ed985f 100%);
  }

  .v-icon {
    color: #ffffff !important;
  }
}

.section-title {
  font-size: 16px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
  letter-spacing: -0.3px;
}

// Action Grid
.action-grid {
  display: grid;
  gap: 12px;
}

.action-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 18px;
  background: #fafafa;
  border-radius: 12px;
  border: 1px solid rgba(0, 31, 61, 0.06);
  cursor: pointer;
  transition: all 0.3s ease;

  &:hover {
    background: #ffffff;
    border-color: rgba(237, 152, 95, 0.3);
    transform: translateX(4px);
    box-shadow: 0 4px 16px rgba(237, 152, 95, 0.15);
  }
}

.action-item-icon {
  width: 48px;
  height: 48px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;

  &.action-icon-warning {
    background: linear-gradient(
      135deg,
      rgba(247, 185, 128, 0.2) 0%,
      rgba(237, 152, 95, 0.15) 100%
    );

    .v-icon {
      color: #ed985f !important;
    }
  }

  &.action-icon-info {
    background: linear-gradient(
      135deg,
      rgba(237, 152, 95, 0.15) 0%,
      rgba(247, 185, 128, 0.1) 100%
    );

    .v-icon {
      color: #ed985f !important;
    }
  }

  &.action-icon-primary {
    background: linear-gradient(
      135deg,
      rgba(237, 152, 95, 0.2) 0%,
      rgba(237, 152, 95, 0.1) 100%
    );

    .v-icon {
      color: #ed985f !important;
    }
  }

  &.action-icon-success {
    background: linear-gradient(
      135deg,
      rgba(247, 185, 128, 0.15) 0%,
      rgba(237, 152, 95, 0.1) 100%
    );

    .v-icon {
      color: #ed985f !important;
    }
  }

  &.action-icon-danger {
    background: linear-gradient(
      135deg,
      rgba(239, 68, 68, 0.15) 0%,
      rgba(239, 68, 68, 0.1) 100%
    );

    .v-icon {
      color: #ef4444 !important;
    }
  }
}

.action-item-content {
  flex: 1;
  min-width: 0;
}

.action-item-title {
  font-size: 15px;
  font-weight: 600;
  color: #001f3d;
  margin-bottom: 4px;
}

.action-item-desc {
  font-size: 13px;
  color: rgba(0, 31, 61, 0.6);
}

.action-item-badge {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: #ffffff;
  font-weight: 700;
  font-size: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);
}

// No Actions State
.no-actions-state {
  text-align: center;
  padding: 60px 20px;
}

.no-actions-icon {
  margin-bottom: 16px;

  .v-icon {
    color: #ed985f !important;
  }
}

.no-actions-title {
  font-size: 20px;
  font-weight: 700;
  color: #001f3d;
  margin-bottom: 8px;
}

.no-actions-desc {
  font-size: 14px;
  color: rgba(0, 31, 61, 0.6);
}

// Quick Actions Section
.quick-actions-section {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.section-header-compact {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 16px;
}

.section-title-compact {
  font-size: 16px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
}

.quick-action-buttons {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.quick-action-btn {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 16px;
  background: linear-gradient(
    135deg,
    rgba(237, 152, 95, 0.08) 0%,
    rgba(247, 185, 128, 0.04) 100%
  );
  border: 1px solid rgba(237, 152, 95, 0.15);
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 15px;
  font-weight: 600;
  color: #001f3d;
  text-align: left;
  width: 100%;

  &:hover {
    background: linear-gradient(
      135deg,
      rgba(237, 152, 95, 0.15) 0%,
      rgba(247, 185, 128, 0.08) 100%
    );
    border-color: rgba(237, 152, 95, 0.3);
    transform: translateX(4px);
    box-shadow: 0 4px 12px rgba(237, 152, 95, 0.2);
  }
}

.quick-action-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);

  .v-icon {
    color: #ffffff !important;
  }
}

// System Health Section
.system-health-section {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.health-metrics {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.health-metric {
  // Metric with progress bar
  .metric-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
  }

  .metric-label {
    font-size: 13px;
    font-weight: 600;
    color: rgba(0, 31, 61, 0.7);
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  .metric-value {
    font-size: 18px;
    font-weight: 700;
    color: #001f3d;
  }

  .metric-progress {
    height: 8px;
    background: rgba(0, 31, 61, 0.06);
    border-radius: 20px;
    overflow: hidden;
  }

  .metric-progress-bar {
    height: 100%;
    background: linear-gradient(90deg, #ed985f 0%, #f7b980 100%);
    border-radius: 20px;
    transition: width 0.6s ease;
    box-shadow: 0 0 8px rgba(237, 152, 95, 0.4);
  }

  // Metric info
  .metric-info {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px;
    background: rgba(0, 31, 61, 0.03);
    border-radius: 10px;
  }

  .metric-info-left {
    display: flex;
    align-items: center;
    gap: 10px;
  }

  .metric-icon {
    color: #ed985f !important;
  }

  .metric-info-label {
    font-size: 14px;
    font-weight: 600;
    color: rgba(0, 31, 61, 0.7);
  }

  .metric-badge {
    padding: 6px 12px;
    background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
    color: #ffffff;
    border-radius: 8px;
    font-weight: 700;
    font-size: 13px;
    box-shadow: 0 2px 6px rgba(237, 152, 95, 0.3);
  }

  .metric-text {
    font-size: 13px;
    font-weight: 600;
    color: #001f3d;
  }
}

// Additional overrides for widgets
:deep(.v-chip) {
  font-weight: 600;
  border-radius: 8px;

  &.bg-warning,
  &.bg-info,
  &.bg-primary,
  &.bg-success {
    background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%) !important;
    color: #ffffff !important;
  }
}
</style>
