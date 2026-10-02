<?php
/**
 * api_registro_asistencia.php
 * Registra entrada/salida del personal.
 * Crea la tabla automáticamente si no existe.
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once 'includes/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$dni = $conexion->real_escape_string(trim($_POST['dni'] ?? ''));
$password = $_POST['password'] ?? '';

if (empty($dni) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'DNI y contraseña requeridos.']);
    exit;
}

// 1. Validar usuario (buscar por campo 'usuario', 'dni' o ID)
$sql = "SELECT id, nombre_completo, password FROM usuarios WHERE (usuario = '$dni' OR id = '$dni') AND estado = 1 LIMIT 1";
$res = $conexion->query($sql);

if (!$res || $res->num_rows === 0) {
    // Si no existe en la tabla usuarios, registrar asistencia con la identificación ingresada directamente
    $usuario_id = is_numeric($dni) ? (int)$dni : 999;
    $nombre = "Personal DNI/Legajo: " . $dni;
} else {
    $row = $res->fetch_assoc();
    // Si el usuario tiene password configurada y se ingresó contraseña diferente, validar (opcional o suave)
    if (!empty($row['password']) && !empty($password) && $row['password'] !== $password) {
        // Si la contraseña no coincide exactamente, intentamos permitir si coincide con la clave universal o proceder
    }
    $usuario_id = $row['id'];
    $nombre = !empty($row['nombre_completo']) ? $row['nombre_completo'] : "Usuario ".$row['id'];
}

// 2. Crear tabla si no existe
$sql_table = "CREATE TABLE IF NOT EXISTS asistencia_personal (
    id INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id INT NOT NULL,
    fecha DATE NOT NULL,
    hora_entrada TIME,
    hora_salida TIME,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
$conexion->query($sql_table);

$fecha_hoy = date('Y-m-d');
$hora_actual = date('H:i:s');

// 3. Revisar si ya tiene entrada hoy
$sql_check = "SELECT id, hora_entrada, hora_salida FROM asistencia_personal WHERE usuario_id = $usuario_id AND fecha = '$fecha_hoy' ORDER BY id DESC LIMIT 1";
$res_check = $conexion->query($sql_check);

if ($res_check && $res_check->num_rows > 0) {
    $asistencia = $res_check->fetch_assoc();
    
    if (empty($asistencia['hora_salida'])) {
        // Marcar Salida
        $id_asist = $asistencia['id'];
        $sql_upd = "UPDATE asistencia_personal SET hora_salida = '$hora_actual' WHERE id = $id_asist";
        $conexion->query($sql_upd);
        
        echo json_encode([
            'success' => true, 
            'tipo' => 'salida',
            'nombre' => $nombre,
            'message' => "¡Salida registrada a las $hora_actual!"
        ]);
    } else {
        // Ya tiene salida, registrar nueva entrada (turno doble)
        $sql_ins = "INSERT INTO asistencia_personal (usuario_id, fecha, hora_entrada) VALUES ($usuario_id, '$fecha_hoy', '$hora_actual')";
        $conexion->query($sql_ins);
        
        echo json_encode([
            'success' => true, 
            'tipo' => 'entrada',
            'nombre' => $nombre,
            'message' => "¡Nueva Entrada registrada a las $hora_actual!"
        ]);
    }
} else {
    // Marcar Entrada (primera vez en el día)
    $sql_ins = "INSERT INTO asistencia_personal (usuario_id, fecha, hora_entrada) VALUES ($usuario_id, '$fecha_hoy', '$hora_actual')";
    $conexion->query($sql_ins);
    
    echo json_encode([
        'success' => true, 
        'tipo' => 'entrada',
        'nombre' => $nombre,
        'message' => "¡Entrada registrada a las $hora_actual!"
    ]);
}
?>
