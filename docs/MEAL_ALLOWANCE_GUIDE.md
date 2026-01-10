# Meal Allowance Management System - Implementation Guide

## Overview
This system allows HR/Accountant to create meal allowance documents, filter employees by position, submit for approval to Admin, and generate PDF documents with signatures.

## Features Implemented

### Backend (Laravel)
1. **Database Tables**
   - `meal_allowances` - Main table for meal allowance documents
   - `meal_allowance_items` - Individual employee allowance entries

2. **Models**
   - `MealAllowance` - Main model with relationships
   - `MealAllowanceItem` - Item model with auto-calculation

3. **Controller** (`MealAllowanceController`)
   - `index()` - List all meal allowances with filters
   - `store()` - Create new meal allowance
   - `update()` - Update draft meal allowance
   - `submit()` - Submit for approval
   - `updateApproval()` - Approve/reject (Admin only)
   - `generatePdf()` - Generate PDF document
   - `downloadPdf()` - Download generated PDF
   - `getPositions()` - Get available positions
   - `getEmployeesByPosition()` - Filter employees by position

4. **PDF Template** (`resources/views/pdfs/meal_allowance.blade.php`)
   - Matches the format from your provided images
   - Grouped by position/role
   - Includes signature sections
   - Professional styling with construction theme

### Frontend (Vue 3 + Vuetify 3)
1. **Service** (`mealAllowanceService.js`)
   - API integration for all endpoints

2. **Main View** (`MealAllowanceView.vue`)
   - List view with filters (status, position, search)
   - Action buttons based on role and status
   - Create/Edit/View/Submit/Approve/Download functionality

3. **Components**
   - `MealAllowanceForm.vue` - Create/Edit form with employee selection
   - `MealAllowanceDetails.vue` - View details dialog
   - `MealAllowanceApproval.vue` - Admin approval dialog with signatory inputs

## Workflow

### 1. Create Meal Allowance (HR/Accountant)
1. Navigate to **Benefits > Meal Allowances**
2. Click "Create New Meal Allowance"
3. Fill in:
   - Title (e.g., "Meal Allowance for November 26 - December 10, 2025 @ CSU MED 2")
   - Location
   - Period (start and end dates)
   - Select Position/Role to filter employees
4. Add employees:
   - Click "Add All Employees" to add all in that position
   - Or manually add rows and select employees
   - Enter "No. of Days" and "Amount per Day"
   - Total is automatically calculated
5. Save as Draft

### 2. Submit for Approval (HR/Accountant)
1. Review the meal allowance
2. Click the Submit button (send icon)
3. Status changes to "Pending Approval"

### 3. Approve and Generate PDF (Admin)
1. Navigate to Meal Allowances
2. Filter by "Pending Approval" status
3. Click the Approve button (check icon)
4. Fill in signatory names:
   - Prepared by
   - Checked by
   - Checked & Verified by
   - Recommended by
   - Approved by
5. Click "Approve & Generate PDF"
6. System automatically:
   - Approves the document
   - Generates PDF with signatures
   - Saves PDF to storage

### 4. Download PDF
1. Click the PDF button (red icon) on approved documents
2. PDF downloads with filename: `[reference_number].pdf`

## API Endpoints

```
GET    /api/meal-allowances                       - List all
POST   /api/meal-allowances                       - Create new
GET    /api/meal-allowances/{id}                  - View details
PUT    /api/meal-allowances/{id}                  - Update (draft only)
DELETE /api/meal-allowances/{id}                  - Delete (draft only)
GET    /api/meal-allowances/positions             - Get positions
POST   /api/meal-allowances/employees-by-position - Filter employees
POST   /api/meal-allowances/{id}/submit           - Submit for approval
POST   /api/meal-allowances/{id}/approval         - Approve/reject
POST   /api/meal-allowances/{id}/generate-pdf     - Generate PDF
GET    /api/meal-allowances/{id}/download-pdf     - Download PDF
```

## Permissions

- **HR/Accountant**: Create, Edit (draft), Submit, View
- **Admin**: All above + Approve/Reject
- **Employee**: No access

## Status Flow

```
DRAFT → PENDING_APPROVAL → APPROVED
                         ↓
                      REJECTED
```

## Installation Steps

1. **Run Migrations**
   ```bash
   cd backend
   php artisan migrate
   ```

2. **Clear Cache**
   ```bash
   php artisan config:clear
   php artisan cache:clear
   php artisan route:clear
   ```

3. **Ensure Storage is Linked**
   ```bash
   php artisan storage:link
   ```

4. **Frontend (if needed)**
   ```bash
   cd frontend
   npm install
   npm run dev
   ```

## PDF Output Format

The generated PDF follows your provided format:
- Header with title and date
- Table grouped by position (LEADMAN, H.E. OPERATOR/DRIVER, etc.)
- Columns: Name, No. of Days, Amount per day, Total, Signature
- Position codes displayed (C&M, Mq, etc.)
- Grand Total at bottom
- Signature sections with names for all signatories

## Testing

1. Login as HR/Accountant
2. Create a meal allowance
3. Add employees from a position
4. Submit for approval
5. Login as Admin
6. Approve the meal allowance with signatory names
7. Download the generated PDF
8. Verify format matches your requirements

## Notes

- Only draft documents can be edited or deleted
- PDF is automatically generated upon approval
- Reference numbers are auto-generated: MA-YYYYMMDD-0001
- Grand total is automatically calculated
- Employee position codes are pulled from PositionRate table

## Troubleshooting

### PDF Generation Fails
- Ensure DomPDF package is installed: `composer require barryvdh/dompdf-laravel`
- Check storage permissions: `chmod -R 775 storage`
- Verify storage is linked: `php artisan storage:link`

### Employees Not Loading
- Ensure employees have position_id assigned
- Check employees are marked as active
- Verify PositionRate table has codes

### Permission Denied
- Check user role in database
- Ensure middleware is applied correctly
- Clear cache: `php artisan config:clear`

## Future Enhancements

- Email notification to admin when submitted
- Bulk approval for multiple documents
- Export to Excel format
- Filter by date range
- Project-based filtering
- Signature upload instead of name input
- Audit log for approval history
