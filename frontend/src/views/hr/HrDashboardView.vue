<template>
  <div class="modern-dashboard hr-dashboard">
    <!-- Dashboard Content -->
    <div>
      <!-- Merged Header with Employee Info -->
      <div class="dashboard-header-merged hr-gradient">
        <div class="header-content">
          <v-avatar color="#ED985F" size="80" class="header-avatar">
            <v-img v-if="userAvatar" :src="userAvatar" cover></v-img>
            <v-icon v-else size="40" color="white">mdi-account-tie</v-icon>
          </v-avatar>
          <div class="header-info">
            <div class="welcome-badge hr-badge">
              <v-icon size="16" class="welcome-icon">mdi-account-tie</v-icon>
              <span>Human Resources Portal</span>
            </div>
            <h1 class="dashboard-title">Welcome, {{ fullName }}</h1>
            <p class="dashboard-subtitle">
              {{ employee?.position }} - {{ employee?.project?.name }} •
              Employee No: {{ employee?.employee_number }}
            </p>
            <p class="dashboard-date">{{ currentMonthYear }}</p>
          </div>
        </div>
        <div class="header-actions">
          <v-btn
            variant="text"
            prepend-icon="mdi-refresh"
            @click="loadDashboardData"
            :loading="refreshing"
            class="refresh-btn"
          >
            Refresh
          </v-btn>
        </div>
      </div>

      <!-- HR Statistics Cards -->
      <v-row class="stats-row">
        <v-col cols="12" sm="6" lg="3">
          <div
            class="stat-card-new stat-card-hr-employees"
            @click="$router.push('/employees')"
          >
            <div class="stat-icon-wrapper">
              <div class="stat-icon-circle">
                <v-icon size="22">mdi-account-group</v-icon>
              </div>
            </div>
            <div class="stat-content">
              <div class="stat-label">Total Employees</div>
              <div class="stat-value">{{ hrStats.totalEmployees }}</div>
              <div class="stat-meta">Active workforce</div>
            </div>
            <div class="stat-arrow">
              <v-icon size="20">mdi-chevron-right</v-icon>
            </div>
          </div>
        </v-col>

        <v-col cols="12" sm="6" lg="3">
          <div
            class="stat-card-new stat-card-hr-applications"
            @click="$router.push('/resume-review')"
          >
            <div class="stat-icon-wrapper">
              <div class="stat-icon-circle stat-icon-pulse-hr">
                <v-icon size="22">mdi-file-account</v-icon>
              </div>
            </div>
            <div class="stat-content">
              <div class="stat-label">Pending Applications</div>
              <div class="stat-value">{{ hrStats.pendingApplications }}</div>
              <div class="stat-meta">Need review</div>
            </div>
            <div class="stat-arrow">
              <v-icon size="20">mdi-chevron-right</v-icon>
            </div>
          </div>
        </v-col>

        <v-col cols="12" sm="6" lg="3">
          <div
            class="stat-card-new stat-card-hr-leaves"
            @click="$router.push('/leave-approval')"
          >
            <div class="stat-icon-wrapper">
              <div class="stat-icon-circle">
                <v-icon size="22">mdi-calendar-alert</v-icon>
              </div>
            </div>
            <div class="stat-content">
              <div class="stat-label">Pending Leaves</div>
              <div class="stat-value">{{ hrStats.pendingLeaves }}</div>
              <div class="stat-meta">Awaiting approval</div>
            </div>
            <div class="stat-arrow">
              <v-icon size="20">mdi-chevron-right</v-icon>
            </div>
          </div>
        </v-col>

        <v-col cols="12" sm="6" lg="3">
          <div
            class="stat-card-new stat-card-hr-resignations"
            @click="$router.push('/resignations')"
          >
            <div class="stat-icon-wrapper">
              <div class="stat-icon-circle">
                <v-icon size="22">mdi-briefcase-remove</v-icon>
              </div>
            </div>
            <div class="stat-content">
              <div class="stat-label">Recent Resignations</div>
              <div class="stat-value">{{ hrStats.recentResignations }}</div>
              <div class="stat-meta">Last 30 days</div>
            </div>
            <div class="stat-arrow">
              <v-icon size="20">mdi-chevron-right</v-icon>
            </div>
          </div>
        </v-col>
      </v-row>

      <!-- Main Content Layout -->
      <v-row>
        <!-- Left Column - 2/3 Width -->
        <v-col cols="12" lg="8">
          <!-- Pending Actions with Modern Design -->
          <div class="action-section mb-6">
            <div class="section-header-compact">
              <div class="section-title-wrapper">
                <div class="section-icon-badge">
                  <v-icon size="18">mdi-bell-ring</v-icon>
                </div>
                <h2 class="section-title">Action Items</h2>
                <v-chip size="small" class="ml-2" v-if="totalPendingItems > 0">
                  {{ totalPendingItems }}
                </v-chip>
              </div>
            </div>

            <!-- Action Cards in Grid -->
            <div class="action-grid" v-if="totalPendingItems > 0">
              <!-- Pending Applications -->
              <div
                v-if="hrStats.pendingApplications > 0"
                class="action-item"
                @click="$router.push('/resume-review')"
              >
                <div class="action-item-icon action-icon-warning">
                  <v-icon size="24">mdi-file-account</v-icon>
                </div>
                <div class="action-item-content">
                  <div class="action-item-title">Job Applications</div>
                  <div class="action-item-desc">
                    {{ hrStats.pendingApplications }} awaiting review
                  </div>
                </div>
                <div class="action-item-badge">
                  {{ hrStats.pendingApplications }}
                </div>
              </div>

              <!-- Pending Leaves -->
              <div
                v-if="hrStats.pendingLeaves > 0"
                class="action-item"
                @click="$router.push('/leave-approval')"
              >
                <div class="action-item-icon action-icon-info">
                  <v-icon size="24">mdi-calendar-alert</v-icon>
                </div>
                <div class="action-item-content">
                  <div class="action-item-title">Leave Requests</div>
                  <div class="action-item-desc">
                    {{ hrStats.pendingLeaves }} need approval
                  </div>
                </div>
                <div class="action-item-badge">
                  {{ hrStats.pendingLeaves }}
                </div>
              </div>

              <!-- Recent Resignations -->
              <div
                v-if="hrStats.recentResignations > 0"
                class="action-item"
                @click="$router.push('/resignations')"
              >
                <div class="action-item-icon action-icon-danger">
                  <v-icon size="24">mdi-briefcase-remove</v-icon>
                </div>
                <div class="action-item-content">
                  <div class="action-item-title">Resignations</div>
                  <div class="action-item-desc">
                    {{ hrStats.recentResignations }} pending review
                  </div>
                </div>
                <div class="action-item-badge">
                  {{ hrStats.recentResignations }}
                </div>
              </div>
            </div>

            <!-- No Pending Actions -->
            <div v-else class="no-actions-state">
              <div class="no-actions-icon">
                <v-icon size="64" color="success"
                  >mdi-check-circle-outline</v-icon
                >
              </div>
              <div class="no-actions-title">All Caught Up!</div>
              <div class="no-actions-desc">
                No pending actions require your attention
              </div>
            </div>
          </div>

          <!-- Pending Applications Details -->
          <div class="applications-section mb-6">
            <div class="section-header-compact">
              <div class="section-icon-badge">
                <v-icon size="16">mdi-file-account</v-icon>
              </div>
              <h3 class="section-title-compact">Recent Applications</h3>
            </div>

            <div class="content-card">
              <v-data-table
                :headers="applicationHeaders"
                :items="pendingApplications"
                :items-per-page="10"
                class="modern-table"
              >
                <template v-slot:item.name="{ item }">
                  <div class="name-cell">
                    <v-avatar size="32" color="#ED985F">
                      <span class="text-white text-caption">{{
                        getInitials(item.first_name, item.last_name)
                      }}</span>
                    </v-avatar>
                    <span class="ml-2">
                      {{ item.first_name }} {{ item.last_name }}
                    </span>
                  </div>
                </template>
                <template v-slot:item.position="{ item }">
                  <span class="table-text">{{ item.position }}</span>
                </template>
                <template v-slot:item.project="{ item }">
                  <span class="table-text">{{
                    item.project?.name || "N/A"
                  }}</span>
                </template>
                <template v-slot:item.created_at="{ item }">
                  <span class="table-date">{{
                    formatDate(item.created_at)
                  }}</span>
                </template>
                <template v-slot:item.actions="{ item }">
                  <v-btn
                    size="small"
                    variant="text"
                    color="#ED985F"
                    @click="viewApplication(item)"
                  >
                    Review
                  </v-btn>
                </template>
              </v-data-table>
            </div>
          </div>

          <!-- Pending Leave Requests Section -->
          <div class="leave-requests-section mb-6">
            <div class="section-header-compact">
              <div class="section-icon-badge warning">
                <v-icon size="16">mdi-calendar-alert</v-icon>
              </div>
              <h3 class="section-title-compact">Recent Leave Requests</h3>
            </div>

            <div class="content-card">
              <v-data-table
                :headers="leaveHeaders"
                :items="pendingLeaveRequests"
                :items-per-page="10"
                class="modern-table"
              >
                <template v-slot:item.employee="{ item }">
                  <div class="name-cell">
                    <v-avatar size="32" color="#F57C00">
                      <span class="text-white text-caption">{{
                        getInitials(
                          item.employee.first_name,
                          item.employee.last_name,
                        )
                      }}</span>
                    </v-avatar>
                    <span class="ml-2">
                      {{ item.employee.first_name }}
                      {{ item.employee.last_name }}
                    </span>
                  </div>
                </template>
                <template v-slot:item.leave_type="{ item }">
                  <v-chip size="small" variant="flat" color="#FFF3E0">
                    {{ item.leave_type }}
                  </v-chip>
                </template>
                <template v-slot:item.dates="{ item }">
                  <span class="table-text">
                    {{ formatDate(item.start_date) }} -
                    {{ formatDate(item.end_date) }}
                  </span>
                </template>
                <template v-slot:item.total_days="{ item }">
                  <span class="table-hours">{{ item.total_days }} days</span>
                </template>
                <template v-slot:item.actions="{ item }">
                  <v-btn
                    size="small"
                    variant="text"
                    color="#F57C00"
                    @click="viewLeaveRequest(item)"
                  >
                    Review
                  </v-btn>
                </template>
              </v-data-table>
            </div>
          </div>
        </v-col>

        <!-- Right Column - 1/3 Width -->
        <v-col cols="12" lg="4">
          <!-- Current Payslip Details -->
          <div class="current-payslip-section mb-6">
            <div class="section-header-compact">
              <div class="section-icon-badge success">
                <v-icon size="16">mdi-cash-multiple</v-icon>
              </div>
              <h3 class="section-title-compact">Current Payslip</h3>
            </div>

            <div
              class="content-card"
              v-if="currentPayslip && currentPayslip.payroll"
            >
              <div class="payslip-details">
                <div class="payslip-detail-item">
                  <span class="detail-label">Pay Period</span>
                  <span class="detail-value">
                    {{ formatDate(currentPayslip.payroll.period_start) }} -
                    {{ formatDate(currentPayslip.payroll.period_end) }}
                  </span>
                </div>

                <div class="payslip-detail-divider"></div>

                <div class="payslip-detail-item">
                  <span class="detail-label">Gross Pay</span>
                  <span class="detail-value amount-positive"
                    >₱{{ formatNumber(currentPayslip.gross_pay) }}</span
                  >
                </div>

                <div class="payslip-detail-item">
                  <span class="detail-label">Deductions</span>
                  <span class="detail-value amount-negative"
                    >₱{{ formatNumber(currentPayslip.total_deductions) }}</span
                  >
                </div>

                <div class="payslip-detail-divider-thick"></div>

                <div class="payslip-detail-item-main">
                  <span class="detail-label-main">Net Pay</span>
                  <span class="detail-value-main"
                    >₱{{ formatNumber(currentPayslip.net_pay) }}</span
                  >
                </div>

                <div class="payslip-actions">
                  <button
                    class="payslip-action-btn primary"
                    @click="downloadPayslip(currentPayslip.id, 'pdf')"
                  >
                    <v-icon size="18">mdi-file-pdf-box</v-icon>
                    <span>Download PDF</span>
                  </button>
                  <button
                    class="payslip-action-btn secondary"
                    @click="downloadPayslip(currentPayslip.id, 'excel')"
                  >
                    <v-icon size="18">mdi-file-excel</v-icon>
                    <span>Download Excel</span>
                  </button>
                </div>
              </div>
            </div>
            <div v-else class="content-card">
              <div class="empty-state-small">
                <v-icon size="48" color="rgba(0, 31, 61, 0.2)"
                  >mdi-cash-off</v-icon
                >
                <div class="empty-state-text">No current payslip available</div>
              </div>
            </div>
          </div>

          <!-- Quick Actions - HR specific -->
          <div class="quick-actions-section mb-6">
            <div class="section-header-compact">
              <div class="section-icon-badge">
                <v-icon size="16">mdi-lightning-bolt</v-icon>
              </div>
              <h3 class="section-title-compact">Quick Actions</h3>
            </div>
            <div class="quick-action-buttons">
              <button
                class="quick-action-btn"
                @click="$router.push('/employees?add=true')"
              >
                <div class="quick-action-icon">
                  <v-icon>mdi-account-plus</v-icon>
                </div>
                <span>Add Employee</span>
              </button>
              <button
                class="quick-action-btn"
                @click="showUploadResumeDialog = true"
              >
                <div class="quick-action-icon">
                  <v-icon>mdi-file-account</v-icon>
                </div>
                <span>Upload Resume</span>
              </button>
              <button
                class="quick-action-btn"
                @click="$router.push('/biometric-import')"
              >
                <div class="quick-action-icon">
                  <v-icon>mdi-file-upload</v-icon>
                </div>
                <span>Import Biometrics</span>
              </button>
            </div>
          </div>

          <!-- HR Statistics -->
          <div class="system-health-section">
            <div class="section-header-compact">
              <div class="section-icon-badge success">
                <v-icon size="16">mdi-chart-box</v-icon>
              </div>
              <h3 class="section-title-compact">HR Overview</h3>
            </div>
            <div class="health-metrics">
              <div class="health-metric">
                <div class="metric-info">
                  <div class="metric-info-left">
                    <v-icon size="20" class="metric-icon" color="#ed985f"
                      >mdi-account-group</v-icon
                    >
                    <span class="metric-info-label">Active Employees</span>
                  </div>
                  <div class="metric-badge">{{ hrStats.totalEmployees }}</div>
                </div>
              </div>

              <div class="health-metric">
                <div class="metric-info">
                  <div class="metric-info-left">
                    <v-icon size="20" class="metric-icon" color="#F57C00"
                      >mdi-clock-check</v-icon
                    >
                    <span class="metric-info-label">Attendance Rate</span>
                  </div>
                  <div class="metric-badge">{{ attendanceRate }}%</div>
                </div>
              </div>

              <div class="health-metric">
                <div class="metric-info">
                  <div class="metric-info-left">
                    <v-icon size="20" class="metric-icon" color="#5c6bc0"
                      >mdi-briefcase-remove</v-icon
                    >
                    <span class="metric-info-label">Turnover (30d)</span>
                  </div>
                  <div class="metric-text">
                    {{ hrStats.recentResignations }} employees
                  </div>
                </div>
              </div>
            </div>
          </div>
        </v-col>
      </v-row>

      <!-- My Submitted Resumes Section -->
      <v-row class="mt-4">
        <v-col cols="12">
          <div class="section-header-compact">
            <div class="section-icon-badge">
              <v-icon size="16">mdi-file-document-multiple</v-icon>
            </div>
            <h3 class="section-title-compact">My Submitted Resumes</h3>
          </div>

          <div class="content-card">
            <v-data-table
              :headers="resumeHeaders"
              :items="myResumes"
              :loading="loadingResumes"
              class="modern-table"
            >
              <template v-slot:item.full_name="{ item }">
                <span class="table-text">{{
                  item.full_name || `${item.first_name} ${item.last_name}`
                }}</span>
              </template>
              <template v-slot:item.email="{ item }">
                <span class="table-text">{{ item.email }}</span>
              </template>
              <template v-slot:item.position_applied="{ item }">
                <span class="table-text">{{ item.position_applied }}</span>
              </template>
              <template v-slot:item.status="{ item }">
                <v-chip
                  :color="getResumeStatusColor(item.status)"
                  size="small"
                  variant="flat"
                >
                  {{ item.status }}
                </v-chip>
              </template>
              <template v-slot:item.created_at="{ item }">
                <span class="table-date">{{
                  formatDate(item.created_at)
                }}</span>
              </template>
              <template v-slot:item.actions="{ item }">
                <v-btn
                  icon="mdi-download"
                  size="small"
                  variant="text"
                  @click="downloadResume(item.id)"
                  title="Download Resume"
                ></v-btn>
                <v-btn
                  v-if="item.status === 'pending'"
                  icon="mdi-delete"
                  size="small"
                  variant="text"
                  color="error"
                  @click="deleteResume(item.id)"
                  title="Delete"
                ></v-btn>
              </template>
            </v-data-table>
          </div>
        </v-col>
      </v-row>
    </div>

    <!-- Upload Resume Dialog -->
    <v-dialog
      v-model="showUploadResumeDialog"
      max-width="900px"
      persistent
      scrollable
    >
      <v-card class="modern-dialog">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper primary">
            <v-icon size="24">mdi-account-plus-outline</v-icon>
          </div>
          <div>
            <div class="dialog-title">Upload Applicant Resume</div>
            <div class="dialog-subtitle">
              Complete applicant information for review
            </div>
          </div>
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="dialog-content" style="max-height: 70vh">
          <v-form ref="resumeForm">
            <!-- Info Alert -->
            <v-alert
              type="info"
              variant="tonal"
              density="compact"
              class="mb-4"
              icon="mdi-information"
            >
              Application will be sent for admin review after submission.
            </v-alert>

            <v-row>
              <!-- Section 1: Personal Information -->
              <v-col cols="12">
                <div class="section-header">
                  <div class="section-icon">
                    <v-icon size="18">mdi-account-circle</v-icon>
                  </div>
                  <h3 class="section-title">Personal Information</h3>
                </div>
              </v-col>

              <v-col cols="12" md="4">
                <v-text-field
                  v-model="resumeData.first_name"
                  label="First Name"
                  prepend-inner-icon="mdi-account"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="4">
                <v-text-field
                  v-model="resumeData.middle_name"
                  label="Middle Name"
                  variant="outlined"
                  density="comfortable"
                  hint="Optional"
                  persistent-hint
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="4">
                <v-text-field
                  v-model="resumeData.last_name"
                  label="Last Name"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="resumeData.date_of_birth"
                  label="Date of Birth"
                  type="date"
                  prepend-inner-icon="mdi-calendar"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="resumeData.gender"
                  :items="GENDERS"
                  label="Gender"
                  prepend-inner-icon="mdi-gender-male-female"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                ></v-select>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="resumeData.email"
                  label="Email Address"
                  type="email"
                  prepend-inner-icon="mdi-email"
                  :rules="[rules.required, rules.email]"
                  variant="outlined"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="resumeData.phone"
                  label="Mobile Number"
                  prepend-inner-icon="mdi-cellphone"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                  hint="Format: 09171234567"
                  persistent-hint
                ></v-text-field>
              </v-col>

              <v-col cols="12">
                <v-textarea
                  v-model="resumeData.address"
                  label="Complete Address"
                  prepend-inner-icon="mdi-map-marker"
                  rows="1"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                ></v-textarea>
              </v-col>

              <!-- Section 2: Professional Information -->
              <v-col cols="12" class="mt-4">
                <div class="section-header">
                  <div class="section-icon">
                    <v-icon size="18">mdi-briefcase</v-icon>
                  </div>
                  <h3 class="section-title">Professional Information</h3>
                </div>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="resumeData.position_applied"
                  :items="positionOptions"
                  label="Position Applied For"
                  prepend-inner-icon="mdi-badge-account"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                  hint="Select from available positions"
                  persistent-hint
                ></v-select>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="resumeData.department_preference"
                  :items="departments"
                  item-title="name"
                  item-value="name"
                  label="Department Preference"
                  prepend-inner-icon="mdi-office-building"
                  variant="outlined"
                  density="comfortable"
                  hint="Optional"
                  persistent-hint
                  clearable
                ></v-select>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="resumeData.expected_salary"
                  label="Expected Salary"
                  prepend-inner-icon="mdi-cash"
                  variant="outlined"
                  density="comfortable"
                  hint="Optional - Daily or monthly rate"
                  persistent-hint
                  prefix="₱"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="resumeData.availability_date"
                  label="Available Start Date"
                  type="date"
                  prepend-inner-icon="mdi-calendar-clock"
                  variant="outlined"
                  density="comfortable"
                  hint="When can you start?"
                  persistent-hint
                ></v-text-field>
              </v-col>

              <!-- Section 3: Resume Upload -->
              <v-col cols="12" class="mt-4">
                <div class="section-header">
                  <div class="section-icon">
                    <v-icon size="18">mdi-file-document</v-icon>
                  </div>
                  <h3 class="section-title">Resume & Documents</h3>
                </div>
              </v-col>

              <v-col cols="12">
                <v-file-input
                  v-model="resumeData.resume_file"
                  label="Upload Resume"
                  accept=".pdf,.jpg,.jpeg,.png"
                  variant="outlined"
                  density="comfortable"
                  prepend-icon="mdi-paperclip"
                  hint="PDF, JPG, or PNG (Max 10MB)"
                  persistent-hint
                  show-size
                  :multiple="false"
                  :rules="[rules.required]"
                  @update:model-value="handleFileChange"
                >
                  <template v-slot:selection="{ fileNames }">
                    <v-chip size="small" color="primary" class="me-2">
                      {{ fileNames[0] }}
                    </v-chip>
                  </template>
                </v-file-input>
              </v-col>

              <!-- File Preview Section -->
              <v-col cols="12" v-if="filePreview">
                <v-card variant="outlined" class="file-preview-card">
                  <v-card-title class="text-subtitle-2 bg-grey-lighten-4">
                    <v-icon start size="20">mdi-eye</v-icon>
                    File Preview
                  </v-card-title>
                  <v-card-text class="pa-0">
                    <!-- Image Preview -->
                    <div
                      v-if="filePreviewType === 'image'"
                      class="image-preview-container"
                    >
                      <v-img
                        :src="filePreview"
                        max-height="400"
                        contain
                        class="preview-image"
                      ></v-img>
                    </div>
                    <!-- PDF Preview -->
                    <div
                      v-else-if="filePreviewType === 'pdf'"
                      class="pdf-preview-container"
                    >
                      <iframe
                        :src="filePreview"
                        class="pdf-preview"
                        frameborder="0"
                      ></iframe>
                    </div>
                  </v-card-text>
                </v-card>
              </v-col>

              <v-col cols="12">
                <v-textarea
                  v-model="resumeData.notes"
                  label="Additional Notes"
                  rows="3"
                  variant="outlined"
                  density="comfortable"
                  hint="Optional - Any additional information you'd like to share"
                  persistent-hint
                ></v-textarea>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <button
            class="dialog-btn dialog-btn-cancel"
            @click="closeResumeDialog"
            :disabled="uploading"
          >
            Cancel
          </button>
          <button
            class="dialog-btn dialog-btn-primary"
            @click="submitResume"
            :disabled="uploading"
          >
            <v-progress-circular
              v-if="uploading"
              indeterminate
              size="16"
              width="2"
              class="me-2"
            ></v-progress-circular>
            {{ uploading ? "Uploading..." : "Submit Application" }}
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import { useToast } from "vue-toastification";
import api from "@/services/api";
import { resumeService } from "@/services/resumeService";
import { formatDate, formatNumber } from "@/utils/formatters";
import { devLog } from "@/utils/devLog";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import { usePositionRates } from "@/composables/usePositionRates";

const router = useRouter();
const authStore = useAuthStore();
const toast = useToast();
const { confirm: confirmDialog } = useConfirmDialog();
const { positionOptions, loadPositionRates } = usePositionRates();

const refreshing = ref(false);
const downloading = ref(false);
const uploading = ref(false);
const loadingResumes = ref(false);
const employee = ref(null);
const attendanceSummary = ref({
  present: 0,
  absent: 0,
  late: 0,
  total_hours: 0,
});
const attendanceRecords = ref([]);
const currentPayslip = ref(null);
const payslipHistory = ref([]);
const myResumes = ref([]);
const showUploadResumeDialog = ref(false);

// HR-specific data
const hrStats = ref({
  totalEmployees: 0,
  pendingApplications: 0,
  pendingLeaves: 0,
  recentResignations: 0,
});

const pendingApplications = ref([]);
const pendingLeaveRequests = ref([]);
const recentResignations = ref([]);

const resumeData = ref({
  first_name: "",
  middle_name: "",
  last_name: "",
  date_of_birth: null,
  gender: "",
  email: "",
  phone: "",
  address: "",
  position_applied: "",
  department_preference: "",
  expected_salary: "",
  availability_date: "",
  resume_file: null,
  notes: "",
});

const filePreview = ref(null);
const filePreviewType = ref(null);
const departments = ref([]);

const resumeForm = ref(null);

const GENDERS = [
  { title: "Male", value: "male" },
  { title: "Female", value: "female" },
];

const rules = {
  required: (value) => !!value || "This field is required",
  email: (value) => /.+@.+\..+/.test(value) || "Email must be valid",
};

const currentMonth = computed(() => {
  return new Date().toLocaleDateString("en-US", {
    month: "long",
    year: "numeric",
  });
});

const currentMonthYear = computed(() => {
  return new Date().toLocaleDateString("en-US", {
    month: "long",
    day: "numeric",
    year: "numeric",
  });
});

const fullName = computed(() => {
  return (
    employee.value?.full_name ||
    authStore.user?.name ||
    authStore.user?.username ||
    "HR Staff"
  );
});

const presentDays = computed(() => {
  return attendanceSummary.value?.present || 0;
});

const absentDays = computed(() => {
  return attendanceSummary.value?.absent || 0;
});

const lateDays = computed(() => {
  return attendanceSummary.value?.late || 0;
});

const totalHours = computed(() => {
  const hours = attendanceSummary.value?.total_hours || 0;
  return typeof hours === "number" ? hours.toFixed(0) : "0";
});

const totalDays = computed(() => {
  return presentDays.value + absentDays.value;
});

const latestPayslip = computed(() => {
  const netPay = currentPayslip.value?.net_pay;
  if (!netPay) return 0;
  return typeof netPay === "number" ? netPay : parseFloat(netPay) || 0;
});

const totalPendingItems = computed(() => {
  return (
    hrStats.value.pendingApplications +
    hrStats.value.pendingLeaves +
    hrStats.value.recentResignations
  );
});

const attendanceRate = computed(() => {
  if (!attendanceSummary.value) return 0;
  const present = attendanceSummary.value.present || 0;
  const total = totalDays.value || 1;
  return Math.round((present / total) * 100);
});

const userAvatar = computed(() => {
  if (!authStore.user?.avatar) return null;
  const avatar = authStore.user.avatar;
  // If avatar is already a full URL, return it
  if (avatar.startsWith("http")) return avatar;
  // Otherwise, prepend the base URL (remove /api from VITE_API_URL)
  const apiUrl = (
    import.meta.env.VITE_API_URL || "http://localhost:8000/api"
  ).replace("/api", "");
  return `${apiUrl}/storage/${avatar}`;
});

const attendanceHeaders = [
  { title: "Date", key: "attendance_date", sortable: true },
  { title: "Status", key: "status", sortable: true },
  { title: "Time In", key: "time_in" },
  { title: "Time Out", key: "time_out" },
  { title: "Hours", key: "regular_hours" },
];

const applicationHeaders = [
  { title: "Name", key: "name", sortable: true },
  { title: "Position", key: "position", sortable: true },
  { title: "Department", key: "project", sortable: true },
  { title: "Submitted", key: "created_at", sortable: true },
  { title: "Actions", key: "actions", sortable: false },
];

const leaveHeaders = [
  { title: "Employee", key: "employee", sortable: true },
  { title: "Leave Type", key: "leave_type", sortable: true },
  { title: "Dates", key: "dates", sortable: false },
  { title: "Duration", key: "total_days", sortable: true },
  { title: "Actions", key: "actions", sortable: false },
];

const resumeHeaders = [
  { title: "Applicant Name", key: "full_name", sortable: true },
  { title: "Email", key: "email", sortable: true },
  { title: "Position", key: "position_applied", sortable: true },
  { title: "Status", key: "status", sortable: true },
  { title: "Submitted", key: "created_at", sortable: true },
  { title: "Actions", key: "actions", sortable: false },
];

onMounted(async () => {
  await fetchDashboardData();
  await fetchMyResumes();
  await fetchDepartments();
  await loadPositionRates();
});

async function fetchDashboardData() {
  try {
    const response = await api.get("/employee/dashboard");

    employee.value = response.data.employee;
    attendanceSummary.value = response.data.attendance_summary || {
      present: 0,
      absent: 0,
      late: 0,
      total_hours: 0,
    };
    attendanceRecords.value = response.data.attendance || [];
    currentPayslip.value = response.data.current_payslip;
    payslipHistory.value = response.data.payslip_history || [];

    // Fetch HR-specific data
    await fetchHRStats();
    await fetchPendingApplications();
    await fetchPendingLeaves();
    await fetchRecentResignations();
  } catch (error) {
    devLog.error("Error loading dashboard:", error);
    toast.error("Failed to load dashboard data");
  }
}

async function fetchHRStats() {
  try {
    // Get total employees count from meta or fetch all
    const employeesResponse = await api.get("/employees", {
      params: { per_page: 9999 }, // Get all employees
    });
    hrStats.value.totalEmployees =
      employeesResponse.data.meta?.total ||
      employeesResponse.data.data?.length ||
      employeesResponse.data.length ||
      0;

    // Get pending applications count
    const applicationsResponse = await api.get("/employee-applications", {
      params: { status: "pending" },
    });
    hrStats.value.pendingApplications =
      applicationsResponse.data.data?.length ||
      applicationsResponse.data.length ||
      0;

    // Get pending leaves count
    const leavesResponse = await api.get("/employee-applications", {
      params: { type: "leave", status: "pending" },
    });
    hrStats.value.pendingLeaves =
      leavesResponse.data.data?.filter((app) => app.type === "leave").length ||
      0;

    // Get recent resignations (last 30 days)
    const thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
    const startDate = thirtyDaysAgo.toISOString().split("T")[0];

    const resignationsResponse = await api.get("/resignations", {
      params: {
        start_date: startDate,
        per_page: 100, // Get all within date range
      },
    });
    hrStats.value.recentResignations =
      resignationsResponse.data.data?.length ||
      resignationsResponse.data.length ||
      0;
  } catch (error) {
    devLog.error("Error loading HR stats:", error);
    // Don't show error toast, just log it
  }
}

async function fetchPendingApplications() {
  try {
    const response = await api.get("/employee-applications", {
      params: { status: "pending", limit: 10 },
    });
    pendingApplications.value = response.data.data || response.data || [];
  } catch (error) {
    devLog.error("Error loading pending applications:", error);
  }
}

async function fetchPendingLeaves() {
  try {
    const response = await api.get("/employee-applications", {
      params: { type: "leave", status: "pending", limit: 10 },
    });
    const allApplications = response.data.data || response.data || [];
    pendingLeaveRequests.value = allApplications.filter(
      (app) => app.type === "leave",
    );
  } catch (error) {
    devLog.error("Error loading pending leaves:", error);
  }
}

async function fetchRecentResignations() {
  try {
    const thirtyDaysAgo = new Date();
    thirtyDaysAgo.setDate(thirtyDaysAgo.getDate() - 30);
    const startDate = thirtyDaysAgo.toISOString().split("T")[0];

    const response = await api.get("/resignations", {
      params: {
        start_date: startDate,
        per_page: 10,
      },
    });
    recentResignations.value = response.data.data || response.data || [];
  } catch (error) {
    devLog.error("Error loading recent resignations:", error);
  }
}

async function loadDashboardData() {
  refreshing.value = true;
  try {
    await fetchDashboardData();
  } finally {
    refreshing.value = false;
  }
}

async function downloadPayslip(payslipId, format) {
  downloading.value = true;
  try {
    // Find the payslip in history or current
    let payslip =
      currentPayslip.value?.id === payslipId
        ? currentPayslip.value
        : payslipHistory.value.find((p) => p.id === payslipId);

    if (!payslip) {
      toast.error("Payslip not found");
      return;
    }

    const response = await api.get(
      `/payrolls/${payslip.payroll_id}/employees/${payslip.employee_id}/download-payslip`,
      {
        params: { format: format === "excel" ? "excel" : "pdf" },
        responseType: "blob",
      },
    );

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute(
      "download",
      `payslip_${payslip.employee_id}_${format === "pdf" ? ".pdf" : ".xlsx"}`,
    );
    document.body.appendChild(link);
    link.click();
    link.remove();

    toast.success(`Payslip downloaded successfully`);
  } catch (error) {
    devLog.error("Error downloading payslip:", error);
    toast.error("Failed to download payslip");
  } finally {
    downloading.value = false;
  }
}

function downloadCurrentPayslip() {
  if (currentPayslip.value) {
    downloadPayslip(currentPayslip.value.id, "pdf");
  } else {
    toast.warning("No current payslip available");
  }
}

function getStatusColor(status) {
  const colors = {
    present: "success",
    absent: "error",
    late: "warning",
    undertime: "warning",
    holiday: "info",
  };
  return colors[status] || "grey";
}

function getInitials(firstName, lastName) {
  const first = firstName ? firstName.charAt(0).toUpperCase() : "";
  const last = lastName ? lastName.charAt(0).toUpperCase() : "";
  return first + last || "?";
}

function getResignationStatusColor(status) {
  const colors = {
    pending: "warning",
    approved: "success",
    rejected: "error",
    completed: "info",
  };
  return colors[status] || "grey";
}

function viewApplication(application) {
  router.push(`/resume-review/${application.id}`);
}

function viewLeaveRequest(leave) {
  router.push(`/leave-approval/${leave.id}`);
}

function viewResignation(resignation) {
  router.push(`/resignations/${resignation.id}`);
}

function calculateHours(record) {
  if (
    record.regular_hours &&
    typeof record.regular_hours === "number" &&
    record.regular_hours > 0
  ) {
    return record.regular_hours;
  }

  if (record.time_in && record.time_out) {
    try {
      const timeIn = new Date(`2000-01-01 ${record.time_in}`);
      const timeOut = new Date(`2000-01-01 ${record.time_out}`);
      const hours = (timeOut - timeIn) / (1000 * 60 * 60);
      return hours > 0 ? hours : 0;
    } catch (error) {
      devLog.error("Error calculating hours:", error);
      return 0;
    }
  }

  return 0;
}

function formatHoursDisplay(hours) {
  if (!hours || hours <= 0) return "0h 0m";

  const totalMinutes = Math.round(hours * 60);
  const hrs = Math.floor(totalMinutes / 60);
  const mins = totalMinutes % 60;

  if (hrs === 0) {
    return `${mins}m`;
  } else if (mins === 0) {
    return `${hrs}h`;
  } else {
    return `${hrs}h ${mins}m`;
  }
}

async function fetchMyResumes() {
  loadingResumes.value = true;
  try {
    const response = await resumeService.getMyResumes();
    myResumes.value = response.data || response;
  } catch (error) {
    devLog.error("Error loading resumes:", error);
    toast.error("Failed to load resumes");
  } finally {
    loadingResumes.value = false;
  }
}

async function submitResume() {
  const { valid } = await resumeForm.value.validate();
  if (!valid) {
    toast.warning("Please fill in all required fields");
    return;
  }

  if (!resumeData.value.resume_file) {
    toast.warning("Please select a resume file");
    return;
  }

  uploading.value = true;
  try {
    const formData = new FormData();
    formData.append("first_name", resumeData.value.first_name);
    formData.append("middle_name", resumeData.value.middle_name || "");
    formData.append("last_name", resumeData.value.last_name);
    formData.append("date_of_birth", resumeData.value.date_of_birth || "");
    formData.append("gender", resumeData.value.gender || "");
    formData.append("email", resumeData.value.email);
    formData.append("phone", resumeData.value.phone);
    formData.append("address", resumeData.value.address || "");
    formData.append("position_applied", resumeData.value.position_applied);
    formData.append(
      "department_preference",
      resumeData.value.department_preference || "",
    );
    formData.append("expected_salary", resumeData.value.expected_salary || "");
    formData.append(
      "availability_date",
      resumeData.value.availability_date || "",
    );
    formData.append("notes", resumeData.value.notes || "");

    // Handle file input - v-file-input returns array
    const file = Array.isArray(resumeData.value.resume_file)
      ? resumeData.value.resume_file[0]
      : resumeData.value.resume_file;
    // Backend expects 'resume' not 'resume_file'
    formData.append("resume", file);

    await resumeService.uploadResume(formData);
    toast.success(
      "Application submitted successfully! Waiting for admin review.",
    );
    closeResumeDialog();
    await fetchMyResumes();
    await fetchHRStats();
  } catch (error) {
    devLog.error("Error uploading resume:", error);
    toast.error(
      error.response?.data?.message || "Failed to submit application",
    );
  } finally {
    uploading.value = false;
  }
}

function handleFileChange(files) {
  // Clean up previous PDF blob URL to prevent memory leaks
  if (filePreview.value && filePreviewType.value === "pdf") {
    URL.revokeObjectURL(filePreview.value);
  }

  filePreview.value = null;
  filePreviewType.value = null;

  if (!files || files.length === 0) return;

  const file = Array.isArray(files) ? files[0] : files;

  if (file.type.startsWith("image/")) {
    // Image preview
    const reader = new FileReader();
    reader.onload = (e) => {
      filePreview.value = e.target.result;
      filePreviewType.value = "image";
    };
    reader.readAsDataURL(file);
  } else if (file.type === "application/pdf") {
    // PDF preview
    const fileURL = URL.createObjectURL(file);
    filePreview.value = fileURL;
    filePreviewType.value = "pdf";
  }
}

async function downloadResume(resumeId) {
  try {
    const response = await resumeService.downloadResume(resumeId);
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute("download", `resume_${resumeId}.pdf`);
    document.body.appendChild(link);
    link.click();
    link.remove();
    toast.success("Resume downloaded successfully");
  } catch (error) {
    devLog.error("Error downloading resume:", error);
    toast.error("Failed to download resume");
  }
}

async function deleteResume(resumeId) {
  if (!(await confirmDialog("Are you sure you want to delete this resume?"))) {
    return;
  }

  try {
    await resumeService.deleteResume(resumeId);
    toast.success("Resume deleted successfully");
    await fetchMyResumes();
  } catch (error) {
    devLog.error("Error deleting resume:", error);
    toast.error("Failed to delete resume");
  }
}

async function fetchDepartments() {
  try {
    const response = await api.get("/projects");
    departments.value = response.data || [];
  } catch (error) {
    devLog.error("Error loading departments:", error);
  }
}

function closeResumeDialog() {
  showUploadResumeDialog.value = false;

  // Clean up PDF blob URL to prevent memory leaks
  if (filePreview.value && filePreviewType.value === "pdf") {
    URL.revokeObjectURL(filePreview.value);
  }

  filePreview.value = null;
  filePreviewType.value = null;
  resumeData.value = {
    first_name: "",
    middle_name: "",
    last_name: "",
    date_of_birth: null,
    gender: "",
    email: "",
    phone: "",
    address: "",
    position_applied: "",
    department_preference: "",
    expected_salary: "",
    availability_date: "",
    resume_file: null,
    notes: "",
  };
  resumeForm.value?.reset();
}

function getResumeStatusColor(status) {
  const colors = {
    pending: "warning",
    approved: "success",
    rejected: "error",
  };
  return colors[status] || "grey";
}
</script>

<style scoped lang="scss">
.modern-dashboard {
  max-width: 1600px;
  margin: 0 auto;
  padding: 0 8px;
}

.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  min-height: 400px;
  gap: 16px;

  .loading-text {
    color: rgba(0, 31, 61, 0.6);
    font-size: 15px;
  }
}

// Merged Header with Employee Info
.dashboard-header-merged {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 32px;
  padding: 28px;
  background: linear-gradient(135deg, #001f3d 0%, #0a2f4d 100%);
  border-radius: 20px;
  box-shadow: 0 4px 16px rgba(0, 31, 61, 0.15);

  @media (max-width: 960px) {
    flex-direction: column;
    gap: 20px;
    align-items: flex-start;
  }

  &.hr-gradient {
    background: linear-gradient(135deg, #001f3d 0%, #0a2f4d 100%);
  }
}

.header-content {
  display: flex;
  align-items: center;
  gap: 24px;
  flex: 1;

  @media (max-width: 960px) {
    width: 100%;
  }
}

.header-avatar {
  flex-shrink: 0;
  border: 3px solid rgba(255, 255, 255, 0.2);
}

.header-info {
  flex: 1;
  min-width: 0;
}

.welcome-badge {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 6px 14px;
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(10px);
  border-radius: 20px;
  font-size: 12px;
  font-weight: 600;
  color: #ffffff;
  text-transform: uppercase;
  letter-spacing: 0.5px;

  .welcome-icon {
    color: #ed985f;
  }

  &.hr-badge {
    .welcome-icon {
      color: #ed985f;
    }
  }
}

.dashboard-title {
  font-size: 28px;
  font-weight: 700;
  color: #ffffff;
  margin: 8px 0 6px 0;
  letter-spacing: -0.5px;
}

.dashboard-subtitle {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.85);
  margin: 0 0 4px 0;
}

.dashboard-date {
  font-size: 13px;
  color: rgba(255, 255, 255, 0.6);
  margin: 0;
}

.header-actions {
  display: flex;
  gap: 12px;
  align-items: center;

  @media (max-width: 960px) {
    width: 100%;
    justify-content: flex-end;
  }
}

.refresh-btn {
  color: white !important;
  text-transform: none;
  letter-spacing: 0;
}

// Modern Statistics Cards
.stats-row {
  margin-bottom: 32px;
}

.stat-card-new {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px;
  background: #ffffff;
  border-radius: 12px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  overflow: hidden;
  height: 100%;

  &::before {
    content: "";
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: linear-gradient(180deg, #ed985f 0%, #f7b980 100%);
    transform: scaleY(0);
    transition: transform 0.3s ease;
  }

  &:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(237, 152, 95, 0.2);
    border-color: rgba(237, 152, 95, 0.3);

    &::before {
      transform: scaleY(1);
    }

    .stat-arrow {
      transform: translateX(4px);
      opacity: 1;
    }
  }
}

.stat-icon-wrapper {
  flex-shrink: 0;
}

.stat-icon-circle {
  width: 48px;
  height: 48px;
  border-radius: 10px;
  background: linear-gradient(
    135deg,
    rgba(237, 152, 95, 0.12) 0%,
    rgba(247, 185, 128, 0.08) 100%
  );
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;

  .v-icon {
    color: #ed985f !important;
  }
}

// All stat cards use the same light background style
// No need for individual overrides

.stat-icon-pulse-hr {
  animation: pulse-glow 2s infinite;
}

@keyframes pulse-glow {
  0%,
  100% {
    box-shadow: 0 0 0 0 rgba(237, 152, 95, 0.4);
  }
  50% {
    box-shadow: 0 0 0 8px rgba(237, 152, 95, 0);
  }
}

.stat-content {
  flex: 1;
  min-width: 0;
}

.stat-label {
  font-size: 11px;
  font-weight: 600;
  color: rgba(0, 31, 61, 0.6);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 6px;
}

.stat-value {
  font-size: 24px;
  font-weight: 700;
  color: #001f3d;
  line-height: 1;
  margin-bottom: 4px;
}

.stat-meta {
  font-size: 12px;
  color: rgba(0, 31, 61, 0.5);
}

.stat-arrow {
  opacity: 0;
  transform: translateX(0);
  transition: all 0.3s ease;
  color: #ed985f;
}

// Section Headers
.section-header-compact {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 16px;
  padding: 0 8px;
}

.section-icon-badge {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: linear-gradient(
    135deg,
    rgba(237, 152, 95, 0.12) 0%,
    rgba(247, 185, 128, 0.08) 100%
  );
  display: flex;
  align-items: center;
  justify-content: center;

  &.success {
    background: linear-gradient(
      135deg,
      rgba(16, 185, 129, 0.12) 0%,
      rgba(16, 185, 129, 0.08) 100%
    );

    .v-icon {
      color: #10b981 !important;
    }
  }

  &.warning {
    background: linear-gradient(
      135deg,
      rgba(255, 152, 0, 0.12) 0%,
      rgba(255, 111, 0, 0.08) 100%
    );

    .v-icon {
      color: #ff9800 !important;
    }
  }

  &.info {
    background: linear-gradient(
      135deg,
      rgba(33, 150, 243, 0.12) 0%,
      rgba(21, 101, 192, 0.08) 100%
    );

    .v-icon {
      color: #2196f3 !important;
    }
  }

  &.danger {
    background: linear-gradient(
      135deg,
      rgba(244, 67, 54, 0.12) 0%,
      rgba(198, 40, 40, 0.08) 100%
    );

    .v-icon {
      color: #f44336 !important;
    }
  }

  .v-icon {
    color: #ed985f !important;
  }
}

.section-title-compact {
  font-size: 18px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
  letter-spacing: -0.3px;
}

// Content Cards
.content-card {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  padding: 24px;
}

// Tables
.modern-table {
  :deep(.v-data-table__wrapper) {
    border-radius: 12px;
    overflow: hidden;
  }

  :deep(.v-data-table-header) {
    background: rgba(0, 31, 61, 0.02);

    th {
      font-weight: 600 !important;
      font-size: 13px !important;
      color: rgba(0, 31, 61, 0.7) !important;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }
  }

  :deep(tbody tr) {
    transition: background 0.2s ease;

    &:hover {
      background: #f8f9fa;
    }
  }
}

.table-date {
  font-weight: 500;
  color: #001f3d;
}

.table-time {
  font-family: "Courier New", monospace;
  font-size: 14px;
  color: rgba(0, 31, 61, 0.8);
}

.table-hours {
  font-weight: 600;
  color: #ed985f;
}

.table-text {
  font-weight: 500;
  color: rgba(0, 31, 61, 0.8);
}

.name-cell {
  display: flex;
  align-items: center;
  gap: 8px;
}

// Payslip Details
.payslip-details {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.payslip-detail-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.detail-label {
  font-size: 13px;
  color: rgba(0, 31, 61, 0.6);
  font-weight: 500;
}

.detail-value {
  font-size: 14px;
  color: #001f3d;
  font-weight: 600;

  &.amount-positive {
    color: #43e97b;
  }

  &.amount-negative {
    color: #f5576c;
  }
}

.payslip-detail-divider {
  height: 1px;
  background: rgba(0, 31, 61, 0.1);
}

.payslip-detail-divider-thick {
  height: 2px;
  background: rgba(0, 31, 61, 0.15);
}

.payslip-detail-item-main {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
  border-radius: 12px;
}

.detail-label-main {
  font-size: 14px;
  color: rgba(0, 31, 61, 0.7);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.detail-value-main {
  font-size: 24px;
  color: #001f3d;
  font-weight: 700;
}

.payslip-actions {
  display: flex;
  gap: 12px;
  margin-top: 8px;
}

.payslip-action-btn {
  flex: 1;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;

  &.primary {
    background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
    color: white;

    &:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(237, 152, 95, 0.4);
    }
  }

  &.secondary {
    background: #f8f9fa;
    color: #001f3d;

    &:hover {
      background: #e9ecef;
      transform: translateY(-2px);
    }
  }
}

// Payslip History
.payslip-history-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.payslip-history-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 16px;
  background: #f8f9fa;
  border-radius: 12px;
  transition: all 0.3s ease;

  &:hover {
    background: #e9ecef;
    transform: translateX(4px);
  }
}

.payslip-history-info {
  flex: 1;
}

.payslip-history-period {
  display: flex;
  align-items: center;
  font-size: 13px;
  color: rgba(0, 31, 61, 0.7);
  margin-bottom: 4px;
}

.payslip-history-amount {
  font-size: 18px;
  font-weight: 700;
  color: #001f3d;
}

.payslip-history-actions {
  display: flex;
  gap: 8px;
}

.payslip-action-btn-small {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  background: white;
  border: 1px solid rgba(0, 31, 61, 0.1);
  border-radius: 8px;
  font-size: 12px;
  font-weight: 600;
  color: #001f3d;
  cursor: pointer;
  transition: all 0.3s ease;

  &:hover {
    background: #001f3d;
    color: white;
    border-color: #001f3d;
  }
}

// Quick Actions
.quick-actions-section {
  margin-bottom: 24px;
}

.quick-action-buttons {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.quick-action-btn {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 16px;
  background: white;
  border: 1px solid rgba(0, 31, 61, 0.08);
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  cursor: pointer;
  transition: all 0.3s ease;
  text-align: left;
  font-size: 14px;
  font-weight: 600;
  color: #001f3d;

  &:hover {
    transform: translateX(4px);
    box-shadow: 0 4px 12px rgba(237, 152, 95, 0.15);
    border-color: rgba(237, 152, 95, 0.2);
  }

  &.highlight {
    background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
    color: white;
    border-color: transparent;

    .quick-action-icon {
      background: rgba(255, 255, 255, 0.2);

      .v-icon {
        color: white !important;
      }
    }
  }
}

.quick-action-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: linear-gradient(
    135deg,
    rgba(237, 152, 95, 0.12) 0%,
    rgba(247, 185, 128, 0.08) 100%
  );
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;

  .v-icon {
    color: #ed985f !important;
  }
}

// Empty State
.empty-state-small {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
  gap: 12px;
}

.empty-state-text {
  font-size: 14px;
  color: rgba(0, 31, 61, 0.5);
  font-weight: 500;
}

// Action Section (matching admin dashboard)
.action-section {
  .section-header {
    margin-bottom: 20px;
  }

  .section-title-wrapper {
    display: flex;
    align-items: center;
    gap: 12px;
  }

  .section-title {
    font-size: 20px;
    font-weight: 700;
    color: #001f3d;
    margin: 0;
  }
}

.action-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 16px;
}

.action-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px;
  background: white;
  border-radius: 16px;
  box-shadow: 0 2px 8px rgba(0, 31, 61, 0.08);
  cursor: pointer;
  transition: all 0.3s ease;
  border: 1px solid rgba(0, 31, 61, 0.08);

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 24px rgba(237, 152, 95, 0.15);
    border-color: rgba(237, 152, 95, 0.2);
  }
}

.action-item-icon {
  width: 48px;
  height: 48px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;

  &.action-icon-warning {
    background: linear-gradient(
      135deg,
      rgba(255, 152, 0, 0.12) 0%,
      rgba(255, 111, 0, 0.08) 100%
    );

    .v-icon {
      color: #ff9800 !important;
    }
  }

  &.action-icon-info {
    background: linear-gradient(
      135deg,
      rgba(33, 150, 243, 0.12) 0%,
      rgba(21, 101, 192, 0.08) 100%
    );

    .v-icon {
      color: #2196f3 !important;
    }
  }

  &.action-icon-success {
    background: linear-gradient(
      135deg,
      rgba(76, 175, 80, 0.12) 0%,
      rgba(46, 125, 50, 0.08) 100%
    );

    .v-icon {
      color: #4caf50 !important;
    }
  }

  &.action-icon-primary {
    background: linear-gradient(
      135deg,
      rgba(237, 152, 95, 0.12) 0%,
      rgba(247, 185, 128, 0.08) 100%
    );

    .v-icon {
      color: #ed985f !important;
    }
  }

  &.action-icon-danger {
    background: linear-gradient(
      135deg,
      rgba(244, 67, 54, 0.12) 0%,
      rgba(198, 40, 40, 0.08) 100%
    );

    .v-icon {
      color: #f44336 !important;
    }
  }
}

.action-item-content {
  flex: 1;
  min-width: 0;
}

.action-item-title {
  font-size: 15px;
  font-weight: 600;
  color: #001f3d;
  margin-bottom: 4px;
}

.action-item-desc {
  font-size: 13px;
  color: rgba(0, 31, 61, 0.6);
}

.action-item-badge {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: #f8f9fa;
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 13px;
  font-weight: 700;
  color: #001f3d;
  flex-shrink: 0;
}

.no-actions-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 64px 24px;
  gap: 16px;
  background: white;
  border-radius: 16px;
  box-shadow: 0 2px 8px rgba(0, 31, 61, 0.08);
}

.no-actions-icon {
  opacity: 0.5;
}

.no-actions-title {
  font-size: 20px;
  font-weight: 700;
  color: #001f3d;
}

.no-actions-desc {
  font-size: 14px;
  color: rgba(0, 31, 61, 0.6);
}

// System Health Section (matching admin dashboard)
.system-health-section {
  margin-bottom: 24px;
}

.health-metrics {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.health-metric {
  background: white;
  border-radius: 12px;
  padding: 16px;
  box-shadow: 0 2px 8px rgba(0, 31, 61, 0.08);
}

.metric-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 12px;
}

.metric-label {
  font-size: 13px;
  color: rgba(0, 31, 61, 0.6);
  font-weight: 500;
}

.metric-value {
  font-size: 16px;
  font-weight: 700;
  color: #001f3d;
}

.metric-progress {
  height: 8px;
  background: #f8f9fa;
  border-radius: 4px;
  overflow: hidden;
}

.metric-progress-bar {
  height: 100%;
  background: linear-gradient(90deg, #ed985f 0%, #f7b980 100%);
  border-radius: 4px;
  transition: width 0.5s ease;
}

.metric-info {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.metric-info-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.metric-icon {
  color: #ed985f;
}

.metric-info-label {
  font-size: 13px;
  color: rgba(0, 31, 61, 0.7);
  font-weight: 500;
}

.metric-badge {
  padding: 6px 12px;
  background: #f8f9fa;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 700;
  color: #001f3d;
}

.metric-text {
  font-size: 13px;
  color: rgba(0, 31, 61, 0.7);
  font-weight: 500;
}

// Modern Dialog Styles (matching AddEmployeeDialog)
.modern-dialog {
  border-radius: 16px;
  overflow: hidden;
}

.dialog-header {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 20px 24px;
  background: linear-gradient(
    135deg,
    rgba(0, 31, 61, 0.02) 0%,
    rgba(237, 152, 95, 0.02) 100%
  );
  border-bottom: 1px solid rgba(0, 31, 61, 0.08);
}

.dialog-icon-wrapper {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.25);

  &.primary {
    background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  }
}

.dialog-title {
  font-size: 20px;
  font-weight: 700;
  color: #001f3d;
  line-height: 1.2;
  letter-spacing: -0.3px;
}

.dialog-subtitle {
  font-size: 13px;
  color: rgba(0, 31, 61, 0.6);
  margin-top: 4px;
}

.dialog-content {
  padding: 24px;
}

.section-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 20px;
  background: linear-gradient(
    135deg,
    rgba(0, 31, 61, 0.02) 0%,
    rgba(237, 152, 95, 0.02) 100%
  );
  border-radius: 12px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  margin-bottom: 16px;
}

.section-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.25);
}

.section-title {
  font-size: 16px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
  letter-spacing: -0.3px;
}

.dialog-actions {
  padding: 16px 24px;
  background: rgba(0, 31, 61, 0.02);
}

.dialog-btn {
  padding: 10px 24px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;

  &:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
}

.dialog-btn-cancel {
  background: transparent;
  color: #64748b;

  &:hover:not(:disabled) {
    background: rgba(0, 31, 61, 0.04);
  }
}

.dialog-btn-primary {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: white;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);
  margin-left: 12px;

  &:hover:not(:disabled) {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(237, 152, 95, 0.4);
  }
}

// File Preview Styles
.file-preview-card {
  margin-top: 16px;
  border-radius: 12px;
  overflow: hidden;
}

.image-preview-container {
  padding: 20px;
  background: #f8f9fa;
  display: flex;
  justify-content: center;
  align-items: center;
}

.preview-image {
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.pdf-preview-container {
  height: 500px;
  background: #f8f9fa;
}

.pdf-preview {
  width: 100%;
  height: 100%;
}
</style>
