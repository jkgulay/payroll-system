import { createRouter, createWebHistory } from "vue-router";
import { useAuthStore } from "@/stores/auth";

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: "/login",
      name: "login",
      component: () => import("@/views/auth/LoginView.vue"),
      meta: { requiresAuth: false },
    },
    {
      path: "/register",
      name: "register",
      component: () => import("@/views/auth/RegisterView.vue"),
      meta: { requiresAuth: false },
    },
    {
      path: "/",
      component: () => import("@/layouts/MainLayout.vue"),
      meta: { requiresAuth: true },
      children: [
        {
          path: "",
          name: "dashboard",
          component: () => import("@/views/DashboardView.vue"),
          meta: { title: "Dashboard" },
        },
        {
          path: "employees",
          name: "employees",
          component: () => import("@/views/employees/EmployeeListView.vue"),
          meta: { title: "Employees" },
        },
        {
          path: "employees/create",
          name: "employee-create",
          component: () => import("@/views/employees/EmployeeFormView.vue"),
          meta: { title: "Add Employee" },
        },
        {
          path: "employees/:id",
          name: "employee-detail",
          component: () => import("@/views/employees/EmployeeDetailView.vue"),
          meta: { title: "Employee Details" },
        },
        {
          path: "employees/:id/edit",
          name: "employee-edit",
          component: () => import("@/views/employees/EmployeeFormView.vue"),
          meta: { title: "Edit Employee" },
        },
        {
          path: "attendance",
          name: "attendance",
          component: () => import("@/views/attendance/AttendanceView.vue"),
          meta: { title: "Attendance" },
        },
        {
          path: "payroll",
          name: "payroll",
          component: () => import("@/views/payroll/PayrollListView.vue"),
          meta: { title: "Payroll" },
        },
        {
          path: "payroll/create",
          name: "payroll-create",
          component: () => import("@/views/payroll/PayrollFormView.vue"),
          meta: { title: "Create Payroll" },
        },
        {
          path: "payroll/:id",
          name: "payroll-detail",
          component: () => import("@/views/payroll/PayrollDetailView.vue"),
          meta: { title: "Payroll Details" },
        },
        {
          path: "payroll/:id/process",
          name: "payroll-process",
          component: () => import("@/views/payroll/PayrollProcessView.vue"),
          meta: { title: "Process Payroll" },
        },
        {
          path: "allowances",
          name: "allowances",
          component: () => import("@/views/benefits/AllowancesView.vue"),
          meta: { title: "Allowances" },
        },
        {
          path: "loans",
          name: "loans",
          component: () => import("@/views/benefits/LoansView.vue"),
          meta: { title: "Loans" },
        },
        {
          path: "deductions",
          name: "deductions",
          component: () => import("@/views/benefits/DeductionsView.vue"),
          meta: { title: "Deductions" },
        },
        {
          path: "reports",
          name: "reports",
          component: () => import("@/views/reports/ReportsView.vue"),
          meta: { title: "Reports" },
        },
        {
          path: "settings",
          name: "settings",
          component: () => import("@/views/settings/SettingsView.vue"),
          meta: { title: "Settings" },
        },
      ],
    },
    {
      path: "/:pathMatch(.*)*",
      name: "not-found",
      component: () => import("@/views/NotFoundView.vue"),
    },
  ],
});

// Navigation guard
router.beforeEach(async (to, from, next) => {
  const authStore = useAuthStore();

  // Check if route requires authentication
  if (to.meta.requiresAuth !== false) {
    if (!authStore.isAuthenticated) {
      // Not authenticated, redirect to login
      return next({ name: "login", query: { redirect: to.fullPath } });
    }
  } else {
    // Route doesn't require auth (login page)
    if (authStore.isAuthenticated && (to.name === "login" || to.name === "register")) {
      // Already authenticated, redirect to dashboard
      return next({ name: "dashboard" });
    }
  }

  // Set page title
  document.title = to.meta.title
    ? `${to.meta.title} - Payroll System`
    : "Payroll System";

  next();
});

export default router;
