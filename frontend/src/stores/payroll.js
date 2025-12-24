import { defineStore } from "pinia";
import { ref } from "vue";
import api from "@/services/api";

export const usePayrollStore = defineStore("payroll", () => {
  // State
  const payrolls = ref([]);
  const currentPayroll = ref(null);
  const payrollItems = ref([]);
  const loading = ref(false);
  const processing = ref(false);

  // Actions
  async function fetchPayrolls(params = {}) {
    loading.value = true;
    try {
      const response = await api.get("/payroll", { params });
      payrolls.value = response.data.data;
      return response.data;
    } catch (error) {
      throw error;
    } finally {
      loading.value = false;
    }
  }

  async function fetchPayroll(id) {
    loading.value = true;
    try {
      const response = await api.get(`/payroll/${id}`);
      currentPayroll.value = response.data;
      return response.data;
    } catch (error) {
      throw error;
    } finally {
      loading.value = false;
    }
  }

  async function createPayroll(data) {
    loading.value = true;
    try {
      const response = await api.post("/payroll", data);
      payrolls.value.unshift(response.data);
      return response.data;
    } catch (error) {
      throw error;
    } finally {
      loading.value = false;
    }
  }

  async function processPayroll(id, employeeIds = null) {
    processing.value = true;
    try {
      const response = await api.post(`/payroll/${id}/process`, {
        employee_ids: employeeIds,
      });
      currentPayroll.value = response.data.payroll;
      return response.data;
    } catch (error) {
      throw error;
    } finally {
      processing.value = false;
    }
  }

  async function checkPayroll(id) {
    loading.value = true;
    try {
      const response = await api.post(`/payroll/${id}/check`);
      currentPayroll.value = response.data.payroll;
      return response.data;
    } catch (error) {
      throw error;
    } finally {
      loading.value = false;
    }
  }

  async function recommendPayroll(id) {
    loading.value = true;
    try {
      const response = await api.post(`/payroll/${id}/recommend`);
      currentPayroll.value = response.data.payroll;
      return response.data;
    } catch (error) {
      throw error;
    } finally {
      loading.value = false;
    }
  }

  async function approvePayroll(id) {
    loading.value = true;
    try {
      const response = await api.post(`/payroll/${id}/approve`);
      currentPayroll.value = response.data.payroll;
      return response.data;
    } catch (error) {
      throw error;
    } finally {
      loading.value = false;
    }
  }

  async function markPayrollPaid(id) {
    loading.value = true;
    try {
      const response = await api.post(`/payroll/${id}/mark-paid`);
      currentPayroll.value = response.data.payroll;
      return response.data;
    } catch (error) {
      throw error;
    } finally {
      loading.value = false;
    }
  }

  async function fetchPayrollSummary(id) {
    loading.value = true;
    try {
      const response = await api.get(`/payroll/${id}/summary`);
      return response.data;
    } catch (error) {
      throw error;
    } finally {
      loading.value = false;
    }
  }

  async function fetchPayrollItems(payrollId) {
    loading.value = true;
    try {
      const response = await api.get(`/payroll/${payrollId}/items`);
      payrollItems.value = response.data;
      return response.data;
    } catch (error) {
      throw error;
    } finally {
      loading.value = false;
    }
  }

  async function deletePayroll(id) {
    loading.value = true;
    try {
      await api.delete(`/payroll/${id}`);
      payrolls.value = payrolls.value.filter((p) => p.id !== id);
    } catch (error) {
      throw error;
    } finally {
      loading.value = false;
    }
  }

  return {
    // State
    payrolls,
    currentPayroll,
    payrollItems,
    loading,
    processing,
    // Actions
    fetchPayrolls,
    fetchPayroll,
    createPayroll,
    processPayroll,
    checkPayroll,
    recommendPayroll,
    approvePayroll,
    markPayrollPaid,
    fetchPayrollSummary,
    fetchPayrollItems,
    deletePayroll,
  };
});
