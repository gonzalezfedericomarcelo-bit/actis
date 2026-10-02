<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'includes/conexion.php';

// Endpoint para que el Tótem pueda resetear el papel mediante un PIN
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Obtenemos los datos enviados como JSON
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);
    $pin_recibido = isset($input['pin']) ? trim($input['pin']) : '';
    $res_pin = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'pin_rollo'");
    $db_pin = ($res_pin && $res_pin->num_rows > 0) ? $res_pin->fetch_assoc()['estado'] : '88888';
    
    if ($pin_recibido === $db_pin) {
        
        // 1. Resetear el contador de impresiones del Tótem
        $res_reset = $conexion->query("UPDATE totem_config SET estado = '0' WHERE tipo = 'tickets_impresos'");
        
        // 2. Registrar el cambio en el historial de rollos
        $res_historial = $conexion->query("INSERT INTO historial_rollos (origen) VALUES ('TOTEM')");
        
        if ($res_reset && $res_historial) {
            echo json_encode(['status' => 'success', 'message' => 'Rollo reseteado exitosamente']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error actualizando base de datos']);
        }
        
    } else {
        echo json_encode(['status' => 'error', 'message' => 'PIN incorrecto']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
}
?>
