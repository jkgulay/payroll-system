import api from "./api.js";

const BASE_URL = "/attendance-modification-requests";

const moduleAccessService = {
  async getRequests(module, params = {}) {
    const response = await api.get(BASE_URL, { params: { ...params, module } });
    return response.data;
  },

  async getRequestsForModules(modules, params = {}) {
    const response = await api.get(BASE_URL, { params: { ...params, modules: modules.join(',') } });
    return response.data;
  },

  async submitRequest(module, data) {
    const response = await api.post(BASE_URL, { ...data, module });
    return response.data;
  },

  async checkAccess(module, date = null) {
    const params = { module };
    if (date) params.date = date;
    const response = await api.get(`${BASE_URL}/check-access`, { params });
    return response.data;
  },

  async getPendingCount(module = null) {
    const params = module ? { module } : {};
    const response = await api.get(`${BASE_URL}/pending-count`, { params });
    return response.data;
  },

  async getPendingCountForModules(modules) {
    const response = await api.get(`${BASE_URL}/pending-count`, { params: { modules: modules.join(',') } });
    return response.data;
  },

  async approveRequest(id, notes = null) {
    const response = await api.post(`${BASE_URL}/${id}/approve`, { notes });
    return response.data;
  },

  async rejectRequest(id, notes) {
    const response = await api.post(`${BASE_URL}/${id}/reject`, { notes });
    return response.data;
  },
};

export default moduleAccessService;
