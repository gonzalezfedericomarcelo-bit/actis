<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Content-Type: application/javascript");
?>
// ==UserScript==
// @name         SISTEMA ACTIS - CALCULADORA DNI (NATIVA)
// @namespace    http://tampermonkey.net/
// @version      2.0
// @match        *://validador.iosfa.gob.ar/ValidadorDni*
// @grant        none
// ==/UserScript==

(function() {
    'use strict';
    
    // No ejecutar en la página de Login para no pisar el otro script
    if (window.location.pathname.toLowerCase().includes('login') || document.querySelector('input[type="password"]')) {
        return;
    }

    // 1. Estilos para ocultar basura y reformatear el contenedor NATIVO de DevExpress
    const estilo = document.createElement('style');
    estilo.innerHTML = `
        /* Ocultar interfaz nativa inútil y popups de DevExpress (los reemplazaremos con nuestro modal) */
        nav.navbar, footer, .dua-tbl, .badge-alertas, #ctl00_UsuarioVtoAlertas1_userAlertas, .row h3:first-of-type { display: none !important; }
        .dxeHelpText_IOSFA, .safi-hint, .red1, #ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btnImprimir { display: none !important; }
        .dxpc-mainDiv, .dxpc-shadow, .modal-backdrop, .modal-dialog { display: none !important; opacity: 0 !important; pointer-events: none !important; }

        /* OCULTAR DEFINITIVAMENTE TODO LO NATIVO DE DEVEXPRESS */
        /* Ocultamos absolutamente TODAS las filas nativas de DevExpress dentro del formulario */
        #ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout > .row,
        .dxeNullTextSys, .dxeNullText, [class*="NullText"] {
            /* NO USAR DISPLAY NONE PORQUE ROMPE LA VALIDACION NATIVA */
            opacity: 0 !important;
            pointer-events: none !important;
            height: 0 !important;
            width: 0 !important;
            overflow: hidden !important;
            position: absolute !important;
            z-index: -9999 !important;
        }
        
        /* Ocultar el panel de resultados de DevExpress (el cartel feo) enviándolo fuera de la pantalla */
        #ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_divDatosAfiliado {
            position: absolute !important;
            left: -99999px !important;
            top: -99999px !important;
            opacity: 0 !important;
            pointer-events: none !important;
            z-index: -99999 !important;
            height: 0 !important;
            overflow: hidden !important;
        }

        body { 
            background-color: #f1f5f9 !important; /* Fallback si bloquean imagenes externas */
            background-image: linear-gradient(rgba(2, 132, 199, 0.7), rgba(2, 132, 199, 0.7)), url('https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?q=80&w=2000&auto=format&fit=crop') !important;
            background-size: cover !important;
            background-position: center !important;
            margin: 0; padding: 0; display: block !important; height: 100vh; overflow: hidden; font-family: 'Outfit', sans-serif !important;
        }

        #kiosco-panel-izquierdo {
            position: fixed;
            left: 0;
            top: 0;
            width: 55vw;
            height: 100vh;
            background: #ffffff;
            padding: 40px 60px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            box-shadow: 10px 0 30px rgba(0,0,0,0.1);
            z-index: 10;
            box-sizing: border-box;
        }

        .kiosco-logo {
            max-width: 200px;
            margin-bottom: 25px;
        }

        .kiosco-title {
            font-size: 48px !important;
            font-weight: 900;
            color: #0f172a;
            line-height: 1.1;
            margin-bottom: 15px;
        }

        .kiosco-subtitle {
            font-size: 22px !important;
            color: #64748b;
            line-height: 1.4;
            margin-bottom: 25px;
            max-width: 95%;
        }

        .kiosco-steps {
            display: flex;
            flex-direction: column;
            gap: 20px;
            margin-bottom: 25px;
        }

        .k-step {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .k-step-num {
            min-width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #0284c7;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            font-weight: 900;
            box-shadow: 0 4px 15px rgba(2, 132, 199, 0.3);
        }

        .k-step-text {
            display: flex;
            flex-direction: column;
        }

        .k-step-text strong {
            font-size: 24px;
            color: #0f172a;
            margin-bottom: 5px;
        }

        .k-step-text span {
            font-size: 18px;
            color: #64748b;
        }

        .kiosco-info-box {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            padding: 15px 20px;
            border-radius: 20px;
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .kiosco-info-box i {
            color: #16a34a;
            font-size: 32px;
        }

        .kiosco-info-box p {
            margin: 0;
            font-size: 16px;
            color: #166534;
            line-height: 1.4;
        }
        
        /* Reformatear el contenedor nativo principal para usarlo como fondo de nuestra UI */
        #ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout {
            position: fixed !important;
            left: 55vw !important; /* Comienza donde termina el panel izquierdo */
            right: 0 !important;   /* Termina en el borde derecho */
            top: 0 !important;
            bottom: 0 !important;
            margin: auto !important; /* Magia para centrar absoluto horizontal y verticalmente en su espacio */
            background: #ffffff !important;
            padding: 25px 20px !important;
            border-radius: 30px !important;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1) !important;
            border: 1px solid #e2e8f0 !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: center !important; /* Mantiene todo al medio */
            width: 90% !important; /* Se ajusta a pantallas más chicas */
            max-width: 450px !important; /* Nunca más de 450px para evitar cortarse */
            height: fit-content !important;
            max-height: 95vh !important; /* Si la pantalla es bajita, no se sale */
            gap: 10px !important;
            box-sizing: border-box !important;
        }

        /* Ocultar celdas nativas que rompen el diseño (ej: campo Credencial) */
        .dxflEmptyItemSys, .dxflBottomMarginSys { opacity: 0 !important; pointer-events: none !important; position: absolute !important; }
        div[id*="Credencial"], div[id*="NroCredencial"], label[for*="Credencial"] { opacity: 0 !important; pointer-events: none !important; position: absolute !important; }
        .col-md-4, .col-lg-4 { width: 100% !important; float: none !important; padding: 0 !important; margin: 0 !important; display: block !important; height: auto !important; }

        /* Teclado Virtual (se inyectará en orden 2) */
        .teclado-kiosco { display: grid; grid-template-columns: repeat(3, 1fr); grid-template-rows: repeat(4, 1fr); gap: 10px; margin: 0 !important; width: 100%; order: 2 !important; height: auto !important; }
        .kbtn { background: #f1f5f9 !important; border: 1px solid #cbd5e1 !important; border-radius: 12px !important; font-size: 35px !important; font-weight: 900 !important; color: #0f172a !important; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.1s; height: 60px !important; box-shadow: 0 4px 0 #cbd5e1 !important; padding: 0 !important; }
        .kbtn:active { transform: translateY(4px) !important; box-shadow: 0 0 0 #cbd5e1 !important; background: #e2e8f0 !important; }
        .kbtn-borrar { background: #ef4444 !important; border-color: #ef4444 !important; color: white !important; font-size: 28px !important; box-shadow: 0 4px 0 #b91c1c !important; }
        .kbtn-borrar:active { background: #dc2626 !important; box-shadow: 0 0 0 #b91c1c !important; }
    `;
    document.head.appendChild(estilo);

    // =========================================================================
    // INYECCIÓN DE LA UI DE KIOSCO (HTML PURO, SIN DEVEXPRESS)
    // =========================================================================

    // Variables globales para nuestro input fantasma
    let inputKiosco = null;
    let btnLimpio = null;

    // Redirección al Dashboard
    const urlDashboard = 'https://federicogonzalez.net/actis/dashboard_totem.php';

    function procesarPulsacion(num) {
        let miInput = document.getElementById('kiosco-dni-input');
        if (!miInput) return;
        
        if (miInput.value.length < 9) {
            miInput.value = miInput.value + num;
        }
    }

    function procesarBorrado() {
        let miInput = document.getElementById('kiosco-dni-input');
        if (!miInput) return;
        
        if (miInput.value.length > 0) {
            miInput.value = miInput.value.slice(0, -1);
        }
    }

    // 3. Modales Custom (Libro de Quejas)
    function mostrarModalIdentidad(nombre, dni, codigo, estado) {
        try {
            let overlay = document.createElement('div');
            overlay.id = 'modal-actis-overlay';
            overlay.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.8); display:flex; align-items:center; justify-content:center; z-index:999999;';
            
            let card = document.createElement('div');
            card.style.cssText = 'background:#ffffff; padding:40px; border-radius:30px; box-shadow:0 20px 50px rgba(0,0,0,0.2); border:1px solid #e2e8f0; width:90%; max-width:500px; text-align:center; color:#0f172a; font-family:"Segoe UI", sans-serif;';
            
            if (estado === 'AFILIADO ACTIVO') {
                let innerURL = `https://federicogonzalez.net/actis/formulario_quejas.php?dni=${encodeURIComponent(dni)}&nombre=${encodeURIComponent(nombre)}&codigo=${encodeURIComponent(codigo)}&estado=${encodeURIComponent(estado)}`;
                let urlQR = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(innerURL)}`;
                
                card.innerHTML = `
                    <h2 style="color:#0284c7; margin-top:0; font-size:32px; font-weight:900;"><i class="fa-solid fa-circle-check"></i> AFILIADO IDENTIFICADO</h2>
                    <h3 style="font-size:24px; font-weight:700; margin:10px 0;">${nombre}</h3>
                    <p style="color:#64748b; font-size:18px; margin-bottom:30px; font-weight:600;">DNI: ${dni}</p>
                    <p style="font-size:16px; margin-bottom:20px; color:#0f172a; font-weight:700;">Por favor, escanee el siguiente código con su celular para abrir el Libro de Quejas y Sugerencias de la Policlínica.</p>
                    <div style="background:white; padding:20px; border-radius:15px; display:inline-block; margin-bottom:30px; border: 2px solid #e2e8f0;">
                        <img src="${urlQR}" style="width:250px; height:250px; display:block;">
                    </div>
                    <button id="btn-finalizar-qr" style="width:100%; padding:15px; background:#0284c7; color:white; border:none; border-radius:15px; font-size:20px; font-weight:900; cursor:pointer; box-shadow:0 4px 0 #0369a1; transition:0.2s;">FINALIZAR Y VOLVER</button>
                `;
                
                overlay.appendChild(card);
                document.body.appendChild(overlay);

                document.getElementById('btn-finalizar-qr').addEventListener('click', () => { window.location.href = urlDashboard; });
                setTimeout(() => { window.location.href = urlDashboard; }, 30000);
            } else {
                card.innerHTML = `
                    <h2 style="color:#ef4444; margin-top:0; font-size:32px; font-weight:900;"><i class="fa-solid fa-circle-xmark"></i> ACCESO DENEGADO</h2>
                    <p style="color:#64748b; font-size:20px; margin-bottom:30px; font-weight:600;">El DNI ingresado no figura como afiliado activo en el padrón de IOSFA o no pudo ser validado.</p>
                    <button id="btn-cancelar-modal" style="width:100%; padding:15px; background:#ef4444; color:white; border:none; border-radius:15px; font-size:20px; font-weight:900; cursor:pointer; box-shadow:0 4px 0 #b91c1c; margin-bottom:15px;">REINTENTAR</button>
                    <button id="btn-volver-dashboard" style="width:100%; padding:15px; background:#f1f5f9; color:#0f172a; border:1px solid #cbd5e1; border-radius:15px; font-size:20px; font-weight:900; cursor:pointer; box-shadow:0 4px 0 #cbd5e1;">CANCELAR Y VOLVER</button>
                `;
                overlay.appendChild(card);
                document.body.appendChild(overlay);

                document.getElementById('btn-cancelar-modal').addEventListener('click', () => {
                    document.body.removeChild(overlay);
                    if(document.getElementById('kiosco-dni-input')) { document.getElementById('kiosco-dni-input').value = ''; }
                    let btnL = document.getElementById('kiosco-btn-buscar');
                    if(btnL) { btnL.innerText = 'CONFIRMAR IDENTIDAD'; btnL.style.background = '#0284c7'; btnL.style.boxShadow = '0 4px 0 #0369a1'; btnL.disabled = false; }
                });
                document.getElementById('btn-volver-dashboard').addEventListener('click', () => { window.location.href = urlDashboard; });
                setTimeout(() => { window.location.href = urlDashboard; }, 15000);
            }
        } catch (e) {
            alert('Error mostrando modal: ' + e.message);
        }
    }

    // 4. Observador continuo para extraer los datos de DevExpress
    setInterval(() => {
        if (document.getElementById('modal-actis-overlay')) return; // Ya estamos mostrando el modal
        
        let divAfiliado = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_divDatosAfiliado_Afiliado');
        let lblMensajeNoVal = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_pnlValidacion_lblMensajeNoEsValidable');

        // CASO 1: Éxito
        if (divAfiliado && divAfiliado.innerText.trim() !== '') {
            let nombre = divAfiliado.innerText.trim();
            
            let divCodigo = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_divDatosAfiliado_Codigo');
            let divDni = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_divDatosAfiliado_Documento');
            
            let codigo = divCodigo ? divCodigo.innerText.trim() : '';
            let dni = divDni ? divDni.innerText.trim() : '';
            
            let estado = 'DESCONOCIDO';
            let headers = document.querySelectorAll('#ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_divDatosAfiliado h3');
            headers.forEach(h => {
                if (h.style.color === 'green' || h.style.color === 'red') estado = h.innerText.trim();
            });

            // Vaciamos el texto primero para no relanzar el modal múltiples veces
            divAfiliado.innerText = '';
            mostrarModalIdentidad(nombre, dni, codigo, estado);
            
            // Re-habilitar nuestro botón limpio
            let btnLimpio = document.getElementById('kiosco-btn-buscar');
            if (btnLimpio) {
                btnLimpio.innerText = 'CONFIRMAR IDENTIDAD';
                btnLimpio.disabled = false;
            }
        }
        
        // CASO 2: Error (DNI inexistente)
        if (lblMensajeNoVal && lblMensajeNoVal.innerText.includes('No se encontraron')) {
            alert('El DNI ingresado no corresponde a un afiliado activo.');
            lblMensajeNoVal.innerText = ''; // Limpiar para no repetir
            let btnCerrar = document.querySelector('.dxpc-mainDiv .dxbButtonSys');
            if (btnCerrar) btnCerrar.click();
            
            let miInput = document.getElementById('kiosco-dni-input');
            if (miInput) miInput.value = '';
            
            let btnLimpio = document.getElementById('kiosco-btn-buscar');
            if (btnLimpio) {
                btnLimpio.innerText = 'CONFIRMAR IDENTIDAD';
                btnLimpio.disabled = false;
            }
        }
    }, 500);

    // 5. Inyectar nuestra UI limpia de Kiosco y esconder DevExpress
    let waitInterval = setInterval(() => {
        const globalWin = (typeof unsafeWindow !== 'undefined') ? unsafeWindow : window;
        let formLayout = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout');
        let inputContainerNativo = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout_txtDNI');
        let btnContainerNativo = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout_2'); // El contenedor del botón buscar

        if (formLayout && inputContainerNativo && btnContainerNativo) {
            clearInterval(waitInterval);

            // 0. INYECTAR FONT AWESOME Y GOOGLE FONTS SI NO EXISTEN
            if (!document.getElementById('kiosco-fontawesome')) {
                let linkFA = document.createElement('link');
                linkFA.id = 'kiosco-fontawesome';
                linkFA.rel = 'stylesheet';
                linkFA.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css';
                document.head.appendChild(linkFA);
                
                let linkFonts = document.createElement('link');
                linkFonts.rel = 'stylesheet';
                linkFonts.href = 'https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800;900&display=swap';
                document.head.appendChild(linkFonts);
            }

            // 0.5. CREAR PANEL IZQUIERDO DE DISEÑO (SIN MOVER EL FORMULARIO NATIVO)
            if (!document.getElementById('kiosco-panel-izquierdo')) {
                let panelIzquierdo = document.createElement('div');
                panelIzquierdo.id = 'kiosco-panel-izquierdo';
                panelIzquierdo.innerHTML = `
                    <h1 class="kiosco-title">Buzón de<br><span style="color:#0284c7;">Sugerencias</span></h1>
                    <p class="kiosco-subtitle">Ayúdenos a mejorar nuestro servicio. Deje su sugerencia, queja o felicitación escaneando el código QR que se generará tras validar su DNI.</p>
                    
                    <div class="kiosco-steps">
                        <div class="k-step">
                            <div class="k-step-num">1</div>
                            <div class="k-step-text">
                                <strong>Identificación</strong>
                                <span>Ingrese su DNI en el panel numérico.</span>
                            </div>
                        </div>
                        <div class="k-step">
                            <div class="k-step-num">2</div>
                            <div class="k-step-text">
                                <strong>Validación</strong>
                                <span>Aguarde la confirmación del padrón.</span>
                            </div>
                        </div>
                        <div class="k-step">
                            <div class="k-step-num">3</div>
                            <div class="k-step-text">
                                <strong>Código QR</strong>
                                <span>Escanee el código generado con su celular.</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="kiosco-info-box">
                        <i class="fa-solid fa-circle-info"></i>
                        <div>
                            <strong>¿Por qué validamos su identidad?</strong>
                            <p>Para generar un ticket a su nombre y enviarle la respuesta o resolución formal a su correo electrónico.</p>
                        </div>
                    </div>
                `;
                document.body.appendChild(panelIzquierdo);
            }

            // 1. OCULTAR TODO LO NATIVO PERO MANTENIENDOLO EN EL DOM PARA QUE FUNCIONE DEVEXPRESS
            inputContainerNativo.style.opacity = '0';
            inputContainerNativo.style.position = 'absolute';
            inputContainerNativo.style.zIndex = '-9999';
            inputContainerNativo.style.pointerEvents = 'none';

            btnContainerNativo.style.opacity = '0';
            btnContainerNativo.style.position = 'absolute';
            btnContainerNativo.style.zIndex = '-9999';
            btnContainerNativo.style.pointerEvents = 'none';
            
            let labelNativo = document.querySelector('label[for*="txtDNI_I"]');
            if (labelNativo) {
                labelNativo.style.opacity = '0';
                labelNativo.style.position = 'absolute';
            }

            // 2. CREAR NUESTRA PROPIA UI LIMPIA
            if (!document.getElementById('kiosco-ui-limpia')) {
                let uiLimpia = document.createElement('div');
                uiLimpia.id = 'kiosco-ui-limpia';
                uiLimpia.style.cssText = 'width: 100%; display: flex; flex-direction: column; gap: 15px; align-items: center; order: 1;';
                
                // Título
                let titulo = document.createElement('div');
                titulo.innerHTML = '<b>INGRESE SU DNI</b>';
                titulo.style.cssText = 'font-size: 20px; color: #0f172a; text-align: center; width: 100%; margin-bottom: 5px; font-weight: 800; text-transform: uppercase; letter-spacing: 1px;';
                uiLimpia.appendChild(titulo);
                
                // Input Limpio
                inputKiosco = document.createElement('input');
                inputKiosco.id = 'kiosco-dni-input';
                inputKiosco.type = 'text';
                inputKiosco.readOnly = true; // El usuario NO puede hacerle click, usa el teclado
                inputKiosco.placeholder = 'DNI DEL AFILIADO';
                inputKiosco.style.cssText = 'border-radius: 12px; border: 2px solid #cbd5e1; height: 50px; width: 100%; background: #f8fafc; color: #0f172a; font-size: 28px; font-weight: 900; text-align: center; box-shadow: inset 0 2px 5px rgba(0,0,0,0.05); outline: none; letter-spacing: 3px; box-sizing: border-box; transition: 0.2s;';
                
                // Hack para ::placeholder desde inline JS no se puede, lo inyectamos con <style>
                let stylePH = document.createElement('style');
                stylePH.innerHTML = '#kiosco-dni-input::placeholder { color: rgba(15,23,42,0.3) !important; font-size: 20px; letter-spacing: 1px; }';
                document.head.appendChild(stylePH);
                
                uiLimpia.appendChild(inputKiosco);
                
                // Botón Buscar Limpio
                btnLimpio = document.createElement('button');
                btnLimpio.id = 'kiosco-btn-buscar';
                btnLimpio.innerText = 'CONFIRMAR IDENTIDAD';
                btnLimpio.style.cssText = 'width: 100%; height: 50px; font-size: 18px; font-weight: bold; border-radius: 12px; background: #0284c7; color: white; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; text-transform: uppercase; box-shadow: 0 4px 0 #0369a1; transition: all 0.2s; box-sizing: border-box;';
                
                btnLimpio.addEventListener('click', () => {
                    let dniValor = inputKiosco.value;
                    if (dniValor.length < 6) {
                        alert('Por favor ingrese un DNI válido.');
                        return;
                    }
                    
                    // 1. Deshabilitamos nuestro botón temporalmente
                    btnLimpio.innerText = 'BUSCANDO...';
                    btnLimpio.disabled = true;
                    
                    // 2. Sincronizamos robustamente usando eventos (como en tamp_turnos)
                    let inputRealDx = document.querySelector('[id$="txtDNI_I"]');
                    if (inputRealDx) {
                        inputRealDx.focus();
                        inputRealDx.value = dniValor;
                        inputRealDx.dispatchEvent(new Event('input', { bubbles: true }));
                        inputRealDx.dispatchEvent(new Event('change', { bubbles: true }));
                        
                        let dxObjName = inputRealDx.id.replace('_I', '');
                        if (typeof globalWin[dxObjName] !== 'undefined') {
                            globalWin[dxObjName].SetText(dniValor);
                        }
                        
                        setTimeout(() => {
                            inputRealDx.blur();
                            // 3. Disparamos el clic en el botón nativo escondido
                            let btnNativo = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout_btnBuscarAfiliado');
                            if (btnNativo) {
                                btnNativo.click();
                            }
                        }, 50);
                    }
                });
                
                uiLimpia.appendChild(btnLimpio);
                formLayout.appendChild(uiLimpia);
            }

            // Inyectar el teclado dentro del contenedor nativo si no existe
            if (!document.getElementById('teclado-sugerencias')) {
                let tecladoHTML = document.createElement('div');
                tecladoHTML.id = 'teclado-sugerencias';
                tecladoHTML.className = 'teclado-kiosco';
                
                let htmlBotones = '';
                for (let i = 1; i <= 9; i++) {
                    htmlBotones += `<button type="button" class="kbtn num-btn" data-num="${i}">${i}</button>`;
                }
                htmlBotones += `<button type="button" class="kbtn kbtn-borrar">X</button>`;
                htmlBotones += `<button type="button" class="kbtn num-btn" data-num="0">0</button>`;
                
                tecladoHTML.innerHTML = htmlBotones;
                formLayout.appendChild(tecladoHTML);

                // Agregar BOTÓN ROJO DE VOLVER debajo del teclado
                let btnVolverHtml = document.createElement('button');
                btnVolverHtml.id = 'kiosco-btn-volver';
                btnVolverHtml.type = 'button'; // Prevenir submit nativo
                btnVolverHtml.innerHTML = '<i class="fa-solid fa-arrow-left"></i> VOLVER AL INICIO';
                btnVolverHtml.style.cssText = 'width: 100%; height: 60px; font-size: 22px; font-weight: 900; border-radius: 12px; background: #ef4444; color: white; border: none; display: flex; align-items: center; justify-content: center; cursor: pointer; text-transform: uppercase; box-shadow: 0 4px 0 #b91c1c; transition: all 0.2s; box-sizing: border-box; order: 3; margin-top: 10px; gap: 10px;';
                btnVolverHtml.addEventListener('click', (e) => {
                    e.preventDefault(); // Prevenir submit nativo
                    window.location.href = urlDashboard;
                });
                formLayout.appendChild(btnVolverHtml);

                tecladoHTML.querySelectorAll('.kbtn').forEach(btn => {
                    if (btn.classList.contains('kbtn-borrar')) {
                        btn.addEventListener('click', procesarBorrado);
                        btn.addEventListener('touchstart', (e) => { e.preventDefault(); procesarBorrado(); });
                    } else {
                        let numero = btn.getAttribute('data-num');
                        btn.addEventListener('click', () => procesarPulsacion(numero));
                        btn.addEventListener('touchstart', (e) => { e.preventDefault(); procesarPulsacion(numero); });
                    }
                });
            }


            
            // Limpiar valores del input silencioso oculto
            let inputSilencioso = document.querySelector('[id$="txtDNI_I"]');
            if (inputSilencioso) {
                inputSilencioso.value = '';
                let dxObjName = inputSilencioso.id.replace('_I', '');
                if (typeof globalWin[dxObjName] !== 'undefined') globalWin[dxObjName].SetText('');
            }
        }
    }, 300);

})();
