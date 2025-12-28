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
  Legend
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
  Legend
);

const props = defineProps({
  days: {
    type: Number,
    default: 30
  }
});

const toast = useToast();
const loaded = ref(false);
const attendanceData = ref([]);

const chartData = computed(() => ({
  labels: attendanceData.value.map(d => d.date),
  datasets: [
    {
      label: 'Attendance Rate (%)',
      data: attendanceData.value.map(d => d.rate),
      borderColor: '#4CAF50',
      backgroundColor: 'rgba(76, 175, 80, 0.1)',
      tension: 0.4,
      fill: true,
      borderWidth: 3,
      pointRadius: 4,
      pointHoverRadius: 6,
      pointBackgroundColor: '#4CAF50'
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
          const dataPoint = attendanceData.value[context.dataIndex];
          return [
            `Rate: ${context.parsed.y.toFixed(2)}%`,
            `Present: ${dataPoint.present}`,
            `Total: ${dataPoint.total}`
          ];
        }
      }
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      max: 100,
      ticks: {
        callback: function(value) {
          return value + '%';
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
    const response = await api.get('/dashboard/attendance-rate', {
      params: { days: props.days }
    });
    attendanceData.value = response.data;
    loaded.value = true;
  } catch (error) {
    console.error('Error loading attendance rate:', error);
    toast.error('Failed to load attendance rate');
  }
};

onMounted(() => {
  loadData();
});

defineExpose({
  refresh: loadData
});
</script>
