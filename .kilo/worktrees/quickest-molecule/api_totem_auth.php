<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once 'includes/conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputJSON = file_get_contents('php://input');
    $input = json_decode($inputJSON, TRUE);
    
    $tipo = isset($input['tipo']) ? $input['tipo'] : '';
    $pin = isset($input['pin']) ? trim($input['pin']) : '';

    if ($tipo === 'soporte') {
        $res = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'pin_soporte'");
        $db_pin = ($res && $res->num_rows > 0) ? $res->fetch_assoc()['estado'] : '35911'; // Fallback a 35911
        
        if ($pin === $db_pin) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'PIN incorrecto']);
        }
    } else if ($tipo === 'rollo') {
        $res = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'pin_rollo'");
        $db_pin = ($res && $res->num_rows > 0) ? $res->fetch_assoc()['estado'] : '88888'; // Fallback a 88888
        
        if ($pin === $db_pin) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'PIN incorrecto']);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Tipo inválido']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Método no permitido']);
}
?>
