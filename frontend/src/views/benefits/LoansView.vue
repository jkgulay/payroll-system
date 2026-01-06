<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-6">
      <h1 class="text-h4 font-weight-bold">Employee Loans</h1>
      <v-btn
        color="primary"
        prepend-icon="mdi-plus"
        @click="openAddDialog"
        v-if="userRole !== 'employee'"
      >
        Add Loan
      </v-btn>
    </div>

    <!-- Pending Approval Alert (Admin Only) -->
    <v-alert
      v-if="userRole === 'admin' && pendingCount > 0"
      type="info"
      variant="tonal"
      class="mb-4"
      closable
    >
      <template v-slot:title>
        {{ pendingCount }} Loan{{ pendingCount > 1 ? "s" : "" }} Pending
        Approval
      </template>
      <v-btn color="primary" size="small" class="mt-2" @click="showPendingOnly">
        View Pending Loans
      </v-btn>
    </v-alert>

    <!-- Filters -->
    <v-card class="mb-4">
      <v-card-text>
        <v-row>
          <v-col cols="12" md="3" v-if="userRole !== 'employee'">
            <v-autocomplete
              v-model="filters.employee_id"
              :items="employees"
              item-title="full_name"
              item-value="id"
              label="Filter by Employee"
              variant="outlined"
              density="compact"
              clearable
              @update:model-value="fetchLoans"
            ></v-autocomplete>
          </v-col>
          <v-col cols="12" :md="userRole === 'employee' ? 4 : 3">
            <v-select
              v-model="filters.loan_type"
              :items="loanTypes"
              label="Filter by Type"
              variant="outlined"
              density="compact"
              clearable
              @update:model-value="fetchLoans"
            ></v-select>
          </v-col>
          <v-col cols="12" :md="userRole === 'employee' ? 4 : 3">
            <v-select
              v-model="filters.status"
              :items="statusOptions"
              label="Filter by Status"
              variant="outlined"
              density="compact"
              clearable
              @update:model-value="fetchLoans"
            ></v-select>
          </v-col>
          <v-col cols="12" :md="userRole === 'employee' ? 4 : 3">
            <v-btn
              color="secondary"
              variant="outlined"
              block
              @click="clearFilters"
            >
              Clear Filters
            </v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Loans Table -->
    <v-card>
      <v-data-table
        :headers="headers"
        :items="loans"
        :loading="loading"
        :items-per-page="15"
      >
        <template v-slot:item.employee="{ item }">
          <div>
            <div class="font-weight-medium">{{ item.employee?.full_name }}</div>
            <div class="text-caption text-medium-emphasis">
              {{ item.employee?.employee_number }}
            </div>
          </div>
        </template>

        <template v-slot:item.loan_number="{ item }">
          <span class="font-weight-medium">{{ item.loan_number }}</span>
        </template>

        <template v-slot:item.loan_type="{ item }">
          <v-chip
            :color="getLoanTypeColor(item.loan_type)"
            size="small"
            variant="tonal"
          >
            {{ formatLoanType(item.loan_type) }}
          </v-chip>
        </template>

        <template v-slot:item.principal_amount="{ item }">
          <span class="font-weight-medium"
            >₱{{ formatNumber(item.principal_amount) }}</span
          >
        </template>

        <template v-slot:item.total_amount="{ item }">
          <span class="font-weight-medium"
            >₱{{ formatNumber(item.total_amount) }}</span
          >
        </template>

        <template v-slot:item.balance="{ item }">
          <span
            :class="
              item.balance > 0 ? 'text-error font-weight-bold' : 'text-success'
            "
          >
            ₱{{ formatNumber(item.balance) }}
          </span>
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
          <!-- Admin Actions -->
          <template v-if="userRole === 'admin' && item.status === 'pending'">
            <v-btn
              icon="mdi-check"
              size="small"
              variant="text"
              color="success"
              @click="openApproveDialog(item)"
            ></v-btn>
            <v-btn
              icon="mdi-close"
              size="small"
              variant="text"
              color="error"
              @click="openRejectDialog(item)"
            ></v-btn>
          </template>

          <!-- Accountant/Admin Actions -->
          <v-btn
            v-if="
              userRole !== 'employee' &&
              ['pending', 'approved'].includes(item.status)
            "
            icon="mdi-pencil"
            size="small"
            variant="text"
            @click="openEditDialog(item)"
          ></v-btn>
          <v-btn
            v-if="
              userRole !== 'employee' &&
              ['pending', 'rejected'].includes(item.status)
            "
            icon="mdi-delete"
            size="small"
            variant="text"
            color="error"
            @click="confirmDelete(item)"
          ></v-btn>

          <!-- View Details -->
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
            <v-icon size="64" color="grey">mdi-hand-coin-outline</v-icon>
            <p class="text-h6 mt-4">No loans found</p>
            <p class="text-body-2 text-medium-emphasis">
              {{
                userRole === "employee"
                  ? "Request a loan to get started"
                  : "Add a loan to get started"
              }}
            </p>
          </div>
        </template>
      </v-data-table>
    </v-card>

    <!-- Add/Edit Dialog -->
    <v-dialog v-model="dialog" max-width="800px" persistent>
      <v-card>
        <v-card-title class="text-h5">
          {{
            editMode
              ? "Edit Loan"
              : isEmployeeRequest
              ? "Request Loan"
              : "Add Loan"
          }}
        </v-card-title>

        <v-card-text>
          <v-form ref="form" v-model="formValid">
            <v-row>
              <v-col cols="12" v-if="!isEmployeeRequest">
                <v-autocomplete
                  v-model="formData.employee_id"
                  :items="employees"
                  item-title="full_name"
                  item-value="id"
                  label="Employee *"
                  variant="outlined"
                  :rules="[rules.required]"
                  :disabled="editMode"
                >
                  <template v-slot:item="{ props, item }">
                    <v-list-item v-bind="props">
                      <template v-slot:title>
                        {{ item.raw.full_name }}
                      </template>
                      <template v-slot:subtitle>
                        {{ item.raw.employee_number }} - {{ item.raw.position }}
                      </template>
                    </v-list-item>
                  </template>
                </v-autocomplete>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="formData.loan_type"
                  :items="loanTypes"
                  label="Loan Type *"
                  variant="outlined"
                  :rules="[rules.required]"
                  @update:model-value="onLoanTypeChange"
                ></v-select>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model.number="formData.principal_amount"
                  label="Principal Amount *"
                  type="number"
                  prefix="₱"
                  variant="outlined"
                  :rules="[rules.required, rules.positive]"
                  @update:model-value="calculateLoan"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model.number="formData.interest_rate"
                  label="Interest Rate (%) *"
                  type="number"
                  suffix="%"
                  variant="outlined"
                  :rules="[rules.required, rules.percentage]"
                  @update:model-value="calculateLoan"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model.number="formData.loan_term_months"
                  label="Loan Term (Months) *"
                  type="number"
                  variant="outlined"
                  :rules="[rules.required, rules.positive]"
                  @update:model-value="calculateLoan"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="formData.payment_frequency"
                  :items="frequencyOptions"
                  label="Payment Frequency *"
                  variant="outlined"
                  :rules="[rules.required]"
                  @update:model-value="calculateLoan"
                ></v-select>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="formData.loan_date"
                  label="Loan Date *"
                  type="date"
                  variant="outlined"
                  :rules="[rules.required]"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="formData.first_payment_date"
                  label="First Payment Date *"
                  type="date"
                  variant="outlined"
                  :rules="[rules.required]"
                ></v-text-field>
              </v-col>

              <!-- Calculated Fields -->
              <v-col cols="12">
                <v-alert type="info" variant="tonal">
                  <div class="text-subtitle-2 mb-2">Loan Summary</div>
                  <div class="d-flex justify-space-between mb-1">
                    <span>Principal:</span>
                    <span class="font-weight-bold"
                      >₱{{ formatNumber(formData.principal_amount || 0) }}</span
                    >
                  </div>
                  <div class="d-flex justify-space-between mb-1">
                    <span>Interest ({{ formData.interest_rate || 0 }}%):</span>
                    <span class="font-weight-bold"
                      >₱{{ formatNumber(calculatedInterest) }}</span
                    >
                  </div>
                  <v-divider class="my-2"></v-divider>
                  <div class="d-flex justify-space-between mb-2">
                    <span>Total Amount:</span>
                    <span class="font-weight-bold text-primary"
                      >₱{{ formatNumber(calculatedTotal) }}</span
                    >
                  </div>
                  <div class="d-flex justify-space-between">
                    <span
                      >{{
                        formData.payment_frequency === "semi_monthly"
                          ? "Semi-Monthly"
                          : "Monthly"
                      }}
                      Payment:</span
                    >
                    <span class="font-weight-bold text-error"
                      >₱{{ formatNumber(calculatedAmortization) }}</span
                    >
                  </div>
                </v-alert>
              </v-col>

              <v-col cols="12">
                <v-textarea
                  v-model="formData.purpose"
                  label="Purpose *"
                  variant="outlined"
                  rows="2"
                  :rules="[rules.required]"
                ></v-textarea>
              </v-col>

              <v-col cols="12">
                <v-textarea
                  v-model="formData.notes"
                  label="Notes (Optional)"
                  variant="outlined"
                  rows="2"
                ></v-textarea>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="closeDialog">Cancel</v-btn>
          <v-btn
            color="primary"
            variant="flat"
            :loading="saving"
            :disabled="!formValid"
            @click="saveLoan"
          >
            {{
              editMode
                ? "Update"
                : isEmployeeRequest
                ? "Submit Request"
                : "Add Loan"
            }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Approve Dialog -->
    <v-dialog v-model="approveDialog" max-width="500px" persistent>
      <v-card>
        <v-card-title class="text-h5">Approve Loan</v-card-title>
        <v-card-text>
          <p class="mb-4">
            Approve loan request for
            <strong>{{ selectedLoan?.employee?.full_name }}</strong
            >?
          </p>
          <v-textarea
            v-model="approvalNotes"
            label="Approval Notes (Optional)"
            variant="outlined"
            rows="3"
          ></v-textarea>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="approveDialog = false">Cancel</v-btn>
          <v-btn
            color="success"
            variant="flat"
            :loading="saving"
            @click="approveLoan"
          >
            Approve
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Reject Dialog -->
    <v-dialog v-model="rejectDialog" max-width="500px" persistent>
      <v-card>
        <v-card-title class="text-h5">Reject Loan</v-card-title>
        <v-card-text>
          <p class="mb-4">
            Reject loan request for
            <strong>{{ selectedLoan?.employee?.full_name }}</strong
            >?
          </p>
          <v-textarea
            v-model="rejectionReason"
            label="Rejection Reason *"
            variant="outlined"
            rows="3"
            :rules="[rules.required]"
          ></v-textarea>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="rejectDialog = false">Cancel</v-btn>
          <v-btn
            color="error"
            variant="flat"
            :loading="saving"
            :disabled="!rejectionReason"
            @click="rejectLoan"
          >
            Reject
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete Confirmation Dialog -->
    <v-dialog v-model="deleteDialog" max-width="400px">
      <v-card>
        <v-card-title class="text-h5">Confirm Delete</v-card-title>
        <v-card-text>
          Are you sure you want to delete this loan? This action cannot be
          undone.
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="deleteDialog = false">Cancel</v-btn>
          <v-btn
            color="error"
            variant="flat"
            :loading="deleting"
            @click="deleteLoan"
          >
            Delete
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Details Dialog -->
    <v-dialog v-model="detailsDialog" max-width="700px">
      <v-card v-if="selectedLoan">
        <v-card-title class="text-h5 d-flex align-center">
          <v-icon class="mr-2">mdi-hand-coin</v-icon>
          Loan Details
        </v-card-title>

        <v-divider></v-divider>

        <v-card-text class="pt-4">
          <v-row>
            <v-col cols="12">
              <div class="text-subtitle-1 font-weight-bold mb-2">
                Loan Information
              </div>
            </v-col>
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">Loan Number</div>
              <div class="font-weight-medium">
                {{ selectedLoan.loan_number }}
              </div>
            </v-col>
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">Employee</div>
              <div class="font-weight-medium">
                {{ selectedLoan.employee?.full_name }}
              </div>
            </v-col>
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">Loan Type</div>
              <v-chip
                :color="getLoanTypeColor(selectedLoan.loan_type)"
                size="small"
                variant="tonal"
              >
                {{ formatLoanType(selectedLoan.loan_type) }}
              </v-chip>
            </v-col>
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">Status</div>
              <v-chip
                :color="getStatusColor(selectedLoan.status)"
                size="small"
                variant="flat"
              >
                {{ formatStatus(selectedLoan.status) }}
              </v-chip>
            </v-col>

            <v-col cols="12">
              <v-divider class="my-2"></v-divider>
              <div class="text-subtitle-1 font-weight-bold mb-2">
                Financial Details
              </div>
            </v-col>
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">
                Principal Amount
              </div>
              <div class="font-weight-medium">
                ₱{{ formatNumber(selectedLoan.principal_amount) }}
              </div>
            </v-col>
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">Interest Rate</div>
              <div class="font-weight-medium">
                {{ selectedLoan.interest_rate }}%
              </div>
            </v-col>
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">Total Amount</div>
              <div class="font-weight-bold text-primary">
                ₱{{ formatNumber(selectedLoan.total_amount) }}
              </div>
            </v-col>
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">Balance</div>
              <div
                :class="
                  selectedLoan.balance > 0
                    ? 'font-weight-bold text-error'
                    : 'font-weight-bold text-success'
                "
              >
                ₱{{ formatNumber(selectedLoan.balance) }}
              </div>
            </v-col>
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">Amount Paid</div>
              <div class="font-weight-medium">
                ₱{{ formatNumber(selectedLoan.amount_paid) }}
              </div>
            </v-col>
            <v-col cols="6">
              <div class="text-caption text-medium-emphasis">
                Payment Frequency
              </div>
              <div class="font-weight-medium">
                {{ formatFrequency(selectedLoan.payment_frequency) }}
              </div>
            </v-col>

            <v-col cols="12" v-if="selectedLoan.purpose">
              <v-divider class="my-2"></v-divider>
              <div class="text-subtitle-1 font-weight-bold mb-2">Purpose</div>
              <div>{{ selectedLoan.purpose }}</div>
            </v-col>

            <v-col
              cols="12"
              v-if="
                selectedLoan.approval_notes || selectedLoan.rejection_reason
              "
            >
              <v-divider class="my-2"></v-divider>
              <v-alert
                :type="selectedLoan.status === 'approved' ? 'success' : 'error'"
                variant="tonal"
              >
                <div class="text-subtitle-2 mb-1">
                  {{
                    selectedLoan.status === "approved"
                      ? "Approval Notes"
                      : "Rejection Reason"
                  }}
                </div>
                <div>
                  {{
                    selectedLoan.approval_notes || selectedLoan.rejection_reason
                  }}
                </div>
              </v-alert>
            </v-col>
          </v-row>
        </v-card-text>

        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="detailsDialog = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useToast } from "vue-toastification";
import loanService from "@/services/loanService";
import api from "@/services/api";
import { useAuthStore } from "@/stores/auth";

const toast = useToast();
const authStore = useAuthStore();

// User role
const userRole = computed(() => authStore.user?.role);

// Data
const loans = ref([]);
const employees = ref([]);
const loading = ref(false);
const saving = ref(false);
const deleting = ref(false);
const dialog = ref(false);
const approveDialog = ref(false);
const rejectDialog = ref(false);
const deleteDialog = ref(false);
const detailsDialog = ref(false);
const editMode = ref(false);
const isEmployeeRequest = ref(false);
const formValid = ref(false);
const form = ref(null);
const selectedLoan = ref(null);
const approvalNotes = ref("");
const rejectionReason = ref("");
const pendingCount = ref(0);

// Filters
const filters = ref({
  employee_id: null,
  loan_type: null,
  status: null,
});

// Form data
const defaultFormData = {
  employee_id: null,
  loan_type: null,
  principal_amount: null,
  interest_rate: 0,
  loan_term_months: 12,
  payment_frequency: "semi_monthly",
  loan_date: new Date().toISOString().split("T")[0],
  first_payment_date: null,
  purpose: "",
  notes: "",
};

const formData = ref({ ...defaultFormData });

// Calculated fields
const calculatedInterest = computed(() => {
  const principal = formData.value.principal_amount || 0;
  const rate = formData.value.interest_rate || 0;
  return principal * (rate / 100);
});

const calculatedTotal = computed(() => {
  const principal = formData.value.principal_amount || 0;
  return principal + calculatedInterest.value;
});

const calculatedAmortization = computed(() => {
  const termMonths = formData.value.loan_term_months || 1;
  const frequency = formData.value.payment_frequency;
  const paymentsPerYear = frequency === "semi_monthly" ? 24 : 12;
  const totalPayments = (termMonths / 12) * paymentsPerYear;
  return calculatedTotal.value / totalPayments;
});

// Headers
const headers = computed(() => {
  const baseHeaders = [
    { title: "Loan Number", key: "loan_number", sortable: true },
    { title: "Loan Type", key: "loan_type", sortable: true },
    { title: "Principal", key: "principal_amount", sortable: true },
    { title: "Total Amount", key: "total_amount", sortable: true },
    { title: "Balance", key: "balance", sortable: true },
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
const loanTypes = [
  { title: "SSS Loan", value: "sss" },
  { title: "Pag-IBIG Loan", value: "pag_ibig" },
  { title: "Company Loan", value: "company" },
  { title: "Emergency Loan", value: "emergency" },
  { title: "Salary Advance", value: "salary_advance" },
  { title: "Other", value: "other" },
];

const statusOptions = [
  { title: "Pending", value: "pending" },
  { title: "Approved", value: "approved" },
  { title: "Active", value: "active" },
  { title: "Paid", value: "paid" },
  { title: "Rejected", value: "rejected" },
];

const frequencyOptions = [
  { title: "Monthly", value: "monthly" },
  { title: "Semi-Monthly", value: "semi_monthly" },
];

// Validation rules
const rules = {
  required: (value) => !!value || "This field is required",
  positive: (value) => value > 0 || "Must be greater than 0",
  percentage: (value) =>
    (value >= 0 && value <= 100) || "Must be between 0 and 100",
};

// Fetch loans
const fetchLoans = async () => {
  loading.value = true;
  try {
    const params = {};
    if (filters.value.employee_id)
      params.employee_id = filters.value.employee_id;
    if (filters.value.loan_type) params.loan_type = filters.value.loan_type;
    if (filters.value.status) params.status = filters.value.status;

    const response = await loanService.getLoans(params);
    loans.value = response.data.data || response.data;

    // Count pending loans for admin
    if (userRole.value === "admin") {
      pendingCount.value = loans.value.filter(
        (loan) => loan.status === "pending"
      ).length;
    }
  } catch (error) {
    toast.error("Failed to load loans");
    console.error(error);
  } finally {
    loading.value = false;
  }
};

// Fetch employees
const fetchEmployees = async () => {
  try {
    const response = await api.get("/employees?per_page=1000");
    employees.value = response.data.data || response.data;
  } catch (error) {
    console.error("Failed to load employees:", error);
  }
};

// Open dialogs
const openAddDialog = () => {
  editMode.value = false;
  isEmployeeRequest.value = false;
  formData.value = { ...defaultFormData };
  dialog.value = true;
};

const openRequestDialog = () => {
  editMode.value = false;
  isEmployeeRequest.value = true;
  formData.value = {
    ...defaultFormData,
    employee_id: authStore.user?.employee_id,
  };
  dialog.value = true;
};

const openEditDialog = (loan) => {
  editMode.value = true;
  isEmployeeRequest.value = false;
  selectedLoan.value = loan;
  formData.value = {
    employee_id: loan.employee_id,
    loan_type: loan.loan_type,
    principal_amount: loan.principal_amount,
    interest_rate: loan.interest_rate,
    loan_term_months: loan.loan_term_months,
    payment_frequency: loan.payment_frequency,
    loan_date: loan.loan_date,
    first_payment_date: loan.first_payment_date,
    purpose: loan.purpose,
    notes: loan.notes,
  };
  dialog.value = true;
};

const openApproveDialog = (loan) => {
  selectedLoan.value = loan;
  approvalNotes.value = "";
  approveDialog.value = true;
};

const openRejectDialog = (loan) => {
  selectedLoan.value = loan;
  rejectionReason.value = "";
  rejectDialog.value = true;
};

const closeDialog = () => {
  dialog.value = false;
  editMode.value = false;
  isEmployeeRequest.value = false;
  formData.value = { ...defaultFormData };
  if (form.value) form.value.resetValidation();
};

// Loan type change handler
const onLoanTypeChange = () => {
  const defaultRates = {
    sss: 10.0,
    pag_ibig: 5.5,
    company: 8.0,
    emergency: 5.0,
    salary_advance: 0.0,
    other: 6.0,
  };
  formData.value.interest_rate = defaultRates[formData.value.loan_type] || 0;
  calculateLoan();
};

const calculateLoan = () => {
  // Calculations are reactive via computed properties
};

// Save loan
const saveLoan = async () => {
  if (!form.value) return;

  const { valid } = await form.value.validate();
  if (!valid) return;

  saving.value = true;
  try {
    if (editMode.value) {
      await loanService.updateLoan(selectedLoan.value.id, formData.value);
      toast.success("Loan updated successfully");
    } else {
      await loanService.createLoan(formData.value);
      toast.success(
        isEmployeeRequest.value
          ? "Loan request submitted"
          : "Loan created successfully"
      );
    }
    closeDialog();
    fetchLoans();
  } catch (error) {
    toast.error(error.response?.data?.message || "Failed to save loan");
    console.error(error);
  } finally {
    saving.value = false;
  }
};

// Approve loan
const approveLoan = async () => {
  saving.value = true;
  try {
    await loanService.approveLoan(selectedLoan.value.id, approvalNotes.value);
    toast.success("Loan approved successfully");
    approveDialog.value = false;
    fetchLoans();
  } catch (error) {
    toast.error(error.response?.data?.message || "Failed to approve loan");
    console.error(error);
  } finally {
    saving.value = false;
  }
};

// Reject loan
const rejectLoan = async () => {
  if (!rejectionReason.value) return;

  saving.value = true;
  try {
    await loanService.rejectLoan(selectedLoan.value.id, rejectionReason.value);
    toast.success("Loan rejected");
    rejectDialog.value = false;
    fetchLoans();
  } catch (error) {
    toast.error(error.response?.data?.message || "Failed to reject loan");
    console.error(error);
  } finally {
    saving.value = false;
  }
};

// Delete loan
const confirmDelete = (loan) => {
  selectedLoan.value = loan;
  deleteDialog.value = true;
};

const deleteLoan = async () => {
  deleting.value = true;
  try {
    await loanService.deleteLoan(selectedLoan.value.id);
    toast.success("Loan deleted successfully");
    deleteDialog.value = false;
    fetchLoans();
  } catch (error) {
    toast.error(error.response?.data?.message || "Failed to delete loan");
    console.error(error);
  } finally {
    deleting.value = false;
  }
};

// View details
const viewDetails = (loan) => {
  selectedLoan.value = loan;
  detailsDialog.value = true;
};

// Show pending only
const showPendingOnly = () => {
  filters.value.status = "pending";
  fetchLoans();
};

// Clear filters
const clearFilters = () => {
  filters.value = {
    employee_id: null,
    loan_type: null,
    status: null,
  };
  fetchLoans();
};

// Formatters
const formatNumber = (value) => {
  if (!value) return "0.00";
  return Number(value).toLocaleString("en-PH", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

const formatLoanType = (type) => {
  const types = {
    sss: "SSS Loan",
    pag_ibig: "Pag-IBIG Loan",
    company: "Company Loan",
    emergency: "Emergency Loan",
    salary_advance: "Salary Advance",
    other: "Other",
  };
  return types[type] || type;
};

const formatStatus = (status) => {
  return status.charAt(0).toUpperCase() + status.slice(1);
};

const formatFrequency = (frequency) => {
  return frequency === "semi_monthly" ? "Semi-Monthly" : "Monthly";
};

const getLoanTypeColor = (type) => {
  const colors = {
    sss: "blue",
    pag_ibig: "green",
    company: "purple",
    emergency: "orange",
    salary_advance: "cyan",
    other: "grey",
  };
  return colors[type] || "grey";
};

const getStatusColor = (status) => {
  const colors = {
    pending: "warning",
    approved: "info",
    active: "success",
    paid: "success",
    rejected: "error",
  };
  return colors[status] || "grey";
};

// Lifecycle
onMounted(() => {
  fetchLoans();
  if (userRole.value !== "employee") {
    fetchEmployees();
  }
});
</script>
