<template>
  <div class="payroll-detail-page">
    <v-overlay :model-value="loading" class="align-center justify-center">
      <v-progress-circular
        indeterminate
        color="#ED985F"
        :size="70"
        :width="7"
      ></v-progress-circular>
    </v-overlay>

    <div v-if="!loading">
      <!-- Modern Page Header -->
      <div class="page-header">
        <div class="header-content">
          <div class="back-button-wrapper">
            <button class="back-button" @click="$router.push('/payroll')">
              <v-icon size="20">mdi-arrow-left</v-icon>
              <span>Back to Payroll List</span>
            </button>
          </div>

          <div class="header-main">
            <div class="page-title-section">
              <div class="page-icon-badge">
                <v-icon size="22">mdi-file-document-outline</v-icon>
              </div>
              <div>
                <h1 class="page-title">{{ payroll?.payroll_number }}</h1>
                <p class="page-subtitle">{{ payroll?.period_name }}</p>
              </div>
            </div>
            <div class="action-buttons">
              <div class="status-badge" :class="`status-${payroll?.status}`">
                <v-icon size="16">{{ getStatusIcon(payroll?.status) }}</v-icon>
                <span>{{ payroll?.status?.toUpperCase() }}</span>
              </div>
              <button
                v-if="payroll?.status === 'draft'"
                class="action-btn action-btn-success"
                @click="finalizePayroll"
                :disabled="finalizing"
              >
                <v-progress-circular
                  v-if="finalizing"
                  indeterminate
                  size="16"
                  width="2"
                  class="mr-2"
                ></v-progress-circular>
                <v-icon v-else size="20">mdi-check-circle</v-icon>
                <span>Finalize Payroll</span>
              </button>
              <button
                v-if="payroll?.status === 'draft'"
                class="action-btn action-btn-reprocess"
                @click="reprocessPayroll"
                :disabled="reprocessing || finalizing"
              >
                <v-progress-circular
                  v-if="reprocessing"
                  indeterminate
                  size="16"
                  width="2"
                  class="mr-2"
                ></v-progress-circular>
                <v-icon v-else size="20">mdi-refresh</v-icon>
                <span>Reprocess Payroll</span>
              </button>
              <button
                class="action-btn action-btn-primary"
                @click="openExportDialog"
              >
                <v-icon size="20">mdi-download</v-icon>
                <span>Download Register</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Modern Stats Grid -->
      <div class="stats-grid">
        <div class="stat-card info-card">
          <div class="stat-icon period">
            <v-icon size="20">mdi-calendar-range</v-icon>
          </div>
          <div class="stat-content">
            <div class="stat-label">Pay Period</div>
            <div class="stat-value-text">
              {{ formatDate(payroll?.period_start) }} -
              {{ formatDate(payroll?.period_end) }}
            </div>
          </div>
        </div>
        <div class="stat-card info-card">
          <div class="stat-icon payment">
            <v-icon size="20">mdi-calendar-clock</v-icon>
          </div>
          <div class="stat-content">
            <div class="stat-label">Payment Date</div>
            <div class="stat-value-text">
              {{ formatDate(payroll?.payment_date) }}
            </div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon employees">
            <v-icon size="20">mdi-account-group</v-icon>
          </div>
          <div class="stat-content">
            <div class="stat-label">Employees</div>
            <div class="stat-value">{{ payroll?.items?.length || 0 }}</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon gross">
            <v-icon size="20">mdi-cash-plus</v-icon>
          </div>
          <div class="stat-content">
            <div class="stat-label">Total Gross Pay</div>
            <div class="stat-value primary">
              ₱{{ formatCurrency(payroll?.total_gross) }}
            </div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon net">
            <v-icon size="20">mdi-cash-check</v-icon>
          </div>
          <div class="stat-content">
            <div class="stat-label">Total Net Pay</div>
            <div class="stat-value success">
              ₱{{ formatCurrency(payroll?.total_net) }}
            </div>
          </div>
        </div>
      </div>

      <!-- Employee Payroll Table -->
      <div class="modern-card">
        <div class="card-header">
          <div class="header-left">
            <div class="header-icon">
              <v-icon size="20">mdi-account-group</v-icon>
            </div>
            <div>
              <h2 class="card-title">Employee Payroll Details</h2>
              <p class="card-subtitle">
                Detailed breakdown of employee earnings and deductions
              </p>
            </div>
          </div>
          <div class="header-right">
            <v-select
              v-model="viewMode"
              :items="viewModeOptions"
              item-title="title"
              item-value="value"
              label="View"
              prepend-inner-icon="mdi-view-dashboard-outline"
              variant="outlined"
              density="comfortable"
              hide-details
              style="min-width: 220px"
              @update:model-value="handleViewModeChange"
            ></v-select>
            <v-select
              v-if="viewMode === 'device'"
              v-model="deviceFilter"
              :items="availableDevices"
              label="Filter by Device"
              prepend-inner-icon="mdi-devices"
              variant="outlined"
              density="comfortable"
              clearable
              hide-details
              style="min-width: 250px"
            ></v-select>
            <v-text-field
              v-model="search"
              prepend-inner-icon="mdi-magnify"
              label="Search employee..."
              clearable
              variant="outlined"
              density="comfortable"
              hide-details
              style="min-width: 300px"
            ></v-text-field>
          </div>
        </div>

        <v-alert
          v-if="viewMode === 'device'"
          type="info"
          variant="tonal"
          density="comfortable"
          class="mt-3"
        >
          Device split is based on attendance device hours in this payroll
          period. Values shown per row are prorated device shares of the
          employee total.
        </v-alert>

        <v-alert
          v-if="
            isPayrollist &&
            !hasPayrollItemEditAccess &&
            payrollEditAccessStatus !== 'admin'
          "
          :type="
            payrollEditAccessStatus === 'pending'
              ? 'warning'
              : payrollEditAccessStatus === 'rejected'
                ? 'error'
                : 'info'
          "
          variant="tonal"
          density="comfortable"
          class="mt-3"
        >
          <div>
            <strong>Payroll Item Edit Access</strong>
          </div>
          <div class="mt-1">
            {{
              payrollEditAccessMessage ||
              "You need admin approval before editing payroll item details."
            }}
          </div>
          <v-btn
            v-if="payrollEditAccessStatus !== 'pending'"
            class="mt-3"
            color="primary"
            variant="flat"
            prepend-icon="mdi-send"
            :loading="submittingPayrollEditAccessRequest"
            @click="showPayrollEditAccessRequestDialog = true"
          >
            Request Access
          </v-btn>
        </v-alert>

        <div class="table-section">
          <v-data-table
            :headers="headers"
            :items="previewItems"
            :items-per-page="10"
            density="compact"
            class="modern-table register-preview"
          >
            <template v-slot:item.name="{ item }">
              <div>
                <div class="font-weight-medium">
                  {{ item._rowNo }}. {{ getEmployeeName(item) }}
                </div>
                <div class="text-caption text-medium-emphasis name-meta">
                  <span>{{ item.employee?.employee_number }}</span>
                  <v-chip
                    v-if="isItemEdited(item)"
                    size="x-small"
                    color="#ED985F"
                    variant="tonal"
                    class="edited-chip"
                  >
                    Edited
                  </v-chip>
                </div>
              </div>
            </template>

            <template v-slot:item.device="{ item }">
              <div>
                <div class="font-weight-medium">
                  {{ item._deviceName || "Unassigned" }}
                </div>
                <div
                  v-if="item._deviceDesignation || item._deviceLocation"
                  class="text-caption text-medium-emphasis"
                >
                  {{ item._deviceDesignation || "N/A" }} &middot;
                  {{ item._deviceLocation || "N/A" }}
                </div>
              </div>
            </template>

            <template v-slot:item.rate="{ item }">
              <div class="text-right">
                {{ displayMoney(item.effective_rate || item.rate) }}
              </div>
            </template>

            <template v-slot:item.no_of_days="{ item }">
              <div class="text-center">{{ displayDays(item.days_worked) }}</div>
            </template>

            <template v-slot:item.amount="{ item }">
              <div class="text-right">
                {{ displayMoney(getRegisterAmount(item)) }}
              </div>
            </template>

            <template v-slot:item.ot_hrs="{ item }">
              <div class="text-center">
                {{ displayHours(item.regular_ot_hours, true) }}
              </div>
            </template>

            <template v-slot:item.reg_ot="{ item }">
              <div class="text-right">
                {{ displayMoney(item.regular_ot_pay, true) }}
              </div>
            </template>

            <template v-slot:item.sun_hol_hrs="{ item }">
              <div class="text-center">
                {{ displayHours(getSunSplHolHours(item), true) }}
              </div>
            </template>

            <template v-slot:item.sun_hol_pay="{ item }">
              <div class="text-right">
                {{ displayMoney(getSunSplHolPay(item), true) }}
              </div>
            </template>

            <template v-slot:item.adj_prev_salary="{ item }">
              <div class="text-right">
                {{ displayMoney(item.salary_adjustment, true) }}
              </div>
            </template>

            <template v-slot:item.allowance="{ item }">
              <div class="text-right">
                <div>{{ displayMoney(item.other_allowances, true) }}</div>
                <div
                  v-if="shouldShowAllowanceTotal(item)"
                  class="text-caption text-medium-emphasis split-total-note"
                >
                  Total: {{ displayMoney(getSourceAllowance(item), true) }}
                </div>
              </div>
            </template>

            <template v-slot:item.gross_amount="{ item }">
              <div class="text-right font-weight-bold">
                {{ displayMoney(item.gross_pay) }}
              </div>
            </template>

            <template v-slot:item.employee_savings="{ item }">
              <div class="text-right">
                {{ displayMoney(item.employee_savings, true) }}
              </div>
            </template>

            <template v-slot:item.loans="{ item }">
              <div class="text-right">{{ displayMoney(item.loans, true) }}</div>
            </template>

            <template v-slot:item.ut="{ item }">
              <div class="text-right">
                {{ displayMoney(item.undertime_deduction, true) }}
              </div>
            </template>

            <template v-slot:item.deductions="{ item }">
              <div class="text-right">
                {{ displayMoney(getCombinedDeductions(item), true) }}
              </div>
            </template>

            <template v-slot:item.cash_advance="{ item }">
              <div class="text-right">
                {{ displayMoney(item.cash_advance, true) }}
              </div>
            </template>

            <template v-slot:item.sss="{ item }">
              <div class="text-right">{{ displayMoney(item.sss, true) }}</div>
            </template>

            <template v-slot:item.phic="{ item }">
              <div class="text-right">
                {{ displayMoney(item.philhealth, true) }}
              </div>
            </template>

            <template v-slot:item.hdmf="{ item }">
              <div class="text-right">
                {{ displayMoney(item.pagibig, true) }}
              </div>
            </template>

            <template v-slot:item.net_amount="{ item }">
              <div class="text-right font-weight-bold" style="color: #10b981">
                {{ displayMoney(item.net_pay) }}
              </div>
            </template>

            <template v-slot:body.append>
              <tr class="register-total-row">
                <td class="text-left font-weight-bold">T O T A L</td>
                <td></td>
                <td></td>
                <td></td>
                <td class="text-right font-weight-bold">
                  {{ displayMoney(previewTotals.amount) }}
                </td>
                <td class="text-center font-weight-bold">
                  {{ displayHours(previewTotals.regularOtHours, false) }}
                </td>
                <td class="text-right font-weight-bold">
                  {{ displayMoney(previewTotals.regularOtPay) }}
                </td>
                <td class="text-center font-weight-bold">
                  {{ displayHours(previewTotals.sunSplHolHours, false) }}
                </td>
                <td class="text-right font-weight-bold">
                  {{ displayMoney(previewTotals.sunSplHolPay) }}
                </td>
                <td class="text-right font-weight-bold">
                  {{ displayMoney(previewTotals.salaryAdjustment) }}
                </td>
                <td class="text-right font-weight-bold">
                  {{ displayMoney(previewTotals.allowances) }}
                </td>
                <td class="text-right font-weight-bold">
                  {{ displayMoney(previewTotals.grossPay) }}
                </td>
                <td class="text-right font-weight-bold">
                  {{ displayMoney(previewTotals.employeeSavings) }}
                </td>
                <td class="text-right font-weight-bold">
                  {{ displayMoney(previewTotals.loans) }}
                </td>
                <td class="text-right font-weight-bold">
                  {{ displayMoney(previewTotals.undertime, true) }}
                </td>
                <td class="text-right font-weight-bold">
                  {{ displayMoney(previewTotals.deductions) }}
                </td>
                <td class="text-right font-weight-bold">
                  {{ displayMoney(previewTotals.cashAdvance) }}
                </td>
                <td class="text-right font-weight-bold">
                  {{ displayMoney(previewTotals.sss) }}
                </td>
                <td class="text-right font-weight-bold">
                  {{ displayMoney(previewTotals.philhealth) }}
                </td>
                <td class="text-right font-weight-bold">
                  {{ displayMoney(previewTotals.pagibig) }}
                </td>
                <td class="text-right font-weight-bold">
                  {{ displayMoney(previewTotals.netPay) }}
                </td>
                <td></td>
              </tr>
            </template>

            <!-- Actions -->
            <template v-slot:item.actions="{ item }">
              <v-menu location="bottom end" offset="6">
                <template v-slot:activator="{ props }">
                  <v-btn
                    v-bind="props"
                    icon="mdi-dots-vertical"
                    size="small"
                    variant="text"
                    class="row-action-btn"
                    title="Row Actions"
                  ></v-btn>
                </template>

                <v-list density="compact" class="row-actions-menu">
                  <v-list-item
                    prepend-icon="mdi-pencil-outline"
                    title="Edit Adjustments"
                    :disabled="!canEditPayrollItems"
                    @click="openEditItemDialog(item)"
                  ></v-list-item>
                  <v-list-item
                    prepend-icon="mdi-download"
                    title="Download Payslip"
                    @click="downloadPayslip(item)"
                  ></v-list-item>
                  <div v-if="!canEditPayrollItems" class="menu-hint">
                    Editing is available only for draft payroll.
                  </div>
                </v-list>
              </v-menu>
            </template>
          </v-data-table>
        </div>
      </div>
    </div>

    <!-- Edit Payroll Item Dialog -->
    <v-dialog
      v-model="showEditItemDialog"
      max-width="820px"
      persistent
      scrollable
    >
      <v-card class="export-dialog">
        <div class="export-dialog-header">
          <div class="export-header-icon">
            <v-icon size="22">mdi-pencil-outline</v-icon>
          </div>
          <div class="export-header-text">
            <h2 class="export-header-title">Edit Payroll Item</h2>
            <p class="export-header-subtitle">
              {{ getEmployeeName(editingItem) || "Employee" }}
              <span v-if="editingItem?.employee?.employee_number">
                &middot; {{ editingItem.employee.employee_number }}
              </span>
            </p>
          </div>
          <button class="export-close-btn" @click="closeEditItemDialog">
            <v-icon size="20">mdi-close</v-icon>
          </button>
        </div>

        <v-divider></v-divider>

        <v-card-text class="export-dialog-body">
          <div class="export-section">
            <div class="export-section-label">
              <v-icon size="16" class="mr-2" color="#64748b"
                >mdi-tune-vertical</v-icon
              >
              Edit Register Fields Before Export
            </div>

            <v-alert
              v-if="editFromDeviceView"
              type="info"
              variant="tonal"
              density="comfortable"
              class="mb-3"
            >
              You are editing the employee total row from a split-by-device
              view. Split rows update proportionally after saving. Government
              and deduction fields are hidden here to avoid double counting.
            </v-alert>

            <div class="edit-grid">
              <v-text-field
                v-model.number="editItemForm.rate"
                label="Rate"
                type="number"
                min="0"
                step="0.01"
                variant="outlined"
                density="comfortable"
                hide-details
              ></v-text-field>
              <v-text-field
                v-model.number="editItemForm.days_worked"
                label="No. of Days"
                type="number"
                min="0"
                step="0.5"
                variant="outlined"
                density="comfortable"
                hide-details
              ></v-text-field>
              <v-text-field
                v-model.number="editItemForm.regular_ot_hours"
                label="OT Hrs"
                type="number"
                min="0"
                step="0.01"
                variant="outlined"
                density="comfortable"
                hide-details
              ></v-text-field>
              <v-text-field
                v-model.number="editItemForm.regular_ot_pay"
                label="Reg OT"
                type="number"
                min="0"
                step="0.01"
                variant="outlined"
                density="comfortable"
                hide-details
              ></v-text-field>
              <v-text-field
                v-model.number="editItemForm.special_ot_hours"
                label="Special OT Hrs"
                type="number"
                min="0"
                step="0.01"
                variant="outlined"
                density="comfortable"
                hide-details
              ></v-text-field>
              <v-text-field
                v-model.number="editItemForm.sunday_hours"
                label="Sunday Hrs"
                type="number"
                min="0"
                step="0.01"
                variant="outlined"
                density="comfortable"
                hide-details
              ></v-text-field>
              <v-text-field
                v-model.number="editItemForm.holiday_days"
                label="Holiday Days"
                type="number"
                min="0"
                step="0.5"
                variant="outlined"
                density="comfortable"
                hide-details
              ></v-text-field>
              <v-text-field
                v-model.number="editItemForm.special_ot_pay"
                label="Special OT Pay"
                type="number"
                min="0"
                step="0.01"
                variant="outlined"
                density="comfortable"
                hide-details
              ></v-text-field>
              <v-text-field
                v-model.number="editItemForm.sunday_pay"
                label="Sunday Pay"
                type="number"
                min="0"
                step="0.01"
                variant="outlined"
                density="comfortable"
                hide-details
              ></v-text-field>
              <v-text-field
                v-model.number="editItemForm.holiday_pay"
                label="Holiday Pay"
                type="number"
                min="0"
                step="0.01"
                variant="outlined"
                density="comfortable"
                hide-details
              ></v-text-field>
              <v-text-field
                v-model.number="editItemForm.salary_adjustment"
                label="Adj. Prev Salary"
                type="number"
                step="0.01"
                variant="outlined"
                density="comfortable"
                hide-details
              ></v-text-field>
              <v-text-field
                v-model.number="editItemForm.other_allowances"
                label="Allowance"
                type="number"
                min="0"
                step="0.01"
                variant="outlined"
                density="comfortable"
                hide-details
              ></v-text-field>
              <v-text-field
                v-if="!isSplitFieldLocked('employee_savings')"
                v-model.number="editItemForm.employee_savings"
                label="Emp Savings"
                type="number"
                min="0"
                step="0.01"
                variant="outlined"
                density="comfortable"
                hide-details
              ></v-text-field>
              <v-text-field
                v-if="!isSplitFieldLocked('loans')"
                v-model.number="editItemForm.loans"
                label="Loans"
                type="number"
                min="0"
                step="0.01"
                variant="outlined"
                density="comfortable"
                hide-details
              ></v-text-field>
              <v-text-field
                v-if="!isSplitFieldLocked('undertime_deduction')"
                v-model.number="editItemForm.undertime_deduction"
                label="UT"
                type="number"
                min="0"
                step="0.01"
                variant="outlined"
                density="comfortable"
                hide-details
              ></v-text-field>
              <v-text-field
                v-if="!isSplitFieldLocked('deductions')"
                v-model.number="editItemForm.deductions"
                label="Deductions"
                type="number"
                min="0"
                step="0.01"
                variant="outlined"
                density="comfortable"
                hide-details
              ></v-text-field>
              <v-text-field
                v-if="!isSplitFieldLocked('cash_advance')"
                v-model.number="editItemForm.cash_advance"
                label="Cash Adv"
                type="number"
                min="0"
                step="0.01"
                variant="outlined"
                density="comfortable"
                hide-details
              ></v-text-field>
              <v-text-field
                v-if="!isSplitFieldLocked('sss')"
                v-model.number="editItemForm.sss"
                label="SSS"
                type="number"
                min="0"
                step="0.01"
                variant="outlined"
                density="comfortable"
                hide-details
              ></v-text-field>
              <v-text-field
                v-if="!isSplitFieldLocked('philhealth')"
                v-model.number="editItemForm.philhealth"
                label="PHIC"
                type="number"
                min="0"
                step="0.01"
                variant="outlined"
                density="comfortable"
                hide-details
              ></v-text-field>
              <v-text-field
                v-if="!isSplitFieldLocked('pagibig')"
                v-model.number="editItemForm.pagibig"
                label="HDMF"
                type="number"
                min="0"
                step="0.01"
                variant="outlined"
                density="comfortable"
                hide-details
              ></v-text-field>
            </div>

            <div class="edit-note">
              SH HRS is composed of Special OT Hrs + Sunday Hrs + (Holiday Days
              x 8).
            </div>

            <v-textarea
              v-if="isPayrollist"
              v-model="payrollEditReason"
              label="Edit Reason (Required)"
              placeholder="Explain why this payroll item adjustment is needed"
              variant="outlined"
              density="comfortable"
              rows="2"
              class="mt-3"
              :disabled="savingItemAdjustments"
            ></v-textarea>

            <div class="edit-summary-cards">
              <div class="edit-summary-card">
                <div class="edit-summary-label">Amount</div>
                <div class="edit-summary-value">
                  {{ displayMoney(editItemComputed.amount) }}
                </div>
              </div>
              <div class="edit-summary-card">
                <div class="edit-summary-label">Gross Pay</div>
                <div class="edit-summary-value">
                  {{ displayMoney(editItemComputed.grossPay) }}
                </div>
              </div>
              <div class="edit-summary-card">
                <div class="edit-summary-label">Total Deductions</div>
                <div class="edit-summary-value">
                  {{ displayMoney(editItemComputed.totalDeductions) }}
                </div>
              </div>
              <div class="edit-summary-card net">
                <div class="edit-summary-label">Net Pay</div>
                <div class="edit-summary-value">
                  {{ displayMoney(editItemComputed.netPay) }}
                </div>
              </div>
            </div>
          </div>
        </v-card-text>

        <v-divider></v-divider>

        <div class="export-dialog-footer">
          <div class="export-summary">
            <v-icon size="14" class="mr-1" color="#94a3b8"
              >mdi-information-outline</v-icon
            >
            <span>
              Changes update payroll totals and will reflect in exports.
            </span>
          </div>
          <div class="export-actions">
            <button
              class="dialog-btn dialog-btn-cancel"
              @click="closeEditItemDialog"
            >
              Cancel
            </button>
            <button
              class="export-download-btn"
              :disabled="
                savingItemAdjustments ||
                (isPayrollist && !payrollEditReason.trim())
              "
              @click="saveItemAdjustments"
            >
              <v-progress-circular
                v-if="savingItemAdjustments"
                indeterminate
                size="18"
                width="2"
                class="mr-2"
              ></v-progress-circular>
              <v-icon v-else size="18" class="mr-2">mdi-content-save</v-icon>
              Save Adjustments
            </button>
          </div>
        </div>
      </v-card>
    </v-dialog>

    <v-dialog
      v-model="showPayrollEditAccessRequestDialog"
      max-width="560px"
      persistent
    >
      <v-card class="export-dialog">
        <div class="export-dialog-header">
          <div class="export-header-icon">
            <v-icon size="22">mdi-lock-open-variant-outline</v-icon>
          </div>
          <div class="export-header-text">
            <h2 class="export-header-title">Request Payroll Edit Access</h2>
            <p class="export-header-subtitle">
              Submit a request for admin approval before editing payroll items.
            </p>
          </div>
          <button
            class="export-close-btn"
            :disabled="submittingPayrollEditAccessRequest"
            @click="showPayrollEditAccessRequestDialog = false"
          >
            <v-icon size="20">mdi-close</v-icon>
          </button>
        </div>

        <v-divider></v-divider>

        <v-card-text class="export-dialog-body">
          <v-alert
            v-if="
              payrollEditAccessStatus === 'rejected' && payrollEditAccessMessage
            "
            type="error"
            variant="tonal"
            density="comfortable"
            class="mb-3"
          >
            {{ payrollEditAccessMessage }}
          </v-alert>

          <v-textarea
            v-model="payrollEditAccessReason"
            label="Reason"
            placeholder="State why payroll item adjustments are needed"
            variant="outlined"
            rows="3"
            auto-grow
          ></v-textarea>
        </v-card-text>

        <v-divider></v-divider>

        <div class="export-dialog-footer">
          <div class="export-summary">
            <v-icon size="14" class="mr-1" color="#94a3b8"
              >mdi-information-outline</v-icon
            >
            <span
              >Admins can review and approve requests in the Access Requests
              page.</span
            >
          </div>
          <div class="export-actions">
            <button
              class="dialog-btn dialog-btn-cancel"
              :disabled="submittingPayrollEditAccessRequest"
              @click="showPayrollEditAccessRequestDialog = false"
            >
              Cancel
            </button>
            <button
              class="export-download-btn"
              :disabled="
                submittingPayrollEditAccessRequest ||
                !payrollEditAccessReason.trim()
              "
              @click="submitPayrollItemEditAccessRequest"
            >
              <v-progress-circular
                v-if="submittingPayrollEditAccessRequest"
                indeterminate
                size="18"
                width="2"
                class="mr-2"
              ></v-progress-circular>
              <v-icon v-else size="18" class="mr-2">mdi-send</v-icon>
              Submit Request
            </button>
          </div>
        </div>
      </v-card>
    </v-dialog>

    <!-- Export Payroll Dialog -->
    <v-dialog
      v-model="showExportDialog"
      max-width="660px"
      persistent
      scrollable
    >
      <v-card class="export-dialog">
        <!-- Header -->
        <div class="export-dialog-header">
          <div class="export-header-icon">
            <v-icon size="22">mdi-download</v-icon>
          </div>
          <div class="export-header-text">
            <h2 class="export-header-title">Export Payroll</h2>
            <p class="export-header-subtitle">
              {{ payroll?.payroll_number }} &middot; {{ payroll?.period_name }}
            </p>
          </div>
          <button
            class="export-close-btn"
            @click="showExportDialog = false"
            :disabled="downloadingRegister"
          >
            <v-icon size="20">mdi-close</v-icon>
          </button>
        </div>

        <v-divider></v-divider>

        <v-card-text class="export-dialog-body">
          <!-- Format Selection -->
          <div class="export-section">
            <div class="export-section-label">
              <v-icon size="16" class="mr-2" color="#64748b"
                >mdi-file-document-outline</v-icon
              >
              Export Format
            </div>

            <div class="format-grid">
              <!-- PDF -->
              <div
                class="format-card"
                :class="{ active: exportFilter.format === 'pdf' }"
                @click="
                  ((exportFilter.format = 'pdf'),
                  (exportFilter.device_name = null))
                "
              >
                <div class="format-card-icon pdf">
                  <v-icon size="22">mdi-file-pdf-box</v-icon>
                </div>
                <div class="format-card-info">
                  <div class="format-card-name">PDF</div>
                  <div class="format-card-desc">Best for printing</div>
                </div>
                <v-icon
                  v-if="exportFilter.format === 'pdf'"
                  size="18"
                  class="format-check"
                  >mdi-check-circle</v-icon
                >
              </div>

              <!-- Payslips -->
              <div
                class="format-card"
                :class="{ active: exportFilter.format === 'payslips' }"
                @click="
                  ((exportFilter.format = 'payslips'),
                  (exportFilter.device_name = null))
                "
              >
                <div class="format-card-icon payslips">
                  <v-icon size="22">mdi-file-document-multiple</v-icon>
                </div>
                <div class="format-card-info">
                  <div class="format-card-name">Payslips</div>
                  <div class="format-card-desc">4 per page &middot; PDF</div>
                </div>
                <v-icon
                  v-if="exportFilter.format === 'payslips'"
                  size="18"
                  class="format-check"
                  >mdi-check-circle</v-icon
                >
              </div>

              <!-- By Device (PDF) -->
              <div
                class="format-card"
                :class="{ active: exportFilter.format === 'by_device_pdf' }"
                @click="exportFilter.format = 'by_device_pdf'"
              >
                <div class="format-card-icon device-pdf">
                  <v-icon size="22">mdi-devices</v-icon>
                </div>
                <div class="format-card-info">
                  <div class="format-card-name">By Device</div>
                  <div class="format-card-desc">Grouped &middot; PDF</div>
                </div>
                <v-icon
                  v-if="exportFilter.format === 'by_device_pdf'"
                  size="18"
                  class="format-check"
                  >mdi-check-circle</v-icon
                >
              </div>
            </div>

            <v-select
              v-if="exportFilter.format === 'payslips'"
              v-model="exportFilter.paper_size"
              :items="paperSizeOptions"
              item-title="title"
              item-value="value"
              label="Paper Size"
              prepend-inner-icon="mdi-file-document"
              variant="outlined"
              density="comfortable"
              hide-details
              class="mt-3"
            ></v-select>

            <v-select
              v-if="exportFilter.format === 'by_device_pdf'"
              v-model="exportFilter.device_name"
              :items="availableDevices"
              label="Device (Optional)"
              prepend-inner-icon="mdi-devices"
              variant="outlined"
              density="comfortable"
              clearable
              hide-details
              class="mt-3"
            ></v-select>
          </div>
        </v-card-text>

        <v-divider></v-divider>

        <!-- Footer -->
        <div class="export-dialog-footer">
          <div class="export-summary">
            <v-icon size="14" class="mr-1" color="#94a3b8"
              >mdi-information-outline</v-icon
            >
            <span>{{ exportSummaryText }}</span>
          </div>
          <div class="export-actions">
            <button
              class="dialog-btn dialog-btn-cancel"
              :disabled="downloadingRegister"
              @click="showExportDialog = false"
            >
              Cancel
            </button>
            <button
              class="export-download-btn"
              :disabled="downloadingRegister"
              @click="downloadRegister"
            >
              <v-progress-circular
                v-if="downloadingRegister"
                indeterminate
                size="18"
                width="2"
                class="mr-2"
              ></v-progress-circular>
              <v-icon v-else size="18" class="mr-2">mdi-download</v-icon>
              Download {{ exportFormatLabel }}
            </button>
          </div>
        </div>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useToast } from "vue-toastification";
import api from "@/services/api";
import { formatCurrency, formatDate } from "@/utils/formatters";
import { useConfirmDialog } from "@/composables/useConfirmDialog";
import { useAuthStore } from "@/stores/auth";
import moduleAccessService from "@/services/moduleAccessService";

const route = useRoute();
const router = useRouter();
const toast = useToast();
const { confirm: confirmDialog } = useConfirmDialog();
const authStore = useAuthStore();

const PAYROLL_ITEM_EDIT_MODULE = "payroll-item-adjustments";

const loading = ref(false);
const finalizing = ref(false);
const reprocessing = ref(false);
const search = ref("");
const viewMode = ref("employee");
const deviceFilter = ref(null);
const payroll = ref(null);
const showExportDialog = ref(false);
const downloadingRegister = ref(false);
const showEditItemDialog = ref(false);
const savingItemAdjustments = ref(false);
const editingItem = ref(null);
const editFromDeviceView = ref(false);
const editedItemIds = ref([]);
const editItemForm = ref({
  rate: 0,
  days_worked: 0,
  regular_ot_hours: 0,
  regular_ot_pay: 0,
  special_ot_hours: 0,
  special_ot_pay: 0,
  sunday_hours: 0,
  sunday_pay: 0,
  holiday_days: 0,
  holiday_pay: 0,
  salary_adjustment: 0,
  other_allowances: 0,
  sss: 0,
  philhealth: 0,
  pagibig: 0,
  deductions: 0,
  cash_advance: 0,
  employee_savings: 0,
  loans: 0,
  undertime_deduction: 0,
});
const payrollEditReason = ref("");
const showPayrollEditAccessRequestDialog = ref(false);
const payrollEditAccessReason = ref("");
const submittingPayrollEditAccessRequest = ref(false);
const checkingPayrollEditAccess = ref(false);
const payrollEditAccessStatus = ref("none");
const payrollEditAccessMessage = ref("");
const editableItemFields = [
  "rate",
  "days_worked",
  "regular_ot_hours",
  "regular_ot_pay",
  "special_ot_hours",
  "special_ot_pay",
  "sunday_hours",
  "sunday_pay",
  "holiday_days",
  "holiday_pay",
  "salary_adjustment",
  "other_allowances",
  "sss",
  "philhealth",
  "pagibig",
  "deductions",
  "cash_advance",
  "employee_savings",
  "loans",
  "undertime_deduction",
];
const splitViewLockedFields = [
  "sss",
  "philhealth",
  "pagibig",
  "deductions",
  "cash_advance",
  "employee_savings",
  "loans",
  "undertime_deduction",
];
const exportFilter = ref({
  format: "by_device_pdf", // pdf, payslips, by_device_pdf
  paper_size: "long_bond", // long_bond, a4 (for payslips)
  device_name: null,
});
const paperSizeOptions = [
  { title: "8.5 x 13 (Long Bond)", value: "long_bond" },
  { title: "A4", value: "a4" },
];
const STANDARD_HOURS_PER_DAY = 8;
const viewModeOptions = [
  { title: "Employee Totals", value: "employee" },
  { title: "Split By Device", value: "device" },
];

const canEditPayrollItems = computed(() => payroll.value?.status === "draft");
const isPayrollist = computed(() => authStore.userRole === "payrollist");
const hasPayrollItemEditAccess = computed(
  () =>
    !isPayrollist.value ||
    ["approved", "admin"].includes(payrollEditAccessStatus.value),
);

const availableDevices = computed(() => {
  const names = (payroll.value?.device_grouped_items || [])
    .map((group) => group?.device_name)
    .filter(Boolean);

  return names.sort((a, b) => {
    const aIsUnassigned = String(a).toLowerCase() === "unassigned";
    const bIsUnassigned = String(b).toLowerCase() === "unassigned";
    if (aIsUnassigned && !bIsUnassigned) return 1;
    if (!aIsUnassigned && bIsUnassigned) return -1;
    return String(a).localeCompare(String(b), undefined, {
      numeric: true,
      sensitivity: "base",
    });
  });
});

async function checkPayrollItemEditAccess() {
  if (!isPayrollist.value) {
    payrollEditAccessStatus.value = "admin";
    payrollEditAccessMessage.value = "";
    return true;
  }

  checkingPayrollEditAccess.value = true;
  try {
    const response = await moduleAccessService.checkAccess(
      PAYROLL_ITEM_EDIT_MODULE,
    );
    payrollEditAccessStatus.value = response.status || "none";
    payrollEditAccessMessage.value = response.message || "";
    return Boolean(response.has_access);
  } catch {
    payrollEditAccessStatus.value = "none";
    payrollEditAccessMessage.value =
      "Unable to verify payroll edit access right now.";
    return false;
  } finally {
    checkingPayrollEditAccess.value = false;
  }
}

async function submitPayrollItemEditAccessRequest() {
  const reason = (payrollEditAccessReason.value || "").trim();
  if (!reason) {
    toast.error("Reason is required.");
    return;
  }

  submittingPayrollEditAccessRequest.value = true;
  try {
    await moduleAccessService.submitRequest(PAYROLL_ITEM_EDIT_MODULE, {
      reason,
    });
    toast.success("Access request submitted successfully.");
    showPayrollEditAccessRequestDialog.value = false;
    payrollEditAccessReason.value = "";
    payrollEditAccessStatus.value = "pending";
    payrollEditAccessMessage.value = "Your request is pending admin approval.";
  } catch (error) {
    toast.error(
      error?.response?.data?.message || "Failed to submit access request.",
    );
  } finally {
    submittingPayrollEditAccessRequest.value = false;
  }
}

// Format label for the download button
const exportFormatLabel = computed(() => {
  const labels = {
    pdf: "PDF",
    payslips: "Payslips",
    by_device_pdf: "By Device (PDF)",
  };
  return labels[exportFilter.value.format] || exportFilter.value.format;
});

// Summary text for the footer
const exportSummaryText = computed(() => {
  const totalEmployees = payroll.value?.items?.length || 0;
  if (
    exportFilter.value.format === "by_device_pdf" &&
    exportFilter.value.device_name
  ) {
    return `Device: ${exportFilter.value.device_name} · ${exportFormatLabel.value} format`;
  }
  return `All ${totalEmployees} employees · ${exportFormatLabel.value} format`;
});

const splitByDeviceItems = computed(() => {
  const groups = payroll.value?.device_grouped_items || [];

  return groups.flatMap((group) => {
    const deviceName = group?.device_name || "Unassigned";
    const designation = group?.designation || null;
    const location = group?.location || null;
    const items = Array.isArray(group?.items) ? group.items : [];

    return items.map((item) => ({
      ...item,
      _deviceName: deviceName,
      _deviceDesignation: designation,
      _deviceLocation: location,
    }));
  });
});

const sourcePreviewItems = computed(() => {
  if (viewMode.value === "device" && splitByDeviceItems.value.length > 0) {
    return splitByDeviceItems.value;
  }

  return (payroll.value?.items || []).map((item) => ({
    ...item,
    _deviceName: "All Devices",
    _deviceDesignation: null,
    _deviceLocation: null,
  }));
});

const previewItems = computed(() => {
  if (!sourcePreviewItems.value.length) return [];

  const rawSearchTerm = (search.value || "").trim();
  const normalizedSearchTerm = normalizeSearchText(rawSearchTerm);
  const compactSearchTerm = normalizedSearchTerm.replace(/\s+/g, "");

  const filtered = sourcePreviewItems.value.filter((item) => {
    if (viewMode.value === "device" && deviceFilter.value) {
      if (String(item._deviceName || "") !== String(deviceFilter.value)) {
        return false;
      }
    }

    if (!rawSearchTerm) {
      return true;
    }

    if (!normalizedSearchTerm) {
      return true;
    }

    const employee = item?.employee || {};
    const firstName = employee.first_name || "";
    const middleName = employee.middle_name || "";
    const lastName = employee.last_name || "";
    const employeeNumber = employee.employee_number || "";
    const fullName = [firstName, middleName, lastName]
      .filter(Boolean)
      .join(" ");
    const storedFullName = employee.full_name || "";
    const middleInitial = middleName.trim().charAt(0);
    const fullNameWithInitial = [firstName, middleInitial, lastName]
      .filter(Boolean)
      .join(" ");
    const fullNameWithInitialDot = middleInitial
      ? `${firstName} ${middleInitial}. ${lastName}`
      : "";

    const candidates = [
      firstName,
      middleName,
      lastName,
      fullName,
      storedFullName,
      fullNameWithInitial,
      fullNameWithInitialDot,
      employeeNumber,
    ];

    return candidates.some((value) => {
      const normalizedValue = normalizeSearchText(value);
      if (!normalizedValue) {
        return false;
      }

      return (
        normalizedValue.includes(normalizedSearchTerm) ||
        normalizedValue.replace(/\s+/g, "").includes(compactSearchTerm)
      );
    });
  });

  return filtered.map((item, index) => ({
    ...item,
    _rowNo: index + 1,
  }));
});

const previewTotals = computed(() => {
  const items = previewItems.value;
  const sum = (extractor) =>
    items.reduce((total, item) => total + toNumber(extractor(item)), 0);

  return {
    amount: sum((item) => getRegisterAmount(item)),
    regularOtHours: sum((item) => item.regular_ot_hours),
    regularOtPay: sum((item) => item.regular_ot_pay),
    sunSplHolHours: sum((item) => getSunSplHolHours(item)),
    sunSplHolPay: sum((item) => getSunSplHolPay(item)),
    salaryAdjustment: sum((item) => item.salary_adjustment),
    allowances: sum((item) => item.other_allowances),
    grossPay: sum((item) => item.gross_pay),
    employeeSavings: sum((item) => item.employee_savings),
    loans: sum((item) => item.loans),
    undertime: sum((item) => item.undertime_deduction),
    deductions: sum((item) => getCombinedDeductions(item)),
    cashAdvance: sum((item) => item.cash_advance),
    sss: sum((item) => item.sss),
    philhealth: sum((item) => item.philhealth),
    pagibig: sum((item) => item.pagibig),
    netPay: sum((item) => item.net_pay),
  };
});

const editItemComputed = computed(() => {
  const item = editingItem.value;
  if (!item) {
    return {
      amount: 0,
      grossPay: 0,
      totalDeductions: 0,
      netPay: 0,
    };
  }

  const amount =
    toNumber(editItemForm.value.rate) *
    toNumber(editItemForm.value.days_worked);

  const grossPay =
    amount +
    toNumber(editItemForm.value.holiday_pay) +
    toNumber(editItemForm.value.regular_ot_pay) +
    toNumber(editItemForm.value.special_ot_pay) +
    toNumber(editItemForm.value.sunday_pay) +
    toNumber(editItemForm.value.other_allowances) +
    toNumber(editItemForm.value.salary_adjustment);

  const totalDeductions =
    toNumber(editItemForm.value.sss) +
    toNumber(editItemForm.value.philhealth) +
    toNumber(editItemForm.value.pagibig) +
    toNumber(item.withholding_tax) +
    toNumber(editItemForm.value.loans) +
    toNumber(editItemForm.value.employee_savings) +
    toNumber(editItemForm.value.cash_advance) +
    toNumber(editItemForm.value.deductions) +
    toNumber(editItemForm.value.undertime_deduction);

  return {
    amount,
    grossPay,
    totalDeductions,
    netPay: grossPay - totalDeductions,
  };
});

const headers = [
  { title: "NAME", key: "name", sortable: false },
  { title: "DEVICE", key: "device", sortable: false },
  { title: "RATE", key: "rate", sortable: false, align: "end" },
  { title: "NO. OF DAYS", key: "no_of_days", sortable: false, align: "center" },
  { title: "AMOUNT", key: "amount", sortable: false, align: "end" },
  { title: "OT HRS", key: "ot_hrs", sortable: false, align: "center" },
  { title: "REG OT", key: "reg_ot", sortable: false, align: "end" },
  { title: "SH HRS", key: "sun_hol_hrs", sortable: false, align: "center" },
  { title: "SUN/SPL HOL", key: "sun_hol_pay", sortable: false, align: "end" },
  {
    title: "ADJ. PREV SAL",
    key: "adj_prev_salary",
    sortable: false,
    align: "end",
  },
  { title: "ALLOWANCE", key: "allowance", sortable: false, align: "end" },
  { title: "GROSS AMOUNT", key: "gross_amount", sortable: false, align: "end" },
  {
    title: "EMP SAVINGS",
    key: "employee_savings",
    sortable: false,
    align: "end",
  },
  { title: "LOANS", key: "loans", sortable: false, align: "end" },
  { title: "UT", key: "ut", sortable: false, align: "end" },
  { title: "DEDUCTIONS", key: "deductions", sortable: false, align: "end" },
  { title: "CASH ADV", key: "cash_advance", sortable: false, align: "end" },
  { title: "SSS", key: "sss", sortable: false, align: "end" },
  { title: "PHIC", key: "phic", sortable: false, align: "end" },
  { title: "HDMF", key: "hdmf", sortable: false, align: "end" },
  { title: "NET AMOUNT", key: "net_amount", sortable: false, align: "end" },
  { title: "Actions", key: "actions", sortable: false, align: "center" },
];

function handleViewModeChange(nextMode) {
  if (nextMode !== "device") {
    deviceFilter.value = null;
  }
}

function toNumber(value) {
  const num = Number(value);
  return Number.isFinite(num) ? num : 0;
}

function normalizeSearchText(value) {
  return String(value || "")
    .toLowerCase()
    .replace(/[^a-z0-9\s]/g, " ")
    .replace(/\s+/g, " ")
    .trim();
}

function getEmployeeName(item) {
  const fullName = item?.employee?.full_name;
  if (fullName) {
    return fullName;
  }

  return [item?.employee?.first_name, item?.employee?.last_name]
    .filter(Boolean)
    .join(" ")
    .trim();
}

function displayDays(value) {
  const num = toNumber(value);
  if (!num) {
    return "0";
  }
  return num
    .toFixed(2)
    .replace(/\.00$/, "")
    .replace(/(\.\d*[1-9])0+$/, "$1");
}

function displayHours(value, blankIfZero = true) {
  const num = toNumber(value);
  if (blankIfZero && !num) {
    return "";
  }
  return num
    .toFixed(2)
    .replace(/\.00$/, "")
    .replace(/(\.\d*[1-9])0+$/, "$1");
}

function displayMoney(value, blankIfZero = false) {
  const num = toNumber(value);
  if (blankIfZero && !num) {
    return "";
  }
  return formatCurrency(num);
}

function getRegisterAmount(item) {
  return (
    toNumber(item?.effective_rate ?? item?.rate) * toNumber(item?.days_worked)
  );
}

function getSunSplHolHours(item) {
  return (
    toNumber(item?.special_ot_hours) +
    toNumber(item?.sunday_hours) +
    toNumber(item?.holiday_days) * STANDARD_HOURS_PER_DAY
  );
}

function getSunSplHolPay(item) {
  return (
    toNumber(item?.special_ot_pay) +
    toNumber(item?.sunday_pay) +
    toNumber(item?.holiday_pay)
  );
}

function getCombinedDeductions(item) {
  return toNumber(item?.employee_deductions) + toNumber(item?.other_deductions);
}

function isItemEdited(item) {
  return editedItemIds.value.includes(Number(item?.id));
}

function getSourcePayrollItem(item) {
  return (
    payroll.value?.items?.find(
      (source) => Number(source?.id) === Number(item?.id),
    ) || item
  );
}

function getSourceAllowance(item) {
  return toNumber(getSourcePayrollItem(item)?.other_allowances);
}

function shouldShowAllowanceTotal(item) {
  if (viewMode.value !== "device") {
    return false;
  }

  const splitAllowance = toNumber(item?.other_allowances);
  const totalAllowance = getSourceAllowance(item);
  return Math.abs(splitAllowance - totalAllowance) > 0.009;
}

function isSplitFieldLocked(field) {
  return editFromDeviceView.value && splitViewLockedFields.includes(field);
}

function getEditableItemFields(item) {
  return {
    rate: toNumber(item?.rate) || toNumber(item?.effective_rate),
    days_worked: toNumber(item?.days_worked),
    regular_ot_hours: toNumber(item?.regular_ot_hours),
    regular_ot_pay: toNumber(item?.regular_ot_pay),
    special_ot_hours: toNumber(item?.special_ot_hours),
    special_ot_pay: toNumber(item?.special_ot_pay),
    sunday_hours: toNumber(item?.sunday_hours),
    sunday_pay: toNumber(item?.sunday_pay),
    holiday_days: toNumber(item?.holiday_days),
    holiday_pay: toNumber(item?.holiday_pay),
    salary_adjustment: toNumber(item?.salary_adjustment),
    other_allowances: toNumber(item?.other_allowances),
    sss: toNumber(item?.sss),
    philhealth: toNumber(item?.philhealth),
    pagibig: toNumber(item?.pagibig),
    deductions:
      toNumber(item?.employee_deductions) + toNumber(item?.other_deductions),
    cash_advance: toNumber(item?.cash_advance),
    employee_savings: toNumber(item?.employee_savings),
    loans: toNumber(item?.loans),
    undertime_deduction: toNumber(item?.undertime_deduction),
  };
}

async function openEditItemDialog(item) {
  if (!canEditPayrollItems.value) {
    toast.info("Only draft payrolls can be edited.");
    return;
  }

  if (isPayrollist.value) {
    const hasAccess = await checkPayrollItemEditAccess();
    if (!hasAccess) {
      if (payrollEditAccessStatus.value === "pending") {
        toast.info(
          payrollEditAccessMessage.value ||
            "Your payroll edit request is pending admin approval.",
        );
      } else {
        showPayrollEditAccessRequestDialog.value = true;
      }
      return;
    }
  }

  if (!item?.id) {
    toast.error("Unable to edit this payroll item.");
    return;
  }

  const sourceItem = getSourcePayrollItem(item);

  editFromDeviceView.value = viewMode.value === "device";
  editingItem.value = { ...sourceItem };
  editItemForm.value = getEditableItemFields(sourceItem);
  payrollEditReason.value = "";
  showEditItemDialog.value = true;
}

function closeEditItemDialog() {
  showEditItemDialog.value = false;
  editingItem.value = null;
  editFromDeviceView.value = false;
  payrollEditReason.value = "";
  editItemForm.value = getEditableItemFields(null);
}

function buildEditItemPayload() {
  const payload = editableItemFields.reduce((acc, key) => {
    if (key !== "deductions" && !isSplitFieldLocked(key)) {
      acc[key] = toNumber(editItemForm.value[key]);
    }
    return acc;
  }, {});

  if (!isSplitFieldLocked("deductions")) {
    const combinedDeductions = toNumber(editItemForm.value.deductions);
    const originalOtherDeductions = toNumber(
      editingItem.value?.other_deductions,
    );
    const nextOtherDeductions = Math.min(
      originalOtherDeductions,
      combinedDeductions,
    );

    payload.other_deductions = nextOtherDeductions;
    payload.employee_deductions = combinedDeductions - nextOtherDeductions;
  }

  return payload;
}

async function saveItemAdjustments() {
  if (!editingItem.value?.id || !payroll.value?.id) {
    toast.error("Unable to save adjustments.");
    return;
  }

  if (!canEditPayrollItems.value) {
    toast.error("This payroll is no longer editable.");
    return;
  }

  if (isPayrollist.value) {
    const hasAccess = await checkPayrollItemEditAccess();
    if (!hasAccess) {
      toast.error(
        payrollEditAccessMessage.value ||
          "Access request approval is required before editing payroll items.",
      );
      return;
    }

    if (!payrollEditReason.value.trim()) {
      toast.error("Edit reason is required before saving adjustments.");
      return;
    }
  }

  savingItemAdjustments.value = true;
  try {
    const savedItemId = Number(editingItem.value.id);
    const payload = buildEditItemPayload();

    if (isPayrollist.value) {
      payload.reason = payrollEditReason.value.trim();
    }

    await api.put(
      `/payrolls/${payroll.value.id}/items/${editingItem.value.id}`,
      payload,
    );

    if (!editedItemIds.value.includes(savedItemId)) {
      editedItemIds.value = [...editedItemIds.value, savedItemId];
    }

    toast.success("Payroll item updated successfully");
    closeEditItemDialog();
    await fetchPayroll();
  } catch (error) {
    toast.error(
      error?.response?.data?.message || "Failed to update payroll item",
    );
  } finally {
    savingItemAdjustments.value = false;
  }
}

onMounted(() => {
  fetchPayroll();
  checkPayrollItemEditAccess();
});

function openExportDialog() {
  // Always default to by-device split export when opening the register dialog.
  exportFilter.value.format = "by_device_pdf";
  exportFilter.value.device_name = null;
  showExportDialog.value = true;
}

async function fetchPayroll() {
  loading.value = true;
  try {
    const response = await api.get(`/payrolls/${route.params.id}`, {
      cacheTTL: 15000,
    });
    const editedIds = Array.isArray(response.data?.edited_item_ids)
      ? response.data.edited_item_ids
          .map((value) => Number(value))
          .filter((value) => Number.isFinite(value))
      : [];

    editedItemIds.value = [...new Set(editedIds)];
    payroll.value = response.data;
  } catch (error) {
    toast.error("Failed to load payroll details");
    router.push("/payroll");
  } finally {
    loading.value = false;
  }
}

async function finalizePayroll() {
  if (
    !(await confirmDialog(
      "Are you sure you want to finalize this payroll? You will not be able to edit it after finalization.",
    ))
  ) {
    return;
  }

  finalizing.value = true;
  try {
    await api.post(`/payrolls/${payroll.value.id}/finalize`);
    toast.success("Payroll finalized successfully");
    await fetchPayroll();
  } catch (error) {
    toast.error("Failed to finalize payroll");
  } finally {
    finalizing.value = false;
  }
}

async function reprocessPayroll() {
  if (
    !(await confirmDialog(
      "Reprocess this draft payroll? This recalculates payroll from attendance/source data and may overwrite manual item edits.",
    ))
  ) {
    return;
  }

  reprocessing.value = true;
  try {
    await api.post(`/payrolls/${payroll.value.id}/reprocess`);
    editedItemIds.value = [];
    toast.success("Payroll reprocessed successfully");
    await fetchPayroll();
  } catch (error) {
    toast.error(
      error?.response?.data?.message || "Failed to reprocess payroll",
    );
  } finally {
    reprocessing.value = false;
  }
}

async function downloadRegister() {
  if (downloadingRegister.value) return;
  downloadingRegister.value = true;

  // Determine if exporting payslips or register (needs to be outside try for error handling)
  const isPayslips = exportFilter.value.format === "payslips";

  try {
    // Build params object
    const params = {};

    // Only add format for register export (payslips is always PDF)
    if (!isPayslips) {
      params.format = exportFilter.value.format;
      if (
        exportFilter.value.format === "by_device_pdf" &&
        exportFilter.value.device_name
      ) {
        params.device_name = exportFilter.value.device_name;
      }
    } else {
      params.paper_size = exportFilter.value.paper_size;
    }

    params.filter_type = "all";

    // Use different endpoint for payslips
    const endpoint = isPayslips
      ? `/payrolls/${payroll.value.id}/download-payslips`
      : `/payrolls/${payroll.value.id}/download-register`;

    // Use a longer timeout for larger grouped PDF exports (including bulk payslips)
    const isLargeExport =
      isPayslips || ["by_device_pdf"].includes(exportFilter.value.format);

    const response = await api.get(endpoint, {
      params: params,
      responseType: "blob",
      timeout: isLargeExport ? 300000 : 60000, // 5 min for large exports, 1 min otherwise
    });

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;

    // Build filename with appropriate extension
    const extensions = {
      pdf: ".pdf",
      payslips: ".pdf",
      by_device_pdf: ".pdf",
    };

    // Use different base filename for payslips
    let filename = isPayslips
      ? `payslips_${payroll.value.payroll_number}`
      : `payroll_register_${payroll.value.payroll_number}`;

    if (isPayslips && exportFilter.value.paper_size === "a4") {
      filename += "_a4";
    }

    filename += extensions[exportFilter.value.format];

    link.setAttribute("download", filename);
    document.body.appendChild(link);
    link.click();
    link.remove();

    const formatNames = {
      pdf: "PDF",
      payslips: "Compact Payslips PDF",
      by_device_pdf: "By Device (PDF)",
    };

    const successMessage = isPayslips
      ? "Payslips downloaded successfully"
      : `Payroll register downloaded as ${formatNames[exportFilter.value.format]}`;

    toast.success(successMessage);
    showExportDialog.value = false;
  } catch (error) {
    // When responseType is 'blob', error response data is a Blob - parse it
    let errorMessage = isPayslips
      ? "Failed to download payslips"
      : "Failed to download payroll register";
    if (error.response?.data instanceof Blob) {
      try {
        const text = await error.response.data.text();
        const json = JSON.parse(text);
        if (json.message) errorMessage = json.message;
      } catch (_) {}
    }
    toast.error(errorMessage);
  } finally {
    downloadingRegister.value = false;
  }
}

async function downloadPayslip(item) {
  try {
    const response = await api.get(
      `/payrolls/${payroll.value.id}/employees/${item.employee_id}/download-payslip`,
      { responseType: "blob" },
    );

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute(
      "download",
      `payslip_${item.employee.employee_number}.pdf`,
    );
    document.body.appendChild(link);
    link.click();
    link.remove();

    toast.success("Payslip downloaded");
  } catch (error) {
    toast.error("Failed to download payslip");
  }
}

function getStatusColor(status) {
  const colors = {
    draft: "warning",
    finalized: "info",
    paid: "success",
  };
  return colors[status] || "grey";
}

function getStatusIcon(status) {
  const icons = {
    draft: "mdi-file-edit-outline",
    finalized: "mdi-file-check-outline",
    paid: "mdi-check-circle",
  };
  return icons[status] || "mdi-file-document";
}

// formatDate, formatCurrency imported from @/utils/formatters
</script>

<style scoped>
.payroll-detail-page {
  background: #f8f9fa;
  min-height: 100vh;
}

/* Page Header */
.page-header {
  background: white;
  border-radius: 16px;
  padding: 24px;
  margin-bottom: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.header-content {
  display: flex;
  flex-direction: column;
  align-items: stretch;
  justify-content: flex-start;
  gap: 20px;
}

.back-button-wrapper {
  margin-bottom: 4px;
}

.back-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: transparent;
  border: none;
  border-radius: 8px;
  color: #64748b;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;
}

.back-button:hover {
  background: rgba(0, 31, 61, 0.04);
  color: #001f3d;
}

.header-main {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 24px;
}

.page-title-section {
  display: flex;
  align-items: center;
  gap: 16px;
}

.page-icon-badge {
  width: 56px;
  height: 56px;
  border-radius: 14px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);
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
  align-items: center;
}

.status-badge {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 600;
  letter-spacing: 0.3px;
}

.status-badge.status-draft {
  background: rgba(237, 152, 95, 0.1);
  color: #ed985f;
}

.status-badge.status-finalized {
  background: rgba(16, 185, 129, 0.1);
  color: #10b981;
}

.status-badge.status-paid {
  background: rgba(16, 185, 129, 0.15);
  color: #059669;
}

.action-btn {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px 12px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  border: none;
  white-space: nowrap;
}

.action-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

.action-btn-primary {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: white;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);
}

.action-btn-primary:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.4);
}

.action-btn-success {
  background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
  color: white;
  box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
}

.action-btn-success:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(16, 185, 129, 0.4);
}

.action-btn-reprocess {
  background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%);
  color: white;
  box-shadow: 0 2px 8px rgba(15, 118, 110, 0.3);
}

.action-btn-reprocess:hover:not(:disabled) {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(15, 118, 110, 0.4);
}

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 12px;
  margin-bottom: 20px;
}

.stat-card {
  background: white;
  border-radius: 12px;
  padding: 14px 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  display: flex;
  align-items: center;
  gap: 12px;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  position: relative;
  overflow: hidden;

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
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 8px 24px rgba(237, 152, 95, 0.2);
  border-color: rgba(237, 152, 95, 0.3);

  &::before {
    transform: scaleY(1);
  }
}

.stat-card.info-card {
  grid-column: span 1;
}

.stat-icon {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.stat-icon.period {
  background: rgba(100, 116, 139, 0.1);

  .v-icon {
    color: #64748b;
  }
}

.stat-icon.payment {
  background: rgba(100, 116, 139, 0.1);

  .v-icon {
    color: #64748b;
  }
}

.stat-icon.employees {
  background: rgba(0, 31, 61, 0.1);

  .v-icon {
    color: #001f3d;
  }
}

.stat-icon.gross {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);

  .v-icon {
    color: white;
  }
}

.stat-icon.net {
  background: rgba(16, 185, 129, 0.1);

  .v-icon {
    color: #10b981;
  }
}

.stat-content {
  flex: 1;
  min-width: 0;
}

.stat-label {
  font-size: 12px;
  color: rgba(0, 31, 61, 0.6);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
}

.stat-value {
  font-size: 19px;
  font-weight: 700;
  color: #001f3d;
  line-height: 1;
}

.stat-value.primary {
  color: #ed985f;
}

.stat-value.success {
  color: #10b981;
}

.stat-value-text {
  font-size: 14px;
  font-weight: 600;
  color: #001f3d;
  line-height: 1.4;
}

/* Modern Card */
.modern-card {
  background: white;
  border-radius: 16px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
  overflow: hidden;
  padding: 14px;
}

.card-header {
  padding-bottom: 10px;
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
}

.header-left {
  display: flex;
  align-items: center;
  gap: 16px;
}

.header-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
}

.card-title {
  font-size: 18px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
}

.card-subtitle {
  font-size: 13px;
  color: #64748b;
  margin: 4px 0 0 0;
}

.header-right {
  display: flex;
  gap: 12px;
  align-items: center;
  flex-wrap: wrap;
}

.header-right > * {
  flex: 1 1 220px;
  min-width: 0;
}

.table-section {
  overflow-x: auto;
  padding-top: 10px;
  -webkit-overflow-scrolling: touch;
}

.modern-table {
  background: transparent;
}

.modern-table :deep(thead) {
  background: #f8f9fa;
}

.modern-table :deep(th) {
  color: #001f3d !important;
  font-weight: 600 !important;
  font-size: 13px !important;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  border-bottom: 1px solid rgba(0, 31, 61, 0.08) !important;
}

.modern-table :deep(.v-data-table__th) {
  background: #f8f9fa !important;
}

.register-preview :deep(table) {
  min-width: 1780px;
  table-layout: auto;
}

.register-preview :deep(th),
.register-preview :deep(td) {
  padding: 6px 8px !important;
  font-size: 11.5px !important;
}

.register-preview :deep(th) {
  white-space: normal;
  line-height: 1.15;
}

.register-preview :deep(td) {
  white-space: nowrap;
}

.register-preview :deep(th:first-child),
.register-preview :deep(td:first-child) {
  min-width: 200px;
}

.register-preview :deep(th:nth-child(2)),
.register-preview :deep(td:nth-child(2)) {
  min-width: 160px;
}

.register-preview :deep(th:last-child),
.register-preview :deep(td:last-child) {
  min-width: 72px;
}

.register-total-row td {
  background: #fff7ee !important;
  border-top: 2px solid rgba(237, 152, 95, 0.35) !important;
  font-size: 12px;
}

.row-action-btn {
  color: #64748b;
}

.row-action-btn:hover {
  color: #001f3d;
  background: rgba(0, 31, 61, 0.06);
}

.row-actions-menu {
  min-width: 220px;
}

.menu-hint {
  padding: 4px 16px 10px;
  font-size: 11px;
  line-height: 1.4;
  color: #94a3b8;
}

.name-meta {
  display: inline-flex;
  align-items: center;
  gap: 6px;
}

.edited-chip {
  font-weight: 600;
}

.split-total-note {
  white-space: nowrap;
}

.edit-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 12px;
}

.edit-note {
  margin-top: 8px;
  font-size: 12px;
  color: #64748b;
}

.edit-summary-cards {
  margin-top: 14px;
  display: grid;
  grid-template-columns: repeat(4, minmax(0, 1fr));
  gap: 10px;
}

.edit-summary-card {
  background: #f8fafc;
  border: 1px solid rgba(0, 31, 61, 0.08);
  border-radius: 10px;
  padding: 10px 12px;
}

.edit-summary-card.net {
  background: rgba(16, 185, 129, 0.08);
  border-color: rgba(16, 185, 129, 0.2);
}

.edit-summary-label {
  font-size: 11px;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  color: #64748b;
}

.edit-summary-value {
  margin-top: 4px;
  font-size: 15px;
  font-weight: 700;
  color: #001f3d;
}

/* Responsive Design */
@media (max-width: 1024px) {
  .header-main {
    flex-direction: column;
    align-items: flex-start;
  }

  .header-right {
    width: 100%;
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 10px;
  }

  .header-right > * {
    min-width: 0 !important;
    width: 100%;
  }

  .register-preview :deep(table) {
    min-width: 1500px;
  }

  .action-buttons {
    width: 100%;
    flex-wrap: wrap;
  }

  .stats-grid {
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  }
}

@media (max-width: 768px) {
  .payroll-detail-page {
    padding: 16px;
  }

  .page-header {
    padding: 16px;
  }

  .page-title {
    font-size: 22px;
  }

  .card-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .header-right {
    display: grid;
    grid-template-columns: 1fr;
    width: 100%;
  }

  .header-right > * {
    min-width: 0 !important;
    width: 100%;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }

  .register-preview :deep(table) {
    min-width: 1220px;
  }

  .edit-grid,
  .edit-summary-cards {
    grid-template-columns: 1fr;
  }

  .action-btn {
    padding: 10px 16px;
    font-size: 13px;
  }
}

/* Dialog & section styles from _shared-layout.scss */

/* ── Export Dialog ── */
.export-dialog {
  border-radius: 16px !important;
  overflow: hidden;
}

.export-dialog-header {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 20px 24px;
  background: linear-gradient(135deg, #f8f9fa 0%, #eef1f5 100%);
}

.export-header-icon {
  width: 44px;
  height: 44px;
  border-radius: 12px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  flex-shrink: 0;
  box-shadow: 0 3px 10px rgba(237, 152, 95, 0.25);
}

.export-header-text {
  flex: 1;
  min-width: 0;
}

.export-header-title {
  font-size: 18px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
  line-height: 1.3;
}

.export-header-subtitle {
  font-size: 13px;
  color: #64748b;
  margin: 2px 0 0 0;
}

.export-close-btn {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  border: none;
  background: rgba(0, 31, 61, 0.06);
  color: #64748b;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;
  flex-shrink: 0;
}

.export-close-btn:hover {
  background: rgba(0, 31, 61, 0.1);
  color: #001f3d;
}

.export-dialog-body {
  padding: 20px 24px !important;
}

/* Sections */
.export-section {
  margin-bottom: 20px;
}

.export-section:last-child {
  margin-bottom: 0;
}

.export-section-label {
  display: flex;
  align-items: center;
  font-size: 12px;
  font-weight: 700;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.6px;
  margin-bottom: 12px;
  background: none;
  border: none;
  padding: 0;
  width: 100%;
}

/* Format Grid */
.format-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
}

.format-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px;
  border-radius: 12px;
  border: 1.5px solid rgba(0, 31, 61, 0.08);
  background: white;
  cursor: pointer;
  transition: all 0.2s ease;
  position: relative;
}

.format-card:hover {
  border-color: rgba(0, 31, 61, 0.16);
  background: #fafbfc;
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.format-card.active {
  border-color: #ed985f;
  background: rgba(237, 152, 95, 0.04);
  box-shadow: 0 0 0 3px rgba(237, 152, 95, 0.1);
}

.format-card-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.format-card-icon.pdf {
  background: rgba(211, 47, 47, 0.1);
  color: #d32f2f;
}

.format-card-icon.payslips {
  background: rgba(255, 111, 0, 0.1);
  color: #ff6f00;
}

.format-card-icon.device-pdf {
  background: rgba(0, 137, 123, 0.1);
  color: #00897b;
}

.format-card-info {
  flex: 1;
  min-width: 0;
}

.format-card-name {
  font-size: 14px;
  font-weight: 600;
  color: #001f3d;
  line-height: 1.3;
}

.format-card-desc {
  font-size: 11px;
  color: #94a3b8;
  line-height: 1.3;
  margin-top: 1px;
}

.format-check {
  color: #ed985f;
  flex-shrink: 0;
}

/* Footer */
.export-dialog-footer {
  padding: 14px 24px;
  background: #f8f9fa;
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.export-summary {
  display: flex;
  align-items: center;
  font-size: 12px;
  color: #94a3b8;
  min-width: 0;
  flex-shrink: 1;
}

.export-summary span {
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.export-actions {
  display: flex;
  gap: 10px;
  align-items: center;
  flex-shrink: 0;
}

.dialog-btn {
  padding: 10px 20px;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  display: flex;
  align-items: center;
  gap: 8px;
  white-space: nowrap;
}

.dialog-btn-cancel {
  background: #e2e8f0;
  color: #64748b;
}

.dialog-btn-cancel:hover {
  background: #cbd5e1;
}

.export-download-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 22px;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: white;
  cursor: pointer;
  transition: all 0.2s ease;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);
  white-space: nowrap;
}

.export-download-btn:hover:not(:disabled) {
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(237, 152, 95, 0.4);
}

.export-download-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

@media (max-width: 520px) {
  .format-grid {
    grid-template-columns: 1fr;
  }

  .export-dialog-footer {
    flex-direction: column;
    align-items: stretch;
    gap: 12px;
  }

  .export-summary {
    justify-content: center;
  }

  .export-actions {
    justify-content: flex-end;
  }
}

.form-field-wrapper {
  margin-bottom: 20px;
}

.form-label {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 14px;
  font-weight: 600;
  color: #001f3d;
  margin-bottom: 8px;
}
</style>
