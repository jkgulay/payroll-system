<template>
  <v-container fluid class="pa-6">
    <v-row v-if="loading" class="fill-height" align-content="center" justify="center">
      <v-col class="text-center">
        <v-progress-circular indeterminate color="primary" :size="70"></v-progress-circular>
      </v-col>
    </v-row>

    <div v-else>
      <!-- Header -->
      <v-row class="mb-4">
        <v-col cols="12">
          <v-btn
            variant="text"
            prepend-icon="mdi-arrow-left"
            @click="$router.push('/payroll')"
            class="mb-4"
          >
            Back to Payroll List
          </v-btn>

          <div class="d-flex justify-space-between align-center">
            <div>
              <h1 class="text-h4 font-weight-bold mb-2">
                {{ payroll?.payroll_number }}
              </h1>
              <p class="text-subtitle-1 text-medium-emphasis">
                {{ payroll?.period_name }}
              </p>
            </div>
            <div class="d-flex gap-2">
              <v-chip
                :color="getStatusColor(payroll?.status)"
                size="large"
                variant="flat"
              >
                {{ payroll?.status?.toUpperCase() }}
              </v-chip>
              <v-btn
                v-if="payroll?.status === 'draft'"
                color="success"
                prepend-icon="mdi-check-circle"
                @click="finalizePayroll"
                :loading="finalizing"
              >
                Finalize Payroll
              </v-btn>
              <v-btn
                color="primary"
                prepend-icon="mdi-download"
                @click="downloadRegister"
              >
                Download Register
              </v-btn>
            </div>
          </div>
        </v-col>
      </v-row>

      <!-- Payroll Info Cards -->
      <v-row class="mb-4">
        <v-col cols="12" md="3">
          <v-card>
            <v-card-text>
              <div class="text-overline mb-1">Period</div>
              <div class="text-body-1">
                {{ formatDate(payroll?.period_start) }} - {{ formatDate(payroll?.period_end) }}
              </div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card>
            <v-card-text>
              <div class="text-overline mb-1">Payment Date</div>
              <div class="text-body-1">{{ formatDate(payroll?.payment_date) }}</div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="2">
          <v-card>
            <v-card-text>
              <div class="text-overline mb-1">Employees</div>
              <div class="text-h6 font-weight-bold">{{ payroll?.items?.length || 0 }}</div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="2">
          <v-card>
            <v-card-text>
              <div class="text-overline mb-1">Total Gross</div>
              <div class="text-h6 font-weight-bold text-info">
                ₱{{ formatCurrency(payroll?.total_gross) }}
              </div>
            </v-card-text>
          </v-card>
        </v-col>
        <v-col cols="12" md="2">
          <v-card>
            <v-card-text>
              <div class="text-overline mb-1">Total Net</div>
              <div class="text-h6 font-weight-bold text-success">
                ₱{{ formatCurrency(payroll?.total_net) }}
              </div>
            </v-card-text>
          </v-card>
        </v-col>
      </v-row>

      <!-- Employee Payroll Items -->
      <v-card>
        <v-card-title class="d-flex align-center">
          <v-icon icon="mdi-account-group" class="mr-2"></v-icon>
          Employee Payroll Details
          <v-spacer></v-spacer>
          <v-text-field
            v-model="search"
            prepend-inner-icon="mdi-magnify"
            label="Search employee..."
            single-line
            hide-details
            density="compact"
            style="max-width: 300px"
          ></v-text-field>
        </v-card-title>

        <v-data-table
          :headers="headers"
          :items="payroll?.items || []"
          :search="search"
          :items-per-page="15"
          class="elevation-1"
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
              <div>₱{{ formatCurrency(item.rate) }}</div>
              <div class="text-caption">{{ item.days_worked }} days</div>
            </div>
          </template>

          <!-- Basic Pay -->
          <template v-slot:item.basic_pay="{ item }">
            <div class="text-right">₱{{ formatCurrency(item.basic_pay) }}</div>
          </template>

          <!-- Overtime -->
          <template v-slot:item.overtime="{ item }">
            <div>
              <div v-if="item.regular_ot_hours > 0" class="text-caption">
                {{ item.regular_ot_hours }}h: ₱{{ formatCurrency(item.regular_ot_pay) }}
              </div>
              <div v-else class="text-caption text-medium-emphasis">-</div>
            </div>
          </template>

          <!-- Gross Pay -->
          <template v-slot:item.gross_pay="{ item }">
            <div class="text-right font-weight-bold text-info">
              ₱{{ formatCurrency(item.gross_pay) }}
            </div>
          </template>

          <!-- Deductions -->
          <template v-slot:item.deductions="{ item }">
            <div class="text-caption">
              <div>SSS: ₱{{ formatCurrency(item.sss) }}</div>
              <div>PhilHealth: ₱{{ formatCurrency(item.philhealth) }}</div>
              <div>Pag-IBIG: ₱{{ formatCurrency(item.pagibig) }}</div>
              <div v-if="item.loans > 0">Loans: ₱{{ formatCurrency(item.loans) }}</div>
            </div>
          </template>

          <!-- Net Pay -->
          <template v-slot:item.net_pay="{ item }">
            <div class="text-right font-weight-bold text-success">
              ₱{{ formatCurrency(item.net_pay) }}
            </div>
          </template>

          <!-- Actions -->
          <template v-slot:item.actions="{ item }">
            <v-btn
              icon="mdi-download"
              size="small"
              variant="text"
              color="primary"
              @click="downloadPayslip(item)"
            >
            </v-btn>
          </template>
        </v-data-table>
      </v-card>
    </div>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useToast } from 'vue-toastification';
import api from '@/services/api';

const route = useRoute();
const router = useRouter();
const toast = useToast();

const loading = ref(false);
const finalizing = ref(false);
const search = ref('');
const payroll = ref(null);

const headers = [
  { title: 'Employee', key: 'employee', sortable: true },
  { title: 'Rate & Days', key: 'rate_days', sortable: false },
  { title: 'Basic Pay', key: 'basic_pay', sortable: true, align: 'end' },
  { title: 'Overtime', key: 'overtime', sortable: false },
  { title: 'Gross Pay', key: 'gross_pay', sortable: true, align: 'end' },
  { title: 'Deductions', key: 'deductions', sortable: false },
  { title: 'Net Pay', key: 'net_pay', sortable: true, align: 'end' },
  { title: 'Actions', key: 'actions', sortable: false, align: 'center' },
];

onMounted(() => {
  fetchPayroll();
});

async function fetchPayroll() {
  loading.value = true;
  try {
    const response = await api.get(`/payrolls/${route.params.id}`);
    payroll.value = response.data;
  } catch (error) {
    console.error('Error fetching payroll:', error);
    toast.error('Failed to load payroll details');
    router.push('/payroll');
  } finally {
    loading.value = false;
  }
}

async function finalizePayroll() {
  if (!confirm('Are you sure you want to finalize this payroll? You will not be able to edit it after finalization.')) {
    return;
  }

  finalizing.value = true;
  try {
    await api.post(`/payrolls/${payroll.value.id}/finalize`);
    toast.success('Payroll finalized successfully');
    await fetchPayroll();
  } catch (error) {
    console.error('Error finalizing payroll:', error);
    toast.error('Failed to finalize payroll');
  } finally {
    finalizing.value = false;
  }
}

async function downloadRegister() {
  try {
    const response = await api.get(`/payrolls/${payroll.value.id}/download-register`, {
      responseType: 'blob',
    });

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `payroll_register_${payroll.value.payroll_number}.pdf`);
    document.body.appendChild(link);
    link.click();
    link.remove();

    toast.success('Payroll register downloaded');
  } catch (error) {
    console.error('Error downloading register:', error);
    toast.error('Failed to download payroll register');
  }
}

async function downloadPayslip(item) {
  try {
    const response = await api.get(
      `/payrolls/${payroll.value.id}/employees/${item.employee_id}/download-payslip`,
      { responseType: 'blob' }
    );

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement('a');
    link.href = url;
    link.setAttribute('download', `payslip_${item.employee.employee_number}.pdf`);
    document.body.appendChild(link);
    link.click();
    link.remove();

    toast.success('Payslip downloaded');
  } catch (error) {
    console.error('Error downloading payslip:', error);
    toast.error('Failed to download payslip');
  }
}

function getStatusColor(status) {
  const colors = {
    draft: 'warning',
    finalized: 'info',
    paid: 'success',
  };
  return colors[status] || 'grey';
}

function formatDate(date) {
  if (!date) return '';
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  });
}

function formatCurrency(amount) {
  if (!amount) return '0.00';
  return parseFloat(amount).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}
</script>

<style scoped>
.v-card {
  border-radius: 12px;
}
.gap-2 {
  gap: 8px;
}
</style>
