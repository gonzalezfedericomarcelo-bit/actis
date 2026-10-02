<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
require_once '../includes/conexion.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');
$hoy = date('Y-m-d');

// --- 1. TELEMETRÍA ORIGINAL DE LA WEB ---

$t_totales = $conexion->query("SELECT COUNT(*) as cant FROM turnos WHERE fecha_turno = '$hoy'")->fetch_assoc()['cant'] ?? 0;
$t_pendientes = $conexion->query("SELECT COUNT(*) as cant FROM turnos WHERE fecha_turno = '$hoy' AND estado = 'Pendiente'")->fetch_assoc()['cant'] ?? 0;
$t_autorizados = $conexion->query("SELECT COUNT(*) as cant FROM turnos WHERE fecha_turno = '$hoy' AND estado = 'Autorizado'")->fetch_assoc()['cant'] ?? 0;
$t_presentes = $conexion->query("SELECT COUNT(*) as cant FROM turnos WHERE fecha_turno = '$hoy' AND estado = 'Presente'")->fetch_assoc()['cant'] ?? 0;
$t_cancelados = $conexion->query("SELECT COUNT(*) as cant FROM turnos WHERE fecha_turno = '$hoy' AND estado = 'Cancelado'")->fetch_assoc()['cant'] ?? 0;
$t_atendidos = $conexion->query("SELECT COUNT(*) as cant FROM turnos WHERE fecha_turno = '$hoy' AND estado = 'Atendido'")->fetch_assoc()['cant'] ?? 0;

$totem_hoy = $conexion->query("SELECT COUNT(*) as total, SUM(CASE WHEN modo = 'VALIDACION' THEN 1 ELSE 0 END) as val, SUM(CASE WHEN modo = 'ASISTENCIA' THEN 1 ELSE 0 END) as asis, AVG(tiempo_operacion) as prom_t, MIN(CASE WHEN tiempo_operacion > 0 THEN tiempo_operacion ELSE 99 END) as vel_max, MAX(tiempo_operacion) as max_t FROM estadisticas_totem WHERE DATE(fecha_hora) = '$hoy'")->fetch_assoc();
$tkts_total = $totem_hoy['total'] ?? 0;
$tkts_val = $totem_hoy['val'] ?? 0;
$tkts_asis = $totem_hoy['asis'] ?? 0;
$tiempo_prom = round($totem_hoy['prom_t'] ?? 0, 1);
$tiempo_max = round($totem_hoy['max_t'] ?? 0, 1);

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

$pac_nuevos = $conexion->query("SELECT COUNT(DISTINCT paciente_id) as cant FROM turnos WHERE fecha_turno = '$hoy'")->fetch_assoc()['cant'] ?? 0;

$q_top_med = $conexion->query("SELECT especialidad as servicio, COUNT(*) as cant FROM turnos WHERE fecha_turno = '$hoy' AND estado NOT IN ('Cancelado', 'Ausente') GROUP BY especialidad ORDER BY cant DESC LIMIT 5");
$top_3_med = [];
if ($q_top_med) {
    while ($r = $q_top_med->fetch_assoc()) {
        $top_3_med[] = $r;
    }
}
$top_med = count($top_3_med) > 0 ? $top_3_med[0] : ['servicio'=>'N/A', 'cant'=>0];

$q_top_totem = $conexion->query("SELECT servicio, COUNT(*) as cant FROM estadisticas_totem WHERE DATE(fecha_hora) = '$hoy' GROUP BY servicio ORDER BY cant DESC LIMIT 1");
$top_totem = ($q_top_totem && $q_top_totem->num_rows > 0) ? $q_top_totem->fetch_assoc() : ['servicio'=>'N/A', 'cant'=>0];

$q_top_prof = $conexion->query("SELECT profesional, COUNT(*) as cant FROM turnos WHERE fecha_turno = '$hoy' AND estado NOT IN ('Cancelado', 'Ausente') GROUP BY profesional ORDER BY cant DESC LIMIT 5");
$top_3_prof = [];
if ($q_top_prof) {
    while ($r = $q_top_prof->fetch_assoc()) {
        $top_3_prof[] = $r;
    }
}

$q_flujo = $conexion->query("SELECT SUM(CASE WHEN tipo_registro='INGRESO' THEN 1 ELSE 0 END) as ing, SUM(CASE WHEN tipo_registro='EGRESO' THEN 1 ELSE 0 END) as eg FROM registro_ingresos WHERE DATE(fecha_hora) = '$hoy'");
$flujo = $q_flujo ? $q_flujo->fetch_assoc() : ['ing'=>0, 'eg'=>0];

$q_reinicio = $conexion->query("SELECT fecha FROM totem_config WHERE tipo = 'ultimo_reinicio'");
$ultimo_reinicio = ($q_reinicio && $q_reinicio->num_rows > 0) ? date('d/m/Y', strtotime($q_reinicio->fetch_assoc()['fecha'])) : 'N/A';

$q_total_consultas_historicas = $conexion->query("SELECT SUM(consultas) as cant FROM servicios");
$consultas_hist = $q_total_consultas_historicas ? $q_total_consultas_historicas->fetch_assoc()['cant'] : 0;

// --- 2. LAS 10 NUEVAS MÉTRICAS ESTADÍSTICAS ---

// Métrica 1: Tasa de Ausentismo
$ausentes = $conexion->query("SELECT COUNT(*) as cant FROM turnos WHERE fecha_turno = '$hoy' AND estado = 'Ausente'")->fetch_assoc()['cant'] ?? 0;
$tasa_ausentismo = $t_totales > 0 ? round(($ausentes / $t_totales) * 100, 1) : 0;

// Métrica 2: Tasa de Adopción Digital (Tótem vs Ventanilla)
$adopcion_digital = ($total_totem + $total_ventanilla) > 0 ? round(($total_totem / ($total_totem + $total_ventanilla)) * 100, 1) : 0;

// Métrica 3: Horario Pico de Tránsito
$q_pico = $conexion->query("SELECT HOUR(fecha_hora) as hora, COUNT(*) as cant FROM estadisticas_totem WHERE DATE(fecha_hora) = '$hoy' GROUP BY hora ORDER BY cant DESC LIMIT 1");
$hora_pico = "N/A";
if ($q_pico && $q_pico->num_rows > 0) {
    $r = $q_pico->fetch_assoc();
    $h = str_pad($r['hora'], 2, '0', STR_PAD_LEFT);
    $h2 = str_pad($r['hora']+1, 2, '0', STR_PAD_LEFT);
    $hora_pico = "$h:00 - $h2:00";
}

// Métrica 4: Turnos Finalizados (Atendidos) -> $t_atendidos ya está. Porcentaje completado.
$porcentaje_completado = $t_totales > 0 ? round(($t_atendidos / $t_totales) * 100, 1) : 0;

// Métrica 5: Eficacia de Validación (Val vs Asis global)
$eficacia_val = $tkts_total > 0 ? round(($tkts_val / $tkts_total) * 100, 1) : 0;

// Métrica 6: Cuello de Botella (Tiempo Máximo) -> $tiempo_max ya lo saqué arriba

// Métrica 7: Autonomía Global de Papel y Estimación
$tkts_restantes_totem = max(0, $nivel_papel - $tickets_imp);
$tkts_restantes_vent = max(0, $nivel_papel_vent - $tickets_imp_vent);
$autonomia_global = $tkts_restantes_totem + $tkts_restantes_vent;

$horas_operativas_hoy = max(1, (int)date('H') - 7); // Asumiendo apertura a las 8 AM
$tasa_totem = $total_totem / $horas_operativas_hoy;
$tasa_vent = $total_ventanilla / $horas_operativas_hoy;

$horas_restantes_totem = $tasa_totem > 0 ? round($tkts_restantes_totem / $tasa_totem, 1) : 999;
$horas_restantes_vent = $tasa_vent > 0 ? round($tkts_restantes_vent / $tasa_vent, 1) : 999;

// Métrica 8: Carga de Especialidades (Top 3) -> $top_3_med ya sacado arriba

// Métrica 9: Monitor de Cancelaciones
$tasa_cancelaciones = $t_totales > 0 ? round(($t_cancelados / $t_totales) * 100, 1) : 0;

// Métrica 10: Proporción de Pacientes Nuevos
$proporcion_nuevos = $t_totales > 0 ? round(($pac_nuevos / $t_totales) * 100, 1) : 0;

$res_rollos_totem = $conexion->query("SELECT COUNT(*) as c FROM historial_rollos WHERE origen = 'TOTEM'")->fetch_assoc()['c'] ?? 0;
$res_rollos_ven = $conexion->query("SELECT COUNT(*) as c FROM historial_rollos WHERE origen = 'VENTANILLA'")->fetch_assoc()['c'] ?? 0;

$dias_es_cortos = ['Dom', 'Lun', 'Mar', 'Mie', 'Jue', 'Vie', 'Sab'];
$meses_es_cortos = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
$fecha_formateada = $dias_es_cortos[date('w')] . ", " . date('d') . " " . $meses_es_cortos[date('n') - 1] . " " . date('Y');

// Armando la respuesta final estructurada
echo json_encode([
    "status" => "success",
    "data" => [
        "fecha_actual" => $fecha_formateada,
        "turnos" => [
            "totales" => $t_totales,
            "pendientes" => $t_pendientes,
            "autorizados" => $t_autorizados,
            "presentes" => $t_presentes,
            "cancelados" => $t_cancelados,
            "ausentes" => $ausentes,
            "atendidos" => $t_atendidos,
            "tasa_ausentismo" => $tasa_ausentismo,
            "tasa_cancelaciones" => $tasa_cancelaciones,
            "porcentaje_completado" => $porcentaje_completado,
            "proporcion_nuevos" => $proporcion_nuevos,
            "top_3_especialidades" => $top_3_med,
            "top_3_profesionales" => $top_3_prof
        ],
        "operaciones" => [
            "totem_total" => $total_totem,
            "totem_val" => $totem_val,
            "totem_asis" => $totem_asis,
            "ventanilla_total" => $total_ventanilla,
            "ventanilla_val" => $vent_val,
            "ventanilla_asis" => $vent_asis,
            "total_combinado" => $tkts_total,
            "tiempo_promedio" => $tiempo_prom,
            "tiempo_maximo" => $tiempo_max,
            "adopcion_digital" => $adopcion_digital,
            "eficacia_validacion" => $eficacia_val,
            "hora_pico" => $hora_pico,
            "top_tramite_totem" => $top_totem['servicio'],
            "consultas_historicas" => $consultas_hist,
            "ultimo_reinicio" => $ultimo_reinicio
        ],
        "insumos" => [
            "nivel_papel_totem" => $nivel_papel,
            "tickets_imp_totem" => $tickets_imp,
            "porcentaje_totem" => $porcentaje_papel,
            "restantes_totem" => $tkts_restantes_totem,
            "horas_restantes_totem" => $horas_restantes_totem,
            
            "nivel_papel_vent" => $nivel_papel_vent,
            "tickets_imp_vent" => $tickets_imp_vent,
            "porcentaje_vent" => $porcentaje_papel_vent,
            "restantes_vent" => $tkts_restantes_vent,
            "horas_restantes_vent" => $horas_restantes_vent,
            
            "autonomia_global_tickets" => $autonomia_global,
            "rollos_historicos_totem" => $res_rollos_totem,
            "rollos_historicos_vent" => $res_rollos_ven
        ],
        "pacientes" => [
            "nuevos_hoy" => $pac_nuevos
        ],
        "seguridad" => [
            "flujo_ingresos" => $flujo['ing'] ?? 0,
            "flujo_egresos" => $flujo['eg'] ?? 0,
            "accesos_total" => $seg_accesos,
            "rondas_hoy" => $seg_rondas,
            "llaves_ocupadas" => $seg_llaves
        ]
    ]
]);
?>
