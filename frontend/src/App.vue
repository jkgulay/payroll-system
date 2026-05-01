<template>
  <v-app>
    <router-view />
    <!-- AI ChatBot Component -->
    <ChatBot v-if="showChatBot" />
    <!-- Global Confirm Dialog -->
    <ConfirmDialog />
  </v-app>
</template>

<script setup>
import { onMounted, computed } from "vue";
import { useRoute } from "vue-router";
import { useAuthStore } from "@/stores/auth";
import ChatBot from "@/components/ChatBot.vue";
import ConfirmDialog from "@/components/ConfirmDialog.vue";

const authStore = useAuthStore();
const route = useRoute();

const dashboardRouteNames = new Set([
  "admin-dashboard",
  "hr-dashboard",
  "payrollist-dashboard",
  "employee-dashboard",
]);

const showChatBot = computed(
  () => authStore.isAuthenticated && dashboardRouteNames.has(route.name),
);

onMounted(async () => {
  // Initialize auth from localStorage
  await authStore.checkAuth();
});
</script>

<style lang="scss">
@use "@/styles/main.scss";
</style>
