<template>
  <div class="resume-submissions-page">
    <!-- Clean Page Header -->
    <div class="page-header">
      <div class="header-left">
        <h1 class="page-title">
          <v-icon class="title-icon">mdi-file-account-outline</v-icon>
          Resume Submissions
        </h1>
        <p class="page-subtitle">View and manage all submitted resumes</p>
      </div>
      <v-btn
        color="primary"
        variant="flat"
        prepend-icon="mdi-refresh"
        @click="fetchResumes"
        :loading="loading"
      >
        Refresh
      </v-btn>
    </div>

    <!-- Simple Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card" @click="activeTab = 'pending'">
        <div class="stat-info">
          <div class="stat-label">Pending</div>
          <div class="stat-value">{{ stats.pending }}</div>
        </div>
        <v-icon class="stat-icon warning">mdi-clock-outline</v-icon>
      </div>
      <div class="stat-card" @click="activeTab = 'approved'">
        <div class="stat-info">
          <div class="stat-label">Approved</div>
          <div class="stat-value">{{ stats.approved }}</div>
        </div>
        <v-icon class="stat-icon success">mdi-check-circle</v-icon>
      </div>
      <div class="stat-card" @click="activeTab = 'rejected'">
        <div class="stat-info">
          <div class="stat-label">Rejected</div>
          <div class="stat-value">{{ stats.rejected }}</div>
        </div>
        <v-icon class="stat-icon error">mdi-close-circle</v-icon>
      </div>
      <div class="stat-card" @click="activeTab = 'all'">
        <div class="stat-info">
          <div class="stat-label">Total</div>
          <div class="stat-value">{{ stats.total }}</div>
        </div>
        <v-icon class="stat-icon primary">mdi-file-document-multiple</v-icon>
      </div>
    </div>

    <!-- Content Card -->
    <v-card class="content-card">
      <!-- Tabs -->
      <v-tabs v-model="activeTab" color="primary">
        <v-tab value="all">All ({{ stats.total }})</v-tab>
        <v-tab value="pending">Pending ({{ stats.pending }})</v-tab>
        <v-tab value="approved">Approved ({{ stats.approved }})</v-tab>
        <v-tab value="rejected">Rejected ({{ stats.rejected }})</v-tab>
      </v-tabs>

      <v-divider></v-divider>

      <!-- Search -->
      <div class="search-bar">
        <v-text-field
          v-model="searchQuery"
          prepend-inner-icon="mdi-magnify"
          label="Search resumes..."
          variant="outlined"
          density="compact"
          hide-details
          clearable
        ></v-text-field>
      </div>

      <!-- Data Table -->
      <v-data-table
        :headers="headers"
        :items="filteredResumes"
        :loading="loading"
        :search="searchQuery"
        :items-per-page="10"
      >
        <template v-slot:item.full_name="{ item }">
          <div class="name-cell">
            <div class="name">
              {{ item.first_name }}
              <template v-if="item.middle_name">{{ item.middle_name }}</template>
              {{ item.last_name }}
            </div>
            <div v-if="item.position_applied" class="position">
              {{ item.position_applied }}
            </div>
          </div>
        </template>

        <template v-slot:item.user="{ item }">
          <div class="user-cell">
            <div>{{ item.user?.username }}</div>
            <div class="email">{{ item.user?.email }}</div>
          </div>
        </template>

        <template v-slot:item.original_filename="{ item }">
          <div class="file-cell">
            <v-icon :color="getFileIcon(item.file_type).color" size="20">
              {{ getFileIcon(item.file_type).icon }}
            </v-icon>
            <span>{{ item.original_filename }}</span>
          </div>
        </template>

        <template v-slot:item.file_size="{ item }">
          {{ formatFileSize(item.file_size) }}
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

        <template v-slot:item.created_at="{ item }">
          {{ formatDate(item.created_at) }}
        </template>

        <template v-slot:item.actions="{ item }">
          <div class="actions">
            <v-btn
              icon="mdi-eye"
              size="small"
              variant="text"
              @click="viewResume(item)"
            ></v-btn>
            <v-btn
              icon="mdi-download"
              size="small"
              variant="text"
              @click="downloadResume(item)"
            ></v-btn>
          </div>
        </template>

        <template v-slot:no-data>
          <div class="no-data">
            <v-icon size="48" color="grey-lighten-1">
              mdi-file-document-outline
            </v-icon>
            <p>No resume submissions found</p>
          </div>
        </template>
      </v-data-table>
    </v-card>

    <!-- View Dialog -->
    <v-dialog v-model="viewDialog" max-width="700px">
      <v-card v-if="selectedResume" class="modern-dialog">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper info">
            <v-icon size="24">mdi-file-account</v-icon>
          </div>
          <div>
            <div class="dialog-title">Resume Details</div>
            <div class="dialog-subtitle">Review applicant information</div>
          </div>
        </v-card-title>

        <v-card-text class="dialog-content">
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

          <!-- Personal Information -->
          <div class="detail-row">
            <div class="detail-label">Full Name</div>
            <div class="detail-value">
              {{ selectedResume.first_name }}
              <template v-if="selectedResume.middle_name">{{ selectedResume.middle_name }}</template>
              {{ selectedResume.last_name }}
            </div>
          </div>

          <div class="detail-row">
            <div class="detail-label">Date of Birth</div>
            <div class="detail-value">{{ selectedResume.date_of_birth ? formatDate(selectedResume.date_of_birth) : 'Not provided' }}</div>
          </div>

          <div class="detail-row">
            <div class="detail-label">Gender</div>
            <div class="detail-value">{{ selectedResume.gender ? (selectedResume.gender.charAt(0).toUpperCase() + selectedResume.gender.slice(1)) : 'Not specified' }}</div>
          </div>

          <!-- Contact Information -->
          <div class="detail-row">
            <div class="detail-label">Email</div>
            <div class="detail-value">{{ selectedResume.email || selectedResume.user?.email || 'N/A' }}</div>
          </div>

          <div class="detail-row">
            <div class="detail-label">Phone</div>
            <div class="detail-value">{{ selectedResume.phone || selectedResume.contact_number || 'Not provided' }}</div>
          </div>

          <div class="detail-row">
            <div class="detail-label">Address</div>
            <div class="detail-value">{{ selectedResume.address || 'Not provided' }}</div>
          </div>

          <!-- Professional Information -->
          <div class="detail-row">
            <div class="detail-label">Position Applied</div>
            <div class="detail-value">{{ selectedResume.position_applied || 'Not specified' }}</div>
          </div>

          <div class="detail-row">
            <div class="detail-label">Department</div>
            <div class="detail-value">{{ selectedResume.department_preference || 'Not specified' }}</div>
          </div>

          <div class="detail-row">
            <div class="detail-label">Expected Salary</div>
            <div class="detail-value">{{ selectedResume.expected_salary ? '₱' + selectedResume.expected_salary : 'Not specified' }}</div>
          </div>

          <div class="detail-row">
            <div class="detail-label">Start Date</div>
            <div class="detail-value">{{ selectedResume.availability_date ? formatDate(selectedResume.availability_date) : 'Not specified' }}</div>
          </div>

          <!-- Submission Details -->
          <div class="detail-row">
            <div class="detail-label">Submitted By</div>
            <div class="detail-value">{{ selectedResume.user?.username }}</div>
          </div>

          <div class="detail-row">
            <div class="detail-label">Submitted On</div>
            <div class="detail-value">{{ formatDate(selectedResume.created_at) }}</div>
          </div>

          <div class="detail-row">
            <div class="detail-label">File Name</div>
            <div class="detail-value">{{ selectedResume.original_filename }}</div>
          </div>

          <div class="detail-row">
            <div class="detail-label">File Size</div>
            <div class="detail-value">{{ formatFileSize(selectedResume.file_size) }}</div>
          </div>

          <div v-if="selectedResume.notes" class="detail-row full-width">
            <div class="detail-label">Additional Notes</div>
            <div class="detail-value">{{ selectedResume.notes }}</div>
          </div>

          <v-alert
            v-if="selectedResume.status === 'rejected' && selectedResume.admin_notes"
            type="error"
            variant="tonal"
            density="compact"
            class="mt-3"
          >
            <div class="alert-title">Rejection Reason</div>
            <div>{{ selectedResume.admin_notes }}</div>
          </v-alert>

          <v-alert
            v-if="selectedResume.status === 'pending'"
            type="info"
            variant="tonal"
            density="compact"
            class="mt-3"
          >
            <div>Awaiting admin review</div>
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
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useToast } from "vue-toastification";
import { resumeService } from "@/services/resumeService";
import { formatDateTime as formatDate } from "@/utils/formatters";
import { devLog } from "@/utils/devLog";

const toast = useToast();

const loading = ref(false);
const viewDialog = ref(false);
const resumes = ref([]);
const selectedResume = ref(null);
const activeTab = ref("all");
const searchQuery = ref("");

const headers = [
  { title: "Applicant", value: "full_name", sortable: true },
  { title: "Submitted By", value: "user", sortable: true },
  { title: "File", value: "original_filename", sortable: true },
  { title: "Size", value: "file_size", sortable: true },
  { title: "Status", value: "status", sortable: true },
  { title: "Date", value: "created_at", sortable: true },
  { title: "Actions", value: "actions", sortable: false, align: "center" },
];

const stats = computed(() => ({
  total: resumes.value.length,
  pending: resumes.value.filter((r) => r.status === "pending").length,
  approved: resumes.value.filter((r) => r.status === "approved").length,
  rejected: resumes.value.filter((r) => r.status === "rejected").length,
}));

const filteredResumes = computed(() => {
  if (activeTab.value === "all") {
    return resumes.value;
  }
  return resumes.value.filter((resume) => resume.status === activeTab.value);
});

onMounted(() => {
  fetchResumes();
});

async function fetchResumes() {
  loading.value = true;
  try {
    const response = await resumeService.getAllResumes();

    if (response.success) {
      resumes.value = response.data;
    }
  } catch (error) {
    toast.error("Failed to fetch resume submissions");
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

function getStatusColor(status) {
  switch (status) {
    case "approved":
      return "success";
    case "rejected":
      return "error";
    case "pending":
      return "warning";
    default:
      return "grey";
  }
}

function getAlertType(status) {
  switch (status) {
    case "approved":
      return "success";
    case "rejected":
      return "error";
    case "pending":
      return "warning";
    default:
      return "info";
  }
}

function getFileIcon(fileType) {
  if (!fileType) return { icon: "mdi-file", color: "#9E9E9E" };

  if (fileType.includes("pdf")) {
    return { icon: "mdi-file-pdf-box", color: "#F44336" };
  } else if (fileType.includes("word") || fileType.includes("doc")) {
    return { icon: "mdi-file-word", color: "#2196F3" };
  } else if (fileType.includes("image")) {
    return { icon: "mdi-file-image", color: "#4CAF50" };
  }
  return { icon: "mdi-file", color: "#9E9E9E" };
}

function formatFileSize(bytes) {
  if (!bytes) return "0 B";
  const k = 1024;
  const sizes = ["B", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
}
</script>

<style scoped>
.resume-submissions-page {
  padding: 24px;
  max-width: 1400px;
  margin: 0 auto;
}

/* Header */
.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 24px;
  gap: 16px;
}

.header-left {
  flex: 1;
}

.page-title {
  font-size: 1.75rem;
  font-weight: 600;
  color: #1f2937;
  margin: 0 0 8px 0;
  display: flex;
  align-items: center;
  gap: 12px;
}

.title-icon {
  color: rgb(var(--v-theme-primary));
}

.page-subtitle {
  color: #6b7280;
  font-size: 0.9375rem;
  margin: 0;
}

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.stat-card {
  background: white;
  border-radius: 8px;
  padding: 20px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  transition: all 0.2s ease;
  cursor: pointer;
}

.stat-card:hover {
  box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
  transform: translateY(-2px);
}

.stat-info {
  flex: 1;
}

.stat-label {
  font-size: 0.875rem;
  color: #6b7280;
  margin-bottom: 8px;
  font-weight: 500;
}

.stat-value {
  font-size: 2rem;
  font-weight: 700;
  color: #1f2937;
  line-height: 1;
}

.stat-icon {
  font-size: 2rem;
  opacity: 0.3;
}

.stat-icon.warning { color: rgb(var(--v-theme-warning)); }
.stat-icon.success { color: rgb(var(--v-theme-success)); }
.stat-icon.error { color: rgb(var(--v-theme-error)); }
.stat-icon.primary { color: rgb(var(--v-theme-primary)); }

/* Content Card */
.content-card {
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

/* Search Bar */
.search-bar {
  padding: 16px 16px 0;
}

/* Table Styles */
.name-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.name {
  font-weight: 600;
  color: #1f2937;
}

.position {
  font-size: 0.8125rem;
  color: #6b7280;
}

.user-cell {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.email {
  font-size: 0.8125rem;
  color: #6b7280;
}

.file-cell {
  display: flex;
  align-items: center;
  gap: 8px;
}

.actions {
  display: flex;
  gap: 4px;
  justify-content: center;
}

.no-data {
  text-align: center;
  padding: 48px 16px;
  color: #6b7280;
}

.no-data p {
  margin-top: 12px;
  font-size: 0.9375rem;
}

/* Dialog */
.modern-dialog {
  border-radius: 16px !important;
  overflow: hidden;

  .dialog-header {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px 24px !important;
    background: linear-gradient(135deg, #001f3d 0%, #0a2f4d 100%);
    color: #ffffff;
  }

  .dialog-icon-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: rgba(255, 255, 255, 0.15);
    flex-shrink: 0;

    .v-icon {
      color: #ffffff !important;
    }

    &.info {
      background: rgba(237, 152, 95, 0.2);
    }

    &:hover {
      background: rgba(0, 31, 61, 0.1);
      transform: scale(1.05);
    }
  }

  .dialog-title {
    font-size: 20px;
    font-weight: 700;
    color: #ffffff;
    margin-bottom: 4px;
  }

  .dialog-subtitle {
    font-size: 13px;
    color: rgba(255, 255, 255, 0.8);
  }

  .dialog-content {
    padding: 24px !important;
  }

  .dialog-actions {
    padding: 16px 24px !important;
    background: rgba(0, 31, 61, 0.02);
    border-top: 1px solid rgba(0, 31, 61, 0.08);
    gap: 10px;
  }
}

/* Detail Row (View Dialog) */
.detail-row {
  display: grid;
  grid-template-columns: 140px 1fr;
  gap: 12px;
  padding: 12px 0;
  border-bottom: 1px solid rgba(0, 31, 61, 0.06);

  &:last-child {
    border-bottom: none;
  }

  &.full-width {
    grid-template-columns: 1fr;
  }

  .detail-label {
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: rgba(0, 31, 61, 0.6);
  }

  .detail-value {
    font-size: 14px;
    font-weight: 500;
    color: #001f3d;
  }
}

.alert-title {
  font-size: 12px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 6px;
}

/* Dialog Buttons */
.dialog-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;

  .v-icon {
    flex-shrink: 0;
    color: inherit !important;
  }

  &:hover:not(:disabled) {
    transform: translateY(-1px);
  }

  &:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }

  &.dialog-btn-cancel {
    background: rgba(0, 31, 61, 0.06);
    color: rgba(0, 31, 61, 0.8);

    &:hover:not(:disabled) {
      background: rgba(0, 31, 61, 0.1);
    }
  }

  &.dialog-btn-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);

    .v-icon {
      color: #ffffff !important;
    }

    &:hover:not(:disabled) {
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
    }
  }

  &.dialog-btn-danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);

    .v-icon {
      color: #ffffff !important;
    }

    &:hover:not(:disabled) {
      box-shadow: 0 4px 12px rgba(239, 68, 68, 0.4);
    }
  }
}

/* Responsive */
@media (max-width: 768px) {
  .resume-submissions-page {
    padding: 16px;
  }

  .page-header {
    flex-direction: column;
  }

  .stats-grid {
    grid-template-columns: 1fr 1fr;
    gap: 12px;
  }

  .stat-card {
    padding: 16px;
  }

  .stat-value {
    font-size: 1.5rem;
  }
}

@media (max-width: 480px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }
}
</style>
