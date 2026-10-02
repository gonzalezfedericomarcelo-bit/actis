<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
require_once '../includes/conexion.php';

$origen = isset($_GET['origen']) ? $conexion->real_escape_string($_GET['origen']) : '';
$fecha_desde = isset($_GET['fecha_desde']) ? $conexion->real_escape_string($_GET['fecha_desde']) : date('Y-m-d');
$fecha_hasta = isset($_GET['fecha_hasta']) ? $conexion->real_escape_string($_GET['fecha_hasta']) : date('Y-m-d');

$where_origen = "";
if ($origen == 'TOTEM' || $origen == 'VENTANILLA') {
    $where_origen = "AND (origen = '$origen' OR (origen IS NULL AND '$origen' = 'TOTEM'))";
}

$sql = "SELECT * FROM estadisticas_totem WHERE DATE(fecha_hora) >= '$fecha_desde' AND DATE(fecha_hora) <= '$fecha_hasta' $where_origen ORDER BY id DESC LIMIT 3000";
$res = $conexion->query($sql);

$reportes = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $row['origen'] = empty($row['origen']) ? 'TOTEM' : $row['origen'];
        $reportes[] = $row;
    }
}

echo json_encode([
    "status" => "success",
    "data" => $reportes
]);
?>
