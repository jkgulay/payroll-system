<template>
  <div class="import-page">
    <!-- Modern Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="page-title-section">
          <div class="page-icon-badge">
            <v-icon size="20">mdi-file-upload</v-icon>
          </div>
          <div>
            <h1 class="page-title">Import Employees</h1>
            <p class="page-subtitle">
              Bulk import employee data from Excel files
            </p>
          </div>
        </div>
        <div class="action-buttons">
          <button
            class="action-btn action-btn-secondary"
            @click="downloadTemplate"
          >
            <v-icon size="20">mdi-download</v-icon>
            <span>Download Template</span>
          </button>
        </div>
      </div>
    </div>

    <v-card class="modern-card">
      <v-card-text>
        <v-alert type="info" variant="tonal" class="mb-4">
          <p class="font-weight-bold mb-2">Import Process:</p>
          <ol>
            <li>
              <strong>Step 1:</strong> Set default values (optional - for
              employees without Position or Project)
            </li>
            <li>
              <strong>Step 2:</strong> Upload Excel file with employee data
            </li>
            <li>
              <strong>Step 3:</strong> Backend processes and imports employees
            </li>
          </ol>
          <p class="mt-2 text-body-2">
            This method is fast - similar to the biometric import process.
          </p>
        </v-alert>

        <!-- Step 1: Upload File -->
        <v-card v-if="step === 1" class="mb-4" variant="outlined">
          <v-card-title class="text-h6">
            <v-icon class="mr-2">mdi-numeric-1-circle</v-icon>
            Upload Employee Data
          </v-card-title>
          <v-card-text>
            <!-- Bulk Defaults -->
            <v-card variant="outlined" class="mb-4">
              <v-card-title class="text-subtitle-1">
                <v-icon class="mr-2">mdi-format-list-bulleted</v-icon>
                Default Values (Optional)
              </v-card-title>
              <v-card-text>
                <p class="text-body-2 text-medium-emphasis mb-3">
                  Apply these defaults to employees without Position or Project
                  data
                </p>
                <v-row>
                  <v-col cols="12" md="6">
                    <v-autocomplete
                      v-model="defaultPosition"
                      :items="positionOptions"
                      label="Default Position (Job Role)"
                      placeholder="Select from Pay Rates"
                      density="compact"
                      clearable
                      hint="For employees without Position"
                      persistent-hint
                    >
                      <template v-slot:prepend-inner>
                        <v-icon size="small">mdi-briefcase</v-icon>
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
                      clearable
                      hint="For employees without Department"
                      persistent-hint
                    >
                      <template v-slot:prepend-inner>
                        <v-icon size="small">mdi-folder-outline</v-icon>
                      </template>
                    </v-autocomplete>
                  </v-col>
                </v-row>
              </v-card-text>
            </v-card>

            <div
              class="drop-zone"
              :class="{ 'drop-zone-active': isDragging }"
              @drop.prevent="handleDrop"
              @dragover.prevent="isDragging = true"
              @dragleave.prevent="isDragging = false"
              @click="$refs.fileInput.click()"
              style="cursor: pointer"
            >
              <v-icon size="64" color="primary" class="mb-4">
                mdi-cloud-upload
              </v-icon>
              <p class="text-h6 mb-2">
                {{
                  selectedFile
                    ? selectedFile.name
                    : "Drop employee file here or click to browse"
                }}
              </p>
              <p class="text-caption text-grey mt-4">
                Supported formats: Excel (.xlsx, .xls)
              </p>
              <input
                ref="fileInput"
                type="file"
                accept=".xlsx,.xls"
                style="display: none"
                @change="handleFileSelect"
              />
            </div>

            <div
              v-if="selectedFile"
              class="mt-4 d-flex justify-space-between align-center"
            >
              <div class="d-flex align-center">
                <v-icon color="success" class="mr-2">mdi-file-check</v-icon>
                <span
                  >{{ selectedFile.name }} ({{
                    formatFileSize(selectedFile.size)
                  }})</span
                >
              </div>
              <v-btn
                color="primary"
                @click="submitImport"
                :loading="importing"
                :disabled="!selectedFile"
              >
                Import Employees
              </v-btn>
            </div>

            <v-alert v-if="uploadError" type="error" class="mt-4">
              {{ uploadError }}
            </v-alert>
          </v-card-text>
        </v-card>

        <!-- Step 2: Import Progress -->
        <v-card v-if="step === 2" class="mb-4" variant="outlined">
          <v-card-title class="d-flex align-center">
            <v-icon class="mr-2">mdi-upload</v-icon>
            <span>Importing Employees...</span>
          </v-card-title>
          <v-card-text class="py-6">
            <v-progress-linear
              indeterminate
              color="primary"
              height="25"
              rounded
              class="mb-2"
            >
            </v-progress-linear>
            <div class="text-center text-body-2 text-medium-emphasis">
              {{ importStatus }}
            </div>
          </v-card-text>
        </v-card>

        <!-- Step 3: Results -->
        <v-card v-if="step === 3" variant="outlined">
          <v-card-title class="d-flex align-center">
            <v-icon class="mr-2">mdi-check-circle</v-icon>
            Import Complete
          </v-card-title>
          <v-card-text>
            <v-alert
              :type="importResult.failed > 0 ? 'warning' : 'success'"
              class="mb-4"
            >
              <div class="text-h6 mb-2">Import Summary</div>
              <div>
                ✅ Successfully imported:
                <strong>{{ importResult.imported }}</strong> employees
              </div>
              <div v-if="importResult.failed > 0">
                ❌ Failed: <strong>{{ importResult.failed }}</strong> employees
              </div>
            </v-alert>

            <!-- Errors List -->
            <v-card
              v-if="importResult.errors && importResult.errors.length > 0"
              class="mb-4"
            >
              <v-card-title class="text-subtitle-1 text-error">
                <v-icon class="mr-2">mdi-alert-circle</v-icon>
                Import Errors
              </v-card-title>
              <v-card-text>
                <v-list density="compact">
                  <v-list-item
                    v-for="(error, index) in importResult.errors"
                    :key="index"
                  >
                    <v-list-item-title>
                      Row {{ error.row }}: {{ error.staff_code }}
                    </v-list-item-title>
                    <v-list-item-subtitle class="text-error">
                      {{ error.error }}
                    </v-list-item-subtitle>
                  </v-list-item>
                </v-list>
              </v-card-text>
            </v-card>

            <div class="d-flex justify-space-between">
              <v-btn color="grey" @click="resetImport"> Import More </v-btn>
              <v-btn color="primary" :to="{ name: 'employees' }">
                View Employees
              </v-btn>
            </div>
          </v-card-text>
        </v-card>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup>
import { ref, computed } from "vue";
import { useRouter } from "vue-router";
import api from "../../services/api";
import { useToast } from "vue-toastification";
import { onMounted } from "vue";

const router = useRouter();
const toast = useToast();

// Load position rates and projects on mount
onMounted(async () => {
  try {
    const [positionResponse, projectResponse] = await Promise.all([
      api.get("/position-rates"),
      api.get("/projects"),
    ]);

    // Create a map of position name -> daily rate
    positionRates.value = {};
    positionResponse.data.forEach((rate) => {
      positionRates.value[rate.position_name] = rate.daily_rate;
    });

    // Store projects for dropdown
    projects.value = projectResponse.data;
  } catch (error) {
    console.error("Failed to load data:", error);
  }
});

const step = ref(1);
const isDragging = ref(false);
const uploadError = ref("");
const selectedFile = ref(null);
const importing = ref(false);
const importStatus = ref(""); // Current status message
const importResult = ref({ imported: 0, failed: 0, errors: [] });
const positionRates = ref({}); // Store position name -> daily rate mapping
const projects = ref([]); // Available projects

// Default values to apply during import
const defaultPosition = ref("");
const defaultProject = ref(null);

// Position options from pay rates table
const positionOptions = computed(() => {
  return Object.keys(positionRates.value).sort();
});

// Project options
const projectOptions = computed(() => {
  return projects.value.map((p) => ({
    title: p.name,
    value: p.id,
  }));
});

const handleDrop = (e) => {
  isDragging.value = false;
  const files = e.dataTransfer.files;
  if (files.length > 0) {
    const file = files[0];
    if (file && (file.name.endsWith(".xls") || file.name.endsWith(".xlsx"))) {
      selectedFile.value = file;
      uploadError.value = "";
    } else {
      uploadError.value = "Please upload a valid Excel file (.xls or .xlsx)";
    }
  }
};

const handleFileSelect = (e) => {
  const files = e.target.files;
  if (files.length > 0) {
    const file = files[0];
    if (file && (file.name.endsWith(".xls") || file.name.endsWith(".xlsx"))) {
      selectedFile.value = file;
      uploadError.value = "";
    } else {
      uploadError.value = "Please upload a valid Excel file (.xls or .xlsx)";
    }
  }
};

const formatFileSize = (bytes) => {
  if (bytes === 0) return "0 Bytes";
  const k = 1024;
  const sizes = ["Bytes", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + " " + sizes[i];
};

const submitImport = async () => {
  if (!selectedFile.value) return;

  importing.value = true;
  step.value = 2; // Show progress
  uploadError.value = "";

  try {
    importStatus.value = "Uploading file...";

    const formData = new FormData();
    formData.append("file", selectedFile.value);

    // Add default values if set
    if (defaultPosition.value) {
      formData.append("default_position", defaultPosition.value);
    }
    if (defaultProject.value) {
      formData.append("default_project_id", defaultProject.value);
    }

    importStatus.value = "Processing employees...";

    const response = await api.post("/employees/import-file", formData, {
      headers: { "Content-Type": "multipart/form-data" },
      timeout: 300000, // 5 minutes
    });

    importResult.value = {
      imported: response.data.imported,
      failed: response.data.failed,
      errors: response.data.errors || [],
    };

    step.value = 3;

    if (response.data.failed === 0) {
      toast.success(
        `Successfully imported ${response.data.imported} employees!`,
      );
    } else {
      toast.warning(
        `Imported ${response.data.imported} employees, ${response.data.failed} failed.`,
      );
    }
  } catch (error) {
    console.error("Import error:", error);

    let errorMessage = "Failed to import employees";

    if (error.response?.data?.message) {
      errorMessage = error.response.data.message;
    } else if (error.message) {
      errorMessage = error.message;
    }

    uploadError.value = errorMessage;
    toast.error(errorMessage, { timeout: 10000 });
    step.value = 1; // Go back to upload
  } finally {
    importing.value = false;
  }
};

const downloadTemplate = async () => {
  try {
    const response = await api.get("/employees/import/template");
    const template = response.data.template;

    // Simple download - create CSV or use backend-generated Excel
    const rows = [
      Object.keys(template[0]),
      ...template.map((obj) => Object.values(obj)),
    ];
    const csvContent = rows.map((row) => row.join(",")).join("\\n");
    const blob = new Blob([csvContent], { type: "text/csv" });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute("download", "employee_import_template.csv");
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);

    toast.success("Template downloaded!");
  } catch (error) {
    console.error("Template download error:", error);
    toast.error("Failed to download template");
  }
};

const resetImport = () => {
  step.value = 1;
  selectedFile.value = null;
  uploadError.value = "";
  importing.value = false;
  importStatus.value = "";
  importResult.value = { imported: 0, failed: 0, errors: [] };
  defaultPosition.value = "";
  defaultProject.value = null;
};
</script>

<style scoped lang="scss">
.import-page {
  padding: 24px;
}

.page-header {
  margin-bottom: 24px;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
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
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);
  color: white;
}

.page-title {
  font-size: 28px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
  letter-spacing: -0.5px;
}

.page-subtitle {
  font-size: 14px;
  color: #64748b;
  margin: 4px 0 0 0;
}

.action-buttons {
  display: flex;
  gap: 12px;
}

.action-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  border-radius: 10px;
  border: none;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.action-btn-secondary {
  background: white;
  color: #001f3d;
  border: 1.5px solid rgba(0, 31, 61, 0.15);
}

.action-btn-secondary:hover {
  background: rgba(0, 31, 61, 0.04);
  border-color: rgba(0, 31, 61, 0.25);
}

.modern-card {
  border-radius: 16px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  border: 1px solid rgba(0, 31, 61, 0.08);
}

.drop-zone {
  border: 2px dashed rgba(237, 152, 95, 0.3);
  border-radius: 16px;
  padding: 60px 20px;
  text-align: center;
  transition: all 0.3s ease;
  background-color: rgba(237, 152, 95, 0.02);
}

.drop-zone-active {
  border-color: #ed985f;
  background-color: rgba(237, 152, 95, 0.08);
}

.drop-zone:hover {
  border-color: #ed985f;
  background-color: rgba(237, 152, 95, 0.05);
}

.error-field {
  background-color: #ffebee;
}
</style>
