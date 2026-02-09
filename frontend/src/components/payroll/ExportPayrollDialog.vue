<template>
  <v-dialog v-model="dialog" max-width="700px" persistent>
    <v-card>
      <v-card-title class="text-h5 bg-primary">
        Export Payroll PDF
        <v-spacer></v-spacer>
        <v-btn icon @click="closeDialog" size="small" variant="text">
          <v-icon>mdi-close</v-icon>
        </v-btn>
      </v-card-title>

      <v-divider></v-divider>

      <v-card-text class="pt-4">
        <v-form ref="form" v-model="valid">
          <!-- Export Type Selection -->
          <v-radio-group
            v-model="exportType"
            label="Export Type"
            :rules="[(v) => !!v || 'Export type is required']"
          >
            <v-radio label="All Employees" value="all"></v-radio>
            <v-radio label="By Employee" value="employee"></v-radio>
            <v-radio label="By Department/Location" value="project"></v-radio>
          </v-radio-group>

          <!-- Employee Filter -->
          <v-autocomplete
            v-if="exportType === 'employee'"
            v-model="selectedEmployee"
            :items="employees"
            item-title="full_name"
            item-value="id"
            label="Select Employee"
            prepend-icon="mdi-account"
            :rules="[(v) => !!v || 'Employee is required']"
            clearable
          ></v-autocomplete>

          <!-- Department Filter -->
          <v-autocomplete
            v-if="exportType === 'project'"
            v-model="selectedProject"
            :items="projects"
            item-title="name"
            item-value="id"
            label="Select Department/Location"
            prepend-icon="mdi-briefcase"
            :rules="[(v) => !!v || 'Department is required']"
            hint="Departments are grouped by location/description"
            persistent-hint
            clearable
          ></v-autocomplete>

          <v-divider class="my-4"></v-divider>

          <!-- Signatures Section -->
          <h3 class="text-subtitle-1 mb-3">Signatures</h3>

          <!-- Prepared By -->
          <div class="mb-4">
            <v-label class="text-caption font-weight-medium"
              >Prepared By (1-2 persons)</v-label
            >
            <v-combobox
              v-model="signatures.prepared_by"
              chips
              multiple
              closable-chips
              prepend-icon="mdi-account-edit"
              placeholder="Enter names"
              :rules="[validateSignatureCount]"
              hint="Press Enter after each name"
              persistent-hint
            ></v-combobox>
          </div>

          <!-- Checked & Verified By -->
          <div class="mb-4">
            <v-label class="text-caption font-weight-medium"
              >Checked & Verified By (1-2 persons)</v-label
            >
            <v-combobox
              v-model="signatures.checked_by"
              chips
              multiple
              closable-chips
              prepend-icon="mdi-account-check"
              placeholder="Enter names"
              :rules="[validateSignatureCount]"
              hint="Press Enter after each name"
              persistent-hint
            ></v-combobox>
          </div>

          <!-- Recommended By -->
          <div class="mb-4">
            <v-label class="text-caption font-weight-medium"
              >Recommended By (1-2 persons)</v-label
            >
            <v-combobox
              v-model="signatures.recommended_by"
              chips
              multiple
              closable-chips
              prepend-icon="mdi-account-star"
              placeholder="Enter names"
              :rules="[validateSignatureCount]"
              hint="Press Enter after each name"
              persistent-hint
            ></v-combobox>
          </div>

          <!-- Approved By -->
          <div class="mb-4">
            <v-label class="text-caption font-weight-medium"
              >Approved By (1-2 persons)</v-label
            >
            <v-combobox
              v-model="signatures.approved_by"
              chips
              multiple
              closable-chips
              prepend-icon="mdi-account-check-outline"
              placeholder="Enter names"
              :rules="[validateSignatureCount]"
              hint="Press Enter after each name"
              persistent-hint
            ></v-combobox>
          </div>
        </v-form>
      </v-card-text>

      <v-divider></v-divider>

      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn
          color="grey"
          variant="text"
          @click="closeDialog"
          :disabled="loading"
        >
          Cancel
        </v-btn>
        <v-btn
          color="primary"
          variant="flat"
          @click="exportPDF"
          :loading="loading"
          :disabled="!valid"
        >
          <v-icon left>mdi-file-pdf-box</v-icon>
          Generate PDF
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, watch, onMounted } from "vue";
import { useToast } from "vue-toastification";
import api from "@/services/api";
import { devLog } from "@/utils/devLog";

const props = defineProps({
  modelValue: Boolean,
  payrollId: {
    type: Number,
    required: true,
  },
});

const emit = defineEmits(["update:modelValue", "exported"]);

const toast = useToast();

// Dialog state
const dialog = ref(props.modelValue);
const valid = ref(false);
const loading = ref(false);
const form = ref(null);

// Export options
const exportType = ref("all");
const selectedEmployee = ref(null);
const selectedProject = ref(null);

// Data lists
const employees = ref([]);
const projects = ref([]);

// Signatures
const signatures = ref({
  prepared_by: [],
  checked_by: [],
  recommended_by: [],
  approved_by: [],
});

// Watch dialog state
watch(
  () => props.modelValue,
  (val) => {
    dialog.value = val;
    if (val) {
      resetForm();
      loadData();
    }
  },
);

watch(dialog, (val) => {
  emit("update:modelValue", val);
});

// Validate signature count (1-2 persons)
const validateSignatureCount = (value) => {
  if (!value || value.length === 0) {
    return "At least 1 person is required";
  }
  if (value.length > 2) {
    return "Maximum 2 persons allowed";
  }
  return true;
};

// Load employees and projects
const loadData = async () => {
  try {
    const [empResponse, projResponse] = await Promise.all([
      api.get("/employees", { params: { status: "active", per_page: 1000 } }),
      api.get("/projects"),
    ]);

    employees.value = empResponse.data.data || empResponse.data;
    projects.value = projResponse.data.data || projResponse.data;
  } catch (error) {
    devLog.error("Error loading data:", error);
    toast.error("Failed to load employees and departments");
  }
};

// Reset form
const resetForm = () => {
  exportType.value = "all";
  selectedEmployee.value = null;
  selectedProject.value = null;
  signatures.value = {
    prepared_by: [],
    checked_by: [],
    recommended_by: [],
    approved_by: [],
  };
  if (form.value) {
    form.value.resetValidation();
  }
};

// Close dialog
const closeDialog = () => {
  dialog.value = false;
  resetForm();
};

// Export PDF
const exportPDF = async () => {
  if (!form.value.validate()) {
    return;
  }

  loading.value = true;

  try {
    const payload = {
      type: exportType.value,
      signatures: signatures.value,
    };

    // Add filter based on export type
    if (exportType.value === "employee") {
      payload.filter_id = selectedEmployee.value;
    } else if (exportType.value === "project") {
      payload.filter_id = selectedProject.value;
    }

    const response = await api.post(
      `/payroll/${props.payrollId}/export-comprehensive-pdf`,
      payload,
      {
        responseType: "blob",
      },
    );

    // Create download link
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;

    // Extract filename from Content-Disposition header or generate one
    const contentDisposition = response.headers["content-disposition"];
    let filename = `payroll_${props.payrollId}.pdf`;

    if (contentDisposition) {
      const filenameMatch = contentDisposition.match(/filename="?(.+)"?/);
      if (filenameMatch && filenameMatch[1]) {
        filename = filenameMatch[1];
      }
    }

    link.setAttribute("download", filename);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);

    toast.success("Payroll PDF exported successfully");
    emit("exported");
    closeDialog();
  } catch (error) {
    devLog.error("Export error:", error);
    const message = error.response?.data?.message || "Failed to export PDF";
    toast.error(message);
  } finally {
    loading.value = false;
  }
};

onMounted(() => {
  if (dialog.value) {
    loadData();
  }
});
</script>

<style scoped>
.v-label {
  opacity: 1;
  color: rgba(0, 0, 0, 0.87);
}
</style>
