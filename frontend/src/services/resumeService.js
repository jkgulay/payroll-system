import api from './api';

export const resumeService = {
  // Accountant endpoints
  async uploadResume(formData) {
    const response = await api.post('/accountant-resumes/upload', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data;
  },

  async getMyResumes() {
    const response = await api.get('/accountant-resumes/my-resumes');
    return response.data;
  },

  async getApprovedResumes() {
    const response = await api.get('/accountant-resumes/approved');
    return response.data;
  },

  async deleteResume(id) {
    const response = await api.delete(`/accountant-resumes/${id}`);
    return response.data;
  },

  // Admin endpoints
  async getPendingResumes() {
    const response = await api.get('/accountant-resumes/pending');
    return response.data;
  },

  async getAllResumes(params) {
    const response = await api.get('/accountant-resumes/all', { params });
    return response.data;
  },

  async approveResume(id, adminNotes) {
    const response = await api.post(`/accountant-resumes/${id}/approve`, {
      admin_notes: adminNotes,
    });
    return response.data;
  },

  async rejectResume(id, adminNotes) {
    const response = await api.post(`/accountant-resumes/${id}/reject`, {
      admin_notes: adminNotes,
    });
    return response.data;
  },

  // Common
  async downloadResume(id) {
    const response = await api.get(`/accountant-resumes/${id}/download`, {
      responseType: 'blob',
    });
    return response;
  },

  getFileUrl(filePath) {
    return `${import.meta.env.VITE_API_URL || 'http://localhost:8000'}/storage/${filePath}`;
  },
};
