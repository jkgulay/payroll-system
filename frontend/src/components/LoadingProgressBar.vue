<template>
  <div v-if="loading" class="progress-bar-container">
    <div class="progress-bar-wrapper">
      <div class="progress-bar" :style="{ width: `${progress}%` }"></div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';

const props = defineProps({
  loading: {
    type: Boolean,
    default: false
  }
});

const progress = ref(0);
let intervalId = null;

watch(() => props.loading, (newVal) => {
  if (newVal) {
    startProgress();
  } else {
    completeProgress();
  }
});

function startProgress() {
  progress.value = 0;
  intervalId = setInterval(() => {
    if (progress.value < 90) {
      progress.value += Math.random() * 10;
    }
  }, 300);
}

function completeProgress() {
  if (intervalId) {
    clearInterval(intervalId);
    intervalId = null;
  }
  progress.value = 100;
  setTimeout(() => {
    progress.value = 0;
  }, 300);
}

onUnmounted(() => {
  if (intervalId) {
    clearInterval(intervalId);
  }
});
</script>

<style scoped>
.progress-bar-container {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  z-index: 9999;
  height: 3px;
}

.progress-bar-wrapper {
  width: 100%;
  height: 100%;
  background: transparent;
  overflow: hidden;
}

.progress-bar {
  height: 100%;
  background: linear-gradient(90deg, #D84315 0%, #FF6E40 50%, #F4511E 100%);
  transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  box-shadow: 0 0 10px rgba(216, 67, 21, 0.5);
  animation: shimmer 1.5s infinite;
}

@keyframes shimmer {
  0% {
    transform: translateX(-100%);
    opacity: 0.6;
  }
  50% {
    opacity: 1;
  }
  100% {
    transform: translateX(0%);
    opacity: 0.6;
  }
}
</style>
