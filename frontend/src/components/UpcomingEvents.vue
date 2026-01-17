<template>
  <v-card class="modern-card" elevation="0">
    <v-card-title class="pa-5">
      <v-icon color="warning" size="small" class="mr-2">mdi-calendar-star</v-icon>
      <div class="text-subtitle-1 font-weight-bold">Upcoming Events</div>
      <v-spacer></v-spacer>
      <v-btn
        icon="mdi-refresh"
        size="small"
        variant="text"
        @click="fetchEvents"
        :loading="loading"
      ></v-btn>
    </v-card-title>
    <v-divider></v-divider>
    <v-card-text class="pa-0" style="max-height: 500px; overflow-y: auto;">
      <v-list class="py-0">
        <template v-if="events.length > 0">
          <template v-for="(event, index) in events" :key="index">
            <v-list-item class="px-5 py-3">
              <template v-slot:prepend>
                <v-avatar :color="event.color" size="40">
                  <v-icon size="20" color="white">{{ event.icon }}</v-icon>
                </v-avatar>
              </template>
              <v-list-item-title class="text-body-2 font-weight-medium mb-1">
                {{ event.title }}
              </v-list-item-title>
              <v-list-item-subtitle class="text-caption">
                <v-icon size="14" class="mr-1">mdi-calendar</v-icon>
                {{ event.description }}
              </v-list-item-subtitle>
              <template v-slot:append>
                <v-chip
                  :color="getDaysUntilColor(event.date)"
                  size="small"
                  variant="tonal"
                >
                  {{ getDaysUntilText(event.date) }}
                </v-chip>
              </template>
            </v-list-item>
            <v-divider v-if="index < events.length - 1"></v-divider>
          </template>
        </template>
        <v-list-item v-else-if="!loading" class="px-5 py-8">
          <v-list-item-title class="text-center text-medium-emphasis">
            <v-icon size="48" color="grey-lighten-1" class="mb-2">mdi-calendar-blank</v-icon>
            <div>No upcoming events</div>
          </v-list-item-title>
        </v-list-item>
        <v-list-item v-else class="px-5 py-8">
          <v-list-item-title class="text-center">
            <v-progress-circular indeterminate color="primary"></v-progress-circular>
          </v-list-item-title>
        </v-list-item>
      </v-list>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import api from '@/services/api';
import { useToast } from 'vue-toastification';
import { differenceInDays, format, isToday, isTomorrow } from 'date-fns';

const toast = useToast();
const events = ref([]);
const loading = ref(false);

const fetchEvents = async () => {
  loading.value = true;
  try {
    const response = await api.get('/dashboard/upcoming-events');
    events.value = response.data;
  } catch (error) {
    console.error('Error fetching events:', error);
    toast.error('Failed to load upcoming events');
  } finally {
    loading.value = false;
  }
};

const getDaysUntilText = (dateString) => {
  try {
    const eventDate = new Date(dateString);
    
    if (isToday(eventDate)) {
      return 'Today';
    }
    
    if (isTomorrow(eventDate)) {
      return 'Tomorrow';
    }
    
    const days = differenceInDays(eventDate, new Date());
    
    if (days < 0) {
      return 'Past';
    }
    
    if (days === 0) {
      return 'Today';
    }
    
    if (days === 1) {
      return 'Tomorrow';
    }
    
    if (days <= 7) {
      return `${days} days`;
    }
    
    return format(eventDate, 'MMM d');
  } catch (error) {
    return dateString;
  }
};

const getDaysUntilColor = (dateString) => {
  try {
    const eventDate = new Date(dateString);
    const days = differenceInDays(eventDate, new Date());
    
    if (days < 0) return 'grey';
    if (days === 0) return 'error';
    if (days <= 3) return 'warning';
    if (days <= 7) return 'info';
    return 'success';
  } catch (error) {
    return 'grey';
  }
};

onMounted(() => {
  fetchEvents();
});
</script>

<style scoped lang="scss">
.modern-card {
  border-radius: 16px !important;
  border: 1px solid rgba(0, 0, 0, 0.08);
}

:deep(.v-list-item) {
  transition: background-color 0.2s;
  
  &:hover {
    background-color: rgba(0, 0, 0, 0.02);
  }
}
</style>
