import api from "./api";

export default {
  /**
   * Get resignation list for management table.
   */
  async getResignations(params = {}) {
    const response = await api.get("/resignations", { params });
    return response.data;
  },

  /**
   * Get resignation stats for management cards.
   */
  async getResignationStats(params = {}) {
    const response = await api.get("/resignations/stats", { params });
    return response.data;
  },

  /**
   * Get current user's latest resignation.
   */
  async getMyResignation(config = {}) {
    const response = await api.get("/resignations/my", config);
    return response.data;
  },

  /**
   * Create a new resignation request.
   */
  async createResignation(data, config = {}) {
    const response = await api.post("/resignations", data, config);
    return response.data;
  },

  /**
   * Withdraw a resignation request.
   */
  async withdrawResignation(id) {
    const response = await api.delete(`/resignations/${id}`);
    return response.data;
  },

  /**
   * Approve a pending resignation request.
   */
  async approveResignation(id, data = {}) {
    const response = await api.post(`/resignations/${id}/approve`, data);
    return response.data;
  },

  /**
   * Reject a pending resignation request.
   */
  async rejectResignation(id, data) {
    const response = await api.post(`/resignations/${id}/reject`, data);
    return response.data;
  },

  /**
   * Process final pay for approved resignation.
   */
  async processFinalPay(id, data = {}) {
    const response = await api.post(
      `/resignations/${id}/process-final-pay`,
      data,
    );
    return response.data;
  },

  /**
   * Release final pay and complete resignation.
   */
  async releaseFinalPay(id) {
    const response = await api.post(`/resignations/${id}/release-final-pay`);
    return response.data;
  },

  /**
   * Download/view a resignation attachment.
   */
  async downloadAttachment(id, attachmentIndex, config = {}) {
    const response = await api.get(
      `/resignations/${id}/attachments/${attachmentIndex}/download`,
      {
        responseType: "blob",
        skipCache: true,
        ...config,
      },
    );
    return response;
  },

  /**
   * Delete a resignation attachment.
   */
  async deleteAttachment(id, attachmentIndex) {
    const response = await api.delete(
      `/resignations/${id}/attachments/${attachmentIndex}`,
    );
    return response.data;
  },
};
