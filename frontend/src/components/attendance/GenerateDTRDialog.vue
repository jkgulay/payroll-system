<template>
  <v-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    max-width="600"
    persistent
  >
    <v-card class="modern-dialog">
      <div class="dialog-header">
        <div class="dialog-icon-wrapper">
          <v-icon size="20">mdi-file-document</v-icon>
        </div>
        <div>
          <div class="dialog-title">Generate Daily Time Record</div>
          <div class="dialog-subtitle">Export attendance records as PDF</div>
        </div>
      </div>

      <v-card-text class="pt-6">
        <v-alert type="info" variant="tonal" class="mb-4">
          Generate a Daily Time Record (DTR) for an employee with signature
          section. Perfect for daily attendance forms or period summaries.
        </v-alert>

        <v-form ref="form" v-model="valid">
          <v-row>
            <v-col cols="6">
              <v-text-field
                v-model="searchQuery"
                label="Search Employees"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-magnify"
                clearable
                placeholder="Search by name, number..."
                @input="filterEmployees"
              ></v-text-field>
            </v-col>

            <v-col cols="3">
              <v-autocomplete
                v-model="filterDepartment"
                :items="departments"
                label="Department"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-domain"
                clearable
                @update:model-value="filterEmployees"
              ></v-autocomplete>
            </v-col>

            <v-col cols="3">
              <v-autocomplete
                v-model="filterStaffType"
                :items="staffTypes"
                label="Staff Type"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-account-group"
                clearable
                @update:model-value="filterEmployees"
              ></v-autocomplete>
            </v-col>

            <v-col cols="12">
              <v-autocomplete
                v-model="formData.employee_id"
                :items="filteredEmployees"
                item-title="full_name"
                item-value="id"
                label="Select Employee *"
                :rules="[rules.required]"
                :loading="loadingEmployees"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-account"
              >
                <template v-slot:item="{ props, item }">
                  <v-list-item v-bind="props">
                    <template v-slot:subtitle>
                      {{ item.raw.employee_number }} -
                      {{ item.raw.position || "N/A" }}
                      <span v-if="item.raw.department">
                        | {{ item.raw.department }}</span
                      >
                    </template>
                  </v-list-item>
                </template>
              </v-autocomplete>
            </v-col>

            <v-col cols="12">
              <v-radio-group v-model="reportType" inline>
                <v-radio
                  label="Period Range (Multiple Days)"
                  value="range"
                ></v-radio>
                <v-radio label="Single Day" value="daily"></v-radio>
              </v-radio-group>
            </v-col>

            <v-col cols="12" v-if="reportType === 'daily'">
              <v-text-field
                v-model="formData.date"
                label="Date *"
                type="date"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-calendar"
                :max="today"
              ></v-text-field>
            </v-col>

            <v-col cols="6" v-if="reportType === 'range'">
              <v-text-field
                v-model="formData.date_from"
                label="Date From *"
                type="date"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-calendar-start"
                :max="today"
              ></v-text-field>
            </v-col>

            <v-col cols="6" v-if="reportType === 'range'">
              <v-text-field
                v-model="formData.date_to"
                label="Date To *"
                type="date"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-calendar-end"
                :max="today"
              ></v-text-field>
            </v-col>

            <v-col cols="12" v-if="reportType === 'range'">
              <v-btn
                block
                variant="tonal"
                @click="setThisMonth"
                prepend-icon="mdi-calendar-month"
              >
                This Month
              </v-btn>
            </v-col>
          </v-row>
        </v-form>

        <v-alert v-if="preview" type="success" variant="tonal" class="mt-4">
          <div class="text-subtitle-2 font-weight-bold mb-2">Preview</div>
          <div><strong>Employee:</strong> {{ preview.employee.full_name }}</div>
          <div><strong>Period:</strong> {{ formatPreviewPeriod() }}</div>
          <div>
            <strong>Total Days Present:</strong>
            {{ preview.totals.days_present }}
          </div>
          <div>
            <strong>Total Hours:</strong> {{ preview.totals.regular_hours }} hrs
          </div>
          <div>
            <strong>Overtime:</strong> {{ Math.floor(preview.totals.overtime_hours || 0) }} hrs
          </div>
        </v-alert>
      </v-card-text>

      <div class="dialog-divider"></div>
      <div class="dialog-actions">
        <button
          v-if="reportType === 'range'"
          class="dialog-btn dialog-btn-secondary"
          @click="loadPreview"
          :disabled="!valid || reportType === 'daily' || previewing"
        >
          <v-icon v-if="previewing" size="16" class="rotating"
            >mdi-loading</v-icon
          >
          <v-icon v-else size="16">mdi-eye</v-icon>
          <span>{{ previewing ? "Loading..." : "Preview" }}</span>
        </button>
        <div style="flex: 1"></div>
        <button class="dialog-btn dialog-btn-cancel" @click="close">
          Cancel
        </button>
        <button
          class="dialog-btn dialog-btn-primary"
          @click="generate"
          :disabled="!valid || generating"
        >
          <v-icon v-if="generating" size="16" class="rotating"
            >mdi-loading</v-icon
          >
          <v-icon v-else size="16">mdi-download</v-icon>
          <span>{{ generating ? "Generating..." : "Generate PDF" }}</span>
        </button>
      </div>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, reactive, watch, onMounted } from "vue";
import api from "@/services/api";
import { useToast } from "vue-toastification";
import { devLog } from "@/utils/devLog";

const props = defineProps({
  modelValue: Boolean,
});

const emit = defineEmits(["update:modelValue"]);
const toast = useToast();

const form = ref(null);
const valid = ref(false);
const generating = ref(false);
const previewing = ref(false);
const loadingEmployees = ref(false);
const employees = ref([]);
const filteredEmployees = ref([]);
const reportType = ref("range");
const preview = ref(null);
const searchQuery = ref("");
const filterDepartment = ref(null);
const filterStaffType = ref(null);
const departments = ref([]);
const staffTypes = ref([]);

const today = new Date().toISOString().split("T")[0];

const formData = reactive({
  employee_id: null,
  date_from: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000)
    .toISOString()
    .split("T")[0],
  date_to: today,
  date: today,
});

const rules = {
  required: (v) => !!v || "This field is required",
};

const loadEmployees = async () => {
  loadingEmployees.value = true;
  try {
    const response = await api.get("/employees", {
      params: {
        per_page: 10000, // Get all employees
      },
    });
    employees.value = response.data.data || response.data || [];
    filteredEmployees.value = employees.value;

    // Extract unique departments and staff types
    const uniqueDepartments = [
      ...new Set(
        employees.value
          .map((e) => e.department)
          .filter((d) => d && d !== "N/A"),
      ),
    ].sort();
    departments.value = uniqueDepartments;

    const uniqueStaffTypes = [
      ...new Set(
        employees.value
          .map((e) => e.staff_type)
          .filter((s) => s && s !== "N/A"),
      ),
    ].sort();
    staffTypes.value = uniqueStaffTypes;
  } catch (error) {
    toast.error("Failed to load employees");
  } finally {
    loadingEmployees.value = false;
  }
};

const filterEmployees = () => {
  let result = [...employees.value];

  // Filter by search query
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase();
    result = result.filter(
      (emp) =>
        emp.full_name?.toLowerCase().includes(query) ||
        emp.employee_number?.toLowerCase().includes(query) ||
        emp.first_name?.toLowerCase().includes(query) ||
        emp.last_name?.toLowerCase().includes(query) ||
        emp.position?.toLowerCase().includes(query),
    );
  }

  // Filter by department
  if (filterDepartment.value) {
    result = result.filter((emp) => emp.department === filterDepartment.value);
  }

  // Filter by staff type
  if (filterStaffType.value) {
    result = result.filter((emp) => emp.staff_type === filterStaffType.value);
  }

  filteredEmployees.value = result;
};

const setThisMonth = () => {
  const now = new Date();
  const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
  formData.date_from = firstDay.toISOString().split("T")[0];
  formData.date_to = today;
};

const loadPreview = async () => {
  if (!valid.value || reportType.value === "daily") return;

  previewing.value = true;
  try {
    const response = await api.post("/attendance/dtr/preview", {
      employee_id: formData.employee_id,
      date_from: formData.date_from,
      date_to: formData.date_to,
    });
    preview.value = response.data;
  } catch (error) {
    toast.error("Failed to load preview");
  } finally {
    previewing.value = false;
  }
};

const formatPreviewPeriod = () => {
  if (!preview.value) return "";
  return reportType.value === "daily"
    ? new Date(formData.date).toLocaleDateString()
    : `${new Date(formData.date_from).toLocaleDateString()} - ${new Date(
        formData.date_to,
      ).toLocaleDateString()}`;
};

const generate = async () => {
  if (!valid.value) return;

  generating.value = true;
  try {
    const endpoint =
      reportType.value === "daily"
        ? "/attendance/dtr/generate-daily"
        : "/attendance/dtr/generate";

    const payload =
      reportType.value === "daily"
        ? { employee_id: formData.employee_id, date: formData.date }
        : {
            employee_id: formData.employee_id,
            date_from: formData.date_from,
            date_to: formData.date_to,
          };

    const response = await api.post(endpoint, payload, {
      responseType: "blob",
    });

    // Create download link
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;

    const employee = employees.value.find((e) => e.id === formData.employee_id);
    const filename =
      reportType.value === "daily"
        ? `DTR_${employee?.employee_number}_${formData.date}.pdf`
        : `DTR_${employee?.employee_number}_${formData.date_from}_${formData.date_to}.pdf`;

    link.setAttribute("download", filename);
    document.body.appendChild(link);
    link.click();
    link.remove();

    toast.success("DTR generated successfully!");
    close();
  } catch (error) {
    devLog.error("Generation error:", error);
    toast.error(error.response?.data?.message || "Failed to generate DTR");
  } finally {
    generating.value = false;
  }
};

const close = () => {
  preview.value = null;
  emit("update:modelValue", false);
};

watch(
  () => props.modelValue,
  (newVal) => {
    if (newVal) {
      // Reset form
      formData.employee_id = null;
      formData.date = today;
      formData.date_from = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000)
        .toISOString()
        .split("T")[0];
      formData.date_to = today;
      reportType.value = "range";
      preview.value = null;
      searchQuery.value = "";
      filterDepartment.value = null;
      filterStaffType.value = null;
      filteredEmployees.value = employees.value;
    }
  },
);

onMounted(() => {
  loadEmployees();
});
</script>

<style scoped lang="scss">
.modern-dialog {
  border-radius: 16px !important;
}

.dialog-header {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 24px;
  background: linear-gradient(
    135deg,
    rgba(0, 31, 61, 0.02) 0%,
    rgba(237, 152, 95, 0.02) 100%
  );
  border-bottom: 1px solid rgba(0, 31, 61, 0.08);
}

.dialog-icon-wrapper {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.25);

  .v-icon {
    color: #ffffff !important;
  }
}

.dialog-title {
  font-size: 20px;
  font-weight: 700;
  color: #001f3d;
  margin-bottom: 4px;
}

.dialog-subtitle {
  font-size: 13px;
  color: rgba(0, 31, 61, 0.6);
}

.dialog-divider {
  height: 1px;
  background: rgba(0, 31, 61, 0.08);
}

.dialog-actions {
  padding: 16px 24px;
  background: rgba(0, 31, 61, 0.02);
  border-top: 1px solid rgba(0, 31, 61, 0.08);
  display: flex;
  gap: 12px;
}

.dialog-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;

  &.dialog-btn-cancel {
    background: rgba(0, 31, 61, 0.06);
    color: rgba(0, 31, 61, 0.8);
    border: 1px solid rgba(0, 31, 61, 0.1);

    &:hover {
      background: rgba(0, 31, 61, 0.1);
    }
  }

  &.dialog-btn-secondary {
    background: rgba(237, 152, 95, 0.1);
    color: #ed985f;
    border: 1px solid rgba(237, 152, 95, 0.2);

    .v-icon {
      color: #ed985f !important;
    }

    &:not(:disabled):hover {
      background: rgba(237, 152, 95, 0.15);
      border-color: rgba(237, 152, 95, 0.3);
    }

    &:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
  }

  &.dialog-btn-primary {
    background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);

    .v-icon {
      color: #ffffff !important;
    }

    &:not(:disabled):hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(237, 152, 95, 0.4);
    }

    &:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
  }
}

@keyframes rotate {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.rotating {
  animation: rotate 1s linear infinite;
}
</style>
