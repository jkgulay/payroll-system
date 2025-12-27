<template>
  <v-container fluid>
    <v-row>
      <v-col cols="12">
        <v-card>
          <v-card-title class="d-flex align-center">
            <v-icon class="mr-2">mdi-file-check-outline</v-icon>
            <span>Resume Review (Admin)</span>
            <v-spacer></v-spacer>
            <v-btn
              icon
              @click="fetchResumes"
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
              <v-col cols="12" sm="3">
                <v-card color="primary" dark>
                  <v-card-text>
                    <div class="text-h5">{{ stats.total }}</div>
                    <div>Total</div>
                  </v-card-text>
                </v-card>
              </v-col>
            </v-row>

            <!-- Filters -->
            <v-row class="mb-4">
              <v-col cols="12" sm="6" md="4">
                <v-select
                  v-model="statusFilter"
                  :items="statusOptions"
                  label="Filter by Status"
                  dense
                  outlined
                  @update:modelValue="fetchResumes"
                ></v-select>
              </v-col>
            </v-row>

            <!-- Data Table -->
            <v-data-table
              :headers="headers"
              :items="resumes"
              :loading="loading"
              class="elevation-1"
            >
              <template v-slot:item.user="{ item }">
                <div>
                  <strong>{{ item.user?.username }}</strong><br>
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
                {{ formatDate(item.created_at) }}
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
                  icon
                  size="small"
                  @click="downloadResume(item)"
                  title="Download"
                >
                  <v-icon>mdi-download</v-icon>
                </v-btn>
                <v-btn
                  v-if="item.status === 'pending'"
                  icon
                  size="small"
                  color="success"
                  @click="openApproveDialog(item)"
                  title="Approve"
                >
                  <v-icon>mdi-check</v-icon>
                </v-btn>
                <v-btn
                  v-if="item.status === 'pending'"
                  icon
                  size="small"
                  color="error"
                  @click="openRejectDialog(item)"
                  title="Reject"
                >
                  <v-icon>mdi-close</v-icon>
                </v-btn>
              </template>
            </v-data-table>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- View Dialog -->
    <v-dialog v-model="viewDialog" max-width="900">
      <v-card v-if="selectedResume">
        <v-card-title>
          <span class="text-h5">Resume Details</span>
          <v-spacer></v-spacer>
          <v-btn icon @click="viewDialog = false">
            <v-icon>mdi-close</v-icon>
          </v-btn>
        </v-card-title>
        <v-card-text>
          <v-row>
            <v-col cols="12" sm="6">
              <strong>Uploaded By:</strong>
              <div>{{ selectedResume.user?.username }} ({{ selectedResume.user?.email }})</div>
            </v-col>
            <v-col cols="12" sm="6">
              <strong>Status:</strong>
              <v-chip :color="getStatusColor(selectedResume.status)" size="small" dark class="ml-2">
                {{ selectedResume.status.toUpperCase() }}
              </v-chip>
            </v-col>
            <v-col cols="12" sm="6">
              <strong>Filename:</strong> {{ selectedResume.original_filename }}
            </v-col>
            <v-col cols="12" sm="6">
              <strong>File Type:</strong> {{ selectedResume.file_type.toUpperCase() }}
            </v-col>
            <v-col cols="12" sm="6">
              <strong>File Size:</strong> {{ formatFileSize(selectedResume.file_size) }}
            </v-col>
            <v-col cols="12" sm="6">
              <strong>Uploaded:</strong> {{ formatDate(selectedResume.created_at) }}
            </v-col>
            <v-col v-if="selectedResume.reviewed_at" cols="12" sm="6">
              <strong>Reviewed:</strong> {{ formatDate(selectedResume.reviewed_at) }}
            </v-col>
            <v-col v-if="selectedResume.reviewer" cols="12" sm="6">
              <strong>Reviewed By:</strong> {{ selectedResume.reviewer.username }}
            </v-col>
            <v-col v-if="selectedResume.admin_notes" cols="12">
              <v-alert :type="selectedResume.status === 'approved' ? 'success' : 'error'">
                <strong>Admin Notes:</strong><br>
                {{ selectedResume.admin_notes }}
              </v-alert>
            </v-col>
          </v-row>

          <!-- Preview for images -->
          <div v-if="['jpg', 'jpeg', 'png'].includes(selectedResume.file_type.toLowerCase())" class="mt-4">
            <v-divider class="mb-4"></v-divider>
            <strong>Preview:</strong>
            <v-img
              :src="selectedResume.file_url"
              max-height="600"
              contain
              class="mt-2"
            ></v-img>
          </div>

          <!-- PDF Preview -->
          <div v-if="selectedResume.file_type.toLowerCase() === 'pdf'" class="mt-4">
            <v-divider class="mb-4"></v-divider>
            <strong>PDF Preview:</strong>
            <iframe
              :src="selectedResume.file_url"
              width="100%"
              height="600px"
              class="mt-2"
            ></iframe>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn
            v-if="selectedResume.status === 'pending'"
            color="success"
            @click="openApproveDialog(selectedResume)"
          >
            <v-icon left>mdi-check</v-icon>
            Approve
          </v-btn>
          <v-btn
            v-if="selectedResume.status === 'pending'"
            color="error"
            @click="openRejectDialog(selectedResume)"
          >
            <v-icon left>mdi-close</v-icon>
            Reject
          </v-btn>
          <v-btn color="primary" @click="downloadResume(selectedResume)">
            <v-icon left>mdi-download</v-icon>
            Download
          </v-btn>
          <v-btn text @click="viewDialog = false">Close</v-btn>
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
            <strong>{{ resumeToReview?.user?.username }}</strong>?
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
          <v-btn
            color="success"
            @click="approveResume"
            :loading="processing"
          >
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
            <strong>{{ resumeToReview?.user?.username }}</strong>?
          </p>
          <v-textarea
            v-model="adminNotes"
            label="Reason for Rejection (Required)"
            outlined
            rows="3"
            :rules="[v => !!v || 'Rejection reason is required']"
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
  </v-container>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { useToast } from 'vue-toastification';
import { resumeService } from '@/services/resumeService';
import { format } from 'date-fns';

const toast = useToast();

const loading = ref(false);
const processing = ref(false);
const viewDialog = ref(false);
const approveDialog = ref(false);
const rejectDialog = ref(false);
const resumes = ref([]);
const selectedResume = ref(null);
const resumeToReview = ref(null);
const adminNotes = ref('');
const statusFilter = ref('all');

const statusOptions = [
  { title: 'All', value: 'all' },
  { title: 'Pending', value: 'pending' },
  { title: 'Approved', value: 'approved' },
  { title: 'Rejected', value: 'rejected' },
];

const headers = [
  { title: 'User', value: 'user', sortable: true },
  { title: 'Filename', value: 'original_filename', sortable: true },
  { title: 'Type', value: 'file_type', sortable: true },
  { title: 'Size', value: 'file_size', sortable: true },
  { title: 'Status', value: 'status', sortable: true },
  { title: 'Uploaded', value: 'created_at', sortable: true },
  { title: 'Actions', value: 'actions', sortable: false, align: 'center' },
];

const stats = computed(() => ({
  total: resumes.value.length,
  pending: resumes.value.filter((r) => r.status === 'pending').length,
  approved: resumes.value.filter((r) => r.status === 'approved').length,
  rejected: resumes.value.filter((r) => r.status === 'rejected').length,
}));

onMounted(() => {
  fetchResumes();
});

async function fetchResumes() {
  loading.value = true;
  try {
    const params = statusFilter.value !== 'all' ? { status: statusFilter.value } : {};
    const response = await resumeService.getAllResumes(params);
    
    if (response.success) {
      resumes.value = response.data;
    }
  } catch (error) {
    toast.error('Failed to fetch resumes');
    console.error(error);
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
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', resume.original_filename);
    document.body.appendChild(link);
    link.click();
    link.remove();
    
    toast.success('Resume downloaded successfully');
  } catch (error) {
    toast.error('Failed to download resume');
    console.error(error);
  }
}

function openApproveDialog(resume) {
  resumeToReview.value = resume;
  adminNotes.value = '';
  approveDialog.value = true;
  viewDialog.value = false;
}

function openRejectDialog(resume) {
  resumeToReview.value = resume;
  adminNotes.value = '';
  rejectDialog.value = true;
  viewDialog.value = false;
}

async function approveResume() {
  if (!resumeToReview.value) return;

  processing.value = true;
  try {
    const response = await resumeService.approveResume(
      resumeToReview.value.id,
      adminNotes.value
    );
    
    if (response.success) {
      toast.success(response.message);
      approveDialog.value = false;
      adminNotes.value = '';
      resumeToReview.value = null;
      fetchResumes();
    }
  } catch (error) {
    toast.error(error.response?.data?.message || 'Failed to approve resume');
    console.error(error);
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
      adminNotes.value
    );
    
    if (response.success) {
      toast.success(response.message);
      rejectDialog.value = false;
      adminNotes.value = '';
      resumeToReview.value = null;
      fetchResumes();
    }
  } catch (error) {
    toast.error(error.response?.data?.message || 'Failed to reject resume');
    console.error(error);
  } finally {
    processing.value = false;
  }
}

function getStatusColor(status) {
  const colors = {
    pending: 'warning',
    approved: 'success',
    rejected: 'error',
  };
  return colors[status] || 'grey';
}

function getFileIcon(fileType) {
  const icons = {
    pdf: { icon: 'mdi-file-pdf-box', color: 'error' },
    doc: { icon: 'mdi-file-word-box', color: 'primary' },
    docx: { icon: 'mdi-file-word-box', color: 'primary' },
    jpg: { icon: 'mdi-file-image', color: 'success' },
    jpeg: { icon: 'mdi-file-image', color: 'success' },
    png: { icon: 'mdi-file-image', color: 'success' },
  };
  return icons[fileType.toLowerCase()] || { icon: 'mdi-file', color: 'grey' };
}

function formatFileSize(bytes) {
  if (bytes === 0) return '0 Bytes';
  const k = 1024;
  const sizes = ['Bytes', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return Math.round(bytes / Math.pow(k, i) * 100) / 100 + ' ' + sizes[i];
}

function formatDate(dateString) {
  return format(new Date(dateString), 'MMM dd, yyyy hh:mm a');
}
</script>
