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
            <v-select
              v-model="positionFilter"
              :items="availablePositions"
              label="Filter by Position"
              prepend-inner-icon="mdi-briefcase"
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

        <div class="table-section">
          <v-data-table
            :headers="headers"
            :items="filteredItems"
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

            <!-- Amount (Basic Pay) -->
            <template v-slot:item.amount="{ item }">
              <div class="text-right">
                <div>₱{{ formatCurrency(item.basic_pay || 0) }}</div>
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
                  Reg: {{ item.regular_ot_hours }}h — ₱{{
                    formatCurrency(item.regular_ot_pay)
                  }}
                </div>
                <div
                  v-if="item.special_ot_hours > 0"
                  class="text-caption text-orange"
                >
                  Sun/Hol: {{ item.special_ot_hours }}h — ₱{{
                    formatCurrency(item.special_ot_pay)
                  }}
                </div>
                <div
                  v-if="
                    !(item.regular_ot_hours > 0) && !(item.special_ot_hours > 0)
                  "
                  class="text-caption text-medium-emphasis"
                >
                  -
                </div>
              </div>
            </template>

            <!-- Undertime -->
            <template v-slot:item.undertime="{ item }">
              <div class="text-center">
                <div
                  v-if="item.undertime_hours > 0"
                  class="text-caption text-warning"
                >
                  {{ formatUndertime(item.undertime_hours) }}
                </div>
                <div
                  v-if="item.undertime_deduction > 0"
                  class="text-caption text-warning"
                >
                  -₱{{ formatCurrency(item.undertime_deduction) }}
                </div>
                <div
                  v-if="
                    !(item.undertime_hours > 0) &&
                    !(item.undertime_deduction > 0)
                  "
                  class="text-caption text-medium-emphasis"
                >
                  -
                </div>
              </div>
            </template>

            <!-- Gross Pay -->
            <template v-slot:item.gross_pay="{ item }">
              <div class="text-right">
                <div class="font-weight-bold" style="color: #ed985f">
                  ₱{{ formatCurrency(item.gross_pay) }}
                </div>
                <div
                  v-if="item.salary_adjustment > 0"
                  class="text-caption text-info"
                >
                  +₱{{ formatCurrency(item.salary_adjustment) }} adj
                </div>
                <div
                  v-else-if="item.salary_adjustment < 0"
                  class="text-caption text-error"
                >
                  -₱{{ formatCurrency(Math.abs(item.salary_adjustment)) }} adj
                </div>
                <template
                  v-if="
                    item.allowances_breakdown &&
                    item.allowances_breakdown.length > 0
                  "
                >
                  <div
                    v-for="(a, idx) in item.allowances_breakdown"
                    :key="idx"
                    class="text-caption text-success"
                  >
                    +₱{{ formatCurrency(a.amount) }}
                    {{ a.label || a.name || a.type }}
                  </div>
                </template>
              </div>
            </template>

            <!-- Deductions -->
            <template v-slot:item.deductions="{ item }">
              <div class="text-caption">
                <div v-if="item.employee?.has_sss">
                  <v-icon size="12" color="primary" class="mr-1"
                    >mdi-check-circle</v-icon
                  >
                  SSS: ₱{{ formatCurrency(item.sss) }}
                </div>
                <div v-else class="text-medium-emphasis">
                  <v-icon size="12" class="mr-1"
                    >mdi-minus-circle-outline</v-icon
                  >
                  SSS: N/A
                </div>
                <div v-if="item.employee?.has_philhealth">
                  <v-icon size="12" color="success" class="mr-1"
                    >mdi-check-circle</v-icon
                  >
                  PhilHealth: ₱{{ formatCurrency(item.philhealth) }}
                </div>
                <div v-else class="text-medium-emphasis">
                  <v-icon size="12" class="mr-1"
                    >mdi-minus-circle-outline</v-icon
                  >
                  PhilHealth: N/A
                </div>
                <div v-if="item.employee?.has_pagibig">
                  <v-icon size="12" color="orange" class="mr-1"
                    >mdi-check-circle</v-icon
                  >
                  Pag-IBIG: ₱{{ formatCurrency(item.pagibig) }}
                </div>
                <div v-else class="text-medium-emphasis">
                  <v-icon size="12" class="mr-1"
                    >mdi-minus-circle-outline</v-icon
                  >
                  Pag-IBIG: N/A
                </div>
                <div v-if="item.undertime_deduction > 0">
                  <v-icon size="12" color="warning" class="mr-1"
                    >mdi-clock-minus-outline</v-icon
                  >
                  Undertime: ₱{{ formatCurrency(item.undertime_deduction) }}
                </div>
                <div v-if="item.loans > 0">
                  <v-icon size="12" color="red" class="mr-1"
                    >mdi-bank-outline</v-icon
                  >
                  Loans: ₱{{ formatCurrency(item.loans) }}
                </div>
                <div v-if="item.cash_advance > 0">
                  <v-icon size="12" color="deep-orange" class="mr-1"
                    >mdi-cash-fast</v-icon
                  >
                  Cash Advance: ₱{{ formatCurrency(item.cash_advance) }}
                </div>
                <div v-if="item.employee_savings > 0">
                  <v-icon size="12" color="teal" class="mr-1"
                    >mdi-piggy-bank-outline</v-icon
                  >
                  Savings: ₱{{ formatCurrency(item.employee_savings) }}
                </div>
                <div v-if="item.withholding_tax > 0">
                  <v-icon size="12" color="purple" class="mr-1"
                    >mdi-file-document-outline</v-icon
                  >
                  Tax: ₱{{ formatCurrency(item.withholding_tax) }}
                </div>
                <div v-if="item.employee_deductions > 0" class="text-warning">
                  Other: ₱{{ formatCurrency(item.employee_deductions) }}
                </div>
                <div v-if="item.other_deductions > 0" class="text-warning">
                  Misc: ₱{{ formatCurrency(item.other_deductions) }}
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
                @click="exportFilter.format = 'pdf'"
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

              <!-- Excel -->
              <div
                class="format-card"
                :class="{ active: exportFilter.format === 'excel' }"
                @click="exportFilter.format = 'excel'"
              >
                <div class="format-card-icon excel">
                  <v-icon size="22">mdi-file-excel-box</v-icon>
                </div>
                <div class="format-card-info">
                  <div class="format-card-name">Excel</div>
                  <div class="format-card-desc">Editable spreadsheet</div>
                </div>
                <v-icon
                  v-if="exportFilter.format === 'excel'"
                  size="18"
                  class="format-check"
                  >mdi-check-circle</v-icon
                >
              </div>

              <!-- Word -->
              <div
                class="format-card"
                :class="{ active: exportFilter.format === 'word' }"
                @click="exportFilter.format = 'word'"
              >
                <div class="format-card-icon word">
                  <v-icon size="22">mdi-file-word-box</v-icon>
                </div>
                <div class="format-card-info">
                  <div class="format-card-name">Word</div>
                  <div class="format-card-desc">Formatted document</div>
                </div>
                <v-icon
                  v-if="exportFilter.format === 'word'"
                  size="18"
                  class="format-check"
                  >mdi-check-circle</v-icon
                >
              </div>

              <!-- Payslips -->
              <div
                class="format-card"
                :class="{ active: exportFilter.format === 'payslips' }"
                @click="exportFilter.format = 'payslips'"
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

              <!-- By Device (Excel) -->
              <div
                class="format-card"
                :class="{ active: exportFilter.format === 'by_device' }"
                @click="exportFilter.format = 'by_device'"
              >
                <div class="format-card-icon device-excel">
                  <v-icon size="22">mdi-devices</v-icon>
                </div>
                <div class="format-card-info">
                  <div class="format-card-name">By Device</div>
                  <div class="format-card-desc">Grouped &middot; Excel</div>
                </div>
                <v-icon
                  v-if="exportFilter.format === 'by_device'"
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
          </div>

          <!-- Filter Section (Collapsible) -->
          <div class="export-section">
            <button
              class="export-section-label clickable"
              @click="showExportFilters = !showExportFilters"
            >
              <v-icon size="16" class="mr-2" color="#64748b"
                >mdi-filter-variant</v-icon
              >
              Filter Employees
              <v-chip
                v-if="activeFilterCount > 0"
                size="x-small"
                color="#ed985f"
                variant="flat"
                class="ml-2"
                >{{ activeFilterCount }} active</v-chip
              >
              <v-spacer></v-spacer>
              <span class="filter-optional-hint">Optional</span>
              <v-icon
                size="18"
                color="#94a3b8"
                :style="{
                  transform: showExportFilters ? 'rotate(180deg)' : 'rotate(0)',
                  transition: 'transform 0.25s ease',
                }"
                >mdi-chevron-down</v-icon
              >
            </button>

            <v-expand-transition>
              <div v-show="showExportFilters" class="filter-panel">
                <v-select
                  v-model="exportFilter.departments"
                  :items="availableDepartments"
                  label="Department"
                  prepend-inner-icon="mdi-office-building"
                  variant="outlined"
                  density="comfortable"
                  multiple
                  chips
                  closable-chips
                  clearable
                  hide-details
                  class="mb-3"
                >
                  <template v-slot:selection="{ item, index }">
                    <v-chip
                      v-if="index < 2"
                      size="small"
                      color="primary"
                      variant="tonal"
                    >
                      {{ item.title }}
                    </v-chip>
                    <span
                      v-if="index === 2"
                      class="text-grey text-caption align-self-center"
                    >
                      (+{{ exportFilter.departments.length - 2 }} more)
                    </span>
                  </template>
                </v-select>

                <v-select
                  v-model="exportFilter.positions"
                  :items="availablePositions"
                  label="Position"
                  prepend-inner-icon="mdi-briefcase"
                  variant="outlined"
                  density="comfortable"
                  multiple
                  chips
                  closable-chips
                  clearable
                  hide-details
                  class="mb-3"
                >
                  <template v-slot:selection="{ item, index }">
                    <v-chip
                      v-if="index < 2"
                      size="small"
                      color="#ED985F"
                      variant="tonal"
                    >
                      {{ item.title }}
                    </v-chip>
                    <span
                      v-if="index === 2"
                      class="text-grey text-caption align-self-center"
                    >
                      (+{{ exportFilter.positions.length - 2 }} more)
                    </span>
                  </template>
                </v-select>

                <v-autocomplete
                  v-model="exportFilter.employees"
                  :items="availableEmployees"
                  item-title="name"
                  item-value="id"
                  label="Specific Employees"
                  prepend-inner-icon="mdi-account-search"
                  variant="outlined"
                  density="comfortable"
                  multiple
                  chips
                  closable-chips
                  clearable
                  hide-details
                >
                  <template v-slot:selection="{ item, index }">
                    <v-chip
                      v-if="index < 2"
                      size="small"
                      color="#00897B"
                      variant="tonal"
                    >
                      {{ item.raw.name }}
                    </v-chip>
                    <span
                      v-if="index === 2"
                      class="text-grey text-caption align-self-center"
                    >
                      (+{{ exportFilter.employees.length - 2 }} more)
                    </span>
                  </template>
                  <template v-slot:item="{ props, item }">
                    <v-list-item v-bind="props" :title="item.raw.name">
                      <template v-slot:subtitle>
                        {{ item.raw.employee_number }} &middot;
                        {{ item.raw.position }}
                      </template>
                    </v-list-item>
                  </template>
                </v-autocomplete>
              </div>
            </v-expand-transition>
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

const route = useRoute();
const router = useRouter();
const toast = useToast();
const { confirm: confirmDialog } = useConfirmDialog();

const loading = ref(false);
const finalizing = ref(false);
const search = ref("");
const positionFilter = ref(null);
const payroll = ref(null);
const showExportDialog = ref(false);
const downloadingRegister = ref(false);
const showExportFilters = ref(false);
const exportFilter = ref({
  format: "pdf", // pdf, excel, word
  departments: [], // Array of department names
  positions: [], // Array of position names
  employees: [], // Array of employee IDs
});
const availableDepartments = ref([]);

// Compute available positions from payroll items
const availablePositions = computed(() => {
  if (!payroll.value?.items) return [];

  const positions = new Set();
  payroll.value.items.forEach((item) => {
    if (item.employee?.position?.position_name) {
      positions.add(item.employee.position.position_name);
    }
  });

  return Array.from(positions).sort();
});

// Compute available employees from payroll items
const availableEmployees = computed(() => {
  if (!payroll.value?.items) return [];

  const employees = payroll.value.items
    .map((item) => {
      if (!item.employee) return null;

      const emp = item.employee;
      const fullName =
        `${emp.first_name || ""} ${emp.middle_name || ""} ${emp.last_name || ""}`
          .replace(/\s+/g, " ")
          .trim();

      return {
        id: emp.id,
        name: fullName,
        employee_number: emp.employee_number || "",
        position: emp.position?.position_name || "N/A",
      };
    })
    .filter((emp) => emp !== null)
    .sort((a, b) => a.name.localeCompare(b.name));

  return employees;
});

// Active filter count for the badge
const activeFilterCount = computed(() => {
  let count = 0;
  if (exportFilter.value.departments?.length) count++;
  if (exportFilter.value.positions?.length) count++;
  if (exportFilter.value.employees?.length) count++;
  return count;
});

// Format label for the download button
const exportFormatLabel = computed(() => {
  const labels = {
    pdf: "PDF",
    excel: "Excel",
    word: "Word",
    payslips: "Payslips",
    by_device: "By Device (XLS)",
    by_device_pdf: "By Device (PDF)",
  };
  return labels[exportFilter.value.format] || exportFilter.value.format;
});

// Summary text for the footer
const exportSummaryText = computed(() => {
  const totalEmployees = payroll.value?.items?.length || 0;
  const parts = [];

  if (exportFilter.value.employees?.length) {
    parts.push(
      `${exportFilter.value.employees.length} employee${exportFilter.value.employees.length > 1 ? "s" : ""} selected`,
    );
  } else if (
    exportFilter.value.departments?.length ||
    exportFilter.value.positions?.length
  ) {
    if (exportFilter.value.departments?.length)
      parts.push(
        `${exportFilter.value.departments.length} dept${exportFilter.value.departments.length > 1 ? "s" : ""}`,
      );
    if (exportFilter.value.positions?.length)
      parts.push(
        `${exportFilter.value.positions.length} position${exportFilter.value.positions.length > 1 ? "s" : ""}`,
      );
    return `Filtered by ${parts.join(" & ")} · ${totalEmployees} total in payroll`;
  } else {
    return `All ${totalEmployees} employees · ${exportFormatLabel.value} format`;
  }

  return `${parts.join(" · ")} · ${totalEmployees} total in payroll`;
});

// Filter items by position
const filteredItems = computed(() => {
  if (!payroll.value?.items) return [];

  if (!positionFilter.value) {
    return payroll.value.items;
  }

  return payroll.value.items.filter((item) => {
    return item.employee?.position?.position_name === positionFilter.value;
  });
});

const headers = [
  { title: "Employee", key: "employee", sortable: true },
  { title: "Rate & Days", key: "rate_days", sortable: false },
  { title: "Basic Pay", key: "amount", sortable: true, align: "end" },
  { title: "Overtime", key: "overtime", sortable: false },
  { title: "UT", key: "undertime", sortable: false },
  { title: "Gross Pay", key: "gross_pay", sortable: true, align: "end" },
  { title: "Deductions", key: "deductions", sortable: false },
  { title: "Net Pay", key: "net_pay", sortable: true, align: "end" },
  { title: "Actions", key: "actions", sortable: false, align: "center" },
];

// Custom filter function to search employee names properly
function customFilter(value, query, item) {
  if (!query) return true;

  const searchTerm = query.toLowerCase();

  // Handle both item.raw (Vuetify 3 internal structure) and direct item
  const employee = item?.raw?.employee || item?.employee;

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
});

async function fetchPayroll() {
  loading.value = true;
  try {
    const response = await api.get(`/payrolls/${route.params.id}`);
    payroll.value = response.data;

    // Extract unique departments from payroll items (from project relationship)
    const departments = new Set();
    payroll.value.items.forEach((item) => {
      // Use project name (from Departments) as the department
      if (item.employee?.project?.name) {
        departments.add(item.employee.project.name);
      } else if (item.employee?.department) {
        // Fallback to department text field
        departments.add(item.employee.department);
      }
    });
    availableDepartments.value = Array.from(departments).sort();
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
    }

    // Determine filter type based on selections
    const hasDepartments =
      exportFilter.value.departments &&
      exportFilter.value.departments.length > 0;
    const hasPositions =
      exportFilter.value.positions && exportFilter.value.positions.length > 0;
    const hasEmployees =
      exportFilter.value.employees && exportFilter.value.employees.length > 0;

    if (hasEmployees) {
      // If employees are selected, prioritize employee filter
      params.filter_type = "employee";
      params.employee_ids = exportFilter.value.employees;
    } else if (hasDepartments && hasPositions) {
      params.filter_type = "both";
      params.departments = exportFilter.value.departments;
      params.positions = exportFilter.value.positions;
    } else if (hasDepartments) {
      params.filter_type = "department";
      params.departments = exportFilter.value.departments;
    } else if (hasPositions) {
      params.filter_type = "position";
      params.positions = exportFilter.value.positions;
    } else {
      params.filter_type = "all";
    }

    // Use different endpoint for payslips
    const endpoint = isPayslips
      ? `/payrolls/${payroll.value.id}/download-payslips`
      : `/payrolls/${payroll.value.id}/download-register`;

    const response = await api.get(endpoint, {
      params: params,
      responseType: "blob",
    });

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;

    // Build filename with appropriate extension
    const extensions = {
      pdf: ".pdf",
      excel: ".xlsx",
      word: ".docx",
      payslips: ".pdf",
      by_device: ".xlsx",
      by_device_pdf: ".pdf",
    };

    // Use different base filename for payslips
    let filename = isPayslips
      ? `payslips_${payroll.value.payroll_number}`
      : `payroll_register_${payroll.value.payroll_number}`;

    // Add department info to filename if filtered
    if (
      exportFilter.value.departments &&
      exportFilter.value.departments.length > 0
    ) {
      if (exportFilter.value.departments.length === 1) {
        filename += `_${exportFilter.value.departments[0].replace(/\s+/g, "_")}`;
      } else {
        filename += "_filtered";
      }
    }

    filename += extensions[exportFilter.value.format];

    link.setAttribute("download", filename);
    document.body.appendChild(link);
    link.click();
    link.remove();

    const formatNames = {
      pdf: "PDF",
      excel: "Excel",
      word: "Word",
      payslips: "Compact Payslips PDF",
      by_device: "By Device (Excel)",
      by_device_pdf: "By Device (PDF)",
    };

    let successMessage = isPayslips
      ? "Payslips downloaded successfully"
      : `Payroll register downloaded as ${formatNames[exportFilter.value.format]}`;
    const filterParts = [];

    if (
      exportFilter.value.employees &&
      exportFilter.value.employees.length > 0
    ) {
      filterParts.push(
        `${exportFilter.value.employees.length} employee${exportFilter.value.employees.length > 1 ? "s" : ""}`,
      );
    } else {
      // Only show department/position filters if no employee filter is active
      if (
        exportFilter.value.departments &&
        exportFilter.value.departments.length > 0
      ) {
        filterParts.push(
          `${exportFilter.value.departments.length} department${exportFilter.value.departments.length > 1 ? "s" : ""}`,
        );
      }

      if (
        exportFilter.value.positions &&
        exportFilter.value.positions.length > 0
      ) {
        filterParts.push(
          `${exportFilter.value.positions.length} position${exportFilter.value.positions.length > 1 ? "s" : ""}`,
        );
      }
    }

    if (filterParts.length > 0) {
      successMessage += ` (filtered by ${filterParts.join(" and ")})`;
    }

    toast.success(successMessage);
    showExportDialog.value = false;

    // Reset filters for next time
    exportFilter.value.departments = [];
    exportFilter.value.positions = [];
    exportFilter.value.employees = [];
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

function formatUndertime(hours) {
  if (!hours || hours <= 0) return "";
  const h = Math.floor(hours);
  const m = Math.round((hours - h) * 60);
  if (h > 0 && m > 0) {
    return `${h}h ${m}m`;
  } else if (h > 0) {
    return `${h}h`;
  } else if (m > 0) {
    return `${m}m`;
  }
  return "";
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

.export-section-label.clickable {
  cursor: pointer;
  padding: 10px 14px;
  border-radius: 10px;
  background: #f8f9fb;
  border: 1px solid rgba(0, 31, 61, 0.06);
  margin-bottom: 0;
  transition: all 0.2s ease;
}

.export-section-label.clickable:hover {
  background: #f1f3f6;
  border-color: rgba(0, 31, 61, 0.1);
}

.filter-optional-hint {
  font-size: 11px;
  font-weight: 500;
  color: #94a3b8;
  text-transform: none;
  letter-spacing: 0;
  margin-right: 6px;
}

.filter-panel {
  padding: 16px 14px 4px 14px;
  background: #fafbfc;
  border: 1px solid rgba(0, 31, 61, 0.06);
  border-top: none;
  border-radius: 0 0 10px 10px;
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

.format-card-icon.excel {
  background: rgba(33, 115, 70, 0.1);
  color: #217346;
}

.format-card-icon.word {
  background: rgba(43, 87, 154, 0.1);
  color: #2b579a;
}

.format-card-icon.payslips {
  background: rgba(255, 111, 0, 0.1);
  color: #ff6f00;
}

.format-card-icon.device-excel {
  background: rgba(0, 137, 123, 0.1);
  color: #00897b;
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
