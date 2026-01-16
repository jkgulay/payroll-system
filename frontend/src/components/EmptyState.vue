<template>
  <div class="empty-state">
    <v-card class="empty-state-card text-center pa-8" elevation="0">
      <div class="empty-state-icon mb-4">
        <v-avatar :size="iconSize" :color="iconColor" variant="tonal">
          <v-icon :size="iconSize * 0.6">{{ icon }}</v-icon>
        </v-avatar>
      </div>
      
      <h3 class="text-h6 mb-2 font-weight-bold">{{ title }}</h3>
      <p class="text-body-2 text-medium-emphasis mb-4">{{ description }}</p>
      
      <v-btn
        v-if="actionText"
        :color="actionColor"
        :prepend-icon="actionIcon"
        variant="elevated"
        @click="$emit('action')"
        class="construction-btn"
      >
        {{ actionText }}
      </v-btn>
    </v-card>
  </div>
</template>

<script setup>
defineProps({
  icon: {
    type: String,
    default: 'mdi-inbox-outline'
  },
  iconSize: {
    type: Number,
    default: 96
  },
  iconColor: {
    type: String,
    default: 'primary'
  },
  title: {
    type: String,
    default: 'No data available'
  },
  description: {
    type: String,
    default: 'There is no data to display at the moment'
  },
  actionText: {
    type: String,
    default: ''
  },
  actionIcon: {
    type: String,
    default: 'mdi-plus'
  },
  actionColor: {
    type: String,
    default: 'primary'
  }
});

defineEmits(['action']);
</script>

<style scoped>
.empty-state {
  min-height: 400px;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 20px;
}

.empty-state-card {
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 250, 252, 0.9) 100%) !important;
  backdrop-filter: blur(20px) saturate(180%) !important;
  -webkit-backdrop-filter: blur(20px) saturate(180%) !important;
  border: 2px dashed rgba(99, 102, 241, 0.3) !important;
  border-radius: 24px !important;
  max-width: 600px;
  margin: 0 auto;
  box-shadow: 0 20px 40px rgba(0, 0, 0, 0.08), 0 0 0 1px rgba(99, 102, 241, 0.05) inset !important;
  position: relative;
  overflow: hidden;
  transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
  
  &::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at top center, rgba(99, 102, 241, 0.05), transparent 70%);
    pointer-events: none;
  }
  
  &:hover {
    transform: translateY(-4px);
    box-shadow: 0 24px 48px rgba(99, 102, 241, 0.12), 0 0 0 1px rgba(99, 102, 241, 0.1) inset !important;
    border-color: rgba(99, 102, 241, 0.5) !important;
  }
}

.empty-state-icon {
  animation: float 4s ease-in-out infinite;
  
  :deep(.v-avatar) {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(139, 92, 246, 0.08) 100%) !important;
    border: 2px solid rgba(99, 102, 241, 0.2);
    backdrop-filter: blur(8px);
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.15);
  }
}

@keyframes float {
  0%, 100% {
    transform: translateY(0) rotate(0deg);
  }
  25% {
    transform: translateY(-12px) rotate(-2deg);
  }
  75% {
    transform: translateY(-8px) rotate(2deg);
  }
}

:deep(.construction-btn) {
  background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%) !important;
  box-shadow: 0 8px 20px rgba(99, 102, 241, 0.3);
  transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
  
  &:hover {
    transform: translateY(-3px) scale(1.05);
    box-shadow: 0 12px 28px rgba(99, 102, 241, 0.4);
  }
}
</style>
