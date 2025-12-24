<template>
  <div>
    <v-row class="mb-4">
      <v-col cols="12" md="6">
        <h1 class="text-h4 font-weight-bold">Payroll</h1>
      </v-col>
      <v-col cols="12" md="6" class="text-right">
        <v-btn color="primary" prepend-icon="mdi-plus" to="/payroll/create">
          New Payroll
        </v-btn>
      </v-col>
    </v-row>

    <v-card>
      <v-card-text class="text-center pa-12">
        <v-icon size="64" color="info">mdi-cash-multiple</v-icon>
        <p class="text-h6 mt-4">Payroll list - Coming soon</p>
        <p class="text-body-2 text-medium-emphasis mt-2">
          Features: Process payroll, construction workflow (check → recommend →
          approve → pay)
        </p>
      </v-card-text>
    </v-card>
  </div>
</template>
