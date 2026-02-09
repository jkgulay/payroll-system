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
import { devLog } from "@/utils/devLog";

ChartJS.register(ArcElement, Tooltip, Legend);

const props = defineProps({
  type: {
    type: String,
    default: 'project',
    validator: (value) => ['project', 'department'].includes(value)
  }
});

const toast = useToast();
const loaded = ref(false);
const distributionData = ref([]);

const colors = [
  'rgba(33, 150, 243, 0.8)',
  'rgba(76, 175, 80, 0.8)',
  'rgba(255, 152, 0, 0.8)',
  'rgba(156, 39, 176, 0.8)',
  'rgba(244, 67, 54, 0.8)',
  'rgba(0, 188, 212, 0.8)',
  'rgba(255, 193, 7, 0.8)',
  'rgba(121, 85, 72, 0.8)',
  'rgba(158, 158, 158, 0.8)',
  'rgba(233, 30, 99, 0.8)'
];

const chartData = computed(() => ({
  labels: distributionData.value.map(d => d.label),
  datasets: [
    {
      data: distributionData.value.map(d => d.value),
      backgroundColor: colors.slice(0, distributionData.value.length),
      borderColor: colors.slice(0, distributionData.value.length).map(c => c.replace('0.8', '1')),
      borderWidth: 2
    }
  ]
}));

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'right',
      labels: {
        padding: 15,
        font: {
          size: 12,
          weight: '500'
        },
        usePointStyle: true,
        generateLabels: function(chart) {
          const data = chart.data;
          if (data.labels.length && data.datasets.length) {
            return data.labels.map((label, i) => {
              const value = data.datasets[0].data[i];
              const total = data.datasets[0].data.reduce((a, b) => a + b, 0);
              const percentage = ((value / total) * 100).toFixed(1);
              return {
                text: `${label} (${percentage}%)`,
                fillStyle: data.datasets[0].backgroundColor[i],
                hidden: false,
                index: i
              };
            });
          }
          return [];
        }
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
          return `${label}: ${value} employees (${percentage}%)`;
        }
      }
    }
  }
};

const loadData = async () => {
  try {
    const response = await api.get('/dashboard/employee-distribution', {
      params: { type: props.type }
    });
    distributionData.value = response.data;
    loaded.value = true;
  } catch (error) {
    devLog.error('Error loading employee distribution:', error);
    toast.error('Failed to load employee distribution');
  }
};

onMounted(() => {
  loadData();
});

defineExpose({
  refresh: loadData
});
</script>
