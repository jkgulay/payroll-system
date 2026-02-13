<template>
  <div class="resume-review-page">
    <!-- Modern Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="page-title-section">
          <div class="page-icon-badge">
            <v-icon size="22">mdi-file-certificate-outline</v-icon>
          </div>
          <div>
            <h1 class="page-title">Resume Review</h1>
            <p class="page-subtitle">
              Review and approve resume submissions from Human Resourcess
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Modern Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon pending">
          <v-icon size="20">mdi-clock-outline</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Pending Review</div>
          <div class="stat-value">{{ stats.pending }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon approved">
          <v-icon size="20">mdi-check-circle</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Approved</div>
          <div class="stat-value">{{ stats.approved }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon rejected">
          <v-icon size="20">mdi-close-circle</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Rejected</div>
          <div class="stat-value">{{ stats.rejected }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon total">
          <v-icon size="20">mdi-file-document-multiple</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Total</div>
          <div class="stat-value">{{ stats.total }}</div>
        </div>
      </div>
    </div>

    <!-- Resume List -->
    <div class="modern-card">
      <div class="filters-section mb-3">
        <v-row align="center">
          <v-col cols="12" md="4">
            <v-select
              v-model="statusFilter"
              :items="statusOptions"
              label="Filter by Status"
              variant="outlined"
              density="comfortable"
              hide-details
              @update:modelValue="fetchResumes"
            ></v-select>
          </v-col>
          <v-spacer></v-spacer>
          <v-col cols="auto">
            <v-btn
              color="#ED985F"
              variant="tonal"
              icon="mdi-refresh"
              @click="fetchResumes"
              :loading="loading"
              title="Refresh"
            ></v-btn>
          </v-col>
        </v-row>
      </div>

      <div class="table-section">
        <v-data-table
          :headers="headers"
          :items="resumes"
          :loading="loading"
          class="elevation-1"
        >
          <template v-slot:item.full_name="{ item }">
            <div v-if="item.first_name">
              <strong>
                {{ item.first_name }}
                <template v-if="item.middle_name">{{ item.middle_name }}</template>
                {{ item.last_name }}
              </strong>
              <div v-if="item.position_applied" class="text-caption text-grey">
                {{ item.position_applied }}
              </div>
            </div>
            <div v-else>
              <span class="text-grey">N/A</span>
            </div>
          </template>

          <template v-slot:item.user="{ item }">
            <div>
              <strong>{{ item.user?.username }}</strong
              ><br />
              <small>{{ item.user?.email }}</small>
            </div>
          </template>

          <template v-slot:item.original_filename="{ item }">
            <div class="d-flex align-center">
              <v-icon :color="getFileIcon(item.file_type).color" class="mr-2">
                {{ getFileIcon(item.file_type).icon }}
              </v-icon>
              <span>{{ item.original_filename }}</span>
            </div>
          </template>

          <template v-slot:item.file_size="{ item }">
            {{ formatFileSize(item.file_size) }}
          </template>

          <template v-slot:item.status="{ item }">
            <v-chip :color="getStatusColor(item.status)" size="small" dark>
              {{ item.status.toUpperCase() }}
            </v-chip>
          </template>

          <template v-slot:item.created_at="{ item }">
            {{ formatDateTime(item.created_at) }}
          </template>

          <template v-slot:item.actions="{ item }">
            <v-btn
              icon
              size="small"
              @click="viewResume(item)"
              title="View Details"
            >
              <v-icon>mdi-eye</v-icon>
            </v-btn>
            <v-btn
              v-if="!item.is_application"
              icon
              size="small"
              @click="downloadResume(item)"
              title="Download"
            >
              <v-icon>mdi-download</v-icon>
            </v-btn>
            <v-btn
              v-if="item.status === 'pending' && !item.is_application"
              icon
              size="small"
              color="success"
              @click="openApproveDialog(item)"
              title="Approve"
            >
              <v-icon>mdi-check</v-icon>
            </v-btn>
            <v-btn
              v-if="item.status === 'pending' && !item.is_application"
              icon
              size="small"
              color="error"
              @click="openRejectDialog(item)"
              title="Reject"
            >
              <v-icon>mdi-close</v-icon>
            </v-btn>
            <v-btn
              v-if="item.status === 'pending' && item.is_application"
              icon
              size="small"
              color="success"
              @click="openApproveApplicationDialog(item)"
              title="Approve Application"
            >
              <v-icon>mdi-check-circle</v-icon>
            </v-btn>
            <v-btn
              v-if="item.status === 'pending' && item.is_application"
              icon
              size="small"
              color="error"
              @click="openRejectApplicationDialog(item)"
              title="Reject Application"
            >
              <v-icon>mdi-close-circle</v-icon>
            </v-btn>
          </template>
        </v-data-table>
      </div>
    </div>

    <!-- View Dialog -->
    <v-dialog v-model="viewDialog" max-width="900">
      <v-card v-if="selectedResume" class="modern-dialog">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper info">
            <v-icon size="24">mdi-file-account</v-icon>
          </div>
          <div>
            <div class="dialog-title">Resume Details</div>
            <div class="dialog-subtitle">Review and approve applicant resume</div>
          </div>
        </v-card-title>

        <v-card-text class="dialog-content">
          <!-- Application Resume Alert -->
          <v-alert
            v-if="selectedResume.is_application"
            type="info"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            <div class="alert-title">Employee Application Resume</div>
            <div>This resume is part of an employee application. You can approve or reject it directly from here.</div>
          </v-alert>

          <div class="detail-row">
            <div class="detail-label">Status</div>
            <div class="detail-value">
              <v-chip
                :color="getStatusColor(selectedResume.status)"
                variant="flat"
                size="small"
              >
                {{ selectedResume.status.toUpperCase() }}
              </v-chip>
            </div>
          </div>

          <!-- Applicant Information -->
          <div v-if="selectedResume.first_name" class="detail-row">
            <div class="detail-label">Full Name</div>
            <div class="detail-value">
              {{ selectedResume.first_name }}
              <template v-if="selectedResume.middle_name">{{ selectedResume.middle_name }}</template>
              {{ selectedResume.last_name }}
            </div>
          </div>

          <div v-if="selectedResume.date_of_birth" class="detail-row">
            <div class="detail-label">Date of Birth</div>
            <div class="detail-value">{{ formatDate(selectedResume.date_of_birth) }}</div>
          </div>

          <div v-if="selectedResume.gender" class="detail-row">
            <div class="detail-label">Gender</div>
            <div class="detail-value">{{ selectedResume.gender.charAt(0).toUpperCase() + selectedResume.gender.slice(1) }}</div>
          </div>

          <div v-if="selectedResume.email" class="detail-row">
            <div class="detail-label">Email</div>
            <div class="detail-value">{{ selectedResume.email }}</div>
          </div>

          <div v-if="selectedResume.phone" class="detail-row">
            <div class="detail-label">Phone</div>
            <div class="detail-value">{{ selectedResume.phone }}</div>
          </div>

          <div v-if="selectedResume.address" class="detail-row">
            <div class="detail-label">Address</div>
            <div class="detail-value">{{ selectedResume.address }}</div>
          </div>

          <div v-if="selectedResume.position_applied" class="detail-row">
            <div class="detail-label">Position Applied</div>
            <div class="detail-value">{{ selectedResume.position_applied }}</div>
          </div>

          <div v-if="selectedResume.department_preference" class="detail-row">
            <div class="detail-label">Department</div>
            <div class="detail-value">{{ selectedResume.department_preference }}</div>
          </div>

          <div v-if="selectedResume.expected_salary" class="detail-row">
            <div class="detail-label">Expected Salary</div>
            <div class="detail-value">₱{{ selectedResume.expected_salary }}</div>
          </div>

          <div v-if="selectedResume.availability_date" class="detail-row">
            <div class="detail-label">Start Date</div>
            <div class="detail-value">{{ formatDate(selectedResume.availability_date) }}</div>
          </div>

          <!-- Submission Details -->
          <div class="detail-row">
            <div class="detail-label">Uploaded By</div>
            <div class="detail-value">
              <div class="employee-info">
                <div class="employee-name">{{ selectedResume.user?.username }}</div>
                <div class="employee-number">{{ selectedResume.user?.email }}</div>
              </div>
            </div>
          </div>

          <div class="detail-row">
            <div class="detail-label">File Name</div>
            <div class="detail-value">{{ selectedResume.original_filename }}</div>
          </div>

          <div class="detail-row">
            <div class="detail-label">File Type</div>
            <div class="detail-value">{{ selectedResume.file_type.toUpperCase() }}</div>
          </div>

          <div class="detail-row">
            <div class="detail-label">File Size</div>
            <div class="detail-value">{{ formatFileSize(selectedResume.file_size) }}</div>
          </div>

          <div class="detail-row">
            <div class="detail-label">Uploaded</div>
            <div class="detail-value">{{ formatDateTime(selectedResume.created_at) }}</div>
          </div>

          <div v-if="selectedResume.reviewed_at" class="detail-row">
            <div class="detail-label">Reviewed</div>
            <div class="detail-value">{{ formatDateTime(selectedResume.reviewed_at) }}</div>
          </div>

          <div v-if="selectedResume.reviewer" class="detail-row">
            <div class="detail-label">Reviewed By</div>
            <div class="detail-value">{{ selectedResume.reviewer.username }}</div>
          </div>

          <div v-if="selectedResume.notes" class="detail-row full-width">
            <div class="detail-label">Applicant Notes</div>
            <div class="detail-value">{{ selectedResume.notes }}</div>
          </div>

          <v-alert
            v-if="selectedResume.admin_notes"
            :type="selectedResume.status === 'approved' ? 'success' : 'error'"
            variant="tonal"
            density="compact"
            class="mt-3"
          >
            <div class="alert-title">Admin Notes</div>
            <div>{{ selectedResume.admin_notes }}</div>
          </v-alert>

          <!-- Preview for images -->
          <div
            v-if="
              ['jpg', 'jpeg', 'png'].includes(
                selectedResume.file_type.toLowerCase(),
              )
            "
            class="mt-4"
          >
            <v-divider class="mb-4"></v-divider>
            <div class="detail-label mb-2">Preview</div>
            <v-img
              :src="selectedResume.file_url"
              max-height="600"
              contain
              class="mt-2"
            ></v-img>
          </div>

          <!-- PDF Preview -->
          <div
            v-if="selectedResume.file_type.toLowerCase() === 'pdf'"
            class="mt-4"
          >
            <v-divider class="mb-4"></v-divider>
            <div class="detail-label mb-2">PDF Preview</div>
            <iframe
              :src="selectedResume.file_url"
              width="100%"
              height="600px"
              class="mt-2"
            ></iframe>
          </div>
        </v-card-text>

        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <button
            v-if="
              selectedResume.status === 'pending' &&
              !selectedResume.is_application
            "
            class="dialog-btn dialog-btn-success"
            @click="openApproveDialog(selectedResume)"
          >
            <v-icon size="18">mdi-check</v-icon>
            Approve
          </button>
          <button
            v-if="
              selectedResume.status === 'pending' &&
              !selectedResume.is_application
            "
            class="dialog-btn dialog-btn-danger"
            @click="openRejectDialog(selectedResume)"
          >
            <v-icon size="18">mdi-close</v-icon>
            Reject
          </button>
          <button
            v-if="
              selectedResume.status === 'pending' &&
              selectedResume.is_application
            "
            class="dialog-btn dialog-btn-success"
            @click="openApproveApplicationDialog(selectedResume)"
          >
            <v-icon size="18">mdi-check-circle</v-icon>
            Approve Application
          </button>
          <button
            v-if="
              selectedResume.status === 'pending' &&
              selectedResume.is_application
            "
            class="dialog-btn dialog-btn-danger"
            @click="openRejectApplicationDialog(selectedResume)"
          >
            <v-icon size="18">mdi-close-circle</v-icon>
            Reject Application
          </button>
          <button
            v-if="!selectedResume.is_application"
            class="dialog-btn dialog-btn-success"
            @click="downloadResume(selectedResume)"
          >
            <v-icon size="18">mdi-download</v-icon>
            Download
          </button>
          <button
            class="dialog-btn dialog-btn-cancel"
            @click="viewDialog = false"
          >
            Close
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Approve Dialog -->
    <v-dialog v-model="approveDialog" max-width="600">
      <v-card>
        <v-card-title>Approve Resume</v-card-title>
        <v-card-text>
          <p class="mb-4">
            Are you sure you want to approve this resume from
            <strong>{{ resumeToReview?.user?.username }}</strong
            >?
          </p>
          <v-textarea
            v-model="adminNotes"
            label="Admin Notes (Optional)"
            outlined
            rows="3"
            placeholder="Add any comments or notes..."
          ></v-textarea>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn text @click="approveDialog = false" :disabled="processing">
            Cancel
          </v-btn>
          <v-btn color="success" @click="approveResume" :loading="processing">
            Approve
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Reject Dialog -->
    <v-dialog v-model="rejectDialog" max-width="600">
      <v-card>
        <v-card-title>Reject Resume</v-card-title>
        <v-card-text>
          <p class="mb-4">
            Are you sure you want to reject this resume from
            <strong>{{ resumeToReview?.user?.username }}</strong
            >?
          </p>
          <v-textarea
            v-model="adminNotes"
            label="Reason for Rejection (Required)"
            outlined
            rows="3"
            :rules="[(v) => !!v || 'Rejection reason is required']"
            placeholder="Please provide a reason for rejection..."
          ></v-textarea>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn text @click="rejectDialog = false" :disabled="processing">
            Cancel
          </v-btn>
          <v-btn
            color="error"
            @click="rejectResume"
            :loading="processing"
            :disabled="!adminNotes"
          >
            Reject
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Approve Application Dialog -->
    <v-dialog v-model="approveApplicationDialog" max-width="600">
      <v-card>
        <v-card-title>Approve Employee Application</v-card-title>
        <v-card-text>
          <p class="mb-4">
            Approving this application will create an employee account for
            <strong
              >{{ applicationToReview?.first_name }}
              {{ applicationToReview?.last_name }}</strong
            >.
          </p>
          <v-text-field
            v-model="applicationHireDate"
            label="Hire Date (Required)"
            type="date"
            outlined
            :rules="[(v) => !!v || 'Hire date is required']"
          ></v-text-field>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn
            text
            @click="approveApplicationDialog = false"
            :disabled="processing"
          >
            Cancel
          </v-btn>
          <v-btn
            color="success"
            @click="approveApplication"
            :loading="processing"
            :disabled="!applicationHireDate"
          >
            Approve & Create Employee
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Reject Application Dialog -->
    <v-dialog v-model="rejectApplicationDialog" max-width="600">
      <v-card>
        <v-card-title>Reject Employee Application</v-card-title>
        <v-card-text>
          <p class="mb-4">
            Are you sure you want to reject the application from
            <strong
              >{{ applicationToReview?.first_name }}
              {{ applicationToReview?.last_name }}</strong
            >?
          </p>
          <v-textarea
            v-model="applicationRejectionReason"
            label="Reason for Rejection (Required)"
            outlined
            rows="3"
            :rules="[(v) => !!v || 'Rejection reason is required']"
            placeholder="Please provide a reason for rejection..."
          ></v-textarea>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn
            text
            @click="rejectApplicationDialog = false"
            :disabled="processing"
          >
            Cancel
          </v-btn>
          <v-btn
            color="error"
            @click="rejectApplication"
            :loading="processing"
            :disabled="!applicationRejectionReason"
          >
            Reject Application
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Application Approval Success Dialog -->
    <v-dialog v-model="approvalSuccessDialog" max-width="600" persistent>
      <v-card>
        <v-card-title class="text-h5 success white--text">
          Application Approved Successfully
        </v-card-title>
        <v-card-text class="pt-4">
          <v-alert type="success" variant="tonal" class="mb-4">
            Employee account has been created successfully!
          </v-alert>

          <div class="mb-4">
            <h3 class="mb-2">Employee Details:</h3>
            <v-list dense>
              <v-list-item>
                <v-list-item-title>Employee Number:</v-list-item-title>
                <v-list-item-subtitle class="text-right">
                  <strong>{{ approvedEmployeeData?.employee_number }}</strong>
                </v-list-item-subtitle>
              </v-list-item>
              <v-list-item>
                <v-list-item-title>Name:</v-list-item-title>
                <v-list-item-subtitle class="text-right">
                  {{ approvedEmployeeData?.first_name }}
                  {{ approvedEmployeeData?.last_name }}
                </v-list-item-subtitle>
              </v-list-item>
              <v-list-item>
                <v-list-item-title>Position:</v-list-item-title>
                <v-list-item-subtitle class="text-right">
                  {{ approvedEmployeeData?.position }}
                </v-list-item-subtitle>
              </v-list-item>
            </v-list>
          </div>

          <v-divider class="my-4"></v-divider>

          <div>
            <h3 class="mb-2">Login Credentials:</h3>
            <v-alert type="info" variant="tonal">
              <p class="mb-2">
                <strong>Username:</strong> {{ approvedEmployeeData?.email }}
              </p>
              <p class="mb-0">
                <strong>Temporary Password:</strong>
                {{ approvedEmployeePassword }}
              </p>
            </v-alert>
            <p class="text-caption mt-2">
              Please provide these credentials to the employee. They will be
              required to change the password on first login.
            </p>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn color="primary" @click="closeApprovalSuccessDialog">
            Close
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useToast } from "vue-toastification";
import { resumeService } from "@/services/resumeService";
import api from "@/services/api";
import { formatDate, formatDateTime } from "@/utils/formatters";
import { devLog } from "@/utils/devLog";

const toast = useToast();

const loading = ref(false);
const processing = ref(false);
const viewDialog = ref(false);
const approveDialog = ref(false);
const rejectDialog = ref(false);
const approveApplicationDialog = ref(false);
const rejectApplicationDialog = ref(false);
const approvalSuccessDialog = ref(false);
const resumes = ref([]);
const selectedResume = ref(null);
const resumeToReview = ref(null);
const applicationToReview = ref(null);
const adminNotes = ref("");
const applicationHireDate = ref("");
const applicationRejectionReason = ref("");
const approvedEmployeeData = ref(null);
const approvedEmployeePassword = ref("");
const statusFilter = ref("all");

const statusOptions = [
  { title: "All", value: "all" },
  { title: "Pending", value: "pending" },
  { title: "Approved", value: "approved" },
  { title: "Rejected", value: "rejected" },
];

const headers = [
  { title: "Full Name", value: "full_name", sortable: true },
  { title: "Submitted By", value: "user", sortable: true },
  { title: "Filename", value: "original_filename", sortable: true },
  { title: "Type", value: "file_type", sortable: true },
  { title: "Size", value: "file_size", sortable: true },
  { title: "Status", value: "status", sortable: true },
  { title: "Uploaded", value: "created_at", sortable: true },
  { title: "Actions", value: "actions", sortable: false, align: "center" },
];

const stats = computed(() => ({
  total: resumes.value.length,
  pending: resumes.value.filter((r) => r.status === "pending").length,
  approved: resumes.value.filter((r) => r.status === "approved").length,
  rejected: resumes.value.filter((r) => r.status === "rejected").length,
}));

onMounted(() => {
  fetchResumes();
});

async function fetchResumes() {
  loading.value = true;
  try {
    const params =
      statusFilter.value !== "all" ? { status: statusFilter.value } : {};
    const response = await resumeService.getAllResumes(params);

    if (response.success) {
      resumes.value = response.data;
    }
  } catch (error) {
    toast.error("Failed to fetch resumes");
    devLog.error(error);
  } finally {
    loading.value = false;
  }
}

function viewResume(resume) {
  selectedResume.value = resume;
  viewDialog.value = true;
}

async function downloadResume(resume) {
  try {
    const response = await resumeService.downloadResume(resume.id);

    // Create blob link to download
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute("download", resume.original_filename);
    document.body.appendChild(link);
    link.click();
    link.remove();

    toast.success("Resume downloaded successfully");
  } catch (error) {
    toast.error("Failed to download resume");
    devLog.error(error);
  }
}

function openApproveDialog(resume) {
  resumeToReview.value = resume;
  adminNotes.value = "";
  approveDialog.value = true;
  viewDialog.value = false;
}

function openRejectDialog(resume) {
  resumeToReview.value = resume;
  adminNotes.value = "";
  rejectDialog.value = true;
  viewDialog.value = false;
}

async function approveResume() {
  if (!resumeToReview.value) return;

  processing.value = true;
  try {
    const response = await resumeService.approveResume(
      resumeToReview.value.id,
      adminNotes.value,
    );

    if (response.success) {
      toast.success(response.message);
      approveDialog.value = false;
      adminNotes.value = "";
      resumeToReview.value = null;
      fetchResumes();
    }
  } catch (error) {
    toast.error(error.response?.data?.message || "Failed to approve resume");
    devLog.error(error);
  } finally {
    processing.value = false;
  }
}

async function rejectResume() {
  if (!resumeToReview.value || !adminNotes.value) return;

  processing.value = true;
  try {
    const response = await resumeService.rejectResume(
      resumeToReview.value.id,
      adminNotes.value,
    );

    if (response.success) {
      toast.success(response.message);
      rejectDialog.value = false;
      adminNotes.value = "";
      resumeToReview.value = null;
      fetchResumes();
    }
  } catch (error) {
    toast.error(error.response?.data?.message || "Failed to reject resume");
    devLog.error(error);
  } finally {
    processing.value = false;
  }
}

function openApproveApplicationDialog(resume) {
  // Extract application ID from the resume ID (format: 'app_X')
  const applicationId = resume.id.toString().replace("app_", "");

  // Fetch full application details
  fetchApplicationDetails(applicationId);

  applicationHireDate.value = "";
  approveApplicationDialog.value = true;
  viewDialog.value = false;
}

function openRejectApplicationDialog(resume) {
  // Extract application ID from the resume ID (format: 'app_X')
  const applicationId = resume.id.toString().replace("app_", "");

  // Fetch full application details
  fetchApplicationDetails(applicationId);

  applicationRejectionReason.value = "";
  rejectApplicationDialog.value = true;
  viewDialog.value = false;
}

async function fetchApplicationDetails(applicationId) {
  try {
    const response = await api.get(`/employee-applications/${applicationId}`);
    applicationToReview.value = response.data;
  } catch (error) {
    toast.error("Failed to fetch application details");
    devLog.error(error);
  }
}

async function approveApplication() {
  if (!applicationToReview.value || !applicationHireDate.value) return;

  processing.value = true;
  try {
    const response = await api.post(
      `/employee-applications/${applicationToReview.value.id}/approve`,
      { date_hired: applicationHireDate.value },
    );

    approvedEmployeeData.value = response.data.employee;
    approvedEmployeePassword.value = response.data.temporary_password;

    toast.success("Application approved successfully!");
    approveApplicationDialog.value = false;
    approvalSuccessDialog.value = true;

    fetchResumes();
  } catch (error) {
    toast.error(
      error.response?.data?.message || "Failed to approve application",
    );
    devLog.error(error);
  } finally {
    processing.value = false;
  }
}

async function rejectApplication() {
  if (!applicationToReview.value || !applicationRejectionReason.value) return;

  processing.value = true;
  try {
    const response = await api.post(
      `/employee-applications/${applicationToReview.value.id}/reject`,
      { rejection_reason: applicationRejectionReason.value },
    );

    toast.success("Application rejected successfully");
    rejectApplicationDialog.value = false;
    applicationRejectionReason.value = "";
    applicationToReview.value = null;

    fetchResumes();
  } catch (error) {
    toast.error(
      error.response?.data?.message || "Failed to reject application",
    );
    devLog.error(error);
  } finally {
    processing.value = false;
  }
}

function closeApprovalSuccessDialog() {
  approvalSuccessDialog.value = false;
  applicationHireDate.value = "";
  applicationToReview.value = null;
  approvedEmployeeData.value = null;
  approvedEmployeePassword.value = "";
}

function getStatusColor(status) {
  const colors = {
    pending: "warning",
    approved: "success",
    rejected: "error",
  };
  return colors[status] || "grey";
}

function getFileIcon(fileType) {
  const icons = {
    pdf: { icon: "mdi-file-pdf-box", color: "error" },
    doc: { icon: "mdi-file-word-box", color: "primary" },
    docx: { icon: "mdi-file-word-box", color: "primary" },
    jpg: { icon: "mdi-file-image", color: "success" },
    jpeg: { icon: "mdi-file-image", color: "success" },
    png: { icon: "mdi-file-image", color: "success" },
  };
  return icons[fileType.toLowerCase()] || { icon: "mdi-file", color: "grey" };
}

function formatFileSize(bytes) {
  if (bytes === 0) return "0 Bytes";
  const k = 1024;
  const sizes = ["Bytes", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + " " + sizes[i];
}
</script>

<style scoped lang="scss">
.resume-review-page {
  background-color: #f8f9fa;
  min-height: 100vh;
}

.page-header {
  margin-bottom: 24px;
}

.header-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.page-title-section {
  display: flex;
  align-items: center;
  gap: 16px;
}

.page-icon-badge {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.25);
}

.page-title {
  font-size: 28px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
  line-height: 1.2;
}

.page-subtitle {
  font-size: 14px;
  color: rgba(0, 31, 61, 0.6);
  margin: 4px 0 0 0;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
  margin-bottom: 20px;
}

.stat-card {
  background: white;
  border-radius: 12px;
  padding: 14px 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  display: flex;
  align-items: center;
  gap: 12px;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  overflow: hidden;

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
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(237, 152, 95, 0.2);
  border-color: rgba(237, 152, 95, 0.3);

  &::before {
    transform: scaleY(1);
  }
}

.stat-icon {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.stat-icon.pending {
  background: rgba(237, 152, 95, 0.1);

  .v-icon {
    color: #ed985f;
  }
}

.stat-icon.approved {
  background: rgba(16, 185, 129, 0.1);

  .v-icon {
    color: #10b981;
  }
}

.stat-icon.rejected {
  background: rgba(239, 68, 68, 0.1);

  .v-icon {
    color: #ef4444;
  }
}

.stat-icon.total {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);

  .v-icon {
    color: white;
  }
}

.stat-content {
  flex: 1;
  min-width: 0;
}

.stat-label {
  font-size: 11px;
  color: rgba(0, 31, 61, 0.6);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
}

.stat-value {
  font-size: 24px;
  font-weight: 700;
  color: #001f3d;
  line-height: 1;
}

.modern-card {
  background: white;
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid rgba(0, 31, 61, 0.08);
  padding: 24px;
}

/* Modern Dialog Styles */
.modern-dialog .dialog-header {
  background: linear-gradient(135deg, #001f3d 0%, #0a2f4d 100%);
  color: white;
  padding: 24px 28px;
  display: flex;
  align-items: center;
  gap: 16px;
  border-bottom: none;
}

.modern-dialog .dialog-icon-wrapper {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  transition: all 0.3s ease;
}

.modern-dialog .dialog-icon-wrapper.info {
  background: rgba(237, 152, 95, 0.2);
  color: #f7b980;
}

.modern-dialog .dialog-icon-wrapper:hover {
  transform: scale(1.05);
}

.modern-dialog .dialog-title {
  font-size: 20px;
  font-weight: 700;
  color: white;
  line-height: 1.2;
}

.modern-dialog .dialog-subtitle {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.7);
  margin-top: 4px;
}

.modern-dialog .dialog-content {
  padding: 28px !important;
  background: #fafbfc;
}

.modern-dialog .detail-row {
  display: grid;
  grid-template-columns: 140px 1fr;
  gap: 16px;
  padding: 14px 0;
  border-bottom: 1px solid rgba(0, 31, 61, 0.06);
  align-items: start;
}

.modern-dialog .detail-row:last-child {
  border-bottom: none;
}

.modern-dialog .detail-row.full-width {
  grid-template-columns: 140px 1fr;
}

.modern-dialog .detail-label {
  font-size: 13px;
  font-weight: 600;
  color: rgba(0, 31, 61, 0.6);
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.modern-dialog .detail-value {
  font-size: 14px;
  color: #001f3d;
  font-weight: 500;
  word-break: break-word;
}

.modern-dialog .employee-info {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.modern-dialog .employee-name {
  font-weight: 600;
  color: #001f3d;
}

.modern-dialog .employee-number {
  font-size: 13px;
  color: rgba(0, 31, 61, 0.6);
}

.modern-dialog .dialog-actions {
  padding: 20px 28px;
  background: white;
  border-top: 1px solid rgba(0, 31, 61, 0.08);
  gap: 12px;
}

.modern-dialog .dialog-btn {
  padding: 10px 20px;
  border-radius: 8px;
  font-weight: 600;
  font-size: 14px;
  border: none;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 8px;
  transition: all 0.2s ease;
  text-transform: none;
  letter-spacing: 0.3px;
}

.modern-dialog .dialog-btn-cancel {
  background: #f5f5f5;
  color: rgba(0, 0, 0, 0.7);
}

.modern-dialog .dialog-btn-cancel:hover {
  background: #eeeeee;
  transform: translateY(-1px);
}

.modern-dialog .dialog-btn-success {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: white;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);
}

.modern-dialog .dialog-btn-success:hover {
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.4);
  transform: translateY(-1px);
}

.modern-dialog .dialog-btn-danger {
  background: linear-gradient(135deg, #ef5350 0%, #f44336 100%);
  color: white;
  box-shadow: 0 2px 8px rgba(239, 83, 80, 0.3);
}

.modern-dialog .dialog-btn-danger:hover {
  box-shadow: 0 4px 12px rgba(239, 83, 80, 0.4);
  transform: translateY(-1px);
}

.modern-dialog .alert-title {
  font-weight: 700;
  font-size: 14px;
  margin-bottom: 4px;
}
</style>
