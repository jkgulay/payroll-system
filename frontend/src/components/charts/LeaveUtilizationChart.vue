<template>
  <div>
    <div v-if="loaded" class="leave-utilization-container">
      <!-- Gauge Chart Alternative using CSS -->
      <div class="gauge-chart">
        <svg viewBox="0 0 200 120" class="gauge-svg">
          <!-- Background arc -->
          <path
            :d="backgroundArc"
            fill="none"
            stroke="#e0e0e0"
            stroke-width="20"
            stroke-linecap="round"
          />
          <!-- Progress arc -->
          <path
            :d="progressArc"
            fill="none"
            :stroke="gaugeColor"
            stroke-width="20"
            stroke-linecap="round"
            class="gauge-progress"
          />
          <!-- Center text -->
          <text
            x="100"
            y="80"
            text-anchor="middle"
            class="gauge-value"
            :fill="gaugeColor"
          >
            {{ utilizationRate }}%
          </text>
          <text x="100" y="100" text-anchor="middle" class="gauge-label">
            Utilization
          </text>
        </svg>
      </div>

      <!-- Stats Cards -->
      <v-row class="mt-4">
        <v-col cols="4">
          <div class="stat-box text-center">
            <div class="stat-value text-h5 font-weight-bold" style="color: #FF9800">
              {{ leaveData.on_leave }}
            </div>
            <div class="stat-label text-caption">On Leave</div>
          </div>
        </v-col>
        <v-col cols="4">
          <div class="stat-box text-center">
            <div class="stat-value text-h5 font-weight-bold" style="color: #4CAF50">
              {{ leaveData.available }}
            </div>
            <div class="stat-label text-caption">Available</div>
          </div>
        </v-col>
        <v-col cols="4">
          <div class="stat-box text-center">
            <div class="stat-value text-h5 font-weight-bold" style="color: #2196F3">
              {{ leaveData.total }}
            </div>
            <div class="stat-label text-caption">Total</div>
          </div>
        </v-col>
      </v-row>
    </div>
    <div v-else class="text-center pa-5">
      <v-progress-circular indeterminate color="primary"></v-progress-circular>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import api from '@/services/api';
import { useToast } from 'vue-toastification';

const toast = useToast();
const loaded = ref(false);
const leaveData = ref({
  on_leave: 0,
  available: 0,
  total: 0,
  utilization_rate: 0
});

const utilizationRate = computed(() => leaveData.value.utilization_rate.toFixed(1));

const gaugeColor = computed(() => {
  const rate = leaveData.value.utilization_rate;
  if (rate < 30) return '#4CAF50'; // Green
  if (rate < 60) return '#FF9800'; // Orange
  return '#F44336'; // Red
});

const polarToCartesian = (centerX, centerY, radius, angleInDegrees) => {
  const angleInRadians = ((angleInDegrees - 90) * Math.PI) / 180.0;
  return {
    x: centerX + radius * Math.cos(angleInRadians),
    y: centerY + radius * Math.sin(angleInRadians)
  };
};

const describeArc = (x, y, radius, startAngle, endAngle) => {
  const start = polarToCartesian(x, y, radius, endAngle);
  const end = polarToCartesian(x, y, radius, startAngle);
  const largeArcFlag = endAngle - startAngle <= 180 ? '0' : '1';
  return [
    'M',
    start.x,
    start.y,
    'A',
    radius,
    radius,
    0,
    largeArcFlag,
    0,
    end.x,
    end.y
  ].join(' ');
};

const backgroundArc = computed(() => {
  return describeArc(100, 100, 70, -90, 90);
});

const progressArc = computed(() => {
  const rate = leaveData.value.utilization_rate;
  const endAngle = -90 + (rate / 100) * 180;
  return describeArc(100, 100, 70, -90, endAngle);
});

const loadData = async () => {
  try {
    const response = await api.get('/dashboard/leave-utilization');
    leaveData.value = response.data;
    loaded.value = true;
  } catch (error) {
    console.error('Error loading leave utilization:', error);
    toast.error('Failed to load leave utilization');
  }
};

onMounted(() => {
  loadData();
});

defineExpose({
  refresh: loadData
});
</script>

<style scoped>
.leave-utilization-container {
  padding: 20px;
}

.gauge-chart {
  max-width: 300px;
  margin: 0 auto;
}

.gauge-svg {
  width: 100%;
  height: auto;
}

.gauge-progress {
  transition: stroke-dashoffset 0.5s ease;
}

.gauge-value {
  font-size: 32px;
  font-weight: bold;
}

.gauge-label {
  font-size: 14px;
  fill: #666;
}

.stat-box {
  padding: 12px;
  border-radius: 8px;
  background: rgba(0, 0, 0, 0.02);
}

.stat-value {
  margin-bottom: 4px;
}

.stat-label {
  color: #666;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}
</style>
