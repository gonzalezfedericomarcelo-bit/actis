// ==UserScript==
// @name         SISTEMA ACTIS - INTEGRACION TURNOS UNIFICADO (V4)
// @namespace    http://tampermonkey.net/
// @version      4.0
// @match        *://sgps.iosfa.gob.ar/*
// @match        *://validador.iosfa.gob.ar/ValidadorDni*
// @grant        GM_setValue
// @grant        GM_getValue
// @grant        GM_addValueChangeListener
// @grant        GM_xmlhttpRequest
// ==/UserScript==

(function() {
    'use strict';

    // RASTREO GLOBAL DEL MOTIVO DE BAJA
    // Funciona en cualquier pantalla o iframe de SGPS donde exista el select vTHTUMBCODIGO
    setInterval(() => {
        let selectMotivo = document.getElementById('vTHTUMBCODIGO');
        if (selectMotivo) {
            let selectedOption = selectMotivo.options[selectMotivo.selectedIndex];
            if (selectedOption && selectedOption.text && selectedOption.value !== '0' && selectedOption.value !== '') {
                GM_setValue('actis_ultimo_motivo_baja', selectedOption.text.trim());
            }
        }
    }, 500);

    // -------------------------------------------------------------------------
    // LÓGICA DE FAST-FORWARD (Bypass de validación para "Sacar otro turno")
    // -------------------------------------------------------------------------
    if (GM_getValue('actis_fastforward_active') === true) {
        let urlLower = window.location.href.toLowerCase();
        
        // PANTALLAS INTERMEDIAS (Donde el motor de fast-forward debe actuar)
        if (urlLower.includes('pacientebusquedavalidacion') || urlLower.includes('datospacientevalidado')) {
            // Inyectar modal bloqueador para que el usuario espere
            if (!document.getElementById('actis_ff_overlay')) {
                let overlayFF = document.createElement('div');
                overlayFF.id = 'actis_ff_overlay';
                overlayFF.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.95); z-index:9999999; display:flex; flex-direction:column; justify-content:center; align-items:center; backdrop-filter:blur(10px); color:white; font-family:Arial, sans-serif;';
                overlayFF.innerHTML = `
                    <div style="border: 8px solid #334155; border-top: 8px solid #10b981; border-radius: 50%; width: 80px; height: 80px; animation: actisSpin 1s linear infinite; margin-bottom:25px;"></div>
                    <h2 style="margin:0; font-size:28px; color:#10b981; font-weight:bold;">Procesando Turno Automático...</h2>
                    <p style="color:#94a3b8; margin-top:15px; font-size:18px; max-width: 500px; text-align: center; line-height: 1.5;">Aguarde unos instantes. SGPS está validando los datos del paciente y avanzando de forma automática.</p>
                    <p style="color:#ef4444; margin-top:20px; font-size:14px; font-weight:bold; background: rgba(239, 68, 68, 0.1); padding: 10px 20px; border-radius: 5px;">Por favor, NO toque el teclado ni el mouse.</p>
                    <button id="btn_cancelar_ff" style="margin-top:30px; background:#ef4444; color:white; border:none; padding:10px 20px; border-radius:5px; font-weight:bold; cursor:pointer; font-size:16px; box-shadow: 0 4px 6px rgba(239, 68, 68, 0.3); transition: background 0.3s;">❌ Cancelar Autocompletado</button>
                    <style>@keyframes actisSpin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }</style>
                `;
                document.body.appendChild(overlayFF);
                
                document.getElementById('btn_cancelar_ff').addEventListener('click', () => {
                    GM_setValue('actis_fastforward_active', false);
                    GM_setValue('actis_fastforward_dni', '');
                    document.getElementById('actis_ff_overlay').remove();
                });
            }

            // PANTALLA 1: BÚSQUEDA DE PACIENTE (thpacientebusquedavalidacion)
            if (urlLower.includes('pacientebusquedavalidacion')) {
                let attempts = 0;
                let searchInterval = setInterval(() => {
                    attempts++;
                    let inputBuscar = document.getElementById('vBUSCAR');
                    
                    // Si encontramos el input y está vacío, le inyectamos el DNI
                    if (inputBuscar && inputBuscar.value === '') {
                        inputBuscar.value = GM_getValue('actis_fastforward_dni');
                        
                        // Inyectamos script nativo de GeneXus para forzar el evento de búsqueda
                        let script = document.createElement('script');
                        script.textContent = `
                            setTimeout(function() {
                                var el = document.getElementById('vBUSCAR');
                                if (el && typeof gx !== 'undefined' && gx.evt) {
                                    gx.evt.onchange(el, new Event('change'));
                                    gx.evt.onblur(el, 24);
                                    // Intentar presionar CTLBUSCAR si existe
                                    var btn = document.getElementById('CTLBUSCAR');
                                    if (btn) btn.click();
                                }
                            }, 200);
                        `;
                        document.body.appendChild(script);

                        // Por si acaso, disparamos eventos nativos
                        inputBuscar.dispatchEvent(new Event('focus', { bubbles: true }));
                        inputBuscar.dispatchEvent(new Event('change', { bubbles: true }));
                        inputBuscar.dispatchEvent(new Event('blur', { bubbles: true }));
                        inputBuscar.dispatchEvent(new KeyboardEvent('keydown', {'key': 'Enter', 'keyCode': 13, 'which': 13, bubbles: true}));
                        inputBuscar.dispatchEvent(new KeyboardEvent('keyup', {'key': 'Enter', 'keyCode': 13, 'which': 13, bubbles: true}));
                        inputBuscar.dispatchEvent(new KeyboardEvent('keypress', {'key': 'Enter', 'keyCode': 13, 'which': 13, bubbles: true}));
                    }
                    
                    // Si la búsqueda ya trajo resultados en la grilla, hacemos clic en el primer resultado
                    let btnSeleccionar = document.querySelector('img[title="Seleccionar"], input[title="Seleccionar"], a[title="Seleccionar"], #vSELECCIONAR_0001');
                    if (btnSeleccionar) {
                        clearInterval(searchInterval);
                        btnSeleccionar.click();
                    }
                    
                    // TIMEOUT DE SEGURIDAD: Si a los ~6 segundos (20 intentos de 300ms) no hay resultados, abortar.
                    if (attempts > 20) {
                        clearInterval(searchInterval);
                        GM_setValue('actis_fastforward_active', false);
                        GM_setValue('actis_fastforward_dni', '');
                        let overlay = document.getElementById('actis_ff_overlay');
                        if (overlay) overlay.remove();
                        alert('⚠️ ACTIS: No se encontraron pacientes válidos en el padrón del SGPS. Autocompletado cancelado.');
                    }
                }, 300);
            }
            
            // PANTALLA 2: DATOS DEL PACIENTE VALIDADOS (thdatospacientevalidado)
            else if (urlLower.includes('datospacientevalidado')) {
                let confirmInterval = setInterval(() => {
                    let btnConf1 = document.getElementById('CONFIRMAR');
                    let btnConf2 = document.getElementById('CONFIRMAR2');
                    
                    if (btnConf1 || btnConf2) {
                        clearInterval(confirmInterval);
                        
                        // APAGAMOS EL FAST FORWARD AQUÍ (Ya cumplió su trabajo)
                        GM_setValue('actis_fastforward_active', false);
                        
                        if (btnConf1) btnConf1.click();
                        else if (btnConf2) btnConf2.click();
                    }
                }, 300);
            }
        }
    }

    const urlAPIActis = 'https://federicogonzalez.net/actis/api_turnos_guardar.php';
    const urlConfigActis = 'https://federicogonzalez.net/actis/admin_modal.php?api=1&t=' + new Date().getTime();

    // -------------------------------------------------------------------------
    // LÓGICA EN SGPS: SALUDO INICIAL FLOTANTE (ESPERA A QUE CARGUE LA PÁGINA)
    // -------------------------------------------------------------------------
    if (window.location.href.includes('sgps.iosfa.gob.ar') && !sessionStorage.getItem('actis_welcomed')) {
        let intentos = 0;
        let checkUser = setInterval(() => {
            let userNameSpan = document.getElementById('span_MPW0024vUSUANOMBRE');
            if (userNameSpan && userNameSpan.innerText.trim() !== '') {
                clearInterval(checkUser);
                mostrarSaludoFlotante(userNameSpan.innerText.trim());
            } else if (intentos > 20) {
                clearInterval(checkUser);
            }
            intentos++;
        }, 500);

        function mostrarSaludoFlotante(nombreCompleto) {
            sessionStorage.setItem('actis_welcomed', 'true');
            let primerNombre = nombreCompleto.split(' ')[0];
            let userName = primerNombre.charAt(0).toUpperCase() + primerNombre.slice(1).toLowerCase();

            GM_xmlhttpRequest({
                method: "GET",
                url: urlConfigActis,
                onload: function(respConfig) {
                    try {
                        let conf = JSON.parse(respConfig.responseText);
                        if (conf.activo === true) {
                            let toast = document.createElement('div');
                            toast.style.cssText = 'position:fixed; top:20px; right:20px; background:#1e293b; color:white; padding:15px 25px; border-radius:10px; box-shadow:0 10px 25px rgba(0,0,0,0.2); z-index:99999; font-family:Arial; font-size:15px; font-weight:bold; border-left:5px solid #3b82f6; transform:translateX(150%); transition:transform 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);';
                            toast.innerHTML = `👋 ¡Hola ${userName}! Sistema ACTIS conectado.`;
                            document.body.appendChild(toast);
                            setTimeout(() => { toast.style.transform = 'translateX(0)'; }, 500);
                            setTimeout(() => { toast.style.transform = 'translateX(150%)'; }, 4500);
                            setTimeout(() => { toast.remove(); }, 5500);
                        }
                    } catch(e) {}
                }
            });
        }
    }

    // -------------------------------------------------------------------------
    // LÓGICA EN EL VALIDADOR (Auto-rellenado y extracción)
    // -------------------------------------------------------------------------
    if (window.location.href.includes('validador.iosfa.gob.ar')) {
        let checkValidador = setInterval(() => {
            let inputDNI = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout_txtDNI_I');
            let btnBuscar = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout_btnBuscarAfiliado');
            let dniAuto = GM_getValue('actis_dni_buscar');

            if (dniAuto && inputDNI && btnBuscar && !inputDNI.hasAttribute('actis-filled')) {
                inputDNI.focus();
                inputDNI.value = dniAuto;
                inputDNI.setAttribute('actis-filled', 'true');
                inputDNI.dispatchEvent(new Event('input', { bubbles: true }));
                inputDNI.dispatchEvent(new Event('change', { bubbles: true }));
                GM_setValue('actis_dni_buscar', '');
                setTimeout(() => {
                    inputDNI.blur();
                    setTimeout(() => { btnBuscar.click(); }, 50);
                }, 50);
            }

            let bodyText = document.body.innerText;
            let matchAfiliado = bodyText.match(/-\s*([A-Za-z]{1,2}\d{5,10})\s*-/);
            if (!matchAfiliado) matchAfiliado = bodyText.match(/\b([A-Za-z]{1,2}\d{6,10})\b/);

            if (matchAfiliado && matchAfiliado[1]) {
                clearInterval(checkValidador);

                // Extraer la Fuerza (Armadas, Gendarmería, etc) buscando cualquier span que empiece con "afi"
                let spanFuerza = document.querySelector('span[class^="afi"]');
                let fuerzaTexto = spanFuerza ? spanFuerza.innerText.trim() : '';

                GM_setValue('actis_afiliado_temp', matchAfiliado[1].toUpperCase());
                GM_setValue('actis_fuerza_temp', fuerzaTexto);

                setTimeout(() => { window.close(); }, 50);
            }
        }, 100);
        return;
    }

    // -------------------------------------------------------------------------
    // MOTOR UNIFICADO DE INYECCIÓN DE PANEL (Para Turnos Simples y Sesionados)
    // -------------------------------------------------------------------------
    function inyectarPanelExtraccion(btnGrabar, esSesionado) {
        if (document.getElementById('panel_actis_inyectado')) return;

        let panelActis = document.createElement('div');
        panelActis.id = 'panel_actis_inyectado';
        panelActis.style.cssText = 'border: 2px dashed #22b14c; padding: 15px; margin-top: 15px; margin-bottom: 15px; background: #f0fdf4; border-radius: 8px; font-family: Arial;';
        panelActis.innerHTML = `
            <div style="color:#166534; font-weight:bold; font-size:14px; margin-bottom:10px;">⚡ Extracción ACTIS (Paso 1)</div>
            <div style="display:flex; gap:10px; align-items:center;">
                <button id="btn_popup_validador" style="background:#3b82f6; color:white; border:none; padding:8px 15px; cursor:pointer; font-weight:bold; border-radius:4px;">Extraer Datos IOSFA</button>
                <input type="text" id="actis_afiliado_input" placeholder="Afiliado..." style="padding:7px; border:1px solid #ccc; width:120px; font-weight:bold; background-color:#e2e8f0; color:#0f172a; text-align:center; font-size:14px; transition: all 0.3s;" readonly>
                <input type="text" id="actis_fuerza_input" placeholder="Fuerza..." style="padding:7px; border:1px solid #ccc; width:180px; font-weight:bold; background-color:#e2e8f0; color:#0f172a; text-align:center; font-size:14px; transition: all 0.3s;" readonly>
                <input type="text" id="actis_nroturno_input" placeholder="Nro Turno" style="padding:7px; border:1px solid #ccc; width:100px; font-weight:bold; background-color:#e2e8f0; color:#0f172a; text-align:center; font-size:14px; transition: all 0.3s;" readonly>
                <span style="font-size:12px; color:#475569;">(Luego, pulse "${esSesionado ? 'Confirmar' : 'Grabar Turno'}" normalmente)</span>
            </div>
        `;
        btnGrabar.parentNode.insertBefore(panelActis, btnGrabar.nextSibling);

        // NOTA: El auto-click automático fue removido a petición del usuario.
        // El operador debe hacer click manualmente en "Extraer Datos IOSFA".

        setInterval(() => {
            let inputAfi = document.getElementById('actis_afiliado_input');
            if (inputAfi && (inputAfi.value === '' || inputAfi.value === 'Buscando...')) {
                let valorEnMemoria = GM_getValue('actis_afiliado_temp');
                let nroTurnoEnMemoria = GM_getValue('actis_turno_temp_numero_seleccionado');
                let esRealmenteReprogramacion = GM_getValue('actis_turno_es_realmente_reprogramacion') === true;
                
                if (nroTurnoEnMemoria) {
                    let inN = document.getElementById('actis_nroturno_input');
                    if(inN && inN.value === '') {
                        inN.value = nroTurnoEnMemoria;
                        inN.style.backgroundColor = '#dbeafe';
                        inN.style.color = '#1e40af';
                        inN.style.borderColor = '#1e40af';
                        GM_setValue('actis_turno_temp_numero_seleccionado', '');
                        
                        // Solo seteamos que es reprogramado si realmente se hizo clic en el botón Reprogramar
                        if (esRealmenteReprogramacion) {
                            GM_setValue('actis_es_reprogramacion_flag', true);
                            GM_setValue('actis_turno_es_realmente_reprogramacion', false);
                        }
                    }
                }
                let fuerzaEnMemoria = GM_getValue('actis_fuerza_temp');

                if (valorEnMemoria && valorEnMemoria !== '') {
                    inputAfi.value = valorEnMemoria;
                    inputAfi.setAttribute('value', valorEnMemoria);

                    let inputFuerza = document.getElementById('actis_fuerza_input');
                    if(inputFuerza) {
                        inputFuerza.value = fuerzaEnMemoria || 'N/A';
                        inputFuerza.style.backgroundColor = '#dcfce7';
                        inputFuerza.style.color = '#166534';
                        inputFuerza.style.borderColor = '#166534';
                    }

                    let txtObs = document.getElementById('vTHTUOBSERVACIONES');
                    if (txtObs) {
                        txtObs.focus();
                        let textoActual = txtObs.value.trim();
                        if (textoActual !== '' && !textoActual.includes(valorEnMemoria)) {
                            txtObs.value = textoActual + ' | Afiliado: ' + valorEnMemoria;
                        } else {
                            txtObs.value = valorEnMemoria;
                        }
                        txtObs.dispatchEvent(new KeyboardEvent('keydown', { bubbles: true }));
                        txtObs.dispatchEvent(new Event('input', { bubbles: true }));
                        txtObs.dispatchEvent(new KeyboardEvent('keyup', { bubbles: true }));
                        txtObs.dispatchEvent(new Event('change', { bubbles: true }));
                        txtObs.blur();
                    }

                    inputAfi.style.backgroundColor = '#dcfce7';
                    inputAfi.style.color = '#166534';
                    inputAfi.style.borderColor = '#166534';
                    inputAfi.style.fontSize = '16px';
                    inputAfi.style.transform = 'scale(1.05)';
                    setTimeout(() => { inputAfi.style.transform = 'scale(1)'; }, 400);
                    
                    // Quitar el overlay de extracción y el iframe oculto inmediatamente
                    let extOverlay = document.getElementById('actis_extract_overlay');
                    if (extOverlay) extOverlay.remove();
                    let hiddenIframe = document.getElementById('actis_hidden_validador');
                    if (hiddenIframe) hiddenIframe.remove();
                    
                    GM_setValue('actis_afiliado_temp', '');
                    GM_setValue('actis_fuerza_temp', '');
                }
            }
        }, 200);

        document.getElementById('btn_popup_validador').addEventListener('click', (e) => {
            e.preventDefault();
            
            // INYECTAR OVERLAY DE EXTRACCIÓN
            if (!document.getElementById('actis_extract_overlay')) {
                let extractOverlay = document.createElement('div');
                extractOverlay.id = 'actis_extract_overlay';
                extractOverlay.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.95); z-index:9999999; display:flex; flex-direction:column; justify-content:center; align-items:center; backdrop-filter:blur(10px); color:white; font-family:Arial, sans-serif;';
                extractOverlay.innerHTML = `
                    <div style="border: 8px solid #334155; border-top: 8px solid #3b82f6; border-radius: 50%; width: 80px; height: 80px; animation: actisSpin 1s linear infinite; margin-bottom:25px;"></div>
                    <h2 style="margin:0; font-size:28px; color:#3b82f6; font-weight:bold;">Extrayendo Datos IOSFA...</h2>
                    <p style="color:#94a3b8; margin-top:15px; font-size:18px; max-width: 500px; text-align: center; line-height: 1.5;">Aguarde mientras nos conectamos al Validador para obtener la afiliación y la fuerza.</p>
                    <p style="color:#ef4444; margin-top:20px; font-size:14px; font-weight:bold; background: rgba(239, 68, 68, 0.1); padding: 10px 20px; border-radius: 5px;">Asegúrese de PERMITIR LAS VENTANAS EMERGENTES (Pop-ups) en su navegador.</p>
                `;
                document.body.appendChild(extractOverlay);
            }

            GM_setValue('actis_afiliado_temp', '');
            let inputAfi = document.getElementById('actis_afiliado_input');
            inputAfi.value = 'Buscando...';
            inputAfi.style.backgroundColor = '#fef08a';
            inputAfi.style.color = '#854d0e';
            inputAfi.style.borderColor = '#ca8a04';

            // Timeout de seguridad por si el Iframe/Popup falla
            setTimeout(() => {
                let extOverlay = document.getElementById('actis_extract_overlay');
                if (extOverlay) {
                    extOverlay.remove();
                    if (inputAfi.value === 'Buscando...') {
                        inputAfi.value = '';
                        inputAfi.style.backgroundColor = '#fca5a5';
                        inputAfi.style.color = '#7f1d1d';
                        
                        // Limpiamos el iframe si falló
                        let hiddenIframe = document.getElementById('actis_hidden_validador');
                        if (hiddenIframe) hiddenIframe.remove();

                        // Si fue un intento automático que falló, pedimos clic manual
                        if (!e.isTrusted) {
                            alert("Extracción automática fallida.\\nPor favor, presione el botón 'Extraer Datos IOSFA' manualmente.");
                        } else {
                            alert("ERROR ACTIS: No se pudo abrir el validador manualmente.\\nPor favor, asegúrese de desactivar el BLOQUEADOR DE POP-UPS para esta página.");
                        }
                    }
                }
            }, 8000);

            // Buscar DNI dependiendo de qué pantalla estemos
            let txtDNI_raw = '';
            if (esSesionado) {
                txtDNI_raw = document.getElementById('span_W0018vDOCUMENTOTIPONUMERO')?.innerText || '';
            } else {
                txtDNI_raw = document.getElementById('span_W0040W0005vDOCUMENTOTIPONUMERO')?.innerText || '';
            }

            let dni_limpio = txtDNI_raw.replace(/\D/g, '');

            if(dni_limpio) {
                GM_setValue('actis_dni_buscar', dni_limpio);
                
                if (!e.isTrusted) {
                    // Intento AUTOMÁTICO: Usamos un iframe invisible para eludir el bloqueador de popups
                    let iframe = document.createElement('iframe');
                    iframe.id = 'actis_hidden_validador';
                    iframe.src = 'https://validador.iosfa.gob.ar/ValidadorDni';
                    iframe.style.display = 'none';
                    document.body.appendChild(iframe);
                } else {
                    // Intento MANUAL (clic humano): Abrimos ventana emergente real (no la bloqueará el navegador)
                    window.open('https://validador.iosfa.gob.ar/ValidadorDni', 'PopupVal', 'width=450,height=550,menubar=no,status=no,titlebar=no,toolbar=no');
                }
            } else {
                alert("No se detectó un DNI cargado en la pantalla.");
                inputAfi.value = '';
                inputAfi.style.backgroundColor = '#e2e8f0';
            }
        });
    }

    // -------------------------------------------------------------------------
    // LÓGICA EN SGPS: PANTALLA ASIGNAR TURNO (SIMPLE - Pre-Guardado)
    // -------------------------------------------------------------------------
    if (window.location.href.includes('thasignarturno.aspx')) {
        let checkInterval = setInterval(() => {
            let btnGrabar = document.getElementById('BTN_GRABAR2');
            if (btnGrabar && !document.getElementById('panel_actis_inyectado')) {
                clearInterval(checkInterval);
                inyectarPanelExtraccion(btnGrabar, false);

                // === NUEVO: RESALTAR CAMBIOS DE REPROGRAMACION ===
                let oldTextsStr = GM_getValue('actis_turno_reprogramar_old_texts');
                if (oldTextsStr) {
                    try {
                        let oldTexts = JSON.parse(oldTextsStr);
                        if (oldTexts && oldTexts.length > 0) {
                            if (!document.getElementById('actis-pulse-style')) {
                                let style = document.createElement('style');
                                style.id = 'actis-pulse-style';
                                style.innerHTML = `
                                @keyframes actisPulse {
                                    0% { transform: scale(1); opacity: 1; }
                                    50% { transform: scale(1.1); opacity: 0.7; }
                                    100% { transform: scale(1); opacity: 1; }
                                }
                                .actis-cambio-tag {
                                    color: #ef4444;
                                    font-weight: 900;
                                    font-size: 0.85em;
                                    margin-left: 8px;
                                    display: inline-block;
                                    animation: actisPulse 1.5s infinite;
                                    background: #fee2e2;
                                    padding: 2px 6px;
                                    border-radius: 4px;
                                    border: 1px solid #ef4444;
                                    box-shadow: 0 2px 4px rgba(239,68,68,0.2);
                                }
                                .actis-campo-modificado {
                                    border: 2px solid #ef4444 !important;
                                    background-color: #fef2f2 !important;
                                    color: #991b1b !important;
                                    font-weight: bold !important;
                                    border-radius: 4px !important;
                                }`;
                                document.head.appendChild(style);
                            }

                            function resaltarCambio(el, type) {
                                if(!el) return;
                                el.classList.add('actis-campo-modificado');
                                let tag = document.createElement('span');
                                tag.innerText = '⭐ ¡CAMBIÓ!';
                                tag.className = 'actis-cambio-tag';
                                if (type === 'span') {
                                    el.parentNode.insertBefore(tag, el.nextSibling);
                                } else {
                                    el.parentNode.appendChild(tag);
                                }
                            }

                            let campos = [
                                { el: document.getElementById('span_W0102vTHMEDCANOMBRE'), type: 'span' },
                                { el: document.getElementById('span_W0102vTHSERDESCRIPCION'), type: 'span' },
                                { el: document.getElementById('W0102vPRESAPENOM'), type: 'input' },
                                { el: document.getElementById('span_W0102vESPEDESCRI_ESPECIALIDAD'), type: 'span' }
                            ];
                            
                            campos.forEach(c => {
                                if (c.el) {
                                    let val = (c.type === 'input' ? c.el.value : c.el.innerText).trim().toUpperCase();
                                    let changed = true;
                                    for (let ot of oldTexts) {
                                        if (ot && (ot.includes(val) || val.includes(ot))) {
                                            changed = false;
                                            break;
                                        }
                                    }
                                    if (changed && val !== '') {
                                        resaltarCambio(c.el, c.type);
                                    }
                                }
                            });
                            
                            let inputFecha = document.getElementById('W0102vTHTUFECHA');
                            if (inputFecha) {
                                let valFechaHora = inputFecha.value.trim();
                                let partes = valFechaHora.split(' ');
                                let fecha = partes[0] ? partes[0].toUpperCase() : '';
                                let hora = partes[1] ? partes[1].toUpperCase() : '';
                                
                                let fechaCambio = true;
                                let horaCambio = true;
                                
                                for (let ot of oldTexts) {
                                    if (ot && ot.includes(fecha)) fechaCambio = false;
                                    if (ot && ot.includes(hora)) horaCambio = false;
                                }
                                
                                if ((fechaCambio || horaCambio) && valFechaHora !== '') {
                                    resaltarCambio(inputFecha, 'input');
                                }
                            }
                            
                            GM_setValue('actis_turno_reprogramar_old_texts', '');
                        }
                    } catch(e) { console.error("ACTIS ERROR al resaltar cambios:", e); }
                }
                // === FIN NUEVO ===

                btnGrabar.addEventListener('mousedown', (e) => {
                    let inputAfiVal = document.getElementById('actis_afiliado_input').value.trim();
                    let nroAfiliado = (inputAfiVal === 'Buscando...') ? '' : inputAfiVal;
                    let txtDNI_raw = document.getElementById('span_W0040W0005vDOCUMENTOTIPONUMERO')?.innerText || '';
                    let dni_limpio = txtDNI_raw.replace(/\D/g, '');
                    let txtNombreCompleto = document.getElementById('span_W0040W0005vEMPEAPENOM')?.innerText.trim() || '';
                    let parts = txtNombreCompleto.split(',');
                    let apellido_p = parts[0] ? parts[0].trim() : '';
                    let nombre_p = parts[1] ? parts[1].trim() : txtNombreCompleto;
                    let fNac_raw = document.getElementById('span_W0040W0005vEMPEFECNAC')?.innerText.trim() || '';
                    let fNacParts = fNac_raw.split('/');
                    let fecha_nac_sql = (fNacParts.length === 3) ? `${fNacParts[2]}-${fNacParts[1]}-${fNacParts[0]}` : '';
                    let txtFechaHora = document.getElementById('span_W0102vTHTUFECHA')?.innerText.trim() || '';
                    let fhParts = txtFechaHora.split(' ');
                    let fTurno_raw = fhParts[0] || '';
                    let fTurnoParts = fTurno_raw.split('/');
                    let fecha_turno_sql = (fTurnoParts.length === 3) ? `${fTurnoParts[2]}-${fTurnoParts[1]}-${fTurnoParts[0]}` : '';
                    let hora_turno_sql = fhParts[1] || '';

                    // NUEVA LÓGICA DE EXTRACCIÓN DE PRÁCTICA (IGNORA CHECKBOX, USA COLOR VERDE O COINCIDENCIA DE TEXTO)
                    let arrayPracticas = [];
                    let idx = 1;
                    let inputBuscar = document.getElementById('W0178vBUSCAR');
                    let textoBuscar = inputBuscar ? inputBuscar.value.trim().toUpperCase() : '';

                    while(true) {
                        let numStr = idx.toString().padStart(4, '0');
                        let codElement = document.getElementById(`span_W0178vNPPECODPRA_CODIGO_PRACTICA_${numStr}`);
                        let descElement = document.getElementById(`span_W0178vNPPEDESBRE_${numStr}`);

                        if(!codElement && !descElement) break;

                        let pCod = codElement ? codElement.innerText.trim() : '';
                        let pDesc = descElement ? descElement.innerText.trim() : '';

                        let colorStyle = codElement ? (codElement.getAttribute('style') || '').toLowerCase() : '';
                        let esVerde = colorStyle.includes('#22b14c') || colorStyle.includes('rgb(34, 177, 76)');
                        let coincideTexto = (textoBuscar !== '' && pDesc.toUpperCase().includes(textoBuscar));

                        if(esVerde || coincideTexto) {
                            let practicaFinal = `[${pCod}] ${pDesc}`;
                            if(!arrayPracticas.includes(practicaFinal)) {
                                arrayPracticas.push(practicaFinal);
                            }
                        }
                        idx++;
                    }

                    // Si no detectó nada en verde, recurre al checkbox como última opción de rescate
                    if (arrayPracticas.length === 0) {
                        idx = 1;
                        while(true) {
                            let numStr = idx.toString().padStart(4, '0');
                            let codElement = document.getElementById(`span_W0178vNPPECODPRA_CODIGO_PRACTICA_${numStr}`);
                            let descElement = document.getElementById(`span_W0178vNPPEDESBRE_${numStr}`);
                            let chkElement = document.getElementById(`W0178vSELECCIONAR_${numStr}`);
                            if(!codElement && !descElement) break;
                            if(chkElement && chkElement.checked) {
                                let pCod = codElement ? codElement.innerText.trim() : '';
                                let pDesc = descElement ? descElement.innerText.trim() : '';
                                if(pCod || pDesc) arrayPracticas.push(`[${pCod}] ${pDesc}`);
                            }
                            idx++;
                        }
                    }

                    let userNameSpan = document.getElementById('span_MPW0024vUSUANOMBRE');
                    let nombreOperador = userNameSpan ? userNameSpan.innerText.trim() : '';

                    let isRepro = GM_getValue('actis_es_reprogramacion_flag') === true;
                    GM_setValue('actis_es_reprogramacion_flag', false);
                    
                    let numActual = document.getElementById('actis_nroturno_input')?.value || '';

                    let payload = {
                        es_sesionado: false,
                        es_reprogramacion: isRepro,
                        operador: nombreOperador,
                        afiliado: nroAfiliado,
                        fuerza: document.getElementById('actis_fuerza_input')?.value || '',
                        numero_turno_anterior: isRepro ? numActual : '',
                        numero_turno: isRepro ? '' : numActual,
                        dni: dni_limpio,
                        nombre: nombre_p,
                        apellido: apellido_p,
                        telefono: document.getElementById('span_W0040vTELEFONONUMERO_0001')?.innerText.trim() || '',
                        email: document.getElementById('span_W0040vEMEMDIRECCION_0001')?.innerText.trim() || '',
                        fecha_nac: fecha_nac_sql,
                        servicio: document.getElementById('span_W0102vTHSERDESCRIPCION')?.innerText.trim() || '',
                        especialidad: document.getElementById('span_W0102vESPEDESCRI_ESPECIALIDAD')?.innerText.trim() || '',
                        profesional: document.getElementById('span_W0102vPRESAPENOM')?.innerText.trim() || '',
                        fecha: fecha_turno_sql,
                        hora: hora_turno_sql,
                        practicas: arrayPracticas.join(' | '),
                        observaciones: document.getElementById('vTHTUOBSERVACIONES')?.value.trim() || ''
                    };
                    GM_setValue('actis_turno_temp_payload', JSON.stringify(payload));
                });
            }
        }, 500);
    }

    // -------------------------------------------------------------------------
    // LÓGICA EN SGPS: PANTALLA ASIGNAR TURNO SESIONADO (Pre-Guardado)
    // -------------------------------------------------------------------------
    if (window.location.href.includes('thturnobusquedasesionado2.aspx')) {
        let checkIntervalSes = setInterval(() => {
            let btnGrabar = document.getElementById('BUTTON3'); // El botón "Confirmar"

            if (btnGrabar && !document.getElementById('panel_actis_inyectado')) {
                clearInterval(checkIntervalSes);
                inyectarPanelExtraccion(btnGrabar, true);

                btnGrabar.addEventListener('mousedown', (e) => {
                    let inputAfiVal = document.getElementById('actis_afiliado_input').value.trim();
                    let nroAfiliado = (inputAfiVal === 'Buscando...') ? '' : inputAfiVal;
                    let txtDNI_raw = document.getElementById('span_W0018vDOCUMENTOTIPONUMERO')?.innerText || '';
                    let dni_limpio = txtDNI_raw.replace(/\D/g, '');
                    let txtNombreCompleto = document.getElementById('span_W0018vEMPEAPENOM')?.innerText.trim() || '';
                    let parts = txtNombreCompleto.split(',');
                    let apellido_p = parts[0] ? parts[0].trim() : '';
                    let nombre_p = parts[1] ? parts[1].trim() : txtNombreCompleto;

                    let fNac_raw = document.getElementById('span_W0018vEMPEFECNAC')?.innerText.trim() || '';
                    let fNacParts = fNac_raw.split('/');
                    let fecha_nac_sql = (fNacParts.length === 3) ? `${fNacParts[2]}-${fNacParts[1]}-${fNacParts[0]}` : '';

                    let srvSelect = document.getElementById('vTHSERCODIGO');
                    let srvDesc = srvSelect ? srvSelect.options[srvSelect.selectedIndex].text.replace(/\s+/g, ' ').trim() : '';

                    let profSelect = document.getElementById('vPRESNUMINT_NUM_INTERNO_PREST');
                    let profDesc = profSelect ? profSelect.options[profSelect.selectedIndex].text.replace(/\s+/g, ' ').trim() : '';

                    let espSelect = document.getElementById('vESPECODIGO_ESPECIALIDAD');
                    let espDesc = espSelect ? espSelect.options[espSelect.selectedIndex].text.replace(/\s+/g, ' ').trim() : '';

                    let userNameSpan = document.getElementById('span_MPW0024vUSUANOMBRE');
                    let nombreOperador = userNameSpan ? userNameSpan.innerText.trim() : '';

                    let payload = {
                        es_sesionado: true,
                        operador: nombreOperador,
                        afiliado: nroAfiliado,
                        fuerza: document.getElementById('actis_fuerza_input')?.value || '',
                        numero_turno: document.getElementById('actis_nroturno_input')?.value || '',
                        dni: dni_limpio,
                        nombre: nombre_p,
                        apellido: apellido_p,
                        fecha_nac: fecha_nac_sql,
                        servicio: srvDesc,
                        profesional: profDesc,
                        especialidad: espDesc,
                        practicas: 'TURNOS SESIONADOS MULTIPLES'
                    };
                    GM_setValue('actis_turno_temp_payload', JSON.stringify(payload));
                });
            }
        }, 500);
    }

    // -------------------------------------------------------------------------
    // LÓGICA EN SGPS: PANTALLAS DE ÉXITO (Turno Simple y Turno Sesionado)
    // -------------------------------------------------------------------------
    if (window.location.href.includes('thmensajeturno.aspx') || window.location.href.includes('thmensajeturnossesionado.aspx')) {
        // Inyectamos CSS para ocultar los botones inútiles y el spam de continuar
        let style = document.createElement('style');
        style.innerHTML = `
            #BOTONCOMPROBANTE, #BOTONENVIAMAIL, #BOTONCARATULA, #TEXTBLOCK2 { display: none !important; }
            input[value="S\xED"], input[value="Si"], input[value="No"] { display: none !important; }
        `;
        document.head.appendChild(style);

        let checkExito = setInterval(() => {
            // Ocultamos el texto de spam si existe
            document.querySelectorAll('span, p, div').forEach(el => {
                if(el.innerText && el.innerText.includes('Desea continuar dando turnos')) {
                    el.style.display = 'none';
                }
            });

            // Buscamos el botón ancla dependiendo de la pantalla
            let btnCaratula = document.getElementById('BOTONCARATULA') || document.getElementById('BUTTON3');

            // Solo creamos el botón de WhatsApp porque ACTIS se envía solo
            if (btnCaratula && !document.getElementById('BOTONWSP')) {
                clearInterval(checkExito);

                let btnWsp = document.createElement('input');
                btnWsp.type = 'button';
                btnWsp.id = 'BOTONWSP';
                btnWsp.value = 'Copiar para WhatsApp';
                btnWsp.className = 'Button';
                btnWsp.style.cssText = "background-color: #25D366; color: white; margin-left: 5px; font-weight: bold; border-color: #128C7E; cursor: pointer;";

                btnCaratula.parentNode.insertBefore(btnWsp, btnCaratula.nextSibling);

                let btnEmail = document.createElement('input');
                btnEmail.type = 'button';
                btnEmail.id = 'BOTONEMAIL';
                btnEmail.value = 'Mandar por Mail';
                btnEmail.className = 'Button';
                btnEmail.style.cssText = "background-color: #3b82f6; color: white; margin-left: 5px; font-weight: bold; border-color: #2563eb; cursor: pointer;";

                btnCaratula.parentNode.insertBefore(btnEmail, btnWsp.nextSibling);

                let btnOtroTurno = document.createElement('input');
                btnOtroTurno.type = 'button';
                btnOtroTurno.id = 'BOTONOTROTURNO';
                btnOtroTurno.value = 'Sacar otro turno al mismo paciente';
                btnOtroTurno.className = 'Button';
                btnOtroTurno.style.cssText = "background-color: #8b5cf6; color: white; margin-left: 5px; font-weight: bold; border-color: #7c3aed; cursor: pointer;";
                
                btnCaratula.parentNode.insertBefore(btnOtroTurno, btnEmail.nextSibling);

                btnOtroTurno.addEventListener('click', (e) => {
                    e.preventDefault();
                    let payloadStr = GM_getValue('actis_turno_temp_payload');
                    if (payloadStr) {
                        let data = JSON.parse(payloadStr);
                        GM_setValue('actis_fastforward_dni', data.dni);
                        GM_setValue('actis_fastforward_active', true);
                        
                        btnOtroTurno.value = 'Redirigiendo...';
                        btnOtroTurno.style.backgroundColor = '#10b981';
                        
                        // Obtenemos la URL fresca del menú para que no se rompa el sistema
                            let freshUrl = 'thturnobusquedaporturnost.aspx?JSYeX/1RlFufbgnDFQXpHmMKRRhBmF5JxhKPEj4q0G5XPHhbLIf+2BQaJCvMK17w'; // Fallback por defecto a la URL solicitada
                            let elements = document.querySelectorAll('a, span, div, li, td');
                            for (let el of elements) {
                                let txt = el.innerText || el.textContent;
                                if (txt && txt.trim().toLowerCase() === 'asignación de turnos') {
                                    // Si es un enlace 'a', tomamos el href. Si es TD con onclick, extraemos la URL
                                    if (el.tagName === 'A' && el.href) {
                                        freshUrl = el.href;
                                    } else {
                                        let outerHTML = el.outerHTML;
                                        let match = outerHTML.match(/uswlink\.aspx\?[^'"\\]+/);
                                        if (match) freshUrl = match[0];
                                    }
                                    break;
                                }
                            }
                        
                        // Crear el modal tal como solicitó el usuario
                        let overlay = document.createElement('div');
                        overlay.style.cssText = 'position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 999999; display: flex; justify-content: center; align-items: center;';
                        
                        let modalBox = document.createElement('div');
                        modalBox.style.cssText = 'background: white; padding: 30px; border-radius: 10px; text-align: center; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border: 3px solid #10b981; font-family: Arial, sans-serif; min-width: 300px;';
                        
                        let titulo = document.createElement('h2');
                        titulo.innerText = '✔️ DNI Copiado con Éxito';
                        titulo.style.cssText = 'color: #10b981; margin-top: 0;';
                        
                        let subtitulo = document.createElement('p');
                        subtitulo.innerText = 'El DNI del paciente (' + data.dni + ') ha sido guardado.\nHaz clic en el botón de abajo para ir a la búsqueda de turnos y continuar el proceso.';
                        subtitulo.style.cssText = 'font-size: 16px; color: #333; margin-bottom: 25px; line-height: 1.5;';
                        
                        let btnRedirect = document.createElement('button');
                        btnRedirect.innerText = 'Ir a Sacar Turno 🚀';
                        btnRedirect.style.cssText = 'background: #3b82f6; color: white; border: none; padding: 15px 30px; font-size: 18px; font-weight: bold; border-radius: 5px; cursor: pointer; transition: 0.3s; width: 100%;';
                        btnRedirect.onmouseover = () => btnRedirect.style.background = '#2563eb';
                        btnRedirect.onmouseout = () => btnRedirect.style.background = '#3b82f6';
                        
                        btnRedirect.addEventListener('click', () => {
                            btnRedirect.innerText = 'Redirigiendo...';
                            btnRedirect.style.background = '#10b981';
                            window.location.href = freshUrl;
                        });
                        
                        let btnCancelar = document.createElement('button');
                        btnCancelar.innerText = 'Cancelar';
                        btnCancelar.style.cssText = 'background: #cbd5e1; color: #334155; border: none; padding: 10px 20px; font-size: 14px; font-weight: bold; border-radius: 5px; cursor: pointer; margin-top: 15px; width: 100%;';
                        btnCancelar.addEventListener('click', () => {
                            document.body.removeChild(overlay);
                        });
                        
                        modalBox.appendChild(titulo);
                        modalBox.appendChild(subtitulo);
                        modalBox.appendChild(btnRedirect);
                        modalBox.appendChild(btnCancelar);
                        overlay.appendChild(modalBox);
                        document.body.appendChild(overlay);
                    }
                });

                btnEmail.addEventListener('click', (e) => {
                    e.preventDefault();
                    let payloadStr = GM_getValue('actis_turno_temp_payload');
                    if (!payloadStr) return;
                    let data = JSON.parse(payloadStr);
                    data.tipo_accion = data.es_reprogramacion ? 'reprogramado' : 'asignado';
                    
                    let originalText = btnEmail.value;
                    let originalBg = btnEmail.style.backgroundColor;
                    btnEmail.value = 'Enviando...';
                    
                    GM_xmlhttpRequest({
                        method: "POST",
                        url: 'https://federicogonzalez.net/actis/api_enviar_email.php',
                        data: JSON.stringify(data),
                        headers: { "Content-Type": "application/json" },
                        onload: function(response) {
                            try {
                                let res = JSON.parse(response.responseText);
                                if(res.status === 'success') {
                                    btnEmail.value = '¡Enviado! ✔';
                                    btnEmail.style.backgroundColor = '#10b981';
                                } else {
                                    alert(res.message);
                                    btnEmail.value = originalText;
                                }
                            } catch(err) {
                                alert("Error al procesar la respuesta.");
                                btnEmail.value = originalText;
                            }
                            setTimeout(() => { btnEmail.value = originalText; btnEmail.style.backgroundColor = originalBg; }, 3000);
                        }
                    });
                });

                // Función para mostrar pantalla de carga
                function mostrarPantallaCarga(data) {
                    // DESACTIVADA A PETICIÓN DEL USUARIO: No mostrar overlay al grabar turno
                }
                
                function actualizarEstadoNuevo(nro, isError = false) {
                    let el = document.getElementById('actis_status_nuevo');
                    if (el) {
                        if (isError) {
                            el.style.color = '#ef4444';
                            el.style.borderColor = '#ef4444';
                            // Ahora nro contiene el mensaje de error real, no lo piso!
                            el.innerHTML = `⚠️ ` + nro;
                        } else {
                            if (nro.includes("Paginando")) {
                                el.style.color = '#38bdf8';
                                el.style.borderColor = '#38bdf8';
                                el.innerHTML = `🔄 ` + nro;
                            } else {
                                el.style.color = '#10b981';
                                el.style.borderColor = '#10b981';
                                el.innerHTML = `¡ÉXITO! Turno Nuevo Encontrado: <strong style="font-size:22px; color:white;">${nro}</strong>`;
                            }
                        }
                    }
                }

                function ocultarPantallaCarga() {
                    let el = document.getElementById('actis_loading_overlay');
                    if(el) { el.style.opacity = '0'; setTimeout(() => el.remove(), 300); }
                }

                // Función auxiliar para extraer múltiples fechas del input oculto de GeneXus (Sesionados)
                function obtenerFechasSesionadas() {
                    try {
                        let gxStateRaw = document.getElementsByName('GXState')[0]?.value;
                        if(gxStateRaw) {
                            let stateObj = JSON.parse(gxStateRaw);
                            let turnosArray = stateObj.vSDT_TURNOS_SELECCIONADOS || [];
                            return turnosArray;
                        }
                    } catch(e) { return []; }
                    return [];
                }

                btnWsp.addEventListener('click', (e) => {
                    e.preventDefault();
                    let payloadStr = GM_getValue('actis_turno_temp_payload');
                    if (!payloadStr) { alert("Error: No se encontró la información del turno."); return; }

                    try {
                        let data = JSON.parse(payloadStr);
                        let msjWsp = `🏥 *POLICLÍNICA GENERAL ACTIS*\n`;
                        if (data.es_reprogramacion) {
                            msjWsp += `🔄 *TURNO REPROGRAMADO*\n\n`;
                        } else {
                            msjWsp += `✅ *TURNO CONFIRMADO*\n\n`;
                        }
                        msjWsp += `👤 *Paciente:* ${data.apellido}, ${data.nombre}\n`;
                        if (data.dni) msjWsp += `🪪 *DNI:* ${data.dni}\n`;
                        if (data.afiliado) msjWsp += `💳 *Nro. Afiliado:* ${data.afiliado}\n`;
                        if (data.fuerza && data.fuerza !== 'N/A') msjWsp += `🎖️ *Fuerza:* ${data.fuerza}\n`;

                        if (data.es_sesionado) {
                            let arrTurnos = obtenerFechasSesionadas();
                            msjWsp += `🩺 *Servicio:* ${data.servicio || 'Nefrología'}\n`;
                            if (data.profesional) msjWsp += `👨‍⚕️ *Profesional:* ${data.profesional}\n`;
                            msjWsp += `\n📅 *Fechas Asignadas:*\n`;
                            arrTurnos.forEach(t => {
                                let fParts = t.Fecha.split('-');
                                let hStr = t.Hora.substring(0,2) + ':' + t.Hora.substring(2,4);
                                msjWsp += `🔹 ${fParts[2]}/${fParts[1]}/${fParts[0]} a las ${hStr} hs\n`;
                            });
                        } else {
                            let fechaParts = data.fecha ? data.fecha.split('-') : [];
                            let fechaFormateada = (fechaParts.length === 3) ? `${fechaParts[2]}/${fechaParts[1]}/${fechaParts[0]}` : data.fecha;
                            msjWsp += `📅 *Fecha del Turno:* ${fechaFormateada}\n`;
                            msjWsp += `⏰ *Hora:* ${data.hora} hs\n`;
                            msjWsp += `🩺 *Servicio:* ${data.servicio}\n`;
                            if (data.profesional) msjWsp += `👨‍⚕️ *Profesional:* ${data.profesional}\n`;
                            if (data.practicas) msjWsp += `📝 *Práctica:* ${data.practicas}\n`;
                        }

                        let ahora = new Date();
                        let fechaEmitido = ahora.toLocaleDateString('es-AR') + ' ' + ahora.toLocaleTimeString('es-AR', {hour: '2-digit', minute:'2-digit'});
                        msjWsp += `\n📌 _Por favor, recuerde sacar su número de validación el día de su turno en la entrada de la Policlínica, desde la ventanilla o el Tótem._\n`;
                        msjWsp += `\n⚠️ *POR FAVOR RESPONDA A ESTE MENSAJE CONFIRMANDO O RECHAZANDO EL TURNO*\n`;
                        let primerNombreOp = (data.operador || 'ACTIS').trim().split(' ')[0];
                        let opCorto = primerNombreOp.charAt(0).toUpperCase() + primerNombreOp.slice(1).toLowerCase();
                        msjWsp += `\n🕒 _Emitido el: ${fechaEmitido}_ por: *${opCorto}*`;

                        navigator.clipboard.writeText(msjWsp).then(() => {
                            let originalText = btnWsp.value;
                            let originalBg = btnWsp.style.backgroundColor;
                            btnWsp.value = '¡Copiado! ✔';
                            btnWsp.style.backgroundColor = '#128C7E';
                            setTimeout(() => { btnWsp.value = originalText; btnWsp.style.backgroundColor = originalBg; }, 2000);
                        }).catch(err => { alert("No se pudo copiar automáticamente."); });
                    } catch(err) { alert("Error al formatear el mensaje de WhatsApp."); }
                });

                (async function autoEnviarActis() {
                    let payloadStr = GM_getValue('actis_turno_temp_payload');
                    if (!payloadStr) return;

                    let data = JSON.parse(payloadStr);
                    
                    if (data.es_reprogramacion) {
                        mostrarPantallaCarga(data); // <-- Muestra pantalla negra con el viejo
                    } else {
                        // Pantalla normal para turnos nuevos
                        mostrarPantallaCarga(data); 
                        let el = document.getElementById('actis_status_nuevo');
                        if (el) el.style.display = 'none';
                    }
                    
                    // INTENTO 1: EXTRAER NRO DE TURNO DEL TEXTO VERDE
                    if (!data.numero_turno || data.numero_turno === '') {
                        let matchNuevoNro = document.body.innerText.match(/Nro\.?\s*(\d{5,})/i);
                        if (matchNuevoNro && matchNuevoNro[1]) {
                            data.numero_turno = matchNuevoNro[1];
                            GM_setValue('actis_turno_temp_payload', JSON.stringify(data));
                            actualizarEstadoNuevo(data.numero_turno);
                        }
                    }
                    
                    // INTENTO 1.5: EXTRAER DE GXSTATE O HTML OCULTO
                    if (!data.numero_turno || data.numero_turno === '') {
                        let htmlTodo = document.documentElement.innerHTML;
                        // Busca si el nro de turno quedó en algún JSON o variable escondida
                        let matchOculto = htmlTodo.match(/["']?THTUNUMERO["']?\s*:\s*["']?(\d{5,})["']?/i);
                        if (matchOculto && matchOculto[1]) {
                            data.numero_turno = matchOculto[1];
                            GM_setValue('actis_turno_temp_payload', JSON.stringify(data));
                            actualizarEstadoNuevo(data.numero_turno);
                        }
                    }
                    
                    // INTENTO 2: SI FALLÓ, USAR UNA VENTANA OCULTA (Para evitar el 403 Forbidden del Iframe)
                    if (!data.es_sesionado && (!data.numero_turno || data.numero_turno === '')) {
                        await new Promise((resolve) => {
                            let ventanita = null;
                            try {
                                ventanita = window.open('thturnoshistorial.aspx', 'actis_hidden', 'width=100,height=100,left=-2000,top=-2000');
                            } catch(e) {
                                actualizarEstadoNuevo('⚠️ Pop-up bloqueado. Active las ventanas emergentes.', true);
                                resolve();
                                return;
                            }
                            
                            if (!ventanita) {
                                actualizarEstadoNuevo('⚠️ Pop-up bloqueado. Active las ventanas emergentes.', true);
                                resolve();
                                return;
                            }
                            
                            let intentos = 0;
                            let maxIntentos = 30; // 15 segundos (más tiempo por si hay que paginar)
                            let paginaActual = 1;
                            let pollInterval = setInterval(() => {
                                intentos++;
                                if (intentos > maxIntentos) {
                                    clearInterval(pollInterval);
                                    try {
                                        let idoc = ventanita.document;
                                        let rows = idoc.querySelectorAll('tr[id^="Turnos_grid2ContainerRow_"]');
                                        actualizarEstadoNuevo(`Filas pág ${paginaActual}: ${rows.length}. Turno no hallado.`, true);
                                    } catch(e) {
                                        actualizarEstadoNuevo(`Error de seguridad 403/CORS en ventana.`, true);
                                    }
                                    if(ventanita) ventanita.close();
                                    resolve();
                                    return;
                                }
                                
                                try {
                                    let idoc = ventanita.document;
                                    let rows = idoc.querySelectorAll('tr[id^="Turnos_grid2ContainerRow_"]');
                                    if (rows.length === 0) return; // Esperar a que cargue
                                    
                                    let fPayload = data.fecha;
                                    if (fPayload && fPayload.includes('-')) {
                                        let p = fPayload.split('-');
                                        if (p.length === 3) fPayload = p[2] + '/' + p[1] + '/' + p[0];
                                    }

                                    let encontrado = false;
                                    for (let i = rows.length - 1; i >= 0; i--) {
                                        let row = rows[i];
                                        let idx = row.id.split('_').pop();
                                        let fGrid = idoc.getElementById('span_vFECHAGRID2_' + idx)?.innerText.trim() || '';
                                        let hGrid = idoc.getElementById('span_vHORAGRID2_' + idx)?.innerText.trim() || '';
                                        
                                        if (fGrid === fPayload && hGrid.startsWith(data.hora)) {
                                            let nro = idoc.getElementById('span_THTUNUMERO_' + idx)?.innerText.trim();
                                            if (nro && !isNaN(nro)) {
                                                data.numero_turno = nro;
                                                GM_setValue('actis_turno_temp_payload', JSON.stringify(data));
                                                clearInterval(pollInterval);
                                                actualizarEstadoNuevo(data.numero_turno);
                                                encontrado = true;
                                                if(ventanita) ventanita.close();
                                                setTimeout(() => { resolve(); }, 1500);
                                                return;
                                            }
                                        }
                                    }
                                    
                                    // Si no lo encontró en esta página, intentamos pasar a la siguiente
                                    if (!encontrado && intentos % 3 === 0) { // Cada 1.5s intentamos paginar si no apareció
                                        let btnNext = idoc.getElementById('SIGUIENTE');
                                        
                                        // Si existe el botón y no parece estar deshabilitado
                                        if (btnNext && !btnNext.disabled && btnNext.style.display !== 'none' && !btnNext.className.includes('Disabled') && !btnNext.src.includes('Disabled')) {
                                            actualizarEstadoNuevo(`Paginando... Buscando en pág ${paginaActual + 1}`);
                                            // Forzar click en GeneXus
                                            var evt = new MouseEvent("click", { bubbles: true, cancelable: true, view: ventanita });
                                            btnNext.dispatchEvent(evt);
                                            
                                            paginaActual++;
                                            // Le damos tiempo extra para que cargue la nueva página
                                            intentos -= 2; 
                                        }
                                    }
                                } catch(e) {}
                            }, 500);
                        });
                    }
                    
                    if (data.numero_turno) {
                        actualizarEstadoNuevo(data.numero_turno);
                        await new Promise(r => setTimeout(r, 1000));
                    } else {
                        actualizarEstadoNuevo('Extracción fallida. No se guardará en ACTIS para evitar duplicados sin número.', true);
                        await new Promise(r => setTimeout(r, 4000));
                        ocultarPantallaCarga();
                        return; // DETENEMOS LA EJECUCIÓN ACÁ, NO ENVIAMOS A LA API
                    }
                    // =================================================================

                    let arrayDePayloads = [];

                    if (data.es_sesionado) {
                        let arrTurnos = obtenerFechasSesionadas();
                        if(arrTurnos.length === 0) {
                            ocultarPantallaCarga();
                            alert("No se pudieron detectar las fechas de los turnos sesionados.");
                            return;
                        }

                        // CAPTURA EXTREMA DE LA PRÁCTICA (Desde la pantalla final)
                        let codPracFinal = document.getElementById('span_vTHTUPRCODPRACTICA')?.innerText.trim() || '';
                        let descPracFinal = document.getElementById('span_vNOMEDESBRE_DESC_BREVE_NOMENC')?.innerText.trim() || '';
                        if (descPracFinal !== '') {
                            data.practicas = `[${codPracFinal}] ${descPracFinal}`;
                        }

                        // Preparamos el array de fechas para PHP
                        data.turnos_multiples = [];
                        arrTurnos.forEach(t => {
                            data.turnos_multiples.push({
                                fecha: t.Fecha,
                                hora: t.Hora.substring(0,2) + ':' + t.Hora.substring(2,4) + ':00'
                            });
                        });

                        // Mandamos un solo payload "Padre" que adentro tiene el array de fechas
                        arrayDePayloads.push(data);

                    } else {
                        arrayDePayloads.push(data); // Turno normal, va uno solo
                    }

                    let todosExitosos = true;

                    // Enviamos los turnos a la API uno por uno
                    for (let i = 0; i < arrayDePayloads.length; i++) {
                        await new Promise((resolve) => {
                            GM_xmlhttpRequest({
                                method: "POST",
                                url: urlAPIActis,
                                data: JSON.stringify(arrayDePayloads[i]),
                                headers: { "Content-Type": "application/json" },
                                onload: function(response) {
                                    try {
                                        let res = JSON.parse(response.responseText);
                                        if (res.status !== 'success') todosExitosos = false;
                                    } catch(err) { todosExitosos = false; }
                                    resolve();
                                },
                                onerror: function() { todosExitosos = false; resolve(); }
                            });
                        });
                    }

                    if (todosExitosos) {
                        ocultarPantallaCarga(); // <-- Oculta la pantalla de carga

                        // ---- MODAL DESACTIVADO A PETICIÓN DEL USUARIO ----
                        // El flujo ahora sigue directamente hacia la pantalla de éxito
                        // sin interrumpir ni requerir clics adicionales.
                    } else {
                        ocultarPantallaCarga();
                        console.error("Hubo un error al guardar el turno automáticamente en ACTIS.");
                    }
                })(); // <- Ejecuta la función de Auto-envío
            }
        }, 500);
    }

    // -------------------------------------------------------------------------
    // LÓGICA EN SGPS: PANTALLA HISTORIAL (Detección de Baja de Turnos)
    // -------------------------------------------------------------------------
    if (window.location.href.toLowerCase().includes('thturnoshistorial.aspx') || window.location.href.toLowerCase().includes('thturnobusquedaporturnost.aspx')) {
        const urlAPICancelar = 'https://federicogonzalez.net/actis/api_turnos_cancelar.php';
        
        // 1. Escuchar clic en los íconos de eliminar
        document.body.addEventListener('click', (e) => {
            if (e.target && e.target.id && e.target.id.startsWith('vELIMINAR_ACTION_')) {
                let suffix = e.target.id.replace('vELIMINAR_ACTION_', ''); // e.g. "0001"
                
                // Extraer datos fijos de la cabecera (Paciente)
                let txtDNI_raw = document.getElementById('span_W0022vDOCUMENTOTIPONUMERO')?.innerText || '';
                let dni_limpio = txtDNI_raw.replace(/\D/g, '');
                let nombreCompleto = document.getElementById('span_W0022vEMPEAPENOM')?.innerText.trim() || '';
                let afiliado = document.getElementById('span_W0022vCREDENCIAL')?.innerText.trim() || '';
                let fecha_nac = document.getElementById('span_W0022vEMPEFECNAC')?.innerText.trim() || '';
                
                // Operador
                let userNameSpan = document.getElementById('span_MPW0024vUSUANOMBRE');
                let operador = userNameSpan ? userNameSpan.innerText.trim() : '';

                // Extraer dinámicamente TODO lo que esté en la fila de ese turno
                let rowData = {};
                let rowSpans = document.querySelectorAll(`span[id$="_${suffix}"]`);
                rowSpans.forEach(span => {
                    rowData[span.id] = span.innerText.trim();
                });

                let payload = {
                    operador: operador,
                    dni: dni_limpio,
                    nombre: nombreCompleto,
                    afiliado: afiliado,
                    fecha_nac: fecha_nac,
                    datos_fila: rowData, // <- Acá va toda la info de la grilla (Fecha, Especialidad, etc)
                    motivo: '' // Se llenará en el momento del éxito
                };
                
                GM_setValue('actis_turno_cancelar_payload', JSON.stringify(payload));
            } else if (e.target && e.target.id && e.target.id.startsWith('vREPROGRAMACION_ACTION_')) {
                // ---> CAMBIO REPROGRAMACION: Capturamos el número ANTES de reprogramar <---
                let suffix = e.target.id.replace('vREPROGRAMACION_ACTION_', '');
                let nroTurno = document.getElementById('span_THTUNUMERO_' + suffix)?.innerText.trim() || '';
                
                let oldRowTexts = [];
                let rowSpans = document.querySelectorAll(`span[id$="_${suffix}"]`);
                rowSpans.forEach(span => {
                    if (span.innerText.trim()) {
                        oldRowTexts.push(span.innerText.trim().toUpperCase());
                    }
                });

                if (nroTurno && !isNaN(nroTurno)) {
                    GM_setValue('actis_turno_temp_numero_seleccionado', nroTurno);
                    GM_setValue('actis_turno_reprogramar_old_texts', JSON.stringify(oldRowTexts));
                    GM_setValue('actis_turno_es_realmente_reprogramacion', true);
                    console.log("ACTIS - CAMBIO REPROGRAMACION: Turno viejo capturado: " + nroTurno);
                }
            } else {
                // ---> CAMBIO REPROGRAMACION: Volvemos al original para no pisar el número viejo <---
                let fila = e.target.closest('tr[id^="Gridtd_thturnoContainerRow_"]');
                if (fila) {
                    let suffix = fila.id.split('_').pop();
                    let nroTurno = document.getElementById('span_THTUNUMERO_' + suffix)?.innerText.trim() || '';
                    if (nroTurno && !isNaN(nroTurno)) {
                        GM_setValue('actis_turno_temp_numero_seleccionado', nroTurno);
                        // Asegurarnos de que no lo marque como reprogramación si solo es selección de turno
                        GM_setValue('actis_turno_es_realmente_reprogramacion', false);
                    }
                }
            }
        });

        // 2. Escuchar aparición del mensaje de éxito con un Interval más seguro
        setInterval(() => {
            let spansError = Array.from(document.querySelectorAll('span[id^="ERRDESCRI_"]'));
            
            // Buscar también dentro de los iframes (GeneXus abre los popups de confirmación en iframes)
            let iframes = document.querySelectorAll('iframe');
            iframes.forEach(ifr => {
                try {
                    let iframeDoc = ifr.contentDocument || ifr.contentWindow.document;
                    if (iframeDoc) {
                        let iframeSpans = iframeDoc.querySelectorAll('span[id^="ERRDESCRI_"]');
                        spansError = spansError.concat(Array.from(iframeSpans));
                    }
                } catch(e) {}
            });

            let successFound = false;
            let successNode = null;
            
            for (let span of spansError) {
                if (!span.dataset.actisProcessed && span.innerText && span.innerText.toLowerCase().includes("eliminado con")) {
                    successFound = true;
                    successNode = span;
                    break;
                }
            }

            if (successFound && !document.getElementById('actis_modal_baja')) {
                // Marcar como procesado para que no se haga un bucle infinito
                successNode.dataset.actisProcessed = 'true';
                
                // 1. Ocultar el mensaje nativo y cualquier Popup de GeneXus
                successNode.style.display = 'none';
                if(successNode.parentNode) successNode.parentNode.style.display = 'none';
                
                // Si el popup de GeneXus es un div flotante, lo ocultamos para que no moleste
                let gxPopups = document.querySelectorAll('.gx-popup, .Popup, [id^="gxp0_"]');
                gxPopups.forEach(p => p.style.display = 'none');
                
                // 2. Armar el payload limpio
                let payloadStr = GM_getValue('actis_turno_cancelar_payload');
                if (payloadStr) {
                    let pData = JSON.parse(payloadStr);
                    
                    // Extraer fecha y hora dinámicamente usando las IDs de GeneXus
                    let idFecha = pData.datos_fila ? Object.keys(pData.datos_fila).find(k => k.includes('THTUFECHA')) : null;
                    let idHora = pData.datos_fila ? Object.keys(pData.datos_fila).find(k => k.includes('THTUHORA')) : null;
                    let idServicio = pData.datos_fila ? Object.keys(pData.datos_fila).find(k => k.includes('THSERCODIG')) : null;
                    let idMedico = pData.datos_fila ? Object.keys(pData.datos_fila).find(k => k.includes('vPROFESIONAL') || k.includes('PRESAPENOM')) : null;
                    let idTurnoNum = pData.datos_fila ? Object.keys(pData.datos_fila).find(k => k.includes('THTUNUMERO')) : null;
                    
                    let fecha = pData.fecha || (idFecha ? pData.datos_fila[idFecha].trim() : '');
                    let hora = pData.hora || (idHora ? pData.datos_fila[idHora].trim() : '');
                    
                    pData.fecha = fecha;
                    pData.hora = hora;
                    pData.servicio = pData.servicio || (idServicio ? pData.datos_fila[idServicio].trim() : '');
                    pData.profesional = pData.profesional || (idMedico ? pData.datos_fila[idMedico].trim() : '');
                    pData.numero_turno = pData.numero_turno || (idTurnoNum ? pData.datos_fila[idTurnoNum].trim() : '');
                    
                    pData.motivo = GM_getValue('actis_ultimo_motivo_baja') || '-';
                    // Reset el motivo temporal para el próximo turno
                    GM_setValue('actis_ultimo_motivo_baja', '');
                    
                    GM_setValue('actis_turno_cancelar_payload', JSON.stringify(pData));

                    // Enviar silenciosamente la cancelación a ACTIS
                    GM_xmlhttpRequest({
                        method: "POST",
                        url: urlAPICancelar,
                        data: JSON.stringify(pData),
                        headers: { "Content-Type": "application/json" },
                        onload: function(response) {
                            console.log("ACTIS: Turno cancelado notificado correctamente. Respuesta: " + response.responseText);
                        }
                    });
                    
                    // 3. Dibujar el Modal Gigante
                    let overlay = document.createElement('div');
                    overlay.id = 'actis_modal_baja';
                    overlay.style.cssText = 'position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15,23,42,0.85); z-index:999999; display:flex; justify-content:center; align-items:center; backdrop-filter:blur(8px); opacity:0; transition:opacity 0.3s;';

                    let modal = document.createElement('div');
                    modal.style.cssText = 'background:white; padding:40px; border-radius:20px; text-align:center; box-shadow:0 25px 50px rgba(0,0,0,0.3); max-width:450px; transform:scale(0.8); transition:transform 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275); border-top: 10px solid #ef4444;';
                    
                    let pacienteNombre = pData.nombre || 'Paciente';

                    let htmlBaja = `
                        <div style="font-size:70px; margin-top:-60px; text-shadow: 0 5px 15px rgba(239,68,68,0.3);">⚠️</div>
                        <h2 style="margin:10px 0 5px 0; color:#b91c1c; font-family:'Arial Black', Impact, sans-serif; font-size:26px; text-transform:uppercase; letter-spacing:1px;">TURNO CANCELADO</h2>
                        <p style="color:#ef4444; font-family:Arial, sans-serif; font-size:15px; font-weight:bold; margin:0 0 20px 0;">¡Baja registrada en ACTIS!</p>

                        <div style="background: #fef2f2; border: 1px solid #fecaca; padding: 15px; border-radius: 10px; margin-bottom: 25px; text-align: left;">
                            <p style="margin:0 0 5px 0; font-family:Arial, sans-serif; font-size:14px; color:#991b1b;"><strong>Paciente:</strong> ${pacienteNombre}</p>
                            <p style="margin:0 0 5px 0; font-family:Arial, sans-serif; font-size:14px; color:#991b1b;"><strong>Nro. Afiliado:</strong> <span id="actis_modal_afi">${pData.afiliado || '-'}</span></p>
                            ${pData.numero_turno ? `<p style="margin:0 0 5px 0; font-family:Arial, sans-serif; font-size:14px; color:#991b1b;"><strong>Nro. de Turno:</strong> ${pData.numero_turno}</p>` : ''}
                            <p style="margin:0 0 5px 0; font-family:Arial, sans-serif; font-size:14px; color:#991b1b;"><strong>Servicio:</strong> ${pData.servicio || '-'}</p>
                            <p style="margin:0 0 5px 0; font-family:Arial, sans-serif; font-size:14px; color:#991b1b;"><strong>Profesional:</strong> ${pData.profesional || '-'}</p>
                            <p style="margin:0 0 5px 0; font-family:Arial, sans-serif; font-size:14px; color:#991b1b;"><strong>Fecha:</strong> ${fecha} a las ${hora}</p>
                            <p style="margin:0; font-family:Arial, sans-serif; font-size:14px; color:#991b1b;"><strong>Motivo:</strong> ${pData.motivo || '-'}</p>
                        </div>

                        <div style="display:flex; flex-direction:column; gap:10px;">
                            <button id="btn_wsp_modal_baja" style="background: linear-gradient(180deg, #25D366, #128C7E); color:white; border:none; padding:12px 20px; border-radius:30px; font-weight:bold; font-size:16px; cursor:pointer; box-shadow:0 5px 15px rgba(37,211,102,0.3); transition: transform 0.1s;">📱 Copiar para WhatsApp</button>
                            <button id="btn_email_modal_baja" style="background: linear-gradient(180deg, #3b82f6, #2563eb); color:white; border:none; padding:12px 20px; border-radius:30px; font-weight:bold; font-size:16px; cursor:pointer; box-shadow:0 5px 15px rgba(59,130,246,0.3); transition: transform 0.1s;">✉️ Mandar por Mail</button>
                            <button id="btn_cerrar_modal_baja" style="background: transparent; color:#94a3b8; border:1px solid #cbd5e1; padding:10px 20px; border-radius:30px; font-weight:bold; font-size:14px; cursor:pointer; margin-top:10px;">Cerrar Ventana</button>
                        </div>
                    `;

                    modal.innerHTML = htmlBaja;
                    overlay.appendChild(modal);
                    document.body.appendChild(overlay);

                    setTimeout(() => { overlay.style.opacity = '1'; modal.style.transform = 'scale(1)'; }, 10);

                    // Obtener info real del paciente desde ACTIS
                    let realAfiliado = pData.afiliado;
                    let realFuerza = '';
                    
                    GM_xmlhttpRequest({
                        method: "GET",
                        url: "https://federicogonzalez.net/actis/api_paciente_info.php?dni=" + pData.dni,
                        onload: function(resp) {
                            try {
                                let res = JSON.parse(resp.responseText);
                                if(res.status === 'success') {
                                    if(res.afiliado) {
                                        realAfiliado = res.afiliado;
                                        pData.afiliado = res.afiliado; 
                                        let afiSpan = document.getElementById('actis_modal_afi');
                                        if (afiSpan) afiSpan.innerText = res.afiliado;
                                    }
                                    if(res.fuerza) {
                                        realFuerza = res.fuerza;
                                        pData.fuerza = res.fuerza;
                                    }
                                    // Actualizamos el payload en memoria por si el usuario envia el mail despues
                                    GM_setValue('actis_turno_cancelar_payload', JSON.stringify(pData));
                                }
                            } catch(e) {}
                        }
                    });
                    
                    // Lógicas de botones del Modal
                    document.getElementById('btn_cerrar_modal_baja').addEventListener('click', () => {
                        overlay.style.opacity = '0';
                        modal.style.transform = 'scale(0.8)';
                        setTimeout(() => { 
                            overlay.remove(); 
                            window.location.reload(); 
                        }, 300);
                    });
                    
                    let btnWspBaja = document.getElementById('btn_wsp_modal_baja');
                    btnWspBaja.addEventListener('click', (e) => {
                        e.preventDefault();
                        let msj = `🏥 *POLICLÍNICA GENERAL ACTIS*\n`;
                        msj += `❌ *AVISO DE CANCELACIÓN*\n\n`;
                        msj += `👤 *Paciente:* ${pacienteNombre}\n`;
                        if(pData.dni) msj += `🪪 *DNI:* ${pData.dni}\n`;
                        if(realAfiliado && realAfiliado !== '-') msj += `💳 *Nro. Afiliado:* ${realAfiliado}\n`;
                        if(realFuerza && realFuerza !== 'N/A') msj += `🎖️ *Fuerza:* ${realFuerza}\n`;
                        if(fecha) msj += `📅 *Fecha:* ${fecha}\n`;
                        if(hora) msj += `⏰ *Hora:* ${hora}\n`;
                        if(pData.servicio) msj += `🩺 *Servicio:* ${pData.servicio}\n`;
                        if(pData.profesional) msj += `👨‍⚕️ *Profesional:* ${pData.profesional}\n`;
                        if(pData.motivo) msj += `\n💬 *Motivo:* ${pData.motivo}\n`;
                        msj += `\n_Si considera que esto es un error, por favor comuníquese a la brevedad._`;
                        
                        let ahora = new Date();
                        let fechaEmitido = ahora.toLocaleDateString('es-AR') + ' ' + ahora.toLocaleTimeString('es-AR', {hour: '2-digit', minute:'2-digit'});
                        let primerNombreOp = (pData.operador || 'ACTIS').trim().split(' ')[0];
                        let opCorto = primerNombreOp.charAt(0).toUpperCase() + primerNombreOp.slice(1).toLowerCase();
                        msj += `\n\n🕒 _Emitido el: ${fechaEmitido}_ por: *${opCorto}*`;
                        
                        navigator.clipboard.writeText(msj).then(() => {
                            let oldText = btnWspBaja.innerText;
                            btnWspBaja.innerText = '¡Copiado! ✅';
                            setTimeout(()=> { btnWspBaja.innerText = oldText; }, 2000);
                        }).catch(err => alert("Error copiando texto."));
                    });
                    
                    let btnEmailBaja = document.getElementById('btn_email_modal_baja');
                    btnEmailBaja.addEventListener('click', (e) => {
                        e.preventDefault();
                        pData.tipo_accion = 'cancelado';
                        let oldText = btnEmailBaja.innerText;
                        btnEmailBaja.innerText = 'Enviando...';
                        GM_xmlhttpRequest({
                            method: "POST",
                            url: 'https://federicogonzalez.net/actis/api_enviar_email.php',
                            data: JSON.stringify(pData),
                            headers: { "Content-Type": "application/json" },
                            onload: function(response) {
                                try {
                                    let res = JSON.parse(response.responseText);
                                    if(res.status === 'success') {
                                        btnEmailBaja.innerText = '¡Enviado! ✔';
                                        btnEmailBaja.style.background = 'linear-gradient(180deg, #10b981, #059669)';
                                    } else {
                                        alert(res.message);
                                        btnEmailBaja.innerText = oldText;
                                    }
                                } catch(err) {
                                    alert("Error enviando email.");
                                    btnEmailBaja.innerText = oldText;
                                }
                                setTimeout(()=> { btnEmailBaja.innerText = oldText; btnEmailBaja.style.background = 'linear-gradient(180deg, #3b82f6, #2563eb)'; }, 3000);
                            }
                        });
                    });
                }
            }
        }, 500);
    }

    // =========================================================================
    // MOSTRAR TURNOS ACTIVOS AL LADO DEL BOTÓN BUSCAR AZUL (BUTTON1)
    // -> COMENTADO Y DESACTIVADO A PETICIÓN DEL USUARIO
    // =========================================================================
    /* 
    if (window.location.href.includes("thturnoshistorial.aspx")) {
        setInterval(() => {
            let btnBuscarAzul = document.getElementById('BUTTON1');
            if (btnBuscarAzul) {
                let panelActivos = document.getElementById('panel_actis_activos');
                if (panelActivos) {
                    panelActivos.style.display = 'none';
                }
            }
        }, 500); 
    }
    */

})();