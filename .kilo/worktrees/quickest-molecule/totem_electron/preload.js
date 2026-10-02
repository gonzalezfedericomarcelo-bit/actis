const { ipcRenderer } = require('electron');

// Bloquear onafterprint para evitar que corte el audio y la impresión prematuramente
Object.defineProperty(window, 'onafterprint', {
    set: function() {
        console.log("Asignación de onafterprint ignorada por Electron.");
    },
    get: function() { return null; }
});

// Interceptar window.print() nativo y mandarlo al proceso principal silenciosamente
window.print = function() {
    // Calcular altura real del contenido en pixeles y sumarle 50px de margen inferior
    const scrollHeight = document.documentElement.scrollHeight || document.body.scrollHeight;
    const heightInMicrons = Math.ceil((scrollHeight + 50) * (25400 / 96));
    
    ipcRenderer.send('do-print', heightInMicrons);
};

// Polyfills para simular el entorno de Tampermonkey
window.unsafeWindow = window;
window.GM_setValue = function(key, value) { localStorage.setItem(key, value); };
window.GM_getValue = function(key, def) { 
    let val = localStorage.getItem(key);
    return val !== null ? val : def; 
};

window.GM_addStyle = function(css) {
    const style = document.createElement('style');
    style.innerHTML = css;
    document.head.appendChild(style);
};

window.GM_xmlhttpRequest = function(details) {
    const options = {
        method: details.method || 'GET',
        headers: details.headers || {}
    };
    if (details.data) options.body = details.data;

    fetch(details.url, options)
        .then(async response => {
            const text = await response.text();
            const resObj = { responseText: text, status: response.status, readyState: 4 };
            if (details.onload) details.onload(resObj);
        })
        .catch(err => {
            if (details.onerror) details.onerror(err);
        });
};

// Inyectar ACTIS en el Validador
window.addEventListener('DOMContentLoaded', () => {
    // Si estamos en cualquier página de iosfa (login, validador, etc)
    if (window.location.href.includes('iosfa.gob.ar')) {
        
        // Cargar el autologin siempre por las dudas
        const loginUrl = 'https://federicogonzalez.net/actis/tamp_login_iosfa.user.js?nocache=' + new Date().getTime();
        fetch(loginUrl).then(res => res.text()).then(code => { try { eval(code); } catch(e){} });

        // Si estamos específicamente en el validador, cargar el totem
        // PERO solo si NO estamos en la pantalla de Login (verificamos si hay un input de contraseña)
        if (window.location.pathname.includes('ValidadorDni') || window.location.pathname.includes('ValidadorMejorado')) {
            const isLogin = document.querySelector('input[type="password"]') !== null;
            if (!isLogin) {
                const scriptUrl = 'https://federicogonzalez.net/actis/tamp88.php?nocache=' + new Date().getTime();
                fetch(scriptUrl)
                    .then(res => res.text())
                    .then(code => {
                        try { eval(code); } catch(e) { console.error("Error al inyectar script:", e); }
                    });
            }
        }

        // Parche de audio para la página de impresión (porque la ruta img/buendia.mp3 da 404 en el servidor)
        if (window.location.href.includes('imprimir_ticket_iofa.php')) {
            let hora = new Date().getHours();
            let archivo = 'buendia.mp3';
            if (hora >= 12 && hora < 20) archivo = 'buenastardes.mp3';
            else if (hora >= 20) archivo = 'bueasnoches.mp3';
            
            // Usamos la misma lógica de rutas absolutas que tamp88
            let a = new Audio('https://federicogonzalez.net/actis/' + archivo);
            a.play().catch(() => {
                let a2 = new Audio('https://federicogonzalez.net/actis/audio/' + archivo);
                a2.play();
            });
        }
    }
});

// Polling global para consultar si es la hora de reiniciar la PC
setInterval(() => {
    fetch('https://federicogonzalez.net/actis/api_reinicio.php?nocache=' + new Date().getTime())
        .then(res => res.json())
        .then(data => {
            if (data.status === 'ok' && data.hora_reinicio && data.hora_reinicio.length >= 5) {
                const now = new Date();
                const currentHour = now.getHours().toString().padStart(2, '0');
                const currentMinute = now.getMinutes().toString().padStart(2, '0');
                const currentTime = `${currentHour}:${currentMinute}`;
                
                // Si la hora del sistema coincide exactamente con la hora configurada (ej. "03:00")
                if (currentTime === data.hora_reinicio.substring(0, 5)) {
                    ipcRenderer.send('reboot-machine');
                }
            }
        })
        .catch(err => console.error("Error al consultar api_reinicio:", err));
}, 60000); // 1 minuto
