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

// Construction Company Theme
// Inspired by steel, concrete, safety, and industrial design
const customTheme = {
  dark: false,
  colors: {
    primary: "#D84315", // Construction Orange - Bold, industrial
    secondary: "#37474F", // Charcoal Steel - Dark gray for construction machinery
    accent: "#FFA726", // Safety Orange - High visibility
    error: "#C62828", // Safety Red - Danger/error signage
    info: "#0277BD", // Steel Blue - Professional and technical
    success: "#2E7D32", // Safety Green - Go/approved
    warning: "#F9A825", // Caution Yellow - Warning signs
    background: "#ECEFF1", // Light concrete gray
    surface: "#FFFFFF",
    steel: "#455A64", // Steel gray for structural elements
    concrete: "#90A4AE", // Concrete gray for secondary elements
    hardhat: "#FF6F00", // Hardhat orange for important highlights
    blueprint: "#1565C0", // Blueprint blue for technical elements
  },
};

// Dark theme for construction sites (optional)
const constructionDark = {
  dark: true,
  colors: {
    primary: "#FF6E40", // Bright construction orange
    secondary: "#546E7A", // Lighter steel for dark mode
    accent: "#FFB74D", // Warm safety orange
    error: "#EF5350",
    info: "#29B6F6",
    success: "#66BB6A",
    warning: "#FFCA28",
    background: "#263238", // Dark concrete
    surface: "#37474F", // Dark steel
    steel: "#607D8B",
    concrete: "#78909C",
    hardhat: "#FF9100",
    blueprint: "#42A5F5",
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
      elevation: 2,
      rounded: "lg",
      style: "border-left: 4px solid;",
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
