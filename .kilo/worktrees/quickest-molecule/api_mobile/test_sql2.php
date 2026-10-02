<?php
require_once '../includes/conexion.php';
$res = $conexion->query("DESCRIBE usuarios");
echo "usuarios:\n";
while($row = $res->fetch_assoc()) echo $row['Field'] . "\n";
echo "\nascensores:\n";
$res = $conexion->query("DESCRIBE ascensores");
while($row = $res->fetch_assoc()) echo $row['Field'] . "\n";
?>
