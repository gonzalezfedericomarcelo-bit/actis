<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
require_once 'includes/conexion.php'; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dni = isset($_POST['dni']) ? $conexion->real_escape_string($_POST['dni']) : '';
    $nombre = isset($_POST['nombre']) ? $conexion->real_escape_string($_POST['nombre']) : 'DESCONOCIDO';
    $motivo = isset($_POST['motivo']) ? $conexion->real_escape_string($_POST['motivo']) : 'NO AFILIADO';
    $origen = isset($_POST['origen']) ? $conexion->real_escape_string($_POST['origen']) : 'TOTEM';

    if ($dni !== '') {
        $sql = "INSERT INTO registro_rechazados (dni, nombre, motivo, origen) VALUES ('$dni', '$nombre', '$motivo', '$origen')";
        $conexion->query($sql);
    }
}
?>
