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

  // Delete allowance
  async delete(id) {
    const response = await api.delete(`/allowances/${id}`);
    return response.data;
  },
};
