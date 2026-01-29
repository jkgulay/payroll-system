<template>
  <v-dialog v-model="isOpen" max-width="1000" persistent scrollable>
    <v-card class="backup-security-dialog">
      <!-- Header -->
      <v-card-title class="dialog-header">
        <div class="header-content">
          <div class="icon-wrapper">
            <v-icon size="28" color="white">mdi-shield-check</v-icon>
          </div>
          <div>
            <h2 class="dialog-title">Backup & Security</h2>
            <p class="dialog-subtitle">Configure system backups, security settings, and data policies</p>
          </div>
        </div>
        <v-btn icon variant="text" @click="closeDialog" class="close-btn">
          <v-icon>mdi-close</v-icon>
        </v-btn>
      </v-card-title>

      <v-divider />

      <!-- Content -->
      <v-card-text class="dialog-content">
        <v-tabs v-model="activeTab" class="config-tabs">
          <v-tab value="backup">
            <v-icon start>mdi-backup-restore</v-icon>
            Database Backup
          </v-tab>
          <v-tab value="security">
            <v-icon start>mdi-lock-outline</v-icon>
            Security Settings
          </v-tab>
          <v-tab value="session">
            <v-icon start>mdi-account-clock</v-icon>
            Session Management
          </v-tab>
          <v-tab value="audit">
            <v-icon start>mdi-text-box-search</v-icon>
            Audit Logs
          </v-tab>
        </v-tabs>

        <v-window v-model="activeTab" class="config-window">
          <!-- Database Backup Tab -->
          <v-window-item value="backup">
            <div class="config-section">
              <div class="section-header">
                <h3 class="section-title">Database Backup Configuration</h3>
                <p class="section-description">
                  Configure automatic backups and data retention policies
                </p>
              </div>

              <!-- Automatic Backup -->
              <div class="setting-group">
                <div class="group-header">
                  <h4 class="group-title">Automatic Backup</h4>
                  <v-switch
                    v-model="config.backup.autoBackupEnabled"
                    color="primary"
                    hide-details
                    density="compact"
                  />
                </div>

                <div v-if="config.backup.autoBackupEnabled" class="group-content">
                  <div class="setting-row">
                    <div class="setting-label">
                      <v-icon size="20" color="primary">mdi-clock-outline</v-icon>
                      <span>Backup Frequency</span>
                    </div>
                    <v-select
                      v-model="config.backup.frequency"
                      :items="backupFrequencies"
                      variant="outlined"
                      density="compact"
                      hide-details
                      class="setting-select"
                    />
                  </div>

                  <div class="setting-row">
                    <div class="setting-label">
                      <v-icon size="20" color="success">mdi-clock-time-eight</v-icon>
                      <span>Backup Time</span>
                    </div>
                    <v-text-field
                      v-model="config.backup.backupTime"
                      type="time"
                      variant="outlined"
                      density="compact"
                      hide-details
                      class="setting-input"
                    />
                  </div>

                  <div class="setting-row">
                    <div class="setting-label">
                      <v-icon size="20" color="info">mdi-history</v-icon>
                      <span>Retention Period (days)</span>
                    </div>
                    <v-text-field
                      v-model.number="config.backup.retentionDays"
                      type="number"
                      variant="outlined"
                      density="compact"
                      hide-details
                      suffix="days"
                      class="setting-input"
                    />
                  </div>

                  <div class="info-card">
                    <v-icon color="info" size="18">mdi-information-outline</v-icon>
                    <div>
                      Backups older than {{ config.backup.retentionDays }} days will be automatically deleted. 
                      Recommended minimum: 30 days
                    </div>
                  </div>
                </div>
              </div>

              <!-- Backup Location -->
              <div class="setting-group">
                <div class="group-header">
                  <h4 class="group-title">Backup Storage</h4>
                </div>

                <div class="group-content">
                  <div class="setting-row">
                    <div class="setting-label">
                      <v-icon size="20" color="primary">mdi-folder</v-icon>
                      <span>Storage Location</span>
                    </div>
                    <v-text-field
                      v-model="config.backup.storageLocation"
                      variant="outlined"
                      density="compact"
                      hide-details
                      readonly
                      class="setting-input-large"
                    />
                  </div>

                  <div class="setting-row">
                    <div class="setting-label">
                      <v-icon size="20" color="warning">mdi-database</v-icon>
                      <span>Maximum Backup Size (MB)</span>
                    </div>
                    <v-text-field
                      v-model.number="config.backup.maxBackupSize"
                      type="number"
                      variant="outlined"
                      density="compact"
                      hide-details
                      suffix="MB"
                      class="setting-input"
                    />
                  </div>
                </div>
              </div>

              <!-- Manual Backup -->
              <div class="action-card">
                <div class="action-content">
                  <div class="action-icon">
                    <v-icon size="32" color="primary">mdi-database-export</v-icon>
                  </div>
                  <div>
                    <h4 class="action-title">Create Backup Now</h4>
                    <p class="action-description">Generate an immediate backup of the database</p>
                  </div>
                </div>
                <v-btn
                  color="primary"
                  variant="elevated"
                  @click="createBackup"
                  :loading="backingUp"
                >
                  <v-icon start>mdi-backup-restore</v-icon>
                  Backup Now
                </v-btn>
              </div>
            </div>
          </v-window-item>

          <!-- Security Settings Tab -->
          <v-window-item value="security">
            <div class="config-section">
              <div class="section-header">
                <h3 class="section-title">Security Configuration</h3>
                <p class="section-description">
                  Configure password policies and authentication requirements
                </p>
              </div>

              <!-- Password Policy -->
              <div class="setting-group">
                <div class="group-header">
                  <h4 class="group-title">Password Policy</h4>
                </div>

                <div class="group-content">
                  <div class="setting-row">
                    <div class="setting-label">
                      <v-icon size="20" color="primary">mdi-form-textbox-password</v-icon>
                      <span>Minimum Password Length</span>
                    </div>
                    <v-text-field
                      v-model.number="config.security.minPasswordLength"
                      type="number"
                      variant="outlined"
                      density="compact"
                      hide-details
                      suffix="characters"
                      class="setting-input"
                    />
                  </div>

                  <div class="setting-row">
                    <div class="setting-label">
                      <v-icon size="20" color="warning">mdi-clock-alert</v-icon>
                      <span>Password Expiration (days)</span>
                    </div>
                    <v-text-field
                      v-model.number="config.security.passwordExpirationDays"
                      type="number"
                      variant="outlined"
                      density="compact"
                      hide-details
                      suffix="days"
                      class="setting-input"
                    />
                  </div>

                  <div class="checkbox-row">
                    <v-checkbox
                      v-model="config.security.requireUppercase"
                      label="Require uppercase letters"
                      color="primary"
                      hide-details
                      density="compact"
                    />
                  </div>

                  <div class="checkbox-row">
                    <v-checkbox
                      v-model="config.security.requireLowercase"
                      label="Require lowercase letters"
                      color="primary"
                      hide-details
                      density="compact"
                    />
                  </div>

                  <div class="checkbox-row">
                    <v-checkbox
                      v-model="config.security.requireNumbers"
                      label="Require numbers"
                      color="primary"
                      hide-details
                      density="compact"
                    />
                  </div>

                  <div class="checkbox-row">
                    <v-checkbox
                      v-model="config.security.requireSpecialChars"
                      label="Require special characters"
                      color="primary"
                      hide-details
                      density="compact"
                    />
                  </div>
                </div>
              </div>

              <!-- Two-Factor Authentication -->
              <div class="setting-group">
                <div class="group-header">
                  <h4 class="group-title">Two-Factor Authentication</h4>
                  <v-switch
                    v-model="config.security.require2FA"
                    color="primary"
                    hide-details
                    density="compact"
                  />
                </div>

                <div v-if="config.security.require2FA" class="group-content">
                  <div class="info-card warning">
                    <v-icon color="warning" size="18">mdi-alert</v-icon>
                    <div>
                      <strong>Important:</strong> Enabling this will require all users to set up 
                      two-factor authentication on their next login.
                    </div>
                  </div>
                </div>
              </div>

              <!-- Login Attempts -->
              <div class="setting-group">
                <div class="group-header">
                  <h4 class="group-title">Failed Login Protection</h4>
                </div>

                <div class="group-content">
                  <div class="setting-row">
                    <div class="setting-label">
                      <v-icon size="20" color="error">mdi-alert-circle</v-icon>
                      <span>Max Failed Attempts</span>
                    </div>
                    <v-text-field
                      v-model.number="config.security.maxLoginAttempts"
                      type="number"
                      variant="outlined"
                      density="compact"
                      hide-details
                      suffix="attempts"
                      class="setting-input"
                    />
                  </div>

                  <div class="setting-row">
                    <div class="setting-label">
                      <v-icon size="20" color="warning">mdi-lock-clock</v-icon>
                      <span>Lockout Duration (minutes)</span>
                    </div>
                    <v-text-field
                      v-model.number="config.security.lockoutDuration"
                      type="number"
                      variant="outlined"
                      density="compact"
                      hide-details
                      suffix="minutes"
                      class="setting-input"
                    />
                  </div>
                </div>
              </div>
            </div>
          </v-window-item>

          <!-- Session Management Tab -->
          <v-window-item value="session">
            <div class="config-section">
              <div class="section-header">
                <h3 class="section-title">Session Management</h3>
                <p class="section-description">
                  Configure user session timeouts and activity settings
                </p>
              </div>

              <!-- Session Timeout -->
              <div class="setting-group">
                <div class="group-header">
                  <h4 class="group-title">Session Timeout</h4>
                </div>

                <div class="group-content">
                  <div class="setting-row">
                    <div class="setting-label">
                      <v-icon size="20" color="primary">mdi-timer-outline</v-icon>
                      <span>Idle Timeout (minutes)</span>
                    </div>
                    <v-text-field
                      v-model.number="config.session.idleTimeout"
                      type="number"
                      variant="outlined"
                      density="compact"
                      hide-details
                      suffix="minutes"
                      class="setting-input"
                    />
                  </div>

                  <div class="setting-row">
                    <div class="setting-label">
                      <v-icon size="20" color="warning">mdi-clock-end</v-icon>
                      <span>Maximum Session Duration (hours)</span>
                    </div>
                    <v-text-field
                      v-model.number="config.session.maxDuration"
                      type="number"
                      variant="outlined"
                      density="compact"
                      hide-details
                      suffix="hours"
                      class="setting-input"
                    />
                  </div>

                  <div class="info-card">
                    <v-icon color="info" size="18">mdi-information-outline</v-icon>
                    <div>
                      Users will be automatically logged out after {{ config.session.idleTimeout }} minutes 
                      of inactivity or {{ config.session.maxDuration }} hours total session time.
                    </div>
                  </div>
                </div>
              </div>

              <!-- Concurrent Sessions -->
              <div class="setting-group">
                <div class="group-header">
                  <h4 class="group-title">Concurrent Sessions</h4>
                </div>

                <div class="group-content">
                  <div class="checkbox-row">
                    <v-checkbox
                      v-model="config.session.allowMultipleSessions"
                      label="Allow users to have multiple active sessions"
                      color="primary"
                      hide-details
                      density="compact"
                    />
                  </div>

                  <div v-if="config.session.allowMultipleSessions" class="setting-row">
                    <div class="setting-label">
                      <v-icon size="20" color="primary">mdi-account-multiple</v-icon>
                      <span>Max Concurrent Sessions</span>
                    </div>
                    <v-text-field
                      v-model.number="config.session.maxConcurrentSessions"
                      type="number"
                      variant="outlined"
                      density="compact"
                      hide-details
                      suffix="sessions"
                      class="setting-input"
                    />
                  </div>
                </div>
              </div>

              <!-- Active Sessions -->
              <div class="action-card">
                <div class="action-content">
                  <div class="action-icon">
                    <v-icon size="32" color="error">mdi-account-cancel</v-icon>
                  </div>
                  <div>
                    <h4 class="action-title">Terminate All Sessions</h4>
                    <p class="action-description">Force logout all users (except admins)</p>
                  </div>
                </div>
                <v-btn
                  color="error"
                  variant="elevated"
                  @click="confirmTerminateSessions"
                >
                  <v-icon start>mdi-logout</v-icon>
                  Logout All
                </v-btn>
              </div>
            </div>
          </v-window-item>

          <!-- Audit Logs Tab -->
          <v-window-item value="audit">
            <div class="config-section">
              <div class="section-header">
                <h3 class="section-title">Audit Log Configuration</h3>
                <p class="section-description">
                  Configure audit logging and data retention settings
                </p>
              </div>

              <!-- Audit Logging -->
              <div class="setting-group">
                <div class="group-header">
                  <h4 class="group-title">Audit Logging</h4>
                  <v-switch
                    v-model="config.audit.enabled"
                    color="primary"
                    hide-details
                    density="compact"
                  />
                </div>

                <div v-if="config.audit.enabled" class="group-content">
                  <div class="checkbox-row">
                    <v-checkbox
                      v-model="config.audit.logLoginAttempts"
                      label="Log all login attempts"
                      color="primary"
                      hide-details
                      density="compact"
                    />
                  </div>

                  <div class="checkbox-row">
                    <v-checkbox
                      v-model="config.audit.logDataChanges"
                      label="Log data modifications"
                      color="primary"
                      hide-details
                      density="compact"
                    />
                  </div>

                  <div class="checkbox-row">
                    <v-checkbox
                      v-model="config.audit.logPayrollAccess"
                      label="Log payroll access and exports"
                      color="primary"
                      hide-details
                      density="compact"
                    />
                  </div>

                  <div class="checkbox-row">
                    <v-checkbox
                      v-model="config.audit.logEmployeeChanges"
                      label="Log employee record changes"
                      color="primary"
                      hide-details
                      density="compact"
                    />
                  </div>

                  <div class="checkbox-row">
                    <v-checkbox
                      v-model="config.audit.logSettingsChanges"
                      label="Log settings changes"
                      color="primary"
                      hide-details
                      density="compact"
                    />
                  </div>
                </div>
              </div>

              <!-- Log Retention -->
              <div class="setting-group">
                <div class="group-header">
                  <h4 class="group-title">Log Retention</h4>
                </div>

                <div class="group-content">
                  <div class="setting-row">
                    <div class="setting-label">
                      <v-icon size="20" color="primary">mdi-calendar-clock</v-icon>
                      <span>Retention Period (days)</span>
                    </div>
                    <v-text-field
                      v-model.number="config.audit.retentionDays"
                      type="number"
                      variant="outlined"
                      density="compact"
                      hide-details
                      suffix="days"
                      class="setting-input"
                    />
                  </div>

                  <div class="info-card">
                    <v-icon color="info" size="18">mdi-information-outline</v-icon>
                    <div>
                      Audit logs older than {{ config.audit.retentionDays }} days will be automatically archived. 
                      Recommended minimum for compliance: 365 days (1 year).
                    </div>
                  </div>
                </div>
              </div>

              <!-- Export Logs -->
              <div class="action-card">
                <div class="action-content">
                  <div class="action-icon">
                    <v-icon size="32" color="success">mdi-file-export</v-icon>
                  </div>
                  <div>
                    <h4 class="action-title">Export Audit Logs</h4>
                    <p class="action-description">Download audit logs for external analysis</p>
                  </div>
                </div>
                <v-btn
                  color="success"
                  variant="elevated"
                  @click="exportAuditLogs"
                  :loading="exporting"
                >
                  <v-icon start>mdi-download</v-icon>
                  Export
                </v-btn>
              </div>
            </div>
          </v-window-item>
        </v-window>
      </v-card-text>

      <v-divider />

      <!-- Actions -->
      <v-card-actions class="dialog-actions">
        <v-btn variant="text" @click="closeDialog" class="cancel-btn">
          Cancel
        </v-btn>
        <v-spacer />
        <v-btn
          color="primary"
          variant="elevated"
          @click="saveConfiguration"
          :loading="saving"
          class="save-btn"
        >
          <v-icon start>mdi-content-save</v-icon>
          Save Configuration
        </v-btn>
      </v-card-actions>
    </v-card>

    <!-- Confirmation Dialog -->
    <v-dialog v-model="showConfirmDialog" max-width="500">
      <v-card>
        <v-card-title class="text-h6 bg-error text-white">
          Confirm Action
        </v-card-title>
        <v-card-text class="pt-4">
          Are you sure you want to terminate all user sessions? All users (except admins) will be logged out immediately.
        </v-card-text>
        <v-card-actions>
          <v-spacer />
          <v-btn variant="text" @click="showConfirmDialog = false">
            Cancel
          </v-btn>
          <v-btn color="error" variant="elevated" @click="terminateAllSessions">
            Confirm
          </v-btn>
        </v-card-actions>
      </v-card>
    </v-dialog>
  </v-dialog>
</template>

<script setup>
import { ref, watch } from 'vue';
import { useToast } from 'vue-toastification';

const props = defineProps({
  modelValue: Boolean,
});

const emit = defineEmits(['update:modelValue']);

const toast = useToast();

const isOpen = ref(props.modelValue);
const activeTab = ref('backup');
const saving = ref(false);
const backingUp = ref(false);
const exporting = ref(false);
const showConfirmDialog = ref(false);

const backupFrequencies = [
  { title: 'Daily', value: 'daily' },
  { title: 'Weekly', value: 'weekly' },
  { title: 'Monthly', value: 'monthly' },
];

// Configuration data
const config = ref({
  backup: {
    autoBackupEnabled: true,
    frequency: 'daily',
    backupTime: '02:00',
    retentionDays: 30,
    storageLocation: '/storage/backups',
    maxBackupSize: 500,
  },
  security: {
    minPasswordLength: 8,
    passwordExpirationDays: 90,
    requireUppercase: true,
    requireLowercase: true,
    requireNumbers: true,
    requireSpecialChars: true,
    require2FA: false,
    maxLoginAttempts: 5,
    lockoutDuration: 15,
  },
  session: {
    idleTimeout: 30,
    maxDuration: 8,
    allowMultipleSessions: true,
    maxConcurrentSessions: 3,
  },
  audit: {
    enabled: true,
    logLoginAttempts: true,
    logDataChanges: true,
    logPayrollAccess: true,
    logEmployeeChanges: true,
    logSettingsChanges: true,
    retentionDays: 365,
  },
});

watch(
  () => props.modelValue,
  (newVal) => {
    isOpen.value = newVal;
  }
);

watch(isOpen, (newVal) => {
  emit('update:modelValue', newVal);
});

const closeDialog = () => {
  isOpen.value = false;
};

const createBackup = async () => {
  backingUp.value = true;
  try {
    // Simulate backup creation
    await new Promise((resolve) => setTimeout(resolve, 2000));
    toast.success('Database backup created successfully');
  } catch (error) {
    toast.error('Failed to create backup: ' + error.message);
  } finally {
    backingUp.value = false;
  }
};

const confirmTerminateSessions = () => {
  showConfirmDialog.value = true;
};

const terminateAllSessions = async () => {
  showConfirmDialog.value = false;
  try {
    // Simulate session termination
    await new Promise((resolve) => setTimeout(resolve, 1000));
    toast.success('All user sessions have been terminated');
  } catch (error) {
    toast.error('Failed to terminate sessions: ' + error.message);
  }
};

const exportAuditLogs = async () => {
  exporting.value = true;
  try {
    // Simulate export
    await new Promise((resolve) => setTimeout(resolve, 1500));
    toast.success('Audit logs exported successfully');
  } catch (error) {
    toast.error('Failed to export logs: ' + error.message);
  } finally {
    exporting.value = false;
  }
};

const saveConfiguration = async () => {
  saving.value = true;
  try {
    // Simulate save
    await new Promise((resolve) => setTimeout(resolve, 1000));
    toast.success('Security configuration saved successfully');
    closeDialog();
  } catch (error) {
    toast.error('Failed to save configuration: ' + error.message);
  } finally {
    saving.value = false;
  }
};
</script>

<style scoped lang="scss">
.backup-security-dialog {
  border-radius: 16px;
}

.dialog-header {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: white;
  padding: 24px 28px;
  position: relative;
}

.header-content {
  display: flex;
  align-items: center;
  gap: 16px;
  width: 100%;
}

.icon-wrapper {
  width: 56px;
  height: 56px;
  background: rgba(255, 255, 255, 0.2);
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.dialog-title {
  font-size: 24px;
  font-weight: 700;
  margin: 0;
  color: white;
}

.dialog-subtitle {
  font-size: 14px;
  margin: 4px 0 0 0;
  opacity: 0.9;
}

.close-btn {
  position: absolute;
  top: 16px;
  right: 16px;

  :deep(.v-icon) {
    color: white !important;
  }
}

.dialog-content {
  padding: 0;
  max-height: 70vh;
}

.config-tabs {
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
  padding: 0 24px;

  :deep(.v-tab) {
    text-transform: none;
    font-weight: 600;
    letter-spacing: 0;
  }
}

.config-window {
  padding: 24px;
}

.config-section {
  display: flex;
  flex-direction: column;
  gap: 24px;
}

.section-header {
  margin-bottom: 8px;
}

.section-title {
  font-size: 20px;
  font-weight: 700;
  color: #001f3d;
  margin: 0 0 8px 0;
}

.section-description {
  font-size: 14px;
  color: rgba(0, 31, 61, 0.6);
  margin: 0;
}

// Setting Groups
.setting-group {
  background: white;
  border: 1px solid rgba(0, 0, 0, 0.1);
  border-radius: 12px;
  overflow: hidden;
}

.group-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 16px 20px;
  background: rgba(237, 152, 95, 0.06);
  border-bottom: 1px solid rgba(0, 0, 0, 0.08);
}

.group-title {
  font-size: 16px;
  font-weight: 600;
  color: #001f3d;
  margin: 0;
}

.group-content {
  padding: 20px;
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.setting-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.setting-label {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 14px;
  font-weight: 500;
  color: #001f3d;
  flex: 1;
}

.setting-input,
.setting-select {
  max-width: 250px;

  :deep(.v-field) {
    background: rgba(237, 152, 95, 0.04);
  }
}

.setting-input-large {
  flex: 1;

  :deep(.v-field) {
    background: rgba(237, 152, 95, 0.04);
  }
}

.checkbox-row {
  padding-left: 4px;
}

.info-card {
  display: flex;
  gap: 12px;
  padding: 16px;
  background: rgba(66, 133, 244, 0.06);
  border: 1px solid rgba(66, 133, 244, 0.15);
  border-radius: 10px;
  font-size: 14px;
  color: rgba(0, 31, 61, 0.8);
  line-height: 1.5;

  &.warning {
    background: rgba(251, 188, 4, 0.06);
    border-color: rgba(251, 188, 4, 0.15);
  }

  strong {
    color: #001f3d;
  }
}

// Action Cards
.action-card {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 20px;
  padding: 20px;
  background: rgba(237, 152, 95, 0.04);
  border: 1px solid rgba(237, 152, 95, 0.15);
  border-radius: 12px;
}

.action-content {
  display: flex;
  align-items: center;
  gap: 16px;
  flex: 1;
}

.action-icon {
  width: 60px;
  height: 60px;
  background: white;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.action-title {
  font-size: 16px;
  font-weight: 600;
  color: #001f3d;
  margin: 0 0 4px 0;
}

.action-description {
  font-size: 13px;
  color: rgba(0, 31, 61, 0.6);
  margin: 0;
}

// Actions
.dialog-actions {
  padding: 20px 28px;
  background: rgba(0, 0, 0, 0.02);
}

.cancel-btn {
  text-transform: none;
  font-weight: 600;
}

.save-btn {
  text-transform: none;
  font-weight: 600;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: white;
  padding: 0 24px;

  :deep(.v-icon) {
    color: white !important;
  }
}
</style>
