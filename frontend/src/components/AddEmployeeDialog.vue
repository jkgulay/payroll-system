<template>
  <v-dialog
    :model-value="modelValue"
    @update:model-value="handleDialogChange"
    max-width="1000px"
    persistent
    scrollable
  >
    <v-card class="modern-dialog">
      <v-card-title class="dialog-header">
        <div class="dialog-icon-wrapper primary">
          <v-icon size="24">mdi-account-plus</v-icon>
        </div>
        <div>
          <div class="dialog-title">Add New Employee</div>
          <div class="dialog-subtitle">Complete all required fields</div>
        </div>
      </v-card-title>
      <v-divider></v-divider>

      <v-card-text class="dialog-content" style="max-height: 70vh">
        <v-form ref="employeeForm">
          <!-- Info Alert -->
          <v-alert
            type="info"
            variant="tonal"
            density="compact"
            class="mb-4"
            icon="mdi-information"
          >
            Employee number and credentials will be auto-generated upon saving.
          </v-alert>

          <v-row>
            <!-- Section 1: Personal Information -->
            <v-col cols="12">
              <div class="section-header">
                <div class="section-icon">
                  <v-icon size="18">mdi-account-circle</v-icon>
                </div>
                <h3 class="section-title">Personal Information</h3>
              </div>
            </v-col>

            <v-col cols="12" md="4">
              <v-text-field
                v-model="formData.first_name"
                label="First Name"
                prepend-inner-icon="mdi-account"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="4">
              <v-text-field
                v-model="formData.middle_name"
                label="Middle Name"
                variant="outlined"
                density="comfortable"
                hint="Optional"
                persistent-hint
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="4">
              <v-text-field
                v-model="formData.last_name"
                label="Last Name"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                v-model="formData.date_of_birth"
                label="Date of Birth"
                type="date"
                prepend-inner-icon="mdi-calendar"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-select
                v-model="formData.gender"
                :items="GENDERS"
                label="Gender"
                prepend-inner-icon="mdi-gender-male-female"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
              ></v-select>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                v-model="formData.email"
                label="Email Address"
                type="email"
                prepend-inner-icon="mdi-email"
                :rules="[rules.emailOptional]"
                variant="outlined"
                density="comfortable"
                hint="Optional - Used for notifications"
                persistent-hint
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                v-model="formData.mobile_number"
                label="Mobile Number"
                prepend-inner-icon="mdi-cellphone"
                variant="outlined"
                density="comfortable"
                hint="Format: 09171234567"
                persistent-hint
              ></v-text-field>
            </v-col>

            <v-col cols="12">
              <v-textarea
                v-model="formData.worker_address"
                label="Complete Address"
                prepend-inner-icon="mdi-map-marker"
                rows="1"
                hint="Enter the worker's complete home address"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
              ></v-textarea>
            </v-col>

            <!-- Section 2: Employment Information -->
            <v-col cols="12" class="mt-4">
              <div class="section-header">
                <div class="section-icon">
                  <v-icon size="18">mdi-briefcase</v-icon>
                </div>
                <h3 class="section-title">Employment Information</h3>
              </div>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                v-model="formData.employee_number"
                label="Employee Number (Auto-Generated)"
                prepend-inner-icon="mdi-identifier"
                readonly
                variant="outlined"
                density="comfortable"
                hint="Will be automatically generated (EMP001, EMP002...)"
                persistent-hint
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-select
                v-model="formData.project_id"
                :items="projects"
                item-title="name"
                item-value="id"
                label="Project"
                prepend-inner-icon="mdi-office-building"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
              ></v-select>
            </v-col>

            <v-col cols="12" md="6">
              <v-select
                v-model="formData.position"
                :items="positionOptions"
                label="Position"
                prepend-inner-icon="mdi-badge-account"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
                hint="Daily rate will be set automatically based on position"
                persistent-hint
                @update:model-value="updateBasicSalary"
              ></v-select>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                v-model="formData.date_hired"
                label="Hire Date"
                type="date"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                :model-value="formatSalaryDisplay()"
                label="Daily Rate"
                prepend-inner-icon="mdi-cash"
                readonly
                variant="outlined"
                density="comfortable"
                hint="Based on selected position"
                persistent-hint
                prefix="₱"
                suffix="/day"
              ></v-text-field>
            </v-col>

            <!-- Section 3: Employment Type & Status -->
            <v-col cols="12" class="mt-4">
              <div class="section-header">
                <div class="section-icon">
                  <v-icon size="18">mdi-file-document</v-icon>
                </div>
                <h3 class="section-title">Contract & Status</h3>
              </div>
            </v-col>

            <v-col cols="12" md="6">
              <v-select
                v-model="formData.contract_type"
                :items="CONTRACT_TYPES"
                label="Contract Type"
                prepend-inner-icon="mdi-file-sign"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
                hint="Regular, Probationary, or Contractual"
                persistent-hint
              ></v-select>
            </v-col>

            <v-col cols="12" md="6">
              <v-select
                v-model="formData.activity_status"
                :items="ACTIVITY_STATUSES"
                label="Activity Status"
                prepend-inner-icon="mdi-account-check"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
                hint="Current employment status"
                persistent-hint
              ></v-select>
            </v-col>

            <v-col cols="12" md="6">
              <v-select
                v-model="formData.work_schedule"
                :items="WORK_SCHEDULES"
                label="Work Schedule"
                prepend-inner-icon="mdi-clock-outline"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
                hint="Full-time or Part-time"
                persistent-hint
              ></v-select>
            </v-col>

            <v-col cols="12" md="6">
              <v-select
                v-model="formData.salary_type"
                :items="SALARY_TYPES"
                label="Salary Type"
                prepend-inner-icon="mdi-cash-multiple"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
              ></v-select>
            </v-col>

            <!-- Section 4: User Account -->
            <v-col cols="12" class="mt-4">
              <div class="section-header">
                <div class="section-icon">
                  <v-icon size="18">mdi-account-key</v-icon>
                </div>
                <h3 class="section-title">User Account</h3>
              </div>
            </v-col>

            <v-col cols="12">
              <v-alert
                type="warning"
                variant="tonal"
                density="compact"
                class="mb-2"
                icon="mdi-key"
              >
                <div class="text-subtitle-2 mb-2">
                  Auto-Generated Credentials
                </div>
                <ul class="ml-4">
                  <li>Username will be firstname.lastname (lowercase)</li>
                  <li>
                    Temporary password: LastName + EmployeeNumber + 2 random
                    digits
                  </li>
                  <li>Employee must change password on first login</li>
                </ul>
              </v-alert>
            </v-col>

            <v-col cols="12" md="6">
              <v-select
                v-model="formData.role"
                :items="[
                  { title: 'Accountant', value: 'accountant' },
                  { title: 'Employee', value: 'employee' },
                ]"
                label="User Role"
                prepend-inner-icon="mdi-shield-account"
                :rules="[rules.required]"
                hint="System access level"
                persistent-hint
                variant="outlined"
                density="comfortable"
              ></v-select>
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>

      <v-divider></v-divider>

      <v-card-actions class="dialog-actions">
        <v-spacer></v-spacer>
        <button
          class="dialog-btn dialog-btn-cancel"
          @click="handleClose"
          :disabled="saving"
        >
          Cancel
        </button>
        <button
          class="dialog-btn dialog-btn-primary"
          @click="handleSave"
          :disabled="saving"
        >
          <v-progress-circular
            v-if="saving"
            indeterminate
            size="16"
            width="2"
            class="mr-2"
          ></v-progress-circular>
          <v-icon v-else size="18" class="mr-1">mdi-content-save</v-icon>
          {{ saving ? 'Creating...' : 'Create Employee' }}
        </button>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch, onMounted } from "vue";
import { usePositionRates } from "@/composables/usePositionRates";

const emit = defineEmits(["update:modelValue", "save"]);
const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false,
  },
  projects: {
    type: Array,
    default: () => [],
  },
});

const { positionOptions, getRate, loadPositionRates } = usePositionRates();

const employeeForm = ref(null);
const saving = ref(false);

// Constants - using value/title format for backend compatibility
const GENDERS = [
  { title: "Male", value: "male" },
  { title: "Female", value: "female" },
];
const WORK_SCHEDULES = [
  { title: "Full Time", value: "full_time" },
  { title: "Part Time", value: "part_time" },
];
const CONTRACT_TYPES = [
  { title: "Regular", value: "regular" },
  { title: "Probationary", value: "probationary" },
  { title: "Contractual", value: "contractual" },
];
const ACTIVITY_STATUSES = [
  { title: "Active", value: "active" },
  { title: "On Leave", value: "on_leave" },
  { title: "Resigned", value: "resigned" },
  { title: "Terminated", value: "terminated" },
];
const SALARY_TYPES = [
  { title: "Daily", value: "daily" },
  { title: "Monthly", value: "monthly" },
];

const formData = ref({
  // Personal Information
  first_name: "",
  middle_name: "",
  last_name: "",
  date_of_birth: null,
  gender: "",
  mobile_number: "",
  email: "",
  worker_address: "",

  // Employment Details
  employee_number: "",
  project_id: null,
  position: "",
  date_hired: null,
  work_schedule: "",
  contract_type: "",
  activity_status: "active",
  salary_type: "daily",
  basic_salary: 0,

  // User Account
  role: "employee",
});

const rules = {
  required: (v) => !!v || "This field is required",
  email: (v) => !v || /.+@.+\..+/.test(v) || "Email must be valid",
  emailOptional: (v) => !v || /.+@.+\..+/.test(v) || "Email must be valid",
  phone: (v) => !v || /^09\d{9}$/.test(v) || "Format: 09171234567",
};

// Load position rates on mount
onMounted(async () => {
  await loadPositionRates();
});

// Update basic salary when position changes
function updateBasicSalary(position) {
  if (position) {
    formData.value.basic_salary = getRate(position);
  }
}

// Format salary display
function formatSalaryDisplay() {
  if (!formData.value.position) return "0.00";

  const rate = getRate(formData.value.position);
  if (rate) {
    return rate.toLocaleString("en-US", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  }

  return "0.00";
}

const handleDialogChange = (value) => {
  if (!value) {
    handleClose();
  }
};

const handleClose = () => {
  emit("update:modelValue", false);
};

const handleSave = async () => {
  if (!employeeForm.value) return;

  const { valid } = await employeeForm.value.validate();
  if (!valid) return;

  saving.value = true;
  const callback = (value) => {
    saving.value = value;
  };
  emit("save", { data: formData.value, setSaving: callback });
};

watch(
  () => props.modelValue,
  (newVal) => {
    if (!newVal) {
      formData.value = {
        first_name: "",
        middle_name: "",
        last_name: "",
        date_of_birth: null,
        gender: "",
        mobile_number: "",
        email: "",
        worker_address: "",
        employee_number: "",
        project_id: null,
        position: "",
        date_hired: null,
        work_schedule: "",
        contract_type: "",
        activity_status: "active",
        salary_type: "daily",
        basic_salary: 0,
        role: "employee",
      };
      saving.value = false;
      if (employeeForm.value) {
        employeeForm.value.resetValidation();
      }
    }
  }
);
</script>

<style scoped lang="scss">
.modern-dialog {
  border-radius: 16px;
}

.dialog-header {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 24px;
  background: linear-gradient(135deg, rgba(0, 31, 61, 0.02) 0%, rgba(237, 152, 95, 0.02) 100%);
  border-bottom: 1px solid rgba(0, 31, 61, 0.08);
}

.dialog-icon-wrapper {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  
  &.primary {
    background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
    box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);
  }
}

.dialog-title {
  font-size: 20px;
  font-weight: 700;
  color: #001f3d;
  line-height: 1.2;
}

.dialog-subtitle {
  font-size: 13px;
  color: #64748b;
  margin-top: 2px;
}

.dialog-content {
  padding: 24px;
}

.section-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 20px;
  background: linear-gradient(135deg, rgba(0, 31, 61, 0.02) 0%, rgba(237, 152, 95, 0.02) 100%);
  border-radius: 12px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  margin-bottom: 20px;
}

.section-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.25);
}

.section-title {
  font-size: 16px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
  letter-spacing: -0.3px;
}

.dialog-actions {
  padding: 16px 24px;
  background: rgba(0, 31, 61, 0.02);
}

.dialog-btn {
  padding: 10px 24px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  
  &:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
}

.dialog-btn-cancel {
  background: transparent;
  color: #64748b;
  
  &:hover:not(:disabled) {
    background: rgba(0, 31, 61, 0.04);
  }
}

.dialog-btn-primary {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: white;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);
  margin-left: 12px;
  
  &:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(237, 152, 95, 0.4);
  }
}
</style>
