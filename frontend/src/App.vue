<template>
  <v-app>
    <router-view />
  </v-app>
</template>

<script setup>
import { onMounted } from "vue";
import { useAuthStore } from "@/stores/auth";
import { useSyncStore } from "@/stores/sync";

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
@import "@/styles/main.scss";
</style>
