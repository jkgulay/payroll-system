<template>
  <v-container fluid>
    <v-row>
      <v-col cols="12">
        <v-card>
          <v-card-title class="d-flex align-center">
            <v-icon class="mr-2">mdi-file-document-outline</v-icon>
            <span>My Resumes</span>
            <v-spacer></v-spacer>
            <v-btn color="primary" @click="uploadDialog = true">
              <v-icon left>mdi-upload</v-icon>
              Upload Resume
            </v-btn>
          </v-card-title>

          <v-card-text>
            <!-- Statistics Cards -->
            <v-row class="mb-4">
              <v-col cols="12" sm="4">
                <v-card color="primary" dark>
                  <v-card-text>
                    <div class="text-h5">{{ stats.total }}</div>
                    <div>Total Resumes</div>
                  </v-card-text>
                </v-card>
              </v-col>
              <v-col cols="12" sm="4">
                <v-card color="success" dark>
                  <v-card-text>
                    <div class="text-h5">{{ stats.approved }}</div>
                    <div>Approved</div>
                  </v-card-text>
                </v-card>
              </v-col>
              <v-col cols="12" sm="4">
                <v-card color="warning" dark>
                  <v-card-text>
                    <div class="text-h5">{{ stats.pending }}</div>
                    <div>Pending</div>
                  </v-card-text>
                </v-card>
              </v-col>
            </v-row>

            <!-- Tabs -->
            <v-tabs v-model="tab" class="mb-4">
              <v-tab value="all">All Resumes</v-tab>
              <v-tab value="approved">Approved</v-tab>
              <v-tab value="pending">Pending</v-tab>
              <v-tab value="rejected">Rejected</v-tab>
            </v-tabs>

            <!-- Data Table -->
            <v-data-table
              :headers="headers"
              :items="filteredResumes"
              :loading="loading"
              class="elevation-1"
            >
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

              <template v-slot:item.reviewed_at="{ item }">
                {{ item.reviewed_at ? formatDate(item.reviewed_at) : 'N/A' }}
              </template>

              <template v-slot:item.actions="{ item }">
                <v-btn
                  icon
                  size="small"
                  @click="viewResume(item)"
                  title="View"
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
                  color="error"
                  @click="confirmDelete(item)"
                  title="Delete"
                >
                  <v-icon>mdi-delete</v-icon>
                </v-btn>
              </template>
            </v-data-table>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Upload Dialog -->
    <v-dialog v-model="uploadDialog" max-width="600">
      <v-card>
        <v-card-title>
          <span class="text-h5">Upload Resume</span>
        </v-card-title>
        <v-card-text>
          <v-file-input
            v-model="selectedFile"
            label="Select Resume File"
            accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
            prepend-icon="mdi-file-document"
            show-size
            :rules="fileRules"
            :loading="uploading"
            :disabled="uploading"
          >
            <template v-slot:message>
              Accepted formats: PDF, DOC, DOCX, JPG, PNG (Max 10MB)
            </template>
          </v-file-input>

          <v-alert v-if="uploadError" type="error" class="mt-3" dismissible>
            {{ uploadError }}
          </v-alert>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn text @click="closeUploadDialog" :disabled="uploading">
            Cancel
          </v-btn>
          <v-btn
            color="primary"
            @click="uploadResume"
            :loading="uploading"
            :disabled="!selectedFile || uploading"
          >
            Upload
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- View Dialog -->
    <v-dialog v-model="viewDialog" max-width="800">
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
              <strong>Filename:</strong> {{ selectedResume.original_filename }}
            </v-col>
            <v-col cols="12" sm="6">
              <strong>Status:</strong>
              <v-chip :color="getStatusColor(selectedResume.status)" size="small" dark class="ml-2">
                {{ selectedResume.status.toUpperCase() }}
              </v-chip>
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
            <v-col cols="12" sm="6">
              <strong>Reviewed:</strong> {{ selectedResume.reviewed_at ? formatDate(selectedResume.reviewed_at) : 'Not yet reviewed' }}
            </v-col>
            <v-col v-if="selectedResume.reviewer" cols="12">
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
              max-height="500"
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
              height="500px"
              class="mt-2"
            ></iframe>
          </div>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn color="primary" @click="downloadResume(selectedResume)">
            <v-icon left>mdi-download</v-icon>
            Download
          </v-btn>
          <v-btn text @click="viewDialog = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete Confirmation Dialog -->
    <v-dialog v-model="deleteDialog" max-width="500">
      <v-card>
        <v-card-title>Confirm Delete</v-card-title>
        <v-card-text>
          Are you sure you want to delete this resume? This action cannot be undone.
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn text @click="deleteDialog = false">Cancel</v-btn>
          <v-btn color="error" @click="deleteResume" :loading="deleting">
            Delete
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
const uploading = ref(false);
const deleting = ref(false);
const uploadDialog = ref(false);
const viewDialog = ref(false);
const deleteDialog = ref(false);
const tab = ref('all');
const resumes = ref([]);
const selectedFile = ref(null);
const selectedResume = ref(null);
const resumeToDelete = ref(null);
const uploadError = ref('');

const headers = [
  { title: 'Filename', value: 'original_filename', sortable: true },
  { title: 'Type', value: 'file_type', sortable: true },
  { title: 'Size', value: 'file_size', sortable: true },
  { title: 'Status', value: 'status', sortable: true },
  { title: 'Uploaded', value: 'created_at', sortable: true },
  { title: 'Reviewed', value: 'reviewed_at', sortable: true },
  { title: 'Actions', value: 'actions', sortable: false, align: 'center' },
];

const fileRules = [
  (v) => !!v || 'File is required',
  (v) => !v || v.size <= 10485760 || 'File size must be less than 10MB',
];

const stats = computed(() => ({
  total: resumes.value.length,
  approved: resumes.value.filter((r) => r.status === 'approved').length,
  pending: resumes.value.filter((r) => r.status === 'pending').length,
  rejected: resumes.value.filter((r) => r.status === 'rejected').length,
}));

const filteredResumes = computed(() => {
  if (tab.value === 'all') return resumes.value;
  return resumes.value.filter((r) => r.status === tab.value);
});

onMounted(() => {
  fetchResumes();
});

async function fetchResumes() {
  loading.value = true;
  try {
    const response = await resumeService.getMyResumes();
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

async function uploadResume() {
  if (!selectedFile.value) return;

  uploadError.value = '';
  uploading.value = true;

  try {
    const formData = new FormData();
    formData.append('resume', selectedFile.value);

    const response = await resumeService.uploadResume(formData);
    
    if (response.success) {
      toast.success(response.message);
      closeUploadDialog();
      fetchResumes();
    }
  } catch (error) {
    uploadError.value = error.response?.data?.message || 'Failed to upload resume';
    toast.error(uploadError.value);
    console.error(error);
  } finally {
    uploading.value = false;
  }
}

function closeUploadDialog() {
  uploadDialog.value = false;
  selectedFile.value = null;
  uploadError.value = '';
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

function confirmDelete(resume) {
  resumeToDelete.value = resume;
  deleteDialog.value = true;
}

async function deleteResume() {
  if (!resumeToDelete.value) return;

  deleting.value = true;
  try {
    const response = await resumeService.deleteResume(resumeToDelete.value.id);
    
    if (response.success) {
      toast.success(response.message);
      deleteDialog.value = false;
      resumeToDelete.value = null;
      fetchResumes();
    }
  } catch (error) {
    toast.error(error.response?.data?.message || 'Failed to delete resume');
    console.error(error);
  } finally {
    deleting.value = false;
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
