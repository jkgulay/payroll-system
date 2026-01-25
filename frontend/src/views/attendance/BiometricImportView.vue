<template>
  <div class="biometric-import-view">
    <!-- Compact Modern Header -->
    <div class="page-header-compact">
      <div class="header-left">
        <div class="page-icon-badge">
          <v-icon size="20">mdi-file-upload</v-icon>
        </div>
        <div>
          <h1 class="page-title">Biometric Import</h1>
          <p class="page-subtitle">
            Import staff information and attendance records
          </p>
        </div>
      </div>
      <button class="action-btn-compact" @click="showTemplateInfo">
        <v-icon size="18">mdi-information-outline</v-icon>
        <span>Template Info</span>
      </button>
    </div>

    <!-- Import Steps Grid -->
    <v-row class="import-steps-grid">
      <!-- Step 1: Staff Information -->
      <v-col cols="12" lg="6">
        <div class="import-step-card">
          <div class="step-header">
            <div class="step-number">1</div>
            <div class="step-header-content">
              <h3 class="step-title">Staff Information</h3>
              <p class="step-subtitle">
                Import employee data from biometric device
              </p>
            </div>
          </div>

          <!-- Collapsible Info Section -->
          <v-expansion-panels variant="accordion" class="info-accordion">
            <v-expansion-panel>
              <v-expansion-panel-title class="info-panel-title">
                <v-icon size="18" class="mr-2">mdi-information</v-icon>
                <span class="text-body-2">File Requirements</span>
              </v-expansion-panel-title>
              <v-expansion-panel-text class="info-panel-text">
                <div class="info-grid">
                  <div class="info-item">
                    <div class="info-label">Required</div>
                    <div class="info-value">Staff Code, Name</div>
                  </div>
                  <div class="info-item">
                    <div class="info-label">Optional</div>
                    <div class="info-value">
                      Email, Mobile, Gender, Position, Department
                    </div>
                  </div>
                  <div class="info-item">
                    <div class="info-label">Format</div>
                    <div class="info-value">Excel (.xls, .xlsx)</div>
                  </div>
                </div>
              </v-expansion-panel-text>
            </v-expansion-panel>
          </v-expansion-panels>

          <!-- Default Values - Compact -->
          <div class="default-values-section">
            <div class="section-label">
              <v-icon size="16">mdi-cog-outline</v-icon>
              <span>Default Values (Optional)</span>
            </div>
            <v-row dense>
              <v-col cols="12" md="6">
                <v-autocomplete
                  v-model="defaultPosition"
                  :items="positionOptions"
                  label="Default Position"
                  placeholder="Select position"
                  density="compact"
                  variant="outlined"
                  clearable
                  hide-details
                >
                  <template v-slot:prepend-inner>
                    <v-icon size="18">mdi-briefcase</v-icon>
                  </template>
                </v-autocomplete>
              </v-col>
              <v-col cols="12" md="6">
                <v-autocomplete
                  v-model="defaultProject"
                  :items="projectOptions"
                  label="Default Project"
                  placeholder="Select project"
                  density="compact"
                  variant="outlined"
                  clearable
                  hide-details
                >
                  <template v-slot:prepend-inner>
                    <v-icon size="18">mdi-folder-outline</v-icon>
                  </template>
                </v-autocomplete>
              </v-col>
            </v-row>
          </div>

          <!-- Compact Drop Zone -->
          <div
            class="drop-zone-compact"
            :class="{ 'drop-zone-active': isDraggingStaff }"
            @drop.prevent="handleStaffDrop"
            @dragover.prevent="isDraggingStaff = true"
            @dragleave.prevent="isDraggingStaff = false"
            @click="$refs.staffFileInput.click()"
          >
            <v-icon size="48" color="primary">mdi-cloud-upload-outline</v-icon>
            <div class="drop-zone-text">
              <p class="drop-zone-title">
                {{
                  staffFile ? staffFile.name : "Drop file or click to browse"
                }}
              </p>
              <p class="drop-zone-hint">Excel files only (.xlsx, .xls)</p>
            </div>
            <input
              ref="staffFileInput"
              type="file"
              accept=".xls,.xlsx"
              style="display: none"
              @change="handleStaffFileSelect"
            />
          </div>

          <!-- File Info & Action -->
          <div v-if="staffFile" class="file-action-bar">
            <div class="file-info-compact">
              <v-icon size="20" color="success">mdi-file-check</v-icon>
              <span class="file-name">{{ staffFile.name }}</span>
              <span class="file-size"
                >({{ formatFileSize(staffFile.size) }})</span
              >
            </div>
            <v-btn
              color="primary"
              variant="flat"
              size="small"
              @click="importStaffInformation"
              :loading="importingStaff"
              :disabled="!staffFile"
            >
              <v-icon start size="18">mdi-import</v-icon>
              Import
            </v-btn>
          </div>

          <!-- Compact Results -->
          <div
            v-if="staffImportResult"
            class="import-results-compact"
            :class="
              staffImportResult.success ? 'results-success' : 'results-error'
            "
          >
            <div class="results-header">
              <v-icon size="20">{{
                staffImportResult.success
                  ? "mdi-check-circle"
                  : "mdi-alert-circle"
              }}</v-icon>
              <span class="results-title">Import Complete</span>
            </div>
            <div class="results-stats">
              <div class="stat-item">
                <span class="stat-label">Imported:</span>
                <span class="stat-value success">{{
                  staffImportResult.imported
                }}</span>
              </div>
              <div class="stat-item">
                <span class="stat-label">Updated:</span>
                <span class="stat-value">{{ staffImportResult.updated }}</span>
              </div>
              <div class="stat-item">
                <span class="stat-label">Skipped:</span>
                <span class="stat-value">{{ staffImportResult.skipped }}</span>
              </div>
              <div class="stat-item">
                <span class="stat-label">Failed:</span>
                <span class="stat-value error">{{
                  staffImportResult.failed
                }}</span>
              </div>
            </div>
            <div
              v-if="
                staffImportResult.errors && staffImportResult.errors.length > 0
              "
              class="results-errors"
            >
              <div class="errors-label">Errors:</div>
              <div class="errors-list">
                <div
                  v-for="(error, index) in staffImportResult.errors.slice(0, 5)"
                  :key="index"
                  class="error-item"
                >
                  Row {{ error.row }}: {{ error.error }}
                </div>
                <div
                  v-if="staffImportResult.errors.length > 5"
                  class="error-more"
                >
                  + {{ staffImportResult.errors.length - 5 }} more errors
                </div>
              </div>
            </div>
          </div>
        </div>
      </v-col>

      <!-- Step 2: Punch Records -->
      <v-col cols="12" lg="6">
        <div class="import-step-card">
          <div class="step-header">
            <div class="step-number">2</div>
            <div class="step-header-content">
              <h3 class="step-title">Punch Records</h3>
              <p class="step-subtitle">
                Import attendance data for registered staff
              </p>
            </div>
          </div>

          <!-- Collapsible Info Section -->
          <v-expansion-panels variant="accordion" class="info-accordion">
            <v-expansion-panel>
              <v-expansion-panel-title class="info-panel-title">
                <v-icon size="18" class="mr-2">mdi-information</v-icon>
                <span class="text-body-2">File Requirements</span>
              </v-expansion-panel-title>
              <v-expansion-panel-text class="info-panel-text">
                <div class="info-grid">
                  <div class="info-item">
                    <div class="info-label">Required</div>
                    <div class="info-value">
                      Staff Code, Name, Date columns (MM-DD)
                    </div>
                  </div>
                  <div class="info-item">
                    <div class="info-label">Time Format</div>
                    <div class="info-value">
                      HH:MM (e.g., 08:30), multiple times per day
                    </div>
                  </div>
                  <div class="info-item">
                    <div class="info-label">Format</div>
                    <div class="info-value">Excel (.xls, .xlsx)</div>
                  </div>
                </div>
              </v-expansion-panel-text>
            </v-expansion-panel>
          </v-expansion-panels>

          <!-- Year/Month Selection - Compact -->
          <div class="date-selection-section">
            <div class="section-label">
              <v-icon size="16">mdi-calendar</v-icon>
              <span>Date Parameters</span>
            </div>
            <v-row dense>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model.number="punchYear"
                  label="Year *"
                  type="number"
                  :min="2020"
                  :max="2100"
                  variant="outlined"
                  density="compact"
                  hide-details
                >
                  <template v-slot:prepend-inner>
                    <v-icon size="18">mdi-calendar</v-icon>
                  </template>
                </v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-select
                  v-model="punchMonth"
                  :items="months"
                  label="Month (optional)"
                  variant="outlined"
                  density="compact"
                  clearable
                  hide-details
                >
                  <template v-slot:prepend-inner>
                    <v-icon size="18">mdi-calendar-month</v-icon>
                  </template>
                </v-select>
              </v-col>
            </v-row>
          </div>

          <!-- Compact Drop Zone -->
          <div
            class="drop-zone-compact"
            :class="{ 'drop-zone-active': isDraggingPunch }"
            @drop.prevent="handlePunchDrop"
            @dragover.prevent="isDraggingPunch = true"
            @dragleave.prevent="isDraggingPunch = false"
            @click="$refs.punchFileInput.click()"
          >
            <v-icon size="48" color="primary">mdi-cloud-upload-outline</v-icon>
            <div class="drop-zone-text">
              <p class="drop-zone-title">
                {{
                  punchFile ? punchFile.name : "Drop file or click to browse"
                }}
              </p>
              <p class="drop-zone-hint">Excel files only (.xlsx, .xls)</p>
            </div>
            <input
              ref="punchFileInput"
              type="file"
              accept=".xls,.xlsx"
              style="display: none"
              @change="handlePunchFileSelect"
            />
          </div>

          <!-- File Info & Action -->
          <div v-if="punchFile" class="file-action-bar">
            <div class="file-info-compact">
              <v-icon size="20" color="success">mdi-file-check</v-icon>
              <span class="file-name">{{ punchFile.name }}</span>
              <span class="file-size"
                >({{ formatFileSize(punchFile.size) }})</span
              >
            </div>
            <v-btn
              color="primary"
              variant="flat"
              size="small"
              @click="importPunchRecords"
              :loading="importingPunch"
              :disabled="!punchFile || !punchYear"
            >
              <v-icon start size="18">mdi-import</v-icon>
              Import
            </v-btn>
          </div>

          <!-- Compact Results -->
          <div
            v-if="punchImportResult"
            class="import-results-compact"
            :class="
              punchImportResult.success ? 'results-success' : 'results-error'
            "
          >
            <div class="results-header">
              <v-icon size="20">{{
                punchImportResult.success
                  ? "mdi-check-circle"
                  : "mdi-alert-circle"
              }}</v-icon>
              <span class="results-title">Import Complete</span>
            </div>
            <div class="results-stats">
              <div class="stat-item">
                <span class="stat-label">Imported:</span>
                <span class="stat-value success">{{
                  punchImportResult.imported
                }}</span>
              </div>
              <div class="stat-item">
                <span class="stat-label">Updated:</span>
                <span class="stat-value">{{ punchImportResult.updated }}</span>
              </div>
              <div class="stat-item">
                <span class="stat-label">Skipped:</span>
                <span class="stat-value">{{ punchImportResult.skipped }}</span>
              </div>
              <div class="stat-item">
                <span class="stat-label">Failed:</span>
                <span class="stat-value error">{{
                  punchImportResult.failed
                }}</span>
              </div>
            </div>
            <div
              v-if="
                punchImportResult.errors && punchImportResult.errors.length > 0
              "
              class="results-errors"
            >
              <div class="errors-label">Errors:</div>
              <div class="errors-list">
                <div
                  v-for="(error, index) in punchImportResult.errors.slice(0, 5)"
                  :key="index"
                  class="error-item"
                >
                  Row {{ error.row }}: {{ error.error }}
                </div>
                <div
                  v-if="punchImportResult.errors.length > 5"
                  class="error-more"
                >
                  + {{ punchImportResult.errors.length - 5 }} more errors
                </div>
              </div>
            </div>
          </div>
        </div>
      </v-col>
    </v-row>

    <!-- Template Info Dialog - Professional Design -->
    <v-dialog v-model="templateDialog" max-width="900px">
      <v-card class="template-dialog">
        <v-card-title class="dialog-title">
          <v-icon class="mr-2" color="primary">mdi-information</v-icon>
          <span>Template Information</span>
        </v-card-title>
        <v-divider></v-divider>
        <v-card-text class="dialog-content">
          <v-row>
            <v-col cols="12" md="6">
              <div class="template-info-card">
                <div class="template-card-header">
                  <v-icon size="24" color="primary">mdi-account-group</v-icon>
                  <h4>Staff Information Template</h4>
                </div>
                <div class="template-card-body">
                  <div class="info-section">
                    <div class="info-section-title">Required Columns</div>
                    <ul class="info-list">
                      <li>Staff Code</li>
                      <li>Name</li>
                    </ul>
                  </div>
                  <div class="info-section">
                    <div class="info-section-title">Optional Columns</div>
                    <ul class="info-list">
                      <li>User ID, Email, Mobile No</li>
                      <li>Gender, Entry Date</li>
                      <li>
                        Entry Status (Official/Regular/Probationary/Contractual)
                      </li>
                      <li>Position, Department</li>
                    </ul>
                  </div>
                  <div class="info-section">
                    <div class="info-section-title">Field Mapping</div>
                    <ul class="info-list">
                      <li><strong>Staff Type</strong> → Position (job role)</li>
                      <li>
                        <strong>Department</strong> → Project (auto-created)
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </v-col>
            <v-col cols="12" md="6">
              <div class="template-info-card">
                <div class="template-card-header">
                  <v-icon size="24" color="primary">mdi-clock-outline</v-icon>
                  <h4>Punch Records Template</h4>
                </div>
                <div class="template-card-body">
                  <div class="info-section">
                    <div class="info-section-title">Required Columns</div>
                    <ul class="info-list">
                      <li>Staff Code</li>
                      <li>Name</li>
                      <li>Date columns in MM-DD format (e.g., 12-01, 12-02)</li>
                    </ul>
                  </div>
                  <div class="info-section">
                    <div class="info-section-title">Time Format</div>
                    <ul class="info-list">
                      <li>HH:MM format (e.g., 08:30, 17:00)</li>
                      <li>Multiple times separated by newlines in cell</li>
                    </ul>
                  </div>
                  <div class="info-section">
                    <div class="info-section-title">Parameters</div>
                    <ul class="info-list">
                      <li><strong>Year:</strong> Required - Year of records</li>
                      <li>
                        <strong>Month:</strong> Optional - Override month from
                        columns
                      </li>
                    </ul>
                  </div>
                </div>
              </div>
            </v-col>
          </v-row>
        </v-card-text>
        <v-divider></v-divider>
        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <v-btn color="primary" variant="flat" @click="templateDialog = false">
            Close
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import api from "@/services/api";
import { useToast } from "vue-toastification";

const toast = useToast();

// Staff Information Import
const staffFile = ref(null);
const isDraggingStaff = ref(false);
const importingStaff = ref(false);
const staffImportResult = ref(null);

// Default values for missing data
const defaultPosition = ref(null);
const defaultProject = ref(null);
const positionOptions = ref([]);
const projectOptions = ref([]);

// Load positions and projects on mount
onMounted(async () => {
  try {
    // Load position rates
    const positionsResponse = await api.get("/position-rates");
    positionOptions.value = positionsResponse.data
      .map((rate) => rate.position_name)
      .sort();

    // Load projects
    const projectsResponse = await api.get("/projects");
    projectOptions.value = projectsResponse.data.map((project) => ({
      title: project.name,
      value: project.id,
    }));
  } catch (error) {
    console.error("Failed to load options:", error);
  }
});

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
  { title: "January", value: 1 },
  { title: "February", value: 2 },
  { title: "March", value: 3 },
  { title: "April", value: 4 },
  { title: "May", value: 5 },
  { title: "June", value: 6 },
  { title: "July", value: 7 },
  { title: "August", value: 8 },
  { title: "September", value: 9 },
  { title: "October", value: 10 },
  { title: "November", value: 11 },
  { title: "December", value: 12 },
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
  if (file && (file.name.endsWith(".xls") || file.name.endsWith(".xlsx"))) {
    staffFile.value = file;
    staffImportResult.value = null;
  } else {
    toast.error("Please upload a valid Excel file (.xls or .xlsx)");
  }
};

const importStaffInformation = async () => {
  if (!staffFile.value) return;

  importingStaff.value = true;
  staffImportResult.value = null;

  try {
    const formData = new FormData();
    formData.append("file", staffFile.value);

    // Add default values if set
    if (defaultPosition.value) {
      formData.append("default_position", defaultPosition.value);
    }
    if (defaultProject.value) {
      formData.append("default_project_id", defaultProject.value);
    }

    const response = await api.post("/biometric/import-staff", formData, {
      headers: { "Content-Type": "multipart/form-data" },
    });

    staffImportResult.value = {
      success: true,
      imported: response.data.imported,
      updated: response.data.updated,
      skipped: response.data.skipped,
      failed: response.data.failed,
      errors: response.data.errors || [],
    };

    toast.success(
      `Staff information imported successfully! ${response.data.imported} employees created, ${response.data.updated} updated.`,
    );
  } catch (error) {
    toast.error(
      error.response?.data?.message || "Failed to import staff information",
    );
    staffImportResult.value = {
      success: false,
      imported: 0,
      updated: 0,
      skipped: 0,
      failed: 0,
      errors: [
        { row: 0, error: error.response?.data?.message || error.message },
      ],
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
  if (file && (file.name.endsWith(".xls") || file.name.endsWith(".xlsx"))) {
    punchFile.value = file;
    punchImportResult.value = null;
  } else {
    toast.error("Please upload a valid Excel file (.xls or .xlsx)");
  }
};

const importPunchRecords = async () => {
  if (!punchFile.value || !punchYear.value) return;

  importingPunch.value = true;
  punchImportResult.value = null;

  try {
    const formData = new FormData();
    formData.append("file", punchFile.value);
    formData.append("year", punchYear.value);
    if (punchMonth.value) {
      formData.append("month", punchMonth.value);
    }

    const response = await api.post(
      "/biometric/import-punch-records",
      formData,
      {
        headers: { "Content-Type": "multipart/form-data" },
      },
    );

    punchImportResult.value = {
      success: true,
      imported: response.data.imported,
      updated: response.data.updated,
      skipped: response.data.skipped,
      failed: response.data.failed,
      errors: response.data.errors || [],
    };

    toast.success(
      `Punch records imported successfully! ${response.data.imported} records created, ${response.data.updated} updated.`,
    );
  } catch (error) {
    toast.error(
      error.response?.data?.message || "Failed to import punch records",
    );
    punchImportResult.value = {
      success: false,
      imported: 0,
      updated: 0,
      skipped: 0,
      failed: 0,
      errors: [
        { row: 0, error: error.response?.data?.message || error.message },
      ],
    };
  } finally {
    importingPunch.value = false;
  }
};

// Utility Functions
const formatFileSize = (bytes) => {
  if (bytes < 1024) return bytes + " B";
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + " KB";
  return (bytes / (1024 * 1024)).toFixed(2) + " MB";
};

const showTemplateInfo = () => {
  templateDialog.value = true;
};
</script>

<style scoped lang="scss">
.biometric-import-view {
  padding: 0;
}

// Compact Header matching Dashboard
.page-header-compact {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 24px;
  gap: 16px;
  flex-wrap: wrap;
}

.header-left {
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
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);
  color: white;
  flex-shrink: 0;
}

.page-title {
  font-size: 24px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
  line-height: 1.2;
  letter-spacing: -0.3px;
}

.page-subtitle {
  font-size: 13px;
  color: #64748b;
  margin: 4px 0 0 0;
}

.action-btn-compact {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 18px;
  border-radius: 10px;
  border: 1.5px solid rgba(0, 31, 61, 0.15);
  background: white;
  color: #001f3d;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;

  &:hover {
    background: rgba(0, 31, 61, 0.04);
    border-color: rgba(0, 31, 61, 0.25);
  }
}

// Import Steps Grid
.import-steps-grid {
  margin: 0 -12px;
}

.import-step-card {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  overflow: hidden;
  height: 100%;
  display: flex;
  flex-direction: column;
}

// Step Header
.step-header {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px 24px;
  background: linear-gradient(
    135deg,
    rgba(237, 152, 95, 0.06) 0%,
    rgba(247, 185, 128, 0.04) 100%
  );
  border-bottom: 1px solid rgba(0, 31, 61, 0.08);
}

.step-number {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: white;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 20px;
  font-weight: 700;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);
}

.step-header-content {
  flex: 1;
}

.step-title {
  font-size: 18px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
  line-height: 1.2;
}

.step-subtitle {
  font-size: 13px;
  color: #64748b;
  margin: 4px 0 0 0;
}

// Info Accordion - Compact
.info-accordion {
  margin: 16px 24px;

  :deep(.v-expansion-panel) {
    background: transparent !important;
    box-shadow: none !important;
  }

  .info-panel-title {
    min-height: 40px !important;
    padding: 8px 12px !important;
    font-size: 13px;
    font-weight: 600;
    color: #001f3d;
    background: rgba(0, 31, 61, 0.02);
    border-radius: 8px;
  }

  .info-panel-text {
    padding: 12px !important;
  }
}

.info-grid {
  display: grid;
  gap: 12px;
}

.info-item {
  padding: 10px 12px;
  background: rgba(0, 31, 61, 0.02);
  border-radius: 8px;
  border-left: 3px solid #ed985f;
}

.info-label {
  font-size: 11px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
}

.info-value {
  font-size: 13px;
  color: #001f3d;
  line-height: 1.5;
}

// Default Values & Date Selection Sections
.default-values-section,
.date-selection-section {
  padding: 16px 24px;
  border-top: 1px solid rgba(0, 31, 61, 0.06);
}

.section-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 600;
  color: #001f3d;
  margin-bottom: 12px;

  .v-icon {
    color: #ed985f;
  }
}

// Compact Drop Zone
.drop-zone-compact {
  margin: 16px 24px;
  padding: 32px 20px;
  border: 2px dashed rgba(237, 152, 95, 0.3);
  border-radius: 12px;
  background: rgba(237, 152, 95, 0.02);
  text-align: center;
  cursor: pointer;
  transition: all 0.3s ease;

  &:hover,
  &.drop-zone-active {
    border-color: #ed985f;
    background: rgba(237, 152, 95, 0.06);
  }

  .v-icon {
    color: #ed985f !important;
  }
}

.drop-zone-text {
  margin-top: 12px;
}

.drop-zone-title {
  font-size: 14px;
  font-weight: 600;
  color: #001f3d;
  margin: 0 0 4px 0;
}

.drop-zone-hint {
  font-size: 12px;
  color: #64748b;
  margin: 0;
}

// File Action Bar
.file-action-bar {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 24px;
  background: rgba(34, 197, 94, 0.06);
  border-top: 1px solid rgba(34, 197, 94, 0.2);
  gap: 12px;
  flex-wrap: wrap;
}

.file-info-compact {
  display: flex;
  align-items: center;
  gap: 8px;
  flex: 1;
  min-width: 0;
}

.file-name {
  font-size: 13px;
  font-weight: 600;
  color: #001f3d;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.file-size {
  font-size: 12px;
  color: #64748b;
  flex-shrink: 0;
}

// Import Results - Compact
.import-results-compact {
  margin: 16px 24px 24px;
  padding: 16px;
  border-radius: 12px;
  border: 1px solid;

  &.results-success {
    background: rgba(34, 197, 94, 0.06);
    border-color: rgba(34, 197, 94, 0.3);
  }

  &.results-error {
    background: rgba(239, 68, 68, 0.06);
    border-color: rgba(239, 68, 68, 0.3);
  }
}

.results-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;

  .v-icon {
    color: #22c55e;
  }

  .results-error & .v-icon {
    color: #ef4444;
  }
}

.results-title {
  font-size: 14px;
  font-weight: 700;
  color: #001f3d;
}

.results-stats {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 12px;
  margin-bottom: 12px;
}

.stat-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 8px 12px;
  background: white;
  border-radius: 8px;
  border: 1px solid rgba(0, 31, 61, 0.08);
}

.stat-label {
  font-size: 12px;
  color: #64748b;
  font-weight: 500;
}

.stat-value {
  font-size: 16px;
  font-weight: 700;
  color: #001f3d;

  &.success {
    color: #22c55e;
  }

  &.error {
    color: #ef4444;
  }
}

.results-errors {
  padding-top: 12px;
  border-top: 1px solid rgba(0, 31, 61, 0.08);
}

.errors-label {
  font-size: 12px;
  font-weight: 700;
  color: #ef4444;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 8px;
}

.errors-list {
  display: flex;
  flex-direction: column;
  gap: 6px;
}

.error-item {
  font-size: 12px;
  color: #001f3d;
  padding: 6px 10px;
  background: rgba(239, 68, 68, 0.06);
  border-radius: 6px;
  border-left: 3px solid #ef4444;
}

.error-more {
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
  padding: 6px 10px;
  text-align: center;
}

// Template Dialog
.template-dialog {
  border-radius: 16px !important;
}

.dialog-title {
  font-size: 20px;
  font-weight: 700;
  color: #001f3d;
  padding: 20px 24px;
}

.dialog-content {
  padding: 24px !important;
}

.template-info-card {
  background: white;
  border: 1px solid rgba(0, 31, 61, 0.08);
  border-radius: 12px;
  overflow: hidden;
  height: 100%;
}

.template-card-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 20px;
  background: linear-gradient(
    135deg,
    rgba(237, 152, 95, 0.06) 0%,
    rgba(247, 185, 128, 0.04) 100%
  );
  border-bottom: 1px solid rgba(0, 31, 61, 0.08);

  h4 {
    font-size: 16px;
    font-weight: 700;
    color: #001f3d;
    margin: 0;
  }
}

.template-card-body {
  padding: 20px;
}

.info-section {
  margin-bottom: 16px;

  &:last-child {
    margin-bottom: 0;
  }
}

.info-section-title {
  font-size: 13px;
  font-weight: 700;
  color: #001f3d;
  margin-bottom: 8px;
}

.info-list {
  list-style: none;
  padding: 0;
  margin: 0;

  li {
    font-size: 13px;
    color: #64748b;
    line-height: 1.6;
    padding: 4px 0 4px 16px;
    position: relative;

    &::before {
      content: "•";
      position: absolute;
      left: 0;
      color: #ed985f;
      font-weight: bold;
    }
  }
}

.dialog-actions {
  padding: 16px 24px !important;
  background: rgba(0, 31, 61, 0.02);
}
</style>
