<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
require_once 'includes/conexion.php';

$hora_reinicio = '';
$reinicio_forzado = false;

$query = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'hora_reinicio_totem'");
if ($query && $query->num_rows > 0) {
    $hora_reinicio = $query->fetch_assoc()['estado'];
}

$queryForzado = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'forzar_reinicio_totem'");
if ($queryForzado && $queryForzado->num_rows > 0) {
    if ($queryForzado->fetch_assoc()['estado'] == '1') {
        $reinicio_forzado = true;
        // Inmediatamente después de leerlo, lo reseteamos a 0 para no entrar en un bucle de reinicios
        $conexion->query("UPDATE totem_config SET estado = '0' WHERE tipo = 'forzar_reinicio_totem'");
    }
}

echo json_encode([
    'status' => 'ok', 
    'hora_reinicio' => $hora_reinicio,
    'reinicio_forzado' => $reinicio_forzado
]);
?>
