import api from './api';

export const auditLogService = {
  /**
   * Get all audit logs with filtering and pagination
   */
  async getAll(params = {}) {
    const response = await api.get('/audit-logs', { params });
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
      { value: 'employees', text: 'Employees', icon: 'mdi-account-group', color: 'primary' },
      { value: 'attendance', text: 'Attendance', icon: 'mdi-calendar-check', color: 'success' },
      { value: 'leave', text: 'Leave Management', icon: 'mdi-calendar-remove', color: 'orange' },
      { value: 'deductions', text: 'Deductions', icon: 'mdi-cash-minus', color: 'error' },
      { value: 'loans', text: 'Loans', icon: 'mdi-cash-multiple', color: 'purple' },
      { value: 'payroll', text: 'Payroll', icon: 'mdi-currency-php', color: 'green' },
      { value: 'applications', text: 'Applications', icon: 'mdi-file-document', color: 'blue' },
      { value: 'user_profile', text: 'User Profile', icon: 'mdi-account-edit', color: 'indigo' },
      { value: 'documents', text: 'Documents', icon: 'mdi-file-document-multiple', color: 'cyan' },
      { value: 'biometric', text: 'Biometric', icon: 'mdi-fingerprint', color: 'teal' },
    ];
  },

  /**
   * Get available actions (for filtering)
   */
  getAvailableActions() {
    return [
      { value: 'created', text: 'Created', color: 'success' },
      { value: 'updated', text: 'Updated', color: 'info' },
      { value: 'deleted', text: 'Deleted', color: 'error' },
      { value: 'approved', text: 'Approved', color: 'green' },
      { value: 'rejected', text: 'Rejected', color: 'red' },
      { value: 'salary_changed', text: 'Salary Changed', color: 'warning' },
      { value: 'position_changed', text: 'Position Changed', color: 'orange' },
      { value: 'biometric_import', text: 'Biometric Import', color: 'purple' },
      { value: 'password_changed', text: 'Password Changed', color: 'indigo' },
      { value: 'profile_updated', text: 'Profile Updated', color: 'blue' },
      { value: '2fa_enabled', text: '2FA Enabled', color: 'teal' },
      { value: '2fa_disabled', text: '2FA Disabled', color: 'pink' },
    ];
  },

  /**
   * Format action for display
   */
  formatAction(action) {
    return action.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
  },

  /**
   * Get action color
   */
  getActionColor(action) {
    const actionMap = {
      'created': 'success',
      'updated': 'info',
      'deleted': 'error',
      'approved': 'green',
      'rejected': 'red',
      'salary_changed': 'warning',
      'position_changed': 'orange',
      'biometric_import': 'purple',
      'password_changed': 'indigo',
      'profile_updated': 'blue',
      '2fa_enabled': 'teal',
      '2fa_disabled': 'pink',
    };
    return actionMap[action] || 'grey';
  },

  /**
   * Get module icon
   */
  getModuleIcon(module) {
    const iconMap = {
      'employees': 'mdi-account-group',
      'attendance': 'mdi-calendar-check',
      'leave': 'mdi-calendar-remove',
      'deductions': 'mdi-cash-minus',
      'loans': 'mdi-cash-multiple',
      'payroll': 'mdi-currency-php',
      'applications': 'mdi-file-document',
      'user_profile': 'mdi-account-edit',
      'documents': 'mdi-file-document-multiple',
      'biometric': 'mdi-fingerprint',
    };
    return iconMap[module] || 'mdi-information';
  },

  /**
   * Export audit logs
   */
  async exportLogs(params = {}) {
    const response = await api.get('/audit-logs/export', {
      params,
      responseType: 'blob',
    });
    return response.data;
  },
};

export default auditLogService;
