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
                class="action-btn action-btn-primary"
                @click="showExportDialog = true"
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
            <v-text-field
              v-model="search"
              prepend-inner-icon="mdi-magnify"
              label="Search employee..."
              clearable
              variant="outlined"
              density="comfortable"
              hide-details
              style="min-width: 400px"
            ></v-text-field>
          </div>
        </div>

        <div class="table-section">
          <v-data-table
            :headers="headers"
            :items="payroll?.items || []"
            :search="search"
            :custom-filter="customFilter"
            :items-per-page="15"
            class="modern-table"
          >
            <!-- Employee -->
            <template v-slot:item.employee="{ item }">
              <div>
                <div class="font-weight-medium">
                  {{ item.employee?.first_name }} {{ item.employee?.last_name }}
                </div>
                <div class="text-caption text-medium-emphasis">
                  {{ item.employee?.employee_number }}
                </div>
              </div>
            </template>

            <!-- Rate & Days -->
            <template v-slot:item.rate_days="{ item }">
              <div>
                <div>
                  ₱{{ formatCurrency(item.effective_rate || item.rate || 0) }}
                </div>
                <div class="text-caption">
                  <span v-if="item.regular_days > 0 || item.holiday_days > 0">
                    {{ item.regular_days }} reg
                    <span v-if="item.holiday_days > 0">
                      + {{ item.holiday_days }} hol</span
                    >
                  </span>
                  <span v-else>{{ item.days_worked }} days</span>
                </div>
              </div>
            </template>

            <!-- Basic Pay -->
            <template v-slot:item.basic_pay="{ item }">
              <div class="text-right">
                <div>₱{{ formatCurrency(item.basic_pay) }}</div>
                <div
                  v-if="item.holiday_pay > 0"
                  class="text-caption text-success"
                >
                  +₱{{ formatCurrency(item.holiday_pay) }} hol
                </div>
              </div>
            </template>

            <!-- Overtime -->
            <template v-slot:item.overtime="{ item }">
              <div>
                <div v-if="item.regular_ot_hours > 0" class="text-caption">
                  {{ item.regular_ot_hours }}h: ₱{{
                    formatCurrency(item.regular_ot_pay)
                  }}
                </div>
                <div v-else class="text-caption text-medium-emphasis">-</div>
              </div>
            </template>

            <!-- Gross Pay -->
            <template v-slot:item.gross_pay="{ item }">
              <div class="text-right font-weight-bold" style="color: #ed985f">
                ₱{{ formatCurrency(item.gross_pay) }}
              </div>
            </template>

            <!-- Deductions -->
            <template v-slot:item.deductions="{ item }">
              <div class="text-caption">
                <div>SSS: ₱{{ formatCurrency(item.sss) }}</div>
                <div>PhilHealth: ₱{{ formatCurrency(item.philhealth) }}</div>
                <div>Pag-IBIG: ₱{{ formatCurrency(item.pagibig) }}</div>
                <div v-if="item.total_loan_deductions > 0">
                  Loans: ₱{{ formatCurrency(item.total_loan_deductions) }}
                </div>
                <div v-if="item.employee_deductions > 0" class="text-warning">
                  Other Deductions: ₱{{
                    formatCurrency(item.employee_deductions)
                  }}
                </div>
              </div>
            </template>

            <!-- Net Pay -->
            <template v-slot:item.net_pay="{ item }">
              <div class="text-right font-weight-bold" style="color: #10b981">
                ₱{{ formatCurrency(item.net_pay) }}
              </div>
            </template>

            <!-- Actions -->
            <template v-slot:item.actions="{ item }">
              <v-btn
                icon="mdi-download"
                size="small"
                variant="text"
                color="#ED985F"
                @click="downloadPayslip(item)"
                title="Download Payslip"
              >
              </v-btn>
            </template>
          </v-data-table>
        </div>
      </div>
    </div>

    <!-- Export Filter Dialog -->
    <v-dialog
      v-model="showExportDialog"
      max-width="600px"
      persistent
      scrollable
    >
      <v-card class="modern-dialog">
        <!-- Enhanced Header -->
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper primary">
            <v-icon size="24">mdi-filter-variant</v-icon>
          </div>
          <div>
            <div class="dialog-title">Export Payroll Register</div>
            <div class="dialog-subtitle">
              Filter and download payroll register PDF
            </div>
          </div>
        </v-card-title>

        <v-divider></v-divider>

        <v-card-text class="dialog-content">
          <!-- Section: Filter Options -->
          <v-col cols="12" class="px-0">
            <div class="section-header">
              <div class="section-icon">
                <v-icon size="18">mdi-filter</v-icon>
              </div>
              <h3 class="section-title">Filter Options</h3>
            </div>
          </v-col>

          <v-radio-group
            v-model="exportFilter.type"
            inline
            hide-details
            class="mb-4"
          >
            <v-radio label="All Employees" value="all"></v-radio>
            <v-radio label="By Department" value="department"></v-radio>
            <v-radio label="By Staff Type" value="staff_type"></v-radio>
          </v-radio-group>

          <div
            class="form-field-wrapper"
            v-if="exportFilter.type === 'department'"
          >
            <label class="form-label">
              <v-icon size="small" color="#ed985f">mdi-office-building</v-icon>
              Select Departments
            </label>
            <v-autocomplete
              v-model="exportFilter.departments"
              :items="departmentOptions"
              placeholder="Choose one or more departments"
              variant="outlined"
              density="comfortable"
              prepend-inner-icon="mdi-office-building"
              color="#ed985f"
              multiple
              chips
              closable-chips
              hint="Select one or more departments"
              persistent-hint
            ></v-autocomplete>
          </div>

          <div
            class="form-field-wrapper"
            v-if="exportFilter.type === 'staff_type'"
          >
            <label class="form-label">
              <v-icon size="small" color="#ed985f">mdi-account-group</v-icon>
              Select Staff Types
            </label>
            <v-autocomplete
              v-model="exportFilter.staff_types"
              :items="staffTypeOptions"
              placeholder="Choose one or more staff types"
              variant="outlined"
              density="comfortable"
              prepend-inner-icon="mdi-account-group"
              color="#ed985f"
              multiple
              chips
              closable-chips
              hint="Select one or more staff types"
              persistent-hint
            ></v-autocomplete>
          </div>

          <v-alert
            v-if="exportFilter.type !== 'all'"
            type="info"
            variant="tonal"
            density="compact"
            class="mt-4"
          >
            <template v-slot:prepend>
              <v-icon icon="mdi-information"></v-icon>
            </template>
            <div class="text-caption">
              <strong>Note:</strong>
              <span v-if="exportFilter.type === 'department'">
                Multiple departments will generate separate tables for each
                department.
              </span>
              <span v-else-if="exportFilter.type === 'staff_type'">
                Multiple staff types will generate separate tables for each
                staff type.
              </span>
            </div>
          </v-alert>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <button
            class="dialog-btn dialog-btn-cancel"
            @click="showExportDialog = false"
          >
            Cancel
          </button>
          <button
            class="dialog-btn dialog-btn-primary"
            @click="downloadRegister"
          >
            <v-icon size="20" class="mr-2">mdi-download</v-icon>
            Download PDF
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRoute, useRouter } from "vue-router";
import { useToast } from "vue-toastification";
import api from "@/services/api";

const route = useRoute();
const router = useRouter();
const toast = useToast();

const loading = ref(false);
const finalizing = ref(false);
const search = ref("");
const payroll = ref(null);
const showExportDialog = ref(false);
const exportFilter = ref({
  type: "all",
  departments: [],
  staff_types: [],
});
const departmentOptions = ref([]);
const staffTypeOptions = ref([]);

const headers = [
  { title: "Employee", key: "employee", sortable: true },
  { title: "Rate & Days", key: "rate_days", sortable: false },
  { title: "Basic Pay", key: "basic_pay", sortable: true, align: "end" },
  { title: "Overtime", key: "overtime", sortable: false },
  { title: "Gross Pay", key: "gross_pay", sortable: true, align: "end" },
  { title: "Deductions", key: "deductions", sortable: false },
  { title: "Net Pay", key: "net_pay", sortable: true, align: "end" },
  { title: "Actions", key: "actions", sortable: false, align: "center" },
];

// Custom filter function to search employee names properly
function customFilter(value, query, item) {
  if (!query) return true;

  const searchTerm = query.toLowerCase();
  const employee = item.raw?.employee;

  if (!employee) return false;

  // Search in first name, last name, middle name, full name, and employee number
  const firstName = (employee.first_name || "").toLowerCase();
  const middleName = (employee.middle_name || "").toLowerCase();
  const lastName = (employee.last_name || "").toLowerCase();
  const fullName = `${firstName} ${middleName} ${lastName}`.trim();
  const employeeNumber = (employee.employee_number || "").toLowerCase();

  return (
    firstName.includes(searchTerm) ||
    lastName.includes(searchTerm) ||
    middleName.includes(searchTerm) ||
    fullName.includes(searchTerm) ||
    employeeNumber.includes(searchTerm)
  );
}

onMounted(() => {
  fetchPayroll();
  loadDepartmentOptions();
  loadStaffTypeOptions();
});

async function fetchPayroll() {
  loading.value = true;
  try {
    const response = await api.get(`/payrolls/${route.params.id}`);
    payroll.value = response.data;
  } catch (error) {
    console.error("Error fetching payroll:", error);
    toast.error("Failed to load payroll details");
    router.push("/payroll");
  } finally {
    loading.value = false;
  }
}

async function finalizePayroll() {
  if (
    !confirm(
      "Are you sure you want to finalize this payroll? You will not be able to edit it after finalization.",
    )
  ) {
    return;
  }

  finalizing.value = true;
  try {
    await api.post(`/payrolls/${payroll.value.id}/finalize`);
    toast.success("Payroll finalized successfully");
    await fetchPayroll();
  } catch (error) {
    console.error("Error finalizing payroll:", error);
    toast.error("Failed to finalize payroll");
  } finally {
    finalizing.value = false;
  }
}

async function downloadRegister() {
  try {
    // Build query parameters based on filter
    const params = {
      filter_type: exportFilter.value.type,
    };

    if (
      exportFilter.value.type === "department" &&
      exportFilter.value.departments.length > 0
    ) {
      params.departments = exportFilter.value.departments;
    } else if (
      exportFilter.value.type === "staff_type" &&
      exportFilter.value.staff_types.length > 0
    ) {
      params.staff_types = exportFilter.value.staff_types;
    }

    const response = await api.get(
      `/payrolls/${payroll.value.id}/download-register`,
      {
        params,
        responseType: "blob",
      },
    );

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;

    let filename = `payroll_register_${payroll.value.payroll_number}`;
    if (exportFilter.value.type !== "all") {
      filename += "_filtered";
    }
    filename += ".pdf";

    link.setAttribute("download", filename);
    document.body.appendChild(link);
    link.click();
    link.remove();

    toast.success("Payroll register downloaded");
    showExportDialog.value = false;
  } catch (error) {
    console.error("Error downloading register:", error);
    toast.error("Failed to download payroll register");
  }
}

async function loadDepartmentOptions() {
  try {
    const response = await api.get("/employees/departments");
    departmentOptions.value = response.data || [];
  } catch (error) {
    console.error("Error loading departments:", error);
  }
}

async function loadStaffTypeOptions() {
  try {
    const response = await api.get("/position-rates");
    staffTypeOptions.value =
      response.data.map((pos) => pos.position_name) || [];
  } catch (error) {
    console.error("Error loading staff types:", error);
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
    console.error("Error downloading payslip:", error);
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

function formatDate(date) {
  if (!date) return "";
  return new Date(date).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

function formatCurrency(amount) {
  if (!amount) return "0.00";
  return parseFloat(amount).toLocaleString("en-US", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}
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

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
  margin-bottom: 24px;
}

.stat-card {
  background: white;
  border-radius: 16px;
  padding: 16px;
  display: flex;
  align-items: center;
  gap: 16px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
  transition: all 0.2s ease;
  border: 1px solid rgba(0, 31, 61, 0.06);
  cursor: pointer;
}

.stat-card:hover {
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.15) !important;
  transform: translateY(-2px) !important;
  border-color: rgba(237, 152, 95, 0.3) !important;
  background: white !important;
}

.stat-card.info-card {
  grid-column: span 1;
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  flex-shrink: 0;
}

.stat-icon.period,
.stat-icon.payment {
  background: linear-gradient(135deg, #64748b 0%, #94a3b8 100%);
}

.stat-icon.employees {
  background: linear-gradient(135deg, #001f3d 0%, #0f3557 100%);
}

.stat-icon.gross {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
}

.stat-icon.net {
  background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
}

.stat-content {
  flex: 1;
  min-width: 0;
  overflow: hidden;
}

.stat-label {
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
}

.stat-value {
  font-size: 18px;
  font-weight: 700;
  color: #001f3d;
  letter-spacing: -0.5px;
  word-break: break-word;
  line-height: 1.2;
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
}

.table-section {
  overflow-x: auto;
  padding-top: 10px;
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

/* Responsive Design */
@media (max-width: 1024px) {
  .header-main {
    flex-direction: column;
    align-items: flex-start;
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
    width: 100%;
  }

  .header-right .v-text-field {
    max-width: 100% !important;
  }

  .stats-grid {
    grid-template-columns: 1fr;
  }

  .action-btn {
    padding: 10px 16px;
    font-size: 13px;
  }
}

/* Modern Dialog Styling */
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

  &:hover:not(:disabled) {
    box-shadow: 0 4px 12px rgba(237, 152, 95, 0.4);
    transform: translateY(-1px);
  }
}
</style>
