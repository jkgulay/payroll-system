<template>
  <div>
    <Pie :data="chartData" :options="chartOptions" v-if="loaded" />
    <div v-else class="text-center pa-5">
      <v-progress-circular indeterminate color="primary"></v-progress-circular>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed, onUnmounted } from "vue";
import { Pie } from "vue-chartjs";
import { Chart as ChartJS, ArcElement, Tooltip, Legend } from "chart.js";
import api from "@/services/api";
import { useToast } from "vue-toastification";
import { onAttendanceUpdate } from "@/stores/attendance";
import { devLog } from "@/utils/devLog";

ChartJS.register(ArcElement, Tooltip, Legend);

const toast = useToast();
const loaded = ref(false);
const statusData = ref([]);

const statusColors = {
  Leave: "#ef5350",
  "Business Trip": "#2196f3",
  Vacation: "#ff9800",
  Punched: "#4caf50",
  Unpunched: "#ffeb3b",
  Late: "#f44336",
  Present: "#4caf50",
  Absent: "#ef5350",
  "On Leave": "#2196f3",
  "Holiday OT": "#9c27b0",
  "Weekend OT": "#673ab7",
  "Normal OT": "#3f51b5",
  "Leave Early": "#ff5722",
};

const chartData = computed(() => ({
  labels: statusData.value.map((d) => d.label),
  datasets: [
    {
      data: statusData.value.map((d) => d.value),
      backgroundColor: statusData.value.map(
        (d) => statusColors[d.label] || "#9e9e9e"
      ),
      borderColor: "#ffffff",
      borderWidth: 2,
      hoverOffset: 10,
    },
  ],
}));

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: "bottom",
      labels: {
        padding: 15,
        font: {
          size: 11,
        },
        usePointStyle: true,
        pointStyle: "circle",
      },
    },
    tooltip: {
      backgroundColor: "rgba(0, 0, 0, 0.8)",
      padding: 12,
      callbacks: {
        label: function (context) {
          const label = context.label || "";
          const value = context.parsed;
          const total = context.dataset.data.reduce((a, b) => a + b, 0);
          const percentage = ((value / total) * 100).toFixed(1);
          return `${label}: ${value} (${percentage}%)`;
        },
      },
    },
  },
};

const loadData = async () => {
  try {
    const response = await api.get("/dashboard/today-staff-info");
    statusData.value = response.data;
    loaded.value = true;
  } catch (error) {
    devLog.error("Error loading today staff info:", error);
    toast.error("Failed to load today staff info");
  }
};

let unsubscribeAttendance = null;

onMounted(() => {
  loadData();

  // Listen for attendance updates
  unsubscribeAttendance = onAttendanceUpdate(() => {
    loadData();
  });
});

onUnmounted(() => {
  if (unsubscribeAttendance) {
    unsubscribeAttendance();
  }
});

defineExpose({
  refresh: loadData,
});
</script>
