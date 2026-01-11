# Database Schema Reference for AI Chatbot

## Complete Database Relationships

### Core Tables

#### 1. **employees** (Main employee table)
```sql
Key Columns:
- id (PK)
- user_id (FK to users) - nullable, unique
- employee_number (unique)
- first_name, last_name, middle_name
- project_id (FK to projects) - REQUIRED
- worker_address (text) - employee address
- position (VARCHAR) - legacy, being phased out
- position_id (FK to position_rates) - nullable, new standard
- employment_type: 'regular', 'contractual', 'part_time'
- employment_status: 'regular', 'probationary', 'contractual', 'active', 'resigned', 'terminated', 'retired'
- date_hired, date_regularized, date_separated
- basic_salary (DECIMAL) - daily rate for daily-paid
- salary_type: 'daily', 'monthly', 'hourly'
- sss_number, philhealth_number, pagibig_number, tin_number
- is_active (boolean)
- deleted_at (soft deletes)

Relationships:
- belongsTo: project, positionRate
- hasMany: attendance, payrollItems, allowances, bonuses, deductions, loans
- hasOne: governmentInfo

Important Notes:
- position accessor handles both position_id (relationship) and legacy position column
- project_id is required (not nullable)
- Use $employee->position to get position name (auto-loads from positionRate if position_id exists)
```

#### 2. **projects** (Formerly departments)
```sql
Key Columns:
- id (PK)
- code (unique)
- name
- description
- head_employee_id (FK to employees) - nullable
- is_active (boolean)
- deleted_at (soft deletes)

Relationships:
- hasMany: employees

Important Notes:
- This replaced the old "departments" concept
- Tracks construction projects/sites
```

#### 3. **position_rates** (Job positions with rates)
```sql
Key Columns:
- id (PK)
- code (unique) - added in migration 2026_01_10
- position_name (unique)
- daily_rate (DECIMAL)
- category: 'skilled', 'semi-skilled', 'technical', 'support'
- is_active (boolean)
- deleted_at (soft deletes)

Relationships:
- hasMany: employees

Default Positions Include:
- Skilled: Carpenter, Mason, Plumber, Electrician, Welder, Painter, Steel Worker, Heavy Equipment Operator
- Semi-Skilled: Construction Worker, Helper, Laborer, Rigger, Scaffolder
- Technical: Foreman, Site Engineer, Project Engineer, Safety Officer, QC Inspector, Surveyor
- Support: Warehouse Keeper, Timekeeper, Security Guard, Driver
```

#### 4. **attendance**
```sql
Key Columns:
- id (PK)
- employee_id (FK to employees)
- attendance_date (DATE) - NOT "date"!
- time_in, time_out, break_start, break_end (TIME)
- regular_hours, overtime_hours, undertime_hours, late_hours, night_differential_hours (DECIMAL)
- status: 'present', 'absent', 'leave', 'holiday', 'rest_day'
- is_holiday (boolean)
- holiday_type: 'regular', 'special_non_working'
- is_manual_entry (boolean)
- is_edited (boolean)
- approval_status: 'pending', 'approved', 'rejected'
- deleted_at (soft deletes)

Relationships:
- belongsTo: employee

CRITICAL:
- Column name is attendance_date NOT date
- Always use whereDate('attendance_date', ...) not whereDate('date', ...)
```

#### 5. **payroll** (Summary/header table)
```sql
Key Columns:
- id (PK)
- payroll_number (unique)
- period_type: 'semi_monthly', 'monthly'
- period_start, period_end, payment_date (DATE)
- pay_period_number (1 or 2)
- month, year (INTEGER)
- status: 'draft', 'processing', 'checked', 'recommended', 'approved', 'paid', 'cancelled'
- total_gross_pay, total_deductions, total_net_pay (DECIMAL) - aggregates
- prepared_by, checked_by, recommended_by, approved_by, paid_by (FK to users)
- deleted_at (soft deletes)

Relationships:
- hasMany: payrollItems

CRITICAL:
- This table does NOT have employee_id
- This table does NOT have overtime columns
- This table does NOT have government contribution columns
- For employee-specific data, JOIN with payroll_items
```

#### 6. **payroll_items** (Detailed per-employee records)
```sql
Key Columns:
- id (PK)
- payroll_id (FK to payroll)
- employee_id (FK to employees)
- basic_rate, days_worked, basic_pay (DECIMAL)
- overtime_hours, overtime_pay (DECIMAL)
- holiday_pay, night_differential (DECIMAL)
- adjustments (DECIMAL)
- water_allowance, cola, other_allowances, total_allowances (DECIMAL)
- total_bonuses (DECIMAL)
- gross_pay (DECIMAL)
- sss_contribution, philhealth_contribution, pagibig_contribution, withholding_tax (DECIMAL)
- total_other_deductions, total_loan_deductions (DECIMAL)
- total_deductions, net_pay (DECIMAL)
- payslip_generated (boolean)

Relationships:
- belongsTo: payroll, employee
- hasMany: payrollItemDetails

CRITICAL:
- employee_id is HERE, not in payroll table
- All per-employee payroll data is in THIS table
- To count employees in a payroll: COUNT(DISTINCT payroll_items.employee_id)
- To sum overtime: SUM(payroll_items.overtime_pay)
- To get contributions: Use payroll_items columns, not payroll columns
```

#### 7. **employee_leaves** (Leave requests)
```sql
Key Columns:
- id (PK)
- employee_id (FK to employees)
- leave_type_id (FK to leave_types)
- leave_date_from, leave_date_to (DATE)
- number_of_days (DECIMAL)
- reason (TEXT)
- status: 'pending', 'approved', 'rejected', 'cancelled'
- approved_by (FK to users)
- deleted_at (soft deletes)

Relationships:
- belongsTo: employee, leaveType, approvedByUser

Important Notes:
- Use leaveType relationship, not leave_type
- Has eager loadable relationships
```

#### 8. **employee_allowances**
```sql
Key Columns:
- id (PK)
- employee_id (FK to employees)
- allowance_type: 'water', 'cola', 'transportation', 'meal', etc.
- allowance_name
- amount (DECIMAL)
- frequency: 'daily', 'weekly', 'semi-monthly', 'monthly'
- effective_date, end_date (DATE)
- is_taxable, is_active (boolean)
- deleted_at (soft deletes)

Relationships:
- belongsTo: employee
```

#### 9. **employee_bonuses**
```sql
Key Columns:
- id (PK)
- employee_id (FK to employees)
- bonus_type: 'performance', 'project_completion', 'referral', etc.
- bonus_name
- amount (DECIMAL)
- grant_date, payment_date (DATE)
- payment_status: 'pending', 'approved', 'paid', 'cancelled'
- is_taxable (boolean)
- deleted_at (soft deletes)

Relationships:
- belongsTo: employee
```

#### 10. **employee_loans**
```sql
Key Columns:
- id (PK)
- loan_number (unique)
- employee_id (FK to employees)
- loan_type: 'sss', 'pagibig', 'company', 'emergency'
- principal_amount, interest_rate, total_amount (DECIMAL)
- monthly_amortization, amount_paid, balance (DECIMAL)
- loan_term_months (INTEGER)
- start_date, end_date (DATE)
- status: 'active', 'completed', 'cancelled'
- deleted_at (soft deletes)

Relationships:
- belongsTo: employee
```

## Query Patterns

### ✅ CORRECT Patterns

#### Employee Queries
```php
// Get employees with position names
Employee::with('positionRate:id,position_name')
    ->select('id', 'first_name', 'last_name', 'position_id')
    ->get()
    ->map(function($emp) {
        return [
            'name' => $emp->first_name . ' ' . $emp->last_name,
            'position' => $emp->position // Accessor handles it
        ];
    });

// Get employees by project
DB::table('employees')
    ->join('projects', 'employees.project_id', '=', 'projects.id')
    ->where('employees.is_active', true)
    ->whereNull('employees.deleted_at')
    ->select('projects.name', DB::raw('count(*) as count'))
    ->groupBy('projects.name')
    ->get();
```

#### Attendance Queries
```php
// Yesterday's absences
Attendance::whereDate('attendance_date', $yesterday) // Use attendance_date!
    ->where('status', 'absent')
    ->with('employee:id,first_name,last_name')
    ->get();

// Attendance rate this month
DB::table('attendance')
    ->whereBetween('attendance_date', [$start, $end]) // Use attendance_date!
    ->groupBy('employee_id')
    ->selectRaw('COUNT(CASE WHEN status = \'present\' THEN 1 END) as present_days')
    ->get();
```

#### Payroll Queries
```php
// Monthly payroll summary with employee count
DB::table('payroll')
    ->join('payroll_items', 'payroll.id', '=', 'payroll_items.payroll_id')
    ->whereYear('payroll.period_start', $year)
    ->whereMonth('payroll.period_start', $month)
    ->whereNull('payroll.deleted_at')
    ->selectRaw('
        SUM(payroll.total_gross_pay) as total_gross,
        SUM(payroll.total_net_pay) as total_net,
        COUNT(DISTINCT payroll_items.employee_id) as employee_count
    ')
    ->first();

// Overtime statistics
DB::table('payroll_items')
    ->join('payroll', 'payroll_items.payroll_id', '=', 'payroll.id')
    ->whereYear('payroll.period_start', $year)
    ->whereMonth('payroll.period_start', $month)
    ->whereNull('payroll.deleted_at')
    ->selectRaw('
        SUM(payroll_items.overtime_hours) as total_hours,
        SUM(payroll_items.overtime_pay) as total_pay,
        COUNT(DISTINCT payroll_items.employee_id) as employee_count
    ')
    ->first();

// Government contributions
DB::table('payroll_items')
    ->join('payroll', 'payroll_items.payroll_id', '=', 'payroll.id')
    ->whereYear('payroll.period_start', $year)
    ->whereNull('payroll.deleted_at')
    ->selectRaw('
        SUM(payroll_items.sss_contribution) as total_sss,
        SUM(payroll_items.philhealth_contribution) as total_philhealth,
        SUM(payroll_items.pagibig_contribution) as total_pagibig,
        SUM(payroll_items.withholding_tax) as total_tax
    ')
    ->first();
```

### ❌ WRONG Patterns (NEVER USE)

```php
// ❌ WRONG: attendance table has no "date" column
Attendance::whereDate('date', $yesterday) // Wrong!

// ❌ WRONG: payroll table has no employee_id
Payroll::whereYear('period_start', $year)
    ->selectRaw('COUNT(DISTINCT employee_id)') // Wrong!

// ❌ WRONG: payroll table has no overtime_pay
Payroll::sum('overtime_pay') // Wrong!

// ❌ WRONG: payroll table has no government contributions
Payroll::selectRaw('SUM(sss_contribution)') // Wrong!

// ❌ WRONG: employee.position without loading relationship
Employee::select('id', 'first_name', 'position_id')
    ->get()
    ->map(fn($e) => $e->position) // May cause N+1!

// ✅ CORRECT: Eager load the relationship
Employee::with('positionRate:id,position_name')
    ->select('id', 'first_name', 'position_id')
    ->get()
    ->map(fn($e) => $e->position) // Safe!
```

## Model Accessors & Relationships

### Employee Model
```php
// Accessor (auto-loads relationship)
$employee->position // Returns position name from positionRate or legacy column

// Relationships
$employee->project // BelongsTo Project
$employee->positionRate // BelongsTo PositionRate
$employee->attendance // HasMany Attendance
$employee->payrollItems // HasMany PayrollItem
$employee->allowances // HasMany EmployeeAllowance
$employee->loans // HasMany EmployeeLoan
```

### Attendance Model
```php
$attendance->employee // BelongsTo Employee
$attendance->attendance_date // Carbon instance
```

### Payroll Model
```php
$payroll->payrollItems // HasMany PayrollItem
```

### PayrollItem Model
```php
$payrollItem->payroll // BelongsTo Payroll
$payrollItem->employee // BelongsTo Employee
```

### EmployeeLeave Model
```php
$leave->employee // BelongsTo Employee
$leave->leaveType // BelongsTo LeaveType (NOT leave_type!)
$leave->approvedBy // BelongsTo User
```

## Common Query Scenarios

### 1. Employee Count & Demographics
```php
// Total employees
Employee::count();

// Active employees
Employee::where('is_active', true)->count();

// By project
DB::table('employees')
    ->join('projects', 'employees.project_id', '=', 'projects.id')
    ->where('employees.is_active', true)
    ->groupBy('projects.name')
    ->selectRaw('projects.name, COUNT(*) as count')
    ->get();

// By position
DB::table('employees')
    ->join('position_rates', 'employees.position_id', '=', 'position_rates.id')
    ->where('employees.is_active', true)
    ->groupBy('position_rates.position_name')
    ->selectRaw('position_rates.position_name, COUNT(*) as count')
    ->get();
```

### 2. Attendance Analysis
```php
// Absences today
Attendance::whereDate('attendance_date', today())
    ->where('status', 'absent')
    ->with('employee')
    ->get();

// Perfect attendance this month
DB::table('attendance')
    ->whereBetween('attendance_date', [startOfMonth, endOfMonth])
    ->groupBy('employee_id')
    ->havingRaw('COUNT(CASE WHEN status = \'present\' THEN 1 END) = COUNT(*)')
    ->select('employee_id')
    ->get();
```

### 3. Payroll Reporting
```php
// Current period summary
DB::table('payroll')
    ->where('year', $year)
    ->where('month', $month)
    ->where('pay_period_number', $period)
    ->whereNull('deleted_at')
    ->selectRaw('
        SUM(total_gross_pay) as gross,
        SUM(total_net_pay) as net,
        SUM(total_deductions) as deductions
    ')
    ->first();

// Employee payroll details
DB::table('payroll_items')
    ->join('payroll', 'payroll_items.payroll_id', '=', 'payroll.id')
    ->join('employees', 'payroll_items.employee_id', '=', 'employees.id')
    ->where('payroll.id', $payrollId)
    ->select('employees.first_name', 'employees.last_name', 'payroll_items.*')
    ->get();
```

## Performance Tips

1. **Always use eager loading** for relationships:
   ```php
   Employee::with('positionRate', 'project')->get();
   ```

2. **Select only needed columns**:
   ```php
   Employee::select('id', 'first_name', 'last_name', 'position_id')->get();
   ```

3. **Use indexes** (already created):
   - employees: employee_number, project_id, is_active, position_id
   - attendance: employee_id, attendance_date
   - payroll: payroll_number, status, payment_date
   - payroll_items: payroll_id, employee_id

4. **Filter soft deletes**:
   ```php
   ->whereNull('deleted_at') // For raw queries
   ```

## Migration History Notes

- **2025_12_27**: Changed department → project, location → worker_address
- **2026_01_05_000004**: Added position_id (FK to position_rates)
- **2026_01_06_000003**: Dropped old position column (kept for backward compatibility)
- **2026_01_10**: Added code column to position_rates
