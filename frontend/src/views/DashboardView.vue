<template>
  <div>
    <v-row>
      <v-col cols="12">
        <h1 class="text-h4 font-weight-bold mb-6">Dashboard</h1>
      </v-col>
    </v-row>

    <!-- Statistics Cards -->
    <v-row>
      <v-col cols="12" sm="6" md="3">
        <v-card>
          <v-card-text>
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-overline text-medium-emphasis">
                  Total Employees
                </div>
                <div class="text-h4 font-weight-bold">
                  {{ stats.totalEmployees }}
                </div>
                <div class="text-caption text-success">
                  <v-icon size="small">mdi-arrow-up</v-icon>
                  {{ stats.activeEmployees }} active
                </div>
              </div>
              <v-avatar color="primary" size="56">
                <v-icon size="32">mdi-account-group</v-icon>
              </v-avatar>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card>
          <v-card-text>
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-overline text-medium-emphasis">
                  This Period
                </div>
                <div class="text-h4 font-weight-bold">
                  ₱{{ formatNumber(stats.periodPayroll) }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  Payroll amount
                </div>
              </div>
              <v-avatar color="success" size="56">
                <v-icon size="32">mdi-cash-multiple</v-icon>
              </v-avatar>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card>
          <v-card-text>
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-overline text-medium-emphasis">
                  Attendance Today
                </div>
                <div class="text-h4 font-weight-bold">
                  {{ stats.presentToday }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  of {{ stats.totalEmployees }} employees
                </div>
              </div>
              <v-avatar color="info" size="56">
                <v-icon size="32">mdi-calendar-check</v-icon>
              </v-avatar>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card>
          <v-card-text>
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-overline text-medium-emphasis">
                  Pending Approvals
                </div>
                <div class="text-h4 font-weight-bold">
                  {{ stats.pendingApprovals }}
                </div>
                <div class="text-caption text-warning">Requires attention</div>
              </div>
              <v-avatar color="warning" size="56">
                <v-icon size="32">mdi-alert-circle</v-icon>
              </v-avatar>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Quick Actions -->
    <v-row class="mt-4">
      <v-col cols="12">
        <v-card>
          <v-card-title class="bg-primary">
            <v-icon start>mdi-lightning-bolt</v-icon>
            Quick Actions
          </v-card-title>
          <v-card-text class="pa-4">
            <v-row>
              <v-col cols="6" sm="4" md="2">
                <v-btn
                  color="primary"
                  variant="tonal"
                  block
                  size="large"
                  prepend-icon="mdi-account-plus"
                  to="/employees/create"
                >
                  Add Employee
                </v-btn>
              </v-col>
              <v-col cols="6" sm="4" md="2">
                <v-btn
                  color="info"
                  variant="tonal"
                  block
                  size="large"
                  prepend-icon="mdi-calendar-plus"
                  to="/attendance"
                >
                  Attendance
                </v-btn>
              </v-col>
              <v-col cols="6" sm="4" md="2">
                <v-btn
                  color="success"
                  variant="tonal"
                  block
                  size="large"
                  prepend-icon="mdi-cash-plus"
                  to="/payroll/create"
                >
                  New Payroll
                </v-btn>
              </v-col>
              <v-col cols="6" sm="4" md="2">
                <v-btn
                  color="accent"
                  variant="tonal"
                  block
                  size="large"
                  prepend-icon="mdi-file-chart"
                  to="/reports"
                >
                  Reports
                </v-btn>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Recent Activity -->
    <v-row class="mt-4">
      <v-col cols="12" md="6">
        <v-card height="400">
          <v-card-title>
            <v-icon start>mdi-clock-outline</v-icon>
            Recent Attendance
          </v-card-title>
          <v-divider></v-divider>
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
        <v-card height="400">
          <v-card-title>
            <v-icon start>mdi-cash-clock</v-icon>
            Recent Payrolls
          </v-card-title>
          <v-divider></v-divider>
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
import { ref, onMounted } from "vue";
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
