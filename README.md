# Construction Payroll Management System

A comprehensive Philippine payroll management system designed specifically for construction companies. Built with modern technologies to handle complex payroll computations, employee management, attendance tracking, and government compliance requirements.

## Overview

This system streamlines payroll operations for construction companies by automating calculations for Philippine labor laws and government contributions (SSS, PhilHealth, Pag-IBIG, and Withholding Tax). It features multi-user access with role-based permissions, offline-first architecture for field operations, and comprehensive reporting capabilities.

## Tech Stack

- **Backend**: Laravel 10 + PostgreSQL
- **Frontend**: Vue 3 + Vuetify 3
- **Desktop**: Electron
- **Authentication**: Laravel Sanctum
- **Offline Support**: IndexedDB with sync capabilities

## Key Features

### Employee Management

- Complete employee records with government IDs
- Position and project assignment tracking
- Resume upload and approval workflow
- Employee documents management
- Activity status monitoring

### Attendance & Time Tracking

- Biometric device integration
- Manual attendance entry
- Overtime and night differential tracking
- Holiday pay calculations
- Leave management

### Payroll Processing

- Automated Philippine payroll computation
- Basic pay, overtime, holiday pay, night differential
- Multiple allowance types (rice, transportation, communication, etc.)
- Bonus management (13th month, performance, project completion)
- Loan and cash advance tracking
- Deduction management

### Government Compliance

- **SSS Contributions**: Automatic computation based on 2025 rates
- **PhilHealth**: 4% premium calculation
- **Pag-IBIG**: 1-2% contributions with ₱200 cap
- **Withholding Tax**: TRAIN Law 2025 compliance
- Government reports and remittance tracking

### Benefits & Compensation

- 14 types of allowances (daily, weekly, monthly)
- Multiple bonus categories
- Loan management with automatic deductions
- Flexible deduction system
- 13th month pay computation

### Reporting & Analytics

- Payroll reports with Excel export
- Attendance summaries
- Employee payslips (PDF)
- Government contribution reports
- Audit trails and system logs

### Access Control

- **Admin**: Full system access
- **Accountant**: Payroll and reports access
- **Employee**: Personal dashboard and payslip viewing

### Additional Features

- Offline-first architecture for remote sites
- Data synchronization when online
- Construction-themed professional UI
- Multi-project support
- Responsive design for mobile and desktop
- Secure authentication with 2FA support

## Quick Start

### Prerequisites

- PHP 8.1+, Composer 2.x
- Node.js 18+
- PostgreSQL 14+

### Installation

```bash
# Backend Setup
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
php artisan serve

# Frontend Setup
cd frontend
npm install
npm run dev
```

### Default Credentials

- **Admin**: `admin@payroll.com` / `admin123`
- **Accountant**: `accountant@payroll.com` / `accountant123`
- **Employee**: `employee@payroll.com` / `employee123`

## Configuration

Configure your database in `backend/.env`:

```env
DB_CONNECTION=pgsql
DB_DATABASE=construction_payroll
DB_USERNAME=postgres
DB_PASSWORD=your_password
```

## Documentation

Detailed documentation available in the `/docs` directory:

- **Database Schema**: Complete table structure and relationships
- **API Structure**: All available endpoints with examples
- **Payroll Computation**: Step-by-step formulas and calculations
- **Setup Guide**: Detailed installation instructions

## System Architecture

```
┌─────────────────────────────────────────────┐
│          Vue 3 Frontend (Vuetify)          │
│         Offline-First with IndexedDB        │
└─────────────────┬───────────────────────────┘
                  │ REST API
┌─────────────────▼───────────────────────────┐
│         Laravel 10 Backend API              │
│   Service Layer + Repository Pattern        │
└─────────────────┬───────────────────────────┘
                  │
┌─────────────────▼───────────────────────────┐
│         PostgreSQL Database                 │
│        35+ Tables with Relationships        │
└─────────────────────────────────────────────┘
```

## Payroll Computation Flow

```
Basic Pay → Overtime → Holiday Pay → Night Diff → Allowances → Bonuses
    ↓
Gross Pay
    ↓
SSS → PhilHealth → Pag-IBIG → Withholding Tax → Other Deductions → Loans
    ↓
Net Pay
```

## License

This project is licensed under the Creative Commons Attribution-NonCommercial 4.0 International License (CC BY-NC 4.0).

**Free for personal, educational, and non-commercial use.**  
For commercial licensing inquiries, please contact the project maintainer.

See [LICENSE](LICENSE) file for full details.

## Support

For issues, questions, or contributions, please refer to the project documentation or contact the development team.
