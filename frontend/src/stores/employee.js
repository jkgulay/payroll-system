import { defineStore } from "pinia";
import { ref } from "vue";
import api from "@/services/api";

export const useEmployeeStore = defineStore("employee", () => {
  // State
  const employees = ref([]);
  const currentEmployee = ref(null);
  const departments = ref([]);
  const locations = ref([]);
  const loading = ref(false);
  const pagination = ref({
    page: 1,
    perPage: 20,
    total: 0,
  });

  // Actions
  async function fetchEmployees(params = {}) {
    loading.value = true;
    try {
      const response = await api.get("/employees", { params });
      employees.value = response.data.data;
      pagination.value = {
        page: response.data.current_page,
        perPage: response.data.per_page,
        total: response.data.total,
      };
      return response.data;
    } catch (error) {
      throw error;
    } finally {
      loading.value = false;
    }
  }

  async function fetchEmployee(id) {
    loading.value = true;
    try {
      const response = await api.get(`/employees/${id}`);
      currentEmployee.value = response.data;
      return response.data;
    } catch (error) {
      throw error;
    } finally {
      loading.value = false;
    }
  }

  async function createEmployee(data) {
    loading.value = true;
    try {
      const response = await api.post("/employees", data);
      employees.value.unshift(response.data);
      return response.data;
    } catch (error) {
      throw error;
    } finally {
      loading.value = false;
    }
  }

  async function updateEmployee(id, data) {
    loading.value = true;
    try {
      const response = await api.put(`/employees/${id}`, data);
      const index = employees.value.findIndex((e) => e.id === id);
      if (index !== -1) {
        employees.value[index] = response.data;
      }
      return response.data;
    } catch (error) {
      throw error;
    } finally {
      loading.value = false;
    }
  }

  async function deleteEmployee(id) {
    loading.value = true;
    try {
      await api.delete(`/employees/${id}`);
      employees.value = employees.value.filter((e) => e.id !== id);
    } catch (error) {
      throw error;
    } finally {
      loading.value = false;
    }
  }

  async function fetchDepartments() {
    try {
      const response = await api.get("/departments");
      departments.value = response.data;
      return response.data;
    } catch (error) {
      throw error;
    }
  }

  async function fetchLocations() {
    try {
      const response = await api.get("/locations");
      locations.value = response.data;
      return response.data;
    } catch (error) {
      throw error;
    }
  }

  async function fetchEmployeeAllowances(employeeId) {
    try {
      const response = await api.get(`/employees/${employeeId}/allowances`);
      return response.data;
    } catch (error) {
      throw error;
    }
  }

  async function fetchEmployeeLoans(employeeId) {
    try {
      const response = await api.get(`/employees/${employeeId}/loans`);
      return response.data;
    } catch (error) {
      throw error;
    }
  }

  async function fetchEmployeeDeductions(employeeId) {
    try {
      const response = await api.get(`/employees/${employeeId}/deductions`);
      return response.data;
    } catch (error) {
      throw error;
    }
  }

  return {
    // State
    employees,
    currentEmployee,
    departments,
    locations,
    loading,
    pagination,
    // Actions
    fetchEmployees,
    fetchEmployee,
    createEmployee,
    updateEmployee,
    deleteEmployee,
    fetchDepartments,
    fetchLocations,
    fetchEmployeeAllowances,
    fetchEmployeeLoans,
    fetchEmployeeDeductions,
  };
});
