<template>
  <div>
    <!-- Page Header with Construction Theme -->
    <v-row class="mb-6">
      <v-col cols="12">
        <div class="d-flex align-center justify-space-between">
          <div>
            <h1 class="construction-header text-h3 mb-2">Dashboard</h1>
            <p class="text-subtitle-1 text-medium-emphasis">
              <v-icon size="small" class="mr-1">mdi-calendar-today</v-icon>
              {{ currentDate }}
            </p>
          </div>
          <v-btn
            color="primary"
            size="large"
            prepend-icon="mdi-refresh"
            variant="elevated"
            @click="refreshData"
            :loading="refreshing"
            class="construction-btn"
          >
            Refresh Data
          </v-btn>
        </div>
        <div class="steel-divider mt-4"></div>
      </v-col>
    </v-row>

    <!-- Statistics Cards with Construction Theme -->
    <v-row>
      <v-col cols="12" sm="6" md="3">
        <v-card class="industrial-card stat-card stat-card-primary">
          <v-card-text class="pa-5">
            <div class="d-flex align-center justify-space-between">
              <div>
                <div
                  class="text-overline text-medium-emphasis font-weight-bold"
                >
                  Total Workers
                </div>
                <div class="text-h3 font-weight-bold mt-2 mb-2">
                  {{ stats.totalEmployees }}
                </div>
                <div class="text-caption">
                  <v-chip size="x-small" color="success" variant="flat">
                    <v-icon start size="x-small">mdi-arrow-up</v-icon>
                    {{ stats.activeEmployees }} active
                  </v-chip>
                </div>
              </div>
              <v-avatar color="primary" size="72" class="stat-avatar">
                <v-icon size="40">mdi-hard-hat</v-icon>
              </v-avatar>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card class="industrial-card stat-card stat-card-success">
          <v-card-text class="pa-5">
            <div class="d-flex align-center justify-space-between">
              <div>
                <div
                  class="text-overline text-medium-emphasis font-weight-bold"
                >
                  This Period
                </div>
                <div class="text-h3 font-weight-bold mt-2 mb-2">
                  ₱{{ formatNumber(stats.periodPayroll) }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  Total Payroll
                </div>
              </div>
              <v-avatar color="success" size="72" class="stat-avatar">
                <v-icon size="40">mdi-currency-php</v-icon>
              </v-avatar>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card class="industrial-card stat-card stat-card-info">
          <v-card-text class="pa-5">
            <div class="d-flex align-center justify-space-between">
              <div>
                <div
                  class="text-overline text-medium-emphasis font-weight-bold"
                >
                  Attendance Today
                </div>
                <div class="text-h3 font-weight-bold mt-2 mb-2">
                  {{ stats.presentToday }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  of {{ stats.totalEmployees }} workers
                </div>
              </div>
              <v-avatar color="info" size="72" class="stat-avatar">
                <v-icon size="40">mdi-clock-check-outline</v-icon>
              </v-avatar>
            </div>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" sm="6" md="3">
        <v-card class="industrial-card stat-card stat-card-warning">
          <v-card-text class="pa-5">
            <div class="d-flex align-center justify-space-between">
              <div>
                <div
                  class="text-overline text-medium-emphasis font-weight-bold"
                >
                  Pending Tasks
                </div>
                <div class="text-h3 font-weight-bold mt-2 mb-2">
                  {{ stats.pendingApprovals }}
                </div>
                <div class="text-caption">
                  <v-chip size="x-small" color="warning" variant="flat">
                    <v-icon start size="x-small">mdi-alert</v-icon>
                    Action Required
                  </v-chip>
                </div>
              </div>
              <v-avatar color="warning" size="72" class="stat-avatar">
                <v-icon size="40">mdi-clipboard-alert-outline</v-icon>
              </v-avatar>
            </div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Pending Employee Applications -->
    <v-row class="mt-6" v-if="pendingApplications.length > 0">
      <v-col cols="12">
        <v-card class="industrial-card">
          <v-card-title class="construction-header pa-5 bg-warning">
            <v-icon start size="24">mdi-account-clock</v-icon>
            Pending Employee Applications
            <v-chip size="small" color="error" class="ml-2">
              {{ pendingApplications.length }}
            </v-chip>
          </v-card-title>
          <v-divider class="steel-divider"></v-divider>
          <v-card-text>
            <v-data-table
              :headers="applicationHeaders"
              :items="pendingApplications"
              :items-per-page="5"
              density="comfortable"
            >
              <template v-slot:item.full_name="{ item }">
                <strong
                  >{{ item.first_name }} {{ item.middle_name }}
                  {{ item.last_name }}</strong
                >
              </template>

              <template v-slot:item.project="{ item }">
                {{ item.project?.name || "N/A" }}
              </template>

              <template v-slot:item.submitted_at="{ item }">
                {{ formatDate(item.submitted_at) }}
              </template>

              <template v-slot:item.created_by="{ item }">
                {{ item.creator?.username || "Unknown" }}
              </template>

              <template v-slot:item.application_status="{ item }">
                <v-chip
                  size="small"
                  :color="getApplicationStatusColor(item.application_status)"
                >
                  {{ item.application_status }}
                </v-chip>
              </template>

              <template v-slot:item.actions="{ item }">
                <v-btn
                  icon="mdi-eye"
                  size="small"
                  variant="text"
                  color="info"
                  @click="viewApplication(item)"
                  title="View Details"
                ></v-btn>
              </template>
            </v-data-table>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Quick Actions with Construction Theme -->
    <v-row class="mt-6">
      <v-col cols="12">
        <v-card class="industrial-card quick-actions-card">
          <v-card-title class="construction-header pa-5">
            <v-icon start size="24">mdi-toolbox-outline</v-icon>
            Quick Actions
          </v-card-title>
          <v-divider class="steel-divider"></v-divider>
          <v-card-text class="pa-5">
            <v-row>
              <v-col cols="6" sm="4" md="3">
                <v-btn
                  color="primary"
                  variant="elevated"
                  block
                  size="x-large"
                  class="construction-btn action-btn"
                  @click="showAddEmployeeDialog = true"
                >
                  <v-icon size="28" class="mb-2"
                    >mdi-account-plus-outline</v-icon
                  >
                  <div class="text-caption">Add Worker</div>
                </v-btn>
              </v-col>
              <v-col cols="6" sm="4" md="3">
                <v-btn
                  color="info"
                  variant="elevated"
                  block
                  size="x-large"
                  class="construction-btn action-btn"
                  to="/attendance"
                >
                  <v-icon size="28" class="mb-2"
                    >mdi-clock-check-outline</v-icon
                  >
                  <div class="text-caption">Attendance</div>
                </v-btn>
              </v-col>
              <v-col cols="6" sm="4" md="3">
                <v-btn
                  color="success"
                  variant="elevated"
                  block
                  size="x-large"
                  class="construction-btn action-btn"
                  to="/payroll/create"
                >
                  <v-icon size="28" class="mb-2">mdi-cash-plus</v-icon>
                  <div class="text-caption">New Payroll</div>
                </v-btn>
              </v-col>
              <v-col cols="6" sm="4" md="3">
                <v-btn
                  color="accent"
                  variant="elevated"
                  block
                  size="x-large"
                  class="construction-btn action-btn"
                  to="/reports"
                >
                  <v-icon size="28" class="mb-2">mdi-file-chart-outline</v-icon>
                  <div class="text-caption">Reports</div>
                </v-btn>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Recent Activity with Construction Theme -->
    <v-row class="mt-6">
      <v-col cols="12" md="6">
        <v-card class="industrial-card" height="450">
          <v-card-title class="construction-header pa-5">
            <v-icon start size="24">mdi-clock-check-outline</v-icon>
            Recent Attendance
          </v-card-title>
          <v-divider class="steel-divider"></v-divider>
          <v-card-text>
            <v-list density="compact">
              <v-list-item
                v-for="attendance in recentAttendance"
                :key="attendance.id"
                :subtitle="`${attendance.time_in || 'N/A'} - ${
                  attendance.time_out || 'Ongoing'
                }`"
              >
                <template v-slot:prepend>
                  <v-avatar :color="getAttendanceColor(attendance.status)">
                    <v-icon>{{ getAttendanceIcon(attendance.status) }}</v-icon>
                  </v-avatar>
                </template>
                <v-list-item-title>{{
                  attendance.employee_name
                }}</v-list-item-title>
              </v-list-item>
            </v-list>
          </v-card-text>
        </v-card>
      </v-col>

      <v-col cols="12" md="6">
        <v-card class="industrial-card" height="450">
          <v-card-title class="construction-header pa-5">
            <v-icon start size="24">mdi-cash-clock</v-icon>
            Recent Payrolls
          </v-card-title>
          <v-divider class="steel-divider"></v-divider>
          <v-card-text>
            <v-list density="compact">
              <v-list-item
                v-for="payroll in recentPayrolls"
                :key="payroll.id"
                :subtitle="`${payroll.period_start_date} to ${payroll.period_end_date}`"
                :to="`/payroll/${payroll.id}`"
              >
                <template v-slot:prepend>
                  <v-avatar :color="getPayrollColor(payroll.status)">
                    <v-icon>mdi-cash</v-icon>
                  </v-avatar>
                </template>
                <v-list-item-title>{{
                  payroll.payroll_number
                }}</v-list-item-title>
                <template v-slot:append>
                  <v-chip :color="getPayrollColor(payroll.status)" size="small">
                    {{ payroll.status }}
                  </v-chip>
                </template>
              </v-list-item>
            </v-list>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Add Employee Dialog (Reusable Component) -->
    <AddEmployeeDialog
      v-model="showAddEmployeeDialog"
      :projects="projects"
      @save="saveEmployee"
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
              Employee {{ newEmployeeData?.employee_number }} -
              {{ newEmployeeData?.first_name }} {{ newEmployeeData?.last_name }}
            </div>
            <p>A login account has been created successfully!</p>
          </v-alert>

          <div class="mb-4">
            <div class="text-subtitle-1 font-weight-bold mb-2">
              Login Credentials:
            </div>
            <v-sheet color="grey-lighten-4" rounded class="pa-4">
              <div class="mb-3">
                <div class="text-caption text-grey-darken-2">Username</div>
                <div class="text-body-1 font-weight-bold">
                  {{
                    createdEmployeeUsername || newEmployeeData?.email || "N/A"
                  }}
                </div>
              </div>
              <div class="mb-3" v-if="newEmployeeData?.email">
                <div class="text-caption text-grey-darken-2">Email</div>
                <div class="text-body-1 font-weight-bold">
                  {{ newEmployeeData?.email }}
                </div>
              </div>
              <div class="mb-3">
                <div class="text-caption text-grey-darken-2">
                  Temporary Password
                </div>
                <div class="text-h6 font-weight-bold text-primary">
                  {{ temporaryPassword }}
                </div>
              </div>
              <div>
                <div class="text-caption text-grey-darken-2">Role</div>
                <div class="text-body-1 font-weight-bold text-capitalize">
                  {{ newEmployeeData?.role || employeeData.role }}
                </div>
              </div>
            </v-sheet>
          </div>

          <v-alert type="warning" variant="tonal" density="compact">
            <v-icon start>mdi-alert</v-icon>
            <strong>Important:</strong> The employee must change this password
            on their first login.
          </v-alert>

          <v-alert type="info" variant="tonal" density="compact" class="mt-2">
            <v-icon start>mdi-information</v-icon>
            Please save or share these credentials securely with the employee.
          </v-alert>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-btn
            variant="text"
            prepend-icon="mdi-content-copy"
            @click="copyCredentials"
          >
            Copy Credentials
          </v-btn>
          <v-spacer></v-spacer>
          <v-btn
            color="primary"
            variant="elevated"
            @click="showPasswordDialog = false"
          >
            Done
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Application Review Dialog -->
    <v-dialog
      v-model="showApplicationDialog"
      max-width="1000px"
      persistent
      scrollable
    >
      <v-card v-if="selectedApplication">
        <v-card-title class="text-h5 py-4 bg-warning">
          <v-icon start>mdi-account-check</v-icon>
          Review Employee Application
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-6" style="max-height: 600px">
          <v-row>
            <!-- Personal Information -->
            <v-col cols="12">
              <div class="text-h6 mb-2">Section 1: Personal Information</div>
              <v-divider class="mb-4"></v-divider>
            </v-col>

            <v-col cols="12" md="4">
              <v-text-field
                :model-value="selectedApplication.first_name"
                label="First Name"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="4">
              <v-text-field
                :model-value="selectedApplication.middle_name"
                label="Middle Name"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="4">
              <v-text-field
                :model-value="selectedApplication.last_name"
                label="Last Name"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                :model-value="selectedApplication.email"
                label="Email"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                :model-value="selectedApplication.mobile_number"
                label="Phone Number"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                :model-value="selectedApplication.date_of_birth"
                label="Date of Birth"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                :model-value="selectedApplication.gender"
                label="Gender"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12">
              <v-textarea
                :model-value="selectedApplication.worker_address"
                label="Worker Address"
                rows="2"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-textarea>
            </v-col>

            <!-- Employment Information -->
            <v-col cols="12">
              <div class="text-h6 mb-2 mt-4">
                Section 2: Employment Information
              </div>
              <v-divider class="mb-4"></v-divider>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                :model-value="selectedApplication.project?.name"
                label="Project"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                :model-value="selectedApplication.position"
                label="Position"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                :model-value="selectedApplication.date_hired"
                label="Hire Date"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                :model-value="selectedApplication.employment_status"
                label="Employment Status"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                :model-value="selectedApplication.employment_type"
                label="Employment Type"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                :model-value="selectedApplication.salary_type"
                label="Salary Type"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                :model-value="`₱${selectedApplication.basic_salary}`"
                label="Basic Pay Rate"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <!-- Allowances -->
            <v-col cols="12" v-if="hasAllowances(selectedApplication)">
              <div class="text-h6 mb-2 mt-4">Section 3: Allowances</div>
              <v-divider class="mb-4"></v-divider>
            </v-col>

            <v-col
              cols="12"
              md="6"
              v-if="selectedApplication.has_water_allowance"
            >
              <v-text-field
                :model-value="`Water Allowance: ₱${selectedApplication.water_allowance}`"
                label="Water Allowance"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6" v-if="selectedApplication.has_cola">
              <v-text-field
                :model-value="`COLA: ₱${selectedApplication.cola}`"
                label="COLA"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6" v-if="selectedApplication.has_incentives">
              <v-text-field
                :model-value="`Incentives: ₱${selectedApplication.incentives}`"
                label="Incentives"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6" v-if="selectedApplication.has_ppe">
              <v-text-field
                :model-value="`PPE: ₱${selectedApplication.ppe}`"
                label="PPE"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <!-- Documents -->
            <v-col cols="12">
              <div class="text-h6 mb-2 mt-4">Section 4: Documents</div>
              <v-divider class="mb-4"></v-divider>
            </v-col>

            <v-col cols="12">
              <v-expansion-panels>
                <!-- Resume -->
                <v-expansion-panel v-if="selectedApplication.resume_path">
                  <v-expansion-panel-title>
                    <v-icon class="mr-3" color="primary"
                      >mdi-file-document</v-icon
                    >
                    <span class="font-weight-bold">Resume (Required)</span>
                  </v-expansion-panel-title>
                  <v-expansion-panel-text>
                    <div class="mb-3">
                      <v-btn
                        size="small"
                        color="primary"
                        variant="elevated"
                        :href="getStorageUrl(selectedApplication.resume_path)"
                        target="_blank"
                        prepend-icon="mdi-open-in-new"
                      >
                        Open in New Tab
                      </v-btn>
                      <v-btn
                        size="small"
                        color="secondary"
                        variant="elevated"
                        :href="getStorageUrl(selectedApplication.resume_path)"
                        download
                        prepend-icon="mdi-download"
                        class="ml-2"
                      >
                        Download
                      </v-btn>
                    </div>
                    <!-- Preview -->
                    <div
                      v-if="
                        getFileExtension(selectedApplication.resume_path) ===
                        'pdf'
                      "
                    >
                      <iframe
                        :src="getStorageUrl(selectedApplication.resume_path)"
                        width="100%"
                        height="600px"
                        style="border: 1px solid #ddd; border-radius: 4px"
                      ></iframe>
                    </div>
                    <div v-else>
                      <v-img
                        :src="getStorageUrl(selectedApplication.resume_path)"
                        max-height="600"
                        contain
                      ></v-img>
                    </div>
                  </v-expansion-panel-text>
                </v-expansion-panel>

                <!-- ID Document -->
                <v-expansion-panel v-if="selectedApplication.id_path">
                  <v-expansion-panel-title>
                    <v-icon class="mr-3" color="info"
                      >mdi-card-account-details</v-icon
                    >
                    <span class="font-weight-bold">ID Document</span>
                  </v-expansion-panel-title>
                  <v-expansion-panel-text>
                    <div class="mb-3">
                      <v-btn
                        size="small"
                        color="primary"
                        variant="elevated"
                        :href="getStorageUrl(selectedApplication.id_path)"
                        target="_blank"
                        prepend-icon="mdi-open-in-new"
                      >
                        Open in New Tab
                      </v-btn>
                      <v-btn
                        size="small"
                        color="secondary"
                        variant="elevated"
                        :href="getStorageUrl(selectedApplication.id_path)"
                        download
                        prepend-icon="mdi-download"
                        class="ml-2"
                      >
                        Download
                      </v-btn>
                    </div>
                    <!-- Preview -->
                    <div
                      v-if="
                        getFileExtension(selectedApplication.id_path) === 'pdf'
                      "
                    >
                      <iframe
                        :src="getStorageUrl(selectedApplication.id_path)"
                        width="100%"
                        height="600px"
                        style="border: 1px solid #ddd; border-radius: 4px"
                      ></iframe>
                    </div>
                    <div v-else>
                      <v-img
                        :src="getStorageUrl(selectedApplication.id_path)"
                        max-height="600"
                        contain
                      ></v-img>
                    </div>
                  </v-expansion-panel-text>
                </v-expansion-panel>

                <!-- Contract -->
                <v-expansion-panel v-if="selectedApplication.contract_path">
                  <v-expansion-panel-title>
                    <v-icon class="mr-3" color="success">mdi-file-sign</v-icon>
                    <span class="font-weight-bold">Contract</span>
                  </v-expansion-panel-title>
                  <v-expansion-panel-text>
                    <div class="mb-3">
                      <v-btn
                        size="small"
                        color="primary"
                        variant="elevated"
                        :href="getStorageUrl(selectedApplication.contract_path)"
                        target="_blank"
                        prepend-icon="mdi-open-in-new"
                      >
                        Open in New Tab
                      </v-btn>
                      <v-btn
                        size="small"
                        color="secondary"
                        variant="elevated"
                        :href="getStorageUrl(selectedApplication.contract_path)"
                        download
                        prepend-icon="mdi-download"
                        class="ml-2"
                      >
                        Download
                      </v-btn>
                    </div>
                    <!-- Preview -->
                    <div
                      v-if="
                        getFileExtension(selectedApplication.contract_path) ===
                        'pdf'
                      "
                    >
                      <iframe
                        :src="getStorageUrl(selectedApplication.contract_path)"
                        width="100%"
                        height="600px"
                        style="border: 1px solid #ddd; border-radius: 4px"
                      ></iframe>
                    </div>
                    <div v-else>
                      <v-img
                        :src="getStorageUrl(selectedApplication.contract_path)"
                        max-height="600"
                        contain
                      ></v-img>
                    </div>
                  </v-expansion-panel-text>
                </v-expansion-panel>

                <!-- Certificates -->
                <v-expansion-panel v-if="selectedApplication.certificates_path">
                  <v-expansion-panel-title>
                    <v-icon class="mr-3" color="warning"
                      >mdi-certificate</v-icon
                    >
                    <span class="font-weight-bold">Certificates</span>
                  </v-expansion-panel-title>
                  <v-expansion-panel-text>
                    <div class="mb-3">
                      <v-btn
                        size="small"
                        color="primary"
                        variant="elevated"
                        :href="
                          getStorageUrl(selectedApplication.certificates_path)
                        "
                        target="_blank"
                        prepend-icon="mdi-open-in-new"
                      >
                        Open in New Tab
                      </v-btn>
                      <v-btn
                        size="small"
                        color="secondary"
                        variant="elevated"
                        :href="
                          getStorageUrl(selectedApplication.certificates_path)
                        "
                        download
                        prepend-icon="mdi-download"
                        class="ml-2"
                      >
                        Download
                      </v-btn>
                    </div>
                    <!-- Preview -->
                    <div
                      v-if="
                        getFileExtension(
                          selectedApplication.certificates_path
                        ) === 'pdf'
                      "
                    >
                      <iframe
                        :src="
                          getStorageUrl(selectedApplication.certificates_path)
                        "
                        width="100%"
                        height="600px"
                        style="border: 1px solid #ddd; border-radius: 4px"
                      ></iframe>
                    </div>
                    <div v-else>
                      <v-img
                        :src="
                          getStorageUrl(selectedApplication.certificates_path)
                        "
                        max-height="600"
                        contain
                      ></v-img>
                    </div>
                  </v-expansion-panel-text>
                </v-expansion-panel>
              </v-expansion-panels>

              <!-- No documents message -->
              <v-alert
                v-if="
                  !selectedApplication.resume_path &&
                  !selectedApplication.id_path &&
                  !selectedApplication.contract_path &&
                  !selectedApplication.certificates_path
                "
                type="info"
                variant="tonal"
              >
                No documents uploaded with this application
              </v-alert>
            </v-col>

            <!-- Application Status -->
            <v-col cols="12">
              <div class="text-h6 mb-2 mt-4">
                Section 5: Application Details
              </div>
              <v-divider class="mb-4"></v-divider>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                :model-value="
                  selectedApplication.creator?.username || 'Unknown'
                "
                label="Submitted By"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                :model-value="formatDate(selectedApplication.submitted_at)"
                label="Submitted At"
                readonly
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <!-- Hire Date (required for approval) -->
            <v-col cols="12" md="6" v-if="applicationAction === 'approve'">
              <v-text-field
                v-model="approvalHireDate"
                label="Hire Date (Required)"
                type="date"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
                hint="Set the official hire date for this employee"
                persistent-hint
              ></v-text-field>
            </v-col>

            <!-- Rejection Reason (only for approve/reject) -->
            <v-col cols="12" v-if="applicationAction === 'reject'">
              <v-textarea
                v-model="rejectionReason"
                label="Rejection Reason"
                rows="3"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
                hint="Please provide a reason for rejection"
                persistent-hint
              ></v-textarea>
            </v-col>
          </v-row>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-btn
            color="error"
            variant="elevated"
            @click="applicationAction = 'reject'"
            :disabled="processing || applicationAction === 'reject'"
          >
            <v-icon start>mdi-close-circle</v-icon>
            Reject
          </v-btn>
          <v-spacer></v-spacer>
          <v-btn
            variant="text"
            @click="closeApplicationDialog"
            :disabled="processing"
          >
            Cancel
          </v-btn>
          <v-btn
            v-if="applicationAction === 'reject'"
            color="error"
            variant="elevated"
            @click="rejectApplication"
            :loading="processing"
          >
            <v-icon start>mdi-send</v-icon>
            Confirm Rejection
          </v-btn>
          <v-btn
            v-else
            color="success"
            variant="elevated"
            @click="approveApplication"
            :loading="processing"
          >
            <v-icon start>mdi-check-circle</v-icon>
            Approve Application
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Approval Success Dialog -->
    <v-dialog v-model="showApprovalSuccessDialog" max-width="600px" persistent>
      <v-card>
        <v-card-title class="text-h5 py-4 bg-success">
          <v-icon start>mdi-check-circle</v-icon>
          Application Approved
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-6">
          <v-alert type="success" variant="tonal" class="mb-4">
            <div class="text-h6 mb-2">
              {{ approvedEmployeeData?.employee_number }} -
              {{ approvedEmployeeData?.first_name }}
              {{ approvedEmployeeData?.last_name }}
            </div>
          </v-alert>

          <div class="mb-4">
            <div class="text-subtitle-1 font-weight-bold mb-2">
              Login Credentials Created:
            </div>
            <v-sheet color="grey-lighten-4" rounded class="pa-4">
              <div class="mb-3">
                <div class="text-caption">Username (Email)</div>
                <div class="text-body-1 font-weight-bold">
                  {{ approvedEmployeeData?.email }}
                </div>
              </div>
              <div class="mb-3">
                <div class="text-caption">Temporary Password</div>
                <div class="text-h6 font-weight-bold text-primary">
                  {{ approvedEmployeePassword }}
                </div>
              </div>
              <div>
                <div class="text-caption">Role</div>
                <div class="text-body-1 font-weight-bold text-capitalize">
                  Employee
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
            @click="copyApprovedCredentials"
          >
            Copy
          </v-btn>
          <v-spacer></v-spacer>
          <v-btn color="primary" @click="showApprovalSuccessDialog = false">
            Done
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import api from "@/services/api";
import { useToast } from "vue-toastification";
import AddEmployeeDialog from "@/components/AddEmployeeDialog.vue";

const toast = useToast();

const stats = ref({
  totalEmployees: 0,
  activeEmployees: 0,
  periodPayroll: 0,
  presentToday: 0,
  pendingApprovals: 0,
});

const recentAttendance = ref([]);
const recentPayrolls = ref([]);

// Application management
const pendingApplications = ref([]);
const applicationHeaders = ref([
  { title: "Name", key: "full_name" },
  { title: "Position", key: "position" },
  { title: "Project", key: "project" },
  { title: "Submitted By", key: "created_by" },
  { title: "Submitted At", key: "submitted_at" },
  { title: "Status", key: "application_status" },
  { title: "Actions", key: "actions", sortable: false },
]);
const showApplicationDialog = ref(false);
const selectedApplication = ref(null);
const applicationAction = ref("approve"); // 'approve' or 'reject'
const rejectionReason = ref("");
const approvalHireDate = ref("");
const processing = ref(false);
const showApprovalSuccessDialog = ref(false);
const approvedEmployeeData = ref(null);
const approvedEmployeePassword = ref("");

// Employee form data
const showAddEmployeeDialog = ref(false);
const showPasswordDialog = ref(false);
const temporaryPassword = ref("");
const newEmployeeData = ref(null);
const createdEmployeeUsername = ref("");
const saving = ref(false);
const refreshing = ref(false);
const projects = ref([]);

async function saveEmployee(employeeData) {
  saving.value = true;
  try {
    const response = await api.post("/employees", employeeData);

    // Store temporary password and employee data
    temporaryPassword.value = response.data.temporary_password;
    newEmployeeData.value = response.data.employee;
    newEmployeeData.value.role = response.data.role; // Add role from response
    createdEmployeeUsername.value = response.data.username;

    toast.success("Employee created successfully!");
    showAddEmployeeDialog.value = false; // Close the dialog

    // Show password dialog
    showPasswordDialog.value = true;

    await fetchDashboardData(); // Refresh dashboard stats
  } catch (error) {
    console.error("Error creating employee:", error);
    console.error("Full error response:", error.response?.data);
    console.error("Validation errors:", error.response?.data?.errors);

    if (error.response?.data?.errors) {
      // Show specific validation errors
      const errors = error.response.data.errors;
      Object.keys(errors).forEach((field) => {
        toast.error(`${field}: ${errors[field][0]}`);
      });
    } else {
      const message =
        error.response?.data?.message ||
        error.response?.data?.error ||
        "Failed to create employee";
      toast.error(message);
    }
  } finally {
    saving.value = false;
  }
}

const currentDate = computed(() => {
  return new Date().toLocaleDateString("en-US", {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  });
});

onMounted(async () => {
  await fetchDashboardData();
  await fetchProjects();
  await fetchPendingApplications();
});

async function fetchDashboardData() {
  try {
    const response = await api.get("/dashboard");
    stats.value = response.data.stats;
    recentAttendance.value = response.data.recent_attendance || [];
    recentPayrolls.value = response.data.recent_payrolls || [];
  } catch (error) {
    console.error("Error fetching dashboard data:", error);
  }
}

async function refreshData() {
  refreshing.value = true;
  try {
    await Promise.all([fetchDashboardData(), fetchPendingApplications()]);
    toast.success("Dashboard refreshed successfully!");
  } catch (error) {
    console.error("Error refreshing dashboard:", error);
    toast.error("Failed to refresh dashboard");
  } finally {
    refreshing.value = false;
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

function copyCredentials() {
  const emailInfo = newEmployeeData.value?.email
    ? `\nEmail: ${newEmployeeData.value.email}`
    : "";
  const credentials = `Employee Login Credentials
Employee Number: ${newEmployeeData.value?.employee_number}
Name: ${newEmployeeData.value?.first_name} ${newEmployeeData.value?.last_name}
Username: ${
    createdEmployeeUsername.value || newEmployeeData.value?.email
  }${emailInfo}
Temporary Password: ${temporaryPassword.value}
Role: ${newEmployeeData.value?.role}

⚠️ Employee must change password on first login.`;

  navigator.clipboard.writeText(credentials);
  toast.success("Credentials copied to clipboard!");
}

function formatNumber(value) {
  return new Intl.NumberFormat("en-PH", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value || 0);
}

function getAttendanceColor(status) {
  const colors = {
    present: "success",
    absent: "error",
    late: "warning",
    halfday: "info",
  };
  return colors[status] || "grey";
}

function getAttendanceIcon(status) {
  const icons = {
    present: "mdi-check-circle",
    absent: "mdi-close-circle",
    late: "mdi-clock-alert",
    halfday: "mdi-clock-half",
  };
  return icons[status] || "mdi-help-circle";
}

function getPayrollColor(status) {
  const colors = {
    draft: "grey",
    processing: "info",
    checked: "warning",
    recommended: "accent",
    approved: "success",
    paid: "primary",
  };
  return colors[status] || "grey";
}

// Application Management Functions
async function fetchPendingApplications() {
  try {
    const response = await api.get("/employee-applications", {
      params: { status: "pending" },
    });
    pendingApplications.value = response.data;
  } catch (error) {
    console.error("Error fetching applications:", error);
    toast.error("Failed to load pending applications");
  }
}

function viewApplication(application) {
  selectedApplication.value = application;
  applicationAction.value = "approve";
  rejectionReason.value = "";
  approvalHireDate.value =
    application.date_hired || new Date().toISOString().split("T")[0];
  showApplicationDialog.value = true;
}

async function approveApplication() {
  if (!approvalHireDate.value) {
    toast.warning("Please provide a hire date");
    return;
  }
  processing.value = true;
  try {
    const response = await api.post(
      `/employee-applications/${selectedApplication.value.id}/approve`,
      { date_hired: approvalHireDate.value }
    );
    approvedEmployeeData.value = response.data.employee;
    approvedEmployeePassword.value = response.data.temporary_password;
    toast.success("Application approved! Employee account created.");
    closeApplicationDialog();
    showApprovalSuccessDialog.value = true;
    await fetchPendingApplications();
    await fetchDashboardData(); // Update stats
  } catch (error) {
    console.error("Error approving application:", error);
    toast.error(
      error.response?.data?.message || "Failed to approve application"
    );
  } finally {
    processing.value = false;
  }
}

async function rejectApplication() {
  if (!rejectionReason.value.trim()) {
    toast.warning("Please provide a rejection reason");
    return;
  }
  processing.value = true;
  try {
    await api.post(
      `/employee-applications/${selectedApplication.value.id}/reject`,
      { rejection_reason: rejectionReason.value }
    );
    toast.success("Application rejected");
    closeApplicationDialog();
    await fetchPendingApplications();
  } catch (error) {
    console.error("Error rejecting application:", error);
    toast.error("Failed to reject application");
  } finally {
    processing.value = false;
  }
}

function closeApplicationDialog() {
  showApplicationDialog.value = false;
  selectedApplication.value = null;
  applicationAction.value = "approve";
  rejectionReason.value = "";
  approvalHireDate.value = "";
  processing.value = false;
}

function copyApprovedCredentials() {
  const credentials = `Employee Login Credentials
Employee Number: ${approvedEmployeeData.value.employee_number}
Name: ${approvedEmployeeData.value.first_name} ${approvedEmployeeData.value.last_name}
Username: ${approvedEmployeeData.value.email}
Temporary Password: ${approvedEmployeePassword.value}
Role: Employee

⚠️ Employee must change password on first login.`;

  navigator.clipboard
    .writeText(credentials)
    .then(() => {
      toast.success("Credentials copied to clipboard!");
    })
    .catch(() => {
      toast.error("Failed to copy credentials");
    });
}

function hasAllowances(application) {
  return (
    application.has_water_allowance ||
    application.has_cola ||
    application.has_incentives ||
    application.has_ppe
  );
}

function getApplicationStatusColor(status) {
  const colors = {
    pending: "warning",
    approved: "success",
    rejected: "error",
  };
  return colors[status] || "default";
}

function formatDate(dateString) {
  if (!dateString) return "";
  const date = new Date(dateString);
  return date.toLocaleString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

function getStorageUrl(path) {
  const baseURL = import.meta.env.VITE_API_URL || "http://localhost:8000/api";
  const backendURL = baseURL.replace("/api", "");
  return `${backendURL}/storage/${path}`;
}

function getFileExtension(path) {
  if (!path) return "";
  return path.split(".").pop().toLowerCase();
}
</script>

<style scoped lang="scss">
// Construction-themed Dashboard Styling

.construction-header {
  font-family: "Roboto Condensed", sans-serif;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  color: #37474f;
}

.construction-btn {
  text-transform: none;
  font-weight: 700;
  letter-spacing: 0.5px;
  box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
  transition: all 0.3s ease;

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
  }
}

// Stat Cards with Construction Theme
.stat-card {
  position: relative;
  overflow: hidden;
  transition: all 0.3s ease;

  &::before {
    content: "";
    position: absolute;
    top: 0;
    right: 0;
    width: 100px;
    height: 100px;
    background: rgba(255, 255, 255, 0.05);
    border-radius: 50%;
    transform: translate(30%, -30%);
  }

  &:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 24px rgba(0, 0, 0, 0.15) !important;
  }

  &.stat-card-primary {
    border-left-color: #d84315 !important;
  }

  &.stat-card-success {
    border-left-color: #2e7d32 !important;
  }

  &.stat-card-info {
    border-left-color: #0277bd !important;
  }

  &.stat-card-warning {
    border-left-color: #f9a825 !important;
  }
}

.stat-avatar {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  border: 3px solid rgba(255, 255, 255, 0.3);
}

// Quick Actions Card
.quick-actions-card {
  background: linear-gradient(135deg, #ffffff 0%, #f5f5f5 100%) !important;
}

.action-btn {
  height: 100px !important;
  flex-direction: column;
  gap: 8px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;

  :deep(.v-btn__content) {
    flex-direction: column;
    gap: 8px;
  }

  &:hover {
    transform: translateY(-4px) scale(1.02);
  }
}

// List items with construction accents
:deep(.v-list-item) {
  border-left: 3px solid transparent;
  transition: all 0.2s ease;

  &:hover {
    border-left-color: #d84315;
    background: rgba(216, 67, 21, 0.05);
  }
}

// Card title styling
:deep(.v-card-title) {
  font-weight: 700;
  letter-spacing: 0.5px;
}

// Chart card styling
.chart-card {
  background: linear-gradient(135deg, #ffffff 0%, #fafafa 100%) !important;

  canvas {
    max-height: 300px;
  }
}
</style>
