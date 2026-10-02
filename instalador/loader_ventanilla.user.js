// ==UserScript==
// @name         ACTIS - Loader Validador Dinámico
// @namespace    http://tampermonkey.net/
// @version      1.0
// @match        *://validador.iosfa.gob.ar/ValidadorDni*
// @grant        none
// ==/UserScript==

(function() {
    'use strict';
    let script = document.createElement('script');
    script.src = 'https://federicogonzalez.net/actis/tamp_ventanilla.php?t=' + new Date().getTime();
    document.head.appendChild(script);
})();
