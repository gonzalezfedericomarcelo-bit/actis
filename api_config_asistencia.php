<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
require_once 'includes/conexion.php';

$q = $conexion->query("SELECT tipo, estado FROM totem_config WHERE tipo IN ('totem_demanda_espontanea_habilitado', 'pin_demanda_espontanea_totem')");
$hab = 1;
$pin = '12345';
while ($r = $q->fetch_assoc()) {
    if ($r['tipo'] == 'totem_demanda_espontanea_habilitado') $hab = (int)$r['estado'];
    if ($r['tipo'] == 'pin_demanda_espontanea_totem') $pin = $r['estado'];
}

echo json_encode([
    'habilitado' => ($hab >= 1),
    'libre' => ($hab === 2),
    'pin' => $pin
]);
?>
