<template>
  <v-app>
    <!-- Construction-themed Navigation Drawer -->
    <v-navigation-drawer
      v-model="drawer"
      :rail="rail"
      permanent
      @click="rail = false"
      class="construction-drawer"
      color="steel"
      elevation="4"
      app
    >
      <!-- User Profile Section -->
      <v-list-item nav class="user-profile-item pa-3">
        <template v-slot:prepend>
          <v-avatar size="40" color="primary">
            <v-img v-if="userAvatar" :src="userAvatar" cover></v-img>
            <v-icon v-else color="white">mdi-account</v-icon>
          </v-avatar>
        </template>
        <v-list-item-title>{{ userName }}</v-list-item-title>
        <v-list-item-subtitle>{{ userRole }}</v-list-item-subtitle>
        <template v-slot:append>
          <v-btn
            variant="text"
            :icon="rail ? 'mdi-chevron-right' : 'mdi-chevron-left'"
            @click.stop="rail = !rail"
            size="small"
            color="white"
          ></v-btn>
        </template>
      </v-list-item>

      <v-divider class="steel-divider mx-3"></v-divider>

      <!-- Navigation Menu -->
      <v-list density="compact" nav class="pa-2">
        <template v-for="item in menuItems" :key="item.value">
          <!-- Items with children (submenu) -->
          <v-list-group v-if="item.children" :value="item.value">
            <template v-slot:activator="{ props }">
              <v-list-item
                v-bind="props"
                :prepend-icon="item.icon"
                :title="item.title"
                color="hardhat"
                class="menu-item mb-1"
                rounded="lg"
              ></v-list-item>
            </template>
            <v-list-item
              v-for="child in item.children"
              :key="child.value"
              :prepend-icon="child.icon"
              :title="child.title"
              :value="child.value"
              :to="child.to"
              color="hardhat"
              class="menu-item mb-1 ml-4"
              rounded="lg"
            ></v-list-item>
          </v-list-group>
          
          <!-- Regular items without children -->
          <v-list-item
            v-else
            :prepend-icon="item.icon"
            :title="item.title"
            :value="item.value"
            :to="item.to"
            color="hardhat"
            class="menu-item mb-1"
            rounded="lg"
          ></v-list-item>
        </template>
      </v-list>

      <!-- Logout Button -->
      <template v-slot:append>
        <div class="pa-3">
          <v-btn
            block
            prepend-icon="mdi-logout"
            @click="handleLogout"
            color="error"
            variant="tonal"
            class="font-weight-bold"
          >
            Logout
          </v-btn>
        </div>
      </template>
    </v-navigation-drawer>

    <!-- Construction-themed App Bar -->
    <v-app-bar elevation="4" class="construction-appbar" color="surface" app>
      <!-- Construction-themed Title with Icon -->
      <div class="d-flex align-center">
        <v-icon
          icon="mdi-hard-hat"
          color="hardhat"
          size="32"
          class="mr-3 rotating-hardhat"
        ></v-icon>
        <div>
          <v-app-bar-title class="construction-header text-h6">
            {{ pageTitle }}
          </v-app-bar-title>
          <div class="text-caption text-medium-emphasis">
            Construction Payroll System
          </div>
        </div>
      </div>

      <v-spacer></v-spacer>

      <!-- Sync status indicator with construction theme -->
      <v-chip
        v-if="syncStore.pendingChanges > 0"
        color="info"
        size="small"
        class="mr-2 construction-chip"
        prepend-icon="mdi-sync"
      >
        {{ syncStore.pendingChanges }} pending
      </v-chip>

      <!-- Online/Offline indicator with construction safety colors -->
      <v-chip
        :color="syncStore.isOnline ? 'success' : 'error'"
        size="small"
        variant="elevated"
        class="construction-chip mr-2"
        :prepend-icon="syncStore.isOnline ? 'mdi-wifi' : 'mdi-wifi-off'"
      >
        {{ syncStore.isOnline ? "Online" : "Offline" }}
      </v-chip>

      <!-- Notification Bell -->
      <v-btn icon="mdi-bell-outline" variant="text" color="steel"></v-btn>
    </v-app-bar>

    <!-- Main Content Area -->
    <v-main class="main-content">
      <v-container fluid class="pa-6 main-container">
        <!-- Breadcrumbs for better navigation -->
        <v-breadcrumbs
          v-if="breadcrumbs.length > 1"
          :items="breadcrumbs"
          class="px-0 mb-4 breadcrumbs-construction"
        >
          <template v-slot:divider>
            <v-icon size="small">mdi-chevron-right</v-icon>
          </template>
          <template v-slot:item="{ item }">
            <v-breadcrumbs-item
              :to="item.to"
              :disabled="item.disabled"
              class="text-body-2"
            >
              <v-icon v-if="item.icon" size="small" class="mr-1">{{ item.icon }}</v-icon>
              {{ item.title }}
            </v-breadcrumbs-item>
          </template>
        </v-breadcrumbs>

        <router-view v-slot="{ Component }">
          <transition name="page-transition" mode="out-in">
            <component :is="Component" :key="route.fullPath" />
          </transition>
        </router-view>
      </v-container>
    </v-main>

    <!-- Logout Confirmation Dialog -->
    <v-dialog v-model="logoutDialog" max-width="450" persistent>
      <v-card>
        <v-card-title class="d-flex align-center bg-error pa-4">
          <v-icon icon="mdi-logout" size="28" class="mr-3"></v-icon>
          <span class="text-h6">Confirm Logout</span>
        </v-card-title>

        <v-card-text class="pa-6">
          <div class="text-body-1">
            Are you sure you want to logout from the Construction Payroll
            System?
          </div>
          <div class="text-body-2 text-medium-emphasis mt-2">
            Any unsaved changes will be lost.
          </div>
        </v-card-text>

        <v-card-actions class="pa-4">
          <v-spacer></v-spacer>
          <v-btn
            variant="text"
            @click="logoutDialog = false"
            :disabled="loggingOut"
          >
            Cancel
          </v-btn>
          <v-btn
            color="error"
            variant="elevated"
            @click="confirmLogout"
            :loading="loggingOut"
            prepend-icon="mdi-logout"
          >
            Logout
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-app>
</template>

<script setup>
import { ref, computed } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import { useSyncStore } from "@/stores/sync";
import { useToast } from "vue-toastification";
import api from "@/services/api";

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const syncStore = useSyncStore();
const toast = useToast();

const drawer = ref(true);
const rail = ref(false);
const logoutDialog = ref(false);
const loggingOut = ref(false);

const userName = computed(
  () => authStore.user?.name || authStore.user?.username || "User"
);
const userRole = computed(() => {
  const role = authStore.user?.role || "Employee";
  return role.charAt(0).toUpperCase() + role.slice(1);
});
const userAvatar = computed(() => {
  if (!authStore.user?.avatar) return null;
  const avatar = authStore.user.avatar;
  // If avatar is already a full URL, return it
  if (avatar.startsWith("http")) return avatar;
  // Otherwise, prepend the base URL (remove /api from VITE_API_URL)
  const apiUrl = (
    import.meta.env.VITE_API_URL || "http://localhost:8000/api"
  ).replace("/api", "");
  return `${apiUrl}/storage/${avatar}`;
});
const pageTitle = computed(() => route.meta.title || "Dashboard");

// Breadcrumbs generation
const breadcrumbs = computed(() => {
  const crumbs = [
    {
      title: "Home",
      icon: "mdi-home",
      to: "/",
      disabled: false
    }
  ];

  // Parse route path to create breadcrumbs
  const paths = route.path.split("/").filter(p => p);
  let currentPath = "";
  
  paths.forEach((path, index) => {
    currentPath += `/${path}`;
    const isLast = index === paths.length - 1;
    
    crumbs.push({
      title: path.replace(/-/g, " ").replace(/\b\w/g, l => l.toUpperCase()),
      to: currentPath,
      disabled: isLast
    });
  });

  return crumbs;
});

// Filter menu items based on user role with construction-themed icons
const menuItems = computed(() => {
  const allItems = [
    {
      title: "Dashboard",
      icon: "mdi-view-dashboard-variant",
      value: "admin-dashboard",
      to: "/admin-dashboard",
      roles: ["admin"],
    },
    {
      title: "Dashboard",
      icon: "mdi-view-dashboard-variant",
      value: "accountant-dashboard",
      to: "/accountant-dashboard",
      roles: ["accountant"],
    },
    {
      title: "My Dashboard",
      icon: "mdi-view-dashboard",
      value: "employee-dashboard",
      to: "/employee-dashboard",
      roles: ["employee"],
    },
    {
      title: "Employees",
      icon: "mdi-hard-hat",
      value: "employees",
      to: "/employees",
      roles: ["admin"],
    },
    {
      title: "Projects",
      icon: "mdi-office-building",
      value: "projects",
      to: "/projects",
      roles: ["admin"],
    },
    {
      title: "Attendance",
      icon: "mdi-clock-check-outline",
      value: "attendance",
      to: "/attendance",
      roles: ["admin", "accountant"],
    },
    {
      title: "Payroll",
      icon: "mdi-currency-php",
      value: "payroll",
      to: "/payroll",
      roles: ["admin", "accountant"],
    },
    {
      title: "Pay Rates",
      icon: "mdi-cash-multiple",
      value: "pay-rates",
      to: "/payroll/pay-rates",
      roles: ["admin", "accountant"],
    },
    {
      title: "My Resumes",
      icon: "mdi-file-document-outline",
      value: "resumes",
      to: "/resumes",
      roles: ["accountant"],
    },
    {
      title: "Resume Review",
      icon: "mdi-file-certificate-outline",
      value: "resume-review",
      to: "/resume-review",
      roles: ["admin"],
    },
    {
      title: "Benefits & Deductions",
      icon: "mdi-cash-multiple",
      value: "benefits",
      roles: ["admin", "accountant"],
      children: [
        {
          title: "Allowances",
          icon: "mdi-cash-plus",
          value: "allowances",
          to: "/allowances",
        },
        {
          title: "Loans",
          icon: "mdi-hand-coin-outline",
          value: "loans",
          to: "/loans",
        },
        {
          title: "Deductions",
          icon: "mdi-cash-minus",
          value: "deductions",
          to: "/deductions",
        },
      ],
    },
    {
      title: "Reports",
      icon: "mdi-file-chart-outline",
      value: "reports",
      to: "/reports",
      roles: ["admin", "accountant"],
    },
    {
      title: "My Profile",
      icon: "mdi-badge-account-horizontal-outline",
      value: "profile",
      to: "/profile",
      roles: ["admin", "accountant", "employee"],
    },
    {
      title: "Settings",
      icon: "mdi-cog-outline",
      value: "settings",
      to: "/settings",
      roles: ["admin"],
    },
  ];

  // Filter items based on user role
  const currentRole = authStore.userRole;
  return allItems.filter(
    (item) => !item.roles || item.roles.includes(currentRole)
  );
});

async function handleLogout() {
  // Show confirmation dialog
  logoutDialog.value = true;
}

async function confirmLogout() {
  loggingOut.value = true;
  try {
    await authStore.logout();
    toast.success("Logged out successfully");
    router.push("/login");
  } catch (error) {
    console.error("Logout error:", error);
    toast.error("Error logging out");
  } finally {
    loggingOut.value = false;
    logoutDialog.value = false;
  }
}

function scrollToSection(sectionId) {
  const element = document.getElementById(sectionId);
  if (element) {
    element.scrollIntoView({ behavior: "smooth", block: "start" });
  }
}

async function downloadCurrentPayslip() {
  try {
    // Get current payslip from employee dashboard
    const response = await api.get("/employee/dashboard");
    const currentPayslip = response.data.current_payslip;

    if (currentPayslip) {
      const pdfResponse = await api.get(`/payslips/${currentPayslip.id}/pdf`, {
        responseType: "blob",
      });

      const url = window.URL.createObjectURL(new Blob([pdfResponse.data]));
      const link = document.createElement("a");
      link.href = url;
      link.setAttribute("download", `payslip.pdf`);
      document.body.appendChild(link);
      link.click();
      link.remove();

      toast.success("Payslip downloaded successfully");
    } else {
      toast.warning("No current payslip available");
    }
  } catch (error) {
    console.error("Error downloading payslip:", error);
    toast.error("Failed to download payslip");
  }
}
</script>

<style scoped lang="scss">
// Construction-themed Navigation Drawer
.construction-drawer {
  background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%) !important;
  position: fixed !important;
  top: 0 !important;
  bottom: 0 !important;
  height: 100vh !important;
  overflow-y: auto !important;
  display: flex !important;
  flex-direction: column !important;
  border-right: 1px solid rgba(255, 255, 255, 0.08);

  :deep(.v-navigation-drawer__content) {
    height: 100% !important;
    display: flex !important;
    flex-direction: column !important;
    overflow-y: auto !important;
  }

  :deep(.v-list) {
    flex: 1 !important;
    overflow-y: auto !important;
  }

  :deep(.v-list-item) {
    color: rgba(255, 255, 255, 0.85) !important;
    margin: 4px 8px;
    border-radius: 12px;
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);

    .v-list-item-title {
      color: rgba(255, 255, 255, 0.9) !important;
      font-weight: 500;
    }

    .v-icon {
      color: rgba(255, 255, 255, 0.7) !important;
    }

    &:hover {
      background: rgba(99, 102, 241, 0.2) !important;
      transform: translateX(4px);

      .v-list-item-title {
        color: white !important;
      }

      .v-icon {
        color: rgba(255, 255, 255, 0.9) !important;
      }
    }
  }

  :deep(.v-list-item--active) {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%) !important;
    color: white !important;
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.4);

    &::before {
      opacity: 0;
    }
    
    .v-list-item-title {
      color: white !important;
      font-weight: 600;
    }

    .v-icon {
      color: white !important;
    }
  }

  // List group styling for submenus
  :deep(.v-list-group) {
    .v-list-item {
      color: rgba(255, 255, 255, 0.85) !important;
    }

    .v-list-group__items {
      .v-list-item {
        padding-left: 48px;
      }
    }
  }
}

.user-profile-item {
  background: rgba(0, 0, 0, 0.2);
  margin: 8px;
  border-radius: 12px;
  padding: 12px 8px;

  :deep(.v-list-item-title) {
    color: white !important;
    font-weight: 600;
    font-size: 0.95rem;
  }

  :deep(.v-list-item-subtitle) {
    color: rgba(255, 255, 255, 0.75) !important;
    text-transform: uppercase;
    font-size: 0.7rem;
    letter-spacing: 0.8px;
    font-weight: 600;
    margin-top: 2px;
  }

  :deep(.v-avatar) {
    border: 2px solid rgba(255, 255, 255, 0.2);
  }

  :deep(.v-btn) {
    color: rgba(255, 255, 255, 0.8) !important;
  }
}

.menu-item {
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);

  :deep(.v-list-item__prepend) {
    .v-icon {
      font-size: 20px;
      color: rgba(255, 255, 255, 0.7) !important;
    }
  }

  :deep(.v-list-item-title) {
    color: rgba(255, 255, 255, 0.9) !important;
    font-weight: 500;
  }

  &:hover {
    :deep(.v-list-item__prepend) {
      .v-icon {
        color: rgba(255, 255, 255, 0.95) !important;
      }
    }

    :deep(.v-list-item-title) {
      color: white !important;
    }
  }
}

// Steel beam divider effect
:deep(.steel-divider) {
  background: rgba(255, 255, 255, 0.1) !important;
  height: 1px;
  margin: 12px 16px;
}

// Logout button styling in drawer
.construction-drawer {
  :deep(.v-btn) {
    &.v-btn--variant-tonal {
      background-color: rgba(239, 68, 68, 0.15) !important;
      color: #fca5a5 !important;
      font-weight: 600;

      &:hover {
        background-color: rgba(239, 68, 68, 0.25) !important;
        color: #fef2f2 !important;
      }

      .v-icon {
        color: #fca5a5 !important;
      }
    }
  }
}

// Breadcrumbs styling
.breadcrumbs-construction {
  :deep(.v-breadcrumbs-item) {
    color: #546E7A;
    font-weight: 500;
    transition: all 0.2s ease;
    
    &:hover {
      color: #D84315;
    }
  }
  
  :deep(.v-breadcrumbs-item--disabled) {
    color: #D84315;
    font-weight: 600;
  }
  
  :deep(.v-breadcrumbs-divider) {
    color: #B0BEC5;
  }
}

// Construction App Bar styling
.construction-appbar {
  backdrop-filter: blur(10px);
  background-color: rgba(255, 255, 255, 0.95) !important;
  border-bottom: 1px solid rgba(0, 0, 0, 0.08) !important;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05) !important;
}

.construction-chip {
  font-weight: 600;
  text-transform: uppercase;
  font-size: 11px;
  letter-spacing: 0.5px;
}

// Rotating hardhat animation
.rotating-hardhat {
  animation: subtle-rotate 3s ease-in-out infinite;
}

@keyframes subtle-rotate {
  0%,
  100% {
    transform: rotate(0deg);
  }
  25% {
    transform: rotate(-5deg);
  }
  75% {
    transform: rotate(5deg);
  }
}

// Main content area
.main-content {
  background: #f8fafc;
  min-height: 100vh;
  overflow-y: auto;
}

.main-container {
  min-height: 100vh;
  padding-bottom: 24px;
}

// Page transitions
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.3s ease, transform 0.3s ease;
}

.fade-enter-from {
  opacity: 0;
  transform: translateY(10px);
}

.fade-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}
</style>
