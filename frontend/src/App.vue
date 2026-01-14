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
import ChatBot from "@/components/ChatBot.vue";

const authStore = useAuthStore();

onMounted(async () => {
  // Initialize auth from localStorage
  await authStore.checkAuth();
});
</script>

<style lang="scss">
@use "@/styles/main.scss";
</style>
