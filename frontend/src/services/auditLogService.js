import api from "./api";

export const auditLogService = {
  /**
   * Get all audit logs with filtering and pagination
   */
  async getAll(params = {}, config = {}) {
    const response = await api.get("/audit-logs", { ...config, params });
    return response.data;
  },

  /**
   * Get audit logs by module
   */
  async getByModule(module, params = {}) {
    const response = await api.get(`/audit-logs/module/${module}`, { params });
    return response.data;
  },

  /**
   * Get available modules (for filtering)
   */
  getAvailableModules() {
    return [
      {
        value: "employees",
        text: "Employees",
        icon: "mdi-account-group",
        color: "primary",
      },
      {
        value: "attendance",
        text: "Attendance",
        icon: "mdi-calendar-check",
        color: "success",
      },
      {
        value: "leave",
        text: "Leave Management",
        icon: "mdi-calendar-remove",
        color: "orange",
      },
      {
        value: "deductions",
        text: "Deductions",
        icon: "mdi-cash-minus",
        color: "error",
      },
      {
        value: "loans",
        text: "Loans",
        icon: "mdi-cash-multiple",
        color: "purple",
      },
      {
        value: "payroll",
        text: "Payroll",
        icon: "mdi-currency-php",
        color: "green",
      },
      {
        value: "applications",
        text: "Applications",
        icon: "mdi-file-document",
        color: "blue",
      },
      {
        value: "user_profile",
        text: "User Profile",
        icon: "mdi-account-edit",
        color: "indigo",
      },
      {
        value: "documents",
        text: "Documents",
        icon: "mdi-file-document-multiple",
        color: "cyan",
      },
      {
        value: "biometric",
        text: "Biometric",
        icon: "mdi-fingerprint",
        color: "teal",
      },
    ];
  },

  /**
   * Get available actions (for filtering)
   */
  getAvailableActions() {
    return [
      { value: "created", text: "Created", color: "success" },
      { value: "updated", text: "Updated", color: "info" },
      { value: "deleted", text: "Deleted", color: "error" },
      { value: "create_payroll", text: "Create Payroll", color: "success" },
      { value: "update_payroll", text: "Update Payroll", color: "info" },
      {
        value: "update_payroll_item",
        text: "Update Payroll Item",
        color: "warning",
      },
      { value: "finalize_payroll", text: "Finalize Payroll", color: "green" },
      {
        value: "reprocess_payroll",
        text: "Reprocess Payroll",
        color: "warning",
      },
      { value: "delete_payroll", text: "Delete Payroll", color: "error" },
      {
        value: "payroll_punch_review",
        text: "Payroll Punch Review",
        color: "indigo",
      },
      {
        value: "payroll_overtime_attendance_view",
        text: "View Overtime Attendance",
        color: "teal",
      },
      {
        value: "payroll_overtime_employees_set",
        text: "Set Overtime-Eligible Employees",
        color: "cyan",
      },
      {
        value: "payroll_overtime_employees_updated",
        text: "Update Overtime-Eligible Employees",
        color: "cyan",
      },
      { value: "update_attendance", text: "Update Attendance", color: "blue" },
      {
        value: "update_attendance_overtime",
        text: "Update Attendance Overtime",
        color: "deep-orange",
      },
      {
        value: "manual_attendance_entry",
        text: "Manual Attendance Entry",
        color: "purple",
      },
      { value: "approved", text: "Approved", color: "green" },
      { value: "rejected", text: "Rejected", color: "red" },
      { value: "salary_changed", text: "Salary Changed", color: "warning" },
      { value: "position_changed", text: "Position Changed", color: "orange" },
      { value: "biometric_import", text: "Biometric Import", color: "purple" },
      { value: "password_changed", text: "Password Changed", color: "indigo" },
      { value: "profile_updated", text: "Profile Updated", color: "blue" },
      { value: "2fa_enabled", text: "2FA Enabled", color: "teal" },
      { value: "2fa_disabled", text: "2FA Disabled", color: "pink" },
    ];
  },

  /**
   * Format action for display
   */
  formatAction(action) {
    return action.replace(/_/g, " ").replace(/\b\w/g, (l) => l.toUpperCase());
  },

  /**
   * Get action color
   */
  getActionColor(action) {
    const actionMap = {
      created: "success",
      updated: "info",
      deleted: "error",
      create_payroll: "success",
      update_payroll: "info",
      update_payroll_item: "warning",
      finalize_payroll: "green",
      reprocess_payroll: "warning",
      delete_payroll: "error",
      payroll_punch_review: "indigo",
      payroll_overtime_attendance_view: "teal",
      payroll_overtime_employees_set: "cyan",
      payroll_overtime_employees_updated: "cyan",
      update_attendance: "blue",
      update_attendance_overtime: "deep-orange",
      manual_attendance_entry: "purple",
      approved: "green",
      rejected: "red",
      salary_changed: "warning",
      position_changed: "orange",
      biometric_import: "purple",
      password_changed: "indigo",
      profile_updated: "blue",
      "2fa_enabled": "teal",
      "2fa_disabled": "pink",
    };
    return actionMap[action] || "grey";
  },

  /**
   * Get module icon
   */
  getModuleIcon(module) {
    const iconMap = {
      employees: "mdi-account-group",
      attendance: "mdi-calendar-check",
      leave: "mdi-calendar-remove",
      deductions: "mdi-cash-minus",
      loans: "mdi-cash-multiple",
      payroll: "mdi-currency-php",
      applications: "mdi-file-document",
      user_profile: "mdi-account-edit",
      documents: "mdi-file-document-multiple",
      biometric: "mdi-fingerprint",
    };
    return iconMap[module] || "mdi-information";
  },

  /**
   * Export audit logs
   */
  async exportLogs(params = {}) {
    const response = await api.get("/audit-logs/export", {
      params,
      responseType: "blob",
    });
    return response.data;
  },
};

export default auditLogService;
