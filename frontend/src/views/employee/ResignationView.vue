<template>
  <div class="resignation-page">
    <!-- Modern Page Header -->
    <div class="page-header">
      <div class="page-title-section">
        <div class="page-icon-badge">
          <v-icon size="20">mdi-briefcase-remove-outline</v-icon>
        </div>
        <div>
          <h1 class="page-title">My Resignation</h1>
          <p class="page-subtitle">Submit and track your resignation request</p>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="loading-container">
      <v-progress-circular
        indeterminate
        color="primary"
        size="64"
      ></v-progress-circular>
      <p class="loading-text">Loading resignation information...</p>
    </div>

    <!-- No Resignation Filed - Show Form -->
    <v-row v-else-if="!resignation" justify="center">
      <v-col cols="12" md="8" lg="6">
        <!-- Resignation Form Card -->
        <div class="resignation-form-card">
          <div class="card-header">
            <div class="card-header-left">
              <div class="card-header-icon">
                <v-icon size="18">mdi-file-document-edit</v-icon>
              </div>
              <h3 class="card-header-title">Submit Resignation</h3>
            </div>
          </div>

          <div class="form-content">
            <v-form
              ref="resignationForm"
              v-model="formValid"
              @submit.prevent="submitResignation"
            >
              <!-- Last Working Day -->
              <div class="form-group">
                <label class="form-label">
                  <v-icon size="16">mdi-calendar</v-icon>
                  <span>Intended Last Working Day</span>
                </label>
                <v-text-field
                  v-model="formData.last_working_day"
                  type="date"
                  variant="outlined"
                  :rules="[rules.required, rules.futureDate]"
                  :min="minDate"
                  density="comfortable"
                  hide-details="auto"
                ></v-text-field>
              </div>

              <!-- Reason -->
              <div class="form-group">
                <label class="form-label">
                  <v-icon size="16">mdi-text</v-icon>
                  <span>Reason for Resignation (Optional)</span>
                </label>
                <v-textarea
                  v-model="formData.reason"
                  variant="outlined"
                  rows="4"
                  counter="1000"
                  :rules="[rules.maxLength]"
                  placeholder="Please provide your reason for resigning..."
                  density="comfortable"
                  hide-details="auto"
                ></v-textarea>
              </div>

              <!-- File Attachments -->
              <div class="form-group">
                <label class="form-label">
                  <v-icon size="16">mdi-paperclip</v-icon>
                  <span>Attachments (Optional)</span>
                </label>
                <div class="file-upload-area">
                  <v-file-input
                    v-model="attachmentFiles"
                    variant="outlined"
                    multiple
                    chips
                    accept=".jpg,.jpeg,.png,.pdf,.doc,.docx"
                    prepend-icon="mdi-paperclip"
                    :rules="attachmentRules"
                    density="comfortable"
                    hide-details="auto"
                    placeholder="Select files..."
                  >
                    <template v-slot:selection="{ fileNames }">
                      <template
                        v-for="(fileName, index) in fileNames"
                        :key="fileName"
                      >
                        <v-chip class="me-2" color="primary" size="small" label>
                          {{ fileName }}
                        </v-chip>
                      </template>
                    </template>
                  </v-file-input>
                  <div class="file-hint">
                    <v-icon size="14">mdi-information</v-icon>
                    <span
                      >Max 10MB per file. Formats: JPG, PNG, PDF, DOC,
                      DOCX</span
                    >
                  </div>
                </div>
              </div>

              <!-- Warning Alert -->
              <div class="warning-alert">
                <div class="warning-icon">
                  <v-icon size="20">mdi-alert</v-icon>
                </div>
                <div class="warning-content">
                  <strong>Important:</strong> Once submitted, your resignation
                  will be reviewed by HR. Please ensure you have discussed this
                  with your supervisor.
                </div>
              </div>

              <!-- Form Actions -->
              <div class="form-actions">
                <button
                  type="button"
                  class="form-btn form-btn-secondary"
                  @click="$router.back()"
                >
                  <v-icon size="18">mdi-arrow-left</v-icon>
                  <span>Cancel</span>
                </button>
                <button
                  type="submit"
                  class="form-btn form-btn-primary"
                  :disabled="!formValid || submitting"
                >
                  <v-progress-circular
                    v-if="submitting"
                    indeterminate
                    size="18"
                    width="2"
                    color="white"
                  ></v-progress-circular>
                  <v-icon v-else size="18">mdi-send</v-icon>
                  <span>{{
                    submitting ? "Submitting..." : "Submit Resignation"
                  }}</span>
                </button>
              </div>
            </v-form>
          </div>
        </div>
      </v-col>
    </v-row>

    <!-- Existing Resignation -->
    <v-row v-else>
      <!-- Main Content -->
      <v-col cols="12" md="8">
        <!-- Status Card -->
        <div class="status-card mb-4">
          <div class="status-header">
            <div
              class="status-icon-wrapper"
              :class="getStatusClass(resignation.status)"
            >
              <v-icon size="28">{{ getStatusIcon(resignation.status) }}</v-icon>
            </div>
            <div class="status-info">
              <div class="status-label">Resignation Status</div>
              <v-chip
                :color="getStatusColor(resignation.status)"
                size="large"
                label
              >
                {{ resignation.status_label }}
              </v-chip>
            </div>
          </div>
        </div>

        <!-- Details Card -->
        <div class="details-card mb-4">
          <div class="card-header">
            <div class="card-header-left">
              <div class="card-header-icon">
                <v-icon size="18">mdi-information</v-icon>
              </div>
              <h3 class="card-header-title">Resignation Details</h3>
            </div>
          </div>

          <div class="details-content">
            <!-- Info Items -->
            <div class="info-grid">
              <div class="info-item">
                <div class="info-icon">
                  <v-icon size="20">mdi-calendar</v-icon>
                </div>
                <div class="info-content">
                  <div class="info-label">Resignation Date</div>
                  <div class="info-value">
                    {{ formatDate(resignation.resignation_date) }}
                  </div>
                </div>
              </div>

              <div class="info-item">
                <div class="info-icon">
                  <v-icon size="20">mdi-calendar-check</v-icon>
                </div>
                <div class="info-content">
                  <div class="info-label">Requested Last Working Day</div>
                  <div class="info-value">
                    {{ formatDate(resignation.last_working_day) }}
                  </div>
                </div>
              </div>

              <div
                v-if="resignation.effective_date"
                class="info-item highlight"
              >
                <div class="info-icon">
                  <v-icon size="20">mdi-calendar-star</v-icon>
                </div>
                <div class="info-content">
                  <div class="info-label">Approved Last Working Day</div>
                  <div class="info-value">
                    {{ formatDate(resignation.effective_date) }}
                  </div>
                </div>
              </div>

              <div
                v-if="resignation.days_remaining !== null"
                class="info-item"
                :class="resignation.days_remaining <= 7 ? 'warning' : 'success'"
              >
                <div class="info-icon">
                  <v-icon size="20">mdi-clock-outline</v-icon>
                </div>
                <div class="info-content">
                  <div class="info-label">Days Remaining</div>
                  <div class="info-value">
                    {{ resignation.days_remaining }} days
                  </div>
                </div>
              </div>
            </div>

            <!-- Reason -->
            <div v-if="resignation.reason" class="reason-section">
              <div class="section-title">
                <v-icon size="16">mdi-text</v-icon>
                <span>Reason</span>
              </div>
              <div class="reason-text">{{ resignation.reason }}</div>
            </div>

            <!-- Attachments -->
            <div
              v-if="
                resignation.attachments && resignation.attachments.length > 0
              "
              class="attachments-section"
            >
              <div class="section-title">
                <v-icon size="16">mdi-paperclip</v-icon>
                <span>Attachments</span>
              </div>
              <div class="attachments-list">
                <div
                  v-for="(attachment, index) in resignation.attachments"
                  :key="index"
                  class="attachment-item"
                  @click="viewAttachment(index, attachment)"
                >
                  <div
                    class="attachment-icon"
                    :style="{ color: getFileIconColor(attachment.mime_type) }"
                  >
                    <v-icon size="24">{{
                      getFileIcon(attachment.mime_type)
                    }}</v-icon>
                  </div>
                  <div class="attachment-info">
                    <div class="attachment-name">
                      {{ attachment.original_name }}
                    </div>
                    <div class="attachment-size">
                      {{ formatFileSize(attachment.size) }}
                    </div>
                  </div>
                  <button
                    v-if="resignation.status === 'pending'"
                    class="attachment-delete-btn"
                    @click.stop="deleteAttachment(index)"
                  >
                    <v-icon size="16">mdi-close</v-icon>
                  </button>
                </div>
              </div>
            </div>

            <!-- HR Remarks -->
            <div v-if="resignation.remarks" class="remarks-section">
              <div class="section-title">
                <v-icon size="16">mdi-comment-text</v-icon>
                <span>HR Remarks</span>
              </div>
              <div class="remarks-text">{{ resignation.remarks }}</div>
            </div>

            <!-- Processed By -->
            <div v-if="resignation.processed_by" class="processed-by">
              <v-icon size="16">mdi-account-check</v-icon>
              <span
                >Processed by
                <strong>{{ resignation.processed_by?.name }}</strong> on
                {{ formatDate(resignation.processed_at) }}</span
              >
            </div>
          </div>
        </div>

        <!-- Final Pay Card (if approved) -->
        <div
          v-if="
            resignation.status === 'approved' ||
            resignation.status === 'completed'
          "
          class="final-pay-card"
        >
          <div class="card-header">
            <div class="card-header-left">
              <div class="card-header-icon success">
                <v-icon size="18">mdi-cash-check</v-icon>
              </div>
              <h3 class="card-header-title">Final Pay Information</h3>
            </div>
          </div>

          <div class="final-pay-content">
            <div v-if="resignation.final_pay_amount" class="pay-grid">
              <div class="pay-item">
                <div class="pay-label">13th Month Pay</div>
                <div class="pay-value success">
                  ₱{{ formatCurrency(resignation.thirteenth_month_amount) }}
                </div>
              </div>
              <div class="pay-item">
                <div class="pay-label">Total Final Pay</div>
                <div class="pay-value primary">
                  ₱{{ formatCurrency(resignation.final_pay_amount) }}
                </div>
              </div>
            </div>

            <div
              v-if="resignation.final_pay_released"
              class="pay-alert success"
            >
              <v-icon size="20">mdi-check-circle</v-icon>
              <span
                >Final pay released on
                {{ formatDate(resignation.final_pay_release_date) }}</span
              >
            </div>
            <div v-else class="pay-alert info">
              <v-icon size="20">mdi-information</v-icon>
              <span
                >Your final pay is being processed and will be released
                soon.</span
              >
            </div>
          </div>
        </div>
      </v-col>

      <!-- Sidebar -->
      <v-col cols="12" md="4">
        <!-- Actions Card -->
        <div v-if="resignation.status === 'pending'" class="action-card mb-4">
          <div class="card-header">
            <div class="card-header-left">
              <div class="card-header-icon warning">
                <v-icon size="18">mdi-alert-circle</v-icon>
              </div>
              <h3 class="card-header-title">Actions</h3>
            </div>
          </div>

          <div class="action-content">
            <p class="action-text">
              Your resignation is pending approval. You can withdraw it if
              needed.
            </p>
            <button
              class="withdraw-btn"
              @click="confirmWithdraw"
              :disabled="withdrawing"
            >
              <v-icon size="18">mdi-cancel</v-icon>
              <span>Withdraw Resignation</span>
            </button>
          </div>
        </div>

        <!-- Timeline Card -->
        <div class="timeline-card">
          <div class="card-header">
            <div class="card-header-left">
              <div class="card-header-icon">
                <v-icon size="18">mdi-timeline-clock</v-icon>
              </div>
              <h3 class="card-header-title">Timeline</h3>
            </div>
          </div>

          <div class="timeline-content">
            <div class="timeline-item">
              <div class="timeline-dot primary"></div>
              <div class="timeline-info">
                <div class="timeline-title">Submitted</div>
                <div class="timeline-date">
                  {{ formatDate(resignation.created_at) }}
                </div>
              </div>
            </div>

            <div v-if="resignation.status !== 'pending'" class="timeline-item">
              <div
                class="timeline-dot"
                :class="resignation.status === 'rejected' ? 'error' : 'success'"
              ></div>
              <div class="timeline-info">
                <div class="timeline-title">
                  {{
                    resignation.status === "rejected" ? "Rejected" : "Approved"
                  }}
                </div>
                <div class="timeline-date">
                  {{ formatDate(resignation.processed_at) }}
                </div>
              </div>
            </div>

            <div
              v-if="resignation.status === 'completed'"
              class="timeline-item"
            >
              <div class="timeline-dot success"></div>
              <div class="timeline-info">
                <div class="timeline-title">Final Pay Released</div>
                <div class="timeline-date">
                  {{ formatDate(resignation.final_pay_release_date) }}
                </div>
              </div>
            </div>
          </div>
        </div>
      </v-col>
    </v-row>

    <!-- Withdraw Dialog -->
    <v-dialog v-model="showWithdrawDialog" max-width="450">
      <div class="modern-dialog">
        <div class="dialog-header">
          <div class="dialog-icon-wrapper warning">
            <v-icon size="24">mdi-alert</v-icon>
          </div>
          <div>
            <div class="dialog-title">Confirm Withdrawal</div>
            <div class="dialog-subtitle">This action cannot be undone</div>
          </div>
        </div>

        <div class="dialog-content">
          <p>Are you sure you want to withdraw your resignation?</p>
        </div>

        <div class="dialog-actions">
          <button
            class="dialog-btn dialog-btn-secondary"
            @click="showWithdrawDialog = false"
          >
            Cancel
          </button>
          <button
            class="dialog-btn dialog-btn-danger"
            @click="withdrawResignation"
            :disabled="withdrawing"
          >
            <v-progress-circular
              v-if="withdrawing"
              indeterminate
              size="16"
              width="2"
              color="white"
            ></v-progress-circular>
            <span>{{ withdrawing ? "Withdrawing..." : "Withdraw" }}</span>
          </button>
        </div>
      </div>
    </v-dialog>

    <!-- Attachment Preview Dialog -->
    <v-dialog v-model="showAttachmentDialog" max-width="900">
      <div class="attachment-dialog">
        <div class="attachment-dialog-header">
          <v-icon size="20">{{
            getFileIcon(currentAttachment?.mime_type)
          }}</v-icon>
          <span>{{ currentAttachment?.original_name }}</span>
          <v-spacer></v-spacer>
          <button class="icon-btn" @click="downloadCurrentAttachment">
            <v-icon size="20">mdi-download</v-icon>
          </button>
          <button class="icon-btn" @click="showAttachmentDialog = false">
            <v-icon size="20">mdi-close</v-icon>
          </button>
        </div>

        <div class="attachment-dialog-content">
          <div
            v-if="isImage(currentAttachment?.mime_type)"
            class="image-preview"
          >
            <img :src="attachmentUrl" :alt="currentAttachment?.original_name" />
          </div>

          <iframe
            v-else-if="isPDF(currentAttachment?.mime_type)"
            :src="attachmentUrl"
            class="pdf-preview"
          ></iframe>

          <div v-else class="no-preview">
            <v-icon size="64" color="grey">mdi-file-document</v-icon>
            <div class="no-preview-text">
              {{ currentAttachment?.original_name }}
            </div>
            <p>This file type cannot be previewed.</p>
            <button class="download-btn" @click="downloadCurrentAttachment">
              <v-icon size="18">mdi-download</v-icon>
              <span>Download File</span>
            </button>
          </div>
        </div>
      </div>
    </v-dialog>

    <v-snackbar v-model="snackbar" :color="snackbarColor" :timeout="3000">
      {{ snackbarText }}
    </v-snackbar>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import api from "@/services/api";
import { useAuthStore } from "@/stores/auth";
import { formatDate, formatCurrency } from "@/utils/formatters";
import { useConfirmDialog } from "@/composables/useConfirmDialog";

const router = useRouter();
const authStore = useAuthStore();
const { confirm: confirmDialog } = useConfirmDialog();

// State
const loading = ref(true);
const submitting = ref(false);
const withdrawing = ref(false);
const formValid = ref(false);
const resignation = ref(null);
const showWithdrawDialog = ref(false);
const showAttachmentDialog = ref(false);
const currentAttachment = ref(null);
const currentAttachmentIndex = ref(null);
const attachmentUrl = ref("");
const attachmentFiles = ref([]);

// Snackbar
const snackbar = ref(false);
const snackbarText = ref("");
const snackbarColor = ref("success");

// Form data
const formData = ref({
  last_working_day: "",
  reason: "",
});

// Validation rules
const rules = {
  required: (v) => !!v || "Required",
  futureDate: (v) => {
    if (!v) return true;
    const selected = new Date(v);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return selected > today || "Date must be in the future";
  },
  maxLength: (v) => !v || v.length <= 1000 || "Maximum 1000 characters",
};

const attachmentRules = [
  (files) => {
    if (!files || files.length === 0) return true;
    const maxSize = 10 * 1024 * 1024; // 10MB
    for (const file of files) {
      if (file.size > maxSize) {
        return `File "${file.name}" exceeds 10MB limit`;
      }
    }
    return true;
  },
];

// Computed
const minDate = computed(() => {
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  return tomorrow.toISOString().split("T")[0];
});

// Methods
const loadResignation = async () => {
  try {
    loading.value = true;
    const employeeId = authStore.user?.employee_id;
    if (!employeeId) {
      loading.value = false;
      return;
    }
    const response = await api.get(`/resignations/employee/${employeeId}`, {
      skipToast: true,
    });
    resignation.value = response.data;
  } catch {
    // 404 means no resignation exists, which is expected - don't show error
    resignation.value = null;
  } finally {
    loading.value = false;
  }
};

const submitResignation = async () => {
  if (!formValid.value) return;

  try {
    submitting.value = true;
    const employeeId = authStore.user.employee_id;

    const formDataToSend = new FormData();
    formDataToSend.append("employee_id", employeeId);
    formDataToSend.append("last_working_day", formData.value.last_working_day);
    if (formData.value.reason) {
      formDataToSend.append("reason", formData.value.reason);
    }

    if (attachmentFiles.value && attachmentFiles.value.length > 0) {
      attachmentFiles.value.forEach((file) => {
        formDataToSend.append("attachments[]", file);
      });
    }

    const response = await api.post("/resignations", formDataToSend, {
      headers: { "Content-Type": "multipart/form-data" },
    });
    resignation.value = response.data.resignation;
    showSnackbar("Resignation submitted successfully", "success");
  } catch (error) {
    showSnackbar(
      error.response?.data?.message || "Failed to submit resignation",
      "error",
    );
  } finally {
    submitting.value = false;
  }
};

const confirmWithdraw = () => {
  showWithdrawDialog.value = true;
};

const withdrawResignation = async () => {
  try {
    withdrawing.value = true;
    await api.delete(`/resignations/${resignation.value.id}`);
    showSnackbar("Resignation withdrawn successfully", "success");
    resignation.value = null;
    showWithdrawDialog.value = false;
  } catch (error) {
    showSnackbar(
      error.response?.data?.message || "Failed to withdraw resignation",
      "error",
    );
  } finally {
    withdrawing.value = false;
  }
};

const getStatusColor = (status) => {
  const colors = {
    pending: "warning",
    approved: "success",
    rejected: "error",
    completed: "info",
  };
  return colors[status] || "grey";
};

const getStatusIcon = (status) => {
  const icons = {
    pending: "mdi-clock-outline",
    approved: "mdi-check-circle",
    rejected: "mdi-close-circle",
    completed: "mdi-checkbox-marked-circle",
  };
  return icons[status] || "mdi-help-circle";
};

const getStatusClass = (status) => {
  const classes = {
    pending: "pending",
    approved: "success",
    rejected: "error",
    completed: "info",
  };
  return classes[status] || "";
};

const formatFileSize = (bytes) => {
  if (!bytes) return "0 B";
  const k = 1024;
  const sizes = ["B", "KB", "MB", "GB"];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
};

const getFileIcon = (mimeType) => {
  if (isImage(mimeType)) return "mdi-image";
  if (isPDF(mimeType)) return "mdi-file-pdf-box";
  if (mimeType?.includes("word") || mimeType?.includes("document"))
    return "mdi-file-word";
  return "mdi-file-document";
};

const getFileIconColor = (mimeType) => {
  if (isImage(mimeType)) return "#10b981";
  if (isPDF(mimeType)) return "#ef4444";
  if (mimeType?.includes("word") || mimeType?.includes("document"))
    return "#3b82f6";
  return "#64748b";
};

const isImage = (mimeType) => mimeType?.startsWith("image/");
const isPDF = (mimeType) => mimeType === "application/pdf";

const viewAttachment = async (index, attachment) => {
  try {
    currentAttachment.value = attachment;
    currentAttachmentIndex.value = index;

    const response = await api.get(
      `/resignations/${resignation.value.id}/attachments/${index}/download`,
      { responseType: "blob" },
    );

    if (attachmentUrl.value) window.URL.revokeObjectURL(attachmentUrl.value);
    attachmentUrl.value = window.URL.createObjectURL(
      new Blob([response.data], { type: attachment.mime_type }),
    );
    showAttachmentDialog.value = true;
  } catch {
    showSnackbar("Failed to load attachment", "error");
  }
};

const downloadCurrentAttachment = async () => {
  try {
    const response = await api.get(
      `/resignations/${resignation.value.id}/attachments/${currentAttachmentIndex.value}/download`,
      { responseType: "blob" },
    );

    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;
    link.setAttribute("download", currentAttachment.value.original_name);
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
  } catch {
    showSnackbar("Failed to download attachment", "error");
  }
};

const deleteAttachment = async (index) => {
  if (
    !(await confirmDialog("Are you sure you want to delete this attachment?"))
  )
    return;

  try {
    const response = await api.delete(
      `/resignations/${resignation.value.id}/attachments/${index}`,
    );
    resignation.value = response.data.resignation;
    showSnackbar("Attachment deleted successfully", "success");
  } catch (error) {
    showSnackbar(
      error.response?.data?.message || "Failed to delete attachment",
      "error",
    );
  }
};

const showSnackbar = (text, color = "success") => {
  snackbarText.value = text;
  snackbarColor.value = color;
  snackbar.value = true;
};

onMounted(() => {
  loadResignation();
});
</script>

<style scoped lang="scss">
.resignation-page {
  min-height: 100vh;
}

// Page Header
.page-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
}

.page-title-section {
  display: flex;
  align-items: center;
  gap: 16px;
}

.page-icon-badge {
  width: 56px;
  height: 56px;
  border-radius: 12px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);

  .v-icon {
    color: #ffffff !important;
  }
}

.page-title {
  font-size: 28px;
  font-weight: 700;
  color: #001f3d;
  margin: 0 0 4px 0;
  letter-spacing: -0.5px;
}

.page-subtitle {
  font-size: 14px;
  color: rgba(0, 31, 61, 0.6);
  margin: 0;
}

// Loading
.loading-container {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 80px 20px;
}

.loading-text {
  margin-top: 16px;
  color: #64748b;
}

// Card Styles
.resignation-form-card,
.status-card,
.details-card,
.final-pay-card,
.action-card,
.timeline-card {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  overflow: hidden;
}

.card-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px;
  background: linear-gradient(
    135deg,
    rgba(0, 31, 61, 0.02) 0%,
    rgba(237, 152, 95, 0.02) 100%
  );
  border-bottom: 1px solid rgba(0, 31, 61, 0.08);
}

.card-header-left {
  display: flex;
  align-items: center;
  gap: 12px;
}

.card-header-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.25);

  .v-icon {
    color: #ffffff !important;
  }

  &.success {
    background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);
  }

  &.warning {
    background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
    box-shadow: 0 2px 8px rgba(245, 158, 11, 0.25);
  }
}

.card-header-title {
  font-size: 16px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
}

// Form Content
.form-content {
  padding: 24px;
}

.form-group {
  margin-bottom: 20px;
}

.form-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 600;
  color: #001f3d;
  margin-bottom: 8px;
  text-transform: uppercase;
  letter-spacing: 0.5px;

  .v-icon {
    color: #ed985f !important;
  }
}

.file-upload-area {
  background: rgba(0, 31, 61, 0.02);
  border-radius: 12px;
  padding: 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
}

.file-hint {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: #64748b;
  margin-top: 8px;

  .v-icon {
    color: #ed985f !important;
  }
}

.warning-alert {
  display: flex;
  gap: 12px;
  padding: 16px;
  background: rgba(245, 158, 11, 0.08);
  border: 1px solid rgba(245, 158, 11, 0.2);
  border-radius: 12px;
  margin-bottom: 24px;
}

.warning-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: rgba(245, 158, 11, 0.15);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;

  .v-icon {
    color: #f59e0b !important;
  }
}

.warning-content {
  font-size: 14px;
  color: #001f3d;
  line-height: 1.5;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.form-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;

  &:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }

  &.form-btn-primary {
    background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);

    &:not(:disabled):hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 16px rgba(237, 152, 95, 0.4);
    }
  }

  &.form-btn-secondary {
    background: rgba(0, 31, 61, 0.06);
    color: rgba(0, 31, 61, 0.8);

    &:not(:disabled):hover {
      background: rgba(0, 31, 61, 0.1);
    }
  }
}

// Status Card
.status-header {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 24px;
}

.status-icon-wrapper {
  width: 56px;
  height: 56px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;

  &.pending {
    background: linear-gradient(135deg, #f59e0b 0%, #fbbf24 100%);
    box-shadow: 0 4px 12px rgba(245, 158, 11, 0.3);
  }

  &.success {
    background: linear-gradient(135deg, #10b981 0%, #34d399 100%);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
  }

  &.error {
    background: linear-gradient(135deg, #ef4444 0%, #f87171 100%);
    box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);
  }

  &.info {
    background: linear-gradient(135deg, #3b82f6 0%, #60a5fa 100%);
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
  }
}

.status-info {
  .status-label {
    font-size: 13px;
    color: #64748b;
    margin-bottom: 8px;
  }
}

// Details Content
.details-content {
  padding: 24px;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
  gap: 12px;
  margin-bottom: 20px;
}

.info-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px;
  background: rgba(0, 31, 61, 0.02);
  border-radius: 10px;
  transition: all 0.3s ease;

  &:hover {
    background: rgba(237, 152, 95, 0.04);
  }

  &.highlight {
    background: rgba(237, 152, 95, 0.06);
    border: 1px solid rgba(237, 152, 95, 0.15);
  }

  &.warning {
    background: rgba(245, 158, 11, 0.06);
    border: 1px solid rgba(245, 158, 11, 0.15);
  }

  &.success {
    background: rgba(16, 185, 129, 0.06);
    border: 1px solid rgba(16, 185, 129, 0.15);
  }
}

.info-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: linear-gradient(
    135deg,
    rgba(237, 152, 95, 0.12) 0%,
    rgba(247, 185, 128, 0.08) 100%
  );
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;

  .v-icon {
    color: #ed985f !important;
  }
}

.info-content {
  flex: 1;
  min-width: 0;
}

.info-label {
  font-size: 12px;
  font-weight: 600;
  color: rgba(0, 31, 61, 0.6);
  text-transform: uppercase;
  letter-spacing: 0.5px;
  margin-bottom: 4px;
}

.info-value {
  font-size: 15px;
  font-weight: 600;
  color: #001f3d;
}

// Section Styles
.section-title {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 700;
  color: #001f3d;
  margin-bottom: 12px;
  text-transform: uppercase;
  letter-spacing: 0.5px;

  .v-icon {
    color: #ed985f !important;
  }
}

.reason-section,
.remarks-section {
  background: rgba(0, 31, 61, 0.02);
  border-radius: 12px;
  padding: 16px;
  margin-bottom: 16px;
}

.reason-text,
.remarks-text {
  font-size: 14px;
  color: #001f3d;
  line-height: 1.6;
}

// Attachments
.attachments-section {
  margin-bottom: 16px;
}

.attachments-list {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
  gap: 10px;
}

.attachment-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 12px;
  background: rgba(0, 31, 61, 0.02);
  border: 1px solid rgba(0, 31, 61, 0.08);
  border-radius: 10px;
  cursor: pointer;
  transition: all 0.2s ease;

  &:hover {
    background: rgba(237, 152, 95, 0.04);
    border-color: rgba(237, 152, 95, 0.2);
    transform: translateY(-2px);
  }
}

.attachment-info {
  flex: 1;
  min-width: 0;
}

.attachment-name {
  font-size: 13px;
  font-weight: 600;
  color: #001f3d;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.attachment-size {
  font-size: 11px;
  color: #64748b;
  margin-top: 2px;
}

.attachment-delete-btn {
  width: 24px;
  height: 24px;
  border-radius: 6px;
  background: rgba(239, 68, 68, 0.1);
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;

  .v-icon {
    color: #ef4444 !important;
  }

  &:hover {
    background: rgba(239, 68, 68, 0.2);
  }
}

.processed-by {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 12px;
  background: rgba(0, 31, 61, 0.02);
  border-radius: 8px;
  font-size: 13px;
  color: #64748b;

  .v-icon {
    color: #10b981 !important;
  }
}

// Final Pay
.final-pay-content {
  padding: 24px;
}

.pay-grid {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 16px;
  margin-bottom: 16px;
}

.pay-item {
  background: rgba(0, 31, 61, 0.02);
  border-radius: 12px;
  padding: 16px;
}

.pay-label {
  font-size: 12px;
  font-weight: 600;
  color: #64748b;
  text-transform: uppercase;
  margin-bottom: 8px;
}

.pay-value {
  font-size: 22px;
  font-weight: 700;
  color: #001f3d;

  &.success {
    color: #10b981;
  }

  &.primary {
    color: #ed985f;
  }
}

.pay-alert {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border-radius: 10px;
  font-size: 14px;

  &.success {
    background: rgba(16, 185, 129, 0.08);
    color: #10b981;
  }

  &.info {
    background: rgba(59, 130, 246, 0.08);
    color: #3b82f6;
  }
}

// Action Card
.action-content {
  padding: 20px 24px;
}

.action-text {
  font-size: 14px;
  color: #64748b;
  margin-bottom: 16px;
}

.withdraw-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 12px 20px;
  background: rgba(239, 68, 68, 0.1);
  border: 1px solid rgba(239, 68, 68, 0.2);
  border-radius: 10px;
  color: #ef4444;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;

  &:hover {
    background: rgba(239, 68, 68, 0.15);
    border-color: rgba(239, 68, 68, 0.3);
  }

  &:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
}

// Timeline
.timeline-content {
  padding: 20px 24px;
}

.timeline-item {
  display: flex;
  align-items: flex-start;
  gap: 12px;
  padding-bottom: 20px;
  border-left: 2px solid rgba(0, 31, 61, 0.1);
  margin-left: 7px;
  padding-left: 20px;
  position: relative;

  &:last-child {
    border-left-color: transparent;
    padding-bottom: 0;
  }
}

.timeline-dot {
  position: absolute;
  left: -8px;
  width: 14px;
  height: 14px;
  border-radius: 50%;
  border: 3px solid white;
  box-shadow: 0 0 0 2px currentColor;

  &.primary {
    background: #ed985f;
    color: #ed985f;
  }

  &.success {
    background: #10b981;
    color: #10b981;
  }

  &.error {
    background: #ef4444;
    color: #ef4444;
  }
}

.timeline-info {
  flex: 1;
}

.timeline-title {
  font-size: 14px;
  font-weight: 600;
  color: #001f3d;
}

.timeline-date {
  font-size: 12px;
  color: #64748b;
  margin-top: 4px;
}

// Modern Dialog
.modern-dialog {
  background: white;
  border-radius: 16px;
  overflow: hidden;
}

.dialog-header {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 24px;
  background: linear-gradient(
    135deg,
    rgba(0, 31, 61, 0.02) 0%,
    rgba(237, 152, 95, 0.02) 100%
  );
  border-bottom: 1px solid rgba(0, 31, 61, 0.08);
}

.dialog-icon-wrapper {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;

  &.warning {
    background: rgba(245, 158, 11, 0.15);

    .v-icon {
      color: #f59e0b !important;
    }
  }
}

.dialog-title {
  font-size: 18px;
  font-weight: 700;
  color: #001f3d;
}

.dialog-subtitle {
  font-size: 13px;
  color: #64748b;
  margin-top: 2px;
}

.dialog-content {
  padding: 24px;
  font-size: 14px;
  color: #001f3d;
}

.dialog-actions {
  display: flex;
  justify-content: flex-end;
  gap: 12px;
  padding: 16px 24px;
  background: rgba(0, 31, 61, 0.02);
  border-top: 1px solid rgba(0, 31, 61, 0.08);
}

.dialog-btn {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 10px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;
  border: none;

  &.dialog-btn-secondary {
    background: rgba(0, 31, 61, 0.06);
    color: rgba(0, 31, 61, 0.8);

    &:hover {
      background: rgba(0, 31, 61, 0.1);
    }
  }

  &.dialog-btn-danger {
    background: #ef4444;
    color: white;

    &:hover {
      background: #dc2626;
    }

    &:disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }
  }
}

// Attachment Dialog
.attachment-dialog {
  background: white;
  border-radius: 16px;
  overflow: hidden;
}

.attachment-dialog-header {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 16px 20px;
  background: rgba(0, 31, 61, 0.02);
  border-bottom: 1px solid rgba(0, 31, 61, 0.08);
  font-weight: 600;
  color: #001f3d;
}

.icon-btn {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  background: rgba(0, 31, 61, 0.06);
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;

  &:hover {
    background: rgba(0, 31, 61, 0.1);
  }
}

.attachment-dialog-content {
  min-height: 400px;
  max-height: 70vh;
  overflow: auto;
}

.image-preview {
  padding: 20px;
  text-align: center;

  img {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
  }
}

.pdf-preview {
  width: 100%;
  height: 70vh;
  border: none;
}

.no-preview {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 60px 20px;
  text-align: center;
  color: #64748b;
}

.no-preview-text {
  font-size: 18px;
  font-weight: 600;
  color: #001f3d;
  margin: 16px 0 8px;
}

.download-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  margin-top: 20px;
  padding: 12px 24px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: white;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.2s ease;

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);
  }
}
</style>
