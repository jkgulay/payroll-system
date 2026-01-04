<template>
  <v-snackbar
    v-model="show"
    :color="color"
    :timeout="timeout"
    location="top right"
    :vertical="vertical"
    class="construction-snackbar"
  >
    <div class="d-flex align-center">
      <v-icon :icon="icon" class="mr-3" size="24"></v-icon>
      <div class="flex-grow-1">
        <div class="text-subtitle-2 font-weight-bold">{{ title }}</div>
        <div v-if="message" class="text-body-2">{{ message }}</div>
      </div>
    </div>
    
    <template v-slot:actions>
      <v-btn
        variant="text"
        icon="mdi-close"
        size="small"
        @click="show = false"
      ></v-btn>
    </template>
  </v-snackbar>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
  modelValue: {
    type: Boolean,
    default: false
  },
  type: {
    type: String,
    default: 'info',
    validator: (value) => ['success', 'error', 'warning', 'info'].includes(value)
  },
  title: {
    type: String,
    required: true
  },
  message: {
    type: String,
    default: ''
  },
  timeout: {
    type: Number,
    default: 5000
  },
  vertical: {
    type: Boolean,
    default: false
  }
});

const emit = defineEmits(['update:modelValue']);

const show = computed({
  get: () => props.modelValue,
  set: (value) => emit('update:modelValue', value)
});

const color = computed(() => {
  const colors = {
    success: 'success',
    error: 'error',
    warning: 'warning',
    info: 'info'
  };
  return colors[props.type] || 'info';
});

const icon = computed(() => {
  const icons = {
    success: 'mdi-check-circle',
    error: 'mdi-alert-circle',
    warning: 'mdi-alert',
    info: 'mdi-information'
  };
  return icons[props.type] || 'mdi-information';
});
</script>

<style scoped>
.construction-snackbar {
  :deep(.v-snackbar__wrapper) {
    border-left: 4px solid currentColor;
    border-radius: 8px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
  }
}
</style>
