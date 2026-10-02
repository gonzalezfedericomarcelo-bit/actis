<?php
require_once 'includes/conexion.php';
$tables = ['ascensores','ascensor_incidencias','empresas_mantenimiento','ascensor_visitas','ascensor_bitacora'];
foreach($tables as $t) {
    $q = $conexion->query("SHOW TABLES LIKE '$t'");
    if (!$q || $q->num_rows == 0) { echo "$t: NO EXISTE\n"; continue; }
    echo "$t:\n";
    $c = $conexion->query("SHOW COLUMNS FROM $t");
    while($r = $c->fetch_assoc()) echo "  {$r['Field']} ({$r['Type']})\n";
    $cnt = $conexion->query("SELECT COUNT(*) as c FROM $t");
    if ($cnt && $r=$cnt->fetch_assoc()) echo "  FILAS: {$r['c']}\n";
    echo "\n";
}
