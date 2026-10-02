<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
require_once '../includes/conexion.php';

$dias  = ['Domingo','Lunes','Martes','Miercoles','Jueves','Viernes','Sabado'];
$meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$out['fecha_actual'] = $dias[date('w')] . ', ' . date('j') . ' de ' . $meses[date('n')] . ' de ' . date('Y');

// ══════════════════════════════════════════════════
// 1. TAREAS — log_tareas  (cols: id_tarea, estado, fecha_creacion, prioridad, id_categoria, id_creador)
// ══════════════════════════════════════════════════
$tareas_abiertas = 0; $tareas_cerradas = 0; $tareas_hoy = 0; $tareas_mes = 0;
try {
    $q = $pdo->query("SELECT
        SUM(CASE WHEN estado NOT IN ('completada','cancelada') THEN 1 ELSE 0 END) AS abiertas,
        SUM(CASE WHEN estado IN ('completada','cancelada') THEN 1 ELSE 0 END)     AS cerradas,
        SUM(CASE WHEN DATE(fecha_creacion) = CURDATE() THEN 1 ELSE 0 END)         AS hoy,
        SUM(CASE WHEN MONTH(fecha_creacion)=MONTH(CURDATE()) AND YEAR(fecha_creacion)=YEAR(CURDATE()) THEN 1 ELSE 0 END) AS mes
        FROM log_tareas");
    if ($q && $r = $q->fetch()) {
        $tareas_abiertas = (int)$r['abiertas'];
        $tareas_cerradas = (int)$r['cerradas'];
        $tareas_hoy      = (int)$r['hoy'];
        $tareas_mes      = (int)$r['mes'];
    }
} catch (Exception $e) {}

// ══════════════════════════════════════════════════
// 2. PEDIDOS — log_pedidos_trabajo  (cols: id_pedido, estado_pedido, fecha_emision, prioridad)
// ══════════════════════════════════════════════════
$pedidos_pendientes = 0; $pedidos_aprobados = 0; $pedidos_mes = 0;
try {
    $q = $pdo->query("SELECT
        SUM(CASE WHEN estado_pedido IN ('pendiente_encargado','pendiente_firma_remota','pendiente') THEN 1 ELSE 0 END) AS pendientes,
        SUM(CASE WHEN estado_pedido IN ('aprobado','completado','finalizado') THEN 1 ELSE 0 END)                        AS aprobados,
        SUM(CASE WHEN MONTH(fecha_emision)=MONTH(CURDATE()) AND YEAR(fecha_emision)=YEAR(CURDATE()) THEN 1 ELSE 0 END) AS mes
        FROM log_pedidos_trabajo");
    if ($q && $r = $q->fetch()) {
        $pedidos_pendientes = (int)$r['pendientes'];
        $pedidos_aprobados  = (int)$r['aprobados'];
        $pedidos_mes        = (int)$r['mes'];
    }
} catch (Exception $e) {}

// ══════════════════════════════════════════════════
// 3. ASISTENCIAS — log_asistencia_partes  (cols: id_parte, fecha, fecha_creacion, estado)
// ══════════════════════════════════════════════════
$asist_hoy = 0; $asist_mes = 0;
try {
    $q = $pdo->query("SELECT
        SUM(CASE WHEN fecha = CURDATE() THEN 1 ELSE 0 END) AS hoy,
        SUM(CASE WHEN MONTH(fecha)=MONTH(CURDATE()) AND YEAR(fecha)=YEAR(CURDATE()) THEN 1 ELSE 0 END) AS mes
        FROM log_asistencia_partes");
    if ($q && $r = $q->fetch()) { $asist_hoy=(int)$r['hoy']; $asist_mes=(int)$r['mes']; }
} catch (Exception $e) {}

// ══════════════════════════════════════════════════
// 4. CARDS PRINCIPALES
// ══════════════════════════════════════════════════
$total_ops = $tareas_abiertas + $pedidos_pendientes;
$out['total']     = $total_ops;
$out['card1_val'] = $tareas_abiertas;
$out['card2_val'] = $pedidos_pendientes;

$total_t = $tareas_abiertas + $tareas_cerradas;
$total_p = $pedidos_pendientes + $pedidos_aprobados;
$out['pct_izq']   = $total_t > 0 ? round(($tareas_abiertas / $total_t) * 100) : 0;
$out['pct_der']   = $total_p > 0 ? round(($pedidos_pendientes / $total_p) * 100) : 0;
$out['val_izq_a'] = $tareas_abiertas;
$out['val_izq_b'] = $tareas_cerradas;
$out['val_der_a'] = $pedidos_pendientes;
$out['val_der_b'] = $pedidos_aprobados;

$out['chip_a'] = $tareas_mes;
$out['chip_b'] = $pedidos_mes;

// ══════════════════════════════════════════════════
// 5. HORA PICO
// ══════════════════════════════════════════════════
$out['hora_pico'] = 'Sin datos hoy';
try {
    $q = $pdo->query("SELECT HOUR(fecha_creacion) as h, COUNT(*) as c FROM log_tareas WHERE DATE(fecha_creacion)=CURDATE() GROUP BY h ORDER BY c DESC LIMIT 1");
    if ($q && $r = $q->fetch()) { $h=(int)$r['h']; $out['hora_pico']=sprintf('%02d:00 %s',$h%12?:12,$h>=12?'PM':'AM'); }
    else {
        $q2 = $pdo->query("SELECT HOUR(fecha_creacion) as h, COUNT(*) as c FROM log_tareas WHERE fecha_creacion>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY h ORDER BY c DESC LIMIT 1");
        if ($q2 && $r2=$q2->fetch()) { $h=(int)$r2['h']; $out['hora_pico']=sprintf('%02d:00 %s (últ.30d)',$h%12?:12,$h>=12?'PM':'AM'); }
    }
} catch (Exception $e) {}

// ══════════════════════════════════════════════════
// 6. MINI CHARTS
// ══════════════════════════════════════════════════
$alta_t=0; $baja_t=0;
try {
    $q = $pdo->query("SELECT prioridad, COUNT(*) as c FROM log_tareas WHERE estado NOT IN ('completada','cancelada') GROUP BY prioridad");
    if ($q) { while($r=$q->fetch()){ $p=strtolower($r['prioridad']??''); if(in_array($p,['alta','urgente','critica'])) $alta_t+=(int)$r['c']; else $baja_t+=(int)$r['c']; } }
} catch (Exception $e) {}

$ped_urg=0; $ped_norm=0;
try {
    $q = $pdo->query("SELECT prioridad, COUNT(*) as c FROM log_pedidos_trabajo GROUP BY prioridad");
    if ($q) { while($r=$q->fetch()){ $p=strtolower($r['prioridad']??''); if(in_array($p,['urgente','alta'])) $ped_urg+=(int)$r['c']; else $ped_norm+=(int)$r['c']; } }
} catch (Exception $e) {}

$kan_pend=0; $kan_done=0;
try {
    $q = $pdo->query("SELECT estado, COUNT(*) as c FROM log_agenda_interna GROUP BY estado");
    if ($q) { while($r=$q->fetch()){ $e=strtolower($r['estado']??''); if(strpos($e,'complet')!==false||strpos($e,'archiv')!==false) $kan_done+=(int)$r['c']; else $kan_pend+=(int)$r['c']; } }
} catch (Exception $e) {}

$out['mini1_a'] = $alta_t;
$out['mini1_b'] = $baja_t;
$out['mini2_a'] = $ped_urg;
$out['mini2_b'] = $ped_norm;
$out['mini3_a'] = $kan_pend;
$out['mini3_b'] = $kan_done;

$out['eficacia']    = $total_t > 0 ? round(($tareas_cerradas / $total_t) * 100, 1) : 0;
$out['tiempo_prom'] = "2h";
$out['tiempo_max']  = "8h";
$out['combinado']   = $tareas_mes + $pedidos_mes;

// ══════════════════════════════════════════════════
// 7. TOP RANKINGS
// ══════════════════════════════════════════════════
$out['top1'] = []; $out['top1_key'] = 'nombre';
try {
    $q = $pdo->query("SELECT u.nombre_completo as nombre, COUNT(*) as cantidad
        FROM log_tareas_asignaciones ta JOIN usuarios u ON ta.id_usuario = u.id
        GROUP BY ta.id_usuario, u.nombre_completo ORDER BY cantidad DESC LIMIT 3");
    if ($q) { while($r=$q->fetch()) $out['top1'][]=['nombre'=>$r['nombre'],'cantidad'=>(int)$r['cantidad']]; }
} catch (Exception $e) {}

if (empty($out['top1'])) {
    try {
        $q2 = $pdo->query("SELECT u.nombre_completo as nombre, COUNT(*) as cantidad
            FROM log_tareas t JOIN usuarios u ON t.id_creador = u.id
            GROUP BY t.id_creador, u.nombre_completo ORDER BY cantidad DESC LIMIT 3");
        if ($q2) { while($r=$q2->fetch()) $out['top1'][]=['nombre'=>$r['nombre'],'cantidad'=>(int)$r['cantidad']]; }
    } catch (Exception $e) {}
}
if (empty($out['top1'])) $out['top1'] = [['nombre'=>'Sin datos','cantidad'=>0]];

$out['top2'] = []; $out['top2_key'] = 'nombre';
try {
    $q = $pdo->query("SELECT c.nombre, COUNT(t.id_tarea) as cantidad
        FROM log_tareas t LEFT JOIN log_categorias c ON t.id_categoria = c.id_categoria
        GROUP BY t.id_categoria, c.nombre ORDER BY cantidad DESC LIMIT 3");
    if ($q) { while($r=$q->fetch()) $out['top2'][]=['nombre'=>$r['nombre']??'Sin categoría','cantidad'=>(int)$r['cantidad']]; }
} catch (Exception $e) {}
if (empty($out['top2'])) $out['top2'] = [['nombre'=>'Sin datos','cantidad'=>0]];

// ══════════════════════════════════════════════════
// 8. ÁREA Y PIE
// ══════════════════════════════════════════════════
$out['area_card1_val'] = $tareas_hoy;
$out['area_card2_val'] = $asist_hoy;
$out['pie_a'] = $tareas_abiertas;
$out['pie_b'] = $tareas_cerradas;
$out['pie_c'] = $pedidos_pendientes;

echo json_encode($out);
