// ==UserScript==
// @name         ACTIS - Loader Turnos Dinamico
// @namespace    http://tampermonkey.net/
// @version      5.0
// @match        *://sgps.iosfa.gob.ar/*
// @match        *://validador.iosfa.gob.ar/ValidadorDni*
// @grant        GM_xmlhttpRequest
// @grant        GM_setValue
// @grant        GM_getValue
// @grant        GM_addValueChangeListener
// @connect      federicogonzalez.net
// ==/UserScript==

(function() {
    'use strict';
    
    // Le agregamos la hora actual al final de la URL para romper cualquier caché
    const scriptUrl = 'https://federicogonzalez.net/actis/tamp_turnos.php?nocache=' + new Date().getTime();
    
    GM_xmlhttpRequest({
        method: "GET",
        url: scriptUrl,
        onload: function(response) {
            try {
                // Ejecuta el código que trae de tu servidor al instante
                eval(response.responseText);
            } catch (e) {
                console.error("Error ejecutando ACTIS desde el servidor: ", e);
            }
        },
        onerror: function() {
            console.error("No se pudo conectar con el servidor de ACTIS.");
        }
    });
})();