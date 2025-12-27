<template>
  <div>
    <!-- Loading Indicator -->
    <v-row v-if="loading">
      <v-col cols="12" class="text-center py-8">
        <v-progress-circular
          indeterminate
          color="primary"
          size="64"
        ></v-progress-circular>
        <p class="mt-4">Loading dashboard...</p>
      </v-col>
    </v-row>

    <!-- Dashboard Content -->
    <div v-else>
      <!-- Quick Action Cards -->
      <v-row>
        <v-col cols="12" sm="6" md="3">
          <v-card class="hover-card" @click="scrollToSection('attendance')">
            <v-card-text class="text-center pa-6">
              <v-icon size="48" color="info" class="mb-2"
                >mdi-calendar-check</v-icon
              >
              <div class="text-h6">My Attendance</div>
              <div class="text-caption text-medium-emphasis">View records</div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-card class="hover-card" @click="scrollToSection('payslip')">
            <v-card-text class="text-center pa-6">
              <v-icon size="48" color="success" class="mb-2"
                >mdi-cash-multiple</v-icon
              >
              <div class="text-h6">Current Payslip</div>
              <div class="text-caption text-medium-emphasis">
                Latest payment
              </div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-card class="hover-card" @click="scrollToSection('history')">
            <v-card-text class="text-center pa-6">
              <v-icon size="48" color="warning" class="mb-2"
                >mdi-history</v-icon
              >
              <div class="text-h6">Payslip History</div>
              <div class="text-caption text-medium-emphasis">Past records</div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" sm="6" md="3">
          <v-card class="hover-card" @click="downloadCurrentPayslip">
            <v-card-text class="text-center pa-6">
              <v-icon size="48" color="primary" class="mb-2"
                >mdi-download</v-icon
              >
              <div class="text-h6">Download</div>
              <div class="text-caption text-medium-emphasis">
                Export payslip
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Employee Info Card -->
      <v-row v-if="employee">
        <v-col cols="12">
          <v-card>
            <v-card-text>
              <div class="d-flex align-center">
                <v-avatar color="primary" size="64" class="mr-4">
                  <v-icon size="32">mdi-account</v-icon>
                </v-avatar>
                <div>
                  <h2 class="text-h5">{{ employee.full_name }}</h2>
                  <p class="text-body-1 mb-0">
                    {{ employee.position }} - {{ employee.project?.name }}
                  </p>
                  <p class="text-caption text-medium-emphasis">
                    Employee No: {{ employee.employee_number }}
                  </p>
                </div>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Attendance Summary -->
      <v-row class="mt-4" id="attendance">
        <v-col cols="12">
          <v-card>
            <v-card-title class="d-flex align-center justify-space-between">
              <span>
                <v-icon class="mr-2">mdi-calendar-check</v-icon>
                Attendance Summary - {{ currentMonth }}
              </span>
            </v-card-title>
            <v-card-text>
              <v-row>
                <v-col cols="6" sm="3">
                  <div class="text-center">
                    <div class="text-h4 text-success">
                      {{ attendanceSummary.present }}
                    </div>
                    <div class="text-caption">Present</div>
                  </div>
                </v-col>
                <v-col cols="6" sm="3">
                  <div class="text-center">
                    <div class="text-h4 text-error">
                      {{ attendanceSummary.absent }}
                    </div>
                    <div class="text-caption">Absent</div>
                  </div>
                </v-col>
                <v-col cols="6" sm="3">
                  <div class="text-center">
                    <div class="text-h4 text-warning">
                      {{ attendanceSummary.late }}
                    </div>
                    <div class="text-caption">Late</div>
                  </div>
                </v-col>
                <v-col cols="6" sm="3">
                  <div class="text-center">
                    <div class="text-h4 text-primary">
                      {{ attendanceSummary.total_hours?.toFixed(2) || 0 }}
                    </div>
                    <div class="text-caption">Total Hours</div>
                  </div>
                </v-col>
              </v-row>

              <v-divider class="my-4"></v-divider>

              <v-data-table
                :headers="attendanceHeaders"
                :items="attendanceRecords"
                :items-per-page="10"
                class="elevation-0"
              >
                <template v-slot:item.date="{ item }">
                  {{ formatDate(item.date) }}
                </template>
                <template v-slot:item.status="{ item }">
                  <v-chip :color="getStatusColor(item.status)" size="small">
                    {{ item.status }}
                  </v-chip>
                </template>
                <template v-slot:item.time_in="{ item }">
                  {{ item.time_in || "N/A" }}
                </template>
                <template v-slot:item.time_out="{ item }">
                  {{ item.time_out || "N/A" }}
                </template>
                <template v-slot:item.total_hours_worked="{ item }">
                  {{ item.total_hours_worked?.toFixed(2) || 0 }}
                </template>
              </v-data-table>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Current Payslip -->
      <v-row class="mt-4" id="payslip">
        <v-col cols="12" md="6">
          <v-card>
            <v-card-title class="d-flex align-center justify-space-between">
              <span>
                <v-icon class="mr-2">mdi-cash-multiple</v-icon>
                Current Payslip
              </span>
            </v-card-title>
            <v-card-text>
              <div v-if="currentPayslip && currentPayslip.payroll">
                <v-list>
                  <v-list-item>
                    <v-list-item-title>Pay Period</v-list-item-title>
                    <v-list-item-subtitle>
                      {{ formatDate(currentPayslip.payroll.period_start) }} -
                      {{ formatDate(currentPayslip.payroll.period_end) }}
                    </v-list-item-subtitle>
                  </v-list-item>
                  <v-list-item>
                    <v-list-item-title>Gross Pay</v-list-item-title>
                    <v-list-item-subtitle>
                      ₱{{ formatNumber(currentPayslip.gross_pay) }}
                    </v-list-item-subtitle>
                  </v-list-item>
                  <v-list-item>
                    <v-list-item-title>Total Deductions</v-list-item-title>
                    <v-list-item-subtitle>
                      ₱{{ formatNumber(currentPayslip.total_deductions) }}
                    </v-list-item-subtitle>
                  </v-list-item>
                  <v-divider class="my-2"></v-divider>
                  <v-list-item>
                    <v-list-item-title class="text-h6"
                      >Net Pay</v-list-item-title
                    >
                    <v-list-item-subtitle class="text-h5 text-success">
                      ₱{{ formatNumber(currentPayslip.net_pay) }}
                    </v-list-item-subtitle>
                  </v-list-item>
                </v-list>

                <v-divider class="my-4"></v-divider>

                <div class="d-flex gap-2">
                  <v-btn
                    color="primary"
                    prepend-icon="mdi-file-pdf-box"
                    @click="downloadPayslip(currentPayslip.id, 'pdf')"
                    :loading="downloading"
                    block
                  >
                    Download PDF
                  </v-btn>
                  <v-btn
                    color="success"
                    prepend-icon="mdi-microsoft-excel"
                    @click="downloadPayslip(currentPayslip.id, 'excel')"
                    :loading="downloading"
                    block
                  >
                    Download Excel
                  </v-btn>
                </div>
              </div>
              <div v-else class="text-center py-8 text-medium-emphasis">
                <v-icon size="64" color="grey-lighten-1">mdi-cash-off</v-icon>
                <p class="mt-4">No payslip available</p>
              </div>
            </v-card-text>
          </v-card>
        </v-col>

        <!-- Payslip History -->
        <v-col cols="12" md="6" id="history">
          <v-card>
            <v-card-title class="d-flex align-center justify-space-between">
              <span>
                <v-icon class="mr-2">mdi-history</v-icon>
                Payslip History
              </span>
            </v-card-title>
            <v-card-text>
              <v-list v-if="payslipHistory.length > 0">
                <v-list-item
                  v-for="payslip in payslipHistory"
                  :key="payslip.id"
                  class="mb-2"
                  v-if="payslip.payroll"
                >
                  <v-list-item-title>
                    {{ formatDate(payslip.payroll.period_start) }} -
                    {{ formatDate(payslip.payroll.period_end) }}
                  </v-list-item-title>
                  <v-list-item-subtitle>
                    Net Pay: ₱{{ formatNumber(payslip.net_pay) }}
                  </v-list-item-subtitle>
                  <template v-slot:append>
                    <v-menu>
                      <template v-slot:activator="{ props }">
                        <v-btn
                          icon="mdi-download"
                          size="small"
                          v-bind="props"
                          variant="text"
                        ></v-btn>
                      </template>
                      <v-list>
                        <v-list-item
                          @click="downloadPayslip(payslip.id, 'pdf')"
                        >
                          <v-list-item-title>
                            <v-icon size="small" class="mr-2"
                              >mdi-file-pdf-box</v-icon
                            >
                            PDF
                          </v-list-item-title>
                        </v-list-item>
                        <v-list-item
                          @click="downloadPayslip(payslip.id, 'excel')"
                        >
                          <v-list-item-title>
                            <v-icon size="small" class="mr-2"
                              >mdi-microsoft-excel</v-icon
                            >
                            Excel
                          </v-list-item-title>
                        </v-list-item>
                      </v-list>
                    </v-menu>
                  </template>
                </v-list-item>
              </v-list>
              <div v-else class="text-center py-8 text-medium-emphasis">
                <v-icon size="64" color="grey-lighten-1">mdi-history</v-icon>
                <p class="mt-4">No payslip history</p>
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </div>
    <!-- End v-else -->
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import api from "@/services/api";
import { useToast } from "vue-toastification";

const toast = useToast();

const loading = ref(false);
const downloading = ref(false);
const employee = ref(null);
const attendanceRecords = ref([]);
const attendanceSummary = ref({
  total_days: 0,
  present: 0,
  absent: 0,
  late: 0,
  undertime: 0,
  total_hours: 0,
  overtime_hours: 0,
});
const currentPayslip = ref(null);
const payslipHistory = ref([]);

const currentMonth = computed(() => {
  const date = new Date();
  return date.toLocaleDateString("en-US", { month: "long", year: "numeric" });
});

const attendanceHeaders = [
  { title: "Date", key: "date" },
  { title: "Status", key: "status" },
  { title: "Time In", key: "time_in" },
  { title: "Time Out", key: "time_out" },
  { title: "Hours", key: "total_hours_worked" },
];

onMounted(() => {
  fetchDashboardData();
});

async function fetchDashboardData() {
  loading.value = true;
  try {
    const response = await api.get("/employee/dashboard");
    employee.value = response.data.employee;
    attendanceRecords.value = response.data.attendance;
    attendanceSummary.value = response.data.attendance_summary;
    currentPayslip.value = response.data.current_payslip;
    payslipHistory.value = response.data.payslip_history;
  } catch (error) {
    console.error("Error fetching dashboard data:", error);
    toast.error("Failed to load dashboard data");
  } finally {
    loading.value = false;
  }
}

async function downloadPayslip(payslipId, format) {
  downloading.value = true;
  try {
    const response = await api.get(`/payslips/${payslipId}/${format}`, {
      responseType: "blob",
    });

    // Create download link
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute(
      "download",
      `payslip_${format === "pdf" ? ".pdf" : ".xlsx"}`
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
  }).format(value);
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

function scrollToSection(sectionId) {
  const element = document.getElementById(sectionId);
  if (element) {
    element.scrollIntoView({ behavior: "smooth", block: "start" });
  }
}

function downloadCurrentPayslip() {
  if (currentPayslip.value) {
    downloadPayslip(currentPayslip.value.id, "pdf");
  } else {
    toast.warning("No current payslip available");
  }
}
</script>

<style scoped>
.gap-2 {
  gap: 8px;
}

.hover-card {
  cursor: pointer;
  transition: all 0.3s ease;
}

.hover-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
}
</style>
