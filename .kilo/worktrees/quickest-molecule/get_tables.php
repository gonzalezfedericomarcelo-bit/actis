<?php
require_once 'conexion.php';
$res = [];
$q = $conexion->query("SHOW TABLES");
if ($q) {
    while($r = $q->fetch_array()) { $res[] = $r[0]; }
    echo json_encode($res);
} else {
    echo "Error: " . $conexion->error;
}
