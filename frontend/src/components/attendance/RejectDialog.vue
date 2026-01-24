<template>
  <v-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    max-width="500"
    persistent
  >
    <v-card class="modern-dialog">
      <div class="dialog-header">
        <div class="dialog-icon-wrapper danger">
          <v-icon size="20">mdi-close-circle</v-icon>
        </div>
        <div>
          <div class="dialog-title">Reject Attendance</div>
          <div class="dialog-subtitle">Provide a reason for rejection</div>
        </div>
      </div>

      <v-card-text class="pt-6">
        <v-form ref="form" v-model="valid">
          <v-textarea
            v-model="reason"
            label="Rejection Reason *"
            :rules="[rules.required]"
            variant="outlined"
            rows="4"
            auto-grow
            prepend-inner-icon="mdi-comment-alert"
          ></v-textarea>
        </v-form>
      </v-card-text>

      <div class="dialog-divider"></div>
      <div class="dialog-actions">
        <button class="dialog-btn dialog-btn-cancel" @click="close">
          Cancel
        </button>
        <button
          class="dialog-btn dialog-btn-danger"
          @click="reject"
          :disabled="!valid || rejecting"
        >
          <v-icon v-if="rejecting" size="16" class="rotating"
            >mdi-loading</v-icon
          >
          <v-icon v-else size="16">mdi-close-circle</v-icon>
          <span>{{ rejecting ? "Rejecting..." : "Reject" }}</span>
        </button>
      </div>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, watch } from "vue";
import attendanceService from "@/services/attendanceService";
import { useToast } from "vue-toastification";

const props = defineProps({
  modelValue: Boolean,
  attendance: Object,
});

const emit = defineEmits(["update:modelValue", "rejected"]);
const toast = useToast();

const form = ref(null);
const valid = ref(false);
const rejecting = ref(false);
const reason = ref("");

const rules = {
  required: (v) => !!v || "Reason is required",
};

const reject = async () => {
  if (!valid.value || !props.attendance) return;

  rejecting.value = true;
  try {
    await attendanceService.reject(props.attendance.id, reason.value);
    emit("rejected");
    close();
  } catch (error) {
    toast.error(error.response?.data?.message || "Failed to reject attendance");
  } finally {
    rejecting.value = false;
  }
};

const close = () => {
  reason.value = "";
  emit("update:modelValue", false);
};

watch(
  () => props.modelValue,
  (newVal) => {
    if (!newVal) {
      reason.value = "";
    }
  },
);
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
  box-shadow: 0 2px 8px rgba(244, 67, 54, 0.25);

  &.danger {
    background: linear-gradient(
      135deg,
      rgba(244, 67, 54, 0.2) 0%,
      rgba(244, 67, 54, 0.15) 100%
    );
    .v-icon {
      color: #f44336 !important;
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

  &.dialog-btn-danger {
    background: linear-gradient(135deg, #f44336 0%, #e57373 100%);
    color: #ffffff;
    box-shadow: 0 2px 8px rgba(244, 67, 54, 0.3);

    .v-icon {
      color: #ffffff !important;
    }

    &:not(:disabled):hover {
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(244, 67, 54, 0.4);
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
