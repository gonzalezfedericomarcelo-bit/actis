<?php
require_once 'includes/conexion.php';
$q = $conexion->query("SHOW TABLES LIKE '%ascensor%'");
echo "Ascensores:\n";
while ($r = $q->fetch_array()) { echo $r[0] . "\n"; }
$q = $conexion->query("SHOW TABLES LIKE '%bitacora%'");
echo "\nBitacora:\n";
while ($r = $q->fetch_array()) { echo $r[0] . "\n"; }
