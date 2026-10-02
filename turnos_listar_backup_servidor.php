<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { header("Location: index.php"); exit; }

require_once 'includes/conexion.php';
require_once 'includes/header.php';

// --- Lógica para Eliminar Turno Individual ---
if(isset($_GET['eliminar_id']) && isset($_SESSION['permisos']) && in_array('modulo_turnos_eliminar', $_SESSION['permisos'])) {
    $id_del = $conexion->real_escape_string($_GET['eliminar_id']);
    $conexion->query("DELETE FROM turnos WHERE id = $id_del");
    echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({icon: 'success', title: 'Eliminado', text: 'El turno fue eliminado del sistema.', confirmButtonColor: '#10b981'}); });</script>";
}

// --- Lógica para Eliminación Masiva ---
if(isset($_POST['eliminar_masivo']) && isset($_POST['turnos_sel']) && isset($_SESSION['permisos']) && in_array('modulo_turnos_eliminar', $_SESSION['permisos'])) {
    $ids = array_map('intval', $_POST['turnos_sel']);
    if(count($ids) > 0) {
        $ids_str = implode(',', $ids);
        $conexion->query("DELETE FROM turnos WHERE id IN ($ids_str)");
        echo "<script>document.addEventListener('DOMContentLoaded', function() { Swal.fire({icon: 'success', title: 'Limpieza Completa', text: 'Se eliminaron ".count($ids)." turnos.', confirmButtonColor: '#10b981'}).then(() => { window.location.href = 'turnos_listar.php'; }); });</script>";
    }
}

// --- Lógica de Filtros Múltiples ---
$filtro_dni = isset($_GET['dni']) ? $conexion->real_escape_string($_GET['dni']) : '';
$filtro_fecha = isset($_GET['fecha']) ? $conexion->real_escape_string($_GET['fecha']) : '';
$filtro_hora = isset($_GET['hora']) ? $conexion->real_escape_string($_GET['hora']) : '';
$filtro_estado = isset($_GET['estado']) ? $conexion->real_escape_string($_GET['estado']) : '';
$filtro_profesional = isset($_GET['profesional']) ? $conexion->real_escape_string($_GET['profesional']) : '';
$filtro_operador = isset($_GET['operador']) ? $conexion->real_escape_string($_GET['operador']) : '';
$filtro_servicio = isset($_GET['servicio']) ? $conexion->real_escape_string($_GET['servicio']) : '';
$filtro_hora_creacion = isset($_GET['hora_creacion']) ? $conexion->real_escape_string($_GET['hora_creacion']) : '';
$filtro_creado_hoy = (isset($_GET['creado_hoy']) && $_GET['creado_hoy'] == '1') ? 1 : 0;

$hoy_str = date('Y-m-d');
$es_filtro_hoy = ($filtro_fecha === $hoy_str && $filtro_dni == '' && $filtro_estado == '' && $filtro_profesional == '' && $filtro_servicio == '' && $filtro_hora == '' && $filtro_operador == '' && $filtro_hora_creacion == '' && !$filtro_creado_hoy);

// Base condition
$where = "1=1";
$hay_filtros = false;

if($filtro_dni != '') { $where .= " AND (p.dni LIKE '%$filtro_dni%' OR p.nombre LIKE '%$filtro_dni%' OR p.apellido LIKE '%$filtro_dni%')"; $hay_filtros = true; }
if($filtro_fecha != '') { $where .= " AND t.fecha_turno = '$filtro_fecha'"; $hay_filtros = true; }
if($filtro_hora != '') { $where .= " AND t.hora_turno LIKE '$filtro_hora%'"; $hay_filtros = true; }
if($filtro_estado != '') { $where .= " AND t.estado = '$filtro_estado'"; $hay_filtros = true; }
if($filtro_profesional != '') { $where .= " AND t.profesional LIKE '%$filtro_profesional%'"; $hay_filtros = true; }
if($filtro_servicio != '') { $where .= " AND t.servicio LIKE '%$filtro_servicio%'"; $hay_filtros = true; }
if($filtro_operador != '') { $where .= " AND (t.operador_externo LIKE '%$filtro_operador%' OR u.nombre_completo LIKE '%$filtro_operador%')"; $hay_filtros = true; }
if($filtro_hora_creacion != '') { $where .= " AND TIME(t.creado_el) LIKE '$filtro_hora_creacion%'"; $hay_filtros = true; }
if($filtro_creado_hoy) { $where .= " AND DATE(t.creado_el) = '$hoy_str'"; $hay_filtros = true; }


// --- PAGINACIÓN Y LIMITES ---
$por_pagina = 50;
$pagina_actual = isset($_GET['pagina']) ? (int)$_GET['pagina'] : 1;
if($pagina_actual < 1) $pagina_actual = 1;
$offset = ($pagina_actual - 1) * $por_pagina;

// --- ORDENAMIENTO DINÁMICO ---
$sort_by = isset($_GET['sort_by']) ? $_GET['sort_by'] : 'cargado'; // Por defecto: horario cargado
$sort_order = isset($_GET['sort_order']) && strtoupper($_GET['sort_order']) == 'ASC' ? 'ASC' : 'DESC'; // Por defecto: más nuevos primero

$order_sql = "t.creado_el DESC";
switch ($sort_by) {
    case 'paciente': $order_sql = "p.apellido $sort_order, p.nombre $sort_order"; break;
    case 'servicio': $order_sql = "t.servicio $sort_order, t.profesional $sort_order"; break;
    case 'horario':  $order_sql = "t.fecha_turno $sort_order, t.hora_turno $sort_order"; break;
    case 'cargado':  $order_sql = "t.creado_el $sort_order"; break;
    case 'estado':   $order_sql = "t.estado $sort_order"; break;
}

$sql_count = "SELECT COUNT(*) as total FROM turnos t INNER JOIN pacientes p ON t.paciente_id = p.id LEFT JOIN usuarios u ON t.usuario_creador_id = u.id WHERE $where";
$total_items = $conexion->query($sql_count)->fetch_assoc()['total'];
$total_paginas = ceil($total_items / $por_pagina);

// Consulta: Ordenado dinámicamente con paginación
$sql = "SELECT t.*, 
               p.nombre, p.apellido, p.dni, p.hc as afiliado, p.telefono, p.email,
               u.nombre_completo AS creador_nombre 
        FROM turnos t 
        INNER JOIN pacientes p ON t.paciente_id = p.id 
        LEFT JOIN usuarios u ON t.usuario_creador_id = u.id
        WHERE $where 
        ORDER BY $order_sql 
        LIMIT $offset, $por_pagina";
        
$resultado = $conexion->query($sql);

// Helper para mantener todos los filtros cuando se hace clic en una cabecera para ordenar
$qs_array = $_GET;
unset($qs_array['sort_by'], $qs_array['sort_order'], $qs_array['pagina']);
$base_qs = http_build_query($qs_array);
$base_url_sort = "turnos_listar.php?" . ($base_qs ? $base_qs . "&" : "");

// --- ESTADÍSTICAS DEL FILTRO ACTUAL ---
$sql_stats = "SELECT COUNT(*) as total_turnos, 
              SUM(CASE WHEN t.estado = 'Atendido' THEN 1 ELSE 0 END) as total_atendidos,
              SUM(CASE WHEN t.estado = 'Presente' THEN 1 ELSE 0 END) as total_presentes,
              SUM(CASE WHEN t.estado = 'Cancelado' THEN 1 ELSE 0 END) as total_cancelados
              FROM turnos t 
              INNER JOIN pacientes p ON t.paciente_id = p.id 
              LEFT JOIN usuarios u ON t.usuario_creador_id = u.id 
              WHERE $where";
$stats = $conexion->query($sql_stats)->fetch_assoc();

// --- CARGAR LISTAS DINÁMICAS PARA LOS FILTROS ---
$list_servicios = $conexion->query("SELECT DISTINCT servicio FROM turnos WHERE servicio IS NOT NULL AND servicio != '' ORDER BY servicio");
$list_profesionales = $conexion->query("SELECT DISTINCT profesional FROM turnos WHERE profesional IS NOT NULL AND profesional != '' ORDER BY profesional");
$list_operadores = $conexion->query("SELECT DISTINCT IFNULL(NULLIF(t.operador_externo, ''), u.nombre_completo) as operador_nombre FROM turnos t LEFT JOIN usuarios u ON t.usuario_creador_id = u.id WHERE t.operador_externo != '' OR u.nombre_completo IS NOT NULL ORDER BY operador_nombre");
?>

<style>
    /* Diseño Base */
    body { background-color: #f1f5f9; }
    .tl-container { max-width: 1400px; margin: 20px auto; padding: 0 15px; font-family: 'Poppins', sans-serif; box-sizing: border-box; }
    .tl-panel { background: #ffffff; border-radius: 16px; padding: 20px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
    
    /* Encabezado y Botonera Superior */
    .tl-top-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; flex-wrap: nowrap; gap: 6px; padding-bottom: 5px; width: 100%; box-sizing: border-box; }
    .tl-header { font-size: 1.15rem; font-weight: 800; color: #0f172a; margin: 0; display: flex; align-items: center; gap: 6px; white-space: nowrap; flex-shrink: 0; }
    
    .tl-actions-top { display: flex; gap: 5px; align-items: center; flex-wrap: nowrap; justify-content: flex-end; flex-shrink: 1; min-width: 0; }
    .btn-open-filters { background: #144973; color: white; border: none; padding: 6px 10px; border-radius: 8px; font-weight: 600; font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; gap: 5px; box-shadow: 0 2px 4px rgba(20,73,115,0.2); transition: 0.2s; white-space: nowrap; flex-shrink: 1; }
    .btn-open-filters.active-badge { background: #3b82f6; }
    
    /* Botón Rápido "Hoy" y "Limpiar" */
    .btn-quick-hoy { background: #10b981; color: white; border: none; padding: 6px 10px; border-radius: 8px; font-weight: 600; font-size: 0.8rem; cursor: pointer; display: flex; align-items: center; gap: 5px; text-decoration: none; box-shadow: 0 2px 4px rgba(16,185,129,0.2); transition: all 0.2s; white-space: nowrap; flex-shrink: 1; }
    .btn-quick-hoy:active { transform: scale(0.96); }
    .btn-quick-clear { background: #ef4444; box-shadow: 0 2px 4px rgba(239,68,68,0.2); }
    
    /* Modal de Filtros (Rediseñado para ser amigable en móvil) */
    .modal-filtros-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15,23,42,0.7); z-index: 9999; display: none; justify-content: center; align-items: center; backdrop-filter: blur(4px); }
    .modal-filtros-overlay.show { display: flex; }
    .modal-filtros-content { background: white; width: 100%; max-width: 700px; padding: 25px; border-radius: 20px; animation: scaleUp 0.3s ease-out; box-sizing: border-box; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); }
    .grid-filtros { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 5px; }
    @keyframes scaleUp { from { transform: scale(0.95); opacity: 0; } to { transform: scale(1); opacity: 1; } }
    .modal-filtros-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; }
    .modal-filtros-header h3 { margin: 0; font-size: 1.3rem; color: #0f172a; font-weight: 800; }
    .btn-close-filters { background: #f1f5f9; border: none; width: 36px; height: 36px; border-radius: 50%; color: #64748b; font-weight: bold; font-size: 1.2rem; cursor: pointer; transition: 0.2s; }
    .btn-close-filters:hover { background: #e2e8f0; color: #0f172a; }
    
    .tl-group { margin-bottom: 15px; }
    .tl-label { font-size: 0.85rem; font-weight: 700; color: #475569; display: block; margin-bottom: 6px; }
    .tl-input, .tl-select { width: 100%; padding: 12px 15px; border-radius: 10px; border: 1px solid #cbd5e1; font-weight: 500; font-size: 1rem; box-sizing: border-box; background: #f8fafc; transition: border 0.2s; }
    .tl-input:focus, .tl-select:focus { border-color: #3b82f6; outline: none; background: #ffffff; }
    .modal-filtros-actions { display: flex; gap: 12px; margin-top: 25px; }
    .btn-action { flex: 1; padding: 14px; border: none; border-radius: 10px; font-weight: 800; cursor: pointer; text-align: center; text-decoration: none; font-size: 1rem; transition: 0.2s; }
    .btn-aplicar { background: #3b82f6; color: white; box-shadow: 0 4px 6px rgba(59,130,246,0.25); }
    .btn-aplicar:active { transform: scale(0.98); }
    .btn-limpiar { background: #f1f5f9; color: #64748b; border: 1px solid #e2e8f0; }
    .btn-limpiar:active { background: #e2e8f0; }

    /* Barra Superior Fija (Sticky) y Buscador */
    .sticky-top-bar { position: relative; background: #ffffff; padding: 5px 0 10px 0; margin-top: 0; border-bottom: 1px solid #e2e8f0; margin-bottom: 15px; width: 100%; box-sizing: border-box; }
    .quick-search { padding: 6px 10px; border-radius: 8px; border: 2px solid #cbd5e1; font-family: 'Poppins'; outline: none; width: 130px; transition: border 0.2s; font-size: 0.8rem; flex-shrink: 1; min-width: 80px; }
    .quick-search:focus { border-color: #144973; }

    /* Fijar SOLO en escritorio, debajo del menú principal (top 70px) y por debajo de los desplegables (z-index 45) */
    @media (min-width: 769px) {
        .sticky-top-bar { position: sticky !important; top: 70px !important; z-index: 45 !important; }
    }

    /* Contenedor de Tabla fluido y Columnas Espaciadas */
    .tl-table-wrap { overflow-x: auto; border: 1px solid #e2e8f0; background: white; width: 100%; border-radius: 8px; }
    .tl-table { width: 100%; border-collapse: collapse; min-width: 1100px; table-layout: fixed; } /* MIN WIDTH AMPLIADO PARA QUE NO SE MEZCLE NADA */
    .tl-table th { padding: 12px 10px; text-align: left; color: #475569; background: #f8fafc; font-size: 0.8rem; font-weight: 700; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; white-space: nowrap; }
    .tl-table td { padding: 12px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; font-size: 0.9rem; }
    
    /* Medidas quirúrgicas de las columnas */
    .col-chk { width: 40px; text-align: center; }
    .col-paciente { width: 22%; }
    .col-servicio { width: 22%; }
    .col-horario { width: 12%; }
    .col-operador { width: 20%; } /* COLUMNA NUEVA PARA CREADOR */
    .col-estado { width: 12%; }
    .col-accion { width: 110px; text-align: center; }

    /* Controles de Paginación Visual */
    .paginacion { display: flex; justify-content: center; gap: 6px; margin-top: 25px; flex-wrap: wrap; padding-bottom: 15px; }
    .page-link { background: white; border: 1px solid #cbd5e1; color: #1e293b; padding: 8px 15px; border-radius: 8px; text-decoration: none; font-weight: 700; transition: all 0.2s; font-size: 0.9rem; }
    .page-link:hover { background: #e2e8f0; border-color: #94a3b8; }
    .page-link.active { background: #144973; color: white; border-color: #144973; }
    
    /* Compactando Textos */
    .text-primary-dark { color: #0f172a; font-weight: 700; display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .text-muted-small { color: #64748b; font-size: 0.8rem; display: block; margin-top: 2px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .tl-estado { padding: 6px 12px; border-radius: 6px; font-weight: 800; font-size: 0.75rem; text-transform: uppercase; display: inline-block; }
    
    .tl-btn-icon { width: 32px; height: 32px; border-radius: 6px; display: inline-flex; justify-content: center; align-items: center; color: white; border: none; cursor: pointer; text-decoration: none; font-size: 0.9rem; }
    .icon-view { background: #f59e0b; } .icon-edit { background: #3b82f6; } .icon-del { background: #ef4444; }

    .fecha-grupo-header { background: #1e293b; color: white; padding: 12px 15px; font-size: 0.95rem; font-weight: 700; margin-top: 25px; display: flex; align-items: center; gap: 10px; border-radius: 8px 8px 0 0; }

    /* Modal de Detalles del Turno (SweetAlert) */
    .swal-compact-mobile { width: 100% !important; max-width: 600px !important; padding: 0 !important; border-radius: 12px !important; box-sizing: border-box; overflow: hidden !important; }
    .swal-header { background: #144973; color: white; padding: 15px 45px 15px 20px; font-size: 1.1rem; font-weight: 700; text-align: left; position: relative; }
    .swal-custom-content { padding: 15px; text-align: left; background: #ffffff; max-height: 80vh; overflow-y: auto; box-sizing: border-box; }
    
    div:where(.swal2-container) button:where(.swal2-close) {
        color: #0f172a !important; background: #e2e8f0 !important; border: 2px solid #ffffff !important; border-radius: 50% !important;
        top: 8px !important; right: 10px !important; width: 34px !important; height: 34px !important; font-size: 1.5rem !important;
        display: flex !important; align-items: center !important; justify-content: center !important; z-index: 9999 !important;
        transition: all 0.2s ease !important; outline: none !important; box-shadow: 0 2px 4px rgba(0,0,0,0.1) !important;
    }
    div:where(.swal2-container) button:where(.swal2-close):hover { background: #ef4444 !important; color: #ffffff !important; transform: scale(1.1); }
    
    .info-list-compact { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 10px; }
    .info-list-compact li { display: flex; flex-direction: column; border-bottom: 1px solid #f1f5f9; padding-bottom: 8px; }
    .info-list-compact li:last-child { border-bottom: none; }
    .info-list-compact li label { font-size: 0.7rem; color: #64748b; font-weight: 700; text-transform: uppercase; margin-bottom: 2px; }
    .info-list-compact li span { font-size: 0.95rem; font-weight: 600; color: #0f172a; word-break: break-word; }
    
    .block-full { background: #f8fafc; border-radius: 8px; padding: 12px; margin-top: 15px; border: 1px solid #e2e8f0; }
    .block-title { font-size: 0.85rem; font-weight: 700; color: #334155; margin: 0 0 8px 0; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; }
    .code-box { font-family: monospace; font-size: 0.85rem; color: #1e293b; line-height: 1.4; word-break: break-word; }

    /* Aprovechamiento al 100% en Celulares (COMPACTO REAL) */
    @media (max-width: 768px) {
        body, html { max-width: 100vw !important; overflow-x: hidden !important; }
        .tl-container, .tl-panel { padding: 6px 4px !important; margin: 0 !important; width: 100% !important; box-sizing: border-box !important; border-radius: 0 !important; border: none !important; box-shadow: none !important; }
        
        .sticky-top-bar { position: relative !important; top: 0 !important; padding: 0 !important; border: none !important; margin-bottom: 8px !important; }
        .tl-header { font-size: 1.1rem !important; margin-bottom: 8px !important; text-align: center; }

        /* Contenedor flexible general para acomodar en bloque */
        .tl-actions-top { display: flex !important; flex-wrap: wrap !important; gap: 5px !important; width: 100% !important; flex-direction: row !important; justify-content: space-between !important; }
        
        /* FILA 1: Buscador y Botón de Filtros pegados y compactos */
        .tl-actions-top > div:first-child { flex: 1 1 calc(100% - 45px) !important; display: flex !important; gap: 4px !important; margin: 0 !important; }
        .quick-search { flex-grow: 1 !important; width: 100% !important; padding: 8px !important; font-size: 0.9rem !important; border-radius: 8px !important; margin: 0 !important; height: 38px !important; box-sizing: border-box !important; }
        .tl-actions-top > div:first-child > .btn-open-filters { width: 40px !important; height: 38px !important; flex-shrink: 0 !important; padding: 0 !important; display: flex !important; justify-content: center !important; align-items: center !important; border-radius: 8px !important; }
        
        /* Destruye el texto del botón "Filtrar Sistema" y lo deja como un cuadradito con ícono */
        .tl-actions-top > button[onclick*="modalFiltros"] { flex: 0 0 40px !important; height: 38px !important; margin: 0 !important; padding: 0 !important; display: flex !important; justify-content: center !important; align-items: center !important; font-size: 0 !important; border-radius: 8px !important; }
        .tl-actions-top > button[onclick*="modalFiltros"] i { font-size: 1.1rem !important; margin: 0 !important; }

        /* FILA 2 y 3: Resto de botones de a pares (mitad de pantalla) para que no sean un paredón */
        .tl-actions-top > .btn-quick-hoy { flex: 1 1 calc(50% - 4px) !important; font-size: 0.75rem !important; padding: 8px 4px !important; margin: 0 !important; display: flex !important; justify-content: center !important; align-items: center !important; white-space: nowrap !important; height: 34px !important; box-sizing: border-box !important; }

        /* Estadísticas ultra compactas (4 tiritas en una fila) */
        div[style*="flex-wrap: wrap; margin-bottom: 20px;"] { display: grid !important; grid-template-columns: repeat(4, 1fr) !important; gap: 4px !important; width: 100% !important; margin-bottom: 8px !important; padding: 0 !important; }
        div[style*="flex-wrap: wrap; margin-bottom: 20px;"] > div { padding: 4px !important; border-width: 1px !important; border-bottom-width: 3px !important; min-width: 0 !important; }
        div[style*="flex-wrap: wrap; margin-bottom: 20px;"] > div > span:first-child { font-size: 0.55rem !important; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        div[style*="flex-wrap: wrap; margin-bottom: 20px;"] > div > span:last-child { font-size: 1rem !important; }

        /* Modal Filtros desde abajo */
        .modal-filtros-overlay { align-items: flex-end !important; padding: 0 !important; }
        .modal-filtros-content { max-height: 85vh !important; overflow-y: auto !important; border-radius: 20px 20px 0 0 !important; padding: 15px !important; width: 100% !important; margin: 0 !important; box-sizing: border-box !important; }
        .tl-group[style*="display: flex"] { flex-direction: column !important; gap: 10px !important; }
        .tl-group > div { width: 100% !important; }

        /* Tablas */
        .tl-table-wrap { border-radius: 0 !important; border-left: none !important; border-right: none !important; width: 100% !important; }
        .fecha-grupo-header { border-radius: 0 !important; margin-top: 5px !important; padding: 8px 10px !important; font-size: 0.85rem !important; }
        .swal-compact-mobile { margin: 0 !important; border-radius: 16px 16px 0 0 !important; align-self: flex-end !important; }
    }
    
    
</style>
<div class="tl-container">
    <div class="tl-panel">
        
        <div class="tl-top-bar sticky-top-bar">
            <h2 class="tl-header"><i class="fa-solid fa-list-check" style="color: #3b82f6;"></i> Gestión de Turnos</h2>
            <div class="tl-actions-top">
                <div style="display:flex; align-items:center; gap:5px; margin-right: 15px;">
                    <input type="text" id="busqueda_rapida" class="quick-search" placeholder="🔍 Buscar DNI o Nombre..." value="<?= htmlspecialchars($filtro_dni) ?>" onkeypress="if(event.key === 'Enter') aplicarBusquedaRapida()">
                    <button type="button" class="btn-open-filters" onclick="aplicarBusquedaRapida()" style="padding: 8px 12px;"><i class="fa-solid fa-magnifying-glass"></i></button>
                </div>
                
                <?php if ($es_filtro_hoy): ?>
                    <a href="turnos_listar.php" class="btn-quick-hoy btn-quick-clear">
                        <i class="fa-solid fa-xmark"></i> Limpiar Hoy
                    </a>
                <?php else: ?>
                    <a href="turnos_listar.php?fecha=<?= $hoy_str ?>" class="btn-quick-hoy">
                        <i class="fa-regular fa-calendar-check"></i> Turnos para Hoy
                    </a>
                <?php endif; ?>

                <?php if ($filtro_creado_hoy): ?>
                    <a href="turnos_listar.php" class="btn-quick-hoy btn-quick-clear">
                        <i class="fa-solid fa-xmark"></i> Quitar Filtro Sacados
                    </a>
                <?php else: ?>
                    <a href="turnos_listar.php?creado_hoy=1" class="btn-quick-hoy" style="background: #8b5cf6; box-shadow: 0 2px 4px rgba(139,92,246,0.3);">
                        <i class="fa-solid fa-clock-rotate-left"></i> Sacados Hoy
                    </a>
                <?php endif; ?>

                <button class="btn-open-filters <?= $hay_filtros ? 'active-badge' : '' ?>" onclick="document.getElementById('modalFiltros').classList.add('show')">
                    <i class="fa-solid fa-filter"></i> <?= $hay_filtros ? 'Filtros Activos' : 'Filtrar Sistema' ?>
                </button>

                <!-- BOTÓN REPORTE PDF -->
                               <a href="reporte_turnos_pdf.php?dni=<?= urlencode($filtro_dni) ?>&fecha=<?= urlencode($filtro_fecha) ?>&hora=<?= urlencode($filtro_hora) ?>&estado=<?= urlencode($filtro_estado) ?>&profesional=<?= urlencode($filtro_profesional) ?>&servicio=<?= urlencode($filtro_servicio) ?>&operador=<?= urlencode($filtro_operador) ?>&hora_creacion=<?= urlencode($filtro_hora_creacion) ?>&creado_hoy=<?= $filtro_creado_hoy ?>&nro_turno=<?= urlencode($filtro_nro_turno) ?>&afiliado=<?= urlencode($filtro_afiliado) ?>&contacto=<?= urlencode($filtro_contacto) ?>&detalles=<?= urlencode($filtro_detalles) ?>" target="_blank" class="btn-quick-hoy" style="background: #eab308; box-shadow: 0 2px 4px rgba(234,179,8,0.3); color: #fff;">
                    <i class="fa-solid fa-file-pdf"></i> Reporte PDF
                </a>
                
                <?php if(isset($_SESSION['permisos']) && in_array('modulo_turnos_eliminar', $_SESSION['permisos'])): ?>
                <button type="button" class="btn-quick-hoy" style="background:#64748b; box-shadow: 0 2px 4px rgba(100,116,139,0.2);" onclick="seleccionarTodoGlobal()">
                    <i class="fa-solid fa-check-double"></i> Marcar Todos
                </button>
                <button type="button" class="btn-quick-hoy btn-quick-clear" onclick="confirmarEliminacionMasiva()">
                    <i class="fa-solid fa-trash-can"></i> Borrar Seleccionados
                </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- PANEL DE ESTADÍSTICAS RÁPIDAS -->
        <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;">
            <div style="flex: 1; min-width: 100px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; text-align: center; border-bottom: 4px solid #3b82f6;">
                <span style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Listados</span>
                <span style="display: block; font-size: 1.5rem; color: #0f172a; font-weight: 900;"><?= $stats['total_turnos'] ?? 0 ?></span>
            </div>
            <div style="flex: 1; min-width: 100px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; text-align: center; border-bottom: 4px solid #10b981;">
                <span style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Atendidos</span>
                <span style="display: block; font-size: 1.5rem; color: #0f172a; font-weight: 900;"><?= $stats['total_atendidos'] ?? 0 ?></span>
            </div>
            <div style="flex: 1; min-width: 100px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; text-align: center; border-bottom: 4px solid #047857;">
                <span style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Presentes</span>
                <span style="display: block; font-size: 1.5rem; color: #0f172a; font-weight: 900;"><?= $stats['total_presentes'] ?? 0 ?></span>
            </div>
            <div style="flex: 1; min-width: 100px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px; text-align: center; border-bottom: 4px solid #ef4444;">
                <span style="display: block; font-size: 0.75rem; color: #64748b; font-weight: 700; text-transform: uppercase;">Cancelados</span>
                <span style="display: block; font-size: 1.5rem; color: #0f172a; font-weight: 900;"><?= $stats['total_cancelados'] ?? 0 ?></span>
            </div>
        </div>

        <form id="form_masivo" method="POST" action="">
            <input type="hidden" name="eliminar_masivo" value="1">

        <div id="modalFiltros" class="modal-filtros-overlay">
            <div class="modal-filtros-content">
                <div class="modal-filtros-header">
                    <h3>Búsqueda Avanzada</h3>
                    <button type="button" class="btn-close-filters" onclick="document.getElementById('modalFiltros').classList.remove('show')"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <div class="tl-group">
                    <label class="tl-label"><i class="fa-regular fa-user"></i> Paciente (DNI o Nombre)</label>
                    <input type="text" id="filtro_dni" value="<?= htmlspecialchars($filtro_dni) ?>" placeholder="Ej: Perez o 35123456" class="tl-input">
                </div>
                <div class="tl-group">
                    <label class="tl-label"><i class="fa-regular fa-calendar"></i> Fecha para la cual es el Turno</label>
                    <input type="date" id="filtro_fecha" value="<?= htmlspecialchars($filtro_fecha) ?>" class="tl-input">
                </div>
                
                <div class="tl-group" style="display: flex; gap: 10px;">
                    <div style="flex: 1;">
                        <label class="tl-label"><i class="fa-solid fa-hashtag"></i> Nro. Turno</label>
                        <input type="text" id="filtro_nro_turno" value="<?= htmlspecialchars($filtro_nro_turno ?? '') ?>" class="tl-input" placeholder="Ej: 12345">
                    </div>
                    <div style="flex: 1;">
                        <label class="tl-label"><i class="fa-solid fa-id-card"></i> Afiliado</label>
                        <input type="text" id="filtro_afiliado" value="<?= htmlspecialchars($filtro_afiliado ?? '') ?>" class="tl-input" placeholder="Ej: HC o Afiliado">
                    </div>
                </div>
                
                <div class="tl-group" style="display: flex; gap: 10px;">
                    <div style="flex: 1;">
                        <label class="tl-label"><i class="fa-solid fa-phone"></i> Contacto / Email</label>
                        <input type="text" id="filtro_contacto" value="<?= htmlspecialchars($filtro_contacto ?? '') ?>" class="tl-input" placeholder="Tel o Mail">
                    </div>
                    <div style="flex: 1;">
                        <label class="tl-label"><i class="fa-solid fa-notes-medical"></i> Detalles / Obs</label>
                        <input type="text" id="filtro_detalles" value="<?= htmlspecialchars($filtro_detalles ?? '') ?>" class="tl-input" placeholder="Práctica u Obs">
                    </div>
                </div>

                <div class="tl-group" style="display: flex; gap: 10px;">
                    <div style="flex: 1;">
                        <label class="tl-label"><i class="fa-regular fa-clock"></i> Hora del Turno</label>
                        <input type="time" id="filtro_hora" value="<?= htmlspecialchars($filtro_hora) ?>" class="tl-input">
                    </div>
                    <div style="flex: 1;">
                        <label class="tl-label"><i class="fa-solid fa-stopwatch"></i> Hora de Carga</label>
                        <input type="time" id="filtro_hora_creacion" value="<?= htmlspecialchars($filtro_hora_creacion) ?>" class="tl-input">
                    </div>
                </div>
                <div class="tl-group" style="display: flex; gap: 10px;">
                    <div style="flex: 1;">
                        <label class="tl-label"><i class="fa-solid fa-stethoscope"></i> Servicio</label>
                        <select id="filtro_servicio" class="tl-select">
                            <option value="">Todos los servicios</option>
                            <?php while($row = $list_servicios->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($row['servicio']) ?>" <?= $filtro_servicio == $row['servicio'] ? 'selected' : '' ?>><?= htmlspecialchars($row['servicio']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div style="flex: 1;">
                        <label class="tl-label"><i class="fa-solid fa-user-doctor"></i> Profesional</label>
                        <select id="filtro_profesional" class="tl-select">
                            <option value="">Todos los profesionales</option>
                            <?php while($row = $list_profesionales->fetch_assoc()): ?>
                                <option value="<?= htmlspecialchars($row['profesional']) ?>" <?= $filtro_profesional == $row['profesional'] ? 'selected' : '' ?>><?= htmlspecialchars($row['profesional']) ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                </div>
                <div class="tl-group">
                    <label class="tl-label"><i class="fa-solid fa-headset"></i> Operador (Quién lo cargó)</label>
                    <select id="filtro_operador" class="tl-select">
                        <option value="">Todos los operadores</option>
                        <?php while($row = $list_operadores->fetch_assoc()): ?>
                            <option value="<?= htmlspecialchars($row['operador_nombre']) ?>" <?= $filtro_operador == $row['operador_nombre'] ? 'selected' : '' ?>><?= htmlspecialchars($row['operador_nombre']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>

                <div class="tl-group">
                    <label class="tl-label"><i class="fa-solid fa-clipboard-check"></i> Estado del Turno</label>
                    <select id="filtro_estado" class="tl-select">
                        <option value="">Todos los estados</option>
                        <option value="Pendiente" <?= $filtro_estado == 'Pendiente' ? 'selected' : '' ?>>Pendiente</option>
                        <option value="Autorizado" <?= $filtro_estado == 'Autorizado' ? 'selected' : '' ?>>Autorizado</option>
                        <option value="Presente" <?= $filtro_estado == 'Presente' ? 'selected' : '' ?>>Presente</option>
                        <option value="Atendido" <?= $filtro_estado == 'Atendido' ? 'selected' : '' ?>>Atendido</option>
                        <option value="Cancelado" <?= $filtro_estado == 'Cancelado' ? 'selected' : '' ?>>Cancelado</option>
                    </select>
                </div>
                <div class="modal-filtros-actions">
                    <a href="turnos_listar.php" class="btn-action btn-limpiar">Limpiar Todo</a>
                    <button type="button" onclick="aplicarFiltros()" class="btn-action btn-aplicar">Aplicar Filtros</button>
                </div>
            </div>
        </div>
                <?php if($resultado && $resultado->num_rows > 0): ?>
            <?php 
            $fecha_actual = null;
            while($t = $resultado->fetch_assoc()): 
                $fecha_fmt = !empty($t['fecha_turno']) ? date("d/m/Y", strtotime($t['fecha_turno'])) : 'Sin Fecha';
                $hora_fmt = !empty($t['hora_turno']) ? date("H:i", strtotime($t['hora_turno'])) : '--:--';
                $fecha_creacion_fmt = !empty($t['creado_el']) ? date("d/m/Y", strtotime($t['creado_el'])) : 'Desconocida';
                $hora_creacion_fmt = !empty($t['creado_el']) ? date("H:i", strtotime($t['creado_el'])) : '--:--';
                
                // Si ordenamos por horario, agrupamos por fecha. Si ordenamos por otra cosa, unificamos la tabla.
                $grupo_header = ($sort_by === 'horario') ? $fecha_fmt : 'Listado de Turnos (Ordenado)';
                
                if ($fecha_actual !== $grupo_header) {
                    if ($fecha_actual !== null) { echo "</tbody></table></div>"; }
                    $fecha_actual = $grupo_header;
                    
                    $titulo_grupo = ($sort_by === 'horario') ? "<i class='fa-regular fa-calendar-days'></i> Turnos del día: {$fecha_fmt}" : "<i class='fa-solid fa-list-ol'></i> Turnos Activos en Pantalla";
                    
                    // Lógica para armar los enlaces de las cabeceras (invierten el orden al hacer clic)
                    $link_paciente = $base_url_sort . "sort_by=paciente&sort_order=" . ($sort_by == 'paciente' && $sort_order == 'ASC' ? 'DESC' : 'ASC');
                    $link_servicio = $base_url_sort . "sort_by=servicio&sort_order=" . ($sort_by == 'servicio' && $sort_order == 'ASC' ? 'DESC' : 'ASC');
                    $link_horario = $base_url_sort . "sort_by=horario&sort_order=" . ($sort_by == 'horario' && $sort_order == 'ASC' ? 'DESC' : 'ASC');
                    $link_cargado = $base_url_sort . "sort_by=cargado&sort_order=" . ($sort_by == 'cargado' && $sort_order == 'ASC' ? 'DESC' : 'ASC');
                    $link_estado = $base_url_sort . "sort_by=estado&sort_order=" . ($sort_by == 'estado' && $sort_order == 'ASC' ? 'DESC' : 'ASC');

                    // Iconos visuales (flechitas)
                    $i_sort = '<i class="fa-solid fa-sort" style="opacity:0.3; margin-left:4px;"></i>';
                    $i_asc = '<i class="fa-solid fa-sort-up" style="color:#3b82f6; margin-left:4px;"></i>';
                    $i_desc = '<i class="fa-solid fa-sort-down" style="color:#3b82f6; margin-left:4px;"></i>';

                    $icon_p = $sort_by == 'paciente' ? ($sort_order == 'ASC' ? $i_asc : $i_desc) : $i_sort;
                    $icon_s = $sort_by == 'servicio' ? ($sort_order == 'ASC' ? $i_asc : $i_desc) : $i_sort;
                    $icon_h = $sort_by == 'horario' ? ($sort_order == 'ASC' ? $i_asc : $i_desc) : $i_sort;
                    $icon_c = $sort_by == 'cargado' ? ($sort_order == 'ASC' ? $i_asc : $i_desc) : $i_sort;
                    $icon_e = $sort_by == 'estado' ? ($sort_order == 'ASC' ? $i_asc : $i_desc) : $i_sort;

                    echo "<div class='fecha-grupo-header'>{$titulo_grupo}</div>";
                    echo "<div class='tl-table-wrap'><table class='tl-table'>
                            <thead>
                                <tr>
                                    <th class='col-chk'><input type='checkbox' onclick='toggleAll(this)' style='cursor:pointer; width:16px; height:16px;'></th>
                                    <th class='col-turno' style='width: 80px;'><a href='#' style='color:inherit; text-decoration:none; display:block;'>Nro. Turno</a></th>
                                    <th class='col-paciente'><a href='{$link_paciente}' style='color:inherit; text-decoration:none; display:block;'>Paciente {$icon_p}</a></th>
                                    <th class='col-servicio'><a href='{$link_servicio}' style='color:inherit; text-decoration:none; display:block;'>Servicio / Profesional {$icon_s}</a></th>
                                    <th class='col-horario'><a href='{$link_horario}' style='color:inherit; text-decoration:none; display:block;'>Horario {$icon_h}</a></th>
                                    <th class='col-operador'><a href='{$link_cargado}' style='color:inherit; text-decoration:none; display:block;'>Cargado Por {$icon_c}</a></th>
                                    <th class='col-estado'><a href='{$link_estado}' style='color:inherit; text-decoration:none; display:block;'>Estado {$icon_e}</a></th>
                                    <th class='col-accion'>Acción</th>
                                </tr>
                            </thead>
                            <tbody>";
                }

                $estado = $t['estado'] ?? 'Pendiente';
                $bg_estado = "#fef3c7"; $color_estado = "#92400e";
                if($estado == 'Autorizado') { $bg_estado = "#eff6ff"; $color_estado = "#1e40af"; }
                if($estado == 'Presente') { $bg_estado = "#ecfdf5"; $color_estado = "#047857"; }
                if($estado == 'Atendido') { $bg_estado = "#10b981"; $color_estado = "#ffffff"; }
                if($estado == 'Cancelado') { $bg_estado = "#fef2f2"; $color_estado = "#dc2626"; }
                
                // Extrae e identifica claramente al Operador que sacó el turno
                $creador_final = !empty($t['operador_externo']) ? $t['operador_externo'] : (!empty($t['creador_nombre']) ? $t['creador_nombre'] : 'Sistema');
                
                $datos_json = htmlspecialchars(json_encode([
                    'id' => $t['id'],
                    'paciente' => ($t['apellido'] ?? '') . ', ' . ($t['nombre'] ?? ''),
                    'dni' => $t['dni'] ?? '',
                    'afiliado' => $t['afiliado'] ?? '',
                    'numero_turno' => $t['numero_turno'] ?? '',
                    'tel' => $t['telefono'] ?? '-',
                    'fecha_turno' => $fecha_fmt,
                    'hora_turno' => $hora_fmt,
                    'servicio' => $t['servicio'] ?? '-',
                    'profesional' => $t['profesional'] ?? 'Sin asignar',
                    'estado' => $estado,
                    'practicas' => $t['motivo_visita'] ?? '',
                    'obs' => $t['observaciones'] ?? '',
                    'diagnostico' => $t['diagnostico'] ?? '',
                    'comentario_paciente' => $t['comentario_paciente'] ?? '',
                    'creado_el' => $fecha_creacion_fmt . ' a las ' . $hora_creacion_fmt . ' hs',
                    'creador' => $creador_final
                ]), ENT_QUOTES, 'UTF-8');
            ?>
            
            <tr onclick="if(!event.target.closest('button') && !event.target.closest('a') && !event.target.closest('input')) abrirFichaTurno(<?= $datos_json ?>)">
                <td style="text-align:center;">
                    <input type="checkbox" name="turnos_sel[]" value="<?= $t['id'] ?>" class="chk-turno" style="cursor:pointer; width:16px; height:16px;">
                </td>
                <td style="font-weight: 800; color: #144973; font-size: 0.95rem;">
                    <?= htmlspecialchars($t['numero_turno'] ?? '-') ?>
                </td>
                <td>
                    <span class="text-primary-dark" title="<?= htmlspecialchars(($t['apellido'] ?? '') . ', ' . ($t['nombre'] ?? '')) ?>"><?= htmlspecialchars(($t['apellido'] ?? '') . ', ' . ($t['nombre'] ?? '')) ?></span>
                    <span class="text-muted-small">DNI: <?= htmlspecialchars($t['dni'] ?? '') ?></span>
                </td>
                <td>
                    <span class="text-primary-dark" title="<?= htmlspecialchars($t['servicio'] ?? '-') ?>"><?= htmlspecialchars($t['servicio'] ?? '-') ?></span>
                    <span class="text-muted-small" style="color: #3b82f6;" title="<?= htmlspecialchars($t['profesional'] ?? 'Sin profesional') ?>"><?= htmlspecialchars($t['profesional'] ?? 'Sin profesional') ?></span>
                </td>
                <td>
                    <span style="font-weight: 800; color: #0f172a; font-size: 1rem;"><i class="fa-regular fa-clock" style="color:#64748b;"></i> <?= $hora_fmt ?> hs</span>
                    <span class="text-muted-small"><i class="fa-regular fa-calendar"></i> <?= $fecha_fmt ?></span>
                </td>
                <td>
                    <span style="font-weight: 800; color: #8b5cf6; font-size: 0.85rem; display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"><i class="fa-solid fa-headset"></i> <?= htmlspecialchars($creador_final) ?></span>
                    <span class="text-muted-small" style="font-weight: 600;"><i class="fa-regular fa-calendar-plus"></i> <?= $fecha_creacion_fmt ?> - <?= $hora_creacion_fmt ?> hs</span>
                </td>
                <td>
                    <span class="tl-estado" style="background: <?= $bg_estado ?>; color: <?= $color_estado ?>;">
                        <?= htmlspecialchars($estado) ?>
                    </span>
                </td>
                <td style="text-align: center;">
                    <div style="display: flex; gap: 4px; justify-content: center;">
                        <button type="button" class="tl-btn-icon icon-view" onclick='abrirFichaTurno(<?= $datos_json ?>)'><i class="fa-solid fa-eye"></i></button>
                        <?php if(isset($_SESSION['permisos']) && in_array('modulo_turnos_editar', $_SESSION['permisos'])): ?>
                        <a href="turnos_editar.php?id=<?= $t['id'] ?>" class="tl-btn-icon icon-edit"><i class="fa-solid fa-pen"></i></a>
                        <?php endif; ?>
                        <?php if(isset($_SESSION['permisos']) && in_array('modulo_turnos_eliminar', $_SESSION['permisos'])): ?>
                        <button type="button" class="tl-btn-icon icon-del" onclick="confirmarEliminacion(<?= $t['id'] ?>)"><i class="fa-solid fa-trash"></i></button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endwhile; ?>
            <?php if ($fecha_actual !== null): ?>
                </tbody></table></div> 
            <?php endif; ?>
            
            <?php if($total_paginas > 1): ?>
            <div class="paginacion">
                <?php for($i=1; $i<=$total_paginas; $i++): ?>
                    <a href="turnos_listar.php?pagina=<?= $i ?>&dni=<?= urlencode($filtro_dni) ?>&fecha=<?= urlencode($filtro_fecha) ?>&hora=<?= urlencode($filtro_hora) ?>&estado=<?= urlencode($filtro_estado) ?>&profesional=<?= urlencode($filtro_profesional) ?>&servicio=<?= urlencode($filtro_servicio) ?>&operador=<?= urlencode($filtro_operador) ?>&hora_creacion=<?= urlencode($filtro_hora_creacion) ?>&creado_hoy=<?= $filtro_creado_hoy ?>&nro_turno=<?= urlencode($filtro_nro_turno) ?>&afiliado=<?= urlencode($filtro_afiliado) ?>&contacto=<?= urlencode($filtro_contacto) ?>&detalles=<?= urlencode($filtro_detalles) ?>&sort_by=<?= urlencode($sort_by) ?>&sort_order=<?= urlencode($sort_order) ?>" class="page-link <?= ($i == $pagina_actual) ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
            
        <?php else: ?>
            <div style="text-align: center; padding: 60px 10px;">
                <i class="fa-solid fa-folder-open" style="font-size: 3rem; color: #cbd5e1; margin-bottom: 15px;"></i>
                <h3 style="color: #64748b; margin: 0; font-size:1.1rem; font-weight: 600;">No se encontraron turnos con estos criterios.</h3>
            </div>
        <?php endif; ?>
        </form>
    </div>
</div>
<script>
// Cierra el modal si tocan afuera
document.getElementById('modalFiltros').addEventListener('click', function(e) {
    if(e.target === this) this.classList.remove('show');
});

// Construye la URL para filtrar
function aplicarFiltros() {
    let dni = document.getElementById('filtro_dni').value;
    let fecha = document.getElementById('filtro_fecha').value;
    let hora = document.getElementById('filtro_hora').value;
    let profesional = document.getElementById('filtro_profesional').value;
    let servicio = document.getElementById('filtro_servicio').value;
    let estado = document.getElementById('filtro_estado').value;
    let operador = document.getElementById('filtro_operador').value;
    let hora_creacion = document.getElementById('filtro_hora_creacion').value;
    let nro_turno = document.getElementById('filtro_nro_turno') ? document.getElementById('filtro_nro_turno').value : '';
    let afiliado = document.getElementById('filtro_afiliado') ? document.getElementById('filtro_afiliado').value : '';
    let contacto = document.getElementById('filtro_contacto') ? document.getElementById('filtro_contacto').value : '';
    let detalles = document.getElementById('filtro_detalles') ? document.getElementById('filtro_detalles').value : '';
    
    let url = 'turnos_listar.php?';
    if(dni) url += 'dni=' + encodeURIComponent(dni) + '&';
    if(fecha) url += 'fecha=' + encodeURIComponent(fecha) + '&';
    if(hora) url += 'hora=' + encodeURIComponent(hora) + '&';
    if(profesional) url += 'profesional=' + encodeURIComponent(profesional) + '&';
    if(servicio) url += 'servicio=' + encodeURIComponent(servicio) + '&';
    if(estado) url += 'estado=' + encodeURIComponent(estado) + '&';
    if(operador) url += 'operador=' + encodeURIComponent(operador) + '&';
    if(hora_creacion) url += 'hora_creacion=' + encodeURIComponent(hora_creacion) + '&';
    if(nro_turno) url += 'nro_turno=' + encodeURIComponent(nro_turno) + '&';
    if(afiliado) url += 'afiliado=' + encodeURIComponent(afiliado) + '&';
    if(contacto) url += 'contacto=' + encodeURIComponent(contacto) + '&';
    if(detalles) url += 'detalles=' + encodeURIComponent(detalles) + '&';
    
    // Mantiene el filtro de "Sacados Hoy" si estaba activo
    const params = new URLSearchParams(window.location.search);
    if (params.has('creado_hoy')) {
        url += 'creado_hoy=1&';
    }

    // Mantiene el orden actual al aplicar filtros nuevos
    let sort_by = params.get('sort_by') || 'cargado';
    let sort_order = params.get('sort_order') || 'DESC';
    url += 'sort_by=' + encodeURIComponent(sort_by) + '&sort_order=' + encodeURIComponent(sort_order);
    
    window.location.href = url;
}

function aplicarBusquedaRapida() {
    let dni = document.getElementById('busqueda_rapida').value;
    window.location.href = 'turnos_listar.php?dni=' + encodeURIComponent(dni);
}

function abrirFichaTurno(data) {
    let practicasFmt = data.practicas ? `<div class="code-box">${data.practicas.replace(/\|/g, '<br>')}</div>` : '<div class="code-box" style="color:#94a3b8;">No registradas.</div>';
    let obsFmt = data.obs ? `<div class="code-box">${data.obs}</div>` : '<div class="code-box" style="color:#94a3b8;">Sin observaciones.</div>';
    let diagFmt = data.diagnostico ? `<div class="code-box" style="color:#0f172a; font-weight:600;">${data.diagnostico}</div>` : '<div class="code-box" style="color:#94a3b8;">Sin diagnóstico registrado.</div>';
    
    let btnConstancia = (data.estado === 'Atendido' || data.estado === 'Presente') ? 
        `<a href="tickets/checkout_${data.id}.pdf" target="_blank" style="display:block; text-align:center; background:#10b981; color:white; padding:12px; border-radius:8px; text-decoration:none; font-weight:800; margin-top:20px; text-transform:uppercase; box-shadow:0 4px 6px rgba(16,185,129,0.3);"><i class="fa-solid fa-file-pdf"></i> Descargar Constancia / Ticket</a>` : '';

    let canceladoAlert = '';
    if (data.estado === 'Cancelado') {
        let motivoStr = "Cancelación a pedido del paciente/operador";
        if (data.comentario_paciente && data.comentario_paciente.includes('Motivo Baja:')) {
            motivoStr = data.comentario_paciente.split('Motivo Baja:')[1].trim();
        }
        canceladoAlert = `
            <div style="background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; padding: 12px; margin-bottom: 15px;">
                <h4 style="margin: 0 0 5px 0; color: #dc2626; font-size: 0.95rem; display: flex; align-items: center; gap: 5px;">
                    <i class="fa-solid fa-circle-exclamation"></i> Turno Cancelado
                </h4>
                <p style="margin: 0; color: #991b1b; font-size: 0.85rem; font-weight: 500;"><strong>Motivo:</strong> ${motivoStr}</p>
            </div>
        `;
    }

    Swal.fire({
        html: `
            <div class="swal-header">Detalle del Turno</div>
            <div class="swal-custom-content">
                ${canceladoAlert}
                <ul class="info-list-compact">
                    <li><label>Paciente</label><span>${data.paciente}</span></li>
                    <li><label>DNI / Afiliado</label><span>${data.dni || '-'} | ${data.afiliado || '-'}</span></li>
                    ${data.numero_turno ? `<li><label>Número de Turno</label><span>${data.numero_turno}</span></li>` : ''}
                    <li><label>Teléfono</label><span>${data.tel}</span></li>
                    <li><label>Turno designado para el día</label><span style="color:#10b981;">${data.fecha_turno} - ${data.hora_turno} hs</span></li>
                    <li><label>Médico / Servicio</label><span>${data.profesional} (${data.servicio})</span></li>
                    <li><label>Auditoría de Carga</label><span style="color:#8b5cf6;">Operador: ${data.creador} <br> Registrado el ${data.creado_el}</span></li>
                </ul>

                <div class="block-full">
                    <h4 class="block-title">Prácticas</h4>
                    ${practicasFmt}
                </div>

                <div class="block-full">
                    <h4 class="block-title">Observaciones</h4>
                    ${obsFmt}
                </div>

                <div class="block-full" style="background:#eff6ff; border-color:#bfdbfe;">
                    <h4 class="block-title" style="color:#1e40af;"><i class="fa-solid fa-stethoscope"></i> Diagnóstico Médico</h4>
                    ${diagFmt}
                </div>

                ${btnConstancia}
            </div>
        `,
        padding: 0,
        showCloseButton: true,
        showConfirmButton: false,
        customClass: { popup: 'swal-compact-mobile' }
    });
}

function confirmarEliminacion(id) {
    Swal.fire({
        title: '¿Eliminar Turno?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Eliminar'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = `turnos_listar.php?eliminar_id=${id}`;
        }
    });
}

// Selecciona/Deselecciona solo los turnos de ese día específico (esa tabla)
function toggleAll(source) {
    let table = source.closest('table');
    let checkboxes = table.querySelectorAll('.chk-turno');
    checkboxes.forEach(chk => { chk.checked = source.checked; });
}

// Selecciona/Deselecciona TODO lo que hay en la pantalla (Global)
function seleccionarTodoGlobal() {
    let checkboxes = document.querySelectorAll('.chk-turno');
    let allChecked = Array.from(checkboxes).every(c => c.checked); // Verifica si ya están todos marcados
    checkboxes.forEach(chk => { chk.checked = !allChecked; });
    
    // También tildamos/destildamos las cabeceras de cada día
    let headerChks = document.querySelectorAll('th input[type="checkbox"]');
    headerChks.forEach(chk => { chk.checked = !allChecked; });
}

function confirmarEliminacionMasiva() {
    let seleccionados = document.querySelectorAll('.chk-turno:checked');
    if(seleccionados.length === 0) {
        Swal.fire('Atención', 'Debes seleccionar al menos un turno marcando la casilla de la izquierda.', 'info');
        return;
    }
    
    Swal.fire({
        title: `¿Eliminar ${seleccionados.length} turnos?`,
        text: 'Esta acción no se puede deshacer y limpiará la base de datos.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Eliminar Todos'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('form_masivo').submit();
        }
    });
}
</script>
<?php require_once 'includes/footer.php'; ?>
