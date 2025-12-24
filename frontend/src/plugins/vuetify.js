import "vuetify/styles";
import "@mdi/font/css/materialdesignicons.css";
import { createVuetify } from "vuetify";
import * as components from "vuetify/components";
import * as directives from "vuetify/directives";

// Construction company theme
const customTheme = {
  dark: false,
  colors: {
    primary: "#1976D2", // Blue
    secondary: "#424242", // Dark gray for construction
    accent: "#FF9800", // Orange for construction warnings
    error: "#FF5252",
    info: "#2196F3",
    success: "#4CAF50",
    warning: "#FFC107",
    background: "#F5F5F5",
    surface: "#FFFFFF",
  },
};

export default createVuetify({
  components,
  directives,
  theme: {
    defaultTheme: "customTheme",
    themes: {
      customTheme,
    },
  },
  defaults: {
    VBtn: {
      elevation: 0,
      rounded: "md",
    },
    VCard: {
      elevation: 1,
      rounded: "lg",
    },
    VTextField: {
      variant: "outlined",
      density: "comfortable",
    },
    VSelect: {
      variant: "outlined",
      density: "comfortable",
    },
    VAutocomplete: {
      variant: "outlined",
      density: "comfortable",
    },
  },
});
