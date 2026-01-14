<template>
  <v-container fluid class="pa-6">
    <v-card>
      <v-card-title class="d-flex align-center justify-space-between">
        <div class="d-flex align-center">
          <v-icon class="mr-2">mdi-file-upload-outline</v-icon>
          <span>Import from Biometric Device</span>
        </div>
        <v-btn
          color="info"
          variant="text"
          @click="showTemplateInfo"
          prepend-icon="mdi-information"
        >
          Template Info
        </v-btn>
      </v-card-title>

      <v-divider></v-divider>

      <v-card-text>
        <v-alert type="info" variant="tonal" class="mb-4">
          <p class="font-weight-bold mb-2">Import Process:</p>
          <ol>
            <li><strong>Step 1:</strong> Import Staff Information (employee data)</li>
            <li><strong>Step 2:</strong> Import Punch Records (attendance data)</li>
          </ol>
          <p class="mt-2 text-body-2">
            This ensures staff codes are registered before importing their attendance.
          </p>
        </v-alert>

        <!-- Step 1: Import Staff Information -->
        <v-card variant="outlined" class="mb-4">
          <v-card-title class="bg-primary">
            <v-icon class="mr-2">mdi-numeric-1-circle</v-icon>
            Step 1: Import Staff Information
          </v-card-title>
          <v-card-text class="pt-4">
            <v-alert type="info" variant="tonal" class="mb-4">
              <strong>Required columns:</strong> Staff Code, Name<br />
              <strong>Optional:</strong> User ID, Email, Mobile No, Gender, Entry Date,
              Entry Status, Position, Department<br />
              <strong>Format:</strong> Excel (.xls, .xlsx)
            </v-alert>

            <div
              class="drop-zone"
              :class="{ 'drop-zone-active': isDraggingStaff }"
              @drop.prevent="handleStaffDrop"
              @dragover.prevent="isDraggingStaff = true"
              @dragleave.prevent="isDraggingStaff = false"
              @click="$refs.staffFileInput.click()"
            >
              <v-icon size="64" color="primary">mdi-cloud-upload</v-icon>
              <p class="text-h6 mt-4">
                {{ staffFile ? staffFile.name : 'Drop staff information file here or click to browse' }}
              </p>
              <p class="text-caption text-medium-emphasis">
                Supported formats: .xls, .xlsx
              </p>
              <input
                ref="staffFileInput"
                type="file"
                accept=".xls,.xlsx"
                style="display: none"
                @change="handleStaffFileSelect"
              />
            </div>

            <div v-if="staffFile" class="mt-4 d-flex justify-space-between align-center">
              <div class="d-flex align-center">
                <v-icon color="success" class="mr-2">mdi-file-check</v-icon>
                <span>{{ staffFile.name }} ({{ formatFileSize(staffFile.size) }})</span>
              </div>
              <v-btn
                color="primary"
                @click="importStaffInformation"
                :loading="importingStaff"
                :disabled="!staffFile"
              >
                Import Staff
              </v-btn>
            </div>

            <!-- Staff Import Results -->
            <v-alert
              v-if="staffImportResult"
              :type="staffImportResult.success ? 'success' : 'error'"
              variant="tonal"
              class="mt-4"
            >
              <p class="font-weight-bold">Import Results:</p>
              <ul>
                <li>Imported: {{ staffImportResult.imported }}</li>
                <li>Updated: {{ staffImportResult.updated }}</li>
                <li>Skipped: {{ staffImportResult.skipped }}</li>
                <li>Failed: {{ staffImportResult.failed }}</li>
              </ul>
              <div v-if="staffImportResult.errors && staffImportResult.errors.length > 0" class="mt-2">
                <p class="font-weight-bold">Errors:</p>
                <ul>
                  <li v-for="(error, index) in staffImportResult.errors.slice(0, 10)" :key="index">
                    Row {{ error.row }}: {{ error.error }}
                  </li>
                  <li v-if="staffImportResult.errors.length > 10">
                    ... and {{ staffImportResult.errors.length - 10 }} more errors
                  </li>
                </ul>
              </div>
            </v-alert>
          </v-card-text>
        </v-card>

        <!-- Step 2: Import Punch Records -->
        <v-card variant="outlined">
          <v-card-title class="bg-secondary">
            <v-icon class="mr-2">mdi-numeric-2-circle</v-icon>
            Step 2: Import Punch Records (Attendance)
          </v-card-title>
          <v-card-text class="pt-4">
            <v-alert type="warning" variant="tonal" class="mb-4">
              <strong>Important:</strong> Complete Step 1 first to ensure all staff codes are registered!
            </v-alert>

            <v-alert type="info" variant="tonal" class="mb-4">
              <strong>Required columns:</strong> Staff Code, Name, Date columns (MM-DD format)<br />
              <strong>Time format:</strong> HH:MM (e.g., 08:30), multiple times per day<br />
              <strong>Format:</strong> Excel (.xls, .xlsx)
            </v-alert>

            <!-- Year and Month Selection -->
            <v-row class="mb-4">
              <v-col cols="12" md="6">
                <v-text-field
                  v-model.number="punchYear"
                  label="Year *"
                  type="number"
                  :min="2020"
                  :max="2100"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-calendar"
                ></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-select
                  v-model="punchMonth"
                  :items="months"
                  label="Month (optional)"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-calendar-month"
                  clearable
                ></v-select>
              </v-col>
            </v-row>

            <div
              class="drop-zone"
              :class="{ 'drop-zone-active': isDraggingPunch }"
              @drop.prevent="handlePunchDrop"
              @dragover.prevent="isDraggingPunch = true"
              @dragleave.prevent="isDraggingPunch = false"
              @click="$refs.punchFileInput.click()"
            >
              <v-icon size="64" color="secondary">mdi-cloud-upload</v-icon>
              <p class="text-h6 mt-4">
                {{ punchFile ? punchFile.name : 'Drop punch records file here or click to browse' }}
              </p>
              <p class="text-caption text-medium-emphasis">
                Supported formats: .xls, .xlsx
              </p>
              <input
                ref="punchFileInput"
                type="file"
                accept=".xls,.xlsx"
                style="display: none"
                @change="handlePunchFileSelect"
              />
            </div>

            <div v-if="punchFile" class="mt-4 d-flex justify-space-between align-center">
              <div class="d-flex align-center">
                <v-icon color="success" class="mr-2">mdi-file-check</v-icon>
                <span>{{ punchFile.name }} ({{ formatFileSize(punchFile.size) }})</span>
              </div>
              <v-btn
                color="secondary"
                @click="importPunchRecords"
                :loading="importingPunch"
                :disabled="!punchFile || !punchYear"
              >
                Import Punch Records
              </v-btn>
            </div>

            <!-- Punch Import Results -->
            <v-alert
              v-if="punchImportResult"
              :type="punchImportResult.success ? 'success' : 'error'"
              variant="tonal"
              class="mt-4"
            >
              <p class="font-weight-bold">Import Results:</p>
              <ul>
                <li>Imported: {{ punchImportResult.imported }}</li>
                <li>Updated: {{ punchImportResult.updated }}</li>
                <li>Skipped: {{ punchImportResult.skipped }}</li>
                <li>Failed: {{ punchImportResult.failed }}</li>
              </ul>
              <div v-if="punchImportResult.errors && punchImportResult.errors.length > 0" class="mt-2">
                <p class="font-weight-bold">Errors:</p>
                <ul>
                  <li v-for="(error, index) in punchImportResult.errors.slice(0, 10)" :key="index">
                    Row {{ error.row }}: {{ error.error }}
                  </li>
                  <li v-if="punchImportResult.errors.length > 10">
                    ... and {{ punchImportResult.errors.length - 10 }} more errors
                  </li>
                </ul>
              </div>
            </v-alert>
          </v-card-text>
        </v-card>
      </v-card-text>
    </v-card>

    <!-- Template Info Dialog -->
    <v-dialog v-model="templateDialog" max-width="800">
      <v-card>
        <v-card-title class="bg-info">
          <v-icon class="mr-2">mdi-information</v-icon>
          Import Template Information
        </v-card-title>
        <v-card-text class="pt-4">
          <v-expansion-panels>
            <v-expansion-panel>
              <v-expansion-panel-title>
                <v-icon class="mr-2">mdi-account-group</v-icon>
                Staff Information Template
              </v-expansion-panel-title>
              <v-expansion-panel-text>
                <p><strong>Description:</strong> Import staff/employee information from biometric device</p>
                <p><strong>Required Columns:</strong></p>
                <ul>
                  <li>Staff Code</li>
                  <li>Name</li>
                </ul>
                <p><strong>Optional Columns:</strong></p>
                <ul>
                  <li>User ID</li>
                  <li>Email</li>
                  <li>Mobile No</li>
                  <li>Gender</li>
                  <li>Entry Date</li>
                  <li>Entry Status (Official/Regular/Probationary/Contractual)</li>
                  <li>Position</li>
                  <li>Department</li>
                </ul>
              </v-expansion-panel-text>
            </v-expansion-panel>

            <v-expansion-panel>
              <v-expansion-panel-title>
                <v-icon class="mr-2">mdi-clock-outline</v-icon>
                Punch Records Template
              </v-expansion-panel-title>
              <v-expansion-panel-text>
                <p><strong>Description:</strong> Import attendance punch records with date-based columns</p>
                <p><strong>Required Columns:</strong></p>
                <ul>
                  <li>Staff Code</li>
                  <li>Name</li>
                  <li>Date columns in MM-DD format (e.g., 12-01, 12-02)</li>
                </ul>
                <p><strong>Time Format:</strong> HH:MM (e.g., 08:30, 17:00)</p>
                <p><strong>Multiple Times:</strong> Separate by newlines within the cell</p>
                <p><strong>Parameters:</strong></p>
                <ul>
                  <li><strong>Year:</strong> Year of the records (required)</li>
                  <li><strong>Month:</strong> Optional - Override the month from date columns</li>
                </ul>
              </v-expansion-panel-text>
            </v-expansion-panel>
          </v-expansion-panels>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn color="primary" @click="templateDialog = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-container>
</template>

<script setup>
import { ref } from 'vue';
import api from '@/services/api';
import { useToast } from 'vue-toastification';

const toast = useToast();

// Staff Information Import
const staffFile = ref(null);
const isDraggingStaff = ref(false);
const importingStaff = ref(false);
const staffImportResult = ref(null);

// Punch Records Import
const punchFile = ref(null);
const isDraggingPunch = ref(false);
const importingPunch = ref(false);
const punchImportResult = ref(null);
const punchYear = ref(new Date().getFullYear());
const punchMonth = ref(null);

// Template Dialog
const templateDialog = ref(false);

// Months
const months = [
  { title: 'January', value: 1 },
  { title: 'February', value: 2 },
  { title: 'March', value: 3 },
  { title: 'April', value: 4 },
  { title: 'May', value: 5 },
  { title: 'June', value: 6 },
  { title: 'July', value: 7 },
  { title: 'August', value: 8 },
  { title: 'September', value: 9 },
  { title: 'October', value: 10 },
  { title: 'November', value: 11 },
  { title: 'December', value: 12 },
];

// Staff Information Handlers
const handleStaffFileSelect = (event) => {
  const file = event.target.files[0];
  if (file) {
    staffFile.value = file;
    staffImportResult.value = null;
  }
};

const handleStaffDrop = (event) => {
  isDraggingStaff.value = false;
  const file = event.dataTransfer.files[0];
  if (file && (file.name.endsWith('.xls') || file.name.endsWith('.xlsx'))) {
    staffFile.value = file;
    staffImportResult.value = null;
  } else {
    toast.error('Please upload a valid Excel file (.xls or .xlsx)');
  }
};

const importStaffInformation = async () => {
  if (!staffFile.value) return;

  importingStaff.value = true;
  staffImportResult.value = null;

  try {
    const formData = new FormData();
    formData.append('file', staffFile.value);

    const response = await api.post('/biometric/import-staff', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    staffImportResult.value = {
      success: true,
      imported: response.data.imported,
      updated: response.data.updated,
      skipped: response.data.skipped,
      failed: response.data.failed,
      errors: response.data.errors || [],
    };

    toast.success(`Staff information imported successfully! ${response.data.imported} employees created, ${response.data.updated} updated.`);
  } catch (error) {
    toast.error(error.response?.data?.message || 'Failed to import staff information');
    staffImportResult.value = {
      success: false,
      imported: 0,
      updated: 0,
      skipped: 0,
      failed: 0,
      errors: [{ row: 0, error: error.response?.data?.message || error.message }],
    };
  } finally {
    importingStaff.value = false;
  }
};

// Punch Records Handlers
const handlePunchFileSelect = (event) => {
  const file = event.target.files[0];
  if (file) {
    punchFile.value = file;
    punchImportResult.value = null;
  }
};

const handlePunchDrop = (event) => {
  isDraggingPunch.value = false;
  const file = event.dataTransfer.files[0];
  if (file && (file.name.endsWith('.xls') || file.name.endsWith('.xlsx'))) {
    punchFile.value = file;
    punchImportResult.value = null;
  } else {
    toast.error('Please upload a valid Excel file (.xls or .xlsx)');
  }
};

const importPunchRecords = async () => {
  if (!punchFile.value || !punchYear.value) return;

  importingPunch.value = true;
  punchImportResult.value = null;

  try {
    const formData = new FormData();
    formData.append('file', punchFile.value);
    formData.append('year', punchYear.value);
    if (punchMonth.value) {
      formData.append('month', punchMonth.value);
    }

    const response = await api.post('/biometric/import-punch-records', formData, {
      headers: { 'Content-Type': 'multipart/form-data' },
    });

    punchImportResult.value = {
      success: true,
      imported: response.data.imported,
      updated: response.data.updated,
      skipped: response.data.skipped,
      failed: response.data.failed,
      errors: response.data.errors || [],
    };

    toast.success(`Punch records imported successfully! ${response.data.imported} records created, ${response.data.updated} updated.`);
  } catch (error) {
    toast.error(error.response?.data?.message || 'Failed to import punch records');
    punchImportResult.value = {
      success: false,
      imported: 0,
      updated: 0,
      skipped: 0,
      failed: 0,
      errors: [{ row: 0, error: error.response?.data?.message || error.message }],
    };
  } finally {
    importingPunch.value = false;
  }
};

// Utility Functions
const formatFileSize = (bytes) => {
  if (bytes < 1024) return bytes + ' B';
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB';
  return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
};

const showTemplateInfo = () => {
  templateDialog.value = true;
};
</script>

<style scoped>
.drop-zone {
  border: 2px dashed #ccc;
  border-radius: 8px;
  padding: 40px;
  text-align: center;
  cursor: pointer;
  transition: all 0.3s ease;
}

.drop-zone:hover,
.drop-zone-active {
  border-color: #1976d2;
  background-color: rgba(25, 118, 210, 0.05);
}
</style>
