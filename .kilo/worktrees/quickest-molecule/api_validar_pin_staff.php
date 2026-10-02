<?php
/**
 * api_validar_pin_staff.php
 * Valida el PIN de Staff contra totem_config.
 * Si no hay sesión activa, responde con needs_login = true.
 * POST: pin
 * Responde: {"success": true, "redirect": "..."} | {"success": false, "message": "..."} | {"needs_login": true}
 */
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store');
session_start();

require_once 'includes/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido.']);
    exit;
}

// Si no hay sesión activa, indicar que se necesita login primero
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['needs_login' => true]);
    exit;
}

$pin_ingresado = trim($_POST['pin'] ?? '');

if (empty($pin_ingresado)) {
    echo json_encode(['success' => false, 'message' => 'PIN vacío.']);
    exit;
}

$res = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'pin_dashboard_staff'");
if (!$res || $res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'PIN no configurado.']);
    exit;
}

$pin_db = $res->fetch_assoc()['estado'];

if ($pin_ingresado === $pin_db) {
    echo json_encode(['success' => true, 'redirect' => 'admin_totem_staff.php']);
} else {
    echo json_encode(['success' => false, 'message' => 'PIN incorrecto.']);
}
