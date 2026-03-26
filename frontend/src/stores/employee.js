import { defineStore } from "pinia";
import { ref } from "vue";
import api from "@/services/api";

export const useEmployeeStore = defineStore("employee", () => {
  const EMPLOYEE_LIST_CACHE_PREFIX = "employees:list:v1";
  const EMPLOYEE_LIST_CACHE_TTL_MS = 20000;

  function buildEmployeeListCacheKey(params = {}) {
    const sortedKeys = Object.keys(params).sort();
    const serializedParams = sortedKeys
      .map((key) => `${key}:${String(params[key] ?? "")}`)
      .join("|");
    return `${EMPLOYEE_LIST_CACHE_PREFIX}:${serializedParams}`;
  }

  function clearEmployeeListCache() {
    for (const key of Object.keys(sessionStorage)) {
      if (key.startsWith(EMPLOYEE_LIST_CACHE_PREFIX)) {
        sessionStorage.removeItem(key);
      }
    }
  }

  // State
  const employees = ref([]);
  const currentEmployee = ref(null);
  const projects = ref([]);
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
      const cacheKey = buildEmployeeListCacheKey(params);
      const cachedPayload = sessionStorage.getItem(cacheKey);

      if (cachedPayload) {
        try {
          const parsed = JSON.parse(cachedPayload);
          if (Date.now() - parsed.savedAt < EMPLOYEE_LIST_CACHE_TTL_MS) {
            employees.value = parsed.data.data;
            pagination.value = {
              page: parsed.data.current_page,
              perPage: parsed.data.per_page,
              total: parsed.data.total,
            };
            loading.value = false;
            return parsed.data;
          }
          sessionStorage.removeItem(cacheKey);
        } catch {
          sessionStorage.removeItem(cacheKey);
        }
      }

      const response = await api.get("/employees", { params, cacheTTL: 15000 });
      employees.value = response.data.data;
      pagination.value = {
        page: response.data.current_page,
        perPage: response.data.per_page,
        total: response.data.total,
      };

      sessionStorage.setItem(
        cacheKey,
        JSON.stringify({
          data: response.data,
          savedAt: Date.now(),
        }),
      );

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
      clearEmployeeListCache();
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
      clearEmployeeListCache();
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
      clearEmployeeListCache();
    } catch (error) {
      throw error;
    } finally {
      loading.value = false;
    }
  }

  async function fetchProjects() {
    try {
      const response = await api.get("/projects");
      projects.value = response.data;
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
    projects,
    locations,
    loading,
    pagination,
    // Actions
    fetchEmployees,
    fetchEmployee,
    createEmployee,
    updateEmployee,
    deleteEmployee,
    fetchProjects,
    fetchLocations,
    fetchEmployeeAllowances,
    fetchEmployeeLoans,
    fetchEmployeeDeductions,
  };
});
