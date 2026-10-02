<?php
require_once '../includes/conexion.php';
header('Content-Type: text/plain');

$tables = ['ascensores','ascensor_incidencias','empresas_mantenimiento','ascensor_visitas'];
foreach($tables as $t) {
    try {
        $cols = $pdo->query("SHOW COLUMNS FROM $t")->fetchAll();
        echo "$t:\n";
        foreach($cols as $c) echo "  {$c['Field']} ({$c['Type']})\n";
        $cnt = $pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn();
        echo "  FILAS: $cnt\n\n";
    } catch(Exception $e) { echo "$t: NO EXISTE\n\n"; }
}

// Muestra además una muestra de ascensor_incidencias
try {
    $sample = $pdo->query("SELECT * FROM ascensor_incidencias LIMIT 2")->fetchAll();
    echo "SAMPLE incidencias:\n";
    foreach($sample as $s) echo json_encode($s) . "\n";
} catch(Exception $e) { echo "No hay muestra\n"; }
