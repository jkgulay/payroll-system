<template>
  <div class="resignation-management-page">
    <!-- Modern Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="page-title-section">
          <div class="page-icon-badge">
            <v-icon size="22">mdi-briefcase-remove-outline</v-icon>
          </div>
          <div>
            <h1 class="page-title">Resignation Management</h1>
            <p class="page-subtitle">
              Manage employee resignation requests and process final payments
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Modern Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon pending">
          <v-icon size="20">mdi-clock-outline</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Pending</div>
          <div class="stat-value">{{ stats.pending }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon approved">
          <v-icon size="20">mdi-check-circle</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Approved</div>
          <div class="stat-value">{{ stats.approved }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon rejected">
          <v-icon size="20">mdi-close-circle</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Rejected</div>
          <div class="stat-value">{{ stats.rejected }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon completed">
          <v-icon size="20">mdi-checkbox-marked-circle</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Completed</div>
          <div class="stat-value">{{ stats.completed }}</div>
        </div>
      </div>
    </div>

    <!-- Resignations List -->
    <div class="modern-card">
      <div class="filters-section">
        <v-row align="center" class="mb-0">
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.status"
              :items="statusOptions"
              label="Status"
              variant="outlined"
              density="comfortable"
              hide-details
              @update:model-value="onFilterChange"
            ></v-select>
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field
              v-model="search"
              label="Search Employee"
              variant="outlined"
              density="comfortable"
              prepend-inner-icon="mdi-magnify"
              hide-details
              clearable
            ></v-text-field>
          </v-col>
          <v-spacer></v-spacer>
          <v-col cols="auto">
            <v-btn
              color="#ED985F"
              variant="tonal"
              icon="mdi-refresh"
              @click="loadResignations(currentPage)"
              :loading="loading"
              title="Refresh"
            ></v-btn>
          </v-col>
        </v-row>
      </div>

      <div class="table-section">
        <v-data-table-server
          :headers="headers"
          :items="resignations"
          :loading="loading"
          :items-length="totalItems"
          :items-per-page="itemsPerPage"
          :page="currentPage"
          :items-per-page-options="pageSizeOptions"
          item-value="id"
          class="elevation-0"
          @update:page="onPageChange"
          @update:items-per-page="onItemsPerPageChange"
        >
          <!-- Employee -->
          <template v-slot:item.employee="{ item }">
            <div class="py-2">
              <div class="font-weight-bold">{{ item.employee.full_name }}</div>
              <div class="text-caption text-medium-emphasis">
                {{ item.employee.employee_number }}
              </div>
            </div>
          </template>

          <!-- Position -->
          <template v-slot:item.position="{ item }">
            <div class="py-2">
              <div>{{ item.employee.position }}</div>
              <div class="text-caption text-medium-emphasis">
                {{ item.employee.project?.name }}
              </div>
            </div>
          </template>

          <!-- Status -->
          <template v-slot:item.status="{ item }">
            <v-chip :color="getStatusColor(item.status)" size="small">
              {{ item.status_label }}
            </v-chip>
          </template>

          <!-- Dates -->
          <template v-slot:item.resignation_date="{ item }">
            {{ formatDate(item.resignation_date) }}
          </template>

          <template v-slot:item.last_working_day="{ item }">
            <div>{{ formatDate(item.last_working_day) }}</div>
            <div
              v-if="
                item.effective_date &&
                item.effective_date !== item.last_working_day
              "
              class="text-caption text-warning"
            >
              Modified: {{ formatDate(item.effective_date) }}
            </div>
          </template>

          <!-- Days Remaining -->
          <template v-slot:item.days_remaining="{ item }">
            <v-chip
              v-if="item.days_remaining !== null && item.status === 'approved'"
              :color="item.days_remaining > 7 ? 'success' : 'warning'"
              size="small"
            >
              {{ item.days_remaining }} days
            </v-chip>
            <span v-else>-</span>
          </template>

          <!-- Final Pay -->
          <template v-slot:item.final_pay="{ item }">
            <div v-if="item.final_pay_amount">
              <div class="font-weight-bold">
                ₱{{ formatCurrency(item.final_pay_amount) }}
              </div>
              <v-chip
                v-if="item.final_pay_released"
                color="success"
                size="x-small"
                class="mt-1"
              >
                Released
              </v-chip>
              <v-chip v-else color="warning" size="x-small" class="mt-1">
                Pending
              </v-chip>
            </div>
            <span v-else class="text-medium-emphasis">Not calculated</span>
          </template>

          <!-- Actions -->
          <template v-slot:item.actions="{ item }">
            <v-menu location="bottom end">
              <template v-slot:activator="{ props }">
                <v-btn
                  v-bind="props"
                  icon="mdi-dots-vertical"
                  size="small"
                  variant="text"
                  title="Actions"
                ></v-btn>
              </template>
              <v-list density="compact">
                <v-list-item @click="viewDetails(item)">
                  <template v-slot:prepend>
                    <v-icon size="18">mdi-eye</v-icon>
                  </template>
                  <v-list-item-title>View Details</v-list-item-title>
                </v-list-item>

                <v-list-item
                  v-if="item.status === 'pending'"
                  @click="openApproveDialog(item)"
                >
                  <template v-slot:prepend>
                    <v-icon size="18" color="success">mdi-check</v-icon>
                  </template>
                  <v-list-item-title>Approve</v-list-item-title>
                </v-list-item>

                <v-list-item
                  v-if="item.status === 'pending'"
                  @click="openRejectDialog(item)"
                >
                  <template v-slot:prepend>
                    <v-icon size="18" color="error">mdi-close</v-icon>
                  </template>
                  <v-list-item-title>Reject</v-list-item-title>
                </v-list-item>

                <v-list-item
                  v-if="item.status === 'approved' && !item.final_pay_amount"
                  @click="openFinalPayDialog(item)"
                >
                  <template v-slot:prepend>
                    <v-icon size="18" color="primary">mdi-calculator</v-icon>
                  </template>
                  <v-list-item-title>Calculate Final Pay</v-list-item-title>
                </v-list-item>

                <v-list-item
                  v-if="
                    item.status === 'approved' &&
                    item.final_pay_amount &&
                    !item.final_pay_released
                  "
                  @click="openReleaseDialog(item)"
                >
                  <template v-slot:prepend>
                    <v-icon size="18" color="success">mdi-cash-check</v-icon>
                  </template>
                  <v-list-item-title>Release Final Pay</v-list-item-title>
                </v-list-item>
              </v-list>
            </v-menu>
          </template>
        </v-data-table-server>
      </div>
    </div>

    <!-- View Details Dialog -->
    <v-dialog v-model="showDetailsDialog" max-width="700">
      <v-card v-if="selectedResignation">
        <v-card-title class="pa-6">
          <v-icon left :color="getStatusColor(selectedResignation.status)">
            {{ getStatusIcon(selectedResignation.status) }}
          </v-icon>
          Resignation Details
        </v-card-title>

        <v-divider></v-divider>

        <v-card-text class="pa-6">
          <v-row>
            <v-col cols="12" sm="6">
              <div class="mb-4">
                <div class="text-caption text-medium-emphasis mb-1">
                  Employee
                </div>
                <div class="text-body-1 font-weight-bold">
                  {{ selectedResignation.employee.full_name }}
                </div>
                <div class="text-caption">
                  {{ selectedResignation.employee.employee_number }}
                </div>
              </div>
            </v-col>

            <v-col cols="12" sm="6">
              <div class="mb-4">
                <div class="text-caption text-medium-emphasis mb-1">
                  Position
                </div>
                <div class="text-body-1">
                  {{ selectedResignation.employee.position }}
                </div>
              </div>
            </v-col>

            <v-col cols="12" sm="6">
              <div class="mb-4">
                <div class="text-caption text-medium-emphasis mb-1">
                  Resignation Date
                </div>
                <div class="text-body-1">
                  {{ formatDate(selectedResignation.resignation_date) }}
                </div>
              </div>
            </v-col>

            <v-col cols="12" sm="6">
              <div class="mb-4">
                <div class="text-caption text-medium-emphasis mb-1">
                  Requested Last Day
                </div>
                <div class="text-body-1">
                  {{ formatDate(selectedResignation.last_working_day) }}
                </div>
              </div>
            </v-col>

            <v-col cols="12" sm="6" v-if="selectedResignation.effective_date">
              <div class="mb-4">
                <div class="text-caption text-medium-emphasis mb-1">
                  Effective Last Day
                </div>
                <div class="text-body-1 font-weight-bold text-success">
                  {{ formatDate(selectedResignation.effective_date) }}
                </div>
              </div>
            </v-col>

            <v-col cols="12" v-if="selectedResignation.reason">
              <v-divider class="my-2"></v-divider>
              <div class="mb-4">
                <div class="text-caption text-medium-emphasis mb-1">Reason</div>
                <div class="text-body-1">{{ selectedResignation.reason }}</div>
              </div>
            </v-col>

            <!-- Attachments Display -->
            <v-col
              cols="12"
              v-if="
                selectedResignation.attachments &&
                selectedResignation.attachments.length > 0
              "
            >
              <v-divider class="my-2"></v-divider>
              <div class="mb-4">
                <div class="text-caption text-medium-emphasis mb-2">
                  Attachments
                </div>
                <div class="d-flex flex-wrap ga-2">
                  <v-chip
                    v-for="(
                      attachment, index
                    ) in selectedResignation.attachments"
                    :key="index"
                    color="primary"
                    variant="outlined"
                    :prepend-icon="getFileIcon(attachment.mime_type)"
                    @click="
                      viewAttachment(selectedResignation.id, index, attachment)
                    "
                  >
                    {{ attachment.original_name }}
                    <template v-slot:append>
                      <v-icon size="small">mdi-eye</v-icon>
                    </template>
                  </v-chip>
                </div>
              </div>
            </v-col>

            <v-col cols="12" v-if="selectedResignation.remarks">
              <v-divider class="my-2"></v-divider>
              <div class="mb-4">
                <div class="text-caption text-medium-emphasis mb-1">
                  HR Remarks
                </div>
                <div class="text-body-1">{{ selectedResignation.remarks }}</div>
              </div>
            </v-col>

            <v-col cols="12" v-if="selectedResignation.processed_by">
              <v-divider class="my-2"></v-divider>
              <div class="mb-4">
                <div class="text-caption text-medium-emphasis mb-1">
                  Processed By
                </div>
                <div class="text-body-1">
                  {{ selectedResignation.processed_by.name }} on
                  {{ formatDate(selectedResignation.processed_at) }}
                </div>
              </div>
            </v-col>

            <v-col cols="12" v-if="selectedResignation.final_pay_amount">
              <v-divider class="my-2"></v-divider>
              <div class="text-subtitle-1 font-weight-bold mb-3">
                Final Pay Details
              </div>

              <v-row>
                <v-col cols="12" sm="6">
                  <div class="mb-2">
                    <div class="text-caption text-medium-emphasis">
                      13th Month Pay
                    </div>
                    <div class="text-h6 text-success">
                      ₱{{
                        formatCurrency(
                          selectedResignation.thirteenth_month_amount,
                        )
                      }}
                    </div>
                  </div>
                </v-col>

                <v-col cols="12" sm="6">
                  <div class="mb-2">
                    <div class="text-caption text-medium-emphasis">
                      Total Final Pay
                    </div>
                    <div class="text-h6 text-primary">
                      ₱{{
                        formatCurrency(selectedResignation.final_pay_amount)
                      }}
                    </div>
                  </div>
                </v-col>

                <v-col cols="12">
                  <v-chip
                    :color="
                      selectedResignation.final_pay_released
                        ? 'success'
                        : 'warning'
                    "
                    class="mt-2"
                  >
                    {{
                      selectedResignation.final_pay_released
                        ? "Final Pay Released"
                        : "Final Pay Pending"
                    }}
                  </v-chip>
                  <div
                    v-if="selectedResignation.final_pay_released"
                    class="text-caption mt-1"
                  >
                    Released on:
                    {{ formatDate(selectedResignation.final_pay_release_date) }}
                  </div>
                </v-col>
              </v-row>
            </v-col>
          </v-row>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="showDetailsDialog = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Approve Dialog -->
    <v-dialog v-model="showApproveDialog" max-width="600">
      <v-card>
        <v-card-title class="pa-6">
          <v-icon left color="success">mdi-check-circle</v-icon>
          Approve Resignation
        </v-card-title>

        <v-divider></v-divider>

        <v-card-text class="pa-6">
          <v-form ref="approveForm" v-model="approveFormValid">
            <v-alert type="info" variant="tonal" class="mb-4">
              Approving resignation for:
              <strong>{{ selectedResignation?.employee.full_name }}</strong>
            </v-alert>

            <v-text-field
              v-model="approveData.effective_date"
              label="Effective Last Working Day"
              type="date"
              variant="outlined"
              :hint="`Requested: ${formatDate(selectedResignation?.last_working_day)}`"
              persistent-hint
              class="mb-4"
            ></v-text-field>

            <v-textarea
              v-model="approveData.remarks"
              label="Remarks (Optional)"
              variant="outlined"
              rows="3"
              counter="1000"
            ></v-textarea>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="showApproveDialog = false"
            >Cancel</v-btn
          >
          <v-btn
            color="success"
            @click="approveResignation"
            :loading="processing"
            :disabled="!approveFormValid"
          >
            Approve
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Reject Dialog -->
    <v-dialog v-model="showRejectDialog" max-width="600">
      <v-card>
        <v-card-title class="pa-6">
          <v-icon left color="error">mdi-close-circle</v-icon>
          Reject Resignation
        </v-card-title>

        <v-divider></v-divider>

        <v-card-text class="pa-6">
          <v-form ref="rejectForm" v-model="rejectFormValid">
            <v-alert type="warning" variant="tonal" class="mb-4">
              Rejecting resignation for:
              <strong>{{ selectedResignation?.employee.full_name }}</strong>
            </v-alert>

            <v-textarea
              v-model="rejectData.remarks"
              label="Rejection Reason *"
              variant="outlined"
              rows="4"
              :rules="[(v) => !!v || 'Reason is required']"
              counter="1000"
            ></v-textarea>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="showRejectDialog = false">Cancel</v-btn>
          <v-btn
            color="error"
            @click="rejectResignation"
            :loading="processing"
            :disabled="!rejectFormValid"
          >
            Reject
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Calculate Final Pay Dialog -->
    <v-dialog v-model="showFinalPayDialog" max-width="760">
      <v-card>
        <v-card-title class="pa-6">
          <v-icon left color="primary">mdi-calculator</v-icon>
          Calculate Final Pay
        </v-card-title>

        <v-divider></v-divider>

        <v-card-text class="pa-6">
          <v-alert type="info" variant="tonal" class="mb-4">
            <div class="mb-2">
              <strong>Employee:</strong>
              {{ selectedResignation?.employee.full_name }}
            </div>
            <div>
              <strong>Last Working Day:</strong>
              {{ formatDate(selectedResignation?.effective_date) }}
            </div>
          </v-alert>

          <v-form ref="finalPayForm">
            <v-text-field
              v-model.number="finalPayData.remaining_salary"
              label="Additional Amount (Remaining Salary, etc.)"
              type="number"
              variant="outlined"
              prefix="₱"
              :rules="[(v) => v >= 0 || 'Must be positive']"
              hint="Any additional amounts to include in final pay"
              persistent-hint
            ></v-text-field>
          </v-form>

          <v-alert type="warning" variant="tonal" class="mt-4">
            The system will automatically calculate:
            <ul class="mt-2">
              <li>Unpaid attendance salary (after last paid payroll period)</li>
              <li>Pro-rated 13th month pay</li>
              <li>Unused leave credits (if applicable)</li>
              <li>Employee savings payout</li>
              <li>Outstanding loans and deductions to subtract</li>
            </ul>
          </v-alert>

          <v-card
            v-if="finalPayBreakdown"
            variant="tonal"
            color="primary"
            class="mt-4"
          >
            <v-card-title class="text-subtitle-1 pb-0">
              Final Pay Breakdown Preview
            </v-card-title>
            <v-card-text class="pt-3">
              <div class="d-flex justify-space-between py-1">
                <span>
                  Unpaid Attendance Salary ({{
                    finalPayBreakdown.unpaid_attendance_days || 0
                  }}
                  day/s)
                </span>
                <strong>
                  ₱{{
                    formatCurrency(
                      finalPayBreakdown.unpaid_attendance_salary || 0,
                    )
                  }}
                </strong>
              </div>

              <div class="d-flex justify-space-between py-1">
                <span>13th Month Pay</span>
                <strong>
                  ₱{{
                    formatCurrency(finalPayBreakdown.thirteenth_month_pay || 0)
                  }}
                </strong>
              </div>

              <div class="d-flex justify-space-between py-1">
                <span>Unused Leave Credits</span>
                <strong
                  >₱{{
                    formatCurrency(finalPayBreakdown.unused_leave || 0)
                  }}</strong
                >
              </div>

              <div class="d-flex justify-space-between py-1">
                <span>Employee Savings Payout</span>
                <strong>
                  ₱{{ formatCurrency(finalPayBreakdown.employee_savings || 0) }}
                </strong>
              </div>

              <div class="d-flex justify-space-between py-1">
                <span>Additional Amount</span>
                <strong>
                  ₱{{ formatCurrency(finalPayBreakdown.remaining_salary || 0) }}
                </strong>
              </div>

              <v-divider class="my-2"></v-divider>

              <div class="d-flex justify-space-between py-1">
                <span>Gross Total</span>
                <strong
                  >₱{{
                    formatCurrency(finalPayBreakdown.gross_total || 0)
                  }}</strong
                >
              </div>

              <div class="d-flex justify-space-between py-1 text-error">
                <span>Outstanding Loans</span>
                <strong>
                  - ₱{{
                    formatCurrency(finalPayBreakdown.outstanding_loans || 0)
                  }}
                </strong>
              </div>

              <div class="d-flex justify-space-between py-1 text-error">
                <span>Outstanding Deductions</span>
                <strong>
                  - ₱{{
                    formatCurrency(
                      finalPayBreakdown.outstanding_deductions || 0,
                    )
                  }}
                </strong>
              </div>

              <v-divider class="my-2"></v-divider>

              <div
                class="d-flex justify-space-between py-1 text-h6 font-weight-bold"
              >
                <span>Net Final Pay</span>
                <span>₱{{ formatCurrency(finalPayBreakdown.total || 0) }}</span>
              </div>

              <div class="text-caption text-medium-emphasis mt-2">
                Attendance coverage:
                {{ formatDate(finalPayBreakdown.unpaid_attendance_start) }} to
                {{ formatDate(finalPayBreakdown.unpaid_attendance_end) }}
              </div>
            </v-card-text>
          </v-card>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="closeFinalPayDialog">Cancel</v-btn>
          <v-btn
            color="secondary"
            variant="tonal"
            @click="previewFinalPayBreakdown"
            :loading="previewingFinalPay"
            :disabled="processing"
          >
            Preview Breakdown
          </v-btn>
          <v-btn
            color="primary"
            @click="calculateFinalPay"
            :loading="processing"
            :disabled="!finalPayBreakdown || previewingFinalPay"
          >
            Confirm & Calculate
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Release Final Pay Dialog -->
    <v-dialog v-model="showReleaseDialog" max-width="500">
      <v-card>
        <v-card-title class="pa-6">
          <v-icon left color="success">mdi-cash-check</v-icon>
          Release Final Pay
        </v-card-title>

        <v-divider></v-divider>

        <v-card-text class="pa-6">
          <v-alert type="warning" variant="tonal" class="mb-4">
            <strong>Are you sure you want to release the final pay?</strong>
          </v-alert>

          <div class="mb-4">
            <div class="text-caption text-medium-emphasis mb-1">Employee</div>
            <div class="text-body-1 font-weight-bold">
              {{ selectedResignation?.employee.full_name }}
            </div>
          </div>

          <div class="mb-4">
            <div class="text-caption text-medium-emphasis mb-1">
              Final Pay Amount
            </div>
            <div class="text-h6 text-primary">
              ₱{{ formatCurrency(selectedResignation?.final_pay_amount) }}
            </div>
          </div>

          <v-alert type="info" variant="tonal">
            This will mark the employee's status as
            <strong>resigned</strong> and stop future salary processing.
          </v-alert>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="showReleaseDialog = false"
            >Cancel</v-btn
          >
          <v-btn color="success" @click="releaseFinalPay" :loading="processing">
            Release Final Pay
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Attachment Preview Dialog -->
    <v-dialog v-model="showAttachmentDialog" max-width="900">
      <v-card>
        <v-card-title class="pa-4 d-flex align-center">
          <v-icon left>{{ getFileIcon(currentAttachment?.mime_type) }}</v-icon>
          {{ currentAttachment?.original_name }}
          <v-spacer></v-spacer>
          <v-btn
            icon="mdi-download"
            variant="text"
            @click="downloadCurrentAttachment"
          ></v-btn>
          <v-btn
            icon="mdi-close"
            variant="text"
            @click="showAttachmentDialog = false"
          ></v-btn>
        </v-card-title>

        <v-divider></v-divider>

        <v-card-text
          class="pa-0"
          style="min-height: 500px; max-height: 80vh; overflow: auto"
        >
          <!-- Image Preview -->
          <div
            v-if="isImage(currentAttachment?.mime_type)"
            class="pa-4 text-center"
          >
            <img
              :src="attachmentUrl"
              :alt="currentAttachment?.original_name"
              style="max-width: 100%; height: auto"
            />
          </div>

          <!-- PDF Preview -->
          <iframe
            v-else-if="isPDF(currentAttachment?.mime_type)"
            :src="attachmentUrl"
            style="width: 100%; height: 70vh; border: none"
          ></iframe>

          <!-- Other file types -->
          <div v-else class="pa-8 text-center">
            <v-icon size="80" color="grey">mdi-file-document</v-icon>
            <div class="text-h6 mt-4">
              {{ currentAttachment?.original_name }}
            </div>
            <div class="text-caption text-medium-emphasis mt-2">
              This file type cannot be previewed. Click the download button to
              view it.
            </div>
            <v-btn
              color="primary"
              class="mt-4"
              prepend-icon="mdi-download"
              @click="downloadCurrentAttachment"
            >
              Download File
            </v-btn>
          </div>
        </v-card-text>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snackbar" :color="snackbarColor" :timeout="3000">
      {{ snackbarText }}
    </v-snackbar>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from "vue";
import resignationService from "@/services/resignationService";
import { formatDate, formatCurrency } from "@/utils/formatters";

// State
const loading = ref(true);
const processing = ref(false);
const resignations = ref([]);
const selectedResignation = ref(null);
const resignationStats = ref({
  pending: 0,
  approved: 0,
  rejected: 0,
  completed: 0,
  total: 0,
});
const currentPage = ref(1);
const itemsPerPage = ref(15);
const totalItems = ref(0);
const pageSizeOptions = [
  { value: 10, title: "10" },
  { value: 15, title: "15" },
  { value: 25, title: "25" },
  { value: 50, title: "50" },
];
const search = ref("");
let searchDebounceTimeout = null;

// Dialogs
const showDetailsDialog = ref(false);
const showApproveDialog = ref(false);
const showRejectDialog = ref(false);
const showFinalPayDialog = ref(false);
const showReleaseDialog = ref(false);
const showAttachmentDialog = ref(false);
const currentAttachment = ref(null);
const currentResignationId = ref(null);
const currentAttachmentIndex = ref(null);
const attachmentUrl = ref("");

// Form validation
const approveFormValid = ref(false);
const rejectFormValid = ref(false);

// Form data
const approveData = ref({
  effective_date: "",
  remarks: "",
});

const rejectData = ref({
  remarks: "",
});

const finalPayData = ref({
  remaining_salary: 0,
});
const finalPayBreakdown = ref(null);
const previewingFinalPay = ref(false);

// Filters
const filters = ref({
  status: "all",
});

// Snackbar
const snackbar = ref(false);
const snackbarText = ref("");
const snackbarColor = ref("success");

// Options
const statusOptions = [
  { title: "All", value: "all" },
  { title: "Pending", value: "pending" },
  { title: "Approved", value: "approved" },
  { title: "Rejected", value: "rejected" },
  { title: "Completed", value: "completed" },
];

// Computed
const stats = computed(() => ({
  pending: resignationStats.value.pending,
  approved: resignationStats.value.approved,
  rejected: resignationStats.value.rejected,
  completed: resignationStats.value.completed,
}));

// Table headers
const headers = [
  { title: "Employee", key: "employee", sortable: false },
  { title: "Position", key: "position", sortable: false },
  { title: "Status", key: "status" },
  { title: "Filed Date", key: "resignation_date" },
  { title: "Last Working Day", key: "last_working_day" },
  { title: "Days Left", key: "days_remaining" },
  { title: "Final Pay", key: "final_pay", sortable: false },
  { title: "Actions", key: "actions", sortable: false, align: "center" },
];

// Methods
const loadResignations = async (page = currentPage.value) => {
  try {
    loading.value = true;

    const normalizedPage = Number(page) > 0 ? Number(page) : 1;
    currentPage.value = normalizedPage;

    const searchTerm = String(search.value || "").trim();

    const listParams = {
      page: normalizedPage,
      per_page: itemsPerPage.value,
      status: filters.value.status,
    };

    if (searchTerm) {
      listParams.search = searchTerm;
    }

    const statsParams = {};
    if (searchTerm) {
      statsParams.search = searchTerm;
    }

    const [listData, statsData] = await Promise.all([
      resignationService.getResignations(listParams),
      resignationService.getResignationStats(statsParams),
    ]);

    resignations.value = Array.isArray(listData?.data) ? listData.data : [];
    currentPage.value = Number(listData?.current_page || normalizedPage);
    totalItems.value = Number(
      listData?.total || resignations.value.length || 0,
    );
    itemsPerPage.value = Number(listData?.per_page || itemsPerPage.value);

    resignationStats.value = {
      pending: Number(statsData?.pending || 0),
      approved: Number(statsData?.approved || 0),
      rejected: Number(statsData?.rejected || 0),
      completed: Number(statsData?.completed || 0),
      total: Number(statsData?.total || 0),
    };
  } catch (error) {
    showSnackbar("Failed to load resignations", "error");
    resignations.value = [];
    totalItems.value = 0;
    resignationStats.value = {
      pending: 0,
      approved: 0,
      rejected: 0,
      completed: 0,
      total: 0,
    };
  } finally {
    loading.value = false;
  }
};

const onFilterChange = () => {
  currentPage.value = 1;
  loadResignations(1);
};

const onPageChange = (page) => {
  const nextPage = Number(page) > 0 ? Number(page) : 1;
  if (nextPage === currentPage.value) {
    return;
  }

  loadResignations(nextPage);
};

const onItemsPerPageChange = (perPage) => {
  const normalizedPerPage = Number(perPage) > 0 ? Number(perPage) : 15;
  if (normalizedPerPage === itemsPerPage.value) {
    return;
  }

  itemsPerPage.value = normalizedPerPage;
  currentPage.value = 1;
  loadResignations(1);
};

const viewDetails = (resignation) => {
  selectedResignation.value = resignation;
  showDetailsDialog.value = true;
};

const openApproveDialog = (resignation) => {
  selectedResignation.value = resignation;
  approveData.value = {
    effective_date: resignation.last_working_day,
    remarks: "",
  };
  showApproveDialog.value = true;
};

const openRejectDialog = (resignation) => {
  selectedResignation.value = resignation;
  rejectData.value = {
    remarks: "",
  };
  showRejectDialog.value = true;
};

const openFinalPayDialog = (resignation) => {
  selectedResignation.value = resignation;
  finalPayData.value = {
    remaining_salary: 0,
  };
  finalPayBreakdown.value = null;
  showFinalPayDialog.value = true;
};

const closeFinalPayDialog = () => {
  showFinalPayDialog.value = false;
  finalPayBreakdown.value = null;
};

const openReleaseDialog = (resignation) => {
  selectedResignation.value = resignation;
  showReleaseDialog.value = true;
};

const approveResignation = async () => {
  try {
    processing.value = true;
    await resignationService.approveResignation(
      selectedResignation.value.id,
      approveData.value,
    );
    showSnackbar("Resignation approved successfully", "success");
    showApproveDialog.value = false;
    await loadResignations(currentPage.value);
  } catch (error) {
    showSnackbar(
      error.response?.data?.message || "Failed to approve resignation",
      "error",
    );
  } finally {
    processing.value = false;
  }
};

const rejectResignation = async () => {
  try {
    processing.value = true;
    await resignationService.rejectResignation(
      selectedResignation.value.id,
      rejectData.value,
    );
    showSnackbar("Resignation rejected", "success");
    showRejectDialog.value = false;
    await loadResignations(currentPage.value);
  } catch (error) {
    showSnackbar(
      error.response?.data?.message || "Failed to reject resignation",
      "error",
    );
  } finally {
    processing.value = false;
  }
};

const calculateFinalPay = async () => {
  if (!finalPayBreakdown.value) {
    showSnackbar("Please preview the breakdown before confirming.", "warning");
    return;
  }

  try {
    processing.value = true;
    const response = await resignationService.processFinalPay(
      selectedResignation.value.id,
      finalPayData.value,
    );

    if (response?.resignation) {
      selectedResignation.value = response.resignation;
    }

    showSnackbar("Final pay calculated successfully", "success");
    closeFinalPayDialog();

    await loadResignations(currentPage.value);
  } catch (error) {
    showSnackbar(
      error.response?.data?.message || "Failed to calculate final pay",
      "error",
    );
  } finally {
    processing.value = false;
  }
};

const previewFinalPayBreakdown = async () => {
  try {
    previewingFinalPay.value = true;
    const response = await resignationService.processFinalPay(
      selectedResignation.value.id,
      {
        ...finalPayData.value,
        preview_only: true,
      },
    );

    finalPayBreakdown.value = response?.breakdown || null;

    if (!finalPayBreakdown.value) {
      showSnackbar("Unable to generate final pay preview.", "warning");
      return;
    }

    showSnackbar("Final pay breakdown preview updated", "success");
  } catch (error) {
    finalPayBreakdown.value = null;
    showSnackbar(
      error.response?.data?.message || "Failed to preview final pay breakdown",
      "error",
    );
  } finally {
    previewingFinalPay.value = false;
  }
};

const releaseFinalPay = async () => {
  try {
    processing.value = true;
    await resignationService.releaseFinalPay(selectedResignation.value.id);
    showSnackbar(
      "Final pay released successfully. Employee status updated to resigned.",
      "success",
    );
    showReleaseDialog.value = false;
    await loadResignations(currentPage.value);
  } catch (error) {
    showSnackbar(
      error.response?.data?.message || "Failed to release final pay",
      "error",
    );
  } finally {
    processing.value = false;
  }
};

const getStatusColor = (status) => {
  const colors = {
    pending: "warning",
    approved: "success",
    rejected: "error",
    completed: "info",
  };
  return colors[status] || "grey";
};

const getStatusIcon = (status) => {
  const icons = {
    pending: "mdi-clock-outline",
    approved: "mdi-check-circle",
    rejected: "mdi-close-circle",
    completed: "mdi-checkbox-marked-circle",
  };
  return icons[status] || "mdi-help-circle";
};

const toAttachmentBlob = (response, fallbackMimeType = "") => {
  const mimeType =
    fallbackMimeType ||
    response?.headers?.["content-type"] ||
    "application/octet-stream";

  if (response?.data instanceof Blob) {
    return response.data;
  }

  return new Blob([response?.data ?? ""], { type: mimeType });
};

const viewAttachment = async (resignationId, index, attachment) => {
  try {
    currentAttachment.value = attachment;
    currentResignationId.value = resignationId;
    currentAttachmentIndex.value = index;

    const response = await resignationService.downloadAttachment(
      resignationId,
      index,
    );

    const attachmentBlob = toAttachmentBlob(response, attachment?.mime_type);

    // Create object URL for viewing
    if (attachmentUrl.value) {
      window.URL.revokeObjectURL(attachmentUrl.value);
    }
    attachmentUrl.value = window.URL.createObjectURL(attachmentBlob);
    showAttachmentDialog.value = true;
  } catch (error) {
    showSnackbar("Failed to load attachment", "error");
  }
};

const downloadCurrentAttachment = async () => {
  try {
    const response = await resignationService.downloadAttachment(
      currentResignationId.value,
      currentAttachmentIndex.value,
    );

    const attachmentBlob = toAttachmentBlob(
      response,
      currentAttachment.value?.mime_type,
    );

    // Create a download link
    const url = window.URL.createObjectURL(attachmentBlob);
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute("download", currentAttachment.value.original_name);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  } catch (error) {
    showSnackbar("Failed to download attachment", "error");
  }
};

const isImage = (mimeType) => {
  return mimeType?.startsWith("image/");
};

const isPDF = (mimeType) => {
  return mimeType === "application/pdf";
};

const getFileIcon = (mimeType) => {
  if (isImage(mimeType)) return "mdi-image";
  if (isPDF(mimeType)) return "mdi-file-pdf-box";
  if (mimeType?.includes("word") || mimeType?.includes("document"))
    return "mdi-file-word";
  return "mdi-file-document";
};

const showSnackbar = (text, color = "success") => {
  snackbarText.value = text;
  snackbarColor.value = color;
  snackbar.value = true;
};

// Lifecycle
onMounted(() => {
  loadResignations(1);
});

watch(search, () => {
  if (searchDebounceTimeout) {
    clearTimeout(searchDebounceTimeout);
  }

  searchDebounceTimeout = setTimeout(() => {
    currentPage.value = 1;
    loadResignations(1);
  }, 350);
});

watch(
  () => finalPayData.value.remaining_salary,
  () => {
    finalPayBreakdown.value = null;
  },
);

onUnmounted(() => {
  if (searchDebounceTimeout) {
    clearTimeout(searchDebounceTimeout);
    searchDebounceTimeout = null;
  }
});
</script>

<style scoped lang="scss">
.resignation-management-page {
  background-color: #f8f9fa;
  min-height: 100vh;
}

.page-header {
  margin-bottom: 24px;
}

.header-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
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
  color: white;
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.25);
}

.page-title {
  font-size: 28px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
  line-height: 1.2;
}

.page-subtitle {
  font-size: 14px;
  color: rgba(0, 31, 61, 0.6);
  margin: 4px 0 0 0;
}

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

.stat-icon {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.stat-icon.pending {
  background: rgba(237, 152, 95, 0.1);

  .v-icon {
    color: #ed985f;
  }
}

.stat-icon.approved {
  background: rgba(16, 185, 129, 0.1);

  .v-icon {
    color: #10b981;
  }
}

.stat-icon.rejected {
  background: rgba(239, 68, 68, 0.1);

  .v-icon {
    color: #ef4444;
  }
}

.stat-icon.completed {
  background: rgba(0, 31, 61, 0.1);

  .v-icon {
    color: #001f3d;
  }
}

.stat-content {
  flex: 1;
  min-width: 0;
}

.stat-label {
  font-size: 11px;
  color: rgba(0, 31, 61, 0.6);
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
}

.stat-value {
  font-size: 24px;
  font-weight: 700;
  color: #001f3d;
  line-height: 1;
}

.modern-card {
  background: white;
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid rgba(0, 31, 61, 0.08);
  padding: 24px;
}

.gap-2 {
  gap: 8px;
}
</style>
