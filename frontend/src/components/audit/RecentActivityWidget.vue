<template>
  <div class="activity-widget">
    <div class="widget-header">
      <div class="widget-title-wrapper">
        <div class="widget-icon-badge">
          <v-icon size="18">mdi-history</v-icon>
        </div>
        <h2 class="widget-title">Recent Activity</h2>
      </div>
      <button
        class="view-all-btn"
        @click="$router.push({ name: 'audit-trail' })"
      >
        <span>View All</span>
        <v-icon size="16">mdi-arrow-right</v-icon>
      </button>
    </div>

    <div class="widget-content">
      <div v-if="activities.length > 0" class="activity-list">
        <div
          v-for="(activity, index) in activities"
          :key="activity.id"
          class="activity-item"
        >
          <div
            class="activity-icon-wrapper"
            :class="`activity-icon-${getActionColor(activity.action)}`"
          >
            <v-icon size="20">{{ getModuleIcon(activity.module) }}</v-icon>
          </div>
          <div class="activity-content">
            <div class="activity-description">
              {{ activity.description || "Activity performed" }}
            </div>
            <div class="activity-meta">
              <span
                class="activity-badge"
                :class="`badge-${getActionColor(activity.action)}`"
              >
                {{ formatAction(activity.action) }}
              </span>
              <span class="activity-user">{{
                activity.user?.name || "System"
              }}</span>
              <span class="activity-time">{{
                formatTime(activity.created_at)
              }}</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading State -->
      <div v-else-if="loading" class="empty-state">
        <v-progress-circular
          indeterminate
          color="#ED985F"
          size="32"
        ></v-progress-circular>
        <div class="empty-state-text">Loading activities...</div>
      </div>

      <!-- Empty State -->
      <div v-else class="empty-state">
        <v-icon size="48" color="rgba(0, 31, 61, 0.2)">mdi-history</v-icon>
        <div class="empty-state-text">No recent activity</div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import auditLogService from "@/services/auditLogService";

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
    console.error("Error fetching recent activities:", error);
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

  if (diffMins < 1) return "Just now";
  if (diffMins < 60) return `${diffMins}m ago`;
  if (diffHours < 24) return `${diffHours}h ago`;
  if (diffDays < 7) return `${diffDays}d ago`;

  return activityDate.toLocaleDateString("en-US", {
    month: "short",
    day: "numeric",
  });
}

function formatModule(module) {
  return module
    ? module.replace(/_/g, " ").replace(/\b\w/g, (l) => l.toUpperCase())
    : "";
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

<style scoped lang="scss">
.activity-widget {
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
  font-size: 16px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
  letter-spacing: -0.3px;
}

.view-all-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 8px 16px;
  background: rgba(237, 152, 95, 0.1);
  border: 1px solid rgba(237, 152, 95, 0.2);
  border-radius: 8px;
  color: #ed985f;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;

  .v-icon {
    color: #ed985f !important;
  }

  &:hover {
    background: rgba(237, 152, 95, 0.15);
    border-color: rgba(237, 152, 95, 0.3);
    transform: translateX(2px);
  }
}

.widget-content {
  padding: 16px 24px;
}

.activity-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.activity-item {
  display: flex;
  gap: 14px;
  padding: 14px;
  background: rgba(0, 31, 61, 0.02);
  border-radius: 10px;
  transition: all 0.3s ease;

  &:hover {
    background: rgba(237, 152, 95, 0.04);
    transform: translateX(2px);
  }
}

.activity-icon-wrapper {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;

  &.activity-icon-success {
    background: linear-gradient(
      135deg,
      rgba(76, 175, 80, 0.12) 0%,
      rgba(76, 175, 80, 0.08) 100%
    );
    .v-icon {
      color: #4caf50 !important;
    }
  }

  &.activity-icon-warning {
    background: linear-gradient(
      135deg,
      rgba(255, 152, 0, 0.12) 0%,
      rgba(255, 152, 0, 0.08) 100%
    );
    .v-icon {
      color: #ff9800 !important;
    }
  }

  &.activity-icon-error {
    background: linear-gradient(
      135deg,
      rgba(244, 67, 54, 0.12) 0%,
      rgba(244, 67, 54, 0.08) 100%
    );
    .v-icon {
      color: #f44336 !important;
    }
  }

  &.activity-icon-info {
    background: linear-gradient(
      135deg,
      rgba(33, 150, 243, 0.12) 0%,
      rgba(33, 150, 243, 0.08) 100%
    );
    .v-icon {
      color: #2196f3 !important;
    }
  }

  &.activity-icon-primary {
    background: linear-gradient(
      135deg,
      rgba(237, 152, 95, 0.12) 0%,
      rgba(247, 185, 128, 0.08) 100%
    );
    .v-icon {
      color: #ed985f !important;
    }
  }
}

.activity-content {
  flex: 1;
  min-width: 0;
}

.activity-description {
  font-size: 14px;
  font-weight: 600;
  color: #001f3d;
  margin-bottom: 6px;
  line-height: 1.4;
}

.activity-meta {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-wrap: wrap;
}

.activity-badge {
  font-size: 10px;
  font-weight: 700;
  padding: 3px 8px;
  border-radius: 6px;
  text-transform: uppercase;
  letter-spacing: 0.5px;

  &.badge-success {
    background: rgba(76, 175, 80, 0.15);
    color: #4caf50;
  }

  &.badge-warning {
    background: rgba(255, 152, 0, 0.15);
    color: #ff9800;
  }

  &.badge-error {
    background: rgba(244, 67, 54, 0.15);
    color: #f44336;
  }

  &.badge-info {
    background: rgba(33, 150, 243, 0.15);
    color: #2196f3;
  }

  &.badge-primary {
    background: rgba(237, 152, 95, 0.15);
    color: #ed985f;
  }
}

.activity-user,
.activity-time {
  font-size: 12px;
  color: rgba(0, 31, 61, 0.6);
}

.activity-user::after {
  content: "•";
  margin-left: 8px;
  color: rgba(0, 31, 61, 0.3);
}

.empty-state {
  padding: 48px 24px;
  text-align: center;

  .v-progress-circular {
    margin-bottom: 12px;
  }
}

.empty-state-text {
  font-size: 13px;
  color: rgba(0, 31, 61, 0.6);
  margin-top: 12px;
}
</style>
