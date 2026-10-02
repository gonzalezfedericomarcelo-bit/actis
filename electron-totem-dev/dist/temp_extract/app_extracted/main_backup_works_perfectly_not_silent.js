const { app, BrowserWindow, session, ipcMain } = require('electron');
const path = require('path');
const fs = require('fs');

// Almacenamiento global para simular localStorage entre dominios cruzados
const globalStore = {};
ipcMain.on('set-store', (event, key, value) => { globalStore[key] = value; });
ipcMain.on('get-store', (event, key) => { event.returnValue = globalStore[key] || null; });
ipcMain.on('remove-store', (event, key) => { delete globalStore[key]; });

// Interceptar impresión para que sea silenciosa hacia la etiquetadora
ipcMain.on('do-print', (event) => {
    const win = BrowserWindow.fromWebContents(event.sender);
    if (win) {
        win.webContents.print({ silent: false, color: false, margins: { marginType: 'printableArea' } });
    }
});

function createWindow() {
    const mainWindow = new BrowserWindow({
        kiosk: true,
        autoHideMenuBar: true,
        webPreferences: {
            preload: path.join(__dirname, 'preload.js'),
            nodeIntegration: false,
            contextIsolation: false,
            webSecurity: false
        }
    });

    const startUrl = 'https://validador.iosfa.gob.ar/ValidadorDni';
    mainWindow.loadURL(startUrl);

    mainWindow.webContents.setWindowOpenHandler(({ url }) => {
        return { 
            action: 'allow',
            overrideBrowserWindowOptions: {
                kiosk: true,
                autoHideMenuBar: true,
                webPreferences: {
                    preload: path.join(__dirname, 'preload.js'),
                    nodeIntegration: false,
                    contextIsolation: false,
                    webSecurity: false
                }
            }
        };
    });

    setInterval(() => {
        session.defaultSession.clearCache().then(() => {
            console.log('Cache cleared automatically.');
        });
    }, 1000 * 60 * 60);
}

app.commandLine.appendSwitch('ignore-certificate-errors', 'true');
app.commandLine.appendSwitch('autoplay-policy', 'no-user-gesture-required');

app.whenReady().then(() => {
    createWindow();

    app.on('activate', () => {
        if (BrowserWindow.getAllWindows().length === 0) createWindow();
    });
});

app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') app.quit();
});
