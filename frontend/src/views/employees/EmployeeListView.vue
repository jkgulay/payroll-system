<template>
  <div class="employees-page">
    <!-- Modern Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="page-title-section">
          <div class="page-icon-badge">
            <v-icon size="22">mdi-hard-hat</v-icon>
          </div>
          <div>
            <h1 class="page-title">Employee Management</h1>
            <p class="page-subtitle">
              Manage and track all employee information
            </p>
          </div>
        </div>
        <div class="action-buttons">
          <button
            class="action-btn action-btn-secondary"
            @click="$router.push({ name: 'biometric-import' })"
          >
            <v-icon size="20">mdi-file-upload</v-icon>
            <span>Import Employees</span>
          </button>
          <button
            class="action-btn action-btn-primary"
            @click="showAddEmployeeDialog = true"
          >
            <v-icon size="20">mdi-account-plus</v-icon>
            <span>Add Employee</span>
          </button>
        </div>
      </div>
    </div>

    <div class="modern-card">
      <div class="filters-section">
        <v-row class="mb-0" align="center" dense>
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
              label="Department"
              clearable
              variant="outlined"
              density="comfortable"
              hide-details
              @update:model-value="fetchEmployees"
            ></v-select>
          </v-col>
          <v-col cols="6" md="2">
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
          <v-col cols="6" md="2">
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
          <v-col cols="auto" class="d-flex align-center">
            <v-btn
              color="#ED985F"
              variant="tonal"
              icon="mdi-refresh"
              @click="refreshEmployees"
              :loading="employeeStore.loading"
              title="Refresh"
            ></v-btn>
          </v-col>
          <v-col cols="auto" class="d-flex align-center">
            <v-btn
              color="error"
              variant="tonal"
              icon="mdi-filter-remove"
              @click="clearFilters"
              title="Clear Filters"
            ></v-btn>
          </v-col>
        </v-row>
      </div>

      <div class="table-section">
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
          class="elevation-0"
        >
          <template v-slot:item.employee_number="{ item }">
            <strong>{{ item.employee_number }}</strong>
          </template>

          <template v-slot:item.biometric_id="{ item }">
            <v-chip
              v-if="item.biometric_id"
              size="small"
              color="info"
              variant="tonal"
            >
              {{ item.biometric_id }}
            </v-chip>
            <span v-else class="text-medium-emphasis">--</span>
          </template>

          <template v-slot:item.full_name="{ item }">
            <div>
              <div class="font-weight-medium">
                {{ item.first_name }} {{ item.middle_name }}
                {{ item.last_name }}
              </div>
              <div
                class="text-caption text-medium-emphasis"
                v-if="item.username"
              >
                @{{ item.username }}
              </div>
            </div>
          </template>

          <template v-slot:item.project="{ item }">
            <span v-if="item.project">{{ item.project.name }}</span>
            <span v-else class="text-medium-emphasis">N/A</span>
          </template>

          <template v-slot:item.date_hired="{ item }">
            <span v-if="item.date_hired">
              {{
                new Date(item.date_hired).toLocaleDateString("en-US", {
                  year: "numeric",
                  month: "short",
                  day: "numeric",
                })
              }}
            </span>
            <span v-else class="text-medium-emphasis">--</span>
          </template>

          <template v-slot:item.contract_type="{ item }">
            <v-chip
              size="small"
              :color="getContractTypeColor(item.contract_type)"
              variant="tonal"
            >
              {{ formatContractType(item.contract_type) }}
            </v-chip>
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

          <template v-slot:item.position="{ item }">
            {{ item.position || "N/A" }}
          </template>

          <template v-slot:item.pay_rate="{ item }">
            <div class="text-right">
              <div class="font-weight-bold">
                {{ formatCurrency(getEmployeeEffectiveRate(item)) }}
              </div>
              <v-chip
                v-if="item.custom_pay_rate"
                size="x-small"
                color="success"
                variant="tonal"
                class="mt-1"
              >
                <v-icon start size="x-small">mdi-star</v-icon>
                Custom
              </v-chip>
            </div>
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
                <v-list-item @click="openPayRateDialog(item)">
                  <template v-slot:prepend>
                    <v-icon color="success">mdi-cash</v-icon>
                  </template>
                  <v-list-item-title>Update Pay Rate</v-list-item-title>
                </v-list-item>
                <v-divider></v-divider>
                <v-list-item
                  @click="resignEmployee(item)"
                  v-if="item.activity_status !== 'resigned'"
                >
                  <template v-slot:prepend>
                    <v-icon color="warning"
                      >mdi-briefcase-remove-outline</v-icon
                    >
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
      </div>
    </div>

    <!-- Process Resignation Dialog -->
    <v-dialog v-model="showResignDialog" max-width="600">
      <v-card>
        <div class="dialog-header">
          <div class="header-icon-badge">
            <v-icon size="24">mdi-briefcase-remove-outline</v-icon>
          </div>
          <div>
            <div class="header-title">Process Employee Resignation</div>
            <div class="header-subtitle">
              Submit resignation request on behalf of employee
            </div>
          </div>
        </div>

        <v-divider></v-divider>

        <v-card-text class="pa-6">
          <v-alert type="info" variant="tonal" class="mb-4">
            Processing resignation for:
            <strong
              >{{ selectedEmployee?.first_name }}
              {{ selectedEmployee?.last_name }}</strong
            >
          </v-alert>

          <v-form ref="resignForm" v-model="resignFormValid">
            <v-text-field
              v-model="resignData.last_working_day"
              label="Last Working Day *"
              type="date"
              variant="outlined"
              :rules="[
                (v) => !!v || 'Required',
                (v) => new Date(v) > new Date() || 'Must be a future date',
              ]"
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
              This will submit a resignation request on behalf of the employee.
              The request will need to be approved through the Resignation
              Management system.
            </v-alert>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn
            variant="outlined"
            class="cancel-btn"
            @click="showResignDialog = false"
          >
            Cancel
          </v-btn>
          <v-btn
            class="primary-action-btn"
            @click="submitResignation"
            :loading="processing"
            :disabled="!resignFormValid"
          >
            <v-icon start>mdi-check</v-icon>
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
        <div class="dialog-header">
          <div class="header-icon-badge">
            <v-icon size="24">{{
              isEditing ? "mdi-pencil" : "mdi-eye"
            }}</v-icon>
          </div>
          <div>
            <div class="header-title">
              {{ isEditing ? "Edit Employee" : "Employee Details" }}
            </div>
            <div class="header-subtitle">
              {{
                isEditing
                  ? "Update employee information"
                  : "View complete employee information"
              }}
            </div>
          </div>
        </div>
        <v-divider></v-divider>

        <v-card-text class="pt-4" style="max-height: 70vh">
          <!-- VIEW MODE - Details Display -->
          <div v-if="!isEditing && selectedEmployee">
            <!-- Personal Information Section -->
            <v-card class="mb-4" variant="outlined">
              <v-card-title class="bg-blue-lighten-5">
                <v-icon start color="primary">mdi-account-circle</v-icon>
                Personal Information
              </v-card-title>
              <v-card-text class="pt-4">
                <v-row>
                  <v-col cols="12" md="6">
                    <div class="detail-row">
                      <div class="detail-label">
                        <v-icon size="small" class="mr-2"
                          >mdi-identifier</v-icon
                        >
                        Employee Number
                      </div>
                      <div class="detail-value">
                        {{ selectedEmployee.employee_number }}
                      </div>
                    </div>
                  </v-col>
                  <v-col cols="12" md="6">
                    <div class="detail-row">
                      <div class="detail-label">
                        <v-icon size="small" class="mr-2">mdi-account</v-icon>
                        Full Name
                      </div>
                      <div class="detail-value">
                        {{ selectedEmployee.first_name }}
                        {{ selectedEmployee.middle_name || "" }}
                        {{ selectedEmployee.last_name }}
                      </div>
                    </div>
                  </v-col>
                  <v-col cols="12" md="4">
                    <div class="detail-row">
                      <div class="detail-label">
                        <v-icon size="small" class="mr-2">mdi-calendar</v-icon>
                        Date of Birth
                      </div>
                      <div class="detail-value">
                        {{ formatDate(selectedEmployee.date_of_birth) }}
                      </div>
                    </div>
                  </v-col>
                  <v-col cols="12" md="4">
                    <div class="detail-row">
                      <div class="detail-label">
                        <v-icon size="small" class="mr-2"
                          >mdi-gender-male-female</v-icon
                        >
                        Gender
                      </div>
                      <div class="detail-value text-capitalize">
                        {{ selectedEmployee.gender }}
                      </div>
                    </div>
                  </v-col>
                  <v-col cols="12" md="4">
                    <div class="detail-row">
                      <div class="detail-label">
                        <v-icon size="small" class="mr-2">mdi-cellphone</v-icon>
                        Mobile Number
                      </div>
                      <div class="detail-value">
                        {{ selectedEmployee.mobile_number || "Not provided" }}
                      </div>
                    </div>
                  </v-col>
                  <v-col cols="12" md="6">
                    <div class="detail-row">
                      <div class="detail-label">
                        <v-icon size="small" class="mr-2">mdi-email</v-icon>
                        Email Address
                      </div>
                      <div class="detail-value">
                        {{ selectedEmployee.email || "Not provided" }}
                      </div>
                    </div>
                  </v-col>
                  <v-col cols="12" md="6">
                    <div class="detail-row">
                      <div class="detail-label">
                        <v-icon size="small" class="mr-2"
                          >mdi-map-marker</v-icon
                        >
                        Address
                      </div>
                      <div class="detail-value">
                        {{ selectedEmployee.worker_address || "Not provided" }}
                      </div>
                    </div>
                  </v-col>
                </v-row>
              </v-card-text>
            </v-card>

            <!-- Employment Information Section -->
            <v-card class="mb-4" variant="outlined">
              <v-card-title class="bg-green-lighten-5">
                <v-icon start color="success">mdi-briefcase</v-icon>
                Employment Information
              </v-card-title>
              <v-card-text class="pt-4">
                <v-row>
                  <v-col cols="12" md="6">
                    <div class="detail-row">
                      <div class="detail-label">
                        <v-icon size="small" class="mr-2"
                          >mdi-office-building</v-icon
                        >
                        Department
                      </div>
                      <div class="detail-value">
                        {{
                          projects?.find(
                            (p) => p.id === selectedEmployee.project_id,
                          )?.name || "Not assigned"
                        }}
                      </div>
                    </div>
                  </v-col>
                  <v-col cols="12" md="6">
                    <div class="detail-row">
                      <div class="detail-label">
                        <v-icon size="small" class="mr-2"
                          >mdi-badge-account</v-icon
                        >
                        Position
                      </div>
                      <div class="detail-value">
                        {{ selectedEmployee.position || "Not assigned" }}
                      </div>
                    </div>
                  </v-col>
                  <v-col cols="12" md="6">
                    <div class="detail-row">
                      <div class="detail-label">
                        <v-icon size="small" class="mr-2"
                          >mdi-calendar-check</v-icon
                        >
                        Date Hired
                      </div>
                      <div class="detail-value">
                        {{ formatDate(selectedEmployee.date_hired) }}
                      </div>
                    </div>
                  </v-col>
                  <v-col cols="12" md="6">
                    <div class="detail-row">
                      <div class="detail-label">
                        <v-icon size="small" class="mr-2">mdi-cash</v-icon>
                        Daily Rate
                      </div>
                      <div class="detail-value text-success font-weight-bold">
                        ₱{{ formatSalaryDisplay(selectedEmployee) }}/day
                      </div>
                    </div>
                  </v-col>
                </v-row>
              </v-card-text>
            </v-card>

            <!-- Contract & Status Section -->
            <v-card variant="outlined">
              <v-card-title class="bg-orange-lighten-5">
                <v-icon start color="warning">mdi-file-document</v-icon>
                Contract & Status
              </v-card-title>
              <v-card-text class="pt-4">
                <v-row>
                  <v-col cols="12" md="6">
                    <div class="detail-row">
                      <div class="detail-label">
                        <v-icon size="small" class="mr-2">mdi-file-sign</v-icon>
                        Contract Type
                      </div>
                      <div class="detail-value text-capitalize">
                        {{ selectedEmployee.contract_type }}
                      </div>
                    </div>
                  </v-col>
                  <v-col cols="12" md="6">
                    <div class="detail-row">
                      <div class="detail-label">
                        <v-icon size="small" class="mr-2"
                          >mdi-account-check</v-icon
                        >
                        Activity Status
                      </div>
                      <div class="detail-value">
                        <v-chip
                          :color="
                            getActivityStatusColor(
                              selectedEmployee.activity_status,
                            )
                          "
                          size="small"
                          class="text-capitalize"
                        >
                          {{
                            selectedEmployee.activity_status?.replace("_", " ")
                          }}
                        </v-chip>
                      </div>
                    </div>
                  </v-col>
                  <v-col cols="12" md="6">
                    <div class="detail-row">
                      <div class="detail-label">
                        <v-icon size="small" class="mr-2"
                          >mdi-clock-outline</v-icon
                        >
                        Work Schedule
                      </div>
                      <div class="detail-value text-capitalize">
                        {{ selectedEmployee.work_schedule }}
                      </div>
                    </div>
                  </v-col>
                  <v-col cols="12" md="6">
                    <div class="detail-row">
                      <div class="detail-label">
                        <v-icon size="small" class="mr-2"
                          >mdi-cash-multiple</v-icon
                        >
                        Salary Type
                      </div>
                      <div class="detail-value text-capitalize">
                        {{ selectedEmployee.salary_type }}
                      </div>
                    </div>
                  </v-col>
                </v-row>
              </v-card-text>
            </v-card>
          </div>

          <!-- EDIT MODE - Form Fields -->
          <v-form ref="employeeForm" v-else-if="isEditing && selectedEmployee">
            <!-- Info Alert for Edit Mode -->
            <v-alert
              type="info"
              variant="tonal"
              density="compact"
              class="mb-4"
              icon="mdi-information"
            >
              Update employee information below. Position changes will
              automatically update the salary.
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
                  :items="projects || []"
                  item-title="name"
                  item-value="id"
                  label="Department"
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
                  @update:model-value="onPositionChange"
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

              <!-- Section 4: Government Contributions -->
              <v-col cols="12" class="mt-4">
                <div class="text-h6 mb-2 d-flex align-center">
                  <v-icon start color="primary">mdi-shield-account</v-icon>
                  Section 4: Government Contributions
                </div>
                <v-divider class="mb-4"></v-divider>
              </v-col>

              <v-col cols="12">
                <v-alert type="info" variant="tonal" density="compact" class="mb-4">
                  Select which government contributions apply to this employee. Only selected contributions will be calculated in the payroll.
                </v-alert>
              </v-col>

              <v-col cols="12" md="4">
                <v-checkbox
                  v-model="selectedEmployee.has_sss"
                  label="SSS (Social Security System)"
                  prepend-icon="mdi-shield-account"
                  color="primary"
                  :readonly="!isEditing"
                  :disabled="!isEditing"
                  hide-details
                  density="comfortable"
                ></v-checkbox>
              </v-col>

              <v-col cols="12" md="4">
                <v-checkbox
                  v-model="selectedEmployee.has_philhealth"
                  label="PhilHealth"
                  prepend-icon="mdi-hospital-box"
                  color="success"
                  :readonly="!isEditing"
                  :disabled="!isEditing"
                  hide-details
                  density="comfortable"
                ></v-checkbox>
              </v-col>

              <v-col cols="12" md="4">
                <v-checkbox
                  v-model="selectedEmployee.has_pagibig"
                  label="Pag-IBIG"
                  prepend-icon="mdi-home-city"
                  color="orange"
                  :readonly="!isEditing"
                  :disabled="!isEditing"
                  hide-details
                  density="comfortable"
                ></v-checkbox>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn
            variant="outlined"
            class="cancel-btn"
            size="large"
            @click="closeDialog"
          >
            {{ isEditing ? "Cancel" : "Close" }}
          </v-btn>
          <v-btn
            v-if="!isEditing"
            class="primary-action-btn"
            size="large"
            @click="toggleEditMode"
          >
            <v-icon start>mdi-pencil</v-icon>
            Edit
          </v-btn>
          <v-btn
            v-if="isEditing"
            class="primary-action-btn"
            size="large"
            @click="saveEmployee"
            :loading="saving"
          >
            <v-icon start>mdi-content-save</v-icon>
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
        <div class="dialog-header">
          <div class="header-icon-badge">
            <v-icon size="24">mdi-shield-check</v-icon>
          </div>
          <div>
            <div class="header-title">Employee Account Created</div>
            <div class="header-subtitle">
              Save these credentials - they will not be shown again
            </div>
          </div>
        </div>
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
            variant="outlined"
            class="cancel-btn"
            prepend-icon="mdi-content-copy"
            @click="copyCredentials"
          >
            Copy Credentials
          </v-btn>
          <v-spacer></v-spacer>
          <v-btn class="primary-action-btn" @click="showPasswordDialog = false">
            <v-icon start>mdi-check</v-icon>
            Done
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- View Credentials Dialog -->
    <v-dialog v-model="showCredentialsDialog" max-width="600px">
      <v-card>
        <div class="dialog-header">
          <div class="header-icon-badge">
            <v-icon size="24">mdi-account-key</v-icon>
          </div>
          <div>
            <div class="header-title">Employee Credentials</div>
            <div class="header-subtitle">
              View and manage employee login credentials
            </div>
          </div>
        </div>
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
            variant="outlined"
            class="cancel-btn"
            prepend-icon="mdi-content-copy"
            @click="copyEmployeeCredentials"
            :disabled="loadingCredentials || !employeeCredentials?.has_account"
          >
            Copy
          </v-btn>
          <v-btn
            variant="outlined"
            class="secondary-action-btn"
            prepend-icon="mdi-lock-reset"
            @click="resetEmployeePassword"
            :loading="resettingPassword"
          >
            Generate New Password
          </v-btn>
          <v-spacer></v-spacer>
          <v-btn class="primary-action-btn" @click="closeCredentialsDialog">
            <v-icon start>mdi-check</v-icon>
            Close
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Pay Rate Update Dialog -->
    <v-dialog v-model="showPayRateDialog" max-width="600px">
      <v-card>
        <div class="dialog-header">
          <div class="header-icon-badge">
            <v-icon size="24">mdi-cash</v-icon>
          </div>
          <div>
            <div class="header-title">Update Employee Pay Rate</div>
            <div class="header-subtitle">Set custom pay rate for employee</div>
          </div>
        </div>
        <v-divider></v-divider>

        <v-card-text class="pt-6">
          <v-alert type="info" variant="tonal" class="mb-4">
            <div class="text-h6 mb-2">
              {{ payRateEmployee?.employee_number }} -
              {{ payRateEmployee?.first_name }}
              {{ payRateEmployee?.last_name }}
            </div>
            <div class="text-caption mt-2">
              <strong>Position:</strong>
              {{ payRateEmployee?.position || "N/A" }}
            </div>
          </v-alert>

          <v-form ref="payRateForm" v-model="payRateFormValid">
            <!-- Current Rate Info -->
            <v-sheet color="grey-lighten-4" rounded class="pa-4 mb-4">
              <div class="text-subtitle-2 mb-2">
                Current Pay Rate Information
              </div>
              <div class="mb-2">
                <span class="text-caption">Position-Based Rate:</span>
                <span class="text-body-1 font-weight-bold ml-2">
                  {{ formatCurrency(getEmployeePositionRate(payRateEmployee)) }}
                </span>
              </div>
              <div v-if="payRateEmployee?.custom_pay_rate" class="mb-2">
                <span class="text-caption">Current Custom Rate:</span>
                <span class="text-body-1 font-weight-bold ml-2 text-success">
                  {{ formatCurrency(payRateEmployee?.custom_pay_rate) }}
                </span>
                <v-chip size="x-small" color="success" class="ml-2"
                  >Active</v-chip
                >
              </div>
              <div class="mt-2">
                <span class="text-caption">Effective Rate:</span>
                <span class="text-h6 font-weight-bold ml-2 text-primary">
                  {{
                    formatCurrency(getEmployeeEffectiveRate(payRateEmployee))
                  }}/day
                </span>
              </div>
            </v-sheet>

            <!-- New Custom Rate Input -->
            <v-text-field
              v-model.number="payRateData.custom_pay_rate"
              label="New Custom Pay Rate (₱)"
              type="number"
              variant="outlined"
              :rules="[
                (v) =>
                  (v !== null && v !== undefined && v !== '') || 'Required',
                (v) => v >= 0 || 'Rate must be positive',
                (v) => v <= 999999.99 || 'Rate is too large',
              ]"
              prefix="₱"
              hint="This will override the position-based rate"
              persistent-hint
              class="mb-4"
            ></v-text-field>

            <!-- Reason (optional) -->
            <v-textarea
              v-model="payRateData.reason"
              label="Reason for Rate Change (Optional)"
              variant="outlined"
              rows="3"
              counter="500"
              hint="For audit trail purposes"
              persistent-hint
            ></v-textarea>

            <v-alert type="warning" variant="tonal" class="mt-4">
              <div class="text-subtitle-2 mb-1">This change will:</div>
              <ul class="pl-4">
                <li>Override the position-based rate for this employee</li>
                <li>
                  Apply to all future payroll, DTR, and 13th month calculations
                </li>
                <li>Be logged in the audit trail</li>
              </ul>
            </v-alert>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-btn
            v-if="payRateEmployee?.custom_pay_rate"
            variant="outlined"
            class="secondary-action-btn"
            prepend-icon="mdi-restore"
            @click="clearCustomPayRate"
            :loading="clearingPayRate"
          >
            Clear Custom Rate
          </v-btn>
          <v-spacer></v-spacer>
          <v-btn
            variant="outlined"
            class="cancel-btn"
            @click="closePayRateDialog"
          >
            Cancel
          </v-btn>
          <v-btn
            class="primary-action-btn"
            @click="updatePayRate"
            :loading="updatingPayRate"
            :disabled="!payRateFormValid"
          >
            Update Pay Rate
          </v-btn>
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
const {
  positionOptions,
  getRate,
  loadPositionRates,
  refreshRates,
  positionRates,
  getPositionRate,
} = usePositionRates();

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
  last_working_day: "",
  reason: "",
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

// Pay Rate Dialog
const showPayRateDialog = ref(false);
const payRateEmployee = ref(null);
const payRateFormValid = ref(false);
const payRateData = ref({
  custom_pay_rate: null,
  reason: "",
});
const updatingPayRate = ref(false);
const clearingPayRate = ref(false);

const departments = ref([]);
const projects = ref([]);
const employeeForm = ref(null);

const contractTypeOptions = CONTRACT_TYPES;
const activityStatusOptions = ACTIVITY_STATUSES;
const workScheduleOptions = WORK_SCHEDULES;

const headers = [
  { title: "Staff Code", key: "employee_number", sortable: true },
  { title: "Biometric ID", key: "biometric_id", sortable: false },
  { title: "Name", key: "full_name", sortable: true },
  { title: "Gender", key: "gender", sortable: true },
  { title: "Department", key: "project", sortable: true },
  { title: "Position", key: "position", sortable: true },
  { title: "Pay Rate", key: "pay_rate", sortable: false },
  { title: "Date Hired", key: "date_hired", sortable: true },
  { title: "Contract Type", key: "contract_type", sortable: true },
  { title: "Status", key: "activity_status", sortable: true },
  { title: "Actions", key: "actions", sortable: false, align: "center" },
];

onMounted(async () => {
  await fetchEmployees();
  await fetchDepartments();
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
  },
);

// Handle position change - update both position name and position_id
function onPositionChange(positionName) {
  if (!selectedEmployee.value || !positionName) {
    return;
  }

  const positionRate = getPositionRate(positionName);
  if (positionRate) {
    selectedEmployee.value.position_id = positionRate.id;
  }
}

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

async function fetchDepartments() {
  try {
    const response = await api.get("/employees/departments");
    departments.value = response.data.filter(
      (dept) => dept && dept.trim() !== "",
    );
  } catch (error) {
    console.error("Error fetching departments:", error);
    // Fallback: extract departments from current employees if API fails
    const uniqueDepts = [
      ...new Set(
        employeeStore.employees
          .map((emp) => emp.department)
          .filter((dept) => dept && dept.trim() !== ""),
      ),
    ].sort();
    departments.value = uniqueDepts;
  }
}

async function fetchProjects() {
  try {
    const response = await api.get("/projects");
    projects.value = response.data.data || response.data;
  } catch (error) {
    console.error("Error fetching projects:", error);
    toast.error("Failed to load projects");
  }
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
    // Refresh position rates to get latest positions
    await refreshRates();

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
      `Are you sure you want to suspend ${employee.first_name} ${employee.last_name}?`,
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
    last_working_day: "",
    reason: "",
  };
  showResignDialog.value = true;
}

async function submitResignation() {
  if (!resignFormValid.value) return;

  try {
    processing.value = true;
    await api.post("/resignations", {
      employee_id: selectedEmployee.value.id,
      ...resignData.value,
    });
    toast.success("Resignation request submitted successfully!");
    showResignDialog.value = false;
    await fetchEmployees();
  } catch (error) {
    console.error("Error submitting resignation:", error);
    const message =
      error.response?.data?.message || "Failed to submit resignation";
    toast.error(message);
  } finally {
    processing.value = false;
  }
}

async function terminateEmployee(employee) {
  if (
    !confirm(
      `Are you sure you want to terminate ${employee.first_name} ${employee.last_name}?`,
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
      `Are you sure you want to DELETE ${employee.first_name} ${employee.last_name}? This action cannot be undone!`,
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
      selectedEmployee.value,
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

function toggleEditMode() {
  isEditing.value = !isEditing.value;
}

function getWorkScheduleColor(schedule) {
  return schedule === "full_time" ? "primary" : "info";
}

function getContractTypeColor(contractType) {
  const colors = {
    regular: "success",
    probationary: "warning",
    contractual: "info",
    project_based: "secondary",
  };
  return colors[contractType] || "grey";
}

function formatContractType(contractType) {
  if (!contractType) return "N/A";
  return contractType
    .split("_")
    .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
    .join(" ");
}

function getActivityStatusColor(status) {
  const colors = {
    active: "success",
    on_leave: "warning",
    resigned: "grey",
    terminated: "error",
    retired: "grey",
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

// Format date for display
function formatDate(dateString) {
  if (!dateString) return "Not provided";
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "long",
    day: "numeric",
  });
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
      `Generate new temporary password for ${selectedCredentialsEmployee.value.first_name} ${selectedCredentialsEmployee.value.last_name}?`,
    )
  ) {
    return;
  }

  resettingPassword.value = true;
  try {
    const response = await api.post(
      `/employees/${selectedCredentialsEmployee.value.id}/reset-password`,
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
  if (!employeeCredentials.value?.has_account) {
    toast.error("No account found for this employee.");
    return;
  }
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

// Pay Rate Functions
function openPayRateDialog(employee) {
  payRateEmployee.value = employee;
  payRateData.value = {
    custom_pay_rate:
      employee.custom_pay_rate || getEmployeeEffectiveRate(employee),
    reason: "",
  };
  showPayRateDialog.value = true;
}

function closePayRateDialog() {
  showPayRateDialog.value = false;
  payRateEmployee.value = null;
  payRateData.value = {
    custom_pay_rate: null,
    reason: "",
  };
}

async function updatePayRate() {
  if (!payRateFormValid.value) return;

  updatingPayRate.value = true;
  try {
    const response = await api.post(
      `/employees/${payRateEmployee.value.id}/update-pay-rate`,
      {
        custom_pay_rate: payRateData.value.custom_pay_rate,
        reason: payRateData.value.reason,
      },
    );

    toast.success(
      `Pay rate updated successfully from ₱${response.data.old_rate} to ₱${response.data.new_rate}`,
    );

    // Refresh the employee list to show updated rates
    await fetchEmployees();
    closePayRateDialog();
  } catch (error) {
    console.error("Error updating pay rate:", error);
    toast.error(error.response?.data?.message || "Failed to update pay rate");
  } finally {
    updatingPayRate.value = false;
  }
}

async function clearCustomPayRate() {
  if (
    !confirm(
      "Are you sure you want to clear the custom pay rate? This will revert to the position-based rate.",
    )
  ) {
    return;
  }

  clearingPayRate.value = true;
  try {
    const response = await api.post(
      `/employees/${payRateEmployee.value.id}/clear-custom-pay-rate`,
      {
        reason: payRateData.value.reason,
      },
    );

    toast.success(
      `Custom pay rate cleared. Rate changed from ₱${response.data.old_rate} to ₱${response.data.new_rate}`,
    );

    // Refresh the employee list to show updated rates
    await fetchEmployees();
    closePayRateDialog();
  } catch (error) {
    console.error("Error clearing custom pay rate:", error);
    toast.error(
      error.response?.data?.message || "Failed to clear custom pay rate",
    );
  } finally {
    clearingPayRate.value = false;
  }
}

function getEmployeePositionRate(employee) {
  if (!employee) return 0;

  // Get rate from position
  if (employee.position) {
    const rate = getRate(employee.position);
    if (rate) return rate;
  }

  // Fallback to basic_salary field
  return employee.basic_salary || 0;
}

function getEmployeeEffectiveRate(employee) {
  if (!employee) return 0;

  // Priority 1: Custom pay rate
  if (employee.custom_pay_rate) {
    return employee.custom_pay_rate;
  }

  // Priority 2: Position-based rate
  return getEmployeePositionRate(employee);
}

function formatCurrency(value) {
  if (!value && value !== 0) return "₱0.00";
  return (
    "₱" +
    Number(value).toLocaleString("en-US", {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2,
    })
  );
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

<style scoped lang="scss">
.employees-page {
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
  gap: 24px;

  @media (max-width: 960px) {
    flex-direction: column;
    align-items: flex-start;
  }
}

.page-title-section {
  display: flex;
  align-items: center;
  gap: 16px;
  flex: 1;
}

.page-icon-badge {
  width: 56px;
  height: 56px;
  border-radius: 14px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);
  flex-shrink: 0;

  .v-icon {
    color: #ffffff !important;
  }
}

.page-title {
  font-size: 28px;
  font-weight: 700;
  color: #001f3d;
  margin: 0 0 4px 0;
  letter-spacing: -0.5px;
}

.page-subtitle {
  font-size: 14px;
  color: rgba(0, 31, 61, 0.6);
  margin: 0;
}

.action-buttons {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;

  @media (max-width: 960px) {
    width: 100%;
  }
}

.action-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 20px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
  white-space: nowrap;

  .v-icon {
    flex-shrink: 0;
  }

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(237, 152, 95, 0.25);
  }

  &.action-btn-primary {
    background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);

    .v-icon {
      color: #ffffff !important;
    }
  }

  &.action-btn-secondary {
    background: rgba(237, 152, 95, 0.1);
    color: #ed985f;
    border: 1px solid rgba(237, 152, 95, 0.2);

    .v-icon {
      color: #ed985f !important;
    }

    &:hover {
      background: rgba(237, 152, 95, 0.15);
      border-color: rgba(237, 152, 95, 0.3);
    }
  }
}

.modern-card {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  overflow: hidden;
  margin-bottom: 24px;
  padding: 24px;
}

.filters-section {
  background: rgba(0, 31, 61, 0.01);
  margin-bottom: 24px;
}

.table-section {
  background: #ffffff;
}

.v-data-table {
  :deep(.v-data-table__wrapper) {
    border-radius: 0;
  }

  :deep(.v-data-table-header) {
    background-color: rgba(0, 31, 61, 0.02);
  }

  :deep(.v-data-table__th) {
    font-weight: 600;
    color: #001f3d;
    font-size: 13px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }

  :deep(tbody tr) {
    transition: background-color 0.2s ease;
  }

  :deep(tbody tr:hover) {
    background-color: rgba(237, 152, 95, 0.04) !important;
  }

  :deep(.v-data-table__td) {
    border-bottom: 1px solid rgba(0, 31, 61, 0.06);
  }
}

/* Dialog Styles */
.dialog-header {
  padding: 24px;
  display: flex;
  align-items: center;
  gap: 16px;
  background: linear-gradient(135deg, #001f3d 0%, #003d5c 100%);
}

.header-icon-badge {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);
  flex-shrink: 0;
}

.header-title {
  font-size: 20px;
  font-weight: 700;
  color: white;
  margin: 0;
  line-height: 1.2;
}

.header-subtitle {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.7);
  margin: 4px 0 0 0;
  line-height: 1.3;
}

.primary-action-btn {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: white;
  border-radius: 10px;
  padding: 0 24px;
  font-weight: 600;
  text-transform: none;
  letter-spacing: 0.3px;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);
  transition: all 0.2s ease;
}

.primary-action-btn:hover {
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.4);
  transform: translateY(-1px);
}

.cancel-btn,
.secondary-action-btn {
  border-radius: 10px;
  padding: 0 24px;
  font-weight: 600;
  text-transform: none;
  letter-spacing: 0.3px;
  transition: all 0.2s ease;
  border: 1.5px solid rgba(0, 31, 61, 0.15);
  color: #001f3d;
}

.cancel-btn:hover,
.secondary-action-btn:hover {
  background: rgba(0, 31, 61, 0.04);
  border-color: rgba(0, 31, 61, 0.25);
}

/* Employee Details View Styles */
.detail-row {
  margin-bottom: 16px;
}

.detail-label {
  display: flex;
  align-items: center;
  font-size: 12px;
  font-weight: 600;
  color: rgba(0, 31, 61, 0.6);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 6px;
}

.detail-value {
  font-size: 15px;
  font-weight: 500;
  color: #001f3d;
  padding: 8px 0;
  min-height: 32px;
  display: flex;
  align-items: center;
}
</style>
