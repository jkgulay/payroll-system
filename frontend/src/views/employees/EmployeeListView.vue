<template>
  <div>
    <v-row class="mb-4">
      <v-col cols="12" md="6">
        <h1 class="text-h4 font-weight-bold">Employees</h1>
      </v-col>
      <v-col cols="12" md="6" class="text-right">
        <v-btn
          color="primary"
          prepend-icon="mdi-account-plus"
          to="/employees/create"
        >
          Add Employee
        </v-btn>
      </v-col>
    </v-row>

    <v-card>
      <v-card-text>
        <v-row class="mb-4">
          <v-col cols="12" md="4">
            <v-text-field
              v-model="search"
              prepend-inner-icon="mdi-magnify"
              label="Search employees..."
              clearable
              @input="fetchEmployees"
            ></v-text-field>
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.department"
              :items="departments"
              item-title="name"
              item-value="id"
              label="Department"
              clearable
              @update:model-value="fetchEmployees"
            ></v-select>
          </v-col>
          <v-col cols="12" md="3">
            <v-select
              v-model="filters.status"
              :items="statusOptions"
              label="Status"
              clearable
              @update:model-value="fetchEmployees"
            ></v-select>
          </v-col>
          <v-col cols="12" md="2">
            <v-select
              v-model="filters.employment_type"
              :items="employmentTypes"
              label="Employment Type"
              clearable
              @update:model-value="fetchEmployees"
            ></v-select>
          </v-col>
        </v-row>

        <v-data-table
          :headers="headers"
          :items="employeeStore.employees"
          :loading="employeeStore.loading"
          :items-per-page="20"
          @click:row="viewEmployee"
          hover
        >
          <template v-slot:item.employee_number="{ item }">
            <strong>{{ item.employee_number }}</strong>
          </template>

          <template v-slot:item.full_name="{ item }">
            {{ item.first_name }} {{ item.middle_name }} {{ item.last_name }}
          </template>

          <template v-slot:item.department="{ item }">
            {{ item.department?.name || "N/A" }}
          </template>

          <template v-slot:item.position="{ item }">
            {{ item.position || "N/A" }}
          </template>

          <template v-slot:item.employment_type="{ item }">
            <v-chip
              size="small"
              :color="getEmploymentTypeColor(item.employment_type)"
            >
              {{ item.employment_type }}
            </v-chip>
          </template>

          <template v-slot:item.is_active="{ item }">
            <v-chip size="small" :color="item.is_active ? 'success' : 'error'">
              {{ item.is_active ? "Active" : "Inactive" }}
            </v-chip>
          </template>

          <template v-slot:item.actions="{ item }">
            <v-btn
              icon="mdi-eye"
              size="small"
              variant="text"
              :to="`/employees/${item.id}`"
            ></v-btn>
            <v-btn
              icon="mdi-pencil"
              size="small"
              variant="text"
              :to="`/employees/${item.id}/edit`"
            ></v-btn>
          </template>
        </v-data-table>
      </v-card-text>
    </v-card>
  </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useEmployeeStore } from "@/stores/employee";

const router = useRouter();
const employeeStore = useEmployeeStore();

const search = ref("");
const filters = ref({
  department: null,
  status: null,
  employment_type: null,
});

const departments = ref([]);

const statusOptions = [
  { title: "Active", value: "active" },
  { title: "Inactive", value: "inactive" },
];

const employmentTypes = [
  { title: "Daily", value: "daily" },
  { title: "Monthly", value: "monthly" },
];

const headers = [
  { title: "Employee #", key: "employee_number", sortable: true },
  { title: "Name", key: "full_name", sortable: true },
  { title: "Department", key: "department", sortable: false },
  { title: "Position", key: "position", sortable: true },
  { title: "Type", key: "employment_type", sortable: true },
  { title: "Status", key: "is_active", sortable: true },
  { title: "Actions", key: "actions", sortable: false, align: "center" },
];

onMounted(async () => {
  await fetchEmployees();
  await fetchDepartments();
});

async function fetchEmployees() {
  await employeeStore.fetchEmployees({
    search: search.value,
    ...filters.value,
  });
}

async function fetchDepartments() {
  departments.value = await employeeStore.fetchDepartments();
}

function viewEmployee(event, { item }) {
  router.push(`/employees/${item.id}`);
}

function getEmploymentTypeColor(type) {
  return type === "monthly" ? "primary" : "info";
}
</script>
