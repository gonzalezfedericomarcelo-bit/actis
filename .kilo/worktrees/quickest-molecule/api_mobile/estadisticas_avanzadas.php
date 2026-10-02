<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
require_once '../includes/conexion.php';

date_default_timezone_set('America/Argentina/Buenos_Aires');
$hoy = date('Y-m-d');
$hace30dias = date('Y-m-d', strtotime('-30 days'));

$modulo = isset($_GET['modulo']) ? $_GET['modulo'] : 'turnos';

$response = [
    "status" => "success",
    "modulo" => $modulo,
    "charts" => []
];

function executeQuery($conexion, $sql) {
    $res = $conexion->query($sql);
    $data = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $data[] = $row;
        }
    }
    return $data;
}

if ($modulo == 'turnos') {
    // 1. Evolución 30 días
    $response['charts']['evolucion'] = executeQuery($conexion, "
        SELECT fecha_turno as label, COUNT(*) as value 
        FROM turnos 
        WHERE fecha_turno >= '$hace30dias' 
        GROUP BY fecha_turno ORDER BY fecha_turno ASC
    ");
    // 2. Distribución Estados
    $response['charts']['estados'] = executeQuery($conexion, "
        SELECT estado as label, COUNT(*) as value 
        FROM turnos 
        GROUP BY estado
    ");
    // 3. Top 10 Servicios
    $response['charts']['servicios'] = executeQuery($conexion, "
        SELECT servicio as label, COUNT(*) as value 
        FROM turnos 
        GROUP BY servicio ORDER BY value DESC LIMIT 10
    ");
    // 4. Top 10 Profesionales
    $response['charts']['profesionales'] = executeQuery($conexion, "
        SELECT profesional as label, COUNT(*) as value 
        FROM turnos 
        WHERE profesional IS NOT NULL AND profesional != '' 
        GROUP BY profesional ORDER BY value DESC LIMIT 10
    ");
    // 5. Turnos por hora (basado en la hora del turno)
    $response['charts']['horas'] = executeQuery($conexion, "
        SELECT LEFT(hora_turno, 2) as label, COUNT(*) as value 
        FROM turnos 
        WHERE hora_turno IS NOT NULL AND hora_turno != ''
        GROUP BY LEFT(hora_turno, 2) ORDER BY label ASC
    ");
    // 6. Tendencia Mensual Histórica
    $response['charts']['tendencia_mensual'] = executeQuery($conexion, "
        SELECT DATE_FORMAT(fecha_turno, '%Y-%m') as label, COUNT(*) as value 
        FROM turnos 
        GROUP BY DATE_FORMAT(fecha_turno, '%Y-%m') ORDER BY label ASC LIMIT 12
    ");
    // 7. Distribución por Día de la Semana
    $response['charts']['dia_semana'] = executeQuery($conexion, "
        SELECT DAYNAME(fecha_turno) as label, COUNT(*) as value 
        FROM turnos 
        WHERE fecha_turno >= '$hace30dias'
        GROUP BY DAYNAME(fecha_turno)
    ");
    // 8. Tasa de No-Show (Ausentismo) histórico
    $response['charts']['ausentismo'] = executeQuery($conexion, "
        SELECT 
            CASE WHEN estado IN ('Atendido', 'Completado') THEN 'Asistió' ELSE 'Ausente/Cancelado' END as label, 
            COUNT(*) as value 
        FROM turnos 
        GROUP BY label
    ");
} 
else if ($modulo == 'totem' || $modulo == 'ventanilla') {
    $filtroOrigen = ($modulo == 'ventanilla') ? "origen = 'VENTANILLA'" : "(origen IS NULL OR origen != 'VENTANILLA')";
    
    // 1. Evolución 30 días
    $response['charts']['evolucion'] = executeQuery($conexion, "
        SELECT DATE(fecha_hora) as label, COUNT(*) as value 
        FROM estadisticas_totem 
        WHERE DATE(fecha_hora) >= '$hace30dias' AND $filtroOrigen
        GROUP BY DATE(fecha_hora) ORDER BY DATE(fecha_hora) ASC
    ");
    // 2. Distribución Modos
    $response['charts']['modos'] = executeQuery($conexion, "
        SELECT modo as label, COUNT(*) as value 
        FROM estadisticas_totem 
        WHERE $filtroOrigen
        GROUP BY modo
    ");
    // 3. Totem/Ventanilla por hora
    $response['charts']['horas'] = executeQuery($conexion, "
        SELECT HOUR(fecha_hora) as label, COUNT(*) as value 
        FROM estadisticas_totem 
        WHERE $filtroOrigen
        GROUP BY HOUR(fecha_hora) ORDER BY label ASC
    ");
    // 4. Top 10 Servicios
    $response['charts']['servicios'] = executeQuery($conexion, "
        SELECT servicio as label, COUNT(*) as value 
        FROM estadisticas_totem 
        WHERE $filtroOrigen AND servicio IS NOT NULL AND servicio != ''
        GROUP BY servicio ORDER BY value DESC LIMIT 10
    ");
    
    if ($modulo == 'totem') {
        // Eficacia QR vs Manual
        $response['charts']['qr'] = executeQuery($conexion, "
            SELECT 
                CASE WHEN token_iofa IS NOT NULL AND token_iofa != '' THEN 'QR/Token' ELSE 'DNI Manual' END as label, 
                COUNT(*) as value 
            FROM estadisticas_totem 
            WHERE $filtroOrigen
            GROUP BY label
        ");
    }
    
    // 6. Flujo Semanal
    $response['charts']['dia_semana_totem'] = executeQuery($conexion, "
        SELECT DAYNAME(fecha_hora) as label, COUNT(*) as value 
        FROM estadisticas_totem 
        WHERE fecha_hora >= '$hace30dias' AND $filtroOrigen
        GROUP BY DAYNAME(fecha_hora)
    ");
    // 7. Tiempo Promedio de Operación
    $response['charts']['tiempo_operacion'] = executeQuery($conexion, "
        SELECT DATE(fecha_hora) as label, ROUND(AVG(tiempo_operacion), 2) as value 
        FROM estadisticas_totem 
        WHERE fecha_hora >= '$hace30dias' AND $filtroOrigen AND tiempo_operacion > 0
        GROUP BY DATE(fecha_hora) ORDER BY DATE(fecha_hora) ASC
    ");
}
else if ($modulo == 'pacientes') {
    // 1. Crecimiento últimos 30 días (Basado en los turnos, cuántos pacientes únicos se atendieron por día)
    $response['charts']['evolucion'] = executeQuery($conexion, "
        SELECT fecha_turno as label, COUNT(DISTINCT paciente_id) as value 
        FROM turnos 
        WHERE fecha_turno >= '$hace30dias'
        GROUP BY fecha_turno ORDER BY fecha_turno ASC
    ");
    // 2. Fuerzas (Obras Sociales) nativo de la tabla pacientes
    $response['charts']['obrasocial'] = executeQuery($conexion, "
        SELECT p.obra_social as label, COUNT(DISTINCT t.paciente_id) as value 
        FROM turnos t
        INNER JOIN pacientes p ON t.paciente_id = p.id
        WHERE p.obra_social IS NOT NULL AND p.obra_social != '' AND t.fecha_turno >= '$hace30dias'
        GROUP BY p.obra_social ORDER BY value DESC LIMIT 10
    ");
    // 3. Estado de validación
    $response['charts']['validacion'] = executeQuery($conexion, "
        SELECT CASE WHEN t.token_iofa IS NOT NULL AND t.token_iofa != '' THEN 'Validado IOFA' ELSE 'No Validado' END as label, COUNT(DISTINCT t.paciente_id) as value 
        FROM turnos t
        WHERE t.fecha_turno >= '$hace30dias'
        GROUP BY label
    ");
    // 4. Género
    $response['charts']['genero'] = executeQuery($conexion, "
        SELECT p.sexo as label, COUNT(DISTINCT t.paciente_id) as value 
        FROM turnos t
        INNER JOIN pacientes p ON t.paciente_id = p.id
        WHERE p.sexo IS NOT NULL AND p.sexo != '' AND t.fecha_turno >= '$hace30dias'
        GROUP BY p.sexo
    ");
    // 5. Estado Civil
    $response['charts']['estado_civil'] = executeQuery($conexion, "
        SELECT p.estado_civil as label, COUNT(DISTINCT t.paciente_id) as value 
        FROM turnos t
        INNER JOIN pacientes p ON t.paciente_id = p.id
        WHERE p.estado_civil IS NOT NULL AND p.estado_civil != '' AND t.fecha_turno >= '$hace30dias'
        GROUP BY p.estado_civil
    ");
    // 6. Top 10 Pacientes Recurrentes (que más turnos sacaron)
    $response['charts']['top_recurrentes'] = executeQuery($conexion, "
        SELECT CONCAT(p.nombre, ' ', p.apellido) as label, COUNT(t.id) as value 
        FROM turnos t
        INNER JOIN pacientes p ON t.paciente_id = p.id
        WHERE t.fecha_turno >= '$hace30dias'
        GROUP BY t.paciente_id ORDER BY value DESC LIMIT 10
    ");
    // 7. Distribución de pacientes únicos por servicio
    $response['charts']['pacientes_servicio'] = executeQuery($conexion, "
        SELECT t.servicio as label, COUNT(DISTINCT t.paciente_id) as value 
        FROM turnos t
        WHERE t.fecha_turno >= '$hace30dias' AND t.servicio IS NOT NULL AND t.servicio != ''
        GROUP BY t.servicio ORDER BY value DESC LIMIT 10
    ");
}
else if ($modulo == 'seguridad') {
    // 1. Ingresos últimos 30 días
    $response['charts']['ingresos'] = executeQuery($conexion, "
        SELECT DATE(fecha_hora) as label, COUNT(*) as value 
        FROM registro_ingresos 
        WHERE DATE(fecha_hora) >= '$hace30dias' AND tipo_registro = 'INGRESO'
        GROUP BY DATE(fecha_hora) ORDER BY DATE(fecha_hora) ASC
    ");
    // 2. Egresos últimos 30 días
    $response['charts']['egresos'] = executeQuery($conexion, "
        SELECT DATE(fecha_hora) as label, COUNT(*) as value 
        FROM registro_ingresos 
        WHERE DATE(fecha_hora) >= '$hace30dias' AND tipo_registro = 'EGRESO'
        GROUP BY DATE(fecha_hora) ORDER BY DATE(fecha_hora) ASC
    ");
    // 3. Rondas últimos 30 días
    $response['charts']['rondas'] = executeQuery($conexion, "
        SELECT DATE(fecha_hora) as label, COUNT(*) as value 
        FROM seguridad_rondas 
        WHERE DATE(fecha_hora) >= '$hace30dias'
        GROUP BY DATE(fecha_hora) ORDER BY DATE(fecha_hora) ASC
    ");
    // 4. Estado de llaves
    $response['charts']['llaves'] = executeQuery($conexion, "
        SELECT estado as label, COUNT(*) as value 
        FROM seguridad_llaves 
        GROUP BY estado
    ");
    // 5. Top Llaves Utilizadas
    $response['charts']['top_llaves'] = executeQuery($conexion, "
        SELECT habitacion_servicio as label, COUNT(*) as value 
        FROM seguridad_llaves_historial
        GROUP BY habitacion_servicio ORDER BY value DESC LIMIT 10
    ");
    // 6. Rondas por Hora
    $response['charts']['rondas_hora'] = executeQuery($conexion, "
        SELECT HOUR(fecha_hora) as label, COUNT(*) as value 
        FROM seguridad_rondas 
        GROUP BY HOUR(fecha_hora) ORDER BY label ASC
    ");
}

echo json_encode($response);
?>
