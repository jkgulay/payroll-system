<template>
  <div class="company-info-page">
    <v-overlay :model-value="loading" class="align-center justify-center">
      <v-progress-circular
        indeterminate
        color="#ED985F"
        :size="70"
        :width="7"
      ></v-progress-circular>
    </v-overlay>

    <!-- Page Header -->
    <div class="page-header">
      <div class="back-button-wrapper">
        <button class="back-button" @click="$router.push('/settings')">
          <v-icon size="20">mdi-arrow-left</v-icon>
          <span>Back to Settings</span>
        </button>
      </div>

      <div class="header-main">
        <div class="page-icon-badge">
          <v-icon size="22">mdi-office-building</v-icon>
        </div>
        <div class="header-info">
          <h1 class="page-title">Company Information</h1>
          <p class="page-subtitle">
            Manage company details that appear on payroll documents and reports
          </p>
        </div>
      </div>
    </div>

    <!-- Company Info Form -->
    <div class="content-wrapper">
      <div class="info-card">
        <!-- Company Logo and Name -->
        <div class="logo-name-section">
          <!-- Logo Upload -->
          <div class="logo-container">
            <div v-if="logoPreview" class="logo-preview">
              <img :src="logoPreview" alt="Company Logo" />
              <button class="remove-logo-btn" @click="removeLogo" type="button">
                <v-icon size="18">mdi-close</v-icon>
              </button>
            </div>
            <div v-else class="logo-placeholder">
              <v-icon size="64" color="rgba(237, 152, 95, 0.3)"
                >mdi-office-building</v-icon
              >
            </div>

            <input
              ref="logoInput"
              type="file"
              accept="image/*"
              @change="handleLogoUpload"
              style="display: none"
            />
            <div class="logo-button-group">
              <button class="upload-logo-btn" @click="$refs.logoInput.click()">
                <v-icon size="20">mdi-upload</v-icon>
                <span>{{ logoPreview ? "Change Logo" : "Upload Logo" }}</span>
              </button>
              <button
                v-if="logoPreview"
                class="reset-logo-btn"
                @click="resetLogoToDefault"
                type="button"
              >
                <v-icon size="20">mdi-restore</v-icon>
                <span>Reset to Icon</span>
              </button>
            </div>
            <p class="logo-hint">PNG, JPG (Max 2MB)</p>
          </div>

          <!-- Company Name -->
          <div class="name-container">
            <label class="field-label">Company Name *</label>
            <v-text-field
              v-model="formData.company_name"
              variant="outlined"
              density="comfortable"
              placeholder="Enter company name"
              hide-details
              :rules="[rules.required]"
            ></v-text-field>
          </div>
        </div>

        <v-divider class="section-divider"></v-divider>

        <!-- Address -->
        <div class="info-section">
          <div class="section-title">
            <v-icon size="20" color="#ed985f">mdi-map-marker</v-icon>
            <span>Company Address</span>
          </div>
          <div class="field-group">
            <label class="field-label">Full Address *</label>
            <v-textarea
              v-model="formData.address"
              variant="outlined"
              density="comfortable"
              placeholder="Street, Barangay, City"
              rows="2"
              hide-details
              :rules="[rules.required]"
            ></v-textarea>
          </div>
        </div>

        <v-divider class="section-divider"></v-divider>

        <!-- Contact Information -->
        <div class="info-section">
          <div class="section-title">
            <v-icon size="20" color="#ed985f">mdi-phone</v-icon>
            <span>Contact Information</span>
          </div>
          <div class="fields-row">
            <div class="field-group">
              <label class="field-label">Phone Number</label>
              <v-text-field
                v-model="formData.phone"
                variant="outlined"
                density="comfortable"
                placeholder="+63 xxx xxx xxxx"
                hide-details
              ></v-text-field>
            </div>
            <div class="field-group">
              <label class="field-label">Email Address</label>
              <v-text-field
                v-model="formData.email"
                variant="outlined"
                density="comfortable"
                placeholder="company@example.com"
                type="email"
                hide-details
                :rules="[rules.email]"
              ></v-text-field>
            </div>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="action-bar">
          <button
            class="action-btn btn-cancel"
            @click="resetForm"
            :disabled="!hasChanges"
          >
            <v-icon size="20">mdi-refresh</v-icon>
            <span>Reset Changes</span>
          </button>
          <button
            class="action-btn btn-save"
            @click="saveCompanyInfo"
            :disabled="!hasChanges || saving"
          >
            <v-icon size="20">mdi-content-save</v-icon>
            <span>{{ saving ? "Saving..." : "Save Changes" }}</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useToast } from "vue-toastification";
import { useCompanyInfoStore } from "@/stores/companyInfo";
import api from "@/services/api";

const router = useRouter();
const toast = useToast();
const companyInfoStore = useCompanyInfoStore();

const loading = ref(false);
const saving = ref(false);
const logoInput = ref(null);
const logoPreview = ref(null);
const logoFile = ref(null);

const originalData = ref({});
const formData = ref({
  company_name: "",
  address: "",
  phone: "",
  email: "",
  logo_path: null,
});

const rules = {
  required: (value) => !!value || "This field is required",
  email: (value) => {
    if (!value) return true;
    const pattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return pattern.test(value) || "Invalid email address";
  },
};

const hasChanges = computed(() => {
  return (
    JSON.stringify(formData.value) !== JSON.stringify(originalData.value) ||
    logoFile.value !== null
  );
});

onMounted(() => {
  fetchCompanyInfo();
});

async function fetchCompanyInfo() {
  loading.value = true;
  try {
    const response = await api.get("/company-info");
    if (response.data.data) {
      formData.value = { ...response.data.data };
      originalData.value = { ...response.data.data };

      // Set logo preview if exists
      if (formData.value.logo_path) {
        // If it's already a full URL, use it
        if (formData.value.logo_path.startsWith("http")) {
          logoPreview.value = formData.value.logo_path;
        } else {
          // Otherwise, construct the full URL using the backend API URL
          const apiUrl = (
            import.meta.env.VITE_API_URL || "http://localhost:8000/api"
          ).replace("/api", "");
          logoPreview.value = `${apiUrl}/storage/${formData.value.logo_path}`;
        }
      }
    }
  } catch (error) {
    console.error("Error fetching company info:", error);
    toast.error("Failed to load company information");
  } finally {
    loading.value = false;
  }
}

function handleLogoUpload(event) {
  const file = event.target.files[0];
  if (!file) return;

  // Validate file size (2MB)
  if (file.size > 2 * 1024 * 1024) {
    toast.error("File size must be less than 2MB");
    return;
  }

  // Validate file type
  if (!file.type.startsWith("image/")) {
    toast.error("Please upload an image file");
    return;
  }

  logoFile.value = file;

  // Create preview
  const reader = new FileReader();
  reader.onload = (e) => {
    logoPreview.value = e.target.result;
  };
  reader.readAsDataURL(file);
}

function removeLogo() {
  logoFile.value = null;
  logoPreview.value = null;
  formData.value.logo_path = null;
  if (logoInput.value) {
    logoInput.value.value = "";
  }
}

async function resetLogoToDefault() {
  try {
    // Send request to delete logo from backend
    await api.delete("/company-info/logo");

    // Clear local state
    logoFile.value = null;
    logoPreview.value = null;
    formData.value.logo_path = null;
    if (logoInput.value) {
      logoInput.value.value = "";
    }

    // Update original data to reflect the change
    originalData.value.logo_path = null;

    // Refresh company info store to update navbar/sidebar immediately
    await companyInfoStore.fetchCompanyInfo(true);

    toast.success("Logo reset to default icon successfully");
  } catch (error) {
    console.error("Error resetting logo:", error);
    toast.error(error.response?.data?.message || "Failed to reset logo");
  }
}

async function saveCompanyInfo() {
  saving.value = true;
  try {
    const formDataToSend = new FormData();

    // Append all text fields
    Object.keys(formData.value).forEach((key) => {
      if (key !== "logo_path" && formData.value[key] !== null) {
        formDataToSend.append(key, formData.value[key]);
      }
    });

    // Append logo if changed
    if (logoFile.value) {
      formDataToSend.append("logo", logoFile.value);
    }

    await api.post("/company-info", formDataToSend, {
      headers: {
        "Content-Type": "multipart/form-data",
      },
    });

    toast.success("Company information updated successfully");
    await fetchCompanyInfo();

    // Refresh company info store to update navbar/sidebar
    await companyInfoStore.fetchCompanyInfo(true);

    logoFile.value = null;
  } catch (error) {
    console.error("Error saving company info:", error);
    toast.error(
      error.response?.data?.message || "Failed to save company information",
    );
  } finally {
    saving.value = false;
  }
}

function resetForm() {
  formData.value = { ...originalData.value };
  logoFile.value = null;

  // Reset logo preview
  if (originalData.value.logo_path) {
    // If it's already a full URL, use it
    if (originalData.value.logo_path.startsWith("http")) {
      logoPreview.value = originalData.value.logo_path;
    } else {
      // Otherwise, construct the full URL using the backend API URL
      const apiUrl = (
        import.meta.env.VITE_API_URL || "http://localhost:8000/api"
      ).replace("/api", "");
      logoPreview.value = `${apiUrl}/storage/${originalData.value.logo_path}`;
    }
  } else {
    logoPreview.value = null;
  }

  if (logoInput.value) {
    logoInput.value.value = "";
  }

  toast.info("Form reset to saved values");
}
</script>

<style scoped lang="scss">
.company-info-page {
  padding: 0 24px 24px 24px;
  max-width: 1200px;
  margin: 0 auto;
}

// Page Header
.page-header {
  margin-bottom: 32px;
}

.back-button-wrapper {
  margin-bottom: 24px;
}

.back-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 10px 16px;
  background: #ffffff;
  border: 1px solid rgba(0, 31, 61, 0.12);
  border-radius: 10px;
  color: #001f3d;
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;

  .v-icon {
    color: #001f3d !important;
  }

  &:hover {
    background: rgba(0, 31, 61, 0.04);
    border-color: rgba(0, 31, 61, 0.2);
  }
}

.header-main {
  display: flex;
  align-items: flex-start;
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
  flex-shrink: 0;
  box-shadow: 0 4px 16px rgba(237, 152, 95, 0.25);

  .v-icon {
    color: #ffffff !important;
  }
}

.header-info {
  flex: 1;
}

.page-title {
  font-size: 28px;
  font-weight: 700;
  color: #001f3d;
  margin: 0 0 8px 0;
  letter-spacing: -0.5px;
}

.page-subtitle {
  font-size: 14px;
  color: rgba(0, 31, 61, 0.6);
  margin: 0;
  line-height: 1.5;
}

// Content
.content-wrapper {
  display: flex;
  justify-content: center;
}

.info-card {
  background: #ffffff;
  border-radius: 16px;
  padding: 40px;
  box-shadow: 0 2px 12px rgba(0, 31, 61, 0.06);
  width: 100%;
  max-width: 800px;
}

// Logo and Name Section
.logo-name-section {
  display: flex;
  gap: 40px;
  align-items: flex-start;

  @media (max-width: 768px) {
    flex-direction: column;
    gap: 24px;
  }
}

.logo-container {
  flex-shrink: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 16px;
}

.logo-preview {
  position: relative;
  width: 180px;
  height: 180px;
  border-radius: 16px;
  border: 3px solid rgba(237, 152, 95, 0.2);
  overflow: hidden;
  background: #ffffff;
  box-shadow: 0 4px 16px rgba(0, 31, 61, 0.08);

  img {
    width: 100%;
    height: 100%;
    object-fit: contain;
    padding: 12px;
  }
}

.logo-placeholder {
  width: 180px;
  height: 180px;
  border-radius: 16px;
  border: 3px dashed rgba(237, 152, 95, 0.3);
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(237, 152, 95, 0.05);
}

.remove-logo-btn {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  background: rgba(244, 67, 54, 0.95);
  border: 2px solid #ffffff;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;

  .v-icon {
    color: #ffffff !important;
  }

  &:hover {
    background: #f44336;
    transform: scale(1.1);
  }
}

.logo-button-group {
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: 100%;
}

.upload-logo-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 24px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  border: none;
  border-radius: 12px;
  color: #ffffff;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);

  .v-icon {
    color: #ffffff !important;
  }

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(237, 152, 95, 0.4);
  }
}

.reset-logo-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 10px 20px;
  background: #ffffff;
  border: 1px solid rgba(0, 31, 61, 0.2);
  border-radius: 12px;
  color: #001f3d;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;

  .v-icon {
    color: #001f3d !important;
  }

  &:hover {
    background: rgba(0, 31, 61, 0.04);
    border-color: rgba(0, 31, 61, 0.3);
    transform: translateY(-1px);
  }
}

.logo-hint {
  font-size: 12px;
  color: rgba(0, 31, 61, 0.5);
  margin: 0;
  text-align: center;
}

.name-container {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

// Section Divider
.section-divider {
  margin: 32px 0;
  border-color: rgba(0, 31, 61, 0.08);
}

// Info Sections
.info-section {
  display: flex;
  flex-direction: column;
  gap: 20px;
}

.section-title {
  display: flex;
  align-items: center;
  gap: 12px;
  font-size: 18px;
  font-weight: 600;
  color: #001f3d;
  margin-bottom: 8px;

  span {
    letter-spacing: -0.3px;
  }
}

// Field Styles
.field-label {
  display: block;
  font-size: 14px;
  font-weight: 600;
  color: #001f3d;
  margin-bottom: 8px;
}

.field-group {
  display: flex;
  flex-direction: column;
}

.fields-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 20px;
}

// Action Bar
.action-bar {
  margin-top: 40px;
  padding-top: 32px;
  border-top: 1px solid rgba(0, 31, 61, 0.08);
  display: flex;
  justify-content: flex-end;
  gap: 16px;
}

.action-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 14px 28px;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;

  .v-icon {
    transition: transform 0.2s ease;
  }

  &:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }

  &:not(:disabled):hover .v-icon {
    transform: scale(1.1);
  }
}

.btn-cancel {
  background: #ffffff;
  color: #001f3d;
  border: 1px solid rgba(0, 31, 61, 0.2);

  .v-icon {
    color: #001f3d !important;
  }

  &:not(:disabled):hover {
    background: rgba(0, 31, 61, 0.04);
    border-color: rgba(0, 31, 61, 0.3);
  }
}

.btn-save {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);

  .v-icon {
    color: #ffffff !important;
  }

  &:not(:disabled):hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(237, 152, 95, 0.4);
  }
}

// Vuetify Overrides
:deep(.v-field) {
  border-radius: 12px;
  font-size: 14px;
}

:deep(.v-field--variant-outlined .v-field__outline) {
  color: rgba(0, 31, 61, 0.15);
}

:deep(.v-field--focused .v-field__outline) {
  color: #ed985f;
  border-width: 2px;
}

:deep(.v-field__input) {
  color: #001f3d;
  font-size: 14px;
  padding: 12px 16px;
  min-height: 48px;
}

:deep(.v-field--variant-outlined .v-field__input) {
  padding: 12px 16px;
}

:deep(textarea.v-field__input) {
  padding: 14px 16px;
}

// Responsive
@media (max-width: 768px) {
  .company-info-page {
    padding: 0 16px 16px 16px;
  }

  .info-card {
    padding: 24px;
  }

  .action-bar {
    flex-direction: column;

    .action-btn {
      width: 100%;
    }
  }

  .fields-row {
    grid-template-columns: 1fr;
  }
}
</style>
