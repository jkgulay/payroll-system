<template>
  <v-card class="modern-card" elevation="0">
    <v-card-title class="pa-5">
      <v-icon color="primary" size="small" class="mr-2">mdi-history</v-icon>
      <div class="text-subtitle-1 font-weight-bold">Recent Activity</div>
      <v-spacer></v-spacer>
      <v-btn
        icon="mdi-refresh"
        size="small"
        variant="text"
        @click="fetchActivities"
        :loading="loading"
      ></v-btn>
    </v-card-title>
    <v-divider></v-divider>
    <v-card-text class="pa-0" style="max-height: 500px; overflow-y: auto;">
      <v-list class="py-0">
        <template v-if="activities.length > 0">
          <template v-for="(activity, index) in activities" :key="activity.id">
            <v-list-item class="px-5 py-3">
              <template v-slot:prepend>
                <v-avatar :color="getActivityColor(activity.action)" size="40">
                  <v-icon size="20" color="white">{{ getActivityIcon(activity.action) }}</v-icon>
                </v-avatar>
              </template>
              <v-list-item-title class="text-body-2 font-weight-medium mb-1">
                {{ activity.description }}
              </v-list-item-title>
              <v-list-item-subtitle class="text-caption">
                <v-icon size="14" class="mr-1">mdi-clock-outline</v-icon>
                {{ formatTimeAgo(activity.created_at) }}
                <template v-if="activity.user">
                  • {{ activity.user.name }}
                </template>
              </v-list-item-subtitle>
            </v-list-item>
            <v-divider v-if="index < activities.length - 1"></v-divider>
          </template>
        </template>
        <v-list-item v-else-if="!loading" class="px-5 py-8">
          <v-list-item-title class="text-center text-medium-emphasis">
            <v-icon size="48" color="grey-lighten-1" class="mb-2">mdi-history</v-icon>
            <div>No recent activity</div>
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
import { formatDistanceToNow } from 'date-fns';
import { devLog } from "@/utils/devLog";

const toast = useToast();
const activities = ref([]);
const loading = ref(false);

const fetchActivities = async () => {
  loading.value = true;
  try {
    const response = await api.get('/dashboard/recent-activities', {
      params: { limit: 15 }
    });
    activities.value = response.data;
  } catch (error) {
    devLog.error('Error fetching activities:', error);
    toast.error('Failed to load recent activities');
  } finally {
    loading.value = false;
  }
};

const getActivityIcon = (action) => {
  const iconMap = {
    employee_created: 'mdi-account-plus',
    employee_updated: 'mdi-account-edit',
    payroll_approved: 'mdi-cash-check',
    payroll_finalized: 'mdi-cash-lock',
    leave_approved: 'mdi-calendar-check',
    leave_rejected: 'mdi-calendar-remove',
    attendance_corrected: 'mdi-clock-edit',
    biometric_import: 'mdi-file-upload',
    application_approved: 'mdi-check-circle',
    application_rejected: 'mdi-close-circle',
  };
  return iconMap[action] || 'mdi-information';
};

const getActivityColor = (action) => {
  const colorMap = {
    employee_created: 'success',
    employee_updated: 'info',
    payroll_approved: 'success',
    payroll_finalized: 'primary',
    leave_approved: 'success',
    leave_rejected: 'error',
    attendance_corrected: 'warning',
    biometric_import: 'info',
    application_approved: 'success',
    application_rejected: 'error',
  };
  return colorMap[action] || 'grey';
};

const formatTimeAgo = (date) => {
  try {
    return formatDistanceToNow(new Date(date), { addSuffix: true });
  } catch (error) {
    return date;
  }
};

onMounted(() => {
  fetchActivities();
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
