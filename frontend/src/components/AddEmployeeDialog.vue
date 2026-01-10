<template>
  <v-dialog
    :model-value="modelValue"
    @update:model-value="handleDialogChange"
    max-width="1000px"
    persistent
    scrollable
  >
    <v-card>
      <v-card-title class="text-h5 py-4 bg-primary">
        <v-icon start>mdi-account-plus</v-icon>
        Add New Employee
      </v-card-title>
      <v-divider></v-divider>

      <v-card-text class="pt-4" style="max-height: 70vh">
        <v-form ref="employeeForm">
          <!-- Info Alert -->
          <v-alert
            type="info"
            variant="tonal"
            density="compact"
            class="mb-4"
            icon="mdi-information"
          >
            Complete all required fields. Employee number and credentials will
            be auto-generated upon saving.
          </v-alert>

          <v-row>
            <!-- Section 1: Personal Information -->
            <v-col cols="12">
              <div class="text-h6 mb-2 d-flex align-center">
                <v-icon start color="primary">mdi-account-circle</v-icon>
                Section 1: Personal Information
              </div>
              <v-divider class="mb-4"></v-divider>
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
              <div class="text-h6 mb-2 d-flex align-center">
                <v-icon start color="primary">mdi-briefcase</v-icon>
                Section 2: Employment Information
              </div>
              <v-divider class="mb-4"></v-divider>
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
              <div class="text-h6 mb-2 d-flex align-center">
                <v-icon start color="primary">mdi-file-document</v-icon>
                Section 3: Contract & Status
              </div>
              <v-divider class="mb-4"></v-divider>
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
              <div class="text-h6 mb-2 d-flex align-center">
                <v-icon start color="primary">mdi-account-key</v-icon>
                Section 4: User Account
              </div>
              <v-divider class="mb-4"></v-divider>
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

      <v-card-actions class="pa-4">
        <v-spacer></v-spacer>
        <v-btn
          variant="text"
          @click="handleClose"
          :disabled="saving"
          size="large"
        >
          Cancel
        </v-btn>
        <v-btn
          color="primary"
          variant="elevated"
          @click="handleSave"
          :loading="saving"
          size="large"
          prepend-icon="mdi-content-save"
        >
          Create Employee
        </v-btn>
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
