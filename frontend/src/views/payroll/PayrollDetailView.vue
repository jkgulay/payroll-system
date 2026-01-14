<template>
  <div>
    <v-row class="mb-4" align="center">
      <v-col>
        <v-btn
          icon="mdi-arrow-left"
          variant="text"
          @click="$router.push('/payroll')"
          class="mr-2"
        ></v-btn>
        <h1 class="text-h4 font-weight-bold d-inline">Payroll Details</h1>
      </v-col>
      <v-col cols="auto">
        <v-btn
          color="primary"
          @click="openExportDialog"
          prepend-icon="mdi-file-pdf-box"
        >
          Export PDF
        </v-btn>
      </v-col>
    </v-row>

    <v-card v-if="loading" class="pa-8">
      <v-progress-circular
        indeterminate
        color="primary"
        class="mx-auto d-block"
      ></v-progress-circular>
    </v-card>

    <div v-else-if="payroll">
      <!-- Payroll Header Info -->
      <v-card class="mb-4">
        <v-card-title class="d-flex align-center">
          <span>{{ payroll.payroll_number }}</span>
          <v-spacer></v-spacer>
          <v-chip :color="getStatusColor(payroll.status)" variant="flat">
            {{ getStatusLabel(payroll.status) }}
          </v-chip>
        </v-card-title>
        <v-card-text>
          <v-row>
            <v-col cols="12" md="3">
              <div class="text-caption text-medium-emphasis">Period</div>
              <div class="text-body-1 font-weight-medium">
                {{ formatDate(payroll.period_start) }} -
                {{ formatDate(payroll.period_end) }}
              </div>
            </v-col>
            <v-col cols="12" md="3">
              <div class="text-caption text-medium-emphasis">Payment Date</div>
              <div class="text-body-1 font-weight-medium">
                {{ formatDate(payroll.payment_date) }}
              </div>
            </v-col>
            <v-col cols="12" md="2">
              <div class="text-caption text-medium-emphasis">Employees</div>
              <div class="text-h6 font-weight-bold">
                {{ payroll.payroll_items?.length || 0 }}
              </div>
            </v-col>
            <v-col cols="12" md="4">
              <div class="text-caption text-medium-emphasis">Total Net Pay</div>
              <div class="text-h6 font-weight-bold text-success">
                {{ formatCurrency(payroll.total_net_pay) }}
              </div>
            </v-col>
          </v-row>
        </v-card-text>
      </v-card>

      <!-- Quick Actions -->
      <v-card class="mb-4">
        <v-card-text>
          <v-btn-group divided variant="outlined" class="w-100">
            <v-btn
              v-if="payroll.status === 'draft'"
              @click="showProcessDialog = true"
              prepend-icon="mdi-play-circle"
              color="primary"
            >
              Process Payroll
            </v-btn>
            <v-btn @click="openExportDialog" prepend-icon="mdi-file-pdf-box">
              Export PDF
            </v-btn>
            <v-btn
              @click="$router.push('/payroll')"
              prepend-icon="mdi-arrow-left"
            >
              Back to List
            </v-btn>
          </v-btn-group>
        </v-card-text>
      </v-card>

      <!-- Summary Cards -->
      <v-row class="mb-4">
        <v-col cols="12" md="4">
          <v-card>
            <v-card-text>
              <div class="text-caption text-medium-emphasis">Gross Pay</div>
              <div class="text-h5 font-weight-bold">
                {{ formatCurrency(payroll.total_gross_pay) }}
              </div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="4">
          <v-card>
            <v-card-text>
              <div class="text-caption text-medium-emphasis">
                Total Deductions
              </div>
              <div class="text-h5 font-weight-bold text-error">
                {{ formatCurrency(payroll.total_deductions) }}
              </div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="4">
          <v-card>
            <v-card-text>
              <div class="text-caption text-medium-emphasis">Net Pay</div>
              <div class="text-h5 font-weight-bold text-success">
                {{ formatCurrency(payroll.total_net_pay) }}
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>
    </div>

    <!-- Export Payroll Dialog -->
    <ExportPayrollDialog
      v-model="showExportDialog"
      :payroll-id="payrollId"
      @exported="handleExported"
    />

    <!-- Process Payroll Confirmation Dialog -->
    <v-dialog v-model="showProcessDialog" max-width="500px" persistent>
      <v-card>
        <v-card-title class="text-h5 py-4">
          <v-icon start color="primary">mdi-play-circle</v-icon>
          Process Payroll
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-6">
          <p>
            Process payroll for <strong>{{ payroll?.period_label }}</strong
            >?
          </p>
          <p class="text-body-2 text-medium-emphasis mt-2">
            This will calculate salaries for all employees based on their
            attendance records.
          </p>
          <v-alert type="info" variant="tonal" class="mt-4" density="compact">
            Make sure all attendance records are complete before processing.
          </v-alert>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn
            variant="text"
            @click="showProcessDialog = false"
            :disabled="processing"
          >
            Cancel
          </v-btn>
          <v-btn
            color="primary"
            variant="elevated"
            @click="processPayroll"
            :loading="processing"
          >
            <v-icon start>mdi-play-circle</v-icon>
            Process
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRoute } from "vue-router";
import ExportPayrollDialog from "@/components/payroll/ExportPayrollDialog.vue";
import { useToast } from "vue-toastification";
import api from "@/services/api";

const route = useRoute();
const toast = useToast();

const payrollId = computed(() => parseInt(route.params.id));
const showExportDialog = ref(false);
const showProcessDialog = ref(false);
const loading = ref(false);
const processing = ref(false);
const payroll = ref(null);

const openExportDialog = () => {
  showExportDialog.value = true;
};

const handleExported = () => {
  toast.success("Payroll exported successfully");
};

const processPayroll = async () => {
  processing.value = true;
  try {
    const response = await api.post(`/payroll/${payrollId.value}/process`);
    toast.success(response.data.message || "Payroll processed successfully");
    showProcessDialog.value = false;
    await fetchPayrollDetails();
  } catch (error) {
    console.error("Error processing payroll:", error);
    toast.error(error.response?.data?.error || "Failed to process payroll");
  } finally {
    processing.value = false;
  }
};

const fetchPayrollDetails = async () => {
  loading.value = true;
  try {
    const response = await api.get(`/payroll/${payrollId.value}`);
    payroll.value = response.data;
  } catch (error) {
    console.error("Error fetching payroll:", error);
    toast.error("Failed to load payroll details");
  } finally {
    loading.value = false;
  }
};

const formatDate = (date) => {
  if (!date) return "-";
  return new Date(date).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
};

const formatCurrency = (amount) => {
  if (!amount) return "₱0.00";
  return new Intl.NumberFormat("en-PH", {
    style: "currency",
    currency: "PHP",
  }).format(amount);
};

const getStatusColor = (status) => {
  const colors = {
    draft: "grey",
    processing: "info",
    checked: "warning",
    recommended: "accent",
    approved: "success",
    paid: "primary",
  };
  return colors[status] || "grey";
};

const getStatusLabel = (status) => {
  return status ? status.charAt(0).toUpperCase() + status.slice(1) : "Unknown";
};

onMounted(() => {
  fetchPayrollDetails();
});
</script>
