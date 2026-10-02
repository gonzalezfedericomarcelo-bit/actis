<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once 'includes/conexion.php';
header('Content-Type: application/json');

$response = [];
$res = $conexion->query("SELECT * FROM permisos");
$permisos = [];
if($res) { while($r = $res->fetch_assoc()) { $permisos[] = $r; } }
$response['permisos'] = $permisos;

$res3 = $conexion->query("SELECT * FROM configuracion_totem");
$conf = [];
if($res3) { while($r = $res3->fetch_assoc()) { $conf[] = $r; } }
$response['configuracion_totem'] = $conf;

echo json_encode($response, JSON_PRETTY_PRINT);
?>
