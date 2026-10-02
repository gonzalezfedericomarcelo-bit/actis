const { app, BrowserWindow, ipcMain, shell, session } = require('electron');
const path = require('path');

let mainWindow;

// ── PERSISTENCIA DE SESIÓN ──────────────────────────────────────────────────
// Convierte cookies de sesión (sin expiración) en cookies persistentes de 90 días.
// Esto permite que el login del admin ACTIS sobreviva reinicios de la aplicación.
function habilitarPersistenciaDeSesion() {
    const NOVENTA_DIAS = 90 * 24 * 60 * 60; // segundos

    session.defaultSession.cookies.on('changed', (event, cookie, cause, removed) => {
        // Solo actuar cuando se AGREGA una cookie (no cuando se elimina)
        if (removed) return;
        // Solo cookies sin expiración explícita (= session cookies)
        if (cookie.expirationDate) return;

        // Reconstruir la URL a partir del dominio de la cookie
        const dominio = cookie.domain.startsWith('.') ? cookie.domain.slice(1) : cookie.domain;
        const url = `${cookie.secure ? 'https' : 'http'}://${dominio}${cookie.path || '/'}`;

        const cookiePersistente = {
            url,
            name:           cookie.name,
            value:          cookie.value,
            domain:         cookie.domain,
            path:           cookie.path  || '/',
            secure:         !!cookie.secure,
            httpOnly:       !!cookie.httpOnly,
            sameSite:       cookie.sameSite || 'no_restriction',
            expirationDate: Math.floor(Date.now() / 1000) + NOVENTA_DIAS
        };

        session.defaultSession.cookies.set(cookiePersistente)
            .catch(() => {}); // silencioso si falla (ej: cookie inmutable)
    });

    console.log('ACTIS ELECTRON: Persistencia de sesión activada (90 días).');
}


function createWindow() {
    mainWindow = new BrowserWindow({
        width: 1024,
        height: 768,
        kiosk: true, // Pantalla completa absoluta
        fullscreen: true,
        autoHideMenuBar: true,
        webPreferences: {
            nodeIntegration: false,
            contextIsolation: true,
            preload: path.join(__dirname, 'preload.js'),
            nativeWindowOpen: true,
            webSecurity: false // Bypass CORS/CSP para poder inyectar scripts externos como Tampermonkey
        }
    });

    // Cargar la página inicial oficial de IOSFA (donde actúa el autologin y luego tamp88)
    mainWindow.loadURL('https://validador.iosfa.gob.ar/');

    // (did-fail-load removido para evitar bucles de recarga)

    // Interceptar la apertura de nuevas ventanas
    mainWindow.webContents.setWindowOpenHandler(({ url }) => {
        return { 
            action: 'allow', 
            overrideBrowserWindowOptions: {
                kiosk: true,
                fullscreen: true,
                autoHideMenuBar: true,
                parent: mainWindow,
                modal: true,
                webPreferences: {
                    nodeIntegration: false,
                    contextIsolation: true,
                    preload: path.join(__dirname, 'preload.js'),
                    webSecurity: false
                }
            }
        };
    });

    // Interceptar navegaciones indeseadas (como /LoNuevo después del login)
    mainWindow.webContents.on('will-navigate', (event, url) => {
        if (url.includes('/LoNuevo')) {
            event.preventDefault();
            mainWindow.loadURL('https://validador.iosfa.gob.ar/ValidadorDni');
        }
    });

    // También interceptar redirecciones automáticas del servidor
    mainWindow.webContents.on('did-redirect-navigation', (event, url) => {
        if (url.includes('/LoNuevo')) {
            event.preventDefault();
            mainWindow.loadURL('https://validador.iosfa.gob.ar/ValidadorDni');
        }
    });
}

app.whenReady().then(() => {
    habilitarPersistenciaDeSesion(); // ← Activar persistencia ANTES de abrir ventana
    createWindow();

    app.on('activate', function () {
        if (BrowserWindow.getAllWindows().length === 0) createWindow();
    });
});

app.on('window-all-closed', function () {
    if (process.platform !== 'darwin') app.quit();
});

ipcMain.handle('get-printers', async (event) => {
    const win = BrowserWindow.fromWebContents(event.sender);
    return await win.webContents.getPrintersAsync();
});

ipcMain.on('reboot-machine', () => {
    console.log("ACTIS ELECTRON: Reiniciando la PC por orden del servidor...");
    require('child_process').exec('shutdown /r /f /t 0', (error) => {
        if (error) console.error("Error al reiniciar la PC:", error);
    });
});

ipcMain.on('print-current-page', async (event, dynamicHeight) => {
    const win = BrowserWindow.fromWebContents(event.sender);
    try {
        let h = dynamicHeight && dynamicHeight > 0 ? dynamicHeight : 240000;
        await win.webContents.print({ 
            silent: true, 
            color: false,
            pageSize: { width: 80000, height: h },
            margins: { marginType: 'printableArea' }
        });
        console.log("ACTIS ELECTRON: Impresión enviada correctamente a la impresora predeterminada. Altura dinámica:", h);
    } catch (err) {
        console.error("ACTIS ELECTRON: Error al imprimir:", err);
    }
});
