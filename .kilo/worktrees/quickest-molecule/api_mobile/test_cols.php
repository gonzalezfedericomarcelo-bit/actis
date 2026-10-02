<?php
require_once '../includes/conexion.php';
$q = $conexion->query("SHOW COLUMNS FROM ascensor_historial");
$cols = [];
while($r = $q->fetch_array()) { $cols[] = $r[0]; }
echo "ascensor_historial: " . implode(", ", $cols) . "\n";
$q = $conexion->query("SHOW COLUMNS FROM ascensor_visitas_tecnicas");
$cols = [];
while($r = $q->fetch_array()) { $cols[] = $r[0]; }
echo "ascensor_visitas_tecnicas: " . implode(", ", $cols) . "\n";
