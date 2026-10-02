<?php
require_once '../includes/conexion.php';
$res = $conexion->query('DESCRIBE ascensor_incidencias');
while($row = $res->fetch_assoc()) echo $row['Field'] . "\n";
?>
