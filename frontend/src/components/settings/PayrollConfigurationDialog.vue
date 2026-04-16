<template>
  <v-dialog v-model="isOpen" max-width="900" persistent scrollable>
    <v-card class="payroll-config-dialog">
      <!-- Header -->
      <v-card-title class="dialog-header">
        <div class="header-content">
          <div class="icon-wrapper">
            <v-icon size="28" color="white">mdi-currency-usd</v-icon>
          </div>
          <div>
            <h2 class="dialog-title">Payroll Configuration</h2>
            <p class="dialog-subtitle">
              Configure overtime rates and payroll calculation settings
            </p>
          </div>
        </div>
        <v-btn icon variant="text" @click="closeDialog" class="close-btn">
          <v-icon>mdi-close</v-icon>
        </v-btn>
      </v-card-title>

      <v-divider />

      <!-- Content -->
      <v-card-text class="dialog-content">
        <v-tabs v-model="activeTab" class="config-tabs">
          <v-tab value="overtime">
            <v-icon start>mdi-clock-plus</v-icon>
            Overtime Rates
          </v-tab>
          <v-tab value="holidays">
            <v-icon start>mdi-calendar-star</v-icon>
            Holiday Rates
          </v-tab>
          <v-tab value="daily-cap">
            <v-icon start>mdi-timer-lock-outline</v-icon>
            Daily OT Cap
          </v-tab>
        </v-tabs>

        <v-window v-model="activeTab" class="config-window">
          <!-- Overtime Rates Tab -->
          <v-window-item value="overtime">
            <div class="config-section">
              <div class="section-header">
                <h3 class="section-title">Overtime Rate Multipliers</h3>
                <p class="section-description">
                  Configure the multipliers applied to the base hourly rate for
                  overtime work
                </p>
              </div>

              <div class="rate-cards">
                <!-- Regular Day OT -->
                <div class="rate-card">
                  <div class="rate-icon regular">
                    <v-icon size="24">mdi-calendar</v-icon>
                  </div>
                  <div class="rate-details">
                    <h4 class="rate-label">Regular Day Overtime</h4>
                    <p class="rate-formula">
                      Formula: rate/8 × multiplier × hours
                    </p>
                    <div class="rate-input-group">
                      <v-text-field
                        v-model.number="config.overtime.regularDay"
                        type="number"
                        step="0.01"
                        variant="outlined"
                        density="compact"
                        suffix="×"
                        hide-details
                        class="rate-input"
                      />
                      <div class="rate-example">
                        Example: ₱71.25/hr × {{ config.overtime.regularDay }} =
                        ₱{{
                          (71.25 * config.overtime.regularDay).toFixed(2)
                        }}/hr
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Sunday OT -->
                <div class="rate-card">
                  <div class="rate-icon sunday">
                    <v-icon size="24">mdi-calendar-weekend</v-icon>
                  </div>
                  <div class="rate-details">
                    <h4 class="rate-label">Sunday Overtime</h4>
                    <p class="rate-formula">
                      Formula: rate/8 × multiplier × hours
                    </p>
                    <div class="rate-input-group">
                      <v-text-field
                        v-model.number="config.overtime.sunday"
                        type="number"
                        step="0.01"
                        variant="outlined"
                        density="compact"
                        suffix="×"
                        hide-details
                        class="rate-input"
                      />
                      <div class="rate-example">
                        Example: ₱71.25/hr × {{ config.overtime.sunday }} = ₱{{
                          (71.25 * config.overtime.sunday).toFixed(2)
                        }}/hr
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </v-window-item>

          <!-- Holiday Rates Tab -->
          <v-window-item value="holidays">
            <div class="config-section">
              <div class="section-header">
                <h3 class="section-title">Holiday Overtime Rate Multipliers</h3>
                <p class="section-description">
                  Configure the multipliers applied for overtime work during
                  holidays
                </p>
              </div>
              <div class="rate-cards">
                <!-- Regular Holiday -->
                <div class="rate-card">
                  <div class="rate-icon holiday-regular">
                    <v-icon size="24">mdi-calendar-star</v-icon>
                  </div>
                  <div class="rate-details">
                    <h4 class="rate-label">Regular Holiday</h4>
                    <p class="rate-formula">
                      Formula: rate/8 × 2 × multiplier × hours
                    </p>
                    <div class="rate-input-group">
                      <v-text-field
                        v-model.number="config.holidays.regularHoliday"
                        type="number"
                        step="0.01"
                        variant="outlined"
                        density="compact"
                        suffix="×"
                        hide-details
                        class="rate-input"
                      />
                      <div class="rate-example">
                        Example: ₱71.25/hr × 2 ×
                        {{ config.holidays.regularHoliday }} = ₱{{
                          (71.25 * 2 * config.holidays.regularHoliday).toFixed(
                            2,
                          )
                        }}/hr
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Regular Holiday on Sunday -->
                <div class="rate-card">
                  <div class="rate-icon holiday-sunday">
                    <v-icon size="24">mdi-calendar-star-outline</v-icon>
                  </div>
                  <div class="rate-details">
                    <h4 class="rate-label">Regular Holiday on Sunday</h4>
                    <p class="rate-formula">
                      Formula: rate/8 × 2 × 1.3 × multiplier × hours
                    </p>
                    <div class="rate-input-group">
                      <v-text-field
                        v-model.number="config.holidays.regularHolidaySunday"
                        type="number"
                        step="0.01"
                        variant="outlined"
                        density="compact"
                        suffix="×"
                        hide-details
                        class="rate-input"
                      />
                      <div class="rate-example">
                        Example: ₱71.25/hr × 2 × 1.3 ×
                        {{ config.holidays.regularHolidaySunday }} = ₱{{
                          (
                            71.25 *
                            2 *
                            1.3 *
                            config.holidays.regularHolidaySunday
                          ).toFixed(2)
                        }}/hr
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Special Holiday -->
                <div class="rate-card">
                  <div class="rate-icon holiday-special">
                    <v-icon size="24">mdi-star-circle</v-icon>
                  </div>
                  <div class="rate-details">
                    <h4 class="rate-label">Special Holiday</h4>
                    <p class="rate-formula">
                      Formula: rate/8 × 1.3 × multiplier × hours
                    </p>
                    <div class="rate-input-group">
                      <v-text-field
                        v-model.number="config.holidays.specialHoliday"
                        type="number"
                        step="0.01"
                        variant="outlined"
                        density="compact"
                        suffix="×"
                        hide-details
                        class="rate-input"
                      />
                      <div class="rate-example">
                        Example: ₱71.25/hr × 1.3 ×
                        {{ config.holidays.specialHoliday }} = ₱{{
                          (
                            71.25 *
                            1.3 *
                            config.holidays.specialHoliday
                          ).toFixed(2)
                        }}/hr
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </v-window-item>

          <v-window-item value="daily-cap">
            <div class="config-section">
              <div class="section-header">
                <h3 class="section-title">2-Hour Daily Overtime Cap</h3>
                <p class="section-description">
                  Apply a fixed maximum of 2 overtime hours per day to selected
                  positions or specific employees.
                </p>
              </div>

              <v-alert type="info" variant="tonal" density="comfortable">
                Employees not selected here keep their original overtime hours.
                When selected, overtime is automatically limited to 2 hours per
                day even if punch records exceed that amount.
              </v-alert>

              <div class="cap-summary">
                <span class="cap-pill">
                  <v-icon size="16" start>mdi-timer-sand</v-icon>
                  Fixed OT Max: 2 hours/day
                </span>
                <span class="cap-pill secondary">
                  <v-icon size="16" start>mdi-account-group</v-icon>
                  {{ config.overtime.dailyCap.positionIds.length }} position(s)
                </span>
                <span class="cap-pill secondary">
                  <v-icon size="16" start>mdi-account-check</v-icon>
                  {{ config.overtime.dailyCap.employeeIds.length }} employee(s)
                </span>
              </div>

              <div class="rate-card cap-target-card">
                <div class="rate-icon regular">
                  <v-icon size="24">mdi-badge-account-horizontal</v-icon>
                </div>
                <div class="rate-details">
                  <h4 class="rate-label">Positions With 2-Hour Daily OT Cap</h4>
                  <p class="rate-formula">
                    Any employee in selected positions will be capped to 2 OT
                    hours per day.
                  </p>
                  <v-select
                    v-model="config.overtime.dailyCap.positionIds"
                    :items="positionOptions"
                    item-title="title"
                    item-value="value"
                    label="Select positions"
                    placeholder="Choose position(s)"
                    prepend-inner-icon="mdi-account-tie"
                    variant="outlined"
                    density="comfortable"
                    multiple
                    chips
                    closable-chips
                    clearable
                    :loading="targetOptionsLoading"
                    :disabled="targetOptionsLoading"
                    hint="Example: Admin, Engineer, General Worker"
                    persistent-hint
                  >
                    <template #item="{ props, item }">
                      <v-list-item v-bind="props" :subtitle="item.raw.subtitle" />
                    </template>
                  </v-select>
                </div>
              </div>

              <div class="rate-card cap-target-card">
                <div class="rate-icon sunday">
                  <v-icon size="24">mdi-account-multiple-check</v-icon>
                </div>
                <div class="rate-details">
                  <h4 class="rate-label">Specific Employees With 2-Hour Daily OT Cap</h4>
                  <p class="rate-formula">
                    Use this for exceptions or employee-specific overrides.
                  </p>
                  <v-select
                    v-model="config.overtime.dailyCap.employeeIds"
                    :items="employeeOptions"
                    item-title="title"
                    item-value="value"
                    label="Select employees"
                    placeholder="Choose employee(s)"
                    prepend-inner-icon="mdi-account"
                    variant="outlined"
                    density="comfortable"
                    multiple
                    chips
                    closable-chips
                    clearable
                    :loading="targetOptionsLoading"
                    :disabled="targetOptionsLoading"
                    hint="Employee-level selection works together with position selection."
                    persistent-hint
                  >
                    <template #item="{ props, item }">
                      <v-list-item v-bind="props" :subtitle="item.raw.subtitle" />
                    </template>
                  </v-select>
                </div>
              </div>
            </div>
          </v-window-item>
        </v-window>
      </v-card-text>

      <v-divider />

      <!-- Actions -->
      <v-card-actions class="dialog-actions">
        <v-btn variant="text" @click="closeDialog" class="cancel-btn">
          Cancel
        </v-btn>
        <v-spacer />
        <v-btn
          color="primary"
          variant="elevated"
          @click="saveConfiguration"
          :loading="saving"
          class="save-btn"
        >
          <v-icon start>mdi-content-save</v-icon>
          Save Configuration
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>

<script setup>
import { ref, watch } from "vue";
import { useToast } from "vue-toastification";
import api from "@/services/api";

const props = defineProps({
  modelValue: Boolean,
});

const emit = defineEmits(["update:modelValue"]);

const toast = useToast();

const isOpen = ref(props.modelValue);
const activeTab = ref("overtime");
const saving = ref(false);
const targetOptionsLoading = ref(false);
const positionOptions = ref([]);
const employeeOptions = ref([]);

const createDefaultConfig = () => ({
  overtime: {
    regularDay: 1.25,
    sunday: 1.3,
    dailyCap: {
      hours: 2,
      positionIds: [],
      employeeIds: [],
    },
  },
  holidays: {
    regularHoliday: 1.3,
    regularHolidaySunday: 1.3,
    specialHoliday: 1.3,
  },
});

const normalizeNumber = (value, fallback) => {
  const normalized = Number(value);
  return Number.isFinite(normalized) ? normalized : fallback;
};

const normalizeIdArray = (value) => {
  if (!Array.isArray(value)) return [];

  return [...new Set(value.map((item) => Number(item)).filter((id) => id > 0))];
};

const normalizeConfig = (payload = {}) => {
  const defaults = createDefaultConfig();
  const overtime = payload?.overtime || {};
  const holidays = payload?.holidays || {};
  const dailyCap = overtime?.dailyCap || {};

  return {
    overtime: {
      regularDay: normalizeNumber(overtime.regularDay, defaults.overtime.regularDay),
      sunday: normalizeNumber(overtime.sunday, defaults.overtime.sunday),
      dailyCap: {
        hours: 2,
        positionIds: normalizeIdArray(dailyCap.positionIds),
        employeeIds: normalizeIdArray(dailyCap.employeeIds),
      },
    },
    holidays: {
      regularHoliday: normalizeNumber(
        holidays.regularHoliday,
        defaults.holidays.regularHoliday,
      ),
      regularHolidaySunday: normalizeNumber(
        holidays.regularHolidaySunday,
        defaults.holidays.regularHolidaySunday,
      ),
      specialHoliday: normalizeNumber(
        holidays.specialHoliday,
        defaults.holidays.specialHoliday,
      ),
    },
  };
};

const config = ref(createDefaultConfig());

watch(
  () => props.modelValue,
  (newVal) => {
    isOpen.value = newVal;
  },
);

watch(isOpen, (newVal) => {
  emit("update:modelValue", newVal);
  if (newVal) {
    initializeDialog();
  }
});

const closeDialog = () => {
  isOpen.value = false;
};

const initializeDialog = async () => {
  await Promise.all([loadConfiguration(), loadTargetOptions()]);
  ensureSelectedTargetsVisible();
};

const ensureSelectedTargetsVisible = () => {
  const existingPositionIds = new Set(positionOptions.value.map((item) => item.value));
  const existingEmployeeIds = new Set(employeeOptions.value.map((item) => item.value));

  (config.value.overtime.dailyCap.positionIds || []).forEach((id) => {
    if (!existingPositionIds.has(id)) {
      positionOptions.value.push({
        title: `Position #${id}`,
        subtitle: "Not active or no longer available",
        value: id,
      });
    }
  });

  (config.value.overtime.dailyCap.employeeIds || []).forEach((id) => {
    if (!existingEmployeeIds.has(id)) {
      employeeOptions.value.push({
        title: `Employee #${id}`,
        subtitle: "Not active or no longer available",
        value: id,
      });
    }
  });
};

const loadTargetOptions = async () => {
  targetOptionsLoading.value = true;

  try {
    const [positionsResponse, employeesResponse] = await Promise.all([
      api.get("/position-rates", {
        params: { active_only: true },
      }),
      api.get("/employees", {
        params: {
          per_page: 500,
          activity_status: "active,on_leave",
        },
      }),
    ]);

    const positions = Array.isArray(positionsResponse.data)
      ? positionsResponse.data
      : [];
    positionOptions.value = positions.map((position) => ({
      title: position.position_name,
      subtitle: `Daily Rate: ₱${Number(position.daily_rate || 0).toFixed(2)}`,
      value: Number(position.id),
    }));

    const employees = Array.isArray(employeesResponse.data?.data)
      ? employeesResponse.data.data
      : [];
    employeeOptions.value = employees.map((employee) => {
      const fullName =
        employee.full_name ||
        [employee.first_name, employee.last_name].filter(Boolean).join(" ");

      return {
        title: `${employee.employee_number || "EMP"} - ${fullName}`,
        subtitle: employee.position || "No position assigned",
        value: Number(employee.id),
      };
    });

    ensureSelectedTargetsVisible();
  } catch (error) {
    toast.error("Failed to load position and employee options");
  } finally {
    targetOptionsLoading.value = false;
  }
};

const saveConfiguration = async () => {
  saving.value = true;

  try {
    const payload = normalizeConfig(config.value);
    await api.put("/payroll-config", payload);
    toast.success("Payroll configuration saved successfully");
    closeDialog();
  } catch (error) {
    const message =
      error.response?.data?.message || error.message || "Failed to save payroll configuration";
    toast.error(message);
  } finally {
    saving.value = false;
  }
};

const loadConfiguration = async () => {
  try {
    const response = await api.get("/payroll-config");
    config.value = normalizeConfig(response.data || {});
    ensureSelectedTargetsVisible();
  } catch (error) {
    toast.error("Failed to load payroll configuration");
  }
};
</script>

<style scoped lang="scss">
.payroll-config-dialog {
  border-radius: 16px;
}

.dialog-header {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: white;
  padding: 24px 28px;
  position: relative;
}

.header-content {
  display: flex;
  align-items: center;
  justify-content: flex-start;
  gap: 16px;
  width: 100%;
}

.icon-wrapper {
  width: 56px;
  height: 56px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.dialog-title {
  font-size: 24px;
  font-weight: 700;
  margin: 0;
  color: white;
}

.dialog-subtitle {
  font-size: 14px;
  margin: 4px 0 0 0;
  opacity: 0.9;
}

.close-btn {
  position: absolute;
  top: 16px;
  right: 16px;

  :deep(.v-icon) {
    color: white !important;
  }
}

.dialog-content {
  padding: 0;
  max-height: 600px;
}

.config-tabs {
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
  padding: 0 24px;

  :deep(.v-tab) {
    text-transform: none;
    font-weight: 600;
    letter-spacing: 0;
  }
}

.config-window {
  padding: 24px;
}

.config-section {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.section-header {
  margin-bottom: 8px;
}

.section-title {
  font-size: 20px;
  font-weight: 700;
  color: #001f3d;
  margin: 0 0 8px 0;
}

.section-description {
  font-size: 14px;
  color: rgba(0, 31, 61, 0.6);
  margin: 0;
}

.cap-summary {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.cap-pill {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  border-radius: 999px;
  padding: 8px 12px;
  background: rgba(237, 152, 95, 0.15);
  color: #7b3b12;
  font-size: 12px;
  font-weight: 600;
}

.cap-pill.secondary {
  background: rgba(0, 31, 61, 0.08);
  color: #001f3d;
}

// Rate Cards
.rate-cards {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.rate-card {
  display: flex;
  gap: 20px;
  padding: 20px;
  background: rgba(237, 152, 95, 0.04);
  border: 1px solid rgba(237, 152, 95, 0.15);
  border-radius: 12px;
  transition: all 0.2s ease;

  &:hover {
    background: rgba(237, 152, 95, 0.08);
    border-color: rgba(237, 152, 95, 0.3);
  }
}

.cap-target-card {
  align-items: flex-start;
}

.rate-icon {
  width: 50px;
  height: 50px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;

  &.regular {
    background: linear-gradient(
      135deg,
      rgba(66, 133, 244, 0.15),
      rgba(66, 133, 244, 0.05)
    );

    :deep(.v-icon) {
      color: #4285f4 !important;
    }
  }

  &.sunday {
    background: linear-gradient(
      135deg,
      rgba(251, 188, 4, 0.15),
      rgba(251, 188, 4, 0.05)
    );

    :deep(.v-icon) {
      color: #fbbc04 !important;
    }
  }

  &.holiday-regular {
    background: linear-gradient(
      135deg,
      rgba(219, 68, 55, 0.15),
      rgba(219, 68, 55, 0.05)
    );

    :deep(.v-icon) {
      color: #db4437 !important;
    }
  }

  &.holiday-sunday {
    background: linear-gradient(
      135deg,
      rgba(244, 160, 0, 0.15),
      rgba(244, 160, 0, 0.05)
    );

    :deep(.v-icon) {
      color: #f4a000 !important;
    }
  }

  &.holiday-special {
    background: linear-gradient(
      135deg,
      rgba(15, 157, 88, 0.15),
      rgba(15, 157, 88, 0.05)
    );

    :deep(.v-icon) {
      color: #0f9d58 !important;
    }
  }
}

.rate-details {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.rate-label {
  font-size: 16px;
  font-weight: 600;
  color: #001f3d;
  margin: 0;
}

.rate-formula {
  font-size: 13px;
  color: rgba(0, 31, 61, 0.5);
  margin: 0;
  font-family: "Courier New", monospace;
}

.rate-input-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 4px;
}

.rate-input {
  max-width: 150px;

  :deep(.v-field) {
    background: white;
  }
}

.rate-example {
  font-size: 12px;
  color: rgba(0, 31, 61, 0.6);
  padding: 6px 12px;
  background: white;
  border-radius: 6px;
  border: 1px solid rgba(0, 0, 0, 0.08);
}

// Actions
.dialog-actions {
  padding: 20px 28px;
  background: rgba(0, 0, 0, 0.02);
}

.cancel-btn {
  text-transform: none;
  font-weight: 600;
}

.save-btn {
  text-transform: none;
  font-weight: 600;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: white;
  padding: 0 24px;

  :deep(.v-icon) {
    color: white !important;
  }
}
</style>
