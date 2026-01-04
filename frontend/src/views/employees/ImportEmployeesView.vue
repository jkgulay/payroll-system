<template>
  <v-container fluid class="pa-6">
    <v-card>
      <v-card-title class="d-flex align-center justify-space-between">
        <div class="d-flex align-center">
          <v-icon class="mr-2">mdi-file-upload</v-icon>
          <span>Import Employees</span>
        </div>
        <v-btn
          color="primary"
          variant="text"
          @click="downloadTemplate"
          prepend-icon="mdi-download"
        >
          Download Template
        </v-btn>
      </v-card-title>

      <v-divider></v-divider>

      <v-card-text>
        <!-- Step 1: Upload File -->
        <v-card v-if="step === 1" class="mb-4" variant="outlined">
          <v-card-title class="text-h6">
            <v-icon class="mr-2">mdi-numeric-1-circle</v-icon>
            Upload Employee Data
          </v-card-title>
          <v-card-text>
            <div
              class="drop-zone"
              :class="{ 'drop-zone-active': isDragging }"
              @drop.prevent="handleDrop"
              @dragover.prevent="isDragging = true"
              @dragleave.prevent="isDragging = false"
            >
              <v-icon size="64" color="primary" class="mb-4">
                mdi-cloud-upload
              </v-icon>
              <p class="text-h6 mb-2">Drag & Drop your Excel/CSV file here</p>
              <p class="text-body-2 text-grey mb-4">or</p>
              <v-btn color="primary" @click="$refs.fileInput.click()">
                Browse Files
              </v-btn>
              <input
                ref="fileInput"
                type="file"
                accept=".xlsx,.xls,.csv"
                style="display: none"
                @change="handleFileSelect"
              />
              <p class="text-caption text-grey mt-4">
                Supported formats: Excel (.xlsx, .xls), CSV (.csv)
              </p>
            </div>

            <v-alert v-if="uploadError" type="error" class="mt-4">
              {{ uploadError }}
            </v-alert>
          </v-card-text>
        </v-card>

        <!-- Step 2: Preview & Fill Missing Data -->
        <v-card v-if="step === 2" class="mb-4" variant="outlined">
          <v-card-title class="d-flex align-center justify-space-between">
            <div class="d-flex align-center">
              <v-icon class="mr-2">mdi-numeric-2-circle</v-icon>
              <span
                >Preview & Complete Data ({{
                  employees.length
                }}
                employees)</span
              >
            </div>
            <div>
              <v-btn color="grey" variant="text" @click="step = 1" class="mr-2">
                Back
              </v-btn>
              <v-btn color="primary" @click="submitImport" :loading="importing">
                Import Employees
              </v-btn>
            </div>
          </v-card-title>

          <v-card-text>
            <v-alert type="info" class="mb-4" variant="tonal">
              <p class="font-weight-bold mb-2">Fill missing required fields:</p>
              <ul>
                <li>
                  <strong>Position:</strong> Job title (e.g., Mason, Foreman,
                  Helper)
                </li>
                <li>
                  <strong>Basic Salary:</strong> Daily rate (minimum ₱570)
                </li>
                <li>
                  <strong>Employment Type:</strong> Full-time, Part-time, or
                  Contractual
                </li>
              </ul>
            </v-alert>

            <!-- Bulk Actions -->
            <v-card class="mb-4" variant="outlined">
              <v-card-title class="text-subtitle-1">
                <v-icon class="mr-2">mdi-format-list-bulleted</v-icon>
                Apply to All
              </v-card-title>
              <v-card-text>
                <v-row>
                  <v-col cols="12" md="4">
                    <v-autocomplete
                      v-model="bulkDefaults.position"
                      :items="positionOptions"
                      label="Default Position"
                      placeholder="Select from Pay Rates"
                      density="compact"
                      hide-details
                      clearable
                      hint="Positions from Pay Rates page"
                    >
                      <template v-slot:prepend-inner>
                        <v-icon size="small">mdi-briefcase</v-icon>
                      </template>
                    </v-autocomplete>
                  </v-col>
                  <v-col cols="12" md="4">
                    <v-text-field
                      v-model.number="bulkDefaults.basic_salary"
                      label="Default Salary (Daily)"
                      type="number"
                      prefix="₱"
                      density="compact"
                      hide-details
                      readonly
                      :placeholder="
                        bulkDefaults.position
                          ? 'Auto-filled from position'
                          : '450'
                      "
                      :hint="
                        bulkDefaults.position
                          ? 'Auto-set from selected position'
                          : 'Select a position first'
                      "
                      persistent-hint
                    >
                      <template v-slot:prepend-inner>
                        <v-icon size="small">mdi-currency-php</v-icon>
                      </template>
                    </v-text-field>
                  </v-col>
                  <v-col cols="12" md="4">
                    <v-select
                      v-model="bulkDefaults.employment_type"
                      label="Employment Type"
                      :items="['full-time', 'part-time', 'contractual']"
                      density="compact"
                      hide-details
                    ></v-select>
                  </v-col>
                </v-row>
                <v-btn
                  color="primary"
                  variant="text"
                  class="mt-2"
                  @click="applyBulkDefaults"
                  size="small"
                >
                  Apply to All Employees
                </v-btn>
              </v-card-text>
            </v-card>

            <!-- Employee List -->
            <v-data-table
              :headers="previewHeaders"
              :items="employees"
              :items-per-page="10"
              class="elevation-1"
            >
              <template v-slot:item.name="{ item }">
                <div class="d-flex align-center">
                  <v-avatar color="primary" size="32" class="mr-2">
                    <span class="text-white text-caption">
                      {{ getInitials(item.name) }}
                    </span>
                  </v-avatar>
                  <div>
                    <div class="font-weight-medium">{{ item.name }}</div>
                    <div class="text-caption text-grey">
                      {{ item.staff_code }}
                    </div>
                  </div>
                </div>
              </template>

              <template v-slot:item.position="{ item }">
                <v-text-field
                  v-model="item.position"
                  density="compact"
                  hide-details
                  placeholder="Position"
                  :class="{ 'error-field': !item.position }"
                ></v-text-field>
              </template>

              <template v-slot:item.basic_salary="{ item }">
                <v-text-field
                  v-model.number="item.basic_salary"
                  type="number"
                  density="compact"
                  hide-details
                  prefix="₱"
                  placeholder="570"
                  :class="{
                    'error-field':
                      !item.basic_salary || item.basic_salary < 570,
                  }"
                ></v-text-field>
              </template>

              <template v-slot:item.employment_type="{ item }">
                <v-select
                  v-model="item.employment_type"
                  :items="['full-time', 'part-time', 'contractual']"
                  density="compact"
                  hide-details
                  :class="{ 'error-field': !item.employment_type }"
                ></v-select>
              </template>

              <template v-slot:item.entry_status="{ item }">
                <v-chip :color="getStatusColor(item.entry_status)" size="small">
                  {{ item.entry_status }}
                </v-chip>
              </template>
            </v-data-table>
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
  </v-container>
</template>

<script setup>
import { ref, computed, watch } from "vue";
import { useRouter } from "vue-router";
import api from "../../services/api";
import * as XLSX from "xlsx/xlsx.mjs";
import { useToast } from "vue-toastification";
import { onMounted } from "vue";

const router = useRouter();
const toast = useToast();

// Load position rates on mount
onMounted(async () => {
  try {
    const response = await api.get("/position-rates");
    // Create a map of position name -> daily rate
    positionRates.value = {};
    response.data.forEach((rate) => {
      positionRates.value[rate.position_name] = rate.daily_rate;
    });
  } catch (error) {
    console.error("Failed to load position rates:", error);
  }
});

const step = ref(1);
const isDragging = ref(false);
const uploadError = ref("");
const employees = ref([]);
const importing = ref(false);
const importResult = ref({ imported: 0, failed: 0, errors: [] });
const positionRates = ref({}); // Store position name -> daily rate mapping
const missingPositions = ref(new Set()); // Track positions not in pay rates table

const bulkDefaults = ref({
  position: "",
  basic_salary: null,
  employment_type: "full-time",
});

// Position options from pay rates table
const positionOptions = computed(() => {
  return Object.keys(positionRates.value).sort();
});

// Watch for position changes in bulk defaults and auto-fill salary
watch(
  () => bulkDefaults.value.position,
  (newPosition) => {
    if (newPosition && positionRates.value[newPosition]) {
      bulkDefaults.value.basic_salary = positionRates.value[newPosition];
    } else {
      bulkDefaults.value.basic_salary = null;
    }
  }
);

const previewHeaders = [
  { title: "Employee", key: "name", sortable: true },
  { title: "Gender", key: "gender", sortable: true },
  { title: "Entry Date", key: "entry_date", sortable: true },
  { title: "Position", key: "position", sortable: false },
  { title: "Daily Salary", key: "basic_salary", sortable: false },
  { title: "Type", key: "employment_type", sortable: false },
  { title: "Status", key: "entry_status", sortable: true },
];

const handleDrop = (e) => {
  isDragging.value = false;
  const files = e.dataTransfer.files;
  if (files.length > 0) {
    processFile(files[0]);
  }
};

const handleFileSelect = (e) => {
  const files = e.target.files;
  if (files.length > 0) {
    processFile(files[0]);
  }
};

const processFile = async (file) => {
  uploadError.value = "";

  // Validate file type
  const validTypes = [
    "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
    "application/vnd.ms-excel",
    "text/csv",
  ];

  if (
    !validTypes.includes(file.type) &&
    !file.name.match(/\.(xlsx|xls|csv)$/i)
  ) {
    uploadError.value = "Invalid file type. Please upload Excel or CSV file.";
    return;
  }

  try {
    const data = await readFile(file);
    parseEmployeeData(data);
    step.value = 2;
  } catch (error) {
    uploadError.value = "Failed to read file: " + error.message;
  }
};

const readFile = (file) => {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();

    reader.onload = (e) => {
      try {
        const data = new Uint8Array(e.target.result);
        const workbook = XLSX.read(data, { type: "array" });
        const firstSheet = workbook.Sheets[workbook.SheetNames[0]];
        const jsonData = XLSX.utils.sheet_to_json(firstSheet);
        resolve(jsonData);
      } catch (error) {
        reject(error);
      }
    };

    reader.onerror = () => reject(new Error("Failed to read file"));
    reader.readAsArrayBuffer(file);
  });
};

const parseEmployeeData = (data) => {
  missingPositions.value.clear();
  employees.value = data.map((row) => {
    const position = row["Position"] || row["position"] || "";
    let basic_salary = row["Basic Salary"] || row["basic_salary"] || null;

    // Auto-apply salary from position rates if position exists but no salary
    if (position && !basic_salary) {
      if (positionRates.value[position]) {
        basic_salary = positionRates.value[position];
      } else {
        // Track positions not in pay rates table
        missingPositions.value.add(position);
        basic_salary = 450; // Default rate (consistent with Add Employee dialog)
      }
    } else if (!basic_salary) {
      basic_salary = 450;
    }

    return {
      staff_code: row["Staff Code"] || row["staff_code"] || "",
      name: row["Name"] || row["name"] || "",
      department: row["Department"] || row["department"] || "",
      gender: row["Gender"] || row["gender"] || "Male",
      card_no: row["Card No"] || row["card_no"] || "",
      punch_password: row["Punch Password"] || row["punch_password"] || "",
      mobile_no: row["Mobile No"] || row["mobile_no"] || "",
      email: row["Email"] || row["email"] || "",
      entry_date: formatDate(row["Entry Date"] || row["entry_date"]),
      entry_status: row["Entry Status"] || row["entry_status"] || "",
      fingerprint_face:
        row["Fingerprint/Face"] || row["fingerprint_face"] || "",
      // Fields auto-filled based on position rates
      position: position,
      basic_salary: basic_salary,
      employment_type:
        row["Employment Type"] || row["employment_type"] || "full-time",
    };
  });

  // Show warning if positions are missing from pay rates
  if (missingPositions.value.size > 0) {
    const positions = Array.from(missingPositions.value).join(", ");
    toast.warning(
      `${missingPositions.value.size} position(s) not found in Pay Rates: ${positions}. Using default rate (₱450/day). Add these positions in Payroll > Pay Rates.`,
      { timeout: 8000 }
    );
  }
};

const formatDate = (dateValue) => {
  if (!dateValue) return "";

  // Handle Excel date serial number
  if (typeof dateValue === "number") {
    const excelEpoch = new Date(1899, 11, 30);
    const date = new Date(excelEpoch.getTime() + dateValue * 86400000);
    return date.toISOString().split("T")[0];
  }

  // Handle string dates
  if (typeof dateValue === "string") {
    const date = new Date(dateValue);
    if (!isNaN(date.getTime())) {
      return date.toISOString().split("T")[0];
    }
  }

  return dateValue;
};

const applyBulkDefaults = () => {
  employees.value.forEach((emp) => {
    // Apply position
    if (bulkDefaults.value.position && !emp.position) {
      emp.position = bulkDefaults.value.position;
    }

    // Auto-apply salary based on position if not already set
    if (emp.position && !emp.basic_salary) {
      // Try to get rate from position rates
      if (positionRates.value[emp.position]) {
        emp.basic_salary = positionRates.value[emp.position];
      } else if (bulkDefaults.value.basic_salary) {
        // Fallback to bulk default salary
        emp.basic_salary = bulkDefaults.value.basic_salary;
      } else {
        // Ultimate fallback to default rate (consistent with Add Employee dialog)
        emp.basic_salary = 450;
      }
    } else if (bulkDefaults.value.basic_salary && !emp.basic_salary) {
      // Apply bulk default salary if no position match
      emp.basic_salary = bulkDefaults.value.basic_salary;
    } else if (!emp.basic_salary) {
      // Ensure everyone has a salary
      emp.basic_salary = 450;
    }

    // Apply employment type
    if (bulkDefaults.value.employment_type && !emp.employment_type) {
      emp.employment_type = bulkDefaults.value.employment_type;
    }
  });
  toast.success("Bulk defaults applied! Salaries auto-set based on positions.");
};

const submitImport = async () => {
  // Validate required fields
  const invalid = employees.value.filter(
    (emp) => !emp.position || !emp.basic_salary || !emp.employment_type
  );

  if (invalid.length > 0) {
    toast.error(
      `${invalid.length} employee(s) have missing required fields. Please fill Position, Salary, and Employment Type.`
    );
    return;
  }

  importing.value = true;

  // Show a toast for large imports
  if (employees.value.length > 50) {
    toast.info(
      `Importing ${employees.value.length} employees... This may take a few minutes.`,
      {
        timeout: 10000,
      }
    );
  }

  try {
    // Clean up empty strings and convert to null
    const cleanedEmployees = employees.value.map((emp) => {
      const cleaned = {};
      Object.keys(emp).forEach((key) => {
        // Convert empty strings to null, but keep actual values
        cleaned[key] = emp[key] === "" ? null : emp[key];
      });
      // Ensure gender has a default value
      if (!cleaned.gender) {
        cleaned.gender = "Male";
      }
      return cleaned;
    });

    console.log(`Sending ${cleanedEmployees.length} employees to import`);
    console.log("First employee sample:", cleanedEmployees[0]);

    const response = await api.post(
      "/employees/import",
      {
        employees: cleanedEmployees,
      },
      {
        timeout: 300000, // 5 minutes for very large imports
      }
    );

    importResult.value = response.data;
    step.value = 3;

    if (response.data.failed === 0) {
      toast.success(
        `Successfully imported ${response.data.imported} employees!`
      );
    } else {
      toast.warning(
        `Imported ${response.data.imported} employees, ${response.data.failed} failed.`
      );
    }
  } catch (error) {
    console.error("Import error:", error);
    console.error("Error response:", error.response);
    console.error("Error data:", error.response?.data);
    console.error("Error code:", error.code);
    console.error("Error message:", error.message);

    let errorMessage = "Failed to import employees";

    // Check for timeout error
    if (error.code === "ECONNABORTED" || error.message.includes("timeout")) {
      errorMessage = `Request timed out. You're importing ${employees.value.length} employees. Try importing in smaller batches (max 100 at a time).`;
    } else if (error.response?.data?.message) {
      errorMessage = error.response.data.message;
    } else if (error.response?.data?.errors) {
      const errors = error.response.data.errors;
      const firstError = Object.values(errors)[0];
      errorMessage = Array.isArray(firstError) ? firstError[0] : firstError;
    } else if (error.message) {
      errorMessage = error.message;
    }

    toast.error(errorMessage, { timeout: 10000 });
  } finally {
    importing.value = false;
  }
};

const downloadTemplate = async () => {
  try {
    const response = await api.get("/employees/import/template");
    const template = response.data.template;

    // Create Excel file
    const ws = XLSX.utils.json_to_sheet(template);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Employees");

    // Add instructions sheet
    const instructions = [
      {
        Field: "staff_code",
        Required: "Yes",
        Description: "Employee number (unique)",
      },
      { Field: "name", Required: "Yes", Description: "Full name" },
      { Field: "gender", Required: "Yes", Description: "Male or Female" },
      {
        Field: "entry_date",
        Required: "Yes",
        Description: "Date hired (YYYY-MM-DD)",
      },
      {
        Field: "entry_status",
        Required: "Yes",
        Description: "Official/Regular/Probationary/Contractual",
      },
      { Field: "position", Required: "Yes", Description: "Job position" },
      {
        Field: "basic_salary",
        Required: "Yes",
        Description: "Daily rate (minimum 570)",
      },
      {
        Field: "punch_password",
        Required: "No",
        Description: "Biometric device PIN",
      },
      { Field: "mobile_no", Required: "No", Description: "Mobile number" },
      { Field: "email", Required: "No", Description: "Email address" },
      {
        Field: "department",
        Required: "No",
        Description: "Department name (for reference)",
      },
    ];

    const wsInstructions = XLSX.utils.json_to_sheet(instructions);
    XLSX.utils.book_append_sheet(wb, wsInstructions, "Instructions");

    // Download
    XLSX.writeFile(wb, "employee_import_template.xlsx");
    toast.success("Template downloaded!");
  } catch (error) {
    console.error("Template download error:", error);
    toast.error("Failed to download template");
  }
};

const resetImport = () => {
  step.value = 1;
  employees.value = [];
  uploadError.value = "";
  importResult.value = { imported: 0, failed: 0, errors: [] };
  bulkDefaults.value = {
    position: "",
    basic_salary: null,
    employment_type: "full-time",
  };
};

const getInitials = (name) => {
  if (!name) return "??";
  const parts = name.split(" ");
  return parts.length > 1
    ? parts[0][0] + parts[parts.length - 1][0]
    : parts[0][0];
};

const getStatusColor = (status) => {
  const colors = {
    official: "success",
    regular: "success",
    probationary: "warning",
    probation: "warning",
    contractual: "info",
    contract: "info",
  };
  return colors[status?.toLowerCase()] || "grey";
};
</script>

<style scoped>
.drop-zone {
  border: 2px dashed #ccc;
  border-radius: 8px;
  padding: 60px 20px;
  text-align: center;
  transition: all 0.3s ease;
  background-color: #fafafa;
}

.drop-zone-active {
  border-color: #1976d2;
  background-color: #e3f2fd;
}

.drop-zone:hover {
  border-color: #1976d2;
  background-color: #f5f5f5;
}

.error-field {
  background-color: #ffebee;
}
</style>
