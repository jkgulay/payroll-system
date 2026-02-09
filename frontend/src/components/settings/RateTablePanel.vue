<template>
  <div class="pa-4">
    <div class="d-flex justify-space-between align-center mb-4">
      <h3>{{ title }}</h3>
      <v-btn
        color="primary"
        @click="$emit('add', type)"
        prepend-icon="mdi-plus"
      >
        Add Rate
      </v-btn>
    </div>

    <v-data-table
      :headers="headers"
      :items="rates"
      :loading="loading"
      class="elevation-1"
      density="comfortable"
    >
      <template #item.min_salary="{ item }">
        ₱{{ formatCurrency(item.min_salary) }}
      </template>

      <template #item.max_salary="{ item }">
        <span v-if="item.max_salary"
          >₱{{ formatCurrency(item.max_salary) }}</span
        >
        <span v-else class="text-grey">No limit</span>
      </template>

      <template #item.employee_rate="{ item }">
        <span v-if="item.employee_rate">{{ item.employee_rate }}%</span>
        <span v-else-if="item.employee_fixed"
          >₱{{ formatCurrency(item.employee_fixed) }}</span
        >
        <span v-else>-</span>
      </template>

      <template #item.employer_rate="{ item }">
        <span v-if="item.employer_rate">{{ item.employer_rate }}%</span>
        <span v-else-if="item.employer_fixed"
          >₱{{ formatCurrency(item.employer_fixed) }}</span
        >
        <span v-else>-</span>
      </template>

      <template #item.total_contribution="{ item }">
        <span v-if="item.total_contribution"
          >₱{{ formatCurrency(item.total_contribution) }}</span
        >
        <span v-else>Calculated</span>
      </template>

      <template #item.effective_date="{ item }">
        {{ formatDate(item.effective_date) }}
      </template>

      <template #item.end_date="{ item }">
        <span v-if="item.end_date">{{ formatDate(item.end_date) }}</span>
        <span v-else class="text-grey">Current</span>
      </template>

      <template #item.is_active="{ item }">
        <v-chip
          :color="item.is_active ? 'success' : 'grey'"
          size="small"
          @click="$emit('toggle-active', item)"
          style="cursor: pointer"
        >
          {{ item.is_active ? "Active" : "Inactive" }}
        </v-chip>
      </template>

      <template #item.actions="{ item }">
        <v-btn
          icon="mdi-pencil"
          size="small"
          variant="text"
          @click="$emit('edit', item)"
        ></v-btn>
        <v-btn
          icon="mdi-delete"
          size="small"
          variant="text"
          color="error"
          @click="$emit('delete', item)"
        ></v-btn>
      </template>

      <template #no-data>
        <div class="text-center pa-4">
          <v-icon size="64" color="grey-lighten-1">mdi-database-off</v-icon>
          <p class="text-grey mt-2">No rates configured yet</p>
          <v-btn color="primary" @click="$emit('add', type)" class="mt-2">
            Add First Rate
          </v-btn>
        </div>
      </template>
    </v-data-table>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { formatCurrency, formatDate } from "@/utils/formatters";

const props = defineProps({
  rates: {
    type: Array,
    required: true,
  },
  type: {
    type: String,
    required: true,
  },
  title: {
    type: String,
    required: true,
  },
});

defineEmits(["add", "edit", "delete", "toggle-active"]);

const loading = computed(() => false);

const headers = [
  { title: "Name", key: "name", sortable: true },
  { title: "Salary Range", key: "min_salary", sortable: true },
  { title: "To", key: "max_salary", sortable: true },
  { title: "Employee Rate", key: "employee_rate", sortable: false },
  { title: "Employer Rate", key: "employer_rate", sortable: false },
  { title: "Total", key: "total_contribution", sortable: false },
  { title: "Effective Date", key: "effective_date", sortable: true },
  { title: "End Date", key: "end_date", sortable: true },
  { title: "Status", key: "is_active", sortable: true },
  { title: "Actions", key: "actions", sortable: false, align: "center" },
];
</script>
