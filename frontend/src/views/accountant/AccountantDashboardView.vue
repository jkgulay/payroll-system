<template>
  <div>
    <!-- Quick Stats -->
    <v-row>
      <v-col cols="12" sm="6" md="3">
        <v-card>
          <v-card-text>
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-overline text-medium-emphasis">Total Employees</div>
                <div class="text-h4 font-weight-bold">{{ stats.totalEmployees }}</div>
                <div class="text-caption text-success">
                  <v-icon size="small">mdi-arrow-up</v-icon>
                  {{ stats.activeEmployees }} active
                </div>
              </div>
              <v-avatar color="primary" size="56">
                <v-icon size="32">mdi-account-group</v-icon>
              </v-avatar>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card>
          <v-card-text>
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-overline text-medium-emphasis">Pending Requests</div>
                <div class="text-h4 font-weight-bold">{{ stats.pendingRequests }}</div>
                <div class="text-caption text-warning">Needs approval</div>
              </div>
              <v-avatar color="warning" size="56">
                <v-icon size="32">mdi-alert-circle</v-icon>
              </v-avatar>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card>
          <v-card-text>
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-overline text-medium-emphasis">This Period</div>
                <div class="text-h4 font-weight-bold">₱{{ formatNumber(stats.periodPayroll) }}</div>
                <div class="text-caption text-medium-emphasis">Payroll amount</div>
              </div>
              <v-avatar color="success" size="56">
                <v-icon size="32">mdi-cash-multiple</v-icon>
              </v-avatar>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card>
          <v-card-text>
            <div class="d-flex align-center justify-space-between">
              <div>
                <div class="text-overline text-medium-emphasis">Attendance Today</div>
                <div class="text-h4 font-weight-bold">{{ stats.presentToday }}</div>
                <div class="text-caption text-medium-emphasis">of {{ stats.totalEmployees }} employees</div>
              </div>
              <v-avatar color="info" size="56">
                <v-icon size="32">mdi-calendar-check</v-icon>
              </v-avatar>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Quick Actions -->
    <v-row class="mt-4">
      <v-col cols="12">
        <v-card>
          <v-card-title class="d-flex align-center">
            <v-icon class="mr-2">mdi-lightning-bolt</v-icon>
            Quick Actions
          </v-card-title>
          <v-card-text>
            <v-row>
              <v-col cols="12" sm="6" md="3">
                <v-btn
                  color="primary"
                  block
                  prepend-icon="mdi-account-plus"
                  @click="showAddEmployeeDialog = true"
                >
                  Add Employee
                </v-btn>
              </v-col>
              <v-col cols="12" sm="6" md="3">
                <v-btn
                  color="info"
                  block
                  prepend-icon="mdi-calendar-edit"
                  @click="$router.push('/attendance')"
                >
                  Edit Attendance
                </v-btn>
              </v-col>
              <v-col cols="12" sm="6" md="3">
                <v-btn
                  color="success"
                  block
                  prepend-icon="mdi-cash-edit"
                  @click="showPayslipModifyDialog = true"
                >
                  Modify Payslip
                </v-btn>
              </v-col>
              <v-col cols="12" sm="6" md="3">
                <v-btn
                  color="orange"
                  block
                  prepend-icon="mdi-download"
                  @click="showDownloadDialog = true"
                >
                  Download Payroll
                </v-btn>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Employee List -->
    <v-row class="mt-4">
      <v-col cols="12">
        <v-card>
          <v-card-title class="d-flex align-center justify-space-between">
            <span>
              <v-icon class="mr-2">mdi-account-group</v-icon>
              Employee List
            </span>
            <v-btn
              color="primary"
              prepend-icon="mdi-account-plus"
              @click="showAddEmployeeDialog = true"
            >
              Add Employee
            </v-btn>
          </v-card-title>
          <v-card-text>
            <v-text-field
              v-model="search"
              prepend-inner-icon="mdi-magnify"
              label="Search employees"
              single-line
              hide-details
              class="mb-4"
            ></v-text-field>

            <v-data-table
              :headers="employeeHeaders"
              :items="employees"
              :search="search"
              :loading="loading"
            >
              <template v-slot:item.full_name="{ item }">
                <div class="d-flex align-center">
                  <v-avatar color="primary" size="32" class="mr-2">
                    <span class="text-caption">{{ getInitials(item.full_name) }}</span>
                  </v-avatar>
                  {{ item.full_name }}
                </div>
              </template>
              <template v-slot:item.employment_status="{ item }">
                <v-chip
                  :color="item.employment_status === 'active' ? 'success' : 'grey'"
                  size="small"
                >
                  {{ item.employment_status }}
                </v-chip>
              </template>
              <template v-slot:item.actions="{ item }">
                <v-btn
                  icon="mdi-pencil"
                  size="small"
                  variant="text"
                  @click="editEmployee(item)"
                ></v-btn>
                <v-btn
                  icon="mdi-eye"
                  size="small"
                  variant="text"
                  @click="viewEmployee(item)"
                ></v-btn>
              </template>
            </v-data-table>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Add/Edit Employee Dialog -->
    <v-dialog v-model="showAddEmployeeDialog" max-width="800px" persistent>
      <v-card>
        <v-card-title>
          <span class="text-h5">{{ editingEmployee ? 'Edit' : 'Add' }} Employee</span>
        </v-card-title>
        <v-card-text>
          <v-form ref="employeeForm">
            <v-row>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="employeeData.employee_number"
                  label="Employee Number"
                  :rules="[rules.required]"
                ></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="employeeData.first_name"
                  label="First Name"
                  :rules="[rules.required]"
                ></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="employeeData.last_name"
                  label="Last Name"
                  :rules="[rules.required]"
                ></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="employeeData.date_of_birth"
                  label="Date of Birth"
                  type="date"
                  :rules="[rules.required]"
                ></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="employeeData.email"
                  label="Email"
                  type="email"
                  :rules="[rules.required, rules.email]"
                ></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="employeeData.mobile_number"
                  label="Mobile Number"
                ></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="employeeData.position"
                  label="Position"
                ></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-select
                  v-model="employeeData.department_id"
                  :items="departments"
                  item-title="name"
                  item-value="id"
                  label="Department"
                  :rules="[rules.required]"
                ></v-select>
              </v-col>
              <v-col cols="12" md="6">
                <v-select
                  v-model="employeeData.location_id"
                  :items="locations"
                  item-title="name"
                  item-value="id"
                  label="Location"
                  :rules="[rules.required]"
                ></v-select>
              </v-col>
              <v-col cols="12" md="6">
                <v-select
                  v-model="employeeData.employment_status"
                  :items="['regular', 'probationary', 'contractual', 'resigned', 'terminated']"
                  label="Employment Status"
                  :rules="[rules.required]"
                ></v-select>
              </v-col>
              <v-col cols="12" md="6">
                <v-select
                  v-model="employeeData.employment_type"
                  :items="['regular', 'contractual', 'part_time']"
                  label="Employment Type"
                  :rules="[rules.required]"
                ></v-select>
              </v-col>
              <v-col cols="12" md="6">
                <v-select
                  v-model="employeeData.salary_type"
                  :items="['daily', 'weekly', 'semi-monthly', 'monthly']"
                  label="Salary Type"
                  :rules="[rules.required]"
                ></v-select>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model.number="employeeData.basic_salary"
                  label="Basic Salary"
                  type="number"
                  prefix="₱"
                  :rules="[rules.required]"
                ></v-text-field>
              </v-col>
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="employeeData.date_hired"
                  label="Date Hired"
                  type="date"
                  :rules="[rules.required]"
                ></v-text-field>
              </v-col>
              
              <!-- User Account Creation Section -->
              <v-col cols="12">
                <v-divider class="my-4"></v-divider>
                <v-checkbox
                  v-model="employeeData.create_user_account"
                  label="Create user account for employee login"
                  hide-details
                ></v-checkbox>
              </v-col>
              
              <template v-if="employeeData.create_user_account">
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="employeeData.username"
                    label="Username"
                    :rules="[rules.required]"
                    hint="Username for employee login"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    v-model="employeeData.password"
                    label="Password"
                    type="password"
                    :rules="[rules.required, rules.minLength]"
                    hint="Minimum 6 characters"
                  ></v-text-field>
                </v-col>
              </template>
            </v-row>
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn @click="closeEmployeeDialog">Cancel</v-btn>
          <v-btn color="primary" @click="saveEmployee" :loading="saving">
            {{ editingEmployee ? 'Update' : 'Submit to Admin' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Payslip Modify Dialog -->
    <v-dialog v-model="showPayslipModifyDialog" max-width="600px" persistent>
      <v-card>
        <v-card-title>Modify Employee Payslip</v-card-title>
        <v-card-text>
          <v-select
            v-model="selectedEmployee"
            :items="employees"
            item-title="full_name"
            item-value="id"
            label="Select Employee"
            class="mb-4"
          ></v-select>
          <v-text-field
            v-model.number="payslipModify.additional_allowance"
            label="Additional Allowance"
            type="number"
            prefix="₱"
          ></v-text-field>
          <v-text-field
            v-model.number="payslipModify.additional_deduction"
            label="Additional Deduction"
            type="number"
            prefix="₱"
          ></v-text-field>
          <v-textarea
            v-model="payslipModify.notes"
            label="Notes/Reason"
            rows="3"
          ></v-textarea>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn @click="showPayslipModifyDialog = false">Cancel</v-btn>
          <v-btn color="primary" @click="submitPayslipModification">Submit</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Download Payroll Dialog -->
    <v-dialog v-model="showDownloadDialog" max-width="500px">
      <v-card>
        <v-card-title>Download Payroll Report</v-card-title>
        <v-card-text>
          <v-select
            v-model="downloadOptions.payroll_id"
            :items="payrolls"
            item-title="period_label"
            item-value="id"
            label="Select Payroll Period"
            class="mb-4"
          ></v-select>
          <v-select
            v-model="downloadOptions.format"
            :items="['PDF', 'Excel']"
            label="Format"
            class="mb-4"
          ></v-select>
          <v-checkbox
            v-model="downloadOptions.include_signatures"
            label="Include signature lines"
          ></v-checkbox>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn @click="showDownloadDialog = false">Cancel</v-btn>
          <v-btn color="primary" @click="downloadPayroll" :loading="downloading">
            Download
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useToast } from "vue-toastification";
import api from "@/services/api";

const router = useRouter();
const toast = useToast();

const loading = ref(false);
const saving = ref(false);
const downloading = ref(false);
const search = ref("");
const employees = ref([]);
const departments = ref([]);
const locations = ref([]);
const payrolls = ref([]);
const showAddEmployeeDialog = ref(false);
const showPayslipModifyDialog = ref(false);
const showDownloadDialog = ref(false);
const editingEmployee = ref(null);
const selectedEmployee = ref(null);

const stats = ref({
  totalEmployees: 0,
  activeEmployees: 0,
  pendingRequests: 0,
  periodPayroll: 0,
  presentToday: 0,
});

const employeeData = ref({
  employee_number: "",
  first_name: "",
  last_name: "",
  date_of_birth: "",
  email: "",
  mobile_number: "",
  position: "",
  department_id: null,
  location_id: null,
  employment_status: "regular",
  employment_type: "regular",
  basic_salary: 0,
  salary_type: "monthly",
  date_hired: "",
  create_user_account: false,
  username: "",
  password: "",
});

const payslipModify = ref({
  additional_allowance: 0,
  additional_deduction: 0,
  notes: "",
});

const downloadOptions = ref({
  payroll_id: null,
  format: "PDF",
  include_signatures: true,
});

const employeeHeaders = [
  { title: "Employee", key: "full_name" },
  { title: "Employee No.", key: "employee_number" },
  { title: "Position", key: "position" },
  { title: "Department", key: "department.name" },
  { title: "Status", key: "employment_status" },
  { title: "Actions", key: "actions", sortable: false },
];

const rules = {
  required: (value) => !!value || "This field is required",
  email: (value) => /.+@.+\..+/.test(value) || "Email must be valid",
  minLength: (value) => !value || value.length >= 6 || "Minimum 6 characters required",
};

const employeeForm = ref(null);

onMounted(() => {
  fetchDashboardData();
  fetchEmployees();
  fetchDepartments();
  fetchLocations();
  fetchPayrolls();
});

async function fetchDashboardData() {
  try {
    const response = await api.get("/accountant/dashboard/stats");
    stats.value = response.data;
  } catch (error) {
    console.error("Error fetching dashboard data:", error);
  }
}

async function fetchEmployees() {
  loading.value = true;
  try {
    const response = await api.get("/employees", {
      params: { per_page: 100 } // Get more employees without pagination for dashboard
    });
    const data = response.data.data || response.data;
    // Handle Laravel pagination response
    employees.value = Array.isArray(data) ? data : (data.data || []);
  } catch (error) {
    console.error("Error fetching employees:", error);
    toast.error("Failed to load employees");
  } finally {
    loading.value = false;
  }
}

async function fetchDepartments() {
  try {
    const response = await api.get("/departments");
    departments.value = response.data.data || response.data;
  } catch (error) {
    console.error("Error fetching departments:", error);
  }
}

async function fetchLocations() {
  try {
    const response = await api.get("/locations");
    locations.value = response.data.data || response.data;
  } catch (error) {
    console.error("Error fetching locations:", error);
  }
}

async function fetchPayrolls() {
  try {
    const response = await api.get("/payroll");
    payrolls.value = (response.data.data || response.data).map((p) => ({
      ...p,
      period_label: `${p.period_start} to ${p.period_end}`,
    }));
  } catch (error) {
    console.error("Error fetching payrolls:", error);
  }
}

async function saveEmployee() {
  const { valid } = await employeeForm.value.validate();
  if (!valid) return;

  saving.value = true;
  try {
    if (editingEmployee.value) {
      await api.put(`/employees/${editingEmployee.value.id}`, employeeData.value);
      toast.success("Employee updated successfully");
    } else {
      await api.post("/employees", employeeData.value);
      toast.success("Employee submitted to admin for approval");
    }
    closeEmployeeDialog();
    fetchEmployees();
    fetchDashboardData();
  } catch (error) {
    console.error("Error saving employee:", error);
    toast.error(error.response?.data?.message || "Failed to save employee");
  } finally {
    saving.value = false;
  }
}

function editEmployee(employee) {
  editingEmployee.value = employee;
  employeeData.value = { ...employee };
  showAddEmployeeDialog.value = true;
}

function viewEmployee(employee) {
  router.push(`/employees/${employee.id}`);
}

function closeEmployeeDialog() {
  showAddEmployeeDialog.value = false;
  editingEmployee.value = null;
  employeeData.value = {
    employee_number: "",
    first_name: "",
    last_name: "",
    date_of_birth: "",
    email: "",
    mobile_number: "",
    position: "",
    department_id: null,
    location_id: null,
    employment_status: "regular",
    employment_type: "regular",
    basic_salary: 0,
    salary_type: "monthly",
    date_hired: "",
    create_user_account: false,
    username: "",
    password: "",
  };
}

async function submitPayslipModification() {
  if (!selectedEmployee.value) {
    toast.warning("Please select an employee");
    return;
  }

  try {
    await api.post("/payslip-modifications", {
      employee_id: selectedEmployee.value,
      ...payslipModify.value,
    });
    toast.success("Payslip modification submitted");
    showPayslipModifyDialog.value = false;
    payslipModify.value = {
      additional_allowance: 0,
      additional_deduction: 0,
      notes: "",
    };
  } catch (error) {
    console.error("Error submitting payslip modification:", error);
    toast.error("Failed to submit payslip modification");
  }
}

async function downloadPayroll() {
  if (!downloadOptions.value.payroll_id) {
    toast.warning("Please select a payroll period");
    return;
  }

  downloading.value = true;
  try {
    const endpoint = downloadOptions.value.format === "PDF" 
      ? `/payroll/${downloadOptions.value.payroll_id}/export-pdf`
      : `/payroll/${downloadOptions.value.payroll_id}/export-excel`;

    const response = await api.get(endpoint, {
      params: { include_signatures: downloadOptions.value.include_signatures },
      responseType: "blob",
    });

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;
    const ext = downloadOptions.value.format === "PDF" ? "pdf" : "xlsx";
    link.setAttribute("download", `payroll_report.${ext}`);
    document.body.appendChild(link);
    link.click();
    link.remove();

    toast.success("Payroll report downloaded successfully");
    showDownloadDialog.value = false;
  } catch (error) {
    console.error("Error downloading payroll:", error);
    toast.error("Failed to download payroll report");
  } finally {
    downloading.value = false;
  }
}

function getInitials(name) {
  return name
    .split(" ")
    .map((n) => n[0])
    .join("")
    .toUpperCase();
}

function formatNumber(value) {
  return new Intl.NumberFormat("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value);
}
</script>

<style scoped>
.v-data-table {
  background: transparent;
}
</style>
