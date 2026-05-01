import api from "./api";

export default {
  // Get all employee allowances
  async getAll(params = {}) {
    const response = await api.get("/allowances", { params });
    return response.data;
  },

  // Get single allowance
  async getById(id) {
    const response = await api.get(`/allowances/${id}`);
    return response.data;
  },

  // Create new allowance
  async create(data) {
    const response = await api.post("/allowances", data);
    return response.data;
  },

  // Update allowance
  async update(id, data) {
    const response = await api.put(`/allowances/${id}`, data);
    return response.data;
  },

  // Approve or reject allowance (Admin only)
  async updateApproval(id, action, rejectionReason = null) {
    const response = await api.post(`/allowances/${id}/approval`, {
      action,
      rejection_reason: rejectionReason,
    });
    return response.data;
  },

  // Bulk approve or reject allowances (Admin only)
  async updateBulkApproval(allowanceIds, action, rejectionReason = null) {
    const response = await api.post(`/allowances/approval/bulk`, {
      allowance_ids: allowanceIds,
      action,
      rejection_reason: rejectionReason,
    });
    return response.data;
  },

  // Approve or reject a whole request batch (Admin only)
  async updateBatchApproval(batchId, action, rejectionReason = null) {
    const response = await api.post(`/allowances/approval/batch`, {
      batch_id: batchId,
      action,
      rejection_reason: rejectionReason,
    });
    return response.data;
  },

  // Delete allowance
  async delete(id) {
    const response = await api.delete(`/allowances/${id}`);
    return response.data;
  },
};
