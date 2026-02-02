<template>
  <v-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    max-width="600"
    persistent
  >
    <v-card class="modern-dialog">
      <div class="dialog-header">
        <div class="dialog-icon-wrapper">
          <v-icon size="20">{{
            attendance ? "mdi-pencil" : "mdi-plus"
          }}</v-icon>
        </div>
        <div>
          <div class="dialog-title">
            {{ attendance ? "Edit" : "Create" }} Attendance
          </div>
          <div class="dialog-subtitle">
            {{ attendance ? "Update" : "Add new" }} attendance record
          </div>
        </div>
      </div>

      <v-card-text class="pt-6">
        <v-form ref="form" v-model="valid">
          <v-row>
            <v-col cols="12">
              <v-autocomplete
                v-model="formData.employee_id"
                :items="employees"
                item-title="full_name"
                item-value="id"
                label="Employee *"
                :rules="[rules.required]"
                :loading="loadingEmployees"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-account"
                :disabled="!!attendance"
              >
                <template v-slot:item="{ props, item }">
                  <v-list-item v-bind="props">
                    <template v-slot:subtitle>
                      {{ item.raw.employee_number }} - {{ item.raw.position }}
                    </template>
                  </v-list-item>
                </template>
              </v-autocomplete>
            </v-col>

            <v-col cols="12">
              <v-text-field
                v-model="formData.attendance_date"
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
              <v-alert
                type="info"
                density="compact"
                variant="tonal"
                class="mb-2"
              >
                <strong>Standard Schedule:</strong> 8:00 AM - 12:00 PM (Morning)
                | 1:00 PM - 5:00 PM (Afternoon)
              </v-alert>
            </v-col>

            <v-col cols="12" class="pb-0">
              <div class="text-subtitle-2 font-weight-bold mb-2">
                Morning Shift
              </div>
            </v-col>

            <v-col cols="6">
              <v-text-field
                v-model="formData.time_in"
                label="Time In (AM)"
                type="time"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-clock-in"
                hint="e.g., 08:00 AM"
              ></v-text-field>
            </v-col>

            <v-col cols="6">
              <v-text-field
                v-model="formData.break_start"
                label="Lunch Break Start"
                type="time"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-food"
                hint="e.g., 12:00 PM"
              ></v-text-field>
            </v-col>

            <v-col cols="12" class="pb-0 pt-2">
              <v-divider></v-divider>
              <div class="text-subtitle-2 font-weight-bold my-2">
                Afternoon Shift
              </div>
            </v-col>

            <v-col cols="6">
              <v-text-field
                v-model="formData.break_end"
                label="Lunch Break End"
                type="time"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-food-off"
                hint="e.g., 01:00 PM"
              ></v-text-field>
            </v-col>

            <v-col cols="6">
              <v-text-field
                v-model="formData.time_out"
                label="Time Out (PM)"
                type="time"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-clock-out"
                hint="e.g., 05:00 PM"
              ></v-text-field>
            </v-col>

            <v-col cols="12">
              <v-textarea
                v-model="formData.notes"
                label="Notes / Reason"
                variant="outlined"
                density="comfortable"
                rows="3"
                prepend-inner-icon="mdi-note"
              ></v-textarea>
            </v-col>

            <v-col cols="12" v-if="!attendance">
              <v-checkbox
                v-model="formData.requires_approval"
                label="Requires approval"
                density="compact"
                hide-details
              ></v-checkbox>
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
          @click="save"
          :disabled="!valid || saving"
        >
          <v-icon v-if="saving" size="16" class="rotating">mdi-loading</v-icon>
          <v-icon v-else size="16">{{
            attendance ? "mdi-check" : "mdi-plus"
          }}</v-icon>
          <span>{{
            saving ? "Saving..." : attendance ? "Update" : "Create"
          }}</span>
        </button>
      </div>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, reactive, watch, onMounted } from "vue";
import { useAttendanceStore } from "@/stores/attendance";
import api from "@/services/api";
import { useToast } from "vue-toastification";

const props = defineProps({
  modelValue: Boolean,
  attendance: Object,
  prefilledDate: String,
});

const emit = defineEmits(["update:modelValue", "saved"]);
const toast = useToast();
const attendanceStore = useAttendanceStore();

const form = ref(null);
const valid = ref(false);
const saving = ref(false);
const loadingEmployees = ref(false);
const employees = ref([]);

const today = new Date().toISOString().split("T")[0];

const formData = reactive({
  employee_id: null,
  attendance_date: props.prefilledDate || today,
  time_in: "",
  time_out: "",
  break_start: "",
  break_end: "",
  notes: "",
  requires_approval: true,
});

const rules = {
  required: (v) => !!v || "This field is required",
};

const loadEmployees = async () => {
  loadingEmployees.value = true;
  try {
    const perPage = 200;
    let page = 1;
    let allEmployees = [];
    let lastPage = 1;

    do {
      const response = await api.get("/employees", {
        params: { per_page: perPage, page },
      });
      const data = response.data?.data || response.data || [];
      allEmployees = allEmployees.concat(data);
      lastPage = response.data?.last_page || 1;
      page += 1;
    } while (page <= lastPage);

    employees.value = allEmployees;
  } catch (error) {
    toast.error("Failed to load employees");
  } finally {
    loadingEmployees.value = false;
  }
};

const save = async () => {
  if (!valid.value) return;

  saving.value = true;
  try {
    // Convert time format to H:i:s if provided, null if empty
    const data = {
      ...formData,
      time_in: formData.time_in ? formData.time_in + ":00" : null,
      time_out: formData.time_out ? formData.time_out + ":00" : null,
      break_start: formData.break_start ? formData.break_start + ":00" : null,
      break_end: formData.break_end ? formData.break_end + ":00" : null,
      notes: formData.notes || null,
    };

    // Remove null values to avoid sending them
    Object.keys(data).forEach((key) => {
      if (data[key] === null || data[key] === "") {
        delete data[key];
      }
    });

    if (props.attendance) {
      await attendanceStore.updateAttendance(props.attendance.id, data);
      toast.success("Attendance updated successfully");
    } else {
      await attendanceStore.createAttendance(data);
      toast.success("Attendance created successfully");
    }

    emit("saved");
  } catch (error) {
    console.error("Save error:", error.response?.data);

    // Handle duplicate record case
    if (
      error.response?.status === 422 &&
      error.response?.data?.action === "exists"
    ) {
      const existingRecord = error.response.data.attendance;
      const confirmed = await new Promise((resolve) => {
        if (
          confirm(
            `An attendance record already exists for this employee on this date.\n\n` +
              `Existing record: ${existingRecord.status} (${
                existingRecord.time_in || "No time in"
              } - ${existingRecord.time_out || "No time out"})\n\n` +
              `Would you like to update the existing record instead?`,
          )
        ) {
          resolve(true);
        } else {
          resolve(false);
        }
      });

      if (confirmed) {
        try {
          await attendanceStore.updateAttendance(existingRecord.id, data);
          toast.success("Attendance updated successfully");
          emit("saved");
        } catch (updateError) {
          console.error("Update error:", updateError.response?.data);
          toast.error(
            updateError.response?.data?.message ||
              "Failed to update attendance",
          );
        }
      }
    } else {
      toast.error(error.response?.data?.message || "Failed to save attendance");
    }
  } finally {
    saving.value = false;
  }
};

const close = () => {
  emit("update:modelValue", false);
};

watch(
  () => props.modelValue,
  (newVal) => {
    if (newVal) {
      if (props.attendance) {
        // Edit mode - populate form
        Object.assign(formData, {
          employee_id: props.attendance.employee_id,
          attendance_date: props.attendance.attendance_date,
          time_in: props.attendance.time_in?.substring(0, 5) || "",
          time_out: props.attendance.time_out?.substring(0, 5) || "",
          break_start: props.attendance.break_start?.substring(0, 5) || "",
          break_end: props.attendance.break_end?.substring(0, 5) || "",
          notes:
            props.attendance.manual_reason ||
            props.attendance.edit_reason ||
            "",
        });
      } else {
        // Create mode - reset form
        Object.assign(formData, {
          employee_id: null,
          attendance_date: props.prefilledDate || today,
          time_in: "",
          time_out: "",
          break_start: "",
          break_end: "",
          notes: "",
          requires_approval: true,
        });
      }
    }
  },
);

onMounted(() => {
  loadEmployees();
});
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
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.25);

  .v-icon {
    color: #ffffff !important;
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
