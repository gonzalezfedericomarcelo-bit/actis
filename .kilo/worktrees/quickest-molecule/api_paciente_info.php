<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *'); 
require_once 'includes/conexion.php';

$dni = isset($_GET['dni']) ? $conexion->real_escape_string($_GET['dni']) : '';

if (empty($dni)) {
    echo json_encode(['status' => 'error']);
    exit;
}

$q_paciente = $conexion->query("SELECT hc, observaciones FROM pacientes WHERE dni = '$dni' LIMIT 1");
if ($q_paciente && $q_paciente->num_rows > 0) {
    $row = $q_paciente->fetch_assoc();
    $afiliado = $row['hc'];
    $fuerza = '';
    
    if (strpos($row['observaciones'], 'Fuerza:') !== false) {
        preg_match('/Fuerza:\s*(.*?)(?=\s*\||$)/', $row['observaciones'], $matches);
        if (isset($matches[1])) {
            $fuerza = trim($matches[1]);
        }
    }
    
    echo json_encode(['status' => 'success', 'afiliado' => $afiliado, 'fuerza' => $fuerza]);
} else {
    echo json_encode(['status' => 'error']);
}
?>
