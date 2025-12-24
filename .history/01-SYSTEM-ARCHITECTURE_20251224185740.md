# Payroll Management System - System Architecture

## Overview

Desktop-based payroll system with mobile admin access, designed for 100-200 employees across multiple departments and locations in the Philippines.

## Tech Stack

### Frontend

- **Framework**: Vue 3 (Composition API)
- **UI Library**: Vuetify 3
- **State Management**: Pinia
- **API Client**: Axios
- **Desktop Wrapper**: Electron
- **Mobile**: Progressive Web App (PWA) for admin access
- **Offline Storage**: IndexedDB via Dexie.js
- **PDF Generation**: jsPDF + html2canvas
- **Excel Export**: SheetJS (xlsx)

### Backend

- **Framework**: Laravel 10+
- **API**: RESTful with Laravel Sanctum authentication
- **Queue System**: Laravel Queue (database driver)
- **File Storage**: Laravel Storage (local/cloud)
- **PDF Generation**: DomPDF or Snappy (wkhtmltopdf)
- **Excel Import/Export**: Laravel Excel (Maatwebsite)

### Database

- **Primary Database**: PostgreSQL 14+
- **Offline Storage**: IndexedDB (client-side)
- **Cache**: Redis (optional, for performance)

### Infrastructure

- **Primary Deployment**: Electron desktop app
- **Web Server**: Nginx or Apache (local)
- **API Server**: PHP-FPM
- **Background Jobs**: Laravel Queue Worker
- **Sync Strategy**: Conflict resolution with last-write-wins + manual review

---

## System Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                        CLIENT LAYER                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                   │
│  ┌──────────────────┐              ┌──────────────────┐         │
│  │  Desktop App     │              │   Mobile PWA     │         │
│  │  (Electron)      │              │   (Admin Only)   │         │
│  │                  │              │                  │         │
│  │  Vue 3 + Vuetify │              │  Vue 3 + Vuetify │         │
│  │  IndexedDB       │              │  IndexedDB       │         │
│  └──────────────────┘              └──────────────────┘         │
│         │                                    │                   │
│         └────────────────┬───────────────────┘                   │
│                          │                                       │
│                   Offline Sync Queue                             │
│                          │                                       │
└──────────────────────────┼───────────────────────────────────────┘
                           │
                           │ HTTPS/REST API
                           │
┌──────────────────────────┼───────────────────────────────────────┐
│                          │    API LAYER                           │
├──────────────────────────┼───────────────────────────────────────┤
│                          ▼                                        │
│              ┌─────────────────────┐                             │
│              │  Laravel REST API   │                             │
│              │  (Sanctum Auth)     │                             │
│              └─────────────────────┘                             │
│                          │                                        │
│         ┌────────────────┼────────────────┐                      │
│         │                │                │                      │
│         ▼                ▼                ▼                      │
│  ┌──────────┐   ┌──────────────┐   ┌──────────┐                │
│  │  Auth    │   │   Business   │   │  Queue   │                │
│  │  Module  │   │   Logic      │   │  Worker  │                │
│  └──────────┘   └──────────────┘   └──────────┘                │
│                                                                   │
└───────────────────────────┬───────────────────────────────────────┘
                            │
                            │ PDO/Eloquent
                            │
┌───────────────────────────┼───────────────────────────────────────┐
│                           │     DATA LAYER                         │
├───────────────────────────┼───────────────────────────────────────┤
│                           ▼                                        │
│              ┌─────────────────────┐                              │
│              │   PostgreSQL DB     │                              │
│              │                     │                              │
│              │  - Employees        │                              │
│              │  - Attendance       │                              │
│              │  - Payroll          │                              │
│              │  - Deductions       │                              │
│              │  - Government       │                              │
│              │  - Audit Logs       │                              │
│              └─────────────────────┘                              │
│                                                                    │
└────────────────────────────────────────────────────────────────────┘
```

---

## Architecture Layers

### 1. Presentation Layer (Vue 3 + Vuetify)

**Desktop Application (Electron)**

- Primary interface for all users
- Full feature access
- Offline-first with background sync
- Local IndexedDB for cached data
- Embedded Node.js server (optional) or connect to network API

**Mobile PWA (Admin Only)**

- Approval workflows
- Dashboard and reports
- Employee quick lookup
- Push notifications for pending actions

### 2. Application Layer (Laravel API)

**API Architecture Pattern**: Repository + Service Pattern

```
Controller → Request Validation → Service Layer → Repository → Model → Database
                                      ↓
                                  Business Logic
                                  Calculations
                                  Validations
```

**Key Components**:

- **Controllers**: Handle HTTP requests/responses
- **Services**: Business logic and complex operations
- **Repositories**: Data access abstraction
- **Jobs**: Asynchronous processing (payroll computation, PDF generation)
- **Events/Listeners**: Audit logging, notifications
- **Middleware**: Authentication, authorization, rate limiting

### 3. Data Layer (PostgreSQL)

**Database Design Principles**:

- Normalized structure (3NF minimum)
- Soft deletes for critical data
- Audit trail tables
- Temporal data for historical tracking
- Indexed columns for performance

---

## Offline-First Architecture

### Sync Strategy

**Data Classification**:

1. **Read-Only Cached Data** (no sync conflicts)

   - Government contribution tables
   - Company policies
   - Department/location lists
   - User permissions

2. **User-Specific Data** (minimal conflicts)

   - Employee own payslips
   - Employee own attendance
   - Attendance correction requests

3. **Shared Mutable Data** (potential conflicts)
   - Employee records
   - Attendance entries (admin)
   - Payroll records
   - Deductions

### Sync Flow

```
1. User makes changes → Store in IndexedDB with sync flag
2. Queue changes with timestamp and user ID
3. When online → Send changes to API
4. API validates and processes
5. On success → Mark as synced
6. On conflict → Present to user for resolution
7. Background: Pull server changes → Merge with local data
```

### Conflict Resolution

**Strategy**: Last-Write-Wins with Conflict Detection

1. Each record has `version` field and `updated_at` timestamp
2. Client sends version number with updates
3. Server checks if version matches current
4. If mismatch → conflict detected
5. Present both versions to user for manual merge
6. Critical operations (payroll approval) require online mode

---

## Security Architecture

### Authentication

- **Laravel Sanctum** for API token authentication
- **Role-Based Access Control (RBAC)** with permission gates
- **Session Management**: 8-hour timeout, refresh tokens
- **Password Policy**: Min 8 chars, complexity requirements
- **Two-Factor Authentication (2FA)**: Optional for admin

### Authorization

```
Role → Permissions → Resources

Admin/Owner → All permissions
Accountant → Payroll, Attendance, Reports (no employee delete)
Employee → View own data, request corrections
```

### Data Security

- **Encryption at Rest**: Database encryption for sensitive fields
- **Encryption in Transit**: HTTPS/TLS 1.3
- **Audit Logging**: All data modifications logged with user, timestamp, old/new values
- **Input Validation**: Server-side validation for all inputs
- **SQL Injection Prevention**: Eloquent ORM with parameter binding
- **XSS Prevention**: Output escaping in Vue templates

### Desktop Security

- **App Signing**: Code signing for Electron app
- **Update Mechanism**: Secure auto-update with signature verification
- **Local Storage**: Encrypted IndexedDB for sensitive data
- **API Keys**: Stored in secure environment, not in client code

---

## Performance Considerations

### Backend Optimization

- **Database Indexing**: All foreign keys, frequently queried columns
- **Query Optimization**: Eager loading, pagination
- **Caching**: Redis for contribution tables, user permissions
- **Background Jobs**: Offload heavy computations (payroll, reports)
- **API Rate Limiting**: Prevent abuse

### Frontend Optimization

- **Code Splitting**: Lazy load routes and components
- **Virtual Scrolling**: For large employee lists
- **Debouncing**: Search inputs, auto-save
- **Service Workers**: Cache static assets, API responses
- **Progressive Enhancement**: Core features work offline

### Database Optimization

- **Partitioning**: Partition attendance and payroll tables by year/month
- **Archiving**: Move old payroll records to archive tables
- **Connection Pooling**: Optimize database connections
- **Query Monitoring**: Track slow queries

---

## Scalability Plan

### Current Scale: 100-200 employees

- Single PostgreSQL instance
- Single API server (can be local or cloud)
- Electron desktop apps

### Growth Path: 500+ employees

- Read replicas for PostgreSQL
- Load balancer for API servers
- Redis cluster for caching
- CDN for static assets
- Microservices for payroll computation

### Growth Path: 1000+ employees

- Sharding by location/department
- Message queue (RabbitMQ/Redis) for async processing
- Dedicated reporting database
- Distributed file storage (S3-compatible)

---

## Deployment Architecture

### Local Deployment (Primary)

**Option A: All-in-One Electron**

```
Electron App
├── Vue Frontend (Renderer Process)
├── Node.js Backend Proxy (Main Process)
├── Laravel API (via PHP binary)
└── PostgreSQL (via embedded PostgreSQL or connection to local instance)
```

**Option B: Separate Services (Recommended)**

```
Desktop:
├── Electron App (Frontend Only)
├── Laravel API (XAMPP/LARAGON/Docker)
└── PostgreSQL (Local Installation)

Network:
└── API accessible via local network (e.g., 192.168.1.100:8000)
```

### Cloud Deployment (Optional for sync)

```
Cloud Server:
├── Laravel API (AWS EC2, DigitalOcean, or Azure VM)
├── PostgreSQL (Managed Database)
├── Redis (Caching)
└── S3-Compatible Storage (Documents, backups)
```

---

## Technology Justification

### Why Vue 3?

- Composition API for better code organization
- Excellent TypeScript support
- Smaller bundle size than React
- Reactivity system perfect for form-heavy apps

### Why Vuetify?

- Material Design out of the box
- Comprehensive component library
- Data tables with sorting, filtering, pagination
- Form validation built-in

### Why Laravel?

- Robust ORM (Eloquent)
- Built-in authentication and authorization
- Queue system for background jobs
- Extensive package ecosystem
- Excellent documentation

### Why PostgreSQL?

- JSONB support for flexible schema evolution
- Advanced indexing (GiST, GIN)
- Better handling of concurrent writes
- Window functions for complex reporting
- Full ACID compliance

### Why Electron?

- Cross-platform desktop app (Windows, Mac, Linux)
- Access to Node.js APIs
- Auto-update mechanism
- Native system integration
- Can package PHP/PostgreSQL if needed

---

## Development Environment Setup

### Prerequisites

- **Node.js**: 18+ (for Vue, Electron)
- **PHP**: 8.1+ (for Laravel)
- **Composer**: 2.x
- **PostgreSQL**: 14+
- **Git**: Version control

### Recommended Tools

- **IDE**: VS Code or PHPStorm
- **API Testing**: Postman or Insomnia
- **Database Management**: DBeaver or pgAdmin
- **Version Control**: Git + GitHub/GitLab

### Local Development

```bash
# Backend
cd backend
composer install
php artisan migrate:fresh --seed
php artisan serve

# Frontend
cd frontend
npm install
npm run dev

# Electron (after frontend build)
cd frontend
npm run electron:dev
```

---

## Next Steps

Refer to:

- `02-DATABASE-SCHEMA.md` for complete database design
- `03-API-STRUCTURE.md` for Laravel API breakdown
- `04-FRONTEND-STRUCTURE.md` for Vue component architecture
- `05-PAYROLL-COMPUTATION.md` for Philippine payroll calculation logic
- `06-DEVELOPMENT-ROADMAP.md` for phased implementation plan
