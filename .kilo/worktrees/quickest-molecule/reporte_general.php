<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }
require_once 'includes/conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

$col_check = $conexion->query("SHOW COLUMNS FROM estadisticas_totem LIKE 'tiempo_operacion'");
$has_tiempo = ($col_check && $col_check->num_rows > 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (in_array('modulo_reportes_eliminar', isset($_SESSION['permisos']) ? $_SESSION['permisos'] : [])) {
        if (isset($_POST['eliminar_uno'])) { $conexion->query("DELETE FROM estadisticas_totem WHERE id = ".(int)$_POST['id_eliminar']); }
        if (isset($_POST['eliminar_seleccionados']) && !empty($_POST['ids'])) { $conexion->query("DELETE FROM estadisticas_totem WHERE id IN (".implode(',', array_map('intval', $_POST['ids'])).")"); }
        if (isset($_POST['eliminar_todos_fecha'])) { $conexion->query("DELETE FROM estadisticas_totem WHERE DATE(fecha_hora) = '".$conexion->real_escape_string($_POST['fecha_actual'])."'"); }
    }
    header("Location: reporte_general.php?".$_POST['query_string']); exit;
}

require_once 'includes/header.php';

$fecha_inicio = isset($_GET['fecha_inicio']) ? $conexion->real_escape_string($_GET['fecha_inicio']) : date('Y-m-d');
$fecha_fin = isset($_GET['fecha_fin']) ? $conexion->real_escape_string($_GET['fecha_fin']) : date('Y-m-d');
$filtro_servicio = isset($_GET['servicio']) ? $conexion->real_escape_string($_GET['servicio']) : '';
$filtro_origen = isset($_GET['origen']) ? $conexion->real_escape_string($_GET['origen']) : 'todos';
$filtro_busqueda = isset($_GET['busqueda']) ? $conexion->real_escape_string($_GET['busqueda']) : '';
$filtro_token = isset($_GET['token']) ? $conexion->real_escape_string($_GET['token']) : '';
$hora_inicio = isset($_GET['hora_inicio']) ? $conexion->real_escape_string($_GET['hora_inicio']) : '';
$hora_fin = isset($_GET['hora_fin']) ? $conexion->real_escape_string($_GET['hora_fin']) : '';

$where_clauses = ["DATE(fecha_hora) BETWEEN '$fecha_inicio' AND '$fecha_fin'"];
if (!empty($filtro_servicio)) { $where_clauses[] = "servicio = '$filtro_servicio'"; }
if (!empty($filtro_busqueda)) { $where_clauses[] = "(dni LIKE '%$filtro_busqueda%' OR nombre LIKE '%$filtro_busqueda%')"; }
if (!empty($filtro_token)) { $where_clauses[] = "token_iofa LIKE '%$filtro_token%'"; }
if (!empty($hora_inicio)) { $where_clauses[] = "TIME(fecha_hora) >= '$hora_inicio'"; }
if (!empty($hora_fin)) { $where_clauses[] = "TIME(fecha_hora) <= '$hora_fin'"; }

if ($filtro_origen === 'totem') { $where_clauses[] = "origen = 'TOTEM'"; } 
elseif ($filtro_origen === 'ventanilla') { $where_clauses[] = "origen = 'VENTANILLA'"; }

$sql_where = implode(' AND ', $where_clauses);
if(empty($sql_where)) $sql_where = "1=1";

$total_validaciones = ($res = $conexion->query("SELECT COUNT(*) as t FROM estadisticas_totem WHERE modo = 'VALIDACION' AND $sql_where")) ? $res->fetch_assoc()['t'] : 0;
$total_asistencias = ($res = $conexion->query("SELECT COUNT(*) as t FROM estadisticas_totem WHERE modo = 'ASISTENCIA' AND $sql_where")) ? $res->fetch_assoc()['t'] : 0;
$total_tickets = $total_validaciones + $total_asistencias;

$hora_pico = ($res = $conexion->query("SELECT HOUR(fecha_hora) as h, COUNT(*) as c FROM estadisticas_totem WHERE $sql_where GROUP BY h ORDER BY c DESC LIMIT 1")) && $res->num_rows > 0 ? $res->fetch_assoc() : ['h'=>'-','c'=>0];
$hora_valle = ($res = $conexion->query("SELECT HOUR(fecha_hora) as h, COUNT(*) as c FROM estadisticas_totem WHERE $sql_where GROUP BY h ORDER BY c ASC LIMIT 1")) && $res->num_rows > 0 ? $res->fetch_assoc() : ['h'=>'-','c'=>0];
$tiempo_promedio = 0;
if($has_tiempo && $total_tickets > 0) { $tiempo_promedio = ($res = $conexion->query("SELECT AVG(tiempo_operacion) as prom FROM estadisticas_totem WHERE $sql_where")) ? round($res->fetch_assoc()['prom'], 2) : 0; }

$tiempos_entre_totem = [];
$tiempos_entre_ventanilla = [];
$res_tiempos_entre = $conexion->query("SELECT origen, fecha_hora, tiempo_operacion FROM estadisticas_totem WHERE $sql_where ORDER BY origen, fecha_hora ASC");
$last_end_totem = null;
$last_end_ventanilla = null;
if ($res_tiempos_entre) {
    while($row = $res_tiempos_entre->fetch_assoc()) {
        $current_end = strtotime($row['fecha_hora']);
        $current_duration = (float)$row['tiempo_operacion'];
        $current_start = $current_end - $current_duration;

        if ($row['origen'] == 'TOTEM' || $row['origen'] == '') {
            if ($last_end_totem !== null) {
                $espera = $current_start - ($last_end_totem + 1);
                if ($espera > 0 && $espera < 3600) { $tiempos_entre_totem[] = $espera; }
            }
            $last_end_totem = $current_end;
        } elseif ($row['origen'] == 'VENTANILLA') {
            if ($last_end_ventanilla !== null) {
                $espera = $current_start - ($last_end_ventanilla + 1);
                if ($espera > 0 && $espera < 3600) { $tiempos_entre_ventanilla[] = $espera; }
            }
            $last_end_ventanilla = $current_end;
        }
    }
}
$prom_entre_totem = count($tiempos_entre_totem) > 0 ? round(array_sum($tiempos_entre_totem) / count($tiempos_entre_totem)) : 0;
$prom_entre_ventanilla = count($tiempos_entre_ventanilla) > 0 ? round(array_sum($tiempos_entre_ventanilla) / count($tiempos_entre_ventanilla)) : 0;

$res_papel = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'tickets_impresos'");
$tickets_impresos = ($res_papel && $res_papel->num_rows > 0) ? (int)$res_papel->fetch_assoc()['estado'] : 0;
$res_cap = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'capacidad_rollo'");
$capacidad_rollo = ($res_cap && $res_cap->num_rows > 0) ? (int)$res_cap->fetch_assoc()['estado'] : 120;
$papel_restante = $capacidad_rollo - $tickets_impresos;

$res_totem = $conexion->query("SELECT COUNT(*) as t FROM estadisticas_totem WHERE (origen = 'TOTEM' OR origen = '') AND $sql_where");
$total_totem = $res_totem ? $res_totem->fetch_assoc()['t'] : 0;

$res_ventanilla = $conexion->query("SELECT COUNT(*) as t FROM estadisticas_totem WHERE origen = 'VENTANILLA' AND $sql_where");
$total_ventanilla = $res_ventanilla ? $res_ventanilla->fetch_assoc()['t'] : 0;

$porcentaje_totem = ($total_tickets > 0) ? round(($total_totem / $total_tickets) * 100) : 0;

$res_papel_v = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'tickets_impresos_ventanilla'");
$tickets_impresos_v = ($res_papel_v && $res_papel_v->num_rows > 0) ? (int)$res_papel_v->fetch_assoc()['estado'] : 0;
$res_cap_v = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'capacidad_rollo_ventanilla'");
$capacidad_rollo_v = ($res_cap_v && $res_cap_v->num_rows > 0) ? (int)$res_cap_v->fetch_assoc()['estado'] : 120;
$papel_restante_ven = (isset($capacidad_rollo_v) ? $capacidad_rollo_v : 0) - (isset($tickets_impresos_v) ? $tickets_impresos_v : 0);
$papel_restante_total = (isset($papel_restante) ? $papel_restante : 0) + $papel_restante_ven;

$res_rollos_totem = $conexion->query("SELECT COUNT(*) as c FROM historial_rollos WHERE origen = 'TOTEM'");
$rollos_totem = $res_rollos_totem ? $res_rollos_totem->fetch_assoc()['c'] : 0;

$res_rollos_ven = $conexion->query("SELECT COUNT(*) as c FROM historial_rollos WHERE origen = 'VENTANILLA'");
$rollos_ven = $res_rollos_ven ? $res_rollos_ven->fetch_assoc()['c'] : 0;

$rollos_total = $rollos_totem + $rollos_ven;

$por_pagina = 50;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $por_pagina;

$res_detalle = $conexion->query("SELECT * FROM estadisticas_totem WHERE $sql_where ORDER BY fecha_hora DESC LIMIT $por_pagina OFFSET $offset");
$total_registros_q = $conexion->query("SELECT COUNT(*) as t FROM estadisticas_totem WHERE $sql_where");
$total_registros_pag = ($total_registros_q) ? $total_registros_q->fetch_assoc()['t'] : 0;
$total_paginas = ceil($total_registros_pag / $por_pagina);

$res_lista_servicios = $conexion->query("SELECT DISTINCT servicio FROM estadisticas_totem ORDER BY servicio ASC");
$query_string_pdf = http_build_query($_GET);

$res_lentos = $conexion->query("SELECT dni, nombre, tiempo_operacion, modo FROM estadisticas_totem WHERE $sql_where AND tiempo_operacion > 0 ORDER BY tiempo_operacion DESC LIMIT 5");
$lentos = []; if($res_lentos) while($r = $res_lentos->fetch_assoc()) $lentos[] = $r;
$res_rapidos = $conexion->query("SELECT dni, nombre, tiempo_operacion, modo FROM estadisticas_totem WHERE $sql_where AND tiempo_operacion > 0 ORDER BY tiempo_operacion ASC LIMIT 5");
$rapidos = []; if($res_rapidos) while($r = $res_rapidos->fetch_assoc()) $rapidos[] = $r;
$res_prom_modo = $conexion->query("SELECT modo, AVG(tiempo_operacion) as prom FROM estadisticas_totem WHERE $sql_where AND tiempo_operacion > 0 GROUP BY modo");
$prom_modo = ['VALIDACION' => 0, 'ASISTENCIA' => 0];
if($res_prom_modo) while($r = $res_prom_modo->fetch_assoc()) $prom_modo[$r['modo']] = round($r['prom'], 2);

$titulo_origen = "Unificado";
if($filtro_origen == 'totem') $titulo_origen = "Exclusivo Tótem";
if($filtro_origen == 'ventanilla') $titulo_origen = "Exclusivo Ventanilla";
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<style>
    /* Diseño Base unificado a turnos_listar.php */
    .tl-container { max-width: 1400px; margin: 20px auto; padding: 0 15px; font-family: 'Poppins', sans-serif; display: flex; flex-direction: column; gap: 20px; }
    
    .tl-panel { background: #ffffff; border-radius: 20px; padding: 25px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); }
    
    .tl-header { font-size: 1.4rem; font-weight: 900; color: #0f172a; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px; margin: 0 0 20px 0; border-bottom: 2px solid #e2e8f0; padding-bottom: 15px; }
    .tl-header-title { display: flex; align-items: center; gap: 10px; }
    
    .tl-btn-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .tl-btn { padding: 10px 16px; border: none; border-radius: 10px; font-weight: 800; font-size: 0.9rem; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 8px; transition: transform 0.2s; text-decoration: none; color: white; white-space: nowrap; box-sizing: border-box; }
    .tl-btn:active { transform: scale(0.95); }
    .tl-btn-primary { background: #144973; }
    .tl-btn-pdf { background: #10b981; }
    .tl-btn-time { background: #8b5cf6; }

    /* Tarjetas KPI Optimizadas */
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px; margin-bottom: 25px; }
    .kpi-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 16px; padding: 15px; display: flex; flex-direction: column; gap: 10px; }
    .kpi-header { font-size: 0.8rem; font-weight: 800; color: #64748b; text-transform: uppercase; display: flex; align-items: center; gap: 6px; margin: 0; }
    .kpi-body { display: flex; justify-content: space-between; align-items: flex-end; }
    .kpi-main { display: flex; flex-direction: column; line-height: 1; }
    .kpi-val { font-size: 2.2rem; font-weight: 900; color: #0f172a; }
    .kpi-lbl { font-size: 0.75rem; font-weight: 700; color: #94a3b8; margin-top: 6px; }
    .kpi-subs { display: flex; flex-direction: column; gap: 4px; text-align: right; font-size: 0.85rem; font-weight: 700; color: #475569; }

    /* Buscador */
    .buscador-wrapper { background: #f8fafc; padding: 15px; border-radius: 16px; border: 1px solid #e2e8f0; margin-bottom: 20px; }
    .buscador-input { width: 100%; padding: 12px 15px; border-radius: 10px; border: 2px solid #cbd5e1; font-weight: 600; font-family: 'Poppins', sans-serif; font-size: 0.95rem; outline: none; box-sizing: border-box; }
    .buscador-input:focus { border-color: #144973; }

    /* Tabla y Agrupación Visual */
    .tl-table-wrap { overflow-x: auto; border-radius: 12px; border: 1px solid #e2e8f0; background: white; }
    .tl-table { width: 100%; border-collapse: collapse; min-width: 800px; background: white; }
    .tl-table th { padding: 15px; text-align: left; color: #475569; background: #f8fafc; border-bottom: 2px solid #e2e8f0; font-size: 0.85rem; font-weight: 800; text-transform: uppercase; }
    .tl-table td { padding: 15px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; font-size: 0.9rem; color: #1e293b; }
    .tl-table tr.fila-datos:hover { background: #f8fafc; cursor: pointer; }
    
    .fila-fecha-grupo td { background: #144973 !important; color: white !important; font-weight: 900; font-size: 1.05rem; padding: 12px 15px !important; }
    .tl-estado { padding: 4px 8px; border-radius: 6px; font-weight: 800; font-size: 0.75rem; text-transform: uppercase; border: 1px solid #e2e8f0; background: #f1f5f9; display: inline-block; }
    
    .tl-btn-icon { width: 36px; height: 36px; border-radius: 8px; display: inline-flex; justify-content: center; align-items: center; color: white; border: none; cursor: pointer; text-decoration: none; transition: transform 0.2s; }
    .tl-btn-icon:active { transform: scale(0.95); }
    .icon-del { background: #ef4444; }

    /* === RESPONSIVO MÓVIL ESTRICTO === */
    @media (max-width: 768px) {
        body { overflow-x: hidden !important; }
        .tl-container { padding: 0 10px !important; padding-bottom: 40px !important; overflow-x: hidden !important; }
        .tl-panel { padding: 15px !important; border-radius: 16px !important; width: 100% !important; box-sizing: border-box !important; }
        
        .tl-header { flex-direction: column; align-items: stretch; gap: 15px; }
        .tl-header-title { font-size: 1.25rem; }
        .tl-btn-actions { flex-direction: row; flex-wrap: wrap; gap: 8px; }
        .tl-btn { flex: 1; padding: 10px; font-size: 0.8rem; }
        
        .kpi-grid { grid-template-columns: 1fr; gap: 12px; }
        .kpi-card { padding: 12px; }
        .kpi-val { font-size: 1.8rem; }
        
        .col-mobile-hide { display: none !important; }
        .tl-table { min-width: 0 !important; }
        .tl-table th, .tl-table td { padding: 10px 8px !important; font-size: 0.8rem !important; }
        .tl-btn-icon { width: 32px !important; height: 32px !important; }
        .col-acciones-th { font-size: 0 !important; }
        .col-acciones-th::after { content: "⚙️"; font-size: 1rem; }
    }
</style>

<div class="tl-container">
    <div class="tl-panel">
        
        <div class="tl-header">
            <div class="tl-header-title">
                <i class="fa-solid fa-chart-pie" style="color:#144973;"></i> Reporte <?php echo $titulo_origen; ?>
            </div>
            <div class="tl-btn-actions">
                <button type="button" class="tl-btn tl-btn-primary" onclick="abrirFiltros()"><i class="fa-solid fa-filter"></i> Filtros</button>
                <a href="reporte_general_pdf.php?<?php echo $query_string_pdf; ?>" target="_blank" class="tl-btn tl-btn-pdf"><i class="fa-solid fa-file-pdf"></i> Imprimir PDF</a>
                <button type="button" class="tl-btn tl-btn-time" onclick="abrirStatsTiempos()"><i class="fa-solid fa-stopwatch"></i> Tiempos</button>
                <?php if(in_array('modulo_reportes_eliminar', isset($_SESSION['permisos']) ? $_SESSION['permisos'] : [])): ?>
                <button type="button" class="tl-btn" style="background: #1e293b;" onclick="abrirModoAdmin()"><i class="fa-solid fa-shield-halved"></i> Admin</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-header"><i class="fa-solid fa-ticket" style="color: #64748b;"></i> Volumen de Tickets</div>
                <div class="kpi-body">
                    <div class="kpi-main"><span class="kpi-val"><?php echo $total_tickets; ?></span><span class="kpi-lbl">Totales Emitidos</span></div>
                    <div class="kpi-subs">
                        <div><span style="color: #14b8a6;"><?php echo $total_totem; ?></span> Tótem</div>
                        <div><span style="color: #f43f5e;"><?php echo $total_ventanilla; ?></span> Vent.</div>
                    </div>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-header"><i class="fa-solid fa-chart-simple" style="color: #8b5cf6;"></i> Tipos de Operación</div>
                <div class="kpi-body">
                    <div class="kpi-main"><span class="kpi-val"><?php echo $porcentaje_totem; ?><span style="font-size:1.4rem;">%</span></span><span class="kpi-lbl">Uso del Tótem</span></div>
                    <div class="kpi-subs">
                        <div><span style="color: #0284c7;"><?php echo $total_validaciones; ?></span> Val.</div>
                        <div><span style="color: #d97706;"><?php echo $total_asistencias; ?></span> Asis.</div>
                    </div>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-header"><i class="fa-solid fa-stopwatch" style="color: #6d28d9;"></i> Tiempos de Atención</div>
                <div class="kpi-body">
                    <div class="kpi-main"><span class="kpi-val"><?php echo $tiempo_promedio; ?><span style="font-size:1.4rem;">s</span></span><span class="kpi-lbl">Promedio Gral.</span></div>
                    <div class="kpi-subs">
                        <div><span style="color: #14b8a6;"><?php echo $prom_entre_totem; ?>s</span> Tót.</div>
                        <div><span style="color: #f43f5e;"><?php echo $prom_entre_ventanilla; ?>s</span> Vent.</div>
                    </div>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-header"><i class="fa-solid fa-boxes-stacked" style="color: #334155;"></i> Tickets Restantes</div>
                <div class="kpi-body">
                    <div class="kpi-main"><span class="kpi-val"><?php echo $papel_restante_total; ?></span><span class="kpi-lbl">Capacidad Total</span></div>
                    <div class="kpi-subs">
                        <div><span style="color: #059669;"><?php echo $papel_restante; ?></span> Tótem</div>
                        <div><span style="color: #eab308;"><?php echo $papel_restante_ven; ?></span> Vent.</div>
                    </div>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-header"><i class="fa-solid fa-boxes-packing" style="color: #475569;"></i> Consumo de Rollos</div>
                <div class="kpi-body">
                    <div class="kpi-main"><span class="kpi-val"><?php echo $rollos_total; ?></span><span class="kpi-lbl">Histórico Usado</span></div>
                    <div class="kpi-subs">
                        <div><span style="color: #059669;"><?php echo $rollos_totem; ?></span> Tótem</div>
                        <div><span style="color: #eab308;"><?php echo $rollos_ven; ?></span> Vent.</div>
                    </div>
                </div>
            </div>

            <div class="kpi-card">
                <div class="kpi-header"><i class="fa-regular fa-clock" style="color: #f59e0b;"></i> Flujo Horario</div>
                <div class="kpi-body">
                    <div class="kpi-main"><span class="kpi-val"><?php echo $hora_pico['h'] !== '-' ? $hora_pico['h'].':00' : '-'; ?></span><span class="kpi-lbl">Hora Pico</span></div>
                    <div class="kpi-subs">
                        <div><span style="color: #f43f5e;"><?php echo $hora_pico['c']; ?></span> Alta</div>
                        <div><span style="color: #3b82f6;"><?php echo $hora_valle['c']; ?></span> Baja</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="buscador-wrapper" style="display: flex; gap: 10px;">
            <input type="text" id="reporte-predictivo" class="buscador-input" placeholder="🔍 Buscar DNI o Nombre en TODA la base de datos (Presione ENTER)..." value="<?php echo htmlspecialchars($filtro_busqueda); ?>" onkeypress="if(event.key === 'Enter') buscarEnBase()">
            <button type="button" class="tl-btn tl-btn-primary" onclick="buscarEnBase()"><i class="fa-solid fa-magnifying-glass"></i> Buscar</button>
            <?php if(!empty($filtro_busqueda)): ?>
            <button type="button" class="tl-btn" style="background:#ef4444; color:white;" onclick="limpiarBusqueda()"><i class="fa-solid fa-xmark"></i> Limpiar</button>
            <?php endif; ?>
        </div>

        <div class="tl-table-wrap">
            <form id="form-delete" method="POST" action="">
                <input type="hidden" name="query_string" value="<?php echo htmlspecialchars($_SERVER['QUERY_STRING']); ?>">
                <input type="hidden" name="fecha_actual" value="<?php echo htmlspecialchars($fecha_inicio); ?>">
                <input type="hidden" name="id_eliminar" id="id_eliminar" value="">
                
                <table class="tl-table" id="tabla-exportar">
                    <thead>
                        <tr>
                            <?php if(in_array('modulo_reportes_eliminar', isset($_SESSION['permisos']) ? $_SESSION['permisos'] : [])): ?>
                            <th style="width: 40px; text-align: center;"><input type="checkbox" onclick="let cb = document.getElementsByName('ids[]'); for(let i=0; i<cb.length; i++) cb[i].checked = this.checked;" style="width: 18px; height: 18px; cursor: pointer; accent-color: #144973;"></th>
                            <?php endif; ?>
                            <th>Hora</th>
                            <th>Paciente</th>
                            <th>Servicio / Origen</th>
                            <th>Modo</th>
                            <th class="col-mobile-hide">Orden/Tk</th>
                            <th class="col-mobile-hide" style="text-align: center;">Tpo(s)</th>
                            <?php if(in_array('modulo_reportes_eliminar', isset($_SESSION['permisos']) ? $_SESSION['permisos'] : [])): ?>
                            <th class="col-acciones-th" style="text-align: center;">Acción</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($res_detalle && $res_detalle->num_rows > 0): ?>
                            <?php 
                            $fecha_actual_agrupacion = null;
                            while($fila = $res_detalle->fetch_assoc()): 
                                $hora_str = date("H:i", strtotime($fila['fecha_hora']));
                                $fecha_str = date("d/m/Y", strtotime($fila['fecha_hora']));
                                $modo_str = $fila['modo'] == 'VALIDACION' ? 'Validación' : 'Asistencia';
                                $tiempo_tkt = $has_tiempo ? $fila['tiempo_operacion'] : 0;
                                $orden_str = isset($fila['numero_orden']) && $fila['numero_orden'] ? $fila['numero_orden'] : '';
                                $origen_str = isset($fila['origen']) && $fila['origen'] != '' ? $fila['origen'] : 'TOTEM'; 
                                $js_args = sprintf("'%s','%s','%s','%s','%s','%s','%s','%s','%s','%s'", $fila['id'], $hora_str, $fecha_str, addslashes($fila['dni']), addslashes($fila['nombre']), addslashes($fila['servicio']), addslashes($fila['token_iofa']), $modo_str, $tiempo_tkt, addslashes($orden_str));
                                
                                if ($fecha_actual_agrupacion !== $fecha_str) {
                                    $fecha_actual_agrupacion = $fecha_str;
                                    $colsp = in_array('modulo_reportes_eliminar', isset($_SESSION['permisos']) ? $_SESSION['permisos'] : []) ? 8 : 7;
                                    echo "<tr class='fila-fecha-grupo'><td colspan='{$colsp}'><i class='fa-regular fa-calendar-check'></i> Registros del Día: {$fecha_actual_agrupacion}</td></tr>";
                                }
                            ?>
                                <tr class="fila-datos" onclick="if(event.target.tagName !== 'INPUT' && event.target.tagName !== 'BUTTON' && event.target.tagName !== 'I') mostrarTicket(<?php echo $js_args; ?>)">
                                    <?php if(in_array('modulo_reportes_eliminar', isset($_SESSION['permisos']) ? $_SESSION['permisos'] : [])): ?>
                                    <td style="text-align:center;"><input type="checkbox" name="ids[]" value="<?php echo $fila['id']; ?>" style="width: 18px; height: 18px; accent-color: #144973;"></td>
                                    <?php endif; ?>
                                    
                                    <td style="font-weight: 800; color: #475569;"><i class="fa-regular fa-clock" style="color:#94a3b8;"></i> <?php echo $hora_str; ?></td>
                                    
                                    <td>
                                        <strong style="color:#144973; font-size: 1.05rem;"><?php echo htmlspecialchars($fila['nombre']); ?></strong><br>
                                        <span style="color:#64748b; font-size:0.8rem; font-weight:600;"><i class="fa-regular fa-id-card"></i> DNI: <?php echo htmlspecialchars($fila['dni']); ?></span>
                                    </td>
                                    
                                    <td>
                                        <strong style="color:#0f172a; font-size: 0.95rem;"><?php echo htmlspecialchars($fila['servicio']); ?></strong><br>
                                        <span style="color:#0ea5e9; font-size:0.8rem; font-weight:800;"><?php echo $origen_str; ?></span>
                                    </td>
                                    
                                    <td><span class="tl-estado"><?php echo $modo_str; ?></span></td>
                                    
                                    <td class="col-mobile-hide" style="font-weight: 900; color: #16a34a; font-family: monospace; font-size:1.05rem;">
                                        <?php echo ($modo_str == 'Validación') ? htmlspecialchars($fila['token_iofa']) : htmlspecialchars($orden_str); ?>
                                    </td>
                                    
                                    <td class="col-mobile-hide" style="text-align: center; color:#64748b; font-weight:bold;"><?php echo $tiempo_tkt; ?>s</td>
                                    
                                    <?php if(in_array('modulo_reportes_eliminar', isset($_SESSION['permisos']) ? $_SESSION['permisos'] : [])): ?>
                                    <td style="text-align:center;">
                                        <button type="button" class="tl-btn-icon icon-del" onclick="borrarIndividual('<?php echo $fila['id']; ?>')"><i class="fa-solid fa-trash"></i></button>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="8" style="text-align: center; padding: 40px; color: #64748b; font-weight: 700;"><i class="fa-solid fa-inbox" style="font-size:2rem; margin-bottom:10px; color:#cbd5e1; display:block;"></i> No se encontraron registros.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>

        <?php if(isset($total_paginas) && $total_paginas > 1): ?>
        <div style="display: flex; justify-content: center; gap: 8px; margin-top: 15px; flex-wrap: wrap; padding-bottom: 20px;">
            <?php 
                $query_params = $_GET;
                for($i = 1; $i <= $total_paginas; $i++): 
                    $query_params['pagina'] = $i;
                    $link = 'reporte_general.php?' . http_build_query($query_params);
                    $activo = ($i == $pagina_actual) ? 'background: #144973; color: white;' : 'background: white; color: #475569; border: 1px solid #cbd5e1;';
            ?>
                <a href="<?php echo $link; ?>" style="padding: 8px 14px; border-radius: 8px; font-weight: 900; text-decoration: none; font-size: 0.9rem; transition: background 0.2s; <?php echo $activo; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
    let urlBasePdf = '';
    document.addEventListener("DOMContentLoaded", function() {
        let btnPdf = document.querySelector('a[href*="reporte_general_pdf.php"]');
        if (btnPdf) urlBasePdf = btnPdf.getAttribute('href');
    });

    function buscarEnBase() {
        let texto = document.getElementById('reporte-predictivo').value.trim();
        let params = new URLSearchParams(window.location.search);
        if (texto !== '') {
            params.set('busqueda', texto);
        } else {
            params.delete('busqueda');
        }
        params.set('pagina', '1');
        window.location.href = 'reporte_general.php?' + params.toString();
    }

    function limpiarBusqueda() {
        let params = new URLSearchParams(window.location.search);
        params.delete('busqueda');
        params.set('pagina', '1');
        window.location.href = 'reporte_general.php?' + params.toString();
    }

    function abrirModoAdmin() {
        Swal.fire({
            title: 'Modo Administrador',
            html: `
                <div style="display:flex; flex-direction:column; gap:10px; margin-top: 15px;">
                    <button type="button" style="background: #f59e0b; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 1rem;" onclick="Swal.close(); borrarSeleccionados();"><i class="fa-solid fa-check-double"></i> Borrar Seleccionados</button>
                    <button type="button" style="background: #ef4444; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 1rem;" onclick="Swal.close(); borrarTodoFiltrado();"><i class="fa-solid fa-trash-can"></i> Vaciar Tabla</button>
                </div>
            `,
            showConfirmButton: false, showCloseButton: true
        });
    }

    function abrirFiltros() {
        Swal.fire({
            title: 'Filtros Unificados',
            html: `
                <div style="display:flex; flex-direction:column; gap:10px; text-align:left; font-size:0.9rem;">
                    <div><label style="font-weight:700; color:#475569;">Origen de Atención:</label>
                        <select id="swal-origen" class="swal2-input" style="margin:0; height:40px; border-radius:10px;">
                            <option value="todos" <?php echo ($filtro_origen == 'todos') ? 'selected' : ''; ?>>Toda la Clínica (Tótem + Ventanilla)</option>
                            <option value="totem" <?php echo ($filtro_origen == 'totem') ? 'selected' : ''; ?>>Sólo Tótem </option>
                            <option value="ventanilla" <?php echo ($filtro_origen == 'ventanilla') ? 'selected' : ''; ?>>Sólo Ventanilla </option>
                        </select>
                    </div>
                    <div><label style="font-weight:700; color:#475569;">Desde:</label><input type="date" id="swal-f1" value="<?php echo $fecha_inicio; ?>" class="swal2-input" style="margin:0; height:40px; border-radius:10px;"></div>
                    <div><label style="font-weight:700; color:#475569;">Hasta:</label><input type="date" id="swal-f2" value="<?php echo $fecha_fin; ?>" class="swal2-input" style="margin:0; height:40px; border-radius:10px;"></div>
                    <div><label style="font-weight:700; color:#475569;">Hora Min:</label><input type="time" id="swal-h1" value="<?php echo $hora_inicio; ?>" class="swal2-input" style="margin:0; height:40px; border-radius:10px;"></div>
                    <div><label style="font-weight:700; color:#475569;">Hora Max:</label><input type="time" id="swal-h2" value="<?php echo $hora_fin; ?>" class="swal2-input" style="margin:0; height:40px; border-radius:10px;"></div>
                    <div><label style="font-weight:700; color:#475569;">DNI / Nombre:</label><input type="text" id="swal-dni" value="<?php echo htmlspecialchars($filtro_busqueda); ?>" class="swal2-input" style="margin:0; height:40px; border-radius:10px;"></div>
                    <div><label style="font-weight:700; color:#475569;">Token IOFA:</label><input type="text" id="swal-tk" value="<?php echo htmlspecialchars($filtro_token); ?>" class="swal2-input" style="margin:0; height:40px; border-radius:10px;"></div>
                    <div><label style="font-weight:700; color:#475569;">Servicio:</label>
                        <select id="swal-serv" class="swal2-input" style="margin:0; height:40px; border-radius:10px;">
                            <option value="">TODOS</option>
                            <?php if($res_lista_servicios): $res_lista_servicios->data_seek(0); while($s = $res_lista_servicios->fetch_assoc()): ?>
                                <option value="<?php echo htmlspecialchars($s['servicio']); ?>" <?php if($filtro_servicio == $s['servicio']) echo 'selected'; ?>><?php echo htmlspecialchars($s['servicio']); ?></option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>
                </div>
            `,
            confirmButtonColor: '#144973', confirmButtonText: '<i class="fa-solid fa-filter"></i> Aplicar',
            showCancelButton: true, cancelButtonText: 'Limpiar Todo',
            preConfirm: () => {
                let params = new URLSearchParams();
                params.set('origen', document.getElementById('swal-origen').value);
                params.set('fecha_inicio', document.getElementById('swal-f1').value);
                params.set('fecha_fin', document.getElementById('swal-f2').value);
                if(document.getElementById('swal-h1').value) params.set('hora_inicio', document.getElementById('swal-h1').value);
                if(document.getElementById('swal-h2').value) params.set('hora_fin', document.getElementById('swal-h2').value);
                if(document.getElementById('swal-dni').value) params.set('busqueda', document.getElementById('swal-dni').value);
                if(document.getElementById('swal-tk').value) params.set('token', document.getElementById('swal-tk').value);
                if(document.getElementById('swal-serv').value) params.set('servicio', document.getElementById('swal-serv').value);
                return params.toString();
            }
        }).then((result) => {
            if (result.isConfirmed) { window.location.href = 'reporte_general.php?' + result.value; }
            else if (result.dismiss === Swal.DismissReason.cancel) { window.location.href = 'reporte_general.php'; }
        });
    }

    function abrirStatsTiempos() {
        const lentos = <?php echo json_encode($lentos); ?>;
        const rapidos = <?php echo json_encode($rapidos); ?>;
        const pVal = <?php echo $prom_modo['VALIDACION'] ?? 0; ?>;
        const pAsis = <?php echo $prom_modo['ASISTENCIA'] ?? 0; ?>;
        
        let htmlLentos = lentos.map((i, idx) => `<div style="display:flex; justify-content:space-between; border-bottom:1px solid #e2e8f0; padding:5px 0;"><span>${idx+1}. ${i.nombre.substring(0,15)}</span><strong style="color:#ef4444;">${i.tiempo_operacion} seg</strong></div>`).join('');
        let htmlRapidos = rapidos.map((i, idx) => `<div style="display:flex; justify-content:space-between; border-bottom:1px solid #e2e8f0; padding:5px 0;"><span>${idx+1}. ${i.nombre.substring(0,15)}</span><strong style="color:#10b981;">${i.tiempo_operacion} seg</strong></div>`).join('');

        if(!htmlLentos) htmlLentos = 'Sin datos aún';
        if(!htmlRapidos) htmlRapidos = 'Sin datos aún';

        Swal.fire({
            title: '⏱️ Estadísticas de Tiempos',
            html: `
                <div style="text-align:left; font-size:0.9rem;">
                    <h4 style="margin: 0 0 10px 0; color:#144973;">Promedios por Operación</h4>
                    <div style="display:flex; justify-content:space-around; margin-bottom:20px; background:#f8fafc; padding:10px; border-radius:8px; border: 1px solid #e2e8f0;">
                        <div style="text-align:center; color:#64748b;"><b>Validación</b><br><span style="font-size:1.5rem; font-weight:900; color:#0f172a;">${pVal}s</span></div>
                        <div style="text-align:center; color:#64748b;"><b>Asistencia</b><br><span style="font-size:1.5rem; font-weight:900; color:#0f172a;">${pAsis}s</span></div>
                    </div>
                    <h4 style="margin: 0 0 10px 0; color:#ef4444;">🐌 Los 5 más Lentos</h4>
                    <div style="margin-bottom:20px;">${htmlLentos}</div>
                    <h4 style="margin: 0 0 10px 0; color:#10b981;">⚡ Los 5 más Rápidos</h4>
                    <div>${htmlRapidos}</div>
                </div>
            `,
            confirmButtonColor: '#144973',
            confirmButtonText: 'Cerrar'
        });
    }

    function mostrarTicket(id, hora, fecha, dni, nombre, servicio, token, modo, tiempo, orden) {
        let esVal = modo === 'Validación' || modo === 'VALIDACION' || modo === 'VAL';
        let textoQr = "";
        
        if (esVal) {
            textoQr = `https://federicogonzalez.net/actis/ticket_publico_validacion.php?token=${encodeURIComponent(token)}&dni=${encodeURIComponent(dni)}&nombre=${encodeURIComponent(nombre)}&servicio=${encodeURIComponent(servicio)}&fecha=${encodeURIComponent(fecha)}&hora=${encodeURIComponent(hora)}`;
        } else {
            textoQr = `https://federicogonzalez.net/actis/ticket_publico_asistencia.php?servicio=${encodeURIComponent(servicio)}&orden=${encodeURIComponent(orden)}&dni=${encodeURIComponent(dni)}&nombre=${encodeURIComponent(nombre)}&token=${encodeURIComponent(token)}&fecha=${encodeURIComponent(fecha)}&hora=${encodeURIComponent(hora)}`;
        }

        let tiempoColor = parseFloat(tiempo) > 15 ? '#ef4444' : '#10b981';

        Swal.fire({
            title: '',
            html: `
                <div style="text-align: center; font-family: 'Poppins', sans-serif; color: #1e293b;">
                    <div style="margin-bottom: 10px;">
                        <img src="https://federicogonzalez.net/actis/img/osfa.svg" style="height: 32px; margin-bottom: 4px; border-radius: 6px;">
                        <h3 style="font-size: 1.1rem; font-weight: 900; margin: 0; color: #144973; letter-spacing: 0.5px;">POLICLÍNICA GENERAL ACTIS</h3>
                        <p style="font-size: 0.75rem; color: #64748b; margin: 2px 0 0 0;">
                            <i class="fa-regular fa-calendar-days"></i> ${fecha} &nbsp;&nbsp; <i class="fa-regular fa-clock"></i> ${hora} hs
                        </p>
                    </div>

                    <div style="margin-bottom: 10px;">
                        <span style="background: ${esVal ? '#eff6ff' : '#fff7ed'}; color: ${esVal ? '#144973' : '#c2410c'}; padding: 4px 10px; border-radius: 30px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; border: 1px solid ${esVal ? '#bfdbfe' : '#ffedd5'};">
                            ${esVal ? '<i class="fa-solid fa-circle-check"></i> Turno Programado (Validación)' : '<i class="fa-solid fa-users"></i> Demanda Espontánea (Asistencia)'}
                        </span>
                    </div>

                    <div style="background: #f8fafc; border: 2px dashed #cbd5e1; border-radius: 10px; padding: 10px; margin-bottom: 10px; position: relative; text-align: left;">
                        <div style="position: absolute; top: 8px; right: 8px; background: ${tiempoColor}15; color: ${tiempoColor}; padding: 2px 6px; border-radius: 6px; font-size: 0.65rem; font-weight: 800;">
                            ⏱️ ${tiempo}s
                        </div>
                        
                        <div style="margin-bottom: 8px;">
                            <small style="font-size: 0.6rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 1px;">Servicio Destino</small>
                            <strong style="font-size: 0.95rem; color: #0f172a; display: flex; align-items: center; gap: 4px;">
                                <i class="fa-solid fa-stethoscope" style="color: #144973;"></i> ${servicio}
                            </strong>
                        </div>

                        <div style="margin-bottom: 8px; border-top: 1px dashed #e2e8f0; padding-top: 8px;">
                            <small style="font-size: 0.6rem; font-weight: 800; color: #94a3b8; text-transform: uppercase; display: block; margin-bottom: 1px;">Paciente Afiliado</small>
                            <strong style="font-size: 0.9rem; color: #0f172a; display: block;">${nombre}</strong>
                            <span style="font-size: 0.8rem; color: #475569; display: block; margin-top: 1px;">
                                <i class="fa-regular fa-id-card" style="color: #94a3b8;"></i> DNI: ${dni}
                            </span>
                        </div>

                        <div style="background: white; border: 1px solid #e2e8f0; padding: 8px; border-radius: 8px; text-align: center; margin-top: 8px;">
                            <small style="font-size: 0.6rem; font-weight: 800; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 2px;">
                                ${esVal ? 'Código Token IOSFA' : 'Número de Orden'}
                            </small>
                            <span style="font-size: 1.3rem; font-weight: 900; color: #16a34a; letter-spacing: 2px; font-family: monospace;">
                                ${esVal ? token : orden}
                            </span>
                        </div>
                        ${!esVal ? `
                        <div style="background: white; border: 1px solid #e2e8f0; padding: 6px; border-radius: 8px; text-align: center; margin-top: 6px;">
                            <small style="font-size: 0.6rem; font-weight: 800; color: #64748b; text-transform: uppercase; display: block; margin-bottom: 1px;">Token IOSFA</small>
                            <span style="font-size: 0.95rem; font-weight: 900; color: #144973; font-family: monospace;">${token}</span>
                        </div>
                        ` : ''}
                    </div>

                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; margin-bottom: 10px; background: white; padding: 8px; border-radius: 10px; border: 1px solid #e2e8f0; max-width: 120px; margin-left: auto; margin-right: auto;">
                        <div id="modal-qr-render"></div>
                        <small style="font-size: 0.6rem; color: #94a3b8; font-weight: 800; text-transform: uppercase; margin-top: 4px;">QR Comprobante</small>
                    </div>

                    <div style="margin-top: 10px;">
                        <a href="${textoQr}" target="_blank" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px; width: 100%; max-width: 250px; background: #144973; color: white; text-decoration: none; padding: 10px 14px; border-radius: 8px; font-weight: 800; font-size: 0.8rem; box-shadow: 0 4px 12px rgba(20, 73, 115, 0.3);">
                            <i class="fa-solid fa-arrow-up-right-from-square"></i> Verificar Veracidad Online
                        </a>
                    </div>
                </div>
            `,
            showCloseButton: true,
            showConfirmButton: false,
            width: '420px',
            padding: '10px 10px 15px 10px',
            background: '#ffffff',
            didOpen: () => {
                new QRCode(document.getElementById("modal-qr-render"), {
                    text: textoQr,
                    width: 90,
                    height: 90,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.L
                });
            }
        });
    }

    function borrarIndividual(id) {
        document.getElementById('id_eliminar').value = id; 
        document.getElementById('form-delete').insertAdjacentHTML('beforeend', '<input type="hidden" name="eliminar_uno" value="1">'); 
        document.getElementById('form-delete').submit();
    }
</script>
<?php require_once 'includes/footer.php'; ?>