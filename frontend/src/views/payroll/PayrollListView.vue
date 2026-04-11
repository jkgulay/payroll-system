<template>
  <div class="payroll-page">
    <!-- Modern Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="page-title-section">
          <div class="page-icon-badge">
            <v-icon size="22">mdi-cash-multiple</v-icon>
          </div>
          <div>
            <h1 class="page-title">Payroll Management</h1>
            <p class="page-subtitle">
              Create, manage and process payroll for all employees
            </p>
          </div>
        </div>
        <div class="action-buttons">
          <button
            class="action-btn action-btn-secondary"
            @click="openSignatureSettings"
          >
            <v-icon size="20">mdi-cog-outline</v-icon>
            <span>Signature Settings</span>
          </button>
          <button
            class="action-btn action-btn-primary"
            @click="openCreateDialog"
          >
            <v-icon size="20">mdi-plus</v-icon>
            <span>Create Payroll</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Modern Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon total">
          <v-icon size="20">mdi-cash-multiple</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Total Payrolls</div>
          <div class="stat-value">{{ stats.total }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon draft">
          <v-icon size="20">mdi-file-edit-outline</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Draft</div>
          <div class="stat-value warning">{{ stats.draft }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon finalized">
          <v-icon size="20">mdi-file-check-outline</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Finalized</div>
          <div class="stat-value info">{{ stats.finalized }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon paid">
          <v-icon size="20">mdi-check-circle</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Paid</div>
          <div class="stat-value success">{{ stats.paid }}</div>
        </div>
      </div>
    </div>

    <!-- Payroll List -->
    <div class="modern-card">
      <div class="filters-section">
        <v-row align="center" class="mb-0">
          <v-col cols="12" md="4">
            <v-text-field
              v-model="search"
              prepend-inner-icon="mdi-magnify"
              label="Search payroll..."
              clearable
              variant="outlined"
              density="comfortable"
              hide-details
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="statusFilter"
              :items="statusFilterOptions"
              item-title="title"
              item-value="value"
              label="Filter by status"
              variant="outlined"
              density="comfortable"
              hide-details
            ></v-select>
          </v-col>
          <v-col cols="auto">
            <v-btn
              variant="tonal"
              color="grey"
              prepend-icon="mdi-filter-remove"
              @click="clearTableFilters"
              :disabled="!hasActiveFilters"
            >
              Clear
            </v-btn>
          </v-col>
          <v-col cols="auto" v-if="hasActiveFilters">
            <v-chip size="small" color="info" variant="tonal">
              {{ activeFilterCount }} active filter{{
                activeFilterCount > 1 ? "s" : ""
              }}
            </v-chip>
          </v-col>
          <v-col cols="auto">
            <v-btn
              color="#ED985F"
              variant="tonal"
              icon="mdi-refresh"
              @click="fetchPayrolls"
              :loading="loading"
              title="Refresh"
            ></v-btn>
          </v-col>
        </v-row>
      </div>

      <div class="table-section">
        <v-data-table
          :headers="headers"
          :items="filteredPayrolls"
          :loading="loading"
          :items-per-page="15"
          class="elevation-1"
        >
          <!-- Status -->
          <template v-slot:item.status="{ item }">
            <v-chip
              :color="getStatusColor(item.status)"
              size="small"
              variant="flat"
            >
              {{ item.status.toUpperCase() }}
            </v-chip>
          </template>

          <!-- Period -->
          <template v-slot:item.period="{ item }">
            <div>
              <div class="font-weight-medium">{{ item.period_name }}</div>
              <div class="text-caption text-medium-emphasis">
                {{ formatDate(item.period_start) }} -
                {{ formatDate(item.period_end) }}
              </div>
            </div>
          </template>

          <!-- Payment Date -->
          <template v-slot:item.payment_date="{ item }">
            {{ formatDate(item.payment_date) }}
          </template>

          <!-- Employees Count -->
          <template v-slot:item.items_count="{ item }">
            <v-chip size="small" variant="outlined">
              {{ item.items_count }} employees
            </v-chip>
          </template>

          <!-- Total Net -->
          <template v-slot:item.total_net="{ item }">
            <div class="text-right font-weight-bold">
              ₱{{ formatCurrency(item.total_net) }}
            </div>
          </template>

          <!-- Actions -->
          <template v-slot:item.actions="{ item }">
            <v-menu>
              <template v-slot:activator="{ props }">
                <v-btn
                  icon="mdi-dots-vertical"
                  size="small"
                  variant="text"
                  v-bind="props"
                >
                </v-btn>
              </template>
              <v-list density="compact">
                <v-list-item @click="viewPayroll(item)">
                  <template v-slot:prepend>
                    <v-icon size="small" color="primary">mdi-eye</v-icon>
                  </template>
                  <v-list-item-title>View Details</v-list-item-title>
                </v-list-item>
                <v-list-item
                  :disabled="item.status !== 'draft'"
                  @click="editPayroll(item)"
                >
                  <template v-slot:prepend>
                    <v-icon
                      size="small"
                      :color="item.status === 'draft' ? 'warning' : 'grey'"
                    >mdi-pencil</v-icon>
                  </template>
                  <v-list-item-title>Edit</v-list-item-title>
                </v-list-item>
                <v-divider></v-divider>
                <v-list-item
                  :disabled="item.status !== 'draft'"
                  @click="confirmDelete(item)"
                >
                  <template v-slot:prepend>
                    <v-icon
                      size="small"
                      :color="item.status === 'draft' ? 'error' : 'grey'"
                    >mdi-delete</v-icon>
                  </template>
                  <v-list-item-title
                    :class="{ 'text-error': item.status === 'draft' }"
                  >Delete</v-list-item-title>
                </v-list-item>
              </v-list>
            </v-menu>
          </template>

          <template v-slot:no-data>
            <div class="text-center py-8">
              <v-icon size="54" color="grey">mdi-file-search-outline</v-icon>
              <p class="text-h6 mt-3 mb-1">No payroll records found</p>
              <p class="text-body-2 text-medium-emphasis mb-4">
                Try adjusting search or status filter.
              </p>
              <v-btn
                variant="outlined"
                color="primary"
                @click="clearTableFilters"
                :disabled="!hasActiveFilters"
              >
                Clear filters
              </v-btn>
            </div>
          </template>
        </v-data-table>
      </div>
    </div>

    <!-- Create/Edit Dialog - Modern UI -->
    <v-dialog
      v-model="dialog"
      max-width="880px"
      width="94vw"
      persistent
      scrollable
    >
      <v-card class="modern-dialog payroll-form-dialog">
        <!-- Enhanced Header -->
        <v-card-title class="dialog-header payroll-dialog-header">
          <div class="dialog-icon-wrapper primary">
            <v-icon size="24">{{
              editMode ? "mdi-pencil" : "mdi-cash-multiple"
            }}</v-icon>
          </div>
          <div>
            <div class="dialog-title">
              {{ editMode ? "Edit Payroll" : "Create Payroll" }}
            </div>
            <div class="dialog-subtitle">
              {{
                editMode
                  ? "Update payroll period details"
                  : "Generate new payroll period"
              }}
            </div>
          </div>
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text
          class="dialog-content payroll-dialog-content"
          style="max-height: 86vh"
          @keydown.capture="handlePayrollFormKeydown"
        >
          <v-form ref="form" v-model="valid" class="payroll-form">
            <v-alert type="info" variant="tonal" density="compact" class="mb-3">
              <template v-slot:prepend>
                <v-icon icon="mdi-lightbulb-on-outline"></v-icon>
              </template>
              <div class="text-caption">
                Fill in the payroll dates first, then choose optional filters
                and deductions.
              </div>
            </v-alert>

            <div class="wizard-steps mb-4">
              <button
                type="button"
                class="wizard-step"
                :class="{ active: currentStep === 1, done: currentStep > 1 }"
              >
                <span class="wizard-step-index">1</span>
                <span class="wizard-step-label">Basic Info</span>
              </button>
              <button
                type="button"
                class="wizard-step"
                :class="{ active: currentStep === 2, done: currentStep > 2 }"
              >
                <span class="wizard-step-index">2</span>
                <span class="wizard-step-label"
                  >Employees, Overtime & Review</span
                >
              </button>
              <button
                type="button"
                class="wizard-step"
                :class="{ active: currentStep === 3 }"
              >
                <span class="wizard-step-index">3</span>
                <span class="wizard-step-label">Deductions & Notes</span>
              </button>
            </div>

            <div v-show="currentStep === 1">
              <!-- Section 1: Basic Information -->
              <v-col cols="12" class="px-0">
                <div class="section-header">
                  <div class="section-icon">
                    <v-icon size="18">mdi-information</v-icon>
                  </div>
                  <h3 class="section-title">Basic Information</h3>
                </div>
              </v-col>

              <!-- Period Name -->
              <div class="form-field-wrapper mt-2">
                <label class="form-label">
                  <v-icon size="small" color="#ed985f">mdi-label</v-icon>
                  Period Name <span class="text-error">*</span>
                </label>
                <v-text-field
                  v-model="formData.period_name"
                  placeholder="Enter period name"
                  :rules="[(v) => !!v || 'Period name is required']"
                  hint="Example: March 1–15, 2026"
                  persistent-hint
                  required
                  variant="outlined"
                  density="compact"
                  prepend-inner-icon="mdi-label"
                  color="#ed985f"
                ></v-text-field>
              </div>

              <v-row class="payroll-dates-row">
                <!-- Period Start -->
                <v-col cols="12" sm="6">
                  <div class="form-field-wrapper">
                    <label class="form-label">
                      <v-icon size="small" color="#ed985f"
                        >mdi-calendar-start</v-icon
                      >
                      Period Start <span class="text-error">*</span>
                    </label>
                    <v-text-field
                      v-model="formData.period_start"
                      type="date"
                      placeholder="Select start date"
                      :rules="startDateRules"
                      required
                      variant="outlined"
                      density="compact"
                      prepend-inner-icon="mdi-calendar-start"
                      color="#ed985f"
                    ></v-text-field>
                  </div>
                </v-col>

                <!-- Period End -->
                <v-col cols="12" sm="6">
                  <div class="form-field-wrapper">
                    <label class="form-label">
                      <v-icon size="small" color="#ed985f"
                        >mdi-calendar-end</v-icon
                      >
                      Period End <span class="text-error">*</span>
                    </label>
                    <v-text-field
                      v-model="formData.period_end"
                      type="date"
                      placeholder="Select end date"
                      :rules="endDateRules"
                      required
                      variant="outlined"
                      density="compact"
                      prepend-inner-icon="mdi-calendar-end"
                      color="#ed985f"
                    ></v-text-field>
                  </div>
                </v-col>
              </v-row>

              <!-- Payment Date -->
              <div class="form-field-wrapper">
                <label class="form-label">
                  <v-icon size="small" color="#ed985f"
                    >mdi-calendar-check</v-icon
                  >
                  Payment Date <span class="text-error">*</span>
                </label>
                <v-text-field
                  v-model="formData.payment_date"
                  type="date"
                  placeholder="Select payment date"
                  :rules="paymentDateRules"
                  required
                  variant="outlined"
                  density="compact"
                  prepend-inner-icon="mdi-calendar-check"
                  color="#ed985f"
                ></v-text-field>
              </div>
            </div>

            <div v-show="currentStep === 2" class="step-two-workspace">
              <div class="step-two-panel step-two-panel--scope mt-4">
                <div class="section-header">
                  <div class="section-icon">
                    <v-icon size="18">mdi-account-group</v-icon>
                  </div>
                  <h3 class="section-title">Employees & Scope</h3>
                </div>

                <p class="panel-intro mt-3 mb-3">
                  Choose payroll scope and optional attendance constraints
                  before setting overtime and punch review.
                </p>

                <div class="scope-controls">
                  <v-btn-toggle
                    v-model="formData.payroll_scope"
                    color="primary"
                    variant="outlined"
                    divided
                    mandatory
                    class="scope-toggle"
                  >
                    <v-btn value="all" size="small">
                      <v-icon start>mdi-account-group</v-icon>
                      All Employees
                    </v-btn>
                    <v-btn value="individual" size="small">
                      <v-icon start>mdi-account-check</v-icon>
                      Individual Payroll
                    </v-btn>
                  </v-btn-toggle>

                  <v-checkbox
                    v-model="formData.has_attendance"
                    label="Only include employees with payable attendance"
                    prepend-icon="mdi-calendar-check"
                    hint="Includes only approved attendance with time-in and either time-out or half-day status within this payroll period"
                    persistent-hint
                    color="#ed985f"
                    density="compact"
                    class="payroll-checkbox"
                  ></v-checkbox>
                </div>

                <v-row
                  v-if="formData.payroll_scope === 'individual'"
                  class="mt-0"
                >
                  <v-col cols="12" md="5">
                    <v-select
                      v-model="formData.individual_target"
                      :items="individualTargetOptions"
                      item-title="title"
                      item-value="value"
                      label="Individual Payroll By"
                      variant="outlined"
                      density="compact"
                      prepend-inner-icon="mdi-filter"
                    ></v-select>
                  </v-col>

                  <v-col
                    cols="12"
                    md="7"
                    v-if="formData.individual_target === 'position'"
                  >
                    <v-autocomplete
                      v-model="formData.included_position"
                      :items="positionOptions"
                      label="Select Position *"
                      placeholder="Choose position"
                      prepend-inner-icon="mdi-briefcase"
                      clearable
                      variant="outlined"
                      density="compact"
                      :rules="[(v) => !!v || 'Position is required']"
                    ></v-autocomplete>
                  </v-col>

                  <v-col cols="12" md="7" v-else>
                    <v-autocomplete
                      v-model="formData.included_employee_id"
                      :items="employeeOptions"
                      :loading="employeeOptionsLoading"
                      item-title="full_name"
                      item-value="id"
                      label="Select Employee *"
                      placeholder="Search by name, employee number, or position"
                      prepend-inner-icon="mdi-account-search"
                      clearable
                      variant="outlined"
                      density="compact"
                      :custom-filter="customEmployeeFilter"
                      :rules="[(v) => !!v || 'Employee is required']"
                    >
                      <template v-slot:item="{ props, item }">
                        <v-list-item v-bind="props">
                          <template v-slot:title>
                            {{ item.raw.full_name }}
                          </template>
                          <template v-slot:subtitle>
                            {{ item.raw.employee_number }} -
                            {{ item.raw.position || "N/A" }}
                          </template>
                        </v-list-item>
                      </template>
                    </v-autocomplete>
                  </v-col>
                </v-row>

                <v-autocomplete
                  v-if="formData.payroll_scope === 'all'"
                  v-model="formData.excluded_positions"
                  :items="positionOptions"
                  label="Exclude Positions (Optional)"
                  placeholder="Select positions to exclude from this payroll"
                  prepend-inner-icon="mdi-account-remove"
                  multiple
                  chips
                  closable-chips
                  clearable
                  variant="outlined"
                  density="compact"
                  hint="Employees with selected positions will not be included in payroll generation"
                  persistent-hint
                ></v-autocomplete>
              </div>

              <div class="step-two-panel mt-4">
                <div class="section-header">
                  <div class="section-icon">
                    <v-icon size="18">mdi-clock-check-outline</v-icon>
                  </div>
                  <h3 class="section-title">Overtime Selection & Day View</h3>
                </div>

                <p class="panel-intro mt-3 mb-3">
                  Employees are loaded automatically from your payroll period
                  and scope. Select only who should be included in overtime
                  processing.
                </p>

                <div class="ot-summary mb-3">
                  <v-chip size="small" color="info" variant="tonal">
                    <template v-if="overtimeCandidatesLoading"
                      >Loading employees...</template
                    >
                    <template v-else
                      >{{ overtimeCandidates.length }} employee(s) found</template
                    >
                  </v-chip>
                  <v-chip size="small" color="success" variant="tonal">
                    {{ formData.overtime_employee_ids?.length || 0 }} selected
                  </v-chip>
                </div>

                <div class="ot-actions mb-3">
                  <v-btn
                    variant="outlined"
                    color="#ED985F"
                    prepend-icon="mdi-checkbox-marked-circle-outline"
                    :disabled="
                      !overtimeCandidatesLoaded || overtimeCandidates.length === 0
                    "
                    @click="selectEmployeesWithOvertimeRequest"
                    class="step-action-btn"
                  >
                    Add All With OT Request
                  </v-btn>
                  <v-btn
                    v-if="formData.overtime_employee_ids?.length"
                    variant="text"
                    color="grey"
                    prepend-icon="mdi-close-circle-outline"
                    @click="clearOvertimeSelection"
                    class="step-action-btn"
                  >
                    Clear Selected
                  </v-btn>
                </div>

                <v-progress-linear
                  v-if="overtimeCandidatesLoading"
                  color="#ED985F"
                  indeterminate
                  rounded
                  height="3"
                  class="mb-3"
                ></v-progress-linear>

                <v-autocomplete
                  v-model="formData.overtime_employee_ids"
                  :items="overtimeCandidates"
                  :loading="overtimeCandidatesLoading"
                  item-title="display_name"
                  item-value="id"
                  label="Include Overtime For (Optional)"
                  placeholder="If empty, overtime is included for all employees"
                  prepend-inner-icon="mdi-account-clock"
                  multiple
                  clearable
                  variant="outlined"
                  density="compact"
                  :menu-props="{ maxHeight: 340 }"
                  :disabled="!overtimeCandidatesLoaded && !overtimeCandidatesLoading"
                  class="mb-2"
                >
                  <template v-slot:selection="{ item, index }">
                    <v-chip
                      v-if="index < 2"
                      size="x-small"
                      color="#ED985F"
                      variant="tonal"
                      class="mr-1"
                    >
                      {{ item.title }}
                    </v-chip>
                    <span
                      v-else-if="index === 2"
                      class="text-caption text-medium-emphasis"
                    >
                      +{{ (formData.overtime_employee_ids?.length || 0) - 2 }}
                      more selected
                    </span>
                  </template>
                  <template v-slot:item="{ props, item }">
                    <v-list-item v-bind="props">
                      <template v-slot:title>
                        {{ item.raw.full_name }}
                      </template>
                      <template v-slot:subtitle>
                        {{ item.raw.employee_number }} -
                        {{ item.raw.position || "N/A" }}
                        <span v-if="item.raw.has_overtime_request">
                          · OT request found</span
                        >
                      </template>
                    </v-list-item>
                  </template>
                </v-autocomplete>

                <p
                  v-if="overtimeCandidatesLoaded && overtimeCandidates.length === 0"
                  class="text-caption text-medium-emphasis mb-2"
                >
                  No employees matched the current payroll scope and period.
                </p>

                <div class="day-view-toolbar mt-3 mb-2">
                  <v-select
                    v-model="overtimePreviewEmployeeId"
                    :items="selectedOvertimeEmployees"
                    item-title="display_name"
                    item-value="id"
                    label="View Daily Time For Selected Employee"
                    prepend-inner-icon="mdi-calendar-clock"
                    :disabled="selectedOvertimeEmployees.length === 0"
                    variant="outlined"
                    density="compact"
                    clearable
                    class="day-view-employee-select"
                  ></v-select>

                  <v-btn
                    variant="outlined"
                    color="#ED985F"
                    prepend-icon="mdi-table-eye"
                    :disabled="!overtimePreviewEmployeeId"
                    :loading="employeeAttendanceLoading"
                    @click="loadSelectedEmployeeAttendance"
                    class="day-view-load-btn"
                  >
                    Load Day View
                  </v-btn>
                </div>

                <div
                  v-if="selectedEmployeeAttendance.length > 0"
                  class="attendance-day-view mt-2"
                >
                  <div class="attendance-day-view-scroll">
                    <v-table density="compact">
                      <thead>
                        <tr>
                          <th>Date</th>
                          <th>Status</th>
                          <th>Time In</th>
                          <th>Time Out</th>
                          <th>OT In</th>
                          <th>OT Out</th>
                          <th>OT Hours</th>
                          <th></th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr
                          v-for="row in selectedEmployeeAttendance"
                          :key="row.id"
                        >
                          <td>{{ formatDate(row.attendance_date) }}</td>
                          <td>
                            <v-chip size="x-small" variant="tonal" color="info">
                              {{ row.status }}
                            </v-chip>
                          </td>
                          <td>
                            <v-text-field
                              v-model="row.time_in_input"
                              type="time"
                              step="60"
                              lang="en-GB"
                              class="day-time-input"
                              density="compact"
                              variant="outlined"
                              hide-details
                            ></v-text-field>
                          </td>
                          <td>
                            <v-text-field
                              v-model="row.time_out_input"
                              type="time"
                              step="60"
                              lang="en-GB"
                              class="day-time-input"
                              density="compact"
                              variant="outlined"
                              hide-details
                            ></v-text-field>
                          </td>
                          <td>
                            <v-text-field
                              v-model="row.ot_time_in_input"
                              type="time"
                              step="60"
                              lang="en-GB"
                              class="day-time-input"
                              density="compact"
                              variant="outlined"
                              hide-details
                            ></v-text-field>
                          </td>
                          <td>
                            <v-text-field
                              v-model="row.ot_time_out_input"
                              type="time"
                              step="60"
                              lang="en-GB"
                              class="day-time-input"
                              density="compact"
                              variant="outlined"
                              hide-details
                            ></v-text-field>
                          </td>
                          <td>{{ row.overtime_hours || 0 }}</td>
                          <td>
                            <v-btn
                              size="small"
                              color="#ED985F"
                              variant="tonal"
                              :loading="attendanceRowSaving[row.id]"
                              @click="saveAttendanceRow(row)"
                            >
                              Save
                            </v-btn>
                          </td>
                        </tr>
                      </tbody>
                    </v-table>
                  </div>
                </div>
              </div>

              <div class="step-two-panel mt-4">
                <div class="section-header">
                  <div class="section-icon">
                    <v-icon size="18">mdi-timeline-clock-outline</v-icon>
                  </div>
                  <h3 class="section-title">Punch Review (Payroll Period)</h3>
                </div>

                <p class="panel-intro mt-3 mb-3">
                  Filters auto-refresh this table. Use Refresh Now only when you
                  want an immediate manual reload.
                </p>

                <v-row class="mt-0 align-end punch-filter-grid">
                  <v-col cols="12" md="3">
                    <v-text-field
                      v-model="payrollPunchFilters.date"
                      label="Review Date"
                      type="date"
                      variant="outlined"
                      density="compact"
                      prepend-inner-icon="mdi-calendar"
                      :min="formData.period_start || undefined"
                      :max="formData.period_end || undefined"
                    ></v-text-field>
                  </v-col>
                  <v-col cols="12" md="6">
                    <v-autocomplete
                      v-model="payrollPunchFilters.employee_id"
                      :items="payrollPunchEmployees"
                      item-title="full_name"
                      item-value="id"
                      label="Employee"
                      placeholder="Filter by employee (optional)"
                      prepend-inner-icon="mdi-account-search"
                      variant="outlined"
                      density="compact"
                      clearable
                    >
                      <template v-slot:item="{ props, item }">
                        <v-list-item v-bind="props">
                          <template v-slot:subtitle>
                            {{ item.raw.employee_number || "No ID" }}
                          </template>
                        </v-list-item>
                      </template>
                    </v-autocomplete>
                  </v-col>
                  <v-col cols="12" md="3">
                    <div class="punch-filter-actions">
                      <v-btn
                        variant="outlined"
                        color="grey"
                        prepend-icon="mdi-filter-remove"
                        @click="clearPayrollPunchFilters"
                        class="step-action-btn"
                      >
                        Clear
                      </v-btn>
                      <v-btn
                        color="#ED985F"
                        prepend-icon="mdi-refresh"
                        :loading="payrollPunchLoading"
                        @click="loadPayrollPunchReview"
                        class="step-action-btn"
                      >
                        Refresh Now
                      </v-btn>
                    </div>
                  </v-col>
                </v-row>

                <v-data-table
                  :headers="payrollPunchHeaders"
                  :items="payrollPunchDayViewRows"
                  :loading="payrollPunchLoading"
                  :items-per-page="10"
                  :items-per-page-options="[
                    { value: 10, title: '10' },
                    { value: 20, title: '20' },
                    { value: 50, title: '50' },
                  ]"
                  class="elevation-0 payroll-punch-review-table mb-0"
                >
                  <template v-slot:item.staff_code="{ item }">
                    <span class="font-weight-medium">{{
                      item.staff_code
                    }}</span>
                  </template>

                  <template v-slot:item.date="{ item }">
                    {{ formatDate(item.date) }}
                  </template>

                  <template
                    v-for="timeKey in payrollPunchTimeKeys"
                    v-slot:[`item.${timeKey}`]="{ item }"
                    :key="timeKey"
                  >
                    <v-chip
                      v-if="item[timeKey]"
                      size="x-small"
                      color="success"
                      variant="flat"
                      class="payroll-punch-time-chip"
                    >
                      {{ item[timeKey] }}
                    </v-chip>
                    <span v-else class="text-medium-emphasis">-</span>
                  </template>

                  <template v-slot:item.actions="{ item }">
                    <v-btn
                      size="small"
                      variant="text"
                      color="#001f3d"
                      prepend-icon="mdi-pencil"
                      @click="openPayrollPunchEdit(item.source)"
                    >
                      Edit
                    </v-btn>
                  </template>

                  <template v-slot:no-data>
                    <div class="text-center py-6">
                      <v-icon size="48" color="grey"
                        >mdi-clipboard-search-outline</v-icon
                      >
                      <p class="text-subtitle-1 mt-2 mb-1">
                        No punch records found
                      </p>
                      <p class="text-body-2 text-medium-emphasis mb-0">
                        Adjust the review date or employee filter. Results load
                        automatically.
                      </p>
                    </div>
                  </template>
                </v-data-table>
              </div>
            </div>

            <div v-show="currentStep === 3">
              <!-- Section 3: Government Contributions -->
              <v-col cols="12" class="px-0 mt-4">
                <div class="section-header">
                  <div class="section-icon">
                    <v-icon size="18">mdi-bank</v-icon>
                  </div>
                  <h3 class="section-title">Government Contributions</h3>
                </div>
              </v-col>

              <v-alert
                type="info"
                variant="tonal"
                density="compact"
                class="mb-3"
              >
                <template v-slot:prepend>
                  <v-icon icon="mdi-information"></v-icon>
                </template>
                <div class="text-caption">
                  <strong>Note:</strong> Select which government contributions
                  to deduct for this payroll period. Unchecked contributions
                  will not be deducted, even if enabled for the employee.
                </div>
              </v-alert>

              <v-row class="mt-n2">
                <v-col cols="12" md="4">
                  <v-checkbox
                    v-model="formData.deduct_sss"
                    label="SSS"
                    prepend-icon="mdi-shield-account"
                    hint="Social Security System"
                    persistent-hint
                    color="#ed985f"
                    density="compact"
                    class="payroll-checkbox"
                  ></v-checkbox>
                </v-col>
                <v-col cols="12" md="4">
                  <v-checkbox
                    v-model="formData.deduct_philhealth"
                    label="PhilHealth"
                    prepend-icon="mdi-medical-bag"
                    hint="Philippine Health Insurance"
                    persistent-hint
                    color="#ed985f"
                    density="compact"
                    class="payroll-checkbox"
                  ></v-checkbox>
                </v-col>
                <v-col cols="12" md="4">
                  <v-checkbox
                    v-model="formData.deduct_pagibig"
                    label="Pag-IBIG"
                    prepend-icon="mdi-home-account"
                    hint="Home Development Mutual Fund"
                    persistent-hint
                    color="#ed985f"
                    density="compact"
                    class="payroll-checkbox"
                  ></v-checkbox>
                </v-col>
              </v-row>

              <div class="d-flex justify-space-between align-center mb-3">
                <div class="text-caption text-medium-emphasis">
                  Enabled deductions for this payroll
                </div>
                <v-chip size="small" color="#ed985f" variant="tonal">
                  {{ selectedContributionCount }} selected
                </v-chip>
              </div>

              <v-textarea
                v-model="formData.notes"
                label="Notes (Optional)"
                rows="2"
                counter="300"
                maxlength="300"
                variant="outlined"
                density="compact"
                prepend-icon="mdi-note-text"
              ></v-textarea>
            </div>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="dialog-actions payroll-dialog-actions">
          <v-btn
            variant="outlined"
            color="grey"
            @click="closeDialog"
            :disabled="saving"
          >
            Cancel
          </v-btn>
          <v-btn
            v-if="currentStep > 1"
            variant="outlined"
            color="#001f3d"
            @click="goPrevStep"
            :disabled="saving"
          >
            <v-icon size="16" class="mr-1">mdi-arrow-left</v-icon>
            Back
          </v-btn>
          <v-spacer></v-spacer>
          <v-btn
            v-if="currentStep < 3"
            color="#ED985F"
            variant="flat"
            @click="goNextStep"
            :disabled="saving"
          >
            Next
            <v-icon size="16" class="ml-1">mdi-arrow-right</v-icon>
          </v-btn>
          <v-btn
            v-else
            color="#ED985F"
            variant="flat"
            @click="savePayroll"
            :disabled="isSaveDisabled"
          >
            <v-progress-circular
              v-if="saving"
              indeterminate
              size="16"
              width="2"
              class="mr-2"
            ></v-progress-circular>
            <v-icon v-else size="18" class="mr-1">{{
              editMode ? "mdi-pencil" : "mdi-content-save"
            }}</v-icon>
            {{
              saving
                ? editMode
                  ? "Updating..."
                  : "Creating..."
                : editMode
                  ? "Update Payroll"
                  : "Create Payroll"
            }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <ManualEntryDialog
      v-model="payrollPunchEditDialog"
      :attendance="payrollPunchSelectedAttendance"
      :prefilledDate="payrollPunchPrefilledDate"
      @saved="handlePayrollPunchSaved"
    />

    <!-- Delete Confirmation Dialog - Modern Design -->
    <v-dialog v-model="deleteDialog" max-width="550">
      <v-card class="delete-dialog-modern">
        <!-- Modern Header with Icon -->
        <div class="delete-dialog-header">
          <div class="delete-icon-wrapper">
            <v-icon size="40" color="white">mdi-alert-circle-outline</v-icon>
          </div>
          <h2 class="delete-title">Confirm Deletion</h2>
        </div>

        <v-card-text class="delete-dialog-content">
          <!-- Payroll Details Card -->
          <div v-if="selectedPayroll" class="details-card">
            <div class="details-header">
              <v-icon size="16" color="#ed985f">mdi-information-outline</v-icon>
              <span>Payroll Information</span>
            </div>
            <div class="details-grid">
              <div class="detail-item">
                <div class="detail-icon">
                  <v-icon size="16">mdi-label</v-icon>
                </div>
                <div class="detail-content">
                  <div class="detail-label">Period</div>
                  <div class="detail-value">
                    {{ selectedPayroll.period_name }}
                  </div>
                </div>
              </div>
              <div class="detail-item">
                <div class="detail-icon">
                  <v-icon size="16">mdi-account-group</v-icon>
                </div>
                <div class="detail-content">
                  <div class="detail-label">Employees</div>
                  <div class="detail-value">
                    {{ selectedPayroll.items_count }} affected
                  </div>
                </div>
              </div>
              <div class="detail-item">
                <div class="detail-icon">
                  <v-icon size="16">mdi-cash</v-icon>
                </div>
                <div class="detail-content">
                  <div class="detail-label">Total Amount</div>
                  <div class="detail-value">
                    ₱{{ formatCurrency(selectedPayroll.total_net) }}
                  </div>
                </div>
              </div>
              <div class="detail-item">
                <div class="detail-icon">
                  <v-icon size="16">mdi-flag</v-icon>
                </div>
                <div class="detail-content">
                  <div class="detail-label">Status</div>
                  <div class="detail-value">
                    <v-chip
                      :color="getStatusColor(selectedPayroll.status)"
                      size="small"
                      variant="flat"
                    >
                      {{ selectedPayroll.status.toUpperCase() }}
                    </v-chip>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Consequences List -->
          <div class="consequences-section">
            <div class="consequences-title">
              <v-icon size="16" color="#64748b"
                >mdi-alert-circle-outline</v-icon
              >
              <span>What will be deleted:</span>
            </div>
            <ul class="consequences-list">
              <li>All employee payroll items and calculations</li>
              <li>Associated deduction records and installments</li>
              <li>Payment history and transaction logs</li>
            </ul>
          </div>

          <!-- Final Confirmation -->
          <div class="final-confirmation">
            <v-icon size="20" color="#ef4444">mdi-alert</v-icon>
            <span
              >Are you absolutely sure you want to delete this payroll?</span
            >
          </div>
        </v-card-text>

        <!-- Modern Actions -->
        <v-card-actions class="delete-dialog-actions">
          <v-btn
            variant="outlined"
            color="#64748b"
            @click="deleteDialog = false"
            class="cancel-btn"
          >
            <v-icon size="18" class="mr-1">mdi-close</v-icon>
            Cancel
          </v-btn>
          <v-spacer></v-spacer>
          <v-btn
            color="#ef4444"
            variant="flat"
            :loading="deleting"
            @click="deletePayroll"
            class="delete-btn"
          >
            <v-icon size="18" class="mr-1">mdi-delete-forever</v-icon>
            Delete Permanently
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Validation Warning Dialog -->
    <v-dialog v-model="validationWarningDialog" max-width="800" persistent>
      <v-card class="modern-dialog">
        <v-card-title class="dialog-header warning-header">
          <div class="dialog-icon-wrapper warning">
            <v-icon size="28">mdi-alert-circle</v-icon>
          </div>
          <div>
            <div class="dialog-title">Incomplete Attendance Records</div>
            <div class="dialog-subtitle">
              {{ incompleteRecords.length }} record(s) with missing punch data
            </div>
          </div>
        </v-card-title>

        <v-card-text class="dialog-content">
          <v-alert
            type="warning"
            variant="tonal"
            density="compact"
            class="mb-4"
          >
            <template v-slot:prepend>
              <v-icon icon="mdi-information"></v-icon>
            </template>
            <div class="text-caption">
              <strong>Important:</strong> Employees with incomplete attendance
              records may receive incorrect pay. Please complete all attendance
              records before creating payroll.
            </div>
          </v-alert>

          <!-- Incomplete Records Table -->
          <div class="incomplete-records-table">
            <v-table density="compact">
              <thead>
                <tr>
                  <th>Employee</th>
                  <th>Date</th>
                  <th>Issues</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(record, index) in incompleteRecords" :key="index">
                  <td>
                    <div class="employee-info">
                      <div class="font-weight-medium">
                        {{ record.employee_name }}
                      </div>
                      <div class="text-caption text-medium-emphasis">
                        {{ record.employee_number }}
                      </div>
                    </div>
                  </td>
                  <td>{{ record.attendance_date }}</td>
                  <td>
                    <div class="d-flex flex-wrap gap-1">
                      <v-chip
                        v-for="(issue, idx) in record.issues"
                        :key="idx"
                        color="warning"
                        size="small"
                        variant="tonal"
                      >
                        {{ issue }}
                      </v-chip>
                    </div>
                  </td>
                </tr>
              </tbody>
            </v-table>
          </div>

          <!-- Action Recommendations -->
          <v-alert type="info" variant="tonal" density="compact" class="mt-4">
            <div class="text-caption">
              <strong>Recommended:</strong> Fix incomplete attendance records
              first by going to Attendance Management → Missing Attendance tab.
            </div>
          </v-alert>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="dialog-actions">
          <v-btn
            variant="outlined"
            color="#64748b"
            @click="closeValidationWarning"
            :disabled="saving"
          >
            Cancel
          </v-btn>
          <v-btn
            color="#ED985F"
            variant="tonal"
            @click="goToMissingAttendance"
            :disabled="saving"
          >
            <v-icon size="18" class="mr-1">mdi-clock-alert-outline</v-icon>
            Fix Attendance
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Signature Settings Dialog -->
    <v-dialog v-model="signatureDialog" max-width="750px" persistent scrollable>
      <v-card>
        <v-card-title class="d-flex align-center pa-4">
          <v-icon class="mr-2">mdi-signature-freehand</v-icon>
          Payroll Signature Settings
          <v-spacer />
          <v-btn icon variant="text" @click="signatureDialog = false">
            <v-icon>mdi-close</v-icon>
          </v-btn>
        </v-card-title>
        <v-divider />
        <v-card-text class="pa-4">
          <p class="text-body-2 text-medium-emphasis mb-4">
            Configure the signatory names that appear on all payroll PDF
            exports.
          </p>

          <div class="text-subtitle-2 font-weight-bold mb-2">
            Row 1 — Primary Signatories
          </div>
          <v-row dense>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="signatureForm.sig_prepared_by"
                label="Prepared By"
                variant="outlined"
                density="comfortable"
                hide-details="auto"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="signatureForm.sig_checked_by"
                label="Checked & Verified By"
                variant="outlined"
                density="comfortable"
                hide-details="auto"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="signatureForm.sig_recommended_by"
                label="Recommended By"
                variant="outlined"
                density="comfortable"
                hide-details="auto"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="signatureForm.sig_approved_by"
                label="Approved By"
                variant="outlined"
                density="comfortable"
                hide-details="auto"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="signatureForm.sig_approved_by_position"
                label="Approved By — Position / Title"
                variant="outlined"
                density="comfortable"
                hide-details="auto"
              />
            </v-col>
          </v-row>

          <v-divider class="my-4" />

          <div class="text-subtitle-2 font-weight-bold mb-2">
            Row 2 — Secondary Signatories
          </div>
          <v-row dense>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="signatureForm.sig_prepared_by_2"
                label="Prepared By (2nd)"
                variant="outlined"
                density="comfortable"
                hide-details="auto"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="signatureForm.sig_checked_by_2"
                label="Checked & Verified By (2nd)"
                variant="outlined"
                density="comfortable"
                hide-details="auto"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="signatureForm.sig_recommended_by_2"
                label="Recommended By (2nd)"
                variant="outlined"
                density="comfortable"
                hide-details="auto"
              />
            </v-col>
            <v-col cols="12" sm="6">
              <v-text-field
                v-model="signatureForm.sig_approved_by_2"
                label="Approved By (2nd)"
                variant="outlined"
                density="comfortable"
                hide-details="auto"
              />
            </v-col>
          </v-row>
        </v-card-text>
        <v-divider />
        <v-card-actions class="pa-4">
          <v-spacer />
          <v-btn variant="text" @click="signatureDialog = false">Cancel</v-btn>
          <v-btn
            color="#ED985F"
            variant="flat"
            :loading="savingSignature"
            @click="saveSignatureSettings"
          >
            Save
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, watch } from "vue";
import { useRouter } from "vue-router";
import { useToast } from "vue-toastification";
import api from "@/services/api";
import { formatCurrency, formatDate } from "@/utils/formatters";
import { useKeyboardFirstFlow } from "@/composables/useKeyboardFirstFlow";
import { useDebouncedFn } from "@/composables/useDebounce";
import { usePositionRates } from "@/composables/usePositionRates";
import ManualEntryDialog from "@/components/attendance/ManualEntryDialog.vue";

const router = useRouter();
const toast = useToast();
const { positionOptions, loadPositionRates } = usePositionRates();

const loading = ref(false);
const saving = ref(false);
const deleting = ref(false);
const search = ref("");
const statusFilter = ref("all");
const dialog = ref(false);
const deleteDialog = ref(false);
const validationWarningDialog = ref(false);
const incompleteRecords = ref([]);
const editMode = ref(false);
const valid = ref(false);
const form = ref(null);
const currentStep = ref(1);

const payrolls = ref([]);
const selectedPayroll = ref(null);

const signatureDialog = ref(false);
const savingSignature = ref(false);
const signatureForm = ref({
  sig_prepared_by: "",
  sig_prepared_by_2: "",
  sig_checked_by: "",
  sig_recommended_by: "",
  sig_approved_by: "",
  sig_approved_by_position: "",
  sig_checked_by_2: "",
  sig_recommended_by_2: "",
  sig_approved_by_2: "",
});

const formData = ref({
  period_name: "",
  period_start: "",
  period_end: "",
  payment_date: "",
  notes: "",
  payroll_scope: "all",
  individual_target: "position",
  included_position: null,
  included_employee_id: null,
  has_attendance: false,
  excluded_positions: [],
  overtime_employee_ids: [],
  deduct_sss: true,
  deduct_philhealth: true,
  deduct_pagibig: true,
});

const employeeOptions = ref([]);
const employeeOptionsLoaded = ref(false);
const employeeOptionsLoading = ref(false);
const overtimeCandidates = ref([]);
const overtimeCandidatesLoaded = ref(false);
const overtimeCandidatesLoading = ref(false);
const overtimePreviewEmployeeId = ref(null);
const selectedEmployeeAttendance = ref([]);
const employeeAttendanceLoading = ref(false);
const attendanceRowSaving = ref({});
const payrollPunchLoading = ref(false);
const payrollPunchRecords = ref([]);
const payrollPunchFilters = ref({
  date: "",
  employee_id: null,
});
const payrollPunchEditDialog = ref(false);
const payrollPunchSelectedAttendance = ref(null);
const payrollPunchPrefilledDate = ref(null);

const individualTargetOptions = [
  { title: "Position", value: "position" },
  { title: "Employee", value: "employee" },
];

const startDateRules = [(value) => !!value || "Start date is required"];

const endDateRules = [
  (value) => !!value || "End date is required",
  (value) => {
    if (!value || !formData.value.period_start) return true;
    return (
      value >= formData.value.period_start ||
      "End date must be on/after start date"
    );
  },
];

const paymentDateRules = [
  (value) => !!value || "Payment date is required",
  (value) => {
    if (!value || !formData.value.period_end) return true;
    return (
      value >= formData.value.period_end ||
      "Payment date must be on/after period end"
    );
  },
];

const selectedContributionCount = computed(() => {
  return [
    formData.value.deduct_sss,
    formData.value.deduct_philhealth,
    formData.value.deduct_pagibig,
  ].filter(Boolean).length;
});

const isSaveDisabled = computed(() => {
  return saving.value;
});

const headers = [
  { title: "Payroll #", key: "payroll_number", sortable: true },
  { title: "Period", key: "period", sortable: false },
  { title: "Payment Date", key: "payment_date", sortable: true },
  { title: "Employees", key: "items_count", sortable: true },
  { title: "Total Net Pay", key: "total_net", sortable: true, align: "end" },
  { title: "Status", key: "status", sortable: true },
  { title: "Actions", key: "actions", sortable: false, align: "center" },
];

const stats = computed(() => {
  return {
    total: payrolls.value.length,
    draft: payrolls.value.filter((p) => p.status === "draft").length,
    finalized: payrolls.value.filter((p) => p.status === "finalized").length,
    paid: payrolls.value.filter((p) => p.status === "paid").length,
  };
});

const statusFilterOptions = [
  { title: "All Statuses", value: "all" },
  { title: "Draft", value: "draft" },
  { title: "Finalized", value: "finalized" },
  { title: "Paid", value: "paid" },
];

const filteredPayrolls = computed(() => {
  const term = search.value.trim().toLowerCase();

  return payrolls.value.filter((item) => {
    const statusMatch =
      statusFilter.value === "all" || item.status === statusFilter.value;

    if (!statusMatch) return false;
    if (!term) return true;

    const values = [
      item.payroll_number,
      item.period_name,
      item.status,
      item.payment_date,
      String(item.items_count ?? ""),
      String(item.total_net ?? ""),
    ];

    return values.some((value) =>
      value?.toString().toLowerCase().includes(term),
    );
  });
});

const hasActiveFilters = computed(() => {
  return !!search.value.trim() || statusFilter.value !== "all";
});

const activeFilterCount = computed(() => {
  return [search.value.trim(), statusFilter.value !== "all"].filter(Boolean)
    .length;
});

const selectedOvertimeEmployees = computed(() => {
  if (!Array.isArray(formData.value.overtime_employee_ids)) return [];
  const selected = new Set(formData.value.overtime_employee_ids);
  return overtimeCandidates.value.filter((candidate) =>
    selected.has(candidate.id),
  );
});

const payrollPunchTimeKeys = Array.from(
  { length: 10 },
  (_, idx) => `time${idx + 1}`,
);

const payrollPunchHeaders = [
  { title: "Staff Code", key: "staff_code" },
  { title: "Name", key: "name" },
  { title: "Date", key: "date" },
  { title: "Week", key: "week" },
  { title: "Device Name", key: "device_name" },
  ...payrollPunchTimeKeys.map((timeKey, idx) => ({
    title: `Time${idx + 1}`,
    key: timeKey,
    sortable: false,
  })),
  { title: "Actions", key: "actions", sortable: false },
];

const payrollPunchFieldMap = [
  { field: "time_in" },
  { field: "break_start" },
  { field: "break_end" },
  { field: "time_out" },
  { field: "ot_time_in" },
  { field: "ot_time_out" },
  { field: "ot_time_in_2" },
  { field: "ot_time_out_2" },
];

const payrollPunchEmployees = computed(() => {
  const sourceEmployees = Array.isArray(payrollPunchRecords.value)
    ? payrollPunchRecords.value
        .map((record) => record.employee)
        .filter((employee) => employee && employee.id)
    : [];

  const unique = new Map();
  sourceEmployees.forEach((employee) => {
    if (!unique.has(employee.id)) {
      unique.set(employee.id, {
        id: employee.id,
        full_name:
          employee.full_name ||
          `${employee.first_name || ""} ${employee.last_name || ""}`.trim(),
        employee_number: employee.employee_number,
      });
    }
  });

  return Array.from(unique.values()).sort((a, b) =>
    String(a.employee_number || "").localeCompare(
      String(b.employee_number || ""),
      undefined,
      { numeric: true },
    ),
  );
});

const payrollPunchDayViewRows = computed(() => {
  return payrollPunchRecords.value.map((record) => {
    const row = {
      source: record,
      staff_code: record.employee?.employee_number || "-",
      name: record.employee?.full_name || "-",
      date: record.attendance_date,
      week: getWeekDay(record.attendance_date),
      device_name: record.device_name || "-",
    };

    const punchTimes = getRecordPunches(record)
      .map((entry) => entry.time)
      .slice(0, 10);

    payrollPunchTimeKeys.forEach((timeKey, idx) => {
      row[timeKey] = punchTimes[idx] || null;
    });

    return row;
  });
});

const getWeekDay = (dateValue) => {
  if (!dateValue) {
    return "-";
  }

  const safeDate = `${String(dateValue).slice(0, 10)}T00:00:00`;
  return new Date(safeDate).toLocaleDateString("en-US", {
    weekday: "long",
  });
};

const normalizePunchTime = (timeValue) => {
  if (!timeValue) {
    return null;
  }

  return String(timeValue).slice(0, 5);
};

const getRecordPunches = (record) => {
  const punches = payrollPunchFieldMap
    .map(({ field }) => ({
      time: normalizePunchTime(record[field]),
    }))
    .filter((entry) => entry.time);

  punches.sort((a, b) => a.time.localeCompare(b.time));
  return punches;
};

const { handleKeydown: handlePayrollFormKeydown } = useKeyboardFirstFlow({
  onEscape: () => {
    if (!saving.value) closeDialog();
  },
  onSubmitLast: () => {
    if (currentStep.value < 3) {
      goNextStep();
      return;
    }
    if (!isSaveDisabled.value) {
      savePayroll();
    }
  },
});

onMounted(() => {
  fetchPayrolls();
  loadPositionRates();
});

async function loadEmployeeOptions() {
  if (employeeOptionsLoaded.value || employeeOptionsLoading.value) return;

  employeeOptionsLoading.value = true;
  try {
    const response = await api.get("/employees", {
      params: {
        per_page: 2000,
        activity_status: "active,on_leave",
      },
      cacheTTL: 120000,
    });

    const data = Array.isArray(response.data)
      ? response.data
      : response.data?.data || [];

    employeeOptions.value = data.map((employee) => ({
      id: employee.id,
      full_name:
        employee.full_name ||
        `${employee.first_name || ""} ${employee.last_name || ""}`.trim(),
      employee_number: employee.employee_number,
      position:
        employee.positionRate?.position_name ||
        employee.position ||
        employee.position_name ||
        "",
    }));
    employeeOptionsLoaded.value = true;
  } catch (error) {
    employeeOptions.value = [];
  } finally {
    employeeOptionsLoading.value = false;
  }
}

function shouldLoadEmployeeOptions() {
  return (
    dialog.value &&
    formData.value.payroll_scope === "individual" &&
    formData.value.individual_target === "employee"
  );
}

function shouldAutoLoadPayrollPunchReview() {
  return (
    dialog.value &&
    currentStep.value === 2 &&
    !!formData.value.period_start &&
    !!formData.value.period_end
  );
}

function shouldAutoLoadOvertimeCandidates() {
  return (
    dialog.value &&
    currentStep.value === 2 &&
    !!formData.value.period_start &&
    !!formData.value.period_end
  );
}

const queueOvertimeCandidatesAutoLoad = useDebouncedFn(() => {
  if (!shouldAutoLoadOvertimeCandidates()) return;
  loadOvertimeCandidates();
}, 300);

const queuePayrollPunchAutoLoad = useDebouncedFn(() => {
  if (!shouldAutoLoadPayrollPunchReview()) return;
  loadPayrollPunchReview();
}, 300);

watch(
  [
    () => dialog.value,
    () => formData.value.payroll_scope,
    () => formData.value.individual_target,
  ],
  () => {
    if (shouldLoadEmployeeOptions()) {
      loadEmployeeOptions();
    }
  },
);

watch(
  [
    () => dialog.value,
    () => currentStep.value,
    () => formData.value.period_start,
    () => formData.value.period_end,
    () => formData.value.payroll_scope,
    () => formData.value.individual_target,
    () => formData.value.included_position,
    () => formData.value.included_employee_id,
    () => formData.value.has_attendance,
    () => JSON.stringify(formData.value.excluded_positions || []),
  ],
  () => {
    if (!shouldAutoLoadOvertimeCandidates()) return;
    queueOvertimeCandidatesAutoLoad();
  },
);

watch(
  [
    () => dialog.value,
    () => currentStep.value,
    () => formData.value.period_start,
    () => formData.value.period_end,
    () => formData.value.payroll_scope,
    () => formData.value.individual_target,
    () => formData.value.included_position,
    () => formData.value.included_employee_id,
    () => payrollPunchFilters.value.date,
    () => payrollPunchFilters.value.employee_id,
  ],
  () => {
    if (!shouldAutoLoadPayrollPunchReview()) return;
    queuePayrollPunchAutoLoad();
  },
);

const customEmployeeFilter = (itemTitle, queryText, item) => {
  if (!queryText) return true;

  const search = queryText.toLowerCase();
  const fullName = item.raw.full_name?.toLowerCase() || "";
  const employeeNumber = item.raw.employee_number?.toLowerCase() || "";
  const position = item.raw.position?.toLowerCase() || "";

  return (
    fullName.includes(search) ||
    employeeNumber.includes(search) ||
    position.includes(search)
  );
};

async function fetchPayrolls() {
  loading.value = true;
  try {
    const response = await api.get("/payrolls", { cacheTTL: 15000 });
    payrolls.value = response.data.data || response.data;
  } catch (error) {
    toast.error("Failed to load payrolls");
  } finally {
    loading.value = false;
  }
}

function openCreateDialog() {
  editMode.value = false;
  currentStep.value = 1;
  formData.value = {
    period_name: "",
    period_start: "",
    period_end: "",
    payment_date: "",
    notes: "",
    payroll_scope: "all",
    individual_target: "position",
    included_position: null,
    included_employee_id: null,
    has_attendance: false,
    excluded_positions: [],
    overtime_employee_ids: [],
    deduct_sss: true,
    deduct_philhealth: true,
    deduct_pagibig: true,
  };
  overtimeCandidates.value = [];
  overtimeCandidatesLoaded.value = false;
  overtimePreviewEmployeeId.value = null;
  selectedEmployeeAttendance.value = [];
  payrollPunchRecords.value = [];
  payrollPunchFilters.value = {
    date: "",
    employee_id: null,
  };
  payrollPunchSelectedAttendance.value = null;
  payrollPunchPrefilledDate.value = null;
  dialog.value = true;
}

function editPayroll(item) {
  if (item.status !== "draft") {
    toast.info("Only draft payrolls can be edited");
    return;
  }

  editMode.value = true;
  currentStep.value = 1;
  selectedPayroll.value = item;
  const payrollScope = item.payroll_scope || "all";
  const individualTarget = item.individual_target || "position";
  const excludedPositions = Array.isArray(item.excluded_positions)
    ? item.excluded_positions
    : [];

  formData.value = {
    period_name: item.period_name,
    period_start: item.period_start,
    period_end: item.period_end,
    payment_date: item.payment_date,
    notes: item.notes || "",
    payroll_scope: payrollScope,
    individual_target: individualTarget,
    included_position:
      payrollScope === "individual" && individualTarget === "position"
        ? item.included_position
        : null,
    included_employee_id:
      payrollScope === "individual" && individualTarget === "employee"
        ? item.included_employee_id
        : null,
    has_attendance: Boolean(item.has_attendance),
    excluded_positions: payrollScope === "all" ? excludedPositions : [],
    overtime_employee_ids: item.overtime_employee_ids || [],
    deduct_sss: item.deduct_sss !== false,
    deduct_philhealth: item.deduct_philhealth !== false,
    deduct_pagibig: item.deduct_pagibig !== false,
  };
  overtimeCandidates.value = [];
  overtimeCandidatesLoaded.value = false;
  overtimePreviewEmployeeId.value = null;
  selectedEmployeeAttendance.value = [];
  payrollPunchRecords.value = [];
  payrollPunchFilters.value = {
    date: item.period_start || "",
    employee_id: null,
  };
  payrollPunchSelectedAttendance.value = null;
  payrollPunchPrefilledDate.value = null;
  dialog.value = true;
}

function closeDialog() {
  dialog.value = false;
  currentStep.value = 1;
  selectedPayroll.value = null;
  overtimeCandidates.value = [];
  overtimeCandidatesLoaded.value = false;
  overtimePreviewEmployeeId.value = null;
  selectedEmployeeAttendance.value = [];
  payrollPunchRecords.value = [];
  payrollPunchFilters.value = {
    date: "",
    employee_id: null,
  };
  payrollPunchEditDialog.value = false;
  payrollPunchSelectedAttendance.value = null;
  payrollPunchPrefilledDate.value = null;
}

function validateStepOne() {
  if (!formData.value.period_name) {
    toast.error("Period name is required");
    return false;
  }
  if (!formData.value.period_start || !formData.value.period_end) {
    toast.error("Payroll period start and end dates are required");
    return false;
  }
  if (formData.value.period_end < formData.value.period_start) {
    toast.error("Period end must be on or after period start");
    return false;
  }
  if (!formData.value.payment_date) {
    toast.error("Payment date is required");
    return false;
  }
  if (formData.value.payment_date < formData.value.period_end) {
    toast.error("Payment date must be on or after period end");
    return false;
  }
  return true;
}

function validateStepTwo() {
  if (formData.value.payroll_scope === "individual") {
    if (
      formData.value.individual_target === "position" &&
      !formData.value.included_position
    ) {
      toast.error("Please select a position for individual payroll");
      return false;
    }

    if (
      formData.value.individual_target === "employee" &&
      !formData.value.included_employee_id
    ) {
      toast.error("Please select an employee for individual payroll");
      return false;
    }
  }
  return true;
}

async function goNextStep() {
  if (currentStep.value === 1 && !validateStepOne()) return;
  if (currentStep.value === 2 && !validateStepTwo()) return;
  if (currentStep.value < 3) {
    currentStep.value += 1;

    if (currentStep.value === 2) {
      if (!payrollPunchFilters.value.date && formData.value.period_start) {
        payrollPunchFilters.value.date = formData.value.period_start;
      }
      queueOvertimeCandidatesAutoLoad();
      queuePayrollPunchAutoLoad();
    }
  }
}

function goPrevStep() {
  if (currentStep.value > 1) {
    currentStep.value -= 1;
  }
}

function payrollScopePayload() {
  return {
    payroll_scope: formData.value.payroll_scope,
    individual_target: formData.value.individual_target,
    included_position:
      formData.value.payroll_scope === "individual" &&
      formData.value.individual_target === "position"
        ? formData.value.included_position
        : null,
    included_employee_id:
      formData.value.payroll_scope === "individual" &&
      formData.value.individual_target === "employee"
        ? formData.value.included_employee_id
        : null,
    has_attendance: formData.value.has_attendance || false,
    excluded_positions:
      formData.value.payroll_scope === "all"
        ? formData.value.excluded_positions || []
        : [],
  };
}

async function loadOvertimeCandidates() {
  if (!formData.value.period_start || !formData.value.period_end) {
    return;
  }

  const previousPreviewEmployeeId = overtimePreviewEmployeeId.value;

  overtimeCandidatesLoading.value = true;
  try {
    const response = await api.post("/payrolls/overtime/candidates", {
      period_start: formData.value.period_start,
      period_end: formData.value.period_end,
      ...payrollScopePayload(),
    });

    const rows = response.data?.employees || [];
    overtimeCandidates.value = rows.map((row) => ({
      ...row,
      display_name: `${row.full_name} (${row.employee_number})`,
    }));

    const validIds = new Set(overtimeCandidates.value.map((row) => row.id));
    formData.value.overtime_employee_ids = (
      formData.value.overtime_employee_ids || []
    ).filter((id) => validIds.has(id));

    const selectedIds = new Set(formData.value.overtime_employee_ids || []);
    const canKeepPreview =
      previousPreviewEmployeeId &&
      selectedIds.has(previousPreviewEmployeeId) &&
      validIds.has(previousPreviewEmployeeId);

    overtimeCandidatesLoaded.value = true;
    overtimePreviewEmployeeId.value = canKeepPreview
      ? previousPreviewEmployeeId
      : null;

    if (!canKeepPreview) {
      selectedEmployeeAttendance.value = [];
    }
  } catch (error) {
    toast.error(
      error.response?.data?.message || "Failed to load overtime candidates",
    );
  } finally {
    overtimeCandidatesLoading.value = false;
  }
}

function clearOvertimeSelection() {
  formData.value.overtime_employee_ids = [];
  overtimePreviewEmployeeId.value = null;
  selectedEmployeeAttendance.value = [];
}

function selectEmployeesWithOvertimeRequest() {
  if (!overtimeCandidatesLoaded.value) return;

  const withRequests = overtimeCandidates.value.filter(
    (candidate) => candidate.has_overtime_request,
  );

  if (withRequests.length === 0) {
    toast.info("No overtime requests found in this payroll period");
    return;
  }

  formData.value.overtime_employee_ids = withRequests
    .map((candidate) => candidate.id)
    .filter((id) => id !== null && id !== undefined);
}

async function loadPayrollPunchReview() {
  if (!formData.value.period_start || !formData.value.period_end) {
    toast.error("Please set payroll period start and end dates first");
    return;
  }

  payrollPunchLoading.value = true;
  try {
    const payload = {
      period_start: formData.value.period_start,
      period_end: formData.value.period_end,
      ...payrollScopePayload(),
    };

    if (payrollPunchFilters.value.date) {
      payload.date = payrollPunchFilters.value.date;
    }

    if (payrollPunchFilters.value.employee_id) {
      payload.employee_id = payrollPunchFilters.value.employee_id;
    }

    const response = await api.post("/payrolls/punch-review", payload);
    payrollPunchRecords.value = response.data?.attendance || [];

    if (payrollPunchFilters.value.employee_id) {
      const validEmployeeIds = new Set(
        (response.data?.employees || []).map((employee) => employee.id),
      );
      if (!validEmployeeIds.has(payrollPunchFilters.value.employee_id)) {
        payrollPunchFilters.value.employee_id = null;
      }
    }
  } catch (error) {
    toast.error(
      error.response?.data?.message || "Failed to load payroll punch review",
    );
  } finally {
    payrollPunchLoading.value = false;
  }
}

function clearPayrollPunchFilters() {
  payrollPunchFilters.value = {
    date: formData.value.period_start || "",
    employee_id: null,
  };
  queuePayrollPunchAutoLoad();
}

function openPayrollPunchEdit(attendance) {
  payrollPunchSelectedAttendance.value = attendance;
  payrollPunchPrefilledDate.value = attendance?.attendance_date || null;
  payrollPunchEditDialog.value = true;
}

async function handlePayrollPunchSaved() {
  payrollPunchEditDialog.value = false;
  payrollPunchSelectedAttendance.value = null;
  payrollPunchPrefilledDate.value = null;

  await loadPayrollPunchReview();

  if (overtimePreviewEmployeeId.value) {
    await loadSelectedEmployeeAttendance();
  }
}

function toTimeInput(value) {
  if (!value) return "";
  return value.length >= 5 ? value.slice(0, 5) : value;
}

function toApiTime(value) {
  if (!value) return null;
  return value.length === 5 ? `${value}:00` : value;
}

async function loadSelectedEmployeeAttendance() {
  if (!overtimePreviewEmployeeId.value) return;
  if (!formData.value.period_start || !formData.value.period_end) {
    toast.error("Please set payroll period dates first");
    return;
  }

  employeeAttendanceLoading.value = true;
  try {
    const response = await api.get(
      `/payrolls/overtime/employee/${overtimePreviewEmployeeId.value}/attendance`,
      {
        params: {
          period_start: formData.value.period_start,
          period_end: formData.value.period_end,
        },
      },
    );

    selectedEmployeeAttendance.value = (response.data?.attendance || []).map(
      (row) => ({
        ...row,
        time_in_input: toTimeInput(row.time_in),
        time_out_input: toTimeInput(row.time_out),
        ot_time_in_input: toTimeInput(row.ot_time_in),
        ot_time_out_input: toTimeInput(row.ot_time_out),
      }),
    );
  } catch (error) {
    toast.error(
      error.response?.data?.message || "Failed to load employee day view",
    );
  } finally {
    employeeAttendanceLoading.value = false;
  }
}

async function saveAttendanceRow(row) {
  attendanceRowSaving.value = {
    ...attendanceRowSaving.value,
    [row.id]: true,
  };

  try {
    const response = await api.put(`/attendance/${row.id}`, {
      time_in: toApiTime(row.time_in_input),
      time_out: toApiTime(row.time_out_input),
      ot_time_in: toApiTime(row.ot_time_in_input),
      ot_time_out: toApiTime(row.ot_time_out_input),
      notes: "Updated during payroll overtime setup",
    });

    const updated = response.data?.attendance;
    if (updated) {
      row.status = updated.status;
      row.approval_status = updated.approval_status;
      row.regular_hours = updated.regular_hours;
      row.overtime_hours = updated.overtime_hours;
      row.undertime_hours = updated.undertime_hours;
      row.late_hours = updated.late_hours;
      row.time_in = updated.time_in;
      row.time_out = updated.time_out;
      row.ot_time_in = updated.ot_time_in;
      row.ot_time_out = updated.ot_time_out;
      row.time_in_input = toTimeInput(updated.time_in);
      row.time_out_input = toTimeInput(updated.time_out);
      row.ot_time_in_input = toTimeInput(updated.ot_time_in);
      row.ot_time_out_input = toTimeInput(updated.ot_time_out);
    }

    toast.success("Attendance day updated");
  } catch (error) {
    toast.error(error.response?.data?.message || "Failed to save attendance");
  } finally {
    attendanceRowSaving.value = {
      ...attendanceRowSaving.value,
      [row.id]: false,
    };
  }
}

function clearTableFilters() {
  search.value = "";
  statusFilter.value = "all";
}

async function savePayroll() {
  const { valid } = await form.value.validate();
  if (!valid) return;

  if (formData.value.payroll_scope === "individual") {
    if (
      formData.value.individual_target === "position" &&
      !formData.value.included_position
    ) {
      toast.error("Please select a position for individual payroll");
      return;
    }

    if (
      formData.value.individual_target === "employee" &&
      !formData.value.included_employee_id
    ) {
      toast.error("Please select an employee for individual payroll");
      return;
    }
  }

  // Prepare payload - simplified for all employees only
  const payload = {
    period_name: formData.value.period_name,
    period_start: formData.value.period_start,
    period_end: formData.value.period_end,
    payment_date: formData.value.payment_date,
    notes: formData.value.notes,
    ...payrollScopePayload(),
    deduct_sss: formData.value.deduct_sss,
    deduct_philhealth: formData.value.deduct_philhealth,
    deduct_pagibig: formData.value.deduct_pagibig,
  };

  if (
    Array.isArray(formData.value.overtime_employee_ids) &&
    formData.value.overtime_employee_ids.length > 0
  ) {
    payload.overtime_employee_ids = formData.value.overtime_employee_ids;
  }

  // Step 1: Validate attendance completeness (new payroll only)
  if (!editMode.value) {
    saving.value = true;
    try {
      await api.post("/payrolls/validate", {
        period_start: formData.value.period_start,
        period_end: formData.value.period_end,
        ...payrollScopePayload(),
      });
    } catch (error) {
      saving.value = false;
      if (error.response?.status === 422) {
        incompleteRecords.value = error.response.data.incomplete_records || [];
        validationWarningDialog.value = true;
        return;
      } else {
        toast.error(
          error.response?.data?.message || "Failed to validate payroll data",
        );
        return;
      }
    }
  }

  // Step 2: Create payroll
  saving.value = true;
  try {
    if (editMode.value) {
      await api.put(`/payrolls/${selectedPayroll.value.id}`, payload);
      toast.success("Payroll updated successfully");
    } else {
      const response = await api.post("/payrolls", payload);
      const employeeCount =
        response.data.items_count || response.data.payroll?.items_count || 0;
      toast.success(
        `Payroll created successfully for ${employeeCount} employee(s)`,
      );
    }
    await fetchPayrolls();
    closeDialog();
  } catch (error) {
    let errorMessage = "Failed to save payroll";

    if (error.response?.data) {
      const data = error.response.data;
      if (data.error) {
        errorMessage = data.error;
      } else if (data.message && data.message !== "Validation failed") {
        errorMessage = data.message;
      }
    }

    toast.error(errorMessage, {
      duration: 8000, // Show longer for detailed messages
    });
  } finally {
    saving.value = false;
  }
}

function viewPayroll(item) {
  api
    .get(`/payrolls/${item.id}`, { cacheTTL: 15000, skipToast: true })
    .catch(() => {});
  router.push(`/payroll/${item.id}`);
}

function confirmDelete(item) {
  if (item.status !== "draft") {
    toast.info("Only draft payrolls can be deleted");
    return;
  }

  selectedPayroll.value = item;
  deleteDialog.value = true;
}

async function deletePayroll() {
  deleting.value = true;
  try {
    await api.delete(`/payrolls/${selectedPayroll.value.id}`);
    toast.success("Payroll deleted successfully");
    await fetchPayrolls();
    deleteDialog.value = false;
  } catch (error) {
    toast.error("Failed to delete payroll");
  } finally {
    deleting.value = false;
  }
}

// Validation Warning Dialog Functions
function closeValidationWarning() {
  validationWarningDialog.value = false;
  incompleteRecords.value = [];
}

function goToMissingAttendance() {
  validationWarningDialog.value = false;
  dialog.value = false;
  router.push("/attendance?tab=missing");
}

function getStatusColor(status) {
  const colors = {
    draft: "warning",
    finalized: "info",
    paid: "success",
  };
  return colors[status] || "grey";
}

// formatDate, formatCurrency imported from @/utils/formatters

// Signature Settings
async function openSignatureSettings() {
  try {
    const response = await api.get("/company-info");
    const data = response.data.data || {};
    signatureForm.value = {
      sig_prepared_by: data.sig_prepared_by || "",
      sig_prepared_by_2: data.sig_prepared_by_2 || "",
      sig_checked_by: data.sig_checked_by || "",
      sig_recommended_by: data.sig_recommended_by || "",
      sig_approved_by: data.sig_approved_by || "",
      sig_approved_by_position: data.sig_approved_by_position || "",
      sig_checked_by_2: data.sig_checked_by_2 || "",
      sig_recommended_by_2: data.sig_recommended_by_2 || "",
      sig_approved_by_2: data.sig_approved_by_2 || "",
    };
  } catch {
    toast.error("Failed to load signature settings");
  }
  signatureDialog.value = true;
}

async function saveSignatureSettings() {
  savingSignature.value = true;
  try {
    await api.post("/company-info", signatureForm.value);
    toast.success("Signature settings saved");
    signatureDialog.value = false;
  } catch {
    toast.error("Failed to save signature settings");
  } finally {
    savingSignature.value = false;
  }
}
</script>

<style scoped lang="scss">
.payroll-page {
  max-width: 1600px;
  margin: 0 auto;
}

.payroll-form-dialog {
  border-radius: 14px !important;
}

.payroll-dialog-header {
  padding: 14px 18px !important;
}

.payroll-dialog-content {
  padding: 14px 18px 8px !important;
}

.payroll-form {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.wizard-steps {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 8px;
}

.wizard-step {
  border: 1px solid rgba(0, 31, 61, 0.14);
  border-radius: 10px;
  background: #fff;
  color: #001f3d;
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 10px;
  text-align: left;
}

.wizard-step-index {
  width: 24px;
  height: 24px;
  border-radius: 999px;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 31, 61, 0.1);
  font-size: 12px;
  font-weight: 700;
}

.wizard-step-label {
  font-size: 12px;
  font-weight: 600;
}

.wizard-step.active {
  border-color: #ed985f;
  background: rgba(237, 152, 95, 0.1);
}

.wizard-step.active .wizard-step-index {
  background: #ed985f;
  color: #fff;
}

.wizard-step.done {
  border-color: #10b981;
  background: rgba(16, 185, 129, 0.08);
}

.wizard-step.done .wizard-step-index {
  background: #10b981;
  color: #fff;
}

.step-two-workspace {
  display: flex;
  flex-direction: column;
  gap: 0;
}

.step-two-panel {
  border: 1px solid rgba(0, 31, 61, 0.12);
  border-radius: 14px;
  padding: 14px;
  background: #fff;
  box-shadow: 0 1px 3px rgba(0, 31, 61, 0.06);
}

.step-two-panel--scope {
  background: linear-gradient(
    180deg,
    rgba(0, 31, 61, 0.015) 0%,
    rgba(237, 152, 95, 0.03) 100%
  );
}

.panel-intro {
  font-size: 12px;
  line-height: 1.45;
  color: rgba(0, 31, 61, 0.72);
}

.scope-controls {
  display: grid;
  grid-template-columns: minmax(0, 1fr) minmax(280px, 38%);
  gap: 10px;
  align-items: start;
  margin-bottom: 10px;
}

.scope-toggle {
  width: fit-content;
  max-width: 100%;
}

.ot-summary {
  display: flex;
  align-items: center;
  gap: 10px;
  flex-wrap: wrap;
}

.ot-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.step-action-btn {
  min-height: 36px;
}

.day-view-toolbar {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 10px;
  align-items: start;
}

.day-view-employee-select {
  min-width: 0;
}

.day-view-load-btn {
  min-height: 40px;
}

.attendance-day-view {
  border: 1px solid rgba(0, 31, 61, 0.1);
  border-radius: 10px;
  overflow: hidden;
}

.attendance-day-view-scroll {
  max-height: 340px;
  overflow: auto;
}

.attendance-day-view :deep(td),
.attendance-day-view :deep(th) {
  white-space: nowrap;
}

.attendance-day-view :deep(.day-time-input) {
  min-width: 132px;
}

.attendance-day-view :deep(.day-time-input .v-field) {
  min-width: 132px;
}

.attendance-day-view :deep(.day-time-input input[type="time"]) {
  min-width: 118px;
  font-variant-numeric: tabular-nums;
}

.punch-filter-actions {
  display: flex;
  justify-content: flex-end;
  gap: 8px;
  flex-wrap: wrap;
}

.punch-filter-grid {
  margin-bottom: 2px;
}

.payroll-punch-review-table {
  border: 1px solid rgba(0, 31, 61, 0.1);
  border-radius: 10px;
  overflow: hidden;
}

.payroll-punch-review-table :deep(td),
.payroll-punch-review-table :deep(th) {
  white-space: nowrap;
}

.payroll-punch-time-chip {
  min-width: 50px;
  justify-content: center;
}

.payroll-dates-row {
  margin-top: -4px;
}

.payroll-checkbox {
  margin-top: 0 !important;
  margin-bottom: 0 !important;
}

.payroll-dialog-actions {
  position: sticky;
  bottom: 0;
  background: #ffffff;
  padding: 10px 18px !important;
  border-top: 1px solid rgba(0, 31, 61, 0.08);
  z-index: 2;
}

.form-field-wrapper {
  margin-bottom: 2px;
}

.form-label {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  margin-bottom: 6px;
  font-size: 13px;
  font-weight: 600;
  color: #001f3d;
}

// Delete Dialog - Modern Design
.delete-dialog-modern {
  border-radius: 16px !important;
  overflow: hidden;
}

.delete-dialog-header {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  padding: 32px 24px;
  text-align: center;
  position: relative;
  overflow: hidden;

  &::before {
    content: "";
    position: absolute;
    top: -50%;
    right: -50%;
    width: 200%;
    height: 200%;
    background: radial-gradient(
      circle,
      rgba(255, 255, 255, 0.1) 0%,
      transparent 70%
    );
    animation: pulse 3s ease-in-out infinite;
  }
}

@keyframes pulse {
  0%,
  100% {
    transform: scale(1);
    opacity: 0.5;
  }
  50% {
    transform: scale(1.1);
    opacity: 0.8;
  }
}

.delete-icon-wrapper {
  width: 80px;
  height: 80px;
  margin: 0 auto 16px;
  border-radius: 50%;
  background: rgba(255, 255, 255, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  backdrop-filter: blur(10px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
  position: relative;
  z-index: 1;
}

.delete-title {
  font-size: 24px;
  font-weight: 700;
  color: white;
  margin: 0 0 8px 0;
  position: relative;
  z-index: 1;
}

.delete-subtitle {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.9);
  margin: 0;
  position: relative;
  z-index: 1;
}

.delete-dialog-content {
  padding: 24px !important;
}

.warning-badge {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: rgba(239, 68, 68, 0.08);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 8px;
  margin-bottom: 20px;

  span {
    font-size: 13px;
    font-weight: 600;
    color: #ef4444;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
}

.details-card {
  background: linear-gradient(
    135deg,
    rgba(237, 152, 95, 0.04) 0%,
    rgba(247, 185, 128, 0.02) 100%
  );
  border: 1px solid rgba(237, 152, 95, 0.15);
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 20px;
}

.details-header {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 12px;
  padding-bottom: 10px;
  border-bottom: 1px solid rgba(0, 31, 61, 0.08);

  span {
    font-size: 13px;
    font-weight: 700;
    color: #001f3d;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
}

.details-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 12px;
}

.detail-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 10px;
  background: white;
  border-radius: 8px;
  border: 1px solid rgba(0, 31, 61, 0.08);
}

.detail-icon {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;

  .v-icon {
    color: white !important;
  }
}

.detail-content {
  flex: 1;
  min-width: 0;
}

.detail-label {
  font-size: 11px;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 2px;
}

.detail-value {
  font-size: 13px;
  font-weight: 600;
  color: #001f3d;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.consequences-section {
  background: rgba(100, 116, 139, 0.04);
  border: 1px solid rgba(100, 116, 139, 0.15);
  border-radius: 10px;
  padding: 14px 16px;
  margin-bottom: 20px;
}

.consequences-title {
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 10px;

  span {
    font-size: 12px;
    font-weight: 700;
    color: #64748b;
    text-transform: uppercase;
    letter-spacing: 0.5px;
  }
}

.consequences-list {
  margin: 0;
  padding-left: 20px;

  li {
    font-size: 13px;
    color: #64748b;
    line-height: 1.6;
    margin-bottom: 6px;

    &:last-child {
      margin-bottom: 0;
    }
  }
}

.final-confirmation {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px 16px;
  background: rgba(239, 68, 68, 0.06);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 10px;
  border-left: 4px solid #ef4444;

  span {
    font-size: 14px;
    font-weight: 600;
    color: #001f3d;
    line-height: 1.5;
  }
}

.delete-dialog-actions {
  padding: 20px 24px !important;
  background: rgba(0, 31, 61, 0.02);
  border-top: 1px solid rgba(0, 31, 61, 0.08);
}

.cancel-btn {
  text-transform: none !important;
  font-weight: 600 !important;
  border-radius: 10px !important;
  padding: 10px 20px !important;
  letter-spacing: 0 !important;

  &:hover {
    background: rgba(100, 116, 139, 0.08) !important;
  }
}

.delete-btn {
  text-transform: none !important;
  font-weight: 600 !important;
  border-radius: 10px !important;
  padding: 10px 20px !important;
  letter-spacing: 0 !important;
  box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3) !important;

  &:hover:not(:disabled) {
    box-shadow: 0 6px 16px rgba(239, 68, 68, 0.4) !important;
    transform: translateY(-1px);
  }
}

// Validation Warning Dialog
.warning-header {
  background: linear-gradient(
    135deg,
    rgba(255, 152, 0, 0.05) 0%,
    rgba(255, 193, 7, 0.03) 100%
  ) !important;
}

.incomplete-records-table {
  border: 1px solid rgba(0, 31, 61, 0.08);
  border-radius: 8px;
  overflow: hidden;
  max-height: 400px;
  overflow-y: auto;

  .v-table {
    background: transparent;

    thead {
      background: rgba(0, 31, 61, 0.02);
      position: sticky;
      top: 0;
      z-index: 1;

      th {
        font-weight: 600;
        color: #001f3d;
        font-size: 13px;
        padding: 12px 16px !important;
        border-bottom: 1px solid rgba(0, 31, 61, 0.1) !important;
      }
    }

    tbody {
      tr {
        &:hover {
          background: rgba(237, 152, 95, 0.02);
        }

        td {
          padding: 12px 16px !important;
          border-bottom: 1px solid rgba(0, 31, 61, 0.05) !important;
        }
      }
    }
  }
}

.employee-info {
  .font-weight-medium {
    color: #001f3d;
    font-size: 14px;
    line-height: 1.4;
  }

  .text-caption {
    font-size: 12px;
    line-height: 1.2;
  }
}

.gap-1 {
  gap: 4px;
}

// Section Header - match AllowanceForm style
.section-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  background: linear-gradient(
    135deg,
    rgba(0, 31, 61, 0.02) 0%,
    rgba(237, 152, 95, 0.02) 100%
  );
  border-radius: 12px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  margin-bottom: 0;
}

.section-icon {
  width: 30px;
  height: 30px;
  border-radius: 8px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.25);

  .v-icon {
    color: #ffffff !important;
  }
}

.section-title {
  font-size: 15px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
  letter-spacing: -0.3px;
}

@media (max-width: 960px) {
  .scope-controls {
    grid-template-columns: 1fr;
  }

  .scope-toggle {
    width: 100%;
  }

  .scope-toggle :deep(.v-btn) {
    flex: 1 1 auto;
  }

  .ot-actions {
    width: 100%;
  }

  .ot-actions :deep(.v-btn) {
    flex: 1 1 calc(50% - 8px);
  }

  .day-view-toolbar {
    grid-template-columns: 1fr;
  }

  .day-view-load-btn {
    width: 100%;
  }

  .punch-filter-actions {
    justify-content: flex-start;
    width: 100%;
  }

  .punch-filter-actions :deep(.v-btn) {
    flex: 1 1 calc(50% - 8px);
  }
}
</style>
