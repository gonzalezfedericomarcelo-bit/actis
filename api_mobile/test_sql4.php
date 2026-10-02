<?php
require_once '../includes/conexion.php';
$res = $conexion->query("SELECT * FROM permisos");
while($row = $res->fetch_assoc()) echo $row['nombre_permiso'] . "\n";
?>
