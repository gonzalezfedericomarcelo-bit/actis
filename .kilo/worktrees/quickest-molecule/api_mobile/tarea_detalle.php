<?php
header('Content-Type: application/json; charset=utf-8');
header("Access-Control-Allow-Origin: *");
require_once '../includes/conexion.php';

$id_tarea = (int)($_GET['id'] ?? 0);
if ($id_tarea <= 0) {
    echo json_encode(['status' => 'error', 'message' => 'ID de tarea inválido.']);
    exit;
}

try {
    // 1. Datos principales de la tarea
    $sql = "SELECT 
                t.*,
                c.nombre AS categoria_nombre,
                ca.nombre_completo AS creador_nombre,
                resp.nombre_completo AS responsable_nombre,
                (SELECT GROUP_CONCAT(u.nombre_completo SEPARATOR ', ')
                 FROM log_tareas_asignaciones ta
                 JOIN usuarios u ON ta.id_usuario = u.id
                 WHERE ta.id_tarea = t.id_tarea AND ta.id_usuario != t.id_asignado) AS colaboradores_nombres,
                dest.nombre AS destino_nombre,
                ar.nombre AS area_nombre,
                pt.numero_orden AS numero_orden_pedido,
                pt.solicitante_email,
                pt.solicitante_real_nombre,
                pt.solicitante_telefono,
                pt.descripcion_sintomas AS descripcion_pedido,
                pt.fecha_emision AS fecha_pedido,
                pt.fecha_requerida,
                u_aux.nombre_completo AS auxiliar_nombre
            FROM log_tareas t
            LEFT JOIN log_categorias c ON t.id_categoria = c.id_categoria
            LEFT JOIN usuarios ca ON t.id_creador = ca.id
            LEFT JOIN usuarios resp ON t.id_asignado = resp.id
            LEFT JOIN log_pedidos_trabajo pt ON t.id_pedido_origen = pt.id_pedido
            LEFT JOIN log_destinos_internos dest ON pt.id_destino_interno = dest.id_destino
            LEFT JOIN log_areas ar ON pt.id_area = ar.id_area
            LEFT JOIN usuarios u_aux ON pt.id_auxiliar = u_aux.id
            WHERE t.id_tarea = :id_tarea";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([':id_tarea' => $id_tarea]);
    $tarea = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tarea) {
        echo json_encode(['status' => 'error', 'message' => 'Tarea no encontrada.']);
        exit;
    }

    // 2. Actualizaciones / Novedades
    $sql_act = "SELECT a.id_actualizacion, a.id_usuario, a.contenido, a.fecha_actualizacion,
                       u.nombre_completo AS usuario_nombre, u.rol AS usuario_rol,
                       a.causo_reserva
                FROM log_actualizaciones_tarea a
                JOIN usuarios u ON a.id_usuario = u.id
                WHERE a.id_tarea = :id_tarea
                ORDER BY a.fecha_actualizacion DESC";
    $stmt_act = $pdo->prepare($sql_act);
    $stmt_act->execute([':id_tarea' => $id_tarea]);
    $actualizaciones = $stmt_act->fetchAll(PDO::FETCH_ASSOC);

    // 3. Adjuntos iniciales
    $sql_adj = "SELECT id_adjunto, nombre_archivo, ruta_archivo
                FROM log_adjuntos_tarea
                WHERE id_tarea = :id_tarea AND tipo_adjunto = 'inicial'
                ORDER BY fecha_subida ASC";
    $stmt_adj = $pdo->prepare($sql_adj);
    $stmt_adj->execute([':id_tarea' => $id_tarea]);
    $adjuntos_iniciales = $stmt_adj->fetchAll(PDO::FETCH_ASSOC);

    // 4. Adjuntos finales
    $sql_adj_fin = "SELECT id_adjunto, nombre_archivo, ruta_archivo, fecha_subida
                   FROM log_adjuntos_tarea
                   WHERE id_tarea = :id_tarea AND tipo_adjunto = 'final'
                   ORDER BY fecha_subida DESC";
    $stmt_adj_fin = $pdo->prepare($sql_adj_fin);
    $stmt_adj_fin->execute([':id_tarea' => $id_tarea]);
    $adjuntos_finales = $stmt_adj_fin->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status'          => 'success',
        'tarea'           => $tarea,
        'actualizaciones' => $actualizaciones,
        'adjuntos_ini'    => $adjuntos_iniciales,
        'adjuntos_fin'    => $adjuntos_finales,
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    echo json_encode([
        'status'  => 'error',
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
