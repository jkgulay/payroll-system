import { defineStore } from "pinia";
import { ref } from "vue";
import api from "@/services/api";

// Event bus for attendance updates
const attendanceEventBus = new EventTarget();

export const useAttendanceStore = defineStore("attendance", () => {
  // State
  const attendances = ref([]);
  const currentAttendance = ref(null);
  const loading = ref(false);

  // Helper function to emit events
  const emitAttendanceUpdate = (type, data = null) => {
    const event = new CustomEvent("attendance-updated", {
      detail: { type, data, timestamp: Date.now() },
    });
    attendanceEventBus.dispatchEvent(event);
  };

  // Actions
  async function fetchAttendances(params = {}) {
    loading.value = true;
    try {
      const response = await api.get("/attendance", { params });
      attendances.value = response.data.data;
      return response.data;
    } catch (error) {
      throw error;
    } finally {
      loading.value = false;
    }
  }

  async function fetchAttendance(id) {
    loading.value = true;
    try {
      const response = await api.get(`/attendance/${id}`);
      currentAttendance.value = response.data;
      return response.data;
    } catch (error) {
      throw error;
    } finally {
      loading.value = false;
    }
  }

  async function createAttendance(data) {
    loading.value = true;
    try {
      const response = await api.post("/attendance", data);
      attendances.value.unshift(response.data);
      emitAttendanceUpdate("created", response.data);
      return response.data;
    } catch (error) {
      throw error;
    } finally {
      loading.value = false;
    }
  }

  async function updateAttendance(id, data) {
    loading.value = true;
    try {
      const response = await api.put(`/attendance/${id}`, data);
      const index = attendances.value.findIndex((a) => a.id === id);
      if (index !== -1) {
        attendances.value[index] = response.data;
      }
      emitAttendanceUpdate("updated", response.data);
      return response.data;
    } catch (error) {
      throw error;
    } finally {
      loading.value = false;
    }
  }

  async function deleteAttendance(id) {
    loading.value = true;
    try {
      await api.delete(`/attendance/${id}`);
      attendances.value = attendances.value.filter((a) => a.id !== id);
      emitAttendanceUpdate("deleted", { id });
    } catch (error) {
      throw error;
    } finally {
      loading.value = false;
    }
  }

  async function importBiometric(data) {
    loading.value = true;
    try {
      const response = await api.post("/attendance/import-biometric", data);
      return response.data;
    } catch (error) {
      throw error;
    } finally {
      loading.value = false;
    }
  }

  async function approveAttendance(id) {
    loading.value = true;
    try {
      const response = await api.post(`/attendance/${id}/approve`);
      const index = attendances.value.findIndex((a) => a.id === id);
      if (index !== -1) {
        attendances.value[index] = response.data;
      }
      return response.data;
    } catch (error) {
      throw error;
    } finally {
      loading.value = false;
    }
  }

  async function rejectAttendance(id, reason) {
    loading.value = true;
    try {
      const response = await api.post(`/attendance/${id}/reject`, { reason });
      const index = attendances.value.findIndex((a) => a.id === id);
      if (index !== -1) {
        attendances.value[index] = response.data;
      }
      return response.data;
    } catch (error) {
      throw error;
    } finally {
      loading.value = false;
    }
  }

  async function fetchEmployeeSummary(employeeId, params = {}) {
    loading.value = true;
    try {
      const response = await api.get(
        `/attendance/employee/${employeeId}/summary`,
        { params }
      );
      return response.data;
    } catch (error) {
      throw error;
    } finally {
      loading.value = false;
    }
  }

  return {
    // State
    attendances,
    currentAttendance,
    loading,
    // Actions
    fetchAttendances,
    fetchAttendance,
    createAttendance,
    updateAttendance,
    deleteAttendance,
    importBiometric,
    approveAttendance,
    rejectAttendance,
    fetchEmployeeSummary,
  };
});

// Export event subscription helper
export const onAttendanceUpdate = (callback) => {
  const handler = (event) => callback(event.detail);
  attendanceEventBus.addEventListener("attendance-updated", handler);
  // Return unsubscribe function
  return () =>
    attendanceEventBus.removeEventListener("attendance-updated", handler);
};
