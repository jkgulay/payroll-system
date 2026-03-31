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

          <!-- File Requirements - Collapsible Modern Card -->
          <v-expansion-panels
            variant="accordion"
            class="requirements-accordion"
          >
            <v-expansion-panel>
              <v-expansion-panel-title class="requirements-panel-title">
                <v-icon size="16" color="#ed985f" class="mr-2"
                  >mdi-file-document-outline</v-icon
                >
                <span>File Requirements</span>
              </v-expansion-panel-title>
              <v-expansion-panel-text class="requirements-panel-text">
                <div class="requirements-grid">
                  <div class="requirement-item required">
                    <div class="requirement-icon">
                      <v-icon size="14">mdi-check-circle</v-icon>
                    </div>
                    <div class="requirement-content">
                      <div class="requirement-label">Required Fields</div>
                      <div class="requirement-value">Staff Code, Name</div>
                    </div>
                  </div>
                  <div class="requirement-item optional">
                    <div class="requirement-icon">
                      <v-icon size="14">mdi-information</v-icon>
                    </div>
                    <div class="requirement-content">
                      <div class="requirement-label">Optional Fields</div>
                      <div class="requirement-value">
                        Email, Mobile, Gender, Position, Project
                      </div>
                    </div>
                  </div>
                  <div class="requirement-item format">
                    <div class="requirement-icon">
                      <v-icon size="14">mdi-file-excel</v-icon>
                    </div>
                    <div class="requirement-content">
                      <div class="requirement-label">File Format</div>
                      <div class="requirement-value">
                        Excel (.xls, .xlsx) or CSV (Excel auto-converts to CSV)
                      </div>
                    </div>
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
                  placeholder="Select department"
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
              <p class="drop-zone-hint">
                Excel or CSV files (.xlsx, .xls, .csv)
              </p>
            </div>
            <input
              ref="staffFileInput"
              type="file"
              accept=".xls,.xlsx,.csv"
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
              :disabled="!staffFile || preparingStaffFile"
            >
              <v-icon start size="18">mdi-import</v-icon>
              Import
            </v-btn>
          </div>

          <div v-if="staffFile" class="conversion-badge-row">
            <div v-if="preparingStaffFile" class="conversion-preparing">
              <v-progress-circular
                indeterminate
                size="16"
                width="2"
                color="#ed985f"
              />
              <span>Preparing CSV preview...</span>
            </div>

            <div
              v-else-if="staffPreparationInfo"
              class="conversion-badge"
              :class="{
                'conversion-badge-converted': staffPreparationInfo.converted,
                'conversion-badge-fallback': staffPreparationInfo.fallback,
              }"
            >
              <v-icon size="14">mdi-file-delimited-outline</v-icon>
              <span class="conversion-title">
                {{
                  staffPreparationInfo.converted
                    ? "Converted to CSV"
                    : staffPreparationInfo.fallback
                      ? "Using Original Excel"
                      : "CSV Ready"
                }}
              </span>
              <span class="conversion-meta">
                Upload: {{ formatFileSize(staffPreparationInfo.uploadSize) }}
              </span>
              <span
                v-if="staffPreparationInfo.converted"
                class="conversion-meta muted"
              >
                from {{ formatFileSize(staffPreparationInfo.originalSize) }}
              </span>
            </div>
          </div>

          <!-- Progress Bar -->
          <div v-if="importingStaff" class="progress-container">
            <div class="progress-header">
              <span class="progress-label">
                {{
                  staffProcessing
                    ? staffProgressDetail || "Processing records..."
                    : "Uploading file..."
                }}
              </span>
              <span class="progress-percentage">
                {{ Math.round(staffUploadProgress) }}%
              </span>
            </div>
            <v-progress-linear
              :model-value="staffUploadProgress"
              color="#ed985f"
              height="8"
              rounded
            ></v-progress-linear>
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

          <!-- File Requirements - Collapsible Modern Card -->
          <v-expansion-panels
            variant="accordion"
            class="requirements-accordion"
          >
            <v-expansion-panel>
              <v-expansion-panel-title class="requirements-panel-title">
                <v-icon size="16" color="#ed985f" class="mr-2"
                  >mdi-file-document-outline</v-icon
                >
                <span>File Requirements</span>
              </v-expansion-panel-title>
              <v-expansion-panel-text class="requirements-panel-text">
                <div class="requirements-grid">
                  <div class="requirement-item required">
                    <div class="requirement-icon">
                      <v-icon size="14">mdi-check-circle</v-icon>
                    </div>
                    <div class="requirement-content">
                      <div class="requirement-label">Required Fields</div>
                      <div class="requirement-value">
                        Staff Code, Name, Punch Date (YYYY-MM-DD HH:MM)
                      </div>
                    </div>
                  </div>
                  <div class="requirement-item optional">
                    <div class="requirement-icon">
                      <v-icon size="14">mdi-clock-outline</v-icon>
                    </div>
                    <div class="requirement-content">
                      <div class="requirement-label">Row Format</div>
                      <div class="requirement-value">
                        One punch event per row — grouped by employee + date
                        automatically
                      </div>
                    </div>
                  </div>
                  <div class="requirement-item format">
                    <div class="requirement-icon">
                      <v-icon size="14">mdi-file-excel</v-icon>
                    </div>
                    <div class="requirement-content">
                      <div class="requirement-label">File Format</div>
                      <div class="requirement-value">
                        Excel (.xls, .xlsx) or CSV (Excel auto-converts to CSV)
                      </div>
                    </div>
                  </div>
                </div>
              </v-expansion-panel-text>
            </v-expansion-panel>
          </v-expansion-panels>

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
              <p class="drop-zone-hint">
                Excel or CSV files (.xlsx, .xls, .csv)
              </p>
            </div>
            <input
              ref="punchFileInput"
              type="file"
              accept=".xls,.xlsx,.csv"
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
              :disabled="!punchFile || preparingPunchFile"
            >
              <v-icon start size="18">mdi-import</v-icon>
              Import
            </v-btn>
          </div>

          <div v-if="punchFile" class="conversion-badge-row">
            <div v-if="preparingPunchFile" class="conversion-preparing">
              <v-progress-circular
                indeterminate
                size="16"
                width="2"
                color="#ed985f"
              />
              <span>Preparing CSV preview...</span>
            </div>

            <div
              v-else-if="punchPreparationInfo"
              class="conversion-badge"
              :class="{
                'conversion-badge-converted': punchPreparationInfo.converted,
                'conversion-badge-fallback': punchPreparationInfo.fallback,
              }"
            >
              <v-icon size="14">mdi-file-delimited-outline</v-icon>
              <span class="conversion-title">
                {{
                  punchPreparationInfo.converted
                    ? "Converted to CSV"
                    : punchPreparationInfo.fallback
                      ? "Using Original Excel"
                      : "CSV Ready"
                }}
              </span>
              <span class="conversion-meta">
                Upload: {{ formatFileSize(punchPreparationInfo.uploadSize) }}
              </span>
              <span
                v-if="punchPreparationInfo.converted"
                class="conversion-meta muted"
              >
                from {{ formatFileSize(punchPreparationInfo.originalSize) }}
              </span>
            </div>
          </div>

          <!-- Progress Bar -->
          <div v-if="importingPunch" class="progress-container">
            <div class="progress-header">
              <span class="progress-label">
                {{
                  punchProcessing
                    ? punchProgressDetail || "Processing records..."
                    : "Uploading file..."
                }}
              </span>
              <span class="progress-percentage">
                {{ Math.round(punchUploadProgress) }}%
              </span>
            </div>
            <v-progress-linear
              :model-value="punchUploadProgress"
              color="#ed985f"
              height="8"
              rounded
            ></v-progress-linear>
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
                      <li>Position, Project</li>
                    </ul>
                  </div>
                  <div class="info-section">
                    <div class="info-section-title">Field Mapping</div>
                    <ul class="info-list">
                      <li><strong>Staff Type</strong> → Position (job role)</li>
                      <li><strong>Project</strong> → Project (auto-created)</li>
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
                      <li>Punch Date (YYYY-MM-DD HH:MM)</li>
                    </ul>
                  </div>
                  <div class="info-section">
                    <div class="info-section-title">Optional Columns</div>
                    <ul class="info-list">
                      <li>Punch Type</li>
                      <li>Punch Address</li>
                      <li>Device Name</li>
                      <li>Punch Photo, Remark</li>
                    </ul>
                  </div>
                  <div class="info-section">
                    <div class="info-section-title">Notes</div>
                    <ul class="info-list">
                      <li>One row = one punch event</li>
                      <li>
                        Multiple punches per employee per day are grouped
                        automatically
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
import { devLog } from "@/utils/devLog";

const toast = useToast();

// Staff Information Import
const staffFile = ref(null);
const isDraggingStaff = ref(false);
const importingStaff = ref(false);
const staffImportResult = ref(null);
const staffUploadProgress = ref(0);
const staffProcessing = ref(false);
const staffProgressDetail = ref("");
const staffPreparedFile = ref(null);
const staffPreparationInfo = ref(null);
const preparingStaffFile = ref(false);

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
    devLog.error("Failed to load options:", error);
  }
});

// Punch Records Import
const punchFile = ref(null);
const isDraggingPunch = ref(false);
const importingPunch = ref(false);
const punchImportResult = ref(null);
const punchUploadProgress = ref(0);
const punchProcessing = ref(false);
const punchProgressDetail = ref("");
const punchPreparedFile = ref(null);
const punchPreparationInfo = ref(null);
const preparingPunchFile = ref(false);

// Template Dialog
const templateDialog = ref(false);
let xlsxModule = null;

const getXlsxModule = async () => {
  if (xlsxModule) return xlsxModule;

  try {
    xlsxModule = await import("xlsx");
    return xlsxModule;
  } catch (error) {
    devLog.error("Failed to load xlsx module:", error);
    throw new Error(
      "Spreadsheet converter is not ready. Refresh the page or restart the dev server with --force.",
    );
  }
};

const getFileExtension = (file) => {
  if (!file?.name || !file.name.includes(".")) return "";
  return file.name.split(".").pop().toLowerCase();
};

const isSupportedImportFile = (file) => {
  const ext = getFileExtension(file);
  return ["xls", "xlsx", "csv"].includes(ext);
};

const isExcelFile = (file) => {
  const ext = getFileExtension(file);
  return ext === "xls" || ext === "xlsx";
};

const convertExcelFileToCsv = async (file) => {
  const XLSX = await getXlsxModule();
  const arrayBuffer = await file.arrayBuffer();
  const workbook = XLSX.read(arrayBuffer, {
    type: "array",
    raw: false,
    cellDates: false,
  });

  const firstSheetName = workbook.SheetNames?.[0];
  if (!firstSheetName) {
    throw new Error("The Excel file has no readable worksheet.");
  }

  const worksheet = workbook.Sheets[firstSheetName];
  const csv = XLSX.utils.sheet_to_csv(worksheet, {
    blankrows: false,
    FS: ",",
    RS: "\n",
    dateNF: "yyyy-mm-dd hh:mm:ss",
  });

  const normalizedCsv = csv.replace(/\r\n/g, "\n");
  const baseName = file.name.replace(/\.[^.]+$/, "");
  return new File([normalizedCsv], `${baseName}.csv`, {
    type: "text/csv;charset=utf-8",
  });
};

const prepareFileForImport = async (
  file,
  { setDetail, setProgress, importLabel },
) => {
  if (!file) {
    throw new Error("No file selected");
  }

  if (!isSupportedImportFile(file)) {
    throw new Error("Please upload a valid file (.xls, .xlsx, or .csv)");
  }

  if (!isExcelFile(file)) {
    setDetail("Uploading CSV file...");
    setProgress(10);
    return file;
  }

  setDetail("Converting Excel file to CSV...");
  setProgress(10);

  try {
    const converted = await convertExcelFileToCsv(file);
    setDetail("Excel converted to CSV. Uploading optimized file...");
    setProgress(18);
    return converted;
  } catch (error) {
    devLog.error(`CSV conversion failed for ${importLabel}:`, error);
    toast.warning(
      "Could not convert to CSV. Uploading original Excel file for compatibility.",
    );
    setDetail("Using original Excel file for upload...");
    setProgress(12);
    return file;
  }
};

const buildPreparationInfo = ({
  originalFile,
  uploadFile,
  converted,
  fallback = false,
}) => ({
  converted,
  fallback,
  originalName: originalFile.name,
  originalSize: originalFile.size,
  uploadName: uploadFile.name,
  uploadSize: uploadFile.size,
});

const prepareSelectedFile = async (
  file,
  {
    importLabel,
    setSelectedFile,
    setPreparedFile,
    setPreparationInfo,
    setIsPreparing,
    clearResult,
  },
) => {
  if (!file) return;

  if (!isSupportedImportFile(file)) {
    toast.error("Please upload a valid file (.xls, .xlsx, or .csv)");
    return;
  }

  setSelectedFile(file);
  setPreparedFile(null);
  setPreparationInfo(null);
  clearResult();
  setIsPreparing(true);

  if (!isExcelFile(file)) {
    setPreparedFile(file);
    setPreparationInfo(
      buildPreparationInfo({
        originalFile: file,
        uploadFile: file,
        converted: false,
      }),
    );
    setIsPreparing(false);
    return;
  }

  try {
    const convertedFile = await convertExcelFileToCsv(file);
    setPreparedFile(convertedFile);
    setPreparationInfo(
      buildPreparationInfo({
        originalFile: file,
        uploadFile: convertedFile,
        converted: true,
      }),
    );
  } catch (error) {
    devLog.error(`CSV pre-conversion failed for ${importLabel}:`, error);
    toast.warning(
      "Preview conversion failed. The importer will use original Excel for compatibility.",
    );
    setPreparedFile(file);
    setPreparationInfo(
      buildPreparationInfo({
        originalFile: file,
        uploadFile: file,
        converted: false,
        fallback: true,
      }),
    );
  } finally {
    setIsPreparing(false);
  }
};

const handleStaffFileSelect = async (event) => {
  const file = event.target.files[0];
  await prepareSelectedFile(file, {
    importLabel: "staff import",
    setSelectedFile: (value) => (staffFile.value = value),
    setPreparedFile: (value) => (staffPreparedFile.value = value),
    setPreparationInfo: (value) => (staffPreparationInfo.value = value),
    setIsPreparing: (value) => (preparingStaffFile.value = value),
    clearResult: () => {
      staffImportResult.value = null;
    },
  });
};

const handleStaffDrop = async (event) => {
  isDraggingStaff.value = false;
  const file = event.dataTransfer.files[0];
  await prepareSelectedFile(file, {
    importLabel: "staff import",
    setSelectedFile: (value) => (staffFile.value = value),
    setPreparedFile: (value) => (staffPreparedFile.value = value),
    setPreparationInfo: (value) => (staffPreparationInfo.value = value),
    setIsPreparing: (value) => (preparingStaffFile.value = value),
    clearResult: () => {
      staffImportResult.value = null;
    },
  });
};

// Read a streaming response (JSON lines) and call handlers for progress/complete/error
const readStream = async (response, { onProgress, onComplete, onError }) => {
  const extractJsonObjects = (text) => {
    const messages = [];
    if (!text) return { messages, remaining: "" };

    let start = -1;
    let depth = 0;
    let inString = false;
    let escaped = false;
    let lastConsumedIndex = 0;

    for (let i = 0; i < text.length; i++) {
      const ch = text[i];

      if (start === -1) {
        if (ch === "{") {
          start = i;
          depth = 1;
          inString = false;
          escaped = false;
        }
        continue;
      }

      if (inString) {
        if (escaped) {
          escaped = false;
          continue;
        }
        if (ch === "\\") {
          escaped = true;
          continue;
        }
        if (ch === '"') {
          inString = false;
        }
        continue;
      }

      if (ch === '"') {
        inString = true;
        continue;
      }

      if (ch === "{") {
        depth++;
        continue;
      }

      if (ch === "}") {
        depth--;
        if (depth === 0) {
          const candidate = text.slice(start, i + 1);
          try {
            messages.push(JSON.parse(candidate));
          } catch {
            // ignore malformed candidate and continue scanning
          }
          lastConsumedIndex = i + 1;
          start = -1;
        }
      }
    }

    if (start !== -1) {
      return { messages, remaining: text.slice(start) };
    }

    return { messages, remaining: text.slice(lastConsumedIndex) };
  };

  if (!response.body) {
    const fallbackText = await response.text();
    if (!fallbackText.trim()) return;

    const { messages } = extractJsonObjects(fallbackText);
    for (const msg of messages) {
      if (msg.type === "progress" && onProgress) onProgress(msg);
      else if (msg.type === "complete" && onComplete) onComplete(msg);
      else if (msg.type === "error" && onError) onError(msg);
      else if (
        (msg.imported !== undefined ||
          msg.updated !== undefined ||
          msg.skipped !== undefined ||
          msg.failed !== undefined) &&
        onComplete
      ) {
        onComplete({ ...msg, type: "complete" });
      }
    }
    return;
  }

  const handleMessage = (msg) => {
    if (!msg || typeof msg !== "object") return;
    if (msg.type === "progress" && onProgress) onProgress(msg);
    else if (msg.type === "complete" && onComplete) onComplete(msg);
    else if (msg.type === "error" && onError) onError(msg);
  };

  const reader = response.body.getReader();
  const decoder = new TextDecoder();
  let buffer = "";

  while (true) {
    const { done, value } = await reader.read();
    if (done) break;
    buffer += decoder.decode(value, { stream: true });

    const extracted = extractJsonObjects(buffer);
    buffer = extracted.remaining;

    for (const msg of extracted.messages) {
      if (
        msg.type === undefined &&
        (msg.imported !== undefined ||
          msg.updated !== undefined ||
          msg.skipped !== undefined ||
          msg.failed !== undefined)
      ) {
        handleMessage({ ...msg, type: "complete" });
      } else {
        handleMessage(msg);
      }
    }
  }

  // Process any remaining buffer
  if (buffer.trim()) {
    const extracted = extractJsonObjects(buffer);
    for (const msg of extracted.messages) {
      if (
        msg.type === undefined &&
        (msg.imported !== undefined ||
          msg.updated !== undefined ||
          msg.skipped !== undefined ||
          msg.failed !== undefined)
      ) {
        handleMessage({ ...msg, type: "complete" });
      } else {
        handleMessage(msg);
      }
    }
  }
};

// Build fetch headers with auth token
const getAuthHeaders = () => {
  const token =
    localStorage.getItem("token") || sessionStorage.getItem("token");
  const headers = {};
  if (token) headers["Authorization"] = `Bearer ${token}`;
  return headers;
};

const getApiBaseUrl = () => {
  return import.meta.env.VITE_API_URL || "http://localhost:8000/api";
};

const importStaffInformation = async () => {
  if (!staffFile.value) return;

  importingStaff.value = true;
  staffImportResult.value = null;
  staffUploadProgress.value = 0;
  staffProcessing.value = false;
  staffProgressDetail.value = "";

  try {
    staffProcessing.value = true;
    staffProgressDetail.value = "Preparing file...";

    const uploadFile =
      staffPreparedFile.value ||
      (await prepareFileForImport(staffFile.value, {
        setDetail: (detail) => (staffProgressDetail.value = detail),
        setProgress: (value) => (staffUploadProgress.value = value),
        importLabel: "staff import",
      }));

    const formData = new FormData();
    formData.append("file", uploadFile);
    formData.append("original_filename", staffFile.value.name);

    // Add default values if set
    if (defaultPosition.value) {
      formData.append("default_position", defaultPosition.value);
    }
    if (defaultProject.value) {
      formData.append("default_project_id", defaultProject.value);
    }

    // Use fetch for streaming response
    const response = await fetch(getApiBaseUrl() + "/biometric/import-staff", {
      method: "POST",
      headers: getAuthHeaders(),
      body: formData,
    });

    if (!response.ok) {
      const errorText = await response.text();
      let errorMsg = "Failed to import staff information";
      try {
        const errorJson = JSON.parse(errorText);
        errorMsg = errorJson.message || errorMsg;
      } catch {
        // not JSON
      }
      throw new Error(errorMsg);
    }

    // Switch to processing mode — upload is done
    staffProcessing.value = true;
    staffUploadProgress.value = 0;

    let completed = false;

    await readStream(response, {
      onProgress: (msg) => {
        staffUploadProgress.value = msg.percent || 0;
        staffProgressDetail.value = msg.detail || msg.phase || "Processing...";
      },
      onComplete: (msg) => {
        completed = true;
        staffUploadProgress.value = 100;
        staffProgressDetail.value = "Complete!";

        staffImportResult.value = {
          success: true,
          imported: msg.imported || 0,
          updated: msg.updated || 0,
          skipped: msg.skipped || 0,
          failed: msg.failed || 0,
          errors: msg.errors || [],
        };

        toast.success(
          `Staff information imported successfully! ${msg.imported || 0} employees created, ${msg.updated || 0} updated.`,
        );
      },
      onError: (msg) => {
        completed = true;
        throw new Error(msg.message || "Import failed on server");
      },
    });

    if (!completed) {
      throw new Error(
        `Stream ended without completion message. Last status: ${staffProgressDetail.value || "No server status received"}`,
      );
    }
  } catch (error) {
    toast.error(error.message || "Failed to import staff information");
    staffImportResult.value = {
      success: false,
      imported: 0,
      updated: 0,
      skipped: 0,
      failed: 0,
      errors: [{ row: 0, error: error.message }],
    };
  } finally {
    importingStaff.value = false;
    staffUploadProgress.value = 0;
    staffProcessing.value = false;
    staffProgressDetail.value = "";
  }
};

// Punch Records Handlers
const handlePunchFileSelect = async (event) => {
  const file = event.target.files[0];
  await prepareSelectedFile(file, {
    importLabel: "punch import",
    setSelectedFile: (value) => (punchFile.value = value),
    setPreparedFile: (value) => (punchPreparedFile.value = value),
    setPreparationInfo: (value) => (punchPreparationInfo.value = value),
    setIsPreparing: (value) => (preparingPunchFile.value = value),
    clearResult: () => {
      punchImportResult.value = null;
    },
  });
};

const handlePunchDrop = async (event) => {
  isDraggingPunch.value = false;
  const file = event.dataTransfer.files[0];
  await prepareSelectedFile(file, {
    importLabel: "punch import",
    setSelectedFile: (value) => (punchFile.value = value),
    setPreparedFile: (value) => (punchPreparedFile.value = value),
    setPreparationInfo: (value) => (punchPreparationInfo.value = value),
    setIsPreparing: (value) => (preparingPunchFile.value = value),
    clearResult: () => {
      punchImportResult.value = null;
    },
  });
};

const importPunchRecords = async () => {
  if (!punchFile.value) return;

  importingPunch.value = true;
  punchImportResult.value = null;
  punchUploadProgress.value = 0;
  punchProcessing.value = false;
  punchProgressDetail.value = "";

  try {
    punchProcessing.value = true;
    punchProgressDetail.value = "Preparing file...";

    const uploadFile =
      punchPreparedFile.value ||
      (await prepareFileForImport(punchFile.value, {
        setDetail: (detail) => (punchProgressDetail.value = detail),
        setProgress: (value) => (punchUploadProgress.value = value),
        importLabel: "punch import",
      }));

    const formData = new FormData();
    formData.append("file", uploadFile);
    formData.append("original_filename", punchFile.value.name);

    // Use fetch for streaming response
    const response = await fetch(
      getApiBaseUrl() + "/biometric/import-punch-records",
      {
        method: "POST",
        headers: getAuthHeaders(),
        body: formData,
      },
    );

    if (!response.ok) {
      const errorText = await response.text();
      let errorMsg = "Failed to import punch records";
      try {
        const errorJson = JSON.parse(errorText);
        errorMsg = errorJson.message || errorMsg;
      } catch {
        // not JSON
      }
      throw new Error(errorMsg);
    }

    // Switch to processing mode — upload is done
    punchProcessing.value = true;
    punchUploadProgress.value = 0;

    let completed = false;

    await readStream(response, {
      onProgress: (msg) => {
        punchUploadProgress.value = msg.percent || 0;
        punchProgressDetail.value = msg.detail || msg.phase || "Processing...";
      },
      onComplete: (msg) => {
        completed = true;
        punchUploadProgress.value = 100;
        punchProgressDetail.value = "Complete!";

        punchImportResult.value = {
          success: true,
          imported: msg.imported || 0,
          updated: msg.updated || 0,
          skipped: msg.skipped || 0,
          failed: msg.failed || 0,
          errors: msg.errors || [],
        };

        toast.success(
          `Punch records imported successfully! ${msg.imported || 0} records created, ${msg.updated || 0} updated.`,
        );
      },
      onError: (msg) => {
        completed = true;
        throw new Error(msg.message || "Import failed on server");
      },
    });

    if (!completed) {
      throw new Error(
        `Stream ended without completion message. Last status: ${punchProgressDetail.value || "No server status received"}`,
      );
    }
  } catch (error) {
    toast.error(error.message || "Failed to import punch records");
    punchImportResult.value = {
      success: false,
      imported: 0,
      updated: 0,
      skipped: 0,
      failed: 0,
      errors: [{ row: 0, error: error.message }],
    };
  } finally {
    importingPunch.value = false;
    punchUploadProgress.value = 0;
    punchProcessing.value = false;
    punchProgressDetail.value = "";
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

// File Requirements Accordion - Modern Collapsible Design
.requirements-accordion {
  margin: 16px 16px;
  max-width: 95%;

  :deep(.v-expansion-panel) {
    background: linear-gradient(
      135deg,
      rgba(237, 152, 95, 0.04) 0%,
      rgba(247, 185, 128, 0.02) 100%
    ) !important;
    border: 1px solid rgba(237, 152, 95, 0.15) !important;
    border-radius: 12px !important;
    box-shadow: none !important;
    overflow: hidden !important;
  }

  :deep(.v-expansion-panel-title) {
    min-height: 48px !important;
    padding: 12px 48px 12px 16px !important;
    justify-content: flex-start !important;
  }

  :deep(.v-expansion-panel-title__overlay) {
    display: none !important;
  }

  :deep(.v-expansion-panel-title__icon) {
    position: absolute !important;
    right: 12px !important;
    margin-inline-start: 0 !important;
  }

  :deep(.v-expansion-panel-text__wrapper) {
    padding: 0 16px 16px 16px !important;
  }
}

.requirements-panel-title {
  font-size: 13px;
  font-weight: 700;
  color: #001f3d;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  display: flex;
  align-items: center;
  gap: 8px;

  span {
    font-size: 13px;
    font-weight: 700;
  }
}

.requirements-panel-text {
  padding: 0 !important;
}

.requirements-grid {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.requirement-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px;
  background: white;
  border-radius: 10px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  transition: all 0.2s ease;

  &:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
    transform: translateY(-1px);
  }

  &.required .requirement-icon {
    background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
  }

  &.optional .requirement-icon {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
  }

  &.format .requirement-icon {
    background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  }
}

.requirement-icon {
  width: 28px;
  height: 28px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);

  .v-icon {
    color: white !important;
  }
}

.requirement-content {
  flex: 1;
  min-width: 0;
}

.requirement-label {
  font-size: 11px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
}

.requirement-value {
  font-size: 13px;
  color: #001f3d;
  line-height: 1.5;
  font-weight: 500;
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

.conversion-badge-row {
  padding: 0 24px 12px;
}

.conversion-preparing {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  font-size: 12px;
  color: #64748b;
}

.conversion-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
  padding: 8px 10px;
  border-radius: 8px;
  background: rgba(59, 130, 246, 0.08);
  border: 1px solid rgba(59, 130, 246, 0.2);
  color: #1d4ed8;
}

.conversion-badge-converted {
  background: rgba(34, 197, 94, 0.08);
  border-color: rgba(34, 197, 94, 0.22);
  color: #15803d;
}

.conversion-badge-fallback {
  background: rgba(245, 158, 11, 0.1);
  border-color: rgba(245, 158, 11, 0.28);
  color: #b45309;
}

.conversion-title {
  font-size: 12px;
  font-weight: 700;
}

.conversion-meta {
  font-size: 12px;
  font-weight: 600;
}

.conversion-meta.muted {
  color: #64748b;
  font-weight: 500;
}

/* Progress Container */
.progress-container {
  padding: 16px 24px;
  background: rgba(237, 152, 95, 0.05);
  border-top: 1px solid rgba(237, 152, 95, 0.15);
}

.progress-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 8px;
}

.progress-label {
  font-size: 13px;
  font-weight: 600;
  color: #001f3d;
}

.progress-percentage {
  font-size: 12px;
  font-weight: 700;
  color: #ed985f;
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
