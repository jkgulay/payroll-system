<template>
  <v-dialog v-model="show" max-width="900px" persistent>
    <v-card>
      <v-card-title class="text-h5 py-4 bg-primary">
        <v-icon start>mdi-account-plus</v-icon>
        Add Employee
      </v-card-title>
      <v-divider></v-divider>

      <v-card-text class="pt-6">
        <v-form ref="employeeForm">
          <v-row>
            <!-- Personal Information -->
            <v-col cols="12">
              <div class="text-h6 mb-2">Personal Information</div>
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
                label="Middle Name (Optional)"
                variant="outlined"
                density="comfortable"
                hint="Optional"
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
                v-model="formData.email"
                label="Email (Optional)"
                type="email"
                prepend-inner-icon="mdi-email"
                :rules="[rules.emailOptional]"
                variant="outlined"
                density="comfortable"
                hint="If no email, username will be firstname.lastname"
                persistent-hint
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                v-model="formData.mobile_number"
                label="Phone Number"
                prepend-inner-icon="mdi-phone"
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                v-model="formData.date_of_birth"
                label="Date of Birth"
                type="date"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-select
                v-model="formData.gender"
                :items="[
                  { title: 'Male', value: 'male' },
                  { title: 'Female', value: 'female' },
                  { title: 'Other', value: 'other' },
                ]"
                label="Gender"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
              ></v-select>
            </v-col>

            <v-col cols="12">
              <v-textarea
                v-model="formData.worker_address"
                label="Worker Address"
                prepend-inner-icon="mdi-map-marker"
                rows="1"
                hint="Enter the worker's complete home address"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
              ></v-textarea>
            </v-col>

            <!-- Employment Information -->
            <v-col cols="12">
              <div class="text-h6 mb-2 mt-4">Employment Information</div>
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
              <v-select
                v-model="formData.contract_type"
                :items="[
                  { title: 'Regular', value: 'regular' },
                  { title: 'Probationary', value: 'probationary' },
                  { title: 'Contractual', value: 'contractual' },
                ]"
                label="Contract Type"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
                hint="Type of employment contract"
                persistent-hint
              ></v-select>
            </v-col>

            <v-col cols="12" md="6">
              <v-select
                v-model="formData.activity_status"
                :items="[
                  { title: 'Active', value: 'active' },
                  { title: 'On Leave', value: 'on_leave' },
                  { title: 'Resigned', value: 'resigned' },
                  { title: 'Terminated', value: 'terminated' },
                  { title: 'Retired', value: 'retired' },
                ]"
                label="Activity Status"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
                hint="Current work status"
                persistent-hint
              ></v-select>
            </v-col>

            <v-col cols="12" md="6">
              <v-select
                v-model="formData.employment_type"
                :items="[
                  { title: 'Regular', value: 'regular' },
                  { title: 'Contractual', value: 'contractual' },
                  { title: 'Part Time', value: 'part_time' },
                ]"
                label="Employment Type"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
                hint="Type of employment contract"
                persistent-hint
              ></v-select>
            </v-col>

            <v-col cols="12" md="6">
              <v-select
                v-model="formData.salary_type"
                :items="[
                  { title: 'Daily', value: 'daily' },
                  { title: 'Monthly', value: 'monthly' },
                ]"
                label="Salary Type"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
              ></v-select>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                v-model.number="formData.basic_salary"
                label="Basic Pay Rate (Auto-Set)"
                type="number"
                prepend-inner-icon="mdi-cash"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
                readonly
                hint="Rate is automatically set based on position. Can be adjusted later."
                persistent-hint
                suffix="₱/day"
              ></v-text-field>
            </v-col>

            <!-- Allowances Section -->
            <v-col cols="12">
              <div class="text-h6 mb-2 mt-4">Allowances</div>
              <v-divider class="mb-2"></v-divider>
            </v-col>

            <v-col cols="12">
              <v-alert type="info" variant="tonal" density="compact">
                Allowances can be added after creating the employee through the
                Benefits > Allowances page. This allows for more flexible
                management with custom types, frequencies, and date ranges.
              </v-alert>
            </v-col>

            <!-- User Account Section -->
            <v-col cols="12">
              <div class="text-h6 mb-2 mt-4">User Account</div>
              <v-divider class="mb-4"></v-divider>
            </v-col>

            <v-col cols="12">
              <v-alert type="info" variant="tonal" density="compact">
                <ul class="mt-2">
                  <li>
                    <strong>Username:</strong> Email if provided, otherwise
                    firstname.lastname
                  </li>
                  <li>
                    <strong>Password:</strong> Auto-generated (LastName + EmpID
                    + 2 random digits)
                  </li>
                  <li><strong>Role:</strong> Selected below</li>
                  <li><strong>Status:</strong> Active</li>
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
                hint="Admin can assign accountant or employee role"
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
        <v-btn variant="text" @click="handleClose" :disabled="saving">
          Cancel
        </v-btn>
        <v-btn
          color="primary"
          variant="elevated"
          @click="handleSave"
          :loading="saving"
        >
          <v-icon start>mdi-content-save</v-icon>
          Save Employee
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch, onMounted } from "vue";
import { usePositionRates } from "@/composables/usePositionRates";

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

const emit = defineEmits(["update:modelValue", "save"]);

const { positionOptions, getRate, loadPositionRates } = usePositionRates();

const show = computed({
  get: () => props.modelValue,
  set: (value) => emit("update:modelValue", value),
});

const employeeForm = ref(null);
const saving = ref(false);

const formData = ref({
  employee_number: "",
  first_name: "",
  middle_name: "",
  last_name: "",
  date_of_birth: "",
  gender: "male",
  email: "",
  mobile_number: "",
  worker_address: "",
  project_id: null,
  position: "",
  date_hired: "",
  contract_type: "regular",
  activity_status: "active",
  employment_type: "regular",
  salary_type: "daily",
  basic_salary: 450,
  role: "employee",
});

const rules = {
  required: (v) => !!v || "This field is required",
  email: (v) => /.+@.+\..+/.test(v) || "Email must be valid",
  emailOptional: (v) => !v || /.+@.+\..+/.test(v) || "Email must be valid",
};

// Load position rates from database on mount
onMounted(async () => {
  await loadPositionRates();
});

// Update basic salary when position changes
function updateBasicSalary(position) {
  if (position) {
    formData.value.basic_salary = getRate(position);
  }
}

// Reset form when dialog closes
watch(show, (newVal) => {
  if (!newVal) {
    resetForm();
  }
});

function resetForm() {
  formData.value = {
    employee_number: "",
    first_name: "",
    middle_name: "",
    last_name: "",
    date_of_birth: "",
    gender: "",
    email: "",
    mobile_number: "",
    worker_address: "",
    project_id: null,
    position: "",
    date_hired: "",
    contract_type: "regular",
    activity_status: "active",
    employment_type: "regular",
    salary_type: "daily",
    basic_salary: 450,
    role: "employee",
  };
  if (employeeForm.value) {
    employeeForm.value.resetValidation();
  }
}

async function handleSave() {
  if (!employeeForm.value) return;

  const { valid } = await employeeForm.value.validate();
  if (!valid) return;

  saving.value = true;
  emit("save", {
    data: formData.value,
    setSaving: (val) => (saving.value = val),
  });
}

function handleClose() {
  if (!saving.value) {
    show.value = false;
  }
}
</script>
