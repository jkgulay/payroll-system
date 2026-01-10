import { defineConfig } from "vite";
import vue from "@vitejs/plugin-vue";
import vuetify from "vite-plugin-vuetify";
import { fileURLToPath, URL } from "node:url";

// Optional plugins - gracefully handle if not installed
let viteCompression = null;
let visualizer = null;

try {
  viteCompression = (await import("vite-plugin-compression")).default;
} catch (e) {
  console.warn("vite-plugin-compression not installed - compression disabled");
}

try {
  visualizer = (await import("rollup-plugin-visualizer")).visualizer;
} catch (e) {
  console.warn(
    "rollup-plugin-visualizer not installed - bundle analysis disabled"
  );
}

// https://vitejs.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    vuetify({
      autoImport: true,
      styles: {
        configFile: "src/styles/settings.scss",
      },
    }),
    // Gzip compression (optional)
    viteCompression &&
      viteCompression({
        algorithm: "gzip",
        ext: ".gz",
        threshold: 10240, // Only compress files > 10KB
        deleteOriginFile: false,
      }),
    // Brotli compression (optional)
    viteCompression &&
      viteCompression({
        algorithm: "brotliCompress",
        ext: ".br",
        threshold: 10240,
        deleteOriginFile: false,
      }),
    // Bundle analyzer (optional, only in build mode)
    visualizer &&
      visualizer({
        filename: "./dist/stats.html",
        open: false,
        gzipSize: true,
        brotliSize: true,
      }),
  ].filter(Boolean), // Remove null/false plugins
  resolve: {
    alias: {
      "@": fileURLToPath(new URL("./src", import.meta.url)),
    },
  },
  server: {
    port: 5173,
    proxy: {
      "/api": {
        target: "http://localhost:8000",
        changeOrigin: true,
      },
    },
  },
  build: {
    outDir: "dist",
    assetsDir: "assets",
    // Enable minification
    minify: "terser",
    terserOptions: {
      compress: {
        drop_console: true, // Remove console.logs in production
        drop_debugger: true,
        pure_funcs: ['console.log', 'console.info', 'console.debug'],
      },
    },
    // Optimize chunk size
    chunkSizeWarningLimit: 1000,
    rollupOptions: {
      output: {
        // Manual chunk splitting for better caching
        manualChunks: (id) => {
          // Core Vue libraries
          if (
            id.includes("node_modules/vue") ||
            id.includes("node_modules/vue-router") ||
            id.includes("node_modules/pinia")
          ) {
            return "vendor-vue";
          }
          // Vuetify components
          if (id.includes("node_modules/vuetify")) {
            return "vendor-vuetify";
          }
          // Chart libraries
          if (
            id.includes("node_modules/chart.js") ||
            id.includes("node_modules/vue-chartjs")
          ) {
            return "vendor-charts";
          }
          // PDF/Excel libraries
          if (
            id.includes("node_modules/jspdf") ||
            id.includes("node_modules/exceljs") ||
            id.includes("node_modules/xlsx")
          ) {
            return "vendor-export";
          }
          // Utilities and date handling
          if (
            id.includes("node_modules/axios") ||
            id.includes("node_modules/date-fns")
          ) {
            return "vendor-utils";
          }
          // Virtual scroller
          if (id.includes("node_modules/vue-virtual-scroller")) {
            return "vendor-virtual-scroller";
          }
          // IndexedDB (Dexie for offline)
          if (id.includes("node_modules/dexie")) {
            return "vendor-db";
          }
          // Other node_modules
          if (id.includes("node_modules")) {
            return "vendor-other";
          }
        },
        // Optimize asset file names
        chunkFileNames: "js/[name]-[hash].js",
        entryFileNames: "js/[name]-[hash].js",
        assetFileNames: (assetInfo) => {
          const info = assetInfo.name.split(".");
          const ext = info[info.length - 1];
          if (/\.(png|jpe?g|gif|svg|webp|ico)$/.test(assetInfo.name)) {
            return `images/[name]-[hash][extname]`;
          } else if (/\.(woff2?|eot|ttf|otf)$/.test(assetInfo.name)) {
            return `fonts/[name]-[hash][extname]`;
          } else if (ext === "css") {
            return `css/[name]-[hash][extname]`;
          }
          return `assets/[name]-[hash][extname]`;
        },
      },
    },
    // Enable CSS code splitting
    cssCodeSplit: true,
    // Source maps only for errors
    sourcemap: false,
  },
  // CSS preprocessor options
  css: {
    preprocessorOptions: {
      scss: {
        api: "modern-compiler", // Use modern Sass API
      },
    },
  },
  // Optimize dependencies
  optimizeDeps: {
    include: [
      "vue",
      "vue-router",
      "pinia",
      "axios",
      "date-fns",
      "vue-virtual-scroller",
    ],
    exclude: ["vue-demi"],
  },
});
