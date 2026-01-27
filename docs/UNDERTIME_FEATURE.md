# Undertime Feature Implementation

## Overview
Implemented department-specific undertime tracking and salary deduction for employees in certain departments with strict 8:00 AM time-in requirements.

## Affected Departments
The following departments have strict undertime tracking enabled:
1. **Admin Resign**
2. **Sur admin**
3. **Weekly Admin (mix)**
4. **ENGINEER SA SITE**
5. **Giovanni Construction and Power On Enterprise Co**

## Undertime Rules

### Time-In Requirements
- **Standard Time-In**: 8:00 AM
- **Grace Period**: 3 minutes (8:00 AM - 8:03 AM)
- **Late Threshold**: Beyond 8:03 AM
- **Half-Day Threshold**: 9:01 AM or later

### Calculation Logic

#### 1. Normal Late (8:04 AM - 9:00 AM)
- **Undertime Hours**: Minutes late / 60
- **Example**: Time-in at 8:15 AM
  - Minutes late: 15 - 3 = 12 minutes
  - Undertime hours: 12 / 60 = 0.2 hours
  - Deduction: (Rate / 8) × 0.2

#### 2. Half-Day (9:01 AM or later)
- **Status**: Automatically marked as "half_day"
- **Undertime Hours**: 4 hours (half of 8-hour workday)
- **Deduction**: (Rate / 8) × 4 = Rate / 2

### Deduction Formula
```
Undertime Deduction = (Daily Rate / 8) × Undertime Hours
```

Where:
- **Daily Rate**: Employee's basic salary/rate
- **8**: Standard working hours per day
- **Undertime Hours**: Calculated based on minutes late

## Implementation Details

### Files Modified

#### 1. Attendance Model (`app/Models/Attendance.php`)
- **Added Methods**:
  - `isUndertimeDepartment()`: Checks if employee belongs to undertime-tracked department
  - `calculateUndertimeHours()`: Calculates undertime hours with department-specific logic
  
- **Updated Methods**:
  - `calculateHours()`: Now uses department-specific undertime calculation

#### 2. PayrollItem Model (`app/Models/PayrollItem.php`)
- **Added Fields**:
  - `undertime_hours`: Tracks total undertime hours
  - `undertime_deduction`: Stores calculated deduction amount

#### 3. PayrollService (`app/Services/PayrollService.php`)
- **Updated Methods**:
  - `calculatePayrollItem()`: Now calculates and applies undertime deductions
  
- **Calculation Flow**:
  1. Accumulates undertime hours from all attendance records
  2. Calculates undertime deduction: `(rate / 8) × total_undertime_hours`
  3. Deducts from gross pay before final calculations

#### 4. Database Migration
- **File**: `2026_01_27_000001_add_undertime_deduction_to_payroll_items.php`
- **Changes**:
  - Added `undertime_hours` column (decimal 8,2)
  - Added `undertime_deduction` column (decimal 12,2)

## Examples

### Example 1: 15 Minutes Late
```
Employee: John Doe
Department: Admin Resign
Daily Rate: ₱800
Time In: 8:15 AM

Calculation:
- Grace Period: 3 minutes
- Minutes Late: 15 - 3 = 12 minutes
- Undertime Hours: 12 / 60 = 0.2 hours
- Hourly Rate: ₱800 / 8 = ₱100
- Undertime Deduction: ₱100 × 0.2 = ₱20
```

### Example 2: Half-Day
```
Employee: Jane Smith
Department: ENGINEER SA SITE
Daily Rate: ₱1,000
Time In: 9:30 AM

Calculation:
- Status: Marked as "half_day"
- Undertime Hours: 4 hours
- Hourly Rate: ₱1,000 / 8 = ₱125
- Undertime Deduction: ₱125 × 4 = ₱500
```

### Example 3: Within Grace Period
```
Employee: Bob Johnson
Department: Sur admin
Daily Rate: ₱750
Time In: 8:02 AM

Calculation:
- Within grace period (8:00 - 8:03)
- Undertime Hours: 0
- Undertime Deduction: ₱0
```

## How It Works

### Attendance Processing
1. When employee clocks in, the system checks:
   - Is the employee in an undertime-tracked department?
   - What time did they clock in?
   
2. If in undertime department:
   - Calculate minutes late beyond grace period
   - If ≥ 9:01 AM: Mark as half-day with 4 hours undertime
   - Otherwise: Calculate undertime hours from minutes late
   
3. Undertime hours are stored in the attendance record

### Payroll Processing
1. During payroll calculation:
   - Sum all undertime hours from attendance records
   - Calculate deduction: `(rate / 8) × total_undertime_hours`
   - Deduct from gross pay
   
2. Store values in payroll item:
   - `undertime_hours`: Total hours
   - `undertime_deduction`: Total deduction amount

## Benefits
1. **Accurate Tracking**: Precise calculation of undertime to the minute
2. **Fair Deductions**: Proportional salary deductions based on actual time missed
3. **Department-Specific**: Only applies to departments requiring strict punctuality
4. **Automated**: No manual calculation needed
5. **Transparent**: Clear documentation of undertime hours and deductions in payroll

## Notes
- Other departments continue to use the standard undertime calculation
- Grace period is 3 minutes (configurable in code)
- Half-day threshold is 9:01 AM (configurable in code)
- All calculations are automatically performed when attendance is saved
- Deductions are applied during payroll processing

## Testing Recommendations
1. Test with employees from undertime-tracked departments
2. Test with employees from other departments (should use standard logic)
3. Test edge cases:
   - Exactly 8:03 AM (should have no undertime)
   - Exactly 9:01 AM (should be half-day)
   - Multiple late arrivals in one payroll period
4. Verify payroll calculations include correct deductions
5. Check that payslips show undertime hours and deductions
