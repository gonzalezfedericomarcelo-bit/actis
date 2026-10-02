<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../includes/conexion.php';
$pin = "1234";
$pin_esc = $conexion->real_escape_string($pin);
$sql = "SELECT id, nombre_completo FROM usuarios WHERE pin_totem = '$pin_esc' AND estado = 1";
echo "Executing: $sql\n";
$res = $conexion->query($sql);
if ($res) {
    echo "Rows: " . $res->num_rows . "\n";
} else {
    echo "Error: " . $conexion->error . "\n";
}
?>
