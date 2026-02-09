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
            <div class="name">{{ item.first_name }} {{ item.last_name }}</div>
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
      <v-card v-if="selectedResume">
        <v-card-title class="d-flex align-center">
          <v-icon class="mr-2">mdi-file-document</v-icon>
          Resume Details
          <v-spacer></v-spacer>
          <v-chip :color="getStatusColor(selectedResume.status)" size="small">
            {{ selectedResume.status }}
          </v-chip>
        </v-card-title>

        <v-divider></v-divider>

        <v-card-text class="pa-6">
          <!-- Status -->
          <v-alert
            :type="getAlertType(selectedResume.status)"
            variant="tonal"
            class="mb-4"
          >
            <strong>Status:</strong> {{ selectedResume.status.toUpperCase() }}
            <div v-if="selectedResume.status === 'pending'" class="mt-1">
              Awaiting admin review
            </div>
            <div v-if="selectedResume.status === 'rejected' && selectedResume.admin_notes" class="mt-1">
              <strong>Reason:</strong> {{ selectedResume.admin_notes }}
            </div>
          </v-alert>

          <!-- Applicant Info -->
          <div class="info-section">
            <h3 class="section-title">Applicant Information</h3>
            <v-row dense>
              <v-col cols="6">
                <div class="info-label">Name</div>
                <div class="info-value">
                  {{ selectedResume.first_name }} {{ selectedResume.last_name }}
                </div>
              </v-col>
              <v-col cols="6">
                <div class="info-label">Position</div>
                <div class="info-value">
                  {{ selectedResume.position_applied || 'Not specified' }}
                </div>
              </v-col>
              <v-col cols="6">
                <div class="info-label">Email</div>
                <div class="info-value">
                  {{ selectedResume.email || selectedResume.user?.email || 'N/A' }}
                </div>
              </v-col>
              <v-col cols="6">
                <div class="info-label">Contact</div>
                <div class="info-value">
                  {{ selectedResume.contact_number || 'Not provided' }}
                </div>
              </v-col>
            </v-row>
          </div>

          <!-- Submission Info -->
          <div class="info-section">
            <h3 class="section-title">Submission Details</h3>
            <v-row dense>
              <v-col cols="6">
                <div class="info-label">Submitted By</div>
                <div class="info-value">{{ selectedResume.user?.username }}</div>
              </v-col>
              <v-col cols="6">
                <div class="info-label">Date</div>
                <div class="info-value">{{ formatDate(selectedResume.created_at) }}</div>
              </v-col>
              <v-col cols="6">
                <div class="info-label">File</div>
                <div class="info-value">{{ selectedResume.original_filename }}</div>
              </v-col>
              <v-col cols="6">
                <div class="info-label">Size</div>
                <div class="info-value">{{ formatFileSize(selectedResume.file_size) }}</div>
              </v-col>
            </v-row>
          </div>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn
            color="primary"
            variant="flat"
            prepend-icon="mdi-download"
            @click="downloadResume(selectedResume)"
          >
            Download
          </v-btn>
          <v-btn variant="text" @click="viewDialog = false">Close</v-btn>
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
.info-section {
  margin-bottom: 24px;
}

.section-title {
  font-size: 1rem;
  font-weight: 600;
  color: #1f2937;
  margin-bottom: 16px;
}

.info-label {
  font-size: 0.75rem;
  color: #6b7280;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
  font-weight: 600;
}

.info-value {
  font-size: 0.9375rem;
  color: #1f2937;
  font-weight: 500;
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
