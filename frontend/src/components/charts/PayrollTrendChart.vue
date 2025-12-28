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
  months: {
    type: Number,
    default: 12
  }
});

const toast = useToast();
const loaded = ref(false);
const trendData = ref([]);

const chartData = computed(() => ({
  labels: trendData.value.map(d => d.month),
  datasets: [
    {
      label: 'Net Pay',
      data: trendData.value.map(d => d.net_pay),
      borderColor: '#2196F3',
      backgroundColor: 'rgba(33, 150, 243, 0.1)',
      tension: 0.4,
      fill: true,
      borderWidth: 3,
      pointRadius: 4,
      pointHoverRadius: 6
    },
    {
      label: 'Basic Pay',
      data: trendData.value.map(d => d.basic_pay),
      borderColor: '#4CAF50',
      backgroundColor: 'rgba(76, 175, 80, 0.1)',
      tension: 0.4,
      fill: false,
      borderWidth: 2,
      pointRadius: 3
    },
    {
      label: 'Overtime',
      data: trendData.value.map(d => d.overtime),
      borderColor: '#FF9800',
      backgroundColor: 'rgba(255, 152, 0, 0.1)',
      tension: 0.4,
      fill: false,
      borderWidth: 2,
      pointRadius: 3
    }
  ]
}));

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: true,
      position: 'top',
      labels: {
        usePointStyle: true,
        padding: 15,
        font: {
          size: 12,
          weight: '500'
        }
      }
    },
    title: {
      display: false
    },
    tooltip: {
      mode: 'index',
      intersect: false,
      backgroundColor: 'rgba(0, 0, 0, 0.8)',
      titleColor: '#fff',
      bodyColor: '#fff',
      borderColor: '#2196F3',
      borderWidth: 1,
      padding: 12,
      displayColors: true,
      callbacks: {
        label: function(context) {
          let label = context.dataset.label || '';
          if (label) {
            label += ': ';
          }
          label += '₱' + context.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2 });
          return label;
        }
      }
    }
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        callback: function(value) {
          return '₱' + value.toLocaleString('en-US');
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
  },
  interaction: {
    mode: 'nearest',
    axis: 'x',
    intersect: false
  }
};

const loadData = async () => {
  try {
    const response = await api.get('/dashboard/payroll-trends', {
      params: { months: props.months }
    });
    trendData.value = response.data;
    loaded.value = true;
  } catch (error) {
    console.error('Error loading payroll trends:', error);
    toast.error('Failed to load payroll trends');
  }
};

onMounted(() => {
  loadData();
});

defineExpose({
  refresh: loadData
});
</script>
