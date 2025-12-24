# Frontend Implementation - COMPLETE ✅

**Construction Payroll Management System - Frontend**

This document provides comprehensive documentation for the Vue 3 + Vuetify 3 frontend implementation.

---

## 📁 Project Structure

```
frontend/
├── electron/                    # Electron wrapper for desktop app
│   ├── main.js                 # Electron main process
│   └── preload.js              # Electron preload script (IPC bridge)
├── public/                      # Static assets
├── src/
│   ├── assets/                 # Images, fonts, etc.
│   ├── components/             # Reusable Vue components
│   ├── layouts/                # Layout components
│   │   └── MainLayout.vue      # Main app layout with sidebar
│   ├── plugins/                # Vue plugins
│   │   └── vuetify.js          # Vuetify configuration
│   ├── router/                 # Vue Router
│   │   └── index.js            # Route definitions + auth guards
│   ├── services/               # Services layer
│   │   ├── api.js              # Axios HTTP client with interceptors
│   │   └── db.js               # IndexedDB wrapper (Dexie)
│   ├── stores/                 # Pinia state management
│   │   ├── auth.js             # Authentication store
│   │   ├── employee.js         # Employee management store
│   │   ├── payroll.js          # Payroll processing store
│   │   ├── attendance.js       # Attendance tracking store
│   │   └── sync.js             # Offline sync queue store
│   ├── styles/                 # Global styles
│   │   ├── main.scss           # Main stylesheet
│   │   └── settings.scss       # Vuetify theme settings
│   ├── views/                  # Page components
│   │   ├── auth/               # Authentication views
│   │   │   └── LoginView.vue
│   │   ├── employees/          # Employee management
│   │   │   ├── EmployeeListView.vue
│   │   │   ├── EmployeeDetailView.vue
│   │   │   └── EmployeeFormView.vue
│   │   ├── attendance/         # Attendance management
│   │   │   └── AttendanceView.vue
│   │   ├── payroll/            # Payroll processing
│   │   │   ├── PayrollListView.vue
│   │   │   ├── PayrollFormView.vue
│   │   │   ├── PayrollDetailView.vue
│   │   │   └── PayrollProcessView.vue
│   │   ├── benefits/           # Benefits management
│   │   │   ├── AllowancesView.vue
│   │   │   ├── LoansView.vue
│   │   │   └── DeductionsView.vue
│   │   ├── reports/            # Reports
│   │   │   └── ReportsView.vue
│   │   ├── settings/           # System settings
│   │   │   └── SettingsView.vue
│   │   ├── DashboardView.vue   # Main dashboard
│   │   └── NotFoundView.vue    # 404 page
│   ├── App.vue                 # Root component
│   └── main.js                 # Application entry point
├── .env.example                # Environment variables template
├── index.html                  # HTML entry point
├── package.json                # Dependencies and scripts
├── vite.config.js              # Vite configuration
└── README.md                   # Frontend documentation
```

---

## 🎨 Tech Stack

### Core Framework

- **Vue 3.4** - Composition API, script setup
- **Vite 5.0** - Build tool with HMR
- **Vuetify 3.5** - Material Design component library
- **Pinia 2.1** - State management (Vuex successor)
- **Vue Router 4.2** - Client-side routing

### HTTP & Storage

- **Axios 1.6** - HTTP client with interceptors
- **Dexie 3.2** - IndexedDB wrapper for offline storage

### UI & Utilities

- **Chart.js 4.4** + **vue-chartjs 5.3** - Data visualization
- **date-fns 3.0** - Date manipulation
- **vue-toastification 2.0** - Toast notifications
- **@mdi/font 7.4** - Material Design Icons

### Reports & Export

- **jsPDF 2.5** - PDF generation
- **jspdf-autotable 3.8** - PDF tables
- **xlsx 0.18** - Excel export

### Desktop (Electron)

- **Electron 28.1** - Desktop app wrapper
- **electron-builder 24.9** - App packaging
- **concurrently 8.2** - Run dev server + Electron
- **wait-on 7.2** - Wait for dev server

---

## 🔐 Authentication Flow

### Login Process (`stores/auth.js`)

```javascript
// 1. User submits credentials
await authStore.login({ email, password, remember });

// 2. API call to /api/login
const response = await api.post("/login", credentials);

// 3. Store token and user data
token.value = response.data.token;
user.value = response.data.user;
localStorage.setItem("token", token.value);

// 4. Set Authorization header for future requests
api.defaults.headers.common["Authorization"] = `Bearer ${token.value}`;

// 5. Router redirects to dashboard
router.push("/");
```

### Route Guards (`router/index.js`)

```javascript
router.beforeEach(async (to, from, next) => {
  if (to.meta.requiresAuth !== false) {
    if (!authStore.isAuthenticated) {
      return next({ name: "login", query: { redirect: to.fullPath } });
    }
  }
  next();
});
```

### Auto-reauth on Refresh (`App.vue`)

```javascript
onMounted(async () => {
  await authStore.checkAuth(); // Validates token with /api/user
});
```

---

## 📦 State Management (Pinia Stores)

### Auth Store (`stores/auth.js`)

**State:**

- `user` - Current user object
- `token` - JWT token
- `loading` - Loading state

**Getters:**

- `isAuthenticated` - Boolean auth status
- `userRole` - User role (admin, accountant, etc.)
- `isAdmin` - Is user admin
- `isAccountant` - Is user admin or accountant

**Actions:**

- `login(credentials)` - Authenticate user
- `logout()` - Clear session
- `checkAuth()` - Validate token
- `fetchUser()` - Refresh user data

### Employee Store (`stores/employee.js`)

**State:**

- `employees` - Array of employees
- `currentEmployee` - Currently selected employee
- `departments` - All departments
- `locations` - All job sites
- `pagination` - Pagination metadata

**Actions:**

- `fetchEmployees(params)` - Get employee list with filters
- `fetchEmployee(id)` - Get employee details
- `createEmployee(data)` - Add new employee
- `updateEmployee(id, data)` - Update employee
- `deleteEmployee(id)` - Delete employee
- `fetchDepartments()` - Get all departments
- `fetchLocations()` - Get all job sites
- `fetchEmployeeAllowances(employeeId)` - Get allowances
- `fetchEmployeeLoans(employeeId)` - Get loans
- `fetchEmployeeDeductions(employeeId)` - Get deductions

### Payroll Store (`stores/payroll.js`)

**State:**

- `payrolls` - Array of payroll periods
- `currentPayroll` - Currently selected payroll
- `payrollItems` - Items for current payroll
- `processing` - Processing state

**Actions:**

- `fetchPayrolls(params)` - Get payroll list
- `fetchPayroll(id)` - Get payroll details
- `createPayroll(data)` - Create new payroll period
- `processPayroll(id, employeeIds)` - Calculate payroll
- `checkPayroll(id)` - 1st approval (checker)
- `recommendPayroll(id)` - 2nd approval (recommender)
- `approvePayroll(id)` - 3rd approval (approver)
- `markPayrollPaid(id)` - Mark as paid (final)
- `fetchPayrollSummary(id)` - Get statistics
- `fetchPayrollItems(payrollId)` - Get payroll items
- `deletePayroll(id)` - Cancel payroll

### Attendance Store (`stores/attendance.js`)

**State:**

- `attendances` - Array of attendance records
- `currentAttendance` - Currently selected record

**Actions:**

- `fetchAttendances(params)` - Get attendance list
- `fetchAttendance(id)` - Get attendance details
- `createAttendance(data)` - Manual entry
- `updateAttendance(id, data)` - Edit attendance
- `deleteAttendance(id)` - Delete record
- `importBiometric(data)` - Bulk import from biometric
- `approveAttendance(id)` - Approve correction
- `rejectAttendance(id, reason)` - Reject correction
- `fetchEmployeeSummary(employeeId, params)` - Get employee summary

### Sync Store (`stores/sync.js`)

**State:**

- `isSyncing` - Currently syncing flag
- `isOnline` - Online/offline status
- `pendingChanges` - Number of pending changes
- `lastSyncTime` - Last successful sync timestamp

**Actions:**

- `startSync()` - Start auto-sync (every 5 minutes)
- `stopSync()` - Stop auto-sync
- `syncNow()` - Immediate sync
- `queueChange(action, modelType, modelId, data)` - Add to queue
- `updateOnlineStatus(status)` - Update online status

---

## 🌐 API Integration

### Axios Configuration (`services/api.js`)

**Base URL:** `http://localhost:8000/api` (configurable via `VITE_API_URL`)

**Request Interceptor:**

```javascript
// Add JWT token to all requests
config.headers.Authorization = `Bearer ${token}`;
```

**Response Interceptor:**

```javascript
// Handle errors globally
switch (status) {
  case 401: // Unauthorized - redirect to login
  case 403: // Forbidden - show error
  case 404: // Not found
  case 422: // Validation errors
  case 500: // Server error
}
```

**Toast Notifications:**

- Success: Green toast with checkmark
- Error: Red toast with error message
- Info: Blue toast for informational messages
- Warning: Orange toast for warnings

---

## 💾 Offline Support (IndexedDB)

### Database Schema (`services/db.js`)

**Tables:**

- `syncQueue` - Pending changes to sync
- `employees` - Cached employee data
- `attendance` - Cached attendance records
- `payrolls` - Cached payroll periods
- `payrollItems` - Cached payroll items
- `departments` - Cached departments
- `locations` - Cached job sites
- `settings` - App settings
- `lastSync` - Last sync timestamps

### Sync Queue Process

1. **User makes change while offline**

   ```javascript
   await dbHelpers.addToSyncQueue("update", "employees", 123, data, userId);
   ```

2. **Change is queued in IndexedDB**

   ```javascript
   {
     action: 'update',
     model_type: 'employees',
     model_id: 123,
     data: {...},
     status: 'pending',
     attempts: 0,
     created_at: new Date()
   }
   ```

3. **When online, sync runs every 5 minutes**

   ```javascript
   syncStore.startSync(); // Auto-sync every 5 minutes
   ```

4. **Each pending change is processed**

   ```javascript
   await api.put(`/employees/123`, data);
   await db.syncQueue.update(changeId, { status: "completed" });
   ```

5. **Failed changes are retried**
   ```javascript
   attempts++
   if (attempts > 3) {
     status = 'failed'
     show notification to user
   }
   ```

---

## 🎭 Component Structure

### MainLayout (`layouts/MainLayout.vue`)

**Features:**

- Left sidebar navigation
- Top app bar with sync status
- Online/offline indicator
- Pending changes badge
- User menu
- Collapsible rail mode

**Menu Items:**

- Dashboard
- Employees
- Attendance
- Payroll
- Allowances
- Loans
- Deductions
- Reports
- Settings

### LoginView (`views/auth/LoginView.vue`)

**Features:**

- Email/password form
- Remember me checkbox
- Form validation
- Loading state
- Error messages
- Redirect to intended page after login

### DashboardView (`views/DashboardView.vue`)

**Statistics Cards:**

- Total Employees (active count)
- This Period Payroll (total amount)
- Attendance Today (present count)
- Pending Approvals (count)

**Quick Actions:**

- Add Employee
- Record Attendance
- Create Payroll
- View Reports

**Recent Activity:**

- Recent Attendance (last 10 records)
- Recent Payrolls (last 5 payrolls)

### EmployeeListView (`views/employees/EmployeeListView.vue`)

**Features:**

- Data table with sorting
- Search by name/employee number
- Filter by department, status, employment type
- Pagination (20 per page)
- Click row to view details
- Action buttons (view, edit)
- Color-coded status chips

---

## 🖥️ Desktop App (Electron)

### Main Process (`electron/main.js`)

**Window Configuration:**

- Size: 1400x900 (min: 1024x768)
- Title: "Construction Payroll System"
- Auto-hide menu bar
- Context isolation enabled
- Node integration disabled (security)

**IPC Handlers:**

- `app-version` - Get app version
- `app-path` - Get system paths
- `is-online` - Check internet connection
- `print-pdf` - Print to PDF
- `show-save-dialog` - Save file dialog
- `show-open-dialog` - Open file dialog
- `write-file` - Write file to disk
- `read-file` - Read file from disk

### Preload Script (`electron/preload.js`)

**Exposed APIs:**

```javascript
window.electron = {
  getAppVersion(),
  getAppPath(name),
  isOnline(),
  showSaveDialog(options),
  showOpenDialog(options),
  writeFile(filePath, data),
  readFile(filePath),
  printPDF(options),
  platform,
  isElectron
}
```

### Building Desktop App

```bash
# Development
npm run electron:dev

# Build for Windows
npm run electron:build -- --win

# Build for Mac
npm run electron:build -- --mac

# Build for Linux
npm run electron:build -- --linux
```

**Output:**

- Windows: `.exe` installer + portable
- Mac: `.dmg` disk image
- Linux: `.AppImage` portable app

---

## 🎨 Theming & Styling

### Vuetify Theme (`plugins/vuetify.js`)

**Construction Company Colors:**

```javascript
{
  primary: '#1976D2',    // Blue
  secondary: '#424242',   // Dark gray
  accent: '#FF9800',      // Orange (construction)
  error: '#FF5252',       // Red
  info: '#2196F3',        // Light blue
  success: '#4CAF50',     // Green
  warning: '#FFC107',     // Yellow
  background: '#F5F5F5',  // Light gray
  surface: '#FFFFFF',     // White
}
```

### Global Styles (`styles/main.scss`)

**Utility Classes:**

- `.text-truncate` - Ellipsis overflow
- `.cursor-pointer` - Pointer cursor
- `.status-badge` - Status chips with colors
- `.card-header` - Card header with title + actions
- `.offline-indicator` - Fixed offline banner
- `.sync-indicator` - Sync progress indicator

**Print Styles:**

- `.no-print` - Hide in print mode
- Hide navigation drawer and app bar
- Remove padding from main content

---

## 📱 Responsive Design

### Breakpoints (Vuetify)

- **xs** - Extra small (< 600px) - Mobile portrait
- **sm** - Small (600-960px) - Mobile landscape
- **md** - Medium (960-1264px) - Tablet
- **lg** - Large (1264-1904px) - Desktop
- **xl** - Extra large (> 1904px) - Large desktop

### Mobile Optimizations

- Responsive grid (v-row/v-col)
- Collapsible sidebar on mobile
- Touch-friendly buttons (min 48px)
- Swipeable cards
- Mobile-friendly data tables (scroll)
- Responsive typography

---

## 🔧 Development Setup

### Prerequisites

```bash
Node.js 18+ and npm
Backend API running on http://localhost:8000
```

### Installation

```bash
cd frontend

# Install dependencies
npm install

# Copy environment file
cp .env.example .env

# Start development server
npm run dev
```

**Dev Server:** http://localhost:5173

### Environment Variables

```env
VITE_API_URL=http://localhost:8000/api
VITE_APP_NAME="Construction Payroll System"
VITE_APP_VERSION=1.0.0
VITE_ENABLE_OFFLINE_MODE=true
VITE_ENABLE_SYNC=true
```

---

## 📦 Building for Production

### Web App

```bash
# Build for production
npm run build

# Preview production build
npm run preview
```

**Output:** `dist/` directory

### Desktop App

```bash
# Build Electron app
npm run electron:build
```

**Output:** `dist-electron/` directory

---

## 🧪 Testing (To Be Implemented)

### Unit Tests (Vitest)

- Component tests
- Store tests
- Service tests

### E2E Tests (Playwright)

- Login flow
- Employee CRUD
- Payroll processing
- Attendance tracking

---

## 🚀 Deployment

### Web App (Nginx)

```nginx
server {
    listen 80;
    server_name payroll.example.com;
    root /var/www/payroll/dist;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    location /api {
        proxy_pass http://localhost:8000;
    }
}
```

### Desktop App

- Distribute via company intranet
- Auto-update via electron-updater
- Code signing for Windows/Mac

---

## 📊 Features Summary

### ✅ Implemented

- **Authentication**

  - Login with email/password
  - JWT token management
  - Remember me functionality
  - Auto-logout on 401
  - Route guards

- **Dashboard**

  - Statistics cards (employees, payroll, attendance)
  - Quick actions
  - Recent activity feed

- **Employee Management**

  - List with search and filters
  - View employee details (placeholder)
  - Add/edit employee (placeholder)
  - Department and location management

- **Payroll**

  - List payroll periods (placeholder)
  - Create payroll (placeholder)
  - Process payroll (placeholder)
  - Construction workflow (placeholder)

- **Attendance**

  - Attendance management (placeholder)

- **Benefits**

  - Allowances (placeholder)
  - Loans (placeholder)
  - Deductions (placeholder)

- **Reports**

  - 6 report types listed
  - PDF/Excel export (to be implemented)

- **Settings**

  - Company info (placeholder)
  - Payroll settings (placeholder)
  - Government rates (placeholder)
  - User management (placeholder)

- **Offline Support**

  - IndexedDB caching
  - Sync queue
  - Auto-sync when online
  - Offline indicator

- **Desktop App**
  - Electron wrapper
  - IPC communication
  - File dialogs
  - Print to PDF

### 🚧 To Be Implemented

- Employee form with all fields
- Employee details with tabs (info, benefits, attendance, payroll)
- Payroll processing interface with calculation display
- Attendance calendar view
- Biometric import interface
- Allowances/loans/deductions CRUD
- Reports generation (PDF/Excel)
- Settings forms
- User management
- Notifications system
- Audit log viewer
- 13th month pay calculator
- Leave management

---

## 🎯 Key Features

### Construction-Specific

1. **Multi-Site Management**

   - Track employees across multiple job sites
   - Site-specific allowances
   - Site transfer tracking

2. **Construction Allowances**

   - Water allowance
   - COLA (Cost of Living Allowance)
   - Site allowance
   - Hazard pay
   - Meal allowance

3. **Construction Deductions**

   - PPE (Personal Protective Equipment)
   - Tools and equipment
   - Uniform
   - Cash advances

4. **5-Stage Approval Workflow**
   - Prepare (Payroll staff)
   - Check (Checker)
   - Recommend (Recommender)
   - Approve (Approver)
   - Mark Paid (Cashier)

### Philippine Compliance

1. **Government Contributions**

   - SSS (2025 rates)
   - PhilHealth (4% premium)
   - Pag-IBIG (1-2% with cap)

2. **Tax Computation**

   - TRAIN Law 2025
   - Progressive tax brackets
   - Withholding tax

3. **Required Reports**
   - BIR Form 1601-C (monthly)
   - SSS R3 (monthly)
   - PhilHealth RF-1 (monthly)
   - Pag-IBIG MCRF (monthly)

---

## 📝 Code Quality

### Best Practices

- **Composition API** - Modern Vue 3 syntax
- **Script Setup** - Cleaner component code
- **Pinia** - Type-safe state management
- **Async/Await** - Clean async code
- **Error Handling** - Try/catch with toast notifications
- **Loading States** - User feedback during operations
- **Optimistic Updates** - Instant UI feedback
- **Debouncing** - Search input optimization
- **Lazy Loading** - Route-based code splitting

### Performance

- **Vite** - Fast HMR and builds
- **Tree Shaking** - Remove unused code
- **Code Splitting** - Smaller initial bundle
- **Lazy Routes** - Load on demand
- **Vuetify Treeshaking** - Import only used components
- **Image Optimization** - Lazy loading images
- **IndexedDB** - Client-side caching

---

## 🐛 Known Issues

1. Employee form not fully implemented (placeholder)
2. Payroll processing interface incomplete
3. Attendance calendar view not implemented
4. Reports generation not implemented
5. Settings forms not implemented

---

## 🔮 Future Enhancements

1. **Mobile App** (React Native / Flutter)
2. **Biometric Device Integration**
3. **SMS Notifications** (Payslip alerts)
4. **Email Reports** (Auto-send)
5. **Advanced Analytics** (Charts, trends)
6. **Multi-language Support** (English/Tagalog)
7. **Dark Mode**
8. **PWA Support** (Install as app)
9. **Real-time Updates** (WebSocket)
10. **AI-powered Insights** (Payroll predictions)

---

## 📞 Support

For issues or questions:

- Email: support@payroll.example.com
- Internal Helpdesk: ext. 123

---

**Version:** 1.0.0  
**Last Updated:** December 24, 2025  
**Status:** Frontend Foundation Complete ✅
