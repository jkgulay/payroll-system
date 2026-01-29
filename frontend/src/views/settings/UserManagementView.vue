<template>
  <div class="user-management-page">
    <v-overlay :model-value="loading" class="align-center justify-center">
      <v-progress-circular
        indeterminate
        color="#ED985F"
        :size="70"
        :width="7"
      ></v-progress-circular>
    </v-overlay>

    <!-- Modern Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="back-button-wrapper">
          <button class="back-button" @click="$router.push('/settings')">
            <v-icon size="20">mdi-arrow-left</v-icon>
            <span>Back to Settings</span>
          </button>
        </div>

        <div class="header-main">
          <div class="page-title-section">
            <div class="page-icon-badge">
              <v-icon size="22">mdi-account-multiple</v-icon>
            </div>
            <div>
              <h1 class="page-title">User Management</h1>
              <p class="page-subtitle">
                Manage system users, roles, and access permissions
              </p>
            </div>
          </div>
          <div class="action-buttons">
            <button
              class="action-btn action-btn-primary"
              @click="openAddDialog"
            >
              <v-icon size="20">mdi-plus</v-icon>
              <span>Add User</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Stats Cards -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon total">
          <v-icon size="20">mdi-account-group</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Total Users</div>
          <div class="stat-value">{{ stats.total }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon active">
          <v-icon size="20">mdi-account-check</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Active Users</div>
          <div class="stat-value success">{{ stats.active }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon inactive">
          <v-icon size="20">mdi-account-off</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Inactive Users</div>
          <div class="stat-value">{{ stats.inactive }}</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon admin">
          <v-icon size="20">mdi-shield-account</v-icon>
        </div>
        <div class="stat-content">
          <div class="stat-label">Admin Users</div>
          <div class="stat-value">{{ stats.by_role.admin }}</div>
        </div>
      </div>
    </div>

    <!-- Filters and Table -->
    <div class="content-card">
      <div class="table-controls">
        <v-text-field
          v-model="search"
          prepend-inner-icon="mdi-magnify"
          label="Search users..."
          variant="outlined"
          density="comfortable"
          hide-details
          clearable
          class="search-field"
        ></v-text-field>

        <v-select
          v-model="roleFilter"
          :items="roleOptions"
          label="Role"
          variant="outlined"
          density="comfortable"
          hide-details
          clearable
          class="filter-select"
        ></v-select>

        <v-select
          v-model="statusFilter"
          :items="statusOptions"
          label="Status"
          variant="outlined"
          density="comfortable"
          hide-details
          clearable
          class="filter-select"
        ></v-select>
      </div>

      <v-data-table
        :headers="headers"
        :items="filteredUsers"
        :search="search"
        class="modern-table"
        :items-per-page="15"
        :loading="loading"
      >
        <template v-slot:item.user="{ item }">
          <div class="user-cell">
            <div class="user-avatar">
              <v-icon v-if="!item.avatar" size="24">mdi-account</v-icon>
              <img v-else :src="item.avatar" alt="Avatar" />
            </div>
            <div class="user-info">
              <div class="user-name">{{ item.name }}</div>
              <div class="user-username">@{{ item.username }}</div>
            </div>
          </div>
        </template>

        <template v-slot:item.email="{ item }">
          <div class="email-cell">{{ item.email }}</div>
        </template>

        <template v-slot:item.role="{ item }">
          <v-chip
            :color="getRoleColor(item.role)"
            size="small"
            class="role-chip"
          >
            <v-icon size="14" class="mr-1">{{ getRoleIcon(item.role) }}</v-icon>
            {{ getRoleLabel(item.role) }}
          </v-chip>
        </template>

        <template v-slot:item.employee="{ item }">
          <div v-if="item.employee" class="employee-cell">
            <div class="employee-number">{{ item.employee.employee_number }}</div>
            <div class="employee-name">{{ getEmployeeName(item.employee) }}</div>
          </div>
          <span v-else class="text-grey">-</span>
        </template>

        <template v-slot:item.status="{ item }">
          <v-chip
            :color="item.is_active ? 'success' : 'error'"
            size="small"
            class="status-chip"
          >
            <v-icon size="14" class="mr-1">
              {{ item.is_active ? "mdi-check-circle" : "mdi-close-circle" }}
            </v-icon>
            {{ item.is_active ? "Active" : "Inactive" }}
          </v-chip>
        </template>

        <template v-slot:item.last_login="{ item }">
          <span v-if="item.last_login_at" class="last-login">
            {{ formatDate(item.last_login_at) }}
          </span>
          <span v-else class="text-grey">Never</span>
        </template>

        <template v-slot:item.actions="{ item }">
          <div class="action-buttons-cell">
            <v-tooltip text="View Details" location="top">
              <template v-slot:activator="{ props }">
                <v-btn
                  v-bind="props"
                  icon
                  size="small"
                  variant="text"
                  color="primary"
                  @click="viewUser(item)"
                >
                  <v-icon size="18">mdi-eye</v-icon>
                </v-btn>
              </template>
            </v-tooltip>

            <v-tooltip text="Edit User" location="top">
              <template v-slot:activator="{ props }">
                <v-btn
                  v-bind="props"
                  icon
                  size="small"
                  variant="text"
                  color="warning"
                  @click="editUser(item)"
                >
                  <v-icon size="18">mdi-pencil</v-icon>
                </v-btn>
              </template>
            </v-tooltip>

            <v-tooltip
              :text="item.is_active ? 'Deactivate' : 'Activate'"
              location="top"
            >
              <template v-slot:activator="{ props }">
                <v-btn
                  v-bind="props"
                  icon
                  size="small"
                  variant="text"
                  :color="item.is_active ? 'warning' : 'success'"
                  @click="toggleUserStatus(item)"
                >
                  <v-icon size="18">
                    {{ item.is_active ? "mdi-account-off" : "mdi-account-check" }}
                  </v-icon>
                </v-btn>
              </template>
            </v-tooltip>

            <v-tooltip text="Delete User" location="top">
              <template v-slot:activator="{ props }">
                <v-btn
                  v-bind="props"
                  icon
                  size="small"
                  variant="text"
                  color="error"
                  @click="confirmDelete(item)"
                >
                  <v-icon size="18">mdi-delete</v-icon>
                </v-btn>
              </template>
            </v-tooltip>
          </div>
        </template>
      </v-data-table>
    </div>

    <!-- Add/Edit User Dialog -->
    <v-dialog v-model="showUserDialog" max-width="700px" persistent>
      <v-card class="modern-dialog">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper primary">
            <v-icon size="24">
              {{ isEditMode ? "mdi-account-edit" : "mdi-account-plus" }}
            </v-icon>
          </div>
          <div>
            <div class="dialog-title">
              {{ isEditMode ? "Edit User" : "Add New User" }}
            </div>
            <div class="dialog-subtitle">
              {{
                isEditMode
                  ? "Update user information and permissions"
                  : "Create a new system user account"
              }}
            </div>
          </div>
        </v-card-title>

        <v-divider></v-divider>

        <v-card-text class="dialog-content">
          <v-form ref="formRef">
            <v-row>
              <v-col cols="12">
                <label class="form-label">
                  <v-icon size="small" color="#ed985f">mdi-account</v-icon>
                  Full Name *
                </label>
                <v-text-field
                  v-model="userForm.name"
                  variant="outlined"
                  density="comfortable"
                  placeholder="Enter full name"
                  :rules="[rules.required]"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <label class="form-label">
                  <v-icon size="small" color="#ed985f">mdi-at</v-icon>
                  Username *
                </label>
                <v-text-field
                  v-model="userForm.username"
                  variant="outlined"
                  density="comfortable"
                  placeholder="Enter username"
                  :rules="[rules.required]"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <label class="form-label">
                  <v-icon size="small" color="#ed985f">mdi-email</v-icon>
                  Email *
                </label>
                <v-text-field
                  v-model="userForm.email"
                  type="email"
                  variant="outlined"
                  density="comfortable"
                  placeholder="Enter email"
                  :rules="[rules.required, rules.email]"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <label class="form-label">
                  <v-icon size="small" color="#ed985f">mdi-shield-account</v-icon>
                  Role *
                </label>
                <v-select
                  v-model="userForm.role"
                  :items="roleOptions"
                  variant="outlined"
                  density="comfortable"
                  :rules="[rules.required]"
                ></v-select>
              </v-col>

              <v-col cols="12" md="6">
                <label class="form-label">
                  <v-icon size="small" color="#ed985f">mdi-badge-account</v-icon>
                  Link to Employee (Optional)
                </label>
                <v-autocomplete
                  v-model="userForm.employee_id"
                  :items="availableEmployees"
                  item-title="full_name"
                  item-value="id"
                  variant="outlined"
                  density="comfortable"
                  placeholder="Select employee"
                  clearable
                >
                  <template v-slot:item="{ props, item }">
                    <v-list-item v-bind="props">
                      <template v-slot:prepend>
                        <v-icon size="20">mdi-account</v-icon>
                      </template>
                      <template v-slot:title>
                        {{ item.raw.full_name }}
                      </template>
                      <template v-slot:subtitle>
                        {{ item.raw.employee_number }}
                      </template>
                    </v-list-item>
                  </template>
                </v-autocomplete>
              </v-col>

              <v-col cols="12" md="6">
                <label class="form-label">
                  <v-icon size="small" color="#ed985f">mdi-lock</v-icon>
                  Password {{ isEditMode ? "(Leave blank to keep current)" : "*" }}
                </label>
                <v-text-field
                  v-model="userForm.password"
                  type="password"
                  variant="outlined"
                  density="comfortable"
                  placeholder="Enter password"
                  :rules="isEditMode ? [] : [rules.required, rules.minLength]"
                ></v-text-field>
              </v-col>

              <v-col cols="12" md="6">
                <label class="form-label">
                  <v-icon size="small" color="#ed985f">mdi-lock-check</v-icon>
                  Confirm Password
                </label>
                <v-text-field
                  v-model="userForm.password_confirmation"
                  type="password"
                  variant="outlined"
                  density="comfortable"
                  placeholder="Confirm password"
                  :rules="
                    userForm.password
                      ? [
                          (v) =>
                            v === userForm.password || 'Passwords must match',
                        ]
                      : []
                  "
                ></v-text-field>
              </v-col>

              <v-col cols="12">
                <v-switch
                  v-model="userForm.is_active"
                  label="Active User"
                  color="success"
                  hide-details
                ></v-switch>
              </v-col>
            </v-row>
          </v-form>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <button
            class="dialog-btn dialog-btn-cancel"
            @click="closeUserDialog"
            :disabled="saving"
          >
            Cancel
          </button>
          <button
            class="dialog-btn dialog-btn-primary"
            @click="saveUser"
            :disabled="saving"
          >
            <v-progress-circular
              v-if="saving"
              indeterminate
              size="16"
              width="2"
              class="mr-2"
            ></v-progress-circular>
            <v-icon v-else size="20" class="mr-2">mdi-content-save</v-icon>
            {{ isEditMode ? "Update User" : "Create User" }}
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- View User Dialog -->
    <v-dialog v-model="showViewDialog" max-width="600px">
      <v-card class="modern-dialog">
        <v-card-title class="dialog-header">
          <div class="dialog-icon-wrapper primary">
            <v-icon size="24">mdi-account</v-icon>
          </div>
          <div>
            <div class="dialog-title">User Details</div>
            <div class="dialog-subtitle">View user information</div>
          </div>
        </v-card-title>

        <v-divider></v-divider>

        <v-card-text class="dialog-content" v-if="selectedUser">
          <div class="user-detail-grid">
            <div class="detail-item">
              <div class="detail-label">Full Name</div>
              <div class="detail-value">{{ selectedUser.name }}</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Username</div>
              <div class="detail-value">@{{ selectedUser.username }}</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Email</div>
              <div class="detail-value">{{ selectedUser.email }}</div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Role</div>
              <div class="detail-value">
                <v-chip :color="getRoleColor(selectedUser.role)" size="small">
                  {{ getRoleLabel(selectedUser.role) }}
                </v-chip>
              </div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Status</div>
              <div class="detail-value">
                <v-chip
                  :color="selectedUser.is_active ? 'success' : 'error'"
                  size="small"
                >
                  {{ selectedUser.is_active ? "Active" : "Inactive" }}
                </v-chip>
              </div>
            </div>
            <div class="detail-item" v-if="selectedUser.employee">
              <div class="detail-label">Linked Employee</div>
              <div class="detail-value">
                {{ getEmployeeName(selectedUser.employee) }} ({{
                  selectedUser.employee.employee_number
                }})
              </div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Last Login</div>
              <div class="detail-value">
                {{
                  selectedUser.last_login_at
                    ? formatDate(selectedUser.last_login_at)
                    : "Never"
                }}
              </div>
            </div>
            <div class="detail-item">
              <div class="detail-label">Account Created</div>
              <div class="detail-value">
                {{ formatDate(selectedUser.created_at) }}
              </div>
            </div>
          </div>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <button class="dialog-btn dialog-btn-cancel" @click="showViewDialog = false">
            Close
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>

    <!-- Delete Confirmation Dialog -->
    <v-dialog v-model="showDeleteDialog" max-width="500px">
      <v-card class="modern-dialog">
        <v-card-title class="dialog-header error">
          <div class="dialog-icon-wrapper error">
            <v-icon size="24">mdi-alert</v-icon>
          </div>
          <div>
            <div class="dialog-title">Confirm Delete</div>
            <div class="dialog-subtitle">This action cannot be undone</div>
          </div>
        </v-card-title>

        <v-divider></v-divider>

        <v-card-text class="dialog-content">
          <p>
            Are you sure you want to delete user
            <strong>{{ userToDelete?.username }}</strong>?
          </p>
          <p class="text-error mt-3">
            This will permanently remove the user account and cannot be undone.
          </p>
        </v-card-text>

        <v-divider></v-divider>

        <v-card-actions class="dialog-actions">
          <v-spacer></v-spacer>
          <button
            class="dialog-btn dialog-btn-cancel"
            @click="showDeleteDialog = false"
            :disabled="deleting"
          >
            Cancel
          </button>
          <button
            class="dialog-btn dialog-btn-error"
            @click="deleteUser"
            :disabled="deleting"
          >
            <v-progress-circular
              v-if="deleting"
              indeterminate
              size="16"
              width="2"
              class="mr-2"
            ></v-progress-circular>
            <v-icon v-else size="20" class="mr-2">mdi-delete</v-icon>
            Delete User
          </button>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useToast } from "vue-toastification";
import api from "@/services/api";

const router = useRouter();
const toast = useToast();

const formRef = ref(null);
const loading = ref(false);
const saving = ref(false);
const deleting = ref(false);
const users = ref([]);
const stats = ref({
  total: 0,
  active: 0,
  inactive: 0,
  by_role: {
    admin: 0,
    hr: 0,
    payrollist: 0,
    employee: 0,
  },
});
const availableEmployees = ref([]);

const search = ref("");
const roleFilter = ref(null);
const statusFilter = ref(null);

const showUserDialog = ref(false);
const showViewDialog = ref(false);
const showDeleteDialog = ref(false);
const isEditMode = ref(false);
const selectedUser = ref(null);
const userToDelete = ref(null);

const userForm = ref({
  name: "",
  username: "",
  email: "",
  password: "",
  password_confirmation: "",
  role: "",
  employee_id: null,
  is_active: true,
});

const roleOptions = [
  { title: "Admin", value: "admin" },
  { title: "hr", value: "hr" },
  { title: "Payroll Staff", value: "payrollist" },
  { title: "Employee", value: "employee" },
];

const statusOptions = [
  { title: "Active", value: true },
  { title: "Inactive", value: false },
];

const headers = [
  { title: "User", key: "user", sortable: true },
  { title: "Email", key: "email", sortable: true },
  { title: "Role", key: "role", sortable: true },
  { title: "Employee", key: "employee", sortable: false },
  { title: "Status", key: "status", sortable: true },
  { title: "Last Login", key: "last_login", sortable: true },
  { title: "Actions", key: "actions", sortable: false, align: "center" },
];

const rules = {
  required: (v) => !!v || "This field is required",
  email: (v) => /.+@.+\..+/.test(v) || "Invalid email address",
  minLength: (v) => v?.length >= 8 || "Minimum 8 characters required",
};

const filteredUsers = computed(() => {
  let filtered = users.value;

  if (roleFilter.value) {
    filtered = filtered.filter((user) => user.role === roleFilter.value);
  }

  if (statusFilter.value !== null) {
    filtered = filtered.filter((user) => user.is_active === statusFilter.value);
  }

  return filtered;
});

function getRoleColor(role) {
  const colors = {
    admin: "error",
    hr: "info",
    payrollist: "warning",
    employee: "success",
  };
  return colors[role] || "grey";
}

function getRoleIcon(role) {
  const icons = {
    admin: "mdi-shield-crown",
    hr: "mdi-calculator",
    payrollist: "mdi-cash-multiple",
    employee: "mdi-account",
  };
  return icons[role] || "mdi-account";
}

function getRoleLabel(role) {
  const labels = {
    admin: "Admin",
    hr: "hr",
    payrollist: "Payroll Staff",
    employee: "Employee",
  };
  return labels[role] || role;
}

function getEmployeeName(employee) {
  if (!employee) return "";
  return `${employee.first_name} ${employee.middle_name || ""} ${employee.last_name}`.trim();
}

function formatDate(date) {
  return new Date(date).toLocaleDateString("en-US", {
    year: "numeric",
    month: "short",
    day: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

async function fetchUsers() {
  loading.value = true;
  try {
    const response = await api.get("/users");
    users.value = response.data;
  } catch (error) {
    console.error("Error fetching users:", error);
    toast.error("Failed to load users");
  } finally {
    loading.value = false;
  }
}

async function fetchStats() {
  try {
    const response = await api.get("/users/stats");
    stats.value = response.data;
  } catch (error) {
    console.error("Error fetching stats:", error);
  }
}

async function fetchAvailableEmployees() {
  try {
    const response = await api.get("/users/available-employees");
    availableEmployees.value = response.data;
  } catch (error) {
    console.error("Error fetching available employees:", error);
  }
}

function openAddDialog() {
  isEditMode.value = false;
  userForm.value = {
    name: "",
    username: "",
    email: "",
    password: "",
    password_confirmation: "",
    role: "",
    employee_id: null,
    is_active: true,
  };
  showUserDialog.value = true;
  fetchAvailableEmployees();
}

function editUser(user) {
  isEditMode.value = true;
  selectedUser.value = user;
  userForm.value = {
    name: user.name,
    username: user.username,
    email: user.email,
    password: "",
    password_confirmation: "",
    role: user.role,
    employee_id: user.employee_id,
    is_active: user.is_active,
  };
  showUserDialog.value = true;
  fetchAvailableEmployees();
}

function viewUser(user) {
  selectedUser.value = user;
  showViewDialog.value = true;
}

function closeUserDialog() {
  showUserDialog.value = false;
  selectedUser.value = null;
  isEditMode.value = false;
}

async function saveUser() {
  // Validate form before submitting
  const { valid } = await formRef.value.validate();
  if (!valid) {
    toast.warning("Please fill in all required fields correctly");
    return;
  }

  saving.value = true;
  try {
    if (isEditMode.value) {
      await api.put(`/users/${selectedUser.value.id}`, userForm.value);
      toast.success("User updated successfully");
    } else {
      await api.post("/users", userForm.value);
      toast.success("User created successfully");
    }
    closeUserDialog();
    fetchUsers();
    fetchStats();
  } catch (error) {
    console.error("Error saving user:", error);
    toast.error(
      error.response?.data?.message || "Failed to save user"
    );
  } finally {
    saving.value = false;
  }
}

async function toggleUserStatus(user) {
  try {
    await api.post(`/users/${user.id}/toggle-status`);
    toast.success(
      `User ${user.is_active ? "deactivated" : "activated"} successfully`
    );
    fetchUsers();
    fetchStats();
  } catch (error) {
    console.error("Error toggling user status:", error);
    toast.error(error.response?.data?.message || "Failed to update user status");
  }
}

function confirmDelete(user) {
  userToDelete.value = user;
  showDeleteDialog.value = true;
}

async function deleteUser() {
  deleting.value = true;
  try {
    await api.delete(`/users/${userToDelete.value.id}`);
    toast.success("User deleted successfully");
    showDeleteDialog.value = false;
    userToDelete.value = null;
    fetchUsers();
    fetchStats();
  } catch (error) {
    console.error("Error deleting user:", error);
    toast.error(error.response?.data?.message || "Failed to delete user");
  } finally {
    deleting.value = false;
  }
}

onMounted(() => {
  fetchUsers();
  fetchStats();
});
</script>

<style scoped>
.user-management-page {
  padding: 24px;
  max-width: 1600px;
  margin: 0 auto;
}

/* Page Header */
.page-header {
  background: white;
  border-radius: 16px;
  padding: 24px;
  margin-bottom: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.header-content {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.back-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  background: transparent;
  border: none;
  color: #666;
  font-size: 14px;
  padding: 8px 12px;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.3s ease;
}

.back-button:hover {
  background: #f5f5f5;
  color: #ed985f;
}

.header-main {
  display: flex;
  justify-content: space-between;
  align-items: center;
  flex-wrap: wrap;
  gap: 16px;
}

.page-title-section {
  display: flex;
  align-items: center;
  gap: 16px;
}

.page-icon-badge {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: linear-gradient(135deg, #ed985f 0%, #f5b98c 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);
}

.page-title {
  font-size: 28px;
  font-weight: 700;
  color: #1a1a1a;
  margin: 0;
}

.page-subtitle {
  font-size: 14px;
  color: #666;
  margin: 4px 0 0 0;
}

.action-buttons {
  display: flex;
  gap: 12px;
}

.action-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 10px 20px;
  border: none;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

.action-btn-primary {
  background: linear-gradient(135deg, #ed985f 0%, #f5b98c 100%);
  color: white;
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);
}

.action-btn-primary:hover {
  transform: translateY(-2px);
  box-shadow: 0 6px 20px rgba(237, 152, 95, 0.4);
}

/* Stats Grid */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
  margin-bottom: 24px;
}

.stat-card {
  background: white;
  border-radius: 12px;
  padding: 20px;
  display: flex;
  align-items: center;
  gap: 16px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
  transition: all 0.3s ease;
}

.stat-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 10px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
}

.stat-icon.total {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.stat-icon.active {
  background: linear-gradient(135deg, #4caf50 0%, #66bb6a 100%);
}

.stat-icon.inactive {
  background: linear-gradient(135deg, #f44336 0%, #e57373 100%);
}

.stat-icon.admin {
  background: linear-gradient(135deg, #ff5722 0%, #ff7043 100%);
}

.stat-content {
  flex: 1;
}

.stat-label {
  font-size: 13px;
  color: #666;
  margin-bottom: 4px;
}

.stat-value {
  font-size: 24px;
  font-weight: 700;
  color: #1a1a1a;
}

.stat-value.success {
  color: #4caf50;
}

/* Content Card */
.content-card {
  background: white;
  border-radius: 16px;
  padding: 24px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
}

.table-controls {
  display: flex;
  gap: 16px;
  margin-bottom: 24px;
  flex-wrap: wrap;
}

.search-field {
  flex: 1;
  min-width: 250px;
}

.filter-select {
  min-width: 180px;
}

/* Table Styles */
.user-cell {
  display: flex;
  align-items: center;
  gap: 12px;
}

.user-avatar {
  width: 40px;
  height: 40px;
  border-radius: 50%;
  background: linear-gradient(135deg, #ed985f 0%, #f5b98c 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
  overflow: hidden;
}

.user-avatar img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.user-info {
  display: flex;
  flex-direction: column;
}

.user-name {
  font-weight: 600;
  color: #1a1a1a;
}

.user-username {
  font-size: 12px;
  color: #999;
}

.email-cell {
  color: #666;
}

.employee-cell {
  display: flex;
  flex-direction: column;
}

.employee-number {
  font-size: 12px;
  color: #999;
}

.employee-name {
  font-size: 13px;
  color: #666;
}

.action-buttons-cell {
  display: flex;
  gap: 4px;
  justify-content: center;
}

/* Dialog Styles */
.dialog-header {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 24px !important;
  background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
}

.dialog-header.error {
  background: linear-gradient(135deg, #ffebee 0%, #ffcdd2 100%);
}

.dialog-icon-wrapper {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: white;
}

.dialog-icon-wrapper.primary {
  background: linear-gradient(135deg, #ed985f 0%, #f5b98c 100%);
}

.dialog-icon-wrapper.error {
  background: linear-gradient(135deg, #f44336 0%, #e57373 100%);
}

.dialog-title {
  font-size: 20px;
  font-weight: 700;
  color: #1a1a1a;
}

.dialog-subtitle {
  font-size: 13px;
  color: #666;
  margin-top: 4px;
}

.dialog-content {
  padding: 24px !important;
}

.form-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  font-weight: 600;
  color: #333;
  margin-bottom: 8px;
}

.dialog-actions {
  padding: 16px 24px !important;
  background: #f8f9fa;
}

.dialog-btn {
  padding: 10px 24px;
  border: none;
  border-radius: 8px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  display: flex;
  align-items: center;
  gap: 8px;
}

.dialog-btn-cancel {
  background: #e0e0e0;
  color: #666;
}

.dialog-btn-cancel:hover {
  background: #d0d0d0;
}

.dialog-btn-primary {
  background: linear-gradient(135deg, #ed985f 0%, #f5b98c 100%);
  color: white;
}

.dialog-btn-primary:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);
}

.dialog-btn-error {
  background: linear-gradient(135deg, #f44336 0%, #e57373 100%);
  color: white;
}

.dialog-btn-error:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 12px rgba(244, 67, 54, 0.3);
}

/* User Detail Grid */
.user-detail-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
}

.detail-item {
  display: flex;
  flex-direction: column;
  gap: 8px;
}

.detail-label {
  font-size: 12px;
  font-weight: 600;
  color: #999;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

.detail-value {
  font-size: 14px;
  color: #1a1a1a;
  font-weight: 500;
}

.text-grey {
  color: #999 !important;
}

.text-error {
  color: #f44336 !important;
}
</style>

