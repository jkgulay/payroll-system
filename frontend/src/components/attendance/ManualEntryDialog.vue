<template>
  <v-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    max-width="600"
    persistent
  >
    <v-card>
      <v-card-title class="text-h5 bg-primary">
        {{ attendance ? "Edit" : "Create" }} Attendance
      </v-card-title>

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

            <v-col cols="6">
              <v-text-field
                v-model="formData.time_in"
                label="Time In"
                type="time"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-clock-in"
              ></v-text-field>
            </v-col>

            <v-col cols="6">
              <v-text-field
                v-model="formData.time_out"
                label="Time Out"
                type="time"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-clock-out"
              ></v-text-field>
            </v-col>

            <v-col cols="6">
              <v-text-field
                v-model="formData.break_start"
                label="Break Start"
                type="time"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-coffee"
              ></v-text-field>
            </v-col>

            <v-col cols="6">
              <v-text-field
                v-model="formData.break_end"
                label="Break End"
                type="time"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-coffee-off"
              ></v-text-field>
            </v-col>

            <v-col cols="12">
              <v-select
                v-model="formData.status"
                :items="statusOptions"
                label="Status *"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-clipboard-check"
              ></v-select>
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

      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn text @click="close">Cancel</v-btn>
        <v-btn
          color="primary"
          @click="save"
          :loading="saving"
          :disabled="!valid"
        >
          {{ attendance ? "Update" : "Create" }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, reactive, watch, onMounted } from "vue";
import attendanceService from "@/services/attendanceService";
import api from "@/services/api";
import { useToast } from "vue-toastification";

const props = defineProps({
  modelValue: Boolean,
  attendance: Object,
  prefilledDate: String,
});

const emit = defineEmits(["update:modelValue", "saved"]);
const toast = useToast();

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
  status: "present",
  notes: "",
  requires_approval: true,
});

const statusOptions = [
  { title: "Present", value: "present" },
  { title: "Absent", value: "absent" },
  { title: "Late", value: "late" },
  { title: "Half Day", value: "half_day" },
  { title: "On Leave", value: "on_leave" },
];

const rules = {
  required: (v) => !!v || "This field is required",
};

const loadEmployees = async () => {
  loadingEmployees.value = true;
  try {
    const response = await api.get("/employees");
    employees.value = response.data.data || response.data || [];
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
      await attendanceService.updateAttendance(props.attendance.id, data);
    } else {
      await attendanceService.createAttendance(data);
    }

    emit("saved");
  } catch (error) {
    console.error("Save error:", error.response?.data);
    toast.error(error.response?.data?.message || "Failed to save attendance");
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
          status: props.attendance.status,
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
          status: "present",
          notes: "",
          requires_approval: true,
        });
      }
    }
  }
);

onMounted(() => {
  loadEmployees();
});
</script>
