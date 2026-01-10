<template>
  <v-container fluid>
    <v-row>
      <v-col cols="12">
        <v-card>
          <v-card-title class="d-flex align-center">
            <v-icon icon="mdi-check-circle-outline" start></v-icon>
            Leave Approval Management
          </v-card-title>

          <v-divider></v-divider>

          <!-- Stats Cards -->
          <v-card-text>
            <v-row>
              <v-col cols="12" md="3">
                <v-card variant="tonal" color="warning">
                  <v-card-text>
                    <div class="d-flex align-center justify-space-between">
                      <div>
                        <div class="text-h4">{{ pendingCount }}</div>
                        <div class="text-body-2">Pending</div>
                      </div>
                      <v-icon icon="mdi-clock-outline" size="48"></v-icon>
                    </div>
                  </v-card-text>
                </v-card>
              </v-col>
              <v-col cols="12" md="3">
                <v-card variant="tonal" color="success">
                  <v-card-text>
                    <div class="d-flex align-center justify-space-between">
                      <div>
                        <div class="text-h4">{{ approvedCount }}</div>
                        <div class="text-body-2">Approved</div>
                      </div>
                      <v-icon icon="mdi-check-circle" size="48"></v-icon>
                    </div>
                  </v-card-text>
                </v-card>
              </v-col>
              <v-col cols="12" md="3">
                <v-card variant="tonal" color="error">
                  <v-card-text>
                    <div class="d-flex align-center justify-space-between">
                      <div>
                        <div class="text-h4">{{ rejectedCount }}</div>
                        <div class="text-body-2">Rejected</div>
                      </div>
                      <v-icon icon="mdi-close-circle" size="48"></v-icon>
                    </div>
                  </v-card-text>
                </v-card>
              </v-col>
              <v-col cols="12" md="3">
                <v-card variant="tonal" color="info">
                  <v-card-text>
                    <div class="d-flex align-center justify-space-between">
                      <div>
                        <div class="text-h4">{{ totalCount }}</div>
                        <div class="text-body-2">Total Requests</div>
                      </div>
                      <v-icon icon="mdi-file-document-multiple" size="48"></v-icon>
                    </div>
                  </v-card-text>
                </v-card>
              </v-col>
            </v-row>
          </v-card-text>

          <v-divider></v-divider>

          <!-- Filters -->
          <v-card-text>
            <v-row>
              <v-col cols="12" md="3">
                <v-select
                  v-model="filters.status"
                  :items="statusOptions"
                  label="Status"
                  clearable
                  @update:model-value="loadLeaves"
                ></v-select>
              </v-col>
              <v-col cols="12" md="3">
                <v-select
                  v-model="filters.leave_type_id"
                  :items="leaveTypes"
                  item-title="name"
                  item-value="id"
                  label="Leave Type"
                  clearable
                  @update:model-value="loadLeaves"
                ></v-select>
              </v-col>
              <v-col cols="12" md="6" class="d-flex justify-end align-center">
                <v-text-field
                  v-model="search"
                  label="Search by employee"
                  prepend-inner-icon="mdi-magnify"
                  single-line
                  hide-details
                  clearable
                  density="comfortable"
                  style="max-width: 300px"
                ></v-text-field>
              </v-col>
            </v-row>
          </v-card-text>

          <!-- Leave Requests Table -->
          <v-data-table
            :headers="headers"
            :items="leaves"
            :loading="loading"
            :search="search"
            item-value="id"
            class="elevation-1"
          >
            <template #item.employee="{ item }">
              <div class="d-flex align-center py-2">
                <div>
                  <div class="font-weight-bold">
                    {{ item.employee?.first_name }} {{ item.employee?.last_name }}
                  </div>
                  <div class="text-caption text-grey">
                    {{ item.employee?.employee_number }}
                  </div>
                </div>
              </div>
            </template>

            <template #item.leave_type="{ item }">
              <v-chip size="small" color="primary">
                {{ item.leave_type?.name }}
              </v-chip>
            </template>

            <template #item.leave_date_from="{ item }">
              {{ formatDate(item.leave_date_from) }}
            </template>

            <template #item.leave_date_to="{ item }">
              {{ formatDate(item.leave_date_to) }}
            </template>

            <template #item.number_of_days="{ item }">
              <v-chip size="small">
                {{ item.number_of_days }} day(s)
              </v-chip>
            </template>

            <template #item.status="{ item }">
              <v-chip
                size="small"
                :color="getStatusColor(item.status)"
              >
                {{ item.status.toUpperCase() }}
              </v-chip>
            </template>

            <template #item.actions="{ item }">
              <v-btn
                icon="mdi-eye"
                size="small"
                variant="text"
                @click="viewLeave(item)"
              ></v-btn>
              <v-btn
                v-if="item.status === 'pending'"
                icon="mdi-check"
                size="small"
                variant="text"
                color="success"
                @click="openApproveDialog(item)"
              ></v-btn>
              <v-btn
                v-if="item.status === 'pending'"
                icon="mdi-close"
                size="small"
                variant="text"
                color="error"
                @click="openRejectDialog(item)"
              ></v-btn>
            </template>
          </v-data-table>
        </v-card>
      </v-col>
    </v-row>

    <!-- View Leave Dialog -->
    <v-dialog v-model="showViewDialog" max-width="700px">
      <v-card v-if="selectedLeave">
        <v-card-title class="bg-primary">
          <v-icon icon="mdi-calendar-clock" start></v-icon>
          Leave Request Details
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-4">
          <v-row>
            <v-col cols="12">
              <v-chip :color="getStatusColor(selectedLeave.status)" class="mb-3">
                {{ selectedLeave.status.toUpperCase() }}
              </v-chip>
            </v-col>

            <v-col cols="6">
              <div class="text-caption text-grey">Employee</div>
              <div class="text-body-1 font-weight-bold">
                {{ selectedLeave.employee?.first_name }} {{ selectedLeave.employee?.last_name }}
              </div>
              <div class="text-caption">{{ selectedLeave.employee?.employee_number }}</div>
            </v-col>

            <v-col cols="6">
              <div class="text-caption text-grey">Position</div>
              <div class="text-body-1">{{ selectedLeave.employee?.position || 'N/A' }}</div>
            </v-col>

            <v-col cols="6">
              <div class="text-caption text-grey">Leave Type</div>
              <div class="text-body-1">{{ selectedLeave.leave_type?.name }}</div>
            </v-col>

            <v-col cols="6">
              <div class="text-caption text-grey">Number of Days</div>
              <div class="text-body-1">{{ selectedLeave.number_of_days }} day(s)</div>
            </v-col>

            <v-col cols="6">
              <div class="text-caption text-grey">From Date</div>
              <div class="text-body-1">{{ formatDate(selectedLeave.leave_date_from) }}</div>
            </v-col>

            <v-col cols="6">
              <div class="text-caption text-grey">To Date</div>
              <div class="text-body-1">{{ formatDate(selectedLeave.leave_date_to) }}</div>
            </v-col>

            <v-col cols="12">
              <div class="text-caption text-grey">Reason</div>
              <v-card variant="tonal" class="mt-1">
                <v-card-text>{{ selectedLeave.reason }}</v-card-text>
              </v-card>
            </v-col>

            <v-col v-if="selectedLeave.approved_by" cols="12">
              <div class="text-caption text-grey">
                {{ selectedLeave.status === 'approved' ? 'Approved' : 'Rejected' }} By
              </div>
              <div class="text-body-1">
                {{ selectedLeave.approved_by?.name || 'N/A' }} on 
                {{ formatDateTime(selectedLeave.approved_at) }}
              </div>
            </v-col>

            <v-col v-if="selectedLeave.rejection_reason && selectedLeave.status === 'rejected'" cols="12">
              <v-alert type="error" variant="tonal">
                <div class="text-caption font-weight-bold">Rejection Reason</div>
                <div>{{ selectedLeave.rejection_reason }}</div>
              </v-alert>
            </v-col>
          </v-row>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn
            v-if="selectedLeave.status === 'pending'"
            color="success"
            prepend-icon="mdi-check"
            @click="openApproveDialog(selectedLeave)"
          >
            Approve
          </v-btn>
          <v-btn
            v-if="selectedLeave.status === 'pending'"
            color="error"
            prepend-icon="mdi-close"
            @click="openRejectDialog(selectedLeave)"
          >
            Reject
          </v-btn>
          <v-btn @click="showViewDialog = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Approve Dialog -->
    <v-dialog v-model="showApproveDialog" max-width="500px" persistent>
      <v-card>
        <v-card-title class="bg-success">
          <v-icon icon="mdi-check-circle" start></v-icon>
          Approve Leave Request
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-4">
          <p class="mb-4">
            Are you sure you want to approve this leave request for 
            <strong>{{ selectedLeave?.employee?.first_name }} {{ selectedLeave?.employee?.last_name }}</strong>?
          </p>

          <v-alert type="info" variant="tonal" class="mb-4">
            <div><strong>Leave Type:</strong> {{ selectedLeave?.leave_type?.name }}</div>
            <div><strong>Duration:</strong> {{ selectedLeave?.number_of_days }} day(s)</div>
            <div>
              <strong>Dates:</strong> 
              {{ formatDate(selectedLeave?.leave_date_from) }} to 
              {{ formatDate(selectedLeave?.leave_date_to) }}
            </div>
          </v-alert>

          <v-textarea
            v-model="approveData.remarks"
            label="Remarks (Optional)"
            rows="3"
            outlined
          ></v-textarea>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn @click="closeApproveDialog">Cancel</v-btn>
          <v-btn
            color="success"
            :loading="approving"
            @click="confirmApprove"
          >
            Approve
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Reject Dialog -->
    <v-dialog v-model="showRejectDialog" max-width="500px" persistent>
      <v-card>
        <v-card-title class="bg-error">
          <v-icon icon="mdi-close-circle" start></v-icon>
          Reject Leave Request
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-4">
          <p class="mb-4">
            Are you sure you want to reject this leave request for 
            <strong>{{ selectedLeave?.employee?.first_name }} {{ selectedLeave?.employee?.last_name }}</strong>?
          </p>

          <v-textarea
            v-model="rejectData.rejection_reason"
            label="Rejection Reason *"
            rows="3"
            :rules="[rules.required]"
            outlined
          ></v-textarea>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn @click="closeRejectDialog">Cancel</v-btn>
          <v-btn
            color="error"
            :loading="rejecting"
            :disabled="!rejectData.rejection_reason"
            @click="confirmReject"
          >
            Reject
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Snackbar for notifications -->
    <v-snackbar
      v-model="snackbar.show"
      :color="snackbar.color"
      :timeout="3000"
    >
      {{ snackbar.message }}
    </v-snackbar>
  </v-container>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import { format, parseISO } from 'date-fns';
import leaveService from '@/services/leaveService';

// State
const loading = ref(false);
const approving = ref(false);
const rejecting = ref(false);
const leaves = ref([]);
const leaveTypes = ref([]);
const search = ref('');
const showViewDialog = ref(false);
const showApproveDialog = ref(false);
const showRejectDialog = ref(false);
const selectedLeave = ref(null);

const filters = ref({
  status: null,
  leave_type_id: null
});

const approveData = ref({
  remarks: ''
});

const rejectData = ref({
  rejection_reason: ''
});

const snackbar = ref({
  show: false,
  message: '',
  color: 'success'
});

// Computed
const pendingCount = computed(() => {
  return leaves.value.filter(l => l.status === 'pending').length;
});

const approvedCount = computed(() => {
  return leaves.value.filter(l => l.status === 'approved').length;
});

const rejectedCount = computed(() => {
  return leaves.value.filter(l => l.status === 'rejected').length;
});

const totalCount = computed(() => {
  return leaves.value.length;
});

// Table headers
const headers = [
  { title: 'Employee', key: 'employee', sortable: true },
  { title: 'Leave Type', key: 'leave_type', sortable: true },
  { title: 'From Date', key: 'leave_date_from', sortable: true },
  { title: 'To Date', key: 'leave_date_to', sortable: true },
  { title: 'Days', key: 'number_of_days', sortable: true, align: 'center' },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, align: 'center' }
];

const statusOptions = [
  { title: 'All', value: null },
  { title: 'Pending', value: 'pending' },
  { title: 'Approved', value: 'approved' },
  { title: 'Rejected', value: 'rejected' }
];

// Validation rules
const rules = {
  required: v => !!v || 'This field is required'
};

// Methods
const loadLeaves = async () => {
  try {
    loading.value = true;
    const params = {};
    if (filters.value.status) {
      params.status = filters.value.status;
    }
    if (filters.value.leave_type_id) {
      params.leave_type_id = filters.value.leave_type_id;
    }
    const response = await leaveService.getLeaves(params);
    leaves.value = response.data || response;
  } catch (error) {
    showSnackbar('Failed to load leave requests', 'error');
  } finally {
    loading.value = false;
  }
};

const loadLeaveTypes = async () => {
  try {
    const response = await leaveService.getLeaveTypes();
    leaveTypes.value = response.data || response;
  } catch (error) {
    showSnackbar('Failed to load leave types', 'error');
  }
};

const viewLeave = (leave) => {
  selectedLeave.value = leave;
  showViewDialog.value = true;
};

const openApproveDialog = (leave) => {
  selectedLeave.value = leave;
  approveData.value = { remarks: '' };
  showViewDialog.value = false;
  showApproveDialog.value = true;
};

const closeApproveDialog = () => {
  showApproveDialog.value = false;
  approveData.value = { remarks: '' };
};

const confirmApprove = async () => {
  try {
    approving.value = true;
    await leaveService.approveLeave(selectedLeave.value.id, approveData.value);
    showSnackbar('Leave request approved successfully', 'success');
    closeApproveDialog();
    loadLeaves();
  } catch (error) {
    showSnackbar(error.response?.data?.message || 'Failed to approve leave request', 'error');
  } finally {
    approving.value = false;
  }
};

const openRejectDialog = (leave) => {
  selectedLeave.value = leave;
  rejectData.value = { rejection_reason: '' };
  showViewDialog.value = false;
  showRejectDialog.value = true;
};

const closeRejectDialog = () => {
  showRejectDialog.value = false;
  rejectData.value = { rejection_reason: '' };
};

const confirmReject = async () => {
  if (!rejectData.value.rejection_reason) {
    showSnackbar('Please provide a rejection reason', 'error');
    return;
  }

  try {
    rejecting.value = true;
    await leaveService.rejectLeave(selectedLeave.value.id, rejectData.value);
    showSnackbar('Leave request rejected', 'success');
    closeRejectDialog();
    loadLeaves();
  } catch (error) {
    showSnackbar(error.response?.data?.message || 'Failed to reject leave request', 'error');
  } finally {
    rejecting.value = false;
  }
};

const formatDate = (date) => {
  if (!date) return 'N/A';
  try {
    return format(parseISO(date), 'MMM dd, yyyy');
  } catch {
    return date;
  }
};

const formatDateTime = (date) => {
  if (!date) return 'N/A';
  try {
    return format(parseISO(date), 'MMM dd, yyyy hh:mm a');
  } catch {
    return date;
  }
};

const getStatusColor = (status) => {
  const colors = {
    pending: 'warning',
    approved: 'success',
    rejected: 'error',
    cancelled: 'grey'
  };
  return colors[status] || 'grey';
};

const showSnackbar = (message, color = 'success') => {
  snackbar.value = {
    show: true,
    message,
    color
  };
};

// Lifecycle
onMounted(() => {
  loadLeaves();
  loadLeaveTypes();
});
</script>

<style scoped>
.v-chip {
  font-weight: 500;
}
</style>
