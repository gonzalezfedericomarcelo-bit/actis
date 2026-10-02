<?php
session_start();
require_once 'includes/conexion.php';

header('Content-Type: application/json');

if(!isset($_SESSION['usuario_id']) || !isset($_POST['punto_qr'])) {
    echo json_encode(['status' => 'error', 'mensaje' => 'Acceso denegado o datos vacíos.']);
    exit;
}

$uid = (int)$_SESSION['usuario_id'];
$punto = $conexion->real_escape_string(trim($_POST['punto_qr']));

$query = "INSERT INTO seguridad_rondas (usuario_seguridad_id, punto_qr) VALUES ($uid, '$punto')";

if($conexion->query($query)) {
    echo json_encode(['status' => 'ok']);
} else {
    echo json_encode(['status' => 'error', 'mensaje' => 'Error BD: ' . $conexion->error]);
}
?>
