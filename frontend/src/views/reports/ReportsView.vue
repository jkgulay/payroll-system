<template>
  <div>
    <h1 class="text-h4 font-weight-bold mb-6">Reports</h1>

    <v-row>
      <v-col cols="12" md="4" v-for="report in reports" :key="report.id">
        <v-card hover @click="generateReport(report)">
          <v-card-text class="text-center pa-6">
            <v-icon
              :icon="report.icon"
              size="48"
              :color="report.color"
            ></v-icon>
            <h3 class="text-h6 mt-3 mb-2">{{ report.title }}</h3>
            <p class="text-body-2 text-medium-emphasis">
              {{ report.description }}
            </p>
          </v-card-text>
        </v-card>
      </v-col>
    </v-row>
  </div>
</template>

<script setup>
import { useToast } from "vue-toastification";

const toast = useToast();

const reports = [
  {
    id: "payroll-summary",
    title: "Payroll Summary",
    description: "Total payroll breakdown by period",
    icon: "mdi-cash-multiple",
    color: "success",
  },
  {
    id: "government-contributions",
    title: "Government Contributions",
    description: "SSS, PhilHealth, Pag-IBIG summary",
    icon: "mdi-bank",
    color: "primary",
  },
  {
    id: "tax-withholding",
    title: "Tax Withholding",
    description: "BIR Form 1601-C data",
    icon: "mdi-file-document",
    color: "error",
  },
  {
    id: "attendance-summary",
    title: "Attendance Summary",
    description: "Employee attendance by period",
    icon: "mdi-calendar-check",
    color: "info",
  },
  {
    id: "employee-masterlist",
    title: "Employee Masterlist",
    description: "Complete employee directory",
    icon: "mdi-account-group",
    color: "secondary",
  },
  {
    id: "loans-summary",
    title: "Loans Summary",
    description: "Outstanding loans report",
    icon: "mdi-hand-coin",
    color: "warning",
  },
];

function generateReport(report) {
  toast.info(`${report.title} report - Coming soon`);
}
</script>
