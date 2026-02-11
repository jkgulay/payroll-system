<template>
  <div class="allowance-page">
    <div class="modern-card">
      <!-- Modern Page Header -->
      <div class="page-header">
        <div class="page-icon-badge">
          <v-icon icon="mdi-food" size="24" color="white"></v-icon>
        </div>
        <div class="page-header-content">
          <h1 class="page-title">Allowance Management</h1>
          <p class="page-subtitle">
            Create and manage employee allowance distributions
          </p>
        </div>
        <button
          v-if="canCreate"
          class="action-btn action-btn-primary"
          @click="openCreateDialog"
        >
          <v-icon size="20">mdi-plus</v-icon>
          <span>Create New Allowance</span>
        </button>
      </div>

      <!-- Filters Section -->
      <div class="filters-section">
        <v-row>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.status"
              :items="statusOptions"
              label="Status"
              variant="outlined"
              density="comfortable"
              clearable
              hide-details
              @update:model-value="fetchAllowances"
            ></v-select>
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.position_id"
              :items="positions"
              item-title="position_name"
              item-value="id"
              label="Position"
              variant="outlined"
              density="comfortable"
              clearable
              hide-details
              @update:model-value="fetchAllowances"
            ></v-select>
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field
              v-model="filters.search"
              label="Search by reference or title"
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="comfortable"
              clearable
              hide-details
              @update:model-value="fetchAllowances"
            ></v-text-field>
          </v-col>
        </v-row>
      </div>

      <!-- Data Table -->
      <v-data-table
        :headers="headers"
        :items="allowances"
        :loading="loading"
        class="modern-table"
      >
        <template #[`item.reference_number`]="{ item }">
          <div>
            <strong>{{ item.reference_number }}</strong>
            <div class="text-caption text-grey">
              Created: {{ formatDate(item.created_at) }}
            </div>
          </div>
        </template>

        <template #[`item.period`]="{ item }">
          <div class="text-body-2">
            {{ formatDate(item.period_start) }} -
            {{ formatDate(item.period_end) }}
          </div>
        </template>

        <template #[`item.position`]="{ item }">
          {{ item.position?.position_name || "All Positions" }}
        </template>

        <template #[`item.employee_count`]="{ item }">
          <v-chip size="small" color="info">
            {{ item.items?.length || 0 }}
          </v-chip>
        </template>

        <template #[`item.total_amount`]="{ item }">
          <strong>₱{{ formatNumber(calculateTotal(item)) }}</strong>
        </template>

        <template #[`item.status`]="{ item }">
          <v-chip :color="getStatusColor(item.status)" size="small">
            {{ item.status.replace("_", " ").toUpperCase() }}
          </v-chip>
        </template>

        <template #[`item.actions`]="{ item }">
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
              <v-list-item @click="viewDetails(item)">
                <template #prepend>
                  <v-icon size="18">mdi-eye</v-icon>
                </template>
                <v-list-item-title>View Details</v-list-item-title>
              </v-list-item>

              <v-list-item
                v-if="
                  (item.status === 'draft' && canEdit) ||
                  (item.status !== 'draft' && isAdmin)
                "
                @click="editAllowance(item)"
              >
                <template #prepend>
                  <v-icon size="18" color="primary">mdi-pencil</v-icon>
                </template>
                <v-list-item-title>Edit</v-list-item-title>
              </v-list-item>

              <v-list-item
                v-if="item.status === 'draft' && canSubmit && !item._submitted"
                @click="submitForApproval(item)"
              >
                <template #prepend>
                  <v-icon size="18" color="success">mdi-send</v-icon>
                </template>
                <v-list-item-title>Submit for Approval</v-list-item-title>
              </v-list-item>

              <v-list-item
                v-if="item.status === 'pending_approval' && canApprove"
                @click="openApprovalDialog(item)"
              >
                <template #prepend>
                  <v-icon size="18" color="success">mdi-check-circle</v-icon>
                </template>
                <v-list-item-title>Approve/Reject</v-list-item-title>
              </v-list-item>

              <v-list-item
                v-if="
                  item.status === 'approved' && canApprove && !item.pdf_path
                "
                @click="generatePdf(item)"
              >
                <template #prepend>
                  <v-icon size="18" color="orange">mdi-file-pdf-box</v-icon>
                </template>
                <v-list-item-title>Generate PDF</v-list-item-title>
              </v-list-item>

              <v-list-item
                v-if="item.status === 'approved' && item.pdf_path"
                @click="downloadPdf(item)"
              >
                <template #prepend>
                  <v-icon size="18" color="error">mdi-download</v-icon>
                </template>
                <v-list-item-title>Download PDF</v-list-item-title>
              </v-list-item>

              <v-divider></v-divider>

              <v-list-item
                v-if="(item.status === 'draft' && canDelete) || isAdmin"
                @click="deleteAllowance(item)"
              >
                <template #prepend>
                  <v-icon size="18" color="error">mdi-delete</v-icon>
                </template>
                <v-list-item-title>Delete</v-list-item-title>
              </v-list-item>
            </v-list>
          </v-menu>
        </template>
      </v-data-table>
    </div>

    <!-- Create/Edit Dialog -->
    <AllowanceForm
      v-model="showFormDialog"
      :meal-allowance="selectedAllowance"
      :positions="positions"
      @saved="onSaved"
    />

    <!-- View Details Dialog -->
    <AllowanceDetails
      v-model="showDetailsDialog"
      :meal-allowance="selectedAllowance"
    />

    <!-- Approval Dialog -->
    <AllowanceApproval
      v-model="showApprovalDialog"
      :meal-allowance="selectedAllowance"
      @approved="onApproved"
    />
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useToast } from "vue-toastification";
import { useAuthStore } from "@/stores/auth";
import allowanceService from "@/services/allowanceService";
import AllowanceForm from "@/components/allowance/AllowanceForm.vue";
import AllowanceDetails from "@/components/allowance/AllowanceDetails.vue";
import AllowanceApproval from "@/components/allowance/AllowanceApproval.vue";
import { formatDate, formatNumber } from "@/utils/formatters";
import { devLog } from "@/utils/devLog";
import { useConfirmDialog } from "@/composables/useConfirmDialog";

const toast = useToast();
const { confirm: confirmDialog } = useConfirmDialog();

const authStore = useAuthStore();
const loading = ref(false);
const allowances = ref([]);
const positions = ref([]);
const selectedAllowance = ref(null);
const showFormDialog = ref(false);
const showDetailsDialog = ref(false);
const showApprovalDialog = ref(false);

const filters = ref({
  status: null,
  position_id: null,
  search: "",
});

const statusOptions = [
  { title: "Draft", value: "draft" },
  { title: "Pending Approval", value: "pending_approval" },
  { title: "Approved", value: "approved" },
  { title: "Rejected", value: "rejected" },
];

const headers = [
  { title: "Reference No.", key: "reference_number", sortable: true },
  { title: "Title", key: "title", sortable: true },
  { title: "Period", key: "period", sortable: true },
  { title: "Position", key: "position", sortable: true },
  {
    title: "No. of Employees",
    key: "employee_count",
    sortable: true,
    align: "center",
  },
  { title: "Total Amount", key: "total_amount", sortable: true },
  { title: "Status", key: "status", sortable: true },
  { title: "Actions", key: "actions", sortable: false, align: "center" },
];

const canCreate = computed(() =>
  ["admin", "hr", "payrollist"].includes(authStore.user?.role),
);
const canEdit = computed(() =>
  ["admin", "hr", "payrollist"].includes(authStore.user?.role),
);
const canSubmit = computed(() =>
  ["admin", "hr", "payrollist"].includes(authStore.user?.role),
);
const canApprove = computed(() => authStore.user?.role === "admin");
const canDelete = computed(() =>
  ["admin", "hr", "payrollist"].includes(authStore.user?.role),
);
const isAdmin = computed(() => authStore.user?.role === "admin");

onMounted(async () => {
  await fetchPositions();
  await fetchAllowances();
});

async function fetchAllowances() {
  loading.value = true;
  try {
    const response = await allowanceService.getAll(filters.value);
    allowances.value = response.data || response;
  } catch (error) {
    devLog.error("Error fetching allowances:", error);
    allowances.value = [];
  } finally {
    loading.value = false;
  }
}

async function fetchPositions() {
  try {
    positions.value = await allowanceService.getPositions();
  } catch (error) {
    devLog.error("Error fetching positions:", error);
  }
}

function openCreateDialog() {
  selectedAllowance.value = null;
  showFormDialog.value = true;
}

function editAllowance(item) {
  selectedAllowance.value = item;
  showFormDialog.value = true;
}

function viewDetails(item) {
  selectedAllowance.value = item;
  showDetailsDialog.value = true;
}

function openApprovalDialog(item) {
  selectedAllowance.value = item;
  showApprovalDialog.value = true;
}

async function submitForApproval(item) {
  if (!(await confirmDialog("Submit this allowance for approval?"))) return;

  try {
    await allowanceService.submit(item.id);
    toast.success("Allowance submitted for approval");
    await fetchAllowances();
  } catch (error) {
    devLog.error("Error submitting allowance:", error);
    toast.error("Failed to submit allowance");
  }
}

async function generatePdf(item) {
  if (!(await confirmDialog(`Generate PDF for ${item.reference_number}?`)))
    return;

  loading.value = true;
  try {
    const response = await allowanceService.generatePdf(item.id);
    toast.success("PDF generated successfully");
    await fetchAllowances();
  } catch (error) {
    devLog.error("Error generating PDF:", error);
    toast.error(error.response?.data?.message || "Failed to generate PDF");
  } finally {
    loading.value = false;
  }
}

async function downloadPdf(item) {
  try {
    loading.value = true;
    const blob = await allowanceService.downloadPdf(item.id);
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement("a");
    link.href = url;
    link.download = `${item.reference_number}.pdf`;
    link.click();
    window.URL.revokeObjectURL(url);
  } catch (error) {
    devLog.error("Error downloading PDF:", error);
    toast.error("Failed to download PDF. Please generate it first.");
  } finally {
    loading.value = false;
  }
}

async function deleteAllowance(item) {
  const isApproved = item.status === "approved";
  const message = isApproved
    ? `⚠️ WARNING: This allowance (${item.reference_number}) has been APPROVED and may have been used in payroll calculations. Are you absolutely sure you want to delete it?`
    : `Delete allowance ${item.reference_number}?`;

  if (!(await confirmDialog(message))) return;

  try {
    await allowanceService.delete(item.id);
    toast.success("Allowance deleted successfully");
    await fetchAllowances();
  } catch (error) {
    devLog.error("Error deleting allowance:", error);
    toast.error(error.response?.data?.message || "Failed to delete allowance");
  }
}

function onSaved() {
  showFormDialog.value = false;
  fetchAllowances();
}

function onApproved() {
  showApprovalDialog.value = false;
  fetchAllowances();
}

function calculateTotal(allowance) {
  if (!allowance.items || !Array.isArray(allowance.items)) {
    return 0;
  }
  return allowance.items.reduce((total, item) => {
    return (
      total +
      parseFloat(item.no_of_days || 0) * parseFloat(item.amount_per_day || 0)
    );
  }, 0);
}

function getStatusColor(status) {
  const colors = {
    draft: "grey",
    pending_approval: "orange",
    approved: "success",
    rejected: "error",
  };
  return colors[status] || "grey";
}
</script>
<style lang="scss" scoped>
.allowance-page {
  background-color: #f8f9fa;
  min-height: 100vh;
}

.modern-card {
  padding: 24px;
  background: white;
  border-radius: 16px;
  overflow: hidden;
  border: 1px solid rgba(0, 31, 61, 0.08);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.page-header {
  display: flex;
  align-items: center;
  gap: 20px;
  margin-bottom: 32px;
  padding-bottom: 24px;
  border-bottom: 1px solid rgba(0, 31, 61, 0.08);
}

.page-icon-badge {
  width: 48px;
  height: 48px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.page-header-content {
  flex: 1;
}

.page-title {
  font-size: 24px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
  line-height: 1.2;
}

.page-subtitle {
  font-size: 14px;
  color: #64748b;
  margin: 4px 0 0 0;
}

.action-button {
  text-transform: none;
  font-weight: 600;
  letter-spacing: 0;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.2);
  transition: all 0.2s ease;

  &:hover {
    box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);
    transform: translateY(-1px);
  }
}

.filters-section {
  margin-bottom: 24px;
}

.modern-table {
  border-radius: 12px;
  overflow: hidden;

  :deep(th) {
    background-color: #f8f9fa !important;
    color: #001f3d !important;
    font-weight: 600 !important;
    text-transform: uppercase;
    font-size: 12px;
    letter-spacing: 0.5px;
  }

  :deep(.v-data-table__tr:hover) {
    background-color: rgba(237, 152, 95, 0.04) !important;
  }
}

.action-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 20px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
  white-space: nowrap;

  .v-icon {
    flex-shrink: 0;
  }

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(237, 152, 95, 0.25);
  }

  &.action-btn-primary {
    background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);

    .v-icon {
      color: #ffffff !important;
    }
  }

  &.action-btn-secondary {
    background: rgba(237, 152, 95, 0.1);
    color: #ed985f;
    border: 1px solid rgba(237, 152, 95, 0.2);

    .v-icon {
      color: #ed985f !important;
    }

    &:hover {
      background: rgba(237, 152, 95, 0.15);
      border-color: rgba(237, 152, 95, 0.3);
    }
  }
}
</style>
