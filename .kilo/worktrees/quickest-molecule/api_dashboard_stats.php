<?php
session_start();
if(!isset($_SESSION['usuario_id'])) { 
    http_response_code(403);
    echo json_encode(['error' => 'No autorizado']);
    exit; 
}
require_once 'includes/conexion.php';
date_default_timezone_set('America/Argentina/Buenos_Aires');
$hoy = date('Y-m-d');
$hace_una_hora = date('Y-m-d H:i:s', strtotime('-1 hour'));

$data = [];

// 1. Estado del Sistema
$res_estado = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'estado_manual'");
$data['estado_sistema'] = ($res_estado && $res_estado->num_rows > 0) ? strtoupper($res_estado->fetch_assoc()['estado']) : 'DESCONOCIDO';

// 2. Tickets en Espera (Turnos de hoy marcados como 'Presente')
$res_espera = $conexion->query("SELECT COUNT(*) as c FROM turnos WHERE fecha_turno = '$hoy' AND estado = 'Presente'");
$data['tickets_espera'] = $res_espera ? (int)$res_espera->fetch_assoc()['c'] : 0;

// 3. Tiempo Promedio de Atención (Últimos 60 min)
$res_prom = $conexion->query("SELECT AVG(tiempo_operacion) as prom FROM estadisticas_totem WHERE fecha_hora >= '$hace_una_hora' AND tiempo_operacion > 0");
$data['tiempo_promedio'] = $res_prom && $res_prom->num_rows > 0 ? round($res_prom->fetch_assoc()['prom'], 1) : 0;

// 4. Nivel de Insumo (Papel)
$res_config = $conexion->query("SELECT tipo, estado FROM totem_config WHERE tipo IN ('tickets_impresos', 'capacidad_rollo')");
$impresos = 0; $capacidad = 120;
while($r = $res_config->fetch_assoc()){
    if($r['tipo'] == 'tickets_impresos') $impresos = (int)$r['estado'];
    if($r['tipo'] == 'capacidad_rollo') $capacidad = (int)$r['estado'];
}
$papel_restante = max(0, $capacidad - $impresos);
$data['nivel_papel'] = $capacidad > 0 ? round(($papel_restante / $capacidad) * 100) : 0;

// 5. Temperatura del CPU & 8. Uso Memoria/CPU (Requiere entorno Linux)
$load = function_exists('sys_getloadavg') ? sys_getloadavg() : [0, 0, 0];
$data['uso_cpu'] = round($load[0] * 100 / 4, 1); // Asumiendo 4 cores, ajustar según server
$data['temp_cpu'] = rand(45, 65); // Placeholder. En prod: exec('cat /sys/class/thermal/thermal_zone0/temp') / 1000

// 6. Heartbeat del Tótem (Última interacción registrada)
$res_hb = $conexion->query("SELECT MAX(fecha_hora) as hb FROM estadisticas_totem");
$data['heartbeat'] = ($res_hb && $hb = $res_hb->fetch_assoc()['hb']) ? date('H:i:s', strtotime($hb)) : '--:--:--';

// 7. Tasa de Error Horaria (Simulado con fallos/cancelaciones recientes si aplica)
$data['tasa_error'] = rand(0, 2); // Reemplazar con lógica de log de errores reales del Tótem

// 9. Total Tickets Hoy (Validación vs Asistencia)
$res_totales = $conexion->query("SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN modo = 'VALIDACION' THEN 1 ELSE 0 END) as val,
    SUM(CASE WHEN modo = 'ASISTENCIA' THEN 1 ELSE 0 END) as asis
    FROM estadisticas_totem WHERE DATE(fecha_hora) = '$hoy'");
$row_totales = $res_totales->fetch_assoc();
$data['tot_tkts'] = $row_totales['total'] ?? 0;
$data['val_tkts'] = $row_totales['val'] ?? 0;
$data['asis_tkts'] = $row_totales['asis'] ?? 0;

// 10. Estado de Impresora
$data['estado_impresora'] = $data['nivel_papel'] > 5 ? 'OK' : 'ALERTA';

// 11. Latencia de Red
$data['latencia_red'] = rand(15, 45); // Placeholder. Reemplazable por ping interno o tiempo de respuesta de BD.

// 12. Throughput (Tickets procesados en la última hora)
$res_tp = $conexion->query("SELECT COUNT(*) as c FROM estadisticas_totem WHERE fecha_hora >= '$hace_una_hora'");
$data['throughput'] = $res_tp ? (int)$res_tp->fetch_assoc()['c'] : 0;

header('Content-Type: application/json');
echo json_encode($data);
exit;