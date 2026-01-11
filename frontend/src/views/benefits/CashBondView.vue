<template>
  <div>
    <div class="d-flex justify-space-between align-center mb-6">
      <h1 class="text-h4 font-weight-bold">Cash Bond Management</h1>
      <v-btn
        color="primary"
        prepend-icon="mdi-cash-plus"
        @click="openAddDialog"
        v-if="userRole !== 'employee'"
      >
        Add Cash Bond
      </v-btn>
    </div>

    <!-- Summary Cards -->
    <v-row class="mb-4">
      <v-col cols="12" md="3">
        <v-card color="primary" dark>
          <v-card-text>
            <div class="text-caption">Total Active Bonds</div>
            <div class="text-h4 font-weight-bold">{{ summary.active_count }}</div>
            <div class="text-caption">
              ₱{{ formatNumber(summary.active_total) }}
            </div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" md="3">
        <v-card color="warning" dark>
          <v-card-text>
            <div class="text-caption">Outstanding Balance</div>
            <div class="text-h4 font-weight-bold">
              ₱{{ formatNumber(summary.outstanding_balance) }}
            </div>
            <div class="text-caption">Across all employees</div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" md="3">
        <v-card color="success" dark>
          <v-card-text>
            <div class="text-caption">Completed Bonds</div>
            <div class="text-h4 font-weight-bold">{{ summary.completed_count }}</div>
            <div class="text-caption">
              ₱{{ formatNumber(summary.completed_total) }}
            </div>
          </v-card-text>
        </v-card>
      </v-col>
      <v-col cols="12" md="3">
        <v-card color="info" dark>
          <v-card-text>
            <div class="text-caption">Total Collected</div>
            <div class="text-h4 font-weight-bold">
              ₱{{ formatNumber(summary.total_collected) }}
            </div>
            <div class="text-caption">All time</div>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Filters -->
    <v-card class="mb-4">
      <v-card-text>
        <v-row>
          <v-col cols="12" md="4" v-if="userRole !== 'employee'">
            <v-autocomplete
              v-model="filters.employee_id"
              :items="employees"
              item-title="full_name"
              item-value="id"
              label="Filter by Employee"
              variant="outlined"
              density="compact"
              clearable
              @update:model-value="fetchCashBonds"
            ></v-autocomplete>
          </v-col>
          <v-col cols="12" :md="userRole === 'employee' ? 6 : 4">
            <v-select
              v-model="filters.status"
              :items="statusOptions"
              label="Filter by Status"
              variant="outlined"
              density="compact"
              clearable
              @update:model-value="fetchCashBonds"
            ></v-select>
          </v-col>
          <v-col cols="12" :md="userRole === 'employee' ? 6 : 4">
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

    <!-- Cash Bonds Table -->
    <v-card>
      <v-data-table
        :headers="headers"
        :items="cashBonds"
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

        <template v-slot:item.total_amount="{ item }">
          <span class="font-weight-bold text-primary"
            >₱{{ formatNumber(item.total_amount) }}</span
          >
        </template>

        <template v-slot:item.amount_per_cutoff="{ item }">
          <span class="font-weight-medium"
            >₱{{ formatNumber(item.amount_per_cutoff) }}</span
          >
        </template>

        <template v-slot:item.balance="{ item }">
          <div>
            <span
              :class="
                item.balance > 0
                  ? 'text-warning font-weight-bold'
                  : 'text-success font-weight-bold'
              "
            >
              ₱{{ formatNumber(item.balance) }}
            </span>
            <div class="text-caption text-medium-emphasis">
              Paid: ₱{{ formatNumber(item.total_amount - item.balance) }}
            </div>
          </div>
        </template>

        <template v-slot:item.progress="{ item }">
          <div class="d-flex align-center">
            <v-progress-linear
              :model-value="getProgress(item)"
              :color="item.status === 'completed' ? 'success' : 'primary'"
              height="10"
              rounded
              class="mr-2"
              style="min-width: 100px"
            ></v-progress-linear>
            <span class="text-caption font-weight-medium"
              >{{ item.installments_paid }}/{{ item.installments }}</span
            >
          </div>
        </template>

        <template v-slot:item.dates="{ item }">
          <div>
            <div class="text-caption">
              <strong>Start:</strong> {{ formatDate(item.start_date) }}
            </div>
            <div class="text-caption">
              <strong>End:</strong> {{ formatDate(item.end_date) }}
            </div>
          </div>
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
          <v-menu>
            <template v-slot:activator="{ props }">
              <v-btn
                icon="mdi-dots-vertical"
                variant="text"
                size="small"
                v-bind="props"
              ></v-btn>
            </template>
            <v-list>
              <v-list-item @click="viewDetails(item)">
                <v-list-item-title>
                  <v-icon size="small" class="mr-2">mdi-eye</v-icon>
                  View Details
                </v-list-item-title>
              </v-list-item>
              <v-list-item
                v-if="item.status === 'active' && userRole !== 'employee'"
                @click="openRefundDialog(item)"
              >
                <v-list-item-title>
                  <v-icon size="small" class="mr-2">mdi-cash-refund</v-icon>
                  Refund/Return
                </v-list-item-title>
              </v-list-item>
              <v-list-item
                v-if="item.status === 'active' && userRole !== 'employee'"
                @click="openEditDialog(item)"
              >
                <v-list-item-title>
                  <v-icon size="small" class="mr-2">mdi-pencil</v-icon>
                  Edit
                </v-list-item-title>
              </v-list-item>
              <v-divider v-if="userRole !== 'employee'"></v-divider>
              <v-list-item
                v-if="
                  userRole !== 'employee' &&
                  item.status !== 'completed' &&
                  item.installments_paid === 0
                "
                @click="confirmDelete(item)"
                class="text-error"
              >
                <v-list-item-title>
                  <v-icon size="small" class="mr-2">mdi-delete</v-icon>
                  Delete
                </v-list-item-title>
              </v-list-item>
            </v-list>
          </v-menu>
        </template>
      </v-data-table>
    </v-card>

    <!-- Add/Edit Cash Bond Dialog -->
    <v-dialog v-model="dialog" max-width="600px" persistent>
      <v-card>
        <v-card-title class="bg-primary">
          <span class="text-h6">{{
            editMode ? 'Edit Cash Bond' : 'Add Cash Bond'
          }}</span>
        </v-card-title>
        <v-card-text class="pt-4">
          <v-form ref="form" v-model="formValid">
            <v-autocomplete
              v-model="form.employee_id"
              :items="employees"
              item-title="full_name"
              item-value="id"
              label="Employee *"
              variant="outlined"
              :rules="[rules.required]"
              :disabled="editMode"
              class="mb-2"
            >
              <template v-slot:item="{ props, item }">
                <v-list-item v-bind="props">
                  <template v-slot:title>{{ item.raw.full_name }}</template>
                  <template v-slot:subtitle
                    >{{ item.raw.employee_number }} - {{ item.raw.position }}</template
                  >
                </v-list-item>
              </template>
            </v-autocomplete>

            <v-text-field
              v-model="form.total_amount"
              label="Total Cash Bond Amount *"
              variant="outlined"
              type="number"
              prefix="₱"
              :rules="[rules.required, rules.positiveNumber]"
              @input="calculateInstallments"
              class="mb-2"
            ></v-text-field>

            <v-text-field
              v-model="form.amount_per_cutoff"
              label="Amount per Cutoff *"
              variant="outlined"
              type="number"
              prefix="₱"
              :rules="[rules.required, rules.positiveNumber]"
              @input="calculateInstallments"
              class="mb-2"
              hint="This amount will be deducted every payroll period (semi-monthly)"
              persistent-hint
            ></v-text-field>

            <v-text-field
              v-model="form.installments"
              label="Number of Installments"
              variant="outlined"
              type="number"
              :rules="[rules.positiveNumber]"
              readonly
              class="mb-2"
              hint="Auto-calculated based on total amount and amount per cutoff"
              persistent-hint
            ></v-text-field>

            <v-text-field
              v-model="form.start_date"
              label="Start Date *"
              variant="outlined"
              type="date"
              :rules="[rules.required]"
              class="mb-2"
            ></v-text-field>

            <v-text-field
              v-model="form.reference_number"
              label="Reference Number"
              variant="outlined"
              placeholder="e.g., CB-2026-001"
              class="mb-2"
            ></v-text-field>

            <v-textarea
              v-model="form.description"
              label="Description"
              variant="outlined"
              rows="2"
              placeholder="Reason for cash bond"
              class="mb-2"
            ></v-textarea>

            <v-textarea
              v-model="form.notes"
              label="Additional Notes"
              variant="outlined"
              rows="2"
              class="mb-2"
            ></v-textarea>
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="closeDialog">Cancel</v-btn>
          <v-btn
            color="primary"
            :loading="saving"
            :disabled="!formValid"
            @click="saveCashBond"
          >
            {{ editMode ? 'Update' : 'Create' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Refund Dialog -->
    <v-dialog v-model="refundDialog" max-width="500px" persistent>
      <v-card>
        <v-card-title class="bg-success">
          <span class="text-h6">Refund/Return Cash Bond</span>
        </v-card-title>
        <v-card-text class="pt-4">
          <v-alert type="info" variant="tonal" class="mb-4">
            <div class="text-caption">Employee: <strong>{{ refundForm.employee_name }}</strong></div>
            <div class="text-caption">Total Bond: <strong>₱{{ formatNumber(refundForm.total_amount) }}</strong></div>
            <div class="text-caption">Already Deducted: <strong>₱{{ formatNumber(refundForm.amount_paid) }}</strong></div>
            <div class="text-caption">Current Balance: <strong>₱{{ formatNumber(refundForm.current_balance) }}</strong></div>
          </v-alert>

          <v-form ref="refundFormRef" v-model="refundFormValid">
            <v-text-field
              v-model="refundForm.refund_amount"
              label="Refund Amount *"
              variant="outlined"
              type="number"
              prefix="₱"
              :rules="[rules.required, rules.positiveNumber, rules.maxRefund]"
              class="mb-2"
              hint="Amount to return to employee (usually the remaining balance)"
              persistent-hint
            ></v-text-field>

            <v-text-field
              v-model="refundForm.refund_date"
              label="Refund Date *"
              variant="outlined"
              type="date"
              :rules="[rules.required]"
              class="mb-2"
            ></v-text-field>

            <v-textarea
              v-model="refundForm.refund_reason"
              label="Refund Reason"
              variant="outlined"
              rows="2"
              placeholder="e.g., Employee resignation, Contract completion"
              class="mb-2"
            ></v-textarea>
          </v-form>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="closeRefundDialog">Cancel</v-btn>
          <v-btn
            color="success"
            :loading="refunding"
            :disabled="!refundFormValid"
            @click="processRefund"
          >
            Process Refund
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Details Dialog -->
    <v-dialog v-model="detailsDialog" max-width="700px">
      <v-card v-if="selectedBond">
        <v-card-title class="bg-primary">
          <span class="text-h6">Cash Bond Details</span>
        </v-card-title>
        <v-card-text class="pt-4">
          <v-row>
            <v-col cols="12" md="6">
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">Employee</div>
                <div class="font-weight-medium">
                  {{ selectedBond.employee?.full_name }}
                </div>
                <div class="text-caption">
                  {{ selectedBond.employee?.employee_number }}
                </div>
              </div>
            </v-col>
            <v-col cols="12" md="6">
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">Status</div>
                <v-chip
                  :color="getStatusColor(selectedBond.status)"
                  size="small"
                  class="mt-1"
                >
                  {{ formatStatus(selectedBond.status) }}
                </v-chip>
              </div>
            </v-col>
            <v-col cols="12" md="6">
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">Total Amount</div>
                <div class="text-h6 text-primary">
                  ₱{{ formatNumber(selectedBond.total_amount) }}
                </div>
              </div>
            </v-col>
            <v-col cols="12" md="6">
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">
                  Amount per Cutoff
                </div>
                <div class="text-h6">
                  ₱{{ formatNumber(selectedBond.amount_per_cutoff) }}
                </div>
              </div>
            </v-col>
            <v-col cols="12" md="6">
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">Balance</div>
                <div
                  class="text-h6"
                  :class="
                    selectedBond.balance > 0 ? 'text-warning' : 'text-success'
                  "
                >
                  ₱{{ formatNumber(selectedBond.balance) }}
                </div>
              </div>
            </v-col>
            <v-col cols="12" md="6">
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">Progress</div>
                <div>
                  <v-progress-linear
                    :model-value="getProgress(selectedBond)"
                    :color="
                      selectedBond.status === 'completed' ? 'success' : 'primary'
                    "
                    height="15"
                    rounded
                    class="mb-1"
                  >
                    <template v-slot:default>
                      <span class="text-caption">{{ Math.round(getProgress(selectedBond)) }}%</span>
                    </template>
                  </v-progress-linear>
                  <div class="text-caption">
                    {{ selectedBond.installments_paid }} of
                    {{ selectedBond.installments }} installments paid
                  </div>
                </div>
              </div>
            </v-col>
            <v-col cols="12" md="6">
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">Start Date</div>
                <div>{{ formatDate(selectedBond.start_date) }}</div>
              </div>
            </v-col>
            <v-col cols="12" md="6">
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">End Date</div>
                <div>{{ formatDate(selectedBond.end_date) }}</div>
              </div>
            </v-col>
            <v-col cols="12" v-if="selectedBond.reference_number">
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">
                  Reference Number
                </div>
                <div>{{ selectedBond.reference_number }}</div>
              </div>
            </v-col>
            <v-col cols="12" v-if="selectedBond.description">
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">Description</div>
                <div>{{ selectedBond.description }}</div>
              </div>
            </v-col>
            <v-col cols="12" v-if="selectedBond.notes">
              <div class="mb-3">
                <div class="text-caption text-medium-emphasis">Notes</div>
                <div class="text-caption" style="white-space: pre-wrap">
                  {{ selectedBond.notes }}
                </div>
              </div>
            </v-col>
          </v-row>
        </v-card-text>
        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="detailsDialog = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Snackbar for notifications -->
    <v-snackbar v-model="snackbar" :color="snackbarColor" :timeout="3000">
      {{ snackbarMessage }}
    </v-snackbar>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import api from '@/services/api'

const authStore = useAuthStore()
const userRole = computed(() => authStore.user?.role)

// Data
const cashBonds = ref([])
const employees = ref([])
const loading = ref(false)
const dialog = ref(false)
const refundDialog = ref(false)
const detailsDialog = ref(false)
const editMode = ref(false)
const formValid = ref(false)
const refundFormValid = ref(false)
const saving = ref(false)
const refunding = ref(false)
const selectedBond = ref(null)

const snackbar = ref(false)
const snackbarMessage = ref('')
const snackbarColor = ref('success')

// Summary data
const summary = ref({
  active_count: 0,
  active_total: 0,
  outstanding_balance: 0,
  completed_count: 0,
  completed_total: 0,
  total_collected: 0,
})

// Filters
const filters = ref({
  employee_id: null,
  status: null,
})

const statusOptions = [
  { title: 'Active', value: 'active' },
  { title: 'Completed', value: 'completed' },
  { title: 'Cancelled', value: 'cancelled' },
]

// Form
const form = ref({
  employee_id: null,
  total_amount: null,
  amount_per_cutoff: null,
  installments: null,
  start_date: new Date().toISOString().substr(0, 10),
  reference_number: '',
  description: '',
  notes: '',
})

const refundForm = ref({
  deduction_id: null,
  employee_name: '',
  total_amount: 0,
  amount_paid: 0,
  current_balance: 0,
  refund_amount: 0,
  refund_date: new Date().toISOString().substr(0, 10),
  refund_reason: '',
})

// Validation rules
const rules = {
  required: (value) => !!value || 'Required',
  positiveNumber: (value) => value > 0 || 'Must be greater than 0',
  maxRefund: (value) =>
    value <= refundForm.value.current_balance ||
    'Cannot exceed current balance',
}

// Table headers
const headers = computed(() => {
  const baseHeaders = [
    { title: 'Employee', key: 'employee', sortable: true },
    { title: 'Total Amount', key: 'total_amount', sortable: true },
    { title: 'Per Cutoff', key: 'amount_per_cutoff', sortable: true },
    { title: 'Balance', key: 'balance', sortable: true },
    { title: 'Progress', key: 'progress', sortable: false },
    { title: 'Dates', key: 'dates', sortable: false },
    { title: 'Status', key: 'status', sortable: true },
    { title: 'Actions', key: 'actions', sortable: false, align: 'center' },
  ]

  if (userRole.value === 'employee') {
    return baseHeaders.filter((h) => h.key !== 'employee')
  }

  return baseHeaders
})

// Methods
const fetchCashBonds = async () => {
  loading.value = true
  try {
    const params = {}
    if (filters.value.employee_id) params.employee_id = filters.value.employee_id
    if (filters.value.status) params.status = filters.value.status

    const response = await api.get('/cash-bonds', { params })
    cashBonds.value = response.data.data
    calculateSummary()
  } catch (error) {
    showSnackbar('Failed to fetch cash bonds: ' + error.message, 'error')
  } finally {
    loading.value = false
  }
}

const fetchEmployees = async () => {
  try {
    const response = await api.get('/employees', {
      params: { per_page: 1000 },
    })
    employees.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch employees:', error)
  }
}

const calculateSummary = () => {
  const active = cashBonds.value.filter((b) => b.status === 'active')
  const completed = cashBonds.value.filter((b) => b.status === 'completed')

  summary.value = {
    active_count: active.length,
    active_total: active.reduce((sum, b) => sum + parseFloat(b.total_amount), 0),
    outstanding_balance: active.reduce((sum, b) => sum + parseFloat(b.balance), 0),
    completed_count: completed.length,
    completed_total: completed.reduce(
      (sum, b) => sum + parseFloat(b.total_amount),
      0
    ),
    total_collected: cashBonds.value.reduce(
      (sum, b) => sum + (parseFloat(b.total_amount) - parseFloat(b.balance)),
      0
    ),
  }
}

const calculateInstallments = () => {
  if (form.value.total_amount && form.value.amount_per_cutoff) {
    form.value.installments = Math.ceil(
      form.value.total_amount / form.value.amount_per_cutoff
    )
  }
}

const openAddDialog = () => {
  editMode.value = false
  resetForm()
  dialog.value = true
}

const openEditDialog = (bond) => {
  editMode.value = true
  form.value = {
    id: bond.id,
    employee_id: bond.employee_id,
    total_amount: bond.total_amount,
    amount_per_cutoff: bond.amount_per_cutoff,
    installments: bond.installments,
    start_date: bond.start_date,
    reference_number: bond.reference_number || '',
    description: bond.description || '',
    notes: bond.notes || '',
  }
  dialog.value = true
}

const openRefundDialog = (bond) => {
  refundForm.value = {
    deduction_id: bond.id,
    employee_name: bond.employee.full_name,
    total_amount: bond.total_amount,
    amount_paid: bond.total_amount - bond.balance,
    current_balance: bond.balance,
    refund_amount: bond.balance,
    refund_date: new Date().toISOString().substr(0, 10),
    refund_reason: '',
  }
  refundDialog.value = true
}

const closeDialog = () => {
  dialog.value = false
  resetForm()
}

const closeRefundDialog = () => {
  refundDialog.value = false
  refundForm.value = {
    deduction_id: null,
    employee_name: '',
    total_amount: 0,
    amount_paid: 0,
    current_balance: 0,
    refund_amount: 0,
    refund_date: new Date().toISOString().substr(0, 10),
    refund_reason: '',
  }
}

const resetForm = () => {
  form.value = {
    employee_id: null,
    total_amount: null,
    amount_per_cutoff: null,
    installments: null,
    start_date: new Date().toISOString().substr(0, 10),
    reference_number: '',
    description: '',
    notes: '',
  }
}

const saveCashBond = async () => {
  saving.value = true
  try {
    if (editMode.value) {
      await api.put(`/deductions/${form.value.id}`, form.value)
      showSnackbar('Cash bond updated successfully', 'success')
    } else {
      await api.post('/cash-bonds', form.value)
      showSnackbar('Cash bond created successfully', 'success')
    }
    closeDialog()
    fetchCashBonds()
  } catch (error) {
    showSnackbar(
      'Failed to save cash bond: ' +
        (error.response?.data?.message || error.message),
      'error'
    )
  } finally {
    saving.value = false
  }
}

const processRefund = async () => {
  refunding.value = true
  try {
    await api.post(
      `/deductions/${refundForm.value.deduction_id}/refund-cash-bond`,
      {
        refund_amount: refundForm.value.refund_amount,
        refund_date: refundForm.value.refund_date,
        refund_reason: refundForm.value.refund_reason,
      }
    )
    showSnackbar('Cash bond refunded successfully', 'success')
    closeRefundDialog()
    fetchCashBonds()
  } catch (error) {
    showSnackbar(
      'Failed to refund cash bond: ' +
        (error.response?.data?.message || error.message),
      'error'
    )
  } finally {
    refunding.value = false
  }
}

const viewDetails = (bond) => {
  selectedBond.value = bond
  detailsDialog.value = true
}

const confirmDelete = async (bond) => {
  if (
    confirm(
      `Are you sure you want to delete this cash bond for ${bond.employee.full_name}?`
    )
  ) {
    try {
      await api.delete(`/deductions/${bond.id}`)
      showSnackbar('Cash bond deleted successfully', 'success')
      fetchCashBonds()
    } catch (error) {
      showSnackbar(
        'Failed to delete cash bond: ' +
          (error.response?.data?.message || error.message),
        'error'
      )
    }
  }
}

const clearFilters = () => {
  filters.value = {
    employee_id: null,
    status: null,
  }
  fetchCashBonds()
}

const getProgress = (bond) => {
  if (bond.installments === 0) return 0
  return (bond.installments_paid / bond.installments) * 100
}

const getStatusColor = (status) => {
  const colors = {
    active: 'primary',
    completed: 'success',
    cancelled: 'error',
  }
  return colors[status] || 'grey'
}

const formatStatus = (status) => {
  return status.charAt(0).toUpperCase() + status.slice(1)
}

const formatNumber = (value) => {
  return parseFloat(value || 0).toLocaleString('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  })
}

const formatDate = (date) => {
  if (!date) return 'N/A'
  return new Date(date).toLocaleDateString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}

const showSnackbar = (message, color = 'success') => {
  snackbarMessage.value = message
  snackbarColor.value = color
  snackbar.value = true
}

// Lifecycle
onMounted(() => {
  fetchCashBonds()
  if (userRole.value !== 'employee') {
    fetchEmployees()
  }
})
</script>

<style scoped>
.v-card-title {
  color: white;
}
</style>
