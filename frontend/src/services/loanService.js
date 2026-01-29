import api from "./api";

export default {
  // Get all loans with filters
  getLoans(params = {}) {
    return api.get("/loans", { params });
  },

  // Get a single loan
  getLoan(id) {
    return api.get(`/loans/${id}`);
  },

  // Create a new loan (employee request or HR create)
  createLoan(data) {
    return api.post("/loans", data);
  },

  // Update a loan
  updateLoan(id, data) {
    return api.put(`/loans/${id}`, data);
  },

  // Delete a loan
  deleteLoan(id) {
    return api.delete(`/loans/${id}`);
  },

  // Approve a loan (admin only)
  approveLoan(id, approvalNotes = null) {
    return api.post(`/loans/${id}/approve`, { approval_notes: approvalNotes });
  },

  // Reject a loan (admin only)
  rejectLoan(id, rejectionReason) {
    return api.post(`/loans/${id}/reject`, {
      rejection_reason: rejectionReason,
    });
  },

  // Record a payment for a loan
  recordPayment(id, paymentData) {
    return api.post(`/loans/${id}/payments`, paymentData);
  },

  // Get pending loans (admin approval queue)
  getPendingLoans() {
    return api.get("/loans/pending", { params: { pending_only: true } });
  },
};
