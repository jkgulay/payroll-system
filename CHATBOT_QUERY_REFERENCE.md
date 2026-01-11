# AI Chatbot - Quick Query Reference

## Verified Working Queries

### Employee Information ✅

**Query Examples:**
- "How many employees do we have?"
- "Show me all active employees"
- "How many inactive employees?"
- "List employees by project"
- "Who are the recent hires?"
- "Show me employees hired in the last 3 months"
- "Who are the top 5 earners?"
- "What's the average salary by project?"

**Database Tables Used:**
- `employees` (with `is_active`, `project_id`, `position_id`)
- `projects` (joined for project names)
- `position_rates` (joined for position names)

---

### Attendance & Absences ✅

**Query Examples:**
- "Who was absent yesterday?"
- "Show me today's absences"
- "How many people were absent this week?"
- "Who has low attendance this month?"
- "Show me attendance rate for [employee name]"

**Database Tables Used:**
- `attendance` (using `attendance_date` column)
- `employees` (joined for employee names)

**Important:** Column is `attendance_date` NOT `date`

---

### Payroll Information ✅

**Query Examples:**
- "What's the total payroll for December 2025?"
- "Show me payroll for January 2026"
- "How much did we pay last month?"
- "What was the total gross pay in Q4?"
- "Compare Q3 and Q4 payroll"

**Database Tables Used:**
- `payroll` (for totals: `total_gross_pay`, `total_net_pay`)
- `payroll_items` (for employee count)

**Join Pattern:**
```sql
payroll → payroll_items (for employee_id)
```

---

### Overtime Statistics ✅

**Query Examples:**
- "Show me overtime statistics"
- "What's the total overtime pay this month?"
- "How many overtime hours this month?"
- "Who worked the most overtime?"

**Database Tables Used:**
- `payroll_items` (has `overtime_hours`, `overtime_pay`)
- `payroll` (joined for period filtering)

**Important:** Overtime data is in `payroll_items` NOT `payroll`

---

### Government Contributions & Tax ✅

**Query Examples:**
- "What are the SSS contributions this year?"
- "Show me PhilHealth remittances"
- "Total Pag-IBIG contributions this year?"
- "What's the withholding tax total?"
- "Show me all government contributions"

**Database Tables Used:**
- `payroll_items` (has all contribution columns)
  - `sss_contribution`
  - `philhealth_contribution`
  - `pagibig_contribution`
  - `withholding_tax`
- `payroll` (joined for year/period filtering)

**Important:** Contributions are in `payroll_items` NOT `payroll`

---

### Leave Management ✅

**Query Examples:**
- "Show me pending leave requests"
- "How many leave requests are pending?"
- "Who is on leave today?"
- "List all approved leaves this month"

**Database Tables Used:**
- `employee_leaves` (with status filter)
- `employees` (joined for employee names)
- `leave_types` (joined for leave type names)

**Relationship:** Use `leaveType` (NOT `leave_type`)

---

### Document Compliance ✅

**Query Examples:**
- "Who has missing documents?"
- "Show me employees without SSS numbers"
- "Who needs to submit government IDs?"

**Database Tables Used:**
- `employees` (checking for NULL in ID columns)
  - `tin_number`
  - `sss_number`
  - `philhealth_number`
  - `pagibig_number`

---

### System Help ✅

**Query Examples:**
- "How do I process payroll?"
- "Help me with overtime calculation"
- "How is tax computed?"
- "Explain leave accrual"

**Response:** Static help content from configuration

---

## Common Query Patterns

### Pattern 1: Count with Filter
```php
Employee::where('is_active', true)->count()
```

### Pattern 2: Join with Aggregation
```php
DB::table('employees')
    ->join('projects', 'employees.project_id', '=', 'projects.id')
    ->groupBy('projects.name')
    ->selectRaw('projects.name, COUNT(*) as count')
    ->get()
```

### Pattern 3: Payroll with Items
```php
DB::table('payroll')
    ->join('payroll_items', 'payroll.id', '=', 'payroll_items.payroll_id')
    ->whereYear('payroll.period_start', $year)
    ->selectRaw('SUM(...), COUNT(DISTINCT payroll_items.employee_id)')
    ->get()
```

### Pattern 4: Eager Loading Relationships
```php
Employee::with('positionRate:id,position_name')
    ->select('id', 'first_name', 'position_id')
    ->get()
```

---

## Column Name Quick Reference

### ❌ WRONG → ✅ CORRECT

**Attendance Table:**
- ❌ `date` → ✅ `attendance_date`

**Employee Table (Department → Project):**
- ❌ `department_id` → ✅ `project_id`
- ❌ `department` → ✅ `projects` (table)
- ❌ `location` → ✅ `worker_address`

**Employee Status:**
- ❌ `status` → ✅ `is_active` (boolean)
- ❌ `hire_date` → ✅ `date_hired`

**Payroll Employee Data:**
- ❌ `payroll.employee_id` → ✅ `payroll_items.employee_id`
- ❌ `payroll.overtime_pay` → ✅ `payroll_items.overtime_pay`
- ❌ `payroll.sss_contribution` → ✅ `payroll_items.sss_contribution`

---

## Response Format

The AI will respond with:
1. **Direct Answer** - Numerical data, lists, summaries
2. **Context** - Relevant details from database
3. **Formatted Data** - Tables, bullet points, or structured info
4. **Suggestions** - Related queries or next steps

---

## Error Handling

If you encounter errors, check:

1. **Column Names** - Use correct column names from schema
2. **Table Joins** - Verify payroll ↔ payroll_items joins
3. **Relationships** - Ensure eager loading with `with()`
4. **Soft Deletes** - Filter `whereNull('deleted_at')`
5. **Active Records** - Filter `where('is_active', true)` when needed

---

## Test Scenarios

### Scenario 1: Employee Count by Project
✅ **Query:** "How many employees in each project?"
✅ **Result:** List of projects with employee counts
✅ **Tables:** employees ← project

### Scenario 2: Yesterday's Absences
✅ **Query:** "Who was absent yesterday?"
✅ **Result:** List of employees who were absent
✅ **Tables:** attendance (attendance_date) ← employees

### Scenario 3: Monthly Payroll Total
✅ **Query:** "What's the total payroll for December 2025?"
✅ **Result:** Gross pay, net pay, employee count
✅ **Tables:** payroll ← payroll_items (for count)

### Scenario 4: Overtime This Month
✅ **Query:** "Show me overtime statistics this month"
✅ **Result:** Total hours, total pay, employee count
✅ **Tables:** payroll ← payroll_items (overtime columns)

### Scenario 5: Government Contributions
✅ **Query:** "What are the government contributions this year?"
✅ **Result:** SSS, PhilHealth, Pag-IBIG, Tax totals
✅ **Tables:** payroll ← payroll_items (contribution columns)

---

## Success Indicators

✅ No 500 errors  
✅ Accurate employee counts  
✅ Correct attendance data  
✅ Proper payroll calculations  
✅ Valid government contribution totals  
✅ Fast response times (with eager loading)  
✅ Consistent results across queries  

---

## Quick Debugging

If chatbot returns wrong data:

1. **Check the query intent detection** - Is it categorizing correctly?
2. **Verify the database context** - Is it querying the right tables?
3. **Review the joins** - Are payroll_items joined properly?
4. **Check column names** - Using `attendance_date` not `date`?
5. **Look at logs** - `backend/storage/logs/laravel.log`

---

## Reference Documents

- `DATABASE_SCHEMA_REFERENCE.md` - Complete schema guide
- `DATABASE_ANALYSIS_COMPLETE.md` - All fixes documented
- `AI_CHATBOT_README.md` - Setup and usage guide
- `CHATBOT_UI_UPDATE.md` - UI improvements

---

**Last Updated:** January 11, 2026  
**Status:** ✅ All queries verified and working
