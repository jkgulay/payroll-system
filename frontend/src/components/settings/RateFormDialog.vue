<template>
  <v-dialog
    :model-value="modelValue"
    @update:model-value="$emit('update:modelValue', $event)"
    max-width="600"
    persistent
  >
    <v-card>
      <v-card-title class="text-h5">
        {{ rate ? "Edit" : "Add" }} {{ typeLabel }} Rate
      </v-card-title>

      <v-divider></v-divider>

      <v-card-text>
        <v-form ref="formRef" @submit.prevent="submit">
          <v-row>
            <!-- Name -->
            <v-col cols="12">
              <v-text-field
                v-model="form.name"
                label="Rate Name"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
                hint="E.g., 'SSS Bracket 1', 'PhilHealth Standard Rate'"
                persistent-hint
              ></v-text-field>
            </v-col>

            <!-- Salary Range -->
            <v-col cols="12" md="6">
              <v-text-field
                v-model.number="form.min_salary"
                label="Minimum Salary"
                type="number"
                step="0.01"
                prefix="₱"
                :rules="[rules.required, rules.positive]"
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                v-model.number="form.max_salary"
                label="Maximum Salary"
                type="number"
                step="0.01"
                prefix="₱"
                :rules="[rules.greaterThanMin]"
                variant="outlined"
                density="comfortable"
                hint="Leave empty for no upper limit"
                persistent-hint
              ></v-text-field>
            </v-col>

            <!-- Employee Contribution -->
            <v-col cols="12">
              <div class="text-subtitle-2 mb-2">Employee Contribution</div>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                v-model.number="form.employee_rate"
                label="Rate (%)"
                type="number"
                step="0.01"
                suffix="%"
                :rules="[rules.rateRange]"
                variant="outlined"
                density="comfortable"
                :disabled="!!form.employee_fixed"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                v-model.number="form.employee_fixed"
                label="Fixed Amount"
                type="number"
                step="0.01"
                prefix="₱"
                variant="outlined"
                density="comfortable"
                :disabled="!!form.employee_rate"
              ></v-text-field>
            </v-col>

            <!-- Employer Contribution -->
            <v-col cols="12">
              <div class="text-subtitle-2 mb-2">Employer Contribution</div>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                v-model.number="form.employer_rate"
                label="Rate (%)"
                type="number"
                step="0.01"
                suffix="%"
                :rules="[rules.rateRange]"
                variant="outlined"
                density="comfortable"
                :disabled="!!form.employer_fixed"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                v-model.number="form.employer_fixed"
                label="Fixed Amount"
                type="number"
                step="0.01"
                prefix="₱"
                variant="outlined"
                density="comfortable"
                :disabled="!!form.employer_rate"
              ></v-text-field>
            </v-col>

            <!-- Total Contribution (Optional) -->
            <v-col cols="12">
              <v-text-field
                v-model.number="form.total_contribution"
                label="Total Contribution (Optional)"
                type="number"
                step="0.01"
                prefix="₱"
                variant="outlined"
                density="comfortable"
                hint="If specified, will override calculated total"
                persistent-hint
              ></v-text-field>
            </v-col>

            <!-- Effective Dates -->
            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.effective_date"
                label="Effective Date"
                type="date"
                :rules="[rules.required]"
                variant="outlined"
                density="comfortable"
              ></v-text-field>
            </v-col>

            <v-col cols="12" md="6">
              <v-text-field
                v-model="form.end_date"
                label="End Date"
                type="date"
                :rules="[rules.afterEffectiveDate]"
                variant="outlined"
                density="comfortable"
                hint="Leave empty if currently active"
                persistent-hint
              ></v-text-field>
            </v-col>

            <!-- Status -->
            <v-col cols="12">
              <v-switch
                v-model="form.is_active"
                label="Active"
                color="primary"
                hide-details
              ></v-switch>
            </v-col>

            <!-- Notes -->
            <v-col cols="12">
              <v-textarea
                v-model="form.notes"
                label="Notes (Optional)"
                variant="outlined"
                density="comfortable"
                rows="2"
              ></v-textarea>
            </v-col>
          </v-row>
        </v-form>
      </v-card-text>

      <v-divider></v-divider>

      <v-card-actions>
        <v-spacer></v-spacer>
        <v-btn variant="text" @click="$emit('cancel')"> Cancel </v-btn>
        <v-btn color="primary" @click="submit" :loading="loading">
          {{ rate ? "Update" : "Add" }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, watch, computed } from "vue";

const props = defineProps({
  modelValue: {
    type: Boolean,
    required: true,
  },
  rate: {
    type: Object,
    default: null,
  },
  type: {
    type: String,
    required: true,
  },
});

const emit = defineEmits(["update:modelValue", "save", "cancel"]);

const formRef = ref(null);
const loading = ref(false);

const typeLabel = computed(() => {
  const labels = {
    sss: "SSS",
    philhealth: "PhilHealth",
    pagibig: "Pag-IBIG",
    tax: "Tax",
  };
  return labels[props.type] || props.type;
});

const form = ref({
  type: props.type,
  name: "",
  min_salary: null,
  max_salary: null,
  employee_rate: null,
  employee_fixed: null,
  employer_rate: null,
  employer_fixed: null,
  total_contribution: null,
  effective_date: new Date().toISOString().split("T")[0],
  end_date: null,
  is_active: true,
  notes: "",
});

// Validation rules
const rules = {
  required: (v) => !!v || "This field is required",
  positive: (v) => v >= 0 || "Must be positive",
  rateRange: (v) =>
    !v || (v >= 0 && v <= 100) || "Rate must be between 0 and 100",
  greaterThanMin: (v) => {
    if (!v) return true;
    return (
      !form.value.min_salary ||
      v > form.value.min_salary ||
      "Must be greater than minimum salary"
    );
  },
  afterEffectiveDate: (v) => {
    if (!v) return true;
    return (
      !form.value.effective_date ||
      v > form.value.effective_date ||
      "Must be after effective date"
    );
  },
};

// Watch for rate and type prop changes
watch(
  [() => props.rate, () => props.type],
  ([newRate, newType]) => {
    if (newRate) {
      form.value = {
        ...newRate,
        type: newType, // Ensure type is always set from props
        effective_date:
          newRate.effective_date?.split("T")[0] ||
          new Date().toISOString().split("T")[0],
        end_date: newRate.end_date?.split("T")[0] || null,
      };
    } else {
      form.value = {
        type: newType,
        name: "",
        min_salary: null,
        max_salary: null,
        employee_rate: null,
        employee_fixed: null,
        employer_rate: null,
        employer_fixed: null,
        total_contribution: null,
        effective_date: new Date().toISOString().split("T")[0],
        end_date: null,
        is_active: true,
        notes: "",
      };
    }
  },
  { immediate: true }
);

const submit = async () => {
  const { valid } = await formRef.value.validate();
  if (!valid) return;

  loading.value = true;
  try {
    // Ensure type is always set from props
    const rateData = {
      ...form.value,
      type: props.type,
    };
    emit("save", rateData);
  } finally {
    loading.value = false;
  }
};
</script>
