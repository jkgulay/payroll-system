<template>
  <div>
    <Line :data="chartData" :options="chartOptions" v-if="loaded" />
    <div v-else class="text-center pa-5">
      <v-progress-circular indeterminate color="primary"></v-progress-circular>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { Line } from 'vue-chartjs';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
} from 'chart.js';
import api from '@/services/api';
import { useToast } from 'vue-toastification';

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
  Filler
);

const props = defineProps({
  days: {
    type: Number,
    default: 30
  }
});

const toast = useToast();
const loaded = ref(false);
const overtimeData = ref([]);

const chartData = computed(() => ({
  labels: overtimeData.value.map(d => d.date),
  datasets: [
    {
      label: 'Overtime Hours',
      data: overtimeData.value.map(d => d.hours),
      borderColor: '#FF9800',
      backgroundColor: 'rgba(255, 152, 0, 0.3)',
      tension: 0.4,
      fill: true,
      borderWidth: 3,
      pointRadius: 4,
      pointHoverRadius: 6,
      pointBackgroundColor: '#FF9800'
    }
  ]
}));

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    },
    tooltip: {
      backgroundColor: 'rgba(0, 0, 0, 0.8)',
      padding: 12,
      callbacks: {
        label: function(context) {
          return `${context.parsed.y.toFixed(2)} hours`;
        }
      }
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        callback: function(value) {
          return value + ' hrs';
        }
      },
      grid: {
        color: 'rgba(0, 0, 0, 0.05)'
      }
    },
    x: {
      grid: {
        display: false
      },
      ticks: {
        maxRotation: 45,
        minRotation: 45
      }
    }
  }
};

const loadData = async () => {
  try {
    const response = await api.get('/dashboard/overtime-trend', {
      params: { days: props.days }
    });
    overtimeData.value = response.data;
    loaded.value = true;
  } catch (error) {
    console.error('Error loading overtime trend:', error);
    toast.error('Failed to load overtime trend');
  }
};

onMounted(() => {
  loadData();
});

defineExpose({
  refresh: loadData
});
</script>
