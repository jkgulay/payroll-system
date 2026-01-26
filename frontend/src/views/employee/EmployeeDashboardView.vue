<template>
  <div class="modern-dashboard">
    <!-- Dashboard Content -->
    <div>
      <!-- Merged Header with Employee Info -->
      <div class="dashboard-header-merged">
        <div class="header-content">
          <v-avatar color="#ED985F" size="80" class="header-avatar">
            <v-img v-if="userAvatar" :src="userAvatar" cover></v-img>
            <v-icon v-else size="40" color="white">mdi-account</v-icon>
          </v-avatar>
          <div class="header-info">
            <div class="welcome-badge">
              <v-icon size="16" class="welcome-icon">mdi-account-circle</v-icon>
              <span>Employee Portal</span>
            </div>
            <h1 class="dashboard-title">Welcome, {{ fullName }}</h1>
            <p class="dashboard-subtitle">
              {{ employee?.position }} - {{ employee?.project?.name }} •
              Employee No: {{ employee?.employee_number }}
            </p>
            <p class="dashboard-date">{{ currentMonthYear }}</p>
          </div>
        </div>
        <div class="header-actions">
          <v-btn
            variant="text"
            prepend-icon="mdi-refresh"
            @click="loadDashboardData"
            :loading="refreshing"
            class="refresh-btn"
          >
            Refresh
          </v-btn>
        </div>
      </div>

      <!-- Modern Statistics Cards -->
      <v-row class="stats-row">
        <v-col cols="12" sm="6" lg="3">
          <div class="stat-card-new stat-card-employees">
            <div class="stat-icon-wrapper">
              <div class="stat-icon-circle">
                <v-icon size="22">mdi-calendar-check</v-icon>
              </div>
            </div>
            <div class="stat-content">
              <div class="stat-label">Present This Month</div>
              <div class="stat-value">{{ presentDays }}</div>
              <div class="stat-meta">of {{ totalDays }} days</div>
            </div>
          </div>
        </v-col>

        <v-col cols="12" sm="6" lg="3">
          <div class="stat-card-new stat-card-attendance">
            <div class="stat-icon-wrapper">
              <div class="stat-icon-circle">
                <v-icon size="22">mdi-clock-alert</v-icon>
              </div>
            </div>
            <div class="stat-content">
              <div class="stat-label">Late / Undertime</div>
              <div class="stat-value">{{ lateDays }}</div>
              <div class="stat-meta">times this month</div>
            </div>
          </div>
        </v-col>

        <v-col cols="12" sm="6" lg="3">
          <div class="stat-card-new stat-card-payroll">
            <div class="stat-icon-wrapper">
              <div class="stat-icon-circle">
                <v-icon size="22">mdi-clock-time-four</v-icon>
              </div>
            </div>
            <div class="stat-content">
              <div class="stat-label">Total Hours</div>
              <div class="stat-value">{{ totalHours }}</div>
              <div class="stat-meta">hours worked</div>
            </div>
          </div>
        </v-col>

        <v-col cols="12" sm="6" lg="3">
          <div
            class="stat-card-new stat-card-pending"
            @click="downloadCurrentPayslip"
          >
            <div class="stat-icon-wrapper">
              <div class="stat-icon-circle stat-icon-pulse">
                <v-icon size="22">mdi-cash-multiple</v-icon>
              </div>
            </div>
            <div class="stat-content">
              <div class="stat-label">Latest Payslip</div>
              <div class="stat-value">₱{{ formatNumber(latestPayslip) }}</div>
              <div class="stat-meta">Click to download</div>
            </div>
            <div class="stat-arrow">
              <v-icon size="20">mdi-download</v-icon>
            </div>
          </div>
        </v-col>
      </v-row>

      <!-- Main Content Layout -->
      <v-row>
        <!-- Left Column - 2/3 Width -->
        <v-col cols="12" lg="8">
          <!-- Attendance Records Section -->
          <div class="attendance-section mb-6">
            <div class="section-header-compact">
              <div class="section-icon-badge">
                <v-icon size="16">mdi-calendar-check</v-icon>
              </div>
              <h3 class="section-title-compact">
                Attendance Records (Last 3 Months)
              </h3>
            </div>

            <div class="content-card">
              <v-data-table
                :headers="attendanceHeaders"
                :items="attendanceRecords"
                :items-per-page="15"
                class="modern-table"
              >
                <template v-slot:item.attendance_date="{ item }">
                  <span class="table-date">{{
                    formatDate(item.attendance_date)
                  }}</span>
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
                <template v-slot:item.time_in="{ item }">
                  <span class="table-time">{{ item.time_in || "N/A" }}</span>
                </template>
                <template v-slot:item.time_out="{ item }">
                  <span class="table-time">{{ item.time_out || "N/A" }}</span>
                </template>
                <template v-slot:item.regular_hours="{ item }">
                  <span class="table-hours"
                    >{{ calculateHours(item) }} hrs</span
                  >
                </template>
              </v-data-table>
            </div>
          </div>

          <!-- Payslip History -->
          <div class="payslip-history-section">
            <div class="section-header-compact">
              <div class="section-icon-badge">
                <v-icon size="16">mdi-history</v-icon>
              </div>
              <h3 class="section-title-compact">
                Payslip History (Last 12 Months)
              </h3>
            </div>

            <div class="content-card">
              <div v-if="payslipHistory.length > 0" class="payslip-list">
                <div
                  v-for="payslip in payslipHistory"
                  :key="payslip.id"
                  class="payslip-item"
                  @click="downloadPayslip(payslip.id, 'pdf')"
                >
                  <div class="payslip-icon-wrapper">
                    <v-icon size="20">mdi-file-document</v-icon>
                  </div>
                  <div class="payslip-content">
                    <div class="payslip-period">
                      {{ formatDate(payslip.payroll.period_start) }} -
                      {{ formatDate(payslip.payroll.period_end) }}
                    </div>
                    <div class="payslip-amount">
                      ₱{{ formatNumber(payslip.net_pay) }}
                    </div>
                  </div>
                  <div class="payslip-download">
                    <v-icon size="20" color="#ED985F">mdi-download</v-icon>
                  </div>
                </div>
              </div>
              <div v-else class="empty-state-small">
                <v-icon size="48" color="rgba(0, 31, 61, 0.2)"
                  >mdi-file-document-outline</v-icon
                >
                <div class="empty-state-text">No payslip history available</div>
              </div>
            </div>
          </div>
        </v-col>

        <!-- Right Column - 1/3 Width -->
        <v-col cols="12" lg="4">
          <!-- Current Payslip Details -->
          <div class="current-payslip-section mb-6">
            <div class="section-header-compact">
              <div class="section-icon-badge success">
                <v-icon size="16">mdi-cash-multiple</v-icon>
              </div>
              <h3 class="section-title-compact">Current Payslip</h3>
            </div>

            <div
              class="content-card"
              v-if="currentPayslip && currentPayslip.payroll"
            >
              <div class="payslip-details">
                <div class="payslip-detail-item">
                  <span class="detail-label">Pay Period</span>
                  <span class="detail-value">
                    {{ formatDate(currentPayslip.payroll.period_start) }} -
                    {{ formatDate(currentPayslip.payroll.period_end) }}
                  </span>
                </div>

                <div class="payslip-detail-divider"></div>

                <div class="payslip-detail-item">
                  <span class="detail-label">Gross Pay</span>
                  <span class="detail-value amount-positive"
                    >₱{{ formatNumber(currentPayslip.gross_pay) }}</span
                  >
                </div>

                <div class="payslip-detail-item">
                  <span class="detail-label">Deductions</span>
                  <span class="detail-value amount-negative"
                    >₱{{ formatNumber(currentPayslip.total_deductions) }}</span
                  >
                </div>

                <div class="payslip-detail-divider-thick"></div>

                <div class="payslip-detail-item-main">
                  <span class="detail-label-main">Net Pay</span>
                  <span class="detail-value-main"
                    >₱{{ formatNumber(currentPayslip.net_pay) }}</span
                  >
                </div>

                <div class="payslip-actions">
                  <button
                    class="payslip-action-btn primary"
                    @click="downloadPayslip(currentPayslip.id, 'pdf')"
                  >
                    <v-icon size="18">mdi-file-pdf-box</v-icon>
                    <span>Download PDF</span>
                  </button>
                  <button
                    class="payslip-action-btn secondary"
                    @click="downloadPayslip(currentPayslip.id, 'excel')"
                  >
                    <v-icon size="18">mdi-file-excel</v-icon>
                    <span>Download Excel</span>
                  </button>
                </div>
              </div>
            </div>
            <div v-else class="content-card">
              <div class="empty-state-small">
                <v-icon size="48" color="rgba(0, 31, 61, 0.2)"
                  >mdi-cash-off</v-icon
                >
                <div class="empty-state-text">No current payslip available</div>
              </div>
            </div>
          </div>

          <!-- Quick Links -->
          <div class="quick-actions-section">
            <div class="section-header-compact">
              <div class="section-icon-badge">
                <v-icon size="16">mdi-lightning-bolt</v-icon>
              </div>
              <h3 class="section-title-compact">Quick Actions</h3>
            </div>
            <div class="quick-action-buttons">
              <button
                class="quick-action-btn"
                @click="$router.push('/profile')"
              >
                <div class="quick-action-icon">
                  <v-icon>mdi-account-edit</v-icon>
                </div>
                <span>My Profile</span>
              </button>
              <button
                class="quick-action-btn"
                @click="$router.push('/my-leaves')"
              >
                <div class="quick-action-icon">
                  <v-icon>mdi-calendar-clock</v-icon>
                </div>
                <span>My Leaves</span>
              </button>
              <button
                class="quick-action-btn"
                @click="$router.push('/my-resignation')"
              >
                <div class="quick-action-icon">
                  <v-icon>mdi-account-arrow-right</v-icon>
                </div>
                <span>My Resignation</span>
              </button>
              <button class="quick-action-btn" @click="downloadCurrentPayslip">
                <div class="quick-action-icon">
                  <v-icon>mdi-download</v-icon>
                </div>
                <span>Download Payslip</span>
              </button>
            </div>
          </div>
        </v-col>
      </v-row>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import api from "@/services/api";
import { useToast } from "vue-toastification";
import { useAuthStore } from "@/stores/auth";

const router = useRouter();
const toast = useToast();
const authStore = useAuthStore();

const loading = ref(false);
const refreshing = ref(false);
const downloading = ref(false);
const employee = ref(null);
const attendanceSummary = ref({
  present: 0,
  absent: 0,
  late: 0,
  total_hours: 0,
});
const attendanceRecords = ref([]);
const currentPayslip = ref(null);
const payslipHistory = ref([]);

const currentMonth = computed(() => {
  return new Date().toLocaleDateString("en-US", {
    month: "long",
    year: "numeric",
  });
});

const currentMonthYear = computed(() => {
  return new Date().toLocaleDateString("en-US", {
    month: "long",
    day: "numeric",
    year: "numeric",
  });
});

const fullName = computed(() => {
  return (
    employee.value?.full_name ||
    authStore.user?.name ||
    authStore.user?.username ||
    "Employee"
  );
});

const presentDays = computed(() => {
  return attendanceSummary.value?.present || 0;
});

const absentDays = computed(() => {
  return attendanceSummary.value?.absent || 0;
});

const lateDays = computed(() => {
  return attendanceSummary.value?.late || 0;
});

const totalHours = computed(() => {
  const hours = attendanceSummary.value?.total_hours || 0;
  return typeof hours === "number" ? hours.toFixed(0) : "0";
});

const totalDays = computed(() => {
  return presentDays.value + absentDays.value;
});

const latestPayslip = computed(() => {
  const netPay = currentPayslip.value?.net_pay;
  if (!netPay) return 0;
  return typeof netPay === "number" ? netPay : parseFloat(netPay) || 0;
});

const userAvatar = computed(() => {
  if (!authStore.user?.avatar) return null;
  const avatar = authStore.user.avatar;
  // If avatar is already a full URL, return it
  if (avatar.startsWith("http")) return avatar;
  // Otherwise, prepend the base URL (remove /api from VITE_API_URL)
  const apiUrl = (
    import.meta.env.VITE_API_URL || "http://localhost:8000/api"
  ).replace("/api", "");
  return `${apiUrl}/storage/${avatar}`;
});

const attendanceHeaders = [
  { title: "Date", key: "attendance_date", sortable: true },
  { title: "Status", key: "status", sortable: true },
  { title: "Time In", key: "time_in" },
  { title: "Time Out", key: "time_out" },
  { title: "Hours", key: "regular_hours" },
];

onMounted(async () => {
  await fetchDashboardData();
});

async function fetchDashboardData() {
  try {
    const response = await api.get("/employee/dashboard");

    employee.value = response.data.employee;
    attendanceSummary.value = response.data.attendance_summary || {
      present: 0,
      absent: 0,
      late: 0,
      total_hours: 0,
    };
    attendanceRecords.value = response.data.attendance || [];
    currentPayslip.value = response.data.current_payslip;
    payslipHistory.value = response.data.payslip_history || [];
  } catch (error) {
    console.error("Error loading dashboard:", error);
    toast.error("Failed to load dashboard data");
  }
}

async function loadDashboardData() {
  refreshing.value = true;
  try {
    await fetchDashboardData();
  } finally {
    refreshing.value = false;
  }
}

async function downloadPayslip(payslipId, format) {
  downloading.value = true;
  try {
    // Find the payslip in history or current
    let payslip =
      currentPayslip.value?.id === payslipId
        ? currentPayslip.value
        : payslipHistory.value.find((p) => p.id === payslipId);

    if (!payslip) {
      toast.error("Payslip not found");
      return;
    }

    const response = await api.get(
      `/payrolls/${payslip.payroll_id}/employees/${payslip.employee_id}/download-payslip`,
      {
        params: { format: format === "excel" ? "excel" : "pdf" },
        responseType: "blob",
      },
    );

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute(
      "download",
      `payslip_${payslip.employee_id}_${format === "pdf" ? ".pdf" : ".xlsx"}`,
    );
    document.body.appendChild(link);
    link.click();
    link.remove();

    toast.success(`Payslip downloaded successfully`);
  } catch (error) {
    console.error("Error downloading payslip:", error);
    toast.error("Failed to download payslip");
  } finally {
    downloading.value = false;
  }
}

function downloadCurrentPayslip() {
  if (currentPayslip.value) {
    downloadPayslip(currentPayslip.value.id, "pdf");
  } else {
    toast.warning("No current payslip available");
  }
}

function formatDate(date) {
  return new Date(date).toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
    year: "numeric",
  });
}

function formatNumber(value) {
  return new Intl.NumberFormat("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value || 0);
}

function getStatusColor(status) {
  const colors = {
    present: "success",
    absent: "error",
    late: "warning",
    undertime: "warning",
    holiday: "info",
  };
  return colors[status] || "grey";
}

function calculateHours(record) {
  if (
    record.regular_hours &&
    typeof record.regular_hours === "number" &&
    record.regular_hours > 0
  ) {
    return record.regular_hours.toFixed(2);
  }

  if (record.time_in && record.time_out) {
    try {
      const timeIn = new Date(`2000-01-01 ${record.time_in}`);
      const timeOut = new Date(`2000-01-01 ${record.time_out}`);
      const hours = (timeOut - timeIn) / (1000 * 60 * 60);
      return hours > 0 ? hours.toFixed(2) : "0.00";
    } catch (error) {
      console.error("Error calculating hours:", error);
      return "0.00";
    }
  }

  return "0.00";
}
</script>

<style scoped lang="scss">
.modern-dashboard {
  max-width: 1600px;
  margin: 0 auto;
  padding: 0 8px;
}

.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 400px;
  gap: 16px;

  .loading-text {
    color: rgba(0, 31, 61, 0.6);
    font-size: 15px;
  }
}

// Merged Header with Employee Info
.dashboard-header-merged {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 32px;
  padding: 28px;
  background: linear-gradient(135deg, #001f3d 0%, #0a2f4d 100%);
  border-radius: 20px;
  box-shadow: 0 4px 16px rgba(0, 31, 61, 0.15);

  @media (max-width: 960px) {
    flex-direction: column;
    gap: 20px;
    align-items: flex-start;
  }
}

.header-content {
  display: flex;
  align-items: center;
  gap: 24px;
  flex: 1;

  @media (max-width: 960px) {
    width: 100%;
  }
}

.header-avatar {
  flex-shrink: 0;
  border: 3px solid rgba(255, 255, 255, 0.2);
}

.header-info {
  flex: 1;
  min-width: 0;
}

.dashboard-title {
  font-size: 28px;
  font-weight: 700;
  color: #ffffff;
  margin: 8px 0 6px 0;
  letter-spacing: -0.5px;
}

.dashboard-subtitle {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.85);
  margin: 0 0 4px 0;
}

.dashboard-date {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.6);
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 12px;
  align-items: center;

  @media (max-width: 960px) {
    width: 100%;
    justify-content: flex-end;
  }
}

.refresh-btn {
  text-transform: none;
  font-weight: 600;
  color: #ffffff !important;
  background: rgba(255, 255, 255, 0.1) !important;

  &:hover {
    background: rgba(255, 255, 255, 0.2) !important;
  }

  :deep(.v-icon) {
    color: #ffffff !important;
  }
}

// Modern Stat Cards
.stats-row {
  margin-bottom: 20px;
}

.stat-card-new {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  background: #ffffff;
  border-radius: 12px;
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
  width: 48px;
  height: 48px;
  border-radius: 10px;
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
  font-size: 11px;
  font-weight: 600;
  color: rgba(0, 31, 61, 0.6);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 6px;
}

.stat-value {
  font-size: 24px;
  font-weight: 700;
  color: #001f3d;
  line-height: 1;
  margin-bottom: 4px;
}

.stat-meta {
  font-size: 12px;
  color: rgba(0, 31, 61, 0.5);
}

.stat-arrow {
  opacity: 0;
  transform: translateX(0);
  transition: all 0.3s ease;
  color: #ed985f;
}

.welcome-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  border-radius: 20px;
  color: #ffffff;
  font-size: 11px;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 8px;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);

  .welcome-icon {
    color: #ffffff !important;
  }
}

// Section Headers
.section-header-compact {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
  padding: 0 8px;
}

.section-icon-badge {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: linear-gradient(
    135deg,
    rgba(237, 152, 95, 0.12) 0%,
    rgba(247, 185, 128, 0.08) 100%
  );
  display: flex;
  align-items: center;
  justify-content: center;

  &.success {
    background: linear-gradient(
      135deg,
      rgba(16, 185, 129, 0.12) 0%,
      rgba(16, 185, 129, 0.08) 100%
    );

    .v-icon {
      color: #10b981 !important;
    }
  }

  .v-icon {
    color: #ed985f !important;
  }
}

.section-title-compact {
  font-size: 18px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
}

// Content Cards
.content-card {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  padding: 24px;
}

// Modern Table
.modern-table {
  :deep(.v-data-table-header) {
    background: rgba(0, 31, 61, 0.02);

    th {
      font-weight: 600 !important;
      font-size: 13px !important;
      color: rgba(0, 31, 61, 0.7) !important;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
  }

  .table-date {
    font-weight: 500;
    color: #001f3d;
  }

  .table-time {
    font-family: "Courier New", monospace;
    font-size: 14px;
    color: rgba(0, 31, 61, 0.8);
  }

  .table-hours {
    font-weight: 600;
    color: #ed985f;
  }
}

// Payslip List
.payslip-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.payslip-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  background: #ffffff;
  border: 1px solid rgba(0, 31, 61, 0.08);
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.3s ease;

  &:hover {
    transform: translateX(4px);
    box-shadow: 0 4px 12px rgba(237, 152, 95, 0.1);
    border-color: rgba(237, 152, 95, 0.3);

    .payslip-download {
      transform: scale(1.1);
    }
  }
}

.payslip-icon-wrapper {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: linear-gradient(
    135deg,
    rgba(237, 152, 95, 0.12) 0%,
    rgba(247, 185, 128, 0.08) 100%
  );
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;

  .v-icon {
    color: #ed985f !important;
  }
}

.payslip-content {
  flex: 1;
}

.payslip-period {
  font-size: 14px;
  font-weight: 600;
  color: #001f3d;
  margin-bottom: 4px;
}

.payslip-amount {
  font-size: 18px;
  font-weight: 700;
  color: #10b981;
}

.payslip-download {
  transition: transform 0.3s ease;
}

// Current Payslip Details
.payslip-details {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.payslip-detail-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.detail-label {
  font-size: 14px;
  color: rgba(0, 31, 61, 0.6);
  font-weight: 500;
}

.detail-value {
  font-size: 14px;
  color: #001f3d;
  font-weight: 600;

  &.amount-positive {
    color: #10b981;
  }

  &.amount-negative {
    color: #ef4444;
  }
}

.payslip-detail-divider {
  height: 1px;
  background: rgba(0, 31, 61, 0.08);
}

.payslip-detail-divider-thick {
  height: 2px;
  background: linear-gradient(90deg, #ed985f 0%, #f7b980 100%);
  margin: 8px 0;
}

.payslip-detail-item-main {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  background: linear-gradient(
    135deg,
    rgba(237, 152, 95, 0.08) 0%,
    rgba(247, 185, 128, 0.04) 100%
  );
  border-radius: 12px;
}

.detail-label-main {
  font-size: 16px;
  color: #001f3d;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.detail-value-main {
  font-size: 28px;
  color: #10b981;
  font-weight: 700;
}

// Payslip Actions
.payslip-actions {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 8px;
}

.payslip-action-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px;
  border-radius: 10px;
  border: none;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;

  &.primary {
    background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
    color: white;

    &:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(237, 152, 95, 0.4);
    }
  }

  &.secondary {
    background: rgba(0, 31, 61, 0.06);
    color: #001f3d;

    &:hover {
      background: rgba(0, 31, 61, 0.1);
    }
  }
}

// Quick Actions
.quick-actions-section {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  padding: 20px;
}

.quick-action-buttons {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.quick-action-btn {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  background: rgba(0, 31, 61, 0.02);
  border: 1px solid rgba(0, 31, 61, 0.08);
  border-radius: 12px;
  cursor: pointer;
  transition: all 0.3s ease;
  font-size: 14px;
  font-weight: 600;
  color: #001f3d;

  &:hover {
    background: linear-gradient(
      135deg,
      rgba(237, 152, 95, 0.08) 0%,
      rgba(247, 185, 128, 0.04) 100%
    );
    border-color: rgba(237, 152, 95, 0.3);
    transform: translateX(4px);
  }
}

.quick-action-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: linear-gradient(
    135deg,
    rgba(237, 152, 95, 0.12) 0%,
    rgba(247, 185, 128, 0.08) 100%
  );
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;

  .v-icon {
    color: #ed985f !important;
  }
}

// Empty States
.empty-state-small {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 40px 20px;
  text-align: center;
}

.empty-state-text {
  margin-top: 12px;
  font-size: 14px;
  color: rgba(0, 31, 61, 0.5);
}
</style>
