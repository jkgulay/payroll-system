<template>
  <div class="deductions-page">
    <div class="modern-card">
      <!-- Modern Page Header -->
      <div class="page-header">
        <div class="page-icon-badge">
          <v-icon icon="mdi-cash-minus" size="24" color="white"></v-icon>
        </div>
        <div class="page-header-content">
          <h1 class="page-title">Employee Deductions</h1>
          <p class="page-subtitle">
            Manage employee deductions and installment payments
          </p>
        </div>
        <div class="d-flex gap-2" v-if="hasAccess">
          <v-btn
            icon="mdi-refresh"
            variant="text"
            color="primary"
            @click="fetchDeductions"
            title="Refresh"
          ></v-btn>
          <button
            v-if="userRole !== 'employee'"
            class="action-btn action-btn-primary"
            @click="openAddDialog"
          >
            <v-icon size="20">mdi-plus</v-icon>
            <span>Add Deduction</span>
          </button>
        </div>
      </div>

      <!-- Access Gate for Payrollist -->
      <template v-if="!isAdminOrHr && !hasAccess">
        <!-- No request yet -->
        <v-alert
          v-if="accessStatus === 'none'"
          type="info"
          variant="tonal"
          prominent
          class="ma-4"
          icon="mdi-lock-outline"
        >
          <v-alert-title>Access Required</v-alert-title>
          <p class="mt-1">
            You need to request access from an administrator before you can
            manage deductions.
          </p>
          <v-btn
            color="primary"
            variant="flat"
            class="mt-3"
            prepend-icon="mdi-send"
            @click="requestDialog = true"
          >
            Request Access
          </v-btn>
        </v-alert>

        <!-- Pending -->
        <v-alert
          v-else-if="accessStatus === 'pending'"
          type="warning"
          variant="tonal"
          prominent
          class="ma-4"
          icon="mdi-clock-outline"
        >
          <v-alert-title>Pending Approval</v-alert-title>
          <p class="mt-1">
            Your access request is pending admin approval. You will be able to
            manage deductions once approved.
          </p>
        </v-alert>

        <!-- Rejected -->
        <v-alert
          v-else-if="accessStatus === 'rejected'"
          type="error"
          variant="tonal"
          prominent
          class="ma-4"
          icon="mdi-close-circle-outline"
        >
          <v-alert-title>Request Rejected</v-alert-title>
          <p class="mt-1">{{ accessMessage }}</p>
          <v-btn
            color="primary"
            variant="flat"
            class="mt-3"
            prepend-icon="mdi-send"
            @click="requestDialog = true"
          >
            Submit New Request
          </v-btn>
        </v-alert>

        <!-- My Requests History -->
        <v-expansion-panels v-model="requestsPanel" class="mx-4 mb-4">
          <v-expansion-panel>
            <v-expansion-panel-title>
              <v-icon class="mr-2">mdi-history</v-icon>
              My Access Requests
            </v-expansion-panel-title>
            <v-expansion-panel-text>
              <v-list v-if="myRequests.length > 0" density="compact">
                <v-list-item
                  v-for="req in myRequests"
                  :key="req.id"
                  :subtitle="req.reason"
                >
                  <template v-slot:prepend>
                    <v-icon :color="getRequestStatusColor(req.status)">
                      {{
                        req.status === "pending"
                          ? "mdi-clock-outline"
                          : req.status === "approved"
                            ? "mdi-check-circle"
                            : "mdi-close-circle"
                      }}
                    </v-icon>
                  </template>
                  <template v-slot:append>
                    <v-chip
                      :color="getRequestStatusColor(req.status)"
                      size="x-small"
                      variant="flat"
                    >
                      {{ req.status }}
                    </v-chip>
                  </template>
                </v-list-item>
              </v-list>
              <p v-else class="text-center text-medium-emphasis py-4">
                No requests yet.
              </p>
            </v-expansion-panel-text>
          </v-expansion-panel>
        </v-expansion-panels>

        <!-- Request Access Dialog -->
        <v-dialog v-model="requestDialog" max-width="500" persistent>
          <v-card rounded="lg">
            <v-card-title class="d-flex align-center pa-4">
              <v-icon color="primary" class="mr-2"
                >mdi-lock-open-variant</v-icon
              >
              Request Deductions Access
            </v-card-title>
            <v-divider></v-divider>
            <v-card-text class="pa-4">
              <p class="text-body-2 mb-4">
                Please provide a reason for needing access to the Deductions
                module.
              </p>
              <v-textarea
                v-model="requestReason"
                label="Reason"
                variant="outlined"
                rows="3"
                :rules="[(v) => !!v || 'Reason is required']"
                placeholder="Explain why you need access to manage deductions"
              ></v-textarea>
            </v-card-text>
            <v-divider></v-divider>
            <v-card-actions class="pa-4">
              <v-spacer></v-spacer>
              <v-btn
                variant="text"
                @click="
                  requestDialog = false;
                  requestReason = '';
                "
              >
                Cancel
              </v-btn>
              <v-btn
                color="primary"
                variant="flat"
                :loading="submittingRequest"
                :disabled="!requestReason"
                prepend-icon="mdi-send"
                @click="submitAccessRequest"
              >
                Submit Request
              </v-btn>
            </v-card-actions>
          </v-card>
        </v-dialog>
      </template>

      <!-- Main Content (only when access granted) -->
      <template v-if="hasAccess">
        <!-- Filters -->
        <div class="filters-section">
          <v-row>
            <v-col cols="12" md="3">
              <v-text-field
                v-model="filters.search"
                prepend-inner-icon="mdi-magnify"
                label="Search employee..."
                variant="outlined"
                density="comfortable"
                clearable
                hide-details
                @update:model-value="debouncedSearch"
              ></v-text-field>
            </v-col>
            <v-col cols="12" md="3" v-if="userRole !== 'employee'">
              <v-select
                v-model="filters.department"
                :items="departments"
                item-title="title"
                item-value="value"
                label="Filter by Project"
                variant="outlined"
                density="comfortable"
                clearable
                hide-details
                @update:model-value="fetchDeductions"
              ></v-select>
            </v-col>
            <v-col cols="12" md="3" v-if="userRole !== 'employee'">
              <v-select
                v-model="filters.position"
                :items="positions"
                item-title="title"
                item-value="value"
                label="Filter by Position"
                variant="outlined"
                density="comfortable"
                clearable
                hide-details
                @update:model-value="fetchDeductions"
              ></v-select>
            </v-col>
            <v-col cols="12" :md="userRole === 'employee' ? 3 : 2">
              <v-select
                v-model="filters.deduction_type"
                :items="filterDeductionTypes"
                label="Filter by Type"
                variant="outlined"
                density="comfortable"
                clearable
                hide-details
                @update:model-value="fetchDeductions"
              ></v-select>
            </v-col>
            <v-col cols="12" :md="userRole === 'employee' ? 3 : 2">
              <v-select
                v-model="filters.status"
                :items="statusOptions"
                label="Filter by Status"
                variant="outlined"
                density="comfortable"
                clearable
                hide-details
                @update:model-value="fetchDeductions"
              ></v-select>
            </v-col>
            <v-col cols="auto" class="d-flex align-center">
              <v-btn
                color="error"
                variant="tonal"
                icon="mdi-filter-remove"
                @click="clearFilters"
                :disabled="!hasActiveFilters"
                title="Clear Filters"
              ></v-btn>
            </v-col>
            <v-col
              cols="auto"
              class="d-flex align-center"
              v-if="hasActiveFilters"
            >
              <v-chip size="small" color="info" variant="tonal">
                {{ activeFilterCount }} active filter{{
                  activeFilterCount > 1 ? "s" : ""
                }}
              </v-chip>
            </v-col>
          </v-row>
        </div>

        <!-- Deductions Table -->
        <v-data-table
          :headers="headers"
          :items="filteredDeductions"
          :loading="loading"
          :items-per-page="15"
          class="modern-table"
        >
          <template v-slot:item.employee="{ item }">
            <div>
              <div class="font-weight-medium">
                {{ item.employee?.full_name }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ item.employee?.employee_number }}
              </div>
            </div>
          </template>

          <template v-slot:item.deduction_type="{ item }">
            <v-chip
              :color="getDeductionTypeColor(item.deduction_type)"
              size="small"
              variant="tonal"
            >
              {{ formatDeductionType(item.deduction_type) }}
            </v-chip>
          </template>

          <template v-slot:item.total_amount="{ item }">
            <span class="font-weight-medium"
              >₱{{ formatNumber(item.total_amount) }}</span
            >
          </template>

          <template v-slot:item.amount_per_cutoff="{ item }">
            <span class="font-weight-medium"
              >₱{{ formatNumber(item.amount_per_cutoff) }}</span
            >
          </template>

          <template v-slot:item.balance="{ item }">
            <span
              :class="
                item.balance > 0
                  ? 'text-error font-weight-bold'
                  : 'text-success'
              "
            >
              ₱{{ formatNumber(item.balance) }}
            </span>
          </template>

          <template v-slot:item.progress="{ item }">
            <div class="d-flex align-center">
              <v-progress-linear
                :model-value="getProgress(item)"
                :color="item.status === 'completed' ? 'success' : 'primary'"
                height="8"
                rounded
                class="mr-2"
                style="min-width: 80px"
              ></v-progress-linear>
              <span class="text-caption"
                >{{ item.installments_paid }}/{{ item.installments }}</span
              >
            </div>
          </template>

          <template v-slot:item.status="{ item }">
            <v-chip
              :color="getStatusColor(item.status)"
              size="small"
              variant="flat"
            >
              {{ formatStatus(item.status) }}
            </v-chip>
          </template>

          <template v-slot:item.actions="{ item }">
            <v-btn
              v-if="userRole !== 'employee' && item.status === 'active'"
              icon="mdi-pencil"
              size="small"
              variant="text"
              @click="openEditDialog(item)"
            ></v-btn>
            <v-btn
              v-if="
                userRole !== 'employee' &&
                (item.installments_paid === 0 || item.status === 'cancelled')
              "
              icon="mdi-delete"
              size="small"
              variant="text"
              color="error"
              @click="confirmDelete(item)"
            ></v-btn>
            <v-btn
              icon="mdi-eye"
              size="small"
              variant="text"
              color="info"
              @click="viewDetails(item)"
            ></v-btn>
          </template>

          <template v-slot:no-data>
            <div class="text-center py-8">
              <v-icon size="64" color="grey">mdi-wallet-minus-outline</v-icon>
              <p class="text-h6 mt-4">No deductions found</p>
              <p class="text-body-2 text-medium-emphasis">
                {{
                  activeTab === "government"
                    ? "No government deductions"
                    : activeTab === "company"
                      ? "No company deductions"
                      : "No deductions available"
                }}
              </p>
              <v-btn
                class="mt-3"
                variant="outlined"
                color="primary"
                @click="clearFilters"
                :disabled="!hasActiveFilters"
              >
                Clear filters
              </v-btn>
            </div>
          </template>
        </v-data-table>
      </template>
    </div>

    <!-- Add/Edit Dialog - Modern UI -->
    <v-dialog v-model="dialog" max-width="800px" persistent scrollable>
      <v-card class="modern-dialog deductions-dialog">
        <v-card-title class="dialog-header deductions-dialog-header">
          <div class="dialog-icon-wrapper primary">
            <v-icon size="24">{{
              editMode ? "mdi-pencil" : "mdi-cash-minus"
            }}</v-icon>
          </div>
          <div>
            <div class="dialog-title">
              {{ editMode ? "Edit Deduction" : "Add Deduction" }}
            </div>
            <div class="dialog-subtitle">
              <template v-if="editMode"> Update deduction details </template>
              <template v-else>
                <span v-if="selectionMode === 'individual'"
                  >Create new deduction for employee</span
                >
                <span v-else-if="selectionMode === 'multiple'"
                  >Create deduction for multiple employees</span
                >
                <span v-else-if="selectionMode === 'department'"
                  >Create deduction for all employees in a department</span
                >
                <span v-else-if="selectionMode === 'position'"
                  >Create deduction for all employees with a position</span
                >
              </template>
            </div>
          </div>
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text
          class="dialog-content deductions-dialog-content"
          style="max-height: 70vh"
          @keydown.capture="handleDeductionFormKeydown"
        >
          <v-form ref="form" v-model="formValid">
            <v-alert type="info" variant="tonal" density="compact" class="mb-4">
              <template v-slot:prepend>
                <v-icon icon="mdi-information"></v-icon>
              </template>
              <div class="text-caption">
                Fields marked with <strong>*</strong> are required.
              </div>
            </v-alert>

            <v-stepper
              v-model="deductionStep"
              :items="deductionStepItems"
              flat
              density="compact"
              class="mb-4"
            ></v-stepper>

            <v-row v-if="deductionStep === 1">
              <v-col cols="12">
                <div class="section-header">
                  <div class="section-icon">
                    <v-icon size="18">mdi-cash-minus</v-icon>
                  </div>
                  <h3 class="section-title">Deduction Information</h3>
                </div>
              </v-col>

              <!-- Selection Mode (only for new deduction) -->
              <v-col cols="12" v-if="!editMode">
                <v-btn-toggle
                  v-model="selectionMode"
                  color="primary"
                  variant="outlined"
                  divided
                  mandatory
                  class="mb-2"
                >
                  <v-btn value="individual" size="small">
                    <v-icon start>mdi-account</v-icon>
                    Individual
                  </v-btn>
                  <v-btn value="multiple" size="small">
                    <v-icon start>mdi-account-multiple</v-icon>
                    Multiple Employees
                  </v-btn>
                  <v-btn value="department" size="small">
                    <v-icon start>mdi-office-building</v-icon>
                    By Project
                  </v-btn>
                  <v-btn value="position" size="small">
                    <v-icon start>mdi-briefcase</v-icon>
                    By Position
                  </v-btn>
                </v-btn-toggle>
              </v-col>

              <!-- Individual Employee Selection -->
              <v-col
                cols="12"
                v-if="!editMode && selectionMode === 'individual'"
              >
                <v-autocomplete
                  v-model="formData.employee_id"
                  :items="employees"
                  item-title="full_name"
                  item-value="id"
                  label="Search and Select Employee *"
                  placeholder="Search by name, employee number, or position"
                  variant="outlined"
                  density="comfortable"
                  :rules="[rules.required]"
                  clearable
                  prepend-inner-icon="mdi-account-search"
                  hint="Search by name, employee number, or position"
                  persistent-hint
                  no-data-text="No employees found matching your search"
                  :custom-filter="customEmployeeFilter"
                >
                  <template v-slot:item="{ props, item }">
                    <v-list-item v-bind="props">
                      <template v-slot:prepend>
                        <v-avatar color="primary" size="40">
                          <span class="text-white text-subtitle-2">
                            {{ getInitials(item.raw.full_name) }}
                          </span>
                        </v-avatar>
                      </template>
                      <template v-slot:title>
                        <span class="font-weight-medium">{{
                          item.raw.full_name
                        }}</span>
                      </template>
                      <template v-slot:subtitle>
                        <v-chip
                          size="x-small"
                          class="mr-1"
                          color="primary"
                          variant="outlined"
                        >
                          {{ item.raw.employee_number }}
                        </v-chip>
                        <span class="text-caption">{{
                          item.raw.position || "N/A"
                        }}</span>
                      </template>
                    </v-list-item>
                  </template>
                </v-autocomplete>
              </v-col>

              <!-- Multiple Employees Selection -->
              <v-col cols="12" v-if="!editMode && selectionMode === 'multiple'">
                <v-autocomplete
                  v-model="formData.employee_ids"
                  :items="employees"
                  item-title="full_name"
                  item-value="id"
                  label="Select Multiple Employees *"
                  placeholder="Search and select employees"
                  variant="outlined"
                  density="comfortable"
                  :rules="[rules.arrayRequired]"
                  clearable
                  multiple
                  chips
                  closable-chips
                  prepend-inner-icon="mdi-account-multiple"
                  hint="Select multiple employees to apply this deduction"
                  persistent-hint
                  no-data-text="No employees found matching your search"
                  :custom-filter="customEmployeeFilter"
                >
                  <template v-slot:chip="{ item, props }">
                    <v-chip v-bind="props" color="primary" size="small">
                      {{ item.raw.full_name }}
                    </v-chip>
                  </template>
                  <template v-slot:item="{ props, item }">
                    <v-list-item v-bind="props">
                      <template v-slot:prepend>
                        <v-avatar color="primary" size="40">
                          <span class="text-white text-subtitle-2">
                            {{ getInitials(item.raw.full_name) }}
                          </span>
                        </v-avatar>
                      </template>
                      <template v-slot:title>
                        <span class="font-weight-medium">{{
                          item.raw.full_name
                        }}</span>
                      </template>
                      <template v-slot:subtitle>
                        <v-chip
                          size="x-small"
                          class="mr-1"
                          color="primary"
                          variant="outlined"
                        >
                          {{ item.raw.employee_number }}
                        </v-chip>
                        <span class="text-caption">{{
                          item.raw.position || "N/A"
                        }}</span>
                      </template>
                    </v-list-item>
                  </template>
                </v-autocomplete>
              </v-col>

              <!-- Project Selection -->
              <v-col
                cols="12"
                v-if="!editMode && selectionMode === 'department'"
              >
                <v-select
                  v-model="formData.department"
                  :items="departments"
                  item-title="title"
                  item-value="value"
                  label="Select Project *"
                  placeholder="Choose a department"
                  variant="outlined"
                  density="comfortable"
                  :rules="[rules.required]"
                  :loading="loadingDepartments"
                  prepend-inner-icon="mdi-office-building"
                  hint="Deduction will be applied to all active employees in this department"
                  persistent-hint
                  @update:model-value="loadEmployeesByFilter"
                >
                  <template v-slot:prepend-item>
                    <v-list-item>
                      <v-list-item-title class="text-caption text-grey">
                        {{ departments.length }} department(s) available
                      </v-list-item-title>
                    </v-list-item>
                    <v-divider></v-divider>
                  </template>
                </v-select>
              </v-col>

              <!-- Position Selection -->
              <v-col cols="12" v-if="!editMode && selectionMode === 'position'">
                <v-select
                  v-model="formData.position"
                  :items="positions"
                  label="Select Position *"
                  placeholder="Choose a position"
                  variant="outlined"
                  density="comfortable"
                  :rules="[rules.required]"
                  :loading="loadingPositions"
                  prepend-inner-icon="mdi-briefcase"
                  hint="Deduction will be applied to all active employees with this position"
                  persistent-hint
                  @update:model-value="loadEmployeesByFilter"
                >
                  <template v-slot:prepend-item>
                    <v-list-item>
                      <v-list-item-title class="text-caption text-grey">
                        {{ positions.length }} position(s) available
                      </v-list-item-title>
                    </v-list-item>
                    <v-divider></v-divider>
                  </template>
                </v-select>
              </v-col>

              <!-- Show affected employees count -->
              <v-col
                cols="12"
                v-if="
                  !editMode &&
                  (selectionMode === 'department' ||
                    selectionMode === 'position') &&
                  affectedEmployeesCount > 0
                "
              >
                <v-alert
                  type="info"
                  variant="tonal"
                  density="compact"
                  icon="mdi-information"
                >
                  <strong>{{ affectedEmployeesCount }}</strong> active
                  employee(s) will receive this deduction
                </v-alert>
              </v-col>

              <!-- Show selected count for multiple -->
              <v-col
                cols="12"
                v-if="
                  !editMode &&
                  selectionMode === 'multiple' &&
                  formData.employee_ids &&
                  formData.employee_ids.length > 0
                "
              >
                <v-alert
                  type="info"
                  variant="tonal"
                  density="compact"
                  icon="mdi-account-check"
                >
                  <strong>{{ formData.employee_ids.length }}</strong>
                  employee(s) selected
                </v-alert>
              </v-col>

              <!-- Edit Mode: Show employee name only -->
              <v-col cols="12" v-if="editMode">
                <v-text-field
                  :model-value="selectedDeduction?.employee?.full_name"
                  label="Employee"
                  variant="outlined"
                  density="comfortable"
                  readonly
                  prepend-inner-icon="mdi-account"
                  hint="Employee cannot be changed when editing"
                  persistent-hint
                ></v-text-field>
              </v-col>

              <!-- Deduction Type -->
              <v-col cols="12" md="6">
                <v-select
                  v-model="formData.deduction_type"
                  :items="formDeductionTypes"
                  label="Deduction Type *"
                  placeholder="Select deduction type"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-tag"
                  :rules="[rules.required]"
                ></v-select>
              </v-col>

              <v-col
                cols="12"
                md="6"
                v-if="formData.deduction_type === 'custom'"
              >
                <v-text-field
                  v-model="formData.custom_deduction_type"
                  label="Custom Deduction Type *"
                  placeholder="e.g., Equipment Rental"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-tag-plus"
                  :rules="[rules.required]"
                ></v-text-field>
              </v-col>

              <!-- Deduction Name -->
              <v-col cols="12" md="6">
                <v-text-field
                  v-model="formData.deduction_name"
                  label="Deduction Name"
                  placeholder="Auto-generated from type if left blank"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-text"
                  hint="Auto-generated from type if left blank"
                  persistent-hint
                >
                  <template #append-inner>
                    <v-chip size="x-small" color="info">Auto-generated</v-chip>
                  </template>
                </v-text-field>
              </v-col>
            </v-row>

            <v-row v-if="deductionStep === 2">
              <v-col cols="12" class="mt-4">
                <div class="section-header">
                  <div class="section-icon">
                    <v-icon size="18">mdi-currency-php</v-icon>
                  </div>
                  <h3 class="section-title">Amount Details</h3>
                </div>
              </v-col>

              <!-- Total Amount -->
              <v-col cols="12" md="6">
                <v-text-field
                  v-model.number="formData.total_amount"
                  type="number"
                  label="Total Amount *"
                  prefix="₱"
                  placeholder="0.00"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-currency-php"
                  :loading="cashAdvanceAvailabilityLoading"
                  :rules="[rules.required, rules.positive]"
                ></v-text-field>

                <v-alert
                  v-if="showCashAdvanceAvailability"
                  class="mt-2"
                  density="compact"
                  variant="tonal"
                  :type="hasCashAdvanceLimitWarning ? 'warning' : 'info'"
                  :icon="
                    hasCashAdvanceLimitWarning
                      ? 'mdi-alert'
                      : 'mdi-wallet-outline'
                  "
                >
                  <div
                    class="d-flex align-center justify-space-between flex-wrap ga-2"
                  >
                    <span class="text-caption"
                      >Available Balance (Estimated)</span
                    >
                    <strong
                      >₱{{ formatNumber(cashAdvanceAvailableBalance) }}</strong
                    >
                  </div>
                  <div class="text-caption mt-1">
                    Based on approved attendance from
                    <strong>{{ cashAdvanceStartDateLabel }}</strong>
                    to
                    <strong>{{ cashAdvanceAsOfDateLabel }}</strong>
                    <span v-if="cashAdvanceLastPayrollLabel">
                      (last payroll ended {{ cashAdvanceLastPayrollLabel }})
                    </span>
                  </div>
                  <div
                    v-if="
                      cashAdvanceAvailability.active_cash_advance_balance > 0
                    "
                    class="text-caption mt-1"
                  >
                    Current active cash advance balance: ₱{{
                      formatNumber(
                        cashAdvanceAvailability.active_cash_advance_balance,
                      )
                    }}. Suggested additional cap: ₱{{
                      formatNumber(cashAdvanceAvailability.recommended_limit)
                    }}.
                  </div>
                  <div
                    v-if="hasCashAdvanceLimitWarning"
                    class="text-caption mt-1 font-weight-medium"
                  >
                    Entered amount is above the estimated available balance.
                  </div>
                </v-alert>

                <v-alert
                  v-else-if="showCashAdvanceAvailabilityError"
                  class="mt-2"
                  density="compact"
                  variant="tonal"
                  type="warning"
                  icon="mdi-alert-circle-outline"
                >
                  {{ cashAdvanceAvailabilityError }}
                </v-alert>

                <v-alert
                  v-else-if="showCashAdvanceSelectionHint"
                  class="mt-2"
                  density="compact"
                  variant="tonal"
                  type="info"
                  icon="mdi-information-outline"
                >
                  Available balance preview is shown for individual employee
                  selection.
                </v-alert>
              </v-col>

              <!-- Amount per Cutoff -->
              <v-col cols="12" md="6">
                <v-text-field
                  v-model.number="formData.amount_per_cutoff"
                  type="number"
                  label="Amount per Cutoff *"
                  prefix="₱"
                  placeholder="0.00"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-cash"
                  :rules="[rules.required, rules.positive]"
                  hint="Automatically calculated from Total Amount ÷ Installments"
                  persistent-hint
                  readonly
                  :disabled="true"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model.number="formData.installments"
                  label="Number of Installments *"
                  type="number"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-numeric"
                  :rules="[rules.required, rules.positive]"
                  hint="How many payroll periods to deduct"
                  persistent-hint
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="formData.reference_number"
                  label="Reference Number"
                  placeholder="Auto-generated if left blank"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-identifier"
                  hint="Leave blank to auto-generate"
                  persistent-hint
                ></v-text-field>
              </v-col>

              <v-col cols="12" class="mt-4">
                <div class="section-header">
                  <div class="section-icon">
                    <v-icon size="18">mdi-calendar-clock</v-icon>
                  </div>
                  <h3 class="section-title">Payment Schedule</h3>
                </div>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="formData.start_date"
                  label="Start Date *"
                  type="date"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-calendar-start"
                  :rules="[rules.required]"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="formData.end_date"
                  label="End Date"
                  type="date"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-calendar-end"
                  hint="Automatically calculated from start date and installments"
                  persistent-hint
                  readonly
                  :disabled="true"
                ></v-text-field>
              </v-col>

              <v-col cols="12">
                <v-textarea
                  v-model="formData.description"
                  label="Description"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-text-box"
                  rows="2"
                ></v-textarea>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="dialog-actions deductions-dialog-actions">
          <v-spacer></v-spacer>
          <v-btn
            v-if="deductionStep > 1"
            variant="text"
            color="primary"
            @click="deductionStep = deductionStep - 1"
            :disabled="saving"
          >
            Back
          </v-btn>
          <v-btn
            variant="outlined"
            color="grey"
            @click="closeDialog"
            :disabled="saving"
          >
            Cancel
          </v-btn>
          <v-btn
            v-if="deductionStep === 1"
            color="primary"
            variant="flat"
            @click="deductionStep = 2"
            :disabled="!canProceedDeductionStepOne || saving"
          >
            Next
          </v-btn>
          <v-btn
            v-else
            color="#ED985F"
            variant="flat"
            @click="saveDeduction"
            :disabled="!canSaveDeduction || saving"
          >
            <v-progress-circular
              v-if="saving"
              indeterminate
              size="16"
              width="2"
              class="mr-2"
            ></v-progress-circular>
            <v-icon v-else size="18" class="mr-1">{{
              editMode ? "mdi-check" : "mdi-content-save"
            }}</v-icon>
            {{
              saving
                ? editMode
                  ? "Updating..."
                  : "Creating..."
                : editMode
                  ? "Update Deduction"
                  : "Create Deduction"
            }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete Confirmation Dialog -->
    <v-dialog v-model="deleteDialog" max-width="400px">
      <v-card>
        <v-card-title class="text-h5">Confirm Delete</v-card-title>
        <v-card-text>
          Are you sure you want to delete this deduction? This action cannot be
          undone.
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="deleteDialog = false">Cancel</v-btn>
          <v-btn
            color="error"
            variant="flat"
            :loading="deleting"
            @click="deleteDeduction"
          >
            Delete
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Details Dialog -->
    <v-dialog v-model="detailsDialog" max-width="700px" scrollable>
      <v-card v-if="selectedDeduction" class="modern-dialog">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper primary">
            <v-icon size="24">mdi-cash-minus</v-icon>
          </div>
          <div>
            <div class="dialog-title">Deduction Details</div>
            <div class="dialog-subtitle">
              View deduction information and payment progress
            </div>
          </div>
        </v-card-title>

        <v-divider></v-divider>

        <v-card-text class="dialog-content" style="max-height: 70vh">
          <v-row>
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">Employee</div>
              <div class="font-weight-medium">
                {{ selectedDeduction.employee?.full_name }}
              </div>
            </v-col>
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">
                Deduction Type
              </div>
              <v-chip
                :color="getDeductionTypeColor(selectedDeduction.deduction_type)"
                size="small"
                variant="tonal"
              >
                {{ formatDeductionType(selectedDeduction.deduction_type) }}
              </v-chip>
            </v-col>
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">
                Deduction Name
              </div>
              <div class="font-weight-medium">
                {{ selectedDeduction.deduction_name }}
              </div>
            </v-col>
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">Status</div>
              <v-chip
                :color="getStatusColor(selectedDeduction.status)"
                size="small"
                variant="flat"
              >
                {{ formatStatus(selectedDeduction.status) }}
              </v-chip>
            </v-col>

            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">Total Amount</div>
              <div class="font-weight-bold text-primary">
                ₱{{ formatNumber(selectedDeduction.total_amount) }}
              </div>
            </v-col>
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">Per Cutoff</div>
              <div class="font-weight-medium">
                ₱{{ formatNumber(selectedDeduction.amount_per_cutoff) }}
              </div>
            </v-col>
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">Balance</div>
              <div
                :class="
                  selectedDeduction.balance > 0
                    ? 'font-weight-bold text-error'
                    : 'font-weight-bold text-success'
                "
              >
                ₱{{ formatNumber(selectedDeduction.balance) }}
              </div>
            </v-col>
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">Progress</div>
              <div class="font-weight-medium">
                {{ selectedDeduction.installments_paid }}/{{
                  selectedDeduction.installments
                }}
                payments
              </div>
            </v-col>

            <v-col cols="12">
              <v-progress-linear
                :model-value="getProgress(selectedDeduction)"
                :color="
                  selectedDeduction.status === 'completed'
                    ? 'success'
                    : 'primary'
                "
                height="10"
                rounded
              ></v-progress-linear>
            </v-col>

            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">Start Date</div>
              <div class="font-weight-medium">
                {{ formatDate(selectedDeduction.start_date) }}
              </div>
            </v-col>
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">End Date</div>
              <div class="font-weight-medium">
                {{ formatDate(selectedDeduction.end_date) }}
              </div>
            </v-col>

            <v-col
              cols="12"
              v-if="selectedDeduction.reference_number"
              class="mt-2"
            >
              <v-alert color="info" variant="tonal" density="compact">
                <div class="text-caption text-medium-emphasis">
                  Reference Number
                </div>
                <div class="font-weight-medium">
                  {{ selectedDeduction.reference_number }}
                </div>
              </v-alert>
            </v-col>

            <v-col cols="12" v-if="selectedDeduction.description" class="mt-2">
              <v-alert color="grey" variant="tonal" density="compact">
                <div class="text-caption text-medium-emphasis">Description</div>
                <div class="font-weight-medium">
                  {{ selectedDeduction.description }}
                </div>
              </v-alert>
            </v-col>
          </v-row>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="dialog-actions deductions-dialog-actions">
          <v-spacer></v-spacer>
          <v-btn variant="outlined" color="grey" @click="detailsDialog = false">
            Close
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onBeforeUnmount, watch } from "vue";
import { useRoute } from "vue-router";
import { useToast } from "vue-toastification";
import deductionService from "@/services/deductionService";
import moduleAccessService from "@/services/moduleAccessService";
import api from "@/services/api";
import { useAuthStore } from "@/stores/auth";
import { formatDate, formatNumber } from "@/utils/formatters";
import { devLog } from "@/utils/devLog";
import { useKeyboardFirstFlow } from "@/composables/useKeyboardFirstFlow";

const toast = useToast();
const route = useRoute();
const authStore = useAuthStore();

// User role
const userRole = computed(() => authStore.user?.role);
const isAdminOrHr = computed(() => ["admin", "hr"].includes(userRole.value));

// Access control
const accessStatus = ref("none");
const accessMessage = ref("");
const requestDialog = ref(false);
const requestReason = ref("");
const submittingRequest = ref(false);
const myRequests = ref([]);
const requestsPanel = ref(null);
const hasAccess = computed(
  () =>
    isAdminOrHr.value ||
    accessStatus.value === "approved" ||
    accessStatus.value === "admin" ||
    userRole.value === "employee",
);

const getRequestStatusColor = (status) => {
  const colors = { pending: "warning", approved: "success", rejected: "error" };
  return colors[status] || "grey";
};

const checkDeductionAccess = async () => {
  if (isAdminOrHr.value || userRole.value === "employee") return;
  try {
    const response = await moduleAccessService.checkAccess("deductions");
    accessStatus.value = response.status;
    accessMessage.value = response.message || "";
  } catch {
    accessStatus.value = "none";
  }
};

const loadMyRequests = async () => {
  if (isAdminOrHr.value || userRole.value === "employee") return;
  try {
    const response = await moduleAccessService.getRequests("deductions");
    myRequests.value = response.data || [];
  } catch {
    myRequests.value = [];
  }
};

const submitAccessRequest = async () => {
  if (!requestReason.value) return;
  submittingRequest.value = true;
  try {
    await moduleAccessService.submitRequest("deductions", {
      reason: requestReason.value,
    });
    toast.success("Access request submitted successfully");
    requestDialog.value = false;
    requestReason.value = "";
    accessStatus.value = "pending";
    await loadMyRequests();
  } catch (error) {
    const msg = error.response?.data?.message || "Failed to submit request";
    toast.error(msg);
  } finally {
    submittingRequest.value = false;
  }
};

// Data
const deductions = ref([]);
const employees = ref([]);
const departments = ref([]);
const positions = ref([]);
const loading = ref(false);
const saving = ref(false);
const deleting = ref(false);
const loadingEmployees = ref(false);
const loadingDepartments = ref(false);
const loadingPositions = ref(false);
const dialog = ref(false);
const deleteDialog = ref(false);
const detailsDialog = ref(false);
const editMode = ref(false);
const formValid = ref(false);
const form = ref(null);
const selectedDeduction = ref(null);
const selectionMode = ref("individual");
const affectedEmployeesCount = ref(0);
const deductionStep = ref(1);
const deductionStepItems = ["Employee & Type", "Amount & Schedule"];
const cashAdvanceAvailability = ref(null);
const cashAdvanceAvailabilityLoading = ref(false);
const cashAdvanceAvailabilityError = ref("");

// Filters
const filters = ref({
  search: "",
  department: null,
  position: null,
  deduction_type: null,
  status: null,
});

// Form data
const defaultFormData = {
  employee_id: null,
  employee_ids: [],
  department: null,
  position: null,
  deduction_type: null,
  custom_deduction_type: "",
  deduction_name: "",
  total_amount: null,
  amount_per_cutoff: null,
  installments: null,
  start_date: new Date().toISOString().split("T")[0],
  end_date: null,
  description: "",
  reference_number: "",
};

const formData = ref({ ...defaultFormData });

const canProceedDeductionStepOne = computed(() => {
  const hasType =
    !!formData.value.deduction_type &&
    (formData.value.deduction_type !== "custom" ||
      !!formData.value.custom_deduction_type);

  if (!hasType) return false;

  if (editMode.value) return true;

  if (selectionMode.value === "individual") {
    return !!formData.value.employee_id;
  }

  if (selectionMode.value === "multiple") {
    return (
      Array.isArray(formData.value.employee_ids) &&
      formData.value.employee_ids.length > 0
    );
  }

  if (selectionMode.value === "department") {
    return !!formData.value.department;
  }

  if (selectionMode.value === "position") {
    return !!formData.value.position;
  }

  return false;
});

const canSaveDeduction = computed(() => {
  return (
    canProceedDeductionStepOne.value &&
    Number(formData.value.total_amount) > 0 &&
    Number(formData.value.installments) > 0 &&
    !!formData.value.start_date
  );
});

const isCashAdvanceType = computed(
  () => formData.value.deduction_type === "cash_advance",
);

const isIndividualCashAdvanceContext = computed(() => {
  if (!dialog.value || !isCashAdvanceType.value) return false;
  if (editMode.value) return true;
  return selectionMode.value === "individual";
});

const shouldFetchCashAdvanceAvailability = computed(() => {
  return isIndividualCashAdvanceContext.value && !!formData.value.employee_id;
});

const showCashAdvanceAvailability = computed(() => {
  return (
    shouldFetchCashAdvanceAvailability.value && !!cashAdvanceAvailability.value
  );
});

const showCashAdvanceAvailabilityError = computed(() => {
  return (
    shouldFetchCashAdvanceAvailability.value &&
    !!cashAdvanceAvailabilityError.value
  );
});

const showCashAdvanceSelectionHint = computed(() => {
  return (
    dialog.value &&
    isCashAdvanceType.value &&
    !editMode.value &&
    selectionMode.value !== "individual"
  );
});

const cashAdvanceAvailableBalance = computed(() => {
  return Number(cashAdvanceAvailability.value?.available_balance || 0);
});

const hasCashAdvanceLimitWarning = computed(() => {
  if (!showCashAdvanceAvailability.value) return false;
  return (
    Number(formData.value.total_amount || 0) > cashAdvanceAvailableBalance.value
  );
});

const cashAdvanceStartDateLabel = computed(() => {
  const date = cashAdvanceAvailability.value?.start_date;
  return date ? formatDate(date) : "N/A";
});

const cashAdvanceAsOfDateLabel = computed(() => {
  const date = cashAdvanceAvailability.value?.as_of_date;
  return date ? formatDate(date) : "N/A";
});

const cashAdvanceLastPayrollLabel = computed(() => {
  const date = cashAdvanceAvailability.value?.last_payroll?.period_end;
  return date ? formatDate(date) : "";
});

const { handleKeydown: handleDeductionFormKeydown } = useKeyboardFirstFlow({
  onEscape: () => {
    if (!saving.value) closeDialog();
  },
  onSubmitLast: () => {
    if (!saving.value && canSaveDeduction.value) {
      saveDeduction();
    }
  },
});

// Computed - Filter deductions by category tab
const filteredDeductions = computed(() => {
  return deductions.value;
});

const hasActiveFilters = computed(() => {
  return (
    !!filters.value.search ||
    !!filters.value.department ||
    !!filters.value.position ||
    !!filters.value.deduction_type ||
    !!filters.value.status
  );
});

const activeFilterCount = computed(() => {
  return [
    filters.value.search,
    filters.value.department,
    filters.value.position,
    filters.value.deduction_type,
    filters.value.status,
  ].filter(Boolean).length;
});

// Custom filter for employee autocomplete
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

// Headers
const headers = computed(() => {
  const baseHeaders = [
    { title: "Type", key: "deduction_type", sortable: true },
    { title: "Name", key: "deduction_name", sortable: true },
    { title: "Total", key: "total_amount", sortable: true },
    { title: "Per Cutoff", key: "amount_per_cutoff", sortable: true },
    { title: "Balance", key: "balance", sortable: true },
    { title: "Progress", key: "progress", sortable: false },
    { title: "Status", key: "status", sortable: true },
    { title: "Actions", key: "actions", sortable: false, align: "center" },
  ];

  if (userRole.value !== "employee") {
    baseHeaders.unshift({
      title: "Employee",
      key: "employee",
      sortable: false,
    });
  }

  return baseHeaders;
});

// Options
const baseDeductionTypes = [
  { title: "PPE (Personal Protective Equipment)", value: "ppe" },
  { title: "Tools", value: "tools" },
  { title: "Uniform", value: "uniform" },
  { title: "Absence", value: "absence" },
  { title: "Cash Advance", value: "cash_advance" },
  { title: "Damages", value: "damages" },
  { title: "Insurance", value: "insurance" },
  { title: "Cooperative", value: "cooperative" },
  { title: "Loan Repayment", value: "loan" },
  { title: "Other", value: "other" },
];

const formDeductionTypes = [
  ...baseDeductionTypes,
  { title: "Custom", value: "custom" },
];

const filterDeductionTypes = [...baseDeductionTypes];

const statusOptions = [
  { title: "Active", value: "active" },
  { title: "Completed", value: "completed" },
  { title: "Cancelled", value: "cancelled" },
];

// Validation rules
const rules = {
  required: (value) => !!value || "This field is required",
  arrayRequired: (value) =>
    (Array.isArray(value) && value.length > 0) ||
    "Please select at least one employee",
  positive: (value) => value > 0 || "Must be greater than 0",
};

// Watch selection mode to reset related fields
watch(selectionMode, (newMode) => {
  affectedEmployeesCount.value = 0;
  formData.value.employee_id = null;
  formData.value.employee_ids = [];
  formData.value.department = null;
  formData.value.position = null;
});

const resetCashAdvanceAvailability = () => {
  cashAdvanceAvailability.value = null;
  cashAdvanceAvailabilityLoading.value = false;
  cashAdvanceAvailabilityError.value = "";
};

const fetchCashAdvanceAvailability = async () => {
  if (!shouldFetchCashAdvanceAvailability.value) {
    resetCashAdvanceAvailability();
    return;
  }

  cashAdvanceAvailabilityLoading.value = true;
  cashAdvanceAvailabilityError.value = "";

  try {
    const response = await deductionService.getCashAdvanceAvailability(
      formData.value.employee_id,
    );
    cashAdvanceAvailability.value = response.data;
  } catch (error) {
    cashAdvanceAvailability.value = null;
    cashAdvanceAvailabilityError.value =
      error.response?.data?.message ||
      "Unable to load available balance estimate";
  } finally {
    cashAdvanceAvailabilityLoading.value = false;
  }
};

watch(
  () => [
    dialog.value,
    editMode.value,
    selectionMode.value,
    formData.value.deduction_type,
    formData.value.employee_id,
  ],
  () => {
    if (!dialog.value || !isCashAdvanceType.value) {
      resetCashAdvanceAvailability();
      return;
    }

    if (!isIndividualCashAdvanceContext.value) {
      resetCashAdvanceAvailability();
      return;
    }

    fetchCashAdvanceAvailability();
  },
);

// Watch total_amount and installments to auto-calculate amount_per_cutoff
watch(
  () => [formData.value.total_amount, formData.value.installments],
  ([totalAmount, installments]) => {
    if (totalAmount > 0 && installments > 0) {
      formData.value.amount_per_cutoff = parseFloat(
        (totalAmount / installments).toFixed(2),
      );
    } else {
      formData.value.amount_per_cutoff = null;
    }
  },
);

// Watch start_date and installments to auto-calculate end_date
watch(
  () => [formData.value.start_date, formData.value.installments],
  ([startDate, installments]) => {
    if (startDate && installments > 0) {
      const start = new Date(startDate);
      // Each installment is a semi-monthly period (approximately 15 days)
      const installmentsInMonths = Math.ceil(installments / 2);
      const endDate = new Date(start);
      endDate.setMonth(endDate.getMonth() + installmentsInMonths);
      formData.value.end_date = endDate.toISOString().split("T")[0];
    } else {
      formData.value.end_date = null;
    }
  },
);

// Fetch deductions
const fetchDeductions = async () => {
  loading.value = true;
  try {
    const params = {
      paginate: false,
      per_page: 3000,
    };
    if (filters.value.search) params.search = filters.value.search;
    if (filters.value.department) params.department = filters.value.department;
    if (filters.value.position) params.position = filters.value.position;
    if (filters.value.deduction_type)
      params.deduction_type = filters.value.deduction_type;
    if (filters.value.status) params.status = filters.value.status;

    const response = await deductionService.getDeductions(params);
    deductions.value = response.data.data || response.data;
  } catch (error) {
    toast.error("Failed to load deductions");
    devLog.error(error);
  } finally {
    loading.value = false;
  }
};

// Debounce search to avoid too many API calls
let searchTimeout = null;
const debouncedSearch = () => {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    fetchDeductions();
  }, 500);
};

// Fetch employees
const fetchEmployees = async () => {
  if (loadingEmployees.value) return;

  loadingEmployees.value = true;
  try {
    const response = await api.get("/employees", {
      params: {
        per_page: 2000,
        activity_status: "active,on_leave",
      },
    });

    // Handle both paginated and non-paginated responses
    if (response.data.data) {
      employees.value = response.data.data;
    } else if (Array.isArray(response.data)) {
      employees.value = response.data;
    } else {
      employees.value = [];
    }
  } catch (error) {
    devLog.error("Failed to load employees:", error);
    toast.error("Failed to load employees");
  } finally {
    loadingEmployees.value = false;
  }
};

const ensureEmployeesLoaded = async () => {
  if (userRole.value === "employee") return;
  if (employees.value.length > 0 || loadingEmployees.value) return;
  await fetchEmployees();
};

const runWhenIdle = (callback) => {
  if (typeof window !== "undefined" && "requestIdleCallback" in window) {
    window.requestIdleCallback(() => callback());
    return;
  }
  setTimeout(() => callback(), 250);
};

// Fetch departments
const fetchDepartments = async () => {
  loadingDepartments.value = true;
  try {
    const response = await deductionService.getDepartments();
    departments.value = response.data;
  } catch (error) {
    devLog.error("Failed to load projects:", error);
    toast.error("Failed to load projects");
  } finally {
    loadingDepartments.value = false;
  }
};

// Fetch positions
const fetchPositions = async () => {
  loadingPositions.value = true;
  try {
    const response = await deductionService.getPositions();
    positions.value = response.data;
  } catch (error) {
    devLog.error("Failed to load positions:", error);
    toast.error("Failed to load positions");
  } finally {
    loadingPositions.value = false;
  }
};

// Load employees by department or position
const loadEmployeesByFilter = async () => {
  try {
    const filters = {};
    if (selectionMode.value === "department" && formData.value.department) {
      filters.department = formData.value.department;
    } else if (selectionMode.value === "position" && formData.value.position) {
      filters.position = formData.value.position;
    } else {
      affectedEmployeesCount.value = 0;
      return;
    }

    const response = await deductionService.getEmployeesByFilter(filters);
    affectedEmployeesCount.value = response.data.count || 0;
  } catch (error) {
    devLog.error("Failed to load employees by filter:", error);
    affectedEmployeesCount.value = 0;
  }
};

// Open dialogs
const openAddDialog = () => {
  ensureEmployeesLoaded();
  editMode.value = false;
  deductionStep.value = 1;
  selectionMode.value = "individual";
  affectedEmployeesCount.value = 0;
  formData.value = { ...defaultFormData };
  resetCashAdvanceAvailability();
  dialog.value = true;
};

const openEditDialog = (deduction) => {
  ensureEmployeesLoaded();
  editMode.value = true;
  deductionStep.value = 1;
  selectedDeduction.value = deduction;
  const baseTypeValues = baseDeductionTypes.map((type) => type.value);
  const isKnownType = baseTypeValues.includes(deduction.deduction_type);
  formData.value = {
    employee_id: deduction.employee_id,
    deduction_type: isKnownType ? deduction.deduction_type : "custom",
    custom_deduction_type: isKnownType ? "" : deduction.deduction_type,
    deduction_name: deduction.deduction_name,
    total_amount: deduction.total_amount,
    amount_per_cutoff: deduction.amount_per_cutoff,
    installments: deduction.installments,
    start_date: deduction.start_date,
    end_date: deduction.end_date,
    description: deduction.description,
    reference_number: deduction.reference_number,
  };
  resetCashAdvanceAvailability();
  dialog.value = true;
};

const closeDialog = () => {
  dialog.value = false;
  editMode.value = false;
  deductionStep.value = 1;
  selectionMode.value = "individual";
  affectedEmployeesCount.value = 0;
  formData.value = { ...defaultFormData };
  resetCashAdvanceAvailability();
  if (form.value) form.value.resetValidation();
};

// Save deduction
const saveDeduction = async () => {
  if (!form.value) return;

  const { valid } = await form.value.validate();
  if (!valid) return;

  saving.value = true;
  try {
    const payload = { ...formData.value };
    if (payload.deduction_type === "custom") {
      const customType = (payload.custom_deduction_type || "").trim();
      payload.deduction_type = normalizeDeductionType(customType);
      if (!payload.deduction_name) {
        payload.deduction_name = customType;
      }
    }
    delete payload.custom_deduction_type;

    if (editMode.value) {
      // Edit mode - single update
      await deductionService.updateDeduction(
        selectedDeduction.value.id,
        payload,
      );
      toast.success("Deduction updated successfully");
    } else {
      // Create mode - check selection mode
      if (selectionMode.value === "individual") {
        // Single employee
        await deductionService.createDeduction(payload);
        toast.success("Deduction created successfully");
      } else {
        // Bulk creation (multiple, department, or position)
        const bulkData = {
          selection_mode: selectionMode.value,
          ...payload,
        };

        const response = await deductionService.bulkCreateDeductions(bulkData);
        const count = response.data.data?.count || 0;
        toast.success(
          `Successfully created deductions for ${count} employee(s)`,
        );
      }
    }
    closeDialog();
    fetchDeductions();
  } catch (error) {
    toast.error(error.response?.data?.message || "Failed to save deduction");
    devLog.error(error);
  } finally {
    saving.value = false;
  }
};

// Delete deduction
const confirmDelete = (deduction) => {
  selectedDeduction.value = deduction;
  deleteDialog.value = true;
};

const deleteDeduction = async () => {
  deleting.value = true;
  try {
    await deductionService.deleteDeduction(selectedDeduction.value.id);
    toast.success("Deduction deleted successfully");
    deleteDialog.value = false;
    fetchDeductions();
  } catch (error) {
    toast.error(error.response?.data?.message || "Failed to delete deduction");
    devLog.error(error);
  } finally {
    deleting.value = false;
  }
};

// View details
const viewDetails = (deduction) => {
  selectedDeduction.value = deduction;
  detailsDialog.value = true;
};

// Clear filters
const clearFilters = () => {
  filters.value = {
    search: "",
    department: null,
    position: null,
    deduction_type: null,
    status: null,
  };
  fetchDeductions();
};

// Formatters
const formatDeductionType = (type) => {
  const types = {
    ppe: "PPE",
    tools: "Tools",
    uniform: "Uniform",
    absence: "Absence",
    sss: "SSS",
    philhealth: "PhilHealth",
    pagibig: "Pag-IBIG",
    tax: "Tax",
    loan: "Loan",
    cash_advance: "Cash Advance",
    damages: "Damages",
    insurance: "Insurance",
    cooperative: "Cooperative",
    other: "Other",
  };
  if (types[type]) return types[type];
  if (!type) return "";
  return type
    .toString()
    .replace(/_/g, " ")
    .replace(/\b\w/g, (char) => char.toUpperCase());
};

const normalizeDeductionType = (value) => {
  return (
    value
      .toString()
      .trim()
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, "_")
      .replace(/^_+|_+$/g, "") || "other"
  );
};

const formatStatus = (status) => {
  return status.charAt(0).toUpperCase() + status.slice(1);
};

const getInitials = (name) => {
  if (!name) return "??";
  const parts = name.trim().split(" ");
  if (parts.length === 1) return parts[0].substring(0, 2).toUpperCase();
  return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
};

const getDeductionTypeColor = (type) => {
  const governmentTypes = ["sss", "philhealth", "pagibig", "tax"];
  if (governmentTypes.includes(type)) return "blue";

  const colors = {
    ppe: "orange",
    tools: "purple",
    uniform: "green",
    absence: "red",
    cash_advance: "deep-orange",
    damages: "pink",
    insurance: "teal",
    cooperative: "indigo",
    loan: "cyan",
    other: "grey",
  };
  return colors[type] || "grey";
};

const getStatusColor = (status) => {
  const colors = {
    active: "success",
    completed: "info",
    cancelled: "error",
  };
  return colors[status] || "grey";
};

const getProgress = (deduction) => {
  const installments = Number(deduction.installments) || 0;
  const installmentsPaid = Number(deduction.installments_paid) || 0;

  if (installments <= 0) return 0;
  return Math.min(100, Math.max(0, (installmentsPaid / installments) * 100));
};

// Lifecycle
onMounted(async () => {
  // Check access first for payrollist
  await checkDeductionAccess();
  loadMyRequests();

  if (hasAccess.value) {
    fetchDeductions();
    if (userRole.value !== "employee") {
      runWhenIdle(() => {
        ensureEmployeesLoaded();
      });
      fetchDepartments();
      fetchPositions();
    }
  }

  // Refresh data when user returns to the tab
  document.addEventListener("visibilitychange", handleVisibilityChange);
});

// Refresh data when user returns to the tab
const handleVisibilityChange = () => {
  if (!document.hidden) {
    fetchDeductions();
  }
};

// Cleanup on component unmount
onBeforeUnmount(() => {
  document.removeEventListener("visibilitychange", handleVisibilityChange);
});
</script>

<style lang="scss" scoped>
.deductions-page {
  background-color: #f8f9fa;
  min-height: 100vh;
}

.modern-card {
  padding: 24px;
  background: white;
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid rgba(0, 31, 61, 0.08);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.page-header {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 32px;
  padding-bottom: 24px;
  border-bottom: 1px solid rgba(0, 31, 61, 0.08);
}

.page-icon-badge {
  width: 48px;
  height: 48px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);
  flex-shrink: 0;
}

.page-header-content {
  flex: 1;
}

.page-title {
  font-size: 24px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
  line-height: 1.2;
}

.page-subtitle {
  font-size: 14px;
  color: #64748b;
  margin: 4px 0 0 0;
}

.action-button {
  text-transform: none;
  font-weight: 600;
  letter-spacing: 0;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.2);
  transition: all 0.2s ease;

  &:hover {
    box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);
    transform: translateY(-1px);
  }
}

.filters-section {
  margin-bottom: 24px;
}

.modern-table {
  border-radius: 12px;
  overflow: hidden;

  :deep(th) {
    background-color: #f8f9fa !important;
    color: #001f3d !important;
    font-weight: 600 !important;
    text-transform: uppercase;
    font-size: 12px;
    letter-spacing: 0.5px;
  }

  :deep(.v-data-table__tr:hover) {
    background-color: rgba(237, 152, 95, 0.04) !important;
  }
}

/* Modern Dialog Styles */
.modern-dialog {
  border-radius: 16px !important;
  overflow: hidden;
}

.deductions-dialog-header {
  padding: 16px 20px;
}

.deductions-dialog-content {
  padding: 16px 20px 10px;
}

.deductions-dialog-actions {
  position: sticky;
  bottom: 0;
  z-index: 2;
  background: #ffffff;
  border-top: 1px solid rgba(0, 31, 61, 0.08);
}

.dialog-header {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 24px;
  background: white;
  border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

.dialog-icon-wrapper {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;

  &.primary {
    background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
    box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);

    .v-icon {
      color: white;
    }
  }
}

.dialog-title {
  font-size: 20px;
  font-weight: 700;
  color: #001f3d;
  line-height: 1.2;
}

.dialog-subtitle {
  font-size: 14px;
  color: #64748b;
  margin-top: 4px;
}

.dialog-content {
  padding: 24px;
  background: #fafafa;
}

.section-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 16px;
  background: linear-gradient(
    135deg,
    rgba(0, 31, 61, 0.02) 0%,
    rgba(237, 152, 95, 0.02) 100%
  );
  border-radius: 8px;
  margin-bottom: 16px;
}

.section-icon {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.25);
  flex-shrink: 0;

  .v-icon {
    color: white;
  }
}

.section-title {
  font-size: 16px;
  font-weight: 600;
  color: #001f3d;
  margin: 0;
}

.dialog-actions {
  padding: 16px 24px;
  background: rgba(0, 31, 61, 0.02);
  gap: 12px;
}

.form-field-wrapper {
  position: relative;
}

.form-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 600;
  color: #424242;
  margin-bottom: 8px;
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
</style>
