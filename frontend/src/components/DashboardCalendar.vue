<template>
  <div class="calendar-widget">
    <div class="widget-header">
      <div class="widget-title-wrapper">
        <div class="widget-icon-badge">
          <v-icon size="18">mdi-calendar-month</v-icon>
        </div>
        <h2 class="widget-title">{{ currentMonthYear }}</h2>
      </div>
      <div class="calendar-nav">
        <button class="add-event-btn" @click="openEventDialog">
          <v-icon size="16">mdi-plus</v-icon>
          <span>Add Event</span>
        </button>
        <button class="nav-btn" @click="previousMonth">
          <v-icon size="16">mdi-chevron-left</v-icon>
        </button>
        <button class="nav-btn" @click="nextMonth">
          <v-icon size="16">mdi-chevron-right</v-icon>
        </button>
      </div>
    </div>

    <div class="widget-content">
      <div class="calendar-grid">
        <!-- Days of week header -->
        <div v-for="day in daysOfWeek" :key="day" class="calendar-day-header">
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
              'calendar-date--holiday': date.isHoliday,
            },
          ]"
          @click="selectDate(date)"
        >
          <span class="date-number">{{ date.day }}</span>
          <div v-if="date.hasEvent" class="event-indicator"></div>
          <div v-if="date.isHoliday" class="holiday-indicator"></div>
        </div>
      </div>

      <!-- Events for selected date -->
      <div v-if="selectedDateEvents.length > 0" class="selected-events">
        <div class="selected-events-divider"></div>
        <div class="selected-events-title">
          Events on {{ formatDate(selectedDate) }}
        </div>
        <div
          v-for="event in selectedDateEvents"
          :key="event.id"
          class="selected-event-item"
        >
          <div class="selected-event-left">
            <div
              class="selected-event-icon"
              :style="{ background: getColorValue(event.color) }"
            >
              <v-icon size="14">{{ event.icon }}</v-icon>
            </div>
            <span class="selected-event-title">{{ event.title }}</span>
          </div>
          <button
            v-if="!event.isHoliday"
            class="delete-event-btn"
            @click="deleteEvent(event.id)"
          >
            <v-icon size="14">mdi-delete</v-icon>
          </button>
        </div>
      </div>
    </div>

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
                      backgroundColor: getColorValue(item.raw.value),
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
                        backgroundColor: getColorValue(item.raw.value),
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
          <v-btn color="primary" variant="flat" @click="saveEvent"
            >Save Event</v-btn
          >
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from "vue";
import api from "@/services/api";

const currentDate = ref(new Date());
const selectedDate = ref(new Date());
const eventDialog = ref(false);
const eventFormRef = ref(null);
const eventForm = ref({
  title: "",
  date: "",
  icon: "mdi-calendar-star",
  color: "primary",
});

// Sample events - can be replaced with props or API data
const events = ref([
  {
    id: 1,
    date: new Date(),
    title: "Payroll Processing",
    icon: "mdi-currency-php",
    color: "success",
  },
  {
    id: 2,
    date: new Date(new Date().setDate(new Date().getDate() + 2)),
    title: "Team Meeting",
    icon: "mdi-account-group",
    color: "primary",
  },
]);

// Holidays from API
const holidays = ref([]);

const daysOfWeek = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];

const iconOptions = [
  { label: "Calendar Star", value: "mdi-calendar-star" },
  { label: "Currency PHP", value: "mdi-currency-php" },
  { label: "Account Group", value: "mdi-account-group" },
  { label: "Briefcase", value: "mdi-briefcase" },
  { label: "School", value: "mdi-school" },
  { label: "Heart", value: "mdi-heart" },
  { label: "Party Popper", value: "mdi-party-popper" },
  { label: "Cake", value: "mdi-cake-variant" },
  { label: "Gift", value: "mdi-gift" },
  { label: "Alert", value: "mdi-alert-circle" },
];

const colorOptions = [
  { label: "Primary", value: "primary" },
  { label: "Success", value: "success" },
  { label: "Warning", value: "warning" },
  { label: "Error", value: "error" },
  { label: "Info", value: "info" },
  { label: "Purple", value: "purple" },
];

const rules = {
  required: (value) => !!value || "This field is required",
};

const currentMonthYear = computed(() => {
  return currentDate.value.toLocaleDateString("en-US", {
    month: "long",
    year: "numeric",
  });
});

const calendarDates = computed(() => {
  const year = currentDate.value.getFullYear();
  const month = currentDate.value.getMonth();

  const firstDay = new Date(year, month, 1);
  const lastDay = new Date(year, month + 1, 0);
  const prevLastDay = new Date(year, month, 0);

  const firstDayOfWeek = firstDay.getDay();
  const lastDate = lastDay.getDate();
  const prevLastDate = prevLastDay.getDate();

  const dates = [];

  // Previous month dates
  for (let i = firstDayOfWeek - 1; i >= 0; i--) {
    const date = new Date(year, month - 1, prevLastDate - i);
    const hasEvent = events.value.some((event) =>
      isSameDay(new Date(event.date), date),
    );
    const isHoliday = holidays.value.some((holiday) =>
      isSameDay(new Date(holiday.date), date),
    );
    dates.push({
      day: prevLastDate - i,
      isOtherMonth: true,
      isToday: false,
      isSelected: false,
      hasEvent,
      isHoliday,
      date,
    });
  }

  // Current month dates
  const today = new Date();
  for (let i = 1; i <= lastDate; i++) {
    const date = new Date(year, month, i);
    const isToday = isSameDay(date, today);
    const isSelected = isSameDay(date, selectedDate.value);
    const hasEvent = events.value.some((event) =>
      isSameDay(new Date(event.date), date),
    );
    const isHoliday = holidays.value.some((holiday) =>
      isSameDay(new Date(holiday.date), date),
    );

    dates.push({
      day: i,
      isOtherMonth: false,
      isToday,
      isSelected,
      hasEvent,
      isHoliday,
      date,
    });
  }

  // Next month dates
  const remainingDays = 42 - dates.length; // 6 rows × 7 days
  for (let i = 1; i <= remainingDays; i++) {
    const date = new Date(year, month + 1, i);
    const hasEvent = events.value.some((event) =>
      isSameDay(new Date(event.date), date),
    );
    const isHoliday = holidays.value.some((holiday) =>
      isSameDay(new Date(holiday.date), date),
    );
    dates.push({
      day: i,
      isOtherMonth: true,
      isToday: false,
      isSelected: false,
      hasEvent,
      isHoliday,
      date,
    });
  }

  return dates;
});

const selectedDateEvents = computed(() => {
  const dayEvents = events.value.filter((event) =>
    isSameDay(new Date(event.date), selectedDate.value),
  );

  const dayHolidays = holidays.value
    .filter((holiday) => isSameDay(new Date(holiday.date), selectedDate.value))
    .map((holiday) => ({
      id: `holiday-${holiday.id}`,
      title: holiday.name,
      icon: "mdi-palm-tree",
      color: holiday.type === "regular" ? "error" : "warning",
      isHoliday: true,
    }));

  return [...dayEvents, ...dayHolidays];
});

function isSameDay(date1, date2) {
  return (
    date1.getDate() === date2.getDate() &&
    date1.getMonth() === date2.getMonth() &&
    date1.getFullYear() === date2.getFullYear()
  );
}

function selectDate(date) {
  selectedDate.value = date.date;

  // If clicking a date from another month, navigate to that month
  if (date.isOtherMonth) {
    currentDate.value = new Date(
      date.date.getFullYear(),
      date.date.getMonth(),
      1,
    );
  }
}

function previousMonth() {
  currentDate.value = new Date(
    currentDate.value.getFullYear(),
    currentDate.value.getMonth() - 1,
    1,
  );
}

function nextMonth() {
  currentDate.value = new Date(
    currentDate.value.getFullYear(),
    currentDate.value.getMonth() + 1,
    1,
  );
}

function openEventDialog() {
  // Set default date to selected date
  const year = selectedDate.value.getFullYear();
  const month = String(selectedDate.value.getMonth() + 1).padStart(2, "0");
  const day = String(selectedDate.value.getDate()).padStart(2, "0");
  eventForm.value.date = `${year}-${month}-${day}`;
  eventDialog.value = true;
}

function closeEventDialog() {
  eventDialog.value = false;
  eventForm.value = {
    title: "",
    date: "",
    icon: "mdi-calendar-star",
    color: "primary",
  };
  if (eventFormRef.value) {
    eventFormRef.value.reset();
  }
}

async function saveEvent() {
  if (!eventFormRef.value) return;

  const { valid } = await eventFormRef.value.validate();
  if (!valid) return;

  const newEvent = {
    id: Date.now(), // Simple ID generation
    title: eventForm.value.title,
    date: new Date(eventForm.value.date),
    icon: eventForm.value.icon,
    color: eventForm.value.color,
  };

  events.value.push(newEvent);

  // Save to localStorage
  localStorage.setItem("calendar_events", JSON.stringify(events.value));

  closeEventDialog();
}

function deleteEvent(eventId) {
  const index = events.value.findIndex((e) => e.id === eventId);
  if (index !== -1) {
    events.value.splice(index, 1);
    // Update localStorage
    localStorage.setItem("calendar_events", JSON.stringify(events.value));
  }
}

function formatDate(date) {
  return date.toLocaleDateString("en-US", {
    month: "long",
    day: "numeric",
    year: "numeric",
  });
}

function getColorValue(colorName) {
  const colorMap = {
    primary: "#6366f1",
    success: "#10b981",
    warning: "#f59e0b",
    error: "#ef4444",
    info: "#3b82f6",
    purple: "#8b5cf6",
  };
  return colorMap[colorName] || "#6366f1";
}

// Fetch holidays from API
async function fetchHolidays() {
  try {
    const year = currentDate.value.getFullYear();
    const response = await api.get(`/holidays/year/${year}`);
    holidays.value = response.data.data.holidays.map((holiday) => ({
      date: new Date(holiday.date),
      name: holiday.name,
      type: holiday.type,
      id: holiday.id,
    }));
  } catch (error) {
    console.error("Error fetching holidays:", error);
  }
}

// Fetch upcoming events from dashboard API
async function fetchUpcomingEvents() {
  try {
    const response = await api.get("/dashboard/upcoming-events");
    const upcomingEvents = response.data.map((event) => ({
      id: `event-${event.type}-${new Date(event.date).getTime()}`,
      date: new Date(event.date),
      title: event.title,
      icon: event.icon,
      color: event.color,
      type: event.type,
    }));

    // Merge with existing events (custom events)
    const customEvents = events.value.filter(
      (e) => !e.id.toString().startsWith("event-"),
    );
    events.value = [...customEvents, ...upcomingEvents];
  } catch (error) {
    console.error("Error fetching upcoming events:", error);
  }
}

// Watch for month/year changes to refetch holidays
watch(currentDate, () => {
  fetchHolidays();
});

// Load events from localStorage on mount
onMounted(() => {
  const savedEvents = localStorage.getItem("calendar_events");
  if (savedEvents) {
    try {
      const parsed = JSON.parse(savedEvents);
      // Convert date strings back to Date objects
      events.value = parsed.map((event) => ({
        ...event,
        date: new Date(event.date),
      }));
    } catch (e) {
      console.error("Error loading events:", e);
    }
  }

  // Fetch holidays and upcoming events from API
  fetchHolidays();
  fetchUpcomingEvents();
});
</script>

<style scoped lang="scss">
.calendar-widget {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  overflow: hidden;
}

.widget-header {
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

.widget-title-wrapper {
  display: flex;
  align-items: center;
  gap: 12px;
}

.widget-icon-badge {
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
}

.widget-title {
  font-size: 13px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
  letter-spacing: -0.3px;
}

.calendar-nav {
  display: flex;
  align-items: center;
  gap: 8px;
}

.add-event-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 14px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  border: none;
  border-radius: 8px;
  color: #ffffff;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;

  .v-icon {
    color: #ffffff !important;
  }

  &:hover {
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(237, 152, 95, 0.4);
  }
}

.nav-btn {
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: rgba(237, 152, 95, 0.1);
  border: 1px solid rgba(237, 152, 95, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s ease;

  .v-icon {
    color: #ed985f !important;
  }

  &:hover {
    background: rgba(237, 152, 95, 0.15);
    border-color: rgba(237, 152, 95, 0.3);
  }
}

.widget-content {
  padding: 16px 24px;
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
    background-color: rgba(237, 152, 95, 0.1);
  }

  .date-number {
    font-size: 0.875rem;
    font-weight: 500;
    color: #001f3d;
  }

  &.calendar-date--other-month {
    opacity: 0.3;
    cursor: default;

    &:hover {
      background-color: transparent;
    }
  }

  &.calendar-date--today {
    background-color: rgba(237, 152, 95, 0.15);
    font-weight: 700;

    .date-number {
      color: #ed985f;
    }
  }

  &.calendar-date--selected {
    background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);

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
    background-color: #ed985f;
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

.selected-events {
  margin-top: 16px;
}

.selected-events-divider {
  height: 1px;
  background: rgba(0, 31, 61, 0.08);
  margin-bottom: 16px;
}

.selected-events-title {
  font-size: 13px;
  font-weight: 700;
  color: #001f3d;
  margin-bottom: 12px;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.selected-event-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 10px 12px;
  background: rgba(237, 152, 95, 0.05);
  border-radius: 8px;
  border-left: 3px solid #ed985f;
  margin-bottom: 8px;

  &:last-child {
    margin-bottom: 0;
  }
}

.selected-event-left {
  display: flex;
  align-items: center;
  gap: 10px;
  flex: 1;
}

.selected-event-icon {
  width: 28px;
  height: 28px;
  border-radius: 6px;
  display: flex;
  align-items: center;
  justify-content: center;

  .v-icon {
    color: #ffffff !important;
  }
}

.selected-event-title {
  font-size: 13px;
  font-weight: 600;
  color: #001f3d;
}

.delete-event-btn {
  width: 28px;
  height: 28px;
  border-radius: 6px;
  background: rgba(244, 67, 54, 0.1);
  border: 1px solid rgba(244, 67, 54, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.3s ease;

  .v-icon {
    color: #f44336 !important;
  }

  &:hover {
    background: rgba(244, 67, 54, 0.15);
    border-color: rgba(244, 67, 54, 0.3);
  }
}
</style>
