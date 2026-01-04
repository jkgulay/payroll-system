import { defineAsyncComponent } from "vue";

/**
 * Lazy-loaded components for better code splitting
 * These components are loaded only when needed
 * 
 * Usage:
 * import { LazyPayrollForm, LazyAttendanceChart } from '@/utils/lazyComponents'
 */

// Loading component to show while async component is loading
const LoadingComponent = {
  template: `
    <div class="lazy-loading pa-8 text-center">
      <v-progress-circular indeterminate color="primary" size="48" />
      <div class="text-caption text-grey mt-4">Loading...</div>
    </div>
  `,
};

// Error component to show if async component fails to load
const ErrorComponent = {
  template: `
    <div class="lazy-error pa-8 text-center">
      <v-icon size="64" color="error" class="mb-4">mdi-alert-circle</v-icon>
      <div class="text-h6 text-error">Failed to load component</div>
      <v-btn color="primary" @click="$parent.$forceUpdate()" class="mt-4">
        Retry
      </v-btn>
    </div>
  `,
};

/**
 * Helper function to create lazy component with loading/error states
 */
const createLazyComponent = (loader) => {
  return defineAsyncComponent({
    loader,
    loadingComponent: LoadingComponent,
    errorComponent: ErrorComponent,
    delay: 200, // Delay before showing loading component (ms)
    timeout: 10000, // Timeout for loading (ms)
  });
};

// ============================================
// CHARTS - Heavy chart components
// ============================================

export const LazyLineChart = createLazyComponent(() =>
  import("@/components/charts/LineChart.vue")
);

export const LazyBarChart = createLazyComponent(() =>
  import("@/components/charts/BarChart.vue")
);

export const LazyPieChart = createLazyComponent(() =>
  import("@/components/charts/PieChart.vue")
);

export const LazyDoughnutChart = createLazyComponent(() =>
  import("@/components/charts/DoughnutChart.vue")
);

// ============================================
// PAYROLL - Heavy payroll components
// ============================================

export const LazyPayrollForm = createLazyComponent(() =>
  import("@/components/payroll/PayrollForm.vue")
);

export const LazyPayslipGenerator = createLazyComponent(() =>
  import("@/components/payroll/PayslipGenerator.vue")
);

export const LazyPayrollCalculator = createLazyComponent(() =>
  import("@/components/payroll/PayrollCalculator.vue")
);

// ============================================
// REPORTS - Heavy report components
// ============================================

export const LazyReportGenerator = createLazyComponent(() =>
  import("@/components/reports/ReportGenerator.vue")
);

export const LazyPDFPreview = createLazyComponent(() =>
  import("@/components/reports/PDFPreview.vue")
);

export const LazyExcelExporter = createLazyComponent(() =>
  import("@/components/reports/ExcelExporter.vue")
);

// ============================================
// ATTENDANCE - Heavy attendance components
// ============================================

export const LazyAttendanceCalendar = createLazyComponent(() =>
  import("@/components/attendance/AttendanceCalendar.vue")
);

export const LazyAttendanceChart = createLazyComponent(() =>
  import("@/components/attendance/AttendanceChart.vue")
);

// ============================================
// EMPLOYEE - Heavy employee components
// ============================================

export const LazyEmployeeForm = createLazyComponent(() =>
  import("@/components/employees/EmployeeForm.vue")
);

export const LazyEmployeeDocuments = createLazyComponent(() =>
  import("@/components/employees/EmployeeDocuments.vue")
);

// ============================================
// DIALOGS - Heavy dialog components
// ============================================

export const LazyTwoFactorVerify = createLazyComponent(() =>
  import("@/components/TwoFactorVerify.vue")
);

export const LazyTwoFactorSetup = createLazyComponent(() =>
  import("@/components/TwoFactorSetup.vue")
);

// ============================================
// UTILITIES
// ============================================

/**
 * Preload a component before it's needed
 * Useful for preloading components user is likely to need
 * 
 * @example
 * // Preload when hovering over a button
 * <v-btn @mouseenter="preloadComponent(LazyPayrollForm)">
 *   Open Payroll
 * </v-btn>
 */
export const preloadComponent = (component) => {
  if (component.__asyncLoader) {
    component.__asyncLoader();
  }
};

/**
 * Batch preload multiple components
 * 
 * @example
 * preloadComponents([LazyPayrollForm, LazyPayslipGenerator]);
 */
export const preloadComponents = (components) => {
  components.forEach((component) => preloadComponent(component));
};
