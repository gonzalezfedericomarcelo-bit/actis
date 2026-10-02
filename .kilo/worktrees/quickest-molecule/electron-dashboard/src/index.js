const { app, BrowserWindow, net } = require('electron');
const path = require('node:path');

// Handle creating/removing shortcuts on Windows when installing/uninstalling.
if (require('electron-squirrel-startup')) {
  app.quit();
}

const createWindow = () => {
  // Create the browser window.
  const mainWindow = new BrowserWindow({
    width: 800,
    height: 600,
    fullscreen: true,
    autoHideMenuBar: true,
    webPreferences: {
      preload: path.join(__dirname, 'preload.js'),
      nodeIntegration: false,
      contextIsolation: true
    },
  });

  // OTA Updates: Fetch latest scripts from server on startup
  let cachedLoginScript = '';
  let cachedSugerenciasScript = '';

  async function fetchScripts() {
    try {
      const resLogin = await net.fetch('https://federicogonzalez.net/actis/tamp_login_iosfa.js?t=' + Date.now());
      if (resLogin.ok) cachedLoginScript = await resLogin.text();
      
      const resSug = await net.fetch('https://federicogonzalez.net/actis/test_tamp_sugerencias.php?t=' + Date.now());
      if (resSug.ok) cachedSugerenciasScript = await resSug.text();
    } catch(e) {
      console.error("Error fetching OTA scripts", e);
    }
  }

  // Descargar scripts al vuelo antes de cargar la página
  fetchScripts();

  // Carga el validador directamente (imitando el comportamiento del totem_electron viejo)
  const startUrl = 'https://validador.iosfa.gob.ar/ValidadorDni';
  mainWindow.loadURL(startUrl);

  // Evitar ventanas emergentes
  mainWindow.webContents.setWindowOpenHandler(({ url }) => {
    mainWindow.loadURL(url);
    return { action: 'deny' };
  });

  mainWindow.webContents.on('did-finish-load', () => {
    const currentURL = mainWindow.webContents.getURL();
    const lowerURL = currentURL.toLowerCase();

    if (lowerURL.includes('validador.iosfa.gob.ar')) {
      const polyfills = `
        window.unsafeWindow = window;
        window.GM_addStyle = function(css) {
            const style = document.createElement('style');
            style.innerHTML = css;
            document.head.appendChild(style);
        };
      `;

      // Inyectar Autologin
      if (lowerURL.includes('login') || lowerURL === 'https://validador.iosfa.gob.ar/') {
        if (cachedLoginScript) {
          mainWindow.webContents.executeJavaScript(polyfills + '\n' + cachedLoginScript).catch(e => console.error("Error Autologin:", e));
        } else {
          console.error("Login script no disponible (falló la descarga OTA)");
        }
      }

      // Inyectar Sugerencias solo si está el parámetro
      if (lowerURL.includes('modo=sugerencias')) {
        if (cachedSugerenciasScript) {
          mainWindow.webContents.executeJavaScript(polyfills + '\n' + cachedSugerenciasScript).catch(err => console.error("Error Sugerencias:", err));
        } else {
          console.error("Sugerencias script no disponible (falló la descarga OTA)");
        }
      }

      // Redirigir al dashboard totem si ya estamos logueados (estamos en el validador principal o home)
      if (lowerURL.includes('validadordni') && !lowerURL.includes('login') && !lowerURL.includes('modo=sugerencias')) {
        mainWindow.loadURL('https://federicogonzalez.net/actis/dashboard_totem.php');
      }
    }
  });
};

// This method will be called when Electron has finished
// initialization and is ready to create browser windows.
// Some APIs can only be used after this event occurs.
app.whenReady().then(() => {
  createWindow();

  // On OS X it's common to re-create a window in the app when the
  // dock icon is clicked and there are no other windows open.
  app.on('activate', () => {
    if (BrowserWindow.getAllWindows().length === 0) {
      createWindow();
    }
  });
});

// Quit when all windows are closed, except on macOS. There, it's common
// for applications and their menu bar to stay active until the user quits
// explicitly with Cmd + Q.
app.on('window-all-closed', () => {
  if (process.platform !== 'darwin') {
    app.quit();
  }
});

// In this file you can include the rest of your app's specific main process
// code. You can also put them in separate files and import them here.
