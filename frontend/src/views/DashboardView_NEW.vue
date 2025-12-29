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
      <v-col cols="12" sm="6" lg="3">
        <v-card class="modern-card stat-card" elevation="0">
          <v-card-text class="pa-5">
            <div class="d-flex justify-space-between align-center mb-3">
              <span class="text-subtitle-2 text-medium-emphasis">Total Revenue</span>
              <v-icon size="20" color="success">mdi-trending-up</v-icon>
            </div>
            <div class="text-h4 font-weight-bold mb-2">
              ₱{{ formatNumber(stats.periodPayroll) }}
            </div>
            <div class="d-flex align-center">
              <v-chip size="x-small" color="success" variant="flat" class="mr-2">
                <v-icon start size="12">mdi-arrow-up</v-icon>
                +4.2%
              </v-chip>
              <span class="text-caption text-medium-emphasis">from last month</span>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" lg="3">
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
              <v-chip size="x-small" color="primary" variant="flat" class="mr-2">
                <v-icon start size="12">mdi-arrow-up</v-icon>
                +1.7%
              </v-chip>
              <span class="text-caption text-medium-emphasis">from last month</span>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" lg="3">
        <v-card class="modern-card stat-card" elevation="0">
          <v-card-text class="pa-5">
            <div class="d-flex justify-space-between align-center mb-3">
              <span class="text-subtitle-2 text-medium-emphasis">Attendance Today</span>
              <v-icon size="20" color="info">mdi-clock-check</v-icon>
            </div>
            <div class="text-h4 font-weight-bold mb-2">
              {{ stats.presentToday }}
            </div>
            <div class="d-flex align-center">
              <v-chip size="x-small" color="error" variant="flat" class="mr-2">
                <v-icon start size="12">mdi-arrow-down</v-icon>
                -2.9%
              </v-chip>
              <span class="text-caption text-medium-emphasis">from last month</span>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" lg="3">
        <v-card class="modern-card stat-card" elevation="0">
          <v-card-text class="pa-5">
            <div class="d-flex justify-space-between align-center mb-3">
              <span class="text-subtitle-2 text-medium-emphasis">Total Employees</span>
              <v-icon size="20" color="warning">mdi-account-hard-hat</v-icon>
            </div>
            <div class="text-h4 font-weight-bold mb-2">
              {{ stats.totalEmployees }}
            </div>
            <div class="d-flex align-center">
              <v-chip size="x-small" color="success" variant="flat" class="mr-2">
                <v-icon start size="12">mdi-arrow-up</v-icon>
                +0.9%
              </v-chip>
              <span class="text-caption text-medium-emphasis">from last month</span>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Main Content Row -->
    <v-row>
      <!-- Left Column - Charts -->
      <v-col cols="12" lg="8">
        <!-- Revenue Chart -->
        <v-card class="modern-card mb-6" elevation="0">
          <v-card-title class="pa-5 d-flex justify-space-between align-center">
            <div>
              <div class="text-h6 font-weight-bold">Total Revenue</div>
              <div class="text-caption text-medium-emphasis mt-1">
                Monthly payroll overview
              </div>
            </div>
            <v-btn-toggle
              v-model="revenueFilter"
              mandatory
              density="compact"
              variant="outlined"
              divided
            >
              <v-btn value="earnings" size="small">Earnings</v-btn>
              <v-btn value="spendings" size="small">Spendings</v-btn>
            </v-btn-toggle>
          </v-card-title>
          <v-divider></v-divider>
          <v-card-text class="pa-5">
            <PayrollTrendChart v-if="revenueFilter === 'earnings'" :months="6" />
            <PayrollComparisonChart v-else />
          </v-card-text>
        </v-card>

        <!-- Statistics Cards -->
        <v-row>
          <v-col cols="12" md="6">
            <v-card class="modern-card" elevation="0">
              <v-card-title class="pa-5">
                <div class="text-subtitle-1 font-weight-bold">Employee Distribution</div>
              </v-card-title>
              <v-divider></v-divider>
              <v-card-text class="pa-5" style="height: 300px">
                <EmployeeDistributionChart />
              </v-card-text>
            </v-card>
          </v-col>

          <v-col cols="12" md="6">
            <v-card class="modern-card" elevation="0">
              <v-card-title class="pa-5">
                <div class="text-subtitle-1 font-weight-bold">Attendance Rate</div>
              </v-card-title>
              <v-divider></v-divider>
              <v-card-text class="pa-5" style="height: 300px">
                <AttendanceRateChart />
              </v-card-text>
            </v-card>
          </v-col>
        </v-row>
      </v-col>

      <!-- Right Column - Calendar & Info -->
      <v-col cols="12" lg="4">
        <!-- Calendar Widget -->
        <DashboardCalendar class="mb-6" />

        <!-- Quick Stats Card -->
        <v-card class="modern-card mb-6" elevation="0">
          <v-card-title class="pa-5">
            <div class="text-subtitle-1 font-weight-bold">Community Growth</div>
          </v-card-title>
          <v-divider></v-divider>
          <v-card-text class="pa-5 text-center">
            <div class="position-relative d-inline-block mb-4">
              <svg width="120" height="120" class="circular-progress">
                <circle
                  cx="60"
                  cy="60"
                  r="50"
                  fill="none"
                  stroke="#e0e0e0"
                  stroke-width="10"
                />
                <circle
                  cx="60"
                  cy="60"
                  r="50"
                  fill="none"
                  stroke="#6366f1"
                  stroke-width="10"
                  :stroke-dasharray="circumference"
                  :stroke-dashoffset="progressOffset"
                  transform="rotate(-90 60 60)"
                  class="progress-circle"
                />
              </svg>
              <div class="progress-text">
                <div class="text-h4 font-weight-bold">65%</div>
              </div>
            </div>
            <div class="d-flex align-center justify-center">
              <v-icon color="success" size="16" class="mr-1">mdi-arrow-up</v-icon>
              <span class="text-body-2 font-weight-medium text-success">+0.9%</span>
              <span class="text-body-2 text-medium-emphasis ml-1">from last month</span>
            </div>
          </v-card-text>
        </v-card>

        <!-- Pending Applications (if any) -->
        <v-card v-if="pendingApplications.length > 0" class="modern-card" elevation="0">
          <v-card-title class="pa-5 d-flex justify-space-between align-center">
            <div class="text-subtitle-1 font-weight-bold">Pending Applications</div>
            <v-chip size="small" color="warning" variant="flat">
              {{ pendingApplications.length }}
            </v-chip>
          </v-card-title>
          <v-divider></v-divider>
          <v-list class="py-0">
            <v-list-item
              v-for="app in pendingApplications.slice(0, 3)"
              :key="app.id"
              @click="viewApplication(app)"
              class="px-5"
            >
              <template v-slot:prepend>
                <v-avatar color="warning" size="40">
                  <v-icon>mdi-account</v-icon>
                </v-avatar>
              </template>
              <v-list-item-title class="font-weight-medium">
                {{ app.first_name }} {{ app.last_name }}
              </v-list-item-title>
              <v-list-item-subtitle>{{ app.position }}</v-list-item-subtitle>
              <template v-slot:append>
                <v-btn icon="mdi-chevron-right" size="small" variant="text"></v-btn>
              </template>
            </v-list-item>
          </v-list>
          <v-divider v-if="pendingApplications.length > 3"></v-divider>
          <v-card-actions v-if="pendingApplications.length > 3" class="pa-3">
            <v-btn
              block
              variant="text"
              size="small"
              color="primary"
              append-icon="mdi-arrow-right"
            >
              View All ({{ pendingApplications.length }})
            </v-btn>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>

    <!-- Payroll Summary Table -->
    <v-row class="mt-2">
      <v-col cols="12">
        <v-card class="modern-card" elevation="0">
          <v-card-title class="pa-5 d-flex justify-space-between align-center">
            <div>
              <div class="text-h6 font-weight-bold">Employees Payroll</div>
              <div class="text-caption text-medium-emphasis mt-1">
                Recent payroll transactions
              </div>
            </div>
            <v-btn
              variant="text"
              color="primary"
              append-icon="mdi-arrow-right"
              to="/payroll"
            >
              View All
            </v-btn>
          </v-card-title>
          <v-divider></v-divider>
          <v-data-table
            :headers="payrollHeaders"
            :items="recentPayrolls"
            :items-per-page="5"
            class="modern-table"
          >
            <template v-slot:item.payroll_number="{ item }">
              <span class="font-weight-medium">#{{ item.payroll_number }}</span>
            </template>

            <template v-slot:item.period="{ item }">
              <div class="text-body-2">
                {{ formatDateShort(item.period_start_date) }} - {{ formatDateShort(item.period_end_date) }}
              </div>
            </template>

            <template v-slot:item.amount="{ item }">
              <span class="font-weight-bold">₱{{ formatNumber(item.total_gross) }}</span>
            </template>

            <template v-slot:item.status="{ item }">
              <v-chip
                :color="getPayrollColor(item.status)"
                size="small"
                variant="flat"
              >
                {{ item.status }}
              </v-chip>
            </template>

            <template v-slot:item.actions="{ item }">
              <v-btn
                icon="mdi-eye"
                size="small"
                variant="text"
                :to="`/payroll/${item.id}`"
              ></v-btn>
            </template>
          </v-data-table>
        </v-card>
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
                  {{ newEmployeeData?.role || 'employee' }}
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
import { ref, onMounted, computed } from "vue";
import api from "@/services/api";
import { useToast } from "vue-toastification";
import AddEmployeeDialog from "@/components/AddEmployeeDialog.vue";
import DashboardCalendar from "@/components/DashboardCalendar.vue";
import PayrollTrendChart from "@/components/charts/PayrollTrendChart.vue";
import PayrollComparisonChart from "@/components/charts/PayrollComparisonChart.vue";
import EmployeeDistributionChart from "@/components/charts/EmployeeDistributionChart.vue";
import AttendanceRateChart from "@/components/charts/AttendanceRateChart.vue";

const toast = useToast();

const stats = ref({
  totalEmployees: 0,
  activeEmployees: 0,
  periodPayroll: 0,
  presentToday: 0,
  pendingApprovals: 0,
});

const recentPayrolls = ref([]);
const revenueFilter = ref('earnings');
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
  return `${start.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })} - ${end.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`;
});

const payrollHeaders = [
  { title: 'Payroll Number', key: 'payroll_number' },
  { title: 'Period', key: 'period' },
  { title: 'Amount', key: 'amount' },
  { title: 'Status', key: 'status' },
  { title: 'Actions', key: 'actions', sortable: false }
];

// Circular progress for community growth
const circumference = 2 * Math.PI * 50;
const progressOffset = computed(() => {
  const progress = 65; // 65%
  return circumference - (progress / 100) * circumference;
});

onMounted(async () => {
  await fetchDashboardData();
  await fetchProjects();
  await fetchPendingApplications();
});

async function fetchDashboardData() {
  try {
    const response = await api.get("/dashboard");
    stats.value = response.data.stats;
    recentPayrolls.value = response.data.recent_payrolls || [];
  } catch (error) {
    console.error("Error fetching dashboard data:", error);
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
}

.dashboard-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  
  @media (max-width: 960px) {
    flex-direction: column;
    align-items: flex-start;
    gap: 16px;
  }
}

.modern-card {
  border-radius: 16px !important;
  border: 1px solid rgba(0, 0, 0, 0.08);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  
  &:hover {
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12) !important;
    transform: translateY(-2px);
  }
}

.stat-card {
  position: relative;
  overflow: hidden;
  
  &::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #6366f1 0%, #8b5cf6 100%);
  }
}

.circular-progress {
  .progress-circle {
    transition: stroke-dashoffset 1s ease;
  }
}

.progress-text {
  position: absolute;
  top: 50%;
  left: 50%;
  transform: translate(-50%, -50%);
}

.modern-table {
  :deep(thead) {
    background-color: #f8fafc;
  }
  
  :deep(tbody tr:hover) {
    background-color: #f1f5f9 !important;
  }
}
</style>
