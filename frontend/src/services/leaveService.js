import api from './api';

export default {
  /**
   * Get all leaves
   */
  async getLeaves(params = {}) {
    const response = await api.get('/leaves', { params });
    return response.data;
  },

  /**
   * Get my leaves (for employee)
   */
  async getMyLeaves(params = {}) {
    const response = await api.get('/leaves/my-leaves', { params });
    return response.data;
  },

  /**
   * Get pending leaves for approval (HR)
   */
  async getPendingLeaves() {
    const response = await api.get('/leaves/pending');
    return response.data;
  },

  /**
   * Get a single leave by ID
   */
  async getLeave(id) {
    const response = await api.get(`/leaves/${id}`);
    return response.data;
  },

  /**
   * Create a new leave request
   */
  async createLeave(data) {
    const response = await api.post('/leaves', data);
    return response.data;
  },

  /**
   * Update a leave request
   */
  async updateLeave(id, data) {
    const response = await api.put(`/leaves/${id}`, data);
    return response.data;
  },

  /**
   * Delete a leave request
   */
  async deleteLeave(id) {
    const response = await api.delete(`/leaves/${id}`);
    return response.data;
  },

  /**
   * Approve a leave request
   */
  async approveLeave(id, data = {}) {
    const response = await api.post(`/leaves/${id}/approve`, data);
    return response.data;
  },

  /**
   * Reject a leave request
   */
  async rejectLeave(id, data) {
    const response = await api.post(`/leaves/${id}/reject`, data);
    return response.data;
  },

  /**
   * Get employee leave credits
   */
  async getEmployeeCredits(employeeId) {
    const response = await api.get(`/leaves/employee/${employeeId}/credits`);
    return response.data;
  },

  /**
   * Get my leave credits (for current employee)
   */
  async getMyCredits() {
    const response = await api.get('/leaves/my-credits');
    return response.data;
  },

  /**
   * Get all leave types
   */
  async getLeaveTypes() {
    const response = await api.get('/leave-types');
    return response.data;
  },

  /**
   * Create a new leave type
   */
  async createLeaveType(data) {
    const response = await api.post('/leave-types', data);
    return response.data;
  },

  /**
   * Update a leave type
   */
  async updateLeaveType(id, data) {
    const response = await api.put(`/leave-types/${id}`, data);
    return response.data;
  },

  /**
   * Delete a leave type
   */
  async deleteLeaveType(id) {
    const response = await api.delete(`/leave-types/${id}`);
    return response.data;
  }
};
