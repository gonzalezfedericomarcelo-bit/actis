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
$user = $conexion->real_escape_string($data['usuario'] ?? '');
$pass = $data['password'] ?? '';

if (empty($user) || empty($pass)) {
    echo json_encode(["status" => "error", "message" => "Faltan credenciales"]);
    exit;
}

$sql = "SELECT id, nombre_completo, rol_id FROM usuarios WHERE usuario = '$user' AND password = '$pass' AND estado = 1";
$res = $conexion->query($sql);

if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    // En producción se usaría un JWT, aquí simulamos un token basado en sha256
    $token = hash('sha256', $row['id'] . time() . 'ACTIS_MOBILE_SECURE');
    $uid = $row['id'];
    
    // Obtener permisos del usuario
    $permisos = [];
    $q_perm = $conexion->query("SELECT p.nombre_permiso FROM permisos p INNER JOIN rol_permiso rp ON p.id = rp.permiso_id INNER JOIN usuarios u ON u.rol_id = rp.rol_id WHERE u.id = $uid AND u.estado = 1");
    if($q_perm && $q_perm->num_rows > 0) {
        while($p = $q_perm->fetch_assoc()) {
            $permisos[] = $p['nombre_permiso'];
        }
    }

    // Guardar token en BD (idealmente) o simplemente devolver éxito (versión simplificada)
    echo json_encode([
        "status" => "success",
        "token" => $token,
        "user" => [
            "id" => $row['id'],
            "nombre" => $row['nombre_completo'],
            "rol_id" => $row['rol_id'],
            "permisos" => $permisos
        ]
    ]);
} else {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Credenciales incorrectas o usuario inactivo"]);
}
?>
