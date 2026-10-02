<?php
session_start();
require_once 'includes/conexion.php';

header('Content-Type: application/json');

if(!isset($_SESSION['usuario_id']) || !isset($_POST['accion'])) {
    echo json_encode(['status' => 'error', 'mensaje' => 'Acceso denegado.']);
    exit;
}

$accion = $_POST['accion'];

if ($accion == 'activar_panico') {
    // Set totem to EVACUATION mode
    // First check if 'estado_manual' exists
    $check = $conexion->query("SELECT id FROM totem_config WHERE tipo = 'estado_manual'");
    if ($check && $check->num_rows > 0) {
        $conexion->query("UPDATE totem_config SET estado = 'EVACUACION' WHERE tipo = 'estado_manual'");
    } else {
        $conexion->query("INSERT INTO totem_config (tipo, estado) VALUES ('estado_manual', 'EVACUACION')");
    }
    echo json_encode(['status' => 'ok', 'mensaje' => 'Código Rojo Activado. Totem Bloqueado.']);
    exit;
}

if ($accion == 'desactivar_panico') {
    $conexion->query("UPDATE totem_config SET estado = 'ACTIVO' WHERE tipo = 'estado_manual'");
    echo json_encode(['status' => 'ok', 'mensaje' => 'Sistema normalizado.']);
    exit;
}
?>
