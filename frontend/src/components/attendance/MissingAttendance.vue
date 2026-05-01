<template>
  <div>
    <!-- Filters -->
    <v-card-text>
      <v-row>
        <v-col cols="12" md="3">
          <v-text-field
            v-model="selectedDate"
            label="Select Date"
            type="date"
            variant="outlined"
            density="compact"
            prepend-inner-icon="mdi-calendar"
            hide-details
          ></v-text-field>
        </v-col>
        <v-col cols="12" md="3">
          <v-select
            v-model="filterType"
            :items="filterOptions"
            label="Filter By Issue Type"
            variant="outlined"
            density="compact"
            hide-details
          ></v-select>
        </v-col>
        <v-col cols="12" md="6">
          <v-text-field
            v-model="searchEmployee"
            label="Search Employee"
            variant="outlined"
            density="compact"
            prepend-inner-icon="mdi-magnify"
            clearable
            hide-details
          ></v-text-field>
        </v-col>
      </v-row>

      <!-- Access Notice for Non-Admin Users -->
      <v-row v-if="!isAdminOrHr && selectedDate" class="mt-4">
        <v-col cols="12">
          <!-- Pending Request Notice -->
          <v-alert
            v-if="accessStatus === 'pending'"
            type="info"
            variant="tonal"
            prominent
            border="start"
            icon="mdi-clock-outline"
          >
            <v-alert-title>Request Pending Approval</v-alert-title>
            <p class="mb-0 mt-1">
              Your request to modify attendance for
              <strong>{{ formatDate(selectedDate) }}</strong> is pending admin
              approval. You will be able to modify records once approved.
            </p>
          </v-alert>

          <!-- Rejected Request Notice -->
          <v-alert
            v-else-if="accessStatus === 'rejected'"
            type="error"
            variant="tonal"
            prominent
            border="start"
            icon="mdi-close-circle-outline"
          >
            <v-alert-title>Request Rejected</v-alert-title>
            <p class="mb-1 mt-1">
              Your request to modify attendance for
              <strong>{{ formatDate(selectedDate) }}</strong> was rejected.
            </p>
            <p v-if="accessMessage" class="mb-2 text-caption">
              <strong>Reason:</strong> {{ accessMessage }}
            </p>
            <v-btn
              size="small"
              variant="outlined"
              color="error"
              prepend-icon="mdi-refresh"
              @click="showRequestDialog = true"
            >
              Submit New Request
            </v-btn>
          </v-alert>

          <!-- No Request - Must Request Access -->
          <v-alert
            v-else-if="accessStatus === 'none'"
            type="warning"
            variant="tonal"
            prominent
            border="start"
            icon="mdi-lock-outline"
          >
            <v-alert-title>Approval Required</v-alert-title>
            <p class="mb-2 mt-1">
              You must request approval from an administrator before you can
              modify missing attendance records for
              <strong>{{ formatDate(selectedDate) }}</strong
              >.
            </p>
            <v-btn
              size="small"
              color="warning"
              variant="flat"
              prepend-icon="mdi-send"
              @click="showRequestDialog = true"
            >
              Request Access
            </v-btn>
          </v-alert>

          <!-- Approved - Access Granted -->
          <v-alert
            v-else-if="accessStatus === 'approved'"
            type="success"
            variant="tonal"
            border="start"
            icon="mdi-check-circle-outline"
            density="compact"
            closable
          >
            Access granted — you can modify attendance records for
            <strong>{{ formatDate(selectedDate) }}</strong
            >.
          </v-alert>
        </v-col>
      </v-row>

      <!-- My Requests History (for non-admin users) -->
      <v-row v-if="!isAdminOrHr && myRequests.length > 0" class="mt-2">
        <v-col cols="12">
          <v-expansion-panels variant="accordion">
            <v-expansion-panel>
              <v-expansion-panel-title>
                <v-icon size="20" class="mr-2">mdi-history</v-icon>
                My Modification Requests
                <v-chip
                  size="x-small"
                  class="ml-2"
                  color="primary"
                  variant="tonal"
                >
                  {{ myRequests.length }}
                </v-chip>
              </v-expansion-panel-title>
              <v-expansion-panel-text>
                <v-table density="compact">
                  <thead>
                    <tr>
                      <th>Date</th>
                      <th>Reason</th>
                      <th>Status</th>
                      <th>Reviewed By</th>
                      <th>Notes</th>
                    </tr>
                  </thead>
                  <tbody>
                    <tr v-for="req in myRequests" :key="req.id">
                      <td>{{ formatDate(req.date) }}</td>
                      <td>{{ req.reason }}</td>
                      <td>
                        <v-chip
                          :color="getStatusColor(req.status)"
                          size="x-small"
                          variant="flat"
                        >
                          {{ req.status }}
                        </v-chip>
                      </td>
                      <td>{{ req.reviewer?.name || "—" }}</td>
                      <td>{{ req.review_notes || "—" }}</td>
                    </tr>
                  </tbody>
                </v-table>
              </v-expansion-panel-text>
            </v-expansion-panel>
          </v-expansion-panels>
        </v-col>
      </v-row>

      <!-- Summary Cards -->
      <v-row v-if="summary && hasAccess" class="mt-4">
        <v-col cols="12" md="3">
          <v-card flat class="text-center pa-2" color="blue-grey-lighten-5">
            <div class="text-h5 font-weight-bold text-blue-grey">
              {{ summary.total_employees }}
            </div>
            <div class="text-caption text-medium-emphasis">
              Total Active Employees
            </div>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card flat class="text-center pa-2" color="blue-lighten-5">
            <div class="text-h5 font-weight-bold text-blue">
              {{ summary.total_attendance_records }}
            </div>
            <div class="text-caption text-medium-emphasis">
              With Attendance Records
            </div>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card flat class="text-center pa-2" color="red-lighten-5">
            <div class="text-h5 font-weight-bold text-red">
              {{ summary.employees_with_issues }}
            </div>
            <div class="text-caption text-medium-emphasis">
              With Issues / Missing
            </div>
          </v-card>
        </v-col>
        <v-col cols="12" md="3">
          <v-card flat class="text-center pa-2" color="green-lighten-5">
            <div class="text-h5 font-weight-bold text-green">
              {{ summary.total_employees - summary.employees_with_issues }}
            </div>
            <div class="text-caption text-medium-emphasis">Complete / OK</div>
          </v-card>
        </v-col>
      </v-row>
    </v-card-text>

    <!-- Data Table (only shown when user has access) -->
    <v-data-table
      v-if="hasAccess"
      :headers="headers"
      :items="filteredRecords"
      :loading="loading"
      :items-per-page="15"
      :items-per-page-options="[
        { value: 10, title: '10' },
        { value: 15, title: '15' },
        { value: 25, title: '25' },
        { value: 50, title: '50' },
        { value: -1, title: 'All' },
      ]"
      class="elevation-0"
    >
      <template v-slot:item.employee_number="{ item }">
        <div>
          <div class="font-weight-medium">{{ item.full_name }}</div>
          <div class="text-caption text-medium-emphasis">
            <v-chip size="x-small" variant="tonal">
              {{ item.employee_number }}
            </v-chip>
          </div>
        </div>
      </template>

      <template v-slot:item.position="{ item }">
        <div class="text-truncate" style="max-width: 120px">
          {{ item.position }}
        </div>
      </template>

      <template v-slot:item.department="{ item }">
        <div class="text-truncate" style="max-width: 130px">
          {{ item.department }}
        </div>
      </template>

      <template v-slot:item.issues="{ item }">
        <div class="d-flex flex-wrap gap-1">
          <v-chip
            v-for="(issue, idx) in item.issues"
            :key="idx"
            :color="getIssueColor(issue)"
            size="small"
            :prepend-icon="getIssueIcon(issue)"
          >
            {{ issue }}
          </v-chip>
        </div>
      </template>

      <template v-slot:item.time_in="{ item }">
        <v-chip
          v-if="item.attendance?.time_in"
          size="small"
          color="success"
          variant="tonal"
          prepend-icon="mdi-login"
        >
          {{ item.attendance.time_in }}
        </v-chip>
        <span v-else class="text-medium-emphasis">--:--</span>
      </template>

      <template v-slot:item.time_out="{ item }">
        <v-chip
          v-if="item.attendance?.time_out"
          size="small"
          color="error"
          variant="tonal"
          prepend-icon="mdi-logout"
        >
          {{ item.attendance.time_out }}
        </v-chip>
        <span v-else class="text-medium-emphasis text-red">Missing</span>
      </template>

      <template v-slot:item.break_start="{ item }">
        <v-chip
          v-if="item.attendance?.break_start"
          size="small"
          color="warning"
          variant="tonal"
          prepend-icon="mdi-coffee"
        >
          {{ item.attendance.break_start }}
        </v-chip>
        <span v-else class="text-medium-emphasis">--:--</span>
      </template>

      <template v-slot:item.break_end="{ item }">
        <v-chip
          v-if="item.attendance?.break_end"
          size="small"
          color="info"
          variant="tonal"
          prepend-icon="mdi-coffee-outline"
        >
          {{ item.attendance.break_end }}
        </v-chip>
        <span
          v-else-if="item.attendance?.break_start"
          class="text-medium-emphasis text-orange"
          >Missing</span
        >
        <span v-else class="text-medium-emphasis">--:--</span>
      </template>

      <template v-slot:item.ot_time_in="{ item }">
        <v-chip
          v-if="item.attendance?.ot_time_in"
          size="small"
          color="purple"
          variant="tonal"
          prepend-icon="mdi-clock-plus"
        >
          {{ item.attendance.ot_time_in }}
        </v-chip>
        <span v-else class="text-medium-emphasis">--:--</span>
      </template>

      <template v-slot:item.ot_time_out="{ item }">
        <v-chip
          v-if="item.attendance?.ot_time_out"
          size="small"
          color="deep-purple"
          variant="tonal"
          prepend-icon="mdi-clock-minus"
        >
          {{ item.attendance.ot_time_out }}
        </v-chip>
        <span
          v-else-if="item.attendance?.ot_time_in"
          class="text-medium-emphasis text-orange"
          >Missing</span
        >
        <span v-else class="text-medium-emphasis">--:--</span>
      </template>

      <template v-slot:item.actions="{ item }">
        <v-menu location="bottom end">
          <template v-slot:activator="{ props }">
            <v-btn
              v-bind="props"
              icon="mdi-dots-vertical"
              size="small"
              variant="text"
            ></v-btn>
          </template>
          <v-list density="compact">
            <v-list-item
              @click="
                $emit('edit-attendance', {
                  attendance: item.attendance,
                  date: selectedDate,
                  employee_id: item.employee_id,
                })
              "
            >
              <template v-slot:prepend>
                <v-icon size="18">mdi-pencil</v-icon>
              </template>
              <v-list-item-title>Edit</v-list-item-title>
            </v-list-item>
            <v-list-item
              v-if="canDeleteAttendance(item)"
              @click="$emit('delete', item.attendance)"
            >
              <template v-slot:prepend>
                <v-icon size="18" color="error">mdi-delete</v-icon>
              </template>
              <v-list-item-title>Delete</v-list-item-title>
            </v-list-item>
          </v-list>
        </v-menu>
      </template>

      <template v-slot:no-data>
        <div class="text-center pa-6">
          <v-icon size="64" :color="selectedDate ? 'success' : 'grey'">
            {{ selectedDate ? "mdi-check-all" : "mdi-calendar-search" }}
          </v-icon>
          <p class="text-h6 mt-4">
            {{
              selectedDate
                ? "No missing attendance found!"
                : "Please select a date"
            }}
          </p>
          <p v-if="selectedDate" class="text-body-2 text-medium-emphasis">
            All attendance records have complete punch data for this date.
          </p>
        </div>
      </template>
    </v-data-table>

    <!-- Request Access Dialog -->
    <v-dialog v-model="showRequestDialog" max-width="500" persistent>
      <v-card rounded="lg">
        <v-card-title class="d-flex align-center pa-4">
          <v-icon color="warning" class="mr-2"
            >mdi-lock-open-variant-outline</v-icon
          >
          Request Attendance Modification Access
        </v-card-title>
        <v-divider></v-divider>
        <v-card-text class="pa-4">
          <p class="text-body-2 mb-4">
            You are requesting access to modify missing attendance records for
            <strong>{{ formatDate(selectedDate) }}</strong
            >. Please provide a reason for this request.
          </p>
          <v-textarea
            v-model="requestReason"
            label="Reason for Request"
            variant="outlined"
            rows="3"
            :rules="[(v) => !!v || 'Reason is required']"
            counter="500"
            maxlength="500"
            placeholder="e.g., Need to update missing time-out records for employees who forgot to punch out"
          ></v-textarea>
        </v-card-text>
        <v-divider></v-divider>
        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn
            variant="text"
            @click="
              showRequestDialog = false;
              requestReason = '';
            "
          >
            Cancel
          </v-btn>
          <v-btn
            color="primary"
            variant="flat"
            :loading="submittingRequest"
            :disabled="!requestReason"
            prepend-icon="mdi-send"
            @click="submitRequest"
          >
            Submit Request
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted, watch } from "vue";
import attendanceService from "@/services/attendanceService";
import { useAuthStore } from "@/stores/auth";
import { onAttendanceUpdate } from "@/stores/attendance";
import { useToast } from "vue-toastification";

const emit = defineEmits(["edit-attendance", "delete"]);
const toast = useToast();
const authStore = useAuthStore();

const loading = ref(false);
const selectedDate = ref(getTodayDate());
const filterType = ref("all");
const searchEmployee = ref("");
const missingRecords = ref([]);
const summary = ref(null);

// Access control state
const accessStatus = ref("none"); // 'none', 'pending', 'approved', 'rejected', 'admin'
const accessMessage = ref("");
const checkingAccess = ref(false);
const showRequestDialog = ref(false);
const requestReason = ref("");
const submittingRequest = ref(false);
const myRequests = ref([]);

const isAdminOrHr = computed(() =>
  ["admin", "hr"].includes(authStore.userRole),
);

const hasAccess = computed(
  () => isAdminOrHr.value || accessStatus.value === "approved",
);

const canDeleteRole = computed(() =>
  ["admin", "hr", "payrollist"].includes(authStore.userRole),
);

const canDeleteAttendance = (item) => {
  if (!canDeleteRole.value) return false;
  if (!item?.attendance?.id) return false;
  return true;
};

const filterOptions = [
  { title: "All Issues", value: "all" },
  { title: "No Attendance Record", value: "no_record" },
  { title: "Missing Time Out", value: "missing_timeout" },
  { title: "Missing Break Out", value: "missing_breakout" },
  { title: "Missing OT Time Out", value: "missing_ot_timeout" },
];

const headers = [
  { title: "Employee", key: "employee_number", sortable: true },
  { title: "Position", key: "position", sortable: true },
  { title: "Project", key: "department", sortable: true },
  { title: "Issues", key: "issues", sortable: false },
  { title: "Time In", key: "time_in", sortable: false },
  { title: "Time Out", key: "time_out", sortable: false },
  { title: "Break Start", key: "break_start", sortable: false },
  { title: "Break End", key: "break_end", sortable: false },
  { title: "OT In", key: "ot_time_in", sortable: false },
  { title: "OT Out", key: "ot_time_out", sortable: false },
  { title: "Actions", key: "actions", sortable: false, align: "center" },
];

function getTodayDate() {
  const today = new Date();
  return today.toISOString().split("T")[0];
}

function formatDate(dateStr) {
  if (!dateStr) return "";
  const d = new Date(dateStr + "T00:00:00");
  return d.toLocaleDateString("en-US", {
    weekday: "long",
    year: "numeric",
    month: "long",
    day: "numeric",
  });
}

function getStatusColor(status) {
  const colors = { pending: "warning", approved: "success", rejected: "error" };
  return colors[status] || "grey";
}

// Check if user has access for the selected date
const checkAccess = async () => {
  if (isAdminOrHr.value) {
    accessStatus.value = "admin";
    return;
  }

  if (!selectedDate.value) return;

  checkingAccess.value = true;
  try {
    const response = await attendanceService.checkModificationAccess(
      selectedDate.value,
    );
    accessStatus.value = response.status;
    accessMessage.value = response.message || "";
  } catch {
    accessStatus.value = "none";
  } finally {
    checkingAccess.value = false;
  }
};

// Load user's own requests
const loadMyRequests = async () => {
  if (isAdminOrHr.value) return;

  try {
    const response = await attendanceService.getModificationRequests();
    myRequests.value = response.data || [];
  } catch {
    myRequests.value = [];
  }
};

// Submit access request
const submitRequest = async () => {
  if (!requestReason.value || !selectedDate.value) return;

  submittingRequest.value = true;
  try {
    await attendanceService.submitModificationRequest({
      date: selectedDate.value,
      reason: requestReason.value,
    });
    toast.success("Access request submitted. Waiting for admin approval.");
    showRequestDialog.value = false;
    requestReason.value = "";
    accessStatus.value = "pending";
    await loadMyRequests();
  } catch (error) {
    const msg = error.response?.data?.message || "Failed to submit request";
    toast.error(msg);
  } finally {
    submittingRequest.value = false;
  }
};

// Computed: Filtered Records based on search
const filteredRecords = computed(() => {
  if (!searchEmployee.value) return missingRecords.value;

  const search = searchEmployee.value.toLowerCase();
  return missingRecords.value.filter((record) => {
    return (
      record.full_name?.toLowerCase().includes(search) ||
      record.employee_number?.toLowerCase().includes(search) ||
      record.position?.toLowerCase().includes(search) ||
      record.department?.toLowerCase().includes(search)
    );
  });
});

const loadMissingAttendance = async () => {
  if (!selectedDate.value) {
    toast.warning("Please select a date");
    return;
  }

  // Check access first for non-admin users
  await checkAccess();

  // Only load data if user has access
  if (!hasAccess.value) {
    // Still load data for display purposes but don't allow editing
    // The table is hidden so just clear the data
    missingRecords.value = [];
    summary.value = null;
    return;
  }

  loading.value = true;
  try {
    const response = await attendanceService.getMissingAttendance({
      date: selectedDate.value,
      type: filterType.value,
    });

    missingRecords.value = response.missing_records || [];
    summary.value = {
      total_employees: response.total_employees,
      total_attendance_records: response.total_attendance_records,
      employees_with_issues: response.employees_with_issues,
    };
  } catch (error) {
    toast.error("Failed to load missing attendance data");
    missingRecords.value = [];
    summary.value = null;
  } finally {
    loading.value = false;
  }
};

const getIssueColor = (issue) => {
  if (issue.includes("No attendance record")) return "blue-grey";
  if (issue.includes("Missing time out")) return "error";
  if (issue.includes("Missing break out")) return "warning";
  if (issue.includes("Missing OT")) return "orange";
  return "grey";
};

const getIssueIcon = (issue) => {
  if (issue.includes("No attendance record")) return "mdi-account-off";
  if (issue.includes("Missing time out")) return "mdi-logout-variant";
  if (issue.includes("Missing break out")) return "mdi-coffee-off";
  if (issue.includes("Missing OT")) return "mdi-clock-alert";
  return "mdi-alert-circle";
};

let unsubscribeAttendance = null;
let onAttendanceChanged = null;

// Auto-fetch when date or filter type changes
watch([selectedDate, filterType], () => {
  if (selectedDate.value) {
    loadMissingAttendance();
  }
});

onMounted(() => {
  loadMissingAttendance();
  loadMyRequests();

  // Keep missing attendance tab in sync after manual create/update actions.
  unsubscribeAttendance = onAttendanceUpdate(() => {
    if (selectedDate.value) {
      loadMissingAttendance();
    }
  });

  onAttendanceChanged = () => {
    if (selectedDate.value) {
      loadMissingAttendance();
    }
  };
  window.addEventListener("attendance-data-changed", onAttendanceChanged);
});

onUnmounted(() => {
  if (unsubscribeAttendance) {
    unsubscribeAttendance();
  }
  if (onAttendanceChanged) {
    window.removeEventListener("attendance-data-changed", onAttendanceChanged);
  }
});
</script>

<style scoped>
.summary-card {
  text-align: center;
  min-height: 100px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.gap-1 {
  gap: 4px;
}
</style>
