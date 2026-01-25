<template>
  <div class="holiday-management">
    <div class="page-header">
      <div>
        <h1>Holiday Management</h1>
        <p class="subtitle">Manage company holidays and holiday pay rates</p>
      </div>
      <button class="btn-primary" @click="showAddModal = true">
        <i class="fas fa-plus"></i> Add Holiday
      </button>
    </div>

    <!-- Filters -->
    <div class="filters-section">
      <div class="filter-group">
        <label>Year:</label>
        <select v-model="selectedYear" @change="loadHolidays">
          <option v-for="year in years" :key="year" :value="year">
            {{ year }}
          </option>
        </select>
      </div>

      <div class="filter-group">
        <label>Type:</label>
        <div class="btn-group">
          <button 
            @click="filterType = null" 
            :class="{ active: filterType === null }"
          >
            All
          </button>
          <button 
            @click="filterType = 'regular'" 
            :class="{ active: filterType === 'regular' }"
          >
            Regular
          </button>
          <button 
            @click="filterType = 'special'" 
            :class="{ active: filterType === 'special' }"
          >
            Special
          </button>
        </div>
      </div>

      <div class="stats">
        <div class="stat-card">
          <div class="stat-value">{{ totalHolidays }}</div>
          <div class="stat-label">Total Holidays</div>
        </div>
        <div class="stat-card regular">
          <div class="stat-value">{{ regularCount }}</div>
          <div class="stat-label">Regular</div>
        </div>
        <div class="stat-card special">
          <div class="stat-value">{{ specialCount }}</div>
          <div class="stat-label">Special</div>
        </div>
      </div>
    </div>

    <!-- Holidays List -->
    <div v-if="loading" class="loading">
      <i class="fas fa-spinner fa-spin"></i> Loading holidays...
    </div>

    <div v-else-if="filteredHolidays.length === 0" class="empty-state">
      <i class="fas fa-calendar-times"></i>
      <p>No holidays found for {{ selectedYear }}</p>
      <button class="btn-primary" @click="showAddModal = true">
        Add First Holiday
      </button>
    </div>

    <div v-else class="holidays-grid">
      <div 
        v-for="holiday in filteredHolidays" 
        :key="holiday.id"
        :class="['holiday-card', holiday.type]"
      >
        <div class="holiday-header">
          <div class="holiday-date">
            <div class="month">{{ formatMonth(holiday.date) }}</div>
            <div class="day">{{ formatDay(holiday.date) }}</div>
            <div class="weekday">{{ formatWeekday(holiday.date) }}</div>
          </div>
          <div class="holiday-info">
            <h3>{{ holiday.name }}</h3>
            <span class="badge" :class="holiday.type">
              {{ holiday.type === 'regular' ? 'Regular Holiday' : 'Special Holiday' }}
            </span>
          </div>
        </div>

        <div v-if="holiday.description" class="holiday-description">
          {{ holiday.description }}
        </div>

        <div class="holiday-pay-info">
          <div class="pay-rate">
            <i class="fas fa-money-bill-wave"></i>
            <div>
              <strong>Pay Rate:</strong>
              <span class="rate">{{ getPayRateText(holiday) }}</span>
            </div>
          </div>
          <div class="recurring-badge" v-if="holiday.is_recurring">
            <i class="fas fa-sync-alt"></i> Recurring
          </div>
        </div>

        <div class="holiday-actions">
          <button class="btn-edit" @click="editHoliday(holiday)">
            <i class="fas fa-edit"></i> Edit
          </button>
          <button class="btn-delete" @click="confirmDelete(holiday)">
            <i class="fas fa-trash"></i> Delete
          </button>
        </div>
      </div>
    </div>

    <!-- Add/Edit Holiday Modal -->
    <div v-if="showAddModal || showEditModal" class="modal-overlay" @click.self="closeModal">
      <div class="modal-content">
        <div class="modal-header">
          <h2>{{ showEditModal ? 'Edit' : 'Add' }} Holiday</h2>
          <button class="btn-close" @click="closeModal">
            <i class="fas fa-times"></i>
          </button>
        </div>

        <form @submit.prevent="submitForm" class="modal-body">
          <div class="form-row">
            <div class="form-group full">
              <label>Holiday Name <span class="required">*</span></label>
              <input 
                v-model="form.name" 
                type="text" 
                required 
                placeholder="e.g., New Year's Day"
                class="form-control"
              />
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label>Date <span class="required">*</span></label>
              <input 
                v-model="form.date" 
                type="date" 
                required 
                class="form-control"
              />
            </div>

            <div class="form-group">
              <label>Type <span class="required">*</span></label>
              <select v-model="form.type" required class="form-control">
                <option value="regular">Regular Holiday</option>
                <option value="special">Special Holiday</option>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group full">
              <label>Description</label>
              <textarea 
                v-model="form.description" 
                rows="3"
                placeholder="Optional description"
                class="form-control"
              ></textarea>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group checkbox">
              <label>
                <input v-model="form.is_recurring" type="checkbox" />
                <span>Recurring annually</span>
              </label>
            </div>

            <div class="form-group checkbox">
              <label>
                <input v-model="form.is_active" type="checkbox" />
                <span>Active</span>
              </label>
            </div>
          </div>

          <!-- Pay Rate Preview -->
          <div class="pay-preview">
            <h4><i class="fas fa-calculator"></i> Pay Rate Preview</h4>
            <div class="preview-grid">
              <div class="preview-item">
                <strong>Regular Day (Mon-Sat):</strong>
                <span class="multiplier">{{ form.type === 'regular' ? '2.0x' : '2.6x' }}</span>
              </div>
              <div class="preview-item">
                <strong>Sunday:</strong>
                <span class="multiplier">2.6x</span>
              </div>
            </div>
            <div class="preview-note">
              <i class="fas fa-info-circle"></i>
              <span v-if="form.type === 'regular'">
                Regular holidays: 2x pay (2.6x on Sunday)
              </span>
              <span v-else>
                Special holidays: 2.6x pay for 8 hours work
              </span>
            </div>
          </div>

          <div class="form-actions">
            <button type="button" class="btn-secondary" @click="closeModal">
              Cancel
            </button>
            <button type="submit" class="btn-primary" :disabled="saving">
              <i v-if="saving" class="fas fa-spinner fa-spin"></i>
              <i v-else class="fas fa-save"></i>
              {{ saving ? 'Saving...' : (showEditModal ? 'Update' : 'Create') }}
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div v-if="showDeleteModal" class="modal-overlay" @click.self="showDeleteModal = false">
      <div class="modal-content small">
        <div class="modal-header">
          <h2>Delete Holiday</h2>
          <button class="btn-close" @click="showDeleteModal = false">
            <i class="fas fa-times"></i>
          </button>
        </div>
        <div class="modal-body">
          <p>Are you sure you want to delete <strong>{{ holidayToDelete?.name }}</strong>?</p>
          <p class="warning">This action cannot be undone.</p>
        </div>
        <div class="form-actions">
          <button class="btn-secondary" @click="showDeleteModal = false">
            Cancel
          </button>
          <button class="btn-danger" @click="deleteHoliday" :disabled="deleting">
            <i v-if="deleting" class="fas fa-spinner fa-spin"></i>
            {{ deleting ? 'Deleting...' : 'Delete' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue';
import api from '@/services/api';
import { useToast } from 'vue-toastification';

const toast = useToast();

// State
const selectedYear = ref(new Date().getFullYear());
const holidays = ref([]);
const filterType = ref(null);
const showAddModal = ref(false);
const showEditModal = ref(false);
const showDeleteModal = ref(false);
const holidayToDelete = ref(null);
const loading = ref(false);
const saving = ref(false);
const deleting = ref(false);

const form = ref({
  name: '',
  date: '',
  type: 'regular',
  description: '',
  is_recurring: false,
  is_active: true
});

// Computed
const years = computed(() => {
  const currentYear = new Date().getFullYear();
  return [currentYear - 1, currentYear, currentYear + 1, currentYear + 2];
});

const filteredHolidays = computed(() => {
  if (filterType.value === null) return holidays.value;
  return holidays.value.filter(h => h.type === filterType.value);
});

const totalHolidays = computed(() => holidays.value.length);
const regularCount = computed(() => holidays.value.filter(h => h.type === 'regular').length);
const specialCount = computed(() => holidays.value.filter(h => h.type === 'special').length);

// Methods
const loadHolidays = async () => {
  loading.value = true;
  try {
    const response = await api.get(`/holidays/year/${selectedYear.value}`);
    holidays.value = response.data.data.holidays;
  } catch (error) {
    console.error('Failed to load holidays:', error);
    toast.error('Failed to load holidays');
  } finally {
    loading.value = false;
  }
};

const formatMonth = (date) => {
  return new Date(date).toLocaleDateString('en-US', { month: 'short' });
};

const formatDay = (date) => {
  return new Date(date).getDate();
};

const formatWeekday = (date) => {
  return new Date(date).toLocaleDateString('en-US', { weekday: 'short' });
};

const getPayRateText = (holiday) => {
  const date = new Date(holiday.date);
  const isSunday = date.getDay() === 0;
  
  if (holiday.type === 'regular') {
    return isSunday ? '2.6x (Sunday)' : '2.0x';
  } else {
    return '2.6x (8 hours)';
  }
};

const editHoliday = (holiday) => {
  form.value = {
    id: holiday.id,
    name: holiday.name,
    date: holiday.date,
    type: holiday.type,
    description: holiday.description || '',
    is_recurring: holiday.is_recurring,
    is_active: holiday.is_active
  };
  showEditModal.value = true;
};

const confirmDelete = (holiday) => {
  holidayToDelete.value = holiday;
  showDeleteModal.value = true;
};

const deleteHoliday = async () => {
  deleting.value = true;
  try {
    await api.delete(`/holidays/${holidayToDelete.value.id}`);
    toast.success('Holiday deleted successfully');
    showDeleteModal.value = false;
    holidayToDelete.value = null;
    await loadHolidays();
  } catch (error) {
    console.error('Failed to delete holiday:', error);
    toast.error('Failed to delete holiday');
  } finally {
    deleting.value = false;
  }
};

const submitForm = async () => {
  saving.value = true;
  try {
    if (showEditModal.value) {
      await api.put(`/holidays/${form.value.id}`, form.value);
      toast.success('Holiday updated successfully');
    } else {
      await api.post('/holidays', form.value);
      toast.success('Holiday created successfully');
    }
    
    closeModal();
    await loadHolidays();
  } catch (error) {
    console.error('Failed to save holiday:', error);
    toast.error(error.response?.data?.message || 'Failed to save holiday');
  } finally {
    saving.value = false;
  }
};

const closeModal = () => {
  showAddModal.value = false;
  showEditModal.value = false;
  form.value = {
    name: '',
    date: '',
    type: 'regular',
    description: '',
    is_recurring: false,
    is_active: true
  };
};

onMounted(() => {
  loadHolidays();
});
</script>

<style scoped>
.holiday-management {
  padding: 2rem;
  max-width: 1400px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
}

.page-header h1 {
  margin: 0;
  font-size: 2rem;
  color: #2c3e50;
}

.subtitle {
  color: #7f8c8d;
  margin-top: 0.5rem;
}

.filters-section {
  background: white;
  padding: 1.5rem;
  border-radius: 8px;
  box-shadow: 0 2px 4px rgba(0,0,0,0.1);
  margin-bottom: 2rem;
  display: flex;
  gap: 2rem;
  align-items: center;
  flex-wrap: wrap;
}

.filter-group {
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.filter-group label {
  font-weight: 600;
  color: #2c3e50;
}

.filter-group select {
  padding: 0.5rem 1rem;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 1rem;
}

.btn-group {
  display: flex;
  gap: 0.5rem;
}

.btn-group button {
  padding: 0.5rem 1rem;
  border: 1px solid #ddd;
  background: white;
  border-radius: 6px;
  cursor: pointer;
  transition: all 0.3s;
}

.btn-group button.active {
  background: #3498db;
  color: white;
  border-color: #3498db;
}

.stats {
  display: flex;
  gap: 1rem;
  margin-left: auto;
}

.stat-card {
  background: #f8f9fa;
  padding: 1rem;
  border-radius: 6px;
  text-align: center;
  min-width: 100px;
}

.stat-card.regular {
  background: #fee;
  border: 2px solid #e74c3c;
}

.stat-card.special {
  background: #e3f2fd;
  border: 2px solid #3498db;
}

.stat-value {
  font-size: 2rem;
  font-weight: bold;
  color: #2c3e50;
}

.stat-label {
  font-size: 0.875rem;
  color: #7f8c8d;
  margin-top: 0.25rem;
}

.holidays-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 1.5rem;
}

.holiday-card {
  background: white;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0,0,0,0.1);
  overflow: hidden;
  transition: transform 0.2s, box-shadow 0.2s;
  border-left: 6px solid;
}

.holiday-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.holiday-card.regular {
  border-left-color: #e74c3c;
}

.holiday-card.special {
  border-left-color: #3498db;
}

.holiday-header {
  display: flex;
  gap: 1rem;
  padding: 1.5rem;
  background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.holiday-date {
  text-align: center;
  min-width: 70px;
}

.holiday-date .month {
  font-size: 0.875rem;
  color: #7f8c8d;
  text-transform: uppercase;
  font-weight: 600;
}

.holiday-date .day {
  font-size: 2.5rem;
  font-weight: bold;
  color: #2c3e50;
  line-height: 1;
}

.holiday-date .weekday {
  font-size: 0.875rem;
  color: #95a5a6;
  margin-top: 0.25rem;
}

.holiday-info {
  flex: 1;
}

.holiday-info h3 {
  margin: 0 0 0.5rem 0;
  font-size: 1.25rem;
  color: #2c3e50;
}

.badge {
  display: inline-block;
  padding: 0.25rem 0.75rem;
  border-radius: 12px;
  font-size: 0.75rem;
  text-transform: uppercase;
  font-weight: 600;
}

.badge.regular {
  background: #fee;
  color: #e74c3c;
}

.badge.special {
  background: #e3f2fd;
  color: #3498db;
}

.holiday-description {
  padding: 0 1.5rem;
  color: #7f8c8d;
  font-size: 0.875rem;
  margin-bottom: 1rem;
}

.holiday-pay-info {
  padding: 1rem 1.5rem;
  background: #f8f9fa;
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.pay-rate {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  color: #27ae60;
}

.pay-rate i {
  font-size: 1.5rem;
}

.pay-rate .rate {
  margin-left: 0.5rem;
  font-weight: bold;
  font-size: 1.125rem;
}

.recurring-badge {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #9b59b6;
  font-size: 0.875rem;
  font-weight: 600;
}

.holiday-actions {
  padding: 1rem 1.5rem;
  display: flex;
  gap: 0.75rem;
  border-top: 1px solid #e9ecef;
}

.btn-edit, .btn-delete {
  flex: 1;
  padding: 0.625rem 1rem;
  border: none;
  border-radius: 6px;
  cursor: pointer;
  font-weight: 500;
  transition: all 0.3s;
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 0.5rem;
}

.btn-edit {
  background: #3498db;
  color: white;
}

.btn-edit:hover {
  background: #2980b9;
}

.btn-delete {
  background: #e74c3c;
  color: white;
}

.btn-delete:hover {
  background: #c0392b;
}

.loading, .empty-state {
  text-align: center;
  padding: 4rem 2rem;
  color: #7f8c8d;
  background: white;
  border-radius: 8px;
}

.empty-state i {
  font-size: 4rem;
  color: #bdc3c7;
  margin-bottom: 1rem;
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
  padding: 2rem;
}

.modal-content {
  background: white;
  border-radius: 12px;
  width: 100%;
  max-width: 600px;
  max-height: 90vh;
  overflow-y: auto;
  box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}

.modal-content.small {
  max-width: 400px;
}

.modal-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 1.5rem;
  border-bottom: 1px solid #e9ecef;
}

.modal-header h2 {
  margin: 0;
  font-size: 1.5rem;
  color: #2c3e50;
}

.btn-close {
  background: none;
  border: none;
  font-size: 1.5rem;
  color: #7f8c8d;
  cursor: pointer;
  padding: 0;
  width: 32px;
  height: 32px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 4px;
  transition: all 0.2s;
}

.btn-close:hover {
  background: #f8f9fa;
  color: #2c3e50;
}

.modal-body {
  padding: 1.5rem;
}

.form-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-bottom: 1rem;
}

.form-group.full {
  grid-column: 1 / -1;
}

.form-group label {
  display: block;
  margin-bottom: 0.5rem;
  font-weight: 600;
  color: #2c3e50;
}

.required {
  color: #e74c3c;
}

.form-control {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #ddd;
  border-radius: 6px;
  font-size: 1rem;
  transition: border-color 0.3s;
}

.form-control:focus {
  outline: none;
  border-color: #3498db;
  box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
}

.form-group.checkbox {
  grid-column: 1 / -1;
}

.form-group.checkbox label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  font-weight: normal;
  cursor: pointer;
}

.form-group.checkbox input[type="checkbox"] {
  width: 18px;
  height: 18px;
  cursor: pointer;
}

.pay-preview {
  grid-column: 1 / -1;
  background: #f8f9fa;
  padding: 1.5rem;
  border-radius: 8px;
  border: 2px solid #e9ecef;
  margin-top: 1rem;
}

.pay-preview h4 {
  margin: 0 0 1rem 0;
  color: #2c3e50;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.preview-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 1rem;
  margin-bottom: 1rem;
}

.preview-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.multiplier {
  background: #27ae60;
  color: white;
  padding: 0.25rem 0.75rem;
  border-radius: 6px;
  font-weight: bold;
}

.preview-note {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #7f8c8d;
  font-size: 0.875rem;
  margin-top: 0.75rem;
  padding-top: 0.75rem;
  border-top: 1px solid #e9ecef;
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  margin-top: 1.5rem;
  padding-top: 1.5rem;
  border-top: 1px solid #e9ecef;
}

.btn-primary, .btn-secondary, .btn-danger {
  padding: 0.75rem 1.5rem;
  border: none;
  border-radius: 6px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.btn-primary {
  background: #3498db;
  color: white;
}

.btn-primary:hover:not(:disabled) {
  background: #2980b9;
}

.btn-primary:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.btn-secondary {
  background: #95a5a6;
  color: white;
}

.btn-secondary:hover {
  background: #7f8c8d;
}

.btn-danger {
  background: #e74c3c;
  color: white;
}

.btn-danger:hover:not(:disabled) {
  background: #c0392b;
}

.warning {
  color: #e74c3c;
  font-size: 0.875rem;
  margin-top: 0.5rem;
}
</style>
