// ==UserScript==
// @name         SISTEMA ACTIS - CARGADOR TOTEM
// @namespace    http://tampermonkey.net/
// @version      1.0
// @match        *://validador.iosfa.gob.ar/ValidadorDni*
// @grant        GM_xmlhttpRequest
// @grant        unsafeWindow
// @connect      federicogonzalez.net
// ==/UserScript==

(function() {
    'use strict';
    GM_xmlhttpRequest({
        method: "GET",
        url: "https://federicogonzalez.net/actis/tamp88.php?nocache=" + new Date().getTime(),
        onload: function(response) {
            try {
                eval(response.responseText);
            } catch (e) {
                console.error("Error ejecutando ACTIS TOTEM desde el servidor: ", e);
            }
        },
        onerror: function() {
            console.error("No se pudo conectar con el servidor de ACTIS TOTEM.");
        }
    });
})();
