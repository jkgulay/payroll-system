<template>
  <div>
    <v-row class="mb-4">
      <v-col cols="12" md="6">
        <h1 class="text-h4 font-weight-bold">Payroll Management</h1>
        <p class="text-subtitle-2 text-medium-emphasis">
          Process and manage payroll periods
        </p>
      </v-col>
      <v-col cols="12" md="6" class="text-right">
        <v-btn
          color="primary"
          prepend-icon="mdi-plus"
          @click="showCreateDialog = true"
          size="large"
        >
          New Payroll Period
        </v-btn>
      </v-col>
    </v-row>

    <!-- Filters -->
    <v-card class="mb-4">
      <v-card-text>
        <v-row>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.status"
              :items="statusOptions"
              label="Status"
              clearable
              prepend-inner-icon="mdi-filter"
              @update:model-value="fetchPayrolls"
            ></v-select>
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.year"
              :items="yearOptions"
              label="Year"
              clearable
              prepend-inner-icon="mdi-calendar"
              @update:model-value="fetchPayrolls"
            ></v-select>
          </v-col>
          <v-col cols="12" md="6" class="text-right">
            <v-btn-group variant="outlined" divided>
              <v-btn
                prepend-icon="mdi-refresh"
                @click="fetchPayrolls"
                :loading="loading"
              >
                Refresh
              </v-btn>
            </v-btn-group>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Payroll Table -->
    <v-card>
      <v-data-table
        :headers="headers"
        :items="payrolls"
        :loading="loading"
        :items-per-page="20"
        hover
      >
        <template v-slot:item.payroll_number="{ item }">
          <strong class="text-primary">{{ item.payroll_number }}</strong>
        </template>

        <template v-slot:item.period="{ item }">
          <div>
            <div class="font-weight-medium">{{ item.period_label }}</div>
            <div class="text-caption text-medium-emphasis">
              Pay Period {{ item.pay_period_number || "N/A" }}
            </div>
          </div>
        </template>

        <template v-slot:item.payment_date="{ item }">
          {{ formatDate(item.payment_date) }}
        </template>

        <template v-slot:item.employee_count="{ item }">
          <v-chip size="small" color="info">
            {{ item.payroll_items_count || 0 }} employees
          </v-chip>
        </template>

        <template v-slot:item.total_net_pay="{ item }">
          <strong>₱{{ formatCurrency(item.total_net_pay) }}</strong>
        </template>

        <template v-slot:item.status="{ item }">
          <v-chip :color="getStatusColor(item.status)" size="small">
            <v-icon start size="x-small">{{
              getStatusIcon(item.status)
            }}</v-icon>
            {{ getStatusLabel(item.status) }}
          </v-chip>
        </template>

        <template v-slot:item.actions="{ item }">
          <v-menu>
            <template v-slot:activator="{ props }">
              <v-btn
                icon="mdi-dots-vertical"
                size="small"
                variant="text"
                v-bind="props"
              ></v-btn>
            </template>
            <v-list>
              <v-list-item @click="viewPayroll(item)">
                <template v-slot:prepend>
                  <v-icon color="info">mdi-eye</v-icon>
                </template>
                <v-list-item-title>View Details</v-list-item-title>
              </v-list-item>

              <v-list-item
                v-if="item.status === 'draft'"
                @click="processPayroll(item)"
              >
                <template v-slot:prepend>
                  <v-icon color="primary">mdi-play-circle</v-icon>
                </template>
                <v-list-item-title>Process Payroll</v-list-item-title>
              </v-list-item>

              <v-list-item
                v-if="item.status === 'processing'"
                @click="checkPayroll(item)"
              >
                <template v-slot:prepend>
                  <v-icon color="warning">mdi-check-circle</v-icon>
                </template>
                <v-list-item-title>Check Payroll</v-list-item-title>
              </v-list-item>

              <v-list-item
                v-if="item.status === 'checked'"
                @click="recommendPayroll(item)"
              >
                <template v-slot:prepend>
                  <v-icon color="accent">mdi-thumb-up</v-icon>
                </template>
                <v-list-item-title>Recommend</v-list-item-title>
              </v-list-item>

              <v-list-item
                v-if="item.status === 'recommended'"
                @click="approvePayroll(item)"
              >
                <template v-slot:prepend>
                  <v-icon color="success">mdi-check-decagram</v-icon>
                </template>
                <v-list-item-title>Approve</v-list-item-title>
              </v-list-item>

              <v-list-item
                v-if="item.status === 'approved'"
                @click="markAsPaid(item)"
              >
                <template v-slot:prepend>
                  <v-icon color="primary">mdi-cash-check</v-icon>
                </template>
                <v-list-item-title>Mark as Paid</v-list-item-title>
              </v-list-item>

              <v-divider></v-divider>

              <v-list-item @click="exportPayroll(item, 'excel')">
                <template v-slot:prepend>
                  <v-icon color="success">mdi-file-excel</v-icon>
                </template>
                <v-list-item-title>Export Excel</v-list-item-title>
              </v-list-item>

              <v-list-item @click="exportPayroll(item, 'pdf')">
                <template v-slot:prepend>
                  <v-icon color="error">mdi-file-pdf</v-icon>
                </template>
                <v-list-item-title>Export PDF</v-list-item-title>
              </v-list-item>

              <v-divider v-if="item.status === 'draft'"></v-divider>

              <v-list-item
                v-if="item.status === 'draft'"
                @click="confirmDeletePayroll(item)"
              >
                <template v-slot:prepend>
                  <v-icon color="error">mdi-delete</v-icon>
                </template>
                <v-list-item-title>Delete Payroll</v-list-item-title>
              </v-list-item>
            </v-list>
          </v-menu>
        </template>

        <template v-slot:no-data>
          <div class="text-center py-12">
            <v-icon size="64" color="grey-lighten-1">mdi-cash-multiple</v-icon>
            <p class="text-h6 mt-4 text-medium-emphasis">No Payroll Records</p>
            <p class="text-body-2 text-medium-emphasis mb-4">
              Create your first payroll period to get started
            </p>
            <v-btn
              color="primary"
              prepend-icon="mdi-plus"
              @click="showCreateDialog = true"
            >
              Create Payroll
            </v-btn>
          </div>
        </template>
      </v-data-table>
    </v-card>

    <!-- Create Payroll Dialog -->
    <v-dialog v-model="showCreateDialog" max-width="600px" persistent>
      <v-card>
        <v-card-title class="text-h5 py-4 bg-primary">
          <v-icon start>mdi-cash-plus</v-icon>
          Create New Payroll Period
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-6">
          <v-form ref="createForm">
            <v-row>
              <v-col cols="12">
                <v-alert type="info" variant="tonal" density="compact">
                  Create a new payroll period to calculate employee salaries
                  based on attendance records.
                </v-alert>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="newPayroll.period_start_date"
                  label="Period Start Date"
                  type="date"
                  :rules="[rules.required]"
                  variant="outlined"
                  prepend-inner-icon="mdi-calendar-start"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="newPayroll.period_end_date"
                  label="Period End Date"
                  type="date"
                  :rules="[rules.required, rules.endDateAfterStart]"
                  variant="outlined"
                  prepend-inner-icon="mdi-calendar-end"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="newPayroll.payment_date"
                  label="Payment Date"
                  type="date"
                  :rules="[rules.required]"
                  variant="outlined"
                  prepend-inner-icon="mdi-cash-clock"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="newPayroll.pay_period_number"
                  :items="[
                    { title: 'Period 1 (1st-15th)', value: 1 },
                    { title: 'Period 2 (16th-End)', value: 2 },
                  ]"
                  label="Pay Period"
                  variant="outlined"
                  prepend-inner-icon="mdi-numeric"
                  clearable
                ></v-select>
              </v-col>

              <!-- Optional Filters Section -->
              <v-col cols="12">
                <v-divider class="my-2"></v-divider>
                <v-alert
                  type="info"
                  variant="tonal"
                  density="compact"
                  class="mb-4"
                >
                  <strong>Optional Filters:</strong> Generate payroll for
                  specific groups of employees
                </v-alert>
              </v-col>

              <v-col cols="12" md="6">
                <v-select
                  v-model="newPayroll.contract_type"
                  :items="[
                    { title: 'Regular', value: 'regular' },
                    { title: 'Probationary', value: 'probationary' },
                    { title: 'Contractual', value: 'contractual' },
                  ]"
                  label="Contract Type (Optional)"
                  variant="outlined"
                  prepend-inner-icon="mdi-file-document-outline"
                  clearable
                  hint="Leave empty to include all contract types"
                  persistent-hint
                ></v-select>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="newPayroll.project_id"
                  label="Project ID (Optional)"
                  type="number"
                  variant="outlined"
                  prepend-inner-icon="mdi-folder-outline"
                  clearable
                  hint="Leave empty to include all projects"
                  persistent-hint
                ></v-text-field>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="closeCreateDialog" :disabled="saving">
            Cancel
          </v-btn>
          <v-btn
            color="primary"
            variant="elevated"
            @click="createPayroll"
            :loading="saving"
          >
            <v-icon start>mdi-check</v-icon>
            Create Payroll
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Confirm Action Dialog -->
    <v-dialog v-model="showConfirmDialog" max-width="500px" persistent>
      <v-card>
        <v-card-title class="text-h5 py-4">
          <v-icon start :color="confirmAction.color">{{
            confirmAction.icon
          }}</v-icon>
          {{ confirmAction.title }}
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-6">
          <p>{{ confirmAction.message }}</p>
          <v-alert
            v-if="confirmAction.type === 'warning'"
            type="warning"
            variant="tonal"
            class="mt-4"
          >
            This action cannot be undone. Please confirm to proceed.
          </v-alert>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="showConfirmDialog = false">
            Cancel
          </v-btn>
          <v-btn
            :color="confirmAction.color"
            variant="elevated"
            @click="confirmActionExecute"
            :loading="processing"
          >
            <v-icon start>{{ confirmAction.icon }}</v-icon>
            {{ confirmAction.buttonText }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>
<script setup>
import { ref, onMounted, computed } from "vue";
import { useRouter } from "vue-router";
import api from "@/services/api";
import { useToast } from "vue-toastification";

const router = useRouter();
const toast = useToast();

const payrolls = ref([]);
const loading = ref(false);
const saving = ref(false);
const processing = ref(false);
const showCreateDialog = ref(false);
const showConfirmDialog = ref(false);
const createForm = ref(null);

const filters = ref({
  status: null,
  year: null,
});

const newPayroll = ref({
  period_start_date: "",
  period_end_date: "",
  payment_date: "",
  pay_period_number: null,
  // Optional filters for targeted payroll generation
  project_id: null,
  contract_type: null,
  position_id: null,
  employee_ids: [],
});

const confirmAction = ref({
  type: "",
  title: "",
  message: "",
  buttonText: "",
  icon: "",
  color: "",
  payroll: null,
  action: null,
});

const headers = [
  { title: "Payroll #", key: "payroll_number", sortable: true },
  { title: "Period", key: "period", sortable: false },
  { title: "Payment Date", key: "payment_date", sortable: true },
  { title: "Employees", key: "employee_count", sortable: false },
  { title: "Net Pay", key: "total_net_pay", sortable: true },
  { title: "Status", key: "status", sortable: true },
  { title: "Actions", key: "actions", sortable: false, align: "center" },
];

const statusOptions = [
  { title: "Draft", value: "draft" },
  { title: "Processing", value: "processing" },
  { title: "Checked", value: "checked" },
  { title: "Recommended", value: "recommended" },
  { title: "Approved", value: "approved" },
  { title: "Paid", value: "paid" },
];

const yearOptions = computed(() => {
  const currentYear = new Date().getFullYear();
  const years = [];
  for (let i = currentYear; i >= currentYear - 5; i--) {
    years.push({ title: i.toString(), value: i });
  }
  return years;
});

const rules = {
  required: (v) => !!v || "This field is required",
  endDateAfterStart: (v) => {
    if (!newPayroll.value.period_start_date || !v) return true;
    return (
      new Date(v) >= new Date(newPayroll.value.period_start_date) ||
      "End date must be after start date"
    );
  },
};

onMounted(() => {
  fetchPayrolls();
});

async function fetchPayrolls() {
  loading.value = true;
  try {
    const params = {};
    if (filters.value.status) params.status = filters.value.status;
    if (filters.value.year) params.year = filters.value.year;

    const response = await api.get("/payroll", { params });
    payrolls.value = response.data.data || response.data;
  } catch (error) {
    console.error("Error fetching payrolls:", error);
    toast.error("Failed to load payroll records");
  } finally {
    loading.value = false;
  }
}

async function createPayroll() {
  const { valid } = await createForm.value.validate();
  if (!valid) {
    toast.warning("Please fill in all required fields");
    return;
  }

  saving.value = true;
  try {
    // Clean up payload - remove empty/null optional fields
    const payload = {
      period_start_date: newPayroll.value.period_start_date,
      period_end_date: newPayroll.value.period_end_date,
      payment_date: newPayroll.value.payment_date,
    };

    // Add optional fields only if they have values
    if (newPayroll.value.pay_period_number) {
      payload.pay_period_number = newPayroll.value.pay_period_number;
    }
    if (newPayroll.value.project_id) {
      payload.project_id = parseInt(newPayroll.value.project_id);
    }
    if (newPayroll.value.contract_type) {
      payload.contract_type = newPayroll.value.contract_type;
    }
    if (newPayroll.value.position_id) {
      payload.position_id = parseInt(newPayroll.value.position_id);
    }
    if (
      newPayroll.value.employee_ids &&
      newPayroll.value.employee_ids.length > 0
    ) {
      payload.employee_ids = newPayroll.value.employee_ids;
    }

    const response = await api.post("/payroll", payload);
    toast.success("Payroll period created successfully!");
    closeCreateDialog();
    await fetchPayrolls();

    // Navigate to the created payroll details
    router.push(`/payroll/${response.data.id}`);
  } catch (error) {
    console.error("Error creating payroll:", error);
    toast.error(
      error.response?.data?.message ||
        error.response?.data?.error ||
        "Failed to create payroll"
    );
  } finally {
    saving.value = false;
  }
}

function closeCreateDialog() {
  showCreateDialog.value = false;
  createForm.value?.reset();
  newPayroll.value = {
    period_start_date: "",
    period_end_date: "",
    payment_date: "",
    pay_period_number: null,
    project_id: null,
    contract_type: null,
    position_id: null,
    employee_ids: [],
  };
}

function viewPayroll(payroll) {
  router.push(`/payroll/${payroll.id}`);
}

function processPayroll(payroll) {
  confirmAction.value = {
    type: "info",
    title: "Process Payroll",
    message: `Process payroll for ${payroll.period_label}? This will calculate salaries for all employees based on their attendance records.`,
    buttonText: "Process",
    icon: "mdi-play-circle",
    color: "primary",
    payroll: payroll,
    action: "process",
  };
  showConfirmDialog.value = true;
}

function checkPayroll(payroll) {
  confirmAction.value = {
    type: "warning",
    title: "Check Payroll",
    message: `Mark this payroll as checked? This is the first approval step in the workflow.`,
    buttonText: "Check",
    icon: "mdi-check-circle",
    color: "warning",
    payroll: payroll,
    action: "check",
  };
  showConfirmDialog.value = true;
}

function recommendPayroll(payroll) {
  confirmAction.value = {
    type: "warning",
    title: "Recommend Payroll",
    message: `Recommend this payroll for approval? This is the second approval step.`,
    buttonText: "Recommend",
    icon: "mdi-thumb-up",
    color: "accent",
    payroll: payroll,
    action: "recommend",
  };
  showConfirmDialog.value = true;
}

function approvePayroll(payroll) {
  confirmAction.value = {
    type: "warning",
    title: "Approve Payroll",
    message: `Give final approval for this payroll? After approval, payments can be processed.`,
    buttonText: "Approve",
    icon: "mdi-check-decagram",
    color: "success",
    payroll: payroll,
    action: "approve",
  };
  showConfirmDialog.value = true;
}

function markAsPaid(payroll) {
  confirmAction.value = {
    type: "warning",
    title: "Mark as Paid",
    message: `Mark this payroll as paid? This indicates that all employees have been paid.`,
    buttonText: "Mark Paid",
    icon: "mdi-cash-check",
    color: "primary",
    payroll: payroll,
    action: "mark-paid",
  };
  showConfirmDialog.value = true;
}

async function confirmActionExecute() {
  processing.value = true;
  const payroll = confirmAction.value.payroll;
  const action = confirmAction.value.action;

  try {
    // If action is a function, execute it directly
    if (typeof action === "function") {
      await action();
    } else {
      // Otherwise, treat it as a string endpoint
      let endpoint = `/payroll/${payroll.id}/${action}`;
      const response = await api.post(endpoint);

      toast.success(response.data.message || "Action completed successfully");
      showConfirmDialog.value = false;
      await fetchPayrolls();
    }
  } catch (error) {
    console.error(`Error executing ${action}:`, error);
    toast.error(error.response?.data?.error || `Failed to execute action`);
  } finally {
    processing.value = false;
  }
}

async function exportPayroll(payroll, format) {
  try {
    toast.info(`Exporting payroll as ${format.toUpperCase()}...`);

    const response = await api.get(`/payroll/${payroll.id}/export-${format}`, {
      responseType: "blob",
    });

    // Create download link
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute(
      "download",
      `payroll-${payroll.payroll_number}.${format === "excel" ? "xlsx" : "pdf"}`
    );
    document.body.appendChild(link);
    link.click();
    link.remove();

    toast.success(`Payroll exported successfully!`);
  } catch (error) {
    console.error("Error exporting payroll:", error);
    toast.error("Failed to export payroll");
  }
}

function getStatusColor(status) {
  const colors = {
    draft: "grey",
    processing: "info",
    checked: "warning",
    recommended: "accent",
    approved: "success",
    paid: "primary",
  };
  return colors[status] || "grey";
}

function getStatusIcon(status) {
  const icons = {
    draft: "mdi-file-outline",
    processing: "mdi-cog",
    checked: "mdi-check-circle",
    recommended: "mdi-thumb-up",
    approved: "mdi-check-decagram",
    paid: "mdi-cash-check",
  };
  return icons[status] || "mdi-help-circle";
}

function getStatusLabel(status) {
  const labels = {
    draft: "Draft",
    processing: "Processing",
    checked: "Checked",
    recommended: "Recommended",
    approved: "Approved",
    paid: "Paid",
  };
  return labels[status] || status;
}

function formatDate(date) {
  if (!date) return "N/A";
  return new Date(date).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

function formatCurrency(amount) {
  if (!amount) return "0.00";
  return new Intl.NumberFormat("en-PH", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(amount);
}

function confirmDeletePayroll(payroll) {
  confirmAction.value = {
    type: "warning",
    title: "Delete Payroll Period",
    message: `Are you sure you want to delete payroll ${payroll.payroll_number}? This will permanently remove the payroll period and all associated data.`,
    buttonText: "Delete",
    icon: "mdi-delete-alert",
    color: "error",
    action: async () => {
      await api.delete(`/payroll/${payroll.id}`);
      toast.success("Payroll deleted successfully!");
      showConfirmDialog.value = false;
      await fetchPayrolls();
    },
  };
  showConfirmDialog.value = true;
}
</script>

<style scoped>
.v-data-table :deep(tbody tr:hover) {
  background-color: rgba(0, 0, 0, 0.02);
}
</style>
