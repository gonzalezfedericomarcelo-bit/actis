<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
require_once '../includes/conexion.php';

$fecha_desde = isset($_GET['fecha_desde']) ? $conexion->real_escape_string($_GET['fecha_desde']) : null;
$fecha_hasta = isset($_GET['fecha_hasta']) ? $conexion->real_escape_string($_GET['fecha_hasta']) : null;
$paciente_id = isset($_GET['paciente_id']) ? (int)$_GET['paciente_id'] : null;
$profesional = isset($_GET['profesional']) ? $conexion->real_escape_string($_GET['profesional']) : null;
$especialidad = isset($_GET['especialidad']) ? $conexion->real_escape_string($_GET['especialidad']) : null;

if ($paciente_id) {
    // Si envían paciente_id, mostrar su historial limitando a los últimos 150 para no colapsar la app
    $sql = "SELECT t.id, t.fecha_turno, t.hora_turno, t.especialidad, t.profesional, t.numero_turno, t.estado, t.creado_el, t.motivo_visita, t.operador_externo, p.nombre, p.apellido, p.dni, p.hc as afiliado, p.telefono, u.nombre_completo as creador_nombre 
            FROM turnos t 
            LEFT JOIN pacientes p ON t.paciente_id = p.id 
            LEFT JOIN usuarios u ON t.usuario_creador_id = u.id 
            WHERE t.paciente_id = $paciente_id
            ORDER BY t.fecha_turno DESC, t.hora_turno DESC 
            LIMIT 150";
} elseif ($profesional) {
    // Mostrar historial de turnos de hoy de un profesional
    $sql = "SELECT t.id, t.fecha_turno, t.hora_turno, t.especialidad, t.profesional, t.numero_turno, t.estado, t.creado_el, t.motivo_visita, t.operador_externo, p.nombre, p.apellido, p.dni, p.hc as afiliado, p.telefono, u.nombre_completo as creador_nombre 
            FROM turnos t 
            LEFT JOIN pacientes p ON t.paciente_id = p.id 
            LEFT JOIN usuarios u ON t.usuario_creador_id = u.id 
            WHERE t.profesional = '$profesional' AND t.fecha_turno = CURDATE()
            ORDER BY t.hora_turno ASC 
            LIMIT 150";
} elseif ($especialidad) {
    // Mostrar historial de turnos de hoy de una especialidad
    $sql = "SELECT t.id, t.fecha_turno, t.hora_turno, t.especialidad, t.profesional, t.numero_turno, t.estado, t.creado_el, t.motivo_visita, t.operador_externo, p.nombre, p.apellido, p.dni, p.hc as afiliado, p.telefono, u.nombre_completo as creador_nombre 
            FROM turnos t 
            LEFT JOIN pacientes p ON t.paciente_id = p.id 
            LEFT JOIN usuarios u ON t.usuario_creador_id = u.id 
            WHERE t.especialidad = '$especialidad' AND t.fecha_turno = CURDATE()
            ORDER BY t.hora_turno ASC 
            LIMIT 150";
} elseif ($fecha_desde && $fecha_hasta) {
    // Si envían filtro de fechas, buscar exclusivamente en ese rango con un límite mayor
    $sql = "SELECT t.id, t.fecha_turno, t.hora_turno, t.especialidad, t.profesional, t.numero_turno, t.estado, t.creado_el, t.motivo_visita, t.operador_externo, p.nombre, p.apellido, p.dni, p.hc as afiliado, p.telefono, u.nombre_completo as creador_nombre 
            FROM turnos t 
            LEFT JOIN pacientes p ON t.paciente_id = p.id 
            LEFT JOIN usuarios u ON t.usuario_creador_id = u.id 
            WHERE t.fecha_turno >= '$fecha_desde' AND t.fecha_turno <= '$fecha_hasta'
            ORDER BY t.fecha_turno ASC, t.hora_turno ASC 
            LIMIT 3000";
} else {
    // Listar los turnos del día, los sacados hoy, y los últimos 300 (por defecto)
    $sql = "(SELECT t.id, t.fecha_turno, t.hora_turno, t.especialidad, t.profesional, t.numero_turno, t.estado, t.creado_el, t.motivo_visita, t.operador_externo, p.nombre, p.apellido, p.dni, p.hc as afiliado, p.telefono, u.nombre_completo as creador_nombre FROM turnos t LEFT JOIN pacientes p ON t.paciente_id = p.id LEFT JOIN usuarios u ON t.usuario_creador_id = u.id WHERE t.fecha_turno = CURDATE())
    UNION
    (SELECT t.id, t.fecha_turno, t.hora_turno, t.especialidad, t.profesional, t.numero_turno, t.estado, t.creado_el, t.motivo_visita, t.operador_externo, p.nombre, p.apellido, p.dni, p.hc as afiliado, p.telefono, u.nombre_completo as creador_nombre FROM turnos t LEFT JOIN pacientes p ON t.paciente_id = p.id LEFT JOIN usuarios u ON t.usuario_creador_id = u.id WHERE DATE(t.creado_el) = CURDATE())
    UNION
    (SELECT t.id, t.fecha_turno, t.hora_turno, t.especialidad, t.profesional, t.numero_turno, t.estado, t.creado_el, t.motivo_visita, t.operador_externo, p.nombre, p.apellido, p.dni, p.hc as afiliado, p.telefono, u.nombre_completo as creador_nombre FROM turnos t LEFT JOIN pacientes p ON t.paciente_id = p.id LEFT JOIN usuarios u ON t.usuario_creador_id = u.id ORDER BY t.id DESC LIMIT 400)
    ORDER BY fecha_turno DESC, hora_turno DESC";
}

$res = $conexion->query($sql);

$turnos = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $turnos[] = $row;
    }
}

echo json_encode([
    "status" => "success",
    "data" => $turnos
]);
?>
