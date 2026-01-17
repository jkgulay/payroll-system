<template>
  <v-app>
    <!-- Construction-themed Navigation Drawer -->
    <v-navigation-drawer
      v-model="drawer"
      :rail="rail && !isMobile"
      :temporary="isMobile"
      :permanent="!isMobile"
      class="construction-drawer"
      color="steel"
      elevation="4"
      :width="256"
      :rail-width="72"
      app
    >
      <!-- User Profile Section -->
      <v-list-item nav class="user-profile-item pa-3">
        <template v-slot:prepend>
          <v-tooltip location="right" :disabled="!rail || isMobile">
            <template v-slot:activator="{ props }">
              <v-avatar v-bind="props" size="40" color="primary">
                <v-img v-if="userAvatar" :src="userAvatar" cover></v-img>
                <v-icon v-else color="white">mdi-account</v-icon>
              </v-avatar>
            </template>
            <span>{{ userName }} - {{ userRole }}</span>
          </v-tooltip>
        </template>
        <v-list-item-title v-if="!rail">{{ userName }}</v-list-item-title>
        <v-list-item-subtitle v-if="!rail">{{ userRole }}</v-list-item-subtitle>
        <template v-slot:append v-if="!isMobile">
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
          <!-- Items with children (submenu) - hide in rail mode -->
          <v-list-group v-if="item.children && !rail" :value="item.value">
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
          <v-tooltip v-else location="right" :disabled="!rail || isMobile">
            <template v-slot:activator="{ props: tooltipProps }">
              <v-list-item
                v-bind="tooltipProps"
                :prepend-icon="item.icon"
                :title="rail ? '' : item.title"
                :value="item.value"
                :to="item.to || (item.children?.[0]?.to)"
                color="hardhat"
                class="menu-item mb-1"
                rounded="lg"
              ></v-list-item>
            </template>
            <span>{{ item.title }}</span>
          </v-tooltip>
        </template>
      </v-list>

      <!-- Logout Button -->
      <template v-slot:append>
        <div class="pa-3">
          <v-tooltip location="right" :disabled="!rail || isMobile">
            <template v-slot:activator="{ props }">
              <v-btn
                v-bind="props"
                v-if="rail && !isMobile"
                icon="mdi-logout"
                @click="handleLogout"
                color="error"
                variant="tonal"
                class="font-weight-bold logout-btn-rail"
                size="large"
              ></v-btn>
              <v-btn
                v-else
                block
                prepend-icon="mdi-logout"
                @click="handleLogout"
                color="error"
                variant="tonal"
                class="font-weight-bold logout-btn"
              >
                Logout
              </v-btn>
            </template>
            <span>Logout</span>
          </v-tooltip>
        </div>
      </template>
    </v-navigation-drawer>

    <!-- Construction-themed App Bar -->
    <v-app-bar elevation="4" class="construction-appbar" color="surface" app>
      <!-- Hamburger Menu Button (Mobile Only) -->
      <v-app-bar-nav-icon
        v-if="isMobile"
        @click="drawer = !drawer"
        color="hardhat"
      ></v-app-bar-nav-icon>

      <!-- Construction-themed Title with Icon -->
      <div class="d-flex align-center">
        <v-icon
          v-if="!isMobile"
          icon="mdi-hard-hat"
          color="hardhat"
          size="32"
          class="mr-3 rotating-hardhat"
        ></v-icon>
        <div>
          <v-app-bar-title :class="isMobile ? 'text-subtitle-1' : 'construction-header text-h6'">
            {{ isMobile ? (pageTitle.length > 20 ? pageTitle.substring(0, 20) + '...' : pageTitle) : pageTitle }}
          </v-app-bar-title>
          <div v-if="!isMobile" class="text-caption text-medium-emphasis">
            Construction Payroll System
          </div>
        </div>
      </div>

      <v-spacer></v-spacer>

      <!-- Notification Bell -->
      <v-btn icon="mdi-bell-outline" variant="text" color="steel" :size="isMobile ? 'small' : 'default'"></v-btn>
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
              <v-icon v-if="item.icon" size="small" class="mr-1">{{
                item.icon
              }}</v-icon>
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
    <v-dialog v-model="logoutDialog" max-width="480" persistent>
      <v-card class="logout-dialog-card" elevation="24">
        <!-- Modern Header with Gradient -->
        <div class="logout-dialog-header">
          <div class="logout-icon-circle">
            <v-icon icon="mdi-logout-variant" size="40" color="white"></v-icon>
          </div>
          <h2 class="logout-dialog-title">Confirm Logout</h2>
          <p class="logout-dialog-subtitle">End your current session</p>
        </div>

        <!-- Content Section -->
        <v-card-text class="logout-dialog-content pa-8">
          <!-- Warning Banner -->
          <div class="logout-warning-banner">
            <v-icon icon="mdi-alert-circle-outline" size="24" color="#ff6f00"></v-icon>
            <div class="ml-3">
              <div class="logout-message-title">
                Are you sure you want to logout?
              </div>
              <div class="logout-message-subtitle">
                You will be signed out from the Construction Payroll System
              </div>
            </div>
          </div>

          <!-- Info Note -->
          <div class="logout-info-note">
            <v-icon icon="mdi-information-outline" size="18"></v-icon>
            <span>Any unsaved changes will be lost</span>
          </div>
        </v-card-text>

        <!-- Action Buttons -->
        <v-card-actions class="logout-dialog-actions pa-6 pt-0">
          <v-btn
            variant="outlined"
            size="large"
            @click="logoutDialog = false"
            :disabled="loggingOut"
            class="logout-cancel-btn flex-grow-1"
          >
            <v-icon start>mdi-close</v-icon>
            Cancel
          </v-btn>
          <v-btn
            size="large"
            @click="confirmLogout"
            :loading="loggingOut"
            class="logout-confirm-btn flex-grow-1"
            elevation="8"
          >
            <v-icon start>mdi-logout</v-icon>
            Logout
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-app>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from "vue";
import { useRouter, useRoute } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import { useToast } from "vue-toastification";
import { useDisplay } from "vuetify";
import api from "@/services/api";

const router = useRouter();
const route = useRoute();
const authStore = useAuthStore();
const toast = useToast();
const { mobile, mdAndDown } = useDisplay();

const drawer = ref(true);
const rail = ref(false);
const logoutDialog = ref(false);
const loggingOut = ref(false);
const isMobile = computed(() => mdAndDown.value);

// Handle initial drawer state for mobile
onMounted(() => {
  if (isMobile.value) {
    drawer.value = false;
    rail.value = false;
  }
});

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
      disabled: false,
    },
  ];

  // Parse route path to create breadcrumbs
  const paths = route.path.split("/").filter((p) => p);
  let currentPath = "";

  paths.forEach((path, index) => {
    currentPath += `/${path}`;
    const isLast = index === paths.length - 1;

    crumbs.push({
      title: path.replace(/-/g, " ").replace(/\b\w/g, (l) => l.toUpperCase()),
      to: currentPath,
      disabled: isLast,
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
      title: "Biometric Import",
      icon: "mdi-file-upload-outline",
      value: "biometric-import",
      to: "/biometric-import",
      roles: ["admin", "accountant"],
    },
    {
      title: "Payroll",
      icon: "mdi-cash-multiple",
      value: "payroll",
      to: "/payroll",
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
      title: "HR Management",
      icon: "mdi-account-tie",
      value: "hr-management",
      roles: ["admin"],
      children: [
        {
          title: "Resume Review",
          icon: "mdi-file-certificate-outline",
          value: "resume-review",
          to: "/resume-review",
          roles: ["admin"],
        },
        {
          title: "Leave Approval",
          icon: "mdi-calendar-check",
          value: "leave-approval",
          to: "/leave-approval",
          roles: ["admin", "hr"],
        },
        {
          title: "Resignations",
          icon: "mdi-briefcase-remove-outline",
          value: "resignations",
          to: "/resignations",
          roles: ["admin", "accountant"],
        },
      ],
    },
    {
      title: "Benefits & Deductions",
      icon: "mdi-cash-multiple",
      value: "benefits",
      roles: ["admin", "accountant"],
      children: [
        {
          title: "Allowances",
          icon: "mdi-food",
          value: "allowances",
          to: "/allowances",
          roles: ["admin", "accountant", "hr"],
        },
        {
          title: "13th Month Pay",
          icon: "mdi-gift-outline",
          value: "thirteenth-month-pay",
          to: "/thirteenth-month-pay",
          roles: ["admin", "accountant", "hr"],
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
        {
          title: "Cash Bonds",
          icon: "mdi-cash-lock",
          value: "cash-bonds",
          to: "/cash-bonds",
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
      title: "My Leaves",
      icon: "mdi-calendar-clock",
      value: "my-leaves",
      to: "/my-leaves",
      roles: ["employee"],
    },
    {
      title: "My Loans",
      icon: "mdi-hand-coin-outline",
      value: "my-loans",
      to: "/loans",
      roles: ["employee"],
    },
    {
      title: "My Resignation",
      icon: "mdi-briefcase-remove-outline",
      value: "my-resignation",
      to: "/my-resignation",
      roles: ["employee"],
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
// Modern Navigation Drawer with glassmorphism
.construction-drawer {
  background: linear-gradient(180deg, rgba(30, 41, 59, 0.95) 0%, rgba(15, 23, 42, 0.98) 100%) !important;
  backdrop-filter: blur(40px) saturate(180%) !important;
  -webkit-backdrop-filter: blur(40px) saturate(180%) !important;
  position: fixed !important;
  top: 0 !important;
  bottom: 0 !important;
  height: 100vh !important;
  overflow-y: auto !important;
  display: flex !important;
  flex-direction: column !important;
  border-right: 1px solid rgba(139, 92, 246, 0.2);
  box-shadow: 4px 0 24px rgba(0, 0, 0, 0.12);

  :deep(.v-navigation-drawer__content) {
    height: 100% !important;
    display: flex !important;
    flex-direction: column !important;
    overflow-y: auto !important;
    position: relative;
    
    &::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: radial-gradient(circle at top left, rgba(99, 102, 241, 0.1), transparent 50%);
      pointer-events: none;
    }
  }

  :deep(.v-list) {
    flex: 1 !important;
    overflow-y: auto !important;
  }

  :deep(.v-list-item) {
    color: rgba(255, 255, 255, 0.9) !important;
    margin: 6px 12px;
    border-radius: 14px;
    transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
    position: relative;
    overflow: hidden;
    
    &::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(135deg, rgba(99, 102, 241, 0.1), rgba(139, 92, 246, 0.05));
      opacity: 0;
      transition: opacity 0.3s ease;
    }

    .v-list-item-title {
      color: rgba(255, 255, 255, 0.95) !important;
      font-weight: 500;
      font-size: 0.95rem;
    }

    .v-icon {
      color: rgba(255, 255, 255, 0.75) !important;
      transition: all 0.3s ease;
    }

    &:hover {
      background: rgba(99, 102, 241, 0.15) !important;
      transform: translateX(6px) scale(1.02);
      box-shadow: 0 4px 16px rgba(99, 102, 241, 0.2);
      
      &::before {
        opacity: 1;
      }

      .v-list-item-title {
        color: white !important;
      }

      .v-icon {
        color: white !important;
        transform: scale(1.1);
      }
    }
  }

  :deep(.v-list-item--active) {
    background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%) !important;
    color: white !important;
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.35), 
                0 0 0 1px rgba(255, 255, 255, 0.1) inset;
    border: 1px solid rgba(255, 255, 255, 0.15);

    &::before {
      opacity: 1;
      background: linear-gradient(135deg, rgba(255, 255, 255, 0.1), transparent);
    }

    .v-list-item-title {
      color: white !important;
      font-weight: 600;
      letter-spacing: 0.2px;
    }

    .v-icon {
      color: white !important;
      filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
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
  
  // Rail mode specific styling
  &.v-navigation-drawer--rail {
    :deep(.v-list-item) {
      padding: 0 !important;
      margin: 6px auto !important;
      justify-content: center !important;
      align-items: center !important;
      min-height: 48px !important;
      height: 48px !important;
      width: 48px !important;
      border-radius: 12px !important;
      display: flex !important;
      flex-shrink: 0 !important;
      position: relative !important;
      
      .v-list-item__overlay {
        border-radius: 12px !important;
      }
      
      .v-list-item__prepend {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        height: 100% !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        opacity: 1 !important;
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        
        .v-icon {
          font-size: 22px !important;
          margin: 0 !important;
          display: flex !important;
          align-items: center !important;
          justify-content: center !important;
          position: relative !important;
        }
      }
      
      .v-list-item__content {
        display: none !important;
        width: 0 !important;
        flex: none !important;
      }
      
      .v-list-item__append {
        display: none !important;
        width: 0 !important;
      }
      
      &:hover {
        transform: scale(1.05);
        background: rgba(99, 102, 241, 0.25) !important;
      }
      
      &::before {
        border-radius: 12px !important;
      }
    }
    
    :deep(.v-list-item--active) {
      background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%) !important;
      
      .v-icon {
        color: white !important;
      }
      
      &::before {
        opacity: 0 !important;
      }
    }
    
    :deep(.v-list-group) {
      display: none;
    }
    
    .user-profile-item {
      padding: 0 !important;
      margin: 8px auto !important;
      justify-content: center !important;
      align-items: center !important;
      height: 56px !important;
      width: 56px !important;
      position: relative;
      border-radius: 50% !important;
      display: flex !important;
      
      :deep(.v-list-item__prepend) {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
        height: 100% !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
      }
      
      :deep(.v-list-item__content) {
        display: none !important;
      }
      
      :deep(.v-list-item__append) {
        display: flex !important;
        position: absolute;
        right: -8px;
        top: -4px;
        opacity: 1 !important;
        z-index: 100 !important;
        width: auto !important;
        
        .v-btn {
          width: 24px !important;
          height: 24px !important;
          min-width: 24px !important;
          padding: 0 !important;
          background: rgba(0, 0, 0, 0.3) !important;
          opacity: 1 !important;
          
          &:hover {
            background: rgba(0, 0, 0, 0.5) !important;
          }
        }
      }
      
      :deep(.v-avatar) {
        margin: 0 !important;
        position: relative !important;
      }
      
      &:hover {
        transform: none !important;
        background: rgba(0, 0, 0, 0.3) !important;
      }
    }
    
    .steel-divider {
      margin: 8px 16px !important;
    }
  }
}

.user-profile-item {
  background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(139, 92, 246, 0.1) 100%);
  backdrop-filter: blur(10px);
  margin: 12px;
  border-radius: 16px;
  padding: 16px 12px;
  transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
  min-height: 72px;
  border: 1px solid rgba(139, 92, 246, 0.2);
  position: relative;
  overflow: hidden;
  
  &::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at top right, rgba(236, 72, 153, 0.1), transparent 70%);
    opacity: 0;
    transition: opacity 0.3s ease;
  }
  
  &:hover {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.25) 0%, rgba(139, 92, 246, 0.2) 100%);
    box-shadow: 0 8px 20px rgba(99, 102, 241, 0.25);
    border-color: rgba(139, 92, 246, 0.4);
    
    &::before {
      opacity: 1;
    }
  }

  :deep(.v-list-item-title) {
    color: white !important;
    font-weight: 600;
    font-size: 1rem;
    letter-spacing: 0.2px;
  }

  :deep(.v-list-item-subtitle) {
    color: rgba(255, 255, 255, 0.8) !important;
    font-size: 0.75rem;
    letter-spacing: 1px;
    font-weight: 500;
    margin-top: 4px;
    text-transform: uppercase;
  }

  :deep(.v-avatar) {
    border: 3px solid rgba(139, 92, 246, 0.4);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
    
    &:hover {
      border-color: rgba(139, 92, 246, 0.8);
      transform: scale(1.05);
    }
  }

  :deep(.v-btn) {
    color: rgba(255, 255, 255, 0.85) !important;
    transition: all 0.3s ease;
    
    &:hover {
      color: white !important;
      background: rgba(255, 255, 255, 0.1) !important;
    }
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

// Modern divider effect
:deep(.steel-divider) {
  background: linear-gradient(90deg, 
    transparent 0%, 
    rgba(139, 92, 246, 0.4) 50%, 
    transparent 100%) !important;
  height: 2px;
  margin: 16px 20px;
  border-radius: 999px;
  box-shadow: 0 0 16px rgba(139, 92, 246, 0.3);
}

// Modern logout button styling in drawer
.logout-btn,
.logout-btn-rail {
  background: linear-gradient(135deg, rgba(239, 68, 68, 0.15) 0%, rgba(220, 38, 38, 0.2) 100%) !important;
  color: #fca5a5 !important;
  font-weight: 600;
  border: 1px solid rgba(239, 68, 68, 0.3);
  transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
  backdrop-filter: blur(8px);

  &:hover {
    background: linear-gradient(135deg, rgba(239, 68, 68, 0.3) 0%, rgba(220, 38, 38, 0.35) 100%) !important;
    color: #fef2f2 !important;
    box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3);
    transform: translateY(-2px);
    border-color: rgba(239, 68, 68, 0.5);
    
    .v-icon {
      color: #fef2f2 !important;
      transform: rotate(-10deg) scale(1.1);
    }
  }

  .v-icon {
    color: #fca5a5 !important;
    font-size: 24px !important;
    transition: all 0.3s ease;
  }
}

.logout-btn-rail {
  width: 52px !important;
  height: 52px !important;
  min-width: 52px !important;
  border-radius: 14px !important;
}

// Modern breadcrumbs styling
.breadcrumbs-construction {
  :deep(.v-breadcrumbs-item) {
    color: #64748b;
    font-weight: 500;
    transition: all 0.3s ease;
    padding: 6px 12px;
    border-radius: 8px;

    &:hover {
      color: #6366f1;
      background: rgba(99, 102, 241, 0.08);
      transform: translateY(-1px);
    }
  }

  :deep(.v-breadcrumbs-item--disabled) {
    color: #6366f1;
    font-weight: 600;
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(139, 92, 246, 0.08) 100%);
    padding: 6px 12px;
    border-radius: 8px;
  }

  :deep(.v-breadcrumbs-divider) {
    color: #cbd5e1;
    font-size: 1.1rem;
  }
}

// Modern App Bar styling with glassmorphism
.construction-appbar {
  backdrop-filter: blur(40px) saturate(180%);
  -webkit-backdrop-filter: blur(40px) saturate(180%);
  background: linear-gradient(135deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 0.95) 100%) !important;
  border-bottom: 1px solid rgba(99, 102, 241, 0.15) !important;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05), 
              0 0 0 1px rgba(99, 102, 241, 0.05) inset !important;
  position: relative;
  
  &::before {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 3px;
    background: linear-gradient(90deg, 
      transparent 0%, 
      rgba(99, 102, 241, 0.3) 25%,
      rgba(139, 92, 246, 0.4) 50%,
      rgba(236, 72, 153, 0.3) 75%,
      transparent 100%);
    opacity: 0.6;
  }
}

.construction-chip {
  font-weight: 600;
  font-size: 12px;
  letter-spacing: 0.5px;
  background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(139, 92, 246, 0.08) 100%);
  border: 1px solid rgba(99, 102, 241, 0.2);
  backdrop-filter: blur(8px);
  transition: all 0.3s ease;
  
  &:hover {
    background: linear-gradient(135deg, rgba(99, 102, 241, 0.15) 0%, rgba(139, 92, 246, 0.12) 100%);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(99, 102, 241, 0.2);
  }
}

// Modern icon animation
.rotating-hardhat {
  animation: modernBounce 3s ease-in-out infinite;
  filter: drop-shadow(0 2px 4px rgba(99, 102, 241, 0.3));
}

@keyframes modernBounce {
  0%, 100% {
    transform: translateY(0) rotate(0deg);
  }
  25% {
    transform: translateY(-3px) rotate(-5deg);
  }
  50% {
    transform: translateY(0) rotate(0deg);
  }
  75% {
    transform: translateY(-3px) rotate(5deg);
  }
}

// Main content area with modern gradient background
.main-content {
  background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 50%, #f1f5f9 100%);
  min-height: 100vh;
  overflow-y: auto;
  position: relative;
  
  &::before {
    content: '';
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: 
      radial-gradient(circle at 20% 20%, rgba(99, 102, 241, 0.03), transparent 40%),
      radial-gradient(circle at 80% 80%, rgba(139, 92, 246, 0.03), transparent 40%);
    pointer-events: none;
    z-index: 0;
  }
}

.main-container {
  min-height: 100vh;
  padding-bottom: 32px;
  position: relative;
  z-index: 1;
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

// ========================================
// LOGOUT DIALOG STYLES (matching login theme)
// ========================================

.logout-dialog-card {
  border-radius: 20px !important;
  overflow: hidden;
  background: rgba(255, 255, 255, 0.98) !important;
  box-shadow: 0 40px 80px -20px rgba(0, 0, 0, 0.4),
    0 20px 40px -20px rgba(255, 111, 0, 0.15),
    0 0 0 1px rgba(255, 152, 0, 0.1) inset !important;
  -webkit-backdrop-filter: blur(20px);
  backdrop-filter: blur(20px);
  animation: dialogFadeIn 0.3s ease-out;
}

@keyframes dialogFadeIn {
  from {
    opacity: 0;
    transform: scale(0.95) translateY(20px);
  }
  to {
    opacity: 1;
    transform: scale(1) translateY(0);
  }
}

.logout-dialog-header {
  padding: 2.5rem 2rem 2rem;
  background: linear-gradient(135deg, #37474f 0%, #ff6f00 100%);
  position: relative;
  text-align: center;

  &::after {
    content: "";
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, transparent, #ff9800, transparent);
  }
}

.logout-icon-circle {
  width: 80px;
  height: 80px;
  margin: 0 auto 1.25rem;
  background: rgba(255, 255, 255, 0.15);
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  -webkit-backdrop-filter: blur(10px);
  backdrop-filter: blur(10px);
  border: 2px solid rgba(255, 255, 255, 0.3);
  box-shadow: 0 8px 20px rgba(0, 0, 0, 0.2);
  animation: pulseIcon 2s ease-in-out infinite;
}

@keyframes pulseIcon {
  0%, 100% {
    transform: scale(1);
  }
  50% {
    transform: scale(1.05);
  }
}

.logout-dialog-title {
  font-size: 1.75rem;
  font-weight: 700;
  color: white;
  margin: 0 0 0.5rem;
  text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
  letter-spacing: -0.5px;
}

.logout-dialog-subtitle {
  font-size: 0.9rem;
  color: rgba(255, 255, 255, 0.9);
  margin: 0;
  font-weight: 400;
  text-shadow: 0 1px 3px rgba(0, 0, 0, 0.2);
}

.logout-dialog-content {
  background: #ffffff;
}

.logout-warning-banner {
  display: flex;
  align-items: flex-start;
  padding: 1.5rem;
  background: rgba(255, 111, 0, 0.08);
  border-radius: 12px;
  border: 1px solid rgba(255, 111, 0, 0.2);
  box-shadow: 0 2px 8px rgba(255, 111, 0, 0.08);
  margin-bottom: 1rem;
}

.logout-message-title {
  font-size: 1.05rem;
  font-weight: 600;
  color: #37474f;
  margin-bottom: 0.25rem;
}

.logout-message-subtitle {
  font-size: 0.875rem;
  color: #546e7a;
  line-height: 1.5;
}

.logout-info-note {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.875rem 1rem;
  background: rgba(84, 110, 122, 0.06);
  border-radius: 10px;
  font-size: 0.8125rem;
  color: #546e7a;
  font-weight: 500;
  border: 1px solid rgba(84, 110, 122, 0.1);
}

.logout-dialog-actions {
  display: flex;
  gap: 1rem;
  background: #fafafa;
  border-top: 1px solid rgba(0, 0, 0, 0.06);
}

.logout-cancel-btn {
  height: 48px !important;
  border-radius: 12px !important;
  font-weight: 600 !important;
  border: 2px solid rgba(84, 110, 122, 0.3) !important;
  color: #546e7a !important;
  transition: all 0.3s ease !important;

  &:hover {
    border-color: #546e7a !important;
    background: rgba(84, 110, 122, 0.08) !important;
    transform: translateY(-2px);
  }
}

.logout-confirm-btn {
  height: 48px !important;
  border-radius: 12px !important;
  font-weight: 600 !important;
  background: linear-gradient(135deg, #ff6f00 0%, #ff9800 100%) !important;
  color: white !important;
  box-shadow: 0 8px 20px rgba(255, 111, 0, 0.3),
    0 4px 10px rgba(255, 111, 0, 0.2) !important;
  transition: all 0.3s ease !important;

  &:hover {
    box-shadow: 0 12px 28px rgba(255, 111, 0, 0.4),
      0 6px 14px rgba(255, 111, 0, 0.3) !important;
    transform: translateY(-2px);
  }

  &:active {
    transform: translateY(0);
  }
}</style>