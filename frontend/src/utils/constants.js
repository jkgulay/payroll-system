/**
 * Shared constants for the payroll system
 * Ensures consistency between frontend and backend
 */

// Employment Status Constants
export const CONTRACT_TYPES = [
  { value: 'regular', label: 'Regular', color: 'success' },
  { value: 'probationary', label: 'Probationary', color: 'warning' },
  { value: 'contractual', label: 'Contractual', color: 'info' }
]

export const ACTIVITY_STATUSES = [
  { value: 'active', label: 'Active', color: 'success' },
  { value: 'on_leave', label: 'On Leave', color: 'warning' },
  { value: 'resigned', label: 'Resigned', color: 'error' },
  { value: 'terminated', label: 'Terminated', color: 'error' },
  { value: 'retired', label: 'Retired', color: 'grey' }
]

// Salary Types
export const SALARY_TYPES = [
  { value: 'daily', label: 'Daily' },
  { value: 'monthly', label: 'Monthly' },
  { value: 'hourly', label: 'Hourly' }
]

// Employment Types
export const EMPLOYMENT_TYPES = [
  { value: 'regular', label: 'Regular' },
  { value: 'contractual', label: 'Contractual' },
  { value: 'part_time', label: 'Part Time' }
]

// Gender Options
export const GENDERS = [
  { value: 'male', label: 'Male' },
  { value: 'female', label: 'Female' },
  { value: 'other', label: 'Other' }
]

// Payroll Status
export const PAYROLL_STATUSES = [
  { value: 'draft', label: 'Draft', color: 'grey' },
  { value: 'processing', label: 'Processing', color: 'info' },
  { value: 'checked', label: 'Checked', color: 'primary' },
  { value: 'recommended', label: 'Recommended', color: 'warning' },
  { value: 'approved', label: 'Approved', color: 'success' },
  { value: 'paid', label: 'Paid', color: 'success' },
  { value: 'cancelled', label: 'Cancelled', color: 'error' }
]

// Period Types
export const PERIOD_TYPES = [
  { value: 'semi_monthly', label: 'Semi-Monthly' },
  { value: 'monthly', label: 'Monthly' }
]

// Allowance Types
export const ALLOWANCE_TYPES = [
  { value: 'transportation', label: 'Transportation Allowance' },
  { value: 'meal', label: 'Meal Allowance' },
  { value: 'housing', label: 'Housing Allowance' },
  { value: 'communication', label: 'Communication Allowance' },
  { value: 'clothing', label: 'Clothing Allowance' },
  { value: 'medical', label: 'Medical Allowance' },
  { value: 'education', label: 'Education Allowance' },
  { value: 'performance', label: 'Performance Allowance' },
  { value: 'hazard', label: 'Hazard Pay' },
  { value: 'other', label: 'Other Allowance' }
]

// Allowance Frequencies
export const ALLOWANCE_FREQUENCIES = [
  { value: 'daily', label: 'Daily' },
  { value: 'semi_monthly', label: 'Semi-Monthly' },
  { value: 'monthly', label: 'Monthly' }
]

// Attendance Status
export const ATTENDANCE_STATUSES = [
  { value: 'present', label: 'Present', color: 'success' },
  { value: 'absent', label: 'Absent', color: 'error' },
  { value: 'late', label: 'Late', color: 'warning' },
  { value: 'half_day', label: 'Half Day', color: 'info' },
  { value: 'on_leave', label: 'On Leave', color: 'grey' },
  { value: 'holiday', label: 'Holiday', color: 'purple' },
  { value: 'rest_day', label: 'Rest Day', color: 'grey' }
]

// Leave Types
export const LEAVE_TYPES = [
  { value: 'sick', label: 'Sick Leave' },
  { value: 'vacation', label: 'Vacation Leave' },
  { value: 'emergency', label: 'Emergency Leave' },
  { value: 'maternity', label: 'Maternity Leave' },
  { value: 'paternity', label: 'Paternity Leave' },
  { value: 'bereavement', label: 'Bereavement Leave' }
]

// Holiday Types
export const HOLIDAY_TYPES = [
  { value: 'regular', label: 'Regular Holiday' },
  { value: 'special', label: 'Special Non-Working Holiday' }
]

// Loan Types
export const LOAN_TYPES = [
  { value: 'sss', label: 'SSS Loan' },
  { value: 'pagibig', label: 'Pag-IBIG Loan' },
  { value: 'company', label: 'Company Loan' },
  { value: 'emergency', label: 'Emergency Loan' },
  { value: 'other', label: 'Other Loan' }
]

// Deduction Types
export const DEDUCTION_TYPES = [
  { value: 'government', label: 'Government' },
  { value: 'loan', label: 'Loan' },
  { value: 'tax', label: 'Tax' },
  { value: 'other', label: 'Other' }
]

// User Roles
export const USER_ROLES = [
  { value: 'admin', label: 'Administrator' },
  { value: 'accountant', label: 'Accountant' },
  { value: 'hr', label: 'HR' },
  { value: 'employee', label: 'Employee' }
]

// Helper functions
export function getLabelByValue(constantArray, value) {
  const item = constantArray.find(item => item.value === value)
  return item ? item.label : value
}

export function getColorByValue(constantArray, value) {
  const item = constantArray.find(item => item.value === value)
  return item?.color || 'grey'
}

// Minimum wage constants (can be configured per region)
export const MINIMUM_WAGES = {
  daily_metro_manila: 570,
  daily_provincial: 450,
  monthly_minimum: 12000
}

// Working days configuration
export const WORKING_DAYS = {
  per_semi_month: 11,
  per_month: 22,
  per_year: 261
}

export default {
  CONTRACT_TYPES,
  ACTIVITY_STATUSES,
  SALARY_TYPES,
  EMPLOYMENT_TYPES,
  GENDERS,
  PAYROLL_STATUSES,
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
  getColorByValue
}
