<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'includes/conexion.php';

$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['dni']) || empty($data['dni'])) {
    echo json_encode(['status' => 'error', 'message' => 'No se recibieron datos válidos']);
    exit;
}

$dni = $conexion->real_escape_string($data['dni']);
$motivo = isset($data['motivo']) ? $conexion->real_escape_string($data['motivo']) : 'Cancelación Manual';

// Los datos vienen dentro de datos_fila si fue por el interceptor genérico, o sueltos
$fecha = isset($data['fecha']) ? $data['fecha'] : (isset($data['datos_fila']) ? array_values(preg_grep('/THTUFECHA/', array_keys($data['datos_fila']))) : '');
if (is_array($fecha)) $fecha = isset($data['datos_fila'][$fecha[0]]) ? $data['datos_fila'][$fecha[0]] : '';

$hora = isset($data['hora']) ? $data['hora'] : (isset($data['datos_fila']) ? array_values(preg_grep('/THTUHORA/', array_keys($data['datos_fila']))) : '');
if (is_array($hora)) $hora = isset($data['datos_fila'][$hora[0]]) ? $data['datos_fila'][$hora[0]] : '';

$fecha = $conexion->real_escape_string($fecha);
$hora = $conexion->real_escape_string($hora);

// 1. Buscar al paciente por DNI
$q_paciente = $conexion->query("SELECT id FROM pacientes WHERE dni = '$dni' LIMIT 1");

if ($q_paciente && $q_paciente->num_rows > 0) {
    $row = $q_paciente->fetch_assoc();
    $paciente_id = $row['id'];
    
    // 2. Buscar el turno y cancelarlo
    $fecha_limpia = explode(' ', trim($fecha))[0]; // "28/07/2026 18:50:00" -> "28/07/2026"
    $fecha_sql = $fecha_limpia;
    if (strpos($fecha_limpia, '/') !== false) {
        $partes = explode('/', $fecha_limpia);
        if (count($partes) == 3) {
            $fecha_sql = "{$partes[2]}-{$partes[1]}-{$partes[0]}";
        }
    }
    
    // Si la hora vino dentro de la fecha pero vacía en el campo de hora
    if (empty($hora) && strpos($fecha, ':') !== false) {
        $partes_fecha = explode(' ', trim($fecha));
        if (count($partes_fecha) >= 2) {
            $hora = $partes_fecha[1];
        }
    }
    
    $update_sql = "UPDATE turnos 
                   SET estado = 'Cancelado', 
                       comentario_paciente = CONCAT(IFNULL(comentario_paciente, ''), ' | Motivo Baja: $motivo') 
                   WHERE paciente_id = $paciente_id 
                   AND fecha_turno = '$fecha_sql' 
                   AND estado IN ('Autorizado', 'Reprogramado')";
                   
    if (!empty($hora)) {
        $hora_limpia = str_replace(' hs', '', $hora);
        $update_sql .= " AND hora_turno LIKE '$hora_limpia%'";
    }
                   
    if ($conexion->query($update_sql)) {
        if ($conexion->affected_rows > 0) {
            echo json_encode(['status' => 'success', 'message' => 'Turno cancelado correctamente en ACTIS']);
        } else {
            echo json_encode(['status' => 'warning', 'message' => 'No se encontró el turno activo para cancelar']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Error al cancelar el turno: ' . $conexion->error]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Paciente no encontrado en la base de datos de ACTIS']);
}
?>
