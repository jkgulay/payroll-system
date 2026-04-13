import api from "./api";

const MODULE_METADATA = {
  employees: {
    text: "Employees",
    icon: "mdi-account-group",
    color: "primary",
  },
  attendance: {
    text: "Attendance",
    icon: "mdi-calendar-check",
    color: "success",
  },
  payroll: {
    text: "Payroll",
    icon: "mdi-currency-php",
    color: "green",
  },
  leave: {
    text: "Leave Management",
    icon: "mdi-calendar-remove",
    color: "orange",
  },
  leaves: {
    text: "Leave Management",
    icon: "mdi-calendar-remove",
    color: "orange",
  },
  deductions: {
    text: "Deductions",
    icon: "mdi-cash-minus",
    color: "error",
  },
  allowances: {
    text: "Allowances",
    icon: "mdi-cash-plus",
    color: "light-green",
  },
  bonuses: {
    text: "Bonuses",
    icon: "mdi-gift-outline",
    color: "pink",
  },
  loans: {
    text: "Loans",
    icon: "mdi-cash-multiple",
    color: "purple",
  },
  applications: {
    text: "Applications",
    icon: "mdi-file-document",
    color: "blue",
  },
  documents: {
    text: "Documents",
    icon: "mdi-file-document-multiple",
    color: "cyan",
  },
  biometric: {
    text: "Biometric",
    icon: "mdi-fingerprint",
    color: "teal",
  },
  user_profile: {
    text: "User Profile",
    icon: "mdi-account-edit",
    color: "indigo",
  },
  user_management: {
    text: "User Management",
    icon: "mdi-account-cog",
    color: "indigo",
  },
  projects: {
    text: "Projects",
    icon: "mdi-office-building-marker",
    color: "blue-grey",
  },
  government_rates: {
    text: "Government Rates",
    icon: "mdi-percent-circle-outline",
    color: "blue-grey",
  },
  salary_adjustments: {
    text: "Salary Adjustments",
    icon: "mdi-cash-edit",
    color: "amber-darken-2",
  },
  position_rates: {
    text: "Position Rates",
    icon: "mdi-briefcase-account-outline",
    color: "cyan-darken-1",
  },
  hr_resume: {
    text: "HR Resume Review",
    icon: "mdi-file-account-outline",
    color: "deep-purple",
  },
  settings: {
    text: "Settings",
    icon: "mdi-cog-outline",
    color: "grey-darken-1",
  },
  security: {
    text: "Security",
    icon: "mdi-shield-lock-outline",
    color: "deep-orange",
  },
  module_access_requests: {
    text: "Access Requests",
    icon: "mdi-lock-check-outline",
    color: "amber",
  },
};

const ACTION_METADATA = {
  created: { text: "Created", color: "success" },
  updated: { text: "Updated", color: "info" },
  deleted: { text: "Deleted", color: "error" },
  approved: { text: "Approved", color: "green" },
  rejected: { text: "Rejected", color: "red" },
  create_payroll: { text: "Created Payroll Run", color: "success" },
  update_payroll: { text: "Updated Payroll Run", color: "info" },
  update_payroll_item: {
    text: "Updated Employee Payroll Item",
    color: "warning",
  },
  finalize_payroll: { text: "Finalized Payroll Run", color: "green" },
  reprocess_payroll: {
    text: "Processed Payroll Recalculation",
    color: "warning",
  },
  delete_payroll: { text: "Deleted Payroll Run", color: "error" },
  payroll_punch_review: { text: "Reviewed Payroll Punches", color: "indigo" },
  payroll_overtime_attendance_view: {
    text: "Viewed Overtime Attendance",
    color: "teal",
  },
  payroll_overtime_employees_set: {
    text: "Set Overtime-Eligible Employees",
    color: "cyan",
  },
  payroll_overtime_employees_updated: {
    text: "Updated Overtime-Eligible Employees",
    color: "cyan",
  },
  update_attendance: { text: "Updated Attendance", color: "blue" },
  update_attendance_overtime: {
    text: "Updated Attendance Overtime",
    color: "deep-orange",
  },
  manual_attendance_entry: {
    text: "Created Manual Attendance Record",
    color: "purple",
  },
  salary_changed: { text: "Changed Salary", color: "warning" },
  position_changed: { text: "Changed Position", color: "orange" },
  biometric_import: { text: "Imported Biometric Data", color: "purple" },
  password_changed: { text: "Changed Password", color: "indigo" },
  profile_updated: { text: "Updated Profile", color: "blue" },
  "2fa_enabled": { text: "Enabled 2FA", color: "teal" },
  "2fa_disabled": { text: "Disabled 2FA", color: "pink" },
  create_user: { text: "Created User Account", color: "success" },
  update_user: { text: "Updated User Account", color: "info" },
  delete_user: { text: "Deleted User Account", color: "error" },
  toggle_user_status: { text: "Updated User Account Status", color: "warning" },
  reset_password: { text: "Updated User Password", color: "warning" },
  approve_attendance: { text: "Approved Attendance Record", color: "green" },
  reject_attendance: { text: "Rejected Attendance Record", color: "red" },
  mark_absent: { text: "Marked as Absent", color: "red-darken-1" },
  recalculate_date_range: { text: "Recalculated Attendance", color: "indigo" },
  fetch_from_device: { text: "Fetched from Device", color: "teal" },
  sync_employees_to_device: {
    text: "Synced Employees to Device",
    color: "teal",
  },
  clear_device_logs: { text: "Cleared Device Logs", color: "deep-orange" },
  create_allowance: { text: "Created Allowance", color: "success" },
  update_allowance: { text: "Updated Allowance", color: "info" },
  delete_allowance: { text: "Deleted Allowance", color: "error" },
  create_bonus: { text: "Created Bonus", color: "success" },
  update_bonus: { text: "Updated Bonus", color: "info" },
  delete_bonus: { text: "Deleted Bonus", color: "error" },
  create_adjustment: { text: "Created Adjustment", color: "success" },
  update_adjustment: { text: "Updated Adjustment", color: "info" },
  delete_adjustment: { text: "Deleted Adjustment", color: "error" },
  create_rate: { text: "Created Rate", color: "success" },
  update_rate: { text: "Updated Rate", color: "info" },
  delete_rate: { text: "Deleted Rate", color: "error" },
  bulk_delete_rates: { text: "Bulk Deleted Rates", color: "error" },
  create_position_rate: { text: "Created Position Rate", color: "success" },
  update_position_rate: { text: "Updated Position Rate", color: "info" },
  delete_position_rate: { text: "Deleted Position Rate", color: "error" },
  create_project: { text: "Created Project", color: "success" },
  update_project: { text: "Updated Project", color: "info" },
  delete_project: { text: "Deleted Project", color: "error" },
  create: { text: "Created Record", color: "success" },
  update: { text: "Updated Record", color: "info" },
  delete: { text: "Deleted Record", color: "error" },
  approve: { text: "Approved Request", color: "green" },
  reject: { text: "Rejected Request", color: "red" },
  payment: { text: "Processed Payment", color: "success" },
  loan_payment: { text: "Processed Loan Payment", color: "success" },
  installment: { text: "Processed Installment Payment", color: "success" },
  refund: { text: "Processed Refund", color: "blue" },
  withdraw: { text: "Processed Withdrawal", color: "orange" },
  bulk_create: { text: "Bulk Created Records", color: "success" },
  staff_import: { text: "Imported Staff Data", color: "teal" },
  punch_record_import: { text: "Imported Punch Records", color: "teal" },
  avatar_uploaded: { text: "Uploaded Avatar", color: "indigo" },
  avatar_removed: { text: "Removed Avatar", color: "orange" },
  resume_uploaded: { text: "Uploaded Resume", color: "success" },
  resume_deleted: { text: "Deleted Resume", color: "error" },
  resume_approved: { text: "Approved Resume", color: "green" },
  resume_rejected: { text: "Rejected Resume", color: "red" },
  pay_rate_update: { text: "Updated Pay Rate", color: "info" },
  pay_rate_clear: { text: "Cleared Custom Pay Rate", color: "warning" },
};

function toTitleCaseFromSnake(value) {
  return String(value || "")
    .replace(/_/g, " ")
    .replace(/\b\w/g, (char) => char.toUpperCase())
    .trim();
}

function mergeDynamicOptions(baseOptions, dynamicValues = [], normalizeOption) {
  const merged = new Map(baseOptions.map((option) => [option.value, option]));

  for (const value of dynamicValues) {
    if (!value || merged.has(value)) continue;
    merged.set(value, normalizeOption(value));
  }

  return Array.from(merged.values()).sort((a, b) =>
    String(a.text).localeCompare(String(b.text), undefined, {
      sensitivity: "base",
      numeric: true,
    }),
  );
}

function normalizeModuleOption(value) {
  const metadata = MODULE_METADATA[value];
  return {
    value,
    text: metadata?.text || toTitleCaseFromSnake(value),
    icon: metadata?.icon || "mdi-shield-outline",
    color: metadata?.color || "grey-darken-1",
  };
}

function normalizeActionOption(value) {
  const metadata = ACTION_METADATA[value];
  return {
    value,
    text: metadata?.text || toTitleCaseFromSnake(value),
    color: metadata?.color || "grey",
  };
}

function parseLogValues(values) {
  if (values && typeof values === "object" && !Array.isArray(values)) {
    return values;
  }

  if (typeof values === "string") {
    try {
      const parsed = JSON.parse(values);
      if (parsed && typeof parsed === "object" && !Array.isArray(parsed)) {
        return parsed;
      }
    } catch {
      return {};
    }
  }

  return {};
}

function toEntityLabel(value) {
  return toTitleCaseFromSnake(value).toLowerCase();
}

const SUMMARY_VERBS = Object.freeze({
  CREATED: "Created",
  UPDATED: "Updated",
  PROCESSED: "Processed",
  APPROVED: "Approved",
  REJECTED: "Rejected",
  IMPORTED: "Imported",
  FINALIZED: "Finalized",
});

function summaryLine(verbKey, subject) {
  const verb = SUMMARY_VERBS[verbKey] || SUMMARY_VERBS.PROCESSED;
  const normalized = String(subject || "")
    .trim()
    .replace(/\.$/, "");
  return normalized ? `${verb} ${normalized}.` : `${verb}.`;
}

function summarizeByPrefix(action, prefix, verbKey, suffix = "record") {
  if (!action.startsWith(prefix)) return "";
  const entity = action.slice(prefix.length);
  if (!entity) return "";
  return summaryLine(verbKey, `${toEntityLabel(entity)} ${suffix}`);
}

function summarizeGenericAction(action, module) {
  const moduleLabel = (
    MODULE_METADATA[module]?.text || toTitleCaseFromSnake(module)
  ).toLowerCase();

  if (action === "create")
    return summaryLine("CREATED", `a ${moduleLabel} record`);
  if (action === "update")
    return summaryLine("UPDATED", `a ${moduleLabel} record`);
  if (action === "delete")
    return summaryLine("PROCESSED", `${moduleLabel} record deletion`);
  if (action === "approve")
    return summaryLine("APPROVED", `a ${moduleLabel} request`);
  if (action === "reject")
    return summaryLine("REJECTED", `a ${moduleLabel} request`);
  return "";
}

function summarizeSpecialAction(action, module, newValues) {
  switch (action) {
    case "create_payroll": {
      const periodName = newValues.period_name;
      if (periodName) {
        return summaryLine("CREATED", `payroll run ${periodName}`);
      }
      return summaryLine("CREATED", "a payroll run");
    }
    case "update_payroll":
      return summaryLine("UPDATED", "payroll run settings");
    case "update_payroll_item": {
      return summaryLine("UPDATED", "employee payroll item");
    }
    case "finalize_payroll":
      return summaryLine("FINALIZED", "payroll run for release");
    case "reprocess_payroll":
      return summaryLine(
        "PROCESSED",
        "payroll recalculation from attendance records",
      );
    case "delete_payroll":
      return summaryLine("PROCESSED", "payroll run deletion");
    case "payroll_punch_review":
      return summaryLine(
        "PROCESSED",
        "payroll punch review before payroll processing",
      );
    case "payroll_overtime_attendance_view":
      return summaryLine("PROCESSED", "overtime attendance review for payroll");
    case "payroll_overtime_employees_set":
      return summaryLine(
        "UPDATED",
        "overtime-eligible employee list for payroll",
      );
    case "payroll_overtime_employees_updated":
      return summaryLine(
        "UPDATED",
        "overtime-eligible employee selection for payroll",
      );
    case "manual_attendance_entry":
      return summaryLine("CREATED", "manual attendance record");
    case "approve_attendance":
      return summaryLine("APPROVED", "attendance record");
    case "reject_attendance":
      return summaryLine("REJECTED", "attendance record");
    case "recalculate_date_range":
      return summaryLine(
        "PROCESSED",
        "attendance recalculation for selected date range",
      );
    case "fetch_from_device":
      return summaryLine("IMPORTED", "attendance logs from biometric device");
    case "sync_employees_to_device":
      return summaryLine("PROCESSED", "employee sync to biometric device");
    case "clear_device_logs":
      return summaryLine("PROCESSED", "biometric device log cleanup");
    case "salary_changed":
      return summaryLine("UPDATED", "employee salary settings");
    case "position_changed":
      return summaryLine("UPDATED", "employee position assignment");
    case "toggle_user_status":
      return summaryLine("UPDATED", "user account active status");
    case "reset_password":
    case "password_changed":
      return summaryLine("UPDATED", "user password");
    case "2fa_enabled":
      return summaryLine(
        "UPDATED",
        "account two-factor authentication status (enabled)",
      );
    case "2fa_disabled":
      return summaryLine(
        "UPDATED",
        "account two-factor authentication status (disabled)",
      );
    case "profile_updated":
      return summaryLine("UPDATED", "account profile information");
    case "loan_payment":
    case "payment":
      return module === "loans"
        ? summaryLine("PROCESSED", "loan payment")
        : summaryLine("PROCESSED", "payment");
    default:
      return "";
  }
}

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
  getAvailableModules(dynamicValues = []) {
    const baseOptions = Object.keys(MODULE_METADATA).map((value) =>
      normalizeModuleOption(value),
    );

    return mergeDynamicOptions(
      baseOptions,
      dynamicValues,
      normalizeModuleOption,
    );
  },

  /**
   * Get available actions (for filtering)
   */
  getAvailableActions(dynamicValues = []) {
    const baseOptions = Object.keys(ACTION_METADATA).map((value) =>
      normalizeActionOption(value),
    );

    return mergeDynamicOptions(
      baseOptions,
      dynamicValues,
      normalizeActionOption,
    );
  },

  /**
   * Format action for display
   */
  formatAction(action) {
    if (!action) return "Unknown Activity";
    return ACTION_METADATA[action]?.text || toTitleCaseFromSnake(action);
  },

  /**
   * Format module for display
   */
  formatModule(module) {
    if (!module) return "General";
    return MODULE_METADATA[module]?.text || toTitleCaseFromSnake(module);
  },

  /**
   * Get action color
   */
  getActionColor(action) {
    return ACTION_METADATA[action]?.color || "grey";
  },

  /**
   * Get module icon
   */
  getModuleIcon(module) {
    return MODULE_METADATA[module]?.icon || "mdi-information";
  },

  /**
   * Build a human-friendly summary for admins/non-technical users.
   */
  summarizeActivity(log) {
    const action = String(log?.action || "").trim();
    const module = String(log?.module || "").trim();
    const description = String(log?.description || "").trim();
    const newValues = parseLogValues(log?.new_values);

    if (!action) {
      return description || "Performed a system activity.";
    }

    const specialSummary = summarizeSpecialAction(action, module, newValues);
    if (specialSummary) {
      return specialSummary;
    }

    const genericSummary = summarizeGenericAction(action, module);
    if (genericSummary) {
      return genericSummary;
    }

    const prefixedSummary =
      summarizeByPrefix(action, "create_", "CREATED") ||
      summarizeByPrefix(action, "update_", "UPDATED") ||
      summarizeByPrefix(action, "delete_", "PROCESSED", "record deletion") ||
      summarizeByPrefix(action, "approve_", "APPROVED", "request") ||
      summarizeByPrefix(action, "reject_", "REJECTED", "request") ||
      summarizeByPrefix(action, "import_", "IMPORTED") ||
      summarizeByPrefix(action, "finalize_", "FINALIZED");

    if (prefixedSummary) {
      return prefixedSummary;
    }

    if (description) {
      return summaryLine("PROCESSED", `activity details: ${description}`);
    }

    const moduleText = module
      ? this.formatModule(module).toLowerCase()
      : "the system";
    return summaryLine(
      "PROCESSED",
      `${this.formatAction(action).toLowerCase()} in ${moduleText}`,
    );
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
