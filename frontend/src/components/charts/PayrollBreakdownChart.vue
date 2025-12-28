<template>
  <div>
    <Doughnut :data="chartData" :options="chartOptions" v-if="loaded" />
    <div v-else class="text-center pa-5">
      <v-progress-circular indeterminate color="primary"></v-progress-circular>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue';
import { Doughnut } from 'vue-chartjs';
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from 'chart.js';
import api from '@/services/api';
import { useToast } from 'vue-toastification';

ChartJS.register(ArcElement, Tooltip, Legend);

const props = defineProps({
  period: {
    type: String,
    default: 'current-month'
  }
});

const toast = useToast();
const loaded = ref(false);
const breakdownData = ref(null);

const chartData = computed(() => ({
  labels: ['Basic Pay', 'Overtime', 'Allowances', 'Deductions', 'Net Pay'],
  datasets: [
    {
      data: [
        breakdownData.value?.basic_pay || 0,
        breakdownData.value?.overtime || 0,
        breakdownData.value?.allowances || 0,
        breakdownData.value?.deductions || 0,
        breakdownData.value?.net_pay || 0
      ],
      backgroundColor: [
        'rgba(76, 175, 80, 0.8)',  // Green - Basic Pay
        'rgba(255, 152, 0, 0.8)',  // Orange - Overtime
        'rgba(33, 150, 243, 0.8)', // Blue - Allowances
        'rgba(244, 67, 54, 0.8)',  // Red - Deductions
        'rgba(156, 39, 176, 0.8)'  // Purple - Net Pay
      ],
      borderColor: [
        'rgba(76, 175, 80, 1)',
        'rgba(255, 152, 0, 1)',
        'rgba(33, 150, 243, 1)',
        'rgba(244, 67, 54, 1)',
        'rgba(156, 39, 176, 1)'
      ],
      borderWidth: 2
    }
  ]
}));

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom',
      labels: {
        padding: 15,
        font: {
          size: 12,
          weight: '500'
        },
        usePointStyle: true
      }
    },
    tooltip: {
      backgroundColor: 'rgba(0, 0, 0, 0.8)',
      padding: 12,
      callbacks: {
        label: function(context) {
          const label = context.label || '';
          const value = context.parsed || 0;
          const total = context.dataset.data.reduce((a, b) => a + b, 0);
          const percentage = ((value / total) * 100).toFixed(1);
          return `${label}: ₱${value.toLocaleString('en-US', { minimumFractionDigits: 2 })} (${percentage}%)`;
        }
      }
    }
  }
};

const loadData = async () => {
  try {
    const response = await api.get('/dashboard/payroll-breakdown', {
      params: { period: props.period }
    });
    breakdownData.value = response.data;
    loaded.value = true;
  } catch (error) {
    console.error('Error loading payroll breakdown:', error);
    toast.error('Failed to load payroll breakdown');
  }
};

onMounted(() => {
  loadData();
});

defineExpose({
  refresh: loadData
});
</script>
