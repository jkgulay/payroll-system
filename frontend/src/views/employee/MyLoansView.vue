<template>
  <div class="my-loans-page">
    <!-- Modern Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="page-title-section">
          <div class="page-icon-badge">
            <v-icon size="20">mdi-hand-coin</v-icon>
          </div>
          <div>
            <h1 class="page-title">My Loans</h1>
            <p class="page-subtitle">View and manage your loan requests</p>
          </div>
        </div>
        <div class="action-buttons">
          <button
            class="action-btn action-btn-primary"
            @click="openRequestDialog"
          >
            <v-icon size="20">mdi-plus</v-icon>
            <span>Request Loan</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon total">
          <v-icon size="20">mdi-hand-coin</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Total Loans</div>
          <div class="stat-value">{{ loans.length }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon pending">
          <v-icon size="20">mdi-clock-alert</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Pending</div>
          <div class="stat-value">{{ pendingCount }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon active">
          <v-icon size="20">mdi-check-circle</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Active</div>
          <div class="stat-value">{{ activeCount }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon balance">
          <v-icon size="20">mdi-cash-multiple</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Balance Remaining</div>
          <div class="stat-value">
            ₱{{ formatNumber(totalBalanceRemaining) }}
          </div>
        </div>
      </div>
    </div>

    <div class="modern-card">
      <!-- Filters -->
      <div class="filters-section">
        <div class="filter-group">
          <v-select
            v-model="filters.loan_type"
            :items="loanTypes"
            label="Loan Type"
            clearable
            variant="outlined"
            density="compact"
            class="filter-field"
            @update:model-value="fetchMyLoans"
          ></v-select>
          <v-select
            v-model="filters.status"
            :items="statusOptions"
            label="Status"
            clearable
            variant="outlined"
            density="compact"
            class="filter-field"
            @update:model-value="fetchMyLoans"
          ></v-select>
        </div>
      </div>

      <!-- Loans Table -->
      <v-data-table
        :headers="headers"
        :items="loans"
        :loading="loading"
        :items-per-page="15"
        class="modern-table"
      >
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
          <v-progress-linear
            class="mt-2"
            height="6"
            :model-value="getLoanProgress(item)"
            color="primary"
            rounded
          ></v-progress-linear>
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
            icon="mdi-eye"
            size="small"
            variant="text"
            @click="viewLoanDetails(item)"
          ></v-btn>
          <v-btn
            v-if="item.status === 'pending'"
            icon="mdi-close"
            size="small"
            variant="text"
            color="error"
            @click="confirmCancel(item)"
          ></v-btn>
        </template>

        <template v-slot:no-data>
          <div class="text-center py-8">
            <v-icon size="64" color="grey-lighten-1">mdi-hand-coin</v-icon>
            <p class="mt-4 text-grey">No loans found</p>
          </div>
        </template>
      </v-data-table>
    </div>

    <!-- Request Loan Dialog -->
    <v-dialog v-model="requestDialog" max-width="800px" persistent scrollable>
      <v-card class="modern-dialog">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper primary">
            <v-icon size="24">mdi-hand-coin</v-icon>
          </div>
          <div>
            <div class="dialog-title">Request Loan</div>
            <div class="dialog-subtitle">Submit loan request for approval</div>
          </div>
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="dialog-content" style="max-height: 70vh">
          <v-form ref="requestFormRef" v-model="formValid">
            <v-row>
              <v-col cols="12">
                <div class="section-header">
                  <div class="section-icon">
                    <v-icon size="18">mdi-cash</v-icon>
                  </div>
                  <h3 class="section-title">Loan Information</h3>
                </div>
              </v-col>

              <v-col cols="12" md="4">
                <v-select
                  v-model="requestForm.loan_type"
                  :items="loanTypes"
                  label="Loan Type"
                  placeholder="Select loan type"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-tag"
                  :rules="[rules.required]"
                  @update:model-value="onLoanTypeChange"
                ></v-select>
              </v-col>

              <v-col cols="12" md="4">
                <v-text-field
                  v-model.number="requestForm.principal_amount"
                  label="Principal Amount"
                  type="number"
                  prefix="₱"
                  placeholder="0.00"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-currency-php"
                  :rules="[rules.required, rules.positive]"
                  @update:model-value="calculateLoan"
                ></v-text-field>
              </v-col>

              <v-col cols="12" class="mt-2">
                <div class="section-header">
                  <div class="section-icon">
                    <v-icon size="18">mdi-calendar-clock</v-icon>
                  </div>
                  <h3 class="section-title">Payment Schedule</h3>
                </div>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="requestForm.loan_date"
                  label="Loan Date"
                  type="date"
                  placeholder="Select loan date"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-calendar"
                  :rules="[rules.required]"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="requestForm.first_payment_date"
                  label="First Payment Date"
                  type="date"
                  placeholder="Select first payment date"
                  variant="outlined"
                  density="comfortable"
                  prepend-inner-icon="mdi-calendar-start"
                  :rules="[rules.required]"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-textarea
                  v-model="requestForm.purpose"
                  label="Purpose"
                  placeholder="Enter loan purpose"
                  prepend-inner-icon="mdi-text"
                  variant="outlined"
                  rows="2"
                  density="comfortable"
                  :rules="[rules.required]"
                ></v-textarea>
              </v-col>

              <v-col cols="12" md="6">
                <v-textarea
                  v-model="requestForm.notes"
                  label="Notes (Optional)"
                  placeholder="Additional notes"
                  prepend-inner-icon="mdi-note-text"
                  variant="outlined"
                  rows="2"
                  density="comfortable"
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
            @click="requestDialog = false"
          >
            Cancel
          </button>
          <button
            class="dialog-btn dialog-btn-primary"
            :disabled="!formValid || submitting"
            @click="submitRequest"
          >
            <v-icon size="20" class="mr-1">mdi-send</v-icon>
            Submit Request
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Loan Details Dialog -->
    <v-dialog v-model="detailsDialog" max-width="600">
      <v-card class="modern-dialog" v-if="selectedLoan">
        <v-card-title class="dialog-header">
          <v-icon class="mr-2">mdi-file-document</v-icon>
          Loan Details - {{ selectedLoan.loan_number }}
        </v-card-title>
        <v-card-text>
          <v-list density="compact">
            <v-list-item>
              <template v-slot:prepend>
                <v-icon>mdi-tag</v-icon>
              </template>
              <v-list-item-title>Loan Type</v-list-item-title>
              <v-list-item-subtitle>{{
                formatLoanType(selectedLoan.loan_type)
              }}</v-list-item-subtitle>
            </v-list-item>
            <v-list-item>
              <template v-slot:prepend>
                <v-icon>mdi-cash</v-icon>
              </template>
              <v-list-item-title>Principal Amount</v-list-item-title>
              <v-list-item-subtitle
                >₱{{
                  formatNumber(selectedLoan.principal_amount)
                }}</v-list-item-subtitle
              >
            </v-list-item>
            <v-list-item>
              <template v-slot:prepend>
                <v-icon>mdi-percent</v-icon>
              </template>
              <v-list-item-title>Interest Rate</v-list-item-title>
              <v-list-item-subtitle
                >{{ selectedLoan.interest_rate }}%</v-list-item-subtitle
              >
            </v-list-item>
            <v-list-item>
              <template v-slot:prepend>
                <v-icon>mdi-calculator</v-icon>
              </template>
              <v-list-item-title>Total Amount</v-list-item-title>
              <v-list-item-subtitle
                >₱{{
                  formatNumber(selectedLoan.total_amount)
                }}</v-list-item-subtitle
              >
            </v-list-item>
            <v-list-item>
              <template v-slot:prepend>
                <v-icon>mdi-calendar-range</v-icon>
              </template>
              <v-list-item-title>Terms</v-list-item-title>
              <v-list-item-subtitle
                >{{ selectedLoan.terms }} months</v-list-item-subtitle
              >
            </v-list-item>
            <v-list-item>
              <template v-slot:prepend>
                <v-icon>mdi-cash-clock</v-icon>
              </template>
              <v-list-item-title>Monthly Deduction</v-list-item-title>
              <v-list-item-subtitle
                >₱{{
                  formatNumber(selectedLoan.monthly_deduction)
                }}</v-list-item-subtitle
              >
            </v-list-item>
            <v-list-item>
              <template v-slot:prepend>
                <v-icon>mdi-scale-balance</v-icon>
              </template>
              <v-list-item-title>Remaining Balance</v-list-item-title>
              <v-list-item-subtitle
                >₱{{ formatNumber(selectedLoan.balance) }}</v-list-item-subtitle
              >
            </v-list-item>
            <v-list-item>
              <template v-slot:prepend>
                <v-icon>mdi-flag</v-icon>
              </template>
              <v-list-item-title>Status</v-list-item-title>
              <v-list-item-subtitle>
                <v-chip
                  :color="getStatusColor(selectedLoan.status)"
                  size="small"
                >
                  {{ formatStatus(selectedLoan.status) }}
                </v-chip>
              </v-list-item-subtitle>
            </v-list-item>
            <v-list-item v-if="selectedLoan.reason">
              <template v-slot:prepend>
                <v-icon>mdi-text</v-icon>
              </template>
              <v-list-item-title>Reason</v-list-item-title>
              <v-list-item-subtitle>{{
                selectedLoan.reason
              }}</v-list-item-subtitle>
            </v-list-item>
            <v-list-item
              v-if="
                selectedLoan.status === 'rejected' &&
                selectedLoan.rejection_reason
              "
            >
              <template v-slot:prepend>
                <v-icon color="error">mdi-alert-circle</v-icon>
              </template>
              <v-list-item-title>Rejection Reason</v-list-item-title>
              <v-list-item-subtitle class="text-error">{{
                selectedLoan.rejection_reason
              }}</v-list-item-subtitle>
            </v-list-item>
          </v-list>
        </v-card-text>
        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="detailsDialog = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Cancel Confirmation Dialog -->
    <v-dialog v-model="cancelDialog" max-width="400">
      <v-card class="modern-dialog">
        <v-card-title class="dialog-header text-error">
          <v-icon class="mr-2" color="error">mdi-alert</v-icon>
          Cancel Loan Request
        </v-card-title>
        <v-card-text>
          Are you sure you want to cancel this loan request? This action cannot
          be undone.
        </v-card-text>
        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="cancelDialog = false"
            >No, Keep It</v-btn
          >
          <v-btn
            color="error"
            variant="flat"
            :loading="cancelling"
            @click="cancelLoan"
          >
            Yes, Cancel
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useToast } from "vue-toastification";
import loanService from "@/services/loanService";
import { useAuthStore } from "@/stores/auth";

const toast = useToast();
const authStore = useAuthStore();

// State
const loans = ref([]);
const loading = ref(false);
const requestDialog = ref(false);
const detailsDialog = ref(false);
const cancelDialog = ref(false);
const submitting = ref(false);
const cancelling = ref(false);
const selectedLoan = ref(null);
const requestFormRef = ref(null);
const formValid = ref(false);

const filters = ref({
  loan_type: null,
  status: null,
});

const requestForm = ref({
  loan_type: null,
  principal_amount: null,
  interest_rate: 0,
  loan_term_months: 12,
  payment_frequency: "semi_monthly",
  loan_date: new Date().toISOString().split("T")[0],
  first_payment_date: null,
  purpose: "",
  notes: "",
});

// Table headers
const headers = [
  { title: "Loan #", key: "loan_number", sortable: true },
  { title: "Type", key: "loan_type", sortable: true },
  { title: "Principal", key: "principal_amount", sortable: true },
  { title: "Total", key: "total_amount", sortable: true },
  { title: "Balance", key: "balance", sortable: true },
  { title: "Status", key: "status", sortable: true },
  { title: "Actions", key: "actions", sortable: false, align: "center" },
];

const loanTypes = [
  { title: "Company Loan", value: "company" },
  { title: "Salary Advance", value: "salary_advance" },
  { title: "Emergency Loan", value: "emergency" },
  { title: "Other", value: "other" },
];

const statusOptions = [
  { title: "Pending", value: "pending" },
  { title: "Approved", value: "approved" },
  { title: "Rejected", value: "rejected" },
  { title: "Paid", value: "paid" },
];

// Computed
const pendingCount = computed(
  () => loans.value.filter((loan) => loan.status === "pending").length,
);

const activeCount = computed(
  () => loans.value.filter((loan) => loan.status === "active").length,
);

const totalBalanceRemaining = computed(() =>
  loans.value
    .filter((loan) => ["approved", "active"].includes(loan.status))
    .reduce((sum, loan) => sum + parseFloat(loan.balance || 0), 0),
);

const getLoanProgress = (loan) => {
  const total = parseFloat(loan.total_amount) || 0;
  const balance = parseFloat(loan.balance) || 0;
  if (total <= 0) return 0;
  const paid = Math.max(0, total - balance);
  return Math.min(100, Math.round((paid / total) * 100));
};

const onLoanTypeChange = () => {
  const defaultRates = {
    company: 8.0,
    emergency: 5.0,
    salary_advance: 0.0,
    other: 6.0,
  };
  requestForm.value.interest_rate =
    defaultRates[requestForm.value.loan_type] || 0;
};

// Validation rules
const rules = {
  required: (value) => !!value || "This field is required",
  positive: (value) => value > 0 || "Must be greater than 0",
  minAmount: (value) => value >= 1000 || "Minimum amount is ₱1,000",
};

// Fetch my loans
const fetchMyLoans = async () => {
  loading.value = true;
  try {
    const params = {
      employee_id: authStore.user?.employee_id,
    };
    if (filters.value.loan_type) params.loan_type = filters.value.loan_type;
    if (filters.value.status) params.status = filters.value.status;

    const response = await loanService.getLoans(params);
    loans.value = response.data.data || response.data;
  } catch (error) {
    toast.error("Failed to load loans");
    console.error(error);
  } finally {
    loading.value = false;
  }
};

// Open request dialog
const openRequestDialog = () => {
  const today = new Date();
  const nextMonth = new Date(today);
  nextMonth.setMonth(nextMonth.getMonth() + 1);
  const loanDate = today.toISOString().split("T")[0];
  const firstPaymentDate = nextMonth.toISOString().split("T")[0];

  requestForm.value = {
    loan_type: null,
    principal_amount: null,
    interest_rate: 0,
    loan_term_months: 12,
    payment_frequency: "semi_monthly",
    loan_date: loanDate,
    first_payment_date: firstPaymentDate,
    purpose: "",
    notes: "",
  };
  requestDialog.value = true;
};

// Submit loan request
const submitRequest = async () => {
  const { valid } = await requestFormRef.value.validate();
  if (!valid) return;

  submitting.value = true;
  try {
    await loanService.createLoan({
      employee_id: authStore.user?.employee_id,
      loan_type: requestForm.value.loan_type,
      principal_amount: requestForm.value.principal_amount,
      interest_rate: requestForm.value.interest_rate,
      loan_term_months: requestForm.value.loan_term_months,
      payment_frequency: requestForm.value.payment_frequency,
      loan_date: requestForm.value.loan_date,
      first_payment_date: requestForm.value.first_payment_date,
      purpose: requestForm.value.purpose,
      notes: requestForm.value.notes,
    });
    toast.success("Loan request submitted successfully");
    requestDialog.value = false;
    fetchMyLoans();
  } catch (error) {
    toast.error(error.response?.data?.message || "Failed to submit request");
    console.error(error);
  } finally {
    submitting.value = false;
  }
};

// View loan details
const viewLoanDetails = (loan) => {
  selectedLoan.value = loan;
  detailsDialog.value = true;
};

// Confirm cancel
const confirmCancel = (loan) => {
  selectedLoan.value = loan;
  cancelDialog.value = true;
};

// Cancel loan
const cancelLoan = async () => {
  cancelling.value = true;
  try {
    await loanService.deleteLoan(selectedLoan.value.id);
    toast.success("Loan request cancelled");
    cancelDialog.value = false;
    fetchMyLoans();
  } catch (error) {
    toast.error(error.response?.data?.message || "Failed to cancel loan");
    console.error(error);
  } finally {
    cancelling.value = false;
  }
};

// Helpers
const formatNumber = (num) => {
  if (num === null || num === undefined) return "0.00";
  return parseFloat(num).toLocaleString("en-PH", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
};

const formatLoanType = (type) => {
  const types = {
    sss: "SSS Loan",
    pag_ibig: "Pag-IBIG Loan",
    company: "Company Loan",
    salary_advance: "Salary Advance",
    emergency: "Emergency Loan",
    other: "Other",
  };
  return types[type] || type;
};

const formatStatus = (status) => {
  return status.charAt(0).toUpperCase() + status.slice(1);
};

const getLoanTypeColor = (type) => {
  const colors = {
    sss: "blue",
    pag_ibig: "green",
    company: "purple",
    salary_advance: "orange",
    emergency: "red",
    other: "grey",
  };
  return colors[type] || "grey";
};

const getStatusColor = (status) => {
  const colors = {
    pending: "warning",
    approved: "success",
    rejected: "error",
    paid: "info",
  };
  return colors[status] || "grey";
};

// Lifecycle
onMounted(() => {
  fetchMyLoans();
});
</script>

<style scoped lang="scss">
.my-loans-page {
  max-width: 1600px;
  margin: 0 auto;
}

// Modern Page Header
.page-header {
  margin-bottom: 28px;
}

.header-content {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 24px;
  flex-wrap: wrap;

  @media (max-width: 960px) {
    flex-direction: column;
    align-items: flex-start;
  }
}

.page-title-section {
  display: flex;
  align-items: center;
  gap: 16px;
  flex: 1;
}

.page-icon-badge {
  width: 56px;
  height: 56px;
  border-radius: 14px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);
  flex-shrink: 0;

  .v-icon {
    color: #ffffff !important;
  }
}

.page-title {
  font-size: 28px;
  font-weight: 700;
  color: #001f3d;
  margin: 0 0 4px 0;
  letter-spacing: -0.5px;
}

.page-subtitle {
  font-size: 14px;
  color: rgba(0, 31, 61, 0.6);
  margin: 0;
}

// Action Buttons
.action-buttons {
  display: flex;
  gap: 10px;
  flex-wrap: wrap;

  @media (max-width: 960px) {
    width: 100%;
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

// Stats Grid
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 16px;
  margin-bottom: 20px;
}

.stat-card {
  background: white;
  border-radius: 16px;
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  border: 1px solid rgba(0, 31, 61, 0.08);
  transition: all 0.2s ease;

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  }
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;

  &.total {
    background: rgba(237, 152, 95, 0.1);
    .v-icon {
      color: #ed985f !important;
    }
  }

  &.pending {
    background: rgba(245, 158, 11, 0.1);
    .v-icon {
      color: #f59e0b !important;
    }
  }

  &.active {
    background: rgba(16, 185, 129, 0.1);
    .v-icon {
      color: #10b981 !important;
    }
  }

  &.balance {
    background: rgba(59, 130, 246, 0.1);
    .v-icon {
      color: #3b82f6 !important;
    }
  }
}

.stat-label {
  font-size: 14px;
  color: #64748b;
}

.stat-value {
  font-size: 1.5rem;
  font-weight: 700;
  color: #001f3d;
}

// Main Content Card
.modern-card {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  overflow: hidden;
  padding: 24px;
}

// Filters Section
.filters-section {
  display: flex;
  justify-content: space-between;
  align-items: center;
  gap: 16px;
  padding-bottom: 20px;
  margin-bottom: 20px;
  border-bottom: 1px solid rgba(0, 31, 61, 0.08);
  flex-wrap: wrap;

  @media (max-width: 768px) {
    flex-direction: column;
    align-items: stretch;
  }
}

.filter-group {
  display: flex;
  gap: 16px;
  align-items: center;
  flex-wrap: wrap;
}

.filter-field {
  min-width: 200px;
}

.modern-table {
  border-radius: 12px;
  overflow: hidden;
}

.modern-dialog {
  border-radius: 16px !important;
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
  flex-shrink: 0;
}

.dialog-icon-wrapper.primary {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);
  color: white;
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

.loan-summary-alert {
  border-radius: 12px !important;
  padding: 16px !important;

  :deep(.v-alert__content) {
    padding: 0 !important;
  }
}
</style>
