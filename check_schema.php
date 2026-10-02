<?php
require_once 'includes/conexion.php';
$res = $conexion->query("DESCRIBE pacientes");
while($row = $res->fetch_assoc()){
    print_r($row);
}
?>
