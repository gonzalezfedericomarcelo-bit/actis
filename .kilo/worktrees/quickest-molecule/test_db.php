<?php
require_once 'includes/conexion.php';
echo '<b>registro_rechazados:</b><br>';
$q = $conexion->query('SHOW COLUMNS FROM registro_rechazados');
while($r = $q->fetch_assoc()) echo $r['Field'] . '<br>';
echo '<hr><b>seguridad_rondas:</b><br>';
$q2 = $conexion->query('SHOW COLUMNS FROM seguridad_rondas');
while($r = $q2->fetch_assoc()) echo $r['Field'] . '<br>';
echo '<hr><b>seguridad_incidencias:</b><br>';
$q3 = $conexion->query('SHOW COLUMNS FROM seguridad_incidencias');
while($r = $q3->fetch_assoc()) echo $r['Field'] . '<br>';
?>
