<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }
require_once 'includes/conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');

// Verificar si existe la columna tiempo_operacion (Blindaje contra errores)
$col_check = $conexion->query("SHOW COLUMNS FROM estadisticas_totem LIKE 'tiempo_operacion'");
$has_tiempo = ($col_check && $col_check->num_rows > 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['eliminar_uno'])) { $conexion->query("DELETE FROM estadisticas_totem WHERE id = ".(int)$_POST['id_eliminar']); }
    if (isset($_POST['eliminar_seleccionados']) && !empty($_POST['ids'])) { $conexion->query("DELETE FROM estadisticas_totem WHERE id IN (".implode(',', array_map('intval', $_POST['ids'])).")"); }
    if (isset($_POST['eliminar_todos_fecha'])) { $conexion->query("DELETE FROM estadisticas_totem WHERE DATE(fecha_hora) = '".$conexion->real_escape_string($_POST['fecha_actual'])."'"); }
    header("Location: reporte_totem.php?".$_POST['query_string']); exit;
}

require_once 'includes/header.php';

$fecha_inicio = isset($_GET['fecha_inicio']) ? $conexion->real_escape_string($_GET['fecha_inicio']) : date('Y-m-d');
$fecha_fin = isset($_GET['fecha_fin']) ? $conexion->real_escape_string($_GET['fecha_fin']) : date('Y-m-d');
$filtro_servicio = isset($_GET['servicio']) ? $conexion->real_escape_string($_GET['servicio']) : '';
$ver_unidos = isset($_GET['ver_unidos']) ? (int)$_GET['ver_unidos'] : 0;
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

// Lógica de separación: Si NO estamos uniendo, filtramos SOLO los del Tótem (tienen número de orden)
if ($ver_unidos !== 1) { 
    $where_clauses[] = "(numero_orden IS NOT NULL AND numero_orden != '')"; 
}

$sql_where = implode(' AND ', $where_clauses);

// KPIs Básicos
$total_validaciones = ($res = $conexion->query("SELECT COUNT(*) as t FROM estadisticas_totem WHERE modo = 'VALIDACION' AND $sql_where")) ? $res->fetch_assoc()['t'] : 0;
$total_asistencias = ($res = $conexion->query("SELECT COUNT(*) as t FROM estadisticas_totem WHERE modo = 'ASISTENCIA' AND $sql_where")) ? $res->fetch_assoc()['t'] : 0;
$total_tickets = $total_validaciones + $total_asistencias;

// KPIs Avanzados
$hora_pico = ($res = $conexion->query("SELECT HOUR(fecha_hora) as h, COUNT(*) as c FROM estadisticas_totem WHERE $sql_where GROUP BY h ORDER BY c DESC LIMIT 1")) && $res->num_rows > 0 ? $res->fetch_assoc() : ['h'=>'-','c'=>0];
$hora_valle = ($res = $conexion->query("SELECT HOUR(fecha_hora) as h, COUNT(*) as c FROM estadisticas_totem WHERE $sql_where GROUP BY h ORDER BY c ASC LIMIT 1")) && $res->num_rows > 0 ? $res->fetch_assoc() : ['h'=>'-','c'=>0];
$tiempo_promedio = 0;
if($has_tiempo && $total_tickets > 0) {
    $tiempo_promedio = ($res = $conexion->query("SELECT AVG(tiempo_operacion) as prom FROM estadisticas_totem WHERE $sql_where")) ? round($res->fetch_assoc()['prom'], 2) : 0;
}

$res_papel = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'tickets_impresos'");
$tickets_impresos = ($res_papel && $res_papel->num_rows > 0) ? (int)$res_papel->fetch_assoc()['estado'] : 0;
$res_cap = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'capacidad_rollo'");
$capacidad_rollo = ($res_cap && $res_cap->num_rows > 0) ? (int)$res_cap->fetch_assoc()['estado'] : 120;
$papel_restante = $capacidad_rollo - $tickets_impresos;

$res_duplicados = $conexion->query("SELECT dni, nombre, COUNT(*) as c FROM estadisticas_totem WHERE $sql_where GROUP BY dni HAVING c > 1 ORDER BY c DESC");

// Paginación 50 registros
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

// --- NUEVAS ESTADISTICAS DE TIEMPO ---
$res_lentos = $conexion->query("SELECT dni, nombre, tiempo_operacion, modo FROM estadisticas_totem WHERE $sql_where AND tiempo_operacion > 0 ORDER BY tiempo_operacion DESC LIMIT 5");
$lentos = []; if($res_lentos) while($r = $res_lentos->fetch_assoc()) $lentos[] = $r;

$res_rapidos = $conexion->query("SELECT dni, nombre, tiempo_operacion, modo FROM estadisticas_totem WHERE $sql_where AND tiempo_operacion > 0 ORDER BY tiempo_operacion ASC LIMIT 5");
$rapidos = []; if($res_rapidos) while($r = $res_rapidos->fetch_assoc()) $rapidos[] = $r;

$res_prom_modo = $conexion->query("SELECT modo, AVG(tiempo_operacion) as prom FROM estadisticas_totem WHERE $sql_where AND tiempo_operacion > 0 GROUP BY modo");
$prom_modo = ['VALIDACION' => 0, 'ASISTENCIA' => 0];
if($res_prom_modo) while($r = $res_prom_modo->fetch_assoc()) $prom_modo[$r['modo']] = round($r['prom'], 2);
?>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
    /* Estructura adoptada del modelo pacientes_listar.php */
    .app-container { display: flex; flex-direction: column; gap: 15px; padding: 5px; font-family: 'Poppins', sans-serif; }
    
    .header-filtros { background: white; padding: 15px; border-radius: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; display: flex; flex-direction: column; gap: 15px; }
    .header-filtros h2 { margin: 0; font-size: 1.5rem; font-weight: 800; color: #0f172a; display: flex; align-items: center; gap: 8px; }
    
    .botones-arriba { display: flex; gap: 8px; flex-wrap: wrap; }
    .btn-action { flex: 1; min-width: 100px; padding: 12px; border-radius: 12px; font-weight: 800; font-size: 0.85rem; border: none; cursor: pointer; color: white; display: flex; justify-content: center; align-items: center; gap: 6px; transition: transform 0.2s; text-decoration: none; text-align: center; }
    .btn-action:active { transform: scale(0.95); }
    
    .bg-primary { background: #2563eb; } 
    .bg-success { background: #10b981; } 
    .bg-purple { background: #8b5cf6; } 
    .bg-warning { background: #f59e0b; }
    .bg-danger { background: #ef4444; }
    .bg-dark { background: #1e293b; }

    /* Rediseño de los KPIs */
    .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 12px; margin-bottom: 5px; }
    .kpi-card { background: white; border-radius: 16px; padding: 16px; border: 1px solid #f1f5f9; box-shadow: 0 2px 4px rgba(0,0,0,0.02); display: flex; flex-direction: column; position: relative; overflow: hidden; }
    .kpi-title { font-size: 0.75rem; font-weight: 800; color: #64748b; text-transform: uppercase; margin-bottom: 4px; z-index: 1; }
    .kpi-value { font-size: 1.8rem; font-weight: 900; margin: 0; z-index: 1; line-height: 1.1; }
    .kpi-bg-icon { position: absolute; right: -15px; bottom: -15px; font-size: 5rem; opacity: 0.04; z-index: 0; }

    .kpi-ext-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-bottom: 5px; }
    .kpi-ext { background: #f8fafc; border: 1px solid #f1f5f9; padding: 12px; border-radius: 14px; text-align: center; }
    .kpi-ext h6 { font-size: 0.7rem; color: #64748b; font-weight: 800; text-transform: uppercase; margin: 0 0 6px 0; }
    .kpi-ext p { font-size: 1.1rem; color: #0f172a; font-weight: 900; margin: 0; }

    /* Contenedor de la Tabla Principal */
    .table-container { background: white; border-radius: 20px; padding: 15px; border: 1px solid #e2e8f0; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.03); }
    .table-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; }
    .table-header h4 { font-size: 1.1rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 8px; }

    .admin-box { background: #fef2f2; border: 1px solid #fecaca; padding: 12px; border-radius: 12px; margin-bottom: 15px; display: flex; gap: 10px; flex-wrap: wrap; align-items: center; }
    
    .table-responsive { width: 100%; overflow-x: auto; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; padding: 12px 10px; font-size: 0.8rem; font-weight: 800; color: #64748b; border-bottom: 2px solid #e2e8f0; text-transform: uppercase; }
    td { padding: 12px 10px; font-size: 0.9rem; color: #1e293b; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
    tr:hover { background-color: #f8fafc; }
    
    .badge-modo { font-size: 0.75rem; font-weight: 800; background: #f1f5f9; padding: 4px 8px; border-radius: 6px; }

    @media (min-width: 768px) {
        .header-filtros { flex-direction: row; justify-content: space-between; align-items: center; }
        .botones-arriba { flex: 1; justify-content: flex-end; }
        .kpi-grid { grid-template-columns: repeat(4, 1fr); }
    }

    /* Adaptación móvil estricta y corrección de superposición */
    @media (max-width: 992px) {
        .table-container { background: transparent; padding: 0; border: none; box-shadow: none; }
        .table-responsive table, .table-responsive thead, .table-responsive tbody { display: block; width: 100%; }
        .table-responsive thead { display: none; }
        
        .table-responsive tr { 
            background: white; border-radius: 12px; padding: 10px; border: 1px solid #f1f5f9; 
            box-shadow: 0 2px 4px rgba(0,0,0,0.02); margin-bottom: 8px; display: grid; 
            grid-template-columns: auto auto 1fr auto; gap: 6px; align-items: center; cursor: pointer; position: relative;
        }
        
        .table-responsive td { padding: 0 !important; border: none !important; display: flex; align-items: center; justify-content: flex-start; }
        
        .td-hora { grid-column: 1 / -1; font-size: 0.8rem; font-weight: 800; color: #64748b; margin-bottom: -4px; }
        .td-nombre { grid-column: 1 / -1; font-size: 1.05rem; font-weight: 900; color: #0f172a; line-height: 1.1; margin-right: 25px; }
        .td-dni { grid-column: 1 / -1; font-size: 0.85rem; font-weight: 700; color: #475569; margin-bottom: 4px; }
        
        /* Badges y botones en la misma línea compactada */
        .td-serv { grid-column: 1; font-size: 0.7rem; font-weight: 800; color: #2563eb; background: #eff6ff; padding: 4px 6px; border-radius: 6px; width: fit-content; text-transform: uppercase; }
        .td-serv i { display: none; } /* Ocultar icono para ganar espacio */
        .td-modo { grid-column: 2; width: fit-content; }
        .badge-modo { font-size: 0.65rem; padding: 4px 6px; }
        .td-orden { grid-column: 3; justify-content: flex-end; padding-right: 5px !important; }
        .td-accion { grid-column: 4; justify-content: flex-end; }
        .td-accion button { padding: 6px 12px !important; font-size: 0.8rem !important; }

        .td-check { position: absolute; top: 10px; right: 10px; width: auto !important; z-index: 10; }
        
        .td-oculto-mobile { display: none !important; }
    }


    .ticket-virtual { background: #fdfdfd; border: 2px dashed #94a3b8; padding: 20px; border-radius: 5px; text-align: center; color: #0f172a; width: 100%; max-width: 300px; margin: 0 auto; box-shadow: 0 10px 20px rgba(0,0,0,0.1); }
</style>
<div class="app-container">
    <div class="header-filtros">
        <h2><i class="fa-solid fa-chart-pie" style="color:#2563eb;"></i> Analytics Tótem</h2>
        <div style="display:flex; flex-direction:column; gap:10px; width:100%;">
            <div class="botones-arriba">
                <button type="button" class="btn-action bg-primary" onclick="abrirFiltros()"><i class="fa-solid fa-filter"></i> Filtros</button>
                <a href="reporte_totem_pdf.php?<?php echo $query_string_pdf; ?>" target="_blank" class="btn-action bg-success"><i class="fa-solid fa-file-pdf"></i> PDF</a>
                <button type="button" class="btn-action bg-success" onclick="exportarCSV()"><i class="fa-solid fa-file-csv"></i> CSV</button>
                <button type="button" class="btn-action bg-purple" onclick="abrirStatsTiempos()"><i class="fa-solid fa-stopwatch"></i> Tiempos</button>
            </div>
        </div>
    </div>

    <div class="kpi-grid">
        <div class="kpi-card" style="border-top: 4px solid #0284c7;">
            <i class="fa-solid fa-file-signature kpi-bg-icon" style="color: #0284c7;"></i>
            <div class="kpi-title">Validaciones</div>
            <div class="kpi-value" style="color: #0284c7;"><?php echo $total_validaciones; ?></div>
        </div>
        <div class="kpi-card" style="border-top: 4px solid #d97706;">
            <i class="fa-solid fa-users kpi-bg-icon" style="color: #d97706;"></i>
            <div class="kpi-title">Asistencias</div>
            <div class="kpi-value" style="color: #d97706;"><?php echo $total_asistencias; ?></div>
        </div>
        <div class="kpi-card" style="border-top: 4px solid #059669;">
            <i class="fa-solid fa-receipt kpi-bg-icon" style="color: #059669;"></i>
            <div class="kpi-title">Papel Tótem</div>
            <div class="kpi-value" style="color: #059669;"><?php echo $papel_restante; ?> <span style="font-size: 1rem;">tkts</span></div>
        </div>
        <div class="kpi-card" style="border-top: 4px solid #6d28d9;">
            <i class="fa-solid fa-clock kpi-bg-icon" style="color: #6d28d9;"></i>
            <div class="kpi-title">Tiempo Prom.</div>
            <div class="kpi-value" style="color: #6d28d9;"><?php echo $tiempo_promedio; ?> <span style="font-size: 1rem;">s</span></div>
        </div>
    </div>

    <div class="kpi-ext-grid">
        <div class="kpi-ext">
            <h6>🔥 Hora Pico</h6>
            <p><?php echo $hora_pico['h']; ?>:00 <span style="font-size:0.8rem; color:#ef4444;">(<?php echo $hora_pico['c']; ?> turnos)</span></p>
        </div>
        <div class="kpi-ext">
            <h6>💤 Hora Valle</h6>
            <p><?php echo $hora_valle['h']; ?>:00 <span style="font-size:0.8rem; color:#3b82f6;">(<?php echo $hora_valle['c']; ?> turnos)</span></p>
        </div>
    </div>

    <div class="table-container">
        <div class="table-header" style="flex-wrap: wrap; gap: 10px;">
            <h4><i class="fa-solid fa-list-check" style="color:#64748b;"></i> Registros del Tótem</h4>
            <button type="button" style="background: #1e293b; color: white; border: none; padding: 6px 12px; border-radius: 8px; font-size: 0.8rem; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px;" onclick="abrirModoAdmin()"><i class="fa-solid fa-shield-halved"></i> Modo Admin</button>
        </div>
        
        <?php if($res_duplicados && $res_duplicados->num_rows > 0): ?>
        <div style="background: #fef2f2; border: 1px solid #fecaca; padding: 10px; border-radius: 10px; margin-bottom: 15px; color:#dc2626; font-weight:800; font-size:0.85rem; display:flex; align-items:center; gap: 6px;">
            <i class="fa-solid fa-triangle-exclamation"></i> ¡Hay DNIs con múltiples tickets hoy!
        </div>
        <?php endif; ?>

        <div style="margin-bottom: 15px;">
            <input type="text" id="reporte-predictivo" class="swal2-input" placeholder="🔍 Buscador predictivo en pantalla (Filtra DNI, Paciente, Servicio o Modo)..." onkeyup="filtrarReporteVivo()" style="width: 100%; margin: 0; box-sizing: border-box; border-radius: 12px; padding: 12px 16px; font-weight: 600; border: 2px solid #e2e8f0; background: #f8fafc; outline: none; font-family: 'Poppins', sans-serif;">
        </div>

        <div class="table-responsive">
            <form id="form-delete" method="POST" action="">
                <input type="hidden" name="query_string" value="<?php echo htmlspecialchars($_SERVER['QUERY_STRING']); ?>">
                <input type="hidden" name="fecha_actual" value="<?php echo htmlspecialchars($fecha_inicio); ?>">
                <input type="hidden" name="id_eliminar" id="id_eliminar" value="">
                <table id="tabla-exportar">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;"><input type="checkbox" onclick="let cb = document.getElementsByName('ids[]'); for(let i=0; i<cb.length; i++) cb[i].checked = this.checked;" style="width: 18px; height: 18px; cursor: pointer; accent-color: #2563eb;"></th>
                            <th>Hora</th>
                            <th>DNI</th>
                            <th>Paciente</th>
                            <th>Servicio</th>
                            <th>Modo</th>
                            <th>Orden</th>
                            <th>Token</th>
                            <th style="text-align: center;">Tpo(s)</th>
                            <th style="text-align: center;">Acción</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($res_detalle && $res_detalle->num_rows > 0): ?>
                            <?php while($fila = $res_detalle->fetch_assoc()): 
                                $hora_str = date("H:i", strtotime($fila['fecha_hora']));
                                $fecha_str = date("d/m/Y", strtotime($fila['fecha_hora']));
                                $modo_str = $fila['modo'] == 'VALIDACION' ? 'Validación' : 'Asistencia';
                                $tiempo_tkt = $has_tiempo ? $fila['tiempo_operacion'] : 0;
                                $orden_str = isset($fila['numero_orden']) && $fila['numero_orden'] ? $fila['numero_orden'] : '-';
                                $js_args = sprintf("'%s','%s','%s','%s','%s','%s','%s','%s','%s','%s'", $fila['id'], $hora_str, $fecha_str, addslashes($fila['dni']), addslashes($fila['nombre']), addslashes($fila['servicio']), addslashes($fila['token_iofa']), $modo_str, $tiempo_tkt, addslashes($orden_str));
                            ?>
                                <tr onclick="if(event.target.tagName !== 'INPUT' && event.target.tagName !== 'BUTTON' && event.target.tagName !== 'I') mostrarTicket(<?php echo $js_args; ?>)">
                                    <td class="td-check"><input type="checkbox" name="ids[]" value="<?php echo $fila['id']; ?>" style="width: 18px; height: 18px; accent-color: #2563eb;"></td>
                                    <td class="td-hora"><i class="fa-regular fa-clock"></i> <?php echo $hora_str; ?> hs</td>
                                    <td class="td-dni"><i class="fa-regular fa-id-card" style="color: #94a3b8;"></i> <?php echo htmlspecialchars($fila['dni']); ?></td>
                                    <td class="td-nombre"><strong><?php echo htmlspecialchars($fila['nombre']); ?></strong></td>
                                    <td class="td-serv"><i class="fa-solid fa-stethoscope" style="color: #64748b;"></i> <?php echo htmlspecialchars($fila['servicio']); ?></td>
                                    <td class="td-modo"><span class="badge-modo"><?php echo $modo_str; ?></span></td>
                                    <td style="font-weight: 900; color: #16a34a;"><?php echo htmlspecialchars($orden_str); ?></td>
                                    <td class="td-oculto-mobile" style="font-weight: 800;"><?php echo htmlspecialchars($fila['token_iofa']); ?></td>
                                    <td class="td-oculto-mobile" style="text-align: center; color:#64748b; font-weight:bold;"><?php echo $tiempo_tkt; ?>s</td>
                                    <td class="td-accion">
                                        <button type="button" style="background: #ef4444; color: white; border: none; padding: 8px 14px; border-radius: 8px; font-size: 0.9rem; cursor: pointer; flex: none;" onclick="borrarIndividual('<?php echo $fila['id']; ?>')"><i class="fa-solid fa-trash"></i></button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" style="text-align: center; padding: 40px 20px; background: white; border-radius: 16px; border: 2px dashed #cbd5e1; color: #64748b; font-weight: 700;">
                                    <i class="fa-solid fa-file-excel" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 15px; display: block;"></i>
                                    No se encontraron registros.
                                </td>
                            </tr>
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
                    $link = 'reporte_totem.php?' . http_build_query($query_params);
                    $activo = ($i == $pagina_actual) ? 'background: #2563eb; color: white;' : 'background: white; color: #475569; border: 1px solid #cbd5e1;';
            ?>
                <a href="<?php echo $link; ?>" style="padding: 6px 14px; border-radius: 8px; font-weight: 900; text-decoration: none; font-size: 0.9rem; <?php echo $activo; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

    </div>
</div>
<script>
    let urlBasePdf = '';

    document.addEventListener("DOMContentLoaded", function() {
        let btnPdf = document.querySelector('a[href*="reporte_totem_pdf.php"]');
        if (btnPdf) {
            urlBasePdf = btnPdf.getAttribute('href');
        }
    });

    function filtrarReporteVivo() {
        let query = document.getElementById('reporte-predictivo').value.trim().toUpperCase();
        let tabla = document.getElementById('tabla-exportar');
        if (!tabla) return;
        
        let filas = tabla.querySelectorAll('tbody tr');
        
        filas.forEach(fila => {
            if(fila.cells.length <= 1) return;
            
            let textoFila = fila.innerText.toUpperCase();
            if (textoFila.indexOf(query) > -1) {
                fila.style.display = '';
            } else {
                fila.style.display = 'none';
            }
        });

        let btnPdf = document.querySelector('a[href*="reporte_totem_pdf.php"]');
        if (btnPdf && urlBasePdf !== '') {
            if (query !== '') {
                let conector = urlBasePdf.includes('?') ? '&' : '?';
                btnPdf.setAttribute('href', urlBasePdf + conector + 'busqueda=' + encodeURIComponent(query));
            } else {
                btnPdf.setAttribute('href', urlBasePdf);
            }
        }
    }

    function abrirModoAdmin() {
        Swal.fire({
            title: 'Modo Administrador',
            html: `
                <div style="display:flex; flex-direction:column; gap:10px; margin-top: 15px;">
                    <button type="button" style="background: #f59e0b; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 1rem; display: flex; align-items: center; justify-content: center; gap: 8px;" onclick="Swal.close(); borrarSeleccionados();"><i class="fa-solid fa-check-double"></i> Borrar Seleccionados</button>
                    <button type="button" style="background: #ef4444; color: white; border: none; padding: 12px; border-radius: 8px; font-weight: bold; cursor: pointer; font-size: 1rem; display: flex; align-items: center; justify-content: center; gap: 8px;" onclick="Swal.close(); borrarTodoFiltrado();"><i class="fa-solid fa-trash-can"></i> Vaciar Tabla Completa</button>
                </div>
            `,
            showConfirmButton: false,
            showCloseButton: true
        });
    }

    function abrirFiltros() {
        Swal.fire({
            title: 'Filtros Avanzados',
            html: `
                <div style="display:flex; flex-direction:column; gap:10px; text-align:left; font-size:0.9rem;">
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
                    
                    <div style="margin-top: 15px; background: #eff6ff; padding: 10px; border-radius: 10px; border: 1px dashed #3b82f6; display: flex; align-items: center; gap: 10px;">
                        <input type="checkbox" id="swal-unidos" value="1" <?php echo ($ver_unidos === 1) ? 'checked' : ''; ?> style="width: 20px; height: 20px; cursor: pointer;">
                        <label for="swal-unidos" style="font-weight:800; color:#1d4ed8; margin:0; cursor: pointer;">Unir Estadísticas (Tótem + Ventanilla)</label>
                    </div>
                </div>
            `,
            confirmButtonColor: '#2563eb',
            confirmButtonText: '<i class="fa-solid fa-filter"></i> Aplicar',
            showCancelButton: true, cancelButtonText: 'Limpiar Todo',
            preConfirm: () => {
                let params = new URLSearchParams();
                params.set('fecha_inicio', document.getElementById('swal-f1').value);
                params.set('fecha_fin', document.getElementById('swal-f2').value);
                if(document.getElementById('swal-h1').value) params.set('hora_inicio', document.getElementById('swal-h1').value);
                if(document.getElementById('swal-h2').value) params.set('hora_fin', document.getElementById('swal-h2').value);
                if(document.getElementById('swal-dni').value) params.set('busqueda', document.getElementById('swal-dni').value);
                if(document.getElementById('swal-tk').value) params.set('token', document.getElementById('swal-tk').value);
                if(document.getElementById('swal-serv').value) params.set('servicio', document.getElementById('swal-serv').value);
                if(document.getElementById('swal-unidos').checked) params.set('ver_unidos', '1');
                return params.toString();
            }
        }).then((result) => {
            if (result.isConfirmed) { window.location.href = 'reporte_totem.php?' + result.value; }
            else if (result.dismiss === Swal.DismissReason.cancel) { window.location.href = 'reporte_totem.php'; }
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
                    <h4 style="margin: 0 0 10px 0; color:#3b82f6;">Promedios por Operación</h4>
                    <div style="display:flex; justify-content:space-around; margin-bottom:20px; background:#f8fafc; padding:10px; border-radius:8px;">
                        <div style="text-align:center;"><b>Validación</b><br><span style="font-size:1.5rem; font-weight:900; color:#0f172a;">${pVal}s</span></div>
                        <div style="text-align:center;"><b>Asistencia</b><br><span style="font-size:1.5rem; font-weight:900; color:#0f172a;">${pAsis}s</span></div>
                    </div>
                    <h4 style="margin: 0 0 10px 0; color:#ef4444;">🐌 Los 5 más Lentos</h4>
                    <div style="margin-bottom:20px;">${htmlLentos}</div>
                    <h4 style="margin: 0 0 10px 0; color:#10b981;">⚡ Los 5 más Rápidos</h4>
                    <div>${htmlRapidos}</div>
                </div>
            `,
            confirmButtonColor: '#8b5cf6',
            confirmButtonText: 'Cerrar'
        });
    }

    function mostrarTicket(id, hora, fecha, dni, nombre, servicio, token, modo, tiempo, orden) {
        let esVal = modo === 'Validación' || modo === 'VALIDACION';
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
                        <img src="https://federicogonzalez.net/actis/img/osfa.png" style="height: 32px; margin-bottom: 4px;">
                        <h3 style="font-size: 1.1rem; font-weight: 900; margin: 0; color: #5274AD; letter-spacing: 0.5px;">POLICLÍNICA GENERAL ACTIS</h3>
                        <p style="font-size: 0.75rem; color: #64748b; margin: 2px 0 0 0;">
                            <i class="fa-regular fa-calendar-days"></i> ${fecha} &nbsp;&nbsp; <i class="fa-regular fa-clock"></i> ${hora} hs
                        </p>
                    </div>

                    <div style="margin-bottom: 10px;">
                        <span style="background: ${esVal ? '#eff6ff' : '#fff7ed'}; color: ${esVal ? '#2563eb' : '#c2410c'}; padding: 4px 10px; border-radius: 30px; font-size: 0.65rem; font-weight: 800; text-transform: uppercase; border: 1px solid ${esVal ? '#bfdbfe' : '#ffedd5'};">
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
                                <i class="fa-solid fa-stethoscope" style="color: #5274AD;"></i> ${servicio}
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
                            <span style="font-size: 0.95rem; font-weight: 900; color: #5274AD; font-family: monospace;">${token}</span>
                        </div>
                        ` : ''}
                    </div>

                    <div style="display: flex; flex-direction: column; align-items: center; justify-content: center; margin-bottom: 10px; background: white; padding: 8px; border-radius: 10px; border: 1px solid #e2e8f0; max-width: 120px; margin-left: auto; margin-right: auto;">
                        <div id="modal-qr-render"></div>
                        <small style="font-size: 0.6rem; color: #94a3b8; font-weight: 800; text-transform: uppercase; margin-top: 4px;">QR Comprobante</small>
                    </div>

                    <div style="margin-top: 10px;">
                        <a href="${textoQr}" target="_blank" style="display: inline-flex; align-items: center; justify-content: center; gap: 6px; width: 100%; max-width: 250px; background: #5274AD; color: white; text-decoration: none; padding: 10px 14px; border-radius: 8px; font-weight: 800; font-size: 0.8rem; box-shadow: 0 4px 12px rgba(82, 116, 173, 0.3);">
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
    
    function exportarCSV() {
        let tabla = document.getElementById("tabla-exportar");
        let filas = tabla.querySelectorAll("tr");
        let csv = [];
        for (let i = 0; i < filas.length; i++) {
            let cols = filas[i].querySelectorAll("td, th");
            let filaArr = [];
            for (let j = 1; j < cols.length - 1; j++) { filaArr.push('"' + cols[j].innerText.replace(/"/g, '""') + '"'); }
            if(filaArr.length > 0) csv.push(filaArr.join(","));
        }
        let blob = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
        let url = URL.createObjectURL(blob);
        let a = document.createElement("a"); a.href = url; a.download = "Reporte_Totem.csv"; a.click();
    }

    function borrarSeleccionados() {
        if (document.querySelectorAll('input[name="ids[]"]:checked').length === 0) return Swal.fire({ icon: 'info', title: 'Atención', text: 'Selecciona un registro.' });
        Swal.fire({ title: '¿Borrar seleccionados?', icon: 'warning', showCancelButton: true, confirmButtonColor: '#f59e0b', confirmButtonText: 'Sí, borrar' }).then((r) => {
            if (r.isConfirmed) { document.getElementById('form-delete').insertAdjacentHTML('beforeend', '<input type="hidden" name="eliminar_seleccionados" value="1">'); document.getElementById('form-delete').submit(); }
        });
    }
    function borrarTodoFiltrado() {
        Swal.fire({ title: '¡ATENCIÓN!', text: "¿Borrar TODOS los mostrados?", icon: 'error', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Sí, borrar' }).then((r) => {
            if (r.isConfirmed) { document.getElementById('form-delete').insertAdjacentHTML('beforeend', '<input type="hidden" name="eliminar_todos_fecha" value="1">'); document.getElementById('form-delete').submit(); }
        });
    }
    function borrarIndividual(id) {
        Swal.fire({ title: '¿Eliminar?', text: "No se puede deshacer.", icon: 'warning', showCancelButton: true, confirmButtonColor: '#ef4444', confirmButtonText: 'Eliminar' }).then((r) => {
            if (r.isConfirmed) { document.getElementById('id_eliminar').value = id; document.getElementById('form-delete').insertAdjacentHTML('beforeend', '<input type="hidden" name="eliminar_uno" value="1">'); document.getElementById('form-delete').submit(); }
        });
    }
</script>
<?php require_once 'includes/footer.php'; ?>
