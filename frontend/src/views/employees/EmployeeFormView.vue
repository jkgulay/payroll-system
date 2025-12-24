<template>
  <div>
    <h1 class="text-h4 font-weight-bold mb-6">
      {{ isEditMode ? "Edit" : "Add" }} Employee
    </h1>
    <v-card>
      <v-card-text class="text-center pa-12">
        <v-icon size="64" color="info">mdi-account-edit</v-icon>
        <p class="text-h6 mt-4">Employee form view - Coming soon</p>
        <v-btn color="primary" class="mt-4" to="/employees"
          >Back to Employees</v-btn
        >
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup>
import { computed } from "vue";
import { useRoute } from "vue-router";

const route = useRoute();
const isEditMode = computed(() => !!route.params.id);
</script>
