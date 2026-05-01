<template>
  <div class="allowance-page">
    <div class="modern-card">
      <div class="page-header">
        <div class="page-icon-badge">
          <v-icon icon="mdi-cash-multiple" size="24" color="white"></v-icon>
        </div>
        <div class="page-header-content">
          <h1 class="page-title">Allowance Management</h1>
          <p class="page-subtitle">
            Assign meal, travel, and other allowances directly to personnel
          </p>
        </div>
        <button
          v-if="canCreate && hasAccess"
          class="action-btn action-btn-primary"
          @click="openCreateDialog"
        >
          <v-icon size="20">mdi-plus</v-icon>
          <span>Add Allowance</span>
        </button>
      </div>

      <template v-if="!isAdminOrHr && !hasAccess">
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
            manage allowances.
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
            manage allowances once approved.
          </p>
        </v-alert>

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
                  <template #prepend>
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
                  <template #append>
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

        <v-dialog v-model="requestDialog" max-width="500" persistent>
          <v-card rounded="lg">
            <v-card-title class="d-flex align-center pa-4">
              <v-icon color="primary" class="mr-2">
                mdi-lock-open-variant
              </v-icon>
              Request Allowances Access
            </v-card-title>
            <v-divider></v-divider>
            <v-card-text class="pa-4">
              <p class="text-body-2 mb-4">
                Please provide a reason for needing access to the Allowances
                module.
              </p>
              <v-textarea
                v-model="requestReason"
                label="Reason"
                variant="outlined"
                rows="3"
                :rules="[(v) => !!v || 'Reason is required']"
                placeholder="Explain why you need access to manage allowances"
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

      <template v-if="hasAccess">
        <div class="filters-section">
          <v-row>
            <v-col cols="12" md="4">
              <v-autocomplete
                v-model="filters.employee_id"
                :items="employeeOptions"
                item-title="label"
                item-value="value"
                label="Employee"
                variant="outlined"
                density="comfortable"
                :custom-filter="employeeSearchFilter"
                no-data-text="No matching employees"
                clearable
                hide-details
                @update:model-value="onFilterChange"
              ></v-autocomplete>
            </v-col>

            <v-col cols="12" md="4">
              <v-select
                v-model="filters.allowance_type"
                :items="allowanceTypeOptions"
                item-title="title"
                item-value="value"
                label="Allowance Type"
                variant="outlined"
                density="comfortable"
                clearable
                hide-details
                @update:model-value="onFilterChange"
              ></v-select>
            </v-col>

            <v-col cols="12" md="4">
              <v-select
                v-model="filters.status"
                :items="statusOptions"
                item-title="title"
                item-value="value"
                label="Approval Status"
                variant="outlined"
                density="comfortable"
                clearable
                hide-details
                @update:model-value="onFilterChange"
              ></v-select>
            </v-col>
          </v-row>
        </div>

        <div
          v-if="canApprove && filters.status === 'pending' && hasPendingOnPage"
          class="bulk-actions-bar"
        >
          <v-checkbox-btn
            :model-value="allPendingSelected"
            :indeterminate="hasPartialPendingSelection"
            color="primary"
            :disabled="loading || selectingAllPending || processingBulkApproval"
            @update:model-value="toggleSelectAllPending"
          ></v-checkbox-btn>
          <span class="bulk-actions-count">
            {{ selectedPendingIds.length }} selected
          </span>
          <v-btn
            color="success"
            variant="flat"
            size="small"
            prepend-icon="mdi-check-circle"
            :disabled="selectedPendingIds.length === 0"
            :loading="processingBulkApproval"
            @click="approveSelectedAllowances"
          >
            Approve Selected
          </v-btn>
        </div>

        <v-data-table-server
          :headers="headers"
          :items="allowances"
          :loading="loading"
          :items-length="totalItems"
          :items-per-page="itemsPerPage"
          :page="currentPage"
          :items-per-page-options="[
            { value: 10, title: '10' },
            { value: 15, title: '15' },
            { value: 25, title: '25' },
            { value: 50, title: '50' },
          ]"
          class="modern-table"
          @update:page="onPageChange"
          @update:items-per-page="onItemsPerPageChange"
        >
          <template #[`header.bulk_select`]>
            <v-checkbox-btn
              :model-value="allPendingSelected"
              :indeterminate="hasPartialPendingSelection"
              color="primary"
              :disabled="
                loading || selectingAllPending || processingBulkApproval
              "
              @update:model-value="toggleSelectAllPending"
            ></v-checkbox-btn>
          </template>

          <template #[`item.bulk_select`]="{ item }">
            <v-checkbox-btn
              v-if="item.status === 'pending'"
              :model-value="selectedPendingIds.includes(item.id)"
              color="primary"
              @update:model-value="togglePendingSelection(item.id, $event)"
            ></v-checkbox-btn>
          </template>

          <template #[`item.employee`]="{ item }">
            <div>
              <strong>{{ getEmployeeName(item) }}</strong>
              <div class="text-caption text-grey">
                {{ item.employee?.employee_number || "No employee no." }}
              </div>
            </div>
          </template>

          <template #[`item.allowance_type`]="{ item }">
            <v-chip size="small" color="info" variant="tonal">
              {{ getAllowanceTypeLabel(item.allowance_type) }}
            </v-chip>
          </template>

          <template #[`item.allowance_name`]="{ item }">
            <div>
              <div>
                {{
                  item.allowance_name ||
                  `${getAllowanceTypeLabel(item.allowance_type)} Allowance`
                }}
              </div>
              <div
                v-if="item.request_batch_id"
                class="text-caption text-medium-emphasis"
              >
                Batch {{ shortBatchId(item.request_batch_id) }}
              </div>
            </div>
          </template>

          <template #[`item.amount`]="{ item }">
            <strong>₱{{ formatNumber(item.amount) }}</strong>
          </template>

          <template #[`item.frequency`]="{ item }">
            {{ getFrequencyLabel(item.frequency) }}
          </template>

          <template #[`item.effective_period`]="{ item }">
            <div>{{ formatDate(item.effective_date) }}</div>
            <div class="text-caption text-grey">
              Until
              {{ item.end_date ? formatDate(item.end_date) : "No end date" }}
            </div>
          </template>

          <template #[`item.status`]="{ item }">
            <v-chip
              :color="getApprovalStatusColor(item.status)"
              size="small"
              variant="tonal"
            >
              {{ getApprovalStatusLabel(item.status) }}
            </v-chip>
          </template>

          <template #[`item.is_active`]="{ item }">
            <v-chip
              :color="item.is_active ? 'success' : 'grey'"
              size="small"
              variant="tonal"
            >
              {{ item.is_active ? "Active" : "Inactive" }}
            </v-chip>
          </template>

          <template #[`item.actions`]="{ item }">
            <v-menu location="bottom end">
              <template #activator="{ props }">
                <v-btn
                  v-bind="props"
                  icon="mdi-dots-vertical"
                  size="small"
                  variant="text"
                ></v-btn>
              </template>
              <v-list density="compact">
                <v-list-item @click="viewDetails(item)">
                  <template #prepend>
                    <v-icon size="18">mdi-eye</v-icon>
                  </template>
                  <v-list-item-title>View Details</v-list-item-title>
                </v-list-item>

                <v-list-item
                  v-if="canEdit && canEditRecord(item)"
                  @click="editAllowance(item)"
                >
                  <template #prepend>
                    <v-icon size="18" color="primary">mdi-pencil</v-icon>
                  </template>
                  <v-list-item-title>Edit</v-list-item-title>
                </v-list-item>

                <v-list-item
                  v-if="canApprove && item.status === 'pending'"
                  @click="approveAllowance(item)"
                >
                  <template #prepend>
                    <v-icon size="18" color="success">mdi-check-circle</v-icon>
                  </template>
                  <v-list-item-title>Approve</v-list-item-title>
                </v-list-item>

                <v-list-item
                  v-if="
                    canApprove &&
                    item.status === 'pending' &&
                    item.request_batch_id
                  "
                  @click="approveRequestBatch(item)"
                >
                  <template #prepend>
                    <v-icon size="18" color="success">mdi-check-all</v-icon>
                  </template>
                  <v-list-item-title>Approve Request Batch</v-list-item-title>
                </v-list-item>

                <v-list-item
                  v-if="canApprove && item.status === 'pending'"
                  @click="rejectAllowance(item)"
                >
                  <template #prepend>
                    <v-icon size="18" color="error">mdi-close-circle</v-icon>
                  </template>
                  <v-list-item-title>Reject</v-list-item-title>
                </v-list-item>

                <v-divider></v-divider>

                <v-list-item
                  v-if="canDelete && canDeleteRecord(item)"
                  @click="deleteAllowance(item)"
                >
                  <template #prepend>
                    <v-icon size="18" color="error">mdi-delete</v-icon>
                  </template>
                  <v-list-item-title>Delete</v-list-item-title>
                </v-list-item>
              </v-list>
            </v-menu>
          </template>
        </v-data-table-server>
      </template>
    </div>

    <v-dialog v-model="showFormDialog" max-width="760" persistent>
      <v-card class="modern-dialog">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper primary">
            <v-icon size="24">mdi-cash-plus</v-icon>
          </div>
          <div>
            <div class="dialog-title">
              {{ isEditMode ? "Edit Allowance" : "Add Allowance" }}
            </div>
            <div class="dialog-subtitle">
              Assign an allowance to one or more employees
            </div>
          </div>
        </v-card-title>

        <v-divider></v-divider>

        <v-card-text class="dialog-content">
          <v-form ref="formRef" @submit.prevent="saveAllowance">
            <v-row>
              <v-col v-if="!isEditMode" cols="12" md="6">
                <v-select
                  v-model="form.assignment_mode"
                  :items="assignmentModeOptions"
                  item-title="title"
                  item-value="value"
                  label="Assignment Mode"
                  variant="outlined"
                  density="comfortable"
                  @update:model-value="onAssignmentModeChange"
                ></v-select>
              </v-col>

              <v-col
                v-if="isEditMode || form.assignment_mode === 'single'"
                cols="12"
                md="6"
              >
                <v-autocomplete
                  v-model="form.employee_id"
                  :items="employeeOptions"
                  item-title="label"
                  item-value="value"
                  label="Employee *"
                  variant="outlined"
                  density="comfortable"
                  :custom-filter="employeeSearchFilter"
                  no-data-text="No matching employees"
                  :rules="[rules.required]"
                  :disabled="isEditMode"
                ></v-autocomplete>
              </v-col>

              <v-col
                v-else-if="form.assignment_mode === 'multiple'"
                cols="12"
                md="6"
              >
                <v-autocomplete
                  v-model="form.employee_ids"
                  :items="employeeOptions"
                  item-title="label"
                  item-value="value"
                  label="Employees *"
                  variant="outlined"
                  density="comfortable"
                  multiple
                  :menu-props="{ maxHeight: 320 }"
                  :custom-filter="employeeSearchFilter"
                  no-data-text="No matching employees"
                  :rules="[rules.requiredArray]"
                >
                  <template #selection="{ index }">
                    <span v-if="index === 0" class="text-body-2 text-truncate">
                      {{ selectedEmployeeSummary }}
                    </span>
                  </template>
                </v-autocomplete>
              </v-col>

              <v-col
                v-else-if="form.assignment_mode === 'project'"
                cols="12"
                md="6"
              >
                <v-autocomplete
                  v-model="form.project_ids"
                  :items="projectOptions"
                  item-title="label"
                  item-value="value"
                  label="Projects *"
                  variant="outlined"
                  density="comfortable"
                  multiple
                  :menu-props="{ maxHeight: 320 }"
                  no-data-text="No projects available"
                  :rules="[rules.requiredArray]"
                >
                  <template #selection="{ index }">
                    <span v-if="index === 0" class="text-body-2 text-truncate">
                      {{ selectedProjectSummary }}
                    </span>
                  </template>
                </v-autocomplete>
              </v-col>

              <v-col
                v-else-if="form.assignment_mode === 'position'"
                cols="12"
                md="6"
              >
                <v-autocomplete
                  v-model="form.position_ids"
                  :items="positionOptions"
                  item-title="label"
                  item-value="value"
                  label="Positions *"
                  variant="outlined"
                  density="comfortable"
                  multiple
                  :menu-props="{ maxHeight: 320 }"
                  no-data-text="No positions available"
                  :rules="[rules.requiredArray]"
                >
                  <template #selection="{ index }">
                    <span v-if="index === 0" class="text-body-2 text-truncate">
                      {{ selectedPositionSummary }}
                    </span>
                  </template>
                </v-autocomplete>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="form.allowance_type"
                  :items="allowanceTypeOptions"
                  item-title="title"
                  item-value="value"
                  label="Allowance Type *"
                  variant="outlined"
                  density="comfortable"
                  :rules="[rules.required]"
                ></v-select>
              </v-col>

              <v-col
                cols="12"
                v-if="!isEditMode && form.assignment_mode !== 'single'"
              >
                <v-alert type="info" variant="tonal" density="compact">
                  {{ bulkSelectionSummary }}
                </v-alert>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="form.allowance_name"
                  label="Allowance Name"
                  placeholder="Optional custom label"
                  variant="outlined"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model.number="form.amount"
                  :label="amountFieldLabel"
                  type="number"
                  min="0"
                  step="0.01"
                  variant="outlined"
                  density="comfortable"
                  :rules="[rules.required, rules.positiveAmount]"
                  prefix="₱"
                  :hint="amountFieldHint"
                  persistent-hint
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="form.frequency"
                  :items="frequencyOptions"
                  item-title="title"
                  item-value="value"
                  label="Frequency *"
                  variant="outlined"
                  density="comfortable"
                  :rules="[rules.required]"
                ></v-select>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="form.effective_date"
                  label="Coverage Start *"
                  type="date"
                  variant="outlined"
                  density="comfortable"
                  hint="Automatically included in payrolls whose period overlaps this coverage window"
                  persistent-hint
                  :rules="[rules.required]"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="form.end_date"
                  label="Coverage End"
                  type="date"
                  variant="outlined"
                  density="comfortable"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-switch
                  v-model="form.is_active"
                  label="Active"
                  color="success"
                  inset
                  hide-details
                ></v-switch>
              </v-col>

              <v-col cols="12">
                <v-textarea
                  v-model="form.notes"
                  label="Notes"
                  variant="outlined"
                  density="comfortable"
                  rows="3"
                  auto-grow
                ></v-textarea>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <button
            type="button"
            class="dialog-btn dialog-btn-cancel"
            @click="closeFormDialog"
            :disabled="saving"
          >
            Cancel
          </button>
          <button
            type="button"
            class="dialog-btn dialog-btn-primary"
            @click="saveAllowance"
            :disabled="saving"
          >
            <v-progress-circular
              v-if="saving"
              indeterminate
              size="16"
              width="2"
              class="mr-2"
            ></v-progress-circular>
            <v-icon v-else size="18" class="mr-1">mdi-content-save</v-icon>
            {{ saving ? "Saving..." : isEditMode ? "Update" : "Save" }}
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="showDetailsDialog" max-width="760">
      <v-card v-if="selectedAllowance" class="modern-dialog">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper secondary">
            <v-icon size="24">mdi-file-document</v-icon>
          </div>
          <div>
            <div class="dialog-title">Allowance Details</div>
            <div class="dialog-subtitle">
              {{
                selectedAllowance.allowance_name ||
                `${getAllowanceTypeLabel(selectedAllowance.allowance_type)} Allowance`
              }}
            </div>
          </div>
        </v-card-title>

        <v-divider></v-divider>

        <v-card-text class="dialog-content detail-grid">
          <div class="detail-item">
            <span class="detail-label">Employee</span>
            <span class="detail-value">{{
              getEmployeeName(selectedAllowance)
            }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Allowance Type</span>
            <span class="detail-value">{{
              getAllowanceTypeLabel(selectedAllowance.allowance_type)
            }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Amount</span>
            <span class="detail-value"
              >₱{{ formatNumber(selectedAllowance.amount) }}</span
            >
          </div>
          <div class="detail-item">
            <span class="detail-label">Frequency</span>
            <span class="detail-value">{{
              getFrequencyLabel(selectedAllowance.frequency)
            }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Coverage Start</span>
            <span class="detail-value">{{
              formatDate(selectedAllowance.effective_date)
            }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Coverage End</span>
            <span class="detail-value">{{
              selectedAllowance.end_date
                ? formatDate(selectedAllowance.end_date)
                : "No end date"
            }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Approval Status</span>
            <span class="detail-value">{{
              getApprovalStatusLabel(selectedAllowance.status)
            }}</span>
          </div>
          <div class="detail-item">
            <span class="detail-label">Activation</span>
            <span class="detail-value">{{
              selectedAllowance.is_active ? "Active" : "Inactive"
            }}</span>
          </div>
          <div
            class="detail-item full-width"
            v-if="selectedAllowance.approver || selectedAllowance.approved_at"
          >
            <span class="detail-label">Approved By</span>
            <span class="detail-value">
              {{
                selectedAllowance.approver?.name ||
                selectedAllowance.approver?.username ||
                "System"
              }}
              <span v-if="selectedAllowance.approved_at">
                on {{ formatDate(selectedAllowance.approved_at) }}
              </span>
            </span>
          </div>
          <div
            class="detail-item full-width"
            v-if="selectedAllowance.rejection_reason"
          >
            <span class="detail-label">Rejection Reason</span>
            <span class="detail-value">{{
              selectedAllowance.rejection_reason
            }}</span>
          </div>
          <div class="detail-item full-width" v-if="selectedAllowance.notes">
            <span class="detail-label">Notes</span>
            <span class="detail-value">{{ selectedAllowance.notes }}</span>
          </div>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <button
            class="dialog-btn dialog-btn-cancel"
            @click="showDetailsDialog = false"
          >
            Close
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-dialog v-model="showRejectDialog" max-width="560" persistent>
      <v-card class="modern-dialog">
        <v-card-title class="d-flex align-center pa-4">
          <v-icon color="error" class="mr-2">mdi-close-circle</v-icon>
          Reject Allowance
        </v-card-title>
        <v-divider></v-divider>
        <v-card-text class="pa-4">
          <p class="text-body-2 mb-3">
            You are rejecting
            <strong>
              {{
                rejectingAllowance?.allowance_name ||
                `${getAllowanceTypeLabel(rejectingAllowance?.allowance_type)} Allowance`
              }}
            </strong>
            for
            <strong>{{ getEmployeeName(rejectingAllowance || {}) }}</strong
            >.
          </p>
          <v-textarea
            v-model="rejectionReason"
            label="Rejection Reason"
            placeholder="Optional reason for rejection"
            variant="outlined"
            rows="3"
            auto-grow
          ></v-textarea>
        </v-card-text>
        <v-divider></v-divider>
        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn
            variant="text"
            :disabled="processingApproval"
            @click="closeRejectDialog"
          >
            Cancel
          </v-btn>
          <v-btn
            color="error"
            variant="flat"
            :loading="processingApproval"
            @click="submitRejectAllowance"
          >
            Reject Allowance
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from "vue";
import { useRoute } from "vue-router";
import { useToast } from "vue-toastification";
import { useAuthStore } from "@/stores/auth";
import allowanceService from "@/services/employeeAllowanceService";
import moduleAccessService from "@/services/moduleAccessService";
import api from "@/services/api";
import { formatDate, formatNumber } from "@/utils/formatters";
import { devLog } from "@/utils/devLog";
import { useConfirmDialog } from "@/composables/useConfirmDialog";

const toast = useToast();
const route = useRoute();
const authStore = useAuthStore();
const { confirm: confirmDialog } = useConfirmDialog();

const isAdminOrHr = computed(() =>
  ["admin", "hr"].includes(authStore.user?.role),
);

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
    accessStatus.value === "admin",
);

const loading = ref(false);
const saving = ref(false);
const allowances = ref([]);
const employees = ref([]);
const selectedAllowance = ref(null);
const showFormDialog = ref(false);
const showDetailsDialog = ref(false);
const showRejectDialog = ref(false);
const rejectingAllowance = ref(null);
const rejectionReason = ref("");
const processingApproval = ref(false);
const processingBulkApproval = ref(false);
const processingBatchApproval = ref(false);
const selectingAllPending = ref(false);
const selectedPendingIds = ref([]);
const formRef = ref(null);

const currentPage = ref(1);
const itemsPerPage = ref(15);
const totalItems = ref(0);

const filters = ref({
  employee_id: null,
  allowance_type: null,
  status: null,
});

const allowanceTypeOptions = [
  { title: "Meal", value: "meal" },
  { title: "Travel", value: "transportation" },
  { title: "Communication", value: "communication" },
  { title: "Housing", value: "housing" },
  { title: "Clothing", value: "clothing" },
  { title: "Medical", value: "medical" },
  { title: "Education", value: "education" },
  { title: "Performance", value: "performance" },
  { title: "Hazard", value: "hazard" },
  { title: "Incentive", value: "incentive" },
  { title: "COLA", value: "cola" },
  { title: "PPE", value: "ppe" },
  { title: "Water", value: "water" },
  { title: "Other", value: "other" },
];

const frequencyOptions = [
  { title: "Daily", value: "daily" },
  { title: "Weekly", value: "weekly" },
  { title: "Semi-Monthly", value: "semi_monthly" },
  { title: "Monthly", value: "monthly" },
];

const statusOptions = [
  { title: "Pending Approval", value: "pending" },
  { title: "Approved", value: "approved" },
  { title: "Rejected", value: "rejected" },
];

const assignmentModeOptions = [
  { title: "Single Employee", value: "single" },
  { title: "Multiple Employees", value: "multiple" },
  { title: "By Project", value: "project" },
  { title: "By Position", value: "position" },
];

const frequencyToDailyDivisor = {
  daily: 1,
  weekly: 5,
  semi_monthly: 11,
  monthly: 22,
};

const baseHeaders = [
  { title: "Employee", key: "employee", sortable: false },
  { title: "Type", key: "allowance_type", sortable: false },
  { title: "Name", key: "allowance_name", sortable: false },
  { title: "Amount", key: "amount", sortable: false },
  { title: "Frequency", key: "frequency", sortable: false },
  { title: "Coverage Period", key: "effective_period", sortable: false },
  { title: "Approval", key: "status", sortable: false },
  { title: "Activation", key: "is_active", sortable: false },
  { title: "Actions", key: "actions", sortable: false, align: "center" },
];

const headers = computed(() => {
  if (!(canApprove.value && filters.value.status === "pending")) {
    return baseHeaders;
  }

  return [
    { title: "", key: "bulk_select", sortable: false, width: "52px" },
    ...baseHeaders,
  ];
});

const rules = {
  required: (v) => !!v || "Required",
  requiredArray: (v) => (Array.isArray(v) && v.length > 0) || "Required",
  positiveAmount: (v) => Number(v) > 0 || "Amount must be greater than 0",
};

const canCreate = computed(() =>
  ["admin", "hr", "payrollist"].includes(authStore.user?.role),
);
const canEdit = computed(() =>
  ["admin", "hr", "payrollist"].includes(authStore.user?.role),
);
const canDelete = computed(() =>
  ["admin", "hr", "payrollist"].includes(authStore.user?.role),
);
const canApprove = computed(() => authStore.user?.role === "admin");

const employeeOptions = computed(() =>
  employees.value.map((employee) => ({
    value: employee.id,
    label: `${employee.name}${employee.employee_number ? ` (${employee.employee_number})` : ""} - ${employee.project_name} / ${employee.position_name}`,
    searchText: `${employee.name} ${employee.employee_number || ""} ${employee.project_name} ${employee.position_name}`,
  })),
);

const projectOptions = computed(() => {
  const map = new Map();

  employees.value.forEach((employee) => {
    if (!employee.project_id) {
      return;
    }

    map.set(employee.project_id, {
      value: employee.project_id,
      label: employee.project_name,
    });
  });

  return Array.from(map.values()).sort((a, b) =>
    a.label.localeCompare(b.label),
  );
});

const positionOptions = computed(() => {
  const map = new Map();

  employees.value.forEach((employee) => {
    if (!employee.position_id) {
      return;
    }

    map.set(employee.position_id, {
      value: employee.position_id,
      label: employee.position_name,
    });
  });

  return Array.from(map.values()).sort((a, b) =>
    a.label.localeCompare(b.label),
  );
});

const targetEmployeeIds = computed(() => {
  if (isEditMode.value) {
    return form.value.employee_id ? [form.value.employee_id] : [];
  }

  const assignmentMode = form.value.assignment_mode;

  if (assignmentMode === "single") {
    return form.value.employee_id ? [form.value.employee_id] : [];
  }

  if (assignmentMode === "multiple") {
    return Array.isArray(form.value.employee_ids)
      ? [...new Set(form.value.employee_ids)]
      : [];
  }

  if (assignmentMode === "project") {
    const selectedProjects = new Set(form.value.project_ids || []);
    return employees.value
      .filter((employee) => selectedProjects.has(employee.project_id))
      .map((employee) => employee.id);
  }

  if (assignmentMode === "position") {
    const selectedPositions = new Set(form.value.position_ids || []);
    return employees.value
      .filter((employee) => selectedPositions.has(employee.position_id))
      .map((employee) => employee.id);
  }

  return [];
});

const bulkSelectionSummary = computed(() => {
  if (isEditMode.value || form.value.assignment_mode === "single") {
    return "";
  }

  const count = targetEmployeeIds.value.length;
  if (count === 0) {
    return "No employees matched the selected target yet.";
  }

  return `${count} employee${count > 1 ? "s" : ""} will receive this allowance.`;
});

const selectedEmployeeSummary = computed(() =>
  getSelectionSummary(form.value.employee_ids, employeeOptions.value),
);

const selectedProjectSummary = computed(() =>
  getSelectionSummary(form.value.project_ids, projectOptions.value),
);

const selectedPositionSummary = computed(() =>
  getSelectionSummary(form.value.position_ids, positionOptions.value),
);

const pendingAllowanceIds = computed(() =>
  allowances.value
    .filter((allowance) => allowance.status === "pending")
    .map((allowance) => allowance.id),
);

const hasPendingOnPage = computed(() => pendingAllowanceIds.value.length > 0);

const allPendingSelected = computed(() => {
  const totalPendingItems = Number(totalItems.value || 0);

  return (
    filters.value.status === "pending" &&
    totalPendingItems > 0 &&
    selectedPendingIds.value.length === totalPendingItems
  );
});

const hasPartialPendingSelection = computed(() => {
  const selectedCount = selectedPendingIds.value.length;
  const totalPendingItems = Number(totalItems.value || 0);

  return selectedCount > 0 && selectedCount < totalPendingItems;
});

const amountFieldLabel = computed(() =>
  form.value.frequency === "daily" ? "Daily Amount *" : "Amount *",
);

const amountFieldHint = computed(() => {
  const amount = Number(form.value.amount || 0);
  if (amount <= 0) {
    return "Set frequency to Daily if you want to input exact per-day allowance.";
  }

  if (form.value.frequency === "daily") {
    return `Exact daily amount: ₱${formatNumber(amount)} per day.`;
  }

  const divisor = frequencyToDailyDivisor[form.value.frequency] || 1;
  const dailyEquivalent = amount / divisor;

  return `Estimated daily equivalent: ₱${formatNumber(dailyEquivalent)} per day.`;
});

const isEditMode = computed(() => !!selectedAllowance.value?.id);

function getDefaultForm() {
  return {
    assignment_mode: "single",
    employee_id: null,
    employee_ids: [],
    project_ids: [],
    position_ids: [],
    allowance_type: null,
    allowance_name: "",
    amount: null,
    frequency: "semi_monthly",
    effective_date: new Date().toISOString().slice(0, 10),
    end_date: null,
    is_active: true,
    notes: "",
  };
}

const form = ref(getDefaultForm());

const getRequestStatusColor = (status) =>
  ({ pending: "warning", approved: "success", rejected: "error" })[status] ||
  "grey";

const checkModuleAccess = async () => {
  if (isAdminOrHr.value) {
    return;
  }

  try {
    const response = await moduleAccessService.checkAccess("allowances");
    accessStatus.value = response.status;
    accessMessage.value = response.message || "";
  } catch {
    accessStatus.value = "none";
  }
};

const loadMyRequests = async () => {
  if (isAdminOrHr.value) {
    return;
  }

  try {
    const response = await moduleAccessService.getRequests("allowances");
    myRequests.value = response.data || [];
  } catch {
    myRequests.value = [];
  }
};

const submitAccessRequest = async () => {
  if (!requestReason.value) {
    return;
  }

  submittingRequest.value = true;
  try {
    await moduleAccessService.submitRequest("allowances", {
      reason: requestReason.value,
    });
    toast.success("Access request submitted successfully");
    requestDialog.value = false;
    requestReason.value = "";
    accessStatus.value = "pending";
    await loadMyRequests();
  } catch (error) {
    toast.error(error.response?.data?.message || "Failed to submit request");
  } finally {
    submittingRequest.value = false;
  }
};

onMounted(async () => {
  const preselectedStatus = route.query.status;
  if (
    typeof preselectedStatus === "string" &&
    statusOptions.some((option) => option.value === preselectedStatus)
  ) {
    filters.value.status = preselectedStatus;
  }

  await checkModuleAccess();
  await loadMyRequests();

  if (hasAccess.value) {
    await Promise.all([fetchEmployees(), fetchAllowances(1)]);
  }
});

async function fetchEmployees() {
  try {
    const response = await api.get("/employees", {
      params: {
        per_page: 10000,
        activity_status: "active,on_leave",
      },
    });

    const data = Array.isArray(response.data)
      ? response.data
      : response.data?.data || [];

    employees.value = data.map((employee) => ({
      ...employee,
      name:
        employee.full_name ||
        `${employee.first_name || ""} ${employee.last_name || ""}`.trim(),
      project_name: employee.project?.name || "No Project",
      position_name:
        employee.positionRate?.position_name ||
        employee.position ||
        "No Position",
    }));
  } catch (error) {
    devLog.error("Error fetching employees:", error);
    toast.error("Failed to fetch employees");
    employees.value = [];
  }
}

async function fetchAllowances(page = currentPage.value) {
  loading.value = true;
  try {
    currentPage.value = page;
    const params = {
      page,
      per_page: itemsPerPage.value,
      employee_id: filters.value.employee_id || undefined,
      allowance_type: filters.value.allowance_type || undefined,
      status: filters.value.status || undefined,
    };

    const response = await allowanceService.getAll(params);

    allowances.value = Array.isArray(response?.data) ? response.data : [];
    currentPage.value = response?.current_page || page;
    totalItems.value = Number(response?.total || allowances.value.length || 0);
  } catch (error) {
    devLog.error("Error fetching allowances:", error);
    toast.error("Failed to fetch allowances");
    allowances.value = [];
    selectedPendingIds.value = [];
    currentPage.value = 1;
    totalItems.value = 0;
  } finally {
    loading.value = false;
  }
}

function onFilterChange() {
  selectedPendingIds.value = [];
  currentPage.value = 1;
  fetchAllowances(1);
}

function onPageChange(page) {
  if (page === currentPage.value) {
    return;
  }

  fetchAllowances(page);
}

function onItemsPerPageChange(perPage) {
  const normalizedPerPage = Number(perPage) > 0 ? Number(perPage) : 15;
  if (normalizedPerPage === itemsPerPage.value) {
    return;
  }

  itemsPerPage.value = normalizedPerPage;
  fetchAllowances(1);
}

function togglePendingSelection(id, checked) {
  if (!checked) {
    selectedPendingIds.value = selectedPendingIds.value.filter(
      (selectedId) => selectedId !== id,
    );
    return;
  }

  if (!selectedPendingIds.value.includes(id)) {
    selectedPendingIds.value = [...selectedPendingIds.value, id];
  }
}

async function toggleSelectAllPending(checked) {
  if (!checked) {
    selectedPendingIds.value = [];
    return;
  }

  if (filters.value.status !== "pending") {
    return;
  }

  selectingAllPending.value = true;
  try {
    const ids = [];
    let page = 1;
    let lastPage = 1;

    do {
      const response = await allowanceService.getAll({
        page,
        per_page: 200,
        employee_id: filters.value.employee_id || undefined,
        allowance_type: filters.value.allowance_type || undefined,
        status: "pending",
      });

      const rows = Array.isArray(response?.data) ? response.data : [];
      rows.forEach((allowance) => {
        if (allowance?.status === "pending" && allowance?.id) {
          ids.push(allowance.id);
        }
      });

      lastPage = Number(response?.last_page || 1);
      page += 1;
    } while (page <= lastPage);

    selectedPendingIds.value = Array.from(new Set(ids));
  } catch (error) {
    devLog.error("Error selecting all pending allowances:", error);
    toast.error("Failed to select all pending allowances");
  } finally {
    selectingAllPending.value = false;
  }
}

function onAssignmentModeChange() {
  form.value.employee_id = null;
  form.value.employee_ids = [];
  form.value.project_ids = [];
  form.value.position_ids = [];
}

function getSelectionSummary(selectedValues, options) {
  if (!Array.isArray(selectedValues) || selectedValues.length === 0) {
    return "";
  }

  const firstValue = selectedValues[0];
  const firstLabel =
    options.find((option) => option.value === firstValue)?.label ||
    String(firstValue);

  if (selectedValues.length === 1) {
    return firstLabel;
  }

  return `${firstLabel} +${selectedValues.length - 1} more`;
}

function employeeSearchFilter(_, queryText, item) {
  const query = (queryText || "").toLowerCase().trim();
  if (!query) {
    return true;
  }

  const rawItem = item?.raw || {};
  const searchable = [rawItem.label, rawItem.searchText, rawItem.value]
    .filter(Boolean)
    .join(" ")
    .toLowerCase();

  return searchable.includes(query);
}

function openCreateDialog() {
  selectedAllowance.value = null;
  form.value = getDefaultForm();
  showFormDialog.value = true;
}

function closeFormDialog() {
  showFormDialog.value = false;
  selectedAllowance.value = null;
  form.value = getDefaultForm();
}

function editAllowance(item) {
  selectedAllowance.value = item;
  form.value = {
    assignment_mode: "single",
    employee_id: item.employee_id,
    employee_ids: [],
    project_ids: [],
    position_ids: [],
    allowance_type: item.allowance_type,
    allowance_name: item.allowance_name || "",
    amount: Number(item.amount),
    frequency: item.frequency || "semi_monthly",
    effective_date: item.effective_date?.slice(0, 10) || "",
    end_date: item.end_date?.slice(0, 10) || null,
    is_active: !!item.is_active,
    notes: item.notes || "",
  };
  showFormDialog.value = true;
}

function viewDetails(item) {
  selectedAllowance.value = item;
  showDetailsDialog.value = true;
}

async function saveAllowance() {
  if (saving.value) {
    return;
  }

  saving.value = true;

  const { valid } = await formRef.value.validate();
  if (!valid) {
    saving.value = false;
    return;
  }

  if (form.value.end_date && form.value.end_date < form.value.effective_date) {
    toast.error(
      "Coverage end must be the same as or later than coverage start",
    );
    saving.value = false;
    return;
  }

  const selectedEmployeeIds = [...new Set(targetEmployeeIds.value)];
  if (!isEditMode.value && selectedEmployeeIds.length === 0) {
    toast.error("Please select at least one employee target");
    saving.value = false;
    return;
  }

  try {
    const basePayload = {
      allowance_type: form.value.allowance_type,
      amount: Number(form.value.amount),
      frequency: form.value.frequency,
      effective_date: form.value.effective_date,
      end_date: form.value.end_date || null,
      is_taxable: false,
      is_active: !!form.value.is_active,
      allowance_name:
        form.value.allowance_name?.trim() ||
        `${getAllowanceTypeLabel(form.value.allowance_type)} Allowance`,
      notes: form.value.notes?.trim() || null,
    };

    let response;
    if (isEditMode.value) {
      response = await allowanceService.update(
        selectedAllowance.value.id,
        basePayload,
      );
      toast.success(response?.message || "Allowance updated successfully");
      await fetchAllowances(currentPage.value);
    } else {
      response = await allowanceService.create({
        ...basePayload,
        ...(selectedEmployeeIds.length === 1
          ? { employee_id: selectedEmployeeIds[0] }
          : { employee_ids: selectedEmployeeIds }),
      });
      toast.success(response?.message || "Allowance created successfully");
      await fetchAllowances(1);
    }

    closeFormDialog();
  } catch (error) {
    devLog.error("Error saving allowance:", error);
    toast.error(error.response?.data?.message || "Failed to save allowance");
  } finally {
    saving.value = false;
  }
}

async function deleteAllowance(item) {
  const label =
    item.allowance_name ||
    `${getAllowanceTypeLabel(item.allowance_type)} Allowance`;

  if (!(await confirmDialog(`Delete ${label} for ${getEmployeeName(item)}?`))) {
    return;
  }

  try {
    await allowanceService.delete(item.id);
    toast.success("Allowance deleted successfully");
    await fetchAllowances(currentPage.value);
  } catch (error) {
    devLog.error("Error deleting allowance:", error);
    toast.error(error.response?.data?.message || "Failed to delete allowance");
  }
}

async function approveAllowance(item) {
  if (
    !(await confirmDialog(
      `Approve ${item.allowance_name || "this allowance"}?`,
    ))
  ) {
    return;
  }

  try {
    const response = await allowanceService.updateApproval(item.id, "approve");
    toast.success(response?.message || "Allowance approved successfully");
    await fetchAllowances(currentPage.value);
  } catch (error) {
    devLog.error("Error approving allowance:", error);
    toast.error(error.response?.data?.message || "Failed to approve allowance");
  }
}

async function approveRequestBatch(item) {
  if (!item?.request_batch_id) {
    return;
  }

  if (processingBatchApproval.value) {
    return;
  }

  let batchPendingCount = null;

  try {
    const response = await allowanceService.getAll({
      request_batch_id: item.request_batch_id,
      status: "pending",
      page: 1,
      per_page: 1,
    });

    batchPendingCount = Number(response?.total || 0);
  } catch {
    // Non-blocking: still allow confirmation and processing.
  }

  const confirmationLabel =
    batchPendingCount && batchPendingCount > 0
      ? `${batchPendingCount} pending allowance${batchPendingCount > 1 ? "s" : ""}`
      : "all pending allowances in this request batch";

  if (!(await confirmDialog(`Approve ${confirmationLabel}?`))) {
    return;
  }

  processingBatchApproval.value = true;

  try {
    const response = await allowanceService.updateBatchApproval(
      item.request_batch_id,
      "approve",
    );

    toast.success(response?.message || "Request batch approved successfully");
    selectedPendingIds.value = [];
    await fetchAllowances(currentPage.value);
  } catch (error) {
    devLog.error("Error approving request batch:", error);
    toast.error(
      error.response?.data?.message || "Failed to approve request batch",
    );
  } finally {
    processingBatchApproval.value = false;
  }
}

async function approveSelectedAllowances() {
  if (selectedPendingIds.value.length === 0) {
    return;
  }

  const selectedCount = selectedPendingIds.value.length;
  if (
    !(await confirmDialog(
      `Approve ${selectedCount} selected pending allowance${selectedCount > 1 ? "s" : ""}?`,
    ))
  ) {
    return;
  }

  processingBulkApproval.value = true;

  try {
    const response = await allowanceService.updateBulkApproval(
      selectedPendingIds.value,
      "approve",
    );

    toast.success(response?.message || "Selected allowances approved");
    selectedPendingIds.value = [];
    await fetchAllowances(currentPage.value);
  } catch (error) {
    devLog.error("Error bulk approving allowances:", error);
    toast.error(
      error.response?.data?.message || "Failed to bulk approve allowances",
    );
  } finally {
    processingBulkApproval.value = false;
  }
}

async function rejectAllowance(item) {
  rejectingAllowance.value = item;
  rejectionReason.value = "";
  showRejectDialog.value = true;
}

function closeRejectDialog() {
  showRejectDialog.value = false;
  rejectingAllowance.value = null;
  rejectionReason.value = "";
}

async function submitRejectAllowance() {
  if (!rejectingAllowance.value) {
    return;
  }

  processingApproval.value = true;

  try {
    const response = await allowanceService.updateApproval(
      rejectingAllowance.value.id,
      "reject",
      rejectionReason.value?.trim() || null,
    );
    toast.success(response?.message || "Allowance rejected successfully");
    closeRejectDialog();
    await fetchAllowances(currentPage.value);
  } catch (error) {
    devLog.error("Error rejecting allowance:", error);
    toast.error(error.response?.data?.message || "Failed to reject allowance");
  } finally {
    processingApproval.value = false;
  }
}

function canEditRecord(item) {
  if (canApprove.value) {
    return true;
  }

  return item.status !== "approved";
}

function canDeleteRecord(item) {
  if (canApprove.value) {
    return true;
  }

  return item.status !== "approved";
}

function getEmployeeName(allowance) {
  if (allowance.employee?.full_name) {
    return allowance.employee.full_name;
  }

  if (allowance.employee?.name) {
    return allowance.employee.name;
  }

  const employee = employees.value.find(
    (item) => item.id === allowance.employee_id,
  );
  return employee?.name || "Unknown Employee";
}

function getAllowanceTypeLabel(type) {
  const matched = allowanceTypeOptions.find((item) => item.value === type);
  return matched ? matched.title : type || "Unknown";
}

function getFrequencyLabel(frequency) {
  const matched = frequencyOptions.find((item) => item.value === frequency);
  return matched ? matched.title : frequency || "Unknown";
}

function getApprovalStatusLabel(status) {
  const matched = statusOptions.find((item) => item.value === status);
  return matched ? matched.title : status || "Unknown";
}

function getApprovalStatusColor(status) {
  const statusColors = {
    pending: "warning",
    approved: "success",
    rejected: "error",
  };

  return statusColors[status] || "grey";
}

function shortBatchId(batchId) {
  if (!batchId) {
    return "";
  }

  return String(batchId).slice(-8).toUpperCase();
}
</script>

<style scoped lang="scss">
.allowance-page {
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

.filters-section {
  margin-bottom: 24px;
}

.bulk-actions-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 14px;
  margin-bottom: 14px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  border-radius: 10px;
  background: #f8fafc;
}

.bulk-actions-count {
  font-size: 13px;
  color: #334155;
  margin-right: auto;
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
}

.modern-dialog {
  border-radius: 16px;
  overflow: hidden;
}

.dialog-header {
  background: white;
  padding: 24px;
  display: flex;
  align-items: center;
  gap: 16px;
}

.dialog-icon-wrapper {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;

  &.primary {
    background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
    box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);
  }

  &.secondary {
    background: linear-gradient(135deg, #64748b 0%, #94a3b8 100%);
    box-shadow: 0 4px 12px rgba(100, 116, 139, 0.25);
  }
}

.dialog-title {
  font-size: 20px;
  font-weight: 700;
  color: #001f3d;
  line-height: 1.2;
}

.dialog-subtitle {
  font-size: 13px;
  color: #64748b;
  margin-top: 2px;
}

.dialog-content {
  padding: 24px;
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

.detail-grid {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  gap: 14px;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 4px;
  padding: 10px 12px;
  border-radius: 10px;
  background: #f8fafc;
  border: 1px solid rgba(0, 31, 61, 0.08);
}

.detail-item.full-width {
  grid-column: 1 / -1;
}

.detail-label {
  font-size: 12px;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.3px;
}

.detail-value {
  font-size: 14px;
  color: #001f3d;
  font-weight: 600;
}

@media (max-width: 960px) {
  .page-header {
    flex-wrap: wrap;
  }

  .bulk-actions-bar {
    flex-wrap: wrap;
  }

  .bulk-actions-count {
    width: 100%;
    margin-right: 0;
  }

  .action-btn {
    width: 100%;
    justify-content: center;
  }
}

@media (max-width: 720px) {
  .detail-grid {
    grid-template-columns: 1fr;
  }
}
</style>
