<template>
  <v-container fluid>
    <v-row>
      <v-col cols="12">
        <v-card>
          <v-card-title class="d-flex align-center justify-space-between">
            <div>
              <v-icon icon="mdi-calendar-clock" start></v-icon>
              My Leave Requests
            </div>
            <v-btn
              color="primary"
              prepend-icon="mdi-plus"
              @click="openNewLeaveDialog"
            >
              File New Leave
            </v-btn>
          </v-card-title>

          <v-divider></v-divider>

          <!-- Leave Credits Summary -->
          <v-card-text v-if="leaveCredits.length > 0">
            <v-row>
              <v-col
                v-for="credit in leaveCredits"
                :key="credit.leave_type.id"
                cols="12"
                md="3"
              >
                <v-card variant="tonal" color="info">
                  <v-card-text>
                    <div class="text-h6">{{ credit.leave_type.name }}</div>
                    <div class="text-body-2 text-grey">
                      Available: {{ credit.available_credits }} / {{ credit.total_credits }} days
                    </div>
                    <v-progress-linear
                      :model-value="(credit.used_credits / credit.total_credits) * 100"
                      :color="credit.available_credits > 0 ? 'success' : 'error'"
                      height="8"
                      rounded
                      class="mt-2"
                    ></v-progress-linear>
                  </v-card-text>
                </v-card>
              </v-col>
            </v-row>
          </v-card-text>

          <v-divider></v-divider>

          <!-- Filters -->
          <v-card-text>
            <v-row>
              <v-col cols="12" md="4">
                <v-select
                  v-model="filters.status"
                  :items="statusOptions"
                  label="Status"
                  clearable
                  @update:model-value="loadLeaves"
                ></v-select>
              </v-col>
              <v-col cols="12" md="8" class="d-flex justify-end align-center">
                <v-text-field
                  v-model="search"
                  label="Search"
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
          >
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
                icon="mdi-pencil"
                size="small"
                variant="text"
                @click="editLeave(item)"
              ></v-btn>
              <v-btn
                v-if="item.status === 'pending'"
                icon="mdi-delete"
                size="small"
                variant="text"
                color="error"
                @click="deleteLeave(item)"
              ></v-btn>
            </template>
          </v-data-table>
        </v-card>
      </v-col>
    </v-row>

    <!-- New/Edit Leave Dialog -->
    <v-dialog v-model="showLeaveDialog" max-width="600px" persistent>
      <v-card>
        <v-card-title class="bg-primary">
          <v-icon icon="mdi-calendar-plus" start></v-icon>
          {{ editMode ? 'Edit Leave Request' : 'File New Leave Request' }}
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-4">
          <v-form ref="leaveFormRef" v-model="formValid">
            <v-row>
              <v-col cols="12">
                <v-select
                  v-model="formData.leave_type_id"
                  :items="leaveTypes"
                  item-title="name"
                  item-value="id"
                  label="Leave Type *"
                  :rules="[rules.required]"
                  prepend-inner-icon="mdi-calendar-text"
                ></v-select>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="formData.leave_date_from"
                  type="date"
                  label="From Date *"
                  :rules="[rules.required]"
                  :min="minDate"
                  prepend-inner-icon="mdi-calendar-start"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <v-text-field
                  v-model="formData.leave_date_to"
                  type="date"
                  label="To Date *"
                  :rules="[rules.required, rules.endDateAfterStart]"
                  :min="formData.leave_date_from || minDate"
                  prepend-inner-icon="mdi-calendar-end"
                ></v-text-field>
              </v-col>

              <v-col cols="12">
                <v-alert
                  v-if="numberOfDays > 0"
                  type="info"
                  variant="tonal"
                  density="compact"
                >
                  Total Days: {{ numberOfDays }} day(s)
                </v-alert>
              </v-col>

              <v-col cols="12">
                <v-textarea
                  v-model="formData.reason"
                  label="Reason *"
                  :rules="[rules.required]"
                  rows="3"
                  prepend-inner-icon="mdi-text"
                ></v-textarea>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn @click="closeLeaveDialog">Cancel</v-btn>
          <v-btn
            color="primary"
            :loading="saving"
            @click="saveLeave"
          >
            {{ editMode ? 'Update' : 'Submit' }}
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- View Leave Dialog -->
    <v-dialog v-model="showViewDialog" max-width="600px">
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
              <div class="text-body-1">{{ selectedLeave.reason }}</div>
            </v-col>

            <v-col v-if="selectedLeave.approved_by" cols="12">
              <div class="text-caption text-grey">Approved/Rejected By</div>
              <div class="text-body-1">
                {{ selectedLeave.approved_by?.name || 'N/A' }} on 
                {{ formatDateTime(selectedLeave.approved_at) }}
              </div>
            </v-col>

            <v-col v-if="selectedLeave.rejection_reason" cols="12">
              <v-alert type="error" variant="tonal">
                <div class="text-caption">Rejection Reason</div>
                <div>{{ selectedLeave.rejection_reason }}</div>
              </v-alert>
            </v-col>
          </v-row>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn @click="showViewDialog = false">Close</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete Confirmation Dialog -->
    <v-dialog v-model="showDeleteDialog" max-width="400px">
      <v-card>
        <v-card-title class="bg-error">
          <v-icon icon="mdi-delete-alert" start></v-icon>
          Confirm Delete
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-4">
          Are you sure you want to delete this leave request?
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions>
          <v-spacer></v-spacer>
          <v-btn @click="showDeleteDialog = false">Cancel</v-btn>
          <v-btn
            color="error"
            :loading="deleting"
            @click="confirmDelete"
          >
            Delete
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
import { format, parseISO, differenceInDays } from 'date-fns';
import leaveService from '@/services/leaveService';
import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore();

// State
const loading = ref(false);
const saving = ref(false);
const deleting = ref(false);
const leaves = ref([]);
const leaveTypes = ref([]);
const leaveCredits = ref([]);
const search = ref('');
const formValid = ref(false);
const editMode = ref(false);
const showLeaveDialog = ref(false);
const showViewDialog = ref(false);
const showDeleteDialog = ref(false);
const selectedLeave = ref(null);
const leaveToDelete = ref(null);
const leaveFormRef = ref(null);

const filters = ref({
  status: null
});

const formData = ref({
  leave_type_id: null,
  leave_date_from: '',
  leave_date_to: '',
  reason: ''
});

const snackbar = ref({
  show: false,
  message: '',
  color: 'success'
});

// Computed
const minDate = computed(() => {
  return format(new Date(), 'yyyy-MM-dd');
});

const numberOfDays = computed(() => {
  if (formData.value.leave_date_from && formData.value.leave_date_to) {
    const from = new Date(formData.value.leave_date_from);
    const to = new Date(formData.value.leave_date_to);
    return differenceInDays(to, from) + 1;
  }
  return 0;
});

// Table headers
const headers = [
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
  required: v => !!v || 'This field is required',
  endDateAfterStart: v => {
    if (!formData.value.leave_date_from || !v) return true;
    return v >= formData.value.leave_date_from || 'End date must be after start date';
  }
};

// Methods
const loadLeaves = async () => {
  try {
    loading.value = true;
    const params = {};
    if (filters.value.status) {
      params.status = filters.value.status;
    }
    const response = await leaveService.getMyLeaves(params);
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

const loadLeaveCredits = async () => {
  try {
    const response = await leaveService.getMyCredits();
    leaveCredits.value = response.leave_credits || [];
  } catch (error) {
    console.error('Failed to load leave credits:', error);
    // Don't show error to user, just log it
  }
};

const openNewLeaveDialog = () => {
  editMode.value = false;
  formData.value = {
    leave_type_id: null,
    leave_date_from: '',
    leave_date_to: '',
    reason: ''
  };
  showLeaveDialog.value = true;
};

const closeLeaveDialog = () => {
  showLeaveDialog.value = false;
  formData.value = {
    leave_type_id: null,
    leave_date_from: '',
    leave_date_to: '',
    reason: ''
  };
};

const saveLeave = async () => {
  if (!leaveFormRef.value) return;
  
  const { valid } = await leaveFormRef.value.validate();
  if (!valid) return;

  try {
    saving.value = true;
    if (editMode.value && selectedLeave.value) {
      await leaveService.updateLeave(selectedLeave.value.id, formData.value);
      showSnackbar('Leave request updated successfully', 'success');
    } else {
      await leaveService.createLeave(formData.value);
      showSnackbar('Leave request submitted successfully', 'success');
    }
    closeLeaveDialog();
    loadLeaves();
    loadLeaveCredits();
  } catch (error) {
    showSnackbar(error.response?.data?.message || 'Failed to save leave request', 'error');
  } finally {
    saving.value = false;
  }
};

const viewLeave = (leave) => {
  selectedLeave.value = leave;
  showViewDialog.value = true;
};

const editLeave = (leave) => {
  selectedLeave.value = leave;
  editMode.value = true;
  formData.value = {
    leave_type_id: leave.leave_type_id,
    leave_date_from: format(parseISO(leave.leave_date_from), 'yyyy-MM-dd'),
    leave_date_to: format(parseISO(leave.leave_date_to), 'yyyy-MM-dd'),
    reason: leave.reason
  };
  showLeaveDialog.value = true;
};

const deleteLeave = (leave) => {
  leaveToDelete.value = leave;
  showDeleteDialog.value = true;
};

const confirmDelete = async () => {
  try {
    deleting.value = true;
    await leaveService.deleteLeave(leaveToDelete.value.id);
    showSnackbar('Leave request deleted successfully', 'success');
    showDeleteDialog.value = false;
    loadLeaves();
    loadLeaveCredits();
  } catch (error) {
    showSnackbar('Failed to delete leave request', 'error');
  } finally {
    deleting.value = false;
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
  loadLeaveCredits();
});
</script>

<style scoped>
.v-chip {
  font-weight: 500;
}
</style>
