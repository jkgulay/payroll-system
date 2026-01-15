# Payroll Filtering Feature

## Overview
Added employee filtering capabilities to payroll creation, allowing you to create payroll for specific groups of employees instead of all active employees.

## Features Added

### Filter Options
1. **All Employees** (Default)
   - Creates payroll for all active employees
   - Traditional behavior

2. **By Position/Role**
   - Filter employees by their position/role
   - Multiple positions can be selected
   - Useful for processing payroll by job type (e.g., only Carpenters, only Electricians)

3. **By Project**
   - Filter employees by their assigned project
   - Multiple projects can be selected
   - Useful for project-specific payroll processing

## Backend Changes

### PayrollController.php
**File:** `backend/app/Http/Controllers/PayrollController.php`

#### Store Method
- Added validation for new filter parameters:
  - `filter_type`: 'all', 'position', or 'project'
  - `position_ids[]`: Array of position rate IDs
  - `project_ids[]`: Array of project IDs
- Passes filters to `generatePayrollItems()` method
- Returns employee count in response

#### generatePayrollItems Method
- Now accepts optional `$filters` parameter
- Applies query filters based on filter type:
  - Position filter: `whereIn('position_id', $position_ids)`
  - Project filter: `whereIn('project_id', $project_ids)`
- Throws exception if no employees match the filter
- Validates that at least one employee exists before creating payroll

## Frontend Changes

### PayrollListView.vue
**File:** `frontend/src/views/payroll/PayrollListView.vue`

#### UI Enhancements
1. **Filter Selection Radio Group**
   - Choose between All/Position/Project filters
   - Inline radio buttons for easy selection

2. **Position Autocomplete**
   - Multi-select dropdown
   - Shows position name, code, and daily rate
   - Validation: At least one position required when filter is active
   - Chip display for selected positions

3. **Project Autocomplete**
   - Multi-select dropdown
   - Shows project name, code, and employee count
   - Validation: At least one project required when filter is active
   - Chip display for selected projects

4. **Information Alert**
   - Shows when filters are active
   - Explains which employees will be included
   - Helps prevent confusion

#### Data Management
- Added state for positions and projects lists
- Added loading states for autocompletes
- Fetches positions from `/position-rates` API
- Fetches projects from `/projects` API
- Filters to show only active positions and projects

#### Form Submission
- Sends filter data to backend
- Shows success message with employee count
- Example: "Payroll created successfully for 15 employee(s)"

## Usage Examples

### Example 1: Payroll for All Carpenters
1. Click "Create Payroll"
2. Fill in period and payment dates
3. Select "By Position/Role"
4. Choose "CARPENTER" from the dropdown
5. Click Create
6. System creates payroll for all active carpenters

### Example 2: Payroll for Specific Project
1. Click "Create Payroll"
2. Fill in period and payment dates
3. Select "By Project"
4. Choose one or more projects (e.g., "Building A Construction")
5. Click Create
6. System creates payroll for all employees in selected projects

### Example 3: Multiple Positions
1. Click "Create Payroll"
2. Fill in period and payment dates
3. Select "By Position/Role"
4. Choose multiple positions (e.g., "CARPENTER", "MASON", "ELECTRICIAN")
5. Click Create
6. System creates payroll for all employees with any of the selected positions

## Validation Rules

### Backend Validation
```php
'filter_type' => 'nullable|in:all,position,project'
'position_ids' => 'nullable|array'
'position_ids.*' => 'exists:position_rates,id'
'project_ids' => 'nullable|array'
'project_ids.*' => 'exists:projects,id'
```

### Frontend Validation
- At least one position required when filter_type = 'position'
- At least one project required when filter_type = 'project'
- All standard payroll validations still apply

## Error Handling

### No Employees Found
If no employees match the selected filters:
```json
{
  "message": "Failed to create payroll",
  "error": "No employees found matching the selected filters"
}
```

### Invalid Position/Project IDs
If invalid IDs are provided:
```json
{
  "message": "The selected position ids.0 is invalid."
}
```

## API Endpoints Used

### Get Positions
```
GET /api/position-rates
Returns: List of all position rates (filtered to active only in frontend)
```

### Get Projects
```
GET /api/projects
Returns: List of all projects (filtered to active only in frontend)
```

### Create Payroll
```
POST /api/payrolls
Body: {
  period_name: string,
  period_start: date,
  period_end: date,
  payment_date: date,
  notes: string (optional),
  filter_type: 'all' | 'position' | 'project',
  position_ids: number[] (when filter_type = 'position'),
  project_ids: number[] (when filter_type = 'project')
}
```

## Database Impact

### No Schema Changes Required
- Uses existing relationships:
  - `employees.position_id` → `position_rates.id`
  - `employees.project_id` → `projects.id`
- No new tables or columns needed

## Benefits

1. **Flexibility**: Process payroll for specific groups
2. **Efficiency**: No need to delete unwanted payroll items
3. **Accuracy**: Reduces errors from processing wrong employees
4. **Control**: Better management of project-based or role-based payroll
5. **Audit Trail**: Clear indication of which filters were used

## Future Enhancements

Possible additions:
1. Department-based filtering (when department feature is added)
2. Employment type filtering (Regular, Contractual, etc.)
3. Location-based filtering
4. Combination filters (e.g., Position AND Project)
5. Save filter presets for common scenarios
6. Preview employee count before creating payroll

## Testing Checklist

- [x] Create payroll with "All Employees" filter
- [x] Create payroll with single position filter
- [x] Create payroll with multiple positions filter
- [x] Create payroll with single project filter
- [x] Create payroll with multiple projects filter
- [x] Validate error handling for no matching employees
- [x] Validate required field when filters are active
- [x] Verify employee count in success message
- [x] Test with no active positions/projects
- [x] Verify filter clears when switching between types

## Notes

- Filters only apply to **active** employees
- Inactive employees are never included regardless of filters
- Editing payroll does not currently support filter changes
- Filters are applied at creation time only
- After creation, all generated payroll items are treated equally

---

**Last Updated:** January 15, 2026
