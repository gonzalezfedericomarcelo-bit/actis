<?php
session_start();
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/javascript; charset=utf-8");
require_once 'includes/conexion.php';

$nombre_usuario = isset($_SESSION['nombre']) ? htmlspecialchars($_SESSION['nombre']) : 'Usuario';
$inicial_usuario = strtoupper(substr($nombre_usuario, 0, 1));
$rol_usuario = isset($_SESSION['rol_nombre']) ? htmlspecialchars($_SESSION['rol_nombre']) : 'Operador';

// Configuracion de Totem para UI Ventanilla
$q_mostrar = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'mostrar_header_footer'");
$mostrar_hf_ven = ($q_mostrar && $q_mostrar->num_rows > 0) ? (int)$q_mostrar->fetch_assoc()['estado'] : 1;

$pin_rollo = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'pin_rollo_ventanilla'")->fetch_assoc()['estado'] ?? '77777';
$pin_demanda_espontanea_ventanilla = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'pin_demanda_espontanea_ventanilla'")->fetch_assoc()['estado'] ?? '77777';
$ventanilla_demanda_esp_hab = (int)($conexion->query("SELECT estado FROM totem_config WHERE tipo = 'ventanilla_demanda_espontanea_habilitada'")->fetch_assoc()['estado'] ?? '1');

// Verificacion automatica de papel para Ventanilla
$res_cap_ven = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'capacidad_rollo_ventanilla'");
$capacidad_ven = ($res_cap_ven && $res_cap_ven->num_rows > 0) ? (int)$res_cap_ven->fetch_assoc()['estado'] : 120;

$res_imp_ven = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'tickets_impresos_ventanilla'");
$impresos_ven = ($res_imp_ven && $res_imp_ven->num_rows > 0) ? (int)$res_imp_ven->fetch_assoc()['estado'] : 0;

$res_bloq_ven = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'bloqueo_min_papel_ventanilla'");
$bloqueo_ven = ($res_bloq_ven && $res_bloq_ven->num_rows > 0) ? (int)$res_bloq_ven->fetch_assoc()['estado'] : 5;

$sin_papel_ven = (($capacidad_ven - $impresos_ven) <= $bloqueo_ven) ? 1 : 0;
// Cargamos los servicios directamente desde la base de datos para la API local
$servicios_json = "[]";
$q_servicios = $conexion->query("SELECT nombre, imprime_numero FROM servicios ORDER BY nombre ASC");
if($q_servicios) {
    $servicios_array = [];
    while($r_srv = $q_servicios->fetch_assoc()) {
        $servicios_array[] = [
            'nombre' => $r_srv['nombre'],
            'imprime_numero' => (int)$r_srv['imprime_numero']
        ];
    }
    $servicios_json = json_encode($servicios_array, JSON_UNESCAPED_UNICODE);
}
?>
// ==UserScript==
// @name         ACTIS Core - Validador Ventanilla Total (Lógica)
// @namespace    http://tampermonkey.net/
// @version      10.0
// @match        *://validador.iosfa.gob.ar/*
// @grant        GM_xmlhttpRequest
// @connect      federicogonzalez.net
// ==/UserScript==

(function() {
    'use strict';
    
    const currentUrl = window.location.href.toLowerCase();
    if (!currentUrl.includes('validadordni') && !currentUrl.includes('login') && !currentUrl.includes('afiliaciones/menu')) {
        window.location.href = 'https://validador.iosfa.gob.ar/ValidadorDni';
        return;
    }
    if (currentUrl.includes('login') || currentUrl.includes('afiliaciones/menu')) {
        return; 
    }

    window.datosServiciosAPI = JSON.parse(`<?php echo $servicios_json; ?>`);
    window.ventanillaSinPapel = <?php echo $sin_papel_ven; ?>;

    // INTERCEPTOR DE ERRORES DEVEXPRESS
    const alertaOriginal = window.alert;
    window.alert = function(mensaje) {
        if (mensaje && typeof mensaje === 'string' && (mensaje.toUpperCase().includes('CALLBACK REQUEST') || mensaje.toUpperCase().includes('INTERNAL SERVER ERROR'))) {
            window.location.href = 'https://validador.iosfa.gob.ar/ValidadorDni';
            return;
        }
        alertaOriginal(mensaje);
    };

    // 1. Destruir el viewport de IOSFA y forzar el de Actis
    document.querySelectorAll('meta[name="viewport"]').forEach(m => m.remove());
    const metaViewport = document.createElement('meta');
    metaViewport.name = "viewport";
    metaViewport.content = "width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no";
    document.head.appendChild(metaViewport);

    // 2. Fuentes e iconos
    const font = document.createElement('link'); font.rel = 'stylesheet'; font.href = 'https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap'; document.head.appendChild(font);
    const fa = document.createElement('link'); fa.rel = 'stylesheet'; fa.href = 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css'; document.head.appendChild(fa);

    const estilo = document.createElement('style');
    estilo.innerHTML = `
        html { font-size: 16px !important; height: 100% !important; }

        /* Ocultar basura nativa de IOSFA */
        nav.navbar, .dua-tbl, footer.footer-iosfa, #footer, .badge-alertas, #ctl00_UsuarioVtoAlertas1_userAlertas, .row h3:first-of-type { display: none !important; }
        .dxeHelpText_IOSFA, .safi-hint, #ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btnImprimir { display: none !important; }
        #ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout_BootstrapButton2 { display: none !important; }
        .dxpc-mainDiv, .dxpc-shadow, .modal-backdrop, .modal-dialog { display: none !important; opacity: 0 !important; pointer-events: none !important; }

        /* Estructura Base de ACTIS */
        body {
            margin: 0 !important; padding: 0 !important; padding-top: 70px !important;
            font-family: 'Poppins', sans-serif !important;
            background: url('https://images.unsplash.com/photo-1519494026892-80bbd2d6fd0d?q=80&w=2053&auto=format&fit=crop') no-repeat center center fixed !important;
            background-size: cover !important; display: flex !important; flex-direction: column !important; min-height: 100vh !important; -webkit-tap-highlight-color: transparent !important;
        }
        body::before { content: ""; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(241, 245, 249, 0.94); z-index: -1; }
        .main-content-ventanilla { padding: 15px !important; max-width: 1400px !important; margin: 0 auto !important; padding-bottom: 100px !important; flex: 1 0 auto !important; display: flex !important; flex-direction: column !important; align-items: center !important; width: 100% !important; box-sizing: border-box !important; }

        /* HEADER.PHP */
        .actis-navbar { position: fixed !important; top: 0 !important; left: 0 !important; width: 100% !important; height: 70px !important; background: rgba(255,255,255,0.95) !important; backdrop-filter: blur(10px) !important; border-bottom: 1px solid #e2e8f0 !important; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02) !important; display: flex !important; justify-content: space-between !important; align-items: center !important; padding: 0 20px !important; z-index: 900 !important; box-sizing: border-box !important; }
        .actis-brand { font-size: 1.3rem !important; font-weight: 900 !important; color: #0f172a !important; display: flex !important; align-items: center !important; gap: 8px !important; text-decoration: none !important; letter-spacing: -0.5px !important; }
        .actis-brand i { background: linear-gradient(135deg, #2563eb, #1d4ed8) !important; color: white !important; width: 32px !important; height: 32px !important; display: flex !important; justify-content: center !important; align-items: center !important; border-radius: 8px !important; font-size: 1rem !important; font-style: normal !important;}
        .topbar-tools { display: flex !important; align-items: center !important; gap: 12px !important; }
        .live-clock { background: #f1f5f9 !important; color: #475569 !important; padding: 6px 12px !important; border-radius: 8px !important; font-weight: 800 !important; font-size: 0.85rem !important; display: flex !important; align-items: center !important; gap: 6px !important; border: 1px solid #e2e8f0 !important; }
        .user-chip { display: flex !important; align-items: center !important; gap: 8px !important; background: #eff6ff !important; border: 1px solid #bfdbfe !important; padding: 6px 14px 6px 6px !important; border-radius: 30px !important; font-size: 0.85rem !important; font-weight: 700 !important; color: #1e293b !important; cursor: pointer !important; }
        .user-chip-avatar { width: 26px !important; height: 26px !important; background: #2563eb !important; color: white !important; border-radius: 50% !important; display: flex !important; justify-content: center !important; align-items: center !important; font-size: 0.8rem !important; }

        .actis-bottom-bar { display: flex !important; justify-content: space-around !important; align-items: center !important; position: fixed !important; bottom: 0 !important; left: 0 !important; width: 100% !important; height: 75px !important; background: rgba(255,255,255,0.98) !important; backdrop-filter: blur(10px) !important; border-top: 1px solid #e2e8f0 !important; box-shadow: 0 -4px 20px rgba(0,0,0,0.06) !important; z-index: 10005 !important; padding-bottom: env(safe-area-inset-bottom) !important; }
        .bottom-tab { display: flex !important; flex-direction: column !important; align-items: center !important; justify-content: center !important; gap: 4px !important; color: #94a3b8 !important; text-decoration: none !important; font-size: 0.7rem !important; font-weight: 800 !important; flex: 1 !important; height: 100% !important; border: none !important; background: transparent !important; cursor: pointer !important; transition: all 0.2s !important; }
        .bottom-tab i { font-size: 1.4rem !important; transition: transform 0.2s !important; display: block !important;}
        .bottom-tab.active { color: #2563eb !important; }
        .bottom-tab.active i { transform: translateY(-3px) !important; color: #2563eb !important; }

        .right-drawer-overlay { position: fixed !important; top: 0 !important; left: 0 !important; width: 100% !important; height: 100% !important; background: rgba(15,23,42,0.6) !important; backdrop-filter: blur(3px) !important; z-index: 2000 !important; opacity: 0 !important; visibility: hidden !important; transition: all 0.3s !important; }
        .right-drawer-overlay.active { opacity: 1 !important; visibility: visible !important; }
        .right-drawer { position: fixed !important; top: 0 !important; right: -320px !important; width: 280px !important; height: 100vh !important; background: #ffffff !important; box-shadow: -5px 0 25px rgba(0,0,0,0.15) !important; transition: right 0.3s cubic-bezier(0.4, 0, 0.2, 1) !important; z-index: 2001 !important; display: flex !important; flex-direction: column !important; }
        .right-drawer.active { right: 0 !important; }
        .drawer-header { padding: 25px 20px !important; background: #f8fafc !important; border-bottom: 1px solid #e2e8f0 !important; display: flex !important; flex-direction: column !important; gap: 5px !important; position: relative !important; text-align: left !important;}
        .btn-close-drawer { position: absolute !important; top: 20px !important; right: 20px !important; background: #fee2e2 !important; color: #dc2626 !important; border: none !important; width: 35px !important; height: 35px !important; border-radius: 10px !important; font-size: 1.2rem !important; cursor: pointer !important; display: flex !important; align-items: center !important; justify-content: center !important;}
        .drawer-avatar { width: 50px !important; height: 50px !important; background: linear-gradient(135deg, #3b82f6, #1d4ed8) !important; color: white !important; border-radius: 15px !important; display: flex !important; justify-content: center !important; align-items: center !important; font-size: 1.5rem !important; font-weight: 800 !important; margin-bottom: 10px !important; }
        .drawer-body { flex: 1 !important; overflow-y: auto !important; padding: 20px !important; }
        .drawer-group { margin-bottom: 25px !important; }
        .drawer-title { font-size: 0.75rem !important; font-weight: 900 !important; color: #94a3b8 !important; text-transform: uppercase !important; margin-bottom: 10px !important; letter-spacing: 0.5px !important; text-align: left !important; display: block !important;}
        .drawer-link { display: flex !important; align-items: center !important; gap: 12px !important; padding: 12px 14px !important; text-decoration: none !important; color: #1e293b !important; font-weight: 700 !important; font-size: 0.95rem !important; border-radius: 12px !important; transition: background 0.2s !important; margin-bottom: 5px !important; }
        .drawer-link i { width: 24px !important; text-align: center !important; color: #64748b !important; font-size: 1.1rem !important; display: block !important;}
        .drawer-link:active { background: #f1f5f9 !important; transform: scale(0.98) !important; }
        .drawer-link.logout { color: #dc2626 !important; background: #fef2f2 !important; margin-top: 10px !important; }
        .drawer-link.logout i { color: #dc2626 !important; }

        .actis-menu-desktop { display: none !important; }
        @media (min-width: 992px) {
            .actis-bottom-bar { display: none !important; }
            .actis-menu-desktop { display: flex !important; gap: 8px !important; align-items: center !important; }
            .nav-link-desk { padding: 10px 15px !important; color: #475569 !important; font-weight: 700 !important; font-size: 0.9rem !important; text-decoration: none !important; border-radius: 10px !important; display: flex !important; align-items: center !important; gap: 8px !important; }
            .nav-link-desk:hover, .nav-link-desk.active { background: #f1f5f9 !important; color: #2563eb !important; }
            .topbar-tools { display: none !important; }
        }

        /* FOOTER.PHP */
        .actis-footer-complejo { background: #0f172a !important; color: #cbd5e1 !important; font-family: 'Poppins', sans-serif !important; margin-top: auto !important; border-top: 4px solid #2563eb !important; position: relative !important; z-index: 10 !important; display: block !important; width: 100% !important; text-align: left !important;}
        .afc-container { display: grid !important; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)) !important; gap: 40px !important; max-width: 1400px !important; margin: 0 auto !important; padding: 60px 30px !important; width: 100% !important; box-sizing: border-box !important; align-items: start !important; justify-content: center !important;}
        .afc-columna { display: flex !important; flex-direction: column !important; align-items: flex-start !important; justify-content: flex-start !important; width: 100% !important;}
        .afc-columna h3 { color: #ffffff !important; font-size: 1.8rem !important; margin: 0 0 20px 0 !important; display: flex !important; align-items: center !important; gap: 12px !important; font-weight: 900 !important; letter-spacing: -0.5px !important; line-height: 1.2 !important; }
        .afc-columna h3 i { background: linear-gradient(135deg, #2563eb, #1d4ed8) !important; color: white !important; width: 40px !important; height: 40px !important; display: flex !important; justify-content: center !important; align-items: center !important; border-radius: 10px !important; font-size: 1.2rem !important; font-style: normal !important;}
        .afc-branding p { font-size: 0.9rem !important; line-height: 1.7 !important; color: #94a3b8 !important; margin-bottom: 25px !important; text-align: left !important;}

        .afc-social { display: flex !important; gap: 12px !important; }
        .afc-social a { width: 38px !important; height: 38px !important; background: rgba(255,255,255,0.05) !important; color: #cbd5e1 !important; border-radius: 8px !important; display: flex !important; align-items: center !important; justify-content: center !important; text-decoration: none !important; transition: all 0.3s ease !important; font-size: 1.1rem !important; border: 1px solid rgba(255,255,255,0.1) !important; }
        .afc-social a:hover { background: #2563eb !important; color: white !important; transform: translateY(-3px) !important; border-color: #2563eb !important; box-shadow: 0 5px 15px rgba(37,99,235,0.4) !important; }

        .afc-columna h4 { color: #ffffff !important; font-size: 1.1rem !important; margin: 0 0 25px 0 !important; font-weight: 700 !important; text-transform: uppercase !important; letter-spacing: 1px !important; position: relative !important; padding-bottom: 12px !important; display: block !important;}
        .afc-columna h4::after { content: '' !important; position: absolute !important; left: 0 !important; bottom: 0 !important; width: 40px !important; height: 3px !important; background: #2563eb !important; border-radius: 2px !important; display: block !important;}

        .afc-links ul { list-style: none !important; padding: 0 !important; margin: 0 !important; display: block !important; width: 100% !important;}
        .afc-links ul li { margin-bottom: 15px !important; margin-top: 0 !important; padding: 0 !important; display: block !important; width: 100% !important; text-align: left !important; line-height: 1 !important; }
        .afc-links ul li a { color: #94a3b8 !important; text-decoration: none !important; font-size: 0.95rem !important; transition: all 0.2s !important; display: flex !important; align-items: center !important; gap: 10px !important; font-weight: 500 !important; padding: 0 !important; margin: 0 !important; line-height: 1.2 !important; }
        .afc-links ul li a i { font-size: 0.75rem !important; color: #3b82f6 !important; transition: transform 0.2s !important; display: block !important;}
        .afc-links ul li a:hover { color: #ffffff !important; padding-left: 6px !important; }
        .afc-links ul li a:hover i { transform: translateX(4px) !important; }

        .afc-contacto .afc-info-item { display: flex !important; align-items: flex-start !important; gap: 15px !important; margin-bottom: 20px !important; color: #94a3b8 !important; font-size: 0.95rem !important; line-height: 1.5 !important; width: 100% !important;}
        .afc-contacto .afc-info-item i { color: #3b82f6 !important; font-size: 1.2rem !important; margin-top: 2px !important; display: block !important;}
        .afc-contacto .afc-info-item span { font-weight: 500 !important; display: block !important; text-align: left !important;}

        .afc-estado { margin-top: 30px !important; background: rgba(34, 197, 94, 0.1) !important; border: 1px solid rgba(34, 197, 94, 0.2) !important; padding: 12px 15px !important; border-radius: 8px !important; display: inline-flex !important; align-items: center !important; gap: 10px !important; color: #4ade80 !important; font-size: 0.85rem !important; font-weight: 700 !important; text-transform: uppercase !important; letter-spacing: 0.5px !important; }
        .afc-dot { width: 10px !important; height: 10px !important; background: #22c55e !important; border-radius: 50% !important; box-shadow: 0 0 5px #22c55e !important; }
        .afc-bottom { background: #020617 !important; padding: 25px 20px !important; text-align: center !important; border-top: 1px solid rgba(255,255,255,0.05) !important; display: block !important;}
        .afc-bottom p { margin: 0 !important; font-size: 0.85rem !important; color: #64748b !important; font-weight: 500 !important; }

        @media (max-width: 992px) {
            .actis-footer-complejo { margin-bottom: 75px !important; }
            .afc-container { padding: 40px 20px !important; gap: 30px !important; grid-template-columns: 1fr !important;}
        }

        @import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800;900&display=swap');
        
        /* Ocultar interfaz nativa de IOSFA y DevExpress por completo (la ocultamos pero sigue en el DOM) */
        form#form1 { opacity: 0 !important; pointer-events: none !important; position: absolute !important; left: -9999px !important; z-index: -1 !important;}
        body { margin: 0 !important; padding: 0 !important; overflow: hidden !important; background: #000 !important; }
        
        /* Nueva Interfaz Totalmente Custom */
        #actis-super-ui {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: #030712;
            background-image: 
                radial-gradient(at 0% 0%, rgba(56, 189, 248, 0.1) 0, transparent 50%), 
                radial-gradient(at 100% 100%, rgba(139, 92, 246, 0.1) 0, transparent 50%),
                linear-gradient(135deg, #020617 0%, #0f172a 100%);
            z-index: 10000;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Outfit', sans-serif;
            color: white;
            overflow: hidden;
        }

        /* Partículas animadas de fondo */
        .particles-bg { position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; opacity: 0.4; }

        /* Contenedor principal dividido */
        .actis-split-container {
            width: 90%; max-width: 1300px; height: 80vh; max-height: 850px;
            background: rgba(15, 23, 42, 0.95);
            border: 1px solid rgba(255,255,255,0.05);
            border-radius: 40px;
            display: flex; flex-direction: row;
            box-shadow: 0 10px 30px rgba(0,0,0,0.8);
            overflow: hidden; position: relative;
        }

        /* Panel Izquierdo (Hero Information) */
        .actis-left-panel {
            flex: 1.2;
            padding: 60px;
            display: flex; flex-direction: column; justify-content: center;
            background: linear-gradient(145deg, rgba(2,132,199,0.1) 0%, rgba(124,58,237,0.05) 100%);
            border-right: 1px solid rgba(255,255,255,0.05);
            position: relative;
        }
        .actis-left-panel h1 { font-size: 3.5rem; font-weight: 900; line-height: 1.1; margin: 0 0 20px 0; background: linear-gradient(to right, #38bdf8, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .actis-left-panel p.desc { font-size: 1.2rem; color: #94a3b8; font-weight: 300; line-height: 1.6; margin-bottom: 40px; max-width: 90%; }
        
        .feature-item { display: flex; align-items: center; gap: 20px; margin-bottom: 25px; }
        .feature-icon { width: 60px; height: 60px; border-radius: 16px; background: rgba(56, 189, 248, 0.1); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: #38bdf8; border: 1px solid rgba(56,189,248,0.2); }
        .feature-text h4 { margin: 0 0 5px 0; font-size: 1.1rem; font-weight: 700; color: #e2e8f0; }
        .feature-text p { margin: 0; font-size: 0.9rem; color: #64748b; font-weight: 400; }

        /* Panel Derecho (Interacción del Operador) */
        .actis-right-panel {
            flex: 1; padding: 60px;
            display: flex; flex-direction: column; align-items: center; justify-content: center;
            position: relative;
        }
        
        .custom-form-wrapper { width: 100%; max-width: 450px; text-align: center; }
        .custom-form-wrapper i.main-icon { font-size: 4rem; color: #e2e8f0; margin-bottom: 25px; display: block; text-shadow: 0 10px 20px rgba(0,0,0,0.5); }
        .custom-form-wrapper h2 { font-size: 2rem; font-weight: 800; margin: 0 0 10px 0; color: #ffffff; letter-spacing: -0.5px; }
        .custom-form-wrapper p.subtitle { color: #94a3b8; font-size: 1.1rem; margin-bottom: 40px; }

        .custom-input-group { position: relative; width: 100%; margin-bottom: 30px; }
        .custom-input-group input {
            width: 100%; height: 90px;
            background: rgba(0,0,0,0.4);
            border: 2px solid rgba(255,255,255,0.1);
            border-radius: 24px;
            padding: 0 25px;
            font-size: 2.5rem; font-weight: 900; color: #ffffff; text-align: center;
            font-family: 'Outfit', sans-serif; letter-spacing: 5px;
            transition: all 0.3s ease; box-shadow: inset 0 5px 15px rgba(0,0,0,0.5);
            box-sizing: border-box; outline: none;
            -moz-appearance: textfield;
        }
        .custom-input-group input::-webkit-outer-spin-button,
        .custom-input-group input::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }
        .custom-input-group input:focus { border-color: #38bdf8; background: rgba(0,0,0,0.6); box-shadow: 0 0 30px rgba(56, 189, 248, 0.15), inset 0 5px 15px rgba(0,0,0,0.5); }
        .custom-input-group input::placeholder { font-size: 1.2rem; letter-spacing: 1px; color: #475569; font-weight: 600; text-transform: uppercase; }

        .custom-btn-submit {
            width: 100%; height: 75px;
            border-radius: 20px; border: none;
            background: linear-gradient(135deg, #0284c7 0%, #2563eb 100%);
            color: white; font-size: 1.2rem; font-weight: 800; text-transform: uppercase; letter-spacing: 2px; font-family: 'Outfit', sans-serif;
            cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 15px;
            box-shadow: 0 15px 30px -10px rgba(37, 99, 235, 0.5), inset 0 1px 0 rgba(255,255,255,0.2);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .custom-btn-submit:hover { transform: translateY(-5px); box-shadow: 0 20px 40px -10px rgba(37, 99, 235, 0.7); background: linear-gradient(135deg, #0369a1 0%, #1d4ed8 100%); }
        .custom-btn-submit:active { transform: translateY(2px); }

        /* Loader Overlay de la nueva UI */
        .custom-loader-overlay {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15,23,42,0.95);
            display: none; flex-direction: column; align-items: center; justify-content: center;
            border-radius: 40px; z-index: 50; color: #38bdf8;
        }
        .custom-loader-overlay i { font-size: 4rem; animation: spin 1.5s linear infinite; margin-bottom: 20px; }
        @keyframes spin { 100% { transform: rotate(360deg); } }

        /* ========================================================================= */
        
        /* Ocultamos el resultado nativo para usar Popup Fede Actis*/
        #ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_divDatosAfiliado { position: absolute !important; left: -9999px !important; opacity: 0 !important; pointer-events: none !important; }
        
        /* ESTILOS DEL MODAL EMERGENTE REDISEÑADO Y SUBMODAL */
        .modal-ventanilla-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.95); z-index: 99999; display: none; justify-content: center; align-items: center; padding: 20px; box-sizing: border-box; opacity: 0; transition: opacity 0.2s ease; }
        .modal-ventanilla-overlay[style*="display: flex"] { opacity: 1; }
        .modal-ventanilla-box { background: white; width: 100%; max-width: 1000px; border-radius: 32px; padding: 0; box-shadow: 0 10px 30px rgba(0,0,0,0.4); display: flex; flex-direction: row; overflow: hidden; border: 1px solid rgba(255,255,255,0.2); font-family: 'Poppins', sans-serif; position: relative;}
        
        /* Rediseño premium de la parte izquierda */
        .mv-left { flex: 1.2; padding: 50px; background: linear-gradient(145deg, #0f172a 0%, #1e293b 100%); display: flex; flex-direction: column; justify-content: flex-start; color: #f8fafc; position: relative;}
        .mv-left::after { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: radial-gradient(circle at top right, rgba(56, 189, 248, 0.1), transparent 50%); pointer-events: none; }
        .mv-right { width: 450px; padding: 50px; background: #f8fafc; display: flex; flex-direction: column; justify-content: center; }
        
        .mv-title { font-size: 1.8rem; font-weight: 900; color: #ffffff; margin-top: 0; padding-bottom: 20px; margin-bottom: 25px; text-align: left; display: flex; align-items: center; gap: 15px; letter-spacing: -0.5px; border-bottom: 1px solid rgba(255,255,255,0.1); position: relative;}
        .mv-title i { background: rgba(56, 189, 248, 0.15); color: #38bdf8; padding: 12px; border-radius: 14px; font-size: 1.4rem; }
        
        .mv-data { text-align: left; font-size: 1.15rem; color: #cbd5e1; line-height: 1.6; font-weight: 500; display: flex; flex-direction: column; gap: 12px;}
        
        /* Forzamos el rediseño de las etiquetas P y H3 que trae IOSFA, convirtiéndolas en Cards */
        .mv-data p { color: #94a3b8 !important; margin: 0 !important; font-size: 1.05rem !important; background: rgba(255,255,255,0.03); padding: 12px 18px; border-radius: 12px; border: 1px solid rgba(255,255,255,0.05); display: flex; align-items: center; justify-content: space-between; }
        .mv-data p strong { color: #f8fafc; font-weight: 700; letter-spacing: 0.5px; }
        .mv-data h3 { color: #ffffff !important; margin: 0 !important; font-size: 1.6rem !important; background: rgba(56, 189, 248, 0.1); padding: 15px 20px; border-radius: 16px; border: 1px solid rgba(56, 189, 248, 0.2); font-weight: 800; letter-spacing: -0.5px;}
        .mv-data h3[style*="color: green"], .mv-data h3[style*="color: #4ade80"], .mv-data h3[style*="color: #10b981"] { background: rgba(34, 197, 94, 0.1) !important; color: #4ade80 !important; border: 1px solid rgba(34, 197, 94, 0.2) !important; font-size: 1.4rem !important; margin-top: 10px !important; text-align: center; }
        .mv-data h3[style*="color: #ef4444"] { background: rgba(239, 68, 68, 0.1) !important; color: #f87171 !important; border: 1px solid rgba(239, 68, 68, 0.2) !important; font-size: 1.4rem !important; margin-top: 10px !important; text-align: center; }
        
        .mv-data span.red1 { color: #fee2e2; font-weight: 900; font-size: 1.1rem; display: flex; align-items: center; justify-content: center; margin: 10px 0; background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%); padding: 12px 20px; border-radius: 14px; box-shadow: 0 10px 15px -3px rgba(239, 68, 68, 0.3); text-transform: uppercase; letter-spacing: 1px; }
        
        .mv-buttons { display: flex; flex-direction: column; gap: 20px; }
        .mv-btn { padding: 16px; font-size: 1.15rem; font-weight: 800; border: none; border-radius: 16px; cursor: pointer; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); color: white; display: flex; justify-content: center; align-items: center; gap: 12px; text-transform: uppercase; font-family: 'Poppins', sans-serif; letter-spacing: 1px; position: relative; overflow: hidden;}
        .mv-btn-val { background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); box-shadow: 0 10px 20px -5px rgba(37,99,235,0.4); }
        .mv-btn-val:hover:not(:disabled) { transform: translateY(-3px); box-shadow: 0 15px 25px -5px rgba(37,99,235,0.5); }
        .mv-btn-val:active:not(:disabled) { transform: translateY(1px); box-shadow: 0 5px 10px -5px rgba(37,99,235,0.4); }
        
        .mv-btn-asi { background: linear-gradient(135deg, #10b981 0%, #059669 100%); box-shadow: 0 10px 20px -5px rgba(16,185,129,0.4); }
        .mv-btn-asi:hover:not(:disabled) { transform: translateY(-3px); box-shadow: 0 15px 25px -5px rgba(16,185,129,0.5); }
        .mv-btn-asi:active:not(:disabled) { transform: translateY(1px); box-shadow: 0 5px 10px -5px rgba(16,185,129,0.4); }
        
        .mv-btn-cancel { background: #cbd5e1; color: #475569; margin-top: 15px; font-weight: 700; box-shadow: none; }
        .mv-btn-cancel:hover { background: #e2e8f0; color: #1e293b; }
        
        .mv-btn-selector { width: 100%; padding: 18px 20px; border-radius: 14px; border: 2px solid #e2e8f0; background: #ffffff; font-family: 'Poppins', sans-serif; font-size: 1.1rem; font-weight: 700; color: #475569; margin-bottom: 20px; cursor: pointer; display: flex; justify-content: space-between; align-items: center; transition: all 0.3s; text-align: left; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        .mv-btn-selector:hover { border-color: #94a3b8; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); transform: translateY(-2px); }
        .mv-btn-selector i { color: #94a3b8; font-size: 1.2rem; }

        /* Submodal de Servicios Ampliado y con buscador */
        .submodal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 100000; display: none; justify-content: center; align-items: center; padding: 20px; box-sizing: border-box; }
        .submodal-box { background: white; padding: 30px; border-radius: 20px; width: 92%; max-width: 1050px; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 10px 20px rgba(0,0,0,0.4); border: 2px solid #cbd5e1; }
        .sm-title { font-size: 1.6rem; font-weight: 900; color: #0f172a; margin-top: 0; margin-bottom: 15px; text-align: center; text-transform: uppercase; }
        .sm-listado { overflow-y: auto; display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px; padding-right: 5px; margin-bottom: 10px; flex-grow: 1; min-height: 150px; max-height: 45vh; }
        .sm-item { padding: 8px 12px; background: #f8fafc; border: 2px solid #e2e8f0; border-radius: 8px; font-family: 'Poppins', sans-serif; font-weight: 700; font-size: 0.85rem; color: #334155; cursor: pointer; text-align: left; transition: all 0.2s; display: flex; justify-content: space-between; align-items: center; }
        .sm-item i { color: #94a3b8; font-size: 0.9rem; transition: all 0.2s; }
        .sm-item:hover { background: #eff6ff; border-color: #3b82f6; color: #1d4ed8; transform: translateX(5px); }
        .sm-item:hover i { color: #3b82f6; }

        /* Contenedor Alfabeto */
        .sm-abc-container { display: flex; flex-wrap: wrap; gap: 5px; margin-bottom: 15px; justify-content: center; width: 100%; }
        .sm-abc-btn { padding: 6px 12px; background: #f1f5f9; border: 1px solid #cbd5e1; border-radius: 8px; font-weight: 800; cursor: pointer; font-size: 0.85rem; font-family: 'Poppins', sans-serif; transition: all 0.15s; }
        .sm-abc-btn:hover { background: #e2e8f0; }
        .sm-abc-btn.active { background: #2563eb; color: white; border-color: #2563eb; box-shadow: 0 4px 10px rgba(37,99,235,0.2); }

        /* Deshabilitar botón de impresión */
        .mv-btn:disabled { opacity: 0.4; cursor: not-allowed; pointer-events: none; box-shadow: none !important; transform: none !important; }

        /* ADAPTACIÓN CELULAR DEL MODAL */
        @media (max-width: 768px) {
            .modal-ventanilla-box { flex-direction: column; max-height: 90vh; overflow-y: auto; border-radius: 16px; }
            .mv-left { padding: 20px; border-right: none; border-bottom: 2px solid rgba(255,255,255,0.1); }
            .mv-right { width: 100%; padding: 20px; box-sizing: border-box; }
            .mv-title { font-size: 1.3rem; margin-bottom: 15px; }
            .mv-data h3 { font-size: 1.2rem !important; line-height: 1.3 !important; }
            .mv-data h3[style*="color: green"] { font-size: 1.4rem !important; padding: 5px 0 !important; margin-top: 10px !important; }
            .mv-btn { font-size: 1rem; padding: 12px; }
            .submodal-box { padding: 20px; max-height: 90vh; }
        }
    `;
    document.head.appendChild(estilo);

    <?php if($mostrar_hf_ven === 1): ?>
    const headerHTML = `
        <header class="actis-navbar">
            <a href="https://federicogonzalez.net/actis/dashboard.php" class="actis-brand">
                <i><i class="fa-solid fa-heart-pulse"></i></i> ACTIS Core
            </a>

            <div class="topbar-tools">
                <div class="live-clock"><i class="fa-regular fa-clock" style="color:#2563eb;"></i> <span id="clock-display">--:--</span></div>
                <button onclick="window.abrirModalRollo()" style="background:#059669; color:white; border:none; padding: 8px 15px; border-radius: 8px; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 6px rgba(5, 150, 105, 0.2); margin-left: 15px;">
                    <i class="fa-solid fa-print"></i> Cambiar Rollo
                </button>
            </div>
        </header>

        <nav class="actis-bottom-bar">
            <a href="https://validador.iosfa.gob.ar/ValidadorDni" class="bottom-tab active">
                <i class="fa-solid fa-file-signature"></i>
                <span>Validar</span>
            </a>
            <a href="javascript:void(0)" class="bottom-tab" onclick="window.abrirModalRollo()" style="color:#059669;">
                <i class="fa-solid fa-print"></i>
                <span>Cambiar Rollo</span>
            </a>
        </nav>
    `;

    const footerHTML = `
        <footer class="actis-footer-complejo">
            <div class="afc-container">
                <div class="afc-columna afc-branding">
                    <h3><i><i class="fa-solid fa-heart-pulse"></i></i> ACTIS Core</h3>
                    <p>Plataforma de Gestión Integral. Optimizando la atención médica de nuestros afiliados con tecnología de vanguardia.</p>
                </div>
                <div class="afc-columna afc-links">
                    <h4>Gestión de Ventanilla</h4>
                    <ul>
                        <li><a href="javascript:void(0)" onclick="window.abrirModalRollo()"><i class="fa-solid fa-print"></i> Cambié Rollo (Ventanilla)</a></li>
                    </ul>
                </div>
            </div>
            <div class="afc-bottom">
                <p>&copy; ${new Date().getFullYear()} Policlínica General ACTIS. Todos los derechos reservados.</p>
            </div>
        </footer>
    `;
    <?php endif; ?>

    const modalHTML = `
        <div class="modal-ventanilla-overlay" id="mv-overlay">
            <div class="modal-ventanilla-box">
                <div class="mv-left">
                    <h2 class="mv-title"><i class="fa-solid fa-address-card" style="color: #38bdf8; font-size: 2rem;"></i> DATOS DEL AFILIADO</h2>
                    <div class="mv-data" id="mv-content"></div>
                </div>
                <div class="mv-right">
                    <h3 style="font-size: 1.4rem; color: #0f172a; margin-bottom: 25px; font-weight: 900; text-align: center; text-transform: uppercase; letter-spacing: -0.5px;">&iquest;Qu&eacute; desea hacer?</h3>
                    <div class="mv-buttons">
                        <div style="background: #ffffff; padding: 25px; border-radius: 20px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
                            <label style="display: flex; align-items: center; gap: 8px; font-size: 1rem; font-weight: 800; color: #1e40af; margin-bottom: 12px; text-align: left;"><i class="fa-solid fa-check-to-slot" style="background:#eff6ff; padding:8px; border-radius:8px;"></i> Destino de Validaci&oacute;n:</label>
                            <button id="mv-btn-abrir-servicios-val" class="mv-btn-selector">
                                <span id="mv-servicio-val-texto" style="font-size: 0.95rem; color: #64748b;">Haga clic aqu&iacute; para elegir...</span>
                                <i class="fa-solid fa-chevron-down"></i>
                            </button>
                            <input type="hidden" id="mv-servicio-val-oculto" value="">
                            <button class="mv-btn mv-btn-val" id="mv-print-val" style="width: 100%;" disabled><i class="fa-solid fa-print"></i> Imprimir Validaci&oacute;n</button>
                        </div>
                        <div style="background: #ffffff; padding: 25px; border-radius: 20px; box-shadow: 0 10px 25px -5px rgba(0,0,0,0.05); border: 1px solid #e2e8f0; margin-top: 10px;">
                            <label style="display: flex; align-items: center; gap: 8px; font-size: 1rem; font-weight: 800; color: #059669; margin-bottom: 12px; text-align: left;"><i class="fa-solid fa-hospital-user" style="background:#ecfdf5; padding:8px; border-radius:8px;"></i> Destino de Asistencia:</label>
                            <button id="mv-btn-abrir-servicios" class="mv-btn-selector">
                                <span id="mv-servicio-texto" style="font-size: 0.95rem; color: #64748b;">Haga clic aqu&iacute; para elegir...</span>
                                <i class="fa-solid fa-chevron-down"></i>
                            </button>
                            <input type="hidden" id="mv-servicio-oculto" value="">
                            <button class="mv-btn mv-btn-asi" id="mv-print-asi" style="width: 100%;" disabled><i class="fa-solid fa-notes-medical"></i> Ticket Asistencia</button>
                        </div>
                        <button class="mv-btn mv-btn-cancel" onclick="window.cerrarModalVentanilla()"><i class="fa-solid fa-arrow-right-from-bracket"></i> Cancelar / Siguiente</button>
                    </div>
                </div>
            </div>
        </div>
        <div class="submodal-overlay" id="submodal-servicios">
            <div class="submodal-box">
                <h3 class="sm-title" id="sm-modal-dinamico-titulo">Seleccione el Destino</h3>
                <input type="text" id="sm-buscador" placeholder="Escribe aqui para buscar el servicio medico..." style="width: 100%; padding: 14px 20px; border-radius: 12px; border: 2px solid #cbd5e1; font-family: 'Poppins', sans-serif; font-size: 1.1rem; font-weight: 600; margin-bottom: 15px; box-sizing: border-box; outline: none; background: #fafafa;">
                <div class="sm-abc-container" id="sm-abc-contenedor"></div>
                <div class="sm-listado" id="sm-listado-contenedor"></div>
                <button class="mv-btn mv-btn-cancel" onclick="document.getElementById('submodal-servicios').style.display='none'" style="width: 100%; margin-top: 10px;"><i class="fa-solid fa-xmark"></i> Volver</button>
            </div>
        </div>
        <div class="modal-ventanilla-overlay" id="mv-rollo-overlay" style="z-index: 9999999;">
            <div class="modal-ventanilla-box" style="max-width: 400px; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 20px;">
                <i class="fa-solid fa-print" style="font-size: 3rem; color: #059669; margin-bottom: 15px;"></i>
                <h3 style="font-size: 1.2rem; color: #1e293b; margin-bottom: 15px; font-weight: 900;">&iquest;Cambio de Rollo?</h3>
                <p style="color: #64748b; margin-bottom: 20px; font-size: 0.95rem;">&iquest;Confirma que ha reemplazado el rollo de papel en la VENTANILLA?</p>
                <input type="tel" id="mv-rollo-pin" autocomplete="off" placeholder="PIN de Seguridad" style="-webkit-text-security: disc; width: 100%; padding: 12px; margin-bottom: 20px; text-align: center; font-size: 1.2rem; font-weight: bold; border-radius: 8px; border: 2px solid #cbd5e1; outline: none; box-sizing: border-box;" maxlength="6">
                <div style="display: flex; gap: 10px; justify-content: center; width: 100%;">
                    <button onclick="window.cerrarModalRollo()" style="flex: 1; padding: 12px 20px; border-radius: 8px; border: 1px solid #cbd5e1; background: white; color: #475569; font-weight: bold; cursor: pointer;">Cancelar</button>
                    <button onclick="window.confirmarResetRollo()" style="flex: 1; padding: 12px 20px; border-radius: 8px; border: none; background: #059669; color: white; font-weight: bold; cursor: pointer;">Sí, Confirmar</button>
                </div>
            </div>
        </div>
        <div class="modal-ventanilla-overlay" id="mv-pin-overlay" style="z-index: 9999999;">
            <div class="modal-ventanilla-box" style="max-width: 400px; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 25px;">
                <i class="fa-solid fa-lock" style="font-size: 3rem; color: #b45309; margin-bottom: 15px;"></i>
                <h3 style="font-size: 1.2rem; color: #1e293b; margin-bottom: 10px; font-weight: 900;">ACCESO RESTRINGIDO</h3>
                <p style="color: #64748b; margin-bottom: 20px; font-size: 0.95rem; font-weight: bold;">PACIENTE SIN TURNO.<br>Ingrese PIN de operador para habilitar Demanda Espontánea:</p>
                <input type="tel" id="mv-pin-input" autocomplete="off" placeholder="PIN" style="-webkit-text-security: disc; width: 100%; padding: 15px; margin-bottom: 20px; text-align: center; font-size: 1.5rem; letter-spacing: 5px; font-weight: bold; border-radius: 12px; border: 2px solid #cbd5e1; outline: none; box-sizing: border-box;" maxlength="6">
                <div style="display: flex; gap: 10px; justify-content: center; width: 100%;">
                    <button onclick="document.getElementById('mv-pin-overlay').style.display='none';" style="flex: 1; padding: 12px 20px; border-radius: 8px; border: 1px solid #cbd5e1; background: white; color: #475569; font-weight: bold; cursor: pointer;">Cancelar</button>
                    <button id="mv-pin-confirmar" style="flex: 1; padding: 12px 20px; border-radius: 8px; border: none; background: #b45309; color: white; font-weight: bold; cursor: pointer;">Ingresar</button>
                </div>
            </div>
        </div>
        <div class="modal-ventanilla-overlay" id="mv-error-afiliado-overlay" style="z-index: 9999999;">
            <div class="modal-ventanilla-box" style="max-width: 450px; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 30px; background: #fff1f2; border: 2px solid #fda4af;">
                <i class="fa-solid fa-circle-xmark" style="font-size: 4rem; color: #e11d48; margin-bottom: 20px;"></i>
                <h3 style="font-size: 1.5rem; color: #9f1239; margin-bottom: 10px; font-weight: 900;">AFILIADO NO ENCONTRADO</h3>
                <p style="color: #be123c; margin-bottom: 25px; font-size: 1rem; font-weight: 500;">El DNI ingresado no figura en el padrón o se encuentra inactivo. Por favor, verifique el número e intente nuevamente.</p>
                <!-- SOLUCION: Recargar la pagina para salir del modal sin problemas con DevExpress -->
                <button onclick="window.cerrarModalVentanilla();" style="width: 100%; padding: 15px; border-radius: 12px; border: none; background: #e11d48; color: white; font-weight: bold; font-size: 1.1rem; cursor: pointer; box-shadow: 0 4px 10px rgba(225, 29, 72, 0.3);">Entendido</button>
            </div>
        </div>
        <div class="modal-ventanilla-overlay" id="mv-fs-overlay" style="z-index: 9999999;">
            <div class="modal-ventanilla-box" style="max-width: 450px; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 30px; background: #fef2f2; border: 2px solid #f87171;">
                <i class="fa-solid fa-triangle-exclamation" style="font-size: 4rem; color: #dc2626; margin-bottom: 20px;"></i>
                <h3 style="font-size: 1.5rem; color: #991b1b; margin-bottom: 10px; font-weight: 900;">FUERZAS DE SEGURIDAD</h3>
                <p style="color: #b91c1c; margin-bottom: 25px; font-size: 1rem; font-weight: 600;">Este paciente pertenece a una Fuerza de Seguridad y NO TIENE COBERTURA por parte de esta Obra Social.</p>
                <button onclick="window.cerrarModalVentanilla();" style="width: 100%; padding: 15px; border-radius: 12px; border: none; background: #dc2626; color: white; font-weight: bold; font-size: 1.1rem; cursor: pointer; box-shadow: 0 4px 10px rgba(220, 38, 38, 0.3);">Cerrar Advertencia</button>
            </div>
        </div>
    `;


    <?php if($mostrar_hf_ven === 1): ?>
    document.body.insertAdjacentHTML('afterbegin', headerHTML);
    <?php endif; ?>
    
    document.body.insertAdjacentHTML('beforeend', modalHTML);

    if (window.ventanillaSinPapel === 1) {
        let overlayRollo = document.getElementById('mv-rollo-overlay');
        if (overlayRollo) {
            overlayRollo.style.display = 'flex';
            let titulo = overlayRollo.querySelector('h3');
            if (titulo) {
                titulo.innerHTML = 'VENTANILLA BLOQUEADA<br>POR FALTA DE PAPEL';
                titulo.style.color = '#dc2626'; // Rojo de error
            }
            let btnCancelar = overlayRollo.querySelector('button[onclick="window.cerrarModalRollo()"]');
            if (btnCancelar) btnCancelar.style.display = 'none';
            let overlayBox = overlayRollo.querySelector('.modal-ventanilla-box');
            if (overlayBox) {
                overlayBox.style.border = '4px solid #dc2626';
            }
        }
    }
    /* ========================================================================= */
    /* INYECCION DE LA NUEVA INTERFAZ TOTALMENTE INDEPENDIENTE (SHADOW UI) */
    /* ========================================================================= */
    const nuevaInterfazHTML = `
        <div id="actis-super-ui">
            <div class="particles-bg"></div>
            <div class="actis-split-container">
                <div class="actis-left-panel">
                    <h1>SISTEMA ACTIS<br>RECEPCIÓN</h1>
                    <p class="desc">Plataforma de validación de afiliados y asignación de turnos. Conectada a la base central de IOSFA en tiempo real.</p>
                    
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fa-solid fa-bolt"></i></div>
                        <div class="feature-text">
                            <h4>Validación Instantánea</h4>
                            <p>Sin demoras. Padrón actualizado al segundo.</p>
                        </div>
                    </div>
                    <div class="feature-item">
                        <div class="feature-icon"><i class="fa-solid fa-shield-halved"></i></div>
                        <div class="feature-text">
                            <h4>Seguridad Garantizada</h4>
                            <p>Control estricto de acceso y cobertura.</p>
                        </div>
                    </div>
                    
                    <div style="margin-top: 30px;">
                        <button onclick="window.activarTab('mejorado')" style="background: rgba(255, 255, 255, 0.1); color: #fff; border: 1px solid rgba(255,255,255,0.2); padding: 12px 20px; border-radius: 12px; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 10px; font-family: 'Outfit', sans-serif; transition: all 0.3s; box-shadow: 0 4px 15px rgba(0,0,0,0.2);">
                            <i class="fa-solid fa-magnifying-glass-plus"></i> BÚSQUEDA AVANZADA
                        </button>
                    </div>
                </div>
                <div class="actis-right-panel">
                    <div class="custom-loader-overlay" id="actis-loader">
                        <i class="fa-solid fa-circle-notch"></i>
                        <h3 style="margin:0; font-weight:800; font-size:1.5rem;">Validando en IOSFA...</h3>
                    </div>
                    <div class="custom-form-wrapper">
                        <i class="fa-solid fa-id-card main-icon"></i>
                        <h2>INGRESO DE PACIENTE</h2>
                        <p class="subtitle">Escanee o escriba el DNI del afiliado</p>
                        
                        <div class="custom-input-group">
                            <input type="number" id="actis-fake-dni" placeholder="NÚMERO DE DNI" autocomplete="off">
                        </div>
                        
                        <button class="custom-btn-submit" id="actis-fake-btn">
                            <i class="fa-solid fa-magnifying-glass"></i> Consultar Padrón
                        </button>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Inyectamos la interfaz falsa encima de todo
    document.body.insertAdjacentHTML('afterbegin', nuevaInterfazHTML);

    const iframeContainerHTML = `
        <div id="actis-tab-mejorado" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; z-index:20000; background:white;">
            <div style="position: absolute; top: 500px; left: 30px; z-index: 20001;">
                <button onclick="window.activarTab('ventanilla')" onmouseover="this.style.transform='scale(1.05)';" onmouseout="this.style.transform='scale(1)';" style="background: linear-gradient(135deg, #2563eb, #1e40af); color: white; border: none; padding: 14px 28px; border-radius: 50px; font-weight: 800; font-size: 1rem; cursor: pointer; box-shadow: 0 10px 30px rgba(37, 99, 235, 0.5); font-family: 'Outfit', sans-serif; display: flex; align-items: center; gap: 12px; transition: transform 0.2s ease;">
                    <i class="fa-solid fa-arrow-left" style="font-size: 1.2rem;"></i> VOLVER A RECEPCIÓN
                </button>
            </div>
            <iframe src="https://validador.iosfa.gob.ar/Afiliaciones/Menu" style="width:100%; height:100%; border:none;"></iframe>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', iframeContainerHTML);
    
    window.activarTab = function(tabName) {
        if (tabName === 'ventanilla') {
            document.getElementById('actis-super-ui').style.display = 'flex';
            document.getElementById('actis-tab-mejorado').style.display = 'none';
        } else {
            document.getElementById('actis-super-ui').style.display = 'none';
            document.getElementById('actis-tab-mejorado').style.display = 'block';
        }
    };
    
    // Script puente para conectar la UI hermosa con la UI fea y oculta de DevExpress
    setTimeout(() => {
        const fakeDni = document.getElementById('actis-fake-dni');
        const fakeBtn = document.getElementById('actis-fake-btn');
        const realDni = document.querySelector('[id$="txtDNI_I"]');
        const realBtn = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout_btnBuscarAfiliado');
        const loader = document.getElementById('actis-loader');
        
        if (fakeDni && realDni) {
            const urlParams = new URLSearchParams(window.location.search);
            const dniAuto = urlParams.get('dni_repetir');
            
            if (dniAuto) {
                fakeDni.value = dniAuto;
                window.history.replaceState({}, document.title, window.location.pathname);
                setTimeout(() => { fakeBtn.click(); }, 300);
            } else {
                fakeDni.focus();
            }
            
            fakeBtn.addEventListener('click', () => {
                if(fakeBtn.disabled) return;
                
                if(fakeDni.value.length < 5) {
                    alert('Por favor, ingrese un DNI válido.');
                    return;
                }
                
                // Evitar multi-clicks accidentales que corrompen el ViewState de DevExpress
                fakeBtn.disabled = true;
                fakeDni.disabled = true;
                
                // Mostramos un loader hermoso de espera
                loader.style.display = 'flex';
                
                // Copiamos el valor a la UI nativa
                realDni.value = fakeDni.value;
                realDni.dispatchEvent(new Event('change', { bubbles: true }));
                realDni.dispatchEvent(new KeyboardEvent('keyup', { bubbles: true }));
                
                // Disparamos el boton original de DevExpress despues de 100ms para asegurar el estado
                setTimeout(() => {
                    if (realBtn) realBtn.click();
                }, 100);
            });
            
            fakeDni.addEventListener('keypress', (e) => {
                if(e.key === 'Enter') fakeBtn.click();
            });
        }
    }, 500);
    /* ========================================================================= */


    /* ELIMINADO: manipulación del DOM original de DevExpress que rompía el ViewState */
    
    <?php if($mostrar_hf_ven === 1): ?>
    document.body.insertAdjacentHTML('beforeend', footerHTML);
    <?php endif; ?>

    window.abrirModalRollo = function() {
        document.getElementById('mv-rollo-overlay').style.display = 'flex';
    };

    window.cerrarModalRollo = function() {
        document.getElementById('mv-rollo-overlay').style.display = 'none';
        let pinEl = document.getElementById('mv-rollo-pin');
        if(pinEl) {
            pinEl.value = '';
            pinEl.placeholder = 'PIN de Seguridad';
            pinEl.style.borderColor = '#cbd5e1';
        }
        let fakeDni = document.getElementById('actis-fake-dni');
        if (fakeDni && !fakeDni.disabled) {
            setTimeout(() => { fakeDni.focus(); }, 150);
        }
    };

    window.confirmarResetRollo = function() {
        let pinInputEl = document.getElementById('mv-rollo-pin');
        let pinInput = pinInputEl.value;
        if (pinInput !== '<?php echo $pin_rollo; ?>') {
            pinInputEl.value = '';
            pinInputEl.placeholder = 'PIN INCORRECTO';
            pinInputEl.style.borderColor = '#ef4444';
            setTimeout(() => { pinInputEl.focus(); }, 100);
            return;
        }

        window.cerrarModalRollo();
        let fd = new FormData();
        fd.append('api_reset_ventanilla', '1');
        fd.append('pin', pinInput);
        fetch('https://federicogonzalez.net/actis/admin_totem.php', { method: 'POST', body: fd })
        .then(r => {
            if(r.ok) {
                // Notificación in-app en lugar de alert
                let loader = document.getElementById('actis-loader');
                if(loader) {
                    loader.innerHTML = '<i class="fa-solid fa-circle-check" style="color:#22c55e;"></i><h3 style="margin:0; font-weight:800; font-size:1.5rem;">Rollo Reseteado</h3>';
                    loader.style.display = 'flex';
                    setTimeout(() => { loader.style.display = 'none'; loader.innerHTML = '<i class="fa-solid fa-circle-notch"></i><h3 style="margin:0; font-weight:800; font-size:1.5rem;">Validando en IOSFA...</h3>'; }, 2000);
                }
                window.cerrarModalVentanilla();
            } else {
                pinInputEl.value = '';
                pinInputEl.placeholder = 'ERROR DE SERVIDOR';
                pinInputEl.style.borderColor = '#ef4444';
                document.getElementById('mv-rollo-overlay').style.display = 'flex';
            }
        })
        .catch(e => {
            pinInputEl.value = '';
            pinInputEl.placeholder = 'ERROR DE RED';
            pinInputEl.style.borderColor = '#ef4444';
            document.getElementById('mv-rollo-overlay').style.display = 'flex';
        });
    };

    window.abrirModalPin = function(callbackExito) {
        <?php if($ventanilla_demanda_esp_hab == 2): ?>
        // 2 = Habilitada SIN PIN (Acceso Libre)
        callbackExito();
        return;
        <?php endif; ?>

        let pinOverlay = document.getElementById('mv-pin-overlay');
        let pinInput = document.getElementById('mv-pin-input');
        let pinBtn = document.getElementById('mv-pin-confirmar');
        
        pinOverlay.style.display = 'flex';
        pinInput.value = '';
        setTimeout(() => pinInput.focus(), 150);
        
        let submitAction = function() {
            let pin = pinInput.value;
            if (pin === '<?php echo $pin_demanda_espontanea_ventanilla; ?>') {
                pinOverlay.style.display = 'none';
                callbackExito();
            } else if (pin !== '') {
                alert('❌ PIN INCORRECTO. Acceso denegado.');
                pinInput.value = '';
                pinInput.focus();
            }
        };
        
        pinBtn.onclick = submitAction;
        pinInput.onkeypress = function(e) {
            if(e.key === 'Enter') submitAction();
        };
    };

    window.cerrarModalVentanilla = function() {
        console.log("ACTIS: Limpiando pantalla de forma segura...");
        
        // Ocultar modales de resultados
        let modales = ['mv-overlay', 'mv-error-afiliado-overlay', 'mv-fs-overlay', 'submodal-servicios'];
        modales.forEach(id => {
            let el = document.getElementById(id);
            if(el) el.style.display = 'none';
        });
        
        // Mostrar loader para evitar parpadeos
        let loader = document.getElementById('actis-loader');
        if(loader) {
            loader.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin" style="margin-bottom:15px; font-size:3rem; color:#38bdf8;"></i><h3 style="margin:0; font-weight:800; font-size:1.5rem;">Preparando...</h3>';
            loader.style.display = 'flex';
        }
        
        // Forzar recarga limpia para resetear el ViewState bloqueado de DevExpress
        setTimeout(() => {
            window.location.replace(window.location.pathname);
        }, 100);
    };

    // Polling en vivo para chequear papel cada 30 segundos
    setInterval(() => {
        fetch('https://federicogonzalez.net/actis/api_estado_ventanilla.php?t=' + new Date().getTime())
        .then(r => r.json())
        .then(data => {
            if (data && data.sin_papel) {
                let overlayRollo = document.getElementById('mv-rollo-overlay');
                if (overlayRollo && overlayRollo.style.display !== 'flex') {
                    console.log("ACTIS: Bloqueo en vivo activado por falta de papel");
                    overlayRollo.style.display = 'flex';
                    let titulo = overlayRollo.querySelector('h3');
                    if (titulo) {
                        titulo.innerHTML = 'VENTANILLA BLOQUEADA<br>POR FALTA DE PAPEL';
                        titulo.style.color = '#dc2626';
                    }
                    let btnCancelar = overlayRollo.querySelector('button[onclick="window.cerrarModalRollo()"]');
                    if (btnCancelar) btnCancelar.style.display = 'none';
                    let overlayBox = overlayRollo.querySelector('.modal-ventanilla-box');
                    if (overlayBox) overlayBox.style.border = '4px solid #dc2626';
                }
            }
        })
        .catch(e => console.error("Error al consultar API estado ventanilla:", e));
    }, 30000);

    setInterval(() => {
        // [OPTIMIZACION DE MEMORIA]: En vez de iterar sobre 500 elementos de texto en cada tick, solo seleccionamos elementos que sepamos que traen errores de DevExpress
        let erroresPopups = document.querySelectorAll('.dxeErrorCellSys, .dxpc-mainDiv');
        erroresPopups.forEach(el => {
            if (el.textContent && el.textContent.includes('RECUERDE:')) {
                el.style.display = 'none';
            }
        });

        let label = document.querySelector('label[for*="txtDNI_I"]');
        if (label && !label.innerHTML.includes('DOCUMENTO')) {
            label.innerHTML = '<i class="fa-solid fa-id-card" style="color: #2563eb; margin-right: 8px;"></i> NÚMERO DE DOCUMENTO (DNI)';
        }
        let resultDiv = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_divDatosAfiliado');
        let inputDni = document.querySelector('[id$="txtDNI_I"]');

        if (inputDni) { if (inputDni.value.length > 0 && !window.tiempoInicioVentanilla) { window.tiempoInicioVentanilla = Date.now(); } else if (inputDni.value.length === 0 && window.tiempoInicioVentanilla) { window.tiempoInicioVentanilla = null; } }
        let btnBusqueda = document.getElementById('ctl00_Content_ObtenerCVDni1_BootstrapCallbackPanel1_btFormLayout_btnBuscarAfiliado');
        if (btnBusqueda && !btnBusqueda.dataset.iconAdded) {
            // El texto interno del botón se reemplaza, el CSS se encarga de TODO el estilo (lineas 185+)
            btnBusqueda.innerHTML = '<i class="fa-solid fa-magnifying-glass" style="margin-right:8px;"></i> CONSULTAR EN IOSFA';
            btnBusqueda.removeAttribute('style'); // Borramos cualquier estilo inline nativo de DevExpress para que mande nuestro CSS
            btnBusqueda.dataset.iconAdded = 'true';
        }
        
        // Cierre automático de errores
        let popupsDetectados = document.querySelectorAll('.dxpc-mainDiv, .modal-dialog, .modal-content, .dxeErrorCellSys, table[id*="Error"]');
        popupsDetectados.forEach(p => {
            let textoPopup = p.innerText ? p.innerText.toUpperCase() : '';
            if (textoPopup.includes('CALLBACK REQUEST') || textoPopup.includes('INTERNAL SERVER ERROR')) {
                window.location.href = 'https://validador.iosfa.gob.ar/ValidadorDni';
                return;
            }
            
            if (p.innerText.includes('no Existe') || p.innerText.includes('No esta Activo')) {
                // Solo operamos si el cartel está visible
                if (p.style.display !== 'none' && p.style.opacity !== '0' && !p.hasAttribute('data-oculto')) {
                    p.setAttribute('data-oculto', 'true');

                    // --- INICIO REGISTRO RECHAZADO VENTANILLA ---
                    let dInput = document.querySelector('[id$="txtDNI_I"]');
                    let dValor = dInput ? dInput.value : '';
                    if (dValor.length >= 6) {
                        fetch('https://federicogonzalez.net/actis/registrar_rechazo.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: 'dni=' + encodeURIComponent(dValor) + '&motivo=NO AFILIADO&origen=VENTANILLA'
                        }).catch(e=>e);
                    }
                    // --- FIN REGISTRO RECHAZADO VENTANILLA ---

                    let botonesCerrar = p.querySelectorAll('button, .btn, .dxbButtonSys, .dxb');
                    botonesCerrar.forEach(b => { if (b.innerText && b.innerText.trim() === 'Cerrar') b.click(); });
                    let inputDni = document.querySelector('[id$="txtDNI_I"]');
                    if (inputDni) {
                        inputDni.value = '';
                        inputDni.placeholder = 'AFILIADO NO ENCONTRADO';
                        document.getElementById('mv-error-afiliado-overlay').style.display = 'flex';
                    }
                } else if (p.style.display === 'none' || p.style.opacity === '0') {
                    // Reseteamos bandera si se ocultó para que funcione en el próximo error
                    p.removeAttribute('data-oculto');
                }
            }
        });

        // Detección Inmune a Bucle (Basada en el ciclo de vida del div de la mierda que usan DevExpress)
        if (resultDiv) {
            // El sistema nativo de IOSFA siempre oculta este div en el milisegundo que haces clic en "Consultar".
            // Usamos ese momento exacto para limpiar nuestro candado y prepararnos para la respuesta.
            if (resultDiv.style.display === 'none' || resultDiv.style.visibility === 'hidden') {
                resultDiv.dataset.modalMostrado = "";
            } 
            // Cuando IOSFA termina de buscar con éxito, vuelve a mostrar el div en pantalla.
            else if (resultDiv.style.display !== 'none' && resultDiv.style.visibility !== 'hidden' && resultDiv.textContent.includes('CODIGO DE VALIDACION')) {
                
                // Si el candado está vacío, significa que es una consulta 100% fresca y acabamos de recibirla.
                if (resultDiv.dataset.modalMostrado !== "true") {
                    resultDiv.dataset.modalMostrado = "true"; // Cerramos el candado inmediatamente para no repetir
                    
                    let valIdElem = document.querySelector('[id$="divDatosAfiliado_ValidacionId"]');
                    let tokenActual = valIdElem ? valIdElem.textContent.trim() : '';
                    let afiliadoRaw = document.querySelector('[id$="divDatosAfiliado_Afiliado"]') ? document.querySelector('[id$="divDatosAfiliado_Afiliado"]').textContent : '';
                    let nombre = afiliadoRaw.split('-')[0].trim();
                    let dni = inputDni ? inputDni.value : '';
                    
                    // Limpiar carteles por ID de consultas anteriores
                    ['mv-msg-turno', 'mv-msg-denegado', 'mv-msg-esp'].forEach(id => {
                        let el = document.getElementById(id);
                        if(el) el.remove();
                    });

                    // Bloquear la interfaz mientras consultamos el turno
                    document.getElementById('mv-overlay').style.display = 'flex';
                    let loadingMsg = document.createElement('div');
                    loadingMsg.id = 'mv-loading-turnos';
                    loadingMsg.className = 'mv-dynamic-msg';
                    loadingMsg.innerHTML = '<div style="background:#eff6ff; padding:20px; border-radius:15px; border:2px solid #3b82f6; text-align:center; color:#1e40af; font-size:1.2rem; font-weight:bold; margin-bottom: 20px;"><i class="fa-solid fa-spinner fa-spin"></i> Verificando turnos programados...</div>';
                    
                    let btnVal = document.getElementById('mv-btn-abrir-servicios-val');
                    let btnAsi = document.getElementById('mv-btn-abrir-servicios');
                    let printValBtn = document.getElementById('mv-print-val');
                    let printAsiBtn = document.getElementById('mv-print-asi');
                    
                    // Restaurar handlers originales si fueron modificados previamente
                    if (btnVal && btnVal.dataset.wrapped) {
                        btnVal.onclick = btnVal._originalOnclick;
                        btnVal.dataset.wrapped = "";
                    }
                    if (btnAsi && btnAsi.dataset.wrapped) {
                        btnAsi.onclick = btnAsi._originalOnclick;
                        btnAsi.dataset.wrapped = "";
                    }
                    
                    // Reset visual de los botones selectores para consultas limpias
                    let valTexto = document.getElementById('mv-servicio-val-texto');
                    if(valTexto) { valTexto.textContent = 'Haga clic aquí para elegir...'; valTexto.style.color = '#64748b'; }
                    let valOculto = document.getElementById('mv-servicio-val-oculto');
                    if(valOculto) valOculto.value = '';
                    
                    let asiTexto = document.getElementById('mv-servicio-texto');
                    if(asiTexto) { asiTexto.textContent = 'Haga clic aquí para elegir...'; asiTexto.style.color = '#64748b'; }
                    let asiOculto = document.getElementById('mv-servicio-oculto');
                    if(asiOculto) asiOculto.value = '';
                    
                    if (btnVal) {
                        btnVal.style.pointerEvents = '';
                        btnVal.style.opacity = '';
                        btnVal.dataset.locked = '';
                        let ic = btnVal.querySelector('i.fa-chevron-down');
                        if(ic) ic.style.display = '';
                    }
                    if (btnAsi) {
                        btnAsi.style.pointerEvents = '';
                        btnAsi.style.opacity = '';
                        btnAsi.dataset.locked = '';
                        let ic = btnAsi.querySelector('i.fa-chevron-down');
                        if(ic) ic.style.display = '';
                    }
                    if (printValBtn) printValBtn.disabled = true;
                    if (printAsiBtn) printAsiBtn.disabled = true;
                    
                    btnVal.style.display = 'block';
                    btnAsi.style.display = 'block';
                    
                    /*
                    btnVal.style.display = 'none';
                    btnAsi.style.display = 'none';
                    let buttonsContainer = document.querySelector('.mv-buttons');
                    buttonsContainer.prepend(loadingMsg);

                    fetch(`https://federicogonzalez.net/actis/api_verificar_turno.php?dni=${dni}&origen=ventanilla`)
                    .then(r => r.json())
                    .then(res => {
                            let loadingEl = document.getElementById('mv-loading-turnos');
                            if(loadingEl) loadingEl.remove();
                            
                            btnVal.style.display = 'block';
                            btnAsi.style.display = 'block';
                            
                            if (res && res.tiene_turno) {
                                // TIENE TURNO - Auto seleccionar
                                let turnoSeleccionado = res.turnos[0].servicio;
                                
                                document.getElementById('mv-servicio-val-texto').textContent = turnoSeleccionado + ' (TURNO)';
                                document.getElementById('mv-servicio-val-texto').style.color = '#10b981';
                                document.getElementById('mv-servicio-val-oculto').value = turnoSeleccionado;
                                printValBtn.disabled = false;
                                
                                document.getElementById('mv-servicio-texto').textContent = turnoSeleccionado + ' (TURNO)';
                                document.getElementById('mv-servicio-texto').style.color = '#10b981';
                                document.getElementById('mv-servicio-oculto').value = turnoSeleccionado;
                                printAsiBtn.disabled = false;
                                
                                // Bloquear selección manual porque ya tiene turno
                                btnVal.style.pointerEvents = 'none';
                                btnVal.style.opacity = '0.9';
                                btnVal.dataset.locked = 'true';
                                let ic1 = btnVal.querySelector('i.fa-chevron-down');
                                if (ic1) ic1.style.display = 'none';
                                
                                btnAsi.style.pointerEvents = 'none';
                                btnAsi.style.opacity = '0.9';
                                btnAsi.dataset.locked = 'true';
                                let ic2 = btnAsi.querySelector('i.fa-chevron-down');
                                if (ic2) ic2.style.display = 'none';
                                
                                let cartelTurno = document.getElementById('mv-msg-turno');
                                if(cartelTurno) cartelTurno.remove();
                                cartelTurno = document.createElement('div');
                                cartelTurno.id = 'mv-msg-turno';
                                cartelTurno.innerHTML = `<div style="background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%); padding:20px; border-radius:16px; border:1px solid #86efac; margin-bottom:20px; color:#065f46; text-align:center; box-shadow: 0 10px 15px -3px rgba(34, 197, 94, 0.1);"><i class="fa-solid fa-calendar-check" style="font-size: 2rem; color: #10b981; margin-bottom: 10px;"></i><br><span style="font-weight:700; font-size:1.1rem; letter-spacing: 0.5px;">TURNO ENCONTRADO PARA HOY:</span><br><span style="font-size:1.5rem; font-weight:900; display:block; margin-top:8px; color: #047857;">${turnoSeleccionado} (${res.turnos[0].hora})</span></div>`;
                                buttonsContainer.prepend(cartelTurno);
                                
                            } else {
                                // NO TIENE TURNO - Demanda Espontánea
                                if (res && res.demanda_espontanea_habilitada === false) {
                                    // Se ocultó el cartel por solicitud del usuario
                                } else {
                                    btnVal.style.display = 'flex';
                                    btnAsi.style.display = 'flex';
                                    
                                    let msgEsp = document.getElementById('mv-msg-esp');
                                    if(msgEsp) msgEsp.remove();
                                    msgEsp = document.createElement('div');
                                    msgEsp.id = 'mv-msg-esp';
                                    
                                    let textoPin = <?php echo $ventanilla_demanda_esp_hab; ?> == 2 
                                        ? "Demanda Espontánea Habilitada (Acceso Libre)." 
                                        : "Se requerirá PIN de operador para Demanda Espontánea.";
                                        
                                    msgEsp.innerHTML = `<div style="background: linear-gradient(135deg, #fffbeb 0%, #fef3c7 100%); padding:15px; border-radius:14px; border:1px solid #fcd34d; margin-bottom:20px; color:#b45309; font-size:1rem; text-align:center; font-weight:700; box-shadow: 0 4px 6px -1px rgba(245, 158, 11, 0.1);"><i class="fa-solid fa-circle-exclamation" style="font-size: 1.5rem; margin-bottom: 8px;"></i><br><span style="color: #92400e; font-size: 1.1rem; font-weight: 900;">PACIENTE SIN TURNO</span><br><span style="font-size: 0.95rem;">${textoPin}</span></div>`;
                                    buttonsContainer.prepend(msgEsp);
                                    
                                    if (!btnVal.dataset.wrapped) {
                                        btnVal._originalOnclick = btnVal.onclick;
                                        btnVal.dataset.wrapped = "true";
                                        btnVal.onclick = function(e) {
                                            window.abrirModalPin(function() {
                                                if (btnVal._originalOnclick) btnVal._originalOnclick(e);
                                            });
                                        };
                                    }
                                    
                                    if (!btnAsi.dataset.wrapped) {
                                        btnAsi._originalOnclick = btnAsi.onclick;
                                        btnAsi.dataset.wrapped = "true";
                                        btnAsi.onclick = function(e) {
                                            window.abrirModalPin(function() {
                                                if (btnAsi._originalOnclick) btnAsi._originalOnclick(e);
                                            });
                                        };
                                    }
                                }
                            }
                            // En caso de error crítico, liberamos los botones para no bloquear el trabajo
                            btnVal.style.display = 'block';
                            btnAsi.style.display = 'block';
                    })
                    .catch(err => {
                        let loadingEl = document.getElementById('mv-loading-turnos');
                        if(loadingEl) loadingEl.remove();
                        console.error('Error fetching turnos:', err);
                        btnVal.style.display = 'block';
                        btnAsi.style.display = 'block';
                    });
                    */

                    let fuerza = 'NO ESPECIFICADA';
                    let estado = 'NO ESPECIFICADO';
                    if (resultDiv.textContent.includes('FUERZAS ARMADAS')) fuerza = 'FUERZAS ARMADAS';
                    if (resultDiv.textContent.toUpperCase().includes('FUERZAS DE SEGURIDAD')) fuerza = 'FUERZAS DE SEGURIDAD';
                    if (resultDiv.textContent.toUpperCase().includes('ACTIVO')) estado = 'ACTIVO';
                    if (resultDiv.textContent.toUpperCase().includes('INACTIVO')) estado = 'INACTIVO';

                        try {
                            // EXTRACT DATA FROM NATIVE DEVEXPRESS
                            let horaRaw = resultDiv.querySelector('[id$="pHoraConsulta"]') ? resultDiv.querySelector('[id$="pHoraConsulta"]').textContent.replace(/(\r\n|\n|\r)/gm, " ") : '';
                            let tokenRaw = resultDiv.querySelector('[id$="divDatosAfiliado_ValidacionId"]') ? resultDiv.querySelector('[id$="divDatosAfiliado_ValidacionId"]').textContent.trim() : '';
                            let afiliadoRawExtraido = resultDiv.querySelector('[id$="divDatosAfiliado_Afiliado"]') ? resultDiv.querySelector('[id$="divDatosAfiliado_Afiliado"]').textContent.trim() : '';
                            
                            let spanFS = null;
                            let spans = resultDiv.querySelectorAll('span');
                            spans.forEach(s => {
                                if (s.textContent && s.textContent.toUpperCase().includes('FUERZAS DE SEGURIDAD')) spanFS = s.textContent.trim();
                            });
                            
                            let estadoRaw = 'NO ESPECIFICADO';
                            let h3s = resultDiv.querySelectorAll('h3');
                            h3s.forEach(h3 => {
                                if (h3.textContent && h3.textContent.toUpperCase().includes('ACTIVO')) estadoRaw = h3.textContent.trim();
                            });

                            let afiliadoNombre = afiliadoRawExtraido.split('-')[0] ? afiliadoRawExtraido.split('-')[0].trim() : afiliadoRawExtraido;

                            // RENDER BEAUTIFUL HTML
                            let infoContainer = document.getElementById('mv-content');
                            if (infoContainer) {
                                infoContainer.innerHTML = `
                                <div style="background: rgba(255,255,255,0.05); padding: 20px; border-radius: 16px; margin-bottom: 20px; border: 1px solid rgba(255,255,255,0.1); box-shadow: inset 0 2px 10px rgba(0,0,0,0.1);">
                                    <div style="font-size: 0.95rem; color: #94a3b8; text-transform: uppercase; font-weight: 800; margin-bottom: 8px; letter-spacing: 1px;"><i class="fa-solid fa-user"></i> Datos del Paciente</div>
                                    <div style="font-size: 1.4rem; font-weight: 900; color: #ffffff; text-transform: uppercase;">${afiliadoNombre}</div>
                                    <div style="font-size: 1.1rem; color: #cbd5e1; margin-top: 5px; font-weight: 600;"><i class="fa-solid fa-id-card"></i> DNI: ${dni}</div>
                                </div>
                                
                                <div style="display: flex; gap: 15px; margin-bottom: 20px;">
                                    <div style="flex: 1; background: ${estadoRaw.includes('INACTIVO') ? 'rgba(239, 68, 68, 0.1)' : 'rgba(16, 185, 129, 0.1)'}; padding: 15px; border-radius: 16px; border: 1px solid ${estadoRaw.includes('INACTIVO') ? 'rgba(239, 68, 68, 0.2)' : 'rgba(16, 185, 129, 0.2)'};">
                                        <div style="font-size: 0.85rem; color: ${estadoRaw.includes('INACTIVO') ? '#fca5a5' : '#6ee7b7'}; text-transform: uppercase; font-weight: 800; margin-bottom: 5px; letter-spacing: 0.5px;">Estado Afiliatorio</div>
                                        <div style="font-size: 1.2rem; font-weight: 900; color: ${estadoRaw.includes('INACTIVO') ? '#ef4444' : '#10b981'};">${estadoRaw}</div>
                                    </div>
                                    <div style="flex: 1; background: rgba(56, 189, 248, 0.1); padding: 15px; border-radius: 16px; border: 1px solid rgba(56, 189, 248, 0.2);">
                                        <div style="font-size: 0.85rem; color: #7dd3fc; text-transform: uppercase; font-weight: 800; margin-bottom: 5px; letter-spacing: 0.5px;">Fuerza</div>
                                        <div style="font-size: 1.1rem; font-weight: 900; color: #e0f2fe;">${spanFS || fuerza}</div>
                                    </div>
                                </div>
                                
                                <div style="background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.02) 100%); padding: 25px; border-radius: 16px; border: 1px solid rgba(255,255,255,0.15); text-align: center; box-shadow: 0 10px 20px rgba(0,0,0,0.1);">
                                    <div style="font-size: 0.95rem; color: #cbd5e1; text-transform: uppercase; font-weight: 800; margin-bottom: 10px; letter-spacing: 1px;"><i class="fa-solid fa-qrcode"></i> Código de Validación (OCVD)</div>
                                    <div style="font-size: 2.5rem; font-weight: 900; color: #ffffff; letter-spacing: 4px; font-family: monospace;">${tokenRaw || 'N/A'}</div>
                                </div>
                            `;

                            // FUERZAS DE SEGURIDAD BANNER
                            if (spanFS && spanFS.toUpperCase().includes('FUERZAS DE SEGURIDAD')) {
                                 infoContainer.innerHTML += '<div style="background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%); color: #ffffff; padding: 25px; border-radius: 20px; font-weight: 900; font-size: 1.5rem; text-align: center; border: 1px solid rgba(255,255,255,0.2); margin-top: 25px; text-transform: uppercase; box-shadow: 0 15px 30px -5px rgba(239, 68, 68, 0.5);"><i class="fa-solid fa-triangle-exclamation" style="font-size: 3rem; margin-bottom: 15px; display: block; color: #fef08a; text-shadow: 0 4px 10px rgba(0,0,0,0.2);"></i> FUERZAS DE SEGURIDAD <br><span style="font-size: 1.1rem; color: #fecaca; display: block; margin-top: 10px; font-weight: 700; letter-spacing: 1px;">PACIENTE SIN COBERTURA EN LA OBRA SOCIAL</span></div>';
                                 document.getElementById('mv-fs-overlay').style.display = 'flex';
                            }
                            }
                        } catch (err) {
                            alert('CRITICAL RENDER ERROR: ' + err.message);
                            console.error(err);
                        }

                    window.imprimirTicketOculto = function(url) {
                        let overlay = document.getElementById('mv-overlay');
                        if (overlay) {
                            overlay.style.display = 'flex';
                            overlay.innerHTML = '<div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:white;"><i class="fa-solid fa-print" style="font-size: 5rem; margin-bottom: 20px; color: #38bdf8;"></i><h1 style="font-size: 2rem; font-weight: 900;">IMPRIMIENDO TICKET...</h1><p style="color:#94a3b8; margin-top:10px;">Por favor espere unos segundos.</p></div>';
                        }
                        
                        let printWin = window.open(url, '_blank');
                        let checkClosed = setInterval(() => {
                            if (!printWin || printWin.closed) {
                                clearInterval(checkClosed);
                                
                                let dniGuardado = inputDni ? inputDni.value : '';
                                if (overlay) {
                                    overlay.innerHTML = `
                                        <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; color:white; width: 100%;">
                                            <i class="fa-solid fa-circle-check" style="font-size: 5rem; margin-bottom: 20px; color: #10b981;"></i>
                                            <h1 style="font-size: 2rem; font-weight: 900; margin-bottom: 30px;">TICKET ENVIADO A IMPRESIÓN</h1>
                                            <div style="display:flex; gap:20px; width:100%; max-width:650px;">
                                                <button onclick="window.cerrarModalVentanilla();" style="flex:1; padding:25px 20px; border-radius:15px; border:none; background:#3b82f6; color:white; font-size:1.2rem; font-weight:bold; cursor:pointer; box-shadow:0 10px 20px rgba(59,130,246,0.3); transition: transform 0.2s;"><i class="fa-solid fa-user-plus" style="font-size:2.5rem; margin-bottom:15px; display:block;"></i><span style="display:block;">Nuevo Paciente</span></button>
                                                <button onclick="window.location.href='https://validador.iosfa.gob.ar/ValidadorDni?dni_repetir=${dniGuardado}';" style="flex:1; padding:25px 20px; border-radius:15px; border:none; background:#10b981; color:white; font-size:1.2rem; font-weight:bold; cursor:pointer; box-shadow:0 10px 20px rgba(16,185,129,0.3); transition: transform 0.2s;"><i class="fa-solid fa-notes-medical" style="font-size:2.5rem; margin-bottom:15px; display:block;"></i><span style="display:block;">Sacar Otro Turno<br>(Mismo DNI)</span></button>
                                            </div>
                                        </div>
                                    `;
                                } else {
                                    window.cerrarModalVentanilla();
                                }
                            }
                        }, 500);
                    };


                    // FUNCIÁ“N MOTOR DE RENDERIZADO Y FILTRADO DINÁ MICO
                    window.submodalTarget = 'ASI'; // Por defecto Asistencia
                    window.smLetraActiva = 'TODOS';

                    window.renderSubmodalServicios = function() {
                        let listadoContenedor = document.getElementById('sm-listado-contenedor');
                        if (!listadoContenedor) return;
                        listadoContenedor.innerHTML = '';

                        let txtBuscador = document.getElementById('sm-buscador') ? document.getElementById('sm-buscador').value.toUpperCase().trim() : '';
                        let letraFiltro = window.smLetraActiva || 'TODOS';

                        let serviciosAgregados = new Set(); // Evitar duplicados
                        
                        // Usar la lista maestra de la API
                        let listaAProcesar = (window.datosServiciosAPI && window.datosServiciosAPI.length > 0) ? window.datosServiciosAPI : [];
                        
                        // Fallback por si la API tarda en cargar
                        if (listaAProcesar.length === 0) {
                            let tempSelect = document.createElement('select');
                            tempSelect.innerHTML = window.serviciosCargados;
                            Array.from(tempSelect.options).forEach(opt => listaAProcesar.push({nombre: opt.value, imprime_numero: 1}));
                        }

                        listaAProcesar.forEach(srv => {
                            let nombreServicio = srv.nombre ? srv.nombre.toUpperCase().trim() : '';
                            
                            // FILTRO CRUCIAL: Si estamos en ASISTENCIA, solo mostrar los que tienen imprime_numero == 1
                            if (window.submodalTarget === 'ASI' && String(srv.imprime_numero) !== "1") {
                                return;
                            }
                            // FILTRO CRUCIAL: Si estamos en VALIDACION, solo mostrar los que tienen imprime_numero == 0
                            if (window.submodalTarget === 'VAL' && String(srv.imprime_numero) === "1") {
                                return;
                            }

                            // Si el servicio ya fue dibujado, lo saltamos (evita duplicados reales)
                            if (serviciosAgregados.has(nombreServicio)) return;

                            // 1. Filtrar por cuadro de bÁºsqueda escriturada
                            if (txtBuscador !== '' && !nombreServicio.includes(txtBuscador)) return;

                            // 2. Filtrar por Abecedario de botones
                            if (letraFiltro !== 'TODOS' && !nombreServicio.startsWith(letraFiltro)) return;
                            
                            // Lo marcamos como agregado
                            serviciosAgregados.add(nombreServicio);

                            // Crear botón interactivo
                            let btnSrv = document.createElement('button');
                            btnSrv.className = 'sm-item';
                            btnSrv.innerHTML = `<span>${srv.nombre}</span> <i class="fa-solid fa-arrow-right"></i>`;
                            
                            btnSrv.onclick = function() {
                                if (window.submodalTarget === 'VAL') {
                                    document.getElementById('mv-servicio-val-texto').textContent = srv.nombre;
                                    document.getElementById('mv-servicio-val-texto').style.color = '#0f172a';
                                    document.getElementById('mv-servicio-val-oculto').value = srv.nombre;
                                    document.getElementById('mv-print-val').disabled = false;
                                } else {
                                    document.getElementById('mv-servicio-texto').textContent = srv.nombre;
                                    document.getElementById('mv-servicio-texto').style.color = '#0f172a';
                                    document.getElementById('mv-servicio-oculto').value = srv.nombre;
                                    document.getElementById('mv-print-asi').disabled = false;
                                }
                                document.getElementById('submodal-servicios').style.display = 'none';
                            };
                            listadoContenedor.appendChild(btnSrv);
                        });
                    };

                    // Inicializar abecedario una Áºnica vez si estÁ¡ vacío
                    let abcContenedor = document.getElementById('sm-abc-contenedor');
                    if (abcContenedor && abcContenedor.innerHTML.trim() === '') {
                        let letrasArray = ['TODOS'].concat('ABCDEFGHIJKLMNOPQRSTUVWXYZ'.split(''));
                        letrasArray.forEach(letra => {
                            let btnLetra = document.createElement('button');
                            btnLetra.className = 'sm-abc-btn';
                            if (letra === 'TODOS') btnLetra.classList.add('active');
                            btnLetra.textContent = letra;
                            
                            btnLetra.onclick = function() {
                                abcContenedor.querySelectorAll('.sm-abc-btn').forEach(b => b.classList.remove('active'));
                                btnLetra.classList.add('active');
                                window.smLetraActiva = letra;
                                window.renderSubmodalServicios();
                            };
                            abcContenedor.appendChild(btnLetra);
                        });

                        // Vincular el tipeo del cuadro de bÁºsqueda en vivo
                        document.getElementById('sm-buscador').oninput = function() {
                            window.renderSubmodalServicios();
                        };
                    }

                    // Forzar reseteo completo de campos al levantar un paciente fresco
                    document.getElementById('mv-servicio-val-texto').textContent = 'Haga clic aquí para elegir...';
                    document.getElementById('mv-servicio-val-texto').style.color = '#64748b';
                    document.getElementById('mv-servicio-val-oculto').value = '';
                    document.getElementById('mv-print-val').disabled = true;

                    document.getElementById('mv-servicio-texto').textContent = 'Haga clic aquí para elegir...';
                    document.getElementById('mv-servicio-texto').style.color = '#64748b';
                    document.getElementById('mv-servicio-oculto').value = '';
                    document.getElementById('mv-print-asi').disabled = true;

                    // Acciones de apertura dinÁ¡micas configurando objetivos
                    document.getElementById('mv-btn-abrir-servicios-val').onclick = function(e) {
                        if (this.dataset.locked === 'true') { e.stopPropagation(); return; }
                        window.submodalTarget = 'VAL';
                        document.getElementById('sm-modal-dinamico-titulo').innerHTML = 'SELECCIONE DESTINO DE VALIDACI&Oacute;N';
                        if(document.getElementById('sm-buscador')) document.getElementById('sm-buscador').value = '';
                        window.smLetraActiva = 'TODOS';
                        if(abcContenedor) {
                            abcContenedor.querySelectorAll('.sm-abc-btn').forEach(b => b.classList.remove('active'));
                            abcContenedor.firstChild.classList.add('active');
                        }
                        window.renderSubmodalServicios();
                        document.getElementById('submodal-servicios').style.display = 'flex';
                        setTimeout(() => { document.getElementById('sm-buscador').focus(); }, 150);
                    };

                    document.getElementById('mv-btn-abrir-servicios').onclick = function(e) {
                        if (this.dataset.locked === 'true') { e.stopPropagation(); return; }
                        window.submodalTarget = 'ASI';
                        document.getElementById('sm-modal-dinamico-titulo').innerText = 'SELECCIONE DESTINO DE ASISTENCIA';
                        if(document.getElementById('sm-buscador')) document.getElementById('sm-buscador').value = '';
                        window.smLetraActiva = 'TODOS';
                        if(abcContenedor) {
                            abcContenedor.querySelectorAll('.sm-abc-btn').forEach(b => b.classList.remove('active'));
                            abcContenedor.firstChild.classList.add('active');
                        }
                        window.renderSubmodalServicios();
                        document.getElementById('submodal-servicios').style.display = 'flex';
                        setTimeout(() => { document.getElementById('sm-buscador').focus(); }, 150);
                    };

                    // Asignamos botones de impresión calculando el tiempo EXACTO EN EL MOMENTO DEL CLIC
                    document.getElementById('mv-print-val').onclick = function() {
                        let servicioSeleccionadoVal = document.getElementById('mv-servicio-val-oculto').value;
                        if (!servicioSeleccionadoVal || servicioSeleccionadoVal === '') {
                            alert("Por favor, seleccione un servicio m\u00e9dico de validaci\u00f3n antes de imprimir.");
                            return;
                        }
                        let tiempoFinalOp = window.tiempoInicioVentanilla ? ((Date.now() - window.tiempoInicioVentanilla) / 1000).toFixed(2) : 0;
                        let url = `https://federicogonzalez.net/actis/imprimir_ventanilla_validacion.php?dni=`+encodeURIComponent(dni)+`&nombre=`+encodeURIComponent(nombre)+`&token=`+encodeURIComponent(tokenActual)+`&tiempo=`+tiempoFinalOp+`&fuerza=`+encodeURIComponent(fuerza)+`&estado=`+encodeURIComponent(estado)+`&servicio=`+encodeURIComponent(servicioSeleccionadoVal);
                        window.imprimirTicketOculto(url);
                    };

                    document.getElementById('mv-print-asi').onclick = function() {
                        let servicioSeleccionado = document.getElementById('mv-servicio-oculto').value;
                        if (!servicioSeleccionado || servicioSeleccionado === '') {
                            alert("Por favor, haga clic en 'Destino de Asistencia' y seleccione un servicio antes de imprimir el ticket.");
                            return;
                        }
                        let tiempoFinalOp = window.tiempoInicioVentanilla ? ((Date.now() - window.tiempoInicioVentanilla) / 1000).toFixed(2) : 0;
                        let url = `https://federicogonzalez.net/actis/imprimir_ventanilla_asistencia.php?dni=`+encodeURIComponent(dni)+`&nombre=`+encodeURIComponent(nombre)+`&servicio=`+encodeURIComponent(servicioSeleccionado)+`&token=`+encodeURIComponent(tokenActual)+`&tiempo=`+tiempoFinalOp+`&fuerza=`+encodeURIComponent(fuerza)+`&estado=`+encodeURIComponent(estado);
                        window.imprimirTicketOculto(url);
                    };
                }
            }
        }
    }, 500);

    // === COMPATIBILIDAD CON CARGADOR DINÁMICO DE TAMPERMONKEY ===
    // Expone las funciones llamadas desde el HTML (onclick) al contexto del sandbox.
    if (typeof unsafeWindow !== 'undefined') {
        unsafeWindow.abrirModalRollo = window.abrirModalRollo;
        unsafeWindow.cerrarModalRollo = window.cerrarModalRollo;
        unsafeWindow.confirmarResetRollo = window.confirmarResetRollo;
        unsafeWindow.cerrarModalVentanilla = window.cerrarModalVentanilla;
    }

})();


























