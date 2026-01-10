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
        <v-row class="mb-4" align="center">
          <v-col cols="12" md="3">
            <v-text-field
              v-model="search"
              prepend-inner-icon="mdi-magnify"
              label="Search employees..."
              clearable
              variant="outlined"
              density="comfortable"
              hide-details
              @update:model-value="debouncedSearch"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="2">
            <v-select
              v-model="filters.project_id"
              :items="projects"
              item-title="name"
              item-value="id"
              label="Project"
              clearable
              variant="outlined"
              density="comfortable"
              hide-details
              @update:model-value="fetchEmployees"
            ></v-select>
          </v-col>
          <v-col cols="12" md="2">
            <v-select
              v-model="filters.contract_type"
              :items="contractTypeOptions"
              label="Contract Type"
              clearable
              variant="outlined"
              density="comfortable"
              hide-details
              @update:model-value="fetchEmployees"
            ></v-select>
          </v-col>
          <v-col cols="12" md="2">
            <v-select
              v-model="filters.activity_status"
              :items="activityStatusOptions"
              label="Activity Status"
              clearable
              variant="outlined"
              density="comfortable"
              hide-details
              @update:model-value="fetchEmployees"
            ></v-select>
          </v-col>
          <v-col cols="12" md="1">
            <v-select
              v-model="filters.work_schedule"
              :items="workScheduleOptions"
              label="Schedule"
              clearable
              variant="outlined"
              density="comfortable"
              hide-details
              @update:model-value="fetchEmployees"
            ></v-select>
          </v-col>
          <v-col cols="12" md="2" class="d-flex gap-2">
            <v-btn
              color="primary"
              variant="tonal"
              icon="mdi-refresh"
              @click="refreshEmployees"
              :loading="employeeStore.loading"
              title="Refresh"
            ></v-btn>
            <v-btn
              color="error"
              variant="tonal"
              icon="mdi-filter-remove"
              @click="clearFilters"
              title="Clear Filters"
            ></v-btn>
          </v-col>
        </v-row>

        <v-data-table
          :headers="headers"
          :items="employeeStore.employees"
          :loading="employeeStore.loading"
          :items-per-page="itemsPerPage"
          :page="page"
          :server-items-length="totalEmployees"
          @update:page="onPageChange"
          @update:items-per-page="onItemsPerPageChange"
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

          <template v-slot:item.work_schedule="{ item }">
            <v-chip
              size="small"
              :color="getWorkScheduleColor(item.work_schedule)"
            >
              {{
                item.work_schedule === "full_time" ? "Full Time" : "Part Time"
              }}
            </v-chip>
          </template>

          <template v-slot:item.activity_status="{ item }">
            <v-chip
              size="small"
              :color="getActivityStatusColor(item.activity_status)"
            >
              {{ formatActivityStatus(item.activity_status) }}
            </v-chip>
          </template>

          <template v-slot:item.actions="{ item }">
            <v-menu>
              <template v-slot:activator="{ props }">
                <v-btn
                  icon="mdi-dots-vertical"
                  size="small"
                  variant="text"
                  v-bind="props"
                  title="Actions"
                ></v-btn>
              </template>
              <v-list>
                <v-list-item @click="viewEmployee(item)">
                  <template v-slot:prepend>
                    <v-icon color="info">mdi-eye</v-icon>
                  </template>
                  <v-list-item-title>View Details</v-list-item-title>
                </v-list-item>
                <v-list-item @click="editEmployee(item)">
                  <template v-slot:prepend>
                    <v-icon color="primary">mdi-pencil</v-icon>
                  </template>
                  <v-list-item-title>Edit Employee</v-list-item-title>
                </v-list-item>
                <v-list-item @click="viewCredentials(item)">
                  <template v-slot:prepend>
                    <v-icon color="info">mdi-account-key</v-icon>
                  </template>
                  <v-list-item-title>View Credentials</v-list-item-title>
                </v-list-item>
                <v-divider></v-divider>
                <v-list-item @click="resignEmployee(item)" v-if="item.activity_status !== 'resigned'">
                  <template v-slot:prepend>
                    <v-icon color="warning">mdi-briefcase-remove-outline</v-icon>
                  </template>
                  <v-list-item-title>Process Resignation</v-list-item-title>
                </v-list-item>
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

    <!-- Process Resignation Dialog -->
    <v-dialog v-model="showResignDialog" max-width="600">
      <v-card>
        <v-card-title class="pa-6">
          <v-icon left color="warning">mdi-briefcase-remove-outline</v-icon>
          Process Employee Resignation
        </v-card-title>

        <v-divider></v-divider>

        <v-card-text class="pa-6">
          <v-alert type="info" variant="tonal" class="mb-4">
            Processing resignation for: <strong>{{ selectedEmployee?.first_name }} {{ selectedEmployee?.last_name }}</strong>
          </v-alert>

          <v-form ref="resignForm" v-model="resignFormValid">
            <v-text-field
              v-model="resignData.last_working_day"
              label="Last Working Day *"
              type="date"
              variant="outlined"
              :rules="[v => !!v || 'Required', v => new Date(v) > new Date() || 'Must be a future date']"
              :min="new Date().toISOString().split('T')[0]"
              class="mb-4"
            ></v-text-field>

            <v-textarea
              v-model="resignData.reason"
              label="Reason for Resignation (Optional)"
              variant="outlined"
              rows="3"
              counter="1000"
            ></v-textarea>

            <v-alert type="warning" variant="tonal" class="mt-4">
              This will submit a resignation request on behalf of the employee. The request will need to be approved through the Resignation Management system.
            </v-alert>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="showResignDialog = false">Cancel</v-btn>
          <v-btn 
            color="warning" 
            @click="submitResignation" 
            :loading="processing"
            :disabled="!resignFormValid"
          >
            Submit Resignation
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Employee Details/Edit Dialog -->
    <v-dialog
      v-model="showEmployeeDialog"
      max-width="1000px"
      scrollable
      persistent
    >
      <v-card>
        <v-card-title
          class="text-h5 py-4"
          :class="isEditing ? 'bg-primary' : 'bg-info'"
        >
          <v-icon start>{{ isEditing ? "mdi-pencil" : "mdi-eye" }}</v-icon>
          {{ isEditing ? "Edit Employee" : "Employee Details" }}
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-4" style="max-height: 70vh">
          <v-form ref="employeeForm" v-if="selectedEmployee">
            <!-- Info Alert for Edit Mode -->
            <v-alert
              v-if="isEditing"
              type="info"
              variant="tonal"
              density="compact"
              class="mb-4"
              icon="mdi-information"
            >
              Update employee information below. Position changes will
              automatically update the salary.
            </v-alert>

            <!-- View Mode Info Alert -->
            <v-alert
              v-else
              type="info"
              variant="tonal"
              density="compact"
              class="mb-4"
              icon="mdi-eye"
            >
              Viewing employee details. Click "Edit" to make changes.
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

              <v-col cols="12" md="3">
                <v-text-field
                  v-model="selectedEmployee.employee_number"
                  label="Employee Number"
                  prepend-inner-icon="mdi-identifier"
                  readonly
                  variant="outlined"
                  density="comfortable"
                  hint="Auto-generated unique ID"
                  persistent-hint
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="3">
                <v-text-field
                  v-model="selectedEmployee.first_name"
                  label="First Name"
                  prepend-inner-icon="mdi-account"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'outlined'"
                  :bg-color="!isEditing ? 'grey-lighten-4' : undefined"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="3">
                <v-text-field
                  v-model="selectedEmployee.middle_name"
                  label="Middle Name"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'outlined'"
                  :bg-color="!isEditing ? 'grey-lighten-4' : undefined"
                  density="comfortable"
                  hint="Optional"
                  persistent-hint
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="3">
                <v-text-field
                  v-model="selectedEmployee.last_name"
                  label="Last Name"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'outlined'"
                  :bg-color="!isEditing ? 'grey-lighten-4' : undefined"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="4">
                <v-text-field
                  v-model="selectedEmployee.date_of_birth"
                  label="Date of Birth"
                  type="date"
                  prepend-inner-icon="mdi-calendar"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'outlined'"
                  :bg-color="!isEditing ? 'grey-lighten-4' : undefined"
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
                  prepend-inner-icon="mdi-gender-male-female"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'outlined'"
                  :bg-color="!isEditing ? 'grey-lighten-4' : undefined"
                  density="comfortable"
                ></v-select>
              </v-col>

              <v-col cols="12" md="4">
                <v-text-field
                  v-model="selectedEmployee.mobile_number"
                  label="Mobile Number"
                  prepend-inner-icon="mdi-cellphone"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'outlined'"
                  :bg-color="!isEditing ? 'grey-lighten-4' : undefined"
                  density="comfortable"
                  hint="Format: 09171234567"
                  persistent-hint
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="selectedEmployee.email"
                  label="Email Address"
                  prepend-inner-icon="mdi-email"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'outlined'"
                  :bg-color="!isEditing ? 'grey-lighten-4' : undefined"
                  density="comfortable"
                  hint="Optional - Used for notifications"
                  persistent-hint
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-textarea
                  v-model="selectedEmployee.worker_address"
                  label="Complete Address"
                  prepend-inner-icon="mdi-map-marker"
                  rows="1"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'outlined'"
                  :bg-color="!isEditing ? 'grey-lighten-4' : undefined"
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
                <v-select
                  v-model="selectedEmployee.project_id"
                  :items="projects"
                  item-title="name"
                  item-value="id"
                  label="Project"
                  prepend-inner-icon="mdi-office-building"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'outlined'"
                  :bg-color="!isEditing ? 'grey-lighten-4' : undefined"
                  density="comfortable"
                ></v-select>
              </v-col>

              <v-col cols="12" md="6">
                <v-autocomplete
                  v-model="selectedEmployee.position"
                  :items="positionOptions"
                  label="Position"
                  prepend-inner-icon="mdi-badge-account"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'outlined'"
                  :bg-color="!isEditing ? 'grey-lighten-4' : undefined"
                  density="comfortable"
                  clearable
                  hint="Daily rate will auto-update based on position"
                  persistent-hint
                ></v-autocomplete>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="selectedEmployee.date_hired"
                  label="Date Hired"
                  type="date"
                  prepend-inner-icon="mdi-calendar-check"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'outlined'"
                  :bg-color="!isEditing ? 'grey-lighten-4' : undefined"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  :model-value="formatSalaryDisplay(selectedEmployee)"
                  label="Daily Rate"
                  prepend-inner-icon="mdi-cash"
                  prefix="₱"
                  suffix="/day"
                  readonly
                  variant="outlined"
                  bg-color="blue-lighten-5"
                  density="comfortable"
                  hint="Based on selected position"
                  persistent-hint
                ></v-text-field>
              </v-col>

              <!-- Section 3: Contract & Status -->
              <v-col cols="12" class="mt-4">
                <div class="text-h6 mb-2 d-flex align-center">
                  <v-icon start color="primary">mdi-file-document</v-icon>
                  Section 3: Contract & Status
                </div>
                <v-divider class="mb-4"></v-divider>
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
                  prepend-inner-icon="mdi-file-sign"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'outlined'"
                  :bg-color="!isEditing ? 'grey-lighten-4' : undefined"
                  density="comfortable"
                  hint="Regular, Probationary, or Contractual"
                  persistent-hint
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
                  prepend-inner-icon="mdi-account-check"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'outlined'"
                  :bg-color="!isEditing ? 'grey-lighten-4' : undefined"
                  density="comfortable"
                  hint="Current employment status"
                  persistent-hint
                ></v-select>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="selectedEmployee.work_schedule"
                  :items="workScheduleOptions"
                  label="Work Schedule"
                  prepend-inner-icon="mdi-clock-outline"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'outlined'"
                  :bg-color="!isEditing ? 'grey-lighten-4' : undefined"
                  density="comfortable"
                  hint="Full-time or Part-time"
                  persistent-hint
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
                  prepend-inner-icon="mdi-cash-multiple"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'outlined'"
                  :bg-color="!isEditing ? 'grey-lighten-4' : undefined"
                  density="comfortable"
                ></v-select>
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
            size="large"
            @click="isEditing = true"
            prepend-icon="mdi-pencil"
          >
            Edit
          </v-btn>
          <v-spacer></v-spacer>
          <v-btn variant="text" size="large" @click="closeDialog">
            {{ isEditing ? "Cancel" : "Close" }}
          </v-btn>
          <v-btn
            v-if="isEditing"
            color="primary"
            variant="elevated"
            size="large"
            @click="saveEmployee"
            :loading="saving"
            prepend-icon="mdi-content-save"
          >
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
import {
  CONTRACT_TYPES,
  ACTIVITY_STATUSES,
  WORK_SCHEDULES,
} from "@/utils/constants";

const toast = useToast();
const employeeStore = useEmployeeStore();
const { positionOptions, getRate, loadPositionRates } = usePositionRates();

const search = ref("");
const page = ref(1);
const itemsPerPage = ref(25);
const totalEmployees = ref(0);

const filters = ref({
  project_id: null,
  contract_type: null,
  activity_status: null,
  work_schedule: null,
  position: null,
});

// Existing employee list variables
const showEmployeeDialog = ref(false);
const selectedEmployee = ref(null);
const isEditing = ref(false);
const saving = ref(false);

// Resignation Dialog
const showResignDialog = ref(false);
const resignFormValid = ref(false);
const processing = ref(false);
const resignData = ref({
  last_working_day: '',
  reason: ''
});

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

const contractTypeOptions = CONTRACT_TYPES;
const activityStatusOptions = ACTIVITY_STATUSES;
const workScheduleOptions = WORK_SCHEDULES;

const headers = [
  { title: "Employee #", key: "employee_number", sortable: true },
  { title: "Name", key: "full_name", sortable: true },
  { title: "Gender", key: "gender", sortable: true },
  { title: "Project", key: "project", sortable: false },
  { title: "Position", key: "position", sortable: true },
  { title: "Schedule", key: "work_schedule", sortable: true },
  { title: "Activity Status", key: "activity_status", sortable: true },
  { title: "Actions", key: "actions", sortable: false, align: "center" },
];

onMounted(async () => {
  await fetchEmployees();
  await fetchProjects();
  await loadPositionRates();
});

// Debounce search to avoid too many API calls
let searchTimeout = null;
const debouncedSearch = () => {
  if (searchTimeout) clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    page.value = 1; // Reset to first page on search
    fetchEmployees();
  }, 500); // Wait 500ms after user stops typing
};

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
  const response = await employeeStore.fetchEmployees({
    search: search.value,
    page: page.value,
    per_page: itemsPerPage.value,
    ...filters.value,
  });

  // Update pagination data if the API returns it
  if (response && response.meta) {
    totalEmployees.value = response.meta.total || response.total || 0;
  }
}

function onPageChange(newPage) {
  page.value = newPage;
  fetchEmployees();
}

function onItemsPerPageChange(newItemsPerPage) {
  itemsPerPage.value = newItemsPerPage;
  page.value = 1; // Reset to first page
  fetchEmployees();
}

async function fetchProjects() {
  projects.value = await employeeStore.fetchProjects();
}

async function refreshEmployees() {
  page.value = 1;
  await fetchEmployees();
  toast.success("Employee list refreshed");
}

function clearFilters() {
  search.value = "";
  filters.value = {
    project_id: null,
    contract_type: null,
    activity_status: null,
    work_schedule: null,
    position: null,
  };
  page.value = 1;
  fetchEmployees();
  toast.info("Filters cleared");
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

function resignEmployee(employee) {
  selectedEmployee.value = employee;
  resignData.value = {
    last_working_day: '',
    reason: ''
  };
  showResignDialog.value = true;
}

async function submitResignation() {
  if (!resignFormValid.value) return;

  try {
    processing.value = true;
    await api.post('/resignations', {
      employee_id: selectedEmployee.value.id,
      ...resignData.value
    });
    toast.success('Resignation request submitted successfully!');
    showResignDialog.value = false;
    await fetchEmployees();
  } catch (error) {
    console.error('Error submitting resignation:', error);
    const message = error.response?.data?.message || 'Failed to submit resignation';
    toast.error(message);
  } finally {
    processing.value = false;
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

function getWorkScheduleColor(schedule) {
  return schedule === "full_time" ? "primary" : "info";
}

function getActivityStatusColor(status) {
  const colors = {
    active: "success",
    on_leave: "warning",
    resigned: "grey",
    terminated: "error",
    retired: "grey"
  };
  return colors[status] || "grey";
}

function formatActivityStatus(status) {
  if (!status) return "N/A";
  return status
    .split("_")
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(" ");
}

// Add Employee Dialog functions
async function saveNewEmployee(payload) {
  savingNew.value = true;
  try {
    const response = await api.post("/employees", payload.data);

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
    console.error("Validation errors:", error.response?.data?.errors);
    console.error("Sent data:", payload.data);
    const message =
      error.response?.data?.message || "Failed to create employee";
    toast.error(message);
  } finally {
    payload.setSaving(false);
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

// Format salary display based on position rate
function formatSalaryDisplay(employee) {
  if (!employee) return "N/A";

  // If employee has position, get the rate from position
  if (employee.position) {
    const rate = getRate(employee.position);
    if (rate) {
      return rate.toLocaleString("en-US", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    }
  }

  // Fallback to basic_salary field
  if (employee.basic_salary) {
    return Number(employee.basic_salary).toLocaleString("en-US", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    });
  }

  return "0.00";
}
</script>
