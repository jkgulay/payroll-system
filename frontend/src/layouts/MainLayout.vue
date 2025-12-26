<template>
  <v-app>
    <v-navigation-drawer
      v-model="drawer"
      :rail="rail"
      permanent
      @click="rail = false"
    >
      <v-list-item
        :prepend-avatar="userAvatar"
        :title="userName"
        :subtitle="userRole"
        nav
      >
        <template v-slot:append>
          <v-btn
            variant="text"
            :icon="rail ? 'mdi-chevron-right' : 'mdi-chevron-left'"
            @click.stop="rail = !rail"
          ></v-btn>
        </template>
      </v-list-item>

      <v-divider></v-divider>

      <v-list density="compact" nav>
        <v-list-item
          v-for="item in menuItems"
          :key="item.value"
          :prepend-icon="item.icon"
          :title="item.title"
          :value="item.value"
          :to="item.to"
          color="primary"
        ></v-list-item>
      </v-list>

      <template v-slot:append>
        <div class="pa-2">
          <v-btn block prepend-icon="mdi-logout" @click="handleLogout">
            Logout
          </v-btn>
        </div>
      </template>
    </v-navigation-drawer>

    <v-app-bar elevation="1">
      <v-app-bar-title>
        <v-icon icon="mdi-hard-hat" class="mr-2"></v-icon>
        {{ pageTitle }}
      </v-app-bar-title>

      <v-spacer></v-spacer>

      <!-- Sync status indicator -->
      <v-chip
        v-if="syncStore.pendingChanges > 0"
        color="info"
        size="small"
        class="mr-2"
      >
        <v-icon start>mdi-sync</v-icon>
        {{ syncStore.pendingChanges }} pending
      </v-chip>

      <!-- Online/Offline indicator -->
      <v-chip
        :color="syncStore.isOnline ? 'success' : 'error'"
        size="small"
        variant="flat"
      >
        <v-icon start>{{
          syncStore.isOnline ? "mdi-wifi" : "mdi-wifi-off"
        }}</v-icon>
        {{ syncStore.isOnline ? "Online" : "Offline" }}
      </v-chip>

      <v-btn icon="mdi-bell" class="ml-2"></v-btn>
    </v-app-bar>

    <v-main>
      <v-container fluid>
        <router-view v-slot="{ Component }">
          <transition name="fade" mode="out-in">
            <component :is="Component" />
          </transition>
        </router-view>
      </v-container>
    </v-main>
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

const userName = computed(() => authStore.user?.name || "User");
const userRole = computed(() => authStore.user?.role || "Employee");
const userAvatar = computed(
  () => authStore.user?.avatar || "/avatar-placeholder.png"
);
const pageTitle = computed(() => route.meta.title || "Dashboard");

// Filter menu items based on user role
const menuItems = computed(() => {
  const allItems = [
    {
      title: "Dashboard",
      icon: "mdi-view-dashboard",
      value: "admin-dashboard",
      to: "/admin-dashboard",
      roles: ["admin", "accountant"],
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
      icon: "mdi-account-group",
      value: "employees",
      to: "/employees",
      roles: ["admin", "accountant"],
    },
    {
      title: "Attendance",
      icon: "mdi-calendar-clock",
      value: "attendance",
      to: "/attendance",
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
      title: "Allowances",
      icon: "mdi-wallet-plus",
      value: "allowances",
      to: "/allowances",
      roles: ["admin", "accountant"],
    },
    {
      title: "Loans",
      icon: "mdi-hand-coin",
      value: "loans",
      to: "/loans",
      roles: ["admin", "accountant"],
    },
    {
      title: "Deductions",
      icon: "mdi-wallet",
      value: "deductions",
      to: "/deductions",
      roles: ["admin", "accountant"],
    },
    {
      title: "Reports",
      icon: "mdi-chart-bar",
      value: "reports",
      to: "/reports",
      roles: ["admin", "accountant"],
    },
    {
      title: "Settings",
      icon: "mdi-cog",
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
  try {
    await authStore.logout();
    toast.success("Logged out successfully");
    router.push("/login");
  } catch (error) {
    console.error("Logout error:", error);
    toast.error("Error logging out");
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

<style scoped>
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.2s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}
</style>
