<template>
  <v-container fluid class="pa-6">
    <div class="d-flex align-center justify-space-between mb-6">
      <h1 class="text-h4 font-weight-bold">Project Management</h1>
      <v-btn color="primary" prepend-icon="mdi-plus" @click="openCreateDialog">
        New Project
      </v-btn>
    </div>

    <!-- Filter Tabs -->
    <v-tabs v-model="filterTab" color="primary" class="mb-4">
      <v-tab value="all">All Projects</v-tab>
      <v-tab value="active">Active</v-tab>
      <v-tab value="completed">Completed</v-tab>
    </v-tabs>

    <!-- Search Bar -->
    <v-text-field
      v-model="search"
      prepend-inner-icon="mdi-magnify"
      label="Search projects..."
      variant="outlined"
      density="compact"
      clearable
      class="mb-4"
    ></v-text-field>

    <!-- Loading State -->
    <v-progress-linear
      v-if="loading"
      indeterminate
      color="primary"
      class="mb-4"
    ></v-progress-linear>

    <!-- Projects Grid -->
    <v-row v-if="!loading">
      <v-col
        v-for="project in filteredProjects"
        :key="project.id"
        cols="12"
        md="6"
        lg="4"
      >
        <v-card class="project-card" elevation="2">
          <v-card-title class="d-flex align-center">
            <div class="flex-grow-1">
              <div class="text-h6">{{ project.name }}</div>
              <div class="text-caption text-grey">{{ project.code }}</div>
            </div>
            <v-chip
              :color="project.is_active ? 'success' : 'grey'"
              size="small"
              variant="flat"
            >
              {{ project.is_active ? "Active" : "Completed" }}
            </v-chip>
          </v-card-title>

          <v-card-text>
            <div class="mb-2">
              <div class="text-caption text-grey">Description</div>
              <div class="text-body-2">
                {{ project.description || "No description" }}
              </div>
            </div>

            <v-divider class="my-3"></v-divider>

            <div class="d-flex align-center mb-2">
              <v-icon size="small" class="mr-2">mdi-account-hard-hat</v-icon>
              <span class="text-body-2">
                <strong>Head:</strong>
                {{
                  project.head_employee
                    ? `${project.head_employee.first_name} ${project.head_employee.last_name}`
                    : "Not assigned"
                }}
              </span>
            </div>

            <div class="d-flex align-center">
              <v-icon size="small" class="mr-2">mdi-account-group</v-icon>
              <span class="text-body-2">
                <strong>{{ project.employees_count || 0 }}</strong> employee(s)
                assigned
              </span>
            </div>
          </v-card-text>

          <v-card-actions>
            <v-btn
              size="small"
              variant="text"
              color="primary"
              @click="viewProject(project)"
            >
              View Details
            </v-btn>

            <v-spacer></v-spacer>

            <v-menu>
              <template v-slot:activator="{ props }">
                <v-btn
                  icon="mdi-dots-vertical"
                  size="small"
                  variant="text"
                  v-bind="props"
                ></v-btn>
              </template>

              <v-list density="compact">
                <v-list-item @click="editProject(project)">
                  <template v-slot:prepend>
                    <v-icon size="small">mdi-pencil</v-icon>
                  </template>
                  <v-list-item-title>Edit</v-list-item-title>
                </v-list-item>

                <v-list-item
                  v-if="project.is_active"
                  @click="markComplete(project)"
                >
                  <template v-slot:prepend>
                    <v-icon size="small">mdi-check-circle</v-icon>
                  </template>
                  <v-list-item-title>Mark Complete</v-list-item-title>
                </v-list-item>

                <v-list-item v-else @click="reactivateProject(project)">
                  <template v-slot:prepend>
                    <v-icon size="small">mdi-restart</v-icon>
                  </template>
                  <v-list-item-title>Reactivate</v-list-item-title>
                </v-list-item>

                <v-list-item
                  v-if="project.employees_count > 0"
                  @click="openPayrollDialog(project)"
                >
                  <template v-slot:prepend>
                    <v-icon size="small">mdi-currency-usd</v-icon>
                  </template>
                  <v-list-item-title>Generate Payroll</v-list-item-title>
                </v-list-item>

                <v-divider></v-divider>

                <v-list-item
                  @click="deleteProject(project)"
                  :disabled="project.employees_count > 0"
                >
                  <template v-slot:prepend>
                    <v-icon size="small" color="error">mdi-delete</v-icon>
                  </template>
                  <v-list-item-title class="text-error"
                    >Delete</v-list-item-title
                  >
                </v-list-item>
              </v-list>
            </v-menu>
          </v-card-actions>
        </v-card>
      </v-col>
    </v-row>

    <!-- Empty State -->
    <v-card
      v-if="!loading && filteredProjects.length === 0"
      class="pa-8 text-center"
    >
      <v-icon size="64" color="grey-lighten-1">mdi-folder-open</v-icon>
      <div class="text-h6 mt-4 text-grey">No projects found</div>
      <div class="text-body-2 text-grey">
        {{
          search
            ? "Try adjusting your search"
            : "Create your first project to get started"
        }}
      </div>
    </v-card>

    <!-- Create/Edit Dialog -->
    <v-dialog v-model="dialog" max-width="600px" persistent>
      <v-card>
        <v-card-title>
          <span class="text-h5">{{
            editMode ? "Edit Project" : "New Project"
          }}</span>
        </v-card-title>

        <v-card-text>
          <v-form ref="formRef" @submit.prevent="saveProject">
            <v-text-field
              v-model="formData.code"
              label="Project Code"
              variant="outlined"
              density="comfortable"
              hint="Leave blank to auto-generate"
              persistent-hint
              class="mb-2"
            ></v-text-field>

            <v-text-field
              v-model="formData.name"
              label="Project Name *"
              variant="outlined"
              density="comfortable"
              :rules="[(v) => !!v || 'Name is required']"
              required
              class="mb-2"
            ></v-text-field>

            <v-textarea
              v-model="formData.description"
              label="Description"
              variant="outlined"
              density="comfortable"
              rows="3"
              class="mb-2"
            ></v-textarea>

            <v-autocomplete
              v-model="formData.head_employee_id"
              :items="employees"
              item-title="full_name"
              item-value="id"
              label="Project Head"
              variant="outlined"
              density="comfortable"
              clearable
              :loading="loadingEmployees"
            ></v-autocomplete>
          </v-form>
        </v-card-text>

        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="closeDialog">Cancel</v-btn>
          <v-btn
            color="primary"
            variant="flat"
            @click="saveProject"
            :loading="saving"
          >
            {{ editMode ? "Update" : "Create" }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Project Details Dialog -->
    <v-dialog v-model="detailsDialog" max-width="900px">
      <v-card v-if="selectedProject">
        <v-card-title class="d-flex align-center">
          <div class="flex-grow-1">
            <div class="text-h5">{{ selectedProject.name }}</div>
            <div class="text-caption text-grey">{{ selectedProject.code }}</div>
          </div>
          <v-chip
            :color="selectedProject.is_active ? 'success' : 'grey'"
            variant="flat"
          >
            {{ selectedProject.is_active ? "Active" : "Completed" }}
          </v-chip>
        </v-card-title>

        <v-card-text>
          <div class="mb-4">
            <div class="text-subtitle-2 text-grey mb-1">Description</div>
            <div>{{ selectedProject.description || "No description" }}</div>
          </div>

          <div class="mb-4">
            <div class="text-subtitle-2 text-grey mb-1">Project Head</div>
            <div>
              {{
                selectedProject.head_employee
                  ? `${selectedProject.head_employee.first_name} ${selectedProject.head_employee.last_name} (${selectedProject.head_employee.position})`
                  : "Not assigned"
              }}
            </div>
          </div>

          <v-divider class="my-4"></v-divider>

          <div class="text-h6 mb-3">
            Assigned Employees ({{ projectEmployees.length }})
          </div>

          <v-progress-linear
            v-if="loadingDetails"
            indeterminate
            color="primary"
          ></v-progress-linear>

          <v-data-table
            v-else
            :headers="employeeHeaders"
            :items="projectEmployees"
            :items-per-page="10"
            density="comfortable"
          >
            <template v-slot:item.full_name="{ item }">
              <div>{{ item.full_name }}</div>
              <div class="text-caption text-grey">
                {{ item.employee_number }}
              </div>
            </template>

            <template v-slot:item.basic_salary="{ item }">
              ₱{{
                Number(item.basic_salary).toLocaleString("en-US", {
                  minimumFractionDigits: 2,
                })
              }}
              <div class="text-caption text-grey">{{ item.salary_type }}</div>
            </template>

            <template v-slot:item.employment_status="{ item }">
              <v-chip
                :color="
                  item.employment_status === 'active' ? 'success' : 'grey'
                "
                size="small"
              >
                {{ item.employment_status }}
              </v-chip>
            </template>
          </v-data-table>
        </v-card-text>

        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="detailsDialog = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Generate Payroll Dialog -->
    <v-dialog v-model="payrollDialog" max-width="500px" persistent>
      <v-card>
        <v-card-title>
          <span class="text-h5">Generate Payroll</span>
        </v-card-title>

        <v-card-text>
          <div class="mb-4">
            <strong>Project:</strong> {{ payrollProject?.name }}
          </div>
          <div class="mb-4">
            <strong>Employees:</strong>
            {{ payrollProject?.employees_count }} active employee(s)
          </div>

          <v-form ref="payrollFormRef">
            <v-text-field
              v-model="payrollForm.period_start_date"
              label="Period Start Date *"
              type="date"
              variant="outlined"
              density="comfortable"
              :rules="[(v) => !!v || 'Required']"
              required
              class="mb-2"
            ></v-text-field>

            <v-text-field
              v-model="payrollForm.period_end_date"
              label="Period End Date *"
              type="date"
              variant="outlined"
              density="comfortable"
              :rules="[(v) => !!v || 'Required']"
              required
              class="mb-2"
            ></v-text-field>

            <v-text-field
              v-model="payrollForm.payment_date"
              label="Payment Date *"
              type="date"
              variant="outlined"
              density="comfortable"
              :rules="[(v) => !!v || 'Required']"
              required
            ></v-text-field>
          </v-form>
        </v-card-text>

        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="closePayrollDialog">Cancel</v-btn>
          <v-btn
            color="primary"
            variant="flat"
            @click="generatePayroll"
            :loading="generatingPayroll"
          >
            Generate
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Snackbar -->
    <v-snackbar v-model="snackbar" :color="snackbarColor" :timeout="3000">
      {{ snackbarText }}
    </v-snackbar>
  </v-container>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import api from "@/services/api";

// State
const loading = ref(false);
const loadingEmployees = ref(false);
const loadingDetails = ref(false);
const saving = ref(false);
const generatingPayroll = ref(false);
const dialog = ref(false);
const detailsDialog = ref(false);
const payrollDialog = ref(false);
const editMode = ref(false);
const filterTab = ref("all");
const search = ref("");
const projects = ref([]);
const employees = ref([]);
const selectedProject = ref(null);
const projectEmployees = ref([]);
const payrollProject = ref(null);
const snackbar = ref(false);
const snackbarText = ref("");
const snackbarColor = ref("success");

// Form data
const formData = ref({
  code: "",
  name: "",
  description: "",
  head_employee_id: null,
  is_active: true,
});

const payrollForm = ref({
  period_start_date: "",
  period_end_date: "",
  payment_date: "",
});

const formRef = ref(null);
const payrollFormRef = ref(null);

// Table headers
const employeeHeaders = [
  { title: "Employee", key: "full_name", sortable: true },
  { title: "Position", key: "position", sortable: true },
  { title: "Salary", key: "basic_salary", sortable: true },
  { title: "Status", key: "employment_status", sortable: true },
  { title: "Date Hired", key: "date_hired", sortable: true },
];

// Computed
const filteredProjects = computed(() => {
  let filtered = projects.value;

  // Filter by tab
  if (filterTab.value === "active") {
    filtered = filtered.filter((p) => p.is_active);
  } else if (filterTab.value === "completed") {
    filtered = filtered.filter((p) => !p.is_active);
  }

  // Filter by search
  if (search.value) {
    const searchLower = search.value.toLowerCase();
    filtered = filtered.filter(
      (p) =>
        p.name.toLowerCase().includes(searchLower) ||
        p.code.toLowerCase().includes(searchLower) ||
        (p.description && p.description.toLowerCase().includes(searchLower))
    );
  }

  return filtered;
});

// Methods
const fetchProjects = async () => {
  loading.value = true;
  try {
    const response = await api.get("/projects");
    projects.value = response.data;
  } catch (error) {
    showSnackbar("Failed to load projects", "error");
    console.error("Error fetching projects:", error);
  } finally {
    loading.value = false;
  }
};

const fetchEmployees = async () => {
  loadingEmployees.value = true;
  try {
    const response = await api.get("/employees");
    // Handle paginated response
    const employeeData = response.data.data || response.data;
    employees.value = employeeData.map((emp) => ({
      ...emp,
      full_name: `${emp.first_name} ${emp.last_name} - ${emp.position}`,
    }));
  } catch (error) {
    console.error("Error fetching employees:", error);
  } finally {
    loadingEmployees.value = false;
  }
};

const openCreateDialog = () => {
  editMode.value = false;
  formData.value = {
    code: "",
    name: "",
    description: "",
    head_employee_id: null,
    is_active: true,
  };
  dialog.value = true;
  if (employees.value.length === 0) {
    fetchEmployees();
  }
};

const editProject = (project) => {
  editMode.value = true;
  formData.value = {
    id: project.id,
    code: project.code,
    name: project.name,
    description: project.description,
    head_employee_id: project.head_employee_id,
    is_active: project.is_active,
  };
  dialog.value = true;
  if (employees.value.length === 0) {
    fetchEmployees();
  }
};

const saveProject = async () => {
  if (!formRef.value) return;

  const valid = await formRef.value.validate();
  if (!valid.valid) return;

  saving.value = true;
  try {
    if (editMode.value) {
      await api.put(`/projects/${formData.value.id}`, formData.value);
      showSnackbar("Project updated successfully", "success");
    } else {
      await api.post("/projects", formData.value);
      showSnackbar("Project created successfully", "success");
    }
    await fetchProjects();
    closeDialog();
  } catch (error) {
    const message = error.response?.data?.message || "Failed to save project";
    showSnackbar(message, "error");
    console.error("Error saving project:", error);
  } finally {
    saving.value = false;
  }
};

const closeDialog = () => {
  dialog.value = false;
  formData.value = {
    code: "",
    name: "",
    description: "",
    head_employee_id: null,
    is_active: true,
  };
};

const viewProject = async (project) => {
  selectedProject.value = project;
  detailsDialog.value = true;
  loadingDetails.value = true;
  projectEmployees.value = [];

  try {
    const response = await api.get(`/projects/${project.id}/employees`);
    projectEmployees.value = response.data;
  } catch (error) {
    showSnackbar("Failed to load project employees", "error");
    console.error("Error fetching project employees:", error);
  } finally {
    loadingDetails.value = false;
  }
};

const markComplete = async (project) => {
  if (!confirm(`Are you sure you want to mark "${project.name}" as complete?`))
    return;

  try {
    await api.post(`/projects/${project.id}/mark-complete`);
    showSnackbar("Project marked as complete", "success");
    await fetchProjects();
  } catch (error) {
    showSnackbar("Failed to update project status", "error");
    console.error("Error marking project complete:", error);
  }
};

const reactivateProject = async (project) => {
  try {
    await api.post(`/projects/${project.id}/reactivate`);
    showSnackbar("Project reactivated", "success");
    await fetchProjects();
  } catch (error) {
    showSnackbar("Failed to reactivate project", "error");
    console.error("Error reactivating project:", error);
  }
};

const deleteProject = async (project) => {
  if (!confirm(`Are you sure you want to delete "${project.name}"?`)) return;

  try {
    await api.delete(`/projects/${project.id}`);
    showSnackbar("Project deleted successfully", "success");
    await fetchProjects();
  } catch (error) {
    const message = error.response?.data?.message || "Failed to delete project";
    showSnackbar(message, "error");
    console.error("Error deleting project:", error);
  }
};

const openPayrollDialog = (project) => {
  payrollProject.value = project;
  payrollForm.value = {
    period_start_date: "",
    period_end_date: "",
    payment_date: "",
  };
  payrollDialog.value = true;
};

const closePayrollDialog = () => {
  payrollDialog.value = false;
  payrollProject.value = null;
  payrollForm.value = {
    period_start_date: "",
    period_end_date: "",
    payment_date: "",
  };
};

const generatePayroll = async () => {
  if (!payrollFormRef.value) return;

  const valid = await payrollFormRef.value.validate();
  if (!valid.valid) return;

  generatingPayroll.value = true;
  try {
    const response = await api.post(
      `/projects/${payrollProject.value.id}/generate-payroll`,
      payrollForm.value
    );
    showSnackbar(response.data.message, "success");
    closePayrollDialog();
  } catch (error) {
    const message =
      error.response?.data?.message || "Failed to generate payroll";
    showSnackbar(message, "error");
    console.error("Error generating payroll:", error);
  } finally {
    generatingPayroll.value = false;
  }
};

const showSnackbar = (text, color = "success") => {
  snackbarText.value = text;
  snackbarColor.value = color;
  snackbar.value = true;
};

// Lifecycle
onMounted(() => {
  fetchProjects();
});
</script>

<style scoped>
.project-card {
  height: 100%;
  display: flex;
  flex-direction: column;
}

.project-card .v-card-text {
  flex-grow: 1;
}
</style>
