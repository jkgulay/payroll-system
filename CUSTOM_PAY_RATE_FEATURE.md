# Custom Pay Rate Feature

## Overview
This feature allows administrators to set individual pay rates for employees, overriding the position-based rate. This is useful when different employees in the same position have different rates based on experience, performance, or negotiated terms.

## Features Implemented

### 1. Database Changes
- **Migration**: `2026_01_17_105406_add_custom_pay_rate_to_employees_table.php`
- **New Column**: `custom_pay_rate` (decimal 12,2) in `employees` table
- Allows NULL values (optional - if not set, position-based rate is used)

### 2. Backend Changes

#### Employee Model (`app/Models/Employee.php`)
- Added `custom_pay_rate` to `$fillable` array
- Added `custom_pay_rate` to `$casts` array as `decimal:2`
- Updated `getBasicSalary()` method with priority order:
  1. **Custom Pay Rate** (highest priority - set by admin)
  2. **Position Rate** (from position_rates table)
  3. **Basic Salary** (fallback for backward compatibility)

#### API Endpoints (`routes/api.php`)
Two new endpoints added:
```php
POST /api/employees/{employee}/update-pay-rate
POST /api/employees/{employee}/clear-custom-pay-rate
```

#### Controller Methods (`app/Http/Controllers/Api/EmployeeController.php`)
- **`updatePayRate()`**: Sets custom pay rate for an employee
  - Validates rate (required, numeric, 0-999999.99)
  - Optional reason parameter for audit trail
  - Logs change to audit_logs table
  - Returns old and new rates

- **`clearCustomPayRate()`**: Removes custom pay rate
  - Reverts to position-based rate
  - Logs the change
  - Returns old and new rates

#### Request Validation
Updated both `StoreEmployeeRequest` and `UpdateEmployeeRequest`:
- Added `custom_pay_rate` validation rule
- Nullable, numeric, min 0, max 999999.99

#### PayrollService (`app/Services/PayrollService.php`)
- Updated `calculatePayrollItem()` to use `$employee->getBasicSalary()`
- Ensures custom rates are used in payroll calculations

### 3. Frontend Changes

#### Employee List View (`frontend/src/views/employees/EmployeeListView.vue`)

**Table Column Added:**
- New "Pay Rate" column showing:
  - Effective rate (custom or position-based)
  - Badge indicating if custom rate is active

**Actions Menu:**
- Added "Update Pay Rate" option

**New Dialog:**
- **Pay Rate Update Dialog** with:
  - Current rate information display
  - Position-based rate (reference)
  - Current custom rate (if set)
  - Effective rate (what's currently used)
  - Input field for new custom rate
  - Optional reason field
  - "Clear Custom Rate" button (if custom rate exists)
  - Form validation

**New Functions:**
- `openPayRateDialog()` - Opens dialog with employee data
- `closePayRateDialog()` - Closes and resets dialog
- `updatePayRate()` - Calls API to update rate
- `clearCustomPayRate()` - Calls API to clear custom rate
- `getEmployeePositionRate()` - Gets position-based rate
- `getEmployeeEffectiveRate()` - Gets final effective rate
- `formatCurrency()` - Formats currency display

## How It Works

### Rate Priority System
The system uses a three-tier priority system for determining an employee's pay rate:

1. **Custom Pay Rate** (Highest Priority)
   - Set individually by admin
   - Overrides position-based rate
   - Persisted in `employees.custom_pay_rate`

2. **Position Rate** (Medium Priority)
   - From `position_rates` table
   - Linked via `employees.position_id`
   - Used when no custom rate is set

3. **Basic Salary** (Lowest Priority)
   - Direct value in `employees.basic_salary`
   - Fallback for backward compatibility

### Usage in Calculations

All payroll-related calculations now use `$employee->getBasicSalary()` which automatically applies the priority system:

- **Payroll Generation**: Uses effective rate for basic pay calculations
- **DTR (Daily Time Record)**: Daily rate based on effective rate
- **13th Month Pay**: Calculated from payroll items which use effective rates
- **Overtime Calculations**: Hourly rate derived from effective daily rate

## User Workflow

### Setting a Custom Pay Rate
1. Navigate to Employees list
2. Click actions menu (⋮) for employee
3. Select "Update Pay Rate"
4. Dialog shows:
   - Current position-based rate
   - Current custom rate (if any)
   - Effective rate being used
5. Enter new custom rate
6. Optionally add reason for audit trail
7. Click "Update Pay Rate"

### Clearing a Custom Pay Rate
1. Open Pay Rate dialog for employee with custom rate
2. Click "Clear Custom Rate" button
3. Confirm action
4. Rate reverts to position-based rate

### Visual Indicators
- Pay Rate column shows a green "Custom" badge for employees with custom rates
- Dialog clearly distinguishes between position rate and custom rate
- Effective rate is prominently displayed

## Audit Trail
All rate changes are logged to the `audit_logs` table with:
- User who made the change
- Employee affected
- Old rate value
- New rate value
- Reason (if provided)
- Timestamp
- IP address

## API Examples

### Update Pay Rate
```http
POST /api/employees/123/update-pay-rate
Content-Type: application/json
Authorization: Bearer {token}

{
  "custom_pay_rate": 750.00,
  "reason": "Promoted to senior level"
}
```

Response:
```json
{
  "message": "Pay rate updated successfully",
  "employee": {...},
  "old_rate": 570.00,
  "new_rate": 750.00
}
```

### Clear Custom Pay Rate
```http
POST /api/employees/123/clear-custom-pay-rate
Content-Type: application/json
Authorization: Bearer {token}

{
  "reason": "Position rate now matches performance"
}
```

Response:
```json
{
  "message": "Custom pay rate cleared successfully. Reverted to position-based rate.",
  "employee": {...},
  "old_rate": 750.00,
  "new_rate": 570.00
}
```

## Benefits

1. **Flexibility**: Set individual rates without creating new positions
2. **Accuracy**: Ensures correct rates in payroll, DTR, and 13th month calculations
3. **Audit Trail**: All changes logged with reasons
4. **User-Friendly**: Simple UI for managing rates
5. **Backward Compatible**: Existing salary fields still work
6. **Priority System**: Clear hierarchy for rate determination

## Testing Checklist

- [x] Migration runs successfully
- [x] Custom rate can be set via API
- [x] Custom rate can be cleared via API
- [x] Custom rate appears in employee list
- [x] Payroll uses custom rate when set
- [x] Payroll uses position rate when custom rate not set
- [x] Audit logs are created for changes
- [x] UI dialog validates input correctly
- [x] Badge shows correctly for custom rates
- [ ] Test payroll generation with custom rates
- [ ] Test DTR with custom rates
- [ ] Test 13th month calculation with custom rates

## Future Enhancements

1. **Rate History**: Track all historical rate changes
2. **Bulk Rate Updates**: Update rates for multiple employees
3. **Rate Effective Dates**: Set future-dated rate changes
4. **Rate Approval Workflow**: Require approval for rate changes above threshold
5. **Rate Comparison Report**: Compare actual rates vs position rates
6. **Export Rate Data**: Download employee rates for analysis
