<template>
  <v-container fluid>
    <v-card>
      <v-card-title class="d-flex align-center">
        <v-icon left color="primary">mdi-cash-multiple</v-icon>
        <span>13th Month Pay Management</span>
        <v-spacer></v-spacer>
        <v-btn color="primary" @click="openCalculateDialog">
          <v-icon left>mdi-calculator</v-icon>
          Calculate 13th Month Pay
        </v-btn>
      </v-card-title>

      <!-- Filters -->
      <v-card-text>
        <v-row>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.year"
              :items="yearOptions"
              label="Year"
              clearable
              dense
              outlined
              @update:model-value="fetchThirteenthMonth"
            ></v-select>
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.status"
              :items="statusOptions"
              label="Status"
              clearable
              dense
              outlined
              @update:model-value="fetchThirteenthMonth"
            ></v-select>
          </v-col>
        </v-row>
      </v-card-text>

      <!-- Data Table -->
      <v-data-table
        :headers="headers"
        :items="thirteenthMonthList"
        :loading="loading"
        class="elevation-1"
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
          {{ item.department || 'All Departments' }}
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

          <v-tooltip text="Download PDF" location="top">
            <template v-slot:activator="{ props }">
              <v-btn
                v-bind="props"
                icon="mdi-file-pdf-box"
                size="small"
                variant="text"
                color="error"
                @click="downloadPdf(item)"
              ></v-btn>
            </template>
          </v-tooltip>

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
    </v-card>

    <!-- Calculate Dialog -->
    <v-dialog v-model="calculateDialog" max-width="600px" persistent>
      <v-card>
        <v-card-title class="text-h5">
          Calculate 13th Month Pay
        </v-card-title>
        <v-card-text>
          <v-form ref="calculateForm" v-model="formValid">
            <v-select
              v-model="calculateForm.year"
              :items="yearOptions"
              label="Year *"
              :rules="[v => !!v || 'Year is required']"
              outlined
              dense
              class="mb-3"
            ></v-select>

            <v-select
              v-model="calculateForm.period"
              :items="periodOptions"
              label="Period *"
              :rules="[v => !!v || 'Period is required']"
              outlined
              dense
              class="mb-3"
            ></v-select>

            <v-select
              v-model="calculateForm.department"
              :items="departments"
              label="Department (Optional)"
              clearable
              outlined
              dense
              class="mb-3"
              hint="Leave empty to calculate for all departments"
              persistent-hint
            ></v-select>

            <v-text-field
              v-model="calculateForm.payment_date"
              label="Payment Date *"
              type="date"
              :rules="[v => !!v || 'Payment date is required']"
              outlined
              dense
              class="mb-3"
            ></v-text-field>
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn color="grey" variant="text" @click="calculateDialog = false">
            Cancel
          </v-btn>
          <v-btn
            color="primary"
            :loading="calculating"
            :disabled="!formValid"
            @click="calculate"
          >
            Calculate
          </v-btn>
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
                  <v-list-item-subtitle>{{ selectedItem.year }}</v-list-item-subtitle>
                </v-list-item>
                <v-list-item>
                  <v-list-item-title>Period:</v-list-item-title>
                  <v-list-item-subtitle>{{ formatPeriod(selectedItem.period) }}</v-list-item-subtitle>
                </v-list-item>
                <v-list-item>
                  <v-list-item-title>Department:</v-list-item-title>
                  <v-list-item-subtitle>{{ selectedItem.department || 'All Departments' }}</v-list-item-subtitle>
                </v-list-item>
              </v-list>
            </v-col>
            <v-col cols="12" md="6">
              <v-list dense>
                <v-list-item>
                  <v-list-item-title>Payment Date:</v-list-item-title>
                  <v-list-item-subtitle>{{ formatDate(selectedItem.payment_date) }}</v-list-item-subtitle>
                </v-list-item>
                <v-list-item>
                  <v-list-item-title>Total Amount:</v-list-item-title>
                  <v-list-item-subtitle>₱{{ formatNumber(selectedItem.total_amount) }}</v-list-item-subtitle>
                </v-list-item>
                <v-list-item>
                  <v-list-item-title>Status:</v-list-item-title>
                  <v-list-item-subtitle>
                    <v-chip :color="getStatusColor(selectedItem.status)" size="small">
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
              {{ item.employee?.full_name || 'N/A' }}
            </template>
            <template #[`item.department`]="{ item }">
              {{ item.employee?.department || 'N/A' }}
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
  </v-container>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import api from '@/services/api'

const formatDate = (date) => {
  if (!date) return 'N/A'
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

const formatNumber = (value) => {
  if (!value) return '0.00'
  return new Intl.NumberFormat('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(value)
}

const loading = ref(false)
const calculating = ref(false)
const calculateDialog = ref(false)
const detailsDialog = ref(false)
const formValid = ref(false)
const thirteenthMonthList = ref([])
const selectedItem = ref(null)
const departments = ref([])

const filters = ref({
  year: null,
  status: null
})

const calculateForm = ref({
  year: new Date().getFullYear(),
  period: 'full_year',
  department: null,
  payment_date: null
})

const snackbar = ref({
  show: false,
  message: '',
  color: 'success'
})

const headers = [
  { title: 'Batch Number', key: 'batch_number', sortable: true },
  { title: 'Year/Period', key: 'year_period', sortable: false },
  { title: 'Department', key: 'department', sortable: true },
  { title: 'Employees', key: 'employee_count', sortable: false },
  { title: 'Total Amount', key: 'total_amount', sortable: true },
  { title: 'Payment Date', key: 'payment_date', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, align: 'center' }
]

const detailHeaders = [
  { title: 'Employee', key: 'employee', sortable: true },
  { title: 'Department', key: 'department', sortable: true },
  { title: 'Total Basic Salary', key: 'total_basic_salary', sortable: true },
  { title: 'Net Pay', key: 'net_pay', sortable: true }
]

const statusOptions = [
  { title: 'All', value: null },
  { title: 'Draft', value: 'draft' },
  { title: 'Computed', value: 'computed' },
  { title: 'Approved', value: 'approved' },
  { title: 'Paid', value: 'paid' }
]

const periodOptions = [
  { title: 'Full Year', value: 'full_year' },
  { title: 'First Half (Jan-Jun)', value: 'first_half' },
  { title: 'Second Half (Jul-Dec)', value: 'second_half' }
]

const yearOptions = computed(() => {
  const currentYear = new Date().getFullYear()
  const years = []
  for (let i = currentYear; i >= currentYear - 5; i--) {
    years.push(i)
  }
  return years
})

const getStatusColor = (status) => {
  const colors = {
    draft: 'grey',
    computed: 'info',
    approved: 'success',
    paid: 'primary'
  }
  return colors[status] || 'grey'
}

const formatPeriod = (period) => {
  const periods = {
    full_year: 'Full Year',
    first_half: 'First Half',
    second_half: 'Second Half'
  }
  return periods[period] || period
}

const fetchThirteenthMonth = async () => {
  loading.value = true
  try {
    const params = {}
    if (filters.value.year) params.year = filters.value.year
    if (filters.value.status) params.status = filters.value.status

    const response = await api.get('/thirteenth-month', { params })
    thirteenthMonthList.value = response.data.data || response.data
  } catch (error) {
    console.error('Error fetching 13th month pay:', error)
    showSnackbar('Failed to fetch 13th month pay records', 'error')
  } finally {
    loading.value = false
  }
}

const fetchDepartments = async () => {
  try {
    const response = await api.get('/thirteenth-month/departments')
    departments.value = ['All Departments', ...response.data]
  } catch (error) {
    console.error('Error fetching departments:', error)
  }
}

const openCalculateDialog = () => {
  calculateForm.value = {
    year: new Date().getFullYear(),
    period: 'full_year',
    department: null,
    payment_date: null
  }
  calculateDialog.value = true
}

const calculate = async () => {
  calculating.value = true
  try {
    const payload = {
      year: calculateForm.value.year,
      period: calculateForm.value.period,
      payment_date: calculateForm.value.payment_date
    }
    
    if (calculateForm.value.department && calculateForm.value.department !== 'All Departments') {
      payload.department = calculateForm.value.department
    }

    const response = await api.post('/thirteenth-month/calculate', payload)
    showSnackbar(response.data.message || '13th month pay calculated successfully', 'success')
    calculateDialog.value = false
    fetchThirteenthMonth()
  } catch (error) {
    console.error('Error calculating 13th month pay:', error)
    showSnackbar(error.response?.data?.message || 'Failed to calculate 13th month pay', 'error')
  } finally {
    calculating.value = false
  }
}

const viewDetails = async (item) => {
  loading.value = true
  try {
    const response = await api.get(`/thirteenth-month/${item.id}`)
    selectedItem.value = response.data
    detailsDialog.value = true
  } catch (error) {
    console.error('Error fetching details:', error)
    showSnackbar('Failed to fetch details', 'error')
  } finally {
    loading.value = false
  }
}

const downloadPdf = async (item) => {
  try {
    const response = await api.get(`/thirteenth-month/${item.id}/export-pdf`, {
      responseType: 'blob'
    })
    
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `13th-month-pay-${item.batch_number}.pdf`)
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
    window.URL.revokeObjectURL(url)
    
    showSnackbar('PDF downloaded successfully', 'success')
  } catch (error) {
    console.error('Error downloading PDF:', error)
    showSnackbar('Failed to download PDF', 'error')
  }
}

const approve = async (item) => {
  if (!confirm('Are you sure you want to approve this 13th month pay?')) return
  
  try {
    const response = await api.post(`/thirteenth-month/${item.id}/approve`)
    showSnackbar(response.data.message || 'Approved successfully', 'success')
    fetchThirteenthMonth()
  } catch (error) {
    console.error('Error approving:', error)
    showSnackbar(error.response?.data?.message || 'Failed to approve', 'error')
  }
}

const markPaid = async (item) => {
  if (!confirm('Are you sure you want to mark this as paid?')) return
  
  try {
    const response = await api.post(`/thirteenth-month/${item.id}/mark-paid`)
    showSnackbar(response.data.message || 'Marked as paid successfully', 'success')
    fetchThirteenthMonth()
  } catch (error) {
    console.error('Error marking as paid:', error)
    showSnackbar(error.response?.data?.message || 'Failed to mark as paid', 'error')
  }
}

const deleteItem = async (item) => {
  if (!confirm(`Are you sure you want to delete batch ${item.batch_number}? This action cannot be undone.`)) return
  
  try {
    const response = await api.delete(`/thirteenth-month/${item.id}`)
    showSnackbar(response.data.message || 'Deleted successfully', 'success')
    fetchThirteenthMonth()
  } catch (error) {
    console.error('Error deleting:', error)
    showSnackbar(error.response?.data?.message || 'Failed to delete', 'error')
  }
}

const showSnackbar = (message, color = 'success') => {
  snackbar.value = {
    show: true,
    message,
    color
  }
}

onMounted(() => {
  fetchThirteenthMonth()
  fetchDepartments()
})
</script>

<style scoped>
.v-card-title {
  background: linear-gradient(90deg, #1976d2 0%, #1565c0 100%);
  color: white;
}
</style>
