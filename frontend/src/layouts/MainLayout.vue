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
      @click="rail = false"
    >
      <!-- Logo Header -->
      <div class="sidebar-header" v-if="!isMobile" @click.stop="rail = !rail">
        <transition name="logo-fade" mode="out-in">
          <div v-if="!rail" class="sidebar-logo-expanded" key="expanded">
            <div class="logo-icon-wrapper">
              <v-icon icon="mdi-hard-hat" size="24"></v-icon>
            </div>
            <div class="logo-text-wrapper">
              <span class="logo-text">GC Payroll</span>
              <span class="logo-subtext">Management System</span>
            </div>
            <v-icon
              icon="mdi-chevron-left"
              size="20"
              class="collapse-icon"
            ></v-icon>
          </div>
          <div v-else class="sidebar-logo-collapsed" key="collapsed">
            <div class="logo-icon-wrapper-rail">
              <v-icon icon="mdi-hard-hat" size="22"></v-icon>
            </div>
          </div>
        </transition>
      </div>

      <v-divider class="steel-divider mx-3" v-if="!isMobile"></v-divider>

      <!-- Navigation Menu -->
      <v-list density="compact" nav class="pa-2">
        <template v-for="section in menuSections" :key="section.title">
          <!-- Section Header -->
          <v-list-subheader
            v-if="!rail"
            class="menu-section-header text-caption font-weight-bold text-medium-emphasis px-4 py-2 mt-2"
          >
            {{ section.title }}
          </v-list-subheader>
          <v-divider v-else class="my-2 mx-4"></v-divider>

          <!-- Section Items -->
          <template v-for="item in section.items" :key="item.value">
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
                  :to="item.to || item.children?.[0]?.to"
                  color="hardhat"
                  class="menu-item mb-1"
                  rounded="lg"
                ></v-list-item>
              </template>
              <span>{{ item.title }}</span>
            </v-tooltip>
          </template>
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

    <!-- App Bar -->
    <v-app-bar elevation="0" class="construction-appbar" app>
      <!-- Hamburger Menu Button (Mobile Only) -->
      <v-app-bar-nav-icon
        v-if="isMobile"
        @click="drawer = !drawer"
        class="appbar-menu-btn"
      ></v-app-bar-nav-icon>

      <!-- Page Title -->
      <div class="d-flex align-center">
        <div class="appbar-icon-wrapper" v-if="!isMobile">
          <v-icon icon="mdi-hard-hat" size="24"></v-icon>
        </div>
        <div>
          <v-app-bar-title class="appbar-title">
            {{
              isMobile
                ? pageTitle.length > 20
                  ? pageTitle.substring(0, 20) + "..."
                  : pageTitle
                : pageTitle
            }}
          </v-app-bar-title>
          <div v-if="!isMobile" class="appbar-subtitle">
            Giovanni Construction
          </div>
        </div>
      </div>

      <v-spacer></v-spacer>

      <!-- User Info Badge -->
      <div class="appbar-user-badge" v-if="!isMobile">
        <v-avatar size="32">
          <v-img v-if="userAvatar" :src="userAvatar" cover></v-img>
          <v-icon v-else size="20">mdi-account</v-icon>
        </v-avatar>
        <div class="ml-2">
          <div class="appbar-user-name">{{ userName }}</div>
          <div class="appbar-user-role">{{ userRole }}</div>
        </div>
      </div>
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
      <v-card class="modern-dialog-card logout-dialog-card">
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
            <v-icon
              icon="mdi-alert-circle-outline"
              size="24"
              color="#ed985f"
            ></v-icon>
            <div class="ml-3">
              <div class="logout-message-title">
                Are you sure you want to logout?
              </div>
              <div class="logout-message-subtitle">
                You will be signed out from Giovanni Construction
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
  () => authStore.user?.name || authStore.user?.username || "User",
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
      title: "Position Rates",
      icon: "mdi-badge-account",
      value: "position-rates",
      to: "/position-rates",
      roles: ["admin", "accountant"],
    },
    {
      title: "Settings",
      icon: "mdi-cog-outline",
      value: "settings",
      to: "/settings",
      roles: ["admin"],
    },
    {
      title: "Audit Trail",
      icon: "mdi-shield-search",
      value: "audit-trail",
      to: "/audit-trail",
      roles: ["admin"],
    },
  ];

  // Filter items based on user role
  const currentRole = authStore.userRole;
  return allItems.filter(
    (item) => !item.roles || item.roles.includes(currentRole),
  );
});

// Organize menu items into sections with labels
const menuSections = computed(() => {
  const currentRole = authStore.userRole;

  // Define sections based on role
  const sections = [
    {
      title: "MAIN MENU",
      items: [],
    },
    {
      title: "TEAM MANAGEMENT",
      items: [],
    },
    {
      title: "LIST",
      items: [],
    },
  ];

  // Main Menu items (common actions)
  const mainMenuItems = [
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
      title: "Dashboard",
      icon: "mdi-view-dashboard",
      value: "employee-dashboard",
      to: "/employee-dashboard",
      roles: ["employee"],
    },
    {
      title: "Attendance",
      icon: "mdi-clock-check-outline",
      value: "attendance",
      to: "/attendance",
      roles: ["admin", "accountant"],
    },

    {
      title: "Settings",
      icon: "mdi-cog-outline",
      value: "settings",
      to: "/settings",
      roles: ["admin"],
    },
    {
      title: "My Profile",
      icon: "mdi-badge-account-horizontal-outline",
      value: "profile",
      to: "/profile",
      roles: ["admin", "accountant", "employee"],
    },
  ];

  // Team Management items
  const teamManagementItems = [
    {
      title: "Employees",
      icon: "mdi-hard-hat",
      value: "employees",
      to: "/employees",
      roles: ["admin"],
    },
    {
      title: "Payrolls",
      icon: "mdi-cash-multiple",
      value: "payroll",
      to: "/payroll",
      roles: ["admin", "accountant"],
    },
    {
      title: "Position Rates",
      icon: "mdi-badge-account",
      value: "position-rates",
      to: "/position-rates",
      roles: ["admin", "accountant"],
    },
    {
      title: "Projects",
      icon: "mdi-office-building",
      value: "projects",
      to: "/projects",
      roles: ["admin"],
    },
    {
      title: "Hiring",
      icon: "mdi-account-plus-outline",
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
      title: "My Resumes",
      icon: "mdi-file-document-outline",
      value: "resumes",
      to: "/resumes",
      roles: ["accountant"],
    },
  ];

  // List items (reports and data views)
  const listItems = [
    {
      title: "Reports",
      icon: "mdi-file-chart-outline",
      value: "reports",
      to: "/reports",
      roles: ["admin", "accountant"],
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
      title: "Biometric Import",
      icon: "mdi-file-upload-outline",
      value: "biometric-import",
      to: "/biometric-import",
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
  ];

  // Filter and assign items to sections
  sections[0].items = mainMenuItems.filter(
    (item) => !item.roles || item.roles.includes(currentRole),
  );

  sections[1].items = teamManagementItems.filter(
    (item) => !item.roles || item.roles.includes(currentRole),
  );

  sections[2].items = listItems.filter(
    (item) => !item.roles || item.roles.includes(currentRole),
  );

  // Remove empty sections
  return sections.filter((section) => section.items.length > 0);
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
// Professional Navigation Drawer - Clean Design
.construction-drawer {
  background: #001f3d !important;
  position: fixed !important;
  top: 0 !important;
  bottom: 0 !important;
  height: 100vh !important;
  overflow-y: auto !important;
  display: flex !important;
  flex-direction: column !important;
  border-right: 1px solid rgba(230, 230, 230, 0.1);
  box-shadow: 2px 0 12px rgba(0, 0, 0, 0.15);

  :deep(.v-navigation-drawer__content) {
    height: 100% !important;
    display: flex !important;
    flex-direction: column !important;
    overflow-y: auto !important;
    background: transparent;

    /* Hide scrollbar but keep scrolling functionality */
    scrollbar-width: none;
    -ms-overflow-style: none;

    &::-webkit-scrollbar {
      display: none;
    }
  }

  :deep(.v-list) {
    flex: 1 !important;
    overflow-y: auto !important;
    background: transparent !important;
    padding: 8px !important;

    scrollbar-width: none;
    -ms-overflow-style: none;

    &::-webkit-scrollbar {
      display: none;
    }
  }

  :deep(.v-list-item) {
    color: #e6e6e6 !important;
    margin: 4px 8px !important;
    border-radius: 8px !important;
    min-height: 44px !important;
    transition: all 0.2s ease !important;
    background: transparent !important;

    .v-list-item-title {
      color: #e6e6e6 !important;
      font-weight: 500;
      font-size: 0.9rem;
      letter-spacing: 0.2px;
    }

    .v-icon {
      color: #e6e6e6 !important;
      opacity: 0.8;
      font-size: 20px !important;
      transition: all 0.2s ease;
    }

    &:hover {
      background: rgba(237, 152, 95, 0.12) !important;

      .v-list-item-title {
        color: #f7b980 !important;
      }

      .v-icon {
        color: #f7b980 !important;
        opacity: 1;
      }
    }
  }

  :deep(.v-list-item--active) {
    background: #ed985f !important;

    .v-list-item-title {
      color: #001f3d !important;
      font-weight: 600;
    }

    .v-icon {
      color: #001f3d !important;
      opacity: 1;
    }

    &:hover {
      background: #f7b980 !important;

      .v-list-item-title {
        color: #001f3d !important;
      }

      .v-icon {
        color: #001f3d !important;
      }
    }
  }

  // List group styling for submenus
  :deep(.v-list-group) {
    .v-list-item {
      color: #e6e6e6 !important;
    }

    .v-list-group__items {
      .v-list-item {
        padding-left: 48px;

        &::before {
          content: "";
          position: absolute;
          left: 28px;
          top: 50%;
          width: 6px;
          height: 6px;
          border-radius: 50%;
          background: rgba(230, 230, 230, 0.3);
          transform: translateY(-50%);
        }

        &.v-list-item--active::before {
          background: #001f3d;
        }
      }
    }
  }

  // Rail mode specific styling
  &.v-navigation-drawer--rail {
    :deep(.v-list-item) {
      padding: 0 !important;
      padding-inline-start: 0 !important;
      padding-inline-end: 0 !important;
      margin: 4px auto !important;
      margin-inline-start: auto !important;
      margin-inline-end: auto !important;
      min-height: 44px !important;
      height: 44px !important;
      width: 44px !important;
      min-width: 44px !important;
      max-width: 44px !important;
      border-radius: 8px !important;
      display: flex !important;
      align-items: center !important;
      justify-content: center !important;
      flex-shrink: 0 !important;
      position: relative !important;

      .v-list-item__overlay {
        border-radius: 8px !important;
      }

      .v-list-item__prepend {
        margin: 0 !important;
        margin-inline-start: 0 !important;
        margin-inline-end: 0 !important;
        padding: 0 !important;
        padding-inline-start: 0 !important;
        padding-inline-end: 0 !important;
        width: 100% !important;
        height: 100% !important;
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        opacity: 1 !important;
        position: absolute !important;
        left: 0 !important;
        right: 0 !important;
        top: 0 !important;
        bottom: 0 !important;

        .v-icon {
          font-size: 20px !important;
          margin: 0 !important;
          margin-inline-start: 0 !important;
          margin-inline-end: 0 !important;
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
        background: rgba(237, 152, 95, 0.15) !important;
      }
    }

    :deep(.v-list-item--active) {
      background: #ed985f !important;

      .v-icon {
        color: #001f3d !important;
      }
    }

    :deep(.v-list-group) {
      display: none;
    }

    .steel-divider {
      margin: 8px 16px !important;
    }
  }
}

// Sidebar Header
.sidebar-header {
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 16px 12px;
  min-height: 72px;
  cursor: pointer;
  transition: all 0.2s ease;

  &:hover {
    background: rgba(237, 152, 95, 0.08);
  }
}

.sidebar-logo-expanded {
  display: flex;
  align-items: center;
  justify-content: space-between;
  width: 100%;
  gap: 12px;
}

.logo-icon-wrapper {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);

  .v-icon {
    color: #001f3d !important;
  }
}

.logo-text-wrapper {
  display: flex;
  flex-direction: column;
  flex: 1;
  min-width: 0;
}

.logo-text {
  font-size: 1.15rem;
  font-weight: 700;
  letter-spacing: -0.3px;
  color: #fff;
  line-height: 1.2;
}

.logo-subtext {
  font-size: 0.65rem;
  color: #f7b980;
  font-weight: 500;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  line-height: 1.2;
}

.collapse-icon {
  color: #e6e6e6 !important;
  opacity: 0.6;
  transition: all 0.2s ease;
  flex-shrink: 0;

  .sidebar-header:hover & {
    opacity: 1;
    color: #f7b980 !important;
  }
}

.sidebar-logo-collapsed {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 100%;
}

.logo-icon-wrapper-rail {
  width: 44px;
  height: 44px;
  border-radius: 10px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);
  transition: all 0.2s ease;

  .v-icon {
    color: #001f3d !important;
  }

  .sidebar-header:hover & {
    transform: scale(1.05);
    box-shadow: 0 6px 16px rgba(237, 152, 95, 0.4);
  }
}

// Logo transition
.logo-fade-enter-active,
.logo-fade-leave-active {
  transition: opacity 0.15s ease;
}

.logo-fade-enter-from,
.logo-fade-leave-to {
  opacity: 0;
}

// Menu item styling
.menu-item {
  :deep(.v-list-item__prepend) {
    .v-icon {
      font-size: 20px;
      color: #e6e6e6 !important;
      opacity: 0.8;
    }
  }

  :deep(.v-list-item-title) {
    color: #e6e6e6 !important;
    font-weight: 500;
  }

  &:hover {
    :deep(.v-list-item__prepend) {
      .v-icon {
        color: #f7b980 !important;
        opacity: 1;
      }
    }

    :deep(.v-list-item-title) {
      color: #f7b980 !important;
    }
  }
}

// Clean divider
:deep(.steel-divider) {
  background: rgba(230, 230, 230, 0.15) !important;
  height: 1px !important;
  margin: 12px 16px !important;
  border-radius: 0 !important;
  box-shadow: none !important;
}

// Menu section headers
.menu-section-header {
  color: #f7b980 !important;
  font-size: 0.7rem !important;
  letter-spacing: 1px !important;
  text-transform: uppercase !important;
  font-weight: 600 !important;
  margin-top: 20px !important;
  margin-bottom: 8px !important;
  padding-left: 20px !important;
  padding-right: 16px !important;
  user-select: none;

  &:first-of-type {
    margin-top: 4px !important;
  }
}

// Logout button - clean style
.logout-btn,
.logout-btn-rail {
  background: rgba(239, 68, 68, 0.1) !important;
  color: #ef4444 !important;
  font-weight: 600;
  border: 1px solid rgba(239, 68, 68, 0.2) !important;
  transition: all 0.2s ease;

  &:hover {
    background: rgba(239, 68, 68, 0.2) !important;
    border-color: rgba(239, 68, 68, 0.4) !important;
  }

  .v-icon {
    color: #ef4444 !important;
    font-size: 20px !important;
  }
}

.logout-btn-rail {
  width: 44px !important;
  height: 44px !important;
  min-width: 44px !important;
  border-radius: 8px !important;
}

// Breadcrumbs - clean style
.breadcrumbs-construction {
  :deep(.v-breadcrumbs-item) {
    color: #64748b;
    font-weight: 500;
    padding: 4px 8px;
    border-radius: 4px;

    &:hover {
      color: #ed985f;
      background: rgba(237, 152, 95, 0.08);
    }
  }

  :deep(.v-breadcrumbs-item--disabled) {
    color: #001f3d;
    font-weight: 600;
    background: rgba(237, 152, 95, 0.1);
    padding: 4px 8px;
    border-radius: 4px;
  }

  :deep(.v-breadcrumbs-divider) {
    color: #cbd5e1;
  }
}

// Clean App Bar styling
.construction-appbar {
  background: #fff !important;
  border-bottom: 1px solid rgba(0, 31, 61, 0.08) !important;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04) !important;
  padding: 0 16px !important;
}

.appbar-menu-btn {
  color: #001f3d !important;
}

.appbar-icon-wrapper {
  width: 40px;
  height: 40px;
  border-radius: 8px;
  background: rgba(237, 152, 95, 0.1);
  display: flex;
  align-items: center;
  justify-content: center;
  margin-right: 12px;

  .v-icon {
    color: #ed985f !important;
  }
}

.appbar-title {
  font-size: 1.1rem !important;
  font-weight: 600 !important;
  color: #001f3d !important;
  letter-spacing: -0.2px;
}

.appbar-subtitle {
  font-size: 0.75rem;
  color: #64748b;
  font-weight: 500;
  margin-top: -2px;
}

.appbar-user-badge {
  display: flex;
  align-items: center;
  padding: 6px 12px;
  border-radius: 8px;
  background: rgba(0, 31, 61, 0.04);
  border: 1px solid rgba(0, 31, 61, 0.08);

  .v-avatar {
    border: 2px solid #ed985f;
    background: rgba(237, 152, 95, 0.1);

    .v-icon {
      color: #ed985f !important;
    }
  }
}

.appbar-user-name {
  font-size: 0.85rem;
  font-weight: 600;
  color: #001f3d;
  line-height: 1.2;
}

.appbar-user-role {
  font-size: 0.7rem;
  color: #ed985f;
  text-transform: uppercase;
  letter-spacing: 0.5px;
  font-weight: 600;
  line-height: 1.2;
}

.construction-chip {
  font-weight: 600;
  font-size: 12px;
  letter-spacing: 0.3px;
  background: rgba(237, 152, 95, 0.1) !important;
  border: 1px solid rgba(237, 152, 95, 0.2) !important;
  color: #001f3d !important;

  &:hover {
    background: rgba(237, 152, 95, 0.15) !important;
  }
}

// Clean main content area
.main-content {
  background: #f5f5f5;
  min-height: 100vh;
  overflow-y: auto;
}

.main-container {
  min-height: 100vh;
  padding-bottom: 32px;
}

// Page transitions
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

// ========================================
// LOGOUT DIALOG STYLES (matching login theme)
// ========================================

.logout-dialog-card {
  border-radius: 20px !important;
  overflow: hidden;
  background: rgba(255, 255, 255, 0.98) !important;
  box-shadow:
    0 40px 80px -20px rgba(0, 0, 0, 0.4),
    0 20px 40px -20px rgba(237, 152, 95, 0.15),
    0 0 0 1px rgba(247, 185, 128, 0.1) inset !important;
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
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  position: relative;
  text-align: center;

  &::after {
    content: "";
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, transparent, #f7b980, transparent);
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
  0%,
  100% {
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
  background: rgba(237, 152, 95, 0.08);
  border-radius: 12px;
  border: 1px solid rgba(237, 152, 95, 0.2);
  box-shadow: 0 2px 8px rgba(237, 152, 95, 0.08);
  margin-bottom: 1rem;
}

.logout-message-title {
  font-size: 1.05rem;
  font-weight: 600;
  color: #001f3d;
  margin-bottom: 0.25rem;
}

.logout-message-subtitle {
  font-size: 0.875rem;
  color: rgba(0, 31, 61, 0.7);
  line-height: 1.5;
}

.logout-info-note {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  padding: 0.875rem 1rem;
  background: rgba(0, 31, 61, 0.04);
  border-radius: 10px;
  font-size: 0.8125rem;
  color: rgba(0, 31, 61, 0.7);
  font-weight: 500;
  border: 1px solid rgba(0, 31, 61, 0.08);
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
  border: 2px solid rgba(0, 31, 61, 0.2) !important;
  color: #001f3d !important;
  transition: all 0.3s ease !important;

  &:hover {
    border-color: #001f3d !important;
    background: rgba(0, 31, 61, 0.04) !important;
    transform: translateY(-2px);
  }
}

.logout-confirm-btn {
  height: 48px !important;
  border-radius: 12px !important;
  font-weight: 600 !important;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%) !important;
  color: white !important;
  box-shadow:
    0 8px 20px rgba(237, 152, 95, 0.3),
    0 4px 10px rgba(237, 152, 95, 0.2) !important;
  transition: all 0.3s ease !important;

  &:hover {
    box-shadow:
      0 12px 28px rgba(237, 152, 95, 0.4),
      0 6px 14px rgba(237, 152, 95, 0.3) !important;
    transform: translateY(-2px);
  }

  &:active {
    transform: translateY(0);
  }
}
</style>
