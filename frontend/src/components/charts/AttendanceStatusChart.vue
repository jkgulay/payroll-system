<template>
  <div>
    <Bar :data="chartData" :options="chartOptions" v-if="loaded" />
    <div v-else class="text-center pa-5">
      <v-progress-circular indeterminate color="primary"></v-progress-circular>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { Bar } from 'vue-chartjs';
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend
} from 'chart.js';
import api from '@/services/api';
import { useToast } from 'vue-toastification';

ChartJS.register(CategoryScale, LinearScale, BarElement, Title, Tooltip, Legend);

const props = defineProps({
  period: {
    type: String,
    default: 'current-month'
  }
});

const toast = useToast();
const loaded = ref(false);
const statusData = ref([]);

const statusColors = {
  'Present': 'rgba(76, 175, 80, 0.8)',
  'Absent': 'rgba(244, 67, 54, 0.8)',
  'Late': 'rgba(255, 152, 0, 0.8)',
  'Undertime': 'rgba(255, 193, 7, 0.8)',
  'On-Leave': 'rgba(33, 150, 243, 0.8)',
  'On Leave': 'rgba(33, 150, 243, 0.8)'
};

const chartData = computed(() => ({
  labels: statusData.value.map(d => d.label),
  datasets: [
    {
      label: 'Days',
      data: statusData.value.map(d => d.value),
      backgroundColor: statusData.value.map(d => statusColors[d.label] || 'rgba(158, 158, 158, 0.8)'),
      borderColor: statusData.value.map(d => (statusColors[d.label] || 'rgba(158, 158, 158, 0.8)').replace('0.8', '1')),
      borderWidth: 2,
      borderRadius: 8
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
          const total = context.dataset.data.reduce((a, b) => a + b, 0);
          const percentage = ((context.parsed.y / total) * 100).toFixed(1);
          return `${context.parsed.y} days (${percentage}%)`;
        }
      }
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        stepSize: 1,
        callback: function(value) {
          return Math.floor(value);
        }
      },
      grid: {
        color: 'rgba(0, 0, 0, 0.05)'
      }
    },
    x: {
      grid: {
        display: false
      }
    }
  }
};

const loadData = async () => {
  try {
    const response = await api.get('/dashboard/attendance-status-distribution', {
      params: { period: props.period }
    });
    statusData.value = response.data;
    loaded.value = true;
  } catch (error) {
    console.error('Error loading attendance status distribution:', error);
    toast.error('Failed to load attendance status distribution');
  }
};

onMounted(() => {
  loadData();
});

defineExpose({
  refresh: loadData
});
</script>
