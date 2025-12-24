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

const menuItems = [
  {
    title: "Dashboard",
    icon: "mdi-view-dashboard",
    value: "dashboard",
    to: "/",
  },
  {
    title: "Employees",
    icon: "mdi-account-group",
    value: "employees",
    to: "/employees",
  },
  {
    title: "Attendance",
    icon: "mdi-calendar-clock",
    value: "attendance",
    to: "/attendance",
  },
  {
    title: "Payroll",
    icon: "mdi-cash-multiple",
    value: "payroll",
    to: "/payroll",
  },
  {
    title: "Allowances",
    icon: "mdi-wallet-plus",
    value: "allowances",
    to: "/allowances",
  },
  { title: "Loans", icon: "mdi-hand-coin", value: "loans", to: "/loans" },
  {
    title: "Deductions",
    icon: "mdi-wallet-minus",
    value: "deductions",
    to: "/deductions",
  },
  { title: "Reports", icon: "mdi-chart-bar", value: "reports", to: "/reports" },
  { title: "Settings", icon: "mdi-cog", value: "settings", to: "/settings" },
];

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
