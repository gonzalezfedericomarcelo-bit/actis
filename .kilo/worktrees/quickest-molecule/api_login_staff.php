<?php
/**
 * api_login_staff.php
 * Valida credenciales ACTIS y crea la sesión.
 * POST: usuario, password
 * Responde: {"success": true/false, "message": "..."}
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store');
session_start();

require_once 'includes/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

$usuario = $conexion->real_escape_string(trim($_POST['usuario'] ?? ''));
$password = $_POST['password'] ?? '';

if (empty($usuario) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Complete usuario y contraseña.']);
    exit;
}

$sql = "SELECT id, nombre_completo, rol_id FROM usuarios WHERE usuario = '$usuario' AND password = '$password' AND estado = 1";
$res = $conexion->query($sql);

if ($res && $res->num_rows > 0) {
    $row = $res->fetch_assoc();
    $_SESSION['usuario_id'] = $row['id'];
    $_SESSION['nombre']     = $row['nombre_completo'];
    $_SESSION['rol_id']     = $row['rol_id'];
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'message' => 'Usuario o contraseña incorrectos.']);
}
