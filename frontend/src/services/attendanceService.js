import api from "./api.js";

const attendanceService = {
  /**
   * Get attendance records with filters
   */
  async getAttendance(params = {}) {
    const response = await api.get("/attendance", { params });
    return response.data;
  },

  /**
   * Get single attendance record
   */
  async getAttendanceById(id) {
    const response = await api.get(`/attendance/${id}`);
    return response.data;
  },

  /**
   * Create manual attendance entry
   */
  async createAttendance(data) {
    const response = await api.post("/attendance", data);
    return response.data;
  },

  /**
   * Update attendance record
   */
  async updateAttendance(id, data) {
    const response = await api.put(`/attendance/${id}`, data);
    return response.data;
  },

  /**
   * Delete attendance record
   */
  async deleteAttendance(id) {
    const response = await api.delete(`/attendance/${id}`);
    return response.data;
  },

  /**
   * Import biometric data from file
   */
  async importBiometric(formData) {
    const response = await api.post("/attendance/import-biometric", formData, {
      headers: {
        "Content-Type": "multipart/form-data",
      },
    });
    return response.data;
  },

  /**
   * Fetch attendance directly from biometric device
   */
  async fetchFromDevice(params = {}) {
    const response = await api.post("/attendance/fetch-from-device", params);
    return response.data;
  },

  /**
   * Sync employees to biometric device
   */
  async syncEmployees() {
    const response = await api.post("/attendance/sync-employees");
    return response.data;
  },

  /**
   * Get biometric device information
   */
  async getDeviceInfo() {
    const response = await api.get("/attendance/device-info");
    return response.data;
  },

  /**
   * Approve attendance record
   */
  async approve(id, notes = null) {
    const response = await api.post(`/attendance/${id}/approve`, { notes });
    return response.data;
  },

  /**
   * Reject attendance record
   */
  async reject(id, reason) {
    const response = await api.post(`/attendance/${id}/reject`, { reason });
    return response.data;
  },

  /**
   * Get pending approvals
   */
  async getPendingApprovals(params = {}) {
    const response = await api.get("/attendance/pending-approvals", { params });
    return response.data;
  },

  /**
   * Get attendance summary for date range
   */
  async getSummary(params) {
    const response = await api.get("/attendance/summary", { params });
    return response.data;
  },

  /**
   * Get employee attendance summary
   */
  async getEmployeeSummary(employeeId) {
    const response = await api.get(
      `/attendance/employee/${employeeId}/summary`,
    );
    return response.data;
  },

  /**
   * Mark employees as absent
   */
  async markAbsent(data) {
    const response = await api.post("/attendance/mark-absent", data);
    return response.data;
  },

  /**
   * Get employees with missing attendance data for a specific date
   */
  async getMissingAttendance(params) {
    const response = await api.get("/attendance/missing", { params });
    return response.data;
  },

  /**
   * Clear device logs
   */
  async clearDeviceLogs() {
    const response = await api.post("/attendance/clear-device-logs");
    return response.data;
  },

  // ===== Attendance Modification Requests =====

  /**
   * Get modification requests
   */
  async getModificationRequests(params = {}) {
    const response = await api.get("/attendance-modification-requests", { params });
    return response.data;
  },

  /**
   * Submit a modification request for a specific date
   */
  async submitModificationRequest(data) {
    const response = await api.post("/attendance-modification-requests", data);
    return response.data;
  },

  /**
   * Check if user has access to modify attendance for a date
   */
  async checkModificationAccess(date) {
    const response = await api.get("/attendance-modification-requests/check-access", {
      params: { date },
    });
    return response.data;
  },

  /**
   * Get count of pending modification requests (admin)
   */
  async getModificationPendingCount() {
    const response = await api.get("/attendance-modification-requests/pending-count");
    return response.data;
  },

  /**
   * Approve a modification request (admin)
   */
  async approveModificationRequest(id, notes = null) {
    const response = await api.post(`/attendance-modification-requests/${id}/approve`, { notes });
    return response.data;
  },

  /**
   * Reject a modification request (admin)
   */
  async rejectModificationRequest(id, notes) {
    const response = await api.post(`/attendance-modification-requests/${id}/reject`, { notes });
    return response.data;
  },
};

export default attendanceService;
