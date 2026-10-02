<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }
require_once 'includes/conexion.php';

// LEER PERMISOS DINÁMICOS
$uid = (int)$_SESSION['usuario_id'];
$mis_permisos = [];
$q_perm = $conexion->query("SELECT p.nombre_permiso FROM permisos p INNER JOIN rol_permiso rp ON p.id = rp.permiso_id INNER JOIN usuarios u ON u.rol_id = rp.rol_id WHERE u.id = $uid AND u.estado = 1");
if($q_perm && $q_perm->num_rows > 0) {
    while($row = $q_perm->fetch_assoc()) { $mis_permisos[] = $row['nombre_permiso']; }
}

if (count($mis_permisos) == 1 && in_array('modulo_seguridad', $mis_permisos)) {
    header("Location: seguridad_dashboard.php");
    exit;
}

require_once 'includes/header.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');
$hoy = date('Y-m-d');

// --- 1. TELEMETRÍA ORIGINAL ---
$t_totales = $conexion->query("SELECT COUNT(*) as cant FROM turnos WHERE fecha_turno = '$hoy'")->fetch_assoc()['cant'] ?? 0;
$t_pendientes = $conexion->query("SELECT COUNT(*) as cant FROM turnos WHERE fecha_turno = '$hoy' AND estado = 'Pendiente'")->fetch_assoc()['cant'] ?? 0;
$t_autorizados = $conexion->query("SELECT COUNT(*) as cant FROM turnos WHERE fecha_turno = '$hoy' AND estado = 'Autorizado'")->fetch_assoc()['cant'] ?? 0;
$t_presentes = $conexion->query("SELECT COUNT(*) as cant FROM turnos WHERE fecha_turno = '$hoy' AND estado = 'Presente'")->fetch_assoc()['cant'] ?? 0;

$totem_hoy = $conexion->query("SELECT COUNT(*) as total, SUM(CASE WHEN modo = 'VALIDACION' THEN 1 ELSE 0 END) as val, SUM(CASE WHEN modo = 'ASISTENCIA' THEN 1 ELSE 0 END) as asis, AVG(tiempo_operacion) as prom_t, MIN(CASE WHEN tiempo_operacion > 0 THEN tiempo_operacion ELSE 99 END) as vel_max FROM estadisticas_totem WHERE DATE(fecha_hora) = '$hoy'")->fetch_assoc();
$tkts_total = $totem_hoy['total'] ?? 0;
$tkts_val = $totem_hoy['val'] ?? 0;
$tkts_asis = $totem_hoy['asis'] ?? 0;
$tiempo_prom = round($totem_hoy['prom_t'] ?? 0, 1);

// NUEVAS MÉTRICAS: Desglose de Totem vs Ventanilla
$q_detalles = $conexion->query("
    SELECT 
        SUM(CASE WHEN modo = 'VALIDACION' AND (origen IS NULL OR origen != 'VENTANILLA') THEN 1 ELSE 0 END) as totem_val,
        SUM(CASE WHEN modo = 'ASISTENCIA' AND (origen IS NULL OR origen != 'VENTANILLA') THEN 1 ELSE 0 END) as totem_asis,
        SUM(CASE WHEN modo = 'VALIDACION' AND origen = 'VENTANILLA' THEN 1 ELSE 0 END) as vent_val,
        SUM(CASE WHEN modo = 'ASISTENCIA' AND origen = 'VENTANILLA' THEN 1 ELSE 0 END) as vent_asis
    FROM estadisticas_totem 
    WHERE DATE(fecha_hora) = '$hoy'
");
$detalles_op = $q_detalles ? $q_detalles->fetch_assoc() : ['totem_val'=>0, 'totem_asis'=>0, 'vent_val'=>0, 'vent_asis'=>0];
$totem_val = $detalles_op['totem_val'] ?? 0;
$totem_asis = $detalles_op['totem_asis'] ?? 0;
$vent_val = $detalles_op['vent_val'] ?? 0;
$vent_asis = $detalles_op['vent_asis'] ?? 0;

$total_totem = $totem_val + $totem_asis;
$total_ventanilla = $vent_val + $vent_asis;

$nivel_papel = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'capacidad_rollo'")->fetch_assoc()['estado'] ?? 100;
$tickets_imp = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'tickets_impresos'")->fetch_assoc()['estado'] ?? 0;
$porcentaje_papel = max(0, min(100, round((($nivel_papel - $tickets_imp) / $nivel_papel) * 100)));

$nivel_papel_vent = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'capacidad_rollo_ventanilla'")->fetch_assoc()['estado'] ?? 100;
$tickets_imp_vent = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'tickets_impresos_ventanilla'")->fetch_assoc()['estado'] ?? 0;
$porcentaje_papel_vent = max(0, min(100, round((($nivel_papel_vent - $tickets_imp_vent) / $nivel_papel_vent) * 100)));

$seg_accesos = $conexion->query("SELECT COUNT(*) as cant FROM registro_ingresos WHERE DATE(fecha_hora) = '$hoy'")->fetch_assoc()['cant'] ?? 0;
$seg_rondas = $conexion->query("SELECT COUNT(*) as cant FROM seguridad_rondas WHERE DATE(fecha_hora) = '$hoy'")->fetch_assoc()['cant'] ?? 0;
$seg_llaves = $conexion->query("SELECT COUNT(*) as cant FROM seguridad_llaves WHERE estado != 'Disponible'")->fetch_assoc()['cant'] ?? 0;

$historial_totem = $conexion->query("SELECT COUNT(*) as c FROM historial_rollos WHERE origen = 'TOTEM'")->fetch_assoc()['c'] ?? 0;
$historial_ven = $conexion->query("SELECT COUNT(*) as c FROM historial_rollos WHERE origen = 'VENTANILLA'")->fetch_assoc()['c'] ?? 0;

// ASISTENCIA DE PERSONAL
$conexion->query("CREATE TABLE IF NOT EXISTS asistencia_personal (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    fecha DATE NOT NULL,
    hora_entrada TIME,
    hora_salida TIME,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");

$q_asistencia = $conexion->query("SELECT COALESCE(u.nombre_completo, u.usuario, CONCAT('Usuario #', a.usuario_id)) AS nombre_completo, a.fecha, a.hora_entrada, a.hora_salida FROM asistencia_personal a LEFT JOIN usuarios u ON a.usuario_id = u.id ORDER BY a.id DESC LIMIT 20");
$asistencias = [];
if ($q_asistencia && $q_asistencia->num_rows > 0) {
    while($row = $q_asistencia->fetch_assoc()) {
        $asistencias[] = $row;
    }
}

// --- 2. LOS 10 INDICADORES Y ESTADÍSTICAS NUEVAS (MÁS INFORMACIÓN) ---
$pac_nuevos = $conexion->query("SELECT COUNT(*) as cant FROM pacientes WHERE DATE(fecha_registro) = '$hoy'")->fetch_assoc()['cant'] ?? 0;
$t_cancelados = $conexion->query("SELECT COUNT(*) as cant FROM turnos WHERE fecha_turno = '$hoy' AND estado = 'Cancelado'")->fetch_assoc()['cant'] ?? 0;

$q_top_med = $conexion->query("SELECT servicio, COUNT(*) as cant FROM turnos WHERE fecha_turno = '$hoy' GROUP BY servicio ORDER BY cant DESC LIMIT 1");
$top_med = ($q_top_med && $q_top_med->num_rows > 0) ? $q_top_med->fetch_assoc() : ['servicio'=>'N/A', 'cant'=>0];

$q_top_totem = $conexion->query("SELECT servicio, COUNT(*) as cant FROM estadisticas_totem WHERE DATE(fecha_hora) = '$hoy' GROUP BY servicio ORDER BY cant DESC LIMIT 1");
$top_totem = ($q_top_totem && $q_top_totem->num_rows > 0) ? $q_top_totem->fetch_assoc() : ['servicio'=>'N/A', 'cant'=>0];

$q_flujo = $conexion->query("SELECT SUM(CASE WHEN tipo_registro='INGRESO' THEN 1 ELSE 0 END) as ing, SUM(CASE WHEN tipo_registro='EGRESO' THEN 1 ELSE 0 END) as eg FROM registro_ingresos WHERE DATE(fecha_hora) = '$hoy'");
$flujo = $q_flujo ? $q_flujo->fetch_assoc() : ['ing'=>0, 'eg'=>0];

$tot_llaves_disp = $conexion->query("SELECT COUNT(*) as cant FROM seguridad_llaves WHERE estado = 'Disponible'")->fetch_assoc()['cant'] ?? 0;

$q_reinicio = $conexion->query("SELECT fecha FROM totem_config WHERE tipo = 'ultimo_reinicio'");
$ultimo_reinicio = ($q_reinicio && $q_reinicio->num_rows > 0) ? date('d/m/Y', strtotime($q_reinicio->fetch_assoc()['fecha'])) : 'N/A';

$velocidad_maxima = ($totem_hoy['vel_max'] && $totem_hoy['vel_max'] != 99) ? round($totem_hoy['vel_max'], 1) . 's' : 'N/A';

$q_total_consultas_historicas = $conexion->query("SELECT SUM(consultas) as cant FROM servicios");
$consultas_hist = $q_total_consultas_historicas ? $q_total_consultas_historicas->fetch_assoc()['cant'] : 0;
?>
<style>
    /* Estructura Base 1400px / Flat */
    .dash-container { display: flex; flex-direction: column; gap: 25px; max-width: 1400px; margin: 20px auto; padding: 0 15px; font-family: 'Poppins', sans-serif; box-sizing: border-box; }
    
    .dash-header-block { background: #ffffff; border-radius: 16px; padding: 14px 20px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
    .dash-header-title { display: flex; align-items: center; gap: 12px; }
    .dash-header-title h1 { margin: 0; font-size: 1.3rem; font-weight: 900; color: #144973; letter-spacing: -0.3px; }
    .status-badge { display: inline-flex; align-items: center; gap: 6px; background: #dcfce7; color: #166534; padding: 5px 12px; border-radius: 20px; font-size: 0.8rem; font-weight: 800; text-transform: uppercase; }
    .status-pulse { width: 8px; height: 8px; background: #10b981; border-radius: 50%; animation: pulse-green 2s infinite; }
    @keyframes pulse-green { 0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); } 70% { transform: scale(1); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0); } 100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); } }
    .dash-header-stats { display: flex; gap: 20px; align-items: center; flex-wrap: wrap; }
    .header-stat-item { font-size: 0.85rem; font-weight: 700; color: #64748b; display: flex; align-items: center; gap: 6px; }
    .header-stat-item strong { color: #0f172a; font-weight: 800; }
    
    /* KPI Superiores - Forzado a 5 columnas en Desktop */
    .dash-grid-kpi { display: grid; grid-template-columns: 1fr; gap: 15px; }
    @media (min-width: 576px) { .dash-grid-kpi { grid-template-columns: repeat(2, 1fr); } }
    @media (min-width: 992px) { .dash-grid-kpi { grid-template-columns: repeat(3, 1fr); } }
    @media (min-width: 1200px) { .dash-grid-kpi { grid-template-columns: repeat(5, 1fr); } }
    .kpi-block { background: #ffffff; border-radius: 20px; padding: 25px; border: 1px solid #e2e8f0; position: relative; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); overflow: hidden; display: flex; flex-direction: column; justify-content: center; transition: transform 0.2s; }
    .kpi-block:hover { transform: translateY(-3px); }
    .kpi-title { font-size: 0.85rem; font-weight: 800; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; z-index: 1; }
    .kpi-num { font-size: 2.8rem; font-weight: 900; color: #0f172a; margin: 5px 0; line-height: 1; z-index: 1; }
    .kpi-foot { font-size: 0.85rem; font-weight: 700; color: #475569; margin-top: auto; z-index: 1; }
    .kpi-icon-back { position: absolute; right: 20px; top: 20px; font-size: 3.5rem; color: #f1f5f9; z-index: 0; }
    
    /* Grillas de Paneles */
    .panel-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 25px; }
    .panel-card { background: #ffffff; border-radius: 20px; border: 1px solid #e2e8f0; padding: 25px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); box-sizing: border-box; display: flex; flex-direction: column; }
    .panel-card-title { font-size: 1.3rem; font-weight: 900; color: #0f172a; margin: 0 0 20px 0; display: flex; align-items: center; gap: 10px; border-bottom: 2px solid #f8fafc; padding-bottom: 15px; }
    
    .progress-container { background: #f1f5f9; border-radius: 8px; height: 12px; overflow: hidden; margin-top: 10px; border: 1px solid #e2e8f0; z-index: 1; position: relative; }
    .progress-bar { height: 100%; transition: width 0.5s ease; border-radius: 8px; }
    
    /* Listas de Estadísticas */
    .stats-list { display: flex; flex-direction: column; gap: 12px; }
    .stats-item { display: flex; justify-content: space-between; align-items: center; padding: 14px 16px; background: #f8fafc; border-radius: 12px; border: 1px solid #e2e8f0; font-size: 0.95rem; font-weight: 700; transition: transform 0.2s, border-color 0.2s; }
    .stats-item:hover { transform: translateY(-2px); border-color: #cbd5e1; }
    .stats-item span:first-child { color: #475569; font-weight: 700; font-size: 0.9rem;}

    .chart-box { position: relative; height: 260px; width: 100%; margin-top: auto; }

    @media (max-width: 991px) {
        .panel-row { grid-template-columns: 1fr !important; gap: 20px; }
    }
    @media (max-width: 768px) {
        .dash-container { padding: 0 10px; gap: 15px; }
        .dash-header-block { flex-direction: column; align-items: flex-start; padding: 12px; gap: 10px; }
        .dash-header-stats { width: 100%; justify-content: space-between; border-top: 1px solid #f1f5f9; padding-top: 10px; gap: 8px; }
        .dash-header-title h1 { font-size: 1.1rem; }
        .kpi-block { padding: 15px; }
        .kpi-num { font-size: 2rem; }
        .panel-card { padding: 15px; }
        .chart-box { height: 220px; }
    }
</style>

<div class="dash-container">
    
    <div class="dash-header-block">
        <div class="dash-header-title">
            <h1>Monitoreo de Operaciones</h1>
            <div class="status-badge">
                <div class="status-pulse"></div>
                <span>Sistema Activo</span>
            </div>
        </div>
        <div class="dash-header-stats">
            <div class="header-stat-item"><i class="fa-solid fa-calendar-day" style="color: #144973;"></i> Fecha: <strong><?php echo date('d/m/Y'); ?></strong></div>
            <div class="header-stat-item"><i class="fa-solid fa-user-check" style="color: #10b981;"></i> En Sala Ahora: <strong><?php echo $t_presentes; ?> pacientes</strong></div>
            <div class="header-stat-item"><i class="fa-solid fa-stethoscope" style="color: #0ea5e9;"></i> Mayor Demanda: <strong style="text-transform: capitalize;"><?php echo htmlspecialchars($top_med['servicio']); ?></strong></div>
            <div class="header-stat-item"><i class="fa-solid fa-desktop" style="color: #8b5cf6;"></i> Top Trámite Tótem: <strong style="text-transform: capitalize;"><?php echo htmlspecialchars($top_totem['servicio']); ?></strong></div>
        </div>
    </div>

    <div class="dash-grid-kpi">
        <div class="kpi-block" style="border-left: 5px solid #144973;">
            <i class="fa-solid fa-calendar-check kpi-icon-back"></i>
            <div class="kpi-title">Turnos Médicos (Hoy)</div>
            <div class="kpi-num"><?php echo $t_totales; ?></div>
            <div class="kpi-foot" style="color:#144973;"><i class="fa-solid fa-circle-check"></i> <?php echo $t_autorizados; ?> validados en tránsito</div>
        </div>

        <div class="kpi-block" style="border-left: 5px solid #0ea5e9;">
            <i class="fa-solid fa-desktop kpi-icon-back"></i>
            <div class="kpi-title">Operaciones Totales (Hoy)</div>
            <div class="kpi-num"><?php echo $tkts_total; ?></div>
            <div class="kpi-foot">
                <span style="color:#0ea5e9; font-weight:800; font-size:0.9rem;"><i class="fa-solid fa-qrcode"></i> Val: <?php echo $tkts_val; ?></span> | 
                <span style="color:#8b5cf6; font-weight:800; font-size:0.9rem;"><i class="fa-solid fa-ticket"></i> Asis: <?php echo $tkts_asis; ?></span>
            </div>
        </div>

        <div class="kpi-block" style="border-left: 5px solid #8b5cf6;">
            <i class="fa-solid fa-print kpi-icon-back"></i>
            <div class="kpi-title">Emisión Tickets</div>
            <div class="kpi-num" style="font-size: 2.2rem;"><?php echo ($total_totem + $total_ventanilla); ?> <span style="font-size:0.9rem; color:#64748b;">Totales</span></div>
            <div class="kpi-foot" style="display:flex; flex-direction:column; gap:4px; font-size:0.75rem;">
                <span><i class="fa-solid fa-desktop" style="color:#0ea5e9;"></i> Tótem (<?php echo $total_totem; ?>): Val: <?php echo $totem_val; ?> | Asis: <?php echo $totem_asis; ?></span>
                <span><i class="fa-solid fa-user-tie" style="color:#144973;"></i> Vent (<?php echo $total_ventanilla; ?>): Val: <?php echo $vent_val; ?> | Asis: <?php echo $vent_asis; ?></span>
                <span style="color:#f59e0b; margin-top:2px; font-weight:800;"><i class="fa-regular fa-clock"></i> Promedio Op: <?php echo $tiempo_prom; ?>s</span>
            </div>
        </div>

        <div class="kpi-block" style="border-left: 5px solid #f59e0b;">
            <i class="fa-solid fa-scroll kpi-icon-back"></i>
            <div class="kpi-title">Insumo Tótem</div>
            <div class="kpi-num" style="font-size: 2.2rem; color: <?php echo ($porcentaje_papel < 20) ? '#ef4444' : '#0f172a'; ?>;"><?php echo $porcentaje_papel; ?>%</div>
            <div class="progress-container" style="height: 6px; margin-bottom: 6px;">
                <div class="progress-bar" style="width: <?php echo $porcentaje_papel; ?>%; background: <?php echo ($porcentaje_papel < 20) ? '#ef4444' : '#10b981'; ?>;"></div>
            </div>
            <div class="kpi-foot" style="font-size: 0.8rem;">Quedan: <strong><?php echo ($nivel_papel - $tickets_imp); ?></strong> de <?php echo $nivel_papel; ?> tkts</div>
        </div>

        <div class="kpi-block" style="border-left: 5px solid #10b981;">
            <i class="fa-solid fa-receipt kpi-icon-back"></i>
            <div class="kpi-title">Insumo Ventanilla</div>
            <div class="kpi-num" style="font-size: 2.2rem; color: <?php echo ($porcentaje_papel_vent < 20) ? '#ef4444' : '#0f172a'; ?>;"><?php echo $porcentaje_papel_vent; ?>%</div>
            <div class="progress-container" style="height: 6px; margin-bottom: 6px;">
                <div class="progress-bar" style="width: <?php echo $porcentaje_papel_vent; ?>%; background: <?php echo ($porcentaje_papel_vent < 20) ? '#ef4444' : '#10b981'; ?>;"></div>
            </div>
            <div class="kpi-foot" style="font-size: 0.8rem;">Quedan: <strong><?php echo ($nivel_papel_vent - $tickets_imp_vent); ?></strong> de <?php echo $nivel_papel_vent; ?> tkts</div>
        </div>
    </div>

    <div class="panel-row" style="grid-template-columns: repeat(3, 1fr);">
        
        <div class="panel-card">
            <h2 class="panel-card-title"><i class="fa-solid fa-stethoscope" style="color: #144973;"></i> Área Médica y Recepción</h2>
            <div class="stats-list">
                <div class="stats-item"><span><i class="fa-regular fa-clock"></i> Pendientes de Llegada</span><span style="color:#f59e0b;"><?php echo $t_pendientes; ?></span></div>
                <div class="stats-item"><span><i class="fa-solid fa-user-check"></i> Presentes en Sala</span><span style="color:#10b981;"><?php echo $t_presentes; ?></span></div>
                <div class="stats-item"><span><i class="fa-solid fa-ban"></i> Cancelados (Bajas)</span><span style="color:#ef4444;"><?php echo $t_cancelados; ?></span></div>
                <div class="stats-item" style="background:#f1f5f9; border-color:#e2e8f0;">
                    <span><i class="fa-solid fa-ranking-star"></i> Top Especialidad Hoy</span>
                    <span style="color:#144973; text-transform:uppercase; font-size:0.8rem; text-align:right;">
                        <?php echo htmlspecialchars($top_med['servicio']); ?><br>
                        <span style="color:#64748b; font-weight:600;">(<?php echo $top_med['cant']; ?> turnos)</span>
                    </span>
                </div>
            </div>
        </div>

        <div class="panel-card">
            <h2 class="panel-card-title"><i class="fa-solid fa-microchip" style="color: #144973;"></i> Hardware y Autogestión</h2>
            <div class="stats-list">
                <div class="stats-item"><span><i class="fa-solid fa-qrcode"></i> Validaciones Exitosas (IOSFA)</span><span style="color:#144973;"><?php echo $tkts_val; ?></span></div>
                <div class="stats-item"><span><i class="fa-solid fa-ticket"></i> Asistencias Derivadas</span><span style="color:#0ea5e9;"><?php echo $tkts_asis; ?></span></div>
                <div class="stats-item"><span><i class="fa-solid fa-server"></i> Consultas Acumuladas</span><span style="color:#0f172a;"><?php echo number_format($consultas_hist, 0, '', '.'); ?></span></div>
                <div class="stats-item"><span><i class="fa-solid fa-power-off"></i> Último Reinicio de Sistema</span><span style="color:#475569;"><?php echo $ultimo_reinicio; ?></span></div>
                <div class="stats-item" style="background:#eff6ff; border-color:#bfdbfe;">
                    <span><i class="fa-solid fa-history" style="color:#2563eb;"></i> Historial de Rollos Usados</span>
                    <span style="color:#1e3a8a; font-weight: 900;">
                        Tótem: <?php echo $historial_totem; ?> | Ven: <?php echo $historial_ven; ?>
                    </span>
                </div>
                <div class="stats-item" style="background:#f1f5f9; border-color:#e2e8f0;">
                    <span><i class="fa-solid fa-ranking-star"></i> Top Servicio Tótem</span>
                    <span style="color:#144973; text-transform:uppercase; font-size:0.8rem; text-align:right;">
                        <?php echo htmlspecialchars($top_totem['servicio']); ?><br>
                        <span style="color:#64748b; font-weight:600;">(<?php echo $top_totem['cant']; ?> toques)</span>
                    </span>
                </div>
            </div>
        </div>

        <div class="panel-card">
            <h2 class="panel-card-title"><i class="fa-solid fa-shield-halved" style="color: #144973;"></i> Control y Seguridad</h2>
            <div class="stats-list">
                <div class="stats-item"><span><i class="fa-solid fa-door-open"></i> Ingresos (Aprobados)</span><span style="color:#10b981;"><?php echo (int)$flujo['ing']; ?></span></div>
                <div class="stats-item"><span><i class="fa-solid fa-door-closed"></i> Egresos Registrados</span><span style="color:#64748b;"><?php echo (int)$flujo['eg']; ?></span></div>
                <div class="stats-item"><span><i class="fa-solid fa-shoe-prints"></i> Rondas QR Concluidas</span><span style="color:#144973;"><?php echo $seg_rondas; ?></span></div>
                <div class="stats-item"><span><i class="fa-solid fa-key"></i> Llaves Maestras en Uso</span><span style="color:#ef4444;"><?php echo $seg_llaves; ?></span></div>
                <div class="stats-item"><span><i class="fa-solid fa-key"></i> Llaves Disponibles</span><span style="color:#10b981;"><?php echo $tot_llaves_disp; ?></span></div>
            </div>
        </div>

    </div>

    <div class="panel-row" style="grid-template-columns: 1fr 1fr;">
        <div class="panel-card">
            <h2 class="panel-card-title"><i class="fa-solid fa-users-rectangle" style="color: #144973;"></i> Desglose de Operaciones (Hoy)</h2>
            <div class="stats-list">
                <div class="stats-item"><span><i class="fa-solid fa-desktop" style="color:#0ea5e9; width:20px;"></i> Tótem - Validaciones IOSFA</span><span style="color:#0ea5e9; font-size:1.1rem;"><?php echo $totem_val; ?></span></div>
                <div class="stats-item"><span><i class="fa-solid fa-desktop" style="color:#0ea5e9; width:20px;"></i> Tótem - Asistencia</span><span style="color:#0ea5e9; font-size:1.1rem;"><?php echo $totem_asis; ?></span></div>
                <div class="stats-item"><span><i class="fa-solid fa-user-tie" style="color:#144973; width:20px;"></i> Ventanilla - Validaciones IOSFA</span><span style="color:#144973; font-size:1.1rem;"><?php echo $vent_val; ?></span></div>
                <div class="stats-item"><span><i class="fa-solid fa-user-tie" style="color:#144973; width:20px;"></i> Ventanilla - Asistencia</span><span style="color:#144973; font-size:1.1rem;"><?php echo $vent_asis; ?></span></div>
                <div class="stats-item" style="background:#f8fafc; border-color:#cbd5e1; margin-top: 10px;">
                    <span><i class="fa-solid fa-scale-balanced" style="color:#475569; width:20px;"></i> Autogestión vs Operador</span>
                    <span style="color:#0f172a; font-size:0.9rem; text-align:right;">
                        Tótem: <strong style="color:#0ea5e9;"><?php echo $total_totem; ?></strong> | Ventanilla: <strong style="color:#144973;"><?php echo $total_ventanilla; ?></strong>
                    </span>
                </div>
            </div>
        </div>

        <div class="panel-card">
            <h2 class="panel-card-title"><i class="fa-solid fa-chart-line" style="color: #144973;"></i> Comparativa Tótem vs Ventanilla</h2>
            <div class="chart-box">
                <canvas id="comparativaOrigenChart"></canvas>
            </div>
        </div>
    </div>

    <div class="panel-row" style="grid-template-columns: 1fr 1fr;">
        <div class="panel-card">
            <h2 class="panel-card-title"><i class="fa-solid fa-chart-pie" style="color: #144973;"></i> Distribución General (Volumen)</h2>
            <div class="chart-box">
                <canvas id="volumenChart"></canvas>
            </div>
        </div>

        <div class="panel-card">
            <h2 class="panel-card-title"><i class="fa-solid fa-chart-simple" style="color: #144973;"></i> Estados de Turnos Médicos</h2>
            <div class="chart-box">
                <canvas id="estadosChart"></canvas>
            </div>
        </div>
    </div>

    <!-- PANEL ASISTENCIA DE PERSONAL -->
    <div class="panel-row" style="grid-template-columns: 1fr; margin-top: 25px;">
        <div class="panel-card">
            <h2 class="panel-card-title"><i class="fa-solid fa-user-clock" style="color: #10b981;"></i> Registro de Asistencia del Personal</h2>
            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; text-align: left; font-size: 0.95rem;">
                    <thead>
                        <tr style="background: #f1f5f9; color: #475569; border-bottom: 2px solid #e2e8f0;">
                            <th style="padding: 12px 15px; font-weight: 800;">Nombre Completo</th>
                            <th style="padding: 12px 15px; font-weight: 800;">Fecha</th>
                            <th style="padding: 12px 15px; font-weight: 800;">Hora Entrada</th>
                            <th style="padding: 12px 15px; font-weight: 800;">Hora Salida</th>
                            <th style="padding: 12px 15px; font-weight: 800;">Estado Actual</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($asistencias) > 0): ?>
                            <?php foreach($asistencias as $asist): ?>
                                <?php 
                                    $estado = empty($asist['hora_salida']) ? '<span style="color: #10b981; font-weight: 700;"><i class="fa-solid fa-circle-check"></i> Presente</span>' : '<span style="color: #64748b; font-weight: 700;"><i class="fa-solid fa-arrow-right-from-bracket"></i> Retirado</span>';
                                ?>
                                <tr style="border-bottom: 1px solid #e2e8f0;">
                                    <td style="padding: 12px 15px; font-weight: 600; color: #0f172a;"><?php echo htmlspecialchars($asist['nombre_completo']); ?></td>
                                    <td style="padding: 12px 15px; color: #475569;"><?php echo date('d/m/Y', strtotime($asist['fecha'])); ?></td>
                                    <td style="padding: 12px 15px; color: #475569;"><i class="fa-regular fa-clock"></i> <?php echo date('H:i', strtotime($asist['hora_entrada'])); ?></td>
                                    <td style="padding: 12px 15px; color: #475569;"><?php echo !empty($asist['hora_salida']) ? '<i class="fa-regular fa-clock"></i> ' . date('H:i', strtotime($asist['hora_salida'])) : '-'; ?></td>
                                    <td style="padding: 12px 15px;"><?php echo $estado; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="padding: 20px; text-align: center; color: #64748b; font-style: italic;">No hay registros de asistencia para el día de hoy.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    // Definición de la paleta corporativa
    const colorPrimario = '#144973';
    const colorSecundario = '#0ea5e9';
    const colorExito = '#10b981';
    const colorAlerta = '#f59e0b';
    const colorPeligro = '#ef4444';

    // Gráfico 1: Doughnut (General)
    const ctx1 = document.getElementById('volumenChart');
    if(ctx1) {
        new Chart(ctx1.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: ['Turnos Médicos', 'Tickets Kiosco', 'Escaneos Seguridad'],
                datasets: [{
                    data: [<?php echo $t_totales; ?>, <?php echo $tkts_total; ?>, <?php echo $seg_accesos; ?>],
                    backgroundColor: [colorPrimario, colorSecundario, colorExito],
                    borderWidth: 0, hoverOffset: 4
                }]
            },
            options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'right', labels: { font: { family: 'Poppins', size: 12, weight: '600' }, color: '#475569' } } }, cutout: '65%' }
        });
    }

    // Gráfico 2: Barras (Estados de Turnos)
    const ctx2 = document.getElementById('estadosChart');
    if(ctx2) {
        new Chart(ctx2.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Pendientes', 'Autorizados', 'Presentes', 'Cancelados'],
                datasets: [{
                    label: 'Cantidad',
                    data: [<?php echo $t_pendientes; ?>, <?php echo $t_autorizados; ?>, <?php echo $t_presentes; ?>, <?php echo $t_cancelados; ?>],
                    backgroundColor: [colorAlerta, colorPrimario, colorExito, colorPeligro],
                    borderRadius: 8,
                    borderSkipped: false
                }]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false } },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { family: 'Poppins', weight: '600' }, color: '#64748b' } },
                    x: { grid: { display: false }, ticks: { font: { family: 'Poppins', size: 11, weight: '700' }, color: '#475569' } }
                }
            }
        });
    }

    // Gráfico 3: Comparativa Tótem vs Ventanilla
    const ctx3 = document.getElementById('comparativaOrigenChart');
    if(ctx3) {
        new Chart(ctx3.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Validaciones IOSFA', 'Asistencias'],
                datasets: [
                    {
                        label: 'Tótem (Autogestión)',
                        data: [<?php echo $totem_val; ?>, <?php echo $totem_asis; ?>],
                        backgroundColor: colorSecundario,
                        borderRadius: 8,
                        borderSkipped: false
                    },
                    {
                        label: 'Ventanilla (Operador)',
                        data: [<?php echo $vent_val; ?>, <?php echo $vent_asis; ?>],
                        backgroundColor: colorPrimario,
                        borderRadius: 8,
                        borderSkipped: false
                    }
                ]
            },
            options: {
                responsive: true, maintainAspectRatio: false,
                plugins: { legend: { position: 'top', labels: { font: { family: 'Poppins', size: 12, weight: '600' }, color: '#475569' } } },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { font: { family: 'Poppins', weight: '600' }, color: '#64748b' } },
                    x: { grid: { display: false }, ticks: { font: { family: 'Poppins', size: 12, weight: '700' }, color: '#475569' } }
                }
            }
        });
    }
});
</script>
<?php require_once 'includes/footer.php'; ?>