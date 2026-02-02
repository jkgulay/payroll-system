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
            <h1 class="page-title">Department Management</h1>
            <p class="page-subtitle">
              Manage departments and track employee assignments
            </p>
          </div>
        </div>
        <div class="action-buttons">
          <button
            class="action-btn action-btn-primary"
            @click="openCreateDialog"
          >
            <v-icon size="20">mdi-plus</v-icon>
            <span>New Department</span>
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
        <span>All Departments</span>
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
      label="Search departments..."
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
                Designation
              </div>
              <div class="section-value">
                {{ project.description || "No designation provided" }}
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
      <div class="text-h6 mt-4 text-grey">No departments found</div>
      <div class="text-body-2 text-grey">
        {{
          search
            ? "Try adjusting your search"
            : "Create your first department to get started"
        }}
      </div>
    </v-card>

    <!-- Create/Edit Dialog - Modern UI -->
    <v-dialog v-model="dialog" max-width="700px" persistent>
      <v-card class="backup-security-dialog">
        <!-- Header -->
        <v-card-title class="dialog-header">
          <div class="header-content">
            <div class="icon-wrapper">
              <v-icon size="24" color="white">{{
                editMode ? "mdi-pencil" : "mdi-folder-plus"
              }}</v-icon>
            </div>
            <div class="header-text">
              <h2 class="dialog-title">
                {{ editMode ? "Edit Department" : "New Department" }}
              </h2>
              <p class="dialog-subtitle">
                {{
                  editMode
                    ? "Update department information"
                    : "Create a new department"
                }}
              </p>
            </div>
          </div>
          <v-btn
            icon
            variant="text"
            @click="closeDialog"
            size="small"
            class="close-btn"
          >
            <v-icon size="20">mdi-close</v-icon>
          </v-btn>
        </v-card-title>

        <v-divider />

        <v-card-text class="pa-6">
          <v-form ref="formRef" @submit.prevent="saveProject">
            <v-row>
              <!-- Project Code -->
              <v-col cols="12">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="primary">mdi-barcode</v-icon>
                    Department Code
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
                    Department Name <span class="text-error">*</span>
                  </label>
                  <v-text-field
                    v-model="formData.name"
                    placeholder="Enter department name"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-folder"
                    color="primary"
                    :rules="[(v) => !!v || 'Name is required']"
                    required
                  ></v-text-field>
                </div>
              </v-col>

              <!-- Designation -->
              <v-col cols="12">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="primary">mdi-text-box</v-icon>
                    Designation
                  </label>
                  <v-textarea
                    v-model="formData.description"
                    placeholder="Enter department designation"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-text"
                    color="primary"
                    rows="3"
                  ></v-textarea>
                </div>
              </v-col>

              <!-- Schedule Settings -->
              <v-col cols="12">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="primary"
                      >mdi-clock-time-four</v-icon
                    >
                    Schedule Settings
                  </label>
                  <v-row>
                    <v-col cols="12" md="4">
                      <v-text-field
                        v-model="formData.time_in"
                        label="Time In"
                        type="time"
                        variant="outlined"
                        density="comfortable"
                        prepend-inner-icon="mdi-clock-in"
                        color="primary"
                        clearable
                      ></v-text-field>
                    </v-col>
                    <v-col cols="12" md="4">
                      <v-text-field
                        v-model="formData.time_out"
                        label="Time Out"
                        type="time"
                        variant="outlined"
                        density="comfortable"
                        prepend-inner-icon="mdi-clock-out"
                        color="primary"
                        clearable
                      ></v-text-field>
                    </v-col>
                    <v-col cols="12" md="4">
                      <v-text-field
                        v-model.number="formData.grace_period_minutes"
                        label="Grace Period (minutes)"
                        type="number"
                        min="0"
                        max="180"
                        variant="outlined"
                        density="comfortable"
                        prepend-inner-icon="mdi-timer-outline"
                        color="primary"
                        clearable
                      ></v-text-field>
                    </v-col>
                  </v-row>
                </div>
              </v-col>

              <!-- Project Head -->
              <v-col cols="12">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="primary"
                      >mdi-account-tie</v-icon
                    >
                    Department Head
                  </label>
                  <v-autocomplete
                    v-model="formData.head_employee_id"
                    :items="employees"
                    item-title="full_name"
                    item-value="id"
                    placeholder="Select department head"
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

        <v-card-actions class="dialog-actions">
          <v-btn variant="text" @click="closeDialog" class="cancel-btn">
            Cancel
          </v-btn>
          <v-spacer></v-spacer>
          <v-btn
            color="primary"
            variant="elevated"
            :loading="saving"
            @click="saveProject"
            prepend-icon="mdi-check"
            class="save-btn"
          >
            {{ editMode ? "Update" : "Create" }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Project Details Dialog -->
    <v-dialog v-model="detailsDialog" max-width="1000px" persistent scrollable>
      <v-card v-if="selectedProject" class="backup-security-dialog">
        <!-- Header -->
        <v-card-title class="dialog-header">
          <div class="header-content">
            <div class="icon-wrapper">
              <v-icon size="24" color="white">mdi-folder-open</v-icon>
            </div>
            <div class="header-text">
              <h2 class="dialog-title">{{ selectedProject.name }}</h2>
              <p class="dialog-subtitle">{{ selectedProject.code }}</p>
            </div>
            <v-chip
              :color="selectedProject.is_active ? 'success' : 'grey'"
              variant="flat"
              size="small"
              class="status-chip-header"
            >
              {{ selectedProject.is_active ? "Active" : "Completed" }}
            </v-chip>
          </div>
          <v-btn
            icon
            variant="text"
            @click="detailsDialog = false"
            size="small"
            class="close-btn"
          >
            <v-icon size="20">mdi-close</v-icon>
          </v-btn>
        </v-card-title>

        <v-divider />

        <!-- Content -->
        <v-card-text class="dialog-content">
          <v-tabs v-model="detailsTab" class="config-tabs">
            <v-tab value="info">
              <v-icon start>mdi-information-outline</v-icon>
              Department Info
            </v-tab>
            <v-tab value="employees">
              <v-icon start>mdi-account-group</v-icon>
              Employees ({{ projectEmployees.length }})
            </v-tab>
          </v-tabs>

          <v-window v-model="detailsTab" class="config-window">
            <!-- Department Info Tab -->
            <v-window-item value="info">
              <div class="config-section">
                <!-- Designation -->
                <div class="setting-group">
                  <div class="group-header">
                    <h4 class="group-title">
                      <v-icon size="20" class="mr-2">mdi-text-box</v-icon>
                      Designation
                    </h4>
                  </div>
                  <div class="group-content">
                    <div class="info-text">
                      {{
                        selectedProject.description || "No designation provided"
                      }}
                    </div>
                  </div>
                </div>

                <!-- Department Head -->
                <div class="setting-group">
                  <div class="group-header">
                    <h4 class="group-title">
                      <v-icon size="20" class="mr-2">mdi-account-tie</v-icon>
                      Department Head
                    </h4>
                  </div>
                  <div class="group-content">
                    <div v-if="selectedProject.head_employee" class="info-row">
                      <div class="info-label">Name:</div>
                      <div class="info-value">
                        {{ selectedProject.head_employee.first_name }}
                        {{ selectedProject.head_employee.last_name }}
                      </div>
                    </div>
                    <div v-if="selectedProject.head_employee" class="info-row">
                      <div class="info-label">Position:</div>
                      <div class="info-value">
                        {{ selectedProject.head_employee.position || "N/A" }}
                      </div>
                    </div>
                    <div
                      v-if="!selectedProject.head_employee"
                      class="info-text"
                    >
                      No department head assigned
                    </div>
                  </div>
                </div>

                <!-- Schedule -->
                <div class="setting-group">
                  <div class="group-header">
                    <h4 class="group-title">
                      <v-icon size="20" class="mr-2"
                        >mdi-clock-time-four</v-icon
                      >
                      Schedule Settings
                    </h4>
                  </div>
                  <div class="group-content">
                    <div class="info-row">
                      <div class="info-label">
                        <v-icon size="18" color="success">mdi-clock-in</v-icon>
                        Time In:
                      </div>
                      <div class="info-value">
                        {{
                          formatScheduleTime(selectedProject.time_in) ||
                          DEFAULT_TIME_IN
                        }}
                      </div>
                    </div>
                    <div class="info-row">
                      <div class="info-label">
                        <v-icon size="18" color="error">mdi-clock-out</v-icon>
                        Time Out:
                      </div>
                      <div class="info-value">
                        {{
                          formatScheduleTime(selectedProject.time_out) ||
                          DEFAULT_TIME_OUT
                        }}
                      </div>
                    </div>
                    <div class="info-row">
                      <div class="info-label">
                        <v-icon size="18" color="primary"
                          >mdi-timer-outline</v-icon
                        >
                        Grace Period:
                      </div>
                      <div class="info-value">
                        {{
                          selectedProject.grace_period_minutes !== null &&
                          selectedProject.grace_period_minutes !== undefined
                            ? `${selectedProject.grace_period_minutes} minutes`
                            : `${DEFAULT_GRACE} minutes`
                        }}
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </v-window-item>

            <!-- Employees Tab -->
            <v-window-item value="employees">
              <div class="config-section">
                <div class="section-header-with-action">
                  <div>
                    <h3 class="section-title">Assigned Employees</h3>
                    <p class="section-description">
                      {{ projectEmployees.length }} employee(s) currently
                      assigned to this department
                    </p>
                  </div>
                  <v-btn
                    v-if="selectedProject.is_active"
                    color="primary"
                    variant="elevated"
                    prepend-icon="mdi-account-multiple-plus"
                    @click="openAddEmployeesDialog"
                  >
                    Add Employees
                  </v-btn>
                </div>

                <v-progress-linear
                  v-if="loadingDetails"
                  indeterminate
                  color="primary"
                  class="mb-4"
                ></v-progress-linear>

                <div v-else class="employees-table-wrapper">
                  <v-data-table
                    :headers="employeeHeaders"
                    :items="projectEmployees"
                    :items-per-page="10"
                    density="comfortable"
                    class="elevation-0 modern-table"
                  >
                    <template v-slot:item.full_name="{ item }">
                      <div class="employee-cell">
                        <div class="employee-name">{{ item.full_name }}</div>
                        <div class="employee-number">
                          {{ item.employee_number }}
                        </div>
                      </div>
                    </template>

                    <template v-slot:item.basic_salary="{ item }">
                      <div class="salary-cell">
                        <div class="salary-amount">
                          ₱{{
                            Number(item.basic_salary).toLocaleString("en-US", {
                              minimumFractionDigits: 2,
                            })
                          }}
                        </div>
                        <div class="salary-type">{{ item.salary_type }}</div>
                      </div>
                    </template>

                    <template v-slot:item.activity_status="{ item }">
                      <v-chip
                        :color="
                          item.activity_status === 'active' ? 'success' : 'grey'
                        "
                        size="small"
                        variant="flat"
                      >
                        {{ item.activity_status }}
                      </v-chip>
                    </template>
                  </v-data-table>
                </div>
              </div>
            </v-window-item>
          </v-window>
        </v-card-text>

        <v-divider />

        <!-- Actions -->
        <v-card-actions class="dialog-actions">
          <v-btn
            variant="text"
            @click="detailsDialog = false"
            class="cancel-btn"
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

    <!-- Transfer Employees Dialog -->
    <v-dialog v-model="transferDialog" max-width="800px" persistent>
      <v-card class="backup-security-dialog">
        <!-- Header -->
        <v-card-title class="dialog-header">
          <div class="header-content">
            <div class="icon-wrapper">
              <v-icon size="24" color="white">mdi-account-switch</v-icon>
            </div>
            <div class="header-text">
              <h2 class="dialog-title">Transfer Employees</h2>
              <p class="dialog-subtitle">{{ selectedProject?.name }}</p>
            </div>
          </div>
          <v-btn
            icon
            variant="text"
            @click="closeTransferDialog"
            size="small"
            class="close-btn"
          >
            <v-icon size="20">mdi-close</v-icon>
          </v-btn>
        </v-card-title>

        <v-divider />

        <v-card-text class="pa-6">
          <div class="mb-4">
            <v-alert type="info" variant="tonal" density="compact">
              Transfer employees to other departments before marking this
              department as complete.
            </v-alert>
          </div>

          <!-- Transfer Mode Selector -->
          <v-tabs v-model="transferMode" color="primary" class="mb-4">
            <v-tab value="bulk">
              <v-icon start>mdi-account-multiple-check</v-icon>
              Bulk Transfer
            </v-tab>
            <v-tab value="individual">
              <v-icon start>mdi-account-arrow-right</v-icon>
              Individual Transfer
            </v-tab>
          </v-tabs>

          <!-- Bulk Transfer Mode -->
          <div v-if="transferMode === 'bulk'">
            <v-row>
              <v-col cols="12">
                <div class="form-field-wrapper">
                  <label class="form-label">
                    <v-icon size="small" color="primary"
                      >mdi-office-building</v-icon
                    >
                    Target Department <span class="text-error">*</span>
                  </label>
                  <v-autocomplete
                    v-model="bulkTransferData.target_project_id"
                    :items="availableDepartments"
                    item-title="name"
                    item-value="id"
                    placeholder="Select department"
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-office-building"
                    color="primary"
                    clearable
                    :rules="[(v) => !!v || 'Department is required']"
                  >
                    <template v-slot:item="{ props, item }">
                      <v-list-item v-bind="props">
                        <template v-slot:prepend>
                          <v-icon>mdi-folder-open</v-icon>
                        </template>
                        <v-list-item-title>{{
                          item.raw.name
                        }}</v-list-item-title>
                        <v-list-item-subtitle>{{
                          item.raw.code
                        }}</v-list-item-subtitle>
                      </v-list-item>
                    </template>
                  </v-autocomplete>
                </div>
              </v-col>

              <v-col cols="12">
                <v-alert type="success" variant="tonal" density="compact">
                  All {{ projectEmployees.length }} employee(s) will be
                  transferred to the selected department.
                </v-alert>
              </v-col>
            </v-row>
          </div>

          <!-- Individual Transfer Mode -->
          <div v-if="transferMode === 'individual'">
            <v-data-table
              :headers="transferHeaders"
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

              <template v-slot:item.target_project="{ item }">
                <v-autocomplete
                  v-model="individualTransfers[item.id]"
                  :items="availableDepartments"
                  item-title="name"
                  item-value="id"
                  placeholder="Select department"
                  variant="outlined"
                  density="compact"
                  hide-details
                  clearable
                  style="min-width: 200px"
                >
                  <template v-slot:item="{ props, item: deptItem }">
                    <v-list-item v-bind="props">
                      <template v-slot:prepend>
                        <v-icon size="small">mdi-folder-open</v-icon>
                      </template>
                      <v-list-item-title>{{
                        deptItem.raw.name
                      }}</v-list-item-title>
                      <v-list-item-subtitle>{{
                        deptItem.raw.code
                      }}</v-list-item-subtitle>
                    </v-list-item>
                  </template>
                </v-autocomplete>
              </template>
            </v-data-table>
          </div>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="dialog-actions">
          <v-btn
            variant="text"
            @click="skipTransfer"
            prepend-icon="mdi-skip-next"
            class="cancel-btn"
          >
            Skip & Mark Complete
          </v-btn>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="closeTransferDialog" class="cancel-btn">
            Cancel
          </v-btn>
          <v-btn
            color="primary"
            variant="elevated"
            :loading="transferring"
            @click="executeTransfer"
            prepend-icon="mdi-check"
            class="save-btn"
          >
            Transfer & Mark Complete
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Add Employees Dialog -->
    <v-dialog v-model="addEmployeesDialog" max-width="700px" persistent>
      <v-card class="backup-security-dialog">
        <!-- Header -->
        <v-card-title class="dialog-header">
          <div class="header-content">
            <div class="icon-wrapper">
              <v-icon size="24" color="white">mdi-account-multiple-plus</v-icon>
            </div>
            <div class="header-text">
              <h2 class="dialog-title">Add Employees</h2>
              <p class="dialog-subtitle">{{ selectedProject?.name }}</p>
            </div>
          </div>
          <v-btn
            icon
            variant="text"
            @click="addEmployeesDialog = false"
            size="small"
            class="close-btn"
          >
            <v-icon size="20">mdi-close</v-icon>
          </v-btn>
        </v-card-title>

        <v-divider />

        <v-card-text class="pa-6">
          <v-autocomplete
            v-model="selectedEmployeesToAdd"
            :items="availableEmployees"
            item-title="full_name"
            item-value="id"
            label="Select Employees"
            placeholder="Choose employees to add"
            variant="outlined"
            density="comfortable"
            prepend-inner-icon="mdi-account-search"
            color="primary"
            multiple
            chips
            closable-chips
            clearable
            :loading="loadingEmployees"
          >
            <template v-slot:chip="{ props, item }">
              <v-chip v-bind="props" closable size="small">
                {{ item.raw.full_name }}
              </v-chip>
            </template>
            <template v-slot:item="{ props, item }">
              <v-list-item
                v-bind="props"
                :title="item.raw.full_name"
                :subtitle="`${item.raw.position} • ${item.raw.employee_number}`"
              >
                <template v-slot:prepend>
                  <v-icon>mdi-account</v-icon>
                </template>
              </v-list-item>
            </template>
          </v-autocomplete>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="dialog-actions">
          <v-btn
            variant="text"
            @click="addEmployeesDialog = false"
            class="cancel-btn"
          >
            Cancel
          </v-btn>
          <v-spacer></v-spacer>
          <v-btn
            color="primary"
            variant="elevated"
            :loading="addingEmployees"
            :disabled="!selectedEmployeesToAdd.length"
            @click="executeAddEmployees"
            prepend-icon="mdi-check"
            class="save-btn"
          >
            Add {{ selectedEmployeesToAdd.length }} Employee(s)
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
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
const transferDialog = ref(false);
const addEmployeesDialog = ref(false);
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
const transferMode = ref("bulk");
const transferring = ref(false);
const addingEmployees = ref(false);
const selectedEmployeesToAdd = ref([]);
const detailsTab = ref("info");

// Transfer data
const bulkTransferData = ref({
  target_project_id: null,
});
const individualTransfers = ref({});

// Form data
const formData = ref({
  code: "",
  name: "",
  description: "",
  time_in: null,
  time_out: null,
  grace_period_minutes: null,
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

const transferHeaders = [
  { title: "Employee", key: "full_name", sortable: true },
  { title: "Position", key: "position", sortable: true },
  { title: "Target Department", key: "target_project", sortable: false },
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

const availableDepartments = computed(() => {
  return projects.value.filter(
    (p) => p.is_active && p.id !== selectedProject.value?.id,
  );
});

const availableEmployees = computed(() => {
  // Get employees not in the current department
  const currentDeptEmployeeIds = projectEmployees.value.map((e) => e.id);
  return employees.value.filter(
    (emp) => !currentDeptEmployeeIds.includes(emp.id),
  );
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
    const response = await api.get("/employees", {
      params: { per_page: -1 }, // Get all employees without pagination
    });
    // Handle paginated response
    const employeeData = response.data.data || response.data;
    employees.value = employeeData.map((emp) => ({
      ...emp,
      full_name: `${emp.first_name} ${emp.last_name}`,
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
    time_in: DEFAULT_TIME_IN,
    time_out: DEFAULT_TIME_OUT,
    grace_period_minutes: DEFAULT_GRACE,
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
    time_in: formatScheduleTime(project.time_in) || DEFAULT_TIME_IN,
    time_out: formatScheduleTime(project.time_out) || DEFAULT_TIME_OUT,
    grace_period_minutes:
      project.grace_period_minutes !== undefined &&
      project.grace_period_minutes !== null
        ? project.grace_period_minutes
        : DEFAULT_GRACE,
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
    const payload = normalizeSchedulePayload(formData.value);
    if (editMode.value) {
      await api.put(`/projects/${formData.value.id}`, payload);
      showSnackbar("Project updated successfully", "success");
    } else {
      await api.post("/projects", payload);
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
    time_in: DEFAULT_TIME_IN,
    time_out: DEFAULT_TIME_OUT,
    grace_period_minutes: DEFAULT_GRACE,
    head_employee_id: null,
    is_active: true,
  };
};

const formatScheduleTime = (value) => {
  if (!value) return null;
  const [hours, minutes] = value.split(":");
  if (!hours || !minutes) return value;
  return `${hours.padStart(2, "0")}:${minutes.padStart(2, "0")}`;
};

const DEFAULT_TIME_IN = "07:30";
const DEFAULT_TIME_OUT = "17:00";
const DEFAULT_GRACE = 0;

const viewProject = async (project) => {
  selectedProject.value = project;
  detailsDialog.value = true;
  await fetchProjectEmployees(project.id);
  // Fetch employees list if not loaded
  if (employees.value.length === 0) {
    await fetchEmployees();
  }
};

const openAddEmployeesDialog = () => {
  selectedEmployeesToAdd.value = [];
  addEmployeesDialog.value = true;
};

const executeAddEmployees = async () => {
  if (selectedEmployeesToAdd.value.length === 0) {
    showSnackbar("Please select employees to add", "error");
    return;
  }

  addingEmployees.value = true;
  try {
    const response = await api.post(
      `/projects/${selectedProject.value.id}/add-employees`,
      { employee_ids: selectedEmployeesToAdd.value },
    );

    showSnackbar(response.data.message, "success");
    addEmployeesDialog.value = false;
    selectedEmployeesToAdd.value = [];

    // Refresh employee list
    await fetchProjectEmployees(selectedProject.value.id);
    await fetchProjects();
  } catch (error) {
    showSnackbar("Failed to add employees", "error");
    console.error("Error adding employees:", error);
  } finally {
    addingEmployees.value = false;
  }
};

const markComplete = async (project) => {
  // Check if department has employees
  selectedProject.value = project;
  await fetchProjectEmployees(project.id);

  if (projectEmployees.value.length > 0) {
    // Open transfer dialog
    transferDialog.value = true;
    individualTransfers.value = {};
  } else {
    // No employees, just mark complete
    if (
      !confirm(`Are you sure you want to mark "${project.name}" as complete?`)
    )
      return;
    await completeProject(project);
  }
};

const fetchProjectEmployees = async (projectId) => {
  loadingDetails.value = true;
  try {
    const response = await api.get(`/projects/${projectId}/employees`);
    projectEmployees.value = response.data;
  } catch (error) {
    showSnackbar("Failed to load project employees", "error");
    console.error("Error fetching project employees:", error);
  } finally {
    loadingDetails.value = false;
  }
};

const closeTransferDialog = () => {
  transferDialog.value = false;
  transferMode.value = "bulk";
  bulkTransferData.value = { target_project_id: null };
  individualTransfers.value = {};
};

const skipTransfer = async () => {
  if (
    !confirm(
      `Skip transfer and mark "${selectedProject.value?.name}" as complete?\n\nEmployees will remain in this department.`,
    )
  )
    return;

  await completeProject(selectedProject.value);
  closeTransferDialog();
};

const executeTransfer = async () => {
  if (transferMode.value === "bulk") {
    if (!bulkTransferData.value.target_project_id) {
      showSnackbar("Please select a target department", "error");
      return;
    }

    if (
      !confirm(
        `Transfer all ${projectEmployees.value.length} employee(s) to the selected department?`,
      )
    )
      return;

    transferring.value = true;
    try {
      const transfers = projectEmployees.value.map((emp) => ({
        employee_id: emp.id,
        target_project_id: bulkTransferData.value.target_project_id,
      }));

      await api.post(
        `/projects/${selectedProject.value.id}/transfer-employees`,
        { transfers },
      );

      await completeProject(selectedProject.value);
      showSnackbar(
        "Employees transferred and department marked complete",
        "success",
      );
      closeTransferDialog();
      await fetchProjects();
    } catch (error) {
      showSnackbar("Failed to transfer employees", "error");
      console.error("Error transferring employees:", error);
    } finally {
      transferring.value = false;
    }
  } else {
    // Individual transfer
    const transfers = Object.entries(individualTransfers.value)
      .filter(([_, targetId]) => targetId)
      .map(([employeeId, targetId]) => ({
        employee_id: parseInt(employeeId),
        target_project_id: targetId,
      }));

    if (transfers.length === 0) {
      showSnackbar("Please assign departments to employees", "error");
      return;
    }

    if (
      !confirm(
        `Transfer ${transfers.length} employee(s) to their selected departments?`,
      )
    )
      return;

    transferring.value = true;
    try {
      await api.post(
        `/projects/${selectedProject.value.id}/transfer-employees`,
        { transfers },
      );

      await completeProject(selectedProject.value);
      showSnackbar(
        `${transfers.length} employee(s) transferred and department marked complete`,
        "success",
      );
      closeTransferDialog();
      await fetchProjects();
    } catch (error) {
      showSnackbar("Failed to transfer employees", "error");
      console.error("Error transferring employees:", error);
    } finally {
      transferring.value = false;
    }
  }
};

const completeProject = async (project) => {
  try {
    await api.post(`/projects/${project.id}/mark-complete`);
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

const normalizeSchedulePayload = (payload) => ({
  ...payload,
  time_in: payload.time_in || null,
  time_out: payload.time_out || null,
  grace_period_minutes:
    payload.grace_period_minutes === "" ||
    payload.grace_period_minutes === undefined
      ? null
      : payload.grace_period_minutes,
});

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
  line-clamp: 2;
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

// Backup & Security Dialog Styles
.backup-security-dialog {
  border-radius: 16px;
}

.dialog-header {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: white;
  padding: 20px 24px;
  position: relative;
}

.header-content {
  display: flex;
  align-items: center;
  gap: 14px;
  width: 100%;
  padding-right: 110px;
}

.header-text {
  flex: 1;
  min-width: 0;
}

.icon-wrapper {
  width: 48px;
  height: 48px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.dialog-title {
  font-size: 20px;
  font-weight: 700;
  margin: 0;
  color: white;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  line-height: 1.2;
}

.dialog-subtitle {
  font-size: 13px;
  margin: 3px 0 0 0;
  opacity: 0.9;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
  line-height: 1.2;
}

.status-chip-header {
  position: absolute;
  top: 22px;
  right: 60px;
  font-weight: 600 !important;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-size: 11px !important;
}

.close-btn {
  position: absolute;
  top: 14px;
  right: 14px;

  :deep(.v-icon) {
    color: white !important;
  }
}

.dialog-content {
  padding: 0;
  max-height: 70vh;
}

.config-tabs {
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
  padding: 0 24px;

  :deep(.v-tab) {
    text-transform: none;
    font-weight: 600;
    letter-spacing: 0;
  }
}

.config-window {
  padding: 24px;
}

.config-section {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.section-header-with-action {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 16px;
}

.section-title {
  font-size: 20px;
  font-weight: 700;
  color: #001f3d;
  margin: 0 0 8px 0;
}

.section-description {
  font-size: 14px;
  color: rgba(0, 31, 61, 0.6);
  margin: 0;
}

.setting-group {
  background: white;
  border: 1px solid rgba(0, 0, 0, 0.1);
  border-radius: 12px;
  overflow: hidden;
}

.group-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  background: rgba(237, 152, 95, 0.06);
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}

.group-title {
  font-size: 16px;
  font-weight: 600;
  color: #001f3d;
  margin: 0;
  display: flex;
  align-items: center;
}

.group-content {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.info-text {
  font-size: 14px;
  color: rgba(0, 31, 61, 0.8);
  line-height: 1.6;
}

.info-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.info-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #001f3d;
  flex: 1;
}

.info-value {
  font-size: 14px;
  font-weight: 600;
  color: #001f3d;
  text-align: right;
}

.employees-table-wrapper {
  border: 1px solid rgba(0, 0, 0, 0.1);
  border-radius: 12px;
  overflow: hidden;
}

.modern-table {
  :deep(.v-data-table-header) {
    background: rgba(237, 152, 95, 0.06);
  }

  :deep(th) {
    font-weight: 600 !important;
    color: #001f3d !important;
  }
}

.employee-cell {
  padding: 4px 0;
}

.employee-name {
  font-weight: 600;
  color: #001f3d;
  font-size: 14px;
}

.employee-number {
  font-size: 12px;
  color: rgba(0, 31, 61, 0.6);
  margin-top: 2px;
}

.salary-cell {
  text-align: right;
}

.salary-amount {
  font-weight: 600;
  color: #001f3d;
  font-size: 14px;
}

.salary-type {
  font-size: 12px;
  color: rgba(0, 31, 61, 0.6);
  margin-top: 2px;
  text-transform: capitalize;
}

.dialog-actions {
  padding: 20px 28px;
  background: rgba(0, 0, 0, 0.02);
}

.cancel-btn {
  text-transform: none;
  font-weight: 600;
}
</style>
