import api from './api';

export const resumeService = {
  // HR endpoints
  async uploadResume(formData) {
    const response = await api.post('/hr-resumes/upload', formData, {
      headers: {
        'Content-Type': 'multipart/form-data',
      },
    });
    return response.data;
  },

  async getMyResumes() {
    const response = await api.get('/hr-resumes/my-resumes');
    return response.data;
  },

  async getApprovedResumes() {
    const response = await api.get('/hr-resumes/approved');
    return response.data;
  },

  async deleteResume(id) {
    const response = await api.delete(`/hr-resumes/${id}`);
    return response.data;
  },

  // Admin endpoints
  async getPendingResumes() {
    const response = await api.get('/hr-resumes/pending');
    return response.data;
  },

  async getAllResumes(params) {
    const response = await api.get('/hr-resumes/all', { params });
    return response.data;
  },

  async approveResume(id, adminNotes) {
    const response = await api.post(`/hr-resumes/${id}/approve`, {
      admin_notes: adminNotes,
    });
    return response.data;
  },

  async rejectResume(id, adminNotes) {
    const response = await api.post(`/hr-resumes/${id}/reject`, {
      admin_notes: adminNotes,
    });
    return response.data;
  },

  // Common
  async downloadResume(id) {
    const response = await api.get(`/hr-resumes/${id}/download`, {
      responseType: 'blob',
    });
    return response;
  },

  getFileUrl(filePath) {
    const apiUrl = (import.meta.env.VITE_API_URL || 'http://localhost:8000/api').replace('/api', '');
    return `${apiUrl}/storage/${filePath}`;
  },
};
