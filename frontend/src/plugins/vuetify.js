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

// Custom Professional Theme
// Using custom color palette: Navy, Orange, Peach, Light Gray
const customTheme = {
  dark: false,
  colors: {
    primary: "#ED985F", // Orange - Primary brand color
    secondary: "#F7B980", // Peach - Complementary accent
    accent: "#ED985F", // Orange - Call-to-action highlights
    error: "#ef4444", // Red - Error states
    info: "#ED985F", // Orange - Information (changed from purple/blue)
    success: "#10b981", // Emerald - Success states
    warning: "#F7B980", // Peach - Warnings
    background: "#f8fafc", // Light background
    surface: "#ffffff", // White - Surface elements
    slate: "#64748b", // Slate - Secondary text
    navy: "#001F3D", // Custom Navy - Dark elements
    orange: "#ED985F", // Custom Orange - Primary highlights
    peach: "#F7B980", // Custom Peach - Secondary highlights
    lightGray: "#E6E6E6", // Custom Light Gray
    // Legacy color aliases for backward compatibility
    steel: "#64748b", // Maps to slate
    hardhat: "#ED985F", // Maps to orange
    concrete: "#E6E6E6", // Light gray
  },
};

// Dark theme for night mode
const constructionDark = {
  dark: true,
  colors: {
    primary: "#F7B980", // Lighter peach for dark mode
    secondary: "#ED985F", // Orange
    accent: "#F7B980", // Peach
    error: "#f87171",
    info: "#F7B980",
    success: "#34d399",
    warning: "#ED985F",
    background: "#0f172a", // Slate 900
    surface: "#1e293b", // Slate 800
    slate: "#94a3b8",
    navy: "#001F3D", // Custom Navy
    orange: "#ED985F",
    peach: "#F7B980",
    lightGray: "#E6E6E6",
    // Legacy color aliases for backward compatibility
    steel: "#94a3b8", // Light slate for dark mode
    hardhat: "#ED985F", // Maps to orange
    concrete: "#E6E6E6", // Light gray
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
