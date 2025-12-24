const { app, BrowserWindow, ipcMain } = require("electron");
const path = require("path");
const isDev = process.env.NODE_ENV !== "production";

let mainWindow;

function createWindow() {
  mainWindow = new BrowserWindow({
    width: 1400,
    height: 900,
    minWidth: 1024,
    minHeight: 768,
    webPreferences: {
      preload: path.join(__dirname, "preload.js"),
      contextIsolation: true,
      nodeIntegration: false,
      enableRemoteModule: false,
    },
    icon: path.join(__dirname, "../public/icon.png"),
    title: "Construction Payroll System",
    autoHideMenuBar: true,
  });

  // Load the app
  if (isDev) {
    mainWindow.loadURL("http://localhost:5173");
    mainWindow.webContents.openDevTools();
  } else {
    mainWindow.loadFile(path.join(__dirname, "../dist/index.html"));
  }

  // Handle window closed
  mainWindow.on("closed", () => {
    mainWindow = null;
  });
}

// App ready
app.whenReady().then(() => {
  createWindow();

  app.on("activate", () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      createWindow();
    }
  });
});

// Quit when all windows are closed
app.on("window-all-closed", () => {
  if (process.platform !== "darwin") {
    app.quit();
  }
});

// IPC handlers
ipcMain.handle("app-version", () => {
  return app.getVersion();
});

ipcMain.handle("app-path", (event, name) => {
  return app.getPath(name);
});

ipcMain.handle("is-online", () => {
  return require("net").isOnline();
});

// Handle print request
ipcMain.handle("print-pdf", async (event, options) => {
  const win = BrowserWindow.fromWebContents(event.sender);
  const data = await win.webContents.printToPDF(options);
  return data;
});

// Handle file dialog
ipcMain.handle("show-save-dialog", async (event, options) => {
  const { dialog } = require("electron");
  const win = BrowserWindow.fromWebContents(event.sender);
  return await dialog.showSaveDialog(win, options);
});

ipcMain.handle("show-open-dialog", async (event, options) => {
  const { dialog } = require("electron");
  const win = BrowserWindow.fromWebContents(event.sender);
  return await dialog.showOpenDialog(win, options);
});

// Handle file system operations
ipcMain.handle("write-file", async (event, filePath, data) => {
  const fs = require("fs").promises;
  await fs.writeFile(filePath, data);
  return true;
});

ipcMain.handle("read-file", async (event, filePath) => {
  const fs = require("fs").promises;
  return await fs.readFile(filePath, "utf-8");
});
