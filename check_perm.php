<?php
require_once 'includes/conexion.php';
$res = $conexion->query("SELECT nombre_permiso FROM permisos");
while($row = $res->fetch_assoc()) {
    echo $row['nombre_permiso'] . "\n";
}
