/**
 * Shared constants for the payroll system
 * Ensures consistency between frontend and backend
 */

// Employment Status Constants
export const CONTRACT_TYPES = [
  { value: "regular", title: "Regular", color: "success" },
  { value: "probationary", title: "Probationary", color: "warning" },
  { value: "contractual", title: "Contractual", color: "info" },
];

export const ACTIVITY_STATUSES = [
  { value: "active", title: "Active", color: "success" },
  { value: "on_leave", title: "On Leave", color: "warning" },
  { value: "resigned", title: "Resigned", color: "error" },
  { value: "terminated", title: "Terminated", color: "error" },
  { value: "retired", title: "Retired", color: "grey" },
];

// Salary Types
export const SALARY_TYPES = [
  { value: "daily", title: "Daily" },
  { value: "monthly", title: "Monthly" },
  { value: "hourly", title: "Hourly" },
];

// Work Schedule (replaces confusing EMPLOYMENT_TYPES)
export const WORK_SCHEDULES = [
  { value: "full_time", title: "Full Time" },
  { value: "part_time", title: "Part Time" },
];

// Legacy alias for backwards compatibility
export const EMPLOYMENT_TYPES = WORK_SCHEDULES;

// Gender Options
export const GENDERS = [
  { value: "male", title: "Male" },
  { value: "female", title: "Female" },
  { value: "other", title: "Other" },
];

// Period Types
export const PERIOD_TYPES = [
  { value: "semi_monthly", title: "Semi-Monthly" },
  { value: "monthly", title: "Monthly" },
];

// Allowance Types
export const ALLOWANCE_TYPES = [
  { value: "water", title: "Water Allowance" },
  { value: "cola", title: "COLA (Cost of Living)" },
  { value: "incentive", title: "Incentive" },
  { value: "ppe", title: "PPE (Personal Protective Equipment)" },
  { value: "transportation", title: "Transportation Allowance" },
  { value: "meal", title: "Meal Allowance" },
  { value: "communication", title: "Communication Allowance" },
  { value: "housing", title: "Housing Allowance" },
  { value: "clothing", title: "Clothing Allowance" },
  { value: "medical", title: "Medical Allowance" },
  { value: "education", title: "Education Allowance" },
  { value: "performance", title: "Performance Allowance" },
  { value: "hazard", title: "Hazard Pay" },
  { value: "other", title: "Other Allowance" },
];

// Allowance Frequencies
export const ALLOWANCE_FREQUENCIES = [
  { value: "daily", title: "Daily" },
  { value: "weekly", title: "Weekly" },
  { value: "semi_monthly", title: "Semi-Monthly" },
  { value: "monthly", title: "Monthly" },
];

// Attendance Status
export const ATTENDANCE_STATUSES = [
  { value: "present", title: "Present", color: "success" },
  { value: "absent", title: "Absent", color: "error" },
  { value: "late", title: "Late", color: "warning" },
  { value: "half_day", title: "Half Day", color: "info" },
  { value: "on_leave", title: "On Leave", color: "grey" },
  { value: "holiday", title: "Holiday", color: "purple" },
  { value: "rest_day", title: "Rest Day", color: "grey" },
];

// Leave Types
export const LEAVE_TYPES = [
  { value: "sick", title: "Sick Leave" },
  { value: "vacation", title: "Vacation Leave" },
  { value: "emergency", title: "Emergency Leave" },
  { value: "maternity", title: "Maternity Leave" },
  { value: "paternity", title: "Paternity Leave" },
  { value: "bereavement", title: "Bereavement Leave" },
];

// Holiday Types
export const HOLIDAY_TYPES = [
  { value: "regular", title: "Regular Holiday" },
  { value: "special", title: "Special Non-Working Holiday" },
];

// Loan Types
export const LOAN_TYPES = [
  { value: "sss", title: "SSS Loan" },
  { value: "pag_ibig", title: "Pag-IBIG Loan" },
  { value: "company", title: "Company Loan" },
  { value: "emergency", title: "Emergency Loan" },
  { value: "salary_advance", title: "Salary Advance" },
  { value: "other", title: "Other Loan" },
];

// Loan Status
export const LOAN_STATUSES = [
  { value: "pending", title: "Pending Approval", color: "warning" },
  { value: "approved", title: "Approved", color: "info" },
  { value: "active", title: "Active", color: "success" },
  { value: "paid", title: "Paid Off", color: "grey" },
  { value: "rejected", title: "Rejected", color: "error" },
  { value: "cancelled", title: "Cancelled", color: "error" },
];

// Payment Frequencies
export const PAYMENT_FREQUENCIES = [
  { value: "monthly", title: "Monthly" },
  { value: "semi_monthly", title: "Semi-Monthly" },
];

// Deduction Types
export const DEDUCTION_TYPES = [
  { value: "ppe", title: "PPE (Personal Protective Equipment)" },
  { value: "tools", title: "Tools" },
  { value: "uniform", title: "Uniform" },
  { value: "absence", title: "Absence" },
  { value: "sss", title: "SSS" },
  { value: "philhealth", title: "PhilHealth" },
  { value: "pagibig", title: "Pag-IBIG" },
  { value: "tax", title: "Tax" },
  { value: "loan", title: "Loan" },
  { value: "other", title: "Other" },
];

// Deduction Categories
export const DEDUCTION_CATEGORIES = [
  { value: "government", title: "Government Deductions" },
  { value: "company", title: "Company Deductions" },
];

// Deduction Status
export const DEDUCTION_STATUSES = [
  { value: "active", title: "Active", color: "success" },
  { value: "completed", title: "Completed", color: "grey" },
  { value: "cancelled", title: "Cancelled", color: "error" },
];

// User Roles
export const USER_ROLES = [
  { value: "admin", title: "Administrator" },
  { value: "hr", title: "Human Resources" },
  { value: "hr", title: "HR" },
  { value: "employee", title: "Employee" },
];

// Helper functions
export function getLabelByValue(constantArray, value) {
  const item = constantArray.find((item) => item.value === value);
  return item ? item.title : value;
}

export function getColorByValue(constantArray, value) {
  const item = constantArray.find((item) => item.value === value);
  return item?.color || "grey";
}

// Minimum wage constants (can be configured per region)
export const MINIMUM_WAGES = {
  daily_metro_manila: 570,
  daily_provincial: 450,
  monthly_minimum: 12000,
};

// Working days configuration
export const WORKING_DAYS = {
  per_semi_month: 11,
  per_month: 22,
  per_year: 261,
};

export default {
  CONTRACT_TYPES,
  ACTIVITY_STATUSES,
  SALARY_TYPES,
  EMPLOYMENT_TYPES,
  GENDERS,
  PERIOD_TYPES,
  ALLOWANCE_TYPES,
  ALLOWANCE_FREQUENCIES,
  ATTENDANCE_STATUSES,
  LEAVE_TYPES,
  HOLIDAY_TYPES,
  LOAN_TYPES,
  DEDUCTION_TYPES,
  USER_ROLES,
  MINIMUM_WAGES,
  WORKING_DAYS,
  getLabelByValue,
  getColorByValue,
};
