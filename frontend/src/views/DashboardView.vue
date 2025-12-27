<template>
  <div>
    <!-- Page Header with Construction Theme -->
    <v-row class="mb-6">
      <v-col cols="12">
        <div class="d-flex align-center justify-space-between">
          <div>
            <h1 class="construction-header text-h3 mb-2">Dashboard</h1>
            <p class="text-subtitle-1 text-medium-emphasis">
              <v-icon size="small" class="mr-1">mdi-calendar-today</v-icon>
              {{ currentDate }}
            </p>
          </div>
          <v-btn
            color="primary"
            size="large"
            prepend-icon="mdi-refresh"
            variant="elevated"
            @click="refreshData"
            class="construction-btn"
          >
            Refresh Data
          </v-btn>
        </div>
        <div class="steel-divider mt-4"></div>
      </v-col>
    </v-row>

    <!-- Statistics Cards with Construction Theme -->
    <v-row>
      <v-col cols="12" sm="6" md="3">
        <v-card class="industrial-card stat-card stat-card-primary">
          <v-card-text class="pa-5">
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-overline text-medium-emphasis font-weight-bold">
                  Total Workers
                </div>
                <div class="text-h3 font-weight-bold mt-2 mb-2">
                  {{ stats.totalEmployees }}
                </div>
                <div class="text-caption">
                  <v-chip size="x-small" color="success" variant="flat">
                    <v-icon start size="x-small">mdi-arrow-up</v-icon>
                    {{ stats.activeEmployees }} active
                  </v-chip>
                </div>
              </div>
              <v-avatar color="primary" size="72" class="stat-avatar">
                <v-icon size="40">mdi-hard-hat</v-icon>
              </v-avatar>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card class="industrial-card stat-card stat-card-success">
          <v-card-text class="pa-5">
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-overline text-medium-emphasis font-weight-bold">
                  This Period
                </div>
                <div class="text-h3 font-weight-bold mt-2 mb-2">
                  ₱{{ formatNumber(stats.periodPayroll) }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  Total Payroll
                </div>
              </div>
              <v-avatar color="success" size="72" class="stat-avatar">
                <v-icon size="40">mdi-currency-php</v-icon>
              </v-avatar>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card class="industrial-card stat-card stat-card-info">
          <v-card-text class="pa-5">
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-overline text-medium-emphasis font-weight-bold">
                  Attendance Today
                </div>
                <div class="text-h3 font-weight-bold mt-2 mb-2">
                  {{ stats.presentToday }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  of {{ stats.totalEmployees }} workers
                </div>
              </div>
              <v-avatar color="info" size="72" class="stat-avatar">
                <v-icon size="40">mdi-clock-check-outline</v-icon>
              </v-avatar>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card class="industrial-card stat-card stat-card-warning">
          <v-card-text class="pa-5">
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-overline text-medium-emphasis font-weight-bold">
                  Pending Tasks
                </div>
                <div class="text-h3 font-weight-bold mt-2 mb-2">
                  {{ stats.pendingApprovals }}
                </div>
                <div class="text-caption">
                  <v-chip size="x-small" color="warning" variant="flat">
                    <v-icon start size="x-small">mdi-alert</v-icon>
                    Action Required
                  </v-chip>
                </div>
              </div>
              <v-avatar color="warning" size="72" class="stat-avatar">
                <v-icon size="40">mdi-clipboard-alert-outline</v-icon>
              </v-avatar>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Quick Actions with Construction Theme -->
    <v-row class="mt-6">
      <v-col cols="12">
        <v-card class="industrial-card quick-actions-card">
          <v-card-title class="construction-header pa-5">
            <v-icon start size="24">mdi-toolbox-outline</v-icon>
            Quick Actions
          </v-card-title>
          <v-divider class="steel-divider"></v-divider>
          <v-card-text class="pa-5">
            <v-row>
              <v-col cols="6" sm="4" md="3">
                <v-btn
                  color="primary"
                  variant="elevated"
                  block
                  size="x-large"
                  class="construction-btn action-btn"
                  to="/employees/create"
                >
                  <v-icon size="28" class="mb-2">mdi-account-plus-outline</v-icon>
                  <div class="text-caption">Add Worker</div>
                </v-btn>
              </v-col>
              <v-col cols="6" sm="4" md="3">
                <v-btn
                  color="info"
                  variant="elevated"
                  block
                  size="x-large"
                  class="construction-btn action-btn"
                  to="/attendance"
                >
                  <v-icon size="28" class="mb-2">mdi-clock-check-outline</v-icon>
                  <div class="text-caption">Attendance</div>
                </v-btn>
              </v-col>
              <v-col cols="6" sm="4" md="3">
                <v-btn
                  color="success"
                  variant="elevated"
                  block
                  size="x-large"
                  class="construction-btn action-btn"
                  to="/payroll/create"
                >
                  <v-icon size="28" class="mb-2">mdi-cash-plus</v-icon>
                  <div class="text-caption">New Payroll</div>
                </v-btn>
              </v-col>
              <v-col cols="6" sm="4" md="3">
                <v-btn
                  color="accent"
                  variant="elevated"
                  block
                  size="x-large"
                  class="construction-btn action-btn"
                  to="/reports"
                >
                  <v-icon size="28" class="mb-2">mdi-file-chart-outline</v-icon>
                  <div class="text-caption">Reports</div>
                </v-btn>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Recent Activity with Construction Theme -->
    <v-row class="mt-6">
      <v-col cols="12" md="6">
        <v-card class="industrial-card" height="450">
          <v-card-title class="construction-header pa-5">
            <v-icon start size="24">mdi-clock-check-outline</v-icon>
            Recent Attendance
          </v-card-title>
          <v-divider class="steel-divider"></v-divider>
          <v-card-text>
            <v-list density="compact">
              <v-list-item
                v-for="attendance in recentAttendance"
                :key="attendance.id"
                :subtitle="`${attendance.time_in || 'N/A'} - ${
                  attendance.time_out || 'Ongoing'
                }`"
              >
                <template v-slot:prepend>
                  <v-avatar :color="getAttendanceColor(attendance.status)">
                    <v-icon>{{ getAttendanceIcon(attendance.status) }}</v-icon>
                  </v-avatar>
                </template>
                <v-list-item-title>{{
                  attendance.employee_name
                }}</v-list-item-title>
              </v-list-item>
            </v-list>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="6">
        <v-card class="industrial-card" height="450">
          <v-card-title class="construction-header pa-5">
            <v-icon start size="24">mdi-cash-clock</v-icon>
            Recent Payrolls
          </v-card-title>
          <v-divider class="steel-divider"></v-divider>
          <v-card-text>
            <v-list density="compact">
              <v-list-item
                v-for="payroll in recentPayrolls"
                :key="payroll.id"
                :subtitle="`${payroll.period_start_date} to ${payroll.period_end_date}`"
                :to="`/payroll/${payroll.id}`"
              >
                <template v-slot:prepend>
                  <v-avatar :color="getPayrollColor(payroll.status)">
                    <v-icon>mdi-cash</v-icon>
                  </v-avatar>
                </template>
                <v-list-item-title>{{
                  payroll.payroll_number
                }}</v-list-item-title>
                <template v-slot:append>
                  <v-chip :color="getPayrollColor(payroll.status)" size="small">
                    {{ payroll.status }}
                  </v-chip>
                </template>
              </v-list-item>
            </v-list>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import api from "@/services/api";

const stats = ref({
  totalEmployees: 0,
  activeEmployees: 0,
  periodPayroll: 0,
  presentToday: 0,
  pendingApprovals: 0,
});

const recentAttendance = ref([]);
const recentPayrolls = ref([]);

const currentDate = computed(() => {
  return new Date().toLocaleDateString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric'
  });
});

onMounted(async () => {
  await fetchDashboardData();
});

async function fetchDashboardData() {
  try {
    const response = await api.get("/dashboard");
    stats.value = response.data.stats;
    recentAttendance.value = response.data.recent_attendance || [];
    recentPayrolls.value = response.data.recent_payrolls || [];
  } catch (error) {
    console.error("Error fetching dashboard data:", error);
  }
}

async function refreshData() {
  await fetchDashboardData();
}

function formatNumber(value) {
  return new Intl.NumberFormat("en-PH", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value || 0);
}

function getAttendanceColor(status) {
  const colors = {
    present: "success",
    absent: "error",
    late: "warning",
    halfday: "info",
  };
  return colors[status] || "grey";
}

function getAttendanceIcon(status) {
  const icons = {
    present: "mdi-check-circle",
    absent: "mdi-close-circle",
    late: "mdi-clock-alert",
    halfday: "mdi-clock-half",
  };
  return icons[status] || "mdi-help-circle";
}

function getPayrollColor(status) {
  const colors = {
    draft: "grey",
    processing: "info",
    checked: "warning",
    recommended: "accent",
    approved: "success",
    paid: "primary",
  };
  return colors[status] || "grey";
}
</script>

<style scoped lang="scss">
// Construction-themed Dashboard Styling

.construction-header {
  font-family: "Roboto Condensed", sans-serif;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  color: #37474F;
}

.construction-btn {
  text-transform: none;
  font-weight: 700;
  letter-spacing: 0.5px;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
  transition: all 0.3s ease;
  
  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
  }
}

// Stat Cards with Construction Theme
.stat-card {
  position: relative;
  overflow: hidden;
  transition: all 0.3s ease;
  
  &::before {
    content: "";
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 50%;
    transform: translate(30%, -30%);
  }
  
  &:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15) !important;
  }
  
  &.stat-card-primary {
    border-left-color: #D84315 !important;
  }
  
  &.stat-card-success {
    border-left-color: #2E7D32 !important;
  }
  
  &.stat-card-info {
    border-left-color: #0277BD !important;
  }
  
  &.stat-card-warning {
    border-left-color: #F9A825 !important;
  }
}

.stat-avatar {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  border: 3px solid rgba(255, 255, 255, 0.3);
}

// Quick Actions Card
.quick-actions-card {
  background: linear-gradient(135deg, #FFFFFF 0%, #F5F5F5 100%) !important;
}

.action-btn {
  height: 100px !important;
  flex-direction: column;
  gap: 8px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  
  :deep(.v-btn__content) {
    flex-direction: column;
    gap: 8px;
  }
  
  &:hover {
    transform: translateY(-4px) scale(1.02);
  }
}

// List items with construction accents
:deep(.v-list-item) {
  border-left: 3px solid transparent;
  transition: all 0.2s ease;
  
  &:hover {
    border-left-color: #D84315;
    background: rgba(216, 67, 21, 0.05);
  }
}

// Card title styling
:deep(.v-card-title) {
  font-weight: 700;
  letter-spacing: 0.5px;
}

// Chart card styling
.chart-card {
  background: linear-gradient(135deg, #FFFFFF 0%, #FAFAFA 100%) !important;
  
  canvas {
    max-height: 300px;
  }
}
</style>
