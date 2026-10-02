<?php
require_once '../includes/conexion.php';
$res = $conexion->query("SELECT COUNT(*) FROM ascensor_incidencias");
echo "Incidencias: " . $res->fetch_array()[0] . "\n";
$res = $conexion->query("SELECT COUNT(*) FROM ascensores");
echo "Ascensores: " . $res->fetch_array()[0] . "\n";
