<template>
  <div>
    <v-row class="mb-4">
      <v-col cols="12" md="6">
        <h1 class="text-h4 font-weight-bold">Employees</h1>
      </v-col>
      <v-col cols="12" md="6" class="text-right">
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
              v-model="filters.employment_status"
              :items="statusOptions"
              label="Status"
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
              :items="positions"
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
                <v-text-field
                  v-model="selectedEmployee.position"
                  label="Position"
                  :readonly="!isEditing"
                  :variant="isEditing ? 'outlined' : 'plain'"
                  density="comfortable"
                ></v-text-field>
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
                  v-model="selectedEmployee.employment_status"
                  :items="[
                    { title: 'Regular', value: 'regular' },
                    { title: 'Probationary', value: 'probationary' },
                    { title: 'Contractual', value: 'contractual' },
                    { title: 'Active', value: 'active' },
                  ]"
                  label="Employment Status"
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

    <!-- Add Employee Dialog -->
    <v-dialog
      v-model="showAddEmployeeDialog"
      max-width="900px"
      persistent
      scrollable
    >
      <v-card>
        <v-card-title class="text-h5 py-4 bg-primary">
          <v-icon start>mdi-account-plus</v-icon>
          Add Employee
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-6" style="max-height: 600px">
          <v-form ref="addEmployeeForm">
            <v-row>
              <!-- Personal Information -->
              <v-col cols="12">
                <div class="text-h6 mb-2">Personal Information</div>
                <v-divider class="mb-4"></v-divider>
              </v-col>

              <v-col cols="12" md="4">
                <v-text-field
                  v-model="newEmployeeData.first_name"
                  label="First Name"
                  prepend-inner-icon="mdi-account"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="4">
                <v-text-field
                  v-model="newEmployeeData.middle_name"
                  label="Middle Name (Optional)"
                  variant="outlined"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="4">
                <v-text-field
                  v-model="newEmployeeData.last_name"
                  label="Last Name"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="newEmployeeData.email"
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
                  v-model="newEmployeeData.mobile_number"
                  label="Phone Number"
                  prepend-inner-icon="mdi-phone"
                  variant="outlined"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="newEmployeeData.date_of_birth"
                  label="Date of Birth"
                  type="date"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="newEmployeeData.gender"
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
                  v-model="newEmployeeData.worker_address"
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
                  v-model="newEmployeeData.employee_number"
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
                  v-model="newEmployeeData.project_id"
                  :items="projects"
                  item-title="name"
                  item-value="id"
                  label="Project"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                ></v-select>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="newEmployeeData.position"
                  label="Position"
                  prepend-inner-icon="mdi-badge-account"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="newEmployeeData.date_hired"
                  label="Hire Date"
                  type="date"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="newEmployeeData.employment_status"
                  :items="[
                    { title: 'Active', value: 'active' },
                    { title: 'Probationary', value: 'probationary' },
                  ]"
                  label="Employment Status"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                  hint="Current work status"
                  persistent-hint
                ></v-select>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="newEmployeeData.employment_type"
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
                  v-model="newEmployeeData.salary_type"
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
                  v-model.number="newEmployeeData.basic_salary"
                  label="Basic Pay Rate"
                  type="number"
                  prepend-inner-icon="mdi-cash"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                  hint="Default: ₱450 per day (can be modified)"
                  persistent-hint
                ></v-text-field>
              </v-col>

              <!-- Allowances -->
              <v-col cols="12">
                <div class="text-h6 mb-2 mt-4">Allowances</div>
                <v-divider class="mb-4"></v-divider>
              </v-col>

              <v-col cols="12" md="6">
                <v-checkbox
                  v-model="newEmployeeData.has_water_allowance"
                  label="Water Allowance"
                  color="primary"
                  hide-details
                ></v-checkbox>
                <v-text-field
                  v-if="newEmployeeData.has_water_allowance"
                  v-model.number="newEmployeeData.water_allowance"
                  label="Amount"
                  type="number"
                  prefix="₱"
                  variant="outlined"
                  density="compact"
                  class="mt-2"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-checkbox
                  v-model="newEmployeeData.has_cola"
                  label="COLA (Cost of Living Allowance)"
                  color="primary"
                  hide-details
                ></v-checkbox>
                <v-text-field
                  v-if="newEmployeeData.has_cola"
                  v-model.number="newEmployeeData.cola"
                  label="Amount"
                  type="number"
                  prefix="₱"
                  variant="outlined"
                  density="compact"
                  class="mt-2"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-checkbox
                  v-model="newEmployeeData.has_incentives"
                  label="Incentives"
                  color="primary"
                  hide-details
                ></v-checkbox>
                <v-text-field
                  v-if="newEmployeeData.has_incentives"
                  v-model.number="newEmployeeData.incentives"
                  label="Amount"
                  type="number"
                  prefix="₱"
                  variant="outlined"
                  density="compact"
                  class="mt-2"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-checkbox
                  v-model="newEmployeeData.has_ppe"
                  label="PPE (Personal Protective Equipment)"
                  color="primary"
                  hide-details
                ></v-checkbox>
                <v-text-field
                  v-if="newEmployeeData.has_ppe"
                  v-model.number="newEmployeeData.ppe"
                  label="Amount"
                  type="number"
                  prefix="₱"
                  variant="outlined"
                  density="compact"
                  class="mt-2"
                ></v-text-field>
              </v-col>

              <!-- User Role -->
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
                      <strong>Password:</strong> Auto-generated (LastName +
                      EmpID + 2 random digits)
                    </li>
                    <li><strong>Role:</strong> Selected below</li>
                    <li><strong>Status:</strong> Active</li>
                    <li>Employee must change password on first login</li>
                  </ul>
                </v-alert>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="newEmployeeData.role"
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
          <v-btn variant="text" @click="closeAddDialog" :disabled="savingNew">
            Cancel
          </v-btn>
          <v-btn
            color="primary"
            variant="elevated"
            @click="saveNewEmployee"
            :loading="savingNew"
          >
            <v-icon start>mdi-check</v-icon>
            Add Employee
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

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
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useEmployeeStore } from "@/stores/employee";
import { useToast } from "vue-toastification";
import api from "@/services/api";

const toast = useToast();
const employeeStore = useEmployeeStore();

const search = ref("");
const filters = ref({
  project_id: null,
  employment_status: null,
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
const addEmployeeForm = ref(null);
const savingNew = ref(false);

const newEmployeeData = ref({
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
  employment_status: "active",
  employment_type: "regular",
  salary_type: "daily",
  basic_salary: 450,
  has_water_allowance: false,
  water_allowance: 0,
  has_cola: false,
  cola: 0,
  has_incentives: false,
  incentives: 0,
  has_ppe: false,
  ppe: 0,
  role: "employee",
});

const rules = {
  required: (v) => !!v || "This field is required",
  email: (v) => /.+@.+\..+/.test(v) || "Email must be valid",
  emailOptional: (v) => !v || /.+@.+\..+/.test(v) || "Email must be valid",
};

const projects = ref([]);
const employeeForm = ref(null);

const statusOptions = [
  { title: "Active", value: "active" },
  { title: "Probationary", value: "probationary" },
  { title: "Resigned", value: "resigned" },
  { title: "Terminated", value: "terminated" },
  { title: "Retired", value: "retired" },
];

const employmentTypeOptions = [
  { title: "Regular", value: "regular" },
  { title: "Contractual", value: "contractual" },
  { title: "Part Time", value: "part_time" },
];

const positions = ref([]);

const headers = [
  { title: "Employee #", key: "employee_number", sortable: true },
  { title: "Name", key: "full_name", sortable: true },
  { title: "Project", key: "project", sortable: false },
  { title: "Position", key: "position", sortable: true },
  { title: "Type", key: "employment_type", sortable: true },
  { title: "Status", key: "is_active", sortable: true },
  { title: "Actions", key: "actions", sortable: false, align: "center" },
];

onMounted(async () => {
  await fetchEmployees();
  await fetchProjects();
  await fetchPositions();
});

async function fetchEmployees() {
  await employeeStore.fetchEmployees({
    search: search.value,
    ...filters.value,
  });
}

async function fetchProjects() {
  projects.value = await employeeStore.fetchProjects();
}

async function fetchPositions() {
  try {
    const response = await api.get("/employees", {
      params: { per_page: 1000 },
    });
    // Extract unique positions from employees
    const uniquePositions = [
      ...new Set(
        response.data.data
          .map((emp) => emp.position)
          .filter((pos) => pos && pos.trim() !== "")
      ),
    ].sort();
    positions.value = uniquePositions;
  } catch (error) {
    console.error("Error fetching positions:", error);
  }
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
      employment_status: "suspended",
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
      employment_status: "terminated",
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
async function saveNewEmployee() {
  const { valid } = await addEmployeeForm.value.validate();
  if (!valid) {
    toast.warning("Please fill in all required fields");
    return;
  }

  savingNew.value = true;
  try {
    const response = await api.post("/employees", newEmployeeData.value);

    temporaryPassword.value = response.data.temporary_password;
    createdEmployee.value = response.data.employee;
    createdEmployee.value.role = response.data.role;
    createdEmployeeUsername.value = response.data.username;

    toast.success("Employee created successfully!");
    closeAddDialog();
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

function closeAddDialog() {
  showAddEmployeeDialog.value = false;
  addEmployeeForm.value?.reset();
  newEmployeeData.value = {
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
    employment_status: "",
    employment_type: "",
    salary_type: "daily",
    basic_salary: 450,
    has_water_allowance: false,
    water_allowance: 0,
    has_cola: false,
    cola: 0,
    has_incentives: false,
    incentives: 0,
    has_ppe: false,
    ppe: 0,
    role: "employee",
  };
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
</script>
