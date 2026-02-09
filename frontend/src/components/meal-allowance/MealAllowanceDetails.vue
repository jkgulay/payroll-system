<template>
  <v-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    max-width="1000px"
  >
    <v-card v-if="mealAllowance">
      <v-card-title class="bg-primary">
        <v-icon left>mdi-file-document</v-icon>
        Allowance Details
        <v-spacer></v-spacer>
        <v-btn icon="mdi-close" variant="text" @click="close"></v-btn>
      </v-card-title>

      <v-card-text class="pt-4">
        <v-row>
          <v-col cols="6">
            <strong>Reference Number:</strong><br />
            {{ mealAllowance.reference_number }}
          </v-col>
          <v-col cols="6">
            <strong>Status:</strong><br />
            <v-chip :color="getStatusColor(mealAllowance.status)" size="small">
              {{ mealAllowance.status.replace("_", " ").toUpperCase() }}
            </v-chip>
          </v-col>
          <v-col cols="12">
            <strong>Title:</strong><br />
            {{ mealAllowance.title }}
          </v-col>
          <v-col cols="6">
            <strong>Period:</strong><br />
            {{ formatDate(mealAllowance.period_start) }} -
            {{ formatDate(mealAllowance.period_end) }}
          </v-col>
          <v-col cols="6">
            <strong>Location:</strong><br />
            {{ mealAllowance.location || "N/A" }}
          </v-col>
          <v-col cols="12">
            <strong>Total Employees:</strong>
            {{ mealAllowance.items_count || mealAllowance.items?.length || 0 }}
          </v-col>
        </v-row>

        <v-divider class="my-4"></v-divider>

        <h3 class="mb-2">Employees (Showing first 100)</h3>
        <v-data-table
          :headers="headers"
          :items="displayItems"
          density="compact"
          :loading="loading"
        >
          <template #[`item.total_amount`]="{ item }">
            ₱{{ formatNumber(item.total_amount) }}
          </template>

          <template #bottom>
            <v-row class="ma-2">
              <v-col class="text-right">
                <strong
                  >Grand Total: ₱{{
                    formatNumber(mealAllowance.grand_total)
                  }}</strong
                >
              </v-col>
            </v-row>
          </template>
        </v-data-table>
      </v-card-text>

      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn @click="close">Close</v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { computed } from "vue";
import { formatDate, formatNumber } from "@/utils/formatters";

const props = defineProps({
  modelValue: Boolean,
  mealAllowance: Object,
});

const emit = defineEmits(["update:modelValue"]);

const loading = computed(() => false);

const displayItems = computed(() => {
  if (!props.mealAllowance?.items) return [];
  return props.mealAllowance.items.map((item) => ({
    ...item,
    employee_name: item.employee?.name || item.employee_name,
    position_code: item.employee?.position?.position_code || item.position_code,
  }));
});

const headers = [
  { title: "Employee Name", key: "employee_name" },
  { title: "Position Code", key: "position_code" },
  { title: "No. of Days", key: "no_of_days" },
  { title: "Amount/Day", key: "amount_per_day" },
  { title: "Total", key: "total_amount" },
];

function close() {
  emit("update:modelValue", false);
}

function getStatusColor(status) {
  const colors = {
    draft: "grey",
    pending_approval: "orange",
    approved: "success",
    rejected: "error",
  };
  return colors[status] || "grey";
}
</script>
