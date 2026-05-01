<template>
  <v-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    max-width="500"
    persistent
  >
    <v-card class="modern-dialog">
      <div class="dialog-header">
        <div class="dialog-icon-wrapper warning">
          <v-icon size="20">mdi-account-alert</v-icon>
        </div>
        <div>
          <div class="dialog-title">Mark Absent</div>
          <div class="dialog-subtitle">
            Mark employees without records as absent
          </div>
        </div>
      </div>

      <v-card-text class="pt-6">
        <v-form ref="form" v-model="valid">
          <v-row>
            <v-col cols="12">
              <v-text-field
                v-model="date"
                label="Date *"
                type="date"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-calendar"
                :max="today"
              ></v-text-field>
            </v-col>

            <v-col cols="12">
              <v-checkbox
                v-model="excludeOnLeave"
                label="Exclude employees on approved leave"
                density="compact"
                hide-details
              ></v-checkbox>
            </v-col>

            <v-col cols="12">
              <v-alert type="info" variant="tonal" density="compact">
                This will mark all employees without attendance records for the
                selected date as absent.
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
          class="dialog-btn dialog-btn-warning"
          @click="markAbsent"
          :disabled="!valid || marking"
        >
          <v-icon v-if="marking" size="16" class="rotating">mdi-loading</v-icon>
          <v-icon v-else size="16">mdi-account-alert</v-icon>
          <span>{{ marking ? "Marking..." : "Mark Absent" }}</span>
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

const emit = defineEmits(["update:modelValue", "marked"]);
const toast = useToast();

const form = ref(null);
const valid = ref(false);
const marking = ref(false);
const date = ref(new Date().toISOString().split("T")[0]);
const excludeOnLeave = ref(true);

const today = new Date().toISOString().split("T")[0];

const rules = {
  required: (v) => !!v || "This field is required",
};

const markAbsent = async () => {
  if (!valid.value) return;

  marking.value = true;
  try {
    const result = await attendanceService.markAbsent({
      date: date.value,
      exclude_on_leave: excludeOnLeave.value,
    });
    emit("marked", result);
    close();
  } catch (error) {
    toast.error(error.response?.data?.message || "Failed to mark absent");
  } finally {
    marking.value = false;
  }
};

const close = () => {
  date.value = today;
  excludeOnLeave.value = true;
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
  box-shadow: 0 2px 8px rgba(255, 152, 0, 0.25);

  &.warning {
    background: linear-gradient(
      135deg,
      rgba(255, 152, 0, 0.2) 0%,
      rgba(255, 152, 0, 0.15) 100%
    );
    .v-icon {
      color: #ff9800 !important;
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

  &.dialog-btn-warning {
    background: linear-gradient(135deg, #ff9800 0%, #ffb74d 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(255, 152, 0, 0.3);

    .v-icon {
      color: #ffffff !important;
    }

    &:not(:disabled):hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(255, 152, 0, 0.4);
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
