<?php
header('Content-Type: application/json');
require_once 'includes/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['dni'])) {
    echo json_encode(['success' => false, 'message' => 'DNI no proporcionado.']);
    exit;
}

$dni = $conexion->real_escape_string(trim($_POST['dni']));

// Buscar paciente
$sql_pac = "SELECT id, email FROM pacientes WHERE dni = '$dni'";
$res_pac = $conexion->query($sql_pac);

if ($res_pac->num_rows === 0) {
    echo json_encode(['success' => true, 'turnos' => [], 'email' => '']);
    exit;
}

$paciente = $res_pac->fetch_assoc();
$paciente_id = $paciente['id'];
$email = $paciente['email'];

// Buscar turnos futuros del paciente
// Consideramos "futuros" a turnos con fecha >= HOY
$sql_turnos = "SELECT id, fecha_turno, hora_turno, especialidad, profesional, estado 
               FROM turnos 
               WHERE paciente_id = $paciente_id 
               AND fecha_turno >= CURDATE() 
               AND estado != 'Cancelado' 
               ORDER BY fecha_turno ASC, hora_turno ASC";
               
$res_turnos = $conexion->query($sql_turnos);

$turnos = [];
while ($row = $res_turnos->fetch_assoc()) {
    // Formatear fecha y hora
    $fecha_obj = new DateTime($row['fecha_turno']);
    $row['fecha_formateada'] = $fecha_obj->format('d/m/Y');
    
    $hora_obj = new DateTime($row['hora_turno']);
    $row['hora_formateada'] = $hora_obj->format('H:i');
    
    $turnos[] = $row;
}

echo json_encode([
    'success' => true,
    'turnos' => $turnos,
    'email' => $email
]);
exit;
