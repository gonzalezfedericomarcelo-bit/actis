<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
require_once '../includes/conexion.php';

$dias = ['Domingo','Lunes','Martes','Miercoles','Jueves','Viernes','Sabado'];
$meses = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$out['fecha_actual'] = $dias[date('w')] . ', ' . date('j') . ' de ' . $meses[date('n')] . ' de ' . date('Y');

// Ingresos
$ingresos_hoy = 0; $rechazados_hoy = 0; $ingresos_mes = 0;
$q = $conexion->query("SELECT SUM(CASE WHEN DATE(fecha_hora)=CURDATE() THEN 1 ELSE 0 END) as hoy, COUNT(*) as mes FROM registro_ingresos WHERE MONTH(fecha_hora)=MONTH(CURDATE())");
if ($q && $r=$q->fetch_assoc()) { $ingresos_hoy=(int)$r['hoy']; $ingresos_mes=(int)$r['mes']; }
$q = $conexion->query("SELECT COUNT(*) as c FROM registro_rechazados WHERE DATE(fecha_hora)=CURDATE()");
if ($q && $r=$q->fetch_assoc()) $rechazados_hoy=(int)$r['c'];
$rechazados_mes = 0;
$q = $conexion->query("SELECT COUNT(*) as c FROM registro_rechazados WHERE MONTH(fecha_hora)=MONTH(CURDATE())");
if ($q && $r=$q->fetch_assoc()) $rechazados_mes=(int)$r['c'];

$total_dia = $ingresos_hoy + $rechazados_hoy;
$out['total'] = $ingresos_mes;
$out['card1_val'] = $ingresos_hoy;
$out['card2_val'] = $rechazados_hoy;
$out['pct_izq'] = $total_dia > 0 ? round(($ingresos_hoy/$total_dia)*100) : 0;
$rondas_hoy = 0;
$q = $conexion->query("SELECT COUNT(*) as c FROM seguridad_rondas WHERE DATE(fecha_hora)=CURDATE()");
if ($q && $r=$q->fetch_assoc()) $rondas_hoy=(int)$r['c'];
$out['pct_der'] = min(100, $rondas_hoy * 10); // escala de rondas
$out['val_izq_a'] = $ingresos_hoy; $out['val_izq_b'] = 0;
$out['val_der_a'] = $rondas_hoy; $out['val_der_b'] = 0;

// Llaves
$llaves = 0;
$q = $conexion->query("SELECT COUNT(*) as c FROM seguridad_prestamos_llaves");
if ($q && $r=$q->fetch_assoc()) $llaves=(int)$r['c'];
$out['chip_a'] = $rondas_hoy; $out['chip_b'] = $llaves;

// Hora pico ingresos
$out['hora_pico'] = 'N/A';
$q = $conexion->query("SELECT HOUR(fecha_hora) as h, COUNT(*) as c FROM registro_ingresos WHERE DATE(fecha_hora)=CURDATE() GROUP BY h ORDER BY c DESC LIMIT 1");
if ($q && $r=$q->fetch_assoc()) { $h=(int)$r['h']; $out['hora_pico']=sprintf('%02d:00 %s',$h%12?:12,$h>=12?'PM':'AM'); }

// Mini charts
$incidencias = 0;
$q = $conexion->query("SELECT COUNT(*) as c FROM seguridad_incidencias WHERE DATE(fecha_reporte)=CURDATE()");
if ($q && $r=$q->fetch_assoc()) $incidencias=(int)$r['c'];
$out['mini1_a'] = $ingresos_hoy; $out['mini1_b'] = $rechazados_hoy;
$out['mini2_a'] = $rondas_hoy; $out['mini2_b'] = max(1,$rondas_hoy);
$out['mini3_a'] = $incidencias; $out['mini3_b'] = max(1, $rondas_hoy);
$out['eficacia'] = $total_dia>0 ? round(($ingresos_hoy/$total_dia)*100,1) : 0;
$out['tiempo_prom'] = "2m"; $out['tiempo_max'] = "5m"; $out['combinado'] = $ingresos_mes;

// Top motivos ingreso
$out['top1'] = []; $out['top1_key'] = 'nombre';
$q = $conexion->query("SELECT motivo as nombre, COUNT(*) as cantidad FROM registro_ingresos WHERE DATE(fecha_hora)=CURDATE() AND motivo IS NOT NULL AND motivo != '' GROUP BY motivo ORDER BY cantidad DESC LIMIT 3");
if ($q) { while($r=$q->fetch_assoc()) $out['top1'][]=['nombre'=>$r['nombre'],'cantidad'=>(int)$r['cantidad']]; }
if (empty($out['top1'])) $out['top1']=[['nombre'=>'Sin datos de motivos','cantidad'=>0]];

// Top motivos rechazo
$out['top2'] = []; $out['top2_key'] = 'nombre';
$q = $conexion->query("SELECT motivo as nombre, COUNT(*) as cantidad FROM registro_rechazados WHERE MONTH(fecha_hora)=MONTH(CURDATE()) GROUP BY motivo ORDER BY cantidad DESC LIMIT 3");
if ($q) { while($r=$q->fetch_assoc()) $out['top2'][]=['nombre'=>$r['nombre'],'cantidad'=>(int)$r['cantidad']]; }
if (empty($out['top2'])) $out['top2']=[['nombre'=>'Sin datos','cantidad'=>0]];

$out['area_card1_val'] = $ingresos_hoy; $out['area_card2_val'] = $rondas_hoy;
$out['pie_a'] = $ingresos_hoy; $out['pie_b'] = $rechazados_hoy; $out['pie_c'] = $rondas_hoy;
echo json_encode($out);
