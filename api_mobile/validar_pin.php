<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once '../includes/conexion.php';

$data = json_decode(file_get_contents("php://input"), true);
$pin = $data['pin'] ?? '';

if (empty($pin)) {
    echo json_encode(["status" => "error", "message" => "PIN vacío."]);
    exit;
}

// Buscar el usuario que tenga este pin_totem
$pin_esc = $conexion->real_escape_string($pin);
$sql = "SELECT id, nombre_completo FROM usuarios WHERE pin_totem = '$pin_esc' AND estado = 1";
$res = $conexion->query($sql);

if ($res && $res->num_rows > 0) {
    $user = $res->fetch_assoc();
    echo json_encode([
        "status" => "success", 
        "usuario_id" => $user['id'],
        "nombre_completo" => $user['nombre_completo']
    ]);
} else {
    echo json_encode([
        "status" => "error", 
        "message" => "PIN incorrecto o usuario inactivo."
    ]);
}
?>
