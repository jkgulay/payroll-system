import { defineStore } from "pinia";
import api from "@/services/api";

export const useCompanyInfoStore = defineStore("companyInfo", {
  state: () => ({
    companyInfo: {
      company_name: "Giovanni Construction",
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
    },
    loading: false,
    lastFetched: null,
  }),

  getters: {
    companyName: (state) =>
      state.companyInfo.company_name || "Giovanni Construction",
    companyLogo: (state) => {
      if (!state.companyInfo.logo_path) return null;

      // If it's already a full URL, return it
      if (state.companyInfo.logo_path.startsWith("http")) {
        return state.companyInfo.logo_path;
      }

      // Otherwise, construct the full URL using the backend API URL
      const apiUrl = (
        import.meta.env.VITE_API_URL || "http://localhost:8000/api"
      ).replace("/api", "");
      return `${apiUrl}/storage/${state.companyInfo.logo_path}`;
    },
    hasLogo: (state) => !!state.companyInfo.logo_path,
    fullAddress: (state) => {
      const parts = [
        state.companyInfo.address,
        state.companyInfo.city,
        state.companyInfo.province,
        state.companyInfo.postal_code,
      ].filter(Boolean);
      return parts.join(", ");
    },
    contactInfo: (state) => ({
      phone: state.companyInfo.phone,
      mobile: state.companyInfo.mobile,
      email: state.companyInfo.email,
      website: state.companyInfo.website,
    }),
  },

  actions: {
    async fetchCompanyInfo(forceRefresh = false) {
      // Cache for 5 minutes
      const cacheTime = 5 * 60 * 1000;
      if (
        !forceRefresh &&
        this.lastFetched &&
        Date.now() - this.lastFetched < cacheTime
      ) {
        return this.companyInfo;
      }

      this.loading = true;
      try {
        const response = await api.get("/company-info");
        if (response.data.data) {
          this.companyInfo = { ...this.companyInfo, ...response.data.data };
          this.lastFetched = Date.now();
        }
        return this.companyInfo;
      } catch (error) {
        console.error("Error fetching company info:", error);
        // Keep default values on error
        return this.companyInfo;
      } finally {
        this.loading = false;
      }
    },

    async updateCompanyInfo(data) {
      this.loading = true;
      try {
        const response = await api.put("/company-info", data);
        if (response.data.data) {
          this.companyInfo = { ...this.companyInfo, ...response.data.data };
          this.lastFetched = Date.now();
        }
        return response.data;
      } catch (error) {
        console.error("Error updating company info:", error);
        throw error;
      } finally {
        this.loading = false;
      }
    },

    clearCache() {
      this.lastFetched = null;
    },
  },
});
