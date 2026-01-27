# Undertime Feature - Quick Reference

## Summary
✅ **Implemented department-specific undertime tracking with automatic salary deductions**

## Departments Affected
- Admin Resign
- Sur admin  
- Weekly Admin (mix)
- ENGINEER SA SITE
- Giovanni Construction and Power On Enterprise Co

## Quick Rules

| Time In | Status | Undertime Hours | Deduction Formula |
|---------|--------|-----------------|-------------------|
| 8:00 - 8:03 | ✅ On Time | 0 | ₱0 |
| 8:04 - 9:00 | ⚠️ Late | Minutes/60 | (Rate/8) × Hours |
| 9:01+ | ❌ Half-Day | 4 hours | Rate/2 |

## Code Changes

### 1. Attendance Model
```php
// New methods added:
- isUndertimeDepartment() - Check if employee in tracked dept
- calculateUndertimeHours() - Calculate undertime with grace period
```

### 2. Payroll Service
```php
// Modified calculation:
- Accumulates undertime hours from attendance
- Calculates deduction: (rate/8) × undertime_hours
- Deducts from gross pay
```

### 3. Database
```sql
-- New columns in payroll_items:
ALTER TABLE payroll_items ADD COLUMN undertime_hours DECIMAL(8,2);
ALTER TABLE payroll_items ADD COLUMN undertime_deduction DECIMAL(12,2);
```

## Example Scenarios

### Scenario 1: 10 Minutes Late
```
Time In: 8:10 AM
Grace: 3 min → Late: 7 min
Undertime: 7/60 = 0.1167 hours
Rate: ₱800/day → ₱100/hour
Deduction: ₱100 × 0.1167 = ₱11.67
```

### Scenario 2: Half-Day
```
Time In: 9:15 AM
Status: Half-Day
Undertime: 4 hours
Rate: ₱800/day → ₱100/hour
Deduction: ₱100 × 4 = ₱400
```

## Files Modified
1. ✅ `app/Models/Attendance.php` - Undertime calculation logic
2. ✅ `app/Models/PayrollItem.php` - Added undertime fields
3. ✅ `app/Services/PayrollService.php` - Payroll deduction calculation
4. ✅ `database/migrations/2026_01_27_000001_add_undertime_deduction_to_payroll_items.php` - DB schema

## Migration Status
✅ **Migrated successfully** - Run on: January 27, 2026

## Next Steps (Optional)
- [ ] Update frontend to display undertime hours and deductions
- [ ] Add undertime report in admin dashboard
- [ ] Configure grace period and thresholds in settings
- [ ] Add email notifications for half-day status
