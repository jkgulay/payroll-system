# Construction Payroll Management System

Philippine payroll system for construction companies built with Laravel 10 and Vue 3.

## Tech Stack

- **Backend**: Laravel 10 + PostgreSQL
- **Frontend**: Vue 3 + Vuetify
- **Auth**: Laravel Sanctum

## Quick Start

### Prerequisites

- PHP 8.1+, Composer 2.x
- Node.js 18+
- PostgreSQL 14+

### Installation

```bash
# Backend
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve

# Frontend
cd frontend
npm install
npm run dev
```

### Default Credentials

- Admin: `admin@payroll.com` / `admin123`
- Accountant: `accountant@payroll.com` / `accountant123`
- Employee: `employee@payroll.com` / `employee123`

## Features

- Employee management with government IDs
- Attendance tracking and biometric import
- Payroll processing with Philippine tax compliance
- Government contributions (SSS, PhilHealth, Pag-IBIG, Withholding Tax)
- Benefits, allowances, loans, and deductions
- Multi-stage approval workflow
- **Resume upload and approval system** (NEW)
- Excel export capabilities
- Offline-first architecture

**What it covers:**

- Step-by-step Philippine payroll computation
- Basic pay, overtime, holiday pay calculations
- SSS, PhilHealth, Pag-IBIG formulas
- Withholding tax (TRAIN Law) computation
- Complete worked examples
- Special cases (13th month, minimum wage, etc.)
- Validation rules

**Read this if you need:**
✅ What's Completed

### Backend (100% Complete)

- ✅ **9 Database Migrations** (35+ tables)

  - Users, employees, departments, locations
  - Attendance tracking with biometric import
  - Payroll with construction workflow
  - Employee benefits (allowances, loans, deductions, 13th month)
  - Government contribution tables (SSS, PhilHealth, Pag-IBIG, Tax)
  - Recruitment module, leave management
  - System tables (holidays, settings, audit logs)

- ✅ **18 Eloquent Models** with relationships

  - Complete CRUD operations
  - Auto-calculations (attendance hours, payroll totals)
  - Scopes and query builders
  - Audit trail support

- ✅ **6 Service Classes**

  - `PayrollService` (650+ lines) - Complete payroll engine
  - `AttendanceService` - Biometric import, hours calculation
  - `SSSComputationService` - 2025 SSS rates
  - `PhilHealthComputationService` - 4% premium
  - `PagIbigComputationService` - 1-2% with ₱200 cap
  - `TaxComputationService` - TRAIN Law 2025

- ✅ **API Structure** (70+ endpoints)
  - Authentication (Sanctum)
  - Employee management
  - Attendance tracking
  - Payroll processing with approval workflow
  - Benefits management (allowances, loans, deductions)
  - 13th month pay computation
  - Recruitment module
  - Leave management
  - Reports and analytics

**See [BACKEND-COMPLETE.md](BACKEND-COMPLETE.md) for detailed documentation.**

---

## 📋 Implementation Checklist

### Phase 1: Backend ✅ COMPLETE

- [x] Database schema design
- [x] Laravel migrations
- [x] Eloquent models with relationships
- [x] Service layer (PayrollService, AttendanceService)
- [x] Government computation services (SSS, PhilHealth, Pag-IBIG, Tax)
- [x] API controllers and routes
- [x] Configuration files

### Phase 2: Frontend ✅ COMPLETE

- [x] Initialize Vue 3 + Vite project
- [x] Install Vuetify 3
- [x] Set up Pinia stores (auth, employee, payroll, attendance, sync)
- [x] Create core layouts (MainLayout with sidebar)
- [x] Implement authentication views (LoginView)
- [x] Create dashboard with statistics
- [x] Build employee management views
- [x] Create payroll management views
- [x] Set up offline support with IndexedDB
- [x] API integration with Axios interceptors

**See [FRONTEND-COMPLETE.md](FRONTEND-COMPLETE.md) for detailed documentation.**

### Phase 3: Desktop App ✅ COMPLETE

- [x] Electron wrapper setup
- [x] IPC communication (main ↔ renderer)
- [x] File system operations
- [x] Print to PDF support
- [x] Build configuration for Windows/Mac/Linux

### Phase 4: Production 📋 NEXT STEPS

- [x] Construction payslip PDF template (Blade)
- [ ] Complete remaining frontend views (employee form, payroll processing)
- [ ] Database seeders (test data)
- [ ] API tests (PHPUnit)
- [ ] User acceptance testing
- [ ] Deployment
      (✅ Backend Complete!)

1. Review [BACKEND-COMPLETE.md](BACKEND-COMPLETE.md) for what's built
2. Study [02-DATABASE-SCHEMA.md](02-DATABASE-SCHEMA.md) for data structure
3. Check [03-API-STRUCTURE.md](03-API-STRUCTURE.md) for API endpoints
4. See [05-PAYROLL-COMPUTATION.md](05-PAYROLL-COMPUTATION.md) for formulas
5. Test API endpoints with Postman/Insomnia

### Phase 4: Production 📋 PLANNED

- [ ] Construction payslip PDF template
- [ ] Database seeders
- [ ] API tests
- [ ] User acceptance testing
- [ ] Deployment

---

## 🎓 Developer

- Payroll calculation formulas
- Government contribution rates
- Tax computation logic
- Verification of computations

**Computation Flow:**

```
Basic Pay → Overtime → Holiday Pay → Night Diff → Allowances → Bonuses
    ↓
Gross Pay
    ↓
SSS → PhilHealth → Pag-IBIG → Tax → Other Deductions → Loans
    ↓
Net Pay
```

---

### 6. [Development Roadmap](06-DEVELOPMENT-ROADMAP.md)

**What it covers:**

- 9-phase implementation plan
- Detailed task breakdown per phase
- Timeline estimates (4-6 months total)
- MVP first, then progressive enhancement
- Testing and deployment strategy
- Maintenance plan

## Configuration

Database settings in `backend/.env`:

```env
DB_CONNECTION=pgsql
DB_DATABASE=construction_payroll
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

## License

MIT
