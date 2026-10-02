<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
require_once '../includes/conexion.php';

$where = ["1=1"];

// Filtros
if (!empty($_GET['dni'])) {
    $dni = $conexion->real_escape_string($_GET['dni']);
    $where[] = "(dni LIKE '%$dni%' OR apellido LIKE '%$dni%' OR nombre LIKE '%$dni%')";
}
if (!empty($_GET['obra_social'])) {
    $os = $conexion->real_escape_string($_GET['obra_social']);
    $where[] = "obra_social LIKE '%$os%'";
}
if (!empty($_GET['fecha_desde']) && !empty($_GET['fecha_hasta'])) {
    $fd = $conexion->real_escape_string($_GET['fecha_desde']);
    $fh = $conexion->real_escape_string($_GET['fecha_hasta']);
    $where[] = "DATE(fecha_registro) BETWEEN '$fd' AND '$fh'";
}

$where_clause = implode(" AND ", $where);

// Base limit
$limit = empty($_GET['dni']) && empty($_GET['obra_social']) && empty($_GET['fecha_desde']) ? "LIMIT 100" : "LIMIT 500";

$sql = "
SELECT p.*, 
       (SELECT COUNT(id) FROM turnos WHERE paciente_id = p.id) as total_turnos,
       (SELECT MAX(fecha_turno) FROM turnos WHERE paciente_id = p.id) as ultimo_turno
FROM pacientes p
WHERE $where_clause
ORDER BY p.id DESC
$limit
";

$res = $conexion->query($sql);

$pacientes = [];
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $pacientes[] = $row;
    }
}

// Stats para los contadores rápidos en la app
$stats = [];
if (empty($_GET['dni'])) {
    $stats['total'] = $conexion->query("SELECT COUNT(*) as cant FROM pacientes")->fetch_assoc()['cant'] ?? 0;
    $stats['nuevos_mes'] = $conexion->query("SELECT COUNT(*) as cant FROM pacientes WHERE fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)")->fetch_assoc()['cant'] ?? 0;
}

echo json_encode([
    "status" => "success",
    "data" => $pacientes,
    "stats" => $stats
]);
?>
