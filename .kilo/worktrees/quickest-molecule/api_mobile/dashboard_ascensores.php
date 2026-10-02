<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
require_once '../includes/conexion.php';

$dias   = ['Domingo','Lunes','Martes','Miercoles','Jueves','Viernes','Sabado'];
$meses  = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$out['fecha_actual'] = $dias[date('w')] . ', ' . date('j') . ' de ' . $meses[date('n')] . ' de ' . date('Y');

// ══════════════════════════════════════════════════
// 1. ASCENSORES — lógica IDÉNTICA a ascensores.php (Bitácora)
//    estado se calcula desde fallas_activas, no desde la columna estado
// ══════════════════════════════════════════════════
$ascensores = [];
$q = $pdo->query("SELECT a.id_ascensor as id, a.nombre, a.ubicacion, a.nro_serie,
    e.nombre as nombre_empresa,
    (SELECT COUNT(*) FROM ascensor_incidencias WHERE id_ascensor = a.id_ascensor AND estado != 'resuelto') as fallas_activas,
    (SELECT COUNT(*) FROM ascensor_incidencias WHERE id_ascensor = a.id_ascensor) as total_incidencias,
    (SELECT MAX(fecha_reporte) FROM ascensor_incidencias WHERE id_ascensor = a.id_ascensor) as ultima_falla
    FROM ascensores a
    LEFT JOIN empresas_mantenimiento e ON a.id_empresa = e.id_empresa
    ORDER BY a.nombre ASC");
if ($q) $ascensores = $q->fetchAll(PDO::FETCH_ASSOC);

$total = count($ascensores);
$op    = 0; $falla = 0; $mant = 0;
foreach ($ascensores as $a) {
    // Mismo cálculo que ascensores.php
    if ((int)$a['fallas_activas'] > 0) $falla++;
    else $op++;
}

$out['total']    = $total;
$out['card1_val'] = $op;     // Operativos
$out['card2_val'] = $falla;  // Con Falla

$out['pct_izq'] = $total > 0 ? round(($op / $total) * 100) : 0;
$out['pct_der'] = $total > 0 ? round(($falla / $total) * 100) : 0;
$out['val_izq_a'] = $op;
$out['val_izq_b'] = 0;
$out['val_der_a'] = $falla;
$out['val_der_b'] = 0;

// ══════════════════════════════════════════════════
// 2. INCIDENCIAS — tabla ascensor_incidencias
// ══════════════════════════════════════════════════
$abiertas = 0; $resueltas = 0; $hoy = 0; $mes = 0;
$q = $pdo->query("SELECT
    SUM(CASE WHEN estado != 'resuelto' THEN 1 ELSE 0 END)                                    AS abiertas,
    SUM(CASE WHEN estado = 'resuelto' THEN 1 ELSE 0 END)                                      AS resueltas,
    SUM(CASE WHEN DATE(fecha_reporte) = CURDATE() THEN 1 ELSE 0 END)                          AS hoy,
    SUM(CASE WHEN MONTH(fecha_reporte) = MONTH(CURDATE()) AND YEAR(fecha_reporte) = YEAR(CURDATE()) THEN 1 ELSE 0 END) AS mes
    FROM ascensor_incidencias");
if ($q && $r = $q->fetch(PDO::FETCH_ASSOC)) {
    $abiertas  = (int)$r['abiertas'];
    $resueltas = (int)$r['resueltas'];
    $hoy       = (int)$r['hoy'];
    $mes       = (int)$r['mes'];
}
$out['chip_a'] = $abiertas;
$out['chip_b'] = $mes;

// ══════════════════════════════════════════════════
// 3. HORA PICO
// ══════════════════════════════════════════════════
$out['hora_pico'] = 'Sin datos hoy';
$q = $pdo->query("SELECT HOUR(fecha_reporte) as h, COUNT(*) as c FROM ascensor_incidencias WHERE DATE(fecha_reporte) = CURDATE() GROUP BY h ORDER BY c DESC LIMIT 1");
if ($q && $r = $q->fetch(PDO::FETCH_ASSOC)) {
    $h = (int)$r['h'];
    $out['hora_pico'] = sprintf('%02d:00 %s', $h % 12 ?: 12, $h >= 12 ? 'PM' : 'AM');
} else {
    // Usar hora con más incidencias de los últimos 30 días
    $q2 = $pdo->query("SELECT HOUR(fecha_reporte) as h, COUNT(*) as c FROM ascensor_incidencias WHERE fecha_reporte >= DATE_SUB(NOW(), INTERVAL 30 DAY) GROUP BY h ORDER BY c DESC LIMIT 1");
    if ($q2 && $r2 = $q2->fetch(PDO::FETCH_ASSOC)) {
        $h = (int)$r2['h'];
        $out['hora_pico'] = sprintf('%02d:00 %s (últ. 30d)', $h % 12 ?: 12, $h >= 12 ? 'PM' : 'AM');
    }
}

// ══════════════════════════════════════════════════
// 4. MINI CHARTS — prioridad, estado, operativo/falla
// ══════════════════════════════════════════════════
// Mini 1: Prioridad (alta/emergencia vs baja/media)
$alta = 0; $baja = 0;
$q = $pdo->query("SELECT prioridad, COUNT(*) as c FROM ascensor_incidencias GROUP BY prioridad");
if ($q) {
    while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
        $p = strtolower($r['prioridad'] ?? '');
        if (in_array($p, ['alta','emergencia','critica'])) $alta += (int)$r['c'];
        else $baja += (int)$r['c'];
    }
}
if ($alta + $baja == 0) { $alta = $falla; $baja = max(0, $mes - $falla); }
$out['mini1_a'] = $alta;
$out['mini1_b'] = $baja;

// Mini 2: Abiertas vs Resueltas
$out['mini2_a'] = $abiertas;
$out['mini2_b'] = $resueltas;

// Mini 3: Operativos vs No Operativos (del estado real)
$out['mini3_a'] = $op;
$out['mini3_b'] = $falla + $mant;

// Eficacia = % ascensores operativos
$out['eficacia']    = $total > 0 ? round(($op / $total) * 100, 1) : 0;
$out['tiempo_prom'] = "1h 30m";
$out['tiempo_max']  = "4h";
$out['combinado']   = $mes > 0 ? $mes : ($abiertas + $resueltas);

// ══════════════════════════════════════════════════
// 5. TOP ASCENSORES — ranking por incidencias históricas
// ══════════════════════════════════════════════════
$out['top1']     = [];
$out['top1_key'] = 'nombre';
$q = $pdo->query("SELECT a.nombre, COUNT(i.id_incidencia) as cantidad
    FROM ascensores a
    LEFT JOIN ascensor_incidencias i ON i.id_ascensor = a.id_ascensor
    GROUP BY a.id_ascensor, a.nombre
    ORDER BY cantidad DESC
    LIMIT 3");
if ($q) {
    while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
        // Nombre corto: primeras 30 caracteres
        $nombre = strlen($r['nombre']) > 35 ? substr($r['nombre'], 0, 32) . '…' : $r['nombre'];
        $out['top1'][] = ['nombre' => $nombre, 'cantidad' => (int)$r['cantidad']];
    }
}

// TOP 2: Tipos de falla más reportados
$out['top2']     = [];
$out['top2_key'] = 'nombre';
$q = $pdo->query("SELECT titulo as nombre, COUNT(*) as cantidad
    FROM ascensor_incidencias
    GROUP BY titulo
    ORDER BY cantidad DESC
    LIMIT 3");
if ($q) {
    while ($r = $q->fetch(PDO::FETCH_ASSOC)) {
        $nombre = strlen($r['nombre']) > 35 ? substr($r['nombre'], 0, 32) . '…' : $r['nombre'];
        $out['top2'][] = ['nombre' => $nombre, 'cantidad' => (int)$r['cantidad']];
    }
}
if (empty($out['top2'])) $out['top2'] = [['nombre' => 'Sin incidencias registradas', 'cantidad' => 0]];

// ══════════════════════════════════════════════════
// 6. ÁREA TÉCNICA
// ══════════════════════════════════════════════════
$out['area_card1_val'] = $hoy;   // incidencias hoy
$out['area_card2_val'] = $falla; // ascensores con falla activa

// ══════════════════════════════════════════════════
// 7. PIE CHART GLOBAL
// ══════════════════════════════════════════════════
$out['pie_a'] = $op;
$out['pie_b'] = $falla;
$out['pie_c'] = $mant;

echo json_encode($out);
