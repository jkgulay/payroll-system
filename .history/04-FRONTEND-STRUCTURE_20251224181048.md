# Vue 3 + Vuetify Frontend Structure

## Project Structure

```
frontend/
├── public/
│   ├── favicon.ico
│   └── index.html
├── src/
│   ├── assets/
│   │   ├── images/
│   │   ├── styles/
│   │   └── fonts/
│   ├── components/
│   │   ├── common/
│   │   │   ├── AppHeader.vue
│   │   │   ├── AppSidebar.vue
│   │   │   ├── AppFooter.vue
│   │   │   ├── DataTable.vue
│   │   │   ├── FormDialog.vue
│   │   │   ├── ConfirmDialog.vue
│   │   │   └── LoadingOverlay.vue
│   │   ├── employee/
│   │   │   ├── EmployeeList.vue
│   │   │   ├── EmployeeForm.vue
│   │   │   ├── EmployeeCard.vue
│   │   │   └── EmployeeImport.vue
│   │   ├── attendance/
│   │   │   ├── AttendanceCalendar.vue
│   │   │   ├── AttendanceForm.vue
│   │   │   ├── AttendanceSummary.vue
│   │   │   └── CorrectionRequestForm.vue
│   │   ├── payroll/
│   │   │   ├── PayrollList.vue
│   │   │   ├── PayrollForm.vue
│   │   │   ├── PayrollItemsTable.vue
│   │   │   ├── PayslipViewer.vue
│   │   │   └── PayrollSummary.vue
│   │   └── reports/
│   │       ├── ReportFilters.vue
│   │       ├── PayrollReport.vue
│   │       └── ContributionReport.vue
│   ├── views/
│   │   ├── auth/
│   │   │   ├── Login.vue
│   │   │   └── ForgotPassword.vue
│   │   ├── dashboard/
│   │   │   ├── AdminDashboard.vue
│   │   │   ├── AccountantDashboard.vue
│   │   │   └── EmployeeDashboard.vue
│   │   ├── employees/
│   │   │   ├── EmployeeIndex.vue
│   │   │   ├── EmployeeCreate.vue
│   │   │   ├── EmployeeEdit.vue
│   │   │   └── EmployeeView.vue
│   │   ├── attendance/
│   │   │   ├── AttendanceIndex.vue
│   │   │   ├── AttendanceManagement.vue
│   │   │   └── CorrectionRequests.vue
│   │   ├── payroll/
│   │   │   ├── PayrollIndex.vue
│   │   │   ├── PayrollCreate.vue
│   │   │   ├── PayrollView.vue
│   │   │   └── PayrollProcess.vue
│   │   ├── recruitment/
│   │   │   ├── ApplicantIndex.vue
│   │   │   └── ApplicantView.vue
│   │   ├── reports/
│   │   │   └── ReportsIndex.vue
│   │   └── settings/
│   │       ├── CompanySettings.vue
│   │       ├── ContributionTables.vue
│   │       ├── Holidays.vue
│   │       └── UserManagement.vue
│   ├── stores/
│   │   ├── auth.js
│   │   ├── employee.js
│   │   ├── attendance.js
│   │   ├── payroll.js
│   │   ├── settings.js
│   │   ├── sync.js
│   │   └── notifications.js
│   ├── router/
│   │   └── index.js
│   ├── services/
│   │   ├── api.js
│   │   ├── authService.js
│   │   ├── employeeService.js
│   │   ├── attendanceService.js
│   │   ├── payrollService.js
│   │   ├── reportService.js
│   │   ├── syncService.js
│   │   └── offlineService.js
│   ├── utils/
│   │   ├── validators.js
│   │   ├── formatters.js
│   │   ├── dateHelpers.js
│   │   ├── payrollHelpers.js
│   │   └── exportHelpers.js
│   ├── plugins/
│   │   ├── vuetify.js
│   │   └── pinia.js
│   ├── db/
│   │   ├── schema.js         // IndexedDB schema
│   │   └── syncQueue.js      // Offline sync queue
│   ├── App.vue
│   └── main.js
├── electron/
│   ├── main.js               // Electron main process
│   ├── preload.js            // Preload script
│   └── ipc-handlers.js       // IPC communication
├── package.json
├── vite.config.js
├── electron-builder.json     // Electron packaging config
└── .env
```

---

## Main Application Entry

### src/main.js

```javascript
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import vuetify from './plugins/vuetify'
import { initDB } from './db/schema'

const app = createApp(App)

app.use(createPinia())
app.use(router)
app.use(vuetify)

// Initialize IndexedDB for offline support
initDB().then(() => {
  console.log('IndexedDB initialized')
})

app.mount('#app')
```

---

### src/App.vue

```vue
<template>
  <v-app>
    <v-navigation-drawer
      v-if="authStore.isAuthenticated"
      v-model="drawer"
      app
      :rail="rail"
      @click="rail = false"
    >
      <AppSidebar @toggle-rail="rail = !rail" />
    </v-navigation-drawer>

    <v-app-bar v-if="authStore.isAuthenticated" app>
      <AppHeader @toggle-drawer="drawer = !drawer" />
    </v-app-bar>

    <v-main>
      <v-container fluid>
        <router-view />
      </v-container>
    </v-main>

    <!-- Offline indicator -->
    <v-snackbar
      v-model="!isOnline"
      :timeout="-1"
      color="warning"
      location="top"
    >
      <v-icon>mdi-wifi-off</v-icon>
      You are currently offline. Changes will sync when online.
    </v-snackbar>

    <!-- Sync progress -->
    <v-snackbar
      v-model="syncStore.isSyncing"
      :timeout="-1"
      color="info"
      location="bottom right"
    >
      <v-progress-circular
        indeterminate
        size="20"
        class="mr-2"
      />
      Syncing {{ syncStore.syncProgress }}...
    </v-snackbar>

    <!-- Global loading -->
    <LoadingOverlay v-if="loading" />
  </v-app>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useAuthStore } from '@/stores/auth'
import { useSyncStore } from '@/stores/sync'
import AppHeader from '@/components/common/AppHeader.vue'
import AppSidebar from '@/components/common/AppSidebar.vue'
import LoadingOverlay from '@/components/common/LoadingOverlay.vue'

const authStore = useAuthStore()
const syncStore = useSyncStore()

const drawer = ref(true)
const rail = ref(false)
const isOnline = ref(navigator.onLine)
const loading = ref(false)

onMounted(() => {
  // Listen for online/offline events
  window.addEventListener('online', () => {
    isOnline.value = true
    syncStore.startSync()
  })
  
  window.addEventListener('offline', () => {
    isOnline.value = false
  })
  
  // Start background sync if online
  if (isOnline.value) {
    syncStore.startSync()
  }
})
</script>
```

---

## Pinia Stores

### stores/auth.js

```javascript
import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import authService from '@/services/authService'
import router from '@/router'

export const useAuthStore = defineStore('auth', () => {
  const user = ref(null)
  const token = ref(localStorage.getItem('token'))
  const permissions = ref([])
  
  const isAuthenticated = computed(() => !!token.value)
  const userRole = computed(() => user.value?.role)
  
  const isAdmin = computed(() => userRole.value === 'admin')
  const isAccountant = computed(() => userRole.value === 'accountant')
  const isEmployee = computed(() => userRole.value === 'employee')
  
  async function login(credentials) {
    try {
      const response = await authService.login(credentials)
      
      token.value = response.token
      user.value = response.user
      permissions.value = response.permissions
      
      localStorage.setItem('token', response.token)
      localStorage.setItem('user', JSON.stringify(response.user))
      
      router.push('/dashboard')
    } catch (error) {
      throw error
    }
  }
  
  async function logout() {
    try {
      await authService.logout()
    } catch (error) {
      console.error('Logout error:', error)
    } finally {
      token.value = null
      user.value = null
      permissions.value = []
      
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      
      router.push('/login')
    }
  }
  
  async function fetchUser() {
    try {
      const response = await authService.me()
      user.value = response.user
      permissions.value = response.permissions
    } catch (error) {
      await logout()
    }
  }
  
  function hasPermission(permission) {
    return permissions.value.includes(permission)
  }
  
  function can(permission) {
    return hasPermission(permission) || isAdmin.value
  }
  
  return {
    user,
    token,
    permissions,
    isAuthenticated,
    userRole,
    isAdmin,
    isAccountant,
    isEmployee,
    login,
    logout,
    fetchUser,
    hasPermission,
    can
  }
})
```

---

### stores/payroll.js

```javascript
import { defineStore } from 'pinia'
import { ref } from 'vue'
import payrollService from '@/services/payrollService'

export const usePayrollStore = defineStore('payroll', () => {
  const payrolls = ref([])
  const currentPayroll = ref(null)
  const payrollItems = ref([])
  const loading = ref(false)
  
  async function fetchPayrolls(params = {}) {
    loading.value = true
    try {
      const response = await payrollService.getAll(params)
      payrolls.value = response.data
      return response
    } catch (error) {
      console.error('Error fetching payrolls:', error)
      throw error
    } finally {
      loading.value = false
    }
  }
  
  async function fetchPayroll(id) {
    loading.value = true
    try {
      const response = await payrollService.getById(id)
      currentPayroll.value = response.data
      return response.data
    } catch (error) {
      console.error('Error fetching payroll:', error)
      throw error
    } finally {
      loading.value = false
    }
  }
  
  async function createPayroll(data) {
    loading.value = true
    try {
      const response = await payrollService.create(data)
      payrolls.value.unshift(response.data)
      return response.data
    } catch (error) {
      console.error('Error creating payroll:', error)
      throw error
    } finally {
      loading.value = false
    }
  }
  
  async function processPayroll(id) {
    loading.value = true
    try {
      const response = await payrollService.process(id)
      await fetchPayroll(id)
      return response
    } catch (error) {
      console.error('Error processing payroll:', error)
      throw error
    } finally {
      loading.value = false
    }
  }
  
  async function approvePayroll(id) {
    loading.value = true
    try {
      const response = await payrollService.approve(id)
      await fetchPayroll(id)
      return response
    } catch (error) {
      console.error('Error approving payroll:', error)
      throw error
    } finally {
      loading.value = false
    }
  }
  
  async function fetchPayrollItems(payrollId) {
    loading.value = true
    try {
      const response = await payrollService.getItems(payrollId)
      payrollItems.value = response.data
      return response.data
    } catch (error) {
      console.error('Error fetching payroll items:', error)
      throw error
    } finally {
      loading.value = false
    }
  }
  
  async function generatePayslips(payrollId) {
    loading.value = true
    try {
      const response = await payrollService.generatePayslips(payrollId)
      return response
    } catch (error) {
      console.error('Error generating payslips:', error)
      throw error
    } finally {
      loading.value = false
    }
  }
  
  return {
    payrolls,
    currentPayroll,
    payrollItems,
    loading,
    fetchPayrolls,
    fetchPayroll,
    createPayroll,
    processPayroll,
    approvePayroll,
    fetchPayrollItems,
    generatePayslips
  }
})
```

---

### stores/sync.js (Offline Support)

```javascript
import { defineStore } from 'pinia'
import { ref } from 'vue'
import { db } from '@/db/schema'
import syncService from '@/services/syncService'

export const useSyncStore = defineStore('sync', () => {
  const isSyncing = ref(false)
  const syncProgress = ref('')
  const pendingChanges = ref([])
  const lastSyncTime = ref(null)
  
  async function addToSyncQueue(entity, action, data) {
    const change = {
      id: Date.now(),
      entity,
      action,
      data,
      timestamp: new Date().toISOString(),
      synced: false
    }
    
    await db.syncQueue.add(change)
    pendingChanges.value.push(change)
  }
  
  async function startSync() {
    if (isSyncing.value || !navigator.onLine) return
    
    isSyncing.value = true
    
    try {
      // Get pending changes
      const queue = await db.syncQueue.where('synced').equals(false).toArray()
      
      if (queue.length === 0) {
        isSyncing.value = false
        return
      }
      
      let synced = 0
      
      for (const change of queue) {
        try {
          syncProgress.value = `${synced + 1} of ${queue.length}`
          
          await syncService.sync(change)
          
          // Mark as synced
          await db.syncQueue.update(change.id, { synced: true })
          synced++
          
        } catch (error) {
          console.error('Sync error for change:', change, error)
          // Continue with next change
        }
      }
      
      // Clean up old synced records (older than 7 days)
      const weekAgo = new Date()
      weekAgo.setDate(weekAgo.getDate() - 7)
      await db.syncQueue
        .where('synced').equals(true)
        .and(item => new Date(item.timestamp) < weekAgo)
        .delete()
      
      pendingChanges.value = await db.syncQueue
        .where('synced').equals(false)
        .toArray()
      
      lastSyncTime.value = new Date().toISOString()
      
    } catch (error) {
      console.error('Sync error:', error)
    } finally {
      isSyncing.value = false
      syncProgress.value = ''
    }
  }
  
  async function loadPendingChanges() {
    pendingChanges.value = await db.syncQueue
      .where('synced').equals(false)
      .toArray()
  }
  
  return {
    isSyncing,
    syncProgress,
    pendingChanges,
    lastSyncTime,
    addToSyncQueue,
    startSync,
    loadPendingChanges
  }
})
```

---

## Services

### services/api.js (Axios Configuration)

```javascript
import axios from 'axios'
import router from '@/router'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL || 'http://localhost:8000/api',
  timeout: 30000,
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

// Request interceptor
api.interceptors.request.use(
  (config) => {
    const token = localStorage.getItem('token')
    if (token) {
      config.headers.Authorization = `Bearer ${token}`
    }
    return config
  },
  (error) => {
    return Promise.reject(error)
  }
)

// Response interceptor
api.interceptors.response.use(
  (response) => {
    return response.data
  },
  (error) => {
    if (error.response) {
      // Handle specific error codes
      if (error.response.status === 401) {
        localStorage.removeItem('token')
        localStorage.removeItem('user')
        router.push('/login')
      } else if (error.response.status === 403) {
        // Unauthorized access
        console.error('Forbidden:', error.response.data.message)
      }
    } else if (error.request) {
      // Network error
      console.error('Network error:', error.message)
    }
    
    return Promise.reject(error)
  }
)

export default api
```

---

### services/payrollService.js

```javascript
import api from './api'

export default {
  getAll(params) {
    return api.get('/payroll', { params })
  },
  
  getById(id) {
    return api.get(`/payroll/${id}`)
  },
  
  create(data) {
    return api.post('/payroll', data)
  },
  
  update(id, data) {
    return api.put(`/payroll/${id}`, data)
  },
  
  delete(id) {
    return api.delete(`/payroll/${id}`)
  },
  
  process(id) {
    return api.post(`/payroll/${id}/process`)
  },
  
  approve(id) {
    return api.post(`/payroll/${id}/approve`)
  },
  
  recalculate(id) {
    return api.post(`/payroll/${id}/recalculate`)
  },
  
  getItems(id) {
    return api.get(`/payroll/${id}/items`)
  },
  
  generatePayslips(id) {
    return api.post(`/payroll/${id}/generate-payslips`)
  },
  
  export(id, format = 'excel') {
    return api.get(`/payroll/${id}/export`, {
      params: { format },
      responseType: 'blob'
    })
  }
}
```

---

## Router Configuration

### router/index.js

```javascript
import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/auth/Login.vue'),
    meta: { guest: true }
  },
  {
    path: '/',
    redirect: '/dashboard'
  },
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: () => import('@/views/dashboard/AdminDashboard.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/employees',
    name: 'Employees',
    component: () => import('@/views/employees/EmployeeIndex.vue'),
    meta: { requiresAuth: true, roles: ['admin', 'accountant'] }
  },
  {
    path: '/employees/create',
    name: 'EmployeeCreate',
    component: () => import('@/views/employees/EmployeeCreate.vue'),
    meta: { requiresAuth: true, roles: ['admin', 'accountant'] }
  },
  {
    path: '/employees/:id',
    name: 'EmployeeView',
    component: () => import('@/views/employees/EmployeeView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/employees/:id/edit',
    name: 'EmployeeEdit',
    component: () => import('@/views/employees/EmployeeEdit.vue'),
    meta: { requiresAuth: true, roles: ['admin', 'accountant'] }
  },
  {
    path: '/attendance',
    name: 'Attendance',
    component: () => import('@/views/attendance/AttendanceIndex.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/payroll',
    name: 'Payroll',
    component: () => import('@/views/payroll/PayrollIndex.vue'),
    meta: { requiresAuth: true, roles: ['admin', 'accountant'] }
  },
  {
    path: '/payroll/create',
    name: 'PayrollCreate',
    component: () => import('@/views/payroll/PayrollCreate.vue'),
    meta: { requiresAuth: true, roles: ['admin', 'accountant'] }
  },
  {
    path: '/payroll/:id',
    name: 'PayrollView',
    component: () => import('@/views/payroll/PayrollView.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/reports',
    name: 'Reports',
    component: () => import('@/views/reports/ReportsIndex.vue'),
    meta: { requiresAuth: true, roles: ['admin', 'accountant'] }
  },
  {
    path: '/recruitment',
    name: 'Recruitment',
    component: () => import('@/views/recruitment/ApplicantIndex.vue'),
    meta: { requiresAuth: true, roles: ['admin', 'accountant'] }
  },
  {
    path: '/settings',
    name: 'Settings',
    component: () => import('@/views/settings/CompanySettings.vue'),
    meta: { requiresAuth: true, roles: ['admin'] }
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

// Navigation guard
router.beforeEach((to, from, next) => {
  const authStore = useAuthStore()
  
  if (to.meta.requiresAuth && !authStore.isAuthenticated) {
    next('/login')
  } else if (to.meta.guest && authStore.isAuthenticated) {
    next('/dashboard')
  } else if (to.meta.roles && !to.meta.roles.includes(authStore.userRole)) {
    next('/dashboard')
  } else {
    next()
  }
})

export default router
```

---

## Key Components

### components/payroll/PayrollItemsTable.vue

```vue
<template>
  <v-card>
    <v-card-title>
      Payroll Items
      <v-spacer />
      <v-text-field
        v-model="search"
        append-icon="mdi-magnify"
        label="Search"
        single-line
        hide-details
        density="compact"
      />
    </v-card-title>

    <v-data-table
      :headers="headers"
      :items="items"
      :search="search"
      :loading="loading"
      class="elevation-1"
    >
      <template #item.employee="{ item }">
        {{ item.employee?.full_name }}
      </template>

      <template #item.gross_pay="{ item }">
        {{ formatCurrency(item.gross_pay) }}
      </template>

      <template #item.total_deductions="{ item }">
        {{ formatCurrency(item.total_deductions) }}
      </template>

      <template #item.net_pay="{ item }">
        <strong>{{ formatCurrency(item.net_pay) }}</strong>
      </template>

      <template #item.actions="{ item }">
        <v-btn
          icon="mdi-file-pdf"
          size="small"
          @click="viewPayslip(item)"
        />
      </template>
    </v-data-table>
  </v-card>
</template>

<script setup>
import { ref } from 'vue'
import { formatCurrency } from '@/utils/formatters'

defineProps({
  items: {
    type: Array,
    required: true
  },
  loading: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['view-payslip'])

const search = ref('')

const headers = [
  { title: 'Employee', key: 'employee', sortable: true },
  { title: 'Days Worked', key: 'days_worked', sortable: true },
  { title: 'Basic Pay', key: 'basic_pay', sortable: true },
  { title: 'Gross Pay', key: 'gross_pay', sortable: true },
  { title: 'Deductions', key: 'total_deductions', sortable: true },
  { title: 'Net Pay', key: 'net_pay', sortable: true },
  { title: 'Actions', key: 'actions', sortable: false }
]

function viewPayslip(item) {
  emit('view-payslip', item)
}
</script>
```

---

## IndexedDB Schema

### db/schema.js

```javascript
import Dexie from 'dexie'

export const db = new Dexie('PayrollDB')

db.version(1).stores({
  employees: 'id, employee_number, email, department_id, location_id',
  attendance: 'id, employee_id, attendance_date, [employee_id+attendance_date]',
  payrolls: 'id, payroll_number, year, month, pay_period_number',
  payrollItems: 'id, payroll_id, employee_id, [payroll_id+employee_id]',
  departments: 'id, code, name',
  locations: 'id, code, name',
  syncQueue: '++id, entity, synced, timestamp',
  settings: 'key'
})

export async function initDB() {
  try {
    await db.open()
    console.log('Database opened successfully')
  } catch (error) {
    console.error('Failed to open database:', error)
  }
}

export async function cacheData(store, data) {
  try {
    if (Array.isArray(data)) {
      await db[store].bulkPut(data)
    } else {
      await db[store].put(data)
    }
  } catch (error) {
    console.error(`Error caching data to ${store}:`, error)
  }
}

export async function getCachedData(store, query = {}) {
  try {
    if (Object.keys(query).length === 0) {
      return await db[store].toArray()
    } else {
      return await db[store].where(query).toArray()
    }
  } catch (error) {
    console.error(`Error getting cached data from ${store}:`, error)
    return []
  }
}
```

---

## Utilities

### utils/formatters.js

```javascript
export function formatCurrency(value) {
  if (!value) return '₱0.00'
  return new Intl.NumberFormat('en-PH', {
    style: 'currency',
    currency: 'PHP'
  }).format(value)
}

export function formatDate(date, format = 'short') {
  if (!date) return ''
  const d = new Date(date)
  
  if (format === 'short') {
    return d.toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'short',
      day: 'numeric'
    })
  } else if (format === 'long') {
    return d.toLocaleDateString('en-US', {
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      weekday: 'long'
    })
  } else {
    return d.toISOString().split('T')[0]
  }
}

export function formatTime(time) {
  if (!time) return ''
  return new Date(`2000-01-01T${time}`).toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit'
  })
}

export function formatNumber(value, decimals = 2) {
  if (!value) return '0.00'
  return parseFloat(value).toFixed(decimals)
}
```

---

## Vuetify Configuration

### plugins/vuetify.js

```javascript
import { createVuetify } from 'vuetify'
import * as components from 'vuetify/components'
import * as directives from 'vuetify/directives'
import { aliases, mdi } from 'vuetify/iconsets/mdi'
import '@mdi/font/css/materialdesignicons.css'
import 'vuetify/styles'

const vuetify = createVuetify({
  components,
  directives,
  icons: {
    defaultSet: 'mdi',
    aliases,
    sets: {
      mdi
    }
  },
  theme: {
    defaultTheme: 'light',
    themes: {
      light: {
        colors: {
          primary: '#1976D2',
          secondary: '#424242',
          accent: '#82B1FF',
          error: '#FF5252',
          info: '#2196F3',
          success: '#4CAF50',
          warning: '#FFC107'
        }
      },
      dark: {
        colors: {
          primary: '#2196F3',
          secondary: '#424242',
          accent: '#FF4081',
          error: '#FF5252',
          info: '#2196F3',
          success: '#4CAF50',
          warning: '#FB8C00'
        }
      }
    }
  }
})

export default vuetify
```

---

## Next Steps

- See `05-PAYROLL-COMPUTATION.md` for detailed payroll calculation logic
- See `06-DEVELOPMENT-ROADMAP.md` for implementation phases
