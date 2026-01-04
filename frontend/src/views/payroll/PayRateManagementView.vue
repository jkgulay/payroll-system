<template>
  <div>
    <!-- Page Header -->
    <v-row class="mb-4">
      <v-col cols="12" md="8">
        <h1 class="text-h4 font-weight-bold">
          <v-icon size="large" class="mr-2">mdi-cash-multiple</v-icon>
          Compensation & Pay Rates
        </h1>
        <p class="text-subtitle-1 text-medium-emphasis mt-2">
          View and manage employee daily rates and compensation history
        </p>
      </v-col>
      <v-col cols="12" md="4" class="text-right">
        <v-btn
          color="primary"
          prepend-icon="mdi-briefcase-edit"
          variant="elevated"
          size="large"
          @click="showPositionRatesDialog = true"
        >
          Manage Position Rates
        </v-btn>
      </v-col>
    </v-row>

    <!-- Filters -->
    <v-card class="mb-4">
      <v-card-text>
        <v-row>
          <v-col cols="12" md="3">
            <v-text-field
              v-model="search"
              label="Search Employee"
              prepend-inner-icon="mdi-magnify"
              variant="outlined"
              density="comfortable"
              clearable
              @input="fetchEmployees"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.project_id"
              :items="projects"
              item-title="name"
              item-value="id"
              label="Filter by Project"
              variant="outlined"
              density="comfortable"
              clearable
              @update:model-value="fetchEmployees"
            ></v-select>
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.position"
              :items="positionOptions"
              label="Filter by Position"
              variant="outlined"
              density="comfortable"
              clearable
              @update:model-value="fetchEmployees"
            ></v-select>
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.salary_type"
              :items="[
                { title: 'Daily', value: 'daily' },
                { title: 'Monthly', value: 'monthly' },
              ]"
              label="Filter by Salary Type"
              variant="outlined"
              density="comfortable"
              clearable
              @update:model-value="fetchEmployees"
            ></v-select>
          </v-col>
        </v-row>
      </v-card-text>
    </v-card>

    <!-- Employees Table -->
    <v-card>
      <v-data-table
        :headers="headers"
        :items="employees"
        :loading="loading"
        :search="search"
        density="comfortable"
        class="elevation-1"
      >
        <template v-slot:item.full_name="{ item }">
          <div class="d-flex align-center">
            <v-avatar color="primary" size="32" class="mr-3">
              <span class="text-caption font-weight-bold">
                {{ item.first_name.charAt(0) + item.last_name.charAt(0) }}
              </span>
            </v-avatar>
            <div>
              <div class="font-weight-medium">
                {{ item.first_name }} {{ item.last_name }}
              </div>
              <div class="text-caption text-medium-emphasis">
                {{ item.employee_number }}
              </div>
            </div>
          </div>
        </template>

        <template v-slot:item.project="{ item }">
          {{ item.project?.name || "N/A" }}
        </template>

        <template v-slot:item.position="{ item }">
          <v-chip size="small" color="info" variant="tonal">
            {{ item.position }}
          </v-chip>
        </template>

        <template v-slot:item.salary_type="{ item }">
          <v-chip
            size="small"
            :color="item.salary_type === 'daily' ? 'warning' : 'success'"
            variant="tonal"
          >
            {{ item.salary_type }}
          </v-chip>
        </template>

        <template v-slot:item.basic_salary="{ item }">
          <div class="text-right font-weight-bold">
            ₱{{ formatNumber(item.basic_salary) }}
            <span class="text-caption text-medium-emphasis">
              /{{ item.salary_type === "daily" ? "day" : "month" }}
            </span>
          </div>
        </template>

        <template v-slot:item.semi_monthly_equivalent="{ item }">
          <div class="text-right">
            ₱{{ formatNumber(getSemiMonthlyEquivalent(item)) }}
          </div>
        </template>

        <template v-slot:item.last_updated="{ item }">
          <div class="text-caption">
            {{ item.updated_at ? formatDate(item.updated_at) : "N/A" }}
          </div>
        </template>

        <template v-slot:item.actions="{ item }">
          <v-btn
            icon="mdi-pencil"
            size="small"
            variant="text"
            color="primary"
            @click="openEditDialog(item)"
            title="Edit Pay Rate"
          ></v-btn>
          <v-btn
            icon="mdi-history"
            size="small"
            variant="text"
            color="info"
            @click="viewHistory(item)"
            title="View History"
          ></v-btn>
        </template>
      </v-data-table>
    </v-card>

    <!-- Edit Pay Rate Dialog -->
    <v-dialog v-model="showEditDialog" max-width="600px" persistent>
      <v-card>
        <v-card-title class="text-h5 py-4 bg-primary">
          <v-icon start>mdi-cash-edit</v-icon>
          Edit Pay Rate
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-6" v-if="selectedEmployee">
          <v-form ref="editForm">
            <v-row>
              <!-- Employee Info -->
              <v-col cols="12">
                <v-alert type="info" variant="tonal" density="compact">
                  <strong>Employee:</strong>
                  {{ selectedEmployee.first_name }}
                  {{ selectedEmployee.last_name }} ({{
                    selectedEmployee.employee_number
                  }})
                  <br />
                  <strong>Position:</strong> {{ selectedEmployee.position }}
                </v-alert>
              </v-col>

              <!-- Current Rate -->
              <v-col cols="12">
                <v-card variant="outlined" color="grey-lighten-3">
                  <v-card-text>
                    <div class="text-caption text-medium-emphasis">
                      Current Rate
                    </div>
                    <div class="text-h5 font-weight-bold">
                      ₱{{ formatNumber(selectedEmployee.basic_salary) }}
                      <span class="text-caption">
                        /{{
                          selectedEmployee.salary_type === "daily"
                            ? "day"
                            : "month"
                        }}
                      </span>
                    </div>
                  </v-card-text>
                </v-card>
              </v-col>

              <!-- New Rate -->
              <v-col cols="12">
                <v-text-field
                  v-model.number="editData.new_rate"
                  label="New Pay Rate"
                  type="number"
                  prepend-inner-icon="mdi-cash"
                  :rules="[rules.required, rules.positive]"
                  variant="outlined"
                  density="comfortable"
                  :suffix="
                    '₱/' +
                    (selectedEmployee.salary_type === 'daily' ? 'day' : 'month')
                  "
                  hint="Enter the new pay rate for this employee"
                  persistent-hint
                ></v-text-field>
              </v-col>

              <!-- Effective Date -->
              <v-col cols="12">
                <v-text-field
                  v-model="editData.effective_date"
                  label="Effective Date"
                  type="date"
                  prepend-inner-icon="mdi-calendar"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                  hint="Date when this new rate takes effect"
                  persistent-hint
                ></v-text-field>
              </v-col>

              <!-- Reason -->
              <v-col cols="12">
                <v-textarea
                  v-model="editData.reason"
                  label="Reason for Change"
                  :rules="[rules.required]"
                  variant="outlined"
                  density="comfortable"
                  rows="3"
                  hint="Provide a reason for this pay rate change (e.g., Annual increase, Promotion, Performance bonus)"
                  persistent-hint
                ></v-textarea>
              </v-col>

              <!-- Preview -->
              <v-col cols="12" v-if="editData.new_rate">
                <v-card variant="outlined" color="success">
                  <v-card-text>
                    <div class="text-caption text-medium-emphasis">
                      Rate Change Preview
                    </div>
                    <div class="d-flex justify-space-between align-center mt-2">
                      <div>
                        <div class="text-subtitle-2">Old Rate:</div>
                        <div class="font-weight-bold">
                          ₱{{ formatNumber(selectedEmployee.basic_salary) }}
                        </div>
                      </div>
                      <v-icon size="large">mdi-arrow-right</v-icon>
                      <div>
                        <div class="text-subtitle-2">New Rate:</div>
                        <div class="font-weight-bold text-success">
                          ₱{{ formatNumber(editData.new_rate) }}
                        </div>
                      </div>
                      <div>
                        <div class="text-subtitle-2">Difference:</div>
                        <div
                          class="font-weight-bold"
                          :class="
                            editData.new_rate - selectedEmployee.basic_salary >
                            0
                              ? 'text-success'
                              : 'text-error'
                          "
                        >
                          {{
                            editData.new_rate - selectedEmployee.basic_salary >
                            0
                              ? "+"
                              : ""
                          }}₱{{
                            formatNumber(
                              editData.new_rate - selectedEmployee.basic_salary
                            )
                          }}
                          ({{
                            formatPercentage(
                              ((editData.new_rate -
                                selectedEmployee.basic_salary) /
                                selectedEmployee.basic_salary) *
                                100
                            )
                          }}%)
                        </div>
                      </div>
                    </div>
                  </v-card-text>
                </v-card>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn variant="text" @click="closeEditDialog" :disabled="saving">
            Cancel
          </v-btn>
          <v-btn
            color="primary"
            variant="elevated"
            @click="savePayRate"
            :loading="saving"
          >
            <v-icon start>mdi-content-save</v-icon>
            Save Changes
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- History Dialog -->
    <v-dialog v-model="showHistoryDialog" max-width="800px">
      <v-card>
        <v-card-title class="text-h5 py-4 bg-info">
          <v-icon start>mdi-history</v-icon>
          Pay Rate History
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-4" v-if="selectedEmployee">
          <v-alert type="info" variant="tonal" density="compact" class="mb-4">
            <strong>Employee:</strong>
            {{ selectedEmployee.first_name }}
            {{ selectedEmployee.last_name }} ({{
              selectedEmployee.employee_number
            }})
          </v-alert>

          <v-timeline side="end" density="compact">
            <v-timeline-item
              v-for="(record, index) in historyRecords"
              :key="index"
              :dot-color="index === 0 ? 'success' : 'grey'"
              size="small"
            >
              <template v-slot:opposite>
                <div class="text-caption">
                  {{ formatDate(record.effective_date) }}
                </div>
              </template>

              <v-card variant="outlined">
                <v-card-text>
                  <div class="d-flex justify-space-between align-center">
                    <div>
                      <div class="font-weight-bold text-h6">
                        ₱{{ formatNumber(record.rate) }}
                        <v-chip
                          v-if="index === 0"
                          size="x-small"
                          color="success"
                          class="ml-2"
                        >
                          Current
                        </v-chip>
                      </div>
                      <div class="text-caption text-medium-emphasis mt-1">
                        {{ record.reason }}
                      </div>
                    </div>
                    <div
                      class="text-right"
                      v-if="index < historyRecords.length - 1"
                    >
                      <div
                        class="text-caption"
                        :class="
                          record.rate - historyRecords[index + 1].rate > 0
                            ? 'text-success'
                            : 'text-error'
                        "
                      >
                        {{
                          record.rate - historyRecords[index + 1].rate > 0
                            ? "+"
                            : ""
                        }}₱{{
                          formatNumber(
                            record.rate - historyRecords[index + 1].rate
                          )
                        }}
                      </div>
                    </div>
                  </div>
                  <v-divider class="my-2"></v-divider>
                  <div class="text-caption text-medium-emphasis">
                    Updated by:
                    {{ record.updated_by || "System" }} •
                    {{ formatDate(record.created_at) }}
                  </div>
                </v-card-text>
              </v-card>
            </v-timeline-item>
          </v-timeline>

          <v-alert
            v-if="historyRecords.length === 0"
            type="info"
            variant="tonal"
          >
            No pay rate history available for this employee.
          </v-alert>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn color="primary" @click="showHistoryDialog = false">
            Close
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Manage Position Rates Dialog -->
    <v-dialog v-model="showPositionRatesDialog" max-width="900px" persistent>
      <v-card>
        <v-card-title class="text-h5 py-4 bg-primary">
          <v-icon start>mdi-briefcase-edit</v-icon>
          Manage Position Rates
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-4">
          <v-row>
            <v-col cols="12">
              <v-alert
                type="info"
                variant="tonal"
                density="compact"
                class="mb-4"
              >
          
                Set default daily rates for job positions. These rates will be
                automatically assigned when creating new employees.
              </v-alert>
            </v-col>

            <!-- Search and Add New Position -->
            <v-col cols="12" md="8">
              <v-text-field
                v-model="positionSearch"
                label="Search Positions"
                prepend-inner-icon="mdi-magnify"
                variant="outlined"
                density="comfortable"
                clearable
              ></v-text-field>
            </v-col>
            <v-col cols="12" md="4">
              <v-btn
                color="success"
                prepend-icon="mdi-plus"
                block
                size="large"
                @click="showAddPositionDialog = true"
              >
                Add Position
              </v-btn>
            </v-col>

            <!-- Position Rates Table -->
            <v-col cols="12">
              <v-data-table
                :headers="positionRateHeaders"
                :items="filteredPositionRates"
                :items-per-page="10"
                density="comfortable"
                class="elevation-1"
              >
                <template v-slot:item.position="{ item }">
                  <div class="font-weight-medium">{{ item.position }}</div>
                </template>

                <template v-slot:item.rate="{ item }">
                  <div class="font-weight-bold">
                    ₱{{ formatNumber(item.rate) }}/day
                  </div>
                </template>

                <template v-slot:item.semi_monthly="{ item }">
                  <div class="text-medium-emphasis">
                    ₱{{ formatNumber(item.rate * 15) }}
                  </div>
                </template>

                <template v-slot:item.employee_count="{ item }">
                  <v-chip size="small" color="info" variant="tonal">
                    {{ getEmployeeCountForPosition(item.position) }} employees
                  </v-chip>
                </template>

                <template v-slot:item.actions="{ item }">
                  <v-btn
                    icon="mdi-pencil"
                    size="small"
                    variant="text"
                    color="primary"
                    @click="editPositionRate(item)"
                    title="Edit Rate"
                  ></v-btn>
                  <v-btn
                    icon="mdi-account-multiple-check"
                    size="small"
                    variant="text"
                    color="success"
                    @click="bulkUpdatePosition(item)"
                    title="Update All Employees"
                  ></v-btn>
                  <v-btn
                    icon="mdi-delete"
                    size="small"
                    variant="text"
                    color="error"
                    @click="deletePosition(item)"
                    title="Delete Position"
                    :disabled="getEmployeeCountForPosition(item.position) > 0"
                  ></v-btn>
                </template>
              </v-data-table>
            </v-col>
          </v-row>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn color="primary" @click="showPositionRatesDialog = false">
            Close
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Add New Position Dialog -->
    <v-dialog v-model="showAddPositionDialog" max-width="500px" persistent>
      <v-card>
        <v-card-title class="text-h5 py-4 bg-success">
          <v-icon start>mdi-plus-circle</v-icon>
          Add New Position
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-6">
          <v-form ref="addPositionForm">
            <v-text-field
              v-model="newPosition.name"
              label="Position Name"
              prepend-inner-icon="mdi-briefcase"
              :rules="[rules.required]"
              variant="outlined"
              density="comfortable"
              hint="e.g., Crane Operator, Tile Setter"
              persistent-hint
            ></v-text-field>

            <v-text-field
              v-model.number="newPosition.rate"
              label="Default Daily Rate"
              type="number"
              prepend-inner-icon="mdi-cash"
              :rules="[rules.required, rules.positive]"
              variant="outlined"
              density="comfortable"
              class="mt-4"
              suffix="₱/day"
              hint="Standard daily rate for this position"
              persistent-hint
            ></v-text-field>

            <v-alert type="info" variant="tonal" density="compact" class="mt-4">
              This rate will be automatically assigned when creating new
              employees with this position.
            </v-alert>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn
            variant="text"
            @click="closeAddPositionDialog"
            :disabled="savingPosition"
          >
            Cancel
          </v-btn>
          <v-btn
            color="success"
            variant="elevated"
            @click="addPosition"
            :loading="savingPosition"
          >
            <v-icon start>mdi-plus</v-icon>
            Add Position
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Edit Position Rate Dialog -->
    <v-dialog v-model="showEditPositionDialog" max-width="500px" persistent>
      <v-card>
        <v-card-title class="text-h5 py-4 bg-primary">
          <v-icon start>mdi-pencil</v-icon>
          Edit Position Rate
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-6" v-if="editingPosition">
          <v-form ref="editPositionForm">
            <v-text-field
              v-model="editingPosition.position"
              label="Position Name"
              prepend-inner-icon="mdi-briefcase"
              readonly
              variant="outlined"
              density="comfortable"
            ></v-text-field>

            <v-card variant="outlined" color="grey-lighten-3" class="mt-4">
              <v-card-text>
                <div class="text-caption text-medium-emphasis">
                  Current Rate
                </div>
                <div class="text-h5 font-weight-bold">
                  ₱{{ formatNumber(editingPosition.originalRate) }}/day
                </div>
              </v-card-text>
            </v-card>

            <v-text-field
              v-model.number="editingPosition.rate"
              label="New Default Daily Rate"
              type="number"
              prepend-inner-icon="mdi-cash"
              :rules="[rules.required, rules.positive]"
              variant="outlined"
              density="comfortable"
              class="mt-4"
              suffix="₱/day"
            ></v-text-field>

            <v-alert
              type="warning"
              variant="tonal"
              density="compact"
              class="mt-4"
            >
              This will only update the default rate for NEW employees. To
              update existing employees, use the "Update All Employees" button.
            </v-alert>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn
            variant="text"
            @click="closeEditPositionDialog"
            :disabled="savingPosition"
          >
            Cancel
          </v-btn>
          <v-btn
            color="primary"
            variant="elevated"
            @click="updatePositionRate"
            :loading="savingPosition"
          >
            <v-icon start>mdi-content-save</v-icon>
            Save Changes
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Bulk Update Confirmation Dialog -->
    <v-dialog v-model="showBulkUpdateDialog" max-width="600px" persistent>
      <v-card>
        <v-card-title class="text-h5 py-4 bg-warning">
          <v-icon start>mdi-account-multiple-check</v-icon>
          Bulk Update Employees
        </v-card-title>
        <v-divider></v-divider>

        <v-card-text class="pt-6" v-if="bulkUpdateTarget">
          <v-alert type="warning" variant="tonal" class="mb-4">
            <v-icon start>mdi-alert</v-icon>
            <strong>Warning:</strong> This will update the pay rate for ALL
            employees with this position.
          </v-alert>

          <v-card variant="outlined" class="mb-4">
            <v-card-text>
              <div class="text-subtitle-2 text-medium-emphasis mb-2">
                Position
              </div>
              <div class="text-h6 font-weight-bold mb-3">
                {{ bulkUpdateTarget.position }}
              </div>
              <v-divider class="my-3"></v-divider>
              <div class="text-subtitle-2 text-medium-emphasis mb-2">
                Affected Employees
              </div>
              <div class="text-h5 font-weight-bold text-warning">
                {{ getEmployeeCountForPosition(bulkUpdateTarget.position) }}
                employees
              </div>
            </v-card-text>
          </v-card>

          <v-text-field
            v-model.number="bulkUpdateRate"
            label="New Daily Rate for All"
            type="number"
            prepend-inner-icon="mdi-cash"
            :rules="[rules.required, rules.positive]"
            variant="outlined"
            density="comfortable"
            suffix="₱/day"
            hint="This rate will be applied to all employees with this position"
            persistent-hint
          ></v-text-field>

          <v-textarea
            v-model="bulkUpdateReason"
            label="Reason for Bulk Update"
            :rules="[rules.required]"
            variant="outlined"
            density="comfortable"
            rows="3"
            class="mt-4"
            hint="e.g., Annual rate adjustment, Industry standard increase"
            persistent-hint
          ></v-textarea>

          <v-text-field
            v-model="bulkUpdateEffectiveDate"
            label="Effective Date"
            type="date"
            prepend-inner-icon="mdi-calendar"
            :rules="[rules.required]"
            variant="outlined"
            density="comfortable"
            class="mt-4"
          ></v-text-field>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn
            variant="text"
            @click="closeBulkUpdateDialog"
            :disabled="bulkUpdating"
          >
            Cancel
          </v-btn>
          <v-btn
            color="warning"
            variant="elevated"
            @click="confirmBulkUpdate"
            :loading="bulkUpdating"
          >
            <v-icon start>mdi-update</v-icon>
            Update All Employees
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useToast } from "vue-toastification";
import api from "@/services/api";
import { usePositionRates } from "@/composables/usePositionRates";

const toast = useToast();
const {
  positionRates,
  positionOptions,
  getRate,
  updateRate,
  addPosition: addPositionToRates,
  deletePosition: deletePositionFromRates,
  loadPositionRates,
  bulkUpdateEmployees,
  refreshRates,
} = usePositionRates();

const search = ref("");
const filters = ref({
  project_id: null,
  position: null,
  salary_type: null,
});

const employees = ref([]);
const projects = ref([]);
const loading = ref(false);

const showEditDialog = ref(false);
const showHistoryDialog = ref(false);
const selectedEmployee = ref(null);
const saving = ref(false);

// Position Rates Management
const showPositionRatesDialog = ref(false);
const showAddPositionDialog = ref(false);
const showEditPositionDialog = ref(false);
const showBulkUpdateDialog = ref(false);
const positionSearch = ref("");
const savingPosition = ref(false);
const bulkUpdating = ref(false);
const editingPosition = ref(null);
const bulkUpdateTarget = ref(null);
const bulkUpdateRate = ref(null);
const bulkUpdateReason = ref("");
const bulkUpdateEffectiveDate = ref(new Date().toISOString().split("T")[0]);

const newPosition = ref({
  name: "",
  rate: null,
});

const positionRateHeaders = [
  { title: "Position", key: "position", sortable: true },
  { title: "Daily Rate", key: "rate", sortable: true },
  { title: "Semi-Monthly (15 days)", key: "semi_monthly", sortable: false },
  { title: "Employees", key: "employee_count", sortable: false },
  { title: "Actions", key: "actions", sortable: false, align: "center" },
];

const addPositionForm = ref(null);
const editPositionForm = ref(null);

const editData = ref({
  new_rate: null,
  effective_date: new Date().toISOString().split("T")[0],
  reason: "",
});

const historyRecords = ref([]);

const editForm = ref(null);

const headers = [
  { title: "Employee", key: "full_name", sortable: true },
  { title: "Project", key: "project", sortable: false },
  { title: "Position", key: "position", sortable: true },
  { title: "Salary Type", key: "salary_type", sortable: true },
  {
    title: "Current Rate",
    key: "basic_salary",
    sortable: true,
    align: "end",
  },
  {
    title: "Semi-Monthly Equiv.",
    key: "semi_monthly_equivalent",
    sortable: true,
    align: "end",
  },
  { title: "Last Updated", key: "last_updated", sortable: true },
  { title: "Actions", key: "actions", sortable: false, align: "center" },
];

const rules = {
  required: (v) => !!v || "This field is required",
  positive: (v) => v > 0 || "Rate must be greater than 0",
};

onMounted(async () => {
  await loadPositionRates(); // Load position rates from database
  await fetchEmployees();
  await fetchProjects();
});

async function fetchEmployees() {
  loading.value = true;
  try {
    const response = await api.get("/employees", {
      params: {
        search: search.value,
        ...filters.value,
        per_page: 1000,
      },
    });
    employees.value = response.data.data || response.data;
  } catch (error) {
    console.error("Error fetching employees:", error);
    toast.error("Failed to load employees");
  } finally {
    loading.value = false;
  }
}

async function fetchProjects() {
  try {
    const response = await api.get("/projects");
    projects.value = response.data.data || response.data;
  } catch (error) {
    console.error("Error fetching projects:", error);
  }
}

function openEditDialog(employee) {
  selectedEmployee.value = { ...employee };
  editData.value = {
    new_rate: employee.basic_salary,
    effective_date: new Date().toISOString().split("T")[0],
    reason: "",
  };
  showEditDialog.value = true;
}

function closeEditDialog() {
  showEditDialog.value = false;
  selectedEmployee.value = null;
  editData.value = {
    new_rate: null,
    effective_date: new Date().toISOString().split("T")[0],
    reason: "",
  };
}

async function savePayRate() {
  if (!editForm.value) return;

  const { valid } = await editForm.value.validate();
  if (!valid) return;

  saving.value = true;
  try {
    await api.put(`/employees/${selectedEmployee.value.id}`, {
      basic_salary: editData.value.new_rate,
      // Note: In future, send to a separate pay_rate_history endpoint
      // For now, just update the employee record
    });

    // TODO: Create audit log entry with reason and effective date
    // await api.post('/pay-rate-history', {
    //   employee_id: selectedEmployee.value.id,
    //   old_rate: selectedEmployee.value.basic_salary,
    //   new_rate: editData.value.new_rate,
    //   effective_date: editData.value.effective_date,
    //   reason: editData.value.reason,
    // });

    toast.success("Pay rate updated successfully!");
    await fetchEmployees();
    closeEditDialog();
  } catch (error) {
    console.error("Error updating pay rate:", error);
    toast.error(error.response?.data?.message || "Failed to update pay rate");
  } finally {
    saving.value = false;
  }
}

async function viewHistory(employee) {
  selectedEmployee.value = { ...employee };

  // TODO: Fetch actual history from pay_rate_history table
  // For now, show placeholder
  historyRecords.value = [
    {
      rate: employee.basic_salary,
      effective_date: new Date().toISOString(),
      reason: "Current rate",
      updated_by: "System",
      created_at: employee.updated_at || new Date().toISOString(),
    },
  ];

  showHistoryDialog.value = true;
}

function getSemiMonthlyEquivalent(employee) {
  if (employee.salary_type === "monthly") {
    return employee.basic_salary / 2; // Half of monthly for semi-monthly
  }
  // Daily rate × 15 days (semi-monthly period)
  return employee.basic_salary * 15;
}

function formatNumber(value) {
  return new Intl.NumberFormat("en-PH", {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(value || 0);
}

function formatPercentage(value) {
  return new Intl.NumberFormat("en-PH", {
    minimumFractionDigits: 1,
    maximumFractionDigits: 1,
  }).format(value || 0);
}

function formatDate(dateString) {
  if (!dateString) return "N/A";
  const date = new Date(dateString);
  return date.toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
  });
}

// Position Management Functions
const filteredPositionRates = computed(() => {
  // positionRates is now an array of objects: [{id, position_name, daily_rate, category, employee_count}, ...]
  const positions = positionRates.value.map((item) => ({
    id: item.id,
    position: item.position_name,
    rate: item.daily_rate,
    category: item.category,
    employee_count: item.employee_count || 0,
  }));

  if (!positionSearch.value) return positions;

  return positions.filter((p) =>
    p.position.toLowerCase().includes(positionSearch.value.toLowerCase())
  );
});

function getEmployeeCountForPosition(position) {
  return employees.value.filter((emp) => emp.position === position).length;
}

function editPositionRate(item) {
  editingPosition.value = {
    id: item.id, // Now includes ID for API calls
    position: item.position,
    rate: item.rate,
    originalRate: item.rate,
  };
  showEditPositionDialog.value = true;
}

async function updatePositionRate() {
  if (!editPositionForm.value) return;

  const { valid } = await editPositionForm.value.validate();
  if (!valid) return;

  savingPosition.value = true;
  try {
    // Update using API-based composable (saves to database)
    await updateRate(editingPosition.value.id, {
      position_name: editingPosition.value.position,
      daily_rate: editingPosition.value.rate,
    });

    await refreshRates(); // Refresh from server
    toast.success(
      `Default rate for ${editingPosition.value.position} updated to ₱${editingPosition.value.rate}/day`
    );
    closeEditPositionDialog();
  } catch (error) {
    console.error("Error updating position rate:", error);
    toast.error(
      error.response?.data?.message || "Failed to update position rate"
    );
  } finally {
    savingPosition.value = false;
  }
}

function closeEditPositionDialog() {
  showEditPositionDialog.value = false;
  editingPosition.value = null;
}

async function addPosition() {
  if (!addPositionForm.value) return;

  const { valid } = await addPositionForm.value.validate();
  if (!valid) return;

  savingPosition.value = true;
  try {
    // Add using API-based composable (saves to database)
    await addPositionToRates({
      position_name: newPosition.value.name,
      daily_rate: newPosition.value.rate,
      category: "other", // Default category
      is_active: true,
    });

    await refreshRates(); // Refresh from server
    toast.success(`Position "${newPosition.value.name}" added successfully!`);
    closeAddPositionDialog();
  } catch (error) {
    console.error("Error adding position:", error);
    const errorMessage =
      error.response?.data?.message ||
      error.message ||
      "Failed to add position";

    if (
      errorMessage.includes("already exists") ||
      errorMessage.includes("duplicate")
    ) {
      toast.warning("Position already exists!");
    } else {
      toast.error(errorMessage);
    }
  } finally {
    savingPosition.value = false;
  }
}

function closeAddPositionDialog() {
  showAddPositionDialog.value = false;
  newPosition.value = { name: "", rate: null };
}

async function deletePosition(item) {
  if (getEmployeeCountForPosition(item.position) > 0) {
    toast.warning("Cannot delete position with assigned employees");
    return;
  }

  if (confirm(`Delete position "${item.position}"?`)) {
    try {
      await deletePositionFromRates(item.id); // Now uses API with ID
      await refreshRates(); // Refresh from server
      toast.success(`Position "${item.position}" deleted`);
    } catch (error) {
      console.error("Error deleting position:", error);
      toast.error(error.response?.data?.message || "Failed to delete position");
    }
  }
}

function bulkUpdatePosition(item) {
  const employeeCount = getEmployeeCountForPosition(item.position);
  if (employeeCount === 0) {
    toast.warning("No employees with this position to update");
    return;
  }

  bulkUpdateTarget.value = item;
  bulkUpdateRate.value = item.rate;
  bulkUpdateReason.value = "";
  bulkUpdateEffectiveDate.value = new Date().toISOString().split("T")[0];
  showBulkUpdateDialog.value = true;
}

function closeBulkUpdateDialog() {
  showBulkUpdateDialog.value = false;
  bulkUpdateTarget.value = null;
  bulkUpdateRate.value = null;
  bulkUpdateReason.value = "";
}

async function confirmBulkUpdate() {
  if (!bulkUpdateRate.value || !bulkUpdateReason.value) {
    toast.warning("Please fill in all required fields");
    return;
  }

  bulkUpdating.value = true;
  try {
    const employeesToUpdate = employees.value.filter(
      (emp) => emp.position === bulkUpdateTarget.value.position
    );

    // Use the bulk update endpoint from composable
    // This updates BOTH the position_rate AND all employees in a single transaction
    await bulkUpdateEmployees(bulkUpdateTarget.value.id, bulkUpdateRate.value);

    await refreshRates(); // Refresh position rates from server
    await fetchEmployees(); // Refresh employee list

    toast.success(
      `Successfully updated ${employeesToUpdate.length} employees with position "${bulkUpdateTarget.value.position}" to ₱${bulkUpdateRate.value}/day`
    );
    closeBulkUpdateDialog();
  } catch (error) {
    console.error("Error bulk updating employees:", error);
    toast.error(error.response?.data?.message || "Failed to update employees");
  } finally {
    bulkUpdating.value = false;
  }
}
</script>

<style scoped>
:deep(.v-data-table) {
  border-radius: 8px;
}

:deep(.v-timeline-item__body) {
  padding-inline-start: 16px;
}
</style>
