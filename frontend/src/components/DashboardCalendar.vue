<template>
  <v-card class="modern-card calendar-card" elevation="0">
    <v-card-title class="d-flex justify-space-between align-center pa-4">
      <div class="calendar-header">
        <v-icon size="20" class="mr-2">mdi-calendar-month</v-icon>
        {{ currentMonthYear }}
      </div>
      <div class="d-flex align-center gap-2">
        <v-btn 
          size="small" 
          color="primary" 
          variant="flat"
          prepend-icon="mdi-plus"
          @click="openEventDialog"
        >
          Add Event
        </v-btn>
        <v-btn icon size="small" variant="text" @click="previousMonth">
          <v-icon>mdi-chevron-left</v-icon>
        </v-btn>
        <v-btn icon size="small" variant="text" @click="nextMonth">
          <v-icon>mdi-chevron-right</v-icon>
        </v-btn>
      </div>
    </v-card-title>
    
    <v-divider></v-divider>
    
    <v-card-text class="pa-3">
      <div class="calendar-grid">
        <!-- Days of week header -->
        <div
          v-for="day in daysOfWeek"
          :key="day"
          class="calendar-day-header"
        >
          {{ day }}
        </div>
        
        <!-- Calendar dates -->
        <div
          v-for="(date, index) in calendarDates"
          :key="index"
          :class="[
            'calendar-date',
            {
              'calendar-date--today': date.isToday,
              'calendar-date--selected': date.isSelected,
              'calendar-date--other-month': date.isOtherMonth,
              'calendar-date--has-event': date.hasEvent,
              'calendar-date--holiday': date.isHoliday
            }
          ]"
          @click="selectDate(date)"
        >
          <span class="date-number">{{ date.day }}</span>
          <div v-if="date.hasEvent" class="event-indicator"></div>
          <div v-if="date.isHoliday" class="holiday-indicator"></div>
        </div>
      </div>
      
      <!-- Events for selected date -->
      <div v-if="selectedDateEvents.length > 0" class="mt-4">
        <v-divider class="mb-3"></v-divider>
        <div class="text-subtitle-2 mb-2">Events on {{ formatDate(selectedDate) }}</div>
        <div v-for="event in selectedDateEvents" :key="event.id" class="event-item mb-2">
          <div class="d-flex align-center justify-space-between">
            <div class="d-flex align-center">
              <v-icon size="16" :color="event.color" class="mr-2">{{ event.icon }}</v-icon>
              <span class="text-body-2">{{ event.title }}</span>
            </div>
            <v-btn 
              icon 
              size="x-small" 
              variant="text" 
              color="error"
              @click="deleteEvent(event.id)"
            >
              <v-icon size="16">mdi-delete</v-icon>
            </v-btn>
          </div>
        </div>
      </div>
    </v-card-text>
    
    <!-- Event Creation Dialog -->
    <v-dialog v-model="eventDialog" max-width="500">
      <v-card>
        <v-card-title class="pa-4">
          <div class="d-flex align-center justify-space-between">
            <span>Create Event</span>
            <v-btn icon size="small" variant="text" @click="closeEventDialog">
              <v-icon>mdi-close</v-icon>
            </v-btn>
          </div>
        </v-card-title>
        
        <v-divider></v-divider>
        
        <v-card-text class="pa-4">
          <v-form ref="eventFormRef" @submit.prevent="saveEvent">
            <v-text-field
              v-model="eventForm.title"
              label="Event Title"
              :rules="[rules.required]"
              variant="outlined"
              density="comfortable"
              class="mb-3"
            ></v-text-field>
            
            <v-text-field
              v-model="eventForm.date"
              label="Event Date"
              type="date"
              :rules="[rules.required]"
              variant="outlined"
              density="comfortable"
              class="mb-3"
            ></v-text-field>
            
            <v-select
              v-model="eventForm.icon"
              label="Icon"
              :items="iconOptions"
              item-title="label"
              item-value="value"
              variant="outlined"
              density="comfortable"
              class="mb-3"
            >
              <template v-slot:selection="{ item }">
                <div class="d-flex align-center">
                  <v-icon size="20" class="mr-2">{{ item.raw.value }}</v-icon>
                  <span>{{ item.raw.label }}</span>
                </div>
              </template>
              <template v-slot:item="{ item, props }">
                <v-list-item v-bind="props">
                  <template v-slot:prepend>
                    <v-icon>{{ item.raw.value }}</v-icon>
                  </template>
                </v-list-item>
              </template>
            </v-select>
            
            <v-select
              v-model="eventForm.color"
              label="Color"
              :items="colorOptions"
              item-title="label"
              item-value="value"
              variant="outlined"
              density="comfortable"
              class="mb-3"
            >
              <template v-slot:selection="{ item }">
                <div class="d-flex align-center">
                  <div 
                    :style="{ 
                      width: '20px', 
                      height: '20px', 
                      borderRadius: '4px',
                      backgroundColor: getColorValue(item.raw.value)
                    }"
                    class="mr-2"
                  ></div>
                  <span>{{ item.raw.label }}</span>
                </div>
              </template>
              <template v-slot:item="{ item, props }">
                <v-list-item v-bind="props">
                  <template v-slot:prepend>
                    <div 
                      :style="{ 
                        width: '20px', 
                        height: '20px', 
                        borderRadius: '4px',
                        backgroundColor: getColorValue(item.raw.value)
                      }"
                    ></div>
                  </template>
                </v-list-item>
              </template>
            </v-select>
          </v-form>
        </v-card-text>
        
        <v-divider></v-divider>
        
        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="closeEventDialog">Cancel</v-btn>
          <v-btn color="primary" variant="flat" @click="saveEvent">Save Event</v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-card>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'

const currentDate = ref(new Date())
const selectedDate = ref(new Date())
const eventDialog = ref(false)
const eventFormRef = ref(null)
const eventForm = ref({
  title: '',
  date: '',
  icon: 'mdi-calendar-star',
  color: 'primary'
})

// Sample events - can be replaced with props or API data
const events = ref([
  { id: 1, date: new Date(), title: 'Payroll Processing', icon: 'mdi-currency-php', color: 'success' },
  { id: 2, date: new Date(new Date().setDate(new Date().getDate() + 2)), title: 'Team Meeting', icon: 'mdi-account-group', color: 'primary' }
])

// Philippine Holidays for 2025 - can be replaced with API data
const holidays = ref([
  { date: new Date(2025, 0, 1), name: 'New Year\'s Day' },
  { date: new Date(2025, 1, 25), name: 'EDSA Revolution Anniversary' },
  { date: new Date(2025, 3, 9), name: 'Araw ng Kagitingan' },
  { date: new Date(2025, 3, 17), name: 'Maundy Thursday' },
  { date: new Date(2025, 3, 18), name: 'Good Friday' },
  { date: new Date(2025, 4, 1), name: 'Labor Day' },
  { date: new Date(2025, 5, 12), name: 'Independence Day' },
  { date: new Date(2025, 7, 25), name: 'Ninoy Aquino Day' },
  { date: new Date(2025, 7, 26), name: 'National Heroes Day' },
  { date: new Date(2025, 10, 1), name: 'All Saints\' Day' },
  { date: new Date(2025, 10, 30), name: 'Bonifacio Day' },
  { date: new Date(2025, 11, 8), name: 'Feast of the Immaculate Conception' },
  { date: new Date(2025, 11, 25), name: 'Christmas Day' },
  { date: new Date(2025, 11, 30), name: 'Rizal Day' },
  { date: new Date(2025, 11, 31), name: 'New Year\'s Eve' }
])

const daysOfWeek = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']

const iconOptions = [
  { label: 'Calendar Star', value: 'mdi-calendar-star' },
  { label: 'Currency PHP', value: 'mdi-currency-php' },
  { label: 'Account Group', value: 'mdi-account-group' },
  { label: 'Briefcase', value: 'mdi-briefcase' },
  { label: 'School', value: 'mdi-school' },
  { label: 'Heart', value: 'mdi-heart' },
  { label: 'Party Popper', value: 'mdi-party-popper' },
  { label: 'Cake', value: 'mdi-cake-variant' },
  { label: 'Gift', value: 'mdi-gift' },
  { label: 'Alert', value: 'mdi-alert-circle' }
]

const colorOptions = [
  { label: 'Primary', value: 'primary' },
  { label: 'Success', value: 'success' },
  { label: 'Warning', value: 'warning' },
  { label: 'Error', value: 'error' },
  { label: 'Info', value: 'info' },
  { label: 'Purple', value: 'purple' }
]

const rules = {
  required: (value) => !!value || 'This field is required'
}

const currentMonthYear = computed(() => {
  return currentDate.value.toLocaleDateString('en-US', { 
    month: 'long', 
    year: 'numeric' 
  })
})

const calendarDates = computed(() => {
  const year = currentDate.value.getFullYear()
  const month = currentDate.value.getMonth()
  
  const firstDay = new Date(year, month, 1)
  const lastDay = new Date(year, month + 1, 0)
  const prevLastDay = new Date(year, month, 0)
  
  const firstDayOfWeek = firstDay.getDay()
  const lastDate = lastDay.getDate()
  const prevLastDate = prevLastDay.getDate()
  
  const dates = []
  
  // Previous month dates
  for (let i = firstDayOfWeek - 1; i >= 0; i--) {
    dates.push({
      day: prevLastDate - i,
      isOtherMonth: true,
      isToday: false,
      isSelected: false,
      hasEvent: false,
      date: new Date(year, month - 1, prevLastDate - i)
    })
  }
  
  // Current month dates
  const today = new Date()
  for (let i = 1; i <= lastDate; i++) {
    const date = new Date(year, month, i)
    const isToday = isSameDay(date, today)
    const isSelected = isSameDay(date, selectedDate.value)
    const hasEvent = events.value.some(event => isSameDay(new Date(event.date), date))
    const isHoliday = holidays.value.some(holiday => isSameDay(new Date(holiday.date), date))
    
    dates.push({
      day: i,
      isOtherMonth: false,
      isToday,
      isSelected,
      hasEvent,
      isHoliday,
      date
    })
  }
  
  // Next month dates
  const remainingDays = 42 - dates.length // 6 rows × 7 days
  for (let i = 1; i <= remainingDays; i++) {
    dates.push({
      day: i,
      isOtherMonth: true,
      isToday: false,
      isSelected: false,
      hasEvent: false,
      date: new Date(year, month + 1, i)
    })
  }
  
  return dates
})

const selectedDateEvents = computed(() => {
  return events.value.filter(event => 
    isSameDay(new Date(event.date), selectedDate.value)
  )
})

function isSameDay(date1, date2) {
  return date1.getDate() === date2.getDate() &&
         date1.getMonth() === date2.getMonth() &&
         date1.getFullYear() === date2.getFullYear()
}

function selectDate(date) {
  if (!date.isOtherMonth) {
    selectedDate.value = date.date
  }
}

function previousMonth() {
  currentDate.value = new Date(
    currentDate.value.getFullYear(),
    currentDate.value.getMonth() - 1,
    1
  )
}

function nextMonth() {
  currentDate.value = new Date(
    currentDate.value.getFullYear(),
    currentDate.value.getMonth() + 1,
    1
  )
}

function openEventDialog() {
  // Set default date to selected date
  const year = selectedDate.value.getFullYear()
  const month = String(selectedDate.value.getMonth() + 1).padStart(2, '0')
  const day = String(selectedDate.value.getDate()).padStart(2, '0')
  eventForm.value.date = `${year}-${month}-${day}`
  eventDialog.value = true
}

function closeEventDialog() {
  eventDialog.value = false
  eventForm.value = {
    title: '',
    date: '',
    icon: 'mdi-calendar-star',
    color: 'primary'
  }
  if (eventFormRef.value) {
    eventFormRef.value.reset()
  }
}

async function saveEvent() {
  if (!eventFormRef.value) return
  
  const { valid } = await eventFormRef.value.validate()
  if (!valid) return
  
  const newEvent = {
    id: Date.now(), // Simple ID generation
    title: eventForm.value.title,
    date: new Date(eventForm.value.date),
    icon: eventForm.value.icon,
    color: eventForm.value.color
  }
  
  events.value.push(newEvent)
  
  // Save to localStorage
  localStorage.setItem('calendar_events', JSON.stringify(events.value))
  
  closeEventDialog()
}

function deleteEvent(eventId) {
  const index = events.value.findIndex(e => e.id === eventId)
  if (index !== -1) {
    events.value.splice(index, 1)
    // Update localStorage
    localStorage.setItem('calendar_events', JSON.stringify(events.value))
  }
}

function formatDate(date) {
  return date.toLocaleDateString('en-US', { 
    month: 'long', 
    day: 'numeric',
    year: 'numeric'
  })
}

function getColorValue(colorName) {
  const colorMap = {
    primary: '#6366f1',
    success: '#10b981',
    warning: '#f59e0b',
    error: '#ef4444',
    info: '#3b82f6',
    purple: '#8b5cf6'
  }
  return colorMap[colorName] || '#6366f1'
}

// Load events from localStorage on mount
onMounted(() => {
  const savedEvents = localStorage.getItem('calendar_events')
  if (savedEvents) {
    try {
      const parsed = JSON.parse(savedEvents)
      // Convert date strings back to Date objects
      events.value = parsed.map(event => ({
        ...event,
        date: new Date(event.date)
      }))
    } catch (e) {
      console.error('Error loading events:', e)
    }
  }
})
</script>

<style scoped lang="scss">
.calendar-header {
  font-weight: 600;
  font-size: 0.95rem;
  display: flex;
  align-items: center;
}

.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 4px;
}

.calendar-day-header {
  text-align: center;
  font-size: 0.75rem;
  font-weight: 600;
  color: #64748b;
  padding: 8px 4px;
  text-transform: uppercase;
}

.calendar-date {
  aspect-ratio: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  position: relative;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s ease;
  
  &:hover:not(.calendar-date--other-month) {
    background-color: rgba(99, 102, 241, 0.1);
  }
  
  .date-number {
    font-size: 0.875rem;
    font-weight: 500;
  }
  
  &.calendar-date--other-month {
    opacity: 0.3;
    cursor: default;
    
    &:hover {
      background-color: transparent;
    }
  }
  
  &.calendar-date--today {
    background-color: rgba(99, 102, 241, 0.15);
    font-weight: 700;
    
    .date-number {
      color: rgb(99, 102, 241);
    }
  }
  
  &.calendar-date--selected {
    background-color: rgb(99, 102, 241);
    
    .date-number {
      color: white;
    }
    
    .event-indicator {
      background-color: white;
    }
  }
  
  .event-indicator {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background-color: rgb(99, 102, 241);
    position: absolute;
    bottom: 4px;
    left: 50%;
    transform: translateX(-50%);
  }
  
  .holiday-indicator {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background-color: #ef4444;
    position: absolute;
    top: 4px;
    right: 4px;
  }
  
  &.calendar-date--holiday {
    .date-number {
      color: #ef4444;
    }
  }
}

.event-item {
  padding: 8px;
  background-color: rgba(99, 102, 241, 0.05);
  border-radius: 6px;
  border-left: 3px solid rgb(99, 102, 241);
}
</style>
