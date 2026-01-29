import api from "./api";

export default {
  // Get all deductions with filters
  getDeductions(params = {}) {
    return api.get("/deductions", { params });
  },

  // Get a single deduction
  getDeduction(id) {
    return api.get(`/deductions/${id}`);
  },

  // Create a new deduction
  createDeduction(data) {
    return api.post("/deductions", data);
  },

  // Bulk create deductions for multiple employees
  bulkCreateDeductions(data) {
    return api.post("/deductions/bulk", data);
  },

  // Update a deduction
  updateDeduction(id, data) {
    return api.put(`/deductions/${id}`, data);
  },

  // Delete a deduction
  deleteDeduction(id) {
    return api.delete(`/deductions/${id}`);
  },

  // Record an installment payment
  recordInstallment(id, installmentData) {
    return api.post(`/deductions/${id}/record-installment`, installmentData);
  },

  // Get list of departments
  getDepartments() {
    return api.get("/deductions/departments/list");
  },

  // Get list of positions
  getPositions() {
    return api.get("/deductions/positions/list");
  },

  // Get employees by department or position
  getEmployeesByFilter(filters) {
    return api.post("/deductions/employees/filter", filters);
  },
};
