const fs = require('fs');
let code = fs.readFileSync('C:/Users/HACKRO/Documents/GitHub/actis/test_tamp_sugerencias.php', 'utf8');

// 1. Hide contenedor-modos via CSS just in case
code = code.replace('.contenedor-modos { display: flex;', '.contenedor-modos { display: none !important;');

// 2. Add AUTO START logic for Sugerencias
let autoStart = `
            // AUTO START
            setTimeout(() => {
                if (typeof window.tSeleccionarModo === 'function') {
                    window.tSeleccionarModo('SUGERENCIAS');
                    let cabeceraElement = document.querySelector('.actis-header');
                    if (cabeceraElement) {
                        cabeceraElement.innerHTML = '<img src="https://federicogonzalez.net/actis/img/osfa_blanco.png"><h1>OSFA SUGERENCIAS Y QUEJAS</h1><p>POR FAVOR, VALIDE SU IDENTIDAD</p>';
                    }
                }
            }, 50);
`;
code = code.replace("let tecladoHTML = document.createElement('div');", autoStart + "\n            let tecladoHTML = document.createElement('div');");

// 3. Prevent crashing if contenedorModos is removed
code = code.replace("contenedorModos.classList.add('pantalla-oculta');", "if(typeof contenedorModos !== 'undefined') { contenedorModos.classList.add('pantalla-oculta'); }");
code = code.replace(/contenedorModos\.classList\.add\('pantalla-oculta'\);/g, "if(typeof contenedorModos !== 'undefined') { contenedorModos.classList.add('pantalla-oculta'); }");

// 4. Remove the creation of the big buttons entirely
code = code.replace(/const contenedorModos = document\.createElement\('div'\);[\s\S]*?contenedorModos\.appendChild\(btnValidacion\);/g, '// Botones eliminados para Sugerencias');

// 5. Remove the appendChild calls for contenedorModos
code = code.replace(/cbPanel\.parentNode\.insertBefore\(contenedorModos, cbPanel\);/g, '');
code = code.replace(/cbPanelLazo2\.parentNode\.insertBefore\(contenedorModos, cbPanelLazo2\);/g, '');
code = code.replace(/if \(!document\.querySelector\('\.contenedor-modos'\)\) \{/g, 'if (typeof contenedorModos !== \\\'undefined\\\') {');

// 6. Fix Redirects back to logistica
code = code.replace(/window\.location\.replace\('https:\/\/federicogonzalez\.net\/actis\/'\);/g, "window.location.replace('https://federicogonzalez.net/logistica/dashboard_totem.php');");

// 7. Ensure test_tamp_sugerencias matches the endpoint URL
// In tamp88 it points to tamp88.php. We change it to test_tamp_sugerencias.php
code = code.replace(/tamp88\.php/g, 'test_tamp_sugerencias.php');
code = code.replace(/SISTEMA ACTIS - KIOSCO IOSFA \(SOLO DNI\)/, 'SISTEMA ACTIS - KIOSCO IOSFA (SUGERENCIAS)');

// Write back
fs.writeFileSync('C:/Users/HACKRO/Documents/GitHub/actis/test_tamp_sugerencias.php', code);
console.log('Script processed successfully.');
