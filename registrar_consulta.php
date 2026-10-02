<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'includes/conexion.php';

if (isset($_GET['servicio'])) {
    $servicio = $conexion->real_escape_string(trim($_GET['servicio']));
    $conexion->query("UPDATE servicios SET consultas = consultas + 1 WHERE nombre = '$servicio'");
}

echo json_encode(["status" => "ok"]);
$conexion->close();
?>