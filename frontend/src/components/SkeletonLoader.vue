<template>
  <div class="skeleton-wrapper">
    <!-- Card Skeleton -->
    <v-card v-if="type === 'card'" class="industrial-card">
      <v-card-text class="pa-5">
        <v-skeleton-loader type="heading" class="mb-3"></v-skeleton-loader>
        <v-skeleton-loader type="text@3"></v-skeleton-loader>
      </v-card-text>
    </v-card>

    <!-- Stat Card Skeleton -->
    <v-card v-else-if="type === 'stat'" class="industrial-card">
      <v-card-text class="pa-5">
        <div class="d-flex align-center justify-space-between">
          <div style="flex: 1">
            <v-skeleton-loader type="text" width="80px" class="mb-2"></v-skeleton-loader>
            <v-skeleton-loader type="heading" width="120px" class="mb-2"></v-skeleton-loader>
            <v-skeleton-loader type="chip" width="100px"></v-skeleton-loader>
          </div>
          <v-skeleton-loader type="avatar" size="72"></v-skeleton-loader>
        </div>
      </v-card-text>
    </v-card>

    <!-- Table Skeleton -->
    <v-skeleton-loader v-else-if="type === 'table'" type="table"></v-skeleton-loader>

    <!-- Chart Skeleton -->
    <div v-else-if="type === 'chart'" class="chart-skeleton">
      <v-skeleton-loader type="heading" class="mb-4"></v-skeleton-loader>
      <div class="chart-bars">
        <div v-for="i in 5" :key="i" class="chart-bar" :style="{ height: `${Math.random() * 80 + 20}%` }"></div>
      </div>
    </div>

    <!-- List Skeleton -->
    <v-skeleton-loader v-else-if="type === 'list'" type="list-item@3"></v-skeleton-loader>

    <!-- Custom -->
    <v-skeleton-loader v-else :type="type"></v-skeleton-loader>
  </div>
</template>

<script setup>
defineProps({
  type: {
    type: String,
    default: 'card',
    validator: (value) => [
      'card', 'stat', 'table', 'chart', 'list', 
      'heading', 'text', 'avatar', 'chip', 'button'
    ].includes(value)
  }
});
</script>

<style scoped>
.chart-skeleton {
  padding: 24px;
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.95) 0%, rgba(248, 250, 252, 0.9) 100%);
  -webkit-backdrop-filter: blur(20px);
  backdrop-filter: blur(20px);
  border-radius: 20px;
  border: 1px solid rgba(99, 102, 241, 0.1);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
}

.chart-bars {
  display: flex;
  align-items: flex-end;
  justify-content: space-around;
  height: 240px;
  gap: 16px;
}

.chart-bar {
  flex: 1;
  background: linear-gradient(
    180deg,
    rgba(99, 102, 241, 0.2) 0%,
    rgba(139, 92, 246, 0.15) 50%,
    rgba(236, 72, 153, 0.1) 100%
  );
  border-radius: 12px 12px 0 0;
  animation: shimmer 2s cubic-bezier(0.4, 0, 0.2, 1) infinite;
  position: relative;
  overflow: hidden;
  
  &::before {
    content: '';
    position: absolute;
    top: -50%;
    left: -50%;
    width: 200%;
    height: 200%;
    background: linear-gradient(
      90deg,
      transparent 0%,
      rgba(255, 255, 255, 0.4) 50%,
      transparent 100%
    );
    animation: wave 2s ease-in-out infinite;
  }
}

@keyframes shimmer {
  0%, 100% {
    opacity: 1;
    transform: scaleY(1);
  }
  50% {
    opacity: 0.7;
    transform: scaleY(0.95);
  }
}

@keyframes wave {
  0% {
    transform: translateX(-100%) translateY(-100%) rotate(45deg);
  }
  100% {
    transform: translateX(100%) translateY(100%) rotate(45deg);
  }
}

:deep(.v-skeleton-loader__bone) {
  background: linear-gradient(
    90deg,
    rgba(99, 102, 241, 0.08) 0%,
    rgba(139, 92, 246, 0.12) 50%,
    rgba(99, 102, 241, 0.08) 100%
  );
  background-size: 200% 100%;
  animation: skeletonWave 2s ease-in-out infinite;
}

@keyframes skeletonWave {
  0% {
    background-position: 200% 0;
  }
  100% {
    background-position: -200% 0;
  }
}
</style>
