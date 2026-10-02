<?php
header('Content-Type: application/json');
header("Access-Control-Allow-Origin: *");
require_once '../includes/conexion.php';

try {
    $sql = "SELECT p.*,
                   u.nombre_completo AS nombre_auxiliar,
                   a.nombre AS nombre_area,
                   d.nombre AS nombre_destino
            FROM log_pedidos_trabajo p
            LEFT JOIN usuarios u ON p.id_auxiliar = u.id
            LEFT JOIN log_areas a ON p.id_area = a.id_area
            LEFT JOIN log_destinos_internos d ON p.id_destino_interno = d.id_destino
            WHERE p.estado_pedido IN ('pendiente_encargado', 'pendiente_firma_remota')
            ORDER BY p.id_pedido DESC";

    $stmt = $pdo->query($sql);
    $pedidos_pendientes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'pedidos' => $pedidos_pendientes
    ]);

} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error al cargar los pedidos: ' . $e->getMessage()
    ]);
}
