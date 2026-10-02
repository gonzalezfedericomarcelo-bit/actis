<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
require_once '../includes/conexion.php';

try {
    // 1. Obtener Destinos Internos
    $sql_destinos = "SELECT id_destino, nombre, firma_remota FROM log_destinos_internos ORDER BY CASE WHEN nombre LIKE '%Actis%' THEN 0 ELSE 1 END, nombre ASC";
    $destinos = $pdo->query($sql_destinos)->fetchAll(PDO::FETCH_ASSOC);

    // 2. Obtener Áreas
    $sql_areas = "SELECT id_area, id_destino, nombre FROM log_areas ORDER BY nombre ASC";
    $areas = $pdo->query($sql_areas)->fetchAll(PDO::FETCH_ASSOC);

    // 3. Obtener Encargados para CC
    $sql_encargados = "SELECT id, nombre_completo, email FROM usuarios WHERE rol_id IN (4, 5) AND estado = 1 ORDER BY nombre_completo ASC"; // Asumiendo rol_id 4 y 5 son admin/encargado, ajustar si es necesario.
    $encargados = $pdo->query($sql_encargados)->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'destinos' => $destinos,
        'areas' => $areas,
        'encargados' => $encargados
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al obtener datos: ' . $e->getMessage()
    ]);
}
