<template>
  <div class="analytics-dashboard">
    <!-- Page Header -->
    <v-row class="mb-6">
      <v-col cols="12">
        <div class="d-flex align-center justify-space-between">
          <div>
            <h1 class="construction-header text-h3 mb-2">
              <v-icon size="large" class="mr-2">mdi-chart-box-outline</v-icon>
              Analytics Dashboard
            </h1>
            <p class="text-subtitle-1 text-medium-emphasis">
              Comprehensive insights and trends for your payroll system
            </p>
          </div>
          <v-btn
            color="primary"
            size="large"
            prepend-icon="mdi-refresh"
            variant="elevated"
            @click="refreshAllCharts"
            :loading="refreshing"
            class="construction-btn"
          >
            Refresh All
          </v-btn>
        </div>
        <div class="steel-divider mt-4"></div>
      </v-col>
    </v-row>

    <!-- SECTION B: EMPLOYEE ANALYTICS -->
    <v-row class="mb-4 mt-8">
      <v-col cols="12">
        <div class="section-header">
          <v-icon color="primary" size="small" class="mr-2">mdi-account-group</v-icon>
          <span class="text-h5 font-weight-bold">Employee Analytics</span>
        </div>
      </v-col>
    </v-row>

    <v-row>
      <!-- Employee Distribution -->
      <v-col cols="12" md="6">
        <v-card class="industrial-card chart-card" elevation="2">
          <v-card-title class="pa-4 bg-gradient">
            <v-icon start size="20">mdi-chart-pie</v-icon>
            Employee Distribution by Project
          </v-card-title>
          <v-divider></v-divider>
          <v-card-text class="pa-4" style="height: 350px">
            <EmployeeDistributionChart ref="employeeDistChart" type="project" />
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Employment Status Distribution -->
      <v-col cols="12" md="6">
        <v-card class="industrial-card chart-card" elevation="2">
          <v-card-title class="pa-4 bg-gradient">
            <v-icon start size="20">mdi-chart-bar</v-icon>
            Employment Status Distribution
          </v-card-title>
          <v-divider></v-divider>
          <v-card-text class="pa-4" style="height: 350px">
            <EmploymentStatusChart ref="employmentStatusChart" />
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Employee by Location -->
      <v-col cols="12" md="6">
        <v-card class="industrial-card chart-card" elevation="2">
          <v-card-title class="pa-4 bg-gradient">
            <v-icon start size="20">mdi-map-marker-multiple</v-icon>
            Employees by Location
          </v-card-title>
          <v-divider></v-divider>
          <v-card-text class="pa-4" style="height: 300px">
            <EmployeeLocationChart ref="employeeLocationChart" />
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Employee Growth Trend -->
      <v-col cols="12" md="6">
        <v-card class="industrial-card chart-card" elevation="2">
          <v-card-title class="pa-4 bg-gradient">
            <v-icon start size="20">mdi-chart-timeline-variant</v-icon>
            Employee Growth Trend
          </v-card-title>
          <v-divider></v-divider>
          <v-card-text class="pa-4" style="height: 300px">
            <EmployeeGrowthChart ref="employeeGrowthChart" :months="12" />
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- SECTION C: ATTENDANCE ANALYTICS -->
    <v-row class="mb-4 mt-8">
      <v-col cols="12">
        <div class="section-header">
          <v-icon color="info" size="small" class="mr-2">mdi-clock-check-outline</v-icon>
          <span class="text-h5 font-weight-bold">Attendance Analytics</span>
        </div>
      </v-col>
    </v-row>

    <v-row>
      <!-- Daily Attendance Rate -->
      <v-col cols="12" lg="8">
        <v-card class="industrial-card chart-card" elevation="2">
          <v-card-title class="pa-4 bg-gradient">
            <v-icon start size="20">mdi-chart-line</v-icon>
            Daily Attendance Rate (Last 30 Days)
          </v-card-title>
          <v-divider></v-divider>
          <v-card-text class="pa-4" style="height: 300px">
            <AttendanceRateChart ref="attendanceRateChart" :days="30" />
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Leave Utilization -->
      <v-col cols="12" lg="4">
        <v-card class="industrial-card chart-card" elevation="2">
          <v-card-title class="pa-4 bg-gradient">
            <v-icon start size="20">mdi-gauge</v-icon>
            Leave Utilization Rate
          </v-card-title>
          <v-divider></v-divider>
          <v-card-text class="pa-4" style="height: 300px">
            <LeaveUtilizationChart ref="leaveUtilizationChart" />
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Attendance Status Distribution -->
      <v-col cols="12" md="6">
        <v-card class="industrial-card chart-card" elevation="2">
          <v-card-title class="pa-4 bg-gradient">
            <v-icon start size="20">mdi-chart-bar-stacked</v-icon>
            Attendance Status Distribution
          </v-card-title>
          <v-divider></v-divider>
          <v-card-text class="pa-4" style="height: 300px">
            <AttendanceStatusChart ref="attendanceStatusChart" period="current-month" />
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Overtime Hours Trend -->
      <v-col cols="12" md="6">
        <v-card class="industrial-card chart-card" elevation="2">
          <v-card-title class="pa-4 bg-gradient">
            <v-icon start size="20">mdi-clock-outline</v-icon>
            Overtime Hours Trend
          </v-card-title>
          <v-divider></v-divider>
          <v-card-text class="pa-4" style="height: 300px">
            <OvertimeTrendChart ref="overtimeTrendChart" :days="30" />
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import { useToast } from 'vue-toastification';

// Employee Analytics Components
import EmployeeDistributionChart from '@/components/charts/EmployeeDistributionChart.vue';
import EmploymentStatusChart from '@/components/charts/EmploymentStatusChart.vue';
import EmployeeLocationChart from '@/components/charts/EmployeeLocationChart.vue';
import EmployeeGrowthChart from '@/components/charts/EmployeeGrowthChart.vue';

// Attendance Analytics Components
import AttendanceRateChart from '@/components/charts/AttendanceRateChart.vue';
import AttendanceStatusChart from '@/components/charts/AttendanceStatusChart.vue';
import OvertimeTrendChart from '@/components/charts/OvertimeTrendChart.vue';
import LeaveUtilizationChart from '@/components/charts/LeaveUtilizationChart.vue';

const toast = useToast();
const refreshing = ref(false);

// Chart refs
const employeeDistChart = ref(null);
const employmentStatusChart = ref(null);
const employeeLocationChart = ref(null);
const employeeGrowthChart = ref(null);
const attendanceRateChart = ref(null);
const attendanceStatusChart = ref(null);
const overtimeTrendChart = ref(null);
const leaveUtilizationChart = ref(null);

const refreshAllCharts = async () => {
  refreshing.value = true;
  
  try {
    const charts = [
      employeeDistChart,
      employmentStatusChart,
      employeeLocationChart,
      employeeGrowthChart,
      attendanceRateChart,
      attendanceStatusChart,
      overtimeTrendChart,
      leaveUtilizationChart
    ];

    await Promise.all(
      charts.map(chart => chart.value?.refresh?.())
    );

    toast.success('All charts refreshed successfully');
  } catch (error) {
    console.error('Error refreshing charts:', error);
    toast.error('Failed to refresh some charts');
  } finally {
    refreshing.value = false;
  }
};
</script>

<style scoped>
.analytics-dashboard {
  padding: 20px;
}

.construction-header {
  font-weight: 700;
  color: #1a237e;
  text-transform: uppercase;
  letter-spacing: 1px;
}

.steel-divider {
  height: 4px;
  background: linear-gradient(90deg, #2196f3 0%, #1976d2 100%);
  border-radius: 2px;
  box-shadow: 0 2px 4px rgba(33, 150, 243, 0.3);
}

.section-header {
  display: flex;
  align-items: center;
  padding: 12px 0;
  border-bottom: 2px solid #e0e0e0;
  margin-bottom: 8px;
}

.industrial-card {
  border: 1px solid #e0e0e0;
  transition: all 0.3s ease;
}

.industrial-card:hover {
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.1) !important;
  transform: translateY(-2px);
}

.chart-card {
  height: 100%;
}

.bg-gradient {
  background: linear-gradient(135deg, #f5f5f5 0%, #ffffff 100%);
  font-weight: 600;
}

.construction-btn {
  text-transform: uppercase;
  font-weight: 600;
  letter-spacing: 0.5px;
}
</style>
