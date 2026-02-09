<template>
  <div>
    <Bar :data="chartData" :options="chartOptions" v-if="loaded" />
    <div v-else class="text-center pa-5">
      <v-progress-circular indeterminate color="primary"></v-progress-circular>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from "vue";
import { Bar } from "vue-chartjs";
import { devLog } from "@/utils/devLog";
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
} from "chart.js";
import api from "@/services/api";
import { useToast } from "vue-toastification";

ChartJS.register(
  CategoryScale,
  LinearScale,
  BarElement,
  Title,
  Tooltip,
  Legend,
);

const toast = useToast();
const loaded = ref(false);
const distributionData = ref([]);

const statusColors = {
  Active: "rgba(76, 175, 80, 0.8)",
  "On-Leave": "rgba(255, 152, 0, 0.8)",
  "On Leave": "rgba(255, 152, 0, 0.8)",
  Resigned: "rgba(244, 67, 54, 0.8)",
  Terminated: "rgba(158, 158, 158, 0.8)",
  Probationary: "rgba(33, 150, 243, 0.8)",
};

const chartData = computed(() => ({
  labels: distributionData.value.map((d) => d.label),
  datasets: [
    {
      label: "Employees",
      data: distributionData.value.map((d) => d.value),
      backgroundColor: distributionData.value.map(
        (d) => statusColors[d.label] || "rgba(158, 158, 158, 0.8)",
      ),
      borderColor: distributionData.value.map((d) =>
        (statusColors[d.label] || "rgba(158, 158, 158, 0.8)").replace(
          "0.8",
          "1",
        ),
      ),
      borderWidth: 2,
      borderRadius: 8,
    },
  ],
}));

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false,
    },
    tooltip: {
      backgroundColor: "rgba(0, 0, 0, 0.8)",
      padding: 12,
      callbacks: {
        label: function (context) {
          return context.parsed.y + " employees";
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
};

const loadData = async () => {
  try {
    const response = await api.get("/dashboard/employment-status-distribution");
    distributionData.value = response.data;
    loaded.value = true;
  } catch (error) {
    devLog.error("Error loading employment status distribution:", error);
    toast.error("Failed to load employment status distribution");
  }
};

onMounted(() => {
  loadData();
});

defineExpose({
  refresh: loadData,
});
</script>
