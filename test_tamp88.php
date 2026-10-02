<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Content-Type: application/javascript");
?>
// ==UserScript==
// @name         TEST SISTEMA ACTIS - KIOSCO IOSFA (SOLO DNI)
// @namespace    http://tampermonkey.net/
// @version      19.0.1
// @match        *://validador.iosfa.gob.ar/ValidadorDni*
// @grant        unsafeWindow
// @grant        GM_xmlhttpRequest
// @connect      federicogonzalez.net
// @updateURL    https://federicogonzalez.net/actis/test_tamp88.php
// @downloadURL  https://federicogonzalez.net/actis/test_tamp88.php
// ==/UserScript==

(function() {
    'use strict';

    // INTERCEPTOR NUCLEAR DE ERRORES: Bloquea la reescritura de DevExpress
    const alertaOriginal = unsafeWindow.alert;
    Object.defineProperty(unsafeWindow, 'alert', {
        configurable: false,
        enumerable: true,
        writable: false,
        value: function(mensaje) {
            if (mensaje && (mensaje.includes('Callback request failed') || mensaje.includes('internal server error'))) {
                if (typeof unsafeWindow.ultimaAccionKiosco !== 'undefined') {
                    unsafeWindow.ultimaAccionKiosco = 'Falla de OSFA detectada: Autorecuperando...';
                }
                window.location.replace('https://validador.iosfa.gob.ar/ValidadorDni'); 
                return;
            }
            alertaOriginal(mensaje);
        }
    });

    window.addEventListener('pageshow', function(event) {
        if (event.persisted) {
            window.location.replace('https://validador.iosfa.gob.ar/ValidadorDni');
        }
    });

    const scriptH2C = document.createElement('script');
    scriptH2C.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
    document.head.appendChild(scriptH2C);
    document.querySelectorAll('meta[name="viewport"]').forEach(m => m.remove());
    const metaViewport = document.createElement('meta');
    metaViewport.name = "viewport";
    metaViewport.content = "width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no";
    document.head.appendChild(metaViewport);
    
    const estilo = document.createElement('style');
    estilo.innerHTML = `
        nav.navbar, .dua-tbl, footer, .badge-alertas, #ctl00_UsuarioVtoAlertas1_userAlertas, .row h3:first-of-type { display: none !important; }
        .dxeHelpText_IOSFA, .safi-hint, .red1, #ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btnImprimir { display: none !important; }

        /* OCULTAR POPUPS NATIVOS Y MATAR CAPAS INVISIBLES DE DEVEXPRESS (SECUESTRO DE CLICS Y CUELGUES) */
        .dxpc-mainDiv, .dxpc-shadow, .modal-backdrop, .modal-dialog, .dxILoadingPanelSys, .dxgvLoadingPanelSys, .dxWeb_lp, [class*="LoadingPanel"], [class*="dxpc-overlay"], [class*="dxpc-shadow"], .glassPane, .dxeErrorCellSys, table[id*="Error"] { display: none !important; opacity: 0 !important; visibility: hidden !important; pointer-events: none !important; width: 0 !important; height: 0 !important; z-index: -9999 !important; }

        /* OCULTAR BOTÓN DE VALIDADOR MEJORADO Y CARTEL NATIVO FEO DE IOSFA */
        #ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout_BootstrapButton2 { display: none !important; }
        #ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_divDatosAfiliado { position: absolute !important; left: -9999px !important; top: -9999px !important; opacity: 0 !important; pointer-events: none !important; z-index: -99999 !important; height: 0 !important; overflow: hidden !important; }

        body {
            touch-action: pan-x pan-y !important;
            background: linear-gradient(-45deg, #eff6ff, #dbeafe, #bfdbfe, #93c5fd) !important;
            background-size: 400% 400% !important;
            animation: gradienteDinamico 12s ease infinite !important;
            font-family: 'Segoe UI', sans-serif !important;
            overflow: hidden !important;
            margin: 0 !important;
            padding: 0 !important;
            height: 100vh !important;
            display: flex;
            flex-direction: column;
            -webkit-user-select: none !important;
            user-select: none !important;
            -webkit-touch-callout: none !important;
        }
        @keyframes gradienteDinamico {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .pantalla-oculta { display: none !important; }

        /* COMPONENTES EXTRA */
        .totem-reloj { position: fixed; top: 2vh; right: 260px; background: rgba(15,23,42,0.9); color: white; padding: 1vh 1.5vw; border-radius: 10px; font-weight: 900; font-size: 2.5vh; border: 2px solid #0284c7; z-index: 10000; box-shadow: 0 4px 10px rgba(0,0,0,0.3); }
        .totem-marquesina { position: fixed; bottom: 0; left: 0; width: 100%; background: #0f172a; color: #38bdf8; font-size: 2.2vh; font-weight: 800; padding: 1vh 0; z-index: 10000; overflow: hidden; white-space: nowrap; border-top: 3px solid #0284c7; }
        .totem-estado { position: fixed; bottom: 6vh; right: 1vw; display: flex; flex-direction: column; gap: 0.5vh; z-index: 10000; background: rgba(255,255,255,0.95); padding: 1vh; border-radius: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.15); border: 2px solid #cbd5e1; }
        .estado-item { font-size: 1.5vh; font-weight: 900; color: #334155; display: flex; align-items: center; gap: 0.5vw; text-transform: uppercase; }
        .dot-verde { width: 1.2vh; height: 1.2vh; background: #22c55e; border-radius: 50%; box-shadow: 0 0 8px #22c55e; animation: latido 1.5s infinite; }

        .btn-flotante { position: fixed; bottom: 12vh; z-index: 10000; background: #ffffff; border: 3px solid #0284c7; border-radius: 50px; padding: 1vh 1.5vw; font-weight: 900; font-size: 1.8vh; color: #0284c7; cursor: pointer; box-shadow: 0 5px 15px rgba(0,0,0,0.2); transition: all 0.2s; display: flex; align-items: center; justify-content: center; }
        .btn-flotante:active { transform: scale(0.9); }
        .btn-ayuda { left: 1vw; bottom: 6vh; background: #fee2e2; border-color: #dc2626; color: #dc2626; z-index: 9999998 !important; }
        #modal-ayuda { z-index: 9999999 !important; }

        .btn-navegacion { bottom: auto; top: 2vh; font-size: 2vh; padding: 1vh 2vw; border-radius: 12px; height: 6vh; }
        .btn-volver { left: 1vw; background: #f1f5f9; border-color: #64748b; color: #64748b; }
        .btn-home { left: 12vw; background: #e0f2fe; border-color: #0284c7; color: #0284c7; }

        .actis-header {
            background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(12px); -webkit-backdrop-filter: blur(12px);
            padding: 1.5vh 0; text-align: center; border-bottom: 2px solid rgba(2, 132, 199, 0.5); box-shadow: 0 8px 32px 0 rgba(0, 0, 0, 0.3); flex-shrink: 0;
            display: flex; flex-direction: column; align-items: center; justify-content: center; transition: all 0.3s ease;
        }
        .actis-header img { height: 8vh; margin-bottom: 0.5vh; object-fit: contain; }
        .actis-header h1 { color: #ffffff; font-size: 3.5vh; font-weight: 900; margin: 0; letter-spacing: 0.5px; }
        .actis-header p { font-size: 1.8vh; color: #94a3b8; font-weight: 800; margin: 4px 0 0 0; text-transform: uppercase; letter-spacing: 1px; }

        /* DISEÑO DE BOTONES PRINCIPALES MUNDIALISTA */
        .contenedor-modos { display: flex; justify-content: center; align-items: center; gap: 4vw; margin: 0; width: 100%; flex-grow: 1; padding: 0 5vw; box-sizing: border-box; }
        .btn-modo { flex: 1; max-width: 400px; height: 34vh; border-radius: 30px; border: 3px solid rgba(255,255,255,0.6); font-size: 3.8vh; font-weight: 900; color: white; cursor: pointer;
        backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); box-shadow: 0 8px 32px 0 rgba(0,0,0,0.2), inset 0 0 20px rgba(255,255,255,0.3); transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); position: relative; overflow: hidden; animation: pulsoBordeModo 2s infinite; z-index: 99999 !important; pointer-events: auto !important; }
        @keyframes pulsoBordeModo {
            0% { box-shadow: 0 8px 32px 0 rgba(0,0,0,0.2), 0 0 0 0 rgba(116, 172, 223, 0.6); }
            70% { box-shadow: 0 8px 32px 0 rgba(0,0,0,0.2), 0 0 0 20px rgba(116, 172, 223, 0); }
            100% { box-shadow: 0 8px 32px 0 rgba(0,0,0,0.2), 0 0 0 0 rgba(116, 172, 223, 0); }
        }
        .btn-modo::after { content: ''; position: absolute; top: 0; left: -100%; width: 50%; height: 100%;
        background: linear-gradient(to right, rgba(255,255,255,0) 0%, rgba(255,255,255,0.6) 50%, rgba(255,255,255,0) 100%); transform: skewX(-25deg); animation: olaLuz 4s infinite; }
        @keyframes olaLuz { 0% { left: -100%; } 20% { left: 200%; } 100% { left: 200%; } }
        .btn-modo:active { transform: translateY(8px) scale(0.96); box-shadow: 0 5px 15px rgba(0,0,0,0.2); }
        /* BOTONES GEMELOS CELESTES CON SÚPER EFECTOS */
        .btn-asistencia, .btn-validacion { 
            background: linear-gradient(135deg, rgba(116, 172, 223, 0.95), rgba(56, 146, 208, 0.95)); 
            color: #ffffff; 
            text-shadow: 2px 2px 6px rgba(0,0,0,0.5); 
            border: 4px solid rgba(255, 255, 255, 0.95) !important; 
            box-shadow: 0 12px 35px rgba(0,0,0,0.3), inset 0 0 30px rgba(255,255,255,0.6); 
        }
        @keyframes movimientoFondo { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }

        .loading-kiosco { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(255, 255, 255, 0.98); display: flex; flex-direction: column; justify-content: center; align-items: center; z-index: 99999 !important; }
        .spinner-kiosco { width: 10vh; height: 10vh; border: 8px solid #e2e8f0; border-top: 8px solid #0284c7; border-radius: 50%; animation: girarSpinner 1s linear infinite; margin-bottom: 3vh; }
        @keyframes girarSpinner { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .loading-texto { font-size: 4vh; font-weight: 900; color: #0284c7; letter-spacing: 1px; text-transform: uppercase; }

        /* DESTRUIMOS EL CAMPO CREDENCIAL Y OCULTAMOS BASURA NATIVA SIN DESPERTAR CELDAS OCULTAS */
        .dxflEmptyItemSys, .dxflBottomMarginSys { display: none !important; }
        div[id*="Credencial"], div[id*="NroCredencial"], label[for*="Credencial"] { display: none !important; }
        /* TRUCO MAGICO: Hacemos invisible el texto fantasma de Credencial sin romper el diseño */
        #ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout label { font-size: 0 !important; color: transparent !important; margin: 0 !important; padding: 0 !important; }
        #ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout label b { font-size: 3.5vh !important; color: #334155 !important; }

        /* CLASE OCULTA FORTALECIDA PARA EVITAR OVERLAP DE DEVEXPRESS */
        #ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout.pantalla-oculta, .pantalla-oculta { display: none !important; }

        /* DISEÑO CALCULADORA ULTRA COMPACTA: TODO PEGADO Y CENTRADO */
        #ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout label { font-size: 0 !important; color: transparent !important; margin: 0 !important; padding: 0 !important; display: none !important; }
        #ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout label b { font-size: 3vh !important; color: #334155 !important; display: block !important; text-align: center !important; width: 100% !important; margin-bottom: 1vh !important; line-height: 1 !important; }

        #ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout {
            position: relative !important;
            margin: auto !important; /* Centrado perfecto dinámico en el espacio sobrante del flexbox padre */
            background: rgba(255, 255, 255, 0.98) !important;
            backdrop-filter: blur(15px) !important;
            -webkit-backdrop-filter: blur(15px) !important;
            padding: 2vh 2vw !important;
            border-radius: 20px !important;
            box-shadow: 0 15px 50px rgba(0,0,0,0.4) !important;
            border: 4px solid #94a3b8 !important;
            display: flex !important;
            flex-direction: column !important;
            align-items: center !important;
            justify-content: flex-start !important;
            width: 85% !important;
            max-width: 420px !important;
            height: 60vh !important; /* Tope estricto, la caja no crece más allá de esto */
            gap: 1vh !important;
            z-index: 90000 !important;
            box-sizing: border-box !important;
        }
        .col-md-4, .col-lg-4 { width: 100% !important; float: none !important; padding: 0 !important; margin: 0 !important; display: block !important; height: auto !important; }

        /* 1. INPUT DNI MAQUETADO EN PORCENTAJE (Para que no rompa el alto) */
        [id$="txtDNI"], .dxeButtonEditSys { border-radius: 12px !important; border: 3px solid #0284c7 !important; height: 16% !important; width: 100% !important; background: #f8fafc !important; overflow: hidden !important; display: flex !important; align-items: center !important; order: 1 !important; box-shadow: inset 0 3px 6px rgba(0,0,0,0.05) !important; margin: 0 !important; }
        [id$="txtDNI"] > div, .dxeButtonEditSys > div { width: 100% !important; height: 100% !important; }
        [id$="txtDNI_I"], .dxeEditAreaSys { height: 100% !important; text-align: center !important; font-weight: 900 !important; color: #0f172a !important; width: 100% !important; font-size: 3.5vh !important; padding: 0 !important; margin: 0 !important; line-height: normal !important; border: none !important; background: transparent !important; box-shadow: none !important; outline: none !important; letter-spacing: 2px !important; }

        /* 2. TECLADO COMPACTO MAQUETADO EN PORCENTAJES Y GRID STRICTO */
        .teclado-kiosco { display: grid; grid-template-columns: repeat(3, 1fr); grid-template-rows: repeat(4, 1fr); gap: 0.8vh 1vw; margin: 0 !important; width: 100%; order: 2 !important; z-index: 99999 !important; pointer-events: auto !important; height: 55% !important; }
        .kbtn { background: #ffffff !important; border: 2px solid #e2e8f0 !important; border-radius: 10px !important; font-size: 3.5vh !important; font-weight: 900 !important; color: #334155 !important; box-shadow: 0 4px 0 #cbd5e1 !important; cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all 0.1s ease; height: 100% !important; z-index: 99999 !important; pointer-events: auto !important; padding: 0 !important; }
        .kbtn:active { transform: translateY(4px) !important; box-shadow: 0 0 0 #cbd5e1 !important; background: #f1f5f9 !important; }
        .kbtn-borrar { background: #fee2e2 !important; border-color: #fca5a5 !important; color: #dc2626 !important; font-size: 2vh !important; grid-column: span 2; box-shadow: 0 4px 0 #f87171 !important; text-transform: uppercase; }
        .kbtn-borrar:active { box-shadow: 0 0 0 #f87171 !important; transform: translateY(4px) !important; }

        /* 3. BOTON CONFIRMAR MAQUETADO EN PORCENTAJE */
        #ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout_btnBuscarAfiliado {
            display: block !important; height: 15% !important; font-size: 2.5vh !important; background: linear-gradient(180deg, #22c55e, #16a34a) !important; color: white !important; border-radius: 12px !important; width: 100% !important; font-weight: 900 !important; box-shadow: 0 4px 0 #15803d !important; border: none !important; text-transform: uppercase !important; cursor: pointer !important; position: relative !important; bottom: auto !important; left: auto !important; transform: none !important; z-index: 1000 !important; order: 3 !important; margin: 0 !important;
            opacity: 0 !important; pointer-events: none !important; transition: opacity 0.3s ease !important; align-self: flex-end !important; margin-top: auto !important;
        }
        #ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout_btnBuscarAfiliado.visible {
            opacity: 1 !important; pointer-events: auto !important;
        }
        #ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout_btnBuscarAfiliado:active { transform: translateY(4px) !important; box-shadow: 0 0 0 #15803d !important; }

        
        .carrusel-wrapper { display: flex; align-items: center; justify-content: space-between; gap: 1vw; width: 98%; max-width: 1200px; margin: 1vh auto; height: 38vh !important; position: relative; animation: deslizarArriba 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards; }
        .btn-scroll { background: #0284c7; color: white; border: none; border-radius: 50%; width: 8vh; height: 8vh; font-size: 4vh; font-weight: 900; cursor: pointer; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.3); z-index: 100; transition: transform 0.2s; flex-shrink: 0; }
        .btn-scroll:active { transform: scale(0.9); }
        .contenedor-servicios { display: grid; grid-template-rows: repeat(3, 1fr); grid-auto-flow: column; grid-auto-columns: minmax(220px, 240px); gap: 1.5vh 1.5vw; width: 100%; height: 100%; overflow-x: auto; overflow-y: hidden; scroll-behavior: smooth; padding: 1vh 1vw; scroll-snap-type: x mandatory; }
        .contenedor-servicios::-webkit-scrollbar { display: none; }

        @keyframes deslizarArriba { 0% { transform: translateY(80px); opacity: 0; } 100% { transform: translateY(0); opacity: 1; } }
        .btn-servicio { background: rgba(255, 255, 255, 0.6); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); color: #0284c7; border: 2px solid rgba(2, 132, 199, 0.4); border-radius: 12px; padding: 1vh 1vw; font-size: 2vh; font-weight: 900; cursor: pointer; box-shadow: 0 8px 20px rgba(2, 132, 199, 0.15); text-transform: uppercase; display: flex; align-items: center; justify-content: center; text-align: center; transition: all 0.2s ease; scroll-snap-align: start; position: relative; overflow: hidden; animation: pulsoAura 2.5s infinite; width: 100%; height: 100%; line-height: 1.2; }
        @keyframes pulsoAura { 0% { box-shadow: 0 0 0 0 rgba(2, 132, 199, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(2, 132, 199, 0); } 100% { box-shadow: 0 0 0 0 rgba(2, 132, 199, 0); } }
        .btn-servicio:active { transform: translateY(4px); box-shadow: 0 2px 10px rgba(2, 132, 199, 0.1); }

        .contenedor-abc { display: grid; grid-template-columns: repeat(9, 1fr); gap: 0.8vh 0.4vw; max-width: 1000px; width: 95%; margin: 1.5vh auto; height: 18vh !important; }
        .btn-abc { background: linear-gradient(145deg, #ffffff, #f1f5f9); color: #334155; border: 2px solid #cbd5e1; border-radius: 10px; font-size: 2.4vh; font-weight: 900; cursor: pointer; display: flex; align-items: center; justify-content: center; height: 5.2vh; box-shadow: 0 4px 0 #cbd5e1; transition: transform 0.1s, box-shadow 0.1s; }
        .btn-abc.activo { background: linear-gradient(145deg, #0284c7, #0369a1); color: white; border-color: #0284c7; box-shadow: 0 4px 0 #013854; }
        .btn-abc:active { transform: translateY(4px); box-shadow: 0 0 0 #cbd5e1; }

        .modal-ayuda { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0,0,0,0.85); z-index: 100001; display: flex; justify-content: center; align-items: center; flex-direction: column; opacity: 0; pointer-events: none; transition: opacity 0.3s; }
        .modal-ayuda.activo { opacity: 1; pointer-events: auto; }
        .caja-ayuda { background: white; padding: 5vh 5vw; border-radius: 20px; text-align: center; border: 6px solid #dc2626; max-width: 80%; box-shadow: 0 20px 50px rgba(0,0,0,0.5); }
        .totem-tutorial { background: rgba(224, 242, 254, 0.6); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); border: 2px solid rgba(56, 189, 248, 0.5); border-radius: 15px; padding: 2vh; margin: 2vh auto 1vh auto; width: 85%; max-width: 1000px; text-align: center; font-size: 3vh; font-weight: 900; color: #0369a1; box-shadow: 0 8px 32px rgba(3, 105, 161, 0.1); transition: all 0.3s ease; }
        .salvapantallas { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: #000000; z-index: 100005; display: flex; flex-direction: column; justify-content: center; align-items: center; transition: opacity 0.5s; }
        .salvapantallas h2 { color: white; font-size: 5vh; font-weight: 900; animation: flotar 3s infinite ease-in-out; margin-top: 5vh; text-align: center; }

        @keyframes latido { 0% { transform: scale(1); opacity: 1; } 50% { transform: scale(1.5); opacity: 0.5; } 100% { transform: scale(1); opacity: 1; } }
        @keyframes flotar { 0% { transform: translateY(0); } 50% { transform: translateY(-20px); } 100% { transform: translateY(0); } }
    `;
    document.head.appendChild(estilo);

    const cabecera = document.createElement('div');
    cabecera.className = 'actis-header';
    cabecera.innerHTML = '<img id="logo-modo-dios" src="https://federicogonzalez.net/actis/img/osfa_blanco.png" style="pointer-events: auto;"><h1>POLICLÍNICA GENERAL ACTIS</h1><p>SELECCIONE EL TRÁMITE A REALIZAR</p>';
    document.body.insertBefore(cabecera, document.body.firstChild);

    // MODO DIOS: 5 TOQUES RÁPIDOS EN EL LOGO PARA DESBLOQUEAR
    let contadorDios = 0;
    let timerDios = null;
    document.getElementById('logo-modo-dios').addEventListener('click', function(e) {
        e.stopPropagation();
        contadorDios++;
        clearTimeout(timerDios);
        if(contadorDios >= 5) {
            contadorDios = 0;
            if(typeof unsafeWindow.emitirBeep === 'function') unsafeWindow.emitirBeep('exito');
            let bloqueo = document.getElementById('bloqueo-horario-dinamico');
            if(bloqueo) {
                bloqueo.remove();
                unsafeWindow.tRestaurarDOM();
            } else {
                alert('MODO DIOS ACTIVO 🛠️: El tótem ya está desbloqueado.');
            }
        }
        timerDios = setTimeout(() => { contadorDios = 0; }, 2000);
    });

    // DETECTOR DE CAÍDA DE INTERNET (ANTI-DINOSAURIO)
    window.addEventListener('offline', function() {
        let p = document.getElementById('pantalla-offline');
        if(p) p.classList.remove('pantalla-oculta');
    });
    window.addEventListener('online', function() {
        let p = document.getElementById('pantalla-offline');
        if(p) p.classList.add('pantalla-oculta');
    });

    // LECTURA POR VOZ (ACCESIBILIDAD)
    let vozActiva = false;
    unsafeWindow.tActivarVoz = function() {
        vozActiva = !vozActiva;
        let btn = document.getElementById('btn-accesibilidad');
        if(vozActiva) {
            btn.style.background = '#38bdf8';
            btn.style.color = '#0f172a';
            btn.innerHTML = '🔊 VOZ ACTIVADA';
            tHablar('Modo de accesibilidad activado. Por favor, seleccione su trámite.');
        } else {
            btn.style.background = '#0f172a';
            btn.style.color = '#38bdf8';
            btn.innerHTML = '🗣️ LECTURA POR VOZ';
            window.speechSynthesis.cancel();
        }
    };
    // 1. PRECARGA OBLIGATORIA: Mata a la voz española en el primer clic
    window.speechSynthesis.onvoiceschanged = () => window.speechSynthesis.getVoices();
    window.speechSynthesis.getVoices();

    function tHablar(texto) {
        if(!vozActiva) return;
        window.speechSynthesis.cancel();
        
        // 2. DICCIONARIO FONÉTICO: Evita que deletree palabras en mayúsculas
        let textoLimpio = texto
            .replace(/TOKEN/gi, 'toquen')
            .replace(/OSFA/gi, 'ósfa')
            .replace(/ACTIS/gi, 'áctis'); 

        let msg = new SpeechSynthesisUtterance(textoLimpio);
        
        // Configuramos velocidad y tono normal
        msg.rate = 1;
        msg.pitch = 1; 
        msg.lang = 'es-AR';

        let voces = window.speechSynthesis.getVoices();
        
        // Buscamos la voz de Argentina, y si falla, una latina que no sea de España
        let vozArgentina = voces.find(v => v.lang === 'es-AR' || v.lang === 'es_AR' || v.name.includes('Argentina'));
        let vozLatina = voces.find(v => v.lang.startsWith('es-') && !v.lang.includes('ES') && !v.name.includes('Spain'));

        if (vozArgentina) {
            msg.voice = vozArgentina;
        } else if (vozLatina) {
            msg.voice = vozLatina;
        }

        window.speechSynthesis.speak(msg);
    }

    // CONTROL DEL SALVAPANTALLAS (RELOJ Y CARRUSEL)
    let textosCarrusel = [
        "🏥 Bienvenido a la Policlínica General Actis",
        "🪪 Por favor, tenga su DNI en mano para la atención",
        "⏱️ Horario de Laboratorio: 07:00 a 19:00 hs",
        "🤫 Por favor, respete el silencio en la sala"
        // ,"⭐⭐⭐ ¡VAMOS ARGENTINA POR LA CUARTA! ⭐⭐⭐"
    ];
    let indiceCarrusel = 0;
    setInterval(() => {
        let d = new Date();
        let reloj = document.getElementById('reloj-gigante');
        let fecha = document.getElementById('fecha-gigante');
        if(reloj) reloj.innerText = d.toLocaleTimeString('es-AR', { hour12: false, hour: '2-digit', minute: '2-digit' });
        if(fecha) {
            let opciones = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            fecha.innerText = d.toLocaleDateString('es-AR', opciones);
        }
    }, 1000);

    setInterval(() => {
        let cTextos = document.getElementById('carrusel-textos');
        let salva = document.getElementById('salvapantallas-totem');
        if(cTextos && salva && !salva.classList.contains('pantalla-oculta')) {
            cTextos.style.opacity = '0';
            setTimeout(() => {
                indiceCarrusel = (indiceCarrusel + 1) % textosCarrusel.length;
                cTextos.innerText = textosCarrusel[indiceCarrusel];
                cTextos.style.opacity = '1';
            }, 500);
        }
    }, 5000);

    // COMPONENTES MULTI-UI INTEGRADOS
    const UI_Extras = document.createElement('div');
    UI_Extras.innerHTML = `
        <!--
        <div id="decoracion-mundial-izq" style="position: fixed; top: 0; left: 0; width: 18vw; max-width: 250px; height: auto; z-index: 999999; pointer-events: none; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3));">
            <svg viewBox="0 0 250 200" xmlns="http://www.w3.org/2000/svg" style="width: 100%; height: 100%; overflow: visible !important;">
                <path d="M-10,10 Q125,40 260,0" fill="none" stroke="#94a3b8" stroke-width="2" opacity="0.5"/>
                <path d="M10,12 L40,15 L25,60 Z" fill="#74acdf" />
                <path d="M50,16 L80,18 L65,65 Z" fill="#ffffff" />
                <circle cx="65" cy="35" r="6" fill="#f6b40e"/> <path d="M90,19 L120,20 L105,65 Z" fill="#74acdf" />
                <path d="M130,20 L160,18 L145,65 Z" fill="#ffffff" />
                <circle cx="145" cy="35" r="6" fill="#f6b40e"/> <path d="M170,17 L200,14 L185,60 Z" fill="#74acdf" />
                <path d="M210,12 L240,8 L225,50 Z" fill="#ffffff" />
                
                <g class="pelota-anim" transform="translate(20, 80) scale(0.65)">
                    <circle cx="50" cy="50" r="40" fill="#ffffff" stroke="#1e293b" stroke-width="3"/>
                    <path d="M50,30 L65,45 L58,65 L42,65 L35,45 Z" fill="#1e293b" />
                    <path d="M50,30 L50,10 M65,45 L85,40 M58,65 L70,85 M42,65 L30,85 M35,45 L15,40" stroke="#1e293b" stroke-width="3"/>
                </g>

                <g class="corneta-anim" transform="translate(100, 95) rotate(20) scale(0.85)">
                    <path d="M10,40 L80,20 L80,60 Z" fill="#74acdf" />
                    <path d="M80,10 L95,10 L95,70 L80,70 Z" fill="#ffffff" />
                    <path d="M95,10 L105,5 L105,75 L95,70 Z" fill="#74acdf" />
                    <path d="M0,35 L10,35 L10,45 L0,45 Z" fill="#f6b40e" />
                </g>
                
                <path d="M160,130 L165,145 L180,145 L168,155 L172,170 L160,160 L148,170 L152,155 L140,145 L155,145 Z" fill="#f6b40e" transform="scale(0.5) translate(160, 100)" />
                <circle cx="180" cy="90" r="4" fill="#74acdf" />
                <circle cx="110" cy="160" r="5" fill="#ffffff" stroke="#74acdf" stroke-width="2"/>
                <rect x="50" y="160" width="8" height="8" fill="#f6b40e" transform="rotate(45 54 164)"/>
            </svg>
        </div>

        <div id="decoracion-mundial-der" style="position: fixed; top: 0; right: 0; width: 18vw; max-width: 250px; height: auto; z-index: 999999; pointer-events: none; filter: drop-shadow(0 4px 6px rgba(0,0,0,0.3)); transform: scaleX(-1);">
            <svg viewBox="0 0 250 200" xmlns="http://www.w3.org/2000/svg" style="width: 100%; height: 100%; overflow: visible !important;">
                <path d="M-10,10 Q125,40 260,0" fill="none" stroke="#94a3b8" stroke-width="2" opacity="0.5"/>
                <path d="M10,12 L40,15 L25,60 Z" fill="#74acdf" />
                <path d="M50,16 L80,18 L65,65 Z" fill="#ffffff" />
                <circle cx="65" cy="35" r="6" fill="#f6b40e"/> 
                <path d="M90,19 L120,20 L105,65 Z" fill="#74acdf" />
                <path d="M130,20 L160,18 L145,65 Z" fill="#ffffff" />
                <circle cx="145" cy="35" r="6" fill="#f6b40e"/> 
                <path d="M170,17 L200,14 L185,60 Z" fill="#74acdf" />
                <path d="M210,12 L240,8 L225,50 Z" fill="#ffffff" />
                
                <g class="pelota-anim" transform="translate(20, 80) scale(0.65)">
                    <circle cx="50" cy="50" r="40" fill="#ffffff" stroke="#1e293b" stroke-width="3"/>
                    <path d="M50,30 L65,45 L58,65 L42,65 L35,45 Z" fill="#1e293b" />
                    <path d="M50,30 L50,10 M65,45 L85,40 M58,65 L70,85 M42,65 L30,85 M35,45 L15,40" stroke="#1e293b" stroke-width="3"/>
                </g>

                <g class="corneta-anim" transform="translate(100, 95) rotate(20) scale(0.85)">
                    <path d="M10,40 L80,20 L80,60 Z" fill="#74acdf" />
                    <path d="M80,10 L95,10 L95,70 L80,70 Z" fill="#ffffff" />
                    <path d="M95,10 L105,5 L105,75 L95,70 Z" fill="#74acdf" />
                    <path d="M0,35 L10,35 L10,45 L0,45 Z" fill="#f6b40e" />
                </g>
                
                <path d="M160,130 L165,145 L180,145 L168,155 L172,170 L160,160 L148,170 L152,155 L140,145 L155,145 Z" fill="#f6b40e" transform="scale(0.5) translate(160, 100)" />
                <circle cx="180" cy="90" r="4" fill="#74acdf" />
                <circle cx="110" cy="160" r="5" fill="#ffffff" stroke="#74acdf" stroke-width="2"/>
                <rect x="50" y="160" width="8" height="8" fill="#f6b40e" transform="rotate(45 54 164)"/>
            </svg>
        </div>

        <div id="png-argentina-izq" style="position: fixed; top: 35%; left: 2vw; width: 18vw; max-width: 250px; z-index: 999998; pointer-events: none; filter: drop-shadow(0 8px 15px rgba(0,0,0,0.3)); animation: latidoMundial 2s infinite;">
            <img src="https://federicogonzalez.net/actis/img/messi.png" style="width: 100%; height: auto;">
        </div>

        <div id="png-argentina-der" style="position: fixed; top: 35%; right: 2vw; width: 18vw; max-width: 250px; z-index: 999998; pointer-events: none; filter: drop-shadow(0 8px 15px rgba(0,0,0,0.3)); animation: latidoMundial 2s infinite;">
            <img src="https://federicogonzalez.net/actis/img/arg.png" style="width: 100%; height: auto;">
        </div>
        -->

        <style>
            @keyframes animPelota { 0% { transform: translate(20px, 80px) scale(0.65) rotate(0deg); } 50% { transform: translate(20px, 65px) scale(0.65) rotate(20deg); } 100% { transform: translate(20px, 80px) scale(0.65) rotate(0deg); } }
            @keyframes animCorneta { 0% { transform: translate(100px, 95px) rotate(20deg) scale(0.85); } 50% { transform: translate(105px, 90px) rotate(40deg) scale(0.95); } 100% { transform: translate(100px, 95px) rotate(20deg) scale(0.85); } }
            .pelota-anim { transform-origin: center; animation: animPelota 3s ease-in-out infinite; }
            .corneta-anim { transform-origin: center; animation: animCorneta 1.5s ease-in-out infinite; }

            @keyframes latidoMundial { 0% { transform: scale(1) rotate(-2deg); } 50% { transform: scale(1.08) rotate(2deg); } 100% { transform: scale(1) rotate(-2deg); } }
            @keyframes caidaPapelitos { 0% { transform: translateY(-10vh) rotate(0deg); opacity: 1; } 100% { transform: translateY(110vh) rotate(360deg); opacity: 0; } }
            .papelito { position: fixed; width: 1.2vw; height: 1.2vw; z-index: 999997; pointer-events: none; opacity: 0; }
            .p1 { background: #74acdf; left: 10vw; animation: caidaPapelitos 4s linear infinite; }
            .p2 { background: #ffffff; left: 25vw; animation: caidaPapelitos 5s linear infinite 1s; border: 1px solid #cbd5e1; }
            .p3 { background: #74acdf; left: 50vw; animation: caidaPapelitos 3.5s linear infinite 2s; border-radius: 50%; }
            .p4 { background: #ffffff; left: 75vw; animation: caidaPapelitos 4.5s linear infinite 0.5s; border: 1px solid #cbd5e1; }
            .p5 { background: #74acdf; left: 90vw; animation: caidaPapelitos 4s linear infinite 1.5s; }
        </style>
        <!-- <div class="papelito p1"></div><div class="papelito p2"></div><div class="papelito p3"></div><div class="papelito p4"></div><div class="papelito p5"></div> -->
        <div id="png-malvinas" style="position: fixed; bottom: 20vh; right: 2vw; width: 18vw; max-width: 220px; z-index: 999998; pointer-events: none; filter: drop-shadow(0 5px 15px rgba(0,0,0,0.2));">
            <img src="https://federicogonzalez.net/actis/img/malvinas.png" style="width: 100%; height: auto;">
        </div>

        <div class="totem-tutorial" id="tutorial-kiosco" style="display: flex; justify-content: space-between; align-items: center;">
            <button type="button" onclick="tutoPrev()" style="background: none; border: none; font-size: 4vh; cursor: pointer; color: #0284c7; font-weight: 900;">◀</button>
            <span id="tutorial-texto" style="flex-grow: 1;">PASO 1: SELECCIONE SU TRÁMITE ABAJO 👇</span>
            <button type="button" onclick="tutoNext()" style="background: none; border: none; font-size: 4vh; cursor: pointer; color: #0284c7; font-weight: 900;">▶</button>
        </div>

        <div class="totem-reloj" id="reloj-vivo">00:00:00</div>
        <div class="totem-estado">
            <div class="estado-item"><div class="dot-verde"></div> SISTEMA OSFA: EN LÍNEA</div>
            <div class="estado-item"><div class="dot-verde" style="animation-delay: 0.5s"></div> IMPRESORA TÉRMICA: LISTA</div>
        </div>
        <!-- Hamburger Menu Button -->
        <button type="button" class="btn-flotante btn-navegacion btn-home pantalla-oculta" id="totem-btn-home">🏠 INICIO</button>

        <div class="totem-marquesina"><marquee scrollamount="12"> <span style="display: inline-flex; flex-direction: column; width: 24px; height: 16px; vertical-align: middle; border: 1px solid #ddd; line-height: 0;">
  <span style="background: #74ACDF; flex: 1;"></span>  <span style="background: #FFFFFF; flex: 1;"></span>  <span style="background: #74ACDF; flex: 1;"></span></span> BIENVENIDOS A LA POLICLÍNICA GENERAL ACTIS <span style="display: inline-flex; flex-direction: column; width: 24px; height: 16px; vertical-align: middle; border: 1px solid #ddd; line-height: 0;">
  <span style="background: #74ACDF; flex: 1;"></span>  <span style="background: #FFFFFF; flex: 1;"></span>  <span style="background: #74ACDF; flex: 1;"></span></span> INGRESE SU NÚMERO DE DNI Y RETIRE EL TICKET AL FINALIZAR <span style="display: inline-flex; flex-direction: column; width: 24px; height: 16px; vertical-align: middle; border: 1px solid #ddd; line-height: 0;">
  <span style="background: #74ACDF; flex: 1;"></span>  <span style="background: #FFFFFF; flex: 1;"></span>  <span style="background: #74ACDF; flex: 1;"></span></span> RECUERDE QUE EL HORARIO DE LABORATORIO ES DE 07:00 A 19:00 HS <span style="display: inline-flex; flex-direction: column; width: 24px; height: 16px; vertical-align: middle; border: 1px solid #ddd; line-height: 0;">
  <span style="background: #74ACDF; flex: 1;"></span>  <span style="background: #FFFFFF; flex: 1;"></span>  <span style="background: #74ACDF; flex: 1;"></span></span></marquee></div>
        <div class="modal-ayuda" id="modal-ayuda">
            <div class="caja-ayuda" style="max-width: 900px; padding: 4vh 4vw; border-radius: 20px;">
                <h1 style="font-size: 5vh; color: #dc2626; margin-bottom: 2vh; border-bottom: 2px solid #fecaca; padding-bottom: 1vh;">¿NECESITA ASISTENCIA?</h1>

                <div style="background: #f8fafc; padding: 2.5vh; border-radius: 15px; margin-bottom: 3vh; border-left: 6px solid #3b82f6; text-align: left;">
                    <h2 style="font-size: 3vh; color: #1e293b; margin: 0 0 1vh 0;">👤 Para Afiliados:</h2>
                    <p style="font-size: 2.5vh; color: #475569; font-weight: 700; margin: 0;">Por favor, acérquese al personal de seguridad en la entrada para que lo guíe y asista con su trámite.</p>
                </div>

                <div style="background: #fffbeb; padding: 2.5vh; border-radius: 15px; margin-bottom: 3vh; border-left: 6px solid #f59e0b; text-align: left;">
                    <h2 style="font-size: 3vh; color: #1e293b; margin: 0 0 1vh 0;">🛡️ Para Personal Interno (Seguridad / Validación):</h2>
                    <p style="font-size: 2.2vh; color: #475569; font-weight: 700; margin: 0 0 1vh 0;">Si el Tótem presenta fallas técnicas, contacte al encargado de informática:</p>
                    <p style="font-size: 2.6vh; color: #b45309; font-weight: 900; margin: 0;">WhatsApp: 11-6611-6861<br>SG MEC INFO FEDERICO GONZÁLEZ</p>
                </div>

                <div id="botones-ayuda-principal" style="display: flex; gap: 1vw; justify-content: center; margin-top: 4vh;">
                    <button type="button" onclick="tOcultarAyuda()" style="padding: 2vh 2vw; font-size: 2vh; background: #f1f5f9; color: #64748b; border: 2px solid #cbd5e1; border-radius: 12px; font-weight: 900; cursor: pointer; flex: 1;">❌ CERRAR</button>
                    <button type="button" onclick="window.location.replace('https://validador.iosfa.gob.ar/ValidadorDni')" style="padding: 2vh 2vw; font-size: 2vh; background: #f59e0b; color: white; border: none; border-radius: 12px; font-weight: 900; cursor: pointer; flex: 1.2; box-shadow: 0 4px 15px rgba(0,0,0,0.2); transition: all 0.3s;">🔄 REINICIAR NAVEGADOR</button>
                    <button type="button" id="btn-pre-enviar" onclick="tSolicitarPin()" style="padding: 2vh 2vw; font-size: 2vh; background: #0f172a; color: white; border: none; border-radius: 12px; font-weight: 900; flex: 1.5; box-shadow: 0 4px 15px rgba(0,0,0,0.2); transition: all 0.3s; cursor: pointer;">📨 ENVIAR ALERTA</button>
                </div>

                <div id="panel-pin-soporte" style="display: none; background: #f8fafc; border: 4px solid #94a3b8; padding: 3vh; border-radius: 15px; margin-top: 3vh; animation: entrarPop 0.3s ease;">
                    <h3 style="color: #334155; font-size: 3vh; margin: 0 0 1vh 0; font-weight: 900;">🔒 INGRESE PIN DE SEGURIDAD</h3>
                    <input type="password" id="input-pin-alerta" readonly style="width: 60%; padding: 1.5vh; font-size: 4vh; text-align: center; border: 2px solid #cbd5e1; border-radius: 10px; margin-bottom: 2vh; letter-spacing: 8px; font-weight: bold; color: #0f172a; background: white; outline: none; transition: background 0.3s;">
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1vh; width: 60%; margin: 0 auto 2vh auto;">
                        <button type="button" onclick="tAddPin(1)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold; cursor: pointer; box-shadow: 0 3px 0 #cbd5e1;">1</button>
                        <button type="button" onclick="tAddPin(2)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold; cursor: pointer; box-shadow: 0 3px 0 #cbd5e1;">2</button>
                        <button type="button" onclick="tAddPin(3)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold; cursor: pointer; box-shadow: 0 3px 0 #cbd5e1;">3</button>
                        <button type="button" onclick="tAddPin(4)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold; cursor: pointer; box-shadow: 0 3px 0 #cbd5e1;">4</button>
                        <button type="button" onclick="tAddPin(5)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold; cursor: pointer; box-shadow: 0 3px 0 #cbd5e1;">5</button>
                        <button type="button" onclick="tAddPin(6)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold; cursor: pointer; box-shadow: 0 3px 0 #cbd5e1;">6</button>
                        <button type="button" onclick="tAddPin(7)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold; cursor: pointer; box-shadow: 0 3px 0 #cbd5e1;">7</button>
                        <button type="button" onclick="tAddPin(8)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold; cursor: pointer; box-shadow: 0 3px 0 #cbd5e1;">8</button>
                        <button type="button" onclick="tAddPin(9)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold; cursor: pointer; box-shadow: 0 3px 0 #cbd5e1;">9</button>
                        <button type="button" onclick="tClearPin()" style="padding: 1.5vh; font-size: 2vh; border-radius: 8px; border: 2px solid #fca5a5; background: #fee2e2; color: #dc2626; font-weight: bold; cursor: pointer; box-shadow: 0 3px 0 #f87171;">BORRAR</button>
                        <button type="button" onclick="tAddPin(0)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold; cursor: pointer; box-shadow: 0 3px 0 #cbd5e1;">0</button>
                        <button type="button" onclick="tValidarPin()" style="padding: 1.5vh; font-size: 2vh; border-radius: 8px; border: none; background: #22c55e; color: white; font-weight: bold; cursor: pointer; box-shadow: 0 3px 0 #15803d;">ENTRAR</button>
                    </div>
                    <button type="button" onclick="tCancelarConfirmacion()" style="padding: 1.5vh 3vw; border-radius: 10px; background: #f1f5f9; color: #64748b; font-weight: 900; border: 2px solid #cbd5e1; cursor: pointer; font-size: 2vh;">❌ CANCELAR</button>
                </div>

                <div id="confirmacion-soporte" style="display: none; background: #fee2e2; border: 4px solid #dc2626; padding: 3vh; border-radius: 15px; margin-top: 3vh; animation: entrarPop 0.3s ease;">
                    <h3 style="color: #dc2626; font-size: 3.5vh; margin: 0 0 1.5vh 0; font-weight: 900;">⚠️ ¿ESTÁ SEGURO?</h3>
                    <p style="color: #475569; font-size: 2.2vh; font-weight: 800; margin: 0 0 2.5vh 0;">Esta tarea es EXCLUSIVAMENTE de seguridad y técnica. No es para hacer pruebas.</p>
                    <div style="display: flex; gap: 1vw;">
                        <button type="button" onclick="tCancelarConfirmacion()" style="padding: 2vh; flex: 1; border-radius: 10px; background: #f1f5f9; color: #64748b; font-weight: 900; border: 2px solid #cbd5e1; cursor: pointer; font-size: 2vh;">CANCELAR</button>
                        <button type="button" id="btn-enviar-soporte" onclick="tEnviarAlertaSoporte()" style="padding: 2vh; flex: 1; border-radius: 10px; background: #dc2626; color: white; font-weight: 900; border: none; cursor: pointer; font-size: 2vh; box-shadow: 0 4px 10px rgba(220, 38, 38, 0.3);">🚨 SÍ, ENVIAR ALERTA</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="modal-ayuda" id="modal-resultado-kiosco">
            <div class="caja-ayuda" id="caja-resultado-kiosco" style="border-color: #0284c7; max-width: 650px; width: 90%;">
                <div id="resultado-contenido"></div>
            </div>
        </div>

        <div class="modal-ayuda" id="panel-pin-turno" style="background: rgba(15, 23, 42, 0.95); z-index: 100000; flex-direction: column;">
            <div class="caja-ayuda" style="max-width: 900px; padding: 4vh 4vw; border-radius: 20px; border-color: #0ea5e9;">
                <h1 style="font-size: 5vh; color: #0ea5e9; margin-bottom: 2vh; border-bottom: 2px solid #bae6fd; padding-bottom: 1vh;">VALIDACIÓN DE TURNO</h1>
                <div style="background: #f8fafc; border: 4px solid #94a3b8; padding: 3vh; border-radius: 15px; margin-top: 3vh; text-align: center;">
                    <div id="lista-turnos-paciente" style="margin-bottom: 2vh; text-align: left; background: #e0f2fe; padding: 2vh; border-radius: 10px; border: 2px solid #7dd3fc;"></div>
                    <h3 style="color: #334155; font-size: 3vh; margin: 0 0 1vh 0; font-weight: 900;">📅 INGRESE SU NÚMERO DE TURNO</h3>
                    <input type="password" id="input-pin-turno" readonly style="width: 60%; padding: 1.5vh; font-size: 4vh; text-align: center; border: 2px solid #cbd5e1; border-radius: 10px; margin-bottom: 2vh; letter-spacing: 8px; font-weight: bold; color: #0f172a; background: white; outline: none; transition: background 0.3s;">
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1vh; width: 60%; margin: 0 auto 2vh auto;">
                        <button type="button" onclick="tAddPinTurno(1)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold;">1</button>
                        <button type="button" onclick="tAddPinTurno(2)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold;">2</button>
                        <button type="button" onclick="tAddPinTurno(3)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold;">3</button>
                        <button type="button" onclick="tAddPinTurno(4)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold;">4</button>
                        <button type="button" onclick="tAddPinTurno(5)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold;">5</button>
                        <button type="button" onclick="tAddPinTurno(6)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold;">6</button>
                        <button type="button" onclick="tAddPinTurno(7)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold;">7</button>
                        <button type="button" onclick="tAddPinTurno(8)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold;">8</button>
                        <button type="button" onclick="tAddPinTurno(9)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold;">9</button>
                        <button type="button" onclick="tClearPinTurno()" style="padding: 1.5vh; font-size: 2vh; border-radius: 8px; border: 2px solid #fca5a5; background: #fee2e2; color: #dc2626; font-weight: bold;">BORRAR</button>
                        <button type="button" onclick="tAddPinTurno(0)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold;">0</button>
                        <button type="button" onclick="tValidarPinTurno()" id="btn-validar-turno" style="padding: 1.5vh; font-size: 2vh; border-radius: 8px; border: none; background: #0ea5e9; color: white; font-weight: bold;">VALIDAR</button>
                    </div>
                    <button type="button" onclick="tCancelarConfirmacion()" style="padding: 1.5vh 3vw; border-radius: 10px; background: #f1f5f9; color: #64748b; font-weight: 900; border: 2px solid #cbd5e1; cursor: pointer; font-size: 2vh;">❌ CANCELAR</button>
                </div>
            </div>
        </div>

        <div id="totem-menu-flotante" style="position: fixed; bottom: 10vh; left: 2vw; z-index: 10000;">
            <button type="button" onclick="tToggleMenu()" style="background: #0f172a; border: 3px solid #38bdf8; border-radius: 50px; padding: 2vh 2vw; font-weight: 900; font-size: 2.5vh; color: #38bdf8; cursor: pointer; box-shadow: 0 5px 15px rgba(0,0,0,0.4); display: flex; align-items: center; justify-content: center;">
                <span style="margin-right: 1vw; font-size: 3vh;">⚙️</span> OPCIONES
            </button>
            <div id="totem-menu-opciones" style="display: none; position: absolute; bottom: 100%; left: 0; margin-bottom: 2vh; background: #0f172a; border: 3px solid #38bdf8; border-radius: 15px; padding: 2vh; width: 25vw; min-width: 300px; box-shadow: 0 10px 25px rgba(0,0,0,0.5); flex-direction: column; gap: 1.5vh;">
                <button type="button" onclick="tActivarVoz(); tToggleMenu();" id="btn-accesibilidad" style="background: #1e293b; border: 2px solid #475569; color: white; padding: 2vh; border-radius: 10px; font-weight: bold; font-size: 2vh; cursor: pointer; text-align: left; transition: all 0.2s;">
                    🗣️ LECTURA POR VOZ
                </button>
                <button type="button" onclick="tMostrarAyuda(); tToggleMenu();" style="background: #1e293b; border: 2px solid #475569; color: white; padding: 2vh; border-radius: 10px; font-weight: bold; font-size: 2vh; cursor: pointer; text-align: left; transition: all 0.2s;">
                    🆘 NECESITO AYUDA
                </button>
                <button type="button" onclick="tMostrarCambioRollo(); tToggleMenu();" style="background: #1e293b; border: 2px solid #475569; color: white; padding: 2vh; border-radius: 10px; font-weight: bold; font-size: 2vh; cursor: pointer; text-align: left; transition: all 0.2s;">
                    🔄 CAMBIO DE ROLLO
                </button>
            </div>
        </div>

        <div class="modal-ayuda" id="modal-cambio-rollo">
            <div class="caja-ayuda" style="max-width: 900px; padding: 4vh 4vw; border-radius: 20px; border-color: #f59e0b;">
                <h1 style="font-size: 5vh; color: #f59e0b; margin-bottom: 2vh; border-bottom: 2px solid #fde68a; padding-bottom: 1vh;">REEMPLAZO DE ROLLO</h1>
                <div id="panel-pin-rollo" style="background: #f8fafc; border: 4px solid #94a3b8; padding: 3vh; border-radius: 15px; margin-top: 3vh; text-align: center;">
                    <h3 style="color: #334155; font-size: 3vh; margin: 0 0 1vh 0; font-weight: 900;">🔒 INGRESE PIN DE AUTORIZACIÓN</h3>
                    <input type="password" id="input-pin-rollo" readonly style="width: 60%; padding: 1.5vh; font-size: 4vh; text-align: center; border: 2px solid #cbd5e1; border-radius: 10px; margin-bottom: 2vh; letter-spacing: 8px; font-weight: bold; color: #0f172a; background: white; outline: none; transition: background 0.3s;">
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1vh; width: 60%; margin: 0 auto 2vh auto;">
                        <button type="button" onclick="tAddPinRollo(1)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold;">1</button>
                        <button type="button" onclick="tAddPinRollo(2)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold;">2</button>
                        <button type="button" onclick="tAddPinRollo(3)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold;">3</button>
                        <button type="button" onclick="tAddPinRollo(4)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold;">4</button>
                        <button type="button" onclick="tAddPinRollo(5)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold;">5</button>
                        <button type="button" onclick="tAddPinRollo(6)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold;">6</button>
                        <button type="button" onclick="tAddPinRollo(7)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold;">7</button>
                        <button type="button" onclick="tAddPinRollo(8)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold;">8</button>
                        <button type="button" onclick="tAddPinRollo(9)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold;">9</button>
                        <button type="button" onclick="tClearPinRollo()" style="padding: 1.5vh; font-size: 2vh; border-radius: 8px; border: 2px solid #fca5a5; background: #fee2e2; color: #dc2626; font-weight: bold;">BORRAR</button>
                        <button type="button" onclick="tAddPinRollo(0)" style="padding: 1.5vh; font-size: 2.5vh; border-radius: 8px; border: 2px solid #e2e8f0; background: white; font-weight: bold;">0</button>
                        <button type="button" onclick="tValidarPinRollo()" id="btn-validar-rollo" style="padding: 1.5vh; font-size: 2vh; border-radius: 8px; border: none; background: #f59e0b; color: white; font-weight: bold;">VALIDAR</button>
                    </div>
                    <button type="button" onclick="tOcultarCambioRollo()" style="padding: 1.5vh 3vw; border-radius: 10px; background: #f1f5f9; color: #64748b; font-weight: 900; border: 2px solid #cbd5e1; cursor: pointer; font-size: 2vh;">❌ CANCELAR</button>
                </div>
            </div>
        </div>

        <div id="pantalla-offline" class="pantalla-oculta" style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(220, 38, 38, 0.95); z-index: 9999999; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; color: white; backdrop-filter: blur(10px);">
            <span style="font-size: 15vh; margin-bottom: 2vh;">🦖</span>
            <h1 style="font-size: 6vh; font-weight: 900; margin: 0 0 2vh 0; text-shadow: 2px 2px 5px rgba(0,0,0,0.5);">SISTEMA FUERA DE LÍNEA</h1>
            <p style="font-size: 4vh; font-weight: 800; max-width: 80%;">Detectamos un corte en la conexión a Internet.<br>Por favor, acérquese a la ventanilla para ser atendido.</p>
        </div>

        <style>
            .fondo-ondas { background: radial-gradient(circle at center, #0284c7 0%, #0f172a 100%); overflow: hidden; position: relative; }
            .onda { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); border-radius: 50%; border: 2px solid rgba(56, 189, 248, 0.3); animation: expandirOnda 6s linear infinite; }
            .onda:nth-child(2) { animation-delay: 2s; }
            .onda:nth-child(3) { animation-delay: 4s; }
            @keyframes expandirOnda { 0% { width: 0; height: 0; opacity: 1; } 100% { width: 150vw; height: 150vw; opacity: 0; } }
        </style>
        
        <div class="salvapantallas pantalla-oculta fondo-ondas" id="salvapantallas-totem">
            <div class="onda"></div><div class="onda"></div><div class="onda"></div>
            <img src="https://federicogonzalez.net/actis/img/osfa_blanco.png" style="height: 18vh; filter: drop-shadow(0 0 30px rgba(56,189,248,0.8)); animation: flotar 4s infinite ease-in-out; z-index: 2;">
            
            <div id="reloj-gigante" style="font-size: 14vh; font-weight: 900; color: white; margin-top: 1vh; text-shadow: 0 0 20px #38bdf8; z-index: 2; font-family: monospace; letter-spacing: 2px;">00:00</div>
            <div id="fecha-gigante" style="font-size: 4vh; font-weight: 700; color: #bae6fd; margin-bottom: 4vh; z-index: 2; text-transform: uppercase;"></div>
            
            <h2 style="z-index: 2; margin: 0; font-size: 5vh; color: #f6b40e; text-shadow: 2px 2px 4px rgba(0,0,0,0.8);">TÓTEM DISPONIBLE</h2>
            <span style="font-size: 3vh; color: #ffffff; z-index: 2; margin-top: 1vh; animation: latido 1.5s infinite;">👆 Toque la pantalla para comenzar 👆</span>
            
            <div id="carrusel-textos" style="position: absolute; bottom: 8vh; width: 85%; max-width: 1000px; text-align: center; font-size: 3.5vh; font-weight: 800; color: #e0f2fe; background: rgba(15,23,42,0.6); padding: 2vh; border-radius: 20px; border: 2px solid #38bdf8; z-index: 2; transition: opacity 0.5s;">
                🏥 Bienvenido a la Policlínica General Actis
            </div>
        </div>
    `;
    document.body.insertBefore(UI_Extras, document.body.childNodes[1]);

    unsafeWindow.tRestaurarDOM = function() {
        if (unsafeWindow.restaurandoSistema) return;
        unsafeWindow.restaurandoSistema = true;

        unsafeWindow.ultimaAccionKiosco = 'Reiniciando vista...';
        
        let flashCapa = document.createElement('div');
        flashCapa.style.cssText = 'position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: #0f172a; z-index: 9999999; display: flex; justify-content: center; align-items: center;';
        flashCapa.innerHTML = '<img src="https://federicogonzalez.net/actis/img/osfa_blanco.png" style="height: 15vh;">';
        document.body.appendChild(flashCapa);
        
        // window.location.replace evita que el historial crezca infinitamente y sea pesado
        setTimeout(function() {
            window.location.replace('https://validador.iosfa.gob.ar/ValidadorDni');
        }, 200);
    };

    // ENLACE DE NAVEGACION DE BOTONES SUPERIORES
    document.getElementById('totem-btn-home').addEventListener('click', () => unsafeWindow.tRestaurarDOM());

    // FUNCIONES GLOBALES PARA MANEJAR EL MODAL DINÁMICO
    unsafeWindow.tMostrarResultado = function(htmlContenido, esError = false) {
        const modal = document.getElementById('modal-resultado-kiosco');
        const caja = document.getElementById('caja-resultado-kiosco');
        const contenido = document.getElementById('resultado-contenido');
        if (modal && caja && contenido) {
            contenido.innerHTML = htmlContenido;
            caja.style.borderColor = esError ? '#dc2626' : '#74acdf';
            modal.classList.add('activo');
        }
    };
    unsafeWindow.tOcultarResultado = function() {
        const modal = document.getElementById('modal-resultado-kiosco');
        if (modal) modal.classList.remove('activo');
    };

    // AUDIO FEEDBACK Y SALUDO MP3 (PRECARGADO PARA CERO RETARDO)
    let audioSaludo = new Audio();
    function configurarSaludo() {
        let hora = new Date().getHours();
        let archivo = 'bueasnoches.mp3';
        if (hora < 12) archivo = 'buendia.mp3';
        else if (hora < 20) archivo = 'buenastardes.mp3';
        // Ruta base principal
        audioSaludo.src = 'https://federicogonzalez.net/actis/' + archivo;
        audioSaludo.load();
    }
    configurarSaludo();
    setInterval(configurarSaludo, 600000); // Actualiza si cambia la hora del día

    const AudioContext = window.AudioContext || window.webkitAudioContext;
    const audioCtx = new AudioContext();
    unsafeWindow.emitirBeep = function(tipo) {
        if(audioCtx.state === 'suspended') audioCtx.resume();
        const osc = audioCtx.createOscillator();

        const gainNode = audioCtx.createGain();
        osc.connect(gainNode); gainNode.connect(audioCtx.destination);
        if (tipo === 'click') {
            osc.type = 'sine'; osc.frequency.setValueAtTime(850, audioCtx.currentTime);
            gainNode.gain.setValueAtTime(0.05, audioCtx.currentTime);
            osc.start(); osc.stop(audioCtx.currentTime + 0.05);
        } else if (tipo === 'exito') {
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(500, audioCtx.currentTime);
            osc.frequency.exponentialRampToValueAtTime(1000, audioCtx.currentTime + 0.4);
            gainNode.gain.setValueAtTime(0.1, audioCtx.currentTime);
            osc.start(); osc.stop(audioCtx.currentTime + 0.6);
        }
    };

    // ESCUCHADOR GLOBAL DE CLICS: Hace sonar CUALQUIER botón del tótem y lee por voz si está activado
    document.addEventListener('click', function(e) {
        let btnClickeado = e.target.closest('button, [id*="btnBuscarAfiliado"], .btn-modo, .btn-servicio, .btn-abc');
        if (btnClickeado && typeof unsafeWindow.emitirBeep === 'function') {
            if (!btnClickeado.classList.contains('kbtn')) {
                unsafeWindow.emitirBeep('click');
            }
            if (typeof tHablar === 'function') {
                let textoLeer = btnClickeado.innerText || btnClickeado.value;
                if(textoLeer) {
                    textoLeer = textoLeer.replace(/[^a-zA-Z0-9áéíóúÁÉÍÓÚñÑ ]/g, ''); 
                    if(textoLeer.trim() !== '') tHablar(textoLeer);
                }
            }
        }
    }, true);

    document.addEventListener('touchstart', function(e) {
        if (e.touches.length > 1) {
            e.preventDefault();
        }
    }, { passive: false });

    // RELOJ EN 24 HORAS FIJO ARGENTINA
    setInterval(() => {
        let d = new Date();
        let txtReloj = document.getElementById('reloj-vivo');
        if(txtReloj) {
            txtReloj.innerText = d.toLocaleTimeString('es-AR', { hour12: false, hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
    }, 1000);

    // TUTORIAL MANUAL CON FLECHAS
    let pasosTuto = ['PASO 1: SELECCIONE SU TRÁMITE ABAJO 👇', 'PASO 2: BUSQUE SU SERVICIO MÉDICO 🏥', 'PASO 3: INGRESE SU NÚMERO DE DNI 🪪', 'PASO 4: RETIRE SU TICKET IMPRESO 🎫'];
    let pasoActual = 0;
    unsafeWindow.tutoNext = function() {
        pasoActual = (pasoActual + 1) % pasosTuto.length;
        let tutoTexto = document.getElementById('tutorial-texto');
        if(tutoTexto) tutoTexto.innerHTML = pasosTuto[pasoActual];
        resetearInactividad();
    };
    unsafeWindow.tutoPrev = function() {
        pasoActual = (pasoActual - 1 + pasosTuto.length) % pasosTuto.length;
        let tutoTexto = document.getElementById('tutorial-texto');
        if(tutoTexto) tutoTexto.innerHTML = pasosTuto[pasoActual];
        resetearInactividad();
    };

    // REINICIO AUTOMATICO POR INACTIVIDAD (40 SEGUNDOS)
    let inactividadTimer;
    function resetearInactividad() {
        clearTimeout(inactividadTimer);
        let salva = document.getElementById('salvapantallas-totem');
        if(salva && !salva.classList.contains('pantalla-oculta')) {
            salva.classList.add('pantalla-oculta');
            if (modoActual !== '') unsafeWindow.tRestaurarDOM();
        }
        inactividadTimer = setTimeout(() => {
                if (modoActual !== '') unsafeWindow.tRestaurarDOM();
                else if(salva) salva.classList.remove('pantalla-oculta');
            }, 600000);
    }
    ['mousemove','mousedown','keypress','touchstart'].forEach(evt => document.addEventListener(evt, resetearInactividad));

    document.getElementById('salvapantallas-totem').addEventListener('touchstart', function(e) {
        e.preventDefault();
        e.stopPropagation();
        resetearInactividad();
    }, { passive: false });

    document.getElementById('salvapantallas-totem').addEventListener('mousedown', function(e) {
        e.preventDefault();
        e.stopPropagation();
        resetearInactividad();
    });

    resetearInactividad();

    unsafeWindow.tMostrarAyuda = function() {
        unsafeWindow.ultimaAccionKiosco = 'Abrió panel de AYUDA';
        document.getElementById('modal-ayuda').classList.add('activo');
        if (sessionStorage.getItem('kiosco_alerta_habilitada') === '1') {
            let btnPre = document.getElementById('btn-pre-enviar');
            if (btnPre) {
                btnPre.disabled = false;
                btnPre.style.opacity = '1';
                btnPre.style.cursor = 'pointer';
            }
        }
    };
    unsafeWindow.tOcultarAyuda = function() {
        unsafeWindow.ultimaAccionKiosco = 'Cerró panel de AYUDA';
        document.getElementById('modal-ayuda').classList.remove('activo');
        if (typeof unsafeWindow.tCancelarConfirmacion === 'function') unsafeWindow.tCancelarConfirmacion();
    };

    unsafeWindow.tLimpiarCacheVigorosa = function() {
        let btnLimpiar = document.getElementById('btn-limpiar-cache');
        
        if (btnLimpiar.innerText.includes('RECARGAR')) {
            window.location.reload();
            return;
        }

        btnLimpiar.innerHTML = '⏳ LIMPIANDO...';
        btnLimpiar.disabled = true;
        
        sessionStorage.removeItem('kiosco_tiempo_inicio');
        sessionStorage.removeItem('kiosco_modo_restaurar');
        sessionStorage.removeItem('kiosco_servicio_restaurar');
        sessionStorage.setItem('kiosco_alerta_habilitada', '1');
        
        setTimeout(() => {
            // 1. Crear cartel emergente arrancando invisible (opacity: 0) y transicion de 0.8s
            let toastLimpieza = document.createElement('div');
            toastLimpieza.style.cssText = 'position: fixed; top: 40%; left: 50%; transform: translate(-50%, -50%); background: rgba(34, 197, 94, 0.95); color: white; padding: 2vh 3vw; font-size: 3vh; font-weight: 900; border-radius: 15px; z-index: 999999; box-shadow: 0 10px 25px rgba(0,0,0,0.3); transition: opacity 0.8s ease; opacity: 0; pointer-events: none; text-align: center; border: 3px solid #16a34a;';
            toastLimpieza.innerHTML = '✅ CACHÉ LIMPIA';
            document.body.appendChild(toastLimpieza);
            
            // Disparar el fade-in
            setTimeout(() => { toastLimpieza.style.opacity = '1'; }, 10);
            
            // 2. Darle 2 segundos visible, luego fade-out suave
            setTimeout(() => { 
                toastLimpieza.style.opacity = '0'; 
                setTimeout(() => toastLimpieza.remove(), 800); 
            }, 2000);

            // 3. El botón pasa a RECARGAR con un efecto llamativo
            btnLimpiar.innerHTML = '👉 ¡TOCA PARA RECARGAR! 🔄';
            btnLimpiar.style.background = '#2563eb';
            btnLimpiar.style.animation = 'pulsoAura 1.2s infinite';
            btnLimpiar.style.boxShadow = '0 0 15px rgba(37,99,235,0.8)';
            btnLimpiar.disabled = false;
            
            // 4. Habilitar el botón de enviar alerta en silencio
            let btnPre = document.getElementById('btn-pre-enviar');
            if (btnPre) {
                btnPre.disabled = false;
                btnPre.style.opacity = '1';
                btnPre.style.cursor = 'pointer';
            }
        }, 800);
    };

    unsafeWindow.tSolicitarPin = function() {
        // Ocultamos los botones y LOS TEXTOS GIGANTES para que el PIN ocupe el centro sin salir de pantalla
        document.getElementById('botones-ayuda-principal').style.display = 'none';
        
        let caja = document.querySelector('#modal-ayuda .caja-ayuda');
        if (caja) {
            let titulo = caja.querySelector('h1');
            if (titulo) titulo.style.display = 'none';
            let divsInfo = caja.querySelectorAll('div[style*="border-left"]');
            divsInfo.forEach(d => d.style.display = 'none');
        }
        
        document.getElementById('panel-pin-soporte').style.display = 'block';
        document.getElementById('input-pin-alerta').value = '';
    };

    unsafeWindow.tAddPin = function(num) {
        let inputPin = document.getElementById('input-pin-alerta');
        if (inputPin.value.length < 10) inputPin.value += num;
    };

    unsafeWindow.tClearPin = function() {
        document.getElementById('input-pin-alerta').value = '';
    };

    unsafeWindow.tValidarPin = function() {
        let inputPin = document.getElementById('input-pin-alerta');
        fetch('https://federicogonzalez.net/actis/api_totem_auth.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ tipo: 'soporte', pin: inputPin.value })
        })
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                document.getElementById('panel-pin-soporte').style.display = 'none';
                document.getElementById('confirmacion-soporte').style.display = 'block';
            } else {
                inputPin.style.borderColor = '#dc2626';
                inputPin.style.background = '#fee2e2';
                setTimeout(() => { 
                    inputPin.value = ''; 
                    inputPin.style.borderColor = '#cbd5e1'; 
                    inputPin.style.background = 'white'; 
                }, 500);
            }
        })
        .catch(err => {
            inputPin.style.borderColor = '#dc2626';
            inputPin.style.background = '#fee2e2';
            setTimeout(() => { 
                inputPin.value = ''; 
                inputPin.style.borderColor = '#cbd5e1'; 
                inputPin.style.background = 'white'; 
            }, 500);
        });
    };

    // LOGICA HAMBURGUESA Y ROLLO
    unsafeWindow.tToggleMenu = function() {
        let menu = document.getElementById('totem-menu-opciones');
        if (menu.style.display === 'none' || menu.style.display === '') {
            menu.style.display = 'flex';
        } else {
            menu.style.display = 'none';
        }
    };
    document.addEventListener('click', function(e) {
        if (!e.target.closest('#totem-menu-flotante')) {
            let menu = document.getElementById('totem-menu-opciones');
            if (menu) menu.style.display = 'none';
        }
    });

    unsafeWindow.tMostrarCambioRollo = function() {
        let modal = document.getElementById('modal-cambio-rollo');
        modal.style.zIndex = '10000001';
        modal.classList.add('activo');
        document.getElementById('input-pin-rollo').value = '';
    };
    unsafeWindow.tOcultarCambioRollo = function() {
        document.getElementById('modal-cambio-rollo').classList.remove('activo');
    };
    unsafeWindow.tAddPinRollo = function(num) {
        let inputPin = document.getElementById('input-pin-rollo');
        if (inputPin.value.length < 10) inputPin.value += num;
    };
    unsafeWindow.tClearPinRollo = function() {
        document.getElementById('input-pin-rollo').value = '';
    };
    unsafeWindow.tValidarPinRollo = function() {
        let inputPin = document.getElementById('input-pin-rollo');
        let btn = document.getElementById('btn-validar-rollo');
        let originalText = btn.innerHTML;
        btn.innerHTML = '⏳ VALIDANDO...';
        btn.style.opacity = '0.7';
        btn.disabled = true;

        fetch('https://federicogonzalez.net/actis/api_reset_papel.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ pin: inputPin.value })
        })
        .then(response => response.json())
        .then(data => {
            btn.innerHTML = originalText;
            btn.style.opacity = '1';
            btn.disabled = false;
            if (data.status === 'success') {
                document.getElementById('modal-cambio-rollo').classList.remove('activo');
                let modalRes = document.getElementById('modal-resultado-kiosco');
                if (modalRes) modalRes.style.zIndex = '10000002';
                
                let bloqueo = document.getElementById('bloqueo-horario-dinamico');
                if (bloqueo) bloqueo.remove();

                unsafeWindow.tMostrarResultado('<h2 style="color: #22c55e; animation: latido 1.5s infinite;">✅ ROLLO REGISTRADO</h2><p style="font-size: 3vh; margin-top: 1vh;">El sistema se ha desbloqueado correctamente.</p>');
                setTimeout(() => {
                    unsafeWindow.tOcultarResultado();
                    if (modalRes) modalRes.style.zIndex = '';
                }, 3000);
            } else {
                inputPin.style.borderColor = '#dc2626';
                inputPin.style.background = '#fee2e2';
                setTimeout(() => { 
                    inputPin.value = ''; 
                    inputPin.style.borderColor = '#cbd5e1'; 
                    inputPin.style.background = 'white'; 
                }, 500);
            }
        })
        .catch(err => {
            btn.innerHTML = originalText;
            btn.style.opacity = '1';
            btn.disabled = false;
            inputPin.style.borderColor = '#dc2626';
            inputPin.style.background = '#fee2e2';
            setTimeout(() => { 
                inputPin.value = ''; 
                inputPin.style.borderColor = '#cbd5e1'; 
                inputPin.style.background = 'white'; 
            }, 500);
        });
    };

    unsafeWindow.tMostrarConfirmacion = function() {
        document.getElementById('botones-ayuda-principal').style.display = 'none';
        document.getElementById('confirmacion-soporte').style.display = 'block';
    };

    unsafeWindow.tCancelarConfirmacion = function() {
        let conf = document.getElementById('confirmacion-soporte');
        let panelPin = document.getElementById('panel-pin-soporte');
        let princ = document.getElementById('botones-ayuda-principal');
        if(conf) conf.style.display = 'none';
        if(panelPin) panelPin.style.display = 'none';
        
        // Restauramos los textos gigantes y el titulo
        let caja = document.querySelector('#modal-ayuda .caja-ayuda');
        if (caja) {
            let titulo = caja.querySelector('h1');
            if (titulo) titulo.style.display = 'block';
            let divsInfo = caja.querySelectorAll('div[style*="border-left"]');
            divsInfo.forEach(d => d.style.display = 'block');
        }
        
        if(princ) princ.style.display = 'flex';
    };

    unsafeWindow.tEnviarAlertaSoporte = function() {
        let btn = document.getElementById('btn-enviar-soporte');
        let txtOriginal = btn.innerHTML;
        btn.innerHTML = '📸 PREPARANDO...';
        btn.disabled = true;

        document.getElementById('modal-ayuda').classList.remove('activo');

        let toast = document.createElement('div');
        toast.id = 'toast-soporte';
        toast.style.cssText = 'position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: rgba(15,23,42,0.95); color: white; padding: 5vh 5vw; font-size: 5vh; font-weight: 900; border-radius: 20px; z-index: 999999; box-shadow: 0 20px 50px rgba(0,0,0,0.5); border: 6px solid #38bdf8; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; width: 80vw; max-width: 800px; backdrop-filter: blur(10px);';
        toast.innerHTML = '📸<br>SONRÍA PARA LA CÁMARA... 😄<br><div class="spinner-kiosco" style="width: 6vh; height: 6vh; border-width: 6px; border-top-color: #38bdf8; margin: 2vh auto;"></div><span style="font-size:3.5vh; color:#93c5fd; display:block;">ESTAMOS VERIFICANDO UN ERROR</span>';
        document.body.appendChild(toast);

        // Se da tiempo para que la persona lea el cartel
        setTimeout(() => {
            // Ocultamos el cartel para tomar la captura limpia
            toast.style.display = 'none';

            // Esperamos 2 SEGUNDOS COMPLETOS (2000ms) para que todas las animaciones se detengan y la imagen no salga borrosa
            setTimeout(() => {
                if (typeof html2canvas !== 'undefined') {
                    html2canvas(document.body).then(canvas => {
                        let base64image = canvas.toDataURL('image/jpeg', 0.6);
                        
                        // Volvemos a mostrar el cartel con el texto actualizado y el círculo girando
                        toast.style.display = 'flex';
                        toast.innerHTML = '⏳<br>CAPTURANDO PANTALLA...<br><div class="spinner-kiosco" style="width: 6vh; height: 6vh; border-width: 6px; border-top-color: #38bdf8; margin: 2vh auto;"></div><span style="font-size:3vh; color:#93c5fd; display:block;">ENVIANDO REPORTE AL SERVIDOR</span>';

                        fetch('https://federicogonzalez.net/actis/enviar_alerta_soporte.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'captura=' + encodeURIComponent(base64image)
                        })
                        .then(res => res.json()) 
                        .then(data => {
                            if(data.status === 'success') {
                                sessionStorage.removeItem('kiosco_alerta_habilitada');
                                toast.innerHTML = '✅<br>ALERTA Y CAPTURA ENVIADAS EXITOSAMENTE';
                                toast.style.background = 'rgba(22, 163, 74, 0.95)';
                                toast.style.borderColor = '#4ade80';
                            } else {
                                toast.innerHTML = '❌<br>ERROR DEL SERVIDOR:<br><span style="font-size:3vh;">' + (data.mensaje || 'Error desconocido') + '</span>';
                                toast.style.background = 'rgba(220, 38, 38, 0.95)';
                                toast.style.borderColor = '#f87171';
                            }
                            // Bajamos el tiempo del cartel final a 2 segundos (2000ms) para que no sea tan largo
                            setTimeout(() => { toast.remove(); btn.innerHTML = txtOriginal; btn.disabled = false; unsafeWindow.tCancelarConfirmacion(); }, 2000);
                        })
                        .catch(err => {
                            toast.innerHTML = '❌<br>ERROR DE CONEXIÓN AL ENVIAR';
                            toast.style.background = 'rgba(220, 38, 38, 0.95)';
                            toast.style.borderColor = '#f87171';
                            setTimeout(() => { toast.remove(); btn.innerHTML = txtOriginal; btn.disabled = false; unsafeWindow.tCancelarConfirmacion(); }, 2000);
                        });
                    }).catch(err => {
                        toast.style.display = 'flex';
                        toast.innerHTML = '❌<br>ERROR AL GENERAR LA IMAGEN';
                        toast.style.background = 'rgba(220, 38, 38, 0.95)';
                        toast.style.borderColor = '#f87171';
                        setTimeout(() => { toast.remove(); btn.innerHTML = txtOriginal; btn.disabled = false; unsafeWindow.tCancelarConfirmacion(); }, 2000);
                    });
                } else {
                    toast.style.display = 'flex';
                    toast.innerHTML = '❌<br>LIBRERÍA DE CAPTURA NO DISPONIBLE';
                    toast.style.background = 'rgba(220, 38, 38, 0.95)';
                    toast.style.borderColor = '#f87171';
                    setTimeout(() => { toast.remove(); btn.innerHTML = txtOriginal; btn.disabled = false; unsafeWindow.tCancelarConfirmacion(); }, 2000);
                }
            }, 2000); // <-- Los 2 segundos clavados antes de capturar
        }, 4000);
    };

    let servicioSeleccionado = '';
    let modoActual = '';
    let validacionDniTemporal = null;
    let datosServicios = [];
    let tiempoInicioInteraccion = 0;
    unsafeWindow.ultimaAccionKiosco = 'Esperando interacción...';

    const contenedorModos = document.createElement('div');
    contenedorModos.className = 'contenedor-modos';
    const btnAsistencia = document.createElement('button');
    btnAsistencia.type = 'button'; btnAsistencia.className = 'btn-modo btn-asistencia';
    btnAsistencia.innerHTML = '🏥 ASISTENCIA ESPONTÁNEA<br><span style="font-size: 2.5vh; font-weight: 700; margin-top: 1.5vh; display: block;">Retirar Orden y Token (Sin Turno)</span>';
    btnAsistencia.addEventListener('click', () => tSeleccionarModo('ASISTENCIA'));

    const btnValidacion = document.createElement('button');
    btnValidacion.type = 'button'; btnValidacion.className = 'btn-modo btn-validacion';
    btnValidacion.innerHTML = '✅ VALIDAR TURNO<br><span style="font-size: 2.5vh; font-weight: 700; margin-top: 1.5vh; display: block;">Obtener solo Token OSFA</span>';
    btnValidacion.addEventListener('click', () => tSeleccionarModo('VALIDACION'));
    contenedorModos.appendChild(btnAsistencia); contenedorModos.appendChild(btnValidacion);

    const carruselWrapper = document.createElement('div');
    carruselWrapper.className = 'carrusel-wrapper pantalla-oculta';
    carruselWrapper.id = 'carrusel-servicios-wrapper';

    const btnScrollIzq = document.createElement('button');
    btnScrollIzq.type = 'button';
    btnScrollIzq.className = 'btn-scroll';
    btnScrollIzq.innerHTML = '◀';
    btnScrollIzq.onmousedown = (e) => { e.preventDefault(); e.stopPropagation(); };
    btnScrollIzq.onclick = (e) => { e.preventDefault(); e.stopPropagation(); document.querySelector('.contenedor-servicios').scrollBy({ left: -500, behavior: 'smooth' }); };

    const contenedorServicios = document.createElement('div');
    contenedorServicios.className = 'contenedor-servicios';

    const btnScrollDer = document.createElement('button');
    btnScrollDer.type = 'button';
    btnScrollDer.className = 'btn-scroll';
    btnScrollDer.innerHTML = '▶';
    btnScrollDer.onmousedown = (e) => { e.preventDefault(); e.stopPropagation(); };
    btnScrollDer.onclick = (e) => { e.preventDefault(); e.stopPropagation(); document.querySelector('.contenedor-servicios').scrollBy({ left: 500, behavior: 'smooth' }); };

    carruselWrapper.appendChild(btnScrollIzq);
    carruselWrapper.appendChild(contenedorServicios);
    carruselWrapper.appendChild(btnScrollDer);

    const contenedorAbc = document.createElement('div');
    contenedorAbc.className = 'contenedor-abc pantalla-oculta';

    let intervaloArranque = setInterval(() => {
        let formContenedor = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout');

        if (formContenedor) {
            clearInterval(intervaloArranque);

            let cbPanel = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1');
            if(cbPanel && cbPanel.parentNode) {
                cbPanel.parentNode.insertBefore(contenedorModos, cbPanel);
                cbPanel.parentNode.insertBefore(carruselWrapper, cbPanel);
                cbPanel.parentNode.insertBefore(contenedorAbc, cbPanel);
                formContenedor.classList.add('pantalla-oculta');
            }

            let label = document.querySelector('label[for*="txtDNI_I"]');
            if (label) {
                label.innerHTML = '<b style="font-size:3.5vh; color: #334155; display:block; text-align:center; margin-bottom:2vh; font-weight:900;">INGRESE EL NÚMERO DE DNI:</b>';
            }

            let inputInit = document.querySelector('[id$="txtDNI_I"]');
            if(inputInit) inputInit.value = '';

            let btnNativoTexto = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout_btnBuscarAfiliado');
            if(btnNativoTexto) {
                btnNativoTexto.innerText = '🎫 CONFIRMAR DNI';
            }

            let tecladoHTML = document.createElement('div');
            tecladoHTML.id = 'teclado-kiosco-externo';
            tecladoHTML.className = 'teclado-kiosco';
            tecladoHTML.style.maxWidth = '600px';
            tecladoHTML.innerHTML = `
                <button type="button" class="kbtn" onmousedown="event.preventDefault()" onclick="tPulsa('1')">1</button>
                <button type="button" class="kbtn" onmousedown="event.preventDefault()" onclick="tPulsa('2')">2</button>
                <button type="button" class="kbtn" onmousedown="event.preventDefault()" onclick="tPulsa('3')">3</button>
                <button type="button" class="kbtn" onmousedown="event.preventDefault()" onclick="tPulsa('4')">4</button>
                <button type="button" class="kbtn" onmousedown="event.preventDefault()" onclick="tPulsa('5')">5</button>
                <button type="button" class="kbtn" onmousedown="event.preventDefault()" onclick="tPulsa('6')">6</button>
                <button type="button" class="kbtn" onmousedown="event.preventDefault()" onclick="tPulsa('7')">7</button>
                <button type="button" class="kbtn" onmousedown="event.preventDefault()" onclick="tPulsa('8')">8</button>
                <button type="button" class="kbtn" onmousedown="event.preventDefault()" onclick="tPulsa('9')">9</button>
                <button type="button" class="kbtn kbtn-borrar" onmousedown="event.preventDefault()" onclick="tLimpia()">BORRAR TODO</button>
                <button type="button" class="kbtn" onmousedown="event.preventDefault()" onclick="tPulsa('0')">0</button>
            `;
            let formLayoutTeclado = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout');
            if(formLayoutTeclado) {
                formLayoutTeclado.appendChild(tecladoHTML);
            }

            // NO MOVER EL BOTÓN DE SU CONTENEDOR NATIVO. Modificar su DOM dispara el Error 500 en DevExpress.
            let btnGenerar = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout_btnBuscarAfiliado');
            if(btnGenerar) {
                btnGenerar.innerText = '🎫 CONFIRMAR DNI';
                if (!btnGenerar.dataset.monitoreado) {
                    btnGenerar.addEventListener('click', () => { 
                        unsafeWindow.ultimaAccionKiosco = 'Presionó botón: CONFIRMAR DNI'; 
                        btnGenerar.innerHTML = '⏳ BUSCANDO...';
                        
                        // Sistema anti-cuelgue: Si OSFA no responde en 12 segundos, auto-recuperar
                        clearTimeout(unsafeWindow.timerCuelgueIosfa);
                        unsafeWindow.timerCuelgueIosfa = setTimeout(function() {
                if (!modalMostrado) {
                    unsafeWindow.tRestaurarDOM();
                }
            }, 600000);
                    });
                    btnGenerar.dataset.monitoreado = 'true';
                }
            }

            fetch('https://federicogonzalez.net/actis/get_servicios.php?t=' + new Date().getTime())
                .then(response => response.json())
                .then(servicios => {
                    if(servicios.cerrado) {
                        let btnRolloHTML = '';
                        if (servicios.sin_papel) {
                            btnRolloHTML = `
                                <button id="btn-cambio-rollo-dinamico" type="button" onclick="tMostrarCambioRollo()" style="margin-top: 4vh; padding: 2vh 3vw; font-size: 2.5vh; background: #0f172a; color: white; border: 2px solid #334155; border-radius: 12px; font-weight: 900; cursor: pointer; display: flex; align-items: center; gap: 1vw; box-shadow: 0 4px 15px rgba(0,0,0,0.5);">
                                    🖨️ CAMBIAR ROLLO
                                </button>
                            `;
                        }

                        let htmlCerrado = `
                            <div id="bloqueo-horario-dinamico" style="position: fixed; top:0; left:0; width: 100vw; height: 100vh; background: rgba(15,23,42,0.95); backdrop-filter: blur(15px); z-index: 9999999; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; color: white;">
                                <img src="https://federicogonzalez.net/actis/img/osfa_blanco.png" style="height: 15vh; margin-bottom: 3vh; filter: grayscale(1) brightness(2);">
                                <h1 style="font-size: 6vh; color: #ef4444; font-weight: 900; margin-bottom: 1vh;">TÓTEM CERRADO</h1>
                                <p id="bloqueo-mensaje" style="font-size: 4vh; font-weight: 800; max-width: 80%; line-height: 1.4;">${servicios.mensaje}</p>
                                ${btnRolloHTML}
                            </div>
                        `;
                        document.body.insertAdjacentHTML('beforeend', htmlCerrado);
                        return;
                    }

                    datosServicios = servicios;
                    let botonesAbc = ['⭐'].concat('ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split(''));

                    botonesAbc.forEach(item => {
                        let btnLetra = document.createElement('button');
                        btnLetra.type = 'button'; btnLetra.className = 'btn-abc'; btnLetra.innerText = item;
                        btnLetra.onclick = function() {
                            if (item === '⭐') { mostrarMasConsultados(); }
                            else { filtrarPorLetra(item); }
                        };
                        contenedorAbc.appendChild(btnLetra);
                    });
                    unsafeWindow.mostrarMasConsultados();
                })
                .catch(err => {
                    console.error('Fallo BD:', err);
                    datosServicios = [{nombre: "GUARDIA", consultas: 0, imprime_numero: 1}, {nombre: "LABORATORIO", consultas: 0, imprime_numero: 0}];
                });

            unsafeWindow.tPulsa = function(num) {
                if (typeof unsafeWindow.emitirBeep === 'function') unsafeWindow.emitirBeep('click');
                unsafeWindow.ultimaAccionKiosco = 'Teclado: Ingresó número ' + num;
                let inputReal = document.querySelector('[id$="txtDNI_I"]');
                if (!inputReal) return;

                let actual = inputReal.value;
                if (actual.includes('Ingrese el DNI')) {
                    inputReal.value = '';
                    actual = '';
                }
                if (actual.length < 8) {
                    inputReal.focus();
                    document.execCommand('insertText', false, num);
                }
            };

            unsafeWindow.tBorra = function() {
                if (typeof unsafeWindow.emitirBeep === 'function') unsafeWindow.emitirBeep('click');
                unsafeWindow.ultimaAccionKiosco = 'Teclado: Borró un número';
                let inputReal = document.querySelector('[id$="txtDNI_I"]');
                if (!inputReal) return;
                let actual = inputReal.value;
                if (actual.length > 0) {
                    inputReal.focus();
                    document.execCommand('delete', false, null);
                }
            };

            unsafeWindow.tLimpia = function() {
                if (typeof unsafeWindow.emitirBeep === 'function') unsafeWindow.emitirBeep('click');
                unsafeWindow.ultimaAccionKiosco = 'Teclado: Presionó BORRAR TODO';
                let inputReal = document.querySelector('[id$="txtDNI_I"]');
                if(inputReal) {
                    inputReal.value = '';
                    
                    // Limpieza profunda de los campos ocultos de DevExpress (el fantasma del DNI)
                    let rawDni = document.querySelector('[id$="txtDNI_Raw"]');
                    if (rawDni) rawDni.value = '';
                    
                    let dxObjName = inputReal.id.replace('_I', '');
                    if (typeof unsafeWindow[dxObjName] !== 'undefined' && unsafeWindow[dxObjName].SetText) {
                        unsafeWindow[dxObjName].SetText('');
                        if (typeof unsafeWindow[dxObjName].SetIsValid === 'function') unsafeWindow[dxObjName].SetIsValid(true);
                    }
                    
                    inputReal.focus();
                    
                    // Disparamos eventos nativos incluyendo el retroceso (Backspace) para forzar la actualización interna
                    inputReal.dispatchEvent(new Event('input', { bubbles: true }));
                    inputReal.dispatchEvent(new Event('change', { bubbles: true }));
                    inputReal.dispatchEvent(new KeyboardEvent('keyup', { bubbles: true, key: 'Backspace', keyCode: 8 }));
                }
            };
        }
    }, 500);

    function tSeleccionarModo(modo) {
        unsafeWindow.ultimaAccionKiosco = 'Seleccionó modalidad: ' + modo;
        tiempoInicioInteraccion = Date.now();
        sessionStorage.setItem('kiosco_tiempo_inicio', tiempoInicioInteraccion);
        modoActual = modo;
        
        // Ocultar Modos
        contenedorModos.classList.add('pantalla-oculta');
        let cMalvinasClick = document.getElementById('png-malvinas');
        if(cMalvinasClick) cMalvinasClick.classList.add('pantalla-oculta');
        let cajaTuto = document.getElementById('tutorial-kiosco');
        if(cajaTuto) cajaTuto.classList.add('pantalla-oculta');
        
        // AHORA MOSTRAMOS DNI PRIMERO (No Servicios)
        let formContenedor = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout');
        let tclExt = document.getElementById('teclado-kiosco-externo');
        if(formContenedor) {
            formContenedor.classList.remove('pantalla-oculta');
            formContenedor.style.display = 'flex';
        }
        if(tclExt) {
            tclExt.classList.remove('pantalla-oculta');
            tclExt.style.display = 'grid';
        }

        let btnHome = document.getElementById('totem-btn-home');
        if(btnHome) btnHome.classList.remove('pantalla-oculta');

        cabecera.innerHTML = '<img src="https://federicogonzalez.net/actis/img/osfa_blanco.png"><h1>POLICLÍNICA GENERAL ACTIS</h1><p>PASO 1: INGRESE SU NÚMERO DE DNI</p>';
        setTimeout(() => {
            let inputDni = document.querySelector('[id$="txtDNI_I"]');
            if(inputDni) inputDni.focus();
        }, 150);
    }


   function obtenerServiciosFiltrados() {
        if (modoActual === 'ASISTENCIA') return datosServicios.filter(s => s.imprime_numero == 1 || String(s.imprime_numero) === "1");
        return datosServicios;
    }

    unsafeWindow.mostrarMasConsultados = function() {
        unsafeWindow.ultimaAccionKiosco = 'Viendo: SERVICIOS DESTACADOS';
        contenedorServicios.innerHTML = '';
        let filtrados = obtenerServiciosFiltrados();

        if(filtrados.length === 0) {
            contenedorServicios.innerHTML = `<div style="grid-column: span 3; text-align: center; font-size: 3vh; color: #94a3b8; font-weight: 800; padding-top: 15vh; width: 100%;">NO HAY SERVICIOS CONFIGURADOS PARA ESTA OPCIÓN</div>`;
            marcarBotonActivo('⭐');
            return;
        }

        let ordenadosPorConsulta = [...filtrados].sort((a, b) => b.consultas - a.consultas);

        let destacados = ordenadosPorConsulta.slice(0, 16);
        let resto = ordenadosPorConsulta.slice(16);

        resto.sort((a, b) => a.nombre.localeCompare(b.nombre));

        destacados.forEach(serv => {
            let btn = document.createElement('button');
            btn.type = 'button'; btn.className = 'btn-servicio'; btn.innerHTML = `⭐ ${serv.nombre}`;
            btn.onclick = function() { ejecutarSeleccion(serv.nombre); };
            contenedorServicios.appendChild(btn);
        });

        resto.forEach(serv => {
            let btn = document.createElement('button');
            btn.type = 'button'; btn.className = 'btn-servicio'; btn.innerHTML = serv.nombre;
            btn.onclick = function() { ejecutarSeleccion(serv.nombre); };
            contenedorServicios.appendChild(btn);
        });

        marcarBotonActivo('⭐');
    }

    function filtrarPorLetra(letraActiva) {
        unsafeWindow.ultimaAccionKiosco = 'Filtró servicios por letra: ' + letraActiva;
        contenedorServicios.innerHTML = '';
        let filtrados = obtenerServiciosFiltrados().filter(serv => {
            let nombreLimpio = serv.nombre ? serv.nombre.trim().toUpperCase() : '';
            return nombreLimpio.startsWith(letraActiva);
        });
        if(filtrados.length === 0) {
            contenedorServicios.innerHTML = `<div style="grid-column: span 3; text-align: center; font-size: 3vh; color: #94a3b8; font-weight: 800; padding-top: 15vh; width: 100%;">SIN SERVICIOS CON LA LETRA "${letraActiva}"</div>`;
        } else {
            filtrados.forEach(serv => {
                let btn = document.createElement('button');
                btn.type = 'button'; btn.className = 'btn-servicio'; btn.innerText = serv.nombre;
                btn.onclick = function() { ejecutarSeleccion(serv.nombre); };
                contenedorServicios.appendChild(btn);
            });
        }
        marcarBotonActivo(letraActiva);
    }

    function marcarBotonActivo(itemActivo) {
        contenedorAbc.querySelectorAll('.btn-abc').forEach(b => {
            if(b.innerText === itemActivo) b.classList.add('activo');
            else b.classList.remove('activo');
        });
    }

    function ejecutarSeleccion(nombreServicio) {
        unsafeWindow.ultimaAccionKiosco = 'Eligió servicio: ' + nombreServicio;
        servicioSeleccionado = nombreServicio;
        fetch(`https://federicogonzalez.net/actis/registrar_consulta.php?servicio=${encodeURIComponent(nombreServicio)}`).catch(e=>e);

        if (modoActual === 'ASISTENCIA') {
            unsafeWindow.tMostrarLoading();
            fetch('https://federicogonzalez.net/actis/api_config_asistencia.php')
                .then(r => r.json())
                .then(data => {
                    unsafeWindow.tOcultarLoading();
                    if (!data.habilitado) {
                        unsafeWindow.tMostrarErrorSinTurno('La Demanda Espontánea está deshabilitada en este Tótem. Por favor, diríjase a Ventanilla.');
                    } else if (data.libre) {
                        procederImpresionTicket(nombreServicio);
                    } else {
                        // Solicitar PIN de seguridad
                        unsafeWindow.pinSeguridadEsperado = data.pin;
                        unsafeWindow.modalMostrado = true;
                        
                        let cabeceraElement = document.querySelector('.actis-header');
                        if(cabeceraElement) {
                            cabeceraElement.innerHTML = '<img src="https://federicogonzalez.net/actis/img/osfa_blanco.png"><h1>POLICLÍNICA GENERAL ACTIS</h1><p>PASO 3: AUTORIZACIÓN DE SEGURIDAD</p>';
                        }

                        let cCarrusel = document.getElementById('carrusel-servicios-wrapper');
                        if (cCarrusel) { cCarrusel.classList.add('pantalla-oculta'); cCarrusel.style.display = 'none'; }
                        contenedorAbc.classList.add('pantalla-oculta');
                        contenedorAbc.style.display = 'none';
                        
                        document.getElementById('lista-turnos-paciente').innerHTML = `<h4 style="color: #0369a1; font-size: 2.5vh; margin: 0 0 1vh 0;">Autorización Requerida</h4><p style="font-size: 2vh; margin: 0;">Servicio: <strong>${nombreServicio}</strong></p><p style="font-size: 2.2vh; margin-top: 1vh; color: #dc2626; font-weight: bold;">Solicite al personal de seguridad que ingrese el PIN de Demanda Espontánea.</p>`;
                        
                        let panel = document.getElementById('panel-pin-turno');
                        panel.classList.add('activo');
                        document.getElementById('input-pin-turno').value = '';
                    }
                })
                .catch(e => {
                    unsafeWindow.tOcultarLoading();
                    unsafeWindow.tMostrarErrorSinTurno('Ocurrió un error al verificar la Demanda Espontánea.');
                });
            return;
        }

        procederImpresionTicket(nombreServicio);
    }

    function procederImpresionTicket(nombreServicio) {
        document.getElementById('carrusel-servicios-wrapper').classList.add('pantalla-oculta');
        document.getElementById('carrusel-servicios-wrapper').style.display = 'none';
        contenedorAbc.classList.add('pantalla-oculta');
        contenedorAbc.style.display = 'none';

        // CUADRO FINAL DESPUÉS DE ELEGIR SERVICIO
        if (validacionDniTemporal) {
            modalMostrado = true;
            let htmlExito = `
                <h1 style="font-size: 4vh; color: #74acdf; margin-bottom: 2vh; font-weight: 900; text-shadow: 1px 1px 2px rgba(0,0,0,0.1); white-space: nowrap;">⭐⭐ ¡TODO LISTO! ⭐⭐</h1>
                <div style="background: linear-gradient(to bottom, #ffffff, #f1f5f9); padding: 3vh; border-radius: 15px; margin-bottom: 4vh; text-align: left; border: 3px solid #74acdf; box-shadow: 0 5px 15px rgba(116, 172, 223, 0.2);">
                    <p style="font-size: 2.2vh; margin: 0 0 0.5vh 0; color: #64748b; font-weight: bold;">PACIENTE:</p>
                    <p style="font-size: 3.5vh; margin: 0 0 3vh 0; color: #0f172a; font-weight: 900; text-transform: uppercase;">${validacionDniTemporal.nombre}</p>
                    <p style="font-size: 2.2vh; margin: 0 0 0.5vh 0; color: #64748b; font-weight: bold;">DNI:</p>
                    <p style="font-size: 3.5vh; margin: 0 0 3vh 0; color: #0f172a; font-weight: 900;">${validacionDniTemporal.dni}</p>
                    <p style="font-size: 2.2vh; margin: 0 0 0.5vh 0; color: #64748b; font-weight: bold;">TRÁMITE / SERVICIO:</p>
                    <p style="font-size: 3.5vh; margin: 0; color: #0284c7; font-weight: 900; text-transform: uppercase;">${servicioSeleccionado}</p>
                </div>
                <div style="display: flex; flex-direction: column; gap: 2vh;">
                    <button type="button" onclick="tConfirmarImpresion('${(validacionDniTemporal.dni||'').replace(/'/g, "\\'")}', '${(validacionDniTemporal.nombre||'').replace(/'/g, "\\'")}', '${(validacionDniTemporal.codigo||'').replace(/'/g, "\\'")}', '${(validacionDniTemporal.afiliado||'').replace(/'/g, "\\'")}', '${(validacionDniTemporal.fuerza||'').replace(/'/g, "\\'")}', '${(validacionDniTemporal.estado||'').replace(/'/g, "\\'")}')" style="padding: 2.5vh 2vw; font-size: 3vh; background: linear-gradient(180deg, #74acdf, #43a1d5); color: white; border: none; border-radius: 15px; font-weight: 900; cursor: pointer; box-shadow: 0 6px 0 #287bb5; text-transform: uppercase;">⚽ IMPRIMIR TICKET</button>
                    <button type="button" onclick="tVolverAServicios()" style="padding: 1.8vh 2vw; font-size: 2.2vh; background: #f1f5f9; color: #64748b; border: 2px solid #cbd5e1; border-radius: 15px; font-weight: 800; cursor: pointer;">🔄 ELEGIR OTRO SERVICIO</button>
                </div>
            `;
            unsafeWindow.tMostrarResultado(htmlExito, false);
        }
    }

    // PASO INTERMEDIO: CONFIRMAR IDENTIDAD Y ABRIR SERVICIOS
    // ARREGLO TURNOS: Variables globales para validación de turnos
    unsafeWindow.turnosPacienteHoy = [];
    
    // FUNCIONES DEL PIN DE TURNO
    unsafeWindow.tAddPinTurno = function(num) {
        let input = document.getElementById('input-pin-turno');
        if (input.value.length < 8) input.value += num;
    };
    unsafeWindow.tClearPinTurno = function() {
        document.getElementById('input-pin-turno').value = '';
    };
    
    unsafeWindow.tMostrarErrorSinTurno = function(mensaje) {
        let panel = document.getElementById('panel-pin-turno');
        if (panel) panel.classList.remove('activo');
        
        let htmlError = `
            <h1 style="font-size: 5vh; color: #dc2626; margin-bottom: 2vh; font-weight: 900;">⚠️ ATENCIÓN</h1>
            <div style="background: #fee2e2; padding: 4vh; border-radius: 15px; border: 4px solid #f87171; margin-bottom: 4vh;">
                <p style="font-size: 3vh; color: #991b1b; font-weight: bold; margin: 0;">${mensaje}</p>
            </div>
            <button type="button" onclick="tRestaurarDOM()" style="padding: 2vh 3vw; font-size: 2.5vh; background: #0f172a; color: white; border: none; border-radius: 12px; font-weight: 900; cursor: pointer; box-shadow: 0 6px 15px rgba(0,0,0,0.3);">🏠 VOLVER AL INICIO</button>
        `;
        unsafeWindow.tMostrarResultado(htmlError, false);
    };

    unsafeWindow.tCancelarConfirmacion = function() {
        let panel = document.getElementById('panel-pin-turno');
        if (panel) panel.classList.remove('activo');
        unsafeWindow.tRestaurarDOM();
    };

    unsafeWindow.tValidarPinTurno = function() {
        let input = document.getElementById('input-pin-turno');
        let pin = input.value.trim();
        if (pin === '') return;
        
        if (modoActual === 'ASISTENCIA') {
            if (pin === unsafeWindow.pinSeguridadEsperado) {
                document.getElementById('panel-pin-turno').classList.remove('activo');
                unsafeWindow.modalMostrado = false;
                procederImpresionTicket(servicioSeleccionado);
            } else {
                input.value = '';
                input.style.borderColor = '#ef4444';
                input.style.background = '#fef2f2';
                setTimeout(() => {
                    input.style.borderColor = '#0284c7';
                    input.style.background = '#f0f9ff';
                }, 500);
            }
            return;
        }

        // Buscar si el PIN introducido coincide con el ID de alguno de los turnos de hoy
        let turnoCoincidente = unsafeWindow.turnosPacienteHoy.find(t => String(t.id) === pin);
        
        if (turnoCoincidente) {
            // Número correcto!
            document.getElementById('panel-pin-turno').classList.remove('activo');
            unsafeWindow.modalMostrado = false; // Permitir que la máquina de estados avance
            // Como ya sabemos qué servicio es, disparamos la ejecución directamente
            procederImpresionTicket(turnoCoincidente.servicio);
        } else {
            // Número incorrecto
            input.value = '';
            input.style.borderColor = '#ef4444';
            input.style.background = '#fef2f2';
            setTimeout(() => {
                input.style.borderColor = '#0284c7';
                input.style.background = '#f0f9ff';
            }, 500);
        }
    };

    unsafeWindow.tMostrarLoading = function() {
        if(document.getElementById('actis_loading_overlay')) return;
        let loadingHTML = `<div id="actis_loading_overlay" style="position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.95); z-index: 999999; display: flex; flex-direction: column; align-items: center; justify-content: center; backdrop-filter: blur(10px);">
            <img src="https://federicogonzalez.net/actis/img/osfa_blanco.png" style="height: 12vh; margin-bottom: 3vh; animation: latido 1.5s infinite;">
            <h2 style="color: white; font-family: sans-serif; font-size: 3vh;">VERIFICANDO TURNOS...</h2>
        </div>`;
        document.body.insertAdjacentHTML('beforeend', loadingHTML);
    };

    unsafeWindow.tOcultarLoading = function() {
        let overlay = document.getElementById('actis_loading_overlay');
        if(overlay) overlay.remove();
    };

    unsafeWindow.tConfirmarIdentidad = function(dni, nombre, codigo, afiliado, fuerza, estado) {
        clearTimeout(unsafeWindow.timerCuelgueIosfa);
        unsafeWindow.ultimaAccionKiosco = 'Confirmó Identidad: ' + nombre;
        validacionDniTemporal = {
            dni: dni,
            nombre: nombre,
            codigo: codigo,
            afiliado: afiliado,
            fuerza: fuerza,
            estado: estado
        };

        unsafeWindow.tOcultarResultado();
        modalMostrado = false;

        let frmCont = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout');
        let tclExt = document.getElementById('teclado-kiosco-externo');
        if(frmCont) { frmCont.classList.add('pantalla-oculta'); frmCont.style.display = 'none'; }
        if(tclExt) { tclExt.classList.add('pantalla-oculta'); tclExt.style.display = 'none'; }

        // Si es ASISTENCIA ESPONTANEA, no verificamos turno, lo mandamos a los servicios
        if (modoActual === 'ASISTENCIA') {
            unsafeWindow.mostrarMasConsultados();
            
            let cCarrusel = document.getElementById('carrusel-servicios-wrapper');
            let cAbc = document.querySelector('.contenedor-abc');
            if (cCarrusel) { cCarrusel.classList.remove('pantalla-oculta'); cCarrusel.style.display = ''; }
            if (cAbc) { cAbc.classList.remove('pantalla-oculta'); cAbc.style.display = 'grid'; }
            
            let cabeceraElement = document.querySelector('.actis-header');
            if(cabeceraElement) {
                cabeceraElement.innerHTML = '<img src="https://federicogonzalez.net/actis/img/osfa_blanco.png"><h1>POLICLÍNICA GENERAL ACTIS</h1><p>PASO 2: SELECCIONE SU TRÁMITE O SERVICIO</p>';
            }

            audioSaludo.play().catch(e => {
                let nombreArchivo = audioSaludo.src.split('/').pop();
                audioSaludo.src = 'https://federicogonzalez.net/actis/audio/' + nombreArchivo;
                audioSaludo.play().catch(err => console.log('Audio no reproducido', err));
            });
            return;
        }

        // 🛑 NUEVO PASO: VERIFICAR TURNO ANTES DE CONTINUAR (SOLO MODO VALIDACION)
        unsafeWindow.verificandoTurno = true;
        // MOSTRAR CARGANDO
        unsafeWindow.tMostrarLoading();

        fetch('https://federicogonzalez.net/actis/api_verificar_turno.php?dni=' + dni)
            .then(res => res.json())
            .then(data => {
                unsafeWindow.tOcultarLoading();
                if (data.tiene_turno) {
                    unsafeWindow.turnosPacienteHoy = data.turnos;
                    unsafeWindow.verificandoTurno = false;
                    unsafeWindow.modalMostrado = true; // Frenar el carrusel!
                    
                    let cabeceraElement = document.querySelector('.actis-header');
                    if(cabeceraElement) {
                        cabeceraElement.innerHTML = '<img src="https://federicogonzalez.net/actis/img/osfa_blanco.png"><h1>POLICLÍNICA GENERAL ACTIS</h1><p>PASO 2: INGRESE NÚMERO DE TURNO</p>';
                    }
                    // Renderizar la lista de turnos
                    let htmlTurnos = '<h4 style="color: #0369a1; font-size: 2.5vh; margin: 0 0 1vh 0;">Turnos Registrados para Hoy:</h4><ul style="list-style: none; padding: 0; margin: 0;">';
                    data.turnos.forEach(t => {
                        htmlTurnos += `<li style="font-size: 2.5vh; color: #0f172a; padding: 1.5vh 0; border-bottom: 1px dashed #bae6fd;">
                            <strong>${t.servicio}</strong> - ${t.hora}hs
                        </li>`;
                    });
                    htmlTurnos += '</ul>';
                    document.getElementById('lista-turnos-paciente').innerHTML = htmlTurnos;

                    let panel = document.getElementById('panel-pin-turno');
                    panel.classList.add('activo');
                    document.getElementById('input-pin-turno').value = '';

                    let nombreArchivo = audioSaludo.src.split('/').pop();
                    audioSaludo.src = 'https://federicogonzalez.net/actis/audio/' + nombreArchivo;
                    audioSaludo.play().catch(err => console.log('Audio no reproducido', err));
                } else {
                    // NO TIENE TURNO REGISTRADO
                    if (data.turno_cancelado) {
                        let tc = data.turno_cancelado;
                        unsafeWindow.tMostrarErrorSinTurno(`Su turno para <strong>${tc.servicio}</strong> a las ${tc.hora}hs ha sido <strong style="color:#ef4444;">CANCELADO</strong>.<br><br><span style="font-size: 2.2vh;">Fecha de cancelación: ${tc.cargado_el} hs<br>Operador: ${tc.cargado_por}</span><br><br>Por favor, diríjase a Ventanilla.`);
                    } else {
                        unsafeWindow.tMostrarErrorSinTurno('No se registran turnos para este DNI el día de hoy. Por favor, diríjase a Ventanilla.');
                    }
                }
            })
            .catch(err => {
                console.error("Error al verificar turno:", err);
                unsafeWindow.tOcultarLoading();
                unsafeWindow.verificandoTurno = false;
                unsafeWindow.tMostrarErrorSinTurno('Ocurrió un error al verificar los turnos. Por favor, diríjase a Ventanilla.');
            });
    };

    unsafeWindow.tVolverAServicios = function() {
        unsafeWindow.ultimaAccionKiosco = 'Volvió a ELEGIR SERVICIO';
        unsafeWindow.tOcultarResultado();
        modalMostrado = false;
        servicioSeleccionado = '';
        
        let cCarrusel = document.getElementById('carrusel-servicios-wrapper');
        let cAbc = document.querySelector('.contenedor-abc');
        if (cCarrusel) { cCarrusel.classList.remove('pantalla-oculta'); cCarrusel.style.display = ''; }
        if (cAbc) { cAbc.classList.remove('pantalla-oculta'); cAbc.style.display = 'grid'; }
    };

    // ACCIONES NATIVAS ADAPTADAS DEL MODAL PERSONALIZADO
    unsafeWindow.tConfirmarImpresion = function(dni, nombre, codigo, afiliado, fuerza, estado) {
        unsafeWindow.ultimaAccionKiosco = 'Presionó botón: IMPRIMIR TICKET';
        let tiempoTotalSegundos = 0;
        let tiempoGuardado = sessionStorage.getItem('kiosco_tiempo_inicio');

        if (tiempoGuardado && parseInt(tiempoGuardado) > 0) {
            tiempoTotalSegundos = ((Date.now() - parseInt(tiempoGuardado)) / 1000).toFixed(2);
        } else if (tiempoInicioInteraccion > 0) {
            tiempoTotalSegundos = ((Date.now() - tiempoInicioInteraccion) / 1000).toFixed(2);
        }

        sessionStorage.removeItem('kiosco_tiempo_inicio');

        unsafeWindow.tOcultarResultado();
        if(typeof unsafeWindow.emitirBeep === 'function') unsafeWindow.emitirBeep('exito');
        
        // Disparamos el audio del saludo oficial justo con el estallido de la pantalla
        if (typeof audioSaludo !== 'undefined') {
            audioSaludo.currentTime = 0;
            audioSaludo.play().catch(e => console.log('Audio retenido por navegador'));
        }

        let formContainer = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout');
        if (formContainer) formContainer.classList.add('pantalla-oculta');

        if (typeof GM_xmlhttpRequest !== 'undefined') {
            let estadoRapido = { modo: modoActual, dni_input: dni, modal_activo: true, ayuda_activa: false, salvapantallas: false, servicio_seleccionado: servicioSeleccionado, html_modal: '', ultima_accion: 'Redirigiendo a imprimir...' };
            GM_xmlhttpRequest({ method: "POST", url: "https://federicogonzalez.net/actis/sync_estado.php", headers: { "Content-Type": "application/json" }, data: JSON.stringify(estadoRapido) });
        }

        // Salto directo a la impresión
        setTimeout(() => {
            window.location.href = `https://federicogonzalez.net/actis/imprimir_ticket_iofa.php?dni=${encodeURIComponent(dni)}&nombre=${encodeURIComponent(nombre)}&afiliado=${encodeURIComponent(afiliado)}&codigo=${encodeURIComponent(codigo)}&servicio=${encodeURIComponent(servicioSeleccionado)}&modo=${encodeURIComponent(modoActual)}&tiempo=${tiempoTotalSegundos}&return=dni&fuerza=${encodeURIComponent(fuerza)}&estado=${encodeURIComponent(estado)}`;
        }, 200);
    };


    // SOLUCIÓN 100% DINÁMICA: Limpia memoria y DevExpress sin recargar la página
    unsafeWindow.tReintentarDni = function() {
        clearTimeout(unsafeWindow.timerCuelgueIosfa);
        unsafeWindow.ultimaAccionKiosco = 'Presionó: VOLVER A INGRESAR DNI';
        
        // 1. Ocultamos el cartel
        unsafeWindow.tOcultarResultado();
        modalMostrado = false;
        validacionDniTemporal = null;
        
        // 2. Borramos la memoria fantasma de nuestro script
        sessionStorage.removeItem('kiosco_ultimo_dni');
        
        // 3. Destruimos los textos de validación donde DevExpress guarda el éxito anterior
        let elCodigo = document.querySelector('[id$="divDatosAfiliado_ValidacionId"]');
        let elNombre = document.querySelector('[id$="divDatosAfiliado_Afiliado"]');
        if (elCodigo) elCodigo.innerText = '';
        if (elNombre) elNombre.innerText = '';
        
        // 4. Limpiamos cualquier celda de error nativa de DevExpress que trabe nuevas búsquedas
        let erroresSys = document.querySelectorAll('.dxeErrorCellSys, table[id*="Error"]');
        erroresSys.forEach(e => {
            e.style.display = 'none';
            e.innerHTML = '';
        });
        
        // 5. Devolvemos el botón a su estado normal
        let btnTicket = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout_btnBuscarAfiliado');
        if (btnTicket) {
            btnTicket.innerText = '🎫 CONFIRMAR DNI';
            btnTicket.disabled = false;
        }

        // 6. Ejecutamos la limpieza profunda del teclado para resetear el input real
        unsafeWindow.tLimpia();
    };
    
    // Al iniciar el script, verificamos si venimos de un reinicio por error
    let modoGuardado = sessionStorage.getItem('kiosco_modo_restaurar');
    let servicioGuardado = sessionStorage.getItem('kiosco_servicio_restaurar');

    if (modoGuardado && servicioGuardado) {
        // Limpiamos la memoria
        sessionStorage.removeItem('kiosco_modo_restaurar');
        sessionStorage.removeItem('kiosco_servicio_restaurar');

        // Simulamos los pasos para volver instantáneamente a la pantalla del DNI
        setTimeout(() => {
            modoActual = modoGuardado;
            servicioSeleccionado = servicioGuardado;

            contenedorModos.classList.add('pantalla-oculta');
            let cajaTuto = document.getElementById('tutorial-kiosco');
            if(cajaTuto) cajaTuto.classList.add('pantalla-oculta');

            let btnHome = document.getElementById('totem-btn-home');
            if(btnHome) btnHome.classList.remove('pantalla-oculta');

            let btnVolver = document.getElementById('totem-btn-volver');
            if(btnVolver) btnVolver.classList.remove('pantalla-oculta');

            let formContenedor = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout');
            if(formContenedor) {
                formContenedor.classList.remove('pantalla-oculta');
                formContenedor.style.display = 'flex';
            }

            cabecera.innerHTML = '<img src="https://federicogonzalez.net/actis/img/osfa_blanco.png"><h1>POLICLÍNICA GENERAL ACTIS</h1><p>INGRESE SU NÚMERO DE DNI</p>';

            setTimeout(() => {
                let inputDni = document.querySelector('[id$="txtDNI_I"]');
                if(inputDni) {
                    if (document.forms.length > 0) document.forms[0].reset();
                    inputDni.value = '';
                    inputDni.setAttribute('value', '');
                    inputDni.dispatchEvent(new Event('input', { bubbles: true }));
                    inputDni.dispatchEvent(new Event('change', { bubbles: true }));
                    inputDni.focus();
                }
            }, 300);
        }, 800); // Pequeño delay para asegurar que el DOM nativo cargó primero
    }

    let detectado = false;
    let cargandoMostrado = false;
    let modalMostrado = false;

    setInterval(() => {
        let inputDni = document.querySelector('[id$=\"txtDNI_I\"]');
        let btnTicket = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout_btnBuscarAfiliado');
        let frmCont = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout');
        
        if (frmCont) {
            // Eliminar teclados duplicados fantasma antes de inyectar
            let teclados = document.querySelectorAll('.teclado-kiosco');
            if (teclados.length > 1) {
                for(let i = 1; i < teclados.length; i++) teclados[i].remove();
            }
            
            let tclExt = document.getElementById('teclado-kiosco-externo');
            if (!tclExt) {
                let tecladoHTML = document.createElement('div');
                tecladoHTML.id = 'teclado-kiosco-externo';
                tecladoHTML.className = 'teclado-kiosco';
                tecladoHTML.style.maxWidth = '600px';
                tecladoHTML.innerHTML = `
                    <button type="button" class="kbtn" onmousedown="event.preventDefault()" onclick="tPulsa('1')">1</button>
                    <button type="button" class="kbtn" onmousedown="event.preventDefault()" onclick="tPulsa('2')">2</button>
                    <button type="button" class="kbtn" onmousedown="event.preventDefault()" onclick="tPulsa('3')">3</button>
                    <button type="button" class="kbtn" onmousedown="event.preventDefault()" onclick="tPulsa('4')">4</button>
                    <button type="button" class="kbtn" onmousedown="event.preventDefault()" onclick="tPulsa('5')">5</button>
                    <button type="button" class="kbtn" onmousedown="event.preventDefault()" onclick="tPulsa('6')">6</button>
                    <button type="button" class="kbtn" onmousedown="event.preventDefault()" onclick="tPulsa('7')">7</button>
                    <button type="button" class="kbtn" onmousedown="event.preventDefault()" onclick="tPulsa('8')">8</button>
                    <button type="button" class="kbtn" onmousedown="event.preventDefault()" onclick="tPulsa('9')">9</button>
                    <button type="button" class="kbtn kbtn-borrar" onmousedown="event.preventDefault()" onclick="tLimpia()">BORRAR TODO</button>
                    <button type="button" class="kbtn" onmousedown="event.preventDefault()" onclick="tPulsa('0')">0</button>
                `;
                let formLayoutLazo = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout');
                if(formLayoutLazo) {
                    formLayoutLazo.appendChild(tecladoHTML);
                }
                tclExt = tecladoHTML;
            }

            if (!document.querySelector('.contenedor-modos') && typeof contenedorModos !== 'undefined') {
                let cbPanelLazo2 = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1');
                if(cbPanelLazo2 && cbPanelLazo2.parentNode) {
                    cbPanelLazo2.parentNode.insertBefore(contenedorModos, cbPanelLazo2);
                    cbPanelLazo2.parentNode.insertBefore(carruselWrapper, cbPanelLazo2);
                    cbPanelLazo2.parentNode.insertBefore(contenedorAbc, cbPanelLazo2);
                }
            }

            // MÁQUINA DE ESTADOS VISUAL ESTRICTA (Evita solapamientos)
            let cModos = document.querySelector('.contenedor-modos');
            let cCarrusel = document.getElementById('carrusel-servicios-wrapper');
            let cAbc = document.querySelector('.contenedor-abc');
            let cMalvinas = document.getElementById('png-malvinas');
            

            if (modoActual === '') {
                // PANTALLA INICIO
                if (frmCont) { frmCont.classList.add('pantalla-oculta'); frmCont.style.setProperty('display', 'none', 'important'); }
                if (tclExt) { tclExt.classList.add('pantalla-oculta'); tclExt.style.setProperty('display', 'none', 'important'); }
                if (cModos) { cModos.classList.remove('pantalla-oculta'); cModos.style.setProperty('display', 'flex', 'important'); }
                if (cCarrusel) { cCarrusel.classList.add('pantalla-oculta'); cCarrusel.style.setProperty('display', 'none', 'important'); }
                if (cAbc) { cAbc.classList.add('pantalla-oculta'); cAbc.style.setProperty('display', 'none', 'important'); }
                if (cMalvinas) { cMalvinas.classList.remove('pantalla-oculta'); cMalvinas.style.setProperty('display', 'block', 'important'); }
            } else if (modoActual !== '' && !validacionDniTemporal) {
                // PANTALLA DNI (Paso 1)
                if (frmCont) { frmCont.classList.remove('pantalla-oculta'); frmCont.style.setProperty('display', 'flex', 'important'); }
                if (tclExt) { tclExt.classList.remove('pantalla-oculta'); tclExt.style.setProperty('display', 'grid', 'important'); }
                if (cModos) { cModos.classList.add('pantalla-oculta'); cModos.style.setProperty('display', 'none', 'important'); }
                if (cCarrusel) { cCarrusel.classList.add('pantalla-oculta'); cCarrusel.style.setProperty('display', 'none', 'important'); }
                if (cAbc) { cAbc.classList.add('pantalla-oculta'); cAbc.style.setProperty('display', 'none', 'important'); }
                if (cMalvinas) { cMalvinas.classList.add('pantalla-oculta'); cMalvinas.style.setProperty('display', 'none', 'important'); }
            } else if (modoActual !== '' && validacionDniTemporal && servicioSeleccionado === '' && !modalMostrado && !unsafeWindow.verificandoTurno) {
                // PANTALLA SERVICIOS (Paso 2)
                if (frmCont) { frmCont.classList.add('pantalla-oculta'); frmCont.style.setProperty('display', 'none', 'important'); }
                if (tclExt) { tclExt.classList.add('pantalla-oculta'); tclExt.style.setProperty('display', 'none', 'important'); }
                if (cModos) { cModos.classList.add('pantalla-oculta'); cModos.style.setProperty('display', 'none', 'important'); }
                if (cCarrusel) { cCarrusel.classList.remove('pantalla-oculta'); cCarrusel.style.setProperty('display', 'flex', 'important'); }
                if (cAbc) { cAbc.classList.remove('pantalla-oculta'); cAbc.style.setProperty('display', 'grid', 'important'); }
                if (cMalvinas) { cMalvinas.classList.add('pantalla-oculta'); cMalvinas.style.setProperty('display', 'none', 'important'); }
            } else {
                // RESULTADO FINAL / ESTADO CARGANDO VERIFICACION
                if (frmCont) { frmCont.classList.add('pantalla-oculta'); frmCont.style.setProperty('display', 'none', 'important'); }
                if (tclExt) { tclExt.classList.add('pantalla-oculta'); tclExt.style.setProperty('display', 'none', 'important'); }
                if (cModos) { cModos.classList.add('pantalla-oculta'); cModos.style.setProperty('display', 'none', 'important'); }
                if (cCarrusel) { cCarrusel.classList.add('pantalla-oculta'); cCarrusel.style.setProperty('display', 'none', 'important'); }
                if (cAbc) { cAbc.classList.add('pantalla-oculta'); cAbc.style.setProperty('display', 'none', 'important'); }
                if (cMalvinas) { cMalvinas.classList.add('pantalla-oculta'); cMalvinas.style.setProperty('display', 'none', 'important'); }
            }
        }

        if (inputDni) {
            if (inputDni.getAttribute('autocomplete') !== 'off') inputDni.setAttribute('autocomplete', 'off');
            
            let txtDniVal = inputDni.value.trim();
            if (txtDniVal.length >= 6 && !txtDniVal.includes('Ingrese')) {
                if(btnTicket) btnTicket.classList.add('visible');
            } else {
                if(btnTicket) btnTicket.classList.remove('visible');
            }
            // Guardar DNI constantemente para que no se pierda al confirmar
            if (inputDni.value.trim().length > 0 && inputDni.value !== 'Ingrese el DNI') {
                sessionStorage.setItem('kiosco_ultimo_dni', inputDni.value.trim());
            }
        }

        // CONTROL DE ERRORES NATIVOS: INTERCEPTA EL POPUP INVISIBLE Y LANZA NUESTRO MODAL
        let errored = false;
        let popupsDetectados = document.querySelectorAll('.dxpc-mainDiv, .modal-dialog, .modal-content');
        popupsDetectados.forEach(p => {
            if (p.innerText.includes('Callback request failed') || p.innerText.includes('internal server error')) {
                window.location.replace('https://validador.iosfa.gob.ar/ValidadorDni'); // Si DevExpress colapsa internamente, auto-recuperamos el tótem
            } else if (p.innerText.includes('no Existe') || p.innerText.includes('No esta Activo')) {
                if (!p.hasAttribute('data-procesado-error')) {
                    errored = true;
                    p.setAttribute('data-procesado-error', 'true');
                    // Cierra el popup nativo por detrás simulando el clic en "Cerrar"
                    let botonesCerrar = p.querySelectorAll('button, .btn, .dxbButtonSys, .dxb');
                    botonesCerrar.forEach(b => {
                        if (b.innerText && b.innerText.trim() === 'Cerrar') b.click();
                    });
                    // Ocultamos visualmente sin destruir el DOM interno de DevExpress
                    p.style.display = 'none';
                    p.style.opacity = '0';
                    p.style.pointerEvents = 'none';
                }
            }
        });

        if (errored && !modalMostrado) {
            modalMostrado = true;
            let htmlError = `
                <h1 style="font-size: 5vh; color: #dc2626; margin-bottom: 2vh; font-weight: 900;">⚠️ ERROR DE AFILIADO</h1>
                <p style="font-size: 3.2vh; color: #334155; font-weight: 800; line-height: 1.5; margin-bottom: 5vh;">
                    El número ingresado no corresponde a un afiliado activo, está dado de baja o está mal escrito.
                </p>
                <button type="button" onclick="tReintentarDni()" style="width: 100%; padding: 2.5vh 2vw; font-size: 3vh; background: #0f172a; color: white; border: none; border-radius: 15px; font-weight: 900; cursor: pointer; text-transform: uppercase; box-shadow: 0 6px 0 #020617;">🔄 VOLVER A INGRESAR DNI</button>
            `;
            unsafeWindow.tMostrarResultado(htmlError, true);
        }

        if(detectado) return;

        // INTERCEPCIÓN DE RESPUESTA EXITOSA
        let elCodigo = document.querySelector('[id$=\"divDatosAfiliado_ValidacionId\"]');
        let formVisibleCheck = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_divDatosAfiliado');

        if (elCodigo && elCodigo.innerText.trim().length >= 5 && formVisibleCheck && formVisibleCheck.style.display !== 'none' && modoActual !== '' && !modalMostrado && !validacionDniTemporal) {
            
            let codigoReal = elCodigo.innerText.trim();
            let elNombre = document.querySelector('[id$=\"divDatosAfiliado_Afiliado\"]');
            let nombreReal = elNombre ? elNombre.innerText.trim() : 'AFILIADO';
            let arr_nombres = nombreReal.split('-');
            let nombre_limpio = arr_nombres[0] ? arr_nombres[0].trim() : nombreReal;
            
            let dni_limpio = sessionStorage.getItem('kiosco_ultimo_dni') || '00000000';
            
            let elAfiliado = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_divDatosAfiliado_Codigo');
            let numAfiliado = elAfiliado ? elAfiliado.innerText.trim() : 'NO REGISTRA';

            // --- INICIO BLOQUEO DE FUERZAS NO AUTORIZADAS (MEDICUS) ---
            let spanFuerza = document.querySelector('span[class^="afi"]');
            let fuerzaTexto = spanFuerza ? spanFuerza.innerText.trim().toUpperCase() : 'NO ESPECIFICADA';

            // Bloqueamos estrictamente si el texto NO contiene "ARMADA" (así filtramos Prefectura, Gendarmería, Policía, etc)
            if (fuerzaTexto !== '' && fuerzaTexto !== 'NO ESPECIFICADA' && !fuerzaTexto.includes('ARMADA')) {
                modalMostrado = true;
                let htmlErrorMedicus = `
                    <h1 style="font-size: 4.2vh; color: #dc2626; margin: 0 0 1.5vh 0; font-weight: 900;">⚠️ ATENCIÓN AFILIADO</h1>
                    
                    <div style="background: #f8fafc; padding: 2vh; border-radius: 12px; margin-bottom: 1.5vh; border: 2px solid #e2e8f0; text-align: left;">
                        <p style="font-size: 1.8vh; margin: 0; color: #64748b; font-weight: bold;">PACIENTE:</p>
                        <p style="font-size: 2.4vh; margin: 0 0 1vh 0; color: #0f172a; font-weight: 900; text-transform: uppercase;">${nombre_limpio}</p>
                        
                        <div style="display: flex; gap: 2vw;">
                            <div>
                                <p style="font-size: 1.8vh; margin: 0; color: #64748b; font-weight: bold;">DNI:</p>
                                <p style="font-size: 2.4vh; margin: 0; color: #0f172a; font-weight: 900;">${dni_limpio}</p>
                            </div>
                            <div style="flex: 1;">
                                <p style="font-size: 1.8vh; margin: 0; color: #64748b; font-weight: bold;">FUERZA:</p>
                                <p style="font-size: 2.2vh; margin: 0; color: #ef4444; font-weight: 900; text-transform: uppercase; line-height: 1.1;">${fuerzaTexto}</p>
                            </div>
                        </div>
                    </div>

                    <div style="background: #fee2e2; padding: 2vh; border-radius: 10px; border-left: 6px solid #dc2626; margin-bottom: 2vh; text-align: left;">
                        <p style="font-size: 2.1vh; color: #334155; font-weight: 800; margin: 0 0 1.5vh 0; line-height: 1.3;">
                            Usted ya no pertenece a la red de atención de OSFA. Su cobertura corresponde a <strong>MEDICUS</strong>.
                        </p>
                        <div style="border-top: 1px solid #fca5a5; padding-top: 1.5vh; font-size: 2vh; line-height: 1.6;">
                            <span style="display: block; color: #059669; font-weight: bold; white-space: nowrap;">📱 WhatsApp: 11-5094-1119</span>
                            <span style="display: block; color: #1e40af; font-weight: bold; white-space: nowrap;">📞 Call: 0800-333-7624 / 4129-5050</span>
                            <span style="display: block; color: #475569; font-weight: bold; white-space: nowrap;">✉️ contactenos@medicus.com.ar</span>
                        </div>
                    </div>

                    <button type="button" onclick="tReintentarDni()" style="width: 100%; padding: 2vh 2vw; font-size: 2.5vh; background: #0f172a; color: white; border: none; border-radius: 12px; font-weight: 900; cursor: pointer; text-transform: uppercase; box-shadow: 0 4px 0 #020617;">🔄 FINALIZAR Y VOLVER</button>
                `;
                unsafeWindow.tMostrarResultado(htmlErrorMedicus, true);
                return;
            }
            // --- FIN BLOQUEO DE FUERZAS ---

            // CARTEL INTERMEDIO: CONFIRMACIÓN DE IDENTIDAD (CORREGIDO PARA APELLIDOS CON COMILLAS)
            modalMostrado = true;
            
            // Escapamos las comillas simples de manera segura para que no rompan el atributo onclick
            let nombreEscapadoJs = nombre_limpio.replace(/'/g, "\\'");
            let fuerzaEscapadaJs = fuerzaTexto.replace(/'/g, "\\'");
            
            let divGeneral = formVisibleCheck.innerText.toUpperCase();
            let estadoAfiliado = 'NO ESPECIFICADO';
            if (divGeneral.includes('ACTIVO')) estadoAfiliado = 'ACTIVO';
            if (divGeneral.includes('INACTIVO')) estadoAfiliado = 'INACTIVO';

            let htmlExito = `
                <h1 style="font-size: 3.5vh; color: #74acdf; margin-bottom: 2vh; font-weight: 900; text-shadow: 1px 1px 2px rgba(0,0,0,0.1); white-space: nowrap;">⭐⭐ ¡AFILIADO ENCONTRADO! ⭐⭐</h1>
                <div style="background: linear-gradient(to bottom, #ffffff, #f1f5f9); padding: 2vh 3vh; border-radius: 15px; margin-bottom: 2vh; text-align: left; border: 3px solid #74acdf; box-shadow: 0 5px 15px rgba(116, 172, 223, 0.2); word-break: break-word;">
                    <p style="font-size: 2.2vh; margin: 0 0 0.5vh 0; color: #64748b; font-weight: bold;">APELLIDO Y NOMBRE:</p>
                    <p style="font-size: 3.5vh; margin: 0 0 3vh 0; color: #0f172a; font-weight: 900; text-transform: uppercase;">${nombre_limpio}</p>
                    
                    <p style="font-size: 2.2vh; margin: 0 0 0.5vh 0; color: #64748b; font-weight: bold;">NÚMERO DE DOCUMENTO:</p>
                    <p style="font-size: 3.5vh; margin: 0 0 3vh 0; color: #0f172a; font-weight: 900;">${dni_limpio}</p>
                    
                    <p style="font-size: 2.2vh; margin: 0 0 0.5vh 0; color: #64748b; font-weight: bold;">NÚMERO DE AFILIADO:</p>
                    <p style="font-size: 3.5vh; margin: 0 0 3vh 0; color: #0f172a; font-weight: 900;">${numAfiliado}</p>

                    <p style="font-size: 2.2vh; margin: 0 0 0.5vh 0; color: #64748b; font-weight: bold;">FUERZA / ORIGEN:</p>
                    <p style="font-size: 3.5vh; margin: 0; color: #0284c7; font-weight: 900; text-transform: uppercase;">${fuerzaTexto}</p>
                </div>
                <div style="display: flex; flex-direction: column; gap: 2vh;">
                    <button type="button" onclick="tConfirmarIdentidad('${dni_limpio}', '${nombreEscapadoJs}', '${codigoReal}', '${numAfiliado}', '${fuerzaEscapadaJs}', '${estadoAfiliado}')" style="padding: 2.5vh 2vw; font-size: 3vh; background: linear-gradient(180deg, #74acdf, #43a1d5); color: white; border: none; border-radius: 15px; font-weight: 900; cursor: pointer; box-shadow: 0 6px 0 #287bb5; text-transform: uppercase;">⚽ CONFIRMAR IDENTIDAD</button>
                    <button type="button" onclick="tReintentarDni()" style="padding: 1.8vh 2vw; font-size: 2.2vh; background: #f1f5f9; color: #64748b; border: 2px solid #cbd5e1; border-radius: 15px; font-weight: 800; cursor: pointer;">❌ CORREGIR / CAMBIAR DNI</button>
                </div>
            `;
            unsafeWindow.tMostrarResultado(htmlExito, false);
        }
    }, 200);

    // VIGILANTE DE HORARIOS (Consulta rápida cada 5 segundos)
    setInterval(() => {
        fetch('https://federicogonzalez.net/actis/get_servicios.php?t=' + new Date().getTime())
        .then(response => response.json())
        .then(servicios => {
            let bloqueoExistente = document.getElementById('bloqueo-horario-dinamico');
            
            if (servicios && servicios.cerrado) {
                let btnRolloHTML = '';
                if (servicios.sin_papel) {
                    btnRolloHTML = `
                        <button id="btn-cambio-rollo-dinamico" type="button" onclick="tMostrarCambioRollo()" style="margin-top: 4vh; padding: 2vh 3vw; font-size: 2.5vh; background: #0f172a; color: white; border: 2px solid #334155; border-radius: 12px; font-weight: 900; cursor: pointer; display: flex; align-items: center; gap: 1vw; box-shadow: 0 4px 15px rgba(0,0,0,0.5);">
                            🖨️ CAMBIAR ROLLO
                        </button>
                    `;
                }

                // Si el servidor dice cerrado, aseguramos que esté tapado
                if (!bloqueoExistente) {
                    let htmlCerrado = `
                        <div id="bloqueo-horario-dinamico" style="position: fixed; top:0; left:0; width: 100vw; height: 100vh; background: rgba(15,23,42,0.95); backdrop-filter: blur(15px); z-index: 9999999; display: flex; flex-direction: column; justify-content: center; align-items: center; text-align: center; color: white;">
                            <img src="https://federicogonzalez.net/actis/img/osfa_blanco.png" style="height: 15vh; margin-bottom: 3vh; filter: grayscale(1) brightness(2);">
                            <h1 style="font-size: 6vh; color: #ef4444; font-weight: 900; margin-bottom: 1vh;">TÓTEM CERRADO</h1>
                            <p id="bloqueo-mensaje" style="font-size: 4vh; font-weight: 800; max-width: 80%; line-height: 1.4;">${servicios.mensaje}</p>
                            ${btnRolloHTML}
                        </div>
                    `;
                    document.body.insertAdjacentHTML('beforeend', htmlCerrado);
                } else {
                    // Si ya está tapado, actualizamos el texto
                    let msgElem = document.getElementById('bloqueo-mensaje');
                    if(msgElem) msgElem.innerHTML = servicios.mensaje;
                    
                    let btn = document.getElementById('btn-cambio-rollo-dinamico');
                    if (servicios.sin_papel && !btn) {
                        bloqueoExistente.insertAdjacentHTML('beforeend', btnRolloHTML);
                    } else if (!servicios.sin_papel && btn) {
                        btn.remove();
                    }
                }
            } else {
                // Si el sistema responde que está ABIERTO
                if (bloqueoExistente) {
                    bloqueoExistente.remove(); // Se elimina el cartel rojo en vivo
                    
                    // Si la PC arrancó estando cerrada, la UI no existe y es obligatorio el reload
                    if (datosServicios.length === 0) {
                        window.location.replace('https://validador.iosfa.gob.ar/ValidadorDni');
                    } else {
                        // Si el tótem ya estaba andando, lo reseteamos en vivo sin recargar nada
                        modoActual = '';
                        if (typeof unsafeWindow.tOcultarResultado === 'function') unsafeWindow.tOcultarResultado();
                        let salva = document.getElementById('salvapantallas-totem');
                        if (salva) salva.classList.remove('pantalla-oculta');
                    }
                }
            }
        }).catch(err => console.error('Error vigilante de horarios:', err));
    }, 5000);

    // TELEMETRÍA EN VIVO (ESPEJO DE DATOS AL MONITOR)
    setInterval(() => {
        let inputDni = document.querySelector('[id$="txtDNI_I"]');
        let estado = {
            modo: typeof modoActual !== 'undefined' ? modoActual : '',
            dni_input: inputDni ? inputDni.value : '',
            modal_activo: document.getElementById('modal-resultado-kiosco') ? document.getElementById('modal-resultado-kiosco').classList.contains('activo') : false,
            ayuda_activa: document.getElementById('modal-ayuda') ? document.getElementById('modal-ayuda').classList.contains('activo') : false,
            salvapantallas: document.getElementById('salvapantallas-totem') ? !document.getElementById('salvapantallas-totem').classList.contains('pantalla-oculta') : false,
            servicio_seleccionado: typeof servicioSeleccionado !== 'undefined' ? servicioSeleccionado : '',
            html_modal: document.getElementById('resultado-contenido') ? document.getElementById('resultado-contenido').innerHTML : '',
            ultima_accion: typeof unsafeWindow.ultimaAccionKiosco !== 'undefined' ? unsafeWindow.ultimaAccionKiosco : 'Esperando...'
        };
        
        if (typeof GM_xmlhttpRequest !== 'undefined') {
            GM_xmlhttpRequest({
                method: "POST",
                url: "https://federicogonzalez.net/actis/sync_estado.php",
                headers: { "Content-Type": "application/json" },
                data: JSON.stringify(estado)
            });
        }
    }, 1000);

})();
