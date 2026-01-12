<template>
  <v-container fluid class="pa-6">
    <div class="mb-6">
      <h1 class="text-h4 font-weight-bold mb-2">My Resignation</h1>
      <p class="text-body-1 text-medium-emphasis">
        Submit and track your resignation request
      </p>
    </div>

    <!-- Loading State -->
    <v-row v-if="loading">
      <v-col cols="12" class="text-center py-8">
        <v-progress-circular
          indeterminate
          color="primary"
          size="64"
        ></v-progress-circular>
        <p class="mt-4">Loading resignation information...</p>
      </v-col>
    </v-row>

    <!-- No Resignation Filed -->
    <v-row v-else-if="!resignation">
      <v-col cols="12" md="8" lg="6">
        <v-card elevation="2">
          <v-card-text class="pa-6">
            <div class="text-center mb-6">
              <v-icon size="80" color="info">mdi-briefcase-remove-outline</v-icon>
              <h2 class="text-h5 mt-4 mb-2">Submit Resignation</h2>
              <p class="text-body-2 text-medium-emphasis">
                If you wish to resign, please fill out the form below
              </p>
            </div>

            <v-form ref="resignationForm" v-model="formValid" @submit.prevent="submitResignation">
              <v-text-field
                v-model="formData.last_working_day"
                label="Intended Last Working Day *"
                type="date"
                variant="outlined"
                :rules="[rules.required, rules.futureDate]"
                :min="minDate"
                class="mb-4"
              ></v-text-field>

              <v-textarea
                v-model="formData.reason"
                label="Reason for Resignation (Optional)"
                variant="outlined"
                rows="4"
                counter="1000"
                :rules="[rules.maxLength]"
                hint="Please provide your reason for resigning"
                class="mb-4"
              ></v-textarea>

              <!-- File Attachments -->
              <v-card variant="outlined" class="mb-4">
                <v-card-subtitle class="text-body-2 font-weight-medium">
                  <v-icon left size="small">mdi-paperclip</v-icon>
                  Attachments (Optional)
                </v-card-subtitle>
                <v-card-text>
                  <v-file-input
                    v-model="attachmentFiles"
                    label="Attach files (JPG, PNG, PDF, DOC, DOCX)"
                    variant="outlined"
                    multiple
                    chips
                    accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"
                    prepend-icon="mdi-paperclip"
                    :rules="attachmentRules"
                    hint="Max 10MB per file. You can attach resignation letter, clearance forms, or other documents."
                    persistent-hint
                  >
                    <template v-slot:selection="{ fileNames }">
                      <template v-for="(fileName, index) in fileNames" :key="fileName">
                        <v-chip
                          class="me-2"
                          color="primary"
                          size="small"
                          label
                        >
                          {{ fileName }}
                        </v-chip>
                      </template>
                    </template>
                  </v-file-input>
                </v-card-text>
              </v-card>

              <v-alert
                type="warning"
                variant="tonal"
                class="mb-4"
              >
                <strong>Important:</strong> Once submitted, your resignation will be reviewed by HR. 
                Please ensure you have discussed this with your supervisor.
              </v-alert>

              <div class="d-flex justify-end gap-3">
                <v-btn
                  variant="outlined"
                  @click="$router.back()"
                >
                  Cancel
                </v-btn>
                <v-btn
                  color="error"
                  type="submit"
                  :disabled="!formValid || submitting"
                  :loading="submitting"
                >
                  <v-icon left>mdi-send</v-icon>
                  Submit Resignation
                </v-btn>
              </div>
            </v-form>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Existing Resignation -->
    <v-row v-else>
      <v-col cols="12" md="8">
        <v-card elevation="2" class="mb-4">
          <v-card-title class="d-flex align-center pa-6">
            <v-icon left :color="getStatusColor(resignation.status)">
              {{ getStatusIcon(resignation.status) }}
            </v-icon>
            <span>Resignation Status: {{ resignation.status_label }}</span>
          </v-card-title>

          <v-divider></v-divider>

          <v-card-text class="pa-6">
            <v-row>
              <v-col cols="12" sm="6">
                <div class="mb-4">
                  <div class="text-caption text-medium-emphasis mb-1">Resignation Date</div>
                  <div class="text-body-1 font-weight-medium">
                    {{ formatDate(resignation.resignation_date) }}
                  </div>
                </div>
              </v-col>

              <v-col cols="12" sm="6">
                <div class="mb-4">
                  <div class="text-caption text-medium-emphasis mb-1">Requested Last Working Day</div>
                  <div class="text-body-1 font-weight-medium">
                    {{ formatDate(resignation.last_working_day) }}
                  </div>
                </div>
              </v-col>

              <v-col cols="12" sm="6" v-if="resignation.effective_date">
                <div class="mb-4">
                  <div class="text-caption text-medium-emphasis mb-1">Approved Last Working Day</div>
                  <div class="text-body-1 font-weight-medium">
                    {{ formatDate(resignation.effective_date) }}
                  </div>
                </div>
              </v-col>

              <v-col cols="12" sm="6" v-if="resignation.days_remaining !== null">
                <div class="mb-4">
                  <div class="text-caption text-medium-emphasis mb-1">Days Remaining</div>
                  <div class="text-body-1 font-weight-medium">
                    <v-chip :color="resignation.days_remaining > 7 ? 'success' : 'warning'" size="small">
                      {{ resignation.days_remaining }} days
                    </v-chip>
                  </div>
                </div>
              </v-col>

              <v-col cols="12" v-if="resignation.reason">
                <div class="mb-4">
                  <div class="text-caption text-medium-emphasis mb-1">Reason</div>
                  <div class="text-body-1">{{ resignation.reason }}</div>
                </div>
              </v-col>

              <!-- Attachments Display -->
              <v-col cols="12" v-if="resignation.attachments && resignation.attachments.length > 0">
                <div class="mb-4">
                  <div class="text-caption text-medium-emphasis mb-2">Attachments</div>
                  <div class="d-flex flex-wrap ga-2">
                    <v-chip
                      v-for="(attachment, index) in resignation.attachments"
                      :key="index"
                      color="primary"
                      variant="outlined"
                      prepend-icon="mdi-file-document"
                      @click="downloadAttachment(index)"
                      :closable="resignation.status === 'pending'"
                      @click:close="deleteAttachment(index)"
                    >
                      {{ attachment.original_name }}
                      <template v-slot:append>
                        <v-icon size="small">mdi-download</v-icon>
                      </template>
                    </v-chip>
                  </div>
                </div>
              </v-col>

              <v-col cols="12" v-if="resignation.remarks">
                <v-alert type="info" variant="tonal">
                  <div class="text-caption text-medium-emphasis mb-1">HR Remarks</div>
                  <div class="text-body-2">{{ resignation.remarks }}</div>
                </v-alert>
              </v-col>

              <v-col cols="12" v-if="resignation.processed_by">
                <div class="mb-4">
                  <div class="text-caption text-medium-emphasis mb-1">Processed By</div>
                  <div class="text-body-1">
                    {{ resignation.processed_by?.name }} on {{ formatDate(resignation.processed_at) }}
                  </div>
                </div>
              </v-col>
            </v-row>
          </v-card-text>
        </v-card>

        <!-- Final Pay Information (if approved) -->
        <v-card elevation="2" v-if="resignation.status === 'approved' || resignation.status === 'completed'">
          <v-card-title class="pa-6">
            <v-icon left color="success">mdi-cash-check</v-icon>
            Final Pay Information
          </v-card-title>

          <v-divider></v-divider>

          <v-card-text class="pa-6">
            <v-row v-if="resignation.final_pay_amount">
              <v-col cols="12" sm="6">
                <div class="mb-4">
                  <div class="text-caption text-medium-emphasis mb-1">13th Month Pay</div>
                  <div class="text-h6 font-weight-bold text-success">
                    ₱{{ formatCurrency(resignation.thirteenth_month_amount) }}
                  </div>
                </div>
              </v-col>

              <v-col cols="12" sm="6">
                <div class="mb-4">
                  <div class="text-caption text-medium-emphasis mb-1">Total Final Pay</div>
                  <div class="text-h6 font-weight-bold text-primary">
                    ₱{{ formatCurrency(resignation.final_pay_amount) }}
                  </div>
                </div>
              </v-col>

              <v-col cols="12" v-if="resignation.final_pay_released">
                <v-alert type="success" variant="tonal">
                  <strong>Final pay released on {{ formatDate(resignation.final_pay_release_date) }}</strong>
                </v-alert>
              </v-col>

              <v-col cols="12" v-else>
                <v-alert type="info" variant="tonal">
                  Your final pay is being processed and will be released soon.
                </v-alert>
              </v-col>
            </v-row>

            <v-alert v-else type="info" variant="tonal">
              Your final pay calculation is pending. HR will process this soon.
            </v-alert>
          </v-card-text>
        </v-card>
      </v-col>

      <!-- Action Panel -->
      <v-col cols="12" md="4">
        <v-card elevation="2" v-if="resignation.status === 'pending'">
          <v-card-title class="pa-6">
            <v-icon left color="warning">mdi-alert-circle</v-icon>
            Actions
          </v-card-title>

          <v-divider></v-divider>

          <v-card-text class="pa-6">
            <p class="text-body-2 mb-4">
              Your resignation is pending approval. You can withdraw it if needed.
            </p>

            <v-btn
              color="error"
              variant="outlined"
              block
              @click="confirmWithdraw"
              :loading="withdrawing"
            >
              <v-icon left>mdi-cancel</v-icon>
              Withdraw Resignation
            </v-btn>
          </v-card-text>
        </v-card>

        <!-- Timeline -->
        <v-card elevation="2" class="mt-4">
          <v-card-title class="pa-6">
            <v-icon left>mdi-timeline-clock</v-icon>
            Timeline
          </v-card-title>

          <v-divider></v-divider>

          <v-card-text class="pa-6">
            <v-timeline side="end" density="compact">
              <v-timeline-item
                dot-color="primary"
                size="small"
              >
                <div class="mb-2">
                  <div class="font-weight-bold">Submitted</div>
                  <div class="text-caption">{{ formatDate(resignation.created_at) }}</div>
                </div>
              </v-timeline-item>

              <v-timeline-item
                v-if="resignation.status !== 'pending'"
                :dot-color="resignation.status === 'rejected' ? 'error' : 'success'"
                size="small"
              >
                <div class="mb-2">
                  <div class="font-weight-bold">{{ resignation.status === 'rejected' ? 'Rejected' : 'Approved' }}</div>
                  <div class="text-caption">{{ formatDate(resignation.processed_at) }}</div>
                </div>
              </v-timeline-item>

              <v-timeline-item
                v-if="resignation.status === 'completed'"
                dot-color="success"
                size="small"
              >
                <div class="mb-2">
                  <div class="font-weight-bold">Final Pay Released</div>
                  <div class="text-caption">{{ formatDate(resignation.final_pay_release_date) }}</div>
                </div>
              </v-timeline-item>
            </v-timeline>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>

    <!-- Confirmation Dialogs -->
    <v-dialog v-model="showWithdrawDialog" max-width="500">
      <v-card>
        <v-card-title class="pa-6">
          <v-icon left color="warning">mdi-alert</v-icon>
          Confirm Withdrawal
        </v-card-title>

        <v-divider></v-divider>

        <v-card-text class="pa-6">
          Are you sure you want to withdraw your resignation? This action cannot be undone.
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="showWithdrawDialog = false">Cancel</v-btn>
          <v-btn color="error" @click="withdrawResignation" :loading="withdrawing">
            Withdraw
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <v-snackbar v-model="snackbar" :color="snackbarColor" :timeout="3000">
      {{ snackbarText }}
    </v-snackbar>
  </v-container>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '@/services/api'
import { useAuthStore } from '@/stores/auth'

const router = useRouter()
const authStore = useAuthStore()

// State
const loading = ref(true)
const submitting = ref(false)
const withdrawing = ref(false)
const formValid = ref(false)
const resignation = ref(null)
const showWithdrawDialog = ref(false)
const attachmentFiles = ref([])

// Snackbar
const snackbar = ref(false)
const snackbarText = ref('')
const snackbarColor = ref('success')

// Form data
const formData = ref({
  last_working_day: '',
  reason: ''
})

// Validation rules
const rules = {
  required: v => !!v || 'Required',
  futureDate: v => {
    if (!v) return true
    const selected = new Date(v)
    const today = new Date()
    today.setHours(0, 0, 0, 0)
    return selected > today || 'Date must be in the future'
  },
  maxLength: v => !v || v.length <= 1000 || 'Maximum 1000 characters'
}

const attachmentRules = [
  files => {
    if (!files || files.length === 0) return true
    const maxSize = 10 * 1024 * 1024 // 10MB
    for (const file of files) {
      if (file.size > maxSize) {
        return `File "${file.name}" exceeds 10MB limit`
      }
    }
    return true
  }
]

// Computed
const minDate = computed(() => {
  const tomorrow = new Date()
  tomorrow.setDate(tomorrow.getDate() + 1)
  return tomorrow.toISOString().split('T')[0]
})

// Methods
const loadResignation = async () => {
  try {
    loading.value = true
    const employeeId = authStore.user.employee_id
    const response = await api.get(`/resignations/employee/${employeeId}`)
    resignation.value = response.data
  } catch (error) {
    if (error.response?.status !== 404) {
      showSnackbar('Failed to load resignation information', 'error')
    }
    // 404 means no resignation exists, which is fine
  } finally {
    loading.value = false
  }
}

const submitResignation = async () => {
  if (!formValid.value) return

  try {
    submitting.value = true
    const employeeId = authStore.user.employee_id
    
    // Create FormData for file upload
    const formDataToSend = new FormData()
    formDataToSend.append('employee_id', employeeId)
    formDataToSend.append('last_working_day', formData.value.last_working_day)
    if (formData.value.reason) {
      formDataToSend.append('reason', formData.value.reason)
    }
    
    // Append files if any
    if (attachmentFiles.value && attachmentFiles.value.length > 0) {
      attachmentFiles.value.forEach((file) => {
        formDataToSend.append('attachments[]', file)
      })
    }
    
    const response = await api.post('/resignations', formDataToSend, {
      headers: {
        'Content-Type': 'multipart/form-data'
      }
    })
    resignation.value = response.data.resignation
    showSnackbar('Resignation submitted successfully', 'success')
  } catch (error) {
    showSnackbar(error.response?.data?.message || 'Failed to submit resignation', 'error')
  } finally {
    submitting.value = false
  }
}

const confirmWithdraw = () => {
  showWithdrawDialog.value = true
}

const withdrawResignation = async () => {
  try {
    withdrawing.value = true
    await api.delete(`/resignations/${resignation.value.id}`)
    showSnackbar('Resignation withdrawn successfully', 'success')
    resignation.value = null
    showWithdrawDialog.value = false
  } catch (error) {
    showSnackbar(error.response?.data?.message || 'Failed to withdraw resignation', 'error')
  } finally {
    withdrawing.value = false
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
    month: 'long',
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

const showSnackbar = (text, color = 'success') => {
  snackbarText.value = text
  snackbarColor.value = color
  snackbar.value = true
}

const downloadAttachment = async (index) => {
  try {
    const response = await api.get(
      `/resignations/${resignation.value.id}/attachments/${index}/download`,
      { responseType: 'blob' }
    )
    
    // Create a download link
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', resignation.value.attachments[index].original_name)
    document.body.appendChild(link)
    link.click()
    link.remove()
    window.URL.revokeObjectURL(url)
  } catch (error) {
    showSnackbar('Failed to download attachment', 'error')
  }
}

const deleteAttachment = async (index) => {
  if (!confirm('Are you sure you want to delete this attachment?')) return
  
  try {
    const response = await api.delete(
      `/resignations/${resignation.value.id}/attachments/${index}`
    )
    resignation.value = response.data.resignation
    showSnackbar('Attachment deleted successfully', 'success')
  } catch (error) {
    showSnackbar(error.response?.data?.message || 'Failed to delete attachment', 'error')
  }
}

// Lifecycle
onMounted(() => {
  loadResignation()
})
</script>

<style scoped>
.gap-3 {
  gap: 12px;
}
</style>
