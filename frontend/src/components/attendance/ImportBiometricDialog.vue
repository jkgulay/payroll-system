<template>
  <v-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    max-width="600"
    persistent
  >
    <v-card>
      <v-card-title class="text-h5 bg-info">
        Import Biometric Data
      </v-card-title>

      <v-card-text class="pt-6">
        <v-form ref="form" v-model="valid">
          <v-row>
            <v-col cols="12">
              <v-file-input
                v-model="file"
                label="Biometric File *"
                accept=".csv,.txt"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
                prepend-icon="mdi-file-upload"
                show-size
              >
                <template v-slot:append>
                  <v-tooltip
                    text="Upload CSV or TXT file exported from biometric device"
                  >
                    <template v-slot:activator="{ props }">
                      <v-icon v-bind="props">mdi-help-circle</v-icon>
                    </template>
                  </v-tooltip>
                </template>
              </v-file-input>
            </v-col>

            <v-col cols="12">
              <v-select
                v-model="fileType"
                :items="fileTypes"
                label="File Type *"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
                prepend-icon="mdi-file-document"
              ></v-select>
            </v-col>

            <v-col cols="6">
              <v-text-field
                v-model="dateFrom"
                label="Date From"
                type="date"
                variant="outlined"
                density="comfortable"
                prepend-icon="mdi-calendar"
              ></v-text-field>
            </v-col>

            <v-col cols="6">
              <v-text-field
                v-model="dateTo"
                label="Date To"
                type="date"
                variant="outlined"
                density="comfortable"
                prepend-icon="mdi-calendar"
              ></v-text-field>
            </v-col>

            <v-col cols="12">
              <v-alert type="info" variant="tonal" density="compact">
                <div class="text-body-2">
                  <strong>CSV Format:</strong>
                  Badge,Date,Time,State,Person<br />
                  <strong>Text Format:</strong> UserID DateTime InOut<br />
                  <strong>State:</strong> 0=In, 1=Out, 2=Break-In, 3=Break-Out
                </div>
              </v-alert>
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>

      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn text @click="close">Cancel</v-btn>
        <v-btn
          color="info"
          @click="importFile"
          :loading="importing"
          :disabled="!valid"
        >
          Import
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

const emit = defineEmits(["update:modelValue", "imported"]);
const toast = useToast();

const form = ref(null);
const valid = ref(false);
const importing = ref(false);
const file = ref(null);
const fileType = ref("csv");
const dateFrom = ref("");
const dateTo = ref("");

const fileTypes = [
  { title: "CSV File", value: "csv" },
  { title: "Text File", value: "text" },
];

const rules = {
  required: (v) => !!v || "This field is required",
};

const importFile = async () => {
  if (!valid.value || !file.value) return;

  importing.value = true;
  try {
    const formData = new FormData();
    formData.append("file", file.value[0]);
    formData.append("file_type", fileType.value);
    if (dateFrom.value) formData.append("date_from", dateFrom.value);
    if (dateTo.value) formData.append("date_to", dateTo.value);

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
  fileType.value = "csv";
  dateFrom.value = "";
  dateTo.value = "";
  emit("update:modelValue", false);
};
</script>
