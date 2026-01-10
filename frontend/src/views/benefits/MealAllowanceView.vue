<template>
  <v-container fluid>
    <v-card>
      <v-card-title class="d-flex align-center">
        <v-icon left color="primary">mdi-food</v-icon>
        <span>Meal Allowance Management</span>
        <v-spacer></v-spacer>
        <v-btn 
          v-if="canCreate"
          color="primary" 
          @click="openCreateDialog"
        >
          <v-icon left>mdi-plus</v-icon>
          Create New Meal Allowance
        </v-btn>
      </v-card-title>

      <!-- Filters -->
      <v-card-text>
        <v-row>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.status"
              :items="statusOptions"
              label="Status"
              clearable
              dense
              outlined
              @update:model-value="fetchMealAllowances"
            ></v-select>
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.position_id"
              :items="positions"
              item-title="position_name"
              item-value="id"
              label="Position"
              clearable
              dense
              outlined
              @update:model-value="fetchMealAllowances"
            ></v-select>
          </v-col>
          <v-col cols="12" md="4">
            <v-text-field
              v-model="filters.search"
              label="Search by reference or title"
              append-inner-icon="mdi-magnify"
              dense
              outlined
              clearable
              @update:model-value="fetchMealAllowances"
            ></v-text-field>
          </v-col>
        </v-row>
      </v-card-text>

      <!-- Data Table -->
      <v-data-table
        :headers="headers"
        :items="mealAllowances"
        :loading="loading"
        class="elevation-1"
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
            {{ formatDate(item.period_start) }} - {{ formatDate(item.period_end) }}
          </div>
        </template>

        <template #[`item.position`]="{ item }">
          {{ item.position?.position_name || 'All Positions' }}
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
            {{ item.status.replace('_', ' ').toUpperCase() }}
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
          
          <v-tooltip text="Edit" location="top">
            <template v-slot:activator="{ props }">
              <v-btn 
                v-if="item.status === 'draft' && canEdit"
                v-bind="props"
                icon="mdi-pencil" 
                size="small" 
                variant="text"
                color="primary"
                @click="editMealAllowance(item)"
              ></v-btn>
            </template>
          </v-tooltip>
          
          <v-tooltip text="Submit for Approval" location="top">
            <template v-slot:activator="{ props }">
              <v-btn 
                v-if="item.status === 'draft' && canSubmit && !item._submitted"
                v-bind="props"
                icon="mdi-send" 
                size="small" 
                variant="text"
                color="success"
                @click="submitForApproval(item)"
              ></v-btn>
            </template>
          </v-tooltip>
          
          <v-tooltip text="Approve/Reject" location="top">
            <template v-slot:activator="{ props }">
              <v-btn 
                v-if="item.status === 'pending_approval' && canApprove"
                v-bind="props"
                icon="mdi-check-circle" 
                size="small" 
                variant="text"
                color="success"
                @click="openApprovalDialog(item)"
              ></v-btn>
            </template>
          </v-tooltip>
          
          <v-tooltip text="Generate PDF" location="top">
            <template v-slot:activator="{ props }">
              <v-btn 
                v-if="item.status === 'approved' && canApprove && !item.pdf_path"
                v-bind="props"
                icon="mdi-file-pdf-box" 
                size="small" 
                variant="text"
                color="orange"
                @click="generatePdf(item)"
              ></v-btn>
            </template>
          </v-tooltip>
          
          <v-tooltip text="Download PDF" location="top">
            <template v-slot:activator="{ props }">
              <v-btn 
                v-if="item.status === 'approved' && item.pdf_path"
                v-bind="props"
                icon="mdi-download" 
                size="small" 
                variant="text"
                color="error"
                @click="downloadPdf(item)"
              ></v-btn>
            </template>
          </v-tooltip>
          
          <v-tooltip text="Delete" location="top">
            <template v-slot:activator="{ props }">
              <v-btn 
                v-if="item.status === 'draft' && canDelete"
                v-bind="props"
                icon="mdi-delete" 
                size="small" 
                variant="text"
                color="error"
                @click="deleteMealAllowance(item)"
              ></v-btn>
            </template>
          </v-tooltip>
        </template>
      </v-data-table>
    </v-card>

    <!-- Create/Edit Dialog -->
    <MealAllowanceForm
      v-model="showFormDialog"
      :meal-allowance="selectedMealAllowance"
      :positions="positions"
      @saved="onSaved"
    />

    <!-- View Details Dialog -->
    <MealAllowanceDetails
      v-model="showDetailsDialog"
      :meal-allowance="selectedMealAllowance"
    />

    <!-- Approval Dialog -->
    <MealAllowanceApproval
      v-model="showApprovalDialog"
      :meal-allowance="selectedMealAllowance"
      @approved="onApproved"
    />
  </v-container>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import mealAllowanceService from '@/services/mealAllowanceService'
import MealAllowanceForm from '@/components/meal-allowance/MealAllowanceForm.vue'
import MealAllowanceDetails from '@/components/meal-allowance/MealAllowanceDetails.vue'
import MealAllowanceApproval from '@/components/meal-allowance/MealAllowanceApproval.vue'

const authStore = useAuthStore()
const loading = ref(false)
const mealAllowances = ref([])
const positions = ref([])
const selectedMealAllowance = ref(null)
const showFormDialog = ref(false)
const showDetailsDialog = ref(false)
const showApprovalDialog = ref(false)

const filters = ref({
  status: null,
  position_id: null,
  search: ''
})

const statusOptions = [
  { title: 'Draft', value: 'draft' },
  { title: 'Pending Approval', value: 'pending_approval' },
  { title: 'Approved', value: 'approved' },
  { title: 'Rejected', value: 'rejected' }
]

const headers = [
  { title: 'Reference No.', key: 'reference_number', sortable: true },
  { title: 'Title', key: 'title', sortable: true },
  { title: 'Period', key: 'period', sortable: true },
  { title: 'Position', key: 'position', sortable: true },
  { title: 'No. of Employees', key: 'employee_count', sortable: true, align: 'center' },
  { title: 'Total Amount', key: 'total_amount', sortable: true },
  { title: 'Status', key: 'status', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false, align: 'center' }
]

const canCreate = computed(() => ['admin', 'accountant', 'hr'].includes(authStore.user?.role))
const canEdit = computed(() => ['admin', 'accountant', 'hr'].includes(authStore.user?.role))
const canSubmit = computed(() => ['admin', 'accountant', 'hr'].includes(authStore.user?.role))
const canApprove = computed(() => authStore.user?.role === 'admin')
const canDelete = computed(() => ['admin', 'accountant', 'hr'].includes(authStore.user?.role))

onMounted(async () => {
  await fetchPositions()
  await fetchMealAllowances()
})

async function fetchMealAllowances() {
  loading.value = true
  try {
    const response = await mealAllowanceService.getAll(filters.value)
    mealAllowances.value = response.data || response
  } catch (error) {
    console.error('Error fetching meal allowances:', error)
    mealAllowances.value = []
  } finally {
    loading.value = false
  }
}

async function fetchPositions() {
  try {
    positions.value = await mealAllowanceService.getPositions()
  } catch (error) {
    console.error('Error fetching positions:', error)
  }
}

function openCreateDialog() {
  selectedMealAllowance.value = null
  showFormDialog.value = true
}

function editMealAllowance(item) {
  selectedMealAllowance.value = item
  showFormDialog.value = true
}

function viewDetails(item) {
  selectedMealAllowance.value = item
  showDetailsDialog.value = true
}

function openApprovalDialog(item) {
  selectedMealAllowance.value = item
  showApprovalDialog.value = true
}

async function submitForApproval(item) {
  if (!confirm('Submit this meal allowance for approval?')) return
  
  try {
    await mealAllowanceService.submit(item.id)
    alert('Meal allowance submitted for approval')
    await fetchMealAllowances()
  } catch (error) {
    console.error('Error submitting meal allowance:', error)
    alert('Failed to submit meal allowance')
  }
}

async function generatePdf(item) {
  if (!confirm(`Generate PDF for ${item.reference_number}?`)) return
  
  loading.value = true
  try {
    const response = await mealAllowanceService.generatePdf(item.id)
    alert('PDF generated successfully')
    await fetchMealAllowances()
  } catch (error) {
    console.error('Error generating PDF:', error)
    alert(error.response?.data?.message || 'Failed to generate PDF')
  } finally {
    loading.value = false
  }
}

async function downloadPdf(item) {
  try {
    loading.value = true
    const blob = await mealAllowanceService.downloadPdf(item.id)
    const url = window.URL.createObjectURL(blob)
    const link = document.createElement('a')
    link.href = url
    link.download = `${item.reference_number}.pdf`
    link.click()
    window.URL.revokeObjectURL(url)
  } catch (error) {
    console.error('Error downloading PDF:', error)
    alert('Failed to download PDF. Please generate it first.')
  } finally {
    loading.value = false
  }
}

async function deleteMealAllowance(item) {
  if (!confirm(`Delete meal allowance ${item.reference_number}?`)) return
  
  try {
    await mealAllowanceService.delete(item.id)
    alert('Meal allowance deleted successfully')
    await fetchMealAllowances()
  } catch (error) {
    console.error('Error deleting meal allowance:', error)
    alert('Failed to delete meal allowance')
  }
}

function onSaved() {
  showFormDialog.value = false
  fetchMealAllowances()
}

function onApproved() {
  showApprovalDialog.value = false
  fetchMealAllowances()
}

function formatDate(date) {
  return new Date(date).toLocaleDateString('en-US', { 
    year: 'numeric', 
    month: 'short', 
    day: 'numeric' 
  })
}

function formatNumber(value) {
  return new Intl.NumberFormat('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(value)
}

function calculateTotal(mealAllowance) {
  if (!mealAllowance.items || !Array.isArray(mealAllowance.items)) {
    return 0
  }
  return mealAllowance.items.reduce((total, item) => {
    return total + (parseFloat(item.no_of_days || 0) * parseFloat(item.amount_per_day || 0))
  }, 0)
}

function getStatusColor(status) {
  const colors = {
    draft: 'grey',
    pending_approval: 'orange',
    approved: 'success',
    rejected: 'error'
  }
  return colors[status] || 'grey'
}
</script>
