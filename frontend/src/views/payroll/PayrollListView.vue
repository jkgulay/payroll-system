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
          :items="payrolls"
          :search="search"
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
                  v-if="item.status === 'draft'"
                  @click="editPayroll(item)"
                >
                  <template v-slot:prepend>
                    <v-icon size="small" color="warning">mdi-pencil</v-icon>
                  </template>
                  <v-list-item-title>Edit</v-list-item-title>
                </v-list-item>
                <v-divider></v-divider>
                <v-list-item @click="confirmDelete(item)">
                  <template v-slot:prepend>
                    <v-icon size="small" color="error">mdi-delete</v-icon>
                  </template>
                  <v-list-item-title class="text-error"
                    >Delete</v-list-item-title
                  >
                </v-list-item>
              </v-list>
            </v-menu>
          </template>
        </v-data-table>
      </div>
    </div>

    <!-- Create/Edit Dialog - Modern UI -->
    <v-dialog v-model="dialog" max-width="800px" persistent scrollable>
      <v-card class="modern-dialog">
        <!-- Enhanced Header -->
        <v-card-title class="dialog-header">
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

        <v-card-text class="dialog-content" style="max-height: 70vh">
          <v-form ref="form" v-model="valid">
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
            <div class="form-field-wrapper mt-3">
              <label class="form-label">
                <v-icon size="small" color="#ed985f">mdi-label</v-icon>
                Period Name <span class="text-error">*</span>
              </label>
              <v-text-field
                v-model="formData.period_name"
                placeholder="Enter period name"
                :rules="[(v) => !!v || 'Period name is required']"
                required
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-label"
                color="#ed985f"
              ></v-text-field>
            </div>

            <v-row>
              <!-- Period Start -->
              <v-col cols="6">
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
                    :rules="[(v) => !!v || 'Start date is required']"
                    required
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-calendar-start"
                    color="#ed985f"
                  ></v-text-field>
                </div>
              </v-col>

              <!-- Period End -->
              <v-col cols="6">
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
                    :rules="[(v) => !!v || 'End date is required']"
                    required
                    variant="outlined"
                    density="comfortable"
                    prepend-inner-icon="mdi-calendar-end"
                    color="#ed985f"
                  ></v-text-field>
                </div>
              </v-col>
            </v-row>

            <!-- Payment Date -->
            <div class="form-field-wrapper">
              <label class="form-label">
                <v-icon size="small" color="#ed985f">mdi-calendar-check</v-icon>
                Payment Date <span class="text-error">*</span>
              </label>
              <v-text-field
                v-model="formData.payment_date"
                type="date"
                placeholder="Select payment date"
                :rules="[(v) => !!v || 'Payment date is required']"
                required
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-calendar-check"
                color="#ed985f"
              ></v-text-field>
            </div>

            <!-- Section 2: Employee Selection -->
            <v-col cols="12" class="px-0 mt-5">
              <div class="section-header">
                <div class="section-icon">
                  <v-icon size="18">mdi-account-group</v-icon>
                </div>
                <h3 class="section-title">Employee Selection</h3>
              </div>
            </v-col>

            <v-alert type="info" variant="tonal" density="compact" class="mb-4">
              <template v-slot:prepend>
                <v-icon icon="mdi-information"></v-icon>
              </template>
              <div class="text-caption">
                <strong>Note:</strong> Payroll will include all active
                employees. You can filter by department or staff type when
                exporting/downloading the payroll.
              </div>
            </v-alert>

            <!-- Optional: Only With Attendance -->
            <v-checkbox
              v-model="formData.has_attendance"
              label="Only include employees with attendance"
              prepend-icon="mdi-calendar-check"
              hint="Exclude employees who have no attendance records in this payroll period"
              persistent-hint
              color="#ed985f"
              class="mb-4"
            ></v-checkbox>

            <v-textarea
              v-model="formData.notes"
              label="Notes (Optional)"
              rows="3"
              prepend-icon="mdi-note-text"
            ></v-textarea>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <button
            class="dialog-btn dialog-btn-cancel"
            @click="closeDialog"
            :disabled="saving"
          >
            Cancel
          </button>
          <button
            class="dialog-btn dialog-btn-primary"
            @click="savePayroll"
            :disabled="saving"
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
                  ? "Update"
                  : "Create"
            }}
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>

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
              records before creating payroll, or these records will be excluded
              from calculations.
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
          <v-spacer></v-spacer>
          <v-btn
            color="warning"
            variant="flat"
            @click="forceCreatePayroll"
            :loading="saving"
          >
            <v-icon size="18" class="mr-1">mdi-alert</v-icon>
            Create Anyway
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import { useRouter } from "vue-router";
import { useToast } from "vue-toastification";
import api from "@/services/api";
import { formatCurrency, formatDate } from "@/utils/formatters";

const router = useRouter();
const toast = useToast();

const loading = ref(false);
const saving = ref(false);
const deleting = ref(false);
const search = ref("");
const dialog = ref(false);
const deleteDialog = ref(false);
const validationWarningDialog = ref(false);
const incompleteRecords = ref([]);
const editMode = ref(false);
const valid = ref(false);
const form = ref(null);

const payrolls = ref([]);
const selectedPayroll = ref(null);

const formData = ref({
  period_name: "",
  period_start: "",
  period_end: "",
  payment_date: "",
  notes: "",
  has_attendance: false,
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

onMounted(() => {
  fetchPayrolls();
});

async function fetchPayrolls() {
  loading.value = true;
  try {
    const response = await api.get("/payrolls");
    payrolls.value = response.data.data || response.data;
  } catch (error) {
    toast.error("Failed to load payrolls");
  } finally {
    loading.value = false;
  }
}

function openCreateDialog() {
  editMode.value = false;
  formData.value = {
    period_name: "",
    period_start: "",
    period_end: "",
    payment_date: "",
    notes: "",
    has_attendance: false,
  };
  dialog.value = true;
}

function editPayroll(item) {
  editMode.value = true;
  selectedPayroll.value = item;
  formData.value = {
    period_name: item.period_name,
    period_start: item.period_start,
    period_end: item.period_end,
    payment_date: item.payment_date,
    notes: item.notes || "",
  };
  dialog.value = true;
}

function closeDialog() {
  dialog.value = false;
  selectedPayroll.value = null;
}

async function savePayroll(forceCreate = false) {
  const { valid } = await form.value.validate();
  if (!valid) return;

  // Prepare payload - simplified for all employees only
  const payload = {
    period_name: formData.value.period_name,
    period_start: formData.value.period_start,
    period_end: formData.value.period_end,
    payment_date: formData.value.payment_date,
    notes: formData.value.notes,
  };

  // Add attendance filter if enabled
  if (formData.value.has_attendance) {
    payload.has_attendance = formData.value.has_attendance;
  }

  // Add force_create flag if bypassing validation
  if (forceCreate) {
    payload.force_create = true;
  }

  // Step 1: Validate attendance completeness (unless forcing OR editing existing payroll)
  if (!forceCreate && !editMode.value) {
    saving.value = true;
    try {
      await api.post("/payrolls/validate", {
        period_start: formData.value.period_start,
        period_end: formData.value.period_end,
        has_attendance: formData.value.has_attendance || false,
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
  router.push(`/payroll/${item.id}`);
}

function confirmDelete(item) {
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

async function forceCreatePayroll() {
  validationWarningDialog.value = false;
  await savePayroll(true); // Call with force flag
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
</script>

<style scoped lang="scss">
.payroll-page {
  max-width: 1600px;
  margin: 0 auto;
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
  padding: 16px 20px;
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
  width: 36px;
  height: 36px;
  border-radius: 10px;
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
  font-size: 16px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
  letter-spacing: -0.3px;
}
</style>
