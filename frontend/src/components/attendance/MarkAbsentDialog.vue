<template>
  <v-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    max-width="700"
    persistent
  >
    <v-card>
      <v-card-title class="text-h5 bg-warning">Mark Absent</v-card-title>

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

      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn text @click="close">Cancel</v-btn>
        <v-btn
          color="warning"
          @click="markAbsent"
          :loading="marking"
          :disabled="!valid"
        >
          Mark Absent
        </v-btn>
      </v-card-actions>
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
