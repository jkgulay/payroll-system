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
  Filler,
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
  Filler,
);

const props = defineProps({
  months: {
    type: Number,
    default: 12,
  },
});

const toast = useToast();
const loaded = ref(false);
const trendData = ref([]);

const chartData = computed(() => ({
  labels: trendData.value.map((d) => d.month),
  datasets: [
    {
      label: "SSS",
      data: trendData.value.map((d) => d.sss),
      borderColor: "#2196F3",
      backgroundColor: "rgba(33, 150, 243, 0.3)",
      fill: true,
      tension: 0.4,
      borderWidth: 2,
      pointRadius: 3,
    },
    {
      label: "PhilHealth",
      data: trendData.value.map((d) => d.philhealth),
      borderColor: "#4CAF50",
      backgroundColor: "rgba(76, 175, 80, 0.3)",
      fill: true,
      tension: 0.4,
      borderWidth: 2,
      pointRadius: 3,
    },
    {
      label: "Pag-IBIG",
      data: trendData.value.map((d) => d.pagibig),
      borderColor: "#FF9800",
      backgroundColor: "rgba(255, 152, 0, 0.3)",
      fill: true,
      tension: 0.4,
      borderWidth: 2,
      pointRadius: 3,
    },
    {
      label: "Tax",
      data: trendData.value.map((d) => d.tax),
      borderColor: "#F44336",
      backgroundColor: "rgba(244, 67, 54, 0.3)",
      fill: true,
      tension: 0.4,
      borderWidth: 2,
      pointRadius: 3,
    },
  ],
}));

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  interaction: {
    mode: "index",
    intersect: false,
  },
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
          return (
            context.dataset.label +
            ": ₱" +
            context.parsed.y.toLocaleString("en-US", {
              minimumFractionDigits: 2,
            })
          );
        },
      },
    },
  },
  scales: {
    y: {
      stacked: true,
      beginAtZero: true,
      ticks: {
        callback: function (value) {
          return "₱" + value.toLocaleString("en-US");
        },
      },
      grid: {
        color: "rgba(0, 0, 0, 0.05)",
      },
    },
    x: {
      stacked: true,
      grid: {
        display: false,
      },
    },
  },
};

const loadData = async () => {
  try {
    const response = await api.get(
      "/dashboard/government-contribution-trends",
      {
        params: { months: props.months },
      },
    );
    trendData.value = response.data;
    loaded.value = true;
  } catch (error) {
    devLog.error("Error loading government contribution trends:", error);
    toast.error("Failed to load government contribution trends");
  }
};

onMounted(() => {
  loadData();
});

defineExpose({
  refresh: loadData,
});
</script>
