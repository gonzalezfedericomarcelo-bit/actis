<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
require_once 'includes/conexion.php';

$res_cap_ven = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'capacidad_rollo_ventanilla'");
$capacidad_ven = ($res_cap_ven && $res_cap_ven->num_rows > 0) ? (int)$res_cap_ven->fetch_assoc()['estado'] : 120;

$res_imp_ven = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'tickets_impresos_ventanilla'");
$impresos_ven = ($res_imp_ven && $res_imp_ven->num_rows > 0) ? (int)$res_imp_ven->fetch_assoc()['estado'] : 0;

$res_bloq_ven = $conexion->query("SELECT estado FROM totem_config WHERE tipo = 'bloqueo_min_papel_ventanilla'");
$bloqueo_ven = ($res_bloq_ven && $res_bloq_ven->num_rows > 0) ? (int)$res_bloq_ven->fetch_assoc()['estado'] : 5;

$sin_papel_ven = (($capacidad_ven - $impresos_ven) <= $bloqueo_ven) ? true : false;

echo json_encode(['sin_papel' => $sin_papel_ven]);
?>
