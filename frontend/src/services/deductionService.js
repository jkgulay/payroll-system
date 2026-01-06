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
};
