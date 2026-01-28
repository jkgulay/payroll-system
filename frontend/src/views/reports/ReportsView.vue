<template>
  <div class="reports-page">
    <!-- Modern Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="page-title-section">
          <div class="page-icon-badge">
            <v-icon size="22">mdi-file-chart-outline</v-icon>
          </div>
          <div>
            <h1 class="page-title">Reports</h1>
            <p class="page-subtitle">
              Generate and download various payroll and employee reports
            </p>
          </div>
        </div>
      </div>
    </div>

    <!-- Reports Grid -->
    <div class="reports-grid">
      <div
        v-for="report in reports"
        :key="report.id"
        class="report-card"
        @click="generateReport(report)"
      >
        <div class="report-icon" :class="report.colorClass">
          <v-icon size="28">{{ report.icon }}</v-icon>
        </div>
        <div class="report-content">
          <h3 class="report-title">{{ report.title }}</h3>
          <p class="report-description">{{ report.description }}</p>
        </div>
        <div class="report-action">
          <v-icon size="20" color="#001f3d">mdi-chevron-right</v-icon>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { useRouter } from "vue-router";
import { useToast } from "vue-toastification";

const router = useRouter();
const toast = useToast();

const reports = [
  {
    id: "government-contributions",
    title: "Government Contributions",
    description: "SSS, PhilHealth, Pag-IBIG summary",
    icon: "mdi-bank",
    colorClass: "primary",
  },
  {
    id: "tax-withholding",
    title: "Tax Withholding",
    description: "BIR Form 1601-C data",
    icon: "mdi-file-document",
    colorClass: "secondary",
  },
  {
    id: "attendance-summary",
    title: "Attendance Summary",
    description: "Employee attendance by period",
    icon: "mdi-calendar-check",
    colorClass: "info",
  },
  {
    id: "employee-masterlist",
    title: "Employee Masterlist",
    description: "Complete employee directory",
    icon: "mdi-account-group",
    colorClass: "accent",
  },
  {
    id: "loans-summary",
    title: "Loans Summary",
    description: "Outstanding loans report",
    icon: "mdi-hand-coin",
    colorClass: "warning",
  },
];

function generateReport(report) {
  if (report.id === "government-contributions") {
    router.push("/reports/government-contributions");
  } else if (report.id === "attendance-summary") {
    router.push("/reports/attendance-summary");
  } else {
    toast.info(`${report.title} report - Coming soon`);
  }
}
</script>

<style scoped lang="scss">
.reports-page {
  background-color: #f8f9fa;
  min-height: 100vh;
}

.page-header {
  margin-bottom: 24px;
}

.header-content {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.page-title-section {
  display: flex;
  align-items: center;
  gap: 16px;
}

.page-icon-badge {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.25);
}

.page-title {
  font-size: 28px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
  line-height: 1.2;
}

.page-subtitle {
  font-size: 14px;
  color: rgba(0, 31, 61, 0.6);
  margin: 4px 0 0 0;
}

.reports-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
  gap: 16px;
}

.report-card {
  background: white;
  border-radius: 16px;
  padding: 20px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  display: flex;
  align-items: center;
  gap: 16px;
  cursor: pointer;
  transition: all 0.3s ease;

  &:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 20px rgba(0, 31, 61, 0.12);
    border-color: rgba(237, 152, 95, 0.3);
  }
}

.report-icon {
  width: 60px;
  height: 60px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;

  &.primary {
    background: rgba(237, 152, 95, 0.1);
    .v-icon {
      color: #ed985f;
    }
  }

  &.secondary {
    background: rgba(239, 68, 68, 0.1);
    .v-icon {
      color: #ef4444;
    }
  }

  &.info {
    background: rgba(59, 130, 246, 0.1);
    .v-icon {
      color: #3b82f6;
    }
  }

  &.accent {
    background: rgba(0, 31, 61, 0.1);
    .v-icon {
      color: #001f3d;
    }
  }

  &.warning {
    background: rgba(245, 158, 11, 0.1);
    .v-icon {
      color: #f59e0b;
    }
  }
}

.report-content {
  flex: 1;
  min-width: 0;
}

.report-title {
  font-size: 18px;
  font-weight: 600;
  color: #001f3d;
  margin: 0 0 4px 0;
  line-height: 1.3;
}

.report-description {
  font-size: 14px;
  color: rgba(0, 31, 61, 0.6);
  margin: 0;
  line-height: 1.4;
}

.report-action {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  background: rgba(0, 31, 61, 0.05);
  flex-shrink: 0;
  transition: all 0.3s ease;

  .report-card:hover & {
    background: rgba(237, 152, 95, 0.1);

    .v-icon {
      color: #ed985f !important;
    }
  }
}
</style>
