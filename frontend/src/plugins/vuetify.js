import "vuetify/styles";
import "@mdi/font/css/materialdesignicons.css";
import { createVuetify } from "vuetify";

// Tree-shake: Import only the components you need
import {
  VApp,
  VMain,
  VContainer,
  VRow,
  VCol,
  VCard,
  VCardText,
  VCardTitle,
  VCardActions,
  VBtn,
  VIcon,
  VTextField,
  VSelect,
  VAutocomplete,
  VCheckbox,
  VRadio,
  VRadioGroup,
  VSwitch,
  VTextarea,
  VDataTable,
  VDataTableServer,
  VAppBar,
  VNavigationDrawer,
  VList,
  VListItem,
  VListItemTitle,
  VListItemSubtitle,
  VMenu,
  VDialog,
  VTabs,
  VTab,
  VTabsWindow,
  VTabsWindowItem,
  VChip,
  VAvatar,
  VAlert,
  VProgressCircular,
  VProgressLinear,
  VDivider,
  VTooltip,
  VBadge,
  VExpansionPanels,
  VExpansionPanel,
  VExpansionPanelTitle,
  VExpansionPanelText,
  VForm,
  VDatePicker,
  VTimePicker,
  VSnackbar,
  VToolbar,
  VToolbarTitle,
  VSpacer,
  VBreadcrumbs,
  VBreadcrumbsItem,
  VBreadcrumbsDivider,
  VPagination,
  VFileInput,
  VRating,
  VSlider,
  VRangeSlider,
  VCombobox,
  VBanner,
  VBottomNavigation,
  VBottomSheet,
  VCarousel,
  VCarouselItem,
  VHover,
  VImg,
  VOverlay,
  VSkeletonLoader,
  VSpeedDial,
  VStepper,
  VStepperHeader,
  VStepperItem,
  VTable,
  VTimeline,
  VTimelineItem,
  VVirtualScroll,
  VWindow,
  VWindowItem,
} from "vuetify/components";

import { Intersect, Ripple, Touch } from "vuetify/directives";

// Modern Professional Theme
// Inspired by contemporary SaaS applications with vibrant gradients
const customTheme = {
  dark: false,
  colors: {
    primary: "#6366f1", // Modern Indigo - Primary brand color
    secondary: "#8b5cf6", // Purple - Complementary accent
    accent: "#ec4899", // Pink - Call-to-action highlights
    error: "#ef4444", // Red - Error states
    info: "#3b82f6", // Blue - Information
    success: "#10b981", // Emerald - Success states
    warning: "#f59e0b", // Amber - Warnings
    background: "#f8fafc", // Slate 50 - Background
    surface: "#ffffff", // White - Surface elements
    slate: "#64748b", // Slate - Secondary text
    navy: "#1e293b", // Navy - Dark elements
    indigo: "#6366f1", // Indigo - Primary highlights
    violet: "#8b5cf6", // Violet - Secondary highlights
    // Legacy color aliases for backward compatibility
    steel: "#64748b", // Maps to slate
    hardhat: "#ec4899", // Maps to accent/pink
    concrete: "#94a3b8", // Light slate
  },
};

// Dark theme for night mode
const constructionDark = {
  dark: true,
  colors: {
    primary: "#818cf8", // Lighter indigo for dark mode
    secondary: "#a78bfa", // Lighter purple
    accent: "#f472b6", // Lighter pink
    error: "#f87171",
    info: "#60a5fa",
    success: "#34d399",
    warning: "#fbbf24",
    background: "#0f172a", // Slate 900
    surface: "#1e293b", // Slate 800
    slate: "#94a3b8",
    navy: "#0f172a",
    indigo: "#818cf8",
    violet: "#a78bfa",
    // Legacy color aliases for backward compatibility
    steel: "#94a3b8", // Light slate for dark mode
    hardhat: "#f472b6", // Maps to accent/pink
    concrete: "#cbd5e1", // Lighter slate
  },
};

export default createVuetify({
  components: {
    VApp,
    VMain,
    VContainer,
    VRow,
    VCol,
    VCard,
    VCardText,
    VCardTitle,
    VCardActions,
    VBtn,
    VIcon,
    VTextField,
    VSelect,
    VAutocomplete,
    VCheckbox,
    VRadio,
    VRadioGroup,
    VSwitch,
    VTextarea,
    VDataTable,
    VDataTableServer,
    VAppBar,
    VNavigationDrawer,
    VList,
    VListItem,
    VListItemTitle,
    VListItemSubtitle,
    VMenu,
    VDialog,
    VTabs,
    VTab,
    VTabsWindow,
    VTabsWindowItem,
    VChip,
    VAvatar,
    VAlert,
    VProgressCircular,
    VProgressLinear,
    VDivider,
    VTooltip,
    VBadge,
    VExpansionPanels,
    VExpansionPanel,
    VExpansionPanelTitle,
    VExpansionPanelText,
    VForm,
    VDatePicker,
    VTimePicker,
    VSnackbar,
    VToolbar,
    VToolbarTitle,
    VSpacer,
    VBreadcrumbs,
    VBreadcrumbsItem,
    VBreadcrumbsDivider,
    VPagination,
    VFileInput,
    VRating,
    VSlider,
    VRangeSlider,
    VCombobox,
    VBanner,
    VBottomNavigation,
    VBottomSheet,
    VCarousel,
    VCarouselItem,
    VHover,
    VImg,
    VOverlay,
    VSkeletonLoader,
    VSpeedDial,
    VStepper,
    VStepperHeader,
    VStepperItem,
    VTable,
    VTimeline,
    VTimelineItem,
    VVirtualScroll,
    VWindow,
    VWindowItem,
  },
  directives: {
    Intersect,
    Ripple,
    Touch,
  },
  theme: {
    defaultTheme: "customTheme",
    themes: {
      customTheme,
      constructionDark,
    },
  },
  defaults: {
    VBtn: {
      elevation: 2,
      rounded: "md",
      style: "text-transform: none; font-weight: 600;",
    },
    VCard: {
      elevation: 0,
      rounded: "lg",
      style: "border: 1px solid rgba(0, 31, 61, 0.08); background: #ffffff;",
    },
    VTextField: {
      variant: "outlined",
      density: "comfortable",
      color: "primary",
    },
    VSelect: {
      variant: "outlined",
      density: "comfortable",
      color: "primary",
    },
    VAutocomplete: {
      variant: "outlined",
      density: "comfortable",
      color: "primary",
    },
    VAppBar: {
      elevation: 2,
    },
    VNavigationDrawer: {
      elevation: 2,
    },
  },
});
