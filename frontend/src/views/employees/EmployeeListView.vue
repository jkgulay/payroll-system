<template>
  <div>
    <v-row class="mb-4">
      <v-col cols="12" md="6">
        <h1 class="text-h4 font-weight-bold">Employees</h1>
      </v-col>
      <v-col cols="12" md="6" class="text-right">
        <v-btn
          color="success"
          prepend-icon="mdi-file-upload"
          class="mr-2"
          :to="{ name: 'employees-import' }"
        >
          Import Employees
        </v-btn>
        <v-btn
          color="primary"
          prepend-icon="mdi-account-plus"
          @click="showAddEmployeeDialog = true"
        >
          Add Employee
        </v-btn>
      </v-col>
    </v-row>

    <v-card>
      <v-card-text>
        <v-row class="mb-4">
          <v-col cols="12" md="3">
            <v-text-field
              v-model="search"
              prepend-inner-icon="mdi-magnify"
              label="Search employees..."
              clearable
              @input="fetchEmployees"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.project_id"
              :items="projects"
              item-title="name"
              item-value="id"
              label="Project"
              clearable
              @update:model-value="fetchEmployees"
            ></v-select>
          </v-col>
          <v-col cols="12" md="2">
            <v-select
              v-model="filters.contract_type"
              :items="contractTypeOptions"
              label="Contract Type"
              clearable
              @update:model-value="fetchEmployees"
            ></v-select>
          </v-col>
          <v-col cols="12" md="2">
            <v-select
              v-model="filters.activity_status"
              :items="activityStatusOptions"
              label="Activity Status"
              clearable
              @update:model-value="fetchEmployees"
            ></v-select>
          </v-col>
          <v-col cols="12" md="2">
            <v-select
              v-model="filters.employment_type"
              :items="employmentTypeOptions"
              label="Type"
              clearable
              @update:model-value="fetchEmployees"
            ></v-select>
          </v-col>
          <v-col cols="12" md="2">
            <v-select
              v-model="filters.position"
              :items="positionOptions"
              label="Position"
              clearable
              @update:model-value="fetchEmployees"
            ></v-select>
          </v-col>
        </v-row>

        <v-data-table
          :headers="headers"
          :items="employeeStore.employees"
          :loading="employeeStore.loading"
          :items-per-page="20"
          hover
        >
          <template v-slot:item.employee_number="{ item }">
            <strong>{{ item.employee_number }}</strong>
          </template>

          <template v-slot:item.full_name="{ item }">
            {{ item.first_name }} {{ item.middle_name }} {{ item.last_name }}
          </template>

          <template v-slot:item.gender="{ item }">
            <v-chip
              :color="
                item.gender === 'Male' || item.gender === 'male'
                  ? 'blue'
                  : item.gender === 'Female' || item.gender === 'female'
                  ? 'pink'
                  : 'grey'
              "
              size="small"
              variant="tonal"
            >
              {{
                item.gender
                  ? item.gender.charAt(0).toUpperCase() + item.gender.slice(1)
                  : "N/A"
              }}
            </v-chip>
          </template>

          <template v-slot:item.project="{ item }">
            {{ item.project?.name || "N/A" }}
          </template>

          <template v-slot:item.position="{ item }">
            {{ item.position || "N/A" }}
          </template>

          <template v-slot:item.employment_type="{ item }">
            <v-chip
              size="small"
              :color="getEmploymentTypeColor(item.employment_type)"
            >
              {{ item.employment_type }}
            </v-chip>
          </template>

          <template v-slot:item.is_active="{ item }">
            <v-chip size="small" :color="item.is_active ? 'success' : 'error'">
              {{ item.is_active ? "Active" : "Inactive" }}
            </v-chip>
          </template>

          <template v-slot:item.actions="{ item }">
            <v-btn
              icon="mdi-eye"
              size="small"
              variant="text"
              color="info"
              @click="viewEmployee(item)"
              title="View Details"
            ></v-btn>
            <v-btn
              icon="mdi-pencil"
              size="small"
              variant="text"
              color="primary"
              @click="editEmployee(item)"
              title="Edit Employee"
            ></v-btn>
            <v-menu>
              <template v-slot:activator="{ props }">
                <v-btn
                  icon="mdi-dots-vertical"
                  size="small"
                  variant="text"
                  v-bind="props"
                  title="More Actions"
                ></v-btn>
              </template>
              <v-list>
                <v-list-item @click="viewCredentials(item)">
                  <template v-slot:prepend>
                    <v-icon color="info">mdi-account-key</v-icon>
                  </template>
                  <v-list-item-title>View Credentials</v-list-item-title>
                </v-list-item>
                <v-divider></v-divider>
                <v-list-item @click="suspendEmployee(item)">
                  <template v-slot:prepend>
                    <v-icon color="warning">mdi-pause-circle</v-icon>
                  </template>
                  <v-list-item-title>Suspend</v-list-item-title>
                </v-list-item>
                <v-list-item @click="terminateEmployee(item)">
                  <template v-slot:prepend>
                    <v-icon color="error">mdi-account-remove</v-icon>
                  </template>
                  <v-list-item-title>Terminate</v-list-item-title>
                </v-list-item>
                <v-divider></v-divider>
                <v-list-item @click="deleteEmployee(item)" class="text-error">
                  <template v-slot:prepend>
                    <v-icon color="error">mdi-delete</v-icon>
                  </template>
                  <v-list-item-title>Delete</v-list-item-title>
                </v-list-item>
              </v-list>
            </v-menu>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <!-- Employee Details/Edit Dialog -->
    <v-dialog v-model="showEmployeeDialog" max-width="1000px" scrollable>
      <v-card>
        <v-card-title
          class="text-h5 py-4"
          :class="isEditing ? 'bg-primary' : 'bg-info'"
        >
          <v-icon start>{{ isEditing ? "mdi-pencil" : "mdi-eye" }}</v-icon>
          {{ isEditing ? "Edit Employee" : "Employee Details" }}
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-6" style="max-height: 600px">
          <v-form ref="employeeForm" v-if="selectedEmployee">
            <v-row>
              <!-- Personal Information -->
              <v-col cols="12">
                <div class="text-h6 mb-2">Personal Information</div>
                <v-divider class="mb-4"></v-divider>
              </v-col>

              <v-col cols="12" md="3">
                <v-text-field
                  v-model="selectedEmployee.employee_number"
                  label="Employee Number"
                  readonly
                  variant="outlined"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="3">
                <v-text-field
                  v-model="selectedEmployee.first_name"
                  label="First Name"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'plain'"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="3">
                <v-text-field
                  v-model="selectedEmployee.middle_name"
                  label="Middle Name"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'plain'"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="3">
                <v-text-field
                  v-model="selectedEmployee.last_name"
                  label="Last Name"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'plain'"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="4">
                <v-text-field
                  v-model="selectedEmployee.email"
                  label="Email"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'plain'"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="4">
                <v-text-field
                  v-model="selectedEmployee.mobile_number"
                  label="Mobile Number"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'plain'"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="4">
                <v-text-field
                  v-model="selectedEmployee.date_of_birth"
                  label="Date of Birth"
                  type="date"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'plain'"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="4">
                <v-select
                  v-model="selectedEmployee.gender"
                  :items="[
                    { title: 'Male', value: 'male' },
                    { title: 'Female', value: 'female' },
                    { title: 'Other', value: 'other' },
                  ]"
                  label="Gender"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'plain'"
                  density="comfortable"
                ></v-select>
              </v-col>

              <v-col cols="12">
                <v-textarea
                  v-model="selectedEmployee.worker_address"
                  label="Address"
                  rows="2"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'plain'"
                  density="comfortable"
                ></v-textarea>
              </v-col>

              <!-- Employment Information -->
              <v-col cols="12">
                <div class="text-h6 mb-2 mt-4">Employment Information</div>
                <v-divider class="mb-4"></v-divider>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="selectedEmployee.project_id"
                  :items="projects"
                  item-title="name"
                  item-value="id"
                  label="Project"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'plain'"
                  density="comfortable"
                ></v-select>
              </v-col>

              <v-col cols="12" md="6">
                <v-autocomplete
                  v-model="selectedEmployee.position"
                  :items="positionOptions"
                  label="Position"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'plain'"
                  density="comfortable"
                  clearable
                  hint="Salary will auto-update based on position"
                  :persistent-hint="isEditing"
                >
                  <template v-slot:prepend-inner v-if="isEditing">
                    <v-icon size="small">mdi-briefcase</v-icon>
                  </template>
                </v-autocomplete>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="selectedEmployee.date_hired"
                  label="Date Hired"
                  type="date"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'plain'"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="selectedEmployee.contract_type"
                  :items="[
                    { title: 'Regular', value: 'regular' },
                    { title: 'Probationary', value: 'probationary' },
                    { title: 'Contractual', value: 'contractual' },
                  ]"
                  label="Contract Type"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'plain'"
                  density="comfortable"
                ></v-select>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="selectedEmployee.activity_status"
                  :items="[
                    { title: 'Active', value: 'active' },
                    { title: 'On Leave', value: 'on_leave' },
                    { title: 'Resigned', value: 'resigned' },
                    { title: 'Terminated', value: 'terminated' },
                    { title: 'Retired', value: 'retired' },
                  ]"
                  label="Activity Status"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'plain'"
                  density="comfortable"
                ></v-select>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="selectedEmployee.employment_type"
                  :items="[
                    { title: 'Regular', value: 'regular' },
                    { title: 'Contractual', value: 'contractual' },
                    { title: 'Part Time', value: 'part_time' },
                  ]"
                  label="Employment Type"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'plain'"
                  density="comfortable"
                ></v-select>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="selectedEmployee.salary_type"
                  :items="[
                    { title: 'Daily', value: 'daily' },
                    { title: 'Monthly', value: 'monthly' },
                  ]"
                  label="Salary Type"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'plain'"
                  density="comfortable"
                ></v-select>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model.number="selectedEmployee.basic_salary"
                  label="Basic Salary"
                  type="number"
                  prefix="₱"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'plain'"
                  density="comfortable"
                ></v-text-field>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-btn
            v-if="!isEditing"
            color="primary"
            variant="elevated"
            @click="isEditing = true"
          >
            <v-icon start>mdi-pencil</v-icon>
            Edit
          </v-btn>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="closeDialog">
            {{ isEditing ? "Cancel" : "Close" }}
          </v-btn>
          <v-btn
            v-if="isEditing"
            color="primary"
            variant="elevated"
            @click="saveEmployee"
            :loading="saving"
          >
            <v-icon start>mdi-check</v-icon>
            Save Changes
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Add Employee Dialog (Reusable Component) -->
    <AddEmployeeDialog
      v-model="showAddEmployeeDialog"
      :projects="projects"
      @save="saveNewEmployee"
    />

    <!-- Temporary Password Dialog -->
    <v-dialog v-model="showPasswordDialog" max-width="600px" persistent>
      <v-card>
        <v-card-title class="text-h5 py-4 bg-success">
          <v-icon start>mdi-shield-check</v-icon>
          Employee Account Created
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-6">
          <v-alert type="success" variant="tonal" class="mb-4">
            <div class="text-h6 mb-2">
              {{ createdEmployee?.employee_number }} -
              {{ createdEmployee?.first_name }} {{ createdEmployee?.last_name }}
            </div>
          </v-alert>

          <div class="mb-4">
            <div class="text-subtitle-1 font-weight-bold mb-2">
              Login Credentials:
            </div>
            <v-sheet color="grey-lighten-4" rounded class="pa-4">
              <div class="mb-3">
                <div class="text-caption">Username</div>
                <div class="text-body-1 font-weight-bold">
                  {{
                    createdEmployeeUsername || createdEmployee?.email || "N/A"
                  }}
                </div>
              </div>
              <div class="mb-3" v-if="createdEmployee?.email">
                <div class="text-caption">Email</div>
                <div class="text-body-1 font-weight-bold">
                  {{ createdEmployee?.email }}
                </div>
              </div>
              <div class="mb-3">
                <div class="text-caption">Temporary Password</div>
                <div class="text-h6 font-weight-bold text-primary">
                  {{ temporaryPassword }}
                </div>
              </div>
              <div>
                <div class="text-caption">Role</div>
                <div class="text-body-1 font-weight-bold text-capitalize">
                  {{ createdEmployee?.role }}
                </div>
              </div>
            </v-sheet>
          </div>

          <v-alert type="warning" variant="tonal" density="compact">
            Employee must change password on first login
          </v-alert>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-btn
            variant="text"
            prepend-icon="mdi-content-copy"
            @click="copyCredentials"
          >
            Copy
          </v-btn>
          <v-spacer></v-spacer>
          <v-btn color="primary" @click="showPasswordDialog = false">
            Done
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- View Credentials Dialog -->
    <v-dialog v-model="showCredentialsDialog" max-width="600px">
      <v-card>
        <v-card-title class="text-h5 py-4 bg-info">
          <v-icon start>mdi-account-key</v-icon>
          Employee Credentials
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-6">
          <v-alert type="info" variant="tonal" class="mb-4">
            <div class="text-h6 mb-2">
              {{ selectedCredentialsEmployee?.employee_number }} -
              {{ selectedCredentialsEmployee?.first_name }}
              {{ selectedCredentialsEmployee?.last_name }}
            </div>
          </v-alert>

          <div class="mb-4">
            <div class="text-subtitle-1 font-weight-bold mb-2">
              Current Login Credentials:
            </div>
            <v-sheet color="grey-lighten-4" rounded class="pa-4">
              <div class="mb-3">
                <div class="text-caption">Username</div>
                <div class="text-body-1 font-weight-bold">
                  {{ employeeCredentials?.username || "N/A" }}
                </div>
              </div>
              <div class="mb-3" v-if="employeeCredentials?.email">
                <div class="text-caption">Email</div>
                <div class="text-body-1 font-weight-bold">
                  {{ employeeCredentials?.email }}
                </div>
              </div>
              <div class="mb-3">
                <div class="text-caption">Role</div>
                <div class="text-body-1 font-weight-bold text-capitalize">
                  {{ employeeCredentials?.role }}
                </div>
              </div>
              <div class="mb-3">
                <div class="text-caption">Account Status</div>
                <v-chip
                  :color="employeeCredentials?.is_active ? 'success' : 'error'"
                  size="small"
                >
                  {{ employeeCredentials?.is_active ? "Active" : "Inactive" }}
                </v-chip>
              </div>
            </v-sheet>
          </div>

          <v-alert
            type="warning"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            Password is not stored for security. Generate a new temporary
            password if needed.
          </v-alert>

          <div v-if="newGeneratedPassword">
            <v-divider class="mb-4"></v-divider>
            <div class="text-subtitle-1 font-weight-bold mb-2">
              New Temporary Password Generated:
            </div>
            <v-sheet color="success-lighten-4" rounded class="pa-4">
              <div class="text-h6 font-weight-bold text-success">
                {{ newGeneratedPassword }}
              </div>
            </v-sheet>
            <v-alert type="info" variant="tonal" density="compact" class="mt-2">
              Employee must change this password on first login
            </v-alert>
          </div>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-btn
            variant="text"
            prepend-icon="mdi-content-copy"
            @click="copyEmployeeCredentials"
            :disabled="loadingCredentials"
          >
            Copy
          </v-btn>
          <v-btn
            variant="text"
            prepend-icon="mdi-lock-reset"
            color="warning"
            @click="resetEmployeePassword"
            :loading="resettingPassword"
          >
            Generate New Password
          </v-btn>
          <v-spacer></v-spacer>
          <v-btn color="primary" @click="closeCredentialsDialog"> Close </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from "vue";
import { useEmployeeStore } from "@/stores/employee";
import { useToast } from "vue-toastification";
import api from "@/services/api";
import { usePositionRates } from "@/composables/usePositionRates";
import AddEmployeeDialog from "@/components/AddEmployeeDialog.vue";

const toast = useToast();
const employeeStore = useEmployeeStore();
const { positionOptions, getRate, loadPositionRates } = usePositionRates();

const search = ref("");
const filters = ref({
  project_id: null,
  contract_type: null,
  activity_status: null,
  employment_type: null,
  position: null,
});

// Existing employee list variables
const showEmployeeDialog = ref(false);
const selectedEmployee = ref(null);
const isEditing = ref(false);
const saving = ref(false);

// Add Employee Dialog variables
const showAddEmployeeDialog = ref(false);
const showPasswordDialog = ref(false);
const temporaryPassword = ref("");
const createdEmployee = ref(null);
const createdEmployeeUsername = ref("");
const savingNew = ref(false);

// Credentials Dialog
const showCredentialsDialog = ref(false);
const selectedCredentialsEmployee = ref(null);
const employeeCredentials = ref(null);
const newGeneratedPassword = ref("");
const loadingCredentials = ref(false);
const resettingPassword = ref(false);

const projects = ref([]);
const employeeForm = ref(null);

const contractTypeOptions = [
  { title: "Regular", value: "regular" },
  { title: "Probationary", value: "probationary" },
  { title: "Contractual", value: "contractual" },
];

const activityStatusOptions = [
  { title: "Active", value: "active" },
  { title: "On Leave", value: "on_leave" },
  { title: "Resigned", value: "resigned" },
  { title: "Terminated", value: "terminated" },
  { title: "Retired", value: "retired" },
];

const employmentTypeOptions = [
  { title: "Regular", value: "regular" },
  { title: "Contractual", value: "contractual" },
  { title: "Part Time", value: "part_time" },
];

const headers = [
  { title: "Employee #", key: "employee_number", sortable: true },
  { title: "Name", key: "full_name", sortable: true },
  { title: "Gender", key: "gender", sortable: true },
  { title: "Project", key: "project", sortable: false },
  { title: "Position", key: "position", sortable: true },
  { title: "Type", key: "employment_type", sortable: true },
  { title: "Status", key: "is_active", sortable: true },
  { title: "Actions", key: "actions", sortable: false, align: "center" },
];

onMounted(async () => {
  await fetchEmployees();
  await fetchProjects();
  await loadPositionRates();
});

// Watch for position changes and auto-update basic_salary (only when editing)
watch(
  () => selectedEmployee.value?.position,
  (newPosition) => {
    if (isEditing.value && selectedEmployee.value && newPosition) {
      const rate = getRate(newPosition);
      if (rate && selectedEmployee.value.basic_salary !== rate) {
        selectedEmployee.value.basic_salary = rate;
        toast.info(`Salary updated to ₱${rate}/day based on position`);
      }
    }
  }
);

async function fetchEmployees() {
  await employeeStore.fetchEmployees({
    search: search.value,
    ...filters.value,
  });
}

async function fetchProjects() {
  projects.value = await employeeStore.fetchProjects();
}

async function viewEmployee(employee) {
  try {
    const response = await api.get(`/employees/${employee.id}`);
    selectedEmployee.value = { ...response.data };
    isEditing.value = false;
    showEmployeeDialog.value = true;
  } catch (error) {
    console.error("Error fetching employee details:", error);
    toast.error("Failed to load employee details");
  }
}

async function editEmployee(employee) {
  try {
    const response = await api.get(`/employees/${employee.id}`);
    selectedEmployee.value = { ...response.data };
    isEditing.value = true;
    showEmployeeDialog.value = true;
  } catch (error) {
    console.error("Error fetching employee details:", error);
    toast.error("Failed to load employee details");
  }
}

async function suspendEmployee(employee) {
  if (
    !confirm(
      `Are you sure you want to suspend ${employee.first_name} ${employee.last_name}?`
    )
  ) {
    return;
  }

  try {
    await api.put(`/employees/${employee.id}`, {
      ...employee,
      activity_status: "on_leave",
      is_active: false,
    });
    toast.success("Employee suspended successfully!");
    await fetchEmployees();
  } catch (error) {
    console.error("Error suspending employee:", error);
    toast.error("Failed to suspend employee");
  }
}

async function terminateEmployee(employee) {
  if (
    !confirm(
      `Are you sure you want to terminate ${employee.first_name} ${employee.last_name}?`
    )
  ) {
    return;
  }

  try {
    await api.put(`/employees/${employee.id}`, {
      ...employee,
      activity_status: "terminated",
      is_active: false,
      date_separated: new Date().toISOString().split("T")[0],
    });
    toast.success("Employee terminated successfully!");
    await fetchEmployees();
  } catch (error) {
    console.error("Error terminating employee:", error);
    toast.error("Failed to terminate employee");
  }
}

async function deleteEmployee(employee) {
  if (
    !confirm(
      `Are you sure you want to DELETE ${employee.first_name} ${employee.last_name}? This action cannot be undone!`
    )
  ) {
    return;
  }

  try {
    await api.delete(`/employees/${employee.id}`);
    toast.success("Employee deleted successfully!");
    await fetchEmployees();
  } catch (error) {
    console.error("Error deleting employee:", error);
    toast.error("Failed to delete employee");
  }
}

async function saveEmployee() {
  saving.value = true;
  try {
    await api.put(
      `/employees/${selectedEmployee.value.id}`,
      selectedEmployee.value
    );
    toast.success("Employee updated successfully!");
    await fetchEmployees();
    closeDialog();
  } catch (error) {
    console.error("Error updating employee:", error);
    const message =
      error.response?.data?.message || "Failed to update employee";
    toast.error(message);
  } finally {
    saving.value = false;
  }
}

function closeDialog() {
  showEmployeeDialog.value = false;
  isEditing.value = false;
  selectedEmployee.value = null;
}

function getEmploymentTypeColor(type) {
  return type === "monthly" ? "primary" : "info";
}

// Add Employee Dialog functions
async function saveNewEmployee(employeeData) {
  savingNew.value = true;
  try {
    const response = await api.post("/employees", employeeData);

    temporaryPassword.value = response.data.temporary_password;
    createdEmployee.value = response.data.employee;
    createdEmployee.value.role = response.data.role;
    createdEmployeeUsername.value = response.data.username;

    toast.success("Employee created successfully!");
    showAddEmployeeDialog.value = false;
    showPasswordDialog.value = true;
    await fetchEmployees();
  } catch (error) {
    console.error("Error creating employee:", error);
    const message =
      error.response?.data?.message || "Failed to create employee";
    toast.error(message);
  } finally {
    savingNew.value = false;
  }
}

function copyCredentials() {
  const emailInfo = createdEmployee.value.email
    ? `\nEmail: ${createdEmployee.value.email}`
    : "";
  const credentials = `Employee: ${createdEmployee.value.employee_number} - ${
    createdEmployee.value.first_name
  } ${createdEmployee.value.last_name}
Username: ${
    createdEmployeeUsername.value || createdEmployee.value.email
  }${emailInfo}
Temporary Password: ${temporaryPassword.value}
Role: ${createdEmployee.value.role}

⚠️ Employee must change password on first login`;

  navigator.clipboard
    .writeText(credentials)
    .then(() => {
      toast.success("Credentials copied to clipboard!");
    })
    .catch(() => {
      toast.error("Failed to copy credentials");
    });
}

// View Credentials functions
async function viewCredentials(employee) {
  selectedCredentialsEmployee.value = employee;
  newGeneratedPassword.value = "";
  loadingCredentials.value = true;
  showCredentialsDialog.value = true;

  try {
    // Fetch user credentials from backend
    const response = await api.get(`/employees/${employee.id}/credentials`);
    employeeCredentials.value = response.data;
  } catch (error) {
    console.error("Error fetching credentials:", error);
    toast.error("Failed to load credentials");
    employeeCredentials.value = {
      username: "Error loading",
      email: null,
      role: "N/A",
      is_active: false,
    };
  } finally {
    loadingCredentials.value = false;
  }
}

async function resetEmployeePassword() {
  if (
    !confirm(
      `Generate new temporary password for ${selectedCredentialsEmployee.value.first_name} ${selectedCredentialsEmployee.value.last_name}?`
    )
  ) {
    return;
  }

  resettingPassword.value = true;
  try {
    const response = await api.post(
      `/employees/${selectedCredentialsEmployee.value.id}/reset-password`
    );
    newGeneratedPassword.value = response.data.temporary_password;
    toast.success("New temporary password generated!");
  } catch (error) {
    console.error("Error resetting password:", error);
    toast.error("Failed to generate new password");
  } finally {
    resettingPassword.value = false;
  }
}

function copyEmployeeCredentials() {
  const emailInfo = employeeCredentials.value?.email
    ? `\nEmail: ${employeeCredentials.value.email}`
    : "";
  const passwordInfo = newGeneratedPassword.value
    ? `\nTemporary Password: ${newGeneratedPassword.value}\n\n⚠️ Employee must change password on first login`
    : `\n\n⚠️ Password not available. Generate new temporary password if needed.`;

  const credentials = `Employee: ${
    selectedCredentialsEmployee.value.employee_number
  } - ${selectedCredentialsEmployee.value.first_name} ${
    selectedCredentialsEmployee.value.last_name
  }
Username: ${employeeCredentials.value?.username || "N/A"}${emailInfo}
Role: ${employeeCredentials.value?.role || "N/A"}${passwordInfo}`;

  navigator.clipboard
    .writeText(credentials)
    .then(() => {
      toast.success("Credentials copied to clipboard!");
    })
    .catch(() => {
      toast.error("Failed to copy credentials");
    });
}

function closeCredentialsDialog() {
  showCredentialsDialog.value = false;
  selectedCredentialsEmployee.value = null;
  employeeCredentials.value = null;
  newGeneratedPassword.value = "";
}
</script>
