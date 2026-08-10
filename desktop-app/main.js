const { app, BrowserWindow, Menu, shell } = require('electron');
const path = require('path');
const fs = require('fs');

const CONFIG_PATH = path.join(__dirname, 'config.json');
const BOUNDS_PATH = path.join(app.getPath('userData'), 'window-bounds.json');

function loadConfig() {
  const defaults = { serverUrl: 'http://147.224.178.246:4080', windowTitle: 'SSD-ACMIS' };
  try {
    const raw = JSON.parse(fs.readFileSync(CONFIG_PATH, 'utf8'));
    return { ...defaults, ...raw, serverUrl: process.env.SSDACMIS_URL || raw.serverUrl || defaults.serverUrl };
  } catch {
    return { ...defaults, serverUrl: process.env.SSDACMIS_URL || defaults.serverUrl };
  }
}

function loadBounds() {
  try {
    return JSON.parse(fs.readFileSync(BOUNDS_PATH, 'utf8'));
  } catch {
    return { width: 1280, height: 800 };
  }
}

function saveBounds(win) {
  try {
    fs.writeFileSync(BOUNDS_PATH, JSON.stringify(win.getBounds()));
  } catch {
    // Non-fatal — window just won't remember its size next launch.
  }
}

const config = loadConfig();
let mainWindow = null;

// Keep school computers from ending up with five SSD-ACMIS windows open —
// a second launch just focuses the existing one instead.
const gotLock = app.requestSingleInstanceLock();
if (!gotLock) {
  app.quit();
} else {
  app.on('second-instance', () => {
    if (mainWindow) {
      if (mainWindow.isMinimized()) mainWindow.restore();
      mainWindow.focus();
    }
  });

  app.whenReady().then(createWindow);

  app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') app.quit();
  });

  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) createWindow();
  });
}

function createWindow() {
  const bounds = loadBounds();

  mainWindow = new BrowserWindow({
    ...bounds,
    minWidth: 900,
    minHeight: 600,
    title: config.windowTitle,
    icon: path.join(__dirname, 'build', 'icon-180.png'),
    webPreferences: {
      contextIsolation: true,
      nodeIntegration: false,
      sandbox: true,
    },
  });

  mainWindow.loadURL(config.serverUrl);

  // The system browser opens anything the app tries to pop as a new
  // window — printing, downloads and the app itself all stay inside
  // this one window.
  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    shell.openExternal(url);
    return { action: 'deny' };
  });

  mainWindow.webContents.on('did-fail-load', (_event, errorCode, _desc, validatedURL, isMainFrame) => {
    if (!isMainFrame || errorCode === -3) return; // -3 = ERR_ABORTED, e.g. a cancelled navigation.
    const offlinePage = `file://${path.join(__dirname, 'offline.html')}?target=${encodeURIComponent(validatedURL || config.serverUrl)}`;
    mainWindow.loadURL(offlinePage);
  });

  ['resize', 'move'].forEach((evt) => mainWindow.on(evt, () => saveBounds(mainWindow)));

  mainWindow.on('closed', () => { mainWindow = null; });

  Menu.setApplicationMenu(buildMenu());
}

function buildMenu() {
  const isMac = process.platform === 'darwin';
  const template = [
    ...(isMac ? [{ label: app.name, submenu: [{ role: 'about' }, { type: 'separator' }, { role: 'quit' }] }] : []),
    {
      label: 'File',
      submenu: [
        {
          label: 'Reload',
          accelerator: 'CmdOrCtrl+R',
          click: () => mainWindow && mainWindow.loadURL(config.serverUrl),
        },
        { type: 'separator' },
        { role: 'quit' },
      ],
    },
    {
      label: 'View',
      submenu: [
        { role: 'zoomIn' },
        { role: 'zoomOut' },
        { role: 'resetZoom' },
        { type: 'separator' },
        { role: 'togglefullscreen' },
      ],
    },
    {
      label: 'Help',
      submenu: [
        {
          label: 'Connected server',
          click: () => shell.openExternal(config.serverUrl),
        },
      ],
    },
  ];
  return Menu.buildFromTemplate(template);
}
