import api from './api'

export default {
  // Get all meal allowances
  async getAll(params = {}) {
    const response = await api.get('/meal-allowances', { params })
    return response.data
  },

  // Get single meal allowance
  async getById(id) {
    const response = await api.get(`/meal-allowances/${id}`)
    return response.data
  },

  // Get available positions
  async getPositions() {
    const response = await api.get('/meal-allowances/positions')
    return response.data
  },

  // Get employees by position
  async getEmployeesByPosition(positionId, projectId = null) {
    const response = await api.post('/meal-allowances/employees-by-position', {
      position_id: positionId,
      project_id: projectId
    })
    return response.data
  },

  // Create new meal allowance
  async create(data) {
    const response = await api.post('/meal-allowances', data)
    return response.data
  },

  // Update meal allowance
  async update(id, data) {
    const response = await api.put(`/meal-allowances/${id}`, data)
    return response.data
  },

  // Submit for approval
  async submit(id) {
    const response = await api.post(`/meal-allowances/${id}/submit`)
    return response.data
  },

  // Approve or reject (Admin only)
  async updateApproval(id, action, approvalNotes = '', signatories = {}) {
    const response = await api.post(`/meal-allowances/${id}/approval`, {
      action,
      approval_notes: approvalNotes,
      ...signatories
    })
    return response.data
  },

  // Generate PDF
  async generatePdf(id) {
    const response = await api.post(`/meal-allowances/${id}/generate-pdf`)
    return response.data
  },

  // Download PDF
  async downloadPdf(id) {
    const response = await api.get(`/meal-allowances/${id}/download-pdf`, {
      responseType: 'blob'
    })
    return response.data
  },

  // Delete meal allowance
  async delete(id) {
    const response = await api.delete(`/meal-allowances/${id}`)
    return response.data
  }
}
