<template>
  <v-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    max-width="500"
    persistent
  >
    <v-card>
      <v-card-title class="text-h5 bg-error">Reject Attendance</v-card-title>

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

      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn text @click="close">Cancel</v-btn>
        <v-btn
          color="error"
          @click="reject"
          :loading="rejecting"
          :disabled="!valid"
        >
          Reject
        </v-btn>
      </v-card-actions>
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
  }
);
</script>
