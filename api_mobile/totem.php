<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
require_once '../includes/conexion.php';

 = ->query("SELECT tipo, estado FROM totem_config");
 = [];
if () {
    while ( = ->fetch_assoc()) {
        [['tipo']] = ['estado'];
    }
}

// Calcular estado del papel
 = (int)(['tickets_impresos'] ?? 0);
 = (int)(['capacidad_rollo'] ?? 120);
 = ( > 0) ? round((( - ) / ) * 100) : 0;

 = (int)(['tickets_impresos_ventanilla'] ?? 0);
 = (int)(['capacidad_rollo_ventanilla'] ?? 120);
 = ( > 0) ? round((( - ) / ) * 100) : 0;

// Leer feriados
 = ->query("SELECT id, fecha FROM totem_config WHERE tipo = 'feriado' ORDER BY fecha ASC");
 = [];
if () {
    while ( = ->fetch_assoc()) {
        [] = ;
    }
}

 = array_merge(, [
    "papel_totem_pct" => ,
    "papel_ven_pct" => ,
    "feriados" => 
]);

echo json_encode([
    "status" => "success",
    "data" => 
]);
?>
