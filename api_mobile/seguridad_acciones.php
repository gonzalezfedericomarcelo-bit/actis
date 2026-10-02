<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
require_once '../includes/conexion.php';

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!isset($data['accion'])) {
    echo json_encode(["status" => "error", "message" => "Acción no especificada"]);
    exit;
}

$accion = $data['accion'];

if ($accion == 'toggle_panico') {
    // Leer estado actual
    $q = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'estado_manual'");
    if ($q && $q->num_rows > 0) {
        $estado_actual = $q->fetch_assoc()['estado'];
        $nuevo_estado = ($estado_actual == 'EVACUACION') ? 'ACTIVO' : 'EVACUACION';
        
        $conexion->query("UPDATE totem_config SET estado = '$nuevo_estado' WHERE tipo = 'estado_manual'");
        
        echo json_encode(["status" => "success", "nuevo_estado" => $nuevo_estado]);
        exit;
    }
}

echo json_encode(["status" => "error", "message" => "Acción inválida"]);
?>
