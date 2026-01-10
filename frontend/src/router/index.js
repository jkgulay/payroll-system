import { createRouter, createWebHistory } from "vue-router";
import { useAuthStore } from "@/stores/auth";

const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    {
      path: "/login",
      name: "login",
      component: () =>
        import(/* webpackChunkName: "auth" */ "@/views/auth/LoginView.vue"),
      meta: { requiresAuth: false },
    },
    {
      path: "/",
      component: () =>
        import(/* webpackChunkName: "layout" */ "@/layouts/MainLayout.vue"),
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
          component: () =>
            import(
              /* webpackChunkName: "dashboard" */ "@/views/DashboardView.vue"
            ),
          meta: { title: "Dashboard", roles: ["admin"] },
        },
        {
          path: "accountant-dashboard",
          name: "accountant-dashboard",
          component: () =>
            import(
              /* webpackChunkName: "dashboard" */ "@/views/accountant/AccountantDashboardView.vue"
            ),
          meta: { title: "Dashboard", roles: ["accountant"] },
        },
        {
          path: "employee-dashboard",
          name: "employee-dashboard",
          component: () =>
            import(
              /* webpackChunkName: "dashboard" */ "@/views/employee/EmployeeDashboardView.vue"
            ),
          meta: { title: "Dashboard", roles: ["employee"] },
        },
        {
          path: "profile",
          name: "profile",
          component: () =>
            import(
              /* webpackChunkName: "profile" */ "@/views/employee/ProfileView.vue"
            ),
          meta: {
            title: "My Profile",
            roles: ["admin", "accountant", "employee"],
          },
        },
        {
          path: "employees",
          name: "employees",
          component: () =>
            import(
              /* webpackChunkName: "employees" */ "@/views/employees/EmployeeListView.vue"
            ),
          meta: { title: "Employees", roles: ["admin"] },
        },
        {
          path: "employees/import",
          name: "employees-import",
          component: () =>
            import(
              /* webpackChunkName: "employees" */ "@/views/employees/ImportEmployeesView.vue"
            ),
          meta: { title: "Import Employees", roles: ["admin", "accountant"] },
        },
        {
          path: "resignations",
          name: "resignations",
          component: () =>
            import(
              /* webpackChunkName: "employees" */ "@/views/employees/ResignationManagementView.vue"
            ),
          meta: { title: "Resignation Management", roles: ["admin", "accountant"] },
        },
        {
          path: "my-resignation",
          name: "my-resignation",
          component: () =>
            import(
              /* webpackChunkName: "employee" */ "@/views/employee/ResignationView.vue"
            ),
          meta: { title: "My Resignation", roles: ["employee"] },
        },
        {
          path: "projects",
          name: "projects",
          component: () =>
            import(
              /* webpackChunkName: "projects" */ "@/views/projects/ProjectManagementView.vue"
            ),
          meta: { title: "Project Management", roles: ["admin"] },
        },
        {
          path: "attendance",
          name: "attendance",
          component: () =>
            import(
              /* webpackChunkName: "attendance" */ "@/views/attendance/AttendanceView.vue"
            ),
          meta: { title: "Attendance", roles: ["admin", "accountant"] },
        },
        {
          path: "resumes",
          name: "resumes",
          component: () =>
            import(
              /* webpackChunkName: "accountant" */ "@/views/accountant/ResumeManagement.vue"
            ),
          meta: { title: "My Resumes", roles: ["accountant"] },
        },
        {
          path: "resume-review",
          name: "resume-review",
          component: () =>
            import(
              /* webpackChunkName: "accountant" */ "@/views/accountant/AdminResumeReview.vue"
            ),
          meta: { title: "Resume Review", roles: ["admin"] },
        },
        {
          path: "payroll",
          name: "payroll",
          component: () =>
            import(
              /* webpackChunkName: "payroll" */ "@/views/payroll/PayrollListView.vue"
            ),
          meta: { title: "Payroll", roles: ["admin", "accountant"] },
        },
        {
          path: "payroll/create",
          name: "payroll-create",
          component: () =>
            import(
              /* webpackChunkName: "payroll" */ "@/views/payroll/PayrollFormView.vue"
            ),
          meta: { title: "Create Payroll" },
        },
        {
          path: "payroll/:id",
          name: "payroll-detail",
          component: () =>
            import(
              /* webpackChunkName: "payroll" */ "@/views/payroll/PayrollDetailView.vue"
            ),
          meta: { title: "Payroll Details" },
        },
        {
          path: "payroll/pay-rates",
          name: "pay-rates",
          component: () =>
            import(
              /* webpackChunkName: "payroll" */ "@/views/payroll/PayRateManagementView.vue"
            ),
          meta: {
            title: "Compensation & Pay Rates",
            roles: ["admin", "accountant"],
          },
        },
        {
          path: "payroll/:id/process",
          name: "payroll-process",
          component: () =>
            import(
              /* webpackChunkName: "payroll" */ "@/views/payroll/PayrollProcessView.vue"
            ),
          meta: { title: "Process Payroll" },
        },
        {
          path: "allowances",
          name: "allowances",
          component: () =>
            import(
              /* webpackChunkName: "benefits" */ "@/views/benefits/AllowancesView.vue"
            ),
          meta: { title: "Allowances" },
        },
        {
          path: "loans",
          name: "loans",
          component: () =>
            import(
              /* webpackChunkName: "benefits" */ "@/views/benefits/LoansView.vue"
            ),
          meta: { title: "Loans" },
        },
        {
          path: "deductions",
          name: "deductions",
          component: () =>
            import(
              /* webpackChunkName: "benefits" */ "@/views/benefits/DeductionsView.vue"
            ),
          meta: { title: "Deductions" },
        },
        {
          path: "reports",
          name: "reports",
          component: () =>
            import(
              /* webpackChunkName: "reports" */ "@/views/reports/ReportsView.vue"
            ),
          meta: { title: "Reports" },
        },
        {
          path: "analytics",
          name: "analytics",
          component: () =>
            import(
              /* webpackChunkName: "analytics" */ "@/views/AnalyticsDashboard.vue"
            ),
          meta: {
            title: "Analytics Dashboard",
            roles: ["admin", "accountant"],
          },
        },
        {
          path: "settings",
          name: "settings",
          component: () =>
            import(
              /* webpackChunkName: "settings" */ "@/views/settings/SettingsView.vue"
            ),
          meta: { title: "Settings", roles: ["admin"] },
        },
        {
          path: "security",
          name: "security",
          component: () =>
            import(
              /* webpackChunkName: "settings" */ "@/views/settings/SecurityView.vue"
            ),
          meta: { title: "Security Settings" },
        },
      ],
    },
    {
      path: "/:pathMatch(.*)*",
      name: "not-found",
      component: () =>
        import(/* webpackChunkName: "error" */ "@/views/NotFoundView.vue"),
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

    // Ensure user data is loaded (checkAuth now caches, so safe to call)
    if (!authStore.user) {
      const isValid = await authStore.checkAuth();
      if (!isValid) {
        return next({ name: "login", query: { redirect: to.fullPath } });
      }
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
