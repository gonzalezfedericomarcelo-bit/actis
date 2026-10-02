<?php
require_once 'includes/conexion.php';
$q = $conexion->query("DESCRIBE asistencia_personal");
$data = [];
if ($q) {
    while($row = $q->fetch_assoc()){
        $data[] = $row;
    }
} else {
    $data = ["error" => $conexion->error];
}
echo json_encode($data);
