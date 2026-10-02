<?php
require_once '../includes/conexion.php';
$res = $conexion->query("SHOW TABLES;");
while ($r = $res->fetch_array()) { echo $r[0] . "\n"; }
