<template>
  <v-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    max-width="1200px"
    persistent
  >
    <v-card>
      <v-card-title class="d-flex align-center bg-primary">
        <v-icon left>mdi-food</v-icon>
        {{ isEdit ? 'Edit Meal Allowance' : 'Create Meal Allowance' }}
        <v-spacer></v-spacer>
        <v-btn icon="mdi-close" variant="text" @click="close"></v-btn>
      </v-card-title>

      <v-card-text class="pt-4">
        <v-form ref="formRef" @submit.prevent="save">
          <v-row>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.title"
                label="Title *"
                :rules="[rules.required]"
                outlined
                dense
              ></v-text-field>
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.location"
                label="Location"
                outlined
                dense
              ></v-text-field>
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.period_start"
                label="Period Start *"
                type="date"
                :rules="[rules.required]"
                outlined
                dense
              ></v-text-field>
            </v-col>
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.period_end"
                label="Period End *"
                type="date"
                :rules="[rules.required]"
                outlined
                dense
              ></v-text-field>
            </v-col>
            <v-col cols="12" md="6">
              <v-select
                v-model="form.position_id"
                :items="positions"
                item-title="position_name"
                item-value="id"
                label="Position/Role *"
                :rules="[rules.required]"
                outlined
                dense
                @update:model-value="loadEmployees"
              ></v-select>
            </v-col>
            <v-col cols="12">
              <v-textarea
                v-model="form.notes"
                label="Notes"
                outlined
                dense
                rows="2"
              ></v-textarea>
            </v-col>
          </v-row>

          <v-divider class="my-4"></v-divider>

          <v-row>
            <v-col cols="12" class="d-flex justify-space-between align-center">
              <h3>Employees</h3>
              <div>
                <v-chip 
                  v-if="form.items.length > 0" 
                  color="info" 
                  class="mr-2"
                >
                  {{ form.items.length }} employee(s)
                </v-chip>
                <v-btn
                  v-if="availableEmployees.length > 0 && form.items.length === 0"
                  color="primary"
                  size="small"
                  @click="addAllEmployees"
                >
                  <v-icon left>mdi-account-multiple-plus</v-icon>
                  Add All Employees from Position
                </v-btn>
              </div>
            </v-col>
          </v-row>

          <v-data-table
            :headers="itemHeaders"
            :items="form.items"
            :loading="loadingEmployees"
            density="compact"
          >
            <template #[`item.employee_id`]="{ item, index }">
              <div class="d-flex align-center">
                <span v-if="item.employee_name" class="text-body-2">
                  {{ item.employee_name }}
                  <span v-if="item.employee_number" class="text-caption text-grey ml-1">
                    ({{ item.employee_number }})
                  </span>
                </span>
                <v-select
                  v-else
                  v-model="item.employee_id"
                  :items="availableEmployees"
                  item-title="name"
                  item-value="id"
                  density="compact"
                  variant="outlined"
                  hide-details
                  placeholder="Select employee"
                  @update:model-value="onEmployeeSelect(index, $event)"
                ></v-select>
              </div>
            </template>

            <template #[`item.no_of_days`]="{ item }">
              <v-text-field
                v-model.number="item.no_of_days"
                type="number"
                min="1"
                max="31"
                density="compact"
                variant="outlined"
                hide-details
                @input="calculateTotal(item)"
              ></v-text-field>
            </template>

            <template #[`item.amount_per_day`]="{ item }">
              <v-text-field
                v-model.number="item.amount_per_day"
                type="number"
                min="0"
                step="0.01"
                density="compact"
                variant="outlined"
                hide-details
                prefix="₱"
                @input="calculateTotal(item)"
              ></v-text-field>
            </template>

            <template #[`item.total`]="{ item }">
              ₱{{ formatNumber(item.no_of_days * item.amount_per_day) }}
            </template>

            <template #[`item.actions`]="{ index }">
              <v-btn
                icon="mdi-delete"
                size="small"
                variant="text"
                color="error"
                @click="removeItem(index)"
              ></v-btn>
            </template>

            <template #bottom>
              <v-row class="ma-2">
                <v-col>
                  <v-btn
                    color="primary"
                    size="small"
                    @click="addItem"
                  >
                    <v-icon left>mdi-plus</v-icon>
                    Add Row
                  </v-btn>
                </v-col>
                <v-col class="text-right">
                  <strong>Grand Total: ₱{{ formatNumber(grandTotal) }}</strong>
                </v-col>
              </v-row>
            </template>
          </v-data-table>
        </v-form>
      </v-card-text>

      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn @click="close" variant="outlined">Cancel</v-btn>
        <v-btn 
          color="success" 
          @click="saveAndSubmit" 
          :loading="saving"
          v-if="!isEdit"
        >
          <v-icon left>mdi-send</v-icon>
          Create & Submit for Approval
        </v-btn>
        <v-btn 
          color="primary" 
          @click="save" 
          :loading="saving"
        >
          {{ isEdit ? 'Update' : 'Save as Draft' }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import mealAllowanceService from '@/services/mealAllowanceService'

const props = defineProps({
  modelValue: Boolean,
  mealAllowance: Object,
  positions: Array
})

const emit = defineEmits(['update:modelValue', 'saved'])

const formRef = ref(null)
const saving = ref(false)
const loadingEmployees = ref(false)
const availableEmployees = ref([])

const form = ref({
  title: '',
  location: '',
  period_start: '',
  period_end: '',
  position_id: null,
  notes: '',
  items: []
})

const itemHeaders = [
  { title: 'Employee', key: 'employee_id', width: '250px' },
  { title: 'No. of Days', key: 'no_of_days', width: '120px' },
  { title: 'Amount/Day', key: 'amount_per_day', width: '120px' },
  { title: 'Total', key: 'total', width: '120px' },
  { title: 'Actions', key: 'actions', width: '80px', align: 'center' }
]

const rules = {
  required: v => !!v || 'Required'
}

const isEdit = computed(() => !!props.mealAllowance)

const grandTotal = computed(() => {
  return form.value.items.reduce((sum, item) => {
    return sum + (item.no_of_days * item.amount_per_day)
  }, 0)
})

watch(() => props.mealAllowance, (newVal) => {
  if (newVal) {
    form.value = {
      title: newVal.title,
      location: newVal.location || '',
      period_start: newVal.period_start,
      period_end: newVal.period_end,
      position_id: newVal.position_id,
      notes: newVal.notes || '',
      items: newVal.items ? newVal.items.map(item => ({
        employee_id: item.employee_id,
        no_of_days: item.no_of_days,
        amount_per_day: parseFloat(item.amount_per_day)
      })) : []
    }
    if (newVal.position_id) {
      loadEmployees()
    }
  } else {
    resetForm()
  }
}, { immediate: true })

async function loadEmployees() {
  if (!form.value.position_id) return
  
  loadingEmployees.value = true
  try {
    availableEmployees.value = await mealAllowanceService.getEmployeesByPosition(
      form.value.position_id
    )
  } catch (error) {
    console.error('Error loading employees:', error)
  } finally {
    loadingEmployees.value = false
  }
}

function addItem() {
  form.value.items.push({
    employee_id: null,
    no_of_days: 15,
    amount_per_day: 120.00
  })
}

function addAllEmployees() {
  // Clear existing items and add all employees from selected position
  form.value.items = []
  
  availableEmployees.value.forEach(emp => {
    form.value.items.push({
      employee_id: emp.id,
      employee_name: emp.name,
      employee_number: emp.employee_number,
      position_code: emp.position_code,
      no_of_days: 15,
      amount_per_day: 120.00,
      total_amount: 15 * 120.00
    })
  })
}

function removeItem(index) {
  form.value.items.splice(index, 1)
}

function calculateTotal(item) {
  item.total_amount = (item.no_of_days || 0) * (item.amount_per_day || 0)
}

function onEmployeeSelect(index, employeeId) {
  const employee = availableEmployees.value.find(e => e.id === employeeId)
  if (employee) {
    // Store employee details
    form.value.items[index].employee_name = employee.name
    form.value.items[index].employee_number = employee.employee_number
    form.value.items[index].position_code = employee.position_code
    // Set default amount based on employee's daily rate
    form.value.items[index].amount_per_day = employee.basic_salary || 120.00
    calculateTotal(form.value.items[index])
  }
}

async function save() {
  const { valid } = await formRef.value.validate()
  if (!valid) return
  
  if (form.value.items.length === 0) {
    alert('Please add at least one employee')
    return
  }
  
  saving.value = true
  try {
    if (isEdit.value) {
      await mealAllowanceService.update(props.mealAllowance.id, form.value)
      alert('Meal allowance updated successfully')
    } else {
      await mealAllowanceService.create(form.value)
      alert('Meal allowance saved as draft')
    }
    emit('saved')
    close()
  } catch (error) {
    console.error('Error saving meal allowance:', error)
    alert('Failed to save meal allowance: ' + (error.response?.data?.message || error.message))
  } finally {
    saving.value = false
  }
}

async function saveAndSubmit() {
  const { valid } = await formRef.value.validate()
  if (!valid) return
  
  if (form.value.items.length === 0) {
    alert('Please add at least one employee')
    return
  }
  
  if (!confirm('Create and submit this meal allowance for admin approval?')) return
  
  saving.value = true
  try {
    // First create the meal allowance
    const response = await mealAllowanceService.create(form.value)
    const mealAllowanceId = response.data.id
    
    // Then submit it for approval
    await mealAllowanceService.submit(mealAllowanceId)
    
    alert('Meal allowance created and submitted for approval successfully!')
    emit('saved')
    close()
  } catch (error) {
    console.error('Error creating and submitting meal allowance:', error)
    alert('Failed to create and submit: ' + (error.response?.data?.message || error.message))
  } finally {
    saving.value = false
  }
}

function close() {
  emit('update:modelValue', false)
  resetForm()
}

function resetForm() {
  form.value = {
    title: '',
    location: '',
    period_start: '',
    period_end: '',
    position_id: null,
    notes: '',
    items: []
  }
  availableEmployees.value = []
}

function formatNumber(value) {
  return new Intl.NumberFormat('en-PH', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2
  }).format(value)
}
</script>
