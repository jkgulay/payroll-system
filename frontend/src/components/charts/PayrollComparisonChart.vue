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

const toast = useToast();
const loaded = ref(false);
const comparisonData = ref(null);

const chartData = computed(() => ({
  labels: [
    comparisonData.value?.previous_period?.label || 'Previous',
    comparisonData.value?.current_period?.label || 'Current'
  ],
  datasets: [
    {
      label: 'Total Payroll',
      data: [
        comparisonData.value?.previous_period?.amount || 0,
        comparisonData.value?.current_period?.amount || 0
      ],
      backgroundColor: [
        'rgba(158, 158, 158, 0.8)',
        'rgba(33, 150, 243, 0.8)'
      ],
      borderColor: [
        'rgba(158, 158, 158, 1)',
        'rgba(33, 150, 243, 1)'
      ],
      borderWidth: 2,
      borderRadius: 8,
      barThickness: 60
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
          return '₱' + context.parsed.y.toLocaleString('en-US', { minimumFractionDigits: 2 });
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
  }
};

const loadData = async () => {
  try {
    const response = await api.get('/dashboard/payroll-comparison');
    comparisonData.value = response.data;
    loaded.value = true;
  } catch (error) {
    console.error('Error loading payroll comparison:', error);
    toast.error('Failed to load payroll comparison');
  }
};

onMounted(() => {
  loadData();
});

defineExpose({
  refresh: loadData
});
</script>
