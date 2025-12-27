<template>
  <v-container fluid>
    <v-row>
      <v-col cols="12">
        <v-card>
          <v-card-title class="d-flex align-center">
            <v-icon class="mr-2">mdi-clipboard-text</v-icon>
            <span>My Submitted Applications</span>
            <v-spacer></v-spacer>
            <v-btn
              icon
              @click="fetchApplications"
              :loading="loading"
              title="Refresh"
            >
              <v-icon>mdi-refresh</v-icon>
            </v-btn>
          </v-card-title>

          <v-card-text>
            <!-- Statistics Cards -->
            <v-row class="mb-4">
              <v-col cols="12" sm="3">
                <v-card color="primary" dark>
                  <v-card-text>
                    <div class="text-h5">{{ stats.total }}</div>
                    <div>Total Applications</div>
                  </v-card-text>
                </v-card>
              </v-col>
              <v-col cols="12" sm="3">
                <v-card color="warning" dark>
                  <v-card-text>
                    <div class="text-h5">{{ stats.pending }}</div>
                    <div>Pending Review</div>
                  </v-card-text>
                </v-card>
              </v-col>
              <v-col cols="12" sm="3">
                <v-card color="success" dark>
                  <v-card-text>
                    <div class="text-h5">{{ stats.approved }}</div>
                    <div>Approved</div>
                  </v-card-text>
                </v-card>
              </v-col>
              <v-col cols="12" sm="3">
                <v-card color="error" dark>
                  <v-card-text>
                    <div class="text-h5">{{ stats.rejected }}</div>
                    <div>Rejected</div>
                  </v-card-text>
                </v-card>
              </v-col>
            </v-row>

            <!-- Tabs -->
            <v-tabs v-model="tab" class="mb-4">
              <v-tab value="all">All Applications</v-tab>
              <v-tab value="pending">Pending</v-tab>
              <v-tab value="approved">Approved</v-tab>
              <v-tab value="rejected">Rejected</v-tab>
            </v-tabs>

            <!-- Data Table -->
            <v-data-table
              :headers="headers"
              :items="filteredApplications"
              :loading="loading"
              class="elevation-1"
            >
              <template v-slot:item.full_name="{ item }">
                {{ item.first_name }} {{ item.last_name }}
              </template>

              <template v-slot:item.application_status="{ item }">
                <v-chip
                  :color="getStatusColor(item.application_status)"
                  size="small"
                  dark
                >
                  {{ item.application_status.toUpperCase() }}
                </v-chip>
              </template>

              <template v-slot:item.submitted_at="{ item }">
                {{ formatDate(item.submitted_at) }}
              </template>

              <template v-slot:item.reviewed_at="{ item }">
                {{ item.reviewed_at ? formatDate(item.reviewed_at) : "N/A" }}
              </template>

              <template v-slot:item.actions="{ item }">
                <v-btn
                  icon
                  size="small"
                  @click="viewApplication(item)"
                  title="View Details"
                >
                  <v-icon>mdi-eye</v-icon>
                </v-btn>
              </template>
            </v-data-table>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- View Application Dialog -->
    <v-dialog v-model="viewDialog" max-width="900px" scrollable>
      <v-card v-if="selectedApplication">
        <v-card-title
          class="text-h5 py-4"
          :class="getStatusClass(selectedApplication.application_status)"
        >
          <v-icon start>mdi-clipboard-text</v-icon>
          Application Details
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-6" style="max-height: 600px">
          <!-- Status Alert -->
          <v-alert
            :type="getAlertType(selectedApplication.application_status)"
            variant="tonal"
            class="mb-4"
          >
            <div class="text-h6">
              Status: {{ selectedApplication.application_status.toUpperCase() }}
            </div>
            <div v-if="selectedApplication.application_status === 'pending'">
              Your application is currently under review by the admin.
            </div>
            <div v-if="selectedApplication.application_status === 'rejected'">
              <strong>Reason:</strong>
              {{ selectedApplication.rejection_reason }}
            </div>
            <div v-if="selectedApplication.application_status === 'approved'">
              Your application has been approved! Your employee account has been
              created.
            </div>
          </v-alert>

          <!-- Credentials Section (Only for Approved Applications) -->
          <v-card
            v-if="
              selectedApplication.application_status === 'approved' &&
              selectedApplication.employee
            "
            class="mb-4 bg-green-lighten-5"
          >
            <v-card-title class="bg-success text-white">
              <v-icon start>mdi-key</v-icon>
              Your Login Credentials
            </v-card-title>
            <v-card-text class="pt-4">
              <v-alert type="info" variant="tonal" class="mb-4">
                <strong>Important:</strong> Please save these credentials
                securely. You will need them to log into the system.
              </v-alert>

              <v-row>
                <v-col cols="12" md="6">
                  <v-text-field
                    :model-value="selectedApplication.employee.employee_number"
                    label="Employee Number"
                    readonly
                    variant="outlined"
                    density="comfortable"
                    prepend-icon="mdi-badge-account"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    :model-value="selectedApplication.email"
                    label="Username"
                    readonly
                    variant="outlined"
                    density="comfortable"
                    prepend-icon="mdi-account"
                  ></v-text-field>
                </v-col>
                <v-col cols="12">
                  <v-alert type="warning" variant="tonal">
                    <div class="mb-2">
                      <strong>Note:</strong> For security reasons, your
                      temporary password is not displayed here. Please contact
                      the admin who approved your application to receive your
                      temporary password.
                    </div>
                    <div>
                      You will be required to change your password on first
                      login.
                    </div>
                  </v-alert>
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>

          <!-- Application Information -->
          <v-form>
            <v-row>
              <!-- Personal Information -->
              <v-col cols="12">
                <div class="text-h6 mb-2">Personal Information</div>
                <v-divider class="mb-4"></v-divider>
              </v-col>

              <v-col cols="12" md="4">
                <v-text-field
                  :model-value="selectedApplication.first_name"
                  label="First Name"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="4">
                <v-text-field
                  :model-value="selectedApplication.middle_name || 'N/A'"
                  label="Middle Name"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="4">
                <v-text-field
                  :model-value="selectedApplication.last_name"
                  label="Last Name"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  :model-value="selectedApplication.email"
                  label="Email"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  :model-value="selectedApplication.mobile_number"
                  label="Mobile Number"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  :model-value="selectedApplication.date_of_birth"
                  label="Date of Birth"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  :model-value="selectedApplication.gender"
                  label="Gender"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12">
                <v-textarea
                  :model-value="selectedApplication.worker_address"
                  label="Address"
                  rows="2"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-textarea>
              </v-col>

              <!-- Employment Information -->
              <v-col cols="12">
                <div class="text-h6 mb-2 mt-4">Employment Information</div>
                <v-divider class="mb-4"></v-divider>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  :model-value="selectedApplication.position"
                  label="Position Applied"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  :model-value="selectedApplication.employment_type"
                  label="Employment Type"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  :model-value="selectedApplication.salary_type"
                  label="Salary Type"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  :model-value="`₱${selectedApplication.basic_salary}`"
                  label="Basic Salary"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <!-- Application Dates -->
              <v-col cols="12">
                <div class="text-h6 mb-2 mt-4">Application Timeline</div>
                <v-divider class="mb-4"></v-divider>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  :model-value="formatDate(selectedApplication.submitted_at)"
                  label="Submitted At"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6" v-if="selectedApplication.reviewed_at">
                <v-text-field
                  :model-value="formatDate(selectedApplication.reviewed_at)"
                  label="Reviewed At"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="viewDialog = false"> Close </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useToast } from "vue-toastification";
import api from "@/services/api";
import { format } from "date-fns";

const toast = useToast();

const loading = ref(false);
const viewDialog = ref(false);
const tab = ref("all");
const applications = ref([]);
const selectedApplication = ref(null);

const headers = [
  { title: "Full Name", key: "full_name", sortable: true },
  { title: "Email", key: "email", sortable: true },
  { title: "Position", key: "position", sortable: true },
  { title: "Status", key: "application_status", sortable: true },
  { title: "Submitted", key: "submitted_at", sortable: true },
  { title: "Reviewed", key: "reviewed_at", sortable: true },
  { title: "Actions", key: "actions", sortable: false },
];

const stats = computed(() => ({
  total: applications.value.length,
  pending: applications.value.filter((a) => a.application_status === "pending")
    .length,
  approved: applications.value.filter(
    (a) => a.application_status === "approved"
  ).length,
  rejected: applications.value.filter(
    (a) => a.application_status === "rejected"
  ).length,
}));

const filteredApplications = computed(() => {
  if (tab.value === "all") return applications.value;
  return applications.value.filter((a) => a.application_status === tab.value);
});

async function fetchApplications() {
  loading.value = true;
  try {
    const response = await api.get("/employee-applications");
    const data = response.data.data || response.data;
    applications.value = Array.isArray(data) ? data : data.data || [];
  } catch (error) {
    console.error("Error fetching applications:", error);
    toast.error("Failed to load applications");
  } finally {
    loading.value = false;
  }
}

function viewApplication(application) {
  selectedApplication.value = application;
  viewDialog.value = true;
}

function getStatusColor(status) {
  const colors = {
    pending: "warning",
    approved: "success",
    rejected: "error",
  };
  return colors[status] || "grey";
}

function getStatusClass(status) {
  const classes = {
    pending: "bg-warning",
    approved: "bg-success",
    rejected: "bg-error",
  };
  return classes[status] || "bg-grey";
}

function getAlertType(status) {
  const types = {
    pending: "info",
    approved: "success",
    rejected: "error",
  };
  return types[status] || "info";
}

function formatDate(dateString) {
  if (!dateString) return "N/A";
  return format(new Date(dateString), "MMM dd, yyyy hh:mm a");
}

onMounted(() => {
  fetchApplications();
});
</script>
