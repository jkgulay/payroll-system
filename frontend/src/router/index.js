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
      path: "/",
      component: () => import("@/layouts/MainLayout.vue"),
      meta: { requiresAuth: true },
      children: [
        {
          path: "",
          redirect: (to) => {
            const authStore = useAuthStore();
            if (authStore.userRole === "employee") {
              return { name: "employee-dashboard" };
            } else if (authStore.userRole === "accountant") {
              return { name: "accountant-dashboard" };
            } else {
              return { name: "admin-dashboard" };
            }
          },
        },
        {
          path: "admin-dashboard",
          name: "admin-dashboard",
          component: () => import("@/views/DashboardView.vue"),
          meta: { title: "Dashboard", roles: ["admin"] },
        },
        {
          path: "accountant-dashboard",
          name: "accountant-dashboard",
          component: () => import("@/views/accountant/AccountantDashboardView.vue"),
          meta: { title: "Dashboard", roles: ["accountant"] },
        },
        {
          path: "employee-dashboard",
          name: "employee-dashboard",
          component: () => import("@/views/employee/EmployeeDashboardView.vue"),
          meta: { title: "Dashboard", roles: ["employee"] },
        },
        {
          path: "profile",
          name: "profile",
          component: () => import("@/views/employee/ProfileView.vue"),
          meta: { title: "My Profile", roles: ["admin", "accountant", "employee"] },
        },
        {
          path: "employees",
          name: "employees",
          component: () => import("@/views/employees/EmployeeListView.vue"),
          meta: { title: "Employees", roles: ["admin", "accountant"] },
        },
        {
          path: "employees/create",
          name: "employee-create",
          component: () => import("@/views/employees/EmployeeFormView.vue"),
          meta: { title: "Add Employee", roles: ["admin", "accountant"] },
        },
        {
          path: "employees/:id",
          name: "employee-detail",
          component: () => import("@/views/employees/EmployeeDetailView.vue"),
          meta: { title: "Employee Details", roles: ["admin", "accountant"] },
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
          meta: { title: "Attendance", roles: ["admin", "accountant"] },
        },
        {
          path: "resumes",
          name: "resumes",
          component: () => import("@/views/accountant/ResumeManagement.vue"),
          meta: { title: "My Resumes", roles: ["accountant"] },
        },
        {
          path: "resume-review",
          name: "resume-review",
          component: () => import("@/views/accountant/AdminResumeReview.vue"),
          meta: { title: "Resume Review", roles: ["admin"] },
        },
        {
          path: "payroll",
          name: "payroll",
          component: () => import("@/views/payroll/PayrollListView.vue"),
          meta: { title: "Payroll", roles: ["admin", "accountant"] },
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
          meta: { title: "Settings", roles: ["admin"] },
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

    // Ensure user data is loaded
    if (!authStore.user) {
      await authStore.checkAuth();
    }

    // Handle root path redirect
    if (to.path === "/" || to.name === null) {
      let targetRoute;
      if (authStore.userRole === "employee") {
        targetRoute = "employee-dashboard";
      } else if (authStore.userRole === "accountant") {
        targetRoute = "accountant-dashboard";
      } else {
        targetRoute = "admin-dashboard";
      }
      return next({ name: targetRoute });
    }

    // Check role-based access
    if (to.meta.roles && !to.meta.roles.includes(authStore.userRole)) {
      // User doesn't have access to this route, redirect to appropriate dashboard
      let targetRoute;
      if (authStore.userRole === "employee") {
        targetRoute = "employee-dashboard";
      } else if (authStore.userRole === "accountant") {
        targetRoute = "accountant-dashboard";
      } else {
        targetRoute = "admin-dashboard";
      }
      
      // Prevent redirect loop
      if (to.name !== targetRoute) {
        return next({ name: targetRoute });
      }
    }
  } else {
    // Route doesn't require auth (login page)
    if (authStore.isAuthenticated && to.name === "login") {
      // Ensure user data is loaded
      if (!authStore.user) {
        await authStore.checkAuth();
      }
      // Already authenticated, redirect to appropriate dashboard based on role
      let targetRoute;
      if (authStore.userRole === "employee") {
        targetRoute = "employee-dashboard";
      } else if (authStore.userRole === "accountant") {
        targetRoute = "accountant-dashboard";
      } else {
        targetRoute = "admin-dashboard";
      }
      return next({ name: targetRoute });
    }
  }

  // Set page title
  document.title = to.meta.title
    ? `${to.meta.title} - Payroll System`
    : "Payroll System";

  next();
});

export default router;
