<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'includes/conexion.php';

if ($conexion->connect_error) {
    echo json_encode(["status" => "error", "mensaje" => "Error de conexión a la base de datos."]);
    exit;
}

// Reset the tickets_impresos counter to 0 for the totem
$conexion->query("UPDATE totem_config SET estado = '0' WHERE tipo = 'tickets_impresos'");



echo json_encode(["status" => "ok", "mensaje" => "El rollo de papel del tótem se ha reseteado exitosamente."]);
$conexion->close();
?>
