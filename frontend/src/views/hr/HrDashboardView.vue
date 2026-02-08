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
              <v-icon size="16" class="welcome-icon">mdi-account-tie</v-icon>
              <span>Human Resources Portal</span>
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
                  <span class="table-hours">{{ formatHoursDisplay(calculateHours(item)) }}</span>
                </template>
              </v-data-table>
            </div>
          </div>

          <!-- Payslip History Section -->
          <div class="payslip-history-section">
            <div class="section-header-compact">
              <div class="section-icon-badge">
                <v-icon size="16">mdi-history</v-icon>
              </div>
              <h3 class="section-title-compact">Payslip History</h3>
            </div>

            <div class="content-card">
              <div v-if="payslipHistory.length" class="payslip-history-list">
                <div
                  v-for="payslip in payslipHistory"
                  :key="payslip.id"
                  class="payslip-history-item"
                >
                  <div class="payslip-history-info">
                    <div class="payslip-history-period">
                      <v-icon size="16" class="mr-1">mdi-calendar</v-icon>
                      {{ formatDate(payslip.payroll.period_start) }} -
                      {{ formatDate(payslip.payroll.period_end) }}
                    </div>
                    <div class="payslip-history-amount">
                      ₱{{ formatNumber(payslip.net_pay) }}
                    </div>
                  </div>
                  <div class="payslip-history-actions">
                    <button
                      class="payslip-action-btn-small"
                      @click="downloadPayslip(payslip.id, 'pdf')"
                    >
                      <v-icon size="16">mdi-file-pdf-box</v-icon>
                      <span>PDF</span>
                    </button>
                    <button
                      class="payslip-action-btn-small"
                      @click="downloadPayslip(payslip.id, 'excel')"
                    >
                      <v-icon size="16">mdi-file-excel</v-icon>
                      <span>Excel</span>
                    </button>
                  </div>
                </div>
              </div>
              <div v-else class="empty-state-small">
                <v-icon size="48" color="rgba(0, 31, 61, 0.2)"
                  >mdi-history</v-icon
                >
                <div class="empty-state-text">No payslip history</div>
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

          <!-- Quick Links - HR specific -->
          <div class="quick-actions-section">
            <div class="section-header-compact">
              <div class="section-icon-badge">
                <v-icon size="16">mdi-lightning-bolt</v-icon>
              </div>
              <h3 class="section-title-compact">Quick Actions</h3>
            </div>
            <div class="quick-action-buttons">
              <button
                class="quick-action-btn highlight"
                @click="showUploadResumeDialog = true"
              >
                <div class="quick-action-icon highlight">
                  <v-icon>mdi-file-upload</v-icon>
                </div>
                <span>Upload Resume</span>
              </button>
              <button
                class="quick-action-btn"
                @click="$router.push('/leave-approval')"
              >
                <div class="quick-action-icon">
                  <v-icon>mdi-calendar-check</v-icon>
                </div>
                <span>Leave Requests</span>
              </button>
              <button class="quick-action-btn" @click="$router.push('/resignations')">
                <div class="quick-action-icon">
                  <v-icon>mdi-briefcase-remove-outline</v-icon>
                </div>
                <span>Resignations</span>
              </button>
            </div>
          </div>
        </v-col>
      </v-row>

      <!-- My Submitted Resumes Section -->
      <v-row class="mt-4">
        <v-col cols="12">
          <div class="section-header-compact">
            <div class="section-icon-badge">
              <v-icon size="16">mdi-file-document-multiple</v-icon>
            </div>
            <h3 class="section-title-compact">My Submitted Resumes</h3>
          </div>

          <div class="content-card">
            <v-data-table
              :headers="resumeHeaders"
              :items="myResumes"
              :loading="loadingResumes"
              class="modern-table"
            >
              <template v-slot:item.full_name="{ item }">
                <span class="table-text">{{ item.full_name || `${item.first_name} ${item.last_name}` }}</span>
              </template>
              <template v-slot:item.email="{ item }">
                <span class="table-text">{{ item.email }}</span>
              </template>
              <template v-slot:item.position_applied="{ item }">
                <span class="table-text">{{ item.position_applied }}</span>
              </template>
              <template v-slot:item.status="{ item }">
                <v-chip
                  :color="getResumeStatusColor(item.status)"
                  size="small"
                  variant="flat"
                >
                  {{ item.status }}
                </v-chip>
              </template>
              <template v-slot:item.created_at="{ item }">
                <span class="table-date">{{ formatDate(item.created_at) }}</span>
              </template>
              <template v-slot:item.actions="{ item }">
                <v-btn
                  icon="mdi-download"
                  size="small"
                  variant="text"
                  @click="downloadResume(item.id)"
                  title="Download Resume"
                ></v-btn>
                <v-btn
                  v-if="item.status === 'pending'"
                  icon="mdi-delete"
                  size="small"
                  variant="text"
                  color="error"
                  @click="deleteResume(item.id)"
                  title="Delete"
                ></v-btn>
              </template>
            </v-data-table>
          </div>
        </v-col>
      </v-row>
    </div>

    <!-- Upload Resume Dialog -->
    <v-dialog v-model="showUploadResumeDialog" max-width="600px" persistent>
      <v-card>
        <v-card-title class="text-h5 py-4 bg-primary">
          <v-icon start>mdi-file-upload</v-icon>
          Upload Applicant Resume
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-6">
          <v-form ref="resumeForm">
            <v-row>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="resumeData.first_name"
                  label="First Name"
                  variant="outlined"
                  density="comfortable"
                  :rules="[rules.required]"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="resumeData.last_name"
                  label="Last Name"
                  variant="outlined"
                  density="comfortable"
                  :rules="[rules.required]"
                ></v-text-field>
              </v-col>

              <v-col cols="12">
                <v-text-field
                  v-model="resumeData.email"
                  label="Email"
                  type="email"
                  variant="outlined"
                  density="comfortable"
                  :rules="[rules.required, rules.email]"
                ></v-text-field>
              </v-col>

              <v-col cols="12">
                <v-text-field
                  v-model="resumeData.phone"
                  label="Phone Number"
                  variant="outlined"
                  density="comfortable"
                  :rules="[rules.required]"
                ></v-text-field>
              </v-col>

              <v-col cols="12">
                <v-text-field
                  v-model="resumeData.position_applied"
                  label="Position Applied For"
                  variant="outlined"
                  density="comfortable"
                  :rules="[rules.required]"
                ></v-text-field>
              </v-col>

              <v-col cols="12">
                <v-file-input
                  v-model="resumeData.resume_file"
                  label="Resume File"
                  accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                  variant="outlined"
                  density="comfortable"
                  prepend-icon="mdi-paperclip"
                  hint="PDF, DOC, DOCX, JPG, or PNG (Max 10MB)"
                  persistent-hint
                  show-size
                  :rules="[rules.required]"
                ></v-file-input>
              </v-col>

              <v-col cols="12">
                <v-textarea
                  v-model="resumeData.notes"
                  label="Additional Notes (Optional)"
                  rows="3"
                  variant="outlined"
                  density="comfortable"
                ></v-textarea>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="closeResumeDialog" :disabled="uploading">
            Cancel
          </v-btn>
          <v-btn
            color="primary"
            variant="elevated"
            @click="submitResume"
            :loading="uploading"
          >
            Upload Resume
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useToast } from "vue-toastification";
import api from "@/services/api";
import { resumeService } from "@/services/resumeService";

const authStore = useAuthStore();
const toast = useToast();

const refreshing = ref(false);
const downloading = ref(false);
const uploading = ref(false);
const loadingResumes = ref(false);
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
const myResumes = ref([]);
const showUploadResumeDialog = ref(false);

const resumeData = ref({
  first_name: "",
  last_name: "",
  email: "",
  phone: "",
  position_applied: "",
  resume_file: null,
  notes: "",
});

const resumeForm = ref(null);

const rules = {
  required: (value) => !!value || "This field is required",
  email: (value) => /.+@.+\..+/.test(value) || "Email must be valid",
};

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
    "HR Staff"
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

const resumeHeaders = [
  { title: "Applicant Name", key: "full_name", sortable: true },
  { title: "Email", key: "email", sortable: true },
  { title: "Position", key: "position_applied", sortable: true },
  { title: "Status", key: "status", sortable: true },
  { title: "Submitted", key: "created_at", sortable: true },
  { title: "Actions", key: "actions", sortable: false },
];

onMounted(async () => {
  await fetchDashboardData();
  await fetchMyResumes();
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
    return record.regular_hours;
  }

  if (record.time_in && record.time_out) {
    try {
      const timeIn = new Date(`2000-01-01 ${record.time_in}`);
      const timeOut = new Date(`2000-01-01 ${record.time_out}`);
      const hours = (timeOut - timeIn) / (1000 * 60 * 60);
      return hours > 0 ? hours : 0;
    } catch (error) {
      console.error("Error calculating hours:", error);
      return 0;
    }
  }

  return 0;
}

function formatHoursDisplay(hours) {
  if (!hours || hours <= 0) return "0h 0m";
  
  const totalMinutes = Math.round(hours * 60);
  const hrs = Math.floor(totalMinutes / 60);
  const mins = totalMinutes % 60;
  
  if (hrs === 0) {
    return `${mins}m`;
  } else if (mins === 0) {
    return `${hrs}h`;
  } else {
    return `${hrs}h ${mins}m`;
  }
}

async function fetchMyResumes() {
  loadingResumes.value = true;
  try {
    const response = await resumeService.getMyResumes();
    myResumes.value = response.data || response;
  } catch (error) {
    console.error("Error loading resumes:", error);
    toast.error("Failed to load resumes");
  } finally {
    loadingResumes.value = false;
  }
}

async function submitResume() {
  const { valid } = await resumeForm.value.validate();
  if (!valid) {
    toast.warning("Please fill in all required fields");
    return;
  }

  if (!resumeData.value.resume_file) {
    toast.warning("Please select a resume file");
    return;
  }

  uploading.value = true;
  try {
    const formData = new FormData();
    formData.append("first_name", resumeData.value.first_name);
    formData.append("last_name", resumeData.value.last_name);
    formData.append("email", resumeData.value.email);
    formData.append("phone", resumeData.value.phone);
    formData.append("position_applied", resumeData.value.position_applied);
    formData.append("notes", resumeData.value.notes || "");
    
    // Handle file input - v-file-input returns array
    const file = Array.isArray(resumeData.value.resume_file)
      ? resumeData.value.resume_file[0]
      : resumeData.value.resume_file;
    // Backend expects 'resume' not 'resume_file'
    formData.append("resume", file);

    await resumeService.uploadResume(formData);
    toast.success("Resume uploaded successfully! Waiting for admin approval.");
    closeResumeDialog();
    await fetchMyResumes();
  } catch (error) {
    console.error("Error uploading resume:", error);
    toast.error(error.response?.data?.message || "Failed to upload resume");
  } finally {
    uploading.value = false;
  }
}

async function downloadResume(resumeId) {
  try {
    const response = await resumeService.downloadResume(resumeId);
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute("download", `resume_${resumeId}.pdf`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    toast.success("Resume downloaded successfully");
  } catch (error) {
    console.error("Error downloading resume:", error);
    toast.error("Failed to download resume");
  }
}

async function deleteResume(resumeId) {
  if (!confirm("Are you sure you want to delete this resume?")) {
    return;
  }

  try {
    await resumeService.deleteResume(resumeId);
    toast.success("Resume deleted successfully");
    await fetchMyResumes();
  } catch (error) {
    console.error("Error deleting resume:", error);
    toast.error("Failed to delete resume");
  }
}

function closeResumeDialog() {
  showUploadResumeDialog.value = false;
  resumeData.value = {
    first_name: "",
    last_name: "",
    email: "",
    phone: "",
    position_applied: "",
    resume_file: null,
    notes: "",
  };
  resumeForm.value?.reset();
}

function getResumeStatusColor(status) {
  const colors = {
    pending: "warning",
    approved: "success",
    rejected: "error",
  };
  return colors[status] || "grey";
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

.welcome-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(10px);
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  color: #ffffff;
  text-transform: uppercase;
  letter-spacing: 0.5px;

  .welcome-icon {
    color: #ED985F;
  }
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
  color: white !important;
  text-transform: none;
  letter-spacing: 0;
}

// Modern Statistics Cards
.stats-row {
  margin-bottom: 32px;
}

.stat-card-new {
  background: white;
  border-radius: 16px;
  padding: 20px;
  box-shadow: 0 2px 8px rgba(0, 31, 61, 0.08);
  transition: all 0.3s ease;
  cursor: default;
  position: relative;
  overflow: hidden;

  &::before {
    content: "";
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    background: radial-gradient(
      circle,
      rgba(237, 152, 95, 0.1) 0%,
      transparent 70%
    );
    border-radius: 50%;
    transform: translate(30%, -30%);
  }

  &:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 16px rgba(0, 31, 61, 0.12);
  }

  &.stat-card-pending {
    cursor: pointer;
  }
}

.stat-icon-wrapper {
  margin-bottom: 16px;
}

.stat-icon-circle {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  position: relative;
  z-index: 1;
}

.stat-card-employees .stat-icon-circle {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stat-card-attendance .stat-icon-circle {
  background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
}

.stat-card-payroll .stat-icon-circle {
  background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
}

.stat-card-pending .stat-icon-circle {
  background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
}

.stat-icon-pulse {
  animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
  0%,
  100% {
    box-shadow: 0 0 0 0 rgba(67, 233, 123, 0.4);
  }
  50% {
    box-shadow: 0 0 0 8px rgba(67, 233, 123, 0);
  }
}

.stat-content {
  position: relative;
  z-index: 1;
}

.stat-label {
  font-size: 13px;
  color: rgba(0, 31, 61, 0.6);
  font-weight: 500;
  margin-bottom: 8px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.stat-value {
  font-size: 32px;
  font-weight: 700;
  color: #001f3d;
  line-height: 1;
  margin-bottom: 6px;
}

.stat-meta {
  font-size: 12px;
  color: rgba(0, 31, 61, 0.5);
  font-weight: 500;
}

.stat-arrow {
  position: absolute;
  bottom: 20px;
  right: 20px;
  color: rgba(0, 31, 61, 0.2);
  transition: all 0.3s ease;
}

.stat-card-new:hover .stat-arrow {
  color: rgba(0, 31, 61, 0.4);
  transform: translateX(4px);
}

// Section Headers
.section-header-compact {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
}

.section-icon-badge {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  flex-shrink: 0;

  &.success {
    background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
  }
}

.section-title-compact {
  font-size: 18px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
  letter-spacing: -0.3px;
}

// Content Cards
.content-card {
  background: white;
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 2px 8px rgba(0, 31, 61, 0.08);
}

// Tables
.modern-table {
  :deep(.v-data-table__wrapper) {
    border-radius: 12px;
    overflow: hidden;
  }

  :deep(.v-data-table-header) {
    background: #f8f9fa;

    th {
      font-weight: 600 !important;
      color: #001f3d !important;
      text-transform: uppercase;
      font-size: 11px !important;
      letter-spacing: 0.5px;
    }
  }

  :deep(tbody tr) {
    transition: background 0.2s ease;

    &:hover {
      background: #f8f9fa;
    }
  }
}

.table-date {
  font-weight: 500;
  color: #001f3d;
}

.table-time {
  font-family: "Courier New", monospace;
  color: rgba(0, 31, 61, 0.7);
  font-size: 13px;
}

.table-hours {
  font-weight: 600;
  color: #667eea;
}

// Payslip Details
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
  font-size: 13px;
  color: rgba(0, 31, 61, 0.6);
  font-weight: 500;
}

.detail-value {
  font-size: 14px;
  color: #001f3d;
  font-weight: 600;

  &.amount-positive {
    color: #43e97b;
  }

  &.amount-negative {
    color: #f5576c;
  }
}

.payslip-detail-divider {
  height: 1px;
  background: rgba(0, 31, 61, 0.1);
}

.payslip-detail-divider-thick {
  height: 2px;
  background: rgba(0, 31, 61, 0.15);
}

.payslip-detail-item-main {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
  border-radius: 12px;
}

.detail-label-main {
  font-size: 14px;
  color: rgba(0, 31, 61, 0.7);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.detail-value-main {
  font-size: 24px;
  color: #001f3d;
  font-weight: 700;
}

.payslip-actions {
  display: flex;
  gap: 12px;
  margin-top: 8px;
}

.payslip-action-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;

  &.primary {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;

    &:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    }
  }

  &.secondary {
    background: #f8f9fa;
    color: #001f3d;

    &:hover {
      background: #e9ecef;
      transform: translateY(-2px);
    }
  }
}

// Payslip History
.payslip-history-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.payslip-history-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  background: #f8f9fa;
  border-radius: 12px;
  transition: all 0.3s ease;

  &:hover {
    background: #e9ecef;
    transform: translateX(4px);
  }
}

.payslip-history-info {
  flex: 1;
}

.payslip-history-period {
  display: flex;
  align-items: center;
  font-size: 13px;
  color: rgba(0, 31, 61, 0.7);
  margin-bottom: 4px;
}

.payslip-history-amount {
  font-size: 18px;
  font-weight: 700;
  color: #001f3d;
}

.payslip-history-actions {
  display: flex;
  gap: 8px;
}

.payslip-action-btn-small {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  background: white;
  border: 1px solid rgba(0, 31, 61, 0.1);
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  color: #001f3d;
  cursor: pointer;
  transition: all 0.3s ease;

  &:hover {
    background: #001f3d;
    color: white;
    border-color: #001f3d;
  }
}

// Quick Actions
.quick-actions-section {
  margin-bottom: 24px;
}

.quick-action-buttons {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.quick-action-btn {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  background: white;
  border: none;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 31, 61, 0.08);
  cursor: pointer;
  transition: all 0.3s ease;
  text-align: left;
  font-size: 14px;
  font-weight: 600;
  color: #001f3d;

  &:hover {
    transform: translateX(4px);
    box-shadow: 0 4px 12px rgba(0, 31, 61, 0.12);
  }

  &.highlight {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;

    .quick-action-icon {
      background: rgba(255, 255, 255, 0.2);
      color: white;
    }
  }
}

.quick-action-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: #f8f9fa;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #667eea;
  flex-shrink: 0;
}

// Empty State
.empty-state-small {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
  gap: 12px;
}

.empty-state-text {
  font-size: 14px;
  color: rgba(0, 31, 61, 0.5);
  font-weight: 500;
}
</style>

