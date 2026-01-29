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

    <!-- Modern Page Header -->
    <div class="page-header">
      <div class="header-content">
        <div class="back-button-wrapper">
          <button class="back-button" @click="$router.push('/settings')">
            <v-icon size="20">mdi-arrow-left</v-icon>
            <span>Back to Settings</span>
          </button>
        </div>

        <div class="header-main">
          <div class="page-title-section">
            <div class="page-icon-badge">
              <v-icon size="22">mdi-office-building</v-icon>
            </div>
            <div>
              <h1 class="page-title">Company Information</h1>
              <p class="page-subtitle">
                Configure company details, logo, and contact information for
                reports and documents
              </p>
            </div>
          </div>
          <div class="action-buttons">
            <button
              class="action-btn action-btn-secondary"
              @click="resetForm"
              :disabled="!hasChanges"
            >
              <v-icon size="20">mdi-refresh</v-icon>
              <span>Reset</span>
            </button>
            <button
              class="action-btn action-btn-primary"
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

    <!-- Company Info Form -->
    <div class="content-card">
      <!-- Company Logo Section -->
      <div class="form-section">
        <div class="section-header">
          <div class="section-icon">
            <v-icon size="20">mdi-image</v-icon>
          </div>
          <div>
            <h3 class="section-title">Company Logo</h3>
            <p class="section-description">
              Upload your company logo for reports and documents
            </p>
          </div>
        </div>

        <div class="logo-upload-area">
          <div class="logo-preview">
            <div v-if="logoPreview" class="logo-image-wrapper">
              <img :src="logoPreview" alt="Company Logo" class="logo-image" />
              <button class="remove-logo-btn" @click="removeLogo" type="button">
                <v-icon size="16">mdi-close</v-icon>
              </button>
            </div>
            <div v-else class="logo-placeholder">
              <v-icon size="48" color="#ed985f">mdi-office-building</v-icon>
              <span class="placeholder-text">No logo uploaded</span>
            </div>
          </div>

          <div class="logo-upload-controls">
            <input
              ref="logoInput"
              type="file"
              accept="image/*"
              @change="handleLogoUpload"
              style="display: none"
            />
            <button
              class="upload-btn"
              @click="$refs.logoInput.click()"
              type="button"
            >
              <v-icon size="20">mdi-upload</v-icon>
              <span>Upload Logo</span>
            </button>
            <p class="upload-hint">
              PNG, JPG or GIF (Max 2MB, Recommended: 400x400px)
            </p>
          </div>
        </div>
      </div>

      <v-divider class="section-divider"></v-divider>

      <!-- Basic Information -->
      <div class="form-section">
        <div class="section-header">
          <div class="section-icon">
            <v-icon size="20">mdi-information</v-icon>
          </div>
          <div>
            <h3 class="section-title">Basic Information</h3>
            <p class="section-description">Company name and business details</p>
          </div>
        </div>

        <div class="form-grid">
          <v-text-field
            v-model="formData.company_name"
            label="Company Name"
            variant="outlined"
            density="comfortable"
            prepend-inner-icon="mdi-office-building"
            :rules="[rules.required]"
          ></v-text-field>

          <v-text-field
            v-model="formData.business_type"
            label="Business Type"
            variant="outlined"
            density="comfortable"
            prepend-inner-icon="mdi-briefcase"
            placeholder="e.g., Construction, Retail, Services"
          ></v-text-field>

          <v-text-field
            v-model="formData.tin"
            label="Tax Identification Number (TIN)"
            variant="outlined"
            density="comfortable"
            prepend-inner-icon="mdi-card-account-details"
            placeholder="000-000-000-000"
          ></v-text-field>

          <v-text-field
            v-model="formData.registration_number"
            label="Business Registration Number"
            variant="outlined"
            density="comfortable"
            prepend-inner-icon="mdi-file-document"
            placeholder="SEC/DTI Number"
          ></v-text-field>
        </div>
      </div>

      <v-divider class="section-divider"></v-divider>

      <!-- Contact Information -->
      <div class="form-section">
        <div class="section-header">
          <div class="section-icon">
            <v-icon size="20">mdi-contacts</v-icon>
          </div>
          <div>
            <h3 class="section-title">Contact Information</h3>
            <p class="section-description">
              Company contact details and location
            </p>
          </div>
        </div>

        <div class="form-grid">
          <v-textarea
            v-model="formData.address"
            label="Address"
            variant="outlined"
            density="comfortable"
            prepend-inner-icon="mdi-map-marker"
            rows="2"
            class="full-width"
          ></v-textarea>

          <v-text-field
            v-model="formData.city"
            label="City"
            variant="outlined"
            density="comfortable"
            prepend-inner-icon="mdi-city"
          ></v-text-field>

          <v-text-field
            v-model="formData.province"
            label="Province/State"
            variant="outlined"
            density="comfortable"
            prepend-inner-icon="mdi-map"
          ></v-text-field>

          <v-text-field
            v-model="formData.postal_code"
            label="Postal Code"
            variant="outlined"
            density="comfortable"
            prepend-inner-icon="mdi-mailbox"
          ></v-text-field>

          <v-text-field
            v-model="formData.country"
            label="Country"
            variant="outlined"
            density="comfortable"
            prepend-inner-icon="mdi-flag"
          ></v-text-field>

          <v-text-field
            v-model="formData.phone"
            label="Phone Number"
            variant="outlined"
            density="comfortable"
            prepend-inner-icon="mdi-phone"
            placeholder="+63 xxx xxx xxxx"
          ></v-text-field>

          <v-text-field
            v-model="formData.mobile"
            label="Mobile Number"
            variant="outlined"
            density="comfortable"
            prepend-inner-icon="mdi-cellphone"
            placeholder="+63 9xx xxx xxxx"
          ></v-text-field>

          <v-text-field
            v-model="formData.email"
            label="Email Address"
            variant="outlined"
            density="comfortable"
            prepend-inner-icon="mdi-email"
            type="email"
            :rules="[rules.email]"
          ></v-text-field>

          <v-text-field
            v-model="formData.website"
            label="Website"
            variant="outlined"
            density="comfortable"
            prepend-inner-icon="mdi-web"
            placeholder="https://www.example.com"
          ></v-text-field>
        </div>
      </div>

      <v-divider class="section-divider"></v-divider>

      <!-- Report Settings -->
      <div class="form-section">
        <div class="section-header">
          <div class="section-icon">
            <v-icon size="20">mdi-file-document-outline</v-icon>
          </div>
          <div>
            <h3 class="section-title">Report & Document Settings</h3>
            <p class="section-description">
              Configure information displayed on reports and documents
            </p>
          </div>
        </div>

        <div class="form-grid">
          <v-text-field
            v-model="formData.report_header"
            label="Report Header Text"
            variant="outlined"
            density="comfortable"
            prepend-inner-icon="mdi-format-header-1"
            placeholder="Will appear at the top of reports"
            class="full-width"
          ></v-text-field>

          <v-text-field
            v-model="formData.report_footer"
            label="Report Footer Text"
            variant="outlined"
            density="comfortable"
            prepend-inner-icon="mdi-format-header-pound"
            placeholder="Will appear at the bottom of reports"
            class="full-width"
          ></v-text-field>

          <v-text-field
            v-model="formData.prepared_by_title"
            label="Prepared By Title"
            variant="outlined"
            density="comfortable"
            prepend-inner-icon="mdi-account-edit"
            placeholder="e.g., Payroll Officer"
          ></v-text-field>

          <v-text-field
            v-model="formData.approved_by_title"
            label="Approved By Title"
            variant="outlined"
            density="comfortable"
            prepend-inner-icon="mdi-account-check"
            placeholder="e.g., HR Manager"
          ></v-text-field>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from "vue";
import { useRouter } from "vue-router";
import { useToast } from "vue-toastification";
import api from "@/services/api";

const router = useRouter();
const toast = useToast();

const loading = ref(false);
const saving = ref(false);
const logoInput = ref(null);
const logoPreview = ref(null);
const logoFile = ref(null);

const originalData = ref({});
const formData = ref({
  company_name: "",
  business_type: "",
  tin: "",
  registration_number: "",
  address: "",
  city: "",
  province: "",
  postal_code: "",
  country: "Philippines",
  phone: "",
  mobile: "",
  email: "",
  website: "",
  logo_path: null,
  report_header: "",
  report_footer: "",
  prepared_by_title: "",
  approved_by_title: "",
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
        logoPreview.value = formData.value.logo_path.startsWith("http")
          ? formData.value.logo_path
          : `/storage/${formData.value.logo_path}`;
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
    logoPreview.value = originalData.value.logo_path.startsWith("http")
      ? originalData.value.logo_path
      : `/storage/${originalData.value.logo_path}`;
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
  max-width: 1200px;
  margin: 0 auto;
  padding: 0 16px;
}

// Page Header
.page-header {
  margin-bottom: 32px;
}

.header-content {
  display: flex;
  flex-direction: column;
  gap: 16px;
}

.back-button-wrapper {
  display: flex;
}

.back-button {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: transparent;
  border: 1px solid rgba(0, 31, 61, 0.12);
  border-radius: 8px;
  color: rgba(0, 31, 61, 0.7);
  font-size: 14px;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s ease;

  &:hover {
    background: rgba(237, 152, 95, 0.08);
    border-color: rgba(237, 152, 95, 0.3);
    color: #ed985f;

    .v-icon {
      color: #ed985f !important;
    }
  }

  .v-icon {
    color: rgba(0, 31, 61, 0.5) !important;
    transition: color 0.2s ease;
  }
}

.header-main {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  gap: 24px;
  flex-wrap: wrap;
}

.page-title-section {
  display: flex;
  align-items: center;
  gap: 16px;
  flex: 1;
  min-width: 300px;
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
  line-height: 1.4;
}

.action-buttons {
  display: flex;
  gap: 12px;
  flex-shrink: 0;
}

.action-btn {
  display: inline-flex;
  align-items: center;
  gap: 8px;
  padding: 12px 24px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  border: none;
  white-space: nowrap;

  &:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }
}

.action-btn-primary {
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  color: #ffffff;
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);

  .v-icon {
    color: #ffffff !important;
  }

  &:hover:not(:disabled) {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(237, 152, 95, 0.4);
  }
}

.action-btn-secondary {
  background: #ffffff;
  color: #ed985f;
  border: 1px solid rgba(237, 152, 95, 0.3);

  .v-icon {
    color: #ed985f !important;
  }

  &:hover:not(:disabled) {
    background: rgba(237, 152, 95, 0.08);
  }
}

// Content Card
.content-card {
  background: #ffffff;
  border-radius: 16px;
  border: 1px solid rgba(0, 31, 61, 0.08);
  padding: 32px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.form-section {
  margin-bottom: 32px;

  &:last-child {
    margin-bottom: 0;
  }
}

.section-header {
  display: flex;
  align-items: flex-start;
  gap: 16px;
  margin-bottom: 24px;
}

.section-icon {
  width: 40px;
  height: 40px;
  border-radius: 10px;
  background: linear-gradient(
    135deg,
    rgba(237, 152, 95, 0.12) 0%,
    rgba(247, 185, 128, 0.08) 100%
  );
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;

  .v-icon {
    color: #ed985f !important;
  }
}

.section-title {
  font-size: 18px;
  font-weight: 700;
  color: #001f3d;
  margin: 0 0 4px 0;
  letter-spacing: -0.3px;
}

.section-description {
  font-size: 13px;
  color: rgba(0, 31, 61, 0.6);
  margin: 0;
  line-height: 1.5;
}

.section-divider {
  margin: 32px 0;
  border-color: rgba(0, 31, 61, 0.08);
}

// Form Grid
.form-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
  gap: 20px;

  .full-width {
    grid-column: 1 / -1;
  }
}

// Logo Upload
.logo-upload-area {
  display: flex;
  gap: 32px;
  align-items: flex-start;

  @media (max-width: 768px) {
    flex-direction: column;
  }
}

.logo-preview {
  flex-shrink: 0;
}

.logo-image-wrapper {
  position: relative;
  width: 200px;
  height: 200px;
  border-radius: 12px;
  border: 2px solid rgba(0, 31, 61, 0.08);
  overflow: hidden;
  background: #f9f9f9;
}

.logo-image {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.remove-logo-btn {
  position: absolute;
  top: 8px;
  right: 8px;
  width: 32px;
  height: 32px;
  border-radius: 50%;
  background: rgba(244, 67, 54, 0.9);
  border: none;
  display: flex;
  align-items: center;
  justify-content: center;
  cursor: pointer;
  transition: all 0.2s ease;

  &:hover {
    background: #f44336;
    transform: scale(1.1);
  }

  .v-icon {
    color: #ffffff !important;
  }
}

.logo-placeholder {
  width: 200px;
  height: 200px;
  border-radius: 12px;
  border: 2px dashed rgba(0, 31, 61, 0.15);
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  background: #fafafa;
}

.placeholder-text {
  font-size: 14px;
  color: rgba(0, 31, 61, 0.5);
  font-weight: 500;
}

.logo-upload-controls {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 12px;
}

.upload-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  padding: 12px 24px;
  background: linear-gradient(135deg, #ed985f 0%, #f7b980 100%);
  border: none;
  border-radius: 10px;
  color: #ffffff;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 4px 12px rgba(237, 152, 95, 0.3);
  align-self: flex-start;

  .v-icon {
    color: #ffffff !important;
  }

  &:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(237, 152, 95, 0.4);
  }
}

.upload-hint {
  font-size: 13px;
  color: rgba(0, 31, 61, 0.5);
  margin: 0;
  line-height: 1.5;
}

// Vuetify Overrides
:deep(.v-field) {
  border-radius: 10px;
}

:deep(.v-field--variant-outlined .v-field__outline) {
  color: rgba(0, 31, 61, 0.12);
}

:deep(.v-field--focused .v-field__outline) {
  color: #ed985f;
}

:deep(.v-label) {
  color: rgba(0, 31, 61, 0.6);
  font-size: 14px;
}

:deep(.v-field__input) {
  color: #001f3d;
  font-size: 14px;
}

@media (max-width: 768px) {
  .company-info-page {
    padding: 0 8px;
  }

  .content-card {
    padding: 20px;
  }

  .header-main {
    flex-direction: column;
  }

  .action-buttons {
    width: 100%;

    .action-btn {
      flex: 1;
    }
  }

  .form-grid {
    grid-template-columns: 1fr;
  }
}
</style>
