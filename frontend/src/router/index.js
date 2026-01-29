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
            } else if (authStore.userRole === "hr") {
              return { name: "hr-dashboard" };
            } else if (authStore.userRole === "payrollist") {
              return { name: "payrollist-dashboard" };
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
          path: "hr-dashboard",
          name: "hr-dashboard",
          component: () =>
            import(
              /* webpackChunkName: "dashboard" */ "@/views/hr/HrDashboardView.vue"
            ),
          meta: { title: "Dashboard", roles: ["hr"] },
        },
        {
          path: "payrollist-dashboard",
          name: "payrollist-dashboard",
          component: () =>
            import(
              /* webpackChunkName: "dashboard" */ "@/views/payrollist/PayrollistDashboardView.vue"
            ),
          meta: { title: "Dashboard", roles: ["payrollist"] },
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
            roles: ["admin", "hr", "employee", "payrollist"],
          },
        },
        {
          path: "employees",
          name: "employees",
          component: () =>
            import(
              /* webpackChunkName: "employees" */ "@/views/employees/EmployeeListView.vue"
            ),
          meta: { title: "Employees", roles: ["admin", "payrollist"] },
        },
        {
          path: "resignations",
          name: "resignations",
          component: () =>
            import(
              /* webpackChunkName: "employees" */ "@/views/employees/ResignationManagementView.vue"
            ),
          meta: {
            title: "Resignation Management",
            roles: ["admin", "hr"],
          },
        },
        {
          path: "my-resignation",
          name: "my-resignation",
          component: () =>
            import(
              /* webpackChunkName: "employee" */ "@/views/employee/ResignationView.vue"
            ),
          meta: { title: "My Resignation", roles: ["employee", "payrollist"] },
        },
        {
          path: "my-leaves",
          name: "my-leaves",
          component: () =>
            import(
              /* webpackChunkName: "employee" */ "@/views/employee/LeaveRequestView.vue"
            ),
          meta: { title: "My Leaves", roles: ["employee", "payrollist"] },
        },
        {
          path: "my-loans",
          name: "my-loans",
          component: () =>
            import(
              /* webpackChunkName: "employee" */ "@/views/employee/MyLoansView.vue"
            ),
          meta: { title: "My Loans", roles: ["employee", "payrollist"] },
        },
        {
          path: "leave-approval",
          name: "leave-approval",
          component: () =>
            import(
              /* webpackChunkName: "hr" */ "@/views/hr/LeaveApprovalView.vue"
            ),
          meta: { title: "Leave Approval", roles: ["admin", "hr"] },
        },
        {
          path: "projects",
          name: "projects",
          component: () =>
            import(
              /* webpackChunkName: "projects" */ "@/views/projects/ProjectManagementView.vue"
            ),
          meta: { title: "Project Management", roles: ["admin", "payrollist"] },
        },
        {
          path: "attendance",
          name: "attendance",
          component: () =>
            import(
              /* webpackChunkName: "attendance" */ "@/views/attendance/AttendanceView.vue"
            ),
          meta: { title: "Attendance", roles: ["admin", "hr", "payrollist"] },
        },
        {
          path: "biometric-import",
          name: "biometric-import",
          component: () =>
            import(
              /* webpackChunkName: "attendance" */ "@/views/attendance/BiometricImportView.vue"
            ),
          meta: { title: "Biometric Import", roles: ["admin", "hr"] },
        },
        {
          path: "resumes",
          name: "resumes",
          component: () =>
            import(
              /* webpackChunkName: "hr" */ "@/views/hr/ResumeManagement.vue"
            ),
          meta: { title: "My Resumes", roles: ["hr"] },
        },
        {
          path: "resume-review",
          name: "resume-review",
          component: () =>
            import(
              /* webpackChunkName: "hr" */ "@/views/hr/AdminResumeReview.vue"
            ),
          meta: { title: "Resume Review", roles: ["admin"] },
        },
        {
          path: "allowances",
          name: "allowances",
          component: () =>
            import(
              /* webpackChunkName: "benefits" */ "@/views/benefits/MealAllowanceView.vue"
            ),
          meta: {
            title: "Allowances",
            roles: ["admin", "hr", "payrollist"],
          },
        },
        {
          path: "thirteenth-month-pay",
          name: "thirteenth-month-pay",
          component: () =>
            import(
              /* webpackChunkName: "benefits" */ "@/views/benefits/ThirteenthMonthPayView.vue"
            ),
          meta: {
            title: "13th Month Pay",
            roles: ["admin", "hr", "payrollist"],
          },
        },
        {
          path: "loans",
          name: "loans",
          component: () =>
            import(
              /* webpackChunkName: "benefits" */ "@/views/benefits/LoansView.vue"
            ),
          meta: { title: "Loans", roles: ["admin", "payrollist"] },
        },
        {
          path: "deductions",
          name: "deductions",
          component: () =>
            import(
              /* webpackChunkName: "benefits" */ "@/views/benefits/DeductionsView.vue"
            ),
          meta: { title: "Deductions", roles: ["admin", "payrollist"] },
        },
        {
          path: "cash-bonds",
          name: "cash-bonds",
          component: () =>
            import(
              /* webpackChunkName: "benefits" */ "@/views/benefits/CashBondView.vue"
            ),
          meta: {
            title: "Cash Bond Management",
            roles: ["admin", "payrollist"],
          },
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
          path: "reports/government-contributions",
          name: "government-contributions-report",
          component: () =>
            import(
              /* webpackChunkName: "reports" */ "@/views/reports/GovernmentContributionsReport.vue"
            ),
          meta: {
            title: "Government Contributions Report",
            roles: ["admin", "hr"],
          },
        },
        {
          path: "reports/attendance-summary",
          name: "attendance-summary-report",
          component: () =>
            import(
              /* webpackChunkName: "reports" */ "@/views/reports/AttendanceSummaryReport.vue"
            ),
          meta: {
            title: "Attendance Summary Report",
            roles: ["admin", "hr"],
          },
        },
        {
          path: "payroll",
          name: "payroll",
          component: () =>
            import(
              /* webpackChunkName: "payroll" */ "@/views/payroll/PayrollListView.vue"
            ),
          meta: { title: "Payroll Management", roles: ["admin", "payrollist"] },
        },
        {
          path: "payroll/:id",
          name: "payroll-detail",
          component: () =>
            import(
              /* webpackChunkName: "payroll" */ "@/views/payroll/PayrollDetailView.vue"
            ),
          meta: { title: "Payroll Details", roles: ["admin", "payrollist"] },
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
          path: "user-management",
          name: "user-management",
          component: () =>
            import(
              /* webpackChunkName: "settings" */ "@/views/settings/UserManagementView.vue"
            ),
          meta: { title: "User Management", roles: ["admin"] },
        },
        {
          path: "maintenance",
          name: "maintenance",
          component: () =>
            import(
              /* webpackChunkName: "admin" */ "@/views/admin/MaintenanceView.vue"
            ),
          meta: { title: "Database Maintenance", roles: ["admin"] },
        },
        {
          path: "position-rates",
          name: "position-rates",
          component: () =>
            import(
              /* webpackChunkName: "settings" */ "@/views/settings/PositionRatesView.vue"
            ),
          meta: { title: "Position Rates", roles: ["admin", "hr"] },
        },
        {
          path: "holidays",
          name: "holidays",
          component: () =>
            import(
              /* webpackChunkName: "settings" */ "@/views/settings/HolidayManagementView.vue"
            ),
          meta: {
            title: "Holiday Management",
            roles: ["admin", "hr", "payrollist"],
          },
        },
        {
          path: "audit-trail",
          name: "audit-trail",
          component: () =>
            import(
              /* webpackChunkName: "audit" */ "@/views/audit/AuditTrailView.vue"
            ),
          meta: { title: "Audit Trail", roles: ["admin"] },
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
      } else if (authStore.userRole === "hr") {
        targetRoute = "hr-dashboard";
      } else if (authStore.userRole === "payrollist") {
        targetRoute = "payrollist-dashboard";
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
      } else if (authStore.userRole === "hr") {
        targetRoute = "hr-dashboard";
      } else if (authStore.userRole === "payrollist") {
        targetRoute = "payrollist-dashboard";
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
      } else if (authStore.userRole === "hr") {
        targetRoute = "hr-dashboard";
      } else if (authStore.userRole === "payrollist") {
        targetRoute = "payrollist-dashboard";
      } else {
        targetRoute = "admin-dashboard";
      }
      return next({ name: targetRoute });
    }
  }

  // Set page title
  document.title = to.meta.title
    ? `${to.meta.title} - Giovanni Construction`
    : "Giovanni Construction";

  next();
});

export default router;

