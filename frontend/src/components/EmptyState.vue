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
  min-height: 300px;
  display: flex;
  align-items: center;
  justify-content: center;
}

.empty-state-card {
  background: linear-gradient(135deg, #FFFFFF 0%, #FAFAFA 100%) !important;
  border: 2px dashed rgba(0, 0, 0, 0.12);
  border-radius: 12px !important;
  max-width: 500px;
  margin: 0 auto;
}

.empty-state-icon {
  animation: float 3s ease-in-out infinite;
}

@keyframes float {
  0%, 100% {
    transform: translateY(0);
  }
  50% {
    transform: translateY(-10px);
  }
}
</style>
