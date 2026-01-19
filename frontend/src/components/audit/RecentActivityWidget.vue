<template>
  <v-card elevation="2" class="modern-card">
    <v-card-title class="pa-5">
      <v-icon color="info" size="small" class="mr-2">mdi-history</v-icon>
      <div class="text-subtitle-1 font-weight-bold">Recent Activity</div>
      <v-spacer></v-spacer>
      <v-btn
        variant="text"
        size="small"
        :to="{ name: 'audit-trail' }"
        append-icon="mdi-arrow-right"
        color="primary"
      >
        View All
      </v-btn>
    </v-card-title>
    <v-divider></v-divider>

    <v-card-text class="pa-0">
      <v-list v-if="activities.length > 0" class="py-0" density="compact">
        <template v-for="(activity, index) in activities" :key="activity.id">
          <v-list-item class="px-4 py-3">
            <template v-slot:prepend>
              <v-avatar
                :color="getActionColor(activity.action)"
                size="36"
              >
                <v-icon :icon="getModuleIcon(activity.module)" size="20"></v-icon>
              </v-avatar>
            </template>

            <v-list-item-title class="font-weight-medium text-body-2 mb-1">
              {{ activity.description || 'Activity performed' }}
            </v-list-item-title>
            
            <v-list-item-subtitle class="mt-1">
              <div class="d-flex align-center flex-wrap gap-1">
                <v-chip
                  size="x-small"
                  variant="flat"
                  :color="getActionColor(activity.action)"
                  class="text-uppercase font-weight-bold"
                  style="font-size: 9px;"
                >
                  {{ formatAction(activity.action) }}
                </v-chip>
                <span class="text-caption text-medium-emphasis">
                  {{ activity.user?.name || 'System' }} • {{ formatTime(activity.created_at) }}
                </span>
              </div>
            </v-list-item-subtitle>
          </v-list-item>
          <v-divider v-if="index < activities.length - 1" class="mx-4"></v-divider>
        </template>
      </v-list>

      <!-- Loading State -->
      <div v-else-if="loading" class="pa-8 text-center">
        <v-progress-circular indeterminate color="primary" size="32"></v-progress-circular>
        <div class="text-caption text-medium-emphasis mt-2">Loading activities...</div>
      </div>

      <!-- Empty State -->
      <div v-else class="pa-8 text-center">
        <v-icon size="48" color="grey-lighten-2">mdi-history</v-icon>
        <div class="text-body-2 text-medium-emphasis mt-2">
          No recent activity
        </div>
      </div>
    </v-card-text>
  </v-card>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import auditLogService from '@/services/auditLogService';

// Props
const props = defineProps({
  limit: {
    type: Number,
    default: 10,
  },
  module: {
    type: String,
    default: null,
  },
});

// Data
const activities = ref([]);
const loading = ref(false);

// Methods
async function fetchRecentActivities() {
  loading.value = true;
  try {
    const params = {
      limit: props.limit,
    };
    if (props.module) {
      params.module = props.module;
    }

    const response = await auditLogService.getAll(params);
    activities.value = response.data?.slice(0, props.limit) || [];
  } catch (error) {
    console.error('Error fetching recent activities:', error);
  } finally {
    loading.value = false;
  }
}

function formatTime(date) {
  const now = new Date();
  const activityDate = new Date(date);
  const diffMs = now - activityDate;
  const diffMins = Math.floor(diffMs / 60000);
  const diffHours = Math.floor(diffMs / 3600000);
  const diffDays = Math.floor(diffMs / 86400000);

  if (diffMins < 1) return 'Just now';
  if (diffMins < 60) return `${diffMins}m ago`;
  if (diffHours < 24) return `${diffHours}h ago`;
  if (diffDays < 7) return `${diffDays}d ago`;
  
  return activityDate.toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
  });
}

function formatModule(module) {
  return module ? module.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase()) : '';
}

function formatAction(action) {
  return auditLogService.formatAction(action);
}

function getActionColor(action) {
  return auditLogService.getActionColor(action);
}

function getModuleIcon(module) {
  return auditLogService.getModuleIcon(module);
}

// Lifecycle
onMounted(() => {
  fetchRecentActivities();
});
</script>

<style scoped>
.modern-card {
  border-radius: 12px;
  overflow: hidden;
}

.v-list-item {
  min-height: 64px;
}

.v-list-item-subtitle {
  opacity: 1 !important;
}

.gap-1 {
  gap: 4px;
}
</style>
