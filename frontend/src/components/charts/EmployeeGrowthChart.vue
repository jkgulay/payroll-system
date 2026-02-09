<template>
  <div>
    <Line :data="chartData" :options="chartOptions" v-if="loaded" />
    <div v-else class="text-center pa-5">
      <v-progress-circular indeterminate color="primary"></v-progress-circular>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import { Line } from "vue-chartjs";
import { devLog } from "@/utils/devLog";
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
} from "chart.js";
import api from "@/services/api";
import { useToast } from "vue-toastification";

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  Title,
  Tooltip,
  Legend,
);

const props = defineProps({
  months: {
    type: Number,
    default: 12,
  },
});

const toast = useToast();
const loaded = ref(false);
const growthData = ref([]);

const chartData = computed(() => ({
  labels: growthData.value.map((d) => d.month),
  datasets: [
    {
      label: "Hired",
      data: growthData.value.map((d) => d.hired),
      borderColor: "#4CAF50",
      backgroundColor: "rgba(76, 175, 80, 0.1)",
      tension: 0.4,
      fill: false,
      borderWidth: 3,
      pointRadius: 4,
      pointHoverRadius: 6,
    },
    {
      label: "Resigned",
      data: growthData.value.map((d) => d.resigned),
      borderColor: "#F44336",
      backgroundColor: "rgba(244, 67, 54, 0.1)",
      tension: 0.4,
      fill: false,
      borderWidth: 3,
      pointRadius: 4,
      pointHoverRadius: 6,
    },
    {
      label: "Net Change",
      data: growthData.value.map((d) => d.net_change),
      borderColor: "#2196F3",
      backgroundColor: "rgba(33, 150, 243, 0.1)",
      tension: 0.4,
      fill: false,
      borderWidth: 2,
      borderDash: [5, 5],
      pointRadius: 3,
    },
  ],
}));

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: "top",
      labels: {
        usePointStyle: true,
        padding: 15,
        font: {
          size: 12,
          weight: "500",
        },
      },
    },
    tooltip: {
      mode: "index",
      intersect: false,
      backgroundColor: "rgba(0, 0, 0, 0.8)",
      padding: 12,
      callbacks: {
        label: function (context) {
          return context.dataset.label + ": " + context.parsed.y + " employees";
        },
      },
    },
  },
  scales: {
    y: {
      beginAtZero: true,
      ticks: {
        stepSize: 1,
        callback: function (value) {
          return Math.floor(value);
        },
      },
      grid: {
        color: "rgba(0, 0, 0, 0.05)",
      },
    },
    x: {
      grid: {
        display: false,
      },
    },
  },
  interaction: {
    mode: "nearest",
    axis: "x",
    intersect: false,
  },
};

const loadData = async () => {
  try {
    const response = await api.get("/dashboard/employee-growth-trend", {
      params: { months: props.months },
    });
    growthData.value = response.data;
    loaded.value = true;
  } catch (error) {
    devLog.error("Error loading employee growth trend:", error);
    toast.error("Failed to load employee growth trend");
  }
};

onMounted(() => {
  loadData();
});

defineExpose({
  refresh: loadData,
});
</script>
