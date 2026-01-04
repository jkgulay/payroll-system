import { ref, computed } from "vue";
import api from "@/services/api";

// Shared reactive position rates from database
const positionRates = ref([]);
const loading = ref(false);
const loaded = ref(false);

export function usePositionRates() {
  // Load position rates from backend API
  const loadPositionRates = async () => {
    if (loaded.value && positionRates.value.length > 0) {
      return; // Already loaded
    }

    loading.value = true;
    try {
      const response = await api.get("/position-rates", {
        params: { active_only: true },
      });
      positionRates.value = response.data || [];
      loaded.value = true;
    } catch (error) {
      console.error("Error loading position rates:", error);
      positionRates.value = [];
    } finally {
      loading.value = false;
    }
  };

  // Position options for dropdown (sorted alphabetically)
  const positionOptions = computed(() => {
    return positionRates.value.map((rate) => rate.position_name).sort();
  });

  // Get rate for a position
  const getRate = (positionName) => {
    const position = positionRates.value.find(
      (rate) => rate.position_name === positionName
    );
    return position ? parseFloat(position.daily_rate) : 450; // Default to 450 if not found
  };

  // Get full position rate object
  const getPositionRate = (positionName) => {
    return positionRates.value.find(
      (rate) => rate.position_name === positionName
    );
  };

  // Add new position rate
  const addPosition = async (positionData) => {
    try {
      const response = await api.post("/position-rates", positionData);
      positionRates.value.push(response.data.data);
      return response.data.data;
    } catch (error) {
      console.error("Error adding position:", error);
      throw error;
    }
  };

  // Update position rate
  const updateRate = async (id, positionData) => {
    try {
      const response = await api.put(`/position-rates/${id}`, positionData);
      const index = positionRates.value.findIndex((rate) => rate.id === id);
      if (index !== -1) {
        positionRates.value[index] = response.data.data;
      }
      return response.data.data;
    } catch (error) {
      console.error("Error updating position:", error);
      throw error;
    }
  };

  // Delete position rate
  const deletePosition = async (id) => {
    try {
      await api.delete(`/position-rates/${id}`);
      const index = positionRates.value.findIndex((rate) => rate.id === id);
      if (index !== -1) {
        positionRates.value.splice(index, 1);
      }
    } catch (error) {
      console.error("Error deleting position:", error);
      throw error;
    }
  };

  // Bulk update employees with same position
  const bulkUpdateEmployees = async (positionRateId, newRate) => {
    try {
      const response = await api.post(
        `/position-rates/${positionRateId}/bulk-update`,
        { new_rate: newRate }
      );
      // Update the rate in our local array
      const index = positionRates.value.findIndex(
        (rate) => rate.id === positionRateId
      );
      if (index !== -1) {
        positionRates.value[index] = response.data.position_rate;
      }
      return response.data;
    } catch (error) {
      console.error("Error bulk updating employees:", error);
      throw error;
    }
  };

  // Get all rates with employee counts
  const getAllRates = () => {
    return [...positionRates.value];
  };

  // Refresh rates from server
  const refreshRates = async () => {
    loaded.value = false;
    await loadPositionRates();
  };

  return {
    positionRates,
    positionOptions,
    loading,
    loadPositionRates,
    getRate,
    getPositionRate,
    updateRate,
    addPosition,
    deletePosition,
    bulkUpdateEmployees,
    getAllRates,
    refreshRates,
  };
}
