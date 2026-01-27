<template>
  <div class="modern-dashboard">
    <!-- Compact Header with Actions -->
    <div class="dashboard-header-compact">
      <div class="header-left">
        <div class="welcome-badge">
          <v-icon size="16" class="welcome-icon">mdi-chart-box</v-icon>
          <span>Dashboard Overview</span>
        </div>
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

        <!-- Upcoming Events -->
        <UpcomingEvents class="mb-6" />
      </v-col>

      <!-- Right Column - 1/3 Width -->
      <v-col cols="12" lg="4">
        <!-- Quick Actions Redesigned -->
        <div class="quick-actions-section mb-6">
          <div class="section-header-compact">
            <div class="section-icon-badge">
              <v-icon size="16">mdi-lightning-bolt</v-icon>
            </div>
            <h3 class="section-title-compact">Quick Actions</h3>
          </div>
          <div class="quick-action-buttons">
            <button
              class="quick-action-btn"
              @click="showAddEmployeeDialog = true"
            >
              <div class="quick-action-icon">
                <v-icon>mdi-account-plus</v-icon>
              </div>
              <span>Add Employee</span>
            </button>
            <button
              class="quick-action-btn"
              @click="$router.push('/payroll/create')"
            >
              <div class="quick-action-icon">
                <v-icon>mdi-currency-usd</v-icon>
              </div>
              <span>Create Payroll</span>
            </button>
            <button
              class="quick-action-btn"
              @click="$router.push('/biometric-import')"
            >
              <div class="quick-action-icon">
                <v-icon>mdi-file-upload</v-icon>
              </div>
              <span>Import Attendance</span>
            </button>
          </div>
        </div>

        <!-- System Health Redesigned -->
        <div class="system-health-section mb-6">
          <div class="section-header-compact">
            <div class="section-icon-badge success">
              <v-icon size="16">mdi-pulse</v-icon>
            </div>
            <h3 class="section-title-compact">System Status</h3>
          </div>
          <div class="health-metrics">
            <div class="health-metric">
              <div class="metric-header">
                <span class="metric-label">Employee Data Completion</span>
                <span class="metric-value">{{ employeeDataCompletion }}%</span>
              </div>
              <div class="metric-progress">
                <div
                  class="metric-progress-bar"
                  :style="{ width: employeeDataCompletion + '%' }"
                ></div>
              </div>
            </div>

            <div class="health-metric">
              <div class="metric-info">
                <div class="metric-info-left">
                  <v-icon size="20" class="metric-icon"
                    >mdi-calendar-check</v-icon
                  >
                  <span class="metric-info-label">Monthly Attendance</span>
                </div>
                <div class="metric-badge">
                  {{ stats.monthlyAttendanceRate || 0 }}%
                </div>
              </div>
            </div>

            <div class="health-metric">
              <div class="metric-info">
                <div class="metric-info-left">
                  <v-icon size="20" class="metric-icon"
                    >mdi-clock-outline</v-icon
                  >
                  <span class="metric-info-label">Last Biometric Import</span>
                </div>
                <div class="metric-text">
                  {{ lastBiometricImport }}
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Calendar Widget -->
        <DashboardCalendar />
      </v-col>
    </v-row>

    <!-- All Dialogs from Original -->
    <AddEmployeeDialog
      v-model="showAddEmployeeDialog"
      :projects="projects"
      @save="saveEmployee"
    />

    <!-- Temporary Password Dialog -->
    <v-dialog v-model="showPasswordDialog" max-width="600px" persistent>
      <v-card>
        <v-card-title class="text-h5 py-4 bg-success">
          <v-icon start>mdi-shield-check</v-icon>
          Employee Account Created
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-6">
          <v-alert type="success" variant="tonal" class="mb-4">
            <div class="text-h6 mb-2">
              Employee {{ newEmployeeData?.employee_number }} -
              {{ newEmployeeData?.first_name }} {{ newEmployeeData?.last_name }}
            </div>
            <p>A login account has been created successfully!</p>
          </v-alert>

          <div class="mb-4">
            <div class="text-subtitle-1 font-weight-bold mb-2">
              Login Credentials:
            </div>
            <v-sheet color="grey-lighten-4" rounded class="pa-4">
              <div class="mb-3">
                <div class="text-caption text-grey-darken-2">Username</div>
                <div class="text-body-1 font-weight-bold">
                  {{
                    createdEmployeeUsername || newEmployeeData?.email || "N/A"
                  }}
                </div>
              </div>
              <div class="mb-3" v-if="newEmployeeData?.email">
                <div class="text-caption text-grey-darken-2">Email</div>
                <div class="text-body-1 font-weight-bold">
                  {{ newEmployeeData?.email }}
                </div>
              </div>
              <div class="mb-3">
                <div class="text-caption text-grey-darken-2">
                  Temporary Password
                </div>
                <div class="text-h6 font-weight-bold text-primary">
                  {{ temporaryPassword }}
                </div>
              </div>
              <div>
                <div class="text-caption text-grey-darken-2">Role</div>
                <div class="text-body-1 font-weight-bold text-capitalize">
                  {{ newEmployeeData?.role || "employee" }}
                </div>
              </div>
            </v-sheet>
          </div>

          <v-alert type="warning" variant="tonal" density="compact">
            <v-icon start>mdi-alert</v-icon>
            <strong>Important:</strong> The employee must change this password
            on their first login.
          </v-alert>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-btn
            variant="text"
            prepend-icon="mdi-content-copy"
            @click="copyCredentials"
          >
            Copy Credentials
          </v-btn>
          <v-spacer></v-spacer>
          <v-btn
            color="primary"
            variant="elevated"
            @click="showPasswordDialog = false"
          >
            Done
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Application Review Dialog -->
    <v-dialog
      v-model="showApplicationDialog"
      max-width="1000px"
      persistent
      scrollable
    >
      <v-card v-if="selectedApplication">
        <v-card-title class="text-h5 py-4 bg-warning">
          <v-icon start>mdi-account-check</v-icon>
          Review Employee Application
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-6" style="max-height: 600px">
          <v-row>
            <!-- Personal Information -->
            <v-col cols="12">
              <div class="text-h6 mb-2">Section 1: Personal Information</div>
              <v-divider class="mb-4"></v-divider>
            </v-col>

            <v-col cols="12" md="4">
              <v-text-field
                :model-value="selectedApplication.first_name"
                label="First Name"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="4">
              <v-text-field
                :model-value="selectedApplication.middle_name"
                label="Middle Name"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="4">
              <v-text-field
                :model-value="selectedApplication.last_name"
                label="Last Name"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                :model-value="selectedApplication.email"
                label="Email"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                :model-value="selectedApplication.mobile_number"
                label="Phone Number"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <!-- Employment Information -->
            <v-col cols="12">
              <div class="text-h6 mb-2 mt-4">
                Section 2: Employment Information
              </div>
              <v-divider class="mb-4"></v-divider>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                :model-value="selectedApplication.project?.name"
                label="Department"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                :model-value="selectedApplication.position"
                label="Position"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <!-- Hire Date (required for approval) -->
            <v-col cols="12" md="6" v-if="applicationAction === 'approve'">
              <v-text-field
                v-model="approvalHireDate"
                label="Hire Date (Required)"
                type="date"
                variant="outlined"
                density="comfortable"
                hint="Set the official hire date for this employee"
                persistent-hint
              ></v-text-field>
            </v-col>

            <!-- Rejection Reason -->
            <v-col cols="12" v-if="applicationAction === 'reject'">
              <v-textarea
                v-model="rejectionReason"
                label="Rejection Reason"
                rows="3"
                variant="outlined"
                density="comfortable"
                hint="Please provide a reason for rejection"
                persistent-hint
              ></v-textarea>
            </v-col>
          </v-row>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-btn
            color="error"
            variant="elevated"
            @click="applicationAction = 'reject'"
            :disabled="processing || applicationAction === 'reject'"
          >
            <v-icon start>mdi-close-circle</v-icon>
            Reject
          </v-btn>
          <v-spacer></v-spacer>
          <v-btn
            variant="text"
            @click="closeApplicationDialog"
            :disabled="processing"
          >
            Cancel
          </v-btn>
          <v-btn
            v-if="applicationAction === 'reject'"
            color="error"
            variant="elevated"
            @click="rejectApplication"
            :loading="processing"
          >
            <v-icon start>mdi-send</v-icon>
            Confirm Rejection
          </v-btn>
          <v-btn
            v-else
            color="success"
            variant="elevated"
            @click="approveApplication"
            :loading="processing"
          >
            <v-icon start>mdi-check-circle</v-icon>
            Approve Application
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Approval Success Dialog -->
    <v-dialog v-model="showApprovalSuccessDialog" max-width="600px" persistent>
      <v-card>
        <v-card-title class="text-h5 py-4 bg-success">
          <v-icon start>mdi-check-circle</v-icon>
          Application Approved
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-6">
          <v-alert type="success" variant="tonal" class="mb-4">
            <div class="text-h6 mb-2">
              {{ approvedEmployeeData?.employee_number }} -
              {{ approvedEmployeeData?.first_name }}
              {{ approvedEmployeeData?.last_name }}
            </div>
          </v-alert>

          <div class="mb-4">
            <div class="text-subtitle-1 font-weight-bold mb-2">
              Login Credentials Created:
            </div>
            <v-sheet color="grey-lighten-4" rounded class="pa-4">
              <div class="mb-3">
                <div class="text-caption">Username (Email)</div>
                <div class="text-body-1 font-weight-bold">
                  {{ approvedEmployeeData?.email }}
                </div>
              </div>
              <div class="mb-3">
                <div class="text-caption">Temporary Password</div>
                <div class="text-h6 font-weight-bold text-primary">
                  {{ approvedEmployeePassword }}
                </div>
              </div>
              <div>
                <div class="text-caption">Role</div>
                <div class="text-body-1 font-weight-bold text-capitalize">
                  Employee
                </div>
              </div>
            </v-sheet>
          </div>

          <v-alert type="warning" variant="tonal" density="compact">
            Employee must change password on first login
          </v-alert>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-btn
            variant="text"
            prepend-icon="mdi-content-copy"
            @click="copyApprovedCredentials"
          >
            Copy
          </v-btn>
          <v-spacer></v-spacer>
          <v-btn color="primary" @click="showApprovalSuccessDialog = false">
            Done
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, onUnmounted } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import api from "@/services/api";
import { useToast } from "vue-toastification";
import { onAttendanceUpdate } from "@/stores/attendance";
import AddEmployeeDialog from "@/components/AddEmployeeDialog.vue";
import DashboardCalendar from "@/components/DashboardCalendar.vue";
import UpcomingEvents from "@/components/UpcomingEvents.vue";
import EmployeeDistributionChart from "@/components/charts/EmployeeDistributionChart.vue";
import AttendanceStatusChart from "@/components/charts/AttendanceStatusChart.vue";
import TodayStaffInfoChart from "@/components/charts/TodayStaffInfoChart.vue";
import RecentActivityWidget from "@/components/audit/RecentActivityWidget.vue";

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
  employeesCompleteData: 0,
  monthlyAttendanceRate: 0,
  lastBiometricImportDate: null,
});

const refreshing = ref(false);

// Application management
const pendingApplications = ref([]);
const showApplicationDialog = ref(false);
const selectedApplication = ref(null);
const applicationAction = ref("approve");
const rejectionReason = ref("");
const approvalHireDate = ref("");
const processing = ref(false);
const showApprovalSuccessDialog = ref(false);
const approvedEmployeeData = ref(null);
const approvedEmployeePassword = ref("");

// Employee form data
const showAddEmployeeDialog = ref(false);
const showPasswordDialog = ref(false);
const temporaryPassword = ref("");
const newEmployeeData = ref(null);
const createdEmployeeUsername = ref("");
const projects = ref([]);

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

// Circular progress for community growth
const circumference = 2 * Math.PI * 50;

const employeeGrowthPercentage = computed(() => {
  if (stats.value.totalEmployees === 0) return 0;
  return Math.round(
    (stats.value.activeEmployees / stats.value.totalEmployees) * 100,
  );
});

const progressOffset = computed(() => {
  const progress = employeeGrowthPercentage.value;
  return circumference - (progress / 100) * circumference;
});

const totalPendingActions = computed(() => {
  return (
    stats.value.pendingApplications +
    stats.value.pendingLeaves +
    stats.value.pendingAttendanceCorrections +
    stats.value.draftPayrolls +
    (stats.value.pendingResignations || 0)
  );
});

const employeeDataCompletion = computed(() => {
  if (stats.value.totalEmployees === 0) return 0;
  return Math.round(
    (stats.value.employeesCompleteData / stats.value.totalEmployees) * 100,
  );
});

const lastBiometricImport = computed(() => {
  if (!stats.value.lastBiometricImportDate) return "Never";
  const date = new Date(stats.value.lastBiometricImportDate);
  const now = new Date();
  const diffInDays = Math.floor((now - date) / (1000 * 60 * 60 * 24));

  if (diffInDays === 0) return "Today";
  if (diffInDays === 1) return "Yesterday";
  if (diffInDays < 7) return `${diffInDays} days ago`;
  return date.toLocaleDateString("en-US", { month: "short", day: "numeric" });
});

let unsubscribeAttendance = null;

onMounted(async () => {
  await fetchDashboardData();
  await fetchProjects();
  await fetchPendingApplications();

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
  try {
    const response = await api.get("/dashboard");
    stats.value = response.data.stats;
  } catch (error) {
    console.error("Error fetching dashboard data:", error);
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
    await Promise.all([fetchDashboardData(), fetchPendingApplications()]);
    toast.success("Dashboard refreshed successfully!");
  } catch (error) {
    console.error("Error refreshing dashboard:", error);
    toast.error("Failed to refresh dashboard");
  } finally {
    refreshing.value = false;
  }
}

async function fetchProjects() {
  try {
    const response = await api.get("/projects");
    projects.value = response.data.data || response.data;
  } catch (error) {
    console.error("Error fetching projects:", error);
    toast.error("Failed to load projects");
  }
}

async function saveEmployee(payload) {
  const { data: employeeData, setSaving } = payload;

  try {
    const response = await api.post("/employees", employeeData);
    temporaryPassword.value = response.data.temporary_password;
    newEmployeeData.value = response.data.employee;
    newEmployeeData.value.role = response.data.role;
    createdEmployeeUsername.value = response.data.username;

    toast.success("Employee created successfully!");
    showAddEmployeeDialog.value = false;
    showPasswordDialog.value = true;

    await fetchDashboardData();
  } catch (error) {
    console.error("Error creating employee:", error);
    if (error.response?.data?.errors) {
      const errors = error.response.data.errors;
      Object.keys(errors).forEach((field) => {
        toast.error(`${field}: ${errors[field][0]}`);
      });
    } else {
      const message =
        error.response?.data?.message ||
        error.response?.data?.error ||
        "Failed to create employee";
      toast.error(message);
    }
  } finally {
    setSaving(false);
  }
}

function copyCredentials() {
  const emailInfo = newEmployeeData.value?.email
    ? `\nEmail: ${newEmployeeData.value.email}`
    : "";
  const credentials = `Employee Login Credentials
Employee Number: ${newEmployeeData.value?.employee_number}
Name: ${newEmployeeData.value?.first_name} ${newEmployeeData.value?.last_name}
Username: ${
    createdEmployeeUsername.value || newEmployeeData.value?.email
  }${emailInfo}
Temporary Password: ${temporaryPassword.value}
Role: ${newEmployeeData.value?.role}

⚠️ Employee must change password on first login.`;

  navigator.clipboard.writeText(credentials);
  toast.success("Credentials copied to clipboard!");
}

function formatNumber(value) {
  return new Intl.NumberFormat("en-PH", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value || 0);
}

function formatDateShort(dateString) {
  if (!dateString) return "";
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", { month: "short", day: "numeric" });
}

// Application Management Functions
async function fetchPendingApplications() {
  try {
    const response = await api.get("/employee-applications", {
      params: { status: "pending" },
    });
    pendingApplications.value = response.data;
  } catch (error) {
    console.error("Error fetching applications:", error);
    toast.error("Failed to load pending applications");
  }
}

function viewApplication(application) {
  selectedApplication.value = application;
  applicationAction.value = "approve";
  rejectionReason.value = "";
  approvalHireDate.value =
    application.date_hired || new Date().toISOString().split("T")[0];
  showApplicationDialog.value = true;
}

async function approveApplication() {
  if (!approvalHireDate.value) {
    toast.warning("Please provide a hire date");
    return;
  }
  processing.value = true;
  try {
    const response = await api.post(
      `/employee-applications/${selectedApplication.value.id}/approve`,
      { date_hired: approvalHireDate.value },
    );
    approvedEmployeeData.value = response.data.employee;
    approvedEmployeePassword.value = response.data.temporary_password;
    toast.success("Application approved! Employee account created.");
    closeApplicationDialog();
    showApprovalSuccessDialog.value = true;
    await fetchPendingApplications();
    await fetchDashboardData();
  } catch (error) {
    console.error("Error approving application:", error);
    toast.error(
      error.response?.data?.message || "Failed to approve application",
    );
  } finally {
    processing.value = false;
  }
}

async function rejectApplication() {
  if (!rejectionReason.value.trim()) {
    toast.warning("Please provide a rejection reason");
    return;
  }
  processing.value = true;
  try {
    await api.post(
      `/employee-applications/${selectedApplication.value.id}/reject`,
      { rejection_reason: rejectionReason.value },
    );
    toast.success("Application rejected");
    closeApplicationDialog();
    await fetchPendingApplications();
  } catch (error) {
    console.error("Error rejecting application:", error);
    toast.error("Failed to reject application");
  } finally {
    processing.value = false;
  }
}

function closeApplicationDialog() {
  showApplicationDialog.value = false;
  selectedApplication.value = null;
  applicationAction.value = "approve";
  rejectionReason.value = "";
  approvalHireDate.value = "";
  processing.value = false;
}

function copyApprovedCredentials() {
  const credentials = `Employee Login Credentials
Employee Number: ${approvedEmployeeData.value.employee_number}
Name: ${approvedEmployeeData.value.first_name} ${approvedEmployeeData.value.last_name}
Username: ${approvedEmployeeData.value.email}
Temporary Password: ${approvedEmployeePassword.value}
Role: Employee

⚠️ Employee must change password on first login.`;

  navigator.clipboard
    .writeText(credentials)
    .then(() => {
      toast.success("Credentials copied to clipboard!");
    })
    .catch(() => {
      toast.error("Failed to copy credentials");
    });
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
  margin-bottom: 20px;
}

.section-title-wrapper {
  display: flex;
  align-items: center;
  gap: 10px;
}

.section-icon-badge {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;

  &.success {
    background: linear-gradient(135deg, #f7b980 0%, #ed985f 100%);
  }

  .v-icon {
    color: #ffffff !important;
  }
}

.section-title {
  font-size: 18px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
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
