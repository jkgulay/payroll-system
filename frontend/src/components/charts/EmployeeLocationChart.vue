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
const locationData = ref([]);

const chartData = computed(() => ({
  labels: locationData.value.map(d => d.label),
  datasets: [
    {
      label: 'Employees',
      data: locationData.value.map(d => d.value),
      backgroundColor: 'rgba(33, 150, 243, 0.8)',
      borderColor: 'rgba(33, 150, 243, 1)',
      borderWidth: 2,
      borderRadius: 8
    }
  ]
}));

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  indexAxis: 'y',
  plugins: {
    legend: {
      display: false
    },
    tooltip: {
      backgroundColor: 'rgba(0, 0, 0, 0.8)',
      padding: 12,
      callbacks: {
        label: function(context) {
          return context.parsed.x + ' employees';
        }
      }
    }
  },
  scales: {
    x: {
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
    y: {
      grid: {
        display: false
      }
    }
  }
};

const loadData = async () => {
  try {
    const response = await api.get('/dashboard/employee-by-location');
    locationData.value = response.data;
    loaded.value = true;
  } catch (error) {
    console.error('Error loading employee location data:', error);
    toast.error('Failed to load employee location data');
  }
};

onMounted(() => {
  loadData();
});

defineExpose({
  refresh: loadData
});
</script>
