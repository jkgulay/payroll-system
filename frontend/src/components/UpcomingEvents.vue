<template>
  <div class="events-widget">
    <div class="widget-header">
      <div class="widget-title-wrapper">
        <div class="widget-icon-badge">
          <v-icon size="18">mdi-calendar-star</v-icon>
        </div>
        <h2 class="widget-title">Upcoming Events</h2>
      </div>
      <button class="refresh-btn" @click="fetchEvents" :disabled="loading">
        <v-icon size="18" :class="{ rotating: loading }">mdi-refresh</v-icon>
      </button>
    </div>
    <div class="widget-content" style="max-height: 500px; overflow-y: auto">
      <div v-if="events.length > 0" class="events-list">
        <div v-for="(event, index) in events" :key="index" class="event-item">
          <div
            class="event-icon-wrapper"
            :style="{ background: getIconGradient(event.color) }"
          >
            <v-icon size="20">{{ event.icon }}</v-icon>
          </div>
          <div class="event-content">
            <div class="event-title">{{ event.title }}</div>
            <div class="event-description">
              <v-icon size="14">mdi-calendar</v-icon>
              <span>{{ event.description }}</span>
            </div>
          </div>
          <div
            class="event-badge"
            :class="`badge-${getDaysUntilColor(event.date)}`"
          >
            {{ getDaysUntilText(event.date) }}
          </div>
        </div>
      </div>
      <div v-else-if="!loading" class="empty-state">
        <v-icon size="48" color="rgba(0, 31, 61, 0.2)"
          >mdi-calendar-blank</v-icon
        >
        <div class="empty-state-text">No upcoming events</div>
      </div>
      <div v-else class="empty-state">
        <v-progress-circular
          indeterminate
          color="#ED985F"
        ></v-progress-circular>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import api from "@/services/api";
import { useToast } from "vue-toastification";
import { differenceInDays, format, isToday, isTomorrow } from "date-fns";

const toast = useToast();
const events = ref([]);
const loading = ref(false);

const fetchEvents = async () => {
  loading.value = true;
  try {
    const response = await api.get("/dashboard/upcoming-events");
    events.value = response.data;
  } catch (error) {
    console.error("Error fetching events:", error);
    toast.error("Failed to load upcoming events");
  } finally {
    loading.value = false;
  }
};

const getDaysUntilText = (dateString) => {
  try {
    const eventDate = new Date(dateString);

    if (isToday(eventDate)) {
      return "Today";
    }

    if (isTomorrow(eventDate)) {
      return "Tomorrow";
    }

    const days = differenceInDays(eventDate, new Date());

    if (days < 0) {
      return "Past";
    }

    if (days === 0) {
      return "Today";
    }

    if (days === 1) {
      return "Tomorrow";
    }

    if (days <= 7) {
      return `${days} days`;
    }

    return format(eventDate, "MMM d");
  } catch (error) {
    return dateString;
  }
};

const getDaysUntilColor = (dateString) => {
  try {
    const eventDate = new Date(dateString);
    const days = differenceInDays(eventDate, new Date());

    if (days < 0) return "grey";
    if (days === 0) return "error";
    if (days <= 3) return "warning";
    if (days <= 7) return "info";
    return "success";
  } catch (error) {
    return "grey";
  }
};

const getIconGradient = (color) => {
  const gradients = {
    success:
      "linear-gradient(135deg, rgba(76, 175, 80, 0.12) 0%, rgba(76, 175, 80, 0.08) 100%)",
    warning:
      "linear-gradient(135deg, rgba(255, 152, 0, 0.12) 0%, rgba(255, 152, 0, 0.08) 100%)",
    error:
      "linear-gradient(135deg, rgba(244, 67, 54, 0.12) 0%, rgba(244, 67, 54, 0.08) 100%)",
    info: "linear-gradient(135deg, rgba(33, 150, 243, 0.12) 0%, rgba(33, 150, 243, 0.08) 100%)",
    primary:
      "linear-gradient(135deg, rgba(237, 152, 95, 0.12) 0%, rgba(247, 185, 128, 0.08) 100%)",
  };
  return gradients[color] || gradients.primary;
};

onMounted(() => {
  fetchEvents();
});
</script>

<style scoped lang="scss">
.events-widget {
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

.refresh-btn {
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
    transition: transform 0.6s ease;

    &.rotating {
      animation: rotate 1s linear infinite;
    }
  }

  &:hover:not(:disabled) {
    background: rgba(237, 152, 95, 0.15);
    border-color: rgba(237, 152, 95, 0.3);
  }

  &:disabled {
    opacity: 0.6;
    cursor: not-allowed;
  }
}

@keyframes rotate {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.widget-content {
  padding: 16px 24px;
}

.events-list {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.event-item {
  display: flex;
  align-items: center;
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

.event-icon-wrapper {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;

  .v-icon {
    color: #001f3d !important;
  }
}

.event-content {
  flex: 1;
  min-width: 0;
}

.event-title {
  font-size: 14px;
  font-weight: 600;
  color: #001f3d;
  margin-bottom: 4px;
  line-height: 1.4;
}

.event-description {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: rgba(0, 31, 61, 0.6);

  .v-icon {
    color: rgba(0, 31, 61, 0.4) !important;
  }
}

.event-badge {
  font-size: 11px;
  font-weight: 700;
  padding: 6px 12px;
  border-radius: 8px;
  white-space: nowrap;
  flex-shrink: 0;

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

  &.badge-grey {
    background: rgba(0, 31, 61, 0.1);
    color: rgba(0, 31, 61, 0.6);
  }
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
