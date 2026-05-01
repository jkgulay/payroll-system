import { createApp } from "vue";
import { createPinia } from "pinia";
import App from "./App.vue";
import router from "./router";
import vuetify from "./plugins/vuetify";
import Toast from "vue-toastification";
import "vue-toastification/dist/index.css";

// Import global styles
import "./styles/main.scss";
import "./styles/modern-forms.css";

// Import service worker registration
import { registerServiceWorker, setupOnlineListener } from "./utils/serviceWorkerManager";

const app = createApp(App);

// Use plugins
app.use(createPinia());
app.use(router);
app.use(vuetify);
app.use(Toast, {
  position: "top-right",
  timeout: 3000,
  closeOnClick: true,
  pauseOnFocusLoss: true,
  pauseOnHover: true,
  draggable: true,
  draggablePercent: 0.6,
  showCloseButtonOnHover: false,
  hideProgressBar: false,
  closeButton: "button",
  icon: true,
  rtl: false,
});

app.mount("#app");

// Register service worker for offline support
registerServiceWorker();

// Setup online/offline listeners
setupOnlineListener(
  () => {
    // When back online
    const toast = app.config.globalProperties.$toast;
    if (toast) {
      toast.success("You are back online!");
    }
  },
  () => {
    // When offline
    const toast = app.config.globalProperties.$toast;
    if (toast) {
      toast.warning("You are offline. Some features may be unavailable.", {
        timeout: 5000,
      });
    }
  }
);
