<template>
  <div class="modern-dashboard">
    <!-- Modern Header -->
    <div class="dashboard-header mb-6">
      <div>
        <h1 class="text-h4 font-weight-bold mb-1">Dashboard</h1>
        <p class="text-subtitle-1 text-medium-emphasis">
          Welcome back! Here's what's happening today
        </p>
      </div>
      <div class="d-flex align-center gap-3">
        <!-- Date Range Selector -->
        <v-chip
          prepend-icon="mdi-calendar-range"
          variant="outlined"
          class="px-4"
        >
          {{ currentDateRange }}
        </v-chip>
        <v-btn
          color="primary"
          prepend-icon="mdi-refresh"
          variant="flat"
          @click="refreshData"
          :loading="refreshing"
          elevation="0"
        >
          Refresh
        </v-btn>
      </div>
    </div>

    <!-- Statistics Cards Row -->
    <v-row class="mb-6">
      <v-col cols="12" sm="6" md="3">
        <v-card class="modern-card stat-card" elevation="0">
          <v-card-text class="pa-5">
            <div class="d-flex justify-space-between align-center mb-3">
              <span class="text-subtitle-2 text-medium-emphasis">Active Employees</span>
              <v-icon size="20" color="primary">mdi-account-group</v-icon>
            </div>
            <div class="text-h4 font-weight-bold mb-2">
              {{ stats.activeEmployees }}
            </div>
            <div class="d-flex align-center">
              <span class="text-caption text-medium-emphasis">of {{ stats.totalEmployees }} total</span>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card
          class="modern-card stat-card"
          elevation="0"
          style="cursor: pointer"
          @click="goToAttendanceToday"
        >
          <v-card-text class="pa-5">
            <div class="d-flex justify-space-between align-center mb-3">
              <span class="text-subtitle-2 text-medium-emphasis">Present Today</span>
              <v-icon size="20" color="info">mdi-clock-check</v-icon>
            </div>
            <div class="text-h4 font-weight-bold mb-2">
              {{ stats.presentToday }}
            </div>
            <div class="d-flex align-center">
              <span class="text-caption text-medium-emphasis">Click to view details</span>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card class="modern-card stat-card" elevation="0">
          <v-card-text class="pa-5">
            <div class="d-flex justify-space-between align-center mb-3">
              <span class="text-subtitle-2 text-medium-emphasis">Period Payroll</span>
              <v-icon size="20" color="success">mdi-currency-usd</v-icon>
            </div>
            <div class="text-h4 font-weight-bold mb-2">
              ₱{{ formatNumber(stats.periodPayroll) }}
            </div>
            <div class="d-flex align-center">
              <span class="text-caption text-medium-emphasis">Current month</span>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card 
          class="modern-card stat-card" 
          elevation="0"
          style="cursor: pointer"
          @click="$router.push('/resume-review')"
        >
          <v-card-text class="pa-5">
            <div class="d-flex justify-space-between align-center mb-3">
              <span class="text-subtitle-2 text-medium-emphasis">Pending Actions</span>
              <v-icon size="20" color="warning">mdi-alert-circle</v-icon>
            </div>
            <div class="text-h4 font-weight-bold mb-2">
              {{ totalPendingActions }}
            </div>
            <div class="d-flex align-center">
              <span class="text-caption text-medium-emphasis">Requires attention</span>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Main Content Section -->
    <v-row class="mb-6">
      <!-- Left Column - Pending Actions & Charts -->
      <v-col cols="12" lg="8">
        <!-- Pending Actions -->
        <v-card class="modern-card mb-6" elevation="0">
          <v-card-title class="pa-5">
            <v-icon color="warning" size="small" class="mr-2">mdi-bell-alert</v-icon>
            <div class="text-subtitle-1 font-weight-bold">Items Requiring Attention</div>
          </v-card-title>
          <v-divider></v-divider>
          <v-card-text class="pa-0">
            <v-list class="py-0">
              <!-- Pending Applications -->
              <v-list-item
                v-if="stats.pendingApplications > 0"
                @click="$router.push('/resume-review')"
                class="px-5 py-4"
              >
                <template v-slot:prepend>
                  <v-avatar color="warning" size="40">
                    <v-icon>mdi-account-clock</v-icon>
                  </v-avatar>
                </template>
                <v-list-item-title class="font-weight-medium">
                  Job Applications
                </v-list-item-title>
                <v-list-item-subtitle>
                  {{ stats.pendingApplications }} applications awaiting review
                </v-list-item-subtitle>
                <template v-slot:append>
                  <v-chip color="warning" size="small" variant="flat">
                    {{ stats.pendingApplications }}
                  </v-chip>
                </template>
              </v-list-item>
              <v-divider v-if="stats.pendingApplications > 0 && (stats.pendingLeaves > 0 || stats.pendingAttendanceCorrections > 0 || stats.draftPayrolls > 0)"></v-divider>

              <!-- Pending Leaves -->
              <v-list-item
                v-if="stats.pendingLeaves > 0"
                @click="$router.push('/hr/leave-approval')"
                class="px-5 py-4"
              >
                <template v-slot:prepend>
                  <v-avatar color="info" size="40">
                    <v-icon>mdi-calendar-clock</v-icon>
                  </v-avatar>
                </template>
                <v-list-item-title class="font-weight-medium">
                  Leave Requests
                </v-list-item-title>
                <v-list-item-subtitle>
                  {{ stats.pendingLeaves }} leave requests need approval
                </v-list-item-subtitle>
                <template v-slot:append>
                  <v-chip color="info" size="small" variant="flat">
                    {{ stats.pendingLeaves }}
                  </v-chip>
                </template>
              </v-list-item>
              <v-divider v-if="stats.pendingLeaves > 0 && (stats.pendingAttendanceCorrections > 0 || stats.draftPayrolls > 0)"></v-divider>

              <!-- Pending Attendance Corrections -->
              <v-list-item
                v-if="stats.pendingAttendanceCorrections > 0"
                @click="$router.push({ path: '/attendance', query: { tab: 'approvals' } })"
                class="px-5 py-4"
              >
                <template v-slot:prepend>
                  <v-avatar color="primary" size="40">
                    <v-icon>mdi-clock-alert</v-icon>
                  </v-avatar>
                </template>
                <v-list-item-title class="font-weight-medium">
                  Attendance Corrections
                </v-list-item-title>
                <v-list-item-subtitle>
                  {{ stats.pendingAttendanceCorrections }} corrections pending
                </v-list-item-subtitle>
                <template v-slot:append>
                  <v-chip color="primary" size="small" variant="flat">
                    {{ stats.pendingAttendanceCorrections }}
                  </v-chip>
                </template>
              </v-list-item>
              <v-divider v-if="stats.pendingAttendanceCorrections > 0 && stats.draftPayrolls > 0"></v-divider>

              <!-- Draft Payrolls -->
              <v-list-item
                v-if="stats.draftPayrolls > 0"
                @click="$router.push('/payroll')"
                class="px-5 py-4"
              >
                <template v-slot:prepend>
                  <v-avatar color="success" size="40">
                    <v-icon>mdi-file-document-edit</v-icon>
                  </v-avatar>
                </template>
                <v-list-item-title class="font-weight-medium">
                  Draft Payrolls
                </v-list-item-title>
                <v-list-item-subtitle>
                  {{ stats.draftPayrolls }} payrolls ready to finalize
                </v-list-item-subtitle>
                <template v-slot:append>
                  <v-chip color="success" size="small" variant="flat">
                    {{ stats.draftPayrolls }}
                  </v-chip>
                </template>
              </v-list-item>

              <!-- No Pending Actions -->
              <v-list-item v-if="totalPendingActions === 0" class="px-5 py-8">
                <v-list-item-title class="text-center text-medium-emphasis">
                  <v-icon size="48" color="success" class="mb-2">mdi-check-circle</v-icon>
                  <div>All caught up! No pending actions.</div>
                </v-list-item-title>
              </v-list-item>
            </v-list>
          </v-card-text>
        </v-card>

        <!-- Recent Activity Feed -->
        <RecentActivityFeed class="mb-6" />

        <!-- Upcoming Events -->
        <UpcomingEvents />
      </v-col>

      <!-- Right Column - Quick Actions & Widgets -->
      <v-col cols="12" lg="4">
        <!-- Quick Actions -->
        <v-card class="modern-card mb-4" elevation="0">
          <v-card-title class="pa-5">
            <v-icon color="primary" size="small" class="mr-2">mdi-lightning-bolt</v-icon>
            <div class="text-subtitle-1 font-weight-bold">Quick Actions</div>
          </v-card-title>
          <v-divider></v-divider>
          <v-card-text class="pa-3">
            <v-btn
              block
              variant="tonal"
              color="primary"
              prepend-icon="mdi-account-plus"
              class="mb-2 justify-start"
              @click="showAddEmployeeDialog = true"
            >
              Add Employee
            </v-btn>
            <v-btn
              block
              variant="tonal"
              color="success"
              prepend-icon="mdi-currency-usd"
              class="mb-2 justify-start"
              @click="$router.push('/payroll/create')"
            >
              Create Payroll
            </v-btn>
            <v-btn
              block
              variant="tonal"
              color="info"
              prepend-icon="mdi-file-upload"
              class="mb-2 justify-start"
              @click="$router.push('/biometric-import')"
            >
              Import Attendance
            </v-btn>
          </v-card-text>
        </v-card>

        <!-- System Health -->
        <v-card class="modern-card mb-4" elevation="0">
          <v-card-title class="pa-5">
            <v-icon color="success" size="small" class="mr-2">mdi-shield-check</v-icon>
            <div class="text-subtitle-1 font-weight-bold">System Health</div>
          </v-card-title>
          <v-divider></v-divider>
          <v-card-text class="pa-5">
            <v-list class="py-0" density="compact">
              <v-list-item class="px-0 mb-3">
                <v-list-item-title class="text-caption text-medium-emphasis mb-1">
                  Employee Data Completion
                </v-list-item-title>
                <template v-slot:append>
                  <div class="d-flex align-center">
                    <span class="font-weight-bold mr-2 text-body-2">{{ employeeDataCompletion }}%</span>
                    <v-progress-circular
                      :model-value="employeeDataCompletion"
                      size="28"
                      width="3"
                      :color="employeeDataCompletion > 90 ? 'success' : 'warning'"
                    ></v-progress-circular>
                  </div>
                </template>
              </v-list-item>
              
              <v-list-item class="px-0 mb-3">
                <v-list-item-title class="text-caption text-medium-emphasis">
                  Monthly Attendance Rate
                </v-list-item-title>
                <template v-slot:append>
                  <v-chip 
                    size="small" 
                    :color="stats.monthlyAttendanceRate > 95 ? 'success' : 'warning'"
                    variant="flat"
                  >
                    {{ stats.monthlyAttendanceRate || 0 }}%
                  </v-chip>
                </template>
              </v-list-item>

              <v-list-item class="px-0">
                <v-list-item-title class="text-caption text-medium-emphasis">
                  Last Biometric Import
                </v-list-item-title>
                <template v-slot:append>
                  <span class="text-caption font-weight-medium">{{ lastBiometricImport }}</span>
                </template>
              </v-list-item>
            </v-list>
          </v-card-text>
        </v-card>

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
                label="Project"
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
import api from "@/services/api";
import { useToast } from "vue-toastification";
import { onAttendanceUpdate } from "@/stores/attendance";
import AddEmployeeDialog from "@/components/AddEmployeeDialog.vue";
import DashboardCalendar from "@/components/DashboardCalendar.vue";
import RecentActivityFeed from "@/components/RecentActivityFeed.vue";
import UpcomingEvents from "@/components/UpcomingEvents.vue";
import EmployeeDistributionChart from "@/components/charts/EmployeeDistributionChart.vue";
import AttendanceStatusChart from "@/components/charts/AttendanceStatusChart.vue";
import TodayStaffInfoChart from "@/components/charts/TodayStaffInfoChart.vue";

const toast = useToast();
const router = useRouter();

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

// Circular progress for community growth
const circumference = 2 * Math.PI * 50;

const employeeGrowthPercentage = computed(() => {
  if (stats.value.totalEmployees === 0) return 0;
  return Math.round(
    (stats.value.activeEmployees / stats.value.totalEmployees) * 100
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
    stats.value.draftPayrolls
  );
});

const employeeDataCompletion = computed(() => {
  if (stats.value.totalEmployees === 0) return 0;
  return Math.round(
    (stats.value.employeesCompleteData / stats.value.totalEmployees) * 100
  );
});

const lastBiometricImport = computed(() => {
  if (!stats.value.lastBiometricImportDate) return 'Never';
  const date = new Date(stats.value.lastBiometricImportDate);
  const now = new Date();
  const diffInDays = Math.floor((now - date) / (1000 * 60 * 60 * 24));
  
  if (diffInDays === 0) return 'Today';
  if (diffInDays === 1) return 'Yesterday';
  if (diffInDays < 7) return `${diffInDays} days ago`;
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
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
      { date_hired: approvalHireDate.value }
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
      error.response?.data?.message || "Failed to approve application"
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
      { rejection_reason: rejectionReason.value }
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
  animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(20px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

.dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 24px 32px;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.9) 0%, rgba(248, 250, 252, 0.8) 100%);
  backdrop-filter: blur(20px) saturate(180%);
  border-radius: 20px;
  border: 1px solid rgba(99, 102, 241, 0.15);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
  position: relative;
  overflow: hidden;
  
  &::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, #6366f1 0%, #8b5cf6 50%, #ec4899 100%);
  }

  @media (max-width: 960px) {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
    padding: 20px;
  }
}

.modern-card {
  border-radius: 20px !important;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(255, 255, 255, 0.9) 100%) !important;
  backdrop-filter: blur(20px) saturate(180%) !important;
  -webkit-backdrop-filter: blur(20px) saturate(180%) !important;
  border: 1px solid rgba(99, 102, 241, 0.1) !important;
  transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1) !important;
  position: relative;
  overflow: hidden;
  
  &::after {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at top right, rgba(99, 102, 241, 0.05), transparent 60%);
    pointer-events: none;
    opacity: 0;
    transition: opacity 0.4s ease;
  }

  &:hover {
    box-shadow: 0 20px 40px rgba(99, 102, 241, 0.15), 
                0 8px 16px rgba(0, 0, 0, 0.08),
                0 0 0 1px rgba(99, 102, 241, 0.1) inset !important;
    transform: translateY(-4px) scale(1.01);
    border-color: rgba(99, 102, 241, 0.25) !important;
    
    &::after {
      opacity: 1;
    }
  }
}

.stat-card {
  position: relative;
  overflow: hidden;

  &::before {
    content: "";
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #6366f1 0%, #8b5cf6 50%, #ec4899 100%);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);
  }
  
  :deep(.v-icon) {
    background: linear-gradient(135deg, currentColor 0%, currentColor 100%);
    padding: 10px;
    border-radius: 12px;
    opacity: 0.15;
    transition: all 0.3s ease;
  }
  
  &:hover {
    :deep(.v-icon) {
      opacity: 0.25;
      transform: scale(1.1) rotate(-5deg);
    }
  }
}

.circular-progress {
  .progress-circle {
    transition: stroke-dashoffset 1s cubic-bezier(0.4, 0, 0.2, 1);
    filter: drop-shadow(0 2px 8px currentColor);
  }
}

.progress-text {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
  font-weight: 700;
  background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
  -webkit-background-clip: text;
  -webkit-text-fill-color: transparent;
  background-clip: text;
}

.modern-table {
  :deep(thead) {
    background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%);
    
    th {
      font-weight: 600;
      color: #475569;
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 0.5px;
    }
  }

  :deep(tbody tr) {
    transition: all 0.3s ease;
    
    &:hover {
      background: linear-gradient(135deg, rgba(99, 102, 241, 0.04) 0%, rgba(139, 92, 246, 0.03) 100%) !important;
      transform: scale(1.01);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }
  }
}

// Enhanced list items
:deep(.v-list-item) {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  border-radius: 12px;
  margin: 4px 8px;
  
  &:hover {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(139, 92, 246, 0.03) 100%) !important;
    transform: translateX(4px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.1);
  }
}

// Modern chips
:deep(.v-chip) {
  font-weight: 600;
  backdrop-filter: blur(8px);
  border: 1px solid rgba(255, 255, 255, 0.3);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  transition: all 0.3s ease;
  
  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  }
}
</style>
