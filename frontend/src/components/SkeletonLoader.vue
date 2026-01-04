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
  padding: 20px;
  background: white;
  border-radius: 8px;
}

.chart-bars {
  display: flex;
  align-items: flex-end;
  justify-content: space-around;
  height: 200px;
  gap: 12px;
}

.chart-bar {
  flex: 1;
  background: linear-gradient(
    180deg,
    #e0e0e0 0%,
    #f5f5f5 100%
  );
  border-radius: 4px 4px 0 0;
  animation: pulse 1.5s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% {
    opacity: 1;
  }
  50% {
    opacity: 0.6;
  }
}
</style>
