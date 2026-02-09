<template>
  <v-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    max-width="900px"
    persistent
  >
    <v-card v-if="mealAllowance" class="modern-dialog-card" elevation="24">
      <v-card-title class="modern-dialog-header">
        <div class="d-flex align-center w-100">
          <div class="dialog-icon-badge">
            <v-icon size="24">mdi-file-document</v-icon>
          </div>
          <div class="flex-grow-1">
            <div class="text-h5 font-weight-bold">
              {{ mealAllowance.title || "Allowance Details" }}
            </div>
            <div class="text-subtitle-2 text-white-70">
              {{ mealAllowance.reference_number || "Reference" }}
            </div>
          </div>
          <v-chip
            :color="getStatusColor(mealAllowance.status)"
            variant="flat"
            size="default"
          >
            {{ mealAllowance.status.replace("_", " ").toUpperCase() }}
          </v-chip>
          <v-btn
            icon
            variant="text"
            color="white"
            @click="close"
            size="small"
            class="ml-2"
          >
            <v-icon>mdi-close</v-icon>
          </v-btn>
        </div>
      </v-card-title>

      <v-card-text class="pa-6">
        <div class="info-grid mb-4">
          <div class="info-item">
            <div class="info-icon">
              <v-icon size="18">mdi-calendar-range</v-icon>
            </div>
            <div>
              <div class="info-label">Period</div>
              <div class="info-value">
                {{ formatDate(mealAllowance.period_start) }} -
                {{ formatDate(mealAllowance.period_end) }}
              </div>
            </div>
          </div>

          <div class="info-item">
            <div class="info-icon">
              <v-icon size="18">mdi-account-group</v-icon>
            </div>
            <div>
              <div class="info-label">Total Employees</div>
              <div class="info-value">
                {{
                  mealAllowance.items_count || mealAllowance.items?.length || 0
                }}
              </div>
            </div>
          </div>

          <div class="info-item">
            <div class="info-icon">
              <v-icon size="18">mdi-cash</v-icon>
            </div>
            <div>
              <div class="info-label">Grand Total</div>
              <div class="info-value">
                ₱{{ formatNumber(mealAllowance.grand_total) }}
              </div>
            </div>
          </div>
        </div>

        <v-divider class="my-4"></v-divider>

        <div class="text-h6 font-weight-bold mb-4 section-title">
          <v-icon size="20" class="mr-2">mdi-account-group</v-icon>
          Assigned Employees ({{ displayItems.length }})
        </div>

        <v-progress-linear
          v-if="loading"
          indeterminate
          color="#ED985F"
          class="mb-3"
        ></v-progress-linear>

        <v-data-table
          v-else
          :headers="headers"
          :items="pagedItems"
          :items-per-page="itemsPerPage"
          density="comfortable"
          class="elevation-0"
          hide-default-footer
        >
          <template #[`item.total_amount`]="{ item }">
            ₱{{ formatNumber(item.total_amount) }}
          </template>
        </v-data-table>

        <div class="d-flex align-center justify-space-between mt-4">
          <div class="text-caption text-grey">
            Page {{ currentPage }} of {{ totalPages }}
          </div>
          <div class="d-flex align-center gap-2">
            <v-btn
              variant="outlined"
              size="small"
              :disabled="currentPage <= 1"
              @click="currentPage = Math.max(1, currentPage - 1)"
            >
              Prev
            </v-btn>
            <v-btn
              variant="outlined"
              size="small"
              :disabled="currentPage >= totalPages"
              @click="currentPage = Math.min(totalPages, currentPage + 1)"
            >
              Next
            </v-btn>
          </div>
        </div>
      </v-card-text>

      <v-divider></v-divider>

      <v-card-actions class="pa-4">
        <v-spacer></v-spacer>
        <v-btn
          variant="text"
          color="grey-darken-1"
          size="large"
          @click="close"
          prepend-icon="mdi-close"
        >
          Close
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { computed, ref, watch } from "vue";
import { formatDate, formatNumber } from "@/utils/formatters";

const props = defineProps({
  modelValue: Boolean,
  mealAllowance: Object,
});

const emit = defineEmits(["update:modelValue"]);

const loading = computed(() => false);
const itemsPerPage = ref(10);
const currentPage = ref(1);

const displayItems = computed(() => {
  if (!props.mealAllowance?.items) return [];
  return props.mealAllowance.items.map((item) => ({
    ...item,
    employee_name: item.employee?.name || item.employee_name,
    position_code: item.employee?.position?.position_code || item.position_code,
  }));
});

const totalPages = computed(() => {
  const total = displayItems.value.length || 0;
  return Math.max(1, Math.ceil(total / itemsPerPage.value));
});

const pagedItems = computed(() => {
  const start = (currentPage.value - 1) * itemsPerPage.value;
  return displayItems.value.slice(start, start + itemsPerPage.value);
});

watch(
  () => displayItems.value.length,
  () => {
    currentPage.value = 1;
  },
);

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

<style scoped>
.modern-dialog-card {
  border-radius: 20px;
  overflow: hidden;
}

.modern-dialog-header {
  background: linear-gradient(135deg, #e38b4f 0%, #c5652c 100%);
  color: white;
  padding: 24px;
}

.dialog-icon-badge {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  background: rgba(255, 255, 255, 0.2);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 16px;
}

.text-white-70 {
  color: rgba(255, 255, 255, 0.7);
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
  gap: 16px;
}

.info-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: 12px;
  background: #faf6f3;
  border: 1px solid rgba(227, 139, 79, 0.15);
}

.info-icon {
  width: 36px;
  height: 36px;
  border-radius: 10px;
  background: rgba(227, 139, 79, 0.15);
  display: flex;
  align-items: center;
  justify-content: center;
  color: #c5652c;
}

.info-label {
  font-size: 12px;
  text-transform: uppercase;
  letter-spacing: 0.6px;
  color: #8a8a8a;
}

.info-value {
  font-size: 15px;
  font-weight: 600;
  color: #1f1f1f;
}

.section-title {
  color: #2b2b2b;
}

.gap-2 {
  gap: 8px;
}

@media (max-width: 600px) {
  .modern-dialog-header {
    padding: 16px;
  }

  .dialog-icon-badge {
    width: 40px;
    height: 40px;
    margin-right: 12px;
  }
}
</style>
