<template>
  <v-container fluid class="pa-6">
    <div class="mb-6">
      <h1 class="text-h4 font-weight-bold mb-2">Resignation Management</h1>
      <p class="text-body-1 text-medium-emphasis">
        Manage employee resignation requests and process final payments
      </p>
    </div>

    <!-- Filters and Actions -->
    <v-card elevation="2" class="mb-4">
      <v-card-text class="pa-4">
        <v-row>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.status"
              :items="statusOptions"
              label="Status"
              variant="outlined"
              density="compact"
              @update:model-value="loadResignations"
            ></v-select>
          </v-col>

          <v-col cols="12" md="3">
            <v-text-field
              v-model="filters.search"
              label="Search Employee"
              variant="outlined"
              density="compact"
              prepend-inner-icon="mdi-magnify"
              @keyup.enter="loadResignations"
              clearable
            ></v-text-field>
          </v-col>

          <v-col cols="12" md="2">
            <v-btn color="primary" block @click="loadResignations" :loading="loading">
              <v-icon left>mdi-refresh</v-icon>
              Refresh
            </v-btn>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Resignations Table -->
    <v-card elevation="2">
      <v-card-text class="pa-0">
        <v-data-table
          :headers="headers"
          :items="resignations"
          :loading="loading"
          :items-per-page="15"
          class="elevation-0"
        >
          <!-- Employee -->
          <template v-slot:item.employee="{ item }">
            <div class="py-2">
              <div class="font-weight-bold">{{ item.employee.full_name }}</div>
              <div class="text-caption text-medium-emphasis">
                {{ item.employee.employee_number }}
              </div>
            </div>
          </template>

          <!-- Position -->
          <template v-slot:item.position="{ item }">
            <div class="py-2">
              <div>{{ item.employee.position }}</div>
              <div class="text-caption text-medium-emphasis">
                {{ item.employee.project?.name }}
              </div>
            </div>
          </template>

          <!-- Status -->
          <template v-slot:item.status="{ item }">
            <v-chip :color="getStatusColor(item.status)" size="small">
              {{ item.status_label }}
            </v-chip>
          </template>

          <!-- Dates -->
          <template v-slot:item.resignation_date="{ item }">
            {{ formatDate(item.resignation_date) }}
          </template>

          <template v-slot:item.last_working_day="{ item }">
            <div>{{ formatDate(item.last_working_day) }}</div>
            <div v-if="item.effective_date && item.effective_date !== item.last_working_day" class="text-caption text-warning">
              Modified: {{ formatDate(item.effective_date) }}
            </div>
          </template>

          <!-- Days Remaining -->
          <template v-slot:item.days_remaining="{ item }">
            <v-chip 
              v-if="item.days_remaining !== null && item.status === 'approved'"
              :color="item.days_remaining > 7 ? 'success' : 'warning'" 
              size="small"
            >
              {{ item.days_remaining }} days
            </v-chip>
            <span v-else>-</span>
          </template>

          <!-- Final Pay -->
          <template v-slot:item.final_pay="{ item }">
            <div v-if="item.final_pay_amount">
              <div class="font-weight-bold">₱{{ formatCurrency(item.final_pay_amount) }}</div>
              <v-chip 
                v-if="item.final_pay_released" 
                color="success" 
                size="x-small" 
                class="mt-1"
              >
                Released
              </v-chip>
              <v-chip 
                v-else 
                color="warning" 
                size="x-small" 
                class="mt-1"
              >
                Pending
              </v-chip>
            </div>
            <span v-else class="text-medium-emphasis">Not calculated</span>
          </template>

          <!-- Actions -->
          <template v-slot:item.actions="{ item }">
            <div class="d-flex gap-2">
              <v-btn
                icon
                size="small"
                variant="text"
                color="info"
                @click="viewDetails(item)"
              >
                <v-icon>mdi-eye</v-icon>
                <v-tooltip activator="parent" location="top">View Details</v-tooltip>
              </v-btn>

              <v-btn
                v-if="item.status === 'pending'"
                icon
                size="small"
                variant="text"
                color="success"
                @click="openApproveDialog(item)"
              >
                <v-icon>mdi-check</v-icon>
                <v-tooltip activator="parent" location="top">Approve</v-tooltip>
              </v-btn>

              <v-btn
                v-if="item.status === 'pending'"
                icon
                size="small"
                variant="text"
                color="error"
                @click="openRejectDialog(item)"
              >
                <v-icon>mdi-close</v-icon>
                <v-tooltip activator="parent" location="top">Reject</v-tooltip>
              </v-btn>

              <v-btn
                v-if="item.status === 'approved' && !item.final_pay_amount"
                icon
                size="small"
                variant="text"
                color="primary"
                @click="openFinalPayDialog(item)"
              >
                <v-icon>mdi-calculator</v-icon>
                <v-tooltip activator="parent" location="top">Calculate Final Pay</v-tooltip>
              </v-btn>

              <v-btn
                v-if="item.status === 'approved' && item.final_pay_amount && !item.final_pay_released"
                icon
                size="small"
                variant="text"
                color="success"
                @click="openReleaseDialog(item)"
              >
                <v-icon>mdi-cash-check</v-icon>
                <v-tooltip activator="parent" location="top">Release Final Pay</v-tooltip>
              </v-btn>
            </div>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>

    <!-- View Details Dialog -->
    <v-dialog v-model="showDetailsDialog" max-width="700">
      <v-card v-if="selectedResignation">
        <v-card-title class="pa-6">
          <v-icon left :color="getStatusColor(selectedResignation.status)">
            {{ getStatusIcon(selectedResignation.status) }}
          </v-icon>
          Resignation Details
        </v-card-title>

        <v-divider></v-divider>

        <v-card-text class="pa-6">
          <v-row>
            <v-col cols="12" sm="6">
              <div class="mb-4">
                <div class="text-caption text-medium-emphasis mb-1">Employee</div>
                <div class="text-body-1 font-weight-bold">{{ selectedResignation.employee.full_name }}</div>
                <div class="text-caption">{{ selectedResignation.employee.employee_number }}</div>
              </div>
            </v-col>

            <v-col cols="12" sm="6">
              <div class="mb-4">
                <div class="text-caption text-medium-emphasis mb-1">Position</div>
                <div class="text-body-1">{{ selectedResignation.employee.position }}</div>
              </div>
            </v-col>

            <v-col cols="12" sm="6">
              <div class="mb-4">
                <div class="text-caption text-medium-emphasis mb-1">Resignation Date</div>
                <div class="text-body-1">{{ formatDate(selectedResignation.resignation_date) }}</div>
              </div>
            </v-col>

            <v-col cols="12" sm="6">
              <div class="mb-4">
                <div class="text-caption text-medium-emphasis mb-1">Requested Last Day</div>
                <div class="text-body-1">{{ formatDate(selectedResignation.last_working_day) }}</div>
              </div>
            </v-col>

            <v-col cols="12" sm="6" v-if="selectedResignation.effective_date">
              <div class="mb-4">
                <div class="text-caption text-medium-emphasis mb-1">Effective Last Day</div>
                <div class="text-body-1 font-weight-bold text-success">
                  {{ formatDate(selectedResignation.effective_date) }}
                </div>
              </div>
            </v-col>

            <v-col cols="12" v-if="selectedResignation.reason">
              <v-divider class="my-2"></v-divider>
              <div class="mb-4">
                <div class="text-caption text-medium-emphasis mb-1">Reason</div>
                <div class="text-body-1">{{ selectedResignation.reason }}</div>
              </div>
            </v-col>

            <!-- Attachments Display -->
            <v-col cols="12" v-if="selectedResignation.attachments && selectedResignation.attachments.length > 0">
              <v-divider class="my-2"></v-divider>
              <div class="mb-4">
                <div class="text-caption text-medium-emphasis mb-2">Attachments</div>
                <div class="d-flex flex-wrap ga-2">
                  <v-chip
                    v-for="(attachment, index) in selectedResignation.attachments"
                    :key="index"
                    color="primary"
                    variant="outlined"
                    :prepend-icon="getFileIcon(attachment.mime_type)"
                    @click="viewAttachment(selectedResignation.id, index, attachment)"
                  >
                    {{ attachment.original_name }}
                    <template v-slot:append>
                      <v-icon size="small">mdi-eye</v-icon>
                    </template>
                  </v-chip>
                </div>
              </div>
            </v-col>

            <v-col cols="12" v-if="selectedResignation.remarks">
              <v-divider class="my-2"></v-divider>
              <div class="mb-4">
                <div class="text-caption text-medium-emphasis mb-1">HR Remarks</div>
                <div class="text-body-1">{{ selectedResignation.remarks }}</div>
              </div>
            </v-col>

            <v-col cols="12" v-if="selectedResignation.processed_by">
              <v-divider class="my-2"></v-divider>
              <div class="mb-4">
                <div class="text-caption text-medium-emphasis mb-1">Processed By</div>
                <div class="text-body-1">
                  {{ selectedResignation.processed_by.name }} on {{ formatDate(selectedResignation.processed_at) }}
                </div>
              </div>
            </v-col>

            <v-col cols="12" v-if="selectedResignation.final_pay_amount">
              <v-divider class="my-2"></v-divider>
              <div class="text-subtitle-1 font-weight-bold mb-3">Final Pay Details</div>
              
              <v-row>
                <v-col cols="12" sm="6">
                  <div class="mb-2">
                    <div class="text-caption text-medium-emphasis">13th Month Pay</div>
                    <div class="text-h6 text-success">₱{{ formatCurrency(selectedResignation.thirteenth_month_amount) }}</div>
                  </div>
                </v-col>

                <v-col cols="12" sm="6">
                  <div class="mb-2">
                    <div class="text-caption text-medium-emphasis">Total Final Pay</div>
                    <div class="text-h6 text-primary">₱{{ formatCurrency(selectedResignation.final_pay_amount) }}</div>
                  </div>
                </v-col>

                <v-col cols="12">
                  <v-chip 
                    :color="selectedResignation.final_pay_released ? 'success' : 'warning'"
                    class="mt-2"
                  >
                    {{ selectedResignation.final_pay_released ? 'Final Pay Released' : 'Final Pay Pending' }}
                  </v-chip>
                  <div v-if="selectedResignation.final_pay_released" class="text-caption mt-1">
                    Released on: {{ formatDate(selectedResignation.final_pay_release_date) }}
                  </div>
                </v-col>
              </v-row>
            </v-col>
          </v-row>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="showDetailsDialog = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Approve Dialog -->
    <v-dialog v-model="showApproveDialog" max-width="600">
      <v-card>
        <v-card-title class="pa-6">
          <v-icon left color="success">mdi-check-circle</v-icon>
          Approve Resignation
        </v-card-title>

        <v-divider></v-divider>

        <v-card-text class="pa-6">
          <v-form ref="approveForm" v-model="approveFormValid">
            <v-alert type="info" variant="tonal" class="mb-4">
              Approving resignation for: <strong>{{ selectedResignation?.employee.full_name }}</strong>
            </v-alert>

            <v-text-field
              v-model="approveData.effective_date"
              label="Effective Last Working Day"
              type="date"
              variant="outlined"
              :hint="`Requested: ${formatDate(selectedResignation?.last_working_day)}`"
              persistent-hint
              class="mb-4"
            ></v-text-field>

            <v-textarea
              v-model="approveData.remarks"
              label="Remarks (Optional)"
              variant="outlined"
              rows="3"
              counter="1000"
            ></v-textarea>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="showApproveDialog = false">Cancel</v-btn>
          <v-btn 
            color="success" 
            @click="approveResignation" 
            :loading="processing"
            :disabled="!approveFormValid"
          >
            Approve
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Reject Dialog -->
    <v-dialog v-model="showRejectDialog" max-width="600">
      <v-card>
        <v-card-title class="pa-6">
          <v-icon left color="error">mdi-close-circle</v-icon>
          Reject Resignation
        </v-card-title>

        <v-divider></v-divider>

        <v-card-text class="pa-6">
          <v-form ref="rejectForm" v-model="rejectFormValid">
            <v-alert type="warning" variant="tonal" class="mb-4">
              Rejecting resignation for: <strong>{{ selectedResignation?.employee.full_name }}</strong>
            </v-alert>

            <v-textarea
              v-model="rejectData.remarks"
              label="Rejection Reason *"
              variant="outlined"
              rows="4"
              :rules="[v => !!v || 'Reason is required']"
              counter="1000"
            ></v-textarea>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="showRejectDialog = false">Cancel</v-btn>
          <v-btn 
            color="error" 
            @click="rejectResignation" 
            :loading="processing"
            :disabled="!rejectFormValid"
          >
            Reject
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Calculate Final Pay Dialog -->
    <v-dialog v-model="showFinalPayDialog" max-width="600">
      <v-card>
        <v-card-title class="pa-6">
          <v-icon left color="primary">mdi-calculator</v-icon>
          Calculate Final Pay
        </v-card-title>

        <v-divider></v-divider>

        <v-card-text class="pa-6">
          <v-alert type="info" variant="tonal" class="mb-4">
            <div class="mb-2">
              <strong>Employee:</strong> {{ selectedResignation?.employee.full_name }}
            </div>
            <div>
              <strong>Last Working Day:</strong> {{ formatDate(selectedResignation?.effective_date) }}
            </div>
          </v-alert>

          <v-form ref="finalPayForm">
            <v-text-field
              v-model.number="finalPayData.remaining_salary"
              label="Additional Amount (Remaining Salary, etc.)"
              type="number"
              variant="outlined"
              prefix="₱"
              :rules="[v => v >= 0 || 'Must be positive']"
              hint="Any additional amounts to include in final pay"
              persistent-hint
            ></v-text-field>
          </v-form>

          <v-alert type="warning" variant="tonal" class="mt-4">
            The system will automatically calculate:
            <ul class="mt-2">
              <li>Pro-rated 13th month pay</li>
              <li>Unused leave credits (if applicable)</li>
            </ul>
          </v-alert>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="showFinalPayDialog = false">Cancel</v-btn>
          <v-btn 
            color="primary" 
            @click="calculateFinalPay" 
            :loading="processing"
          >
            Calculate
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Release Final Pay Dialog -->
    <v-dialog v-model="showReleaseDialog" max-width="500">
      <v-card>
        <v-card-title class="pa-6">
          <v-icon left color="success">mdi-cash-check</v-icon>
          Release Final Pay
        </v-card-title>

        <v-divider></v-divider>

        <v-card-text class="pa-6">
          <v-alert type="warning" variant="tonal" class="mb-4">
            <strong>Are you sure you want to release the final pay?</strong>
          </v-alert>

          <div class="mb-4">
            <div class="text-caption text-medium-emphasis mb-1">Employee</div>
            <div class="text-body-1 font-weight-bold">{{ selectedResignation?.employee.full_name }}</div>
          </div>

          <div class="mb-4">
            <div class="text-caption text-medium-emphasis mb-1">Final Pay Amount</div>
            <div class="text-h6 text-primary">₱{{ formatCurrency(selectedResignation?.final_pay_amount) }}</div>
          </div>

          <v-alert type="info" variant="tonal">
            This will mark the employee's status as <strong>resigned</strong> and stop future salary processing.
          </v-alert>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="showReleaseDialog = false">Cancel</v-btn>
          <v-btn 
            color="success" 
            @click="releaseFinalPay" 
            :loading="processing"
          >
            Release Final Pay
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Attachment Preview Dialog -->
    <v-dialog v-model="showAttachmentDialog" max-width="900">
      <v-card>
        <v-card-title class="pa-4 d-flex align-center">
          <v-icon left>{{ getFileIcon(currentAttachment?.mime_type) }}</v-icon>
          {{ currentAttachment?.original_name }}
          <v-spacer></v-spacer>
          <v-btn
            icon="mdi-download"
            variant="text"
            @click="downloadCurrentAttachment"
          ></v-btn>
          <v-btn
            icon="mdi-close"
            variant="text"
            @click="showAttachmentDialog = false"
          ></v-btn>
        </v-card-title>

        <v-divider></v-divider>

        <v-card-text class="pa-0" style="min-height: 500px; max-height: 80vh; overflow: auto;">
          <!-- Image Preview -->
          <div v-if="isImage(currentAttachment?.mime_type)" class="pa-4 text-center">
            <img
              :src="attachmentUrl"
              :alt="currentAttachment?.original_name"
              style="max-width: 100%; height: auto;"
            />
          </div>

          <!-- PDF Preview -->
          <iframe
            v-else-if="isPDF(currentAttachment?.mime_type)"
            :src="attachmentUrl"
            style="width: 100%; height: 70vh; border: none;"
          ></iframe>

          <!-- Other file types -->
          <div v-else class="pa-8 text-center">
            <v-icon size="80" color="grey">mdi-file-document</v-icon>
            <div class="text-h6 mt-4">{{ currentAttachment?.original_name }}</div>
            <div class="text-caption text-medium-emphasis mt-2">
              This file type cannot be previewed. Click the download button to view it.
            </div>
            <v-btn
              color="primary"
              class="mt-4"
              prepend-icon="mdi-download"
              @click="downloadCurrentAttachment"
            >
              Download File
            </v-btn>
          </div>
        </v-card-text>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snackbar" :color="snackbarColor" :timeout="3000">
      {{ snackbarText }}
    </v-snackbar>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import api from '@/services/api'

// State
const loading = ref(true)
const processing = ref(false)
const resignations = ref([])
const selectedResignation = ref(null)

// Dialogs
const showDetailsDialog = ref(false)
const showApproveDialog = ref(false)
const showRejectDialog = ref(false)
const showFinalPayDialog = ref(false)
const showReleaseDialog = ref(false)
const showAttachmentDialog = ref(false)
const currentAttachment = ref(null)
const currentResignationId = ref(null)
const currentAttachmentIndex = ref(null)
const attachmentUrl = ref('')

// Form validation
const approveFormValid = ref(false)
const rejectFormValid = ref(false)

// Form data
const approveData = ref({
  effective_date: '',
  remarks: ''
})

const rejectData = ref({
  remarks: ''
})

const finalPayData = ref({
  remaining_salary: 0
})

// Filters
const filters = ref({
  status: 'all',
  search: ''
})

// Snackbar
const snackbar = ref(false)
const snackbarText = ref('')
const snackbarColor = ref('success')

// Options
const statusOptions = [
  { title: 'All', value: 'all' },
  { title: 'Pending', value: 'pending' },
  { title: 'Approved', value: 'approved' },
  { title: 'Rejected', value: 'rejected' },
  { title: 'Completed', value: 'completed' }
]

// Table headers
const headers = [
  { title: 'Employee', key: 'employee', sortable: false },
  { title: 'Position', key: 'position', sortable: false },
  { title: 'Status', key: 'status' },
  { title: 'Filed Date', key: 'resignation_date' },
  { title: 'Last Working Day', key: 'last_working_day' },
  { title: 'Days Left', key: 'days_remaining' },
  { title: 'Final Pay', key: 'final_pay', sortable: false },
  { title: 'Actions', key: 'actions', sortable: false, align: 'center' }
]

// Methods
const loadResignations = async () => {
  try {
    loading.value = true
    const params = {
      status: filters.value.status,
      search: filters.value.search
    }
    const response = await api.get('/resignations', { params })
    resignations.value = response.data.data || response.data
  } catch (error) {
    showSnackbar('Failed to load resignations', 'error')
  } finally {
    loading.value = false
  }
}

const viewDetails = (resignation) => {
  selectedResignation.value = resignation
  showDetailsDialog.value = true
}

const openApproveDialog = (resignation) => {
  selectedResignation.value = resignation
  approveData.value = {
    effective_date: resignation.last_working_day,
    remarks: ''
  }
  showApproveDialog.value = true
}

const openRejectDialog = (resignation) => {
  selectedResignation.value = resignation
  rejectData.value = {
    remarks: ''
  }
  showRejectDialog.value = true
}

const openFinalPayDialog = (resignation) => {
  selectedResignation.value = resignation
  finalPayData.value = {
    remaining_salary: 0
  }
  showFinalPayDialog.value = true
}

const openReleaseDialog = (resignation) => {
  selectedResignation.value = resignation
  showReleaseDialog.value = true
}

const approveResignation = async () => {
  try {
    processing.value = true
    const response = await api.post(`/resignations/${selectedResignation.value.id}/approve`, approveData.value)
    showSnackbar('Resignation approved successfully', 'success')
    showApproveDialog.value = false
    await loadResignations()
  } catch (error) {
    showSnackbar(error.response?.data?.message || 'Failed to approve resignation', 'error')
  } finally {
    processing.value = false
  }
}

const rejectResignation = async () => {
  try {
    processing.value = true
    await api.post(`/resignations/${selectedResignation.value.id}/reject`, rejectData.value)
    showSnackbar('Resignation rejected', 'success')
    showRejectDialog.value = false
    await loadResignations()
  } catch (error) {
    showSnackbar(error.response?.data?.message || 'Failed to reject resignation', 'error')
  } finally {
    processing.value = false
  }
}

const calculateFinalPay = async () => {
  try {
    processing.value = true
    const response = await api.post(`/resignations/${selectedResignation.value.id}/process-final-pay`, finalPayData.value)
    showSnackbar('Final pay calculated successfully', 'success')
    showFinalPayDialog.value = false
    
    await loadResignations()
  } catch (error) {
    showSnackbar(error.response?.data?.message || 'Failed to calculate final pay', 'error')
  } finally {
    processing.value = false
  }
}

const releaseFinalPay = async () => {
  try {
    processing.value = true
    await api.post(`/resignations/${selectedResignation.value.id}/release-final-pay`)
    showSnackbar('Final pay released successfully. Employee status updated to resigned.', 'success')
    showReleaseDialog.value = false
    await loadResignations()
  } catch (error) {
    showSnackbar(error.response?.data?.message || 'Failed to release final pay', 'error')
  } finally {
    processing.value = false
  }
}

const getStatusColor = (status) => {
  const colors = {
    pending: 'warning',
    approved: 'success',
    rejected: 'error',
    completed: 'info'
  }
  return colors[status] || 'grey'
}

const getStatusIcon = (status) => {
  const icons = {
    pending: 'mdi-clock-outline',
    approved: 'mdi-check-circle',
    rejected: 'mdi-close-circle',
    completed: 'mdi-checkbox-marked-circle'
  }
  return icons[status] || 'mdi-help-circle'
}

const formatDate = (date) => {
  if (!date) return 'N/A'
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

const formatCurrency = (amount) => {
  if (!amount) return '0.00'
  return parseFloat(amount).toLocaleString('en-US', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  })
}

const viewAttachment = async (resignationId, index, attachment) => {
  try {
    currentAttachment.value = attachment
    currentResignationId.value = resignationId
    currentAttachmentIndex.value = index
    
    const response = await api.get(
      `/resignations/${resignationId}/attachments/${index}/download`,
      { responseType: 'blob' }
    )
    
    // Create object URL for viewing
    if (attachmentUrl.value) {
      window.URL.revokeObjectURL(attachmentUrl.value)
    }
    attachmentUrl.value = window.URL.createObjectURL(new Blob([response.data], { type: attachment.mime_type }))
    showAttachmentDialog.value = true
  } catch (error) {
    showSnackbar('Failed to load attachment', 'error')
  }
}

const downloadCurrentAttachment = async () => {
  try {
    const response = await api.get(
      `/resignations/${currentResignationId.value}/attachments/${currentAttachmentIndex.value}/download`,
      { responseType: 'blob' }
    )
    
    // Create a download link
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', currentAttachment.value.original_name)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (error) {
    showSnackbar('Failed to download attachment', 'error')
  }
}

const isImage = (mimeType) => {
  return mimeType?.startsWith('image/')
}

const isPDF = (mimeType) => {
  return mimeType === 'application/pdf'
}

const getFileIcon = (mimeType) => {
  if (isImage(mimeType)) return 'mdi-image'
  if (isPDF(mimeType)) return 'mdi-file-pdf-box'
  if (mimeType?.includes('word') || mimeType?.includes('document')) return 'mdi-file-word'
  return 'mdi-file-document'
}

const showSnackbar = (text, color = 'success') => {
  snackbarText.value = text
  snackbarColor.value = color
  snackbar.value = true
}

// Lifecycle
onMounted(() => {
  loadResignations()
})
</script>

<style scoped>
.gap-2 {
  gap: 8px;
}
</style>
