<template>
  <v-dialog v-model="isOpen" max-width="440" persistent>
    <v-card rounded="lg">
      <v-card-title class="text-h6 pa-4">
        {{ title }}
      </v-card-title>
      <v-card-text class="px-4 pb-2">
        {{ message }}
      </v-card-text>
      <v-card-actions class="pa-4 pt-2">
        <v-spacer />
        <v-btn variant="text" @click="onCancel">
          {{ cancelText }}
        </v-btn>
        <v-btn :color="confirmColor" variant="flat" @click="onConfirm">
          {{ confirmText }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { useConfirmDialog } from "@/composables/useConfirmDialog";

const {
  isOpen,
  title,
  message,
  confirmText,
  cancelText,
  confirmColor,
  onConfirm,
  onCancel,
} = useConfirmDialog();
</script>
