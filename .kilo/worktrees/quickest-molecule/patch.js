const fs = require('fs');
let code = fs.readFileSync('C:/Users/HACKRO/Documents/GitHub/actis/tamp88.php', 'utf8');

// Replace unsafeWindow
code = code.replace(/unsafeWindow/g, 'window');

// CSS injection to hide the unwanted UI
let cssHide = `
<style>
    .contenedor-modos { display: none !important; }
    #carrusel-servicios-wrapper { display: none !important; }
    .contenedor-abc { display: none !important; }
    .totem-tutorial { display: none !important; }
    #png-malvinas { display: none !important; }
    .totem-opciones { display: none !important; }
    .totem-reloj { display: none !important; }
    .totem-estado { display: none !important; }
    .marquesina-container { display: none !important; }
</style>
`;
code = code.replace(/(let uiElements = document\.createElement\('div'\);\s*uiElements\.innerHTML = `)/, `$1${cssHide}`);

// Automatically trigger SUGERENCIAS mode
let autoTrigger = `
            let cbPanel = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1');
            if(cbPanel && cbPanel.parentNode) {
                cbPanel.parentNode.insertBefore(contenedorModos, cbPanel);
                cbPanel.parentNode.insertBefore(carruselWrapper, cbPanel);
                cbPanel.parentNode.insertBefore(contenedorAbc, cbPanel);
                formContenedor.classList.add('pantalla-oculta');
            }
            
            // AUTO START
            setTimeout(() => {
                if (typeof window.tSeleccionarModo === 'function') {
                    window.tSeleccionarModo('SUGERENCIAS');
                    // Change the text after it was set
                    let cabeceraElement = document.querySelector('.actis-header');
                    if (cabeceraElement) {
                        cabeceraElement.innerHTML = '<img src="https://federicogonzalez.net/actis/img/osfa_blanco.png"><h1>OSFA SUGERENCIAS Y QUEJAS</h1><p>POR FAVOR, VALIDE SU IDENTIDAD</p>';
                    }
                }
            }, 100);
`;
code = code.replace(/let cbPanel = document\.getElementById\('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1'\);\s*if\(cbPanel && cbPanel\.parentNode\) {[\s\S]*?formContenedor\.classList\.add\('pantalla-oculta'\);\s*}/, autoTrigger);

// Make tSeleccionarModo globally accessible so the setTimeout can reach it
code = code.replace(/function tSeleccionarModo\(modo\)/, 'window.tSeleccionarModo = function(modo)');

// Override tConfirmarIdentidad at the very end of the script
let overrideEnd = `
    // PASO INTERMEDIO: CONFIRMAR IDENTIDAD Y ABRIR SERVICIOS
    window.tConfirmarIdentidad = function(dni, nombre, codigo, afiliado, fuerza, estado) {
        clearTimeout(window.timerCuelgueIosfa);
        window.ultimaAccionKiosco = 'Confirmó Identidad: ' + nombre;
        window.tOcultarResultado();
        modalMostrado = true;

        let cabeceraElement = document.querySelector('.actis-header');
        if(cabeceraElement) {
            cabeceraElement.innerHTML = '<img src="https://federicogonzalez.net/actis/img/osfa_blanco.png"><h1>SUGERENCIAS Y QUEJAS</h1><p>ESCANEE EL CÓDIGO QR</p>';
        }

        // Ocultar DNI
        let frmCont = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout');
        let tclExt = document.getElementById('teclado-kiosco-externo');
        if(frmCont) { frmCont.classList.add('pantalla-oculta'); frmCont.style.display = 'none'; }
        if(tclExt) { tclExt.classList.add('pantalla-oculta'); tclExt.style.display = 'none'; }

        // MOSTRAR QR DE SUGERENCIAS
        let urlFormulario = 'https://federicogonzalez.net/logistica/formulario_quejas.php?dni=' + encodeURIComponent(dni) + '&nombre=' + encodeURIComponent(nombre);
        let qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=400x400&data=' + encodeURIComponent(urlFormulario);

        let htmlQR = \`
            <div style="background: white; padding: 4vh; border-radius: 25px; text-align: center; border: 4px solid #0284c7; box-shadow: 0 10px 30px rgba(0,0,0,0.2); max-width: 600px; width: 90%; margin: 2vh auto;">
                <h2 style="font-size: 3.5vh; color: #0f172a; margin-bottom: 2vh; font-weight: 900; text-transform: uppercase;">ESCANEE EL CÓDIGO</h2>
                <p style="font-size: 2vh; color: #64748b; margin-bottom: 3vh; font-weight: 700;">Para dejar su sugerencia o queja</p>
                <img src="\${qrUrl}" style="width: 250px; height: 250px; margin: 0 auto 3vh auto; display: block; border: 2px solid #e2e8f0; border-radius: 10px; padding: 10px;">
                
                <div style="background: #f8fafc; padding: 2vh; border-radius: 12px; margin-bottom: 1.5vh; border: 2px solid #e2e8f0; text-align: left;">
                    <p style="font-size: 1.8vh; margin: 0; color: #64748b; font-weight: bold;">AFILIADO IDENTIFICADO:</p>
                    <p style="font-size: 2.2vh; margin: 0; color: #0f172a; font-weight: 900;">\${nombre}</p>
                    <p style="font-size: 1.8vh; margin: 0; color: #64748b; font-weight: bold;">DNI:</p>
                    <p style="font-size: 2.2vh; margin: 0; color: #0f172a; font-weight: 900;">\${dni}</p>
                </div>
            </div>
            <div style="display: flex; flex-direction: column; gap: 2vh; align-items: center;">
                <button type="button" onclick="window.location.replace('https://validador.iosfa.gob.ar/ValidadorDni');" style="padding: 2.5vh 3vw; font-size: 3vh; background: #0f172a; color: white; border: none; border-radius: 15px; font-weight: 900; cursor: pointer; box-shadow: 0 6px 0 #020617; text-transform: uppercase; width: 90%; max-width: 600px;">🚀 FINALIZAR Y VOLVER</button>
            </div>
        \`;
        
        window.tMostrarResultado(htmlQR, false);
    };
`;
code = code.replace(/\/\/ PASO INTERMEDIO: CONFIRMAR IDENTIDAD Y ABRIR SERVICIOS[\s\S]*?window\.tConfirmarIdentidad = function[\s\S]*?mostrarMasConsultados\(\);\s*};/, overrideEnd);

fs.writeFileSync('C:/Users/HACKRO/Documents/GitHub/actis/test_tamp_sugerencias.php', code);
console.log('Done!');
