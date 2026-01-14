<template>
  <v-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    max-width="600"
    persistent
  >
    <v-card>
      <v-card-title class="text-h5 bg-success">
        <v-icon start>mdi-file-document</v-icon>
        Generate Daily Time Record (DTR)
      </v-card-title>

      <v-card-text class="pt-6">
        <v-alert type="info" variant="tonal" class="mb-4">
          Generate a Daily Time Record (DTR) for an employee with signature
          section. Perfect for daily attendance forms or period summaries.
        </v-alert>

        <v-form ref="form" v-model="valid">
          <v-row>
            <v-col cols="12">
              <v-autocomplete
                v-model="formData.employee_id"
                :items="employees"
                item-title="full_name"
                item-value="id"
                label="Select Employee *"
                :rules="[rules.required]"
                :loading="loadingEmployees"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-account"
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
              <v-radio-group v-model="reportType" inline>
                <v-radio
                  label="Period Range (Multiple Days)"
                  value="range"
                ></v-radio>
                <v-radio label="Single Day" value="daily"></v-radio>
              </v-radio-group>
            </v-col>

            <v-col cols="12" v-if="reportType === 'daily'">
              <v-text-field
                v-model="formData.date"
                label="Date *"
                type="date"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-calendar"
                :max="today"
              ></v-text-field>
            </v-col>

            <v-col cols="6" v-if="reportType === 'range'">
              <v-text-field
                v-model="formData.date_from"
                label="Date From *"
                type="date"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-calendar-start"
                :max="today"
              ></v-text-field>
            </v-col>

            <v-col cols="6" v-if="reportType === 'range'">
              <v-text-field
                v-model="formData.date_to"
                label="Date To *"
                type="date"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
                prepend-inner-icon="mdi-calendar-end"
                :max="today"
              ></v-text-field>
            </v-col>

            <v-col cols="12" v-if="reportType === 'range'">
              <v-btn
                block
                variant="tonal"
                @click="setThisMonth"
                prepend-icon="mdi-calendar-month"
              >
                This Month
              </v-btn>
            </v-col>
          </v-row>
        </v-form>

        <v-alert v-if="preview" type="success" variant="tonal" class="mt-4">
          <div class="text-subtitle-2 font-weight-bold mb-2">Preview</div>
          <div><strong>Employee:</strong> {{ preview.employee.full_name }}</div>
          <div><strong>Period:</strong> {{ formatPreviewPeriod() }}</div>
          <div>
            <strong>Total Days Present:</strong>
            {{ preview.totals.days_present }}
          </div>
          <div>
            <strong>Total Hours:</strong> {{ preview.totals.regular_hours }} hrs
          </div>
          <div>
            <strong>Overtime:</strong> {{ preview.totals.overtime_hours }} hrs
          </div>
        </v-alert>
      </v-card-text>

      <v-card-actions>
        <v-btn
          text
          @click="loadPreview"
          :loading="previewing"
          :disabled="!valid || reportType === 'daily'"
          v-if="reportType === 'range'"
        >
          Preview
        </v-btn>
        <v-spacer></v-spacer>
        <v-btn text @click="close">Cancel</v-btn>
        <v-btn
          color="success"
          @click="generate"
          :loading="generating"
          :disabled="!valid"
          prepend-icon="mdi-download"
        >
          Generate PDF
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, reactive, watch, onMounted } from "vue";
import api from "@/services/api";
import { useToast } from "vue-toastification";

const props = defineProps({
  modelValue: Boolean,
});

const emit = defineEmits(["update:modelValue"]);
const toast = useToast();

const form = ref(null);
const valid = ref(false);
const generating = ref(false);
const previewing = ref(false);
const loadingEmployees = ref(false);
const employees = ref([]);
const reportType = ref("range");
const preview = ref(null);

const today = new Date().toISOString().split("T")[0];

const formData = reactive({
  employee_id: null,
  date_from: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000)
    .toISOString()
    .split("T")[0],
  date_to: today,
  date: today,
});

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

const setThisMonth = () => {
  const now = new Date();
  const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
  formData.date_from = firstDay.toISOString().split("T")[0];
  formData.date_to = today;
};

const loadPreview = async () => {
  if (!valid.value || reportType.value === "daily") return;

  previewing.value = true;
  try {
    const response = await api.post("/attendance/dtr/preview", {
      employee_id: formData.employee_id,
      date_from: formData.date_from,
      date_to: formData.date_to,
    });
    preview.value = response.data;
  } catch (error) {
    toast.error("Failed to load preview");
  } finally {
    previewing.value = false;
  }
};

const formatPreviewPeriod = () => {
  if (!preview.value) return "";
  return reportType.value === "daily"
    ? new Date(formData.date).toLocaleDateString()
    : `${new Date(formData.date_from).toLocaleDateString()} - ${new Date(
        formData.date_to
      ).toLocaleDateString()}`;
};

const generate = async () => {
  if (!valid.value) return;

  generating.value = true;
  try {
    const endpoint =
      reportType.value === "daily"
        ? "/attendance/dtr/generate-daily"
        : "/attendance/dtr/generate";

    const payload =
      reportType.value === "daily"
        ? { employee_id: formData.employee_id, date: formData.date }
        : {
            employee_id: formData.employee_id,
            date_from: formData.date_from,
            date_to: formData.date_to,
          };

    const response = await api.post(endpoint, payload, {
      responseType: "blob",
    });

    // Create download link
    const url = window.URL.createObjectURL(new Blob([response.data]));
    const link = document.createElement("a");
    link.href = url;

    const employee = employees.value.find((e) => e.id === formData.employee_id);
    const filename =
      reportType.value === "daily"
        ? `DTR_${employee?.employee_number}_${formData.date}.pdf`
        : `DTR_${employee?.employee_number}_${formData.date_from}_${formData.date_to}.pdf`;

    link.setAttribute("download", filename);
    document.body.appendChild(link);
    link.click();
    link.remove();

    toast.success("DTR generated successfully!");
    close();
  } catch (error) {
    console.error("Generation error:", error);
    toast.error(error.response?.data?.message || "Failed to generate DTR");
  } finally {
    generating.value = false;
  }
};

const close = () => {
  preview.value = null;
  emit("update:modelValue", false);
};

watch(
  () => props.modelValue,
  (newVal) => {
    if (newVal) {
      // Reset form
      formData.employee_id = null;
      formData.date = today;
      formData.date_from = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000)
        .toISOString()
        .split("T")[0];
      formData.date_to = today;
      reportType.value = "range";
      preview.value = null;
    }
  }
);

onMounted(() => {
  loadEmployees();
});
</script>
