<?php
// Archivo: totem_ascensores.php
session_start();
date_default_timezone_set('America/Argentina/Buenos_Aires');
require_once 'includes/conexion.php';

// 1. Obtener todas las incidencias activas
$sql = "SELECT i.id_incidencia, i.titulo, i.descripcion_problema, i.fecha_reporte, i.estado, 
               a.nombre as ascensor, a.ubicacion, e.nombre as empresa
        FROM ascensor_incidencias i 
        JOIN ascensores a ON i.id_ascensor = a.id_ascensor 
        LEFT JOIN empresas_mantenimiento e ON a.id_empresa = e.id_empresa
        WHERE i.estado != 'resuelto' 
        ORDER BY i.fecha_reporte ASC";
$pendientes = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Contadores para los filtros
$count_todos = count($pendientes);
$count_criticos = count(array_filter($pendientes, function($p) { return $p['estado'] != 'en_proceso'; }));
$count_proceso = count(array_filter($pendientes, function($p) { return $p['estado'] == 'en_proceso'; }));

// 2. Telemetría y Estadísticas del Tótem
$total_equipos = $pdo->query("SELECT COUNT(*) FROM ascensores")->fetchColumn();
$en_falla = $pdo->query("SELECT COUNT(DISTINCT id_ascensor) FROM ascensor_incidencias WHERE estado != 'resuelto'")->fetchColumn();
$operativos = $total_equipos - $en_falla;
$salud_porcentaje = $total_equipos > 0 ? round(($operativos / $total_equipos) * 100) : 100;

// 3. Últimos equipos resueltos para la marquesina (Ticker)
$sql_ultimos = "SELECT a.nombre FROM ascensor_incidencias i JOIN ascensores a ON i.id_ascensor = a.id_ascensor WHERE i.estado = 'resuelto' ORDER BY i.id_incidencia DESC LIMIT 3";
$ultimos_resueltos = $pdo->query($sql_ultimos)->fetchAll(PDO::FETCH_COLUMN);
$texto_ticker = empty($ultimos_resueltos) ? "Sistema 100% Operativo. Sin novedades recientes." : "Últimos equipos restablecidos: " . implode(" | ", $ultimos_resueltos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <title>Terminal Logística IoT</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/signature_pad/1.5.3/signature_pad.min.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@300;400;600;700;900&family=Outfit:wght@300;400;600;800;900&display=swap" rel="stylesheet">
    
    <style>
        /* =========================================
           NÚCLEO DEL TÓTEM (AZUL MARINO REDISEÑADO)
           ========================================= */
        :root {
            --totem-dark: #020617;
            --totem-base: #0f172a;
            --totem-surface: #1e293b;
            --totem-primary: #3b82f6;
            --totem-accent: #38bdf8;
            --totem-text: #f8fafc;
            --totem-muted: #94a3b8;
            --totem-border: rgba(56, 189, 248, 0.2);
        }

        html, body {
            height: 100vh !important; width: 100vw !important; margin: 0; padding: 0;
            overflow: hidden !important; 
            background: var(--totem-dark);
            background-image: radial-gradient(circle at 100% 0%, var(--totem-base) 0%, var(--totem-dark) 100%);
            color: var(--totem-text); font-family: 'Outfit', sans-serif; user-select: none;
        }

        .totem-layout { display: grid; grid-template-columns: 24vw 76vw; height: 100vh; width: 100vw; }

        /* Barra lateral mejorada para evitar desbordes */
        .sidebar { 
            background: rgba(15, 23, 42, 0.85); 
            backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            border-right: 1px solid var(--totem-border); 
            padding: 3vh 2vw; box-sizing: border-box;
            display: flex; flex-direction: column; height: 100vh;
        }
        
        .sidebar-top { flex: 0 0 auto; }
        .sidebar-middle { flex: 1 1 auto; overflow-y: auto; scrollbar-width: none; margin-bottom: 2vh; }
        .sidebar-middle::-webkit-scrollbar { display: none; }
        .sidebar-bottom { flex: 0 0 auto; }

        .clock-widget h1 { font-family: 'Space Grotesk', sans-serif; font-size: 5vh; font-weight: 900; margin: 0; color: var(--totem-accent); text-shadow: 0 0 15px rgba(56,189,248,0.4); }
        .clock-widget p { font-size: 1.6vh; color: var(--totem-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 2px; margin-top: 0; }

        .sys-health { text-align: center; margin-top: 2vh; }
        .circular-chart { display: block; margin: 0 auto; max-width: 65%; filter: drop-shadow(0 0 10px rgba(0,0,0,0.5)); }
        .circle-bg { fill: none; stroke: rgba(255,255,255,0.05); stroke-width: 3; }
        .circle { fill: none; stroke-width: 2.8; stroke-linecap: round; animation: progress 1.5s ease-out forwards; }
        .color-optimo { stroke: #10b981; } .color-falla { stroke: #ef4444; }
        .percentage { fill: #ffffff; font-family: 'Space Grotesk', sans-serif; font-weight: 900; font-size: 0.5em; text-anchor: middle; }
        
        .stat-blocks { display: grid; gap: 1vh; margin-top: 2vh; }
        .stat-card { background: rgba(0, 0, 0, 0.3); border-radius: 1vh; padding: 1.5vh; border: 1px solid var(--totem-border); display: flex; justify-content: space-between; align-items: center; }
        .stat-card h3 { margin: 0; font-size: 2.5vh; font-weight: 900; color: #fff; }
        .stat-card p { margin: 0; font-size: 1.2vh; color: var(--totem-muted); text-transform: uppercase; font-weight: 700; letter-spacing: 1px; }

        /* Novedad: Historial Actividad */
        .activity-log { background: rgba(0,0,0,0.3); border-radius: 1vh; padding: 1.5vh; border: 1px solid var(--totem-border); margin-top: 2vh; font-size: 1.1vh; color: var(--totem-muted); }
        .activity-log h4 { font-size: 1.3vh; color: var(--totem-accent); margin: 0 0 1vh 0; text-transform: uppercase; font-weight: 800; }
        .log-item { display: flex; gap: 1vw; margin-bottom: 0.5vh; border-bottom: 1px dashed rgba(255,255,255,0.1); padding-bottom: 0.5vh; }

        .totem-info { font-size: 1.1vh; color: var(--totem-muted); text-transform: uppercase; letter-spacing: 1px; line-height: 2; background: rgba(0,0,0,0.4); padding: 1.5vh; border-radius: 1vh; border: 1px solid var(--totem-border); }
        .status-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 5px; box-shadow: 0 0 8px currentColor; }
        
        /* Animaciones para alertas */
        @keyframes pulse-red { 0% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0.4); } 70% { box-shadow: 0 0 0 15px rgba(239, 68, 68, 0); } 100% { box-shadow: 0 0 0 0 rgba(239, 68, 68, 0); } }
        @keyframes pulse-orange { 0% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4); } 70% { box-shadow: 0 0 0 15px rgba(245, 158, 11, 0); } 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0); } }

        .main-content { display: flex; flex-direction: column; height: 100vh; position: relative; }
        .top-nav { height: 10vh; display: flex; justify-content: space-between; align-items: center; padding: 0 3vw; border-bottom: 1px solid var(--totem-border); background: var(--totem-base); }
        .top-nav h2 { font-size: 2.5vh; font-weight: 800; margin: 0; color: var(--totem-text); letter-spacing: -0.5px; }
        
        .filters { display: flex; gap: 1vw; align-items: center; }
        .btn-filter { background: var(--totem-surface); border: 1px solid var(--totem-border); color: var(--totem-muted); padding: 1.2vh 1.5vw; border-radius: 2vh; font-weight: 700; font-size: 1.4vh; transition: 0.3s; cursor: pointer; display: flex; align-items: center; gap: 0.5vw; }
        .btn-filter.active { background: var(--totem-primary); color: #ffffff; border-color: var(--totem-accent); box-shadow: 0 0 15px rgba(56, 189, 248, 0.3); }
        .badge-count { background: rgba(0,0,0,0.5); padding: 0.3vh 0.6vw; border-radius: 1vh; font-size: 1.2vh; }

        /* Reloj Sincronización */
        .sync-clock { font-size: 1.2vh; color: var(--totem-muted); font-weight: 600; text-transform: uppercase; margin-right: 1vw; display: flex; align-items: center; gap: 0.5vw; }

        .cards-area { flex-grow: 1; padding: 3vh 3vw; overflow-y: auto; scrollbar-width: none; }
        .cards-area::-webkit-scrollbar { display: none; }
        
        .kiosk-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 2.5vh 1.5vw; align-content: start; }

        .card-totem {
            background: linear-gradient(145deg, var(--totem-surface), var(--totem-base));
            border: 1px solid var(--totem-border); border-radius: 1.5vh; padding: 2.5vh 1.5vw;
            cursor: pointer; display: flex; flex-direction: column; justify-content: space-between;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1); box-shadow: 0 10px 20px rgba(0,0,0,0.3);
            position: relative; overflow: hidden; height: 20vh;
        }
        .card-totem::before { content: ''; position: absolute; top: 0; left: 0; width: 5px; height: 100%; }
        
        /* Efectos lumínicos nuevos */
        .card-totem.en-proceso { border-left: 2px solid #f59e0b; animation: pulse-orange 3s infinite; }
        .card-totem.en-proceso::before { background: #f59e0b; }
        
        .card-totem.pendiente { border-left: 2px solid #ef4444; animation: pulse-red 2s infinite; }
        .card-totem.pendiente::before { background: #ef4444; }
        
        .card-totem:active { transform: scale(0.96); border-color: var(--totem-accent); }
        
        .c-header { display: flex; justify-content: space-between; align-items: flex-start; }
        .ascensor-nombre { font-size: 2.2vh; font-weight: 900; color: #ffffff; margin:0; }
        .falla-titulo { font-size: 1.6vh; color: var(--totem-text); font-weight: 500; margin-top: 1vh; line-height: 1.4; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .badge-totem { font-size: 1.2vh; padding: 0.8vh 0.8vw; border-radius: 0.8vh; background: rgba(0,0,0,0.5); border: 1px solid var(--totem-border); font-weight: 800; color: var(--totem-accent); }

        .ticker-bar { height: 5vh; background: var(--totem-dark); border-top: 1px solid var(--totem-border); display: flex; align-items: center; padding: 0 2vw; overflow: hidden; white-space: nowrap; }
        .ticker-text { display: inline-block; padding-left: 100%; animation: ticker 25s linear infinite; font-size: 1.5vh; color: var(--totem-muted); font-weight: 600; font-family: 'Space Grotesk', sans-serif; letter-spacing: 1px;}
        @keyframes ticker { 0% { transform: translate(0, 0); } 100% { transform: translate(-100%, 0); } }

        #screensaver {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: var(--totem-dark); z-index: 9999;
            display: flex; flex-direction: column; justify-content: center; align-items: center;
            opacity: 0; pointer-events: none; transition: opacity 1s ease;
        }
        #screensaver.active { opacity: 1; pointer-events: all; }
        #ss-time { font-family: 'Space Grotesk', sans-serif; font-size: 15vh; font-weight: 900; color: var(--totem-text); text-shadow: 0 0 40px rgba(56, 189, 248, 0.4); margin: 0; }
        #ss-text { font-size: 2.5vh; color: var(--totem-muted); font-weight: 600; letter-spacing: 4px; text-transform: uppercase; animation: pulse 2s infinite; }

        /* MODALES */
        .modal-content { background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(20px); border: 1px solid var(--totem-primary); border-radius: 2vh; box-shadow: 0 20px 60px rgba(0,0,0,0.8); overflow: hidden; }
        .modal-header { background: rgba(0,0,0,0.4); border-bottom: 1px solid var(--totem-border); padding: 2vh 2vw; }
        .modal-body { padding: 3vh 2vw; }
        .modal-footer-custom { padding: 2vh 2vw; background: rgba(0,0,0,0.5); border-top: 1px solid var(--totem-border); display: flex; gap: 1vw; }

        .label-totem { font-size: 1.3vh; font-weight: 800; color: var(--totem-accent); text-transform: uppercase; letter-spacing: 1px; margin-bottom: 1vh; display: block; }
        .input-totem { height: 6vh; font-size: 2vh; padding: 0 1vw; border-radius: 1vh; border: 1px solid var(--totem-border); background: rgba(0,0,0,0.4); color: #fff; font-weight: 700; width: 100%; transition: 0.3s; }
        .input-totem:focus { border-color: var(--totem-accent); box-shadow: 0 0 10px var(--totem-border); outline: none; }
        textarea.input-totem { height: 10vh; padding: 1vh 1vw; resize: none; }

        .input-pin { height: 8vh; font-size: 3vh; text-align: center; letter-spacing: 1.5vw; font-weight: 900; background: rgba(245, 158, 11, 0.1) !important; border: 2px dashed #f59e0b !important; color: #f59e0b; }

        .vk-wrapper { background: rgba(0,0,0,0.5); border-radius: 1vh; padding: 1vh; display: none; flex-direction: column; gap: 0.5vh; margin-top: 1.5vh; border: 1px solid var(--totem-border); }
        .vk-row { display: flex; justify-content: center; gap: 0.3vw; }
        .vk-key { flex: 1; height: 4.5vh; background: var(--totem-surface); border: 1px solid var(--totem-border); color: var(--totem-text); border-radius: 0.5vh; font-size: 2vh; font-weight: 700; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.1s; }
        .vk-key:active { background: var(--totem-primary); color: #fff; transform: scale(0.9); }
        .vk-wide { max-width: 12vw; background: rgba(56, 189, 248, 0.1); }
        .vk-space { max-width: 30vw; }
        .vk-close { background: rgba(239, 68, 68, 0.2); color: #ef4444; border-color: rgba(239, 68, 68, 0.5); max-width: 10vw; }

        .num-pad-btn { background: var(--totem-surface); border: 1px solid var(--totem-border); font-weight: 800; font-size: 2.5vh; border-radius: 1vh; padding: 1.5vh 0; color: #fff; transition: 0.1s; }
        .num-pad-btn:active { background: #f59e0b; color: #000; transform: scale(0.95); }

        .btn-totem { height: 7vh; font-size: 1.8vh; font-weight: 800; border-radius: 1vh; text-transform: uppercase; border: none; display: flex; align-items: center; justify-content: center; letter-spacing: 1px; transition: 0.2s; }
        .btn-totem-primary { background: linear-gradient(to right, #2563eb, #3b82f6); color: white; border: 1px solid var(--totem-accent); }
        .btn-totem-success { background: linear-gradient(to right, #059669, #10b981); color: white; }
        .btn-totem-secondary { background: rgba(255,255,255,0.1); color: var(--totem-text); border: 1px solid rgba(255,255,255,0.2); }
        .btn-totem:active { transform: scale(0.95); }

        @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.4; } 100% { opacity: 1; } }
    </style>
</head>
<body onload="initTotem()">

    <div id="screensaver" onclick="resetTimer()">
        <h1 id="ss-time">00:00</h1>
        <p id="ss-text">Toque la pantalla para iniciar</p>
    </div>

    <div class="totem-layout">
        <div class="sidebar">
            <div class="sidebar-top">
                <div class="clock-widget">
                    <h1 id="live-time">00:00</h1>
                    <p id="live-date">Cargando fecha...</p>
                </div>
            </div>

            <div class="sidebar-middle">
                <div class="sys-health">
                    <svg viewBox="0 0 36 36" class="circular-chart">
                        <path class="circle-bg" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        <path class="circle <?php echo $salud_porcentaje < 100 ? 'color-falla' : 'color-optimo'; ?>" stroke-dasharray="<?php echo $salud_porcentaje; ?>, 100" d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" />
                        <text x="18" y="20.35" class="percentage"><?php echo $salud_porcentaje; ?>%</text>
                    </svg>
                    <p style="color: var(--totem-muted); font-weight: 800; font-size: 1.4vh; margin-top: 1vh; letter-spacing: 1px;">Salud Operativa</p>
                </div>

                <div class="stat-blocks">
                    <div class="stat-card"><div><h3><?php echo $total_equipos; ?></h3><p>Nodos Totales</p></div><i class="fas fa-server text-secondary fa-2x opacity-50"></i></div>
                    <div class="stat-card"><div><h3 class="text-danger"><?php echo $en_falla; ?></h3><p>En Falla</p></div><i class="fas fa-exclamation-triangle text-danger fa-2x opacity-50"></i></div>
                </div>

                <div class="activity-log">
                    <h4><i class="fas fa-history me-1"></i> Log Reciente</h4>
                    <div class="log-item"><span class="text-info" id="log-time-1"></span> Reinicio de Interfaz Local</div>
                    <div class="log-item border-0 pb-0"><span class="text-info" id="log-time-2"></span> Sincronización DB exitosa</div>
                </div>
            </div>

            <div class="sidebar-bottom">
                <button class="btn-totem w-100 mb-2" style="background: rgba(56, 189, 248, 0.1); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.3);" onclick="window.location.href='dashboard_totem.php'">
                    <i class="fas fa-arrow-left me-2"></i> Volver al Inicio
                </button>
                <button class="btn-totem w-100 mb-3" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3);" onclick="llamarEmergencia()">
                    <i class="fas fa-phone-alt me-2"></i> Asistencia S.O.S
                </button>
                <div class="totem-info">
                    <div class="d-flex align-items-center mb-1">
                        <div class="status-dot bg-success text-success"></div> <span class="text-success fw-bold">Conexión Segura</span>
                    </div>
                    <div class="d-flex align-items-center mb-2">
                        <i class="fas fa-plug me-2 text-warning"></i> <span class="text-warning fw-bold">UPS Online / AC 220V</span>
                    </div>
                    <i class="fas fa-microchip me-1 opacity-50"></i> T-ID: <?php echo substr(md5($_SERVER['REMOTE_ADDR']), 0, 8); ?><br>
                    <i class="fas fa-network-wired me-1 opacity-50"></i> IP: <?php echo $_SERVER['REMOTE_ADDR']; ?>
                </div>
            </div>
        </div>

        <div class="main-content">
            <div class="top-nav">
                <h2>Comando Central</h2>
                
                <div class="d-flex align-items-center">
                    <div class="sync-clock">
                        <i class="fas fa-sync-alt"></i> Act. <span id="sync-timer"></span>
                    </div>

                    <div class="filters">
                        <button class="btn-filter active" onclick="filtrarTarjetas('todos', this)">Todos <span class="badge-count"><?php echo $count_todos; ?></span></button>
                        <button class="btn-filter" onclick="filtrarTarjetas('pendiente', this)">Críticos <span class="badge-count text-danger"><?php echo $count_criticos; ?></span></button>
                        <button class="btn-filter" onclick="filtrarTarjetas('en-proceso', this)">En Proceso <span class="badge-count text-warning"><?php echo $count_proceso; ?></span></button>
                    </div>
                </div>
            </div>

            <div class="cards-area">
                <?php if (empty($pendientes)): ?>
                    <div style="height: 100%; display: flex; flex-direction: column; justify-content: center; align-items: center; opacity: 0.6;">
                        <i class="fas fa-check-shield text-success mb-4" style="font-size: 8vh;"></i>
                        <h2 style="font-size: 3vh; font-weight: 800;">Flota 100% Operativa</h2>
                        <p style="font-size: 1.8vh; color: var(--totem-muted);">No se registran novedades en el sistema.</p>
                    </div>
                <?php else: ?>
                    <div class="kiosk-grid" id="grid-tarjetas">
                        <?php foreach($pendientes as $p): ?>
                            <?php $clase_falla = $p['estado'] == 'en_proceso' ? 'en-proceso' : 'pendiente'; ?>
                            <div class="card-totem <?php echo $clase_falla; ?>" data-falla="<?php echo $clase_falla; ?>" onclick="abrirFormularioDatos(<?php echo htmlspecialchars(json_encode($p), ENT_QUOTES, 'UTF-8'); ?>)">
                                <div>
                                    <div class="c-header">
                                        <h3 class="ascensor-nombre"><i class="fas fa-elevator text-info me-2 opacity-50"></i><?php echo htmlspecialchars($p['ascensor']); ?></h3>
                                        <span class="badge-totem"><i class="fas fa-wrench me-1"></i><?php echo htmlspecialchars($p['empresa'] ?? 'S/P'); ?></span>
                                    </div>
                                    <p class="falla-titulo"><?php echo htmlspecialchars($p['titulo']); ?></p>
                                </div>
                                <div style="font-size: 1.3vh; color: var(--totem-muted); font-weight: 700; display: flex; justify-content: space-between; align-items: flex-end;">
                                    <span><i class="fas fa-map-marker-alt text-danger me-1"></i> <?php echo htmlspecialchars($p['ubicacion']); ?></span>
                                    <span><i class="far fa-clock me-1"></i> <?php echo date('d/m H:i', strtotime($p['fecha_reporte'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="ticker-bar">
                <span class="ticker-text"><i class="fas fa-bolt text-warning me-2"></i> REPORTE DE TELEMETRÍA: <?php echo $texto_ticker; ?> --- SISTEMA ACTUALIZADO EN TIEMPO REAL.</span>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalDatos" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header d-flex justify-content-between align-items-center">
                    <h3 class="fw-bold text-white m-0"><i class="fas fa-terminal text-info me-2"></i> Consola de Registro</h3>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="bloque-inputs">
                        <div class="mb-2">
                            <label class="label-totem">Nodo Intervenido</label>
                            <input type="text" id="d_mostrar_ascensor" class="input-totem" readonly style="pointer-events: none; border: none; background: rgba(255,255,255,0.05); color: var(--totem-muted);">
                        </div>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2vw; margin-bottom: 1.5vh;">
                            <div>
                                <label class="label-totem">ID del Operario</label>
                                <input type="text" id="form_tecnico" class="input-totem vk-target" inputmode="none" placeholder="Toque aquí...">
                            </div>
                            <div>
                                <label class="label-totem">Dictamen Operativo</label>
                                <select id="form_estado" class="input-totem" style="background: var(--totem-base);">
                                    <option value="resuelto">🟢 OPERATIVO</option>
                                    <option value="en_proceso">🟠 STANDBY</option>
                                </select>
                            </div>
                        </div>
                        <div class="mb-2">
                            <label class="label-totem">Bitácora de Tareas</label>
                            <textarea id="form_detalle" class="input-totem vk-target" inputmode="none" placeholder="Describa la intervención..."></textarea>
                        </div>
                    </div>
                    <div id="bloque-foto">
                        <label class="label-totem"><i class="fas fa-camera me-1"></i> Adjunto Digital</label>
                        <input type="file" id="form_adjunto" class="input-totem" style="padding-top: 1vh; background: transparent; border: 1px dashed rgba(59,130,246,0.3);" accept="image/*" capture="environment">
                    </div>
                    <div class="vk-wrapper" id="keyboard-alphanumeric"></div>
                </div>
                <div class="modal-footer-custom" id="footer-datos">
                    <button type="button" class="btn-totem btn-totem-secondary" style="width: 30%;" data-bs-dismiss="modal">Abortar</button>
                    <button type="button" class="btn-totem btn-totem-primary" style="width: 70%;" onclick="pasarAFirma()"><i class="fas fa-forward me-2"></i> Continuar Secuencia</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalFirma" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content">
                <div class="modal-header d-flex justify-content-between align-items-center">
                    <h3 class="fw-bold text-white m-0"><i class="fas fa-fingerprint text-warning me-2"></i> Protocolo de Autorización</h3>
                    <button type="button" class="btn-close btn-close-white" onclick="regresarADatos()"></button>
                </div>
                <form id="formFinalTotem" action="totem_procesar_visita.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id_incidencia" id="final_id_incidencia">
                    <input type="hidden" name="tecnico" id="final_tecnico">
                    <input type="hidden" name="estado" id="final_estado">
                    <input type="hidden" name="detalle_trabajo" id="final_detalle">
                    <input type="hidden" name="firma_base64" id="final_firma_base64">

                    <div class="modal-body" style="padding: 3vh;">
                        <div class="row g-4 align-items-stretch">
                            <div class="col-md-6">
                                <div style="background: rgba(30,58,138,0.2); border: 2px dashed rgba(59,130,246,0.4); border-radius: 1.5vh; padding: 3vh; text-align: center; height: 100%; display: flex; flex-direction: column; justify-content: center;">
                                    <label class="label-totem text-info mb-2" style="font-size: 1.8vh;"><i class="fas fa-signature me-1"></i> Confirmación Biométrica</label>
                                    <button type="button" class="btn-totem btn-totem-secondary py-3 w-100" style="font-size: 2vh; border-color: #60a5fa; color: #60a5fa; background: rgba(59,130,246,0.1);" onclick="abrirModalCanvas()">
                                        <i class="fas fa-pen-nib me-2"></i> ACTIVAR LIENZO
                                    </button>
                                    <div id="estado_firma_texto" class="mt-3 fw-bold text-danger" style="font-size: 1.6vh; letter-spacing: 1px;">
                                        <i class="fas fa-times-circle me-1"></i> FIRMA PENDIENTE
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div style="background: rgba(245, 158, 11, 0.05); border: 1px solid rgba(245, 158, 11, 0.2); border-radius: 1.5vh; padding: 2vh; display: flex; flex-direction: column; gap: 1vh; height: 100%; justify-content: center;">
                                    <div class="text-center">
                                        <label class="label-totem text-warning m-0" style="font-size: 1.6vh;"><i class="fas fa-lock me-2"></i> Credencial Guardia</label>
                                    </div>
                                    <input type="password" name="pin_guardia" id="pin_guardia_input" class="input-totem input-pin w-100 mx-auto" style="max-width: 80%;" readonly required placeholder="••••" maxlength="10">
                                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 1vh; width: 85%; margin: 0 auto;">
                                        <button type="button" class="btn num-pad-btn" onclick="pressNum('1')">1</button>
                                        <button type="button" class="btn num-pad-btn" onclick="pressNum('2')">2</button>
                                        <button type="button" class="btn num-pad-btn" onclick="pressNum('3')">3</button>
                                        <button type="button" class="btn num-pad-btn" onclick="pressNum('4')">4</button>
                                        <button type="button" class="btn num-pad-btn" onclick="pressNum('5')">5</button>
                                        <button type="button" class="btn num-pad-btn" onclick="pressNum('6')">6</button>
                                        <button type="button" class="btn num-pad-btn" onclick="pressNum('7')">7</button>
                                        <button type="button" class="btn num-pad-btn" onclick="pressNum('8')">8</button>
                                        <button type="button" class="btn num-pad-btn" onclick="pressNum('9')">9</button>
                                        <button type="button" class="btn num-pad-btn" style="background: rgba(239,68,68,0.2); color: #ef4444; border-color: transparent;" onclick="pressNum('C')">C</button>
                                        <button type="button" class="btn num-pad-btn" onclick="pressNum('0')">0</button>
                                        <button type="button" class="btn num-pad-btn" style="background: rgba(245,158,11,0.2); color: #f59e0b; border-color: transparent;" onclick="document.getElementById('pin_guardia_input').value=''"><i class="fas fa-backspace"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer-custom">
                        <button type="button" class="btn-totem btn-totem-secondary" style="width: 30%;" onclick="regresarADatos()"><i class="fas fa-undo-alt me-1"></i> Modificar</button>
                        <button type="submit" class="btn-totem btn-totem-success flex-grow-1"><i class="fas fa-satellite-dish me-2"></i> Transmitir</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalFirmaCanvas" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-fullscreen p-4">
            <div class="modal-content" style="border: 2px solid var(--totem-primary); background: var(--totem-base);">
                <div class="modal-header d-flex justify-content-center" style="background: rgba(59,130,246,0.2); border-bottom: 1px solid var(--totem-primary);">
                    <h3 class="fw-bold text-white m-0" style="font-family: 'Space Grotesk', sans-serif;"><i class="fas fa-pen-nib text-info me-2"></i> PROCEDA A FIRMAR</h3>
                </div>
                <div class="modal-body" style="padding: 2vh;">
                    <div id="contenedor-canvas" style="height: 100%; width: 100%; border-radius: 1.5vh; overflow: hidden; background: #ffffff; border: 4px solid var(--totem-muted);">
                        <canvas id="signature-pad" style="width: 100%; height: 100%; display: block; touch-action: none;"></canvas>
                    </div>
                </div>
                <div class="modal-footer-custom" style="height: 10vh; justify-content: center; gap: 1vw; background: transparent; border: none;">
                    <button type="button" class="btn-totem btn-totem-secondary" style="width: 20%; font-size: 2vh; border: 2px solid #ef4444; color: #ef4444; background: rgba(239,68,68,0.1);" onclick="signaturePad.clear()"><i class="fas fa-eraser me-1"></i> Borrar</button>
                    <button type="button" class="btn-totem btn-totem-secondary" style="width: 20%; font-size: 2vh; border: 2px solid var(--totem-muted);" onclick="cerrarLienzo()"><i class="fas fa-times me-1"></i> Cerrar</button>
                    <button type="button" class="btn-totem btn-totem-primary" style="width: 50%; font-size: 2.2vh;" onclick="guardarFirmaLienzo()"><i class="fas fa-check-double me-2"></i> Fijar Trazo</button>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($_SESSION['swal_msg'])): ?>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire({
                    icon: '<?php echo $_SESSION['swal_type']; ?>',
                    title: '<?php echo $_SESSION['swal_type'] == "success" ? "Proceso Exitoso" : "Alerta"; ?>',
                    html: `<?php echo $_SESSION['swal_msg']; ?>`,
                    background: '#0f172a', color: '#fff',
                    confirmButtonColor: '#38bdf8', customClass: { popup: 'border border-info rounded-4' }
                });
            });
        </script>
        <?php unset($_SESSION['swal_msg'], $_SESSION['swal_type']); ?>
    <?php endif; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let modalDatos = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDatos'));
        let modalFirma = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalFirma'));
        let modalFirmaCanvas = bootstrap.Modal.getOrCreateInstance(document.getElementById('modalFirmaCanvas'));
        
        let signaturePad = null;
        let canvas = document.getElementById('signature-pad');
        let datosIncidenciaActual = null;
        let activeInput = null;
        let firmaRegistrada = false;

        document.addEventListener('touchstart', function(event) { if (event.touches.length > 1) { event.preventDefault(); } }, { passive: false });
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function(event) { let now = (new Date()).getTime(); if (now - lastTouchEnd <= 300) { event.preventDefault(); } lastTouchEnd = now; }, { passive: false });
        canvas.addEventListener('touchstart', function(e) { e.preventDefault(); }, {passive: false});
        canvas.addEventListener('touchmove', function(e) { e.preventDefault(); }, {passive: false});

        document.getElementById('modalFirmaCanvas').addEventListener('shown.bs.modal', function () {
            setTimeout(() => {
                let container = document.getElementById('contenedor-canvas');
                let ratio = Math.max(window.devicePixelRatio || 1, 1);
                canvas.width = container.offsetWidth * ratio;
                canvas.height = container.offsetHeight * ratio;
                canvas.getContext("2d").scale(ratio, ratio);
                if (signaturePad) { signaturePad.off(); }
                signaturePad = new SignaturePad(canvas, { backgroundColor: 'rgb(255, 255, 255)', penColor: 'rgb(0, 0, 0)', minWidth: 1.5, maxWidth: 3 });
            }, 200); 
        });

        function initTotem() {
            initVirtualKeyboard();
            actualizarReloj();
            setInterval(actualizarReloj, 1000);
            
            // Llenar mini log con horas actuales
            let now = new Date();
            let d1 = new Date(now.getTime() - 120000); // hace 2 min
            document.getElementById('log-time-2').innerText = d1.toLocaleTimeString('es-AR', {hour: '2-digit', minute:'2-digit'});
            document.getElementById('log-time-1').innerText = now.toLocaleTimeString('es-AR', {hour: '2-digit', minute:'2-digit'});

            resetTimer(); 
            document.body.addEventListener('touchstart', resetTimer);
            document.body.addEventListener('mousemove', resetTimer);
        }

        let idleTime = 0;
        function resetTimer() {
            idleTime = 0;
            document.getElementById('screensaver').classList.remove('active');
        }
        setInterval(function() {
            idleTime++;
            if (idleTime > 60) { document.getElementById('screensaver').classList.add('active'); }
        }, 1000);

        function actualizarReloj() {
            const now = new Date();
            const timeStr = now.toLocaleTimeString('es-AR', { hour: '2-digit', minute:'2-digit', timeZone: 'America/Argentina/Buenos_Aires' });
            const secStr = now.toLocaleTimeString('es-AR', { hour: '2-digit', minute:'2-digit', second:'2-digit', timeZone: 'America/Argentina/Buenos_Aires' });
            const dateStr = now.toLocaleDateString('es-AR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', timeZone: 'America/Argentina/Buenos_Aires' });
            document.getElementById('live-time').textContent = timeStr;
            document.getElementById('live-date').textContent = dateStr;
            document.getElementById('sync-timer').textContent = secStr; // Reloj sincronización
            if(idleTime > 60) document.getElementById('ss-time').textContent = timeStr;
        }

        const vkContainer = document.getElementById('keyboard-alphanumeric');
        const rows = [['1','2','3','4','5','6','7','8','9','0'], ['Q','W','E','R','T','Y','U','I','O','P'], ['A','S','D','F','G','H','J','K','L','Ñ'], ['Z','X','C','V','B','N','M',',','.','-'], ['ESPACIO', 'BORRAR', 'OCULTAR']];

        function initVirtualKeyboard() {
            vkContainer.innerHTML = '';
            rows.forEach(row => {
                let divRow = document.createElement('div'); divRow.className = 'vk-row';
                row.forEach(key => {
                    let btn = document.createElement('div'); btn.className = 'vk-key'; btn.textContent = key;
                    if(key === 'ESPACIO') btn.classList.add('vk-space');
                    if(key === 'BORRAR' || key === 'OCULTAR') btn.classList.add('vk-wide');
                    if(key === 'OCULTAR') btn.classList.add('vk-close');
                    btn.addEventListener('touchstart', (e) => { e.preventDefault(); handleKeyPress(key); });
                    btn.addEventListener('mousedown', (e) => { e.preventDefault(); handleKeyPress(key); });
                    divRow.appendChild(btn);
                });
                vkContainer.appendChild(divRow);
            });
        }

        function handleKeyPress(key) {
            if (!activeInput) return;
            if (key === 'OCULTAR') { ocultarTeclado(); return; }
            if (key === 'BORRAR') { activeInput.value = activeInput.value.slice(0, -1); } else if (key === 'ESPACIO') { activeInput.value += ' '; } else { activeInput.value += key; }
            activeInput.dispatchEvent(new Event('input', { bubbles: true }));
        }

        function mostrarTeclado(input) {
            activeInput = input;
            document.getElementById('bloque-foto').style.display = 'none';
            document.getElementById('footer-datos').style.display = 'none';
            vkContainer.style.display = 'flex';
        }

        function ocultarTeclado() {
            vkContainer.style.display = 'none';
            document.getElementById('bloque-foto').style.display = 'block';
            document.getElementById('footer-datos').style.display = 'flex';
            if(activeInput) activeInput.blur();
        }

        document.querySelectorAll('.vk-target').forEach(input => { input.addEventListener('focus', () => mostrarTeclado(input)); });

        function filtrarTarjetas(tipo, boton) {
            document.querySelectorAll('.btn-filter').forEach(b => b.classList.remove('active'));
            boton.classList.add('active');
            let tarjetas = document.querySelectorAll('.card-totem');
            tarjetas.forEach(t => { t.style.display = (tipo === 'todos' || t.getAttribute('data-falla') === tipo) ? 'flex' : 'none'; });
        }

        function llamarEmergencia() {
            Swal.fire({ title: 'Emergencia', text: 'Contactando guardia central...', icon: 'info', confirmButtonColor: '#38bdf8', background: '#0f172a', color: '#fff' });
        }

        function abrirFormularioDatos(data) {
            datosIncidenciaActual = data;
            document.getElementById('d_mostrar_ascensor').value = data.ascensor + " (" + data.ubicacion + ")";
            document.getElementById('form_tecnico').value = '';
            document.getElementById('form_estado').value = 'resuelto';
            document.getElementById('form_detalle').value = '';
            document.getElementById('form_adjunto').value = '';
            firmaRegistrada = false;
            document.getElementById('estado_firma_texto').innerHTML = '<i class="fas fa-times-circle me-1"></i> FIRMA PENDIENTE';
            document.getElementById('estado_firma_texto').className = 'mt-3 fw-bold text-danger';
            ocultarTeclado(); modalDatos.show();
        }

        function pasarAFirma() {
            let tec = document.getElementById('form_tecnico').value.trim();
            let det = document.getElementById('form_detalle').value.trim();
            if (tec === '' || det === '') { Swal.fire({ title: 'Aviso', text: 'Técnico y Bitácora obligatorios.', icon: 'warning', background: '#0f172a', color: '#fff' }); return; }
            
            let fotoInputOriginal = document.getElementById('form_adjunto');
            let formFinal = document.getElementById('formFinalTotem');
            let clonPrevio = document.getElementById('clon_adjunto');
            if (clonPrevio) clonPrevio.remove();
            if (fotoInputOriginal.files.length > 0) {
                let clonFoto = fotoInputOriginal.cloneNode(true);
                clonFoto.id = 'clon_adjunto'; clonFoto.style.display = 'none'; clonFoto.name = 'adjunto';
                formFinal.appendChild(clonFoto);
            }
            document.getElementById('final_id_incidencia').value = datosIncidenciaActual.id_incidencia;
            document.getElementById('final_tecnico').value = tec;
            document.getElementById('final_estado').value = document.getElementById('form_estado').value;
            document.getElementById('final_detalle').value = det;
            document.getElementById('pin_guardia_input').value = '';

            modalDatos.hide(); setTimeout(() => { modalFirma.show(); }, 300);
        }

        function abrirModalCanvas() { modalFirma.hide(); setTimeout(() => { modalFirmaCanvas.show(); }, 300); }
        function cerrarLienzo() { modalFirmaCanvas.hide(); setTimeout(() => { modalFirma.show(); }, 300); }

        function guardarFirmaLienzo() {
            if (!signaturePad || signaturePad.isEmpty()) { Swal.fire({ title: 'Lienzo Vacío', text: 'Firme en pantalla.', icon: 'warning', background: '#0f172a', color: '#fff' }); return; }
            firmaRegistrada = true;
            let dataUrl = signaturePad.toDataURL("image/png"); 
            document.getElementById('estado_firma_texto').innerHTML = `
                <div style="background: #fff; border: 3px solid #10b981; border-radius: 1vh; padding: 1vh;">
                    <img src="${dataUrl}" style="max-height: 10vh; display: block; margin: 0 auto;">
                </div>
                <span class="mt-2 d-block text-success fw-bold"><i class="fas fa-check-circle me-1"></i> REGISTRADA</span>
            `;
            document.getElementById('estado_firma_texto').className = 'mt-3'; 
            modalFirmaCanvas.hide(); setTimeout(() => { modalFirma.show(); }, 300);
        }

        function pressNum(val) { let pIn = document.getElementById('pin_guardia_input'); if(val === 'C') pIn.value = ''; else if(pIn.value.length < 10) pIn.value += val; }
        function regresarADatos() { modalFirma.hide(); setTimeout(() => { modalDatos.show(); }, 300); }

        document.getElementById('formFinalTotem').addEventListener('submit', function(e) {
            e.preventDefault();
            if (!firmaRegistrada) { Swal.fire({ title: 'Error', text: 'Falta la firma.', icon: 'error', background: '#0f172a', color: '#fff' }); return; }
            document.getElementById('final_firma_base64').value = signaturePad.toDataURL();
            
            Swal.fire({ title: 'Procesando...', background: '#0f172a', color: '#fff', allowOutsideClick: false, showConfirmButton: false, didOpen: () => { Swal.showLoading(); } });

            fetch('totem_procesar_visita.php', { method: 'POST', body: new FormData(this) })
            .then(response => response.json())
            .then(data => {
                if (data.success) { Swal.fire({ title: 'Éxito', html: data.message, icon: 'success', background: '#0f172a', color: '#fff' }).then(() => { window.location.reload(); }); } 
                else { Swal.fire({ title: 'Error', text: data.message, icon: 'error', background: '#0f172a', color: '#fff' }); document.getElementById('pin_guardia_input').value = ''; }
            })
            .catch(() => { Swal.fire({ title: 'Red', text: 'Falla al conectar.', icon: 'error', background: '#0f172a', color: '#fff' }); });
        });
    </script>
</body>
</html>
