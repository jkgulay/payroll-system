<template>
  <div class="projects-page">
    <!-- Modern Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="page-title-section">
          <div class="page-icon-badge">
            <v-icon size="20">mdi-office-building</v-icon>
          </div>
          <div>
            <h1 class="page-title">Project Management</h1>
            <p class="page-subtitle">
              Manage projects and track employee assignments
            </p>
          </div>
        </div>
        <div class="action-buttons">
          <button
            class="action-btn action-btn-primary"
            @click="openCreateDialog"
          >
            <v-icon size="20">mdi-plus</v-icon>
            <span>New Project</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Modern Filter Tabs -->
    <div class="filter-tabs">
      <button
        class="filter-tab"
        :class="{ active: filterTab === 'all' }"
        @click="filterTab = 'all'"
      >
        <v-icon size="18">mdi-folder-multiple</v-icon>
        <span>All Projects</span>
      </button>
      <button
        class="filter-tab"
        :class="{ active: filterTab === 'active' }"
        @click="filterTab = 'active'"
      >
        <v-icon size="18">mdi-folder-open</v-icon>
        <span>Active</span>
      </button>
      <button
        class="filter-tab"
        :class="{ active: filterTab === 'completed' }"
        @click="filterTab = 'completed'"
      >
        <v-icon size="18">mdi-folder-check</v-icon>
        <span>Completed</span>
      </button>
    </div>

    <!-- Search Bar -->
    <v-text-field
      v-model="search"
      prepend-inner-icon="mdi-magnify"
      label="Search projects..."
      variant="outlined"
      density="compact"
      clearable
      class="search-field"
    ></v-text-field>

    <!-- Loading State -->
    <v-progress-linear
      v-if="loading"
      indeterminate
      color="#ED985F"
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
        <v-card class="project-card" elevation="0">
          <div class="project-card-header">
            <div class="project-icon-wrapper">
              <v-icon size="18" color="white">mdi-folder-open</v-icon>
            </div>
            <v-chip
              :color="project.is_active ? 'success' : 'grey'"
              size="small"
              variant="flat"
              class="status-chip"
            >
              {{ project.is_active ? "Active" : "Completed" }}
            </v-chip>
          </div>

          <v-card-title class="project-card-title">
            <div class="project-name">{{ project.name }}</div>
            <div class="project-code">{{ project.code }}</div>
          </v-card-title>

          <v-card-text class="project-card-content">
            <div class="description-section">
              <div class="section-label">
                <v-icon size="14">mdi-text</v-icon>
                Description
              </div>
              <div class="section-value">
                {{ project.description || "No description provided" }}
              </div>
            </div>

            <div class="info-grid">
              <div class="info-item">
                <div class="info-icon">
                  <v-icon size="18">mdi-account-hard-hat</v-icon>
                </div>
                <div>
                  <div class="info-label">Project Head</div>
                  <div class="info-value">
                    {{
                      project.head_employee
                        ? `${project.head_employee.first_name} ${project.head_employee.last_name}`
                        : "Not assigned"
                    }}
                  </div>
                </div>
              </div>

              <div class="info-item">
                <div class="info-icon">
                  <v-icon size="18">mdi-account-group</v-icon>
                </div>
                <div>
                  <div class="info-label">Team Size</div>
                  <div class="info-value">
                    {{ project.employees_count || 0 }} employee(s)
                  </div>
                </div>
              </div>
            </div>
          </v-card-text>

          <v-card-actions class="project-card-actions">
            <button
              class="card-action-btn card-action-primary"
              @click="viewProject(project)"
            >
              <v-icon size="16">mdi-eye</v-icon>
              <span>View Details</span>
            </button>

            <v-menu>
              <template v-slot:activator="{ props }">
                <button class="card-action-btn card-action-menu" v-bind="props">
                  <v-icon size="18">mdi-dots-vertical</v-icon>
                </button>
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

    <!-- Create/Edit Dialog - Modern UI -->
    <v-dialog v-model="dialog" max-width="700px" persistent>
      <v-card class="modern-dialog-card" elevation="24">
        <!-- Enhanced Header -->
        <v-card-title class="modern-dialog-header">
          <div class="d-flex align-center w-100">
            <div class="dialog-icon-badge">
              <v-icon size="24">{{
                editMode ? "mdi-pencil" : "mdi-folder-plus"
              }}</v-icon>
            </div>
            <div class="flex-grow-1">
              <div class="text-h5 font-weight-bold">
                {{ editMode ? "Edit Project" : "New Project" }}
              </div>
              <div class="text-subtitle-2 text-white-70">
                {{
                  editMode
                    ? "Update project information"
                    : "Create a new project"
                }}
              </div>
            </div>
            <v-btn
              icon
              variant="text"
              color="white"
              @click="closeDialog"
              size="small"
            >
              <v-icon>mdi-close</v-icon>
            </v-btn>
          </div>
        </v-card-title>

        <v-card-text class="pa-6">
          <v-form ref="formRef" @submit.prevent="saveProject">
            <v-row>
              <!-- Project Code -->
              <v-col cols="12">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="primary">mdi-barcode</v-icon>
                    Project Code
                    <v-chip size="x-small" color="info" class="ml-2"
                      >Auto-generated</v-chip
                    >
                  </label>
                  <v-text-field
                    v-model="formData.code"
                    placeholder="Leave blank to auto-generate"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-barcode"
                    color="primary"
                    hint="Leave blank to auto-generate"
                    persistent-hint
                  ></v-text-field>
                </div>
              </v-col>

              <!-- Project Name -->
              <v-col cols="12">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="primary">mdi-folder</v-icon>
                    Project Name <span class="text-error">*</span>
                  </label>
                  <v-text-field
                    v-model="formData.name"
                    placeholder="Enter project name"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-folder"
                    color="primary"
                    :rules="[(v) => !!v || 'Name is required']"
                    required
                  ></v-text-field>
                </div>
              </v-col>

              <!-- Description -->
              <v-col cols="12">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="primary">mdi-text-box</v-icon>
                    Description
                  </label>
                  <v-textarea
                    v-model="formData.description"
                    placeholder="Enter project description"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-text"
                    color="primary"
                    rows="3"
                  ></v-textarea>
                </div>
              </v-col>

              <!-- Project Head -->
              <v-col cols="12">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="primary"
                      >mdi-account-tie</v-icon
                    >
                    Project Head
                  </label>
                  <v-autocomplete
                    v-model="formData.head_employee_id"
                    :items="employees"
                    item-title="full_name"
                    item-value="id"
                    placeholder="Select project head"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-account-tie"
                    color="primary"
                    clearable
                    :loading="loadingEmployees"
                  ></v-autocomplete>
                </div>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <!-- Enhanced Actions -->
        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn
            variant="text"
            color="grey-darken-1"
            size="large"
            @click="closeDialog"
            prepend-icon="mdi-close"
          >
            Cancel
          </v-btn>
          <v-btn
            color="primary"
            size="large"
            :loading="saving"
            @click="saveProject"
            prepend-icon="mdi-check"
            class="px-6"
            elevation="2"
          >
            <span class="font-weight-bold">{{
              editMode ? "Update" : "Create"
            }}</span>
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Project Details Dialog -->
    <v-dialog v-model="detailsDialog" max-width="900px" persistent>
      <v-card v-if="selectedProject" class="modern-dialog-card" elevation="24">
        <v-card-title class="modern-dialog-header">
          <div class="d-flex align-center w-100">
            <div class="dialog-icon-badge">
              <v-icon size="24">mdi-folder-open</v-icon>
            </div>
            <div class="flex-grow-1">
              <div class="text-h5 font-weight-bold">
                {{ selectedProject.name }}
              </div>
              <div class="text-subtitle-2 text-white-70">
                {{ selectedProject.code }}
              </div>
            </div>
            <v-chip
              :color="selectedProject.is_active ? 'success' : 'grey'"
              variant="flat"
              size="default"
            >
              {{ selectedProject.is_active ? "Active" : "Completed" }}
            </v-chip>
            <v-btn
              icon
              variant="text"
              color="white"
              @click="detailsDialog = false"
              size="small"
              class="ml-2"
            >
              <v-icon>mdi-close</v-icon>
            </v-btn>
          </div>
        </v-card-title>

        <v-card-text class="pa-6">
          <div class="mb-4">
            <div
              class="text-subtitle-2 font-weight-bold mb-2"
              style="color: #001f3d"
            >
              <v-icon size="small" color="#ED985F" class="mr-1"
                >mdi-text-box</v-icon
              >
              Description
            </div>
            <div class="text-body-2" style="color: rgba(0, 31, 61, 0.8)">
              {{ selectedProject.description || "No description" }}
            </div>
          </div>

          <div class="mb-4">
            <div
              class="text-subtitle-2 font-weight-bold mb-2"
              style="color: #001f3d"
            >
              <v-icon size="small" color="#ED985F" class="mr-1"
                >mdi-account-tie</v-icon
              >
              Project Head
            </div>
            <div class="text-body-2" style="color: rgba(0, 31, 61, 0.8)">
              {{
                selectedProject.head_employee
                  ? `${selectedProject.head_employee.first_name} ${selectedProject.head_employee.last_name} (${selectedProject.head_employee.position})`
                  : "Not assigned"
              }}
            </div>
          </div>

          <v-divider class="my-4"></v-divider>

          <div class="text-h6 font-weight-bold mb-4" style="color: #001f3d">
            <v-icon size="20" color="#ED985F" class="mr-2"
              >mdi-account-group</v-icon
            >
            Assigned Employees ({{ projectEmployees.length }})
          </div>

          <v-progress-linear
            v-if="loadingDetails"
            indeterminate
            color="#ED985F"
          ></v-progress-linear>

          <v-data-table
            v-else
            :headers="employeeHeaders"
            :items="projectEmployees"
            :items-per-page="10"
            density="comfortable"
            class="elevation-0"
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

            <template v-slot:item.activity_status="{ item }">
              <v-chip
                :color="item.activity_status === 'active' ? 'success' : 'grey'"
                size="small"
              >
                {{ item.activity_status }}
              </v-chip>
            </template>
          </v-data-table>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn
            variant="text"
            color="grey-darken-1"
            size="large"
            @click="detailsDialog = false"
            prepend-icon="mdi-close"
          >
            Close
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Snackbar -->
    <v-snackbar v-model="snackbar" :color="snackbarColor" :timeout="3000">
      {{ snackbarText }}
    </v-snackbar>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import api from "@/services/api";

// State
const loading = ref(false);
const loadingEmployees = ref(false);
const loadingDetails = ref(false);
const saving = ref(false);
const dialog = ref(false);
const detailsDialog = ref(false);
const editMode = ref(false);
const filterTab = ref("all");
const search = ref("");
const projects = ref([]);
const employees = ref([]);
const selectedProject = ref(null);
const projectEmployees = ref([]);
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

const formRef = ref(null);

// Table headers
const employeeHeaders = [
  { title: "Employee", key: "full_name", sortable: true },
  { title: "Position", key: "position", sortable: true },
  { title: "Salary", key: "basic_salary", sortable: true },
  { title: "Status", key: "activity_status", sortable: true },
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
        (p.description && p.description.toLowerCase().includes(searchLower)),
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

<style scoped lang="scss">
.projects-page {
  max-width: 1600px;
  margin: 0 auto;
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

.action-btn-primary {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: white;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);
}

.action-btn-primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.4);
}

.filter-tabs {
  display: flex;
  gap: 8px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.filter-tab {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  border-radius: 10px;
  border: 1.5px solid rgba(0, 31, 61, 0.15);
  background: white;
  font-size: 14px;
  font-weight: 600;
  color: #001f3d;
  cursor: pointer;
  transition: all 0.2s ease;
}

.filter-tab:hover {
  background: rgba(0, 31, 61, 0.04);
  border-color: rgba(0, 31, 61, 0.25);
}

.filter-tab.active {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: white;
  border-color: transparent;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);
}

.search-field {
  margin-bottom: 24px;
  max-width: 450px;
}

.project-card {
  height: 100%;
  display: flex;
  flex-direction: column;
  border-radius: 16px !important;
  border: 1px solid rgba(0, 31, 61, 0.08);
  overflow: hidden;
  transition: all 0.3s ease;
  background: white;
  position: relative;

  &:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(0, 31, 61, 0.12) !important;
    border-color: rgba(237, 152, 95, 0.3);
  }
}

.project-card-header {
  background: linear-gradient(135deg, #001f3d 0%, #1a3a5a 100%);
  padding: 12px 16px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  position: relative;
  overflow: hidden;
}

.project-icon-wrapper {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);
  position: relative;
  z-index: 1;
}

.status-chip {
  position: relative;
  z-index: 1;
  font-weight: 600 !important;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.project-card-title {
  padding: 20px 20px 16px !important;
}

.project-name {
  font-size: 20px;
  font-weight: 700;
  color: #001f3d;
  margin-bottom: 4px;
  letter-spacing: -0.3px;
  line-height: 1.3;
}

.project-code {
  font-size: 13px;
  color: #ed985f;
  font-weight: 600;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.project-card-content {
  flex-grow: 1;
  padding: 0 20px 20px !important;
}

.description-section {
  margin-bottom: 20px;
  padding: 16px;
  background: rgba(0, 31, 61, 0.02);
  border-radius: 10px;
  border: 1px solid rgba(0, 31, 61, 0.06);
}

.section-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 11px;
  font-weight: 700;
  color: rgba(0, 31, 61, 0.6);
  text-transform: uppercase;
  letter-spacing: 0.8px;
  margin-bottom: 8px;

  .v-icon {
    color: #ed985f !important;
  }
}

.section-value {
  font-size: 14px;
  color: #001f3d;
  line-height: 1.6;
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.info-grid {
  display: grid;
  grid-template-columns: 1fr;
  gap: 12px;
}

.info-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding: 12px;
  background: white;
  border-radius: 10px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  transition: all 0.2s ease;

  &:hover {
    background: rgba(237, 152, 95, 0.04);
    border-color: rgba(237, 152, 95, 0.2);
  }
}

.info-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: rgba(237, 152, 95, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;

  .v-icon {
    color: #ed985f !important;
  }
}

.info-label {
  font-size: 11px;
  font-weight: 600;
  color: rgba(0, 31, 61, 0.6);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
}

.info-value {
  font-size: 14px;
  font-weight: 600;
  color: #001f3d;
}

.project-card-actions {
  padding: 16px 20px !important;
  border-top: 1px solid rgba(0, 31, 61, 0.06);
  background: rgba(0, 31, 61, 0.01);
  display: flex;
  gap: 8px;
}

.card-action-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 16px;
  border-radius: 8px;
  border: none;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
}

.card-action-primary {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: white;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.25);

  &:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(237, 152, 95, 0.35);
  }

  &:active {
    transform: translateY(0);
  }
}

.card-action-menu {
  background: white;
  border: 1.5px solid rgba(0, 31, 61, 0.15);
  color: #001f3d;
  width: 40px;
  height: 40px;
  padding: 0;
  justify-content: center;

  &:hover {
    background: rgba(0, 31, 61, 0.04);
    border-color: rgba(0, 31, 61, 0.25);
  }
}

.modern-dialog-card {
  border-radius: 16px;
  overflow: hidden;
}

.modern-dialog-header {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: white;
  padding: 24px;

  .v-icon {
    color: #ffffff !important;
  }
}

.dialog-icon-badge {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: white;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  margin-right: 16px;

  .v-icon {
    color: #ed985f !important;
  }
}

.form-field-wrapper {
  margin-bottom: 8px;
}

.form-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 600;
  color: #001f3d;
  margin-bottom: 8px;
}
</style>
