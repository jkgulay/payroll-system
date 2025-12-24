# Construction Payroll System - Frontend

Vue 3 + Vuetify 3 frontend for the Construction Payroll Management System.

## Features

- 🎨 Modern UI with Vuetify 3 Material Design
- 🔐 Secure authentication with JWT tokens
- 📱 Responsive design for desktop and mobile
- 💾 Offline support with IndexedDB
- 🔄 Auto-sync when online
- 📊 Real-time dashboard with statistics
- 🖨️ Print-ready payslips
- 📈 Charts and reports

## Tech Stack

- **Vue 3** - Progressive JavaScript framework
- **Vuetify 3** - Material Design component library
- **Pinia** - State management
- **Vue Router** - Routing
- **Axios** - HTTP client
- **Dexie** - IndexedDB wrapper for offline storage
- **Chart.js** - Data visualization
- **jsPDF** - PDF generation
- **Vite** - Build tool

## Prerequisites

- Node.js 18+ and npm/yarn
- Backend API running on http://localhost:8000

## Installation

```bash
# Install dependencies
npm install

# Copy environment file
cp .env.example .env

# Start development server
npm run dev
```

The app will be available at http://localhost:5173

## Available Scripts

```bash
# Development server with hot reload
npm run dev

# Build for production
npm run build

# Preview production build
npm run preview

# Run with Electron (desktop app)
npm run electron:dev

# Build Electron app
npm run electron:build

# Lint and fix files
npm run lint
```

## Project Structure

```
frontend/
├── src/
│   ├── assets/          # Static assets (images, fonts)
│   ├── components/      # Reusable Vue components
│   ├── layouts/         # Layout components
│   ├── plugins/         # Vuetify and other plugins
│   ├── router/          # Vue Router configuration
│   ├── services/        # API and database services
│   ├── stores/          # Pinia stores
│   ├── styles/          # Global styles
│   ├── views/           # Page components
│   ├── App.vue          # Root component
│   └── main.js          # App entry point
├── public/              # Public static assets
├── electron/            # Electron main process files
├── .env.example         # Environment variables template
├── index.html           # HTML template
├── package.json         # Dependencies and scripts
└── vite.config.js       # Vite configuration
```

## Stores (State Management)

### Auth Store (`stores/auth.js`)

- User authentication and session management
- Login/logout functionality
- Role-based access control

### Employee Store (`stores/employee.js`)

- Employee CRUD operations
- Department and location management
- Employee benefits (allowances, loans, deductions)

### Payroll Store (`stores/payroll.js`)

- Payroll creation and processing
- Construction workflow (check → recommend → approve → pay)
- Payroll items and calculations

### Attendance Store (`stores/attendance.js`)

- Attendance tracking
- Biometric data import
- Approval workflow

### Sync Store (`stores/sync.js`)

- Offline change tracking
- Auto-sync when online
- Sync queue management

## Offline Support

The app uses IndexedDB (via Dexie) to store data locally for offline access:

- Employees, departments, and locations are cached
- Changes made offline are queued in `syncQueue` table
- Auto-sync runs every 5 minutes when online
- Manual sync available via sync button

## API Integration

All API calls go through `services/api.js` which:

- Adds JWT token to requests
- Handles response errors
- Shows toast notifications
- Redirects to login on 401

## Environment Variables

Create a `.env` file from `.env.example`:

```env
VITE_API_URL=http://localhost:8000/api
VITE_APP_NAME="Construction Payroll System"
VITE_ENABLE_OFFLINE_MODE=true
```

## Building for Production

```bash
# Build web app
npm run build

# Build Electron desktop app
npm run electron:build
```

The web build will be in `dist/` and Electron builds in `dist-electron/`.

## Desktop App (Electron)

The system can run as a standalone desktop application:

```bash
# Development
npm run electron:dev

# Build for Windows/Mac/Linux
npm run electron:build
```

Features:

- No browser required
- System tray integration
- Auto-updates
- Better offline support

## License

Proprietary - Construction Company Management System
