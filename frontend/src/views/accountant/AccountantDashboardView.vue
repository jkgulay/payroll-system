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
              <v-icon class="mr-2">mdi-clipboard-text</v-icon>
              My Submitted Applications
            </span>
            <v-btn
              icon
              @click="fetchMyApplications"
              :loading="loadingApplications"
              title="Refresh"
            >
              <v-icon>mdi-refresh</v-icon>
            </v-btn>
          </v-card-title>
          <v-card-text>
            <v-data-table
              :headers="applicationHeaders"
              :items="myApplications"
              :loading="loadingApplications"
            >
              <template v-slot:item.full_name="{ item }">
                {{ item.first_name }} {{ item.last_name }}
              </template>
              <template v-slot:item.application_status="{ item }">
                <v-chip
                  :color="getApplicationStatusColor(item.application_status)"
                  size="small"
                >
                  {{ item.application_status.toUpperCase() }}
                </v-chip>
              </template>
              <template v-slot:item.submitted_at="{ item }">
                {{ new Date(item.submitted_at).toLocaleDateString() }}
              </template>
              <template v-slot:item.actions="{ item }">
                <v-btn
                  icon="mdi-eye"
                  size="small"
                  variant="text"
                  @click="viewApplicationDetails(item)"
                ></v-btn>
              </template>
            </v-data-table>
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

    <!-- Add Employee Application Dialog -->
    <v-dialog v-model="showAddEmployeeDialog" max-width="900px" persistent scrollable>
      <v-card>
        <v-card-title class="text-h5 py-4 bg-primary">
          Submit Employee Application
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-6" style="max-height: 600px;">
          <v-form ref="employeeForm">
            <v-row>
              <!-- Personal Information -->
              <v-col cols="12">
                <div class="text-h6 mb-2">Section 1: Personal Information</div>
                <v-divider class="mb-4"></v-divider>
              </v-col>

              <v-col cols="12" md="4">
                <v-text-field
                  v-model="employeeData.first_name"
                  label="First Name"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="4">
                <v-text-field
                  v-model="employeeData.middle_name"
                  label="Middle Name (Optional)"
                  variant="outlined"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="4">
                <v-text-field
                  v-model="employeeData.last_name"
                  label="Last Name"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="employeeData.email"
                  label="Email"
                  type="email"
                  :rules="[rules.required, rules.email]"
                  variant="outlined"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="employeeData.mobile_number"
                  label="Phone Number"
                  variant="outlined"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="employeeData.date_of_birth"
                  label="Date of Birth"
                  type="date"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="employeeData.gender"
                  :items="[
                    { title: 'Male', value: 'male' },
                    { title: 'Female', value: 'female' },
                    { title: 'Other', value: 'other' }
                  ]"
                  label="Gender"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                ></v-select>
              </v-col>

              <v-col cols="12">
                <v-textarea
                  v-model="employeeData.worker_address"
                  label="Worker Address"
                  rows="2"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                ></v-textarea>
              </v-col>

              <!-- Employment Information -->
              <v-col cols="12">
                <div class="text-h6 mb-2 mt-4">Section 2: Employment Information</div>
                <v-divider class="mb-4"></v-divider>
              </v-col>

              <v-col cols="12">
                <v-alert type="info" variant="tonal" density="compact">
                  Employee Number will be auto-generated after admin approval
                </v-alert>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="employeeData.project_id"
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
                  v-model="employeeData.position"
                  label="Position"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="employeeData.employment_status"
                  :items="[
                    { title: 'Regular', value: 'regular' },
                    { title: 'Probationary', value: 'probationary' },
                    { title: 'Contractual', value: 'contractual' },
                    { title: 'Active', value: 'active' }
                  ]"
                  label="Employment Status"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                ></v-select>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="employeeData.employment_type"
                  :items="[
                    { title: 'Regular', value: 'regular' },
                    { title: 'Contractual', value: 'contractual' },
                    { title: 'Part Time', value: 'part_time' }
                  ]"
                  label="Employment Type"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                ></v-select>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="employeeData.salary_type"
                  :items="[
                    { title: 'Daily', value: 'daily' },
                    { title: 'Monthly', value: 'monthly' }
                  ]"
                  label="Salary Type"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                ></v-select>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model.number="employeeData.basic_salary"
                  label="Basic Pay Rate"
                  type="number"
                  prefix="₱"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                  hint="Default: ₱450"
                  persistent-hint
                ></v-text-field>
              </v-col>

              <!-- Allowances -->
              <v-col cols="12">
                <div class="text-h6 mb-2 mt-4">Section 3: Allowances</div>
                <v-divider class="mb-4"></v-divider>
              </v-col>

              <v-col cols="12" md="6">
                <v-checkbox
                  v-model="employeeData.has_water_allowance"
                  label="Water Allowance"
                  hide-details
                ></v-checkbox>
                <v-text-field
                  v-if="employeeData.has_water_allowance"
                  v-model.number="employeeData.water_allowance"
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
                  v-model="employeeData.has_cola"
                  label="COLA"
                  hide-details
                ></v-checkbox>
                <v-text-field
                  v-if="employeeData.has_cola"
                  v-model.number="employeeData.cola"
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
                  v-model="employeeData.has_incentives"
                  label="Incentives"
                  hide-details
                ></v-checkbox>
                <v-text-field
                  v-if="employeeData.has_incentives"
                  v-model.number="employeeData.incentives"
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
                  v-model="employeeData.has_ppe"
                  label="PPE"
                  hide-details
                ></v-checkbox>
                <v-text-field
                  v-if="employeeData.has_ppe"
                  v-model.number="employeeData.ppe"
                  label="Amount"
                  type="number"
                  prefix="₱"
                  variant="outlined"
                  density="compact"
                  class="mt-2"
                ></v-text-field>
              </v-col>

              <!-- Document Upload -->
              <v-col cols="12">
                <div class="text-h6 mb-2 mt-4">Section 4: Document Upload</div>
                <v-divider class="mb-4"></v-divider>
              </v-col>

              <v-col cols="12" md="6">
                <v-file-input
                  v-model="documents.resume"
                  label="Resume (Required)"
                  accept=".pdf,.jpg,.jpeg,.png"
                  variant="outlined"
                  density="comfortable"
                  hint="PDF or Image (Max 5MB)"
                  persistent-hint
                  show-size
                  :rules="[rules.required]"
                ></v-file-input>
              </v-col>

              <v-col cols="12" md="6">
                <v-file-input
                  v-model="documents.id_document"
                  label="ID (Optional)"
                  accept=".pdf,.jpg,.jpeg,.png"
                  variant="outlined"
                  density="comfortable"
                  hint="PDF or Image (Max 5MB)"
                  persistent-hint
                  show-size
                ></v-file-input>
              </v-col>

              <v-col cols="12" md="6">
                <v-file-input
                  v-model="documents.contract"
                  label="Contract (Optional)"
                  accept=".pdf,.jpg,.jpeg,.png"
                  variant="outlined"
                  density="comfortable"
                  hint="PDF or Image (Max 5MB)"
                  persistent-hint
                  show-size
                ></v-file-input>
              </v-col>

              <v-col cols="12" md="6">
                <v-file-input
                  v-model="documents.certificates"
                  label="Certificates (Optional)"
                  accept=".pdf,.jpg,.jpeg,.png"
                  variant="outlined"
                  density="comfortable"
                  hint="PDF or Image (Max 5MB)"
                  persistent-hint
                  show-size
                ></v-file-input>
              </v-col>

              <!-- System Status -->
              <v-col cols="12">
                <div class="text-h6 mb-2 mt-4">Section 5: Application Status</div>
                <v-divider class="mb-4"></v-divider>
              </v-col>

              <v-col cols="12">
                <v-alert type="warning" variant="tonal" density="compact" class="mb-2">
                  <div class="text-subtitle-2 mb-2">Important Notes:</div>
                  <ul class="text-caption">
                    <li>Application will be submitted to Admin for review</li>
                    <li>Employee account will only be created after Admin approval</li>
                    <li>You will be notified once the application is processed</li>
                  </ul>
                </v-alert>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="closeEmployeeDialog" :disabled="saving">
            Cancel
          </v-btn>
          <v-btn
            color="primary"
            variant="elevated"
            @click="submitEmployeeApplication"
            :loading="saving"
          >
            Submit Application
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

    <!-- View Employee Dialog -->
    <v-dialog v-model="showViewEmployeeDialog" max-width="1000px" scrollable>
      <v-card>
        <v-card-title class="text-h5 py-4 bg-info">
          <v-icon start>mdi-eye</v-icon>
          Employee Details
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-6" style="max-height: 600px;">
          <v-form v-if="selectedEmployee">
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
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="3">
                <v-text-field
                  v-model="selectedEmployee.first_name"
                  label="First Name"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="3">
                <v-text-field
                  v-model="selectedEmployee.middle_name"
                  label="Middle Name"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="3">
                <v-text-field
                  v-model="selectedEmployee.last_name"
                  label="Last Name"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="4">
                <v-text-field
                  v-model="selectedEmployee.email"
                  label="Email"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="4">
                <v-text-field
                  v-model="selectedEmployee.mobile_number"
                  label="Mobile Number"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="4">
                <v-text-field
                  v-model="selectedEmployee.date_of_birth"
                  label="Date of Birth"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="4">
                <v-text-field
                  v-model="selectedEmployee.gender"
                  label="Gender"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12">
                <v-textarea
                  v-model="selectedEmployee.worker_address"
                  label="Address"
                  rows="2"
                  readonly
                  variant="plain"
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
                  :model-value="selectedEmployee.project?.name || 'N/A'"
                  label="Project"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="selectedEmployee.position"
                  label="Position"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="selectedEmployee.date_hired"
                  label="Date Hired"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="selectedEmployee.employment_status"
                  label="Employment Status"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="selectedEmployee.employment_type"
                  label="Employment Type"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="selectedEmployee.salary_type"
                  label="Salary Type"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  :model-value="`₱${selectedEmployee.basic_salary}`"
                  label="Basic Salary"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  :model-value="selectedEmployee.is_active ? 'Active' : 'Inactive'"
                  label="Status"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <!-- Allowances -->
              <v-col cols="12">
                <div class="text-h6 mb-2 mt-4">Allowances</div>
                <v-divider class="mb-4"></v-divider>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  :model-value="selectedEmployee.has_water_allowance ? `₱${selectedEmployee.water_allowance}` : 'N/A'"
                  label="Water Allowance"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  :model-value="selectedEmployee.has_cola ? `₱${selectedEmployee.cola}` : 'N/A'"
                  label="COLA"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  :model-value="selectedEmployee.has_incentives ? `₱${selectedEmployee.incentives}` : 'N/A'"
                  label="Incentives"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  :model-value="selectedEmployee.has_ppe ? `₱${selectedEmployee.ppe}` : 'N/A'"
                  label="PPE"
                  readonly
                  variant="plain"
                  density="comfortable"
                ></v-text-field>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn
            variant="text"
            @click="closeViewEmployeeDialog"
          >
            Close
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Application Details Dialog -->
    <v-dialog v-model="showApplicationDetailsDialog" max-width="800px" scrollable>
      <v-card>
        <v-card-title class="text-h5 py-4" :class="getApplicationStatusClass(selectedApplication?.application_status)">
          <v-icon start>mdi-clipboard-text</v-icon>
          Application Details
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-6" v-if="selectedApplication">
          <!-- Status Alert -->
          <v-alert
            :type="getApplicationAlertType(selectedApplication.application_status)"
            variant="tonal"
            class="mb-4"
          >
            <div class="text-h6">Status: {{ selectedApplication.application_status.toUpperCase() }}</div>
            <div v-if="selectedApplication.application_status === 'pending'">
              Your application is currently under review by the admin.
            </div>
            <div v-if="selectedApplication.application_status === 'rejected'">
              <strong>Reason:</strong> {{ selectedApplication.rejection_reason }}
            </div>
            <div v-if="selectedApplication.application_status === 'approved'">
              Your application has been approved! Your employee account has been created.
            </div>
          </v-alert>

          <!-- Credentials Section (Only for Approved Applications) -->
          <v-card v-if="selectedApplication.application_status === 'approved' && selectedApplication.employee" class="mb-4 bg-success-lighten">
            <v-card-title class="bg-success text-white">
              <v-icon start>mdi-key</v-icon>
              Your Login Credentials
            </v-card-title>
            <v-card-text class="pt-4">
              <v-alert type="info" variant="tonal" class="mb-4">
                <strong>Important:</strong> Please save these credentials securely. You will need them to log into the system.
              </v-alert>
              
              <v-row>
                <v-col cols="12" md="6">
                  <v-text-field
                    :model-value="selectedApplication.employee.employee_number"
                    label="Employee Number"
                    readonly
                    variant="outlined"
                    density="comfortable"
                    prepend-icon="mdi-badge-account"
                  ></v-text-field>
                </v-col>
                <v-col cols="12" md="6">
                  <v-text-field
                    :model-value="selectedApplication.email"
                    label="Username"
                    readonly
                    variant="outlined"
                    density="comfortable"
                    prepend-icon="mdi-account"
                  ></v-text-field>
                </v-col>
                <v-col cols="12">
                  <v-alert type="warning" variant="tonal">
                    <div class="mb-2">
                      <strong>Note:</strong> For security reasons, your temporary password is not displayed here. 
                      Please contact the admin who approved your application to receive your temporary password.
                    </div>
                    <div>
                      You will be required to change your password on first login.
                    </div>
                  </v-alert>
                </v-col>
              </v-row>
            </v-card-text>
          </v-card>

          <!-- Application Information -->
          <v-row>
            <v-col cols="12">
              <div class="text-h6 mb-2">Personal Information</div>
              <v-divider class="mb-4"></v-divider>
            </v-col>

            <v-col cols="12" md="4">
              <v-text-field
                :model-value="selectedApplication.first_name"
                label="First Name"
                readonly
                variant="plain"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="4">
              <v-text-field
                :model-value="selectedApplication.middle_name || 'N/A'"
                label="Middle Name"
                readonly
                variant="plain"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="4">
              <v-text-field
                :model-value="selectedApplication.last_name"
                label="Last Name"
                readonly
                variant="plain"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                :model-value="selectedApplication.email"
                label="Email"
                readonly
                variant="plain"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                :model-value="selectedApplication.mobile_number"
                label="Mobile Number"
                readonly
                variant="plain"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                :model-value="selectedApplication.position"
                label="Position Applied"
                readonly
                variant="plain"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                :model-value="new Date(selectedApplication.submitted_at).toLocaleString()"
                label="Submitted At"
                readonly
                variant="plain"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6" v-if="selectedApplication.reviewed_at">
              <v-text-field
                :model-value="new Date(selectedApplication.reviewed_at).toLocaleString()"
                label="Reviewed At"
                readonly
                variant="plain"
                density="comfortable"
              ></v-text-field>
            </v-col>
          </v-row>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="showApplicationDetailsDialog = false">
            Close
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
const loadingApplications = ref(false);
const saving = ref(false);
const downloading = ref(false);
const search = ref("");
const employees = ref([]);
const myApplications = ref([]);
const projects = ref([]);
const payrolls = ref([]);
const showAddEmployeeDialog = ref(false);
const showViewEmployeeDialog = ref(false);
const showApplicationDetailsDialog = ref(false);
const showPayslipModifyDialog = ref(false);
const showDownloadDialog = ref(false);
const editingEmployee = ref(null);
const selectedEmployee = ref(null);
const selectedApplication = ref(null);

const stats = ref({
  totalEmployees: 0,
  activeEmployees: 0,
  pendingRequests: 0,
  periodPayroll: 0,
  presentToday: 0,
});

const employeeData = ref({
  first_name: "",
  middle_name: "",
  last_name: "",
  date_of_birth: "",
  gender: "",
  email: "",
  mobile_number: "",
  position: "",
  project_id: null,
  worker_address: "",
  employment_status: "regular",
  employment_type: "regular",
  basic_salary: 450,
  salary_type: "daily",
  has_water_allowance: false,
  water_allowance: 0,
  has_cola: false,
  cola: 0,
  has_incentives: false,
  incentives: 0,
  has_ppe: false,
  ppe: 0,
});

const documents = ref({
  resume: null,
  id_document: null,
  contract: null,
  certificates: null,
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
  { title: "Project", key: "project.name" },
  { title: "Status", key: "employment_status" },
  { title: "Actions", key: "actions", sortable: false },
];

const applicationHeaders = [
  { title: "Name", key: "full_name" },
  { title: "Email", key: "email" },
  { title: "Position", key: "position" },
  { title: "Status", key: "application_status" },
  { title: "Submitted", key: "submitted_at" },
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
  fetchMyApplications();
  fetchProjects();
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

async function fetchMyApplications() {
  loadingApplications.value = true;
  try {
    const response = await api.get("/employee-applications");
    const data = response.data.data || response.data;
    myApplications.value = Array.isArray(data) ? data : (data.data || []);
  } catch (error) {
    console.error("Error fetching applications:", error);
    toast.error("Failed to load applications");
  } finally {
    loadingApplications.value = false;
  }
}

function viewApplicationDetails(application) {
  selectedApplication.value = application;
  showApplicationDetailsDialog.value = true;
}

function getApplicationStatusColor(status) {
  const colors = {
    pending: 'warning',
    approved: 'success',
    rejected: 'error',
  };
  return colors[status] || 'grey';
}

function getApplicationStatusClass(status) {
  const classes = {
    pending: 'bg-warning',
    approved: 'bg-success',
    rejected: 'bg-error',
  };
  return classes[status] || 'bg-grey';
}

function getApplicationAlertType(status) {
  const types = {
    pending: 'warning',
    approved: 'success',
    rejected: 'error',
  };
  return types[status] || 'info';
}

async function fetchProjects() {
  try {
    const response = await api.get("/projects");
    projects.value = response.data.data || response.data;
  } catch (error) {
    console.error("Error fetching projects:", error);
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

async function submitEmployeeApplication() {
  const { valid } = await employeeForm.value.validate();
  if (!valid) {
    toast.warning("Please fill in all required fields");
    return;
  }

  if (!documents.value.resume) {
    toast.warning("Resume is required");
    return;
  }

  saving.value = true;
  try {
    // Create FormData for file uploads
    const formData = new FormData();
    
    // Add all employee data fields with proper boolean handling
    Object.keys(employeeData.value).forEach(key => {
      const value = employeeData.value[key];
      if (value !== null && value !== '') {
        // Convert booleans to 1 or 0 for Laravel
        if (typeof value === 'boolean') {
          formData.append(key, value ? '1' : '0');
        } else {
          formData.append(key, value);
        }
      }
    });

    // Add document files
    if (documents.value.resume) {
      formData.append('resume', documents.value.resume[0] || documents.value.resume);
    }
    if (documents.value.id_document) {
      formData.append('id_document', documents.value.id_document[0] || documents.value.id_document);
    }
    if (documents.value.contract) {
      formData.append('contract', documents.value.contract[0] || documents.value.contract);
    }
    if (documents.value.certificates) {
      formData.append('certificates', documents.value.certificates[0] || documents.value.certificates);
    }

    await api.post("/employee-applications", formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    
    toast.success("Employee application submitted successfully! Waiting for admin approval.");
    closeEmployeeDialog();
    fetchDashboardData();
  } catch (error) {
    console.error("Error submitting employee application:", error);
    toast.error(error.response?.data?.message || "Failed to submit application");
  } finally {
    saving.value = false;
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
      await submitEmployeeApplication();
      return;
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

async function viewEmployee(employee) {
  try {
    const response = await api.get(`/employees/${employee.id}`);
    selectedEmployee.value = { ...response.data };
    showViewEmployeeDialog.value = true;
  } catch (error) {
    console.error("Error fetching employee details:", error);
    toast.error("Failed to load employee details");
  }
}

function closeViewEmployeeDialog() {
  showViewEmployeeDialog.value = false;
  selectedEmployee.value = null;
}

function closeEmployeeDialog() {
  showAddEmployeeDialog.value = false;
  editingEmployee.value = null;
  employeeData.value = {
    first_name: "",
    middle_name: "",
    last_name: "",
    date_of_birth: "",
    gender: "",
    email: "",
    mobile_number: "",
    position: "",
    project_id: null,
    worker_address: "",
    employment_status: "regular",
    employment_type: "regular",
    basic_salary: 450,
    salary_type: "daily",
    has_water_allowance: false,
    water_allowance: 0,
    has_cola: false,
    cola: 0,
    has_incentives: false,
    incentives: 0,
    has_ppe: false,
    ppe: 0,
  };
  documents.value = {
    resume: null,
    id_document: null,
    contract: null,
    certificates: null,
  };
  employeeForm.value?.reset();
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
