<template>
  <div class="thirteenth-month-page">
    <div class="modern-card">
      <!-- Modern Page Header -->
      <div class="page-header">
        <div class="page-icon-badge">
          <v-icon icon="mdi-gift" size="24" color="white"></v-icon>
        </div>
        <div class="page-header-content">
          <h1 class="page-title">13th Month Pay Management</h1>
          <p class="page-subtitle">
            Calculate and manage employee 13th month pay distribution
          </p>
        </div>
        <button
          class="action-btn action-btn-primary"
          @click="openCalculateDialog"
        >
          <v-icon size="20">mdi-calculator</v-icon>
          <span>Calculate 13th Month Pay</span>
        </button>
      </div>

      <!-- Filters Section -->
      <div class="filters-section">
        <v-row>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.year"
              :items="yearOptions"
              label="Year"
              variant="outlined"
              density="comfortable"
              clearable
              hide-details
              @update:model-value="fetchThirteenthMonth"
            ></v-select>
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.status"
              :items="statusOptions"
              label="Status"
              variant="outlined"
              density="comfortable"
              clearable
              hide-details
              @update:model-value="fetchThirteenthMonth"
            ></v-select>
          </v-col>
        </v-row>
      </div>

      <!-- Data Table -->
      <v-data-table
        :headers="headers"
        :items="thirteenthMonthList"
        :loading="loading"
        class="modern-table"
      >
        <template #[`item.batch_number`]="{ item }">
          <div>
            <strong>{{ item.batch_number }}</strong>
            <div class="text-caption text-grey">
              {{ formatDate(item.computation_date) }}
            </div>
          </div>
        </template>

        <template #[`item.year_period`]="{ item }">
          <div>
            <strong>{{ item.year }}</strong>
            <div class="text-caption">{{ formatPeriod(item.period) }}</div>
          </div>
        </template>

        <template #[`item.department`]="{ item }">
          {{ item.department || "All Departments" }}
        </template>

        <template #[`item.employee_count`]="{ item }">
          <v-chip size="small" color="info">
            {{ item.items_count || 0 }} employees
          </v-chip>
        </template>

        <template #[`item.total_amount`]="{ item }">
          <strong>₱{{ formatNumber(item.total_amount) }}</strong>
        </template>

        <template #[`item.payment_date`]="{ item }">
          {{ formatDate(item.payment_date) }}
        </template>

        <template #[`item.status`]="{ item }">
          <v-chip :color="getStatusColor(item.status)" size="small">
            {{ item.status.toUpperCase() }}
          </v-chip>
        </template>

        <template #[`item.actions`]="{ item }">
          <v-tooltip text="View Details" location="top">
            <template v-slot:activator="{ props }">
              <v-btn
                v-bind="props"
                icon="mdi-eye"
                size="small"
                variant="text"
                @click="viewDetails(item)"
              ></v-btn>
            </template>
          </v-tooltip>

          <v-menu location="bottom">
            <template v-slot:activator="{ props }">
              <v-btn
                v-bind="props"
                icon="mdi-file-pdf-box"
                size="small"
                variant="text"
                color="error"
              ></v-btn>
            </template>
            <v-list density="compact">
              <v-list-item @click="downloadPdf(item)">
                <template v-slot:prepend>
                  <v-icon size="small">mdi-file-document-outline</v-icon>
                </template>
                <v-list-item-title>Simple Format</v-list-item-title>
                <v-list-item-subtitle
                  >Name, Total, Signature</v-list-item-subtitle
                >
              </v-list-item>
              <v-list-item @click="downloadPdfDetailed(item)">
                <template v-slot:prepend>
                  <v-icon size="small"
                    >mdi-file-document-multiple-outline</v-icon
                  >
                </template>
                <v-list-item-title>Detailed Format</v-list-item-title>
                <v-list-item-subtitle
                  >Rate, Days, Savings, C/A, etc.</v-list-item-subtitle
                >
              </v-list-item>
            </v-list>
          </v-menu>

          <v-tooltip text="Approve" location="top">
            <template v-slot:activator="{ props }">
              <v-btn
                v-if="item.status === 'computed'"
                v-bind="props"
                icon="mdi-check-circle"
                size="small"
                variant="text"
                color="success"
                @click="approve(item)"
              ></v-btn>
            </template>
          </v-tooltip>

          <v-tooltip text="Mark as Paid" location="top">
            <template v-slot:activator="{ props }">
              <v-btn
                v-if="item.status === 'approved'"
                v-bind="props"
                icon="mdi-cash-check"
                size="small"
                variant="text"
                color="primary"
                @click="markPaid(item)"
              ></v-btn>
            </template>
          </v-tooltip>

          <v-tooltip text="Delete" location="top">
            <template v-slot:activator="{ props }">
              <v-btn
                v-if="item.status === 'computed'"
                v-bind="props"
                icon="mdi-delete"
                size="small"
                variant="text"
                color="error"
                @click="deleteItem(item)"
              ></v-btn>
            </template>
          </v-tooltip>
        </template>
      </v-data-table>
    </div>

    <!-- Calculate Dialog - Modern UI -->
    <v-dialog v-model="calculateDialog" max-width="800px" persistent scrollable>
      <v-card class="modern-dialog">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper primary">
            <v-icon size="24">mdi-calculator-variant</v-icon>
          </div>
          <div>
            <div class="dialog-title">Calculate 13th Month Pay</div>
            <div class="dialog-subtitle">
              Generate 13th month pay for your employees
            </div>
          </div>
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="dialog-content" style="max-height: 70vh">
          <v-form ref="calculateFormRef" v-model="formValid">
            <v-row>
              <!-- Section 1: Period Details -->
              <v-col cols="12">
                <div class="section-header">
                  <div class="section-icon">
                    <v-icon size="18">mdi-calendar-range</v-icon>
                  </div>
                  <h3 class="section-title">Period Details</h3>
                </div>
              </v-col>

              <!-- Year Selection -->
              <v-col cols="12" md="6">
                <v-select
                  v-model="calculateForm.year"
                  :items="yearOptions"
                  label="Year"
                  placeholder="Select year"
                  prepend-inner-icon="mdi-calendar-clock"
                  :rules="[(v) => !!v || 'Year is required']"
                  variant="outlined"
                  density="comfortable"
                >
                  <template v-slot:item="{ props, item }">
                    <v-list-item v-bind="props" :title="item.value">
                      <template v-slot:prepend>
                        <v-icon
                          v-if="item.value === new Date().getFullYear()"
                          color="success"
                        >
                          mdi-star
                        </v-icon>
                      </template>
                    </v-list-item>
                  </template>
                </v-select>
              </v-col>

              <!-- Period Selection -->
              <v-col cols="12" md="6">
                <v-select
                  v-model="calculateForm.period"
                  :items="periodOptions"
                  label="Period"
                  placeholder="Select period"
                  prepend-inner-icon="mdi-calendar-range"
                  :rules="[(v) => !!v || 'Period is required']"
                  variant="outlined"
                  density="comfortable"
                >
                  <template v-slot:item="{ props, item }">
                    <v-list-item v-bind="props">
                      <template v-slot:prepend>
                        <v-icon>{{ getPeriodIcon(item.value) }}</v-icon>
                      </template>
                    </v-list-item>
                  </template>
                </v-select>
              </v-col>

              <!-- Section 2: Department Filter -->
              <v-col cols="12" class="mt-4">
                <div class="section-header">
                  <div class="section-icon">
                    <v-icon size="18">mdi-office-building</v-icon>
                  </div>
                  <h3 class="section-title">Department Filter</h3>
                </div>
              </v-col>

              <!-- Department Selection -->
              <v-col cols="12">
                <v-select
                  v-model="calculateForm.department"
                  :items="departments"
                  item-title="title"
                  item-value="value"
                  label="Department"
                  placeholder="All Departments"
                  prepend-inner-icon="mdi-office-building"
                  clearable
                  variant="outlined"
                  density="comfortable"
                  hint="Leave empty to calculate for all departments"
                  persistent-hint
                >
                  <template v-slot:prepend-item>
                    <v-list-item
                      title="All Departments"
                      @click="calculateForm.department = null"
                    >
                      <template v-slot:prepend>
                        <v-icon color="primary"
                          >mdi-office-building-outline</v-icon
                        >
                      </template>
                    </v-list-item>
                    <v-divider class="my-2"></v-divider>
                  </template>
                </v-select>
              </v-col>

              <!-- Section 3: Payment Details -->
              <v-col cols="12" class="mt-4">
                <div class="section-header">
                  <div class="section-icon">
                    <v-icon size="18">mdi-cash-check</v-icon>
                  </div>
                  <h3 class="section-title">Payment Details</h3>
                </div>
              </v-col>

              <!-- Payment Date -->
              <v-col cols="12">
                <v-text-field
                  v-model="calculateForm.payment_date"
                  label="Payment Date"
                  type="date"
                  placeholder="Select payment date"
                  prepend-inner-icon="mdi-calendar-check"
                  :rules="[(v) => !!v || 'Payment date is required']"
                  variant="outlined"
                  density="comfortable"
                  hint="Date when the 13th month pay will be released"
                  persistent-hint
                ></v-text-field>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>
        <v-divider></v-divider>
        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <button
            class="dialog-btn dialog-btn-cancel"
            @click="calculateDialog = false"
          >
            Cancel
          </button>
          <button
            class="dialog-btn dialog-btn-primary"
            @click="calculate"
            :disabled="!formValid"
          >
            <v-icon size="20" class="mr-1">mdi-calculator-variant</v-icon>
            Calculate Now
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Details Dialog -->
    <v-dialog v-model="detailsDialog" max-width="1200px">
      <v-card v-if="selectedItem">
        <v-card-title class="text-h5">
          13th Month Pay Details - {{ selectedItem.batch_number }}
        </v-card-title>
        <v-card-text>
          <v-row>
            <v-col cols="12" md="6">
              <v-list dense>
                <v-list-item>
                  <v-list-item-title>Year:</v-list-item-title>
                  <v-list-item-subtitle>{{
                    selectedItem.year
                  }}</v-list-item-subtitle>
                </v-list-item>
                <v-list-item>
                  <v-list-item-title>Period:</v-list-item-title>
                  <v-list-item-subtitle>{{
                    formatPeriod(selectedItem.period)
                  }}</v-list-item-subtitle>
                </v-list-item>
                <v-list-item>
                  <v-list-item-title>Department:</v-list-item-title>
                  <v-list-item-subtitle>{{
                    selectedItem.department || "All Departments"
                  }}</v-list-item-subtitle>
                </v-list-item>
              </v-list>
            </v-col>
            <v-col cols="12" md="6">
              <v-list dense>
                <v-list-item>
                  <v-list-item-title>Payment Date:</v-list-item-title>
                  <v-list-item-subtitle>{{
                    formatDate(selectedItem.payment_date)
                  }}</v-list-item-subtitle>
                </v-list-item>
                <v-list-item>
                  <v-list-item-title>Total Amount:</v-list-item-title>
                  <v-list-item-subtitle
                    >₱{{
                      formatNumber(selectedItem.total_amount)
                    }}</v-list-item-subtitle
                  >
                </v-list-item>
                <v-list-item>
                  <v-list-item-title>Status:</v-list-item-title>
                  <v-list-item-subtitle>
                    <v-chip
                      :color="getStatusColor(selectedItem.status)"
                      size="small"
                    >
                      {{ selectedItem.status.toUpperCase() }}
                    </v-chip>
                  </v-list-item-subtitle>
                </v-list-item>
              </v-list>
            </v-col>
          </v-row>

          <v-divider class="my-4"></v-divider>

          <v-data-table
            :headers="detailHeaders"
            :items="selectedItem.items || []"
            :items-per-page="10"
            class="elevation-1"
          >
            <template #[`item.employee`]="{ item }">
              {{ item.employee?.full_name || "N/A" }}
            </template>
            <template #[`item.department`]="{ item }">
              {{ item.employee?.department || "N/A" }}
            </template>
            <template #[`item.total_basic_salary`]="{ item }">
              ₱{{ formatNumber(item.total_basic_salary) }}
            </template>
            <template #[`item.net_pay`]="{ item }">
              <strong>₱{{ formatNumber(item.net_pay) }}</strong>
            </template>
          </v-data-table>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn color="primary" @click="detailsDialog = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Snackbar -->
    <v-snackbar v-model="snackbar.show" :color="snackbar.color" :timeout="3000">
      {{ snackbar.message }}
    </v-snackbar>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import api from "@/services/api";
import { formatDate, formatNumber } from "@/utils/formatters";
import { devLog } from "@/utils/devLog";
import { useConfirmDialog } from "@/composables/useConfirmDialog";

const { confirm: confirmDialog } = useConfirmDialog();
const loading = ref(false);
const calculating = ref(false);
const calculateDialog = ref(false);
const detailsDialog = ref(false);
const formValid = ref(false);
const currentStep = ref(0);
const thirteenthMonthList = ref([]);
const selectedItem = ref(null);
const departments = ref([]);

const filters = ref({
  year: null,
  status: null,
});

const calculateForm = ref({
  year: new Date().getFullYear(),
  period: "full_year",
  department: null,
  payment_date: null,
});

const snackbar = ref({
  show: false,
  message: "",
  color: "success",
});

const headers = [
  { title: "Batch Number", key: "batch_number", sortable: true },
  { title: "Year/Period", key: "year_period", sortable: false },
  { title: "Department", key: "department", sortable: true },
  { title: "Employees", key: "employee_count", sortable: false },
  { title: "Total Amount", key: "total_amount", sortable: true },
  { title: "Payment Date", key: "payment_date", sortable: true },
  { title: "Status", key: "status", sortable: true },
  { title: "Actions", key: "actions", sortable: false, align: "center" },
];

const detailHeaders = [
  { title: "Employee", key: "employee", sortable: true },
  { title: "Department", key: "department", sortable: true },
  { title: "Total Basic Salary", key: "total_basic_salary", sortable: true },
  { title: "Net Pay", key: "net_pay", sortable: true },
];

const statusOptions = [
  { title: "All", value: null },
  { title: "Draft", value: "draft" },
  { title: "Computed", value: "computed" },
  { title: "Approved", value: "approved" },
  { title: "Paid", value: "paid" },
];

const periodOptions = [
  { title: "Full Year", value: "full_year" },
  { title: "First Half (Jan-Jun)", value: "first_half" },
  { title: "Second Half (Jul-Dec)", value: "second_half" },
];

const yearOptions = computed(() => {
  const currentYear = new Date().getFullYear();
  const years = [];
  for (let i = currentYear; i >= currentYear - 5; i--) {
    years.push(i);
  }
  return years;
});

const getStatusColor = (status) => {
  const colors = {
    draft: "grey",
    computed: "info",
    approved: "success",
    paid: "primary",
  };
  return colors[status] || "grey";
};

const formatPeriod = (period) => {
  const periods = {
    full_year: "Full Year",
    first_half: "First Half",
    second_half: "Second Half",
  };
  return periods[period] || period;
};

const getPeriodIcon = (period) => {
  const icons = {
    full_year: "mdi-calendar",
    first_half: "mdi-calendar-start",
    second_half: "mdi-calendar-end",
  };
  return icons[period] || "mdi-calendar";
};

const fetchThirteenthMonth = async () => {
  loading.value = true;
  try {
    const params = {};
    if (filters.value.year) params.year = filters.value.year;
    if (filters.value.status) params.status = filters.value.status;

    const response = await api.get("/thirteenth-month", { params });
    thirteenthMonthList.value = response.data.data || response.data;
  } catch (error) {
    devLog.error("Error fetching 13th month pay:", error);
    showSnackbar("Failed to fetch 13th month pay records", "error");
  } finally {
    loading.value = false;
  }
};

const fetchDepartments = async () => {
  try {
    const response = await api.get("/thirteenth-month/departments");
    departments.value = response.data;
  } catch (error) {
    // Silent fail — departments are optional
  }
};

const openCalculateDialog = () => {
  calculateForm.value = {
    year: new Date().getFullYear(),
    period: "full_year",
    department: null,
    payment_date: null,
  };
  currentStep.value = 0;
  calculateDialog.value = true;
};

const calculate = async () => {
  calculating.value = true;
  try {
    const payload = {
      year: calculateForm.value.year,
      period: calculateForm.value.period,
      payment_date: calculateForm.value.payment_date,
    };

    if (calculateForm.value.department) {
      payload.department = calculateForm.value.department;
    }

    const response = await api.post("/thirteenth-month/calculate", payload);
    showSnackbar(
      response.data.message || "13th month pay calculated successfully",
      "success",
    );
    calculateDialog.value = false;
    fetchThirteenthMonth();
  } catch (error) {
    devLog.error("Error calculating 13th month pay:", error);
    showSnackbar(
      error.response?.data?.message || "Failed to calculate 13th month pay",
      "error",
    );
  } finally {
    calculating.value = false;
  }
};

const viewDetails = async (item) => {
  loading.value = true;
  try {
    const response = await api.get(`/thirteenth-month/${item.id}`);
    selectedItem.value = response.data;
    detailsDialog.value = true;
  } catch (error) {
    devLog.error("Error fetching details:", error);
    showSnackbar("Failed to fetch details", "error");
  } finally {
    loading.value = false;
  }
};

const downloadPdf = async (item) => {
  try {
    const response = await api.get(`/thirteenth-month/${item.id}/export-pdf`, {
      responseType: "blob",
    });

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute("download", `13th-month-pay-${item.batch_number}.pdf`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);

    showSnackbar("PDF downloaded successfully", "success");
  } catch (error) {
    devLog.error("Error downloading PDF:", error);
    showSnackbar("Failed to download PDF", "error");
  }
};

const downloadPdfDetailed = async (item) => {
  try {
    const response = await api.get(
      `/thirteenth-month/${item.id}/export-pdf-detailed`,
      {
        responseType: "blob",
      },
    );

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute(
      "download",
      `13th-month-pay-detailed-${item.batch_number}.pdf`,
    );
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);

    showSnackbar("Detailed PDF downloaded successfully", "success");
  } catch (error) {
    devLog.error("Error downloading detailed PDF:", error);
    showSnackbar("Failed to download detailed PDF", "error");
  }
};

const approve = async (item) => {
  if (
    !(await confirmDialog(
      "Are you sure you want to approve this 13th month pay?",
    ))
  )
    return;

  try {
    const response = await api.post(`/thirteenth-month/${item.id}/approve`);
    showSnackbar(response.data.message || "Approved successfully", "success");
    fetchThirteenthMonth();
  } catch (error) {
    devLog.error("Error approving:", error);
    showSnackbar(error.response?.data?.message || "Failed to approve", "error");
  }
};

const markPaid = async (item) => {
  if (!(await confirmDialog("Are you sure you want to mark this as paid?")))
    return;

  try {
    const response = await api.post(`/thirteenth-month/${item.id}/mark-paid`);
    showSnackbar(
      response.data.message || "Marked as paid successfully",
      "success",
    );
    fetchThirteenthMonth();
  } catch (error) {
    devLog.error("Error marking as paid:", error);
    showSnackbar(
      error.response?.data?.message || "Failed to mark as paid",
      "error",
    );
  }
};

const deleteItem = async (item) => {
  if (
    !(await confirmDialog(
      `Are you sure you want to delete batch ${item.batch_number}? This action cannot be undone.`,
    ))
  )
    return;

  try {
    const response = await api.delete(`/thirteenth-month/${item.id}`);
    showSnackbar(response.data.message || "Deleted successfully", "success");
    fetchThirteenthMonth();
  } catch (error) {
    devLog.error("Error deleting:", error);
    showSnackbar(error.response?.data?.message || "Failed to delete", "error");
  }
};

const showSnackbar = (message, color = "success") => {
  snackbar.value = {
    show: true,
    message,
    color,
  };
};

onMounted(() => {
  fetchThirteenthMonth();
  fetchDepartments();
});
</script>

<style lang="scss" scoped>
.thirteenth-month-page {
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

.v-card-title {
  background: linear-gradient(90deg, #1976d2 0%, #1565c0 100%);
  color: white;
}

/* Modern Dialog Styles */
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
  font-size: 14px;
  color: #6c757d;
  margin-top: 4px;
}

.dialog-content {
  padding: 24px;
  overflow-y: auto;
}

.section-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 10px 20px;
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

.v-select :deep(.v-field),
.v-text-field :deep(.v-field) {
  border-radius: 12px;
  transition: all 0.3s ease;
}

.v-select:hover :deep(.v-field),
.v-text-field:hover :deep(.v-field) {
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.v-chip-group {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
}

/* Enhance button styling */
.v-btn.v-btn--elevated {
  box-shadow: 0 4px 12px rgba(25, 118, 210, 0.3) !important;
  transition: all 0.3s ease;
}

.v-btn.v-btn--elevated:hover {
  box-shadow: 0 6px 20px rgba(25, 118, 210, 0.4) !important;
  transform: translateY(-2px);
}

/* Alert styling */
.v-alert {
  border-radius: 12px;
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
