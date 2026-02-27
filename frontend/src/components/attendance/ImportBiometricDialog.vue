<template>
  <v-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    max-width="600"
    persistent
  >
    <v-card class="modern-dialog">
      <div class="dialog-header">
        <div class="dialog-icon-wrapper info">
          <v-icon size="20">mdi-upload</v-icon>
        </div>
        <div>
          <div class="dialog-title">Import Biometric Data</div>
          <div class="dialog-subtitle">
            Upload attendance from biometric device
          </div>
        </div>
      </div>

      <v-card-text class="pt-6">
        <v-form ref="form" v-model="valid">
          <v-row>
            <v-col cols="12">
              <v-file-input
                v-model="file"
                label="Biometric File *"
                accept=".xls,.xlsx"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
                prepend-icon="mdi-file-upload"
                show-size
              >
                <template v-slot:append>
                  <v-tooltip
                    text="Upload Excel file (.xlsx or .xls) exported from biometric device"
                  >
                    <template v-slot:activator="{ props }">
                      <v-icon v-bind="props">mdi-help-circle</v-icon>
                    </template>
                  </v-tooltip>
                </template>
              </v-file-input>
            </v-col>

            <v-col cols="12">
              <v-alert type="info" variant="tonal" density="compact">
                <div class="text-body-2">
                  <strong>Required columns:</strong> Staff Code, Name, Punch
                  Date<br />
                  <strong>Punch Date format:</strong> YYYY-MM-DD HH:MM (e.g.
                  2026-02-03 08:30)<br />
                  <strong>One row per punch event</strong> — multiple punches
                  are grouped automatically.
                </div>
              </v-alert>
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>

      <div class="dialog-divider"></div>
      <div class="dialog-actions">
        <button class="dialog-btn dialog-btn-cancel" @click="close">
          Cancel
        </button>
        <button
          class="dialog-btn dialog-btn-primary"
          @click="importFile"
          :disabled="!valid || importing"
        >
          <v-icon v-if="importing" size="16" class="rotating"
            >mdi-loading</v-icon
          >
          <v-icon v-else size="16">mdi-upload</v-icon>
          <span>{{ importing ? "Importing..." : "Import" }}</span>
        </button>
      </div>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref } from "vue";
import attendanceService from "@/services/attendanceService";
import { useToast } from "vue-toastification";

const props = defineProps({
  modelValue: Boolean,
});

const emit = defineEmits(["update:modelValue", "imported"]);
const toast = useToast();

const form = ref(null);
const valid = ref(false);
const importing = ref(false);
const file = ref(null);

const rules = {
  required: (v) => !!v || "This field is required",
};

const importFile = async () => {
  if (!valid.value || !file.value) return;

  importing.value = true;
  try {
    const formData = new FormData();
    formData.append("file", file.value[0]);

    const result = await attendanceService.importBiometric(formData);
    emit("imported", result);
    close();
  } catch (error) {
    toast.error(error.response?.data?.message || "Failed to import file");
  } finally {
    importing.value = false;
  }
};

const close = () => {
  file.value = null;
  emit("update:modelValue", false);
};
</script>

<style scoped lang="scss">
.modern-dialog {
  border-radius: 16px !important;
}

.dialog-header {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 24px;
  background: linear-gradient(
    135deg,
    rgba(0, 31, 61, 0.02) 0%,
    rgba(237, 152, 95, 0.02) 100%
  );
  border-bottom: 1px solid rgba(0, 31, 61, 0.08);
}

.dialog-icon-wrapper {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 8px rgba(33, 150, 243, 0.25);

  &.info {
    background: linear-gradient(
      135deg,
      rgba(33, 150, 243, 0.2) 0%,
      rgba(33, 150, 243, 0.15) 100%
    );
    .v-icon {
      color: #2196f3 !important;
    }
  }
}

.dialog-title {
  font-size: 20px;
  font-weight: 700;
  color: #001f3d;
  margin-bottom: 4px;
}

.dialog-subtitle {
  font-size: 13px;
  color: rgba(0, 31, 61, 0.6);
}

.dialog-divider {
  height: 1px;
  background: rgba(0, 31, 61, 0.08);
}

.dialog-actions {
  padding: 16px 24px;
  background: rgba(0, 31, 61, 0.02);
  border-top: 1px solid rgba(0, 31, 61, 0.08);
  display: flex;
  justify-content: flex-end;
  gap: 12px;
}

.dialog-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  padding: 10px 20px;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;

  &.dialog-btn-cancel {
    background: rgba(0, 31, 61, 0.06);
    color: rgba(0, 31, 61, 0.8);
    border: 1px solid rgba(0, 31, 61, 0.1);

    &:hover {
      background: rgba(0, 31, 61, 0.1);
    }
  }

  &.dialog-btn-primary {
    background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(237, 152, 95, 0.3);

    .v-icon {
      color: #ffffff !important;
    }

    &:not(:disabled):hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(237, 152, 95, 0.4);
    }

    &:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
  }
}

@keyframes rotate {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.rotating {
  animation: rotate 1s linear infinite;
}
</style>
