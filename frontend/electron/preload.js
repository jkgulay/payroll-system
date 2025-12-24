const { contextBridge, ipcRenderer } = require("electron");

// Expose protected methods that allow the renderer process to use
// the ipcRenderer without exposing the entire object
contextBridge.exposeInMainWorld("electron", {
  // App info
  getAppVersion: () => ipcRenderer.invoke("app-version"),
  getAppPath: (name) => ipcRenderer.invoke("app-path", name),
  isOnline: () => ipcRenderer.invoke("is-online"),

  // File operations
  showSaveDialog: (options) => ipcRenderer.invoke("show-save-dialog", options),
  showOpenDialog: (options) => ipcRenderer.invoke("show-open-dialog", options),
  writeFile: (filePath, data) =>
    ipcRenderer.invoke("write-file", filePath, data),
  readFile: (filePath) => ipcRenderer.invoke("read-file", filePath),

  // Print operations
  printPDF: (options) => ipcRenderer.invoke("print-pdf", options),

  // Platform info
  platform: process.platform,
  isElectron: true,
});

// Window controls (optional)
contextBridge.exposeInMainWorld("windowControls", {
  minimize: () => ipcRenderer.send("minimize-window"),
  maximize: () => ipcRenderer.send("maximize-window"),
  close: () => ipcRenderer.send("close-window"),
});
