<template>
  <v-container>
    <v-card class="mx-auto" max-width="800">
      <v-card-title class="bg-primary text-white">
        <v-icon start>mdi-tools</v-icon>
        Database Maintenance
      </v-card-title>

      <v-card-text class="pa-6">
        <!-- Database Health -->
        <v-alert
          v-if="healthData"
          :type="healthData.payrolls?.sequence_ok ? 'success' : 'warning'"
          class="mb-4"
        >
          <div class="font-weight-bold mb-2">Database Health Status</div>
          <div>Driver: {{ healthData.database_driver }}</div>
          <div v-if="healthData.payrolls">
            <div>Total Payrolls: {{ healthData.payrolls.total_payrolls }}</div>
            <div>Max Payroll ID: {{ healthData.payrolls.max_id }}</div>
            <div>Sequence Value: {{ healthData.payrolls.sequence_value }}</div>
            <div>
              Status:
              {{
                healthData.payrolls.sequence_ok
                  ? "✓ Sequence is OK"
                  : "⚠ Sequence needs fixing"
              }}
            </div>
          </div>
          <div v-if="healthData.employees">
            <div>Active Employees: {{ healthData.employees.active_count }}</div>
          </div>
        </v-alert>

        <!-- Fix Payroll Sequence -->
        <v-card variant="outlined" class="mb-4">
          <v-card-title class="text-h6">
            <v-icon start color="warning">mdi-database-sync</v-icon>
            Fix Payroll Sequence
          </v-card-title>
          <v-card-text>
            <p class="mb-3">
              If you're experiencing foreign key violation errors when creating
              payrolls (e.g., "Key (payroll_id)=(17) is not present in table
              payroll"), this will fix the PostgreSQL sequence.
            </p>
            <v-alert type="info" density="compact" class="mb-3">
              This is safe to run and will only reset the sequence to match the
              highest existing payroll ID.
            </v-alert>
            <div class="d-flex gap-2">
              <v-btn
                color="warning"
                :loading="fixing"
                @click="fixPayrollSequence"
                prepend-icon="mdi-wrench"
              >
                Fix Sequence Now
              </v-btn>
              <v-btn
                color="error"
                variant="outlined"
                :loading="cleaning"
                @click="cleanDatabase"
                prepend-icon="mdi-broom"
              >
                Clean & Reset All
              </v-btn>
            </div>
          </v-card-text>
        </v-card>

        <!-- Result -->
        <v-alert v-if="result" :type="result.type" class="mb-4">
          {{ result.message }}
        </v-alert>
      </v-card-text>

      <v-card-actions>
        <v-btn color="primary" @click="checkHealth" :loading="checking">
          <v-icon start>mdi-refresh</v-icon>
          Check Health
        </v-btn>
        <v-spacer></v-spacer>
        <v-btn variant="text" @click="$router.push('/admin')">
          Back to Dashboard
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-container>
</template>

<script setup>
import { ref, onMounted } from "vue";
import api from "@/services/api";
import { useToast } from "vue-toastification";

const toast = useToast();

const healthData = ref(null);
const result = ref(null);
const checking = ref(false);
const fixing = ref(false);
const cleaning = ref(false);

onMounted(() => {
  checkHealth();
});

async function checkHealth() {
  checking.value = true;
  result.value = null;
  try {
    const response = await api.get("/maintenance/database-health");
    healthData.value = response.data;
  } catch (error) {
    console.error("Error checking health:", error);
    toast.error("Failed to check database health");
  } finally {
    checking.value = false;
  }
}

async function fixPayrollSequence() {
  if (
    !confirm(
      "Are you sure you want to fix the payroll sequence? This is safe but should only be done when needed.",
    )
  ) {
    return;
  }

  fixing.value = true;
  result.value = null;
  try {
    const response = await api.post("/maintenance/fix-payroll-sequence");
    result.value = {
      type: "success",
      message:
        response.data.message +
        ` (Next ID will be: ${response.data.next_id_will_be})`,
    };
    toast.success("Sequence fixed successfully!");
    await checkHealth();
  } catch (error) {
    console.error("Error fixing sequence:", error);
    result.value = {
      type: "error",
      message:
        error.response?.data?.message || "Failed to fix payroll sequence",
    };
    toast.error("Failed to fix sequence");
  } finally {
    fixing.value = false;
  }
}

async function cleanDatabase() {
  if (
    !confirm(
      "⚠️ WARNING: This will delete any orphaned payroll items and reset ALL sequences. Are you absolutely sure?",
    )
  ) {
    return;
  }

  if (
    !confirm(
      "This action cannot be undone. Type 'yes' in your mind and click OK to proceed.",
    )
  ) {
    return;
  }

  cleaning.value = true;
  result.value = null;
  try {
    const response = await api.post("/maintenance/clean-database", {
      confirm: "yes-delete-orphaned-data",
    });
    result.value = {
      type: "success",
      message: response.data.message,
    };
    toast.success("Database cleaned successfully!");
    await checkHealth();
  } catch (error) {
    console.error("Error cleaning database:", error);
    result.value = {
      type: "error",
      message: error.response?.data?.message || "Failed to clean database",
    };
    toast.error("Failed to clean database");
  } finally {
    cleaning.value = false;
  }
}
</script>

<style scoped>
.v-card-title {
  font-size: 1.25rem;
  font-weight: 600;
}
</style>
