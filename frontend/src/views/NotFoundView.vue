<template>
  <v-container class="fill-height">
    <v-row align="center" justify="center">
      <v-col cols="12" class="text-center">
        <v-icon
          icon="mdi-alert-circle-outline"
          size="120"
          color="error"
        ></v-icon>
        <h1 class="text-h2 font-weight-bold mt-4">404</h1>
        <p class="text-h5 text-medium-emphasis mt-2">Page Not Found</p>
        <p class="text-body-1 mt-4">
          The page you are looking for does not exist or has been moved.
        </p>
        <v-btn
          color="primary"
          size="large"
          prepend-icon="mdi-home"
          class="mt-6"
          to="/"
        >
          Go to Dashboard
        </v-btn>
      </v-col>
    </v-row>
  </v-container>
</template>

<script setup></script>

<style scoped>
.fill-height {
  min-height: 80vh;
}
</style>
