<?php
require_once 'includes/conexion.php';

$sql = "ALTER TABLE turnos ADD COLUMN numero_turno VARCHAR(50) NULL DEFAULT NULL AFTER id";

if ($conexion->query($sql) === TRUE) {
    echo "Columna numero_turno agregada correctamente.";
} else {
    echo "Error agregando columna: " . $conexion->error;
}
?>
