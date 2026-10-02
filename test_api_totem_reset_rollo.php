<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'includes/conexion.php';

if ($conexion->connect_error) {
    echo json_encode(["status" => "error", "mensaje" => "Error de conexión a la base de datos."]);
    exit;
}

// SOLO EN TEST: Desactivar el modo de simulación de pruebas si estuviera activo
// NO RESETEAMOS tickets_impresos NI alertas_silenciadas EN PRODUCCIÓN
$conexion->query("UPDATE totem_config SET estado = '0' WHERE tipo = 'simular_sin_papel_test'");

echo json_encode(["status" => "ok", "mensaje" => "[TEST] El rollo de papel del tótem se ha reseteado exitosamente (Simulación apagada, contador de producción intacto)."]);
$conexion->close();
?>
