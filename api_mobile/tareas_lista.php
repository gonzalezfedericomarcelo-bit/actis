<?php
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
require_once '../includes/conexion.php';

try {
    $sql = "SELECT 
                t.id_tarea,
                t.titulo,
                t.descripcion,
                t.estado,
                t.prioridad,
                t.fecha_creacion,
                t.fecha_limite,
                t.fecha_cierre,
                u.nombre_completo AS nombre_asignado,
                c.nombre AS nombre_categoria,
                dest.nombre AS destino_nombre,
                ar.nombre AS area_nombre,
                pt.numero_orden
            FROM log_tareas t
            LEFT JOIN usuarios u ON t.id_asignado = u.id
            LEFT JOIN log_categorias c ON t.id_categoria = c.id_categoria
            LEFT JOIN log_pedidos_trabajo pt ON t.id_pedido_origen = pt.id_pedido
            LEFT JOIN log_destinos_internos dest ON pt.id_destino_interno = dest.id_destino
            LEFT JOIN log_areas ar ON pt.id_area = ar.id_area
            ORDER BY t.id_tarea DESC";

    $stmt = $pdo->query($sql);
    $tareas = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'tareas' => $tareas
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al cargar tareas: ' . $e->getMessage()
    ]);
}
