<template>
  <div class="settings-page">
    <!-- Modern Page Header -->
    <div class="page-header">
      <div class="page-title-section">
        <div class="page-icon-badge">
          <v-icon size="20">mdi-cog</v-icon>
        </div>
        <div>
          <h1 class="page-title">Settings</h1>
          <p class="page-subtitle">
            Configure system preferences and manage application settings
          </p>
        </div>
      </div>
    </div>

    <!-- Settings Cards Grid -->
    <div class="settings-grid">
      <!-- Company Information -->
      <div class="setting-card" @click="$router.push('/company-info')">
        <div class="setting-icon-wrapper company">
          <v-icon size="32">mdi-office-building</v-icon>
        </div>
        <div class="setting-content">
          <h3 class="setting-title">Company Information</h3>
          <p class="setting-description">
            Configure company details, logo, and contact information for reports
            and documents
          </p>
          <button class="setting-action-btn">
            <span>Edit Company Info</span>
            <v-icon size="18">mdi-arrow-right</v-icon>
          </button>
        </div>
      </div>

      <!-- Government Rates -->
      <div class="setting-card" @click="$router.push('/government-rates')">
        <div class="setting-icon-wrapper rates">
          <v-icon size="32">mdi-bank</v-icon>
        </div>
        <div class="setting-content">
          <h3 class="setting-title">Government Rates</h3>
          <p class="setting-description">
            Update SSS, PhilHealth, Pag-IBIG contribution tables and withholding
            tax rates
          </p>
          <button class="setting-action-btn">
            <span>Manage Rates</span>
            <v-icon size="18">mdi-arrow-right</v-icon>
          </button>
        </div>
      </div>

      <!-- User Management -->
      <div class="setting-card" @click="$router.push('/user-management')">
        <div class="setting-icon-wrapper users">
          <v-icon size="32">mdi-account-multiple</v-icon>
        </div>
        <div class="setting-content">
          <h3 class="setting-title">User Management</h3>
          <p class="setting-description">
            Manage system users, roles, and access permissions across the
            application
          </p>
          <button
            class="setting-action-btn"
            @click.stop="$router.push('/user-management')"
          >
            <span>Manage Users</span>
            <v-icon size="18">mdi-arrow-right</v-icon>
          </button>
        </div>
      </div>

      <!-- Payroll Settings -->
      <div class="setting-card" @click="openPayrollDialog">
        <div class="setting-icon-wrapper payroll">
          <v-icon size="32">mdi-currency-usd</v-icon>
        </div>
        <div class="setting-content">
          <h3 class="setting-title">Payroll Configuration</h3>
          <p class="setting-description">
            Configure overtime rates, and payroll calculation settings
          </p>
          <button class="setting-action-btn">
            <span>Configure Payroll</span>
            <v-icon size="18">mdi-arrow-right</v-icon>
          </button>
        </div>
      </div>

      <!-- Attendance Settings -->
      <div class="setting-card" @click="$router.push('/attendance-settings')">
        <div class="setting-icon-wrapper attendance">
          <v-icon size="32">mdi-clock-check</v-icon>
        </div>
        <div class="setting-content">
          <h3 class="setting-title">Attendance Settings</h3>
          <p class="setting-description">
            Configure work schedules, shift timings, and attendance tracking
            rules
          </p>
          <button class="setting-action-btn">
            <span>Manage Attendance</span>
            <v-icon size="18">mdi-arrow-right</v-icon>
          </button>
        </div>
      </div>

      <!-- Backup & Security -->
      <div class="setting-card" @click="openSecurityDialog">
        <div class="setting-icon-wrapper security">
          <v-icon size="32">mdi-shield-check</v-icon>
        </div>
        <div class="setting-content">
          <h3 class="setting-title">Backup & Security</h3>
          <p class="setting-description">
            Configure automatic backups, security settings, and data retention
            policies
          </p>
          <button class="setting-action-btn">
            <span>Security Settings</span>
            <v-icon size="18">mdi-arrow-right</v-icon>
          </button>
        </div>
      </div>

      <!-- Database Maintenance -->
      <div class="setting-card" @click="goToMaintenance">
        <div class="setting-icon-wrapper maintenance">
          <v-icon size="32">mdi-database-cog</v-icon>
        </div>
        <div class="setting-content">
          <h3 class="setting-title">Database Maintenance</h3>
          <p class="setting-description">
            Fix database sequences, check health status, and perform maintenance
            tasks
          </p>
          <button class="setting-action-btn">
            <span>Open Maintenance</span>
            <v-icon size="18">mdi-arrow-right</v-icon>
          </button>
        </div>
      </div>
    </div>

    <!-- Payroll Configuration Dialog -->
    <PayrollConfigurationDialog v-model="showPayrollDialog" />

    <!-- Backup & Security Dialog -->
    <BackupSecurityDialog v-model="showSecurityDialog" />
  </div>
</template>

<script setup>
import { ref } from "vue";
import { useRouter } from "vue-router";
import PayrollConfigurationDialog from "@/components/settings/PayrollConfigurationDialog.vue";
import BackupSecurityDialog from "@/components/settings/BackupSecurityDialog.vue";

const router = useRouter();
const showPayrollDialog = ref(false);
const showSecurityDialog = ref(false);

const openPayrollDialog = () => {
  showPayrollDialog.value = true;
};

const openSecurityDialog = () => {
  showSecurityDialog.value = true;
};

const goToMaintenance = () => {
  router.push({ name: "maintenance" });
};
</script>

<style scoped lang="scss">
.settings-page {
  max-width: 1400px;
  margin: 0 auto;
}

// Modern Page Header
.page-header {
  margin-bottom: 32px;
}

.page-title-section {
  display: flex;
  align-items: center;
  gap: 16px;
}

.page-icon-badge {
  width: 56px;
  height: 56px;
  border-radius: 14px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);
  flex-shrink: 0;

  .v-icon {
    color: #ffffff !important;
  }
}

.page-title {
  font-size: 28px;
  font-weight: 700;
  color: #001f3d;
  margin: 0 0 4px 0;
  letter-spacing: -0.5px;
}

.page-subtitle {
  font-size: 14px;
  color: rgba(0, 31, 61, 0.6);
  margin: 0;
}

// Settings Grid
.settings-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
  gap: 24px;

  @media (max-width: 960px) {
    grid-template-columns: 1fr;
  }
}

// Setting Card
.setting-card {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  padding: 28px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  cursor: pointer;
  display: flex;
  flex-direction: column;
  gap: 20px;

  &:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 24px rgba(237, 152, 95, 0.15);
    border-color: rgba(237, 152, 95, 0.3);

    .setting-icon-wrapper {
      transform: scale(1.05);
    }

    .setting-action-btn {
      background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
      color: #ffffff;

      .v-icon {
        color: #ffffff !important;
        transform: translateX(4px);
      }
    }
  }
}

.setting-icon-wrapper {
  width: 70px;
  height: 70px;
  border-radius: 16px;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;

  &.company {
    background: linear-gradient(
      135deg,
      rgba(237, 152, 95, 0.15) 0%,
      rgba(247, 185, 128, 0.1) 100%
    );

    .v-icon {
      color: #ed985f !important;
    }
  }

  &.rates {
    background: linear-gradient(
      135deg,
      rgba(247, 185, 128, 0.15) 0%,
      rgba(237, 152, 95, 0.1) 100%
    );

    .v-icon {
      color: #f7b980 !important;
    }
  }

  &.users {
    background: linear-gradient(
      135deg,
      rgba(237, 152, 95, 0.12) 0%,
      rgba(247, 185, 128, 0.08) 100%
    );

    .v-icon {
      color: #ed985f !important;
    }
  }

  &.payroll {
    background: linear-gradient(
      135deg,
      rgba(247, 185, 128, 0.12) 0%,
      rgba(237, 152, 95, 0.1) 100%
    );

    .v-icon {
      color: #f7b980 !important;
    }
  }

  &.attendance {
    background: linear-gradient(
      135deg,
      rgba(237, 152, 95, 0.1) 0%,
      rgba(247, 185, 128, 0.12) 100%
    );

    .v-icon {
      color: #ed985f !important;
    }
  }

  &.security {
    background: linear-gradient(
      135deg,
      rgba(247, 185, 128, 0.1) 0%,
      rgba(237, 152, 95, 0.15) 100%
    );

    .v-icon {
      color: #f7b980 !important;
    }
  }

  &.maintenance {
    background: linear-gradient(
      135deg,
      rgba(237, 152, 95, 0.15) 0%,
      rgba(247, 185, 128, 0.12) 100%
    );

    .v-icon {
      color: #ed985f !important;
    }
  }
}

.setting-content {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.setting-title {
  font-size: 20px;
  font-weight: 700;
  color: #001f3d;
  margin: 0;
  letter-spacing: -0.3px;
}

.setting-description {
  font-size: 14px;
  color: rgba(0, 31, 61, 0.6);
  line-height: 1.6;
  margin: 0;
  flex: 1;
}

.setting-action-btn {
  display: inline-flex;
  align-items: center;
  justify-content: space-between;
  gap: 8px;
  padding: 12px 18px;
  background: rgba(237, 152, 95, 0.1);
  border: 1px solid rgba(237, 152, 95, 0.2);
  border-radius: 10px;
  color: #ed985f;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  width: 100%;

  .v-icon {
    color: #ed985f !important;
    transition: all 0.3s ease;
  }
}
</style>
