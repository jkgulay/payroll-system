import api from "./api";

export default {
  // Get all allowances
  async getAll(params = {}) {
    const response = await api.get("/meal-allowances", { params });
    return response.data;
  },

  // Get single allowance
  async getById(id) {
    const response = await api.get(`/meal-allowances/${id}`);
    return response.data;
  },

  // Get available positions
  async getPositions() {
    const response = await api.get("/meal-allowances/positions");
    return response.data;
  },

  // Get employees by position
  async getEmployeesByPosition(
    positionId,
    projectId = null,
    department = null,
  ) {
    const response = await api.post("/meal-allowances/employees-by-position", {
      position_id: positionId,
      project_id: projectId,
      department: department,
    });
    return response.data;
  },

  // Search employees across all departments
  async searchEmployees(searchTerm, positionId = null) {
    const response = await api.post("/meal-allowances/search-employees", {
      search: searchTerm,
      position_id: positionId,
    });
    return response.data;
  },

  // Bulk assign employees by position with default values
  async bulkAssignByPosition(data) {
    const response = await api.post(
      "/meal-allowances/bulk-assign-by-position",
      data,
    );
    return response.data;
  },

  // Create new allowance
  async create(data) {
    const response = await api.post("/meal-allowances", data);
    return response.data;
  },

  // Update allowance
  async update(id, data) {
    const response = await api.put(`/meal-allowances/${id}`, data);
    return response.data;
  },

  // Submit for approval
  async submit(id) {
    const response = await api.post(`/meal-allowances/${id}/submit`);
    return response.data;
  },

  // Approve or reject (Admin only)
  async updateApproval(id, action, approvalNotes = "", signatories = {}) {
    const response = await api.post(`/meal-allowances/${id}/approval`, {
      action,
      approval_notes: approvalNotes,
      ...signatories,
    });
    return response.data;
  },

  // Generate PDF
  async generatePdf(id) {
    const response = await api.post(`/meal-allowances/${id}/generate-pdf`);
    return response.data;
  },

  // Download PDF
  async downloadPdf(id) {
    const response = await api.get(`/meal-allowances/${id}/download-pdf`, {
      responseType: "blob",
    });
    return response.data;
  },

  // Delete allowance
  async delete(id) {
    const response = await api.delete(`/meal-allowances/${id}`);
    return response.data;
  },
};
