const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('electronAPI', {
    printCurrentPage: (h) => ipcRenderer.send('print-current-page', h),
    rebootMachine: () => ipcRenderer.send('reboot-machine')
});

document.addEventListener('DOMContentLoaded', () => {
    // 1. LOGICA DE IMPRESIÓN SILENCIOSA GLOBAL
    // Sobreescribimos window.print() para que cualquier página que intente imprimir 
    // le avise a Electron que imprima el webContents actual silenciosamente.
    const scriptEl = document.createElement('script');
    scriptEl.textContent = `
        window.print = function() {
            window.onafterprint = null;
            Object.defineProperty(window, 'onafterprint', { set: function() {}, get: function() { return null; } });
            
            const scrollHeight = document.documentElement.scrollHeight || document.body.scrollHeight;
            let h = Math.ceil((scrollHeight + 50) * (25400 / 96));
            if (h < 100000) h = 100000;
            
            window.electronAPI.printCurrentPage(h);
        };
    `;
    document.documentElement.appendChild(scriptEl);

    const url = window.location.href;

    // 2. INYECCIÓN DEL VALIDADOR (tamp88) Y AUTOLOGIN (tamp_login_iosfa)
    if (url.includes('validador.iosfa.gob.ar')) {
        
        // REDIRECCIÓN FORZADA: Si el servidor nos manda a "LoNuevo" o al root después del login, forzamos ValidadorDni
        const urlLower = url.toLowerCase();
        if (urlLower.includes('lonuevo') || urlLower === 'https://validador.iosfa.gob.ar/' || urlLower === 'https://validador.iosfa.gob.ar/default.aspx') {
            window.location.replace('https://validador.iosfa.gob.ar/ValidadorDni');
            return; // Detenemos la ejecución en esta página porque ya nos estamos yendo
        }

        // Polyfill para emular funciones de Tampermonkey
        const gmPolyfill = document.createElement('script');
        gmPolyfill.textContent = `
            // Polyfill para unsafeWindow (en el main world, unsafeWindow es simplemente window)
            window.unsafeWindow = window;

            // Polyfill GM_xmlhttpRequest para tamp88.php
            window.GM_xmlhttpRequest = function(details) {
                fetch(details.url, {
                    method: details.method || "GET",
                    headers: details.headers || {},
                    body: details.data || null
                })
                .then(r => r.text())
                .then(t => { if(details.onload) details.onload({ responseText: t }); })
                .catch(e => { if(details.onerror) details.onerror(e); });
            };

            // Polyfill GM_addStyle para tamp_login_iosfa.user.js
            window.GM_addStyle = function(css) {
                const style = document.createElement('style');
                style.textContent = css;
                document.head.appendChild(style);
            };
        `;
        document.documentElement.appendChild(gmPolyfill);

        const pathName = window.location.pathname.toLowerCase();

        // A) Autologin optimizado para Ventanilla (sin teclado virtual)
        if (pathName === '/' || pathName.includes('login.aspx')) {
            fetch('https://federicogonzalez.net/actis/tamp_login_ventanilla.js?t=' + new Date().getTime())
                .then(res => res.text())
                .then(scriptText => {
                    const loginEl = document.createElement('script');
                    loginEl.textContent = scriptText;
                    document.documentElement.appendChild(loginEl);
                    console.log("ACTIS ELECTRON: tamp_login_ventanilla inyectado.");
                })
                .catch(err => console.error("Error cargando tamp_login_ventanilla:", err));
        }

        // B) Inyector de test_tamp_ventanilla.php (El core de Ventanilla DEV)
        // Usamos pathName para ignorar query strings como ?ReturnUrl=%2fValidadorDni en el login
        if (pathName.includes('validadordni')) {
            fetch('https://federicogonzalez.net/actis/test_tamp_ventanilla.php?t=' + new Date().getTime())
                .then(res => res.text())
                .then(scriptText => {
                    const tampEl = document.createElement('script');
                    tampEl.textContent = scriptText;
                    document.documentElement.appendChild(tampEl);
                    console.log("ACTIS ELECTRON: test_tamp_ventanilla.php inyectado correctamente.");
                })
                .catch(err => console.error("Error cargando test_tamp_ventanilla.php:", err));
        }
    }
});

// Polling global para consultar si es la hora de reiniciar la PC o si hay reinicio forzado
setInterval(() => {
    fetch('https://federicogonzalez.net/actis/api_reinicio.php?nocache=' + new Date().getTime())
        .then(res => res.json())
        .then(data => {
            if (data.status === 'ok') {
                if (data.reinicio_forzado) {
                    console.log("ACTIS ELECTRON: Reinicio forzado detectado desde el Admin.");
                    window.electronAPI.rebootMachine();
                    return;
                }
                if (data.hora_reinicio && data.hora_reinicio.length >= 5) {
                    const now = new Date();
                    const currentHour = now.getHours().toString().padStart(2, '0');
                    const currentMinute = now.getMinutes().toString().padStart(2, '0');
                    const currentTime = `${currentHour}:${currentMinute}`;
                    
                    if (currentTime === data.hora_reinicio.substring(0, 5)) {
                        console.log("ACTIS ELECTRON: Es la hora de reinicio programado.");
                        window.electronAPI.rebootMachine();
                    }
                }
            }
        })
        .catch(err => console.error("Error al consultar api_reinicio:", err));
}, 60000); // 1 minuto
