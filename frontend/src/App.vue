<template>
  <v-app>
    <router-view />
    <!-- AI ChatBot Component -->
    <ChatBot v-if="authStore.isAuthenticated" />
  </v-app>
</template>

<script setup>
import { onMounted } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useSyncStore } from "@/stores/sync";
import ChatBot from "@/components/ChatBot.vue";

const authStore = useAuthStore();
const syncStore = useSyncStore();

onMounted(async () => {
  // Initialize auth from localStorage
  await authStore.checkAuth();

  // Start sync service if online
  if (navigator.onLine) {
    syncStore.startSync();
  }

  // Listen for online/offline events
  window.addEventListener("online", () => {
    syncStore.startSync();
  });

  window.addEventListener("offline", () => {
    syncStore.stopSync();
  });
});
</script>

<style lang="scss">
@use "@/styles/main.scss";
</style>
